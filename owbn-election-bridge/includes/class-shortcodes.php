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

		// Fall back to active election set if not specified.
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

		$category_id = OEB_Category_Manager::get_position_category_id( $year, $set_id, $position_slug );
		if ( ! $category_id ) {
			return '<p>' . esc_html__( 'No candidates have been approved yet.', 'owbn-election-bridge' ) . '</p>';
		}

		$query = new WP_Query( [
			'cat'            => $category_id,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No candidates have been approved yet.', 'owbn-election-bridge' ) . '</p>';
		}

		ob_start();

		$template = locate_template( 'owbn-election-bridge/candidate-list.php' );
		if ( ! $template ) {
			$template = OEB_PATH . 'templates/candidate-list.php';
		}

		include $template;
		wp_reset_postdata();

		return ob_get_clean();
	}
}
