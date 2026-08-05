<?php
/**
 * The assembled trip proposal: trigger and hidden panel (theme 1.40.0).
 *
 * Fully server-rendered. Every price, date, airline, supplier link and message
 * line below was computed in inc/proposal.php from the same cached records the
 * decision card trusts; assets/js/trip-proposal.js only reveals this markup,
 * multiplies the fares already printed here, switches between the records
 * already rendered here, and swaps whole message lines already authored here.
 * The only ways out of the panel are plain anchors the browser owns: the
 * record's own tracked supplier link and the owned WhatsApp concierge.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

$view = isset( $args ) && is_array( $args ) ? $args : array();
if ( empty( $view['tiers'] ) || ! is_array( $view['tiers'] ) ) {
	return;
}

$panel_id = 'trip-proposal-' . (int) $view['instance'];
$title_id = $panel_id . '-title';
?>
<?php if ( ! empty( $view['trigger'] ) ) : ?>
	<button
		type="button"
		class="trip-proposal-trigger"
		data-trip-proposal-trigger
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		aria-expanded="false"
		hidden
	><i data-lucide="wand-sparkles" aria-hidden="true"></i><?php echo esc_html( $view['trigger_label'] ); ?></button>
<?php endif; ?>
<div
	id="<?php echo esc_attr( $panel_id ); ?>"
	class="trip-proposal"
	data-trip-proposal="<?php echo esc_attr( $view['state'] ); ?>"
	data-trip-proposal-trigger-label="<?php echo esc_attr( $view['trigger_label'] ); ?>"
	data-trip-proposal-travelers="<?php echo esc_attr( (string) $view['travelers'] ); ?>"
	data-trip-proposal-min="<?php echo esc_attr( (string) $view['min_travelers'] ); ?>"
	data-trip-proposal-max="<?php echo esc_attr( (string) $view['max_travelers'] ); ?>"
	data-trip-proposal-symbol="<?php echo esc_attr( $view['symbol'] ); ?>"
	data-trip-proposal-party-one="<?php echo esc_attr( $view['party_one'] ); ?>"
	data-trip-proposal-party-many="<?php echo esc_attr( $view['party_many'] ); ?>"
	data-trip-proposal-total-template="<?php echo esc_attr( $view['total_template'] ); ?>"
	data-trip-proposal-wa="<?php echo esc_url( $view['whatsapp'] ); ?>"
	data-trip-proposal-wa-all="<?php echo esc_url( $view['whatsapp_all'] ); ?>"
	data-trip-proposal-wa-addons-line="<?php echo esc_attr( $view['wa_addons_line'] ); ?>"
	data-trip-proposal-wa-addons-template="<?php echo esc_attr( $view['wa_addons_template'] ); ?>"
	data-trip-proposal-wa-travelers-lines="<?php echo esc_attr( wp_json_encode( $view['wa_travelers_lines'] ) ); ?>"
	data-trip-proposal-wa-dates-line="<?php echo esc_attr( $view['wa_dates_line'] ); ?>"
	role="group"
	aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
	tabindex="-1"
	hidden
>
	<button type="button" class="trip-proposal-close" data-trip-proposal-close aria-label="<?php echo esc_attr( $view['close_label'] ); ?>"><span aria-hidden="true">&times;</span></button>
	<p class="trip-proposal-fill" data-trip-proposal-fill aria-hidden="true" hidden>
		<?php foreach ( $view['fill_lines'] as $fill_line ) : ?>
			<span class="trip-proposal-fill-line" data-trip-proposal-fill-line><?php echo esc_html( $fill_line ); ?></span>
		<?php endforeach; ?>
	</p>
	<div class="trip-proposal-body" data-trip-proposal-body>
		<h3 class="trip-proposal-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $view['title'] ); ?></h3>
		<div class="trip-proposal-travelers" role="group" aria-label="<?php echo esc_attr( $view['travelers_label'] ); ?>">
			<span class="trip-proposal-travelers-label"><?php echo esc_html( $view['travelers_label'] ); ?></span>
			<button class="trip-proposal-step" type="button" data-trip-proposal-step="-1" aria-label="<?php echo esc_attr( $view['step_down_label'] ); ?>"><span aria-hidden="true">&minus;</span></button>
			<output class="trip-proposal-travelers-value" data-trip-proposal-travelers-value><?php echo esc_html( number_format_i18n( $view['travelers'] ) ); ?></output>
			<button class="trip-proposal-step" type="button" data-trip-proposal-step="1" aria-label="<?php echo esc_attr( $view['step_up_label'] ); ?>"><span aria-hidden="true">+</span></button>
		</div>
		<?php if ( count( $view['tiers'] ) > 1 ) : ?>
			<div class="trip-proposal-tier-choices" role="group" aria-label="<?php echo esc_attr( $view['tiers_heading'] ); ?>">
				<?php foreach ( $view['tiers'] as $tier_index => $tier ) : ?>
					<button
						type="button"
						class="trip-proposal-tier-choice<?php echo 0 === (int) $tier_index ? ' is-current' : ''; ?>"
						data-trip-proposal-tier-choice="<?php echo esc_attr( $tier['tier'] ); ?>"
						aria-pressed="<?php echo 0 === (int) $tier_index ? 'true' : 'false'; ?>"
					><?php echo esc_html( $tier['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php foreach ( $view['tiers'] as $tier_index => $tier ) : ?>
			<section
				class="trip-proposal-flight"
				data-trip-proposal-tier="<?php echo esc_attr( $tier['tier'] ); ?>"
				data-trip-proposal-tier-link="<?php echo esc_url( $tier['deep_link'] ); ?>"
				data-trip-proposal-tier-wa-dates="<?php echo esc_attr( $tier['wa_dates_line'] ); ?>"
				aria-label="<?php echo esc_attr( $tier['label'] ); ?>"
				<?php echo 0 === (int) $tier_index ? '' : 'hidden'; ?>
			>
				<p class="trip-proposal-flight-total">
					<strong><bdi dir="ltr" data-trip-proposal-tier-total data-trip-proposal-tier-unit="<?php echo esc_attr( (string) $tier['unit'] ); ?>" data-trip-proposal-tier-symbol="<?php echo esc_attr( $view['symbol'] ); ?>"><?php echo esc_html( $tier['total_label'] ); ?></bdi></strong>
					<span data-trip-proposal-tier-party><?php echo esc_html( $view['party_label'] ); ?></span>
				</p>
				<p class="trip-proposal-flight-person">
					<?php
					/* translators: %s: formatted per traveler price. */
					echo esc_html( sprintf( __( '%s לנוסע', 'tra-vel-v2' ), $tier['unit_label'] ) );
					?>
				</p>
				<ul class="trip-proposal-flight-facts">
					<li><i data-lucide="route" aria-hidden="true"></i><?php echo esc_html( $tier['stops_label'] ); ?></li>
					<?php if ( '' !== $tier['dates_label'] ) : ?>
						<li><i data-lucide="calendar-days" aria-hidden="true"></i><bdi dir="ltr"><?php echo esc_html( $tier['dates_label'] ); ?></bdi></li>
					<?php endif; ?>
					<?php if ( '' !== $tier['airline'] ) : ?>
						<li><i data-lucide="plane" aria-hidden="true"></i><span><?php echo esc_html( $view['airline_label'] ); ?> <bdi dir="ltr"><?php echo esc_html( $tier['airline'] ); ?></bdi></span></li>
					<?php endif; ?>
				</ul>
			</section>
		<?php endforeach; ?>
		<?php if ( ! empty( $view['addons'] ) ) : ?>
			<div class="trip-proposal-addons" role="group" aria-label="<?php echo esc_attr( $view['addons_heading'] ); ?>">
				<span class="trip-proposal-addons-heading"><?php echo esc_html( $view['addons_heading'] ); ?></span>
				<ul class="trip-proposal-addon-rows">
					<?php foreach ( $view['addons'] as $addon ) : ?>
						<li class="trip-proposal-addon" data-trip-proposal-addon="<?php echo esc_attr( $addon['key'] ); ?>">
							<button
								type="button"
								class="trip-proposal-addon-toggle"
								data-trip-proposal-addon-toggle
								data-trip-proposal-addon-label="<?php echo esc_attr( $addon['label'] ); ?>"
								role="switch"
								aria-checked="false"
							><span class="trip-proposal-addon-mark" aria-hidden="true"></span><span class="trip-proposal-addon-name"><?php echo esc_html( $addon['label'] ); ?></span><small><?php echo esc_html( $view['addon_note'] ); ?></small></button>
							<?php if ( '' !== $addon['url'] ) : ?>
								<a
									class="trip-proposal-addon-link"
									data-trip-proposal-addon-link
									href="<?php echo esc_url( $addon['url'] ); ?>"
									target="_blank"
									rel="sponsored nofollow noopener"
									hidden
								><?php echo esc_html( $addon['cta_label'] ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<p class="trip-proposal-total" data-trip-proposal-total aria-live="polite"><?php echo esc_html( $view['total_line'] ); ?></p>
		<div class="trip-proposal-actions">
			<a class="trip-proposal-book" data-trip-proposal-book href="<?php echo esc_url( $view['tiers'][0]['deep_link'] ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( $view['book_label'] ); ?><i data-lucide="external-link" aria-hidden="true"></i></a>
			<?php if ( '' !== $view['whatsapp'] ) : ?>
				<a class="trip-proposal-whatsapp" data-trip-proposal-whatsapp href="<?php echo esc_url( $view['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view['whatsapp_label'] ); ?><i data-lucide="message-circle" aria-hidden="true"></i></a>
			<?php endif; ?>
		</div>
		<p class="trip-proposal-scope"><?php echo esc_html( $view['scope_note'] ); ?></p>
		<p class="trip-proposal-scope trip-proposal-currency-note"><?php echo esc_html( $view['currency_note'] ); ?></p>
		<p class="trip-proposal-freshness"><?php echo esc_html( $view['freshness'] ); ?></p>
	</div>
</div>
