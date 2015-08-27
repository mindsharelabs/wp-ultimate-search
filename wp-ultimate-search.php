<?php
/*
Plugin Name: WP Ultimate Search
Plugin URI: https://wordpress.org/plugins/wp-ultimate-search/
Description: Advanced faceted AJAX search and filter utility.
Version: 2.0.3
Author: Mindshare Studios, Inc.
Author URI: https://mindsharelabs.com/
*/

/**
 * @copyright Copyright (c) 2015. All rights reserved.
 * @author    Mindshare Studios, Inc.
 *
 * Spanish translation by Andrew Kurtis <andrewk@webhostinghub.com>
 * Russian translation by Andrijana Nikolic <andrijanan@webhostinggeeks.com>
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
 */

/* CONSTANTS */
if (!defined('WPUS_MIN_WP_VERSION')) {
	define('WPUS_MIN_WP_VERSION', '4.0');
}

if (!defined('WPUS_PLUGIN_NAME')) {
	define('WPUS_PLUGIN_NAME', 'WP Ultimate Search');
}

if (!defined('WPUS_PLUGIN_SLUG')) {
	define('WPUS_PLUGIN_SLUG', 'wp-ultimate-search');
}

if (!defined('WPUS_DIR_PATH')) {
	define('WPUS_DIR_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WPUS_DIR_URL')) {
	define('WPUS_DIR_URL', plugin_dir_url(__FILE__));
}

// check WordPress version
global $wp_version;
if (version_compare($wp_version, WPUS_MIN_WP_VERSION, "<")) {
	exit(WPUS_PLUGIN_NAME . ' requires WordPress ' . WPUS_MIN_WP_VERSION . ' or newer.');
}

