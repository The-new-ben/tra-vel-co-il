<?php
/**
 * Runtime contract for registry-owned SEO decision and transactional pages.
 *
 * The content-opportunity registry owns intent and canonical paths. It does not
 * publish pages by itself. A page is indexable only after its exact owner,
 * stored editorial evidence and, where applicable, commercial route all pass.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for every Earth owner the opportunity system knows.
 *
 * The registry validator, runtime allowlist, Hebrew naming, airport routing
 * and contextual globe all derive from this one map, so adding a destination
 * is one edit instead of four copies that can drift apart.
 *
 * @return array<string,array{name:string,airport:string,latitude:string,longitude:string}>
 */
function tra_vel_v2_seo_opportunity_destinations() {
	return array(
		'budapest' => array( 'name' => 'בודפשט', 'airport' => 'BUD', 'latitude' => '47.4979', 'longitude' => '19.0402' ),
		'prague'   => array( 'name' => 'פראג', 'airport' => 'PRG', 'latitude' => '50.0755', 'longitude' => '14.4378' ),
		'vienna'   => array( 'name' => 'וינה', 'airport' => 'VIE', 'latitude' => '48.2082', 'longitude' => '16.3738' ),
		'athens'   => array( 'name' => 'אתונה', 'airport' => 'ATH', 'latitude' => '37.9838', 'longitude' => '23.7275' ),
		'dubai'    => array( 'name' => 'דובאי', 'airport' => 'DXB', 'latitude' => '25.2048', 'longitude' => '55.2708' ),
		'bangkok'  => array( 'name' => 'בנגקוק', 'airport' => 'BKK', 'latitude' => '13.7563', 'longitude' => '100.5018' ),
		'tokyo'    => array( 'name' => 'טוקיו', 'airport' => '', 'latitude' => '35.6762', 'longitude' => '139.6503' ),
		'lisbon'   => array( 'name' => 'ליסבון', 'airport' => 'LIS', 'latitude' => '38.7223', 'longitude' => '-9.1393' ),
		'larnaca'  => array( 'name' => 'לרנקה', 'airport' => 'LCA', 'latitude' => '34.9003', 'longitude' => '33.6232' ),
		'crete'    => array( 'name' => 'כרתים', 'airport' => 'HER', 'latitude' => '35.3387', 'longitude' => '25.1442' ),
	);
}

/** Return the authoritative registry path for this installation. */
function tra_vel_v2_seo_opportunity_registry_path() {
	$bundled = TRA_VEL_V2_PATH . '/content/seo/content-opportunity-registry.json';
	$checkout = dirname( TRA_VEL_V2_PATH, 2 ) . '/content/seo/content-opportunity-registry.json';
	$path = file_exists( $bundled ) ? $bundled : $checkout;

	/**
	 * Filter the registry path for controlled test and hosting layouts.
	 *
	 * @param string $path Selected registry path.
	 */
	return (string) apply_filters( 'tra_vel_v2_seo_opportunity_registry_path', $path );
}

/**
 * Validate one clean site-relative canonical path.
 *
 * @param mixed $path Candidate path.
 * @return bool
 */
function tra_vel_v2_is_seo_opportunity_path( $path ) {
	return is_string( $path ) && (bool) preg_match( '#^/(?:[a-z0-9-]+/)+$#', $path );
}

/**
 * Validate the registry and return its normalized runtime index.
 *
 * Any malformed record invalidates the whole registry. A partial registry can
 * silently assign the wrong search intent, so runtime loading must fail closed.
 *
 * @param string $path Optional explicit path used by contract tests.
 * @return array{valid:bool,error:string,entries:array<int,array<string,mixed>>,by_path:array<string,array<string,mixed>>,by_id:array<string,array<string,mixed>>}
 */
