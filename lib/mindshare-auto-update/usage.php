<?php
/*
Plugin Name: Auto Update Usage
Plugin URI: http://mindsharelabs.com
Description: Example plugin with autoupdate
Author: Mindshare Studios, Inc.
Version: 1.0
*/

add_action('init', 'mindshare_auto_update');

function mindshare_auto_update() {
	require_once('mindshare-auto-update.php');
	new mindshare_auto_update(plugin_basename(__FILE__), plugin_dir_path(__FILE__));
}

