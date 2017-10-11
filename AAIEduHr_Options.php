<?php
/**
 * Created by PhpStorm.
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 15.09.2017.
 * Time: 13:54
 */

class AAIEduHr_Options {

	protected $data;

	public $areValid = false;

	public $validationMessage = '';

	public function __construct() {
		// Add settings page
		add_action( 'admin_menu', [$this, 'aaieduhr_auth_add_admin_menu'] );
		add_action( 'admin_init', [$this, 'aaieduhr_auth_settings_init'] );

		// Add link to settings
		add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		$this->data = get_option( 'aaieduhr_auth_settings' );

		$this->validate();

	}

	public function get() {
	    return $this->data;
    }

	protected function validate()
    {
        // simpleSAMLPhp path should be valid, file should exists.
        if ( ! file_exists($this->data['simplesamlphp_path'])) {
            $this->validationMessage = 'Can not find simpleSamlPhp file.';
            return;
        }

        // Service type must be valid.
        $validServiceTypes = ['fedlab-sp', 'default-sp'];
        if ( ! in_array($this->data['service_type'], $validServiceTypes)) {
            $this->validationMessage = 'Service type is not valid.';
            return;
        }

        if ( ! isset($this->data['should_create_new_users'])) {
	        $this->data['should_create_new_users'] = 0;
        }

        $this->areValid = true;
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

		add_options_page( 'AAI@EduHr Auth', 'AAI@EduHr Auth', 'manage_options', 'aaieduhr_auth', array( $this, 'aaieduhr_auth_options_page') );

	}

	public function aaieduhr_auth_settings_init(  ) {

		register_setting( 'pluginPage', 'aaieduhr_auth_settings' );

		add_settings_section(
			'aaieduhr_auth_pluginPage_section',
			__( 'Main configuration', 'aaieduhr_auth' ),
			array( $this, 'aaieduhr_auth_settings_section_callback'),
			'pluginPage'
		);

		add_settings_field(
			'simplesamlphp_path',
			__( 'Path to simpleSAMLphp', 'aaieduhr_auth' ),
			array( $this, 'simplesamlphp_path_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

		add_settings_field(
			'service_type',
			__( 'Service type', 'aaieduhr_auth' ),
			array( $this, 'service_type_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

		add_settings_field(
			'should_create_new_users',
			__( 'Create a user if it doesn\'t exist', 'aaieduhr_auth' ),
			array( $this, 'should_create_new_users_render'),
			'pluginPage',
			'aaieduhr_auth_pluginPage_section'
		);

	}

	public function simplesamlphp_path_render(  ) {
        ?>
		<input type='text'
		       class="regular-text"
		       name='aaieduhr_auth_settings[simplesamlphp_path]' value='<?php echo $this->data['simplesamlphp_path']; ?>'>

		<p class="description">For example: /var/www/simplesamlphp/lib/_autoload.php</p>
		<?php

	}


	public function service_type_render(  ) {
		?>
		<input type='text' name='aaieduhr_auth_settings[service_type]' value='<?php echo $this->data['service_type']; ?>'>
		<p class="description">Valid options are: fedlab-sp or default-sp</p>
		<?php

	}


	public function should_create_new_users_render(  ) {
		?>
		<input type='checkbox' name='aaieduhr_auth_settings[should_create_new_users]'
            <?php checked( isset($this->data['should_create_new_users']) && $this->data['should_create_new_users'] == '1'); ?>
               value='1'>
		<?php

	}


	public function aaieduhr_auth_settings_section_callback(  ) {

		$text = 'You should already have simpleSAMLphp configured. Please visit 
                    <a href="http://www.aaiedu.hr/za-davatelje-usluga/za-web-aplikacije/kako-implementirati-autentikaciju-putem-sustava-aaieduhr-u-php">
                    official AAI@EduHr webpage</a>
                    for more information.';

		echo __( $text, 'aaieduhr_auth' );

	}


	public function aaieduhr_auth_options_page(  ) {

		?>
        <div class="wrap">
            <form action='options.php' method='post'>

                <h1>AAIEduHr Auth</h1>

                <?php
                settings_fields( 'pluginPage' );
                do_settings_sections( 'pluginPage' );
                submit_button();
                ?>

            </form>
        </div>
		<?php

	}
}