<?php
/**
 * Talking to the Bionic Uptime status API.
 *
 * The only outbound request this plugin makes lives in this file. It runs
 * only after an administrator has saved a key, and only when an
 * administrator opens the dashboard.
 *
 * @package BionicUptime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The status endpoint. Fixed, so the key is never sent anywhere else.
 */
define( 'BIONIC_UPTIME_API_URL', 'https://bionicuptime.com/api/v1/status' );

/**
 * How long a response, good or bad, is reused. A failure is cached too,
 * so an unreachable API costs one slow dashboard load a minute, not every one.
 */
define( 'BIONIC_UPTIME_CACHE_SECONDS', 60 );

/**
 * Whether a string has the shape of a status key: "bu_" and 43 base64url characters.
 *
 * @param string $key Candidate key.
 * @return bool
 */
function bionic_uptime_is_valid_key( $key ) {
	return is_string( $key ) && 1 === preg_match( '/^bu_[A-Za-z0-9_-]{43}$/', $key );
}

/**
 * The saved key, or an empty string if there is none or it is malformed.
 *
 * @return string
 */
function bionic_uptime_get_key() {
	$key = get_option( BIONIC_UPTIME_OPTION_KEY, '' );
	return bionic_uptime_is_valid_key( $key ) ? $key : '';
}

/**
 * A short, non-secret fingerprint of a key.
 *
 * Stored with the cached result so a cache written for one key is never
 * shown after the key has changed.
 *
 * @param string $key Status key.
 * @return string
 */
function bionic_uptime_key_fingerprint( $key ) {
	return substr( hash( 'sha256', $key ), 0, 16 );
}

/**
 * The status for the saved key, from the 60-second cache or from the API.
 *
 * Always returns an array with a 'kind' of:
 * - 'no_key'       nothing saved; no request is made.
 * - 'ok'           the API answered; 'state', 'label', 'detail', 'checked_at', 'details_url'.
 * - 'unauthorized' the API did not recognise the key.
 * - 'rate_limited' the API asked us to slow down.
 * - 'error'        anything else. We do not know the site's status.
 *
 * @return array
 */
function bionic_uptime_get_status() {
	$key = bionic_uptime_get_key();
	if ( '' === $key ) {
		return array( 'kind' => 'no_key' );
	}

	$fingerprint = bionic_uptime_key_fingerprint( $key );
	$cached      = get_transient( BIONIC_UPTIME_TRANSIENT );

	// Reuse the cache only if it is for this key and is not older than the
	// cache window. The age check is ours, not just the transient's, because a
	// persistent object cache may keep a transient past its expiry, and an
	// old "online" must never be shown as the current status.
	if (
		is_array( $cached )
		&& isset( $cached['fingerprint'], $cached['fetched_at'], $cached['result'] )
		&& $cached['fingerprint'] === $fingerprint
		&& is_int( $cached['fetched_at'] )
		&& ( time() - $cached['fetched_at'] ) >= 0
		&& ( time() - $cached['fetched_at'] ) < BIONIC_UPTIME_CACHE_SECONDS
		&& is_array( $cached['result'] )
	) {
		return $cached['result'];
	}

	$result = bionic_uptime_fetch_status( $key );

	set_transient(
		BIONIC_UPTIME_TRANSIENT,
		array(
			'fingerprint' => $fingerprint,
			'fetched_at'  => time(),
			'result'      => $result,
		),
		BIONIC_UPTIME_CACHE_SECONDS
	);

	return $result;
}

/**
 * Ask the API for the status. No caching here.
 *
 * @param string $key A key that has passed bionic_uptime_is_valid_key().
 * @return array See bionic_uptime_get_status().
 */
function bionic_uptime_fetch_status( $key ) {
	$response = wp_remote_get(
		BIONIC_UPTIME_API_URL,
		array(
			'timeout'     => 5,
			// A redirect would carry the Authorization header somewhere else.
			'redirection' => 0,
			'user-agent'  => 'BionicUptimeWP/' . BIONIC_UPTIME_VERSION . '; ' . home_url(),
			'headers'     => array(
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'kind' => 'error' );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( 401 === $code ) {
		return array( 'kind' => 'unauthorized' );
	}
	if ( 429 === $code ) {
		return array( 'kind' => 'rate_limited' );
	}
	if ( 200 !== $code ) {
		return array( 'kind' => 'error' );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) ) {
		return array( 'kind' => 'error' );
	}

	// A response without a state and a label tells us nothing, and nothing
	// must never be shown as a status.
	if (
		! isset( $body['state'], $body['label'] )
		|| ! is_string( $body['state'] )
		|| ! is_string( $body['label'] )
		|| '' === trim( $body['label'] )
	) {
		return array( 'kind' => 'error' );
	}

	$detail = ( isset( $body['detail'] ) && is_string( $body['detail'] ) ) ? $body['detail'] : '';

	$checked_at = null;
	if ( isset( $body['checked_at'] ) && is_string( $body['checked_at'] ) ) {
		$timestamp = strtotime( $body['checked_at'] );
		if ( false !== $timestamp ) {
			$checked_at = $timestamp;
		}
	}

	// Only link to Bionic Uptime itself.
	$details_url = '';
	if (
		isset( $body['details_url'] )
		&& is_string( $body['details_url'] )
		&& 0 === strpos( $body['details_url'], 'https://bionicuptime.com/' )
	) {
		$details_url = $body['details_url'];
	}

	return array(
		'kind'        => 'ok',
		'state'       => $body['state'],
		'label'       => $body['label'],
		'detail'      => $detail,
		'checked_at'  => $checked_at,
		'details_url' => $details_url,
	);
}
