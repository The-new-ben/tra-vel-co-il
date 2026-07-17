<?php
/**
 * Deterministic harness for TripRequest policy and provider schema safety.
 */

define( 'ABSPATH', __DIR__ );
define( 'TRA_VEL_AGENT_PATH', dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core' );

class WP_Error {
	private $code;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		unset( $message );
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_list_pluck( $list, $field ) {
	return array_map(
		static function ( $item ) use ( $field ) {
			return is_array( $item ) && array_key_exists( $field, $item ) ? $item[ $field ] : null;
		},
		$list
	);
}
function apply_filters( $hook, $value ) { unset( $hook ); return $value; }
function absint( $value ) { return abs( (int) $value ); }

require TRA_VEL_AGENT_PATH . '/includes/interface-tra-vel-agent-provider.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-openai-provider.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-policy.php';
require TRA_VEL_AGENT_PATH . '/includes/class-tra-vel-agent-store.php';

function tv_agent_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Tra-Vel agent policy runtime validation failed: {$message}\n" );
		exit( 1 );
	}
}

function tv_agent_base_request() {
	return array(
		'summary'            => 'Exotic surprise holiday for two under 1,000 USD',
		'language'           => 'mixed',
		'origin_text'        => 'Tel Aviv (TLV)',
		'destination_mode'   => 'anywhere',
		'destinations'       => array(),
		'date_text'          => null,
		'date_flexibility'   => 'flexible',
		'travelers'          => array( 'adults' => 2, 'children' => 0, 'child_ages' => array(), 'rooms' => 1 ),
		'budget'             => array( 'amount' => 1000, 'currency' => 'USD', 'flexibility' => 'hard' ),
		'vibes'              => array( 'exotic', 'romantic' ),
		'hard_constraints'   => array( 'whole trip under 1,000 USD' ),
		'preferences'        => array( 'good value' ),
		'search_scope'       => array( 'flights', 'accommodation', 'transfers', 'activities' ),
		'material_questions' => array(),
		'assumptions'        => array( 'Dates are flexible' ),
		'confidence'         => 0.92,
	);
}

function tv_agent_hydrate_event_type( $stored_type ) {
	$store  = new Tra_Vel_Agent_Store();
	$method = new ReflectionMethod( $store, 'hydrate_event' );
	$method->setAccessible( true );
	$event = $method->invoke(
		$store,
		array(
			'event_uuid' => '11111111-2222-4333-8444-555555555555',
			'sequence_no'=> 1,
			'created_at' => '2030-01-01 00:00:00',
			'event_type' => $stored_type,
			'phase'      => 'supplier_search',
			'status'     => 'waiting',
			'source'     => 'system',
			'visible'    => 1,
			'message'    => 'Supplier search has not started.',
			'payload'    => '{}',
		)
	);
	return $event['type'];
}

function tv_agent_prepare( $request, $input_kind = 'typed', $confirmed = true ) {
	return Tra_Vel_Agent_Policy::prepare_trip_request(
		$request,
		array(
			'input_kind'           => $input_kind,
			'input_sha256'         => str_repeat( 'a', 64 ),
			'transcript_confirmed' => $confirmed,
		)
	);
}

function tv_agent_blockers( $request ) {
	return isset( $request['readiness']['blockers'] ) ? $request['readiness']['blockers'] : array();
}

function tv_agent_question_by_id( $request, $id ) {
	foreach ( $request['material_questions'] as $question ) {
		if ( $id === $question['id'] ) return $question;
	}
	return null;
}

function tv_agent_assert_strict_schema( $schema, $path = 'root' ) {
	if ( isset( $schema['type'] ) && 'object' === $schema['type'] ) {
		tv_agent_assert( array_key_exists( 'additionalProperties', $schema ) && false === $schema['additionalProperties'], "provider schema object {$path} accepts undeclared properties" );
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();
		$property_names = array_keys( $properties );
		sort( $property_names );
		sort( $required );
		tv_agent_assert( $property_names === $required, "provider schema object {$path} does not require every declared property" );
		foreach ( $schema['properties'] as $name => $child ) {
			tv_agent_assert_strict_schema( $child, $path . '.' . $name );
		}
	}
	if ( isset( $schema['type'] ) && 'array' === $schema['type'] ) {
		tv_agent_assert( isset( $schema['items'] ) && is_array( $schema['items'] ), "provider schema array {$path} has no item schema" );
		tv_agent_assert_strict_schema( $schema['items'], $path . '[]' );
	}
}

