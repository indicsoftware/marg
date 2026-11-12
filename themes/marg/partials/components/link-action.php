<?php
/**
 * Component: link/action, checkbox square + label: share / copy link / CITE
 * (Figma 605:319, I836:567;296:359, I836:567;296:360; states on frame 266:77).
 *
 * 12px (mobile) / 24px (>= tablet-lg) empty bordered square (`size-link-icon`,
 * decorative) + Sans bold text-xsmall label, gap 3xsmall. Always a <button>.
 * Behaviour comes from assets/js/frontend/components/product-actions.js:
 *   share -> Web Share API, clipboard fallback (data-marg-url / data-marg-title)
 *   copy  -> copies data-marg-copy (or data-marg-url, or location.href); for ~2s the label
 *            reads "copied!" (Figma 269:285, announced via aria-live) and data-state="copied"
 *            is set. The square stays empty, as drawn. A visually hidden label
 *            (`hide_label`) is revealed while it says "copied!".
 *   cite  -> toggles the element whose id is `controls` (render it with the `hidden` attribute)
 * The Figma copy/hover state (269:288, blur(1px) + "copy link") is intentionally not built.
 *
 * Usage:
 * get_template_part( 'partials/components/link-action', null, [
 *     'action'   => 'cite',
 *     'label'    => __( 'Cite', 'tenup-theme' ),
 *     'uppercase'=> true,
 *     'controls' => 'marg-citation',
 * ] );
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type string $action       Required. 'share' | 'copy' | 'cite'.
 *     @type string $label        Visible label, rendered as given (Figma: "share", "copy link", "CITE").
 *                                Default: share / copy link / cite.
 *     @type bool   $uppercase    Add `uppercase` to the label (Figma uses it for CITE). Default false.
 *     @type bool   $hide_label   Visually hide the label (icon only; mobile magazine copy link). Default false.
 *     @type string $url          URL to share/copy. Default: current product permalink (get_permalink()).
 *     @type string $share_title  Title for navigator.share. Default: get_the_title().
 *     @type string $copy         Text to copy for 'copy' (overrides url), e.g. a DOI.
 *     @type string $copied_label Feedback label after copying. Default "copied!".
 *     @type string $controls     For 'cite': id of the citation panel (aria-controls).
 *     @type array  $attrs        Extra attributes. Escaped by html_attrs().
 *     @type string $class        Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;
use function TenUpTheme\html_attrs;

$args = wp_parse_args(
	$args ?? [],
	[
		'action'       => '',
		'label'        => '',
		'uppercase'    => false,
		'hide_label'   => false,
		'url'          => '',
		'share_title'  => '',
		'copy'         => '',
		'copied_label' => __( 'copied!', 'tenup-theme' ),
		'controls'     => '',
		'attrs'        => [],
		'class'        => '',
	]
);

$defaults = [
	'share' => __( 'share', 'tenup-theme' ),
	'copy'  => __( 'copy link', 'tenup-theme' ),
	'cite'  => __( 'cite', 'tenup-theme' ),
];

if ( ! isset( $defaults[ $args['action'] ] ) ) {
	return;
}

$label = '' !== trim( (string) $args['label'] ) ? $args['label'] : $defaults[ $args['action'] ];
$url   = '' !== $args['url'] ? $args['url'] : (string) get_permalink();

$attrs = array_merge(
	[
		'type'                   => 'button',
		'class'                  => class_names(
			'group inline-flex shrink-0 items-center gap-3xsmall text-marg-red',
			'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current',
			$args['class']
		),
		'data-marg-action'       => $args['action'],
		'data-marg-url'          => 'cite' !== $args['action'] ? esc_url_raw( $url ) : false,
		'data-marg-title'        => 'share' === $args['action'] ? ( '' !== $args['share_title'] ? $args['share_title'] : wp_strip_all_tags( get_the_title() ) ) : false,
		'data-marg-copy'         => 'copy' === $args['action'] ? $args['copy'] : false,
		'data-marg-copied-label' => 'cite' !== $args['action'] ? $args['copied_label'] : false,
		'aria-controls'          => 'cite' === $args['action'] ? $args['controls'] : false,
		'aria-expanded'          => ( 'cite' === $args['action'] && $args['controls'] ) ? 'false' : false,
	],
	(array) $args['attrs']
);
?>
<button<?php echo html_attrs( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html_attrs(). ?>>
	<span class="size-link-icon shrink-0 border border-marg-red group-aria-expanded:bg-marg-red" aria-hidden="true"></span>
	<span data-marg-label<?php echo 'cite' !== $args['action'] ? ' aria-live="polite"' : ''; // Only share/copy labels change ("copied!"); CITE is static. ?> class="<?php echo esc_attr( class_names( 'text-trim whitespace-nowrap font-sans text-xsmall font-bold', $args['uppercase'] ? 'uppercase group-data-[state=copied]:normal-case' : '', $args['hide_label'] ? 'sr-only group-data-[state=copied]:not-sr-only' : '' ) ); ?>"><?php echo esc_html( $label ); ?></span>
</button>
