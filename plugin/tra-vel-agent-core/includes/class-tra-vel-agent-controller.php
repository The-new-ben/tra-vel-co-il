<?php
/**
 * REST control plane for private agent runs and protected approvals.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Controller extends WP_REST_Controller {
	/** @var Tra_Vel_Agent_Store */
	private $store;

	/** @var Tra_Vel_Agent_OpenAI_Provider */
	private $provider;

	public function __construct( $store = null, $provider = null ) {
		$this->namespace = 'tra-vel-agent/v1';
		$this->rest_base = 'runs';
		$this->store     = $store ? $store : new Tra_Vel_Agent_Store();
		$this->provider  = $provider ? $provider : new Tra_Vel_Agent_OpenAI_Provider();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/trip-request',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_trip_request_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_run' ),
				'permission_callback' => array( $this, 'can_create_run' ),
				'args'                => $this->create_run_args(),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<run_id>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_run' ),
				'permission_callback' => array( $this, 'can_access_run' ),
				'args'                => array( 'run_id' => $this->uuid_arg() ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<run_id>[0-9a-fA-F-]{36})/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => array( $this, 'can_access_run' ),
				'args'                => array(
					'run_id' => $this->uuid_arg(),
					'after'  => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<run_id>[0-9a-fA-F-]{36})/messages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revise_run' ),
				'permission_callback' => array( $this, 'can_access_run' ),
				'args'                => array(
					'run_id'               => $this->uuid_arg(),
					'message'              => array( 'type' => 'string', 'required' => true, 'minLength' => 2, 'maxLength' => 4000, 'sanitize_callback' => array( $this, 'sanitize_prompt' ), 'validate_callback' => 'rest_validate_request_arg' ),
					'locale'               => array( 'type' => 'string', 'default' => 'he-IL', 'enum' => array( 'he-IL', 'en-US', 'mixed' ), 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'input_kind'           => array( 'type' => 'string', 'default' => 'typed', 'enum' => array( 'typed', 'voice' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
					'transcript_confirmed' => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
					'client_request_id'    => array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<run_id>[0-9a-fA-F-]{36})/approvals/(?P<approval_id>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'decide_approval' ),
				'permission_callback' => array( $this, 'can_decide_approval' ),
				'args'                => array(
					'run_id'          => $this->uuid_arg(),
					'approval_id'     => $this->uuid_arg(),
					'decision'        => array( 'type' => 'string', 'required' => true, 'enum' => array( 'approve', 'reject' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
					'expected_version'=> array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					'scope_digest'    => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{64}$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
					'idempotency_key' => array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 100, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/credential',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'store_credential' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
					'args'                => array(
						'api_key'      => array( 'type' => 'string', 'required' => true, 'minLength' => 40, 'sanitize_callback' => array( $this, 'sanitize_secret' ), 'validate_callback' => 'rest_validate_request_arg' ),
						'confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_credential' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
				),
			)
		);
	}

	public function create_run( WP_REST_Request $request ) {
		$rate = $this->consume_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$prompt       = $this->sanitize_prompt( $request->get_param( 'prompt' ) );
		$mode         = sanitize_key( (string) $request->get_param( 'mode' ) );
		$locale       = sanitize_text_field( (string) $request->get_param( 'locale' ) );
		$input_kind   = sanitize_key( (string) $request->get_param( 'input_kind' ) );
		$confirmed    = 'voice' !== $input_kind || rest_sanitize_boolean( $request->get_param( 'transcript_confirmed' ) );
		$client_id    = sanitize_text_field( (string) $request->get_param( 'client_request_id' ) );
		$fingerprint  = hash( 'sha256', implode( '|', array( get_current_user_id(), $client_id, $mode, hash( 'sha256', $prompt ) ) ) );
		if ( $this->store->find_recent_fingerprint( $fingerprint ) ) {
			return new WP_Error( 'tra_vel_agent_duplicate_request', 'This agent request was already accepted.', array( 'status' => 409 ) );
		}
		$concurrency = min( 5, max( 1, (int) apply_filters( 'tra_vel_agent_provider_concurrency_limit', 2 ) ) );
		$lease       = $this->store->acquire_lease( 'provider', $concurrency, 120 );
		if ( ! $lease ) {
			return new WP_Error( 'tra_vel_agent_provider_busy', 'The private planner is handling other live requests. Please try again shortly.', array( 'status' => 429, 'retry_after' => 5 ) );
		}

		try {
			$budget = $this->consume_daily_budget();
			if ( is_wp_error( $budget ) ) {
				return $budget;
			}

			$created = $this->store->create_run(
				array(
					'owner_user_id'       => get_current_user_id(),
					'request_fingerprint' => $fingerprint,
					'mode'                => $mode,
					'locale'              => $locale,
					'input_kind'          => $input_kind,
				)
			);
			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$this->store->append_event( $created['id'], $this->event( 'run.created', 'intake', 'completed', 'system', 'הבקשה התקבלה ונפתחה סביבת תכנון פרטית.', array( 'mode' => $mode ) ) );
			$this->store->append_event( $created['id'], $this->event( 'request.interpretation.started', 'understanding', 'running', 'system', 'מפרשים את הבקשה ומאתרים פרטים שחסרים להחלטה.', array() ) );

			$interpreted = $this->provider->interpret( $prompt, $mode, $locale );
			if ( is_wp_error( $interpreted ) ) {
				$error_data = $interpreted->get_error_data();
				$this->store->update_run(
					$created['id'],
					array(
						'status'         => 'provider_error',
						'provider_state' => array( 'error_code' => $interpreted->get_error_code(), 'provider_code' => is_array( $error_data ) && isset( $error_data['provider_code'] ) ? $error_data['provider_code'] : null ),
					)
				);
				$this->store->append_event( $created['id'], $this->event( 'request.interpretation.failed', 'understanding', 'failed', 'tool', 'לא הצלחנו לפרש את הבקשה כעת. לא בוצע חיפוש ספקים ולא נוצרה הזמנה.', array( 'error_code' => $interpreted->get_error_code(), 'retryable' => true ) ) );
				$run      = $this->store->get_run_by_uuid( $created['run_uuid'] );
				$response = $this->private_response( $this->public_run( $run ), 201 );
				return $this->attach_run_cookie( $response, $created['run_uuid'], $created['run_token'] );
			}

			$trip_request = Tra_Vel_Agent_Policy::prepare_trip_request(
				$interpreted['trip_request'],
				array(
					'input_kind'           => $input_kind,
					'input_sha256'         => hash( 'sha256', $prompt ),
					'transcript_confirmed' => $confirmed,
				)
			);
			$needs_clarification = 'needs_clarification' === $trip_request['readiness']['status'];
			$status              = $needs_clarification ? 'needs_clarification' : 'request_ready';
			$this->store->update_run( $created['id'], array( 'status' => $status, 'trip_request' => $trip_request, 'provider_state' => $interpreted['provider'] ) );
			$this->store->append_event( $created['id'], $this->event( 'request.interpreted', 'understanding', 'completed', 'model', 'הבקשה הומרה לתוכנית חיפוש מובנית.', array( 'request_id' => $trip_request['request_id'], 'confidence' => $trip_request['confidence'] ) ) );

			if ( $needs_clarification ) {
				$this->store->append_event( $created['id'], $this->event( 'clarification.required', 'clarification', 'waiting', 'system', 'נדרשת תשובה קצרה לפני שאפשר להתחיל חיפוש אמין.', array( 'question_ids' => $trip_request['readiness']['blockers'] ) ) );
			} else {
				$this->store->append_event( $created['id'], $this->event( 'request.ready', 'clarification', 'completed', 'system', 'הבקשה מוכנה לחיפוש ספקים.', array( 'request_id' => $trip_request['request_id'] ) ) );
				$this->store->append_event( $created['id'], $this->event( 'supplier.search.not_started', 'supplier_search', 'waiting', 'system', 'חיפוש ספקים עדיין לא התחיל. יוצגו מחירים רק לאחר חיבור וביצוע חיפוש חי מתועד.', array( 'provider_connected' => false, 'provider_bookable' => false, 'data_mode' => 'not_connected' ) ) );
			}

			$run      = $this->store->get_run_by_uuid( $created['run_uuid'] );
			$response = $this->private_response( $this->public_run( $run ), 201 );
			return $this->attach_run_cookie( $response, $created['run_uuid'], $created['run_token'] );
		} finally {
			$this->store->release_lease( $lease );
		}
	}

	/**
	 * Revise one private run from a natural-language clarification.
	 *
	 * Raw clarification text is processed in memory and is never stored. The
	 * existing request remains authoritative until a complete strict replacement
	 * has passed provider and deterministic policy validation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revise_run( WP_REST_Request $request ) {
		$rate = $this->consume_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run || empty( $run['trip_request'] ) || ! is_array( $run['trip_request'] ) ) {
			return new WP_Error( 'tra_vel_agent_revision_unavailable', 'This private run has no request that can be revised.', array( 'status' => 409 ) );
		}
		if ( ! in_array( $run['status'], array( 'needs_clarification', 'request_ready' ), true ) ) {
			return new WP_Error( 'tra_vel_agent_revision_state', 'This private run cannot accept a revision in its current state.', array( 'status' => 409 ) );
		}

		$max_revisions = min( 20, max( 2, (int) apply_filters( 'tra_vel_agent_max_request_revisions', 8 ) ) );
		if ( (int) $run['trip_request']['revision'] >= $max_revisions ) {
			return new WP_Error( 'tra_vel_agent_revision_limit', 'This private run reached its revision limit. Start a new plan to continue.', array( 'status' => 409 ) );
		}

		$message     = $this->sanitize_prompt( $request->get_param( 'message' ) );
		$locale      = sanitize_text_field( (string) $request->get_param( 'locale' ) );
		$input_kind  = sanitize_key( (string) $request->get_param( 'input_kind' ) );
		$confirmed   = 'voice' !== $input_kind || rest_sanitize_boolean( $request->get_param( 'transcript_confirmed' ) );
		$client_id   = sanitize_text_field( (string) $request->get_param( 'client_request_id' ) );
		if ( strlen( $message ) < 2 ) {
			return new WP_Error( 'tra_vel_agent_revision_empty', 'Write a short clarification before updating the plan.', array( 'status' => 400 ) );
		}
		if ( 'voice' === $input_kind && ! $confirmed ) {
			return new WP_Error( 'tra_vel_agent_revision_transcript', 'Confirm the voice transcript before it changes the private plan.', array( 'status' => 400 ) );
		}

		$run_lease = $this->store->acquire_lease( 'run' . substr( hash( 'sha256', $run['run_uuid'] ), 0, 24 ), 1, 120 );
		if ( ! $run_lease ) {
			return new WP_Error( 'tra_vel_agent_revision_busy', 'This private plan is already being updated. Please wait for that update to finish.', array( 'status' => 409, 'retry_after' => 5 ) );
		}

		$concurrency   = min( 5, max( 1, (int) apply_filters( 'tra_vel_agent_provider_concurrency_limit', 2 ) ) );
		$provider_lease = $this->store->acquire_lease( 'provider', $concurrency, 120 );
		if ( ! $provider_lease ) {
			$this->store->release_lease( $run_lease );
			return new WP_Error( 'tra_vel_agent_provider_busy', 'The private planner is handling other live requests. Please try again shortly.', array( 'status' => 429, 'retry_after' => 5 ) );
		}

		try {
			$idempotency_key = 'revision:' . (int) $run['id'] . ':' . substr( hash( 'sha256', $client_id ), 0, 40 );
			$expires_at      = max( time() + MINUTE_IN_SECONDS, strtotime( $run['expires_at'] . ' UTC' ) );
			if ( ! $this->store->consume_limit( $idempotency_key, 1, $expires_at ) ) {
				return new WP_Error( 'tra_vel_agent_duplicate_revision', 'This plan update was already accepted.', array( 'status' => 409 ) );
			}

			$budget = $this->consume_daily_budget();
			if ( is_wp_error( $budget ) ) {
				return $budget;
			}

			$next_revision = (int) $run['trip_request']['revision'] + 1;
			$message_hash  = hash( 'sha256', $message );
			$this->store->append_event(
				$run['id'],
				$this->event(
					'clarification.response.received',
					'clarification',
					'completed',
					'human',
					'התשובה התקבלה באופן פרטי. משלבים אותה בתוכנית בלי לשמור את הטקסט החופשי.',
					array( 'revision' => $next_revision, 'input_kind' => $input_kind, 'input_sha256' => $message_hash )
				)
			);
			$this->store->append_event( $run['id'], $this->event( 'request.revision.started', 'understanding', 'running', 'system', 'בודקים אילו פרטים השתנו ומה עדיין חסר לפני חיפוש.', array( 'revision' => $next_revision ) ) );

			$interpreted = $this->provider->revise( $run['trip_request'], $message, $run['mode'], $locale );
			if ( is_wp_error( $interpreted ) ) {
				$error_data = $interpreted->get_error_data();
				$this->store->append_event(
					$run['id'],
					$this->event(
						'request.revision.failed',
						'understanding',
						'failed',
						'tool',
						'לא הצלחנו לשלב את העדכון כעת. התוכנית הקודמת נשארה ללא שינוי.',
						array( 'revision' => $next_revision, 'error_code' => $interpreted->get_error_code(), 'provider_code' => is_array( $error_data ) && isset( $error_data['provider_code'] ) ? $error_data['provider_code'] : null )
					)
				);
				return $interpreted;
			}

			$previous_hash = isset( $run['trip_request']['source']['input_sha256'] ) ? (string) $run['trip_request']['source']['input_sha256'] : str_repeat( '0', 64 );
			$trip_request  = Tra_Vel_Agent_Policy::prepare_trip_request(
				$interpreted['trip_request'],
				array(
					'input_kind'           => $input_kind,
					'input_sha256'         => hash( 'sha256', $previous_hash . '|' . $message_hash ),
					'transcript_confirmed' => $confirmed,
				),
				$run['trip_request']
			);
			$needs_clarification = 'needs_clarification' === $trip_request['readiness']['status'];
			$status              = $needs_clarification ? 'needs_clarification' : 'request_ready';
			$this->store->update_run( $run['id'], array( 'status' => $status, 'trip_request' => $trip_request, 'provider_state' => $interpreted['provider'] ) );
			$this->store->append_event( $run['id'], $this->event( 'request.revised', 'understanding', 'completed', 'model', 'התוכנית עודכנה לפי התשובה ונבדקה מחדש.', array( 'request_id' => $trip_request['request_id'], 'revision' => $trip_request['revision'], 'confidence' => $trip_request['confidence'] ) ) );

			if ( $needs_clarification ) {
				$this->store->append_event( $run['id'], $this->event( 'clarification.required', 'clarification', 'waiting', 'system', 'עדיין חסר פרט שמשפיע על החיפוש. אפשר לענות במשפט חופשי נוסף.', array( 'revision' => $trip_request['revision'], 'question_ids' => $trip_request['readiness']['blockers'] ) ) );
			} else {
				$this->store->append_event( $run['id'], $this->event( 'request.ready', 'clarification', 'completed', 'system', 'הבקשה המעודכנת מוכנה לחיפוש ספקים.', array( 'request_id' => $trip_request['request_id'], 'revision' => $trip_request['revision'] ) ) );
				$this->store->append_event( $run['id'], $this->event( 'supplier.search.not_started', 'supplier_search', 'waiting', 'system', 'חיפוש ספקים עדיין לא התחיל. יוצגו מחירים רק לאחר חיבור וביצוע חיפוש חי מתועד.', array( 'provider_connected' => false, 'provider_bookable' => false, 'data_mode' => 'not_connected' ) ) );
			}

			return $this->private_response( $this->public_run( $this->store->get_run_by_uuid( $run['run_uuid'] ) ) );
		} finally {
			$this->store->release_lease( $provider_lease );
			$this->store->release_lease( $run_lease );
		}
	}

	/**
	 * Public intake is restricted to HTTPS and rate-limited in the callback.
	 *
	 * @return true|WP_Error
	 */
	public function can_create_run() {
		if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			return new WP_Error( 'tra_vel_agent_https_required', 'Agent requests require HTTPS.', array( 'status' => 403 ) );
		}
		return true;
	}

	public function get_run( WP_REST_Request $request ) {
		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		return $this->private_response( $this->public_run( $run ) );
	}

	public function get_events( WP_REST_Request $request ) {
		$run    = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		$events = $this->store->get_events( $run['id'], (int) $request->get_param( 'after' ) );
		return $this->private_response( array( 'run_id' => $run['run_uuid'], 'events' => $events, 'last_sequence' => $events ? end( $events )['sequence'] : (int) $request->get_param( 'after' ) ) );
	}

	public function can_access_run( WP_REST_Request $request ) {
		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run ) {
			return new WP_Error( 'tra_vel_agent_run_missing', 'Agent run not found.', array( 'status' => 404 ) );
		}
		$token = $this->run_cookie_token( $run['run_uuid'] );
		return $this->store->can_access( $run, $token, get_current_user_id() ) ? true : new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run does not belong to the current visitor.', array( 'status' => 403 ) );
	}

	public function can_decide_approval( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id < 1 || ! current_user_can( 'read' ) ) {
			return new WP_Error( 'tra_vel_agent_login_required', 'Sign in before approving a consequential action.', array( 'status' => 401 ) );
		}
		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run ) {
			return new WP_Error( 'tra_vel_agent_run_missing', 'Agent run not found.', array( 'status' => 404 ) );
		}
		if ( (int) $run['owner_user_id'] < 1 || (int) $run['owner_user_id'] !== $user_id ) {
			return new WP_Error( 'tra_vel_agent_approval_owner_required', 'Only the signed-in owner of this run can approve a consequential action.', array( 'status' => 403 ) );
		}
		return true;
	}

	public function decide_approval( WP_REST_Request $request ) {
		$run      = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		$approval = $this->store->get_approval( $run['id'], $request->get_param( 'approval_id' ) );
		if ( ! $approval ) {
			return new WP_Error( 'tra_vel_agent_approval_missing', 'Approval request not found.', array( 'status' => 404 ) );
		}
		if ( (int) $request->get_param( 'expected_version' ) !== $approval['approval_version'] || ! hash_equals( $approval['scope_digest'], (string) $request->get_param( 'scope_digest' ) ) ) {
			return new WP_Error( 'tra_vel_agent_approval_scope_changed', 'The protected action changed and requires a new approval.', array( 'status' => 409 ) );
		}
		$decision = $this->store->decide_approval( $approval, $request->get_param( 'decision' ), $request->get_param( 'idempotency_key' ), get_current_user_id() );
		if ( is_wp_error( $decision ) ) {
			return $decision;
		}
		$this->store->append_event( $run['id'], $this->event( 'approval.decided', 'approval', 'completed', 'human', 'החלטת האישור נשמרה. פעולה חיצונית תתבצע רק דרך כלי מוגן ומתועד.', array( 'approval_id' => $approval['approval_uuid'], 'decision' => $request->get_param( 'decision' ) ) ) );
		return $this->private_response( array( 'approval' => $this->public_approval( $decision ), 'side_effect_executed' => false ) );
	}

	public function get_health() {
		$daily_limit = max( 1, (int) apply_filters( 'tra_vel_agent_daily_request_limit', 20 ) );
		$visitor_limit = max( 1, (int) apply_filters( 'tra_vel_agent_visitor_request_limit', 5 ) );
		$concurrency_limit = min( 5, max( 1, (int) apply_filters( 'tra_vel_agent_provider_concurrency_limit', 2 ) ) );
		return rest_ensure_response(
			array(
				'ok'               => true,
				'plugin_version'   => TRA_VEL_AGENT_VERSION,
				'contract_version' => '1.0.0',
				'provider'         => $this->provider->health(),
				'capabilities'     => array(
					'request_interpretation' => $this->provider->health()['configured'],
					'request_revision'       => $this->provider->health()['configured'],
					'supplier_search'        => false,
					'proposal_generation'    => false,
					'booking_execution'      => false,
				),
				'limits'           => array(
					'per_visitor_requests'     => $visitor_limit,
					'per_visitor_window_seconds'=> 600,
					'global_requests_per_utc_day'=> $daily_limit,
					'provider_concurrency'        => $concurrency_limit,
				),
			)
		);
	}

	public function get_trip_request_schema() {
		$path = TRA_VEL_AGENT_PATH . '/schemas/trip-request.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_agent_schema_missing', 'TripRequest schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_agent_schema_invalid', 'TripRequest schema is invalid.', array( 'status' => 500 ) );
	}

	public function store_credential( WP_REST_Request $request ) {
		if ( 'STORE TRA-VEL OPENAI KEY' !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_key_confirmation', 'Credential confirmation did not match.', array( 'status' => 400 ) );
		}
		$stored = Tra_Vel_Agent_Credential_Vault::store_api_key( $request->get_param( 'api_key' ) );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return $this->private_response( array( 'ok' => true, 'credential' => Tra_Vel_Agent_Credential_Vault::status() ) );
	}

	public function clear_credential() {
		Tra_Vel_Agent_Credential_Vault::clear_stored_key();
		return $this->private_response( array( 'ok' => true, 'credential' => Tra_Vel_Agent_Credential_Vault::status() ) );
	}

	public function can_manage_agent() {
		return current_user_can( 'manage_options' );
	}

	public function sanitize_secret( $value ) {
		return trim( (string) $value );
	}

	private function public_run( $run ) {
		$events = $this->store->get_events( $run['id'] );
		$data   = array(
			'contract_version' => '1.0.0',
			'run_id'           => $run['run_uuid'],
			'status'           => $run['status'],
			'mode'             => $run['mode'],
			'locale'           => $run['locale'],
			'trip_request'     => $run['trip_request'],
			'proposals'        => $run['proposals'],
			'provider'         => $run['provider_state'],
			'events'           => $events,
			'approvals'        => array(),
			'created_at'       => gmdate( 'c', strtotime( $run['created_at'] . ' UTC' ) ),
			'updated_at'       => gmdate( 'c', strtotime( $run['updated_at'] . ' UTC' ) ),
			'expires_at'       => gmdate( 'c', strtotime( $run['expires_at'] . ' UTC' ) ),
		);
		return $data;
	}

	private function attach_run_cookie( WP_REST_Response $response, $run_uuid, $run_token ) {
		if ( get_current_user_id() > 0 ) {
			return $response;
		}
		$value  = rawurlencode( (string) $run_uuid . '.' . (string) $run_token );
		$cookie = '__Host-tra_vel_agent_run=' . $value . '; Max-Age=' . DAY_IN_SECONDS . '; Path=/; Secure; HttpOnly; SameSite=Lax';
		$response->header( 'Set-Cookie', $cookie );
		return $response;
	}

	private function run_cookie_token( $run_uuid ) {
		if ( empty( $_COOKIE['__Host-tra_vel_agent_run'] ) ) {
			return '';
		}
		$parts = explode( '.', rawurldecode( (string) $_COOKIE['__Host-tra_vel_agent_run'] ), 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( (string) $run_uuid, (string) $parts[0] ) ) {
			return '';
		}
		return (string) $parts[1];
	}

	private function public_approval( $approval ) {
		return array(
			'approval_id' => $approval['approval_uuid'],
			'version'     => $approval['approval_version'],
			'status'      => $approval['status'],
			'action_type' => $approval['action_type'],
			'scope_digest'=> $approval['scope_digest'],
			'summary'     => $approval['summary'],
			'action'      => $approval['action_snapshot'],
			'expires_at'  => gmdate( 'c', strtotime( $approval['expires_at'] . ' UTC' ) ),
		);
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private function event( $type, $phase, $status, $source, $message, $data ) {
		return compact( 'type', 'phase', 'status', 'source', 'message', 'data' );
	}

	private function create_run_args() {
		return array(
			'prompt'               => array( 'type' => 'string', 'required' => true, 'minLength' => 4, 'maxLength' => 4000, 'sanitize_callback' => array( $this, 'sanitize_prompt' ), 'validate_callback' => 'rest_validate_request_arg' ),
			'mode'                 => array( 'type' => 'string', 'default' => 'agent', 'enum' => array( 'agent', 'surprise' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'locale'               => array( 'type' => 'string', 'default' => 'he-IL', 'enum' => array( 'he-IL', 'en-US', 'mixed' ), 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
			'input_kind'           => array( 'type' => 'string', 'default' => 'typed', 'enum' => array( 'typed', 'voice' ), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' ),
			'transcript_confirmed' => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'client_request_id'    => array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	public function sanitize_prompt( $value ) {
		$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 4000 ) : substr( $value, 0, 4000 );
	}

	private function uuid_arg() {
		return array( 'type' => 'string', 'required' => true, 'format' => 'uuid', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function consume_rate_limit() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$window_seconds = 10 * MINUTE_IN_SECONDS;
		$window_id      = (int) floor( time() / $window_seconds );
		$visitor_limit  = max( 1, (int) apply_filters( 'tra_vel_agent_visitor_request_limit', 5 ) );
		$key            = 'visitor:' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 40 ) . ':' . $window_id;
		$allowed        = $this->store->consume_limit( $key, $visitor_limit, ( $window_id + 1 ) * $window_seconds + MINUTE_IN_SECONDS );
		if ( ! $allowed ) {
			return new WP_Error( 'tra_vel_agent_rate_limited', 'Too many agent requests. Please wait before trying again.', array( 'status' => 429, 'retry_after' => 600 ) );
		}
		return true;
	}

	private function consume_daily_budget() {
		$daily_limit = max( 1, (int) apply_filters( 'tra_vel_agent_daily_request_limit', 20 ) );
		$daily_key = 'global:' . gmdate( 'Ymd' );
		$allowed   = $this->store->consume_limit( $daily_key, $daily_limit, time() + DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS );
		if ( ! $allowed ) {
			return new WP_Error( 'tra_vel_agent_daily_capacity', 'The private planner has reached its daily live-request capacity.', array( 'status' => 429, 'retry_after' => DAY_IN_SECONDS ) );
		}
		return true;
	}
}
