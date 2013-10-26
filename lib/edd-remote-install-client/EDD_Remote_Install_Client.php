<?php

/**
 * Allows plugins to install new plugins or upgrades
 *
 * @author Mindshare Studios
 * @version 1.1
 */
class WPUS_EDD_Remote_Install_Client {
	private $api_url  = '';

	/**
	 * Class constructor.
	 *
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_api_url, $_plugin_file ) {
		$this->api_url  = trailingslashit( $_api_url );

		add_action( 'admin_enqueue_scripts', array($this, 'register_scripts' ));

		add_action('wp_ajax_edd-check-remote-install', array($this, 'check_remote_install'));
		add_action('wp_ajax_edd-do-remote-install', array($this, 'do_remote_install'));
	}

	/**
	 * Register scripts and styles
	 *
	 * @return void
	 */

	public function register_scripts() {
		wp_enqueue_script('edd-remote-install-script', plugin_dir_url( __FILE__ ) . '/js/edd-remote-install-admin.js', array('jquery'));
		wp_enqueue_style('edd-remote-install-style', plugin_dir_url( __FILE__ ) . '/css/edd-remote-install-admin.css');
	}

	/**
	 * Slug
	 *
	 * Converts a plugin name to a slug
	 *
	 * @param string $str String to convert
	 * @return string $str Converted string
	 */

	private function slug($str) {
		$str = strtolower(trim($str));
		$str = preg_replace('/[^a-z0-9-]/', '-', $str);
		$str = preg_replace('/-+/', "-", $str);
		return $str;
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
			wp_die( 'You do not have sufficient permissions to install plugins on this site.' );

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

			die($response);

		else:

			die("Error occurred while trying to reach remote server. Please try again or contact support.");

		endif;
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

		if(isset($_POST['license'])) {
			$license = $_POST['license'];

			$api_params = array( 
				'edd_action'=> 'activate_license', 
				'license' 	=> $license, 
				'item_name' => urlencode( $_POST['download'] ) // the name of our product in EDD
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

			$options = get_option('wpus_options');
			$options['license_key'] = $license;
			$options['license_status'] = 'active';

			update_option('wpus_options', $options);

		} else {

			// If its a free download, don't send a license
			$license = null;

		}

		$api_params = array(
			'edd_action' => 'get_download',
			'item_name'  => urlencode( $_POST['download'] ),
			'license'	 => urlencode( $license )
		);

		$download_link = add_query_arg($api_params, $this->api_url);

		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; //for plugins_api..

		$upgrader = new Plugin_Upgrader();

		$result = $upgrader->install($download_link);

		if($result == 1) {
			$slug = $this->slug($_POST['download']);
			$path = WP_PLUGIN_DIR . "/" . $slug . "/" . $slug . ".php";
			$result = activate_plugin( $path );
		}

		die();
	}
}

// set up the remote installer
$edd_remote_install = new WPUS_EDD_Remote_Install_Client( WPUS_STORE_URL, __FILE__ );