function tra_vel_v2_load_seo_opportunity_registry( $path = '' ) {
	static $cache = array();
	$path = $path ? (string) $path : tra_vel_v2_seo_opportunity_registry_path();
	$cache_key = $path && file_exists( $path ) ? $path . '|' . (string) filemtime( $path ) : $path;
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$result = array(
		'valid'   => false,
		'error'   => 'registry_unavailable',
		'entries' => array(),
		'by_path' => array(),
		'by_id'   => array(),
	);
	if ( ! $path || ! is_readable( $path ) || filesize( $path ) > 1048576 ) {
		$cache[ $cache_key ] = $result;
		return $result;
	}

	$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_array( $decoded ) || 1 !== ( $decoded['schemaVersion'] ?? null ) || 'he-IL' !== ( $decoded['locale'] ?? '' ) || ! isset( $decoded['entries'] ) || ! is_array( $decoded['entries'] ) ) {
		$result['error'] = 'registry_schema_invalid';
		$cache[ $cache_key ] = $result;
		return $result;
	}

	$allowed_keys = array( 'id', 'canonicalPath', 'pageType', 'primaryIntent', 'cluster', 'parentPath', 'mapState', 'status', 'conversionAction', 'monetization' );
	$allowed_types = array( 'commercial-hub', 'planning-tool', 'audience-hub', 'destination-hub', 'destination-support', 'transactional-cluster', 'decision-guide' );
	$allowed_statuses = array( 'live', 'content-ready', 'backlog' );
	$allowed_map_states = (array) apply_filters(
		'tra_vel_v2_seo_opportunity_map_states',
		array_keys( tra_vel_v2_seo_opportunity_destinations() )
	);
	$ids = array();
	$paths = array();
	$intents = array();
	$entries = array();
	$by_path = array();
	$by_id = array();

	foreach ( $decoded['entries'] as $entry ) {
		if ( ! is_array( $entry ) || array_diff( $allowed_keys, array_keys( $entry ) ) || array_diff( array_keys( $entry ), $allowed_keys ) ) {
			$result['error'] = 'registry_entry_shape_invalid';
			$cache[ $cache_key ] = $result;
			return $result;
		}
		$id = (string) $entry['id'];
		$canonical = (string) $entry['canonicalPath'];
		$parent = (string) $entry['parentPath'];
		$intent = trim( (string) $entry['primaryIntent'] );
		$normalized_intent = function_exists( 'mb_strtolower' ) ? mb_strtolower( $intent, 'UTF-8' ) : strtolower( $intent );
		$monetization = $entry['monetization'];
		$map_state = $entry['mapState'];

		$valid = preg_match( '/^[a-z0-9-]+$/', $id )
			&& ! isset( $ids[ $id ] )
			&& tra_vel_v2_is_seo_opportunity_path( $canonical )
			&& ! isset( $paths[ $canonical ] )
			&& ( '/' === $parent || tra_vel_v2_is_seo_opportunity_path( $parent ) )
			&& in_array( $entry['pageType'], $allowed_types, true )
			&& in_array( $entry['status'], $allowed_statuses, true )
			&& preg_match( '/^[a-z0-9-]+$/', (string) $entry['cluster'] )
			&& strlen( $intent ) >= 8
			&& preg_match( '/[\x{0590}-\x{05FF}]/u', $intent )
			&& ! isset( $intents[ $normalized_intent ] )
			&& is_string( $entry['conversionAction'] )
			&& strlen( trim( $entry['conversionAction'] ) ) >= 12
			&& preg_match( '/[\x{0590}-\x{05FF}]/u', $entry['conversionAction'] )
			&& ( null === $map_state || ( is_string( $map_state ) && in_array( $map_state, $allowed_map_states, true ) ) )
			&& is_array( $monetization )
			&& count( $monetization ) > 0
			&& count( $monetization ) === count( array_unique( $monetization ) );
		if ( $valid ) {
			foreach ( $monetization as $product ) {
				if ( ! is_string( $product ) || ! preg_match( '/^[a-z0-9-]+$/', $product ) ) {
					$valid = false;
					break;
				}
			}
		}
		if ( $valid && 'transactional-cluster' === $entry['pageType'] && ! preg_match( '#^/(?:flights|hotels|packages)/[a-z0-9-]+/$#', $canonical ) ) {
			$valid = false;
		}
		if ( $valid && 'transactional-cluster' === $entry['pageType'] ) {
			$segments = array_values( array_filter( explode( '/', trim( $canonical, '/' ) ) ) );
			$valid = 2 === count( $segments )
				&& '/' . $segments[0] . '/' === $parent;
		}
		if ( $valid && 'decision-guide' === $entry['pageType'] ) {
			$segments = array_values( array_filter( explode( '/', trim( $canonical, '/' ) ) ) );
			$valid = 3 === count( $segments )
				&& 'guides' === $segments[0]
				&& (string) $entry['cluster'] === $segments[1]
				&& '/destinations/' . $segments[1] . '/' === $parent;
		}
		if ( $valid && in_array( $entry['pageType'], array( 'decision-guide', 'transactional-cluster' ), true ) && in_array( $entry['status'], array( 'live', 'content-ready' ), true ) ) {
			$valid = is_string( $map_state ) && in_array( $map_state, $allowed_map_states, true );
		}
		if ( ! $valid ) {
			$result['error'] = 'registry_entry_invalid';
			$cache[ $cache_key ] = $result;
			return $result;
		}

		$ids[ $id ] = true;
		$paths[ $canonical ] = true;
		$intents[ $normalized_intent ] = true;
		$entries[] = $entry;
		$by_path[ $canonical ] = $entry;
		$by_id[ $id ] = $entry;
	}

	foreach ( $entries as $entry ) {
		if ( '/' !== $entry['parentPath'] && ! isset( $by_path[ $entry['parentPath'] ] ) ) {
			$result['error'] = 'registry_parent_missing';
			$cache[ $cache_key ] = $result;
			return $result;
		}
		if ( 'decision-guide' === $entry['pageType'] ) {
			$parent = $by_path[ $entry['parentPath'] ] ?? null;
			if ( ! is_array( $parent ) || 'destination-hub' !== ( $parent['pageType'] ?? '' ) || ( $parent['cluster'] ?? '' ) !== $entry['cluster'] ) {
				$result['error'] = 'registry_decision_parent_invalid';
				$cache[ $cache_key ] = $result;
				return $result;
			}
			if ( in_array( $entry['status'], array( 'live', 'content-ready' ), true ) && ! in_array( $parent['status'] ?? '', array( 'live', 'content-ready' ), true ) ) {
				$result['error'] = 'registry_decision_parent_unready';
				$cache[ $cache_key ] = $result;
				return $result;
			}
		}
		if ( 'transactional-cluster' === $entry['pageType'] ) {
			$parent = $by_path[ $entry['parentPath'] ] ?? null;
			if ( ! is_array( $parent ) || 'commercial-hub' !== ( $parent['pageType'] ?? '' ) || ( $parent['canonicalPath'] ?? '' ) !== $entry['parentPath'] ) {
				$result['error'] = 'registry_transaction_parent_invalid';
				$cache[ $cache_key ] = $result;
				return $result;
			}
			if ( in_array( $entry['status'], array( 'live', 'content-ready' ), true ) && ! in_array( $parent['status'] ?? '', array( 'live', 'content-ready' ), true ) ) {
				$result['error'] = 'registry_transaction_parent_unready';
				$cache[ $cache_key ] = $result;
				return $result;
			}
		}
	}

	$result = array(
		'valid'   => true,
		'error'   => '',
		'entries' => $entries,
		'by_path' => $by_path,
		'by_id'   => $by_id,
	);
	$cache[ $cache_key ] = $result;
	return $result;
}

/**
 * Return one exact registry owner.
 *
 * @param string $canonical_path Exact site-relative canonical path.
 * @param string $registry_path  Optional registry fixture.
 * @return array<string,mixed>|null
 */
