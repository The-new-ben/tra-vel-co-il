<?php
/**
 * Affiliate program registry for unverified Travelpayouts programs (theme 1.39.0).
 *
 * We are joined to several Travelpayouts programs (EKTA, the activities and
 * transfer suppliers, the eSIM suppliers) but hold no dashboard-verified
 * deep-link format for any of them tonight. A fabricated affiliate URL has
 * caused a real production incident before, so this file never builds one.
 *
 * Every program here is registered with two WordPress options: one holding
 * the real URL the owner will paste in later, one flipping the program live.
 * Both default empty and false, so every program renders as disabled until
 * the owner configures it. tra_vel_v2_affiliate_program_link() is the single
 * place that decides whether a program is safe to show as a real link, and
 * it fails closed on anything that is not a validated https URL.
 *
 * When a program is not enabled, callers fall back to the existing owned
 * WhatsApp assisted-sales handoff (inc/handoffs/class-whatsapp-sales-handoff-provider.php),
 * built here by calling the exact same tra_vel_v2_handoff_providers filter
 * the REST handoff controller uses, so the fallback is a real working link
 * and not a placeholder.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

/**
 * The affiliate program registry.
 *
 * Four generic, vertical-scoped entries, not one per brand name: we do not
 * yet know which specific supplier inside each Travelpayouts vertical will
 * end up with a verified link, so the registry key names the vertical, not
 * a brand.
 *
 * @return array<string,array<string,mixed>>
 */
function tra_vel_v2_affiliate_programs() {
	return array(
		'ekta'               => array(
			'label'          => 'EKTA',
			'vertical'       => 'insurance',
			'option_url'     => 'tra_vel_v2_affiliate_url_ekta',
			'option_enabled' => 'tra_vel_v2_affiliate_enabled_ekta',
			'cta_label'      => __( 'בדקו ביטוח נסיעות', 'tra-vel-v2' ),
			'fallback_label' => __( 'קבלו הצעת ביטוח בוואטסאפ', 'tra-vel-v2' ),
		),
		'activities_generic' => array(
			'label'          => __( 'ספק פעילויות וכרטיסים', 'tra-vel-v2' ),
			'vertical'       => 'activity',
			'option_url'     => 'tra_vel_v2_affiliate_url_activities_generic',
			'option_enabled' => 'tra_vel_v2_affiliate_enabled_activities_generic',
			'cta_label'      => __( 'בדקו פעילויות וכרטיסים', 'tra-vel-v2' ),
			'fallback_label' => __( 'קבלו הצעת פעילויות בוואטסאפ', 'tra-vel-v2' ),
		),
		'transfers_generic'  => array(
			'label'          => __( 'ספק העברות משדה התעופה', 'tra-vel-v2' ),
			'vertical'       => 'transfer',
			'option_url'     => 'tra_vel_v2_affiliate_url_transfers_generic',
			'option_enabled' => 'tra_vel_v2_affiliate_enabled_transfers_generic',
			'cta_label'      => __( 'בדקו העברה משדה התעופה', 'tra-vel-v2' ),
			'fallback_label' => __( 'קבלו הצעת העברה בוואטסאפ', 'tra-vel-v2' ),
		),
		'esim_generic'       => array(
			'label'          => __( 'ספק eSIM', 'tra-vel-v2' ),
			'vertical'       => 'esim',
			'option_url'     => 'tra_vel_v2_affiliate_url_esim_generic',
			'option_enabled' => 'tra_vel_v2_affiliate_enabled_esim_generic',
			'cta_label'      => __( 'בדקו eSIM לנסיעה', 'tra-vel-v2' ),
			'fallback_label' => __( 'קבלו הצעת eSIM בוואטסאפ', 'tra-vel-v2' ),
		),
	);
}

/**
 * Reduce any candidate into a URL we are willing to treat as a real link.
 *
 * The only rule that matters here: a non-empty string that esc_url_raw()
 * accepts and that still starts with the literal https scheme afterwards.
 * A javascript: URL, a bare host, an empty string or a non-https URL all
 * resolve to the same empty string.
 *
 * @param mixed $candidate Candidate URL.
 * @return string Validated https URL, or an empty string.
 */
function tra_vel_v2_affiliate_sanitize_url( $candidate ) {
	$candidate = is_string( $candidate ) ? trim( $candidate ) : '';
	if ( '' === $candidate ) {
		return '';
	}
	$safe = (string) esc_url_raw( $candidate, array( 'https' ) );

	return 0 === strpos( $safe, 'https://' ) ? $safe : '';
}

/**
 * Resolve one affiliate program to a normalized, fail-closed link.
 *
 * Enabled is true only when the enabled option is truthy AND the url option
 * holds a validated https URL. Every other combination, including an unknown
 * registry key, a disabled program, or a program missing its URL, returns
 * enabled=false and url=''. Nothing downstream may treat a disabled program
 * as configured.
 *
 * A filter runs after the option read so a future release or a temporary
 * snippet can set a program live without redeploying the theme, matching
 * this codebase's existing temp-snippet-then-option pattern. The filter
 * result is validated exactly as strictly as the option is.
 *
 * @param string $key Registry key, e.g. 'ekta'.
 * @return array{enabled:bool,url:string,label:string} Normalized link.
 */
