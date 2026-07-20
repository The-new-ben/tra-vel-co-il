<?php
/**
 * Template Name: Tra-Vel Destination & Guide Directory
 * Template Post Type: page
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_slug     = get_post_field( 'post_name', get_queried_object_id() );
$is_guides     = 'guides' === $page_slug;
$manifest_path = TRA_VEL_V2_PATH . '/assets/data/editorial-directory.json';
$manifest      = file_exists( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : array();
$earth_destinations = isset( $manifest['destinations'] ) && is_array( $manifest['destinations'] ) ? $manifest['destinations'] : array();
$supporting_guides = isset( $manifest['supporting_guides'] ) && is_array( $manifest['supporting_guides'] ) ? $manifest['supporting_guides'] : array();
$destinations  = $is_guides
	? array_values(
		array_filter(
			array_merge( $earth_destinations, $supporting_guides ),
			static function ( $destination ) {
				return 'published' === ( $destination['guide_status'] ?? '' ) && ! empty( $destination['guide_path'] );
			}
		)
	)
	: $earth_destinations;
$orbit_destinations = $earth_destinations;
$region_count  = count( array_unique( array_column( $destinations, 'region' ) ) );
$guide_source_total = array_sum( array_map( 'intval', array_column( $destinations, 'source_count' ) ) );
$hero = $is_guides
	? array(
		'eyebrow' => __( 'מדריכי יעדים וחופשות', 'tra-vel-v2' ),
		'title'   => __( 'כל מה שצריך לדעת לפני שסוגרים.', 'tra-vel-v2' ),
		'copy'    => __( 'עונות, אזורי לינה, טיסות, מסלולים, תקציב וביטוח. בחרו יעד וקבלו תשובות וכלים מתאימים.', 'tra-vel-v2' ),
	)
	: array(
		'eyebrow' => __( 'יעדים וחופשות', 'tra-vel-v2' ),
		'title'   => __( 'לאן תרצו לטוס?', 'tra-vel-v2' ),
		'copy'    => __( 'עיר או חוף, סוף שבוע או טיול גדול, קצב מהיר או רגוע. בחרו יעד והמשיכו למפה, למדריך ולבדיקת האפשרויות.', 'tra-vel-v2' ),
	);

get_header();
?>
<main id="main-content" class="directory-page" data-directory-root data-directory-kind="<?php echo esc_attr( $page_slug ); ?>">
	<section class="directory-hero">
		<div class="page-width directory-hero-grid">
			<div class="directory-hero-copy">
				<span class="kicker"><i data-lucide="compass"></i><?php echo esc_html( $hero['eyebrow'] ); ?></span>
				<h1><?php echo esc_html( $hero['title'] ); ?></h1>
				<p><?php echo esc_html( $hero['copy'] ); ?></p>
				<div class="directory-hero-actions"><a class="header-cta" href="#directory-grid"><i data-lucide="layout-grid"></i><?php echo esc_html( $is_guides ? __( 'לכל המדריכים', 'tra-vel-v2' ) : __( 'לכל היעדים', 'tra-vel-v2' ) ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?></a></div>
				<?php if ( $is_guides ) : ?>
				<div class="directory-proof"><span><strong><?php echo esc_html( count( $destinations ) ); ?></strong><?php esc_html_e( 'מדריכי עומק', 'tra-vel-v2' ); ?></span><span><strong><i data-lucide="table-properties"></i></strong><?php esc_html_e( 'טבלאות החלטה שימושיות', 'tra-vel-v2' ); ?></span><span><strong><?php echo esc_html( $guide_source_total ); ?></strong><?php esc_html_e( 'קישורים למקורות שנבדקו', 'tra-vel-v2' ); ?></span></div>
				<?php else : ?>
				<div class="directory-proof"><span><strong><?php echo esc_html( count( $destinations ) ); ?></strong><?php esc_html_e( 'יעדים לבחירה', 'tra-vel-v2' ); ?></span><span><strong><?php echo esc_html( $region_count ); ?></strong><?php esc_html_e( 'אזורי עולם', 'tra-vel-v2' ); ?></span><span><strong>✓</strong><?php esc_html_e( 'סינון לפי אזור וסגנון', 'tra-vel-v2' ); ?></span></div>
				<?php endif; ?>
			</div>
			<div class="directory-orbit" aria-label="<?php esc_attr_e( 'מפת יעדים אינטראקטיבית', 'tra-vel-v2' ); ?>">
				<div class="directory-orbit-globe"></div><span class="directory-origin">TLV<small><?php esc_html_e( 'מתחילים כאן', 'tra-vel-v2' ); ?></small></span>
				<?php
				$positions = array(
					'budapest' => array( 38, 30 ), 'prague' => array( 29, 22 ), 'vienna' => array( 46, 38 ),
					'thailand' => array( 72, 64 ), 'athens' => array( 54, 48 ), 'dubai' => array( 64, 54 ),
					'tokyo' => array( 84, 34 ), 'lisbon' => array( 19, 43 ),
					'larnaca' => array( 60, 51 ), 'crete' => array( 49, 55 ),
				);
				foreach ( $orbit_destinations as $destination ) :
					$position = $positions[ $destination['id'] ] ?? array( 50, 50 );
					$map_url  = add_query_arg( 'destination', $destination['map_state'], home_url( '/travel-map/' ) );
					?>
					<a class="directory-map-pin" href="<?php echo esc_url( $map_url ); ?>" style="--pin-x:<?php echo esc_attr( $position[0] ); ?>%;--pin-y:<?php echo esc_attr( $position[1] ); ?>%" aria-label="<?php echo esc_attr( sprintf( __( 'פתחו את %s על המפה', 'tra-vel-v2' ), $destination['city'] ) ); ?>"><strong><?php echo esc_html( $destination['city'] ); ?></strong><small><?php echo esc_html( $destination['experience_label'] ); ?></small></a>
				<?php endforeach; ?>
				<div class="directory-orbit-note"><i data-lucide="mouse-pointer-click"></i><span><strong><?php esc_html_e( 'בחרו נקודה', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'והמשיכו לבדוק מסלול, זמן ופירוט עלויות', 'tra-vel-v2' ); ?></span></div>
			</div>
		</div>
	</section>

	<section class="directory-command page-width" id="directory-grid">
		<div class="section-heading"><div><span class="eyebrow"><?php echo esc_html( $is_guides ? __( 'מדריכי יעדים וחופשות', 'tra-vel-v2' ) : __( 'כל היעדים', 'tra-vel-v2' ) ); ?></span><h2><?php echo esc_html( $is_guides ? __( 'מה תרצו לדעת לפני שסוגרים?', 'tra-vel-v2' ) : __( 'איזו חופשה מתאימה עכשיו?', 'tra-vel-v2' ) ); ?></h2><p><?php echo esc_html( $is_guides ? __( 'בחרו מדריך שנבדק וקבלו תשובות על עונה, טיסות, אזורי לינה, מסלול, תקציב וביטוח לפני שמבקשים מחיר.', 'tra-vel-v2' ) : __( 'סננו לפי אזור או חוויה והשוו מחירי תכנון. המחיר, הזמינות והתנאים מאומתים לפני התשלום.', 'tra-vel-v2' ) ); ?></p></div><strong data-directory-count><?php echo esc_html( count( $destinations ) ); ?></strong></div>
		<form class="directory-filter" data-directory-filter role="search"><label><i data-lucide="search"></i><span class="screen-reader-text"><?php esc_html_e( 'חיפוש יעד', 'tra-vel-v2' ); ?></span><input data-directory-query type="search" placeholder="<?php esc_attr_e( 'חפשו עיר, מדינה או סגנון חופשה', 'tra-vel-v2' ); ?>"></label><div data-directory-filters><button class="is-active" type="button" data-directory-value="all"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="europe"><?php esc_html_e( 'אירופה', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="asia"><?php esc_html_e( 'אסיה', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="city"><?php esc_html_e( 'עיר', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="beach"><?php esc_html_e( 'חוף', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="culture"><?php esc_html_e( 'תרבות', 'tra-vel-v2' ); ?></button></div></form>

		<div class="directory-grid" data-directory-grid>
			<?php foreach ( $destinations as $destination ) :
				$status = $destination['guide_status'] ?? 'research';
				$map_url = add_query_arg( 'destination', $destination['map_state'], home_url( '/travel-map/' ) );
				$search_text = implode( ' ', array( $destination['city'], $destination['country'], $destination['region_label'], $destination['experience_label'], $destination['best_for'] ) );
				?>
				<article id="destination-<?php echo esc_attr( $destination['id'] ); ?>" class="directory-card" data-directory-card data-directory-destination="<?php echo esc_attr( $destination['id'] ); ?>" data-directory-map-state="<?php echo esc_attr( $destination['map_state'] ); ?>" data-region="<?php echo esc_attr( $destination['region'] ); ?>" data-experience="<?php echo esc_attr( $destination['experience'] ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>">
					<div class="directory-card-image" style="background-image:linear-gradient(0deg,rgba(4,20,27,.82),transparent 68%),url('<?php echo esc_url( tra_vel_v2_asset_uri( 'images/' . $destination['image'] ) ); ?>')"><span><?php echo esc_html( $destination['region_label'] ); ?></span><div><small><?php echo esc_html( $destination['country'] ); ?></small><h3><?php echo esc_html( $destination['city'] ); ?></h3></div></div>
					<div class="directory-card-body"><div class="directory-status"><i data-lucide="compass"></i><?php echo esc_html( $destination['experience_label'] ); ?></div><p><?php echo esc_html( $destination['decision'] ); ?></p><div class="directory-meta"><span><i data-lucide="calendar-range"></i><?php echo esc_html( $destination['duration'] ); ?></span><span><i data-lucide="users"></i><?php echo esc_html( $destination['best_for'] ); ?></span></div>
					<div class="directory-card-actions"><a href="<?php echo esc_url( $map_url ); ?>"><i data-lucide="map"></i><?php esc_html_e( 'פתחו במפה', 'tra-vel-v2' ); ?></a><?php if ( 'published' === $status && ! empty( $destination['guide_path'] ) ) : ?><a class="is-primary" href="<?php echo esc_url( home_url( $destination['guide_path'] ) ); ?>"><?php esc_html_e( 'למדריך המלא', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a><?php else : ?><a class="is-primary" href="<?php echo esc_url( $map_url . '#map-support' ); ?>"><?php esc_html_e( 'השוו מסלולים', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a><?php endif; ?></div></div>
				</article>
			<?php endforeach; ?>
		</div>
		<div class="directory-empty" data-directory-empty hidden><i data-lucide="search-x"></i><h3><?php esc_html_e( 'לא מצאנו התאמה מדויקת', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'נסו אזור אחר או פתחו את המפה כדי לגלות יעד לפי זמן ותקציב.', 'tra-vel-v2' ); ?></p><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'למפת החופשות', 'tra-vel-v2' ); ?></a></div>
	</section>

	<?php if ( $is_guides ) : ?>
	<section class="directory-standard"><div class="page-width directory-standard-grid"><div><span class="eyebrow"><?php esc_html_e( 'מה תמצאו בכל מדריך', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'תשובה שימושית לפני כל החלטה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'כל מדריך מציג מקורות, תאריך בדיקה, חלופות, טבלאות החלטה וקישורים ישירים למפה ולשלב הבא.', 'tra-vel-v2' ); ?></p></div><div class="directory-standard-list"><span><i data-lucide="list-checks"></i><strong>✓</strong><?php esc_html_e( 'צעדים שאפשר לבצע', 'tra-vel-v2' ); ?></span><span><i data-lucide="database"></i><strong>✓</strong><?php esc_html_e( 'קישורים למקורות רשמיים', 'tra-vel-v2' ); ?></span><span><i data-lucide="badge-check"></i><strong>✓</strong><?php esc_html_e( 'תאריך ושיטת בדיקה', 'tra-vel-v2' ); ?></span></div></div></section>
	<?php else : ?>
	<section class="directory-standard"><div class="page-width directory-standard-grid"><div><span class="eyebrow"><?php esc_html_e( 'מה תמצאו בכל יעד', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'כל מה שצריך כדי לבחור חופשה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'ראו את היעד על המפה, קראו את המדריך והשוו טיסות, מלונות, חבילות וביטוח במקום אחד.', 'tra-vel-v2' ); ?></p></div><div class="directory-standard-list"><span><i data-lucide="map"></i><strong>1</strong><?php esc_html_e( 'מפת יעד', 'tra-vel-v2' ); ?></span><span><i data-lucide="layers-3"></i><strong>4</strong><?php esc_html_e( 'סוגי נסיעה להשוואה', 'tra-vel-v2' ); ?></span><span><i data-lucide="badge-check"></i><strong>✓</strong><?php esc_html_e( 'אימות מחיר ותנאים לפני רכישה', 'tra-vel-v2' ); ?></span></div></div></section>
	<?php endif; ?>

	<section class="directory-next page-width"><div><span class="eyebrow"><?php esc_html_e( 'מה עושים עכשיו?', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'בחרו יעד והמשיכו לבדיקה.', 'tra-vel-v2' ); ?></h2></div><div><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><span><strong><?php esc_html_e( 'מפת החופשות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'יעדים ודרכי הגעה', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><strong><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בדקו מחיר, כבודה וזמן', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="bed-double"></i><span><strong><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בדקו אזור, חדר ותנאים', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><span><strong><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'מה לבדוק בכיסוי', 'tra-vel-v2' ); ?></span></a></div></section>
</main>
<?php get_footer(); ?>
