<?php
/**
 * Template Name: Tra-Vel Pillar Earth
 * Template Post Type: page
 *
 * A vertical pillar hub: the 3D Earth covered with one vertical's points
 * above server-rendered Hebrew pillar content. The vertical is derived from
 * the page slug and everything renders from tra_vel_v2_pillar_config(), so
 * diving today and cruises, family or conventions later share this file.
 *
 * @package TraVelV2
 */

$pillar_kind   = tra_vel_v2_pillar_kind_for_page();
$pillar        = tra_vel_v2_pillar_config( $pillar_kind );
$pillar_ready  = tra_vel_v2_pillar_page_is_publishable();
$map_url       = home_url( '/travel-map/' );
$planner_url   = home_url( '/ai-planner/' );

get_header();

if ( ! $pillar || ! $pillar_ready ) :
	?>
	<main id="main-content" class="section" data-tra-vel-page="pillar" data-pillar-kind="<?php echo esc_attr( $pillar_kind ); ?>">
		<div class="page-width section-heading">
			<div><span class="eyebrow"><?php esc_html_e( 'Tra-Vel', 'tra-vel-v2' ); ?></span><h1><?php esc_html_e( 'העמוד הזה אינו זמין כרגע', 'tra-vel-v2' ); ?></h1><p><?php esc_html_e( 'אפשר לחזור לדף הבית, לפתוח את מפת החופשות או להתחיל חיפוש חדש.', 'tra-vel-v2' ); ?></p></div>
			<a class="button-link dark-button" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="earth"></i></a>
		</div>
	</main>
	<?php
	get_footer();
	return;
endif;

