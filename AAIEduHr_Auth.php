<?php
/**
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

require('AAIEduHr_Options.php');

class AAIEduHr_Auth {

	// Path to the SSP package.
	protected $ssp_path = 'E:/projects/simplesamlphp/www/_include.php';

	protected $options;

	public function __construct() {

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

		require_once($this->ssp_path);

		$ssp = new SimpleSAML_Auth_Simple('fedlab-sp');

		if ( ! $ssp->isAuthenticated() ) {
			$ssp->requireAuth();
		} else {
			$attributes = $ssp->getAttributes();
		}

		$email = 'marko.ivancic@srce.hr';

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
		require_once($this->ssp_path);

		$ssp = new SimpleSAML_Auth_Simple('fedlab-sp');

		if ($ssp->isAuthenticated() ) {
			$ssp->logout( home_url() );
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
			wp_redirect( home_url() );
		}
	}

}

// Ensure that the plugin is run under Wordpress.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$aaieduhr_auth = new AAIEduHr_Auth();

// Register the plugin activation hook.
register_activation_hook( __FILE__, array( $aaieduhr_auth, 'plugin_activated' ) );


