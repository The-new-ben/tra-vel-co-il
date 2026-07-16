<?php
/**
 * Template Name: Tra-Vel Traveler Workspace
 * Template Post Type: page
 *
 * @package TraVelV2
 */

get_header();
?>
<main id="main-content" class="workspace-page" data-traveler-workspace data-authenticated="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>">
	<section class="workspace-hero">
		<div class="page-width workspace-hero-grid">
			<div class="workspace-hero-copy">
				<span class="eyebrow">Tra-Vel Trip Space</span>
				<h1><?php esc_html_e( 'הטיול שלכם. כל ההחלטות במקום אחד.', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'שומרים טיסות, מלונות וחבילות, מצמידים יעד מחיר ורואים את כל האפשרויות על מפה אחת — בלי לאבד את ההקשר.', 'tra-vel-v2' ); ?></p>
				<div class="workspace-hero-metrics" aria-label="<?php esc_attr_e( 'סיכום סביבת העבודה', 'tra-vel-v2' ); ?>">
					<span><strong data-workspace-count>0</strong><?php esc_html_e( 'אפשרויות שמורות', 'tra-vel-v2' ); ?></span>
					<span><strong data-workspace-watch-count>0</strong><?php esc_html_e( 'מעקבי מחיר', 'tra-vel-v2' ); ?></span>
					<span><strong data-workspace-destination-count>0</strong><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></span>
				</div>
			</div>
			<div class="workspace-orbit" data-workspace-map aria-label="<?php esc_attr_e( 'מפת האפשרויות השמורות', 'tra-vel-v2' ); ?>">
				<div class="workspace-orbit-glow" aria-hidden="true"></div>
				<span class="workspace-origin"><b>TLV</b><small><?php esc_html_e( 'נקודת היציאה', 'tra-vel-v2' ); ?></small></span>
				<div data-workspace-map-pins></div>
				<article class="workspace-map-detail" data-workspace-map-detail>
					<small><?php esc_html_e( 'הבחירה על המפה', 'tra-vel-v2' ); ?></small>
					<h2 data-workspace-map-title><?php esc_html_e( 'שמרו אפשרות כדי להתחיל', 'tra-vel-v2' ); ?></h2>
					<p data-workspace-map-copy><?php esc_html_e( 'האפשרויות שתשמרו מהשוואת הטיסות, המלונות והחבילות יופיעו כאן.', 'tra-vel-v2' ); ?></p>
					<strong data-workspace-map-price>—</strong>
				</article>
			</div>
		</div>
	</section>

	<section class="section workspace-command-section">
		<div class="page-width workspace-command-grid">
			<div class="workspace-command-main">
				<div class="section-heading workspace-heading">
					<div>
						<span class="eyebrow">Decision board</span>
						<h2><?php esc_html_e( 'השוו את מה שכבר מצא חן בעיניכם', 'tra-vel-v2' ); ?></h2>
						<p data-workspace-status role="status"><?php esc_html_e( 'טוען את סביבת העבודה האישית...', 'tra-vel-v2' ); ?></p>
					</div>
					<div class="workspace-filters" data-workspace-filters aria-label="<?php esc_attr_e( 'סינון פריטים שמורים', 'tra-vel-v2' ); ?>">
						<button class="is-active" type="button" data-workspace-filter="all"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="flight"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="hotel"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></button>
						<button type="button" data-workspace-filter="package"><?php esc_html_e( 'חבילות', 'tra-vel-v2' ); ?></button>
					</div>
				</div>
				<div class="workspace-item-grid" data-workspace-items aria-live="polite"></div>
				<div class="workspace-empty" data-workspace-empty hidden>
					<i data-lucide="heart-plus"></i>
					<h3><?php esc_html_e( 'עדיין אין כאן החלטות', 'tra-vel-v2' ); ?></h3>
					<p><?php esc_html_e( 'התחילו מהשוואה אחת. חפשו טיסה, מלון או חבילה ולחצו שמירה על האפשרות שתרצו לזכור.', 'tra-vel-v2' ); ?></p>
					<div><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'הרכיבו חבילה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'פתחו את המפה', 'tra-vel-v2' ); ?></a></div>
				</div>
			</div>

			<aside class="workspace-side-panel">
				<div class="workspace-auth-card">
					<i data-lucide="<?php echo is_user_logged_in() ? 'cloud-check' : 'hard-drive'; ?>"></i>
					<div>
						<strong><?php echo is_user_logged_in() ? esc_html__( 'נשמר גם בחשבון', 'tra-vel-v2' ) : esc_html__( 'נשמר במכשיר הזה', 'tra-vel-v2' ); ?></strong>
						<p><?php echo is_user_logged_in() ? esc_html__( 'השמירות מסונכרנות בצורה פרטית עם חשבון WordPress שלכם.', 'tra-vel-v2' ) : esc_html__( 'אין צורך בחשבון. המידע נשאר בדפדפן ואינו נשלח לשרת.', 'tra-vel-v2' ); ?></p>
					</div>
					<?php if ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'התחברו כדי לסנכרן', 'tra-vel-v2' ); ?></a>
					<?php endif; ?>
				</div>

				<form class="workspace-preferences" data-workspace-preferences>
					<div><span class="eyebrow">Travel defaults</span><h2><?php esc_html_e( 'העדפות חכמות', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'הגדרות בסיס שעוזרות למפה ולמתכנן לדרג אפשרויות.', 'tra-vel-v2' ); ?></p></div>
					<label><span><?php esc_html_e( 'שדה יציאה', 'tra-vel-v2' ); ?></span><input name="home_airport" value="TLV" maxlength="3" inputmode="text" required></label>
					<div class="workspace-form-row">
						<label><span><?php esc_html_e( 'מטבע', 'tra-vel-v2' ); ?></span><select name="currency"><option value="USD">USD</option><option value="EUR">EUR</option><option value="ILS">ILS</option></select></label>
						<label><span><?php esc_html_e( 'עד עצירות', 'tra-vel-v2' ); ?></span><select name="max_stops"><option value="0"><?php esc_html_e( 'ישיר', 'tra-vel-v2' ); ?></option><option value="1" selected>1</option><option value="2">2</option><option value="3">3</option></select></label>
					</div>
					<label><span><?php esc_html_e( 'תקציב כולל', 'tra-vel-v2' ); ?></span><input name="budget" type="number" min="0" max="1000000" step="100" value="0"></label>
					<label><span><?php esc_html_e( 'הרכב', 'tra-vel-v2' ); ?></span><select name="party_style"><option value="solo"><?php esc_html_e( 'יחיד', 'tra-vel-v2' ); ?></option><option value="couple" selected><?php esc_html_e( 'זוג', 'tra-vel-v2' ); ?></option><option value="family"><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></option><option value="friends"><?php esc_html_e( 'חברים', 'tra-vel-v2' ); ?></option></select></label>
					<fieldset><legend><?php esc_html_e( 'מה חשוב יותר?', 'tra-vel-v2' ); ?></legend><div class="workspace-priority-grid"><label><input type="checkbox" name="priorities" value="price" checked><span><?php esc_html_e( 'מחיר', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="comfort" checked><span><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="flexibility"><span><?php esc_html_e( 'גמישות', 'tra-vel-v2' ); ?></span></label><label><input type="checkbox" name="priorities" value="location"><span><?php esc_html_e( 'מיקום', 'tra-vel-v2' ); ?></span></label></div></fieldset>
					<button type="submit"><?php esc_html_e( 'שמרו העדפות', 'tra-vel-v2' ); ?></button>
				</form>

				<div class="workspace-watch-note">
					<i data-lucide="bell-ring"></i>
					<div><strong><?php esc_html_e( 'מעקב מחירים מוכן, אך אינו שולח התראות עדיין', 'tra-vel-v2' ); ?></strong><p><?php esc_html_e( 'אפשר להצמיד יעד מחיר להשוואה. משלוח הודעות ייפתח רק אחרי חיבור ספק חי, אימות מחיר והסכמה מפורשת.', 'tra-vel-v2' ); ?></p></div>
				</div>
				<div class="workspace-privacy-note"><i data-lucide="shield-check"></i><p><?php esc_html_e( 'אין לשמור כאן פרטי דרכון, תשלום, מידע רפואי או תשובות חיתום. סביבת העבודה מיועדת להחלטות תכנון בלבד.', 'tra-vel-v2' ); ?></p></div>
			</aside>
		</div>
	</section>
</main>
<?php
get_footer();
