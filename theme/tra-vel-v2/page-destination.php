<?php
/**
 * Template Name: Tra-Vel Destination Guide
 * Template Post Type: page, destination
 *
 * @package TraVelV2
 */

require_once __DIR__ . '/inc/guide-html.php';

get_header();

while ( have_posts() ) :
	the_post();
	$destination_slug = get_post_field( 'post_name', get_the_ID() );
	$image_credit     = array();
	$hero = get_the_post_thumbnail_url( get_the_ID(), 'tra-vel-hero' );
	if ( ! $hero ) {
		$fallback_images = array(
			'athens'   => 'images/athens-acropolis.jpg',
			'budapest' => 'images/hero-budapest-1600.webp',
			'prague'   => 'images/city-prague.webp',
			'vienna'   => 'images/city-vienna.webp',
			'thailand' => 'images/thailand.jpg',
			'bangkok'  => 'images/thailand.jpg',
			'dubai'    => 'images/city-dubai.webp',
			'tokyo'    => 'images/city-tokyo.webp',
			'lisbon'   => 'images/city-lisbon.webp',
		);
		$fallback_image_credits = array(
			'athens' => array(
				'label' => 'Photo: Davide Aversa, CC BY-SA 4.0',
				'url'   => 'https://commons.wikimedia.org/wiki/File:View_of_Athen%27s_Acropolis.jpg',
			),
			'dubai'  => array(
				'label' => 'Photo: Tim Reckmann, CC BY 2.0',
				'url'   => 'https://commons.wikimedia.org/wiki/File:Dubai_Skyline_mit_Burj_Khalifa_(18241030269).jpg',
			),
			'tokyo'  => array(
				'label' => 'Photo: Morio, CC BY-SA 3.0',
				'url'   => 'https://commons.wikimedia.org/wiki/File:Skyscrapers_of_Shinjuku_2009_January_(revised).jpg',
			),
			'lisbon' => array(
				'label' => 'Photo: Dale Cruse, CC BY 4.0',
				'url'   => 'https://commons.wikimedia.org/wiki/File:Alfama_Rooftops_and_Tagus_River_View,_Lisbon_(54733698959).jpg',
			),
		);
		$fallback_image = $fallback_images[ $destination_slug ] ?? 'images/earth-blue-marble.jpg';
		$image_credit   = $fallback_image_credits[ $destination_slug ] ?? array();
		$hero = tra_vel_v2_asset_uri( $fallback_image );
	}
	$title = get_the_title() ?: __( 'יעד', 'tra-vel-v2' );
	$guide_profile = tra_vel_v2_get_guide_profile( get_the_ID() );
	$guide_contract = tra_vel_v2_get_guide_publication_contract( get_the_ID() );
	$guide_is_ready = ! empty( $guide_contract['ready'] );
	$guide_breadcrumbs = tra_vel_v2_guide_breadcrumb_items( get_the_ID() );
	$map_state      = $guide_profile['map_state'] ?: get_post_field( 'post_name', get_the_ID() );
	$guide_slug     = sanitize_title( get_post_field( 'post_name', get_the_ID() ) );
	$guide_registry_path = tra_vel_v2_seo_opportunity_path_from_url( get_permalink( get_the_ID() ) );
	$guide_registry_entry = $guide_registry_path ? tra_vel_v2_get_seo_opportunity_by_path( $guide_registry_path ) : null;
	$guide_registry_matches = is_array( $guide_registry_entry )
		&& 'destination-hub' === ( $guide_registry_entry['pageType'] ?? '' )
		&& in_array( $guide_registry_entry['status'] ?? '', array( 'live', 'content-ready' ), true )
		&& $guide_registry_path === ( $guide_registry_entry['canonicalPath'] ?? '' )
		&& sanitize_key( (string) ( $guide_registry_entry['cluster'] ?? '' ) ) === sanitize_key( (string) $map_state )
		&& sanitize_key( (string) ( $guide_registry_entry['mapState'] ?? '' ) ) === sanitize_key( (string) $map_state );
	$guide_cluster_links = $guide_is_ready
		&& 'publish-ready' === ( $guide_profile['publication_status'] ?? '' )
		&& 'publish' === get_post_status( get_the_ID() )
		&& 'page-destination.php' === get_page_template_slug( get_the_ID() )
		&& $guide_registry_matches
		? tra_vel_v2_get_public_seo_opportunity_links( $guide_registry_entry['cluster'], '', 6 )
		: array();
	$guide_content_html = get_the_content();
	$guide_content_ids  = tra_vel_v2_extract_guide_content_ids( $guide_content_html );
	$guide_anchor = static function ( $candidates, $fallback ) use ( $guide_content_ids ) {
		foreach ( $candidates as $candidate ) {
			if ( array_key_exists( $candidate, $guide_content_ids ) ) {
				return $candidate;
			}
		}
		return $fallback;
	};
	$guide_anchor_candidates = static function ( $suffixes ) use ( $guide_slug ) {
		$candidates = array_values( $suffixes );
		foreach ( $suffixes as $suffix ) {
			$candidates[] = $guide_slug . '-' . $suffix;
		}
		return array_values( array_unique( $candidates ) );
	};
	$guide_intro_anchor     = $guide_anchor( $guide_anchor_candidates( array( 'intro', 'fit', 'who' ) ), 'intro' );
	$guide_flights_anchor   = $guide_anchor( $guide_anchor_candidates( array( 'flights', 'flight', 'flight-choice', 'flight-chain', 'airport', 'airport-choice' ) ), 'flights' );
	$guide_costs_anchor     = $guide_anchor( $guide_anchor_candidates( array( 'costs', 'budget' ) ), 'costs' );
	$guide_insurance_anchor = $guide_anchor( $guide_anchor_candidates( array( 'insurance', 'health' ) ), 'insurance' );
	$guide_faq_anchor       = $guide_anchor( $guide_anchor_candidates( array( 'faq', 'booking', 'booking-order' ) ), 'faq' );
	$globe_points  = array(
		'budapest' => array( 'latitude' => '47.4979', 'longitude' => '19.0402' ),
		'athens'   => array( 'latitude' => '37.9838', 'longitude' => '23.7275' ),
		'prague'   => array( 'latitude' => '50.0755', 'longitude' => '14.4378' ),
		'vienna'   => array( 'latitude' => '48.2082', 'longitude' => '16.3738' ),
		'dubai'    => array( 'latitude' => '25.2048', 'longitude' => '55.2708' ),
		'bangkok'  => array( 'latitude' => '13.7563', 'longitude' => '100.5018' ),
		'tokyo'    => array( 'latitude' => '35.6762', 'longitude' => '139.6503' ),
		'lisbon'   => array( 'latitude' => '38.7223', 'longitude' => '-9.1393' ),
	);
	$globe_point = $globe_points[ $map_state ] ?? null;
	$map_url     = $globe_point
		? add_query_arg( 'destination', $map_state, home_url( '/travel-map/' ) )
		: home_url( '/travel-map/' );
	$globe_label = $globe_point
		? sprintf( __( 'גלובוס תלת ממדי של המסלול מתל אביב אל %s. גררו לסיבוב או השתמשו בחצים במקלדת.', 'tra-vel-v2' ), $title )
		: __( 'גלובוס תלת ממדי לתכנון חופשה. גררו לסיבוב או פתחו את מפת החופשות כדי לבחור יעד נתמך.', 'tra-vel-v2' );
	?>
