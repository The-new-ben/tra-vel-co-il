<?php
/**
 * Closed data contract for durable, non-transactional commercial intent.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commercial_Intent_Policy {
	const CONTRACT_VERSION       = '1.1.0';
	const SCOPE_CONTRACT_VERSION = '1.0.0';
	const EVENT_CONTRACT_VERSION = '1.0.0';
	const HANDOFF_PROVIDER       = 'tra-vel-concierge';
	const CONTACT_CONSENT_VERSION = '2026-07-19';

	/**
	 * Reject data that must never enter the commercial-intent store.
	 *
	 * @param mixed $value Request data.
	 * @param string $path Current object path.
	 * @return true|WP_Error
	 */
	public static function reject_forbidden_fields( $value, $path = 'request' ) {
		if ( ! is_array( $value ) ) {
			return true;
		}
		$forbidden = '/(?:^|_)(?:ages?|birth(?:date)?|dob|medical|health|pregnan|passport|identity|payment|paid|card|cvv|iban|bank|policy_number|ticket_number|booking(?:_id|_status)?|reservation(?:_id|_status)?|order(?:_id|_number|_status)?|transaction(?:_id|_number|_status)?|booked|reserved|accepted|confirmed|issued|email|phone|prompt|message|notes?)(?:_|$)/i';
		foreach ( $value as $key => $child ) {
			$key = (string) $key;
			if ( preg_match( $forbidden, $key ) ) {
				return new WP_Error(
					'tra_vel_commercial_sensitive_field',
					'This commercial request contains a field that is not permitted in the commercial-intent store.',
					array( 'status' => 400, 'field' => substr( $path . '.' . $key, 0, 120 ) )
				);
			}
			$nested = self::reject_forbidden_fields( $child, $path . '.' . $key );
			if ( is_wp_error( $nested ) ) {
				return $nested;
			}
		}
		return true;
	}

	/**
	 * Normalize the only fields the store is allowed to retain.
	 *
	 * Numeric prices and browser-supplied supplier claims are deliberately not
	 * persisted. They remain visible planning information on the result card and
	 * are revalidated in the personal quote.
	 *
	 * @param array $request Request payload.
	 * @return array|WP_Error
	 */
	public static function normalize_scope( $request ) {
		$request = is_array( $request ) ? $request : array();
		$safe    = self::reject_forbidden_fields( $request );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}

		$vertical = sanitize_key( isset( $request['vertical'] ) ? $request['vertical'] : '' );
		if ( ! in_array( $vertical, self::verticals(), true ) ) {
			return new WP_Error( 'tra_vel_commercial_vertical_invalid', 'Choose a supported travel product.', array( 'status' => 400 ) );
		}

		$trip      = isset( $request['trip'] ) && is_array( $request['trip'] ) ? $request['trip'] : array();
		$candidate = isset( $request['candidate'] ) && is_array( $request['candidate'] ) ? $request['candidate'] : array();
		$currency  = strtoupper( self::plain_text( isset( $trip['currency'] ) ? $trip['currency'] : 'ILS', 3 ) );
		if ( ! in_array( $currency, array( 'ILS', 'USD', 'EUR', 'GBP' ), true ) ) {
			$currency = 'ILS';
		}

		$adults   = self::bounded_int( isset( $trip['adults'] ) ? $trip['adults'] : 0, 0, 20 );
		$children = self::bounded_int( isset( $trip['children'] ) ? $trip['children'] : 0, 0, 20 );
		$infants  = self::bounded_int( isset( $trip['infants'] ) ? $trip['infants'] : 0, 0, 10 );
		$declared = self::bounded_int( isset( $trip['travelers'] ) ? $trip['travelers'] : 0, 0, 20 );
		$travelers = max( 1, min( 20, max( $declared, $adults + $children + $infants ) ) );

		$data_mode = sanitize_key( isset( $request['data_mode'] ) ? $request['data_mode'] : 'demo' );
		if ( ! in_array( $data_mode, array( 'demo', 'mixed', 'live' ), true ) ) {
			$data_mode = 'demo';
		}

		$requested_provider = sanitize_key( isset( $request['requested_provider'] ) ? $request['requested_provider'] : '' );
		if ( ! preg_match( '/^[a-z0-9_-]{2,40}$/', $requested_provider ) ) {
			$requested_provider = self::HANDOFF_PROVIDER;
		}

		$scope = array(
			// The stored scope contract is unchanged in 1.1.0; keeping its own
			// version preserves scope digests so active intents still resume.
			'contract_version'  => self::SCOPE_CONTRACT_VERSION,
			'vertical'          => $vertical,
			'surface'           => self::key( isset( $request['surface'] ) ? $request['surface'] : 'search-results', 32, 'search-results' ),
			'data_mode'         => $data_mode,
			'requested_provider'=> $requested_provider,
			// Until the server can verify an immutable supplier offer reference,
			// every intent resolves to Tra-Vel's owned assisted-quote channel.
			'resolved_provider' => self::HANDOFF_PROVIDER,
			'offer_id'          => self::identifier( isset( $request['offer_id'] ) ? $request['offer_id'] : 'search', 80, 'search' ),
			'trip'              => array(
				'origin'       => self::plain_text( isset( $trip['origin'] ) ? $trip['origin'] : 'TLV', 80 ),
				'destination'  => self::plain_text( isset( $trip['destination'] ) ? $trip['destination'] : '', 80 ),
				'depart_date'  => self::date( isset( $trip['depart_date'] ) ? $trip['depart_date'] : '' ),
				'return_date'  => self::date( isset( $trip['return_date'] ) ? $trip['return_date'] : '' ),
				'adults'       => $adults,
				'children'     => $children,
				'infants'      => $infants,
				'travelers'    => $travelers,
				'rooms'        => self::bounded_int( isset( $trip['rooms'] ) ? $trip['rooms'] : 1, 1, 10 ),
				'budget'       => self::bounded_int( isset( $trip['budget'] ) ? $trip['budget'] : 0, 0, 1000000 ),
				'currency'     => $currency,
				'return_path'  => self::return_path( isset( $trip['return_path'] ) ? $trip['return_path'] : '/' ),
			),
			'candidate'         => array(
				'id'             => self::identifier( isset( $candidate['id'] ) ? $candidate['id'] : ( isset( $request['offer_id'] ) ? $request['offer_id'] : 'search' ), 80, 'search' ),
				'title'          => self::plain_text( isset( $candidate['title'] ) ? $candidate['title'] : '', 120 ),
				'subtitle'       => self::plain_text( isset( $candidate['subtitle'] ) ? $candidate['subtitle'] : '', 180 ),
				'commercial_ref' => self::identifier( isset( $candidate['commercial_ref'] ) ? $candidate['commercial_ref'] : '', 100, '' ),
				'price_scope'    => self::key( isset( $candidate['price_scope'] ) ? $candidate['price_scope'] : 'personal_quote', 40, 'personal_quote' ),
			),
			'commercial_boundary' => array(
				'state'        => 'non_binding_planning_intent',
				'price_status' => 'final_price_and_availability_require_personal_quote',
			),
		);

		if ( '' !== $scope['trip']['depart_date'] && '' !== $scope['trip']['return_date'] && $scope['trip']['return_date'] < $scope['trip']['depart_date'] ) {
			return new WP_Error( 'tra_vel_commercial_dates_invalid', 'The return date must not be earlier than the departure date.', array( 'status' => 400 ) );
		}

		return $scope;
	}

	public static function digest( $value ) {
		return hash( 'sha256', self::canonical_json( $value ) );
	}

	/**
	 * Validate and minimize the optional consented lead-contact object.
	 *
	 * The contact object is extracted by the controller before the scope
	 * passes reject_forbidden_fields, so a phone number can exist only here,
	 * only with an explicit consent boolean and the exact current
	 * contact-consent version. Anything else fails closed with a 400. The
	 * bounded record is operator-readable storage only: public responses may
	 * expose a contact_provided boolean and nothing more.
	 *
	 * @param mixed $raw Raw traveler-supplied contact object.
	 * @return array|WP_Error Bounded contact record, empty array when absent.
	 */
	public static function sanitize_contact( $raw ) {
		if ( ! is_array( $raw ) || array() === $raw ) {
			return array();
		}
		if ( true !== filter_var( isset( $raw['consent'] ) ? $raw['consent'] : false, FILTER_VALIDATE_BOOLEAN ) || self::CONTACT_CONSENT_VERSION !== (string) ( isset( $raw['consent_version'] ) ? $raw['consent_version'] : '' ) ) {
			return new WP_Error( 'tra_vel_commercial_contact_consent_required', 'Explicit contact consent with the current contact-consent version is required.', array( 'status' => 400 ) );
		}
		$phone = self::normalize_phone( isset( $raw['phone'] ) ? $raw['phone'] : '' );
		if ( '' === $phone ) {
			return new WP_Error( 'tra_vel_commercial_contact_phone_invalid', 'A valid callback phone number is required for consented contact.', array( 'status' => 400 ) );
		}
		$name    = self::plain_text( isset( $raw['name'] ) ? $raw['name'] : '', 80 );
		$contact = array(
			'phone'           => $phone,
			'consent_version' => self::CONTACT_CONSENT_VERSION,
			'consented_at'    => gmdate( 'c' ),
		);
		if ( '' !== $name ) {
			$contact['name'] = $name;
		}
		return $contact;
	}

	/**
	 * Normalize a traveler-entered phone number to digits with an optional
	 * leading plus. Input may use +, digits, spaces, and dashes; the stored
	 * value keeps 7 to 15 digits so Israeli and international callback
	 * numbers both fit without retaining free-form text.
	 *
	 * @param mixed $raw Raw phone input.
	 * @return string Normalized phone, or empty string when invalid.
	 */
	public static function normalize_phone( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || strlen( $raw ) > 32 || ! preg_match( '/^\+?[0-9][0-9 \-]*$/', $raw ) ) {
			return '';
		}
		$normalized = ( '+' === $raw[0] ? '+' : '' ) . preg_replace( '/[^0-9]/', '', $raw );
		$digits     = ltrim( $normalized, '+' );
		$length     = strlen( $digits );
		return $length >= 7 && $length <= 15 ? $normalized : '';
	}

	public static function canonical_json( $value ) {
		$value = self::canonicalize( $value );
		return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	public static function verticals() {
		return array( 'flight', 'hotel', 'package', 'insurance', 'car', 'transfer', 'activity', 'esim' );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function bounded_int( $value, $minimum, $maximum ) {
		return max( (int) $minimum, min( (int) $maximum, absint( $value ) ) );
	}

	private static function plain_text( $value, $maximum ) {
		return function_exists( 'mb_substr' )
			? mb_substr( sanitize_text_field( (string) $value ), 0, (int) $maximum )
			: substr( sanitize_text_field( (string) $value ), 0, (int) $maximum );
	}

	private static function identifier( $value, $maximum, $fallback ) {
		$value = substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $value ), 0, (int) $maximum );
		return '' !== $value ? $value : (string) $fallback;
	}

	private static function key( $value, $maximum, $fallback ) {
		$value = substr( sanitize_key( (string) $value ), 0, (int) $maximum );
		return '' !== $value ? $value : (string) $fallback;
	}

	private static function date( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return '';
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';
	}

	private static function return_path( $value ) {
		$value = '/' . ltrim( sanitize_text_field( (string) $value ), '/' );
		return substr( preg_replace( '/[^A-Za-z0-9_\-\/.?=&%]/', '', $value ), 0, 200 );
	}
}
