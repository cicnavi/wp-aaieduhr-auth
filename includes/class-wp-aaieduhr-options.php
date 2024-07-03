<?php


/**
 * Class used to manage plugin options.
 *
 * Class WP_AAIEduHr_Options
 */
class WP_AAIEduHr_Options {

	/**
	 * Option key for AAI@EduHr Authn Bypass Secret
	 */
	const KEY_AABS = 'aabs';

	/**
	 * Contains actual options.
	 *
	 * @var mixed|void
	 */
	protected $data;

	/**
	 * Indication if entered options are actually valid.
	 *
	 * @var bool
	 */
	public $are_valid = true;

	/**
	 * Message about option validity.
	 *
	 * @var string
	 */
	public $validation_message = ' >>';

	/**
	 * The basename of the plugin.
	 * @var
	 */
	protected $plugin_basename;

	/**
	 * WP_AAIEduHr_Options constructor.
	 *
	 * @param string $plugin_basename The basename of the plugin.
	 */
	public function __construct( $plugin_basename ) {

		$this->plugin_basename = $plugin_basename;

		// Initialize plugin settings
		add_action( 'admin_init', [$this, 'initialize_settings'] );

		// Add admin menu and plugin settings page.
		add_action( 'admin_menu', [$this, 'add_admin_menu'] );

		// Add link to settings (shown below the plugin name).
		add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		// Get current settings so we can work with them.
		$this->data = get_option( 'wp_aaieduhr_auth_settings' );

		// Validate current settings.
		$this->validate();

	}

	/**
	 * Initialize plugin settings.
	 */
	public function initialize_settings(  ) {

		// Register the plugin settings.
		// 'option_group' is the same as the menu slug used for the plugin settings page. Also include a call for basic
		// data sanitization.
		register_setting( 'WP_AAIEduHr_Auth_Settings', 'wp_aaieduhr_auth_settings', [$this, 'sanitize_input'] );

		// For convenience, let's use section to group our main configuration options.
		add_settings_section(
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration',
			__( 'Main configuration', 'wp-aaieduhr-auth' ),
			array( $this, 'generate_settings_section_main_configuration'),
			'WP_AAIEduHr_Auth_Settings'
		);

		// Register setting fields to a settings page and section.
		add_settings_field(
			'simplesamlphp_path',
			__( 'Path to simpleSAMLphp', 'wp-aaieduhr-auth' ),
			array( $this, 'render_input_for_simplesamlphp_path'),
			'WP_AAIEduHr_Auth_Settings',
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration'
		);

		add_settings_field(
			'service_type',
			__( 'Service type', 'wp-aaieduhr-auth' ),
			array( $this, 'render_input_for_service_type'),
			'WP_AAIEduHr_Auth_Settings',
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration'
		);

		add_settings_field(
			'should_create_new_users',
			__( 'Create a user if it doesn\'t exist', 'wp-aaieduhr-auth' ),
			array( $this, 'render_input_for_should_create_new_users'),
			'WP_AAIEduHr_Auth_Settings',
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration'
		);

		add_settings_field(
			'allowed_realms',
			__( 'Allowed realms', 'wp-aaieduhr-auth' ),
			array( $this, 'render_input_for_allowed_realms'),
			'WP_AAIEduHr_Auth_Settings',
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration'
		);

		add_settings_field(
			self::KEY_AABS,
			__( 'AAI@EduHr Auth Bypass Secret', 'wp-aaieduhr-auth' ),
			array( $this, 'render_input_for_aabs'),
			'WP_AAIEduHr_Auth_Settings',
			'wp_aaieduhr_auth_plugin_settings_page_main_configuration'
		);

	}

	/**
	 * Sanitize input data for options.
	 *
	 * @param $input
	 *
	 * @return array
	 */
	public function sanitize_input( $input ) {
		// Create our array for storing the sanitized options
		$output = array();

		// Loop through each of the incoming options
		foreach( $input as $key => $value ) {

			// Check to see if the current option has a value. If so, process it.
			if( isset( $input[$key] ) ) {

				$output[$key] = esc_attr( $input[ $key ] );

			}

		}

		return $output;
	}

