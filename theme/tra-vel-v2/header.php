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
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only" href="#main-content"><?php esc_html_e( 'דילוג לתוכן', 'tra-vel-v2' ); ?></a>
<header class="site-header <?php echo $overlay_header ? 'overlay' : 'on-light'; ?>">
	<div class="header-inner page-width">
		<?php tra_vel_v2_brand(); ?>
		<nav class="main-nav" aria-label="<?php esc_attr_e( 'ניווט ראשי', 'tra-vel-v2' ); ?>">
			<div class="nav-item">
				<button class="nav-trigger" aria-expanded="false" aria-controls="mega-book"><?php esc_html_e( 'מזמינים', 'tra-vel-v2' ); ?> <i data-lucide="chevron-down"></i></button>
				<div class="mega-menu" id="mega-book">
					<div class="mega-column">
						<strong><?php esc_html_e( 'הנסיעה', 'tra-vel-v2' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane"></i><span><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'ישירות, קונקשנים ו־Multi-city', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="hotel"></i><span><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'השוואה לפי המחיר הכולל', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><i data-lucide="package"></i><span><?php esc_html_e( 'טיסה + מלון', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'חבילות דינמיות במקום אחד', 'tra-vel-v2' ); ?></small></span></a>
					</div>
					<div class="mega-column">
						<strong><?php esc_html_e( 'שקט נפשי', 'tra-vel-v2' ); ?></strong>
						<a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><span><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'כיסוי שמותאם למסלול', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/cars-transfers/' ) ); ?>"><i data-lucide="car-front"></i><span><?php esc_html_e( 'רכב והעברות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'מהנחיתה עד המלון', 'tra-vel-v2' ); ?></small></span></a>
						<a href="<?php echo esc_url( home_url( '/activities/' ) ); ?>"><i data-lucide="ticket-check"></i><span><?php esc_html_e( 'אטרקציות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'כרטיסים וסיורים מקומיים', 'tra-vel-v2' ); ?></small></span></a>
					</div>
					<a class="mega-feature" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><span><?php esc_html_e( 'המסלול החכם של השבוע', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'תאילנד: ישיר, קונקשן או עצירת לילה', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'השוו זמן, כבודה, סיכון ועלות כוללת', 'tra-vel-v2' ); ?></small><b><?php esc_html_e( 'פתחו במפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></b></a>
				</div>
			</div>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'מפת המחירים', 'tra-vel-v2' ); ?></a>
			<div class="nav-item">
				<button class="nav-trigger" aria-expanded="false" aria-controls="mega-discover"><?php esc_html_e( 'מגלים', 'tra-vel-v2' ); ?> <i data-lucide="chevron-down"></i></button>
				<div class="mega-menu" id="mega-discover">
					<div class="mega-column"><strong><?php esc_html_e( 'לפי עולם', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/destinations/asia/' ) ); ?>"><i data-lucide="palmtree"></i><span><?php esc_html_e( 'אסיה', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'תאילנד, יפן, וייטנאם ועוד', 'tra-vel-v2' ); ?></small></span></a><a href="<?php echo esc_url( home_url( '/destinations/europe/' ) ); ?>"><i data-lucide="landmark"></i><span><?php esc_html_e( 'אירופה', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'עיר, תרבות ומסלולים', 'tra-vel-v2' ); ?></small></span></a></div>
					<div class="mega-column"><strong><?php esc_html_e( 'לפי צורך', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/travel-with-kids/' ) ); ?>"><i data-lucide="baby"></i><span><?php esc_html_e( 'משפחות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'מלונות, טיסות וקצב נכון', 'tra-vel-v2' ); ?></small></span></a><a href="<?php echo esc_url( home_url( '/couples/' ) ); ?>"><i data-lucide="heart"></i><span><?php esc_html_e( 'זוגות', 'tra-vel-v2' ); ?><small><?php esc_html_e( 'חופשות קצרות וירח דבש', 'tra-vel-v2' ); ?></small></span></a></div>
					<a class="mega-feature" href="<?php echo esc_url( home_url( '/thailand/' ) ); ?>"><span><?php esc_html_e( 'מדריך עומק', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'תאילנד 2026: לפני שסוגרים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'עונות, אזורים, עלויות, מסלולים והמלצות', 'tra-vel-v2' ); ?></small><b><?php esc_html_e( 'למדריך', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></b></a>
				</div>
			</div>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'מדריכים', 'tra-vel-v2' ); ?></a>
			<a class="nav-link" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'מתכנן AI', 'tra-vel-v2' ); ?></a>
		</nav>
		<div class="header-actions"><a class="round-button" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>" aria-label="<?php esc_attr_e( 'שמורים', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></a><a class="header-cta" href="<?php echo esc_url( home_url( '/#search' ) ); ?>"><?php esc_html_e( 'מצאו לי חופשה', 'tra-vel-v2' ); ?></a><button class="mobile-menu-button" aria-expanded="false" aria-label="<?php esc_attr_e( 'פתיחת תפריט', 'tra-vel-v2' ); ?>"><i data-lucide="menu"></i></button></div>
		<div class="mobile-drawer"><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'חבילות טיסה + מלון', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'מפת המחירים החכמה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><?php esc_html_e( 'יעדים ומדריכים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></a></div>
	</div>
</header>
