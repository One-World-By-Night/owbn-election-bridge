<?php
defined( 'ABSPATH' ) || exit;

class OEB_Application_Form {

	const ACTION = 'oeb_submit_application';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle_nopriv' ] );
	}

	public static function handle_nopriv(): void {
		wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url() ) );
		exit;
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

		if ( ! is_user_logged_in() ) {
			return '<div class="oeb-notice oeb-notice--info"><p>' . sprintf(
				esc_html__( 'You must %s to submit an application.', 'owbn-election-bridge' ),
				'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'log in', 'owbn-election-bridge' ) . '</a>'
			) . '</p></div>';
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
		}

		$current_user = wp_get_current_user();

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
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'owbn-election-bridge' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$election = OEB_Election_Set::get_active();
		if ( ! $election ) {
			wp_die( esc_html__( 'No active election set.', 'owbn-election-bridge' ), 400 );
		}

		$now = current_time( 'Y-m-d' );
		if ( $now < $election->application_start || ( $election->application_end && $now > $election->application_end ) ) {
			wp_die( esc_html__( 'Applications are not open.', 'owbn-election-bridge' ), 400 );
		}

		$position_slug = sanitize_key( wp_unslash( $_POST['position'] ?? '' ) );
		$name          = sanitize_text_field( wp_unslash( $_POST['applicant_name'] ?? '' ) );
		$email         = sanitize_email( wp_unslash( $_POST['applicant_email'] ?? '' ) );
		$chronicle     = sanitize_text_field( wp_unslash( $_POST['home_chronicle'] ?? '' ) );
		$source_lang   = sanitize_key( wp_unslash( $_POST['source_language'] ?? 'en' ) );
		$intro         = wp_kses_post( wp_unslash( $_POST['introduction'] ?? '' ) );
		$experience    = wp_kses_post( wp_unslash( $_POST['experience'] ?? '' ) );
		$statement     = wp_kses_post( wp_unslash( $_POST['personal_statement'] ?? '' ) );
		$goals         = wp_kses_post( wp_unslash( $_POST['goals'] ?? '' ) );

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

		// One application per user per position.
		$existing = get_posts( [
			'post_type'      => 'post',
			'post_status'    => [ 'pending', 'publish', 'draft' ],
			'author'         => get_current_user_id(),
			'meta_key'       => '_oeb_vote_id',
			'meta_value'     => absint( $position['vote_id'] ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );
		if ( ! empty( $existing ) ) {
			self::redirect_back( 'duplicate' );
			return;
		}

		if ( ! in_array( $source_lang, [ 'en', 'pt_BR' ], true ) ) {
			$source_lang = 'en';
		}

		$content_parts = [];
		$content_parts[] = '<h2>' . esc_html__( 'Introduction and Background', 'owbn-election-bridge' ) . '</h2>';
		$content_parts[] = $intro;

		if ( ! empty( $experience ) ) {
			$content_parts[] = '<h2>' . esc_html__( 'Administrative Experience', 'owbn-election-bridge' ) . '</h2>';
			$content_parts[] = $experience;
		}

		if ( ! empty( $statement ) ) {
			$content_parts[] = '<h2>' . esc_html__( 'Personal Statement', 'owbn-election-bridge' ) . '</h2>';
			$content_parts[] = $statement;
		}

		if ( ! empty( $goals ) ) {
			$content_parts[] = '<h2>' . esc_html__( 'Goals', 'owbn-election-bridge' ) . '</h2>';
			$content_parts[] = $goals;
		}

		$post_id = wp_insert_post( [
			'post_title'    => $name,
			'post_content'  => implode( "\n\n", $content_parts ),
			'post_status'   => 'pending',
			'post_type'     => 'post',
			'post_author'   => get_current_user_id(),
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

		self::redirect_back( 'success' );
	}

	private static function redirect_back( string $status ): void {
		$referer = wp_get_referer() ?: home_url();
		wp_safe_redirect( add_query_arg( 'oeb_status', $status, $referer ) );
		exit;
	}
}
