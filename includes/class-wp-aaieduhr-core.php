<?php


class WP_AAIEduHr_Core {
	const COOKIE_KEY_AABS = 'wp-aaieduhr-auth-aabs';

	/**
	 * Instance of plugin options.
	 *
	 * @var WP_AAIEduHr_Options
	 */
	protected $options;

	public function __construct( WP_AAIEduHr_Options $options ) {

		// Save plugin options so we can use them.
		$this->options = $options;

		// Check if plugin options are valid at all.
		if ( !$this->options->are_valid ) {

			// Options are not valid, so show a notice in admin area that something is not right.
			$class   = 'notice notice-warning';
			$message = __(
					'AAI@EduHr authentication is NOT applied. Please check WP AAI@EduHR Auth settings.',
					'wp-aaieduhr-auth'
				) . $this->options->validation_message;

			WP_AAIEduHr_Helper::display_notice($message, $class);
		}
		// Check if AAI@EduHr authentication is being bypassed in current request
		elseif ( $this->is_aaieduhr_auth_being_bypassed() ) {

			// Set the cookie, so we can check the bypass has been initiated in future requests.
			setcookie(self::COOKIE_KEY_AABS, '1', strtotime('+1 hour'), SITECOOKIEPATH);
		}
		// Check if AAI@EduHr authentication has been bypassed previously
		elseif ($this->has_aaieduhr_auth_been_bypassed_for_current_session()) {

			$class   = 'notice notice-warning';
			$message = __( 'AAI@EduHr authentication has been bypassed for current user.', 'wp-aaieduhr-auth' );

			WP_AAIEduHr_Helper::display_notice($message, $class);

			// Add an action which will check if we need to clear cookies related to aabs.
			add_action( 'init', array( static::class, 'clear_cookies_if_needed' ) );
		}
		// Options are valid, start using AAI@EduHr authentication.
		else {

			$this->apply();

			$class   = 'notice notice-success';
			$message = __( 'AAI@EduHr authentication is applied. Users need to use AAI@EduHr identities to log in.', 'wp-aaieduhr-auth' );

			WP_AAIEduHr_Helper::display_notice($message, $class);
		}

	}

	/**
	 * Start using AIA@EduHr authentication.
	 *
	 */
	protected function apply( ) {

		// Add custom login execution.
		add_action( 'login_form_login', array( $this, 'redirect_to_aaieduhr_login' ) );

		// Hook to logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// Remove default authentication filters, they are not needed now.
		remove_filter( 'authenticate', 'wp_authenticate_username_password' );
		remove_filter( 'authenticate', 'wp_authenticate_email_password' );
		remove_filter( 'authenticate', 'wp_authenticate_spam_check' );

		// Disable the auth check for monitoring whether the user is still logged in, to prevent 'Log in again' modal.
		// More info: https://techjourney.net/disable-wordpress-session-expired-log-in-again-inline-modal-popup/
		remove_action('admin_enqueue_scripts', 'wp_auth_check_load');
	}

	/**
	 * Redirect the user to the AAI@EduHr login page instead of wp-login.php.
	 */
	public function redirect_to_aaieduhr_login( ) {

		// If there is redirect parameter, save it.
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;

		// If user is already logged in, simply redirect him and stop.
		if ( is_user_logged_in() ) {
			$this->redirect_logged_in_user( $redirect_to );
		}

		// Create new SSP instance.
		$ssp = new \SimpleSAML\Auth\Simple( $this->options->get()['service_type'] );

		// User must be authenticated using AAI@EduHr
		$ssp->requireAuth(['ReturnTo' => wp_login_url()]);

		// Get the user attributes from AAI@EduHr
		$attributes = $ssp->getAttributes();

		// Person ID must be set in attributes.
		if ( ! isset( $attributes['hrEduPersonUniqueID'] ) ) {
			// Redirect to our custom page and show the error.
			WP_AAIEduHr_Helper::show_message('error', 'no_unique_id');
		}

		// Check if only specific realms are allowed.
		if ( ! empty( $this->options->get()['allowed_realms'] ) ) {
			// Realms are not empty, so we have to check if user comes from the allowed realms.
			$this->check_if_user_realm_is_allowed( $attributes['hrEduPersonUniqueID'][0] );
		}

		// Realms are ok, we can continue.
		// Try to get the user, so we can check if it already exists.
		$user = get_user_by( 'login', $attributes['hrEduPersonUniqueID'][0] );

		if ( $user ) {
			// Check if this is the first time the user has logged in using AAI@EduHr. We can do that by checking the
			// 'aaieduhr_account' meta which is set to true if the user was created using this plugin,
			// or logged in at least once.
			if ( ! WP_AAIEduHr_Helper::is_aaieduhr_account( $user->ID ) ) {
				// We need to update user meta, so we ensure indication that this is AAI@EduHr account.
				// Prepare user data. This will also prepare user meta.
				$user_data = $this->prepare_user_data( $attributes );

				// Update user meta.
				WP_AAIEduHr_Helper::do_update_user_meta( $user->ID, $user_data['meta'] );

				// We will also reset user password, since it is possible that the admin saw the initial password
				// during user creation using Add New User form in WordPress administration.
				wp_set_password( wp_generate_password(), $user->ID );
			}

			// Ue can set existing user on current request.
			wp_set_current_user( $user->ID );

		} else {
			// User does not exist.
			// Check if we are allowed to create new users.
			if ( ! $this->options->get()['should_create_new_users'] ) {
				// We are not allowed to create new users, so show the appropriate message and stop.
				WP_AAIEduHr_Helper::show_message('error', 'user_creation_disabled');
			}

			// We are allowed to create new users.
			// Prepare data for user creation.
			$user_data = $this->prepare_user_data( $attributes );

			// Create the user.
			// If successful, we will get user ID, otherwise WP_Error instance.
			$user_id = $this->register_user( $user_data );

			// If there was an error creating the user, redirect and show errors.
			if ( is_wp_error( $user_id ) ) {
				// Parse errors into a string.
				$errors       = join( ',', $user_id->get_error_codes() );
				// Show the message and stop.
				WP_AAIEduHr_Helper::show_message('error', $errors );
			}

			// Set user for current request, and also get the user instance.
			$user = wp_set_current_user( $user_id );
		}

		// https://gist.github.com/cliffordp/e8d1d9f732328ba360ad
		// https://codex.wordpress.org/Function_Reference/wp_set_current_user
		// Set auth cookie so user stays authenticated on future requests.
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->user_login, $user );


