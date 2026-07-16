<?php
/**
 * Fictional, non-purchasable insurance quote fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Demo_Insurance_Quote_Adapter implements Tra_Vel_V2_Insurance_Quote_Adapter {
	public function get_id() {
		return 'curated_demo_insurance';
	}

	public function is_configured() {
		return true;
	}

	public function get_mode() {
		return 'demo';
	}

	public function get_cache_version() {
		return TRA_VEL_V2_VERSION . '-insurance-contract-1';
	}

	public function quote( $query ) {
		$path = TRA_VEL_V2_PATH . '/assets/data/insurance-quote-demo.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_demo_insurance_missing', 'Demo insurance comparison is unavailable.', array( 'status' => 503 ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['plans'] ) ) {
			return new WP_Error( 'tra_vel_demo_insurance_invalid', 'Demo insurance comparison is invalid.', array( 'status' => 500 ) );
		}
		if ( (string) $query['destination'] !== $data['destination']['id'] ) {
			return new WP_Error( 'tra_vel_demo_insurance_destination_unsupported', 'The demo insurance adapter supports Europe only.', array( 'status' => 422 ) );
		}

		$days           = max( 1, (int) $query['trip_days'] );
		$traveler_units = max( 1, (float) $query['adults'] + ( (float) $query['children'] * 0.65 ) );
		$travelers      = max( 1, (int) $query['adults'] + (int) $query['children'] );
		$age_factor     = $this->age_factor( (int) $query['oldest_age'] );
		$requested      = array( 'baggage', 'cancellation', 'adventure_sports', 'winter_sports', 'electronics' );

		foreach ( $data['plans'] as &$plan ) {
			$base_cost = round( (float) $plan['daily_base'] * $days * $traveler_units * $age_factor, 2 );
			$addons    = array();
			$addon_cost = 0.0;
			foreach ( $requested as $addon ) {
				if ( empty( $query[ $addon ] ) ) {
					continue;
				}
				$included = $this->is_included( $plan, $addon );
				$cost     = $included ? 0.0 : round( (float) $plan['addon_rates'][ $addon ] * $days * $traveler_units * $age_factor, 2 );
				$addon_cost += $cost;
				$addons[] = array( 'id' => $addon, 'included' => $included, 'estimated_cost' => $cost );
			}
			$total = round( $base_cost + $addon_cost, 2 );
			$plan['score'] = $this->adjust_score( (int) $plan['base_score'], $plan['id'], $query['trip_type'] );
			$plan['pricing'] = array(
				'base'               => $base_cost,
				'addons'             => round( $addon_cost, 2 ),
				'total_trip'         => $total,
				'daily_party'        => round( $total / $days, 2 ),
				'per_person'         => round( $total / $travelers, 2 ),
				'age_factor'         => $age_factor,
				'price_is_estimate'  => true,
			);
			$plan['requested_addons'] = $addons;
			$plan['eligibility'] = array(
				'status' => ( $query['medical_condition'] || $query['pregnancy'] ) ? 'medical_assessment_required' : 'preliminary_demo',
				'message' => ( $query['medical_condition'] || $query['pregnancy'] )
					? 'המחיר והכיסוי אינם תקפים ללא שאלון רפואי וחיתום של המבטח.'
					: 'הדגמה בלבד; הצעה אמיתית דורשת פרטי נוסעים, הצהרות ותנאי מבטח.',
			);
		}
		unset( $plan );
		$data['query'] = $query;
		$data['calculation'] = array( 'trip_days' => $days, 'travelers' => $travelers, 'traveler_units' => $traveler_units, 'age_factor' => $age_factor );
		return $data;
	}

	private function age_factor( $age ) {
		if ( $age <= 60 ) {
			return 1.0;
		}
		if ( $age <= 70 ) {
			return 1.6;
		}
		if ( $age <= 80 ) {
			return 2.4;
		}
		return 3.5;
	}

	private function is_included( $plan, $addon ) {
		$coverage_keys = array(
			'baggage'     => 'baggage_limit',
			'cancellation' => 'cancellation_limit',
			'electronics' => 'electronics_limit',
		);
		return isset( $coverage_keys[ $addon ] ) && ! empty( $plan['coverage'][ $coverage_keys[ $addon ] ] );
	}

	private function adjust_score( $base, $plan_id, $trip_type ) {
		$adjustments = array(
			'family'     => array( 'demo-essential' => -2, 'demo-assisted' => 3, 'demo-extended' => 2 ),
			'multi_city' => array( 'demo-essential' => -2, 'demo-assisted' => 2, 'demo-extended' => 3 ),
			'adventure'  => array( 'demo-essential' => -4, 'demo-assisted' => 2, 'demo-extended' => 4 ),
			'winter'     => array( 'demo-essential' => -5, 'demo-assisted' => 2, 'demo-extended' => 5 ),
			'business'   => array( 'demo-essential' => -1, 'demo-assisted' => 3, 'demo-extended' => 2 ),
		);
		$change = isset( $adjustments[ $trip_type ][ $plan_id ] ) ? $adjustments[ $trip_type ][ $plan_id ] : 0;
		return max( 0, min( 100, $base + $change ) );
	}
}
