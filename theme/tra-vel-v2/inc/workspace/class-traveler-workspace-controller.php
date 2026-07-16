<?php
/**
 * Private saved-trip, preference and price-watch REST contract.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Traveler_Workspace_Controller extends WP_REST_Controller {
	const META_KEY  = 'tra_vel_v2_traveler_workspace';
	const MAX_ITEMS = 50;

	/** @var array|null */
	protected $schema;

	public function __construct() {
		$this->namespace = 'tra-vel/v2';
		$this->rest_base = 'workspace';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_workspace' ),
					'permission_callback' => array( $this, 'can_use_workspace' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_workspace' ),
					'permission_callback' => array( $this, 'can_use_workspace' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/items',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_item' ),
				'permission_callback' => array( $this, 'can_use_workspace' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/items/(?P<item_id>[A-Za-z0-9._:-]{1,80})',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'can_use_workspace' ),
				'args'                => array(
					'item_id' => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[A-Za-z0-9._:-]{1,80}$',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/items/(?P<item_id>[A-Za-z0-9._:-]{1,80})/watch',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_watch' ),
				'permission_callback' => array( $this, 'can_use_workspace' ),
				'args'                => array(
					'item_id' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[A-Za-z0-9._:-]{1,80}$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'enabled' => array( 'type' => 'boolean', 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
					'target_amount' => array( 'type' => 'number', 'default' => 0, 'minimum' => 0, 'maximum' => 1000000, 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/preferences',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_preferences' ),
				'permission_callback' => array( $this, 'can_use_workspace' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contract_schema' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function can_use_workspace() {
		return get_current_user_id() > 0 && current_user_can( 'read' );
	}

	public function get_workspace() {
		return $this->private_response( $this->read_workspace() );
	}

	public function save_item( $request ) {
		$item = $this->sanitize_item( $request->get_json_params() );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$workspace = $this->read_workspace();
		foreach ( $workspace['items'] as $existing ) {
			if ( $existing['id'] === $item['id'] && isset( $existing['watch'] ) ) {
				$item['watch'] = $existing['watch'];
				break;
			}
		}
		$items     = array_values(
			array_filter(
				$workspace['items'],
				static function ( $existing ) use ( $item ) {
					return $existing['id'] !== $item['id'];
				}
			)
		);
		array_unshift( $items, $item );
		$workspace['items'] = array_slice( $items, 0, self::MAX_ITEMS );
		$this->write_workspace( $workspace );
		return $this->private_response( $workspace, 201 );
	}

	public function delete_item( $request ) {
		$item_id   = (string) $request->get_param( 'item_id' );
		$workspace = $this->read_workspace();
		$workspace['items'] = array_values(
			array_filter(
				$workspace['items'],
				static function ( $item ) use ( $item_id ) {
					return $item['id'] !== $item_id;
				}
			)
		);
		$this->write_workspace( $workspace );
		return $this->private_response( $workspace );
	}

	public function update_watch( $request ) {
		$item_id   = (string) $request->get_param( 'item_id' );
		$workspace = $this->read_workspace();
		$found     = false;
		foreach ( $workspace['items'] as &$item ) {
			if ( $item['id'] !== $item_id ) {
				continue;
			}
			$found         = true;
			$item['watch'] = array(
				'enabled'          => (bool) $request->get_param( 'enabled' ),
				'target_amount'    => round( max( 0, (float) $request->get_param( 'target_amount' ) ), 2 ),
				'delivery_enabled' => false,
				'status'           => (bool) $request->get_param( 'enabled' ) ? 'awaiting_live_supplier' : 'off',
			);
			break;
		}
		unset( $item );
		if ( ! $found ) {
			return new WP_Error( 'tra_vel_workspace_item_missing', 'Saved item was not found.', array( 'status' => 404 ) );
		}
		$this->write_workspace( $workspace );
		return $this->private_response( $workspace );
	}

	public function update_preferences( $request ) {
		$preferences = $this->sanitize_preferences( $request->get_json_params() );
		if ( is_wp_error( $preferences ) ) {
			return $preferences;
		}
		$workspace                = $this->read_workspace();
		$workspace['preferences'] = $preferences;
		$this->write_workspace( $workspace );
		return $this->private_response( $workspace );
	}

	public function clear_workspace() {
		delete_user_meta( get_current_user_id(), self::META_KEY );
		return $this->private_response( $this->default_workspace() );
	}

	public function get_contract_schema() {
		$path = TRA_VEL_V2_PATH . '/assets/data/traveler-workspace.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_workspace_schema_missing', 'Workspace schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_workspace_schema_invalid', 'Workspace schema is invalid.', array( 'status' => 500 ) );
	}

	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tra-vel-traveler-workspace',
			'type'       => 'object',
			'properties' => array(
				'version'     => array( 'type' => 'integer', 'readonly' => true ),
				'items'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'readonly' => true ),
				'preferences' => array( 'type' => 'object', 'readonly' => true ),
				'meta'        => array( 'type' => 'object', 'readonly' => true ),
			),
		);
		return $this->schema;
	}

	private function sanitize_item( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_item', 'Saved item must be a JSON object.', array( 'status' => 400 ) );
		}
		$kind = sanitize_key( isset( $input['kind'] ) ? $input['kind'] : '' );
		if ( ! in_array( $kind, array( 'destination', 'route', 'flight', 'hotel', 'package' ), true ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_kind', 'Saved item kind is not supported.', array( 'status' => 400 ) );
		}
		$external_id = preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) ( isset( $input['external_id'] ) ? $input['external_id'] : '' ) );
		$title       = sanitize_text_field( (string) ( isset( $input['title'] ) ? $input['title'] : '' ) );
		if ( '' === $external_id || strlen( $external_id ) > 60 || '' === $title || $this->text_length( $title ) > 160 ) {
			return new WP_Error( 'tra_vel_workspace_required_fields', 'Saved item id and title are required.', array( 'status' => 400 ) );
		}
		$currency = strtoupper( sanitize_text_field( (string) ( isset( $input['currency'] ) ? $input['currency'] : 'USD' ) ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'ILS' ), true ) ) {
			$currency = 'USD';
		}
		$data_mode = sanitize_key( (string) ( isset( $input['data_mode'] ) ? $input['data_mode'] : 'demo' ) );
		if ( ! in_array( $data_mode, array( 'demo', 'mixed', 'live', 'editorial' ), true ) ) {
			$data_mode = 'demo';
		}
		return array(
			'id'           => $kind . ':' . $external_id,
			'kind'         => $kind,
			'external_id'  => $external_id,
			'title'        => $title,
			'subtitle'     => $this->limit_text( isset( $input['subtitle'] ) ? $input['subtitle'] : '', 240 ),
			'destination'  => $this->limit_text( isset( $input['destination'] ) ? $input['destination'] : '', 80 ),
			'route'        => $this->limit_text( isset( $input['route'] ) ? $input['route'] : '', 160 ),
			'price_label'  => $this->limit_text( isset( $input['price_label'] ) ? $input['price_label'] : '', 40 ),
			'price_amount' => round( min( 1000000, max( 0, (float) ( isset( $input['price_amount'] ) ? $input['price_amount'] : 0 ) ) ), 2 ),
			'currency'     => $currency,
			'data_mode'    => $data_mode,
			'href'         => $this->sanitize_internal_url( isset( $input['href'] ) ? $input['href'] : '/' ),
			'saved_at'     => current_time( 'c' ),
			'watch'        => array( 'enabled' => false, 'target_amount' => 0, 'delivery_enabled' => false, 'status' => 'off' ),
		);
	}

	private function sanitize_preferences( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_preferences', 'Preferences must be a JSON object.', array( 'status' => 400 ) );
		}
		$airport = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( isset( $input['home_airport'] ) ? $input['home_airport'] : 'TLV' ) ) );
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $airport ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_airport', 'Home airport must be a three-letter IATA code.', array( 'status' => 400 ) );
		}
		$currency = strtoupper( sanitize_text_field( (string) ( isset( $input['currency'] ) ? $input['currency'] : 'USD' ) ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'ILS' ), true ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_currency', 'Workspace currency is not supported.', array( 'status' => 400 ) );
		}
		$party_style = sanitize_key( (string) ( isset( $input['party_style'] ) ? $input['party_style'] : 'couple' ) );
		if ( ! in_array( $party_style, array( 'solo', 'couple', 'family', 'friends' ), true ) ) {
			$party_style = 'couple';
		}
		$allowed_priorities = array( 'price', 'comfort', 'flexibility', 'location', 'direct', 'family' );
		$priorities = array_values( array_unique( array_intersect( $allowed_priorities, array_map( 'sanitize_key', (array) ( isset( $input['priorities'] ) ? $input['priorities'] : array() ) ) ) ) );
		return array(
			'home_airport' => $airport,
			'currency'     => $currency,
			'budget'       => min( 1000000, max( 0, absint( isset( $input['budget'] ) ? $input['budget'] : 0 ) ) ),
			'max_stops'    => min( 3, max( 0, absint( isset( $input['max_stops'] ) ? $input['max_stops'] : 1 ) ) ),
			'party_style'  => $party_style,
			'priorities'   => array_slice( $priorities, 0, 6 ),
		);
	}

	private function sanitize_internal_url( $url ) {
		$url       = esc_url_raw( (string) $url );
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		if ( $url_host && strtolower( $url_host ) !== strtolower( (string) $home_host ) ) {
			return home_url( '/' );
		}
		return $url ? $url : home_url( '/' );
	}

	private function default_workspace() {
		return array(
			'version'     => 1,
			'items'       => array(),
			'preferences' => array( 'home_airport' => 'TLV', 'currency' => 'USD', 'budget' => 0, 'max_stops' => 1, 'party_style' => 'couple', 'priorities' => array( 'price', 'comfort' ) ),
			'meta'        => array( 'storage' => 'wordpress_user_meta', 'max_items' => self::MAX_ITEMS, 'price_watch_delivery_enabled' => false, 'sensitive_data_allowed' => false ),
		);
	}

	private function read_workspace() {
		$stored  = get_user_meta( get_current_user_id(), self::META_KEY, true );
		$default = $this->default_workspace();
		if ( ! is_array( $stored ) ) {
			return $default;
		}
		$default['items']       = array_slice( array_values( isset( $stored['items'] ) && is_array( $stored['items'] ) ? $stored['items'] : array() ), 0, self::MAX_ITEMS );
		$default['preferences'] = isset( $stored['preferences'] ) && is_array( $stored['preferences'] ) ? array_merge( $default['preferences'], $stored['preferences'] ) : $default['preferences'];
		return $default;
	}

	private function write_workspace( $workspace ) {
		update_user_meta( get_current_user_id(), self::META_KEY, $workspace );
	}

	private function private_response( $workspace, $status = 200 ) {
		$response = new WP_REST_Response( $workspace, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		return $response;
	}

	private function limit_text( $value, $length ) {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}

add_action(
	'rest_api_init',
	static function () {
		$controller = new Tra_Vel_V2_Traveler_Workspace_Controller();
		$controller->register_routes();
	}
);
