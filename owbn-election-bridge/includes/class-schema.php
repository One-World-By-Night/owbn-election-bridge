<?php
defined( 'ABSPATH' ) || exit;

class OEB_Schema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'oeb_election_sets';
	}

	public static function activate(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			year YEAR NOT NULL,
			application_start DATE NOT NULL,
			application_end DATE DEFAULT NULL,
			positions LONGTEXT NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			status VARCHAR(20) DEFAULT 'draft',
			PRIMARY KEY (id),
			KEY idx_year (year),
			KEY idx_status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'oeb_db_version', OEB_VERSION );
	}
}
