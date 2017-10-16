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

	// Don't allow instantiation from outside.
	protected function __construct() {

	}

	/**
	 * Initialize the plugin.
	 */
	public static function init() {

		// Include necessary files.
		foreach ( static::$files_to_include as $file_name ) {
			require ( static::$includes_dir . $file_name );
		}

		static::prepare_plugin_page_definitions();

		// Initialize plugin shortcodes
		WP_AAIEduHr_Shortcodes::init();

		// Prepare plugin options.
		$options = new WP_AAIEduHr_Options();

		// Create plugin instance using available options.
		$core = new WP_AAIEduHr_Core($options);

	}

	/**
	 * Prepare plugin page definitions.
	 */
	protected static function prepare_plugin_page_definitions() {
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
	public static function plugin_activated() {

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
			$page = get_page_by_path($slug);
			if ($page) {
				wp_delete_post($page->ID, true);
			}
		}

		/**
		 * Consider deleting users or removing roles from all users created using AAI@EduHr.
		 * By removing roles, user won't be able to do anything on th site.
		 * The problem with created users can arise if the user chooses to reset his password,
		 * once the plugin has been deactivated.
		 *
		 * $users = get_users([
		 * 	'meta_key' => 'aaieduhr_account',
		 * 	'meta_value' => true
		 * ]);
		 *
		 * foreach ($users as $user) {
		 *  // Delete role
		 * 	update_user_meta($user->ID, 'wp_capabilities', []);
		 *  // ...or delete user. Beware that this will also delete their posts if you don't provide the $reassign
		 *  // parameter (the ID of the user to which to assign all post from the user to be deleted).
		 *  wp_delete_user($user->ID, $reassign = false);
		 * }
		 *
		 *
		 */
	}
}