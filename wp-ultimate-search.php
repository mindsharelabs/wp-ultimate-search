<?php
/*
Plugin Name: WP Ultimate Search
Plugin URI: http://ultimatesearch.mindsharelabs.com
Description: Advanced faceted AJAX search and filter utility.
Version: 1.2.1
Author: Mindshare Studios
Author URI: http://mindsharelabs.com/
*/

/**
 * @copyright Copyright (c) 2012. All rights reserved.
 * @author    Mindshare Studios, Inc.
 *
 * @license   Released under the GPL license http://www.opensource.org/licenses/gpl-license.php
 * @see       http://wordpress.org/extend/plugins/wp-ultimate-search/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 *
 * @todo      use WPUS_PLUGIN_SLUG
 * @todo      replace all class_exists('WPUltimateSearchPro') with better mechanism for testing pro
 * @todo      move all pro functions out of options page php file into this one
 * @todo      setup auto remote install + acivation
 */

/* CONSTANTS */
if(!defined('WPUS_MIN_WP_VERSION')) {
	define('WPUS_MIN_WP_VERSION', '3.1');
}

if(!defined('WPUS_PLUGIN_NAME')) {
	define('WPUS_PLUGIN_NAME', 'WP Ultimate Search');
}

if(!defined('WPUS_PLUGIN_SLUG')) {
	define('WPUS_PLUGIN_SLUG', 'wp-ultimate-search');
}

if(!defined('WPUS_DIR_PATH')) {
	define('WPUS_DIR_PATH', plugin_dir_path(__FILE__));
}

if(!defined('WPUS_DIR_URL')) {
	define('WPUS_DIR_URL', plugin_dir_url(__FILE__));
}

if(!defined('WPUS_PRO_SLUG')) {
	define('WPUS_PRO_SLUG', 'wp-ultimate-search-pro');
}

if(!defined('WPUS_PRO_PATH')) {
	define('WPUS_PRO_PATH', str_replace(WPUS_PLUGIN_SLUG, WPUS_PRO_SLUG, WPUS_DIR_PATH));
}

if(!defined('WPUS_PRO_FILE')) {
	define('WPUS_PRO_FILE', WPUS_PRO_SLUG.'.php');
}

// check WordPress version
global $wp_version;
if(version_compare($wp_version, WPUS_MIN_WP_VERSION, "<")) {
	exit(WPUS_PLUGIN_NAME.' requires WordPress '.WPUS_MIN_WP_VERSION.' or newer.');
}

