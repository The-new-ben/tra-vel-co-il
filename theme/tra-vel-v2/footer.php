<?php
/**
 * Site footer.
 *
 * @package TraVelV2
 */
if ( is_page_template( 'page-map.php' ) ) {
	tra_vel_v2_mobile_bottom_nav();
	?>
	<?php wp_footer(); ?>
	</body>
	</html>
	<?php
	return;
}
?>
<footer class="site-footer">
	<div class="footer-grid page-width">
		<div class="footer-brand"><?php tra_vel_v2_brand(); ?><p><?php esc_html_e( 'טיסות, מלונות, חבילות, ביטוח ומדריכי יעד במקום אחד.', 'tra-vel-v2' ); ?></p></div>
		<div class="footer-column"><strong><?php esc_html_e( 'משווים ובודקים', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><?php esc_html_e( 'מה חשוב לבדוק בביטוח', 'tra-vel-v2' ); ?></a></div>
		<div class="footer-column"><strong><?php esc_html_e( 'מגלים', 'tra-vel-v2' ); ?></strong><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'מפת החופשות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'מדריכים', 'tra-vel-v2' ); ?></a></div>
		<div class="footer-column"><strong>Tra-Vel</strong><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'אודות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>"><?php esc_html_e( 'איך אנחנו משווים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/disclosure/' ) ); ?>"><?php esc_html_e( 'גילוי נאות', 'tra-vel-v2' ); ?></a></div>
	</div>
	<div class="footer-bottom page-width"><span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Tra-Vel</span><span><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'פרטיות', 'tra-vel-v2' ); ?></a> · <a href="<?php echo esc_url( home_url( '/accessibility/' ) ); ?>"><?php esc_html_e( 'נגישות', 'tra-vel-v2' ); ?></a> · <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'תנאי שימוש', 'tra-vel-v2' ); ?></a></span><?php if ( defined( 'TRA_VEL_OPEN_METEO_API_KEY' ) && TRA_VEL_OPEN_METEO_API_KEY ) : ?><a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Weather data by Open-Meteo · CC BY 4.0</a><?php endif; ?></div>
</footer>
<?php tra_vel_v2_mobile_bottom_nav(); ?>
<?php wp_footer(); ?>
</body>
</html>