		// Users are logged in, so we can redirect them to appropriate page.
		$this->redirect_logged_in_user();
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

		// Store all other usermeta available in the user data.
		WP_AAIEduHr_Helper::do_update_user_meta( $user_id, $user_data['meta'] );

		return $user_id;
	}

	/**
	 * Redirect to custom page after the user has been logged out and show the logout message.
	 * Also, if user is authenticated using AAI@EduHr, do the logout.
	 */
	public function redirect_after_logout() {

		// URL to redirect users after logout. Instead of using custom page to show logout message, redierct to
		// site URL and use .
		// $redirect_url = WP_AAIEduHr_Helper::get_permalink_by_slug('aaieduhr-auth', [ 'code' => 'logout' ] );

		$redirect_url = WP_AAIEduHr_Helper::get_site_url( [ 'code' => 'logout' ] );

		$ssp = new \SimpleSAML\Auth\Simple( $this->options->get()['service_type'] );

		if ( $ssp->isAuthenticated() ) {
			$ssp->logout( $redirect_url );
		} else {
			wp_redirect( $redirect_url );
		}

		exit;
	}

	/**
	 * Redirects the user to the correct page depending on whether he / she
	 * is an admin or not, and also if MultiSite feature is used.
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
			WP_AAIEduHr_Helper::show_message('login' );
		}

		// We will end the execution using an exit command because otherwise, the execution will continue
		// with the rest of the actions in wp-login.php
		exit;
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
		if( isset( $attributes['mail'] ) ){
			$data['email'] = $attributes['mail'][0];
		} else {
			$data['email'] = $attributes['hrEduPersonUniqueID'][0];
		}

		if ( isset( $attributes['givenName'] ) ) {
			$data['first_name'] = sanitize_text_field( $attributes['givenName'][0] );
		}
		else {
			$data['first_name'] = '';
		}
		if ( isset( $attributes['sn'] ) ) {
			$data['last_name'] = sanitize_text_field( $attributes['sn'][0] );
		}
		else {
			$data['last_name'] = '';
		}

		// User metadata will be stored in its own array under 'meta' key.
		$data['meta'] = [];
		// We will store an indication that this is AAI@EduHr account.
		$data['meta']['account'] = true;
		// We will store original email, just for records.
		if( isset( $attributes['mail'] ) ){
			$data['meta']['original_mail'] = $attributes['mail'][0];
		}
		// If we get OIB, we will store it.
		if( isset( $attributes['hrEduPersonOIB'] ) ){
			$data['meta']['oib'] = $attributes['hrEduPersonOIB'][0];
		}
		// If we get persistant ID, we will store it.
		if( isset( $attributes['hrEduPersonPersistentID'] ) ){
			$data['meta']['persistent_id'] = $attributes['hrEduPersonPersistentID'][0];
		}

		return $data;
	}

	/**
	 * Check if the user is from the allowed realm.
	 *
	 * @param $aaieduhr_id
	 */
	protected function check_if_user_realm_is_allowed( $aaieduhr_id ) {
		// Extract user realm from his AAI@EduHr ID.
		$user_realm = substr( $aaieduhr_id, strpos( $aaieduhr_id, '@' ) + 1 );

		// If user realm is not in the allowed list, return and show appropriate message.
		if ( ! in_array($user_realm, $this->options->get()['allowed_realms'] ) ) {

			WP_AAIEduHr_Helper::show_message('error', 'realm_not_allowed');

		}
	}

	/**
	 * Check if AAI@EduHr Auth is being bypassed in current request. This can be done by using a previously configured
	 * secret as a GET parameter in wp-login.php route, (/wp-login.php?aabs=some-secret)
	 * @return bool
	 */
	protected function is_aaieduhr_auth_being_bypassed() {
		// If request parameter is present and not empty, and
		// if option is set and not empty, and
		// if they are equal
		if (
			isset($_GET[WP_AAIEduHr_Options::KEY_AABS]) && !empty($_GET[WP_AAIEduHr_Options::KEY_AABS]) &&
			isset($this->options->get()[WP_AAIEduHr_Options::KEY_AABS]) && !empty($this->options->get()[WP_AAIEduHr_Options::KEY_AABS]) &&
			$_GET[WP_AAIEduHr_Options::KEY_AABS] === $this->options->get()[WP_AAIEduHr_Options::KEY_AABS]
		) {
			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	protected function has_aaieduhr_auth_been_bypassed_for_current_session() {
		return isset($_COOKIE[self::COOKIE_KEY_AABS]);
	}

	/**
	 *
	 */
	public static function clear_cookies_if_needed ( ) {
		// If the user is logged in, we can clear the aabs cookie.

		if (is_user_logged_in()) {
			setcookie(self::COOKIE_KEY_AABS, null, strtotime('-1 day'), SITECOOKIEPATH);
		}
	}
}
