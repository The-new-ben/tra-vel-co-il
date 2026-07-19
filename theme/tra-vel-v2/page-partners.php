<?php
/**
 * Template Name: Tra-Vel Partner Center
 * Template Post Type: page
 *
 * @package TraVelV2
 */

get_header();
$can_access = tra_vel_v2_user_can_access_supplier_portal();
$admin_mail = sanitize_email( get_option( 'admin_email' ) );
?>
<main id="main-content" class="partner-page" data-tra-vel-page="partners">
	<section class="partner-hero">
		<div class="page-width partner-hero-grid">
			<div>
				<span class="eyebrow">Tra-Vel Partners</span>
				<h1><?php esc_html_e( 'רוצים להציע שירות למטיילים של Tra-Vel?', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'שלחו בקשת הצטרפות לטיסות, לינה, ביטוח, תחבורה, פעילויות או תוכן מקומי.', 'tra-vel-v2' ); ?></p>
				<div class="partner-types"><span><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'תחבורה', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'חוויות', 'tra-vel-v2' ); ?></span></div>
			</div>
			<div class="partner-access-card">
				<i data-lucide="badge-check"></i>
				<?php if ( $can_access ) : ?>
					<small><?php esc_html_e( 'חשבון עם הרשאת ניהול', 'tra-vel-v2' ); ?></small>
					<h2><?php esc_html_e( 'המשיכו למרכז הניהול', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'הפעולות הזמינות נקבעות לפי הרשאות החשבון שלכם.', 'tra-vel-v2' ); ?></p>
					<a class="partner-primary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'למרכז הניהול', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
				<?php else : ?>
					<small><?php esc_html_e( 'כניסה למשתמשים מורשים', 'tra-vel-v2' ); ?></small>
					<h2><?php esc_html_e( 'יש לכם הרשאה?', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'היכנסו באמצעות החשבון שקיבל הרשאת שותף. שותפים חדשים יכולים לשלוח בקשת הצטרפות.', 'tra-vel-v2' ); ?></p>
					<a class="partner-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'כניסה לחשבון', 'tra-vel-v2' ); ?><i data-lucide="log-in"></i></a>
					<?php if ( $admin_mail ) : ?><a class="partner-secondary" href="mailto:<?php echo esc_attr( $admin_mail ); ?>?subject=Tra-Vel%20Partner"><?php esc_html_e( 'שלחו בקשת הצטרפות', 'tra-vel-v2' ); ?></a><?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<section class="section partner-process">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'לפני שמתחברים', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מה נבדוק בבקשת ההצטרפות', 'tra-vel-v2' ); ?></h2></div></div>
			<div class="partner-process-grid">
				<article><b>01</b><i data-lucide="building-2"></i><h3><?php esc_html_e( 'פרטי העסק', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'זהות, איש קשר, השירות המוצע ותנאים מסחריים.', 'tra-vel-v2' ); ?></p></article>
				<article><b>02</b><i data-lucide="plug-zap"></i><h3><?php esc_html_e( 'מקור המידע', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'איך יתקבלו מחיר, זמינות, תנאים וזמן בדיקה.', 'tra-vel-v2' ); ?></p></article>
				<article><b>03</b><i data-lucide="scan-search"></i><h3><?php esc_html_e( 'איכות וגילוי', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'עדכניות, תנאי שירות, אחריות וגילוי נאות למטייל.', 'tra-vel-v2' ); ?></p></article>
				<article><b>04</b><i data-lucide="map-pinned"></i><h3><?php esc_html_e( 'התאמה למסע', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'איפה השירות עשוי לעזור ומה המטייל צריך לדעת לפני מעבר לספק.', 'tra-vel-v2' ); ?></p></article>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
