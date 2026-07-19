<?php
/**
 * Deterministic constructor for private loyalty and stored-value stress records.
 *
 * The factory only creates opaque HMAC references, seals immutable records, and
 * invokes the closed policy. It performs no storage, REST, network, provider,
 * processor, supplier, messaging, or ledger operation.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_Loyalty_Value_Stress_Factory {
	/** @var string */
	private $secret = '';

	/** @var WP_Error|null */
	private $error;

	/**
	 * @param string|null $secret Server-only deterministic-reference secret.
	 */
	public function __construct( $secret = null ) {
		if ( null === $secret ) {
			if ( ! function_exists( 'wp_salt' ) ) {
				$this->error = $this->error( 'secret_unavailable', 'A server-only reference secret is required.', 503 );
				return;
			}
			$secret = (string) wp_salt( 'auth' );
		}
		if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			$this->error = $this->error( 'secret_unavailable', 'A server-only reference secret is required.', 503 );
			return;
		}
		$this->secret = $secret;
	}

	/** @return array|WP_Error */
	public function create_member_merge( $draft, $now = null, $expected_record_ref = null ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		if ( ! $this->policy_available() ) {
			return $this->error( 'dependency_unavailable', 'The loyalty value stress policy must be loaded first.', 500 );
		}
		if (
			! $this->blank_root_fields( $draft, array( 'merge_ref', 'record_digest' ) ) ||
			! isset( $draft['audit_lineage'] ) || ! is_array( $draft['audit_lineage'] ) ||
			! array_key_exists( 'operation_ref', $draft['audit_lineage'] ) || '' !== $draft['audit_lineage']['operation_ref'] ||
			! array_key_exists( 'lineage_digest', $draft['audit_lineage'] ) || '' !== $draft['audit_lineage']['lineage_digest']
		) {
			return $this->error( 'draft_identity_not_blank', 'Factory-owned merge references and digests must be blank.' );
		}
		$binding_digest = $this->draft_binding_digest( $draft );
		$candidate_ref = $this->private_reference( 'loyalty_merge', array( 'member_merge', $binding_digest ) );
		$conflict = $this->assert_expected_record_ref( $expected_record_ref, $candidate_ref );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		$draft['merge_ref'] = $candidate_ref;
		$draft['audit_lineage']['operation_ref'] = $this->private_reference( 'loyalty_operation', array( 'member_merge_operation', $binding_digest ) );
		$draft['audit_lineage']['lineage_digest'] = Tra_Vel_Loyalty_Value_Stress_Policy::member_merge_basis_digest( $draft );
		$record = Tra_Vel_Loyalty_Value_Stress_Policy::seal_record( $draft );
		return Tra_Vel_Loyalty_Value_Stress_Policy::validate_member_merge( $record, $now );
	}

	/** @return array|WP_Error */
	public function create_accrual_case( $draft, $now = null, $expected_record_ref = null ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		if ( ! $this->policy_available() ) {
			return $this->error( 'dependency_unavailable', 'The loyalty value stress policy must be loaded first.', 500 );
		}
		if ( ! $this->blank_root_fields( $draft, array( 'accrual_case_ref', 'record_digest' ) ) ) {
			return $this->error( 'draft_identity_not_blank', 'Factory-owned accrual references and digests must be blank.' );
		}
		$binding_digest = $this->draft_binding_digest( $draft );
		$candidate_ref = $this->private_reference( 'loyalty_accrual', array( 'accrual_case', $binding_digest ) );
		$conflict = $this->assert_expected_record_ref( $expected_record_ref, $candidate_ref );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		$draft['accrual_case_ref'] = $candidate_ref;
		$record = Tra_Vel_Loyalty_Value_Stress_Policy::seal_record( $draft );
		return Tra_Vel_Loyalty_Value_Stress_Policy::validate_accrual_case( $record, $now );
	}

	/** @return array|WP_Error */
	public function create_cash_points_redemption( $draft, $now = null, $expected_record_ref = null ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		if ( ! $this->policy_available() ) {
			return $this->error( 'dependency_unavailable', 'The loyalty value stress policy must be loaded first.', 500 );
		}
		if ( ! $this->blank_root_fields( $draft, array( 'redemption_ref', 'record_digest' ) ) ) {
			return $this->error( 'draft_identity_not_blank', 'Factory-owned redemption references and digests must be blank.' );
		}
		$binding_digest = $this->draft_binding_digest( $draft );
		$candidate_ref = $this->private_reference( 'loyalty_redemption', array( 'cash_points_redemption', $binding_digest ) );
		$conflict = $this->assert_expected_record_ref( $expected_record_ref, $candidate_ref );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		$draft['redemption_ref'] = $candidate_ref;
		$record = Tra_Vel_Loyalty_Value_Stress_Policy::seal_record( $draft );
		return Tra_Vel_Loyalty_Value_Stress_Policy::validate_cash_points_redemption( $record, $now );
	}

	/** @return array|WP_Error */
	public function create_voucher_ledger( $draft, $now = null, $expected_record_ref = null ) {
		if ( is_wp_error( $this->error ) ) {
			return $this->error;
		}
		if ( ! $this->policy_available() ) {
			return $this->error( 'dependency_unavailable', 'The loyalty value stress policy must be loaded first.', 500 );
		}
		if (
			! $this->blank_root_fields( $draft, array( 'voucher_ref', 'record_digest' ) ) ||
			! isset( $draft['consumption'], $draft['audit_lineage'] ) ||
			! is_array( $draft['consumption'] ) || ! is_array( $draft['audit_lineage'] ) ||
			! array_key_exists( 'operation_ref', $draft['consumption'] ) || '' !== $draft['consumption']['operation_ref'] ||
			! array_key_exists( 'operation_ref', $draft['audit_lineage'] ) || '' !== $draft['audit_lineage']['operation_ref'] ||
			! array_key_exists( 'lineage_digest', $draft['audit_lineage'] ) || '' !== $draft['audit_lineage']['lineage_digest']
		) {
			return $this->error( 'draft_identity_not_blank', 'Factory-owned voucher references and digests must be blank.' );
		}
		$binding_digest = $this->draft_binding_digest( $draft );
		$candidate_ref = $this->private_reference( 'stored_value_voucher', array( 'voucher_ledger', $binding_digest ) );
		$conflict = $this->assert_expected_record_ref( $expected_record_ref, $candidate_ref );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		$draft['voucher_ref'] = $candidate_ref;
		$operation_ref = $this->private_reference( 'stored_value_operation', array( 'voucher_consumption', $binding_digest ) );
		$draft['consumption']['operation_ref'] = $operation_ref;
		$draft['audit_lineage']['operation_ref'] = $operation_ref;
		$draft['audit_lineage']['lineage_digest'] = Tra_Vel_Loyalty_Value_Stress_Policy::voucher_lineage_digest( $draft );
		$record = Tra_Vel_Loyalty_Value_Stress_Policy::seal_record( $draft );
		return Tra_Vel_Loyalty_Value_Stress_Policy::validate_voucher_ledger( $record, $now );
	}

	private function blank_root_fields( $draft, $fields ) {
		if ( ! is_array( $draft ) ) {
			return false;
		}
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $draft ) || '' !== $draft[ $field ] ) {
				return false;
			}
		}
		return true;
	}

	private function policy_available() {
		return class_exists( 'Tra_Vel_Loyalty_Value_Stress_Policy' );
	}

	private function draft_binding_digest( $draft ) {
		$encoded = wp_json_encode( $this->canonicalize( $draft ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private function assert_expected_record_ref( $expected, $candidate ) {
		if ( null === $expected ) {
			return true;
		}
		if ( ! is_string( $expected ) || ! hash_equals( $expected, $candidate ) ) {
			return $this->error( 'idempotency_conflict', 'The same operation expectation cannot be reused with different immutable input.', 409 );
		}
		return true;
	}

	private function private_reference( $prefix, $basis ) {
		$encoded = wp_json_encode( $this->canonicalize( $basis ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return $prefix . '_' . substr( hash_hmac( 'sha256', (string) $encoded, $this->secret ), 0, 32 );
	}

	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = $this->canonicalize( $child );
		}
		return $value;
	}

	private function error( $suffix, $message, $status = 400 ) {
		return new WP_Error( 'tra_vel_loyalty_value_stress_factory_' . $suffix, $message, array( 'status' => $status ) );
	}
}
