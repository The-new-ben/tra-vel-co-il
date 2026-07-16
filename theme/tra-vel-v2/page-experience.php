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
		'eyebrow'     => 'Tra-Vel Flights',
		'title'       => 'לא רק הטיסה הזולה. הדרך הנכונה להגיע.',
		'description' => 'משווים מחיר כולל, כבודה, זמן מדלת לדלת, קונקשן, גמישות וסיכון — ומציגים את החלופות על המפה.',
		'prompt'      => 'תל אביב ← לא משנה לאן',
		'action'      => 'השוו טיסות',
		'chips'       => array( 'ישיר בלבד', 'כבודה כלולה', 'עד עצירה אחת', 'תאריכים גמישים' ),
		'proof'       => array( 'מחיר כולל' => 'כבודה ושינוי בפנים', 'מסלול' => 'ישיר מול קונקשן', 'גמישות' => 'טווח תאריכים חכם', 'שקיפות' => 'למה לבחור בכל חלופה' ),
		'cards'       => array(
			array( 'plane-takeoff', 'ישיר', 'הכי פשוט ונוח', 'זמן קצר יותר וסיכון נמוך — בדרך כלל במחיר גבוה יותר.' ),
			array( 'route', 'קונקשן חכם', 'האיזון המומלץ', 'כרטיס אחד, עצירה סבירה וחיסכון שמצדיק את תוספת הזמן.' ),
			array( 'piggy-bank', 'מסלול יצירתי', 'המחיר הנמוך', 'כרטיסים נפרדים או עצירת לילה — עם הסבר מלא על הסיכון.' ),
		),
	),
	'hotels' => array(
		'eyebrow'     => 'Tra-Vel Stays',
		'title'       => 'המלון הוא חלק מהמסלול. לא רק חדר.',
		'description' => 'בוחרים אזור לפי החוויה, זמן הנסיעה והתקציב; אחר כך משווים נכסים, חדרים, ביטול ומה באמת כלול.',
		'prompt'      => 'יעד, שכונה או סגנון חופשה',
		'action'      => 'מצאו מקום לישון',
		'chips'       => array( 'ביטול גמיש', 'ארוחת בוקר', 'משפחות', 'ליד תחבורה' ),
		'proof'       => array( 'מיקום' => 'זמן אמיתי למסלול', 'חדר' => 'מה בדיוק מזמינים', 'תנאים' => 'ביטול ותשלום', 'עלות' => 'מסים ותוספות בפנים' ),
		'cards'       => array(
			array( 'map-pin-house', 'מרכז וקצב', 'להיות בתוך העניינים', 'בחירה למי שמעדיף ללכת ברגל ולחסוך זמן תחבורה.' ),
			array( 'trees', 'שקט ומרחב', 'יותר מלון באותו תקציב', 'מתאים לשהייה ארוכה, למשפחות ולמי שמעדיף קצב רגוע.' ),
			array( 'waves', 'נופש', 'המלון הוא היעד', 'בריכה, חוף, ספא ושירותים שמצדיקים זמן רב יותר בנכס.' ),
		),
	),
	'travel-insurance' => array(
		'eyebrow'     => 'Tra-Vel Insurance',
		'title'       => 'ביטוח שמתאים למסלול. לא רק למחיר.',
		'description' => 'מתאימים כיסוי ליעד, לאורך הנסיעה, למצב רפואי, לציוד ולפעילויות — ומסבירים את ההבדלים לפני רכישה.',
		'prompt'      => 'לאן נוסעים ומה עושים?',
		'action'      => 'השוו כיסויים',
		'chips'       => array( 'מצב רפואי', 'כבודה וציוד', 'ספורט', 'ביטול וקיצור' ),
		'proof'       => array( 'רפואי' => 'גבולות ותנאים', 'כבודה' => 'ציוד וחריגים', 'פעילות' => 'כיסוי מתאים', 'שירות' => 'איך מפעילים בזמן אמת' ),
		'cards'       => array(
			array( 'heart-pulse', 'בריאות', 'הכיסוי המרכזי', 'הוצאות רפואיות, תרופות, אשפוז ופינוי — לפי תנאי הפוליסה.' ),
			array( 'luggage', 'כבודה וציוד', 'מה חשוב לכם בדרך', 'מזוודה, מחשב, טלפון וציוד ייעודי עם מגבלות ברורות.' ),
			array( 'shield-check', 'שינויים', 'כשהתוכנית משתנה', 'ביטול, קיצור נסיעה ועיכובים — בלי להסתיר חריגים.' ),
		),
	),
	'ai-planner' => array(
		'eyebrow'     => 'Tra-Vel AI Planner',
		'title'       => 'כתבו איך אתם רוצים להרגיש. אנחנו נבנה את הדרך.',
		'description' => 'המתכנן הופך שפה טבעית למסלול חזותי, בודק חלופות ומסביר כל החלטה — תקציב, עונה, טיסות, מלונות וביטוח.',
		'prompt'      => 'למשל: 12 יום בתאילנד לזוג, רגוע, עד ₪9,000, בלי יותר מעצירה אחת',
		'action'      => 'בנו לי מסלול',
		'chips'       => array( 'זוג', 'משפחה', 'גמיש בתאריכים', 'טיול ראשון' ),
		'proof'       => array( 'מבין כוונה' => 'לא רק שדות חיפוש', 'מציע חלופות' => 'עם יתרונות וחסרונות', 'מחובר למפה' => 'כל החלטה במקום', 'נשאר בשליטה' => 'אתם מאשרים כל שינוי' ),
		'cards'       => array(
			array( 'messages-square', 'ספרו', 'מטרה, קצב ותקציב', 'מתחילים מהאנשים ומהחוויה, לא מרשימת יעדים מוכנה.' ),
			array( 'wand-sparkles', 'השוו', 'שלוש דרכים אפשריות', 'המערכת בונה מסלול נוח, מאוזן וזול ומציגה את המחיר של כל בחירה.' ),
			array( 'map', 'ערכו', 'הכול על המפה', 'גוררים, מוסיפים, מסירים ומקבלים חישוב חדש של זמן ועלות.' ),
		),
	),
	'destinations' => array(
		'eyebrow'     => 'Tra-Vel Destinations',
		'title'       => 'יעדים שמתחילים בהחלטה. לא ברשימה.',
		'description' => 'מגלים מקומות לפי תקציב, זמן, עונה וסגנון — ואז עוברים למדריך, למסלול ולהשוואה הרלוונטיים.',
		'prompt'      => 'איזו חופשה מתאימה לכם עכשיו?',
		'action'      => 'גלו על המפה',
		'chips'       => array( 'אירופה קצרה', 'אסיה גדולה', 'חוף', 'עיר ואוכל' ),
		'proof'       => array( 'עונה' => 'מתי זה באמת מתאים', 'תקציב' => 'עלות יומית כוללת', 'קצב' => 'כמה להספיק', 'חיבור' => 'מדריך ומחירים יחד' ),
		'cards'       => array(
			array( 'building-2', 'אירופה', 'קרוב, מגוון וחכם', 'ערים, רכבות, אוכל וחופשות קצרות עם זמן טיסה נוח.' ),
			array( 'palmtree', 'אסיה', 'טיול גדול בקצב שלכם', 'מסלולים ארוכים, עונות שונות ושילוב נכון בין אזורים.' ),
			array( 'sunset', 'איים וחופים', 'לבחור לפי העונה', 'מזג אוויר, גישה, מלון ופעילויות — לא רק תמונה יפה.' ),
		),
	),
	'guides' => array(
		'eyebrow'     => 'Tra-Vel Guides',
		'title'       => 'ידע שעוזר להחליט, לתכנן ולהזמין.',
		'description' => 'מדריכי עומק עם מקורות, תאריך בדיקה, נתונים שימושיים וקישורים ישירים למפה ולשלב הבא במסע.',
		'prompt'      => 'חפשו יעד, שאלה או החלטה',
		'action'      => 'חפשו מדריך',
		'chips'       => array( 'טיסות', 'תקציב', 'מסלולים', 'ביטוח' ),
		'proof'       => array( 'עומק' => 'מדריכי דגל 5,000+ מילים', 'מקורות' => 'עובדות עם תאריך', 'מבנה' => 'תשובה לפני פירוט', 'פעולה' => 'כל פרק מוביל לצעד הבא' ),
		'cards'       => array(
			array( 'book-open-text', 'מדריך יעד', 'כל ההחלטות במקום אחד', 'עונה, אזורים, מסלול, תחבורה, מלונות, עלויות וביטוח.' ),
			array( 'circle-help', 'מדריך החלטה', 'תשובה לשאלה אחת', 'השוואה ממוקדת שמסבירה מה עדיף, למי ובאילו תנאים.' ),
			array( 'list-checks', 'כלי תכנון', 'לא לשכוח את החשוב', 'צ׳קליסטים, מחשבונים וטבלאות שאפשר להשתמש בהם בזמן אמת.' ),
		),
	),
);

