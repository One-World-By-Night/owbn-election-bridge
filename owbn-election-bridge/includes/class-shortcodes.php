<?php
defined( 'ABSPATH' ) || exit;

class OEB_Shortcodes {

	public static function register(): void {
		add_shortcode( 'oeb_apply', [ __CLASS__, 'render_apply' ] );
		add_shortcode( 'oeb_candidates', [ __CLASS__, 'render_candidates' ] );
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
