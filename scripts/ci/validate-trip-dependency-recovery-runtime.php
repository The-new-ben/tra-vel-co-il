<?php
/**
 * Focused runtime checks for private trip dependency and recovery contracts.
 */

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-dependency-taxonomy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-dependency-policy.php';
require_once __DIR__ . '/../../plugin/tra-vel-agent-core/includes/vip/class-tra-vel-trip-recovery-planner.php';

$assertions = 0;
$scenarios = 0;

function recovery_assert( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function recovery_scenario( $label ) {
	global $scenarios;
	++$scenarios;
}

function recovery_digest( $seed ) { return hash( 'sha256', 'trip-recovery:' . $seed ); }
function recovery_ref( $kind, $seed ) { return 'tv_' . $kind . '_' . substr( hash( 'sha256', $seed ), 0, 28 ); }

function recovery_boundary() {
	return array(
		'server_only' => true,
		'public_serialization_allowed' => false,
		'raw_identity_data_stored' => false,
		'raw_payment_data_stored' => false,
		'raw_medical_data_stored' => false,
		'raw_supplier_payload_stored' => false,
		'bearer_secret_stored' => false,
		'vault_pointers_only' => true,
		'supplier_action_claimed' => false,
	);
}

function recovery_node( $vertical, $starts, $ends, $origin, $destination, $index ) {
	$deadline_type = array(
		'flight' => 'boarding_close',
		'accommodation' => 'free_cancellation_cutoff',
		'package' => 'change_cutoff',
		'transfer' => 'pickup_window',
		'activity' => 'activity_start',
		'dining' => 'arrival_notification',
		'insurance' => 'claim_notice',
		'connectivity' => 'connectivity_activation',
		'equipment' => 'equipment_return',
	);
	return array(
		'node_ref' => recovery_ref( 'node', $vertical ),
		'vertical' => $vertical,
		'kind' => Tra_Vel_Trip_Dependency_Taxonomy::node_kind_for_vertical( $vertical ),
		'state' => 'confirmed',
		'service_window' => array( 'starts_at' => $starts, 'ends_at' => $ends, 'timezone' => 'Asia/Jerusalem' ),
		'geography' => array(
			'origin_location_digest' => recovery_digest( 'location:' . $origin ),
			'destination_location_digest' => recovery_digest( 'location:' . $destination ),
			'country_code' => 'IL',
			'local_tourism' => true,
		),
		'supplier' => array(
			'provider_id' => $vertical . '_supplier_a',
			'order_item_ref' => recovery_ref( 'order_item', $vertical ),
			'confirmation_state' => 'verified_current',
			'confirmation_digest' => recovery_digest( 'confirmation:' . $vertical ),
			'observed_at' => '2026-07-19T08:00:00Z',
			'valid_until' => '2026-07-30T00:00:00Z',
		),
		'deadlines' => array(
			array(
				'deadline_ref' => recovery_ref( 'deadline', $vertical ),
				'type' => $deadline_type[ $vertical ],
				'due_at' => $starts,
				'state' => 'open',
				'evidence_digest' => null,
			),
		),
		'artifact_digests' => array( recovery_digest( 'artifact:' . $vertical . ':' . $index ) ),
	);
}

function recovery_constraint( $type, $nodes, $index, $state = 'verified_current' ) {
	$verified = '2026-07-18T12:00:00Z';
	$valid = '2026-08-19T12:00:00Z';
	$evidence = recovery_digest( 'constraint:' . $type . ':' . $index );
	if ( in_array( $state, array( 'required', 'missing', 'not_applicable' ), true ) ) {
		$verified = null;
		$valid = null;
		$evidence = null;
	}
	if ( 'stale' === $state ) {
		$valid = '2026-07-18T13:00:00Z';
	}
	return array(
		'constraint_ref' => recovery_ref( 'constraint', $type . ':' . $index ),
		'subject_ref' => recovery_ref( 'subject', 'party:' . $index ),
		'type' => $type,
		'required_node_refs' => $nodes,
		'state' => $state,
		'evidence_digest' => $evidence,
		'verified_at' => $verified,
		'valid_until' => $valid,
		'vault_pointer_ref' => 'restricted_health_evidence' === $type ? 'vaultptr_' . substr( recovery_digest( 'vault:' . $index ), 0, 24 ) : null,
	);
}

function recovery_graph() {
	$windows = array(
		'flight' => array( '2026-07-20T06:00:00Z', '2026-07-20T07:00:00Z', 'tlv', 'etm_airport' ),
		'transfer' => array( '2026-07-20T08:00:00Z', '2026-07-20T09:00:00Z', 'etm_airport', 'eilat_hotel' ),
		'accommodation' => array( '2026-07-20T09:30:00Z', '2026-07-23T08:00:00Z', 'eilat_hotel', 'eilat_hotel' ),
		'package' => array( '2026-07-19T12:00:00Z', '2026-07-24T12:00:00Z', 'israel_trip', 'israel_trip' ),
		'insurance' => array( '2026-07-19T12:00:00Z', '2026-07-24T12:00:00Z', 'israel_trip', 'israel_trip' ),
		'connectivity' => array( '2026-07-19T12:00:00Z', '2026-07-24T12:00:00Z', 'israel_trip', 'israel_trip' ),
		'equipment' => array( '2026-07-21T07:00:00Z', '2026-07-21T17:00:00Z', 'eilat_hotel', 'eilat_marina' ),
		'activity' => array( '2026-07-21T09:00:00Z', '2026-07-21T12:00:00Z', 'eilat_marina', 'eilat_marina' ),
		'dining' => array( '2026-07-21T18:00:00Z', '2026-07-21T20:00:00Z', 'eilat_hotel', 'eilat_hotel' ),
	);
	$nodes = array();
	$i = 0;
	foreach ( $windows as $vertical => $window ) {
		$nodes[] = recovery_node( $vertical, $window[0], $window[1], $window[2], $window[3], ++$i );
	}
	$refs = array();
	foreach ( $nodes as $node ) {
		$refs[ $node['vertical'] ] = $node['node_ref'];
	}
	$edge_defs = array(
		array( 'flight', 'transfer', 'geographic_handoff', 'trip_critical', 60, 'air_to_ground' ),
		array( 'transfer', 'accommodation', 'geographic_handoff', 'trip_critical', 30, 'ground_to_stay' ),
		array( 'flight', 'package', 'inventory_bundle', 'trip_critical', 0, null ),
		array( 'package', 'insurance', 'insurance_coverage', 'safety_critical', 0, null ),
		array( 'package', 'connectivity', 'connectivity_required', 'important', 0, null ),
		array( 'package', 'equipment', 'equipment_required', 'important', 0, null ),
		array( 'equipment', 'activity', 'equipment_required', 'trip_critical', 0, null ),
		array( 'accommodation', 'activity', 'traveler_presence', 'important', 0, null ),
		array( 'activity', 'dining', 'temporal_before', 'advisory', 0, null ),
	);
	$node_map = array();
	foreach ( $nodes as $node ) {
		$node_map[ $node['vertical'] ] = $node;
	}
	$edges = array();
	foreach ( $edge_defs as $index => $def ) {
		$handoff = null;
		if ( 'geographic_handoff' === $def[2] ) {
			$handoff = array(
				'departure_location_digest' => $node_map[ $def[0] ]['geography']['destination_location_digest'],
				'arrival_location_digest' => $node_map[ $def[1] ]['geography']['origin_location_digest'],
				'mode' => $def[5],
				'feasibility_state' => 'verified',
				'evidence_digest' => recovery_digest( 'handoff:' . $index ),
			);
		}
		$edges[] = array(
			'edge_ref' => recovery_ref( 'edge', $def[0] . ':' . $def[1] ),
			'from_node_ref' => $refs[ $def[0] ],
			'to_node_ref' => $refs[ $def[1] ],
			'type' => $def[2],
			'criticality' => $def[3],
			'min_buffer_minutes' => $def[4],
			'geographic_handoff' => $handoff,
			'constraint_codes' => array( 'traveler.present', 'supplier.confirmed' ),
			'active' => true,
		);
	}
	$all_refs = array_values( $refs );
	$constraints = array(
		recovery_constraint( 'traveler_manifest', $all_refs, 1 ),
		recovery_constraint( 'minor_guardian_authority', $all_refs, 2 ),
		recovery_constraint( 'dependent_adult_authority', $all_refs, 3 ),
		recovery_constraint( 'accessibility_match', $all_refs, 4 ),
		recovery_constraint( 'document_admissibility', array( $refs['flight'] ), 5 ),
		recovery_constraint( 'customer_consent_scope', $all_refs, 6 ),
		recovery_constraint( 'payment_authority', $all_refs, 7 ),
		recovery_constraint( 'restricted_health_evidence', array( $refs['insurance'] ), 8 ),
		recovery_constraint( 'emergency_contact_route', $all_refs, 9 ),
	);
	$graph = array(
		'contract_version' => '1.0.0',
		'environment' => 'sandbox',
		'graph_ref' => recovery_ref( 'graph', 'israel-vip-itinerary' ),
		'graph_version' => 1,
		'previous_graph_digest' => null,
		'graph_digest' => str_repeat( '0', 64 ),
		'trip_ref' => recovery_ref( 'trip', 'israel-vip-trip' ),
		'owner_scope_digest' => recovery_digest( 'owner-scope' ),
		'party_flags' => array( 'minor_present' => true, 'dependent_adult_present' => true, 'accessibility_required' => true ),
		'nodes' => $nodes,
		'edges' => $edges,
		'traveler_constraints' => $constraints,
		'created_at' => '2026-07-18T08:00:00Z',
		'updated_at' => '2026-07-19T08:00:00Z',
		'private_boundary' => recovery_boundary(),
	);
	$graph['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $graph );
	return $graph;
}

function recovery_event( $type, $node_ref, $sequence, $seed, $truth = 'verified_current', $financial = 'no_impact', $source_kind = 'synthetic_test' ) {
	$evidence = recovery_digest( 'event-evidence:' . $seed );
	$expires = '2026-07-20T12:00:00Z';
	if ( 'observed_unverified' === $truth ) {
		$evidence = null;
		$expires = null;
	}
	if ( 'stale' === $truth ) {
		$expires = '2026-07-19T09:00:00Z';
	}
	$financial_evidence = in_array( $financial, array( 'partial_refund_observed', 'balanced', 'disputed' ), true ) ? recovery_digest( 'financial:' . $seed ) : null;
	return array(
		'contract_version' => '1.0.0',
		'event_ref' => recovery_ref( 'trip_event', $seed ),
		'sequence' => $sequence,
		'occurred_at' => '2026-07-19T10:00:00Z',
		'observed_at' => '2026-07-19T10:01:00Z',
		'type' => $type,
		'source' => array( 'kind' => $source_kind, 'truth_state' => $truth, 'evidence_digest' => $evidence, 'response_expires_at' => $expires ),
		'affected_node_refs' => array( $node_ref ),
		'idempotency_digest' => recovery_digest( 'idempotency:' . $seed ),
		'supersedes_event_ref' => null,
		'financial_state' => array( 'state' => $financial, 'evidence_digest' => $financial_evidence ),
		'data_boundary' => array(
			'raw_message_stored' => false,
			'raw_identity_data_stored' => false,
			'raw_payment_data_stored' => false,
			'raw_medical_data_stored' => false,
			'raw_supplier_payload_stored' => false,
			'bearer_secret_stored' => false,
			'authorization_effect' => 'none',
			'supplier_action_claimed' => false,
		),
	);
}

function recovery_node_ref( $graph, $vertical ) {
	foreach ( $graph['nodes'] as $node ) {
		if ( $vertical === $node['vertical'] ) {
			return $node['node_ref'];
		}
	}
	return null;
}

function recovery_plan( $graph, $events ) {
	$plan = Tra_Vel_Trip_Recovery_Planner::assess( $graph, $events, '2026-07-19T12:00:00Z' );
	recovery_assert( ! is_wp_error( $plan ), is_wp_error( $plan ) ? $plan->get_error_code() . ': ' . $plan->get_error_message() : 'plan should validate' );
	return $plan;
}

function recovery_has_strategy( $plan, $strategy ) {
	foreach ( $plan['candidates'] as $candidate ) {
		if ( $strategy === $candidate['strategy'] ) {
			return true;
		}
	}
	return false;
}

function recovery_gate_state( $plan, $code ) {
	foreach ( $plan['gates'] as $gate ) {
		if ( $code === $gate['code'] ) {
			return $gate['state'];
		}
	}
	return null;
}

$graph = recovery_graph();

recovery_scenario( 'complete immutable nine-vertical graph' );
$valid_graph = Tra_Vel_Trip_Dependency_Policy::graph( $graph, '2026-07-19T12:00:00Z' );
recovery_assert( ! is_wp_error( $valid_graph ), 'complete graph must validate' );
recovery_assert( 9 === count( array_unique( array_column( $graph['nodes'], 'vertical' ) ) ), 'graph must cover all nine verticals' );
recovery_assert( 9 === count( $graph['nodes'] ), 'fixture must carry one explicit node per vertical' );
recovery_assert( 9 === count( $graph['traveler_constraints'] ), 'traveler facts must stay independently constrained' );
recovery_assert( false === $graph['private_boundary']['supplier_action_claimed'], 'graph must never claim a supplier action' );
recovery_assert( hash_equals( $graph['graph_digest'], Tra_Vel_Trip_Dependency_Policy::graph_digest( $graph ) ), 'graph self-seal must bind every immutable field' );

recovery_scenario( 'real partial itinerary without invented products' );
$flight_only = $graph;
$flight_only['graph_ref'] = recovery_ref( 'graph', 'flight-only-itinerary' );
$flight_only['trip_ref'] = recovery_ref( 'trip', 'flight-only-trip' );
$flight_only['owner_scope_digest'] = recovery_digest( 'flight-only-owner' );
$flight_only['party_flags'] = array( 'minor_present' => false, 'dependent_adult_present' => false, 'accessibility_required' => false );
$flight_only['nodes'] = array( $graph['nodes'][0] );
$flight_only['edges'] = array();
$flight_only['traveler_constraints'] = array( recovery_constraint( 'traveler_manifest', array( $graph['nodes'][0]['node_ref'] ), 101 ) );
$flight_only['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $flight_only );
recovery_assert( ! is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph( $flight_only, '2026-07-19T12:00:00Z' ) ), 'a flight-only trip must validate without fabricated hotel, dining, insurance, equipment, or connectivity nodes' );
recovery_assert( 1 === count( $flight_only['nodes'] ) && array() === $flight_only['edges'], 'a real one-item itinerary must not require an invented dependency edge' );

recovery_scenario( 'cascading flight cancellation' );
$flight_cancel = recovery_event( 'flight.cancelled', recovery_node_ref( $graph, 'flight' ), 1, 'flight-cancel' );
$flight_plan = recovery_plan( $graph, array( $flight_cancel ) );
recovery_assert( 9 === $flight_plan['impact']['blast_radius_count'], 'flight cancellation must calculate the full downstream blast radius' );
recovery_assert( 8 === count( $flight_plan['impact']['transitive_node_refs'] ), 'flight cancellation must distinguish direct from transitive impact' );
recovery_assert( recovery_has_strategy( $flight_plan, 'protected_flight_rebook' ), 'flight cancellation must propose protected rebooking for review' );
recovery_assert( recovery_has_strategy( $flight_plan, 'preserve_unaffected_components' ), 'flight cancellation must try to preserve still-valid components' );
recovery_assert( 'required' === recovery_gate_state( $flight_plan, 'customer_consent' ), 'flight replacement must remain behind explicit consent' );
recovery_assert( 'satisfied' === recovery_gate_state( $flight_plan, 'document_admissibility' ), 'current document evidence may satisfy the planning gate without exposing documents' );
recovery_assert( 'P1' === $flight_plan['triage']['severity'], 'cascading cancellation must receive trip-critical priority' );

recovery_scenario( 'missed connection' );
$missed = recovery_event( 'flight.missed_connection', recovery_node_ref( $graph, 'transfer' ), 1, 'missed-connection' );
$missed_plan = recovery_plan( $graph, array( $missed ) );
recovery_assert( recovery_has_strategy( $missed_plan, 'protected_flight_rebook' ), 'missed connection must propose protected remaining travel' );
recovery_assert( $missed_plan['impact']['blast_radius_count'] >= 2, 'missed connection must reach dependent arrival services' );
recovery_assert( true === $missed_plan['triage']['human_escalation_required'], 'missed connection requires human oversight' );

recovery_scenario( 'hotel overbooking' );
$overbooked = recovery_event( 'accommodation.overbooked', recovery_node_ref( $graph, 'accommodation' ), 1, 'hotel-overbooked' );
$hotel_plan = recovery_plan( $graph, array( $overbooked ) );
recovery_assert( recovery_has_strategy( $hotel_plan, 'replacement_stay_with_arrival_protection' ), 'overbooking must propose a protected replacement stay' );
recovery_assert( in_array( 'accommodation', $hotel_plan['impact']['affected_verticals'], true ), 'hotel incident must retain its exact vertical' );
recovery_assert( 'satisfied' === recovery_gate_state( $hotel_plan, 'accessibility_supplier_ack' ), 'accessible-party planning must preserve acknowledged accessibility constraints' );
recovery_assert( in_array( 'human_review_required', array( $hotel_plan['triage']['state'] ), true ), 'overbooking must route to a human agent' );

recovery_scenario( 'late arrival threatens hotel' );
$late_flight = recovery_event( 'flight.delayed_connection_at_risk', recovery_node_ref( $graph, 'flight' ), 1, 'late-flight' );
$late_hotel = recovery_event( 'accommodation.late_arrival_risk', recovery_node_ref( $graph, 'accommodation' ), 2, 'late-hotel' );
$late_plan = recovery_plan( $graph, array( $late_flight, $late_hotel ) );
recovery_assert( recovery_has_strategy( $late_plan, 'late_arrival_reconfirmation' ), 'late arrival must propose hotel reconfirmation' );
recovery_assert( in_array( 'concurrent_incidents', $late_plan['triage']['reason_codes'], true ), 'linked flight and hotel incidents must be recognized as concurrent' );
recovery_assert( true === $late_plan['triage']['human_escalation_required'], 'concurrent late-arrival servicing must involve an agent' );

recovery_scenario( 'transfer failure' );
$transfer = recovery_event( 'transfer.failed', recovery_node_ref( $graph, 'transfer' ), 1, 'transfer-failed' );
$transfer_plan = recovery_plan( $graph, array( $transfer ) );
recovery_assert( recovery_has_strategy( $transfer_plan, 'replacement_transfer' ), 'transfer failure must offer replacement mobility' );
recovery_assert( in_array( 'transfer', $transfer_plan['impact']['affected_verticals'], true ), 'transfer failure must remain separately visible' );
recovery_assert( false === $transfer_plan['private_boundary']['execution_dispatched'], 'replacement transfer is a plan, not a dispatched booking' );

recovery_scenario( 'activity weather closure' );
$weather = recovery_event( 'activity.weather_closed', recovery_node_ref( $graph, 'activity' ), 1, 'weather-close', 'verified_current', 'no_impact', 'official_live_feed' );
$weather_plan = recovery_plan( $graph, array( $weather ) );
recovery_assert( recovery_has_strategy( $weather_plan, 'reschedule_weather_window' ), 'weather closure must support rescheduling' );
recovery_assert( recovery_has_strategy( $weather_plan, 'replacement_activity_or_local_option' ), 'weather closure must include a local replacement option' );
recovery_assert( in_array( recovery_node_ref( $graph, 'dining' ), $weather_plan['impact']['transitive_node_refs'], true ), 'activity changes must surface downstream dining timing impact' );

recovery_scenario( 'insurance incident' );
$insurance = recovery_event( 'insurance.incident_reported', recovery_node_ref( $graph, 'insurance' ), 1, 'insurance-incident', 'observed_unverified', 'pending', 'customer_report' );
$insurance_plan = recovery_plan( $graph, array( $insurance ) );
recovery_assert( 'P0' === $insurance_plan['triage']['severity'], 'insurance incident report must enter the immediate assistance lane' );
recovery_assert( true === $insurance_plan['triage']['safety_handoff_required'], 'insurance incident must require an independent safety handoff' );
recovery_assert( recovery_has_strategy( $insurance_plan, 'emergency_assistance_handoff' ), 'insurance incident must create an assistance handoff option' );
recovery_assert( false === $insurance_plan['private_boundary']['supplier_action_claimed'], 'assistance planning must not claim insurer performance' );

recovery_scenario( 'eSIM outage' );
$esim = recovery_event( 'connectivity.outage', recovery_node_ref( $graph, 'connectivity' ), 1, 'esim-outage', 'observed_unverified', 'no_impact', 'customer_report' );
$esim_plan = recovery_plan( $graph, array( $esim ) );
recovery_assert( recovery_has_strategy( $esim_plan, 'alternate_connectivity_with_offline_pack' ), 'connectivity outage must include an offline-safe alternative' );
recovery_assert( in_array( 'connectivity', $esim_plan['impact']['affected_verticals'], true ), 'connectivity must remain a first-class vertical' );
recovery_assert( 'none' === $esim_plan['candidates'][0]['authorization_effect'], 'connectivity alternative cannot silently authorize a purchase' );

recovery_scenario( 'equipment loss' );
$equipment = recovery_event( 'equipment.lost', recovery_node_ref( $graph, 'equipment' ), 1, 'equipment-lost', 'observed_unverified', 'pending', 'customer_report' );
$equipment_plan = recovery_plan( $graph, array( $equipment ) );
recovery_assert( recovery_has_strategy( $equipment_plan, 'emergency_equipment_replacement' ), 'equipment loss must offer replacement planning' );
recovery_assert( recovery_has_strategy( $equipment_plan, 'loss_or_damage_claim_review' ), 'equipment loss must preserve a claim path' );
recovery_assert( in_array( recovery_node_ref( $graph, 'activity' ), $equipment_plan['impact']['transitive_node_refs'], true ), 'equipment loss must expose dependent activity impact' );

recovery_scenario( 'official local-tourism closure' );
$local = recovery_event( 'local_tourism.official_closure', recovery_node_ref( $graph, 'activity' ), 1, 'local-closure', 'verified_current', 'pending', 'official_live_feed' );
$local_plan = recovery_plan( $graph, array( $local ) );
recovery_assert( recovery_has_strategy( $local_plan, 'local_closure_reroute' ), 'official local closure must offer a local reroute' );
recovery_assert( true === $graph['nodes'][7]['geography']['local_tourism'], 'local closure fixture must target explicit Israeli local inventory' );
recovery_assert( in_array( 'activity', $local_plan['impact']['affected_verticals'], true ), 'local inventory remains typed, not collapsed into a generic package' );

recovery_scenario( 'minor dependent and accessibility constraints' );
$constraint_graph = $graph;
foreach ( $constraint_graph['traveler_constraints'] as &$constraint ) {
	if ( 'accessibility_match' === $constraint['type'] ) {
		$constraint = recovery_constraint( 'accessibility_match', $constraint['required_node_refs'], 4, 'stale' );
	}
}
unset( $constraint );
$constraint_graph['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $constraint_graph );
recovery_assert( ! is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph( $constraint_graph, '2026-07-19T12:00:00Z' ) ), 'stale accessibility evidence is representable but must not be treated as current' );
$constraint_plan = recovery_plan( $constraint_graph, array( recovery_event( 'accommodation.overbooked', recovery_node_ref( $constraint_graph, 'accommodation' ), 1, 'accessible-overbooking' ) ) );
recovery_assert( 'stale' === recovery_gate_state( $constraint_plan, 'accessibility_supplier_ack' ), 'stale accessibility evidence must stop automatic recovery readiness' );
recovery_assert( 'blocked' === $constraint_plan['plan_state'], 'stale vulnerable-traveler constraint must block progression' );
recovery_assert( 'satisfied' === recovery_gate_state( $constraint_plan, 'guardian_or_dependent_authority' ), 'current guardian and dependent authority remains independently satisfied' );
recovery_assert( in_array( 'vulnerable_traveler_constraint', $constraint_plan['triage']['reason_codes'], true ), 'vulnerable traveler impact must be explicit in triage' );

recovery_scenario( 'concurrent incidents' );
$concurrent = recovery_plan( $graph, array(
	 recovery_event( 'transfer.failed', recovery_node_ref( $graph, 'transfer' ), 1, 'concurrent-transfer' ),
	 recovery_event( 'connectivity.outage', recovery_node_ref( $graph, 'connectivity' ), 2, 'concurrent-connectivity', 'observed_unverified', 'no_impact', 'customer_report' ),
	 recovery_event( 'equipment.lost', recovery_node_ref( $graph, 'equipment' ), 3, 'concurrent-equipment', 'observed_unverified', 'pending', 'customer_report' ),
) );
recovery_assert( 3 === count( $concurrent['event_ledger']['events'] ), 'concurrent incidents must remain independently recorded' );
recovery_assert( in_array( 'concurrent_incidents', $concurrent['triage']['reason_codes'], true ), 'concurrent failures must be escalated as a coupled case' );
recovery_assert( recovery_has_strategy( $concurrent, 'replacement_transfer' ) && recovery_has_strategy( $concurrent, 'alternate_connectivity_with_offline_pack' ) && recovery_has_strategy( $concurrent, 'emergency_equipment_replacement' ), 'concurrent case must retain options for each service family' );

recovery_scenario( 'stale supplier response' );
$stale_event = recovery_event( 'supplier.response_stale', recovery_node_ref( $graph, 'accommodation' ), 1, 'stale-supplier', 'stale' );
$stale_plan = recovery_plan( $graph, array( $stale_event ) );
recovery_assert( array( $stale_event['event_ref'] ) === $stale_plan['event_ledger']['stale_response_event_refs'], 'stale supplier evidence must be marked in the ledger' );
recovery_assert( 'stale' === recovery_gate_state( $stale_plan, 'supplier_truth' ), 'stale response must block supplier truth' );
recovery_assert( 'blocked' === $stale_plan['plan_state'], 'stale supplier evidence must block progression' );
recovery_assert( recovery_has_strategy( $stale_plan, 'supplier_reverification' ), 'stale response must propose re-verification, not execution' );

recovery_scenario( 'duplicate and out-of-order observations' );
$first = recovery_event( 'transfer.failed', recovery_node_ref( $graph, 'transfer' ), 2, 'order-first' );
$late = recovery_event( 'accommodation.late_arrival_risk', recovery_node_ref( $graph, 'accommodation' ), 1, 'order-late' );
$ordering_plan = recovery_plan( $graph, array( $first, $first, $late ) );
recovery_assert( 1 === $ordering_plan['event_ledger']['duplicate_count'], 'identical duplicate must be acknowledged without repeating impact' );
recovery_assert( array( $late['event_ref'] ) === $ordering_plan['event_ledger']['out_of_order_event_refs'], 'late lower-sequence event must be quarantined' );
recovery_assert( 1 === count( $ordering_plan['event_ledger']['events'] ), 'quarantined out-of-order event cannot mutate the accepted ledger' );
recovery_assert( 'blocked' === $ordering_plan['plan_state'], 'sequence ambiguity must block autonomous progression' );
$conflicting_replay = $first;
$conflicting_replay['type'] = 'connectivity.outage';
recovery_assert( is_wp_error( Tra_Vel_Trip_Recovery_Planner::assess( $graph, array( $first, $conflicting_replay ), '2026-07-19T12:00:00Z' ) ), 'same event reference with different content must fail closed' );

recovery_scenario( 'partial refund and payment uncertainty' );
$partial = recovery_event( 'financial.partial_refund_observed', recovery_node_ref( $graph, 'package' ), 1, 'partial-refund', 'verified_current', 'partial_refund_observed' );
$uncertain = recovery_event( 'financial.payment_state_uncertain', recovery_node_ref( $graph, 'package' ), 2, 'payment-uncertain', 'observed_unverified', 'uncertain', 'operator_observation' );
$money_plan = recovery_plan( $graph, array( $partial, $uncertain ) );
recovery_assert( recovery_has_strategy( $money_plan, 'financial_reconciliation' ), 'partial refund and uncertain payment require reconciliation' );
recovery_assert( 'required' === recovery_gate_state( $money_plan, 'financial_authority' ), 'financial recovery must remain behind verified authority' );
recovery_assert( in_array( 'financial_state_unresolved', $money_plan['triage']['reason_codes'], true ), 'money uncertainty must remain an independent triage reason' );
foreach ( $money_plan['candidates'] as $candidate ) {
	if ( 'financial_reconciliation' === $candidate['strategy'] ) {
		recovery_assert( 'pending' === $candidate['financial_effect']['state'], 'financial option cannot invent a refund amount or balanced result' );
	}
}

recovery_scenario( 'privacy and execution boundary attacks' );
$raw_graph = $graph;
$raw_graph['traveler_name'] = 'Raw identity should never fit the closed contract';
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph( $raw_graph, '2026-07-19T12:00:00Z' ) ), 'raw identity field must fail the closed graph' );
$raw_event = $flight_cancel;
$raw_event['raw_supplier_payload'] = array( 'status' => 'confirmed' );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::event( $raw_event, $graph, '2026-07-19T12:00:00Z' ) ), 'raw supplier payload must be rejected' );
$action_event = $flight_cancel;
$action_event['data_boundary']['supplier_action_claimed'] = true;
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::event( $action_event, $graph, '2026-07-19T12:00:00Z' ) ), 'an observation cannot claim a supplier action' );
$dispatch_plan = $flight_plan;
$dispatch_plan['private_boundary']['execution_dispatched'] = true;
$dispatch_plan['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $dispatch_plan );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery( $dispatch_plan, $graph, '2026-07-19T12:00:00Z' ) ), 'planning contract must reject execution dispatch' );

recovery_scenario( 'graph integrity attacks' );
$tampered = $graph;
$tampered['nodes'][0]['state'] = 'cancelled';
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph( $tampered, '2026-07-19T12:00:00Z' ) ), 'tampered graph must fail its immutable self-seal' );
$cycle = $graph;
$cycle['edges'][] = array(
	'edge_ref' => recovery_ref( 'edge', 'cycle' ),
	'from_node_ref' => recovery_node_ref( $graph, 'dining' ),
	'to_node_ref' => recovery_node_ref( $graph, 'flight' ),
	'type' => 'inventory_bundle',
	'criticality' => 'trip_critical',
	'min_buffer_minutes' => 0,
	'geographic_handoff' => null,
	'constraint_codes' => array( 'cycle.invalid' ),
	'active' => true,
);
$cycle['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $cycle );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph( $cycle, '2026-07-19T12:00:00Z' ) ), 'active dependency cycle must fail closed' );
$unknown = $flight_cancel;
$unknown['affected_node_refs'] = array( recovery_ref( 'node', 'unknown' ) );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::event( $unknown, $graph, '2026-07-19T12:00:00Z' ) ), 'incident cannot target a node outside the bound graph' );
$forged_impact = $flight_plan;
$promoted_ref = array_shift( $forged_impact['impact']['transitive_node_refs'] );
$forged_impact['impact']['direct_node_refs'][] = $promoted_ref;
sort( $forged_impact['impact']['direct_node_refs'], SORT_STRING );
$forged_impact['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $forged_impact );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery( $forged_impact, $graph, '2026-07-19T12:00:00Z' ) ), 'blast radius must derive from accepted event targets rather than caller claims' );

recovery_scenario( 'immutable graph successor' );
$graph_v2 = $graph;
$graph_v2['graph_version'] = 2;
$graph_v2['previous_graph_digest'] = $graph['graph_digest'];
$graph_v2['updated_at'] = '2026-07-19T13:00:00Z';
$graph_v2['nodes'][0]['state'] = 'at_risk';
$graph_v2['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $graph_v2 );
recovery_assert( true === Tra_Vel_Trip_Dependency_Policy::graph_successor( $graph, $graph_v2, '2026-07-19T14:00:00Z' ), 'valid successor must bind the predecessor digest and monotonic version' );
$removed = $graph_v2;
array_pop( $removed['nodes'] );
$removed['graph_digest'] = Tra_Vel_Trip_Dependency_Policy::graph_digest( $removed );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::graph_successor( $graph, $removed, '2026-07-19T14:00:00Z' ) ), 'successor cannot silently remove an itinerary node' );

