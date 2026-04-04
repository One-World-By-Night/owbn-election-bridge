<?php
defined( 'ABSPATH' ) || exit;

// Available in scope: $election, $current_user
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oeb-application-form">
	<?php wp_nonce_field( OEB_Application_Form::ACTION ); ?>
	<input type="hidden" name="action" value="<?php echo esc_attr( OEB_Application_Form::ACTION ); ?>">

	<fieldset>
		<label for="oeb-position"><?php esc_html_e( 'Position Applied For', 'owbn-election-bridge' ); ?> *</label>
		<select id="oeb-position" name="position" required>
			<option value=""><?php esc_html_e( '— Select a position —', 'owbn-election-bridge' ); ?></option>
			<?php foreach ( $election->positions as $pos ) : ?>
				<option value="<?php echo esc_attr( $pos['coordinator_slug'] ); ?>">
					<?php echo esc_html( $pos['coordinator_title'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</fieldset>

	<fieldset>
		<label for="oeb-name"><?php esc_html_e( 'Applicant Name', 'owbn-election-bridge' ); ?> *</label>
		<input type="text" id="oeb-name" name="applicant_name"
			value="<?php echo esc_attr( $current_user->display_name ); ?>" required>
	</fieldset>

	<fieldset>
		<label for="oeb-email"><?php esc_html_e( 'Applicant Email', 'owbn-election-bridge' ); ?> *</label>
		<input type="email" id="oeb-email" name="applicant_email"
			value="<?php echo esc_attr( $current_user->user_email ); ?>" required>
	</fieldset>

	<fieldset>
		<label for="oeb-chronicle"><?php esc_html_e( 'Home Chronicle', 'owbn-election-bridge' ); ?> *</label>
		<?php if ( function_exists( 'owc_get_chronicles' ) ) : ?>
			<?php $chronicles = owc_get_chronicles(); ?>
			<select id="oeb-chronicle" name="home_chronicle" required>
				<option value=""><?php esc_html_e( '— Select chronicle —', 'owbn-election-bridge' ); ?></option>
				<?php foreach ( $chronicles as $ch ) : ?>
					<option value="<?php echo esc_attr( $ch['slug'] ?? '' ); ?>">
						<?php echo esc_html( $ch['title'] ?? $ch['slug'] ?? '' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input type="text" id="oeb-chronicle" name="home_chronicle" required>
		<?php endif; ?>
	</fieldset>

	<fieldset>
		<legend><?php esc_html_e( 'This application is written in:', 'owbn-election-bridge' ); ?> *</legend>
		<label>
			<input type="radio" name="source_language" value="en" checked>
			English
		</label>
		<label>
			<input type="radio" name="source_language" value="pt_BR">
			Português
		</label>
	</fieldset>

	<fieldset>
		<label for="oeb-intro"><?php esc_html_e( 'Introduction and Background', 'owbn-election-bridge' ); ?> *</label>
		<textarea id="oeb-intro" name="introduction" rows="8" required></textarea>
	</fieldset>

	<fieldset>
		<label for="oeb-experience"><?php esc_html_e( 'Administrative Experience', 'owbn-election-bridge' ); ?></label>
		<textarea id="oeb-experience" name="experience" rows="8"></textarea>
	</fieldset>

	<fieldset>
		<label for="oeb-statement"><?php esc_html_e( 'Personal Statement', 'owbn-election-bridge' ); ?></label>
		<textarea id="oeb-statement" name="personal_statement" rows="8"></textarea>
	</fieldset>

	<fieldset>
		<label for="oeb-goals"><?php esc_html_e( 'Goals', 'owbn-election-bridge' ); ?></label>
		<textarea id="oeb-goals" name="goals" rows="8"></textarea>
	</fieldset>

	<button type="submit" class="oeb-submit-btn">
		<?php esc_html_e( 'Submit Application', 'owbn-election-bridge' ); ?>
	</button>
</form>
