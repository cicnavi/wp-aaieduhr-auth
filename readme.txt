=== WP AAI@EduHr Auth ===
Contributors: cicnavi
Tags: authentication, AAI@EduHr, Srce
Requires at least: 4.8.2
Tested up to: 6.1.1
Stable tag: 0.0.8
Requires PHP: 5.6.* or later
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

AAI@EduHr Authentication plugin for WordPress.

== Description ==
WP AAI@EduHr Auth plugin provides simple interface which enables you to utilize AAI@EduHr Authentication and Authorisation Infrastructure of science and higher education in Croatia in your WordPress installation. For more information about AAI@EduHr please visit [http://www.aaiedu.hr/](http://www.aaiedu.hr/).

== Installation ==

= Prerequisites =

Before you can start using WP AAI@EduHr Auth plugin, you already have to have simpleSAMLphp configured on your server.
Visit [official AAI@EduHr site](http://www.aaiedu.hr/za-davatelje-usluga/za-web-aplikacije/kako-implementirati-autentikaciju-putem-sustava-aaieduhr-u-php) for more information (instructions are in Croatian language).

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'WP AAI@EduHr Auth'
3. Activate WP AAI@EduHr Auth from your Plugins page.
4. Visit 'Settings > WP AAI@EduHr Auth' and enter appropriate settings. (You can always edit these later.)

= From WordPress.org =

1. Download WP AAI@EduHr Auth.
2. Upload the 'wp-aaieduhr-auth' directory to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate WP AAI@EduHr Auth from your Plugins page.
4. Visit 'Settings > WP AAI@EduHr Auth' and enter appropriate parameters. (You can always edit these later.)

== Changelog ==
= 0.0.8 =
* Remove password reset option on user edit

= 0.0.7 =
* Update plugin header comment

= 0.0.6 =
* In multisite environment ensure label 'Email' when adding existing users to the site.
* In multisite environment enable usernames to be emails when adding new users to the site.
* Disable custom page creation used for showing auth messages and errors.
* Enable authentication alerts used to show auth messages.

= 0.0.5 =
* Use email from 'email' attribute instead of using 'hrEduPersonUniqueID'

= 0.0.4 =
* Fix Settings link so it's only rendered for this plugin.

= 0.0.3 =
* Disable local password manipulation when plugin is active.
* Ensure that manually created account is marked as AAI@EduHr accounts when user logs in for the first time.

= 0.0.2 =
* First version.

== Upgrade Notice ==
