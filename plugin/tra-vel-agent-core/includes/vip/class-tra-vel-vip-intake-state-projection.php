<?php
/**
 * Deterministic customer-simple projection for no-login VIP intake.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Intake_State_Projection {
	/**
	 * Return the deliberately small customer receipt; internal work never leaks.
	 *
	 * @return array|WP_Error
	 */
	public static function customer_receipt( $result ) {
		$projection = self::project( $result );
		if ( is_wp_error( $projection ) ) {
			return $projection;
		}
		return array(
			'public_receipt_ref' => $projection['public_receipt_ref'],
			'customer_state'     => $projection['customer_state'],
			'message_code'       => $projection['receipt_code'],
			'intake_accepted'    => $projection['intake_accepted'],
			'login_required'    => false,
		);
	}

	/**
	 * Project one validated intake result into calm customer state plus safe work.
	 *
	 * @return array|WP_Error
	 */
	public static function project( $result ) {
		if ( ! self::valid_result( $result ) ) {
			return self::error( 'result_invalid', 'A customer intake state can be projected only from a validated immutable result.' );
		}

		$envelope   = $result['envelope'];
		$duplicate  = $result['duplicate'];
		$danger     = $envelope['classification']['immediate_danger'];
		$malware    = self::has_attachment_state( $envelope, array( 'malware_detected', 'scan_failed', 'pending' ) );
		$restricted = self::has_restricted_attachment( $envelope );
		$security   = in_array( $envelope['source']['sender_trust'], array( 'conflicted', 'suspected_spoof' ), true ) || in_array( $envelope['source']['device_risk'], array( 'lost_or_stolen', 'suspected_compromise' ), true ) || $envelope['source']['scanner_opened'];
		$high_impact = 'high_impact_request' === $envelope['classification']['normalized_intent'] || Tra_Vel_VIP_Intake_Taxonomy::has_high_impact_request( $envelope['access']['requested_scopes'] );
		$conflict    = 'conflicting_instructions' === $envelope['classification']['ambiguity'];
		$ambiguous   = in_array( $envelope['classification']['ambiguity'], array( 'unclear_intent', 'ambiguous_trip' ), true ) || 'unclear' === $envelope['classification']['normalized_intent'];
		$closed_case = 'unique' === $envelope['trip_match']['status'] && 'closed' === $envelope['trip_match']['case_state'];

		$actions = array();
		if ( $duplicate ) {
			$state   = 'already_received';
			$receipt = 'request_already_received';
			$actions[] = 'acknowledge_duplicate';
		} else {
			$actions[] = 'accept_report';
			if ( $malware ) {
				$actions[] = 'quarantine_attachment';
			}
			if ( $restricted ) {
				$actions[] = 'isolate_restricted_attachment';
			}

			if ( $danger ) {
				$state   = 'immediate_safety_help';
				$receipt = 'safety_help_started';
				$actions[] = 'start_safety_handoff';
				$actions[] = 'notify_on_call_operator';
			} elseif ( $malware ) {
				$state   = 'attachment_quarantined';
				$receipt = 'request_received_attachment_checking';
			} elseif ( $security ) {
				$state   = 'security_check_needed';
				$receipt = 'request_received_security_check';
				$actions[] = 'flag_sender_review';
			} elseif ( $high_impact ) {
				$state   = 'step_up_needed';
				$receipt = 'request_received_verification_needed';
				$actions[] = 'request_step_up';
			} elseif ( $conflict ) {
				$state   = 'human_review_started';
				$receipt = 'request_received_instructions_review';
				$actions[] = 'flag_sender_review';
				$actions[] = 'request_clarification';
			} elseif ( $ambiguous ) {
				$state   = 'clarification_needed';
				$receipt = 'request_received_clarification_needed';
				$actions[] = 'request_clarification';
			} elseif ( $closed_case ) {
				$state   = 'case_reopen_review';
				$receipt = 'request_received_reopen_review';
				$actions[] = 'review_case_reopen';
			} else {
				$state   = 'case_received';
				$receipt = 'request_received';
			}

			if ( ! $security && ! $ambiguous && ! $conflict && ! $closed_case ) {
				if ( 'unique' === $envelope['trip_match']['status'] && 'open' === $envelope['trip_match']['case_state'] ) {
					$actions[] = 'attach_report_metadata';
				} else {
					$actions[] = 'create_case';
				}
			}
		}

		if ( in_array( $envelope['receipt']['status'], array( 'failed', 'retry_scheduled' ), true ) ) {
			$actions[] = 'retry_receipt_delivery';
		}

		$actions = array_values( array_unique( $actions ) );
		foreach ( $actions as $action ) {
			if ( ! in_array( $action, Tra_Vel_VIP_Intake_Taxonomy::SAFE_ACTIONS, true ) ) {
				return self::error( 'unsafe_action', 'The intake projection produced an action outside its safe allowlist.' );
			}
		}
		if ( ! in_array( $state, Tra_Vel_VIP_Intake_Taxonomy::CUSTOMER_STATES, true ) ) {
			return self::error( 'customer_state_invalid', 'The intake projection produced an unsupported customer state.' );
		}

		return array(
			'intake_ref'                 => $envelope['intake_ref'],
			'public_receipt_ref'         => $envelope['public_receipt_ref'],
			'customer_state'             => $state,
			'receipt_code'               => $receipt,
			'intake_accepted'             => true,
			'login_required'             => false,
			'authorization_effect'       => 'none',
			'executable_scopes'          => array(),
			'safe_actions'               => $actions,
			'operator_review_required'   => $danger || $malware || $restricted || $security || $high_impact || $conflict || $ambiguous || $closed_case || 'offline_replay' === $envelope['timing']['delay_class'],
			'safety_handoff_required'    => $danger && ! $duplicate,
			'duplicate'                  => $duplicate,
			'duplicate_of_intake_ref'    => $result['duplicate_of_intake_ref'],
		);
	}

	private static function has_attachment_state( $envelope, $states ) {
		foreach ( $envelope['content']['attachments'] as $attachment ) {
			if ( in_array( $attachment['scan_status'], $states, true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function has_restricted_attachment( $envelope ) {
		foreach ( $envelope['content']['attachments'] as $attachment ) {
			if ( 0 === strpos( $attachment['sensitivity'], 'restricted_' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function valid_result( $result ) {
		$keys = array( 'envelope', 'fingerprint', 'replay', 'duplicate', 'duplicate_of_intake_ref' );
		if ( ! is_array( $result ) || array_diff( $keys, array_keys( $result ) ) || array_diff( array_keys( $result ), $keys ) || ! is_array( $result['envelope'] ) || ! is_string( $result['fingerprint'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $result['fingerprint'] ) || $result['fingerprint'] !== Tra_Vel_VIP_Intake_Policy::canonical_digest( $result['envelope'] ) || ! is_bool( $result['replay'] ) || ! is_bool( $result['duplicate'] ) || ( null !== $result['duplicate_of_intake_ref'] && ( ! is_string( $result['duplicate_of_intake_ref'] ) || 1 !== preg_match( '/^tv_intake_[A-Za-z0-9_-]{16,96}$/', $result['duplicate_of_intake_ref'] ) ) ) ) {
			return false;
		}
		if ( $result['replay'] ) {
			return $result['duplicate'] && $result['duplicate_of_intake_ref'] === $result['envelope']['intake_ref'];
		}
		if ( ! $result['duplicate'] ) {
			return null === $result['duplicate_of_intake_ref'];
		}
		return null !== $result['duplicate_of_intake_ref'] && $result['duplicate_of_intake_ref'] !== $result['envelope']['intake_ref'];
	}

	private static function error( $suffix, $message ) {
		return new WP_Error( 'tra_vel_vip_intake_projection_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
