<?php
/**
 * Public travel insurance decision and quote REST contract.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Insurance_Quote_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_V2_Insurance_Quote_Repository */
	private $repository;

	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace  = 'tra-vel/v2';
		$this->rest_base  = 'insurance/quote';
		$this->repository = new Tra_Vel_V2_Insurance_Quote_Repository();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/insurance/health',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_health' ), 'permission_callback' => '__return_true' )
		);
		register_rest_route(
			$this->namespace,
			'/insurance/schema',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_contract_schema' ), 'permission_callback' => '__return_true' )
		);
		register_rest_route(
			$this->namespace,
			'/insurance/cache',
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'purge_cache' ), 'permission_callback' => array( $this, 'can_manage_cache' ) )
		);
	}

	public function get_items( $request ) {
		$query = array(
			'destination'       => $request->get_param( 'destination' ),
			'start_date'        => $request->get_param( 'start_date' ),
			'end_date'          => $request->get_param( 'end_date' ),
			'adults'            => (int) $request->get_param( 'adults' ),
			'children'          => (int) $request->get_param( 'children' ),
			'oldest_age'        => (int) $request->get_param( 'oldest_age' ),
			'trip_type'         => $request->get_param( 'trip_type' ),
			'baggage'           => (bool) $request->get_param( 'baggage' ),
			'cancellation'      => (bool) $request->get_param( 'cancellation' ),
			'adventure_sports'  => (bool) $request->get_param( 'adventure_sports' ),
			'winter_sports'     => (bool) $request->get_param( 'winter_sports' ),
			'electronics'       => (bool) $request->get_param( 'electronics' ),
			'medical_condition' => (bool) $request->get_param( 'medical_condition' ),
			'pregnancy'         => (bool) $request->get_param( 'pregnancy' ),
			'sort'              => $request->get_param( 'sort' ),
			'limit'             => (int) $request->get_param( 'limit' ),
			'currency'          => 'USD',
		);
		$valid = $this->validate_quote( $query );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$contains_sensitive_flags = $query['medical_condition'] || $query['pregnancy'];
		if ( $contains_sensitive_flags && 'GET' === $request->get_method() ) {
			return new WP_Error( 'tra_vel_insurance_sensitive_requires_post', 'Medical assessment flags must be submitted in the request body.', array( 'status' => 400 ) );
		}
		$query['trip_days'] = $this->trip_days( $query['start_date'], $query['end_date'] );
		$resolved = $this->repository->quote( $query, false, ! $contains_sensitive_flags );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$data  = $resolved['data'];
		$plans = $data['plans'];
		usort(
			$plans,
			static function ( $a, $b ) use ( $query ) {
				if ( 'price' === $query['sort'] ) {
					return (float) $a['pricing']['total_trip'] <=> (float) $b['pricing']['total_trip'];
				}
				if ( 'medical' === $query['sort'] ) {
					return (int) $b['coverage']['medical_limit'] <=> (int) $a['coverage']['medical_limit'];
				}
				if ( 'service' === $query['sort'] ) {
					return (int) $b['service_score'] <=> (int) $a['service_score'];
				}
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$plans = array_slice( $plans, 0, $query['limit'] );
		$plans = array_map( array( $this, 'prepare_plan' ), $plans );
		$recommended = $plans ? $plans[0]['id'] : null;
		$requires_assessment = $query['medical_condition'] || $query['pregnancy'];
		$regulated_sale_ready = 'live' === $data['data_mode'] && true === ( isset( $data['regulated_sale_ready'] ) ? $data['regulated_sale_ready'] : false );
		$disclaimer = 'live' === $data['data_mode']
			? 'Supplier prices are preliminary until declarations, underwriting and policy documents are accepted.'
			: 'Fictional demo plans only. No insurer, valid premium, coverage, policy or purchasing option is connected.';
		$response = new WP_REST_Response(
			array(
				'meta' => array(
					'contract_version' => $data['contract_version'], 'data_mode' => $data['data_mode'], 'generated_at' => $resolved['runtime']['generated_at'],
					'cache_state' => $resolved['runtime']['cache_state'], 'cache_ttl' => $resolved['runtime']['cache_ttl'], 'result_count' => count( $plans ),
					'disclaimer' => $disclaimer, 'policy_wording_controls' => true, 'medical_assessment_required' => $requires_assessment,
					'regulated_sale_ready' => $regulated_sale_ready,
				),
				'query' => $query,
				'provider_status' => $data['provider_status'],
				'adapter_status' => $resolved['runtime']['adapters'],
				'destination' => $data['destination'],
				'risk_contexts' => $data['risk_contexts'],
				'calculation' => $data['calculation'],
				'plans' => $plans,
				'recommended' => $recommended,
			),
			200
		);
		$response->header( 'Cache-Control', $contains_sensitive_flags ? 'private, no-store' : sprintf( 'public, max-age=%d, stale-while-revalidate=300', (int) $resolved['runtime']['cache_ttl'] ) );
		$response->header( 'X-Tra-Vel-Data-Mode', $data['data_mode'] );
		$response->header( 'X-Tra-Vel-Regulated-Sale-Ready', $regulated_sale_ready ? '1' : '0' );
		$response->header( 'X-Tra-Vel-Cache', $resolved['runtime']['cache_state'] );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		$response->add_link( 'https://tra-vel.co.il/rels/map', home_url( '/travel-map/?layer=insurance' ) );
		$response->add_link( 'https://tra-vel.co.il/rels/guide', home_url( '/travel-insurance/' ) );
		return $response;
	}

	public function get_health() {
		$result = $this->repository->quote( $this->default_query() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array( 'ok' => true, 'mode' => $result['data']['data_mode'], 'adapters' => $result['runtime']['adapters'], 'cache' => array( 'state' => $result['runtime']['cache_state'], 'ttl' => $result['runtime']['cache_ttl'] ) )
		);
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/insurance-quote.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_insurance_schema_missing', 'Insurance schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_insurance_schema_invalid', 'Insurance schema is invalid.', array( 'status' => 500 ) );
	}

	public function purge_cache() {
		return rest_ensure_response( array( 'ok' => true, 'cache_generation' => $this->repository->purge() ) );
	}

	public function can_manage_cache() {
		return current_user_can( 'manage_options' );
	}

	public function get_collection_params() {
		$defaults = $this->default_query();
		return array(
			'destination'       => array( 'type' => 'string', 'default' => 'europe', 'enum' => array( 'europe' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'start_date'        => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['start_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'end_date'          => array( 'type' => 'string', 'format' => 'date', 'default' => $defaults['end_date'], 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( $this, 'validate_date' ) ),
			'adults'            => array( 'type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 10, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'children'          => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 10, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'oldest_age'        => array( 'type' => 'integer', 'default' => 35, 'minimum' => 18, 'maximum' => 100, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'trip_type'         => array( 'type' => 'string', 'default' => 'city_break', 'enum' => array( 'city_break', 'family', 'multi_city', 'adventure', 'winter', 'business' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'baggage'           => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'cancellation'      => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'adventure_sports'  => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'winter_sports'     => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'electronics'       => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'medical_condition' => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'pregnancy'         => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'sort'              => array( 'type' => 'string', 'default' => 'smart', 'enum' => array( 'smart', 'price', 'medical', 'service' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'limit'             => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 30, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	public function validate_date( $value ) {
		$date = DateTime::createFromFormat( 'Y-m-d', (string) $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'tra-vel-insurance-quote-response', 'type' => 'object',
			'properties' => array(
				'meta' => array( 'type' => 'object', 'readonly' => true ), 'query' => array( 'type' => 'object', 'readonly' => true ),
				'provider_status' => array( 'type' => 'object', 'readonly' => true ), 'adapter_status' => array( 'type' => 'object', 'readonly' => true ),
				'destination' => array( 'type' => 'object', 'readonly' => true ), 'risk_contexts' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'plans' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ), 'recommended' => array( 'type' => array( 'string', 'null' ), 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	public function prepare_plan( $plan ) {
		$money_fields = array( 'base', 'addons', 'total_trip', 'daily_party', 'per_person' );
		foreach ( $money_fields as $field ) {
			$plan['pricing'][ $field . '_formatted' ] = '$' . number_format_i18n( (float) $plan['pricing'][ $field ], 2 );
		}
		$limit_fields = array( 'medical_limit', 'medical_deductible', 'emergency_dental_limit', 'search_rescue_limit', 'third_party_limit', 'baggage_limit', 'electronics_limit', 'cancellation_limit', 'trip_shortening_limit', 'delay_limit' );
		foreach ( $limit_fields as $field ) {
			$plan['coverage'][ $field . '_formatted' ] = '$' . number_format_i18n( (float) $plan['coverage'][ $field ], 0 );
		}
		foreach ( $plan['requested_addons'] as &$addon ) {
			$addon['estimated_cost_formatted'] = '$' . number_format_i18n( (float) $addon['estimated_cost'], 2 );
		}
		unset( $addon );
		return $plan;
	}

	private function validate_quote( $query ) {
		if ( $query['start_date'] < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'tra_vel_insurance_start_in_past', 'Coverage start date must not be in the past.', array( 'status' => 400 ) );
		}
		if ( $query['end_date'] < $query['start_date'] ) {
			return new WP_Error( 'tra_vel_insurance_invalid_end', 'Coverage end date must not precede the start date.', array( 'status' => 400 ) );
		}
		if ( $this->trip_days( $query['start_date'], $query['end_date'] ) > 365 ) {
			return new WP_Error( 'tra_vel_insurance_trip_too_long', 'The comparison supports trips up to 365 days.', array( 'status' => 400 ) );
		}
		return true;
	}

	private function default_query() {
		$today = current_time( 'timestamp' );
		return array(
			'destination' => 'europe', 'start_date' => wp_date( 'Y-m-d', strtotime( '+30 days', $today ) ), 'end_date' => wp_date( 'Y-m-d', strtotime( '+36 days', $today ) ),
			'adults' => 2, 'children' => 0, 'oldest_age' => 35, 'trip_type' => 'city_break', 'baggage' => false, 'cancellation' => false,
			'adventure_sports' => false, 'winter_sports' => false, 'electronics' => false, 'medical_condition' => false, 'pregnancy' => false,
			'sort' => 'smart', 'limit' => 12, 'currency' => 'USD', 'trip_days' => 7,
		);
	}

	private function trip_days( $start_date, $end_date ) {
		$start = new DateTime( $start_date );
		$end   = new DateTime( $end_date );
		return max( 1, (int) $start->diff( $end )->days + 1 );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Insurance_Quote_Controller();
		$controller->register_routes();
	}
);
