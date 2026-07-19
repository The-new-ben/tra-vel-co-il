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
			'/' . $this->rest_base . '/sync',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'sync_workspace' ),
				'permission_callback' => array( $this, 'can_use_workspace' ),
				'args'                => array(
					'items' => array(
						'type'              => 'array',
						'required'          => true,
						'maxItems'          => self::MAX_ITEMS,
						'items'             => array( 'type' => 'object' ),
						'validate_callback' => 'rest_validate_request_arg',
					),
					'preferences' => array(
						'type'              => 'object',
						'required'          => false,
						'validate_callback' => 'rest_validate_request_arg',
					),
					'deleted_item_ids' => array(
						'type'              => 'array',
						'required'          => false,
						'default'           => array(),
						'maxItems'          => self::MAX_ITEMS,
						'uniqueItems'       => true,
						'items'             => array(
							'type'    => 'string',
							'pattern' => '^[A-Za-z0-9._:-]{1,80}$',
						),
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
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

		$snapshot  = $this->read_workspace_snapshot();
		$workspace = $snapshot['workspace'];
		$existing_item = false;
		foreach ( $workspace['items'] as $existing ) {
			if ( $existing['id'] === $item['id'] && isset( $existing['watch'] ) ) {
				$item['watch'] = $existing['watch'];
				$existing_item = true;
				break;
			}
		}
		if ( ! $existing_item && count( $workspace['items'] ) >= self::MAX_ITEMS ) {
			return new WP_Error( 'tra_vel_workspace_capacity', 'The account workspace already contains 50 items.', array( 'status' => 409 ) );
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
		$workspace['items'] = $items;
		$written = $this->write_workspace( $workspace, $snapshot );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $this->private_response( $workspace, 201 );
	}

	/**
	 * Merge a bounded browser snapshot into the current account workspace.
	 *
	 * Tombstones win over submitted items. Existing server items that are not
	 * mentioned remain present, and an existing server watch always remains the
	 * authority for a matching item.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_workspace( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_sync', 'Workspace sync must be a JSON object.', array( 'status' => 400 ) );
		}

		$allowed_keys = array( 'items', 'preferences', 'deleted_item_ids' );
		if ( array_diff( array_keys( $input ), $allowed_keys ) ) {
			return new WP_Error( 'tra_vel_workspace_unknown_sync_field', 'Workspace sync contains an unsupported field.', array( 'status' => 400 ) );
		}
		if ( ! array_key_exists( 'items', $input ) || ! is_array( $input['items'] ) || count( $input['items'] ) > self::MAX_ITEMS ) {
			return new WP_Error( 'tra_vel_workspace_invalid_sync_items', 'Workspace sync items must be an array containing no more than 50 items.', array( 'status' => 400 ) );
		}

		$deleted_input = isset( $input['deleted_item_ids'] ) ? $input['deleted_item_ids'] : array();
		if ( ! is_array( $deleted_input ) || count( $deleted_input ) > self::MAX_ITEMS ) {
			return new WP_Error( 'tra_vel_workspace_invalid_tombstones', 'Workspace sync tombstones must be an array containing no more than 50 item ids.', array( 'status' => 400 ) );
		}
		$deleted = array();
		foreach ( $deleted_input as $item_id ) {
			if ( ! is_string( $item_id ) || 1 !== preg_match( '/^[A-Za-z0-9._:-]{1,80}$/', $item_id ) ) {
				return new WP_Error( 'tra_vel_workspace_invalid_tombstone', 'Workspace sync contains an invalid deleted item id.', array( 'status' => 400 ) );
			}
			if ( isset( $deleted[ $item_id ] ) ) {
				return new WP_Error( 'tra_vel_workspace_duplicate_tombstone', 'Workspace sync contains a duplicate deleted item id.', array( 'status' => 400 ) );
			}
			$deleted[ $item_id ] = true;
		}

		$submitted = array();
		$order     = array();
		foreach ( $input['items'] as $raw_item ) {
			$item = $this->sanitize_item( $raw_item );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			if ( isset( $submitted[ $item['id'] ] ) ) {
				return new WP_Error( 'tra_vel_workspace_duplicate_item', 'Workspace sync contains the same item more than once.', array( 'status' => 400 ) );
			}
			$submitted[ $item['id'] ] = $item;
			$order[]                   = $item['id'];
		}

		$snapshot  = $this->read_workspace_snapshot();
		$workspace = $snapshot['workspace'];
		$server    = array();
		foreach ( $workspace['items'] as $server_item ) {
			if ( ! isset( $deleted[ $server_item['id'] ] ) ) {
				$server[ $server_item['id'] ] = $server_item;
			}
		}

		$merged = array();
		foreach ( $order as $item_id ) {
			if ( isset( $deleted[ $item_id ] ) ) {
				continue;
			}
			$item = $submitted[ $item_id ];
			if ( isset( $server[ $item_id ]['watch'] ) ) {
				$item['watch'] = $server[ $item_id ]['watch'];
			}
			$merged[ $item_id ] = $item;
		}
		foreach ( $server as $item_id => $server_item ) {
			if ( ! isset( $merged[ $item_id ] ) ) {
				$merged[ $item_id ] = $server_item;
			}
		}
		if ( count( $merged ) > self::MAX_ITEMS ) {
			return new WP_Error( 'tra_vel_workspace_sync_capacity', 'Workspace sync would exceed the 50-item account limit.', array( 'status' => 409 ) );
		}

		$workspace['items'] = array_values( $merged );
		if ( array_key_exists( 'preferences', $input ) ) {
			$preferences = $this->sanitize_preferences( $input['preferences'] );
			if ( is_wp_error( $preferences ) ) {
				return $preferences;
			}
			$workspace['preferences'] = $preferences;
		}

		$written = $this->write_workspace( $workspace, $snapshot );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $this->private_response( $workspace );
	}

	public function delete_item( $request ) {
		$item_id   = (string) $request->get_param( 'item_id' );
		$snapshot  = $this->read_workspace_snapshot();
		$workspace = $snapshot['workspace'];
		$workspace['items'] = array_values(
			array_filter(
				$workspace['items'],
				static function ( $item ) use ( $item_id ) {
					return $item['id'] !== $item_id;
				}
			)
		);
		$written = $this->write_workspace( $workspace, $snapshot );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $this->private_response( $workspace );
	}

	public function update_watch( $request ) {
		$item_id   = (string) $request->get_param( 'item_id' );
		$snapshot  = $this->read_workspace_snapshot();
		$workspace = $snapshot['workspace'];
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
		$written = $this->write_workspace( $workspace, $snapshot );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $this->private_response( $workspace );
	}

	public function update_preferences( $request ) {
		$preferences = $this->sanitize_preferences( $request->get_json_params() );
		if ( is_wp_error( $preferences ) ) {
			return $preferences;
		}
		$snapshot                 = $this->read_workspace_snapshot();
		$workspace                = $snapshot['workspace'];
		$workspace['preferences'] = $preferences;
		$written = $this->write_workspace( $workspace, $snapshot );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $this->private_response( $workspace );
	}

	public function clear_workspace() {
		$user_id  = get_current_user_id();
		$snapshot = $this->read_workspace_snapshot();
		if ( ! $snapshot['exists'] ) {
			return $this->private_response( $this->default_workspace() );
		}
		if ( empty( $snapshot['stored'] ) ) {
			return $this->conflict_error();
		}

		$deleted = delete_user_meta( $user_id, self::META_KEY, $snapshot['stored'] );
		if ( ! $deleted ) {
			$current_exists = metadata_exists( 'user', $user_id, self::META_KEY );
			$current        = $current_exists ? get_user_meta( $user_id, self::META_KEY, true ) : null;
			if ( $current_exists && $current === $snapshot['stored'] ) {
				return $this->write_error();
			}
			if ( $current_exists ) {
				return $this->conflict_error();
			}
		}
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

	private function sanitize_item( $input, $preserve_server_fields = false ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_item', 'Saved item must be a JSON object.', array( 'status' => 400 ) );
		}
		foreach ( array( 'kind', 'external_id', 'title' ) as $required_key ) {
			if ( ! isset( $input[ $required_key ] ) || ! is_scalar( $input[ $required_key ] ) ) {
				return new WP_Error( 'tra_vel_workspace_required_fields', 'Saved item id and title are required.', array( 'status' => 400 ) );
			}
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
		$currency_input = isset( $input['currency'] ) && is_scalar( $input['currency'] ) ? $input['currency'] : 'USD';
		$currency       = strtoupper( sanitize_text_field( (string) $currency_input ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'ILS' ), true ) ) {
			$currency = 'USD';
		}
		$data_mode_input = isset( $input['data_mode'] ) && is_scalar( $input['data_mode'] ) ? $input['data_mode'] : 'demo';
		$data_mode       = sanitize_key( (string) $data_mode_input );
		if ( ! in_array( $data_mode, array( 'demo', 'mixed', 'live', 'editorial' ), true ) ) {
			$data_mode = 'demo';
		}
		// Browser JSON is not supplier provenance. A future trusted server path may
		// promote a separately verified record, but this workspace endpoint cannot.
		if ( 'live' === $data_mode ) {
			$data_mode = 'mixed';
		}
		$saved_at = current_time( 'c' );
		$watch    = $this->default_watch();
		if ( $preserve_server_fields ) {
			$saved_at = $this->sanitize_saved_at( isset( $input['saved_at'] ) ? $input['saved_at'] : '' );
			$watch    = $this->sanitize_stored_watch( isset( $input['watch'] ) ? $input['watch'] : array() );
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
			'price_amount' => round( min( 1000000, max( 0, (float) ( isset( $input['price_amount'] ) && is_numeric( $input['price_amount'] ) ? $input['price_amount'] : 0 ) ) ), 2 ),
			'currency'     => $currency,
			'data_mode'    => $data_mode,
			'href'         => $this->sanitize_internal_url( isset( $input['href'] ) ? $input['href'] : '/' ),
			'saved_at'     => $saved_at,
			'watch'        => $watch,
		);
	}

	private function sanitize_preferences( $input, $strict = true ) {
		if ( ! is_array( $input ) ) {
			return $strict
				? new WP_Error( 'tra_vel_workspace_invalid_preferences', 'Preferences must be a JSON object.', array( 'status' => 400 ) )
				: $this->default_preferences();
		}
		$defaults = $this->default_preferences();
		foreach ( array( 'home_airport', 'currency', 'party_style' ) as $scalar_key ) {
			if ( $strict && isset( $input[ $scalar_key ] ) && ! is_scalar( $input[ $scalar_key ] ) ) {
				return new WP_Error( 'tra_vel_workspace_invalid_preferences', 'Workspace preferences contain an invalid value.', array( 'status' => 400 ) );
			}
		}
		foreach ( array( 'budget', 'max_stops' ) as $numeric_key ) {
			if ( $strict && isset( $input[ $numeric_key ] ) && ! is_numeric( $input[ $numeric_key ] ) ) {
				return new WP_Error( 'tra_vel_workspace_invalid_preferences', 'Workspace preferences contain an invalid numeric value.', array( 'status' => 400 ) );
			}
		}
		$airport_input = isset( $input['home_airport'] ) && is_scalar( $input['home_airport'] ) ? $input['home_airport'] : $defaults['home_airport'];
		$airport       = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $airport_input ) );
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $airport ) ) {
			if ( $strict ) {
				return new WP_Error( 'tra_vel_workspace_invalid_airport', 'Home airport must be a three-letter IATA code.', array( 'status' => 400 ) );
			}
			$airport = $defaults['home_airport'];
		}
		$currency_input = isset( $input['currency'] ) && is_scalar( $input['currency'] ) ? $input['currency'] : $defaults['currency'];
		$currency       = strtoupper( sanitize_text_field( (string) $currency_input ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'ILS' ), true ) ) {
			if ( $strict ) {
				return new WP_Error( 'tra_vel_workspace_invalid_currency', 'Workspace currency is not supported.', array( 'status' => 400 ) );
			}
			$currency = $defaults['currency'];
		}
		$party_input = isset( $input['party_style'] ) && is_scalar( $input['party_style'] ) ? $input['party_style'] : $defaults['party_style'];
		$party_style = sanitize_key( (string) $party_input );
		if ( ! in_array( $party_style, array( 'solo', 'couple', 'family', 'friends' ), true ) ) {
			$party_style = $defaults['party_style'];
		}
		$allowed_priorities = array( 'price', 'comfort', 'flexibility', 'location', 'direct', 'family' );
		if ( $strict && isset( $input['priorities'] ) && ! is_array( $input['priorities'] ) ) {
			return new WP_Error( 'tra_vel_workspace_invalid_priorities', 'Workspace priorities must be an array.', array( 'status' => 400 ) );
		}
		$priority_input = isset( $input['priorities'] ) && is_array( $input['priorities'] ) ? array_slice( $input['priorities'], 0, 50 ) : $defaults['priorities'];
		$priority_input = array_values( array_filter( $priority_input, 'is_scalar' ) );
		$priorities     = array_values( array_unique( array_intersect( $allowed_priorities, array_map( 'sanitize_key', $priority_input ) ) ) );
		$budget_input   = isset( $input['budget'] ) && is_numeric( $input['budget'] ) ? $input['budget'] : $defaults['budget'];
		$stops_input    = isset( $input['max_stops'] ) && is_numeric( $input['max_stops'] ) ? $input['max_stops'] : $defaults['max_stops'];
		return array(
			'home_airport' => $airport,
			'currency'     => $currency,
			'budget'       => min( 1000000, max( 0, (int) $budget_input ) ),
			'max_stops'    => min( 3, max( 0, (int) $stops_input ) ),
			'party_style'  => $party_style,
			'priorities'   => array_slice( $priorities, 0, 6 ),
		);
	}

	private function default_watch() {
		return array( 'enabled' => false, 'target_amount' => 0, 'delivery_enabled' => false, 'status' => 'off' );
	}

	private function sanitize_stored_watch( $watch ) {
		if ( ! is_array( $watch ) ) {
			return $this->default_watch();
		}
		$raw_enabled = isset( $watch['enabled'] ) ? $watch['enabled'] : false;
		$enabled     = true === $raw_enabled || 1 === $raw_enabled || '1' === $raw_enabled || ( is_string( $raw_enabled ) && 'true' === strtolower( $raw_enabled ) );
		$target      = isset( $watch['target_amount'] ) && is_numeric( $watch['target_amount'] ) ? $watch['target_amount'] : 0;
		return array(
			'enabled'          => $enabled,
			'target_amount'    => $enabled ? round( min( 1000000, max( 0, (float) $target ) ), 2 ) : 0,
			'delivery_enabled' => false,
			'status'           => $enabled ? 'awaiting_live_supplier' : 'off',
		);
	}

	private function sanitize_saved_at( $value ) {
		$value = $this->limit_text( $value, 40 );
		$timestamp = '' !== $value ? strtotime( $value ) : false;
		return false !== $timestamp ? gmdate( 'c', $timestamp ) : current_time( 'c' );
	}

	private function sanitize_internal_url( $url ) {
		$url       = esc_url_raw( is_scalar( $url ) ? (string) $url : '' );
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
			'preferences' => $this->default_preferences(),
			'meta'        => array( 'storage' => 'wordpress_user_meta', 'max_items' => self::MAX_ITEMS, 'price_watch_delivery_enabled' => false, 'sensitive_data_allowed' => false ),
		);
	}

	private function default_preferences() {
		return array( 'home_airport' => 'TLV', 'currency' => 'USD', 'budget' => 0, 'max_stops' => 1, 'party_style' => 'couple', 'priorities' => array( 'price', 'comfort' ) );
	}

	private function normalize_workspace( $stored ) {
		$default = $this->default_workspace();
		if ( ! is_array( $stored ) ) {
			return $default;
		}
		$items = array();
		foreach ( isset( $stored['items'] ) && is_array( $stored['items'] ) ? $stored['items'] : array() as $stored_item ) {
			$item = $this->sanitize_item( $stored_item, true );
			if ( is_wp_error( $item ) || isset( $items[ $item['id'] ] ) ) {
				continue;
			}
			$items[ $item['id'] ] = $item;
			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
		}
		$default['items']       = array_values( $items );
		$default['preferences'] = $this->sanitize_preferences( isset( $stored['preferences'] ) ? $stored['preferences'] : array(), false );
		return $default;
	}

	private function read_workspace() {
		return $this->normalize_workspace( get_user_meta( get_current_user_id(), self::META_KEY, true ) );
	}

	private function read_workspace_snapshot() {
		$user_id = get_current_user_id();
		$exists  = metadata_exists( 'user', $user_id, self::META_KEY );
		$stored  = $exists ? get_user_meta( $user_id, self::META_KEY, true ) : '';
		return array(
			'exists'    => $exists,
			'stored'    => $stored,
			'workspace' => $this->normalize_workspace( $stored ),
		);
	}

	/**
	 * Commit a workspace only if the exact user-meta snapshot is still current.
	 *
	 * WordPress does not qualify update_user_meta() with an empty previous value,
	 * so an existing empty legacy row fails closed instead of risking an
	 * unqualified overwrite. A repair can remove that malformed row explicitly.
	 *
	 * @param array $workspace Normalized target workspace.
	 * @param array $snapshot Exact raw user-meta snapshot read for this mutation.
	 * @return true|WP_Error
	 */
	private function write_workspace( $workspace, $snapshot ) {
		$user_id = get_current_user_id();
		if ( $snapshot['exists'] && $workspace === $snapshot['stored'] ) {
			return true;
		}

		if ( ! $snapshot['exists'] ) {
			$updated = add_user_meta( $user_id, self::META_KEY, $workspace, true );
		} elseif ( empty( $snapshot['stored'] ) ) {
			return $this->conflict_error();
		} else {
			$updated = update_user_meta( $user_id, self::META_KEY, $workspace, $snapshot['stored'] );
		}
		if ( false !== $updated ) {
			return true;
		}

		$current_exists = metadata_exists( 'user', $user_id, self::META_KEY );
		$current        = $current_exists ? get_user_meta( $user_id, self::META_KEY, true ) : null;
		if ( $current_exists && $workspace === $current ) {
			return true;
		}
		if ( $current_exists !== $snapshot['exists'] || ( $current_exists && $current !== $snapshot['stored'] ) ) {
			return $this->conflict_error();
		}
		return $this->write_error();
	}

	private function write_error() {
		return new WP_Error( 'tra_vel_workspace_write_failed', 'The private workspace could not be saved.', array( 'status' => 500 ) );
	}

	private function conflict_error() {
		return new WP_Error( 'tra_vel_workspace_conflict', 'The private workspace changed in another request. Reload it and retry.', array( 'status' => 409 ) );
	}

	private function private_response( $workspace, $status = 200 ) {
		$response = new WP_REST_Response( $workspace, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
		return $response;
	}

	private function limit_text( $value, $length ) {
		$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
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
