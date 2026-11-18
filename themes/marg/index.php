<?php
/**
 * The main template file (fallback for everything without a specific template).
 *
 * @package TenUpTheme
 */

get_header(); ?>

	<div class="flex flex-col gap-xlarge px-gutter py-xlarge tablet-lg:px-[calc(100%/24)]">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'flex flex-col gap-medium' ); ?>>
					<?php if ( is_singular() ) : ?>
						<h1 class="font-sans text-xlarge font-regular"><?php the_title(); ?></h1>
					<?php else : ?>
						<h2 class="font-sans text-medium font-semibold"><a class="hover:underline" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php endif; ?>
					<div class="flex flex-col gap-xsmall font-sans text-regular [&_a]:underline">
						<?php the_content(); ?>
					</div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<?php // Nothing to show (404 included): an invisible heading and message, so <main> is not an empty landmark for screen readers. ?>
			<h1 class="sr-only"><?php echo esc_html( is_404() ? __( 'Page not found', 'tenup-theme' ) : __( 'Nothing found', 'tenup-theme' ) ); ?></h1>
			<p class="sr-only"><?php echo esc_html( is_404() ? __( 'This page does not exist. Use the Archives link to browse the magazine archive.', 'tenup-theme' ) : __( 'There is nothing to show here yet. Use the Archives link to browse the magazine archive.', 'tenup-theme' ) ); ?></p>
		<?php endif; ?>
	</div>

<?php
get_footer();
