<?php
/**
 * The template for displaying search results pages.
 *
 * @package TenUpTheme
 */

get_header(); ?>

	<section itemscope itemtype="https://schema.org/SearchResultsPage">
		<?php if ( have_posts() ) : ?>
			<h1>
				<?php
				/* translators: the search query */
				printf( esc_html__( 'Search Results for: %s', 'tenup-theme' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
				?>
			</h1>

			<ul role="list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<li itemscope itemtype="https://schema.org/Thing">
					<?php
					if ( has_post_thumbnail() ) {
						the_post_thumbnail();
					}

					// @phpstan-ignore argument.type (s)
					the_title( '<h2 class="inline" itemprop="name"><a href="' . esc_url( get_permalink() ) . '" itemprop="url">', '</a></h2>' );
					?>
					<div itemprop="description">
						<?php the_excerpt(); ?>
					</div>
				</li>

			<?php endwhile; ?>
			</ul>

			<?php the_posts_navigation(); ?>
		<?php else : ?>
			<?php // No results: an invisible heading and message for screen readers (the page is otherwise empty). ?>
			<h1 class="sr-only">
				<?php
				/* translators: the search query */
				printf( esc_html__( 'No results for: %s', 'tenup-theme' ), esc_html( get_search_query() ) );
				?>
			</h1>
			<p class="sr-only"><?php esc_html_e( 'No issues or articles matched your search.', 'tenup-theme' ); ?></p>
		<?php endif; ?>
	</section>

<?php
get_footer();
