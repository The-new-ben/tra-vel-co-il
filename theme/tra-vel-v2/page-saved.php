<?php
/**
 * Template Name: Tra-Vel Traveler Workspace
 * Template Post Type: page
 *
 * @package TraVelV2
 */

$customer_cockpit_cookie_name = class_exists( 'Tra_Vel_VIP_Capability_Session_Controller' ) ? Tra_Vel_VIP_Capability_Session_Controller::SESSION_COOKIE : '';
$customer_cockpit_cookie      = '' !== $customer_cockpit_cookie_name && isset( $_COOKIE[ $customer_cockpit_cookie_name ] ) ? rawurldecode( wp_unslash( (string) $_COOKIE[ $customer_cockpit_cookie_name ] ) ) : '';
$has_customer_cockpit_session = '' !== $customer_cockpit_cookie && 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $customer_cockpit_cookie );
$customer_cockpit_signed_in   = is_user_logged_in();
$customer_cockpit_mode        = $has_customer_cockpit_session ? 'scoped-session' : ( $customer_cockpit_signed_in ? 'signed-in' : 'scoped-session' );
$show_customer_cockpit        = class_exists( 'Tra_Vel_Customer_Trip_Cockpit_Controller' );
if ( $has_customer_cockpit_session || $customer_cockpit_signed_in ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
}
get_header();
?>
<main id="main-content" class="workspace-page" data-tra-vel-page="saved" data-traveler-workspace data-authenticated="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>">
	<section class="workspace-hero">
		<div class="page-width workspace-hero-grid">
			<div class="workspace-hero-copy">
				<span class="eyebrow"><?php esc_html_e( 'הנסיעות שלי', 'tra-vel-v2' ); ?></span>
				<h1><?php esc_html_e( 'כל מה ששמרתם לחופשה, במקום אחד.', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'השוו שוב טיסות, מלונות וחבילות שאהבתם, שמרו מחיר יעד והמשיכו לתכנן בלי להתחיל מחדש.', 'tra-vel-v2' ); ?></p>
				<div class="workspace-hero-metrics" aria-label="<?php esc_attr_e( 'סיכום הנסיעות השמורות', 'tra-vel-v2' ); ?>">
					<span><strong data-workspace-count>0</strong><?php esc_html_e( 'אפשרויות שמורות', 'tra-vel-v2' ); ?></span>
					<span><strong data-workspace-watch-count>0</strong><?php esc_html_e( 'יעדי מחיר', 'tra-vel-v2' ); ?></span>
					<span><strong data-workspace-destination-count>0</strong><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></span>
				</div>
			</div>
			<div class="workspace-orbit-column">
				<div class="workspace-orbit" data-workspace-map data-coordinate-mode="option-orbit" role="region" aria-labelledby="workspace-orbit-title">
					<p id="workspace-orbit-title" class="workspace-orbit-label" data-workspace-orbit-label><?php esc_html_e( 'האפשרויות ששמרתם סביב נקודת היציאה', 'tra-vel-v2' ); ?></p>
					<div class="workspace-orbit-glow" aria-hidden="true"></div>
					<span class="workspace-origin"><b>TLV</b><small><?php esc_html_e( 'נקודת היציאה', 'tra-vel-v2' ); ?></small></span>
					<div data-workspace-map-pins></div>
				</div>
				<article class="workspace-map-detail" data-workspace-map-detail role="status" aria-live="polite" aria-atomic="true">
					<small><?php esc_html_e( 'האפשרות שבחרתם', 'tra-vel-v2' ); ?></small>
					<h2 data-workspace-map-title><?php esc_html_e( 'שמרו אפשרות כדי להתחיל', 'tra-vel-v2' ); ?></h2>
					<p data-workspace-map-copy><?php esc_html_e( 'האפשרויות שתשמרו מהשוואת הטיסות, המלונות והחבילות יופיעו כאן.', 'tra-vel-v2' ); ?></p>
					<strong data-workspace-map-price><?php esc_html_e( 'טרם נקבע', 'tra-vel-v2' ); ?></strong>
				</article>
			</div>
		</div>
	</section>

	<section class="section workspace-command-section">
		<div class="page-width workspace-command-grid">
			<div class="workspace-command-main">
				<?php if ( $show_customer_cockpit ) : ?>
				<section class="customer-trip-cockpit" data-customer-trip-cockpit data-state="loading" data-mode="<?php echo esc_attr( $customer_cockpit_mode ); ?>" aria-labelledby="customer-trip-cockpit-title" aria-busy="true" hidden>
					<header class="customer-trip-cockpit-heading">
						<div>
							<span class="eyebrow"><?php esc_html_e( 'הנסיעה שלכם עכשיו', 'tra-vel-v2' ); ?></span>
							<h2 id="customer-trip-cockpit-title"><?php esc_html_e( 'כל מה שצריך, במקום אחד', 'tra-vel-v2' ); ?></h2>
							<p><?php esc_html_e( 'המצב העדכני של כל שירות, מה כבר טופל ומה דורש מכם פעולה.', 'tra-vel-v2' ); ?></p>
						</div>
						<div class="customer-trip-cockpit-controls">
							<span class="customer-trip-cockpit-mode" data-signed-label="<?php esc_attr_e( 'תצוגה פרטית בחשבון', 'tra-vel-v2' ); ?>" data-scoped-label="<?php esc_attr_e( 'קישור צפייה מאובטח', 'tra-vel-v2' ); ?>"><i data-lucide="<?php echo 'signed-in' === $customer_cockpit_mode ? 'user-round-check' : 'link-2'; ?>"></i><span data-customer-trip-mode-label><?php echo 'signed-in' === $customer_cockpit_mode ? esc_html__( 'תצוגה פרטית בחשבון', 'tra-vel-v2' ) : esc_html__( 'קישור צפייה מאובטח', 'tra-vel-v2' ); ?></span></span>
							<?php if ( $has_customer_cockpit_session && $customer_cockpit_signed_in ) : ?><div class="customer-trip-cockpit-mode-choice" role="group" aria-label="<?php esc_attr_e( 'בחרו איזו נסיעה להציג', 'tra-vel-v2' ); ?>"><button type="button" data-customer-trip-mode-select="scoped-session" aria-pressed="true"><?php esc_html_e( 'הקישור', 'tra-vel-v2' ); ?></button><button type="button" data-customer-trip-mode-select="signed-in" aria-pressed="false"><?php esc_html_e( 'החשבון שלי', 'tra-vel-v2' ); ?></button></div><?php endif; ?>
							<button type="button" data-customer-trip-cockpit-refresh><i data-lucide="refresh-cw"></i><span><?php esc_html_e( 'רעננו מצב', 'tra-vel-v2' ); ?></span></button>
							<button type="button" data-customer-trip-cockpit-close hidden><i data-lucide="log-out"></i><span><?php esc_html_e( 'סיימו צפייה', 'tra-vel-v2' ); ?></span></button>
						</div>
					</header>
					<p class="sr-only" data-customer-trip-cockpit-announcer role="status" aria-live="polite" aria-atomic="true"></p>
					<div class="customer-trip-cockpit-loading" data-customer-trip-cockpit-loading>
						<span aria-hidden="true"></span><strong><?php esc_html_e( 'בודקים את מצב הנסיעה...', 'tra-vel-v2' ); ?></strong>
					</div>
					<div class="customer-trip-cockpit-view" data-customer-trip-cockpit-view hidden>
						<div class="customer-trip-cockpit-summary">
							<div class="customer-trip-cockpit-title"><small><?php esc_html_e( 'הנסיעה הפעילה', 'tra-vel-v2' ); ?></small><h3 data-customer-trip-headline></h3><span data-customer-trip-freshness></span></div>
							<dl class="customer-trip-cockpit-metrics">
								<div><dt><?php esc_html_e( 'שלב', 'tra-vel-v2' ); ?></dt><dd data-customer-trip-phase></dd></div>
								<div><dt><?php esc_html_e( 'מצב', 'tra-vel-v2' ); ?></dt><dd data-customer-trip-health></dd></div>
								<div><dt><?php esc_html_e( 'שירותים מושפעים', 'tra-vel-v2' ); ?></dt><dd data-customer-trip-affected></dd></div>
								<div><dt><?php esc_html_e( 'עדכון אחרון', 'tra-vel-v2' ); ?></dt><dd data-customer-trip-verified></dd></div>
							</dl>
						</div>
						<article class="customer-trip-next-action" data-customer-trip-next-action hidden><i data-lucide="sparkles"></i><div><small><?php esc_html_e( 'הפעולה הבאה', 'tra-vel-v2' ); ?></small><strong data-customer-trip-action-label></strong><p data-customer-trip-action-detail></p></div><button type="button" data-customer-trip-action hidden><?php esc_html_e( 'המשיכו לטיפול האישי', 'tra-vel-v2' ); ?></button></article>
						<section class="customer-trip-services" aria-labelledby="customer-trip-services-title"><div><span class="eyebrow"><?php esc_html_e( 'המסלול המלא', 'tra-vel-v2' ); ?></span><h3 id="customer-trip-services-title"><?php esc_html_e( 'כל השירותים לפי סדר הנסיעה', 'tra-vel-v2' ); ?></h3></div><ol data-customer-trip-services></ol></section>
						<section class="customer-trip-cases" data-customer-trip-cases hidden aria-labelledby="customer-trip-cases-title"><div><span class="eyebrow"><?php esc_html_e( 'טיפול אישי', 'tra-vel-v2' ); ?></span><h3 id="customer-trip-cases-title"><?php esc_html_e( 'פניות שנמצאות בטיפול', 'tra-vel-v2' ); ?></h3></div><div data-customer-trip-case-list></div></section>
						<p class="customer-trip-cockpit-boundary"><i data-lucide="shield-check"></i><?php esc_html_e( 'זו תצוגת מצב בלבד. שינוי, תשלום, ביטול או הזמנה ידרשו אימות ואישור נפרדים.', 'tra-vel-v2' ); ?></p>
					</div>
					<div class="customer-trip-cockpit-empty" data-customer-trip-cockpit-empty hidden><i data-lucide="luggage"></i><div><strong data-customer-trip-empty-title><?php esc_html_e( 'אין כרגע נסיעה פעילה בחשבון', 'tra-vel-v2' ); ?></strong><p data-customer-trip-empty-copy><?php esc_html_e( 'כאשר נסיעה תועבר לטיפול, המצב המלא שלה יופיע כאן.', 'tra-vel-v2' ); ?></p></div></div>
					<div class="customer-trip-cockpit-error" data-customer-trip-cockpit-error hidden><i data-lucide="refresh-cw"></i><div><strong data-customer-trip-error-title><?php esc_html_e( 'לא הצלחנו לרענן את הנסיעה', 'tra-vel-v2' ); ?></strong><p data-customer-trip-error-copy><?php esc_html_e( 'אפשר לנסות שוב בלי לאבד את העדכון האחרון.', 'tra-vel-v2' ); ?></p></div><button type="button" data-customer-trip-cockpit-retry><?php esc_html_e( 'נסו שוב', 'tra-vel-v2' ); ?></button></div>
				</section>
				<?php endif; ?>
				<section class="workspace-cockpit" data-workspace-cockpit data-state="idle" aria-labelledby="workspace-cockpit-title" aria-busy="false">
					<header class="workspace-cockpit-heading">
						<div><span class="eyebrow"><?php esc_html_e( 'תוכניות אחרונות', 'tra-vel-v2' ); ?></span><h2 id="workspace-cockpit-title"><?php esc_html_e( 'תוכניות החופשה שלכם', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'ראו מה כבר הוכן, אילו פרטים חסרים ומה אפשר לעשות עכשיו. מחיר או הזמנה יוצגו רק לאחר בדיקה ואישור.', 'tra-vel-v2' ); ?></p></div>
						<div class="workspace-cockpit-state"><p data-workspace-cockpit-status><?php esc_html_e( 'כאן מופיעות תוכניות החופשה השמורות בחשבון.', 'tra-vel-v2' ); ?></p><button type="button" data-workspace-cockpit-retry hidden><?php esc_html_e( 'נסו שוב', 'tra-vel-v2' ); ?></button></div>
					</header>
					<p class="sr-only" data-workspace-cockpit-announcer role="status" aria-live="polite" aria-atomic="true"></p>
					<div class="workspace-plan-list" data-workspace-plan-list></div>
					<div class="workspace-plan-empty" data-workspace-plan-empty hidden><i data-lucide="sparkles"></i><div><strong><?php esc_html_e( 'עדיין אין תוכנית חופשה', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'ספרו למתכנן לאן תרצו לנסוע, מי נוסע ומה התקציב. תוכלו לשנות את הבקשה בכל שלב.', 'tra-vel-v2' ); ?></p></div><a href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'התחילו לתכנן חופשה', 'tra-vel-v2' ); ?></a></div>
					<p class="workspace-cockpit-safety"><i data-lucide="shield-check"></i><?php esc_html_e( 'התקדמות בתכנון אינה אישור למחיר, לזמינות, לחיוב או להזמנה. לפני רכישה יוצגו המחיר העדכני, התנאים ומי נותן את השירות.', 'tra-vel-v2' ); ?></p>
				</section>
				<section class="workspace-quote-section" data-workspace-quote-cases data-state="idle" aria-labelledby="workspace-quote-title" aria-busy="false">
					<div class="workspace-quote-heading"><div><span class="eyebrow"><?php esc_html_e( 'בדיקה אישית', 'tra-vel-v2' ); ?></span><h2 id="workspace-quote-title"><?php esc_html_e( 'ההצעות והעדכונים שלכם', 'tra-vel-v2' ); ?></h2></div><p data-workspace-quote-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'אחרי שתשלחו תוכנית לבדיקה אישית, העדכונים והפעולה הבאה יופיעו כאן.', 'tra-vel-v2' ); ?></p></div>
					<p class="workspace-proposal-intro"><i data-lucide="badge-check"></i><span><strong><?php esc_html_e( 'פתחו בקשה כדי לראות את ההצעות האישיות שלה', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בכל הצעה תראו עד שמונה חלקי חופשה שרלוונטיים לבקשה, מחירים שנבדקו, מה חסר ומה אפשר לעשות עכשיו.', 'tra-vel-v2' ); ?></span></p>
					<div class="workspace-quote-grid" data-workspace-quote-grid></div>
					<div class="workspace-quote-empty" data-workspace-quote-empty hidden><i data-lucide="clipboard-check"></i><div><strong><?php esc_html_e( 'אין עדיין תוכנית בבדיקה', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'כשתוכנית תהיה מוכנה, תוכלו לשלוח אותה לבדיקה אישית ולראות כאן הצעות, עדכונים והפעולה הבאה.', 'tra-vel-v2' ); ?></p></div><a href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'התחילו בקשה להצעה', 'tra-vel-v2' ); ?></a></div>
				</section>
				<div class="section-heading workspace-heading">
					<div>
						<span class="eyebrow"><?php esc_html_e( 'מה שמרתם', 'tra-vel-v2' ); ?></span>
						<h2><?php esc_html_e( 'השוו טיסות, מלונות וחבילות שאהבתם', 'tra-vel-v2' ); ?></h2>
						<p data-workspace-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'כאן מופיעות האפשרויות ששמרתם במכשיר ובחשבון.', 'tra-vel-v2' ); ?></p>
					</div>
					<div class="workspace-filters" data-workspace-filters aria-label="<?php esc_attr_e( 'סינון פריטים שמורים', 'tra-vel-v2' ); ?>">
						<button class="is-active" type="button" data-workspace-filter="all" aria-pressed="true"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="flight" aria-pressed="false"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="hotel" aria-pressed="false"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="package" aria-pressed="false"><?php esc_html_e( 'חבילות', 'tra-vel-v2' ); ?></button>
					</div>
				</div>
				<div class="workspace-item-grid" data-workspace-items aria-live="polite"></div>
				<div class="workspace-empty" data-workspace-empty hidden>
					<i data-lucide="heart-plus"></i>
					<h3><?php esc_html_e( 'עדיין לא שמרתם אפשרויות', 'tra-vel-v2' ); ?></h3>
					<p><?php esc_html_e( 'התחילו מהשוואה אחת. חפשו טיסה, מלון או חבילה ולחצו שמירה על האפשרות שתרצו לזכור.', 'tra-vel-v2' ); ?></p>
					<div><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'הרכיבו חבילה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'פתחו את המפה', 'tra-vel-v2' ); ?></a></div>
				</div>
			</div>

			<aside class="workspace-side-panel">
				<div class="workspace-auth-card">
					<i data-lucide="<?php echo is_user_logged_in() ? 'cloud-check' : 'hard-drive'; ?>"></i>
					<div>
						<strong><?php echo is_user_logged_in() ? esc_html__( 'האפשרויות מסונכרנות לחשבון', 'tra-vel-v2' ) : esc_html__( 'נשמר רק במכשיר הזה', 'tra-vel-v2' ); ?></strong>
						<p><?php echo is_user_logged_in() ? esc_html__( 'אחרי כל שמירה נציג אם האפשרות נשמרה בחשבון או רק במכשיר הזה.', 'tra-vel-v2' ) : esc_html__( 'אין צורך בחשבון. האפשרויות נשארות בדפדפן הזה ואינן מסתנכרנות לחשבון.', 'tra-vel-v2' ); ?></p>
					</div>
					<?php if ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'התחברו כדי לסנכרן', 'tra-vel-v2' ); ?></a>
					<?php endif; ?>
				</div>

				<form class="workspace-preferences" data-workspace-preferences>
					<div><span class="eyebrow"><?php esc_html_e( 'העדפות לחופשה', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מה מתאים לכם בדרך כלל', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'שמרו שדה יציאה, תקציב וסגנון כדי לקבל אפשרויות מתאימות מהר יותר.', 'tra-vel-v2' ); ?></p></div>
					<label><span><?php esc_html_e( 'שדה יציאה', 'tra-vel-v2' ); ?></span><input name="home_airport" value="TLV" maxlength="3" inputmode="text" required></label>
					<div class="workspace-form-row">
						<label><span><?php esc_html_e( 'מטבע', 'tra-vel-v2' ); ?></span><select name="currency"><option value="USD">USD</option><option value="EUR">EUR</option><option value="ILS">ILS</option></select></label>
						<label><span><?php esc_html_e( 'עד עצירות', 'tra-vel-v2' ); ?></span><select name="max_stops"><option value="0"><?php esc_html_e( 'ישיר', 'tra-vel-v2' ); ?></option><option value="1" selected>1</option><option value="2">2</option><option value="3">3</option></select></label>
					</div>
					<label><span><?php esc_html_e( 'תקציב כולל', 'tra-vel-v2' ); ?></span><input name="budget" type="number" min="0" max="1000000" step="100" value="0"></label>
					<label><span><?php esc_html_e( 'הרכב', 'tra-vel-v2' ); ?></span><select name="party_style"><option value="solo"><?php esc_html_e( 'יחיד', 'tra-vel-v2' ); ?></option><option value="couple" selected><?php esc_html_e( 'זוג', 'tra-vel-v2' ); ?></option><option value="family"><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></option><option value="friends"><?php esc_html_e( 'חברים', 'tra-vel-v2' ); ?></option></select></label>
					<fieldset><legend><?php esc_html_e( 'מה חשוב יותר?', 'tra-vel-v2' ); ?></legend><div class="workspace-priority-grid"><label><input type="checkbox" name="priorities" value="price" checked><span><?php esc_html_e( 'מחיר', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="comfort" checked><span><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="flexibility"><span><?php esc_html_e( 'גמישות', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="location"><span><?php esc_html_e( 'מיקום', 'tra-vel-v2' ); ?></span></label></div></fieldset>
					<p class="workspace-preferences-status" data-workspace-preferences-status role="status" aria-live="polite" aria-atomic="true"></p>
					<button type="submit"><?php esc_html_e( 'שמרו העדפות', 'tra-vel-v2' ); ?></button>
				</form>

				<div class="workspace-watch-note">
					<i data-lucide="bell-ring"></i>
					<div><strong><?php esc_html_e( 'מחיר יעד להשוואה', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'שמרו את המחיר שמתאים לכם כדי להשוות אליו אפשרויות חדשות. לא יישלחו התראות בלי מחיר עדכני והסכמה מפורשת.', 'tra-vel-v2' ); ?></p></div>
				</div>
				<div class="workspace-privacy-note"><i data-lucide="shield-check"></i><p><?php esc_html_e( 'פרטים רגישים יתבקשו רק בצעד מאובטח ולמטרה ברורה. אל תכתבו כאן מספר דרכון, פרטי תשלום, מידע רפואי או תשובות חיתום.', 'tra-vel-v2' ); ?></p></div>
			</aside>
		</div>
	</section>
</main>
<?php
get_footer();
