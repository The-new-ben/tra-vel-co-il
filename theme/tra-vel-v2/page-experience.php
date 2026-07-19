<?php
/**
 * Template Name: Tra-Vel Experience Hub
 * Template Post Type: page
 *
 * @package TraVelV2
 */

$slug = get_post_field( 'post_name', get_queried_object_id() );

$experiences = array(
	'flights' => array(
		'eyebrow'     => 'טיסות',
		'title'       => 'איזו טיסה מתאימה לכם?',
		'description' => 'השוו מחיר לכל הנוסעים, כבודה, זמן מדלת לדלת, קונקשן, גמישות וסיכון.',
		'prompt'      => 'תל אביב ← לא משנה לאן',
		'action'      => 'השוו טיסות',
		'chips'       => array( 'ישיר בלבד', 'כבודה כלולה', 'עד עצירה אחת', 'תאריכים גמישים' ),
		'proof'       => array( 'מחיר כולל' => 'כבודה ושינוי בפנים', 'מסלול' => 'ישיר מול קונקשן', 'גמישות' => 'טווח תאריכים חכם', 'שקיפות' => 'למה לבחור בכל חלופה' ),
		'cards'       => array(
			array( 'plane-takeoff', 'ישיר', 'ישיר ופשוט', 'זמן קצר יותר וסיכון נמוך, בדרך כלל במחיר גבוה יותר.' ),
			array( 'route', 'קונקשן', 'איזון בין זמן למחיר', 'כרטיס אחד ועצירה סבירה עשויים להוזיל את הטיסה בלי להוסיף סיכון של כרטיס נפרד.' ),
			array( 'piggy-bank', 'מסלול גמיש', 'יותר אפשרויות במחיר', 'כרטיסים נפרדים או עצירת לילה, עם הסבר ברור על הזמן והסיכון.' ),
		),
	),
	'hotels' => array(
		'eyebrow'     => 'מלונות',
		'title'       => 'איפה כדאי לישון?',
		'description' => 'בוחרים אזור לפי החוויה, זמן הנסיעה והתקציב; אחר כך משווים נכסים, חדרים, ביטול ומה באמת כלול.',
		'prompt'      => 'יעד, שכונה או סגנון חופשה',
		'action'      => 'השוו מלונות',
		'chips'       => array( 'ביטול גמיש', 'ארוחת בוקר', 'משפחות', 'ליד תחבורה' ),
		'proof'       => array( 'מיקום' => 'זמן אמיתי למסלול', 'חדר' => 'מה בדיוק מזמינים', 'תנאים' => 'ביטול ותשלום', 'עלות' => 'מסים ותוספות בפנים' ),
		'cards'       => array(
			array( 'map-pin-house', 'מרכז וקצב', 'להיות בתוך העניינים', 'בחירה למי שמעדיף ללכת ברגל ולחסוך זמן תחבורה.' ),
			array( 'trees', 'שקט ומרחב', 'יותר מלון באותו תקציב', 'מתאים לשהייה ארוכה, למשפחות ולמי שמעדיף קצב רגוע.' ),
			array( 'waves', 'נופש', 'המלון הוא היעד', 'בריכה, חוף, ספא ושירותים שמצדיקים זמן רב יותר בנכס.' ),
		),
	),
	'packages' => array(
		'eyebrow'     => 'טיסה ומלון',
		'title'       => 'טיסה ומלון במחיר שקל להבין.',
		'description' => 'משווים טיסה, מלון, כבודה, ארוחת בוקר והעברות. מידע לבירור בביטוח מוצג בנפרד ואינו חלק מהמחיר.',
		'prompt'      => 'תל אביב לבודפשט, ארבעה לילות',
		'action'      => 'השוו טיסה ומלון',
		'chips'       => array( 'טיסה ישירה', 'ביטול גמיש', 'כבודה', 'העברה משדה התעופה' ),
		'proof'       => array( 'מחיר' => 'לכל ההרכב, לא לאדם', 'רכיבים' => 'כל תוספת גלויה', 'סיכון' => 'כרטיס, ביטול ומיקום', 'שליטה' => 'מחליפים חלק בלי להתחיל מחדש' ),
		'cards'       => array(
			array( 'badge-dollar-sign', 'עלות מלאה', 'סכום אחד לכל הנסיעה', 'טיסה, לינה, כבודה, אוכל והעברה מוצגים באותו מטבע. מידע לבירור בביטוח נשאר נפרד.' ),
			array( 'sliders-horizontal', 'שליטה', 'כל רכיב ניתן לשינוי', 'מחליפים טיסה, מלון או העברה ורואים את ההשפעה על המחיר. מידע לבירור בביטוח נשאר נפרד.' ),
			array( 'shield-alert', 'שקיפות', 'אין חיסכון בלי בסיס', 'חיסכון יוצג רק מול מחיר נפרד ומחיר משולב בני השוואה; כל רכיב מסומן לפי מקורו.' ),
		),
	),
	'travel-insurance' => array(
		'eyebrow'     => 'ביטוח נסיעות',
		'title'       => 'מה חשוב לבדוק בביטוח נסיעות?',
		'description' => 'רכזו יעד, תאריכים, נוסעים ופעילויות כדי להבין אילו סעיפים והרחבות צריך לברר מול המבטח. המידע אינו המלצה או הצעת ביטוח.',
		'prompt'      => 'לאן נוסעים ומה עושים?',
		'action'      => 'בדקו מה חשוב לנסיעה',
		'chips'       => array( 'מצב רפואי', 'כבודה וציוד', 'ספורט', 'ביטול וקיצור' ),
		'proof'       => array( 'רפואי' => 'גבולות ותנאים', 'כבודה' => 'ציוד וחריגים', 'פעילות' => 'דרישות והחרגות', 'שירות' => 'איך פונים בעת הצורך' ),
		'cards'       => array(
			array( 'heart-pulse', 'בריאות', 'הכיסוי המרכזי', 'הוצאות רפואיות, תרופות, אשפוז ופינוי לפי תנאי הפוליסה.' ),
			array( 'luggage', 'כבודה וציוד', 'מה חשוב לכם בדרך', 'מזוודה, מחשב, טלפון וציוד ייעודי עם מגבלות ברורות.' ),
			array( 'shield-check', 'שינויים', 'כשהתוכנית משתנה', 'ביטול, קיצור נסיעה ועיכובים בלי להסתיר חריגים.' ),
		),
	),
	'ai-planner' => array(
		'eyebrow'     => 'מתכנן החופשה',
		'title'       => 'ספרו לנו איזו חופשה אתם רוצים.',
		'description' => 'כתבו או דברו בחופשיות. יעד, תקציב, תאריכים, נוסעים ומה חשוב לכם. נשאל רק על פרט שחסר.',
		'prompt'      => 'למשל: 12 יום בתאילנד לזוג, רגוע, עד ₪9,000, בלי יותר מעצירה אחת',
		'action'      => 'סדרו לי תוכנית',
		'chips'       => array( 'זוג', 'משפחה', 'גמיש בתאריכים', 'טיול ראשון' ),
		'proof'       => array( 'הבקשה שלכם' => 'יעד, תקציב ומה חשוב', 'שאלות קצרות' => 'רק על מה שחסר', 'סיכום ברור' => 'רואים מה הובן ומה חסר', 'שליטה מלאה' => 'אתם בוחרים אם לבקש סיוע' ),
		'cards'       => array(
			array( 'messages-square', 'ספרו', 'מטרה, קצב ותקציב', 'מתחילים מהאנשים ומהחוויה, לא מרשימת יעדים מוכנה.' ),
			array( 'scan-search', 'בדקו', 'מה באמת הובן', 'מקבלים סיכום מובנה, הנחות מסומנות ושאלות שחייבות תשובה.' ),
			array( 'list-checks', 'הבהירו', 'רק את מה שחסר', 'אחרי השלמת הפרטים תקבלו תוכנית מובנית ותוכלו לבחור אם לבקש בדיקה אישית.' ),
		),
	),
	'destinations' => array(
		'eyebrow'     => 'יעדים וחופשות',
		'title'       => 'לאן תרצו לטוס?',
		'description' => 'מצאו יעדים לפי תקציב, זמן, עונה וסגנון, ואז המשיכו למדריך, למפה ולמחירים.',
		'prompt'      => 'איזו חופשה מתאימה לכם עכשיו?',
		'action'      => 'גלו על המפה',
		'chips'       => array( 'אירופה קצרה', 'אסיה גדולה', 'חוף', 'עיר ואוכל' ),
		'proof'       => array( 'עונה' => 'מתי זה באמת מתאים', 'תקציב' => 'עלות יומית כוללת', 'קצב' => 'כמה להספיק', 'חיבור' => 'מדריך ומחירים יחד' ),
		'cards'       => array(
			array( 'building-2', 'אירופה', 'קרוב, מגוון וחכם', 'ערים, רכבות, אוכל וחופשות קצרות עם זמן טיסה נוח.' ),
			array( 'palmtree', 'אסיה', 'טיול גדול בקצב שלכם', 'מסלולים ארוכים, עונות שונות ושילוב נכון בין אזורים.' ),
			array( 'sunset', 'איים וחופים', 'לבחור לפי העונה', 'מזג אוויר, גישה, מלון ופעילויות, לא רק תמונה יפה.' ),
		),
	),
	'guides' => array(
		'eyebrow'     => 'מדריכי יעדים וחופשות',
		'title'       => 'מה תרצו לדעת לפני שסוגרים?',
		'description' => 'מדריכי עומק עם מקורות, תאריך בדיקה, נתונים שימושיים וקישורים ישירים למפה ולמחירים.',
		'prompt'      => 'חפשו יעד, שאלה או החלטה',
		'action'      => 'פתחו במפת החופשות',
		'chips'       => array( 'טיסות', 'תקציב', 'מסלולים', 'ביטוח' ),
		'proof'       => array( 'החלטות' => 'טבלאות וחלופות ברורות', 'מקורות' => 'עובדות עם תאריך', 'מבנה' => 'תשובה לפני פירוט', 'פעולה' => 'כל פרק מוביל לצעד הבא' ),
		'cards'       => array(
			array( 'book-open-text', 'מדריך יעד', 'כל ההחלטות במקום אחד', 'עונה, אזורים, מסלול, תחבורה, מלונות, עלויות וביטוח.' ),
			array( 'circle-help', 'מדריך החלטה', 'תשובה לשאלה אחת', 'השוואה ממוקדת שמסבירה מה עדיף, למי ובאילו תנאים.' ),
			array( 'list-checks', 'כלי תכנון', 'לא לשכוח את החשוב', 'צ׳קליסטים, מחשבונים וטבלאות שאפשר להשתמש בהם בזמן אמת.' ),
		),
	),
);

