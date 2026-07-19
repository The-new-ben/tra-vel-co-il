<?php
/**
 * Capabilities and the restricted operator role for assisted quote cases.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Quote_Case_Capabilities {
	const VERSION = '1.2.0';

	const VERSION_OPTION = 'tra_vel_quote_case_capabilities_version';

	const ROLE = 'tra_vel_quote_operator';

	const VIEW_CASES = 'tra_vel_view_quote_cases';

	const MANAGE_CASES = 'tra_vel_manage_quote_cases';

	const ASSIGN_CASES = 'tra_vel_assign_quote_cases';

	const PUBLISH_PROPOSALS = 'tra_vel_publish_assisted_proposals';

	const INGEST_PROPOSALS = 'tra_vel_ingest_canonical_assisted_proposals';

	const DISPATCH_SUPPLIER_REQUESTS = 'tra_vel_dispatch_supplier_requests';

	/**
	 * Grant the administrator capabilities and create/update the operator role.
	 *
	 * Supplier dispatch is deliberately withheld from quote operators. It is a
	 * separate trust boundary for a later, explicitly connected supplier flow.
	 *
	 * @return void
	 */
	public static function install() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::all_capabilities() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		$operator = get_role( self::ROLE );
		if ( ! $operator ) {
			add_role(
				self::ROLE,
				__( 'Tra-Vel quote operator', 'tra-vel-agent' ),
				array( 'read' => true )
			);
			$operator = get_role( self::ROLE );
		}

		if ( $operator ) {
			foreach ( self::operator_capabilities() as $capability ) {
				$operator->add_cap( $capability );
			}

			// Enforce the boundary even when upgrading an existing role.
			$operator->remove_cap( self::INGEST_PROPOSALS );
			$operator->remove_cap( self::DISPATCH_SUPPLIER_REQUESTS );
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function maybe_install() {
		if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Remove only the role and capabilities owned by this module.
	 *
	 * @return void
	 */
	public static function uninstall() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::all_capabilities() as $capability ) {
				$administrator->remove_cap( $capability );
			}
		}

		remove_role( self::ROLE );
		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Capabilities reserved for administrators and explicitly trusted roles.
	 *
	 * @return string[]
	 */
	public static function all_capabilities() {
		return array(
			self::VIEW_CASES,
			self::MANAGE_CASES,
			self::ASSIGN_CASES,
			self::PUBLISH_PROPOSALS,
			self::INGEST_PROPOSALS,
			self::DISPATCH_SUPPLIER_REQUESTS,
		);
	}

	/**
	 * Capabilities assigned to the operational quote role.
	 *
	 * @return string[]
	 */
	public static function operator_capabilities() {
		return array(
			self::VIEW_CASES,
			self::MANAGE_CASES,
			self::ASSIGN_CASES,
			self::PUBLISH_PROPOSALS,
		);
	}
}
