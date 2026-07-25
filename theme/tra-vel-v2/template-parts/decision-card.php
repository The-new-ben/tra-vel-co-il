<?php
/**
 * The one screen decision card (theme 1.35.0).
 *
 * Everything here is final on arrival. The totals, the party size, the dates
 * and the outbound links are all computed on the server, so a visitor without
 * JavaScript receives the completed card and can book from it immediately.
 * assets/js/decision-card.js only adds two things on top: a short opening
 * sequence and a traveler stepper that multiplies the numbers already present.
 *
 * The only navigation this card contains is the outbound supplier link on each
 * option. Nothing else here leaves the page.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

$card = isset( $args ) && is_array( $args ) ? $args : array();
if ( empty( $card['tiers'] ) || ! is_array( $card['tiers'] ) ) {
	return;
}
?>
<section
	class="decision-card page-width"
	data-decision-card
	data-decision-card-travelers="<?php echo esc_attr( (string) $card['travelers'] ); ?>"
	data-decision-card-min="<?php echo esc_attr( (string) $card['min_travelers'] ); ?>"
	data-decision-card-max="<?php echo esc_attr( (string) $card['max_travelers'] ); ?>"
	data-decision-card-party-one="<?php echo esc_attr( $card['party_one'] ); ?>"
	data-decision-card-party-many="<?php echo esc_attr( $card['party_many'] ); ?>"
	aria-labelledby="decision-card-title"
>
	<div class="decision-card-head">
		<span class="eyebrow"><?php esc_html_e( 'טיסות בלבד', 'tra-vel-v2' ); ?></span>
		<h2 id="decision-card-title">
			<?php
			/* translators: %s: destination city. */
			echo esc_html( sprintf( __( 'מתל אביב ל%s, הלוך ושוב', 'tra-vel-v2' ), $card['prefixed_city'] ) );
			?>
		</h2>
		<p class="decision-card-lede"><?php esc_html_e( 'מחירים שנמצאו בחיפושים אחרונים. בוחרים וממשיכים לספק ההזמנה.', 'tra-vel-v2' ); ?></p>
	</div>

	<p class="decision-card-fill" data-decision-card-fill aria-hidden="true" hidden>
		<?php foreach ( $card['fill_lines'] as $index => $line ) : ?>
			<span class="decision-card-fill-line" data-decision-card-fill-line<?php echo 0 === (int) $index ? ' data-decision-card-fill-count="' . esc_attr( (string) $card['fill_count'] ) . '"' : ''; ?>><?php echo esc_html( $line ); ?></span>
		<?php endforeach; ?>
	</p>

	<div class="decision-card-travelers" role="group" aria-labelledby="decision-card-travelers-label">
		<span class="decision-card-travelers-label" id="decision-card-travelers-label"><?php esc_html_e( 'נוסעים', 'tra-vel-v2' ); ?></span>
		<button class="decision-card-step" type="button" data-decision-card-step="-1" aria-label="<?php esc_attr_e( 'נוסע אחד פחות', 'tra-vel-v2' ); ?>"><span aria-hidden="true">&minus;</span></button>
		<output class="decision-card-travelers-value" data-decision-card-travelers-value for="decision-card-travelers-label"><?php echo esc_html( number_format_i18n( $card['travelers'] ) ); ?></output>
		<button class="decision-card-step" type="button" data-decision-card-step="1" aria-label="<?php esc_attr_e( 'נוסע אחד יותר', 'tra-vel-v2' ); ?>"><span aria-hidden="true">+</span></button>
	</div>

	<ul class="decision-card-tiers" data-decision-card-tiers>
		<?php foreach ( $card['tiers'] as $tier ) : ?>
			<li class="decision-tier<?php echo $tier['is_stale'] ? ' is-stale' : ''; ?>" data-decision-tier="<?php echo esc_attr( $tier['tier'] ); ?>">
				<span class="decision-tier-label" data-decision-tier-label><?php echo esc_html( $tier['label'] ); ?></span>
				<p class="decision-tier-total">
					<strong><bdi dir="ltr" data-decision-tier-total data-decision-tier-unit="<?php echo esc_attr( (string) $tier['unit'] ); ?>" data-decision-tier-symbol="<?php echo esc_attr( $card['symbol'] ); ?>"><?php echo esc_html( $tier['total_label'] ); ?></bdi></strong>
					<span class="decision-tier-party" data-decision-tier-party><?php echo esc_html( $card['party_label'] ); ?></span>
				</p>
				<p class="decision-tier-person">
					<?php
					/* translators: %s: formatted per traveler price. */
					echo esc_html( sprintf( __( '%s לנוסע', 'tra-vel-v2' ), $tier['unit_label'] ) );
					?>
				</p>
				<ul class="decision-tier-facts">
					<li><i data-lucide="route" aria-hidden="true"></i><?php echo esc_html( $tier['stops_label'] ); ?></li>
					<?php if ( '' !== $tier['dates_label'] ) : ?>
						<li><i data-lucide="calendar-days" aria-hidden="true"></i><bdi dir="ltr"><?php echo esc_html( $tier['dates_label'] ); ?></bdi></li>
					<?php endif; ?>
					<?php if ( '' !== $tier['airline'] ) : ?>
						<li><i data-lucide="plane" aria-hidden="true"></i><span class="decision-tier-airline"><?php esc_html_e( 'חברת תעופה', 'tra-vel-v2' ); ?> <bdi dir="ltr"><?php echo esc_html( $tier['airline'] ); ?></bdi></span></li>
					<?php endif; ?>
				</ul>
				<a class="decision-tier-cta" href="<?php echo esc_url( $tier['cta'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( $card['cta_label'] ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="decision-card-live" data-decision-card-live role="status" aria-live="polite"></p>
	<p class="decision-card-freshness"><?php echo esc_html( $card['freshness'] ); ?></p>
	<p class="decision-card-scope"><?php echo esc_html( $card['scope_note'] ); ?></p>
	<p class="decision-card-scope decision-card-currency-note"><?php echo esc_html( $card['currency_note'] ); ?></p>
</section>
