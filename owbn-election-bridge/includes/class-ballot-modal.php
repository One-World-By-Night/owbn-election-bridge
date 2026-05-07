<?php
/**
 * OEB_Ballot_Modal — AJAX handler that returns a candidate's full
 * application HTML for the in-modal preview on the ballot page.
 *
 * The candidate post is the source of truth. We return its rendered content
 * so the modal mirrors what the standalone post page would display, then run
 * it through TranslatePress (if active) so the modal text matches the page's
 * current language.
 */

defined( 'ABSPATH' ) || exit;

class OEB_Ballot_Modal {

	public static function register(): void {
		add_action( 'wp_ajax_oeb_ballot_modal',        array( __CLASS__, 'ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_oeb_ballot_modal', array( __CLASS__, 'ajax_handler' ) );
	}

	public static function ajax_handler(): void {
		check_ajax_referer( 'oeb_ballot_modal', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Missing post_id' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => 'Candidate not found' ) );
		}

		$lang_slug = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '';

		$html  = '<h2>' . esc_html( get_the_title( $post ) ) . '</h2>';
		$html .= '<div class="oeb-ballot-modal__content">' . apply_filters( 'the_content', $post->post_content ) . '</div>';

		// Run through TP for the requested language. Mirrors the cchub modal pattern.
		$html = self::maybe_translate( $html, $lang_slug );

		wp_send_json_success( array(
			'html'      => $html,
			'title'     => get_the_title( $post ),
			'permalink' => get_permalink( $post ),
		) );
	}

	private static function maybe_translate( string $html, string $lang_slug ): string {
		if ( $lang_slug === '' || ! class_exists( 'TRP_Translate_Press' ) ) {
			return $html;
		}
		$trp      = TRP_Translate_Press::get_trp_instance();
		$settings = $trp->get_component( 'settings' )->get_settings();
		$default  = $settings['default-language'] ?? 'en_US';
		$url_slugs = $settings['url-slugs'] ?? array();
		$target = '';
		foreach ( $url_slugs as $locale => $slug ) {
			if ( strtolower( $slug ) === strtolower( $lang_slug ) ) {
				$target = $locale;
				break;
			}
		}
		if ( $target === '' || $target === $default ) return $html;
		$renderer = $trp->get_component( 'translation_render' );
		if ( ! is_object( $renderer ) || ! method_exists( $renderer, 'translate_page' ) ) return $html;
		global $TRP_LANGUAGE;
		$prev = $TRP_LANGUAGE;
		$TRP_LANGUAGE = $target;
		try {
			$out = $renderer->translate_page( $html );
		} catch ( \Throwable $e ) {
			$out = $html;
		}
		$TRP_LANGUAGE = $prev;
		return $out;
	}
}
