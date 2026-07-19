<?php
/**
 * Closed, non-transactional policy for sourced assisted proposals.
 *
 * This class validates immutable proposal revisions before publication. It
 * deliberately cannot represent a reservation, payment, booking, insurance
 * binding, or ticket issuance outcome.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Assisted_Proposal_Policy {
	const CONTRACT_VERSION        = '1.0.0';
	const SOURCE_CONTRACT_VERSION = '1.0.0';
	const MIN_PUBLICATION_TTL      = 900;
	const MAX_PUBLICATION_TTL      = 604800;
	const MAX_AMOUNT_MINOR         = 1000000000000;
	const FINAL_QUOTE_DISCLOSURE   = 'Final price, availability, and terms are provided only after revalidation in a personal quote.';

	/**
	 * @return string[]
	 */
	public static function positions() {
		return array( 'best_value', 'lowest_friction', 'most_flexible', 'most_memorable', 'custom' );
	}

	/**
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'draft', 'available', 'withdrawn', 'expired', 'superseded' );
	}

	/**
	 * @return string[]
	 */
	public static function traveler_dispositions() {
		return array( 'unavailable', 'awaiting_review', 'reviewed', 'changes_requested', 'contact_authorized', 'declined' );
	}

	/**
	 * Return the safe traveler actions that remain available for the current
	 * aggregate state. Commercial proposal revisions stay immutable; these
	 * actions advance only the separately versioned traveler disposition.
	 *
	 * @param string $status      Effective proposal status.
	 * @param string $disposition Current traveler disposition.
	 * @return string[]
	 */
	public static function traveler_actions_for( $status, $disposition ) {
		if ( 'available' !== $status ) {
			return array();
		}
		$actions = array(
			'awaiting_review' => array( 'review', 'request_changes', 'authorize_contact', 'decline' ),
			'reviewed'        => array( 'request_changes', 'authorize_contact', 'decline' ),
		);
		return $actions[ $disposition ] ?? array();
	}

	/**
	 * Resolve one legal traveler action to its next disposition.
	 *
	 * @param string $status      Effective proposal status.
	 * @param string $disposition Current traveler disposition.
	 * @param string $action      Requested action code.
	 * @return string|WP_Error
	 */
	public static function traveler_action_target( $status, $disposition, $action ) {
		$targets = array(
			'review'            => 'reviewed',
			'request_changes'   => 'changes_requested',
			'authorize_contact' => 'contact_authorized',
			'decline'           => 'declined',
		);
		$action  = (string) $action;
		$allowed = self::traveler_actions_for( $status, $disposition );
		if ( ! isset( $targets[ $action ] ) || ! in_array( $action, $allowed, true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_action_conflict', 'This traveler action is not available for the current proposal state.', array( 'status' => 409 ) );
		}
		return $targets[ $action ];
	}

	/**
	 * @return string[]
	 */
	public static function source_types() {
		return array( 'connected_api', 'supplier_portal', 'supplier_written_quote', 'public_supplier_page', 'official_information' );
	}

	/**
	 * @return string[]
	 */
	public static function source_relationships() {
		return array( 'operator_attested', 'public_reference' );
	}

	/**
	 * Server-owned public evidence registry. Integrations may extend this through
	 * a reviewed plugin filter; operator input alone can never declare a trusted
	 * public provider, relationship, or hostname.
	 *
	 * @return array<string,array>
	 */
	public static function public_source_provider_registry() {
		$registry = array(
			'booking'           => array( 'source_types' => array( 'public_supplier_page' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'booking.com' ) ),
			'expedia'           => array( 'source_types' => array( 'public_supplier_page' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'expedia.com' ) ),
			'issta'             => array( 'source_types' => array( 'public_supplier_page' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'issta.co.il' ) ),
			'el-al'             => array( 'source_types' => array( 'public_supplier_page', 'official_information' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'elal.com' ) ),
			'israel-government' => array( 'source_types' => array( 'official_information' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'gov.il' ) ),
			'iata'              => array( 'source_types' => array( 'official_information' ), 'relationships' => array( 'public_reference' ), 'hosts' => array( 'iata.org' ) ),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'tra_vel_assisted_proposal_public_source_providers', $registry );
			if ( is_array( $filtered ) ) {
				$registry = $filtered;
			}
		}
		return $registry;
	}

	/** @return true|WP_Error */
	public static function validate_public_provider_binding( $provider_code, $source_type, $relationship, $url ) {
		$provider_code = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $provider_code ) );
		$registry      = self::public_source_provider_registry();
		$definition    = is_array( $registry[ $provider_code ] ?? null ) ? $registry[ $provider_code ] : null;
		$parts         = self::is_safe_https_source_url( $url ) ? parse_url( $url ) : false;
		$host          = is_array( $parts ) ? strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) ) : '';
		if ( ! $definition || ! in_array( (string) $source_type, (array) ( $definition['source_types'] ?? array() ), true ) || ! in_array( (string) $relationship, (array) ( $definition['relationships'] ?? array() ), true ) || '' === $host ) {
			return new WP_Error( 'tra_vel_assisted_proposal_public_provider_untrusted', 'Public evidence must match a server-registered provider, source type, relationship, and hostname.' );
		}
		foreach ( (array) ( $definition['hosts'] ?? array() ) as $allowed_host ) {
			$allowed_host = strtolower( trim( (string) $allowed_host, ". \t\n\r\0\x0B" ) );
			if ( '' !== $allowed_host && ( $host === $allowed_host || substr( $host, -strlen( '.' . $allowed_host ) ) === '.' . $allowed_host ) ) {
				return true;
			}
		}
		return new WP_Error( 'tra_vel_assisted_proposal_public_provider_untrusted', 'The public evidence hostname is not registered for this provider.' );
	}

	/**
	 * @return string[]
	 */
	public static function categories() {
		return array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' );
	}

	/**
	 * @return string[]
	 */
	public static function currencies() {
		return array( 'ILS', 'USD', 'EUR' );
	}

	/**
	 * The maximum evidence freshness window is deliberately bounded by source
	 * class. These are revalidation deadlines, never inventory holds.
	 *
	 * @param string $source_type Source contract type.
	 * @return int Seconds.
	 */
	public static function source_max_ttl_seconds( $source_type ) {
		$limits = array(
			'connected_api'         => HOUR_IN_SECONDS,
			'supplier_portal'       => 6 * HOUR_IN_SECONDS,
			'supplier_written_quote' => 7 * DAY_IN_SECONDS,
			'public_supplier_page'  => HOUR_IN_SECONDS,
			'official_information'  => 7 * DAY_IN_SECONDS,
		);
		return isset( $limits[ $source_type ] ) ? (int) $limits[ $source_type ] : 0;
	}

	/**
	 * Validate an exact immutable revision before it becomes traveler-visible.
	 *
	 * @param array    $proposal Complete proposed traveler DTO.
	 * @param array    $sources  Immutable source rows for this revision.
	 * @param array    $context  Current case_active, case_revision and request_digest.
	 * @param int|null $now      Optional UTC epoch for deterministic tests.
	 * @return true|WP_Error
	 */
	public static function validate_publication( $proposal, $sources, $context, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_array( $proposal ) || ! is_array( $sources ) || ! is_array( $context ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_invalid', 'Proposal publication requires typed proposal, source, and case data.' );
		}

		$forbidden = self::reject_forbidden_fields( $proposal );
		if ( self::is_error( $forbidden ) ) {
			return $forbidden;
		}
		$forbidden = self::reject_forbidden_fields( $sources );
		if ( self::is_error( $forbidden ) ) {
			return $forbidden;
		}

		if ( self::CONTRACT_VERSION !== ( $proposal['contract_version'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contract_invalid', 'The assisted proposal contract version is not supported.' );
		}
		if ( 'available' !== ( $proposal['status'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_status_invalid', 'Only an available proposal representation can pass publication validation.' );
		}
		if ( ! in_array( $proposal['position'] ?? '', self::positions(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_position_invalid', 'The proposal position is not allowlisted.' );
		}
		if ( 'awaiting_review' !== ( $proposal['traveler_disposition'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_disposition_invalid', 'Initial publication must begin with traveler review awaiting.' );
		}
		$next_actions          = isset( $proposal['next_actions'] ) && is_array( $proposal['next_actions'] ) ? array_values( $proposal['next_actions'] ) : array();
		$expected_next_actions = array( 'review', 'request_changes', 'authorize_contact', 'decline' );
		sort( $next_actions, SORT_STRING );
		sort( $expected_next_actions, SORT_STRING );
		if ( $next_actions !== $expected_next_actions ) {
			return new WP_Error( 'tra_vel_assisted_proposal_next_actions_invalid', 'Initial publication must expose the exact four safe traveler actions.' );
		}
		if ( empty( $context['case_active'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_case_inactive', 'The parent assisted case is not active.' );
		}

		$addresses = isset( $proposal['addresses'] ) && is_array( $proposal['addresses'] ) ? $proposal['addresses'] : array();
		if ( (int) ( $addresses['case_revision'] ?? 0 ) < 1 || (int) ( $addresses['case_revision'] ?? 0 ) !== (int) ( $context['case_revision'] ?? 0 ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_case_revision_changed', 'The proposal was authored against another case revision.' );
		}
		$request_digest = (string) ( $addresses['request_digest'] ?? '' );
		if ( ! self::is_sha256( $request_digest ) || ! self::safe_hash_equals( $request_digest, (string) ( $context['request_digest'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_request_changed', 'The proposal was authored against another request digest.' );
		}
		$proposal_version = (int) ( $proposal['version'] ?? 0 );
		$revision         = (int) ( $proposal['revision'] ?? 0 );
		if ( $proposal_version < 1 || $revision < 1 || $proposal_version < $revision || (int) ( $proposal['published_revision'] ?? 0 ) !== $revision ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revision_invalid', 'Publication must bind the exact immutable proposal revision.' );
		}
		if ( ! self::is_uuid( $proposal['proposal_id'] ?? '' ) || ! self::is_uuid( $proposal['case_id'] ?? '' ) || ! preg_match( '/^TVP-[A-Z0-9]{8}(?:[A-Z0-9]{4})?$/', (string) ( $proposal['reference'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_identity_invalid', 'Proposal, case, and public reference identities are invalid.' );
		}

		if ( count( $sources ) < 1 || count( $sources ) > 32 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_sources_invalid', 'A proposal requires between one and 32 evidence sources.' );
		}
		$source_map       = array();
		$earliest_expiry  = null;
		$latest_observed  = 0;
		foreach ( $sources as $source ) {
			$valid = self::validate_source( $source, $now );
			if ( self::is_error( $valid ) ) {
				return $valid;
			}
			$source_id = (string) $source['source_id'];
			if ( isset( $source_map[ $source_id ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_duplicate', 'Proposal source identifiers must be unique.' );
			}
			$source_map[ $source_id ] = $source;
			$fresh_until = self::parse_datetime( $source['fresh_until'] );
			$observed_at = self::parse_datetime( $source['observed_at'] );
			$earliest_expiry = null === $earliest_expiry ? $fresh_until : min( $earliest_expiry, $fresh_until );
			$latest_observed = max( $latest_observed, $observed_at );
		}

		$proposal_sources = isset( $proposal['sources'] ) && is_array( $proposal['sources'] ) ? $proposal['sources'] : array();
		$source_digest    = self::source_set_digest( $sources );
		if ( ! self::safe_hash_equals( $source_digest, self::source_set_digest( $proposal_sources ) ) || ! self::safe_hash_equals( $source_digest, (string) ( $proposal['source_set_digest'] ?? '' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_set_changed', 'The proposal source set does not match its immutable evidence digest.' );
		}

		$components = isset( $proposal['components'] ) && is_array( $proposal['components'] ) ? $proposal['components'] : array();
		if ( count( $components ) < 1 || count( $components ) > 16 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_components_invalid', 'A proposal requires between one and 16 components.' );
		}
		$component_keys = array();
		$needs_unpriced = false;
		$needs_taxes    = false;
		$needs_fees     = false;
		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_component_invalid', 'Every proposal component must be a typed object.' );
			}
			$key = (string) ( $component['component_key'] ?? '' );
			if ( ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $key ) || isset( $component_keys[ $key ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_component_key_invalid', 'Proposal component keys must be unique stable identifiers.' );
			}
			$component_keys[ $key ] = true;
			if ( ! in_array( $component['category'] ?? '', self::categories(), true ) || true !== ( $component['requires_revalidation'] ?? null ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_component_policy_invalid', 'Proposal components must use an allowlisted category and require revalidation.' );
			}
			$conditions = isset( $component['conditions'] ) && is_array( $component['conditions'] ) ? $component['conditions'] : array();
			foreach ( array( 'cancellation', 'changes', 'baggage_or_inclusions' ) as $condition_key ) {
				if ( ! is_string( $conditions[ $condition_key ] ?? null ) || '' === trim( $conditions[ $condition_key ] ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_conditions_incomplete', 'Every component must disclose cancellation, change, and inclusion conditions.' );
				}
			}

			$submitted_source_ids = isset( $component['source_ids'] ) && is_array( $component['source_ids'] ) ? array_values( $component['source_ids'] ) : array();
			foreach ( $submitted_source_ids as $submitted_source_id ) {
				if ( ! self::is_uuid( $submitted_source_id ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_component_source_invalid', 'Component evidence identifiers must be UUIDs.' );
				}
			}
			$source_ids           = array_values( array_unique( $submitted_source_ids ) );
			if ( ! $source_ids ) {
				return new WP_Error( 'tra_vel_assisted_proposal_component_unsourced', 'Every proposal component requires evidence.' );
			}
			if ( count( $source_ids ) !== count( $submitted_source_ids ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_component_source_duplicate', 'Component source identifiers must be unique.' );
			}
			$has_commercial_source = false;
			foreach ( $source_ids as $source_id ) {
				if ( ! isset( $source_map[ $source_id ] ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_source_missing', 'A component references evidence outside this immutable revision.' );
				}
				if ( 'official_information' !== $source_map[ $source_id ]['source_type'] ) {
					$has_commercial_source = true;
				}
			}
			$price = isset( $component['price'] ) && is_array( $component['price'] ) ? $component['price'] : array();
			if ( true === ( $price['priced'] ?? null ) && ! $has_commercial_source ) {
				return new WP_Error( 'tra_vel_assisted_proposal_price_unsourced', 'A priced component requires commercial evidence, not only general information.' );
			}
			if ( false === ( $price['priced'] ?? null ) ) {
				$needs_unpriced = true;
			} else {
				$needs_taxes = $needs_taxes || 'included' !== ( $price['taxes'] ?? '' );
				$needs_fees  = $needs_fees || 'included' !== ( $price['fees'] ?? '' );
			}
		}

		$itinerary = isset( $proposal['itinerary'] ) && is_array( $proposal['itinerary'] ) ? $proposal['itinerary'] : array();
		if ( count( $itinerary ) < 1 || count( $itinerary ) > 31 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_itinerary_invalid', 'A published proposal requires a bounded itinerary.' );
		}
		foreach ( $itinerary as $day ) {
			$day_keys = is_array( $day ) && is_array( $day['component_keys'] ?? null ) ? array_values( $day['component_keys'] ) : array();
			foreach ( $day_keys as $day_key ) {
				if ( ! is_string( $day_key ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_itinerary_component_missing', 'The itinerary references a component outside this immutable revision.' );
				}
			}
			if ( count( $day_keys ) !== count( array_unique( $day_keys ) ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_itinerary_component_duplicate', 'Itinerary component references must be unique within a day.' );
			}
			foreach ( $day_keys as $day_key ) {
				if ( ! is_string( $day_key ) || ! isset( $component_keys[ $day_key ] ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_itinerary_component_missing', 'The itinerary references a component outside this immutable revision.' );
				}
			}
		}

		$unresolved       = isset( $proposal['unresolved_items'] ) && is_array( $proposal['unresolved_items'] ) ? $proposal['unresolved_items'] : array();
		$unresolved_codes = array();
		$allowed_unresolved = array( 'unpriced_component', 'taxes_unknown', 'fees_unknown', 'availability_revalidation', 'policy_revalidation', 'schedule_revalidation', 'other' );
		foreach ( $unresolved as $item ) {
			$code = is_array( $item ) ? (string) ( $item['code'] ?? '' ) : '';
			$label = is_array( $item ) ? (string) ( $item['label'] ?? '' ) : '';
			if ( ! in_array( $code, $allowed_unresolved, true ) || '' === trim( $label ) || isset( $unresolved_codes[ $code ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_unresolved_duplicate', 'Unresolved item codes must be present and unique.' );
			}
			$unresolved_codes[ $code ] = true;
		}
		$required_unresolved = array( 'availability_revalidation' );
		if ( $needs_unpriced ) {
			$required_unresolved[] = 'unpriced_component';
		}
		if ( $needs_taxes ) {
			$required_unresolved[] = 'taxes_unknown';
		}
		if ( $needs_fees ) {
			$required_unresolved[] = 'fees_unknown';
		}
		foreach ( $required_unresolved as $required_code ) {
			if ( ! isset( $unresolved_codes[ $required_code ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_gap_undisclosed', 'The proposal does not disclose every incomplete ledger or availability class.' );
			}
		}

		$computed_ledger = self::compute_ledger( $components );
		if ( self::is_error( $computed_ledger ) ) {
			return $computed_ledger;
		}
		$submitted_ledger = isset( $proposal['ledger'] ) && is_array( $proposal['ledger'] ) ? $proposal['ledger'] : array();
		if ( ! self::safe_hash_equals( self::canonical_digest( $computed_ledger ), self::canonical_digest( $submitted_ledger ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_ledger_changed', 'The proposal ledger must be computed by the server from exact component totals.' );
		}

		$freshness = isset( $proposal['freshness'] ) && is_array( $proposal['freshness'] ) ? $proposal['freshness'] : array();
		$checked_at = self::parse_datetime( $freshness['checked_at'] ?? '' );
		$expires_at = self::parse_datetime( $freshness['expires_at'] ?? '' );
		$top_expiry = self::parse_datetime( $proposal['expires_at'] ?? '' );
		$published_at = self::parse_datetime( $proposal['published_at'] ?? '' );
		if ( $checked_at < 1 || $expires_at < 1 || $top_expiry < 1 || $published_at < 1 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_freshness_invalid', 'Publication timestamps must be valid UTC date-times.' );
		}
		if ( $checked_at !== $latest_observed || $expires_at !== $top_expiry || $expires_at > $earliest_expiry ) {
			return new WP_Error( 'tra_vel_assisted_proposal_freshness_mismatch', 'Proposal freshness must be derived from its exact source observations.' );
		}
		if ( $published_at < $checked_at || $published_at > $expires_at ) {
			return new WP_Error( 'tra_vel_assisted_proposal_publication_order_invalid', 'Proposal publication must follow its latest evidence check and cannot exceed its expiry.' );
		}
		if ( $published_at > $now + 60 || $expires_at <= $now + self::MIN_PUBLICATION_TTL || $expires_at > $now + self::MAX_PUBLICATION_TTL ) {
			return new WP_Error( 'tra_vel_assisted_proposal_expiry_invalid', 'The proposal publication window is outside the allowed freshness boundary.' );
		}
		if ( true !== ( $freshness['requires_revalidation'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revalidation_required', 'Every assisted proposal must require final revalidation.' );
		}

		$disclosure = isset( $proposal['disclosure'] ) && is_array( $proposal['disclosure'] ) ? $proposal['disclosure'] : array();
		if ( 'non_binding_assisted_proposal' !== ( $disclosure['commercial_state'] ?? '' ) || true !== ( $disclosure['final_quote_required'] ?? null ) || self::FINAL_QUOTE_DISCLOSURE !== ( $disclosure['message'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_disclosure_invalid', 'The non-binding final-quote disclosure is required verbatim.' );
		}

		return true;
	}

	/**
	 * Validate one immutable source record and its revalidation deadline.
	 *
	 * @param array    $source Source DTO.
	 * @param int|null $now    Optional UTC epoch.
	 * @return true|WP_Error
	 */
	public static function validate_source( $source, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_array( $source ) || self::SOURCE_CONTRACT_VERSION !== ( $source['contract_version'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_contract_invalid', 'The proposal evidence source contract is invalid.' );
		}
		$forbidden = self::reject_forbidden_fields( $source );
		if ( self::is_error( $forbidden ) ) {
			return $forbidden;
		}
		if ( ! self::is_uuid( $source['source_id'] ?? '' ) || ! self::is_sha256( $source['evidence_digest'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_identity_invalid', 'Proposal evidence requires a UUID and SHA-256 digest.' );
		}
		$type = (string) ( $source['source_type'] ?? '' );
		if ( ! in_array( $type, self::source_types(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_type_invalid', 'The proposal evidence type is not allowlisted.' );
		}
		if ( ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', (string) ( $source['provider_code'] ?? '' ) ) || true !== ( $source['requires_revalidation'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_provider_invalid', 'Proposal evidence requires a bounded provider code and revalidation flag.' );
		}

		$observed_at = self::parse_datetime( $source['observed_at'] ?? '' );
		$fresh_until = self::parse_datetime( $source['fresh_until'] ?? '' );
		$max_ttl     = self::source_max_ttl_seconds( $type );
		if ( $observed_at < 1 || $fresh_until < 1 || $observed_at > $now + 60 || $fresh_until <= $now + self::MIN_PUBLICATION_TTL || $fresh_until <= $observed_at || $fresh_until - $observed_at > $max_ttl ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_stale', 'Proposal evidence is stale or exceeds its source freshness policy.' );
		}

		$url       = (string) ( $source['source_url'] ?? '' );
		$reference = (string) ( $source['source_reference'] ?? '' );
		if ( in_array( $type, array( 'public_supplier_page', 'official_information' ), true ) ) {
			if ( ! self::is_safe_https_source_url( $url ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_source_url_invalid', 'Public evidence requires a credential-free HTTPS source URL.' );
			}
			$provider_binding = self::validate_public_provider_binding( $source['provider_code'] ?? '', $type, $source['relationship'] ?? '', $url );
			if ( self::is_error( $provider_binding ) ) {
				return $provider_binding;
			}
		} elseif ( 'operator_attested' !== (string) ( $source['relationship'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_private_relationship_unverified', 'Manual private evidence must use the neutral operator-attested relationship.', array( 'status' => 400 ) );
		} elseif ( ! preg_match( '/^[A-Za-z0-9._:-]{1,190}$/', $reference ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_reference_invalid', 'Connected or written evidence requires a bounded opaque source reference.' );
		} elseif ( '' !== $url ) {
			return new WP_Error( 'tra_vel_assisted_proposal_private_source_url_forbidden', 'Connected, portal, and written evidence must use an opaque reference without a stored URL.' );
		}
		if ( '' !== $url && ! self::is_safe_https_source_url( $url ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_source_url_invalid', 'Evidence URLs must be credential-free HTTPS paths without query data.' );
		}

		return true;
	}

	/**
	 * Compute the one-currency, integer minor-unit ledger from components.
	 *
	 * @param array $components Proposal components.
	 * @return array|WP_Error
	 */
	public static function compute_ledger( $components ) {
		if ( ! is_array( $components ) || ! $components ) {
			return new WP_Error( 'tra_vel_assisted_proposal_ledger_empty', 'A proposal ledger requires components.' );
		}
		$total         = 0;
		$currency      = null;
		$priced_count  = 0;
		$unpriced_keys = array();
		$complete      = true;
		$seen_keys     = array();

		foreach ( $components as $component ) {
			$key = is_array( $component ) ? (string) ( $component['component_key'] ?? '' ) : '';
			if ( '' === $key || isset( $seen_keys[ $key ] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_ledger_key_invalid', 'Ledger component keys must be present and unique.' );
			}
			$seen_keys[ $key ] = true;
			$price = isset( $component['price'] ) && is_array( $component['price'] ) ? $component['price'] : array();
			if ( ! array_key_exists( 'priced', $price ) || ! is_bool( $price['priced'] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_price_state_invalid', 'Every component must explicitly declare whether it is priced.' );
			}
			if ( false === $price['priced'] ) {
				if ( null !== ( $price['total_for_party_minor'] ?? null ) || null !== ( $price['currency'] ?? null ) || 'not_priced' !== ( $price['basis'] ?? '' ) || 'unknown' !== ( $price['taxes'] ?? '' ) || 'unknown' !== ( $price['fees'] ?? '' ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_unpriced_amount_invalid', 'An unpriced component cannot carry a numeric amount or currency.' );
				}
				$unpriced_keys[] = $key;
				$complete = false;
				continue;
			}

			$amount = $price['total_for_party_minor'] ?? null;
			$code   = $price['currency'] ?? null;
			if ( ! is_int( $amount ) || $amount < 0 || $amount > self::MAX_AMOUNT_MINOR || ! in_array( $code, self::currencies(), true ) || ! in_array( $price['basis'] ?? '', array( 'trip_total', 'stay_total', 'ticket_total', 'activity_total', 'item_total' ), true ) || ! in_array( $price['taxes'] ?? '', array( 'included', 'excluded', 'unknown' ), true ) || ! in_array( $price['fees'] ?? '', array( 'included', 'excluded', 'unknown' ), true ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_amount_invalid', 'Priced components require a bounded integer minor-unit amount and supported currency.' );
			}
			if ( null !== $currency && $currency !== $code ) {
				return new WP_Error( 'tra_vel_assisted_proposal_currency_mixed', 'A proposal ledger cannot mix currencies.' );
			}
			if ( $amount > self::MAX_AMOUNT_MINOR - $total ) {
				return new WP_Error( 'tra_vel_assisted_proposal_total_overflow', 'The proposal total exceeds the supported minor-unit ceiling.' );
			}
			$currency = $code;
			$total += $amount;
			$priced_count++;
			if ( 'included' !== ( $price['taxes'] ?? '' ) || 'included' !== ( $price['fees'] ?? '' ) ) {
				$complete = false;
			}
		}

		sort( $unpriced_keys, SORT_STRING );
		$ledger = array(
			'contract_version'        => self::CONTRACT_VERSION,
			'currency'                => $currency,
			'priced_total_minor'      => $total,
			'priced_component_count'  => $priced_count,
			'unpriced_component_keys' => $unpriced_keys,
			'complete_pricing'        => $complete && $priced_count === count( $components ),
		);
		$ledger['calculation_digest'] = self::canonical_digest( $ledger );
		return $ledger;
	}

	/**
	 * Stable digest of a source set independent of input ordering.
	 *
	 * @param array $sources Source DTOs.
	 * @return string
	 */
	public static function source_set_digest( $sources ) {
		$sources = is_array( $sources ) ? array_values( $sources ) : array();
		usort(
			$sources,
			static function ( $left, $right ) {
				$left_id  = is_array( $left ) ? (string) ( $left['source_id'] ?? '' ) : '';
				$right_id = is_array( $right ) ? (string) ( $right['source_id'] ?? '' ) : '';
				return strcmp( $left_id, $right_id );
			}
		);
		return self::canonical_digest( $sources );
	}

	/**
	 * Return effective lifecycle truth even before a cleanup job persists expiry.
	 *
	 * @param string   $status     Stored status.
	 * @param string   $expires_at UTC date-time.
	 * @param int|null $now        Optional UTC epoch.
	 * @return string
	 */
	public static function effective_status( $status, $expires_at, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( 'available' === $status && self::parse_datetime( $expires_at ) <= $now ) {
			return 'expired';
		}
		return in_array( $status, self::statuses(), true ) ? $status : 'expired';
	}

	/**
	 * Draft history is append-only; terminal proposal heads require a new UUID.
	 *
	 * @param string $status Proposal head status.
	 * @return bool
	 */
	public static function can_append_revision( $status ) {
		return in_array( $status, array( 'draft', 'available' ), true );
	}

	/**
	 * Reject fields and exact values that would widen this contract into a sale.
	 *
	 * @param mixed  $value Data to inspect.
	 * @param string $path  Internal diagnostic path.
	 * @return true|WP_Error
	 */
	public static function reject_forbidden_fields( $value, $path = '$' ) {
		$forbidden_fields = array(
			'accepted', 'acceptance_status', 'reservation', 'reservation_id', 'reserved',
			'payment', 'payment_status', 'paid', 'checkout', 'order', 'order_id',
			'booking', 'booking_id', 'booked', 'confirmation', 'confirmation_number',
			'ticket', 'ticket_number', 'issued', 'policy_number', 'purchase', 'purchased',
			'savings', 'saving_amount', 'discount_amount', 'comparator_price',
		);
		$forbidden_values = array( 'accepted', 'reserved', 'paid', 'booked', 'confirmed', 'issued', 'purchased' );

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$normalized = strtolower( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized, $forbidden_fields, true ) ) {
					return new WP_Error( 'tra_vel_assisted_proposal_transactional_field_forbidden', 'Transactional or comparative field is forbidden at ' . $path . '.' . $normalized . '.' );
				}
				$result = self::reject_forbidden_fields( $child, $path . '.' . $normalized );
				if ( self::is_error( $result ) ) {
					return $result;
				}
			}
		} elseif ( is_string( $value ) && in_array( strtolower( trim( $value ) ), $forbidden_values, true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_transactional_value_forbidden', 'Transactional outcome value is forbidden at ' . $path . '.' );
		}
		return true;
	}

	/**
	 * @param mixed $value Any JSON-compatible value.
	 * @return string
	 */
	public static function canonical_digest( $value ) {
		$encoded = function_exists( 'wp_json_encode' )
			? wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			: json_encode( self::canonicalize( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return hash( 'sha256', (string) $encoded );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::canonicalize( $child );
		}
		return $value;
	}

	/**
	 * Validate the shared credential-free public-evidence URL boundary.
	 *
	 * @param mixed $url Candidate URL.
	 * @return bool
	 */
	public static function is_safe_https_source_url( $url ) {
		if ( ! is_string( $url ) || strlen( $url ) > 500 ) {
			return false;
		}
		$parts = parse_url( $url );
		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& '' !== (string) ( $parts['host'] ?? '' )
			&& empty( $parts['user'] )
			&& empty( $parts['pass'] )
			&& ( ! isset( $parts['port'] ) || 443 === (int) $parts['port'] )
			&& empty( $parts['query'] )
			&& empty( $parts['fragment'] );
	}

	private static function parse_datetime( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? 0 : (int) $timestamp;
	}

	private static function is_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	private static function is_sha256( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private static function safe_hash_equals( $known, $candidate ) {
		return is_string( $known ) && is_string( $candidate ) && strlen( $known ) === strlen( $candidate ) && hash_equals( $known, $candidate );
	}

	private static function is_error( $value ) {
		return function_exists( 'is_wp_error' ) ? is_wp_error( $value ) : $value instanceof WP_Error;
	}
}