$experience = isset( $experiences[ $slug ] ) ? $experiences[ $slug ] : $experiences['destinations'];
$experience_kind = isset( $experiences[ $slug ] ) ? $slug : 'destinations';
$map_url    = home_url( '/travel-map/' );
$is_flights = 'flights' === $slug;
$is_hotels  = 'hotels' === $slug;
$is_packages = 'packages' === $slug;
$is_insurance = 'travel-insurance' === $slug;
$is_ai_planner = 'ai-planner' === $slug;
$is_surprise = $is_ai_planner && isset( $_GET['mode'] ) && 'surprise' === sanitize_key( wp_unslash( $_GET['mode'] ) );
$agent_destination = $is_ai_planner && isset( $_GET['destination'] ) ? sanitize_key( wp_unslash( $_GET['destination'] ) ) : '';
$requested_product = $is_ai_planner && isset( $_GET['product'] ) && is_string( $_GET['product'] ) ? sanitize_key( wp_unslash( $_GET['product'] ) ) : '';
$agent_scope_labels = array(
	'flights'       => __( 'טיסות', 'tra-vel-v2' ),
	'accommodation' => __( 'לינה', 'tra-vel-v2' ),
	'transfers'     => __( 'העברות ותחבורה', 'tra-vel-v2' ),
	'activities'    => __( 'פעילויות וסיורים', 'tra-vel-v2' ),
	'dining'        => __( 'אוכל והעדפות', 'tra-vel-v2' ),
	'insurance'     => __( 'ביטוח: מידע לבדיקה', 'tra-vel-v2' ),
	'connectivity'  => __( 'תקשורת וחיבור', 'tra-vel-v2' ),
	'equipment'     => __( 'ציוד לנסיעה', 'tra-vel-v2' ),
);
$agent_scope = array();
if ( $is_ai_planner && isset( $_GET['scope'] ) ) {
	$requested_scope = substr( sanitize_text_field( wp_unslash( $_GET['scope'] ) ), 0, 240 );
	foreach ( explode( ',', $requested_scope ) as $scope_key ) {
		$scope_key = sanitize_key( $scope_key );
		if ( isset( $agent_scope_labels[ $scope_key ] ) ) {
			$agent_scope[] = $scope_key;
		}
	}
	$agent_scope = array_slice( array_values( array_unique( $agent_scope ) ), 0, count( $agent_scope_labels ) );
}
$agent_map_latitude  = null;
$agent_map_longitude = null;
if ( $is_ai_planner && isset( $_GET['latitude'], $_GET['longitude'] ) ) {
	$latitude_value  = sanitize_text_field( wp_unslash( $_GET['latitude'] ) );
	$longitude_value = sanitize_text_field( wp_unslash( $_GET['longitude'] ) );
	if ( is_numeric( $latitude_value ) && is_numeric( $longitude_value ) ) {
		$latitude_value  = (float) $latitude_value;
		$longitude_value = (float) $longitude_value;
		if ( $latitude_value >= -90 && $latitude_value <= 90 && $longitude_value >= -180 && $longitude_value <= 180 ) {
			$agent_map_latitude  = $latitude_value;
			$agent_map_longitude = $longitude_value;
		}
	}
}
$planning_intent   = isset( $_GET['intent'] ) ? sanitize_key( wp_unslash( $_GET['intent'] ) ) : 'smart';
$agent_intent      = $planning_intent;
$agent_intents     = array(
	'smart'     => __( 'חכמה ומאוזנת', 'tra-vel-v2' ),
	'value'     => __( 'עם ערך גבוה לכסף', 'tra-vel-v2' ),
	'easy'      => __( 'פשוטה ונוחה', 'tra-vel-v2' ),
	'romantic'  => __( 'זוגית', 'tra-vel-v2' ),
	'family'    => __( 'משפחתית', 'tra-vel-v2' ),
	'adventure' => __( 'עם הרפתקה', 'tra-vel-v2' ),
	'surprise'  => __( 'מפתיעה', 'tra-vel-v2' ),
);
$agent_intent = isset( $agent_intents[ $agent_intent ] ) ? $agent_intent : 'smart';
$planning_intent = isset( $agent_intents[ $planning_intent ] ) ? $planning_intent : 'smart';
$requested_origin_code = isset( $_GET['origin'] ) && is_string( $_GET['origin'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['origin'] ) ) ) : 'TLV';
if ( ! preg_match( '/^[A-Z]{3}$/', $requested_origin_code ) ) {
	$requested_origin_code = 'TLV';
}
$requested_origin_label = 'TLV' === $requested_origin_code ? __( 'תל אביב', 'tra-vel-v2' ) : $requested_origin_code;
$requested_destination_code = isset( $_GET['destination'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['destination'] ) ) ) : '';
if ( ! preg_match( '/^[A-Z]{3}$/', $requested_destination_code ) ) {
	$requested_destination_code = '';
}
$destination_labels = array(
	'BUD' => __( 'בודפשט', 'tra-vel-v2' ),
	'PRG' => __( 'פראג', 'tra-vel-v2' ),
	'VIE' => __( 'וינה', 'tra-vel-v2' ),
	'ATH' => __( 'אתונה', 'tra-vel-v2' ),
	'DXB' => __( 'דובאי', 'tra-vel-v2' ),
	'BKK' => __( 'בנגקוק', 'tra-vel-v2' ),
	'HND' => __( 'טוקיו', 'tra-vel-v2' ),
	'LIS' => __( 'ליסבון', 'tra-vel-v2' ),
);
$destination_slug_labels = array(
	'budapest' => __( 'בודפשט', 'tra-vel-v2' ),
	'prague'   => __( 'פראג', 'tra-vel-v2' ),
	'vienna'   => __( 'וינה', 'tra-vel-v2' ),
	'athens'   => __( 'אתונה', 'tra-vel-v2' ),
	'dubai'    => __( 'דובאי', 'tra-vel-v2' ),
	'bangkok'  => __( 'בנגקוק', 'tra-vel-v2' ),
	'tokyo'    => __( 'טוקיו', 'tra-vel-v2' ),
	'lisbon'   => __( 'ליסבון', 'tra-vel-v2' ),
);
$trip_destination = isset( $_GET['trip_destination'] ) ? sanitize_key( wp_unslash( $_GET['trip_destination'] ) ) : '';
$destination_code_slugs = array(
	'BUD' => 'budapest',
	'PRG' => 'prague',
	'VIE' => 'vienna',
	'ATH' => 'athens',
	'DXB' => 'dubai',
	'BKK' => 'bangkok',
	'HND' => 'tokyo',
	'LIS' => 'lisbon',
);
$destination_slug_codes = array_flip( $destination_code_slugs );
$experience_destination_catalog = array(
	'budapest' => array( 'label' => __( 'עיר', 'tra-vel-v2' ), 'title' => __( 'חופשה עירונית בבודפשט', 'tra-vel-v2' ), 'copy' => __( 'השוו טיסות, אזור לינה, מרחצאות, אוכל ומה עושים לפני שמבקשים הצעה.', 'tra-vel-v2' ) ),
	'prague'   => array( 'label' => __( 'עיר', 'tra-vel-v2' ), 'title' => __( 'חופשה עירונית בפראג', 'tra-vel-v2' ), 'copy' => __( 'השוו טיסות, אזורי לינה, תחבורה וקצב הליכה לפני שמבקשים הצעה.', 'tra-vel-v2' ) ),
	'vienna'   => array( 'label' => __( 'עיר ותרבות', 'tra-vel-v2' ), 'title' => __( 'תרבות ומשפחה בווינה', 'tra-vel-v2' ), 'copy' => __( 'השוו טיסות, אזור לינה, תחבורה ופעילויות לפי הקצב שלכם.', 'tra-vel-v2' ) ),
	'athens'   => array( 'label' => __( 'עיר ואיים', 'tra-vel-v2' ), 'title' => __( 'אתונה עם אפשרות להמשיך לאיים', 'tra-vel-v2' ), 'copy' => __( 'השוו חופשה עירונית קצרה, חיבור לאיים ואזורי לינה לפי הזמן שלכם.', 'tra-vel-v2' ) ),
	'dubai'    => array( 'label' => __( 'עיר ועצירה', 'tra-vel-v2' ), 'title' => __( 'דובאי כחופשה או כעצירה בדרך', 'tra-vel-v2' ), 'copy' => __( 'השוו טיסה ישירה, עצירת ביניים, אזורי לינה ופעילויות לפי התקציב.', 'tra-vel-v2' ) ),
	'bangkok'  => array( 'label' => __( 'עיר וטבע', 'tra-vel-v2' ), 'title' => __( 'עיר, איים וטבע בתאילנד', 'tra-vel-v2' ), 'copy' => __( 'בדקו מסלול, עונה, מעברים ונקודות לבירור בביטוח לפני שמבקשים הצעה.', 'tra-vel-v2' ) ),
	'tokyo'    => array( 'label' => __( 'עיר ותרבות', 'tra-vel-v2' ), 'title' => __( 'טוקיו כבסיס לטיול ביפן', 'tra-vel-v2' ), 'copy' => __( 'השוו שכונות, רכבות, טיולי יום וקצב מסלול שמתאים למספר הימים.', 'tra-vel-v2' ) ),
	'lisbon'   => array( 'label' => __( 'עיר וחוף', 'tra-vel-v2' ), 'title' => __( 'עיר וחוף בליסבון', 'tra-vel-v2' ), 'copy' => __( 'השוו שכונות, חוף, טיסות ומלונות לפי הקצב שלכם.', 'tra-vel-v2' ) ),
);
$experience_selected_destination = $is_flights ? 'bangkok' : 'budapest';
$experience_context_requested     = (bool) ( $agent_destination || $trip_destination || $requested_destination_code );
$experience_context_supported     = true;
if ( $agent_destination ) {
	if ( isset( $experience_destination_catalog[ $agent_destination ] ) ) {
		$experience_selected_destination = $agent_destination;
	} else {
		$experience_context_supported = false;
	}
} elseif ( $trip_destination ) {
	if ( isset( $experience_destination_catalog[ $trip_destination ] ) ) {
		$experience_selected_destination = $trip_destination;
	} else {
		$experience_context_supported = false;
	}
} elseif ( $requested_destination_code ) {
	if ( isset( $destination_code_slugs[ $requested_destination_code ] ) ) {
		$experience_selected_destination = $destination_code_slugs[ $requested_destination_code ];
	} else {
		$experience_context_supported = false;
	}
}
$experience_destination_options = array( $experience_selected_destination );
foreach ( array_keys( $experience_destination_catalog ) as $destination_slug ) {
	if ( count( $experience_destination_options ) >= 3 ) {
		break;
	}
	if ( ! in_array( $destination_slug, $experience_destination_options, true ) ) {
		$experience_destination_options[] = $destination_slug;
	}
}
$experience_selected_content = $experience_context_supported
	? $experience_destination_catalog[ $experience_selected_destination ]
	: array(
		'title' => __( 'ממשיכים מהיעד שבחרתם', 'tra-vel-v2' ),
		'copy'  => __( 'היעד נשמר. השלימו תאריכים, נוסעים ומה חשוב לכם כדי לקבל כיוון שמתאים לנסיעה הזאת.', 'tra-vel-v2' ),
	);
