<?php
/**
 * Reusable view helpers.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tra_vel_v2_asset_uri( $path ) {
	return esc_url( trailingslashit( TRA_VEL_V2_URI . '/assets' ) . ltrim( $path, '/' ) );
}

function tra_vel_v2_demo_disclosure() {
	echo '<small class="demo-label"><i data-lucide="info"></i>';
	esc_html_e( 'המחירים המוצגים עוזרים לתכנן ולהשוות. המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.', 'tra-vel-v2' );
	echo '</small>';
}

function tra_vel_v2_mobile_bottom_nav() {
	?>
	<nav class="mobile-bottom-nav" aria-label="<?php esc_attr_e( 'ניווט מהיר', 'tra-vel-v2' ); ?>">
		<a class="<?php echo is_front_page() ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"><i data-lucide="house"></i><?php esc_html_e( 'בית', 'tra-vel-v2' ); ?></a>
		<a class="<?php echo is_page( 'travel-map' ) ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><?php esc_html_e( 'מפה', 'tra-vel-v2' ); ?></a>
		<a href="<?php echo esc_url( home_url( '/#search' ) ); ?>"><i data-lucide="search"></i><?php esc_html_e( 'חיפוש', 'tra-vel-v2' ); ?></a>
		<a class="<?php echo is_page( 'saved' ) ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>"><i data-lucide="heart"></i><?php esc_html_e( 'שמורים', 'tra-vel-v2' ); ?></a>
		<a class="<?php echo is_page( 'account' ) ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><i data-lucide="user-round"></i><?php esc_html_e( 'חשבון', 'tra-vel-v2' ); ?></a>
	</nav>
	<?php
}

/**
 * Voice dock (theme 1.29.0): a circular glass microphone docked inside every
 * globe container. Opens a glass sheet with a live transcript, an editable
 * text field and a GO action that carries the request to the planner.
 * Server-rendered hidden; assets/js/voice-dock.js reveals and drives it, so
 * a browser without JavaScript never shows a dead control.
 */
function tra_vel_v2_voice_dock() {
	?>
	<div class="voice-dock" data-voice-dock data-state="idle" hidden>
		<button class="voice-dock-button" type="button" data-voice-dock-toggle aria-expanded="false" aria-label="<?php esc_attr_e( 'תארו את החופשה בקול ונצא לדרך', 'tra-vel-v2' ); ?>"><i data-lucide="mic" aria-hidden="true"></i></button>
		<section class="voice-sheet" data-voice-sheet role="dialog" aria-label="<?php esc_attr_e( 'תיאור החופשה בקול או בהקלדה', 'tra-vel-v2' ); ?>" hidden>
			<header class="voice-sheet-head">
				<strong><?php esc_html_e( 'ספרו לנו על החופשה', 'tra-vel-v2' ); ?></strong>
				<small><?php esc_html_e( 'מדברים או מקלידים, עורכים, ויוצאים לדרך.', 'tra-vel-v2' ); ?></small>
			</header>
			<p class="voice-sheet-interim" data-voice-interim aria-live="polite"></p>
			<label class="voice-sheet-field">
				<span><?php esc_html_e( 'הבקשה שלכם, אפשר לערוך', 'tra-vel-v2' ); ?></span>
				<textarea data-voice-text rows="3" maxlength="4000" autocomplete="off" placeholder="<?php esc_attr_e( 'לדוגמה: שבוע רגוע ביוון בספטמבר לזוג', 'tra-vel-v2' ); ?>"></textarea>
			</label>
			<p class="voice-sheet-note" data-voice-note hidden></p>
			<div class="voice-sheet-actions">
				<button class="voice-sheet-mic" type="button" data-voice-mic aria-pressed="false"><i data-lucide="mic" aria-hidden="true"></i><span data-voice-mic-label><?php esc_html_e( 'התחילו הקלטה', 'tra-vel-v2' ); ?></span></button>
				<button class="voice-sheet-go" type="button" data-voice-go><?php esc_html_e( 'צא לדרך', 'tra-vel-v2' ); ?></button>
				<button class="voice-sheet-cancel" type="button" data-voice-cancel><?php esc_html_e( 'ביטול', 'tra-vel-v2' ); ?></button>
			</div>
			<p class="voice-sheet-footnote"><?php esc_html_e( 'הדיבור מזוהה בדפדפן שלכם. אלינו נשלח הטקסט בלבד.', 'tra-vel-v2' ); ?></p>
		</section>
	</div>
	<?php
}

function tra_vel_v2_brand() {
	?>
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Tra-Vel דף הבית', 'tra-vel-v2' ); ?>">
		<span class="brand-mark"><i data-lucide="navigation"></i></span>
		<span class="brand-word">tra<b>־vel</b></span>
	</a>
	<?php
}
