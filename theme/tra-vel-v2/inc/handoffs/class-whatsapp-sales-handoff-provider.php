<?php
/**
 * Owned assisted-sales handoff through the public Tra-Vel WhatsApp channel.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * The exact dates line of the concierge message, or an empty string.
 *
 * Theme 1.40.0 extracts the three context lines that other surfaces need to
 * reference verbatim into named helpers. The builder closure below and any
 * server-rendered data attribute that quotes one of these lines both call the
 * same function, so the quoted line and the built message can never drift
 * apart byte by byte.
 *
 * @param string $depart_date YYYY-MM-DD, or empty.
 * @param string $return_date YYYY-MM-DD, or empty.
 * @return string Composed line, or an empty string when no date exists.
 */
function tra_vel_v2_whatsapp_dates_line( $depart_date, $return_date ) {
	$dates = array_values( array_filter( array( (string) $depart_date, (string) $return_date ) ) );
	if ( ! $dates ) {
		return '';
	}

	/* translators: %s: trip dates. */
	return sprintf( __( 'תאריכים: %s', 'tra-vel-v2' ), implode( ' עד ', $dates ) );
}

/**
 * The exact travelers line of the concierge message.
 *
 * @param int $travelers Traveler count.
 * @return string
 */
function tra_vel_v2_whatsapp_travelers_line( $travelers ) {
	/* translators: %d: number of travelers. */
	return sprintf( __( 'נוסעים: %d', 'tra-vel-v2' ), (int) $travelers );
}

/**
 * The exact requested add-ons line of the concierge message, or ''.
 *
 * @param array<int,string> $addons Add-on labels, already sanitized.
 * @return string Composed line, or an empty string when no add-on was chosen.
 */
function tra_vel_v2_whatsapp_addons_line( $addons ) {
	$addons = array_values( array_filter( array_map( 'strval', (array) $addons ) ) );
	if ( ! $addons ) {
		return '';
	}

	/* translators: %s: requested add-on list. */
	return sprintf( __( 'תוספות מבוקשות: %s', 'tra-vel-v2' ), implode( ', ', $addons ) );
}

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
			if ( ! empty( $context['offer_id'] ) ) {
				$lines[] = sprintf( __( 'מספר בקשה: %s', 'tra-vel-v2' ), sanitize_text_field( $context['offer_id'] ) );
			}
			$route = array_values( array_filter( array( $context['origin'], $context['destination'] ) ) );
			if ( $route ) {
				$lines[] = sprintf( __( 'מסלול: %s', 'tra-vel-v2' ), implode( ' ← ', $route ) );
			}
			$dates_line = tra_vel_v2_whatsapp_dates_line( $context['depart_date'], $context['return_date'] );
			if ( '' !== $dates_line ) {
				$lines[] = $dates_line;
			}
			$lines[] = tra_vel_v2_whatsapp_travelers_line( (int) $context['travelers'] );
			// Theme 1.40.0: the assembled proposal panel may carry the add-on
			// labels a traveler actually ticked. These are our own registry
			// labels, never prices, so the no-sample-prices rule above holds.
			// Callers that never send add-ons, including the REST handoff
			// controller, produce a byte-identical message to before.
			$addons_line = tra_vel_v2_whatsapp_addons_line( isset( $context['addons'] ) ? $context['addons'] : array() );
			if ( '' !== $addons_line ) {
				$lines[] = $addons_line;
			}
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
