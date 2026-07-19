<?php
/**
 * Fail-closed policy for privacy-minimized, no-login VIP intake envelopes.
 *
 * The envelope is a normalized projection. Message bodies, contact details,
 * attachment bytes, bearer values, and identity/payment/medical facts belong
 * in purpose-limited vaults and may be represented here only by opaque refs or
 * digests.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

final class Tra_Vel_VIP_Intake_Policy {
	/**
	 * Validate an intake envelope and classify exact or cross-channel replay.
	 *
	 * @param array $envelope Normalized, privacy-minimized intake envelope.
	 * @param array $accepted Optional indexes: by_ref, by_channel_event, by_correlation.
	 * @return array|WP_Error
	 */
	public static function intake( $envelope, $accepted = array() ) {
		$validated = self::validate_envelope( $envelope );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$indexes = self::accepted_indexes( $accepted );
		if ( is_wp_error( $indexes ) ) {
			return $indexes;
		}

		$fingerprint = self::canonical_digest( $envelope );
		$intake_ref  = $envelope['intake_ref'];
		if ( isset( $indexes['by_ref'][ $intake_ref ] ) ) {
			if ( $indexes['by_ref'][ $intake_ref ] !== $fingerprint ) {
				return self::error( 'replay_conflict', 'An intake reference cannot be replayed with a different immutable envelope.' );
			}
			return self::result( $envelope, $fingerprint, true, true, $intake_ref );
		}

		$channel_digest = $envelope['source']['channel_event_digest'];
		if ( isset( $indexes['by_channel_event'][ $channel_digest ] ) ) {
			$prior = $indexes['by_channel_event'][ $channel_digest ];
			if ( $prior['content_digest'] !== $envelope['content']['message_digest'] ) {
				return self::error( 'channel_event_conflict', 'A channel event cannot be reused for different content.' );
			}
			return self::result( $envelope, $fingerprint, false, true, $prior['intake_ref'] );
		}

		$correlation_digest = $envelope['correlation_digest'];
		if ( isset( $indexes['by_correlation'][ $correlation_digest ] ) ) {
			$prior = $indexes['by_correlation'][ $correlation_digest ];
			if ( $prior['content_digest'] !== $envelope['content']['message_digest'] ) {
				return self::error( 'correlation_conflict', 'A cross-channel correlation cannot identify different content.' );
			}
			return self::result( $envelope, $fingerprint, false, true, $prior['intake_ref'] );
		}

		return self::result( $envelope, $fingerprint, false, false, null );
	}

	/**
	 * Add an accepted result to deterministic replay indexes.
	 *
	 * @return array|WP_Error
	 */
	public static function index_accepted( $accepted, $result ) {
		$indexes = self::accepted_indexes( $accepted );
		if ( is_wp_error( $indexes ) || ! self::valid_result( $result ) ) {
			return is_wp_error( $indexes ) ? $indexes : self::error( 'accepted_result_invalid', 'Only a validated intake result may be indexed.' );
		}
		if ( $result['duplicate'] ) {
			return $indexes;
		}
		$envelope = $result['envelope'];
		$indexes['by_ref'][ $envelope['intake_ref'] ] = $result['fingerprint'];
		$indexes['by_channel_event'][ $envelope['source']['channel_event_digest'] ] = array(
			'intake_ref'    => $envelope['intake_ref'],
			'content_digest' => $envelope['content']['message_digest'],
		);
		$indexes['by_correlation'][ $envelope['correlation_digest'] ] = array(
			'intake_ref'    => $envelope['intake_ref'],
			'content_digest' => $envelope['content']['message_digest'],
		);
		return $indexes;
	}

	public static function canonical_digest( $value ) {
		return hash( 'sha256', wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function validate_envelope( $envelope ) {
		$keys = array( 'contract_version', 'intake_ref', 'public_receipt_ref', 'idempotency_digest', 'correlation_digest', 'content', 'source', 'access', 'trip_match', 'classification', 'timing', 'receipt', 'data_boundary' );
		if ( ! self::exact_object( $envelope, $keys ) || Tra_Vel_VIP_Intake_Taxonomy::CONTRACT_VERSION !== $envelope['contract_version'] || ! self::privacy_safe( $envelope ) || ! self::ref( $envelope['intake_ref'], 'intake' ) || ! is_string( $envelope['public_receipt_ref'] ) || 1 !== preg_match( '/^TVR-[A-Z0-9]{10}$/', $envelope['public_receipt_ref'] ) || ! self::digest( $envelope['idempotency_digest'] ) || ! self::digest( $envelope['correlation_digest'] ) ) {
			return self::error( 'shape_invalid', 'The intake envelope is not a closed privacy-safe contract.' );
		}
		foreach ( array( 'validate_content', 'validate_source', 'validate_access', 'validate_trip_match', 'validate_classification', 'validate_timing', 'validate_receipt' ) as $validator ) {
			$result = call_user_func( array( __CLASS__, $validator ), $envelope );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( ! self::data_boundary( $envelope['data_boundary'] ) ) {
			return self::error( 'boundary_invalid', 'The no-login intake data boundary is invalid.' );
		}
		return $envelope;
	}

	private static function validate_content( $envelope ) {
		$content = $envelope['content'];
		if ( ! self::exact_object( $content, array( 'message_digest', 'message_vault_ref', 'language_tag', 'semantic_summary_codes', 'attachments' ) ) || ! self::digest( $content['message_digest'] ) || ! self::ref( $content['message_vault_ref'], 'vault' ) || ! is_string( $content['language_tag'] ) || 1 !== preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,3}$/', $content['language_tag'] ) || ! self::code_list( $content['semantic_summary_codes'], true, 24 ) || ! is_array( $content['attachments'] ) || array_values( $content['attachments'] ) !== $content['attachments'] || count( $content['attachments'] ) > 20 ) {
			return self::error( 'content_invalid', 'Intake content must be represented only by a vault ref, digest, and closed metadata.' );
		}
		$attachment_refs = array();
		foreach ( $content['attachments'] as $attachment ) {
			$keys = array( 'attachment_ref', 'blob_digest', 'vault_ref', 'media_class', 'scan_status', 'sensitivity', 'handling' );
			if ( ! self::exact_object( $attachment, $keys ) || ! self::ref( $attachment['attachment_ref'], 'attachment' ) || isset( $attachment_refs[ $attachment['attachment_ref'] ] ) || ! self::digest( $attachment['blob_digest'] ) || ! self::ref( $attachment['vault_ref'], 'vault' ) || ! in_array( $attachment['media_class'], array( 'image', 'document', 'audio', 'video', 'archive', 'other' ), true ) || ! in_array( $attachment['scan_status'], array( 'pending', 'clean', 'malware_detected', 'scan_failed' ), true ) || ! in_array( $attachment['sensitivity'], array( 'none', 'ordinary', 'restricted_identity', 'restricted_medical', 'restricted_payment', 'restricted_legal' ), true ) || ! in_array( $attachment['handling'], array( 'allow_metadata', 'quarantine', 'restricted_vault' ), true ) ) {
				return self::error( 'attachment_invalid', 'An attachment projection is invalid or duplicated.' );
			}
			$attachment_refs[ $attachment['attachment_ref'] ] = true;
			$is_restricted = 0 === strpos( $attachment['sensitivity'], 'restricted_' );
			if ( in_array( $attachment['scan_status'], array( 'pending', 'malware_detected', 'scan_failed' ), true ) && 'quarantine' !== $attachment['handling'] ) {
				return self::error( 'attachment_quarantine_required', 'Unscanned, failed, or malicious attachments must remain quarantined.' );
			}
			if ( 'clean' === $attachment['scan_status'] && $is_restricted && 'restricted_vault' !== $attachment['handling'] ) {
				return self::error( 'attachment_restriction_required', 'Restricted evidence must remain in its restricted vault.' );
			}
			if ( 'clean' === $attachment['scan_status'] && ! $is_restricted && 'allow_metadata' !== $attachment['handling'] ) {
				return self::error( 'attachment_handling_invalid', 'Only clean, non-restricted attachment metadata may enter the general case projection.' );
			}
		}
		return true;
	}

	private static function validate_source( $envelope ) {
		$source = $envelope['source'];
		if ( ! self::exact_object( $source, array( 'channel', 'channel_event_digest', 'sender_assertion_digest', 'sender_trust', 'transport_integrity', 'device_risk', 'scanner_opened' ) ) || ! in_array( $source['channel'], Tra_Vel_VIP_Intake_Taxonomy::CHANNELS, true ) || ! self::digest( $source['channel_event_digest'] ) || ( null !== $source['sender_assertion_digest'] && ! self::digest( $source['sender_assertion_digest'] ) ) || ! in_array( $source['sender_trust'], Tra_Vel_VIP_Intake_Taxonomy::SENDER_TRUST_STATES, true ) || ! in_array( $source['transport_integrity'], array( 'verified', 'unverified', 'failed', 'not_available' ), true ) || ! in_array( $source['device_risk'], array( 'none', 'lost_or_stolen', 'suspected_compromise', 'unknown' ), true ) || ! is_bool( $source['scanner_opened'] ) ) {
			return self::error( 'source_invalid', 'The intake source projection is invalid.' );
		}
		if ( 'verified_channel' === $source['sender_trust'] && ( 'verified' !== $source['transport_integrity'] || ! self::digest( $source['sender_assertion_digest'] ) ) ) {
			return self::error( 'sender_evidence_invalid', 'A verified channel assertion requires integrity and evidence, but still grants no action authority.' );
		}
		return true;
	}

	private static function validate_access( $envelope ) {
		$access = $envelope['access'];
		$keys   = array( 'mode', 'capability_ref', 'capability_digest', 'capability_state', 'requested_scopes', 'permitted_intake_scopes', 'executable_scopes', 'authorization_effect', 'session_evidence_digest' );
		if ( ! self::exact_object( $access, $keys ) || ! in_array( $access['mode'], Tra_Vel_VIP_Intake_Taxonomy::ACCESS_MODES, true ) || ( null !== $access['capability_ref'] && ! self::ref( $access['capability_ref'], 'capability' ) ) || ( null !== $access['capability_digest'] && ! self::digest( $access['capability_digest'] ) ) || ! in_array( $access['capability_state'], Tra_Vel_VIP_Intake_Taxonomy::CAPABILITY_STATES, true ) || ! self::enum_list( $access['requested_scopes'], Tra_Vel_VIP_Intake_Taxonomy::REQUESTABLE_SCOPES, true ) || ! self::enum_list( $access['permitted_intake_scopes'], Tra_Vel_VIP_Intake_Taxonomy::REPORT_SCOPES, false ) || array_diff( $access['permitted_intake_scopes'], $access['requested_scopes'] ) || array() !== $access['executable_scopes'] || 'none' !== $access['authorization_effect'] || ( null !== $access['session_evidence_digest'] && ! self::digest( $access['session_evidence_digest'] ) ) ) {
			return self::error( 'access_invalid', 'No-login intake may record reports but cannot authorize an executable action.' );
		}

		$is_scoped = 'scoped_capability' === $access['mode'];
		if ( $is_scoped ) {
			if ( ! self::ref( $access['capability_ref'], 'capability' ) || ! self::digest( $access['capability_digest'] ) || 'absent' === $access['capability_state'] ) {
				return self::error( 'capability_binding_invalid', 'Scoped intake requires an opaque capability reference and digest.' );
			}
			if ( 'initial_get_unexchanged' === $access['capability_state'] && ( ! $envelope['source']['scanner_opened'] || null !== $access['session_evidence_digest'] || array() !== $access['permitted_intake_scopes'] ) ) {
				return self::error( 'scanner_exchange_required', 'An initial link open cannot establish a session or expose scoped capability.' );
			}
			if ( 'active_scoped_session' === $access['capability_state'] && ( $envelope['source']['scanner_opened'] || ! self::digest( $access['session_evidence_digest'] ) ) ) {
				return self::error( 'capability_session_invalid', 'A scoped capability becomes usable only after a scanner-safe session exchange.' );
			}
			if ( in_array( $access['capability_state'], array( 'consumed', 'expired', 'revoked' ), true ) && array() !== $access['permitted_intake_scopes'] ) {
				return self::error( 'capability_inactive', 'An inactive capability cannot grant an intake scope.' );
			}
		} else {
			if ( null !== $access['capability_ref'] || null !== $access['capability_digest'] || 'absent' !== $access['capability_state'] || $envelope['source']['scanner_opened'] ) {
				return self::error( 'capability_unexpected', 'Only scoped-capability intake may carry capability metadata.' );
			}
			if ( in_array( $access['mode'], array( 'verified_session', 'operator' ), true ) !== self::digest( $access['session_evidence_digest'] ) ) {
				return self::error( 'session_evidence_invalid', 'Verified and operator sessions require evidence; public intake must not claim it.' );
			}
			if ( in_array( $access['mode'], array( 'public_safety', 'public_incident' ), true ) && array_diff( $access['permitted_intake_scopes'], array( 'incident_report' ) ) ) {
				return self::error( 'public_scope_invalid', 'Public intake can submit an incident report but cannot inherit other account capability.' );
			}
		}
		return true;
	}

	private static function validate_trip_match( $envelope ) {
		$match = $envelope['trip_match'];
		if ( ! self::exact_object( $match, array( 'status', 'trip_ref', 'case_ref', 'case_state', 'candidate_count', 'match_evidence_digest' ) ) || ! in_array( $match['status'], array( 'not_attempted', 'no_trip_claimed', 'unique', 'ambiguous', 'rejected' ), true ) || ( null !== $match['trip_ref'] && ! self::ref( $match['trip_ref'], 'trip' ) ) || ( null !== $match['case_ref'] && ! self::ref( $match['case_ref'], 'case' ) ) || ! in_array( $match['case_state'], array( 'none', 'open', 'closed' ), true ) || ! is_int( $match['candidate_count'] ) || $match['candidate_count'] < 0 || $match['candidate_count'] > 100 || ( null !== $match['match_evidence_digest'] && ! self::digest( $match['match_evidence_digest'] ) ) ) {
			return self::error( 'trip_match_invalid', 'The privacy-minimized trip match is invalid.' );
		}
		if ( 'unique' === $match['status'] ) {
			if ( 1 !== $match['candidate_count'] || ! self::ref( $match['trip_ref'], 'trip' ) || ! self::digest( $match['match_evidence_digest'] ) || ( 'none' === $match['case_state'] ) !== ( null === $match['case_ref'] ) ) {
				return self::error( 'unique_trip_match_invalid', 'A unique trip match requires exactly one evidence-bound opaque trip reference.' );
			}
		} elseif ( 'ambiguous' === $match['status'] ) {
			if ( $match['candidate_count'] < 2 || null !== $match['trip_ref'] || null !== $match['case_ref'] || 'none' !== $match['case_state'] || ! self::digest( $match['match_evidence_digest'] ) ) {
				return self::error( 'ambiguous_trip_match_invalid', 'Ambiguous matches must not disclose or select a trip or case.' );
			}
		} elseif ( 0 !== $match['candidate_count'] || null !== $match['trip_ref'] || null !== $match['case_ref'] || 'none' !== $match['case_state'] || null !== $match['match_evidence_digest'] ) {
			return self::error( 'unmatched_trip_invalid', 'An unmatched report cannot claim a trip, case, or match proof.' );
		}
		return true;
	}

	private static function validate_classification( $envelope ) {
		$value = $envelope['classification'];
		$keys  = array( 'normalized_intent', 'incident_family', 'immediate_danger', 'ambiguity', 'priority', 'risk_signals' );
		if ( ! self::exact_object( $value, $keys ) || ! in_array( $value['normalized_intent'], Tra_Vel_VIP_Intake_Taxonomy::NORMALIZED_INTENTS, true ) || ! in_array( $value['incident_family'], Tra_Vel_VIP_Intake_Taxonomy::INCIDENT_FAMILIES, true ) || ! is_bool( $value['immediate_danger'] ) || ! in_array( $value['ambiguity'], Tra_Vel_VIP_Intake_Taxonomy::AMBIGUITY_STATES, true ) || ! in_array( $value['priority'], array( 'P0', 'P1', 'P2', 'P3', 'P4' ), true ) || ! self::enum_list( $value['risk_signals'], Tra_Vel_VIP_Intake_Taxonomy::RISK_SIGNALS, true ) ) {
			return self::error( 'classification_invalid', 'The normalized intake classification is invalid.' );
		}
		if ( $value['immediate_danger'] && ( 'P0' !== $value['priority'] || 'immediate_danger' !== $value['incident_family'] || ! in_array( 'safety', $value['risk_signals'], true ) ) ) {
			return self::error( 'danger_understated', 'Immediate danger must be classified P0 and routed as safety, independent of login or trip match.' );
		}
		if ( in_array( 'none', $value['risk_signals'], true ) && 1 !== count( $value['risk_signals'] ) ) {
			return self::error( 'risk_signal_invalid', 'The none risk signal cannot be combined with a positive signal.' );
		}
		if ( 'safety_report' === $value['normalized_intent'] && ! $value['immediate_danger'] ) {
			return self::error( 'safety_intent_invalid', 'A normalized safety report must trigger immediate-danger handling.' );
		}
		if ( 'public_safety' === $envelope['access']['mode'] && ! $value['immediate_danger'] ) {
			return self::error( 'public_safety_invalid', 'The public safety path is reserved for reports classified as immediate danger.' );
		}
		if ( 'high_impact_request' === $value['normalized_intent'] && ! Tra_Vel_VIP_Intake_Taxonomy::has_high_impact_request( $envelope['access']['requested_scopes'] ) ) {
			return self::error( 'high_impact_scope_missing', 'A high-impact request must name the requested scope without authorizing it.' );
		}
		if ( 'ambiguous' === $envelope['trip_match']['status'] && 'ambiguous_trip' !== $value['ambiguity'] ) {
			return self::error( 'trip_ambiguity_hidden', 'An ambiguous trip match must remain visible to the triage projection.' );
		}
		if ( 'conflicting_instructions' === $value['ambiguity'] && ! in_array( 'fraud', $value['risk_signals'], true ) ) {
			return self::error( 'instruction_conflict_understated', 'Conflicting instructions require a fraud/conflict review signal.' );
		}
		return true;
	}

	private static function validate_timing( $envelope ) {
		$timing = $envelope['timing'];
		$keys   = array( 'reported_at', 'received_at', 'normalized_at', 'delay_class', 'sla_started_at' );
		if ( ! self::exact_object( $timing, $keys ) || ( null !== $timing['reported_at'] && ! self::utc( $timing['reported_at'] ) ) || ! self::utc( $timing['received_at'] ) || ! self::utc( $timing['normalized_at'] ) || $timing['normalized_at'] < $timing['received_at'] || ! in_array( $timing['delay_class'], array( 'current', 'delayed', 'offline_replay', 'unknown' ), true ) || $timing['sla_started_at'] !== $timing['received_at'] ) {
			return self::error( 'timing_invalid', 'The intake clock is invalid; service timing starts when the system receives the report.' );
		}
		if ( null === $timing['reported_at'] ) {
			if ( 'unknown' !== $timing['delay_class'] ) {
				return self::error( 'delay_class_invalid', 'Unknown report time must remain explicitly unknown.' );
			}
			return true;
		}
		$delay = strtotime( $timing['received_at'] ) - strtotime( $timing['reported_at'] );
		if ( $delay < 0 || ( 'current' === $timing['delay_class'] && $delay > 1800 ) || ( 'delayed' === $timing['delay_class'] && ( $delay <= 1800 || $delay > 86400 ) ) || ( 'offline_replay' === $timing['delay_class'] && $delay <= 86400 ) || ( 'unknown' === $timing['delay_class'] ) ) {
			return self::error( 'delay_class_invalid', 'The declared delivery delay does not match the server clock.' );
		}
		if ( 'offline_replay' === $timing['delay_class'] && ! in_array( 'offline', $envelope['classification']['risk_signals'], true ) ) {
			return self::error( 'offline_signal_missing', 'An offline replay must be visible to triage.' );
		}
		return true;
	}

	private static function validate_receipt( $envelope ) {
		$receipt = $envelope['receipt'];
		if ( ! self::exact_object( $receipt, array( 'status', 'delivery_attempt_digest', 'next_retry_at', 'calm_receipt', 'login_required' ) ) || ! in_array( $receipt['status'], array( 'queued', 'delivered', 'failed', 'retry_scheduled' ), true ) || ( null !== $receipt['delivery_attempt_digest'] && ! self::digest( $receipt['delivery_attempt_digest'] ) ) || ( null !== $receipt['next_retry_at'] && ! self::utc( $receipt['next_retry_at'] ) ) || true !== $receipt['calm_receipt'] || false !== $receipt['login_required'] ) {
			return self::error( 'receipt_invalid', 'A no-login report requires a calm, non-authorizing receipt projection.' );
		}
		if ( 'queued' === $receipt['status'] && ( null !== $receipt['delivery_attempt_digest'] || null !== $receipt['next_retry_at'] ) ) {
			return self::error( 'receipt_state_invalid', 'A queued receipt cannot claim an attempt or retry.' );
		}
		if ( 'delivered' === $receipt['status'] && ( ! self::digest( $receipt['delivery_attempt_digest'] ) || null !== $receipt['next_retry_at'] ) ) {
			return self::error( 'receipt_state_invalid', 'A delivered receipt requires delivery evidence and no retry.' );
		}
		if ( in_array( $receipt['status'], array( 'failed', 'retry_scheduled' ), true ) && ( ! self::digest( $receipt['delivery_attempt_digest'] ) || ! self::utc( $receipt['next_retry_at'] ) || $receipt['next_retry_at'] <= $envelope['timing']['received_at'] ) ) {
			return self::error( 'receipt_retry_invalid', 'A failed receipt must schedule a future delivery retry without undoing intake.' );
		}
		return true;
	}

	private static function accepted_indexes( $accepted ) {
		if ( array() === $accepted ) {
			return array( 'by_ref' => array(), 'by_channel_event' => array(), 'by_correlation' => array() );
		}
		if ( ! self::exact_object( $accepted, array( 'by_ref', 'by_channel_event', 'by_correlation' ) ) || ! is_array( $accepted['by_ref'] ) || ! is_array( $accepted['by_channel_event'] ) || ! is_array( $accepted['by_correlation'] ) ) {
			return self::error( 'accepted_index_invalid', 'Replay indexes must use the closed deterministic shape.' );
		}
		foreach ( $accepted['by_ref'] as $ref => $fingerprint ) {
			if ( ! self::ref( $ref, 'intake' ) || ! self::digest( $fingerprint ) ) {
				return self::error( 'accepted_index_invalid', 'The intake replay index contains an invalid reference or fingerprint.' );
			}
		}
		foreach ( array( 'by_channel_event', 'by_correlation' ) as $index_name ) {
			foreach ( $accepted[ $index_name ] as $digest => $entry ) {
				if ( ! self::digest( $digest ) || ! self::exact_object( $entry, array( 'intake_ref', 'content_digest' ) ) || ! self::ref( $entry['intake_ref'], 'intake' ) || ! self::digest( $entry['content_digest'] ) ) {
					return self::error( 'accepted_index_invalid', 'A replay correlation index entry is invalid.' );
				}
			}
		}
		return $accepted;
	}

	private static function result( $envelope, $fingerprint, $replay, $duplicate, $duplicate_of ) {
		return array(
			'envelope'                => $envelope,
			'fingerprint'             => $fingerprint,
			'replay'                  => $replay,
			'duplicate'               => $duplicate,
			'duplicate_of_intake_ref' => $duplicate_of,
		);
	}

	private static function valid_result( $result ) {
		if ( ! self::exact_object( $result, array( 'envelope', 'fingerprint', 'replay', 'duplicate', 'duplicate_of_intake_ref' ) ) || ! is_array( $result['envelope'] ) || ! self::digest( $result['fingerprint'] ) || $result['fingerprint'] !== self::canonical_digest( $result['envelope'] ) || ! is_bool( $result['replay'] ) || ! is_bool( $result['duplicate'] ) || ( null !== $result['duplicate_of_intake_ref'] && ! self::ref( $result['duplicate_of_intake_ref'], 'intake' ) ) ) {
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

	private static function exact_object( $value, $required ) {
		return is_array( $value ) && ! array_diff( $required, array_keys( $value ) ) && ! array_diff( array_keys( $value ), $required );
	}

	private static function ref( $value, $kind ) {
		return is_string( $value ) && 1 === preg_match( '/^tv_' . preg_quote( $kind, '/' ) . '_[A-Za-z0-9_-]{16,96}$/', $value );
	}

	private static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
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

	private static function code_list( $values, $required, $maximum ) {
		if ( ! is_array( $values ) || array_values( $values ) !== $values || ( $required && ! $values ) || count( $values ) > $maximum || count( $values ) !== count( array_unique( $values ) ) ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 96 || 1 !== preg_match( '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $value ) ) {
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

	private static function data_boundary( $value ) {
		$keys = array( 'raw_message_exposed', 'raw_contact_data_exposed', 'raw_attachment_exposed', 'raw_identity_data_exposed', 'raw_payment_data_exposed', 'raw_medical_data_exposed', 'raw_provider_payload_exposed', 'bearer_secret_exposed' );
		if ( ! self::exact_object( $value, $keys ) ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( false !== $value[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	private static function privacy_safe( $value ) {
		$forbidden = '/^(?:raw_message|message_body|body|text|free_text|sender|sender_address|email|email_address|phone|phone_number|contact|contact_details|file_content|attachment_body|bearer_token|token|secret|password|cvv|cvc|card_number|card_pan|pan|passport|passport_number|identity_number|diagnosis|medical_history|medical_narrative|raw_provider_payload|raw_payment_data|payment_token|activation_code|iccid)$/i';
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
		return new WP_Error( 'tra_vel_vip_intake_' . $suffix, $message, array( 'status' => 400 ) );
	}
}
