<?php
/**
 * The template for displaying the header.
 *
 * Figma headers (550:107 mobile, 550:105 tablet portrait, 550:111 tablet landscape,
 * 550:99 / 605:310 desktop) are intentionally blank for now: a transparent bar
 * (over the white page) with a 1px marg-red bottom rule. Height comes from the `h-header` token:
 * 60px below 1133px, 100px from 1133px (tablet-lg), 200px from 1280px (desktop).
 * The page's <h1> belongs to the template, not the header.
 *
 * @package TenUpTheme
 */

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

		<a href="#main" class="skip-to-content-link sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:bg-white focus:px-xsmall focus:py-3xsmall focus:text-small focus:font-bold focus:outline-1 focus:outline-marg-red"><?php esc_html_e( 'Skip to main content', 'tenup-theme' ); ?></a>

		<?php
		// Transparent (the page is white) and positioned above the content, so a
		// template can slide a band under it (magazine mobile cover, Figma 836:919).
		?>
		<header class="site-header relative z-10 h-header w-full border-b border-marg-red" role="banner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="sr-only focus:not-sr-only" rel="home"><?php bloginfo( 'name' ); ?></a>
		</header>

		<main id="main" tabindex="-1" class="block focus:outline-none">
