<?php
/**
 * Destination-guide evidence, freshness and editorial metadata.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the registered guide metadata fields.
 *
 * @return array<string, array<string, mixed>>
 */
function tra_vel_v2_guide_meta_fields() {
	return array(
		'_tra_vel_primary_topic' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_source_checked' => array( 'type' => 'string', 'sanitize_callback' => 'tra_vel_v2_sanitize_iso_date' ),
		'_tra_vel_reviewer' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_review_method' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
		'_tra_vel_map_state' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
		'_tra_vel_sources_json' => array( 'type' => 'string', 'sanitize_callback' => 'tra_vel_v2_sanitize_sources_json' ),
		'_tra_vel_flight_time' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_daily_budget' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_best_season' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_best_for' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
	);
}

/**
 * Keep only a real ISO calendar date.
 *
 * @param mixed $value Candidate value.
 * @return string
 */
function tra_vel_v2_sanitize_iso_date( $value ) {
	$value = sanitize_text_field( (string) $value );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

	return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
}

/**
 * Validate and minimize guide source JSON.
 *
 * @param mixed $value Candidate JSON string.
 * @return string
 */
function tra_vel_v2_sanitize_sources_json( $value ) {
	$decoded = json_decode( wp_unslash( (string) $value ), true );
	if ( ! is_array( $decoded ) ) {
		return '[]';
	}

	$sources = array();
	foreach ( array_slice( $decoded, 0, 40 ) as $source ) {
		if ( ! is_array( $source ) || empty( $source['url'] ) || empty( $source['title'] ) ) {
			continue;
		}
		$url = esc_url_raw( $source['url'], array( 'https' ) );
		if ( ! $url ) {
			continue;
		}
		$sources[] = array(
			'id'        => sanitize_key( $source['id'] ?? '' ),
			'title'     => sanitize_text_field( $source['title'] ),
			'url'       => $url,
			'publisher' => sanitize_text_field( $source['publisher'] ?? '' ),
			'checkedAt' => tra_vel_v2_sanitize_iso_date( $source['checkedAt'] ?? '' ),
		);
	}

	return wp_json_encode( $sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** Register private editorial meta with authenticated REST editing support. */
function tra_vel_v2_register_guide_meta() {
	add_post_type_support( 'page', 'custom-fields' );
	foreach ( array( 'page', 'destination' ) as $post_type ) {
		foreach ( tra_vel_v2_guide_meta_fields() as $key => $args ) {
			register_post_meta(
				$post_type,
				$key,
				array_merge(
					$args,
					array(
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				)
			);
		}
	}
}
add_action( 'init', 'tra_vel_v2_register_guide_meta' );

/**
 * Whether a singular entry uses the destination-guide experience.
 *
 * @param int|null $post_id Optional post ID.
 * @return bool
 */
function tra_vel_v2_is_destination_guide( $post_id = null ) {
	$post_id = $post_id ?: get_queried_object_id();
	return $post_id && ( 'destination' === get_post_type( $post_id ) || 'page-destination.php' === get_page_template_slug( $post_id ) );
}

/**
 * Normalize guide evidence for templates and schema.
 *
 * @param int|null $post_id Optional post ID.
 * @return array<string, mixed>
 */
function tra_vel_v2_get_guide_profile( $post_id = null ) {
	$post_id = $post_id ?: get_queried_object_id();
	$sources = json_decode( (string) get_post_meta( $post_id, '_tra_vel_sources_json', true ), true );
	$sources = is_array( $sources ) ? $sources : array();
	$checked = tra_vel_v2_sanitize_iso_date( get_post_meta( $post_id, '_tra_vel_source_checked', true ) );

	return array(
		'primary_topic' => sanitize_text_field( get_post_meta( $post_id, '_tra_vel_primary_topic', true ) ),
		'checked'       => $checked,
		'reviewer'      => sanitize_text_field( get_post_meta( $post_id, '_tra_vel_reviewer', true ) ),
		'method'        => sanitize_textarea_field( get_post_meta( $post_id, '_tra_vel_review_method', true ) ),
		'map_state'     => sanitize_key( get_post_meta( $post_id, '_tra_vel_map_state', true ) ),
		'sources'       => array_values( $sources ),
		'is_reviewed'   => (bool) ( $checked && ! empty( $sources ) ),
	);
}

/**
 * Render the visible evidence contract for a destination guide.
 *
 * @param int|null $post_id Optional post ID.
 */
function tra_vel_v2_render_guide_evidence( $post_id = null ) {
	$post_id = $post_id ?: get_queried_object_id();
	$profile = tra_vel_v2_get_guide_profile( $post_id );
	$author  = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
	$method  = $profile['method'] ?: __( 'העובדות נבדקות מול מקורות ראשוניים. מחירים מסחריים מסומנים בנפרד ונבדקים שוב לפני הזמנה.', 'tra-vel-v2' );
	?>
	<section class="guide-evidence page-width" aria-labelledby="guide-evidence-title">
		<div class="guide-evidence-status <?php echo $profile['is_reviewed'] ? 'is-reviewed' : 'is-pending'; ?>">
			<i data-lucide="<?php echo $profile['is_reviewed'] ? 'badge-check' : 'clock-3'; ?>"></i>
			<div><small><?php esc_html_e( 'סטטוס עריכה', 'tra-vel-v2' ); ?></small><strong id="guide-evidence-title"><?php echo esc_html( $profile['is_reviewed'] ? __( 'המקורות נבדקו', 'tra-vel-v2' ) : __( 'ממתין לבדיקת מקורות', 'tra-vel-v2' ) ); ?></strong></div>
		</div>
		<div class="guide-evidence-facts">
			<span><small><?php esc_html_e( 'מחבר', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $author ?: 'Tra-Vel' ); ?></strong></span>
			<span><small><?php esc_html_e( 'בדיקה מקצועית', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $profile['reviewer'] ?: __( 'מערכת Tra-Vel', 'tra-vel-v2' ) ); ?></strong></span>
			<span><small><?php esc_html_e( 'העובדות נבדקו', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $profile['checked'] ?: __( 'טרם תועד', 'tra-vel-v2' ) ); ?></strong></span>
			<span><small><?php esc_html_e( 'מקורות ראשוניים', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( number_format_i18n( count( $profile['sources'] ) ) ); ?></strong></span>
		</div>
		<details class="guide-evidence-details">
			<summary><?php esc_html_e( 'איך בדקנו את המדריך', 'tra-vel-v2' ); ?></summary>
			<p><?php echo esc_html( $method ); ?></p>
			<?php if ( $profile['sources'] ) : ?>
				<ul>
					<?php foreach ( array_slice( $profile['sources'], 0, 12 ) as $source ) : ?>
						<?php if ( ! empty( $source['url'] ) && ! empty( $source['title'] ) ) : ?>
							<li><a href="<?php echo esc_url( $source['url'] ); ?>" rel="external noopener"><?php echo esc_html( $source['title'] ); ?></a><?php if ( ! empty( $source['checkedAt'] ) ) : ?><small><?php echo esc_html( $source['checkedAt'] ); ?></small><?php endif; ?></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</details>
	</section>
	<?php
}
