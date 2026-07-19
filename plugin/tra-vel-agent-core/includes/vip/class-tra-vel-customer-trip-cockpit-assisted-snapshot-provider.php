<?php
/**
 * Truthful assisted-planning snapshot source for the customer Trip Cockpit.
 *
 * This provider projects only durable facts the assisted-quote stores already
 * hold: the account owner's quote cases and their published assisted
 * proposals. It fabricates nothing: no bookings, no flights, no payments, no
 * supplier confirmations, and no traveler readiness it cannot verify. Each
 * quote case becomes one planning-phase cockpit trip whose single package
 * service carries the true assisted status and published-proposal history.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Customer_Trip_Cockpit_Assisted_Snapshot_Provider {
	const EVENT_KIND          = 'assisted_case_changed';
	const MAX_PROJECTED_CASES = 20;
	const MAX_PROPOSAL_EVENTS = 12;

	/** @var Tra_Vel_Quote_Case_Store|null Injectable only for deterministic server tests. */
	private $case_store;

	/** @var Tra_Vel_Assisted_Proposal_Store|null Injectable only for deterministic server tests. */
	private $proposal_store;

	public function __construct( $case_store = null, $proposal_store = null ) {
		$this->case_store     = $case_store ? $case_store : ( class_exists( 'Tra_Vel_Quote_Case_Store' ) ? new Tra_Vel_Quote_Case_Store() : null );
		$this->proposal_store = $proposal_store ? $proposal_store : ( class_exists( 'Tra_Vel_Assisted_Proposal_Store' ) ? new Tra_Vel_Assisted_Proposal_Store() : null );
	}

	/** Register the snapshot join callback and the post-commit refresh relays. */
	public function register_hooks() {
		add_filter( Tra_Vel_Customer_Trip_Cockpit_Authoritative_Source_Provider::SNAPSHOT_FILTER, array( $this, 'provide_snapshot' ), 10, 2 );
		add_action( 'tra_vel_quote_case_created', array( $this, 'relay_case_created' ), 20, 3 );
		add_action( 'tra_vel_assisted_proposal_published', array( $this, 'relay_proposal_published' ), 20, 3 );
		add_action( 'tra_vel_quote_case_traveler_action', array( $this, 'relay_traveler_action' ), 20, 3 );
	}

	/**
	 * Stable opaque cockpit trip reference for one assisted quote case.
	 *
	 * @param string $case_uuid Quote-case UUID.
	 * @return string
	 */
	public static function assisted_trip_ref( $case_uuid ) {
		$case_uuid = strtolower( trim( (string) $case_uuid ) );
		if ( '' === $case_uuid ) {
			return '';
		}
		return 'tv_trip_' . substr(
			hash_hmac( 'sha256', 'assisted-quote-trip|' . get_current_blog_id() . '|' . $case_uuid, wp_salt( 'auth' ) ),
			0,
			32
		);
	}

	/** @param string $case_id Quote-case UUID announced after commit. */
	public function relay_case_created( $case_id, $reference = '', $context = array() ) {
		unset( $reference, $context );
		$this->relay_refresh( $case_id, 'created:' . strtolower( (string) $case_id ) );
	}

	/** @param string $case_id Quote-case UUID announced after commit. */
	public function relay_proposal_published( $case_id, $proposal_id = '', $revision = 0 ) {
		$this->relay_refresh( $case_id, 'published:' . strtolower( (string) $proposal_id ) . ':' . absint( $revision ) );
	}

	/** @param string $case_id Quote-case UUID announced after commit. */
	public function relay_traveler_action( $case_id, $proposal_id = '', $action = '' ) {
		$this->relay_refresh( $case_id, 'action:' . strtolower( (string) $proposal_id ) . ':' . sanitize_key( (string) $action ) );
	}

	/**
	 * Turn one committed assisted-quote milestone into a payload-free cockpit
	 * lifecycle event. Guest-owned cases have no account cockpit and are
	 * skipped truthfully rather than bound to a fabricated owner.
	 *
	 * @param string $case_id    Quote-case UUID.
	 * @param string $event_seed Deterministic per-milestone seed.
	 * @return void
	 */
	private function relay_refresh( $case_id, $event_seed ) {
		if ( ! $this->case_store instanceof Tra_Vel_Quote_Case_Store || ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			return;
		}
		$case = $this->case_store->get_case_by_uuid( $case_id );
		if ( ! is_array( $case ) || absint( $case['owner_user_id'] ?? 0 ) < 1 ) {
			return;
		}
		$trip_ref = self::assisted_trip_ref( (string) $case['case_uuid'] );
		if ( '' === $trip_ref ) {
			return;
		}
		$event_ref = 'tv_lifecycle_event_' . substr(
			hash_hmac( 'sha256', 'assisted-case|' . $event_seed, wp_salt( 'secure_auth' ) ),
			0,
			32
		);
		do_action(
			Tra_Vel_Customer_Trip_Cockpit_Source_Assembler::LIFECYCLE_ACTION,
			absint( $case['owner_user_id'] ),
			$trip_ref,
			self::EVENT_KIND,
			$event_ref
		);
	}

	/**
	 * Supply the closed component snapshot for one owner-bound assisted trip.
	 *
	 * An explicitly installed upstream snapshot always wins. Returning null is
	 * the truthful "no assisted trip exists for this reference" answer; every
	 * storage uncertainty fails closed with WP_Error so a stale-but-true prior
	 * projection is never replaced by a guess.
	 *
	 * @param array|WP_Error|null $snapshot Earlier filter result.
	 * @param array               $context  Closed server lifecycle context.
	 * @return array|WP_Error|null
	 */
	public function provide_snapshot( $snapshot, $context ) {
		if ( null !== $snapshot ) {
			return $snapshot;
		}
		if ( ! is_array( $context ) || absint( $context['owner_user_id'] ?? 0 ) < 1 || ! Tra_Vel_Traveler_Principal::valid_ref( $context['trip_ref'] ?? '', 'trip' ) ) {
			return self::error( 'context_invalid', 'The assisted snapshot context is invalid.', 400 );
		}
		if ( ! $this->case_store instanceof Tra_Vel_Quote_Case_Store || ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			return self::error( 'case_store_unavailable', 'Assisted quote storage is not ready for cockpit projection.', 503 );
		}

		$read_error = '';
		$cases      = $this->case_store->list_owned( absint( $context['owner_user_id'] ), '', self::MAX_PROJECTED_CASES, $read_error );
		if ( '' !== $read_error ) {
			return self::error( 'case_read_failed', 'Assisted quote cases could not be read safely for cockpit projection.', 503 );
		}
		foreach ( is_array( $cases ) ? $cases : array() as $case ) {
			if ( ! is_array( $case ) || empty( $case['case_uuid'] ) ) {
				continue;
			}
			$trip_ref = self::assisted_trip_ref( (string) $case['case_uuid'] );
			if ( '' !== $trip_ref && hash_equals( $trip_ref, (string) $context['trip_ref'] ) ) {
				return $this->build_snapshot( $case );
			}
		}
		return null;
	}

	/**
	 * Project one quote case and its published proposals into the exact closed
	 * snapshot contract validated by the cockpit policy.
	 *
	 * @param array $case Hydrated owned quote case.
	 * @return array|WP_Error
	 */
	private function build_snapshot( $case ) {
		$status     = sanitize_key( (string) ( $case['status'] ?? '' ) );
		$reference  = strtoupper( (string) ( $case['reference_code'] ?? '' ) );
		$created_at = self::iso_utc( (string) ( $case['created_at'] ?? '' ) );
		$updated_at = self::iso_utc( (string) ( $case['updated_at'] ?? '' ) );
		if ( '' === $created_at || '' === $updated_at || 1 !== preg_match( '/^TV-[A-Z0-9]{8}$/', $reference ) ) {
			return self::error( 'case_incomplete', 'The assisted quote case cannot be projected safely.', 503 );
		}

		$proposals = $this->published_proposal_heads( $case );
		if ( is_wp_error( $proposals ) ) {
			return $proposals;
		}

		$observed = $updated_at;
		foreach ( $proposals as $head ) {
			if ( strcmp( $head['observed_at'], $observed ) > 0 ) {
				$observed = $head['observed_at'];
			}
		}

		$case_uuid    = strtolower( (string) $case['case_uuid'] );
		$case_version = absint( $case['case_version'] ?? 1 );
		$service_ref  = 'tv_service_' . self::hmac32( 'assisted-service|' . $case_uuid );
		$traveler_ref = 'tv_traveler_' . self::hmac32( 'assisted-traveler|' . $case_uuid );
		$is_terminal  = in_array( $status, array( 'closed_no_quote', 'cancelled', 'expired' ), true );

		$events = array(
			array(
				'event_ref'   => 'tv_timeline_event_' . self::hmac32( 'assisted-created|' . $case_uuid ),
				'event_code'  => 'assisted_request_received',
				'state'       => 'queued',
				'truth_state' => 'verified_current',
				'occurred_at' => $created_at,
			),
		);
		if ( $case_version > 1 || 'queued' !== $status ) {
			$events[] = array(
				'event_ref'   => 'tv_timeline_event_' . self::hmac32( 'assisted-status|' . $case_uuid . '|' . $case_version ),
				'event_code'  => 'assisted_status_changed',
				'state'       => $status,
				'truth_state' => 'verified_current',
				'occurred_at' => $updated_at,
			);
		}
		foreach ( $proposals as $head ) {
			$events[] = array(
				'event_ref'   => 'tv_timeline_event_' . self::hmac32( 'assisted-proposal|' . $head['proposal_uuid'] . '|' . $head['published_revision'] ),
				'event_code'  => 'assisted_proposal_published',
				'state'       => $head['status'],
				'truth_state' => 'verified_current',
				'occurred_at' => $head['observed_at'],
			);
		}

		$trip_action = null;
		if ( 'needs_information' === $status ) {
			$trip_action = self::action( 'provide_missing_information', 'high', array( $service_ref ), array() );
		} elseif ( 'ready_for_assistance' === $status ) {
			$trip_action = self::action( 'open_assisted_contact', 'high', array( $service_ref ), array() );
		}

		return array(
			'headline'           => 'בקשת תכנון מלווה ' . $reference,
			'registration'       => array(
				'gate'                      => 'ready_to_quote',
				'readiness'                 => 'ready',
				'pending_requirement_codes' => array(),
				'next_action'               => null,
				'verified_at'               => $observed,
			),
			'trip_health'        => array(
				'phase'                     => 'planning',
				'health'                    => $this->health_state( $status, $is_terminal ),
				'dependency_projection_ref' => null,
				'recovery_projection_ref'   => null,
				'affected_service_refs'     => array( $service_ref ),
				'unaffected_service_refs'   => array(),
				'next_action'               => $trip_action,
				'verified_at'               => $observed,
			),
			'services'           => array(
				array(
					'service_ref'     => $service_ref,
					'sequence'        => 1,
					'vertical'        => 'package',
					'label'           => $this->service_label( $case ),
					'phase'           => $is_terminal ? 'cancelled' : 'planned',
					'health'          => $this->health_state( $status, $is_terminal ),
					'fulfillment'     => array(
						'state'       => 'selected',
						'truth_state' => 'verified_current',
					),
					'change_state'    => 'unchanged',
					'protected_codes' => array(),
					'next_action'     => null,
					'events'          => $events,
					'verified_at'     => $observed,
				),
			),
			'protections'        => array(),
			'changes'            => array(
				array(
					'change_ref'            => 'tv_change_' . self::hmac32( 'assisted-change|' . $case_uuid . '|' . $case_version . '|' . $observed ),
					'change_code'           => $case_version > 1 ? 'assisted_request_updated' : 'assisted_request_received',
					'affected_service_refs' => array( $service_ref ),
					'truth_state'           => 'verified_current',
					'observed_at'           => $observed,
				),
			),
			'approvals'          => array(),
			'questions'          => array(),
			'vip_cases'          => array(),
			'trip_care_receipts' => array(),
			'commerce_orders'    => array(),
			'loyalty'            => array(
				'status'                => 'not_requested',
				'affected_service_refs' => array(),
				'next_action'           => null,
				'verified_at'           => $observed,
			),
			'traveler_readiness' => array(
				array(
					'traveler_ref'              => $traveler_ref,
					'subject_kind'              => 'adult',
					'readiness'                 => 'unknown',
					'pending_requirement_codes' => array( $is_terminal ? 'assisted_request_closed' : 'traveler_profile_pending' ),
					'next_action'               => self::action(
						$is_terminal ? 'start_new_private_plan' : 'complete_traveler_profile',
						'low',
						array( $service_ref ),
						array( $traveler_ref )
					),
					'deadline'                  => null,
					'verified_at'               => $observed,
				),
			),
			'offline_pack'       => array(
				'status'             => 'not_requested',
				'itinerary'          => 'not_applicable',
				'service_contacts'   => 'not_applicable',
				'emergency_contacts' => 'not_applicable',
				'next_action'        => null,
				'verified_at'        => $observed,
			),
		);
	}

	/**
	 * Read this case's published proposal heads with fail-closed uncertainty.
	 *
	 * @param array $case Hydrated quote case.
	 * @return array|WP_Error Normalized bounded head projections.
	 */
	private function published_proposal_heads( $case ) {
		if ( ! $this->proposal_store instanceof Tra_Vel_Assisted_Proposal_Store || ! method_exists( 'Tra_Vel_Assisted_Proposal_Store', 'is_ready' ) || ! Tra_Vel_Assisted_Proposal_Store::is_ready() ) {
			return self::error( 'proposal_store_unavailable', 'Assisted proposal storage is not ready for cockpit projection.', 503 );
		}
		$heads = $this->proposal_store->list_by_case( absint( $case['id'] ?? 0 ), (string) ( $case['case_uuid'] ?? '' ), self::MAX_PROPOSAL_EVENTS );
		if ( is_wp_error( $heads ) ) {
			return $heads;
		}
		$published = array();
		foreach ( is_array( $heads ) ? $heads : array() as $head ) {
			if ( ! is_array( $head ) || absint( $head['published_revision'] ?? 0 ) < 1 ) {
				continue;
			}
			$observed = self::iso_utc( (string) ( $head['updated_at'] ?? '' ) );
			$status   = sanitize_key( (string) ( $head['status'] ?? '' ) );
			if ( '' === $observed || '' === $status || empty( $head['proposal_uuid'] ) ) {
				return self::error( 'proposal_incomplete', 'A published proposal cannot be projected safely.', 503 );
			}
			$published[] = array(
				'proposal_uuid'      => strtolower( (string) $head['proposal_uuid'] ),
				'published_revision' => absint( $head['published_revision'] ),
				'status'             => $status,
				'observed_at'        => $observed,
			);
			if ( count( $published ) >= self::MAX_PROPOSAL_EVENTS ) {
				break;
			}
		}
		usort(
			$published,
			static function ( $left, $right ) {
				$order = strcmp( $left['observed_at'], $right['observed_at'] );
				return 0 !== $order ? $order : strcmp( $left['proposal_uuid'], $right['proposal_uuid'] );
			}
		);
		return $published;
	}

	/**
	 * Map the durable assisted status onto the closed cockpit health axis.
	 *
	 * @param string $status      Quote-case status.
	 * @param bool   $is_terminal Whether the case reached a terminal outcome.
	 * @return string
	 */
	private function health_state( $status, $is_terminal ) {
		if ( $is_terminal ) {
			return 'completed_with_issue';
		}
		return in_array( $status, array( 'needs_information', 'ready_for_assistance' ), true ) ? 'action_required' : 'on_track';
	}

	/**
	 * Customer-safe service label from the already-minimized route summary.
	 *
	 * @param array $case Hydrated quote case.
	 * @return string
	 */
	private function service_label( $case ) {
		$title = '';
		if ( class_exists( 'Tra_Vel_Quote_Case_Policy' ) && method_exists( 'Tra_Vel_Quote_Case_Policy', 'public_summary' ) ) {
			$summary = Tra_Vel_Quote_Case_Policy::public_summary( is_array( $case['snapshot'] ?? null ) ? $case['snapshot'] : array() );
			$title   = (string) ( $summary['title'] ?? '' );
		}
		$title = trim( str_replace( array( '<', '>', "\r", "\n" ), ' ', sanitize_text_field( $title ) ) );
		$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 100 ) : substr( $title, 0, 100 );
		return '' !== trim( $title ) ? $title : 'בקשת נסיעה';
	}

	private static function action( $code, $priority, $service_refs, $traveler_refs ) {
		return array(
			'code'          => $code,
			'priority'      => $priority,
			'service_refs'  => $service_refs,
			'traveler_refs' => $traveler_refs,
			'deadline'      => null,
			'truth_state'   => 'verified_current',
		);
	}

	private static function hmac32( $seed ) {
		return substr( hash_hmac( 'sha256', (string) $seed, wp_salt( 'auth' ) ), 0, 32 );
	}

	/** Convert one stored UTC MySQL time to the policy clock format. */
	private static function iso_utc( $mysql_datetime ) {
		$mysql_datetime = trim( (string) $mysql_datetime );
		if ( '' === $mysql_datetime ) {
			return '';
		}
		$timestamp = strtotime( $mysql_datetime . ( 1 === preg_match( '/(?:Z|[+-][0-9]{2}:?[0-9]{2})$/', $mysql_datetime ) ? '' : ' UTC' ) );
		if ( false === $timestamp || $timestamp < 1 || $timestamp > time() ) {
			return '';
		}
		return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	private static function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_customer_trip_cockpit_assisted_snapshot_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
