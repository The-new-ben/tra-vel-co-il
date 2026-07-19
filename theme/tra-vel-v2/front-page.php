<?php
/**
 * Tra-Vel V2 commercial discovery homepage.
 *
 * @package TraVelV2
 */

get_header();
$map_url       = home_url( '/travel-map/' );
$thailand_url  = home_url( '/destinations/thailand/' );
$planner_url   = home_url( '/ai-planner/' );
$surprise_url  = add_query_arg( 'mode', 'surprise', $planner_url );
$hero_campaign = tra_vel_v2_get_home_hero_campaign();
$home_campaign_kind = in_array( $hero_campaign['kind'] ?? '', array( 'seasonal', 'evergreen' ), true ) ? $hero_campaign['kind'] : 'evergreen';
$home_campaign_is_seasonal = 'seasonal' === $home_campaign_kind;
$home_discovery_path = TRA_VEL_V2_PATH . '/assets/data/discovery-demo.json';
$home_discovery_data = file_exists( $home_discovery_path ) ? json_decode( file_get_contents( $home_discovery_path ), true ) : array();
$home_destinations = isset( $home_discovery_data['destinations'] ) && is_array( $home_discovery_data['destinations'] ) ? $home_discovery_data['destinations'] : array();
$home_exploration_hubs = isset( $home_discovery_data['exploration_hubs'] ) && is_array( $home_discovery_data['exploration_hubs'] ) ? $home_discovery_data['exploration_hubs'] : array();
$home_route_sets = isset( $home_discovery_data['route_sets'] ) && is_array( $home_discovery_data['route_sets'] ) ? $home_discovery_data['route_sets'] : array();
$home_destination_ids = array_values( array_filter( array_map( 'sanitize_key', array_column( $home_destinations, 'id' ) ) ) );
$home_default_destination = $home_campaign_is_seasonal && in_array( $hero_campaign['map_state'] ?? '', $home_destination_ids, true )
	? $hero_campaign['map_state']
	: tra_vel_v2_select_home_discovery_destination( $home_destination_ids, current_time( 'Y-m-d' ) );
