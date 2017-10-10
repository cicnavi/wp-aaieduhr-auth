<?php
/**
 * https://code.tutsplus.com/tutorials/build-a-custom-wordpress-user-flow-part-1-replace-the-login-page--cms-23627
 *
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 7/27/2017
 * Time: 3:16 PM
 *
 * Plugin Name:       AAI@EduHr Auth
 * Description:       A plugin that replaces the WordPress login with SimpleSamlPHP AAI@Edu authentication.
 * Version:           0.0.1
 * Author:            Marko Ivančić marko.ivancic@srce.hr
 * Author URI:				http://markoivancic.from.hr/
 * License:           GPL-2.0+
 * Text Domain:       aaieduhr_auth
 */

// TODO mivanci Napravi još i ovo:
// Napravi readme: https://generatewp.com/plugin-readme/
// Uskladi ime direktorija s nazivom plugina.
// Prikaži poruku o prijavi na naslovnoj stranici.

require('AAIEduHr_Options.php');

class AAIEduHr_Auth {
	/**
     * Instance which ensures that valid options are set.
	 * @var AAIEduHr_Options
	 */
	protected $options;

	public function __construct() {


		//add_filter( 'the_content', [$this, 'my_the_post_action'] );

	    $this->options = new AAIEduHr_Options();

	    // If options are entered, apply AAI Auth.
	    if ($this->options->areValid) {
	        $this->apply();

		    add_action( 'admin_notices', function() {
			    $class = 'notice notice-success';
			    $message = 'AAI@EduHr Auth is applied, users need to use AAI@EduHr identities to log in.';
			    $this->display_plugin_notice($message,$class);
		    } );
        }
        else {

	        add_action( 'admin_notices', function() {
		        $class = 'notice notice-warning';
		        $message = 'AAI@EduHr is not applied, please check your settings. ' . $this->options->validationMessage;
		        $this->display_plugin_notice($message,$class);
	        } );

        }

		add_shortcode( 'auth-message', array( $this, 'render_auth_message' ) );

	}

	/**
	 * A shortcode for rendering the login form.
	 *
	 * @param  array   $attributes  Shortcode attributes.
	 * @param  string  $content     The text content for shortcode. Not used.
	 *
	 * @return string  The shortcode output
	 */
	public function render_auth_message( $attributes, $content = null ) {
		// Parse shortcode attributes
		$default_attributes = array(
			'show_title' => true,
			'auth_message' => 'No message'
		);

		$attributes = shortcode_atts( $default_attributes, $attributes );

		// Auth message
		if (isset($_GET['type'])) {
			switch ($_GET['type']) {
				case 'login':
                    $attributes['auth_message'] = __('Login successful.', 'aaieduhr');
                    break;
				case 'logout':
                    $attributes['auth_message'] = __('Logout successful.', 'aaieduhr');
					break;
				case 'error':
                    $attributes['auth_message'] = __('Oups, there was an error.', 'aaieduhr');
					break;

			}
		}

		// Error messages
		$errors = array();
		if ( isset( $_REQUEST['errors'] ) ) {
			$error_codes = explode( ',', $_REQUEST['errors'] );

			foreach ( $error_codes as $code ) {
				$errors[] = $this->get_error_message( $code );
			}
		}
		$attributes['errors'] = $errors;

		// Pass the redirect parameter to the WordPress login functionality: by default,
		// don't specify a redirect, but if a valid redirect URL has been passed as
		// request parameter, use it.
		$attributes['redirect'] = '';
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
		}

