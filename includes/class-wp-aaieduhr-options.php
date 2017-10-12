<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 15.09.2017.
 */

/**
 * Class used to manage plugin options.
 *
 * Class WP_AAIEduHr_Options
 */
class WP_AAIEduHr_Options {

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
	public $are_valid = false;

	/**
     * Message about option validity.
     *
	 * @var string
	 */
	public $validation_message = '';

	public function __construct() {

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
		// 'option_group' is the same as the menu slug used for the plugin settings page.
		register_setting( 'WP_AAIEduHr_Auth_Settings', 'wp_aaieduhr_auth_settings' );

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
			__( 'Service type', 'WP_AAIEduHr_Auth' ),
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
               name='wp_aaieduhr_auth_settings[simplesamlphp_path]' value='<?php echo $this->data['simplesamlphp_path']; ?>'>

        <p class="description">For example: /var/www/simplesamlphp/lib/_autoload.php</p>
		<?php

	}

	public function render_input_for_service_type(  ) {
		?>
        <input type='text' name='wp_aaieduhr_auth_settings[service_type]' value='<?php echo $this->data['service_type']; ?>'>
        <p class="description">Valid options are: fedlab-sp or default-sp</p>
		<?php

	}


	public function render_input_for_should_create_new_users(  ) {
		?>
        <input type='checkbox' name='wp_aaieduhr_auth_settings[should_create_new_users]'
			<?php checked( isset($this->data['should_create_new_users']) && $this->data['should_create_new_users'] == '1'); ?>
               value='1'>
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
        // simpleSAMLPhp path should be valid, file should exists.
        if ( ! file_exists($this->data['simplesamlphp_path'])) {
            $this->validation_message = __('Can not find simpleSAMLphp file.', 'wp-aaieduhr-auth');
            return;
        }

        // Service type must be valid.
        $validServiceTypes = ['fedlab-sp', 'default-sp'];
        if ( ! in_array($this->data['service_type'], $validServiceTypes)) {
            $this->validation_message = __('Service type is not valid.', 'wp-aaieduhr-auth');
            return;
        }

        // Simply ensure valid option for user creation.
        if ( isset($this->data['should_create_new_users']) ) {
	        $this->data['should_create_new_users'] = 1;
        }
        else {
	        $this->data['should_create_new_users'] = 0;
        }

        $this->are_valid = true;
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
		if ( current_user_can( 'manage_options' ) ) {
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
}