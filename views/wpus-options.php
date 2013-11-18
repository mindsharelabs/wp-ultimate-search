<?php
/**
 * WPUltimateSearchOptions
 *
 */

define( 'WPUS_STORE_URL', 'http://mindsharelabs.com' );

if(!class_exists('WPUltimateSearchOptions')) :
	class WPUltimateSearchOptions extends WPUltimateSearch {

		private $sections, $checkboxes, $settings;
		public $options, $is_active, $updater;

		function __construct() {

			add_action('admin_menu', array($this, 'register_menus'));
			add_action('admin_init', array($this, 'register_settings'));

			// Create EDDRI instance
			if( !class_exists( 'WPUS_Remote_Install_Client' ) ) {
				include( WPUS_DIR_PATH . '/lib/edd-remote-install-client/EDD_Remote_Install_Client.php' );
			}

			$options = array( 'skipplugincheck'	=> true );
			$edd_remote_install = new WPUS_Remote_Install_Client( WPUS_STORE_URL, 'settings_page_wpus-options', $options );

			add_action( 'eddri-install-complete-settings_page_wpus-options', array($this, 'activate_upgrade') );

			// This will keep track of the checkbox options for the validate_settings function.
			$this->checkboxes = array();
			$this->setting = array();
			$this->get_settings();

				if(!$this->options = get_option('wpus_options')) {
					$this->initialize_settings();
				} else {
					// Only necessary to run these operations when the actual WPUS options page is loaded
					if(isset($_GET['page']) && $_GET['page'] == 'wpus-options') {
						// Check if there are any new meta / taxonomy fields. Set them up w/ default values if necessary
						add_action('admin_init', array($this, 'update_meta_fields'));
						add_action('admin_init', array($this, 'update_taxonomies'));
						add_action('admin_init', array($this, 'update_post_types'));
					}
				}

			if(isset($this->options['license_status']) && $this->options["license_key"] != "")
				$this->is_active = $this->options['license_status'];
			
			$this->sections['general'] = __('General Settings');
			// Only show taxonomy / metafield options for registered users (no more teasing)
			if($this->is_active === "active") {
				$this->sections['taxopts'] = __('Taxonomy Settings');
				$this->sections['metaopts'] = __('Post Meta Settings');
				$this->sections['typeopts'] = __('Post Type Settings');
			}
			$this->sections['reset'] = __('Reset to Defaults');
			$this->sections['about'] = __('About');
		}

		public function activate_upgrade($args) {

			if($args['slug'] == "wp-ultimate-search-pro") {

				$options = get_option('wpus_options');
				$options['license_key'] = $args['license'];
				$options['license_status'] = 'active';

				update_option('wpus_options', $options);

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
		public function update_meta_fields($count = 1) {
			global $wpdb;
			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC
		";

			$allkeys = $wpdb->get_results($querystring);

			// set default values for all meta keys without stored settings

			foreach($allkeys as $i => $key) {
				if($key->{'count'} > $count && !isset($this->options["metafields"][$key->{"meta_key"}])) {
					$this->options["metafields"][$key->{"meta_key"}] = array(
						"enabled"      => 0,
						"label"        => $key->{"meta_key"},
						"count"        => $key->{"count"},
						"type"         => "string",
						"autocomplete" => 1
					);
				}
				// count the instances of each key, overwrite whatever it was before
				if($key->{'count'} > $count) {
					$this->options["metafields"][$key->{"meta_key"}]["count"] = $key->{'count'};
				}
			}
		}

		/**
		 *  Set default taxonomy parameters
		 *
		 */
		public function update_taxonomies() {

			$taxonomies = get_taxonomies(array('public' => TRUE));
			foreach($taxonomies as $taxonomy) {
				if(!isset($this->options['taxonomies'][$taxonomy])) {
					if($taxonomy == 'post_tag') {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 1,
							"label"   => 'tag',
							"max"     => 0,
							"exclude" => '',
							"autocomplete" => 1
						);
					} elseif($taxonomy == 'category') {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 1,
							"label"   => $taxonomy,
							"max"     => 0,
							"exclude" => '',
							"autocomplete" => 1
						);
					} else {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 0,
							"label"   => $taxonomy,
							"max"     => 0,
							"exclude" => '',
							"autocomplete" => 1
						);
					}
				}
			}
		}

		public function update_post_types() {

			$posttypes = get_post_types(array('public' => TRUE));

			foreach($posttypes as $type) {
				if(!isset($this->options['posttypes'][$type])) {
					$this->options['posttypes'][$type] = array(
						"label"		=> $type,
						"enabled"	=> 1
					);
				}

			}

		}

		/**
		 * Add menu pages
		 *
		 */
		public function register_menus() {
			$admin_page = add_options_page('Ultimate Search', 'Ultimate Search', 'manage_options', 'wpus-options', array($this, 'display_page'));
			add_action('admin_print_scripts-'.$admin_page, array($this, 'scripts'));
		}

		/**
		 *
		 * Create settings field
		 *
		 *
		 * For settings fields to be registered with add_settings_field
		 *
		 * @param array $args
		 */
		public function create_setting($args = array()) {

			$defaults = array(
				'id'      => 'wpus_default',
				'title'   => '',
				'desc'    => 'This is a default description.',
				'std'     => '',
				'type'    => 'text',
				'section' => 'general',
				'choices' => array(),
				'class'   => ''
			);

			extract(wp_parse_args($args, $defaults));

			/** @noinspection PhpUndefinedVariableInspection */
			$field_args = array(
				'type'      => $type,
				'id'        => $id,
				'desc'      => $desc,
				'std'       => $std,
				'choices'   => $choices,
				'label_for' => $id,
				'class'     => $class
			);

			if($type == 'checkbox') {
				$this->checkboxes[] = $id;
			}

			/** @noinspection PhpUndefinedVariableInspection */
			add_settings_field($id, $title, array($this, 'display_setting'), 'wpus-options', $section, $field_args);
		}

		/**
		 *
		 * Page wrappers and layout handlers
		 *
		 *
		 */
		public function display_page() {
			?>
			<div class="wrap">
			<div class="icon32" id="icon-options-general"></div>
			<h2><?php echo __('WP Ultimate Search Options') ?> </h2>

			<?php if($this->is_active !== "active" || $this->is_active === "active" && !file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE) ) { ?>
				<div class="postbox-container">
					<div id="submitdiv" class="postbox">
						<h3>WP Ultimate Search Pro</h3>

						<div class="inside">
							<p>The free version of <strong>WP Ultimate Search</strong> contains all of the power of the pro version, but supports faceting only by "tag" and "category".</p>
							<p>Upgrading to <strong>WP Ultimate Search Pro</strong> adds support for faceting by custom taxonmies, like:</p>

							<ul>
							<?php $taxonomies = get_taxonomies(array('public' => TRUE), 'objects');
							foreach($taxonomies as $taxonomy) {
								if($taxonomy->name != "post_tag" && $taxonomy->name != "post_format" && $taxonomy->name != "category") { ?>
									<li><strong><?php echo $taxonomy->name ?></strong></li>
								<?php } ?>
							<?php } ?>
							</ul>

							<p>Also supports post meta data (including data from Advanced Custom Fields), and provides additional settings for how these facets are displayed.</p>
							<p><strong>Only $25 for an unlimited license</strong>.</p>
							<a class="button-primary" target="_blank" href="https://mindsharelabs.com/downloads/wp-ultimate-search-pro/?utm_source=wpus_basic&utm_medium=upgradebutton&utm_campaign=upgrade">Learn More</a>
							<p>After purchasing, click below to install:</p>
							<a class="edd-remote-install" data-download="WP Ultimate Search Pro">Install Upgrade</a>
						</div>
					</div>
				</div>
			<?php } ?>

			<form id="wpus-options" action="options.php" method="post">
				<?php settings_fields('wpus_options'); ?>
				<div class="ui-tabs">
					<ul class="wpus-options ui-tabs-nav">
						<?php foreach($this->sections as $section_slug => $section) { ?>
							<li><a href="#<?php echo $section_slug ?>"><?php echo $section ?></a></li>
						<?php } ?>
					</ul>
					<?php do_settings_sections($_GET['page']); ?>
				</div>
				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes" /></p>
			</form>

		<?php
		}

		/**
		 *
		 * First pane, general options
		 *
		 *
		 */
		public function display_section() {
			// code
		}

		/**
		 *
		 * Taxonomy options section
		 *
		 *
		 */
		public function display_taxopts_section() { ?>

			<table class="widefat <?php if($this->is_active !== "active") : echo 'disabled'; endif; ?>">
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
						<div class="tooltip" title="Comma-separated list of term names to exclude from autocomplete. If the term contains spaces, wrap it in quotation marks."></div>
					</th>
					<th>Include
						<div class="tooltip" title="Comma-separated list of term names to include in autocomplete, all other terms will be excluded. If the term contains spaces, wrap it in quotation marks."></div>
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
				$altclass = '';

				$taxonomies = get_taxonomies(array('public' => TRUE), 'objects');
				foreach($taxonomies as $taxonomy) {
					$tax = $taxonomy->name;

					// If the taxonomy is active, set the 'checked' class
					if(!empty($this->options['taxonomies'][$tax]['enabled'])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$this->options['taxonomies'][$tax]['enabled'] = 0;
					}

					if(empty($this->options["taxonomies"][$tax]["autocomplete"])) {
						$this->options["taxonomies"][$tax]["autocomplete"] = 0;
					}

					// Generate the list of terms for the "Count" tooltip
					$terms = get_terms($tax);
					$termcount = count($terms);
					$termstring = '';
					foreach($terms as $term) {
						$termstring .= $term->name.', ';
					}
					$disabledtext = "";
					if($this->is_active !== "active") {
						$disabledtext = 'disabled="disabled"';
					}
					?>
					<tr>
						<th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $tax.'-title' ?>" class="<?php echo $checked ?>"><?php echo $taxonomy->label ?>:<div class="VS-icon-cancel"></div></span>
						</th>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" <?php echo $disabledtext ?> type="checkbox" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][enabled]" value="1" <?php echo checked($this->options['taxonomies'][$tax]['enabled'], 1, FALSE) ?> />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][label]" size="20" placeholder="<?php echo $taxonomy->name ?>" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['label']) ?>" />
						</td>
						<td class="<?php echo $altclass ?>"><?php echo $termcount ?>
							<div class="tooltip" title="<?php echo $termstring ?>"></div>
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][max]" size="3" placeholder="0" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['max']) ?>" />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][exclude]" size="30" placeholder="" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['exclude']) ?>" />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][include]" size="30" placeholder="" value="<?php echo (isset($this->options['taxonomies'][$tax]['include']) ? esc_attr($this->options['taxonomies'][$tax]['include']) : '') ?>" />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" type="checkbox" name="wpus_options[taxonomies][<?php echo $tax ?>][autocomplete]" value="1" <?php echo checked($this->options["taxonomies"][$tax]["autocomplete"], 1, FALSE) ?> />
						</td>
					</tr>
					<?php
					// Set alternating classes on the table rows
					if($altclass == 'alt') {
						$altclass = '';
					} else {
						$altclass = 'alt';
					}?>
				<?php } ?>
				</tbody>
			</table>
		<?php
		}

		/**
		 *
		 * Meta field options section
		 *
		 *
		 */
		public function display_metaopts_section() { ?>

			<?php if($this->is_active !== "active") : return; endif; ?>

			<table class="widefat <?php if($this->is_active !== "active") : echo 'disabled'; endif; ?>">
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
				$altclass = '';

				//$counts = $this->get_meta_field_counts();

				if(!isset($this->options["metafields"])) {
					$this->options["metafields"] = array(); ?>
					<tr>
						<td colspan=4>No eligible meta fields found</td>
					</tr>
				<?php
				}

				foreach($this->options["metafields"] as $metafield => $value) {

					// If the taxonomy is active, set the 'checked' class
					if(!empty($value["enabled"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$this->options["metafields"][$metafield]["enabled"] = 0;
					}

					if(empty($value["type"])) {
						$this->options["metafields"][$metafield]["type"] = "string";
					}

					if(empty($value["autocomplete"])) {
						$this->options["metafields"][$metafield]["autocomplete"] = 0;
					}

					// Generate the list of terms for the "Count" tooltip
					/* $terms = get_terms($tax);
									$termcount = count($terms);
									$termstring = '';
									foreach ( $terms as $term ) {
										$termstring .= $term->name . ', ';
									} */
					?>
					<tr>
						<th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $metafield.'-title' ?>" class="<?php echo $checked ?>"><?php echo $metafield ?>:<div class="VS-icon-cancel"></div></span>
						</th>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" type="checkbox" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][enabled]" value="1" <?php echo checked($this->options["metafields"][$metafield]["enabled"], 1, FALSE) ?> />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" type="text" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][label]" size="20" placeholder="<?php echo $metafield ?>" value="<?php echo esc_attr($this->options["metafields"][$metafield]["label"]) ?>" />
						</td>
						<td class="<?php echo $altclass ?>"><?php echo $value["count"] ?></td>
						<td class="<?php echo $altclass ?>"><select class="" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][type]" />
							<option value="string" <?php echo selected($this->options["metafields"][$metafield]["type"], "string", FALSE) ?> >String</option>
							<option value="checkbox" <?php echo selected($this->options["metafields"][$metafield]["type"], "checkbox", FALSE) ?> >Checkbox</option>
							<option value="combobox" <?php echo selected($this->options["metafields"][$metafield]["type"], "combobox", FALSE) ?> >Combobox</option>
							<option value="geo" <?php echo selected($this->options["metafields"][$metafield]["type"], "geo", FALSE) ?> >ACF Map</option>
							<option value="radius" <?php echo selected($this->options["metafields"][$metafield]["type"], "radius", FALSE) ?> >Radius</option>
							</select>
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" type="checkbox" name="wpus_options[metafields][<?php echo $metafield ?>][autocomplete]" value="1" <?php echo checked($this->options["metafields"][$metafield]["autocomplete"], 1, FALSE) ?> />
						</td>
					</tr>
					<?php
					// Set alternating classes on the table rows
					if($altclass == 'alt') {
						$altclass = '';
					} else {
						$altclass = 'alt';
					}?>
				<?php } ?>
				</tbody>
			</table>
		<?php
		}

		/**
		 *
		 * Post type options
		 *
		 *
		 */
		public function display_typeopts_section() { ?>

			<?php if($this->is_active !== "active") : return; endif; ?>

			<table class="widefat <?php if($this->is_active !== "active") : echo 'disabled'; endif; ?>">
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
				<?php
				$altclass = '';

				foreach($this->options["posttypes"] as $posttype => $value) {

					// If the taxonomy is active, set the 'checked' class
					if(!empty($value["enabled"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$this->options["posttypes"][$posttype]["enabled"] = 0;
					}
					?>
					<tr>
						<th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $posttype.'-title' ?>" class="<?php echo $checked ?>"><?php echo $posttype ?><div class="VS-icon-cancel"></div></span>
						<input class="" type="hidden" id="<?php echo $posttype ?>" name="wpus_options[posttypes][<?php echo $posttype ?>][label]" value="<?php echo esc_attr($this->options["posttypes"][$posttype]["label"]) ?>" /></th>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" type="checkbox" id="<?php echo $posttype ?>" name="wpus_options[posttypes][<?php echo $posttype ?>][enabled]" value="1" <?php echo checked($this->options["posttypes"][$posttype]["enabled"], 1, FALSE) ?> />
						</td>
					</tr>
					<?php
					// Set alternating classes on the table rows
					if($altclass == 'alt') {
						$altclass = '';
					} else {
						$altclass = 'alt';
					}?>
				<?php } ?>
				</tbody>
			</table>
		<?php
		}

		/**
		 *
		 * About section
		 *
		 *
		 */
		public function display_about_section() {
			?>
			<p>Developed by <a href="http://mind.sh/are/?ref=wpus">Mindshare Studios, Inc</a>. </p>
			<p>If you like what we do and want to show your support, consider <a href="http://mind.sh/are/donate/">making a donation</a>.</p>
			<p>Plugin page on <a href="http://wordpress.org/extend/plugins/<?= WPUS_PLUGIN_SLUG ?>/">WordPress.org</a></p>
			<p>Mindshare <a href="https://mindsharelabs.com/support/">Support Forum</a></p>



		<?php
		}

		/**
		 *
		 * Display HTML fields for individual settings
		 *
		 *
		 * This outputs the actual HTML for the settings fields, where we can receive input and display
		 * labels and descriptions.
		 *
		 * @param array $args
		 */
		public function display_setting($args = array()) {

			extract($args);

			if(!isset($this->options[$id]) && $type != 'checkbox') {
				$this->options[$id] = $std;
			} elseif(!isset($this->options[$id])) {
				$this->options[$id] = 0;
			}

			$field_class = '';
			if($class != '') {
				$field_class = ' '.$class;
			}

			switch($type) {

				case 'heading':
					echo '</td></tr><tr valign="top"><td colspan="2"><h4>'.$desc.'</h4>';
					break;

				case 'checkbox':

					if($class == 'disabledpro' && $this->is_active === "active") {
						$disabled = ' disabled="true"';
					} else {
						$disabled = '';
					}

					echo '<input class="checkbox'.$field_class.'" type="checkbox"'.$disabled.' id="'.$id.'" name="wpus_options['.$id.']" value="1" '.checked($this->options[$id], 1, FALSE).' /> <label for="'.$id.'">'.$desc.'</label>';

					break;

				case 'select':
					echo '<select class="select'.$field_class.'" name="wpus_options['.$id.']">';

					foreach($choices as $value => $label) {
						echo '<option value="'.esc_attr($value).'"'.selected($this->options[$id], $value, FALSE).'>'.$label.'</option>';
					}

					echo '</select>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'radio':
					$i = 0;
					foreach($choices as $value => $label) {
						echo '<input class="radio'.$field_class.'" type="radio" name="wpus_options['.$id.']" id="'.$id.$i.'" value="'.esc_attr($value).'" '.checked($this->options[$id], $value, FALSE).'> <label for="'.$id.$i.'">'.$label.'</label>';
						if($i < count($this->options) - 1) {
							echo '<br />';
						}
						$i++;
					}

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'textarea':
					echo '<textarea class="'.$field_class.'" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" rows="5" cols="30">'.wp_htmledit_pre($this->options[$id]).'</textarea>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'password':
					echo '<input class="regular-text'.$field_class.'" type="password" id="'.$id.'" name="wpus_options['.$id.']" value="'.esc_attr($this->options[$id]).'" />';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'text':
				default:
					$disabledtxt = ' ';
					if($field_class == " disabled") {
						$disabledtxt = ' disabled="disabled" ';
					}

					echo '<input class="regular-text'.$field_class.'"'.$disabledtxt.'type="text" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" value="'.esc_attr($this->options[$id]).'" />';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'hidden':
				default:

					if($desc != '') {
						echo '<span class="description">'.$desc.'</span>';
					}

					echo '<input class="regular-text'.$field_class.'" type="hidden" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" value="'.esc_attr($this->options[$id]).'" />';

					break;
			}
		}

		/**
		 *
		 * Standard settings
		 *
		 *
		 * All settings in the $this->settings object wil be registered with add_settings_field. You can
		 * specify a settings section and default value.
		 *
		 */
		public function get_settings() {

			/* General Settings	 */

			if(!empty($this->options['license_key'])) {
				if($this->is_active !== "active") {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('<div id="message" class="error"><p>There was an error validating your license key. Please contact support.</p></div>'),
						'std'     => "",
						'type'    => 'text',
						'section' => 'general',
						'class'   => 'invalid'
					);
				} elseif($this->is_active === "active" && !file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE)) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('<div id="message" class="error"><p>Your license key is valid but WP Ultimate Search Pro is not installed. Click "Install Upgrade" and enter your license key to reinstall.</p></div>'),
						'std'     => "",
						'type'    => 'text',
						'section' => 'general',
						'class'   => 'invalid'
					);
				} elseif($this->is_active === "active" && !is_plugin_active('wp-ultimate-search-pro/wp-ultimate-search-pro.php')) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('<div id="message" class="error"><p>Your license key is valid but the WP Ultimate Search Pro plugin isn\'t active. Please activate it from the <a href="/wp-admin/plugins.php">Plugins page</a>.</p></div>'),
						'std'     => "",
						'type'    => 'password',
						'section' => 'general',
						'class'   => 'invalid'
					);
				} elseif($this->is_active === "active" && file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE)) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('Thanks for registering!'),
						'std'     => '',
						'type'    => 'password',
						'section' => 'general',
						'class'   => 'valid'
					);
				}
			} else {
				$this->settings['license_key'] = array(
					'title'   => __('License Key'),
					'desc'    => __('No license key registered.'),
					'std'     => "",
					'type'    => 'hidden',
					'section' => 'general'
				);
			}
			$this->settings['box_heading'] = array(
				'section' => 'general',
				'title'   => '', // not used
				'desc'    => 'Search Box',
				'type'    => 'heading'
			);
			$this->settings['show_facets'] = array(
				'title'   => __('Show facets'),
				'desc'    => __('Show available facets when the search box is first clicked.'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general'
			);
			$this->settings['enable_category'] = array(
				'title'   => __('Taxonomies'),
				'desc'    => __('Category'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general',
				'class'	  => 'disabledpro'
			);
			$this->settings['enable_tag'] = array(
				'title'   => __(''),
				'desc'    => __('Tag'),
				'std'     => 1,
				'type'    => 'checkbox',
				'section' => 'general',
				'class'	  => 'disabledpro'
			);
			$this->settings['style'] = array(
				'title'   => __('Style'),
				'desc'    => __(''),
				'choices' => array("visualsearch" => "Visual Search", "square" => "Square"),
				'std'	  => 'visualsearch',
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
			$this->settings['override_default'] = array(
				'section' => 'general',
				'title'   => __('Override default search box'),
				'desc'    => __('Select this to replace the default WordPress search for with an instance of WP Ultimate Search.'),
				'type'    => 'checkbox',
				'std'     => 0
			);
			if($this->is_active === "active" && file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE)) {
				$this->settings['radius_heading'] = array(
					'section' => 'general',
					'title'   => '', // not used
					'desc'    => 'Radius Searches',
					'type'    => 'heading'
				);
				$this->settings['radius_dist'] = array(
					'title'   => __('Radius'),
					'desc'    => __('Set the default distance for radius searches'),
					'std'	  => '60',
					'type'    => 'text',
					'section' => 'general'
				);
				$this->settings['radius_format'] = array(
					'title'   => __('Format'),
					'desc'    => __(''),
					'choices' => array("km" => "Kilometers", "mi" => "Miles", "m" => "Meters"),
					'std'	  => 'km',
					'type'    => 'select',
					'section' => 'general'
				);
				$this->settings['radius_label'] = array(
					'title'   => __('Radius Label'),
					'desc'    => __('Set the text that should be displayed as the label for the radius facet'),
					'std'	  => 'distance (km)',
					'type'    => 'text',
					'section' => 'general'
				);
			}
			$this->settings['results_heading'] = array(
				'section' => 'general',
				'title'   => '', // not used
				'desc'    => 'Search Results',
				'type'    => 'heading'
			);
			$this->settings['and_or'] = array(
				'title'   => __('Search logic'),
				'desc'    => __('Whether to use AND logic or OR logic for facets within the same taxonomy.'),
				'std'     => 'or',
				'choices' => array(
								'or'	=> 'OR',
								'and'	=> 'AND'
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
			$pages = get_pages();
			$page_select = array();
			foreach($pages as $page) {
				$page_select[$page->ID] = $page->post_title;
			}
			$this->settings['results_page'] = array(
				'title'   => __('Search results page'),
				'desc'    => __('Specify the page with the ['.WPUS_PLUGIN_SLUG.'-results] shortcode.<br />Searches conducted from widget will redirect to this page.'),
				'choices' => $page_select,
				'std'	  => array_search('Search', $page_select), 
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
				'std'     => 'btn btn-default',
				'type'    => 'text',
				'section' => 'general'
			);

			$this->settings['analytics_heading'] = array(
				'section' => 'general',
				'title'   => '', // not used
				'desc'    => 'Google Analytics',
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

			$this->settings['advanced_heading'] = array(
				'section' => 'general',
				'title'   => '', // not used
				'desc'    => 'Advanced',
				'type'    => 'heading'
			);
			$this->settings['global_scripts'] = array(
				'section' => 'general',
				'title'   => __('Global Scripts'),
				'desc'    => __('Load WPUS scripts on every page. Disabling this will speed up sites that only use the search bar in a few places.'),
				'type'    => 'checkbox',
				'std'     => 1 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);

			$this->settings['reset_theme'] = array(
				'section' => 'reset',
				'title'   => __('Reset options'),
				'type'    => 'checkbox',
				'std'     => 0,
				'class'   => 'warning', // Custom class for CSS
				'desc'    => __('Check this box and click "Save Changes" below to reset all options to their defaults.')
			);
		}

		/**
		 *
		 * Initialize default settings
		 *
		 *
		 * If no options array is found, initialize everything to their default settings
		 *
		 *
		 */
		public function initialize_settings() {

			$this->options = array();
			foreach($this->settings as $id => $setting) {
				if($setting['type'] != 'heading') {
					$this->options[$id] = $setting['std'];
				}
			}

			$this->options["license_status"] = "invalid";

			// Set default meta field parameters.
			$this->update_meta_fields();

			// Set default taxonomy parameters
			$this->update_taxonomies();

			// Set default post type parametrs
			$this->update_post_types();

			update_option('wpus_options', $this->options);
		}

		/**
		 *
		 * Register settings
		 *
		 *
		 * Set up the wpus_options object, register the different settings sections / pages, and register
		 * each of the individual settings.
		 *
		 */
		public function register_settings() {

			register_setting('wpus_options', 'wpus_options', array($this, 'validate_settings'));

			foreach($this->sections as $slug => $title) {
				if($slug == 'about') {
					add_settings_section($slug, $title, array(&$this, 'display_about_section'), 'wpus-options');
				} else {
					if($slug == 'taxopts') {
						add_settings_section($slug, $title, array(&$this, 'display_taxopts_section'), 'wpus-options');
					} else {
						if($slug == 'metaopts') {
							add_settings_section($slug, $title, array(&$this, 'display_metaopts_section'), 'wpus-options');
						} else {
							if($slug == 'typeopts') {
								add_settings_section($slug, $title, array(&$this, 'display_typeopts_section'), 'wpus-options');
							} else {
								add_settings_section($slug, $title, array(&$this, 'display_section'), 'wpus-options');
							}
						}
					}
				}
			}

			$this->get_settings();

			foreach($this->settings as $id => $setting) {
				$setting['id'] = $id;
				$this->create_setting($setting);
			}
		}

		/**
		 *
		 * Validate settings
		 *
		 *
		 * By default, _POST ignores checkboxes with no value set. We need to set this to 0 in wpus_options,
		 * so this function compares the POST data with the local $this->options array and sets the checkboxes to
		 * 0 where needed. Then merges $input with $this->options so the options *not* registered with add_settings_field
		 * still get passed through into the database.
		 *
		 * @param $input
		 *
		 * @return array|bool
		 */
		public function validate_settings($input) {

			if(!isset($input["reset_theme"]) || $input["reset_theme"] == 0) {

				foreach($this->checkboxes as $id) {
					if(!isset($input[$id]) || $input[$id] != '1') {
						$input[$id] = 0;
					} else {
						$input[$id] = 1;
					}
				}

				$input['radius'] = false;
				if(isset($input['metafields'])) {
					foreach($input['metafields'] as $field => $data) {
						if(isset($data['enabled']) && $data['enabled'] == '1' && $data['type'] == 'radius') {
							$input['radius'] = $data['label'];
						}
					}
				}
				
				$result = array_merge($this->options, $input);
				return $result;
			} else {
				return FALSE;
			}
		}

		/**
		 *
		 * Enqueue and print scripts
		 *
		 */
		public function scripts() {

			wp_enqueue_script('tiptip', WPUS_DIR_URL.'js/jquery.tipTip.minified.js', array('jquery'));
			wp_enqueue_script('main', WPUS_DIR_URL.'js/main-admin.js', array('jquery'));
			wp_localize_script('main', 'main', json_encode($this->sections));
			wp_enqueue_script('jquery-ui-tabs');

			wp_enqueue_style('wpus-admin', WPUS_DIR_URL.'css/wpus-options.css');
		}

	} // END CLASS
endif;