if ( ! in_array( $home_default_destination, $home_destination_ids, true ) ) {
	$home_default_destination = sanitize_key( $home_destinations[0]['id'] ?? '' );
}
$home_default_context = $home_campaign_is_seasonal ? __( 'כיוון שמתאים לעונה', 'tra-vel-v2' ) : __( 'כיוון לפתיחת החיפוש', 'tra-vel-v2' );
$home_default_data = array();
foreach ( $home_destinations as $destination ) {
	if ( $home_default_destination === ( $destination['id'] ?? '' ) ) {
		$home_default_data = $destination;
		break;
	}
}
$home_default_city = sanitize_text_field( $home_default_data['city'] ?? __( 'היעד שנבחר', 'tra-vel-v2' ) );
$home_default_country = sanitize_text_field( $home_default_data['country'] ?? '' );
$home_default_label = trim( $home_default_city . ( $home_default_country ? ', ' . $home_default_country : '' ) );
$home_default_airport = sanitize_text_field( $home_default_data['airport']['code'] ?? '' );
$home_default_image = sanitize_file_name( $home_default_data['image'] ?? 'earth-blue-marble.jpg' );
$home_default_price = isset( $home_default_data['deal']['headline_price'] ) ? 'החל מ-$' . number_format_i18n( (float) $home_default_data['deal']['headline_price'], 0 ) : __( 'בחרו תאריכים', 'tra-vel-v2' );
$home_default_routes = isset( $home_route_sets[ $home_default_destination ] ) && is_array( $home_route_sets[ $home_default_destination ] ) ? $home_route_sets[ $home_default_destination ] : array();
$home_default_guide = 'bangkok' === $home_default_destination ? $thailand_url : home_url( '/destinations/' );
$home_editorial_path = TRA_VEL_V2_PATH . '/assets/data/editorial-directory.json';
$home_editorial_data = file_exists( $home_editorial_path ) ? json_decode( file_get_contents( $home_editorial_path ), true ) : array();
foreach ( (array) ( $home_editorial_data['destinations'] ?? array() ) as $editorial_destination ) {
	if ( $home_default_destination === ( $editorial_destination['map_state'] ?? '' ) && 'published' === ( $editorial_destination['guide_status'] ?? '' ) && ! empty( $editorial_destination['guide_path'] ) ) {
		$home_default_guide = home_url( $editorial_destination['guide_path'] );
		break;
	}
}
$home_now       = current_datetime();
$home_today     = $home_now->format( 'Y-m-d' );
$home_departure = $home_now->modify( '+30 days' )->format( 'Y-m-d' );
$home_return    = $home_now->modify( '+34 days' )->format( 'Y-m-d' );
?>
<main id="main-content" data-tra-vel-page="home">
	<section class="home-hero">
		<div class="hero-grid page-width">
			<div class="hero-copy">
				<span class="kicker"><i data-lucide="sparkles"></i><?php esc_html_e( 'מתחילים מהחופשה שמתאימה לכם', 'tra-vel-v2' ); ?></span>
				<h1><?php esc_html_e( 'איזו חופשה', 'tra-vel-v2' ); ?><br><em><?php esc_html_e( 'מתאימה לכם עכשיו?', 'tra-vel-v2' ); ?></em></h1>
				<p><?php esc_html_e( 'כתבו יעד, תקציב או סגנון, וקבלו כיוון לחופשה עם טיסות, לינה ופירוט עלויות.', 'tra-vel-v2' ); ?></p>
				<div class="hero-agent-actions">
					<a class="ai-prompt" href="<?php echo esc_url( $planner_url ); ?>"><i data-lucide="bot"></i><span><?php esc_html_e( 'כתבו מה אתם מחפשים', 'tra-vel-v2' ); ?></span><strong><?php esc_html_e( '“שבועיים בתאילנד לזוג עד ₪9,000, עם חוף ואוכל כשר”', 'tra-vel-v2' ); ?></strong><i data-lucide="arrow-left"></i></a>
					<a class="surprise-cta" data-home-surprise href="<?php echo esc_url( $surprise_url ); ?>"><i data-lucide="compass"></i><span><strong data-home-surprise-label><?php esc_html_e( 'תפתיעו אותי', 'tra-vel-v2' ); ?></strong><small><?php esc_html_e( 'רעיון לחופשה בלחיצה אחת. אחר כך אפשר לשנות הכול.', 'tra-vel-v2' ); ?></small></span><i data-lucide="arrow-left"></i></a>
				</div>
				<?php if ( $hero_campaign ) : ?>
					<aside class="hero-campaign" data-hero-campaign="<?php echo esc_attr( $hero_campaign['id'] ); ?>" data-campaign-kind="<?php echo esc_attr( $home_campaign_kind ); ?>"<?php echo $home_campaign_is_seasonal ? ' data-map-state="' . esc_attr( $hero_campaign['map_state'] ) . '"' : ''; ?>>
						<div class="hero-campaign-heading"><span><i data-lucide="<?php echo esc_attr( $home_campaign_is_seasonal ? 'calendar-heart' : 'compass' ); ?>"></i><?php echo esc_html( $hero_campaign['eyebrow'] ); ?></span><small><?php echo esc_html( $home_campaign_is_seasonal ? __( 'מתאים לעונה', 'tra-vel-v2' ) : __( 'כיוון פתוח לבחירה', 'tra-vel-v2' ) ); ?></small></div>
						<strong><?php echo esc_html( $hero_campaign['title'] ); ?></strong>
						<p><?php echo esc_html( $hero_campaign['copy'] ); ?></p>
						<div class="hero-campaign-actions"><a class="is-primary" href="<?php echo esc_url( home_url( $hero_campaign['primary_url'] ) ); ?>"><?php echo esc_html( $hero_campaign['primary_label'] ); ?><i data-lucide="arrow-left"></i></a><a href="<?php echo esc_url( home_url( $hero_campaign['secondary_url'] ) ); ?>"><?php echo esc_html( $hero_campaign['secondary_label'] ); ?></a></div>
					</aside>
				<?php endif; ?>
			</div>
			<div class="home-globe-stack">
			<div class="globe-panel" aria-label="<?php esc_attr_e( 'מפת יעדים אינטראקטיבית', 'tra-vel-v2' ); ?>">
				<div class="globe-halo"></div>
				<div class="globe globe-webgl" data-globe-3d data-discovery-globe data-home-globe data-default-destination="<?php echo esc_attr( $home_default_destination ); ?>" data-campaign-kind="<?php echo esc_attr( $home_campaign_kind ); ?>" data-origin-latitude="32.0005" data-origin-longitude="34.8708" data-supported-radius-km="100" data-texture="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" tabindex="0" role="group" aria-label="<?php esc_attr_e( 'גלובוס תלת ממדי של רעיונות לחופשה. גררו לסיבוב או בחרו יעד.', 'tra-vel-v2' ); ?>">
					<canvas data-globe-canvas aria-hidden="true"></canvas>
					<noscript><img class="globe-noscript-image" src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/earth-blue-marble-2048.jpg' ) ); ?>" alt="<?php esc_attr_e( 'מפת עולם סטטית', 'tra-vel-v2' ); ?>"></noscript>
					<svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg>
					<span class="globe-selection-point" data-globe-selection-point aria-hidden="true" hidden></span>
					<span class="origin-point" data-globe-origin title="<?php esc_attr_e( 'תל אביב', 'tra-vel-v2' ); ?>"></span>
					<?php foreach ( $home_destinations as $destination ) :
						$destination_id = sanitize_key( $destination['id'] ?? '' );
						$airport_code   = sanitize_text_field( $destination['airport']['code'] ?? '' );
						$latitude       = isset( $destination['geo']['latitude'] ) ? (float) $destination['geo']['latitude'] : null;
						$longitude      = isset( $destination['geo']['longitude'] ) ? (float) $destination['geo']['longitude'] : null;
						if ( ! $destination_id || ! $airport_code || null === $latitude || null === $longitude ) {
							continue;
						}
						$is_default_destination = $home_default_destination === $destination_id;
						?>
						<button class="price-pin pin-<?php echo esc_attr( $destination_id ); ?><?php echo $is_default_destination ? ' is-active' : ''; ?>" data-destination="<?php echo esc_attr( $destination_id ); ?>" data-latitude="<?php echo esc_attr( $latitude ); ?>" data-longitude="<?php echo esc_attr( $longitude ); ?>" aria-pressed="<?php echo $is_default_destination ? 'true' : 'false'; ?>" type="button"><bdi dir="ltr"><?php echo esc_html( $airport_code ); ?></bdi></button>
					<?php endforeach; ?>
					<?php foreach ( $home_exploration_hubs as $exploration_hub ) :
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
						$hub_label    = sprintf( __( 'גלו את %1$s, %2$s ופתחו תוכנית חופשה מלאה.', 'tra-vel-v2' ), $hub_city, $hub_country );
						?>
						<button class="exploration-hub" data-exploration-hub="<?php echo esc_attr( $hub_id ); ?>" data-city="<?php echo esc_attr( $hub_city ); ?>" data-country="<?php echo esc_attr( $hub_country ); ?>" data-latitude="<?php echo esc_attr( $hub_latitude ); ?>" data-longitude="<?php echo esc_attr( $hub_longitude ); ?>" data-radius-km="<?php echo esc_attr( $hub_radius ); ?>" data-iata-search-code="<?php echo esc_attr( $hub_iata ); ?>" data-live-search-scopes="<?php echo esc_attr( implode( ',', $hub_scopes ) ); ?>" style="--hub-static-x:<?php echo esc_attr( $hub_static_x ); ?>%;--hub-static-y:<?php echo esc_attr( $hub_static_y ); ?>%;" aria-label="<?php echo esc_attr( $hub_label ); ?>" aria-pressed="false" type="button"><span class="exploration-hub-label"><b><?php echo esc_html( $hub_city ); ?></b><small><?php echo esc_html( $hub_country ); ?></small></span></button>
					<?php endforeach; ?>
					<span class="screen-reader-text" data-globe-live role="status" aria-live="polite" aria-atomic="true"></span>
				</div>
				<div class="globe-tools"><button data-map-zoom="in" type="button" aria-label="<?php esc_attr_e( 'הגדלה', 'tra-vel-v2' ); ?>"><i data-lucide="plus"></i></button><button data-map-zoom="out" type="button" aria-label="<?php esc_attr_e( 'הקטנה', 'tra-vel-v2' ); ?>"><i data-lucide="minus"></i></button><a href="<?php echo esc_url( $map_url ); ?>" aria-label="<?php esc_attr_e( 'מפה מלאה', 'tra-vel-v2' ); ?>"><i data-lucide="maximize-2"></i></a></div>
				<div class="home-reveal-feedback" data-home-reveal data-state="ready"><span><i data-lucide="sparkles"></i><span><small data-home-reveal-context><?php echo esc_html( $home_campaign_is_seasonal ? __( 'כיוון שמתאים לעונה', 'tra-vel-v2' ) : __( 'כיוון להתחלת החיפוש', 'tra-vel-v2' ) ); ?></small><strong data-home-reveal-status><?php esc_html_e( 'בחרו יעד או לחצו על תפתיעו אותי.', 'tra-vel-v2' ); ?></strong></span></span><button data-home-reveal-cancel type="button" hidden><?php esc_html_e( 'עצרו', 'tra-vel-v2' ); ?></button></div>
			</div>
				<article class="map-result" data-map-result>
					<img class="map-result-image" data-result-image src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/' . $home_default_image ) ); ?>" alt="<?php echo esc_attr( $home_default_label ); ?>">
					<div class="map-result-body"><div class="result-top"><div><small data-result-context><?php echo esc_html( $home_default_context ); ?></small><h3 data-result-city><?php echo esc_html( $home_default_label ); ?></h3></div><button class="save-button" type="button" aria-label="<?php esc_attr_e( 'שמירה', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></button></div><div class="result-tags" data-result-tags><?php foreach ( array_slice( (array) ( $home_default_data['tags'] ?? array() ), 0, 3 ) as $tag ) : ?><span><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></div><div class="result-price"><div><small><?php esc_html_e( 'מחיר לתכנון לאדם', 'tra-vel-v2' ); ?></small><strong data-result-price><?php echo esc_html( $home_default_price ); ?></strong></div><p data-result-note><?php esc_html_e( 'המחיר הסופי מאומת מול הספק לפני התשלום.', 'tra-vel-v2' ); ?></p></div>
					<div class="home-plan-360" data-home-plan data-destination="<?php echo esc_attr( $home_default_destination ); ?>"><div><span><?php esc_html_e( 'כל החופשה במקום אחד', 'tra-vel-v2' ); ?></span><strong data-home-plan-summary><?php echo esc_html( sprintf( __( 'פתחו וערכו כל חלק בתכנון החופשה ל%s.', 'tra-vel-v2' ), $home_default_city ) ); ?></strong></div>
						<ul class="home-plan-modules" data-home-plan-components aria-label="<?php esc_attr_e( 'שמונה רכיבים לעריכת החופשה', 'tra-vel-v2' ); ?>">
							<li data-home-plan-record="flights"><a data-home-plan-component="flights" data-home-plan-flight href="<?php echo esc_url( add_query_arg( 'destination', $home_default_airport, home_url( '/flights/' ) ) ); ?>"><i data-lucide="plane-takeoff"></i><span><b><?php esc_html_e( 'טיסות וכבודה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'השוו מסלולים, כבודה ועלות מלאה', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="accommodation"><a data-home-plan-component="accommodation" data-home-plan-stay href="<?php echo esc_url( add_query_arg( 'destination', $home_default_airport, home_url( '/hotels/' ) ) ); ?>"><i data-lucide="hotel"></i><span><b><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בחרו אזור, מסים ותנאי ביטול', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="transfers"><a data-home-plan-component="transfers" data-home-plan-transfer href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_airport, 'transfers' => 'true' ), home_url( '/packages/' ) ) ); ?>"><i data-lucide="car-taxi-front"></i><span><b><?php esc_html_e( 'העברות ותחבורה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'התאימו הגעה לשעת הנחיתה', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="activities"><a data-home-plan-component="activities" data-home-plan-activity data-home-plan-guide href="<?php echo esc_url( $home_default_guide ); ?>"><i data-lucide="ticket-check"></i><span><b><?php esc_html_e( 'פעילויות', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בנו קצב וחוויות לפי הימים', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="dining"><a data-home-plan-component="dining" data-home-plan-dining href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'dining' ), $planner_url ) ); ?>"><i data-lucide="utensils"></i><span><b><?php esc_html_e( 'אוכל והעדפות', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'הוסיפו כשרות, אלרגיות ותקציב', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="insurance"><a data-home-plan-component="insurance" data-home-plan-insurance href="<?php echo esc_url( add_query_arg( 'trip_destination', $home_default_destination, home_url( '/travel-insurance/' ) ) ); ?>"><i data-lucide="shield-check"></i><span><b><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'התאימו כיסוי להרכב ולפעילויות', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="connectivity"><a data-home-plan-component="connectivity" data-home-plan-connectivity href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'connectivity' ), $planner_url ) ); ?>"><i data-lucide="wifi"></i><span><b><?php esc_html_e( 'תקשורת ו-eSIM', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'השוו נפח גלישה וכיסוי', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
							<li data-home-plan-record="equipment"><a data-home-plan-component="equipment" data-home-plan-equipment href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'equipment' ), $planner_url ) ); ?>"><i data-lucide="luggage"></i><span><b><?php esc_html_e( 'ציוד והשכרה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בדקו מה לארוז, לקנות או לשכור', 'tra-vel-v2' ); ?></small></span><em><?php esc_html_e( 'עריכה', 'tra-vel-v2' ); ?></em></a></li>
						</ul>
						<a class="home-plan-ai" data-home-plan-ai href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment' ), $planner_url ) ); ?>"><i data-lucide="sparkles"></i><span data-home-plan-ai-label><?php esc_html_e( 'בואו נתכנן את כל החופשה', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
						<a class="home-plan-supporting" data-home-plan-extras href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'dining,connectivity,equipment' ), $planner_url ) ); ?>"><i data-lucide="list-plus"></i><span><?php esc_html_e( 'ערכו יחד אוכל, תקשורת וציוד', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
						<details class="home-plan-ledger" data-home-plan-ledger><summary><span><i data-lucide="calculator"></i><b><?php esc_html_e( 'החופשה שלכם, חלק אחר חלק', 'tra-vel-v2' ); ?></b></span><small data-home-plan-ledger-state><?php esc_html_e( '8 חלקים שאפשר לערוך. המחיר, הזמינות והתנאים ייבדקו לפני הרכישה.', 'tra-vel-v2' ); ?></small></summary><div><span><b><?php esc_html_e( 'טיסות וכבודה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'להשוואה', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'לבחירת אזור', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'העברות ותחבורה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'להתאמת מסלול', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'פעילויות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'לבניית הימים', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'אוכל והעדפות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'להתאמה אישית', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'ביטוח נסיעות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'לבדיקת כיסוי', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'תקשורת ו-eSIM', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'להשוואת חבילות', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'ציוד והשכרה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'להשלמת הצרכים', 'tra-vel-v2' ); ?></em></span></div><p><?php esc_html_e( 'אלה אפשרויות לתכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></p></details>
						<a class="home-plan-full" data-home-plan-full href="<?php echo esc_url( add_query_arg( array( 'destination' => $home_default_destination, 'scope' => 'flights,accommodation,transfers,activities,dining,insurance,connectivity,equipment' ), $map_url ) ); ?>"><span data-home-plan-full-label><?php echo esc_html( sprintf( __( 'פתחו את התכנון המלא ל%s', 'tra-vel-v2' ), $home_default_city ) ); ?></span><i data-lucide="arrow-left"></i></a>
					</div></div>
				</article>
				<?php tra_vel_v2_demo_disclosure(); ?>
			</div>
		</div>
	</section>

	<section class="search-zone" id="search">
		<div class="page-width">
			<noscript>
				<style>.search-zone .product-tabs,.search-zone .search-dock,.search-zone .home-search-progress{display:none!important}</style>
				<nav class="product-links-noscript" aria-label="<?php esc_attr_e( 'פתיחת השוואה ללא JavaScript', 'tra-vel-v2' ); ?>"><p><?php esc_html_e( 'בחרו עמוד השוואה והשלימו בו את פרטי הנסיעה.', 'tra-vel-v2' ); ?></p><a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'מפת היעדים', 'tra-vel-v2' ); ?></a></nav>
			</noscript>
			<div class="product-tabs" role="tablist" aria-label="<?php esc_attr_e( 'סוג חיפוש', 'tra-vel-v2' ); ?>" aria-orientation="horizontal">
				<button id="home-search-tab-package" class="is-active" type="button" role="tab" aria-selected="true" aria-controls="home-search-panel" tabindex="0" data-product-kind="package" data-product-action="<?php echo esc_url( home_url( '/packages/' ) ); ?>" data-submit-label="<?php esc_attr_e( 'השוו טיסה ומלון', 'tra-vel-v2' ); ?>" data-departure-label="<?php esc_attr_e( 'יציאה', 'tra-vel-v2' ); ?>" data-return-label="<?php esc_attr_e( 'חזרה', 'tra-vel-v2' ); ?>"><i data-lucide="package"></i><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></button>
				<button id="home-search-tab-flights" type="button" role="tab" aria-selected="false" aria-controls="home-search-panel" tabindex="-1" data-product-kind="flights" data-product-action="<?php echo esc_url( home_url( '/flights/' ) ); ?>" data-submit-label="<?php esc_attr_e( 'השוו טיסות', 'tra-vel-v2' ); ?>" data-departure-label="<?php esc_attr_e( 'יציאה', 'tra-vel-v2' ); ?>" data-return-label="<?php esc_attr_e( 'חזרה', 'tra-vel-v2' ); ?>"><i data-lucide="plane"></i><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></button>
				<button id="home-search-tab-hotels" type="button" role="tab" aria-selected="false" aria-controls="home-search-panel" tabindex="-1" data-product-kind="hotels" data-product-action="<?php echo esc_url( home_url( '/hotels/' ) ); ?>" data-submit-label="<?php esc_attr_e( 'השוו מלונות', 'tra-vel-v2' ); ?>" data-departure-label="<?php esc_attr_e( 'כניסה', 'tra-vel-v2' ); ?>" data-return-label="<?php esc_attr_e( 'יציאה', 'tra-vel-v2' ); ?>"><i data-lucide="hotel"></i><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></button>
				<button id="home-search-tab-packages" type="button" role="tab" aria-selected="false" aria-controls="home-search-panel" tabindex="-1" data-product-kind="packages" data-product-action="<?php echo esc_url( home_url( '/packages/' ) ); ?>" data-submit-label="<?php esc_attr_e( 'מצאו חבילות', 'tra-vel-v2' ); ?>" data-departure-label="<?php esc_attr_e( 'יציאה', 'tra-vel-v2' ); ?>" data-return-label="<?php esc_attr_e( 'חזרה', 'tra-vel-v2' ); ?>"><i data-lucide="palmtree"></i><?php esc_html_e( 'חבילות', 'tra-vel-v2' ); ?></button>
				<button id="home-search-tab-insurance" type="button" role="tab" aria-selected="false" aria-controls="home-search-panel" tabindex="-1" data-product-kind="insurance" data-product-action="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>" data-submit-label="<?php esc_attr_e( 'לבדיקת נושאים לביטוח', 'tra-vel-v2' ); ?>" data-departure-label="<?php esc_attr_e( 'תחילת נסיעה', 'tra-vel-v2' ); ?>" data-return-label="<?php esc_attr_e( 'סיום נסיעה', 'tra-vel-v2' ); ?>"><i data-lucide="shield-check"></i><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></button>
			</div>
			<form id="home-search-panel" class="search-dock" action="<?php echo esc_url( $map_url ); ?>" method="get" role="tabpanel" aria-labelledby="home-search-tab-package" aria-describedby="home-search-status" aria-busy="false" data-home-search data-product-kind="package" data-map-action="<?php echo esc_url( $map_url ); ?>" data-state="ready" data-uses-origin="true" data-uses-rooms="true">
				<fieldset class="search-field search-route-field">
					<legend><i data-lucide="map-pin" aria-hidden="true"></i><?php esc_html_e( 'מאיפה ולאן', 'tra-vel-v2' ); ?></legend>
					<div class="search-field-controls">
						<label data-home-origin-wrap><span><?php esc_html_e( 'יציאה מ', 'tra-vel-v2' ); ?></span><input name="origin" value="TLV" maxlength="3" pattern="[A-Za-z]{3}" inputmode="text" autocomplete="off" aria-label="<?php esc_attr_e( 'קוד שדה תעופה מוצא', 'tra-vel-v2' ); ?>" required></label>
						<i data-lucide="arrow-left" aria-hidden="true"></i>
						<label><span><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?></span><select name="destination" data-home-destination aria-label="<?php esc_attr_e( 'בחירת יעד', 'tra-vel-v2' ); ?>"><option value="anywhere" data-code="anywhere" data-slug="anywhere"><?php esc_html_e( 'לא משנה לאן', 'tra-vel-v2' ); ?></option><?php foreach ( $home_destinations as $destination ) : $destination_id = sanitize_key( $destination['id'] ?? '' ); $airport_code = sanitize_text_field( $destination['airport']['code'] ?? '' ); if ( ! $destination_id || ! $airport_code ) { continue; } ?><option value="<?php echo esc_attr( $airport_code ); ?>" data-code="<?php echo esc_attr( $airport_code ); ?>" data-slug="<?php echo esc_attr( $destination_id ); ?>"><?php echo esc_html( $destination['city'] ?? $destination_id ); ?> · <?php echo esc_html( $airport_code ); ?></option><?php endforeach; ?></select></label>
					</div>
				</fieldset>
				<fieldset class="search-field search-date-field">
					<legend><i data-lucide="calendar-range" aria-hidden="true"></i><?php esc_html_e( 'מתי', 'tra-vel-v2' ); ?></legend>
					<div class="search-field-controls search-date-controls"><label><span data-home-departure-label><?php esc_html_e( 'יציאה', 'tra-vel-v2' ); ?></span><input type="date" name="departure_date" min="<?php echo esc_attr( $home_today ); ?>" value="<?php echo esc_attr( $home_departure ); ?>" data-home-departure required></label><label><span data-home-return-label><?php esc_html_e( 'חזרה', 'tra-vel-v2' ); ?></span><input type="date" name="return_date" min="<?php echo esc_attr( $home_now->modify( '+31 days' )->format( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $home_return ); ?>" data-home-return required></label></div>
				</fieldset>
				<fieldset class="search-field search-traveler-field">
					<legend><i data-lucide="users" aria-hidden="true"></i><?php esc_html_e( 'מי נוסע', 'tra-vel-v2' ); ?></legend>
					<div class="search-field-controls"><label><span><?php esc_html_e( 'מבוגרים', 'tra-vel-v2' ); ?></span><select name="adults" data-home-adults><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></label><label><span><?php esc_html_e( 'ילדים', 'tra-vel-v2' ); ?></span><select name="children" data-home-children><option value="0" selected>0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select></label><label data-home-rooms-wrap><span><?php esc_html_e( 'חדרים', 'tra-vel-v2' ); ?></span><select name="rooms" data-home-rooms><option value="1" selected>1</option><option value="2">2</option><option value="3">3</option></select></label></div>
				</fieldset>
				<button class="search-submit" type="submit" data-home-search-submit><i data-lucide="search"></i><span><?php esc_html_e( 'השוו טיסה ומלון', 'tra-vel-v2' ); ?></span></button>
			</form>
			<div class="home-search-progress" data-home-search-progress data-state="ready">
				<ol aria-label="<?php esc_attr_e( 'התקדמות החיפוש', 'tra-vel-v2' ); ?>"><li data-home-search-step="product" data-state="confirmed"><i data-lucide="layout-list" aria-hidden="true"></i><span><b><?php esc_html_e( 'סוג השוואה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'בחרתם: טיסה ומלון', 'tra-vel-v2' ); ?></small></span></li><li data-home-search-step="criteria" data-state="confirmed"><i data-lucide="list-checks" aria-hidden="true"></i><span><b><?php esc_html_e( 'פרטי נסיעה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'הפרטים מוכנים', 'tra-vel-v2' ); ?></small></span></li><li data-home-search-step="handoff" data-state="waiting"><i data-lucide="scan-search" aria-hidden="true"></i><span><b><?php esc_html_e( 'בדיקת אפשרויות', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'תיפתח לאחר לחיצה', 'tra-vel-v2' ); ?></small></span></li></ol>
				<p id="home-search-status" data-home-search-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'הפרטים מוכנים. בעמוד הבא יוצגו מקור וזמן בדיקה כאשר הם קיימים.', 'tra-vel-v2' ); ?></p>
			</div>
			<div class="quick-links"><span><?php esc_html_e( 'רעיונות מהירים:', 'tra-vel-v2' ); ?></span><a href="<?php echo esc_url( add_query_arg( 'budget', '950', $map_url ) ); ?>"><?php esc_html_e( 'עד $950 לאדם', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( add_query_arg( 'direct', '1', $map_url ) ); ?>"><?php esc_html_e( 'טיסות ישירות', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( add_query_arg( 'intent', 'family', $map_url ) ); ?>"><?php esc_html_e( 'עם ילדים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'לא משנה לאן', 'tra-vel-v2' ); ?></a></div>
			<div class="intelligence-strip"><div><span class="strip-icon"><i data-lucide="trending-down"></i></span><span><small><?php esc_html_e( 'מחיר ותנאים', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'תכנון עכשיו, אימות לפני רכישה', 'tra-vel-v2' ); ?></strong></span></div><div><span class="strip-icon"><i data-lucide="calculator"></i></span><span><small><?php esc_html_e( 'פירוט עלויות', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'מה כלול ומה לא', 'tra-vel-v2' ); ?></strong></span></div><div><span class="strip-icon"><i data-lucide="route"></i></span><span><small><?php esc_html_e( 'דרכי הגעה', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'ישיר, קונקשן ועצירה בדרך', 'tra-vel-v2' ); ?></strong></span></div><div><span class="strip-icon"><i data-lucide="badge-check"></i></span><span><small><?php esc_html_e( 'מה מתאים לכם', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'יתרונות, חסרונות ורמת סיכון', 'tra-vel-v2' ); ?></strong></span></div></div>
		</div>
	</section>

	<script type="application/json" data-home-route-data><?php echo wp_json_encode( $home_route_sets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
	<section class="section" id="routes">
		<div class="page-width">
			<div class="section-heading"><div><span class="eyebrow" data-home-route-eyebrow><?php echo esc_html( sprintf( __( 'דרכים להגיע ל%s', 'tra-vel-v2' ), $home_default_city ) ); ?></span><h2 data-route-title><?php echo esc_html( sprintf( __( 'מתל אביב ל%s: השוואת מסלולים', 'tra-vel-v2' ), $home_default_city ) ); ?></h2><p><?php esc_html_e( 'השוו עלות לתכנון, זמן, נוחות, כבודה וגמישות לפני שבוחרים.', 'tra-vel-v2' ); ?></p></div><a class="text-link" data-home-route-link href="<?php echo esc_url( add_query_arg( 'destination', $home_default_airport, home_url( '/flights/' ) ) ); ?>"><span data-home-route-link-label><?php echo esc_html( sprintf( __( 'השוו טיסות ל%s', 'tra-vel-v2' ), $home_default_city ) ); ?></span> <i data-lucide="arrow-left"></i></a></div>
			<p class="route-empty" data-home-route-empty<?php echo $home_default_routes ? ' hidden' : ''; ?>><?php esc_html_e( 'אין עדיין השוואת מסלולים ליעד הזה. פתחו את חיפוש הטיסות כדי לבדוק תאריכים ונוסעים.', 'tra-vel-v2' ); ?></p>
			<div class="route-board" data-home-route-board<?php echo $home_default_routes ? '' : ' hidden'; ?>><header class="route-board-head"><div class="route-airports"><b class="airport-code">TLV</b><span><i data-lucide="plane"></i></span><b class="airport-code" data-home-route-airport><?php echo esc_html( $home_default_airport ); ?></b><small><?php esc_html_e( 'מסלולים לתכנון · 2 נוסעים', 'tra-vel-v2' ); ?></small></div><span class="updated"><?php esc_html_e( 'המחיר, הזמינות והתנאים מאומתים לפני התשלום', 'tra-vel-v2' ); ?></span></header><div class="route-cards" data-home-route-cards>
				<?php
				$home_best_route_score = $home_default_routes ? max( array_map( static fn( $route ) => (int) ( $route['score'] ?? 0 ), $home_default_routes ) ) : 0;
				foreach ( array_slice( $home_default_routes, 0, 3 ) as $home_route ) :
					$route_id       = sanitize_key( $home_route['id'] ?? '' );
					$route_label    = sanitize_text_field( $home_route['label'] ?? __( 'מסלול לתכנון', 'tra-vel-v2' ) );
					$route_badge    = sanitize_text_field( $home_route['badge'] ?? __( 'אפשרות לתכנון', 'tra-vel-v2' ) );
					$route_minutes  = max( 0, (int) ( $home_route['duration_minutes'] ?? 0 ) );
					$route_duration = $route_minutes ? sprintf( '%d:%02d שעות', intdiv( $route_minutes, 60 ), $route_minutes % 60 ) : __( 'זמן ייבדק', 'tra-vel-v2' );
					$route_stops    = (int) ( $home_route['stops'] ?? 0 );
					$route_stops_label = 0 === $route_stops ? __( 'ישיר', 'tra-vel-v2' ) : ( 1 === $route_stops ? __( 'עצירה אחת', 'tra-vel-v2' ) : sprintf( __( '%d עצירות', 'tra-vel-v2' ), $route_stops ) );
					$route_total    = (float) ( $home_route['costs']['total'] ?? 0 );
					$route_price    = $route_total > 0 ? '$' . number_format_i18n( $route_total, 0 ) : __( 'בהצעה האישית', 'tra-vel-v2' );
					$route_pros     = array_slice( array_filter( array_map( 'sanitize_text_field', (array) ( $home_route['pros'] ?? array() ) ) ), 0, 3 );
					$route_summary  = $route_label . ' · ' . $route_price . ' · ' . __( 'מחיר לתכנון', 'tra-vel-v2' );
					$is_recommended = (int) ( $home_route['score'] ?? 0 ) === $home_best_route_score;
					?>
					<button class="route-card<?php echo $is_recommended ? ' recommended' : ''; ?>" type="button" data-route="<?php echo esc_attr( $route_id ); ?>" data-route-summary="<?php echo esc_attr( $route_summary ); ?>" aria-pressed="false"><div class="route-card-top"><span class="route-badge"><?php echo esc_html( $route_badge ); ?> · <?php esc_html_e( 'לתכנון', 'tra-vel-v2' ); ?></span><strong class="price"><?php echo esc_html( $route_price ); ?></strong></div><h3><?php echo esc_html( $route_label ); ?></h3><p><?php echo esc_html( $route_duration . ' · ' . $route_stops_label ); ?></p><div class="route-data"><?php foreach ( $route_pros as $route_pro ) : ?><span><i data-lucide="circle-check-big"></i><?php echo esc_html( $route_pro ); ?></span><?php endforeach; ?></div></button>
				<?php endforeach; ?>
			</div><p class="updated" data-route-summary><?php esc_html_e( 'בחרו מסלול כדי לשמור העדפה. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ); ?></p><a class="text-link route-board-cta" href="<?php echo esc_url( add_query_arg( 'destination', $home_default_airport, home_url( '/flights/' ) ) ); ?>"><span><?php echo esc_html( sprintf( __( 'השוו את המסלולים ל%s בחיפוש הטיסות', 'tra-vel-v2' ), $home_default_city ) ); ?></span> <i data-lucide="arrow-left"></i></a></div>
		</div>
	</section>

	<section class="section soft" id="deals"><div class="page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'חופשות שכדאי לבדוק', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'רעיונות לפי יעד וסגנון', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'בחרו רעיון כדי לבדוק תאריכים, מה כלול והמחיר לכל הנוסעים.', 'tra-vel-v2' ); ?></p></div><a class="text-link" href="<?php echo esc_url( $map_url ); ?>"><?php esc_html_e( 'ראו יעדים על המפה', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></a></div><div class="deal-grid">
		<?php
		$deals = array(
			array( 'image' => 'city-budapest.webp', 'tag' => 'עירוני', 'meta' => 'בודפשט · 4 לילות · מרכז העיר', 'title' => 'סוף שבוע עם מסלול גמיש', 'price' => 'החל מ-$229', 'destination' => 'budapest', 'destination_label' => 'בודפשט' ),
			array( 'image' => 'thailand.jpg', 'tag' => 'טיסה + מלון', 'meta' => 'תאילנד · 10 לילות · לפי העונה', 'title' => 'איים, חופים וקצב רגוע', 'price' => 'החל מ-$950', 'destination' => 'bangkok', 'destination_label' => 'תאילנד' ),
			array( 'image' => 'city-vienna.webp', 'tag' => 'עיר ותרבות', 'meta' => 'וינה · 3 לילות · אזור נוח', 'title' => 'תרבות, אוכל ותחבורה יעילה', 'price' => 'החל מ-$279', 'destination' => 'vienna', 'destination_label' => 'וינה' ),
		);
		foreach ( $deals as $deal ) :
			?>
			<article class="deal-card"><div class="deal-media" style="background-image:url('<?php echo tra_vel_v2_asset_uri( 'images/' . $deal['image'] ); ?>')"><span class="deal-tag"><?php echo esc_html( $deal['tag'] ); ?></span><button class="save-button" type="button" aria-label="<?php esc_attr_e( 'שמירה', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></button></div><div class="deal-body"><div class="deal-meta"><?php echo esc_html( $deal['meta'] ); ?></div><h3 class="deal-title"><?php echo esc_html( $deal['title'] ); ?></h3><div class="deal-footer"><div class="deal-price"><small><?php esc_html_e( 'מחיר לתכנון לאדם · מאומת לפני התשלום', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $deal['price'] ); ?></strong></div><a href="<?php echo esc_url( add_query_arg( 'destination', $deal['destination'], $map_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'פתחו את %s במפת החופשות', 'tra-vel-v2' ), $deal['destination_label'] ) ); ?>"><i data-lucide="arrow-left"></i></a></div></div></article>
		<?php endforeach; ?>
		<article class="deal-card"><div class="deal-media" style="background:linear-gradient(135deg,#0d3440,#0b6d7e)"><span class="deal-tag"><?php esc_html_e( 'מה חשוב לבדוק', 'tra-vel-v2' ); ?></span><span class="field-icon" style="position:absolute;inset:50% auto auto 50%;transform:translate(-50%,-50%);width:72px;height:72px"><i data-lucide="shield-check"></i></span></div><div class="deal-body"><div class="deal-meta"><?php esc_html_e( 'ביטוח נסיעות · מדריך לכיסויים', 'tra-vel-v2' ); ?></div><h3 class="deal-title"><?php esc_html_e( 'רפואה, כבודה וספורט: מה לבדוק בפוליסה', 'tra-vel-v2' ); ?></h3><div class="deal-footer"><div class="deal-price"><small><?php esc_html_e( 'פרטים לפני השוואה', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'למדריך', 'tra-vel-v2' ); ?></strong></div><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>" aria-label="<?php esc_attr_e( 'לפרטים על בדיקת ביטוח נסיעות', 'tra-vel-v2' ); ?>"><i data-lucide="arrow-left"></i></a></div></div></article>
	</div></div></section>

	<section class="section dark" id="ai"><div class="page-width ai-panel"><div><span class="kicker"><i data-lucide="sparkles"></i><?php esc_html_e( 'מתכנן החופשה', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'ספרו לנו איזו חופשה אתם רוצים', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'אפשר לכתוב בחופשיות. יעד, תקציב, תאריכים, נוסעים ומה חשוב לכם. נשאל רק על פרט שחסר.', 'tra-vel-v2' ); ?></p><a class="ai-input" href="<?php echo esc_url( $planner_url ); ?>"><i data-lucide="message-circle-more"></i><span><?php esc_html_e( '“טיול זוגי רגוע באסיה, 12 יום, בלי יותר מעצירה אחת...”', 'tra-vel-v2' ); ?></span><span class="ai-input-action"><?php esc_html_e( 'סדרו לי תוכנית', 'tra-vel-v2' ); ?></span></a></div><div class="ai-visual"><span class="ai-orb"><i data-lucide="sparkles"></i></span><article class="suggestion one"><i data-lucide="plane-takeoff"></i><span><small><?php esc_html_e( 'טיסה', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'קונקשן בכרטיס אחד עשוי לאזן זמן ופשטות', 'tra-vel-v2' ); ?></strong></span></article><article class="suggestion two"><i data-lucide="cloud-sun"></i><span><small><?php esc_html_e( 'עונה', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'סמנו איזה מזג אוויר מתאים לכם', 'tra-vel-v2' ); ?></strong></span></article><article class="suggestion three"><i data-lucide="route"></i><span><small><?php esc_html_e( 'קצב', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'בחרו בין מסלול רגוע למסלול מגוון', 'tra-vel-v2' ); ?></strong></span></article></div></div></section>

	<section class="section" id="guides"><div class="page-width"><div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'מדריכים שעוזרים לתכנן', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מדריכי עומק לפי יעד ושאלה', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'קראו על אזורים, עונות, מסלולים ועלויות שכדאי לבדוק לפני שבוחרים.', 'tra-vel-v2' ); ?></p></div><a class="text-link" href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'כל מדריכי היעדים', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></a></div><div class="guide-layout"><a class="featured-guide" href="<?php echo esc_url( $thailand_url ); ?>" style="background-image:linear-gradient(0deg,rgba(4,17,23,.9),transparent 65%),url('<?php echo tra_vel_v2_asset_uri( 'images/thailand.jpg' ); ?>')"><div class="featured-guide-content"><span><?php esc_html_e( 'מדריך מקיף לתאילנד', 'tra-vel-v2' ); ?></span><h3><?php esc_html_e( 'תאילנד 2026: המסלול, התקציב והעונה שמתאימים לכם', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'מפת אזורים, טיסות, איים, מלונות, ביטוח וטעויות יקרות שכדאי למנוע.', 'tra-vel-v2' ); ?></p><b><?php esc_html_e( 'למדריך המלא', 'tra-vel-v2' ); ?> <i data-lucide="arrow-left"></i></b></div></a><div class="guide-stack"><a class="guide-row" href="<?php echo esc_url( add_query_arg( 'topic', 'flight-prices', home_url( '/guides/' ) ) ); ?>"><span class="guide-number">01</span><div><small><?php esc_html_e( 'תכנון', 'tra-vel-v2' ); ?></small><h3><?php esc_html_e( 'איך קוראים מחיר טיסה ומה באמת כלול', 'tra-vel-v2' ); ?></h3></div><i data-lucide="arrow-left"></i></a><a class="guide-row" href="<?php echo esc_url( add_query_arg( 'topic', 'trip-costs', home_url( '/guides/' ) ) ); ?>"><span class="guide-number">02</span><div><small><?php esc_html_e( 'כסף', 'tra-vel-v2' ); ?></small><h3><?php esc_html_e( 'כבודה, המרה והעברות: העלויות שמתחבאות', 'tra-vel-v2' ); ?></h3></div><i data-lucide="arrow-left"></i></a><a class="guide-row" href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><span class="guide-number">03</span><div><small><?php esc_html_e( 'ביטחון', 'tra-vel-v2' ); ?></small><h3><?php esc_html_e( 'מה לבדוק בביטוח נסיעות לפי המסלול', 'tra-vel-v2' ); ?></h3></div><i data-lucide="arrow-left"></i></a></div></div></div></section>
</main>
<?php get_footer(); ?>
