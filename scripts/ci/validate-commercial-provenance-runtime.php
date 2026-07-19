<?php
/**
 * Runtime checks for the commercial live-provenance boundary.
 */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;

	public function __construct( $code ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

require_once __DIR__ . '/../../theme/tra-vel-v2/inc/class-commercial-provenance.php';

function provenance_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Commercial provenance validation failed: {$message}\n" );
		exit( 1 );
	}
}

function valid_commercial_payload( $regulated = false ) {
	$provider = $regulated ? 'licensed_insurer' : 'connected_flights';
	$payload  = array(
		'contract_version' => '1.0.0',
		'data_mode'        => 'live',
		'currency'         => 'USD',
		'provider_status'  => array(
			'connected'               => true,
			'provider_id'             => $provider,
			'retrieved_at'            => gmdate( 'c', time() - 30 ),
			'fresh_until'             => gmdate( 'c', time() + 600 ),
			'availability_checked_at' => gmdate( 'c', time() - 20 ),
			'currency'                => 'USD',
			'price_scope'             => $regulated ? 'whole_policy_period' : 'whole_party_round_trip',
		),
	);

	if ( $regulated ) {
		$payload['provider_status']['licensed_provider'] = true;
		$payload['provider_status']['license_reference'] = 'IL-TEST-123';
		$payload['regulated_sale_ready']                 = true;
		$payload['plans'] = array(
			array(
				'id'       => 'plan-1',
				'pricing'  => array( 'total_trip' => 84.50 ),
				'purchase' => array( 'provider' => $provider, 'purchasable' => true ),
			),
		);
	} else {
		$payload['offers'] = array(
			array(
				'id'         => 'offer-1',
				'trip_total' => array( 'total' => 1275.00 ),
				'booking'    => array( 'provider' => $provider, 'bookable' => true ),
			),
		);
	}

	return $payload;
}

$valid = valid_commercial_payload();
$result = Tra_Vel_V2_Commercial_Provenance::validate(
	$valid,
	'connected_flights',
	'offers',
	array( 'trip_total', 'total' ),
	'booking',
	'bookable',
	'USD',
	'whole_party_round_trip'
);
provenance_assert( true === $result, 'a complete fresh live flight payload must pass' );

$invalid_cases = array(
	'demo mode'              => function ( &$payload ) { $payload['data_mode'] = 'demo'; },
	'provider mismatch'      => function ( &$payload ) { $payload['provider_status']['provider_id'] = 'another_provider'; },
	'disconnected provider'  => function ( &$payload ) { $payload['provider_status']['connected'] = false; },
	'expired freshness'      => function ( &$payload ) { $payload['provider_status']['fresh_until'] = gmdate( 'c', time() - 1 ); },
	'currency mismatch'      => function ( &$payload ) { $payload['provider_status']['currency'] = 'EUR'; },
	'price-scope mismatch'   => function ( &$payload ) { $payload['provider_status']['price_scope'] = 'per_person'; },
	'non-positive price'     => function ( &$payload ) { $payload['offers'][0]['trip_total']['total'] = 0; },
	'item provider mismatch' => function ( &$payload ) { $payload['offers'][0]['booking']['provider'] = 'another_provider'; },
	'bookable capability'    => function ( &$payload ) { $payload['offers'][0]['booking']['bookable'] = false; },
);

foreach ( $invalid_cases as $label => $mutate ) {
	$payload = valid_commercial_payload();
	$mutate( $payload );
	$result = Tra_Vel_V2_Commercial_Provenance::validate(
		$payload,
		'connected_flights',
		'offers',
		array( 'trip_total', 'total' ),
		'booking',
		'bookable',
		'USD',
		'whole_party_round_trip'
	);
	provenance_assert( is_wp_error( $result ) && 'tra_vel_live_provenance_invalid' === $result->get_error_code(), $label . ' must fail closed' );
}

$insurance = valid_commercial_payload( true );
$result = Tra_Vel_V2_Commercial_Provenance::validate(
	$insurance,
	'licensed_insurer',
	'plans',
	array( 'pricing', 'total_trip' ),
	'purchase',
	'purchasable',
	'USD',
	'whole_policy_period',
	array( 'regulated' => true )
);
provenance_assert( true === $result, 'a licensed and explicitly sale-ready insurance payload must pass' );

foreach ( array( 'licensed_provider', 'license_reference', 'regulated_sale_ready' ) as $field ) {
	$payload = valid_commercial_payload( true );
	if ( 'regulated_sale_ready' === $field ) {
		$payload[ $field ] = false;
	} elseif ( 'license_reference' === $field ) {
		$payload['provider_status'][ $field ] = '';
	} else {
		$payload['provider_status'][ $field ] = false;
	}
	$result = Tra_Vel_V2_Commercial_Provenance::validate(
		$payload,
		'licensed_insurer',
		'plans',
		array( 'pricing', 'total_trip' ),
		'purchase',
		'purchasable',
		'USD',
		'whole_policy_period',
		array( 'regulated' => true )
	);
	provenance_assert( is_wp_error( $result ), $field . ' must be required for a regulated sale' );
}

echo "Tra-Vel commercial provenance runtime validation passed.\n";
