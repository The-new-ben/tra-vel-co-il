<?php
/**
 * The assembled trip proposal panel (theme 1.40.0).
 *
 * One click on a priced destination opens a finished, editable proposal on the
 * same page. Everything the panel can ever show is computed here on the
 * server, from the exact same cached observation set the decision card and the
 * found-price surfaces already trust: the up-to-three real flight records from
 * tra_vel_v2_get_destination_options(), the live-gated affiliate registry from
 * inc/affiliate-programs.php, and the owned WhatsApp concierge handoff built
 * by tra_vel_v2_whatsapp_assisted_handoff_url(). The browser never issues a
 * request to assemble it; the client script only reveals what is already in
 * the page, multiplies fares the server printed, switches between records the
 * server rendered, and substitutes message lines the server authored.
 *
 * Money rules are inherited unchanged: every number is a real observed fare or
 * absent, totals are one fare times one traveler count, unpriced add-ons are
 * never summed into anything, and the markup carries no Offer, Product or
 * AggregateOffer vocabulary.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * The add-on tray rows, resolved against the live affiliate registry.
 *
 * Three verticals, fixed: travel insurance, eSIM, and the airport transfer.
 * Each row always exists so a traveler can carry the wish into the WhatsApp
 * handoff, but the supplier link inside a row exists only when the registry
 * reports that program enabled with a validated https URL. A disabled program
 * renders no link at all; nothing here ever fabricates one.
 *
 * @return array<int,array<string,string>> Rows of key, label, cta_label, url.
 */
function tra_vel_v2_proposal_addons() {
	// Theme 1.41.0 live-check reveal: the one verdict sentence the opening
	// sequence may type for an add-on, authored here and only for a program
	// the registry reports live. A disabled program gets no sentence at all,
	// so the client can never claim an add-on we cannot actually link.
	$verdict_lines = array(
		'insurance' => __( 'השגנו לך ביטוח.', 'tra-vel-v2' ),
		'esim'      => __( 'השגנו לך eSIM.', 'tra-vel-v2' ),
		'transfer'  => __( 'השגנו לך העברה.', 'tra-vel-v2' ),
	);

	$rows = array();
	foreach ( array(
		'insurance' => array( 'ekta', __( 'ביטוח נסיעות', 'tra-vel-v2' ) ),
		'esim'      => array( 'esim_generic', 'eSIM' ),
		'transfer'  => array( 'transfers_generic', __( 'העברה משדה התעופה', 'tra-vel-v2' ) ),
	) as $key => $row ) {
		$link   = function_exists( 'tra_vel_v2_affiliate_program_link' ) ? tra_vel_v2_affiliate_program_link( $row[0] ) : array();
		$is_live = is_array( $link ) && ! empty( $link['enabled'] ) && ! empty( $link['url'] );
		$rows[] = array(
			'key'          => $key,
			'label'        => $row[1],
			'cta_label'    => $is_live && '' !== trim( (string) $link['label'] ) ? (string) $link['label'] : '',
			'url'          => $is_live ? (string) $link['url'] : '',
			'verdict_line' => $is_live && isset( $verdict_lines[ $key ] ) ? $verdict_lines[ $key ] : '',
		);
	}

	return $rows;
}

/**
 * Everything the proposal panel needs, or null when no real record exists.
 *
 * @param string $map_state Destination map state.
 * @return array<string,mixed>|null View model, or null without a priced record.
 */
