/**
 * Product actions: share / copy link / cite toggles rendered by
 * partials/components/link-action.php (data-marg-action="share|copy|cite").
 *
 * - share: Web Share API with a clipboard fallback. Uses data-marg-url, then
 *   data-marg-title, falling back to the current page.
 * - copy:  copies data-marg-copy (or data-marg-url, or location.href).
 * - cite:  toggles the element referenced by aria-controls (hidden attribute)
 *          and keeps aria-expanded in sync.
 *
 * Copy feedback: the element's [data-marg-label] text switches to
 * data-marg-copied-label (default "copied") for two seconds and data-state="copied"
 * is set, so templates can style it with data-[state=copied]:... utilities.
 */

const COPIED_MS = 2000;

/**
 * Copy text to the clipboard.
 *
 * @param {string} text Text to copy.
 * @returns {Promise<boolean>} Whether the copy succeeded.
 */
async function copyText(text) {
	try {
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(text);
			return true;
		}
	} catch (e) {
		// Fall through to the legacy path.
	}

	const textarea = document.createElement('textarea');
	textarea.value = text;
	textarea.setAttribute('readonly', '');
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild(textarea);
	textarea.select();
	let ok = false;
	try {
		ok = document.execCommand('copy');
	} catch (e) {
		ok = false;
	}
	textarea.remove();
	return ok;
}

/**
 * Show temporary "copied" feedback on a trigger.
 *
 * @param {HTMLElement} el Trigger element.
 */
function flagCopied(el) {
	const label = el.querySelector('[data-marg-label]');
	const copiedLabel = el.dataset.margCopiedLabel || 'copied';
	if (label && !label.dataset.margOriginal) {
		label.dataset.margOriginal = label.textContent;
	}
	if (label) {
		label.textContent = copiedLabel;
	}
	el.dataset.state = 'copied';
	window.clearTimeout(el.margCopiedTimer);
	el.margCopiedTimer = window.setTimeout(() => {
		if (label && label.dataset.margOriginal) {
			label.textContent = label.dataset.margOriginal;
		}
		delete el.dataset.state;
	}, COPIED_MS);
}

/**
 * Handle a click on an action trigger.
 *
 * @param {HTMLElement} el Trigger element.
 */
async function handle(el) {
	const action = el.dataset.margAction;
	const url = el.dataset.margUrl || window.location.href;

	if (action === 'share') {
		if (navigator.share) {
			try {
				await navigator.share({ title: el.dataset.margTitle || document.title, url });
			} catch (e) {
				// User cancelled: nothing to do.
			}
			return;
		}
		if (await copyText(url)) {
			flagCopied(el);
		}
		return;
	}

	if (action === 'copy') {
		if (await copyText(el.dataset.margCopy || url)) {
			flagCopied(el);
		}
		return;
	}

	if (action === 'cite') {
		const id = el.getAttribute('aria-controls');
		const panel = id ? document.getElementById(id) : null;
		if (!panel) {
			return;
		}
		const open = el.getAttribute('aria-expanded') !== 'true';
		el.setAttribute('aria-expanded', open ? 'true' : 'false');
		panel.hidden = !open;
	}
}

/**
 * Bind delegated click handling once.
 */
export function initProductActions() {
	document.addEventListener('click', (event) => {
		const el = event.target.closest(
			'[data-marg-action="share"], [data-marg-action="copy"], [data-marg-action="cite"]',
		);
		if (!el || el.getAttribute('aria-disabled') === 'true') {
			return;
		}
		if (el.tagName === 'A') {
			event.preventDefault();
		}
		handle(el);
	});
}