	/**
	 * Callback used to generate HTML for main configuration settings section.
	 */
	public function generate_settings_section_main_configuration(  ) {

		$text = '<b>Note:</b> You should already have simpleSAMLphp configured. Please visit 
					<a href="http://www.aaiedu.hr/za-davatelje-usluga/za-web-aplikacije/kako-implementirati-autentikaciju-putem-sustava-aaieduhr-u-php">
					official AAI@EduHr webpage</a>
					for more information.';

		_e( $text, 'wp-aaieduhr-auth' );

	}

	public function render_input_for_simplesamlphp_path(  ) {
		?>
		<input type='text'
			   class="regular-text"
			   name='wp_aaieduhr_auth_settings[simplesamlphp_path]'
			   value='<?php echo isset($this->data['simplesamlphp_path']) ? $this->data['simplesamlphp_path'] : ''; ?>'>

		<p class="description">
			<?php _e('For example: /var/www/simplesamlphp/src/_autoload.php','wp-aaieduhr-auth'); ?>
		</p>
		<?php

	}

	public function render_input_for_service_type(  ) {
		?>
		<input type='text'
			   name='wp_aaieduhr_auth_settings[service_type]'
			   value='<?php echo isset($this->data['service_type']) ? $this->data['service_type'] : ''; ?>'>
		<p class="description">
		   <?php _e('Valid options are: fedlab-sp or default-sp','wp-aaieduhr-auth'); ?>
		</p>
		<?php
	}


