<?php
/**
 * Template: a single ballot card. Included from templates/ballot.php in a
 * loop, and directly from class-shortcodes.php for the [oeb_ballot_card] use.
 *
 * In-scope variable: $card — array from OEB_Ballot::build_card()
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $card ) || ! is_array( $card ) ) return;

$state           = $card['state'];
$voting_type     = $card['voting_type'];
$is_ranked       = in_array( $voting_type, array( 'rcv', 'stv', 'sequential_rcv', 'condorcet' ), true );
$is_singleton    = ( $voting_type === 'singleton' );
$can_vote        = ( 'open' === $state ) && ! empty( $card['user_eligible'] );
$has_voted       = ! empty( $card['user_has_voted'] );
$show_results    = ( 'closed' === $state );

$type_badge = $is_ranked ? __( 'RCV', 'owbn-election-bridge' ) : ( $is_singleton ? __( 'FPTP', 'owbn-election-bridge' ) : strtoupper( $voting_type ) );

// Split options into real candidates vs Abstain/Reject All for layout.
$real_options    = array();
$special_options = array();
foreach ( ( $card['options'] ?? array() ) as $opt ) {
	if ( ! empty( $opt['is_special'] ) ) {
		$special_options[] = $opt;
	} else {
		$real_options[] = $opt;
	}
}

// Pre-compute current selection for change-vote view.
$current_choice_label = '';
if ( $has_voted && $card['user_choice'] !== null ) {
	if ( is_array( $card['user_choice'] ) ) {
		$current_choice_label = implode( ' › ', array_map( 'strval', $card['user_choice'] ) );
	} else {
		$current_choice_label = (string) $card['user_choice'];
	}
}
?>
<article class="oeb-ballot-card oeb-ballot-card--<?php echo esc_attr( $state ); ?> oeb-ballot-card--<?php echo esc_attr( $is_ranked ? 'ranked' : 'singleton' ); ?> <?php echo $has_voted ? 'is-voted' : ''; ?>"
	data-vote-id="<?php echo (int) $card['vote_id']; ?>"
	data-voting-type="<?php echo esc_attr( $voting_type ); ?>"
	data-state="<?php echo esc_attr( $state ); ?>"
	data-eligible="<?php echo $can_vote ? '1' : '0'; ?>"
	data-has-voted="<?php echo $has_voted ? '1' : '0'; ?>">

	<header class="oeb-ballot-card__head">
		<span class="oeb-ballot-card__type-badge"><?php echo esc_html( $type_badge ); ?></span>
		<h3 class="oeb-ballot-card__title"><?php echo esc_html( $card['position_title'] ); ?></h3>
		<?php if ( $has_voted ) : ?>
			<span class="oeb-ballot-card__voted-badge">✓ <?php esc_html_e( 'Voted', 'owbn-election-bridge' ); ?></span>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $card['description'] ) ) : ?>
		<p class="oeb-ballot-card__desc"><?php echo esc_html( $card['description'] ); ?></p>
	<?php endif; ?>

	<p class="oeb-ballot-card__dates">
		<?php
		switch ( $state ) {
			case 'apps_open':
				esc_html_e( 'Applications open. Voting has not yet started.', 'owbn-election-bridge' );
				break;
			case 'apps_closed_voting_pending':
				if ( ! empty( $card['opens_at'] ) ) {
					printf(
						/* translators: %s: voting start date */
						esc_html__( 'Voting opens %s', 'owbn-election-bridge' ),
						esc_html( $card['opens_at'] )
					);
				} else {
					esc_html_e( 'Voting opens soon.', 'owbn-election-bridge' );
				}
				break;
			case 'open':
				printf(
					/* translators: 1: opens at, 2: closes at */
					esc_html__( 'Voting: %1$s – %2$s', 'owbn-election-bridge' ),
					esc_html( $card['opens_at'] ?: '—' ),
					esc_html( $card['closes_at'] ?: '—' )
				);
				break;
			case 'closed':
				esc_html_e( 'Voting closed.', 'owbn-election-bridge' );
				break;
		}
		?>
	</p>

	<?php if ( 'apps_open' === $state ) : ?>
		<?php if ( ! empty( $card['apply_page_url'] ) ) : ?>
			<p class="oeb-ballot-card__apply">
				<a href="<?php echo esc_url( $card['apply_page_url'] ); ?>" class="button"><?php esc_html_e( 'Apply for this position', 'owbn-election-bridge' ); ?></a>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $real_options ) ) : ?>
		<div class="oeb-ballot-card__options">
			<?php if ( $can_vote && $is_singleton ) : ?>
				<fieldset class="oeb-ballot-card__choices">
					<legend class="screen-reader-text"><?php esc_html_e( 'Choose one', 'owbn-election-bridge' ); ?></legend>
					<?php foreach ( $real_options as $opt ) : ?>
						<label class="oeb-ballot-card__choice">
							<input type="radio" name="oeb-vote-<?php echo (int) $card['vote_id']; ?>" value="<?php echo esc_attr( $opt['text'] ); ?>" <?php checked( $current_choice_label, $opt['text'] ); ?>>
							<span class="oeb-ballot-card__candidate-name" data-post-id="<?php echo (int) $opt['post_id']; ?>"><?php echo esc_html( $opt['text'] ); ?></span>
						</label>
					<?php endforeach; ?>
					<?php foreach ( $special_options as $opt ) : ?>
						<label class="oeb-ballot-card__choice oeb-ballot-card__choice--special">
							<input type="radio" name="oeb-vote-<?php echo (int) $card['vote_id']; ?>" value="<?php echo esc_attr( $opt['text'] ); ?>" <?php checked( $current_choice_label, $opt['text'] ); ?>>
							<span><?php echo esc_html( $opt['text'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php elseif ( $can_vote && $is_ranked ) :
				$rank_count = count( $real_options ) + count( $special_options );
				$current_ranks = is_array( $card['user_choice'] ) ? $card['user_choice'] : array();
			?>
				<div class="oeb-ballot-card__ranks">
					<p class="oeb-ballot-card__ranks-label"><?php esc_html_e( 'Rank your choices:', 'owbn-election-bridge' ); ?></p>
					<?php for ( $i = 0; $i < $rank_count; $i++ ) :
						$selected_at_rank = $current_ranks[ $i ] ?? '';
					?>
						<select class="oeb-ballot-card__rank" data-rank="<?php echo (int) $i; ?>">
							<option value="">
								<?php
								printf(
									/* translators: %d: 1-based rank number */
									esc_html__( '%d. — Select —', 'owbn-election-bridge' ),
									$i + 1
								);
								?>
							</option>
							<?php foreach ( $real_options as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt['text'] ); ?>" <?php selected( $selected_at_rank, $opt['text'] ); ?>><?php echo esc_html( $opt['text'] ); ?></option>
							<?php endforeach; ?>
							<?php foreach ( $special_options as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt['text'] ); ?>" <?php selected( $selected_at_rank, $opt['text'] ); ?>><?php echo esc_html( $opt['text'] ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endfor; ?>
				</div>
			<?php else : // read-only candidate list ?>
				<ul class="oeb-ballot-card__candidates">
					<?php foreach ( $real_options as $opt ) : ?>
						<li><span class="oeb-ballot-card__candidate-name" data-post-id="<?php echo (int) $opt['post_id']; ?>"><?php echo esc_html( $opt['text'] ); ?></span></li>
					<?php endforeach; ?>
					<?php foreach ( $special_options as $opt ) : ?>
						<li class="oeb-ballot-card__candidate--special"><?php echo esc_html( $opt['text'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $has_voted && ! $can_vote ) : ?>
		<p class="oeb-ballot-card__current-choice">
			<strong><?php esc_html_e( 'Your vote:', 'owbn-election-bridge' ); ?></strong>
			<?php echo esc_html( $current_choice_label ); ?>
		</p>
	<?php elseif ( $has_voted && $can_vote ) : ?>
		<p class="oeb-ballot-card__current-choice">
			<strong><?php esc_html_e( 'Your current vote:', 'owbn-election-bridge' ); ?></strong>
			<?php echo esc_html( $current_choice_label ); ?>
			<small><?php esc_html_e( '(change above and resubmit to update)', 'owbn-election-bridge' ); ?></small>
		</p>
	<?php endif; ?>

	<footer class="oeb-ballot-card__foot">
		<a class="oeb-ballot-card__view-link" href="<?php echo esc_url( $show_results ? $card['results_url'] : $card['view_url'] ); ?>">
			<?php echo $show_results ? esc_html__( 'View results', 'owbn-election-bridge' ) : esc_html__( 'View vote', 'owbn-election-bridge' ); ?> →
		</a>
		<span class="oeb-ballot-card__inline-status" aria-live="polite"></span>
	</footer>
</article>
