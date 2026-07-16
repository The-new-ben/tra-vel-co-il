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
	esc_html_e( 'מחיר ותנאים מוצגים רק לאחר בחירת תאריכים והרכב', 'tra-vel-v2' );
	echo '</small>';
}

function tra_vel_v2_brand() {
	?>
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Tra-Vel דף הבית', 'tra-vel-v2' ); ?>">
		<span class="brand-mark"><i data-lucide="navigation"></i></span>
		<span class="brand-word">tra<b>־vel</b></span>
	</a>
	<?php
}
