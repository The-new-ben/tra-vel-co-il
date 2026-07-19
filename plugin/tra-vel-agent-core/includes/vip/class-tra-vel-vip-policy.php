<?php
/**
 * Fail-closed contract policy for VIP traveler and service-case foundations.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Policy {
	const MAX_CAPABILITY_LIFETIME_SECONDS = 86400;

	/**
	 * Validate a privacy-minimized progressive registration projection.
	 *
	 * @return array|WP_Error
	 */
	public static function traveler_registration( $registration, $now = null ) {
		$keys = array( 'contract_version', 'registration_ref', 'trip_ref', 'account_ref', 'gate', 'party_flags', 'requirements', 'role_manifest_ref', 'version', 'created_at', 'updated_at', 'data_boundary' );
		if ( ! self::exact_object( $registration, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $registration['contract_version'] || ! self::privacy_safe( $registration ) ) {
			return self::error( 'registration_shape_invalid', 'The traveler registration is not a closed privacy-safe contract.' );
		}
		if ( ! self::ref( $registration['registration_ref'], 'registration' ) || ! self::ref( $registration['trip_ref'], 'trip' ) || ! self::ref( $registration['account_ref'], 'account' ) || ! self::ref( $registration['role_manifest_ref'], 'manifest' ) || ! self::positive_int( $registration['version'] ) || ! self::utc( $registration['created_at'] ) || ! self::utc( $registration['updated_at'] ) || $registration['updated_at'] < $registration['created_at'] || ( null !== $now && ( ! self::utc( $now ) || $registration['updated_at'] > $now ) ) ) {
			return self::error( 'registration_identity_invalid', 'The traveler registration identity or version is invalid.' );
		}
		$gate = $registration['gate'];
		if ( ! in_array( $gate, Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true ) ) {
			return self::error( 'registration_gate_invalid', 'The traveler registration gate is unsupported.' );
		}
		$flags = $registration['party_flags'];
		if ( ! self::exact_object( $flags, array( 'minor_present', 'dependent_adult_present', 'accessibility_required' ) ) || ! self::all_booleans( $flags ) ) {
			return self::error( 'registration_flags_invalid', 'The traveler registration party flags are invalid.' );
		}
		if ( ! is_array( $registration['requirements'] ) || array_values( $registration['requirements'] ) !== $registration['requirements'] || count( $registration['requirements'] ) > 32 ) {
			return self::error( 'registration_requirements_invalid', 'Traveler registration requirements must be an ordered list.' );
		}
		$requirements = array();
		foreach ( $registration['requirements'] as $item ) {
			if ( ! self::exact_object( $item, array( 'code', 'status', 'evidence_digest', 'verified_at' ) ) || ! in_array( $item['code'], Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS, true ) || isset( $requirements[ $item['code'] ] ) || ! in_array( $item['status'], array( 'self_asserted', 'verified', 'not_applicable' ), true ) || ( null !== $item['evidence_digest'] && ! self::digest( $item['evidence_digest'] ) ) || ( null !== $item['verified_at'] && ! self::utc( $item['verified_at'] ) ) ) {
				return self::error( 'registration_requirements_invalid', 'A traveler registration requirement is invalid or duplicated.' );
			}
			if ( 'verified' === $item['status'] && ( ! self::digest( $item['evidence_digest'] ) || ! self::utc( $item['verified_at'] ) ) ) {
				return self::error( 'registration_evidence_invalid', 'Verified traveler requirements require evidence and verification time.' );
			}
			if ( 'verified' === $item['status'] && ( $item['verified_at'] < $registration['created_at'] || $item['verified_at'] > $registration['updated_at'] || ( null !== $now && $item['verified_at'] > $now ) ) ) {
				return self::error( 'registration_evidence_clock_invalid', 'Requirement verification evidence must belong to the current registration revision clock.' );
			}
			if ( 'verified' !== $item['status'] && ( null !== $item['evidence_digest'] || null !== $item['verified_at'] ) ) {
				return self::error( 'registration_orphan_evidence_invalid', 'Self-asserted and not-applicable requirements cannot carry verification evidence.' );
			}
			if ( 'not_applicable' === $item['status'] && ! in_array( $item['code'], array( 'loyalty_preferences_recorded', 'guardian_authority_verified', 'dependent_support_plan_recorded', 'dependent_authority_verified', 'accessibility_supplier_acknowledged' ), true ) ) {
				return self::error( 'registration_requirement_not_applicable_invalid', 'This traveler requirement cannot be marked not applicable.' );
			}
			$requirements[ $item['code'] ] = $item;
		}

		$required = self::requirements_for_gate( $gate, $flags );
		$must_be_verified = array(
			'contact_verified',
			'reachable_contact_verified',
			'travel_document_snapshot_verified',
			'guardian_authority_verified',
			'dependent_authority_verified',
			'payment_session_owner_verified',
			'payment_state_sufficient',
			'supplier_confirmation_verified',
			'mandatory_traveler_data_accepted',
			'traveler_manifest_snapshot_verified',
			'document_admissibility_current',
			'accessibility_supplier_acknowledged',
			'service_contact_pack_offline',
			'dependency_health_checked',
		);
		foreach ( $required as $code ) {
			$optional_not_applicable = 'loyalty_preferences_recorded' === $code;
			if ( ! isset( $requirements[ $code ] ) || ( 'not_applicable' === $requirements[ $code ]['status'] && ! $optional_not_applicable ) || ( in_array( $code, $must_be_verified, true ) && 'verified' !== $requirements[ $code ]['status'] ) ) {
				return self::error( 'registration_gate_blocked', 'The traveler registration has not met every requirement for its declared gate.' );
			}
		}
		if ( ! self::data_boundary( $registration['data_boundary'] ) ) {
			return self::error( 'registration_boundary_invalid', 'The traveler registration data boundary is invalid.' );
		}
		return $registration;
	}

	/**
	 * Return cumulative requirements for one registration gate.
	 *
	 * @return string[]
	 */
	public static function requirements_for_gate( $gate, $party_flags = array() ) {
		$by_gate = array(
			'discover' => array( 'intent_recorded', 'travel_window_recorded', 'party_size_recorded' ),
			'personalize' => array( 'contact_verified', 'traveler_roles_declared', 'traveler_preferences_recorded', 'loyalty_preferences_recorded' ),
			'ready_to_quote' => array( 'exact_occupancy_recorded', 'rules_identity_facts_recorded', 'accessibility_requirements_recorded', 'reachable_contact_verified' ),
			'ready_to_reserve' => array( 'travel_document_snapshot_verified', 'terms_accepted', 'booking_questions_complete', 'payment_session_owner_verified' ),
			'ready_to_fulfill' => array( 'payment_state_sufficient', 'supplier_confirmation_verified', 'mandatory_traveler_data_accepted', 'traveler_manifest_snapshot_verified' ),
			'ready_to_travel' => array( 'document_admissibility_current', 'checkin_pickup_instructions_ready', 'emergency_contacts_offline', 'service_contact_pack_offline', 'itinerary_offline', 'dependency_health_checked' ),
		);
		$requirements = array();
		foreach ( Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES as $candidate ) {
			$requirements = array_merge( $requirements, $by_gate[ $candidate ] );
			if ( 'ready_to_reserve' === $candidate && ! empty( $party_flags['minor_present'] ) ) {
				$requirements[] = 'guardian_authority_verified';
			}
			if ( 'ready_to_quote' === $candidate && ! empty( $party_flags['dependent_adult_present'] ) ) {
				$requirements[] = 'dependent_support_plan_recorded';
			}
			if ( 'ready_to_reserve' === $candidate && ! empty( $party_flags['dependent_adult_present'] ) ) {
				$requirements[] = 'dependent_authority_verified';
			}
			if ( 'ready_to_travel' === $candidate && ! empty( $party_flags['accessibility_required'] ) ) {
				$requirements[] = 'accessibility_supplier_acknowledged';
			}
			if ( $candidate === $gate ) {
				break;
			}
		}
		return array_values( array_unique( $requirements ) );
	}

	/**
	 * Prove that one progressive registration revision is the exact successor
	 * of another, including every readiness invalidation caused by a real-world
	 * traveler, party, document, supplier, itinerary, or policy change.
	 *
	 * @return array|WP_Error
	 */
	public static function registration_successor( $previous, $next, $transition, $now ) {
		$previous = self::traveler_registration( $previous, $now );
		$next     = self::traveler_registration( $next, $now );
		if ( is_wp_error( $previous ) || is_wp_error( $next ) || ! self::utc( $now ) ) {
			return is_wp_error( $previous ) ? $previous : ( is_wp_error( $next ) ? $next : self::error( 'registration_transition_clock_invalid', 'A deterministic current UTC clock is required.' ) );
		}
		$keys = array( 'contract_version', 'transition_ref', 'registration_ref', 'trip_ref', 'from_version', 'to_version', 'from_gate', 'to_gate', 'reason', 'changed_requirements', 'invalidated_requirements', 'actor_ref', 'authority_digest', 'evidence_digest', 'occurred_at', 'authorization_effect', 'data_boundary' );
		if ( ! self::exact_object( $transition, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $transition['contract_version'] || ! self::privacy_safe( $transition ) || ! self::ref( $transition['transition_ref'], 'registration_transition' ) || ! self::ref( $transition['actor_ref'], 'principal' ) || ! self::digest( $transition['authority_digest'] ) || ! self::digest( $transition['evidence_digest'] ) || ! self::utc( $transition['occurred_at'] ) || 'registration_only' !== $transition['authorization_effect'] || ! self::data_boundary( $transition['data_boundary'] ) ) {
			return self::error( 'registration_transition_shape_invalid', 'The registration successor transition is not a closed evidence-bound contract.' );
		}
		if ( ! self::enum_list( $transition['changed_requirements'], Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS, true ) || ! self::enum_list( $transition['invalidated_requirements'], Tra_Vel_VIP_Taxonomy::REGISTRATION_REQUIREMENTS, false ) || ! in_array( $transition['reason'], Tra_Vel_VIP_Taxonomy::REGISTRATION_TRANSITION_REASONS, true ) ) {
			return self::error( 'registration_transition_taxonomy_invalid', 'The registration transition reason or requirement delta is invalid.' );
		}
		if ( $previous['registration_ref'] !== $next['registration_ref'] || $previous['registration_ref'] !== $transition['registration_ref'] || $previous['trip_ref'] !== $next['trip_ref'] || $previous['trip_ref'] !== $transition['trip_ref'] || $previous['account_ref'] !== $next['account_ref'] || $previous['created_at'] !== $next['created_at'] || $next['version'] !== $previous['version'] + 1 || $transition['from_version'] !== $previous['version'] || $transition['to_version'] !== $next['version'] || $transition['from_gate'] !== $previous['gate'] || $transition['to_gate'] !== $next['gate'] || $transition['occurred_at'] !== $next['updated_at'] || $transition['occurred_at'] <= $previous['updated_at'] || $transition['occurred_at'] > $now ) {
			return self::error( 'registration_transition_identity_invalid', 'A successor must preserve identity and advance exactly one revision at its evidence clock.' );
		}

		$previous_requirements = self::registration_requirement_map( $previous['requirements'] );
		$next_requirements     = self::registration_requirement_map( $next['requirements'] );
		$codes = array_values( array_unique( array_merge( array_keys( $previous_requirements ), array_keys( $next_requirements ) ) ) );
		$changed = array();
		$invalidated = array();
		$status_rank = array( 'not_applicable' => 0, 'self_asserted' => 1, 'verified' => 2 );
		foreach ( $codes as $code ) {
			$before = isset( $previous_requirements[ $code ] ) ? $previous_requirements[ $code ] : null;
			$after  = isset( $next_requirements[ $code ] ) ? $next_requirements[ $code ] : null;
			if ( $before !== $after ) {
				$changed[] = $code;
			}
			if ( null !== $before && ( null === $after || $status_rank[ $after['status'] ] < $status_rank[ $before['status'] ] ) ) {
				$invalidated[] = $code;
			}
		}
		sort( $changed, SORT_STRING );
		sort( $invalidated, SORT_STRING );
		$declared_changed = $transition['changed_requirements'];
		$declared_invalidated = $transition['invalidated_requirements'];
		sort( $declared_changed, SORT_STRING );
		sort( $declared_invalidated, SORT_STRING );
		if ( $changed !== $declared_changed || $invalidated !== $declared_invalidated ) {
			return self::error( 'registration_transition_delta_invalid', 'Every changed and invalidated readiness requirement must be declared exactly once.' );
		}

		$from_index = array_search( $previous['gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true );
		$to_index   = array_search( $next['gate'], Tra_Vel_VIP_Taxonomy::REGISTRATION_GATES, true );
		$party_changed = $previous['party_flags'] !== $next['party_flags'];
		$manifest_changed = $previous['role_manifest_ref'] !== $next['role_manifest_ref'];
		if ( 'progress' === $transition['reason'] ) {
			if ( $invalidated || $party_changed || $manifest_changed || $to_index < $from_index || $to_index > $from_index + 1 ) {
				return self::error( 'registration_transition_progress_invalid', 'Progress may add or strengthen requirements and advance at most one gate, but may not hide a regression.' );
			}
		} elseif ( $to_index > $from_index ) {
			return self::error( 'registration_transition_regression_invalid', 'A corrective or disruptive transition cannot simultaneously claim a higher readiness gate.' );
		}
		if ( $party_changed && ! in_array( $transition['reason'], array( 'party_change', 'guardian_change', 'accessibility_change' ), true ) ) {
			return self::error( 'registration_transition_party_reason_invalid', 'Party flags may change only through an explicit party, guardian, or accessibility transition.' );
		}
		if ( $manifest_changed && ! in_array( $transition['reason'], array( 'profile_correction', 'party_change', 'guardian_change' ), true ) ) {
			return self::error( 'registration_transition_manifest_reason_invalid', 'Role-manifest replacement requires an explicit authority-related transition reason.' );
		}
		$reason_invalidation = array(
			'party_change' => array( 'party_size_recorded', 'traveler_roles_declared', 'exact_occupancy_recorded', 'guardian_authority_verified', 'dependent_support_plan_recorded', 'dependent_authority_verified', 'mandatory_traveler_data_accepted', 'supplier_confirmation_verified', 'traveler_manifest_snapshot_verified' ),
			'document_change' => array( 'travel_document_snapshot_verified', 'mandatory_traveler_data_accepted', 'traveler_manifest_snapshot_verified', 'document_admissibility_current', 'supplier_confirmation_verified' ),
			'guardian_change' => array( 'traveler_roles_declared', 'guardian_authority_verified', 'dependent_authority_verified', 'traveler_manifest_snapshot_verified' ),
			'accessibility_change' => array( 'accessibility_requirements_recorded', 'booking_questions_complete', 'supplier_confirmation_verified', 'traveler_manifest_snapshot_verified', 'accessibility_supplier_acknowledged' ),
			'supplier_change' => array( 'supplier_confirmation_verified', 'mandatory_traveler_data_accepted', 'traveler_manifest_snapshot_verified', 'checkin_pickup_instructions_ready', 'accessibility_supplier_acknowledged', 'dependency_health_checked' ),
			'itinerary_change' => array( 'travel_window_recorded', 'rules_identity_facts_recorded', 'supplier_confirmation_verified', 'traveler_manifest_snapshot_verified', 'document_admissibility_current', 'checkin_pickup_instructions_ready', 'itinerary_offline', 'dependency_health_checked' ),
			'policy_refresh' => array( 'terms_accepted', 'booking_questions_complete', 'document_admissibility_current', 'dependency_health_checked' ),
		);
		if ( isset( $reason_invalidation[ $transition['reason'] ] ) && ! array_intersect( $invalidated, $reason_invalidation[ $transition['reason'] ] ) ) {
			return self::error( 'registration_transition_reason_invalidation_missing', 'This real-world change must invalidate at least one affected downstream readiness requirement.' );
		}
		return $next;
	}

	/**
	 * Validate explicit trip-scoped roles and authority. Roles never imply authority.
	 *
	 * @return array|WP_Error
	 */
	public static function role_manifest( $manifest ) {
		$keys = array( 'contract_version', 'manifest_ref', 'trip_ref', 'version', 'principals', 'created_at', 'data_boundary' );
		if ( ! self::exact_object( $manifest, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $manifest['contract_version'] || ! self::ref( $manifest['manifest_ref'], 'manifest' ) || ! self::ref( $manifest['trip_ref'], 'trip' ) || ! self::positive_int( $manifest['version'] ) || ! self::utc( $manifest['created_at'] ) || ! is_array( $manifest['principals'] ) || array_values( $manifest['principals'] ) !== $manifest['principals'] || ! $manifest['principals'] || ! self::privacy_safe( $manifest ) || ! self::data_boundary( $manifest['data_boundary'] ) ) {
			return self::error( 'role_manifest_shape_invalid', 'The traveler role manifest is invalid.' );
		}
		$seen = array();
		foreach ( $manifest['principals'] as $principal ) {
			$principal_keys = array( 'principal_ref', 'roles', 'traveler_refs', 'authority_scopes', 'authority_source', 'authority_evidence_digest', 'valid_from', 'valid_until' );
			if ( ! self::exact_object( $principal, $principal_keys ) || ! self::ref( $principal['principal_ref'], 'principal' ) || isset( $seen[ $principal['principal_ref'] ] ) || ! self::enum_list( $principal['roles'], Tra_Vel_VIP_Taxonomy::ROLES, true ) || ! self::ref_list( $principal['traveler_refs'], 'traveler' ) || ! self::enum_list( $principal['authority_scopes'], Tra_Vel_VIP_Taxonomy::AUTHORITY_SCOPES, false ) || ! in_array( $principal['authority_source'], array( 'self', 'documented_consent', 'guardian_evidence', 'organization_policy', 'supplier_acceptance', 'operator_assignment' ), true ) || ( null !== $principal['authority_evidence_digest'] && ! self::digest( $principal['authority_evidence_digest'] ) ) || ! self::utc( $principal['valid_from'] ) || ! self::utc( $principal['valid_until'] ) || $principal['valid_until'] <= $principal['valid_from'] ) {
				return self::error( 'role_manifest_principal_invalid', 'A traveler role principal is invalid or duplicated.' );
			}
			$seen[ $principal['principal_ref'] ] = true;
			$allowed = array();
			foreach ( $principal['roles'] as $role ) {
				$allowed = array_merge( $allowed, self::role_scope_allowlist( $role ) );
			}
			if ( array_diff( $principal['authority_scopes'], array_unique( $allowed ) ) ) {
				return self::error( 'role_manifest_authority_invalid', 'A role was granted authority outside its maximum permitted scope.' );
			}
			$has_high_impact = (bool) array_intersect( $principal['authority_scopes'], Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES );
			if ( $has_high_impact && ! self::digest( $principal['authority_evidence_digest'] ) ) {
				return self::error( 'role_manifest_authority_evidence_missing', 'High-impact authority requires an evidence digest.' );
			}
			if ( in_array( 'guardian', $principal['roles'], true ) && ! $principal['traveler_refs'] ) {
				return self::error( 'role_manifest_guardian_scope_invalid', 'Guardian authority must name at least one traveler scope.' );
			}
			if ( in_array( 'operator_delegate', $principal['roles'], true ) && $has_high_impact ) {
				return self::error( 'role_manifest_delegate_scope_invalid', 'An operator delegate cannot receive high-impact trip authority.' );
			}
		}
		return $manifest;
	}

	/**
	 * Authorize one action using an explicit role manifest and bounded assurance.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize_scope( $manifest, $principal_ref, $traveler_ref, $scope, $assurance, $now ) {
		$valid = self::role_manifest( $manifest );
		if ( is_wp_error( $valid ) || ! self::ref( $principal_ref, 'principal' ) || ( '' !== $traveler_ref && ! self::ref( $traveler_ref, 'traveler' ) ) || ! in_array( $scope, Tra_Vel_VIP_Taxonomy::AUTHORITY_SCOPES, true ) || ! self::utc( $now ) ) {
			return self::error( 'authorization_input_invalid', 'The VIP authorization input is invalid.' );
		}
		$principal = null;
		foreach ( $manifest['principals'] as $candidate ) {
			if ( $candidate['principal_ref'] === $principal_ref ) {
				$principal = $candidate;
				break;
			}
		}
		if ( null === $principal || $now < $principal['valid_from'] || $now >= $principal['valid_until'] || ! in_array( $scope, $principal['authority_scopes'], true ) || ( '' !== $traveler_ref && ! in_array( $traveler_ref, $principal['traveler_refs'], true ) ) ) {
			return self::error( 'authorization_denied', 'The principal does not hold current authority for this action and traveler.' );
		}
		if ( ! self::exact_object( $assurance, array( 'level', 'verified_at', 'expires_at', 'method_reference_digest' ) ) || ! in_array( $assurance['level'], array( 'scoped_capability', 'recent_auth', 'strong_reauth', 'specialist_review' ), true ) || ! self::utc( $assurance['verified_at'] ) || ! self::utc( $assurance['expires_at'] ) || $assurance['expires_at'] <= $assurance['verified_at'] || $now < $assurance['verified_at'] || $now >= $assurance['expires_at'] || ! self::digest( $assurance['method_reference_digest'] ) ) {
			return self::error( 'authorization_assurance_invalid', 'The authorization assurance is invalid or expired.' );
		}
		if ( Tra_Vel_VIP_Taxonomy::is_high_impact_scope( $scope ) && ! in_array( $assurance['level'], array( 'strong_reauth', 'specialist_review' ), true ) ) {
			return self::error( 'authorization_step_up_required', 'This high-impact action requires current step-up verification.' );
		}
		return true;
	}

	/**
	 * Validate a stored capability projection. It can never contain a bearer value.
	 *
	 * @return array|WP_Error
	 */
	public static function capability_grant( $grant ) {
		$keys = array( 'contract_version', 'capability_ref', 'capability_digest', 'trip_ref', 'case_ref', 'account_ref', 'issue_reason', 'channel', 'allowed_scopes', 'disclosure_classes', 'one_time', 'consumed_at', 'issued_at', 'expires_at', 'revoked_at', 'rotation_generation', 'scanner_safe_initial_get', 'session_exchange_required', 'security', 'data_boundary' );
		if ( ! self::exact_object( $grant, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $grant['contract_version'] || ! self::privacy_safe( $grant ) || ! self::ref( $grant['capability_ref'], 'capability' ) || ! self::digest( $grant['capability_digest'] ) || ! self::ref( $grant['trip_ref'], 'trip' ) || ( null !== $grant['case_ref'] && ! self::ref( $grant['case_ref'], 'case' ) ) || ( null !== $grant['account_ref'] && ! self::ref( $grant['account_ref'], 'account' ) ) || ! in_array( $grant['issue_reason'], array( 'trip_access', 'incident_intake', 'case_progress', 'lost_phone_recovery', 'operator_contact' ), true ) || ! in_array( $grant['channel'], array( 'email', 'sms', 'whatsapp', 'manual_recovery' ), true ) || ! self::enum_list( $grant['allowed_scopes'], Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES, true ) || ! self::enum_list( $grant['disclosure_classes'], array( 'public_emergency_guidance', 'trip_redacted', 'case_progress', 'ordinary_evidence_metadata', 'safe_contact_location' ), true ) || true !== $grant['one_time'] || true !== $grant['scanner_safe_initial_get'] || true !== $grant['session_exchange_required'] || ! is_int( $grant['rotation_generation'] ) || $grant['rotation_generation'] < 1 || ! self::utc( $grant['issued_at'] ) || ! self::utc( $grant['expires_at'] ) || $grant['expires_at'] <= $grant['issued_at'] || ( null !== $grant['consumed_at'] && ! self::utc( $grant['consumed_at'] ) ) || ( null !== $grant['revoked_at'] && ! self::utc( $grant['revoked_at'] ) ) ) {
			return self::error( 'capability_grant_invalid', 'The capability-link projection is invalid.' );
		}
		$lifetime = strtotime( $grant['expires_at'] ) - strtotime( $grant['issued_at'] );
		if ( $lifetime <= 0 || $lifetime > self::MAX_CAPABILITY_LIFETIME_SECONDS || array_intersect( $grant['allowed_scopes'], Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES ) || ( null !== $grant['consumed_at'] && ( $grant['consumed_at'] < $grant['issued_at'] || $grant['consumed_at'] > $grant['expires_at'] ) ) || ( null !== $grant['revoked_at'] && $grant['revoked_at'] < $grant['issued_at'] ) ) {
			return self::error( 'capability_grant_scope_invalid', 'A capability link is limited to short-lived, low-risk scopes.' );
		}
		$security = $grant['security'];
		if ( ! self::exact_object( $security, array( 'http_only', 'secure', 'same_site', 'referrer_policy', 'third_party_scripts', 'indexable' ) ) || true !== $security['http_only'] || true !== $security['secure'] || 'Strict' !== $security['same_site'] || 'no-referrer' !== $security['referrer_policy'] || false !== $security['third_party_scripts'] || false !== $security['indexable'] || ! self::data_boundary( $grant['data_boundary'] ) ) {
			return self::error( 'capability_grant_security_invalid', 'The capability-link security boundary is invalid.' );
		}
		return $grant;
	}

	/**
	 * Validate a deadline with UTC and source-local clocks preserved.
	 *
	 * @return array|WP_Error
	 */
	public static function deadline( $deadline ) {
		$keys = array( 'contract_version', 'deadline_ref', 'case_ref', 'type', 'basis', 'due_at', 'local_due_at', 'local_timezone', 'safety_margin_seconds', 'owner_ref', 'escalation_ref', 'state', 'source_version_digest', 'computed_at', 'satisfied_at', 'data_boundary' );
		if ( ! self::exact_object( $deadline, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $deadline['contract_version'] || ! self::privacy_safe( $deadline ) || ! self::ref( $deadline['deadline_ref'], 'deadline' ) || ! self::ref( $deadline['case_ref'], 'case' ) || ! in_array( $deadline['type'], Tra_Vel_VIP_Taxonomy::DEADLINE_TYPES, true ) || ! in_array( $deadline['basis'], Tra_Vel_VIP_Taxonomy::DEADLINE_BASES, true ) || ! self::utc( $deadline['due_at'] ) || ! self::offset_datetime( $deadline['local_due_at'] ) || ! self::timezone( $deadline['local_timezone'] ) || ! is_int( $deadline['safety_margin_seconds'] ) || $deadline['safety_margin_seconds'] < 0 || $deadline['safety_margin_seconds'] > 2592000 || ! self::ref( $deadline['owner_ref'], 'principal' ) || ! self::ref( $deadline['escalation_ref'], 'principal' ) || ! in_array( $deadline['state'], array( 'scheduled', 'due', 'missed', 'satisfied', 'cancelled', 'uncertain' ), true ) || ! self::digest( $deadline['source_version_digest'] ) || ! self::utc( $deadline['computed_at'] ) || ( null !== $deadline['satisfied_at'] && ! self::utc( $deadline['satisfied_at'] ) ) || ! self::data_boundary( $deadline['data_boundary'] ) ) {
			return self::error( 'deadline_invalid', 'The VIP case deadline is invalid.' );
		}
		$local_utc = ( new DateTimeImmutable( $deadline['local_due_at'] ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$named_date  = ( new DateTimeImmutable( $deadline['due_at'] ) )->setTimezone( new DateTimeZone( $deadline['local_timezone'] ) );
		$named_local = 'Z' === substr( $deadline['local_due_at'], -1 ) ? $named_date->format( 'Y-m-d\TH:i:s\Z' ) : $named_date->format( 'Y-m-d\TH:i:sP' );
		if ( $local_utc !== $deadline['due_at'] || $named_local !== $deadline['local_due_at'] || ( 'scheduled' === $deadline['state'] && $deadline['due_at'] <= $deadline['computed_at'] ) || ( 'satisfied' === $deadline['state'] && null === $deadline['satisfied_at'] ) || ( 'satisfied' !== $deadline['state'] && null !== $deadline['satisfied_at'] ) ) {
			return self::error( 'deadline_clock_invalid', 'The VIP deadline clocks or completion evidence are inconsistent.' );
		}
		return $deadline;
	}

	/**
	 * Validate one decision and its optional expiring authorization packet.
	 *
	 * @return array|WP_Error
	 */
	public static function decision( $decision ) {
		$keys = array( 'contract_version', 'decision_ref', 'case_ref', 'version', 'status', 'option_refs', 'recommended_option_ref', 'impact_scopes', 'required_approver', 'quote_expires_at', 'decision_expires_at', 'policy_snapshot_digest', 'request_digest', 'authorization', 'created_at', 'data_boundary' );
		if ( ! self::exact_object( $decision, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $decision['contract_version'] || ! self::privacy_safe( $decision ) || ! self::ref( $decision['decision_ref'], 'decision' ) || ! self::ref( $decision['case_ref'], 'case' ) || ! self::positive_int( $decision['version'] ) || ! in_array( $decision['status'], array( 'draft', 'ready', 'authorization_pending', 'authorized', 'rejected', 'expired', 'executing', 'superseded' ), true ) || ! self::ref_list( $decision['option_refs'], 'option', true ) || ( null !== $decision['recommended_option_ref'] && ! in_array( $decision['recommended_option_ref'], $decision['option_refs'], true ) ) || ! self::enum_list( $decision['impact_scopes'], Tra_Vel_VIP_Taxonomy::AUTHORITY_SCOPES, true ) || ! self::utc( $decision['quote_expires_at'] ) || ! self::utc( $decision['decision_expires_at'] ) || $decision['decision_expires_at'] > $decision['quote_expires_at'] || ! self::digest( $decision['policy_snapshot_digest'] ) || ! self::digest( $decision['request_digest'] ) || ! self::utc( $decision['created_at'] ) || ! self::data_boundary( $decision['data_boundary'] ) ) {
			return self::error( 'decision_invalid', 'The VIP case decision is invalid.' );
		}
		$approver = $decision['required_approver'];
		if ( ! self::exact_object( $approver, array( 'principal_ref', 'role', 'authority_scope' ) ) || ! self::ref( $approver['principal_ref'], 'principal' ) || ! in_array( $approver['role'], Tra_Vel_VIP_Taxonomy::ROLES, true ) || ! in_array( $approver['authority_scope'], $decision['impact_scopes'], true ) ) {
			return self::error( 'decision_approver_invalid', 'The VIP decision has no valid explicit approver.' );
		}
		$requires_authorization = in_array( $decision['status'], array( 'authorized', 'executing' ), true );
		if ( $requires_authorization && ! is_array( $decision['authorization'] ) ) {
			return self::error( 'decision_authorization_missing', 'An authorized or executing decision requires an authorization packet.' );
		}
		if ( null !== $decision['authorization'] ) {
			$authorization = $decision['authorization'];
			$auth_keys = array( 'authorization_ref', 'actor_ref', 'authority_evidence_digest', 'authorized_scopes', 'expected_decision_version', 'request_digest', 'authorized_at', 'expires_at', 'step_up' );
			if ( ! in_array( $decision['status'], array( 'authorized', 'executing' ), true ) || ! self::exact_object( $authorization, $auth_keys ) || ! self::ref( $authorization['authorization_ref'], 'authorization' ) || $authorization['actor_ref'] !== $approver['principal_ref'] || ! self::digest( $authorization['authority_evidence_digest'] ) || ! self::enum_list( $authorization['authorized_scopes'], Tra_Vel_VIP_Taxonomy::AUTHORITY_SCOPES, true ) || array_diff( $decision['impact_scopes'], $authorization['authorized_scopes'] ) || $authorization['expected_decision_version'] !== $decision['version'] || $authorization['request_digest'] !== $decision['request_digest'] || ! self::utc( $authorization['authorized_at'] ) || ! self::utc( $authorization['expires_at'] ) || $authorization['authorized_at'] < $decision['created_at'] || $authorization['expires_at'] <= $authorization['authorized_at'] || $authorization['expires_at'] > $decision['decision_expires_at'] ) {
				return self::error( 'decision_authorization_invalid', 'The VIP decision authorization is invalid, stale, or incomplete.' );
			}
			$step_up = $authorization['step_up'];
			if ( ! self::exact_object( $step_up, array( 'level', 'verified_at', 'expires_at', 'method_reference_digest' ) ) || ! in_array( $step_up['level'], array( 'recent_auth', 'strong_reauth', 'specialist_review' ), true ) || ! self::utc( $step_up['verified_at'] ) || ! self::utc( $step_up['expires_at'] ) || $step_up['verified_at'] > $authorization['authorized_at'] || $step_up['expires_at'] <= $step_up['verified_at'] || $step_up['expires_at'] < $authorization['authorized_at'] || ! self::digest( $step_up['method_reference_digest'] ) ) {
				return self::error( 'decision_step_up_invalid', 'The VIP decision step-up proof is invalid.' );
			}
			if ( array_intersect( $decision['impact_scopes'], Tra_Vel_VIP_Taxonomy::HIGH_IMPACT_SCOPES ) && ! in_array( $step_up['level'], array( 'strong_reauth', 'specialist_review' ), true ) ) {
				return self::error( 'decision_step_up_required', 'A high-impact decision requires strong step-up or specialist verification.' );
			}
		}
		return $decision;
	}

	/**
	 * Validate one customer-simple, operator-deep service-case projection.
	 *
	 * @return array|WP_Error
	 */
	public static function service_case( $case ) {
		$keys = array( 'contract_version', 'case_ref', 'public_case_ref', 'trip_ref', 'intake_access', 'incident', 'lifecycle', 'severity', 'operational_health', 'owner_ref', 'deadline_refs', 'decision_refs', 'operation_refs', 'outcomes', 'resolution', 'version', 'opened_at', 'updated_at', 'data_boundary' );
		if ( ! self::exact_object( $case, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $case['contract_version'] || ! self::privacy_safe( $case ) || ! self::ref( $case['case_ref'], 'case' ) || ! preg_match( '/^TV-[A-Z0-9]{8}$/', (string) $case['public_case_ref'] ) || ( null !== $case['trip_ref'] && ! self::ref( $case['trip_ref'], 'trip' ) ) || ! in_array( $case['intake_access'], array( 'public_emergency_intake', 'scoped_trip_link', 'step_up_session', 'operator' ), true ) || ! in_array( $case['lifecycle'], Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! in_array( $case['severity'], Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! in_array( $case['operational_health'], array( 'healthy', 'at_risk', 'disrupted', 'stranded', 'recovery_in_progress', 'recovered', 'closed_with_loss' ), true ) || ( null !== $case['owner_ref'] && ! self::ref( $case['owner_ref'], 'principal' ) ) || ! self::ref_list( $case['deadline_refs'], 'deadline' ) || ! self::ref_list( $case['decision_refs'], 'decision' ) || ! self::ref_list( $case['operation_refs'], 'operation' ) || ! self::positive_int( $case['version'] ) || ! self::utc( $case['opened_at'] ) || ! self::utc( $case['updated_at'] ) || $case['updated_at'] < $case['opened_at'] || ! self::data_boundary( $case['data_boundary'] ) ) {
			return self::error( 'service_case_invalid', 'The VIP service case is invalid.' );
		}
		$incident = $case['incident'];
		$incident_keys = array( 'type', 'affected_services', 'people_at_risk', 'current_location_ref', 'next_critical_event_ref', 'evidence_status', 'authorization_status', 'supplier_contactability', 'financial_exposure' );
		if ( ! self::exact_object( $incident, $incident_keys ) || ! in_array( $incident['type'], Tra_Vel_VIP_Taxonomy::incident_types(), true ) || ! self::enum_list( $incident['affected_services'], Tra_Vel_VIP_Taxonomy::VERTICALS, true ) || ( null !== $incident['current_location_ref'] && ! self::ref( $incident['current_location_ref'], 'location' ) ) || ( null !== $incident['next_critical_event_ref'] && ! self::ref( $incident['next_critical_event_ref'], 'event' ) ) || ! in_array( $incident['evidence_status'], array( 'none', 'needed', 'collecting', 'complete', 'restricted' ), true ) || ! in_array( $incident['authorization_status'], array( 'unknown', 'not_required', 'pending', 'verified', 'conflicted', 'expired' ), true ) || ! in_array( $incident['supplier_contactability'], array( 'unknown', 'reachable', 'degraded', 'unreachable', 'not_applicable' ), true ) || ! in_array( $incident['financial_exposure'], array( 'none', 'unknown', 'low', 'material', 'severe' ), true ) ) {
			return self::error( 'service_case_incident_invalid', 'The VIP service case incident normalization is invalid.' );
		}
		$risk = $incident['people_at_risk'];
		if ( ! self::exact_object( $risk, array( 'traveler_count', 'minor_count', 'vulnerable_count', 'stranded_count', 'immediate_danger' ) ) || ! self::bounded_count( $risk['traveler_count'] ) || ! self::bounded_count( $risk['minor_count'] ) || ! self::bounded_count( $risk['vulnerable_count'] ) || ! self::bounded_count( $risk['stranded_count'] ) || ! is_bool( $risk['immediate_danger'] ) || $risk['minor_count'] > $risk['traveler_count'] || $risk['vulnerable_count'] > $risk['traveler_count'] || $risk['stranded_count'] > $risk['traveler_count'] ) {
			return self::error( 'service_case_risk_invalid', 'The VIP service case risk counts are invalid.' );
		}
		$incident_vertical = Tra_Vel_VIP_Taxonomy::incident_vertical( $incident['type'] );
		if ( 'cross_trip' !== $incident_vertical && ! in_array( $incident_vertical, $incident['affected_services'], true ) ) {
			return self::error( 'service_case_vertical_invalid', 'The incident vertical must be present in affected services.' );
		}
		if ( ( $risk['immediate_danger'] || 'insurance.immediate_danger' === $incident['type'] ) && 'P0' !== $case['severity'] ) {
			return self::error( 'service_case_severity_invalid', 'Immediate danger requires P0 severity.' );
		}
		if ( ! in_array( $case['lifecycle'], array( 'received', 'verified' ), true ) && null === $case['owner_ref'] ) {
			return self::error( 'service_case_owner_missing', 'A triaged or later VIP case requires an accountable owner.' );
		}
		$outcome_keys = array_keys( Tra_Vel_VIP_Taxonomy::OUTCOME_AXES );
		if ( ! self::exact_object( $case['outcomes'], $outcome_keys ) ) {
			return self::error( 'service_case_outcomes_invalid', 'Every independent VIP outcome axis is required.' );
		}
		foreach ( Tra_Vel_VIP_Taxonomy::OUTCOME_AXES as $axis => $states ) {
			if ( ! in_array( $case['outcomes'][ $axis ], $states, true ) ) {
				return self::error( 'service_case_outcomes_invalid', 'A VIP outcome axis contains an unsupported state.' );
			}
		}
		$is_resolved = in_array( $case['lifecycle'], array( 'resolved', 'closed', 'closed_with_loss' ), true );
		if ( $is_resolved !== is_array( $case['resolution'] ) ) {
			return self::error( 'service_case_resolution_invalid', 'Resolution evidence is required only for a resolved or closed case.' );
		}
		if ( $is_resolved ) {
			$resolution = $case['resolution'];
			if ( ! self::exact_object( $resolution, array( 'reason_code', 'evidence_digests', 'resolved_at' ) ) || ! in_array( $resolution['reason_code'], array( 'traveler_continuity_restored', 'safety_handoff_verified', 'service_replaced', 'authorized_loss_accepted', 'financial_tail_monitored', 'closed_with_verified_loss' ), true ) || ! self::digest_list( $resolution['evidence_digests'], true ) || ! self::utc( $resolution['resolved_at'] ) || in_array( $case['outcomes']['supplier'], array( 'pending', 'uncertain', 'reconciled' ), true ) || in_array( $case['outcomes']['safety'], array( 'unassessed', 'at_risk', 'handoff_in_progress' ), true ) || ( in_array( $case['lifecycle'], array( 'resolved', 'closed' ), true ) && ! in_array( $case['outcomes']['continuity'], array( 'healthy', 'restored' ), true ) ) || ( 'closed_with_loss' === $case['lifecycle'] && ! in_array( 'closed_with_loss', $case['outcomes'], true ) && ! in_array( $resolution['reason_code'], array( 'authorized_loss_accepted', 'closed_with_verified_loss' ), true ) ) ) {
				return self::error( 'service_case_resolution_invalid', 'The VIP case cannot be resolved without authoritative outcome evidence.' );
			}
		}
		return $case;
	}

	/**
	 * Validate an append-only event and classify exact replays.
	 *
	 * @param array $accepted_by_ref Previously accepted event_ref => immutable envelope fingerprint.
	 * @return array|WP_Error
	 */
	public static function service_case_event( $event, $accepted_by_ref = array() ) {
		$keys = array( 'contract_version', 'event_ref', 'case_ref', 'sequence', 'expected_case_version', 'new_case_version', 'type', 'lifecycle_before', 'lifecycle_after', 'lifecycle_command', 'severity_before', 'severity_after', 'outcome_change', 'actor', 'correlation_ref', 'causation_ref', 'operation_ref', 'payload_digest', 'evidence_digests', 'redaction_classification', 'occurred_at', 'received_at', 'data_boundary' );
		if ( ! self::exact_object( $event, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $event['contract_version'] || ! self::privacy_safe( $event ) || ! self::ref( $event['event_ref'], 'event' ) || ! self::ref( $event['case_ref'], 'case' ) || ! self::positive_int( $event['sequence'] ) || ! self::positive_int( $event['expected_case_version'] ) || $event['new_case_version'] !== $event['expected_case_version'] + 1 || ! in_array( $event['type'], Tra_Vel_VIP_Taxonomy::EVENT_TYPES, true ) || ! in_array( $event['lifecycle_before'], Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! in_array( $event['lifecycle_after'], Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! in_array( $event['severity_before'], Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! in_array( $event['severity_after'], Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! self::ref( $event['correlation_ref'], 'correlation' ) || ( null !== $event['causation_ref'] && ! self::ref( $event['causation_ref'], 'event' ) ) || ( null !== $event['operation_ref'] && ! self::ref( $event['operation_ref'], 'operation' ) ) || ! self::digest( $event['payload_digest'] ) || ! self::digest_list( $event['evidence_digests'] ) || ! in_array( $event['redaction_classification'], array( 'public', 'operator', 'restricted', 'security' ), true ) || ! self::utc( $event['occurred_at'] ) || ! self::utc( $event['received_at'] ) || $event['received_at'] < $event['occurred_at'] || ! self::data_boundary( $event['data_boundary'] ) ) {
			return self::error( 'service_case_event_invalid', 'The VIP service-case event is invalid.' );
		}
		if ( $event['lifecycle_before'] !== $event['lifecycle_after'] ) {
			$next = Tra_Vel_VIP_State_Machine::lifecycle_transition( $event['lifecycle_before'], $event['lifecycle_command'] );
			if ( is_wp_error( $next ) || $next !== $event['lifecycle_after'] ) {
				return self::error( 'service_case_event_transition_invalid', 'The event does not describe a valid lifecycle transition.' );
			}
		} elseif ( null !== $event['lifecycle_command'] ) {
			return self::error( 'service_case_event_transition_invalid', 'An unchanged lifecycle cannot claim a transition command.' );
		}
		if ( null !== $event['outcome_change'] ) {
			$change = $event['outcome_change'];
			if ( ! self::exact_object( $change, array( 'axis', 'from_state', 'to_state', 'command' ) ) ) {
				return self::error( 'service_case_event_outcome_invalid', 'The VIP event outcome change is invalid.' );
			}
			$next = Tra_Vel_VIP_State_Machine::outcome_transition( $change['axis'], $change['from_state'], $change['command'] );
			if ( is_wp_error( $next ) || $next !== $change['to_state'] ) {
				return self::error( 'service_case_event_outcome_invalid', 'The VIP event outcome change is not an allowed transition.' );
			}
		}
		$actor = $event['actor'];
		if ( ! self::exact_object( $actor, array( 'type', 'principal_ref', 'authority_scope', 'authority_evidence_digest' ) ) || ! in_array( $actor['type'], array( 'system', 'traveler', 'operator', 'provider_adapter', 'payment_adapter', 'insurer_assistance', 'emergency_authority' ), true ) || ( null !== $actor['principal_ref'] && ! self::ref( $actor['principal_ref'], 'principal' ) ) || ( null !== $actor['authority_scope'] && ! in_array( $actor['authority_scope'], Tra_Vel_VIP_Taxonomy::AUTHORITY_SCOPES, true ) ) || ( null !== $actor['authority_evidence_digest'] && ! self::digest( $actor['authority_evidence_digest'] ) ) || ( null !== $actor['authority_scope'] && Tra_Vel_VIP_Taxonomy::is_high_impact_scope( $actor['authority_scope'] ) && ! self::digest( $actor['authority_evidence_digest'] ) ) ) {
			return self::error( 'service_case_event_actor_invalid', 'The VIP event actor authority is invalid.' );
		}
		$fingerprint = self::event_fingerprint( $event );
		if ( isset( $accepted_by_ref[ $event['event_ref'] ] ) ) {
			if ( $accepted_by_ref[ $event['event_ref'] ] !== $fingerprint ) {
				return self::error( 'service_case_event_replay_conflict', 'An event reference cannot be replayed with a different immutable envelope.' );
			}
			return array( 'event' => $event, 'replay' => true, 'fingerprint' => $fingerprint );
		}
		return array( 'event' => $event, 'replay' => false, 'fingerprint' => $fingerprint );
	}

	/**
	 * Validate one of the deterministic 50-scenario stress vocabulary entries.
	 *
	 * @return array|WP_Error
	 */
	public static function disruption_scenario( $scenario ) {
		$keys = array( 'contract_version', 'scenario_ref', 'scenario_number', 'scenario_code', 'affected_verticals', 'injected_condition_codes', 'expected_event_types', 'expected_customer_projection', 'expected_case_severity', 'expected_lifecycle', 'expected_outcomes', 'invariant_codes', 'clock', 'provider_script_digests', 'expected_operator_task_codes', 'data_boundary' );
		$invariants = array( 'no_duplicate_side_effect', 'no_false_success', 'current_authority_required', 'constraints_preserved', 'no_hidden_partial_failure', 'no_sensitive_general_events', 'owner_and_timer_required', 'money_balanced_by_currency', 'local_timezone_visible', 'event_replay_deterministic' );
		if ( ! self::exact_object( $scenario, $keys ) || Tra_Vel_VIP_Taxonomy::CONTRACT_VERSION !== $scenario['contract_version'] || ! self::privacy_safe( $scenario ) || ! self::ref( $scenario['scenario_ref'], 'scenario' ) || ! is_int( $scenario['scenario_number'] ) || ! isset( Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS[ $scenario['scenario_number'] ] ) || Tra_Vel_VIP_Taxonomy::STRESS_SCENARIOS[ $scenario['scenario_number'] ] !== $scenario['scenario_code'] || ! self::enum_list( $scenario['affected_verticals'], Tra_Vel_VIP_Taxonomy::VERTICALS, true ) || ! self::code_list( $scenario['injected_condition_codes'], true ) || ! self::enum_list( $scenario['expected_event_types'], Tra_Vel_VIP_Taxonomy::EVENT_TYPES, true ) || ! in_array( $scenario['expected_customer_projection'], array( 'case_received', 'immediate_safety_help', 'action_required', 'recovery_underway', 'attention_needed', 'recovered', 'resolved_with_loss' ), true ) || ! in_array( $scenario['expected_case_severity'], Tra_Vel_VIP_Taxonomy::SEVERITIES, true ) || ! in_array( $scenario['expected_lifecycle'], Tra_Vel_VIP_Taxonomy::LIFECYCLE_STATES, true ) || ! self::enum_list( $scenario['invariant_codes'], $invariants, true ) || array_diff( $invariants, $scenario['invariant_codes'] ) || ! self::digest_list( $scenario['provider_script_digests'], true ) || ! self::code_list( $scenario['expected_operator_task_codes'], true ) || ! self::data_boundary( $scenario['data_boundary'] ) ) {
			return self::error( 'disruption_scenario_invalid', 'The deterministic disruption scenario is invalid.' );
		}
		if ( ! self::exact_object( $scenario['expected_outcomes'], array_keys( Tra_Vel_VIP_Taxonomy::OUTCOME_AXES ) ) ) {
			return self::error( 'disruption_scenario_outcomes_invalid', 'Every stress scenario must declare each independent outcome axis.' );
		}
		foreach ( Tra_Vel_VIP_Taxonomy::OUTCOME_AXES as $axis => $states ) {
			if ( ! in_array( $scenario['expected_outcomes'][ $axis ], $states, true ) ) {
				return self::error( 'disruption_scenario_outcomes_invalid', 'A stress scenario has an invalid expected outcome.' );
			}
		}
		if ( ! self::exact_object( $scenario['clock'], array( 'started_at', 'timezone' ) ) || ! self::utc( $scenario['clock']['started_at'] ) || ! self::timezone( $scenario['clock']['timezone'] ) ) {
			return self::error( 'disruption_scenario_clock_invalid', 'A stress scenario requires a deterministic UTC and local clock.' );
		}
		return $scenario;
	}

	public static function canonical_digest( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Fingerprint the complete immutable event envelope, not only its data payload.
	 */
	public static function event_fingerprint( $event ) {
		return self::canonical_digest( $event );
	}

	private static function role_scope_allowlist( $role ) {
		$low = Tra_Vel_VIP_Taxonomy::LOW_RISK_CAPABILITY_SCOPES;
		$map = array(
			'account_holder' => $low,
			'traveler' => array_merge( $low, array( 'service_reserve', 'service_change', 'service_cancel', 'payment_authorize', 'identity_change', 'sensitive_evidence_disclose', 'recovery_channel_change', 'delegate_manage' ) ),
			'booker' => array_merge( $low, array( 'service_reserve', 'service_change', 'service_cancel' ) ),
			'payer' => array_merge( $low, array( 'payment_authorize' ) ),
			'guardian' => array_merge( $low, array( 'service_reserve', 'service_change', 'service_cancel', 'payment_authorize', 'identity_change', 'guardian_authority_change', 'sensitive_evidence_disclose', 'recovery_channel_change', 'delegate_manage' ) ),
			'beneficiary' => array_merge( $low, array( 'sensitive_evidence_disclose' ) ),
			'emergency_contact' => array( 'incident_report', 'operator_contact_approve' ),
			'operator_delegate' => $low,
			'supplier_passenger' => array(),
		);
		return isset( $map[ $role ] ) ? $map[ $role ] : array();
	}

	private static function registration_requirement_map( $requirements ) {
		$map = array();
		foreach ( $requirements as $requirement ) {
			$map[ $requirement['code'] ] = $requirement;
		}
		ksort( $map, SORT_STRING );
		return $map;
	}

	private static function exact_object( $value, $required ) {
		return is_array( $value ) && ! array_diff( $required, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $required );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function ref_list( $values, $kind, $required = false ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::ref( $value, $kind ) ) {
				return false;
			}
		}
		return true;
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function digest_list( $values, $required = false ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! self::digest( $value ) ) {
				return false;
			}
		}
		return true;
	}

	private static function enum_list( $values, $allowed, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! in_array( $value, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function code_list( $values, $required ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || ! preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $value ) || strlen( $value ) > 96 ) {
				return false;
			}
		}
		return true;
	}

	private static function utc( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return false;
		}
		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $error ) {
			return false;
		}
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ) === $value;
	}

	private static function offset_datetime( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value ) ) {
			return false;
		}
		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $error ) {
			return false;
		}
		if ( 'Z' === substr( $value, -1 ) ) {
			return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ) === $value;
		}
		return $date->format( 'Y-m-d\TH:i:sP' ) === $value;
	}

	private static function timezone( $value ) {
		return is_string( $value ) && in_array( $value, timezone_identifiers_list(), true );
	}

	private static function positive_int( $value ) {
		return is_int( $value ) && $value > 0;
	}

	private static function bounded_count( $value ) {
		return is_int( $value ) && $value >= 0 && $value <= 10000;
	}

	private static function all_booleans( $value ) {
		foreach ( $value as $item ) {
			if ( ! is_bool( $item ) ) {
				return false;
			}
		}
		return true;
	}

	private static function data_boundary( $value ) {
		$keys = array( 'raw_identity_data_exposed', 'raw_payment_data_exposed', 'raw_medical_data_exposed', 'raw_provider_payload_exposed', 'bearer_secret_exposed' );
		return self::exact_object( $value, $keys ) && false === $value['raw_identity_data_exposed'] && false === $value['raw_payment_data_exposed'] && false === $value['raw_medical_data_exposed'] && false === $value['raw_provider_payload_exposed'] && false === $value['bearer_secret_exposed'];
	}

	private static function privacy_safe( $value ) {
		$forbidden = '/^(?:bearer_token|token|secret|password|cvv|cvc|card_number|card_pan|pan|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|raw_provider_payload|raw_payment_data|payment_token|activation_code|iccid)$/i';
		if ( ! is_array( $value ) ) {
			return true;
		}
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && preg_match( $forbidden, $key ) ) {
				return false;
			}
			if ( ! self::privacy_safe( $child ) ) {
				return false;
			}
		}
		return true;
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_vip_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