	public function render_input_for_should_create_new_users(  ) {
		?>
		<input type='checkbox' name='wp_aaieduhr_auth_settings[should_create_new_users]'
				<?php
				checked( isset($this->data['should_create_new_users']) && $this->data['should_create_new_users'] == '1');
				?>
			   value='1'>
		<p class="description">
			<?php
				_e('Check this option if you want to automatically create local users which are successfully authenticated trough AAI@EduHr. <br>
				Uncheck it if you want to manually create local users which are then allowed to authenticate trough AAI@EduHr
				(if you want to use standard WordPress user administration to allow only specific users). <br>', 'wp-aaieduhr-auth');
			?>

		</p>
		<?php

	}

	public function render_input_for_allowed_realms(  ) {
		?>
		<input type='text'
			   class="regular-text"
			   name='wp_aaieduhr_auth_settings[allowed_realms]'
			   value='<?php echo isset($this->data['allowed_realms']) ? implode(', ', $this->data['allowed_realms']) : ''; ?>'>
		<p class="description">
			<?php

				_e('Leave empty if users from any realm are allowed to authenticate trough AAI@EduHr.<br>
					If you want to limit authentication to specific realms, enter comma separated list of realms. <br>
					For example, to limit authentication only to srce.hr and sfzg.hr realms, enter: srce.hr, sfzg.hr', 'wp-aaieduhr-auth');
			?>
		</p>
		<?php

	}

	/**
	 * aabs - AAI@EduHr Auth Bypass Secret
	 * @return void
	 */
	public function render_input_for_aabs(  ) {
		?>
        <input type='text'
               class="regular-text"
               name='wp_aaieduhr_auth_settings[aabs]'
               value='<?php echo isset($this->data[self::KEY_AABS]) ? $this->data[self::KEY_AABS] : ''; ?>'>
        <p class="description">
			<?php

			_e('Secret which can be used to bypass AAI@EduHr authentication, so that a user can authenticate using
                    regular WordPress user / login form. <br>
                    This can be used in scenarios when a site maintainer does not have AAI@EduHr identity, but has to 
                    be able to, for example, get to the site admin dashboard.  <br>
                    To show WordPress login form, set \'aabs\' query parameter in wp-login route, like:
                    /wp-login.php?aabs=some-secret<br>
                    Make sure that the secret is long-enough, hard-to-guess and with no chars which have special meaning in URLs. 
                    ', 'wp-aaieduhr-auth');

			if (!isset($this->data[self::KEY_AABS]) || empty($this->data[self::KEY_AABS])) {
				_e('<br>Example secret to use: ' . wp_generate_password(32, false), 'wp-aaieduhr-auth');
			}
			?>
        </p>
		<?php

	}

	/**
	 * Add admin menu for plugin and generate page for plugin options.
	 */
	public function add_admin_menu(  ) {

		add_options_page(
			'WP AAI@EduHr Auth',
			'WP AAI@EduHr Auth',
			'manage_options',
			'WP_AAIEduHr_Auth_Settings',
			array( $this, 'generate_options_page') );

	}

	/**
	 * Get current options.
	 *
	 * @return mixed|void
	 */
	public function get() {

		return $this->data;

	}

	/**
	 * Validate current options.
	 *
	 */
	protected function validate()
	{
		// simpleSAMLphp path should be valid, file should exist, and simpleSAMLphp class should be loaded.
		if ( ! isset($this->data['simplesamlphp_path']) ||
			 ! file_exists($this->data['simplesamlphp_path']) ||
			 ! is_file($this->data['simplesamlphp_path']) ||
			 ! $this->load_simpleSAMLphp( ) ) {
			$this->validation_message .= __(' Can not load simpleSAMLphp.', 'wp-aaieduhr-auth');
			$this->are_valid = false;
		}

		// Service type must be valid.
		$valid_service_types = ['fedlab-sp', 'default-sp'];
		if ( ! isset($this->data['service_type']) || ! in_array($this->data['service_type'], $valid_service_types)) {
			$this->validation_message .= __(' Service type is not valid.', 'wp-aaieduhr-auth');
			$this->are_valid = false;
		}

		// Simply ensure valid option for user creation.
		if ( isset($this->data['should_create_new_users']) ) {
			$this->data['should_create_new_users'] = 1;
		}
		else {
			$this->data['should_create_new_users'] = 0;
		}

		// Resolve allowed realms.
		// First ensure that there arno no spaces, to be sure.
		if ( isset( $this->data['allowed_realms'] ) ) {
			$this->data['allowed_realms'] = trim($this->data['allowed_realms']);
		}
		else {
			$this->data['allowed_realms'] = '';
		}
		// Check to see if string is empty, and only then consider using specified realms.
		if ( ! empty( $this->data['allowed_realms'] ) ) {

			// In case there are multiple realms separated by comma, get all realms.
			$realms = [];
			foreach ( explode(',', $this->data['allowed_realms']) as $realm) {
				$realms[] = trim($realm);
			}
			$this->data['allowed_realms'] = $realms;
		}
		else {
			// All realms are allowed (string is empty), ensure that we have an empty array.
			$this->data['allowed_realms'] = [];
		}
	}

	/**
	 * Add Settings link shown below the plugin name in the list of installed plugins.
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	public function modify_plugin_action_links( $links, $file )
	{
		// New links to merge into existing links
		$new_links = array();

		// Settings page link
		if ( $this->plugin_basename == $file && current_user_can( 'manage_options' ) ) {
			$new_links['settings'] = '<a href="' .
									 esc_url( add_query_arg( array( 'page' => 'WP_AAIEduHr_Auth_Settings' ),
										 admin_url('options-general.php'))) . '">' .
									 esc_html__( 'Settings', 'wp-aaieduhr-auth' ) . '</a>';
		}

		// Add new links to the existing links array
		return array_merge( $links, $new_links );
	}

	/**
	 * Callback to generate HTML for plugin options page.
	 */
	public function generate_options_page(  ) {

		?>
		<div class="wrap">
			<form action='options.php' method='post'>

				<h1>WP AAI@EduHr Auth</h1>

				<?php
				do_settings_sections( 'WP_AAIEduHr_Auth_Settings' );
				settings_fields( 'WP_AAIEduHr_Auth_Settings' );
				submit_button();
				?>

			</form>
		</div>
		<?php

	}

	/**
	 * Check if class needed to use simpleSAMLphp exists.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	private function load_simpleSAMLphp( ) {
		require_once( $this->data['simplesamlphp_path'] );
		return class_exists( \SimpleSAML\Auth\Simple::class);
	}
}
