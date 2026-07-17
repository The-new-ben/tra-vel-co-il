<?php
/**
 * Deterministic policy checks between model interpretation and supplier tools.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Policy {
	/**
	 * Enrich and gate a provider-produced request.
	 *
	 * @param array $request Provider request.
	 * @param array $source  Input provenance.
	 * @param array $previous Existing prepared request for a revision.
	 * @return array
	 */
	public static function prepare_trip_request( $request, $source, $previous = array() ) {
		$previous = is_array( $previous ) ? $previous : array();
		$request['contract_version'] = '1.0.0';
		$request['request_id']       = ! empty( $previous['request_id'] ) ? (string) $previous['request_id'] : wp_generate_uuid4();
		$request['revision']         = ! empty( $previous['revision'] ) ? (int) $previous['revision'] + 1 : 1;
		$request['source']           = array(
			'channel'              => 'web',
			'input_kind'           => isset( $source['input_kind'] ) && 'voice' === $source['input_kind'] ? 'voice' : 'typed',
			'input_sha256'         => isset( $source['input_sha256'] ) ? (string) $source['input_sha256'] : '',
			'transcript_confirmed' => ! empty( $source['transcript_confirmed'] ),
		);

		$questions = is_array( $request['material_questions'] ) ? $request['material_questions'] : array();
		if ( 'voice' === $request['source']['input_kind'] && ! $request['source']['transcript_confirmed'] ) {
			$questions[] = self::question( 'confirm_transcript', 'source.transcript_confirmed', 'אשרו שהטקסט שנקלט מהמיקרופון נכון לפני שמתחילים לחפש.', 'Voice transcription must be confirmed before it can drive supplier searches.', true );
		}

		$adults   = isset( $request['travelers']['adults'] ) ? $request['travelers']['adults'] : null;
		$children = isset( $request['travelers']['children'] ) ? $request['travelers']['children'] : null;
		$ages     = isset( $request['travelers']['child_ages'] ) && is_array( $request['travelers']['child_ages'] ) ? $request['travelers']['child_ages'] : array();
		if ( null === $adults || (int) $adults < 1 ) {
			$questions[] = self::question( 'traveler_count', 'travelers.adults', 'כמה מבוגרים נוסעים?', 'At least one adult is required for search and booking eligibility.', true );
		}
		if ( null !== $children && (int) $children > 0 && count( $ages ) !== (int) $children ) {
			$questions[] = self::question( 'child_ages', 'travelers.child_ages', 'מה הגיל של כל ילד ביום היציאה?', 'Exact child ages can change airfare, room occupancy, insurance, and eligibility.', true );
		}
		if ( empty( $request['origin_text'] ) ) {
			$questions[] = self::question( 'origin', 'origin_text', 'מאיפה תרצו לצאת?', 'An origin is required before flight and total-trip searches can run.', true );
		}

		$request['material_questions'] = self::unique_questions( $questions );
		$blockers                      = array_values(
			array_filter(
				$request['material_questions'],
				static function ( $question ) {
					return ! empty( $question['blocking'] );
				}
			)
		);
		$request['readiness'] = array(
			'status'   => $blockers ? 'needs_clarification' : 'ready_for_search',
			'blockers' => array_values( wp_list_pluck( $blockers, 'id' ) ),
		);
		return $request;
	}

	private static function question( $id, $field, $question, $reason, $blocking ) {
		return array(
			'id'       => $id,
			'field'    => $field,
			'question' => $question,
			'reason'   => $reason,
			'blocking' => (bool) $blocking,
			'status'   => 'open',
		);
	}

	private static function unique_questions( $questions ) {
		$unique = array();
		foreach ( $questions as $question ) {
			if ( ! is_array( $question ) || empty( $question['id'] ) || empty( $question['question'] ) ) {
				continue;
			}
			$candidate = array(
				'id'       => sanitize_key( (string) $question['id'] ),
				'field'    => isset( $question['field'] ) ? sanitize_text_field( (string) $question['field'] ) : '',
				'question' => sanitize_text_field( (string) $question['question'] ),
				'reason'   => sanitize_text_field( isset( $question['reason'] ) ? (string) $question['reason'] : '' ),
				'blocking' => ! empty( $question['blocking'] ),
				'status'   => 'open',
			);
			foreach ( $unique as $index => $existing ) {
				$same_id    = $candidate['id'] === $existing['id'];
				$same_field = $candidate['field'] && $candidate['field'] === $existing['field'];
				if ( $same_id || $same_field ) {
					if ( ! empty( $existing['blocking'] ) && empty( $candidate['blocking'] ) ) {
						$candidate = $existing;
					} else {
						$candidate['blocking'] = ! empty( $candidate['blocking'] ) || ! empty( $existing['blocking'] );
					}
					unset( $unique[ $index ] );
				}
			}
			$unique[] = $candidate;
		}
		return array_values( $unique );
	}
}
