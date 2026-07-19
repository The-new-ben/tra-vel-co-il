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
		'_tra_vel_author' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_reviewer' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_tra_vel_review_method' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
		'_tra_vel_publication_status' => array( 'type' => 'string', 'sanitize_callback' => 'tra_vel_v2_sanitize_publication_status' ),
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
 * Keep only a guide source-packet publication status.
 *
 * Phase 1 registers and exposes this value without making it a runtime
 * readiness requirement. Existing published guides must be backfilled before
 * the status can become a strict indexing gate.
 *
 * @param mixed $value Candidate status.
 * @return string
 */
function tra_vel_v2_sanitize_publication_status( $value ) {
	$status = sanitize_key( (string) $value );
	return in_array( $status, array( 'research', 'source-ready', 'editorial-review', 'publish-ready' ), true ) ? $status : '';
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
	foreach ( array_slice( $decoded, 0, 80 ) as $source ) {
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
	$current_query = null === $post_id;
	if ( $current_query && ! is_singular() ) {
		return false;
	}
	$post_id = $current_query ? get_queried_object_id() : (int) $post_id;
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
	$author  = sanitize_text_field( get_post_meta( $post_id, '_tra_vel_author', true ) );
	$reviewer = sanitize_text_field( get_post_meta( $post_id, '_tra_vel_reviewer', true ) );
	$method  = sanitize_textarea_field( get_post_meta( $post_id, '_tra_vel_review_method', true ) );
	$valid_sources = array_values(
		array_filter(
			$sources,
			static function ( $source ) {
				return is_array( $source ) && ! empty( $source['title'] ) && ! empty( $source['url'] ) && ! empty( $source['checkedAt'] );
			}
		)
	);

	return array(
		'primary_topic'      => sanitize_text_field( get_post_meta( $post_id, '_tra_vel_primary_topic', true ) ),
		'author'             => $author,
		'checked'            => $checked,
		'reviewer'           => $reviewer,
		'method'             => $method,
		'publication_status' => tra_vel_v2_sanitize_publication_status( get_post_meta( $post_id, '_tra_vel_publication_status', true ) ),
		'map_state'          => sanitize_key( get_post_meta( $post_id, '_tra_vel_map_state', true ) ),
		'sources'            => array_values( $sources ),
		'is_reviewed'        => (bool) ( $checked && $author && $reviewer && $method && count( $valid_sources ) >= 10 ),
	);
}

/**
 * Build one canonical breadcrumb trail from the real WordPress ancestor chain.
 *
 * Page ancestors are returned nearest-first by WordPress, so reversing them
 * preserves Home > Destinations > country > city. Destination guides without
 * a page ancestor retain the existing Home > Destinations > guide trail.
 *
 * @param int|null $post_id Optional post ID.
 * @return array<int, array{name:string,url:string,current:bool}>
 */
function tra_vel_v2_guide_breadcrumb_items( $post_id = null ) {
	$post_id          = $post_id ?: get_queried_object_id();
	$home_url         = home_url( '/' );
	$destinations_url = home_url( '/destinations/' );
	$items            = array(
		array(
			'name'    => __( 'ראשי', 'tra-vel-v2' ),
			'url'     => $home_url,
			'current' => false,
		),
	);
	$ancestor_items   = array();
	$has_destinations = false;

	foreach ( array_reverse( array_map( 'intval', (array) get_post_ancestors( $post_id ) ) ) as $ancestor_id ) {
		$ancestor_url   = (string) get_permalink( $ancestor_id );
		$ancestor_title = (string) get_the_title( $ancestor_id );
		if ( ! $ancestor_url || ! $ancestor_title ) {
			continue;
		}
		if ( rtrim( $ancestor_url, '/' ) === rtrim( $destinations_url, '/' ) ) {
			$has_destinations = true;
		}
		$ancestor_items[] = array(
			'name'    => $ancestor_title,
			'url'     => $ancestor_url,
			'current' => false,
		);
	}

	if ( ! $has_destinations ) {
		$items[] = array(
			'name'    => __( 'יעדים', 'tra-vel-v2' ),
			'url'     => $destinations_url,
			'current' => false,
		);
	}
	$items = array_merge( $items, $ancestor_items );
	$items[] = array(
		'name'    => (string) get_the_title( $post_id ),
		'url'     => (string) get_permalink( $post_id ),
		'current' => true,
	);

	return $items;
}

/**
 * Count words in the rendered editorial body without treating markup as copy.
 *
 * @param string $content Stored WordPress post content.
 * @return int
 */
function tra_vel_v2_count_guide_words( $content ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( ! preg_match_all( "/[\\p{L}\\p{N}][\\p{L}\\p{N}\\x{05BE}'’]*/u", $text, $matches ) ) {
		return 0;
	}
	return count( $matches[0] );
}

/**
 * Return the fail-closed publication contract used by robots and schema.
 *
 * The page remains readable when a check fails. Only indexability and Article
 * claims are withheld until the complete editorial contract is present.
 *
 * @param int|null $post_id Optional post ID.
 * @return array{ready:bool,checks:array<string,bool>,word_count:int}
 */
function tra_vel_v2_get_guide_publication_contract( $post_id = null ) {
	static $contracts = array();
	$post_id    = $post_id ?: get_queried_object_id();
	$profile    = tra_vel_v2_get_guide_profile( $post_id );
	$content    = (string) get_post_field( 'post_content', $post_id );
	$signature  = md5( $content . '|' . wp_json_encode( $profile ) );
	if ( isset( $contracts[ $post_id ] ) && hash_equals( $contracts[ $post_id ]['signature'], $signature ) ) {
		return $contracts[ $post_id ]['contract'];
	}
	$word_count = tra_vel_v2_count_guide_words( $content );
	$visible_text = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	preg_match_all( "/[\\p{L}\\p{N}][\\p{L}\\p{N}\\x{05BE}'’]*/u", $visible_text, $visible_words );
	$hebrew_words = array_filter(
		$visible_words[0] ?? array(),
		static function ( $word ) {
			return (bool) preg_match( '/[\\x{0590}-\\x{05FF}]/u', $word );
		}
	);
	$checked_timestamp = ! empty( $profile['checked'] ) ? strtotime( $profile['checked'] . ' 23:59:59 UTC' ) : false;
	$source_is_fresh = $checked_timestamp && $checked_timestamp <= time() + DAY_IN_SECONDS && $checked_timestamp >= time() - YEAR_IN_SECONDS;
	$source_records_valid = count( $profile['sources'] ) >= 10;
	$source_records_fresh = $source_records_valid;
	$source_dates_aligned = $source_records_valid && (bool) $checked_timestamp;
	foreach ( $profile['sources'] as $source ) {
		$source_checked = is_array( $source ) ? tra_vel_v2_sanitize_iso_date( $source['checkedAt'] ?? '' ) : '';
		$source_timestamp = $source_checked ? strtotime( $source_checked . ' 23:59:59 UTC' ) : false;
		$record_valid = is_array( $source )
			&& ! empty( $source['title'] )
			&& ! empty( $source['url'] )
			&& (bool) esc_url_raw( $source['url'], array( 'https' ) )
			&& (bool) $source_timestamp;
		$source_records_valid = $source_records_valid && $record_valid;
		$source_records_fresh = $source_records_fresh
			&& $record_valid
			&& $source_timestamp <= time() + DAY_IN_SECONDS
			&& $source_timestamp >= time() - YEAR_IN_SECONDS;
		$source_dates_aligned = $source_dates_aligned
			&& $record_valid
			&& $source_timestamp <= $checked_timestamp;
	}
	$checks     = array(
		'long_form_content' => $word_count >= 5000,
		'hebrew_language'   => count( $hebrew_words ) / max( $word_count, 1 ) >= 0.75,
		'section_depth'     => preg_match_all( '/<h2\\b/i', $content ) >= 12,
		'decision_tables'   => preg_match_all( '/<table\\b/i', $content ) >= 3,
		'primary_topic'     => ! empty( $profile['primary_topic'] ),
		'author'            => ! empty( $profile['author'] ),
		'reviewer'          => ! empty( $profile['reviewer'] ),
		'review_method'     => ! empty( $profile['method'] ),
		'source_checked'    => ! empty( $profile['checked'] ),
		'source_freshness'  => (bool) $source_is_fresh,
		'source_evidence'   => (bool) $source_records_valid,
		'source_record_freshness' => (bool) $source_records_fresh,
		'source_date_alignment' => (bool) $source_dates_aligned,
		'map_state'         => ! empty( $profile['map_state'] ),
		'review_complete'   => ! empty( $profile['is_reviewed'] ),
		'publication_status' => 'publish-ready' === ( $profile['publication_status'] ?? '' ),
	);

	$contract = array(
		'ready'      => ! in_array( false, $checks, true ),
		'checks'     => $checks,
		'word_count' => $word_count,
	);
	$contracts[ $post_id ] = array(
		'signature' => $signature,
		'contract'  => $contract,
	);
	return $contract;
}

/**
 * Whether a destination guide is complete enough to be indexed as an Article.
 *
 * @param int|null $post_id Optional post ID.
 * @return bool
 */
function tra_vel_v2_is_guide_publication_ready( $post_id = null ) {
	$contract = tra_vel_v2_get_guide_publication_contract( $post_id );
	return $contract['ready'];
}

/**
 * Render the visible evidence contract for a destination guide.
 *
 * @param int|null $post_id Optional post ID.
 */
function tra_vel_v2_render_guide_evidence( $post_id = null ) {
	$post_id = $post_id ?: get_queried_object_id();
	$profile = tra_vel_v2_get_guide_profile( $post_id );
	$contract = tra_vel_v2_get_guide_publication_contract( $post_id );
	$is_ready = ! empty( $contract['ready'] ) && 'publish-ready' === ( $profile['publication_status'] ?? '' );
	$author  = $profile['author'] ?: get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
	$method  = $profile['method'] ?: ( $is_ready
		? __( 'העובדות נבדקו מול מקורות ראשוניים. מחירים מסחריים מסומנים בנפרד ונבדקים שוב לפני הזמנה.', 'tra-vel-v2' )
		: __( 'היעזרו במדריך לתכנון ראשוני, ובדקו מחדש מחיר, זמינות, תנאים וכל מידע שעשוי להשתנות לפני החלטה או רכישה.', 'tra-vel-v2' ) );
	?>
	<section class="guide-evidence page-width" aria-labelledby="guide-evidence-title">
		<div class="guide-evidence-status <?php echo $is_ready ? 'is-reviewed' : 'is-pending'; ?>">
			<i data-lucide="<?php echo $is_ready ? 'badge-check' : 'info'; ?>"></i>
			<div><small><?php esc_html_e( 'שימוש במידע', 'tra-vel-v2' ); ?></small><strong id="guide-evidence-title"><?php echo esc_html( $is_ready ? __( 'המקורות נבדקו', 'tra-vel-v2' ) : __( 'בדקו פרטים משתנים לפני הזמנה', 'tra-vel-v2' ) ); ?></strong></div>
		</div>
		<div class="guide-evidence-facts">
			<?php if ( $author ) : ?><span><small><?php esc_html_e( 'מחבר', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $author ); ?></strong></span><?php endif; ?>
			<?php if ( ! empty( $profile['reviewer'] ) ) : ?><span><small><?php esc_html_e( 'בדיקה מקצועית', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $profile['reviewer'] ); ?></strong></span><?php endif; ?>
			<?php if ( ! empty( $profile['checked'] ) ) : ?><span><small><?php esc_html_e( 'תאריך בדיקה', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( $profile['checked'] ); ?></strong></span><?php endif; ?>
			<?php if ( ! empty( $profile['sources'] ) ) : ?><span><small><?php esc_html_e( 'מקורות', 'tra-vel-v2' ); ?></small><strong><?php echo esc_html( number_format_i18n( count( $profile['sources'] ) ) ); ?></strong></span><?php endif; ?>
			<span><small><?php esc_html_e( 'לפני רכישה', 'tra-vel-v2' ); ?></small><strong><?php esc_html_e( 'בדקו מחיר, זמינות ותנאים', 'tra-vel-v2' ); ?></strong></span>
		</div>
		<details class="guide-evidence-details">
			<summary><?php echo esc_html( $is_ready ? __( 'איך בדקנו את המדריך', 'tra-vel-v2' ) : __( 'איך להשתמש במידע', 'tra-vel-v2' ) ); ?></summary>
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