$experience = isset( $experiences[ $slug ] ) ? $experiences[ $slug ] : $experiences['destinations'];
$map_url    = home_url( '/travel-map/' );
$is_flights = 'flights' === $slug;
$is_hotels  = 'hotels' === $slug;
$today      = current_time( 'timestamp' );
$departure_default = wp_date( 'Y-m-d', strtotime( '+30 days', $today ) );
$return_default    = wp_date( 'Y-m-d', strtotime( '+44 days', $today ) );
$checkin_default   = wp_date( 'Y-m-d', strtotime( '+45 days', $today ) );
$checkout_default  = wp_date( 'Y-m-d', strtotime( '+49 days', $today ) );

get_header();
?>
<main id="main-content" class="experience-page">
	<section class="experience-hero">
		<div class="page-width experience-hero-grid">
			<div class="experience-copy"><span class="kicker"><i data-lucide="sparkles"></i><?php echo esc_html( $experience['eyebrow'] ); ?></span><h1><?php echo esc_html( $experience['title'] ); ?></h1><p><?php echo esc_html( $experience['description'] ); ?></p></div>
			<?php if ( $is_flights ) : ?>
			<form class="experience-search flight-search-form" data-flight-search aria-label="<?php esc_attr_e( 'חיפוש טיסות', 'tra-vel-v2' ); ?>">
				<div class="flight-airports"><label><?php esc_html_e( 'מאיפה', 'tra-vel-v2' ); ?><input name="origin" value="TLV" maxlength="3" pattern="[A-Za-z]{3}" required><span><?php esc_html_e( 'תל אביב', 'tra-vel-v2' ); ?></span></label><i data-lucide="arrow-left-right"></i><label><?php esc_html_e( 'לאן', 'tra-vel-v2' ); ?><input name="destination" value="BKK" maxlength="3" pattern="[A-Za-z]{3}" required><span><?php esc_html_e( 'בנגקוק', 'tra-vel-v2' ); ?></span></label></div>
				<div class="flight-date-grid"><label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?><input type="date" name="departure_date" min="<?php echo esc_attr( wp_date( 'Y-m-d', $today ) ); ?>" value="<?php echo esc_attr( $departure_default ); ?>" required></label><label><?php esc_html_e( 'חזרה', 'tra-vel-v2' ); ?><input type="date" name="return_date" min="<?php echo esc_attr( $departure_default ); ?>" value="<?php echo esc_attr( $return_default ); ?>" required></label></div>
				<div class="flight-options-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></label><label><?php esc_html_e( 'מחלקה', 'tra-vel-v2' ); ?><select name="cabin"><option value="economy"><?php esc_html_e( 'תיירים', 'tra-vel-v2' ); ?></option><option value="premium_economy"><?php esc_html_e( 'פרימיום', 'tra-vel-v2' ); ?></option><option value="business"><?php esc_html_e( 'עסקים', 'tra-vel-v2' ); ?></option></select></label><label><?php esc_html_e( 'מיון', 'tra-vel-v2' ); ?><select name="sort"><option value="smart"><?php esc_html_e( 'בחירה חכמה', 'tra-vel-v2' ); ?></option><option value="price"><?php esc_html_e( 'עלות מלאה', 'tra-vel-v2' ); ?></option><option value="duration"><?php esc_html_e( 'זמן כולל', 'tra-vel-v2' ); ?></option></select></label></div>
				<input type="hidden" name="infants" value="0"><input type="hidden" name="max_stops" value="1"><label class="flight-direct"><input type="checkbox" name="direct" value="true"><span><?php esc_html_e( 'רק טיסות ישירות', 'tra-vel-v2' ); ?></span></label>
				<button class="experience-submit" type="submit"><span><?php esc_html_e( 'השוו מחיר כולל', 'tra-vel-v2' ); ?></span><i data-lucide="search"></i></button><small><?php esc_html_e( 'מוצגים מחירי הדגמה לא־ניתנים להזמנה עד חיבור ספק מסחרי.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php elseif ( $is_hotels ) : ?>
			<form class="experience-search hotel-search-form" data-hotel-search aria-label="<?php esc_attr_e( 'חיפוש מלונות', 'tra-vel-v2' ); ?>">
				<label class="hotel-destination"><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?><span><i data-lucide="map-pin"></i><input name="destination" value="BUD" maxlength="3" pattern="[A-Za-z]{3}" required><b><?php esc_html_e( 'בודפשט', 'tra-vel-v2' ); ?></b></span></label>
				<div class="hotel-date-grid"><label><?php esc_html_e( 'כניסה', 'tra-vel-v2' ); ?><input type="date" name="checkin" min="<?php echo esc_attr( wp_date( 'Y-m-d', $today ) ); ?>" value="<?php echo esc_attr( $checkin_default ); ?>" required></label><label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?><input type="date" name="checkout" min="<?php echo esc_attr( $checkin_default ); ?>" value="<?php echo esc_attr( $checkout_default ); ?>" required></label></div>
				<div class="hotel-options-grid"><label><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?><select name="adults"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option></select></label><label><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?><select name="children"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></label><label><?php esc_html_e( 'חדרים', 'tra-vel-v2' ); ?><select name="rooms"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></label><label><?php esc_html_e( 'מיון', 'tra-vel-v2' ); ?><select name="sort"><option value="smart"><?php esc_html_e( 'התאמה חכמה', 'tra-vel-v2' ); ?></option><option value="price"><?php esc_html_e( 'עלות שהייה', 'tra-vel-v2' ); ?></option><option value="location"><?php esc_html_e( 'קרוב למסלול', 'tra-vel-v2' ); ?></option><option value="rating"><?php esc_html_e( 'ציון אורחים', 'tra-vel-v2' ); ?></option></select></label></div>
				<div class="hotel-filter-row"><label><input type="checkbox" name="free_cancellation" value="true"><span><?php esc_html_e( 'ביטול חינם', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="breakfast" value="true"><span><?php esc_html_e( 'ארוחת בוקר', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="family" value="true"><span><?php esc_html_e( 'מתאים למשפחה', 'tra-vel-v2' ); ?></span></label></div>
				<input type="hidden" name="area" value=""><input type="hidden" name="min_price" value="0"><input type="hidden" name="max_price" value="2000"><input type="hidden" name="stars" value="0"><input type="hidden" name="limit" value="12">
				<button class="experience-submit" type="submit"><span><?php esc_html_e( 'השוו אזור ומחיר מלא', 'tra-vel-v2' ); ?></span><i data-lucide="search"></i></button><small><?php esc_html_e( 'מחירי מלונות לדוגמה בלבד; אין זמינות או הזמנה חיה עד חיבור ספק מסחרי.', 'tra-vel-v2' ); ?></small>
			</form>
			<?php else : ?>
			<form class="experience-search" action="<?php echo esc_url( $map_url ); ?>" method="get"><label><?php esc_html_e( 'מה אתם מחפשים?', 'tra-vel-v2' ); ?><input name="q" value="<?php echo esc_attr( $experience['prompt'] ); ?>"></label><div class="experience-search-row"><label><?php esc_html_e( 'מתי?', 'tra-vel-v2' ); ?><input name="when" value="<?php esc_attr_e( 'גמיש', 'tra-vel-v2' ); ?>"></label><label><?php esc_html_e( 'תקציב?', 'tra-vel-v2' ); ?><input name="budget" value="<?php esc_attr_e( 'עוד לא החלטנו', 'tra-vel-v2' ); ?>"></label></div><div class="experience-chips"><?php foreach ( $experience['chips'] as $chip ) : ?><button type="button"><?php echo esc_html( $chip ); ?></button><?php endforeach; ?></div><button class="experience-submit" type="submit"><?php echo esc_html( $experience['action'] ); ?><i data-lucide="arrow-left"></i></button><small><?php esc_html_e( 'המחירים והזמינות בעמודי ההדגמה אינם נתונים חיים עד חיבור הספקים.', 'tra-vel-v2' ); ?></small></form>
			<?php endif; ?>
		</div>
	</section>
	<section class="experience-proof page-width" aria-label="<?php esc_attr_e( 'עקרונות ההשוואה', 'tra-vel-v2' ); ?>"><?php foreach ( $experience['proof'] as $label => $value ) : ?><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div><?php endforeach; ?></section>
	<?php if ( $is_flights ) : ?>
	<section class="section flight-search-section" aria-labelledby="flight-results-title"><div class="page-width"><div class="section-heading"><div><span class="eyebrow">Tra-Vel Total Cost</span><h2 id="flight-results-title"><?php esc_html_e( 'שלוש דרכים להגיע. מחיר מלא אחד.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'טיסה, כבודה, מושב, מלון, העברות, ביטוח ולינת ביניים — לפני שבוחרים.', 'tra-vel-v2' ); ?></p></div><p class="flight-search-status" data-flight-status role="status"><?php esc_html_e( 'מכין השוואה...', 'tra-vel-v2' ); ?></p></div><div class="flight-results-grid" data-flight-results aria-live="polite"></div><div class="flight-cost-legend"><strong><?php esc_html_e( 'איך לקרוא את המחיר?', 'tra-vel-v2' ); ?></strong><span><i data-lucide="plane"></i><?php esc_html_e( 'מחיר טיסה אמיתי כולל תוספות', 'tra-vel-v2' ); ?></span><span><i data-lucide="calculator"></i><?php esc_html_e( 'עלות מסע משוערת לכל הנסיעה', 'tra-vel-v2' ); ?></span><span><i data-lucide="shield-alert"></i><?php esc_html_e( 'סיכון מוצג כשיש כרטיסים נפרדים', 'tra-vel-v2' ); ?></span></div></div></section>
	<?php elseif ( $is_hotels ) : ?>
	<section class="section hotel-search-section" aria-labelledby="hotel-results-title"><div class="page-width"><div class="section-heading"><div><span class="eyebrow">Tra-Vel Stay Map</span><h2 id="hotel-results-title"><?php esc_html_e( 'בוחרים קודם אזור. אחר כך מלון.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'רואים מחיר מלא, זמן למסלול, תחבורה, תנאים וההתאמה לסגנון הנסיעה על מפה אחת.', 'tra-vel-v2' ); ?></p></div><p class="hotel-search-status" data-hotel-status role="status"><?php esc_html_e( 'מכין השוואת אזורים...', 'tra-vel-v2' ); ?></p></div><div class="hotel-discovery-layout"><div class="hotel-area-map" data-hotel-area-map aria-label="<?php esc_attr_e( 'מפת אזורי לינה בבודפשט', 'tra-vel-v2' ); ?>"><span class="hotel-river" aria-hidden="true"></span><span class="hotel-map-label label-buda">BUDA</span><span class="hotel-map-label label-pest">PEST</span><div data-hotel-map-pins></div><article class="hotel-area-detail" data-hotel-area-detail><small><?php esc_html_e( 'האזור המומלץ למסלול הזה', 'tra-vel-v2' ); ?></small><h3 data-hotel-area-name><?php esc_html_e( 'טוען אזורים...', 'tra-vel-v2' ); ?></h3><p data-hotel-area-profile></p><div data-hotel-area-tags></div><span data-hotel-area-tradeoff></span><button type="button" data-hotel-area-reset><?php esc_html_e( 'הציגו את כל האזורים', 'tra-vel-v2' ); ?></button></article></div><div class="hotel-results-grid" data-hotel-results aria-live="polite"></div></div><div class="hotel-cost-legend"><strong><?php esc_html_e( 'מה נכלל בהשוואה?', 'tra-vel-v2' ); ?></strong><span><i data-lucide="bed-double"></i><?php esc_html_e( 'חדר, מסים ועמלות לכל השהייה', 'tra-vel-v2' ); ?></span><span><i data-lucide="route"></i><?php esc_html_e( 'זמן אמיתי למסלול ולתחבורה', 'tra-vel-v2' ); ?></span><span><i data-lucide="badge-check"></i><?php esc_html_e( 'ביטול, תשלום והתאמה למשפחה', 'tra-vel-v2' ); ?></span></div></div></section>
	<?php endif; ?>
	<section class="section page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'שלוש דרכים להחליט', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'רואים את ההבדלים לפני שבוחרים', 'tra-vel-v2' ); ?></h2></div><a class="text-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו מפה מלאה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div><div class="experience-card-grid"><?php foreach ( $experience['cards'] as $index => $card ) : ?><article class="experience-card"><span class="experience-card-icon"><i data-lucide="<?php echo esc_attr( $card[0] ); ?>"></i></span><small>0<?php echo esc_html( (string) ( $index + 1 ) ); ?> · <?php echo esc_html( $card[1] ); ?></small><h3><?php echo esc_html( $card[2] ); ?></h3><p><?php echo esc_html( $card[3] ); ?></p><a href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'השוו על המפה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></article><?php endforeach; ?></div></section>
	<section class="section dark experience-map-section"><div class="page-width experience-map-grid"><div><span class="kicker"><i data-lucide="earth"></i>Tra-Vel Globe</span><h2><?php esc_html_e( 'כל אפשרות מקבלת מקום, מחיר והסבר.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'המפה מחברת את החיפוש למסלול: רואים מה משתנה כשמזיזים תאריך, תקציב, אזור או רמת גמישות.', 'tra-vel-v2' ); ?></p><ul><li><?php esc_html_e( 'מחיר כולל והקשר היסטורי', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'זמן, נוחות ורמת סיכון', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'הצעה יזומה לצעד הבא', 'tra-vel-v2' ); ?></li></ul><a class="button-link" href="<?php echo esc_url( $map_url ); ?>"><?php echo esc_html( $experience['action'] ); ?><i data-lucide="arrow-left"></i></a></div><div class="experience-globe" aria-label="<?php esc_attr_e( 'מפת מחירי הדגמה', 'tra-vel-v2' ); ?>"><span class="origin-point"></span><span class="route-curve curve-one"></span><span class="route-curve curve-two"></span><span class="experience-pin pin-one">€214</span><span class="experience-pin pin-two">$742</span><span class="experience-pin pin-three">€327</span><article><small><?php esc_html_e( 'הבחירה החכמה עכשיו', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $experience['action'] ); ?></strong><span><?php esc_html_e( 'נתוני הדגמה עד חיבור ספקים', 'tra-vel-v2' ); ?></span></article></div></div></section>
	<section class="section page-width experience-next"><div><span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מתחילים רחב. מסיימים בהחלטה ברורה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'הגרסה הבאה תחבר ספקי טיסות, מלונות וביטוח, חשבון משתמש, שמירות והתראות מחיר. כרגע המבנה והמסע מוכנים לחיבור הנתונים.', 'tra-vel-v2' ); ?></p></div><a class="button-link dark-button" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'פתחו את המתכנן', 'tra-vel-v2' ); ?><i data-lucide="sparkles"></i></a></section>
</main>
<?php get_footer(); ?>
