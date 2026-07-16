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

get_header();
?>
<main id="main-content" class="experience-page">
	<section class="experience-hero">
		<div class="page-width experience-hero-grid">
			<div class="experience-copy"><span class="kicker"><i data-lucide="sparkles"></i><?php echo esc_html( $experience['eyebrow'] ); ?></span><h1><?php echo esc_html( $experience['title'] ); ?></h1><p><?php echo esc_html( $experience['description'] ); ?></p></div>
			<form class="experience-search" action="<?php echo esc_url( $map_url ); ?>" method="get"><label><?php esc_html_e( 'מה אתם מחפשים?', 'tra-vel-v2' ); ?><input name="q" value="<?php echo esc_attr( $experience['prompt'] ); ?>"></label><div class="experience-search-row"><label><?php esc_html_e( 'מתי?', 'tra-vel-v2' ); ?><input name="when" value="<?php esc_attr_e( 'גמיש', 'tra-vel-v2' ); ?>"></label><label><?php esc_html_e( 'תקציב?', 'tra-vel-v2' ); ?><input name="budget" value="<?php esc_attr_e( 'עוד לא החלטנו', 'tra-vel-v2' ); ?>"></label></div><div class="experience-chips"><?php foreach ( $experience['chips'] as $chip ) : ?><button type="button"><?php echo esc_html( $chip ); ?></button><?php endforeach; ?></div><button class="experience-submit" type="submit"><?php echo esc_html( $experience['action'] ); ?><i data-lucide="arrow-left"></i></button><small><?php esc_html_e( 'המחירים והזמינות בעמודי ההדגמה אינם נתונים חיים עד חיבור הספקים.', 'tra-vel-v2' ); ?></small></form>
		</div>
	</section>
	<section class="experience-proof page-width" aria-label="<?php esc_attr_e( 'עקרונות ההשוואה', 'tra-vel-v2' ); ?>"><?php foreach ( $experience['proof'] as $label => $value ) : ?><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div><?php endforeach; ?></section>
	<section class="section page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'שלוש דרכים להחליט', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'רואים את ההבדלים לפני שבוחרים', 'tra-vel-v2' ); ?></h2></div><a class="text-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו מפה מלאה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div><div class="experience-card-grid"><?php foreach ( $experience['cards'] as $index => $card ) : ?><article class="experience-card"><span class="experience-card-icon"><i data-lucide="<?php echo esc_attr( $card[0] ); ?>"></i></span><small>0<?php echo esc_html( (string) ( $index + 1 ) ); ?> · <?php echo esc_html( $card[1] ); ?></small><h3><?php echo esc_html( $card[2] ); ?></h3><p><?php echo esc_html( $card[3] ); ?></p><a href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'השוו על המפה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></article><?php endforeach; ?></div></section>
	<section class="section dark experience-map-section"><div class="page-width experience-map-grid"><div><span class="kicker"><i data-lucide="earth"></i>Tra-Vel Globe</span><h2><?php esc_html_e( 'כל אפשרות מקבלת מקום, מחיר והסבר.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'המפה מחברת את החיפוש למסלול: רואים מה משתנה כשמזיזים תאריך, תקציב, אזור או רמת גמישות.', 'tra-vel-v2' ); ?></p><ul><li><?php esc_html_e( 'מחיר כולל והקשר היסטורי', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'זמן, נוחות ורמת סיכון', 'tra-vel-v2' ); ?></li><li><?php esc_html_e( 'הצעה יזומה לצעד הבא', 'tra-vel-v2' ); ?></li></ul><a class="button-link" href="<?php echo esc_url( $map_url ); ?>"><?php echo esc_html( $experience['action'] ); ?><i data-lucide="arrow-left"></i></a></div><div class="experience-globe" aria-label="<?php esc_attr_e( 'מפת מחירי הדגמה', 'tra-vel-v2' ); ?>"><span class="origin-point"></span><span class="route-curve curve-one"></span><span class="route-curve curve-two"></span><span class="experience-pin pin-one">€214</span><span class="experience-pin pin-two">$742</span><span class="experience-pin pin-three">€327</span><article><small><?php esc_html_e( 'הבחירה החכמה עכשיו', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $experience['action'] ); ?></strong><span><?php esc_html_e( 'נתוני הדגמה עד חיבור ספקים', 'tra-vel-v2' ); ?></span></article></div></div></section>
	<section class="section page-width experience-next"><div><span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מתחילים רחב. מסיימים בהחלטה ברורה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'הגרסה הבאה תחבר ספקי טיסות, מלונות וביטוח, חשבון משתמש, שמירות והתראות מחיר. כרגע המבנה והמסע מוכנים לחיבור הנתונים.', 'tra-vel-v2' ); ?></p></div><a class="button-link dark-button" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'פתחו את המתכנן', 'tra-vel-v2' ); ?><i data-lucide="sparkles"></i></a></section>
</main>
<?php get_footer(); ?>