function tra_vel_v2_get_seo_opportunity_by_path( $canonical_path, $registry_path = '' ) {
	if ( ! tra_vel_v2_is_seo_opportunity_path( $canonical_path ) ) {
		return null;
	}
	$registry = tra_vel_v2_load_seo_opportunity_registry( $registry_path );
	return $registry['valid'] && isset( $registry['by_path'][ $canonical_path ] ) ? $registry['by_path'][ $canonical_path ] : null;
}

/** Convert a same-site permalink into the registry's site-relative path. */
function tra_vel_v2_seo_opportunity_path_from_url( $url ) {
	$path = wp_parse_url( (string) $url, PHP_URL_PATH );
	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return '';
	}
	$home_path = is_string( $home_path ) ? '/' . trim( $home_path, '/' ) : '/';
	if ( '/' !== $home_path ) {
		if ( 0 !== strpos( $path, $home_path . '/' ) && $path !== $home_path ) {
			return '';
		}
		$path = substr( $path, strlen( $home_path ) );
	}
	$path = '/' . trim( $path, '/' ) . '/';
	return tra_vel_v2_is_seo_opportunity_path( $path ) ? $path : '';
}

/** Whether the entry type and registry status permit a public page at all. */
function tra_vel_v2_is_exposable_seo_opportunity( $entry ) {
	return is_array( $entry )
		&& in_array( $entry['pageType'] ?? '', array( 'decision-guide', 'transactional-cluster' ), true )
		&& in_array( $entry['status'] ?? '', array( 'live', 'content-ready' ), true );
}

/** Whether an exact registry entry belongs to this reusable page system. */
function tra_vel_v2_is_managed_seo_opportunity( $entry ) {
	return is_array( $entry ) && in_array( $entry['pageType'] ?? '', array( 'decision-guide', 'transactional-cluster' ), true );
}

/** Find the exact path owner independently of the assigned WordPress template. */
function tra_vel_v2_get_owned_seo_opportunity( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	if ( ! $post_id ) {
		return null;
	}
	$path = tra_vel_v2_seo_opportunity_path_from_url( get_permalink( $post_id ) );
	$entry = $path ? tra_vel_v2_get_seo_opportunity_by_path( $path ) : null;
	return tra_vel_v2_is_managed_seo_opportunity( $entry ) ? $entry : null;
}

/** Check the exact template and stored registry owner identity for one page. */
function tra_vel_v2_seo_opportunity_identity_matches( $post_id, $entry ) {
	return $post_id
		&& tra_vel_v2_is_exposable_seo_opportunity( $entry )
		&& 'page-seo-opportunity.php' === get_page_template_slug( $post_id )
		&& sanitize_key( (string) get_post_meta( $post_id, '_tra_vel_seo_opportunity_id', true ) ) === ( $entry['id'] ?? '' );
}

/** Return the exact owner for the current reusable SEO template. */
function tra_vel_v2_get_current_seo_opportunity( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	if ( ! $post_id || 'page-seo-opportunity.php' !== get_page_template_slug( $post_id ) ) {
		return null;
	}
	return tra_vel_v2_get_owned_seo_opportunity( $post_id );
}

/**
 * Resolve a managed owner for fail-closed protection, including path drift.
 *
 * Rendering still uses the exact-path getter. Stored ownership is considered
 * only by protection hooks and only when it matches the fully validated registry.
 */
function tra_vel_v2_get_protected_seo_opportunity( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$entry = tra_vel_v2_get_owned_seo_opportunity( $post_id );
	if ( tra_vel_v2_is_managed_seo_opportunity( $entry ) ) {
		return $entry;
	}
	$stored_owner = $post_id ? sanitize_key( (string) get_post_meta( $post_id, '_tra_vel_seo_opportunity_id', true ) ) : '';
	if ( ! $stored_owner ) {
		return null;
	}
	$registry = tra_vel_v2_load_seo_opportunity_registry();
	$entry = ! empty( $registry['valid'] ) ? ( $registry['by_id'][ $stored_owner ] ?? null ) : null;
	return tra_vel_v2_is_managed_seo_opportunity( $entry ) ? $entry : null;
}

/** Whether a page carries enough managed identity to require fail-closed protection. */
function tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry = null ) {
	$post_id = (int) $post_id;
	return $post_id && (
		tra_vel_v2_is_managed_seo_opportunity( $entry )
		|| '' !== sanitize_key( (string) get_post_meta( $post_id, '_tra_vel_seo_opportunity_id', true ) )
		|| 'page-seo-opportunity.php' === get_page_template_slug( $post_id )
	);
}

/** Limit current-query SEO hooks to singular WordPress pages. */
function tra_vel_v2_is_seo_opportunity_page_request() {
	return is_singular( 'page' );
}

/** Register explicit, authenticated page-readiness evidence. */
function tra_vel_v2_register_seo_opportunity_meta() {
	add_post_type_support( 'page', 'custom-fields' );
	$auth = static function ( $allowed, $meta_key, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return current_user_can( 'edit_post', (int) $post_id );
	};
	register_post_meta(
		'page',
		'_tra_vel_seo_opportunity_id',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'string',
					'context' => array( 'edit' ),
				),
			),
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => $auth,
		)
	);
	foreach ( array( '_tra_vel_seo_opportunity_ready', '_tra_vel_seo_conversion_ready' ) as $key ) {
		register_post_meta(
			'page',
			$key,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'boolean',
						'context' => array( 'edit' ),
					),
				),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);
	}
}
add_action( 'init', 'tra_vel_v2_register_seo_opportunity_meta', 12 );

