<?php

/**
 * Allows plugins to install new plugins or upgrades
 *
 * @author Mindshare Studios, Inc.
 * @version 1.4
 */
class WPUS_Remote_Install_Client {
	private $api_url  = '';
	private $options = array(
			'skipplugincheck'	=> false
		);

	/**
	 * Class constructor.
	 *
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_api_url, $page, $options = array() ) {
		$this->api_url  = trailingslashit( $_api_url );

		if(isset($options['skipplugincheck']) && $options['skipplugincheck'] == true) {
			$this->options['skipplugincheck'] = true;
		}

		$options['page'] = $page;
		$this->options = $options;

		add_action( 'load-' . $page, array($this, 'register_scripts' ));

		add_action('wp_ajax_edd-check-plugin-status-' . $page, array($this, 'check_plugin_status'));
		add_action('wp_ajax_edd-check-remote-install-' . $page, array($this, 'check_remote_install'));
		add_action('wp_ajax_edd-do-remote-install-' . $page, array($this, 'do_remote_install'));

		add_action('eddri-install-complete-' . $page, array($this, 'install_complete'), 0, 1);
	}

	/**
	 * Try to convert plugin name to slug
	 *
	 * @param $str Download name
	 * @return $str Slug
	 */

	private function slug($str) {
		$str = strtolower( $str );
		$str = preg_replace("/[\s_]/", "-", $str);

		return $str;
	}

	/**
	 * Register scripts and styles
	 *
	 * @return void
	 */

	public function register_scripts() {
		wp_enqueue_script('edd-remote-install-script', plugin_dir_url( __FILE__ ) . '/js/edd-remote-install-admin.js', array('jquery'));
		wp_enqueue_style('edd-remote-install-style', plugin_dir_url( __FILE__ ) . '/css/edd-remote-install-admin.css');

		wp_localize_script( 'edd-remote-install-script', 'edd_ri_options', $this->options );
	}

	/**
	 * Callback action that's fired when an install is completed successfully
	 *
	 * @param string $slug Slug of plugin successfully installed
	 * @return void
	 */

	public function install_complete($args) {


	}

	/**
	 * Check plugin status
	 *
	 * Checks to see if a plugin is currently installed and disables the install button if so
	 *
	 * @param string $_POST['download'] Download requested
	 * @return string $response
	 */

	public function check_plugin_status() {
		
		$plugin = $this->slug($_POST['download']);

		if (is_plugin_active($plugin . '/' . $plugin . '.php')) {
			die(true);
		} else {
			die(false);
		}
	}

	/**
	 * Check remote install
	 *
	 * Checks remote server for the specified Download
	 *
	 * @param string $_POST['download'] Download requested
	 * @return string $response
	 */

	public function check_remote_install() {

		if ( ! current_user_can('install_plugins') )
			die( 'You do not have sufficient permissions to install plugins on this site.' );

		$api_params = array(
			'edd_action' => 'check_download',
			'item_name'  => urlencode( $_POST['download'] )
		);

		$request = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( ! is_wp_error( $request ) ):
			$request = json_decode( wp_remote_retrieve_body( $request ) );
			$request = maybe_unserialize( $request );

			if($request->download == "free") {
				
				$response = "0";

			} else if ($request->download == "not-free") {

				$response = "1";

			} else {

				$response = "does not exist";

			}

		else:

			$response = "Error occurred while trying to reach remote server. Please try again or contact support.";

		endif;

		die(json_encode($response));
	}

	/**
	 * Do remote install
	 *
	 * Passes the download and license key (if specified) to the server and receives and installs the plugin package
	 *
	 * @param string $_POST['license'] License key (if specified)
	 * @param string $_POST['download'] Download requested
	 * @return response
	 */

	public function do_remote_install() {

		if ( ! current_user_can('install_plugins') )
			wp_die( 'You do not have sufficient permissions to install plugins on this site.' );

		$download = $_POST['download'];

		if(isset($_POST['license'])) {
			$license = $_POST['license'];

			$api_params = array( 
				'edd_action'=> 'activate_license', 
				'license' 	=> $license, 
				'item_name' => urlencode( $download ) // the name of our product in EDD
			);
			
			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, $this->api_url ), array( 'timeout' => 15, 'sslverify' => false ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if($license_data->license != "valid") 
				die("invalid");

		} else {

			// If its a free download, don't send a license
			$license = null;

		}

		$api_params = array(
			'edd_action' => 'get_download',
			'item_name'  => urlencode( $download ),
			'license'	 => urlencode( $license )
		);

		$download_link = add_query_arg($api_params, $this->api_url);

		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; //for plugins_api..

		$upgrader = new Plugin_Upgrader();

		$result = $upgrader->install($download_link);

		if($result == 1) {
			$slug = $this->slug($download);
			$path = WP_PLUGIN_DIR . "/" . $slug . "/" . $slug . ".php";
			$result = activate_plugin( $path );

			$args['slug'] = $slug;
			$args['license'] = $license;
			do_action('eddri-install-complete-' . $this->options['page'], $args);
		}

		die();
	}
}