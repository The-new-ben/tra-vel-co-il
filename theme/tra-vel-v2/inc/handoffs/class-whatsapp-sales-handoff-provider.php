<?php
/**
 * Owned assisted-sales handoff through the public Tra-Vel WhatsApp channel.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the live Tra-Vel concierge as an owned conversion provider.
 *
 * This provider carries only trip-planning context. It deliberately excludes
 * sample prices, passport details, payment data and medical answers.
 *
 * @param array<int, array<string, mixed>> $providers Existing providers.
 * @return array<int, array<string, mixed>>
 */
function tra_vel_v2_register_whatsapp_sales_handoff( $providers ) {
	$phone = preg_replace( '/\D+/', '', (string) apply_filters( 'tra_vel_v2_sales_whatsapp_phone', '972525101555' ) );
	if ( ! preg_match( '/^\d{8,15}$/', $phone ) ) {
		return $providers;
	}

	$providers[] = array(
		'id'            => 'tra-vel-concierge',
		'label'         => 'Tra-Vel',
		'live'          => true,
		'sponsored'     => false,
		'relationship'  => 'owned',
		'verticals'     => array( 'flight', 'hotel', 'package', 'insurance', 'car', 'transfer', 'activity', 'esim' ),
		'allowed_hosts' => array( 'api.whatsapp.com' ),
		'disclosure'    => __( 'שיחה ישירה עם צוות Tra-Vel. המחיר, הזמינות והתנאים יאומתו לפני כל הזמנה.', 'tra-vel-v2' ),
		'build_url'     => static function ( $context ) use ( $phone ) {
			$vertical_labels = array(
				'flight'    => __( 'טיסה', 'tra-vel-v2' ),
				'hotel'     => __( 'מלון', 'tra-vel-v2' ),
				'package'   => __( 'טיסה ומלון', 'tra-vel-v2' ),
				'insurance' => __( 'ביטוח נסיעות', 'tra-vel-v2' ),
				'car'       => __( 'רכב', 'tra-vel-v2' ),
				'transfer'  => __( 'העברה', 'tra-vel-v2' ),
				'activity'  => __( 'פעילות', 'tra-vel-v2' ),
				'esim'      => 'eSIM',
			);
			$lines = array(
				__( 'שלום, אני רוצה הצעת מחיר מאומתת דרך Tra-Vel.', 'tra-vel-v2' ),
				sprintf( __( 'מה מחפשים: %s', 'tra-vel-v2' ), $vertical_labels[ $context['vertical'] ] ?? $context['vertical'] ),
			);
			$route = array_values( array_filter( array( $context['origin'], $context['destination'] ) ) );
			if ( $route ) {
				$lines[] = sprintf( __( 'מסלול: %s', 'tra-vel-v2' ), implode( ' ← ', $route ) );
			}
			$dates = array_values( array_filter( array( $context['depart_date'], $context['return_date'] ) ) );
			if ( $dates ) {
				$lines[] = sprintf( __( 'תאריכים: %s', 'tra-vel-v2' ), implode( ' עד ', $dates ) );
			}
			$lines[] = sprintf( __( 'נוסעים: %d', 'tra-vel-v2' ), (int) $context['travelers'] );
			if ( $context['budget'] > 0 ) {
				$lines[] = sprintf( __( 'תקציב מרבי: %s %s', 'tra-vel-v2' ), number_format_i18n( $context['budget'] ), $context['currency'] );
			}
			$lines[] = __( 'אשמח לבדוק מחיר נוכחי, זמינות, מה כלול ותנאי שינוי לפני הזמנה.', 'tra-vel-v2' );

			return 'https://api.whatsapp.com/send?' . http_build_query(
				array(
					'phone' => $phone,
					'text'  => implode( "\n", $lines ),
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		},
	);

	return $providers;
}
add_filter( 'tra_vel_v2_handoff_providers', 'tra_vel_v2_register_whatsapp_sales_handoff' );

