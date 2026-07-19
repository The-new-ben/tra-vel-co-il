<?php
/**
 * Operator presentation for assisted quote cases.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Quote_Case_Admin {
	const PAGE_SLUG = 'tra-vel-quote-cases';

	/** @var string */
	private $page_hook = '';

	/**
	 * Register the admin-only hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the operator queue as a top-level admin screen.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->page_hook = (string) add_menu_page(
			__( 'Tra-Vel assisted quote cases', 'tra-vel-agent' ),
			__( 'Tra-Vel Quotes', 'tra-vel-agent' ),
			Tra_Vel_Quote_Case_Capabilities::VIEW_CASES,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-airplane',
			26
		);
	}

	/**
	 * Load the queue bundle only on its own admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->page_hook || $this->page_hook !== $hook_suffix ) {
			return;
		}

		if ( ! current_user_can( Tra_Vel_Quote_Case_Capabilities::VIEW_CASES ) ) {
			return;
		}
		if ( ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			return;
		}

		wp_enqueue_style(
			'tra-vel-quote-cases-admin',
			plugins_url( 'assets/admin/quote-cases.css', TRA_VEL_AGENT_FILE ),
			array(),
			TRA_VEL_AGENT_VERSION
		);

		wp_enqueue_script(
			'tra-vel-assisted-proposal-composer-admin',
			plugins_url( 'assets/admin/assisted-proposal-composer.js', TRA_VEL_AGENT_FILE ),
			array(),
			TRA_VEL_AGENT_VERSION,
			true
		);

		wp_enqueue_script(
			'tra-vel-quote-cases-admin',
			plugins_url( 'assets/admin/quote-cases.js', TRA_VEL_AGENT_FILE ),
			array( 'tra-vel-assisted-proposal-composer-admin' ),
			TRA_VEL_AGENT_VERSION,
			true
		);

		$proposal_ready = class_exists( 'Tra_Vel_Assisted_Proposal_Store' )
			&& method_exists( 'Tra_Vel_Assisted_Proposal_Store', 'is_ready' )
			&& Tra_Vel_Assisted_Proposal_Store::is_ready();
		$source_ttls = array();
		foreach ( Tra_Vel_Assisted_Proposal_Policy::source_types() as $source_type ) {
			$source_ttls[ $source_type ] = (int) floor( Tra_Vel_Assisted_Proposal_Policy::source_max_ttl_seconds( $source_type ) / MINUTE_IN_SECONDS );
		}
		$public_source_providers = array();
		foreach ( Tra_Vel_Assisted_Proposal_Policy::public_source_provider_registry() as $provider_code => $definition ) {
			if ( ! is_string( $provider_code ) || ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $provider_code ) || ! is_array( $definition ) ) {
				continue;
			}
			$public_source_providers[ $provider_code ] = array(
				'sourceTypes'   => array_values( array_intersect( Tra_Vel_Assisted_Proposal_Policy::source_types(), (array) ( $definition['source_types'] ?? array() ) ) ),
				'relationships' => array_values( array_intersect( Tra_Vel_Assisted_Proposal_Policy::source_relationships(), (array) ( $definition['relationships'] ?? array() ) ) ),
			);
		}

		wp_localize_script(
			'tra-vel-quote-cases-admin',
			'TraVelQuoteCasesAdmin',
			array(
				'restUrl'                => esc_url_raw( rest_url( 'tra-vel-agent/v1/operator/quote-cases' ) ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'statuses'               => $this->statuses(),
				'canManage'              => current_user_can( Tra_Vel_Quote_Case_Capabilities::MANAGE_CASES ),
				'canPublish'             => $proposal_ready && current_user_can( Tra_Vel_Quote_Case_Capabilities::PUBLISH_PROPOSALS ),
				'canOverrideAssignment'  => current_user_can( 'manage_options' ),
				'currentUserId'          => get_current_user_id(),
				'proposalReady'          => $proposal_ready,
				'proposal'               => array(
					'positions'           => Tra_Vel_Assisted_Proposal_Policy::positions(),
					'sourceTypes'         => Tra_Vel_Assisted_Proposal_Policy::source_types(),
					'sourceRelationships' => Tra_Vel_Assisted_Proposal_Policy::source_relationships(),
					'categories'          => Tra_Vel_Assisted_Proposal_Policy::categories(),
					'currencies'          => Tra_Vel_Assisted_Proposal_Policy::currencies(),
					'sourceTtlMinutes'    => $source_ttls,
					'publicSourceProviders' => $public_source_providers,
					'disclosure'          => Tra_Vel_Assisted_Proposal_Policy::FINAL_QUOTE_DISCLOSURE,
				),
				'proposalStrings'        => array(
					'open'                 => __( 'Open proposals', 'tra-vel-agent' ),
					'close'                => __( 'Close proposal workspace', 'tra-vel-agent' ),
					'heading'              => __( 'Build a sourced trip proposal', 'tra-vel-agent' ),
					'intro'                => __( 'Prepare one editable 360-degree option. Only facts tied to the evidence below can be shown as priced.', 'tra-vel-agent' ),
					'publish'              => __( 'Publish non-binding proposal', 'tra-vel-agent' ),
					'publishing'           => __( 'Publishing...', 'tra-vel-agent' ),
					'published'            => __( 'The sourced proposal is now available in the traveler workspace.', 'tra-vel-agent' ),
					'withdraw'             => __( 'Withdraw proposal', 'tra-vel-agent' ),
					'loading'              => __( 'Loading proposal history...', 'tra-vel-agent' ),
					'noProposals'          => __( 'No proposal has been published for this case yet.', 'tra-vel-agent' ),
					'assignmentRequired'   => __( 'Claim this case before publishing a proposal.', 'tra-vel-agent' ),
					'storeUnavailable'     => __( 'Proposal tools are unavailable until their protected storage passes readiness checks.', 'tra-vel-agent' ),
					'finalReview'          => __( 'Final review', 'tra-vel-agent' ),
					'priceBoundary'        => __( 'Final price, availability, and terms are provided only after revalidation in a personal quote.', 'tra-vel-agent' ),
				),
			)
		);
	}

	/**
	 * Render the progressively enhanced admin shell.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( Tra_Vel_Quote_Case_Capabilities::VIEW_CASES ) ) {
			wp_die( esc_html__( 'You do not have permission to view assisted quote cases.', 'tra-vel-agent' ) );
		}
		if ( ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Tra-Vel quote cases', 'tra-vel-agent' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'The assisted quote queue is unavailable until its protected database schema passes the readiness check.', 'tra-vel-agent' ) . '</p></div></div>';
			return;
		}
		?>
		<div class="wrap tra-vel-quotes-admin">
			<div class="tra-vel-quotes-admin__heading">
				<div>
					<p class="tra-vel-quotes-admin__eyebrow"><?php echo esc_html__( 'Assisted travel operations', 'tra-vel-agent' ); ?></p>
					<h1><?php echo esc_html__( 'Tra-Vel quote cases', 'tra-vel-agent' ); ?></h1>
					<p class="tra-vel-quotes-admin__intro"><?php echo esc_html__( 'Review structured traveler requests, record each real status change, and prepare the next human-assisted step.', 'tra-vel-agent' ); ?></p>
				</div>
				<div class="tra-vel-quotes-admin__trust">
					<span aria-hidden="true"></span>
					<?php echo esc_html__( 'Operator queue', 'tra-vel-agent' ); ?>
				</div>
			</div>

			<div id="tra-vel-quote-cases-app" class="tra-vel-quote-cases-app">
				<p class="tra-vel-quote-cases-app__boot" role="status"><?php echo esc_html__( 'Loading quote cases...', 'tra-vel-agent' ); ?></p>
				<noscript><p class="notice notice-warning"><?php echo esc_html__( 'JavaScript is required to use the operator queue.', 'tra-vel-agent' ); ?></p></noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Return the presentation labels and the legal next states.
	 *
	 * The server remains authoritative. This data only prevents the UI from
	 * presenting impossible actions and can be filtered for translated labels.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function statuses() {
		$statuses = array(
			'queued'               => array(
				'label' => __( 'Queued', 'tra-vel-agent' ),
				'next'  => array( 'in_review', 'needs_information', 'closed_no_quote' ),
			),
			'in_review'            => array(
				'label' => __( 'In review', 'tra-vel-agent' ),
				'next'  => array( 'needs_information', 'ready_for_assistance', 'closed_no_quote' ),
			),
			'needs_information'    => array(
				'label' => __( 'Needs information', 'tra-vel-agent' ),
				'next'  => array( 'queued', 'in_review', 'closed_no_quote' ),
			),
			'ready_for_assistance' => array(
				'label' => __( 'Ready for assistance', 'tra-vel-agent' ),
				'next'  => array( 'in_review', 'needs_information', 'closed_no_quote' ),
			),
			'closed_no_quote'      => array(
				'label' => __( 'Closed without assistance', 'tra-vel-agent' ),
				'next'  => array(),
			),
			'cancelled'            => array(
				'label' => __( 'Cancelled', 'tra-vel-agent' ),
				'next'  => array(),
			),
			'expired'              => array(
				'label' => __( 'Expired', 'tra-vel-agent' ),
				'next'  => array(),
			),
		);

		/**
		 * Filters admin presentation metadata for quote-case statuses.
		 *
		 * @param array<string,array<string,mixed>> $statuses Status labels and next states.
		 */
		return (array) apply_filters( 'tra_vel_quote_case_admin_statuses', $statuses );
	}
}
