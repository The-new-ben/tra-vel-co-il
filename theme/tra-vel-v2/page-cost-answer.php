<?php
/**
 * Template Name: Tra-Vel Cost Answer
 * Template Post Type: page
 *
 * The page that answers "כמה עולה טיסה ל..." with a real number.
 *
 * Everything on this page is server-rendered from inc/cost-answers.php, which
 * resolves the destination from this page's own slug and reads the live fare
 * from the price engine. There is no script, no listener and no client-side
 * fill anywhere in this file: what a visitor receives is the finished answer.
 *
 * When the contract in Part B of the release is not satisfied the page still
 * renders, honestly and usefully, and inc/cost-answers.php has already
 * noindexed it. It never renders a supplier failure and never renders an
 * invented number.
 *
 * @package TraVelV2
 */

$cost_post_id  = (int) get_queried_object_id();
$cost_contract = tra_vel_v2_cost_answer_contract( $cost_post_id );
$cost          = $cost_contract['view'];
$cost_ready    = ! empty( $cost_contract['ready'] );
$cost_map_url  = $cost['map_state']
	? add_query_arg( 'destination', $cost['map_state'], home_url( '/travel-map/' ) )
	: home_url( '/travel-map/' );
$cost_crumbs   = tra_vel_v2_cost_answer_breadcrumb_items( $cost, (string) get_permalink( $cost_post_id ) );

