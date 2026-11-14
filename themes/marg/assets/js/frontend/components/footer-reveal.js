/**
 * Footer reveal: "More such" and the site footer slide up into place together
 * (Figma 608:740 revision: "the 'more such' in every article and mag page will
 * slide from the bottom along with the footer").
 *
 * Markup (footer.php): [data-footer-group][data-footer-reveal] >
 * [data-footer-group-body], holding the related <aside> (when the page has one)
 * and the site footer. Only article and magazine pages carry data-footer-reveal.
 *
 * Where the browser supports scroll-driven animations, CSS does the work
 * (tailwind.css: view timeline on the group, translate on the body, gated on
 * prefers-reduced-motion: no-preference) and this script does nothing.
 * Elsewhere this is the fallback: once the group is about to enter the viewport,
 * the body starts offset downwards (the group clips the overflow, so the page
 * height and layout do not change) and transitions into place when the group
 * scrolls into view. It runs once.
 *
 * Never hidden by default: without JS, without IntersectionObserver or
 * overflow: clip, under prefers-reduced-motion, or when the group is already in
 * view on load, nothing is offset. Keyboard focus inside the group, or a switch
 * to reduced motion, puts it in place at once.
 */

/** Offset the body starts at; the CSS custom property wins when it is set. */
const DEFAULT_OFFSET = 'clamp(96px, 20vh, 200px)';

/** Fallback transition into place. */
const TRANSITION = 'transform 700ms cubic-bezier(0.2, 0.7, 0.2, 1)';

/**
 * Bind the fallback once.
 */
export function initFooterReveal() {
	const group = document.querySelector('[data-footer-reveal]');
	const body = group ? group.querySelector('[data-footer-group-body]') : null;
	if (!group || !body) {
		return;
	}

	const supports = (decl) => !!(window.CSS && CSS.supports && CSS.supports(decl));

	// Native scroll-driven animation: handled in CSS.
	if (supports('animation-timeline: view()')) {
		return;
	}

	if (
		!('IntersectionObserver' in window) ||
		!('AbortController' in window) ||
		!window.matchMedia ||
		!supports('overflow-y: clip')
	) {
		return;
	}

	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
	if (reduce.matches) {
		return;
	}

	let offset = false;
	let done = false;
	const listeners = new AbortController();
	const observer = new IntersectionObserver(
		(entries) => {
			// eslint-disable-next-line no-use-before-define -- the handler only runs after setup.
			onIntersect(entries[entries.length - 1]);
		},
		// Reveal once the group's top is a little inside the viewport.
		{ rootMargin: '0px 0px -10% 0px' },
	);

	const cleanup = () => {
		done = true;
		observer.disconnect();
		listeners.abort();
	};

	/** Drop every inline style the fallback set. */
	const clearStyles = () => {
		body.style.transition = '';
		body.style.transform = '';
		body.style.willChange = '';
		group.style.overflowY = '';
	};

	/** Put the body in place without motion and stop. */
	const settle = () => {
		cleanup();
		clearStyles();
	};

	/** Slide the body into place, then drop the inline styles. */
	const reveal = () => {
		cleanup();
		body.addEventListener('transitionend', clearStyles, { once: true });
		body.style.transition = TRANSITION;
		body.style.transform = '';
	};

	const startOffset = () => {
		const custom = getComputedStyle(group)
			.getPropertyValue('--marg-footer-reveal-offset')
			.trim();
		group.style.overflowY = 'clip';
		body.style.willChange = 'transform';
		body.style.transform = `translateY(${custom || DEFAULT_OFFSET})`;
		offset = true;
	};

	const onIntersect = (entry) => {
		if (done || !entry) {
			return;
		}

		if (!offset) {
			// First report: offset only a group still entirely below the viewport.
			if (entry.boundingClientRect.top < window.innerHeight) {
				cleanup();
				return;
			}
			startOffset();
			return;
		}

		if (entry.isIntersecting) {
			reveal();
		}
	};

	observer.observe(group);
	group.addEventListener('focusin', settle, { signal: listeners.signal });
	if (reduce.addEventListener) {
		reduce.addEventListener('change', settle, { signal: listeners.signal });
	}
}
