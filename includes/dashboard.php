<?php
/**
 * The "Bionic Uptime" dashboard widget.
 *
 * Rule for this file: the words and the colour both come from what the API
 * said. Green is used only when the API said state "online" in a response no
 * older than the cache window. Anything we do not know is grey.
 *
 * @package BionicUptime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', 'bionic_uptime_add_dashboard_widget' );
add_action( 'admin_enqueue_scripts', 'bionic_uptime_enqueue_dashboard_css' );

/**
 * Register the widget for administrators only.
 */
function bionic_uptime_add_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'bionic_uptime_widget',
		__( 'Bionic Uptime', 'bionic-uptime' ),
		'bionic_uptime_render_dashboard_widget'
	);
}

/**
 * Load the widget's small stylesheet on the dashboard only.
 *
 * @param string $hook_suffix Current admin page.
 */
function bionic_uptime_enqueue_dashboard_css( $hook_suffix ) {
	if ( 'index.php' !== $hook_suffix || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_enqueue_style(
		'bionic-uptime-dashboard',
		BIONIC_UPTIME_URL . 'assets/css/dashboard.css',
		array(),
		BIONIC_UPTIME_VERSION
	);
}

/**
 * Which look a state gets. Each look is a colour plus a shape, so the
 * status never depends on colour alone. Unknown states are grey.
 *
 * @param string $state State from the API.
 * @return string One of: online, down, amber, paused, stopped, neutral.
 */
function bionic_uptime_state_look( $state ) {
	switch ( $state ) {
		case 'online':
			return 'online';
		case 'down':
			return 'down';
		case 'checking':
		case 'uncertain':
		case 'delayed':
			return 'amber';
		case 'paused':
			return 'paused';
		case 'stopped_free_limit':
			return 'stopped';
		default:
			return 'neutral';
	}
}

/**
 * Print one status line: a shape (hidden from screen readers) and the words.
 *
 * @param string $look  A value from bionic_uptime_state_look(), or 'neutral'.
 * @param string $label The words to show, exactly as given.
 */
function bionic_uptime_print_status( $look, $label ) {
	?>
	<p class="bionic-uptime-status bionic-uptime-look-<?php echo esc_attr( $look ); ?>">
		<span class="bionic-uptime-shape" aria-hidden="true"></span>
		<span class="bionic-uptime-label"><?php echo esc_html( $label ); ?></span>
	</p>
	<?php
}

/**
 * Render the widget.
 */
function bionic_uptime_render_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$status = bionic_uptime_get_status();

	echo '<div class="bionic-uptime-widget">';

	switch ( $status['kind'] ) {
		case 'no_key':
			?>
			<p class="bionic-uptime-quiet">
				<?php esc_html_e( 'Add a status key to see this site\'s status here.', 'bionic-uptime' ); ?>
				<a href="<?php echo esc_url( bionic_uptime_settings_url() ); ?>"><?php esc_html_e( 'Set it up', 'bionic-uptime' ); ?></a>
			</p>
			<?php
			break;

		case 'unauthorized':
			bionic_uptime_print_status( 'neutral', __( 'This key is not recognised. Check it in Settings -> Bionic Uptime.', 'bionic-uptime' ) );
			?>
			<p class="bionic-uptime-meta">
				<a href="<?php echo esc_url( bionic_uptime_settings_url() ); ?>"><?php esc_html_e( 'Open settings', 'bionic-uptime' ); ?></a>
			</p>
			<?php
			break;

		case 'rate_limited':
			bionic_uptime_print_status( 'neutral', __( 'Could not reach Bionic Uptime', 'bionic-uptime' ) );
			?>
			<p class="bionic-uptime-meta"><?php esc_html_e( 'Bionic Uptime asked for fewer requests. The plugin will try again in a minute.', 'bionic-uptime' ); ?></p>
			<?php
			break;

		case 'ok':
			bionic_uptime_print_status( bionic_uptime_state_look( $status['state'] ), $status['label'] );

			if ( '' !== $status['detail'] ) {
				echo '<p class="bionic-uptime-detail">' . esc_html( $status['detail'] ) . '</p>';
			}

			$checked = '';
			if ( null !== $status['checked_at'] ) {
				$now = time();
				if ( $status['checked_at'] >= $now ) {
					$checked = __( 'Checked just now', 'bionic-uptime' );
				} else {
					$checked = sprintf(
						/* translators: %s: how long ago, for example "30 secs" or "2 mins". */
						__( 'Checked %s ago', 'bionic-uptime' ),
						human_time_diff( $status['checked_at'], $now )
					);
				}
			}
			$has_link = ( '' !== $status['details_url'] );

			if ( '' !== $checked || $has_link ) {
				echo '<p class="bionic-uptime-meta">';
				if ( '' !== $checked ) {
					echo esc_html( $checked );
				}
				if ( '' !== $checked && $has_link ) {
					echo ' &middot; ';
				}
				if ( $has_link ) {
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $status['details_url'] ),
						esc_html__( 'See details', 'bionic-uptime' )
					);
				}
				echo '</p>';
			}
			break;

		default:
			bionic_uptime_print_status( 'neutral', __( 'Could not reach Bionic Uptime', 'bionic-uptime' ) );
			?>
			<p class="bionic-uptime-meta"><?php esc_html_e( 'We do not know this site\'s status right now. The plugin will try again in a minute.', 'bionic-uptime' ); ?></p>
			<?php
			break;
	}

	echo '</div>';
}