/** Return visible-word, Hebrew-ratio and structural metrics for stored content. */
function tra_vel_v2_seo_opportunity_content_metrics( $content ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	preg_match_all( "/[\\p{L}\\p{N}][\\p{L}\\p{N}\\x{05BE}'’]*/u", $text, $words );
	$visible_words = $words[0] ?? array();
	$hebrew_words = array_filter(
		$visible_words,
		static function ( $word ) {
			return (bool) preg_match( '/[\x{0590}-\x{05FF}]/u', $word );
		}
	);
	return array(
		'word_count'   => count( $visible_words ),
		'hebrew_ratio' => count( $hebrew_words ) / max( count( $visible_words ), 1 ),
		'h2_count'     => preg_match_all( '/<h2\b/i', (string) $content ),
		'table_count'  => preg_match_all( '/<table\b/i', (string) $content ),
	);
}

/** Map an Earth owner to its public Hebrew destination name. */
function tra_vel_v2_seo_opportunity_hebrew_name( $map_state ) {
	$destinations = tra_vel_v2_seo_opportunity_destinations();
	return $destinations[ sanitize_key( (string) $map_state ) ]['name'] ?? '';
}

/**
 * Return the search-facing document title for the current owned package page.
 *
 * The formula carries the head term plus the demand modifiers the SERP
 * rewards, without touching stored WordPress titles: it applies only to an
 * identity-matched packages transactional owner with a known Hebrew name.
 *
 * @param int $post_id Optional post ID.
 * @return string Empty string when the current page is not an owned package page.
 */
function tra_vel_v2_seo_opportunity_public_title( $post_id = 0 ) {
	if ( ! $post_id && ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return '';
	}
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$entry = tra_vel_v2_get_current_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_exposable_seo_opportunity( $entry ) || 'transactional-cluster' !== ( $entry['pageType'] ?? '' ) ) {
		return '';
	}
	$segments = array_values( array_filter( explode( '/', trim( (string) ( $entry['canonicalPath'] ?? '' ), '/' ) ) ) );
	if ( 'packages' !== ( $segments[0] ?? '' ) ) {
		return '';
	}
	$name = tra_vel_v2_seo_opportunity_hebrew_name( $entry['mapState'] ?? '' );
	if ( '' === $name ) {
		return '';
	}
	return sprintf(
		/* translators: %s: destination Hebrew name. */
		__( 'חופשה ב%1$s: חבילות נופש ודילים ל%1$s, טיסה ומלון', 'tra-vel-v2' ),
		$name
	);
}

/** Map an Earth owner to the airport code consumed by commercial hubs. */
function tra_vel_v2_seo_opportunity_airport_code( $map_state ) {
	$destinations = tra_vel_v2_seo_opportunity_destinations();
	return $destinations[ sanitize_key( (string) $map_state ) ]['airport'] ?? '';
}

/** Verify that a transactional child leads to a published comparison surface. */
function tra_vel_v2_seo_opportunity_conversion_operational( $entry ) {
	$path_parts = array_values( array_filter( explode( '/', trim( (string) ( $entry['canonicalPath'] ?? '' ), '/' ) ) ) );
	$vertical = $path_parts[0] ?? '';
	if ( ! in_array( $vertical, array( 'flights', 'hotels', 'packages' ), true ) ) {
		return false;
	}
	if ( '/' . $vertical . '/' !== ( $entry['parentPath'] ?? '' ) ) {
		return false;
	}
	$hub = get_page_by_path( $vertical, OBJECT, 'page' );
	$operational = $hub instanceof WP_Post
		&& 'publish' === get_post_status( $hub )
		&& 'page-experience.php' === get_page_template_slug( $hub->ID );
	return (bool) apply_filters( 'tra_vel_v2_seo_opportunity_conversion_operational', $operational, $entry, $hub );
}

/** Return the semantic registry parent, independent of structural WP ancestors. */
function tra_vel_v2_seo_opportunity_semantic_parent( $entry ) {
	$parent_path = is_array( $entry ) ? (string) ( $entry['parentPath'] ?? '' ) : '';
	return tra_vel_v2_is_seo_opportunity_path( $parent_path ) ? tra_vel_v2_get_seo_opportunity_by_path( $parent_path ) : null;
}

/** Verify that the semantic parent is an owned, published public route. */
function tra_vel_v2_seo_opportunity_semantic_parent_ready( $entry ) {
	$parent = tra_vel_v2_seo_opportunity_semantic_parent( $entry );
	if ( ! is_array( $parent ) || ! in_array( $parent['status'] ?? '', array( 'live', 'content-ready' ), true ) ) {
		return false;
	}
	if ( 'decision-guide' === ( $entry['pageType'] ?? '' ) && ( 'destination-hub' !== ( $parent['pageType'] ?? '' ) || ( $parent['cluster'] ?? '' ) !== ( $entry['cluster'] ?? '' ) ) ) {
		return false;
	}
	if ( 'transactional-cluster' === ( $entry['pageType'] ?? '' ) && ( 'commercial-hub' !== ( $parent['pageType'] ?? '' ) || ( $parent['canonicalPath'] ?? '' ) !== ( $entry['parentPath'] ?? '' ) ) ) {
		return false;
	}
	$parent_page = get_page_by_path( trim( $parent['canonicalPath'], '/' ), OBJECT, 'page' );
	$ready = $parent_page instanceof WP_Post
		&& 'publish' === get_post_status( $parent_page )
		&& (string) $parent['canonicalPath'] === tra_vel_v2_seo_opportunity_path_from_url( get_permalink( $parent_page ) );
	if ( $ready && 'decision-guide' === ( $entry['pageType'] ?? '' ) ) {
		$ready = 'page-destination.php' === get_page_template_slug( $parent_page->ID );
	}
	if ( $ready && 'decision-guide' === ( $entry['pageType'] ?? '' ) ) {
		$profile = function_exists( 'tra_vel_v2_get_guide_profile' ) ? tra_vel_v2_get_guide_profile( $parent_page->ID ) : array();
		$guide_contract = function_exists( 'tra_vel_v2_get_guide_publication_contract' ) ? tra_vel_v2_get_guide_publication_contract( $parent_page->ID ) : array( 'ready' => false );
		$ready = ! empty( $guide_contract['ready'] ) && 'publish-ready' === ( $profile['publication_status'] ?? '' );
	}
	return (bool) apply_filters( 'tra_vel_v2_seo_opportunity_semantic_parent_ready', $ready, $entry, $parent, $parent_page );
}

