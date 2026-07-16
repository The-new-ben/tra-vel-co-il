<?php
/**
 * Coherent, non-bookable total-trip package fallback.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_V2_Demo_Trip_Package_Adapter implements Tra_Vel_V2_Trip_Package_Adapter {
	public function get_id() {
		return 'curated_demo_packages';
	}

	public function is_configured() {
		return true;
	}

	public function get_mode() {
		return 'demo';
	}

	public function get_cache_version() {
		return TRA_VEL_V2_VERSION . '-package-contract-1';
	}

	public function search( $query ) {
		$path = TRA_VEL_V2_PATH . '/assets/data/trip-package-demo.json';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tra_vel_demo_packages_missing', 'Demo package inventory is unavailable.', array( 'status' => 503 ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['packages'] ) || empty( $data['insurance_catalog'] ) ) {
			return new WP_Error( 'tra_vel_demo_packages_invalid', 'Demo package inventory is invalid.', array( 'status' => 500 ) );
		}
		if ( strtoupper( (string) $query['origin'] ) !== $data['origin']['code'] || strtoupper( (string) $query['destination'] ) !== $data['destination']['code'] ) {
			return new WP_Error( 'tra_vel_demo_package_route_unsupported', 'The demo package adapter supports TLV to Budapest only.', array( 'status' => 422 ) );
		}

		$nights     = max( 1, (int) $query['nights'] );
		$trip_days  = max( 1, (int) $query['trip_days'] );
		$adults     = max( 1, (int) $query['adults'] );
		$children   = max( 0, (int) $query['children'] );
		$rooms      = max( 1, (int) $query['rooms'] );
		$travelers  = $adults + $children;
		$insurance_catalog = $data['insurance_catalog'];

		foreach ( $data['packages'] as &$package ) {
			$flight = round( ( (float) $package['flight']['adult_price'] * $adults ) + ( (float) $package['flight']['child_price'] * $children ), 2 );
			$baggage = ( $query['baggage'] && empty( $package['flight']['baggage_included'] ) )
				? round( (float) $package['flight']['baggage_addon'] * $travelers, 2 )
				: 0.0;
			$stay = round(
				( (float) $package['stay']['nightly'] * $nights * $rooms ) +
				( (float) $package['stay']['taxes'] * $rooms ) +
				( (float) $package['stay']['fees'] * $rooms ),
				2
			);
			$breakfast = ( $query['breakfast'] && empty( $package['stay']['breakfast_included'] ) )
				? round( ( ( (float) $package['stay']['breakfast_daily_adult'] * $adults ) + ( (float) $package['stay']['breakfast_daily_child'] * $children ) ) * $nights, 2 )
				: 0.0;
			$insurance_key = 'auto' === $query['insurance_tier'] ? $package['default_insurance_tier'] : $query['insurance_tier'];
			$insurance      = isset( $insurance_catalog[ $insurance_key ] ) ? $insurance_catalog[ $insurance_key ] : $insurance_catalog['none'];
			$insurance_cost = round( ( ( (float) $insurance['daily_adult'] * $adults ) + ( (float) $insurance['daily_child'] * $children ) ) * $trip_days, 2 );
			$transfer       = $query['transfers']
				? round( (float) $package['transfer']['base'] + ( (float) $package['transfer']['per_traveler'] * $travelers ), 2 )
				: 0.0;
			$addons = round( $baggage + $breakfast, 2 );
			$total  = round( $flight + $stay + $insurance_cost + $transfer + $addons, 2 );

			$score = (int) $package['base_score'];
			if ( 'family' === $query['trip_style'] ) {
				$score += 'budapest-family-demo' === $package['id'] ? 10 : ( (int) $package['stay']['sleeps'] >= $travelers ? 2 : -12 );
			} elseif ( 'comfort' === $query['trip_style'] ) {
				$score += 'budapest-flex-demo' === $package['id'] ? 10 : (int) round( ( (int) $package['traits']['comfort'] - 80 ) / 5 );
			} elseif ( 'value' === $query['trip_style'] ) {
				$score += 'budapest-value-demo' === $package['id'] ? 18 : 0;
			} elseif ( 'romantic' === $query['trip_style'] ) {
				$score += in_array( $package['stay']['area_id'], array( 'district-v', 'buda-castle' ), true ) ? 4 : -1;
			}
			if ( $query['free_cancellation'] && empty( $package['stay']['free_cancellation'] ) ) {
				$score -= 20;
			}
			if ( $query['baggage'] && ! $package['flight']['baggage_included'] ) {
				$score -= 3;
			}

			$package['score'] = max( 0, min( 100, $score ) );
			$package['stay']['nights']       = $nights;
			$package['stay']['rooms']        = $rooms;
			$package['stay']['party_fits']   = (int) $package['stay']['sleeps'] * $rooms >= $travelers;
			$package['insurance'] = array(
				'id' => $insurance['id'], 'tier' => $insurance_key, 'name' => $insurance['name'], 'medical_limit' => $insurance['medical_limit'],
				'service' => $insurance['service'], 'price_is_estimate' => true, 'purchasable' => false,
			);
			$package['inclusions'] = array(
				'baggage' => (bool) $package['flight']['baggage_included'],
				'breakfast' => (bool) $package['stay']['breakfast_included'],
				'free_cancellation' => (bool) $package['stay']['free_cancellation'],
				'transfers_requested' => (bool) $query['transfers'],
				'insurance_in_calculation' => 'none' !== $insurance_key,
			);
			$package['pricing'] = array(
				'flight' => $flight, 'stay' => $stay, 'insurance' => $insurance_cost, 'transfers' => $transfer, 'baggage' => $baggage,
				'breakfast' => $breakfast, 'addons' => $addons, 'total_party' => $total, 'per_person' => round( $total / max( 1, $travelers ), 2 ),
				'currency' => 'USD', 'comparison_basis' => 'component_sum', 'bundle_discount_verified' => false, 'savings' => null, 'price_is_estimate' => true,
			);
			$package['selection'] = array(
				'trip_style' => $query['trip_style'], 'requested_baggage' => (bool) $query['baggage'], 'requested_breakfast' => (bool) $query['breakfast'],
				'requested_free_cancellation' => (bool) $query['free_cancellation'], 'requested_transfers' => (bool) $query['transfers'], 'insurance_tier' => $insurance_key,
			);
		}
		unset( $package );

		$data['search'] = $query;
		$data['trip']   = array( 'nights' => $nights, 'trip_days' => $trip_days, 'travelers' => $travelers, 'rooms' => $rooms );
		return $data;
	}
}
