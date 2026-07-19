<?php
/**
 * Site header and mega navigation.
 *
 * @package TraVelV2
 */

$overlay_header = is_front_page() || is_page_template( 'page-destination.php' ) || is_page_template( 'page-directory.php' ) || is_singular( 'destination' );
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="tra-vel-release" content="<?php echo esc_attr( TRA_VEL_V2_VERSION ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only skip-link" href="#main-content"><?php esc_html_e( 'דילוג לתוכן', 'tra-vel-v2' ); ?></a>
<header class="site-header <?php echo $overlay_header ? 'overlay' : 'on-light'; ?>">
	<div class="header-inner page-width">
		<?php tra_vel_v2_brand(); ?>
		<nav class="main-nav" aria-label="<?php esc_attr_e( 'ניווט ראשי', 'tra-vel-v2' ); ?>">
			<div class="nav-item">
				<button class="nav-trigger" aria-expanded="false" aria-controls="mega-book"><?php esc_html_e( 'משווים ובודקים', 'tra-vel-v2' ); ?> <i data-lucide="chevron-down"></i></button>
				<div class="mega-menu" id="mega-book">
					<div class="mega-column">
						<strong><?php esc_html_e( 'הנסיעה', 'tra-vel-v2' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane"></i><span><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'ישירות, קונקשנים ומסלולים רב־יעדיים', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="hotel"></i><span><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'בדקו מחירים, חדרים ותנאים', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><i data-lucide="package"></i><span><?php esc_html_e( 'טיסה + מלון', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'בדקו טיסה ומלון יחד', 'tra-vel-v2' ); ?></small></span></a>
					</div>
					<div class="mega-column">
						<strong><?php esc_html_e( 'שקט נפשי', 'tra-vel-v2' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><span><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'בדקו מה חשוב להתאים לנסיעה', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( add_query_arg( 'scope', 'transfers', home_url( '/ai-planner/' ) ) ); ?>"><i data-lucide="car-front"></i><span><?php esc_html_e( 'העברות ותחבורה', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'תארו את המסלול וקבלו אפשרויות לבדיקה', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( add_query_arg( 'scope', 'activities', home_url( '/ai-planner/' ) ) ); ?>"><i data-lucide="ticket-check"></i><span><?php esc_html_e( 'אטרקציות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'ספרו מה אתם אוהבים ובנו יום טיול', 'tra-vel-v2' ); ?></small></span></a>
					</div>
					<a class="mega-feature" href="<?php echo esc_url( add_query_arg( 'destination', 'bangkok', home_url( '/travel-map/' ) ) ); ?>"><span><?php esc_html_e( 'מתכננים תאילנד', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'ישיר, קונקשן או עצירת לילה', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בדקו זמן, כבודה, סיכון ופירוט עלויות', 'tra-vel-v2' ); ?></small><b><?php esc_html_e( 'פתחו את תאילנד במפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></b></a>
				</div>
			</div>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'מפת החופשות', 'tra-vel-v2' ); ?></a>
			<div class="nav-item">
				<button class="nav-trigger" aria-expanded="false" aria-controls="mega-discover"><?php esc_html_e( 'מגלים', 'tra-vel-v2' ); ?> <i data-lucide="chevron-down"></i></button>
				<div class="mega-menu" id="mega-discover">
					<div class="mega-column"><strong><?php esc_html_e( 'לפי עולם', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'region', 'asia', home_url( '/destinations/' ) ) ); ?>"><i data-lucide="palmtree"></i><span><?php esc_html_e( 'אסיה', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'תאילנד, יפן, וייטנאם ועוד', 'tra-vel-v2' ); ?></small></span></a><a href="<?php echo esc_url( add_query_arg( 'region', 'europe', home_url( '/destinations/' ) ) ); ?>"><i data-lucide="landmark"></i><span><?php esc_html_e( 'אירופה', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'עיר, תרבות ומסלולים', 'tra-vel-v2' ); ?></small></span></a></div>
					<div class="mega-column"><strong><?php esc_html_e( 'לפי צורך', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'intent', 'family', home_url( '/destinations/' ) ) ); ?>"><i data-lucide="baby"></i><span><?php esc_html_e( 'משפחות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'מלונות, טיסות וקצב נכון', 'tra-vel-v2' ); ?></small></span></a><a href="<?php echo esc_url( add_query_arg( 'intent', 'couples', home_url( '/destinations/' ) ) ); ?>"><i data-lucide="heart"></i><span><?php esc_html_e( 'זוגות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'חופשות קצרות וירח דבש', 'tra-vel-v2' ); ?></small></span></a></div>
					<a class="mega-feature" href="<?php echo esc_url( home_url( '/destinations/thailand/' ) ); ?>"><span><?php esc_html_e( 'מדריך עומק', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'תאילנד 2026: לפני שסוגרים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'עונות, אזורים, עלויות, מסלולים והמלצות', 'tra-vel-v2' ); ?></small><b><?php esc_html_e( 'למדריך', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></b></a>
				</div>
			</div>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'מדריכים', 'tra-vel-v2' ); ?></a>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'מתכנן החופשה', 'tra-vel-v2' ); ?></a>
		</nav>
		<div class="header-actions">
			<a class="round-button" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>" aria-label="<?php esc_attr_e( 'נסיעות שמורות', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></a>
			<a class="round-button account-button" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" aria-label="<?php esc_attr_e( 'החשבון שלי', 'tra-vel-v2' ); ?>"><i data-lucide="user-round"></i></a>
			<a class="header-cta" href="<?php echo esc_url( home_url( '/#search' ) ); ?>"><?php esc_html_e( 'התחילו חיפוש', 'tra-vel-v2' ); ?></a>
			<button class="mobile-menu-button" type="button" aria-expanded="false" aria-controls="mobile-primary-navigation" aria-label="<?php esc_attr_e( 'פתיחת תפריט', 'tra-vel-v2' ); ?>"><i data-lucide="menu"></i></button>
		</div>
		<div class="mobile-drawer" id="mobile-primary-navigation" role="dialog" aria-modal="true" aria-labelledby="mobile-navigation-title" aria-hidden="true" tabindex="-1">
			<div class="mobile-drawer-head">
				<div><small>Tra-Vel</small><strong id="mobile-navigation-title"><?php esc_html_e( 'כל החופשה, במקום אחד', 'tra-vel-v2' ); ?></strong></div>
				<button class="mobile-drawer-close" type="button" aria-label="<?php esc_attr_e( 'סגירת תפריט', 'tra-vel-v2' ); ?>"><i data-lucide="x"></i></button>
			</div>
			<nav class="mobile-drawer-navigation" aria-label="<?php esc_attr_e( 'ניווט ראשי בנייד', 'tra-vel-v2' ); ?>">
			<a class="mobile-drawer-map" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><span><strong><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'יעדים, טיסות, מלונות ומסלולים', 'tra-vel-v2' ); ?></small></span><i data-lucide="arrow-left"></i></a>
			<div class="mobile-nav-section">
				<button class="mobile-nav-disclosure" type="button" aria-expanded="true" aria-controls="mobile-book-links"><span><?php esc_html_e( 'משווים ובודקים', 'tra-vel-v2' ); ?></span><i data-lucide="chevron-down"></i></button>
				<div class="mobile-nav-links is-open" id="mobile-book-links">
					<a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane"></i><span><strong><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בדקו ישיר, קונקשן ותנאים', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="hotel"></i><span><strong><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בדקו אזור, חדר ותנאים', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><i data-lucide="package"></i><span><strong><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בדקו טיסה ומלון יחד', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><span><strong><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בדקו מה חשוב להתאים לנסיעה', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( add_query_arg( 'scope', 'transfers', home_url( '/ai-planner/' ) ) ); ?>"><i data-lucide="car-front"></i><span><strong><?php esc_html_e( 'העברות ותחבורה', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'סדרו את המעברים לפי המסלול', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( add_query_arg( 'scope', 'activities', home_url( '/ai-planner/' ) ) ); ?>"><i data-lucide="ticket-check"></i><span><strong><?php esc_html_e( 'אטרקציות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בנו פעילויות שמתאימות לקצב שלכם', 'tra-vel-v2' ); ?></small></span></a>
				</div>
			</div>
			<div class="mobile-nav-section">
				<button class="mobile-nav-disclosure" type="button" aria-expanded="false" aria-controls="mobile-discover-links"><span><?php esc_html_e( 'מגלים ומתכננים', 'tra-vel-v2' ); ?></span><i data-lucide="chevron-down"></i></button>
				<div class="mobile-nav-links" id="mobile-discover-links">
					<a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="map-pinned"></i><span><strong><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'בחרו לפי תקציב, עונה וסגנון', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><i data-lucide="book-open"></i><span><strong><?php esc_html_e( 'מדריכי עומק', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'המידע שצריך לפני שסוגרים', 'tra-vel-v2' ); ?></small></span></a>
					<a href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><i data-lucide="sparkles"></i><span><strong><?php esc_html_e( 'מתכנן הנסיעות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'הופכים בקשה למסלול ברור', 'tra-vel-v2' ); ?></small></span></a>
				</div>
			</div>
			<div class="mobile-account-grid">
				<a href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><i data-lucide="user-round"></i><span><strong><?php esc_html_e( 'החשבון שלי', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'כניסה והעדפות', 'tra-vel-v2' ); ?></small></span></a>
				<a href="<?php echo esc_url( home_url( '/saved/' ) ); ?>"><i data-lucide="heart"></i><span><strong><?php esc_html_e( 'נסיעות שמורות', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'השוואות ומעקב', 'tra-vel-v2' ); ?></small></span></a>
			</div>
			<a class="mobile-partner-link" href="<?php echo esc_url( home_url( '/partners/' ) ); ?>"><i data-lucide="briefcase-business"></i><?php esc_html_e( 'כניסה לספקים ושותפים', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
			</nav>
		</div>
	</div>
</header>
