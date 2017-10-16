<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 13.10.2017.
 */

class WP_AAIEduHr_Helper {

	/**
	 * Get the message for the given message code.
	 *
	 * @param $message_code
	 *
	 * @return string|void
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

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'wp-aaieduhr-auth' );
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
	 * Redirect to custom page and show appropriate message according to the provided parameters.
	 *
	 * @param string $code Message code.
	 * @param string $errors Comma separated list of error codes. Default is empty string.
	 * @param string $slug The slug of the page on which to show the error. Default is aaieduhr-auth.
	 */
	public static function show_message( $code, $errors = '', $slug = 'aaieduhr-auth' ) {

		$query_args['code'] = $code;

		if ( isset( $errors ) && ! empty( $errors ) ){
			$query_args['errors'] = $errors;
		}

		$redirect_url = static::get_permalink_by_slug($slug, $query_args);

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

		$redirect_url = add_query_arg( $query_args , $page_permalink );

		return $redirect_url;
	}
}