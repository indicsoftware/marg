/**
 * Email + Password Registration Handler
 */

import { authAjax, showMessage, hideMessage, setLoading, isValidEmail, getRedirectUrl } from './utils';

/**
 * Initialize the email registration form handler.
 *
 * @param {Function} onSuccess Callback after successful registration.
 */
export function initEmailRegister( onSuccess ) {
	const $form = jQuery( '#marg-register-form' );

	$form.on( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage();

		const name = $form.find( 'input[name="name"]' ).val().trim();
		const email = $form.find( 'input[name="email"]' ).val().trim();
		const password = $form.find( 'input[name="password"]' ).val();

		// Client-side validation.
		if ( ! name || ! email || ! password ) {
			showMessage( 'Please fill in all fields.' );
			return;
		}

		if ( ! isValidEmail( email ) ) {
			showMessage( 'Please enter a valid email address.' );
			return;
		}

		if ( password.length < 8 ) {
			showMessage( 'Password must be at least 8 characters.' );
			return;
		}

		setLoading( $form, true );

		authAjax( 'marg_email_register', {
			name,
			email,
			password,
			redirect_url: getRedirectUrl(),
		} )
			.then( ( data ) => {
				showMessage( data.message || 'Account created!', 'success' );
				onSuccess( data );
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Registration failed. Please try again.' );
			} )
			.finally( () => {
				setLoading( $form, false );
			} );
	} );
}