		// Render the login form using an external template
		return $this->get_template_html( 'auth_message', $attributes );
	}

	/**
	 * Finds and returns a matching error message for the given error code.
	 *
	 * @param string $error_code    The error code to look up.
	 *
	 * @return string               An error message.
	 */
	private function get_error_message( $error_code ) {
		switch ( $error_code ) {
			case 'empty_aai':
				return __( 'No AAI', 'personalize-login' );
			case 'empty_username':
				return __( 'You do have an email address, right?', 'personalize-login' );

			case 'empty_password':
				return __( 'You need to enter a password to login.', 'personalize-login' );

			case 'invalid_username':
				return __(
					"We don't have any users with that email address. Maybe you used a different one when signing up?",
					'personalize-login'
				);

			case 'incorrect_password':
				$err = __(
					"The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
					'personalize-login'
				);
				return sprintf( $err, wp_lostpassword_url() );

			default:
				break;
		}

		return __( 'An unknown error occurred. Please try again later.', 'personalize-login' );
	}

	/**
	 * Renders the contents of the given template to a string and returns it.
	 *
	 * @param string $template_name The name of the template to render (without .php)
	 * @param array  $attributes    The PHP variables for the template
	 *
	 * @return string               The contents of the template.
	 */
	private function get_template_html( $template_name, $attributes = null ) {
		if ( ! $attributes ) {
			$attributes = array();
		}

		ob_start();

		do_action( 'personalize_auth_message_' . $template_name );

		require( 'templates/' . $template_name . '.php');

		do_action( 'personalize_auth_message_' . $template_name );

		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public function my_the_post_action( $post_object ) {
		// var_dump($post_object); die();
        return $post_object = '<p>Test</p>';
	}

	protected function apply() {
		// Add custom login execution.
		add_action( 'login_form_login', array( $this, 'redirect_to_custom_login' ) );
		//add_action( 'login_init', array( $this, 'redirect_to_custom_login' ) );

		// Hook to logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// Remove default authentication filters.
		remove_filter( 'authenticate', 'wp_authenticate_username_password');
		remove_filter( 'authenticate', 'wp_authenticate_email_password');
		remove_filter( 'authenticate', 'wp_authenticate_spam_check');
    }

	/**
	 * Plugin activation hook.
	 */
	public function plugin_activated() {
		// Code to execute when plugin is activated.

		// Information needed for creating the plugin's pages
		$page_definitions = array(
			'aaieduhr-auth' => array(
				'title' => __( 'AAIEduHr Auth', 'aaieduhr' ),
				'content' => '[auth-message]'
			),
			'aaieduhr-account' => array(
				'title' => __( 'Your Account', 'aaieduhr' ),
				'content' => '[account-info]'
			),
		);

		foreach ( $page_definitions as $slug => $page ) {
			// Check that the page doesn't exist already
			$query = new WP_Query( 'pagename=' . $slug );
			if ( ! $query->have_posts() ) {
				// Add the page using the data from the array above
				wp_insert_post(
					array(
						'post_content'   => $page['content'],
						'post_name'      => $slug,
						'post_title'     => $page['title'],
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'ping_status'    => 'closed',
						'comment_status' => 'closed',
					)
				);
			}
		}
	}

	/**
	 * Notice to let admins know that AAI@EduHr authentication is active.
	 */
	public function display_plugin_notice($message, $class) {

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
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

		require_once($this->options->get()['simplesamlphp_path']);

		$ssp = new SimpleSAML_Auth_Simple($this->options->get()['service_type']);

		if ( ! $ssp->isAuthenticated() ) {
			$ssp->requireAuth();
		} else {
			$attributes = $ssp->getAttributes();
		}

		// var_dump($attributes); die();

		$email = 'marko.ivancic@srce.hr';

        // $email = $attributes['hrEduPersonUniqueID'][0];

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			wp_redirect( home_url() );
			return new WP_Error( 'broke', "I've fallen and can't get up" );
			exit;
		}
		// https://gist.github.com/cliffordp/e8d1d9f732328ba360ad
		wp_set_current_user($user->ID);
		wp_set_auth_cookie($user->ID);
		do_action('wp_login', $user->user_login);

		$this->redirect_logged_in_user();

		// We end the execution using an exit command because otherwise, the execution will continue
		// with the rest of the actions in wp-login.php
		exit;
	}

	/**
	 * Redirect to custom login page after the user has been logged out.
	 */
	public function redirect_after_logout() {
		require_once($this->options->get()['simplesamlphp_path']);

		$ssp = new SimpleSAML_Auth_Simple($this->options->get()['service_type']);

		if ($ssp->isAuthenticated() ) {
			$ssp->logout( add_query_arg('type', 'logout', home_url('aaieduhr-auth') ) );
		}

		exit;
	}

	/**
	 * Redirects the user to the correct page depending on whether he / she
	 * is an admin or not.
	 *
	 * @param string $redirect_to   An optional redirect_to URL for admin users
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
			wp_redirect(  add_query_arg('type', 'login', home_url('aaieduhr-auth') ) );
		}
	}

}

// Ensure that the plugin is run under Wordpress.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$aaieduhr_auth = new AAIEduHr_Auth();

// Register the plugin activation hook.
register_activation_hook( __FILE__, array( $aaieduhr_auth, 'plugin_activated' ) );


