<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 7/27/2017
 *
 * Plugin Name:       WP AAI@EduHr Auth
 * Description:       Plugin that replaces the default WordPress authentication with SimpleSAMLphp AAI@EduHr.
 * Version:           0.0.1
 * Author:            Marko Ivančić marko.ivancic@srce.hr
 * Author URI:          http://markoivancic.from.hr/
 * License:           GPL-2.0+
 * Text Domain:       wp-aaieduhr-auth
 */

/**
 * Core class
 *
 * Class WP_AAIEduHr_Core
 */
class WP_AAIEduHr_Core {
	/**
	 * Instance of plugin options.
	 *
	 * @var WP_AAIEduHr_Options
	 */
	protected $options;

	public function __construct(WP_AAIEduHr_Options $options) {

		// Instantiate plugin options.
		$this->options = $options;

		// If options are entered and are valid, start using AAI@EduHr authentication.
		if ( $this->options->are_valid ) {

			// Start using AAI@EduHr authentication.
			$this->apply();

			// Display notice in admin area that we are now using AAI@EduHr authentication.
			add_action( 'admin_notices', function () {
				$class   = 'notice notice-success';
				$message = __( 'AAI@EduHr authentication is applied. Users need to use AAI@EduHr identities to log in.', 'wp-aaieduhr-auth' );
				$this->display_plugin_notice( $message, $class );
			} );

		} else {

			// Options are not valid, so show notice in admin area that something is not right.
			add_action( 'admin_notices', function () {
				$class   = 'notice notice-warning';
				$message = __( 'AAI@EduHr authentication is not applied. Please check AAI@EduHR Auth settings. ', 'wp-aaieduhr-auth' )
				           . $this->options->validation_message;
				$this->display_plugin_notice( $message, $class );
			} );

		}

		add_shortcode( 'auth-message', array( $this, 'render_auth_message' ) );

	}

	/**
	 * A shortcode for rendering simple messages to the user, using a shortcode on a dedicated page.
	 * For example, if user logs in, the message 'Login successful' is shown to the user.
	 *
	 * @param  array $attributes Shortcode attributes.
	 * @param  string $content The text content for shortcode. Not used.
	 *
	 * @return string  The shortcode output
	 */
	public function render_auth_message( $attributes, $content = null ) {

		// Some default attributes, to get us started.
		$default_attributes = array(
			'show_title'   => true,
			'auth_message' => 'No message...'
		);

		$attributes = shortcode_atts( $default_attributes, $attributes );

		// Resolve actual, main message (status of authentication).
		if ( isset( $_GET['type'] ) ) {
			switch ( $_GET['type'] ) {
				case 'login':
					$attributes['auth_message'] = __( 'Login successful.', 'wp-aaieduhr-auth' );
					break;
				case 'logout':
					$attributes['auth_message'] = __( 'Logout successful.', 'wp-aaieduhr-auth' );
					break;
				case 'error':
					$attributes['auth_message'] = __( 'Oups, there was an error.', 'wp-aaieduhr-auth' );
					break;

			}
		}

		// Resolve error messages
		$errors = [];
		if ( isset( $_GET['errors'] ) ) {
			// Error message codes will be given as GET parameter, as a comma separated list.
			$error_codes = explode( ',', $_GET['errors'] );

			// For each error code get the actual error message.
			foreach ( $error_codes as $code ) {
				$errors[] = $this->get_error_message( $code );
			}
		}
		// Save errors to attributes, so we can use them in shortcode.
		$attributes['errors'] = $errors;

		// Pass the redirect parameter to the WordPress login functionality: by default,
		// don't specify a redirect, but if a valid redirect URL has been passed as
		// request parameter, use it.
		$attributes['redirect'] = '';
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
		}

