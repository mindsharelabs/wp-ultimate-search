<?php
/**
 * mindshare-updater.php
 *
 * Provides a simple way to add automatic updates to premium themes and plugins.
 * Interacts with our remote repository API: https://mindsharelabs.com/update/
 *
 * @version      0.5.1
 * @created      4/25/13 12:44 AM
 * @author       Mindshare Studios, Inc.
 * @copyright    Copyright (c) 2013
 * @link         http://www.mindsharelabs.com/documentation/
 *
 * @see          http://goo.gl/tUpSc (thanks to Abid Omar for the skeleton)
 *
 * @todo         change error output to use wp_die or WP_Error
 *
 * @changelog:
 *
 *        0.5.1 - bug fix for premium products not sending key / email to API server
 *        0.5 - split updater and license checker into separate classes to avoid issues with WPUS
 *        0.4.2 - bugfixes for update mechanism
 *        0.4.1 - added toString method to retrieve current version number
 *        0.4 - added call to get remote zip file URL, get_remote_url
 *
 */

// make sure the plugin is available
if(!function_exists('is_plugin_active')) {
	require_once(ABSPATH.'wp-admin/includes/plugin.php');
}

if(!class_exists('mindshare_updater')) :

	/**
	 * Class mindshare_updater
	 *
	 * Parent class for mindshare_auto_update and mindshare_license_check
	 * Contains shared variables and functions for child classes.
	 *
	 */
	class mindshare_updater {

		/**
		 * This version number for the Mindshare Auto Update library
		 * This value is returned when this class or its children if they are
		 * treated as a string (via __toString())
		 *
		 * @var
		 */
		public $class_version = '0.5.1';

		/**
		 * The plugin remote update API web service
		 *
		 * @var string
		 */
		public $update_server_uri = 'https://mindsharelabs.com/update/';

		/**
		 * @var string
		 */
		public $hash = 'cdd96d3cc73d1dbdaffa03cc6cd7339b';

		/**
		 *
		 * The version number for this class (Mindshare Auto Update)
		 *
		 * @return string
		 */
		public function __toString() {
			return $this->class_version;
		}
	}
endif;
