<?php
/**
 * OEB_Ballot — data prep for the unified ballot view.
 *
 * Resolves every "active" election set into a flat list of cards that the
 * frontend template renders. Each card represents one wpvp vote (one position).
 * Per-card user state (eligibility, has-voted, choice) is included so the
 * template can branch state without further queries.
 */

defined( 'ABSPATH' ) || exit;

class OEB_Ballot {

	/**
	 * Get all renderable election cards for the unified ballot view.
	 *
	 * Pulls every set with status='active' (one at a time, by design) and
	 * flattens its positions into card structures. If $user_id is provided,
	 * eligibility, has-voted, and current choice are populated per card.
	 *
	 * @param int $user_id WP user ID (0 for logged-out viewers).
	 * @return array { sets: [...], cards: [...] }
	 */
	public static function get_renderable_data( int $user_id = 0 ): array {
		$sets  = OEB_Election_Set::get_all( array( 'status' => 'active' ) );
		$cards = array();
		$set_summaries = array();

		foreach ( $sets as $set ) {
			$set_summaries[] = self::summarize_set( $set );
			foreach ( $set->positions as $position ) {
				$card = self::build_card( $position, $set, $user_id );
				if ( $card && 'open' === $card['state'] ) {
					$cards[] = $card;
				}
			}
		}

		// Sort by position title (alphabetical) since all cards are 'open'.
		usort( $cards, function ( $a, $b ) {
			return strcmp( strtolower( $a['position_title'] ), strtolower( $b['position_title'] ) );
		} );

		return array(
			'sets'  => $set_summaries,
			'cards' => $cards,
		);
	}

	/**
	 * Build a single card for a known vote_id (used by [oeb_ballot_card]).
	 *
	 * @return array|null Null if the vote is not found or not part of any active set.
	 */
	public static function get_card( int $vote_id, int $user_id = 0 ): ?array {
		if ( $vote_id <= 0 ) return null;
		$sets = OEB_Election_Set::get_all( array( 'status' => 'active' ) );
		foreach ( $sets as $set ) {
			foreach ( $set->positions as $position ) {
				if ( (int) ( $position['vote_id'] ?? 0 ) === $vote_id ) {
					return self::build_card( $position, $set, $user_id );
				}
			}
		}
		return null;
	}

	/**
	 * Compress an election set down to the bits the page header shows.
	 */
	private static function summarize_set( object $set ): array {
		return array(
			'id'                => (int) $set->id,
			'year'              => (int) $set->year,
			'label'             => self::format_set_label( $set ),
			'application_start' => $set->application_start ?? '',
			'application_end'   => $set->application_end ?? '',
			'apply_page_url'    => self::apply_page_url( $set ),
		);
	}

	private static function format_set_label( object $set ): string {
		$title = isset( $set->title ) && $set->title ? $set->title : '';
		if ( $title ) return $title;
		return sprintf( '%d Genre Elections', (int) $set->year );
	}

	private static function apply_page_url( object $set ): string {
		$page_id = (int) ( $set->page_id ?? 0 );
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		return '';
	}

	/**
	 * Build a single card data array for one position. Resolves the vote, its
	 * options, candidate posts, and the user's per-vote state.
	 *
	 * @return array|null Null if the position has no vote (skip in render).
	 */
	private static function build_card( array $position, object $set, int $user_id ): ?array {
		$vote_id = (int) ( $position['vote_id'] ?? 0 );
		if ( $vote_id <= 0 ) return null;

		$vote = WPVP_Database::get_vote( $vote_id );
		if ( ! $vote ) return null;

		$options = WPVP_Database::get_voting_options( $vote_id );
		if ( ! is_array( $options ) ) $options = array();

		$state = self::compute_state( $vote, $set );

		// Per-user state.
		$user_logged_in = $user_id > 0;
		$user_has_voted = $user_logged_in && WPVP_Database::user_has_voted( $user_id, $vote_id );
		$user_eligible  = $user_logged_in && WPVP_Permissions::can_cast_vote( $user_id, $vote_id );
		$user_choice    = $user_has_voted ? self::get_user_choice( $vote_id, $user_id ) : null;

		return array(
			'vote_id'        => $vote_id,
			'voting_type'    => $vote->voting_type,
			'voting_stage'   => $vote->voting_stage,
			'state'          => $state,
			'set_id'         => (int) $set->id,
			'set_year'       => (int) $set->year,
			'set_label'      => self::format_set_label( $set ),
			'apply_page_url' => self::apply_page_url( $set ),
			'position_slug'  => sanitize_key( $position['coordinator_slug'] ?? '' ),
			'position_title' => self::resolve_position_title( $position ),
			'description'    => self::short_description( $vote->proposal_description ?? '' ),
			'opens_at'       => $vote->opening_date ?? '',
			'closes_at'      => $vote->closing_date ?? '',
			'options'        => self::shape_options( $options ),
			'view_url'       => self::vote_view_url( $vote ),
			'results_url'    => self::vote_results_url( $vote ),
			'user_logged_in' => $user_logged_in,
			'user_eligible'  => $user_eligible,
			'user_has_voted' => $user_has_voted,
			'user_choice'    => $user_choice,
		);
	}

