<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 13.10.2017.
 */

class WP_AAIEduHr_Helper {

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
					but your account is currently not allowed to enter (new user creation is disabled).', 'wp-aaieduhr-auth' );

			case 'username_exists':
				return __( 'Username is already taken.', 'wp-aaieduhr-auth' );

			case 'email_invalid':
				return __( 'Email is not valid.', 'wp-aaieduhr-auth' );

			case 'existing_user_email':
				return __( 'Email is already used.', 'wp-aaieduhr-auth' );

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'wp-aaieduhr-auth' );
	}

}