<?php
/**
 * Template Name: Tra-Vel Globe
 * Template Post Type: page
 *
 * @package TraVelV2
 */

get_header();

$search_value = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : __( 'שבועיים בתאילנד בנובמבר', 'tra-vel-v2' );
$discovery_path = TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json';
$discovery_data = file_exists( $discovery_path ) ? json_decode( file_get_contents( $discovery_path ), true ) : array();
$map_destinations = isset( $discovery_data['destinations'] ) && is_array( $discovery_data['destinations'] ) ? $discovery_data['destinations'] : array();
$map_exploration_hubs = isset( $discovery_data['exploration_hubs'] ) && is_array( $discovery_data['exploration_hubs'] ) ? $discovery_data['exploration_hubs'] : array();
$requested_map_destination = isset( $_GET['destination'] ) ? sanitize_key( wp_unslash( $_GET['destination'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$known_map_destination_ids = array_values( array_filter( array_map( 'sanitize_key', array_column( $map_destinations, 'id' ) ) ) );
$default_map_destination = in_array( $requested_map_destination, $known_map_destination_ids, true ) ? $requested_map_destination : 'bangkok';
if ( ! array_filter( $map_destinations, static function ( $destination ) use ( $default_map_destination ) { return $default_map_destination === ( $destination['id'] ?? '' ); } ) ) {
	$default_map_destination = sanitize_key( $map_destinations[0]['id'] ?? '' );
}
$default_map_data = array();
foreach ( $map_destinations as $destination ) {
	if ( $default_map_destination === ( $destination['id'] ?? '' ) ) {
		$default_map_data = $destination;
		break;
	}
}
$default_map_city = sanitize_text_field( $default_map_data['city'] ?? __( 'היעד שנבחר', 'tra-vel-v2' ) );
$default_map_country = sanitize_text_field( $default_map_data['country'] ?? '' );
$default_map_label = trim( $default_map_city . ( $default_map_country ? ', ' . $default_map_country : '' ) );
$default_map_image = sanitize_file_name( $default_map_data['image'] ?? 'earth-blue-marble.jpg' );
$default_map_airport = sanitize_text_field( $default_map_data['airport']['code'] ?? '' );
?>
<main id="main-content" class="theme-map-shell" data-tra-vel-page="travel-map">
	<section class="map-product-intro">
		<div class="page-width map-product-intro-inner">
			<div>
				<span class="eyebrow"><?php esc_html_e( 'מפת החופשות', 'tra-vel-v2' ); ?></span>
				<h1><?php esc_html_e( 'לאן תרצו לטוס? בחרו יעד על המפה.', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'סובבו את העולם ובחרו נקודה. פתחו טיסות, אזורי לינה, מזג אוויר ותקציב מלא לתכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></p>
			</div>
			<a href="#map-support" class="map-intro-link"><i data-lucide="mouse-pointer-click"></i><?php esc_html_e( 'מה אפשר לעשות במפה', 'tra-vel-v2' ); ?></a>
		</div>
	</section>

	<div class="map-workspace page-width">
		<aside id="travel-map-filters" class="filter-panel" aria-label="<?php esc_attr_e( 'מסנני חופשה', 'tra-vel-v2' ); ?>">
			<div class="filter-panel-head">
				<div><span class="eyebrow"><?php esc_html_e( 'התאמה אישית', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מה חשוב בחופשה?', 'tra-vel-v2' ); ?></h2></div>
				<button class="round-button" data-filter-close type="button" aria-label="<?php esc_attr_e( 'סגירת מסננים', 'tra-vel-v2' ); ?>"><i data-lucide="x"></i></button>
			</div>
			<p><?php esc_html_e( 'הבחירות משנות את היעדים ואת סדר המסלולים לפי מה שחשוב לכם.', 'tra-vel-v2' ); ?></p>

			<div class="filter-block">
				<div class="budget-row"><strong><?php esc_html_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?></strong><span class="budget-value" data-budget-value><bdi dir="ltr">$950</bdi></span></div>
				<input data-budget type="range" min="200" max="1600" step="25" value="950" aria-label="<?php esc_attr_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?>">
				<div class="filter-scale"><span><bdi dir="ltr">$200</bdi></span><span><bdi dir="ltr">$1,600</bdi></span></div>
				<p class="budget-coverage-note" data-budget-coverage data-coverage="loading" role="status" aria-live="polite"><?php esc_html_e( 'התקציב מסנן את יעדי ההמחשה. הוא אינו מחיר ספק ואינו מעיד על זמינות.', 'tra-vel-v2' ); ?></p>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'מה מחפשים?', 'tra-vel-v2' ); ?></strong>
				<div class="filter-chips" data-filter-kind="trip">
					<button class="is-active" data-filter-value="all" type="button"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="short" type="button"><?php esc_html_e( 'חופשה קצרה', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="long" type="button"><?php esc_html_e( 'טיול ארוך', 'tra-vel-v2' ); ?></button>
				</div>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'קצב הנסיעה', 'tra-vel-v2' ); ?></strong>
				<label class="check-row"><input data-max-stops type="checkbox" checked> <?php esc_html_e( 'עד עצירה אחת', 'tra-vel-v2' ); ?></label>
				<label class="check-row"><input data-max-duration type="checkbox" checked> <?php esc_html_e( 'עד 16 שעות בדרך', 'tra-vel-v2' ); ?></label>
				<label class="check-row"><input data-allow-overnight type="checkbox"> <?php esc_html_e( 'אפשר עצירת לילה בדרך', 'tra-vel-v2' ); ?></label>
				<div class="toggle-row"><span><?php esc_html_e( 'רק טיסות ישירות', 'tra-vel-v2' ); ?></span><button class="toggle" data-direct-filter type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'טיסות ישירות בלבד', 'tra-vel-v2' ); ?>"></button></div>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'מה חשוב יותר?', 'tra-vel-v2' ); ?></strong>
				<div class="filter-chips" data-filter-kind="sort">
					<button class="is-active" data-filter-value="smart" type="button"><?php esc_html_e( 'איזון', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="price" type="button"><?php esc_html_e( 'מחיר', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="time" type="button"><?php esc_html_e( 'זמן', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="comfort" type="button"><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></button>
				</div>
			</div>

			<button class="filter-apply" data-discovery-apply type="button"><?php esc_html_e( 'עדכנו את המפה', 'tra-vel-v2' ); ?></button>
		</aside>

		<section class="map-main-column" aria-label="<?php esc_attr_e( 'מפת יעדים ומסלולים', 'tra-vel-v2' ); ?>">
			<form class="map-search-bar" action="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>" method="get">
				<i data-lucide="search"></i>
				<label class="screen-reader-text" for="travel-map-query"><?php esc_html_e( 'חיפוש חופשה', 'tra-vel-v2' ); ?></label>
				<input id="travel-map-query" name="q" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'לדוגמה: סוף שבוע בבודפשט באוקטובר', 'tra-vel-v2' ); ?>">
				<button type="submit"><?php esc_html_e( 'הראו אפשרויות', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></button>
			</form>

			<div class="map-mobile-action-row">
				<button data-filter-toggle type="button" aria-expanded="false" aria-controls="travel-map-filters"><i data-lucide="sliders-horizontal"></i><?php esc_html_e( 'מסננים', 'tra-vel-v2' ); ?></button>
				<a href="#route-comparison"><i data-lucide="route"></i><?php esc_html_e( 'השוואת מסלולים', 'tra-vel-v2' ); ?></a>
			</div>
			<div class="map-mobile-filter-host" data-mobile-filter-host></div>

			<div class="map-view-layout">
				<nav class="map-layer-buttons" aria-label="<?php esc_attr_e( 'שכבות מפה', 'tra-vel-v2' ); ?>">
					<span><?php esc_html_e( 'תצוגה', 'tra-vel-v2' ); ?></span>
					<button data-map-zoom="in" type="button" aria-label="<?php esc_attr_e( 'הגדלת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="plus"></i></button>
					<button data-map-zoom="out" type="button" aria-label="<?php esc_attr_e( 'הקטנת הגלובוס', 'tra-vel-v2' ); ?>"><i data-lucide="minus"></i></button>
					<button class="is-active" data-map-layer="deals" type="button" aria-pressed="true" aria-label="<?php esc_attr_e( 'יעדים', 'tra-vel-v2' ); ?>"><i data-lucide="map-pin"></i><small><?php esc_html_e( 'יעדים', 'tra-vel-v2' ); ?></small></button>
					<button data-map-layer="hotels" type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'מלונות', 'tra-vel-v2' ); ?>"><i data-lucide="bed-double"></i><small><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></small></button>
					<button data-map-layer="airports" type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'שדות תעופה', 'tra-vel-v2' ); ?>"><i data-lucide="plane"></i><small><?php esc_html_e( 'שדות', 'tra-vel-v2' ); ?></small></button>
					<button data-map-layer="weather" type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'מזג אוויר', 'tra-vel-v2' ); ?>"><i data-lucide="cloud-sun"></i><small><?php esc_html_e( 'מזג אוויר', 'tra-vel-v2' ); ?></small></button>
				</nav>

				<div class="world-canvas" data-map-canvas data-data-mode="loading" aria-label="<?php esc_attr_e( 'גלובוס יעדים אינטראקטיבי', 'tra-vel-v2' ); ?>">
					<div class="globe globe-webgl" data-globe-3d data-discovery-globe data-origin-latitude="32.0005" data-origin-longitude="34.8708" data-supported-radius-km="100" data-texture="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" tabindex="0" role="group" aria-label="<?php esc_attr_e( 'גלובוס תלת ממדי. גררו לסיבוב, לחצו על כל נקודה בכדור הארץ או הקישו Enter כדי לבחור את מרכז התצוגה.', 'tra-vel-v2' ); ?>">
						<canvas data-globe-canvas aria-hidden="true"></canvas>
						<noscript><img class="globe-noscript-image" src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" alt="<?php esc_attr_e( 'מפת עולם סטטית', 'tra-vel-v2' ); ?>"></noscript>
						<svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg>
						<span class="globe-selection-point" data-globe-selection-point aria-hidden="true" hidden></span>
						<span class="origin-point" data-globe-origin aria-label="<?php esc_attr_e( 'תל אביב, נקודת מוצא', 'tra-vel-v2' ); ?>"></span>
						<?php foreach ( $map_destinations as $destination ) :
							$destination_id = sanitize_key( $destination['id'] ?? '' );
							$latitude       = isset( $destination['geo']['latitude'] ) ? (float) $destination['geo']['latitude'] : null;
							$longitude      = isset( $destination['geo']['longitude'] ) ? (float) $destination['geo']['longitude'] : null;
							if ( ! $destination_id || null === $latitude || null === $longitude || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
								continue;
							}
							$is_default_destination = $default_map_destination === $destination_id;
							?>
							<button class="price-pin pin-<?php echo esc_attr( $destination_id ); ?><?php echo $is_default_destination ? ' is-active' : ''; ?>" data-destination="<?php echo esc_attr( $destination_id ); ?>" data-latitude="<?php echo esc_attr( $latitude ); ?>" data-longitude="<?php echo esc_attr( $longitude ); ?>" aria-pressed="<?php echo $is_default_destination ? 'true' : 'false'; ?>" type="button"><?php echo esc_html( $destination['city'] ?? $destination_id ); ?></button>
						<?php endforeach; ?>
						<?php foreach ( $map_exploration_hubs as $exploration_hub ) :
							$hub_id        = sanitize_key( $exploration_hub['id'] ?? '' );
							$hub_city      = sanitize_text_field( $exploration_hub['city'] ?? '' );
							$hub_country   = sanitize_text_field( $exploration_hub['country'] ?? '' );
							$hub_latitude  = isset( $exploration_hub['geo']['latitude'] ) ? (float) $exploration_hub['geo']['latitude'] : null;
							$hub_longitude = isset( $exploration_hub['geo']['longitude'] ) ? (float) $exploration_hub['geo']['longitude'] : null;
							$hub_radius    = isset( $exploration_hub['radius_km'] ) ? (int) $exploration_hub['radius_km'] : 0;
							$hub_iata      = sanitize_text_field( $exploration_hub['iata_search_code'] ?? '' );
							$hub_scopes    = isset( $exploration_hub['live_search_scopes'] ) && is_array( $exploration_hub['live_search_scopes'] ) ? array_map( 'sanitize_key', $exploration_hub['live_search_scopes'] ) : array();
							if ( ! $hub_id || ! $hub_city || ! $hub_country || null === $hub_latitude || null === $hub_longitude || $hub_latitude < -90 || $hub_latitude > 90 || $hub_longitude < -180 || $hub_longitude > 180 || $hub_radius < 40 || $hub_radius > 750 || ( $hub_iata && ! preg_match( '/^[A-Z]{3}$/', $hub_iata ) ) ) {
								continue;
							}
							$hub_static_x = round( ( ( $hub_longitude + 180 ) / 360 ) * 100, 3 );
							$hub_static_y = round( ( ( 90 - $hub_latitude ) / 180 ) * 100, 3 );
							$hub_label    = sprintf( __( 'גלו את %1$s, %2$s. כל האפשרויות ייפתחו לבדיקה לפי פרטי הנסיעה.', 'tra-vel-v2' ), $hub_city, $hub_country );
							?>
							<button class="exploration-hub" data-exploration-hub="<?php echo esc_attr( $hub_id ); ?>" data-city="<?php echo esc_attr( $hub_city ); ?>" data-country="<?php echo esc_attr( $hub_country ); ?>" data-latitude="<?php echo esc_attr( $hub_latitude ); ?>" data-longitude="<?php echo esc_attr( $hub_longitude ); ?>" data-radius-km="<?php echo esc_attr( $hub_radius ); ?>" data-iata-search-code="<?php echo esc_attr( $hub_iata ); ?>" data-live-search-scopes="<?php echo esc_attr( implode( ',', $hub_scopes ) ); ?>" style="--hub-static-x:<?php echo esc_attr( $hub_static_x ); ?>%;--hub-static-y:<?php echo esc_attr( $hub_static_y ); ?>%;" aria-label="<?php echo esc_attr( $hub_label ); ?>" aria-pressed="false" type="button"><span class="exploration-hub-label"><b><?php echo esc_html( $hub_city ); ?></b><small><?php echo esc_html( $hub_country ); ?></small></span></button>
						<?php endforeach; ?>
						<span class="sr-only" data-globe-live role="status" aria-live="polite" aria-atomic="true"></span>
					</div>
				</div>
			</div>

			<div class="map-status-row">
				<div class="map-canvas-guidance"><i data-lucide="move-3d"></i><span><strong><?php esc_html_e( 'גררו כדי לסובב את העולם', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בחרו נקודה או שכבה. כל הפרטים נפתחים מתחת למפה.', 'tra-vel-v2' ); ?></span></div>
				<p class="map-data-status" data-layer-status role="status"><?php esc_html_e( 'היעדים מוצגים. מחירים וזמינות טרם נבדקו.', 'tra-vel-v2' ); ?></p>
				<a class="map-imagery-attribution" href="https://visibleearth.nasa.gov/images/74218/december-blue-marble-next-generation/74226l" target="_blank" rel="noopener noreferrer">NASA Blue Marble</a>
				<a class="map-weather-attribution" data-weather-attribution href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" hidden>Weather data by Open-Meteo · CC BY 4.0</a>
			</div>
			<section class="globe-dive-store" data-dive-store data-dive-depth="0" hidden aria-label="<?php esc_attr_e( 'שירותים ומוצרים לנקודה שנבחרה על הגלובוס', 'tra-vel-v2' ); ?>">
				<div class="dive-store-topline">
					<nav class="dive-breadcrumb" data-dive-breadcrumb aria-label="<?php esc_attr_e( 'רמת הצלילה על הגלובוס', 'tra-vel-v2' ); ?>"></nav>
					<button class="dive-back" data-dive-back type="button" hidden><i data-lucide="corner-left-up"></i><?php esc_html_e( 'חזרה לעולם', 'tra-vel-v2' ); ?></button>
				</div>
				<header class="dive-hero" data-dive-hero>
					<small data-dive-kicker></small>
					<strong data-dive-title></strong>
					<span data-dive-meta></span>
				</header>
				<div class="dive-chip-row" data-dive-chips role="list" aria-label="<?php esc_attr_e( 'חלקי החופשה לנקודה שנבחרה', 'tra-vel-v2' ); ?>" hidden></div>
				<div class="dive-board" data-dive-board hidden></div>
				<div class="dive-nearby" data-dive-nearby hidden></div>
				<p class="dive-footnote" data-dive-footnote hidden><?php esc_html_e( 'המחירים להמחשה; המחיר הסופי מאומת לפני התשלום.', 'tra-vel-v2' ); ?></p>
				<span class="sr-only" data-dive-live role="status" aria-live="polite" aria-atomic="true"></span>
			</section>
			<nav class="map-destination-index" data-map-destination-index aria-label="<?php esc_attr_e( 'בחירת יעד ללא שימוש בגלובוס', 'tra-vel-v2' ); ?>">
				<strong><i data-lucide="list"></i><?php esc_html_e( 'בחרו יעד מהרשימה', 'tra-vel-v2' ); ?></strong>
				<div>
					<?php foreach ( $map_destinations as $destination ) :
						$destination_id = sanitize_key( $destination['id'] ?? '' );
						if ( ! $destination_id ) {
							continue;
						}
						$destination_url = add_query_arg( 'destination', $destination_id, home_url( '/travel-map/' ) ) . '#destination-plan-title';
						?>
						<a data-map-destination-link data-destination="<?php echo esc_attr( $destination_id ); ?>" href="<?php echo esc_url( $destination_url ); ?>"<?php echo $default_map_destination === $destination_id ? ' class="is-active" aria-current="true"' : ''; ?>><span><?php echo esc_html( $destination['city'] ?? $destination_id ); ?></span><small><?php echo esc_html( $destination['country'] ?? '' ); ?></small></a>
					<?php endforeach; ?>
				</div>
			</nav>
			<div class="map-data-disclosure"><?php tra_vel_v2_demo_disclosure(); ?></div>
			<div class="map-trip-context" data-map-trip-context hidden>
				<span class="map-trip-context-icon" aria-hidden="true"><i data-lucide="list-checks"></i></span>
				<span><small><?php esc_html_e( 'פרטי הנסיעה התקבלו', 'tra-vel-v2' ); ?></small><strong data-map-trip-context-summary></strong></span>
				<a data-map-trip-context-edit href="<?php echo esc_url( home_url( '/#search' ) ); ?>"><?php esc_html_e( 'עריכת פרטים', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a>
			</div>
			<div class="map-selection-rail" data-globe-selection data-state="idle" tabindex="-1">
				<span class="map-selection-signal" aria-hidden="true"><i data-lucide="map-pin-check"></i></span>
				<span class="map-selection-copy"><small data-globe-selection-kicker><?php esc_html_e( 'הכיוון שנבחר', 'tra-vel-v2' ); ?></small><strong data-globe-selection-title><?php echo esc_html( sprintf( __( '%s מוכן לתכנון', 'tra-vel-v2' ), $default_map_city ) ); ?></strong><span data-globe-selection-detail><?php esc_html_e( 'פתחו טיסות, לינה, מסלול וכל שאר חלקי החופשה. כל בחירה נשארת ניתנת לעריכה.', 'tra-vel-v2' ); ?></span></span>
				<a data-globe-selection-action href="#destination-plan-title"><?php echo esc_html( sprintf( __( 'פתחו פרטי תכנון ל%s', 'tra-vel-v2' ), $default_map_city ) ); ?><i data-lucide="arrow-down"></i></a>
				<ol class="map-progress-checkpoints" data-map-progress-checkpoints aria-label="<?php esc_attr_e( 'פרטי הבחירה', 'tra-vel-v2' ); ?>">
					<li data-map-checkpoint="point" data-state="confirmed"><i data-lucide="map-pin-check" aria-hidden="true"></i><span><b><?php esc_html_e( 'יעד על המפה', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php echo esc_html( $default_map_city ); ?></small></span></li>
					<li data-map-checkpoint="destination" data-state="confirmed"><i data-lucide="locate-fixed" aria-hidden="true"></i><span><b><?php esc_html_e( 'הכיוון פתוח', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'אפשר להחליף יעד בכל רגע', 'tra-vel-v2' ); ?></small></span></li>
					<li data-map-checkpoint="scopes" data-state="confirmed"><i data-lucide="list-checks" aria-hidden="true"></i><span><b><?php esc_html_e( 'כל החופשה', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'בחרו מה לכלול', 'tra-vel-v2' ); ?></small></span></li>
					<li data-map-checkpoint="live" data-state="waiting"><i data-lucide="radio-tower" aria-hidden="true"></i><span><b><?php esc_html_e( 'מחיר וזמינות', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'ייבדקו לפי תאריכים ונוסעים', 'tra-vel-v2' ); ?></small></span></li>
				</ol>
				<span class="sr-only" data-map-progress-live role="status" aria-live="polite" aria-atomic="true"></span>
			</div>
		</section>
	</div>

	<section class="map-entity-section page-width" data-map-entity-explorer aria-labelledby="map-entity-heading">
		<header class="map-entity-head">
			<div>
				<span class="eyebrow"><?php esc_html_e( 'מהגלובוס לשכבת הפרטים', 'tra-vel-v2' ); ?></span>
				<h2 id="map-entity-heading" data-map-entity-heading><?php esc_html_e( 'המידע נפתח מתחת למפה, בלי להסתיר את כדור הארץ.', 'tra-vel-v2' ); ?></h2>
				<p><?php esc_html_e( 'בחרו יעד או שכבה כדי לראות נקודות רלוונטיות, להבין מה ידוע עכשיו ולעבור לפעולה המתאימה. אפשר לחזור לגלובוס ולהחליף בחירה בכל רגע.', 'tra-vel-v2' ); ?></p>
			</div>
			<div class="map-entity-status" aria-live="polite" aria-atomic="true">
				<span data-map-entity-layer-label><?php esc_html_e( 'שכבת יעדים', 'tra-vel-v2' ); ?></span>
				<strong data-map-entity-count><?php esc_html_e( 'ממתין לבחירה', 'tra-vel-v2' ); ?></strong>
			</div>
		</header>

		<div class="map-entity-layout">
			<div class="map-entity-plane" data-map-entity-plane role="group" aria-label="<?php esc_attr_e( 'מפת פרטים ממוקדת ליעד ולשכבה שנבחרו', 'tra-vel-v2' ); ?>">
				<div class="map-entity-plane-label" aria-hidden="true">
					<i data-lucide="scan-search"></i>
					<span><?php esc_html_e( 'שכבת מידע ממוקדת', 'tra-vel-v2' ); ?></span>
				</div>
				<svg class="map-entity-segments" data-map-entity-segments viewBox="0 0 1000 520" preserveAspectRatio="none" aria-hidden="true" focusable="false"></svg>
				<div class="map-entity-markers" data-map-entity-markers role="list" aria-label="<?php esc_attr_e( 'נקודות בשכבת המידע שנבחרה', 'tra-vel-v2' ); ?>"></div>
				<div class="map-entity-empty" data-map-entity-empty role="status">
					<i data-lucide="mouse-pointer-click" aria-hidden="true"></i>
					<strong><?php esc_html_e( 'בחרו יעד או שכבה על המפה', 'tra-vel-v2' ); ?></strong>
					<span><?php esc_html_e( 'כאן יופיעו הנקודות הזמינות, בלי לכסות את הגלובוס ובלי להציג מידע מסחרי שלא נבדק.', 'tra-vel-v2' ); ?></span>
				</div>
			</div>

			<article class="map-entity-detail" data-map-entity-detail aria-live="polite" aria-atomic="true">
				<span class="map-entity-kind" data-map-entity-kind><i data-lucide="info"></i><?php esc_html_e( 'מידע תכנוני', 'tra-vel-v2' ); ?></span>
				<h3 data-map-entity-title><?php esc_html_e( 'פתחו נקודה כדי לקבל את הפרטים החשובים', 'tra-vel-v2' ); ?></h3>
				<p data-map-entity-summary><?php esc_html_e( 'לאחר בחירה יוצגו כאן ההקשר, מצב הנתון, זמן העדכון והפעולה הבאה. פרטים מסחריים יופיעו רק כאשר יש תשובה תקפה לבדיקה שביקשתם.', 'tra-vel-v2' ); ?></p>
				<dl class="map-entity-facts">
					<div>
						<dt><i data-lucide="badge-dollar-sign"></i><?php esc_html_e( 'מחיר', 'tra-vel-v2' ); ?></dt>
						<dd data-map-entity-price><?php esc_html_e( 'יופיע לאחר הזנת תאריכים ונוסעים', 'tra-vel-v2' ); ?></dd>
					</div>
					<div>
						<dt><i data-lucide="shield-check"></i><?php esc_html_e( 'מצב הנתון', 'tra-vel-v2' ); ?></dt>
						<dd data-map-entity-truth><?php esc_html_e( 'מידע תכנוני, לא הצעה סופית', 'tra-vel-v2' ); ?></dd>
					</div>
					<div>
						<dt><i data-lucide="clock-3"></i><?php esc_html_e( 'עדכניות', 'tra-vel-v2' ); ?></dt>
						<dd data-map-entity-freshness><?php esc_html_e( 'ממתין לבחירת שכבה', 'tra-vel-v2' ); ?></dd>
					</div>
					<div>
						<dt><i data-lucide="database"></i><?php esc_html_e( 'מקור', 'tra-vel-v2' ); ?></dt>
						<dd data-map-entity-source><?php esc_html_e( 'המקור יוצג לצד כל נתון', 'tra-vel-v2' ); ?></dd>
					</div>
				</dl>
				<p class="map-entity-selection-status" data-map-entity-selection-status data-state="preview">
					<i data-lucide="route" aria-hidden="true"></i>
					<span><?php esc_html_e( 'בחרו נקודה כדי לחבר אותה לתוכנית החופשה המלאה.', 'tra-vel-v2' ); ?></span>
				</p>
				<a class="map-entity-action" data-map-entity-action href="#map-support"><span><?php esc_html_e( 'פתחו את תכנון היעד', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-down"></i></a>
				<p class="map-entity-boundary"><i data-lucide="circle-check-big"></i><?php esc_html_e( 'המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></p>
			</article>
		</div>

		<div class="map-entity-list" data-map-entity-list role="list" aria-label="<?php esc_attr_e( 'רשימת פרטים נגישה לשכבה שנבחרה', 'tra-vel-v2' ); ?>">
			<a class="map-entity-fallback-card" role="listitem" href="#destination-plan-title">
				<i data-lucide="map-pin-check" aria-hidden="true"></i>
				<span>
					<small><?php esc_html_e( 'היעד שנבחר', 'tra-vel-v2' ); ?></small>
					<strong><?php echo esc_html( $default_map_label ); ?></strong>
					<em><?php esc_html_e( 'פתחו טיסות, אזורי לינה, מסלול ושאר חלקי החופשה', 'tra-vel-v2' ); ?></em>
				</span>
				<i data-lucide="arrow-left" aria-hidden="true"></i>
			</a>
		</div>
	</section>

	<section class="map-support-section" id="map-support">
		<div class="page-width map-support-grid">
			<article class="map-destination-panel" data-map-result>
				<img class="map-result-image" data-result-image src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/' . $default_map_image ) ); ?>" alt="<?php echo esc_attr( $default_map_label ); ?>">
				<div class="map-destination-copy">
					<div class="result-top"><div><small><?php esc_html_e( 'רעיון לתכנון', 'tra-vel-v2' ); ?></small><h2 data-result-city><?php echo esc_html( $default_map_label ); ?></h2></div><button class="save-button" type="button" aria-label="<?php esc_attr_e( 'שמירת היעד', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></button></div>
					<div class="result-tags" data-result-tags><?php foreach ( array_slice( (array) ( $default_map_data['tags'] ?? array() ), 0, 3 ) as $tag ) : ?><span><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></div>
					<div class="result-facts"><span data-result-airport><bdi dir="ltr"><?php echo esc_html( $default_map_airport ); ?></bdi></span><span data-result-hotel><?php esc_html_e( 'אזורי לינה', 'tra-vel-v2' ); ?></span><span data-result-weather><?php esc_html_e( 'מזג אוויר', 'tra-vel-v2' ); ?></span></div>
					<div class="map-price-state"><div><small data-result-state-label><?php esc_html_e( 'מחיר לתכנון', 'tra-vel-v2' ); ?></small><strong data-result-total><?php esc_html_e( 'בחרו יעד', 'tra-vel-v2' ); ?></strong></div><p><span data-result-price><?php esc_html_e( 'המחיר נבנה לפי היעד וההרכב.', 'tra-vel-v2' ); ?></span> <span data-result-note><?php esc_html_e( 'המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></span></p></div>
					<div class="map-destination-actions"><a data-result-guide href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="book-open"></i><?php esc_html_e( 'מצאו מדריך ליעד', 'tra-vel-v2' ); ?></a><a data-result-hotels href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="bed-double"></i><?php esc_html_e( 'פתחו חיפוש מלונות', 'tra-vel-v2' ); ?></a><a data-result-insurance href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><?php esc_html_e( 'מה לבדוק בביטוח', 'tra-vel-v2' ); ?></a></div>
					<section class="destination-plan-360" data-destination-plan aria-labelledby="destination-plan-title">
						<header class="destination-plan-head"><div><span class="eyebrow"><?php esc_html_e( 'כל החופשה במקום אחד', 'tra-vel-v2' ); ?></span><h3 id="destination-plan-title" data-plan-title><?php echo esc_html( sprintf( __( 'פתחו תכנון ל%s', 'tra-vel-v2' ), $default_map_city ) ); ?></h3></div><span class="destination-plan-state" data-plan-state><?php esc_html_e( 'כל חלק פתוח לעריכה', 'tra-vel-v2' ); ?></span></header>
						<p data-plan-summary><?php esc_html_e( 'בחרו מה חשוב לכם, עברו לכל מוצר בנפרד ובדקו טיסות, מלונות, פעילויות, שאלות לבירור בביטוח והעלות המלאה.', 'tra-vel-v2' ); ?></p>
						<div class="destination-plan-intents" role="group" aria-label="<?php esc_attr_e( 'סגנון החופשה', 'tra-vel-v2' ); ?>">
							<button class="is-active" data-plan-intent="smart" type="button" aria-pressed="true"><?php esc_html_e( 'מאוזן', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="value" type="button" aria-pressed="false"><?php esc_html_e( 'חסכוני', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="easy" type="button" aria-pressed="false"><?php esc_html_e( 'נוח', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="romantic" type="button" aria-pressed="false"><?php esc_html_e( 'זוגי', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="family" type="button" aria-pressed="false"><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="adventure" type="button" aria-pressed="false"><?php esc_html_e( 'הרפתקה', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="surprise" type="button" aria-pressed="false"><?php esc_html_e( 'פתוחים להצעות', 'tra-vel-v2' ); ?></button>
						</div>
						<div class="destination-plan-progress" role="list" aria-label="<?php esc_attr_e( 'מה מוצג ומה עדיין צריך לבדוק', 'tra-vel-v2' ); ?>">
							<span class="is-ready" role="listitem" data-plan-stage="destination" style="--plan-index:0"><i data-lucide="map-pin"></i><b><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'נבחר', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="route" style="--plan-index:1"><i data-lucide="route"></i><b><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'השוו דרכי הגעה', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="stay" style="--plan-index:2"><i data-lucide="bed-double"></i><b><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בחרו אזור', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="experience" style="--plan-index:3"><i data-lucide="sparkles"></i><b><?php esc_html_e( 'חוויה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בנו את הימים', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="cover" style="--plan-index:4"><i data-lucide="shield-check"></i><b><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'התאימו שאלות לבדיקה', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready is-current" role="listitem" aria-current="step" data-plan-stage="total" style="--plan-index:5"><i data-lucide="calculator"></i><b><?php esc_html_e( 'עלות מלאה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'חברו את כל הרכיבים', 'tra-vel-v2' ); ?></small></span>
						</div>
						<div class="destination-plan-options">
							<a data-plan-flight data-plan-layer="airports" href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><small><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></small><strong data-plan-flight-title><?php esc_html_e( 'ישיר או קונקשן', 'tra-vel-v2' ); ?></strong><em data-plan-flight-detail><?php esc_html_e( 'זמן, כבודה ותנאי כרטיס', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-stay data-plan-layer="hotels" href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="hotel"></i><span><small><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></small><strong data-plan-stay-title><?php esc_html_e( 'בחרו אזור לפני מלון', 'tra-vel-v2' ); ?></strong><em data-plan-stay-detail><?php esc_html_e( 'קצב, תחבורה ועלות מלאה', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-experience href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><i data-lucide="map"></i><span><small><?php esc_html_e( 'מדריכים', 'tra-vel-v2' ); ?></small><strong data-plan-experience-title><?php esc_html_e( 'מצאו מידע לתכנון המסלול', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'אוכל, תרבות, טבע וזמן חופשי', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-weather data-plan-layer="weather" href="<?php echo esc_url( home_url( '/travel-map/?layer=weather' ) ); ?>"><i data-lucide="cloud-sun"></i><span><small><?php esc_html_e( 'עונה ומזג אוויר', 'tra-vel-v2' ); ?></small><strong data-plan-weather-title><?php esc_html_e( 'בדיקה לפי תאריך', 'tra-vel-v2' ); ?></strong><em data-plan-weather-detail><?php esc_html_e( 'לא מניחים מזג אוויר בלי מועד', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-cover href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-plus"></i><span><small><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></small><strong data-plan-cover-title><?php esc_html_e( 'נקודות לבירור מול המבטח', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'יעד, מצב רפואי, כבודה, פעילויות וביטול', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-total data-plan-layer="deals" href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><i data-lucide="badge-dollar-sign"></i><span><small><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></small><strong data-plan-total-title><?php esc_html_e( 'העלות עדיין לא חושבה', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'הזינו תאריכים ונוסעים כדי לבדוק חלופות', 'tra-vel-v2' ); ?></em></span></a>
						</div>
						<section class="destination-decision-cockpit" aria-labelledby="destination-decision-title">
							<header class="destination-decision-head">
								<div><span class="eyebrow"><?php esc_html_e( 'כל מה שצריך לנסיעה', 'tra-vel-v2' ); ?></span><h4 id="destination-decision-title"><?php esc_html_e( 'בדקו כל חלק בחופשה לפני שסוגרים', 'tra-vel-v2' ); ?></h4><p data-plan-coverage-copy><?php esc_html_e( 'היעד נבחר. עכשיו אפשר לפתוח כל חלק, להשוות ולשמור את הבחירות.', 'tra-vel-v2' ); ?></p></div>
								<div class="destination-plan-meter" data-plan-meter role="progressbar" aria-label="<?php esc_attr_e( 'בחירות שמוכנות בתוכנית', 'tra-vel-v2' ); ?>" aria-valuemin="0" aria-valuemax="12" aria-valuenow="1" aria-valuetext="<?php esc_attr_e( 'היעד נבחר; עוד 11 חלקי חופשה פתוחים לעריכה', 'tra-vel-v2' ); ?>"><strong data-plan-meter-count><bdi dir="ltr">1/12</bdi></strong><span><i data-plan-meter-fill style="--plan-coverage:8.33%"></i></span><small data-plan-meter-label><?php esc_html_e( 'בחירות מוכנות', 'tra-vel-v2' ); ?></small></div>
							</header>
							<div class="destination-decision-grid" data-plan-modules>
								<details data-plan-module="mobility" open><summary><i data-lucide="car-taxi-front"></i><span><strong data-plan-module-title><?php esc_html_e( 'הגעה ותחבורה מקומית', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'טרם נבדקו פרטים', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'זמן ההעברה תלוי בשדה, במלון ובשעת הנחיתה. מחיר ומסלול טרם נבדקו.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'פתחו טיסה ומלון', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="dining"><summary><i data-lucide="utensils"></i><span><strong data-plan-module-title><?php esc_html_e( 'אוכל, כשרות והעדפות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין להעדפות', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'הוסיפו רמת כשרות, אלרגיות, ילדים וסגנון אוכל לפני שמסדרים תוכנית יומית.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'ספרו על העדפות האוכל', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="entry"><summary><i data-lucide="passport"></i><span><strong data-plan-module-title><?php esc_html_e( 'כניסה, דרכון ואשרות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'נדרשים פרטי נוסע', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'צריך לבדוק אזרחות, תוקף דרכון, מסלול ותאריך מול מקור ממשלתי רשמי לפני קנייה.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'עברו למדריכי הכניסה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="connectivity"><summary><i data-lucide="wifi"></i><span><strong data-plan-module-title><?php esc_html_e( 'תקשורת ו-eSIM', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'טרם נבדקו אפשרויות', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'כדאי להשוות eSIM, חבילת נדידה ו-SIM מקומי לפי ימים, נפח גלישה ושיתוף אינטרנט.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'עברו למדריכי תקשורת', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="accessibility"><summary><i data-lucide="accessibility"></i><span><strong data-plan-module-title><?php esc_html_e( 'משפחה ונגישות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין להרכב ולצרכים', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'גילים, עגלה, הליכה, מעלית, חדרים מחוברים וסיוע בשדה משפיעים על התוכנית.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'ספרו על צרכי המשפחה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="equipment"><summary><i data-lucide="backpack"></i><span><strong data-plan-module-title><?php esc_html_e( 'ציוד, כבודה והשכרה', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין לפעילויות', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'בדקו איזה ציוד נדרש לפעילויות, מה מותר בכבודה ומה אפשר לשכור ביעד.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'עברו למדריכי ציוד', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
							</div>
							<aside class="destination-cost-ledger" data-plan-ledger>
								<header><span><small><?php esc_html_e( 'עלות הנסיעה המלאה', 'tra-vel-v2' ); ?></small><strong data-plan-ledger-total><?php esc_html_e( 'נבנית לפי הבחירות', 'tra-vel-v2' ); ?></strong></span><span class="destination-ledger-state" data-plan-ledger-state><?php esc_html_e( '12 רכיבי עלות לבחירה ולבדיקה', 'tra-vel-v2' ); ?></span></header>
								<div data-plan-ledger-list>
									<span><b><?php esc_html_e( 'טיסה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו דרך הגעה', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'כבודה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו מה לקחת', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו אזור וחדר', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'מסים ועמלות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'יוצגו עם ההצעה', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'העברות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו איך להגיע למלון', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'תחבורה מקומית', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'התאימו למסלול', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'פעילויות וכרטיסים', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו מה לעשות', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'אוכל', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'הוסיפו העדפות ותקציב', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בדקו מה חשוב לברר', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'תקשורת ו-eSIM', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו לפי ימים וגלישה', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'ציוד והשכרה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בחרו לפי הפעילויות', 'tra-vel-v2' ); ?></em></span>
									<span><b><?php esc_html_e( 'כניסה ואשרות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'נדרש בירור רשמי', 'tra-vel-v2' ); ?></em></span>
								</div>
								<p data-plan-ledger-truth><?php esc_html_e( 'לא נציג חיסכון בלי שני מחירים לאותם תאריכים, נוסעים ותנאים, עם זמינות ומועד בדיקה.', 'tra-vel-v2' ); ?></p>
							</aside>
						</section>
						<div class="destination-plan-actions"><a class="destination-plan-primary" data-plan-ai href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><i data-lucide="sparkles"></i><?php esc_html_e( 'סדרו לי תוכנית לחופשה', 'tra-vel-v2' ); ?></a><button data-plan-save type="button"><i data-lucide="bookmark-plus"></i><span><?php esc_html_e( 'שמרו לנסיעה', 'tra-vel-v2' ); ?></span></button><a data-plan-guide href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><?php esc_html_e( 'מצאו מדריך ליעד', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div>
						<p class="destination-plan-truth"><i data-lucide="shield-check"></i><span data-plan-truth><?php esc_html_e( 'היעד נבחר. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></span></p>
					</section>
				</div>
			</article>

			<aside class="route-sheet" id="route-comparison">
				<div class="sheet-head"><div><span class="eyebrow"><?php esc_html_e( 'אפשרויות דרך לבדיקה', 'tra-vel-v2' ); ?></span><h2 data-route-title><?php echo esc_html( sprintf( __( 'תל אביב ל%s: בחרו תאריכים כדי להשוות דרכים', 'tra-vel-v2' ), $default_map_city ) ); ?></h2></div><span><?php esc_html_e( 'השוו זמן, כבודה, עצירות וגמישות; מחיר סופי ייבדק לפני רכישה', 'tra-vel-v2' ); ?></span></div>
				<div class="mini-routes" data-route-list aria-live="off"></div>
				<span class="sr-only" data-route-status role="status" aria-live="polite" aria-atomic="true"></span>
			</aside>
		</div>
	</section>

	<section class="map-depth-section page-width" aria-labelledby="map-depth-title">
		<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'מהגלובוס לפרטים', 'tra-vel-v2' ); ?></span><h2 id="map-depth-title"><?php esc_html_e( 'מתקרבים רק כשצריך. המידע נשאר מסודר.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'בחירת יעד פותחת שכבת מידע מדויקת יותר: שדות תעופה, שכונות, מלונות, אטרקציות, תחבורה ומסלולים. כל שכבה מקשרת לעמוד המתאים באתר.', 'tra-vel-v2' ); ?></p></div></div>
		<div class="map-depth-grid">
			<a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="map"></i><span><strong><?php esc_html_e( 'מצאו עמוד יעד', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'עברו לרשימת היעדים והמידע הזמין לכל יעד', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
			<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><i data-lucide="book-open-text"></i><span><strong><?php esc_html_e( 'פתחו את ספריית המדריכים', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'מצאו מידע על עונות, מסלולים, עלויות והחלטות', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
			<a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><strong><?php esc_html_e( 'עברו לחיפוש טיסות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'הזינו תאריכים ונוסעים כדי לבדוק מחיר וזמינות', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
		</div>
	</section>
</main>
<?php get_footer(); ?>
