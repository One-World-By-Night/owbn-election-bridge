<?php
defined( 'ABSPATH' ) || exit;

class OEB_Shortcodes {

	public static function register(): void {
		add_shortcode( 'oeb_apply', [ __CLASS__, 'render_apply' ] );
		add_shortcode( 'oeb_candidates', [ __CLASS__, 'render_candidates' ] );
		add_shortcode( 'oeb_ballot', [ __CLASS__, 'render_ballot' ] );
		add_shortcode( 'oeb_ballot_card', [ __CLASS__, 'render_ballot_card' ] );
	}

	/**
	 * Render the unified ballot view: every position from active election
	 * set(s) as a card, with submit-all bar.
	 */
	public static function render_ballot( $atts ): string {
		if ( ! class_exists( 'OEB_Ballot' ) ) return '';
		self::enqueue_ballot_assets();

		$user_id = get_current_user_id();
		$data    = OEB_Ballot::get_renderable_data( $user_id );

		$template = locate_template( 'owbn-election-bridge/ballot.php' );
		if ( ! $template ) {
			$template = OEB_PATH . 'templates/ballot.php';
		}
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Render a single ballot card for a known vote_id. Reuses the same
	 * card template so the single-vote widget matches the all-elections page.
	 *
	 *   [oeb_ballot_card vote_id="87"]
	 */
	public static function render_ballot_card( $atts ): string {
		if ( ! class_exists( 'OEB_Ballot' ) ) return '';
		$atts = shortcode_atts( array( 'vote_id' => 0 ), $atts, 'oeb_ballot_card' );
		$vote_id = absint( $atts['vote_id'] );
		if ( $vote_id <= 0 ) return '';

		$card = OEB_Ballot::get_card( $vote_id, get_current_user_id() );
		if ( ! $card ) return '';

		self::enqueue_ballot_assets();

		$template = locate_template( 'owbn-election-bridge/ballot-card.php' );
		if ( ! $template ) {
			$template = OEB_PATH . 'templates/ballot-card.php';
		}
		$can_submit = ! empty( $card['user_eligible'] ) && 'open' === $card['state'] && empty( $card['user_has_voted'] );

		ob_start();
		// Render a single card with a per-card submit button (no sticky bar).
		echo '<div class="oeb-ballot oeb-ballot--single">';
		include $template;
		if ( $can_submit ) {
			echo '<div class="oeb-ballot__sticky oeb-ballot__sticky--inline">';
			echo '<button type="button" class="oeb-ballot__submit-all button button-primary" disabled>' . esc_html__( 'Submit Vote', 'owbn-election-bridge' ) . '</button>';
			echo '<span class="oeb-ballot__status" aria-live="polite"></span>';
			echo '</div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function enqueue_ballot_assets(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) return;
		wp_enqueue_style(
			'oeb-ballot',
			OEB_URL . 'assets/ballot.css',
			array(),
			OEB_VERSION
		);
		wp_enqueue_script(
			'oeb-ballot',
			OEB_URL . 'assets/ballot.js',
			array( 'jquery' ),
			OEB_VERSION,
			true
		);
		wp_localize_script( 'oeb-ballot', 'OEB_BALLOT', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'wpvp_nonce'  => wp_create_nonce( 'wpvp_public' ),
			'oeb_nonce'   => wp_create_nonce( 'oeb_ballot_modal' ),
			'i18n'        => array(
				'submit_all'        => __( 'Submit All Votes', 'owbn-election-bridge' ),
				'submitting'        => __( 'Submitting…', 'owbn-election-bridge' ),
				'voted'             => __( 'Voted', 'owbn-election-bridge' ),
				'remaining_format'  => __( '%1$d of %2$d positions remaining', 'owbn-election-bridge' ),
				'confirm_unvoted'   => __( "You have %d unvoted positions. Submit anyway?", 'owbn-election-bridge' ),
				'submit_failed'     => __( 'Submission failed.', 'owbn-election-bridge' ),
				'all_done'          => __( 'All votes submitted.', 'owbn-election-bridge' ),
				'no_selection'      => __( 'No selection made.', 'owbn-election-bridge' ),
				'loading'           => __( 'Loading…', 'owbn-election-bridge' ),
				'load_error'        => __( 'Could not load application.', 'owbn-election-bridge' ),
			),
		) );
	}

	public static function render_apply( $atts ): string {
		return OEB_Application_Form::render();
	}

	public static function render_candidates( $atts ): string {
		$atts = shortcode_atts( [
			'position' => '',
			'year'     => '',
			'set'      => '',
		], $atts, 'oeb_candidates' );

		$position_slug = sanitize_key( $atts['position'] );
		if ( empty( $position_slug ) ) {
			return '';
		}

		$year   = absint( $atts['year'] );
		$set_id = absint( $atts['set'] );

		if ( ! $year || ! $set_id ) {
			$active = OEB_Election_Set::get_active();
			if ( $active ) {
				if ( ! $year ) {
					$year = intval( $active->year );
				}
				if ( ! $set_id ) {
					$set_id = intval( $active->id );
				}
			}
		}

		if ( ! $year || ! $set_id ) {
			return '';
		}

		$set = OEB_Election_Set::get( $set_id );
		if ( ! $set ) {
			return '';
		}

		// Find the position and its vote.
		$position = null;
		foreach ( $set->positions as $pos ) {
			if ( ( $pos['coordinator_slug'] ?? '' ) === $position_slug ) {
				$position = $pos;
				break;
			}
		}

		$vote_id = absint( $position['vote_id'] ?? 0 );
		$vote    = $vote_id ? WPVP_Database::get_vote( $vote_id ) : null;

		// Determine phase.
		$now           = current_time( 'Y-m-d' );
		$apps_open     = $set->application_start && $set->application_end && $now >= $set->application_start && $now <= $set->application_end;
		$vote_is_open  = $vote && 'open' === $vote->voting_stage;

		// Get candidates.
		$category_id = OEB_Category_Manager::get_position_category_id( $year, $set_id, $position_slug );
		$query       = null;
		if ( $category_id ) {
			$query = new WP_Query( [
				'cat'            => $category_id,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
		}

		ob_start();

		// 1. Apply link (only during application window, hidden once voting starts).
		if ( $apps_open && ! $vote_is_open ) {
			$page_id = absint( $set->page_id ?? 0 );
			if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
				echo '<p class="oeb-apply-link"><a href="' . esc_url( get_permalink( $page_id ) ) . '">';
				esc_html_e( 'Apply for this position', 'owbn-election-bridge' );
				echo '</a></p>';
			}
		}

		// 2. Current candidates.
		if ( $query && $query->have_posts() ) {
			echo '<h4>' . esc_html__( 'Candidates', 'owbn-election-bridge' ) . '</h4>';

			$template = locate_template( 'owbn-election-bridge/candidate-list.php' );
			if ( ! $template ) {
				$template = OEB_PATH . 'templates/candidate-list.php';
			}
			include $template;
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'No candidates have been approved yet.', 'owbn-election-bridge' ) . '</p>';
		}

		// 3. Ballot preview (what options will exist).
		if ( $vote ) {
			$options = WPVP_Database::get_voting_options( $vote_id );
			if ( ! empty( $options ) ) {
				echo '<h4>' . esc_html__( 'Ballot Options', 'owbn-election-bridge' ) . '</h4>';
				echo '<ul class="oeb-ballot-preview">';
				foreach ( $options as $opt ) {
					echo '<li>' . esc_html( $opt['text'] ?? '' ) . '</li>';
				}
				echo '</ul>';
			}
		}

		return ob_get_clean();
	}
}
