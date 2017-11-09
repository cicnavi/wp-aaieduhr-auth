<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 12.10.2017.
 */

class WP_AAIEduHr_Bootstrap {

	/**
	 * Path to directory where all the files to include reside.
	 * @var string
	 */
	protected static $includes_dir = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

	/**
	 * Files to include. Order is important.
	 * @var array
	 */
	protected static $files_to_include = [
		'class-wp-aaieduhr-helper.php',
		'class-wp-aaieduhr-shortcodes.php',
		'class-wp-aaieduhr-options.php',
		'class-wp-aaieduhr-core.php',
	];

	/**
	 * Holds array of page definitions.
	 * @var array
	 */
	protected  static $page_definitions;

	/**
     * Plugin basename, used to define Settings link for plugin options.
	 * @var string
	 */
	protected static $plugin_basename;

	// Don't allow instantiation from outside.
	protected function __construct() {

	}

	/**
	 * Initialize the plugin.
     *
     * @param string The basename of the plugin, used for Settings link for plugin options.
	 */
	public static function init( $plugin_basename ) {

	    static::$plugin_basename = $plugin_basename;

		// Include necessary files.
		foreach ( static::$files_to_include as $file_name ) {
			require ( static::$includes_dir . $file_name );
		}

		static::prepare_plugin_page_definitions();

		// Initialize plugin shortcodes
		WP_AAIEduHr_Shortcodes::init();

		// Prepare plugin options.
		$options = new WP_AAIEduHr_Options( static::$plugin_basename );

		// Create plugin instance using available options.
		$core = new WP_AAIEduHr_Core($options);

		// Disable password resetting features.
		add_filter( 'allow_password_reset', array( static::class, 'disable_password_reset'), 10, 2 );
		add_filter( 'show_password_fields', array( static::class, 'disable_password_fields'), 10, 2 );
		add_action( 'login_form_lostpassword', array( static::class, 'show_disabled_password_manipulation_message' ) );
		add_action( 'login_form_retrievepassword', array( static::class, 'show_disabled_password_manipulation_message' ) );
		add_action( 'login_form_resetpass', array( static::class, 'show_disabled_password_manipulation_message' ) );
		add_action( 'login_form_rp', array( static::class, 'show_disabled_password_manipulation_message' ) );

		// Disable user registration
		add_action( 'login_form_register', array( static::class, 'show_registration_disabled_message' ) );

		// Remove some inputs when adding new users manually using the 'Add New User' form in WordPress.
		add_action('user_new_form', array( static::class, 'remove_unnecessary_input_when_creating_users'), 10, 2 );

	}

	/**
	 * Redirect and show message that working with passwords is disabled, since we want to use AAI@EduHr
	 */
	public static function show_disabled_password_manipulation_message ( ) {
	    WP_AAIEduHr_Helper::show_message('error', 'disabled_password_manipulation' );
    }

	/**
	 * Redirect and show message that working with passwords is disabled, since we want to use AAI@EduHr
	 */
	public static function show_registration_disabled_message ( ) {
		WP_AAIEduHr_Helper::show_message('error', 'registration_disabled' );
	}

	/**
     * Remove some input in 'Add New User' form.
	 * @param string $form_version
	 */
	public static function remove_unnecessary_input_when_creating_users ( $form_version = '' ) {
		if ( 'add-new-user' == $form_version ):
			// Remove 'Send notification' option and Password fields, using jQuery.
			// Dirty, but the original form is hardcoded :/.
		?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#send_user_notification').parents('td').parents('tr').remove();
                    $('.user-pass1-wrap').hide();
				});
			</script>
		<?php
		endif;
	}

	/**
	 * Disable password reset feature for accounts created using AAI@EduHr.
	 *
	 */
	public static function disable_password_reset( $allow, $user_id ) {
		// Disable password resets for all users.
		return false;
		// TODO mivanci Consider: Disable password reset if account is created using AAI@EduHr
		// return ! WP_AAIEduHr_Helper::is_aaieduhr_account( $user_id );

	}

	/**
	 * Remove password reset fields on user edit page for accounts created using AAI@EduHr.
	 *
	 * @param boolean $show
	 * @param WP_User $user
	 *
	 * @return bool True or false.
	 */
	public static function disable_password_fields( $show, $user ) {
		// Disable password fields for all users.
		return false;
		// TODO mivanci Consider: Remove password reset fields for accounts created using AAI@EduHr.
		// return ! WP_AAIEduHr_Helper::is_aaieduhr_account( $user->ID );

	}

	/**
	 * Prepare plugin page definitions.
	 */
	protected static function prepare_plugin_page_definitions( ) {
		// Information needed for creating the plugin's pages
		static::$page_definitions = [
			'aaieduhr-auth' => [
				'title' => __( 'AAI@EduHr Auth', 'wp-aaieduhr-auth' ),
				'content' => '[auth-message]'
			],
		];
	}

	/**
	 * Plugin activation hook. Code to execute when plugin is activated.
	 */
	public static function plugin_activated( ) {

		// Create defined pages.
		foreach ( static::$page_definitions as $slug => $page ) {
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
	 * Code to execute when plugin is deactivated.
	 */
	public static function plugin_deactivated() {
		// Delete all created pages.
		foreach ( static::$page_definitions as $slug => $page_definition ) {
			// Get the defined page.
			$page = get_page_by_path( $slug );
			if ($page) {
				wp_delete_post( $page->ID, true );
			}
		}

		/*
		// Consider doing something with all of the users created when plugin was active.
		// Get all users created using AAI@EduHr authentication.
		$users = get_users([
			'meta_key' => 'aaieduhr_account',
			'meta_value' => true
		]);
		*/

		/*
		// Consider deleting users created using AIA@EduHr.
		foreach ($users as $user) {
		    // Beware that this will also delete their posts since we don't provide the $reassign
		    // parameter (the ID of the user to which to assign all post from the user to be deleted).
			wp_delete_user($user->ID, $reassign = false);
		}
		*/

		/*
		// Consider resseting their passwords, so that they won't be able to use local WP authentication. This is necessary since
		// users can reset their passwords using WP account management.
		foreach ($users as $user) {
			wp_set_password(wp_generate_password(), $user->ID);
		}
		*/

		/*
		// Consider deleting roles for users created using AAI@EduHr.
		// By removing roles, user won't be able to do anything on th site.
		// The problem with created users can arise if the user chooses to reset his local WordPress password,
		// which will effectively enable him to login even if the plugin has been deactivated.
		foreach ($users as $user) {
			// Delete role
			update_user_meta($user->ID, 'wp_capabilities', []);
		}
		*/

	}
}