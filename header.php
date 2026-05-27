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
<a class="skip-link" href="#main">דלגו לתוכן המרכזי</a>
<div class="site-shell">
    <header class="site-header">
        <div class="container header-inner">
            <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                <span class="logo-mark" aria-hidden="true">T</span>
                <span><?php bloginfo('name'); ?></span>
            </a>
            <nav class="nav" aria-label="ניווט ראשי">
                <a href="<?php echo esc_url(home_url('/budapest-vacation/')); ?>">בודפשט</a>
                <a href="<?php echo esc_url(home_url('/prague-vacation/')); ?>">פראג</a>
                <a href="<?php echo esc_url(home_url('/vienna-vacation/')); ?>">וינה</a>
                <a href="<?php echo esc_url(home_url('/budapest-prague-vienna-trip/')); ?>">לב אירופה</a>
                <a href="<?php echo esc_url(home_url('/cheap-flights-europe/')); ?>">טיסות</a>
                <a href="<?php echo esc_url(home_url('/travel-insurance-europe/')); ?>">ביטוח</a>
            </nav>
            <a class="header-cta" href="<?php echo esc_url(home_url('/#inquiry')); ?>">קבלו הצעה</a>
        </div>
    </header>
