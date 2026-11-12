/**
 * PostCSS configuration for the Marg theme.
 *
 * 10up-toolkit uses this file instead of its bundled config whenever a
 * project-level postcss.config.js exists. Two pipelines are needed:
 *
 * 1. The Tailwind CSS v4 entry (assets/css/frontend/tailwind.css) is compiled by
 *    @tailwindcss/postcss alone. Tailwind inlines its own @imports and lowers
 *    modern syntax with Lightning CSS, so it must NOT also go through
 *    postcss-import / postcss-preset-env (preset-env's cascade-layers polyfill
 *    would rewrite Tailwind's @layer output).
 * 2. Every other stylesheet (editor overrides, block CSS) keeps the stock
 *    10up-toolkit pipeline (postcss-import, global data, mixins, preset-env,
 *    cssnano in production).
 */
const path = require('path');
const toolkitConfig = require('10up-toolkit/config/postcss.config.js');

const TAILWIND_ENTRIES = [path.resolve(__dirname, 'assets/css/frontend/tailwind.css')];

module.exports = (api) => {
	const file = api && api.file ? path.resolve(api.file) : '';

	if (TAILWIND_ENTRIES.includes(file)) {
		const isProduction = api.env === 'production' || api.mode === 'production';

		return {
			plugins: {
				'@tailwindcss/postcss': {
					optimize: isProduction ? { minify: true } : false,
				},
			},
		};
	}

	return toolkitConfig(api);
};
