<?php


class WP_AAIEduHr_Shortcodes {

	protected static $templates_dir = '';

	protected function __construct() {

	}

	public static function init() {
		add_shortcode( 'auth-message', array( static::class, 'render_auth_message' ) );
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
	public static function render_auth_message( $attributes, $content = null ) {

		// Some default attributes, to get us started.
		$default_attributes = array(
			'show_title'   => true,
			'auth_message' => 'No message...'
		);

		$attributes = shortcode_atts( $default_attributes, $attributes );

		// Resolve actual, main message (status of authentication).
		if ( isset( $_GET['code'] ) ) {
			$attributes['auth_message'] = WP_AAIEduHr_Helper::get_message( $_GET['code'] );
		}

		// Resolve error messages
		$errors = [];
		if ( isset( $_GET['errors'] ) ) {
			// Error message codes will be given as GET parameter, as a comma separated list.
			$error_codes = explode( ',', $_GET['errors'] );

			// For each error code get the actual error message.
			foreach ( $error_codes as $code ) {
				$errors[] = WP_AAIEduHr_Helper::get_error_message( $code );
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
		return WP_AAIEduHr_Helper::get_template_html( 'auth_message', $attributes );
	}

}
