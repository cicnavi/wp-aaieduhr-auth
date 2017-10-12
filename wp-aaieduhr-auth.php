<?php
/**
 * User: Marko Ivančić marko.ivancic@srce.hr
 * Date: 7/27/2017
 *
 * Plugin Name:       WP AAI@EduHr Auth
 * Description:       Plugin that replaces the default WordPress authentication with SimpleSAMLphp AAI@EduHr.
 * Version:           0.0.1
 * Author:            Marko Ivančić marko.ivancic@srce.hr
 * Author URI:        http://markoivancic.from.hr/
 * License:           GPL-2.0+
 * Text Domain:       wp-aaieduhr-auth
 */

// TODO mivanci Napravi još i ovo:
// Napravi readme: https://generatewp.com/plugin-readme/
// Posebna datoteka za definiranje shortcodes
// Promjena text-domaina u prijevodima na 'wp-aaieduhr-auth'
// Uskladi nazive klasa, metoda, varijabli

// Ensure that the plugin is run under WordPress.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require( 'class-wp-aaieduhr-bootstrap.php' );

// Initialize plugin.
WP_AAIEduHr_Bootstrap::init();

// Register the plugin activation hook.
register_activation_hook( __FILE__, array( 'WP_AAIEduHr_Bootstrap', 'plugin_activated' ) );

