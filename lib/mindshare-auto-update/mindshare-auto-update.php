<?php
/**
 * mindshare-auto-update.php
 *
 * Provides a simple way to add automatic updates to premium themes and plugins.
 * Interacts with our remote repository API: http://mindsharelabs.com/update/
 *
 * @version      0.4.2
 * @created      9/23/12 12:44 AM
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
 *        0.4.2 - bugfixes for update mechanism
 *        0.4.1 - added toString method to retrieve current version number
 *        0.4 - added call to get remote zip file URL, get_remote_url
 *
 */

// make sure the plugin is available
if(!function_exists('is_plugin_active')) {
	include_once(ABSPATH.'wp-admin/includes/plugin.php');
}

if(!class_exists('mindshare_auto_update')) :
	class mindshare_auto_update {

		/**
		 * This version number for this class (Mindshare Auto Update)
		 * This value is returned when the class is treated as a string (via __toString())
		 *
		 * @var
		 */
		public $class_version = '0.4.2';

		/**
		 * The plugin current version
		 *
		 * @var string
		 */
		public $current_version;

		/**
		 * The plugin remote update API web service
		 *
		 * @var string
		 */
		public $update_path = 'http://mindsharelabs.com/update/';

		/**
		 * Plugin Slug (plugin_directory/plugin_file.php)
		 *
		 * @var string
		 */
		public $plugin_slug;

		/**
		 * Plugin name (plugin_file)
		 *
		 * @var string
		 */
		public $slug;

		/**
		 * Plugin path (path to plugin file from server root)
		 *
		 * @var string
		 */
		public $target_dir;

		public $hash = 'cdd96d3cc73d1dbdaffa03cc6cd7339b';

		/**
		 * Initialize a new instance of the WordPress Auto-Update class
		 *
		 * @param        $plugin_slug
		 * @param        $target_dir
		 * @param string $update_path
		 *
		 * @internal param string $current_version
		 * @internal param string $this->plugin_slug
		 */
		function __construct($plugin_slug, $target_dir, $update_path = NULL) {
			// Set the class public variables, order is important here
			$this->plugin_slug = $plugin_slug;
			list($dir, $file) = explode('/', $this->plugin_slug);

			$this->slug = str_replace('.php', '', $file);

			$this->plugin_path = $target_dir;
			if(file_exists($this->plugin_path.$this->slug.'.php')) {
				$this->current_version = $this->get_local_version();
			}

			if(isset($update_path)) {
				$this->update_path = $update_path;
			}

			// define the alternative API for updating checking
			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

			// Define the alternative response for plugin information requests
			add_filter('plugins_api', array($this, 'check_info'), 10, 3);
			//set_site_transient('update_plugins', NULL); // debugging
			//add_filter('pre_set_site_transient_update_plugins', array($this, 'display_transient_update_plugins')); // debugging
			//$this->check_update(get_site_transient('update_plugins')); // debugging
		}

		/**
		 *
		 * The version number for this class (Mindshare Auto Update)
		 *
		 * @return string
		 */
		function __toString() {
			return $this->class_version;
		}

		function display_transient_update_plugins($transient) {
			if($transient !== FALSE) {
				var_dump($transient);
			} else {
				//echo '<h1>not set</h1>';
			}
		}

		/**
		 * Add our self-hosted auto-update plugin to the filter transient
		 *
		 * @param $transient
		 *
		 * @return object $ transient
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
					$obj->url = $this->update_path;
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
				$information = $this->get_remote_information();
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

		/**
		 * get_local_version
		 *
		 * Returns the version of a given locally installed plugin
		 *
		 * @param null $target_dir
		 *
		 * @return string|bool
		 */
		function get_local_version($target_dir = NULL) {

			if(!isset($target_dir)) {
				$target_dir = $this->plugin_path.$this->slug.'.php';
			}
			$plugin_data = get_plugin_data($target_dir);
			if(!empty($plugin_data['Version'])) {
				return $plugin_data['Version'];
			} else {
				return FALSE;
			}
		}

		/**
		 * Returns the version of a remotely hosted plugin from the API web service
		 *
		 * @return string|bool $remote_version
		 */
		public function get_remote_version() {
			$request = wp_remote_post($this->update_path, array('body' => array('project' => $this->slug, 'action' => 'version')));
			if(!is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200) {
				return $request['body'];
			}
			return FALSE;
		}

		/**
		 * Returns the Zip file (download) URL of a remotely hosted plugin from the API web service
		 *
		 * @param null $key
		 * @param null $email
		 *
		 * @return string|bool $remote_url
		 */
		public function get_remote_url($key = NULL, $email = NULL) {

			// if either license key or user email is missing, assume we're dealing with a free product
			if(empty($key) || empty($email)) {
				$body = array(
					'body' => array(
						'project' => $this->slug,
						'action'  => 'url'
					)
				);
			} else {
				$body = array(
					'body' => array(
						'project' => $this->slug,
						'action'  => 'url',
						'k'       => $key,
						'u'       => $email
					)
				);
			}

			$response = wp_remote_post($this->update_path, $body);
			if(!is_wp_error($response) || wp_remote_retrieve_response_code($response) === 200) {
				return $response['body'];
			} else {
				return FALSE;
			}
		}

		/**
		 * Get information about the remote version
		 *
		 * @return bool|object
		 */
		public function get_remote_information() {

			$request = wp_remote_post($this->update_path, array('body' => array('project' => $this->slug, 'action' => 'info')));

			if(!is_wp_error($request)) {
				if(wp_remote_retrieve_response_code($request) === 200) {
					return unserialize($request['body']);
				} elseif(wp_remote_retrieve_response_code($request) === 503) {
					//echo '<div id="message" class="error"><p>We\'re sorry! The update server timed out (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				} else {
					//echo '<div id="message" class="error"><p>We\'re sorry! An unknown error occurred (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				}
			} else {
				$error_string = $request->get_error_message();
				//echo '<div id="message" class="error"><p>'.$error_string.'</p></div>';
				return FALSE;
			}
		}

		/**
		 * Return the status of the plugin licensing
		 *
		 * @param string $key
		 * @param string $email
		 *
		 * @return boolean $remote_license
		 */
		public function get_remote_license($key = NULL, $email = NULL) {
			if(empty($key) || empty($email)) {
				return FALSE;
			}

			$body = array(
				'body' => array(
					'project' => $this->slug,
					'action'  => 'license',
					'k'       => $key,
					'u'       => $email
				)
			);
			$response = wp_remote_post($this->update_path, $body);

			if(is_wp_error($response)) {
				$error_string = $response->get_error_message();
				return $error_string;
			} else {
				if(wp_remote_retrieve_response_code($response) === 200) {
					if(md5(base64_encode(wp_remote_retrieve_body($response))) == $this->hash) {
						return $this->hash;
					} else {
						return 'Your license couldn\'t be verified, please double check your entries and try again.';
					}
				} else {
					if(wp_remote_retrieve_response_code($response) == '503') {
						return 'We\'re sorry! The update server timed out (status code: '.wp_remote_retrieve_response_code($response).'). Please try again in 30 seconds.';
					} else {
						return 'We\'re sorry! An unknown error occurred (status code: '.wp_remote_retrieve_response_code($response).'). Please try again in 30 seconds.';
					}
				}
			}
		}

		/**
		 * maybe_activate_plugin
		 *
		 * Activates a plugin (if it is installed and not already activated.)
		 *
		 * @internal       param $this ->plugin_slug
		 * @internal       param $plugin_file
		 *
		 * @return bool Returns TRUE if plugin activation is successful or plugin is already active, otherwise FALSE
		 *
		 */
		public function maybe_activate_plugin() {
			if(!is_plugin_active($this->plugin_slug)) {
				$result = activate_plugin($this->plugin_slug);
				if(!is_wp_error($result)) {
					return TRUE;
				} else {
					// activation failed
					$error_string = $result->get_error_message();
					return $error_string;
				}
			} else {
				// already active
				return TRUE;
			}
		}

		/**
		 * do_remote_install
		 *
		 * Installs a remote plugin, overwriting if the plugin already exists.
		 *
		 * @todo     add ability to overwrite currently active plugin
		 *
		 *
		 * @param $http_request_url
		 * @param $target_dir
		 *
		 * @internal param $plugin_slug
		 * @internal param $http_request_args
		 *
		 * @return bool
		 */

		public function do_remote_install($http_request_url, $target_dir) {

			$debug = FALSE;

			//$tmp_dir = get_temp_dir() == '/tmp/' ? ABSPATH.'tmp/' : get_temp_dir();
			// WP has issues writing to the sys temp dir so I'm using the 'upgrade' folder instead
			$tmp_dir = ABSPATH.'wp-content/upgrade/';
			if(!is_dir($tmp_dir)) {
				if(!mkdir($tmp_dir, 0755)) {
					return "Plugin upgrade failed. Could not create the directory: ".$tmp_dir;
				}
			}

			// create the target folder
			if(!is_dir($target_dir)) {
				if(!mkdir($target_dir, 0755)) {
					return "Plugin upgrade failed. Could not create the directory: ".$target_dir;
				}
			}

			// create a cURL request
			$file = $tmp_dir.md5($http_request_url).'.zip';
			$client = curl_init($http_request_url);
			curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
			$file_data = curl_exec($client);
			$response_code = curl_getinfo($client, CURLINFO_HTTP_CODE);

			if($response_code == 200) {
				file_put_contents($file, $file_data);
				WP_Filesystem();
				$result = unzip_file($file, $target_dir);
				if(!is_wp_error($result)) {
					unlink($file); // delete the temporary file
					return TRUE;
				} else {
					if($debug !== TRUE) {
						unlink($file);
					}
					return "An error occurred unzipping the file: ".$result->get_error_message();
				}
			} else {
				return "The file (".$http_request_url.") was not found on the remote server, error code: ".$response_code;
			}
		}
	}
endif;
