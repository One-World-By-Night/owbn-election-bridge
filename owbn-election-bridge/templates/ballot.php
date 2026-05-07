<?php
/**
 * Template: full ballot view.
 *
 * Variables in scope:
 *   $data — array { sets: [...], cards: [...] } from OEB_Ballot::get_renderable_data()
 */
defined( 'ABSPATH' ) || exit;

$cards = $data['cards'] ?? array();
$sets  = $data['sets']  ?? array();

if ( empty( $cards ) ) {
	echo '<div class="oeb-ballot oeb-ballot--empty">';
	echo '<p>' . esc_html__( 'No active elections at this time.', 'owbn-election-bridge' ) . '</p>';
	echo '</div>';
	return;
}

$logged_in = is_user_logged_in();

// Open + eligible cards drive the "remaining" counter.
$total_eligible = 0;
foreach ( $cards as $c ) {
	if ( 'open' === $c['state'] && $c['user_eligible'] && ! $c['user_has_voted'] ) {
		$total_eligible++;
	}
}
?>
<div class="oeb-ballot" data-total-eligible="<?php echo (int) $total_eligible; ?>">

	<header class="oeb-ballot__header">
		<?php foreach ( $sets as $s ) : ?>
			<div class="oeb-ballot__set">
				<h2 class="oeb-ballot__set-title"><?php echo esc_html( $s['label'] ); ?></h2>
				<?php if ( ! empty( $s['application_start'] ) || ! empty( $s['application_end'] ) ) : ?>
					<p class="oeb-ballot__set-meta">
						<?php
						printf(
							/* translators: 1: applications start date, 2: applications end date */
							esc_html__( 'Applications: %1$s – %2$s', 'owbn-election-bridge' ),
							esc_html( $s['application_start'] ?: '—' ),
							esc_html( $s['application_end'] ?: '—' )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( ! $logged_in ) : ?>
			<p class="oeb-ballot__notice oeb-ballot__notice--login">
				<?php
				printf(
					/* translators: %s: login URL */
					wp_kses_post( __( 'You are not logged in. <a href="%s">Log in to vote</a>.', 'owbn-election-bridge' ) ),
					esc_url( wp_login_url( get_permalink() ) )
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<div class="oeb-ballot__cards">
		<?php
		foreach ( $cards as $card ) {
			$card_template = locate_template( 'owbn-election-bridge/ballot-card.php' );
			if ( ! $card_template ) {
				$card_template = OEB_PATH . 'templates/ballot-card.php';
			}
			include $card_template;
		}
		?>
	</div>

	<?php if ( $total_eligible > 0 ) : ?>
		<div class="oeb-ballot__sticky" role="region" aria-label="<?php esc_attr_e( 'Submit all votes', 'owbn-election-bridge' ); ?>">
			<button type="button" class="oeb-ballot__submit-all button button-primary"><?php esc_html_e( 'Submit All Votes', 'owbn-election-bridge' ); ?></button>
			<span class="oeb-ballot__counter">
				<?php
				printf(
					/* translators: 1: remaining count, 2: total eligible */
					esc_html__( '%1$d of %2$d positions remaining', 'owbn-election-bridge' ),
					(int) $total_eligible,
					(int) $total_eligible
				);
				?>
			</span>
			<span class="oeb-ballot__status" aria-live="polite"></span>
		</div>
	<?php endif; ?>

	<div class="oeb-ballot-modal" hidden>
		<div class="oeb-ballot-modal__overlay" tabindex="-1"></div>
		<div class="oeb-ballot-modal__panel" role="dialog" aria-modal="true">
			<div class="oeb-ballot-modal__header">
				<a class="oeb-ballot-modal__newtab" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Open in new tab', 'owbn-election-bridge' ); ?> &#x29C9;</a>
				<button type="button" class="oeb-ballot-modal__close" aria-label="<?php esc_attr_e( 'Close', 'owbn-election-bridge' ); ?>">&times;</button>
			</div>
			<div class="oeb-ballot-modal__body"></div>
		</div>
	</div>

</div>
