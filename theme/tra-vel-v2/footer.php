<?php
/**
 * Site footer.
 *
 * @package TraVelV2
 */
if ( is_page_template( 'page-map.php' ) ) {
	?>
	<div class="map-mobile-controls"><button data-filter-toggle type="button" aria-expanded="false"><i data-lucide="sliders-horizontal"></i><?php esc_html_e( 'מסננים', 'tra-vel-v2' ); ?></button><button type="button"><i data-lucide="route"></i><?php esc_html_e( 'מסלולים', 'tra-vel-v2' ); ?></button><button type="button"><i data-lucide="list"></i><?php esc_html_e( 'רשימה', 'tra-vel-v2' ); ?></button></div>
	<?php wp_footer(); ?>
	</body>
	</html>
	<?php
	return;
}
?>
<footer class="site-footer">
	<div class="footer-grid page-width">
		<div class="footer-brand"><?php tra_vel_v2_brand(); ?><p><?php esc_html_e( 'מגלים, משווים ומתכננים את כל הנסיעה על מפה אחת — בשקיפות, בהקשר ובקצב שלכם.', 'tra-vel-v2' ); ?></p></div>
		<div class="footer-column"><strong><?php esc_html_e( 'מזמינים', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'טיסה + מלון', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></a></div>
		<div class="footer-column"><strong><?php esc_html_e( 'מגלים', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'מפת המחירים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'מדריכים', 'tra-vel-v2' ); ?></a></div>
		<div class="footer-column"><strong>Tra-Vel</strong><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'אודות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>"><?php esc_html_e( 'איך אנחנו משווים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/disclosure/' ) ); ?>"><?php esc_html_e( 'גילוי נאות', 'tra-vel-v2' ); ?></a></div>
	</div>
	<div class="footer-bottom page-width"><span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Tra-Vel</span><span><?php esc_html_e( 'פרטיות · נגישות · תנאי שימוש', 'tra-vel-v2' ); ?></span><?php if ( defined( 'TRA_VEL_OPEN_METEO_API_KEY' ) && TRA_VEL_OPEN_METEO_API_KEY ) : ?><a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Weather data by Open-Meteo · CC BY 4.0</a><?php endif; ?></div>
</footer>
<nav class="mobile-bottom-nav" aria-label="<?php esc_attr_e( 'ניווט מהיר', 'tra-vel-v2' ); ?>"><a class="active" href="<?php echo esc_url( home_url( '/' ) ); ?>"><i data-lucide="house"></i><?php esc_html_e( 'בית', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><?php esc_html_e( 'מפה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/#search' ) ); ?>"><i data-lucide="search"></i><?php esc_html_e( 'חיפוש', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><i data-lucide="sparkles"></i><?php esc_html_e( 'מתכנן AI', 'tra-vel-v2' ); ?></a></nav>
<?php wp_footer(); ?>
</body>
</html>
