<?php
/**
 * Cost answers: the one question a traveler actually types (theme 1.38.0).
 *
 * "כמה עולה טיסה ל..." is the highest intent Hebrew flight query there is, and
 * until now this site answered it everywhere except on a page built for it.
 * This file is that page's runtime. It answers with the live cheapest fare the
 * price engine already holds, in one extractable sentence, above everything
 * else.
 *
 * Three laws govern every number that leaves this file:
 *
 *   1. A number is either a real observation from inc/prices.php or it is not
 *      printed. There is no estimated hotel line, no daily budget, no average
 *      and no saving percentage anywhere in this file. A cost component we do
 *      not observe is printed as depending on the traveler's own choice, in
 *      words, with no digits at all.
 *   2. The observed price history speaks only through its own gate. The
 *      when-to-fly section renders real monthly minimums when
 *      tra_vel_v2_price_history_is_meaningful() allows it, and honest guidance
 *      with no trend claim when it does not. It never fabricates a season.
 *   3. Structured data mirrors visible copy or it is not emitted. The FAQ
 *      schema is parsed back out of the exact markup the page renders, through
 *      the same tra_vel_v2_visible_faq_items() extractor the guide system uses,
 *      so a FAQPage node can only ever repeat words a visitor can read. There
 *      is no Offer, Product or AggregateOffer vocabulary here: a fare a third
 *      party found is not our inventory.
 *
 * The indexability contract fails closed. A cost answer page is indexable only
 * when its destination resolves, a real price exists, the comparison table has
 * at least one real row and the visible FAQ carries the full five pairs.
 * Anything less still renders something honest and useful, and is noindexed.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_V2_COST_ANSWER_TEMPLATE', 'page-cost-answer.php' );
define( 'TRA_VEL_V2_COST_ANSWER_FAQ_ID', 'cost-answer-faq' );
define( 'TRA_VEL_V2_COST_ANSWER_FAQ_PAIRS', 5 );

/**
 * Whether the current request is a singular cost answer page.
 *
 * @return bool
 */
function tra_vel_v2_cost_answer_is_request() {
	return is_singular( 'page' ) && is_page_template( TRA_VEL_V2_COST_ANSWER_TEMPLATE );
}

/**
 * Resolve one destination map state from a cost answer page slug.
 *
 * The slug is the only input, and the destination table in
 * inc/seo-opportunities.php is the only authority. A slug such as
 * flight-cost-warsaw or warsaw-cost resolves to warsaw; a slug naming two
 * different destinations, or none, resolves to nothing at all. Guessing which
 * of two cities a page is about would be worse than showing the honest state.
 *
 * @param int $post_id Optional post ID.
 * @return string Destination map state, or an empty string.
 */
function tra_vel_v2_cost_answer_map_state( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	if ( ! $post_id || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return '';
	}

	$slug = sanitize_title( (string) get_post_field( 'post_name', $post_id ) );
	if ( '' === $slug ) {
		return '';
	}

	$tokens  = array_values( array_filter( explode( '-', $slug ) ) );
	$matched = array();
	foreach ( array_keys( tra_vel_v2_seo_opportunity_destinations() ) as $candidate ) {
		$candidate_tokens = array_values( array_filter( explode( '-', (string) $candidate ) ) );
		if ( $candidate_tokens && ! array_diff( $candidate_tokens, $tokens ) ) {
			$matched[] = (string) $candidate;
		}
	}

	return 1 === count( $matched ) ? $matched[0] : '';
}

/**
 * The Hebrew name of one calendar month number.
 *
 * @param int $month Month number, 1 to 12.
 * @return string Month name, or an empty string.
 */
function tra_vel_v2_cost_answer_month_name( $month ) {
	$months = array(
		1  => __( 'ינואר', 'tra-vel-v2' ),
		2  => __( 'פברואר', 'tra-vel-v2' ),
		3  => __( 'מרץ', 'tra-vel-v2' ),
		4  => __( 'אפריל', 'tra-vel-v2' ),
		5  => __( 'מאי', 'tra-vel-v2' ),
		6  => __( 'יוני', 'tra-vel-v2' ),
		7  => __( 'יולי', 'tra-vel-v2' ),
		8  => __( 'אוגוסט', 'tra-vel-v2' ),
		9  => __( 'ספטמבר', 'tra-vel-v2' ),
		10 => __( 'אוקטובר', 'tra-vel-v2' ),
		11 => __( 'נובמבר', 'tra-vel-v2' ),
		12 => __( 'דצמבר', 'tra-vel-v2' ),
	);
	$month = (int) $month;

	return isset( $months[ $month ] ) ? $months[ $month ] : '';
}

/**
 * One supplier calendar date as readable Hebrew.
 *
 * Formatted straight from the stored calendar string, exactly like the decision
 * card does. Routing it through a timestamp would let a site timezone move the
 * day the supplier actually published.
 *
 * @param string $date YYYY-MM-DD.
 * @return string Readable date, or an empty string.
 */