$pillar_points  = $pillar['points'];
$pillar_cta_url = add_query_arg( 'destination', $pillar['planner_destination'], $planner_url );
$pillar_spokes  = tra_vel_v2_pillar_published_spokes( $pillar );
?>
<main id="main-content" class="pillar-page" data-tra-vel-page="pillar" data-pillar-kind="<?php echo esc_attr( $pillar_kind ); ?>">
	<header class="directory-hero pillar-hero">
		<div class="page-width">
			<div class="pillar-hero-copy">
				<span class="eyebrow"><i data-lucide="<?php echo esc_attr( $pillar['icon'] ); ?>"></i><?php echo esc_html( $pillar['eyebrow'] ); ?></span>
				<h1><?php echo esc_html( $pillar['title'] ); ?> <em><?php echo esc_html( $pillar['title_em'] ); ?></em></h1>
				<p><?php echo esc_html( $pillar['intro'] ); ?></p>
				<div class="directory-hero-actions">
					<a class="header-cta" href="#pillar-sites"><i data-lucide="list"></i><?php echo esc_html( $pillar['board_title'] ); ?></a>
					<a href="<?php echo esc_url( $pillar_cta_url ); ?>"><i data-lucide="sparkles"></i><?php echo esc_html( $pillar['cta_label'] ); ?></a>
				</div>
			</div>
			<div class="compact-map pillar-globe-panel" aria-label="<?php echo esc_attr( $pillar['globe_label'] ); ?>">
				<div class="destination-globe-toolbar"><span><i data-lucide="move-3d"></i><?php esc_html_e( 'גררו לסיבוב', 'tra-vel-v2' ); ?></span><div><button data-map-zoom="in" type="button" aria-label="<?php esc_attr_e( 'הגדלת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="plus"></i></button><button data-map-zoom="out" type="button" aria-label="<?php esc_attr_e( 'הקטנת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="minus"></i></button></div></div>
				<div class="globe globe-webgl" data-globe-3d data-globe-pillar="true" data-globe-card-details="#pillar-sites" data-origin-latitude="32.0005" data-origin-longitude="34.8708" data-texture="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" tabindex="0" role="group" aria-label="<?php echo esc_attr( $pillar['globe_label'] ); ?>">
					<canvas data-globe-canvas aria-hidden="true"></canvas>
					<noscript><img class="globe-noscript-image" src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" alt="<?php esc_attr_e( 'מפת עולם סטטית', 'tra-vel-v2' ); ?>"></noscript>
					<svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg>
					<span class="origin-point" data-globe-origin title="<?php esc_attr_e( 'תל אביב', 'tra-vel-v2' ); ?>"></span>
					<?php
					foreach ( $pillar_points as $pillar_point ) :
						$point_id        = sanitize_key( $pillar_point['id'] ?? '' );
						$point_latitude  = isset( $pillar_point['latitude'] ) ? (float) $pillar_point['latitude'] : null;
						$point_longitude = isset( $pillar_point['longitude'] ) ? (float) $pillar_point['longitude'] : null;
						if ( ! $point_id || null === $point_latitude || null === $point_longitude || $point_latitude < -90 || $point_latitude > 90 || $point_longitude < -180 || $point_longitude > 180 ) {
							continue;
						}
						$point_difficulty = max( 1, min( 3, (int) ( $pillar_point['difficulty'] ?? 2 ) ) );
						$point_static_x   = round( ( ( $point_longitude + 180 ) / 360 ) * 100, 3 );
						$point_static_y   = round( ( ( 90 - $point_latitude ) / 180 ) * 100, 3 );
						/* translators: 1: site name, 2: difficulty label. */
						$point_aria = sprintf( __( '%1$s, רמת קושי %2$s. פתיחת פרטי האתר.', 'tra-vel-v2' ), $pillar_point['name'], tra_vel_v2_pillar_difficulty_label( $point_difficulty ) );
						?>
						<button class="price-pin pillar-pin" type="button" data-destination="<?php echo esc_attr( $point_id ); ?>" data-latitude="<?php echo esc_attr( $point_latitude ); ?>" data-longitude="<?php echo esc_attr( $point_longitude ); ?>" data-selection-bound="true" data-site-name="<?php echo esc_attr( $pillar_point['name'] ); ?>" style="--pin-static-x:<?php echo esc_attr( $point_static_x ); ?>%;--pin-static-y:<?php echo esc_attr( $point_static_y ); ?>%;" aria-label="<?php echo esc_attr( $point_aria ); ?>" aria-pressed="false"><span class="pillar-pin-name"><?php echo esc_html( $pillar_point['pin_label'] ); ?></span><span class="pillar-pin-stars" data-difficulty="<?php echo esc_attr( $point_difficulty ); ?>" aria-hidden="true"><?php echo esc_html( tra_vel_v2_pillar_difficulty_stars( $point_difficulty ) ); ?></span></button>
					<?php endforeach; ?>
					<span class="screen-reader-text" data-globe-live role="status" aria-live="polite" aria-atomic="true"></span>
				</div>
				<?php tra_vel_v2_voice_dock(); ?>
				<a class="button-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'עברו לתכנון מלא על המפה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
			</div>
		</div>
	</header>

	<section class="section page-width pillar-sites" id="pillar-sites" data-pillar-sites aria-labelledby="pillar-sites-title">
		<header class="section-heading">
			<div><span class="eyebrow"><i data-lucide="map-pin"></i><?php echo esc_html( $pillar['eyebrow'] ); ?></span><h2 id="pillar-sites-title"><?php echo esc_html( $pillar['board_title'] ); ?></h2><p><?php echo esc_html( $pillar['board_intro'] ); ?></p></div>
		</header>
		<div class="pillar-site-grid">
			<?php
			foreach ( $pillar_points as $pillar_point ) :
				$point_id = sanitize_key( $pillar_point['id'] ?? '' );
				if ( ! $point_id ) {
					continue;
				}
				$point_difficulty = max( 1, min( 3, (int) ( $pillar_point['difficulty'] ?? 2 ) ) );
				$point_depth_min  = (int) ( $pillar_point['depth_min'] ?? 0 );
				$point_depth_max  = (int) ( $pillar_point['depth_max'] ?? 0 );
				?>
				<article class="pillar-site-card" id="pillar-site-<?php echo esc_attr( $point_id ); ?>" data-pillar-site="<?php echo esc_attr( $point_id ); ?>">
					<header class="pillar-site-head">
						<h3><?php echo esc_html( $pillar_point['name'] ); ?></h3>
						<small><bdi dir="ltr"><?php echo esc_html( $pillar_point['name_en'] ); ?></bdi></small>
					</header>
					<p class="pillar-site-difficulty"><span class="pillar-pin-stars" data-difficulty="<?php echo esc_attr( $point_difficulty ); ?>" aria-hidden="true"><?php echo esc_html( tra_vel_v2_pillar_difficulty_stars( $point_difficulty ) ); ?></span><span><?php
					/* translators: %s: difficulty label. */
					echo esc_html( sprintf( __( 'רמת קושי: %s', 'tra-vel-v2' ), tra_vel_v2_pillar_difficulty_label( $point_difficulty ) ) ); ?></span></p>
					<dl class="pillar-site-facts">
						<div><dt><?php esc_html_e( 'טווח עומק אופייני', 'tra-vel-v2' ); ?></dt><dd><?php
						/* translators: 1: minimum depth in meters, 2: maximum depth in meters. */
						echo esc_html( sprintf( __( '%1$d עד %2$d מטר', 'tra-vel-v2' ), $point_depth_min, $point_depth_max ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'עונה מקובלת', 'tra-vel-v2' ); ?></dt><dd><?php echo esc_html( $pillar_point['season'] ); ?></dd></div>
					</dl>
					<p class="pillar-site-summary"><?php echo esc_html( $pillar_point['summary'] ); ?></p>
					<button class="pillar-site-focus" type="button" data-pillar-site-focus="<?php echo esc_attr( $point_id ); ?>" hidden><i data-lucide="earth"></i><?php esc_html_e( 'הצגה על הכדור', 'tra-vel-v2' ); ?></button>
				</article>
			<?php endforeach; ?>
		</div>
		<p class="pillar-safety-note"><i data-lucide="shield-alert" aria-hidden="true"></i><?php echo esc_html( $pillar['safety_note'] ); ?></p>
	</section>

	<article class="article-prose page-width section pillar-article" aria-label="<?php echo esc_attr( $pillar['eyebrow'] ); ?>">
		<?php foreach ( $pillar['sections'] as $pillar_section ) : ?>
			<section id="pillar-<?php echo esc_attr( sanitize_key( $pillar_section['id'] ?? '' ) ); ?>">
				<h2><?php echo esc_html( $pillar_section['heading'] ); ?></h2>
				<?php foreach ( (array) $pillar_section['paragraphs'] as $pillar_paragraph ) : ?>
					<p><?php echo esc_html( $pillar_paragraph ); ?></p>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	</article>

	<section class="section page-width pillar-spokes" aria-labelledby="pillar-spokes-title">
		<header class="seo-cluster-links-heading"><span class="eyebrow"><?php esc_html_e( 'להמשך קריאה', 'tra-vel-v2' ); ?></span><h2 id="pillar-spokes-title"><?php esc_html_e( 'מדריכים מעמיקים לאותו נושא', 'tra-vel-v2' ); ?></h2></header>
		<?php if ( $pillar_spokes ) : ?>
			<div class="seo-cluster-link-grid">
				<?php foreach ( $pillar_spokes as $pillar_spoke ) : ?>
					<a class="seo-cluster-link-card" href="<?php echo esc_url( $pillar_spoke['url'] ); ?>">
						<span class="seo-cluster-link-kind"><i data-lucide="book-open"></i><?php esc_html_e( 'מדריך מעמיק', 'tra-vel-v2' ); ?></span>
						<h3><?php echo esc_html( $pillar_spoke['title'] ); ?></h3>
						<?php if ( $pillar_spoke['description'] ) : ?><p><?php echo esc_html( $pillar_spoke['description'] ); ?></p><?php endif; ?>
						<strong><?php esc_html_e( 'פתחו ובדקו', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></strong>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="pillar-spokes-empty"><?php esc_html_e( 'מדריכים מפורטים יתווספו בהדרגה.', 'tra-vel-v2' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section page-width experience-next pillar-next" aria-labelledby="pillar-next-step">
		<div><span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span><h2 id="pillar-next-step"><?php echo esc_html( $pillar['cta_title'] ); ?></h2><p><?php echo esc_html( $pillar['cta_copy'] ); ?></p></div>
		<a class="button-link dark-button" href="<?php echo esc_url( $pillar_cta_url ); ?>"><?php echo esc_html( $pillar['cta_label'] ); ?><i data-lucide="arrow-left"></i></a>
	</section>
</main>
<?php get_footer(); ?>
