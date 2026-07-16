<?php
/**
 * Fallback template.
 *
 * @package TraVelV2
 */

get_header();
?>
<main id="main-content" class="article-section page-width">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'article-prose' ); ?> id="post-<?php the_ID(); ?>">
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'לא נמצא תוכן.', 'tra-vel-v2' ); ?></p>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
