<?php
/**
 * Auth Modal
 *
 * Renders the login/signup popup modal and enqueues its assets.
 * Loaded on all pages so the modal is available site-wide.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

class AuthModal {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_modal' ] );
		add_action( 'wp_footer', [ $this, 'render_login_wall' ] );
		add_filter( 'wp_nav_menu_items', [ $this, 'add_auth_nav_item' ], 10, 2 );
	}

	/**
	 * Enqueue modal JS and CSS on all frontend pages.
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$js_path  = TENUP_THEME_DIST_PATH . 'js/auth-modal.asset.php';
		$css_path = TENUP_THEME_DIST_PATH . 'css/auth-modal.css';

		// JS dependencies and version from 10up-toolkit asset file.
		$asset = file_exists( $js_path ) ? require $js_path : [
			'dependencies' => [ 'jquery' ],
			'version'      => TENUP_THEME_VERSION,
		];

		wp_enqueue_script(
			'marg-auth-modal',
			TENUP_THEME_DIST_URL . 'js/auth-modal.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script( 'marg-auth-modal', 'margAuth', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'marg_auth_nonce' ),
			'isLoggedIn'    => is_user_logged_in(),
			'homeUrl'       => home_url(),
			'googleEnabled' => \TenUpTheme\AuthProviders\GoogleAuth::is_configured(),
			'appleEnabled'  => \TenUpTheme\AuthProviders\AppleAuth::is_configured(),
		] );

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'marg-auth-modal',
				TENUP_THEME_DIST_URL . 'css/auth-modal.css',
				[],
				$asset['version']
			);
		}
	}

	/**
	 * Add Sign up / Login button to the primary navigation.
	 *
	 * @param string $items Menu HTML.
	 * @param object $args  Menu arguments.
	 * @return string
	 */
	public function add_auth_nav_item( $items, $args ) {
		if ( 'primary' !== $args->theme_location ) {
			return $items;
		}

		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$items .= '<li class="menu-item marg-auth-menu-item">';
			$items .= '<a href="' . esc_url( wc_get_account_endpoint_url( 'dashboard' ) ) . '" class="marg-auth-nav-link marg-auth-nav-link--account">';
			$items .= esc_html( $user->display_name );
			$items .= '</a>';
			$items .= '</li>';
		} else {
			$items .= '<li class="menu-item marg-auth-menu-item">';
			$items .= '<button type="button" class="marg-auth-nav-link" data-marg-auth-trigger="login">';
			$items .= esc_html__( 'Sign up / Login', 'tenup-theme' );
			$items .= '</button>';
			$items .= '</li>';
		}

		return $items;
	}

	/**
	 * Render the auth modal HTML in the footer.
	 */
	public function render_modal() {
		if ( is_admin() ) {
			return;
		}
		?>
		<div id="marg-auth-modal" class="marg-auth-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="marg-auth-title">
			<div class="marg-auth-modal__overlay"></div>
			<div class="marg-auth-modal__content">
				<button class="marg-auth-modal__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'tenup-theme' ); ?>">&times;</button>

				<h2 id="marg-auth-title" class="marg-auth-modal__title">
					<span class="marg-auth-title--login"><?php esc_html_e( 'Welcome back', 'tenup-theme' ); ?></span>
					<span class="marg-auth-title--register" style="display: none;"><?php esc_html_e( 'Create an account', 'tenup-theme' ); ?></span>
				</h2>

				<!-- Tab switcher -->
				<div class="marg-auth-modal__tabs" role="tablist">
					<button class="marg-auth-tab marg-auth-tab--active" type="button" role="tab" aria-selected="true" data-tab="login">
						<?php esc_html_e( 'Log In', 'tenup-theme' ); ?>
					</button>
					<button class="marg-auth-tab" type="button" role="tab" aria-selected="false" data-tab="register">
						<?php esc_html_e( 'Sign Up', 'tenup-theme' ); ?>
					</button>
				</div>

				<!-- Social auth buttons -->
				<?php
				$google_enabled = \TenUpTheme\AuthProviders\GoogleAuth::is_configured();
				$apple_enabled  = \TenUpTheme\AuthProviders\AppleAuth::is_configured();
				$any_social     = $google_enabled || $apple_enabled;
				?>
				<?php if ( $any_social ) : ?>
				<div class="marg-auth-social">
					<?php if ( $google_enabled ) : ?>
					<button class="marg-auth-social__btn marg-auth-social__btn--google" type="button">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
							<path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
							<path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.997 8.997 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
							<path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
						</svg>
						<?php esc_html_e( 'Continue with Google', 'tenup-theme' ); ?>
					</button>
					<?php endif; ?>
					<?php if ( $apple_enabled ) : ?>
					<button class="marg-auth-social__btn marg-auth-social__btn--apple" type="button">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M15.098 13.539a9.37 9.37 0 0 1-.925 1.665c-.487.694-.885 1.175-1.192 1.441-.476.438-.986.663-1.533.677-.393 0-.866-.112-1.42-.339-.555-.226-1.065-.338-1.532-.338-.49 0-1.015.112-1.578.338-.564.227-1.018.345-1.366.358-.524.025-1.047-.207-1.57-.697-.333-.29-.749-.787-1.248-1.49-.535-.753-.975-1.625-1.32-2.62C.472 10.797.133 9.6.133 8.442c0-1.324.286-2.466.858-3.42A5.04 5.04 0 0 1 2.79 3.21a4.836 4.836 0 0 1 2.434-.688c.417 0 .963.129 1.641.383.676.255 1.11.384 1.3.384.142 0 .623-.151 1.438-.453.77-.28 1.42-.396 1.95-.352 1.441.116 2.523.685 3.24 1.712-1.288.781-1.926 1.874-1.913 3.276.012 1.092.408 2.001 1.185 2.723a3.89 3.89 0 0 0 1.176.77c-.094.274-.194.536-.298.787zM11.47.36c0 .856-.312 1.654-.935 2.39-.751.879-1.66 1.387-2.645 1.306a2.66 2.66 0 0 1-.02-.322c0-.822.358-1.7.993-2.42.317-.364.721-.667 1.21-.91.49-.24.953-.372 1.39-.397.013.118.018.236.018.354z" fill="currentColor"/>
						</svg>
						<?php esc_html_e( 'Continue with Apple', 'tenup-theme' ); ?>
					</button>
					<?php endif; ?>
				</div>

				<div class="marg-auth-divider"><span><?php esc_html_e( 'or', 'tenup-theme' ); ?></span></div>
				<?php endif; ?>

				<!-- LOGIN pane -->
				<div class="marg-auth-pane marg-auth-pane--active" data-pane="login" role="tabpanel">
					<form class="marg-auth-form" id="marg-login-form" novalidate>
						<div class="marg-auth-field">
							<label for="marg-login-email" class="screen-reader-text"><?php esc_html_e( 'Email', 'tenup-theme' ); ?></label>
							<input type="email" id="marg-login-email" name="email" placeholder="<?php esc_attr_e( 'Email address', 'tenup-theme' ); ?>" required autocomplete="email" />
						</div>
						<div class="marg-auth-field">
							<label for="marg-login-password" class="screen-reader-text"><?php esc_html_e( 'Password', 'tenup-theme' ); ?></label>
							<input type="password" id="marg-login-password" name="password" placeholder="<?php esc_attr_e( 'Password', 'tenup-theme' ); ?>" required autocomplete="current-password" />
						</div>
						<button type="submit" class="marg-auth-submit"><?php esc_html_e( 'Log In', 'tenup-theme' ); ?></button>
					</form>
					<div class="marg-auth-links">
						<button type="button" class="marg-auth-link" data-switch-pane="magic-link"><?php esc_html_e( 'Use magic link instead', 'tenup-theme' ); ?></button>
						<button type="button" class="marg-auth-link" data-switch-pane="otp"><?php esc_html_e( 'Log in with phone', 'tenup-theme' ); ?></button>
					</div>
				</div>

				<!-- REGISTER pane -->
				<div class="marg-auth-pane" data-pane="register" role="tabpanel">
					<form class="marg-auth-form" id="marg-register-form" novalidate>
						<div class="marg-auth-field">
							<label for="marg-register-name" class="screen-reader-text"><?php esc_html_e( 'Full name', 'tenup-theme' ); ?></label>
							<input type="text" id="marg-register-name" name="name" placeholder="<?php esc_attr_e( 'Full name', 'tenup-theme' ); ?>" required autocomplete="name" />
						</div>
						<div class="marg-auth-field">
							<label for="marg-register-email" class="screen-reader-text"><?php esc_html_e( 'Email', 'tenup-theme' ); ?></label>
							<input type="email" id="marg-register-email" name="email" placeholder="<?php esc_attr_e( 'Email address', 'tenup-theme' ); ?>" required autocomplete="email" />
						</div>
						<div class="marg-auth-field">
							<label for="marg-register-password" class="screen-reader-text"><?php esc_html_e( 'Password', 'tenup-theme' ); ?></label>
							<input type="password" id="marg-register-password" name="password" placeholder="<?php esc_attr_e( 'Password (8+ characters)', 'tenup-theme' ); ?>" required minlength="8" autocomplete="new-password" />
						</div>
						<button type="submit" class="marg-auth-submit"><?php esc_html_e( 'Create Account', 'tenup-theme' ); ?></button>
					</form>
					<div class="marg-auth-links">
						<button type="button" class="marg-auth-link" data-switch-pane="otp"><?php esc_html_e( 'Sign up with phone', 'tenup-theme' ); ?></button>
					</div>
				</div>

				<!-- MAGIC LINK pane (Phase 2) -->
				<div class="marg-auth-pane" data-pane="magic-link" role="tabpanel">
					<form class="marg-auth-form" id="marg-magic-link-form" novalidate>
						<p class="marg-auth-pane__description"><?php esc_html_e( "We'll email you a login link. No password needed.", 'tenup-theme' ); ?></p>
						<div class="marg-auth-field">
							<label for="marg-magic-email" class="screen-reader-text"><?php esc_html_e( 'Email', 'tenup-theme' ); ?></label>
							<input type="email" id="marg-magic-email" name="email" placeholder="<?php esc_attr_e( 'Email address', 'tenup-theme' ); ?>" required autocomplete="email" />
						</div>
						<button type="submit" class="marg-auth-submit"><?php esc_html_e( 'Send Magic Link', 'tenup-theme' ); ?></button>
					</form>
					<div class="marg-auth-success" id="marg-magic-link-success" style="display: none;">
						<p><?php esc_html_e( 'Check your email! We sent a login link to', 'tenup-theme' ); ?> <strong class="marg-sent-email"></strong></p>
					</div>
					<div class="marg-auth-links">
						<button type="button" class="marg-auth-link" data-switch-pane="login"><?php esc_html_e( 'Back to login', 'tenup-theme' ); ?></button>
					</div>
				</div>

				<!-- OTP pane (Phase 3) -->
				<div class="marg-auth-pane" data-pane="otp" role="tabpanel">
					<form class="marg-auth-form" id="marg-otp-send-form" novalidate>
						<p class="marg-auth-pane__description"><?php esc_html_e( 'Log in or sign up with your phone number.', 'tenup-theme' ); ?></p>
						<div class="marg-auth-field">
							<label for="marg-otp-phone" class="screen-reader-text"><?php esc_html_e( 'Phone number', 'tenup-theme' ); ?></label>
							<input type="tel" id="marg-otp-phone" name="phone" placeholder="<?php esc_attr_e( '+91 phone number', 'tenup-theme' ); ?>" required autocomplete="tel" />
						</div>
						<button type="submit" class="marg-auth-submit"><?php esc_html_e( 'Send OTP', 'tenup-theme' ); ?></button>
					</form>
					<form class="marg-auth-form" id="marg-otp-verify-form" style="display: none;" novalidate>
						<p class="marg-auth-pane__description"><?php esc_html_e( 'Enter the 6-digit code sent to', 'tenup-theme' ); ?> <strong class="marg-sent-phone"></strong></p>
						<div class="marg-auth-field">
							<label for="marg-otp-code" class="screen-reader-text"><?php esc_html_e( 'OTP Code', 'tenup-theme' ); ?></label>
							<input type="text" id="marg-otp-code" name="otp" placeholder="<?php esc_attr_e( '000000', 'tenup-theme' ); ?>" maxlength="6" required pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" />
						</div>
						<button type="submit" class="marg-auth-submit"><?php esc_html_e( 'Verify', 'tenup-theme' ); ?></button>
						<div class="marg-auth-links marg-auth-links--inline">
							<button type="button" class="marg-auth-link marg-otp-resend"><?php esc_html_e( 'Resend OTP', 'tenup-theme' ); ?></button>
						</div>
					</form>
					<div class="marg-auth-links">
						<button type="button" class="marg-auth-link" data-switch-pane="login"><?php esc_html_e( 'Back to login', 'tenup-theme' ); ?></button>
					</div>
				</div>

				<!-- Status message -->
				<div class="marg-auth-message" id="marg-auth-message" style="display: none;" role="alert" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the login wall overlay in the footer.
	 */
	public function render_login_wall() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		?>
		<div id="marg-login-wall" class="marg-login-wall" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="marg-login-wall-title">
			<div class="marg-login-wall__overlay"></div>
			<div class="marg-login-wall__content">
				<button class="marg-login-wall__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'tenup-theme' ); ?>">&times;</button>
				<div class="marg-login-wall__icon">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
					</svg>
				</div>
				<h2 id="marg-login-wall-title" class="marg-login-wall__title"><?php esc_html_e( 'You need an account', 'tenup-theme' ); ?></h2>
				<p class="marg-login-wall__message" id="marg-login-wall-message">
					<?php esc_html_e( 'Sign up or log in to access this content.', 'tenup-theme' ); ?>
				</p>
				<button class="marg-login-wall__cta" type="button" data-marg-auth-trigger="register">
					<?php esc_html_e( 'Sign Up / Log In', 'tenup-theme' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
