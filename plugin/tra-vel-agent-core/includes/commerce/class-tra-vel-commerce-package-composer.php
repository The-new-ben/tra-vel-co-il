<?php
/**
 * Atomic package composition from immutable, owner-bound sandbox offers.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Commerce_Package_Composer {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_COMPONENTS   = 32;
	const MAX_AMOUNT_MINOR = 1000000000000;

	/** @var string */
	private $secret = '';

	/** @var WP_Error|null */
	private $error;

	public function __construct( $secret = null ) {
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'package_secret_unavailable', 'The package-reference secret is unavailable.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'package_secret_unavailable', 'The package-reference secret is unavailable.', 503 );
			return;
		}
		$this->secret = $secret . '|tra-vel-commerce-package-v1';
	}

	/**
	 * Compose one package without inventing a discount or mutating offers.
	 *
	 * @param array $session   Server-owned commerce-search-session snapshot.
	 * @param array $offers    Server-owned immutable offer snapshots.
	 * @param array $selection Closed title/components command.
	 * @param array $context   Exact owner_scope_digest and integer UTC epoch.
	 * @return array|WP_Error
	 */
	public function compose( $session, $offers, $selection, $context ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		$context = $this->context( $context );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$session = $this->session( $session, $context );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$selection = $this->selection( $selection );
		if ( is_wp_error( $selection ) ) {
			return $selection;
		}
		if ( ! is_array( $offers ) || ! $this->is_list( $offers ) || count( $offers ) > 500 ) {
			return $this->error( 'package_offers_invalid', 'Package composition requires a bounded server-owned offer list.', 400 );
		}

		$ranked = array();
		foreach ( $session['ranked_offers'] as $item ) {
			if ( ! $this->exact_object( $item, array( 'offer_ref', 'offer_version', 'vertical', 'currency', 'price_scope', 'rank', 'score_bps', 'reasons' ) ) || ! $this->opaque_ref( $item['offer_ref'], 'offer' ) || ! is_int( $item['offer_version'] ) || $item['offer_version'] < 1 || '' === Tra_Vel_Commerce_Money::currency( $item['currency'] ) || ! is_string( $item['price_scope'] ) || '' === $item['price_scope'] ) {
				return $this->error( 'package_session_invalid', 'The package search-session ranking projection is invalid.', 400 );
			}
			$ranked[ $item['offer_ref'] ] = $item;
		}
		$offer_map = array();
		foreach ( $offers as $offer ) {
			$offer = $this->offer( $offer, $session );
			if ( is_wp_error( $offer ) ) {
				return $offer;
			}
			if ( isset( $offer_map[ $offer['offer_ref'] ] ) ) {
				return $this->error( 'package_offer_duplicate', 'An offer snapshot appears more than once.', 409 );
			}
			$offer_map[ $offer['offer_ref'] ] = $offer;
		}

		$components = array();
		$itinerary  = array();
		$line_items = array();
		$seen       = array();
		$currency   = '';
		$minor_unit = null;
		$totals     = array( 'subtotal_amount_minor' => 0, 'tax_amount_minor' => 0, 'fee_amount_minor' => 0, 'credit_amount_minor' => 0, 'total_amount_minor' => 0 );
		$tax_states = array();
		$fee_states = array();
		$expires_at = strtotime( $session['expires_at'] );

		foreach ( $selection['components'] as $index => $selected ) {
			$sequence  = $index + 1;
			$offer_ref = $selected['offer_ref'];
			if ( isset( $seen[ $offer_ref ] ) ) {
				return $this->error( 'package_component_duplicate', 'One offer cannot be selected twice in a package.', 409 );
			}
			if ( ! isset( $offer_map[ $offer_ref ], $ranked[ $offer_ref ] ) || $ranked[ $offer_ref ]['offer_version'] !== $selected['offer_version'] ) {
				return $this->error( 'package_offer_not_in_session', 'A selected offer is not part of the bound search session.', 409 );
			}
			$offer = $offer_map[ $offer_ref ];
			if ( $offer['version'] !== $selected['offer_version'] ) {
				return $this->error( 'package_offer_version_conflict', 'A selected offer version no longer matches the server snapshot.', 409 );
			}
			if ( ! $this->role_matches_vertical( $selected['role'], $offer['vertical'] ) ) {
				return $this->error( 'package_component_role_invalid', 'The component role is not valid for the selected vertical.', 400 );
			}
			$offer_expiry = strtotime( $offer['availability']['fresh_until'] );
			if ( false === $offer_expiry || $offer_expiry <= $context['now'] ) {
				return $this->error( 'package_offer_expired', 'A selected offer expired before package composition.', 409 );
			}
			$expires_at = min( $expires_at, $offer_expiry );
			$pricing = $offer['pricing'];
			if ( '' === $currency ) {
				$currency   = $pricing['currency'];
				$minor_unit = $pricing['minor_unit'];
			} elseif ( $currency !== $pricing['currency'] || $minor_unit !== $pricing['minor_unit'] ) {
				return $this->error( 'package_currency_mismatch', 'Package components require one currency and minor-unit exponent.', 400 );
			}

			$component_ref = $this->reference( 'component', array( $session['session_ref'], $offer_ref, $selected['role'], $sequence ) );
			$offer_digest  = Tra_Vel_Commerce_Policy::canonical_digest( $offer );
			$components[]  = array(
				'component_ref' => $component_ref,
				'role'          => $selected['role'],
				'vertical'      => $offer['vertical'],
				'provider_id'    => $offer['provider_id'],
				'provider_reference_digest' => $offer['provider_reference_digest'],
				'offer_ref'     => $offer_ref,
				'offer_version' => $offer['version'],
				'offer_digest'  => $offer_digest,
				'required'      => $selected['required'],
				'sequence'      => $sequence,
			);
			$place_refs = array();
			foreach ( $offer['geometry']['places'] as $place ) {
				if ( isset( $place['place_ref'] ) && $this->opaque_ref( $place['place_ref'], 'place' ) ) {
					$place_refs[ $place['place_ref'] ] = true;
				}
				if ( count( $place_refs ) >= 8 ) {
					break;
				}
			}
			$itinerary[] = array(
				'sequence'      => $sequence,
				'day'           => $selected['day'],
				'component_ref' => $component_ref,
				'place_refs'    => array_keys( $place_refs ),
				'label'         => $offer['product']['title'],
			);

			foreach ( $pricing['line_items'] as $line_index => $line ) {
				$line_items[] = array(
					'code'            => 'c' . $sequence . '_' . $line['code'],
					'label'           => $line['label'],
					'kind'            => $line['kind'],
					'direction'       => $line['direction'],
					'amount_minor'    => $line['amount_minor'],
					'component_ref'   => $component_ref,
					'evidence_digest' => $line['evidence_digest'],
				);
			}
			foreach ( array_keys( $totals ) as $field ) {
				$sum = Tra_Vel_Commerce_Money::add( $totals[ $field ], $pricing[ $field ] );
				if ( is_wp_error( $sum ) || $sum > self::MAX_AMOUNT_MINOR ) {
					return $this->error( 'package_money_overflow', 'Package totals exceed the supported integer-money boundary.', 400 );
				}
				$totals[ $field ] = $sum;
			}
			$tax_states[] = $pricing['tax_state'];
			$fee_states[] = $pricing['fee_state'];
			$seen[ $offer_ref ] = true;
		}

		$calculated = Tra_Vel_Commerce_Money::add( $totals['subtotal_amount_minor'], $totals['tax_amount_minor'] );
		$calculated = is_wp_error( $calculated ) ? $calculated : Tra_Vel_Commerce_Money::add( $calculated, $totals['fee_amount_minor'] );
		if ( is_wp_error( $calculated ) || $totals['credit_amount_minor'] > $calculated || $calculated - $totals['credit_amount_minor'] !== $totals['total_amount_minor'] ) {
			return $this->error( 'package_money_invalid', 'Package component ledgers do not balance exactly.', 400 );
		}

		$now_iso = gmdate( 'Y-m-d\TH:i:s\Z', $context['now'] );
		$selection_digest = Tra_Vel_Commerce_Policy::canonical_digest( $selection );
		$package = array(
			'contract_version'   => self::CONTRACT_VERSION,
			'environment'        => 'sandbox',
			'package_ref'        => $this->reference( 'package', array( $context['owner_scope_digest'], $session['session_ref'], $selection_digest ) ),
			'version'            => 1,
			'owner_scope_digest' => $context['owner_scope_digest'],
			'search_session_ref' => $session['session_ref'],
			'status'             => 'composed',
			'title'              => $selection['title'],
			'components'         => $components,
			'pricing'            => array_merge(
				array(
					'currency'    => $currency,
					'minor_unit'  => $minor_unit,
					'price_scope' => 'package_total',
					'line_items'  => $line_items,
				),
				$totals,
				array(
					'tax_state' => $this->combined_state( $tax_states ),
					'fee_state' => $this->combined_state( $fee_states ),
				)
			),
			'comparison'         => array( 'discount_status' => 'not_claimed', 'savings_amount_minor' => 0, 'comparator_digest' => null ),
			'revalidation'       => array( 'mode' => 'atomic', 'all_components_required' => true, 'state' => 'not_started', 'checked_at' => null ),
			'itinerary'          => $itinerary,
			'package_digest'     => '',
			'created_at'         => $now_iso,
			'updated_at'         => $now_iso,
			'expires_at'         => gmdate( 'Y-m-d\TH:i:s\Z', $expires_at ),
			'sandbox_truth'      => $this->sandbox_truth(),
			'data_boundary'      => $this->data_boundary(),
		);
		$digest_basis = $package;
		unset( $digest_basis['package_digest'] );
		$package['package_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $digest_basis );
		$encoded = wp_json_encode( $package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || false !== strpos( $encoded, 'private_product_ref' ) || 1 === preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $encoded ) ) {
			return $this->error( 'package_projection_failed', 'A private supplier reference reached the package projection.', 500 );
		}
		return $package;
	}

	private function context( $context ) {
		if ( ! $this->exact_object( $context, array( 'owner_scope_digest', 'now' ) ) || ! $this->digest( $context['owner_scope_digest'] ) || ! is_int( $context['now'] ) || $context['now'] < 0 ) {
			return $this->error( 'package_context_invalid', 'The package owner/time context is invalid.', 400 );
		}
		return $context;
	}

	private function session( $session, $context ) {
		$required = array( 'contract_version', 'environment', 'session_ref', 'version', 'owner_scope_digest', 'request_ref', 'request_digest', 'verticals', 'status', 'ranking_version', 'selection_seed_digest', 'catalog_digest', 'provider_network_digest', 'provider_runs', 'ranked_offers', 'counts', 'created_at', 'updated_at', 'expires_at', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $session, $required ) || self::CONTRACT_VERSION !== $session['contract_version'] || 'sandbox' !== $session['environment'] || ! $this->opaque_ref( $session['session_ref'], 'session' ) || ! is_int( $session['version'] ) || $session['version'] < 1 || ! $this->digest( $session['owner_scope_digest'] ) || $session['owner_scope_digest'] !== $context['owner_scope_digest'] || ! $this->digest( $session['catalog_digest'] ) || ! $this->digest( $session['provider_network_digest'] ) || ! in_array( $session['status'], array( 'complete', 'partial' ), true ) || $session['sandbox_truth'] !== $this->sandbox_truth() || $session['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'package_session_invalid', 'The package search-session boundary is invalid or belongs to another owner.', 403 );
		}
		$expiry = Tra_Vel_Commerce_Policy::utc_datetime( $session['expires_at'] );
		if ( null === $expiry || strtotime( $expiry ) <= $context['now'] ) {
			return $this->error( 'package_session_expired', 'The package search session has expired.', 409 );
		}
		if ( ! is_array( $session['ranked_offers'] ) || ! $this->is_list( $session['ranked_offers'] ) || ! $session['ranked_offers'] ) {
			return $this->error( 'package_session_invalid', 'The package search session has no ranked offers.', 400 );
		}
		return $session;
	}

	private function selection( $selection ) {
		if ( ! $this->exact_object( $selection, array( 'title', 'components' ) ) || ! is_string( $selection['title'] ) || $this->text_length( $selection['title'] ) < 1 || $this->text_length( $selection['title'] ) > 160 || sanitize_text_field( $selection['title'] ) !== $selection['title'] || ! is_array( $selection['components'] ) || ! $this->is_list( $selection['components'] ) || ! $selection['components'] || count( $selection['components'] ) > self::MAX_COMPONENTS ) {
			return $this->error( 'package_selection_invalid', 'The package selection command is invalid.', 400 );
		}
		foreach ( $selection['components'] as $item ) {
			if ( ! $this->exact_object( $item, array( 'offer_ref', 'offer_version', 'role', 'required', 'day' ) ) || ! $this->opaque_ref( $item['offer_ref'], 'offer' ) || ! is_int( $item['offer_version'] ) || $item['offer_version'] < 1 || ! in_array( $item['role'], $this->roles(), true ) || ! is_bool( $item['required'] ) || ! is_int( $item['day'] ) || $item['day'] < 1 || $item['day'] > 365 ) {
				return $this->error( 'package_selection_invalid', 'A package component selection is invalid.', 400 );
			}
		}
		return $selection;
	}

	private function offer( $offer, $session ) {
		$required = array( 'contract_version', 'environment', 'offer_ref', 'version', 'search_session_ref', 'provider_id', 'provider_reference_digest', 'vertical', 'status', 'product', 'geometry', 'pricing', 'availability', 'terms', 'capabilities', 'ranking', 'evidence', 'sandbox_truth', 'data_boundary' );
		if ( ! $this->exact_object( $offer, $required ) || self::CONTRACT_VERSION !== $offer['contract_version'] || 'sandbox' !== $offer['environment'] || ! $this->opaque_ref( $offer['offer_ref'], 'offer' ) || ! is_int( $offer['version'] ) || $offer['version'] < 1 || $offer['search_session_ref'] !== $session['session_ref'] || ! $this->digest( $offer['provider_reference_digest'] ) || '' === Tra_Vel_Commerce_Taxonomy::vertical( $offer['vertical'] ) || ! in_array( $offer['status'], array( 'available', 'limited' ), true ) || $offer['sandbox_truth'] !== $this->sandbox_truth() || $offer['data_boundary'] !== $this->data_boundary() ) {
			return $this->error( 'package_offer_invalid', 'An offer snapshot is not eligible for package composition.', 400 );
		}
		if ( ! $this->exact_object( $offer['product'], array( 'product_ref', 'title', 'subtitle', 'badges', 'media', 'facts' ) ) || ! $this->opaque_ref( $offer['product']['product_ref'], 'product' ) || ! is_string( $offer['product']['title'] ) || $this->text_length( $offer['product']['title'] ) < 1 || $this->text_length( $offer['product']['title'] ) > 160 ) {
			return $this->error( 'package_offer_invalid', 'An offer product projection is invalid.', 400 );
		}
		if ( ! $this->exact_object( $offer['geometry'], array( 'places', 'segments' ) ) || ! is_array( $offer['geometry']['places'] ) || ! $this->is_list( $offer['geometry']['places'] ) ) {
			return $this->error( 'package_offer_invalid', 'An offer geometry projection is invalid.', 400 );
		}
		$pricing = $offer['pricing'];
		$pricing_keys = array( 'currency', 'minor_unit', 'price_scope', 'line_items', 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor', 'tax_state', 'fee_state' );
		if ( ! $this->exact_object( $pricing, $pricing_keys ) || '' === Tra_Vel_Commerce_Money::currency( $pricing['currency'] ) || ! is_int( $pricing['minor_unit'] ) || $pricing['minor_unit'] < 0 || $pricing['minor_unit'] > 3 || ! is_array( $pricing['line_items'] ) || ! $this->is_list( $pricing['line_items'] ) || ! $pricing['line_items'] || ! in_array( $pricing['tax_state'], array( 'included', 'excluded', 'unknown' ), true ) || ! in_array( $pricing['fee_state'], array( 'included', 'excluded', 'unknown' ), true ) ) {
			return $this->error( 'package_offer_money_invalid', 'An offer money ledger is invalid.', 400 );
		}
		foreach ( array( 'subtotal_amount_minor', 'tax_amount_minor', 'fee_amount_minor', 'credit_amount_minor', 'total_amount_minor' ) as $field ) {
			if ( ! is_int( $pricing[ $field ] ) || $pricing[ $field ] < 0 || $pricing[ $field ] > self::MAX_AMOUNT_MINOR ) {
				return $this->error( 'package_offer_money_invalid', 'An offer money total is invalid.', 400 );
			}
		}
		$calculated = Tra_Vel_Commerce_Money::add( $pricing['subtotal_amount_minor'], $pricing['tax_amount_minor'] );
		$calculated = is_wp_error( $calculated ) ? $calculated : Tra_Vel_Commerce_Money::add( $calculated, $pricing['fee_amount_minor'] );
		if ( is_wp_error( $calculated ) || $pricing['credit_amount_minor'] > $calculated || $calculated - $pricing['credit_amount_minor'] !== $pricing['total_amount_minor'] ) {
			return $this->error( 'package_offer_money_invalid', 'An offer money ledger does not balance.', 400 );
		}
		foreach ( $pricing['line_items'] as $line ) {
			if ( ! $this->exact_object( $line, array( 'code', 'label', 'kind', 'direction', 'amount_minor', 'evidence_digest' ) ) || ! is_string( $line['code'] ) || 1 !== preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $line['code'] ) || ! is_string( $line['label'] ) || ! in_array( $line['kind'], array( 'base', 'tax', 'fee', 'addon', 'credit' ), true ) || ! in_array( $line['direction'], array( 'debit', 'credit' ), true ) || ! is_int( $line['amount_minor'] ) || $line['amount_minor'] < 0 || ! $this->digest( $line['evidence_digest'] ) ) {
				return $this->error( 'package_offer_money_invalid', 'An offer money line is invalid.', 400 );
			}
		}
		if ( ! $this->exact_object( $offer['availability'], array( 'state', 'quantity_remaining', 'checked_at', 'fresh_until' ) ) || $offer['availability']['state'] !== $offer['status'] || null === Tra_Vel_Commerce_Policy::utc_datetime( $offer['availability']['fresh_until'] ) ) {
			return $this->error( 'package_offer_invalid', 'An offer availability projection is invalid.', 400 );
		}
		return $offer;
	}

	private function role_matches_vertical( $role, $vertical ) {
		$roles = array(
			'flight'        => array( 'outbound', 'inbound' ),
			'accommodation' => array( 'stay' ),
			'transfer'      => array( 'arrival', 'departure' ),
			'activity'      => array( 'experience' ),
			'dining'        => array( 'meal' ),
			'insurance'     => array( 'coverage' ),
			'connectivity'  => array( 'connection' ),
			'equipment'     => array( 'gear' ),
		);
		return isset( $roles[ $vertical ] ) && in_array( $role, $roles[ $vertical ], true );
	}

	private function roles() {
		return array( 'outbound', 'inbound', 'stay', 'arrival', 'departure', 'experience', 'meal', 'coverage', 'connection', 'gear' );
	}

	private function combined_state( $states ) {
		if ( in_array( 'unknown', $states, true ) ) {
			return 'unknown';
		}
		return in_array( 'excluded', $states, true ) ? 'excluded' : 'included';
	}

	private function reference( $kind, $parts ) {
		$payload = implode( '|', array_map( 'strval', $parts ) );
		$digest  = hash_hmac( 'sha256', $kind . '|' . $payload, $this->secret, true );
		return 'tv_' . $kind . '_' . rtrim( strtr( base64_encode( $digest ), '+/', '-_' ), '=' );
	}

	private function opaque_ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function text_length( $value ) {
		if ( ! is_string( $value ) || false === preg_match_all( '/./us', $value, $matches ) ) {
			return 0;
		}
		return count( $matches[0] );
	}

	private function sandbox_truth() {
		return array(
			'simulated_inventory'  => true,
			'real_supplier_request'=> false,
			'real_inventory_hold'  => false,
			'real_charge'          => false,
			'real_booking'         => false,
			'real_policy_issuance' => false,
			'real_settlement'      => false,
		);
	}

	private function data_boundary() {
		return array(
			'raw_supplier_reference_exposed' => false,
			'raw_payment_data_exposed'       => false,
			'medical_data_exposed'           => false,
		);
	}

	private function exact_object( $value, $keys ) {
		return is_array( $value ) && ! $this->is_list( $value ) && ! array_diff( $keys, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $keys );
	}

	private function is_list( $value ) {
		return is_array( $value ) && ( empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	private function error( $suffix, $message, $status ) {
		return new WP_Error( 'tra_vel_commerce_' . $suffix, $message, array( 'status' => (int) $status ) );
	}
}
