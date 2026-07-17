<?php
/**
 * Truthful assisted-quote state and snapshot policy.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Quote_Case_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const CONSENT_VERSION  = '2026-07-17';

	/**
	 * States deliberately stop before supplier search, proposal, or booking.
	 * Those capabilities remain disabled until a separately evidenced adapter
	 * and execution ledger exist.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance', 'closed_no_quote', 'cancelled', 'expired' );
	}

	/**
	 * Operator-controlled transitions. Traveler cancellation is handled by a
	 * separate policy method so operator and owner authority never blur.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		return array(
			'queued'                => array( 'in_review', 'needs_information', 'closed_no_quote' ),
			'in_review'             => array( 'needs_information', 'ready_for_assistance', 'closed_no_quote' ),
			'needs_information'     => array( 'queued', 'in_review', 'closed_no_quote' ),
			'ready_for_assistance'  => array( 'in_review', 'needs_information', 'closed_no_quote' ),
			'closed_no_quote'       => array(),
			'cancelled'             => array(),
			'expired'               => array(),
		);
	}

	public static function can_transition( $from, $to ) {
		$transitions = self::transitions();
		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}

	public static function can_cancel( $status ) {
		return in_array( $status, array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' ), true );
	}

	/**
	 * Validate that a run is safe to promote into a durable sales case.
	 *
	 * @param array $run Hydrated AgentRun database row.
	 * @return true|WP_Error
	 */
	public static function validate_ready_run( $run ) {
		if ( ! is_array( $run ) || 'request_ready' !== ( $run['status'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_quote_case_run_not_ready', 'The private plan must be complete before an assisted quote can be requested.', array( 'status' => 409 ) );
		}
		$request = isset( $run['trip_request'] ) && is_array( $run['trip_request'] ) ? $run['trip_request'] : array();
		if ( 'ready_for_search' !== ( $request['readiness']['status'] ?? '' ) || ! empty( $request['readiness']['blockers'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_request_blocked', 'The structured trip request still has material blockers.', array( 'status' => 409 ) );
		}
		if ( empty( $request['request_id'] ) || empty( $request['revision'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_request_invalid', 'The structured trip request is incomplete.', array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Freeze only bounded, typed planning fields needed by an assisted quote.
	 * Model-written summaries, vibes, preferences, constraints, questions,
	 * assumptions, raw prompts and provider traces are deliberately excluded so
	 * a model cannot echo contact, medical, passport or payment data into the
	 * 90-day aggregate. A later requirements taxonomy must be allowlisted before
	 * any constraint is added here.
	 *
	 * @param array $request Structured TripRequest.
	 * @return array
	 */
	public static function snapshot( $request ) {
		$destinations = self::planning_text_list( $request['destinations'] ?? array(), 8, 80 );
		$origin       = self::planning_text( $request['origin_text'] ?? '', 120 );
		$date_text    = self::planning_text( $request['date_text'] ?? '', 120 );
		$travelers    = is_array( $request['travelers'] ?? null ) ? $request['travelers'] : array();
		$budget       = is_array( $request['budget'] ?? null ) ? $request['budget'] : array();
		$readiness    = is_array( $request['readiness'] ?? null ) ? $request['readiness'] : array();
		$child_ages   = array_values(
			array_slice(
				array_filter(
					array_map( 'absint', (array) ( $travelers['child_ages'] ?? array() ) ),
					static function ( $age ) {
						return $age <= 17;
					}
				),
				0,
				20
			)
		);
		$amount = isset( $budget['amount'] ) && is_numeric( $budget['amount'] )
			? min( 1000000000, max( 0, (float) $budget['amount'] ) )
			: null;

		return array(
			'contract_version' => '1.0.0',
			'request_id'       => self::bounded_text( $request['request_id'] ?? '', 36 ),
			'revision'         => max( 1, absint( $request['revision'] ?? 1 ) ),
			'summary'          => self::derived_summary( $origin, $destinations, $date_text ),
			'language'         => self::enum( $request['language'] ?? '', array( 'he', 'en', 'mixed' ), 'he' ),
			'origin_text'      => $origin,
			'destination_mode' => self::enum( $request['destination_mode'] ?? '', array( 'fixed', 'anywhere', 'flexible', 'unknown' ), 'unknown' ),
			'destinations'     => $destinations,
			'date_text'        => $date_text,
			'date_flexibility' => self::enum( $request['date_flexibility'] ?? '', array( 'exact', 'flexible', 'unknown' ), 'unknown' ),
			'travelers'        => array(
				'adults'     => min( 20, absint( $travelers['adults'] ?? 0 ) ),
				'children'   => min( 20, absint( $travelers['children'] ?? 0 ) ),
				'child_ages' => $child_ages,
				'rooms'      => min( 20, absint( $travelers['rooms'] ?? 0 ) ),
			),
			'budget'           => array(
				'amount'      => $amount,
				'currency'    => self::enum( $budget['currency'] ?? '', array( 'ILS', 'USD', 'EUR' ), 'UNKNOWN' ),
				'flexibility' => self::enum( $budget['flexibility'] ?? '', array( 'hard', 'soft', 'unknown' ), 'unknown' ),
			),
			'search_scope'     => array_values( array_intersect( array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' ), array_map( 'sanitize_key', (array) ( $request['search_scope'] ?? array() ) ) ) ),
			'readiness'        => array(
				'status'        => self::enum( $readiness['status'] ?? '', array( 'needs_clarification', 'ready_for_search', 'unsupported' ), 'unsupported' ),
				'blocker_count' => min( 20, count( (array) ( $readiness['blockers'] ?? array() ) ) ),
			),
		);
	}

	public static function digest( $snapshot ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $snapshot ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Stable public summary; never includes hard constraints or free-form text.
	 *
	 * @param array $snapshot Minimized request snapshot.
	 * @return array
	 */
	public static function public_summary( $snapshot ) {
		$travelers = isset( $snapshot['travelers'] ) && is_array( $snapshot['travelers'] ) ? $snapshot['travelers'] : array();
		$budget    = isset( $snapshot['budget'] ) && is_array( $snapshot['budget'] ) ? $snapshot['budget'] : array();
		$child_ages = array_values(
			array_slice(
				array_filter(
					array_map( 'absint', (array) ( $travelers['child_ages'] ?? array() ) ),
					static function ( $age ) {
						return $age <= 17;
					}
				),
				0,
				20
			)
		);
		$amount = isset( $budget['amount'] ) && is_numeric( $budget['amount'] )
			? min( 1000000000, max( 0, (float) $budget['amount'] ) )
			: null;
		return array(
			'title'            => self::bounded_text( $snapshot['summary'] ?? '', 500 ),
			'language'         => self::enum( $snapshot['language'] ?? '', array( 'he', 'en', 'mixed' ), 'he' ),
			'origin'           => self::planning_text( $snapshot['origin_text'] ?? '', 120 ),
			'destination_mode' => self::enum( $snapshot['destination_mode'] ?? '', array( 'fixed', 'anywhere', 'flexible', 'unknown' ), 'unknown' ),
			'destinations'     => self::planning_text_list( $snapshot['destinations'] ?? array(), 8, 80 ),
			'date_text'        => self::planning_text( $snapshot['date_text'] ?? '', 120 ),
			'date_flexibility' => self::enum( $snapshot['date_flexibility'] ?? '', array( 'exact', 'flexible', 'unknown' ), 'unknown' ),
			'travelers'        => array(
				'adults'     => min( 20, absint( $travelers['adults'] ?? 0 ) ),
				'children'   => min( 20, absint( $travelers['children'] ?? 0 ) ),
				'child_ages' => $child_ages,
				'rooms'      => min( 20, absint( $travelers['rooms'] ?? 0 ) ),
			),
			'budget'           => array(
				'amount'      => $amount,
				'currency'    => in_array( $budget['currency'] ?? '', array( 'ILS', 'USD', 'EUR' ), true ) ? $budget['currency'] : 'UNKNOWN',
				'flexibility' => self::enum( $budget['flexibility'] ?? '', array( 'hard', 'soft', 'unknown' ), 'unknown' ),
			),
			'scope'            => array_values( array_intersect( array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' ), (array) ( $snapshot['search_scope'] ?? array() ) ) ),
		);
	}

	public static function status_label( $status ) {
		$labels = array(
			'queued'               => 'הבקשה התקבלה',
			'in_review'            => 'בבדיקה אנושית',
			'needs_information'    => 'נדרש מידע נוסף',
			'ready_for_assistance' => 'מוכן להמשך עם נציג Tra-Vel',
			'closed_no_quote'      => 'נסגר ללא הצעה',
			'cancelled'            => 'בוטל',
			'expired'              => 'פג תוקף הבקשה',
		);
		return $labels[ $status ] ?? 'המצב אינו זמין';
	}

	public static function next_action( $status ) {
		$actions = array(
			'queued'               => array( 'actor' => 'tra-vel', 'code' => 'review_request', 'label' => 'צוות Tra-Vel יבדוק את בקשת הנסיעה המובנית.' ),
			'in_review'            => array( 'actor' => 'tra-vel', 'code' => 'complete_review', 'label' => 'נציג אנושי בודק מה ניתן לקדם ומה דורש אימות.' ),
			'needs_information'    => array( 'actor' => 'traveler', 'code' => 'provide_information', 'label' => 'המשיכו בשיחה הפרטית והשלימו את הפרט החסר.' ),
			'ready_for_assistance' => array( 'actor' => 'traveler', 'code' => 'open_assisted_contact', 'label' => 'אפשר לפתוח שיחה מזוהה עם צוות Tra-Vel.' ),
			'closed_no_quote'      => array( 'actor' => 'none', 'code' => 'none', 'label' => 'אין פעולה נוספת שממתינה כרגע.' ),
			'cancelled'            => array( 'actor' => 'none', 'code' => 'none', 'label' => 'הבקשה אינה פעילה עוד.' ),
			'expired'              => array( 'actor' => 'traveler', 'code' => 'start_new', 'label' => 'פתחו תוכנית פרטית חדשה כדי לבקש סיוע עדכני.' ),
		);
		return $actions[ $status ] ?? array( 'actor' => 'none', 'code' => 'none', 'label' => 'אין פעולה זמינה.' );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function bounded_text( $value, $length ) {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function planning_text_list( $values, $limit, $length ) {
		$result = array();
		foreach ( (array) $values as $value ) {
			$value = self::planning_text( $value, $length );
			if ( '' !== $value ) {
				$result[] = $value;
			}
			if ( count( $result ) >= $limit ) {
				break;
			}
		}
		return array_values( array_unique( $result ) );
	}

	private static function planning_text( $value, $length ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value || self::contains_sensitive_pattern( $value ) ) {
			return '';
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function contains_sensitive_pattern( $value ) {
		$patterns = array(
			'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
			'/https?:\/\/\S+/iu',
			'/(?:\+?\d[\s().\-]*){9,}/u',
			'/\b[A-Z]{1,2}\d{7,9}\b/iu',
			'/(?:passport|national\s+id|identity\s+card|דרכון|תעודת\s*זהות)\s*[:#\-]?\s*[A-Z0-9\-]{4,}/iu',
			'/(?:diagnosis|medical|medication|prescription|allergy|health\s+condition|אבחנה|מידע\s+רפואי|תרופה|מרשם|אלרגי(?:ה|ות)?|מחלה)/iu',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}
		return false;
	}

	private static function enum( $value, $allowed, $fallback ) {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function derived_summary( $origin, $destinations, $date_text ) {
		$route = array_values( array_filter( array( $origin, implode( ', ', $destinations ) ) ) );
		$parts = array();
		if ( $route ) {
			$parts[] = implode( ' → ', $route );
		}
		if ( $date_text ) {
			$parts[] = $date_text;
		}
		return $parts ? self::bounded_text( implode( ' · ', $parts ), 500 ) : 'בקשת נסיעה';
	}
}