function tra_vel_v2_affiliate_program_link( $key ) {
	$key      = sanitize_key( (string) $key );
	$programs = tra_vel_v2_affiliate_programs();
	$link     = array(
		'enabled' => false,
		'url'     => '',
		'label'   => '',
	);

	if ( isset( $programs[ $key ] ) ) {
		$program           = $programs[ $key ];
		$link['label']     = isset( $program['cta_label'] ) ? (string) $program['cta_label'] : '';
		$enabled_option_on = (bool) get_option( (string) $program['option_enabled'], false );
		$valid_url         = tra_vel_v2_affiliate_sanitize_url( get_option( (string) $program['option_url'], '' ) );
		if ( $enabled_option_on && '' !== $valid_url ) {
			$link['enabled'] = true;
			$link['url']     = $valid_url;
		}
	}

	/**
	 * Filter one affiliate program's resolved link.
	 *
	 * Runs even for an unknown key, so this doubles as the extension point
	 * for a program that has not been added to the registry yet. The
	 * returned url is still forced through the same https allowlist, so a
	 * filter can never bypass the fail-closed rule, only satisfy it from a
	 * different source than the options table.
	 *
	 * @param array{enabled:bool,url:string,label:string} $link Resolved link.
	 * @param string                                       $key  Registry key.
	 */
	$filtered = apply_filters( 'tra_vel_v2_affiliate_program_link_override', $link, $key );
	if ( ! is_array( $filtered ) || ! array_key_exists( 'enabled', $filtered ) || ! array_key_exists( 'url', $filtered ) ) {
		return $link;
	}

	$override_valid_url = tra_vel_v2_affiliate_sanitize_url( $filtered['url'] );
	$override_enabled    = (bool) $filtered['enabled'] && '' !== $override_valid_url;
	$override_label      = isset( $filtered['label'] ) && is_string( $filtered['label'] ) && '' !== trim( $filtered['label'] )
		? $filtered['label']
		: $link['label'];

	return array(
		'enabled' => $override_enabled,
		'url'     => $override_enabled ? $override_valid_url : '',
		'label'   => $override_label,
	);
}

/**
 * Build a real link into the existing owned WhatsApp assisted-sales handoff.
 *
 * This calls the exact same tra_vel_v2_handoff_providers filter and the same
 * 'tra-vel-concierge' provider that inc/handoffs/class-supplier-handoff-controller.php
 * uses for its REST /handoffs/prepare endpoint, then validates the built URL
 * against the provider's own host allowlist the same way that controller
 * does. Calling the provider directly, in PHP, means every page that needs
 * this fallback gets a real working link with zero JavaScript dependency and
 * zero new network request, matching how every other found-price surface in
 * this theme already works.
 *
 * @param string               $vertical One of the handoff controller's supported verticals.
 * @param array<string, mixed> $args     Optional trip context: offer_id, destination, origin,
 *                                       depart_date, return_date, travelers, budget, currency.
 * @return string Validated https WhatsApp URL, or an empty string when the channel is unavailable.
 */
