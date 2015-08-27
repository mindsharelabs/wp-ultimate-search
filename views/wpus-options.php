<?php

define('WPUS_STORE_URL', 'https://mindsharelabs.com');

if (!class_exists('WPUltimateSearchOptions')) {

	class WPUltimateSearchOptions extends WPUS_options {

		private $settings = array(), $sections = array(), $options;
		public $setup = array(
			'project_name' => 'WP Ultimate Search',
			'project_slug' => 'wp-ultimate-search',
			'page_title'   => 'WP Ultimate Search',
			'menu_title'   => 'WP Ultimate Search',
			'option_group' => 'wpus_options',
			'slug'         => 'wpus-options'
		);

		public function __construct() {

			$this->options = get_option('wpus_options');

			if (!function_exists('is_plugin_active_for_network')) {
				require_once(ABSPATH . '/wp-admin/includes/plugin.php');
			}

			$this->create_sections();

			// Update fields needing updating and save them back to the db
			$this->update_taxonomies();
			$this->update_meta_fields();
			$this->update_post_types();
			update_option('wpus_options', $this->options);

			// Print additional scripts
			add_action('admin_print_scripts-settings_page_wpus-options', array($this, 'wpus_admin_scripts'));

			// Page layout actions
			add_action('show_section_about', array($this, 'display_about_section'), 10, 2);
			add_action('show_section_taxopts', array($this, 'display_taxonomy_section'), 10, 2);
			add_action('show_section_metaopts', array($this, 'display_meta_section'), 10, 2);
			add_action('show_section_typeopts', array($this, 'display_type_section'), 10, 2);
			add_action('show_field_results_page', array($this, 'show_search_select'), 10, 2);
			add_action('show_field_user_roles', array($this, 'user_roles'), 10, 2);

			add_action('init', array($this, 'initialize'));
		}

		/**
		 * Enqueue scripts needed for the WPUS options page
		 *
		 * @access public
		 *
		 *
		 */

		public function wpus_admin_scripts() {

			wp_enqueue_script('tiptip', WPUS_DIR_URL . 'js/jquery.tipTip.minified.js', array('jquery'));
			wp_enqueue_script('main', WPUS_DIR_URL . 'js/wpus-main-admin.js', array('jquery'));
			wp_localize_script('main', 'main', json_encode($this->sections));

			wp_enqueue_style('wpus-admin', WPUS_DIR_URL . 'css/wpus-options.css');
		}

		/**
		 *
		 */
		private function update_taxonomies() {

			$taxonomies = get_taxonomies(array('public' => TRUE));

			foreach ($taxonomies as $taxonomy) {
				if (!isset($this->options['taxonomies'][ $taxonomy ])) {
					if ($taxonomy == 'post_tag') {
						$this->options['taxonomies'][ $taxonomy ] = array(
							"enabled"      => 1,
							"label"        => 'tag',
							"max"          => 0,
							"exclude"      => '',
							"autocomplete" => 1
						);
					} elseif ($taxonomy == 'category') {
						$this->options['taxonomies'][ $taxonomy ] = array(
							"enabled"      => 1,
							"label"        => $taxonomy,
							"max"          => 0,
							"exclude"      => '',
							"autocomplete" => 1
						);
					} else {
						$this->options['taxonomies'][ $taxonomy ] = array(
							"enabled"      => 0,
							"label"        => $taxonomy,
							"max"          => 0,
							"exclude"      => '',
							"autocomplete" => 1
						);
					}
				}
			}
		}

		/**
		 *
		 * Update meta fields
		 *
		 * Queries the database for all available post_meta options (that might be useful) and
		 * stores them in an array. Optionally accepts a $count, which denotes how many instances
		 * of a particular key have to be available before we register it as valid.
		 *
		 * @param int $count
		 */

		private function update_meta_fields($count = 1) {

			global $wpdb;
			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC";

			$allkeys = $wpdb->get_results($querystring);

			// set default values for all meta keys without stored settings

			foreach ($allkeys as $i => $key) {
				if ($key->{'count'} > $count && !isset($this->options["metafields"][ $key->{"meta_key"} ])) {
					$this->options["metafields"][ $key->{"meta_key"} ] = array(
						"enabled"      => 0,
						"label"        => $key->{"meta_key"},
						"count"        => $key->{"count"},
						"type"         => "string",
						"autocomplete" => 1
					);
				}
				// count the instances of each key, overwrite whatever it was before
				if ($key->{'count'} > $count) {
					$this->options["metafields"][ $key->{"meta_key"} ]["count"] = $key->{'count'};
				}
			}
		}

		public function update_post_types() {

			$posttypes = get_post_types(array('public' => TRUE));

			foreach ($posttypes as $type) {
				if (!isset($this->options['posttypes'][ $type ])) {
					$this->options['posttypes'][ $type ] = array(
						"label"   => $type,
						"enabled" => 1
					);
				}
			}
		}

		private function create_sections() {
			$this->sections['wpus-options']['general'] = __('General Settings');
			$this->sections['wpus-options']['taxopts'] = __('Taxonomy Settings');
			$this->sections['wpus-options']['metaopts'] = __('Post Meta Settings');
			$this->sections['wpus-options']['typeopts'] = __('Post Type Settings');
			$this->sections['wpus-options']['about'] = __('About');
			$this->sections['wpus-options']['reset'] = __('Reset to Defaults');
		}

		private function create_settings() {

			/*
			/ SEARCH BOX
			*/

			$this->settings['box_heading'] = array(
				'section' => 'general',
				'title'   => 'Search Box',
				'type'    => 'heading'
			);
			$this->settings['show_facets'] = array(
				'title'   => __('Show facets'),
				'desc'    => __('Show available facets when the search box is first clicked.'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['single_facet_mode'] = array(
				'title'   => __('Single Facet Mode'),
				'desc'    => __('When single facet mode is enabled, the facet selection dialog will be hidden, and the user will get a dropdown of available values on their first click.'),
				'std'     => 0,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['single_use'] = array(
				'title'   => __('Single Use Facets'),
				'desc'    => __('When this box is checked, a given facet can only be used one time in a search query. After this, the facet will no longer appear as an option.'),
				'std'     => 0,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['enable_category'] = array(
				'title'   => __('Taxonomies'),
				'desc'    => __('Category'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['enable_tag'] = array(
				'title'   => __(''),
				'desc'    => __('Tag'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);

			$this->settings['style'] = array(
				'title'   => __('Style'),
				'desc'    => __(''),
				'choices' => array("visualsearch" => "Visual Search", "square" => "Square"),
				'std'     => 'visualsearch',
				'type'    => 'select',
				'section' => 'general'
			);
			$this->settings['placeholder'] = array(
				'title'   => __('Placeholder'),
				'desc'    => __('Text displayed in the search box before a query is entered.'),
				'std'     => "Search",
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['remainder'] = array(
				'title'   => __('Remainder'),
				'desc'    => __('Text displayed to preface queries which don\'t use a facet.'),
				'std'     => "text",
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['override_default'] = array(
				'section' => 'general',
				'title'   => __('Override default search box'),
				'desc'    => __('Select this to replace the default WordPress search for with an instance of WP Ultimate Search.'),
				'type'    => 'checkbox',
				'std'     => 0
			);

			/*
			/ RADIUS SEARCH
			*/

			$this->settings['radius_heading'] = array(
				'section' => 'general',
				'title'   => 'Radius Searches',
				'type'    => 'heading'
			);
			$this->settings['radius_dist'] = array(
				'title'   => __('Radius'),
				'desc'    => __('Set the default distance for radius searches'),
				'std'     => '60',
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['radius_format'] = array(
				'title'   => __('Format'),
				'desc'    => __(''),
				'choices' => array("km" => "Kilometers", "mi" => "Miles", "m" => "Meters"),
				'std'     => 'km',
				'type'    => 'select',
				'section' => 'general'
			);
			$this->settings['radius_label'] = array(
				'title'   => __('Radius Label'),
				'desc'    => __('Set the text that should be displayed as the label for the radius facet'),
				'std'     => 'distance (km)',
				'type'    => 'text',
				'section' => 'general'
			);

			/*
			/ SEARCH RESULTS
			*/

			$this->settings['results_heading'] = array(
				'section' => 'general',
				'title'   => 'Search Results',
				'type'    => 'heading'
			);
			$this->settings['and_or'] = array(
				'title'   => __('Search logic'),
				'desc'    => __('Whether to use AND logic or OR logic for facets within the same taxonomy.'),
				'std'     => 'or',
				'choices' => array(
					'or'  => 'OR',
					'and' => 'AND'
				),
				'type'    => 'radio',
				'section' => 'general'
			);
			$this->settings['clear_search'] = array(
				'title'   => __('"Clear search" button'),
				'desc'    => __('Display a button after search results to clear all terms.'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);

			$pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1));

			$page_select = array();

			foreach ($pages as $page) {
				$page_select[ $page->ID ] = $page->post_title;
			}

			$this->settings['results_page'] = array(
				'title'   => __('Search results page'),
				'desc'    => __('Specify the page with the [' . WPUS_PLUGIN_SLUG . '-results] shortcode.<br />Searches conducted from widget will redirect to this page.'),
				'choices' => $page_select,
				'std'     => array_search('Search', $page_select),
				'type'    => 'select',
				'section' => 'general'
			);

			$this->settings['results_template'] = array(
				'title'   => __('Search results template'),
				'desc'    => __('Select a template for search results. <a href="http://mindsharelabs.com/kb/how-do-i-customize-the-search-results-template/" target="_BLANK">Custom templates</a> will override this section.'),
				'choices' => array(
					'default'   => 'Default results template',
					'thumbnail' => 'Results with featured image thumbnails',
					'titles'    => 'Post titles only',
					'images'    => 'Featured images only'
				),
				'std'     => 'default',
				'type'    => 'select',
				'section' => 'general'
			);

			$this->settings['no_results_msg'] = array(
				'title'   => __('"No results" message'),
				'desc'    => __('Customize the message displayed when no results are found.'),
				'std'     => "Sorry, no results found.",
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['highlight_terms'] = array(
				'title'   => __('Highlight Terms'),
				'desc'    => __('Highlight matching terms in search results.'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['clear_search'] = array(
				'title'   => __('"Clear search" button'),
				'desc'    => __('Display a button after search results to clear all terms.'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['clear_search_text'] = array(
				'title'   => __('Button text'),
				'desc'    => __(''),
				'std'     => 'Clear Search Terms',
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['clear_search_class'] = array(
				'title'   => __('Button CSS class'),
				'desc'    => __('Apply a CSS class to match your theme.'),
				'std'     => 'btn btn-default btn-sm',
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['disable_permalinks'] = array(
				'title'   => __('Disable Permalinks'),
				'desc'    => __('By default, Ultimate Search will update the URL in a user\'s browser as they modify their search query. Check this box to disable that functionality.'),
				'std'     => 0,
				'type'    => 'checkbox',
				'section' => 'general'
			);

			/*
			/ USER SEARCH
			*/
			$this->settings['user_search'] = array(
				'section' => 'general',
				'title'   => 'User Search',
				'type'    => 'heading'
			);

			$this->settings['enable_user_search'] = array(
				'section' => 'general',
				'title'   => __('Enable'),
				'desc'    => __('Check this box to enable searching by user.'),
				'type'    => 'checkbox',
				'std'     => 0
			);
			$this->settings['user_label'] = array(
				'title'   => __('Label'),
				'desc'    => __('Label to show in the dropdown of available facets.'),
				'std'     => "user",
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['user_autocomplete'] = array(
				'section' => 'general',
				'title'   => __('Autocomplete'),
				'desc'    => __('Check this box to enable autocomplete for user searches.'),
				'type'    => 'checkbox',
				'std'     => 0
			);
			$this->settings['enabled_roles'] = array(
				'section' => 'general',
				'title'   => __('User Roles'),
				'desc'    => __('Select the user roles to return in results.'),
				'type'    => 'user_roles',
				'std'     => 0
			);

			/*
			/ ANALYTICS SETTINGS
			*/

			$this->settings['analytics_heading'] = array(
				'section' => 'general',
				'title'   => 'Google Analytics',
				'type'    => 'heading'
			);

			$this->settings['track_events'] = array(
				'section' => 'general',
				'title'   => __('Track Events'),
				'desc'    => __('Enabling this option will cause searches to appear as events in your Google Analytics reports<br /> (requires an Analytics tracking code to be already installed.)'),
				'type'    => 'checkbox',
				'std'     => 0 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);

			$this->settings['event_category'] = array(
				'title'   => __('Event Category'),
				'desc'    => __('Set the category your events will appear under in reports.'),
				'std'     => 'Search',
				'type'    => 'text',
				'section' => 'general'
			);

			$this->settings['reset'] = array(
				'section' => 'reset',
				'title'   => __('Reset options'),
				'type'    => 'reset',
				'desc'    => __('Check this box and click "Save Changes" below to reset all options to their defaults.')
			);
		}

		public function user_roles($id, $field) {

			global $wp_roles;

			if (!isset($wp_roles)) {
				$wp_roles = new WP_Roles();
			}

			$roles = $wp_roles->get_names();
			$options = $this->options;

			foreach ($roles as $role_value => $role_name) {
				echo '<input class="checkbox" id="' . $role_value . '" type="checkbox" name="wpus_options[' . $id . '][' . $role_value . ']" value="1" ' . checked(@$options[ $id ][ $role_value ], 1, FALSE) . ' />';
				echo '<label for="' . $role_value . '">' . $role_name . '</label><br />';
			}
		}

		public function show_search_select($id, $field) {

			$options = $this->options;

			echo '<div id="' . $id . '" class="bfh-selectbox ' . $field['class'] . '" data-name="wpus_options[' . $id . ']' . '" data-value="' . @$this->options[ $id ] . '" ' . ($field['disabled'] ? 'disabled="true"' : '') . ' data-filter="true" >'; // added error suppression for notices per https://github.com/mindsharestudios/wp-ultimate-search/issues/3

			foreach ($field['choices'] as $value => $label) {

				echo '<div data-value="' . esc_attr($value) . '"' . selected($options[ $id ], $value, FALSE) . '>' . $label . '</div>';
			}

			echo '</div>';
		}

		public function display_about_section($slug, $settings) {
			?>

			<p>Developed by <a href="http://mind.sh/are/?ref=wpus">Mindshare Studios, Inc</a>. If you like what we do and want to show your support, consider <a href="http://mind.sh/are/donate/">making
																																																   a
																																																   donation</a>.
			</p>

			<p>Plugin page on <a href="http://wordpress.org/extend/plugins/<?= WPUS_PLUGIN_SLUG ?>/">WordPress.org</a></p>

			<h4>Usage</h4>

			<p><strong>To use the shortcode</strong>: Place <code>[wp-ultimate-search-bar]</code> where you'd like the search bar, and <code>[wp-ultimate-search-results]</code> where you'd like the
													results.</p>

			<p><strong>To use the template tag</strong>: Place <code>wp_ultimate_search_bar();</code> where you'd like the search bar, and <code>wp_ultimate_search_results();</code> where you'd like
													   the results.</p>

			<?php
		}

		public function display_taxonomy_section($slug, $settings) {

			$options = $this->options;

			?>

			<table class="widefat table table-striped">
				<thead>
				<tr>
					<th class="nobg">Taxonomy
						<div class="tooltip" title="Taxonomy label field, as it's stored in the database."></div>
					</th>
					<th>Enabled
						<div class="tooltip" title="Whether or not to include this term as a search facet."></div>
					</th>
					<th>Label override
						<div class="tooltip" title="You can specify a label which will be autocompleted in the search box. This will override the taxonomy's default label."></div>
					</th>
					<th>Terms found
						<div class="tooltip" title="Number of terms in the taxonomy. Hover over the number for a listing."></div>
					</th>
					<th>Max terms
						<div class="tooltip" title="Set a maximum number of terms to load in the autocomplete dropdown. Use '0' for unlimited."></div>
					</th>
					<th>Exclude
						<div class="tooltip" title="Comma-separated list of term IDs to exclude from autocomplete. Child terms will be excluded as well."></div>
					</th>
					<th>Include
						<div class="tooltip" title="Comma-separated list of term IDs to include in autocomplete, all other terms will be excluded. Child terms will be included as well."></div>
					</th>
					<th>Autocomplete
						<div class="tooltip" title="Whether or not to autocomplete values typed into this field."></div>
					</th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th class="nobg">Taxonomy</th>
					<th>Enabled</th>
					<th>Label override</th>
					<th>Terms found</th>
					<th>Max terms</th>
					<th>Exclude</th>
					<th>Include</th>
					<th>Autocomplete</th>
				</tr>
				</tfoot>
				<tbody>
				<?php
				if (isset($options) && array_key_exists('taxonomies', $options)) {
					foreach ($options['taxonomies'] as $taxonomy => $value) {

						// If the taxonomy is active, set the 'checked' class
						if (!empty($value['enabled'])) {
							$checked = 'checked';
						} else {
							$checked = '';
							$options['taxonomies'][ $taxonomy ]['enabled'] = 0;
						}

						if (empty($value["autocomplete"])) {
							$options["taxonomies"][ $taxonomy ]["autocomplete"] = 0;
						}

						// Generate the list of terms for the "Count" tooltip
						$terms = get_terms($taxonomy);
						$termcount = count($terms);
						$termstring = '';

						if (!is_wp_error($terms)) {
							foreach ($terms as $term) {
								$termstring .= $term->name . ', ';
							}
						}
						?>
						<tr>
							<th scope="row" class="tax"><span id="<?php echo $taxonomy . '-title' ?>" class="<?php echo $checked ?>"><?php echo $taxonomy ?>:<div class="VS-icon-cancel"></div></span>
							</th>
							<td>
								<input class="checkbox" type="checkbox" id="<?php echo $taxonomy ?>" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][enabled]" value="1" <?php echo checked($options['taxonomies'][ $taxonomy ]['enabled'], 1, FALSE) ?> />
							</td>
							<td>
								<input class="" type="text" id="<?php echo $taxonomy ?>" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][label]" size="20" placeholder="<?php echo $taxonomy ?>" value="<?php echo esc_attr($options['taxonomies'][ $taxonomy ]['label']) ?>" />
							</td>
							<td><?php echo $termcount ?>
								<div class="tooltip" title="<?php echo $termstring ?>"></div>
							</td>
							<td>
								<input class="" type="text" id="<?php echo $taxonomy ?>" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][max]" size="3" placeholder="0" value="<?php echo esc_attr($options['taxonomies'][ $taxonomy ]['max']) ?>" />
							</td>
							<td>
								<input class="" type="text" id="<?php echo $taxonomy ?>" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][exclude]" size="30" placeholder="" value="<?php echo esc_attr($options['taxonomies'][ $taxonomy ]['exclude']) ?>" />
							</td>
							<td>
								<input class="" type="text" id="<?php echo $taxonomy ?>" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][include]" size="30" placeholder="" value="<?php echo(isset($options['taxonomies'][ $taxonomy ]['include']) ? esc_attr($options['taxonomies'][ $taxonomy ]['include']) : '') ?>" />
							</td>
							<td>
								<input class="checkbox" type="checkbox" name="wpus_options[taxonomies][<?php echo $taxonomy ?>][autocomplete]" value="1" <?php echo checked($options["taxonomies"][ $taxonomy ]["autocomplete"], 1, FALSE) ?> />
							</td>
						</tr>
					<?php }
				} ?>
				</tbody>
			</table>
			<?php
		}

		public function display_meta_section($slug, $settings) {
			?>

			<?php

			$options = $this->options;
			?>

			<table class="widefat table table-striped">
				<thead>
				<tr>
					<th class="nobg">Meta Key
						<div class="tooltip" title="Meta key field, as it's stored in the database."></div>
					</th>
					<th>Enabled
						<div class="tooltip" title="Whether or not to include this term as a search facet."></div>
					</th>
					<th>Label override
						<div class="tooltip" title="You can specify a label which will be autocompleted in the search box. This will override the field's default label."></div>
					</th>
					<th>Instances
						<div class="tooltip" title="Number of times a particular meta field was found in the database."></div>
					</th>
					<th>Type
						<div class="tooltip" title="The format of the data."></div>
					</th>
					<th>Autocomplete
						<div class="tooltip" title="Whether or not to autocomplete values typed into this field."></div>
					</th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th class="nobg">Meta Key</th>
					<th>Enabled</th>
					<th>Label override</th>
					<th>Instances</th>
					<th>Type</th>
					<th>Autocomplete</th>
				</tr>
				</tfoot>
				<tbody>
				<?php

				if (!isset($options["metafields"])) {
					$options["metafields"] = array(); ?>
					<tr>
						<td colspan=4>No eligible meta fields found</td>
					</tr>
					<?php
				}

				foreach ($options["metafields"] as $metafield => $value) {

					// If the taxonomy is active, set the 'checked' class
					if (!empty($value["enabled"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$options["metafields"][ $metafield ]["enabled"] = 0;
					}

					if (empty($value["type"])) {
						$options["metafields"][ $metafield ]["type"] = "string";
					}

					if (empty($value["autocomplete"])) {
						$options["metafields"][ $metafield ]["autocomplete"] = 0;
					}

					?>
					<tr>
						<th scope="row" class="tax"><span id="<?php echo $metafield . '-title' ?>" class="<?php echo $checked ?>"><?php echo $metafield ?>:<div class="VS-icon-cancel"></div></span>
						</th>
						<td>
							<input class="checkbox" type="checkbox" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][enabled]" value="1" <?php echo checked($options["metafields"][ $metafield ]["enabled"], 1, FALSE) ?> />
						</td>
						<td>
							<input class="" type="text" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][label]" size="20" placeholder="<?php echo $metafield ?>" value="<?php echo esc_attr($options["metafields"][ $metafield ]["label"]) ?>" />
						</td>
						<td><?php echo @$value["count"] ?></td>
						<td>
							<div class="bfh-selectbox " id="<?php echo $metafield ?>" data-name="wpus_options[metafields][<?php echo $metafield ?>][type]" data-value="<?php echo $options["metafields"][ $metafield ]["type"] ?>">
								<div data-value="string" <?php echo selected($options["metafields"][ $metafield ]["type"], "string", FALSE) ?> >String</div>
								<div data-value="checkbox" <?php echo selected($options["metafields"][ $metafield ]["type"], "checkbox", FALSE) ?> >Checkbox</div>
								<div data-value="combobox" <?php echo selected($options["metafields"][ $metafield ]["type"], "combobox", FALSE) ?> >Combobox</div>
								<div data-value="true-false" <?php echo selected($options["metafields"][ $metafield ]["type"], "true-false", FALSE) ?> >True/False</div>
								<div data-value="date" <?php echo selected($options["metafields"][ $metafield ]["date"], "date", FALSE) ?> >Date</div>
								<div data-value="geo" <?php echo selected($options["metafields"][ $metafield ]["type"], "geo", FALSE) ?> >ACF Map</div>
								<div data-value="radius" <?php echo selected($options["metafields"][ $metafield ]["type"], "radius", FALSE) ?> >Radius</div>
							</div>
						</td>
						<td>
							<input class="checkbox" type="checkbox" name="wpus_options[metafields][<?php echo $metafield ?>][autocomplete]" value="1" <?php echo checked($options["metafields"][ $metafield ]["autocomplete"], 1, FALSE) ?> />
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>

			<?php
		}

		public function display_type_section($slug, $settings) {
			?>

			<table class="widefat table table-striped">
				<thead>
				<tr>
					<th class="nobg">Post Type
						<div class="tooltip" title="Post type, as it's registered with Wordpress."></div>
					</th>
					<th>Allow in results
						<div class="tooltip" title="Whether or not to include posts of this type in search results."></div>
					</th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th class="nobg">Post Type</th>
					<th>Enabled</th>
				</tr>
				</tfoot>
				<tbody>
				<?php $options = $this->options; ?>

				<?php foreach ($options["posttypes"] as $posttype => $value) {

					if (!empty($value["enabled"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$options["posttypes"][ $posttype ]["enabled"] = 0;
					}
					?>
					<tr>
						<th scope="row" class="tax"><span id="<?php echo $posttype . '-title' ?>" class="<?php echo $checked ?>"><?php echo $posttype ?>
								<div class="VS-icon-cancel"></div></span>
							<input class="" type="hidden" id="<?php echo $posttype ?>" name="wpus_options[posttypes][<?php echo $posttype ?>][label]" value="<?php echo esc_attr($options["posttypes"][ $posttype ]["label"]) ?>" />
						</th>
						<td>
							<input class="checkbox" type="checkbox" id="<?php echo $posttype ?>" name="wpus_options[posttypes][<?php echo $posttype ?>][enabled]" value="1" <?php echo checked($options["posttypes"][ $posttype ]["enabled"], 1, FALSE) ?> />
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			<?php
		}

		public function initialize() {
			$this->create_settings();
			parent::__construct($this->setup, $this->settings, $this->sections);
		}
	}
}
if (class_exists('WPUltimateSearchOptions')) {
	$WPUS_options = new WPUltimateSearchOptions();
}
