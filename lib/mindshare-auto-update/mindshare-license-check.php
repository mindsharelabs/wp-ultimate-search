<?php
/**
 * mindshare-license-check.php
 *
 * Provides a simple way to check licensing for premium themes and plugins.
 * Interacts with our remote repository API: http://mindsharelabs.com/update/
 *
 * @created      9/23/12 12:44 AM
 * @author       Mindshare Studios, Inc.
 * @copyright    Copyright (c) 2013
 * @link         http://www.mindsharelabs.com/documentation/
 *
 */

if(!class_exists('mindshare_updater')) {
	require_once('mindshare-updater.php');
}

if(!class_exists('mindshare_license_check')) :
	class mindshare_license_check extends mindshare_updater {

		/**
		 * The plugin current version
		 *
		 * @var string
		 */
		public $current_version;

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
		 * Target directory (path to install new plugin file)
		 *
		 * @var string
		 */
		public $target_dir;

		/**
		 * License key
		 *
		 * @var string
		 */
		public $key;

		/**
		 * Registered Email
		 *
		 * @var string
		 */
		public $email;

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

			$plugin_slug = apply_filters('msad_plugin_slug', $plugin_slug);
			$target_dir = apply_filters('msad_target_dir', $target_dir);
			$update_server_uri = apply_filters('msad_update_server_uri', $update_server_uri);

			// set class public variables
			if(isset($email)) {
				$this->email = $email;
			}
			if(isset($key)) {
				$this->key = $key;
			}
			// allow alternate update server
			if(isset($update_server_uri)) {
				$this->update_server_uri = $update_server_uri;
			}

			$this->plugin_slug = $plugin_slug;
			/** @noinspection PhpUnusedLocalVariableInspection */
			list($dir, $file) = explode('/', $this->plugin_slug);
			$this->slug = str_replace('.php', '', $file);
			$this->plugin_path = $target_dir;

			// set the currently installed version
			if(file_exists($this->plugin_path.$this->slug.'.php')) {
				$this->current_version = $this->get_local_version();
			}

		}

		/**
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
			$request = wp_remote_post($this->update_server_uri, array('body' => array('project' => $this->slug, 'action' => 'version')));
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

			$response = wp_remote_post($this->update_server_uri, $body);
			if(!is_wp_error($response) || wp_remote_retrieve_response_code($response) === 200) {
				return $response['body'];
			} else {
				return FALSE;
			}
		}

		/**
		 * Get information about the remote version
		 *
		 * @param null $key
		 * @param null $email
		 *
		 * @return bool|object
		 */
		public function get_remote_information($key = NULL, $email = NULL) {

			// if either license key or user email is missing, assume we're dealing with a free product
			if(empty($key) || empty($email)) {
				$body = array(
					'body' => array(
						'project' => $this->slug,
						'action'  => 'info'
					)
				);
			} else {
				$body = array(
					'body' => array(
						'project' => $this->slug,
						'action'  => 'info',
						'k'       => $key,
						'u'       => $email
					)
				);
			}

			$response = wp_remote_post($this->update_server_uri, $body);

			if(!is_wp_error($response)) {
				if(wp_remote_retrieve_response_code($response) === 200) {
					return unserialize($response['body']);
				} elseif(wp_remote_retrieve_response_code($response) === 503) {
					//echo '<div id="message" class="error"><p>We\'re sorry! The update server timed out (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				} else {
					//echo '<div id="message" class="error"><p>We\'re sorry! An unknown error occurred (status code: '.wp_remote_retrieve_response_code($request).'). Please try again later.</p></div>';
					return FALSE;
				}
			} else {
				//echo '<div id="message" class="error"><p>'.$request->get_error_message().'</p></div>';
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
			$response = wp_remote_post($this->update_server_uri, $body);

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
		 *
		 * Activates a plugin (if it is installed and not already activated.)
		 *
		 * @internal       param $this->plugin_slug
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
		 *
		 * Installs a remote plugin, overwriting if the plugin already exists.
		 *
		 * @todo     add/test ability to overwrite currently active plugin
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
					return new WP_Error('mkdir_failed', "Plugin upgrade failed. Could not create the directory: ".$tmp_dir);
				}
			}

			// create the target folder
			if(!is_dir($target_dir)) {
				if(!mkdir($target_dir, 0755)) {
					return new WP_Error('mkdir_failed', "Plugin upgrade failed. Could not create the directory: ".$target_dir);
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
					return new WP_Error('unzip_failed', "An error occurred unzipping the file: ".$result->get_error_message());
				}
			} else {
				return new WP_Error('curl_failed', "The file (".$http_request_url.") was not found on the remote server, error code: ".$response_code);
			}
		}
	}
endif;
