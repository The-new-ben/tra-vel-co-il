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
		<aside id="travel-map-filters" class="filter-panel" aria-label="<?php esc_attr_e( 'מסנני מסע', 'tra-vel-v2' ); ?>">
			<div class="filter-panel-head">
				<div><span class="eyebrow"><?php esc_html_e( 'התאמה אישית', 'tra-vel-v2' ); ?></span><h2><?php esc_html_e( 'מה חשוב בחופשה?', 'tra-vel-v2' ); ?></h2></div>
				<button class="round-button" data-filter-close type="button" aria-label="<?php esc_attr_e( 'סגירת מסננים', 'tra-vel-v2' ); ?>"><i data-lucide="x"></i></button>
			</div>
			<p><?php esc_html_e( 'הבחירות משנות את היעדים ואת סדר המסלולים לפי מה שחשוב לכם.', 'tra-vel-v2' ); ?></p>

			<div class="filter-block">
				<div class="budget-row"><strong><?php esc_html_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?></strong><span class="budget-value" data-budget-value>$950</span></div>
				<input data-budget type="range" min="200" max="1600" step="25" value="950" aria-label="<?php esc_attr_e( 'תקציב מועדף לאדם', 'tra-vel-v2' ); ?>">
				<div class="filter-scale"><span>$200</span><span>$1,600</span></div>
				<p class="budget-coverage-note" data-budget-coverage data-coverage="loading" role="status" aria-live="polite"><?php esc_html_e( 'בודקים על אילו יעדים אפשר להחיל את התקציב לפי מחירי ספק נוכחיים.', 'tra-vel-v2' ); ?></p>
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
				<button type="submit"><?php esc_html_e( 'חפשו', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></button>
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
						<svg class="globe-route-layer" data-globe-routes width="100%" height="100%" aria-hidden="true"><path data-globe-route></path></svg>
						<span class="globe-selection-point" data-globe-selection-point aria-hidden="true" hidden></span>
						<span class="origin-point" data-globe-origin aria-label="<?php esc_attr_e( 'תל אביב, נקודת מוצא', 'tra-vel-v2' ); ?>"></span>
						<button class="price-pin pin-budapest" data-destination="budapest" data-latitude="47.4979" data-longitude="19.0402" aria-pressed="false" type="button"><?php esc_html_e( 'בודפשט', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-athens" data-destination="athens" data-latitude="37.9838" data-longitude="23.7275" aria-pressed="false" type="button"><?php esc_html_e( 'אתונה', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-dubai" data-destination="dubai" data-latitude="25.2048" data-longitude="55.2708" aria-pressed="false" type="button"><?php esc_html_e( 'דובאי', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-bangkok is-active" data-destination="bangkok" data-latitude="13.7563" data-longitude="100.5018" aria-pressed="true" type="button"><?php esc_html_e( 'בנגקוק', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-tokyo" data-destination="tokyo" data-latitude="35.6762" data-longitude="139.6503" aria-pressed="false" type="button"><?php esc_html_e( 'טוקיו', 'tra-vel-v2' ); ?></button>
						<button class="price-pin pin-lisbon" data-destination="lisbon" data-latitude="38.7223" data-longitude="-9.1393" aria-pressed="false" type="button"><?php esc_html_e( 'ליסבון', 'tra-vel-v2' ); ?></button>
						<span class="sr-only" data-globe-live role="status" aria-live="polite" aria-atomic="true"></span>
					</div>
				</div>
			</div>

			<div class="map-status-row">
				<div class="map-canvas-guidance"><i data-lucide="move-3d"></i><span><strong><?php esc_html_e( 'גררו כדי לסובב את העולם', 'tra-vel-v2' ); ?></strong><?php esc_html_e( 'בחרו נקודה או שכבה. כל הפרטים נפתחים מתחת למפה.', 'tra-vel-v2' ); ?></span></div>
				<p class="map-data-status" data-layer-status role="status"><?php esc_html_e( 'מעדכן יעדים ומסלולים', 'tra-vel-v2' ); ?></p>
				<a class="map-imagery-attribution" href="https://visibleearth.nasa.gov/images/74218/december-blue-marble-next-generation/74226l" target="_blank" rel="noopener noreferrer">NASA Blue Marble</a>
				<a class="map-weather-attribution" data-weather-attribution href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" hidden>Weather data by Open-Meteo · CC BY 4.0</a>
			</div>
			<div class="map-selection-rail" data-globe-selection data-state="supported" tabindex="-1">
				<span class="map-selection-signal" aria-hidden="true"><i data-lucide="map-pin-check"></i></span>
				<span class="map-selection-copy"><small data-globe-selection-kicker><?php esc_html_e( 'הנקודה התקבלה', 'tra-vel-v2' ); ?></small><strong data-globe-selection-title><?php esc_html_e( 'בנגקוק נבחרה. תוכנית 360° נפתחה.', 'tra-vel-v2' ); ?></strong><span data-globe-selection-detail><?php esc_html_e( '12 תחומי החלטה מסודרים מתחת למפה. מחירים והזמנה יאומתו בחיפוש חי.', 'tra-vel-v2' ); ?></span></span>
				<a data-globe-selection-action href="#destination-plan-title"><?php esc_html_e( 'צפו בתוכנית', 'tra-vel-v2' ); ?><i data-lucide="arrow-down"></i></a>
				<ol class="map-progress-checkpoints" data-map-progress-checkpoints aria-label="<?php esc_attr_e( 'התקדמות בניית התוכנית', 'tra-vel-v2' ); ?>">
					<li data-map-checkpoint="point" data-state="confirmed"><i data-lucide="map-pin-check" aria-hidden="true"></i><span><b><?php esc_html_e( 'נקודה התקבלה', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'נבחרה על הגלובוס', 'tra-vel-v2' ); ?></small></span></li>
					<li data-map-checkpoint="destination" data-state="confirmed"><i data-lucide="locate-fixed" aria-hidden="true"></i><span><b><?php esc_html_e( 'יעד זוהה', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'בנגקוק', 'tra-vel-v2' ); ?></small></span></li>
					<li data-map-checkpoint="scopes" data-state="confirmed"><i data-lucide="list-checks" aria-hidden="true"></i><span><b><?php esc_html_e( '12 תחומים נפתחו', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'מוכנים לבדיקה', 'tra-vel-v2' ); ?></small></span></li>
					<li data-map-checkpoint="live" data-state="waiting"><i data-lucide="radio-tower" aria-hidden="true"></i><span><b><?php esc_html_e( 'מחיר וזמינות', 'tra-vel-v2' ); ?></b><small data-map-checkpoint-detail><?php esc_html_e( 'ממתינים לחיפוש חי', 'tra-vel-v2' ); ?></small></span></li>
				</ol>
				<span class="sr-only" data-map-progress-live role="status" aria-live="polite" aria-atomic="true"></span>
			</div>
		</section>
	</div>

	<section class="map-support-section" id="map-support">
		<div class="page-width map-support-grid">
			<article class="map-destination-panel" data-map-result>
				<img class="map-result-image" data-result-image src="<?php echo esc_url( tra_vel_v2_asset_uri( 'images/thailand.jpg' ) ); ?>" alt="<?php esc_attr_e( 'בנגקוק, תאילנד', 'tra-vel-v2' ); ?>">
				<div class="map-destination-copy">
					<div class="result-top"><div><small><?php esc_html_e( 'היעד שבחרתם', 'tra-vel-v2' ); ?></small><h2 data-result-city><?php esc_html_e( 'בנגקוק, תאילנד', 'tra-vel-v2' ); ?></h2></div><button class="save-button" type="button" aria-label="<?php esc_attr_e( 'שמירת היעד', 'tra-vel-v2' ); ?>"><i data-lucide="heart"></i></button></div>
					<div class="result-tags" data-result-tags><span><?php esc_html_e( 'טיול ארוך', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'עיר ואיים', 'tra-vel-v2' ); ?></span><span><?php esc_html_e( 'מסלול גמיש', 'tra-vel-v2' ); ?></span></div>
					<div class="result-facts"><span data-result-airport>BKK</span><span data-result-hotel><?php esc_html_e( 'אזורי לינה', 'tra-vel-v2' ); ?></span><span data-result-weather><?php esc_html_e( 'מזג אוויר', 'tra-vel-v2' ); ?></span></div>
					<div class="map-price-state"><div><small data-result-state-label><?php esc_html_e( 'מחיר וזמינות', 'tra-vel-v2' ); ?></small><strong data-result-total><?php esc_html_e( 'בדיקה חיה', 'tra-vel-v2' ); ?></strong></div><p><span data-result-price><?php esc_html_e( 'בדיקה חיה', 'tra-vel-v2' ); ?></span> <span data-result-note><?php esc_html_e( 'המחיר יופיע לאחר חיפוש מול ספקים מחוברים.', 'tra-vel-v2' ); ?></span></p></div>
					<div class="map-destination-actions"><a data-result-guide href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><i data-lucide="book-open"></i><?php esc_html_e( 'מדריך ליעד', 'tra-vel-v2' ); ?></a><a data-result-hotels href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="bed-double"></i><?php esc_html_e( 'אזורי לינה', 'tra-vel-v2' ); ?></a><a data-result-insurance href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-check"></i><?php esc_html_e( 'ביטוח מתאים', 'tra-vel-v2' ); ?></a></div>
					<section class="destination-plan-360" data-destination-plan aria-labelledby="destination-plan-title">
						<header class="destination-plan-head"><div><span class="eyebrow">Tra-Vel 360°</span><h3 id="destination-plan-title" data-plan-title><?php esc_html_e( 'התוכנית החכמה לבנגקוק', 'tra-vel-v2' ); ?></h3></div><span class="destination-plan-state" data-plan-state><?php esc_html_e( 'היעד נבחר · שאר השלבים מוכנים לבדיקה', 'tra-vel-v2' ); ?></span></header>
						<p data-plan-summary><?php esc_html_e( 'בחרו את סגנון הנסיעה ונארגן את ההחלטות לפי מה שחשוב לכם.', 'tra-vel-v2' ); ?></p>
						<div class="destination-plan-intents" role="group" aria-label="<?php esc_attr_e( 'סגנון התוכנית', 'tra-vel-v2' ); ?>">
							<button class="is-active" data-plan-intent="smart" type="button" aria-pressed="true"><?php esc_html_e( 'הכי חכם', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="value" type="button" aria-pressed="false"><?php esc_html_e( 'ערך לכסף', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="easy" type="button" aria-pressed="false"><?php esc_html_e( 'הכי קל', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="romantic" type="button" aria-pressed="false"><?php esc_html_e( 'זוגי', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="family" type="button" aria-pressed="false"><?php esc_html_e( 'משפחה', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="adventure" type="button" aria-pressed="false"><?php esc_html_e( 'הרפתקה', 'tra-vel-v2' ); ?></button>
							<button data-plan-intent="surprise" type="button" aria-pressed="false"><?php esc_html_e( 'תפתיעו אותי', 'tra-vel-v2' ); ?></button>
						</div>
						<div class="destination-plan-progress" role="list" aria-label="<?php esc_attr_e( 'התקדמות התוכנית', 'tra-vel-v2' ); ?>">
							<span class="is-complete" role="listitem" data-plan-stage="destination" style="--plan-index:0"><i data-lucide="check"></i><b><?php esc_html_e( 'יעד', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'נבחר', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="route" style="--plan-index:1"><i data-lucide="route"></i><b><?php esc_html_e( 'דרך', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'מוכן להשוואה', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="stay" style="--plan-index:2"><i data-lucide="bed-double"></i><b><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'מוכן לבדיקה', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="experience" style="--plan-index:3"><i data-lucide="sparkles"></i><b><?php esc_html_e( 'חוויה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'מוכן לתכנון', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready" role="listitem" data-plan-stage="cover" style="--plan-index:4"><i data-lucide="shield-check"></i><b><?php esc_html_e( 'הגנה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'מוכן להשוואה', 'tra-vel-v2' ); ?></small></span>
							<span class="is-ready is-current" role="listitem" aria-current="step" data-plan-stage="total" style="--plan-index:5"><i data-lucide="calculator"></i><b><?php esc_html_e( 'עלות מלאה', 'tra-vel-v2' ); ?></b><small><?php esc_html_e( 'עדיין לא נבדקה', 'tra-vel-v2' ); ?></small></span>
						</div>
						<div class="destination-plan-options">
							<a data-plan-flight data-plan-layer="airports" href="<?php echo esc_url( home_url( '/flights/' ) ); ?>"><i data-lucide="plane-takeoff"></i><span><small><?php esc_html_e( 'טיסה ודרך', 'tra-vel-v2' ); ?></small><strong data-plan-flight-title><?php esc_html_e( 'ישיר מול קונקשן חכם', 'tra-vel-v2' ); ?></strong><em data-plan-flight-detail><?php esc_html_e( 'זמן, כבודה ותנאי כרטיס', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-stay data-plan-layer="hotels" href="<?php echo esc_url( home_url( '/hotels/' ) ); ?>"><i data-lucide="hotel"></i><span><small><?php esc_html_e( 'לינה', 'tra-vel-v2' ); ?></small><strong data-plan-stay-title><?php esc_html_e( 'בחרו אזור לפני מלון', 'tra-vel-v2' ); ?></strong><em data-plan-stay-detail><?php esc_html_e( 'קצב, תחבורה ועלות מלאה', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-experience href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><i data-lucide="map"></i><span><small><?php esc_html_e( 'מה עושים', 'tra-vel-v2' ); ?></small><strong data-plan-experience-title><?php esc_html_e( 'מסלול לפי הכוונה שלכם', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'אוכל, תרבות, טבע וזמן חופשי', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-weather data-plan-layer="weather" href="<?php echo esc_url( home_url( '/travel-map/?layer=weather' ) ); ?>"><i data-lucide="cloud-sun"></i><span><small><?php esc_html_e( 'עונה ומזג אוויר', 'tra-vel-v2' ); ?></small><strong data-plan-weather-title><?php esc_html_e( 'בדיקה לפי תאריך', 'tra-vel-v2' ); ?></strong><em data-plan-weather-detail><?php esc_html_e( 'לא מניחים מזג אוויר בלי מועד', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-cover href="<?php echo esc_url( home_url( '/travel-insurance/' ) ); ?>"><i data-lucide="shield-plus"></i><span><small><?php esc_html_e( 'ביטוח וציוד', 'tra-vel-v2' ); ?></small><strong data-plan-cover-title><?php esc_html_e( 'כיסוי לפי המסלול', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'רפואה, כבודה, ספורט וביטול', 'tra-vel-v2' ); ?></em></span></a>
							<a data-plan-total data-plan-layer="deals" href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><i data-lucide="badge-dollar-sign"></i><span><small><?php esc_html_e( 'עלות הנסיעה', 'tra-vel-v2' ); ?></small><strong data-plan-total-title><?php esc_html_e( 'עדיין לא חושבה בחבילה מלאה', 'tra-vel-v2' ); ?></strong><em><?php esc_html_e( 'עברו למרכיב החבילה כדי לבדוק את כל הרכיבים', 'tra-vel-v2' ); ?></em></span></a>
						</div>
						<section class="destination-decision-cockpit" aria-labelledby="destination-decision-title">
							<header class="destination-decision-head">
								<div><span class="eyebrow"><?php esc_html_e( 'מרכז ההחלטות', 'tra-vel-v2' ); ?></span><h4 id="destination-decision-title"><?php esc_html_e( 'כל מה שצריך לסגור סביב הנסיעה', 'tra-vel-v2' ); ?></h4><p data-plan-coverage-copy><?php esc_html_e( '12 תחומי החלטה מופו. זהו כיסוי תכנוני, לא אישור הזמנה.', 'tra-vel-v2' ); ?></p></div>
								<div class="destination-plan-meter" data-plan-meter role="progressbar" aria-label="<?php esc_attr_e( 'תחומי החלטה שאומתו במידע עדכני', 'tra-vel-v2' ); ?>" aria-valuemin="0" aria-valuemax="12" aria-valuenow="0" aria-valuetext="<?php esc_attr_e( '12 תחומים מופו; 0 אומתו במידע עדכני; אין הזמנה מאושרת', 'tra-vel-v2' ); ?>"><strong data-plan-meter-count>0/12</strong><span><i data-plan-meter-fill style="--plan-coverage:0%"></i></span><small data-plan-meter-label><?php esc_html_e( 'תחומים אומתו', 'tra-vel-v2' ); ?></small></div>
							</header>
							<div class="destination-decision-grid" data-plan-modules>
								<details data-plan-module="mobility" open><summary><i data-lucide="car-taxi-front"></i><span><strong data-plan-module-title><?php esc_html_e( 'הגעה ותחבורה מקומית', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'בסיס תכנוני מוכן', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'זמן ההעברה מהשדה מוכר. סוג ההסעה, המחיר והמסלול ייבדקו לפי המלון והשעה.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/packages/' ) ); ?>"><?php esc_html_e( 'השוו העברות', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="dining"><summary><i data-lucide="utensils"></i><span><strong data-plan-module-title><?php esc_html_e( 'אוכל, כשרות והעדפות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין להעדפות', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'מוסיפים רמת כשרות, אלרגיות, ילדים וסגנון אוכל לפני שמרכיבים מסלול יומי.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'הוסיפו העדפות לסוכן', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="entry"><summary><i data-lucide="passport"></i><span><strong data-plan-module-title><?php esc_html_e( 'כניסה, דרכון ואשרות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'נדרשים פרטי נוסע', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'בודקים אזרחות, תוקף דרכון, מסלול ותאריך מול מקור רשמי לפני רכישה.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'פתחו בדיקת כניסה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="connectivity"><summary><i data-lucide="wifi"></i><span><strong data-plan-module-title><?php esc_html_e( 'תקשורת ו-eSIM', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'אפשרויות מוכנות להשוואה', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'משווים eSIM, חבילת נדידה ו-SIM מקומי לפי ימים, נפח גלישה ושיתוף אינטרנט.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'השוו חיבור לנסיעה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="accessibility"><summary><i data-lucide="accessibility"></i><span><strong data-plan-module-title><?php esc_html_e( 'משפחה ונגישות', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין להרכב ולצרכים', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'גילים, עגלה, הליכה, מעלית, חדרים מחוברים וסיוע בשדה משנים את כל התוכנית.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'התאימו את התוכנית', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
								<details data-plan-module="equipment"><summary><i data-lucide="backpack"></i><span><strong data-plan-module-title><?php esc_html_e( 'ציוד, כבודה והשכרה', 'tra-vel-v2' ); ?></strong><small data-plan-module-state><?php esc_html_e( 'ממתין לפעילויות', 'tra-vel-v2' ); ?></small></span><i data-lucide="chevron-down"></i></summary><div><p data-plan-module-detail><?php esc_html_e( 'מחברים ציוד לפעילויות, לתנאי הכבודה ולאפשרות לשכור במקום במקום לסחוב.', 'tra-vel-v2' ); ?></p><a data-plan-module-action href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'בנו רשימת ציוד', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div></details>
							</div>
							<aside class="destination-cost-ledger" data-plan-ledger>
								<header><span><small><?php esc_html_e( 'עלות מקצה לקצה', 'tra-vel-v2' ); ?></small><strong data-plan-ledger-total><?php esc_html_e( 'נדרש חיפוש חי', 'tra-vel-v2' ); ?></strong></span><span class="destination-ledger-state" data-plan-ledger-state><?php esc_html_e( '12 רכיבי עלות במעקב', 'tra-vel-v2' ); ?></span></header>
								<div data-plan-ledger-list><span><b><?php esc_html_e( 'טיסה וכבודה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'ממתין לחיפוש', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'לינה ומסים', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'ממתין לתאריכים', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'העברות ותחבורה', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בתכנון', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'חוויות ואוכל', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'בתכנון', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'ביטוח ותקשורת', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'ממתין להתאמה', 'tra-vel-v2' ); ?></em></span><span><b><?php esc_html_e( 'ציוד ושונות', 'tra-vel-v2' ); ?></b><em><?php esc_html_e( 'ממתין למסלול', 'tra-vel-v2' ); ?></em></span></div>
								<p data-plan-ledger-truth><?php esc_html_e( 'לא מוצג חיסכון עד שיש מחיר בסיס בר-השוואה, זמינות ומועד בדיקה.', 'tra-vel-v2' ); ?></p>
							</aside>
						</section>
						<div class="destination-plan-actions"><a class="destination-plan-primary" data-plan-ai href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><i data-lucide="sparkles"></i><?php esc_html_e( 'תנו לסוכן לבנות את כל הנסיעה', 'tra-vel-v2' ); ?></a><button data-plan-save type="button"><i data-lucide="bookmark-plus"></i><span><?php esc_html_e( 'שמרו את התוכנית', 'tra-vel-v2' ); ?></span></button><a data-plan-guide href="<?php echo esc_url( home_url( '/destinations/' ) ); ?>"><?php esc_html_e( 'פתחו מדריך עומק', 'tra-vel-v2' ); ?><i data-lucide="arrow-left"></i></a></div>
						<p class="destination-plan-truth"><i data-lucide="shield-check"></i><span data-plan-truth><?php esc_html_e( 'היעד נבחר. מחירים, זמינות והזמנה עדיין לא נבדקו.', 'tra-vel-v2' ); ?></span></p>
					</section>
				</div>
			</article>

			<aside class="route-sheet" id="route-comparison">
				<div class="sheet-head"><div><span class="eyebrow"><?php esc_html_e( 'הדרך שמתאימה לכם', 'tra-vel-v2' ); ?></span><h2 data-route-title><?php esc_html_e( 'תל אביב לבנגקוק: השוואת מסלולים', 'tra-vel-v2' ); ?></h2></div><span><?php esc_html_e( 'מחיר, זמן, כבודה וגמישות', 'tra-vel-v2' ); ?></span></div>
				<div class="mini-routes" data-route-list aria-live="off"></div>
				<span class="sr-only" data-route-status role="status" aria-live="polite" aria-atomic="true"></span>
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
