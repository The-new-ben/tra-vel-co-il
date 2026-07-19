<?php
/**
 * Server-owned composition of sourced assisted proposals.
 *
 * The operator supplies bounded traveler-facing facts and real evidence
 * references. Identity, evidence/source digests, ledger totals, lifecycle
 * fields and mandatory disclosure gaps are always derived by the server.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Assisted_Proposal_Composer {
	const COMMAND_VERSION        = '1.1.0';
	const MAX_FRESHNESS_MINUTES = 10080;
	const MAX_REVISIONS          = 20;
	const EVIDENCE_ATTESTATION_VERSION = '1.0.0';
	const EVIDENCE_ATTESTATION_TTL     = 300;

	/**
	 * Compose a new, publishable proposal for the current immutable case.
	 *
	 * @param array    $input    Closed operator composition input.
	 * @param array    $case     Verified quote-case row.
	 * @param int|null $now      Optional UTC epoch for deterministic tests.
	 * @param array    $identity Optional server/test identities.
	 * @return array|WP_Error
	 */
	public static function compose( $input, $case, $now = null, $identity = array() ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_array( $input ) || ! is_array( $case ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_invalid', 'A typed proposal composition and verified quote case are required.', array( 'status' => 400 ) );
		}
		$closed = self::require_keys(
			$input,
			array( 'position', 'title', 'summary', 'why_it_fits', 'trade_offs', 'route', 'itinerary', 'components', 'sources', 'unresolved_items', 'evidence_attestation_token' ),
			'$'
		);
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}

		$case_uuid     = strtolower( (string) ( $case['case_uuid'] ?? '' ) );
		$case_revision = (int) ( $case['current_revision'] ?? 0 );
		$request_digest = strtolower( (string) ( $case['latest_request_digest'] ?? '' ) );
		if ( ! self::is_uuid( $case_uuid ) || $case_revision < 1 || ! self::is_sha256( $request_digest ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_case_invalid', 'The quote case cannot be bound to a proposal safely.', array( 'status' => 409 ) );
		}

		$position = sanitize_key( (string) $input['position'] );
		if ( ! in_array( $position, Tra_Vel_Assisted_Proposal_Policy::positions(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_position_invalid', 'Choose a supported proposal position.', array( 'status' => 400 ) );
		}
		$identity_position = sanitize_key( (string) ( $identity['position'] ?? '' ) );
		if ( '' !== $identity_position && $identity_position !== $position ) {
			return new WP_Error( 'tra_vel_assisted_composition_identity_changed', 'A proposal revision cannot change its immutable position.', array( 'status' => 409 ) );
		}
		$revision = isset( $identity['revision'] ) ? (int) $identity['revision'] : 1;
		$version  = isset( $identity['version'] ) ? (int) $identity['version'] : 1;
		if ( $revision < 1 || $revision > self::MAX_REVISIONS ) {
			return new WP_Error( 'tra_vel_assisted_composition_revision_invalid', 'The proposed immutable revision is outside its bounded sequence.', array( 'status' => 409 ) );
		}
		if ( $version < $revision ) {
			return new WP_Error( 'tra_vel_assisted_composition_version_invalid', 'The proposal state version cannot trail its immutable commercial revision.', array( 'status' => 409 ) );
		}
		$evidence_checked_at = (int) ( $identity['evidence_checked_at'] ?? 0 );
		if ( $evidence_checked_at < 1 || $evidence_checked_at > $now + 30 || $evidence_checked_at + self::EVIDENCE_ATTESTATION_TTL < $now ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_required', 'Record a fresh server-signed evidence attestation before publication.', array( 'status' => 409 ) );
		}

		$title   = self::text( $input['title'], 160, 'title' );
		$summary = self::text( $input['summary'], 800, 'summary' );
		if ( is_wp_error( $title ) || is_wp_error( $summary ) ) {
			return is_wp_error( $title ) ? $title : $summary;
		}
		$why_it_fits = self::text_list( $input['why_it_fits'], 1, 6, 240, 'why_it_fits' );
		$trade_offs  = self::text_list( $input['trade_offs'], 1, 6, 240, 'trade_offs' );
		if ( is_wp_error( $why_it_fits ) || is_wp_error( $trade_offs ) ) {
			return is_wp_error( $why_it_fits ) ? $why_it_fits : $trade_offs;
		}

		$route = self::route( $input['route'] );
		if ( is_wp_error( $route ) ) {
			return $route;
		}

		$source_result = self::sources( $input['sources'], $evidence_checked_at, $identity );
		if ( is_wp_error( $source_result ) ) {
			return $source_result;
		}
		$sources        = $source_result['sources'];
		$source_ids     = $source_result['source_ids'];
		$latest_observed = $source_result['latest_observed'];
		$earliest_expiry = $source_result['earliest_expiry'];

		$retention = self::datetime( $case['retention_until'] ?? '' );
		if ( $retention < 1 || $earliest_expiry >= $retention ) {
			return new WP_Error( 'tra_vel_assisted_composition_retention_conflict', 'The evidence freshness window must end before quote-case retention.', array( 'status' => 409 ) );
		}

		$component_result = self::components( $input['components'], $source_ids );
		if ( is_wp_error( $component_result ) ) {
			return $component_result;
		}
		$components     = $component_result['components'];
		$component_keys = $component_result['keys'];

		$itinerary = self::itinerary( $input['itinerary'], $component_keys );
		if ( is_wp_error( $itinerary ) ) {
			return $itinerary;
		}

		$unresolved = self::unresolved_items( $input['unresolved_items'], $components );
		if ( is_wp_error( $unresolved ) ) {
			return $unresolved;
		}

		$ledger = Tra_Vel_Assisted_Proposal_Policy::compute_ledger( $components );
		if ( is_wp_error( $ledger ) ) {
			return $ledger;
		}

		$proposal_uuid = self::identity_uuid( $identity['proposal_id'] ?? '' );
		if ( is_wp_error( $proposal_uuid ) ) {
			return $proposal_uuid;
		}
		$reference = self::reference( $identity['reference'] ?? '', $proposal_uuid );
		if ( is_wp_error( $reference ) ) {
			return $reference;
		}

		$proposal = array(
			'contract_version'      => Tra_Vel_Assisted_Proposal_Policy::CONTRACT_VERSION,
			'proposal_id'           => $proposal_uuid,
			'case_id'               => $case_uuid,
			'reference'             => $reference,
			'status'                => 'available',
			'version'               => $version,
			'revision'              => $revision,
			'published_revision'    => $revision,
			'position'              => $position,
			'addresses'             => array(
				'case_revision'  => $case_revision,
				'request_digest' => $request_digest,
			),
			'title'                 => $title,
			'summary'               => $summary,
			'why_it_fits'           => $why_it_fits,
			'trade_offs'            => $trade_offs,
			'route'                 => $route,
			'itinerary'             => $itinerary,
			'components'            => $components,
			'ledger'                => $ledger,
			'sources'               => $sources,
			'source_set_digest'     => Tra_Vel_Assisted_Proposal_Policy::source_set_digest( $sources ),
			'freshness'             => array(
				'checked_at'            => gmdate( 'c', $latest_observed ),
				'expires_at'            => gmdate( 'c', $earliest_expiry ),
				'requires_revalidation' => true,
			),
			'unresolved_items'       => $unresolved,
			'traveler_disposition'  => 'awaiting_review',
			'next_actions'          => Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( 'available', 'awaiting_review' ),
			'disclosure'            => array(
				'commercial_state'    => 'non_binding_assisted_proposal',
				'final_quote_required' => true,
				'message'             => Tra_Vel_Assisted_Proposal_Policy::FINAL_QUOTE_DISCLOSURE,
			),
			'created_at'            => gmdate( 'c', $now ),
			'published_at'          => gmdate( 'c', $now ),
			'expires_at'            => gmdate( 'c', $earliest_expiry ),
		);

		$valid = Tra_Vel_Assisted_Proposal_Policy::validate_publication(
			$proposal,
			$sources,
			array(
				'case_active'    => true,
				'case_revision'  => $case_revision,
				'request_digest' => $request_digest,
			),
			$now
		);
		return true === $valid ? $proposal : $valid;
	}

	/**
	 * Issue a short-lived operator attestation bound to the complete authored
	 * composition and exact quote-case request context. This is evidence that the
	 * operator made the attestation now, not automated supplier verification.
	 *
	 * @return array|WP_Error
	 */
	public static function issue_evidence_attestation( $input, $case, $operator_user_id, $now = null ) {
		$now      = null === $now ? time() : (int) $now;
		$operator = absint( $operator_user_id );
		$subject  = self::attestation_subject( $input );
		$binding  = self::attestation_case_binding( $case );
		if ( is_wp_error( $subject ) || is_wp_error( $binding ) ) {
			return is_wp_error( $subject ) ? $subject : $binding;
		}
		if ( $operator < 1 || ! function_exists( 'wp_salt' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_unavailable', 'Evidence attestation is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$payload = array(
			'version'         => self::EVIDENCE_ATTESTATION_VERSION,
			'operator_user_id'=> $operator,
			'case_id'         => $binding['case_id'],
			'case_version'    => $binding['case_version'],
			'case_revision'   => $binding['case_revision'],
			'request_digest'  => $binding['request_digest'],
			'subject_digest'  => $subject,
			'checked_at'      => $now,
			'expires_at'      => $now + self::EVIDENCE_ATTESTATION_TTL,
		);
		$encoded = self::base64url_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) );
		if ( '' === $encoded ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_unavailable', 'Evidence attestation could not be encoded safely.', array( 'status' => 503 ) );
		}
		$signature = hash_hmac( 'sha256', $encoded, self::attestation_secret() );
		return array(
			'attestation_token' => $encoded . '.' . $signature,
			'checked_at'         => gmdate( 'c', $now ),
			'expires_at'         => gmdate( 'c', $now + self::EVIDENCE_ATTESTATION_TTL ),
		);
	}

	/** @return array|WP_Error */
	public static function verify_evidence_attestation( $input, $case, $operator_user_id, $now = null ) {
		$now      = null === $now ? time() : (int) $now;
		$operator = absint( $operator_user_id );
		$token    = is_array( $input ) ? (string) ( $input['evidence_attestation_token'] ?? '' ) : '';
		if ( $operator < 1 || strlen( $token ) < 80 || strlen( $token ) > 2048 || ! function_exists( 'wp_salt' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_invalid', 'The evidence attestation is missing or invalid.', array( 'status' => 409 ) );
		}
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/', $parts[1] ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_invalid', 'The evidence attestation is malformed.', array( 'status' => 409 ) );
		}
		$expected_signature = hash_hmac( 'sha256', $parts[0], self::attestation_secret() );
		if ( ! hash_equals( $expected_signature, $parts[1] ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_invalid', 'The evidence attestation signature is invalid.', array( 'status' => 409 ) );
		}
		$decoded = self::base64url_decode( $parts[0] );
		$payload = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
		$closed  = self::require_keys( $payload, array( 'version', 'operator_user_id', 'case_id', 'case_version', 'case_revision', 'request_digest', 'subject_digest', 'checked_at', 'expires_at' ), '$.evidence_attestation' );
		if ( is_wp_error( $closed ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_invalid', 'The evidence attestation payload is invalid.', array( 'status' => 409 ) );
		}
		$subject = self::attestation_subject( $input );
		$binding = self::attestation_case_binding( $case );
		if ( is_wp_error( $subject ) || is_wp_error( $binding ) ) {
			return is_wp_error( $subject ) ? $subject : $binding;
		}
		$checked_at = is_int( $payload['checked_at'] ) ? $payload['checked_at'] : 0;
		$expires_at = is_int( $payload['expires_at'] ) ? $payload['expires_at'] : 0;
		$valid = self::EVIDENCE_ATTESTATION_VERSION === $payload['version']
			&& $operator === (int) $payload['operator_user_id']
			&& hash_equals( $binding['case_id'], (string) $payload['case_id'] )
			&& $binding['case_version'] === (int) $payload['case_version']
			&& $binding['case_revision'] === (int) $payload['case_revision']
			&& hash_equals( $binding['request_digest'], (string) $payload['request_digest'] )
			&& hash_equals( $subject, (string) $payload['subject_digest'] )
			&& $checked_at > 0 && $checked_at <= $now + 30
			&& $expires_at === $checked_at + self::EVIDENCE_ATTESTATION_TTL
			&& $expires_at >= $now;
		if ( ! $valid ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_stale', 'The evidence attestation expired or no longer matches the final proposal fields.', array( 'status' => 409 ) );
		}
		return array( 'checked_at' => $checked_at, 'expires_at' => $expires_at );
	}

	/** @return string|WP_Error */
	private static function attestation_subject( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_invalid', 'A typed proposal composition is required.', array( 'status' => 400 ) );
		}
		$subject = $input;
		unset( $subject['evidence_attestation_token'] );
		$closed = self::require_keys( $subject, array( 'position', 'title', 'summary', 'why_it_fits', 'trade_offs', 'route', 'itinerary', 'components', 'sources', 'unresolved_items' ), '$' );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		if ( ! self::is_list( $subject['sources'] ) || count( $subject['sources'] ) < 1 || count( $subject['sources'] ) > 32 ) {
			return new WP_Error( 'tra_vel_assisted_composition_sources_invalid', 'Add between one and 32 evidence sources.', array( 'status' => 400 ) );
		}
		foreach ( $subject['sources'] as $index => $source ) {
			$source_closed = self::require_keys( $source, array( 'provider_code', 'source_type', 'relationship', 'public_label', 'supplier_name', 'seller_name', 'source_reference', 'source_url', 'freshness_minutes', 'revalidated_now' ), '$.sources[' . $index . ']' );
			if ( is_wp_error( $source_closed ) ) {
				return $source_closed;
			}
			if ( true !== $source['revalidated_now'] ) {
				return new WP_Error( 'tra_vel_assisted_composition_source_revalidation_required', 'Every source must be rechecked before the final attestation.', array( 'status' => 400 ) );
			}
		}
		$encoded = wp_json_encode( $subject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > 262144 ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_too_large', 'The evidence attestation command is too large.', array( 'status' => 413 ) );
		}
		return Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $subject );
	}

	/** @return array|WP_Error */
	private static function attestation_case_binding( $case ) {
		$case_id        = strtolower( (string) ( $case['case_uuid'] ?? '' ) );
		$case_version   = (int) ( $case['case_version'] ?? 0 );
		$case_revision  = (int) ( $case['current_revision'] ?? 0 );
		$request_digest = strtolower( (string) ( $case['latest_request_digest'] ?? '' ) );
		if ( ! self::is_uuid( $case_id ) || $case_version < 1 || $case_revision < 1 || ! self::is_sha256( $request_digest ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_case_invalid', 'The quote-case attestation binding is incomplete.', array( 'status' => 409 ) );
		}
		return array( 'case_id' => $case_id, 'case_version' => $case_version, 'case_revision' => $case_revision, 'request_digest' => $request_digest );
	}

	private static function attestation_secret() {
		return wp_salt( 'auth' ) . '|tra-vel-assisted-proposal-evidence-attestation-v1';
	}

	private static function base64url_encode( $value ) {
		return is_string( $value ) ? rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ) : '';
	}

	private static function base64url_decode( $value ) {
		$padding = ( 4 - ( strlen( (string) $value ) % 4 ) ) % 4;
		return base64_decode( strtr( (string) $value, '-_', '+/' ) . str_repeat( '=', $padding ), true );
	}

	/** @return array|WP_Error */
	private static function route( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_route_invalid', 'A structured route is required.', array( 'status' => 400 ) );
		}
		$closed = self::require_keys( $input, array( 'origin', 'destinations', 'legs' ), '$.route' );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		$origin       = self::text( $input['origin'], 120, 'route origin' );
		$destinations = self::text_list( $input['destinations'], 1, 8, 80, 'route destinations', true );
		if ( is_wp_error( $origin ) || is_wp_error( $destinations ) ) {
			return is_wp_error( $origin ) ? $origin : $destinations;
		}
		if ( ! self::is_list( $input['legs'] ) || count( $input['legs'] ) > 12 ) {
			return new WP_Error( 'tra_vel_assisted_composition_route_legs_invalid', 'A route can contain at most 12 ordered legs.', array( 'status' => 400 ) );
		}
		$legs      = array();
		$sequences = array();
		foreach ( $input['legs'] as $index => $leg ) {
			if ( ! is_array( $leg ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_route_leg_invalid', 'Every route leg must be structured.', array( 'status' => 400 ) );
			}
			$closed = self::require_keys( $leg, array( 'sequence', 'from', 'to', 'mode' ), '$.route.legs[' . $index . ']' );
			if ( is_wp_error( $closed ) ) {
				return $closed;
			}
			$sequence = is_int( $leg['sequence'] ) ? $leg['sequence'] : 0;
			$from     = self::text( $leg['from'], 120, 'route leg origin' );
			$to       = self::text( $leg['to'], 120, 'route leg destination' );
			$mode     = sanitize_key( (string) $leg['mode'] );
			if ( $sequence < 1 || $sequence > 12 || isset( $sequences[ $sequence ] ) || is_wp_error( $from ) || is_wp_error( $to ) || ! in_array( $mode, array( 'flight', 'rail', 'road', 'ferry', 'walk', 'other' ), true ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_route_leg_invalid', 'Route legs require unique sequence, endpoints, and a supported mode.', array( 'status' => 400 ) );
			}
			$sequences[ $sequence ] = true;
			$legs[] = array( 'sequence' => $sequence, 'from' => $from, 'to' => $to, 'mode' => $mode );
		}
		usort( $legs, static function ( $left, $right ) { return (int) $left['sequence'] - (int) $right['sequence']; } );
		return array( 'origin' => $origin, 'destinations' => $destinations, 'legs' => $legs );
	}

	/** @return array|WP_Error */
	private static function sources( $input, $now, $identity ) {
		if ( ! self::is_list( $input ) || count( $input ) < 1 || count( $input ) > 32 ) {
			return new WP_Error( 'tra_vel_assisted_composition_sources_invalid', 'Add between one and 32 real evidence sources.', array( 'status' => 400 ) );
		}
		$sources        = array();
		$ids            = array();
		$latest_observed = 0;
		$earliest_expiry = null;
		$provided_ids    = is_array( $identity['source_ids'] ?? null ) ? array_values( $identity['source_ids'] ) : array();
		foreach ( $input as $index => $source_input ) {
			if ( ! is_array( $source_input ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_source_invalid', 'Every evidence source must be structured.', array( 'status' => 400 ) );
			}
			$closed = self::require_keys( $source_input, array( 'provider_code', 'source_type', 'relationship', 'public_label', 'supplier_name', 'seller_name', 'source_reference', 'source_url', 'freshness_minutes', 'revalidated_now' ), '$.sources[' . $index . ']' );
			if ( is_wp_error( $closed ) ) {
				return $closed;
			}
			if ( true !== $source_input['revalidated_now'] ) {
				return new WP_Error( 'tra_vel_assisted_composition_source_revalidation_required', 'Every evidence source must be explicitly revalidated before publication.', array( 'status' => 400 ) );
			}
			$type         = sanitize_key( (string) $source_input['source_type'] );
			$relationship = sanitize_key( (string) $source_input['relationship'] );
			$provider     = sanitize_key( (string) $source_input['provider_code'] );
			$freshness    = is_int( $source_input['freshness_minutes'] ) ? $source_input['freshness_minutes'] : 0;
			$max_minutes  = min( self::MAX_FRESHNESS_MINUTES, (int) floor( Tra_Vel_Assisted_Proposal_Policy::source_max_ttl_seconds( $type ) / 60 ) );
			if ( ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $provider ) || ! in_array( $type, Tra_Vel_Assisted_Proposal_Policy::source_types(), true ) || $freshness < 16 || $freshness > $max_minutes ) {
				return new WP_Error( 'tra_vel_assisted_composition_source_policy_invalid', 'Evidence type, relationship, provider code, or freshness window is invalid.', array( 'status' => 400 ) );
			}
			$public_label = self::text( $source_input['public_label'], 190, 'source label' );
			$supplier     = self::optional_text( $source_input['supplier_name'], 190, 'supplier name' );
			$seller       = self::optional_text( $source_input['seller_name'], 190, 'seller name' );
			if ( is_wp_error( $public_label ) || is_wp_error( $supplier ) || is_wp_error( $seller ) ) {
				return is_wp_error( $public_label ) ? $public_label : ( is_wp_error( $supplier ) ? $supplier : $seller );
			}
			$reference = trim( (string) $source_input['source_reference'] );
			$url       = trim( (string) ( $source_input['source_url'] ?? '' ) );
			$is_public = in_array( $type, array( 'public_supplier_page', 'official_information' ), true );
			if ( $is_public ) {
				$url = esc_url_raw( $url, array( 'https' ) );
				if ( '' === $url ) {
					return new WP_Error( 'tra_vel_assisted_composition_source_url_required', 'Public evidence requires a credential-free HTTPS URL.', array( 'status' => 400 ) );
				}
				if ( ! Tra_Vel_Assisted_Proposal_Policy::is_safe_https_source_url( $url ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_source_url_invalid', 'Public evidence URLs must be credential-free HTTPS paths without query data.', array( 'status' => 400 ) );
				}
				$provider_binding = Tra_Vel_Assisted_Proposal_Policy::validate_public_provider_binding( $provider, $type, $relationship, $url );
				if ( is_wp_error( $provider_binding ) ) {
					return $provider_binding;
				}
				$reference = '';
			} else {
				if ( 'operator_attested' !== $relationship ) {
					return new WP_Error( 'tra_vel_assisted_proposal_private_relationship_unverified', 'Manual private evidence must use the neutral operator-attested relationship.', array( 'status' => 400 ) );
				}
				if ( ! preg_match( '/^[A-Za-z0-9._:-]{1,190}$/', $reference ) ) {
					return new WP_Error( 'tra_vel_assisted_composition_source_reference_required', 'Connected, portal, or written evidence requires an opaque reference.', array( 'status' => 400 ) );
				}
				if ( '' !== $url ) {
					return new WP_Error( 'tra_vel_assisted_composition_private_source_url_forbidden', 'Use only an opaque reference for connected, portal, or written evidence.', array( 'status' => 400 ) );
				}
				$url = null;
			}
			$source_id = self::identity_uuid( $provided_ids[ $index ] ?? '' );
			if ( is_wp_error( $source_id ) || isset( $ids[ is_wp_error( $source_id ) ? '' : $source_id ] ) ) {
				return is_wp_error( $source_id ) ? $source_id : new WP_Error( 'tra_vel_assisted_composition_source_duplicate', 'Generated source identities must be unique.', array( 'status' => 409 ) );
			}
			$observed_at = $now;
			$fresh_until = $now + ( $freshness * MINUTE_IN_SECONDS );
			$source = array(
				'contract_version'      => Tra_Vel_Assisted_Proposal_Policy::SOURCE_CONTRACT_VERSION,
				'source_id'             => $source_id,
				'provider_code'         => $provider,
				'source_type'           => $type,
				'relationship'          => $relationship,
				'public_label'          => $public_label,
				'supplier_name'         => $supplier,
				'seller_name'           => $seller,
				'source_reference'      => $reference,
				'source_url'            => '' === $url ? null : $url,
				'observed_at'           => gmdate( 'c', $observed_at ),
				'fresh_until'           => gmdate( 'c', $fresh_until ),
				'requires_revalidation' => true,
			);
			$source['evidence_digest'] = Tra_Vel_Assisted_Proposal_Policy::canonical_digest( $source );
			$valid = Tra_Vel_Assisted_Proposal_Policy::validate_source( $source, $now );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$ids[ $source_id ] = true;
			$sources[]         = $source;
			$latest_observed   = max( $latest_observed, $observed_at );
			$earliest_expiry   = null === $earliest_expiry ? $fresh_until : min( $earliest_expiry, $fresh_until );
		}
		return array( 'sources' => $sources, 'source_ids' => array_keys( $ids ), 'latest_observed' => $latest_observed, 'earliest_expiry' => $earliest_expiry );
	}

	/** @return array|WP_Error */
	private static function components( $input, $source_ids ) {
		if ( ! self::is_list( $input ) || count( $input ) < 1 || count( $input ) > 16 ) {
			return new WP_Error( 'tra_vel_assisted_composition_components_invalid', 'Add between one and 16 sourced trip components.', array( 'status' => 400 ) );
		}
		$components = array();
		$keys       = array();
		foreach ( $input as $index => $component_input ) {
			if ( ! is_array( $component_input ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_component_invalid', 'Every trip component must be structured.', array( 'status' => 400 ) );
			}
			$closed = self::require_keys( $component_input, array( 'component_key', 'category', 'title', 'description', 'price', 'conditions', 'source_indexes' ), '$.components[' . $index . ']' );
			if ( is_wp_error( $closed ) ) {
				return $closed;
			}
			$key      = sanitize_key( (string) $component_input['component_key'] );
			$category = sanitize_key( (string) $component_input['category'] );
			$title    = self::text( $component_input['title'], 200, 'component title' );
			$description = self::text( $component_input['description'], 800, 'component description' );
			if ( ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $key ) || strlen( $key ) > 64 || isset( $keys[ $key ] ) || ! in_array( $category, Tra_Vel_Assisted_Proposal_Policy::categories(), true ) || is_wp_error( $title ) || is_wp_error( $description ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_component_identity_invalid', 'Components require unique keys, supported categories, titles, and descriptions.', array( 'status' => 400 ) );
			}
			$price = self::price( $component_input['price'] );
			$conditions = self::conditions( $component_input['conditions'] );
			if ( is_wp_error( $price ) || is_wp_error( $conditions ) ) {
				return is_wp_error( $price ) ? $price : $conditions;
			}
			if ( ! self::is_list( $component_input['source_indexes'] ) || count( $component_input['source_indexes'] ) < 1 || count( $component_input['source_indexes'] ) > 8 ) {
				return new WP_Error( 'tra_vel_assisted_composition_component_sources_invalid', 'Every component must cite between one and eight evidence sources.', array( 'status' => 400 ) );
			}
			$component_source_ids = array();
			foreach ( $component_input['source_indexes'] as $source_index ) {
				if ( ! is_int( $source_index ) || ! isset( $source_ids[ $source_index ] ) ) {
					return new WP_Error( 'tra_vel_assisted_composition_component_source_missing', 'A component references an unavailable evidence source.', array( 'status' => 400 ) );
				}
				$component_source_ids[] = $source_ids[ $source_index ];
			}
			$component_source_ids = array_values( array_unique( $component_source_ids ) );
			if ( count( $component_source_ids ) !== count( $component_input['source_indexes'] ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_component_source_duplicate', 'Component evidence references must be unique.', array( 'status' => 400 ) );
			}
			$keys[ $key ] = true;
			$components[] = array(
				'component_key'         => $key,
				'category'              => $category,
				'title'                 => $title,
				'description'           => $description,
				'price'                 => $price,
				'conditions'            => $conditions,
				'source_ids'            => $component_source_ids,
				'requires_revalidation' => true,
			);
		}
		return array( 'components' => $components, 'keys' => array_keys( $keys ) );
	}

	/** @return array|WP_Error */
	private static function price( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_price_invalid', 'Each component needs an explicit price state.', array( 'status' => 400 ) );
		}
		$closed = self::require_keys( $input, array( 'priced', 'total_for_party_minor', 'currency', 'basis', 'taxes', 'fees' ), '$.components[].price' );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		if ( ! is_bool( $input['priced'] ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_price_state_invalid', 'Choose whether the component has a validated source price.', array( 'status' => 400 ) );
		}
		if ( false === $input['priced'] ) {
			return array( 'priced' => false, 'total_for_party_minor' => null, 'currency' => null, 'basis' => 'not_priced', 'taxes' => 'unknown', 'fees' => 'unknown' );
		}
		$amount   = is_int( $input['total_for_party_minor'] ) ? $input['total_for_party_minor'] : -1;
		$currency = (string) $input['currency'];
		$basis    = sanitize_key( (string) $input['basis'] );
		$taxes    = sanitize_key( (string) $input['taxes'] );
		$fees     = sanitize_key( (string) $input['fees'] );
		if ( $amount < 0 || $amount > Tra_Vel_Assisted_Proposal_Policy::MAX_AMOUNT_MINOR || ! in_array( $currency, Tra_Vel_Assisted_Proposal_Policy::currencies(), true ) || ! in_array( $basis, array( 'trip_total', 'stay_total', 'ticket_total', 'activity_total', 'item_total' ), true ) || ! in_array( $taxes, array( 'included', 'excluded', 'unknown' ), true ) || ! in_array( $fees, array( 'included', 'excluded', 'unknown' ), true ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_price_invalid', 'A sourced price requires integer minor units, one supported currency, basis, taxes, and fees.', array( 'status' => 400 ) );
		}
		return array( 'priced' => true, 'total_for_party_minor' => $amount, 'currency' => $currency, 'basis' => $basis, 'taxes' => $taxes, 'fees' => $fees );
	}

	/** @return array|WP_Error */
	private static function conditions( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_conditions_invalid', 'Every component needs traveler-facing conditions.', array( 'status' => 400 ) );
		}
		$closed = self::require_keys( $input, array( 'cancellation', 'changes', 'baggage_or_inclusions' ), '$.components[].conditions' );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		$result = array();
		foreach ( array( 'cancellation', 'changes', 'baggage_or_inclusions' ) as $key ) {
			$value = self::text( $input[ $key ], 500, 'component condition' );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
			$result[ $key ] = $value;
		}
		return $result;
	}

	/** @return array|WP_Error */
	private static function itinerary( $input, $component_keys ) {
		if ( ! self::is_list( $input ) || count( $input ) < 1 || count( $input ) > 31 ) {
			return new WP_Error( 'tra_vel_assisted_composition_itinerary_invalid', 'Add between one and 31 itinerary days.', array( 'status' => 400 ) );
		}
		$allowed = array_fill_keys( $component_keys, true );
		$days    = array();
		$result  = array();
		foreach ( $input as $index => $day_input ) {
			if ( ! is_array( $day_input ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_itinerary_day_invalid', 'Every itinerary day must be structured.', array( 'status' => 400 ) );
			}
			$closed = self::require_keys( $day_input, array( 'day', 'place', 'title', 'component_keys' ), '$.itinerary[' . $index . ']' );
			if ( is_wp_error( $closed ) ) {
				return $closed;
			}
			$day   = is_int( $day_input['day'] ) ? $day_input['day'] : 0;
			$place = self::text( $day_input['place'], 120, 'itinerary place' );
			$title = self::text( $day_input['title'], 200, 'itinerary title' );
			if ( $day < 1 || $day > 365 || isset( $days[ $day ] ) || is_wp_error( $place ) || is_wp_error( $title ) || ! self::is_list( $day_input['component_keys'] ) || count( $day_input['component_keys'] ) > 16 ) {
				return new WP_Error( 'tra_vel_assisted_composition_itinerary_day_invalid', 'Itinerary days require unique day numbers, place, title, and bounded component references.', array( 'status' => 400 ) );
			}
			$keys = array();
			foreach ( $day_input['component_keys'] as $key_input ) {
				$key = sanitize_key( (string) $key_input );
				if ( ! isset( $allowed[ $key ] ) || isset( $keys[ $key ] ) ) {
					return new WP_Error( 'tra_vel_assisted_composition_itinerary_component_invalid', 'An itinerary day references an unavailable or duplicate component.', array( 'status' => 400 ) );
				}
				$keys[ $key ] = true;
			}
			$days[ $day ] = true;
			$result[] = array( 'day' => $day, 'place' => $place, 'title' => $title, 'component_keys' => array_keys( $keys ) );
		}
		usort( $result, static function ( $left, $right ) { return (int) $left['day'] - (int) $right['day']; } );
		return $result;
	}

	/** @return array|WP_Error */
	private static function unresolved_items( $input, $components ) {
		if ( ! self::is_list( $input ) || count( $input ) > 12 ) {
			return new WP_Error( 'tra_vel_assisted_composition_unresolved_invalid', 'Optional review gaps must be a bounded list.', array( 'status' => 400 ) );
		}
		$allowed = array( 'unpriced_component', 'taxes_unknown', 'fees_unknown', 'availability_revalidation', 'policy_revalidation', 'schedule_revalidation', 'other' );
		$items   = array();
		foreach ( $input as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_unresolved_invalid', 'Every review gap must be structured.', array( 'status' => 400 ) );
			}
			$closed = self::require_keys( $item, array( 'code', 'label' ), '$.unresolved_items[' . $index . ']' );
			if ( is_wp_error( $closed ) ) {
				return $closed;
			}
			$code  = sanitize_key( (string) $item['code'] );
			$label = self::text( $item['label'], 240, 'review gap label' );
			if ( ! in_array( $code, $allowed, true ) || isset( $items[ $code ] ) || is_wp_error( $label ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_unresolved_invalid', 'Review gap codes must be supported, unique, and clearly labeled.', array( 'status' => 400 ) );
			}
			$items[ $code ] = $label;
		}
		$required = array(
			'availability_revalidation' => 'Availability will be checked again before purchase.',
		);
		foreach ( $components as $component ) {
			if ( empty( $component['price']['priced'] ) ) {
				$required['unpriced_component'] = 'One or more trip components will be priced in the final personal quote.';
			} else {
				if ( 'included' !== $component['price']['taxes'] ) {
					$required['taxes_unknown'] = 'Taxes require confirmation in the final personal quote.';
				}
				if ( 'included' !== $component['price']['fees'] ) {
					$required['fees_unknown'] = 'Fees require confirmation in the final personal quote.';
				}
			}
		}
		foreach ( $required as $code => $label ) {
			if ( ! isset( $items[ $code ] ) ) {
				$items[ $code ] = $label;
			}
		}
		if ( count( $items ) > 16 ) {
			return new WP_Error( 'tra_vel_assisted_composition_unresolved_invalid', 'The proposal contains too many review gaps.', array( 'status' => 400 ) );
		}
		$result = array();
		foreach ( $items as $code => $label ) {
			$result[] = array( 'code' => $code, 'label' => $label );
		}
		return $result;
	}

	/** @return string|WP_Error */
	private static function text( $value, $max_length, $label ) {
		$value = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
		if ( '' === $value || strlen( $value ) > $max_length ) {
			return new WP_Error( 'tra_vel_assisted_composition_text_invalid', 'The ' . $label . ' is required and exceeds its safe boundary.', array( 'status' => 400 ) );
		}
		return $value;
	}

	/** @return string|WP_Error */
	private static function optional_text( $value, $max_length, $label ) {
		$value = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
		return strlen( $value ) <= $max_length ? $value : new WP_Error( 'tra_vel_assisted_composition_text_invalid', 'The ' . $label . ' exceeds its safe boundary.', array( 'status' => 400 ) );
	}

	/** @return array|WP_Error */
	private static function text_list( $value, $minimum, $maximum, $max_length, $label, $unique = false ) {
		if ( ! self::is_list( $value ) || count( $value ) < $minimum || count( $value ) > $maximum ) {
			return new WP_Error( 'tra_vel_assisted_composition_list_invalid', 'The ' . $label . ' list is outside its safe boundary.', array( 'status' => 400 ) );
		}
		$result = array();
		$seen   = array();
		foreach ( $value as $item ) {
			$text = self::text( $item, $max_length, $label );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			$key = strtolower( $text );
			if ( $unique && isset( $seen[ $key ] ) ) {
				return new WP_Error( 'tra_vel_assisted_composition_list_duplicate', 'The ' . $label . ' list contains duplicates.', array( 'status' => 400 ) );
			}
			$seen[ $key ] = true;
			$result[]     = $text;
		}
		return $result;
	}

	/** @return true|WP_Error */
	private static function require_keys( $value, $required, $path ) {
		if ( ! is_array( $value ) || self::is_list( $value ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_shape_invalid', 'The proposal composition contains an invalid object at ' . $path . '.', array( 'status' => 400 ) );
		}
		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $required, SORT_STRING );
		return $actual === $required ? true : new WP_Error( 'tra_vel_assisted_composition_shape_invalid', 'The proposal composition contains missing or unknown fields at ' . $path . '.', array( 'status' => 400 ) );
	}

	/** @return string|WP_Error */
	private static function identity_uuid( $candidate ) {
		$candidate = strtolower( (string) $candidate );
		if ( '' !== $candidate ) {
			return self::is_uuid( $candidate ) ? $candidate : new WP_Error( 'tra_vel_assisted_composition_identity_invalid', 'A generated proposal identity is invalid.', array( 'status' => 500 ) );
		}
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_identity_unavailable', 'Secure proposal identity generation is unavailable.', array( 'status' => 503 ) );
		}
		$generated = strtolower( (string) wp_generate_uuid4() );
		return self::is_uuid( $generated ) ? $generated : new WP_Error( 'tra_vel_assisted_composition_identity_unavailable', 'Secure proposal identity generation failed.', array( 'status' => 503 ) );
	}

	/** @return string|WP_Error */
	private static function reference( $candidate, $uuid ) {
		$candidate = strtoupper( trim( (string) $candidate ) );
		if ( '' === $candidate ) {
			$candidate = 'TVP-' . strtoupper( substr( str_replace( '-', '', $uuid ), 0, 12 ) );
		}
		return preg_match( '/^TVP-[A-Z0-9]{8}(?:[A-Z0-9]{4})?$/', $candidate ) ? $candidate : new WP_Error( 'tra_vel_assisted_composition_reference_invalid', 'The generated public proposal reference is invalid.', array( 'status' => 500 ) );
	}

	private static function datetime( $value ) {
		$value = (string) $value;
		$time  = strtotime( $value . ( preg_match( '/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value ) ? '' : ' UTC' ) );
		return false === $time ? 0 : (int) $time;
	}

	private static function is_list( $value ) {
		return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private static function is_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	private static function is_sha256( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}
}
