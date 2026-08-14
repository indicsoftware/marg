/**
 * Login Wall Display Logic
 *
 * Shows a "You need an account" overlay before opening the auth modal.
 */

const CONTEXT_MESSAGES = {
	pdf: 'Sign up or log in to view and download PDFs.',
	collect: 'Sign up or log in to save items to your collection.',
	purchase: 'Sign up or log in for a faster checkout experience.',
	default: 'Sign up or log in to access this content.',
};

/**
 * Initialize the login wall component.
 *
 * @param {Function} openAuthModal Callback to open the auth modal.
 */
export function initLoginWall( openAuthModal ) {
	const $wall = jQuery( '#marg-login-wall' );

	if ( ! $wall.length ) {
		return;
	}

	// Close wall on overlay click or close button.
	$wall.find( '.marg-login-wall__overlay, .marg-login-wall__close' ).on( 'click', function () {
		hideLoginWall();
	} );

	// CTA button opens the auth modal.
	$wall.find( '.marg-login-wall__cta' ).on( 'click', function () {
		hideLoginWall();
		openAuthModal( 'register' );
	} );

	// Close on ESC.
	jQuery( document ).on( 'keydown.margLoginWall', function ( e ) {
		if ( e.key === 'Escape' && $wall.is( ':visible' ) ) {
			hideLoginWall();
		}
	} );
}

/**
 * Show the login wall with a context-specific message.
 *
 * @param {string} context One of: 'pdf', 'collect', 'purchase', 'default'.
 */
export function showLoginWall( context = 'default' ) {
	if ( window.margAuth.isLoggedIn ) {
		return;
	}

	const $wall = jQuery( '#marg-login-wall' );
	const message = CONTEXT_MESSAGES[ context ] || CONTEXT_MESSAGES.default;

	$wall.find( '#marg-login-wall-message' ).text( message );
	$wall.fadeIn( 200 );
	document.body.style.overflow = 'hidden';

	// Store the context for pending-action replay after login.
	if ( context !== 'default' ) {
		try {
			sessionStorage.setItem( 'marg_login_context', context );
		} catch ( e ) {
			// sessionStorage unavailable — ignore.
		}
	}
}

/**
 * Hide the login wall.
 */
export function hideLoginWall() {
	jQuery( '#marg-login-wall' ).fadeOut( 200 );
	document.body.style.overflow = '';
}
