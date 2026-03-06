/**
 * OTP (Mobile + SMS) Handler
 *
 * Two-step form: phone number input → OTP verification.
 */

import { authAjax, showMessage, hideMessage, setLoading, getRedirectUrl } from './utils';

/**
 * Initialize the OTP form handlers.
 *
 * @param {Function} onSuccess Callback after successful OTP verification.
 */
export function initOTP( onSuccess ) {
	const $sendForm   = jQuery( '#marg-otp-send-form' );
	const $verifyForm = jQuery( '#marg-otp-verify-form' );

	let currentPhone = '';

	// Step 1: Send OTP.
	$sendForm.on( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage();

		const phone = $sendForm.find( 'input[name="phone"]' ).val().trim();

		if ( ! phone ) {
			showMessage( 'Please enter your phone number.' );
			return;
		}

		if ( ! isValidPhone( phone ) ) {
			showMessage( 'Please enter a valid 10-digit phone number.' );
			return;
		}

		setLoading( $sendForm, true );
		currentPhone = phone;

		authAjax( 'marg_otp_send', { phone } )
			.then( () => {
				// Show verify form, hide send form.
				$sendForm.hide();
				$verifyForm.find( '.marg-sent-phone' ).text( formatPhone( phone ) );
				$verifyForm.show();
				$verifyForm.find( 'input[name="otp"]' ).val( '' ).trigger( 'focus' );
				showMessage( 'OTP sent to your phone.', 'success' );
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Could not send OTP. Please try again.' );
			} )
			.finally( () => {
				setLoading( $sendForm, false );
			} );
	} );

	// Step 2: Verify OTP.
	$verifyForm.on( 'submit', function ( e ) {
		e.preventDefault();
		hideMessage();

		const otp = $verifyForm.find( 'input[name="otp"]' ).val().trim();

		if ( ! otp || otp.length !== 6 ) {
			showMessage( 'Please enter the 6-digit OTP.' );
			return;
		}

		setLoading( $verifyForm, true );

		authAjax( 'marg_otp_verify', {
			phone: currentPhone,
			otp,
			redirect_url: getRedirectUrl(),
		} )
			.then( ( data ) => {
				showMessage( data.message || 'Login successful!', 'success' );
				onSuccess( data );
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Invalid OTP. Please try again.' );
			} )
			.finally( () => {
				setLoading( $verifyForm, false );
			} );
	} );

	// Resend OTP link inside verify form.
	$verifyForm.on( 'click', '.marg-otp-resend', function ( e ) {
		e.preventDefault();
		hideMessage();

		if ( ! currentPhone ) {
			return;
		}

		const $link = jQuery( this );
		$link.addClass( 'marg-otp-resend--disabled' );

		authAjax( 'marg_otp_send', { phone: currentPhone } )
			.then( () => {
				showMessage( 'New OTP sent.', 'success' );
			} )
			.catch( ( err ) => {
				showMessage( err.message || 'Could not resend OTP.' );
			} )
			.finally( () => {
				$link.removeClass( 'marg-otp-resend--disabled' );
			} );
	} );
}

/**
 * Reset the OTP pane back to the phone input step.
 */
export function resetOTPPane() {
	const $sendForm   = jQuery( '#marg-otp-send-form' );
	const $verifyForm = jQuery( '#marg-otp-verify-form' );

	$sendForm.show().find( 'input' ).val( '' );
	$verifyForm.hide().find( 'input' ).val( '' );
}

/**
 * Basic phone number validation (Indian numbers).
 *
 * @param {string} phone Raw phone input.
 * @return {boolean}
 */
function isValidPhone( phone ) {
	const cleaned = phone.replace( /[\s\-\(\)]/g, '' );
	// Accept: +91XXXXXXXXXX, 91XXXXXXXXXX, 0XXXXXXXXXX, XXXXXXXXXX, or other international.
	return /^(\+?91\d{10}|0\d{10}|\d{10}|\+\d{10,15})$/.test( cleaned );
}

/**
 * Format phone for display (mask middle digits).
 *
 * @param {string} phone Phone number.
 * @return {string}
 */
function formatPhone( phone ) {
	const cleaned = phone.replace( /[\s\-\(\)]/g, '' );
	if ( cleaned.length >= 10 ) {
		const last4 = cleaned.slice( -4 );
		return '****' + last4;
	}
	return phone;
}