/** Build visible and structured breadcrumbs from the registry semantic tree. */
function tra_vel_v2_seo_opportunity_breadcrumb_items( $entry, $post_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! is_array( $entry ) ) {
		return array();
	}
	$items = array(
		array(
			'name'    => __( 'ראשי', 'tra-vel-v2' ),
			'url'     => home_url( '/' ),
			'current' => false,
		),
	);
	if ( 'decision-guide' === ( $entry['pageType'] ?? '' ) ) {
		$items[] = array(
			'name'    => __( 'יעדים', 'tra-vel-v2' ),
			'url'     => home_url( '/destinations/' ),
			'current' => false,
		);
	}
	$parent = tra_vel_v2_seo_opportunity_semantic_parent( $entry );
	if ( is_array( $parent ) ) {
		$items[] = array(
			'name'    => (string) $parent['primaryIntent'],
			'url'     => home_url( $parent['canonicalPath'] ),
			'current' => false,
		);
	}
	$items[] = array(
		'name'    => (string) $entry['primaryIntent'],
		'url'     => home_url( $entry['canonicalPath'] ),
		'current' => true,
	);
	return $items;
}

/** Coordinates consumed by the compact contextual Earth. */
function tra_vel_v2_seo_opportunity_coordinates( $map_state ) {
	$destinations = tra_vel_v2_seo_opportunity_destinations();
	$destination = $destinations[ sanitize_key( (string) $map_state ) ] ?? null;
	if ( ! is_array( $destination ) ) {
		return null;
	}
	return array(
		'latitude'  => $destination['latitude'],
		'longitude' => $destination['longitude'],
	);
}

/**
 * Return the dual registry and per-page publication contract.
 *
 * @return array{ready:bool,checks:array<string,bool>,metrics:array<string,mixed>,entry:array<string,mixed>|null}
 */
function tra_vel_v2_get_seo_opportunity_publication_contract( $post_id = 0, $entry = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$entry = is_array( $entry ) ? $entry : tra_vel_v2_get_owned_seo_opportunity( $post_id );
	$content = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';
	$metrics = tra_vel_v2_seo_opportunity_content_metrics( $content );
	$stored_owner = $post_id ? sanitize_key( (string) get_post_meta( $post_id, '_tra_vel_seo_opportunity_id', true ) ) : '';
	$explicit_ready = $post_id ? rest_sanitize_boolean( get_post_meta( $post_id, '_tra_vel_seo_opportunity_ready', true ) ) : false;
	$exact_path = $post_id && is_array( $entry ) && ( $entry['canonicalPath'] ?? '' ) === tra_vel_v2_seo_opportunity_path_from_url( get_permalink( $post_id ) );
	$checks = array(
		'registry_owner'   => tra_vel_v2_is_exposable_seo_opportunity( $entry ),
		'exact_canonical'  => (bool) $exact_path,
		'owner_evidence'   => is_array( $entry ) && $stored_owner === ( $entry['id'] ?? '' ),
		'explicit_ready'   => (bool) $explicit_ready,
		'published_page'   => $post_id && 'publish' === get_post_status( $post_id ),
		'correct_template' => $post_id && 'page-seo-opportunity.php' === get_page_template_slug( $post_id ),
	);

	if ( is_array( $entry ) && 'decision-guide' === ( $entry['pageType'] ?? '' ) ) {
		$profile = function_exists( 'tra_vel_v2_get_guide_profile' ) ? tra_vel_v2_get_guide_profile( $post_id ) : array();
		$guide_contract = function_exists( 'tra_vel_v2_get_guide_publication_contract' ) ? tra_vel_v2_get_guide_publication_contract( $post_id ) : array( 'ready' => false, 'checks' => array() );
		$checks['guide_publication_contract'] = ! empty( $guide_contract['ready'] );
		foreach ( (array) ( $guide_contract['checks'] ?? array() ) as $guide_check => $passed ) {
			$checks[ 'guide_' . sanitize_key( $guide_check ) ] = (bool) $passed;
		}
		$checks['publication_evidence'] = 'publish-ready' === ( $profile['publication_status'] ?? '' );
		$checks['map_state_evidence'] = ! empty( $entry['mapState'] ) && ( $profile['map_state'] ?? '' ) === $entry['mapState'];
		$checks['semantic_parent'] = tra_vel_v2_seo_opportunity_semantic_parent_ready( $entry );
	} elseif ( is_array( $entry ) && 'transactional-cluster' === ( $entry['pageType'] ?? '' ) ) {
		$checks['supporting_content'] = $metrics['word_count'] >= 800;
		$checks['hebrew_language'] = $metrics['hebrew_ratio'] >= 0.70;
		$checks['section_depth'] = $metrics['h2_count'] >= 4;
		$checks['conversion_ready'] = $post_id && rest_sanitize_boolean( get_post_meta( $post_id, '_tra_vel_seo_conversion_ready', true ) );
		$checks['conversion_operational'] = tra_vel_v2_seo_opportunity_conversion_operational( $entry );
		$checks['semantic_parent'] = tra_vel_v2_seo_opportunity_semantic_parent_ready( $entry );
		$checks['map_state_evidence'] = ! empty( $entry['mapState'] ) && null !== tra_vel_v2_seo_opportunity_coordinates( $entry['mapState'] );
	} else {
		$checks['supported_owner_type'] = false;
	}

	return array(
		'ready'   => ! in_array( false, $checks, true ),
		'checks'  => $checks,
		'metrics' => $metrics,
		'entry'   => $entry,
	);
}

