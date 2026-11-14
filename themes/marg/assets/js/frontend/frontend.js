/**
 * Front-end entry.
 *
 * tailwind.css is the only front-end stylesheet (Tailwind CSS v4, compiled by
 * @tailwindcss/postcss; see postcss.config.js). The legacy scaffold
 * stylesheet css/frontend/style.css is no longer imported.
 */
import '../../css/frontend/tailwind.css';

import { initProductActions } from './components/product-actions';
import { initSiteHeader } from './components/site-header';
import { initArchiveList } from './components/archive-list';
import { initArchiveViewToggle } from './components/archive-view-toggle';
import { initArchiveCovers } from './components/archive-covers';
import { initFooterReveal } from './components/footer-reveal';

initProductActions();
initSiteHeader();
initArchiveList();
initArchiveViewToggle();
initArchiveCovers();
initFooterReveal();
