<?php
/**
 * Theme setup and WordPress integration.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tra_vel_v2_setup() {
	load_theme_textdomain( 'tra-vel-v2', TRA_VEL_V2_PATH . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'tra-vel-v2' ),
			'footer'  => __( 'Footer navigation', 'tra-vel-v2' ),
			'mobile'  => __( 'Mobile navigation', 'tra-vel-v2' ),
		)
	);

	add_image_size( 'tra-vel-card', 720, 480, true );
	add_image_size( 'tra-vel-hero', 1920, 1080, true );
	add_image_size( 'tra-vel-map-popover', 640, 420, true );
}
add_action( 'after_setup_theme', 'tra_vel_v2_setup' );

function tra_vel_v2_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'tra_vel_v2_content_width', 820 );
}
add_action( 'after_setup_theme', 'tra_vel_v2_content_width', 0 );

function tra_vel_v2_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'tra-vel-front-page';
	}
	if ( is_page_template( 'page-map.php' ) ) {
		$classes[] = 'map-body';
	}
	if ( is_page_template( 'page-destination.php' ) || is_singular( 'destination' ) ) {
		$classes[] = 'tra-vel-destination';
	}
	return $classes;
}
add_filter( 'body_class', 'tra_vel_v2_body_classes' );