$provider_schema = Tra_Vel_Agent_OpenAI_Provider::trip_request_schema();
tv_agent_assert_strict_schema( $provider_schema );
tv_agent_assert( false === $provider_schema['additionalProperties'], 'provider root schema is not closed' );
tv_agent_assert( in_array( 'anywhere', $provider_schema['properties']['destination_mode']['enum'], true ), 'provider schema cannot preserve anywhere intent' );
$provider_question_schema = $provider_schema['properties']['material_questions']['items'];
tv_agent_assert( in_array( 'field', $provider_question_schema['required'], true ), 'provider clarification does not require a canonical TripRequest field' );
tv_agent_assert( in_array( 'origin_text', $provider_question_schema['properties']['field']['enum'], true ), 'provider clarification cannot identify the origin field canonically' );
tv_agent_assert( in_array( 'destination_mode', $provider_question_schema['properties']['field']['enum'], true ), 'provider clarification cannot identify the destination decision canonically' );
tv_agent_assert( in_array( 'travelers.child_ages', $provider_question_schema['properties']['field']['enum'], true ), 'provider clarification cannot identify nested traveler fields canonically' );
foreach ( array( 'supplier', 'supplier_status', 'availability', 'offers', 'prices', 'proposals', 'booking', 'reservation', 'order_reference' ) as $commercial_key ) {
	tv_agent_assert( ! array_key_exists( $commercial_key, $provider_schema['properties'] ), "provider interpretation schema permits commercial claim field {$commercial_key}" );
}

$base = tv_agent_base_request();
tv_agent_assert( true === Tra_Vel_Agent_OpenAI_Provider::validate_trip_request( $base ), 'valid strict provider request was rejected' );
$missing = $base;
unset( $missing['summary'] );
$missing_result = Tra_Vel_Agent_OpenAI_Provider::validate_trip_request( $missing );
tv_agent_assert( is_wp_error( $missing_result ) && 'tra_vel_agent_trip_request_incomplete' === $missing_result->get_error_code(), 'missing provider field did not fail closed' );
$invalid_question = $base;
$invalid_question['material_questions'][] = array( 'id' => 'dates', 'question' => 'Which dates?', 'reason' => 'Dates affect price.', 'blocking' => true );
$question_result = Tra_Vel_Agent_OpenAI_Provider::validate_trip_request( $invalid_question );
tv_agent_assert( is_wp_error( $question_result ) && 'tra_vel_agent_trip_question_invalid' === $question_result->get_error_code(), 'provider clarification without a canonical field did not fail closed' );
$unknown_question = $base;
$unknown_question['material_questions'][] = array( 'id' => 'passport', 'field' => 'passport.number', 'question' => 'What is your passport number?', 'reason' => 'Not required for interpretation.', 'blocking' => true );
$unknown_question_result = Tra_Vel_Agent_OpenAI_Provider::validate_trip_request( $unknown_question );
tv_agent_assert( is_wp_error( $unknown_question_result ) && 'tra_vel_agent_trip_question_invalid' === $unknown_question_result->get_error_code(), 'provider clarification with an unsupported or sensitive field did not fail closed' );
$valid_question = $base;
$valid_question['material_questions'][] = array( 'id' => 'dates', 'field' => 'date_text', 'question' => 'Which dates?', 'reason' => 'Dates affect price.', 'blocking' => true );
tv_agent_assert( true === Tra_Vel_Agent_OpenAI_Provider::validate_trip_request( $valid_question ), 'provider clarification with a supported canonical field was rejected' );

