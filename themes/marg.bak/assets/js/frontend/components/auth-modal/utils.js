/**
 * Auth Modal Utilities
 *
 * Shared AJAX wrapper, validation helpers, and message display.
 */

/**
 * Make an AJAX POST request to the auth endpoints.
 *
 * @param {string} action  The WordPress AJAX action name.
 * @param {Object} data    Form data to send.
 * @return {Promise<Object>} Resolved with the response data.
 */
export function authAjax( action, data = {} ) {
	return new Promise( ( resolve, reject ) => {
		jQuery.ajax( {
			url: window.margAuth.ajaxUrl,
			type: 'POST',
			data: {
				action,
				nonce: window.margAuth.nonce,
				...data,
			},
			success( response ) {
				if ( response.success ) {
					resolve( response.data );
				} else {
					reject( response.data );
				}
			},
			error() {
				reject( { message: 'Something went wrong. Please try again.' } );
			},
		} );
	} );
}

/**
 * Show a message in the auth modal message area.
 *
 * @param {string} text    Message text.
 * @param {string} type    'error' or 'success'.
 */
export function showMessage( text, type = 'error' ) {
	const $msg = jQuery( '#marg-auth-message' );
	$msg.removeClass( 'marg-auth-message--error marg-auth-message--success' )
		.addClass( `marg-auth-message--${ type }` )
		.text( text )
		.show();
}

/**
 * Hide the auth modal message area.
 */
export function hideMessage() {
	jQuery( '#marg-auth-message' ).hide().text( '' );
}

/**
 * Set a form's submit button to loading state.
 *
 * @param {jQuery} $form  The form element.
 * @param {boolean} loading  Whether to show loading.
 */
export function setLoading( $form, loading ) {
	const $btn = $form.find( '.marg-auth-submit' );
	if ( loading ) {
		$btn.prop( 'disabled', true ).addClass( 'marg-auth-submit--loading' );
	} else {
		$btn.prop( 'disabled', false ).removeClass( 'marg-auth-submit--loading' );
	}
}

/**
 * Basic email validation.
 *
 * @param {string} email Email to validate.
 * @return {boolean}
 */
export function isValidEmail( email ) {
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
}

/**
 * Get the current page URL for redirect-after-login.
 *
 * @return {string}
 */
export function getRedirectUrl() {
	return window.location.href;
}
