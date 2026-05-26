<?php
/**
 * Header template.
 */
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell">
    <header class="site-header">
        <div class="container header-inner">
            <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                <span class="logo-mark" aria-hidden="true">T</span>
                <span><?php bloginfo('name'); ?></span>
            </a>
            <nav class="nav" aria-label="<?php esc_attr_e('Primary navigation', 'travel-revenue'); ?>">
                <a href="#money">חבילות ויעדים</a>
                <a href="#services">שירותי נסיעה</a>
                <a href="#lead">בניית טיול</a>
            </nav>
            <a class="header-cta" href="#lead">בקשת הצעה</a>
        </div>
    </header>
