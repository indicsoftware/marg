/**
 * Magic Link (Passwordless Email) Handler
 */

import { authAjax, showMessage, hideMessage, setLoading, isValidEmail, getRedirectUrl } from './utils';

/**
 * Initialize the magic link form handler.
 */
export function initMagicLink() {
	const $form = jQuery( '#marg-magic-link-form' );
	const $success = jQuery( '#marg-magic-link-success' );

	$form.on( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage();

		const email = $form.find( 'input[name="email"]' ).val().trim();

		if ( ! email ) {
			showMessage( 'Please enter your email address.' );
			return;
		}

		if ( ! isValidEmail( email ) ) {
			showMessage( 'Please enter a valid email address.' );
			return;
		}

		setLoading( $form, true );

		authAjax( 'marg_magic_link_send', {
			email,
			redirect_url: getRedirectUrl(),
		} )
			.then( ( data ) => {
				// Show success state, hide form.
				$form.hide();
				$success.find( '.marg-sent-email' ).text( email );
				$success.show();
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Could not send the magic link. Please try again.' );
			} )
			.finally( () => {
				setLoading( $form, false );
			} );
	} );
}

/**
 * Reset the magic link pane back to its initial form state.
 * Called when switching away and back to this pane.
 */
export function resetMagicLinkPane() {
	jQuery( '#marg-magic-link-form' ).show().find( 'input' ).val( '' );
	jQuery( '#marg-magic-link-success' ).hide();
}
