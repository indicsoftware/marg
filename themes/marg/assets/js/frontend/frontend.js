/**
 * Front-end entry.
 *
 * tailwind.css is the only front-end stylesheet (Tailwind CSS v4, compiled by
 * @tailwindcss/postcss; see postcss.config.js). The legacy scaffold
 * stylesheet css/frontend/style.css is no longer imported.
 */
import '../../css/frontend/tailwind.css';

import { initProductActions } from './components/product-actions';

initProductActions();
