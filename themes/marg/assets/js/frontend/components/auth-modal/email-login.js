/**
 * Email + Password Login Handler
 */

import { authAjax, showMessage, hideMessage, setLoading, isValidEmail, getRedirectUrl } from './utils';

/**
 * Initialize the email login form handler.
 *
 * @param {Function} onSuccess Callback after successful login.
 */
export function initEmailLogin( onSuccess ) {
	const $form = jQuery( '#marg-login-form' );

	$form.on( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage();

		const email = $form.find( 'input[name="email"]' ).val().trim();
		const password = $form.find( 'input[name="password"]' ).val();

		// Client-side validation.
		if ( ! email || ! password ) {
			showMessage( 'Please enter both email and password.' );
			return;
		}

		if ( ! isValidEmail( email ) ) {
			showMessage( 'Please enter a valid email address.' );
			return;
		}

		setLoading( $form, true );

		authAjax( 'marg_email_login', {
			email,
			password,
			redirect_url: getRedirectUrl(),
		} )
			.then( ( data ) => {
				showMessage( data.message || 'Login successful!', 'success' );
				onSuccess( data );
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Invalid email or password.' );
			} )
			.finally( () => {
				setLoading( $form, false );
			} );
	} );
}
