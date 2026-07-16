<?php
/**
 * Traveler and partner identity adapters.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render social login only when a real provider plugin is available.
 *
 * Nextend renders buttons only for providers that have been configured,
 * tested and enabled in WordPress. The theme never creates imitation buttons.
 *
 * @param string $redirect_url Destination after a successful login.
 * @return string
 */
function tra_vel_v2_social_login( $redirect_url ) {
	if ( ! shortcode_exists( 'nextend_social_login' ) ) {
		return '';
	}

	return do_shortcode(
		sprintf(
			'[nextend_social_login style="fullwidth" redirect="%s" login="1" link="0" unlink="0"]',
			esc_url_raw( $redirect_url )
		)
	);
}

/**
 * Determine whether the current account can access supplier operations.
 *
 * A supplier plugin can grant access through the filter without weakening the
 * default WordPress capability check.
 *
 * @param int $user_id WordPress user ID.
 * @return bool
 */
function tra_vel_v2_user_can_access_supplier_portal( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	$allowed = $user_id > 0 && user_can( $user_id, 'edit_posts' );

	/**
	 * Filter supplier portal access for verified partner roles.
	 *
	 * @param bool $allowed Current access decision.
	 * @param int  $user_id WordPress user ID.
	 */
	return (bool) apply_filters( 'tra_vel_v2_supplier_portal_access', $allowed, $user_id );
}

