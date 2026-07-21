<?php
/**
 * Template Name: Tra-Vel Registry Opportunity
 * Template Post Type: page
 *
 * Reusable shell for exact decision-guide and transactional-cluster owners.
 * The article body is authored and stored in WordPress; this template never
 * manufactures destination facts, prices, suppliers or long-form copy.
 *
 * @package TraVelV2
 */

$post_id = (int) get_queried_object_id();
$entry = tra_vel_v2_get_current_seo_opportunity( $post_id );

if ( ! tra_vel_v2_is_exposable_seo_opportunity( $entry ) || ! tra_vel_v2_seo_opportunity_identity_matches( $post_id, $entry ) ) {
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
	get_header();
	?>
	<main id="main-content" class="section" data-tra-vel-page="seo-opportunity-unavailable">
		<div class="page-width section-heading">
			<div><span class="eyebrow"><?php esc_html_e( 'Tra-Vel', 'tra-vel-v2' ); ?></span><h1><?php esc_html_e( 'העמוד הזה אינו זמין', 'tra-vel-v2' ); ?></h1><p><?php esc_html_e( 'אפשר לבחור יעד אחר, לפתוח את מפת החופשות או להתחיל חיפוש חדש.', 'tra-vel-v2' ); ?></p></div>
			<a class="button-link dark-button" href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="earth"></i></a>
		</div>
	</main>
	<?php
	get_footer();
	return;
}

$is_decision = 'decision-guide' === $entry['pageType'];
$map_url = tra_vel_v2_seo_opportunity_map_url( $entry );
$action_url = tra_vel_v2_seo_opportunity_action_url( $entry );
$breadcrumbs = tra_vel_v2_seo_opportunity_breadcrumb_items( $entry, $post_id );
$publication_contract = tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry );
$cluster_links = ! empty( $publication_contract['ready'] )
	? tra_vel_v2_get_public_seo_opportunity_links( $entry['cluster'], $entry['id'], 6 )
	: array();
$map_state = sanitize_key( (string) ( $entry['mapState'] ?? '' ) );
$globe_point = tra_vel_v2_seo_opportunity_coordinates( $map_state );
$map_marker = tra_vel_v2_seo_opportunity_airport_code( $map_state ) ?: $map_state;
$globe_label = sprintf(
	/* translators: %s: page search intent. */
	__( 'גלובוס תלת ממדי בהקשר של %s. הגלובוס ממוקד ללא סיבוב אוטומטי. אפשר לגרור, להשתמש בחצים או לפתוח את המפה המלאה.', 'tra-vel-v2' ),
	$entry['primaryIntent']
);

