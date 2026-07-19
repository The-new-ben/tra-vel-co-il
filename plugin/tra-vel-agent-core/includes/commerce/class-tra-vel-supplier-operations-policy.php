<?php
/**
 * Fail-closed supplier onboarding, source revision, and readiness policy.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Supplier_Operations_Policy {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_SOURCE_AGE_SECONDS = 2592000;

	/**
	 * Validate one immutable supplier onboarding revision.
	 *
	 * No secrets, direct contact details, traveler data, or live claims are accepted
	 * as substitutes for opaque references and evidence digests.
	 *
	 * @return array|WP_Error
	 */
	public static function supplier_profile( $profile, $now = null ) {
		$keys = array(
			'contract_version', 'supplier_id', 'revision_id', 'revision_number', 'previous_revision_digest',
			'created_at', 'effective_at', 'environment', 'lifecycle_status', 'verticals', 'capability_claims',
			'relationship', 'credentials', 'endpoints', 'operation_support', 'escalation', 'licensing',
			'data_governance', 'attribution', 'settlement', 'source_controls', 'health', 'kill_switch',
			'readiness', 'commercial_truth', 'revision_control',
		);
		if ( self::contains_sensitive_material( $profile ) ) {
			return self::error( 'sensitive_material_rejected', 'Supplier contracts accept opaque references only, never raw secrets or personal data.' );
		}
		if ( ! self::exact_object( $profile, $keys ) || self::CONTRACT_VERSION !== $profile['contract_version'] ) {
			return self::error( 'profile_shape_invalid', 'The supplier profile is not the closed supported contract.' );
		}

		$now = self::clock( $now );
		if ( null === $now ) {
			return self::error( 'clock_invalid', 'The supplier readiness clock is invalid.' );
		}
		$digest_basis_profile = $profile;

		$supplier_id = sanitize_key( (string) $profile['supplier_id'] );
		$environment = sanitize_key( (string) $profile['environment'] );
		$lifecycle   = sanitize_key( (string) $profile['lifecycle_status'] );
		if ( ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $supplier_id ) || ! preg_match( '/^suprev_[a-z0-9]{12,64}$/', (string) $profile['revision_id'] ) || ! is_int( $profile['revision_number'] ) || $profile['revision_number'] < 1 || ! in_array( $environment, array( 'sandbox', 'live' ), true ) || ! in_array( $lifecycle, Tra_Vel_Supplier_Operations_Taxonomy::ONBOARDING_STATES, true ) ) {
			return self::error( 'profile_identity_invalid', 'The supplier identity, environment, or lifecycle is invalid.' );
		}
		$created_at   = self::utc_timestamp( $profile['created_at'] );
		$effective_at = self::utc_timestamp( $profile['effective_at'] );
		if ( null === $created_at || null === $effective_at || $created_at > $now || $effective_at < $created_at || ( in_array( $lifecycle, array( 'sandbox_ready', 'sandbox_active', 'live_ready', 'live_active' ), true ) && $effective_at > $now ) || ! self::nullable_digest( $profile['previous_revision_digest'] ) ) {
			return self::error( 'profile_revision_invalid', 'The supplier revision chronology or predecessor digest is invalid.' );
		}
		if ( ( 1 === $profile['revision_number'] && null !== $profile['previous_revision_digest'] ) || ( $profile['revision_number'] > 1 && ! self::is_digest( $profile['previous_revision_digest'] ) ) ) {
			return self::error( 'profile_revision_invalid', 'Supplier revision ancestry must be explicit and immutable.' );
		}

		$verticals = Tra_Vel_Supplier_Operations_Taxonomy::list_of( $profile['verticals'], Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS, 'supplier vertical' );
		if ( is_wp_error( $verticals ) ) {
			return $verticals;
		}
		$claim_result = self::capability_claims( $profile['capability_claims'], $verticals, $environment, $now );
		if ( is_wp_error( $claim_result ) ) {
			return $claim_result;
		}
		$capability_map = $claim_result['map'];
		$capabilities   = $claim_result['capabilities'];

		$relationship = self::relationship( $profile['relationship'], $environment, $now );
		if ( is_wp_error( $relationship ) ) {
			return $relationship;
		}
		if ( in_array( $lifecycle, array( 'sandbox_ready', 'sandbox_active', 'live_ready', 'live_active' ), true ) && 'signed' !== $relationship['agreement_status'] ) {
			return self::error( 'relationship_not_current', 'A ready or active supplier requires a signed current commercial agreement.' );
		}
		$relationship_model = $relationship['model'];
		$merchant_of_record = $relationship['merchant_of_record'];
		$capability_error = self::capability_integrity( $capability_map, $relationship_model, $merchant_of_record );
		if ( is_wp_error( $capability_error ) ) {
			return $capability_error;
		}

		$credentials = self::credentials( $profile['credentials'], $environment, $capabilities, $now );
		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}
		$endpoints = self::endpoints( $profile['endpoints'], $environment, $now );
		if ( is_wp_error( $endpoints ) ) {
			return $endpoints;
		}
		$operations = self::operation_support( $profile['operation_support'], $capabilities );
		if ( is_wp_error( $operations ) ) {
			return $operations;
		}
		$escalation = self::escalation( $profile['escalation'], $now );
		if ( is_wp_error( $escalation ) ) {
			return $escalation;
		}
		$licensing = self::licensing( $profile['licensing'], in_array( 'insurance', $verticals, true ), $environment, $now );
		if ( is_wp_error( $licensing ) ) {
			return $licensing;
		}
		$data_governance = self::data_governance( $profile['data_governance'] );
		if ( is_wp_error( $data_governance ) ) {
			return $data_governance;
		}
		$attribution = self::attribution( $profile['attribution'], $relationship_model, $capabilities );
		if ( is_wp_error( $attribution ) ) {
			return $attribution;
		}
		$settlement = self::settlement( $profile['settlement'], $relationship_model, $merchant_of_record );
		if ( is_wp_error( $settlement ) ) {
			return $settlement;
		}
		$source_controls = self::source_controls( $profile['source_controls'], $now );
		if ( is_wp_error( $source_controls ) ) {
			return $source_controls;
		}
		$health = self::health( $profile['health'], $now );
		if ( is_wp_error( $health ) ) {
			return $health;
		}
		$kill_switch = self::kill_switch( $profile['kill_switch'], $capabilities );
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}
		$revision_control = self::revision_control( $profile['revision_control'], $profile['revision_number'], $profile['previous_revision_digest'] );
		if ( is_wp_error( $revision_control ) ) {
			return $revision_control;
		}
		$truth = self::commercial_truth( $profile['commercial_truth'], $environment );
		if ( is_wp_error( $truth ) ) {
			return $truth;
		}
		$readiness = self::readiness( $profile['readiness'], $environment, $lifecycle, in_array( 'insurance', $verticals, true ), $credentials, $endpoints, $claim_result, $escalation, $licensing, $data_governance, $settlement, $source_controls, $health, $kill_switch, $now );
		if ( is_wp_error( $readiness ) ) {
			return $readiness;
		}

		$profile['supplier_id']       = $supplier_id;
		$profile['environment']       = $environment;
		$profile['lifecycle_status']  = $lifecycle;
		$profile['relationship']      = $relationship;
		$profile['credentials']       = $credentials;
		$profile['endpoints']         = $endpoints;
		$profile['operation_support'] = $operations;
		$profile['escalation']        = $escalation;
		$profile['licensing']         = $licensing;
		$profile['data_governance']   = $data_governance;
		$profile['attribution']       = $attribution;
		$profile['settlement']        = $settlement;
		$profile['source_controls']   = $source_controls;
		$profile['health']            = $health;
		$profile['kill_switch']       = $kill_switch;
		$profile['readiness']         = $readiness;
		$profile['commercial_truth']  = $truth;
		$profile['revision_control']  = $revision_control;
		if ( ! hash_equals( $profile['revision_control']['content_digest'], self::configuration_digest( $digest_basis_profile ) ) ) {
			return self::error( 'revision_content_digest_mismatch', 'The immutable supplier configuration no longer matches its bound content digest.' );
		}
		return $profile;
	}

	/**
	 * Validate an immutable product/rate/availability/terms/blackout source revision.
	 *
	 * @return array|WP_Error
	 */
	public static function inventory_revision( $revision, $now = null ) {
		$keys = array( 'contract_version', 'supplier_id', 'vertical', 'revision_id', 'revision_number', 'previous_revision_digest', 'state', 'environment', 'created_at', 'effective_at', 'valid_until', 'source', 'artifacts', 'revalidation', 'rollback', 'commercial_truth', 'data_boundary' );
		if ( self::contains_sensitive_material( $revision ) ) {
			return self::error( 'inventory_sensitive_material_rejected', 'Inventory revisions accept digests and opaque references only.' );
		}
		if ( ! self::exact_object( $revision, $keys ) || self::CONTRACT_VERSION !== $revision['contract_version'] ) {
			return self::error( 'inventory_shape_invalid', 'The inventory revision is not the closed supported contract.' );
		}
		$now = self::clock( $now );
		if ( null === $now ) {
			return self::error( 'clock_invalid', 'The inventory revision clock is invalid.' );
		}
		$supplier_id = sanitize_key( (string) $revision['supplier_id'] );
		$vertical    = Tra_Vel_Supplier_Operations_Taxonomy::token( $revision['vertical'], Tra_Vel_Supplier_Operations_Taxonomy::VERTICALS );
		$state       = sanitize_key( (string) $revision['state'] );
		$environment = sanitize_key( (string) $revision['environment'] );
		$created_at  = self::utc_timestamp( $revision['created_at'] );
		$effective   = self::utc_timestamp( $revision['effective_at'] );
		$valid_until = self::utc_timestamp( $revision['valid_until'] );
		if ( ! preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $supplier_id ) || '' === $vertical || ! preg_match( '/^invrev_[a-z0-9]{12,64}$/', (string) $revision['revision_id'] ) || ! is_int( $revision['revision_number'] ) || $revision['revision_number'] < 1 || ! in_array( $state, Tra_Vel_Supplier_Operations_Taxonomy::REVISION_STATES, true ) || ! in_array( $environment, array( 'sandbox', 'live' ), true ) || null === $created_at || null === $effective || null === $valid_until || $effective < $created_at || $valid_until <= $effective || ! self::nullable_digest( $revision['previous_revision_digest'] ) ) {
			return self::error( 'inventory_identity_invalid', 'The inventory revision identity, state, or validity window is invalid.' );
		}
		if ( ( 1 === $revision['revision_number'] && null !== $revision['previous_revision_digest'] ) || ( $revision['revision_number'] > 1 && ! self::is_digest( $revision['previous_revision_digest'] ) ) ) {
			return self::error( 'inventory_ancestry_invalid', 'Inventory revision ancestry is invalid.' );
		}
		if ( in_array( $state, array( 'certified', 'active' ), true ) && $valid_until <= $now ) {
			return self::error( 'inventory_stale', 'A certified or active inventory revision cannot contain an expired source window.' );
		}

		$source_keys = array( 'source_revision', 'source_digest', 'captured_at', 'channel' );
		$source = $revision['source'];
		if ( ! self::exact_object( $source, $source_keys ) || ! self::opaque_ref( $source['source_revision'], 'source' ) || ! self::is_digest( $source['source_digest'] ) || null === self::utc_timestamp( $source['captured_at'] ) || ! in_array( $source['channel'], array( 'api', 'feed', 'portal', 'manual_contract' ), true ) || self::utc_timestamp( $source['captured_at'] ) > $created_at ) {
			return self::error( 'inventory_source_invalid', 'The inventory source revision is invalid.' );
		}

		if ( ! is_array( $revision['artifacts'] ) || array_values( $revision['artifacts'] ) !== $revision['artifacts'] || ! $revision['artifacts'] ) {
			return self::error( 'inventory_artifacts_invalid', 'At least one closed inventory artifact is required.' );
		}
		$artifact_keys = array( 'product_ref', 'product_revision', 'product_digest', 'rate_revision', 'rate_digest', 'availability_revision', 'availability_digest', 'terms_revision', 'terms_digest', 'terms_effective_at', 'terms_valid_until', 'blackout_revision', 'blackout_digest' );
		$seen_products = array();
		foreach ( $revision['artifacts'] as $artifact ) {
			if ( ! self::exact_object( $artifact, $artifact_keys ) || ! self::opaque_ref( $artifact['product_ref'], 'product' ) || ! self::opaque_ref( $artifact['product_revision'], 'productrev' ) || ! self::opaque_ref( $artifact['rate_revision'], 'raterev' ) || ! self::opaque_ref( $artifact['availability_revision'], 'availrev' ) || ! self::opaque_ref( $artifact['terms_revision'], 'termsrev' ) || ! self::opaque_ref( $artifact['blackout_revision'], 'blackoutrev' ) || ! self::is_digest( $artifact['product_digest'] ) || ! self::is_digest( $artifact['rate_digest'] ) || ! self::is_digest( $artifact['availability_digest'] ) || ! self::is_digest( $artifact['terms_digest'] ) || ! self::is_digest( $artifact['blackout_digest'] ) ) {
				return self::error( 'inventory_artifact_invalid', 'An inventory artifact must bind every source family to one immutable revision and digest.' );
			}
			$terms_effective = self::utc_timestamp( $artifact['terms_effective_at'] );
			$terms_valid     = self::utc_timestamp( $artifact['terms_valid_until'] );
			if ( null === $terms_effective || null === $terms_valid || $terms_valid <= $terms_effective || ( in_array( $state, array( 'certified', 'active' ), true ) && $terms_valid <= $now ) ) {
				return self::error( 'inventory_terms_stale', 'Certified inventory cannot bind stale or invalid terms.' );
			}
			if ( isset( $seen_products[ $artifact['product_ref'] ] ) ) {
				return self::error( 'inventory_product_duplicate', 'An inventory revision cannot repeat a product reference.' );
			}
			$seen_products[ $artifact['product_ref'] ] = true;
		}

		$revalidation_keys = array( 'required', 'max_cache_age_seconds', 'last_verified_at', 'next_refresh_at', 'evidence_digest' );
		$revalidation = $revision['revalidation'];
		$last_verified = is_array( $revalidation ) && isset( $revalidation['last_verified_at'] ) ? self::utc_timestamp( $revalidation['last_verified_at'] ) : null;
		$next_refresh  = is_array( $revalidation ) && isset( $revalidation['next_refresh_at'] ) ? self::utc_timestamp( $revalidation['next_refresh_at'] ) : null;
		if ( ! self::exact_object( $revalidation, $revalidation_keys ) || true !== $revalidation['required'] || ! is_int( $revalidation['max_cache_age_seconds'] ) || $revalidation['max_cache_age_seconds'] < 0 || $revalidation['max_cache_age_seconds'] > self::MAX_SOURCE_AGE_SECONDS || null === $last_verified || null === $next_refresh || $next_refresh <= $last_verified || ! self::is_digest( $revalidation['evidence_digest'] ) || ( in_array( $state, array( 'certified', 'active' ), true ) && ( $now - $last_verified > $revalidation['max_cache_age_seconds'] || $next_refresh <= $now ) ) ) {
			return self::error( 'inventory_revalidation_invalid', 'Inventory revalidation evidence is missing or stale.' );
		}

		$rollback_keys = array( 'allowed', 'target_revision_digest', 'reason_code', 'requested_at' );
		$rollback = $revision['rollback'];
		if ( ! self::exact_object( $rollback, $rollback_keys ) || ! is_bool( $rollback['allowed'] ) || ! self::nullable_digest( $rollback['target_revision_digest'] ) || ! self::nullable_token( $rollback['reason_code'] ) || ! self::nullable_datetime( $rollback['requested_at'] ) ) {
			return self::error( 'inventory_rollback_invalid', 'The inventory rollback contract is invalid.' );
		}
		$rollback_requested = null !== $rollback['target_revision_digest'] || null !== $rollback['reason_code'] || null !== $rollback['requested_at'];
		if ( ( $rollback_requested && ( ! $rollback['allowed'] || ! self::is_digest( $rollback['target_revision_digest'] ) || ! self::nonempty_token( $rollback['reason_code'] ) || null === self::utc_timestamp( $rollback['requested_at'] ) ) ) || ( ! $rollback_requested && ( null !== $rollback['target_revision_digest'] || null !== $rollback['reason_code'] || null !== $rollback['requested_at'] ) ) ) {
			return self::error( 'inventory_rollback_invalid', 'Rollback requires an explicit target, reason, and timestamp.' );
		}

		$truth = self::commercial_truth( $revision['commercial_truth'], $environment );
		if ( is_wp_error( $truth ) ) {
			return $truth;
		}
		$data_boundary = $revision['data_boundary'];
		if ( ! self::exact_object( $data_boundary, array( 'contains_raw_secrets', 'contains_raw_pii', 'restricted_payload_refs_only' ) ) || false !== $data_boundary['contains_raw_secrets'] || false !== $data_boundary['contains_raw_pii'] || true !== $data_boundary['restricted_payload_refs_only'] ) {
			return self::error( 'inventory_data_boundary_invalid', 'Inventory revisions must contain references only and no raw secrets or PII.' );
		}

		$revision['supplier_id'] = $supplier_id;
		$revision['vertical']    = $vertical;
		$revision['state']       = $state;
		$revision['environment'] = $environment;
		$revision['commercial_truth'] = $truth;
		return $revision;
	}

	/**
	 * Produce a deterministic digest for immutable revision comparison.
	 */
	public static function canonical_digest( $value ) {
		$value = self::canonicalize( $value );
		return hash( 'sha256', wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Digest only immutable supplier configuration. Runtime lifecycle, health,
	 * kill-switch, and readiness projections are validated independently.
	 */
	public static function configuration_digest( $profile ) {
		if ( ! is_array( $profile ) ) {
			return '';
		}
		$basis = $profile;
		unset( $basis['lifecycle_status'], $basis['health'], $basis['kill_switch'], $basis['readiness'] );
		if ( isset( $basis['revision_control'] ) && is_array( $basis['revision_control'] ) ) {
			unset( $basis['revision_control']['content_digest'] );
		}
		return self::canonical_digest( $basis );
	}

	private static function capability_claims( $claims, $verticals, $environment, $now ) {
		$map = Tra_Vel_Supplier_Operations_Taxonomy::capability_map( $claims, $verticals );
		if ( is_wp_error( $map ) ) {
			return $map;
		}
		$keys = array( 'vertical', 'capability', 'certification_status', 'evidence_digests', 'certified_at', 'expires_at' );
		$all  = array();
		foreach ( $claims as $claim ) {
			if ( ! self::exact_object( $claim, $keys ) ) {
				return self::error( 'capability_claim_shape_invalid', 'A supplier capability claim is not closed.' );
			}
			$status = sanitize_key( (string) $claim['certification_status'] );
			if ( ! in_array( $status, Tra_Vel_Supplier_Operations_Taxonomy::CERTIFICATION_STATES, true ) || ! self::digest_list( $claim['evidence_digests'], true ) || ! self::nullable_datetime( $claim['certified_at'] ) || ! self::nullable_datetime( $claim['expires_at'] ) ) {
				return self::error( 'capability_certification_invalid', 'A capability certification claim is invalid.' );
			}
			$certified = in_array( $status, array( 'fixture_passed', 'sandbox_certified', 'live_certified' ), true );
			$certified_at = self::utc_timestamp( $claim['certified_at'] );
			$expires_at   = self::utc_timestamp( $claim['expires_at'] );
			if ( $certified && ( ! $claim['evidence_digests'] || null === $certified_at || $certified_at > $now || ( null !== $claim['expires_at'] && ( null === $expires_at || $expires_at <= $now || $expires_at <= $certified_at ) ) ) ) {
				return self::error( 'capability_evidence_missing', 'Certified capabilities require immutable evidence and a certification time.' );
			}
			if ( 'live' === $environment && 'live_certified' === $status && ( null === $expires_at || $expires_at <= $now ) ) {
				return self::error( 'capability_certification_expired', 'Live capability certification requires a current expiry.' );
			}
			$all[ $claim['capability'] ] = true;
		}
		$all = array_keys( $all );
		sort( $all, SORT_STRING );
		return array( 'map' => $map, 'capabilities' => $all, 'claims' => $claims );
	}

	private static function capability_integrity( $map, $relationship, $merchant_of_record ) {
		$payment_capabilities = array( 'payment_authorize', 'payment_capture', 'payment_void', 'payment_refund' );
		$affiliate_allowed = array( 'search', 'revalidate', 'webhook', 'reconcile', 'report_conversion', 'settlement_reconcile' );
		foreach ( $map as $vertical => $capabilities ) {
			if ( ! in_array( 'search', $capabilities, true ) ) {
				return self::error( 'capability_search_missing', 'Every supplier vertical must expose search before downstream capabilities.' );
			}
			foreach ( Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITY_DEPENDENCIES as $capability => $dependencies ) {
				if ( in_array( $capability, $capabilities, true ) && array_diff( $dependencies, $capabilities ) ) {
					return self::error( 'capability_dependency_missing', 'A supplier capability claim is missing a required predecessor.' );
				}
			}
			if ( 'package' !== $vertical && array_intersect( $payment_capabilities, $capabilities ) ) {
				return self::error( 'capability_vertical_impossible', 'Payment orchestration capabilities may only be claimed for the package/platform vertical.' );
			}
			if ( 'affiliate' === $relationship && array_diff( $capabilities, $affiliate_allowed ) ) {
				return self::error( 'capability_relationship_impossible', 'An affiliate relationship cannot claim reservation, fulfillment, or payment mutations.' );
			}
			if ( 'affiliate' !== $relationship && in_array( 'report_conversion', $capabilities, true ) ) {
				return self::error( 'capability_relationship_impossible', 'Conversion reporting belongs to an affiliate relationship.' );
			}
		}
		$all = array();
		foreach ( $map as $capabilities ) {
			$all = array_merge( $all, $capabilities );
		}
		if ( array_intersect( $payment_capabilities, $all ) && ! in_array( $merchant_of_record, array( 'platform', 'mixed' ), true ) ) {
			return self::error( 'capability_merchant_impossible', 'Payment capabilities require the platform to be a merchant of record for the applicable flow.' );
		}
		if ( 'affiliate' === $relationship && ( ! in_array( 'report_conversion', $all, true ) || ! in_array( 'settlement_reconcile', $all, true ) ) ) {
			return self::error( 'affiliate_capabilities_incomplete', 'Affiliate suppliers require conversion reporting and settlement reconciliation.' );
		}
		return true;
	}

	private static function relationship( $value, $environment, $now ) {
		$keys = array( 'model', 'legal_entity_ref', 'agreement_ref', 'agreement_digest', 'agreement_status', 'effective_at', 'expires_at', 'governing_jurisdiction', 'service_jurisdictions', 'merchant_of_record' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return self::error( 'relationship_shape_invalid', 'The supplier relationship block is not closed.' );
		}
		$model      = sanitize_key( (string) $value['model'] );
		$status     = sanitize_key( (string) $value['agreement_status'] );
		$merchant   = sanitize_key( (string) $value['merchant_of_record'] );
		$effective  = self::utc_timestamp( $value['effective_at'] );
		$expires_at = null === $value['expires_at'] ? null : self::utc_timestamp( $value['expires_at'] );
		if ( ! in_array( $model, Tra_Vel_Supplier_Operations_Taxonomy::RELATIONSHIP_MODELS, true ) || ! self::opaque_ref( $value['legal_entity_ref'], 'legal' ) || ! self::opaque_ref( $value['agreement_ref'], 'agreement' ) || ! self::is_digest( $value['agreement_digest'] ) || ! in_array( $status, array( 'draft', 'signed', 'expired', 'terminated' ), true ) || null === $effective || ( null !== $value['expires_at'] && null === $expires_at ) || ! self::jurisdiction( $value['governing_jurisdiction'] ) || ! self::jurisdiction_list( $value['service_jurisdictions'] ) || ! in_array( $merchant, array( 'supplier', 'platform', 'mixed', 'not_applicable' ), true ) ) {
			return self::error( 'relationship_invalid', 'The supplier legal or commercial relationship is invalid.' );
		}
		if ( 'signed' === $status && ( $effective > $now || ( null !== $expires_at && ( $expires_at <= $now || $expires_at <= $effective ) ) ) ) {
			return self::error( 'relationship_not_current', 'An active supplier agreement must be effective and current at the evaluation clock.' );
		}
		if ( 'sandbox' !== $environment && 'signed' !== $status ) {
			return self::error( 'relationship_not_current', 'A live supplier requires a current signed commercial agreement.' );
		}
		if ( 'affiliate' === $model && ! in_array( $merchant, array( 'supplier', 'not_applicable' ), true ) ) {
			return self::error( 'relationship_merchant_ambiguous', 'An affiliate relationship cannot claim platform merchant control.' );
		}
		$value['model']              = $model;
		$value['agreement_status']   = $status;
		$value['merchant_of_record'] = $merchant;
		return $value;
	}

	private static function credentials( $values, $environment, $capabilities, $now ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values ) {
			return self::error( 'credentials_invalid', 'Supplier credentials must be a closed list of vault references.' );
		}
		$keys = array( 'credential_ref', 'environment', 'status', 'scopes', 'issued_at', 'expires_at', 'last_rotated_at', 'evidence_digest' );
		$seen = array();
		$covered = array();
		foreach ( $values as $credential ) {
			if ( ! self::exact_object( $credential, $keys ) || ! self::opaque_ref( $credential['credential_ref'], 'credref' ) || ! in_array( $credential['environment'], array( 'sandbox', 'live' ), true ) || ! in_array( $credential['status'], array( 'missing', 'configured', 'rotation_due', 'revoked' ), true ) || ! self::nullable_datetime( $credential['issued_at'] ) || ! self::nullable_datetime( $credential['expires_at'] ) || ! self::nullable_datetime( $credential['last_rotated_at'] ) || ! self::nullable_digest( $credential['evidence_digest'] ) ) {
				return self::error( 'credential_reference_invalid', 'A supplier credential reference is invalid.' );
			}
			$scopes = Tra_Vel_Supplier_Operations_Taxonomy::list_of( $credential['scopes'], Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITIES, 'credential scope' );
			if ( is_wp_error( $scopes ) || isset( $seen[ $credential['credential_ref'] ] ) ) {
				return self::error( 'credential_reference_invalid', 'A supplier credential reference or scope is invalid or duplicated.' );
			}
			$seen[ $credential['credential_ref'] ] = true;
			$issued_at  = self::utc_timestamp( $credential['issued_at'] );
			$rotated_at = self::utc_timestamp( $credential['last_rotated_at'] );
			$expires_at = self::utc_timestamp( $credential['expires_at'] );
			if ( 'configured' === $credential['status'] && ( null === $issued_at || $issued_at > $now || null === $rotated_at || $rotated_at > $now || $rotated_at < $issued_at || ! self::is_digest( $credential['evidence_digest'] ) || ( null !== $credential['expires_at'] && ( null === $expires_at || $expires_at <= $now || $expires_at <= $issued_at ) ) ) ) {
				return self::error( 'credential_evidence_invalid', 'Configured supplier credentials require current rotation evidence.' );
			}
			if ( $environment === $credential['environment'] && 'configured' === $credential['status'] ) {
				foreach ( $scopes as $scope ) {
					$covered[ $scope ] = true;
				}
			}
			$credential['scopes'] = $scopes;
		}
		if ( 'live' === $environment && array_diff( $capabilities, array_keys( $covered ) ) ) {
			return self::error( 'live_credentials_incomplete', 'Live readiness requires current credential references for every declared capability.' );
		}
		return $values;
	}

	private static function endpoints( $value, $environment, $now ) {
		$keys = array( 'environment', 'allowed_hosts', 'tls_required', 'redirect_policy', 'webhook_source_hosts', 'certificate_evidence_digest', 'last_verified_at' );
		if ( ! self::exact_object( $value, $keys ) || $environment !== $value['environment'] || ! is_bool( $value['tls_required'] ) || ! in_array( $value['redirect_policy'], array( 'deny', 'canonical_same_host', 'allowlisted' ), true ) || ! self::nullable_digest( $value['certificate_evidence_digest'] ) || ! self::nullable_datetime( $value['last_verified_at'] ) ) {
			return self::error( 'endpoint_boundary_invalid', 'The supplier endpoint boundary is invalid.' );
		}
		$hosts = self::host_list( $value['allowed_hosts'] );
		$webhook_hosts = self::host_list( $value['webhook_source_hosts'] );
		if ( is_wp_error( $hosts ) || is_wp_error( $webhook_hosts ) ) {
			return self::error( 'endpoint_host_invalid', 'Supplier endpoints must use explicit non-wildcard host allowlists.' );
		}
		if ( null !== $value['last_verified_at'] && self::utc_timestamp( $value['last_verified_at'] ) > $now ) {
			return self::error( 'endpoint_verification_future', 'Supplier endpoint verification cannot occur in the future.' );
		}
		if ( 'live' === $environment && ( ! $hosts || true !== $value['tls_required'] || ! self::is_digest( $value['certificate_evidence_digest'] ) || null === self::utc_timestamp( $value['last_verified_at'] ) || self::utc_timestamp( $value['last_verified_at'] ) > $now ) ) {
			return self::error( 'live_endpoint_evidence_missing', 'Live endpoints require TLS, an allowlist, and verification evidence.' );
		}
		$value['allowed_hosts']       = $hosts;
		$value['webhook_source_hosts'] = $webhook_hosts;
		return $value;
	}

	private static function operation_support( $value, $capabilities ) {
		if ( ! self::exact_object( $value, Tra_Vel_Supplier_Operations_Taxonomy::OPERATION_LANES ) ) {
			return self::error( 'operation_support_shape_invalid', 'Every supplier operation lane requires a closed support contract.' );
		}
		$keys = array( 'supported', 'contact_route_ref', 'after_hours_route_ref', 'acknowledgement_sla_seconds', 'resolution_sla_seconds', 'reconciliation_sla_seconds', 'timezone', 'holiday_calendar_ref', 'evidence_digest' );
		foreach ( Tra_Vel_Supplier_Operations_Taxonomy::OPERATION_LANES as $lane ) {
			$contract = $value[ $lane ];
			if ( ! self::exact_object( $contract, $keys ) || ! is_bool( $contract['supported'] ) || ! self::opaque_ref( $contract['contact_route_ref'], 'route' ) || ! self::opaque_ref( $contract['after_hours_route_ref'], 'route' ) || ! self::positive_int( $contract['acknowledgement_sla_seconds'], 604800 ) || ! self::positive_int( $contract['resolution_sla_seconds'], 2592000 ) || ! self::positive_int( $contract['reconciliation_sla_seconds'], 2592000 ) || ! self::timezone( $contract['timezone'] ) || ! self::opaque_ref( $contract['holiday_calendar_ref'], 'calendar' ) || ! self::is_digest( $contract['evidence_digest'] ) ) {
				return self::error( 'operation_support_invalid', 'Supplier operation support requires SLAs, timezone, holiday calendar, evidence, and after-hours escalation.' );
			}
			$required = (bool) array_intersect( Tra_Vel_Supplier_Operations_Taxonomy::LANE_CAPABILITIES[ $lane ], $capabilities );
			if ( $required && ! $contract['supported'] ) {
				return self::error( 'operation_support_capability_mismatch', 'A declared supplier capability is missing its servicing and escalation lane.' );
			}
		}
		return $value;
	}

	private static function escalation( $value, $now ) {
		$keys = array( 'primary_route_ref', 'after_hours_route_ref', 'duty_manager_route_ref', 'coverage_model', 'timezone', 'holiday_calendar_ref', 'steps', 'last_drill_at', 'drill_evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! self::opaque_ref( $value['primary_route_ref'], 'route' ) || ! self::opaque_ref( $value['after_hours_route_ref'], 'route' ) || ! self::opaque_ref( $value['duty_manager_route_ref'], 'route' ) || ! in_array( $value['coverage_model'], array( '24x7', 'follow_the_sun', 'business_hours_with_on_call' ), true ) || ! self::timezone( $value['timezone'] ) || ! self::opaque_ref( $value['holiday_calendar_ref'], 'calendar' ) || ! self::nullable_datetime( $value['last_drill_at'] ) || ! self::nullable_digest( $value['drill_evidence_digest'] ) || ! is_array( $value['steps'] ) || array_values( $value['steps'] ) !== $value['steps'] || count( $value['steps'] ) < 3 ) {
			return self::error( 'escalation_invalid', 'Supplier escalation requires primary, after-hours, duty manager, timezone, holiday calendar, and tested steps.' );
		}
		$step_keys = array( 'sequence', 'delay_seconds', 'route_ref', 'scope' );
		$last_delay = -1;
		foreach ( $value['steps'] as $index => $step ) {
			if ( ! self::exact_object( $step, $step_keys ) || $step['sequence'] !== $index + 1 || ! is_int( $step['delay_seconds'] ) || $step['delay_seconds'] <= $last_delay || $step['delay_seconds'] > 604800 || ! self::opaque_ref( $step['route_ref'], 'route' ) || ! in_array( $step['scope'], array( 'operational', 'commercial', 'security', 'financial', 'regulated' ), true ) ) {
				return self::error( 'escalation_step_invalid', 'Supplier escalation steps must be ordered, bounded, and route-scoped.' );
			}
			$last_delay = $step['delay_seconds'];
		}
		if ( null !== $value['last_drill_at'] && self::utc_timestamp( $value['last_drill_at'] ) > $now ) {
			return self::error( 'escalation_drill_invalid', 'An escalation drill cannot occur in the future.' );
		}
		return $value;
	}

	private static function licensing( $value, $has_insurance, $environment, $now ) {
		$keys = array( 'status', 'jurisdictions', 'licence_reference_digest', 'verified_at', 'expires_at', 'regulated_contact_ref' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['status'], array( 'not_required', 'sandbox_only', 'verified', 'suspended', 'expired' ), true ) || ! self::nullable_digest( $value['licence_reference_digest'] ) || ! self::nullable_datetime( $value['verified_at'] ) || ! self::nullable_datetime( $value['expires_at'] ) || ( null !== $value['regulated_contact_ref'] && ! self::opaque_ref( $value['regulated_contact_ref'], 'route' ) ) ) {
			return self::error( 'licensing_invalid', 'The supplier licensing boundary is invalid.' );
		}
		$jurisdictions = $value['jurisdictions'];
		if ( ! is_array( $jurisdictions ) || array_values( $jurisdictions ) !== $jurisdictions || ( $jurisdictions && ! self::jurisdiction_list( $jurisdictions ) ) ) {
			return self::error( 'licensing_jurisdictions_invalid', 'Supplier licensing jurisdictions are invalid.' );
		}
		if ( $has_insurance && 'live' === $environment && ( 'verified' !== $value['status'] || ! $jurisdictions || ! self::is_digest( $value['licence_reference_digest'] ) || null === self::utc_timestamp( $value['verified_at'] ) || null === self::utc_timestamp( $value['expires_at'] ) || self::utc_timestamp( $value['expires_at'] ) <= $now || ! self::opaque_ref( $value['regulated_contact_ref'], 'route' ) ) ) {
			return self::error( 'insurance_licence_missing', 'A live insurance supplier requires current jurisdiction-specific licence evidence and a regulated escalation route.' );
		}
		if ( ! $has_insurance && 'not_required' !== $value['status'] ) {
			return self::error( 'licensing_scope_invalid', 'Non-insurance profiles cannot imply a regulated insurance licence.' );
		}
		return $value;
	}

	private static function data_governance( $value ) {
		$keys = array( 'retention_policy_ref', 'retention_policy_digest', 'retention_classes', 'minimum_necessary_enforced', 'log_redaction_enforced', 'data_residency_jurisdictions', 'deletion_sla_days', 'security_review_evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! self::opaque_ref( $value['retention_policy_ref'], 'policy' ) || ! self::is_digest( $value['retention_policy_digest'] ) || true !== $value['minimum_necessary_enforced'] || true !== $value['log_redaction_enforced'] || ! self::jurisdiction_list( $value['data_residency_jurisdictions'] ) || ! self::positive_int( $value['deletion_sla_days'], 3650 ) || ! self::is_digest( $value['security_review_evidence_digest'] ) || ! is_array( $value['retention_classes'] ) || array_values( $value['retention_classes'] ) !== $value['retention_classes'] || ! $value['retention_classes'] ) {
			return self::error( 'data_governance_invalid', 'Supplier data governance must be purpose-bound, redacted, retained by class, and evidence-backed.' );
		}
		$entry_keys = array( 'data_class', 'retention_days', 'purpose_ref' );
		$allowed = array( 'operations', 'financial', 'identity_reference', 'regulated_reference', 'security_audit' );
		$seen = array();
		foreach ( $value['retention_classes'] as $entry ) {
			if ( ! self::exact_object( $entry, $entry_keys ) || ! in_array( $entry['data_class'], $allowed, true ) || ! self::positive_int( $entry['retention_days'], 3650 ) || ! self::opaque_ref( $entry['purpose_ref'], 'purpose' ) || isset( $seen[ $entry['data_class'] ] ) ) {
				return self::error( 'retention_class_invalid', 'Supplier retention classes must be unique, bounded, and purpose-referenced.' );
			}
			$seen[ $entry['data_class'] ] = true;
		}
		return $value;
	}

	private static function attribution( $value, $relationship, $capabilities ) {
		$keys = array( 'mode', 'click_reference_required', 'conversion_reference_required', 'attribution_window_days', 'conversion_reporting_sla_hours', 'reversal_supported', 'evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['mode'], array( 'none', 'click', 'conversion', 'owned' ), true ) || ! is_bool( $value['click_reference_required'] ) || ! is_bool( $value['conversion_reference_required'] ) || ! is_int( $value['attribution_window_days'] ) || $value['attribution_window_days'] < 0 || $value['attribution_window_days'] > 365 || ! self::positive_int( $value['conversion_reporting_sla_hours'], 8760 ) || ! is_bool( $value['reversal_supported'] ) || ! self::is_digest( $value['evidence_digest'] ) ) {
			return self::error( 'attribution_invalid', 'The supplier attribution contract is invalid.' );
		}
		if ( 'affiliate' === $relationship && ( ! in_array( $value['mode'], array( 'click', 'conversion' ), true ) || ! $value['click_reference_required'] || ! $value['conversion_reference_required'] || ! $value['reversal_supported'] || ! in_array( 'report_conversion', $capabilities, true ) || ! in_array( 'settlement_reconcile', $capabilities, true ) ) ) {
			return self::error( 'affiliate_attribution_incomplete', 'Affiliate onboarding requires bounded click/conversion attribution, reversal, reporting, and reconciliation.' );
		}
		if ( 'affiliate' !== $relationship && in_array( $value['mode'], array( 'click', 'conversion' ), true ) ) {
			return self::error( 'attribution_relationship_ambiguous', 'Affiliate attribution cannot be attached to a non-affiliate relationship.' );
		}
		return $value;
	}

	private static function settlement( $value, $relationship, $merchant_of_record ) {
		$keys = array( 'model', 'currency', 'gross_basis', 'commission_bps', 'markup_authority', 'invoice_party', 'customer_funds_owner', 'supplier_payable_method', 'payout_route_ref', 'payout_lag_days', 'reconciliation_frequency', 'dispute_sla_hours', 'chargeback_owner', 'tax_owner', 'evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['model'], Tra_Vel_Supplier_Operations_Taxonomy::SETTLEMENT_MODELS, true ) || ! preg_match( '/^[A-Z]{3}$/', (string) $value['currency'] ) || ! in_array( $value['gross_basis'], array( 'retail_gross', 'supplier_net', 'commissionable_gross', 'not_applicable' ), true ) || ( null !== $value['commission_bps'] && ( ! is_int( $value['commission_bps'] ) || $value['commission_bps'] < 0 || $value['commission_bps'] > 10000 ) ) || ! in_array( $value['markup_authority'], array( 'platform', 'supplier', 'contract', 'not_applicable' ), true ) || ! in_array( $value['invoice_party'], array( 'supplier', 'platform', 'affiliate_network', 'not_applicable' ), true ) || ! in_array( $value['customer_funds_owner'], array( 'supplier', 'platform', 'not_applicable' ), true ) || ! in_array( $value['supplier_payable_method'], array( 'gross_less_commission', 'net_rate', 'affiliate_invoice', 'internal' ), true ) || ! self::opaque_ref( $value['payout_route_ref'], 'payout' ) || ! is_int( $value['payout_lag_days'] ) || $value['payout_lag_days'] < 0 || $value['payout_lag_days'] > 365 || ! in_array( $value['reconciliation_frequency'], array( 'per_transaction', 'daily', 'weekly', 'monthly' ), true ) || ! self::positive_int( $value['dispute_sla_hours'], 8760 ) || ! in_array( $value['chargeback_owner'], array( 'supplier', 'platform', 'shared', 'not_applicable' ), true ) || ! in_array( $value['tax_owner'], array( 'supplier', 'platform', 'shared', 'not_applicable' ), true ) || ! self::is_digest( $value['evidence_digest'] ) ) {
			return self::error( 'settlement_invalid', 'The supplier settlement contract is incomplete or ambiguous.' );
		}
		$valid = false;
		if ( 'owned' === $value['model'] ) {
			$valid = 'owned' === $relationship && null === $value['commission_bps'] && 'not_applicable' === $value['gross_basis'] && 'internal' === $value['supplier_payable_method'] && 'platform' === $value['customer_funds_owner'];
		} elseif ( 'commission' === $value['model'] ) {
			$valid = 'direct' === $relationship && is_int( $value['commission_bps'] ) && $value['commission_bps'] > 0 && in_array( $value['gross_basis'], array( 'retail_gross', 'commissionable_gross' ), true ) && 'gross_less_commission' === $value['supplier_payable_method'];
		} elseif ( 'net_rate' === $value['model'] ) {
			$valid = 'direct' === $relationship && null === $value['commission_bps'] && 'supplier_net' === $value['gross_basis'] && 'net_rate' === $value['supplier_payable_method'] && 'platform' === $value['markup_authority'];
		} elseif ( 'affiliate' === $value['model'] ) {
			$valid = 'affiliate' === $relationship && is_int( $value['commission_bps'] ) && $value['commission_bps'] > 0 && 'commissionable_gross' === $value['gross_basis'] && 'affiliate_invoice' === $value['supplier_payable_method'] && 'affiliate_network' === $value['invoice_party'] && in_array( $value['customer_funds_owner'], array( 'supplier', 'not_applicable' ), true );
		}
		if ( ! $valid || ( 'platform' === $merchant_of_record && 'platform' !== $value['customer_funds_owner'] ) || ( 'supplier' === $merchant_of_record && 'supplier' !== $value['customer_funds_owner'] ) ) {
			return self::error( 'settlement_model_ambiguous', 'Relationship, merchant, commission, net-rate, attribution, invoice, and funds ownership must agree.' );
		}
		return $value;
	}

	private static function source_controls( $value, $now ) {
		$keys = array( 'catalog_mode', 'product_revision_digest', 'rate_revision_digest', 'availability_revision_digest', 'terms_revision_digest', 'blackout_revision_digest', 'last_verified_at', 'terms_valid_until', 'max_cache_age_seconds', 'revalidation_required', 'source_evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['catalog_mode'], array( 'pull', 'push', 'hybrid' ), true ) || ! self::is_digest( $value['product_revision_digest'] ) || ! self::is_digest( $value['rate_revision_digest'] ) || ! self::is_digest( $value['availability_revision_digest'] ) || ! self::is_digest( $value['terms_revision_digest'] ) || ! self::is_digest( $value['blackout_revision_digest'] ) || ! self::is_digest( $value['source_evidence_digest'] ) || ! is_int( $value['max_cache_age_seconds'] ) || $value['max_cache_age_seconds'] < 0 || $value['max_cache_age_seconds'] > self::MAX_SOURCE_AGE_SECONDS || true !== $value['revalidation_required'] ) {
			return self::error( 'source_controls_invalid', 'Supplier product, rate, availability, terms, and blackout sources require immutable revisions and revalidation.' );
		}
		$last_verified = self::utc_timestamp( $value['last_verified_at'] );
		$terms_valid   = self::utc_timestamp( $value['terms_valid_until'] );
		if ( null === $last_verified || null === $terms_valid || $last_verified > $now || $terms_valid <= $now || $now - $last_verified > $value['max_cache_age_seconds'] ) {
			return self::error( 'source_terms_stale', 'Supplier source evidence or commercial terms are stale.' );
		}
		return $value;
	}

	private static function health( $value, $now ) {
		$keys = array( 'state', 'failure_threshold', 'open_interval_seconds', 'half_open_probe_limit', 'last_probe_at', 'last_success_at', 'telemetry_evidence_digest' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['state'], Tra_Vel_Supplier_Operations_Taxonomy::HEALTH_STATES, true ) || ! self::positive_int( $value['failure_threshold'], 1000 ) || ! self::positive_int( $value['open_interval_seconds'], 604800 ) || ! self::positive_int( $value['half_open_probe_limit'], 100 ) || ! self::nullable_datetime( $value['last_probe_at'] ) || ! self::nullable_datetime( $value['last_success_at'] ) || ! self::nullable_digest( $value['telemetry_evidence_digest'] ) ) {
			return self::error( 'health_invalid', 'The supplier circuit-breaker health contract is invalid.' );
		}
		if ( in_array( $value['state'], array( 'healthy', 'degraded', 'half_open' ), true ) && ! self::is_digest( $value['telemetry_evidence_digest'] ) ) {
			return self::error( 'health_evidence_missing', 'Operational health requires telemetry evidence.' );
		}
		$last_probe   = self::utc_timestamp( $value['last_probe_at'] );
		$last_success = self::utc_timestamp( $value['last_success_at'] );
		if ( ( null !== $last_probe && $last_probe > $now ) || ( null !== $last_success && $last_success > $now ) || ( null !== $last_probe && null !== $last_success && $last_success > $last_probe ) ) {
			return self::error( 'health_clock_invalid', 'Supplier health observations cannot occur in the future or after their probe.' );
		}
		return $value;
	}

	private static function kill_switch( $value, $capabilities ) {
		$keys = array( 'state', 'blocked_capabilities', 'reason_code', 'activated_at', 'activated_by_ref' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['state'], array( 'armed', 'engaged', 'released' ), true ) || ! is_array( $value['blocked_capabilities'] ) || array_values( $value['blocked_capabilities'] ) !== $value['blocked_capabilities'] || ! self::nullable_token( $value['reason_code'] ) || ! self::nullable_datetime( $value['activated_at'] ) || ( null !== $value['activated_by_ref'] && ! self::opaque_ref( $value['activated_by_ref'], 'actor' ) ) ) {
			return self::error( 'kill_switch_invalid', 'The supplier kill-switch contract is invalid.' );
		}
		$blocked = array();
		if ( $value['blocked_capabilities'] ) {
			$blocked = Tra_Vel_Supplier_Operations_Taxonomy::list_of( $value['blocked_capabilities'], Tra_Vel_Supplier_Operations_Taxonomy::CAPABILITIES, 'kill-switch capability' );
			if ( is_wp_error( $blocked ) || array_diff( $blocked, $capabilities ) ) {
				return self::error( 'kill_switch_scope_invalid', 'The kill switch may block only declared capabilities.' );
			}
		}
		if ( 'engaged' === $value['state'] ) {
			if ( ! $blocked || ! self::nonempty_token( $value['reason_code'] ) || null === self::utc_timestamp( $value['activated_at'] ) || ! self::opaque_ref( $value['activated_by_ref'], 'actor' ) ) {
				return self::error( 'kill_switch_evidence_missing', 'An engaged kill switch requires scope, reason, time, and actor evidence.' );
			}
		} elseif ( $blocked || null !== $value['reason_code'] || null !== $value['activated_at'] || null !== $value['activated_by_ref'] ) {
			return self::error( 'kill_switch_state_ambiguous', 'An inactive kill switch cannot retain active blocking instructions.' );
		}
		$value['blocked_capabilities'] = $blocked;
		return $value;
	}

	private static function readiness( $value, $environment, $lifecycle, $has_insurance, $credentials, $endpoints, $claim_result, $escalation, $licensing, $data_governance, $settlement, $source_controls, $health, $kill_switch, $now ) {
		$keys = array( 'requested_mode', 'decision', 'gates', 'evidence_digests', 'decided_at' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['requested_mode'], array( 'sandbox', 'live' ), true ) || ! in_array( $value['decision'], array( 'draft', 'sandbox_ready', 'live_ready', 'suspended', 'disabled' ), true ) || ! self::exact_object( $value['gates'], Tra_Vel_Supplier_Operations_Taxonomy::READINESS_GATES ) || ! self::digest_list( $value['evidence_digests'], true ) || ! self::nullable_datetime( $value['decided_at'] ) || $value['requested_mode'] !== $environment ) {
			return self::error( 'readiness_invalid', 'The supplier readiness decision is invalid.' );
		}
		foreach ( $value['gates'] as $gate => $status ) {
			if ( ! in_array( $status, array( 'pending', 'pass', 'not_applicable', 'fail' ), true ) ) {
				return self::error( 'readiness_gate_invalid', 'A supplier readiness gate has an unsupported result.' );
			}
			if ( 'licensing' !== $gate && 'not_applicable' === $status ) {
				return self::error( 'readiness_gate_invalid', 'Only an out-of-scope licensing gate may be not applicable.' );
			}
		}
		$ready_decision = in_array( $value['decision'], array( 'sandbox_ready', 'live_ready' ), true );
		if ( $ready_decision ) {
			if ( ! $value['evidence_digests'] || null === self::utc_timestamp( $value['decided_at'] ) || self::utc_timestamp( $value['decided_at'] ) > $now ) {
				return self::error( 'readiness_evidence_missing', 'A ready supplier requires current readiness evidence.' );
			}
			foreach ( $value['gates'] as $gate => $status ) {
				$allowed = 'licensing' === $gate && ! $has_insurance ? array( 'pass', 'not_applicable' ) : array( 'pass' );
				if ( ! in_array( $status, $allowed, true ) ) {
					return self::error( 'readiness_gate_failed', 'Every applicable supplier readiness gate must pass.' );
				}
			}
			if ( null === self::utc_timestamp( $escalation['last_drill_at'] ) || ! self::is_digest( $escalation['drill_evidence_digest'] ) || ! $data_governance['minimum_necessary_enforced'] || ! $data_governance['log_redaction_enforced'] || ! self::is_digest( $settlement['evidence_digest'] ) || ! self::is_digest( $source_controls['source_evidence_digest'] ) ) {
				return self::error( 'readiness_operational_evidence_missing', 'Ready suppliers require drilled escalation, data controls, settlement, and source evidence.' );
			}
		}
		if ( 'sandbox_ready' === $value['decision'] ) {
			if ( 'sandbox' !== $environment || ! in_array( $lifecycle, array( 'sandbox_ready', 'sandbox_active' ), true ) ) {
				return self::error( 'sandbox_readiness_invalid', 'Sandbox readiness must match the sandbox lifecycle.' );
			}
			foreach ( $claim_result['claims'] as $claim ) {
				if ( ! in_array( $claim['certification_status'], array( 'fixture_passed', 'sandbox_certified', 'live_certified' ), true ) ) {
					return self::error( 'sandbox_certification_incomplete', 'Sandbox readiness requires evidence for every declared capability.' );
				}
			}
		}
		if ( 'live_ready' === $value['decision'] ) {
			if ( 'live' !== $environment || ! in_array( $lifecycle, array( 'live_ready', 'live_active' ), true ) || ! $credentials || ! $endpoints['allowed_hosts'] || true !== $endpoints['tls_required'] ) {
				return self::error( 'live_readiness_invalid', 'Live readiness requires the live lifecycle, credentials, and verified endpoints.' );
			}
			foreach ( $claim_result['claims'] as $claim ) {
				if ( 'live_certified' !== $claim['certification_status'] || ! $claim['evidence_digests'] || null === self::utc_timestamp( $claim['expires_at'] ) || self::utc_timestamp( $claim['expires_at'] ) <= $now ) {
					return self::error( 'live_certification_incomplete', 'Every live capability requires current vertical-specific certification evidence.' );
				}
			}
			if ( $has_insurance && 'verified' !== $licensing['status'] ) {
				return self::error( 'live_insurance_not_licensed', 'Live insurance readiness requires verified licensing.' );
			}
		}
		return $value;
	}

	private static function revision_control( $value, $revision_number, $previous_digest ) {
		$keys = array( 'immutable', 'content_digest', 'supersedes_revision_digest', 'rollback_target_digest', 'rollback_reason_code' );
		if ( ! self::exact_object( $value, $keys ) || true !== $value['immutable'] || ! self::is_digest( $value['content_digest'] ) || ! self::nullable_digest( $value['supersedes_revision_digest'] ) || ! self::nullable_digest( $value['rollback_target_digest'] ) || ! self::nullable_token( $value['rollback_reason_code'] ) ) {
			return self::error( 'revision_control_invalid', 'Supplier revisions must be immutable, digested, and ancestry-bound.' );
		}
		if ( ( 1 === $revision_number && null !== $value['supersedes_revision_digest'] ) || ( $revision_number > 1 && $value['supersedes_revision_digest'] !== $previous_digest ) ) {
			return self::error( 'revision_ancestry_mismatch', 'The supplier revision predecessor and supersedes digest must agree.' );
		}
		$has_rollback_target = null !== $value['rollback_target_digest'];
		if ( $has_rollback_target !== ( null !== $value['rollback_reason_code'] ) || ( $has_rollback_target && ( $value['rollback_target_digest'] === $value['content_digest'] || ! self::nonempty_token( $value['rollback_reason_code'] ) ) ) ) {
			return self::error( 'revision_rollback_invalid', 'Rollback requires a distinct immutable target and explicit reason.' );
		}
		return $value;
	}

	private static function commercial_truth( $value, $environment ) {
		if ( ! self::exact_object( $value, array( 'simulated', 'real_booking', 'real_charge' ) ) || ! is_bool( $value['simulated'] ) || ! is_bool( $value['real_booking'] ) || ! is_bool( $value['real_charge'] ) ) {
			return self::error( 'commercial_truth_invalid', 'The supplier commercial truth boundary is invalid.' );
		}
		if ( 'sandbox' === $environment && ( true !== $value['simulated'] || $value['real_booking'] || $value['real_charge'] ) ) {
			return self::error( 'sandbox_truth_invalid', 'Sandbox supplier records cannot claim real bookings or charges.' );
		}
		if ( 'live' === $environment && true === $value['simulated'] ) {
			return self::error( 'live_truth_invalid', 'A live supplier profile cannot be labelled simulated.' );
		}
		return $value;
	}

	private static function contains_sensitive_material( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				$child_key = (string) $child_key;
				if ( preg_match( '/(?:^|_)(?:api_?key|secret|password|bearer|access_?token|refresh_?token|private_?key|cvv|cvc|card_?number|passport|medical|email|phone|traveler_?name|full_?name)(?:$|_)/i', $child_key ) || self::contains_sensitive_material( $child, $child_key ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		if ( preg_match( '/-----BEGIN [A-Z ]*PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/=\-]{8,}|\bsk-[A-Za-z0-9_-]{12,}/i', $value ) || preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value ) ) {
			return true;
		}
		$digits = preg_replace( '/\D+/', '', $value );
		return is_string( $digits ) && preg_match( '/^\+?[0-9 ()\-]{8,20}$/', $value ) && strlen( $digits ) >= 8;
	}

	private static function host_list( $hosts ) {
		if ( ! is_array( $hosts ) || array_values( $hosts ) !== $hosts ) {
			return self::error( 'host_list_invalid', 'Endpoint hosts must be a closed list.' );
		}
		$clean = array();
		foreach ( $hosts as $host ) {
			$host = strtolower( trim( (string) $host ) );
			if ( ! preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host ) || false !== strpos( $host, '*' ) || false !== strpos( $host, '/' ) || 'localhost' === $host ) {
				return self::error( 'host_invalid', 'An endpoint host is not a safe explicit hostname.' );
			}
			$clean[ $host ] = true;
		}
		$clean = array_keys( $clean );
		sort( $clean, SORT_STRING );
		return $clean;
	}

	private static function jurisdiction_list( $values ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ! $values ) {
			return false;
		}
		$seen = array();
		foreach ( $values as $value ) {
			if ( ! self::jurisdiction( $value ) || isset( $seen[ $value ] ) ) {
				return false;
			}
			$seen[ $value ] = true;
		}
		return true;
	}

	private static function jurisdiction( $value ) {
		return is_string( $value ) && ( 'GLOBAL' === $value || 1 === preg_match( '/^[A-Z]{2}(?:-[A-Z0-9]{1,3})?$/', $value ) );
	}

	private static function digest_list( $values, $allow_empty ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( ! $allow_empty && ! $values ) ) {
			return false;
		}
		$seen = array();
		foreach ( $values as $value ) {
			if ( ! self::is_digest( $value ) || isset( $seen[ $value ] ) ) {
				return false;
			}
			$seen[ $value ] = true;
		}
		return true;
	}

	private static function exact_object( $value, $keys ) {
		return is_array( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private static function opaque_ref( $value, $prefix ) {
		return is_string( $value ) && 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '_[a-z0-9][a-z0-9_-]{7,95}$/', $value );
	}

	private static function is_digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function nullable_digest( $value ) {
		return null === $value || self::is_digest( $value );
	}

	private static function nullable_datetime( $value ) {
		return null === $value || null !== self::utc_timestamp( $value );
	}

	private static function nullable_token( $value ) {
		return null === $value || self::nonempty_token( $value );
	}

	private static function nonempty_token( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $value );
	}

	private static function positive_int( $value, $max ) {
		return is_int( $value ) && $value > 0 && $value <= $max;
	}

	private static function timezone( $value ) {
		return is_string( $value ) && in_array( $value, DateTimeZone::listIdentifiers(), true );
	}

	private static function utc_timestamp( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+\-](\d{2}):(\d{2}))$/', $value, $parts ) ) {
			return null;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59 || ( isset( $parts[9] ) && '' !== $parts[9] && (int) $parts[9] > 23 ) || ( isset( $parts[10] ) && '' !== $parts[10] && (int) $parts[10] > 59 ) ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value ) )->getTimestamp();
		} catch ( Exception $error ) {
			return null;
		}
	}

	private static function clock( $value ) {
		if ( null === $value ) {
			return time();
		}
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		return self::utc_timestamp( $value );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_supplier_operations_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
