<?php
/**
 * Component: separator/section (Figma 608:699 desktop, 565:959 mobile).
 *
 * A full-width 1px marg-red top rule with a section label. Figma fades the
 * whole element to 40% opacity, but 40% red text on white is ~2:1 contrast
 * (fails WCAG 1.4.3), so only the rule is faded; the label uses 90% red
 * (~4.7:1). Mobile: 4px top padding; desktop (>= tablet-lg): 16px.
 *
 * Usage:
 * get_template_part( 'partials/components/separator-section', null, [
 *     'label' => __( 'More Such', 'tenup-theme' ),
 * ] );
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type string $label   Required. Section label (plain text). Nothing renders when empty.
 *     @type string $tag     Heading element for the label. Default 'h2'. One of h2-h4, p, span.
 *     @type string $id      Optional id for the label (use with aria-labelledby on the section).
 *     @type string $class   Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;

$args  = wp_parse_args(
	$args ?? [],
	[
		'label' => '',
		'tag'   => 'h2',
		'id'    => '',
		'class' => '',
	]
);
$label = trim( (string) $args['label'] );

if ( '' === $label ) {
	return;
}

$tag = in_array( $args['tag'], [ 'h2', 'h3', 'h4', 'p', 'span' ], true ) ? $args['tag'] : 'h2';
?>
<div class="<?php echo esc_attr( class_names( 'flex w-full items-start border-t border-marg-red/40 pt-3xsmall text-marg-red/90 tablet-lg:pt-xsmall', $args['class'] ) ); ?>">
	<<?php echo tag_escape( $tag ); ?><?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> class="text-trim whitespace-nowrap font-sans text-regular font-regular">
		<?php echo esc_html( $label ); ?>
	</<?php echo tag_escape( $tag ); ?>>
</div>