<main id="main-content" data-tra-vel-page="destination" data-guide-slug="<?php echo esc_attr( $guide_slug ); ?>" data-destination-map-state="<?php echo esc_attr( $map_state ); ?>">
		<section class="destination-hero"><img src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( $title ); ?>">
			<div class="destination-hero-content page-width">
				<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'פירורי לחם', 'tra-vel-v2' ); ?>">
					<?php foreach ( $guide_breadcrumbs as $breadcrumb_index => $breadcrumb ) : ?>
						<?php if ( ! empty( $breadcrumb['current'] ) ) : ?>
							<span aria-current="page"><?php echo esc_html( $breadcrumb['name'] ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $breadcrumb['url'] ); ?>"><?php echo esc_html( $breadcrumb['name'] ); ?></a>
						<?php endif; ?>
						<?php if ( $breadcrumb_index < count( $guide_breadcrumbs ) - 1 ) : ?><i data-lucide="chevron-left" aria-hidden="true"></i><?php endif; ?>
					<?php endforeach; ?>
				</nav>
				<?php if ( $guide_is_ready ) : ?>
					<span class="destination-badge"><i data-lucide="badge-check"></i><?php printf( esc_html__( 'מדריך עומק · המקורות נבדקו %s', 'tra-vel-v2' ), esc_html( $guide_profile['checked'] ) ); ?></span>
				<?php else : ?>
					<span class="destination-badge is-pending"><i data-lucide="map-pinned"></i><?php esc_html_e( 'מפת יעד, אזורי לינה וצעדים לתכנון', 'tra-vel-v2' ); ?></span>
				<?php endif; ?>
				<h1><?php echo esc_html( $title ); ?>.<br><em><?php esc_html_e( 'הטיול שמתאים לכם.', 'tra-vel-v2' ); ?></em></h1>
				<p><?php echo esc_html( get_the_excerpt() ?: __( 'עונות, אזורי לינה, טיסות, מסלולים, תקציב וביטוח. כל מה שצריך לדעת לפני שבודקים מחיר.', 'tra-vel-v2' ) ); ?></p>
				<div class="destination-actions"><a href="<?php echo esc_url( $map_url ); ?>"><i data-lucide="route"></i><?php esc_html_e( 'תכננו טיול על המפה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><i data-lucide="sparkles"></i><?php esc_html_e( 'שאלו את מתכנן החופשה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/saved/' ) ); ?>"><i data-lucide="bookmark"></i><?php esc_html_e( 'לנסיעות שמורות', 'tra-vel-v2' ); ?></a></div>
			</div>
			<?php if ( $image_credit ) : ?><a class="destination-image-credit" href="<?php echo esc_url( $image_credit['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $image_credit['label'] ); ?></a><?php endif; ?>
		</section>
		<div class="fact-ribbon page-width"><div><small><?php esc_html_e( 'זמן טיסה ישירה', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( get_post_meta( get_the_ID(), '_tra_vel_flight_time', true ) ?: __( 'בדקו לפי התאריכים שלכם', 'tra-vel-v2' ) ); ?></strong></div><div><small><?php esc_html_e( 'תקציב יומי לזוג', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( get_post_meta( get_the_ID(), '_tra_vel_daily_budget', true ) ?: __( 'בנו תקציב אישי', 'tra-vel-v2' ) ); ?></strong></div><div><small><?php esc_html_e( 'חלון מומלץ', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( get_post_meta( get_the_ID(), '_tra_vel_best_season', true ) ?: __( 'לפי מזג אוויר ועומס', 'tra-vel-v2' ) ); ?></strong></div><div><small><?php esc_html_e( 'מתאים במיוחד', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( get_post_meta( get_the_ID(), '_tra_vel_best_for', true ) ?: __( 'לפי הקצב שלכם', 'tra-vel-v2' ) ); ?></strong></div></div>
		<nav class="article-tabs page-width" aria-label="<?php esc_attr_e( 'תוכן המדריך', 'tra-vel-v2' ); ?>"><a href="#map"><?php esc_html_e( 'מפת מסלול', 'tra-vel-v2' ); ?></a><a href="#decision-when"><?php esc_html_e( 'מתי לטוס', 'tra-vel-v2' ); ?></a><a href="#decision-areas"><?php esc_html_e( 'איפה לישון', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_costs_anchor ); ?>"><?php esc_html_e( 'עלויות', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_flights_anchor ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_insurance_anchor ); ?>"><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></a><a href="#guide"><?php esc_html_e( 'המדריך המלא', 'tra-vel-v2' ); ?></a></nav>
		<?php tra_vel_v2_render_guide_evidence( get_the_ID() ); ?>

		<section class="article-section" id="map">
			<div class="destination-map-band page-width">
				<div class="destination-map-copy"><span class="eyebrow"><?php esc_html_e( 'תכננו על המפה', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'בחרו נקודה וקבלו את הצעד הבא.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'מתחילים בתל אביב ובודקים טיסה ישירה, קונקשן או עצירת לילה. אחר כך עוברים למלונות, תחבורה ופרטי ביטוח.', 'tra-vel-v2' ); ?></p><ul><li><i data-lucide="badge-dollar-sign"></i><?php esc_html_e( 'תקציב תכנון לכל הנוסעים', 'tra-vel-v2' ); ?></li><li><i data-lucide="clock-3"></i><?php esc_html_e( 'זמן מדלת לדלת', 'tra-vel-v2' ); ?></li><li><i data-lucide="triangle-alert"></i><?php esc_html_e( 'סיכון בכרטיסים נפרדים ובקונקשן', 'tra-vel-v2' ); ?></li><li><i data-lucide="sparkles"></i><?php esc_html_e( 'חלופות שעשויות לחסוך זמן או כסף', 'tra-vel-v2' ); ?></li></ul><a class="header-cta" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?></a></div>
				<div class="compact-map"><div class="destination-globe-toolbar"><span><i data-lucide="move-3d"></i><?php esc_html_e( 'גררו לסיבוב', 'tra-vel-v2' ); ?></span><div><button data-map-zoom="in" type="button" aria-label="<?php esc_attr_e( 'הגדלת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="plus"></i></button><button data-map-zoom="out" type="button" aria-label="<?php esc_attr_e( 'הקטנת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="minus"></i></button></div></div>
					<div class="globe globe-webgl" data-globe-3d data-origin-latitude="32.0005" data-origin-longitude="34.8708" data-texture="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" tabindex="0" role="group" aria-label="<?php echo esc_attr( $globe_label ); ?>"><canvas data-globe-canvas aria-hidden="true"></canvas><noscript><img class="globe-noscript-image" src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" alt="<?php esc_attr_e( 'מפת עולם סטטית', 'tra-vel-v2' ); ?>"></noscript><svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg><span class="origin-point" data-globe-origin aria-label="<?php esc_attr_e( 'תל אביב, נקודת מוצא', 'tra-vel-v2' ); ?>"></span><?php if ( $globe_point ) : ?><a class="price-pin is-active" data-destination="<?php echo esc_attr( $map_state ); ?>" data-latitude="<?php echo esc_attr( $globe_point['latitude'] ); ?>" data-longitude="<?php echo esc_attr( $globe_point['longitude'] ); ?>" aria-current="location" href="<?php echo esc_url( $map_url ); ?>"><?php echo esc_html( $title ); ?></a><?php endif; ?><span class="screen-reader-text" data-globe-live aria-live="polite"></span></div>
					<?php tra_vel_v2_voice_dock(); ?>
					<article class="map-result" data-guide-map-card><img class="map-result-image" src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( $title ); ?>"><div class="map-result-body"><div class="result-top"><div><small><?php esc_html_e( 'דרכי הגעה', 'tra-vel-v2' ); ?></small><h3><?php echo esc_html( $title ); ?></h3></div></div><div class="result-tags"><span><?php esc_html_e( 'זמן כולל', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'ישיר או קונקשן', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'כבודה ותנאים', 'tra-vel-v2' ); ?></span></div><div class="result-price"><div><small><?php esc_html_e( 'תקציב תכנון לכל הנוסעים', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'בחרו תאריכים', 'tra-vel-v2' ); ?></strong></div><p><?php esc_html_e( 'פתחו את המפה כדי להשוות אפשרויות. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></p><a href="<?php echo esc_url( $map_url ); ?>"><?php printf( esc_html__( 'תכננו את %s על המפה', 'tra-vel-v2' ), esc_html( $title ) ); ?><i data-lucide="arrow-left"></i></a></div></div></article>
				</div>
			</div>
		</section>

		<section class="article-section" id="decision-when"><div class="page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'מחיר, מזג אוויר ועומס באותה תמונה', 'tra-vel-v2' ); ?></span><h2><?php printf( esc_html__( 'מתי כדאי לטוס ל%s?', 'tra-vel-v2' ), esc_html( $title ) ); ?></h2><p><?php esc_html_e( 'אין חודש אחד נכון. השוו מזג אוויר, עומס וגמישות, ואז בדקו מחיר לפי התאריכים שלכם.', 'tra-vel-v2' ); ?></p></div></div><div class="month-grid"><div class="month-cell"><span><?php esc_html_e( 'אוקטובר', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div><div class="month-cell best"><b><?php esc_html_e( 'השוו גמישות', 'tra-vel-v2' ); ?></b><span><?php esc_html_e( 'נובמבר', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div><div class="month-cell"><span><?php esc_html_e( 'דצמבר', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div><div class="month-cell"><span><?php esc_html_e( 'ינואר', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div><div class="month-cell"><span><?php esc_html_e( 'פברואר', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div><div class="month-cell"><span><?php esc_html_e( 'מרץ', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( 'מחיר לפי תאריכים', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'מזג אוויר ועומס', 'tra-vel-v2' ); ?></small></div></div></div></section>

		<section class="article-section" id="decision-areas"><div class="page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'איפה תהיו ואיך זה מרגיש', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'אזורי הלינה שמעצבים את הטיול', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'התחילו מסוג החוויה, בחרו אזור, ואז השוו מלונות וחדרים שמתאימים לנוסעים שלכם.', 'tra-vel-v2' ); ?></p></div></div><div class="area-grid"><article class="area-card"><span>01 · <?php esc_html_e( 'נגישות', 'tra-vel-v2' ); ?></span><h3><?php esc_html_e( 'מרכז וקצב', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'קרוב לנקודות העניין המרכזיות ועם פחות זמן תחבורה.', 'tra-vel-v2' ); ?></p><small><?php esc_html_e( 'בדקו רעש, הליכה ותחבורה', 'tra-vel-v2' ); ?></small><footer><strong><?php esc_html_e( 'בדקו מלונות', 'tra-vel-v2' ); ?></strong><a href="#map"><?php esc_html_e( 'ראו על המפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></a></footer></article><article class="area-card"><span>02 · <?php esc_html_e( 'איזון', 'tra-vel-v2' ); ?></span><h3><?php esc_html_e( 'שכונה מקומית', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'יותר אופי מקומי, מרחב ומחיר עם נסיעה קצרה למרכז.', 'tra-vel-v2' ); ?></p><small><?php esc_html_e( 'בדקו קו תחבורה בשעות שלכם', 'tra-vel-v2' ); ?></small><footer><strong><?php esc_html_e( 'בדקו מלונות', 'tra-vel-v2' ); ?></strong><a href="#map"><?php esc_html_e( 'ראו על המפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></a></footer></article><article class="area-card"><span>03 · <?php esc_html_e( 'תמורה', 'tra-vel-v2' ); ?></span><h3><?php esc_html_e( 'מחוץ למרכז', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'חדר גדול יותר או מחיר נמוך יותר, מול זמן ועלות מעבר.', 'tra-vel-v2' ); ?></p><small><?php esc_html_e( 'חשבו מחיר כולל ולא רק חדר', 'tra-vel-v2' ); ?></small><footer><strong><?php esc_html_e( 'בדקו מלונות', 'tra-vel-v2' ); ?></strong><a href="#map"><?php esc_html_e( 'ראו על המפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></a></footer></article></div></div></section>

		<section class="article-section" id="guide"><div class="longform-layout page-width"><aside class="article-toc"><strong><?php esc_html_e( 'במדריך', 'tra-vel-v2' ); ?></strong><a href="#<?php echo esc_attr( $guide_intro_anchor ); ?>"><?php esc_html_e( 'איך מתחילים', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_flights_anchor ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_costs_anchor ); ?>"><?php esc_html_e( 'תקציב', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_insurance_anchor ); ?>"><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></a><a href="#<?php echo esc_attr( $guide_faq_anchor ); ?>"><?php esc_html_e( 'שאלות', 'tra-vel-v2' ); ?></a></aside><article class="article-prose"><span class="eyebrow"><?php esc_html_e( 'כל מה שצריך כדי לבחור נכון', 'tra-vel-v2' ); ?></span>
			<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<h2 id="intro"><?php printf( esc_html__( 'איך מתכננים %s בלי להעתיק מסלול של מישהו אחר', 'tra-vel-v2' ), esc_html( $title ) ); ?></h2><p class="lead"><?php esc_html_e( 'המסלול הנכון מתחיל במספר הימים, האנשים והקצב. רק אחר כך בוחרים מקומות. כל חלק במדריך עוזר לקבל החלטה, להשוות חלופה או לבצע את הצעד הבא.', 'tra-vel-v2' ); ?></p><div class="inline-data"><span class="data-icon"><i data-lucide="database"></i></span><div><h3><?php esc_html_e( 'איזה מידע צריך לבדוק מחדש?', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'מחירי טיסות ומלונות, זמינות, מטבע, מזג אוויר, זמני נסיעה והתראות עשויים להשתנות. הסתמכו על מידע עם מקור וזמן בדיקה, ובדקו שוב לפני החלטה.', 'tra-vel-v2' ); ?></p></div></div><h2 id="flights"><?php esc_html_e( 'טיסה ישירה או קונקשן?', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'בדקו זמן כולל, טרמינל, כבודה, הגנת קונקשן, מדיניות שינוי וסיכון בכרטיסים נפרדים. אם שוקלים עצירת לילה, הוסיפו לינה והעברות לפירוט העלויות לפני שמשווים.', 'tra-vel-v2' ); ?></p><h2 id="costs"><?php esc_html_e( 'כמה עולה הטיול?', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'בנו תקציב לפי טיסה, לינה, תחבורה, אוכל, אטרקציות, תקשורת וביטוח. עדכנו כל רכיב לפי המקור ותאריך הבדיקה שלו.', 'tra-vel-v2' ); ?></p><h2 id="insurance"><?php esc_html_e( 'מה לבדוק בביטוח לפני הנסיעה', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'התחילו במשך הנסיעה, בגילאי הנוסעים ובפעילויות. לפני רכישה, בדקו מי הספק המורשה, מה כלול, החרגות, גבולות אחריות וגילוי נאות.', 'tra-vel-v2' ); ?></p><h2 id="faq"><?php esc_html_e( 'שאלות נפוצות', 'tra-vel-v2' ); ?></h2><div class="faq-list"><details open><summary><?php esc_html_e( 'כמה ימים צריך?', 'tra-vel-v2' ); ?></summary><p><?php esc_html_e( 'התשובה נגזרת מהאזורים, זמני המעבר וקצב הנסיעה ולא ממספר אחד שמתאים לכולם.', 'tra-vel-v2' ); ?></p></details><details><summary><?php esc_html_e( 'האם להזמין טיסה ומלון יחד?', 'tra-vel-v2' ); ?></summary><p><?php esc_html_e( 'בדקו מחיר, תנאי ביטול, סוג חדר, כבודה וגמישות בכל אפשרות לפני שבוחרים.', 'tra-vel-v2' ); ?></p></details></div>
			<?php endif; ?>
		</article></div></section>
		<?php if ( $guide_cluster_links ) : ?>
			<section class="seo-cluster-links section page-width" aria-labelledby="destination-cluster-links-title">
				<header class="seo-cluster-links-heading"><span class="eyebrow"><?php esc_html_e( 'ממשיכים לתכנן', 'tra-vel-v2' ); ?></span><h2 id="destination-cluster-links-title"><?php printf( esc_html__( 'החלטות והשוואות ל%s', 'tra-vel-v2' ), esc_html( $title ) ); ?></h2><p><?php esc_html_e( 'בחרו את השאלה הבאה וקבלו את המידע שצריך כדי להתקדם.', 'tra-vel-v2' ); ?></p></header>
				<div class="seo-cluster-link-grid">
					<?php foreach ( $guide_cluster_links as $cluster_link ) : ?>
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
	</main>
	<?php
endwhile;

get_footer();
