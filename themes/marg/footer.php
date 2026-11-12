<?php
/**
 * The template for displaying the footer.
 *
 * Minimal placeholder (no footer is designed in Figma yet): a 1px marg-red
 * top rule with the site name.
 *
 * @package TenUpTheme
 */

?>
		</main>

		<footer class="site-footer mt-3xlarge border-t border-marg-red px-gutter py-medium tablet-lg:px-[calc(100%/24)]" role="contentinfo">
			<p class="text-trim font-sans text-xsmall font-bold uppercase">
				&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
			</p>
		</footer>

		<?php wp_footer(); ?>
	</body>
</html>
