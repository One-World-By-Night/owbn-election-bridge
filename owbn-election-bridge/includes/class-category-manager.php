<?php
defined( 'ABSPATH' ) || exit;

class OEB_Category_Manager {

	public static function ensure_year_category( int $year ): int {
		$slug = strval( $year );
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( $term ) {
			return $term->term_id;
		}

		$result = wp_insert_term( $slug, 'category', [ 'slug' => $slug ] );

		if ( is_wp_error( $result ) ) {
			$term = get_term_by( 'name', $slug, 'category' );
			return $term ? $term->term_id : 0;
		}

		return $result['term_id'];
	}

	// Set ID in slug guarantees uniqueness across special elections in the same year.
	public static function ensure_position_category( int $year, int $set_id, string $slug, string $title ): int {
		$parent_id = self::ensure_year_category( $year );
		if ( ! $parent_id ) {
			return 0;
		}

		$cat_slug = sanitize_title( $year . '-s' . $set_id . '-' . $slug );
		$term     = get_term_by( 'slug', $cat_slug, 'category' );

		if ( $term && (int) $term->parent === $parent_id ) {
			return $term->term_id;
		}

		$result = wp_insert_term( $title, 'category', [
			'slug'   => $cat_slug,
			'parent' => $parent_id,
		] );

		if ( is_wp_error( $result ) ) {
			$term = get_term_by( 'slug', $cat_slug, 'category' );
			return $term ? $term->term_id : 0;
		}

		return $result['term_id'];
	}

	public static function get_position_category_id( int $year, int $set_id, string $slug ): int {
		$cat_slug = sanitize_title( $year . '-s' . $set_id . '-' . $slug );
		$term     = get_term_by( 'slug', $cat_slug, 'category' );
		return $term ? $term->term_id : 0;
	}
}
