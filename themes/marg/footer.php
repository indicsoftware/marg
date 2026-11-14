<?php
/**
 * The template for displaying the footer.
 *
 * Site-wide Figma footer-interim (partials/site/footer-interim.php): mobile
 * component 908:214 / 908:230 below 1133px, desktop component 922:6730 from
 * 1133px; "archive" variant on the magazine archive, "non-archive" elsewhere.
 * No top margin: the footer's own pt-jumbo (80 / 100) is the gap above it.
 *
 * Footer group (Figma 608:740 revision: "the 'more such' in every article and
 * mag page will slide from the bottom along with the footer"): after </main>,
 * one [data-footer-group] wrapper holds
 *  - the "before footer" slot: the `tenup_theme_before_footer` action. The
 *    article and magazine templates (partials/product/*.php) print their
 *    "More such" <aside> there, so it is a sibling of the footer, not part of
 *    <main>;
 *  - the site footer.
 * On article and magazine pages the wrapper also carries data-footer-reveal:
 * the inner [data-footer-group-body] slides up into place as the group scrolls
 * into view (CSS scroll-driven animation, view timeline on the wrapper, in
 * tailwind.css; IntersectionObserver fallback in
 * assets/js/frontend/components/footer-reveal.js). Without JS or support, and
 * under prefers-reduced-motion, the group is simply in place: nothing is hidden.
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductTemplates;
use function TenUpTheme\html_attrs;

/**
 * Filter whether the footer group slides up into place as it scrolls into view.
 *
 * @param bool $reveal Default true on article and magazine product pages.
 */
$marg_footer_reveal = (bool) apply_filters(
	'tenup_theme_footer_reveal',
	is_singular( 'product' ) && '' !== ProductTemplates::template_for( get_queried_object_id() )
);

?>
		</main>

		<div<?php echo html_attrs( [ 'class' => 'footer-group', 'data-footer-group' => true, 'data-footer-reveal' => $marg_footer_reveal ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html_attrs() escapes every value. ?>>
			<div class="footer-group-body" data-footer-group-body>
				<?php
				/**
				 * Fires inside the footer group, before the site footer.
				 *
				 * Content printed here moves with the footer (see data-footer-reveal above).
				 */
				do_action( 'tenup_theme_before_footer' );
				?>

				<?php get_template_part( 'partials/site/footer-interim' ); ?>
			</div>
		</div>

		<?php wp_footer(); ?>
	</body>
</html>