	/**
	 * Determine the card state machine value.
	 *
	 *   apps_open                   — set has live application window, vote not yet open
	 *   apps_closed_voting_pending  — applications closed, vote still in scheduled/draft
	 *   open                        — vote is currently accepting ballots
	 *   closed                      — vote completed
	 */
	private static function compute_state( object $vote, object $set ): string {
		if ( 'open' === $vote->voting_stage ) {
			return 'open';
		}
		if ( in_array( $vote->voting_stage, array( 'closed', 'completed', 'archived', 'withdrawn' ), true ) ) {
			return 'closed';
		}

		$today = current_time( 'Y-m-d' );
		$apps_open = ! empty( $set->application_start )
			&& ! empty( $set->application_end )
			&& $today >= $set->application_start
			&& $today <= $set->application_end;
		if ( $apps_open ) {
			return 'apps_open';
		}
		return 'apps_closed_voting_pending';
	}

	/**
	 * Look up the user's ballot for a vote and return their stored choice.
	 * For singleton: returns the string they chose. For ranked: returns the
	 * ordered array. For other types: returns the raw decoded ballot_data.
	 */
	private static function get_user_choice( int $vote_id, int $user_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT ballot_data FROM ' . WPVP_Database::ballots_table() . ' WHERE vote_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1',
			$vote_id,
			$user_id
		) );
		if ( ! $row ) return null;
		$decoded = json_decode( $row->ballot_data, true );
		if ( is_array( $decoded ) && isset( $decoded['choice'] ) ) {
			return $decoded['choice'];
		}
		return $decoded;
	}

	/**
	 * Resolve a stable position title.
	 *
	 * Prefers the title stored on the position. Falls back to the coordinator
	 * registry (owc_entity_get_title) and then to a humanized slug.
	 */
	private static function resolve_position_title( array $position ): string {
		$title = isset( $position['coordinator_title'] ) ? trim( (string) $position['coordinator_title'] ) : '';
		if ( $title !== '' ) return $title;
		$slug = sanitize_key( $position['coordinator_slug'] ?? '' );
		if ( function_exists( 'owc_entity_get_title' ) ) {
			$resolved = owc_entity_get_title( 'coordinator', $slug );
			if ( $resolved ) return $resolved;
		}
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * First sentence (~140 chars) of the vote's proposal description, with
	 * shortcodes stripped. Long-form content stays in the View page.
	 */
	private static function short_description( string $raw ): string {
		$stripped = trim( wp_strip_all_tags( strip_shortcodes( $raw ) ) );
		if ( $stripped === '' ) return '';
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $stripped ) > 140 ) {
			return rtrim( mb_substr( $stripped, 0, 140 ) ) . '…';
		}
		return $stripped;
	}

	/**
	 * Shape voting_options for the template. Marks Abstain / Reject All so the
	 * card can render them in their own row separate from real candidates.
	 */
	private static function shape_options( array $options ): array {
		$out = array();
		foreach ( $options as $opt ) {
			$text  = (string) ( $opt['text'] ?? '' );
			if ( $text === '' ) continue;
			$desc  = (string) ( $opt['description'] ?? '' );
			$pid   = isset( $opt['post_id'] ) ? (int) $opt['post_id'] : 0;
			$is_special = in_array( strtolower( $text ), array( 'abstain', 'reject all candidates' ), true ) || $pid <= 0;
			$out[] = array(
				'text'        => $text,
				'description' => $desc,
				'post_id'     => $pid > 0 ? $pid : 0,
				'is_special'  => $is_special,
			);
		}
		return $out;
	}

	/**
	 * URL of the vote detail page. Falls back to the votes list root.
	 */
	private static function vote_view_url( object $vote ): string {
		$base = home_url( '/votes/' );
		if ( ! empty( $vote->id ) ) {
			return add_query_arg( 'vote_id', (int) $vote->id, $base );
		}
		return $base;
	}

	/**
	 * URL of the vote results page (closed vote view).
	 */
	private static function vote_results_url( object $vote ): string {
		return add_query_arg( 'results', '1', self::vote_view_url( $vote ) );
	}
}
