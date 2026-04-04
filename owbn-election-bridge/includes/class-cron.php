<?php
defined( 'ABSPATH' ) || exit;

class OEB_Cron {

	const DISCUSSION_DAYS = 7;
	const VOTING_DAYS     = 7;

	public static function register(): void {
		add_action( 'wpvp_midnight_cron', [ __CLASS__, 'run' ] );
	}

	public static function run(): void {
		$set = OEB_Election_Set::get_active();
		if ( ! $set || empty( $set->application_end ) ) {
			return;
		}

		$today      = current_time( 'Y-m-d' );
		$vote_start = self::voting_start( $set->application_end );
		$vote_end   = self::voting_end( $set->application_end );

		// Open votes on voting start date.
		if ( $today >= $vote_start && ! self::votes_opened( $set ) ) {
			self::open_all_votes( $set );
		}

		// Close votes on voting end date.
		if ( $today >= $vote_end && ! self::votes_closed( $set ) ) {
			self::close_all_votes( $set );
		}
	}

	public static function discussion_start( string $app_end ): string {
		return gmdate( 'Y-m-d', strtotime( $app_end . ' +1 day' ) );
	}

	public static function discussion_end( string $app_end ): string {
		return gmdate( 'Y-m-d', strtotime( $app_end . ' +' . self::DISCUSSION_DAYS . ' days' ) );
	}

	public static function voting_start( string $app_end ): string {
		return gmdate( 'Y-m-d', strtotime( $app_end . ' +' . ( self::DISCUSSION_DAYS + 1 ) . ' days' ) );
	}

	public static function voting_end( string $app_end ): string {
		return gmdate( 'Y-m-d', strtotime( $app_end . ' +' . ( self::DISCUSSION_DAYS + self::VOTING_DAYS ) . ' days' ) );
	}

	private static function votes_opened( object $set ): bool {
		foreach ( $set->positions as $pos ) {
			if ( empty( $pos['vote_id'] ) ) {
				continue;
			}
			$vote = WPVP_Database::get_vote( absint( $pos['vote_id'] ) );
			if ( $vote && in_array( $vote->voting_stage, [ 'draft', 'scheduled' ], true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function votes_closed( object $set ): bool {
		foreach ( $set->positions as $pos ) {
			if ( empty( $pos['vote_id'] ) ) {
				continue;
			}
			$vote = WPVP_Database::get_vote( absint( $pos['vote_id'] ) );
			if ( $vote && in_array( $vote->voting_stage, [ 'draft', 'open', 'scheduled' ], true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function open_all_votes( object $set ): void {
		$now = current_time( 'mysql' );
		$close_date = self::voting_end( $set->application_end ) . ' 23:59:59';

		foreach ( $set->positions as $pos ) {
			if ( empty( $pos['vote_id'] ) ) {
				continue;
			}
			WPVP_Database::update_vote( absint( $pos['vote_id'] ), [
				'voting_stage' => 'open',
				'opening_date' => $now,
				'closing_date' => $close_date,
			] );
		}
	}

	private static function close_all_votes( object $set ): void {
		$now = current_time( 'mysql' );

		foreach ( $set->positions as $pos ) {
			if ( empty( $pos['vote_id'] ) ) {
				continue;
			}
			WPVP_Database::update_vote( absint( $pos['vote_id'] ), [
				'voting_stage' => 'closed',
				'closing_date' => $now,
			] );
		}
	}
}