$ready = tv_agent_prepare( $base );
tv_agent_assert( '1.0.0' === $ready['contract_version'], 'policy contract version changed' );
tv_agent_assert( '11111111-2222-4333-8444-555555555555' === $ready['request_id'], 'policy did not create a stable request id' );
tv_agent_assert( 1 === $ready['revision'], 'initial request revision must be one' );
tv_agent_assert( 'ready_for_search' === $ready['readiness']['status'] && array() === tv_agent_blockers( $ready ), 'complete typed request was not ready for search' );
tv_agent_assert( 'typed' === $ready['source']['input_kind'] && true === $ready['source']['transcript_confirmed'], 'typed input provenance changed' );

$voice_unconfirmed = tv_agent_prepare( $base, 'voice', false );
tv_agent_assert( 'needs_clarification' === $voice_unconfirmed['readiness']['status'], 'unconfirmed voice request was allowed to search' );
tv_agent_assert( in_array( 'confirm_transcript', tv_agent_blockers( $voice_unconfirmed ), true ), 'voice confirmation blocker is missing' );
$voice_question = tv_agent_question_by_id( $voice_unconfirmed, 'confirm_transcript' );
tv_agent_assert( is_array( $voice_question ) && 'source.transcript_confirmed' === $voice_question['field'] && true === $voice_question['blocking'], 'voice confirmation question is incomplete' );
$voice_confirmed = tv_agent_prepare( $base, 'voice', true );
tv_agent_assert( ! in_array( 'confirm_transcript', tv_agent_blockers( $voice_confirmed ), true ), 'confirmed voice transcript remained blocked' );

$child_gap = $base;
$child_gap['travelers']['children'] = 2;
$child_gap['travelers']['child_ages'] = array( 7 );
$child_result = tv_agent_prepare( $child_gap );
tv_agent_assert( in_array( 'child_ages', tv_agent_blockers( $child_result ), true ), 'missing child age did not block price search' );
$child_question = tv_agent_question_by_id( $child_result, 'child_ages' );
tv_agent_assert( 'travelers.child_ages' === $child_question['field'] && true === $child_question['blocking'], 'child-age clarification is not tied to the correct field' );

foreach ( array( null, 0 ) as $invalid_adults ) {
	$adult_gap = $base;
	$adult_gap['travelers']['adults'] = $invalid_adults;
	$adult_result = tv_agent_prepare( $adult_gap );
	tv_agent_assert( in_array( 'traveler_count', tv_agent_blockers( $adult_result ), true ), 'missing or zero adult count did not block search' );
}

foreach ( array( null, '' ) as $missing_origin ) {
	$origin_gap = $base;
	$origin_gap['origin_text'] = $missing_origin;
	$origin_result = tv_agent_prepare( $origin_gap );
	tv_agent_assert( in_array( 'origin', tv_agent_blockers( $origin_result ), true ), 'missing origin did not block flight and total-trip search' );
}

$provider_origin_gap = $base;
$provider_origin_gap['origin_text'] = null;
$provider_origin_gap['material_questions'] = array(
	array(
		'id'       => 'departure_airport',
		'field'    => 'origin_text',
		'question' => 'What city or airport will you be departing from?',
		'reason'   => 'Flights need a departure point.',
		'blocking' => true,
	),
);
$provider_origin_result = tv_agent_prepare( $provider_origin_gap );
$origin_field_questions = array_values(
	array_filter(
		$provider_origin_result['material_questions'],
		static function ( $question ) {
			return 'origin_text' === $question['field'];
		}
	)
);
tv_agent_assert( 1 === count( $origin_field_questions ), 'provider and deterministic origin questions were both shown' );
tv_agent_assert( 'origin' === $origin_field_questions[0]['id'], 'deterministic origin policy did not replace provider wording for the same field' );
tv_agent_assert( 'An origin is required before flight and total-trip searches can run.' === $origin_field_questions[0]['reason'], 'deterministic origin reason did not replace provider wording for the same field' );
tv_agent_assert( array( 'origin' ) === tv_agent_blockers( $provider_origin_result ), 'deduplicated origin question did not preserve its blocker' );

