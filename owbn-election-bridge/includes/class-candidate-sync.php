<?php
defined( 'ABSPATH' ) || exit;

class OEB_Candidate_Sync {

	public static function register(): void {
		add_action( 'transition_post_status', [ __CLASS__, 'on_status_change' ], 10, 3 );
	}

	public static function on_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		$vote_id = get_post_meta( $post->ID, '_oeb_vote_id', true );
		if ( ! $vote_id ) {
			return;
		}

		$vote_id = absint( $vote_id );

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			self::add_candidate( $vote_id, $post );
			return;
		}

		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$vote = WPVP_Database::get_vote( $vote_id );
			if ( $vote && ! in_array( $vote->voting_stage, [ 'draft', 'scheduled' ], true ) ) {
				error_log( sprintf(
					'[OEB] Candidate post %d un-published while vote %d is "%s" — removing from voting options (candidate withdrawal).',
					$post->ID, $vote_id, $vote->voting_stage
				) );
			}
			WPVP_Database::remove_voting_option( $vote_id, $post->ID );
		}
	}

	private static function add_candidate( int $vote_id, \WP_Post $post ): void {
		$vote = WPVP_Database::get_vote( $vote_id );
		if ( ! $vote ) {
			return;
		}

		// Never modify a vote that's already open or beyond.
		if ( ! in_array( $vote->voting_stage, [ 'draft', 'scheduled' ], true ) ) {
			error_log( sprintf(
				'[OEB] Candidate post %d published but vote %d is "%s" — not adding to voting options.',
				$post->ID, $vote_id, $vote->voting_stage
			) );
			return;
		}

		$result = WPVP_Database::add_voting_option( $vote_id, [
			'text'        => $post->post_title,
			'description' => '',
			'post_id'     => $post->ID,
		] );

		if ( ! $result || 'singleton' !== $vote->voting_type ) {
			return;
		}

		// Count only actual candidates (have post_id), not Abstain/Reject All Candidates.
		$options    = WPVP_Database::get_voting_options( $vote_id );
		$candidates = array_filter( $options, function ( $opt ) {
			return ! empty( $opt['post_id'] );
		} );
		if ( count( $candidates ) > 2 ) {
			WPVP_Database::update_vote( $vote_id, [ 'voting_type' => 'rcv' ] );
		}
	}
}