// deny direct access
if (!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

/**
 *  WPUltimateSearch CONTAINER CLASS
 */
if (!class_exists("WPUltimateSearch")) :
	class WPUltimateSearch {

		public $options;

		private $radius_facet;

		function __construct() {

			$this->options = get_option('wpus_options');

			if (is_admin()) {
				require_once(WPUS_DIR_PATH . 'lib/options/options.php'); // include Options framework
				require_once(WPUS_DIR_PATH . 'views/wpus-options.php'); // include options file

				$plugin = plugin_basename(__FILE__);
				add_filter("plugin_action_links_$plugin", array($this, 'wpus_settings_link'));
			}

			add_action('init', array($this, 'init'));

			// REGISTER AJAX FUNCTIONS WITH ADMIN-AJAX
			add_action('wp_ajax_wpus_search', array($this, 'get_results'));
			add_action('wp_ajax_nopriv_wpus_search', array($this, 'get_results')); // need this to serve non logged in users
			add_action('wp_ajax_wpus_getvalues', array($this, 'get_values'));
			add_action('wp_ajax_nopriv_wpus_getvalues', array($this, 'get_values')); // need this to serve non logged in users

			add_filter('wpus_date_save_format', array($this, 'date_save_format'));
			add_filter('wpus_date_display_format', array($this, 'date_display_format'));

			// REGISTER SHORTCODES
			add_shortcode(WPUS_PLUGIN_SLUG . "-bar", array($this, 'search_form'));
			add_shortcode(WPUS_PLUGIN_SLUG . "-results", array($this, 'search_results'));

			// REGISTER WIDGET
			add_action('widgets_init', array($this, 'wpus_register_widgets'));

			// CREATE SEARCH RESULTS PAGE ON ACTIVATION
			register_activation_hook(__FILE__, array($this, 'activation_hook'));

			// REGISTER SCRIPTS IF GLOBAL SCRIPTS ARE ENABLED
			add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
		}

		// Add settings link on plugin page
		public function wpus_settings_link($links) {
			$settings_link = '<a href="options-general.php?page=wpus-options">Settings</a>';
			array_unshift($links, $settings_link);

			return $links;
		}

		/**
		 * wpus_register_widgets
		 *
		 */
		function wpus_register_widgets() {
			require_once(WPUS_DIR_PATH . 'views/wpus-widget.php'); // include widget file
			register_widget('wpultimatesearchwidget');
		}

		function init() {

			if (isset($this->options['override_default'])) {
				if ($this->options['override_default']) {
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
			foreach ($pages as $page) {
				if ($page->post_name == "search") {
					return;
				}
			} // if search page already exists, exit
			$results_page = array(
				'post_title'     => 'Search',
				'post_content'   => '[' . WPUS_PLUGIN_SLUG . '-bar]<br />[' . WPUS_PLUGIN_SLUG . '-results]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_name'      => 'search',
				'comment_status' => 'closed'
			);
			wp_insert_post($results_page);
		}

		/**
		 *  PRIVATE FUNCTIONS
		 *
		 * @param        $posts
		 * @param        $orderby
		 * @param string $order
		 * @param bool   $unique
		 *
		 * @return array|bool
		 */

		private function sort_posts($posts, $orderby, $order = 'ASC', $unique = TRUE) {
			if (!is_array($posts)) {
				return FALSE;
			}

			usort($posts, array(new WPUS_Sort_Posts($orderby, $order), 'sort'));

			// use post ids as the array keys
			if ($unique && count($posts)) {
				$posts = array_combine(wp_list_pluck($posts, 'ID'), $posts);
			}

			return $posts;
		}

		private function lat_long_to_distance($lat1, $lng1, $lat2, $lng2, $format) {

			$latFrom = deg2rad($lat1);
			$lonFrom = deg2rad($lng1);
			$latTo = deg2rad($lat2);
			$lonTo = deg2rad($lng2);

			$lonDelta = $lonTo - $lonFrom;
			$a = pow(cos($latTo) * sin($lonDelta), 2) +
				 pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
			$b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

			$angle = atan2(sqrt($a), $b);

			$earthRadius = 6371000;

			$dist = $angle * $earthRadius;

			if ($format == 'mi') {
				return $dist / 1609.34;
			} elseif ($format == 'km') {
				return $dist / 1000;
			} elseif ($format == 'km') {
				return $dist;
			}
		}

		private function prepare_meta_value($facet, $data) {
			$options = $this->options;

			foreach ($options['metafields'] as $metafield => $value) {

				if ($metafield == $facet) {

					if ($value['type'] == 'checkbox') {

						$data = serialize(array($data));

						return $data;
					} elseif ($value['type'] == 'date') {
						return date(apply_filters('wpus_date_save_format', $this->date_save_format()), strtotime($data)); // @todo fix error
					} elseif ($value['type'] == 'true-false') {

						if ($data == "True") {
							return '1';
						} else {
							return '0';
						}
					} elseif ($value['type'] == 'radius') {

						$prepaddr = urlencode($data);
						$geocode = file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $prepaddr . '&sensor=false');
						$output = json_decode($geocode);
						$lat = $output->results[0]->geometry->location->lat;
						$long = $output->results[0]->geometry->location->lng;
						$this->radius_facet = $facet;

						return array($data, $lat, $long);
					}
				}
			}

			return $data;
		}

		private function filter_radius($results, $location, $radius) {

			if ($radius == NULL) {
				$radius = $this->options['radius_dist'];
			}

			foreach ($results as $id => $result) {

				if ($result_location = get_post_meta($result->ID, $this->radius_facet)) {

					if (!empty($result_location[0]['lat'])) {
						// New ACF GMaps field
						$distance = $this->lat_long_to_distance($location[1], $location[2], $result_location[0]['lat'], $result_location[0]['lng'], $this->options['radius_format']);;
					} elseif (is_string($result_location[0])) {
						// Other maps field. Try to get lat long data from string
						$noaddr = strstr($result_location[0], '|');
						$noaddr = trim($noaddr, '|');
						$latlng = explode(",", $noaddr);
						$distance = $this->lat_long_to_distance($location[1], $location[2], $latlng[0], $latlng[1], $this->options['radius_format']);
					}

					if ($distance >= $radius || empty($result_location[0]['lat'])) {
						unset($results[ $id ]);
					} else {
						// Put the geo data in the output array in case the user wants to do something with it
						$results[ $id ]->distance = $distance;
						$results[ $id ]->location = $location[0];
					}
				}
			}

			return $results;
		}

		private function get_user_by_display_name($display_name) {
			global $wpdb;

			if (!$user = $wpdb->get_row($wpdb->prepare(
				"SELECT `ID` FROM $wpdb->users WHERE `display_name` = %s", $display_name
			))
			) {
				return FALSE;
			}

			return $user->ID;
		}

		/**
		 * @param $searcharray
		 */
		public function execute_query_pro($searcharray) {

			$radius = NULL;

			foreach ($searcharray as $index) {
				// iterate through the search query array and separate the taxonomies into their own array
				foreach ($index as $facet => $data) {
					$facet = esc_sql($facet);
					if ($facet == "tag") {
						$facet = "post_tag";
					}

					$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

					switch ($type) {
						case "text" :
							// $keywords = $this->string_to_keywords($data);
							$keywords = $data;
							break;
						case "taxonomy" :
							$facet = $this->get_taxonomy_name($facet);
							$data = preg_replace('/_/', " ", $data); // in case there are underscores in the value (from a permalink), remove them
							$term = get_term_by('name', $data, $facet);
							if ($term != FALSE) {
								$taxonomies[ $facet ][] = $term->term_id;
							}
							break;
						case "metafield" :
							$data = preg_replace('/_/', " ", $data); // in case there are underscores in the value (from a permalink), remove them
							$facet = $this->get_metafield_name($facet);
							$data = $this->prepare_meta_value($facet, $data);
							$metafields[][ $facet ] = $data;
							break;
						case "radius" :
							$radius = $data;
							break;
						case "user" :
							$users[] = $this->get_user_by_display_name($data);
					}
				}
			}

			$query = array(
				'posts_per_page' => -1,
				'post_status'    => 'publish'
			);

			// Text search
			if (isset($keywords)) {

				$query['s'] = $keywords;
			}

			// Taxonomy search
			if (isset($taxonomies)) {

				$query['tax_query'] = array();

				// Create an AND relation between different taxonomies
				if (count($taxonomies) > 1) {
					$query['tax_query']['relation'] = "AND";
				}

				foreach ($taxonomies as $taxonomy => $terms) {

					// By default, use an OR operation on terms w/in the same taxonomy
					$operator = "IN";
					$include_children = TRUE;

					if (count($terms) > 1 && $this->options['and_or'] == "and") {

						$query['tax_query']['relation'] = "AND";

						foreach ($terms as $term) {

							$query['tax_query'][] = array(
								'taxonomy'         => $taxonomy,
								'terms'            => $term,
								'operator'         => "IN",
								'include_children' => TRUE
							);
						}
					} else {

						$query['tax_query'][] = array(
							'taxonomy'         => $taxonomy,
							'terms'            => $terms,
							'operator'         => $operator,
							'include_children' => $include_children
						);
					}
				}
			}

			// Meta fields
			if (isset($metafields)) {

				$query['meta_query'] = array();

				if ($this->options['and_or'] == "and" && count($metafields) > 1) {
					$query['meta_query']['relation'] = "AND";
				} elseif ($this->options['and_or'] == "or" && count($metafields) > 1) {
					$query['meta_query']['relation'] = "OR";
				}

				foreach ($metafields as $metafield) {

					foreach ($metafield as $name => $metadata) {

						// Since there's no way to do logical operations on geodata stored in a serialized array, we need to pull out every post that Has geodata at all, and then process them one by one.

						if (is_array($metadata)) {

							$location = $metadata;

							$location_query_results = get_posts('meta_key=' . $name . '&posts_per_page=-1&post_type=any');
						} else {

							$query['meta_query'][] = array(
								'key'     => $name,
								'value'   => $metadata,
								'compare' => 'LIKE'
							);
						}
					}
				}
			}

			// Post types
			if (isset($this->options['posttypes'])) {

				$posttypes = $this->options['posttypes'];
				$query['post_type'] = array();

				foreach ($posttypes as $type => $data) {
					if (isset($data['enabled'])) {

						$query['post_type'][] = $type;
					}
				}
			}

			// Users
			if (isset($users) && count($users) > 0) {
				$query['author__in'] = $users;
			}

			// Pass it all through to WP_Query
			$wpus_results = new WP_Query($query);

			if (!isset($keywords)) {
				$keywords = NULL;
			}

			$location_arr = array();

			// If we're conducting a radius search, we need to run a separate query and then merge it back into the other results
			if (isset($this->options['radius']) && $this->options['radius'] != FALSE && isset($location)) {

				if ($radius == NULL) {
					$radius = $this->options['radius_dist'];
				}

				// filter_radius() returns an array of posts, where any post outside of the specified radius from the origin has been removed
				$geo_filtered_posts = $this->filter_radius($location_query_results, $location, $radius);

				// These variables will be passed to the results template for integration with mapping
				$location_arr['address'] = $location[0];
				$location_arr['lat'] = $location[1];
				$location_arr['lng'] = $location[2];
				$location_arr['radius'] = $radius;

				// Grab an array of post ID's from our geographically elligible posts
				$location_ids = wp_list_pluck($geo_filtered_posts, 'ID');

				// Iterate through the results delivered by the main query, and remove any results that don't qualify
				foreach ($wpus_results->posts as $key => $value) {
					if (!in_array($value->ID, $location_ids)) {
						unset($wpus_results->posts[ $key ]);
					}
				}

				// Reorder the array so it's organized again

				$wpus_results->posts = array_values($this->sort_posts($wpus_results->posts, 'ID'));
				$wpus_results->post_count = count($wpus_results->posts);
			}

			$this->print_results($wpus_results, $keywords, $location_arr); // format and output the search results

			die(); // wordpress may print out a spurious zero without this - can be particularly bad if using json
		}

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
			return preg_replace('/(' . implode('|', $keywords) . ')/i', '<strong class="wpus-highlight">$0</strong>', $text);
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
			for ($i = 0; $i < count($keywords[0]); $i++) {
				$keywords[0][ $i ] = stripslashes($keywords[0][ $i ]);
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

			if ($remove_breaks) {
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
				            "' . $parameter . '":"' . $response . '"
				    };
				    /* ]]> */
				    </script>';
		}

		/**
		 *
		 * Shortcode localize
		 *
		 *
		 * Similar to wp_localize_script, but wp_localize_script can only be called on plugin load / on
		 * page load. This function can be called after parsing the shortcode attributes to output
		 * any updated parameters to the page
		 *
		 * @param $params
		 *
		 * @internal param $parameter
		 * @internal param $response
		 */
		private function shortcode_localize($params) {
			echo '
				<script type="text/javascript">
				    /* <![CDATA[ */
				    var shortcode_localize = {';

			if ($params) {
				foreach ($params as $key => $value) {
					echo '"' . $key . '":"' . $value . '",';
				}
			}

			echo ' };
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
		protected function print_results($wpus_results, $keywords, $location) {

			ob_start();

			if (file_exists(TEMPLATEPATH . '/wpus-results-template.php')) {

				require(TEMPLATEPATH . '/wpus-results-template.php');
			} else {

				if ($this->options['results_template'] == 'thumbnail') {

					require(WPUS_DIR_PATH . 'views/wpus-results-template-thumbnail.php');
				} elseif ($this->options['results_template'] == 'titles') {

					require(WPUS_DIR_PATH . 'views/wpus-results-template-titles.php');
				} elseif ($this->options['results_template'] == 'images') {

					require(WPUS_DIR_PATH . 'views/wpus-results-template-images.php');
				} else {

					require(WPUS_DIR_PATH . 'views/wpus-results-template.php');
				}
			}

			// if we're tracking searches as analytics events, pass the number of search results back to main.js
			if (wpus_option('track_events')) {
				$this->ajax_response('numresults', $wpus_results->found_posts);
			}

			echo ob_get_clean();
			//@todo add an option to switch to non post_object based results output (just title and excerpt)
		}

		/**
		 *
		 * Get Enabled Taxonomies
		 *
		 * Return an array of all taxonomies which are currently selected in the options window
		 *
		 * @return array
		 */
		private function get_enabled_facets() {
			$options = $this->options;

			foreach ($options['taxonomies'] as $taxonomy => $key) {
				if (isset($key['enabled'])) {
					if ($key['enabled']) {
						if ($key['label']) {
							$enabled_facets[] = $key['label'];
						} else {
							$enabled_facets[] = $taxonomy;
						}
					}
				}
			}
			if (isset($options['metafields'])) {
				foreach ($options['metafields'] as $metafield => $key) {
					if (isset($key['enabled'])) {
						if ($key['enabled']) {
							if ($key['label']) {
								$enabled_facets[] = $key['label'];
							} else {
								$enabled_facets[] = $metafield;
							}
						}
					}
				}
			}

			if (isset($options['radius']) && $options['radius'] != FALSE) {
				$enabled_facets[] = $options['radius_label'];
			}

			if (isset($options['enable_user_search']) && $options['enable_user_search'] != FALSE) {
				$enabled_facets[] = $options['user_label'];
			}

			return $enabled_facets;
		}

		/**
		 *
		 * Date save format
		 *
		 *
		 * Filter which controls the format of the date field as it's stored in the database
		 *
		 *
		 * @return string
		 */

		public function date_save_format() {
			return 'Ymd';
		}

		/**
		 *
		 * Date display format
		 *
		 *
		 * Filter which controls the format of the date as it's displayed in the search interface
		 *
		 *
		 * @return string
		 */

		public function date_display_format() {
			return 'n/j/Y';
		}

		/**
		 *
		 * Format Meta By Type
		 *
		 *
		 * Formats a meta fields contents for display based on the type of the data
		 *
		 * @param $facet
		 * @param $data
		 *
		 * @return string
		 */

		private function format_meta_by_type($facet, $data) {

			$options = $this->options;

			foreach ($options['metafields'] as $metafield => $value) {
				if ($metafield == $facet) {
					if ($value['type'] == 'checkbox') {
						$data = unserialize($data);

						return $data[0];
					} elseif ($value['type'] == 'true-false') {
						if ($data == 1) {
							return 'True';
						}
					} elseif ($value['type'] == 'geo') {
						$data = unserialize($data);

						return $data['address'];
					} elseif ($value['type'] == 'date') {
						$date = DateTime::createFromFormat(apply_filters('wpus_date_save_format', $this->date_save_format()), $data);

						return $date->format(apply_filters('wpus_date_display_format', $this->date_display_format()));
					}
				}
			}

			return $data;
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
			$options = $this->options;

			foreach ($options['taxonomies'] as $taxonomy => $value) {
				if ($value['label'] == $label) {
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
			$options = $this->options;

			foreach ($options['metafields'] as $metafield => $value) {
				if ($value['label'] == $label) {
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
			$options = $this->options;

			if ($facet == $options['remainder']) {
				return "text";
			}

			if (isset($options['radius_label']) && $facet == $options['radius_label']) {
				return "radius";
			}

			if (isset($options['user_label']) && $facet == $options['user_label']) {
				return "user";
			}

			if (isset($options['taxonomies'])) {
				foreach ($options['taxonomies'] as $taxonomy => $value) {
					if ($value['label'] == $facet || $taxonomy == $facet) {
						return "taxonomy";
					}
				}
			}
			if (isset($options['metafields'])) {
				foreach ($options['metafields'] as $metafield => $value) {
					if ($value['label'] == $facet || $metafield == $facet) {
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
			wp_enqueue_script('underscore');
			wp_enqueue_script('backbone');
			wp_enqueue_script(
				'visualsearch',
				WPUS_DIR_URL . 'js/visualsearch.min.js',
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

			$options = $this->options;

			if (isset($options['radius']) && $options['radius'] != FALSE) {
				$radius = $options['radius'];
			} else {
				$radius = FALSE;
			}

			// ENQUEUE AND LOCALIZE MAIN JS FILE

			wp_enqueue_script('wpus-script', WPUS_DIR_URL . 'js/main-pro.js', array('visualsearch'), '', wpus_option('scripts_in_footer'));
			if ($radius) {
				wp_enqueue_script('google-maps', 'http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places');
				wp_enqueue_script('geocomplete', WPUS_DIR_URL . 'js/jquery.geocomplete.js', array('jquery', 'google-maps'), '', wpus_option('scripts_in_footer'));
			}

			($options['show_facets'] == 1 ? $showfacets = TRUE : $showfacets = FALSE);
			($options['highlight_terms'] == 1 ? $highlight = TRUE : $highlight = FALSE);

			$params = array(
				'ajaxurl'            => admin_url('admin-ajax.php'),
				'searchNonce'        => wp_create_nonce('search-nonce'),
				'trackevents'        => $options['track_events'],
				'eventtitle'         => $options['event_category'],
				'enabledfacets'      => json_encode($this->get_enabled_facets()),
				'resultspage'        => get_permalink($options['results_page']),
				'showfacets'         => $showfacets,
				'placeholder'        => $options['placeholder'],
				'highlight'          => $highlight,
				'radius'             => $radius,
				'remainder'          => $options['remainder'],
				'single_facet'       => $options['single_facet_mode'],
				'disable_permalinks' => $options['disable_permalinks'],
				'single_use'         => $options['single_use'],
			);

			wp_localize_script('wpus-script', 'wpus_script', $params);

			// ENQUEUE STYLES
			if (isset($options['style'])) {
				if ($options['style'] == 'square') {
					wp_enqueue_style('wpus-bar', WPUS_DIR_URL . 'css/square.css');
				} else {
					wp_enqueue_style('wpus-bar', WPUS_DIR_URL . 'css/visualsearch.css');
				}
			} else {
				wp_enqueue_style('wpus-bar', WPUS_DIR_URL . 'css/visualsearch.css');
			}
		}

		/**
		 * search_form
		 *
		 * @return string
		 */
		public function search_form($atts) {

			if (isset($atts['widget']) && get_the_ID() == $this->options['results_page']) {
				return;
			}

			// Make the attributes available to JS
			$this->shortcode_localize($atts);

			$class = '';

			if ($this->options['single_facet_mode'] == TRUE) {
				$class = "single-facet";
			}

			// RENDER SEARCH FORM
			return '<div id="search_box_container" class="' . $class . '"><div id="search"><div class="VS-search">
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
			if (!isset($facet)) {
				exit;
			} // if nothing's been set, we can exit

			// Grab shortcode overrides, if any
			if (isset($_GET['exclude'])) {
				$exclude = $_GET['exclude'];
			}
			if (isset($_GET['include'])) {
				$include = $_GET['include'];
			}

			$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

			$options = $this->options;

			switch ($type) {
				case "taxonomy" :
					$facet = $this->get_taxonomy_name($facet); // get the database taxonomy name from the current facet

					if (!isset($options['taxonomies'][ $facet ]['autocomplete'])) {
						die();
					}

					if (isset($options['taxonomies'][ $facet ]['max'])) {
						$number = $options['taxonomies'][ $facet ]['max'];
					} else {
						$number = 50; // set a max of 50 terms, so we don't break anything
					}

					// Create the array of terms to exclude
					$excludetermids = array();

					if (!empty($options['taxonomies'][ $facet ]['exclude']) || !empty($exclude)) {

						if (!empty($exclude)) {
							$excludetermids = explode(',', $exclude);
						} else {
							$excludetermids = explode(',', $options['taxonomies'][ $facet ]['exclude']);
						}

						foreach ($excludetermids as $term_id) {

							// Check for child terms and add them to the array if found
							$children = get_term_children($term_id, $facet);
							if (count($children) > 0) {
								$excludetermids = array_merge($excludetermids, $children);
							}
						}
					}

					// Create the array of terms to include
					$includetermids = array();

					if (!empty($options['taxonomies'][ $facet ]['include']) || !empty($include)) {

						if (!empty($include)) {
							$includetermids = explode(',', $include);
						} else {
							$includetermids = explode(',', $options['taxonomies'][ $facet ]['include']);
						}

						foreach ($includetermids as $term_id) {

							// Check for child terms and add them to the array if found
							$children = get_term_children($term_id, $facet);
							if (count($children) > 0) {
								$includetermids = array_merge($includetermids, $children);
							}
						}
					}

					$args = array( // parameters for the term query
								   'orderby' => 'name',
								   'order'   => 'ASC',
								   'number'  => $number,
								   'exclude' => $excludetermids,
								   'include' => $includetermids,
					);

					$terms = get_terms($facet, $args);

					foreach ($terms as $term) {
						$values[] = html_entity_decode($term->name);
					}

					echo json_encode($values); // json encode the results array and pass it back to the UI
					die();

				case "metafield" :

					$facet = $this->get_metafield_name($facet);

					if (!isset($options['metafields'][ $facet ]['autocomplete'])) {
						die();
					}

					global $wpdb;

					// get the values from post_meta where the meta key matches the search facet...
					$querystring = "
						SELECT pm.meta_value as value FROM {$wpdb->postmeta} pm
						WHERE pm.meta_key LIKE '{$facet}'
						GROUP BY value
						ORDER BY value ASC";
					// this will be cached, eventually
					$results = $wpdb->get_results($querystring);

					foreach ($results as $key) {
						if (!empty($key->value)) { // for some reason, $results sometimes returns zero-length strings as keys, so this filters them out
							$formatted_value = $this->format_meta_by_type($facet, $key->value);
							$values[] = $formatted_value;
						}
					}

					// Add "False" value for true/false fields

					if ($values[0] == 'True') {
						$values[] = 'False';
					}

					echo json_encode($values);
					die();

				case "user" :

					if (isset($options['user_autocomplete']) && $options['user_autocomplete'] != FALSE) {

						$roles = $options['enabled_roles'];

						foreach ($roles as $role => $enabled) {

							$users = get_users('role=' . $role);

							foreach ($users as $user) {
								$values[] = html_entity_decode($user->display_name);
							}
						}

						echo json_encode($values);
					}

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

			if (!isset($_GET['wpusquery'])) {
				die(); // if no data has been entered, quit
			} else {
				$searcharray = $_GET['wpusquery'];
			}

			$nonce = $_GET['searchNonce'];
			if (!wp_verify_nonce($nonce, 'search-nonce')) // make sure the search nonce matches the nonce generated earlier
			{
				die ('Busted!');
			}

			$this->execute_query_pro($searcharray);
		}
	}

	class WPUS_Sort_Posts extends WPUltimateSearch {
		var $order, $orderby;

		function __construct($orderby, $order) {
			$this->orderby = $orderby;
			$this->order = ('desc' == strtolower($order)) ? 'DESC' : 'ASC';
		}

		function sort($a, $b) {
			if ($a->{$this->orderby} == $b->{$this->orderby}) {
				return 0;
			}

			if ($a->{$this->orderby} < $b->{$this->orderby}) {
				return ('ASC' == $this->order) ? -1 : 1;
			} else {
				return ('ASC' == $this->order) ? 1 : -1;
			}
		}
	}

endif;

/**
 *  GLOBAL FUNCTIONS AND TEMPLATE TAGS
 */
if (class_exists("WPUltimateSearch")) {

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
	function wp_ultimate_search_bar($atts = NULL) {
		global $wp_ultimate_search;
		echo $wp_ultimate_search->search_form($atts);
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
		if (isset($options[ $option ])) {
			return $options[ $option ];
		} else {
			return FALSE;
		}
	}
}