$experience_destination_links = array();
foreach ( $experience_destination_catalog as $destination_slug => $destination_content ) {
	$destination_code = $destination_slug_codes[ $destination_slug ] ?? '';
	if ( ( $is_flights || $is_hotels || $is_packages ) && $destination_code ) {
		$destination_url = add_query_arg( 'destination', $destination_code, home_url( '/' . $experience_kind . '/' ) );
	} elseif ( $is_insurance ) {
		$destination_url = add_query_arg( 'trip_destination', $destination_slug, home_url( '/travel-insurance/' ) );
	} elseif ( $is_ai_planner ) {
		$destination_url = add_query_arg( 'destination', $destination_slug, home_url( '/ai-planner/' ) );
	} else {
		$destination_url = add_query_arg( 'destination', $destination_slug, $map_url );
	}
	$experience_destination_links[ $destination_slug ] = array(
		'city'    => $destination_slug_labels[ $destination_slug ],
		'airport' => $destination_code,
		'url'     => $destination_url,
	);
}
$flight_destination_code = $requested_destination_code ? $requested_destination_code : 'BKK';
$stay_destination_code   = $requested_destination_code ? $requested_destination_code : 'BUD';
$flight_destination_name = isset( $destination_labels[ $flight_destination_code ] ) ? $destination_labels[ $flight_destination_code ] : $flight_destination_code;
$stay_destination_name   = isset( $destination_labels[ $stay_destination_code ] ) ? $destination_labels[ $stay_destination_code ] : $stay_destination_code;
$requested_area           = isset( $_GET['area'] ) ? sanitize_title( wp_unslash( $_GET['area'] ) ) : '';
$requested_area           = strlen( $requested_area ) <= 60 ? $requested_area : '';
$trip_destination_label   = isset( $destination_slug_labels[ $trip_destination ] ) ? $destination_slug_labels[ $trip_destination ] : '';
$insurance_context_ready  = ! $trip_destination || in_array( $trip_destination, array( 'budapest', 'prague', 'vienna', 'athens', 'lisbon' ), true );
$flight_initial_search    = 'TLV' === $requested_origin_code && 'BKK' === $flight_destination_code;
$hotel_initial_search     = 'BUD' === $stay_destination_code;
$package_initial_search   = 'TLV' === $requested_origin_code && 'BUD' === $stay_destination_code;
$flight_sort              = 'value' === $planning_intent ? 'price' : ( 'easy' === $planning_intent ? 'duration' : 'smart' );
$hotel_sort               = 'value' === $planning_intent ? 'price' : ( in_array( $planning_intent, array( 'easy', 'romantic', 'family' ), true ) ? 'location' : 'smart' );
$package_style_map        = array(
	'smart'     => 'city',
	'value'     => 'value',
	'easy'      => 'comfort',
	'romantic'  => 'romantic',
	'family'    => 'family',
	'adventure' => 'adventure',
	'surprise'  => 'city',
);
$package_style            = $package_style_map[ $planning_intent ];
$flight_direct            = ( isset( $_GET['direct'] ) && in_array( sanitize_key( wp_unslash( $_GET['direct'] ) ), array( '1', 'true' ), true ) ) || 'easy' === $planning_intent;
$requested_max_stops      = isset( $_GET['max_stops'] ) ? min( 3, max( 0, absint( $_GET['max_stops'] ) ) ) : 1;
$requested_adults         = isset( $_GET['adults'] ) && is_string( $_GET['adults'] ) ? min( 6, max( 1, absint( wp_unslash( $_GET['adults'] ) ) ) ) : 2;
$requested_children       = isset( $_GET['children'] ) && is_string( $_GET['children'] ) ? min( 4, max( 0, absint( wp_unslash( $_GET['children'] ) ) ) ) : 0;
$requested_rooms          = isset( $_GET['rooms'] ) && is_string( $_GET['rooms'] ) ? min( 3, max( 1, absint( wp_unslash( $_GET['rooms'] ) ) ) ) : 1;
$agent_budget             = isset( $_GET['budget'] ) ? min( 1600, max( 200, absint( $_GET['budget'] ) ) ) : 0;
$package_budget_total     = $agent_budget ? min( 50000, $agent_budget * 2 ) : 0;
$agent_trip               = isset( $_GET['trip'] ) ? sanitize_key( wp_unslash( $_GET['trip'] ) ) : 'all';
$agent_trip_labels        = array(
	'all'   => __( 'באורך גמיש', 'tra-vel-v2' ),
	'short' => __( 'קצרה', 'tra-vel-v2' ),
	'long'  => __( 'ארוכה', 'tra-vel-v2' ),
);
$agent_trip               = isset( $agent_trip_labels[ $agent_trip ] ) ? $agent_trip : 'all';
$agent_max_duration       = isset( $_GET['max_duration'] ) ? min( 3000, max( 60, absint( $_GET['max_duration'] ) ) ) : 0;
$allow_overnight          = isset( $_GET['allow_overnight'] ) && in_array( sanitize_key( wp_unslash( $_GET['allow_overnight'] ) ), array( '1', 'true' ), true );
$package_route_context    = array();
if ( isset( $_GET['max_stops'] ) ) {
	$package_route_context[] = sprintf( _n( 'עד עצירה אחת', 'עד %s עצירות', $requested_max_stops, 'tra-vel-v2' ), number_format_i18n( $requested_max_stops ) );
}
if ( $agent_max_duration ) {
	$package_route_context[] = sprintf( __( 'עד %s שעות בדרך', 'tra-vel-v2' ), number_format_i18n( (int) ceil( $agent_max_duration / 60 ) ) );
}
if ( $allow_overnight ) {
	$package_route_context[] = __( 'אפשרות לעצירת לילה', 'tra-vel-v2' );
}
$agent_prompt = $is_surprise
	? __( 'חופשה אקזוטית לזוג עד 1,000 דולר. לא משנה לאן. הציעו כיוון וסדרו לי תוכנית לבדיקה.', 'tra-vel-v2' )
	: $experience['prompt'];
if ( $is_ai_planner && $agent_destination && ! $is_surprise ) {
	$agent_destination_label = isset( $destination_slug_labels[ $agent_destination ] ) ? $destination_slug_labels[ $agent_destination ] : __( 'היעד שבחרתי', 'tra-vel-v2' );
	$agent_context_parts     = array( $agent_trip_labels[ $agent_trip ] );
	if ( $agent_budget ) {
		$agent_context_parts[] = sprintf( __( 'עד %s דולר לאדם', 'tra-vel-v2' ), number_format_i18n( $agent_budget ) );
	}
	$agent_context_parts[] = sprintf( _n( 'עד עצירה אחת', 'עד %s עצירות', $requested_max_stops, 'tra-vel-v2' ), number_format_i18n( $requested_max_stops ) );
	if ( $agent_max_duration ) {
		$agent_context_parts[] = sprintf( __( 'עד %s שעות בדרך', 'tra-vel-v2' ), number_format_i18n( (int) ceil( $agent_max_duration / 60 ) ) );
	}
	if ( $allow_overnight ) {
		$agent_context_parts[] = __( 'אפשרות לעצירת לילה', 'tra-vel-v2' );
	}
	if ( null !== $agent_map_latitude && null !== $agent_map_longitude ) {
		$agent_context_parts[] = sprintf(
			/* translators: 1: latitude, 2: longitude. */
			__( 'נקודת המפה שנבחרה %1$.4f, %2$.4f', 'tra-vel-v2' ),
			$agent_map_latitude,
			$agent_map_longitude
		);
	}
	if ( $agent_scope ) {
		$agent_context_parts[] = sprintf(
			/* translators: %s: comma-separated trip planning domains. */
			__( 'לכלול בתוכנית %s', 'tra-vel-v2' ),
			implode( ', ', array_map( static fn( $scope_key ) => $agent_scope_labels[ $scope_key ], $agent_scope ) )
		);
	}
	$agent_prompt = sprintf(
		/* translators: 1: planning intent, 2: destination name, 3: selected planning context. */
		__( 'סדרו לי תוכנית לחופשה %1$s ב%2$s, %3$s. שאלו רק מה שחסר, סמנו מה דורש בדיקה והציעו בדיקה אישית רק אם אבחר בכך. אל תציגו מחיר, זמינות או הזמנה בלי תוצאת ספק נפרדת ומאומתת.', 'tra-vel-v2' ),
		$agent_intents[ $agent_intent ],
		$agent_destination_label,
		implode( ', ', $agent_context_parts )
	);
} elseif ( $is_ai_planner && null !== $agent_map_latitude && null !== $agent_map_longitude && ! $is_surprise ) {
	$map_scope = $agent_scope ? $agent_scope : array_keys( $agent_scope_labels );
	$agent_prompt = sprintf(
		/* translators: 1: latitude, 2: longitude, 3: planning intent, 4: comma-separated planning domains. */
		__( 'התחילו מנקודת המפה %1$.4f, %2$.4f. זהו איתי את האזור בלי להמציא עיר, ואז סדרו תוכנית לחופשה %3$s שכוללת %4$s. שאלו רק מה שחסר והציעו בדיקה אישית רק אם אבחר בכך. אל תציגו מחיר, זמינות או הזמנה בלי תוצאת ספק נפרדת ומאומתת.', 'tra-vel-v2' ),
		$agent_map_latitude,
		$agent_map_longitude,
		$agent_intents[ $agent_intent ],
		implode( ', ', array_map( static fn( $scope_key ) => $agent_scope_labels[ $scope_key ], $map_scope ) )
	);
}
$today             = current_datetime();
$today_date        = $today->format( 'Y-m-d' );
$departure_default = $today->modify( '+30 days' )->format( 'Y-m-d' );
$return_default    = $today->modify( '+44 days' )->format( 'Y-m-d' );
$checkin_default   = $today->modify( '+45 days' )->format( 'Y-m-d' );
$checkout_default  = $today->modify( '+49 days' )->format( 'Y-m-d' );
$package_departure = $today->modify( '+30 days' )->format( 'Y-m-d' );
$package_return    = $today->modify( '+34 days' )->format( 'Y-m-d' );
$insurance_start   = $today->modify( '+30 days' )->format( 'Y-m-d' );
$insurance_end     = $today->modify( '+36 days' )->format( 'Y-m-d' );

$requested_date = static function ( $key, $fallback, $minimum ) {
	if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
		return $fallback;
	}

	$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
		return $fallback;
	}

	if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || $value < $minimum ) {
		return $fallback;
	}

	return $value;
};
$date_after = static function ( $date, $days ) {
	return ( new DateTimeImmutable( $date, wp_timezone() ) )->modify( "+{$days} days" )->format( 'Y-m-d' );
};

$departure_default = $requested_date( 'departure_date', $departure_default, $today_date );
$return_minimum    = $is_ai_planner && 'insurance' === $requested_product ? $departure_default : $date_after( $departure_default, 1 );
$return_default    = $requested_date( 'return_date', $date_after( $departure_default, 14 ), $return_minimum );
$checkin_default   = $requested_date( 'checkin', $checkin_default, $today_date );
$checkout_default  = $requested_date( 'checkout', $date_after( $checkin_default, 4 ), $date_after( $checkin_default, 1 ) );
$package_departure = $requested_date( 'departure_date', $package_departure, $today_date );
$package_return    = $requested_date( 'return_date', $date_after( $package_departure, 4 ), $date_after( $package_departure, 1 ) );
$insurance_start   = $requested_date( 'start_date', $insurance_start, $today_date );
$insurance_end     = $requested_date( 'end_date', $date_after( $insurance_start, 6 ), $insurance_start );

if ( $is_ai_planner ) {
	$product_labels     = array(
		'package'   => __( 'טיסה ומלון', 'tra-vel-v2' ),
		'packages'  => __( 'חבילה', 'tra-vel-v2' ),
		'flights'   => __( 'טיסות', 'tra-vel-v2' ),
		'hotels'    => __( 'מלונות', 'tra-vel-v2' ),
		'insurance' => __( 'ביטוח נסיעות', 'tra-vel-v2' ),
	);
	$carried_context    = array();
	if ( isset( $product_labels[ $requested_product ] ) ) {
		$carried_context[] = $product_labels[ $requested_product ];
	}
	if ( isset( $_GET['departure_date'], $_GET['return_date'] ) && is_string( $_GET['departure_date'] ) && is_string( $_GET['return_date'] ) ) {
		$carried_context[] = sprintf( __( 'מ-%1$s עד %2$s', 'tra-vel-v2' ), $departure_default, $return_default );
	}
	if ( isset( $_GET['adults'] ) || isset( $_GET['children'] ) ) {
		$carried_context[] = sprintf( _n( 'נוסע אחד', '%s נוסעים', $requested_adults + $requested_children, 'tra-vel-v2' ), number_format_i18n( $requested_adults + $requested_children ) );
	}
	if ( isset( $_GET['rooms'] ) ) {
		$carried_context[] = sprintf( _n( 'חדר אחד', '%s חדרים', $requested_rooms, 'tra-vel-v2' ), number_format_i18n( $requested_rooms ) );
	}
	if ( isset( $_GET['origin'] ) && is_string( $_GET['origin'] ) ) {
		$carried_context[] = sprintf( __( 'יציאה מ-%s', 'tra-vel-v2' ), $requested_origin_code );
	}
	if ( $carried_context ) {
		$agent_prompt .= ' ' . sprintf( __( 'פרטי הנסיעה שכבר נבחרו: %s.', 'tra-vel-v2' ), implode( ', ', $carried_context ) );
	}
}

