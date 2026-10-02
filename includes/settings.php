<?php
/**
 * Settings -> Bionic Uptime.
 *
 * @package BionicUptime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug of the settings page.
 */
define( 'BIONIC_UPTIME_PAGE', 'bionic-uptime' );

add_action( 'admin_menu', 'bionic_uptime_add_settings_page' );
add_action( 'admin_post_bionic_uptime_save', 'bionic_uptime_handle_save' );
add_filter( 'plugin_action_links_' . plugin_basename( BIONIC_UPTIME_FILE ), 'bionic_uptime_action_links' );

/**
 * Register the settings page.
 */
function bionic_uptime_add_settings_page() {
	add_options_page(
		__( 'Bionic Uptime', 'bionic-uptime' ),
		__( 'Bionic Uptime', 'bionic-uptime' ),
		'manage_options',
		BIONIC_UPTIME_PAGE,
		'bionic_uptime_render_settings_page'
	);
}

/**
 * URL of the settings page.
 *
 * @return string
 */
function bionic_uptime_settings_url() {
	return admin_url( 'options-general.php?page=' . BIONIC_UPTIME_PAGE );
}

/**
 * Add a "Settings" link on the Plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function bionic_uptime_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( bionic_uptime_settings_url() ),
			esc_html__( 'Settings', 'bionic-uptime' )
		);
	}
	return $links;
}

/**
 * The saved key, shortened so it is never shown in full: "bu_…abcd".
 *
 * @param string $key Status key.
 * @return string
 */
function bionic_uptime_mask_key( $key ) {
	return 'bu_…' . substr( $key, -4 );
}

/**
 * Save, replace or remove the key.
 */
function bionic_uptime_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change these settings.', 'bionic-uptime' ), 403 );
	}

	check_admin_referer( 'bionic_uptime_save', 'bionic_uptime_nonce' );

	$message = 'unchanged';

	if ( ! empty( $_POST['bionic_uptime_remove'] ) ) {
		delete_option( BIONIC_UPTIME_OPTION_KEY );
		delete_transient( BIONIC_UPTIME_TRANSIENT );
		$message = 'removed';
	} else {
		$key = isset( $_POST['bionic_uptime_key'] )
			? trim( sanitize_text_field( wp_unslash( $_POST['bionic_uptime_key'] ) ) )
			: '';

		if ( '' !== $key ) {
			if ( bionic_uptime_is_valid_key( $key ) ) {
				// Not autoloaded: it is only needed on the dashboard.
				update_option( BIONIC_UPTIME_OPTION_KEY, $key, false );
				delete_transient( BIONIC_UPTIME_TRANSIENT );
				$message = 'saved';
			} else {
				$message = 'invalid';
			}
		}
	}

	wp_safe_redirect( add_query_arg( 'bionic_uptime_msg', $message, bionic_uptime_settings_url() ) );
	exit;
}

/**
 * Render the settings page.
 */
function bionic_uptime_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$key = bionic_uptime_get_key();

	// Only picks which fixed message to show after a redirect. It changes nothing.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msg = isset( $_GET['bionic_uptime_msg'] ) ? sanitize_key( wp_unslash( $_GET['bionic_uptime_msg'] ) ) : '';

	$messages = array(
		'saved'     => array( 'success', __( 'Key saved.', 'bionic-uptime' ) ),
		'removed'   => array( 'success', __( 'Key removed.', 'bionic-uptime' ) ),
		'unchanged' => array( 'info', __( 'Nothing was changed.', 'bionic-uptime' ) ),
		'invalid'   => array( 'error', __( 'That does not look like a Bionic Uptime key. A key starts with bu_ and is 46 characters long. Nothing was saved.', 'bionic-uptime' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bionic Uptime', 'bionic-uptime' ); ?></h1>

		<?php if ( isset( $messages[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $msg ][0] ); ?> is-dismissible">
				<p><?php echo esc_html( $messages[ $msg ][1] ); ?></p>
			</div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Bionic Uptime checks this site from outside every 60 seconds, from two locations on two different networks, and emails you when it is down. The key only lets this plugin read this one website\'s status. It cannot change anything.', 'bionic-uptime' ); ?></p>

		<p>
			<?php esc_html_e( '3 websites are free forever. No card needed.', 'bionic-uptime' ); ?>
			<a href="https://bionicuptime.com/wordpress" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get a free key', 'bionic-uptime' ); ?></a>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bionic_uptime_save" />
			<?php wp_nonce_field( 'bionic_uptime_save', 'bionic_uptime_nonce' ); ?>

			<table class="form-table" role="presentation">
				<?php if ( '' !== $key ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved key', 'bionic-uptime' ); ?></th>
						<td>
							<code><?php echo esc_html( bionic_uptime_mask_key( $key ) ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bionic_uptime_key"><?php esc_html_e( 'Replace key', 'bionic-uptime' ); ?></label></th>
						<td>
							<input type="password" id="bionic_uptime_key" name="bionic_uptime_key" class="regular-text code" value="" autocomplete="off" spellcheck="false" />
							<p class="description"><?php esc_html_e( 'Leave this empty to keep the saved key.', 'bionic-uptime' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove key', 'bionic-uptime' ); ?></th>
						<td>
							<label for="bionic_uptime_remove">
								<input type="checkbox" id="bionic_uptime_remove" name="bionic_uptime_remove" value="1" />
								<?php esc_html_e( 'Remove the saved key. The plugin will stop contacting Bionic Uptime.', 'bionic-uptime' ); ?>
							</label>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th scope="row"><label for="bionic_uptime_key"><?php esc_html_e( 'Status key', 'bionic-uptime' ); ?></label></th>
						<td>
							<input type="password" id="bionic_uptime_key" name="bionic_uptime_key" class="regular-text code" value="" autocomplete="off" spellcheck="false" />
							<p class="description"><?php esc_html_e( 'Paste the key from your Bionic Uptime account. It starts with bu_.', 'bionic-uptime' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<?php submit_button( __( 'Save', 'bionic-uptime' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Privacy', 'bionic-uptime' ); ?></h2>
		<p>
			<?php esc_html_e( 'Only when an administrator opens the dashboard, and at most once a minute, this plugin sends the key and this site\'s address (in the User-Agent) to bionicuptime.com. Nothing is sent before a key is saved, and nothing is sent from the public side of your site.', 'bionic-uptime' ); ?>
			<a href="https://bionicuptime.com/privacy" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'bionic-uptime' ); ?></a>
		</p>
	</div>
	<?php
}
