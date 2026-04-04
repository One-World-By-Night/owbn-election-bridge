<?php
defined( 'ABSPATH' ) || exit;

// Available in scope: $election, $current_user
?>
<style>
.oeb-application-form {
	max-width: 1100px;
	margin: 0 auto;
}
.oeb-application-form .oeb-field {
	margin-bottom: 24px;
}
.oeb-application-form .oeb-field label,
.oeb-application-form .oeb-field legend {
	display: block;
	font-weight: 700;
	font-size: 1em;
	margin-bottom: 6px;
	padding: 0;
}
.oeb-application-form select,
.oeb-application-form input[type="text"],
.oeb-application-form input[type="email"] {
	width: 100%;
	max-width: 500px;
	padding: 8px 12px;
	font-size: 1em;
	border: 1px solid #8c8f94;
	border-radius: 4px;
}
.oeb-application-form .oeb-radio-group {
	display: flex;
	gap: 24px;
	margin-top: 4px;
}
.oeb-application-form .oeb-radio-group label {
	font-weight: 400;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.oeb-application-form .oeb-submit-btn {
	background: #2271b1;
	color: #fff;
	border: none;
	padding: 12px 32px;
	font-size: 1em;
	font-weight: 600;
	border-radius: 4px;
	cursor: pointer;
	margin-top: 12px;
}
.oeb-application-form .oeb-submit-btn:hover {
	background: #135e96;
}
.oeb-application-form .wp-editor-wrap {
	border: 1px solid #8c8f94;
	border-radius: 4px;
}
</style>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oeb-application-form">
	<?php wp_nonce_field( OEB_Application_Form::ACTION ); ?>
	<input type="hidden" name="action" value="<?php echo esc_attr( OEB_Application_Form::ACTION ); ?>">

	<div class="oeb-field">
		<label for="oeb-position"><?php esc_html_e( 'Position Applied For', 'owbn-election-bridge' ); ?> *</label>
		<select id="oeb-position" name="position" required>
			<option value=""><?php esc_html_e( '— Select a position —', 'owbn-election-bridge' ); ?></option>
			<?php foreach ( $election->positions as $pos ) : ?>
				<option value="<?php echo esc_attr( $pos['coordinator_slug'] ); ?>">
					<?php echo esc_html( $pos['coordinator_title'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="oeb-field">
		<label for="oeb-name"><?php esc_html_e( 'Applicant Name', 'owbn-election-bridge' ); ?> *</label>
		<input type="text" id="oeb-name" name="applicant_name"
			value="<?php echo esc_attr( $current_user->display_name ); ?>" required>
	</div>

	<div class="oeb-field">
		<label for="oeb-email"><?php esc_html_e( 'Applicant Email', 'owbn-election-bridge' ); ?> *</label>
		<input type="email" id="oeb-email" name="applicant_email"
			value="<?php echo esc_attr( $current_user->user_email ); ?>" required>
	</div>

	<div class="oeb-field">
		<label for="oeb-chronicle"><?php esc_html_e( 'Home Chronicle', 'owbn-election-bridge' ); ?> *</label>
		<?php if ( function_exists( 'owc_get_chronicles' ) ) : ?>
			<?php
			$chronicles = array_filter( owc_get_chronicles(), function ( $ch ) {
				return ( $ch['status'] ?? '' ) === 'publish';
			} );
			usort( $chronicles, function ( $a, $b ) {
				return strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
			} );
			?>
			<select id="oeb-chronicle" name="home_chronicle" required style="width: 100%; max-width: 500px;">
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
	</div>

	<div class="oeb-field">
		<legend><?php esc_html_e( 'What language is this application written in?', 'owbn-election-bridge' ); ?> *</legend>
		<div class="oeb-radio-group">
			<label>
				<input type="radio" name="source_language" value="en" checked>
				English
			</label>
			<label>
				<input type="radio" name="source_language" value="pt_BR">
				Português
			</label>
		</div>
	</div>

	<div class="oeb-field">
		<label><?php esc_html_e( 'Introduction and Background', 'owbn-election-bridge' ); ?> *</label>
		<?php wp_editor( '', 'introduction', [
			'textarea_name' => 'introduction',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
		] ); ?>
	</div>

	<div class="oeb-field">
		<label><?php esc_html_e( 'Administrative Experience', 'owbn-election-bridge' ); ?></label>
		<?php wp_editor( '', 'experience', [
			'textarea_name' => 'experience',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
		] ); ?>
	</div>

	<div class="oeb-field">
		<label><?php esc_html_e( 'Personal Statement', 'owbn-election-bridge' ); ?></label>
		<?php wp_editor( '', 'personal_statement', [
			'textarea_name' => 'personal_statement',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
		] ); ?>
	</div>

	<div class="oeb-field">
		<label><?php esc_html_e( 'Goals', 'owbn-election-bridge' ); ?></label>
		<?php wp_editor( '', 'goals', [
			'textarea_name' => 'goals',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
		] ); ?>
	</div>

	<button type="submit" class="oeb-submit-btn">
		<?php esc_html_e( 'Submit Application', 'owbn-election-bridge' ); ?>
	</button>
</form>

<script>
jQuery(document).ready(function($) {
	$('#oeb-chronicle').select2({ placeholder: '<?php echo esc_js( __( '— Select chronicle —', 'owbn-election-bridge' ) ); ?>', allowClear: true, width: '100%' });
	$('#oeb-position').select2({ placeholder: '<?php echo esc_js( __( '— Select a position —', 'owbn-election-bridge' ) ); ?>', allowClear: true, width: '100%' });
});
</script>
