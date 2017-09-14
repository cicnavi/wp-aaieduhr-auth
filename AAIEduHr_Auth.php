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

class AAIEduHr_Auth {

	// Path to the SSP package.
	protected $ssp_path = 'E:/projects/simplesamlphp/www/_include.php';

	public function __construct() {
		// Add custom login execution.
		add_action( 'login_form_login', array( $this, 'redirect_to_custom_login' ) );
		//add_action( 'login_init', array( $this, 'redirect_to_custom_login' ) );

		// Hook to logout
		add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );

		// Remove default authentication filters.
		remove_filter( 'authenticate', 'wp_authenticate_username_password');
		remove_filter( 'authenticate', 'wp_authenticate_email_password');
		remove_filter( 'authenticate', 'wp_authenticate_spam_check');

		// Add settings page
		add_action( 'admin_menu', [$this, 'aaieduhr_auth_add_admin_menu'] );
		add_action( 'admin_init', [$this, 'aaieduhr_auth_settings_init'] );

		// Add link to settings
		add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );
	}

	/**
	 * Plugin activation hook.
	 */
	public function plugin_activated() {
		// Code to execute when plugin is activated.
	}

	/**
	 * Add Settings link to plugins area
	 *
	 * @since bbPress (r2737)
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	public static function modify_plugin_action_links( $links, $file )
	{
		// New links to merge into existing links
		$new_links = array();

		// Settings page link
		if ( current_user_can( 'manage_options' ) ) {
			$new_links['settings'] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'aaieduhr_auth'), admin_url('options-general.php'))) . '">' . esc_html__( 'Settings', 'aaieduhr_auth' ) . '</a>';
		}

		// Add a few links to the existing links array
		return array_merge( $links, $new_links );
	}

	public function aaieduhr_auth_add_admin_menu(  ) {

		add_options_page( 'AAIEduHr Auth', 'AAIEduHr Auth', 'manage_options', 'aaieduhr_auth', array( $this, 'aaieduhr_auth_options_page') );

	}

	public function aaieduhr_auth_settings_init(  ) {

		register_setting( 'pluginPage', 'aaieduhr_auth_settings' );

		add_settings_section(
			'aaieduhr_auth_pluginPage_section',
			__( 'You need to have SimpleSamlPHP already configured...', 'aaieduhr_auth' ),
			array( $this, 'aaieduhr_auth_settings_section_callback'),
			'pluginPage'
		);

		add_settings_field(
			'aaieduhr_auth_text_field_0',
			__( 'Settings field description', 'aaieduhr_auth' ),
			array( $this, 'aaieduhr_auth_text_field_0_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

		add_settings_field(
			'aaieduhr_auth_text_field_1',
			__( 'Settings field description', 'aaieduhr_auth' ),
			array( $this, 'aaieduhr_auth_text_field_1_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

		add_settings_field(
			'aaieduhr_auth_checkbox_field_2',
			__( 'Settings field description', 'aaieduhr_auth' ),
			array( $this, 'aaieduhr_auth_checkbox_field_2_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

	}

	public function aaieduhr_auth_text_field_0_render(  ) {

		$options = get_option( 'aaieduhr_auth_settings' );
		?>
		<input type='text' name='aaieduhr_auth_settings[aaieduhr_auth_text_field_0]' value='<?php echo $options['aaieduhr_auth_text_field_0']; ?>'>
		<?php

	}


	public function aaieduhr_auth_text_field_1_render(  ) {

		$options = get_option( 'aaieduhr_auth_settings' );
		?>
		<input type='text' name='aaieduhr_auth_settings[aaieduhr_auth_text_field_1]' value='<?php echo $options['aaieduhr_auth_text_field_1']; ?>'>
		<?php

	}


	 public function aaieduhr_auth_checkbox_field_2_render(  ) {

		$options = get_option( 'aaieduhr_auth_settings' );
		?>
		<input type='checkbox' name='aaieduhr_auth_settings[aaieduhr_auth_checkbox_field_2]' <?php checked( isset($options['aaieduhr_auth_checkbox_field_2']), 1 ); ?> value='1'>
		<?php

	}


	public function aaieduhr_auth_settings_section_callback(  ) {

		echo __( 'test', 'aaieduhr_auth' );

	}


	public function aaieduhr_auth_options_page(  ) {

		?>
		<form action='options.php' method='post'>

			<h1>AAIEduHr Auth</h1>

			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );
			submit_button();
			?>

		</form>
		<?php

	}

	/**
	 * Notice to let admins know that AAI@EduHr authentication is active.
	 */
	public function display_plugin_notice() {
		$class = 'notice notice-warning';
		$message = 'AAI@EduHr plugin is activated. Users need to use AAI@EduHr identities to log in.';

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

add_action( 'admin_notices', array( $aaieduhr_auth, 'display_plugin_notice') );