get_header();
?>
<main id="main-content" class="cost-answer-page" data-tra-vel-page="cost-answer" data-destination-map-state="<?php echo esc_attr( (string) $cost['map_state'] ); ?>">
	<header class="cost-answer-hero">
		<div class="page-width">
			<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'פירורי לחם', 'tra-vel-v2' ); ?>">
				<?php foreach ( $cost_crumbs as $cost_crumb_index => $cost_crumb ) : ?>
					<?php if ( ! empty( $cost_crumb['current'] ) ) : ?>
						<span aria-current="page"><?php echo esc_html( $cost_crumb['name'] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $cost_crumb['url'] ); ?>"><?php echo esc_html( $cost_crumb['name'] ); ?></a>
					<?php endif; ?>
					<?php if ( $cost_crumb_index < count( $cost_crumbs ) - 1 ) : ?><i data-lucide="chevron-left" aria-hidden="true"></i><?php endif; ?>
				<?php endforeach; ?>
			</nav>
			<span class="eyebrow"><i data-lucide="banknote" aria-hidden="true"></i><?php esc_html_e( 'עלות טיסה', 'tra-vel-v2' ); ?></span>
			<h1>
				<?php
				echo esc_html(
					$cost['prefixed_city']
						/* translators: %s: destination name. */
						? sprintf( __( 'כמה עולה טיסה ל%s', 'tra-vel-v2' ), $cost['prefixed_city'] )
						: __( 'כמה עולה טיסה לחוץ לארץ', 'tra-vel-v2' )
				);
				?>
			</h1>

			<?php if ( '' !== (string) $cost['answer'] ) : ?>
				<div class="cost-answer-card" data-cost-answer>
					<p class="cost-answer-sentence"><strong><?php echo esc_html( (string) $cost['answer'] ); ?></strong></p>
					<p class="cost-answer-freshness"><i data-lucide="clock" aria-hidden="true"></i><?php echo esc_html( (string) $cost['freshness'] ); ?></p>
					<p class="cost-answer-scope"><?php echo esc_html( (string) $cost['scope_note'] ); ?></p>
					<p class="cost-answer-scope"><?php echo esc_html( (string) $cost['currency_note'] ); ?></p>
					<a class="cost-answer-cta" href="<?php echo esc_url( (string) $cost['booking_url'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php esc_html_e( 'בדקו את המחיר אצל ספק ההזמנה', 'tra-vel-v2' ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
				</div>
			<?php else : ?>
				<div class="cost-answer-card cost-answer-card-pending">
					<p class="cost-answer-sentence"><strong><?php esc_html_e( 'עדיין אין לנו מחיר שאפשר לעמוד מאחוריו לעמוד הזה, ולא נמציא אחד.', 'tra-vel-v2' ); ?></strong></p>
					<p class="cost-answer-scope"><?php esc_html_e( 'אפשר לבדוק את היעד על מפת החופשות או לתאר לנו את הנסיעה, ונחזור עם אפשרויות אמיתיות.', 'tra-vel-v2' ); ?></p>
					<div class="cost-answer-pending-actions">
						<a class="cost-answer-cta" href="<?php echo esc_url( $cost_map_url ); ?>"><?php esc_html_e( 'פתחו את מפת החופשות', 'tra-vel-v2' ); ?><i data-lucide="earth" aria-hidden="true"></i></a>
						<a class="button-link" href="<?php echo esc_url( home_url( '/ai-planner/' ) ); ?>"><?php esc_html_e( 'תארו לנו את החופשה', 'tra-vel-v2' ); ?><i data-lucide="arrow-left" aria-hidden="true"></i></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $cost['options'] ) : ?>
		<section class="section page-width cost-answer-section" id="cost-answer-options" aria-labelledby="cost-answer-options-title">
			<header class="section-heading">
				<div>
					<span class="eyebrow"><i data-lucide="scan-search" aria-hidden="true"></i><?php esc_html_e( 'האפשרויות שנמצאו', 'tra-vel-v2' ); ?></span>
					<h2 id="cost-answer-options-title"><?php esc_html_e( 'השוואת האפשרויות שנמצאו בחיפושים האחרונים', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'כל שורה כאן היא רשומת מחיר אמיתית מהחיפושים האחרונים, ולא הרכבה שלנו. שורה שאין מאחוריה רשומה פשוט אינה מופיעה.', 'tra-vel-v2' ); ?></p>
				</div>
			</header>
			<div class="guide-table-wrap">
				<table class="guide-decision-table cost-answer-table">
					<caption><?php
					echo esc_html(
						sprintf(
							/* translators: %s: destination name. */
							__( 'מחירי טיסה הלוך ושוב מתל אביב ל%s שנמצאו לאחרונה', 'tra-vel-v2' ),
							(string) $cost['prefixed_city']
						)
					);
					?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'אפשרות', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'מחיר לנוסע', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'עצירות', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'חברת תעופה', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'תאריכים', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'מעבר לספק', 'tra-vel-v2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cost['options'] as $cost_option ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $cost_option['label'] ); ?></th>
								<td><bdi dir="ltr"><?php echo esc_html( (string) $cost_option['price_label'] ); ?></bdi></td>
								<td><?php echo esc_html( (string) $cost_option['stops_label'] ); ?></td>
								<td><bdi dir="ltr"><?php echo esc_html( (string) $cost_option['airline'] ); ?></bdi></td>
								<td><?php echo esc_html( (string) $cost_option['dates_label'] ); ?></td>
								<td><a href="<?php echo esc_url( (string) $cost_option['deep_link'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php esc_html_e( 'בדקו אצל הספק', 'tra-vel-v2' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="cost-answer-note"><?php echo esc_html( (string) $cost['scope_note'] ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( $cost['breakdown'] ) : ?>
		<section class="section page-width cost-answer-section" id="cost-answer-breakdown" aria-labelledby="cost-answer-breakdown-title">
			<header class="section-heading">
				<div>
					<span class="eyebrow"><i data-lucide="list-checks" aria-hidden="true"></i><?php esc_html_e( 'פירוק העלות', 'tra-vel-v2' ); ?></span>
					<h2 id="cost-answer-breakdown-title"><?php esc_html_e( 'ממה מורכבת העלות האמיתית של הנסיעה', 'tra-vel-v2' ); ?></h2>
					<p><?php esc_html_e( 'רכיב אחד בטבלה נמדד באמת. לכל השאר אין לנו מספר מאומת, ולכן כתוב בהם מה קובע את המחיר במקום סכום שלא בדקנו.', 'tra-vel-v2' ); ?></p>
				</div>
			</header>
			<div class="guide-table-wrap">
				<table class="guide-decision-table cost-answer-table">
					<caption><?php esc_html_e( 'רכיבי העלות: מה ידוע לנו ומה תלוי בבחירה שלכם', 'tra-vel-v2' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'רכיב', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'עלות', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'מה קובע את המחיר', 'tra-vel-v2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cost['breakdown'] as $cost_row ) : ?>
							<tr<?php echo empty( $cost_row['known'] ) ? ' class="cost-answer-row-unknown"' : ''; ?>>
								<th scope="row"><?php echo esc_html( (string) $cost_row['component'] ); ?></th>
								<td><?php if ( ! empty( $cost_row['known'] ) ) : ?><bdi dir="ltr"><?php echo esc_html( (string) $cost_row['amount'] ); ?></bdi><?php else : ?><?php echo esc_html( (string) $cost_row['amount'] ); ?><?php endif; ?></td>
								<td><?php echo esc_html( (string) $cost_row['note'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<section class="section page-width cost-answer-section" id="cost-answer-tips" aria-labelledby="cost-answer-tips-title">
		<header class="section-heading">
			<div>
				<span class="eyebrow"><i data-lucide="piggy-bank" aria-hidden="true"></i><?php esc_html_e( 'איך משלמים פחות', 'tra-vel-v2' ); ?></span>
				<h2 id="cost-answer-tips-title"><?php esc_html_e( 'שבע בדיקות שמורידות את העלות בפועל', 'tra-vel-v2' ); ?></h2>
				<p><?php esc_html_e( 'כל סעיף כאן הוא פעולה שאפשר לבצע, ולא הבטחה לחיסכון. אנחנו לא יודעים כמה תחסכו ולכן לא נכתוב מספר.', 'tra-vel-v2' ); ?></p>
			</div>
		</header>
		<ol class="cost-answer-tips">
			<?php foreach ( $cost['tips'] as $cost_tip ) : ?>
				<li>
					<h3><?php echo esc_html( (string) $cost_tip['title'] ); ?></h3>
					<p><?php echo esc_html( (string) $cost_tip['body'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<section class="section page-width cost-answer-section" id="cost-answer-when" aria-labelledby="cost-answer-when-title">
		<header class="section-heading">
			<div>
				<span class="eyebrow"><i data-lucide="calendar-range" aria-hidden="true"></i><?php esc_html_e( 'מתי לטוס', 'tra-vel-v2' ); ?></span>
				<h2 id="cost-answer-when-title"><?php esc_html_e( 'מתי כדאי לטוס לפי מה שנצפה בפועל', 'tra-vel-v2' ); ?></h2>
			</div>
		</header>
		<?php if ( is_array( $cost['history'] ) ) : ?>
			<p class="cost-answer-note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of recorded observations. */
						__( 'הטבלה מציגה את המחיר הנמוך ביותר שנצפה בפועל לכל חודש יציאה, מתוך %s תצפיות מחיר שרשמנו ליעד הזה. אלה תצפיות ולא תחזיות.', 'tra-vel-v2' ),
						number_format_i18n( (int) $cost['history']['observations'] )
					)
				);
				?>
			</p>
			<div class="guide-table-wrap">
				<table class="guide-decision-table cost-answer-table">
					<caption><?php esc_html_e( 'המחיר הנמוך ביותר שנצפה לכל חודש יציאה', 'tra-vel-v2' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'חודש היציאה', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'המחיר הנמוך שנצפה', 'tra-vel-v2' ); ?></th>
							<th scope="col"><?php esc_html_e( 'מספר תצפיות', 'tra-vel-v2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cost['history']['months'] as $cost_month ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $cost_month['label'] ); ?></th>
								<td><bdi dir="ltr"><?php echo esc_html( (string) $cost_month['min'] ); ?></bdi></td>
								<td><?php echo esc_html( number_format_i18n( (int) $cost_month['observations'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="cost-answer-note"><?php esc_html_e( 'אנחנו רושמים כל מחיר שאנחנו מוצאים ליעד הזה, אבל עדיין אין מספיק תצפיות כדי לומר איזה חודש זול יותר. עד שיהיו, לא נציג כאן גרף עונתי שאין מאחוריו מדידה.', 'tra-vel-v2' ); ?></p>
			<ul class="cost-answer-guidance">
				<li><?php esc_html_e( 'בדקו את אותה נסיעה בכמה תאריכי יציאה סמוכים לפני שאתם מחליטים.', 'tra-vel-v2' ); ?></li>
				<li><?php esc_html_e( 'בדקו גם שבוע לפני ואחרי חופשות בתי הספר, החגים בישראל והחגים המקומיים ביעד.', 'tra-vel-v2' ); ?></li>
				<li><?php esc_html_e( 'השוו יציאה וחזרה באמצע השבוע מול סוף השבוע באותו טווח תאריכים.', 'tra-vel-v2' ); ?></li>
				<li><?php esc_html_e( 'אם התאריכים גמישים, חזרו לבדוק את המסלול יותר מפעם אחת. מחיר שנמצא הוא צילום רגע.', 'tra-vel-v2' ); ?></li>
			</ul>
		<?php endif; ?>
	</section>

	<?php if ( '' !== (string) $cost['faq_markup'] ) : ?>
		<section class="section page-width cost-answer-section cost-answer-faq article-prose" aria-labelledby="<?php echo esc_attr( TRA_VEL_V2_COST_ANSWER_FAQ_ID ); ?>">
			<?php
			// The one and only copy of this FAQ. inc/cost-answers.php parses this
			// exact string back through the shared visible-FAQ extractor before
			// any FAQPage node can exist, so the schema cannot say anything the
			// visitor cannot read here. Every dynamic value inside was escaped
			// when the string was built.
			echo $cost['faq_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</section>
	<?php endif; ?>

	<?php if ( $cost['links'] ) : ?>
		<section class="section page-width cost-answer-section" aria-labelledby="cost-answer-links-title">
			<header class="seo-cluster-links-heading">
				<span class="eyebrow"><?php esc_html_e( 'להמשך התכנון', 'tra-vel-v2' ); ?></span>
				<h2 id="cost-answer-links-title"><?php esc_html_e( 'מכאן ממשיכים לתכנון המלא', 'tra-vel-v2' ); ?></h2>
			</header>
			<div class="seo-cluster-link-grid">
				<?php foreach ( $cost['links'] as $cost_link ) : ?>
					<a class="seo-cluster-link-card" href="<?php echo esc_url( (string) $cost_link['url'] ); ?>">
						<span class="seo-cluster-link-kind"><i data-lucide="<?php echo 'pillar' === $cost_link['kind'] ? 'plane' : 'map-pinned'; ?>" aria-hidden="true"></i><?php echo esc_html( 'pillar' === $cost_link['kind'] ? __( 'השוואת טיסות', 'tra-vel-v2' ) : __( 'מדריך היעד', 'tra-vel-v2' ) ); ?></span>
						<h3><?php echo esc_html( (string) $cost_link['title'] ); ?></h3>
						<strong><?php esc_html_e( 'פתחו ובדקו', 'tra-vel-v2' ); ?><i data-lucide="arrow-left" aria-hidden="true"></i></strong>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $cost_ready ) : ?>
		<section class="section page-width experience-next" aria-labelledby="cost-answer-next">
			<div>
				<span class="eyebrow"><?php esc_html_e( 'השלב הבא', 'tra-vel-v2' ); ?></span>
				<h2 id="cost-answer-next"><?php esc_html_e( 'בדקו את המחיר אצל ספק ההזמנה', 'tra-vel-v2' ); ?></h2>
				<p><?php echo esc_html( (string) $cost['scope_note'] ); ?></p>
			</div>
			<a class="button-link dark-button" href="<?php echo esc_url( (string) $cost['booking_url'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php esc_html_e( 'מעבר לספק ההזמנה', 'tra-vel-v2' ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