$blocking_merge_gap = $base;
$blocking_merge_gap['material_questions'] = array(
	array( 'id' => 'dates_required', 'field' => 'date_text', 'question' => 'Which dates?', 'reason' => 'Dates affect price.', 'blocking' => true ),
	array( 'id' => 'dates_optional', 'field' => 'date_text', 'question' => 'Would you like to add dates?', 'reason' => 'Dates improve results.', 'blocking' => false ),
);
$blocking_merge_result = tv_agent_prepare( $blocking_merge_gap );
tv_agent_assert( 1 === count( $blocking_merge_result['material_questions'] ), 'same-field provider questions were not deduplicated' );
tv_agent_assert( true === $blocking_merge_result['material_questions'][0]['blocking'], 'same-field deduplication weakened a blocking requirement' );
tv_agent_assert( 'dates_required' === $blocking_merge_result['material_questions'][0]['id'], 'a later optional duplicate replaced the blocking question identity' );
tv_agent_assert( 'Which dates?' === $blocking_merge_result['material_questions'][0]['question'], 'a later optional duplicate replaced the blocking question wording' );

$combined = $base;
$combined['origin_text'] = null;
$combined['travelers'] = array( 'adults' => 0, 'children' => 2, 'child_ages' => array(), 'rooms' => 1 );
$combined['material_questions'] = array(
	array( 'id' => 'origin', 'question' => 'Provider wording', 'reason' => 'Provider reason', 'blocking' => true ),
);
$combined_result = tv_agent_prepare( $combined, 'voice', false );
$combined_blockers = tv_agent_blockers( $combined_result );
sort( $combined_blockers );
tv_agent_assert( array( 'child_ages', 'confirm_transcript', 'origin', 'traveler_count' ) === $combined_blockers, 'combined deterministic blockers are incomplete or duplicated' );
tv_agent_assert( 1 === count( array_filter( $combined_result['material_questions'], static function ( $question ) { return 'origin' === $question['id']; } ) ), 'origin clarification was duplicated' );

$anywhere = tv_agent_prepare( $base );
tv_agent_assert( 'anywhere' === $anywhere['destination_mode'], 'policy replaced anywhere intent' );
tv_agent_assert( array() === $anywhere['destinations'], 'policy invented a destination for anywhere intent' );
tv_agent_assert( $base['origin_text'] === $anywhere['origin_text'], 'policy changed the stated origin' );
tv_agent_assert( $base['budget'] === $anywhere['budget'], 'policy changed the traveler budget' );
tv_agent_assert( $base['vibes'] === $anywhere['vibes'], 'policy changed the traveler vibe' );
tv_agent_assert( $base['hard_constraints'] === $anywhere['hard_constraints'], 'policy changed hard constraints' );
tv_agent_assert( $base['search_scope'] === $anywhere['search_scope'], 'policy expanded supplier scope without evidence' );

$allowed_top_level = array_merge( array_keys( $base ), array( 'contract_version', 'request_id', 'revision', 'source', 'readiness' ) );
$unexpected = array_values( array_diff( array_keys( $anywhere ), $allowed_top_level ) );
tv_agent_assert( array() === $unexpected, 'policy added supplier, price, proposal, or booking claims' );
foreach ( array( 'supplier', 'availability', 'offers', 'prices', 'proposals', 'booking', 'reservations', 'orders', 'order_reference' ) as $forbidden_claim ) {
	tv_agent_assert( ! array_key_exists( $forbidden_claim, $anywhere ), "policy added forbidden commercial claim {$forbidden_claim}" );
}

tv_agent_assert( 'supplier.search.not_started' === tv_agent_hydrate_event_type( 'supplier.search.not_started' ), 'canonical event type separators were changed during hydration' );
tv_agent_assert( 'supplier.search.not_started' === tv_agent_hydrate_event_type( 'supplier_search_not_started' ), 'legacy 0.1.0 supplier event was not normalized' );
tv_agent_assert( 'run.created' === tv_agent_hydrate_event_type( 'run_created' ), 'legacy 0.1.0 run event was not normalized' );

echo "Tra-Vel agent policy runtime validation passed (strict schema, deterministic clarification gates, anywhere preservation, canonical events, no supplier or booking claims).\n";
