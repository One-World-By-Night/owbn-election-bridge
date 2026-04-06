<?php
defined( 'ABSPATH' ) || exit;

class OEB_Application_Form {

	const ACTION = 'oeb_submit_application';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle_submit' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
	}

	public static function maybe_enqueue_assets(): void {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'oeb_apply' ) ) {
			return;
		}
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], '4.1.0', true );

		$site_key = get_option( 'oeb_recaptcha_site_key', '' );
		if ( $site_key ) {
			wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . urlencode( $site_key ), [], null, true );
		}
	}

	public static function render(): string {
		$election = OEB_Election_Set::get_active();

		if ( ! $election ) {
			return '<div class="oeb-notice oeb-notice--info"><p>' . esc_html__( 'No election is currently open for applications.', 'owbn-election-bridge' ) . '</p></div>';
		}

		$now   = current_time( 'Y-m-d' );
		$start = $election->application_start;
		$end   = $election->application_end;

		if ( $now < $start ) {
			return '<div class="oeb-notice oeb-notice--info"><p>' . sprintf(
				esc_html__( 'Applications open on %s.', 'owbn-election-bridge' ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start ) ) )
			) . '</p></div>';
		}

		if ( $end && $now > $end ) {
			return '<div class="oeb-notice oeb-notice--info"><p>' . esc_html__( 'The application period has closed.', 'owbn-election-bridge' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = sanitize_key( $_GET['oeb_status'] ?? '' );
		$message = '';
		if ( 'success' === $status ) {
			$message = '<div class="oeb-notice oeb-notice--success"><p>' . esc_html__( 'Your application has been submitted and is pending review.', 'owbn-election-bridge' ) . '</p></div>';
		} elseif ( 'error' === $status ) {
			$message = '<div class="oeb-notice oeb-notice--error"><p>' . esc_html__( 'There was an error submitting your application. Please try again.', 'owbn-election-bridge' ) . '</p></div>';
		} elseif ( 'duplicate' === $status ) {
			$message = '<div class="oeb-notice oeb-notice--error"><p>' . esc_html__( 'You have already submitted an application for this position.', 'owbn-election-bridge' ) . '</p></div>';
		} elseif ( 'captcha' === $status ) {
			$message = '<div class="oeb-notice oeb-notice--error"><p>' . esc_html__( 'Verification failed. Please try again.', 'owbn-election-bridge' ) . '</p></div>';
		}

		$current_user = is_user_logged_in() ? wp_get_current_user() : null;
		$is_guest     = ! is_user_logged_in();
		$site_key     = get_option( 'oeb_recaptcha_site_key', '' );

		ob_start();
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$template = locate_template( 'owbn-election-bridge/application-form.php' );
		if ( ! $template ) {
			$template = OEB_PATH . 'templates/application-form.php';
		}

		include $template;
		return ob_get_clean();
	}

	public static function handle_submit(): void {
		check_admin_referer( self::ACTION );

		// reCAPTCHA v3 verification.
		$secret = get_option( 'oeb_recaptcha_secret_key', '' );
		if ( $secret ) {
			$token = sanitize_text_field( $_POST['g-recaptcha-response'] ?? '' );
			if ( ! self::verify_recaptcha( $secret, $token ) ) {
				self::redirect_back( 'captcha' );
				return;
			}
		}

		$election = OEB_Election_Set::get_active();
		if ( ! $election ) {
			wp_die( esc_html__( 'No active election set.', 'owbn-election-bridge' ), 400 );
		}

		$now = current_time( 'Y-m-d' );
		if ( $now < $election->application_start || ( $election->application_end && $now > $election->application_end ) ) {
			wp_die( esc_html__( 'Applications are not open.', 'owbn-election-bridge' ), 400 );
		}

		$position_slug  = sanitize_key( wp_unslash( $_POST['position'] ?? '' ) );
		$name           = sanitize_text_field( wp_unslash( $_POST['applicant_name'] ?? '' ) );
		$email          = sanitize_email( wp_unslash( $_POST['applicant_email'] ?? '' ) );
		$chronicle      = sanitize_text_field( wp_unslash( $_POST['home_chronicle'] ?? '' ) );
		$approving_group = sanitize_text_field( wp_unslash( $_POST['approving_group'] ?? '' ) );
		$source_lang    = sanitize_key( wp_unslash( $_POST['source_language'] ?? 'en' ) );
		$intro          = wp_kses_post( wp_unslash( $_POST['introduction'] ?? '' ) );
		$experience     = wp_kses_post( wp_unslash( $_POST['experience'] ?? '' ) );
		$statement      = wp_kses_post( wp_unslash( $_POST['personal_statement'] ?? '' ) );
		$goals          = wp_kses_post( wp_unslash( $_POST['goals'] ?? '' ) );

		if ( empty( $position_slug ) || empty( $name ) || empty( $email ) || empty( $chronicle ) || empty( $intro ) ) {
			self::redirect_back( 'error' );
			return;
		}

		$position = null;
		foreach ( $election->positions as $pos ) {
			if ( ( $pos['coordinator_slug'] ?? '' ) === $position_slug ) {
				$position = $pos;
				break;
			}
		}

		if ( ! $position || empty( $position['category_id'] ) || empty( $position['vote_id'] ) ) {
			self::redirect_back( 'error' );
			return;
		}

		// Duplicate check: by user ID if logged in, by email if guest.
		if ( is_user_logged_in() ) {
			$existing = get_posts( [
				'post_type'      => 'post',
				'post_status'    => [ 'pending', 'publish', 'draft' ],
				'author'         => get_current_user_id(),
				'meta_key'       => '_oeb_vote_id',
				'meta_value'     => absint( $position['vote_id'] ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );
		} else {
			$existing = get_posts( [
				'post_type'      => 'post',
				'post_status'    => [ 'pending', 'publish', 'draft' ],
				'meta_query'     => [
					'relation' => 'AND',
					[ 'key' => '_oeb_vote_id', 'value' => absint( $position['vote_id'] ) ],
					[ 'key' => '_oeb_applicant_email', 'value' => $email ],
				],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );
		}
		if ( ! empty( $existing ) ) {
			self::redirect_back( 'duplicate' );
			return;
		}

		if ( ! in_array( $source_lang, [ 'en', 'pt_BR' ], true ) ) {
			$source_lang = 'en';
		}

		$content_parts = [];
		$content_parts[] = '<h3>' . esc_html__( 'Introduction and Background', 'owbn-election-bridge' ) . '</h3>';
		$content_parts[] = $intro;

		if ( ! empty( $experience ) ) {
			$content_parts[] = '<h3>' . esc_html__( 'Administrative Experience', 'owbn-election-bridge' ) . '</h3>';
			$content_parts[] = $experience;
		}

		if ( ! empty( $statement ) ) {
			$content_parts[] = '<h3>' . esc_html__( 'Personal Statement', 'owbn-election-bridge' ) . '</h3>';
			$content_parts[] = $statement;
		}

		if ( ! empty( $goals ) ) {
			$content_parts[] = '<h3>' . esc_html__( 'Goals', 'owbn-election-bridge' ) . '</h3>';
			$content_parts[] = $goals;
		}

		$post_id = wp_insert_post( [
			'post_title'    => $name,
			'post_content'  => implode( "\n\n", $content_parts ),
			'post_status'   => 'pending',
			'post_type'     => 'post',
			'post_author'   => is_user_logged_in() ? get_current_user_id() : 0,
			'post_category' => [ absint( $position['category_id'] ) ],
		], true );

		if ( is_wp_error( $post_id ) ) {
			self::redirect_back( 'error' );
			return;
		}

		update_post_meta( $post_id, '_oeb_election_set_id', absint( $election->id ) );
		update_post_meta( $post_id, '_oeb_position_slug', $position_slug );
		update_post_meta( $post_id, '_oeb_vote_id', absint( $position['vote_id'] ) );
		update_post_meta( $post_id, '_oeb_applicant_email', $email );
		update_post_meta( $post_id, '_oeb_home_chronicle', $chronicle );
		update_post_meta( $post_id, '_oeb_source_language', $source_lang );
		if ( ! empty( $approving_group ) ) {
			update_post_meta( $post_id, '_oeb_approving_group', $approving_group );
		}

		self::redirect_back( 'success' );
	}

	private static function verify_recaptcha( string $secret, string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'body' => [
				'secret'   => $secret,
				'response' => $token,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $body['success'] ) && ( $body['score'] ?? 0 ) >= 0.5;
	}

	private static function redirect_back( string $status ): void {
		$referer = wp_get_referer() ?: home_url();
		wp_safe_redirect( add_query_arg( 'oeb_status', $status, $referer ) );
		exit;
	}
}
