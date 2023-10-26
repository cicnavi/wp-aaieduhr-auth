<?php


class WP_AAIEduHr_Helper {

	/**
	 * Get the message for the given message code.
	 *
	 * @param $message_code
	 *
	 * @return string
	 */
	public static function get_message( $message_code ) {
		switch ( $message_code ) {
			case 'login':
				return __( 'Login successful.', 'wp-aaieduhr-auth' );
			case 'logout':
				return __( 'Logout successful.', 'wp-aaieduhr-auth' );
			case 'error':
				return __( 'Oops, there was an error:', 'wp-aaieduhr-auth' );
			default:
				return __( 'This message is not yet defined.', 'wp-aaieduhr-auth' );
				break;
		}
	}

	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code The error code to look up.
	 *
	 * @return string               An error message.
	 */
	public static function get_error_message( $error_code ) {

		switch ( $error_code ) {

			case 'no_unique_id':
				return __( 'AAI@EduHr service did not provide unique user ID.', 'wp-aaieduhr-auth' );

			case 'user_creation_disabled':
				return __( 'You were successfully authenticated using AAI@EduHr service, 
					however your account is currently not allowed to enter (new user creation is disabled).', 'wp-aaieduhr-auth' );

			case 'username_exists':
				return __( 'Username is already taken.', 'wp-aaieduhr-auth' );

			case 'email_invalid':
				return __( 'Email is not valid.', 'wp-aaieduhr-auth' );

			case 'existing_user_email':
				return __( 'Email is already used.', 'wp-aaieduhr-auth' );

			case 'realm_not_allowed':
				return __( 'You were successfully authenticated using AAI@EduHr service, 
					however your realm is currently not allowed.', 'wp-aaieduhr-auth' );

			case 'simplesamlphp_not_loaded':
				return __( 'simpleSAMLphp package was not loaded. Is the path to simpleSAMLphp correct?', 'wp-aaieduhr-auth' );

			case 'disabled_password_manipulation':
				return __( 'Password manipulation is disabled since AAI@EduHr system is being used.', 'wp-aaieduhr-auth' );

			case 'registration_disabled':
				return __( 'User registration is disabled since AAI@EduHr system is being used.', 'wp-aaieduhr-auth' );

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'wp-aaieduhr-auth' );
	}

	/**
	 * Finds and returns a matching CSS class for the given error or auth code.
	 *
	 * @param string $code The error code to look up.
	 *
	 * @return string      CSS class.
	 */
	public static function get_code_css_class( $code ) {

		switch ( $code ) {

			case 'email_invalid':
			case 'simplesamlphp_not_loaded':
			case 'disabled_password_manipulation':
			case 'registration_disabled':
			case 'no_unique_id':
				return 'danger';

			case 'username_exists':
			case 'existing_user_email':
			case 'realm_not_allowed':
			case 'error':
			case 'user_creation_disabled':
				return 'warning';

			case 'logout':
			case 'login':
				return 'success';

			default:
				break;
		}

		return 'warning';
	}

	/**
	 * Get site URL together with provided query arguments.
	 *
	 * @param array $query_args Optional array of query arguments to put in URL
	 *
	 * @return string
	 */
	public static function get_site_url( $query_args = [] ) {
		return add_query_arg( $query_args, get_site_url() );
	}

	/**
	 * Renders the contents of the given template to a string and returns it.
	 *
	 * @param string $template_name The name of the template to render (without .php)
	 * @param array $attributes The PHP variables for the template
	 *
	 * @return string               The contents of the template.
	 */
	public static function get_template_html( $template_name, $attributes = null ) {

		// Path to the templates directory.
		$templates_dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;

		if ( ! $attributes ) {
			$attributes = array();
		}

		ob_start();

		do_action( 'personalize_auth_message_' . $template_name );

		require( $templates_dir . $template_name . '.php' );

		do_action( 'personalize_auth_message_' . $template_name );

		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Build an array of error messages using error codes from query param.
	 *
	 * @return array
	 */
	public static function resolve_error_codes() {
		$error_codes = [];

		if ( isset( $_GET['errors'] ) ) {
			// Error message codes will be given as GET parameter, as a comma separated list.
			$error_codes = explode( ',', $_GET['errors'] );
		}

		return $error_codes;
	}

	/**
	 * Build an array of error messages using error codes from query param.
	 *
	 * @return array
	 */
	public static function resolve_error_messages() {
		$errors = [];
		if ( isset( $_GET['errors'] ) ) {
			$error_codes = static::resolve_error_codes();

			// For each error code get the actual error message.
			foreach ( $error_codes as $code ) {
				$errors[] = static::get_error_message( $code );
			}
		}
		return $errors;
	}

	/**
	 * Get a string representing the message code.
	 *
	 * @return array
	 */
	public static function resolve_auth_message_code() {

		$code = '';

		if ( isset( $_GET['code'] ) ) {
			$code = $_GET['code'];
		}

		return $code;
	}

	/**
	 * Get a string related to authentication message.
	 *
	 * @return string|null
	 */
	public static function resolve_auth_message() {

		$auth_message = '';
		$code = static::resolve_auth_message_code();

		if ( $code ) {
			$auth_message = WP_AAIEduHr_Helper::get_message( $code );
		}

		return $auth_message;
	}

	/**
	 * Redirect to custom page and show appropriate message according to the provided parameters.
	 *
	 * @param string $code Message code.
	 * @param string $errors Comma separated list of error codes. Default is empty string.
	 * @param string $slug The slug of the page on which to show the error. Default is aaieduhr-auth.
	 */
	public static function show_message( $code, $errors = '', $slug = 'aaieduhr-auth' ) {

		$query_args['code'] = $code;

		if (! empty( $errors )){
			$query_args['errors'] = $errors;
		}

	/*
	 * Instead of redirecting to custom page always redirect to front page along with appropriate query args.
	 * $redirect_url = static::get_permalink_by_slug($slug, $query_args); */

		$redirect_url = static::get_site_url( $query_args );

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get permalink for page and add query params if needed.
	 *
	 * @param $slug Slug of the page.
	 *
	 * @param array $query_args Query arguments to add to the link.
	 *
	 * @return string Permalink
	 */
	public static function get_permalink_by_slug( $slug, $query_args = [] ) {

		$page = get_page_by_path( $slug );

		$page_permalink = get_permalink( $page );

		return add_query_arg( $query_args , $page_permalink );
	}

	/**
	 * Check if the user is created using AAI@EduHr authentication.
	 *
	 * @param int $user_id ID of the user.
	 *
	 * @return bool True if user is created using AAI@EduHr, false otherwise.
	 */
	public static function is_aaieduhr_account ( $user_id ) {
		return (bool) get_user_meta( $user_id, 'aaieduhr_account', true );
	}

	/**
	 * Add or update user metadata.
	 *
	 * @param int $user_id ID of the user.
	 * @param array $meta Key-value pairs to enter as user metadata.
	 */
	public static function do_update_user_meta( $user_id, $meta ) {

		// Prefix to be used for our metadata.
		$prefix = 'aaieduhr_';

		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $prefix . $key, $value );
		}

	}

	/**
	 * Display notice to let admins know if AAI@EduHr authentication is active or not.
	 *
	 * @param string $message
	 * @param string $class
	 *
	 */
	public static function display_notice( $message, $class ) {

		// Add action to display the notice.
		add_action( 'admin_notices', function () use ( $message, $class ) {
			static::display_notice_action( $message, $class );
		} );
	}

	/**
	 * Action which will print admin notice.
	 *
	 * @param string $message
	 * @param string $class
	 */
	protected static function display_notice_action( $message, $class ) {

		// Only show notice for users with administrative privileges.
		if ( current_user_can( 'manage_options') ) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}

	}
}
