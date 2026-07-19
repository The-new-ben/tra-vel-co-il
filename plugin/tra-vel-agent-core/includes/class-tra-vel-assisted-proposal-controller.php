<?php
/**
 * Case-bound REST control plane for sourced assisted proposals.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Assisted_Proposal_Controller extends WP_REST_Controller {
	const OWNER_COOKIE = '__Host-tra_vel_quote_owner';
	const CONTACT_CONSENT_VERSION = '2026-07-19';
	const CONTACT_CONSENT_CONTRACT_VERSION = '1.0.0';
	const CONTACT_CONSENT_PURPOSE = 'assisted_proposal_follow_up';
	const CONTACT_CONSENT_CONTROLLER_SCOPE = 'tra_vel';

	/** @var Tra_Vel_Assisted_Proposal_Store|null */
	private $store;

	/** @var Tra_Vel_Quote_Case_Store|null */
	private $case_store;

	/** @var array<string,array>|null */
	private $schema_cache = array();

	public function __construct( $store = null, $case_store = null ) {
		$this->namespace  = 'tra-vel-agent/v1';
		$this->rest_base  = 'assisted-proposals';
		$this->store      = $store ? $store : ( class_exists( 'Tra_Vel_Assisted_Proposal_Store' ) ? new Tra_Vel_Assisted_Proposal_Store() : null );
		$this->case_store = $case_store ? $case_store : ( class_exists( 'Tra_Vel_Quote_Case_Store' ) ? new Tra_Vel_Quote_Case_Store() : null );
	}

	public function register_routes() {
		add_filter( 'rest_post_dispatch', array( $this, 'protect_private_response' ), 10, 3 );

		register_rest_route(
			$this->namespace,
			'/schema/assisted-proposal',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_proposal_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/assisted-proposal-source',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_source_schema' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace,
			'/schema/assisted-proposal-traveler',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_traveler_proposal_schema' ),
				'permission_callback' => '__return_true',
			)
		);

		$traveler_collection = '/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/' . $this->rest_base;
		$traveler_item       = $traveler_collection . '/(?P<proposal_id>[0-9a-fA-F-]{36})';
		register_rest_route(
			$this->namespace,
			$traveler_collection,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_traveler_proposals' ),
				'permission_callback' => array( $this, 'can_access_traveler_case' ),
				'args'                => array(
					'case_id'  => $this->uuid_arg(),
					'per_page' => $this->per_page_arg(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$traveler_item,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_traveler_proposal' ),
				'permission_callback' => array( $this, 'can_access_traveler_case' ),
				'args'                => array(
					'case_id'     => $this->uuid_arg(),
					'proposal_id' => $this->uuid_arg(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$traveler_item . '/actions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_traveler_action' ),
				'permission_callback' => array( $this, 'can_access_traveler_case' ),
				'args'                => array_merge(
					array(
						'case_id'     => $this->uuid_arg(),
						'proposal_id' => $this->uuid_arg(),
						'action'      => $this->action_arg(),
						'contact_consent' => $this->contact_consent_arg(),
					),
					$this->mutation_args( 1 )
				),
			)
		);

		$operator_collection = '/operator/quote-cases/(?P<case_id>[0-9a-fA-F-]{36})/' . $this->rest_base;
		$operator_item       = $operator_collection . '/(?P<proposal_id>[0-9a-fA-F-]{36})';
		register_rest_route(
			$this->namespace,
			$operator_collection,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_operator_proposals' ),
					'permission_callback' => array( $this, 'can_view_proposals' ),
					'args'                => array(
						'case_id'  => $this->uuid_arg(),
						'per_page' => $this->per_page_arg(),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish_proposal' ),
					'permission_callback' => array( $this, 'can_ingest_proposals' ),
					'args'                => array_merge(
						array(
							'case_id'  => $this->uuid_arg(),
							'proposal' => array(
								'type'              => 'object',
								'required'          => true,
								'validate_callback' => array( $this, 'validate_proposal_arg' ),
							),
						),
						$this->mutation_args( 0 )
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$operator_collection . '/evidence-attestation',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'attest_composition_evidence' ),
				'permission_callback' => array( $this, 'can_publish_proposals' ),
				'args'                => array_merge(
					array(
						'case_id'     => $this->uuid_arg(),
						'composition' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
					$this->composition_context_args()
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$operator_collection . '/compose',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compose_proposal' ),
				'permission_callback' => array( $this, 'can_publish_proposals' ),
				'args'                => array_merge(
					array(
						'case_id'     => $this->uuid_arg(),
						'composition' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
					$this->composition_mutation_args( 0 )
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$operator_item,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_operator_proposal' ),
				'permission_callback' => array( $this, 'can_view_proposals' ),
				'args'                => array(
					'case_id'     => $this->uuid_arg(),
					'proposal_id' => $this->uuid_arg(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$operator_item . '/compose',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compose_proposal_revision' ),
				'permission_callback' => array( $this, 'can_publish_proposals' ),
				'args'                => array_merge(
					array(
						'case_id'     => $this->uuid_arg(),
						'proposal_id' => $this->uuid_arg(),
						'composition' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
					$this->composition_mutation_args( 1 )
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$operator_item . '/withdraw',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'withdraw_proposal' ),
				'permission_callback' => array( $this, 'can_publish_proposals' ),
				'args'                => array_merge(
					array(
						'case_id'     => $this->uuid_arg(),
						'proposal_id' => $this->uuid_arg(),
					),
					$this->mutation_args( 1 )
				),
			)
		);
	}

	public function get_proposal_schema() {
		return $this->schema_response( 'assisted-proposal.schema.json' );
	}

	public function get_source_schema() {
		return $this->schema_response( 'assisted-proposal-source.schema.json' );
	}

	public function get_traveler_proposal_schema() {
		$schema = $this->traveler_proposal_schema();
		return is_wp_error( $schema ) ? $schema : rest_ensure_response( $schema );
	}

	public function can_access_traveler_case( WP_REST_Request $request ) {
		$mutation = WP_REST_Server::CREATABLE === $request->get_method();
		if ( $mutation ) {
			$same_site = $this->same_site_mutation( $request );
			if ( true !== $same_site ) {
				return $same_site;
			}
		}
		// A retained owner may reach the callback so an exact committed retry can
		// resolve. The mutation itself still requires an active case unless its
		// principal-scoped receipt already exists.
		$case = $this->load_case( $request->get_param( 'case_id' ), true, false );
		return is_wp_error( $case ) ? $case : true;
	}

	public function can_view_proposals() {
		$ready = $this->can_use_stores();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! class_exists( 'Tra_Vel_Quote_Case_Capabilities' ) || ! defined( 'Tra_Vel_Quote_Case_Capabilities::VIEW_CASES' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_capability_unavailable', 'Proposal authorization is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return current_user_can( Tra_Vel_Quote_Case_Capabilities::VIEW_CASES )
			? true
			: new WP_Error( 'tra_vel_assisted_proposal_view_forbidden', 'Viewing assisted proposals requires the quote-case view capability.', array( 'status' => 403 ) );
	}

	public function can_publish_proposals() {
		$ready = $this->can_use_stores();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! class_exists( 'Tra_Vel_Quote_Case_Capabilities' ) || ! defined( 'Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_capability_unavailable', 'Proposal authorization is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return current_user_can( Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS )
			? true
			: new WP_Error( 'tra_vel_assisted_proposal_publish_forbidden', 'Publishing or withdrawing assisted proposals requires the dedicated proposal capability.', array( 'status' => 403 ) );
	}

	/**
	 * Gate the low-level canonical ingestion endpoint separately from the human
	 * composer. Quote operators must use the server-owned reduced command.
	 *
	 * @return true|WP_Error
	 */
	public function can_ingest_proposals() {
		$ready = $this->can_use_stores();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! class_exists( 'Tra_Vel_Quote_Case_Capabilities' ) || ! defined( 'Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_capability_unavailable', 'Canonical proposal ingestion authorization is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return current_user_can( Tra_Vel_Quote_Case_Capabilities::INGEST_PROPOSALS )
			? true
			: new WP_Error( 'tra_vel_assisted_proposal_ingest_forbidden', 'Canonical proposal ingestion requires the dedicated trusted-service capability.', array( 'status' => 403 ) );
	}

	public function can_use_stores() {
		if ( ! $this->store || ! $this->case_store || ! class_exists( 'Tra_Vel_Assisted_Proposal_Store' ) || ! class_exists( 'Tra_Vel_Quote_Case_Store' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is temporarily unavailable.', array( 'status' => 503 ) );
		}
		if ( ! method_exists( 'Tra_Vel_Assisted_Proposal_Store', 'is_ready' ) || ! method_exists( 'Tra_Vel_Quote_Case_Store', 'is_ready' ) || ! Tra_Vel_Assisted_Proposal_Store::is_ready() || ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_store_unavailable', 'Assisted proposal storage is temporarily unavailable.', array( 'status' => 503 ) );
		}
		return true;
	}

	public function list_traveler_proposals( WP_REST_Request $request ) {
		$case = $this->load_case( $request->get_param( 'case_id' ), true, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$proposals = $this->list_case_proposals( $case, (int) $request->get_param( 'per_page' ), true );
		return is_wp_error( $proposals ) ? $proposals : $this->private_response( array( 'proposals' => $proposals, 'meta' => array( 'count' => count( $proposals ) ) ) );
	}

	public function get_traveler_proposal( WP_REST_Request $request ) {
		$case = $this->load_case( $request->get_param( 'case_id' ), true, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$bundle = $this->read_bound_bundle( $case, $request->get_param( 'proposal_id' ), true, false );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		$proposal = $this->project_proposal_bundle( $bundle, true );
		return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal ) );
	}

	public function record_traveler_action( WP_REST_Request $request ) {
		$envelope = $this->closed_json_envelope( $request, array( 'action', 'contact_consent', 'expected_version', 'idempotency_key' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		$action = (string) $request->get_param( 'action' );
		if ( ! in_array( $action, $this->traveler_actions(), true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_action_unsupported', 'This traveler action is not supported.', array( 'status' => 400 ) );
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), true, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		if ( ! method_exists( $this->store, 'replay_traveler_action' ) || ! method_exists( $this->store, 'record_traveler_action' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_action_unavailable', 'Traveler proposal actions are temporarily unavailable.', array( 'status' => 503 ) );
		}
		$principal = $this->traveler_principal();
		$contact_consent = $this->normalize_contact_consent( $action, $request->get_json_params() );
		if ( is_wp_error( $contact_consent ) ) {
			return $contact_consent;
		}
		$replay = $this->store->replay_traveler_action(
			$case,
			$request->get_param( 'proposal_id' ),
			(int) $request->get_param( 'expected_version' ),
			$principal,
			$action,
			$contact_consent,
			$request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( is_array( $replay ) ) {
			$proposal = $this->project_proposal_bundle( $replay, true );
			return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal, 'action' => $action, 'replayed' => true ) );
		}
		if ( ! $this->case_is_active( $case ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active for proposal mutation.', array( 'status' => 409 ) );
		}
		$target_valid = $this->validate_contact_target( $contact_consent, $principal );
		if ( is_wp_error( $target_valid ) ) {
			return $target_valid;
		}
		$bound = $this->read_bound_bundle( $case, $request->get_param( 'proposal_id' ), true, true );
		if ( is_wp_error( $bound ) ) {
			return $bound;
		}
		$result = $this->store->record_traveler_action(
			$case,
			$request->get_param( 'proposal_id' ),
			(int) $request->get_param( 'expected_version' ),
			$principal,
			$action,
			$contact_consent,
			$request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( empty( $result['replayed'] ) && in_array( $action, array( 'authorize_contact', 'request_changes', 'decline' ), true ) ) {
			/**
			 * Announce one durably committed material traveler decision. The store
			 * transaction has already committed; idempotent replays never re-fire
			 * this action. The payload carries opaque identifiers only.
			 */
			do_action(
				'tra_vel_quote_case_traveler_action',
				(string) $case['case_uuid'],
				strtolower( (string) $request->get_param( 'proposal_id' ) ),
				$action
			);
		}
		$proposal = $this->project_proposal_bundle( $result, true );
		return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal, 'action' => $action, 'replayed' => ! empty( $result['replayed'] ) ) );
	}

	public function list_operator_proposals( WP_REST_Request $request ) {
		$case = $this->load_case( $request->get_param( 'case_id' ), false, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$proposals = $this->list_case_proposals( $case, (int) $request->get_param( 'per_page' ), false );
		return is_wp_error( $proposals ) ? $proposals : $this->private_response( array( 'proposals' => $proposals, 'meta' => array( 'count' => count( $proposals ) ) ) );
	}

	public function get_operator_proposal( WP_REST_Request $request ) {
		$case = $this->load_case( $request->get_param( 'case_id' ), false, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$bundle = $this->read_bound_bundle( $case, $request->get_param( 'proposal_id' ), false, false );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		$proposal = $this->project_proposal_bundle( $bundle );
		return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal ) );
	}

	public function publish_proposal( WP_REST_Request $request ) {
		$permission = $this->can_ingest_proposals();
		if ( true !== $permission ) {
			return $permission;
		}
		$envelope = $this->closed_json_envelope( $request, array( 'proposal', 'expected_version', 'idempotency_key' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		$proposal_valid = $this->validate_proposal_arg( $request->get_param( 'proposal' ), $request, 'proposal' );
		if ( true !== $proposal_valid ) {
			return $proposal_valid;
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), false, true );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$proposal = $request->get_param( 'proposal' );
		$binding  = $this->validate_proposal_case_binding( $proposal, $case );
		if ( is_wp_error( $binding ) ) {
			return $binding;
		}
		if ( ! method_exists( $this->store, 'publish_revision' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_publish_unavailable', 'Proposal publication is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$result = $this->store->publish_revision(
			$case,
			$proposal,
			(array) $proposal['sources'],
			(int) $request->get_param( 'expected_version' ),
			$this->operator_principal(),
			$request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->announce_committed_publication( $case, $result );
		$projected = $this->project_proposal_bundle( $result );
		if ( is_wp_error( $projected ) ) {
			return $projected;
		}
		return $this->private_response( array( 'proposal' => $projected, 'replayed' => ! empty( $result['replayed'] ) ), empty( $result['replayed'] ) ? 201 : 200 );
	}

	/**
	 * Sign a five-minute final-evidence attestation for the exact authored
	 * composition. No supplier verification, inventory hold, or booking occurs.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function attest_composition_evidence( WP_REST_Request $request ) {
		$envelope = $this->closed_json_envelope( $request, array( 'composition', 'expected_case_version', 'expected_case_revision', 'expected_request_digest' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		if ( ! class_exists( 'Tra_Vel_Assisted_Proposal_Composer' ) || ! method_exists( 'Tra_Vel_Assisted_Proposal_Composer', 'issue_evidence_attestation' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_attestation_unavailable', 'Evidence attestation is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), false, true );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$precondition = $this->validate_composition_case_precondition( $case, $request );
		if ( is_wp_error( $precondition ) ) {
			return $precondition;
		}
		$attestation = Tra_Vel_Assisted_Proposal_Composer::issue_evidence_attestation( $request->get_param( 'composition' ), $case, get_current_user_id() );
		return is_wp_error( $attestation ) ? $attestation : $this->private_response( $attestation );
	}

	/**
	 * Build and publish a new proposal from bounded operator fields.
	 *
	 * Identity, case binding, source digests, ledger totals, lifecycle fields and
	 * mandatory review gaps are generated by the server. This endpoint never
	 * represents a booking, payment, reservation, ticket or final quote.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compose_proposal( WP_REST_Request $request ) {
		$envelope = $this->closed_json_envelope( $request, array( 'composition', 'expected_version', 'expected_case_version', 'expected_case_revision', 'expected_request_digest', 'idempotency_key' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		if ( 0 !== (int) $request->get_param( 'expected_version' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_version_invalid', 'A composed proposal must begin with expected version zero.', array( 'status' => 409 ) );
		}
		if ( ! class_exists( 'Tra_Vel_Assisted_Proposal_Composer' ) || ! method_exists( 'Tra_Vel_Assisted_Proposal_Composer', 'compose' ) || ! method_exists( $this->store, 'publish_composed_revision' ) || ! method_exists( $this->store, 'replay_composed_revision' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_unavailable', 'Secure proposal composition is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), false, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$command_basis = array(
			'command_version'        => Tra_Vel_Assisted_Proposal_Composer::COMMAND_VERSION,
			'mode'                   => 'create',
			'expected_case_version'  => (int) $request->get_param( 'expected_case_version' ),
			'expected_case_revision' => (int) $request->get_param( 'expected_case_revision' ),
			'expected_request_digest'=> strtolower( (string) $request->get_param( 'expected_request_digest' ) ),
			'composition'            => $request->get_param( 'composition' ),
		);
		$replay = $this->store->replay_composed_revision( $case, 0, $this->operator_principal(), $request->get_param( 'idempotency_key' ), $command_basis );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( is_array( $replay ) ) {
			$projected = $this->project_proposal_bundle( $replay );
			return is_wp_error( $projected ) ? $projected : $this->private_response( array( 'proposal' => $projected, 'replayed' => true ), 200 );
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$precondition = $this->validate_composition_case_precondition( $case, $request );
		if ( is_wp_error( $precondition ) ) {
			return $precondition;
		}
		$attestation = Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $request->get_param( 'composition' ), $case, get_current_user_id() );
		if ( is_wp_error( $attestation ) ) {
			return $attestation;
		}
		$proposal = Tra_Vel_Assisted_Proposal_Composer::compose( $request->get_param( 'composition' ), $case, null, array( 'evidence_checked_at' => (int) $attestation['checked_at'] ) );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}
		$proposal_valid = $this->validate_proposal_arg( $proposal, $request, 'proposal' );
		if ( true !== $proposal_valid ) {
			return $proposal_valid;
		}
		$binding = $this->validate_proposal_case_binding( $proposal, $case );
		if ( is_wp_error( $binding ) ) {
			return $binding;
		}
		$result = $this->store->publish_composed_revision(
			$case,
			$proposal,
			(array) $proposal['sources'],
			0,
			$this->operator_principal(),
			$request->get_param( 'idempotency_key' ),
			$command_basis
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->announce_committed_publication( $case, $result );
		$projected = $this->project_proposal_bundle( $result );
		if ( is_wp_error( $projected ) ) {
			return $projected;
		}
		return $this->private_response( array( 'proposal' => $projected, 'replayed' => ! empty( $result['replayed'] ) ), empty( $result['replayed'] ) ? 201 : 200 );
	}

	/**
	 * Publish a fresh immutable revision of an existing proposal identity.
	 *
	 * The operator resubmits every traveler-facing fact and a fully refreshed
	 * evidence set. Immutable identity and revision sequence are derived from the
	 * locked aggregate rather than accepted from the browser.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compose_proposal_revision( WP_REST_Request $request ) {
		$envelope = $this->closed_json_envelope( $request, array( 'composition', 'expected_version', 'expected_case_version', 'expected_case_revision', 'expected_request_digest', 'idempotency_key' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		if ( ! class_exists( 'Tra_Vel_Assisted_Proposal_Composer' ) || ! method_exists( 'Tra_Vel_Assisted_Proposal_Composer', 'compose' ) || ! method_exists( $this->store, 'publish_composed_revision' ) || ! method_exists( $this->store, 'replay_composed_revision' ) ) {
			return new WP_Error( 'tra_vel_assisted_composition_unavailable', 'Secure proposal composition is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), false, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		$expected_version = (int) $request->get_param( 'expected_version' );
		$command_basis = array(
			'command_version'        => Tra_Vel_Assisted_Proposal_Composer::COMMAND_VERSION,
			'mode'                   => 'revise',
			'proposal_id'            => strtolower( (string) $request->get_param( 'proposal_id' ) ),
			'expected_case_version'  => (int) $request->get_param( 'expected_case_version' ),
			'expected_case_revision' => (int) $request->get_param( 'expected_case_revision' ),
			'expected_request_digest'=> strtolower( (string) $request->get_param( 'expected_request_digest' ) ),
			'composition'            => $request->get_param( 'composition' ),
		);
		$replay = $this->store->replay_composed_revision( $case, $expected_version, $this->operator_principal(), $request->get_param( 'idempotency_key' ), $command_basis );
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( is_array( $replay ) ) {
			$projected = $this->project_proposal_bundle( $replay );
			return is_wp_error( $projected ) ? $projected : $this->private_response( array( 'proposal' => $projected, 'replayed' => true ), 200 );
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$precondition = $this->validate_composition_case_precondition( $case, $request );
		if ( is_wp_error( $precondition ) ) {
			return $precondition;
		}
		$attestation = Tra_Vel_Assisted_Proposal_Composer::verify_evidence_attestation( $request->get_param( 'composition' ), $case, get_current_user_id() );
		if ( is_wp_error( $attestation ) ) {
			return $attestation;
		}
		$bundle = $this->read_bound_bundle( $case, $request->get_param( 'proposal_id' ), false, true );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		$head = is_array( $bundle['head'] ?? null ) ? $bundle['head'] : array();
		$identity = array(
			'proposal_id' => (string) ( $head['proposal_uuid'] ?? '' ),
			'reference'   => (string) ( $head['reference_code'] ?? '' ),
			'position'    => (string) ( $head['position'] ?? '' ),
			'revision'    => (int) ( $head['current_revision'] ?? 0 ) + 1,
			'version'     => $expected_version + 1,
			'evidence_checked_at' => (int) $attestation['checked_at'],
		);
		$proposal = Tra_Vel_Assisted_Proposal_Composer::compose( $request->get_param( 'composition' ), $case, null, $identity );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}
		$proposal_valid = $this->validate_proposal_arg( $proposal, $request, 'proposal' );
		if ( true !== $proposal_valid ) {
			return $proposal_valid;
		}
		$binding = $this->validate_proposal_case_binding( $proposal, $case );
		if ( is_wp_error( $binding ) ) {
			return $binding;
		}
		$result = $this->store->publish_composed_revision(
			$case,
			$proposal,
			(array) $proposal['sources'],
			$expected_version,
			$this->operator_principal(),
			$request->get_param( 'idempotency_key' ),
			$command_basis
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->announce_committed_publication( $case, $result );
		$projected = $this->project_proposal_bundle( $result );
		if ( is_wp_error( $projected ) ) {
			return $projected;
		}
		return $this->private_response( array( 'proposal' => $projected, 'replayed' => ! empty( $result['replayed'] ) ), empty( $result['replayed'] ) ? 201 : 200 );
	}

	public function withdraw_proposal( WP_REST_Request $request ) {
		$envelope = $this->closed_json_envelope( $request, array( 'expected_version', 'idempotency_key' ) );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}
		$case = $this->load_case( $request->get_param( 'case_id' ), false, false );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		if ( ! method_exists( $this->store, 'replay_withdrawal' ) || ! method_exists( $this->store, 'withdraw' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_withdraw_unavailable', 'Proposal withdrawal is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$principal = $this->operator_principal();
		$replay = $this->store->replay_withdrawal(
			$case,
			$request->get_param( 'proposal_id' ),
			(int) $request->get_param( 'expected_version' ),
			$principal,
			$request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $replay ) ) {
			return $replay;
		}
		if ( is_array( $replay ) ) {
			$proposal = $this->project_proposal_bundle( $replay );
			return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal, 'replayed' => true ) );
		}
		if ( ! $this->case_is_active( $case ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active for proposal mutation.', array( 'status' => 409 ) );
		}
		$assignment = $this->authorize_operator_case( $case );
		if ( is_wp_error( $assignment ) ) {
			return $assignment;
		}
		$bound = $this->read_bound_bundle( $case, $request->get_param( 'proposal_id' ), false, true );
		if ( is_wp_error( $bound ) ) {
			return $bound;
		}
		$result = $this->store->withdraw(
			$case,
			$request->get_param( 'proposal_id' ),
			(int) $request->get_param( 'expected_version' ),
			$principal,
			$request->get_param( 'idempotency_key' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$proposal = $this->project_proposal_bundle( $result );
		return is_wp_error( $proposal ) ? $proposal : $this->private_response( array( 'proposal' => $proposal, 'replayed' => ! empty( $result['replayed'] ) ) );
	}

	/**
	 * Route validator: reject unknown fields recursively, then apply the expanded
	 * published JSON schema. Unknown values never reach persistence.
	 */
	public function validate_proposal_arg( $value, $request = null, $param = 'proposal' ) {
		$schema = $this->expanded_schema( 'proposal' );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$closed = $this->reject_unknown_schema_fields( $value, $schema, '$.' . (string) $param );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		if ( ! function_exists( 'rest_validate_value_from_schema' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_schema_validator_unavailable', 'Proposal schema validation is unavailable.', array( 'status' => 503 ) );
		}
		$valid = rest_validate_value_from_schema( $value, $schema, (string) $param );
		return is_wp_error( $valid ) ? new WP_Error( 'tra_vel_assisted_proposal_schema_invalid', 'The proposal does not match the closed publication schema.', array( 'status' => 400, 'cause' => $valid->get_error_code() ) ) : true;
	}

	public function validate_traveler_proposal( $value ) {
		$schema = $this->expanded_traveler_schema();
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$closed = $this->reject_unknown_schema_fields( $value, $schema, '$.proposal' );
		if ( is_wp_error( $closed ) ) {
			return $closed;
		}
		if ( ! function_exists( 'rest_validate_value_from_schema' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_schema_validator_unavailable', 'Traveler proposal schema validation is unavailable.', array( 'status' => 503 ) );
		}
		$valid = rest_validate_value_from_schema( $value, $schema, 'proposal' );
		return is_wp_error( $valid ) ? new WP_Error( 'tra_vel_assisted_proposal_traveler_schema_invalid', 'The proposal does not match the closed traveler response schema.', array( 'status' => 400, 'cause' => $valid->get_error_code() ) ) : true;
	}

	public function protect_private_response( $response, $server, $request ) {
		$route = $request instanceof WP_REST_Request ? (string) $request->get_route() : '';
		if ( false === strpos( $route, '/' . $this->rest_base ) ) {
			return $response;
		}
		if ( is_wp_error( $response ) && function_exists( 'rest_convert_error_to_response' ) ) {
			$response = rest_convert_error_to_response( $response );
		}
		$response = rest_ensure_response( $response );
		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
			$response->header( 'Pragma', 'no-cache' );
		}
		return $response;
	}

	private function list_case_proposals( $case, $limit, $traveler_only ) {
		if ( ! method_exists( $this->store, 'list_by_case' ) || ! method_exists( $this->store, 'get_revision_bundle' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_list_unavailable', 'Proposal listing is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$heads = $this->store->list_by_case( (int) $case['id'], (string) $case['case_uuid'], $limit );
		if ( is_wp_error( $heads ) ) {
			return $heads;
		}
		if ( ! is_array( $heads ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_list_uncertain', 'Proposal listing returned an uncertain result.', array( 'status' => 503 ) );
		}
		$proposals = array();
		foreach ( $heads as $head ) {
			$binding = $this->validate_head_case_identity( $head, $case );
			if ( is_wp_error( $binding ) ) {
				return $binding;
			}
			if ( $traveler_only && (int) ( $head['published_revision'] ?? 0 ) < 1 ) {
				continue;
			}
			$revision = $traveler_only ? (int) $head['published_revision'] : (int) $head['current_revision'];
			$bundle   = $this->store->get_revision_bundle( (string) $head['proposal_uuid'], $revision );
			if ( is_wp_error( $bundle ) ) {
				return $bundle;
			}
			if ( ! is_array( $bundle ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_revision_uncertain', 'A proposal revision could not be read safely.', array( 'status' => 503 ) );
			}
			$stable = $this->validate_bundle_head( $head, $bundle );
			if ( is_wp_error( $stable ) ) {
				return $stable;
			}
			if ( ! $this->head_matches_current_request( $head, $case ) || ! $this->case_is_active( $case ) ) {
				$bundle['_force_superseded'] = true;
			}
			$proposal = $this->project_proposal_bundle( $bundle, $traveler_only );
			if ( is_wp_error( $proposal ) ) {
				return $proposal;
			}
			$proposals[] = $proposal;
		}
		return $proposals;
	}

	private function read_bound_bundle( $case, $proposal_uuid, $traveler_only, $require_current ) {
		if ( ! method_exists( $this->store, 'get_by_uuid' ) || ! method_exists( $this->store, 'get_revision_bundle' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_read_unavailable', 'Proposal reading is temporarily unavailable.', array( 'status' => 503 ) );
		}
		$head = $this->store->get_by_uuid( $proposal_uuid );
		if ( is_wp_error( $head ) ) {
			return $head;
		}
		if ( ! is_array( $head ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_missing', 'Assisted proposal not found.', array( 'status' => 404 ) );
		}
		$binding = $this->validate_head_case_identity( $head, $case );
		if ( is_wp_error( $binding ) ) {
			return $binding;
		}
		$current = $this->head_matches_current_request( $head, $case );
		if ( $require_current && ! $current ) {
			return new WP_Error( 'tra_vel_assisted_proposal_request_changed', 'The quote-case request changed after this proposal was prepared.', array( 'status' => 409 ) );
		}
		if ( $traveler_only && (int) ( $head['published_revision'] ?? 0 ) < 1 ) {
			return new WP_Error( 'tra_vel_assisted_proposal_unavailable', 'This assisted proposal is not available for traveler review.', array( 'status' => 410 ) );
		}
		$revision = $traveler_only ? (int) $head['published_revision'] : (int) $head['current_revision'];
		$bundle   = $this->store->get_revision_bundle( $proposal_uuid, $revision );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		if ( ! is_array( $bundle ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_revision_uncertain', 'A proposal revision could not be read safely.', array( 'status' => 503 ) );
		}
		$stable = $this->validate_bundle_head( $head, $bundle );
		if ( is_wp_error( $stable ) ) {
			return $stable;
		}
		if ( ! $current || ! $this->case_is_active( $case ) ) {
			$bundle['_force_superseded'] = true;
		}
		return $bundle;
	}

	private function load_case( $case_uuid, $require_owner, $require_active ) {
		$ready = $this->can_use_stores();
		if ( true !== $ready ) {
			return $ready;
		}
		if ( ! method_exists( $this->case_store, 'get_case_by_uuid' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_read_unavailable', 'The parent quote case cannot be read safely.', array( 'status' => 503 ) );
		}
		$case = $this->case_store->get_case_by_uuid( $case_uuid );
		if ( is_wp_error( $case ) ) {
			return $case;
		}
		if ( ! is_array( $case ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_missing', 'Quote case not found.', array( 'status' => 404 ) );
		}
		$active_statuses = array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' );
		$retained_statuses = array_merge( $active_statuses, array( 'closed_no_quote', 'cancelled', 'expired' ) );
		$status = (string) ( $case['status'] ?? '' );
		if ( ! in_array( $status, $retained_statuses, true ) || (int) ( $case['id'] ?? 0 ) < 1 || (int) ( $case['case_version'] ?? 0 ) < 1 || (int) ( $case['current_revision'] ?? 0 ) < 1 || ! $this->is_uuid( $case['case_uuid'] ?? '' ) || ! $this->is_sha256( $case['latest_request_digest'] ?? '' ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_invalid', 'The retained parent quote case is incomplete.', array( 'status' => 409 ) );
		}
		if ( $require_active && ! in_array( $status, $active_statuses, true ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active for proposal mutation.', array( 'status' => 409 ) );
		}
		$retention = strtotime( (string) ( $case['retention_until'] ?? '' ) . ( preg_match( '/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', (string) ( $case['retention_until'] ?? '' ) ) ? '' : ' UTC' ) );
		if ( false === $retention || $retention <= time() ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case has no active retention window.', array( 'status' => 409 ) );
		}
		if ( $require_owner ) {
			if ( ! method_exists( $this->case_store, 'can_access' ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_owner_check_unavailable', 'Quote-case ownership cannot be verified safely.', array( 'status' => 503 ) );
			}
			$principal = $this->traveler_principal();
			if ( ! $this->case_store->can_access( $case, (int) $principal['user_id'], (string) $principal['token_hash'] ) ) {
				return new WP_Error( 'tra_vel_assisted_proposal_forbidden', 'This private quote case does not belong to the current traveler.', array( 'status' => 403 ) );
			}
		}
		return $case;
	}

	private function validate_head_case_identity( $head, $case ) {
		if ( ! is_array( $head ) || (int) ( $head['quote_case_id'] ?? 0 ) !== (int) $case['id'] || ! $this->safe_equals( (string) ( $head['quote_case_uuid'] ?? '' ), (string) $case['case_uuid'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_mismatch', 'The proposal is not bound to this quote case.', array( 'status' => 409 ) );
		}
		return true;
	}

	private function head_matches_current_request( $head, $case ) {
		return is_array( $head )
			&& (int) ( $head['source_case_revision'] ?? 0 ) === (int) $case['current_revision']
			&& $this->safe_equals( (string) ( $head['request_digest'] ?? '' ), (string) $case['latest_request_digest'] );
	}

	private function case_is_active( $case ) {
		return is_array( $case ) && in_array( (string) ( $case['status'] ?? '' ), array( 'queued', 'in_review', 'needs_information', 'ready_for_assistance' ), true );
	}

	/**
	 * Reject a new commercial write unless it is authored against the exact
	 * active quote-case/request snapshot displayed to the operator. Idempotent
	 * replay is deliberately attempted before this check so a committed response
	 * remains recoverable after the parent later evolves or closes.
	 *
	 * @param array           $case    Verified retained quote case.
	 * @param WP_REST_Request $request Request carrying the authoring precondition.
	 * @return true|WP_Error
	 */
	private function validate_composition_case_precondition( $case, WP_REST_Request $request ) {
		if ( ! $this->case_is_active( $case ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_inactive', 'The parent quote case is not active for a new proposal publication.', array( 'status' => 409 ) );
		}
		$expected_digest = strtolower( (string) $request->get_param( 'expected_request_digest' ) );
		if (
			(int) $request->get_param( 'expected_case_version' ) !== (int) ( $case['case_version'] ?? 0 )
			|| (int) $request->get_param( 'expected_case_revision' ) !== (int) ( $case['current_revision'] ?? 0 )
			|| ! $this->is_sha256( $expected_digest )
			|| ! $this->safe_equals( (string) ( $case['latest_request_digest'] ?? '' ), $expected_digest )
		) {
			return new WP_Error(
				'tra_vel_assisted_proposal_case_precondition_failed',
				'The traveler request changed after this proposal draft was opened.',
				array(
					'status'                => 409,
					'current_case_version'  => (int) ( $case['case_version'] ?? 0 ),
					'current_case_revision' => (int) ( $case['current_revision'] ?? 0 ),
				)
			);
		}
		return true;
	}

	private function validate_bundle_head( $expected, $bundle ) {
		$actual = is_array( $bundle ) && is_array( $bundle['head'] ?? null ) ? $bundle['head'] : null;
		if ( ! $actual || ! $this->safe_equals( (string) ( $expected['proposal_uuid'] ?? '' ), (string) ( $actual['proposal_uuid'] ?? '' ) ) || (int) ( $expected['proposal_version'] ?? 0 ) !== (int) ( $actual['proposal_version'] ?? -1 ) || (int) ( $expected['current_revision'] ?? 0 ) !== (int) ( $actual['current_revision'] ?? -1 ) || (int) ( $expected['published_revision'] ?? 0 ) !== (int) ( $actual['published_revision'] ?? -1 ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_read_changed', 'The proposal changed while its revision was being read.', array( 'status' => 409 ) );
		}
		return true;
	}

	private function validate_proposal_case_binding( $proposal, $case ) {
		$addresses = is_array( $proposal['addresses'] ?? null ) ? $proposal['addresses'] : array();
		if ( ! $this->safe_equals( (string) ( $proposal['case_id'] ?? '' ), (string) $case['case_uuid'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_parent_mismatch', 'The proposal case identity does not match the route.', array( 'status' => 409 ) );
		}
		if ( (int) ( $addresses['case_revision'] ?? 0 ) !== (int) $case['current_revision'] || ! $this->safe_equals( (string) ( $addresses['request_digest'] ?? '' ), (string) $case['latest_request_digest'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_request_changed', 'The proposal addresses a stale quote-case request.', array( 'status' => 409 ) );
		}
		return true;
	}

	private function project_proposal_bundle( $bundle, $traveler_safe = false ) {
		if ( ! is_array( $bundle ) || ! is_array( $bundle['head'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_bundle_invalid', 'The proposal bundle is incomplete.', array( 'status' => 503 ) );
		}
		$proposal = is_array( $bundle['proposal'] ?? null ) ? $bundle['proposal'] : ( is_array( $bundle['revision_snapshot'] ?? null ) ? $bundle['revision_snapshot'] : null );
		$sources  = is_array( $bundle['sources'] ?? null ) ? $bundle['sources'] : null;
		if ( ! is_array( $proposal ) || ! is_array( $sources ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_bundle_invalid', 'The proposal bundle lacks its immutable traveler-safe revision or source set.', array( 'status' => 503 ) );
		}
		$head = $bundle['head'];
		$proposal['sources']               = $sources;
		$proposal['status']                = (string) ( $head['status'] ?? $proposal['status'] ?? '' );
		$proposal['version']               = (int) ( $head['proposal_version'] ?? $proposal['version'] ?? 0 );
		$proposal['published_revision']    = (int) ( $head['published_revision'] ?? $proposal['published_revision'] ?? 0 );
		$proposal['traveler_disposition']  = (string) ( $head['traveler_disposition'] ?? $proposal['traveler_disposition'] ?? 'unavailable' );
		if ( ! empty( $bundle['_force_superseded'] ) && ! in_array( $proposal['status'], array( 'withdrawn', 'expired', 'superseded' ), true ) ) {
			$proposal['status'] = 'superseded';
		}
		$proposal['next_actions']          = $this->next_actions_for( $proposal['status'], $proposal['traveler_disposition'] );
		if ( in_array( $proposal['status'], array( 'withdrawn', 'expired', 'superseded' ), true ) ) {
			$proposal['traveler_disposition'] = 'unavailable';
			$proposal['next_actions']         = array();
		}

		$schema = $this->expanded_schema( 'proposal' );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$projected = $this->project_schema_value( $proposal, $schema );
		$valid     = $this->validate_proposal_arg( $projected );
		if ( true !== $valid ) {
			return new WP_Error( 'tra_vel_assisted_proposal_projection_invalid', 'The stored proposal cannot be emitted through the closed proposal contract.', array( 'status' => 503, 'cause' => is_wp_error( $valid ) ? $valid->get_error_code() : 'invalid' ) );
		}
		if ( $traveler_safe ) {
			$projected['sources'] = array_map( array( $this, 'project_traveler_source' ), $projected['sources'] );
			unset( $projected['source_set_digest'] );
			if ( isset( $projected['addresses'] ) && is_array( $projected['addresses'] ) ) {
				unset( $projected['addresses']['request_digest'] );
			}
			if ( isset( $projected['ledger'] ) && is_array( $projected['ledger'] ) ) {
				unset( $projected['ledger']['calculation_digest'] );
			}
			$traveler_valid = $this->validate_traveler_proposal( $projected );
			if ( true !== $traveler_valid ) {
				return new WP_Error( 'tra_vel_assisted_proposal_traveler_projection_invalid', 'The proposal cannot be emitted through the closed traveler response contract.', array( 'status' => 503, 'cause' => is_wp_error( $traveler_valid ) ? $traveler_valid->get_error_code() : 'invalid' ) );
			}
		}
		return $projected;
	}

	/**
	 * Remove supplier lookup handles and integrity internals from traveler JSON.
	 * Component/source linkage keeps the pseudonymous source UUID only.
	 *
	 * @param array $source Canonical immutable source.
	 * @return array
	 */
	private function project_traveler_source( $source ) {
		$source_type = (string) $source['source_type'];
		$source_url  = in_array( $source_type, array( 'public_supplier_page', 'official_information' ), true )
			? $source['source_url']
			: null;

		return array(
			'contract_version'      => (string) $source['contract_version'],
			'source_id'             => (string) $source['source_id'],
			'source_type'           => $source_type,
			'public_label'          => (string) $source['public_label'],
			'supplier_name'         => (string) $source['supplier_name'],
			'seller_name'           => (string) $source['seller_name'],
			'source_url'            => $source_url,
			'observed_at'           => (string) $source['observed_at'],
			'fresh_until'           => (string) $source['fresh_until'],
			'requires_revalidation' => true,
		);
	}

	private function next_actions_for( $status, $disposition ) {
		if ( ! class_exists( 'Tra_Vel_Assisted_Proposal_Policy' ) || ! method_exists( 'Tra_Vel_Assisted_Proposal_Policy', 'traveler_actions_for' ) ) {
			return array();
		}
		return Tra_Vel_Assisted_Proposal_Policy::traveler_actions_for( (string) $status, (string) $disposition );
	}

	/**
	 * Announce one durably committed proposal publication or revision.
	 *
	 * The store returns only after its transaction has committed, and replayed
	 * receipts never re-fire the action, so downstream notification and cockpit
	 * listeners observe each published revision exactly once per commit. The
	 * payload carries opaque identifiers only, never traveler or price data.
	 *
	 * @param array $case   Verified parent quote case.
	 * @param array $result Committed store bundle.
	 * @return void
	 */
	private function announce_committed_publication( $case, $result ) {
		if ( ! is_array( $result ) || ! empty( $result['replayed'] ) || ! is_array( $result['head'] ?? null ) ) {
			return;
		}
		$head = $result['head'];
		if ( empty( $head['proposal_uuid'] ) || (int) ( $head['published_revision'] ?? 0 ) < 1 ) {
			return;
		}
		do_action(
			'tra_vel_assisted_proposal_published',
			(string) $case['case_uuid'],
			(string) $head['proposal_uuid'],
			(int) $head['published_revision']
		);
	}

	private function traveler_principal() {
		$user_id    = get_current_user_id();
		$token_hash = $this->owner_token_hash();
		$identity   = $user_id > 0 ? 'user:' . $user_id : 'guest:' . $token_hash;
		return array(
			'user_id'        => $user_id,
			'token_hash'     => $token_hash,
			'principal_hash' => hash( 'sha256', $identity ),
		);
	}

	private function operator_principal() {
		$user_id = get_current_user_id();
		return array(
			'user_id'             => $user_id,
			'principal_hash'       => hash( 'sha256', 'operator:' . $user_id ),
			'assignment_override'  => current_user_can( 'manage_options' ),
		);
	}

	/**
	 * Require an assigned operator, with WordPress administrators as the explicit
	 * recovery/oversight boundary. The store repeats this check after locking the
	 * parent case so reassignment cannot race publication or withdrawal.
	 *
	 * @param array $case Verified quote case.
	 * @return true|WP_Error
	 */
	private function authorize_operator_case( $case ) {
		$user_id = get_current_user_id();
		if ( $user_id > 0 && current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( $user_id > 0 && (int) ( $case['assigned_user_id'] ?? 0 ) === $user_id ) {
			return true;
		}
		return new WP_Error( 'tra_vel_assisted_proposal_assignment_forbidden', 'Claim this quote case before publishing or withdrawing a proposal.', array( 'status' => 403 ) );
	}

	private function owner_token_hash() {
		if ( empty( $_COOKIE[ self::OWNER_COOKIE ] ) ) {
			return '';
		}
		$token = rawurldecode( (string) $_COOKIE[ self::OWNER_COOKIE ] );
		return preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ? hash( 'sha256', $token ) : '';
	}

	private function same_site_mutation( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( get_current_user_id() > 0 && ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_nonce_invalid', 'The signed-in traveler session could not be verified.', array( 'status' => 403 ) );
		}
		$source = (string) $request->get_header( 'Origin' );
		if ( ! $source ) {
			$source = (string) $request->get_header( 'Referer' );
		}
		$home          = home_url( '/' );
		$source_host   = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
		$home_host     = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$source_scheme = strtolower( (string) wp_parse_url( $source, PHP_URL_SCHEME ) );
		$home_scheme   = strtolower( (string) wp_parse_url( $home, PHP_URL_SCHEME ) );
		$source_port   = (int) wp_parse_url( $source, PHP_URL_PORT );
		$home_port     = (int) wp_parse_url( $home, PHP_URL_PORT );
		$source_port   = $source_port > 0 ? $source_port : ( 'https' === $source_scheme ? 443 : 80 );
		$home_port     = $home_port > 0 ? $home_port : ( 'https' === $home_scheme ? 443 : 80 );
		$source_user   = wp_parse_url( $source, PHP_URL_USER );
		$source_pass   = wp_parse_url( $source, PHP_URL_PASS );
		if ( ! $source_host || ! $home_host || ! $this->safe_equals( $home_host, $source_host ) || 'https' !== $source_scheme || 'https' !== $home_scheme || $source_port !== $home_port || $source_user || $source_pass ) {
			return new WP_Error( 'tra_vel_assisted_proposal_origin_rejected', 'Traveler proposal actions must come from the Tra-Vel website.', array( 'status' => 403 ) );
		}
		return true;
	}

	private function closed_json_envelope( WP_REST_Request $request, $allowed ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_json_required', 'A JSON object body is required.', array( 'status' => 400 ) );
		}
		$unknown = array_diff( array_keys( $params ), $allowed );
		return $unknown
			? new WP_Error( 'tra_vel_assisted_proposal_envelope_unknown', 'Unknown request fields are not accepted.', array( 'status' => 400, 'fields' => array_values( $unknown ) ) )
			: true;
	}

	private function schema_response( $filename ) {
		$schema = $this->schema_document( $filename );
		return is_wp_error( $schema ) ? $schema : rest_ensure_response( $schema );
	}

	private function traveler_proposal_schema() {
		$key = 'traveler:proposal';
		if ( isset( $this->schema_cache[ $key ] ) ) {
			return $this->schema_cache[ $key ];
		}
		$proposal = $this->schema_document( 'assisted-proposal.schema.json' );
		$source   = $this->schema_document( 'assisted-proposal-traveler-source.schema.json' );
		if ( is_wp_error( $proposal ) || is_wp_error( $source ) ) {
			return is_wp_error( $proposal ) ? $proposal : $source;
		}
		$proposal['$id'] = 'https://tra-vel.co.il/schemas/agent/assisted-proposal-traveler-1.0.0.json';
		$proposal['title'] = 'Tra-Vel Traveler AssistedProposal';
		$proposal['description'] = 'A traveler-safe, sourced, expiring and non-binding personal travel proposal.';
		$proposal['properties']['sources']['items'] = $source;
		unset( $proposal['properties']['source_set_digest'] );
		$proposal['required'] = array_values( array_diff( $proposal['required'], array( 'source_set_digest' ) ) );
		unset( $proposal['definitions']['addresses']['properties']['request_digest'] );
		$proposal['definitions']['addresses']['required'] = array_values( array_diff( $proposal['definitions']['addresses']['required'], array( 'request_digest' ) ) );
		unset( $proposal['definitions']['ledger']['properties']['calculation_digest'] );
		$proposal['definitions']['ledger']['required'] = array_values( array_diff( $proposal['definitions']['ledger']['required'], array( 'calculation_digest' ) ) );
		$this->schema_cache[ $key ] = $proposal;
		return $proposal;
	}

	private function expanded_traveler_schema() {
		$key = 'expanded:traveler-proposal';
		if ( isset( $this->schema_cache[ $key ] ) ) {
			return $this->schema_cache[ $key ];
		}
		$schema = $this->traveler_proposal_schema();
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$expanded = $this->expand_schema_node( $schema, $schema, 0 );
		if ( is_wp_error( $expanded ) ) {
			return $expanded;
		}
		$this->schema_cache[ $key ] = $expanded;
		return $expanded;
	}

	private function schema_document( $filename ) {
		if ( isset( $this->schema_cache[ $filename ] ) ) {
			return $this->schema_cache[ $filename ];
		}
		$path = defined( 'TRA_VEL_AGENT_PATH' ) ? TRA_VEL_AGENT_PATH . '/schemas/' . $filename : '';
		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_schema_missing', 'Assisted proposal schema is unavailable.', array( 'status' => 503 ) );
		}
		$schema = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_schema_invalid', 'Assisted proposal schema is invalid.', array( 'status' => 500 ) );
		}
		$this->schema_cache[ $filename ] = $schema;
		return $schema;
	}

	private function expanded_schema( $type ) {
		$key = 'expanded:' . $type;
		if ( isset( $this->schema_cache[ $key ] ) ) {
			return $this->schema_cache[ $key ];
		}
		$filename = 'source' === $type ? 'assisted-proposal-source.schema.json' : 'assisted-proposal.schema.json';
		$schema   = $this->schema_document( $filename );
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$expanded = $this->expand_schema_node( $schema, $schema, 0 );
		if ( is_wp_error( $expanded ) ) {
			return $expanded;
		}
		$this->schema_cache[ $key ] = $expanded;
		return $expanded;
	}

	private function expand_schema_node( $node, $root, $depth ) {
		if ( $depth > 40 || ! is_array( $node ) ) {
			return $depth > 40 ? new WP_Error( 'tra_vel_assisted_proposal_schema_recursive', 'Assisted proposal schema recursion is invalid.', array( 'status' => 500 ) ) : $node;
		}
		if ( isset( $node['$ref'] ) ) {
			$ref = (string) $node['$ref'];
			if ( 'assisted-proposal-source.schema.json' === $ref ) {
				$source = $this->schema_document( 'assisted-proposal-source.schema.json' );
				return is_wp_error( $source ) ? $source : $this->expand_schema_node( $source, $source, $depth + 1 );
			}
			if ( 0 === strpos( $ref, '#/' ) ) {
				$resolved = $root;
				foreach ( explode( '/', substr( $ref, 2 ) ) as $part ) {
					$part = str_replace( array( '~1', '~0' ), array( '/', '~' ), $part );
					if ( ! is_array( $resolved ) || ! array_key_exists( $part, $resolved ) ) {
						return new WP_Error( 'tra_vel_assisted_proposal_schema_reference_invalid', 'Assisted proposal schema reference is invalid.', array( 'status' => 500 ) );
					}
					$resolved = $resolved[ $part ];
				}
				return $this->expand_schema_node( $resolved, $root, $depth + 1 );
			}
			return new WP_Error( 'tra_vel_assisted_proposal_schema_reference_invalid', 'External schema references are not allowlisted.', array( 'status' => 500 ) );
		}
		$expanded = array();
		foreach ( $node as $key => $child ) {
			$value = $this->expand_schema_node( $child, $root, $depth + 1 );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
			$expanded[ $key ] = $value;
		}
		return $expanded;
	}

	private function reject_unknown_schema_fields( $value, $schema, $path ) {
		if ( ! is_array( $schema ) ) {
			return true;
		}
		$type = $schema['type'] ?? null;
		$is_object_schema = 'object' === $type || ( is_array( $type ) && in_array( 'object', $type, true ) ) || isset( $schema['properties'] );
		if ( $is_object_schema && is_array( $value ) ) {
			$properties = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
			if ( false === ( $schema['additionalProperties'] ?? true ) ) {
				$unknown = array_diff( array_keys( $value ), array_keys( $properties ) );
				if ( $unknown ) {
					return new WP_Error( 'tra_vel_assisted_proposal_shape_invalid', 'Unknown field at ' . $path . '.' . (string) reset( $unknown ) . '.', array( 'status' => 400 ) );
				}
			}
			foreach ( $value as $key => $child ) {
				if ( isset( $properties[ $key ] ) ) {
					$valid = $this->reject_unknown_schema_fields( $child, $properties[ $key ], $path . '.' . $key );
					if ( is_wp_error( $valid ) ) {
						return $valid;
					}
				}
			}
		}
		$is_array_schema = 'array' === $type || ( is_array( $type ) && in_array( 'array', $type, true ) );
		if ( $is_array_schema && is_array( $value ) && isset( $schema['items'] ) ) {
			foreach ( $value as $index => $child ) {
				$valid = $this->reject_unknown_schema_fields( $child, $schema['items'], $path . '[' . $index . ']' );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
			}
		}
		return true;
	}

	private function project_schema_value( $value, $schema ) {
		if ( ! is_array( $schema ) ) {
			return $value;
		}
		$type = $schema['type'] ?? null;
		$is_object_schema = 'object' === $type || ( is_array( $type ) && in_array( 'object', $type, true ) ) || isset( $schema['properties'] );
		if ( $is_object_schema && is_array( $value ) ) {
			$result     = array();
			$properties = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
			foreach ( $properties as $key => $child_schema ) {
				if ( array_key_exists( $key, $value ) ) {
					$result[ $key ] = $this->project_schema_value( $value[ $key ], $child_schema );
				}
			}
			return $result;
		}
		$is_array_schema = 'array' === $type || ( is_array( $type ) && in_array( 'array', $type, true ) );
		if ( $is_array_schema && is_array( $value ) && isset( $schema['items'] ) ) {
			return array_values( array_map( function ( $item ) use ( $schema ) { return $this->project_schema_value( $item, $schema['items'] ); }, $value ) );
		}
		return $value;
	}

	private function private_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private function traveler_actions() {
		return array( 'review', 'request_changes', 'authorize_contact', 'decline' );
	}

	private function action_arg() {
		return array( 'type' => 'string', 'required' => true, 'enum' => $this->traveler_actions(), 'sanitize_callback' => 'sanitize_key', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function contact_consent_arg() {
		return array(
			'type'                 => 'object',
			'required'             => false,
			'additionalProperties' => false,
			'properties'           => array(
				'contract_version'      => array( 'type' => 'string', 'enum' => array( self::CONTACT_CONSENT_CONTRACT_VERSION ) ),
				'consent_version'       => array( 'type' => 'string', 'enum' => array( self::CONTACT_CONSENT_VERSION ) ),
				'affirmed'              => array( 'type' => 'boolean', 'enum' => array( true ) ),
				'purpose'               => array( 'type' => 'string', 'enum' => array( self::CONTACT_CONSENT_PURPOSE ) ),
				'channels'              => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 1, 'uniqueItems' => true, 'items' => array( 'type' => 'string', 'enum' => array( 'email' ) ) ),
				'controller_scope'      => array( 'type' => 'string', 'enum' => array( self::CONTACT_CONSENT_CONTROLLER_SCOPE ) ),
				'recipient_scope'       => array( 'type' => 'string', 'enum' => $this->contact_recipient_scopes() ),
				'contact_target'        => array( 'type' => 'string', 'enum' => array( 'account_email' ) ),
			),
			'validate_callback'    => 'rest_validate_request_arg',
		);
	}

	private function contact_recipient_scopes() {
		return array( 'tra_vel_assistance_team' );
	}

	/**
	 * Enforce explicit, purpose-limited contact consent without retaining a raw
	 * email address or telephone number in the action event.
	 */
	private function normalize_contact_consent( $action, $params ) {
		$params      = is_array( $params ) ? $params : array();
		$has_consent = array_key_exists( 'contact_consent', $params );
		if ( 'authorize_contact' !== (string) $action ) {
			return $has_consent
				? new WP_Error( 'tra_vel_assisted_proposal_contact_consent_unexpected', 'Contact consent is accepted only with authorize_contact.', array( 'status' => 400 ) )
				: null;
		}
		if ( ! $has_consent || ! is_array( $params['contact_consent'] ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_required', 'Explicit contact consent is required before contact can be authorized.', array( 'status' => 400 ) );
		}
		$consent = $params['contact_consent'];
		$required = array( 'contract_version', 'consent_version', 'affirmed', 'purpose', 'channels', 'controller_scope', 'recipient_scope', 'contact_target' );
		if ( array_diff( $required, array_keys( $consent ) ) || array_diff( array_keys( $consent ), $required ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_shape_invalid', 'Contact consent must use the exact supported fields.', array( 'status' => 400 ) );
		}
		$channels = $consent['channels'];
		if ( ! is_array( $channels ) || array( 'email' ) !== array_values( $channels ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_invalid', 'Contact consent channels are invalid.', array( 'status' => 400 ) );
		}
		if ( self::CONTACT_CONSENT_CONTRACT_VERSION !== ( $consent['contract_version'] ?? null ) || self::CONTACT_CONSENT_VERSION !== ( $consent['consent_version'] ?? null ) || true !== ( $consent['affirmed'] ?? null ) || self::CONTACT_CONSENT_PURPOSE !== ( $consent['purpose'] ?? null ) || self::CONTACT_CONSENT_CONTROLLER_SCOPE !== ( $consent['controller_scope'] ?? null ) || ! in_array( $consent['recipient_scope'] ?? null, $this->contact_recipient_scopes(), true ) || 'account_email' !== ( $consent['contact_target'] ?? null ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_consent_invalid', 'Contact consent does not match the supported privacy notice.', array( 'status' => 400 ) );
		}
		return array(
			'contract_version'      => self::CONTACT_CONSENT_CONTRACT_VERSION,
			'consent_version'       => self::CONTACT_CONSENT_VERSION,
			'affirmed'              => true,
			'purpose'               => self::CONTACT_CONSENT_PURPOSE,
			'channels'              => array( 'email' ),
			'controller_scope'      => self::CONTACT_CONSENT_CONTROLLER_SCOPE,
			'recipient_scope'       => (string) $consent['recipient_scope'],
			'contact_target'        => 'account_email',
		);
	}

	/**
	 * Verify the live server-side destination only for a new contact mutation.
	 * Exact committed retries resolve their receipt before this mutable check.
	 */
	private function validate_contact_target( $contact_consent, $principal ) {
		if ( ! is_array( $contact_consent ) ) {
			return true;
		}
		$user_id = absint( $principal['user_id'] ?? 0 );
		$user    = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$email   = is_object( $user ) ? (string) ( $user->user_email ?? '' ) : '';
		if ( $user_id < 1 || ! function_exists( 'is_email' ) || ! is_email( $email ) ) {
			return new WP_Error( 'tra_vel_assisted_proposal_contact_target_unverified', 'Authorize contact requires a signed-in account with a valid server-side email target.', array( 'status' => 409 ) );
		}
		return true;
	}

	private function mutation_args( $minimum_version ) {
		return array(
			'expected_version' => array( 'type' => 'integer', 'required' => true, 'minimum' => (int) $minimum_version, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'idempotency_key'  => array( 'type' => 'string', 'required' => true, 'minLength' => 16, 'maxLength' => 100, 'pattern' => '^[A-Za-z0-9._:-]+$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	private function composition_mutation_args( $minimum_version ) {
		return array_merge(
			$this->mutation_args( $minimum_version ),
			$this->composition_context_args()
		);
	}

	private function composition_context_args() {
		return array(
			'expected_case_version'  => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'expected_case_revision' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' ),
			'expected_request_digest'=> array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{64}$', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' ),
		);
	}

	private function uuid_arg() {
		return array( 'type' => 'string', 'required' => true, 'format' => 'uuid', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function per_page_arg() {
		return array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint', 'validate_callback' => 'rest_validate_request_arg' );
	}

	private function is_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	private function is_sha256( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}

	private function safe_equals( $known, $candidate ) {
		return is_string( $known ) && is_string( $candidate ) && strlen( $known ) === strlen( $candidate ) && hash_equals( $known, $candidate );
	}
}
