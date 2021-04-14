<?php


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
		$core = new WP_AAIEduHr_Core( $options );

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

		// Remove password reset options
		add_action('edit_user_profile', array( static::class, 'remove_unnecessary_input_when_editing_users'), 10, 2 );
		add_filter('bulk_actions-users', array( static::class, 'remove_unnecessary_bulk_actions_when_listing_users' ), 10, 2 );
		add_filter('user_row_actions', array( static::class, 'remove_unnecessary_actions_when_listing_users' ), 10, 2 );

		// Add custom footer.
		add_action('wp_footer', array( static::class, 'add_footer_content') );

		// Enqueue styles
		add_action('wp_enqueue_scripts', array( static::class, 'add_styles') );

		// Modifications related to Multisite feature.
		if ( is_multisite() ) {
			// Ensure that label when adding existing users is always 'Email'
			add_action('user_new_form', array( static::class, 'rename_label_to_email'), 10, 2 );
			// Enable emails as usernames.
			add_filter('wpmu_validate_user_signup', array( static::class, 'enable_email_as_username'), 10, 2 );
		}
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
	 * Remove some input in 'Add New User' form.
	 * @param string $form_version
	 */
	public static function remove_unnecessary_input_when_editing_users ( $profileuser ) {
        // Remove 'Password Reset' option using jQuery.
        // Dirty, but the original form is hardcoded :/.
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#generate-reset-link').parents('td').parents('tr').remove();
                });
            </script>
		<?php
	}

	/**
     * Disable password resetting bulk action.
     *
	 * @param $actions
	 *
	 * @return mixed
	 */
	public static function remove_unnecessary_bulk_actions_when_listing_users( $actions ) {
	    // Disable bulk action for resetting password.
	    unset($actions['resetpassword']);
	    return $actions;
	}


	/**
	 * Disable password resetting for single user (on a single row).
	 *
	 * @param $actions
	 *
	 * @return mixed
	 */
	public static function remove_unnecessary_actions_when_listing_users( $actions, $user_object ) {
		// Disable action for resetting password.
		unset($actions['resetpassword']);
		return $actions;
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
		// NOTE Custom page creation for auth messages is disabled from 0.0.6
/*		foreach ( static::$page_definitions as $slug => $page ) {
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
		}*/
	}

	/**
	 * In MultiSite environment, ensure 'Email' label when adding existing users to site.
	 *
	 * @param string $form_version
	 */
	public static function rename_label_to_email( $form_version = '' )
	{
		// Only apply when adding existing users.
		if ( 'add-existing-user' == $form_version ):

			// Ensure that label is always 'Email'.
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('label[for="adduser-email"]').text("Email");
				});
			</script>
		<?php
		endif;

	}

	/**
	 * @param $result
	 *
	 * @return mixed|void
	 */
	public static function enable_email_as_username( $result )
	{
		if ( is_wp_error( $result[ 'errors' ] ) && !empty( $result[ 'errors' ]->errors ) ) {

			$username_errors = $result[ 'errors' ]->get_error_messages('user_name');

			if ( ! empty( $username_errors)) {
				return static::wpmu_validate_user_signup($result['orig_username'], $result['user_email']);
			}
		}

		return $result;
	}

	/**
	 * This is a copy of original wpmu_validate_user_signup action, without a rule for lowercase letters (a-z) and numbers.
	 *
	 * @param $user_name
	 * @param $user_email
	 *
	 * @return mixed|void
	 */
	private static function wpmu_validate_user_signup( $user_name, $user_email )
	{
		global $wpdb;

		$errors = new WP_Error();

		$orig_username = $user_name;
		$user_name = preg_replace( '/\s+/', '', sanitize_user( $user_name, true ) );

// Code that disabled emails to be used as usernames
//	if ( $user_name != $orig_username || preg_match( '/[^a-z0-9]/', $user_name ) ) {
//		$errors->add( 'user_name', __( 'Usernames can only contain lowercase letters (a-z) and numbers.' ) );
//		$user_name = $orig_username;
//	}

		$user_email = sanitize_email( $user_email );

		if ( empty( $user_name ) )
			$errors->add('user_name', __( 'Please enter a username.' ) );

		$illegal_names = get_site_option( 'illegal_names' );
		if ( ! is_array( $illegal_names ) ) {
			$illegal_names = array(  'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator' );
			add_site_option( 'illegal_names', $illegal_names );
		}
		if ( in_array( $user_name, $illegal_names ) ) {
			$errors->add( 'user_name',  __( 'Sorry, that username is not allowed.' ) );
		}

		/** This filter is documented in wp-includes/user.php */
		$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

		if ( in_array( strtolower( $user_name ), array_map( 'strtolower', $illegal_logins ) ) ) {
			$errors->add( 'user_name',  __( 'Sorry, that username is not allowed.' ) );
		}

		if ( ! is_email( $user_email ) ) {
			$errors->add( 'user_email', __( 'Please enter a valid email address.' ) );
		} elseif ( is_email_address_unsafe( $user_email ) ) {
			$errors->add( 'user_email', __( 'You cannot use that email address to signup. We are having problems with them blocking some of our email. Please use another email provider.' ) );
		}

		if ( strlen( $user_name ) < 4 )
			$errors->add('user_name',  __( 'Username must be at least 4 characters.' ) );

		if ( strlen( $user_name ) > 60 ) {
			$errors->add( 'user_name', __( 'Username may not be longer than 60 characters.' ) );
		}

		// all numeric?
		if ( preg_match( '/^[0-9]*$/', $user_name ) )
			$errors->add('user_name', __('Sorry, usernames must have letters too!'));

		$limited_email_domains = get_site_option( 'limited_email_domains' );
		if ( is_array( $limited_email_domains ) && ! empty( $limited_email_domains ) ) {
			$emaildomain = substr( $user_email, 1 + strpos( $user_email, '@' ) );
			if ( ! in_array( $emaildomain, $limited_email_domains ) ) {
				$errors->add('user_email', __('Sorry, that email address is not allowed!'));
			}
		}

		// Check if the username has been used already.
		if ( username_exists($user_name) )
			$errors->add( 'user_name', __( 'Sorry, that username already exists!' ) );

		// Check if the email address has been used already.
		if ( email_exists($user_email) )
			$errors->add( 'user_email', __( 'Sorry, that email address is already used!' ) );

		// Has someone already signed up for this username?
		$signup = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->signups WHERE user_login = %s", $user_name) );
		if ( $signup != null ) {
			$registered_at =  mysql2date('U', $signup->registered);
			$now = current_time( 'timestamp', true );
			$diff = $now - $registered_at;
			// If registered more than two days ago, cancel registration and let this signup go through.
			if ( $diff > 2 * DAY_IN_SECONDS )
				$wpdb->delete( $wpdb->signups, array( 'user_login' => $user_name ) );
			else
				$errors->add('user_name', __('That username is currently reserved but may be available in a couple of days.'));
		}

		$signup = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->signups WHERE user_email = %s", $user_email) );
		if ( $signup != null ) {
			$diff = current_time( 'timestamp', true ) - mysql2date('U', $signup->registered);
			// If registered more than two days ago, cancel registration and let this signup go through.
			if ( $diff > 2 * DAY_IN_SECONDS )
				$wpdb->delete( $wpdb->signups, array( 'user_email' => $user_email ) );
			else
				$errors->add('user_email', __('That email address has already been used. Please check your inbox for an activation email. It will become available in a couple of days if you do nothing.'));
		}

		$result = array('user_name' => $user_name, 'orig_username' => $orig_username, 'user_email' => $user_email, 'errors' => $errors);

		/**
		 * Filters the validated user registration details.
		 *
		 * This does not allow you to override the username or email of the user during
		 * registration. The values are solely used for validation and error handling.
		 *
		 * @since MU (3.0.0)
		 *
		 * @param array $result {
		 *     The array of user name, email and the error messages.
		 *
		 *     @type string   $user_name     Sanitized and unique username.
		 *     @type string   $orig_username Original username.
		 *     @type string   $user_email    User email address.
		 *     @type WP_Error $errors        WP_Error object containing any errors found.
		 * }
		 */
		return apply_filters( 'aaieduhr_wpmu_validate_user_signup', $result );
	}

	public static function add_footer_content()
	{
		// Resolve error messages
		$error_codes = WP_AAIEduHr_Helper::resolve_error_codes();

		// Resolve auth messages
		$auth_message_code = WP_AAIEduHr_Helper::resolve_auth_message_code();

		// Show appropriate alert with resolved messages.
		if ( ! empty( $error_codes ) || $auth_message_code != '' ) {
			echo '<div class="aaieduhr-alert">';
			echo '<span class="closebtn" onclick="this.parentElement.style.display=\'none\';">&times;</span>';
			echo '<p><strong>AAI@EduHr</strong></p>';

			if ($auth_message_code) {
				echo '<div class="' . WP_AAIEduHr_Helper::get_code_css_class( $auth_message_code ) . '">';
				echo WP_AAIEduHr_Helper::get_message( $auth_message_code ) . '<br>';
				echo '</div>';
			}

			if ( ! empty( $error_codes ) ) {
				foreach ( $error_codes as $error_code ) {
					echo '<div class="' . WP_AAIEduHr_Helper::get_code_css_class( $error_code ) . '">';
					echo WP_AAIEduHr_Helper::get_error_message( $error_code ) . '<br>';
					echo '</div>';
				}
			}
			echo '</div>';
		}

	}

	/**
	 * Enqueue custom CSS.
	 */
	public static function add_styles() {
		wp_register_style('wp-aaieduhr-auth-styles', plugin_dir_url(static::$plugin_basename) . 'css/styles.css' );
		wp_enqueue_style('wp-aaieduhr-auth-styles');
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
