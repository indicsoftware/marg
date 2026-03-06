/**
 * Auth Modal Orchestrator
 *
 * Manages the login/signup popup modal: open/close, tab switching,
 * pane navigation, and post-login redirect.
 */

import { hideMessage } from './utils';
import { initEmailLogin } from './email-login';
import { initEmailRegister } from './email-register';
import { initMagicLink, resetMagicLinkPane } from './magic-link';
import { initOTP, resetOTPPane } from './otp';
import { initSocialAuth } from './social-auth';
import { initLoginWall, showLoginWall } from './login-wall';

export class AuthModal {
	constructor() {
		this.$modal = jQuery( '#marg-auth-modal' );

		if ( ! this.$modal.length ) {
			return;
		}

		this.redirectUrl = window.location.href;
		this.bindEvents();
		this.initForms();
		this.initLoginWallInterceptors();
		initLoginWall( ( tab ) => this.open( tab ) );
		this.exposeGlobalAPI();
		this.replayPendingAction();
	}

	/**
	 * Bind DOM events for the modal.
	 */
	bindEvents() {
		const self = this;

		// Open triggers: any element with data-marg-auth-trigger.
		jQuery( document ).on( 'click', '[data-marg-auth-trigger]', function ( e ) {
			e.preventDefault();
			const tab = jQuery( this ).data( 'marg-auth-trigger' ) || 'login';
			self.open( tab );
		} );

		// Close: overlay click and close button.
		this.$modal.find( '.marg-auth-modal__overlay, .marg-auth-modal__close' ).on( 'click', function () {
			self.close();
		} );

		// Close on ESC.
		jQuery( document ).on( 'keydown.margAuthModal', function ( e ) {
			if ( e.key === 'Escape' && self.$modal.is( ':visible' ) ) {
				self.close();
			}
		} );

		// Tab switching (Login ↔ Register).
		this.$modal.find( '.marg-auth-tab' ).on( 'click', function () {
			const tab = jQuery( this ).data( 'tab' );
			self.switchTab( tab );
		} );

		// Pane links (e.g., "Use magic link instead", "Back to login").
		this.$modal.find( '[data-switch-pane]' ).on( 'click', function ( e ) {
			e.preventDefault();
			const pane = jQuery( this ).data( 'switch-pane' );
			self.showPane( pane );
		} );
	}

	/**
	 * Initialize form handlers.
	 */
	initForms() {
		const onSuccess = ( data ) => this.handleAuthSuccess( data );
		initEmailLogin( onSuccess );
		initEmailRegister( onSuccess );
		initMagicLink();
		initOTP( onSuccess );
		initSocialAuth( onSuccess );
	}

	/**
	 * Open the modal, optionally switching to a specific tab.
	 *
	 * @param {string} tab 'login' or 'register'.
	 */
	open( tab = 'login' ) {
		this.redirectUrl = window.location.href;
		hideMessage();
		this.switchTab( tab );
		this.$modal.fadeIn( 200 );
		document.body.style.overflow = 'hidden';

		// Focus the first input in the active pane.
		setTimeout( () => {
			this.$modal.find( '.marg-auth-pane--active input:first' ).trigger( 'focus' );
		}, 250 );

		// Trap focus inside modal.
		this.trapFocus();

		jQuery( document ).trigger( 'marg:auth:opened' );
	}

	/**
	 * Close the modal.
	 */
	close() {
		this.$modal.fadeOut( 200 );
		document.body.style.overflow = '';
		hideMessage();
		jQuery( document ).trigger( 'marg:auth:closed' );
	}

	/**
	 * Switch between Login and Register tabs.
	 *
	 * @param {string} tab 'login' or 'register'.
	 */
	switchTab( tab ) {
		// Update tab buttons.
		this.$modal.find( '.marg-auth-tab' ).removeClass( 'marg-auth-tab--active' )
			.attr( 'aria-selected', 'false' );
		this.$modal.find( `.marg-auth-tab[data-tab="${ tab }"]` )
			.addClass( 'marg-auth-tab--active' )
			.attr( 'aria-selected', 'true' );

		// Update title.
		this.$modal.find( '.marg-auth-title--login' ).toggle( tab === 'login' );
		this.$modal.find( '.marg-auth-title--register' ).toggle( tab === 'register' );

		// Show the corresponding pane.
		this.showPane( tab );
		hideMessage();
	}