function tra_vel_v2_cost_answer_date_label( $date ) {
	$date = function_exists( 'tra_vel_v2_price_sanitize_date' ) ? tra_vel_v2_price_sanitize_date( $date ) : '';
	if ( '' === $date ) {
		return '';
	}

	$parts = explode( '-', $date );
	$month = tra_vel_v2_cost_answer_month_name( (int) $parts[1] );
	if ( '' === $month ) {
		return '';
	}

	// The year is printed as a plain four digit string on purpose: a thousands
	// separator would turn 2026 into a number nobody writes as a year.
	return sprintf(
		/* translators: 1: day of month, 2: Hebrew month name, 3: year. */
		__( '%1$s ב%2$s %3$s', 'tra-vel-v2' ),
		number_format_i18n( (int) $parts[2] ),
		$month,
		(string) (int) $parts[0]
	);
}

/**
 * The Hebrew label of one observed history month bucket.
 *
 * @param string $month YYYY-MM.
 * @return string Readable month, or an empty string.
 */
function tra_vel_v2_cost_answer_month_label( $month ) {
	if ( ! is_string( $month ) || ! preg_match( '/^(\d{4})-(\d{2})$/', $month, $parts ) ) {
		return '';
	}
	$name = tra_vel_v2_cost_answer_month_name( (int) $parts[2] );
	if ( '' === $name ) {
		return '';
	}

	/* translators: 1: Hebrew month name, 2: year. */
	return sprintf( __( '%1$s %2$s', 'tra-vel-v2' ), $name, (string) (int) $parts[1] );
}

/**
 * The route shape of one found fare, phrased for the answer sentence.
 *
 * @param int $transfers Number of stops on the found itinerary.
 * @return string
 */
function tra_vel_v2_cost_answer_route_shape( $transfers ) {
	$transfers = max( 0, (int) $transfers );
	if ( 0 === $transfers ) {
		return __( 'בטיסה ישירה', 'tra-vel-v2' );
	}
	if ( 1 === $transfers ) {
		return __( 'עם עצירה אחת', 'tra-vel-v2' );
	}

	/* translators: %s: number of stops. */
	return sprintf( __( 'עם %s עצירות', 'tra-vel-v2' ), number_format_i18n( $transfers ) );
}

/**
 * The one sentence this whole page exists to answer.
 *
 * Deliberately a single self contained clause: it names the origin, the
 * destination, the found price, the departure date and the route shape, so it
 * can be quoted on its own and still be true on its own.
 *
 * @param string              $prefixed_city Destination name ready to follow a one letter prefix.
 * @param array<string,mixed> $price         Found price payload.
 * @return string Answer sentence, or an empty string when we hold no price.
 */
function tra_vel_v2_cost_answer_sentence( $prefixed_city, $price ) {
	$prefixed_city = trim( (string) $prefixed_city );
	$amount        = function_exists( 'tra_vel_v2_format_found_price' ) ? tra_vel_v2_format_found_price( $price ) : '';
	$departure     = is_array( $price ) ? tra_vel_v2_cost_answer_date_label( isset( $price['departure_date'] ) ? $price['departure_date'] : '' ) : '';
	if ( '' === $prefixed_city || '' === $amount || '' === $departure ) {
		return '';
	}

	return sprintf(
		/* translators: 1: destination name, 2: found price, 3: departure date, 4: route shape. */
		__( 'טיסה הלוך ושוב מתל אביב ל%1$s נמצאה לאחרונה במחיר %2$s, ליציאה ב-%3$s, %4$s.', 'tra-vel-v2' ),
		$prefixed_city,
		$amount,
		$departure,
		tra_vel_v2_cost_answer_route_shape( isset( $price['transfers'] ) ? $price['transfers'] : 0 )
	);
}

/**
 * The cost components of this trip, split by what we actually observe.
 *
 * Exactly one row carries a number, because exactly one component has a real
 * observation behind it. Every other row is returned with an empty amount and
 * an explicit "depends on your choice" value. A template cannot accidentally
 * print a figure that does not exist, because there is no figure to print.
 *
 * @param array<string,mixed> $price Found price payload.
 * @return array<int,array{component:string,amount:string,known:bool,note:string}>
 */
function tra_vel_v2_cost_answer_breakdown_rows( $price ) {
	$depends = __( 'תלוי בבחירה שלכם', 'tra-vel-v2' );
	$amount  = function_exists( 'tra_vel_v2_format_found_price' ) ? tra_vel_v2_format_found_price( $price ) : '';
	$rows    = array();

	if ( '' !== $amount ) {
		$rows[] = array(
			'component' => __( 'טיסה הלוך ושוב מתל אביב, לנוסע', 'tra-vel-v2' ),
			'amount'    => $amount,
			'known'     => true,
			'note'      => __( 'מחיר שנמצא בחיפוש אמיתי לתאריכים שמופיעים בטבלת ההשוואה.', 'tra-vel-v2' ),
		);
	}

	$rows[] = array(
		'component' => __( 'לינה', 'tra-vel-v2' ),
		'amount'    => $depends,
		'known'     => false,
		'note'      => __( 'משתנה לפי אזור, דירוג, תאריכים ומספר הלילות. אין לנו מחיר מאומת ולכן לא נמציא אחד.', 'tra-vel-v2' ),
	);
	$rows[] = array(
		'component' => __( 'העברות משדה התעופה', 'tra-vel-v2' ),
		'amount'    => $depends,
		'known'     => false,
		'note'      => __( 'משתנה לפי שעת הנחיתה, מספר הנוסעים, כמות המזוודות והמרחק לאזור הלינה.', 'tra-vel-v2' ),
	);
	$rows[] = array(
		'component' => __( 'ביטוח נסיעות', 'tra-vel-v2' ),
		'amount'    => $depends,
		'known'     => false,
		'note'      => __( 'משתנה לפי גיל, מצב רפואי, משך הנסיעה וההרחבות שתבחרו.', 'tra-vel-v2' ),
	);
	$rows[] = array(
		'component' => __( 'תקשורת וגלישה', 'tra-vel-v2' ),
		'amount'    => $depends,
		'known'     => false,
		'note'      => __( 'משתנה לפי חבילת הגלישה או הכרטיס הדיגיטלי שתבחרו ולפי מספר הימים.', 'tra-vel-v2' ),
	);

	return $rows;
}

