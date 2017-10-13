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

		// Save plugin options so we can use them.
		$this->options = $options;

		// If options are valid, start using AAI@EduHr authentication.
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

	}

	/**
	 * Start using AIA@EduHr authentication.
	 *
	 */
	protected function apply() {

		// Add custom login execution.
		add_action( 'login_form_login', array( $this, 'redirect_to_aaieduhr_login' ) );

		// Hook to logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// Remove default authentication filters.
		remove_filter( 'authenticate', 'wp_authenticate_username_password' );
		remove_filter( 'authenticate', 'wp_authenticate_email_password' );
		remove_filter( 'authenticate', 'wp_authenticate_spam_check' );
	}


	/**
	 * Redirect the user to the AAI@EduHr login page instead of wp-login.php.
	 */
	public function redirect_to_aaieduhr_login() {

		// If there is redirect parameter, save it.
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;

		// If user is already logged in, simply redirect him and stop.
		if ( is_user_logged_in() ) {
			$this->redirect_logged_in_user( $redirect_to );
			exit;
		}

		// Get the SimpleSAMLphp package.
		require_once( $this->options->get()['simplesamlphp_path'] );

		// Create new SSP instance.
		$ssp = new SimpleSAML_Auth_Simple( $this->options->get()['service_type'] );

		// User must be authenticated using AAI@EduHr
		$ssp->requireAuth();

		// Get the user attributes from AAI@EduHr
		$attributes = $ssp->getAttributes();

		// Person ID must be set in attributes.
		if ( ! isset( $attributes['hrEduPersonUniqueID'] ) ) {
			// Redirect to our custom page and show the error.
			wp_redirect( home_url( 'aaieduhr-auth' ) . '?code=error&errors=no_unique_id' );
			exit;
		}

		// Try to get the user, so we can check if it already exists.
		$user = get_user_by( 'login', $attributes['hrEduPersonUniqueID'][0] );

		if ( $user ) {
			// User exists, so we can set user on current request.
			wp_set_current_user( $user->ID );
		} else {
			// User does not exist.
			// Check if we are allowed to create new users.
			if ( ! $this->options->get()['should_create_new_users'] ) {
				// We are not allowed to create new users, so show the appropriate message and stop.
				wp_redirect( home_url( 'aaieduhr-auth' ) . '?code=error&errors=user_creation_disabled' );
				exit;
			}

			// We are allowed to create new users.
			// Prepare data for user creation.
			$user_data = $this->prepare_user_data( $attributes );

			// Create the user.
			// If successful, we will get user ID, otherwise WP_Error instance.
			$user_id = $this->register_user( $user_data );

			// If there was an error creating the user, redirect and show errors.
			if ( is_wp_error( $user_id ) ) {
				// Parse errors into a string and append as parameter to redirect
				$errors       = join( ',', $user_id->get_error_codes() );
				$redirect_url = add_query_arg( [
					'code'   => 'error',
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
		// Set auth cookie so user stays authenticated on future requests.
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->user_login );

		// Users are logged in, so we can redirect them to appropriate page.
		$this->redirect_logged_in_user();

		// We end the execution using an exit command because otherwise, the execution will continue
		// with the rest of the actions in wp-login.php
		exit;
	}

	/**
	 * Validate user date and create new user.
	 *
	 * @param array $user_data Data needed to create the user
	 *
	 * @return int|WP_Error         The id of the user that was created, or error if failed.
	 */
	private function register_user( $user_data ) {

		$errors = new WP_Error();

		// Username should not already exist.
		if ( username_exists( $user_data['username'] ) ) {
			$errors->add( 'username_exists', WP_AAIEduHr_Helper::get_error_message( 'username_exists' ) );
			return $errors;
		}

		// Email should be in correct format.
		if ( ! is_email( $user_data['email'] ) ) {
			$errors->add( 'email_invalid', WP_AAIEduHr_Helper::get_error_message( 'email_invalid' ) );
			return $errors;
		}

		// Generate random password. It won't be used when this plugin is active.
		$password = wp_generate_password( 12, false );

		// Specify which data to use for user insert.
		$user_insert_data = [
			'user_login' => $user_data['username'],
			'user_email' => $user_data['email'],
			'user_pass'  => $password,
			'first_name' => $user_data['first_name'],
			'last_name'  => $user_data['last_name'],
			'nickname'   => $user_data['first_name'],
		];

		// Insert user to the database.
		$user_id = wp_insert_user( $user_insert_data );

		$usermeta_prefix = 'aaieduhr_';

		// Add indication that the user was created when he authenticated using AAI@EduHr.
		add_user_meta($user_id, $usermeta_prefix . 'account', true);

		// Store all other usermeta available in the user data.
		foreach ($user_data['meta'] as $key => $value) {
			add_user_meta($user_id, $usermeta_prefix . $key, $value);
		}

		return $user_id;
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
		$redirect_url = add_query_arg( 'code', 'logout', home_url( 'aaieduhr-auth' ) );

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
			wp_redirect( add_query_arg( 'code', 'login', home_url( 'aaieduhr-auth' ) ) );
		}
	}

	/**
	 * Prepare user data from AAI@EduHr attributes.
	 *
	 * @param array $attributes
	 *
	 * @return array User data
	 */
	private function prepare_user_data( $attributes ) {

		$data = [];

		// Username will be AAI@EduHR ID.
		$data['username'] = $attributes['hrEduPersonUniqueID'][0];

		// Email will be the same as user ID, so we basically avoid email duplication (which WordPress won't allow).
		$data['email'] = $attributes['hrEduPersonUniqueID'][0];

		if ( isset( $attributes['givenName'] ) ) {
			$data['first_name'] = sanitize_text_field( $attributes['givenName'][0] );
		}
		else {
			$data['first_name'] = '';
		}
		if ( isset( $attributes['givenName'] ) ) {
			$data['last_name'] = sanitize_text_field( $attributes['sn'][0] );
		}
		else {
			$data['last_name'] = '';
		}

		// User metadata will be stored in its own array under 'meta' key.
		$data['meta'] = [];
		// We will store original email, just for records.
		if( isset( $attributes['mail'] ) ){
			$data['meta']['original_mail'] = $attributes['mail'][0];
		}
		if( isset( $attributes['hrEduPersonOIB'] ) ){
			$data['meta']['oib'] = $attributes['hrEduPersonOIB'][0];
		}
		if( isset( $attributes['hrEduPersonPersistentID'] ) ){
			$data['meta']['persistent_id'] = $attributes['hrEduPersonPersistentID'][0];
		}

		return $data;
	}

}