/**
 * Return crawlable same-cluster links that have passed the complete public
 * publication contract.
 *
 * Registry status alone is never enough. The exact WordPress page, canonical
 * path, template, stored owner and all editorial or commercial evidence must
 * still pass before a link can enter the public internal-link graph.
 *
 * @param string $cluster    Registry cluster key.
 * @param string $exclude_id Optional registry owner to omit.
 * @param int    $limit      Maximum links to return, clamped to 1-8.
 * @return array<int,array{id:string,url:string,title:string,kind:string,cta:string}>
 */
function tra_vel_v2_get_public_seo_opportunity_links( $cluster, $exclude_id = '', $limit = 6 ) {
	$cluster = sanitize_key( (string) $cluster );
	$exclude_id = sanitize_key( (string) $exclude_id );
	$limit = max( 1, min( 8, (int) $limit ) );
	if ( ! $cluster ) {
		return array();
	}

	$registry = tra_vel_v2_load_seo_opportunity_registry();
	if ( empty( $registry['valid'] ) || empty( $registry['entries'] ) ) {
		return array();
	}

	$links = array();
	foreach ( $registry['entries'] as $entry ) {
		if (
			! tra_vel_v2_is_exposable_seo_opportunity( $entry )
			|| $cluster !== sanitize_key( (string) ( $entry['cluster'] ?? '' ) )
			|| $exclude_id === ( $entry['id'] ?? '' )
		) {
			continue;
		}

		$page = get_page_by_path( trim( (string) $entry['canonicalPath'], '/' ), OBJECT, 'page' );
		if ( ! $page instanceof WP_Post || ! tra_vel_v2_seo_opportunity_identity_matches( $page->ID, $entry ) ) {
			continue;
		}

		$contract = tra_vel_v2_get_seo_opportunity_publication_contract( $page->ID, $entry );
		if ( empty( $contract['ready'] ) ) {
			continue;
		}

		$links[] = array(
			'id'    => (string) $entry['id'],
			'url'   => get_permalink( $page ),
			'title' => (string) $entry['primaryIntent'],
			'kind'  => 'decision-guide' === $entry['pageType'] ? 'decision' : 'comparison',
			'cta'   => (string) $entry['conversionAction'],
		);
		if ( count( $links ) >= $limit ) {
			break;
		}
	}

	return $links;
}

/** Build the non-commercial schema nodes for one fully ready opportunity page. */
function tra_vel_v2_seo_opportunity_schema_nodes( $post_id = 0, $entry = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	$entry = is_array( $entry ) ? $entry : tra_vel_v2_get_owned_seo_opportunity( $post_id );
	$contract = tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry );
	if ( ! $contract['ready'] ) {
		return array();
	}

	$url = home_url( $entry['canonicalPath'] );
	$breadcrumb_id = $url . '#breadcrumb';
	$items = array();
	foreach ( tra_vel_v2_seo_opportunity_breadcrumb_items( $entry, $post_id ) as $position => $item ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position + 1,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
	}
	$webpage = array(
		'@type'      => 'WebPage',
		'@id'        => $url . '#webpage',
		'url'        => $url,
		'name'       => $entry['primaryIntent'],
		'inLanguage' => 'he-IL',
		'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		'breadcrumb' => array( '@id' => $breadcrumb_id ),
	);
	$nodes = array(
		$webpage,
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $breadcrumb_id,
			'itemListElement' => $items,
		),
	);
	if ( function_exists( 'tra_vel_v2_visible_faq_items' ) && function_exists( 'tra_vel_v2_faq_page_node' ) ) {
		$faq_node = tra_vel_v2_faq_page_node( $url, tra_vel_v2_visible_faq_items( $post_id ) );
		if ( $faq_node ) {
			$nodes[] = $faq_node;
		}
	}

	if ( 'decision-guide' !== ( $entry['pageType'] ?? '' ) ) {
		return $nodes;
	}
	$profile = tra_vel_v2_get_guide_profile( $post_id );
	$author = $profile['author'] ?: ( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) ?: 'Tra-Vel' );
	$description = get_the_excerpt( $post_id );
	$article = array(
		'@type'            => 'Article',
		'@id'              => $url . '#decision-guide',
		'url'              => $url,
		'headline'         => $entry['primaryIntent'],
		'description'      => $description ? wp_strip_all_tags( $description ) : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 34 ),
		'datePublished'    => get_the_date( DATE_W3C, $post_id ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
		'lastReviewed'     => $profile['checked'],
		'inLanguage'       => 'he-IL',
		'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
		'isPartOf'         => array( '@id' => home_url( '/#website' ) ),
		'author'           => array(
			'@type' => false !== stripos( $author, 'Tra-Vel' ) ? 'Organization' : 'Person',
			'name'  => $author,
		),
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
		'about'            => array( '@type' => 'TouristDestination', 'name' => $profile['primary_topic'] ),
		'citation'         => array_values(
			array_filter(
				array_map(
					static function ( $source ) {
						return ! empty( $source['url'] ) ? esc_url_raw( $source['url'], array( 'https' ) ) : '';
					},
					$profile['sources']
				)
			)
		),
	);
	$nodes[0]['about'] = array( '@id' => $article['@id'] );
	$nodes[] = $article;
	return $nodes;
}

/**
 * Replace plugin rich nodes with the theme's gated, semantic graph fragment.
 * Product, Offer and ItemList are never trusted without validated supplier data.
 */