	/**
	 * Show a specific pane (login, register, magic-link, otp).
	 *
	 * @param {string} pane Pane name.
	 */
	showPane( pane ) {
		this.$modal.find( '.marg-auth-pane' ).removeClass( 'marg-auth-pane--active' );
		this.$modal.find( `[data-pane="${ pane }"]` ).addClass( 'marg-auth-pane--active' );
		hideMessage();

		// Reset sub-panes when navigating away and back.
		if ( pane === 'magic-link' ) {
			resetMagicLinkPane();
		}
		if ( pane === 'otp' ) {
			resetOTPPane();
		}
	}

	/**
	 * Handle successful authentication (any method).
	 *
	 * @param {Object} data Response data from the server.
	 */
	handleAuthSuccess( data ) {
		jQuery( document ).trigger( 'marg:auth:success', [ data ] );

		// Short delay to show success message, then redirect.
		setTimeout( () => {
			const url = data.redirect_url || this.redirectUrl || window.location.href;
			window.location.href = url;
		}, 800 );
	}

	/**
	 * Trap focus within the modal for accessibility.
	 */
	trapFocus() {
		const $modal = this.$modal;
		const focusableSelector = 'button, input, [tabindex]:not([tabindex="-1"]), a[href]';

		$modal.off( 'keydown.margFocusTrap' ).on( 'keydown.margFocusTrap', function ( e ) {
			if ( e.key !== 'Tab' ) {
				return;
			}

			const $focusable = $modal.find( focusableSelector ).filter( ':visible' );
			const $first = $focusable.first();
			const $last = $focusable.last();

			if ( e.shiftKey && document.activeElement === $first[ 0 ] ) {
				e.preventDefault();
				$last.trigger( 'focus' );
			} else if ( ! e.shiftKey && document.activeElement === $last[ 0 ] ) {
				e.preventDefault();
				$first.trigger( 'focus' );
			}
		} );
	}

	/**
	 * Intercept clicks on gated actions (PDF view, collect) when the user is not logged in.
	 * Shows the login wall instead of allowing the action.
	 */
	initLoginWallInterceptors() {
		if ( window.margAuth.isLoggedIn ) {
			return;
		}

		const self = this;

		// Intercept "View PDF" member button clicks for non-logged-in users.
		jQuery( document ).on( 'click', '.member-view-btn', function ( e ) {
			if ( window.margAuth.isLoggedIn ) {
				return; // Let it through.
			}
			e.preventDefault();
			e.stopImmediatePropagation();

			// Store pending action so we can replay after login.
			self.storePendingAction( 'pdf', {
				variationId: jQuery( this ).data( 'variation-id' ) || '',
				productUrl: window.location.href,
			} );

			showLoginWall( 'pdf' );
		} );

		// Intercept collect/bookmark button clicks for non-logged-in users.
		jQuery( document ).on( 'click', '.marg-collect-btn', function ( e ) {
			if ( window.margAuth.isLoggedIn ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();

			self.storePendingAction( 'collect', {
				itemType: jQuery( this ).data( 'item-type' ),
				itemId: jQuery( this ).data( 'item-id' ),
			} );

			showLoginWall( 'collect' );
		} );
	}

	/**
	 * Store a pending action in sessionStorage for replay after login.
	 *
	 * @param {string} action  Action name ('pdf', 'collect').
	 * @param {Object} payload Action-specific data.
	 */
	storePendingAction( action, payload ) {
		try {
			sessionStorage.setItem( 'marg_pending_action', JSON.stringify( { action, payload } ) );
		} catch ( e ) {
			// sessionStorage unavailable — ignore.
		}
	}

	/**
	 * After page load (post-login), check for a pending action and replay it.
	 */
	replayPendingAction() {
		if ( ! window.margAuth.isLoggedIn ) {
			return;
		}

		let pending;
		try {
			const raw = sessionStorage.getItem( 'marg_pending_action' );
			if ( ! raw ) {
				return;
			}
			pending = JSON.parse( raw );
			sessionStorage.removeItem( 'marg_pending_action' );
		} catch ( e ) {
			return;
		}

		if ( ! pending || ! pending.action ) {
			return;
		}

		// Replay the action after a short delay to let the page render.
		setTimeout( () => {
			if ( pending.action === 'pdf' ) {
				// Click the first visible "View PDF" button to re-trigger the PDF viewer.
				const $btn = jQuery( '.member-view-btn:visible' ).first();
				if ( $btn.length ) {
					$btn.trigger( 'click' );
				}
			}
			// 'collect' replay will be handled in Phase 5 when the collections feature is built.
		}, 500 );
	}

	/**
	 * Expose a global API so other scripts can trigger the modal and login wall.
	 */
	exposeGlobalAPI() {
		window.MARGAuth = {
			open: ( tab ) => this.open( tab ),
			close: () => this.close(),
			showLoginWall,
			isLoggedIn: window.margAuth.isLoggedIn,
		};
	}
}