// deny direct access
if(!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

/*
 * If WPUS Pro is available these hooks will handle activation/deactivation.
 * These fail silently if the plugin isn't installed.
 */
if(!function_exists('activate_pro')) {
	function activate_pro() {
		if(is_plugin_inactive(WPUS_PRO_SLUG.'/'.WPUS_PRO_FILE)) {
			add_action('update_option_active_plugins', 'activate_pro_callback', 1);
		}
	}
}
if(!function_exists('activate_pro_callback')) {
	function activate_pro_callback() {
		activate_plugin(WPUS_PRO_SLUG.'/'.WPUS_PRO_FILE);
	}
}
if(!function_exists('deactivate_pro')) {
	function deactivate_pro() {
		if(is_plugin_active(WPUS_PRO_SLUG.'/'.WPUS_PRO_FILE)) {
			add_action('update_option_active_plugins', 'deactivate_pro_callback', 1);
		}
	}
}
if(!function_exists('deactivate_pro_callback')) {
	function deactivate_pro_callback() {
		deactivate_plugins(WPUS_PRO_SLUG.'/'.WPUS_PRO_FILE);
	}
}

register_activation_hook(__FILE__, 'activate_pro');
register_deactivation_hook(__FILE__, 'deactivate_pro');

/**
 *  WPUltimateSearch CONTAINER CLASS
 */
if(!class_exists("WPUltimateSearch")) :
	class WPUltimateSearch {

		public $options, $is_active, $pro_class;

		function __construct() {

			$this->is_active = false;
			$this->options = get_option('wpus_options');

			$options = $this->options;

			if(is_admin()) {
				require_once(WPUS_DIR_PATH.'views/wpus-options.php'); // include options file
				$options_page = new WPUltimateSearchOptions();
				add_action('admin_menu', array($options_page, 'add_pages')); // adds page to menu
				add_action('admin_init', array($options_page, 'register_settings'));
			}

			add_action('init', array($this, 'init'));

			// REGISTER AJAX FUNCTIONS WITH ADMIN-AJAX
			add_action('wp_ajax_wpus_search', array($this, 'get_results'));
			add_action('wp_ajax_nopriv_wpus_search', array($this, 'get_results')); // need this to serve non logged in users
			add_action('wp_ajax_wpus_getvalues', array($this, 'get_values'));
			add_action('wp_ajax_nopriv_wpus_getvalues', array($this, 'get_values')); // need this to serve non logged in users

			// REGISTER SHORTCODES
			add_shortcode(WPUS_PLUGIN_SLUG."-bar", array($this, 'search_form'));
			add_shortcode(WPUS_PLUGIN_SLUG."-results", array($this, 'search_results'));

			// REGISTER WIDGET
			add_action('widgets_init', array($this, 'wpus_register_widgets'));

			// CREATE SEARCH RESULTS PAGE ON ACTIVATION
			register_activation_hook(__FILE__, array($this, 'activation_hook'));

			// REGISTER SCRIPTS IF GLOBAL SCRIPTS ARE ENABLED
			if(isset($options['global_scripts'])) {
				if($options['global_scripts'] == 1) {
					add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
				}
			}
		}
		

		/**
		 * wpus_register_widgets
		 *
		 */
		function wpus_register_widgets() {
			require_once(WPUS_DIR_PATH.'views/wpus-widget.php'); // include widget file
			register_widget('wpultimatesearchwidget');
		}

		function init() {
			if(file_exists(WPUS_PRO_PATH.WPUS_PRO_SLUG.'.php')) {
				require(WPUS_PRO_PATH.WPUS_PRO_SLUG.'.php');
				$this->pro_class = new WPUltimateSearchPro();
			}
			if(isset($this->options['override_default'])) {
				if($this->options['override_default']) {
					add_filter('get_search_form', array($this, 'search_form'));
				}
			}
		}

		/**
		 *
		 * Create search results page
		 *
		 * When the plugin is first activated, create a /search/ page with the results shortcode.
		 *
		 */
		public function activation_hook() {
			$pages = get_pages();
			foreach($pages as $page) {
				if($page->post_name == "search") {
					return;
				}
			} // if search page already exists, exit
			$results_page = array(
				'post_title'     => 'Search',
				'post_content'   => '['.WPUS_PLUGIN_SLUG.'-bar]<br />['.WPUS_PLUGIN_SLUG.'-results]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_name'      => 'search',
				'comment_status' => 'closed'
			);
			wp_insert_post($results_page);
		}

		/**
		 *  PRIVATE FUNCTIONS
		 */

		/**
		 *
		 * Highlight search terms
		 *
		 *
		 * Takes a block of text and an array of keywords, returns the text with
		 * keywords wrapped in a "highlight" class.
		 *
		 * @param $text
		 * @param $keywords
		 *
		 * @return mixed
		 */
		private function highlightsearchterms($text, $keywords) {
			return preg_replace('/('.implode('|', $keywords).')/i', '<strong class="wpus-highlight">$0</strong>', $text);
		}

		/**
		 *
		 * Convert a string to an array of keywords
		 *
		 *
		 * Separate a comma-separated string of keywords into an array, preserving quotation marks
		 *
		 * @param $search
		 *
		 * @return mixed
		 */
		protected function string_to_keywords($search) {
			preg_match_all('/(?<!")\b\w+\b|(?<=")\b[^"]+/', $search, $keywords);
			for($i = 0; $i < count($keywords[0]); $i++) {
				$keywords[0][$i] = stripslashes($keywords[0][$i]);
			}
			return $keywords[0];
		}

		/**
		 *
		 * Modified version of wp_strip_all_tags
		 *
		 *
		 * Strips all HTML etc. tags from a given input, converts line breaks to spaces, and
		 * removes any trailing tags that got clipped by the excerpt process
		 *
		 * @param      $string
		 * @param bool $remove_breaks
		 *
		 * @return string
		 */
		private function wpus_strip_tags($string, $remove_breaks = FALSE) {
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

			$string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);

			$string = preg_replace('@ *</?\s*(P|UL|OL|DL|BLOCKQUOTE)\b[^>]*?> *@si', "\n\n", $string);
			$string = preg_replace('@ *<(BR|DIV|LI|DT|DD|TR|TD|H\d)\b[^>]*?> *@si', "\n", $string);
			$string = preg_replace("@\n\n\n+@si", "\n\n", $string);

			$string = strip_tags($string);

			if($remove_breaks) {
				$string = preg_replace('/[\r\n\t ]+/', ' ', $string);
			}

			// ...since we're pulling excerpts from the DB, some of the excerpts contain truncated HTML tags
			// that won't be picked up by strip_tags(). This removes any trailing HTML from the beginning
			// and end of the excerpt:
			$string = preg_replace('/.*>|<.*/', ' ', $string);

			return trim($string);
		}

		/**
		 *
		 * Ajax response
		 *
		 *
		 * Similar to wp_localize_script, but wp_localize_script can only be called on plugin load / on
		 * page load. This function can be called during execution of the AJAX call & response process
		 * to update the main.js file with new variables.
		 *
		 * @param $parameter
		 * @param $response
		 */
		private function ajax_response($parameter, $response) {
			echo '
				<script type="text/javascript">
				    /* <![CDATA[ */
				    var wpus_response = {
				            "'.$parameter.'":"'.$response.'"
				    };
				    /* ]]> */
				    </script>';
		}

		/**
		 *
		 * Print results
		 *
		 *
		 * If there are results, load the appropriate results template and output
		 * the search results. Send Analytics tracking beacon if enabled.
		 *
		 * @param $results
		 * @param $keywords
		 *
		 * @internal param $resultsarray
		 */
		protected function print_results($results, $keywords) {
			ob_start();

			if(file_exists(TEMPLATEPATH.'/wpus-results-template.php')) {
				require(TEMPLATEPATH.'/wpus-results-template.php');
			} else {
				require(WPUS_DIR_PATH.'views/wpus-results-template.php');
			}
			// if we're tracking searches as analytics events, pass the number of search results back to main.js
			if(wpus_option('track_events')) {
				$this->ajax_response('numresults', count($results));
			}

			echo ob_get_clean();
			/* @todo add an option to switch to non post_object based results output (just title and excerpt)

			if($keywords) {
			echo $this->highlightsearchterms($output, $keywords);
			} else {
			echo $output;
			} */
		}

		/**
		 *
		 * Get Enabled Taxonomies
		 *
		 *
		 * Return an array of all taxonomies which are currently selected in the options window
		 *
		 * @return array
		 */
		private function get_enabled_facets() {
			$options = $this->options;

			if(class_exists("WPUltimateSearchPro")) {

				foreach($options['taxonomies'] as $taxonomy => $key) {
					if(isset($key['enabled'])) {
						if($key['enabled']) {
							if($key['label']) {
								$enabled_facets[] = $key['label'];
							} else {
								$enabled_facets[] = $taxonomy;
							}
						}
					}
				}
				if(isset($options['metafields'])) {
					foreach($options['metafields'] as $metafield => $key) {
						if(isset($key['enabled'])) {
							if($key['enabled']) {
								if($key['label']) {
									$enabled_facets[] = $key['label'];
								} else {
									$enabled_facets[] = $metafield;
								}
							}
						}
					}
				}
			} else {
				$enabled_facets = array();
				if($options['enable_category']) {
					$enabled_facets[] = 'category';
				}
				if($options['enable_tag']) {
					$enabled_facets[] = 'tag';
				}
			}
			return $enabled_facets;
		}

		/**
		 *
		 * Get Taxonomy Name
		 *
		 *
		 * Matches a user-specified label from the options screen to it's corresponding term_name in the db
		 *
		 * @param $label
		 *
		 * @return int|string
		 */
		protected function get_taxonomy_name($label) {
			if(!$options = $this->options) {
				$options = $this->pro_class->options;
			}

			foreach($options['taxonomies'] as $taxonomy => $value) {
				if($value['label'] == $label) {
					return $taxonomy;
				}
			}

			return $label; // if no match found, try to use the label
		}

		/**
		 *
		 * Get Metafield Name
		 *
		 *
		 * Matches a user-specified label from the options screen to it's corresponding meta_key in the db
		 *
		 * @param $label
		 *
		 * @return int|string
		 */
		protected function get_metafield_name($label) {
			if(!$options = $this->options) {
				$options = $this->pro_class->options;
			}

			foreach($options['metafields'] as $metafield => $value) {
				if($value['label'] == $label) {
					return $metafield;
				}
			}

			return $label; // if no match found, try to use the label
		}

		/**
		 *
		 * Determine facet type
		 *
		 *
		 * Given a facet label in string form, determines whether it's a taxonomy or post meta
		 *
		 * @param $facet
		 *
		 * @return string
		 */
		protected function determine_facet_type($facet) {
			if(!$options = $this->options) {
				$options = $this->pro_class->options;
			}

			if($facet == "text") {
				return "text";
			}


			if(isset($options['taxonomies'])) {
				foreach($options['taxonomies'] as $taxonomy => $value) {
					if($value['label'] == $facet || $taxonomy == $facet) {
						return "taxonomy";
					}
				}
			}
			if(isset($options['metafields'])) {
				foreach($options['metafields'] as $metafield => $value) {
					if($value['label'] == $facet || $metafield == $facet) {
						return "metafield";
					}
				}
			}
		}

		/**
		 *  PUBLIC FUNCTIONS
		 */

		/**
		 * register_scripts
		 *
		 */
		public function register_scripts() {

			// ENQUEUE VISUALSEARCH SCRIPTS
			//			wp_enqueue_script('underscore', WPUS_DIR_URL.'js/underscore-min.js');
			//			wp_enqueue_script('backbone', WPUS_DIR_URL.'js/backbone-min.js', array('underscore'));
			wp_enqueue_script('underscore');
			wp_enqueue_script('backbone');
			wp_enqueue_script(
				'visualsearch',
				WPUS_DIR_URL.'js/visualsearch.min.js',
				array(
					 'jquery',
					 'jquery-ui-core',
					 'jquery-ui-widget',
					 'jquery-ui-position',
					 'jquery-ui-autocomplete',
					 'backbone',
					 'underscore'
				)
			);

			// ENQUEUE AND LOCALIZE MAIN JS FILE
			wp_enqueue_script('wpus-script', WPUS_DIR_URL.'js/main.js', array('visualsearch'), '', wpus_option('scripts_in_footer'));

			$options = $this->options;

			($options['show_facets'] == 1 ? $showfacets = true : $showfacets = false);
			($options['highlight_terms'] == 1 ? $highlight = true : $highlight = false);

			$params = array(
				'ajaxurl'          => admin_url('admin-ajax.php'),
				'searchNonce'      => wp_create_nonce('search-nonce'),
				'trackevents'      => $options['track_events'],
				'eventtitle'       => $options['event_category'],
				'enabledfacets'    => json_encode($this->get_enabled_facets()),
				'resultspage'      => get_permalink($options['results_page']),
				'showfacets'	   => $showfacets,
				'placeholder'	   => $options['placeholder'],
				'highlight'		   => $highlight
			);

			wp_localize_script('wpus-script', 'wpus_script', $params);

			// ENQUEUE STYLES
			if(isset($options['style'])) {
				if($options['style'] == 'square') {
					wp_enqueue_style('wpus-bar', WPUS_DIR_URL.'css/square.css');
				} else {
					wp_enqueue_style('wpus-bar', WPUS_DIR_URL.'css/visualsearch.css');	
				}
			} else {
				wp_enqueue_style('wpus-bar', WPUS_DIR_URL.'css/visualsearch.css');
			}
		}

		/**
		 * search_form
		 *
		 * @return string
		 */
		public function search_form($mode) {
			
			if($mode == "widget" && get_the_ID() == $this->options['results_page'])
				return;
			
			$options = $this->options;

			if(isset($options['global_scripts'])) {
				if($options['global_scripts'] == 0) {
					$this->register_scripts();
				}
			}

			// RENDER SEARCH FORM
			return '<div id="search_box_container"><div id="search"><div class="VS-search">
			  <div class="VS-search-box-wrapper VS-search-box">
			    <div class="VS-icon VS-icon-search"></div>
			    <div class="VS-icon VS-icon-cancel VS-cancel-search-box" title="clear search"></div>
			  </div>
			</div></div></div>';
		}

		/**
		 * search_results
		 *
		 * @return string
		 */
		public function search_results() {
			// RENDER SEARCH RESULTS AREA
			return '<div id="wpus_response"></div>';
		}

		/**
		 *
		 * Get values
		 *
		 * This is called by main.js whenever an eligible facet is entered in the search
		 * bar. Returns a comma-separated list of available terms for the facet.
		 *
		 */
		public function get_values() {
			$facet = $_GET['facet'];
			if(!isset($facet)) {
				exit;
			} // if nothing's been set, we can exit

			$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

			$options = $this->options;

			switch($type) {
				case "taxonomy" :
					$facet = $this->get_taxonomy_name($facet); // get the database taxonomy name from the current facet

					if(isset($options['taxonomies'][$facet]['max'])) {
						$number = $options['taxonomies'][$facet]['max'];
					} else {
						$number = 50; // set a max of 50 terms, so we don't break anything
					}
					$excludetermids = array();
					if(!empty($options['taxonomies'][$facet]['exclude'])) {
						$excludeterms = $this->string_to_keywords($options['taxonomies'][$facet]['exclude']);
						foreach($excludeterms as $term) {
							$term = get_term_by('name', $term, $facet);
							$excludetermids[] = $term->term_id;
						}
					}
					$args = array( // parameters for the term query
						'orderby' => 'name',
						'order'   => 'ASC',
						'number'  => $number,
						'exclude' => $excludetermids
					);

					$terms = get_terms($facet, $args);
					foreach($terms as $term) {
						$values[] = html_entity_decode($term->name);
					}

					echo json_encode($values); // json encode the results array and pass it back to the UI
					die();

				case "metafield" :

					$facet = $this->get_metafield_name($facet);

					global $wpdb;

					$querystring = "
						SELECT pm.meta_value as value FROM {$wpdb->postmeta} pm
						WHERE pm.meta_key LIKE '{$facet}'
						ORDER BY value DESC"; // get the values from post_meta where the meta key matches the search facet...
					// this will be cached, eventually
					$results = $wpdb->get_results($querystring);

					foreach($results as $key) {
						if(!empty($key->value)) { // for some reason, $results sometimes returns zero-length strings as keys, so this filters them out
							$values[strtolower($key->value)] = $key->value;
						}
					}
					echo json_encode($values);
					die();
			}
		}

		/**
		 *
		 * Get results
		 *
		 *
		 * This is called by main.js when the wpus_search action is triggered. Gets
		 * the query from the UI, reconstructs it into an array, builds and executes the
		 * database query, and calls the function to output the results.
		 *
		 */
		public function get_results() {

			if(!isset($_GET['wpusquery'])) {
				die(); // if no data has been entered, quit
			} else {
				$searcharray = $_GET['wpusquery'];
			}

			$nonce = $_GET['searchNonce'];
			if(!wp_verify_nonce($nonce, 'search-nonce')) // make sure the search nonce matches the nonce generated earlier
			{
				die ('Busted!');
			}

			if(class_exists("WPUltimateSearchPro")) {
				$this->pro_class->execute_query_pro($searcharray);
			} else {
				$this->execute_query_basic($searcharray);
			}
		}

		/**
		 * @param $searcharray
		 */
		public function execute_query_basic($searcharray) {

			global $wpdb; // load the database wrapper

			foreach($searcharray as $index) { // iterate through the search query array and separate the taxonomies into their own array
				foreach($index as $facet => $data) {
					$facet = esc_sql($facet);

					$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

					switch($type) {
						case "text" :
							$keywords = $this->string_to_keywords($data);
							break;
						case "taxonomy" :
							$facet = $this->get_taxonomy_name($facet);
							$data = preg_replace('/_/', " ", $data); // in case there are underscores in the value (from a permalink), remove them
							if(!isset($taxonomies[$facet])) {
								$taxonomies[$facet] = "'".$data."'"; // if it's the first parameter, don't prefix with a comma
							} else {
								$taxonomies[$facet] .= ", '".$data."'"; // prefix subsequent parameters with ", "
							}
							break;
						case "metafield" :
							echo "I'm sorry but WP Ultimate Search Pro is currently not installed, configured incorrectly, or the plugin is disabled.";
							die();
					}
				}
			}
			// @todo would be nice if we could somehow iterate through to find the first matching keyword instead of just checking $keywords[0]
			$querystring = "
			SELECT *,
			substring(post_content, ";
			if(isset($keywords)) { // if there are keywords, locate them and return a 200 character excerpt beginning 80 characters before the keyword
				$keywords = esc_sql($keywords); // Sanitize the keywords parameters to prevent sql injection attacks
				$querystring .= "
					case 
						 when locate('$keywords[0]', lower(post_content)) <= 80 then 1
			             else locate('$keywords[0]', lower(post_content)) - 80
			        end,";
			} else { // if there aren't any keywords, just return the first 200 characters of the post
				$querystring .= "1,";
			}
			$querystring .= "200)
			AS excerpt
			FROM $wpdb->posts ";
			if(isset($taxonomies)) {
				for($i = 0; $i < count($taxonomies); $i++) { // for each taxonomy (categories, tags, etc.) do some joins so we can check each post against taxonomy[i] and term[i]
					$querystring .= "
					LEFT JOIN $wpdb->term_relationships AS rel".$i." ON($wpdb->posts.ID = rel".$i.".object_id)
					LEFT JOIN $wpdb->term_taxonomy AS tax".$i." ON(rel".$i.".term_taxonomy_id = tax".$i.".term_taxonomy_id)
					LEFT JOIN $wpdb->terms AS term".$i." ON(tax".$i.".term_id = term".$i.".term_id) ";
				}
			}
			$querystring .= "WHERE "; // the SELECT part of the query told us *what* to grab, the WHERE part tells us which posts to grab it from
			// if there are keywords, select posts where any of the keywords appear in either the title or post body
			if(isset($keywords)) {
				for($i = 0; $i < count($keywords); $i++) {
					$querystring .= "(lower(post_content) LIKE '%{$keywords[$i]}%' ";
					$querystring .= "OR lower(post_title) LIKE '%{$keywords[$i]}%') ";
					if($i < count($keywords) - 1) {
						$querystring .= "AND ";
					}
				}
			}
			if(isset($keywords) && isset($taxonomies)) {
				$querystring .= "AND ";
			} // if there were keywords, and there are taxonomies, insert an AND between the two sections
			$i = 0;
			if(isset($taxonomies)) {
				foreach($taxonomies as $taxonomy => $taxstring) { // for each taxonomy, check to see if there are any matches from within the comma-separated list of terms
					if($i > 0) {
						$querystring .= "AND ";
					}
					$querystring .= "(term".$i.".name IN (".$taxstring.") ";
					$querystring .= "AND tax".$i.".taxonomy = '".$taxonomy."') ";
					$i++;
				}
			}
			if((isset($keywords) || isset($taxonomies)) && isset($metafields)) {
				$querystring .= "AND ";
			}
			$querystring .= "
			AND $wpdb->posts.post_status = 'publish'"; // exclude drafts, scheduled posts, etc

			//echo $querystring; $wpdb->show_errors(); 		// for debugging, you can echo the completed query string and enable error reporting before it's executed

			if(!isset($keywords)) {
				$keywords = NULL;
			}

			$this->print_results($wpdb->get_results($querystring, OBJECT), $keywords); // format and output the search results

			die(); // wordpress may print out a spurious zero without this - can be particularly bad if using json
		}
	}
endif;

/**
 *  GLOBAL FUNCTIONS AND TEMPLATE TAGS
 */
if(class_exists("WPUltimateSearch")) {

	$wp_ultimate_search = new WPUltimateSearch();

	/**
	 * wp_ultimate_search_results
	 *
	 */
	function wp_ultimate_search_results() {
		global $wp_ultimate_search;
		echo $wp_ultimate_search->search_results();
	}

	/**
	 * wp_ultimate_search_bar
	 *
	 */
	function wp_ultimate_search_bar($mode = null) {
		global $wp_ultimate_search;
		echo $wp_ultimate_search->search_form($mode);
	}

	/**
	 * make options public
	 *
	 * @param $option
	 *
	 * @return bool
	 *
	 * @todo move inside class. also.. is this really necessary? I can't remember the inspiration
	 * It's used by the widget to check pull the results page template without creating a new instance of the WPUS clas
	 */
	function wpus_option($option) {
		$options = get_option('wpus_options');
		if(isset($options[$option])) {
			return $options[$option];
		} else {
			return FALSE;
		}
	}
}
