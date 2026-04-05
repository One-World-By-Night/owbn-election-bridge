<?php
defined( 'ABSPATH' ) || exit;

class OEB_Election_Set {

	public static function get( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . OEB_Schema::table_name() . ' WHERE id = %d', $id )
		);

		if ( $row ) {
			$row->positions = json_decode( $row->positions, true ) ?: [];
		}

		return $row;
	}

	public static function get_all( array $filters = [] ): array {
		global $wpdb;

		$table = OEB_Schema::table_name();
		$where = [ '1=1' ];
		$args  = [];

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( $filters['status'] );
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY year DESC, id DESC';

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$args );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		foreach ( $rows as $row ) {
			$row->positions = json_decode( $row->positions, true ) ?: [];
		}

		return $rows;
	}

	public static function get_active(): ?object {
		$results = self::get_all( [ 'status' => 'active' ] );
		return ! empty( $results ) ? $results[0] : null;
	}

	// Derive year from application_start date.
	public static function derive_year( string $start_date ): int {
		if ( empty( $start_date ) ) {
			return intval( gmdate( 'Y' ) );
		}
		return intval( gmdate( 'Y', strtotime( $start_date ) ) );
	}

	/**
	 * @return int|false Set ID on success, false on failure.
	 */
	public static function save( array $data ) {
		global $wpdb;

		$table = OEB_Schema::table_name();
		$start = sanitize_text_field( $data['application_start'] ?? '' );

		$el_type = sanitize_key( $data['election_type'] ?? 'full_term' );
		if ( ! in_array( $el_type, [ 'full_term', 'special' ], true ) ) {
			$el_type = 'full_term';
		}

		$row = [
			'name'              => sanitize_text_field( $data['name'] ?? '' ),
			'election_type'     => $el_type,
			'year'              => self::derive_year( $start ),
			'application_start' => $start,
			'application_end'   => ! empty( $data['application_end'] ) ? sanitize_text_field( $data['application_end'] ) : null,
			'positions'         => wp_json_encode( $data['positions'] ?? [] ),
			'status'            => sanitize_key( $data['status'] ?? 'draft' ),
		];

		$formats = [ '%s', '%s', '%d', '%s', '%s', '%s', '%s' ];

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id ) {
			$row['updated_at'] = current_time( 'mysql' );
			$formats[]         = '%s';
			$result = $wpdb->update( $table, $row, [ 'id' => $id ], $formats, [ '%d' ] );
			return false !== $result ? $id : false;
		}

		$row['created_by'] = get_current_user_id();
		$row['created_at'] = current_time( 'mysql' );
		$formats[]         = '%d';
		$formats[]         = '%s';
		$result = $wpdb->insert( $table, $row, $formats );
		return $result ? $wpdb->insert_id : false;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( OEB_Schema::table_name(), [ 'id' => $id ], [ '%d' ] );
		return false !== $result;
	}

	public static function get_position( int $set_id, string $coordinator_slug ): ?array {
		$set = self::get( $set_id );
		if ( ! $set ) {
			return null;
		}
		foreach ( $set->positions as $pos ) {
			if ( ( $pos['coordinator_slug'] ?? '' ) === $coordinator_slug ) {
				return $pos;
			}
		}
		return null;
	}

	public static function update_position( int $set_id, string $slug, array $updates ): bool {
		$set = self::get( $set_id );
		if ( ! $set ) {
			return false;
		}

		$found = false;
		foreach ( $set->positions as &$pos ) {
			if ( ( $pos['coordinator_slug'] ?? '' ) === $slug ) {
				$pos   = array_merge( $pos, $updates );
				$found = true;
				break;
			}
		}
		unset( $pos );

		if ( ! $found ) {
			return false;
		}

		return false !== self::save( [
			'id'                => $set_id,
			'name'              => $set->name,
			'election_type'     => $set->election_type ?? 'full_term',
			'application_start' => $set->application_start,
			'application_end'   => $set->application_end,
			'positions'         => $set->positions,
			'status'            => $set->status,
		] );
	}
}
