<?php
/**
 * Template Name: Tra-Vel Account
 * Template Post Type: page
 *
 * @package TraVelV2
 */

get_header();
$account_url = get_permalink();
$user        = wp_get_current_user();
?>
<main id="main-content" class="identity-page" data-tra-vel-page="account">
	<section class="identity-hero">
		<div class="page-width identity-hero-grid">
			<div class="identity-copy">
				<span class="eyebrow"><?php esc_html_e( 'החשבון שלי', 'tra-vel-v2' ); ?></span>
				<h1><?php esc_html_e( 'הנסיעה שלכם ממשיכה מכל מכשיר', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'שומרים מסלולים, השוואות והעדפות וממשיכים בדיוק מהנקודה שבה עצרתם.', 'tra-vel-v2' ); ?></p>
				<div class="identity-benefits">
					<span><i data-lucide="heart"></i><?php esc_html_e( 'נסיעות שמורות', 'tra-vel-v2' ); ?></span>
					<span><i data-lucide="badge-dollar-sign"></i><?php esc_html_e( 'מחירי יעד שמורים', 'tra-vel-v2' ); ?></span>
					<span><i data-lucide="sliders-horizontal"></i><?php esc_html_e( 'העדפות אישיות', 'tra-vel-v2' ); ?></span>
				</div>
			</div>
			<div class="identity-card">
				<?php if ( is_user_logged_in() ) : ?>
					<div class="identity-user">
						<?php echo get_avatar( $user->ID, 64 ); ?>
						<div><small><?php esc_html_e( 'מחוברים כעת', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong></div>
					</div>
					<h2><?php esc_html_e( 'מה תרצו לעשות?', 'tra-vel-v2' ); ?></h2>
					<div class="identity-actions">
						<a class="identity-primary" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>"><i data-lucide="route"></i><?php esc_html_e( 'פתחו את הנסיעות שלי', 'tra-vel-v2' ); ?></a>
						<a href="<?php echo esc_url( get_edit_profile_url( $user->ID ) ); ?>"><i data-lucide="settings-2"></i><?php esc_html_e( 'הגדרות החשבון', 'tra-vel-v2' ); ?></a>
						<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><i data-lucide="log-out"></i><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?></a>
					</div>
				<?php else : ?>
					<span class="identity-lock"><i data-lucide="lock-keyhole"></i><?php esc_html_e( 'כניסה מאובטחת', 'tra-vel-v2' ); ?></span>
					<h2><?php esc_html_e( 'כניסה לחשבון', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'התחברו כדי לסנכרן את הנסיעות השמורות שלכם.', 'tra-vel-v2' ); ?></p>
					<?php
					wp_login_form(
						array(
							'echo'           => true,
							'redirect'       => home_url( '/saved/' ),
							'label_username' => __( 'אימייל או שם משתמש', 'tra-vel-v2' ),
							'label_password' => __( 'סיסמה', 'tra-vel-v2' ),
							'label_remember' => __( 'זכרו אותי', 'tra-vel-v2' ),
							'label_log_in'   => __( 'כניסה', 'tra-vel-v2' ),
							'remember'       => true,
						)
					);
					?>
					<div class="identity-login-links">
						<a href="<?php echo esc_url( wp_lostpassword_url( $account_url ) ); ?>"><?php esc_html_e( 'שכחתם סיסמה?', 'tra-vel-v2' ); ?></a>
						<?php if ( get_option( 'users_can_register' ) ) : ?><a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'יצירת חשבון', 'tra-vel-v2' ); ?></a><?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<section class="identity-assurance page-width" aria-label="<?php esc_attr_e( 'פרטיות ושליטה', 'tra-vel-v2' ); ?>">
		<article><i data-lucide="shield-check"></i><div><strong><?php esc_html_e( 'הפרטיות בידיים שלכם', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'פרטים רגישים מתבקשים בצעד מאובטח רק כשצריך אותם, למטרה מוגדרת ועם הרשאה מתאימה.', 'tra-vel-v2' ); ?></p></div></article>
		<article><i data-lucide="briefcase-business"></i><div><strong><?php esc_html_e( 'ספקים ושותפים', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'לספקים מאומתים יש כניסה נפרדת והרשאות ייעודיות.', 'tra-vel-v2' ); ?><a href="<?php echo esc_url( home_url( '/partners/' ) ); ?>"><?php esc_html_e( ' למרכז השותפים', 'tra-vel-v2' ); ?></a></p></div></article>
	</section>
</main>
<?php get_footer(); ?>
