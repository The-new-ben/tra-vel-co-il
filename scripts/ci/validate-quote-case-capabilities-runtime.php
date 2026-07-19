<?php
/**
 * Deterministic role/capability boundary for assisted quote cases.
 */

define( 'ABSPATH', __DIR__ );

$roles = array();
$options = array();
$assertions = 0;

class Tra_Vel_Capability_Test_Role {
	public $capabilities = array();

	public function __construct( $capabilities = array() ) {
		$this->capabilities = $capabilities;
	}

	public function add_cap( $capability ) {
		$this->capabilities[ $capability ] = true;
	}

	public function remove_cap( $capability ) {
		unset( $this->capabilities[ $capability ] );
	}
}

function __( $value ) {
	return $value;
}

function get_role( $name ) {
	global $roles;
	return isset( $roles[ $name ] ) ? $roles[ $name ] : null;
}

function add_role( $name, $label, $capabilities ) {
	global $roles;
	unset( $label );
	$roles[ $name ] = new Tra_Vel_Capability_Test_Role( $capabilities );
	return $roles[ $name ];
}

function remove_role( $name ) {
	global $roles;
	unset( $roles[ $name ] );
}

function get_option( $name ) {
	global $options;
	return isset( $options[ $name ] ) ? $options[ $name ] : false;
}

function update_option( $name, $value ) {
	global $options;
	$options[ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	global $options;
	unset( $options[ $name ] );
	return true;
}

function tra_vel_capability_expect( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, 'Assertion failed: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

require_once dirname( __DIR__, 2 ) . '/plugin/tra-vel-agent-core/includes/class-tra-vel-quote-case-capabilities.php';

$ingest = Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS;
$dispatch = Tra_Vel_Quote_Case_Capabilities::DISPATCH_SUPPLIER_REQUESTS;
$publish = Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS;

tra_vel_capability_expect( '1.2.0' === Tra_Vel_Quote_Case_Capabilities::VERSION, 'Capability version must trigger the trusted-ingestion boundary upgrade.' );
tra_vel_capability_expect( in_array( $ingest, Tra_Vel_Quote_Case_Capabilities::all_capabilities(), true ), 'Trusted canonical ingestion must be administrator/service eligible.' );
tra_vel_capability_expect( ! in_array( $ingest, Tra_Vel_Quote_Case_Capabilities::operator_capabilities(), true ), 'Human quote operators must not receive canonical ingestion.' );
tra_vel_capability_expect( ! in_array( $dispatch, Tra_Vel_Quote_Case_Capabilities::operator_capabilities(), true ), 'Human quote operators must not receive supplier dispatch.' );

$roles['administrator'] = new Tra_Vel_Capability_Test_Role();
$roles[ Tra_Vel_Quote_Case_Capabilities::ROLE ] = new Tra_Vel_Capability_Test_Role(
	array(
		$ingest => true,
		$dispatch => true,
	)
);
$options[ Tra_Vel_Quote_Case_Capabilities::VERSION_OPTION ] = '1.1.0';
Tra_Vel_Quote_Case_Capabilities::maybe_install();

tra_vel_capability_expect( Tra_Vel_Quote_Case_Capabilities::VERSION === $options[ Tra_Vel_Quote_Case_Capabilities::VERSION_OPTION ], 'A version mismatch must run capability installation.' );
tra_vel_capability_expect( ! empty( $roles['administrator']->capabilities[ $ingest ] ), 'Administrator installation must include trusted canonical ingestion.' );
tra_vel_capability_expect( ! empty( $roles[ Tra_Vel_Quote_Case_Capabilities::ROLE ]->capabilities[ $publish ] ), 'Operator installation must include reduced proposal composition.' );
tra_vel_capability_expect( empty( $roles[ Tra_Vel_Quote_Case_Capabilities::ROLE ]->capabilities[ $ingest ] ), 'Upgrade must explicitly remove canonical ingestion from an existing operator role.' );
tra_vel_capability_expect( empty( $roles[ Tra_Vel_Quote_Case_Capabilities::ROLE ]->capabilities[ $dispatch ] ), 'Upgrade must explicitly remove supplier dispatch from an existing operator role.' );

Tra_Vel_Quote_Case_Capabilities::uninstall();
tra_vel_capability_expect( ! isset( $roles[ Tra_Vel_Quote_Case_Capabilities::ROLE ] ), 'Uninstall must remove the owned operator role.' );
tra_vel_capability_expect( empty( $roles['administrator']->capabilities[ $ingest ] ), 'Uninstall must remove trusted ingestion from administrators.' );
tra_vel_capability_expect( ! isset( $options[ Tra_Vel_Quote_Case_Capabilities::VERSION_OPTION ] ), 'Uninstall must remove the capability version option.' );

echo 'Tra-Vel quote-case capability runtime passed (' . $assertions . ' deterministic assertions).' . PHP_EOL;
