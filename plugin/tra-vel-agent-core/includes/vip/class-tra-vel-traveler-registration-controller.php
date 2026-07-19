<?php
/**
 * Signed-in, same-origin REST control plane for progressive registration.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Traveler_Registration_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_Traveler_Registration_Store */
	private $store;

	/** @var array<string,array> */
	private $authorized = array();

	public function __construct( $store = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'traveler-registrations';
		$this->store     = $store ? $store : new Tra_Vel_Traveler_Registration_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_registration' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<registration_ref>tv_registration_[A-Za-z0-9_-]{16,96})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_registration' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array( 'registration_ref' => $this->registration_ref_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_registration' ),
					'permission_callback' => array( $this, 'can_update' ),
					'args'                => array( 'registration_ref' => $this->registration_ref_arg() ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/traveler-registration-resource',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_schema' ),
				'permission_callback' => array( $this, 'can_read_schema' ),
			)
		);
	}

	public function can_create( WP_REST_Request $request ) {
		$available = $this->account_boundary();
		return true === $available ? $this->same_origin_nonce( $request ) : $available;
	}

	public function can_update( WP_REST_Request $request ) {
		$available = $this->account_boundary();
		if ( true !== $available ) {
			return $available;
		}
		$same_origin = $this->same_origin_nonce( $request );
		if ( true !== $same_origin ) {
			return $same_origin;
		}
		return $this->authorize_owned_request( $request );
	}

	public function can_read( WP_REST_Request $request ) {
		$available = $this->account_boundary();
		return true === $available ? $this->authorize_owned_request( $request ) : $available;
	}

	public function can_read_schema() {
		return $this->account_boundary();
	}

	public function create_registration( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_json_required', 'A JSON registration request is required.', array( 'status' => 400 ) );
		}
		$input = Tra_Vel_Traveler_Registration_Schema::create_input( $body );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$result = $this->store->create_registration( get_current_user_id(), $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->result_response( $result, ! empty( $result['created'] ) ? 201 : 200 );
	}

	public function update_registration( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_json_required', 'A JSON registration successor is required.', array( 'status' => 400 ) );
		}
		$input = Tra_Vel_Traveler_Registration_Schema::update_input( $body );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$result = $this->store->update_registration( get_current_user_id(), (string) $request->get_param( 'registration_ref' ), $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->result_response( $result, 200 );
	}

	public function get_registration( WP_REST_Request $request ) {
		$key = $this->authorization_key( (string) $request->get_param( 'registration_ref' ) );
		$owned = isset( $this->authorized[ $key ] ) ? $this->authorized[ $key ] : $this->store->get_owned_registration( get_current_user_id(), (string) $request->get_param( 'registration_ref' ) );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}
		if ( ! is_array( $owned ) ) {
			return $this->not_found();
		}
		return $this->result_response(
			array(
				'aggregate'       => $owned['aggregate'],
				'transition'      => null,
				'transition_count'=> (int) $owned['transition_count'],
				'created'         => false,
				'replayed'        => false,
			),
			200
		);
	}

	public function get_schema() {
		$path = TRA_VEL_AGENT_PATH . '/schemas/traveler-registration-resource.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_schema_missing', 'Traveler registration schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $schema ) || empty( $schema['$id'] ) || 'object' !== ( isset( $schema['type'] ) ? $schema['type'] : '' ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_schema_invalid', 'Traveler registration schema is invalid.', array( 'status' => 500 ) );
		}
		return $this->private_response( $schema );
	}

	public function get_item_schema() {
		if ( null !== $this->schema ) {
			return $this->schema;
		}
		$path = TRA_VEL_AGENT_PATH . '/schemas/traveler-registration-resource.schema.json';
		$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
		$this->schema = is_array( $data ) ? $data : array();
		return $this->schema;
	}

	private function result_response( $result, $status ) {
		$registration = Tra_Vel_Traveler_Registration_Schema::public_projection( $result['aggregate'], (int) $result['transition_count'] );
		if ( is_wp_error( $registration ) ) {
			return $registration;
		}
		$transition = null;
		if ( isset( $result['transition'] ) && is_array( $result['transition'] ) ) {
			$event = $result['transition'];
			$transition = array(
				'transition_ref'          => $event['transition_ref'],
				'from_version'            => $event['from_version'],
				'to_version'              => $event['to_version'],
				'from_gate'               => $event['from_gate'],
				'to_gate'                 => $event['to_gate'],
				'reason'                  => $event['reason'],
				'changed_requirements'    => $event['changed_requirements'],
				'invalidated_requirements'=> $event['invalidated_requirements'],
				'occurred_at'              => $event['occurred_at'],
				'authorization_effect'     => 'registration_only',
			);
		}
		return $this->private_response(
			array(
				'registration'       => $registration,
				'transition'         => $transition,
				'created'            => (bool) $result['created'],
				'replayed'           => (bool) $result['replayed'],
				'authorization_effect'=> 'registration_only',
				'executable_scopes'  => array(),
				'side_effects'       => Tra_Vel_Traveler_Registration_Schema::no_side_effects(),
			),
			$status
		);
	}

	private function account_boundary() {
		if ( ! call_user_func( array( $this->store, 'is_ready' ) ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_store_unavailable', 'Traveler registration storage is temporarily unavailable.', array( 'status' => 503 ) );
		}
		if ( get_current_user_id() < 1 ) {
			return new WP_Error( 'tra_vel_traveler_registration_authentication_required', 'Sign in to manage a traveler registration.', array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_account_forbidden', 'This account cannot access traveler registration.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function same_origin_nonce( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_nonce_invalid', 'The signed-in WordPress session could not be verified.', array( 'status' => 403 ) );
		}
		$origin = (string) $request->get_header( 'Origin' );
		$home   = home_url( '/' );
		$origin_scheme = strtolower( (string) wp_parse_url( $origin, PHP_URL_SCHEME ) );
		$origin_host   = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$home_scheme   = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$home_host     = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$origin_port   = self::effective_port( $origin_scheme, wp_parse_url( $origin, PHP_URL_PORT ) );
		$home_port     = self::effective_port( $home_scheme, wp_parse_url( $home, PHP_URL_PORT ) );
		$origin_path   = (string) wp_parse_url( $origin, PHP_URL_PATH );
		$origin_query  = wp_parse_url( $origin, PHP_URL_QUERY );
		$origin_fragment = wp_parse_url( $origin, PHP_URL_FRAGMENT );
		if ( '' === $origin_scheme || '' === $origin_host || '' === $home_scheme || '' === $home_host || ! hash_equals( $home_scheme, $origin_scheme ) || ! hash_equals( $home_host, $origin_host ) || $origin_port !== $home_port || ! in_array( $origin_path, array( '', '/' ), true ) || null !== $origin_query || null !== $origin_fragment || null !== wp_parse_url( $origin, PHP_URL_USER ) || null !== wp_parse_url( $origin, PHP_URL_PASS ) ) {
			return new WP_Error( 'tra_vel_traveler_registration_origin_rejected', 'Registration mutations must come from the exact Tra-Vel origin.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function authorize_owned_request( WP_REST_Request $request ) {
		$registration_ref = (string) $request->get_param( 'registration_ref' );
		$key = $this->authorization_key( $registration_ref );
		$owned = $this->store->get_owned_registration( get_current_user_id(), $registration_ref );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}
		if ( ! is_array( $owned ) ) {
			return $this->not_found();
		}
		$this->authorized[ $key ] = $owned;
		return true;
	}

	private function authorization_key( $registration_ref ) {
		return get_current_user_id() . ':' . $registration_ref;
	}

	private function not_found() {
		return new WP_Error( 'tra_vel_traveler_registration_not_found', 'Traveler registration not found.', array( 'status' => 404 ) );
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		return $response;
	}

	private function registration_ref_arg() {
		return array(
			'type'              => 'string',
			'required'          => true,
			'pattern'           => '^tv_registration_[A-Za-z0-9_-]{16,96}$',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
	}

	private static function effective_port( $scheme, $port ) {
		$port = (int) $port;
		if ( $port > 0 ) {
			return $port;
		}
		return 'https' === $scheme ? 443 : ( 'http' === $scheme ? 80 : 0 );
	}
}