function tra_vel_v2_whatsapp_assisted_handoff_url( $vertical, $args = array() ) {
	$vertical  = sanitize_key( (string) $vertical );
	$supported = array( 'flight', 'hotel', 'package', 'insurance', 'car', 'transfer', 'activity', 'esim' );
	if ( ! in_array( $vertical, $supported, true ) ) {
		return '';
	}

	$providers = apply_filters( 'tra_vel_v2_handoff_providers', array() );
	$provider  = null;
	foreach ( (array) $providers as $candidate ) {
		if ( is_array( $candidate ) && isset( $candidate['id'] ) && 'tra-vel-concierge' === $candidate['id'] ) {
			$provider = $candidate;
			break;
		}
	}
	if (
		null === $provider
		|| empty( $provider['live'] )
		|| empty( $provider['build_url'] ) || ! is_callable( $provider['build_url'] )
		|| empty( $provider['verticals'] ) || ! in_array( $vertical, (array) $provider['verticals'], true )
		|| empty( $provider['allowed_hosts'] )
	) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'offer_id'    => '',
			'destination' => '',
			'origin'      => '',
			'depart_date' => '',
			'return_date' => '',
			'travelers'   => 1,
			'budget'      => 0,
			'currency'    => 'ILS',
		)
	);

	$context = array(
		'vertical'    => $vertical,
		'offer_id'    => substr( preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) $args['offer_id'] ), 0, 80 ),
		'destination' => substr( sanitize_text_field( (string) $args['destination'] ), 0, 80 ),
		'origin'      => substr( sanitize_text_field( (string) $args['origin'] ), 0, 80 ),
		'depart_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['depart_date'] ) ? $args['depart_date'] : '',
		'return_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['return_date'] ) ? $args['return_date'] : '',
		'travelers'   => max( 1, min( 20, absint( $args['travelers'] ) ) ),
		'budget'      => max( 0, min( 1000000, absint( $args['budget'] ) ) ),
		'currency'    => in_array( $args['currency'], array( 'ILS', 'USD', 'EUR', 'GBP' ), true ) ? $args['currency'] : 'ILS',
	);

	$url  = (string) call_user_func( $provider['build_url'], $context );
	$url  = tra_vel_v2_affiliate_sanitize_url( $url );
	$host = strtolower( (string) preg_replace( '/[^a-z0-9.-]/', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	$user = wp_parse_url( $url, PHP_URL_USER );

	$allowed_hosts = array_map(
		static function ( $allowed_host ) {
			return strtolower( (string) preg_replace( '/[^a-z0-9.-]/', '', (string) $allowed_host ) );
		},
		(array) $provider['allowed_hosts']
	);

	if ( '' === $url || ! $host || $user || ! in_array( $host, $allowed_hosts, true ) ) {
		return '';
	}

	return $url;
}

/**
 * Render one honest "next step" commerce card: a real affiliate CTA when a
 * program is configured, or the owned WhatsApp assisted handoff otherwise.
 *
 * This is the single rendering function Part B (the insurance page) and
 * Part C (the four pillar hubs) both call. It never renders a guessed or
 * fabricated link: when no affiliate key is given, or the given program is
 * not enabled, the card falls back to tra_vel_v2_whatsapp_assisted_handoff_url().
 * On the vanishingly unlikely chance that channel is also unavailable, it
 * falls back once more to the planner, which needs no configuration at all,
 * so this function never renders a dead end.
 *
 * @param array<string, mixed> $args {
 *     @type string|null $affiliate_key  Registry key, or null when this vertical has no program.
 *     @type string      $vertical       Handoff vertical for the WhatsApp fallback context.
 *     @type string      $heading        Card heading.
 *     @type string       $enabled_body  Body copy shown when a real affiliate link is live.
 *     @type string       $fallback_body Body copy shown when falling back to WhatsApp.
 *     @type string       $fallback_label CTA label shown when falling back to WhatsApp.
 *     @type array        $context       Trip context passed to the WhatsApp handoff builder.
 * }
 * @return void
 */
function tra_vel_v2_render_commerce_next_step( $args = array() ) {
	static $instance = 0;

	$args = wp_parse_args(
		$args,
		array(
			'affiliate_key'  => null,
			'vertical'       => 'package',
			'heading'        => '',
			'enabled_body'   => '',
			'fallback_body'  => '',
			'fallback_label' => __( 'קבלו עזרה בוואטסאפ', 'tra-vel-v2' ),
			'context'        => array(),
		)
	);

	$link    = ( is_string( $args['affiliate_key'] ) && '' !== $args['affiliate_key'] )
		? tra_vel_v2_affiliate_program_link( $args['affiliate_key'] )
		: null;
	$is_live = is_array( $link ) && ! empty( $link['enabled'] ) && '' !== $link['url'];

	if ( $is_live ) {
		$url    = $link['url'];
		$cta    = '' !== $link['label'] ? $link['label'] : $args['fallback_label'];
		$body   = '' !== $args['enabled_body'] ? $args['enabled_body'] : $args['fallback_body'];
		$rel    = 'sponsored nofollow noopener';
		$is_new = true;
	} else {
		$url = tra_vel_v2_whatsapp_assisted_handoff_url( (string) $args['vertical'], (array) $args['context'] );
		if ( '' === $url && function_exists( 'home_url' ) ) {
			// Defensive only: the WhatsApp provider is always registered, so
			// this path should be unreachable in production. A page must
			// still never dead-end if it is ever reached.
			$url = home_url( '/ai-planner/' );
		}
		$cta    = $args['fallback_label'];
		$body   = $args['fallback_body'];
		$rel    = 'noopener noreferrer';
		$is_new = false;
	}

	if ( '' === trim( (string) $url ) || '' === trim( (string) $cta ) ) {
		return;
	}

	$instance++;
	$heading_id = 'commerce-next-step-heading-' . $instance;
	?>
	<div class="commerce-next-step-card" data-commerce-next-step data-commerce-next-step-state="<?php echo $is_new ? 'affiliate' : 'assisted'; ?>">
		<?php if ( '' !== trim( (string) $args['heading'] ) ) : ?>
			<h3 id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $args['heading'] ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) $body ) ) : ?>
			<p><?php echo esc_html( $body ); ?></p>
		<?php endif; ?>
		<a
			class="commerce-next-step-cta button-link dark-button"
			href="<?php echo esc_url( $url ); ?>"
			target="_blank"
			rel="<?php echo esc_attr( $rel ); ?>"
			<?php echo '' !== trim( (string) $args['heading'] ) ? ' aria-describedby="' . esc_attr( $heading_id ) . '"' : ''; ?>
		><?php echo esc_html( $cta ); ?><i data-lucide="arrow-left" aria-hidden="true"></i></a>
	</div>
	<?php
}
