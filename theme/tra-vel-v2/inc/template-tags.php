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

function tra_vel_v2_brand() {
	?>
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Tra-Vel דף הבית', 'tra-vel-v2' ); ?>">
		<span class="brand-mark"><i data-lucide="navigation"></i></span>
		<span class="brand-word">tra<b>־vel</b></span>
	</a>
	<?php
}
