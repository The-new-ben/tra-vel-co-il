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
$destinations  = isset( $manifest['destinations'] ) && is_array( $manifest['destinations'] ) ? $manifest['destinations'] : array();
$region_count  = count( array_unique( array_column( $destinations, 'region' ) ) );
$hero = $is_guides
	? array(
		'eyebrow' => __( 'Tra-Vel Guides', 'tra-vel-v2' ),
		'title'   => __( 'מדריכים שמובילים להחלטה. לא לעוד לשונית.', 'tra-vel-v2' ),
		'copy'    => __( 'כל מדריך מחבר בין עונות, אזורי לינה, מסלולים, עלויות והזמנה. מתחילים בהחלטה שמעסיקה אתכם וממשיכים לכלים המתאימים.', 'tra-vel-v2' ),
	)
	: array(
		'eyebrow' => __( 'Tra-Vel Destinations', 'tra-vel-v2' ),
		'title'   => __( 'בחרו חופשה לפי ההחלטה שחשובה לכם.', 'tra-vel-v2' ),
		'copy'    => __( 'עיר או חוף, סוף שבוע או טיול גדול, קצב מהיר או רגוע. כל יעד מתחבר למפה, למדריך ולהשוואה המתאימה.', 'tra-vel-v2' ),
	);

get_header();
?>
<main id="main-content" class="directory-page" data-directory-root>
	<section class="directory-hero">
		<div class="page-width directory-hero-grid">
			<div class="directory-hero-copy">
				<span class="kicker"><i data-lucide="compass"></i><?php echo esc_html( $hero['eyebrow'] ); ?></span>
				<h1><?php echo esc_html( $hero['title'] ); ?></h1>
				<p><?php echo esc_html( $hero['copy'] ); ?></p>
				<div class="directory-hero-actions"><a class="header-cta" href="#directory-grid"><i data-lucide="layout-grid"></i><?php esc_html_e( 'גלו יעדים', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><?php esc_html_e( 'פתחו את מפת המסע', 'tra-vel-v2' ); ?></a></div>
				<div class="directory-proof"><span><strong><?php echo esc_html( count( $destinations ) ); ?></strong><?php esc_html_e( 'יעדים לבחירה', 'tra-vel-v2' ); ?></span><span><strong><?php echo esc_html( $region_count ); ?></strong><?php esc_html_e( 'אזורי עולם', 'tra-vel-v2' ); ?></span><span><strong>4</strong><?php esc_html_e( 'שכבות השוואה', 'tra-vel-v2' ); ?></span></div>
			</div>
			<div class="directory-orbit" aria-label="<?php esc_attr_e( 'מפת יעדים אינטראקטיבית', 'tra-vel-v2' ); ?>">
				<div class="directory-orbit-globe"></div><span class="directory-origin">TLV<small><?php esc_html_e( 'מתחילים כאן', 'tra-vel-v2' ); ?></small></span>
				<?php
				$positions = array(
					'budapest' => array( 38, 30 ), 'prague' => array( 29, 22 ), 'vienna' => array( 46, 38 ),
					'thailand' => array( 72, 64 ), 'athens' => array( 54, 48 ), 'tokyo' => array( 84, 34 ),
				);
				foreach ( $destinations as $destination ) :
					$position = $positions[ $destination['id'] ] ?? array( 50, 50 );
					$map_url  = add_query_arg( 'destination', $destination['map_state'], home_url( '/travel-map/' ) );
					?>
					<a class="directory-map-pin" href="<?php echo esc_url( $map_url ); ?>" style="--pin-x:<?php echo esc_attr( $position[0] ); ?>%;--pin-y:<?php echo esc_attr( $position[1] ); ?>%" aria-label="<?php echo esc_attr( sprintf( __( 'פתחו את %s על המפה', 'tra-vel-v2' ), $destination['city'] ) ); ?>"><strong><?php echo esc_html( $destination['city'] ); ?></strong><small><?php echo esc_html( $destination['experience_label'] ); ?></small></a>
				<?php endforeach; ?>
				<div class="directory-orbit-note"><i data-lucide="mouse-pointer-click"></i><span><strong><?php esc_html_e( 'בחרו נקודה', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'והמשיכו להשוואת מסלול, זמן ועלות מלאה', 'tra-vel-v2' ); ?></span></div>
			</div>
		</div>
	</section>

	<section class="directory-command page-width" id="directory-grid">
		<div class="section-heading"><div><span class="eyebrow"><?php echo esc_html( $is_guides ? __( 'ספריית החלטות', 'tra-vel-v2' ) : __( 'ספריית יעדים', 'tra-vel-v2' ) ); ?></span><h2><?php echo esc_html( $is_guides ? __( 'בחרו את המדריך לפי סוג ההחלטה', 'tra-vel-v2' ) : __( 'איזו חופשה מתאימה עכשיו?', 'tra-vel-v2' ) ); ?></h2><p><?php esc_html_e( 'סננו לפי אזור או חוויה. מחירים נבדקים רק במסכי החיפוש החיים.', 'tra-vel-v2' ); ?></p></div><strong data-directory-count><?php echo esc_html( count( $destinations ) ); ?></strong></div>
		<form class="directory-filter" data-directory-filter role="search"><label><i data-lucide="search"></i><span class="screen-reader-text"><?php esc_html_e( 'חיפוש יעד', 'tra-vel-v2' ); ?></span><input data-directory-query type="search" placeholder="<?php esc_attr_e( 'חפשו עיר, מדינה או סגנון חופשה', 'tra-vel-v2' ); ?>"></label><div data-directory-filters><button class="is-active" type="button" data-directory-value="all"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="europe"><?php esc_html_e( 'אירופה', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="asia"><?php esc_html_e( 'אסיה', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="city"><?php esc_html_e( 'עיר', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="beach"><?php esc_html_e( 'חוף', 'tra-vel-v2' ); ?></button><button type="button" data-directory-value="culture"><?php esc_html_e( 'תרבות', 'tra-vel-v2' ); ?></button></div></form>

		<div class="directory-grid" data-directory-grid>
			<?php foreach ( $destinations as $destination ) :
				$status = $destination['guide_status'] ?? 'research';
				$map_url = add_query_arg( 'destination', $destination['map_state'], home_url( '/travel-map/' ) );
				$search_text = implode( ' ', array( $destination['city'], $destination['country'], $destination['region_label'], $destination['experience_label'], $destination['best_for'] ) );
				?>
				<article class="directory-card" data-directory-card data-region="<?php echo esc_attr( $destination['region'] ); ?>" data-experience="<?php echo esc_attr( $destination['experience'] ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>">
					<div class="directory-card-image" style="background-image:linear-gradient(0deg,rgba(4,20,27,.82),transparent 68%),url('<?php echo esc_url( tra_vel_v2_asset_uri( 'images/' . $destination['image'] ) ); ?>')"><span><?php echo esc_html( $destination['region_label'] ); ?></span><div><small><?php echo esc_html( $destination['country'] ); ?></small><h3><?php echo esc_html( $destination['city'] ); ?></h3></div></div>
					<div class="directory-card-body"><div class="directory-status"><i data-lucide="compass"></i><?php echo esc_html( $destination['experience_label'] ); ?></div><p><?php echo esc_html( $destination['decision'] ); ?></p><div class="directory-meta"><span><i data-lucide="calendar-range"></i><?php echo esc_html( $destination['duration'] ); ?></span><span><i data-lucide="users"></i><?php echo esc_html( $destination['best_for'] ); ?></span></div>
					<div class="directory-card-actions"><a href="<?php echo esc_url( $map_url ); ?>"><i data-lucide="map"></i><?php esc_html_e( 'פתחו במפה', 'tra-vel-v2' ); ?></a><?php if ( 'published' === $status && ! empty( $destination['guide_path'] ) ) : ?><a class="is-primary" href="<?php echo esc_url( home_url( $destination['guide_path'] ) ); ?>"><?php esc_html_e( 'למדריך המלא', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a><?php else : ?><a class="is-primary" href="<?php echo esc_url( $map_url . '#map-support' ); ?>"><?php esc_html_e( 'השוו מסלולים', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a><?php endif; ?></div></div>
				</article>
			<?php endforeach; ?>
		</div>
		<div class="directory-empty" data-directory-empty hidden><i data-lucide="search-x"></i><h3><?php esc_html_e( 'לא מצאנו התאמה מדויקת', 'tra-vel-v2' ); ?></h3><p><?php esc_html_e( 'נסו אזור אחר או פתחו את המפה כדי לגלות יעד לפי זמן ותקציב.', 'tra-vel-v2' ); ?></p><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><?php esc_html_e( 'למפת המסע', 'tra-vel-v2' ); ?></a></div>
	</section>

	<section class="directory-standard"><div class="page-width directory-standard-grid"><div><span class="eyebrow"><?php esc_html_e( 'מה מקבלים בכל יעד', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'תמונה מלאה לפני שעוברים להזמנה.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'המפה, המדריך וכלי ההשוואה מחוברים למסע אחד. אפשר להבין את החלופות, לבדוק את התנאים ולהמשיך למוצר שמתאים להרכב.', 'tra-vel-v2' ); ?></p></div><div class="directory-standard-list"><span><i data-lucide="map"></i><strong>1</strong><?php esc_html_e( 'מפת מסע מחוברת', 'tra-vel-v2' ); ?></span><span><i data-lucide="layers-3"></i><strong>4</strong><?php esc_html_e( 'שכבות החלטה', 'tra-vel-v2' ); ?></span><span><i data-lucide="badge-check"></i><strong>LIVE</strong><?php esc_html_e( 'מחיר רק לאחר בדיקה', 'tra-vel-v2' ); ?></span></div></div></section>

	<section class="directory-next page-width"><div><span class="eyebrow"><?php esc_html_e( 'מה עושים עכשיו?', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'הפכו השראה למסלול שאפשר להשוות.', 'tra-vel-v2' ); ?></h2></div><div><a href="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>"><i data-lucide="earth"></i><span><strong><?php esc_html_e( 'מפת המסע', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'יעדים ומסלולים חזותיים', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><strong><?php esc_html_e( 'טיסות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'מחיר כולל ופשרות', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="bed-double"></i><span><strong><?php esc_html_e( 'מלונות', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'אזור, חדר ותנאים', 'tra-vel-v2' ); ?></span></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><span><strong><?php esc_html_e( 'ביטוח', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'כיסוי לפי המסלול', 'tra-vel-v2' ); ?></span></a></div></section>
</main>
<?php get_footer(); ?>
