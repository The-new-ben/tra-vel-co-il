<?php
/**
 * Read-only dual-mode REST bridge for the customer-safe Trip Cockpit.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Customer_Trip_Cockpit_Controller extends WP_REST_Controller {
	const VIEW_CONTEXT_TTL_SECONDS = 300;
	const READ_WINDOW_SECONDS       = 600;
	const READ_LIMIT                = 60;

	/** @var Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider */
	private $provider;

	/** @var Tra_Vel_VIP_Capability_Session_Store */
	private $capability_store;

	/** @var array<string,array> */
	private $authorized = array();

	public function __construct( $provider = null, $capability_store = null ) {
		$this->namespace        = 'tra-vel-agent/v1';
		$this->rest_base        = 'customer-trip-cockpit/current';
		$this->provider         = $provider instanceof Tra_Vel_Customer_Trip_Cockpit_Read_Model_Provider ? $provider : new Tra_Vel_Customer_Trip_Cockpit_Store();
		$this->capability_store = $capability_store ? $capability_store : new Tra_Vel_VIP_Capability_Session_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
		add_filter( 'rest_post_dispatch', array( $this, 'secure_route_response' ), 10, 3 );
	}

	/** Apply the private cache/index boundary to successes and permission errors. */
	public function secure_route_response( $response, $server, $request ) {
		$route = $request instanceof WP_REST_Request ? (string) $request->get_route() : '';
		if ( '/' . $this->namespace . '/' . $this->rest_base !== $route ) {
			return $response;
		}
		$response = is_wp_error( $response ) ? rest_convert_error_to_response( $response ) : rest_ensure_response( $response );
		return $this->private_headers( $response );
	}

	/** Resolve and cache one exact server-side projection/context pair. */
	public function can_read( $request ) {
		$key = $this->request_key( $request );
		unset( $this->authorized[ $key ] );
		$closed = $this->closed_read_request( $request );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		$same_site = $this->same_site_read( $request );
		if ( is_wp_error( $same_site ) ) {
			return $same_site;
		}
		$mode = (string) $request->get_header( 'X-Tra-Vel-Cockpit-Mode' );
		$result = 'signed-in' === $mode ? $this->authorize_signed_in( $request ) : $this->authorize_scoped_session( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->authorized[ $key ] = array(
			'fingerprint' => $this->request_fingerprint( $request ),
			'projection'  => $result['projection'],
			'context'     => $result['context'],
		);
		return true;
	}

	/** Return only the validated 21-field customer view; never the private model. */
	public function get_current( $request ) {
		$key = $this->request_key( $request );
		if ( ! isset( $this->authorized[ $key ] ) || ! hash_equals( $this->authorized[ $key ]['fingerprint'], $this->request_fingerprint( $request ) ) ) {
			unset( $this->authorized[ $key ] );
			return self::error( 'not_authorized', 'The private trip view could not be authorized.', 403 );
		}
		$authorized = $this->authorized[ $key ];
		unset( $this->authorized[ $key ] );
		$now  = time();
		$view = Tra_Vel_Customer_Trip_Cockpit_Customer_View_Factory::create_view( $authorized['projection'], $authorized['context'], $now );
		if ( is_wp_error( $view ) ) {
			error_log( 'Tra-Vel customer Trip Cockpit projection failed final validation: ' . $view->get_error_code() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return self::error( 'view_unavailable', 'The private trip view is temporarily unavailable.', 503 );
		}
		return $this->private_response( $view, 200, $authorized['context']['expires_at'] );
	}

	/** @return array|WP_Error */
	private function authorize_signed_in( $request ) {
		$owner_user_id = (int) get_current_user_id();
		if ( $owner_user_id < 1 ) {
			return self::error( 'login_required', 'Sign in to view this trip.', 401 );
		}
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return self::error( 'nonce_invalid', 'The signed-in trip view could not be verified.', 403 );
		}
		if ( ! current_user_can( 'read' ) ) {
			return self::error( 'read_denied', 'This account cannot view private trips.', 403 );
		}
		$limited = $this->consume_read_limit( 'owner|' . get_current_blog_id() . '|' . $owner_user_id );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		if ( ! $this->provider->is_ready() ) {
			return self::error( 'store_unavailable', 'The private trip view is temporarily unavailable.', 503 );
		}
		$record = $this->provider->get_owned_current_projection( $owner_user_id, time() );
		if ( is_wp_error( $record ) ) {
			return $this->provider_error( $record );
		}
		if ( ! $this->provider_record_valid( $record ) || (int) $record['owner_user_id'] !== $owner_user_id ) {
			return self::error( 'view_unavailable', 'The private trip view is temporarily unavailable.', 503 );
		}
		$expected_scope = Tra_Vel_Traveler_Principal::cockpit_owner_scope_digest( $owner_user_id, $record['account_ref'], $record['trip_ref'] );
		if ( '' === $expected_scope || ! hash_equals( $expected_scope, $record['owner_scope_digest'] ) ) {
			return self::error( 'view_unavailable', 'The private trip view is temporarily unavailable.', 503 );
		}
		$now = time();
		return array(
			'projection' => $record['projection'],
			'context' => array(
				'mode' => 'signed_in', 'verified' => true, 'verified_at' => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
				'trip_ref' => $record['trip_ref'], 'owner_scope_digest' => $expected_scope,
				'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $now + self::VIEW_CONTEXT_TTL_SECONDS ),
				'scopes' => $this->signed_in_scopes( $owner_user_id, $record ), 'disclosure' => 'trip_redacted',
			),
		);
	}

	/** @return array|WP_Error */
	private function authorize_scoped_session( $request ) {
		$session_value = $this->session_cookie_value();
		if ( is_wp_error( $session_value ) ) {
			return $session_value;
		}
		$limited = $this->consume_read_limit( 'session|' . Tra_Vel_VIP_Capability_Session_Store::session_digest( $session_value ) );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		if ( ! $this->provider->is_ready() || ! $this->capability_store->is_ready() ) {
			return self::error( 'store_unavailable', 'The secure trip link is temporarily unavailable.', 503 );
		}
		$session = $this->capability_store->current_session( $session_value, time() );
		if ( is_wp_error( $session ) || ! is_array( $session ) || ! array_key_exists( 'case_ref', $session ) || ! isset( $session['trip_ref'], $session['account_ref'], $session['allowed_scopes'], $session['expires_at'] ) || ! Tra_Vel_Traveler_Principal::valid_ref( $session['trip_ref'], 'trip' ) || ! Tra_Vel_Traveler_Principal::valid_ref( $session['account_ref'], 'account' ) || ! is_array( $session['allowed_scopes'] ) ) {
			return self::missing_session();
		}
		if ( null !== $session['case_ref'] || null === $session['account_ref'] ) {
			return self::missing_session();
		}
		$record = $this->provider->get_bound_projection( $session['trip_ref'], null, $session['account_ref'], time() );
		if ( is_wp_error( $record ) ) {
			return 503 === (int) ( $record->get_error_data()['status'] ?? 0 ) ? $this->provider_error( $record ) : self::missing_session();
		}
		if ( ! $this->provider_record_valid( $record ) || null !== $record['case_ref'] || ! hash_equals( $session['trip_ref'], $record['trip_ref'] ) || ! hash_equals( $session['account_ref'], $record['account_ref'] ) ) {
			return self::missing_session();
		}
		$binding = array( 'trip_ref' => $record['trip_ref'], 'case_ref' => null, 'account_ref' => $record['account_ref'] );
		$resolved = $this->capability_store->resolve_scoped_session( $session_value, $binding, 'trip_view_redacted', 'trip_redacted', time() );
		if ( is_wp_error( $resolved ) ) {
			return self::missing_session();
		}
		$now     = time();
		$expires = min( strtotime( (string) $resolved['expires_at'] ), $now + self::VIEW_CONTEXT_TTL_SECONDS );
		if ( $expires <= $now ) {
			return self::missing_session();
		}
		$scopes = array_values( array_intersect( array( 'trip_view_redacted', 'incident_report', 'case_progress_view' ), (array) $resolved['allowed_scopes'] ) );
		if ( ! in_array( 'trip_view_redacted', $scopes, true ) ) {
			return self::missing_session();
		}
		return array(
			'projection' => $record['projection'],
			'context' => array(
				'mode' => 'scoped_session', 'verified' => true, 'verified_at' => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
				'trip_ref' => $record['trip_ref'], 'owner_scope_digest' => null,
				'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $expires ), 'scopes' => $scopes, 'disclosure' => 'trip_redacted',
			),
		);
	}

	private function signed_in_scopes( $owner_user_id, $record ) {
		$allowed = array( 'trip_view_redacted', 'incident_report', 'case_progress_view' );
		$requested = apply_filters( 'tra_vel_customer_trip_cockpit_signed_in_scopes', $allowed, $owner_user_id, $record['trip_ref'] );
		$scopes = is_array( $requested ) ? array_values( array_intersect( $allowed, $requested ) ) : array();
		if ( ! in_array( 'trip_view_redacted', $scopes, true ) ) {
			$scopes[] = 'trip_view_redacted';
		}
		return array_values( array_unique( $scopes ) );
	}

	private function consume_read_limit( $principal ) {
		$window  = self::READ_WINDOW_SECONDS;
		$expires = ( (int) floor( time() / $window ) + 1 ) * $window + MINUTE_IN_SECONDS;
		$key     = hash_hmac( 'sha256', 'customer-trip-cockpit-read-v1|' . (string) $principal, wp_salt( 'nonce' ) );
		$result  = $this->provider->consume_limit( $key, self::READ_LIMIT, $expires );
		if ( is_wp_error( $result ) ) {
			return self::error( 'limit_unavailable', 'The private trip view is temporarily unavailable.', 503 );
		}
		return true === $result ? true : new WP_Error( 'tra_vel_customer_trip_cockpit_rate_limited', 'Too many private trip requests were made. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, $expires - time() ) ) );
	}

	private function closed_read_request( $request ) {
		$query = $request->get_query_params();
		$body  = trim( (string) $request->get_body() );
		$mode  = (string) $request->get_header( 'X-Tra-Vel-Cockpit-Mode' );
		$intent = (string) $request->get_header( 'X-Tra-Vel-Cockpit-Read' );
		if ( ! empty( $query ) || '' !== $body || '1' !== $intent || ! in_array( $mode, array( 'signed-in', 'scoped-session' ), true ) ) {
			return self::error( 'request_invalid', 'A closed private trip-view request is required.', 400 );
		}
		return true;
	}

	/** Reject an explicit foreign origin while allowing same-origin GETs that omit Origin. */
	private function same_site_read( $request ) {
		$fetch_site = strtolower( trim( (string) $request->get_header( 'Sec-Fetch-Site' ) ) );
		if ( '' !== $fetch_site && 'same-origin' !== $fetch_site ) {
			return self::error( 'origin_rejected', 'The private trip view must be opened from this site.', 403 );
		}
		$origin = trim( (string) $request->get_header( 'Origin' ) );
		if ( '' === $origin ) {
			return true;
		}
		$actual = wp_parse_url( $origin );
		$home   = wp_parse_url( home_url( '/' ) );
		if (
			! is_array( $actual )
			|| ! is_array( $home )
			|| isset( $actual['user'] )
			|| isset( $actual['pass'] )
			|| isset( $actual['path'] )
			|| isset( $actual['query'] )
			|| isset( $actual['fragment'] )
		) {
			return self::error( 'origin_rejected', 'The private trip view must be opened from this site.', 403 );
		}
		$actual_port = isset( $actual['port'] ) ? (int) $actual['port'] : ( 'https' === strtolower( (string) ( $actual['scheme'] ?? '' ) ) ? 443 : 80 );
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === strtolower( (string) ( $home['scheme'] ?? '' ) ) ? 443 : 80 );
		if ( strtolower( (string) ( $actual['scheme'] ?? '' ) ) !== strtolower( (string) ( $home['scheme'] ?? '' ) ) || strtolower( (string) ( $actual['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) ) || $actual_port !== $home_port ) {
			return self::error( 'origin_rejected', 'The private trip view must be opened from this site.', 403 );
		}
		return true;
	}

	private function session_cookie_value() {
		$name = Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE;
		if ( empty( $_COOKIE[ $name ] ) ) {
			return self::missing_session();
		}
		$value = rawurldecode( wp_unslash( (string) $_COOKIE[ $name ] ) );
		return 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $value ) ? $value : self::missing_session();
	}

	private function provider_record_valid( $record ) {
		$keys = array( 'projection', 'owner_user_id', 'account_ref', 'trip_ref', 'case_ref', 'owner_scope_digest' );
		return is_array( $record ) && ! array_diff( $keys, array_keys( $record ) ) && ! array_diff( array_keys( $record ), $keys ) && is_array( $record['projection'] ) && is_int( $record['owner_user_id'] ) && Tra_Vel_Traveler_Principal::valid_ref( $record['account_ref'], 'account' ) && Tra_Vel_Traveler_Principal::valid_ref( $record['trip_ref'], 'trip' ) && null === $record['case_ref'] && is_string( $record['owner_scope_digest'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $record['owner_scope_digest'] );
	}

	private function provider_error( $error ) {
		$status = (int) ( $error->get_error_data()['status'] ?? 503 );
		return 404 === $status ? self::error( 'not_found', 'No active private trip is available.', 404 ) : self::error( 'view_unavailable', 'The private trip view is temporarily unavailable.', 503 );
	}

	private function request_key( $request ) {
		return is_object( $request ) ? spl_object_hash( $request ) : 'invalid';
	}

	private function request_fingerprint( $request ) {
		$cookie_name = Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE;
		$cookie = isset( $_COOKIE[ $cookie_name ] ) ? (string) $_COOKIE[ $cookie_name ] : '';
		$material = implode( '|', array(
			'customer-trip-cockpit-controller-v1', $this->request_key( $request ), (string) $request->get_method(),
			(string) $request->get_route(), wp_json_encode( $request->get_query_params() ), (string) $request->get_body(),
			(string) $request->get_header( 'Origin' ), (string) $request->get_header( 'Sec-Fetch-Site' ),
			(string) $request->get_header( 'X-Tra-Vel-Cockpit-Mode' ), (string) $request->get_header( 'X-Tra-Vel-Cockpit-Read' ),
			(string) $request->get_header( 'X-WP-Nonce' ), (string) get_current_user_id(),
			Tra_Vel_VIP_Capability_Session_Store::session_digest( rawurldecode( $cookie ) ),
		) );
		return hash_hmac( 'sha256', $material, wp_salt( 'nonce' ) );
	}

	private function private_response( $data, $status, $view_expires_at = '' ) {
		$response = new WP_REST_Response( $data, (int) $status );
		if ( is_string( $view_expires_at ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $view_expires_at ) ) {
			$response->header( 'X-Tra-Vel-Cockpit-View-Expires', $view_expires_at );
		}
		return $this->private_headers( $response );
	}

	private function private_headers( $response ) {
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'" );
		$response->header( 'Vary', 'Origin, Cookie, X-WP-Nonce' );
		return $response;
	}

	private static function missing_session() {
		return self::error( 'session_missing', 'The secure trip link is unavailable.', 404 );
	}

	private static function error( $code, $message, $status ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_' . $code, $message, array( 'status' => (int) $status ) );
	}
}
