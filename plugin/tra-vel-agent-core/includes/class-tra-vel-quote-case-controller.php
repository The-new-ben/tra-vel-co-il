<?php
/**
 * REST control plane for durable traveler quote cases and the operator queue.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Quote_Case_Controller extends WP_REST_Controller {
	const OWNER_COOKIE = '__Host-tra_vel_quote_owner';

	/** @var Tra_Vel_Quote_Case_Store */
	private $store;

	/** @var Tra_Vel_Agent_Store */
	private $agent_store;

	public function __construct( $store = null, $agent_store = null ) {
		$this->namespace   = 'tra-vel-agent/v1';
		$this->rest_base   = 'quote-cases';
		$this->store       = $store ? $store : new Tra_Vel_Quote_Case_Store();
		$this->agent_store = $agent_store ? $agent_store : new Tra_Vel_Agent_Store();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/schema/quote-case',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_case_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/quote-case-event',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_event_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/runs/(?P<run_id>[0-9a-fA-F-]{36})/quote-cases',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_case' ),
				'permission_callback' => array( $this, 'can_create_case' ),
				'args'                => array_merge( array( 'run_id' => $this->uuid_arg() ), $this->create_args() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_owned_cases' ),
				'permission_callback' => array( $this, 'can_use_store' ),
				'args'                => array( 'per_page' => $this->per_page_arg() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_case' ),
				'permission_callback' => array( $this, 'can_access_case' ),
				'args'                => array( 'case_id' => $this->uuid_arg() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_case_events' ),
				'permission_callback' => array( $this, 'can_access_case' ),
				'args'                => array(
					'case_id' => $this->uuid_arg(),
					'after'   => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'limit'   => array( 'type' => 'integer', 'default' => Tra_Vel_Quote_Case_Store::EVENT_PAGE_SIZE, 'minimum' => 1, 'maximum' => Tra_Vel_Quote_Case_Store::EVENT_PAGE_SIZE, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_case' ),
				'permission_callback' => array( $this, 'can_access_case' ),
				'args'                => array_merge( array( 'case_id' => $this->uuid_arg() ), $this->mutation_args() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/claim',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'claim_case' ),
				'permission_callback' => array( $this, 'can_claim_case' ),
				'args'                => array_merge( array( 'case_id' => $this->uuid_arg() ), $this->mutation_args() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/handoffs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'prepare_handoff' ),
				'permission_callback' => array( $this, 'can_access_case' ),
				'args'                => array_merge(
					array( 'case_id' => $this->uuid_arg() ),
					$this->mutation_args(),
					array( 'channel' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'whatsapp' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ) )
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/operator/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_operator_cases' ),
				'permission_callback' => array( $this, 'can_view_queue' ),
				'args'                => array(
					'status'   => array( 'type' => 'string', 'default' => '', 'enum' => array_merge( array( '' ), Tra_Vel_Quote_Case_Policy::statuses() ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'per_page' => $this->per_page_arg(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/operator/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_operator_case' ),
				'permission_callback' => array( $this, 'can_view_queue' ),
				'args'                => array( 'case_id' => $this->uuid_arg() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/operator/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_operator_case_events' ),
				'permission_callback' => array( $this, 'can_view_queue' ),
				'args'                => array(
					'case_id' => $this->uuid_arg(),
					'after'   => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'limit'   => array( 'type' => 'integer', 'default' => Tra_Vel_Quote_Case_Store::EVENT_PAGE_SIZE, 'minimum' => 1, 'maximum' => Tra_Vel_Quote_Case_Store::EVENT_PAGE_SIZE, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/operator/' . $this->rest_base . '/(?P<case_id>[0-9a-fA-F-]{36})/transitions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'transition_case' ),
				'permission_callback' => array( $this, 'can_manage_queue' ),
				'args'                => array_merge(
					array( 'case_id' => $this->uuid_arg() ),
					$this->mutation_args(),
					array( 'status' => array( 'type' => 'string', 'required' => true, 'enum' => Tra_Vel_Quote_Case_Policy::statuses(), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ) )
				),
			)
		);
	}

	public function can_create_case( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			return new WP_Error( 'tra_vel_quote_case_https_required', 'Assisted quote requests require HTTPS.', array( 'status' => 403 ) );
		}
		$run = $this->agent_store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run ) {
			return new WP_Error( 'tra_vel_agent_run_missing', 'Agent run not found.', array( 'status' => 404 ) );
		}
		return $this->agent_store->can_access( $run, $this->run_cookie_token( $run['run_uuid'] ), get_current_user_id() ) ? true : new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run does not belong to the current visitor.', array( 'status' => 403 ) );
	}

	public function create_case( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$run       = $this->agent_store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run || ! $this->agent_store->can_access( $run, $this->run_cookie_token( $request->get_param( 'run_id' ) ), get_current_user_id() ) ) {
			return new WP_Error( 'tra_vel_agent_run_forbidden', 'The private source plan changed owner before quote creation began.', array( 'status' => 403 ) );
		}
		$trip      = is_array( $run['trip_request'] ?? null ) ? $run['trip_request'] : array();
		$expected  = (string) $request->get_param( 'expected_request_id' );
		$revision  = (int) $request->get_param( 'expected_revision' );
		if ( ! hash_equals( (string) ( $trip['request_id'] ?? '' ), $expected ) || (int) ( $trip['revision'] ?? 0 ) !== $revision ) {
			return new WP_Error( 'tra_vel_quote_case_request_changed', 'The private plan changed before the quote request was submitted.', array( 'status' => 409 ) );
		}
		if ( true !== rest_sanitize_boolean( $request->get_param( 'consent' ) ) || Tra_Vel_Quote_Case_Policy::CONSENT_VERSION !== $request->get_param( 'consent_version' ) ) {
			return new WP_Error( 'tra_vel_quote_case_consent_required', 'Explicit assisted-quote and retention consent is required.', array( 'status' => 400 ) );
		}
		$acquisition = Tra_Vel_Quote_Case_Policy::sanitize_acquisition( $request->get_param( 'acquisition' ) );
		$contact     = Tra_Vel_Quote_Case_Policy::sanitize_contact( $request->get_param( 'contact' ), 'tra_vel_quote_case' );
		if ( is_wp_error( $contact ) ) {
			return $contact;
		}
		$rate = $this->consume_create_limit( $run['run_uuid'] );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$principal = $this->principal( true );
		$result    = $this->store->create_from_run( $run, $principal, $request->get_param( 'consent_version' ), $request->get_param( 'idempotency_key' ), $acquisition, $contact );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['created'] ) ) {
			/**
			 * Announce one durably committed assisted-quote case. The store has
			 * already committed and released its transaction, and idempotent
			 * replays never reach this branch. Listeners must stay idempotent
			 * and must never receive traveler personal data from this payload.
			 */
			do_action(
				'tra_vel_quote_case_created',
				(string) ( $result['case']['case_uuid'] ?? '' ),
				(string) ( $result['case']['reference_code'] ?? '' ),
				array(
					'owner_user_id' => (int) ( $result['case']['owner_user_id'] ?? 0 ),
					'status'        => (string) ( $result['case']['status'] ?? '' ),
					'service_mode'  => (string) ( $result['case']['service_mode'] ?? 'assisted_quote' ),
					'case_version'  => (int) ( $result['case']['case_version'] ?? 1 ),
				)
			);
		}
		if ( ! $this->store->can_access( $result['case'], get_current_user_id(), $principal['token_hash'] ) ) {
			$recovered = $this->store->recover_owner_from_run( $result['case'], $run['run_uuid'], $principal );
			if ( is_wp_error( $recovered ) ) {
				return $recovered;
			}
			$result['case'] = $recovered['case'];
		}
		$response = $this->private_response(
			array(
				'case'     => $this->traveler_case( $result['case'] ),
				'replayed' => (bool) $result['replayed'],
			),
			$result['created'] ? 201 : 200
		);
		return ! empty( $principal['new_token'] ) ? $this->attach_owner_cookie( $response, $principal['token'] ) : $response;
	}

	public function list_owned_cases( WP_REST_Request $request ) {
		$principal = $this->principal( false );
		$read_error = '';
		$cases     = $this->store->list_owned( get_current_user_id(), $principal['token_hash'], (int) $request->get_param( 'per_page' ), $read_error );
		if ( '' !== $read_error ) {
			return new WP_Error( 'tra_vel_quote_case_list_read_failed', 'Assistance requests are temporarily unavailable.', array( 'status' => 503 ) );
		}
		$resume    = $this->resume_availability( $cases );
		return $this->private_response(
			array(
				'cases' => array_map(
					function ( $case ) use ( $resume ) {
						return $this->public_case( $case, 1, ! empty( $resume[ $case['case_uuid'] ] ) );
					},
					$cases
				),
				'meta'  => array(
					'count'   => count( $cases ),
					'storage' => get_current_user_id() > 0
						? ( $principal['token_hash'] ? 'account_and_private_browser' : 'account' )
						: 'private_browser_owner',
				),
			)
		);
	}

	public function can_access_case( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$case = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		$principal = $this->principal( false );
		return $this->store->can_access( $case, get_current_user_id(), $principal['token_hash'] ) ? true : new WP_Error( 'tra_vel_quote_case_forbidden', 'This private quote case does not belong to the current traveler.', array( 'status' => 403 ) );
	}

	public function get_case( WP_REST_Request $request ) {
		$case = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		$principal = $this->principal( false );
		if ( ! $case || ! $this->store->can_access( $case, get_current_user_id(), $principal['token_hash'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'This private quote case changed owner before it could be read.', array( 'status' => 403 ) );
		}
		return $this->private_response( array( 'case' => $this->traveler_case( $case ) ) );
	}

	public function get_case_events( WP_REST_Request $request ) {
		$case   = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		$principal = $this->principal( false );
		if ( ! $case || ! $this->store->can_access( $case, get_current_user_id(), $principal['token_hash'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_forbidden', 'This private quote case changed owner before its events could be read.', array( 'status' => 403 ) );
		}
		$page = $this->store->get_event_page( $case['id'], (int) $request->get_param( 'after' ), false, (int) $request->get_param( 'limit' ) );
		return $this->private_response( array( 'case_id' => $case['case_uuid'], 'events' => $page['events'], 'last_sequence' => $page['last_sequence'], 'has_more' => $page['has_more'] ) );
	}

	public function cancel_case( WP_REST_Request $request ) {
		$principal = $this->principal( false );
		$result = $this->store->cancel( $request->get_param( 'case_id' ), (int) $request->get_param( 'expected_version' ), $principal, $request->get_param( 'idempotency_key' ) );
		return is_wp_error( $result ) ? $result : $this->mutation_response( $result );
	}

	public function can_claim_case( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( get_current_user_id() < 1 || ! current_user_can( 'read' ) ) {
			return new WP_Error( 'tra_vel_quote_case_login_required', 'Sign in before linking a guest quote case.', array( 'status' => 401 ) );
		}
		$case      = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		if ( $case && (int) $case['owner_user_id'] === get_current_user_id() ) {
			return true;
		}
		return $case && $this->store->can_access( $case, 0, $this->guest_owner_hash() ) ? true : new WP_Error( 'tra_vel_quote_case_claim_forbidden', 'A matching guest owner token is required.', array( 'status' => 403 ) );
	}

	public function claim_case( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$result = $this->store->claim( $request->get_param( 'case_id' ), (int) $request->get_param( 'expected_version' ), get_current_user_id(), $this->guest_owner_hash(), $request->get_param( 'idempotency_key' ) );
		return is_wp_error( $result ) ? $result : $this->mutation_response( $result );
	}

	public function prepare_handoff( WP_REST_Request $request ) {
		$case    = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		$context = $this->handoff_context( $case );
		$prepared = apply_filters( 'tra_vel_agent_quote_case_prepare_handoff', null, $context, $this->traveler_case( $case ) );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		if ( ! is_array( $prepared ) || empty( $prepared['handoff_url'] ) ) {
			return new WP_Error( 'tra_vel_quote_case_handoff_unavailable', 'No verified owned assisted-contact channel is configured.', array( 'status' => 503 ) );
		}
		$url      = esc_url_raw( (string) $prepared['handoff_url'], array( 'https' ) );
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$user     = wp_parse_url( $url, PHP_URL_USER );
		$password = wp_parse_url( $url, PHP_URL_PASS );
		$provider = sanitize_key( (string) ( $prepared['provider'] ?? '' ) );
		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || 'api.whatsapp.com' !== $host || $user || $password || 'tra-vel-concierge' !== $provider ) {
			return new WP_Error( 'tra_vel_quote_case_handoff_rejected', 'The assisted-contact channel failed the owned-provider allowlist.', array( 'status' => 502 ) );
		}
		$expires_timestamp = strtotime( ! empty( $prepared['expires_at'] ) ? (string) $prepared['expires_at'] : '' );
		if ( false === $expires_timestamp || $expires_timestamp < time() + 30 || $expires_timestamp > time() + 600 ) {
			$expires_timestamp = time() + 300;
		}
		$expires_at    = gmdate( 'c', $expires_timestamp );
		$target_digest = hash( 'sha256', $url );
		$principal = $this->principal( false );
		$result = $this->store->record_handoff( $case['case_uuid'], (int) $request->get_param( 'expected_version' ), $principal, 'whatsapp', $provider, $target_digest, $expires_at, $request->get_param( 'idempotency_key' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['replayed'] ) && ! empty( $result['event']['data']['expires_at'] ) ) {
			$expires_at = (string) $result['event']['data']['expires_at'];
		}
		$response = $this->mutation_payload( $result );
		$response['handoff_url'] = $url;
		$response['expires_at']  = $expires_at;
		return $this->private_response( $response );
	}

	public function can_use_store() {
		return Tra_Vel_Quote_Case_Store::is_ready() && Tra_Vel_Agent_Store::is_ready()
			? true
			: new WP_Error( 'tra_vel_quote_case_store_unavailable', 'Assisted quote storage is temporarily unavailable.', array( 'status' => 503 ) );
	}

	public function can_view_queue() {
		$ready = $this->can_use_store();
		return true === $ready ? current_user_can( 'tra_vel_view_quote_cases' ) : $ready;
	}

	public function can_manage_queue( $request = null ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! current_user_can( 'tra_vel_manage_quote_cases' ) ) {
			return false;
		}
		if ( $request instanceof WP_REST_Request && 'in_review' === $request->get_param( 'status' ) && ! current_user_can( 'tra_vel_assign_quote_cases' ) ) {
			return new WP_Error( 'tra_vel_quote_case_assignment_forbidden', 'Claiming a case for review requires the quote-case assignment capability.', array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_operator_cases( WP_REST_Request $request ) {
		$result = $this->store->list_operator( (string) $request->get_param( 'status' ), (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );
		return $this->private_response(
			array(
				'cases' => array_map(
					function ( $case ) {
						return $this->operator_case( $case, 1 );
					},
					$result['cases']
				),
				'meta'  => array( 'count' => count( $result['cases'] ), 'total' => (int) $result['total'], 'page' => (int) $request->get_param( 'page' ), 'per_page' => (int) $request->get_param( 'per_page' ) ),
			)
		);
	}

	public function get_operator_case( WP_REST_Request $request ) {
		$case = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		return $case ? $this->private_response( array( 'case' => $this->operator_case( $case ) ) ) : new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
	}

	public function get_operator_case_events( WP_REST_Request $request ) {
		$case = $this->store->get_case_by_uuid( $request->get_param( 'case_id' ) );
		if ( ! $case ) {
			return new WP_Error( 'tra_vel_quote_case_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		$page = $this->store->get_event_page( $case['id'], (int) $request->get_param( 'after' ), true, (int) $request->get_param( 'limit' ) );
		return $this->private_response( array( 'case_id' => $case['case_uuid'], 'events' => $page['events'], 'last_sequence' => $page['last_sequence'], 'has_more' => $page['has_more'] ) );
	}

	public function transition_case( WP_REST_Request $request ) {
		$result = $this->store->transition( $request->get_param( 'case_id' ), $request->get_param( 'status' ), (int) $request->get_param( 'expected_version' ), get_current_user_id(), $request->get_param( 'idempotency_key' ) );
		return is_wp_error( $result ) ? $result : $this->mutation_response( $result );
	}

	public function get_case_schema() {
		return $this->schema_response( 'quote-case.schema.json' );
	}

	public function get_event_schema() {
		return $this->schema_response( 'quote-case-event.schema.json' );
	}

	public function public_case( $case, $event_limit = Tra_Vel_Quote_Case_Store::EMBEDDED_EVENTS, $resume_available = false ) {
		$events = 1 === (int) $event_limit && isset( $case['_embedded_events'] )
			? (array) $case['_embedded_events']
			: ( $event_limit > 0 ? $this->store->get_recent_events( $case['id'], false, $event_limit ) : array() );
		return array(
			'contract_version' => Tra_Vel_Quote_Case_Policy::CONTRACT_VERSION,
			'case_id'          => (string) $case['case_uuid'],
			'reference'        => (string) $case['reference_code'],
			'status'           => (string) $case['status'],
			'status_label'     => Tra_Vel_Quote_Case_Policy::status_label( $case['status'] ),
			'ownership'        => (int) $case['owner_user_id'] > 0 ? 'account' : 'private_browser_owner',
			'version'          => (int) $case['case_version'],
			'source'           => array(
				'run_id'           => (string) $case['source_run_uuid'],
				'request_id'       => (string) $case['source_request_uuid'],
				'request_revision' => (int) $case['source_request_revision'],
				'request_digest'   => (string) $case['latest_request_digest'],
			),
			'summary'          => Tra_Vel_Quote_Case_Policy::public_summary( $case['snapshot'] ),
			'next_action'      => Tra_Vel_Quote_Case_Policy::next_action( $case['status'] ),
			'resume_available' => (bool) $resume_available,
			'events'           => $events,
			'created_at'       => gmdate( 'c', strtotime( $case['created_at'] . ' UTC' ) ),
			'updated_at'       => gmdate( 'c', strtotime( $case['updated_at'] . ' UTC' ) ),
			'retention_until'  => gmdate( 'c', strtotime( $case['retention_until'] . ' UTC' ) ),
		);
	}

	private function operator_case( $case, $event_limit = Tra_Vel_Quote_Case_Store::EMBEDDED_EVENTS ) {
		$public = $this->public_case( $case, 0 );
		$public['case_revision']     = (int) $case['current_revision'];
		$public['assigned_user_id']  = (int) $case['assigned_user_id'];
		$public['consent_version']   = (string) $case['consent_version'];
		$public['consented_at']      = gmdate( 'c', strtotime( $case['consented_at'] . ' UTC' ) );
		// Acquisition attribution and the explicitly consented lead contact are
		// operator-readable only. They must never enter public_case, traveler
		// responses, event payloads, logs, or webhook bodies.
		$public['acquisition']       = is_array( $case['acquisition'] ?? null ) && array() !== $case['acquisition'] ? $case['acquisition'] : null;
		$public['contact']           = is_array( $case['contact'] ?? null ) && array() !== $case['contact'] ? $case['contact'] : null;
		$public['allowed_transitions'] = Tra_Vel_Quote_Case_Policy::transitions()[ $case['status'] ] ?? array();
		$public['events']            = 1 === (int) $event_limit && isset( $case['_embedded_events'] )
			? (array) $case['_embedded_events']
			: $this->store->get_recent_events( $case['id'], true, $event_limit );
		return $public;
	}

	private function mutation_response( $result ) {
		return $this->private_response( $this->mutation_payload( $result ) );
	}

	private function mutation_payload( $result ) {
		$payload = array(
			'case'     => $this->traveler_case( $result['case'] ),
			'event'    => $result['event'],
			'replayed' => (bool) $result['replayed'],
		);
		if ( array_key_exists( 'reused', $result ) ) {
			$payload['reused'] = (bool) $result['reused'];
		}
		return $payload;
	}

	private function traveler_case( $case, $event_limit = Tra_Vel_Quote_Case_Store::EMBEDDED_EVENTS ) {
		$resume = $this->resume_availability( array( $case ) );
		return $this->public_case( $case, $event_limit, ! empty( $resume[ $case['case_uuid'] ] ) );
	}

	/**
	 * Resolve source-run resume in one bounded read. Any uncertainty is false.
	 *
	 * @param array $cases Already-authorized quote cases.
	 * @return array Availability keyed by quote-case UUID.
	 */
	private function resume_availability( $cases ) {
		$availability = array();
		$source_ids   = array();
		foreach ( is_array( $cases ) ? $cases : array() as $case ) {
			if ( ! is_array( $case ) || empty( $case['case_uuid'] ) ) {
				continue;
			}
			$availability[ (string) $case['case_uuid'] ] = false;
			if ( ! empty( $case['source_run_uuid'] ) ) {
				$source_ids[] = (string) $case['source_run_uuid'];
			}
		}
		if ( ! $source_ids || ! method_exists( $this->agent_store, 'get_run_ownership_by_uuids' ) ) {
			return $availability;
		}

		$read_error = '';
		$runs       = $this->agent_store->get_run_ownership_by_uuids( $source_ids, $read_error );
		if ( '' !== $read_error || ! is_array( $runs ) ) {
			return $availability;
		}
		$user_id = get_current_user_id();
		foreach ( $cases as $case ) {
			$case_uuid = (string) ( $case['case_uuid'] ?? '' );
			$run_uuid  = (string) ( $case['source_run_uuid'] ?? '' );
			$run       = $runs[ $run_uuid ] ?? null;
			if ( ! $case_uuid || ! is_array( $run ) || (int) ( $run['owner_user_id'] ?? -1 ) !== (int) ( $case['owner_user_id'] ?? -2 ) ) {
				continue;
			}
			$availability[ $case_uuid ] = (bool) $this->agent_store->can_access( $run, $this->run_cookie_token( $run_uuid ), $user_id );
		}
		return $availability;
	}

	private function principal( $create ) {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$token = $this->owner_cookie_token();
			return array( 'user_id' => $user_id, 'token' => $token, 'token_hash' => $token ? hash( 'sha256', $token ) : '', 'principal_hash' => hash( 'sha256', 'user:' . $user_id ), 'new_token' => false );
		}
		$token = $this->owner_cookie_token();
		$new   = false;
		if ( ! $token && $create ) {
			try {
				$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
			} catch ( Exception $error ) {
				$token = wp_generate_password( 48, false, false );
			}
			$new = true;
		}
		$hash = $token ? hash( 'sha256', $token ) : '';
		return array( 'user_id' => 0, 'token' => $token, 'token_hash' => $hash, 'principal_hash' => $hash, 'new_token' => $new );
	}

	private function owner_cookie_token() {
		if ( empty( $_COOKIE[ self::OWNER_COOKIE ] ) ) {
			return '';
		}
		$token = rawurldecode( (string) $_COOKIE[ self::OWNER_COOKIE ] );
		return preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ? $token : '';
	}

	private function guest_owner_hash() {
		$token = $this->owner_cookie_token();
		return $token ? hash( 'sha256', $token ) : '';
	}

	private function attach_owner_cookie( WP_REST_Response $response, $token ) {
		$cookie = self::OWNER_COOKIE . '=' . rawurlencode( (string) $token ) . '; Max-Age=' . ( Tra_Vel_Quote_Case_Store::ACTIVE_DAYS * DAY_IN_SECONDS ) . '; Path=/; Secure; HttpOnly; SameSite=Lax';
		$response->header( 'Set-Cookie', $cookie );
		return $response;
	}

	private function run_cookie_token( $run_uuid ) {
		if ( empty( $_COOKIE['__Host-tra_vel_agent_run'] ) ) {
			return '';
		}
		$parts = explode( '.', rawurldecode( (string) $_COOKIE['__Host-tra_vel_agent_run'] ), 2 );
		return 2 === count( $parts ) && hash_equals( (string) $run_uuid, (string) $parts[0] ) ? (string) $parts[1] : '';
	}

	private function handoff_context( $case ) {
		$summary      = Tra_Vel_Quote_Case_Policy::public_summary( $case['snapshot'] );
		$destinations = (array) $summary['destinations'];
		$travelers    = (array) $summary['travelers'];
		$budget       = (array) $summary['budget'];
		return array(
			'provider'     => 'tra-vel-concierge',
			'vertical'     => 'package',
			'offer_id'     => (string) $case['reference_code'],
			'case_id'      => (string) $case['case_uuid'],
			'reference'    => (string) $case['reference_code'],
			'destination'  => implode( ', ', $destinations ),
			'origin'       => (string) $summary['origin'],
			'depart_date'  => '',
			'return_date'  => '',
			'travelers'    => max( 1, absint( $travelers['adults'] ?? 0 ) + absint( $travelers['children'] ?? 0 ) ),
			'budget'       => isset( $budget['amount'] ) ? max( 0, (float) $budget['amount'] ) : 0,
			'currency'     => in_array( $budget['currency'] ?? '', array( 'ILS', 'USD', 'EUR' ), true ) ? $budget['currency'] : 'ILS',
			'product'      => 'Assisted 360 trip request',
			'return_path'  => '/ai-planner/',
		);
	}

	private function schema_response( $filename ) {
		$path = TRA_VEL_AGENT_PATH . '/schemas/' . $filename;
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_quote_case_schema_missing', 'Quote-case schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_quote_case_schema_invalid', 'Quote-case schema is invalid.', array( 'status' => 500 ) );
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private function create_args() {
		return array(
			'expected_request_id' => $this->uuid_arg(),
			'expected_revision'   => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'consent'             => array( 'type' => 'boolean', 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'consent_version'     => array( 'type' => 'string', 'required' => true, 'enum' => array( Tra_Vel_Quote_Case_Policy::CONSENT_VERSION ), 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
			'idempotency_key'     => $this->idempotency_arg(),
			'acquisition'         => array( 'type' => 'object', 'default' => array(), 'validate_callback' => 'rest_validate_request_arg' ),
			'contact'             => array( 'type' => 'object', 'default' => array(), 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	private function mutation_args() {
		return array(
			'expected_version' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'idempotency_key'  => $this->idempotency_arg(),
		);
	}

	private function uuid_arg() {
		return array( 'type' => 'string', 'required' => true, 'format' => 'uuid', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function idempotency_arg() {
		return array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 100, 'pattern' => '^[A-Za-z0-9._:-]+$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	/**
	 * Reserve bounded replay/recovery capacity before generating an owner token
	 * or writing quote events and idempotency records. The source-run allowance
	 * covers a small lost-response window without permitting indefinite cookie
	 * rotation; the visitor allowance limits fan-out across many runs.
	 *
	 * @param string $run_uuid Verified private AgentRun UUID.
	 * @return true|WP_Error
	 */
	private function consume_create_limit( $run_uuid ) {
		$run_window = DAY_IN_SECONDS;
		$run_bucket = (int) floor( time() / $run_window );
		$run_limit  = min( 20, max( 2, (int) apply_filters( 'tra_vel_quote_case_create_limit_per_run', 4 ) ) );
		$run_key    = 'quote-create-run:' . substr( hash_hmac( 'sha256', (string) $run_uuid, wp_salt( 'nonce' ) ), 0, 40 ) . ':' . $run_bucket;
		if ( ! $this->agent_store->consume_limit( $run_key, $run_limit, ( $run_bucket + 1 ) * $run_window + HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'tra_vel_quote_case_rate_limited', 'This private plan has reached its assisted-quote retry allowance. Please use the saved quote case or try again later.', array( 'status' => 429, 'retry_after' => max( 60, ( $run_bucket + 1 ) * $run_window - time() ) ) );
		}

		$visitor_window = 10 * MINUTE_IN_SECONDS;
		$visitor_bucket = (int) floor( time() / $visitor_window );
		$visitor_limit  = min( 100, max( 4, (int) apply_filters( 'tra_vel_quote_case_create_limit_per_visitor', 12 ) ) );
		$address        = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$identity       = get_current_user_id() > 0 ? 'user:' . get_current_user_id() : 'address:' . $address;
		$visitor_key    = 'quote-create-visitor:' . substr( hash_hmac( 'sha256', $identity, wp_salt( 'nonce' ) ), 0, 40 ) . ':' . $visitor_bucket;
		if ( ! $this->agent_store->consume_limit( $visitor_key, $visitor_limit, ( $visitor_bucket + 1 ) * $visitor_window + MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'tra_vel_quote_case_rate_limited', 'Too many assisted-quote attempts. Please wait before trying again.', array( 'status' => 429, 'retry_after' => max( 60, ( $visitor_bucket + 1 ) * $visitor_window - time() ) ) );
		}
		return true;
	}

	private function per_page_arg() {
		return array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' );
	}
}
