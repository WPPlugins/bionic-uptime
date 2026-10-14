<?php
/**
 * The "Bionic Uptime" dashboard widget.
 *
 * Rule for this file: the words and the colour both come from what the API
 * said. Green is used only when the API said state "online" in a response no
 * older than about two minutes; the script greys out anything older. Anything we do
 * not know is grey.
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
 * Load the widget's stylesheet and refresh script on the dashboard only.
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
	wp_enqueue_script(
		'bionic-uptime-dashboard',
		BIONIC_UPTIME_URL . 'assets/js/dashboard.js',
		array( 'wp-a11y', 'wp-i18n' ),
		BIONIC_UPTIME_VERSION,
		true
	);
	wp_set_script_translations( 'bionic-uptime-dashboard', 'bionic-uptime' );
	wp_localize_script(
		'bionic-uptime-dashboard',
		'bionicUptimeWidget',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bionic_uptime_widget' ),
		)
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
 *
 * The widget refreshes itself (assets/js/dashboard.js), because a dashboard is
 * often left open for hours, and a status drawn once would go on saying
 * "Online" long after anyone knew it. The data attributes tell the script how
 * old this answer already is and whether there is a status to keep fresh.
 */
function bionic_uptime_render_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$entry = bionic_uptime_get_status_entry();

	printf(
		'<div class="bionic-uptime-widget" id="bionic-uptime-widget" data-refresh="%s" data-age="%d" data-said="%s">',
		esc_attr( 'no_key' === $entry['result']['kind'] ? '0' : '1' ),
		(int) bionic_uptime_answer_age( $entry ),
		esc_attr( bionic_uptime_said( $entry['result'] ) )
	);
	bionic_uptime_print_widget_body( $entry['result'] );
	echo '</div>';
}

/**
 * Seconds since the API gave this answer. Never negative.
 *
 * @param array $entry From bionic_uptime_get_status_entry().
 * @return int
 */
function bionic_uptime_answer_age( $entry ) {
	return max( 0, time() - (int) $entry['fetched_at'] );
}

/**
 * The words a status change is announced with, or '' for no status.
 * The script announces only when this changes, so a refresh that finds the
 * same status says nothing.
 *
 * @param array $status From bionic_uptime_get_status().
 * @return string
 */
function bionic_uptime_said( $status ) {
	if ( 'ok' === $status['kind'] ) {
		return $status['label'];
	}
	if ( 'no_key' === $status['kind'] ) {
		return '';
	}
	return 'unauthorized' === $status['kind']
		? __( 'This key is not recognised.', 'bionic-uptime' )
		: __( 'Could not reach Bionic Uptime', 'bionic-uptime' );
}

/**
 * The widget's insides, for the dashboard and for the refresh.
 *
 * @param array $status From bionic_uptime_get_status().
 */
function bionic_uptime_print_widget_body( $status ) {
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
}

add_action( 'wp_ajax_bionic_uptime_widget', 'bionic_uptime_ajax_widget' );

/**
 * The widget's refresh: the same insides, built the same way, with the age of
 * the answer. Administrators only, with the dashboard's nonce.
 */
function bionic_uptime_ajax_widget() {
	check_ajax_referer( 'bionic_uptime_widget', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	$entry = bionic_uptime_get_status_entry();
	ob_start();
	bionic_uptime_print_widget_body( $entry['result'] );
	wp_send_json_success(
		array(
			'html' => ob_get_clean(),
			'age'  => bionic_uptime_answer_age( $entry ),
			'said' => bionic_uptime_said( $entry['result'] ),
		)
	);
}
