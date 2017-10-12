<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 12.10.2017.
 */

class WP_AAIEduHr_Bootstrap {

	protected static $includes_dir = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

	protected static $files_to_include = [
		'class-wp-aaieduhr-options.php',
		'class-wp-aaieduhr-core.php',
	];

	protected function __construct() {

	}

	public static function init() {

		foreach (static::$files_to_include as $file_name) {
			require ( static::$includes_dir . $file_name );
		}

		// Get options instance.
		$options = new WP_AAIEduHr_Options();

		// Create plugin instance.
		$core = new WP_AAIEduHr_Core($options);

	}

	/**
	 * Plugin activation hook.
	 */
	public static function plugin_activated() {
		// Code to execute when plugin is activated.

		// Information needed for creating the plugin's pages
		$page_definitions = array(
			'aaieduhr-auth'    => array(
				'title'   => __( 'AAIEduHr Auth', 'aaieduhr' ),
				'content' => '[auth-message]'
			),
			'aaieduhr-account' => array(
				'title'   => __( 'Your Account', 'aaieduhr' ),
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
}