function tra_vel_v2_merge_seo_opportunity_schema_graph( $graph ) {
	if ( ! is_array( $graph ) || ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $graph;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $graph;
	}
	$contract = tra_vel_v2_is_managed_seo_opportunity( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false, 'entry' => null );
	$ready_nodes = $contract['ready'] ? tra_vel_v2_seo_opportunity_schema_nodes( get_queried_object_id(), $contract['entry'] ) : array();
	$output = array();
	foreach ( $graph as $node ) {
		if ( ! is_array( $node ) ) {
			$output[] = $node;
			continue;
		}
		$types = (array) ( $node['@type'] ?? array() );
		if ( array_intersect( array( 'Product', 'Offer', 'ItemList' ), $types ) ) {
			continue;
		}
		if ( array_intersect( array( 'Article', 'BlogPosting', 'NewsArticle', 'BreadcrumbList', 'FAQPage' ), $types ) ) {
			continue;
		}
		if ( in_array( 'WebPage', $types, true ) ) {
			if ( $ready_nodes ) {
				continue;
			}
			unset( $node['about'], $node['mainEntity'], $node['potentialAction'], $node['breadcrumb'], $node['offers'], $node['offer'] );
		}
		$output[] = $node;
	}
	return array_values( array_merge( $output, $ready_nodes ) );
}
add_filter( 'wpseo_schema_graph', 'tra_vel_v2_merge_seo_opportunity_schema_graph', 30 );

/** Apply the fail-closed robots boundary to every reusable SEO page. */
function tra_vel_v2_seo_opportunity_robots_policy( $robots ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $robots;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $robots;
	}
	$contract = tra_vel_v2_is_managed_seo_opportunity( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false );
	if ( ! $contract['ready'] ) {
		$robots['noindex'] = true;
		$robots['follow'] = true;
		unset( $robots['index'], $robots['nofollow'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'tra_vel_v2_seo_opportunity_robots_policy', 30 );

/** Preserve noindex/follow when Yoast owns the robots meta output. */
function tra_vel_v2_seo_opportunity_yoast_robots_policy( $robots ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $robots;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $robots;
	}
	$contract = tra_vel_v2_is_managed_seo_opportunity( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false );
	if ( ! $contract['ready'] ) {
		$robots['index'] = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}
add_filter( 'wpseo_robots_array', 'tra_vel_v2_seo_opportunity_yoast_robots_policy', 30 );

/** Preserve the fail-closed robots gate when AIOSEO owns the robots output. */
function tra_vel_v2_seo_opportunity_aioseo_robots_policy( $attributes ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $attributes;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $attributes;
	}
	$contract = tra_vel_v2_is_managed_seo_opportunity( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false );
	if ( $contract['ready'] ) {
		return $attributes;
	}
	$output = (array) $attributes;
	foreach ( (array) $attributes as $key => $value ) {
		$directive = is_string( $key ) ? strtolower( $key ) : strtolower( (string) $value );
		if ( in_array( $directive, array( 'index', 'nofollow' ), true ) ) {
			unset( $output[ $key ] );
		}
	}
	$output['noindex'] = 'noindex';
	$output['nofollow'] = '';
	return $output;
}
add_filter( 'aioseo_robots_meta', 'tra_vel_v2_seo_opportunity_aioseo_robots_policy', 30 );

/** Turn an occupied managed canonical with invalid ownership into a local 404. */
function tra_vel_v2_protect_seo_opportunity_route_identity() {
	if ( is_admin() || ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) || ( tra_vel_v2_is_managed_seo_opportunity( $entry ) && tra_vel_v2_seo_opportunity_identity_matches( $post_id, $entry ) ) ) {
		return;
	}
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'tra_vel_v2_protect_seo_opportunity_route_identity', 0 );

/** Return the registry canonical only when the current URL matches it exactly. */
function tra_vel_v2_seo_opportunity_canonical_url( $canonical, $post = null ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $canonical;
	}
	$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $canonical;
	}
	if ( ! tra_vel_v2_is_managed_seo_opportunity( $entry ) || ! tra_vel_v2_seo_opportunity_identity_matches( $post_id, $entry ) ) {
		return '';
	}
	return home_url( $entry['canonicalPath'] );
}
add_filter( 'get_canonical_url', 'tra_vel_v2_seo_opportunity_canonical_url', 20, 2 );
add_filter(
	'wpseo_canonical',
	static function ( $canonical ) {
		return tra_vel_v2_seo_opportunity_canonical_url( $canonical );
	},
	20
);
add_filter(
	'aioseo_canonical_url',
	static function ( $canonical ) {
		return tra_vel_v2_seo_opportunity_canonical_url( $canonical );
	},
	20
);

/** Keep Yoast's breadcrumb graph aligned with the registry semantic tree. */
function tra_vel_v2_seo_opportunity_yoast_breadcrumbs( $links ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $links;
	}
	$entry = tra_vel_v2_get_current_seo_opportunity();
	if ( ! tra_vel_v2_is_exposable_seo_opportunity( $entry ) ) {
		return $links;
	}
	return array_map(
		static function ( $item ) {
			return array(
				'url'  => $item['url'],
				'text' => $item['name'],
			);
		},
		tra_vel_v2_seo_opportunity_breadcrumb_items( $entry )
	);
}
add_filter( 'wpseo_breadcrumb_links', 'tra_vel_v2_seo_opportunity_yoast_breadcrumbs', 20 );

/** Keep unready opportunity pages schema-light under Yoast. */
function tra_vel_v2_gate_yoast_seo_opportunity_schema( $data ) {
	if ( ! tra_vel_v2_is_seo_opportunity_page_request() ) {
		return $data;
	}
	$post_id = (int) get_queried_object_id();
	$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
	if ( ! tra_vel_v2_is_seo_opportunity_protection_candidate( $post_id, $entry ) ) {
		return $data;
	}
	$contract = tra_vel_v2_is_managed_seo_opportunity( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false );
	if ( ! $contract['ready'] ) {
		unset( $data['about'], $data['mainEntity'], $data['potentialAction'] );
		return $data;
	}
	$data['@type'] = 'WebPage';
	$data['url'] = home_url( $entry['canonicalPath'] );
	$data['name'] = $entry['primaryIntent'];
	$data['inLanguage'] = 'he-IL';
	return $data;
}
add_filter( 'wpseo_schema_webpage', 'tra_vel_v2_gate_yoast_seo_opportunity_schema', 20 );

