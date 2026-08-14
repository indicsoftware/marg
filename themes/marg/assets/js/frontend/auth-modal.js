/**
 * Auth Modal Entry Point
 *
 * Initializes the login/signup popup modal system.
 */

import '../../css/frontend/components/auth-modal.css';
import { AuthModal } from './components/auth-modal/index';

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		new AuthModal();
	} );
} )( jQuery );
