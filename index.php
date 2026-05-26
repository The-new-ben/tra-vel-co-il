<?php
/**
 * Default template.
 */
get_header();
?>
<main id="main" class="site-main section">
    <div class="container">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('card'); ?>>
                    <h1><?php the_title(); ?></h1>
                    <?php the_content(); ?>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <article class="card">
                <h1><?php esc_html_e('No content found', 'travel-revenue'); ?></h1>
            </article>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
