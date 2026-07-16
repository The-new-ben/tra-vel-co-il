<?php
/**
 * Template Name: Tra-Vel Globe
 * Template Post Type: page
 *
 * @package TraVelV2
 */

get_header();

$search_value = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : __( 'שבועיים בתאילנד בנובמבר', 'tra-vel-v2' );
?>
<main id="main-content" class="theme-map-shell">
	<section class="map-product-intro">
		<div class="page-width map-product-intro-inner">
			<div>
				<span class="eyebrow">Tra-Vel Globe</span>
				<h1><?php esc_html_e( 'העולם פתוח. בוחרים נקודה ומתקדמים למסלול.', 'tra-vel-v2' ); ?></h1>
				<p><?php esc_html_e( 'מתחילים במבט רחב, מתקרבים ליעד ורק אז משווים טיסות, אזורי לינה, מזג אוויר וביטוח. המפה נשארת פנויה לתנועה בכל שלב.', 'tra-vel-v2' ); ?></p>
			</div>
			<a href="#map-support" class="map-intro-link"><i data-lucide="mouse-pointer-click"></i><?php esc_html_e( 'איך עוברים ממפה להזמנה', 'tra-vel-v2' ); ?></a>
		</div>
	</section>

	<div class="map-workspace page-width">
		<aside class="filter-panel" aria-label="<?php esc_attr_e( 'מסנני מסע', 'tra-vel-v2' ); ?>">
			<div class="filter-panel-head">
				<div><span class="eyebrow"><?php esc_html_e( 'התאמה אישית', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מה חשוב בחופשה?', 'tra-vel-v2' ); ?></h2></div>
				<button class="round-button" data-filter-close type="button" aria-label="<?php esc_attr_e( 'סגירת מסננים', 'tra-vel-v2' ); ?>"><i data-lucide="x"></i></button>
			</div>
			<p><?php esc_html_e( 'הבחירות משנות את היעדים ואת סדר המסלולים. הן אינן מסתירות את הגלובוס.', 'tra-vel-v2' ); ?></p>

			<div class="filter-block">
				<div class="budget-row"><strong><?php esc_html_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?></strong><span class="budget-value" data-budget-value>$950</span></div>
				<input data-budget type="range" min="200" max="1600" step="25" value="950" aria-label="<?php esc_attr_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?>">
				<div class="filter-scale"><span>$200</span><span>$1,600</span></div>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'מה מחפשים?', 'tra-vel-v2' ); ?></strong>
				<div class="filter-chips" data-filter-kind="trip">
					<button class="is-active" data-filter-value="all" type="button"><?php esc_html_e( 'הכול', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="flight" type="button"><?php esc_html_e( 'טיסה', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="package" type="button"><?php esc_html_e( 'טיסה ומלון', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="short" type="button"><?php esc_html_e( 'חופשה קצרה', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="long" type="button"><?php esc_html_e( 'טיול ארוך', 'tra-vel-v2' ); ?></button>
				</div>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'קצב הנסיעה', 'tra-vel-v2' ); ?></strong>
				<label class="check-row"><input type="checkbox" checked> <?php esc_html_e( 'עד עצירה אחת', 'tra-vel-v2' ); ?></label>
				<label class="check-row"><input type="checkbox" checked> <?php esc_html_e( 'עד 16 שעות בדרך', 'tra-vel-v2' ); ?></label>
				<label class="check-row"><input type="checkbox"> <?php esc_html_e( 'אפשר עצירת לילה בדרך', 'tra-vel-v2' ); ?></label>
				<div class="toggle-row"><span><?php esc_html_e( 'רק טיסות ישירות', 'tra-vel-v2' ); ?></span><button class="toggle" data-direct-filter type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'טיסות ישירות בלבד', 'tra-vel-v2' ); ?>"></button></div>
			</div>

			<div class="filter-block">
				<strong><?php esc_html_e( 'מה חשוב יותר?', 'tra-vel-v2' ); ?></strong>
				<div class="filter-chips" data-filter-kind="sort">
					<button class="is-active" data-filter-value="smart" type="button"><?php esc_html_e( 'איזון', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="price" type="button"><?php esc_html_e( 'מחיר', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="time" type="button"><?php esc_html_e( 'זמן', 'tra-vel-v2' ); ?></button>
					<button data-filter-value="smart" type="button"><?php esc_html_e( 'נוחות', 'tra-vel-v2' ); ?></button>
				</div>
			</div>

			<button class="filter-apply" data-discovery-apply type="button"><?php esc_html_e( 'עדכנו את המפה', 'tra-vel-v2' ); ?></button>
		</aside>

		<section class="map-main-column" aria-label="<?php esc_attr_e( 'מפת יעדים ומסלולים', 'tra-vel-v2' ); ?>">
			<form class="map-search-bar" action="<?php echo esc_url( home_url( '/travel-map/' ) ); ?>" method="get">
				<i data-lucide="search"></i>
				<label class="screen-reader-text" for="travel-map-query"><?php esc_html_e( 'חיפוש חופשה', 'tra-vel-v2' ); ?></label>
				<input id="travel-map-query" name="q" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'לדוגמה: סוף שבוע בבודפשט באוקטובר', 'tra-vel-v2' ); ?>">
				<button type="submit"><?php esc_html_e( 'חפשו', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></button>
			</form>

			<div class="map-mobile-action-row">
				<button data-filter-toggle type="button" aria-expanded="false"><i data-lucide="sliders-horizontal"></i><?php esc_html_e( 'מסננים', 'tra-vel-v2' ); ?></button>
				<a href="#route-comparison"><i data-lucide="route"></i><?php esc_html_e( 'השוואת מסלולים', 'tra-vel-v2' ); ?></a>
			</div>

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
					<div class="globe" aria-label="<?php esc_attr_e( 'בחרו יעד על פני הגלובוס', 'tra-vel-v2' ); ?>">
						<span class="origin-point" aria-label="<?php esc_attr_e( 'תל אביב, נקודת מוצא', 'tra-vel-v2' ); ?>"></span>
						<span class="route-curve curve-one" aria-hidden="true"></span><span class="route-curve curve-two" aria-hidden="true"></span>
						<button class="price-pin pin-budapest" data-destination="budapest" type="button"><?php esc_html_e( 'בודפשט', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-athens" data-destination="athens" type="button"><?php esc_html_e( 'אתונה', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-dubai" data-destination="dubai" type="button"><?php esc_html_e( 'דובאי', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-bangkok is-active" data-destination="bangkok" type="button"><?php esc_html_e( 'בנגקוק', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-tokyo" data-destination="tokyo" type="button"><?php esc_html_e( 'טוקיו', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-lisbon" data-destination="lisbon" type="button"><?php esc_html_e( 'ליסבון', 'tra-vel-v2' ); ?></button>
					</div>
				</div>
			</div>

			<div class="map-status-row">
				<div class="map-canvas-guidance"><i data-lucide="move-3d"></i><span><strong><?php esc_html_e( 'הגלובוס נשאר פנוי', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בחרו נקודה או שכבה. כל הפרטים נפתחים מתחת למפה.', 'tra-vel-v2' ); ?></span></div>
				<p class="map-data-status" data-layer-status role="status"><?php esc_html_e( 'מעדכן יעדים ומסלולים', 'tra-vel-v2' ); ?></p>
				<a class="map-weather-attribution" data-weather-attribution href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" hidden>Weather data by Open-Meteo · CC BY 4.0</a>
			</div>
		</section>
	</div>

	<section class="map-support-section" id="map-support">
		<div class="page-width map-support-grid">
			<article class="map-destination-panel" data-map-result aria-live="polite">
				<img class="map-result-image" data-result-image src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/thailand.jpg' ) ); ?>" alt="<?php esc_attr_e( 'בנגקוק, תאילנד', 'tra-vel-v2' ); ?>">
				<div class="map-destination-copy">
					<div class="result-top"><div><small><?php esc_html_e( 'היעד שבחרתם', 'tra-vel-v2' ); ?></small><h2 data-result-city><?php esc_html_e( 'בנגקוק, תאילנד', 'tra-vel-v2' ); ?></h2></div><button class="save-button" type="button" aria-label="<?php esc_attr_e( 'שמירת היעד', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></button></div>
					<div class="result-tags" data-result-tags><span><?php esc_html_e( 'טיול ארוך', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'עיר ואיים', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'מסלול גמיש', 'tra-vel-v2' ); ?></span></div>
					<div class="result-facts"><span data-result-airport>BKK</span><span data-result-hotel><?php esc_html_e( 'אזורי לינה', 'tra-vel-v2' ); ?></span><span data-result-weather><?php esc_html_e( 'מזג אוויר', 'tra-vel-v2' ); ?></span></div>
					<div class="map-price-state"><div><small><?php esc_html_e( 'מחיר וזמינות', 'tra-vel-v2' ); ?></small><strong data-result-total><?php esc_html_e( 'בדיקה חיה', 'tra-vel-v2' ); ?></strong></div><p><span data-result-price><?php esc_html_e( 'בדיקה חיה', 'tra-vel-v2' ); ?></span> <span data-result-note><?php esc_html_e( 'המחיר יופיע לאחר חיפוש מול ספקים מחוברים.', 'tra-vel-v2' ); ?></span></p></div>
					<div class="map-destination-actions"><a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="book-open"></i><?php esc_html_e( 'מדריך ליעד', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="bed-double"></i><?php esc_html_e( 'אזורי לינה', 'tra-vel-v2' ); ?></a><a href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><?php esc_html_e( 'ביטוח מתאים', 'tra-vel-v2' ); ?></a></div>
				</div>
			</article>

			<aside class="route-sheet" id="route-comparison">
				<div class="sheet-head"><div><span class="eyebrow"><?php esc_html_e( 'הדרך שמתאימה לכם', 'tra-vel-v2' ); ?></span><h2 data-route-title><?php esc_html_e( 'תל אביב לבנגקוק: השוואת מסלולים', 'tra-vel-v2' ); ?></h2></div><span><?php esc_html_e( 'מחיר, זמן, כבודה וגמישות', 'tra-vel-v2' ); ?></span></div>
				<div class="mini-routes" data-route-list aria-live="polite"></div>
			</aside>
		</div>
	</section>

	<section class="map-depth-section page-width" aria-labelledby="map-depth-title">
		<div class="section-heading"><div><span class="eyebrow"><?php esc_html_e( 'מהגלובוס לפרטים', 'tra-vel-v2' ); ?></span><h2 id="map-depth-title"><?php esc_html_e( 'מתקרבים רק כשצריך. המידע נשאר מסודר.', 'tra-vel-v2' ); ?></h2><p><?php esc_html_e( 'בחירת יעד פותחת שכבת מידע מדויקת יותר: שדות תעופה, שכונות, מלונות, אטרקציות, תחבורה ומסלולים. כל שכבה מקשרת לעמוד המתאים באתר.', 'tra-vel-v2' ); ?></p></div></div>
		<div class="map-depth-grid">
			<a href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="map"></i><span><strong><?php esc_html_e( 'מפת היעד', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'שכונות, מוקדי עניין וזמני נסיעה', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
			<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><i data-lucide="book-open-text"></i><span><strong><?php esc_html_e( 'מדריך עומק', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'עונות, מסלול, עלויות והחלטות', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
			<a href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><strong><?php esc_html_e( 'השוואה והזמנה', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'טיסות, מלונות, חבילות וביטוח', 'tra-vel-v2' ); ?></span><i data-lucide="arrow-left"></i></a>
		</div>
	</section>
</main>
<?php get_footer(); ?>
