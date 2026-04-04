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
							<th><?php esc_html_e( 'Election', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Status', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Positions', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Applications', 'owbn-election-bridge' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'owbn-election-bridge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sets as $set ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $set->name ); ?></strong></td>
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

			<?php self::render_help_static(); ?>
		</div>
		<?php
	}

	public static function render_help_static(): void {
		?>
		<div class="oeb-help" style="max-width: 720px; margin-top: 2em; padding: 16px 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'How to Run an Election', 'owbn-election-bridge' ); ?></h3>

			<h4><?php esc_html_e( 'Setting Up', 'owbn-election-bridge' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Click Add New. Give it a name like "2027 Annual Elections" or "2027 Special Election — Brujah".', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Set your application start and end dates. Candidates can only apply during this window.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Pick coordinator positions from the dropdown, or type a custom slug and title for positions not in the list (like "Mediation - North America").', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'For each position, pick the voting type. Most positions use Auto (starts as FPTP, switches to Ranked Choice if 3+ candidates run).', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'For multi-seat positions (like Mediation), pick "Sequential RCV (Multi-seat)" and set the number of seats. Example: Mediation North America = 5 seats, South America = 2 seats.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Save. The plugin creates a draft vote and a post category for each position.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Click Activate when you\'re ready to open the application window.', 'owbn-election-bridge' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Candidate Applications', 'owbn-election-bridge' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'When you activate an election set, an application page is created automatically using the election name.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Share that page URL with candidates. You\'ll see the link on the election set list and on the edit page.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Candidates log in, pick a position, fill out their statement, and submit.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Each submission creates a pending post. You\'ll find them under Posts with "Pending" status.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'When the election is closed, the application page is automatically unpublished.', 'owbn-election-bridge' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Approving Candidates', 'owbn-election-bridge' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Review each pending post. When you publish it, that candidate is automatically added to their position\'s vote.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'If you trash or unpublish a candidate post, they\'re removed from the vote.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Every vote starts as FPTP. If a position gets more than 2 candidates, it switches to Ranked Choice automatically.', 'owbn-election-bridge' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Timeline (Automatic)', 'owbn-election-bridge' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Applications run from your start date to your end date. The form stops accepting submissions at midnight on the end date.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'A 7-day discussion period starts the day after applications close. Nothing automated happens — this is time for the org to review candidates.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Voting opens automatically 8 days after applications close (midnight cron). All votes open at once.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Voting closes automatically 14 days after applications close (midnight cron). Results are calculated by WP Voting.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'The exact dates are shown on the edit page under "Timeline" once you set an application end date.', 'owbn-election-bridge' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'You can still use "Open All Votes" and "Close All Votes" to override the schedule manually if needed.', 'owbn-election-bridge' ); ?></p>

			<h4><?php esc_html_e( 'Good to Know', 'owbn-election-bridge' ); ?></h4>
			<ul style="list-style: disc; padding-left: 20px;">
				<li><?php esc_html_e( 'Every vote always includes "Abstain" and "Reject All Candidates" as options.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Only one election set can be active at a time. Activating a new one closes the old one.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'A candidate can apply for multiple positions — they just submit the form once per position.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Candidates mark which language their application is written in (English or Portuguese). This helps reviewers know what to expect.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Use the Sync Check on the edit page to make sure published candidates match what\'s in the vote.', 'owbn-election-bridge' ); ?></li>
				<li><?php esc_html_e( 'Make sure all candidates are approved (published) before the voting period starts. Candidates published after votes open won\'t be added.', 'owbn-election-bridge' ); ?></li>
			</ul>
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

		// Application page link.
		$page_id = absint( $set->page_id ?? 0 );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			echo ' | <a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank">' . esc_html__( 'Application Page', 'owbn-election-bridge' ) . '</a>';
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
				self::close_election_page( $current );
				OEB_Election_Set::save( [
					'id'                => $current->id,
					'name'              => $current->name,
					'application_start' => $current->application_start,
					'application_end'   => $current->application_end,
					'positions'         => $current->positions,
					'status'            => 'closed',
				] );
			}
		}

		OEB_Election_Set::save( [
			'id'                => $id,
			'name'              => $set->name,
			'application_start' => $set->application_start,
			'application_end'   => $set->application_end,
			'positions'         => $set->positions,
			'status'            => $status,
		] );

		// Reload to get saved state.
		$set = OEB_Election_Set::get( $id );

		if ( 'active' === $status ) {
			self::ensure_election_page( $set );
		} elseif ( in_array( $status, [ 'closed', 'archived' ], true ) ) {
			self::close_election_page( $set );
		}

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

	private static function ensure_election_page( object $set ): void {
		$page_id = absint( $set->page_id ?? 0 );

		// Re-publish if page exists and isn't permanently deleted.
		if ( $page_id ) {
			$page = get_post( $page_id );
			if ( $page && 'trash' !== $page->post_status ) {
				wp_update_post( [
					'ID'          => $page_id,
					'post_status' => 'publish',
				] );
				return;
			}
			// Trashed or deleted — create a new one.
		}

		$slug = sanitize_title( $set->name ) . '-' . $set->id;

		$page_id = wp_insert_post( [
			'post_title'   => sprintf( '%s — Applications', $set->name ),
			'post_name'    => $slug,
			'post_content' => '[oeb_apply]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		] );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			global $wpdb;
			$wpdb->update(
				OEB_Schema::table_name(),
				[ 'page_id' => $page_id ],
				[ 'id' => $set->id ],
				[ '%d' ],
				[ '%d' ]
			);
		}
	}

	private static function close_election_page( object $set ): void {
		$page_id = absint( $set->page_id ?? 0 );
		if ( ! $page_id ) {
			return;
		}

		wp_update_post( [
			'ID'          => $page_id,
			'post_status' => 'draft',
		] );
	}
}
