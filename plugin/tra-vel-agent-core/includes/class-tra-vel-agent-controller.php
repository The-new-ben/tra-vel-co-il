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
			'/schema/agent-run-summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_run_summary_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_runs' ),
					'permission_callback' => array( $this, 'can_list_runs' ),
					'args'                => array(
						'limit' => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_run' ),
					'permission_callback' => array( $this, 'can_create_run' ),
					'args'                => $this->create_run_args(),
				),
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
		register_rest_route(
			$this->namespace,
			'/settings/notification-webhook',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'store_notification_webhook' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
					'args'                => array(
						'webhook_url'  => array( 'type' => 'string', 'required' => true, 'format' => 'uri', 'maxLength' => 500, 'sanitize_callback' => array( $this, 'sanitize_secret' ), 'validate_callback' => 'rest_validate_request_arg' ),
						'confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_notification_webhook' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/notification-recipients',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'store_notification_recipients' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
					'args'                => array(
						'recipients'   => array( 'type' => 'array', 'required' => true, 'minItems' => 1, 'maxItems' => 10, 'items' => array( 'type' => 'string', 'format' => 'email', 'maxLength' => 254 ), 'validate_callback' => 'rest_validate_request_arg' ),
						'confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_notification_recipients' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/model',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'store_model' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
					'args'                => array(
						'model'        => array( 'type' => 'string', 'required' => true, 'enum' => Tra_Vel_Agent_OpenAI_Provider::ALLOWED_MODELS, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
						'confirmation' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_model' ),
					'permission_callback' => array( $this, 'can_manage_agent' ),
				),
			)
		);
	}

	public function create_run( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
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
		$planning_context = $this->normalize_planning_context( $request->get_param( 'planning_context' ) );
		$fingerprint  = hash( 'sha256', implode( '|', array( get_current_user_id(), $client_id, $mode, hash( 'sha256', $prompt ), hash( 'sha256', wp_json_encode( $planning_context ) ) ) ) );
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

			$this->store->append_event( $created['id'], $this->event( 'run.created', 'intake', 'completed', 'system', 'הבקשה התקבלה ונפתחה סביבת תכנון פרטית.', array( 'mode' => $mode, 'planning_context_kind' => $planning_context['kind'], 'selection_id' => $planning_context['selection_id'] ) ) );
			$this->store->append_event( $created['id'], $this->event( 'request.interpretation.started', 'understanding', 'running', 'system', 'מסדרים את הפרטים ובודקים מה חסר.', array() ) );

			$interpreted = $this->provider->interpret( $prompt, $mode, $locale );
			if ( is_wp_error( $interpreted ) ) {
				$error_data    = $interpreted->get_error_data();
				$provider_code = is_array( $error_data ) && isset( $error_data['provider_code'] ) ? $error_data['provider_code'] : null;
				$this->store->update_run(
					$created['id'],
					array(
						'status'         => 'provider_error',
						'provider_state' => array( 'error_code' => $interpreted->get_error_code(), 'provider_code' => $provider_code ),
					)
				);
				$this->store->append_event( $created['id'], $this->event( 'request.interpretation.failed', 'understanding', 'failed', 'tool', 'לא הצלחנו לפרש את הבקשה כעת. לא בוצע חיפוש ספקים ולא נוצרה הזמנה.', array( 'error_code' => $interpreted->get_error_code(), 'retryable' => true ) ) );
				/**
				 * Alert operational listeners only after the failed run state and
				 * its audit event have committed. Listeners must remain idempotent.
				 *
				 * @param string      $error_code    Internal WP_Error code.
				 * @param string|null $provider_code Upstream provider failure code.
				 */
				do_action( 'tra_vel_agent_provider_error', $interpreted->get_error_code(), $provider_code );
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
					'planning_context'     => $planning_context,
				)
			);
			$needs_clarification = 'needs_clarification' === $trip_request['readiness']['status'];
			$status              = $needs_clarification ? 'needs_clarification' : 'request_ready';
			$this->store->update_run( $created['id'], array( 'status' => $status, 'trip_request' => $trip_request, 'provider_state' => $interpreted['provider'] ) );
			$this->store->append_event( $created['id'], $this->event( 'request.interpreted', 'understanding', 'completed', 'model', 'פרטי החופשה סודרו לתוכנית שאפשר לבדוק ולעדכן.', array( 'request_id' => $trip_request['request_id'], 'confidence' => $trip_request['confidence'] ) ) );

			if ( $needs_clarification ) {
				$this->store->append_event( $created['id'], $this->event( 'clarification.required', 'clarification', 'waiting', 'system', 'חסר פרט אחד שמשפיע על האפשרויות. אפשר לענות במשפט חופשי.', array( 'question_ids' => $trip_request['readiness']['blockers'] ) ) );
			} else {
				$this->store->append_event( $created['id'], $this->event( 'request.ready', 'clarification', 'completed', 'system', 'הבקשה מוכנה לבדיקה. עדיין לא בוצע חיפוש מחיר.', array( 'request_id' => $trip_request['request_id'] ) ) );
				$this->store->append_event( $created['id'], $this->event( 'supplier.search.not_started', 'supplier_search', 'waiting', 'system', 'בדיקת ספקים עדיין לא התחילה. מחירים יוצגו רק לאחר חיבור מקור וקבלת תוצאה מתועדת.', array( 'provider_connected' => false, 'provider_bookable' => false, 'data_mode' => 'not_connected' ) ) );
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
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$rate = $this->consume_rate_limit();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run || empty( $run['trip_request'] ) || ! is_array( $run['trip_request'] ) ) {
			return new WP_Error( 'tra_vel_agent_revision_unavailable', 'This private run has no request that can be revised.', array( 'status' => 409 ) );
		}
		if ( ! $this->store->can_access( $run, $this->run_cookie_token( $run['run_uuid'] ), get_current_user_id() ) ) {
			return new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run changed owner before the update began.', array( 'status' => 403 ) );
		}
		if ( ! in_array( $run['status'], array( 'needs_clarification', 'request_ready' ), true ) ) {
			return new WP_Error( 'tra_vel_agent_revision_state', 'This private run cannot accept a revision in its current state.', array( 'status' => 409 ) );
		}
		$owner_guard = array(
			'owner_user_id'    => (int) $run['owner_user_id'],
			'owner_token_hash' => (string) ( $run['owner_token_hash'] ?? '' ),
			'updated_at'       => (string) $run['updated_at'],
		);

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
			$this->store->append_event( $run['id'], $this->event( 'request.revision.started', 'understanding', 'running', 'system', 'מעדכנים את הפרטים ובודקים מה עדיין חסר.', array( 'revision' => $next_revision ) ) );

			$interpreted = $this->provider->revise( $run['trip_request'], $message, $run['mode'], $locale );
			if ( is_wp_error( $interpreted ) ) {
				$error_data = $interpreted->get_error_data();
				$guarded = $this->store->update_run_if_owner(
					$run['id'],
					array(
						'provider_state' => array(
							'error_code'    => $interpreted->get_error_code(),
							'provider_code' => is_array( $error_data ) && isset( $error_data['provider_code'] ) ? $error_data['provider_code'] : null,
						),
					),
					$owner_guard
				);
				if ( ! $guarded ) {
					return new WP_Error( 'tra_vel_agent_revision_owner_changed', 'The private plan owner or version changed while this update was being prepared. Nothing was replaced.', array( 'status' => 409 ) );
				}
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
				/**
				 * Alert operational listeners only after the recorded provider
				 * failure and its audit event have committed. Listeners must
				 * remain idempotent.
				 *
				 * @param string      $error_code    Internal WP_Error code.
				 * @param string|null $provider_code Upstream provider failure code.
				 */
				do_action( 'tra_vel_agent_provider_error', $interpreted->get_error_code(), is_array( $error_data ) && isset( $error_data['provider_code'] ) ? $error_data['provider_code'] : null );
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
			$persisted = $this->store->update_run_if_owner( $run['id'], array( 'status' => $status, 'trip_request' => $trip_request, 'provider_state' => $interpreted['provider'] ), $owner_guard );
			if ( ! $persisted ) {
				return new WP_Error( 'tra_vel_agent_revision_owner_changed', 'The private plan owner or version changed while this update was being prepared. Nothing was replaced.', array( 'status' => 409 ) );
			}
			$this->store->append_event( $run['id'], $this->event( 'request.revised', 'understanding', 'completed', 'model', 'התוכנית עודכנה לפי התשובה ונבדקה מחדש.', array( 'request_id' => $trip_request['request_id'], 'revision' => $trip_request['revision'], 'confidence' => $trip_request['confidence'] ) ) );

			if ( $needs_clarification ) {
				$this->store->append_event( $run['id'], $this->event( 'clarification.required', 'clarification', 'waiting', 'system', 'עדיין חסר פרט שמשפיע על החיפוש. אפשר לענות במשפט חופשי נוסף.', array( 'revision' => $trip_request['revision'], 'question_ids' => $trip_request['readiness']['blockers'] ) ) );
			} else {
				$this->store->append_event( $run['id'], $this->event( 'request.ready', 'clarification', 'completed', 'system', 'הבקשה המעודכנת מוכנה לבדיקה. עדיין לא בוצע חיפוש מחיר.', array( 'request_id' => $trip_request['request_id'], 'revision' => $trip_request['revision'] ) ) );
				$this->store->append_event( $run['id'], $this->event( 'supplier.search.not_started', 'supplier_search', 'waiting', 'system', 'בדיקת ספקים עדיין לא התחילה. מחירים יוצגו רק לאחר חיבור מקור וקבלת תוצאה מתועדת.', array( 'provider_connected' => false, 'provider_bookable' => false, 'data_mode' => 'not_connected' ) ) );
			}

			$updated_run = $this->store->get_run_by_uuid( $run['run_uuid'] );
			/**
			 * Notify durable workflows only after the replacement TripRequest and
			 * its audit events have committed. Listeners must remain idempotent.
			 */
			do_action( 'tra_vel_agent_run_revised', $updated_run, $run );
			return $this->private_response( $this->public_run( $updated_run ) );
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
	public function can_use_store() {
		if ( ! ( $this->store instanceof Tra_Vel_Agent_Store ) || Tra_Vel_Agent_Store::is_ready() ) {
			return true;
		}
		return new WP_Error( 'tra_vel_agent_store_unavailable', 'Private planner storage is temporarily unavailable.', array( 'status' => 503 ) );
	}

	public function can_create_run() {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
			return new WP_Error( 'tra_vel_agent_https_required', 'Agent requests require HTTPS.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Account plan history is never available to guests or by supplied owner ID.
	 *
	 * @return true|WP_Error
	 */
	public function can_list_runs() {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( get_current_user_id() < 1 || ! current_user_can( 'read' ) ) {
			return new WP_Error( 'tra_vel_agent_login_required', 'Sign in to view saved travel plans.', array( 'status' => 401 ) );
		}
		return true;
	}

	public function list_runs( WP_REST_Request $request ) {
		$allowed = $this->can_list_runs();
		if ( true !== $allowed ) {
			return $allowed;
		}
		$user_id    = get_current_user_id();
		$limit      = min( 20, max( 1, absint( $request->get_param( 'limit' ) ?: 12 ) ) );
		$read_error = '';
		$runs       = $this->store->list_owned_runs( $user_id, $limit, $read_error );
		if ( '' !== $read_error ) {
			return new WP_Error( 'tra_vel_agent_runs_read_failed', 'Saved travel plans are temporarily unavailable.', array( 'status' => 503 ) );
		}
		return $this->private_response(
			array(
				'runs' => array_map( array( $this, 'public_run_summary' ), is_array( $runs ) ? $runs : array() ),
			)
		);
	}

	public function get_run( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run || ! $this->store->can_access( $run, $this->run_cookie_token( $run['run_uuid'] ), get_current_user_id() ) ) {
			return new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run is no longer available to the current visitor.', array( 'status' => 403 ) );
		}
		return $this->private_response( $this->public_run( $run ) );
	}

	public function get_events( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$run    = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run || ! $this->store->can_access( $run, $this->run_cookie_token( $run['run_uuid'] ), get_current_user_id() ) ) {
			return new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run is no longer available to the current visitor.', array( 'status' => 403 ) );
		}
		$events = $this->store->get_events( $run['id'], (int) $request->get_param( 'after' ) );
		return $this->private_response( array( 'run_id' => $run['run_uuid'], 'events' => $events, 'last_sequence' => $events ? end( $events )['sequence'] : (int) $request->get_param( 'after' ) ) );
	}

	public function can_access_run( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
		$run = $this->store->get_run_by_uuid( $request->get_param( 'run_id' ) );
		if ( ! $run ) {
			return new WP_Error( 'tra_vel_agent_run_missing', 'Agent run not found.', array( 'status' => 404 ) );
		}
		$token = $this->run_cookie_token( $run['run_uuid'] );
		return $this->store->can_access( $run, $token, get_current_user_id() ) ? true : new WP_Error( 'tra_vel_agent_run_forbidden', 'This private agent run does not belong to the current visitor.', array( 'status' => 403 ) );
	}

	public function can_decide_approval( WP_REST_Request $request ) {
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
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
		$ready = $this->can_use_store();
		if ( true !== $ready ) {
			return $ready;
		}
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
		$this->store->append_event( $run['id'], $this->event( 'approval.decided', 'approval', 'completed', 'human', 'האישור נשמר. עדיין לא נשלחה הוראה לספק ולא בוצעו חיוב או הזמנה.', array( 'approval_id' => $approval['approval_uuid'], 'decision' => $request->get_param( 'decision' ) ) ) );
		return $this->private_response( array( 'approval' => $this->public_approval( $decision ), 'side_effect_executed' => false ) );
	}

	public function get_health() {
		$daily_limit      = max( 1, (int) apply_filters( 'tra_vel_agent_daily_request_limit', 200 ) );
		$visitor_limit    = max( 1, (int) apply_filters( 'tra_vel_agent_visitor_request_limit', 8 ) );
		$concurrency_limit = min( 5, max( 1, (int) apply_filters( 'tra_vel_agent_provider_concurrency_limit', 2 ) ) );
		$proposal_store_loaded = class_exists( 'Tra_Vel_Assisted_Proposal_Store' );
		$proposal_store_health = $proposal_store_loaded
			? Tra_Vel_Assisted_Proposal_Store::schema_health()
			: array(
				'schema_version'             => null,
				'installed_schema_version'   => null,
				'idempotency_days'            => 7,
				'max_proposals_per_case'      => 12,
				'max_revisions_per_proposal' => 20,
				'max_snapshot_bytes'          => 524288,
				'expected_tables'            => 5,
				'ready_tables'               => 0,
				'transactional_tables'       => 0,
				'required_indexes'           => 9,
				'ready_indexes'              => 0,
				'required_indexes_ready'     => false,
				'inspection_errors'          => array( 'class_unavailable' ),
				'tables_ready'               => false,
			);
		$proposal_store_ready = $proposal_store_loaded && Tra_Vel_Assisted_Proposal_Store::is_ready();
		$proposal_contract_ready = $proposal_store_ready
			&& class_exists( 'Tra_Vel_Assisted_Proposal_Policy' )
			&& class_exists( 'Tra_Vel_Assisted_Proposal_Controller' );
		$vip_intake_store_health = class_exists( 'Tra_Vel_VIP_Intake_Store' )
			? Tra_Vel_VIP_Intake_Store::schema_health()
			: array(
				'schema_version'           => null,
				'installed_schema_version' => null,
				'expected_tables'          => 4,
				'ready_tables'             => 0,
				'tables_ready'             => false,
			);
		$vip_intake_ready = class_exists( 'Tra_Vel_VIP_Intake_Controller' )
			&& ! empty( $vip_intake_store_health['tables_ready'] );
		$vip_capability_store_health = class_exists( 'Tra_Vel_VIP_Capability_Session_Store' )
			? Tra_Vel_VIP_Capability_Session_Store::schema_health()
			: array(
				'schema_version'           => null,
				'installed_schema_version' => null,
				'expected_tables'          => 4,
				'ready_tables'             => 0,
				'tables_ready'             => false,
			);
		$vip_capability_ready = class_exists( 'Tra_Vel_VIP_Capability_Session_Controller' )
			&& class_exists( 'Tra_Vel_VIP_Capability_Session_Store' )
			&& Tra_Vel_VIP_Capability_Session_Store::is_ready();
		$customer_cockpit_store_health = class_exists( 'Tra_Vel_Customer_Trip_Cockpit_Store' )
			? Tra_Vel_Customer_Trip_Cockpit_Store::schema_health()
			: array(
				'schema_version'           => null,
				'installed_schema_version' => null,
				'expected_tables'          => 3,
				'ready_tables'             => 0,
				'tables_ready'             => false,
			);
		$customer_cockpit_ready = class_exists( 'Tra_Vel_Customer_Trip_Cockpit_Controller' )
			&& class_exists( 'Tra_Vel_Customer_Trip_Cockpit_Store' )
			&& Tra_Vel_Customer_Trip_Cockpit_Store::is_ready();
		return rest_ensure_response(
			array(
				'ok'               => true,
				'plugin_version'   => TRA_VEL_AGENT_VERSION,
				'contract_version' => '1.0.0',
				'provider'         => $this->provider->health(),
				'capabilities'     => array(
					'request_interpretation' => $this->provider->health()['configured'],
					'request_revision'       => $this->provider->health()['configured'],
					'account_plan_history'   => true,
					'assisted_quote_cases'   => class_exists( 'Tra_Vel_Quote_Case_Store' ),
					'operator_queue'         => class_exists( 'Tra_Vel_Quote_Case_Controller' ),
					'commercial_intents'     => class_exists( 'Tra_Vel_Commercial_Intent_Store' ),
					'durable_commercial_handoffs' => class_exists( 'Tra_Vel_Commercial_Intent_Controller' ),
					'lead_contact_capture'   => class_exists( 'Tra_Vel_Commercial_Intent_Policy' )
						&& defined( 'Tra_Vel_Commercial_Intent_Policy::CONTACT_CONSENT_VERSION' )
						&& class_exists( 'Tra_Vel_Quote_Case_Policy' )
						&& defined( 'Tra_Vel_Quote_Case_Policy::CONTACT_CONSENT_VERSION' ),
					'sourced_assisted_proposals' => $proposal_contract_ready,
					'audited_proposal_actions' => $proposal_contract_ready && class_exists( 'Tra_Vel_Quote_Case_Capabilities' ) && defined( 'Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS' ),
					'attested_trip_care_receipts' => $vip_intake_ready,
					'no_login_scoped_sessions' => $vip_capability_ready,
					'customer_trip_cockpit'     => $customer_cockpit_ready,
					'raw_trip_care_intake'     => false,
					'supplier_search'        => false,
					'supplier_dispatch'      => false,
					'proposal_generation'    => false,
					'payment_execution'      => false,
					'booking_execution'      => false,
					'reservation_execution'  => false,
					'ticket_issuance'        => false,
				),
				'limits'           => array(
					'per_visitor_requests'     => $visitor_limit,
					'per_visitor_window_seconds'=> 600,
					'global_requests_per_utc_day'=> $daily_limit,
					'provider_concurrency'        => $concurrency_limit,
				),
				'notifications'    => class_exists( 'Tra_Vel_Agent_Notifier' )
					? Tra_Vel_Agent_Notifier::health()
					: array(
						'operator_email'        => false,
						'recipients_configured' => 0,
						'webhook_configured'    => false,
						'customer_email'        => false,
					),
				'agent_store'      => class_exists( 'Tra_Vel_Agent_Store' )
					? Tra_Vel_Agent_Store::schema_health()
					: array(
						'schema_version'           => null,
						'installed_schema_version' => null,
						'expected_tables'          => 4,
						'ready_tables'             => 0,
						'transactional_tables'     => 0,
						'required_indexes'         => 9,
						'ready_indexes'            => 0,
						'required_indexes_ready'   => false,
						'tables_ready'             => false,
					),
				'quote_case_store' => class_exists( 'Tra_Vel_Quote_Case_Store' )
					? Tra_Vel_Quote_Case_Store::schema_health()
					: array(
						'schema_version'           => null,
						'installed_schema_version' => null,
						'expected_tables'          => 4,
						'ready_tables'             => 0,
						'tables_ready'             => false,
						'active_days'              => 0,
						'retention_days'           => 0,
					),
				'commercial_intent_store' => class_exists( 'Tra_Vel_Commercial_Intent_Store' )
					? Tra_Vel_Commercial_Intent_Store::schema_health()
					: array(
						'schema_version'           => null,
						'installed_schema_version' => null,
						'expected_tables'          => 3,
						'ready_tables'             => 0,
						'tables_ready'             => false,
						'active_days'              => 0,
						'retention_days'           => 0,
					),
				'assisted_proposal_store' => $proposal_store_health,
				'vip_intake_store' => $vip_intake_store_health,
				'vip_capability_session_store' => $vip_capability_store_health,
				'customer_trip_cockpit_store' => $customer_cockpit_store_health,
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

	public function get_run_summary_schema() {
		$path = TRA_VEL_AGENT_PATH . '/schemas/agent-run-summary.schema.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_agent_schema_missing', 'AgentRun summary schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $schema ) ? rest_ensure_response( $schema ) : new WP_Error( 'tra_vel_agent_schema_invalid', 'AgentRun summary schema is invalid.', array( 'status' => 500 ) );
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

	/**
	 * Store the encrypted operator notification webhook. The endpoint URL is
	 * never echoed back; only safe configuration state is returned.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function store_notification_webhook( WP_REST_Request $request ) {
		if ( 'STORE TRA-VEL NOTIFICATION WEBHOOK' !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_webhook_confirmation', 'Webhook confirmation did not match.', array( 'status' => 400 ) );
		}
		$stored = Tra_Vel_Agent_Notifier::store_webhook_url( $request->get_param( 'webhook_url' ) );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return $this->private_response( array( 'ok' => true, 'webhook' => Tra_Vel_Agent_Notifier::webhook_status() ) );
	}

	public function clear_notification_webhook() {
		Tra_Vel_Agent_Notifier::clear_webhook_url();
		return $this->private_response( array( 'ok' => true, 'webhook' => Tra_Vel_Agent_Notifier::webhook_status() ) );
	}

	/**
	 * Store the configured operator notification recipients. Addresses are
	 * operator infrastructure, not secrets; only safe configuration state is
	 * returned.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function store_notification_recipients( WP_REST_Request $request ) {
		if ( 'STORE TRA-VEL NOTIFICATION RECIPIENTS' !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_recipients_confirmation', 'Recipient confirmation did not match.', array( 'status' => 400 ) );
		}
		$stored = Tra_Vel_Agent_Notifier::store_recipients( $request->get_param( 'recipients' ) );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return $this->private_response( array( 'ok' => true, 'recipients' => Tra_Vel_Agent_Notifier::recipients_status() ) );
	}

	public function clear_notification_recipients() {
		Tra_Vel_Agent_Notifier::clear_recipients();
		return $this->private_response( array( 'ok' => true, 'recipients' => Tra_Vel_Agent_Notifier::recipients_status() ) );
	}

	/**
	 * Store the configured interpretation model. Model identifiers are
	 * provider configuration, not secrets; the value is validated against the
	 * strict provider allowlist and only safe configuration state is returned.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function store_model( WP_REST_Request $request ) {
		if ( 'STORE TRA-VEL AGENT MODEL' !== $request->get_param( 'confirmation' ) ) {
			return new WP_Error( 'tra_vel_agent_model_confirmation', 'Model confirmation did not match.', array( 'status' => 400 ) );
		}
		$stored = Tra_Vel_Agent_OpenAI_Provider::store_model( $request->get_param( 'model' ) );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return $this->private_response( array( 'ok' => true, 'model' => Tra_Vel_Agent_OpenAI_Provider::model_status() ) );
	}

	public function clear_model() {
		Tra_Vel_Agent_OpenAI_Provider::clear_model();
		return $this->private_response( array( 'ok' => true, 'model' => Tra_Vel_Agent_OpenAI_Provider::model_status() ) );
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

	public function public_run_summary( $run ) {
		$trip_request = isset( $run['trip_request'] ) && is_array( $run['trip_request'] ) ? $run['trip_request'] : array();
		$proposals    = isset( $run['proposals'] ) && is_array( $run['proposals'] ) ? $run['proposals'] : array();
		$readiness    = isset( $trip_request['readiness'] ) && is_array( $trip_request['readiness'] ) ? $trip_request['readiness'] : array();
		$status       = isset( $readiness['status'] ) && in_array( $readiness['status'], array( 'needs_clarification', 'ready_for_search', 'unsupported' ), true ) ? $readiness['status'] : 'unsupported';
		$blockers     = isset( $readiness['blockers'] ) && is_array( $readiness['blockers'] ) ? $readiness['blockers'] : array();
		$blockers     = array_values(
			array_filter(
				array_slice(
					array_map(
						function ( $blocker ) {
							return $this->bounded_public_text( $blocker, 200 );
						},
						$blockers
					),
					0,
					20
				)
			)
		);
		return array(
			'run_id'            => (string) $run['run_uuid'],
			'status'            => (string) $run['status'],
			'mode'              => (string) $run['mode'],
			'locale'            => (string) $run['locale'],
			'summary'           => $this->bounded_public_text( $trip_request['summary'] ?? '', 500 ),
			'planning_context'  => $this->normalize_planning_context( $trip_request['planning_context'] ?? array() ),
			'readiness'         => array( 'status' => $status, 'blockers' => $blockers ),
			'request_revision'  => absint( $trip_request['revision'] ?? 0 ),
			'proposal_count'    => count( $proposals ),
			'created_at'        => gmdate( 'c', strtotime( $run['created_at'] . ' UTC' ) ),
			'updated_at'        => gmdate( 'c', strtotime( $run['updated_at'] . ' UTC' ) ),
			'expires_at'        => gmdate( 'c', strtotime( $run['expires_at'] . ' UTC' ) ),
			'resume_available'  => strtotime( $run['expires_at'] . ' UTC' ) >= time(),
		);
	}

	/**
	 * Bound traveler-visible text without splitting a UTF-8 code point.
	 *
	 * @param mixed $value  Stored scalar value.
	 * @param int   $length Maximum Unicode characters.
	 * @return string
	 */
	private function bounded_public_text( $value, $length ) {
		$value  = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		$length = max( 0, absint( $length ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		$bounded = substr( $value, 0, $length );
		while ( '' !== $bounded && 1 !== preg_match( '//u', $bounded ) ) {
			$bounded = substr( $bounded, 0, -1 );
		}
		return $bounded;
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
			'planning_context'     => array(
				'type'                 => 'object',
				'required'             => false,
				'default'              => array(
					'kind'         => 'free_text',
					'selection_id' => null,
					'latitude'     => null,
					'longitude'    => null,
					'destination'  => null,
					'intent'       => 'smart',
					'scope'        => array(),
				),
				'properties'           => $this->planning_context_schema()['properties'],
				'additionalProperties' => false,
				'validate_callback'    => array( $this, 'validate_planning_context' ),
				'sanitize_callback'    => array( $this, 'sanitize_planning_context' ),
			),
			'client_request_id'    => array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	public function sanitize_prompt( $value ) {
		$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 4000 ) : substr( $value, 0, 4000 );
	}

	/**
	 * Validate structured map and destination context before it reaches policy.
	 *
	 * @param mixed           $value   Raw value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_planning_context( $value, $request, $param ) {
		$valid = rest_validate_value_from_schema( $value, $this->planning_context_schema(), $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$value = is_array( $value ) ? $value : array();
		$kind  = isset( $value['kind'] ) ? (string) $value['kind'] : 'free_text';
		$id    = isset( $value['selection_id'] ) ? (string) $value['selection_id'] : '';
		if ( in_array( $kind, array( 'map_point', 'destination' ), true ) && ! preg_match( '/^[A-Za-z0-9_-]{8,80}$/', $id ) ) {
			return new WP_Error( 'tra_vel_agent_selection_id_required', 'Map and destination planning contexts require a valid selection_id.', array( 'status' => 400 ) );
		}
		if ( 'map_point' === $kind && ( ! isset( $value['latitude'], $value['longitude'] ) || ! is_numeric( $value['latitude'] ) || ! is_numeric( $value['longitude'] ) ) ) {
			return new WP_Error( 'tra_vel_agent_map_coordinates_required', 'Map-point planning context requires a complete coordinate pair.', array( 'status' => 400 ) );
		}
		$has_latitude  = isset( $value['latitude'] ) && null !== $value['latitude'];
		$has_longitude = isset( $value['longitude'] ) && null !== $value['longitude'];
		if ( $has_latitude !== $has_longitude ) {
			return new WP_Error( 'tra_vel_agent_coordinate_pair_required', 'Planning coordinates must contain both latitude and longitude.', array( 'status' => 400 ) );
		}
		if ( 'destination' === $kind && empty( $value['destination'] ) ) {
			return new WP_Error( 'tra_vel_agent_destination_required', 'Destination planning context requires a destination identifier.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Sanitize a validated planning context with the same JSON schema used by OPTIONS.
	 *
	 * @param mixed           $value   Raw value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return array|WP_Error
	 */
	public function sanitize_planning_context( $value, $request, $param ) {
		$sanitized = rest_sanitize_value_from_schema( $value, $this->planning_context_schema(), $param );
		return is_wp_error( $sanitized ) ? $sanitized : $this->normalize_planning_context( $sanitized );
	}

	/**
	 * Planning context schema shared by route validation and TripRequest policy.
	 *
	 * @return array
	 */
	private function planning_context_schema() {
		$scope_values = array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' );
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'kind'         => array( 'type' => 'string', 'enum' => array( 'free_text', 'destination', 'map_point' ), 'default' => 'free_text' ),
				'selection_id' => array( 'type' => array( 'string', 'null' ), 'pattern' => '^[A-Za-z0-9_-]{8,80}$' ),
				'latitude'     => array( 'type' => array( 'number', 'null' ), 'minimum' => -90, 'maximum' => 90 ),
				'longitude'    => array( 'type' => array( 'number', 'null' ), 'minimum' => -180, 'maximum' => 180 ),
				'destination'  => array( 'type' => array( 'string', 'null' ), 'pattern' => '^[a-z0-9-]{1,60}$' ),
				'intent'       => array( 'type' => 'string', 'enum' => array( 'smart', 'value', 'easy', 'romantic', 'family', 'adventure', 'surprise' ), 'default' => 'smart' ),
				'scope'        => array( 'type' => 'array', 'maxItems' => 8, 'uniqueItems' => true, 'items' => array( 'type' => 'string', 'enum' => $scope_values ) ),
			),
		);
	}

	/**
	 * Normalize planning context defensively after REST schema sanitation.
	 *
	 * @param mixed $value Sanitized context.
	 * @return array
	 */
	private function normalize_planning_context( $value ) {
		$value          = is_array( $value ) ? $value : array();
		$allowed_kinds  = array( 'free_text', 'destination', 'map_point' );
		$allowed_intents = array( 'smart', 'value', 'easy', 'romantic', 'family', 'adventure', 'surprise' );
		$allowed_scope  = array( 'flights', 'accommodation', 'transfers', 'activities', 'dining', 'insurance', 'connectivity', 'equipment' );
		$kind           = isset( $value['kind'] ) && in_array( $value['kind'], $allowed_kinds, true ) ? $value['kind'] : 'free_text';
		$selection_id   = isset( $value['selection_id'] ) && preg_match( '/^[A-Za-z0-9_-]{8,80}$/', (string) $value['selection_id'] ) ? (string) $value['selection_id'] : null;
		$latitude       = isset( $value['latitude'] ) && is_numeric( $value['latitude'] ) && (float) $value['latitude'] >= -90 && (float) $value['latitude'] <= 90 ? (float) $value['latitude'] : null;
		$longitude      = isset( $value['longitude'] ) && is_numeric( $value['longitude'] ) && (float) $value['longitude'] >= -180 && (float) $value['longitude'] <= 180 ? (float) $value['longitude'] : null;
		$destination    = isset( $value['destination'] ) ? substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $value['destination'] ) ), 0, 60 ) : '';
		$scope          = isset( $value['scope'] ) && is_array( $value['scope'] ) ? array_values( array_unique( array_intersect( $allowed_scope, $value['scope'] ) ) ) : array();
		$has_coordinates = null !== $latitude && null !== $longitude;
		if ( 'map_point' === $kind && ( ! $has_coordinates || ! $selection_id ) ) {
			$kind = 'free_text';
		}
		if ( 'destination' === $kind && ( ! $destination || ! $selection_id ) ) {
			$kind = 'free_text';
		}
		return array(
			'kind'         => $kind,
			'selection_id' => 'free_text' === $kind ? null : $selection_id,
			'latitude'     => in_array( $kind, array( 'map_point', 'destination' ), true ) && $has_coordinates ? $latitude : null,
			'longitude'    => in_array( $kind, array( 'map_point', 'destination' ), true ) && $has_coordinates ? $longitude : null,
			'destination'  => in_array( $kind, array( 'map_point', 'destination' ), true ) && $destination ? $destination : null,
			'intent'       => isset( $value['intent'] ) && in_array( $value['intent'], $allowed_intents, true ) ? $value['intent'] : 'smart',
			'scope'        => array_slice( $scope, 0, 8 ),
		);
	}

	private function uuid_arg() {
		return array( 'type' => 'string', 'required' => true, 'format' => 'uuid', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function consume_rate_limit() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$window_seconds = 10 * MINUTE_IN_SECONDS;
		$window_id      = (int) floor( time() / $window_seconds );
		$visitor_limit  = max( 1, (int) apply_filters( 'tra_vel_agent_visitor_request_limit', 8 ) );
		$key            = 'visitor:' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 40 ) . ':' . $window_id;
		$allowed        = $this->store->consume_limit( $key, $visitor_limit, ( $window_id + 1 ) * $window_seconds + MINUTE_IN_SECONDS );
		if ( ! $allowed ) {
			return new WP_Error( 'tra_vel_agent_rate_limited', 'Too many agent requests. Please wait before trying again.', array( 'status' => 429, 'retry_after' => 600 ) );
		}
		return true;
	}

	private function consume_daily_budget() {
		$daily_limit = max( 1, (int) apply_filters( 'tra_vel_agent_daily_request_limit', 200 ) );
		$daily_key = 'global:' . gmdate( 'Ymd' );
		$allowed   = $this->store->consume_limit( $daily_key, $daily_limit, time() + DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS );
		if ( ! $allowed ) {
			return new WP_Error( 'tra_vel_agent_daily_capacity', 'The private planner has reached its daily live-request capacity.', array( 'status' => 429, 'retry_after' => DAY_IN_SECONDS ) );
		}
		return true;
	}
}
