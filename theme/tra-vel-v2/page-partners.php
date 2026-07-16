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
<main id="main-content" class="partner-page">
	<section class="partner-hero">
		<div class="page-width partner-hero-grid">
			<div>
				<span class="eyebrow">Tra-Vel Partners</span>
				<h1><?php esc_html_e( 'מחברים את המוצר הנכון לרגע הנכון במסע', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'מרכז אחד לספקי טיסות, לינה, ביטוח, תחבורה, פעילויות ותוכן מקומי.', 'tra-vel-v2' ); ?></p>
				<div class="partner-types"><span><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'תחבורה', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'חוויות', 'tra-vel-v2' ); ?></span></div>
			</div>
			<div class="partner-access-card">
				<i data-lucide="badge-check"></i>
				<?php if ( $can_access ) : ?>
					<small><?php esc_html_e( 'חשבון שותף מאומת', 'tra-vel-v2' ); ?></small>
					<h2><?php esc_html_e( 'מרכז התפעול מוכן', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'המשיכו לניהול המלאי, ההפניות והתוכן בהתאם להרשאות החשבון.', 'tra-vel-v2' ); ?></p>
					<a class="partner-primary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'כניסה למרכז התפעול', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
				<?php else : ?>
					<small><?php esc_html_e( 'כניסה לספקים מאומתים', 'tra-vel-v2' ); ?></small>
					<h2><?php esc_html_e( 'כניסה והרשאות', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'הגישה נפתחת רק לאחר אימות העסק, איש הקשר והרשאות המשתמש.', 'tra-vel-v2' ); ?></p>
					<a class="partner-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'כניסה לחשבון שותף', 'tra-vel-v2' ); ?><i data-lucide="log-in"></i></a>
					<?php if ( $admin_mail ) : ?><a class="partner-secondary" href="mailto:<?php echo esc_attr( $admin_mail ); ?>?subject=Tra-Vel%20Partner"><?php esc_html_e( 'בקשת הצטרפות', 'tra-vel-v2' ); ?></a><?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<section class="section partner-process">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'תהליך ברור', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מאימות ועד הופעה במסע', 'tra-vel-v2' ); ?></h2></div></div>
			<div class="partner-process-grid">
				<article><b>01</b><i data-lucide="building-2"></i><h3><?php esc_html_e( 'אימות העסק', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'זהות, איש קשר, שירות ותנאים מסחריים.', 'tra-vel-v2' ); ?></p></article>
				<article><b>02</b><i data-lucide="plug-zap"></i><h3><?php esc_html_e( 'חיבור נתונים', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'מלאי, מחיר, זמינות ותנאים ממקור מוסכם.', 'tra-vel-v2' ); ?></p></article>
				<article><b>03</b><i data-lucide="scan-search"></i><h3><?php esc_html_e( 'בדיקת איכות', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'עקביות, עדכניות, גילוי נאות וחוויית הזמנה.', 'tra-vel-v2' ); ?></p></article>
				<article><b>04</b><i data-lucide="map-pinned"></i><h3><?php esc_html_e( 'הצגה בהקשר', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'המוצר מופיע במקום שבו הוא באמת עוזר לנוסע.', 'tra-vel-v2' ); ?></p></article>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>

