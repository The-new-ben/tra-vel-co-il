<?php
/**
 * Minimal structured data that complements, rather than replaces, an SEO plugin.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tra_vel_v2_schema_graph() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type' => 'WebSite',
				'@id'   => home_url( '/#website' ),
				'url'   => home_url( '/' ),
				'name'  => get_bloginfo( 'name' ),
				'inLanguage' => 'he-IL',
			),
			array(
				'@type' => 'Organization',
				'@id'   => home_url( '/#organization' ),
				'name'  => 'Tra-Vel',
				'url'   => home_url( '/' ),
			),
		),
	);

	if ( is_singular() ) {
		$graph['@graph'][] = array(
			'@type'            => 'Article',
			'@id'              => get_permalink() . '#article',
			'headline'         => get_the_title(),
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'inLanguage'       => 'he-IL',
			'mainEntityOfPage' => get_permalink(),
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}
add_action( 'wp_head', 'tra_vel_v2_schema_graph', 20 );