get_header();
?>
<main id="main-content" class="experience-page" data-experience-kind="<?php echo esc_attr( $experience_kind ); ?>">
	<section class="experience-hero">
		<div class="page-width experience-hero-grid">
			<div class="experience-copy"><span class="kicker"><i data-lucide="sparkles"></i><?php echo esc_html( $experience['eyebrow'] ); ?></span><h1><?php echo esc_html( $experience['title'] ); ?></h1><p><?php echo esc_html( $experience['description'] ); ?></p></div>
			<?php if ( $is_flights ) : ?>
			<form class="experience-search flight-search-form" data-flight-search data-auto-search="<?php echo esc_attr( $flight_initial_search ? 'true' : 'false' ); ?>" data-initial-status="<?php echo esc_attr( sprintf( __( '%s כבר מחכה בחיפוש. השלימו תאריכים ונוסעים כדי להשוות אפשרויות מתאימות.', 'tra-vel-v2' ), $flight_destination_name ) ); ?>" aria-label="<?php esc_attr_e( 'חיפוש טיסות', 'tra-vel-v2' ); ?>">
				<div class="flight-airports"><label><?php esc_html_e( 'מאיפה', 'tra-vel-v2' ); ?><input name="origin" value="<?php echo esc_attr( $requested_origin_code ); ?>" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" required><span><bdi dir="auto"><?php echo esc_html( $requested_origin_label ); ?></bdi></span></label><i data-lucide="arrow-left-right"></i><label><?php esc_html_e( 'לאן', 'tra-vel-v2' ); ?><input name="destination" value="<?php echo esc_attr( $flight_destination_code ); ?>" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" required><span><bdi dir="auto"><?php echo esc_html( $flight_destination_name ); ?></bdi></span></label></div>
				<div class="flight-date-grid"><label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?><input type="date" name="departure_date" min="<?php echo esc_attr( $today_date ); ?>" value="<?php echo esc_attr( $departure_default ); ?>" dir="ltr" required></label><label><?php esc_html_e( 'חזרה', 'tra-vel-v2' ); ?><input type="date" name="return_date" min="<?php echo esc_attr( $date_after( $departure_default, 1 ) ); ?>" value="<?php echo esc_attr( $return_default ); ?>" dir="ltr" required></label></div>
				<div class="flight-options-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1" <?php selected( $requested_adults, 1 ); ?>>1</option><option value="2" <?php selected( $requested_adults, 2 ); ?>>2</option><option value="3" <?php selected( $requested_adults, 3 ); ?>>3</option><option value="4" <?php selected( $requested_adults, 4 ); ?>>4</option><option value="5" <?php selected( $requested_adults, 5 ); ?>>5</option><option value="6" <?php selected( $requested_adults, 6 ); ?>>6</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0" <?php selected( $requested_children, 0 ); ?>>0</option><option value="1" <?php selected( $requested_children, 1 ); ?>>1</option><option value="2" <?php selected( $requested_children, 2 ); ?>>2</option><option value="3" <?php selected( $requested_children, 3 ); ?>>3</option><option value="4" <?php selected( $requested_children, 4 ); ?>>4</option></select></label><label><?php esc_html_e( 'מחלקה', 'tra-vel-v2' ); ?><select name="cabin"><option value="economy"><?php esc_html_e( 'תיירים', 'tra-vel-v2' ); ?></option><option value="premium_economy"><?php esc_html_e( 'פרימיום', 'tra-vel-v2' ); ?></option><option value="business"><?php esc_html_e( 'עסקים', 'tra-vel-v2' ); ?></option></select></label><label><?php esc_html_e( 'מיון', 'tra-vel-v2' ); ?><select name="sort"><option value="smart" <?php selected( $flight_sort, 'smart' ); ?>><?php esc_html_e( 'איזון בין מחיר לזמן', 'tra-vel-v2' ); ?></option><option value="price" <?php selected( $flight_sort, 'price' ); ?>><?php esc_html_e( 'עלות מלאה', 'tra-vel-v2' ); ?></option><option value="duration" <?php selected( $flight_sort, 'duration' ); ?>><?php esc_html_e( 'זמן כולל', 'tra-vel-v2' ); ?></option></select></label></div>
				<input type="hidden" name="infants" value="0"><input type="hidden" name="max_stops" value="<?php echo esc_attr( $requested_max_stops ); ?>"><input type="hidden" name="max_duration" value="<?php echo esc_attr( $agent_max_duration ? $agent_max_duration : 3000 ); ?>"><label class="flight-direct"><input type="checkbox" name="direct" value="true" <?php checked( $flight_direct ); ?>><span><?php esc_html_e( 'רק טיסות ישירות', 'tra-vel-v2' ); ?></span></label>
				<button class="experience-submit" type="submit"><span><?php esc_html_e( 'השוו מחיר כולל', 'tra-vel-v2' ); ?></span><i data-lucide="search"></i></button><small><?php esc_html_e( 'מחירי התכנון עוזרים להשוות. המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php elseif ( $is_hotels ) : ?>
			<form class="experience-search hotel-search-form" data-hotel-search data-auto-search="<?php echo esc_attr( $hotel_initial_search ? 'true' : 'false' ); ?>" data-initial-status="<?php echo esc_attr( sprintf( __( '%s כבר מחכה בחיפוש. השלימו תאריכים, נוסעים וחדרים כדי להשוות אזורי לינה.', 'tra-vel-v2' ), $stay_destination_name ) ); ?>" aria-label="<?php esc_attr_e( 'חיפוש מלונות', 'tra-vel-v2' ); ?>">
				<label class="hotel-destination"><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?><span><i data-lucide="map-pin"></i><input name="destination" value="<?php echo esc_attr( $stay_destination_code ); ?>" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" required><b><bdi dir="auto"><?php echo esc_html( $stay_destination_name ); ?></bdi></b></span></label>
				<div class="hotel-date-grid"><label><?php esc_html_e( 'כניסה', 'tra-vel-v2' ); ?><input type="date" name="checkin" min="<?php echo esc_attr( $today_date ); ?>" value="<?php echo esc_attr( $checkin_default ); ?>" dir="ltr" required></label><label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?><input type="date" name="checkout" min="<?php echo esc_attr( $date_after( $checkin_default, 1 ) ); ?>" value="<?php echo esc_attr( $checkout_default ); ?>" dir="ltr" required></label></div>
				<div class="hotel-options-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1" <?php selected( $requested_adults, 1 ); ?>>1</option><option value="2" <?php selected( $requested_adults, 2 ); ?>>2</option><option value="3" <?php selected( $requested_adults, 3 ); ?>>3</option><option value="4" <?php selected( $requested_adults, 4 ); ?>>4</option><option value="5" <?php selected( $requested_adults, 5 ); ?>>5</option><option value="6" <?php selected( $requested_adults, 6 ); ?>>6</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0" <?php selected( $requested_children, 0 ); ?>>0</option><option value="1" <?php selected( $requested_children, 1 ); ?>>1</option><option value="2" <?php selected( $requested_children, 2 ); ?>>2</option><option value="3" <?php selected( $requested_children, 3 ); ?>>3</option><option value="4" <?php selected( $requested_children, 4 ); ?>>4</option></select></label><label><?php esc_html_e( 'חדרים', 'tra-vel-v2' ); ?><select name="rooms"><option value="1" <?php selected( $requested_rooms, 1 ); ?>>1</option><option value="2" <?php selected( $requested_rooms, 2 ); ?>>2</option><option value="3" <?php selected( $requested_rooms, 3 ); ?>>3</option></select></label><label><?php esc_html_e( 'מיון', 'tra-vel-v2' ); ?><select name="sort"><option value="smart" <?php selected( $hotel_sort, 'smart' ); ?>><?php esc_html_e( 'איזון בין אזור למחיר', 'tra-vel-v2' ); ?></option><option value="price" <?php selected( $hotel_sort, 'price' ); ?>><?php esc_html_e( 'עלות שהייה', 'tra-vel-v2' ); ?></option><option value="location" <?php selected( $hotel_sort, 'location' ); ?>><?php esc_html_e( 'קרוב למסלול', 'tra-vel-v2' ); ?></option><option value="rating" <?php selected( $hotel_sort, 'rating' ); ?>><?php esc_html_e( 'ציון אורחים', 'tra-vel-v2' ); ?></option></select></label></div>
				<div class="hotel-filter-row"><label><input type="checkbox" name="free_cancellation" value="true"><span><?php esc_html_e( 'ביטול חינם', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="breakfast" value="true"><span><?php esc_html_e( 'ארוחת בוקר', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="family" value="true"><span><?php esc_html_e( 'מתאים למשפחה', 'tra-vel-v2' ); ?></span></label></div>
				<input type="hidden" name="area" value="<?php echo esc_attr( $requested_area ); ?>"><input type="hidden" name="min_price" value="0"><input type="hidden" name="max_price" value="2000"><input type="hidden" name="stars" value="0"><input type="hidden" name="limit" value="12">
				<button class="experience-submit" type="submit"><span><?php esc_html_e( 'השוו אזור ומחיר מלא', 'tra-vel-v2' ); ?></span><i data-lucide="search"></i></button><small><?php esc_html_e( 'מחירי התכנון עוזרים לבחור אזור ומלון. המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php elseif ( $is_packages ) : ?>
			<form class="experience-search package-search-form" data-package-search data-auto-search="<?php echo esc_attr( $package_initial_search ? 'true' : 'false' ); ?>" data-initial-status="<?php echo esc_attr( sprintf( __( '%s כבר מחכה בחיפוש. השלימו תאריכים, נוסעים וחדרים כדי להשוות את החופשה המלאה.', 'tra-vel-v2' ), $stay_destination_name ) ); ?>" aria-label="<?php esc_attr_e( 'חיפוש טיסה ומלון', 'tra-vel-v2' ); ?>">
				<?php if ( $package_route_context ) : ?><p class="package-planning-context" data-package-planning-context><i data-lucide="route"></i><span><?php echo esc_html( sprintf( __( 'העדפת הדרך נשמרה: %s. ההתאמה הסופית של הדרך תיבדק מול אפשרויות הטיסה הזמינות.', 'tra-vel-v2' ), implode( ', ', $package_route_context ) ) ); ?></span></p><?php endif; ?>
				<div class="package-route-row"><label><?php esc_html_e( 'מאיפה', 'tra-vel-v2' ); ?><span><input name="origin" value="<?php echo esc_attr( $requested_origin_code ); ?>" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" required><b><bdi dir="auto"><?php echo esc_html( $requested_origin_label ); ?></bdi></b></span></label><i data-lucide="arrow-left"></i><label><?php esc_html_e( 'לאן', 'tra-vel-v2' ); ?><span><input name="destination" value="<?php echo esc_attr( $stay_destination_code ); ?>" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" required><b><bdi dir="auto"><?php echo esc_html( $stay_destination_name ); ?></bdi></b></span></label></div>
				<div class="package-date-grid"><label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?><input type="date" name="departure_date" min="<?php echo esc_attr( $today_date ); ?>" value="<?php echo esc_attr( $package_departure ); ?>" dir="ltr" required></label><label><?php esc_html_e( 'חזרה', 'tra-vel-v2' ); ?><input type="date" name="return_date" min="<?php echo esc_attr( $date_after( $package_departure, 1 ) ); ?>" value="<?php echo esc_attr( $package_return ); ?>" dir="ltr" required></label></div>
				<div class="package-party-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1" <?php selected( $requested_adults, 1 ); ?>>1</option><option value="2" <?php selected( $requested_adults, 2 ); ?>>2</option><option value="3" <?php selected( $requested_adults, 3 ); ?>>3</option><option value="4" <?php selected( $requested_adults, 4 ); ?>>4</option><option value="5" <?php selected( $requested_adults, 5 ); ?>>5</option><option value="6" <?php selected( $requested_adults, 6 ); ?>>6</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0" <?php selected( $requested_children, 0 ); ?>>0</option><option value="1" <?php selected( $requested_children, 1 ); ?>>1</option><option value="2" <?php selected( $requested_children, 2 ); ?>>2</option><option value="3" <?php selected( $requested_children, 3 ); ?>>3</option><option value="4" <?php selected( $requested_children, 4 ); ?>>4</option></select></label><label><?php esc_html_e( 'חדרים', 'tra-vel-v2' ); ?><select name="rooms"><option value="1" <?php selected( $requested_rooms, 1 ); ?>>1</option><option value="2" <?php selected( $requested_rooms, 2 ); ?>>2</option><option value="3" <?php selected( $requested_rooms, 3 ); ?>>3</option></select></label><label><?php esc_html_e( 'סגנון', 'tra-vel-v2' ); ?><select name="trip_style"><option value="city" <?php selected( $package_style, 'city' ); ?>><?php esc_html_e( 'חופשה עירונית', 'tra-vel-v2' ); ?></option><option value="value" <?php selected( $package_style, 'value' ); ?>><?php esc_html_e( 'מקסימום תמורה', 'tra-vel-v2' ); ?></option><option value="comfort" <?php selected( $package_style, 'comfort' ); ?>><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></option><option value="family" <?php selected( $package_style, 'family' ); ?>><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></option><option value="romantic" <?php selected( $package_style, 'romantic' ); ?>><?php esc_html_e( 'זוגי', 'tra-vel-v2' ); ?></option><option value="adventure" <?php selected( $package_style, 'adventure' ); ?>><?php esc_html_e( 'הרפתקה', 'tra-vel-v2' ); ?></option></select></label></div>
				<div class="package-control-grid"><label><?php esc_html_e( 'מידע לבדיקת ביטוח', 'tra-vel-v2' ); ?><select name="insurance_tier"><option value="auto"><?php esc_html_e( 'הציגו שאלות בסיס לבדיקה', 'tra-vel-v2' ); ?></option><option value="none"><?php esc_html_e( 'אל תציגו מידע על ביטוח', 'tra-vel-v2' ); ?></option><option value="essential"><?php esc_html_e( 'מידע בסיסי לבדיקה', 'tra-vel-v2' ); ?></option><option value="assisted"><?php esc_html_e( 'מידע על שירותי סיוע', 'tra-vel-v2' ); ?></option><option value="extended"><?php esc_html_e( 'מידע על הרחבות לבדיקה', 'tra-vel-v2' ); ?></option></select></label><label><?php esc_html_e( 'תקציב מרבי לכל ההרכב', 'tra-vel-v2' ); ?><select name="max_total"><option value="0" <?php selected( $package_budget_total, 0 ); ?>><?php esc_html_e( 'ללא מגבלה', 'tra-vel-v2' ); ?></option><?php if ( $package_budget_total && ! in_array( $package_budget_total, array( 1200, 1500, 1800, 2500 ), true ) ) : ?><option value="<?php echo esc_attr( $package_budget_total ); ?>" selected><?php echo esc_html( sprintf( __( 'עד $%s לפי התקציב מהמפה', 'tra-vel-v2' ), number_format_i18n( $package_budget_total ) ) ); ?></option><?php endif; ?><option value="1200" <?php selected( $package_budget_total, 1200 ); ?>>$1,200</option><option value="1500" <?php selected( $package_budget_total, 1500 ); ?>>$1,500</option><option value="1800" <?php selected( $package_budget_total, 1800 ); ?>>$1,800</option><option value="2500" <?php selected( $package_budget_total, 2500 ); ?>>$2,500</option></select></label><label><?php esc_html_e( 'מיון', 'tra-vel-v2' ); ?><select name="sort"><option value="smart"><?php esc_html_e( 'איזון בין מחיר לנוחות', 'tra-vel-v2' ); ?></option><option value="price"><?php esc_html_e( 'עלות מלאה', 'tra-vel-v2' ); ?></option><option value="comfort"><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></option><option value="flexibility"><?php esc_html_e( 'גמישות', 'tra-vel-v2' ); ?></option><option value="location"><?php esc_html_e( 'קרבה למסלול', 'tra-vel-v2' ); ?></option></select></label></div>
				<fieldset class="package-inclusion-grid"><legend><?php esc_html_e( 'מה לכלול בחישוב?', 'tra-vel-v2' ); ?></legend><label><input type="checkbox" name="baggage" value="true" checked><span><i data-lucide="luggage"></i><?php esc_html_e( 'כבודה', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="breakfast" value="true" checked><span><i data-lucide="coffee"></i><?php esc_html_e( 'ארוחת בוקר', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="free_cancellation" value="true" checked><span><i data-lucide="calendar-check"></i><?php esc_html_e( 'ביטול גמיש', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="transfers" value="true" checked><span><i data-lucide="car-front"></i><?php esc_html_e( 'העברות', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="direct_only" value="true" <?php checked( $flight_direct ); ?>><span><i data-lucide="plane-takeoff"></i><?php esc_html_e( 'טיסה ישירה', 'tra-vel-v2' ); ?></span></label></fieldset>
				<input type="hidden" name="limit" value="12"><button class="experience-submit" type="submit"><span><?php esc_html_e( 'הציגו חלופות לטיסה ומלון', 'tra-vel-v2' ); ?></span><i data-lucide="search"></i></button><small><?php esc_html_e( 'מחירי התכנון כוללים את הרכיבים המוצגים. המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה. חיסכון יוצג רק מול בסיס השוואה מאומת.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php elseif ( $is_insurance ) : ?>
			<form class="experience-search insurance-quote-form" data-insurance-quote data-context-supported="<?php echo esc_attr( $insurance_context_ready ? 'true' : 'false' ); ?>" data-assisted-url="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>" data-trip-destination="<?php echo esc_attr( $trip_destination ); ?>" data-initial-status="<?php echo esc_attr( $trip_destination_label ? ( $insurance_context_ready ? sprintf( __( 'היעד %s נשמר. השלימו את פרטי הנסיעה כדי לראות אילו נושאים חשוב לברר מול מבטח מורשה.', 'tra-vel-v2' ), $trip_destination_label ) : sprintf( __( 'היעד %s נשמר. השלימו את פרטי הנסיעה ומתכנן החופשה יסדר את הנושאים לבדיקה אישית, בלי להציג פוליסה או הצעת מחיר.', 'tra-vel-v2' ), $trip_destination_label ) ) : '' ); ?>" aria-label="<?php esc_attr_e( 'מידע לבדיקת כיסוי בביטוח נסיעות', 'tra-vel-v2' ); ?>">
				<div class="insurance-destination-row"><label><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?><?php if ( $insurance_context_ready ) : ?><select name="destination"><option value="europe"><?php echo esc_html( $trip_destination_label ? sprintf( __( '%s · אירופה', 'tra-vel-v2' ), $trip_destination_label ) : __( 'אירופה', 'tra-vel-v2' ) ); ?></option></select><?php else : ?><select disabled><option><?php echo esc_html( sprintf( __( '%s · פרטים לבדיקה אישית', 'tra-vel-v2' ), $trip_destination_label ) ); ?></option></select><?php endif; ?></label><label><?php esc_html_e( 'סוג נסיעה', 'tra-vel-v2' ); ?><select name="trip_type"><option value="city_break"><?php esc_html_e( 'חופשה עירונית', 'tra-vel-v2' ); ?></option><option value="family" <?php selected( $planning_intent, 'family' ); ?>><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></option><option value="multi_city"><?php esc_html_e( 'מסלול רב־יעדי', 'tra-vel-v2' ); ?></option><option value="adventure" <?php selected( $planning_intent, 'adventure' ); ?>><?php esc_html_e( 'פעילות ואתגר', 'tra-vel-v2' ); ?></option><option value="winter"><?php esc_html_e( 'ספורט חורף', 'tra-vel-v2' ); ?></option><option value="business"><?php esc_html_e( 'עסקים', 'tra-vel-v2' ); ?></option></select></label></div>
				<div class="insurance-date-grid"><label><?php esc_html_e( 'תחילת הנסיעה', 'tra-vel-v2' ); ?><input type="date" name="start_date" min="<?php echo esc_attr( $today_date ); ?>" value="<?php echo esc_attr( $insurance_start ); ?>" dir="ltr" required></label><label><?php esc_html_e( 'סיום הנסיעה', 'tra-vel-v2' ); ?><input type="date" name="end_date" min="<?php echo esc_attr( $insurance_start ); ?>" value="<?php echo esc_attr( $insurance_end ); ?>" dir="ltr" required></label></div>
				<div class="insurance-traveler-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1" <?php selected( $requested_adults, 1 ); ?>>1</option><option value="2" <?php selected( $requested_adults, 2 ); ?>>2</option><option value="3" <?php selected( $requested_adults, 3 ); ?>>3</option><option value="4" <?php selected( $requested_adults, 4 ); ?>>4</option><option value="5" <?php selected( $requested_adults, 5 ); ?>>5</option><option value="6" <?php selected( $requested_adults, 6 ); ?>>6</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0" <?php selected( $requested_children, 0 ); ?>>0</option><option value="1" <?php selected( $requested_children, 1 ); ?>>1</option><option value="2" <?php selected( $requested_children, 2 ); ?>>2</option><option value="3" <?php selected( $requested_children, 3 ); ?>>3</option><option value="4" <?php selected( $requested_children, 4 ); ?>>4</option></select></label><label><?php esc_html_e( 'גיל המבוגר או המבוגרת ביותר', 'tra-vel-v2' ); ?><input type="number" name="oldest_age" min="18" max="100" value="35" dir="ltr" required></label><input type="hidden" name="sort" value="smart"></div>
				<fieldset class="insurance-addon-grid"><legend><?php esc_html_e( 'אילו נושאים לכלול בבדיקה?', 'tra-vel-v2' ); ?></legend><label><input type="checkbox" name="baggage" value="true"><span><i data-lucide="luggage"></i><?php esc_html_e( 'כבודה', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="cancellation" value="true"><span><i data-lucide="calendar-x"></i><?php esc_html_e( 'ביטול וקיצור', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="adventure_sports" value="true"><span><i data-lucide="mountain"></i><?php esc_html_e( 'ספורט אתגרי', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="winter_sports" value="true"><span><i data-lucide="snowflake"></i><?php esc_html_e( 'ספורט חורף', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="electronics" value="true"><span><i data-lucide="smartphone"></i><?php esc_html_e( 'אלקטרוניקה', 'tra-vel-v2' ); ?></span></label></fieldset>
				<input type="hidden" name="limit" value="12"><button class="experience-submit" type="submit"><span><?php echo esc_html( $insurance_context_ready ? __( 'בדקו מה חשוב לנסיעה', 'tra-vel-v2' ) : __( 'סדרו פרטים לבדיקה אישית', 'tra-vel-v2' ) ); ?></span><i data-lucide="file-search"></i></button><small data-insurance-planning-boundary><?php esc_html_e( 'המידע מארגן נקודות לבדיקה בלבד. הוא אינו ייעוץ, המלצה, דירוג או הצעת ביטוח. הפוליסה, החיתום והמחיר אצל המבטח הם הקובעים.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php elseif ( $is_ai_planner ) : ?>
			<div class="ai-planner-column">
				<form class="experience-search ai-conversation-entry" action="" method="post" data-ai-conversation-entry data-agent-entry-form>
					<label class="ai-conversation-prompt"><span><?php echo esc_html( $is_surprise ? __( 'ספרו תקציב, תאריכים ואווירה במשפט אחד', 'tra-vel-v2' ) : __( 'ספרו לנו על החופשה במילים שלכם', 'tra-vel-v2' ) ); ?></span><textarea name="prompt" rows="5" minlength="4" maxlength="4000" autocomplete="off" data-agent-prompt required><?php echo esc_textarea( $agent_prompt ); ?></textarea></label>
					<input type="hidden" name="mode" value="<?php echo esc_attr( $is_surprise ? 'surprise' : 'agent' ); ?>">
					<label class="ai-transcript-confirmation" data-ai-transcript-confirmation hidden><input type="checkbox" data-ai-transcript-confirmed><span><?php esc_html_e( 'בדקתי ואישרתי שזה התמלול שהתכוונתי לשלוח', 'tra-vel-v2' ); ?></span></label>
					<div class="ai-conversation-actions"><button class="ai-voice-button" type="button" data-ai-voice aria-pressed="false" aria-label="<?php esc_attr_e( 'התחילו להקליט את בקשת החופשה', 'tra-vel-v2' ); ?>"><i data-lucide="mic"></i><span><?php esc_html_e( 'דברו במקום להקליד', 'tra-vel-v2' ); ?></span></button><button class="experience-submit" type="submit" data-agent-submit disabled><span><?php echo esc_html( $is_surprise ? __( 'תפתיעו אותי בתוכנית', 'tra-vel-v2' ) : __( 'סדרו לי תוכנית', 'tra-vel-v2' ) ); ?></span><i data-lucide="sparkles"></i></button></div>
					<p class="ai-voice-status" data-ai-voice-status role="status"><?php esc_html_e( 'קבלו תחילה תוכנית מסודרת שאפשר לערוך. אחר כך תוכלו לבקש בדיקה אישית ממומחה. בשלב הזה לא מתבצעים חיפוש ספקים, חיוב או הזמנה. אל תזינו פרטי דרכון, תשלום או מידע רפואי.', 'tra-vel-v2' ); ?></p>
					<noscript><p class="agent-noscript"><?php esc_html_e( 'מתכנן החופשה דורש JavaScript. הבקשה לא נשלחה.', 'tra-vel-v2' ); ?></p></noscript>
				</form>

				<section class="agent-run-workbench" data-agent-workbench aria-labelledby="agent-run-title" hidden>
					<header class="agent-run-head">
						<div><span class="eyebrow"><?php esc_html_e( 'החופשה שלכם', 'tra-vel-v2' ); ?></span><h2 id="agent-run-title" tabindex="-1"><?php esc_html_e( 'מה כבר קרה ומה הצעד הבא', 'tra-vel-v2' ); ?></h2></div>
						<p class="agent-run-state" data-agent-run-state role="status" aria-live="polite"></p>
					</header>
					<section class="agent-journey" data-agent-journey data-state="idle" aria-labelledby="agent-journey-title">
						<header class="agent-journey-head">
							<div><span><?php esc_html_e( 'התקדמות החופשה', 'tra-vel-v2' ); ?></span><h3 id="agent-journey-title"><?php esc_html_e( 'כך החופשה מתקדמת', 'tra-vel-v2' ); ?></h3></div>
							<div class="agent-journey-meter" data-agent-journey-meter role="progressbar" aria-label="<?php esc_attr_e( 'שלבים שהושלמו בפועל', 'tra-vel-v2' ); ?>" aria-valuemin="0" aria-valuemax="3" aria-valuenow="0" aria-valuetext="<?php esc_attr_e( 'עדיין לא הושלם שלב', 'tra-vel-v2' ); ?>"><strong data-agent-journey-count><bdi dir="ltr">0/3</bdi></strong><span><i data-agent-journey-fill style="--agent-journey-progress:0%"></i></span></div>
						</header>
						<p class="agent-journey-truth"><?php esc_html_e( 'השלימו את התוכנית ואז בחרו אם להעביר אותה לבדיקה אישית. מחיר או אפשרות מסחרית יוצגו רק לאחר קבלת עדכון מאומת מהגורם שבדק אותם.', 'tra-vel-v2' ); ?></p>
						<ol class="agent-journey-steps" aria-label="<?php esc_attr_e( 'שלבי התכנון והבדיקה האישית', 'tra-vel-v2' ); ?>">
							<li class="is-pending" data-agent-journey-step="intake"><i data-lucide="message-circle-more"></i><span><strong><?php esc_html_e( 'שליחת הבקשה', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'טרם נשלחה', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="understanding"><i data-lucide="brain-circuit"></i><span><strong><?php esc_html_e( 'סיכום הפרטים', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'טרם נוצר', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="readiness"><i data-lucide="list-checks"></i><span><strong><?php esc_html_e( 'התוכנית המובנית', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'ממתינה לפרטים', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="supplier_search" hidden><i data-lucide="search-check"></i><span><strong><?php esc_html_e( 'בדיקת ספק נפרדת', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'לא התחילה', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="proposal" hidden><i data-lucide="route"></i><span><strong><?php esc_html_e( 'תוצאות מאומתות', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'טרם התקבלו מספק', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="approval" hidden><i data-lucide="badge-check"></i><span><strong><?php esc_html_e( 'אישור להמשך', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'לא נדרש', 'tra-vel-v2' ); ?></small></span></li>
							<li class="is-pending" data-agent-journey-step="execution" hidden><i data-lucide="external-link"></i><span><strong><?php esc_html_e( 'פעולת ספק', 'tra-vel-v2' ); ?></strong><small data-agent-journey-step-state><?php esc_html_e( 'לא בוצעה', 'tra-vel-v2' ); ?></small></span></li>
						</ol>
						<div class="agent-journey-next"><i data-lucide="move-left"></i><span><small><?php esc_html_e( 'הצעד הבא', 'tra-vel-v2' ); ?></small><strong data-agent-journey-next role="status" aria-live="polite"><?php esc_html_e( 'שלחו בקשה במילים שלכם כדי להתחיל.', 'tra-vel-v2' ); ?></strong></span></div>
						<section class="agent-scope-board" data-agent-journey-scopes aria-labelledby="agent-scope-title">
							<header><div><span><?php esc_html_e( 'מוצרים ושירותים לחופשה', 'tra-vel-v2' ); ?></span><h4 id="agent-scope-title"><?php esc_html_e( 'מה תרצו לכלול', 'tra-vel-v2' ); ?></h4></div><small data-agent-scope-count><?php esc_html_e( 'ממתינים לבקשה שלכם', 'tra-vel-v2' ); ?></small></header>
							<ul>
								<li data-agent-scope="flights"><i data-lucide="plane"></i><span><strong><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחרו', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="accommodation"><i data-lucide="hotel"></i><span><strong><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחרה', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="transfers"><i data-lucide="car-front"></i><span><strong><?php esc_html_e( 'העברות', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחרו', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="activities"><i data-lucide="ticket-check"></i><span><strong><?php esc_html_e( 'פעילויות', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחרו', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="dining"><i data-lucide="utensils"></i><span><strong><?php esc_html_e( 'אוכל', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחר', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="insurance"><i data-lucide="shield-check"></i><span><strong><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחר', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="connectivity"><i data-lucide="wifi"></i><span><strong><?php esc_html_e( 'תקשורת', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחרה', 'tra-vel-v2' ); ?></small></span></li>
								<li data-agent-scope="equipment"><i data-lucide="luggage"></i><span><strong><?php esc_html_e( 'ציוד', 'tra-vel-v2' ); ?></strong><small data-agent-scope-state><?php esc_html_e( 'טרם נבחר', 'tra-vel-v2' ); ?></small></span></li>
							</ul>
						</section>
					</section>
					<p class="agent-run-error" data-agent-error role="alert" hidden></p>
					<section class="agent-request-card" data-agent-trip-request hidden>
						<span><?php esc_html_e( 'הבקשה שהובנה', 'tra-vel-v2' ); ?></span>
						<h3 data-agent-request-summary></h3>
						<dl class="agent-request-facts" data-agent-request-facts></dl>
					</section>
					<section class="agent-assumptions" data-agent-assumptions hidden><h3><?php esc_html_e( 'הנחות שצריך לבדוק', 'tra-vel-v2' ); ?></h3><ul data-agent-assumption-list></ul></section>
					<section class="agent-clarifications" data-agent-clarifications hidden aria-labelledby="agent-clarification-title">
						<h3 id="agent-clarification-title"><?php esc_html_e( 'שאלות שחייבות תשובה', 'tra-vel-v2' ); ?></h3>
						<div class="agent-question-list" data-agent-question-list></div>
						<p><?php esc_html_e( 'עד לקבלת תשובה התוכנית נשארת בשלב ההבהרה. מתכנן החופשה אינו מפעיל חיפוש ספקים. אפשר לענות על כל השאלות במשפט חופשי אחד.', 'tra-vel-v2' ); ?></p>
					</section>
					<section class="agent-revision-composer" data-agent-revision-composer hidden aria-labelledby="agent-revision-title">
						<div class="agent-revision-heading">
							<div><span><?php esc_html_e( 'אותה תוכנית, שיחה מתקדמת', 'tra-vel-v2' ); ?></span><h3 id="agent-revision-title"><?php esc_html_e( 'ענו או שנו פרט במילים שלכם', 'tra-vel-v2' ); ?></h3></div>
							<span class="agent-revision-badge" data-agent-revision-badge></span>
						</div>
						<p data-agent-revision-help><?php esc_html_e( 'אפשר לעדכן יעד, תקציב, תאריכים, נוסעים או העדפות בלי לפתוח תוכנית חדשה.', 'tra-vel-v2' ); ?></p>
						<form class="agent-revision-form" data-agent-revision-form>
							<label><span><?php esc_html_e( 'מה להוסיף או לשנות?', 'tra-vel-v2' ); ?></span><textarea rows="3" minlength="2" maxlength="4000" autocomplete="off" data-agent-revision-message placeholder="<?php esc_attr_e( 'לדוגמה: יוצאים מתל אביב, התאריכים גמישים והתקציב הוא 1,000 דולר', 'tra-vel-v2' ); ?>" required></textarea></label>
							<div class="agent-revision-actions">
								<button type="submit" data-agent-revision-submit><span><?php esc_html_e( 'עדכנו את התוכנית', 'tra-vel-v2' ); ?></span><i data-lucide="wand-sparkles"></i></button>
								<p data-agent-revision-status role="status" aria-live="polite"><?php esc_html_e( 'העדכון נשמר רק בתוכנית הזאת. הטקסט החופשי אינו נשמר.', 'tra-vel-v2' ); ?></p>
							</div>
						</form>
					</section>
					<section class="agent-event-panel" aria-labelledby="agent-event-title">
						<div class="agent-panel-heading"><h3 id="agent-event-title"><?php esc_html_e( 'עדכונים לתוכנית', 'tra-vel-v2' ); ?></h3><small><?php esc_html_e( 'מוצגים רק עדכונים שאושרו', 'tra-vel-v2' ); ?></small></div>
						<p class="agent-event-empty" data-agent-event-empty><?php esc_html_e( 'העדכון הראשון יופיע כאן לאחר קבלת הבקשה.', 'tra-vel-v2' ); ?></p>
						<ol class="agent-event-log" data-agent-event-log role="log" aria-live="polite" aria-relevant="additions text"></ol>
					</section>
					<aside class="agent-supplier-state" data-agent-supplier-state role="status" aria-live="polite" hidden></aside>
					<section class="agent-quote-case" data-agent-quote-case aria-labelledby="agent-quote-case-title" hidden>
						<header class="agent-quote-case-head">
							<div><span><?php esc_html_e( 'בדיקה אישית', 'tra-vel-v2' ); ?></span><h3 id="agent-quote-case-title"><?php esc_html_e( 'שלחו את התוכנית למומחה', 'tra-vel-v2' ); ?></h3></div>
							<span class="agent-quote-status" data-quote-case-status role="status" aria-live="polite"></span>
						</header>
						<p class="agent-quote-error" data-quote-case-error role="alert" hidden></p>
						<div class="agent-quote-create" data-quote-case-create hidden>
							<div class="agent-quote-create-copy"><i data-lucide="badge-check"></i><div><strong><?php esc_html_e( 'הבקשה מוכנה לשליחה', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'צוות Tra-Vel יקבל את פרטי הנסיעה ואת הבחירות האחרונות שאישרתם. הטקסט החופשי, פרטי דרכון, תשלום ומידע רפואי אינם מועברים.', 'tra-vel-v2' ); ?></p></div></div>
							<label class="agent-quote-consent"><input type="checkbox" data-quote-case-consent><span><?php esc_html_e( 'אני מאשר או מאשרת לשלוח את התוכנית לבדיקה אישית ולשמור את הבקשה והיסטוריית הטיפול עד 90 יום', 'tra-vel-v2' ); ?></span></label>
							<div class="agent-quote-create-actions"><button type="button" data-quote-case-create-button disabled><span><?php esc_html_e( 'שלחו לבדיקה אישית', 'tra-vel-v2' ); ?></span><i data-lucide="send"></i></button><p data-quote-case-create-status role="status" aria-live="polite"><?php esc_html_e( 'שליחת התוכנית אינה חיוב או הזמנה.', 'tra-vel-v2' ); ?></p></div>
						</div>
						<div class="agent-quote-active" data-quote-case-active hidden>
							<div class="agent-quote-identity"><div><small><?php esc_html_e( 'מספר בקשה', 'tra-vel-v2' ); ?></small><strong data-quote-case-reference tabindex="-1" dir="ltr"></strong></div><time data-quote-case-updated dir="ltr"></time></div>
							<p class="agent-quote-summary" data-quote-case-summary></p>
							<ol class="agent-quote-progress" data-quote-case-progress aria-label="<?php esc_attr_e( 'התקדמות הבדיקה האישית', 'tra-vel-v2' ); ?>">
								<li data-quote-step="0"><span>1</span><div><strong><?php esc_html_e( 'פרטי הנסיעה', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'הבחירות ששלחתם נשמרו', 'tra-vel-v2' ); ?></small></div></li>
								<li data-quote-step="1"><span>2</span><div><strong><?php esc_html_e( 'בתור לבדיקה', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'הבקשה התקבלה', 'tra-vel-v2' ); ?></small></div></li>
								<li data-quote-step="2"><span>3</span><div><strong><?php esc_html_e( 'עיון אנושי', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בודקים אילו פרטים חסרים', 'tra-vel-v2' ); ?></small></div></li>
								<li data-quote-step="3"><span>4</span><div><strong><?php esc_html_e( 'מוכנים להמשך', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'ממשיכים בשיחה מאומתת', 'tra-vel-v2' ); ?></small></div></li>
							</ol>
							<aside class="agent-quote-next"><i data-lucide="route"></i><div><small><?php esc_html_e( 'הפעולה הבאה', 'tra-vel-v2' ); ?></small><strong data-quote-case-next-action></strong></div></aside>
							<section class="agent-quote-history" aria-labelledby="agent-quote-history-title"><div><h4 id="agent-quote-history-title"><?php esc_html_e( 'היסטוריית הבקשה', 'tra-vel-v2' ); ?></h4><small><?php esc_html_e( 'רק עדכונים שאושרו', 'tra-vel-v2' ); ?></small></div><p data-quote-case-event-empty><?php esc_html_e( 'העדכון הראשון יופיע לאחר פתיחת הבקשה.', 'tra-vel-v2' ); ?></p><ol data-quote-case-events role="log" aria-live="polite" aria-relevant="additions text"></ol></section>
							<div class="agent-quote-actions"><button class="agent-quote-whatsapp" type="button" data-quote-case-handoff><i data-lucide="message-circle"></i><span><?php esc_html_e( 'המשיכו בוואטסאפ', 'tra-vel-v2' ); ?></span></button><button class="agent-quote-cancel" type="button" data-quote-case-cancel><?php esc_html_e( 'בטלו את הבקשה', 'tra-vel-v2' ); ?></button><p data-quote-case-action-status role="status" aria-live="polite"></p></div>
							<small class="agent-quote-safety"><i data-lucide="shield-check"></i><?php esc_html_e( 'המחיר, הזמינות והתנאים יאומתו לפני כל החלטת רכישה. אין חיוב או הזמנה מתוך המסך הזה.', 'tra-vel-v2' ); ?></small>
						</div>
					</section>
				</section>
			</div>
			<?php else : ?>
			<form class="experience-search" action="<?php echo esc_url( $map_url ); ?>" method="get"><label><?php esc_html_e( 'מה אתם מחפשים?', 'tra-vel-v2' ); ?><input name="q" value="<?php echo esc_attr( $experience['prompt'] ); ?>"></label><div class="experience-search-row"><label><?php esc_html_e( 'מתי?', 'tra-vel-v2' ); ?><input name="when" value="<?php esc_attr_e( 'גמיש', 'tra-vel-v2' ); ?>"></label><label><?php esc_html_e( 'תקציב?', 'tra-vel-v2' ); ?><input name="budget" value="<?php esc_attr_e( 'עוד לא החלטנו', 'tra-vel-v2' ); ?>"></label></div><div class="experience-chips"><?php foreach ( $experience['chips'] as $chip ) : ?><button type="button"><?php echo esc_html( $chip ); ?></button><?php endforeach; ?></div><button class="experience-submit" type="submit"><?php echo esc_html( $experience['action'] ); ?><i data-lucide="arrow-left"></i></button><small><?php esc_html_e( 'הטופס פותח את מפת החופשות. הוא אינו בודק מחיר, זמינות או הזמנה.', 'tra-vel-v2' ); ?></small></form>
			<?php endif; ?>
			<?php if ( $is_flights || $is_hotels || $is_packages || $is_insurance ) : ?>
			<a class="experience-personal-check" data-experience-personal-check data-planner-base="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>" data-product="<?php echo esc_attr( $experience_kind ); ?>" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>" hidden><i data-lucide="message-circle-more"></i><span><?php esc_html_e( 'לא מצאתם התאמה מדויקת? שלחו את אותם פרטים לבדיקה אישית', 'tra-vel-v2' ); ?></span></a>
			<?php endif; ?>
		</div>
	</section>
	<?php if ( $is_flights || $is_hotels || $is_packages || $is_insurance ) : ?>
	<div class="experience-data-disclosure page-width"><?php tra_vel_v2_demo_disclosure(); ?></div>
	<?php endif; ?>
	<section class="experience-proof page-width" aria-label="<?php esc_attr_e( 'עקרונות ההשוואה', 'tra-vel-v2' ); ?>"><?php foreach ( $experience['proof'] as $label => $value ) : ?><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div><?php endforeach; ?></section>
	<nav class="experience-destination-index page-width" data-experience-destination-index aria-label="<?php esc_attr_e( 'בחרו יעד להמשך', 'tra-vel-v2' ); ?>">
		<div><strong><?php esc_html_e( 'לאן תרצו להמשיך?', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'הבחירה ממלאת את היעד במסך הקיים. מחיר וזמינות יוצגו רק לאחר בדיקה מתאימה.', 'tra-vel-v2' ); ?></small></div>
		<ul>
			<?php foreach ( $experience_destination_links as $destination_slug => $destination_link ) : ?>
				<li><a data-experience-destination-link data-destination="<?php echo esc_attr( $destination_slug ); ?>" href="<?php echo esc_url( $destination_link['url'] ); ?>"<?php echo $experience_context_supported && $experience_selected_destination === $destination_slug ? ' aria-current="page"' : ''; ?>><span><?php echo esc_html( $destination_link['city'] ); ?></span><small><bdi dir="ltr"><?php echo esc_html( $destination_link['airport'] ); ?></bdi></small></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php if ( $is_flights ) : ?>
	<section class="section flight-search-section" aria-labelledby="flight-results-title"><div class="page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'השוואת טיסות', 'tra-vel-v2' ); ?></span><h2 id="flight-results-title"><?php esc_html_e( 'השוו דרך, זמן ועלות מלאה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'טיסה, כבודה, מושב, עצירות ועלויות המשך מוצגים יחד כדי להבין את ההבדלים.', 'tra-vel-v2' ); ?></p></div><p class="flight-search-status" data-flight-status role="status"><?php esc_html_e( 'מכין השוואה...', 'tra-vel-v2' ); ?></p></div><div class="flight-results-grid" data-flight-results aria-live="polite"></div><div class="flight-cost-legend"><strong><?php esc_html_e( 'איך לקרוא את ההשוואה?', 'tra-vel-v2' ); ?></strong><span><i data-lucide="plane"></i><?php esc_html_e( 'מחיר הטיסה והתוספות לפי מקור וזמן בדיקה', 'tra-vel-v2' ); ?></span><span><i data-lucide="calculator"></i><?php esc_html_e( 'אומדן נפרד לעלויות ההמשך', 'tra-vel-v2' ); ?></span><span><i data-lucide="shield-alert"></i><?php esc_html_e( 'סיכון מוצג כשיש כרטיסים נפרדים', 'tra-vel-v2' ); ?></span></div></div></section>
	<?php elseif ( $is_hotels ) : ?>
	<section class="section hotel-search-section" aria-labelledby="hotel-results-title">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'איפה לישון', 'tra-vel-v2' ); ?></span><h2 id="hotel-results-title"><?php esc_html_e( 'בוחרים קודם אזור. אחר כך מלון.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'השוו מחיר, זמן למסלול, תחבורה, תנאים והתאמה לסגנון החופשה על מפה אחת.', 'tra-vel-v2' ); ?></p></div><p class="hotel-search-status" data-hotel-status role="status"><?php esc_html_e( 'מכין השוואת אזורים...', 'tra-vel-v2' ); ?></p></div>
			<div class="hotel-discovery-layout">
				<div class="hotel-map-column">
					<?php if ( $hotel_initial_search ) : ?>
					<div class="hotel-area-map" data-hotel-area-map aria-label="<?php esc_attr_e( 'מפת אזורי לינה בבודפשט', 'tra-vel-v2' ); ?>"><span class="hotel-river" aria-hidden="true"></span><span class="hotel-map-label label-buda">BUDA</span><span class="hotel-map-label label-pest">PEST</span><div data-hotel-map-pins></div></div>
					<article class="hotel-area-detail" data-hotel-area-detail><small><?php esc_html_e( 'האזור שנבחר לתצוגה', 'tra-vel-v2' ); ?></small><h3 data-hotel-area-name><?php esc_html_e( 'טוען אזורים...', 'tra-vel-v2' ); ?></h3><p data-hotel-area-profile></p><div data-hotel-area-tags></div><span data-hotel-area-tradeoff></span><button type="button" data-hotel-area-reset><?php esc_html_e( 'הציגו את כל האזורים', 'tra-vel-v2' ); ?></button></article>
					<?php else : ?>
					<article class="hotel-area-detail hotel-area-context-pending" data-hotel-area-context-pending role="status">
						<small><?php esc_html_e( 'היעד נשמר', 'tra-vel-v2' ); ?></small>
						<h3><?php echo esc_html( sprintf( __( 'מתכננים לינה ב%s', 'tra-vel-v2' ), $stay_destination_name ) ); ?></h3>
						<p><?php esc_html_e( 'השלימו תאריכים, נוסעים וחדרים. אם לא תימצא התאמה מדויקת, תוכלו להעביר את אותם פרטים לבדיקה אישית.', 'tra-vel-v2' ); ?></p>
					</article>
					<?php endif; ?>
				</div>
				<div class="hotel-results-grid" data-hotel-results aria-live="polite"></div>
			</div>
			<div class="hotel-cost-legend"><strong><?php esc_html_e( 'מה נכלל בהשוואה?', 'tra-vel-v2' ); ?></strong><span><i data-lucide="bed-double"></i><?php esc_html_e( 'חדר, מסים ועמלות לכל השהייה', 'tra-vel-v2' ); ?></span><span><i data-lucide="route"></i><?php esc_html_e( 'זמן אמיתי למסלול ולתחבורה', 'tra-vel-v2' ); ?></span><span><i data-lucide="badge-check"></i><?php esc_html_e( 'ביטול, תשלום והתאמה למשפחה', 'tra-vel-v2' ); ?></span></div>
		</div>
	</section>
	<?php elseif ( $is_packages ) : ?>
	<section class="section package-composer-section" aria-labelledby="package-results-title">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></span><h2 id="package-results-title"><?php esc_html_e( 'ראו מה כלול וכמה עולה כל החופשה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'השוו טיסה, מלון, כבודה, ביטול והעברות לכל הנוסעים. מידע לבירור בביטוח מוצג בנפרד ואינו פוליסה.', 'tra-vel-v2' ); ?></p></div><p class="package-search-status" data-package-status role="status"><?php esc_html_e( 'מכין חלופות...', 'tra-vel-v2' ); ?></p></div>
			<div class="package-trust-alert"><i data-lucide="scan-search"></i><div><strong><?php esc_html_e( 'כך בודקים אם החבילה באמת משתלמת', 'tra-vel-v2' ); ?></strong><span><?php esc_html_e( 'השוו את מחיר הרכיבים למחיר המשולב. חיסכון יוצג רק כששני המחירים התקבלו מהספק ואפשר להשוות ביניהם.', 'tra-vel-v2' ); ?></span></div></div>
			<div class="package-composer-layout">
				<div class="package-map-column">
					<div class="package-journey-map" data-package-map aria-label="<?php echo esc_attr( sprintf( __( 'מפת טיסה ומלון מ%1$s ל%2$s', 'tra-vel-v2' ), $requested_origin_label, $stay_destination_name ) ); ?>"><span class="package-map-grid" aria-hidden="true"></span><span class="package-route-line" aria-hidden="true"></span><span class="package-map-point point-origin"><b><bdi dir="ltr"><?php echo esc_html( $requested_origin_code ); ?></bdi></b><small><?php echo esc_html( $requested_origin_label ); ?></small></span><span class="package-map-point point-destination"><b><bdi dir="ltr"><?php echo esc_html( $stay_destination_code ); ?></bdi></b><small><?php echo esc_html( $stay_destination_name ); ?></small></span><div data-package-map-pins></div></div>
					<article class="package-map-detail" data-package-map-detail><small><?php esc_html_e( 'החלופה המוצגת', 'tra-vel-v2' ); ?></small><h3 data-package-map-title><?php esc_html_e( 'טוען פרטי חלופה...', 'tra-vel-v2' ); ?></h3><p data-package-map-route></p><div class="package-map-kpis"><span><b data-package-map-total><?php esc_html_e( 'טרם נקבע', 'tra-vel-v2' ); ?></b><?php esc_html_e( 'לכל ההרכב', 'tra-vel-v2' ); ?></span><span><b data-package-map-nights><?php esc_html_e( 'טרם נקבע', 'tra-vel-v2' ); ?></b><?php esc_html_e( 'לילות', 'tra-vel-v2' ); ?></span><span><b data-package-map-score><?php esc_html_e( 'טרם נקבע', 'tra-vel-v2' ); ?></b><?php esc_html_e( 'התאמה', 'tra-vel-v2' ); ?></span></div><button type="button" data-package-map-reset><?php esc_html_e( 'הציגו את כל החלופות', 'tra-vel-v2' ); ?></button></article>
				</div>
				<div class="package-results-grid" data-package-results aria-live="polite"></div>
			</div>
			<div class="package-total-legend"><strong><?php esc_html_e( 'מה נכנס לסכום?', 'tra-vel-v2' ); ?></strong><span><i data-lucide="plane"></i><?php esc_html_e( 'טיסה וכבודה לפי הבחירה', 'tra-vel-v2' ); ?></span><span><i data-lucide="hotel"></i><?php esc_html_e( 'כל הלילות, המסים והעמלות', 'tra-vel-v2' ); ?></span><span><i data-lucide="shield-check"></i><?php esc_html_e( 'ביטוח כהמחשה נפרדת ולא כפוליסה', 'tra-vel-v2' ); ?></span><span><i data-lucide="car-front"></i><?php esc_html_e( 'העברה ותוספות שביקשתם', 'tra-vel-v2' ); ?></span></div>
		</div>
	</section>
	<?php elseif ( $is_insurance ) : ?>
	<section class="section insurance-quote-section" aria-labelledby="insurance-results-title">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></span><h2 id="insurance-results-title"><?php esc_html_e( 'בדקו אילו סעיפים דורשים בירור.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'המידע מרכז גבולות, השתתפות עצמית, שירות, הרחבות וחריגים שכדאי לבדוק בפוליסה ומול המבטח.', 'tra-vel-v2' ); ?></p></div><p class="insurance-quote-status" data-insurance-status role="status"><?php esc_html_e( 'מכין מידע לבדיקה...', 'tra-vel-v2' ); ?></p></div>
			<div class="insurance-decision-alert"><i data-lucide="shield-alert"></i><div><strong><?php esc_html_e( 'הפוליסה היא המקור הקובע', 'tra-vel-v2' ); ?></strong><span><?php esc_html_e( 'אין כאן הבטחת כיסוי או אישור תביעה. מצב רפואי, הריון ופעילויות מסוימות דורשים בירור, הצהרה ולעיתים חיתום.', 'tra-vel-v2' ); ?></span></div></div>
			<div class="insurance-decision-layout">
				<div class="insurance-map-column">
					<div class="insurance-risk-map" data-insurance-risk-map aria-label="<?php esc_attr_e( 'מפת פרטי הנסיעה הרלוונטיים לבדיקת ביטוח', 'tra-vel-v2' ); ?>"><span class="insurance-map-continent" aria-hidden="true"></span><span class="insurance-map-label"><bdi dir="ltr">WORLD</bdi></span><div data-insurance-risk-pins></div></div>
					<article class="insurance-risk-detail"><small><?php esc_html_e( 'פרטי נסיעה לדוגמה', 'tra-vel-v2' ); ?></small><h3 data-insurance-risk-title><?php esc_html_e( 'טוען מידע לבדיקה...', 'tra-vel-v2' ); ?></h3><p data-insurance-risk-note></p><div data-insurance-risk-addons></div><button type="button" data-insurance-risk-reset><?php esc_html_e( 'חזרה לחופשה עירונית', 'tra-vel-v2' ); ?></button></article>
				</div>
				<div class="insurance-plan-grid" data-insurance-results aria-live="polite"></div>
			</div>
			<div class="insurance-policy-note"><i data-lucide="file-check-2"></i><div><strong data-insurance-policy-title><?php esc_html_e( 'לפני שבוחרים ביטוח', 'tra-vel-v2' ); ?></strong><span data-insurance-policy-note><?php esc_html_e( 'קראו את נוסח הפוליסה, דף פרטי הביטוח, רשימת ההרחבות, החריגים ותהליך התביעה של המבטח. המידע כאן אינו מחליף אותם.', 'tra-vel-v2' ); ?></span></div></div>
		</div>
	</section>
	<?php endif; ?>
	<section class="commercial-assurance page-width" aria-label="<?php esc_attr_e( 'בדיקה אישית עם מומחה', 'tra-vel-v2' ); ?>">
		<i data-lucide="message-circle-more"></i>
		<div><strong><?php esc_html_e( 'רוצים שמומחה יבדוק את התוכנית?', 'tra-vel-v2' ); ?></strong><span><?php esc_html_e( 'פתחו תוכנית, השלימו את הפרטים ושלחו אותה לבדיקה אישית. שליחת התוכנית אינה חיוב או הזמנה; מחיר וזמינות יוצגו רק לאחר בדיקה מתועדת.', 'tra-vel-v2' ); ?></span></div>
		<small><i data-lucide="shield-check"></i><?php esc_html_e( 'אין לשלוח פרטי דרכון, תשלום או מידע רפואי בצ׳אט', 'tra-vel-v2' ); ?></small>
	</section>
	<section class="section page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'שלוש דרכים להחליט', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'רואים את ההבדלים לפני שבוחרים', 'tra-vel-v2' ); ?></h2></div><a class="text-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div><div class="experience-card-grid"><?php foreach ( $experience['cards'] as $index => $card ) : ?><article class="experience-card"><span class="experience-card-icon"><i data-lucide="<?php echo esc_attr( $card[0] ); ?>"></i></span><small><bdi dir="ltr">0<?php echo esc_html( (string) ( $index + 1 ) ); ?></bdi> · <?php echo esc_html( $card[1] ); ?></small><h3><?php echo esc_html( $card[2] ); ?></h3><p><?php echo esc_html( $card[3] ); ?></p><a href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו במפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></article><?php endforeach; ?></div></section>
	<section class="section dark experience-map-section">
		<div class="page-width experience-map-grid">
			<div><span class="kicker"><i data-lucide="earth"></i><?php esc_html_e( 'מפת החופשות', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'ראו איפה נמצאות האפשרויות ומה מקבלים בכל תקציב.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'שנו תאריך, תקציב, אזור או גמישות. מחירי התכנון מתעדכנים עם הבחירות, והמחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.', 'tra-vel-v2' ); ?></p><ul><li><?php esc_html_e( 'תקציב מלא לטיסה, לינה ותוספות', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'זמן, נוחות ורמת סיכון', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'הצעד הבא לכל אפשרות', 'tra-vel-v2' ); ?></li></ul><a class="button-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו במפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div>
			<div class="experience-globe-column">
				<div class="experience-globe" data-experience-decision-map data-context-requested="<?php echo $experience_context_requested ? 'true' : 'false'; ?>" data-context-supported="<?php echo $experience_context_supported ? 'true' : 'false'; ?>" data-selected-destination="<?php echo $experience_context_supported ? esc_attr( $experience_selected_destination ) : ''; ?>" aria-label="<?php echo esc_attr( $experience_context_supported ? __( 'מפת חופשות. האפשרות הראשונה תואמת ליעד שבחרתם, ואפשר להשוות אליה כיוונים נוספים.', 'tra-vel-v2' ) : __( 'מפת רעיונות לדוגמה. היעד שהוזן אינו מוחלף משום שעדיין אין לו נקודת מפה מאומתת.', 'tra-vel-v2' ) ); ?>"><span class="origin-point"></span><span class="route-curve curve-one"></span><span class="route-curve curve-two"></span><?php foreach ( $experience_destination_options as $option_index => $destination_slug ) : $destination_option = $experience_destination_catalog[ $destination_slug ]; $is_selected_option = $experience_context_supported && 0 === $option_index; ?><button type="button" class="experience-pin pin-<?php echo esc_attr( array( 'one', 'two', 'three' )[ $option_index ] ); ?><?php echo $is_selected_option ? ' is-active' : ''; ?>" data-experience-destination="<?php echo esc_attr( $destination_slug ); ?>" data-experience-title="<?php echo esc_attr( $destination_option['title'] ); ?>" data-experience-copy="<?php echo esc_attr( $destination_option['copy'] ); ?>" aria-pressed="<?php echo $is_selected_option ? 'true' : 'false'; ?>"><?php echo esc_html( $destination_option['label'] ); ?></button><?php endforeach; ?></div>
				<article class="experience-globe-detail" data-experience-decision-detail><small><?php echo esc_html( $experience_context_supported ? __( 'האפשרות המוצגת', 'tra-vel-v2' ) : __( 'היעד שלכם נשמר', 'tra-vel-v2' ) ); ?></small><strong data-experience-selection-title><?php echo esc_html( $experience_selected_content['title'] ); ?></strong><span data-experience-selection-copy><?php echo esc_html( $experience_selected_content['copy'] ); ?></span><a data-experience-selection-link href="<?php echo esc_url( $experience_context_supported ? add_query_arg( 'destination', $experience_selected_destination, $map_url ) : $map_url ); ?>"><?php echo esc_html( $experience_context_supported ? __( 'פתחו את היעד במפה המלאה', 'tra-vel-v2' ) : __( 'פתחו את מפת החופשות', 'tra-vel-v2' ) ); ?><i data-lucide="arrow-left"></i></a></article>
			</div>
		</div>
	</section>
	<section class="section page-width experience-next"><div><span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'סדרו את פרטי החופשה במקום אחד.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'מתכנן החופשה יכין סיכום שאפשר לערוך ולשלוח לבדיקה אישית. מחיר, זמינות או אפשרות רכישה יוצגו רק לאחר בדיקה מתועדת.', 'tra-vel-v2' ); ?></p></div><a class="button-link dark-button" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'סדרו לי תוכנית', 'tra-vel-v2' ); ?><i data-lucide="sparkles"></i></a></section>
</main>
<?php get_footer(); ?>