get_header();
?>
<main id="main-content" class="directory-page" data-tra-vel-page="seo-opportunity" data-opportunity-type="<?php echo esc_attr( $entry['pageType'] ); ?>" data-destination-map-state="<?php echo esc_attr( $map_state ); ?>">
	<header class="directory-hero">
		<div class="directory-hero-grid page-width">
			<div class="directory-hero-copy">
				<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'פירורי לחם', 'tra-vel-v2' ); ?>">
					<?php foreach ( $breadcrumbs as $breadcrumb_index => $breadcrumb ) : ?>
						<?php if ( ! empty( $breadcrumb['current'] ) ) : ?>
							<span aria-current="page"><?php echo esc_html( $breadcrumb['name'] ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $breadcrumb['url'] ); ?>"><?php echo esc_html( $breadcrumb['name'] ); ?></a>
						<?php endif; ?>
						<?php if ( $breadcrumb_index < count( $breadcrumbs ) - 1 ) : ?><i data-lucide="chevron-left" aria-hidden="true"></i><?php endif; ?>
					<?php endforeach; ?>
				</nav>
				<span class="eyebrow"><i data-lucide="<?php echo $is_decision ? 'circle-help' : 'scan-search'; ?>"></i><?php echo esc_html( $is_decision ? __( 'מדריך החלטה', 'tra-vel-v2' ) : __( 'השוואה ליעד', 'tra-vel-v2' ) ); ?></span>
				<h1><?php echo esc_html( $entry['primaryIntent'] ); ?></h1>
				<p><?php echo esc_html( $is_decision ? __( 'השוו את השיקולים החשובים, קבלו החלטה והמשיכו לתכנון המדויק שלכם על המפה.', 'tra-vel-v2' ) : __( 'בחרו את פרטי הנסיעה והשוו אפשרויות לפי מחיר כולל, זמן, תנאים והתאמה להרכב שלכם.', 'tra-vel-v2' ) ); ?></p>
				<div class="directory-hero-actions">
					<a class="header-cta" href="<?php echo esc_url( $action_url ); ?>"><i data-lucide="arrow-left"></i><?php echo esc_html( $entry['conversionAction'] ); ?></a>
					<a href="<?php echo esc_url( $map_url ); ?>"><i data-lucide="earth"></i><?php esc_html_e( 'פתחו את המפה המלאה', 'tra-vel-v2' ); ?></a>
				</div>
			</div>
			<div class="compact-map" aria-label="<?php echo esc_attr( $globe_label ); ?>">
				<div class="destination-globe-toolbar"><span><i data-lucide="move-3d"></i><?php esc_html_e( 'גררו לסיבוב', 'tra-vel-v2' ); ?></span><div><button data-map-zoom="in" type="button" aria-label="<?php esc_attr_e( 'הגדלת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="plus"></i></button><button data-map-zoom="out" type="button" aria-label="<?php esc_attr_e( 'הקטנת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="minus"></i></button></div></div>
				<div class="globe globe-webgl" data-globe-3d data-origin-latitude="32.0005" data-origin-longitude="34.8708" data-texture="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" tabindex="0" role="group" aria-label="<?php echo esc_attr( $globe_label ); ?>"><canvas data-globe-canvas aria-hidden="true"></canvas><noscript><img class="globe-noscript-image" src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" alt="<?php esc_attr_e( 'מפת עולם סטטית', 'tra-vel-v2' ); ?>"></noscript><svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg><span class="origin-point" data-globe-origin aria-label="<?php esc_attr_e( 'תל אביב, נקודת מוצא', 'tra-vel-v2' ); ?>"></span><?php if ( $globe_point ) : ?><a class="price-pin is-active" data-destination="<?php echo esc_attr( $map_state ); ?>" data-latitude="<?php echo esc_attr( $globe_point['latitude'] ); ?>" data-longitude="<?php echo esc_attr( $globe_point['longitude'] ); ?>" aria-label="<?php echo esc_attr( $entry['primaryIntent'] ); ?>" aria-current="location" href="<?php echo esc_url( $map_url ); ?>"><?php echo esc_html( $map_marker ); ?></a><?php endif; ?><span class="screen-reader-text" data-globe-live aria-live="polite"></span></div>
				<?php tra_vel_v2_voice_dock(); ?>
				<a class="button-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'עברו לתכנון מלא על המפה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
			</div>
		</div>
	</header>

	<article class="article-prose page-width section" aria-label="<?php echo esc_attr( $entry['primaryIntent'] ); ?>">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</article>
	<?php if ( $is_decision ) : ?>
		<?php tra_vel_v2_render_guide_evidence( $post_id ); ?>
	<?php endif; ?>
	<?php if ( $cluster_links ) : ?>
		<section class="seo-cluster-links section page-width" aria-labelledby="seo-cluster-links-title">
			<header class="seo-cluster-links-heading"><span class="eyebrow"><?php esc_html_e( 'מתכננים את אותו יעד', 'tra-vel-v2' ); ?></span><h2 id="seo-cluster-links-title"><?php esc_html_e( 'עוד החלטות והשוואות שיעזרו לסגור את התוכנית', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'עברו ישר לשאלה הבאה, בלי להתחיל את החיפוש מחדש.', 'tra-vel-v2' ); ?></p></header>
			<div class="seo-cluster-link-grid">
				<?php foreach ( $cluster_links as $cluster_link ) : ?>
					<a class="seo-cluster-link-card" href="<?php echo esc_url( $cluster_link['url'] ); ?>">
						<span class="seo-cluster-link-kind"><i data-lucide="<?php echo 'decision' === $cluster_link['kind'] ? 'circle-help' : 'scan-search'; ?>"></i><?php echo esc_html( 'decision' === $cluster_link['kind'] ? __( 'מדריך לבחירה', 'tra-vel-v2' ) : __( 'השוואה לנסיעה', 'tra-vel-v2' ) ); ?></span>
						<h3><?php echo esc_html( $cluster_link['title'] ); ?></h3>
						<p><?php echo esc_html( $cluster_link['cta'] ); ?></p>
						<strong><?php esc_html_e( 'פתחו ובדקו', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></strong>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="section page-width experience-next" aria-labelledby="seo-opportunity-next-step">
		<div><span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span><h2 id="seo-opportunity-next-step"><?php echo esc_html( $entry['conversionAction'] ); ?></h2><p><?php echo esc_html( $is_decision ? __( 'שמרו את ההחלטה בתוך התכנון המלא ובדקו איך היא משפיעה על המסלול, הזמן והתקציב.', 'tra-vel-v2' ) : __( 'השלימו תאריכים והרכב כדי לבדוק אפשרויות מתאימות. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ) ); ?></p></div>
		<a class="button-link dark-button" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $entry['conversionAction'] ); ?><i data-lucide="arrow-left"></i></a>
	</section>
</main>
<?php get_footer(); ?>