recovery_scenario( 'verified completion proof' );
$ready = $flight_plan;
$ready['recovery_version'] = 2;
$ready['previous_recovery_digest'] = $flight_plan['recovery_digest'];
$ready['updated_at'] = '2026-07-19T12:10:00Z';
$ready['triage']['next_review_at'] = '2026-07-19T12:15:00Z';
$ready['selected_candidate_ref'] = $ready['candidates'][0]['candidate_ref'];
$ready['plan_state'] = 'ready_for_authorization';
foreach ( $ready['candidates'] as &$candidate ) {
	$candidate['planning_evidence_digest'] = recovery_digest( 'terminal-candidate-revalidation:' . $candidate['candidate_ref'] );
	$candidate['evidence_valid_until'] = '2026-07-19T15:00:00Z';
}
unset( $candidate );
foreach ( $ready['gates'] as &$gate ) {
	if ( 'not_applicable' !== $gate['state'] ) {
		$gate['state'] = 'satisfied';
		$gate['evidence_digest'] = recovery_digest( 'terminal-gate:' . $gate['code'] );
		$gate['verified_at'] = '2026-07-19T12:05:00Z';
	}
}
unset( $gate );
$ready['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $ready );
$ready_result = Tra_Vel_Trip_Dependency_Policy::recovery_successor( $flight_plan, $ready, $graph, '2026-07-19T14:00:00Z' );
recovery_assert( true === $ready_result, is_wp_error( $ready_result ) ? $ready_result->get_error_code() . ': ' . $ready_result->get_error_message() : 'recovery must first reach gated authorization readiness' );

$authorized = $ready;
$authorized['recovery_version'] = 3;
$authorized['previous_recovery_digest'] = $ready['recovery_digest'];
$authorized['updated_at'] = '2026-07-19T12:20:00Z';
$authorized['triage']['next_review_at'] = '2026-07-19T12:25:00Z';
$authorized['plan_state'] = 'authorized';
$authorized['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $authorized );
$authorized_result = Tra_Vel_Trip_Dependency_Policy::recovery_successor( $ready, $authorized, $graph, '2026-07-19T14:00:00Z' );
recovery_assert( true === $authorized_result, is_wp_error( $authorized_result ) ? $authorized_result->get_error_code() . ': ' . $authorized_result->get_error_message() : 'authorization must be a separate immutable revision' );
$rewritten = $authorized;
$rewritten['event_ledger']['events'][0]['type'] = 'flight.missed_connection';
$rewritten['event_ledger']['events'][0]['idempotency_digest'] = recovery_digest( 'rewritten-event' );
$rewritten['event_ledger']['ledger_digest'] = Tra_Vel_Trip_Dependency_Policy::canonical_digest( $rewritten['event_ledger']['events'] );
$rewritten['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $rewritten );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery_successor( $ready, $rewritten, $graph, '2026-07-19T14:00:00Z' ) ), 'successor cannot rewrite an accepted event even when the replacement is structurally valid' );

$executed = $authorized;
$executed['recovery_version'] = 4;
$executed['previous_recovery_digest'] = $authorized['recovery_digest'];
$executed['updated_at'] = '2026-07-19T12:30:00Z';
$executed['triage']['next_review_at'] = '2026-07-19T12:35:00Z';
$executed['plan_state'] = 'execution_observed';
$executed['completion']['state'] = 'in_progress';
$executed['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $executed );
recovery_assert( true === Tra_Vel_Trip_Dependency_Policy::recovery_successor( $authorized, $executed, $graph, '2026-07-19T14:00:00Z' ), 'execution must be observed without claiming that this planner dispatched it' );

$verifying = $executed;
$verifying['recovery_version'] = 5;
$verifying['previous_recovery_digest'] = $executed['recovery_digest'];
$verifying['updated_at'] = '2026-07-19T12:40:00Z';
$verifying['triage']['next_review_at'] = '2026-07-19T12:45:00Z';
$verifying['plan_state'] = 'verification_pending';
$verifying['completion']['state'] = 'pending_verification';
$verifying['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $verifying );
recovery_assert( true === Tra_Vel_Trip_Dependency_Policy::recovery_successor( $executed, $verifying, $graph, '2026-07-19T14:00:00Z' ), 'observed execution must enter independent completion verification' );

$terminal = $verifying;
$terminal['recovery_version'] = 6;
$terminal['previous_recovery_digest'] = $verifying['recovery_digest'];
$terminal['updated_at'] = '2026-07-19T13:00:00Z';
$terminal['triage']['next_review_at'] = '2026-07-19T13:00:00Z';
$terminal['plan_state'] = 'verified';
$affected_terminal = array_merge( $terminal['impact']['direct_node_refs'], $terminal['impact']['transitive_node_refs'] );
$terminal['completion'] = array(
	'state' => 'verified',
	'restored_node_refs' => $affected_terminal,
	'closed_loss_node_refs' => array(),
	'supplier_verification_digest' => recovery_digest( 'terminal-supplier-proof' ),
	'itinerary_revalidation_digest' => recovery_digest( 'terminal-itinerary-proof' ),
	'deadline_outcomes_digest' => recovery_digest( 'terminal-deadline-proof' ),
	'financial_state' => 'balanced',
	'financial_evidence_digest' => recovery_digest( 'terminal-financial-proof' ),
	'customer_notification_digest' => recovery_digest( 'terminal-notification-proof' ),
	'verified_at' => '2026-07-19T13:00:00Z',
	'authorization_effect' => 'none',
	'supplier_action_claimed' => false,
);
$terminal['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $terminal );
$terminal_result = Tra_Vel_Trip_Dependency_Policy::recovery( $terminal, $graph, '2026-07-19T14:00:00Z' );
recovery_assert( ! is_wp_error( $terminal_result ), is_wp_error( $terminal_result ) ? $terminal_result->get_error_code() . ': ' . $terminal_result->get_error_message() : 'terminal snapshot must validate' );
recovery_assert( true === Tra_Vel_Trip_Dependency_Policy::recovery_successor( $verifying, $terminal, $graph, '2026-07-19T14:00:00Z' ), 'terminal revision must bind verification-pending predecessor exactly' );
$skipped = $ready;
$skipped['recovery_version'] = 3;
$skipped['previous_recovery_digest'] = $ready['recovery_digest'];
$skipped['updated_at'] = '2026-07-19T12:20:00Z';
$skipped['plan_state'] = 'verification_pending';
$skipped['completion']['state'] = 'pending_verification';
$skipped['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $skipped );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery_successor( $ready, $skipped, $graph, '2026-07-19T14:00:00Z' ) ), 'successor cannot skip authorization and observed-execution revisions' );
$forged_terminal = $terminal;
$forged_terminal['completion']['supplier_verification_digest'] = null;
$forged_terminal['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $forged_terminal );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery( $forged_terminal, $graph, '2026-07-19T14:00:00Z' ) ), 'completion without supplier verification must fail closed' );
$partial_terminal = $terminal;
array_pop( $partial_terminal['completion']['restored_node_refs'] );
$partial_terminal['recovery_digest'] = Tra_Vel_Trip_Dependency_Policy::recovery_digest( $partial_terminal );
recovery_assert( is_wp_error( Tra_Vel_Trip_Dependency_Policy::recovery( $partial_terminal, $graph, '2026-07-19T14:00:00Z' ) ), 'completion cannot hide an unresolved impacted node' );

recovery_scenario( 'calm public projection' );
$projection = Tra_Vel_Trip_Dependency_Policy::customer_projection( $flight_plan, $graph, '2026-07-19T12:00:00Z' );
recovery_assert( ! is_wp_error( $projection ), 'validated recovery must have a safe customer projection' );
recovery_assert( array( 'contract_version', 'case_ref', 'plan_state', 'severity', 'affected_service_count', 'affected_verticals', 'options_ready', 'action_required', 'human_agent_involved', 'safety_handoff_required', 'completion_state', 'authorization_effect' ) === array_keys( $projection ), 'customer projection must expose only its exact calm status shape' );
recovery_assert( ! isset( $projection['provider_id'], $projection['evidence_digest'], $projection['order_item_ref'] ), 'customer projection cannot expose provider or private evidence locators' );
recovery_assert( 'none' === $projection['authorization_effect'], 'customer status cannot authorize a consequential action' );

$serialized = json_encode( array( 'graph' => $graph, 'plan' => $flight_plan ) );
foreach ( array( '"passport_number":', '"card_number":', '"raw_supplier_payload":', '"bearer_token":', '"medical_narrative":' ) as $forbidden ) {
	recovery_assert( false === strpos( $serialized, $forbidden ), "serialized private contract must not contain {$forbidden}" );
}
recovery_assert( $scenarios >= 20, 'runtime must cover at least twenty dependency and recovery scenarios' );
recovery_assert( count( Tra_Vel_Trip_Dependency_Taxonomy::VERTICALS ) === 9, 'canonical servicing contract must preserve nine verticals' );
recovery_assert( count( Tra_Vel_Trip_Dependency_Taxonomy::GATE_CODES ) === 7, 'customer, money, document, authority, accessibility, supplier, and human gates must stay independent' );

echo "Trip dependency and VIP recovery runtime passed ({$assertions} assertions; {$scenarios} scenarios; 9 verticals; zero supplier dispatch).\n";
