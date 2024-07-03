<?php
/**
 * WP AAI@EduHr Auth
 *
 * @package           WPAAI@EduHr
 * @author            Marko Ivancic
 * @license           GPL-3.0+
 *
 * @wordpress-plugin
 * Plugin Name:       WP AAI@EduHr Auth
 * Plugin URI:        https://wordpress.org/plugins/wp-aaieduhr-auth/
 * Description:       Plugin that replaces the default WordPress authentication with SimpleSAMLphp AAI@EduHr.
 * Version:           0.1.0
 * Author:            Marko Ivancic
 * Author URI:        https://markoivancic.from.hr/
 * License:           GPL-3.0+
 * Text Domain:       wp-aaieduhr-auth
 */

// Ensure that the plugin is run under WordPress.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Plugin basename is used to define Settings link for plugin options.
$plugin_basename = plugin_basename( __FILE__ );

require( 'class-wp-aaieduhr-bootstrap.php' );

// Initialize plugin.
WP_AAIEduHr_Bootstrap::init( $plugin_basename );

// Register the plugin activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'WP_AAIEduHr_Bootstrap', 'plugin_activated' ) );
register_deactivation_hook( __FILE__, array( 'WP_AAIEduHr_Bootstrap', 'plugin_deactivated' ) );
