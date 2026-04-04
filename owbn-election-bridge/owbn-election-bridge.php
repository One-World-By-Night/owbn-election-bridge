<?php
/**
 * Plugin Name: OWBN Election Bridge
 * Description: Coordinator election management bridging candidate applications with wp-voting-plugin.
 * Version:     1.0.0
 * Author:      OWBN Dev Team
 * License:     GPL-2.0-or-later
 * Text Domain: owbn-election-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'OEB_VERSION', '1.0.0' );
define( 'OEB_FILE', __FILE__ );
define( 'OEB_PATH', plugin_dir_path( __FILE__ ) );
define( 'OEB_URL', plugin_dir_url( __FILE__ ) );

function oeb_check_dependencies(): bool {
	$missing = [];

	if ( ! class_exists( 'WPVP_Plugin' ) ) {
		$missing[] = 'WP Voting Plugin';
	}
	if ( ! function_exists( 'owc_get_coordinators' ) ) {
		$missing[] = 'OWBN Client (owbn-core)';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			printf(
				'<div class="notice notice-error"><p><strong>OWBN Election Bridge</strong> requires: %s</p></div>',
				esc_html( implode( ', ', $missing ) )
			);
		} );
		return false;
	}

	return true;
}

function oeb_init(): void {
	if ( ! oeb_check_dependencies() ) {
		return;
	}

	require_once OEB_PATH . 'includes/class-schema.php';
	require_once OEB_PATH . 'includes/class-election-set.php';
	require_once OEB_PATH . 'includes/class-category-manager.php';
	require_once OEB_PATH . 'includes/class-candidate-sync.php';
	require_once OEB_PATH . 'includes/class-application-form.php';
	require_once OEB_PATH . 'includes/class-shortcodes.php';
	require_once OEB_PATH . 'includes/class-cron.php';

	OEB_Candidate_Sync::register();
	OEB_Shortcodes::register();
	OEB_Application_Form::register();
	OEB_Cron::register();

	if ( is_admin() ) {
		require_once OEB_PATH . 'includes/admin/class-admin-page.php';
		require_once OEB_PATH . 'includes/admin/class-election-editor.php';
		OEB_Admin_Page::init();
	}
}
add_action( 'plugins_loaded', 'oeb_init' );

register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-schema.php';
	OEB_Schema::activate();
} );