/** Remove rich content and commercial claims from unready AIOSEO graph nodes. */
function tra_vel_v2_gate_aioseo_seo_opportunity_schema( $schema ) {
	return tra_vel_v2_merge_seo_opportunity_schema_graph( $schema );
}
add_filter( 'aioseo_schema_output', 'tra_vel_v2_gate_aioseo_seo_opportunity_schema', 20 );

/** Return managed opportunity page IDs that fail the complete publication contract. */
function tra_vel_v2_unready_seo_opportunity_page_ids() {
	static $excluded_cache = null;
	if ( null !== $excluded_cache ) {
		return $excluded_cache;
	}

	$registry = tra_vel_v2_load_seo_opportunity_registry();
	$candidates = array_map(
		'intval',
		(array) get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array( 'key' => '_tra_vel_seo_opportunity_id', 'compare' => 'EXISTS' ),
					array( 'key' => '_wp_page_template', 'value' => 'page-seo-opportunity.php' ),
				),
			)
		)
	);
	if ( ! empty( $registry['valid'] ) ) {
		foreach ( $registry['entries'] as $entry ) {
			if ( ! tra_vel_v2_is_managed_seo_opportunity( $entry ) ) {
				continue;
			}
			$page = get_page_by_path( trim( (string) $entry['canonicalPath'], '/' ), OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'publish' === get_post_status( $page ) ) {
				$candidates[] = (int) $page->ID;
			}
		}
	}

	$excluded = array();
	foreach ( array_unique( array_filter( $candidates ) ) as $post_id ) {
		$entry = tra_vel_v2_get_protected_seo_opportunity( $post_id );
		$contract = is_array( $entry ) ? tra_vel_v2_get_seo_opportunity_publication_contract( $post_id, $entry ) : array( 'ready' => false );
		if ( empty( $contract['ready'] ) ) {
			$excluded[] = (int) $post_id;
		}
	}
	$excluded_cache = array_values( array_unique( $excluded ) );
	return $excluded_cache;
}

/** Keep unready managed pages out of WordPress core page sitemaps. */
function tra_vel_v2_exclude_unready_seo_opportunities_from_core_sitemap( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}
	$args['post__not_in'] = array_values(
		array_unique(
			array_merge( array_map( 'intval', (array) ( $args['post__not_in'] ?? array() ) ), tra_vel_v2_unready_seo_opportunity_page_ids() )
		)
	);
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'tra_vel_v2_exclude_unready_seo_opportunities_from_core_sitemap', 20, 2 );

/** Keep unready managed pages out of Yoast and AIOSEO sitemaps. */
function tra_vel_v2_exclude_unready_seo_opportunities_from_plugin_sitemaps( $ids ) {
	return array_values(
		array_unique(
			array_merge( array_map( 'intval', (array) $ids ), tra_vel_v2_unready_seo_opportunity_page_ids() )
		)
	);
}
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'tra_vel_v2_exclude_unready_seo_opportunities_from_plugin_sitemaps', 20 );
add_filter( 'aioseo_sitemap_exclude_posts', 'tra_vel_v2_exclude_unready_seo_opportunities_from_plugin_sitemaps', 20 );

/** Contextual Earth URL for one exact registry owner. */
function tra_vel_v2_seo_opportunity_map_url( $entry ) {
	$map_state = is_array( $entry ) ? sanitize_key( (string) ( $entry['mapState'] ?? '' ) ) : '';
	return $map_state ? add_query_arg( 'destination', $map_state, home_url( '/travel-map/' ) ) : home_url( '/travel-map/' );
}

/** Contextual commercial or planning action for one exact owner. */
function tra_vel_v2_seo_opportunity_action_url( $entry ) {
	if ( ! is_array( $entry ) ) {
		return home_url( '/ai-planner/' );
	}
	$map_state = sanitize_key( (string) ( $entry['mapState'] ?? '' ) );
	$airport = tra_vel_v2_seo_opportunity_airport_code( $map_state );
	$products = (array) ( $entry['monetization'] ?? array() );
	$segments = array_values( array_filter( explode( '/', trim( (string) ( $entry['canonicalPath'] ?? '' ), '/' ) ) ) );
	$canonical_vertical = $segments[0] ?? '';
	$planning_scope = $products;
	if ( 'transactional-cluster' === ( $entry['pageType'] ?? '' ) && in_array( $canonical_vertical, array( 'flights', 'hotels', 'packages' ), true ) ) {
		$planning_scope = array_values( array_unique( array_merge( array( $canonical_vertical ), $products ) ) );
	}
	if ( 'transactional-cluster' === ( $entry['pageType'] ?? '' ) && in_array( $canonical_vertical, array( 'flights', 'hotels', 'packages' ), true ) && $airport ) {
		return add_query_arg( 'destination', $airport, home_url( '/' . $canonical_vertical . '/' ) );
	}
	$primary_product = $products[0] ?? '';
	if ( in_array( $primary_product, array( 'flights', 'hotels', 'packages' ), true ) && $airport ) {
		return add_query_arg( 'destination', $airport, home_url( '/' . $primary_product . '/' ) );
	}
	if ( 'insurance' === $primary_product && $map_state ) {
		return add_query_arg( 'trip_destination', $map_state, home_url( '/travel-insurance/' ) );
	}
	return add_query_arg(
		array_filter(
			array(
				'destination' => $map_state,
				'scope'       => implode( ',', array_slice( $planning_scope, 0, 8 ) ),
			)
		),
		home_url( '/ai-planner/' )
	);
}
