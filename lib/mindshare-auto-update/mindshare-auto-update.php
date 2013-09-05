<?php
/**
 * mindshare-auto-update.php
 *
 * Provides a simple way to add automatic updates to premium themes and plugins.
 * Interacts with our remote repository API: https://mindsharelabs.com/update/
 *
 * @created      4/25/13 12:44 AM
 * @author       Mindshare Studios, Inc.
 * @copyright    Copyright (c) 2013
 * @link         http://www.mindsharelabs.com/documentation/
 *
 */

if(!class_exists('mindshare_license_check')) {
	require_once('mindshare-license-check.php');
}

if(!class_exists('mindshare_auto_update')) :
	class mindshare_auto_update extends mindshare_license_check {

		/**
		 * Initialize a new instance of the WordPress Auto-Update class
		 *
		 * @param        $plugin_slug
		 * @param        $target_dir
		 * @param string $update_server_uri
		 *
		 * @param null   $key
		 * @param null   $email
		 *
		 * @internal param string $current_version
		 * @internal param string $this->plugin_slug
		 */
		function __construct($plugin_slug, $target_dir, $update_server_uri = NULL, $key = NULL, $email = NULL) {
			$debug = FALSE;

			parent::__construct($plugin_slug, $target_dir, $update_server_uri, $key, $email);

			// define the alternative API for updating checking
			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

			// Define the alternative response for plugin information requests
			add_filter('plugins_api', array($this, 'check_info'), 10, 3);

			if($debug) {
				set_site_transient('update_plugins', NULL);
				add_filter('pre_set_site_transient_update_plugins', array($this, 'display_transient_update_plugins'));
				$this->check_update(get_site_transient('update_plugins'));
			}

		}

		function display_transient_update_plugins($transient) {
			if($transient !== FALSE) {
				var_dump($transient);
			} else {
				wp_die('<h1>$transient not set</h1>');
			}
		}

		/**
		 * Add our self-hosted auto-update plugin to the filter transient
		 *
		 * @param object $transient
		 *
		 * @return bool|object $transient
		 */
		public function check_update($transient) {

			if(empty($transient->checked)) {
				return $transient;
			}

			// Get the remote version / info
			$arg = new stdClass();
			$arg->slug = $this->slug;
			$information = $this->check_info(NULL, 'plugin_information', $arg);

			if($information) {
				// If a newer version is available, add the update
				if(version_compare($this->current_version, $information->new_version, '<')) {
					$obj = new stdClass();
					$obj->slug = $this->slug;
					$obj->new_version = $information->new_version;
					$obj->url = $this->update_server_uri;
					$obj->package = $information->download_link;
					$transient->response[$this->plugin_slug] = $obj;
				}
				return $transient;
			} else {
				return FALSE;
			}
		}

		/**
		 * Add our self-hosted description to the filter
		 *
		 * @param boolean $false
		 * @param array   $action
		 * @param object  $arg
		 *
		 * @return bool|object
		 */

		public function check_info($false, $action, $arg) {
			if($arg->slug === $this->slug) {
				$information = $this->get_remote_information($this->key, $this->email);
				return $information;
			}
			/**
			 * Return variable $false instead of explicitly returning boolean FALSE
			 * WordPress passes FALSE here by default
			 *
			 * @see http://goo.gl/tUpSc
			 */
			return $false;
		}
	}
endif;
