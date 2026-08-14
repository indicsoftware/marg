/**
 * Social Auth Handler (Google + Apple)
 *
 * Opens a popup window for OAuth, listens for postMessage callback,
 * and triggers success handler on the parent page.
 */

import { authAjax, showMessage, hideMessage, getRedirectUrl } from './utils';

/**
 * Initialize social auth button handlers.
 *
 * @param {Function} onSuccess Callback after successful social auth.
 */
export function initSocialAuth( onSuccess ) {
	const $googleBtn = jQuery( '.marg-auth-social__btn--google' );
	const $appleBtn  = jQuery( '.marg-auth-social__btn--apple' );

	// Listen for postMessage from the OAuth popup.
	window.addEventListener( 'message', function ( event ) {
		// Verify origin matches our site.
		if ( event.origin !== window.location.origin ) {
			return;
		}

		const data = event.data;

		if ( ! data || data.type !== 'marg-auth-callback' ) {
			return;
		}

		if ( data.success ) {
			showMessage( 'Login successful!', 'success' );
			onSuccess( {
				message: 'Login successful!',
				redirect_url: data.redirect_url || getRedirectUrl(),
			} );
		} else {
			showMessage( data.error || 'Authentication failed. Please try again.' );
		}

		// Re-enable buttons.
		$googleBtn.prop( 'disabled', false );
		$appleBtn.prop( 'disabled', false );
	} );

	// Google button.
	$googleBtn.on( 'click', function () {
		if ( jQuery( this ).prop( 'disabled' ) ) {
			return;
		}
		hideMessage();
		openSocialAuth( 'google', $googleBtn );
	} );

	// Apple button.
	$appleBtn.on( 'click', function () {
		if ( jQuery( this ).prop( 'disabled' ) ) {
			return;
		}
		hideMessage();
		openSocialAuth( 'apple', $appleBtn );
	} );
}

/**
 * Open a popup window for the given OAuth provider.
 *
 * @param {string} provider 'google' or 'apple'.
 * @param {jQuery} $btn     The button element (to disable during loading).
 */
function openSocialAuth( provider, $btn ) {
	$btn.prop( 'disabled', true );

	authAjax( 'marg_social_auth_url', {
		provider,
		redirect_url: getRedirectUrl(),
	} )
		.then( ( data ) => {
			if ( ! data.auth_url ) {
				showMessage( 'Could not start authentication.' );
				$btn.prop( 'disabled', false );
				return;
			}

			// Open popup centered on screen.
			const width  = 500;
			const height = 600;
			const left   = ( window.screen.width - width ) / 2;
			const top    = ( window.screen.height - height ) / 2;

			const popup = window.open(
				data.auth_url,
				'marg_social_auth',
				`width=${ width },height=${ height },left=${ left },top=${ top },toolbar=no,menubar=no,location=yes`
			);

			if ( ! popup ) {
				showMessage( 'Popup was blocked. Please allow popups for this site.' );
				$btn.prop( 'disabled', false );
				return;
			}

			// Poll to detect if popup was closed without completing auth.
			const pollTimer = setInterval( () => {
				if ( popup.closed ) {
					clearInterval( pollTimer );
					$btn.prop( 'disabled', false );
				}
			}, 500 );
		} )
		.catch( ( err ) => {
			showMessage( err.message || 'Could not start authentication.' );
			$btn.prop( 'disabled', false );
		} );
}