/**
 * How to pay less, written as checks a traveler can actually run.
 *
 * Every item is an action, never a claim. There is no percentage here, no
 * "save up to" and no assertion about which day or month is cheaper, because
 * we have not measured any of that and inventing it would be the easiest lie
 * on this page.
 *
 * @return array<int,array{title:string,body:string}>
 */
function tra_vel_v2_cost_answer_saving_tips() {
	return array(
		array(
			'title' => __( 'בדקו את אותו מסלול בכמה תאריכים סמוכים', 'tra-vel-v2' ),
			'body'  => __( 'המחיר שנמצא כאן שייך לתאריך אחד מסוים. הזזה של יום או יומיים ביציאה או בחזרה נבדקת בחינם אצל ספק ההזמנה, ולעיתים מחזירה כרטיס אחר לגמרי.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'השוו יציאה באמצע השבוע מול סוף שבוע', 'tra-vel-v2' ),
			'body'  => __( 'רוב הישראלים מחפשים חמישי עד ראשון. בדקו את אותה חופשה גם ביציאה וחזרה באמצע השבוע לפני שאתם סוגרים, כדי לראות אם המחיר משתנה עבורכם.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'בדקו מה בדיוק כלול לפני שאתם משווים מחירים', 'tra-vel-v2' ),
			'body'  => __( 'כרטיס עם תיק יד בלבד וכרטיס עם מזוודה הם שני מוצרים שונים. השוו רק אחרי שבדקתם כבודה, בחירת מושב ותנאי שינוי, אחרת אתם משווים מספרים שלא מייצגים את אותה נסיעה.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'חשבו את העלות הכוללת ולא את מחיר הכרטיס', 'tra-vel-v2' ),
			'body'  => __( 'לכרטיס זול בשדה מרוחק או בשעה שדורשת מונית לילה יש עלות נלווית. חברו לכרטיס את הכבודה, את ההגעה לשדה ואת ההעברה ליעד, ורק אז השוו.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'אל תחליטו על סמך בדיקה אחת', 'tra-vel-v2' ),
			'body'  => __( 'מחיר שנמצא בחיפוש הוא צילום רגע. אם התאריכים שלכם גמישים, בדקו את אותו מסלול שוב בכמה הזדמנויות לפני ההחלטה במקום לסגור על התצפית הראשונה.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'בדקו גם תאריכים מחוץ לחופשות ולחגים', 'tra-vel-v2' ),
			'body'  => __( 'חופשות בתי הספר, חגי ישראל וחגים מקומיים ביעד מרכזים ביקוש. אם אתם לא כבולים אליהם, כדאי לבדוק גם שבוע לפני או אחרי ולראות מה מתקבל.', 'tra-vel-v2' ),
		),
		array(
			'title' => __( 'בדקו אם קונקשן הוא כרטיס אחד או שניים', 'tra-vel-v2' ),
			'body'  => __( 'שני כרטיסים נפרדים יכולים להיראות זולים יותר, אבל איחור בקטע הראשון הוא באחריותכם בלבד. ודאו אצל ספק ההזמנה אם ההעברה מוגנת לפני שאתם בוחרים בה.', 'tra-vel-v2' ),
		),
	);
}

/**
 * The observed price history for one destination, or nothing at all.
 *
 * Three gates, all of which must pass: the recorder has to be readable, the
 * destination's own history has to clear tra_vel_v2_price_history_is_meaningful(),
 * and the page has to be displaying the same shekel lane the recorder writes.
 * Showing shekel observations under a dollar headline would be comparing two
 * different upstream calls, so that combination renders the honest state
 * instead.
 *
 * @param string      $map_state Destination map state.
 * @param string|null $currency  Currency being displayed.
 * @return array{observations:int,months:array<int,array{label:string,min:string,observations:int}>}|null
 */
function tra_vel_v2_cost_answer_history( $map_state, $currency = null ) {
	if ( ! function_exists( 'tra_vel_v2_price_history_is_meaningful' ) || ! function_exists( 'tra_vel_v2_price_history_stats' ) ) {
		return null;
	}

	$currency = function_exists( 'tra_vel_v2_normalize_currency' )
		? tra_vel_v2_normalize_currency( null === $currency ? tra_vel_v2_current_currency() : $currency )
		: TRA_VEL_V2_PRICE_DEFAULT_CURRENCY;
	if ( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY !== $currency ) {
		return null;
	}

	if ( ! tra_vel_v2_price_history_is_meaningful( $map_state ) ) {
		return null;
	}

	$stats = tra_vel_v2_price_history_stats( $map_state );
	if ( ! is_array( $stats ) || empty( $stats['by_month'] ) ) {
		return null;
	}

	$symbol = tra_vel_v2_currency_symbol( TRA_VEL_V2_PRICE_DEFAULT_CURRENCY );
	$months = array();
	foreach ( (array) $stats['by_month'] as $bucket ) {
		$label = tra_vel_v2_cost_answer_month_label( isset( $bucket['month'] ) ? $bucket['month'] : '' );
		$min   = isset( $bucket['min'] ) ? (int) $bucket['min'] : 0;
		if ( '' === $label || $min <= 0 ) {
			continue;
		}
		$months[] = array(
			'label'        => $label,
			'min'          => tra_vel_v2_format_found_price( array( 'price' => $min, 'currency_symbol' => $symbol ) ),
			'observations' => max( 0, (int) ( isset( $bucket['observations'] ) ? $bucket['observations'] : 0 ) ),
			'raw_min'      => $min,
		);
	}

	if ( ! $months ) {
		return null;
	}

	$cheapest = $months[0];
	foreach ( $months as $month ) {
		if ( $month['raw_min'] < $cheapest['raw_min'] ) {
			$cheapest = $month;
		}
	}

	return array(
		'observations' => (int) $stats['observations'],
		'months'       => $months,
		'cheapest'     => $cheapest,
	);
}

/**
 * The five real sub questions this page answers, with real answers.
 *
 * Every answer is derived from the same view the page renders, so there is no
 * second source of truth that could drift from what a visitor reads.
 *
 * @param array<string,mixed> $view Cost answer view model.
 * @return array<int,array{question:string,answer:string}>
 */
function tra_vel_v2_cost_answer_faq_items( $view ) {
	if ( ! is_array( $view ) || '' === (string) ( $view['answer'] ?? '' ) ) {
		return array();
	}

	$city    = (string) $view['prefixed_city'];
	$direct  = is_array( $view['direct_option'] ?? null ) ? $view['direct_option'] : null;
	$history = is_array( $view['history'] ?? null ) ? $view['history'] : null;

	if ( $history ) {
		$timing = sprintf(
			/* translators: 1: observation count, 2: month label, 3: cheapest observed price. */
			__( 'לפי %1$s תצפיות מחיר שרשמנו ליעד הזה, החודש שבו נצפה עד היום המחיר הנמוך ביותר הוא %2$s, ובו נצפה %3$s. זו תצפית על מה שכבר ראינו ולא תחזית, והמחירים ממשיכים לזוז.', 'tra-vel-v2' ),
			number_format_i18n( (int) $history['observations'] ),
			$history['cheapest']['label'],
			$history['cheapest']['min']
		);
	} else {
		$timing = __( 'עדיין אין לנו מספיק תצפיות מחיר ליעד הזה כדי לומר איזה חודש זול יותר, ולא נמציא מספר. בינתיים בדקו כמה תאריכים סמוכים, יציאה באמצע השבוע ותאריכים שאינם בחופשות בתי הספר ובחגים.', 'tra-vel-v2' );
	}

	if ( $direct ) {
		$direct_answer = sprintf(
			/* translators: %s: found price of the direct option. */
			__( 'כן. בין האפשרויות שנמצאו בחיפושים האחרונים יש טיסה ישירה מתל אביב, והמחיר שנמצא לה הוא %s. אפשרות עם עצירה יכולה להיות זולה יותר, ולכן שווה להשוות את שתיהן בטבלה שלמעלה.', 'tra-vel-v2' ),
			$direct['price_label']
		);
		$duration_shape = __( 'האפשרויות שנמצאו כוללות טיסה ישירה מתל אביב', 'tra-vel-v2' );
	} else {
		$direct_answer  = __( 'באפשרויות שנמצאו בחיפושים האחרונים אין כרגע טיסה ישירה מתל אביב, וכולן כוללות עצירה בדרך. זו תמונת מצב של מה שנמצא עכשיו ולא קביעה לגבי כל השנה.', 'tra-vel-v2' );
		$duration_shape = __( 'האפשרויות שנמצאו מתל אביב כוללות עצירה בדרך', 'tra-vel-v2' );
	}

	return array(
		array(
			'question' => sprintf(
				/* translators: %s: destination name. */
				__( 'כמה עולה טיסה ל%s?', 'tra-vel-v2' ),
				$city
			),
			'answer'   => $view['answer'] . ' ' . tra_vel_v2_found_price_scope_note(),
		),
		array(
			'question' => sprintf(
				/* translators: %s: destination name. */
				__( 'מתי הכי זול לטוס ל%s?', 'tra-vel-v2' ),
				$city
			),
			'answer'   => $timing,
		),
		array(
			'question' => sprintf(
				/* translators: %s: destination name. */
				__( 'כמה זמן הטיסה ל%s?', 'tra-vel-v2' ),
				$city
			),
			'answer'   => sprintf(
				/* translators: %s: route shape of the found options. */
				__( '%s. את משך הטיסה המדויק של כרטיס מסוים מציג ספק ההזמנה במסך ההזמנה, ואנחנו מפרסמים כאן רק את מה שרשומת המחיר עצמה מוכיחה: מחיר, תאריכים, חברת תעופה ומספר עצירות.', 'tra-vel-v2' ),
				$duration_shape
			),
		),
		array(
			'question' => sprintf(
				/* translators: %s: destination name. */
				__( 'האם יש טיסה ישירה ל%s?', 'tra-vel-v2' ),
				$city
			),
			'answer'   => $direct_answer,
		),
		array(
			'question' => __( 'מה לא כלול במחיר?', 'tra-vel-v2' ),
			'answer'   => __( 'המחיר הוא מחיר טיסה הלוך ושוב שנמצא בחיפוש, לפני שינויים וכבודה. לינה, העברות משדה התעופה, ביטוח נסיעות ותקשורת אינם כלולים בו, והעלות שלהם תלויה בבחירה שלכם. המחיר הסופי נסגר אצל ספק ההזמנה.', 'tra-vel-v2' ),
		),
	);
}

/**
 * Render the visible FAQ section exactly once, as markup and as text.
 *
 * This is the single copy of the FAQ that both the visitor and the schema see.
 * The template echoes this string, and the schema builder parses this same
 * string back through tra_vel_v2_visible_faq_items(), so a question can never
 * exist in structured data without existing on the page in the same words.
 *
 * @param array<int,array{question:string,answer:string}> $items FAQ pairs.
 * @param string                                          $title Section heading.
 * @return string Safe HTML.
 */
function tra_vel_v2_cost_answer_faq_markup( $items, $title ) {
	if ( ! is_array( $items ) || ! $items ) {
		return '';
	}

	$markup = '<h2 id="' . esc_attr( TRA_VEL_V2_COST_ANSWER_FAQ_ID ) . '">' . esc_html( $title ) . '</h2>';
	foreach ( $items as $item ) {
		$question = isset( $item['question'] ) ? trim( (string) $item['question'] ) : '';
		$answer   = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';
		if ( '' === $question || '' === $answer ) {
			return '';
		}
		$markup .= '<h3>' . esc_html( $question ) . '</h3><p>' . esc_html( $answer ) . '</p>';
	}

	return $markup;
}

/**
 * Everything one cost answer page renders, resolved once.
 *
 * Always returns an array so the template can render the honest state without
 * a second code path, and the readiness of that array is judged separately by
 * tra_vel_v2_cost_answer_contract().
 *
 * @param int $post_id Optional post ID.
 * @return array<string,mixed>
 */
function tra_vel_v2_cost_answer_view( $post_id = 0 ) {
	$post_id   = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$map_state = tra_vel_v2_cost_answer_map_state( $post_id );
	$view      = array(
		'post_id'       => $post_id,
		'map_state'     => $map_state,
		'city'          => '',
		'prefixed_city' => '',
		'price'         => null,
		'options'       => array(),
		'direct_option' => null,
		'answer'        => '',
		'freshness'     => '',
		'scope_note'    => tra_vel_v2_found_price_scope_note(),
		'currency_note' => '',
		'breakdown'     => array(),
		'tips'          => tra_vel_v2_cost_answer_saving_tips(),
		'history'       => null,
		'faq_items'     => array(),
		'faq_markup'    => '',
		'faq_title'     => '',
		'links'         => array(),
		'booking_url'   => '',
	);

	if ( '' === $map_state || ! function_exists( 'tra_vel_v2_seo_opportunity_destinations' ) ) {
		return $view;
	}

	$destinations = tra_vel_v2_seo_opportunity_destinations();
	$city         = isset( $destinations[ $map_state ]['name'] ) ? (string) $destinations[ $map_state ]['name'] : '';
	if ( '' === $city ) {
		return $view;
	}

	$view['city']          = $city;
	$view['prefixed_city'] = function_exists( 'tra_vel_v2_decision_card_prefixed_city' )
		? tra_vel_v2_decision_card_prefixed_city( $city )
		: $city;
	$view['links']         = tra_vel_v2_cost_answer_internal_links( $map_state );
	$view['faq_title']     = sprintf(
		/* translators: %s: destination name. */
		__( 'שאלות ותשובות על עלות טיסה ל%s', 'tra-vel-v2' ),
		$view['prefixed_city']
	);

	$price = tra_vel_v2_get_destination_price( $map_state );
	if ( ! is_array( $price ) || empty( $price['price'] ) || empty( $price['deep_link'] ) ) {
		return $view;
	}

	$view['price']         = $price;
	$view['booking_url']   = (string) $price['deep_link'];
	$view['answer']        = tra_vel_v2_cost_answer_sentence( $view['prefixed_city'], $price );
	$view['freshness']     = tra_vel_v2_found_price_freshness( $price );
	$view['currency_note'] = tra_vel_v2_found_price_currency_note( isset( $price['currency'] ) ? $price['currency'] : null );
	$view['breakdown']     = tra_vel_v2_cost_answer_breakdown_rows( $price );
	$view['history']       = tra_vel_v2_cost_answer_history( $map_state, isset( $price['currency'] ) ? $price['currency'] : null );

	$labels = function_exists( 'tra_vel_v2_decision_card_tier_labels' ) ? tra_vel_v2_decision_card_tier_labels() : array();
	foreach ( tra_vel_v2_get_destination_options( $map_state ) as $option ) {
		$unit = isset( $option['price'] ) ? (int) $option['price'] : 0;
		$tier = isset( $option['tier'] ) ? (string) $option['tier'] : '';
		if ( $unit <= 0 || empty( $option['deep_link'] ) || ! isset( $labels[ $tier ] ) ) {
			continue;
		}
		$row = array(
			'tier'        => $tier,
			'label'       => $labels[ $tier ],
			'price_label' => tra_vel_v2_format_found_price( $option ),
			'stops_label' => tra_vel_v2_decision_card_stops_label( isset( $option['transfers'] ) ? $option['transfers'] : 0 ),
			'transfers'   => isset( $option['transfers'] ) ? (int) $option['transfers'] : 0,
			'airline'     => isset( $option['airline'] ) ? (string) $option['airline'] : '',
			'dates_label' => tra_vel_v2_decision_card_dates_label( $option ),
			'deep_link'   => (string) $option['deep_link'],
		);
		$view['options'][] = $row;
		if ( null === $view['direct_option'] && 0 === $row['transfers'] ) {
			$view['direct_option'] = $row;
		}
	}

	if ( '' === $view['answer'] || ! $view['options'] ) {
		return $view;
	}

	$view['faq_items']  = tra_vel_v2_cost_answer_faq_items( $view );
	$view['faq_markup'] = tra_vel_v2_cost_answer_faq_markup( $view['faq_items'], $view['faq_title'] );

	return $view;
}

/**
 * Crawlable context links that are proven to resolve to a live page.
 *
 * Registry ownership alone is never enough: the exact WordPress page has to
 * exist and be published, otherwise this page would export a broken link into
 * the entity graph. When the destination's own hub is not published yet, the
 * published destinations directory stands in for it.
 *
 * @param string $map_state Destination map state.
 * @return array<int,array{url:string,title:string,kind:string}>
 */
function tra_vel_v2_cost_answer_internal_links( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );
	if ( ! function_exists( 'tra_vel_v2_load_seo_opportunity_registry' ) ) {
		return array();
	}
	$registry = tra_vel_v2_load_seo_opportunity_registry();
	if ( empty( $registry['valid'] ) ) {
		return array();
	}

	$published = static function ( $canonical_path ) {
		$page = get_page_by_path( trim( (string) $canonical_path, '/' ), OBJECT, 'page' );
		return $page instanceof WP_Post && 'publish' === get_post_status( $page );
	};

	$links = array();
	$hub   = null;
	foreach ( $registry['entries'] as $entry ) {
		if ( 'destination-hub' !== ( $entry['pageType'] ?? '' ) || $map_state !== ( $entry['mapState'] ?? null ) ) {
			continue;
		}
		if ( $published( $entry['canonicalPath'] ) ) {
			$hub = $entry;
		}
		break;
	}
	if ( null === $hub ) {
		$directory = $registry['by_path']['/destinations/'] ?? null;
		$hub       = is_array( $directory ) && $published( '/destinations/' ) ? $directory : null;
	}
	if ( is_array( $hub ) ) {
		$links[] = array(
			'url'   => home_url( $hub['canonicalPath'] ),
			'title' => (string) $hub['primaryIntent'],
			'kind'  => 'destination',
		);
	}

	$pillar = $registry['by_path']['/flights/'] ?? null;
	if ( is_array( $pillar ) && 'commercial-hub' === ( $pillar['pageType'] ?? '' ) && $published( '/flights/' ) ) {
		$links[] = array(
			'url'   => home_url( $pillar['canonicalPath'] ),
			'title' => (string) $pillar['primaryIntent'],
			'kind'  => 'pillar',
		);
	}

	return $links;
}

/**
 * The fail closed indexability contract of one cost answer page.
 *
 * Four independent conditions, all of which have to hold. The FAQ check is
 * deliberately not "we intended to render five pairs": it re-reads the markup
 * the page actually emits, so a rendering bug that drops a pair also drops the
 * page out of the index instead of leaving a five question schema claim above
 * a four question page.
 *
 * @param int                      $post_id Optional post ID.
 * @param array<string,mixed>|null $view    Optional resolved view.
 * @return array{ready:bool,checks:array<string,bool>,view:array<string,mixed>}
 */
function tra_vel_v2_cost_answer_contract( $post_id = 0, $view = null ) {
	$view          = is_array( $view ) ? $view : tra_vel_v2_cost_answer_view( $post_id );
	$visible_pairs = ( '' !== (string) $view['faq_markup'] && function_exists( 'tra_vel_v2_visible_faq_items' ) )
		? tra_vel_v2_visible_faq_items( 0, (string) $view['faq_markup'] )
		: array();

	$checks = array(
		'destination_resolved' => '' !== (string) $view['map_state'] && '' !== (string) $view['city'],
		'real_price'           => is_array( $view['price'] ) && (int) ( $view['price']['price'] ?? 0 ) > 0 && '' !== (string) $view['answer'],
		'comparison_rows'      => count( (array) $view['options'] ) >= 1,
		'visible_faq'          => count( $visible_pairs ) === TRA_VEL_V2_COST_ANSWER_FAQ_PAIRS,
		'booking_route'        => '' !== (string) $view['booking_url'],
	);

	return array(
		'ready'  => ! in_array( false, $checks, true ),
		'checks' => $checks,
		'view'   => $view,
	);
}

/**
 * The meta description of one cost answer page.
 *
 * Exactly the answer sentence, with nothing appended. The snippet a searcher
 * reads and the sentence the page opens with are then provably the same claim,
 * which is the only way a description can stay true as the fare moves.
 *
 * @param int $post_id Optional post ID.
 * @return string Description, or an empty string when the page is not ready.
 */
function tra_vel_v2_cost_answer_meta_description( $post_id = 0 ) {
	$contract = tra_vel_v2_cost_answer_contract( $post_id );

	return $contract['ready'] ? (string) $contract['view']['answer'] : '';
}

/**
 * The public document title of one ready cost answer page.
 *
 * @param int $post_id Optional post ID.
 * @return string Title, or an empty string.
 */
function tra_vel_v2_cost_answer_public_title( $post_id = 0 ) {
	if ( ! $post_id && ! tra_vel_v2_cost_answer_is_request() ) {
		return '';
	}
	$contract = tra_vel_v2_cost_answer_contract( $post_id );
	if ( ! $contract['ready'] ) {
		return '';
	}

	return sprintf(
		/* translators: %s: destination name. */
		__( 'כמה עולה טיסה ל%1$s: המחיר שנמצא עכשיו, טבלת השוואה ומה לא כלול', 'tra-vel-v2' ),
		$contract['view']['prefixed_city']
	);
}

/**
 * The non commercial schema of one ready cost answer page.
 *
 * BreadcrumbList and a gated FAQPage, and nothing else. Offer, Product and
 * AggregateOffer are absent by construction: the fare on this page belongs to
 * a supplier we link to, not to us, and marking it up as our offer would be a
 * commercial claim we cannot honour.
 *
 * @param int                      $post_id Optional post ID.
 * @param array<string,mixed>|null $view    Optional resolved view.
 * @return array<int,array<string,mixed>>
 */
function tra_vel_v2_cost_answer_schema_nodes( $post_id = 0, $view = null ) {
	$post_id  = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$contract = tra_vel_v2_cost_answer_contract( $post_id, $view );
	if ( ! $contract['ready'] ) {
		return array();
	}

	$view = $contract['view'];
	$url  = (string) get_permalink( $post_id );
	if ( '' === $url ) {
		return array();
	}

	$items = array();
	foreach ( tra_vel_v2_cost_answer_breadcrumb_items( $view, $url ) as $position => $item ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position + 1,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
	}

	$nodes = array(
		array(
			'@type'       => 'WebPage',
			'@id'         => $url . '#webpage',
			'url'         => $url,
			'name'        => tra_vel_v2_cost_answer_public_title( $post_id ),
			'description' => (string) $view['answer'],
			'inLanguage'  => 'he-IL',
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'breadcrumb'  => array( '@id' => $url . '#breadcrumb' ),
		),
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $items,
		),
	);

	// The only FAQPage this page can ever emit is the one parsed back out of
	// the markup it just rendered, through the shared visible-FAQ extractor.
	if ( function_exists( 'tra_vel_v2_visible_faq_items' ) && function_exists( 'tra_vel_v2_faq_page_node' ) ) {
		$faq_node = tra_vel_v2_faq_page_node( $url, tra_vel_v2_visible_faq_items( 0, (string) $view['faq_markup'] ) );
		if ( $faq_node ) {
			$nodes[] = $faq_node;
		}
	}

	return $nodes;
}

/**
 * Visible and structured breadcrumbs for one cost answer page.
 *
 * @param array<string,mixed> $view Cost answer view model.
 * @param string              $url  Canonical page URL.
 * @return array<int,array{name:string,url:string,current:bool}>
 */
function tra_vel_v2_cost_answer_breadcrumb_items( $view, $url ) {
	$items = array(
		array(
			'name'    => __( 'ראשי', 'tra-vel-v2' ),
			'url'     => home_url( '/' ),
			'current' => false,
		),
	);
	foreach ( (array) ( $view['links'] ?? array() ) as $link ) {
		if ( 'pillar' !== ( $link['kind'] ?? '' ) ) {
			continue;
		}
		$items[] = array(
			'name'    => (string) $link['title'],
			'url'     => (string) $link['url'],
			'current' => false,
		);
	}
	$items[] = array(
		'name'    => sprintf(
			/* translators: %s: destination name. */
			__( 'כמה עולה טיסה ל%s', 'tra-vel-v2' ),
			(string) ( $view['prefixed_city'] ?? '' )
		),
		'url'     => (string) $url,
		'current' => true,
	);

	return $items;
}

/**
 * Whether this request must stay outside the search index.
 *
 * @return bool
 */
function tra_vel_v2_cost_answer_should_noindex() {
	if ( ! tra_vel_v2_cost_answer_is_request() ) {
		return false;
	}
	$contract = tra_vel_v2_cost_answer_contract( (int) get_queried_object_id() );

	return empty( $contract['ready'] );
}

/**
 * Keep an unready cost answer page out of the index under core robots.
 *
 * @param array<string,bool> $robots Existing directives.
 * @return array<string,bool>
 */
function tra_vel_v2_cost_answer_robots_policy( $robots ) {
	if ( ! tra_vel_v2_cost_answer_should_noindex() ) {
		return $robots;
	}
	$robots['noindex'] = true;
	$robots['follow']  = true;
	unset( $robots['index'], $robots['nofollow'] );

	return $robots;
}
add_filter( 'wp_robots', 'tra_vel_v2_cost_answer_robots_policy', 30 );

/** Preserve the same fail closed policy when Yoast owns robots. */
function tra_vel_v2_cost_answer_yoast_robots_policy( $robots ) {
	if ( ! tra_vel_v2_cost_answer_should_noindex() ) {
		return $robots;
	}
	$robots['index']  = 'noindex';
	$robots['follow'] = 'follow';

	return $robots;
}
add_filter( 'wpseo_robots_array', 'tra_vel_v2_cost_answer_yoast_robots_policy', 30 );

/** Preserve the same fail closed policy when AIOSEO owns robots. */
function tra_vel_v2_cost_answer_aioseo_robots_policy( $attributes ) {
	if ( ! tra_vel_v2_cost_answer_should_noindex() ) {
		return $attributes;
	}
	$output = (array) $attributes;
	foreach ( (array) $attributes as $key => $value ) {
		$directive = is_string( $key ) ? strtolower( $key ) : strtolower( (string) $value );
		if ( in_array( $directive, array( 'index', 'nofollow' ), true ) ) {
			unset( $output[ $key ] );
		}
	}
	$output['noindex']  = 'noindex';
	$output['nofollow'] = '';

	return $output;
}
add_filter( 'aioseo_robots_meta', 'tra_vel_v2_cost_answer_aioseo_robots_policy', 30 );

/**
 * Replace plugin rich nodes with this page's gated, non commercial fragment.
 *
 * Product, Offer, AggregateOffer and ItemList are dropped unconditionally: a
 * found third party fare is never our inventory. Article, BreadcrumbList and
 * FAQPage are dropped and re-emitted only from the contract, so a plugin can
 * neither invent a question nor keep a breadcrumb on an unready page.
 *
 * @param array<int,array<string,mixed>> $graph Plugin graph nodes.
 * @return array<int,array<string,mixed>>
 */
function tra_vel_v2_cost_answer_schema_graph( $graph ) {
	if ( ! is_array( $graph ) || ! tra_vel_v2_cost_answer_is_request() ) {
		return $graph;
	}

	$post_id     = (int) get_queried_object_id();
	$ready_nodes = tra_vel_v2_cost_answer_schema_nodes( $post_id );
	$output      = array();
	foreach ( $graph as $node ) {
		if ( ! is_array( $node ) ) {
			$output[] = $node;
			continue;
		}
		$types = (array) ( $node['@type'] ?? array() );
		if ( array_intersect( array( 'Product', 'Offer', 'AggregateOffer', 'ItemList' ), $types ) ) {
			continue;
		}
		if ( array_intersect( array( 'Article', 'BlogPosting', 'NewsArticle', 'BreadcrumbList', 'FAQPage' ), $types ) ) {
			continue;
		}
		if ( in_array( 'WebPage', $types, true ) ) {
			if ( $ready_nodes ) {
				continue;
			}
			unset( $node['about'], $node['mainEntity'], $node['potentialAction'], $node['breadcrumb'], $node['offers'], $node['offer'] );
		}
		$output[] = $node;
	}

	return array_values( array_merge( $output, $ready_nodes ) );
}
add_filter( 'wpseo_schema_graph', 'tra_vel_v2_cost_answer_schema_graph', 30 );
add_filter( 'aioseo_schema_output', 'tra_vel_v2_cost_answer_schema_graph', 30 );

/**
 * Give a ready cost answer page its search facing title.
 *
 * @param array<string, string> $parts Generated document title parts.
 * @return array<string, string>
 */
function tra_vel_v2_cost_answer_document_title_parts( $parts ) {
	$title = tra_vel_v2_cost_answer_public_title();
	if ( '' !== $title ) {
		$parts['title'] = $title;
	}

	return $parts;
}
add_filter( 'document_title_parts', 'tra_vel_v2_cost_answer_document_title_parts', 20 );

/** Carry the same title through plugins that bypass core title parts. */
function tra_vel_v2_cost_answer_plugin_title( $title ) {
	$cost_answer_title = tra_vel_v2_cost_answer_public_title();

	return '' !== $cost_answer_title ? $cost_answer_title . ' | Tra-Vel' : $title;
}
add_filter( 'wpseo_title', 'tra_vel_v2_cost_answer_plugin_title', 30 );
add_filter( 'wpseo_opengraph_title', 'tra_vel_v2_cost_answer_plugin_title', 30 );
add_filter( 'wpseo_twitter_title', 'tra_vel_v2_cost_answer_plugin_title', 30 );
