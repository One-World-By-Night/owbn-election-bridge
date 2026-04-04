<?php
defined( 'ABSPATH' ) || exit;

class OEB_Admin_Page {

	const SLUG = 'wpvp-elections';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_oeb_delete_set', [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_post_oeb_set_status', [ __CLASS__, 'handle_set_status' ] );
		add_action( 'admin_post_oeb_bulk_vote_action', [ __CLASS__, 'handle_bulk_vote_action' ] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'wpvp-votes',
			__( 'Elections', 'owbn-election-bridge' ),
			__( 'Elections', 'owbn-election-bridge' ),
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'owbn-election-bridge' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( $_GET['action'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$set_id = absint( $_GET['id'] ?? 0 );

		if ( 'edit' === $action || 'new' === $action ) {
			OEB_Election_Editor::render( $set_id );
			return;
		}

		self::render_list();
	}

	private static function render_list(): void {
		$sets = OEB_Election_Set::get_all();

		$status_labels = [
			'draft'    => __( 'Draft', 'owbn-election-bridge' ),
			'active'   => __( 'Active', 'owbn-election-bridge' ),
			'closed'   => __( 'Closed', 'owbn-election-bridge' ),
			'archived' => __( 'Archived', 'owbn-election-bridge' ),
		];

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Election Sets', 'owbn-election-bridge' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'owbn-election-bridge' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( empty( $sets ) ) : ?>
				<p><?php esc_html_e( 'No election sets found.', 'owbn-election-bridge' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Year', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Status', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Positions', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Applications', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'owbn-election-bridge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sets as $set ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $set->year ); ?></strong></td>
								<td><?php echo esc_html( $status_labels[ $set->status ] ?? $set->status ); ?></td>
								<td><?php echo esc_html( count( $set->positions ) ); ?></td>
								<td>
									<?php
									echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $set->application_start ) ) );
									if ( $set->application_end ) {
										echo ' &ndash; ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $set->application_end ) ) );
									}
									?>
								</td>
								<td>
									<?php self::render_row_actions( $set ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_row_actions( object $set ): void {
		$edit_url = admin_url( 'admin.php?page=' . self::SLUG . '&action=edit&id=' . $set->id );
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'owbn-election-bridge' ) . '</a>';

		if ( 'draft' === $set->status ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oeb_set_status&id=' . $set->id . '&status=active' ),
				'oeb_set_status_' . $set->id
			);
			echo ' | <a href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'owbn-election-bridge' ) . '</a>';
		}

		if ( 'active' === $set->status ) {
			$open_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oeb_bulk_vote_action&id=' . $set->id . '&vote_action=open' ),
				'oeb_bulk_vote_' . $set->id
			);
			$close_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=oeb_bulk_vote_action&id=' . $set->id . '&vote_action=close' ),
				'oeb_bulk_vote_' . $set->id
			);
			echo ' | <a href="' . esc_url( $open_url ) . '">' . esc_html__( 'Open All Votes', 'owbn-election-bridge' ) . '</a>';
			echo ' | <a href="' . esc_url( $close_url ) . '">' . esc_html__( 'Close All Votes', 'owbn-election-bridge' ) . '</a>';
		}

		// Pending candidates link.
		if ( ! empty( $set->positions ) ) {
			$pending_url = admin_url( 'edit.php?post_status=pending&post_type=post' );
			echo ' | <a href="' . esc_url( $pending_url ) . '">' . esc_html__( 'Pending Candidates', 'owbn-election-bridge' ) . '</a>';
		}

		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=oeb_delete_set&id=' . $set->id ),
			'oeb_delete_' . $set->id
		);
		echo ' | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_attr__( 'Delete this election set?', 'owbn-election-bridge' ) . '\');" class="delete">' . esc_html__( 'Delete', 'owbn-election-bridge' ) . '</a>';
	}

	public static function handle_delete(): void {
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'oeb_delete_' . $id );

		if ( ! current_user_can( 'manage_options' ) || ! $id ) {
			wp_die( esc_html__( 'Unauthorized.', 'owbn-election-bridge' ) );
		}

		OEB_Election_Set::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public static function handle_set_status(): void {
		$id     = absint( $_GET['id'] ?? 0 );
		$status = sanitize_key( $_GET['status'] ?? '' );
		check_admin_referer( 'oeb_set_status_' . $id );

		$valid_statuses = [ 'draft', 'active', 'closed', 'archived' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'owbn-election-bridge' ) );
		}

		if ( ! current_user_can( 'manage_options' ) || ! $id ) {
			wp_die( esc_html__( 'Unauthorized.', 'owbn-election-bridge' ) );
		}

		$set = OEB_Election_Set::get( $id );
		if ( ! $set ) {
			wp_die( esc_html__( 'Election set not found.', 'owbn-election-bridge' ) );
		}

		// Enforce one active at a time.
		if ( 'active' === $status ) {
			$current = OEB_Election_Set::get_active();
			if ( $current && (int) $current->id !== $id ) {
				OEB_Election_Set::save( [
					'id'                => $current->id,
					'year'              => $current->year,
					'application_start' => $current->application_start,
					'application_end'   => $current->application_end,
					'positions'         => $current->positions,
					'status'            => 'closed',
				] );
			}
		}

		OEB_Election_Set::save( [
			'id'                => $id,
			'year'              => $set->year,
			'application_start' => $set->application_start,
			'application_end'   => $set->application_end,
			'positions'         => $set->positions,
			'status'            => $status,
		] );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public static function handle_bulk_vote_action(): void {
		$id          = absint( $_GET['id'] ?? 0 );
		$vote_action = sanitize_key( $_GET['vote_action'] ?? '' );
		check_admin_referer( 'oeb_bulk_vote_' . $id );

		if ( ! current_user_can( 'manage_options' ) || ! $id ) {
			wp_die( esc_html__( 'Unauthorized.', 'owbn-election-bridge' ) );
		}

		$set = OEB_Election_Set::get( $id );
		if ( ! $set ) {
			wp_die( esc_html__( 'Election set not found.', 'owbn-election-bridge' ) );
		}

		$now = current_time( 'mysql' );

		foreach ( $set->positions as $pos ) {
			if ( empty( $pos['vote_id'] ) ) {
				continue;
			}

			$vid = absint( $pos['vote_id'] );

			if ( 'open' === $vote_action ) {
				WPVP_Database::update_vote( $vid, [
					'voting_stage' => 'open',
					'opening_date' => $now,
				] );
			} elseif ( 'close' === $vote_action ) {
				WPVP_Database::update_vote( $vid, [
					'voting_stage' => 'closed',
					'closing_date' => $now,
				] );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}
}
