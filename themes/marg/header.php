<?php
/**
 * The template for displaying the header.
 *
 * Below 1133px (MOBILE token mode, 390 and 744): Figma "Header-interim / mobile"
 * (881:183; static variant 897:6278 on article 897:6340 and magazine 897:6296).
 * 44px tall (`h-header`), transparent, no bottom rule, sticky at the top (with JS only:
 * html.no-js keeps it in flow, as the fade and auto-hide need site-header.js). It hides
 * on scroll down and reveals on scroll up (assets/js/frontend/components/site-header.js
 * sets data-hidden / data-scrolled), never while it holds focus or near the top.
 * Hidden = translated up by 115px rather than -100% (44px), so the fade below
 * the bar leaves the viewport with it.
 * A centred context title comes from partials/site/header-context.php. A 115px
 * white fade (897:6351) travels with it: always on article pages, elsewhere only
 * once the page has scrolled, so the magazine cover band stays dark at the top
 * (on the magazine archive only while its sticky image strip is not under the bar).
 * tailwind.css reserves the bar (or the fade on articles) as scroll-padding-top, so
 * focused elements and anchor targets are not scrolled underneath it.
 *
 * From 1133px: unchanged Figma desktop headers (550:111, 550:99, 605:310): a blank
 * bar with a 1px marg-red bottom rule, 100px (1133) / 200px (1280+), in normal flow.
 *
 * The header stays in normal flow on mobile too (sticky, not fixed), so templates
 * can still slide a band under it with -mt-header (magazine cover band 838:1042).
 * The page's <h1> belongs to the template, not the header.
 *
 * @package TenUpTheme
 */

use TenUpTheme\SiteChrome;

$marg_header_fade = SiteChrome::header_fade();

// Fade classes: shown always / once scrolled; on the archive, hidden while its sticky stage sits under the bar.
$marg_fade_class = 'always' === $marg_header_fade ? 'opacity-100' : 'opacity-0 group-data-scrolled/header:opacity-100';
if ( SiteChrome::is_archive_page() ) {
	$marg_fade_class .= ' [@media(height>=520px)]:in-[.js]:hidden';
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'bg-white font-sans text-marg-red antialiased' ); ?>>
		<?php wp_body_open(); ?>

		<?php // Target of the footer's "Back to Archives" (#top) on the archive: takes focus, so keyboard users land back at the top too. ?>
		<div id="top" class="absolute top-0 left-0 size-px focus:outline-none" tabindex="-1"></div>

		<a href="#main" class="skip-to-content-link sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:bg-white focus:px-xsmall focus:py-3xsmall focus:text-small focus:font-bold focus:outline-1 focus:outline-marg-red"><?php esc_html_e( 'Skip to main content', 'tenup-theme' ); ?></a>

		<header
			id="site-header"
			class="site-header group/header relative top-0 z-30 h-header w-full transition-transform duration-200 ease-out focus-within:transition-none motion-reduce:transition-none max-tablet-lg:in-[.js]:sticky max-tablet-lg:data-hidden:not-focus-within:-translate-y-[115px] tablet-lg:border-b tablet-lg:border-marg-red"
			role="banner"
			data-site-header
			data-fade="<?php echo esc_attr( $marg_header_fade ); ?>"
			<?php echo esc_attr( SiteChrome::header_autohide() ? 'data-autohide' : '' ); ?>
		>
			<?php
			// White fade 897:6351: 115px, solid to 43.75% then transparent; under the title, above the page.
			if ( 'none' !== $marg_header_fade ) :
				?>
				<div
					class="<?php echo esc_attr( 'pointer-events-none absolute inset-x-0 top-0 h-[115px] bg-[linear-gradient(to_bottom,#fff_43.75%,rgba(255,255,255,0)_100%)] transition-opacity duration-200 motion-reduce:transition-none tablet-lg:hidden ' . $marg_fade_class ); ?>"
					aria-hidden="true"
				></div>
			<?php endif; ?>

			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sr-only focus:not-sr-only focus:absolute focus:left-gutter focus:top-full focus:bg-white focus:px-3xsmall focus:py-micro focus:text-small focus:outline-1 focus:outline-marg-red tablet-lg:focus:left-[calc(100%/24)] tablet-lg:focus:top-1/2 tablet-lg:focus:-translate-y-1/2" rel="home"><?php bloginfo( 'name' ); ?></a>

			<?php get_template_part( 'partials/site/header-context' ); ?>
		</header>

		<main id="main" tabindex="-1" class="block focus:outline-none">