function tra_vel_v2_proposal_view( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );
	if ( '' === $map_state || ! function_exists( 'tra_vel_v2_get_destination_options' ) ) {
		return null;
	}

	$options = tra_vel_v2_get_destination_options( $map_state );
	if ( ! $options ) {
		return null;
	}

	$destinations = function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ? tra_vel_v2_seo_opportunity_destinations() : array();
	$city         = isset( $destinations[ $map_state ]['name'] ) ? (string) $destinations[ $map_state ]['name'] : '';
	if ( '' === $city ) {
		return null;
	}

	$travelers = TRA_VEL_V2_DECISION_CARD_DEFAULT_TRAVELERS;
	$labels    = tra_vel_v2_decision_card_tier_labels();
	$symbol    = isset( $options[0]['currency_symbol'] ) && is_string( $options[0]['currency_symbol'] ) && '' !== trim( $options[0]['currency_symbol'] )
		? trim( $options[0]['currency_symbol'] )
		: tra_vel_v2_currency_symbol( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY );

	$tiers = array();
	foreach ( $options as $option ) {
		$unit = isset( $option['price'] ) ? (int) $option['price'] : 0;
		$tier = isset( $option['tier'] ) ? (string) $option['tier'] : '';
		if ( $unit <= 0 || ! isset( $labels[ $tier ] ) || empty( $option['deep_link'] ) ) {
			continue;
		}
		$tiers[] = array(
			'tier'          => $tier,
			'label'         => $labels[ $tier ],
			'unit'          => $unit,
			'unit_label'    => tra_vel_v2_format_found_price( array( 'price' => $unit, 'currency_symbol' => $symbol ) ),
			'total_label'   => tra_vel_v2_format_found_price( array( 'price' => $unit * $travelers, 'currency_symbol' => $symbol ) ),
			'airline'       => isset( $option['airline'] ) ? (string) $option['airline'] : '',
			'stops_label'   => tra_vel_v2_decision_card_stops_label( isset( $option['transfers'] ) ? $option['transfers'] : 0 ),
			'dates_label'   => tra_vel_v2_decision_card_dates_label( $option ),
			'deep_link'     => (string) $option['deep_link'],
			'wa_dates_line' => function_exists( 'tra_vel_v2_whatsapp_dates_line' )
				? tra_vel_v2_whatsapp_dates_line(
					isset( $option['departure_date'] ) ? (string) $option['departure_date'] : '',
					isset( $option['return_date'] ) ? (string) $option['return_date'] : ''
				)
				: '',
		);
	}

	if ( ! $tiers ) {
		return null;
	}

	// The two honest exits are built here, on the server, through the exact
	// same context builder the assisted handoff already uses. The client only
	// ever substitutes whole lines this same file authored (traveler count,
	// tier dates, ticked add-ons), so every byte of the message a traveler
	// sends originates in tra_vel_v2_whatsapp_* code, never in JavaScript.
	$addons       = tra_vel_v2_proposal_addons();
	$addon_labels = array();
	foreach ( $addons as $addon ) {
		$addon_labels[] = $addon['label'];
	}
	$wa_context = array(
		'destination' => $city,
		'origin'      => __( 'תל אביב', 'tra-vel-v2' ),
		'depart_date' => isset( $options[0]['departure_date'] ) ? (string) $options[0]['departure_date'] : '',
		'return_date' => isset( $options[0]['return_date'] ) ? (string) $options[0]['return_date'] : '',
		'travelers'   => $travelers,
	);
	$whatsapp     = function_exists( 'tra_vel_v2_whatsapp_assisted_handoff_url' )
		? tra_vel_v2_whatsapp_assisted_handoff_url( 'flight', $wa_context )
		: '';
	$whatsapp_all = '' !== $whatsapp && $addon_labels
		? tra_vel_v2_whatsapp_assisted_handoff_url( 'flight', array_merge( $wa_context, array( 'addons' => $addon_labels ) ) )
		: '';

	$wa_travelers_lines = array();
	if ( function_exists( 'tra_vel_v2_whatsapp_travelers_line' ) ) {
		for ( $count = TRA_VEL_V2_DECISION_CARD_MIN_TRAVELERS; $count <= TRA_VEL_V2_DECISION_CARD_MAX_TRAVELERS; $count++ ) {
			$wa_travelers_lines[] = tra_vel_v2_whatsapp_travelers_line( $count );
		}
	}

	/* translators: %s: formatted flights-only total for the whole party. */
	$total_template = __( 'סה"כ טיסות: %s לכל הנוסעים. תוספות נסגרות ומתומחרות אצל הספקים.', 'tra-vel-v2' );

	// Theme 1.41.0 live-check reveal: every sentence the opening sequence can
	// ever type is authored here, on the server, with the city already baked
	// in. The client only replays these bytes character by character and, in
	// the verdict, substitutes the traveler count the same way the pinned
	// total template already substitutes its amount. No sentence exists for
	// data this page does not really hold.
	$prefixed_city = tra_vel_v2_decision_card_prefixed_city( $city );

	return array(
		'state'              => $map_state,
		'city'               => $city,
		/* translators: %s: destination city, spelled for the ל prefix. */
		'title'              => sprintf( __( 'ההצעה שלכם ל%s', 'tra-vel-v2' ), $prefixed_city ),
		'trigger_label'      => __( 'הצעה מלאה בקליק', 'tra-vel-v2' ),
		'travelers'          => $travelers,
		'min_travelers'      => TRA_VEL_V2_DECISION_CARD_MIN_TRAVELERS,
		'max_travelers'      => TRA_VEL_V2_DECISION_CARD_MAX_TRAVELERS,
		'symbol'             => $symbol,
		'party_label'        => tra_vel_v2_decision_card_party_label( $travelers ),
		'party_one'          => tra_vel_v2_decision_card_party_label( 1 ),
		/* translators: %s: number of travelers. */
		'party_many'         => __( 'לכל %s הנוסעים', 'tra-vel-v2' ),
		'tiers'              => $tiers,
		'addons'             => $addons,
		'addon_note'         => __( 'נסגר אצל הספק', 'tra-vel-v2' ),
		'addons_heading'     => __( 'תוספות לחופשה', 'tra-vel-v2' ),
		'tiers_heading'      => __( 'בחירת טיסה', 'tra-vel-v2' ),
		'total_template'     => $total_template,
		'total_line'         => sprintf( $total_template, tra_vel_v2_format_found_price( array( 'price' => (int) $tiers[0]['unit'] * $travelers, 'currency_symbol' => $symbol ) ) ),
		'book_label'         => __( 'הזמינו את הטיסה עכשיו', 'tra-vel-v2' ),
		'whatsapp_label'     => __( 'סגרו לי את הכול בוואטסאפ', 'tra-vel-v2' ),
		'close_label'        => __( 'סגירת ההצעה', 'tra-vel-v2' ),
		'travelers_label'    => __( 'נוסעים', 'tra-vel-v2' ),
		'step_down_label'    => __( 'נוסע אחד פחות', 'tra-vel-v2' ),
		'step_up_label'      => __( 'נוסע אחד יותר', 'tra-vel-v2' ),
		'airline_label'      => __( 'חברת תעופה', 'tra-vel-v2' ),
		'fill_lines'         => array(
			__( 'טיסה: מחיר שנמצא בחיפושים אחרונים', 'tra-vel-v2' ),
			__( 'תוספות: נסגרות ומתומחרות אצל הספקים', 'tra-vel-v2' ),
			__( 'ההצעה מוכנה. הכול ניתן לעריכה.', 'tra-vel-v2' ),
		),
		'whatsapp'           => $whatsapp,
		'whatsapp_all'       => $whatsapp_all,
		'wa_addons_line'     => function_exists( 'tra_vel_v2_whatsapp_addons_line' ) ? tra_vel_v2_whatsapp_addons_line( $addon_labels ) : '',
		'wa_addons_template' => function_exists( 'tra_vel_v2_whatsapp_addons_line' ) ? tra_vel_v2_whatsapp_addons_line( array( '%s' ) ) : '',
		'wa_travelers_lines' => $wa_travelers_lines,
		'wa_dates_line'      => $tiers[0]['wa_dates_line'],
		/* translators: %s: destination city, spelled for the ל prefix. */
		'check_prices_line'  => sprintf( __( 'בודקים לך את המחירים הטובים ביותר ל%s...', 'tra-vel-v2' ), $prefixed_city ),
		'check_dates_line'   => __( 'בודקים מתי הכי שווה לטוס...', 'tra-vel-v2' ),
		// Theme 1.42.0 found-flight step: the sentence quotes the cheapest
		// real record on this very panel, the one the tiers open on, so the
		// claim is the fare printed right below it. No record, no sentence:
		// this whole view already returned null without one.
		/* translators: %s: formatted per traveler fare of the cheapest real record. */
		'flight_found_line'  => sprintf( __( 'נמצאה טיסה: מ-%s לנוסע, הלוך ושוב.', 'tra-vel-v2' ), $tiers[0]['unit_label'] ),
		'start_label'        => __( 'התחילו', 'tra-vel-v2' ),
		/* translators: %s: destination city, spelled for the ל prefix. */
		'verdict_one'        => sprintf( __( 'אתם טסים. נוסע אחד ל%s.', 'tra-vel-v2' ), $prefixed_city ),
		/* translators: 1: traveler count placeholder, 2: destination city, spelled for the ל prefix. */
		'verdict_many'       => sprintf( __( 'אתם טסים. %1$s נוסעים ל%2$s.', 'tra-vel-v2' ), '%s', $prefixed_city ),
		'scope_note'         => tra_vel_v2_found_price_scope_note(),
		'currency_note'      => tra_vel_v2_found_price_currency_note( isset( $options[0]['currency'] ) ? $options[0]['currency'] : null ),
		'freshness'          => tra_vel_v2_found_price_freshness( $options[0] ),
	);
}

/**
 * Render one trigger and one hidden proposal panel for a destination.
 *
 * Renders nothing at all when no real priced record exists, exactly like the
 * found-price block: silence is the only honest empty state. The trigger is
 * server-rendered hidden and only client script reveals it, so a visitor
 * without JavaScript never sees a control that cannot work.
 *
 * @param string              $map_state Destination map state.
 * @param array<string,mixed> $args      Optional: 'trigger' => bool (default true).
 * @return void
 */
function tra_vel_v2_render_proposal( $map_state, $args = array() ) {
	static $instance = 0;

	$view = tra_vel_v2_proposal_view( $map_state );
	if ( null === $view ) {
		return;
	}

	$instance++;
	$view['instance'] = $instance;
	$view['trigger']  = ! isset( $args['trigger'] ) || false !== $args['trigger'];

	get_template_part( 'template-parts/proposal-panel', null, $view );
}