		// Render the actual template
		return $this->get_template_html( 'auth_message', $attributes );
	}

	/**
	 * Redirect the user to the custom login page instead of wp-login.php.
	 */
	public function redirect_to_custom_login() {

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;

		if ( is_user_logged_in() ) {
			$this->redirect_logged_in_user( $redirect_to );
			exit;
		}

		$login_url = home_url( '/' );

		if ( ! empty( $redirect_to ) ) {
			$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
		}

		require_once( $this->options->get()['simplesamlphp_path'] );

		$ssp = new SimpleSAML_Auth_Simple( $this->options->get()['service_type'] );

		if ( ! $ssp->isAuthenticated() ) {
			$ssp->requireAuth();
		} else {
			$attributes = $ssp->getAttributes();
		}

		if ( ! isset( $attributes['hrEduPersonUniqueID'] ) ) {
			wp_redirect( home_url( 'aaieduhr-auth' ) . '?type=error&errors=no_unique_id' );
			exit;
		}

		$username = $attributes['hrEduPersonUniqueID'][0];

		// We will use AAI@EduHR ID as username.
		$user = get_user_by( 'login', $username );

		if ( $user ) {
			// User exists, so we can set user on current request.
			wp_set_current_user( $user->ID );
		} else {
			if ( ! $this->options->get()['should_create_new_users'] ) {
				wp_redirect( home_url( 'aaieduhr-auth' ) . '?type=error&errors=user_creation_disabled' );
				exit;
			}

			// Create new user.
			if ( isset( $attributes['mail'] ) ) {
				$email = $attributes['mail'][0];
			} else {
				$email = $attributes['hrEduPersonUniqueID'][0];
			}

			$first_name = '';
			if ( isset( $attributes['givenName'] ) ) {
				$first_name = sanitize_text_field( $attributes['givenName'][0] );
			}
			$last_name = '';
			if ( isset( $attributes['sn'] ) ) {
				$last_name = sanitize_text_field( $attributes['sn'][0] );
			}

			// If successfull, we will get user ID, otherwise WP_Error instance.
			$user_id = $this->register_user( $username, $email, $first_name, $last_name );

			// If there was an error creating the user, redirect and show errors.
			if ( is_wp_error( $user_id ) ) {
				// Parse errors into a string and append as parameter to redirect
				$errors       = join( ',', $user_id->get_error_codes() );
				$redirect_url = add_query_arg( [
					'type'   => 'error',
					'errors' => $errors
				], home_url( 'aaieduhr-auth' ) );

				wp_redirect( $redirect_url );
				exit;
			}

			// Set user for current request, and also get the user instance.
			$user = wp_set_current_user( $user_id );
		}

		// https://gist.github.com/cliffordp/e8d1d9f732328ba360ad
		// https://codex.wordpress.org/Function_Reference/wp_set_current_user
		// Set auth cookie so user stays authenticated.
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->user_login );

		$this->redirect_logged_in_user();

		// We end the execution using an exit command because otherwise, the execution will continue
		// with the rest of the actions in wp-login.php
		exit;
	}

	/**
	 * Validates and then completes the new user signup process if all went well.
	 *
	 * @param string $username The new user's username (login)
	 * @param string $email The new user's email address
	 * @param string $first_name The new user's first name
	 * @param string $last_name The new user's last name
	 *
	 * @return int|WP_Error         The id of the user that was created, or error if failed.
	 */
	private function register_user( $username, $email, $first_name = '', $last_name = '' ) {

		$errors = new WP_Error();

		if ( username_exists( $username ) ) {
			$errors->add( 'username_exists', $this->get_error_message( 'username_exists' ) );

			return $errors;
		}

		if ( ! is_email( $email ) ) {
			$errors->add( 'email_invalid', $this->get_error_message( 'email_invalid' ) );

			return $errors;
		}

		// Generate the password
		$password = wp_generate_password( 12, false );

		$user_data = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'nickname'   => $first_name,
		);

		$user_id = wp_insert_user( $user_data );

		return $user_id;
	}

	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code The error code to look up.
	 *
	 * @return string               An error message.
	 */
	private function get_error_message( $error_code ) {
		switch ( $error_code ) {

			case 'no_unique_id':
				return __( 'AAI@EduHr service did not provide unique user ID.', 'aaieduhr' );

			case 'user_creation_disabled':
				return __( 'You were successfully authenticated using AAI@EduHr service, 
					but your account is currently not allowed to enter (new user creation is disabled).', 'aaieduhr' );

			case 'username_exists':
				return __( 'Username is already taken.', 'aaieduhr' );

			case 'email_invalid':
				return __( 'Email is not valid.', 'aaieduhr' );

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'personalize-login' );
	}

	/**
	 * Renders the contents of the given template to a string and returns it.
	 *
	 * @param string $template_name The name of the template to render (without .php)
	 * @param array $attributes The PHP variables for the template
	 *
	 * @return string               The contents of the template.
	 */
	private function get_template_html( $template_name, $attributes = null ) {
		if ( ! $attributes ) {
			$attributes = array();
		}

		ob_start();

		do_action( 'personalize_auth_message_' . $template_name );

		require( 'templates/' . $template_name . '.php' );

		do_action( 'personalize_auth_message_' . $template_name );

		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	protected function apply() {

		// Add custom login execution.
		add_action( 'login_form_login', array( $this, 'redirect_to_custom_login' ) );

		// Hook to logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// Remove default authentication filters.
		remove_filter( 'authenticate', 'wp_authenticate_username_password' );
		remove_filter( 'authenticate', 'wp_authenticate_email_password' );
		remove_filter( 'authenticate', 'wp_authenticate_spam_check' );
	}

	/**
	 * Notice to let admins know that AAI@EduHr authentication is active.
	 */
	public function display_plugin_notice( $message, $class ) {

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Redirect to custom page after the user has been logged out and show the logout message.
	 * Also, if user is authenticated using AAI@EduHr, do the logout.
	 */
	public function redirect_after_logout() {

		// We will redirect users to our custom page to show them appropriate logout message.
		$redirect_url = add_query_arg( 'type', 'logout', home_url( 'aaieduhr-auth' ) );

		// We will check if we need to logout user using AAI@EduHr service.
		require_once( $this->options->get()['simplesamlphp_path'] );

		$ssp = new SimpleSAML_Auth_Simple( $this->options->get()['service_type'] );

		if ( $ssp->isAuthenticated() ) {
			$ssp->logout( $redirect_url );
		} else {
			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Redirects the user to the correct page depending on whether he / she
	 * is an admin or not.
	 *
	 * @param string $redirect_to An optional redirect_to URL for admin users
	 */
	private function redirect_logged_in_user( $redirect_to = null ) {

		$user = wp_get_current_user();

		if ( user_can( $user, 'manage_options' ) ) {
			if ( $redirect_to ) {
				wp_safe_redirect( $redirect_to );
			} else {
				wp_redirect( admin_url() );
			}
		} else {
			wp_redirect( add_query_arg( 'type', 'login', home_url( 'aaieduhr-auth' ) ) );
		}
	}

}


