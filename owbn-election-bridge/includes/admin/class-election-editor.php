<?php
defined( 'ABSPATH' ) || exit;

class OEB_Election_Editor {

	public static function render( int $set_id = 0 ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'owbn-election-bridge' ) );
		}

		if ( isset( $_POST['oeb_save_election'] ) ) {
			check_admin_referer( 'oeb_save_election' );
			$saved_id = self::process_save( $set_id );
			if ( $saved_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . OEB_Admin_Page::SLUG . '&action=edit&id=' . $saved_id . '&saved=1' ) );
				exit;
			}
		}

		$set = $set_id ? OEB_Election_Set::get( $set_id ) : null;

		$coordinators = function_exists( 'owc_get_coordinators' ) ? owc_get_coordinators() : [];

		$is_edit = (bool) $set;
		$title   = $is_edit
			? sprintf( __( 'Edit: %s', 'owbn-election-bridge' ), esc_html( $set->name ) )
			: __( 'New Election Set', 'owbn-election-bridge' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php if ( $set ) : ?>
				<?php $page_id = absint( $set->page_id ?? 0 ); ?>
				<?php if ( $page_id && 'publish' === get_post_status( $page_id ) ) : ?>
					<div class="notice notice-info" style="padding: 12px;">
						<strong><?php esc_html_e( 'Application Page:', 'owbn-election-bridge' ); ?></strong>
						<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank">
							<?php echo esc_html( get_permalink( $page_id ) ); ?>
						</a>
						— <?php esc_html_e( 'Share this link with candidates.', 'owbn-election-bridge' ); ?>
					</div>
				<?php elseif ( 'draft' === $set->status ) : ?>
					<div class="notice notice-warning" style="padding: 12px;">
						<?php esc_html_e( 'The application page will be created when you activate this election set.', 'owbn-election-bridge' ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'oeb_save_election' ); ?>
				<input type="hidden" name="set_id" value="<?php echo esc_attr( $set_id ); ?>">

				<table class="form-table">
					<tr>
						<th><label for="oeb-name"><?php esc_html_e( 'Election Name', 'owbn-election-bridge' ); ?></label></th>
						<td>
							<input type="text" id="oeb-name" name="name" class="regular-text"
								value="<?php echo esc_attr( $set ? $set->name : '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. 2027 Annual Elections', 'owbn-election-bridge' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th><label for="oeb-start"><?php esc_html_e( 'Application Start', 'owbn-election-bridge' ); ?></label></th>
						<td>
							<input type="date" id="oeb-start" name="application_start"
								value="<?php echo esc_attr( $set ? $set->application_start : '' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th><label for="oeb-end"><?php esc_html_e( 'Application End', 'owbn-election-bridge' ); ?></label></th>
						<td>
							<input type="date" id="oeb-end" name="application_end"
								value="<?php echo esc_attr( $set ? $set->application_end : '' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional. Leave blank for no end date.', 'owbn-election-bridge' ); ?></p>
						</td>
					</tr>
				</table>

				<?php if ( $set && $set->application_end ) : ?>
					<h2><?php esc_html_e( 'Timeline', 'owbn-election-bridge' ); ?></h2>
					<?php
					$fmt = get_option( 'date_format' );
					$app_end = $set->application_end;
					?>
					<table class="form-table" style="max-width: 500px;">
						<tr>
							<th><?php esc_html_e( 'Applications', 'owbn-election-bridge' ); ?></th>
							<td>
								<?php echo esc_html( date_i18n( $fmt, strtotime( $set->application_start ) ) ); ?>
								&ndash;
								<?php echo esc_html( date_i18n( $fmt, strtotime( $app_end ) ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Discussion', 'owbn-election-bridge' ); ?></th>
							<td>
								<?php echo esc_html( date_i18n( $fmt, strtotime( OEB_Cron::discussion_start( $app_end ) ) ) ); ?>
								&ndash;
								<?php echo esc_html( date_i18n( $fmt, strtotime( OEB_Cron::discussion_end( $app_end ) ) ) ); ?>
								<span style="color: #888;">(<?php echo esc_html( OEB_Cron::DISCUSSION_DAYS ); ?> <?php esc_html_e( 'days', 'owbn-election-bridge' ); ?>)</span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Voting', 'owbn-election-bridge' ); ?></th>
							<td>
								<?php echo esc_html( date_i18n( $fmt, strtotime( OEB_Cron::voting_start( $app_end ) ) ) ); ?>
								&ndash;
								<?php echo esc_html( date_i18n( $fmt, strtotime( OEB_Cron::voting_end( $app_end ) ) ) ); ?>
								<span style="color: #888;">(<?php echo esc_html( OEB_Cron::VOTING_DAYS ); ?> <?php esc_html_e( 'days', 'owbn-election-bridge' ); ?>)</span>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Positions', 'owbn-election-bridge' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pick from the coordinator list or add custom positions.', 'owbn-election-bridge' ); ?></p>

				<table class="wp-list-table widefat" id="oeb-positions-table">
					<thead>
						<tr>
							<th style="width: 30%"><?php esc_html_e( 'Position', 'owbn-election-bridge' ); ?></th>
							<th style="width: 25%"><?php esc_html_e( 'Voting Type', 'owbn-election-bridge' ); ?></th>
							<th style="width: 10%"><?php esc_html_e( 'Seats', 'owbn-election-bridge' ); ?></th>
							<th style="width: 15%"><?php esc_html_e( 'Status', 'owbn-election-bridge' ); ?></th>
							<th style="width: 5%"></th>
						</tr>
					</thead>
					<tbody id="oeb-positions-body">
						<?php
						$existing = $set ? $set->positions : [];
						foreach ( $existing as $i => $pos ) :
							self::render_position_row( $i, $pos );
						endforeach;
						?>
					</tbody>
				</table>

				<p style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<select id="oeb-add-coordinator">
						<option value=""><?php esc_html_e( '— Pick from coordinators —', 'owbn-election-bridge' ); ?></option>
						<?php foreach ( $coordinators as $coord ) : ?>
							<option value="<?php echo esc_attr( $coord['slug'] ?? '' ); ?>"
								data-title="<?php echo esc_attr( $coord['title'] ?? '' ); ?>">
								<?php echo esc_html( $coord['title'] ?? $coord['slug'] ?? '' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="oeb-add-position-btn">
						<?php esc_html_e( 'Add', 'owbn-election-bridge' ); ?>
					</button>
					<span style="color: #888;"><?php esc_html_e( 'or', 'owbn-election-bridge' ); ?></span>
					<input type="text" id="oeb-custom-slug" placeholder="<?php esc_attr_e( 'Custom slug', 'owbn-election-bridge' ); ?>" style="width: 140px;">
					<input type="text" id="oeb-custom-title" placeholder="<?php esc_attr_e( 'Custom title', 'owbn-election-bridge' ); ?>" style="width: 200px;">
					<button type="button" class="button" id="oeb-add-custom-btn">
						<?php esc_html_e( 'Add Custom', 'owbn-election-bridge' ); ?>
					</button>
				</p>

				<?php if ( $is_edit ) : ?>
					<h2><?php esc_html_e( 'Sync Check', 'owbn-election-bridge' ); ?></h2>
					<?php self::render_sync_check( $set ); ?>
				<?php endif; ?>

				<?php submit_button( $is_edit ? __( 'Update Election Set', 'owbn-election-bridge' ) : __( 'Create Election Set', 'owbn-election-bridge' ), 'primary', 'oeb_save_election' ); ?>
			</form>

			<?php OEB_Admin_Page::render_help_static(); ?>
		</div>

		<script>
		(function() {
			var idx = <?php echo count( $existing ?? [] ); ?>;
			var tbody = document.getElementById('oeb-positions-body');
			var addBtn = document.getElementById('oeb-add-position-btn');
			var addSelect = document.getElementById('oeb-add-coordinator');
			var addCustomBtn = document.getElementById('oeb-add-custom-btn');
			var customSlug = document.getElementById('oeb-custom-slug');
			var customTitle = document.getElementById('oeb-custom-title');

			function esc(str) {
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(str));
				return div.innerHTML;
			}

			function typeOptions() {
				return '<option value="auto">Auto (FPTP/RCV)</option>' +
					'<option value="singleton">FPTP</option>' +
					'<option value="rcv">RCV</option>' +
					'<option value="sequential_rcv">Sequential RCV (Multi-seat)</option>';
			}

			function addRow(slug, title) {
				var s = esc(slug), t = esc(title);
				if (tbody.querySelector('[data-slug="' + s + '"]')) return;

				var row = document.createElement('tr');
				row.setAttribute('data-slug', slug);
				row.innerHTML =
					'<td>' +
						'<input type="hidden" name="positions[' + idx + '][coordinator_slug]" value="' + s + '">' +
						'<input type="hidden" name="positions[' + idx + '][coordinator_title]" value="' + t + '">' +
						t +
					'</td>' +
					'<td><select name="positions[' + idx + '][voting_type]" class="oeb-voting-type">' + typeOptions() + '</select></td>' +
					'<td><input type="number" name="positions[' + idx + '][number_of_winners]" value="1" min="1" max="20" style="width:60px;" class="oeb-seats" disabled></td>' +
					'<td>New</td>' +
					'<td><button type="button" class="button oeb-remove-row">&times;</button></td>';
				tbody.appendChild(row);
				idx++;
			}

			addBtn.addEventListener('click', function() {
				var opt = addSelect.options[addSelect.selectedIndex];
				if (!opt.value) return;
				addRow(opt.value, opt.getAttribute('data-title') || opt.value);
				addSelect.selectedIndex = 0;
			});

			addCustomBtn.addEventListener('click', function() {
				var slug = customSlug.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-');
				var title = customTitle.value.trim();
				if (!slug || !title) return;
				addRow(slug, title);
				customSlug.value = '';
				customTitle.value = '';
			});

			tbody.addEventListener('change', function(e) {
				if (e.target.classList.contains('oeb-voting-type')) {
					var seats = e.target.closest('tr').querySelector('.oeb-seats');
					if (seats) {
						seats.disabled = e.target.value !== 'sequential_rcv';
						if (seats.disabled) seats.value = '1';
					}
				}
			});

			tbody.addEventListener('click', function(e) {
				if (e.target.classList.contains('oeb-remove-row')) {
					e.target.closest('tr').remove();
				}
			});
		})();
		</script>
		<?php
	}

	private static function render_position_row( int $i, array $pos ): void {
		$slug     = $pos['coordinator_slug'] ?? '';
		$title    = $pos['coordinator_title'] ?? $slug;
		$type     = $pos['voting_type'] ?? 'auto';
		$seats    = intval( $pos['number_of_winners'] ?? 1 );
		$has_vote = ! empty( $pos['vote_id'] );
		$has_cat  = ! empty( $pos['category_id'] );

		$status = 'New';
		if ( $has_vote && $has_cat ) {
			$status = 'Ready';
		} elseif ( $has_vote || $has_cat ) {
			$status = 'Partial';
		}

		?>
		<tr data-slug="<?php echo esc_attr( $slug ); ?>">
			<td>
				<input type="hidden" name="positions[<?php echo esc_attr( $i ); ?>][coordinator_slug]" value="<?php echo esc_attr( $slug ); ?>">
				<input type="hidden" name="positions[<?php echo esc_attr( $i ); ?>][coordinator_title]" value="<?php echo esc_attr( $title ); ?>">
				<?php if ( $has_vote ) : ?>
					<input type="hidden" name="positions[<?php echo esc_attr( $i ); ?>][vote_id]" value="<?php echo esc_attr( $pos['vote_id'] ); ?>">
				<?php endif; ?>
				<?php if ( $has_cat ) : ?>
					<input type="hidden" name="positions[<?php echo esc_attr( $i ); ?>][category_id]" value="<?php echo esc_attr( $pos['category_id'] ); ?>">
				<?php endif; ?>
				<?php echo esc_html( $title ); ?>
			</td>
			<td>
				<select name="positions[<?php echo esc_attr( $i ); ?>][voting_type]" class="oeb-voting-type">
					<option value="auto" <?php selected( $type, 'auto' ); ?>>Auto (FPTP/RCV)</option>
					<option value="singleton" <?php selected( $type, 'singleton' ); ?>>FPTP</option>
					<option value="rcv" <?php selected( $type, 'rcv' ); ?>>RCV</option>
					<option value="sequential_rcv" <?php selected( $type, 'sequential_rcv' ); ?>>Sequential RCV (Multi-seat)</option>
				</select>
			</td>
			<td>
				<input type="number" name="positions[<?php echo esc_attr( $i ); ?>][number_of_winners]"
					value="<?php echo esc_attr( $seats ); ?>" min="1" max="20" style="width: 60px;"
					class="oeb-seats" <?php echo 'sequential_rcv' !== $type ? 'disabled' : ''; ?>>
			</td>
			<td><?php echo esc_html( $status ); ?></td>
			<td>
				<button type="button" class="button oeb-remove-row">&times;</button>
			</td>
		</tr>
		<?php
	}

	private static function process_save( int $existing_id ): int {
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$start = sanitize_text_field( $_POST['application_start'] ?? '' );
		$end   = sanitize_text_field( $_POST['application_end'] ?? '' );
		$year  = OEB_Election_Set::derive_year( $start );

		if ( $end && $start && $end < $start ) {
			list( $start, $end ) = [ $end, $start ];
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_positions = isset( $_POST['positions'] ) ? (array) wp_unslash( $_POST['positions'] ) : [];

		$existing_set       = $existing_id ? OEB_Election_Set::get( $existing_id ) : null;
		$existing_positions = $existing_set ? $existing_set->positions : [];

		$existing_lookup = [];
		foreach ( $existing_positions as $ep ) {
			$existing_lookup[ $ep['coordinator_slug'] ?? '' ] = $ep;
		}

		// Save the set first so we have an ID for category slugs.
		$set_data = [
			'name'              => $name,
			'application_start' => $start,
			'application_end'   => $end,
			'positions'         => [],
			'status'            => $existing_set ? $existing_set->status : 'draft',
		];
		if ( $existing_id ) {
			$set_data['id'] = $existing_id;
		}
		$set_id = OEB_Election_Set::save( $set_data );
		if ( ! $set_id ) {
			return $existing_id;
		}
		$set_id = absint( $set_id );

		$positions = [];
		foreach ( $raw_positions as $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['coordinator_slug'] ) ) {
				continue;
			}

			$slug   = sanitize_key( $raw['coordinator_slug'] );
			$ptitle = sanitize_text_field( $raw['coordinator_title'] ?? $slug );
			$type   = sanitize_key( $raw['voting_type'] ?? 'auto' );
			$seats  = max( 1, intval( $raw['number_of_winners'] ?? 1 ) );

			$vote_id     = absint( $raw['vote_id'] ?? ( $existing_lookup[ $slug ]['vote_id'] ?? 0 ) );
			$category_id = absint( $raw['category_id'] ?? ( $existing_lookup[ $slug ]['category_id'] ?? 0 ) );

			if ( ! $category_id ) {
				$category_id = OEB_Category_Manager::ensure_position_category( $year, $set_id, $slug, $ptitle );
			}

			if ( ! $vote_id && class_exists( 'WPVP_Database' ) ) {
				$initial_type = 'auto' === $type ? 'singleton' : $type;
				$vote_id = WPVP_Database::save_vote( [
					'proposal_name'        => sprintf( '%s — %s', $ptitle, $name ),
					'proposal_description' => sprintf( '[oeb_candidates position="%s" year="%d" set="%d"]', $slug, $year, $set_id ),
					'voting_type'          => $initial_type,
					'number_of_winners'    => $seats,
					'voting_options'       => [
						[ 'text' => 'Abstain', 'description' => '' ],
						[ 'text' => 'Reject All Candidates', 'description' => '' ],
					],
					'voting_stage'         => 'draft',
				] );
			}

			$positions[] = [
				'coordinator_slug'  => $slug,
				'coordinator_title' => $ptitle,
				'category_id'       => $category_id,
				'vote_id'           => $vote_id,
				'voting_type'       => $type,
				'number_of_winners' => $seats,
			];
		}

		OEB_Election_Set::save( [
			'id'                => $set_id,
			'name'              => $name,
			'application_start' => $start,
			'application_end'   => $end,
			'positions'         => $positions,
			'status'            => $existing_set ? $existing_set->status : 'draft',
		] );

		return $set_id;
	}

	private static function render_sync_check( object $set ): void {
		if ( empty( $set->positions ) ) {
			echo '<p>' . esc_html__( 'No positions to check.', 'owbn-election-bridge' ) . '</p>';
			return;
		}

		echo '<table class="widefat" style="max-width: 800px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Position', 'owbn-election-bridge' ) . '</th>';
		echo '<th>' . esc_html__( 'Published Posts', 'owbn-election-bridge' ) . '</th>';
		echo '<th>' . esc_html__( 'Vote Options', 'owbn-election-bridge' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'owbn-election-bridge' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $set->positions as $pos ) {
			$ptitle  = $pos['coordinator_title'] ?? $pos['coordinator_slug'] ?? '';
			$vote_id = absint( $pos['vote_id'] ?? 0 );

			$post_count = 0;
			if ( $vote_id ) {
				$posts = get_posts( [
					'post_status'    => 'publish',
					'post_type'      => 'post',
					'meta_key'       => '_oeb_vote_id',
					'meta_value'     => $vote_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				] );
				$post_count = count( $posts );
			}

			$option_count = 0;
			if ( $vote_id ) {
				$options = WPVP_Database::get_voting_options( $vote_id );
				$option_count = count( $options );
			}

			$synced = $post_count === $option_count;
			$status_text  = $synced ? __( 'In Sync', 'owbn-election-bridge' ) : __( 'Mismatch', 'owbn-election-bridge' );
			$status_class = $synced ? 'color: green;' : 'color: red; font-weight: bold;';

			echo '<tr>';
			echo '<td>' . esc_html( $ptitle ) . '</td>';
			echo '<td>' . esc_html( $post_count ) . '</td>';
			echo '<td>' . esc_html( $option_count ) . '</td>';
			echo '<td style="' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
