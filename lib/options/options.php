<?php
/**
 * The Mindshare Options Framework is a flexible, lightweight framework for creating WordPress theme and plugin options screens.
 *
 * @version        2.1.3
 * @author         Mindshare Studios, Inc.
 * @copyright      Copyright (c) 2013
 * @link           http://www.mindsharelabs.com/documentation/
 *
 * @credits        Forked from: Admin Page Class 0.9.9 by Ohad Raz http://bainternet.info
 *                 Icons: http://www.famfamfam.com/lab/icons/silk/
 *
 * @license        GNU General Public License v3.0 - license.txt
 *                 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *                 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *                 FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE
 *                 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *                 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *                 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *                 THE SOFTWARE.
 *
 *
 *
 */
if(!class_exists('WPUS_options')) :
	class WPUS_options {

		private $option_group, $setup, $settings, $sections;

		// Optional variable to contain additional pages to register
		private $pages;

		// Will contain all of the options as stored in the database
		private $options;

		// Temporary array to contain all of the checboxes in use
		private $checkboxes;

		// Temporary array to contain all of the field types currently in use
		private $fields;

		// Path to the Mindshare Options Framework
		private $selfpath;

		// Set to true if settings have been imported
		private $settings_imported;

		// Set to true if settings have been updated
		private $settings_updated;

		// Array containing errors (if any) encountered on save
		private $errors;

		// Is set to true when options are being reset
		private $reset_options;

		// Default values for the setup variable
		private $default_project = array(
			'project_name' => 'Untitled Project',
			'project_slug' => 'untitled-project',
			'menu' => 'settings',
			'page_title' => 'Untitled Project Settings',
			'menu_title' => 'Untitled Project',
			'capability' => 'manage_options',
			'option_group' => 'untitled_project_options',
			'slug' => 'untitled-project-settings',
			'page_icon' => 'options-general',
			'icon_url' => '',
			'position' => null
		);

		private $default_page = array(
			'menu' => 'settings',
			'page_title' => 'New Page',
			'menu_title' => 'New Page',
			'capability' => 'manage_options',
			'slug' => 'new-page',
			'page_icon' => 'options-general',
			'icon_url' => '',
			'position' => null
		);

		private $default_setting = array(
			'title' => null,
			'desc' => null,
			'std' => null,
			'type' => null,
			'section' => '',
			'class' => null,            // class to be applied to the input
			'disabled' => false
		);

		/**
		 * Constructor
		 * @param array $setup Contains the universal project setup parameters
		 * @param array $settings Contains all of the settings fields and their assigned section
		 * @param array $sections Contains the various sections (pages and tabs) and their relationships
		 * @param null $pages
		 * @internal param array $subpages (optional) Contains subpages to be generated off of the main page if a top-level menus is being created
		 */
		public function __construct($setup, $settings, $sections = null, $pages = null) {

			// Merge default setup with user-specified setup parameters
			$setup = wp_parse_args($setup, $this->default_project);

			$this->selfpath = plugin_dir_url(__FILE__);

			$this->setup = $setup;
			$this->sections = $sections;
			$this->pages = $pages;
			$this->default_setting['section'] = $setup['slug'];

			// Load option group
			$this->option_group = $setup['option_group'];

			// Will initialize settings if needed, and fill all unset parameters with default values
			$this->settings = $this->initialize_settings($settings);

			// If we're exporting options, prepare and deliver the export file
			add_action('admin_post_export', array($this, 'download_export'));

			if(isset($_POST['action']) && $_POST['action'] == 'update') {
				$this->save_options();
			}

			// Prepare and create menus
			//$this->prepare_menus($setup, $sections);

			add_action('admin_menu', array($this, 'add_menus'));
		}

		/*----------------------------------------------------------------*/
		/*
		/* Functions to handle saving and validation of options
		/*
		/*----------------------------------------------------------------*/

		/**
		 * Checks nonce and saves options to database
		 *
		 * @access private
		 *
		 * @internal param data $_POST
		 */

		private function save_options() {
			if(!isset($_POST[$this->setup['project_slug'].'_nonce'])) {
				return;
			}

			$nonce = $_POST[$this->setup['project_slug'].'_nonce'];
			if(!wp_verify_nonce($nonce, $this->setup['project_slug'])) {
				die('Security check. Invalid nonce.');
			}

			// Get array of form data
			$input = $_POST[$this->option_group];

			// For each settings field, run the input through it's defined validation function
			$settings = $this->settings;

			// Be default $_POST ignores checkboxes with no value set, so we need to iterate through
			// all defined checkboxes and set their value to 0 if they haven't been set in the input
			foreach($this->checkboxes as $id) {
				if(!isset($input[$id]) || $input[$id] != '1') {
					$input[$id] = 0;
				} else {
					$input[$id] = 1;
				}
			}

			foreach($settings as $id => $setting) {

				if(isset($input[$id]) && !isset($setting['subfields']) && $input[$id] != '') {

					$input[$id] = $this->validate_options($id, $input[$id], $setting);
				} elseif(isset($setting['subfields'])) {

					foreach($input[$id] as $sub_id => $subfield) {

						if(isset($input[$id][$sub_id]) && $input[$id][$sub_id] != '') {

							$input[$id][$sub_id] = $this->validate_options($sub_id, $input[$id][$sub_id], $setting['subfields'][$sub_id]);
						}
					}
				}
			}

			if($this->reset_options) {

				$input = null;
			} else {

				// Merge the form data with the existing options, updating as necessary
				$input = wp_parse_args($input, $this->options);
			}

			if(has_filter('validate_'.$this->option_group)) {
				$input = apply_filters('validate_'.$this->option_group, $input);
			}

			// If we're not importing new settings
			if(!$this->settings_imported) {

				// Update the option in the database
				update_option($this->option_group, $input);

				// Update the options within the class
				$this->options = $input;

				if(!$this->reset_options) {
					// Let the page renderer know that the settings have been updated
					$this->settings_updated = true;
				}
			}
		}

		/**
		 * Looks for the proper validation function for a given setting and returns the validated input
		 *
		 * @access private
		 *
		 * @param string $id ID of field
		 * @param mixed $input Input
		 * @param array $setting Setting properties
		 *
		 * @return mixed $input Validated input
		 *
		 */

		private function validate_options($id, $input, $setting) {

			if(method_exists($this, 'validate_field_'.$setting['type']) && !has_filter('validate_field_'.$setting['type'].'_override')) {

				// If a validation filter has been specified for the setting type, register it with add_filters
				add_filter('validate_field_'.$setting['type'], array($this, 'validate_field_'.$setting['type']), 10, 2);
			}

			if(has_filter('validate_field_'.$id)) {

				// If there's a validation function for this particular field ID
				$input = apply_filters('validate_field_'.$id, $input, $setting);
			} elseif(has_filter('validate_field_'.$setting['type']) || has_filter('validate_field_'.$setting['type'].'_override')) {

				// If there's a validation for this field type or an override
				if(has_filter('validate_field_'.$setting['type'].'_override')) {

					$input = apply_filters('validate_field_'.$setting['type'].'_override', $input, $setting);
				} elseif(has_filter('validate_field_'.$setting['type'])) {

					$input = apply_filters('validate_field_'.$setting['type'], $input, $setting);
				}
			} else {

				// If no validator specified, use the default validator
				// @todo right now the validator just passes the input back. see what base-level validation we need
				$input = $this->validate_field_default($input, $setting);
			}

			if(is_wp_error($input)) {

				// If an input fails validation, put the error message into the errors array for display
				$this->errors[$id] = $input->get_error_message();
				$input = $input->get_error_data();
			}

			return $input;
		}

		/*----------------------------------------------------------------*/
		/*
		/* Functions to handle initialization of settings fields
		/*
		/*----------------------------------------------------------------*/

		/**
		 * Checks for new settings fields and sets them to default values
		 *
		 * @access private
		 *
		 * @param $settings array
		 *
		 * @return array $settings The settings array
		 * @return array $options The options array
		 */

		private function initialize_settings($settings) {

			$options = get_option($this->option_group);
			$needs_update = false;

			foreach($settings as $id => $setting) {

				if($setting['type'] == 'checkbox') {
					$this->checkboxes[] = $id;
				}

				// Set default values from global setting default template
				$settings[$id] = wp_parse_args($setting, $this->default_setting);

				// If a custom setting template has been specified, load those values as well
				if(method_exists($this, 'default_field_'.$setting['type'])) {
					$settings[$id] = wp_parse_args($settings[$id], call_user_func(array($this, 'default_field_'.$setting['type'])));
				}

				// Load the array of settings currently in use
				if(!isset($this->fields[$setting['type']])) {
					$this->fields[$setting['type']] = true;
				}

				// Set the default value if no option exists
				if(!isset($options[$id]) && isset($settings[$id]['std'])) {
					$needs_update = true;
					$options[$id] = $settings[$id]['std'];
				}

				// Set defaults for subfields if any subfields are present
				if(isset($setting['subfields'])) {
					foreach($setting['subfields'] as $sub_id => $sub_setting) {

						// Fill in missing parts of the array
						$settings[$id]['subfields'][$sub_id] = wp_parse_args($sub_setting, $this->default_setting);

						if(method_exists($this, 'default_field_'.$sub_setting['type'])) {
							$settings[$id]['subfields'][$sub_id] = wp_parse_args($settings[$id]['subfields'][$sub_id], call_user_func(array($this, 'default_field_'.$sub_setting['type'])));
						}

						// Set default value if needed
						if(!isset($options[$id][$sub_id])) {
							$options[$id][$sub_id] = $setting['subfields'][$sub_id]['std'];
						}
					}
				}
			}

			$this->options = $options;

			// If new options have been added, set their default values
			if($needs_update) {
				update_option($this->option_group, $options);
			}

			return ($settings);
		}

		/*----------------------------------------------------------------*/
		/*
		/* Functions to handle creating menu items and registering pages
		/*
		/*----------------------------------------------------------------*/

		/**
		 * Sets the top level menu slug based on the user preference
		 *
		 * @access private
		 *
		 * @param $menu
		 * @return string
		 * @internal param array $setup
		 * @internal param array $subpages
		 *
		 */

		private function parent_slug($menu) {

			switch($menu) {
				case 'posts':
					return 'edit.php';

				case 'dashboard':
					return 'index.php';

				case 'media':
					return 'upload.php';

				case 'links':
					return 'link-manager.php';

				case 'pages':
					return 'edit.php?post_type=page';

				case 'comments':
					return 'edit-comments.php';

				case 'theme':
					return 'themes.php';

				case 'plugins':
					return 'plugins.php';

				case 'users':
					return 'users.php';

				case 'tools':
					return 'tools.php';

				case 'settings':
					return 'options-general.php';

				default:
					if(post_type_exists($menu)) {
						return 'edit.php?post_type='.$menu;
					} else {
						return $menu;
					}
			}
		}

		/**
		 * Builds menus and submenus according to the pages and subpages specified by the user
		 *
		 * @access public
		 *
		 */

		public function add_menus() {

			// Create an array to contain all pages, and add the main setup page (registered with $setup)
			$pages = array(
				$this->setup['slug'] => array(
					'menu' => $this->setup['menu'],
					'page_title' => $this->setup['page_title'],
					'menu_title' => $this->setup['menu_title'],
					'capability' => $this->setup['capability'],
					'page_icon' => $this->setup['page_icon'],
					'icon_url' => $this->setup['icon_url'],
					'position' => $this->setup['position']
				)
			);

			// If additional pages have been specified, load them into the pages array
			if($this->pages) {
				foreach($this->pages as $slug => $page) {
					$pages[$slug] = wp_parse_args($page, $this->default_page);
				}
			}

			// For each page, register it with add_submenu_page and create an admin_print_scripts action
			foreach($pages as $slug => $page) {

				// If page does not have a menu, create a top level menu item
				if($page['menu'] == null) {

					$id = add_menu_page(
						$page['page_title'],
						$page['menu_title'],
						$page['capability'],
						$slug,
						array($this, 'show_page'),
						$page['icon_url'],
						$page['position']
					);
				} else {

					$id = add_submenu_page(
						$this->parent_slug($page['menu']),    // parent slug
						$page['page_title'],                // page title
						$page['menu_title'],                // menu title
						$page['capability'],                // capability
						$slug,                                // slug
						array($this, 'show_page')            // display function
					);
				}

				add_action('admin_print_scripts-'.$id, array($this, 'scripts'));

				// Add the ID back into the array so we can locate this page again later
				$pages[$slug]['id'] = $id;
			}

			// Make the reorganized array available to the rest of the class
			$this->pages = $pages;
		}

		/*----------------------------------------------------------------*/
		/*
		/* Functions to handle rendering page wrappers and outputting settings fields
		/*
		/*----------------------------------------------------------------*/

		/**
		 * Enqueue scripts and styles
		 */
		public function scripts() {

			wp_enqueue_script('bootstrap', $this->selfpath.'js/bootstrap.min.js', array('jquery'));
			wp_enqueue_script('bootstrap-formhelpers', $this->selfpath.'lib/bootstrap-formhelpers/bootstrap-formhelpers.min.js', array('jquery', 'bootstrap'));
			wp_enqueue_script('options-js', $this->selfpath.'js/options.min.js', array('jquery'));

			wp_enqueue_script('jquery-ui-sortable');

			wp_enqueue_style('bootstrap', $this->selfpath.'css/bootstrap.min.css');
			wp_enqueue_style('fontawesome', $this->selfpath.'css/font-awesome.min.css');
			wp_enqueue_style('options-css', $this->selfpath.'css/options.css');

			// Enqueue TinyMCE editor
			if(isset($this->fields['editor'])) {
				wp_print_scripts('editor');
			}

			// Enqueue codemirror js and css
			if(isset($this->fields['code'])) {
				wp_enqueue_style('at-code-css', $this->selfpath.'/lib/codemirror/codemirror.css', array(), NULL);
				wp_enqueue_style('at-code-css-dark', $this->selfpath.'/lib/codemirror/twilight.css', array(), NULL);
				wp_enqueue_script('at-code-lib', $this->selfpath.'/lib/codemirror/codemirror.js', array('jquery'), FALSE, TRUE);
				wp_enqueue_script('at-code-lib-xml', $this->selfpath.'/lib/codemirror/xml.js', array('jquery'), FALSE, TRUE);
				wp_enqueue_script('at-code-lib-javascript', $this->selfpath.'/lib/codemirror/javascript.js', array('jquery'), FALSE, TRUE);
				wp_enqueue_script('at-code-lib-css', $this->selfpath.'/lib/codemirror/css.js', array('jquery'), FALSE, TRUE);
				wp_enqueue_script('at-code-lib-clike', $this->selfpath.'/lib/codemirror/clike.js', array('jquery'), FALSE, TRUE);
				wp_enqueue_script('at-code-lib-php', $this->selfpath.'/lib/codemirror/php.js', array('jquery'), FALSE, TRUE);
			}

			if(isset($this->fields['file'])) {
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				wp_enqueue_script('holder', $this->selfpath.'js/holder.min.js');

				wp_enqueue_style('thickbox');
			}

			// Enqueue plupload
			if(isset($this->fields['plupload'])) {
				wp_enqueue_script('plupload-all');
				wp_register_script('myplupload', $this->selfpath.'/lib/plupload/myplupload.js', array('jquery'));
				wp_enqueue_script('myplupload');
				wp_register_style('myplupload', $this->selfpath.'/lib/plupload/myplupload.css');
				wp_enqueue_style('myplupload');

				// Add data encoding type for file uploading.
				add_action('post_edit_form_tag', array($this, 'add_enctype'));

				// Make upload feature work event when custom post type doesn't support 'editor'
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('jquery-ui-sortable');

				// Add filters for media upload.
				add_filter('media_upload_gallery', array(&$this, 'insert_images'));
				add_filter('media_upload_library', array(&$this, 'insert_images'));
				add_filter('media_upload_image', array(&$this, 'insert_images'));
				// Delete all attachments when delete custom post type.
				add_action('wp_ajax_at_delete_file', array(&$this, 'delete_file'));

				// Delete file via Ajax
				add_action('wp_ajax_at_delete_mupload', array($this, 'wp_ajax_delete_image'));
			}
		}

		/**
		 * Gets the current page settings based on the screen object given by get_current_screen()
		 *
		 * @access private
		 *
		 * @param $screen object
		 *
		 * @return bool
		 */

		private function get_page_by_screen($screen) {

			foreach($this->pages as $slug => $page) {

				if($page['id'] == $screen->id) {

					if(isset($this->sections[$slug])) {

						// If sections have been given for this specific page
						$page['sections'] = $this->sections[$slug];
					} else {

						// If there are sections, but none for this specific page, create one section w/ the page's slug
						$page['sections'][$slug] = $slug;
					}

					$page['slug'] = $slug;

					return $page;
				}
			}
			return FALSE;
		}

		/**
		 *
		 * Page wrappers and layout handlers
		 *
		 *
		 */

		public function show_page() { ?>
			<?php $page = $this->get_page_by_screen(get_current_screen()); ?>
			<div class="wrap">
			<div class="icon32" id="icon-<?php echo $this->setup['page_icon'] ?>"></div>
			<h2><?php echo $page["page_title"] ?> </h2>

			<?php if($this->settings_updated) {
				echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Settings saved. <a href="">Reload the page.</a></strong></p></div>';
			}

			if($this->settings_imported) {
				echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Settings successfully imported. <a href="">Reload the page.</a></strong></p></div>';
			}

			if($this->reset_options) {
				echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Settings successfully reset. <a href="">Reload the page.</a></strong></p></div>';
			}

			if($this->errors) {
				foreach($this->errors as $id => $error_message) {
					echo '<div id="message" class="error"><p>'.$error_message.'</p></div>';
					echo '<style type="text/css">#'.$id.'{ border: 1px solid #d00; }</style>';
				}
			} ?>

			<form id="<?php echo $page['slug']; ?>" action="" method="post">
				<?php wp_nonce_field($this->setup['project_slug'], $this->setup['project_slug'].'_nonce'); ?>
				<input type="hidden" name="action" value="update">

				<?php if(has_action('before_page_'.$page['id'])) {
					do_action('before_page_'.$page['id']);
				}


				// only display tabs if there's more than one section
				if(count($page['sections']) > 1) { ?>

					<ul class="nav nav-tabs">
						<?php $isfirst = true; ?>
						<?php foreach($page['sections'] as $section_slug => $section) { ?>

							<li <?php if($isfirst) {
								echo "class='active'";
							} ?>><a href="#<?php echo $section_slug ?>" data-toggle="tab"><?php echo $section ?></a></li>

							<?php $isfirst = false; ?>

						<?php } ?>
					</ul>

				<?php } ?>
				<div class="tab-content">
					<?php $isfirst = true; ?>

					<?php foreach($page['sections'] as $section_slug => $section) { ?>

						<div class="tab-pane <?php if($isfirst) {
							echo 'active';
						} ?>" id="<?php echo $section_slug ?>">
							<?php if(count($page['sections']) > 1) { ?>
								<h3><?php echo $section ?></h3>
							<?php } ?>

							<?php // Check to see if a user-created override for the display function is available
							if(has_action('show_section_'.$section_slug)) {

								do_action('show_section_'.$section_slug, $section_slug, $this->settings);
							} else {

								$this->show_section($section_slug);
							} ?>
						</div>
						<?php $isfirst = false; ?>
					<?php } ?>
				</div>
				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes" /></p>
			</form>

		<?php
		}

		/**
		 * Renders the individual settings fields within their appropriate sections
		 *
		 * @access private
		 *
		 * @param $section string
		 *
		 */

		private function show_section($section) { ?>
			<?php $settings = $this->settings; ?>
			<table class="form-table">
				<?php foreach($settings as $id => $setting) {
				if($setting["section"] == $section) {

					// For each part of the field (begin, content, and end) check to see if a user-specified override is available in the child class

					/**
					 * "field_begin" override
					 */

					if(has_action('show_field_'.$setting['type']."_begin")) {

						// If there's a "field begin" override for this specific field
						do_action('show_field_'.$setting['type'].'_begin', $id, $setting);
					} elseif(has_action('show_field_begin')) {

						// If there's a "field begin" override for all fields
						do_action('show_field_begin', $id, $setting);
					} elseif(method_exists($this, 'show_field_'.$setting['type']."_begin")) {

						// If a custom override has been supplied in this file
						call_user_func(array($this, "show_field_".$setting['type']."_begin"), $id, $setting);
					} else {

						// If no override, use the default
						$this->show_field_begin($id, $setting);
					}

					/**
					 * "show_field" override
					 */

					if(has_action('show_field_'.$id)) {

						do_action('show_field_'.$id, $id, $setting);
					} elseif(has_action('show_field_'.$setting['type'])) {

						do_action('show_field_'.$setting['type'], $id, $setting);
					} else {
						// If no custom override, use the default
						call_user_func(array($this, "show_field_".$setting['type']), $id, $setting);
					}

					/**
					 * "field_end" override
					 */

					if(has_action('show_field_'.$setting['type']."_end")) {

						// If there's a "field begin" override for this specific field
						do_action('show_field_'.$setting['type'].'_end', $id, $setting);
					} elseif(has_action('show_field_end')) {

						// If there's a "field begin" override for all fields
						do_action('show_field_end', $id, $setting);
					} elseif(method_exists($this, 'show_field_'.$setting['type']."_end")) {

						// If a custom override has been supplied in this file
						call_user_func(array($this, "show_field_".$setting['type']."_end"), $id, $setting);
					} else {

						// If no override, use the default
						$this->show_field_end($id, $setting);
					}
				}
			} ?>
			</table>
		<?php }

		/*----------------------------------------------------------------
		 *
		 * Functions to handle display and validation of individual fields
		 * 
		 *----------------------------------------------------------------
		
		/**
		 *
		 * Default field handlers
		 * 
		 */

		/**
		 * Begin field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 * @since  0.1
		 * @access private
		 */
		private function show_field_begin($id, $field) {
			echo '<tr valign="top">';
			echo '<th scope="row"><label for="'.$id.'">'.$field['title'].'</label></th>';
			echo '<td>';
		}

		/**
		 * End field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 *
		 * @since  0.1
		 * @access private
		 */
		private function show_field_end($id, $field) {

			if($field['desc'] != '') {
				echo '<span class="description">'.$field['desc'].'</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		/**
		 * Validate field
		 *
		 * @param mixed $input
		 *
		 * @param $setting
		 * @return mixed
		 */
		private function validate_field_default($input, $setting) {

			return $input;
		}

		/**
		 *
		 * Wrapper for fields with subfields
		 *
		 */

		/**
		 * Show subfields field begin
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_subfields_begin($id, $field) {
			echo '<tr valign="top">';
			echo '<th scope="row"><label for="'.$id.'">'.$field['title'].'</label></th>';
			echo '<td class="subfields">';
		}

		/**
		 * Show subfields field
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_subfields($id, $field) {

			foreach($field['subfields'] as $subfield_id => $subfield) {

				if(has_action('show_field_'.$subfield['type'])) {

					do_action('show_field_'.$subfield['type'], $id, $subfield);
				} else {
					// If no custom override, use the default
					call_user_func(array($this, "show_field_".$subfield['type']), $id, $subfield, $subfield_id);
				}
			}
		}

		/**
		 *
		 * Heading field
		 *
		 */

		/**
		 * Show Heading field begin
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_heading_begin($id, $field) {

			echo '</table>';
		}

		/**
		 * Show Heading field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_heading($id, $field) {

			echo '<h4>'.$field['title'].'</h4>';

			if($field['desc'] != '') {

				echo '<p>'.$field['desc'].'</p>';
			}
		}

		/**
		 * Show Heading field end.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_heading_end($id, $field) {

			echo '<table class="form-table">';
		}

		/**
		 *
		 * Paragraph field
		 *
		 */

		/**
		 * Show Paragraph field begin
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_paragraph_begin($id, $field) {
			echo '<tr valign="top"><td></td><td>';
		}

		/**
		 * Show Paragraph field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_paragraph($id, $field) {
			if($field['title']) {
				echo '<p><strong>'.$field['title'].'</strong></p>';
			}
			echo '<p>'.$field['desc'].'</p>';
		}

		/**
		 * Show Paragraph field end.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_paragraph_end($id, $field) {

			echo '</td>';
			echo '</tr>';
		}



		/**
		 *
		 * Text field
		 *
		 */

		/**
		 * Defaults for text field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_text() {

			$args = array(
				'format' => null
			);

			return $args;
		}

		/**
		 * Show field Text.
		 *
		 * @param $id
		 * @param string $field
		 *
		 * @param null $subfield_id
		 * @since  0.1
		 * @access private
		 */
		private function show_field_text($id, $field, $subfield_id = null) {

			if($field['format'] == 'phone') {

				echo '<input id="'.($subfield_id ? $subfield_id : $id).'" class="form-control bfh-phone" data-format="(ddd) ddd-dddd" type="text" id="'.$id.'" name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" placeholder="'.$field['std'].'" value="'.esc_attr($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'>';
			} else {

				echo '<input id="'.($subfield_id ? $subfield_id : $id).'" class="form-control" type="text" name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" placeholder="'.$field['std'].'" value="'.esc_attr($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'>';
			}
		}

		/**
		 * Validate Text field.
		 *
		 * @param string $input
		 * @param $setting
		 * @return string $input
		 *
		 */
		public function validate_field_text($input, $setting) {

			if($setting['format'] == 'phone') {
				// Remove all non-number characters
				$input = preg_replace("/[^0-9]/", '', $input);

				//if we have 10 digits left, it's probably valid.
				if(strlen($input) == 10) {

					return $input;
				} else {

					return new WP_Error('error', __("Invalid phone number."), $input);
				}
			} elseif($setting['format'] == 'zip') {

				if(preg_match('/^\d{5}$/', $input)) {

					return $input;
				} else {

					return new WP_Error('error', __("Invalid ZIP code."), $input);
				}
			} else {

				return sanitize_text_field($input);
			}
		}


		/**
		 *
		 * Textarea field
		 *
		 */

		/**
		 * Defaults for textarea field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_textarea() {

			$args = array(
				'rows' => 5,
				'cols' => 39
			);

			return $args;
		}

		/**
		 * Show Textarea field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_textarea($id, $field) {

			echo '<textarea class="form-control '.$field['class'].'" id="'.$id.'" name="'.$this->option_group.'['.$id.']" placeholder="'.$field['std'].'" rows="'.$field['rows'].'" cols="'.$field['cols'].'" '.($field['disabled'] ? 'disabled="true"' : '').'>'.wp_htmledit_pre($this->options[$id]).'</textarea>';
		}

		/**
		 * Validate Textarea field.
		 *
		 * @param string $input
		 * @param $setting
		 * @return string $input
		 *
		 */
		public function validate_field_textarea($input, $setting) {

			return esc_textarea($input);
		}


		/**
		 *
		 * Checkbox field
		 *
		 */

		/**
		 * Show Checkbox field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_checkbox($id, $field) {
			echo '<input class="checkbox '.$field['class'].'" type="checkbox" id="'.$id.'" name="'.$this->option_group.'['.$id.']" value="1" '.checked($this->options[$id], 1, FALSE).' '.($field['disabled'] ? 'disabled="true"' : '').' />';

			if($field['desc'] != '') {
				echo '<label for="'.$id.'">'.$field['desc'].'</label>';
			}
		}

		/**
		 * Checkbox end field
		 *
		 * @param string $field
		 *
		 *
		 * @access private
		 */
		private function show_field_checkbox_end($id, $field) {

			echo '</td>';
			echo '</tr>';
		}

		/**
		 *
		 * Radio field
		 *
		 */

		/**
		 * Defaults for radio field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_radio() {

			$args = array(
				'choices' => array()
			);

			return $args;
		}

		/**
		 * Show Radio field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_radio($id, $field) {

			$i = 0;

			foreach($field['choices'] as $value => $label) {

				echo '<input class="radio '.$field['class'].'" type="radio" name="'.$this->option_group.'['.$id.']" id="'.$id.$i.'" value="'.esc_attr($value).'" '.checked($this->options[$id], $value, FALSE).' '.($field['disabled'] ? 'disabled=true' : '').'><label for="'.$id.$i.'">'.$label.'</label>';

				if($i < count($field['choices']) - 1) {
					echo '<br />';
				}

				$i++;
			}
		}

		/**
		 *
		 * Select field
		 *
		 */

		/**
		 * Defaults for select field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_select() {

			$args = array(
				'choices' => array()
			);

			return $args;
		}

		/**
		 * Show Select field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_select($id, $field, $subfield_id = null) {

			echo '<div id="'.($subfield_id ? $subfield_id : $id).'" class="bfh-selectbox '.$field['class'].'" data-name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" data-value="'.($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'>';

			foreach($field['choices'] as $value => $label) {
				echo '<div data-value="'.esc_attr($value).'"'.selected($this->options[$id], $value, FALSE).'>'.$label.'</div>';
			}

			echo '</div>';
		}

		/**
		 *
		 * Number / slider / date / time fields
		 *
		 */

		/**
		 * Defaults for number field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_number() {

			$args = array(
				'min' => 0,
				'max' => null
			);

			return $args;
		}

		/**
		 * Show Number field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_number($id, $field, $subfield_id = null) {

			echo '<input id="'.($subfield_id ? $subfield_id : $id).'" type="number" class="select form-control '.$field['class'].'" name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" min="'.$field['min'].'" '.($field['max'] ? 'max='.$field['max'] : '').' value="'.($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'>';
		}

		/**
		 * Validate number field
		 *
		 * @param mixed $input
		 *
		 */
		public function validate_field_number($input, $setting) {

			if($input < $setting['min']) {

				return new WP_Error('error', __("Number must be greater than or equal to ".$setting['min']."."), $input);
			} elseif($input > $setting['max'] && $setting['max'] != null) {

				return new WP_Error('error', __("Number must be less than or equal to ".$setting['max']."."), $input);
			} else {

				return $input;
			}
		}

		/**
		 * Defaults for slider field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_slider() {

			$args = array(
				'min' => 0,
				'max' => 100
			);

			return $args;
		}

		/**
		 * Show Slider field
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_slider($id, $field, $subfield_id = null) {

			echo '<div id="'.($subfield_id ? $subfield_id : $id).'" class="bfh-slider '.$field['class'].'" data-name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" data-min="'.$field['min'].'" '.($field['max'] ? 'data-max='.$field['max'] : '').' data-value="'.($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'></div>';
		}

		/**
		 * Defaults for date field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_date() {

			$args = array(
				'date' => 'today',
				'format' => 'm/d/y',
				'min' => null,
				'max' => null
			);

			return $args;
		}

		/**
		 * Show Date field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_date($id, $field, $subfield_id = null) {

			echo '<div id="'.($subfield_id ? $subfield_id : $id).'" class="bfh-datepicker '.$field['class'].'" data-name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" data-format="'.$field['format'].'" data-date="'.($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" data-min="'.$field['min'].'" data-max="'.$field['max'].'" '.($field['disabled'] ? 'disabled="true"' : '').'></div>';
		}

		/**
		 * Defaults for time field
		 *
		 * @return array $args
		 *
		 */
		private function default_field_time() {

			$args = array(
				'time' => 'now'
			);

			return $args;
		}

		/**
		 * Show Time field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_time($id, $field, $subfield_id = null) {

			echo '<div id="'.($subfield_id ? $subfield_id : $id).'" class="bfh-timepicker '.$field['class'].'" data-name="'.$this->option_group.'['.$id.']'.($subfield_id ? '['.$subfield_id.']' : '').'" data-time="'.($subfield_id ? $this->options[$id][$subfield_id] : $this->options[$id]).'" '.($field['disabled'] ? 'disabled="true"' : '').'></div>';
		}

		/**
		 *
		 * Hidden field
		 *
		 */

		/**
		 * Hidden field begin
		 *
		 * @param string $field
		 *
		 * @access private
		 */
		private function show_field_hidden_begin($id, $field) {
		}

		/**
		 * Show Hidden field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_hidden($id, $field) {

			echo '<input type="hidden" name="'.$this->option_group.'['.$id.']" value="'.$this->options[$id].'">';
		}

		/**
		 * Hidden field end
		 *
		 * @param string $field
		 *
		 * @access private
		 */
		private function show_field_hidden_end($id, $field) {
		}

		/**
		 *
		 * Password field
		 *
		 */

		/**
		 * Show password field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_password($id, $field) {

			echo '<input class="form-control '.$field['class'].'" type="password" name="'.$this->option_group.'['.$id.']" value="'.$this->options[$id].'" '.($field['disabled'] ? 'disabled="true"' : '').'>';
		}

		/**
		 *
		 * Code editor field
		 *
		 */

		/**
		 * Defaults for code editor field
		 *
		 * @return array $args
		 *
		 */

		private function default_field_code() {
			$args = array(
				'theme' => 'default',
				'lang' => 'php'
			);
			return $args;
		}

		/**
		 * Show code editor field
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_code($id, $field) {

			echo '<textarea id="'.$id.'" class="code_text '.$field['class'].'" name="'.$this->option_group.'['.$id.']" data-lang="'.$field['lang'].'" data-theme="'.$field['theme'].'">'.stripslashes($this->options[$id]).'</textarea>';
		}




		/**
		 *
		 * Location fields
		 *
		 */

		/**
		 *
		 * File upload field and utility functions
		 *
		 */

		/**
		 * Add data encoding type for file uploading
		 *
		 * @since  0.1
		 * @access public
		 */
		public function add_enctype() {
			echo ' enctype="multipart/form-data"';
		}




		/**
		 *
		 * WYSIWYG editor field
		 *
		 */

		/**
		 * Prepare and serve export settings
		 *
		 * @return string $content
		 *
		 */

		public function download_export() {

			if($this->option_group == $_REQUEST['option_group']) {

				if(!wp_verify_nonce($_REQUEST['_wpnonce'], 'export-options')) {
					wp_die('Security check');
				}
				//here you get the options to export and set it as content, ex:
				$content = base64_encode(serialize($this->options));
				$file_name = 'exported_settings_'.date('m-d-y').'.txt';
				header('HTTP/1.1 200 OK');
				if(!current_user_can('edit_theme_options')) {
					wp_die('<p>'.__('You do not have sufficient permissions to edit templates for this site.').'</p>');
				}
				if($content === NULL || $file_name === NULL) {
					wp_die('<p>'.__('Error Downloading file.').'</p>');
				}
				$fsize = strlen($content);
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header('Content-Description: File Transfer');
				header("Content-Disposition: attachment; filename=".$file_name);
				header("Content-Length: ".$fsize);
				header("Expires: 0");
				header("Pragma: public");
				echo $content;
				exit;
			}
		}

		/**
		 * Validate import field.
		 *
		 * @param string $input
		 * @param $setting
		 * @return string $input
		 *
		 */

		public function validate_field_import($input, $setting) {

			$import_code = unserialize(base64_decode($input));

			if(is_array($import_code)) {

				update_option($this->option_group, $import_code);
				$this->options = $import_code;
				$this->settings_imported = true;

				return true;
			} else {

				return new WP_Error('error', __("Error importing settings. Check your import file and try again."));
			}
		}

		/**
		 *
		 * Reset options field
		 *
		 */

		/**
		 * Show Reset field.
		 *
		 * @param string $id
		 * @param array $field
		 *
		 */
		private function show_field_reset($id, $field) {
			echo '<input class="checkbox warning '.$field['class'].'" type="checkbox" id="'.$id.'" name="'.$this->option_group.'['.$id.']" value="1" '.checked(@$this->options[$id], 1, FALSE).' />';

			if($field['desc'] != '') {
				echo '<label for="'.$id.'">'.$field['desc'].'</label>';
			}
		}

		/**
		 * Reset field end
		 *
		 * @param string $id
		 * @param array $field
		 *
		 * @access private
		 */
		private function show_field_reset_end($id, $field) {

			echo '</td>';
			echo '</tr>';
		}

		/**
		 * Validates input field
		 *
		 * @param  bool $input
		 * @return bool $input
		 *
		 */
		public function validate_field_reset($input, $setting) {

			if(isset($input)) {
				$this->reset_options = true;
			}

			return $input;
		}


		/**
		 *
		 * DEPRECATED functions from previous version, still to be integrated
		 *
		 */



		/**
		 * Show field Subtitle.
		 *
		 * @param string $field
		 *
		 * @since  0.1
		 * @access public
		 */
		public function show_field_subtitle($field) {
			echo '<h3>'.$field['value'].'</h3>';
		}



		/**
		 * Show Checkbox List field
		 *
		 * @param string $field
		 * @param string $meta
		 *
		 * @since  0.1
		 * @access public
		 */
		public function show_field_checkbox_list($field, $meta) {
			if(!is_array($meta)) {
				$meta = (array) $meta;
			}
			$this->show_field_begin($field, $meta);
			$html = array();
			foreach($field['options'] as $key => $value) {
				$html[] = "<input type='checkbox' class='at-checkbox_list' name='{$field['id']}[]' value='{$key}'".checked(in_array($key, $meta), TRUE, FALSE)." /> {$value}";
			}
			echo implode('<br />', $html);
			$this->show_field_end($field, $meta);
		}

		/**
		 * Show Posts field.
		 * used creating a posts/pages/custom types checkboxlist or a select dropdown
		 *
		 * @param string $field
		 * @param string $meta
		 *
		 * @since  0.1
		 * @access public
		 */
		public function show_field_posts($field, $meta) {

			if(!is_array($meta)) {
				$meta = (array) $meta;
			}
			$this->show_field_begin($field, $meta);
			$options = $field['options'];
			$posts = get_posts($options['args']);
			// checkbox_list
			if('checkbox_list' == $options['type']) {
				foreach($posts as $p) {
					echo "<input type='checkbox' name='{$field['id']}[]' value='$p->ID'".checked(in_array($p->ID, $meta), TRUE, FALSE)." /> $p->post_title<br />";
				}
			} // select
			else {
				echo "<select name='{$field['id']}".($field['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'").">";
				foreach($posts as $p) {
					echo "<option value='$p->ID'".selected(in_array($p->ID, $meta), TRUE, FALSE).">$p->post_title</option>";
				}
				echo "</select>";
			}
			$this->show_field_end($field, $meta);
		}

		/**
		 * Show Taxonomy field.
		 * used creating a category/tags/custom taxonomy checkboxlist or a select dropdown
		 *
		 * @param string $field
		 * @param string $meta
		 *
		 * @since  0.1
		 * @access public
		 * @uses   get_terms()
		 */
		public function show_field_taxonomy($field, $meta) {
			if(!is_array($meta)) {
				$meta = (array) $meta;
			}
			$this->show_field_begin($field, $meta);
			$options = $field['options'];
			$terms = get_terms($options['taxonomy'], $options['args']);
			// checkbox_list
			if('checkbox_list' == $options['type']) {
				foreach($terms as $term) {
					echo "<input type='checkbox' name='{$field['id']}[]' value='$term->slug'".checked(in_array($term->slug, $meta), TRUE, FALSE)." /> $term->name  <br />";
				}
			} // select
			else {
				echo "<select name='{$field['id']}".($field['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'").">";
				foreach($terms as $term) {
					echo "<option value='$term->slug'".selected(in_array($term->slug, $meta), TRUE, FALSE).">$term->name</option>";
				}
				echo "</select>";
			}
			$this->show_field_end($field, $meta);
		}

		/**
		 * Show Role field.
		 * used creating a Wordpress roles list checkboxlist or a select dropdown
		 *
		 * @param string $field
		 * @param string $meta
		 *
		 * @since  0.1
		 * @access public
		 * @uses   global $wp_roles;
		 * @uses   checked();
		 */
		public function show_field_WProle($field, $meta) {
			if(!is_array($meta)) {
				$meta = (array) $meta;
			}
			$this->show_field_begin($field, $meta);
			$options = $field['options'];
			global $wp_roles;
			if(!isset($wp_roles)) {
				$wp_roles = new WP_Roles();
			}
			$names = $wp_roles->get_names();
			if($names) {
				// checkbox_list
				if('checkbox_list' == $options['type']) {
					foreach($names as $n) {
						echo "<input type='checkbox' name='{$field['id']}[]' value='$n'".checked(in_array($n, $meta), TRUE, FALSE)." /> $n<br />";
					}
				} // select
				else {
					echo "<select name='{$field['id']}".(@$field['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'").">";
					foreach($names as $n) {
						echo "<option value='$n'".selected(in_array($n, $meta), TRUE, FALSE).">$n</option>";
					}
					echo "</select>";
				}
			}
			$this->show_field_end($field, $meta);
		}



		/**
		 *  Add Taxonomy field to Page
		 *
		 *
		 * @since  0.1
		 * @access public
		 *
		 * @param $id       string  field id, i.e. the meta key
		 * @param $options  mixed|array options of taxonomy field
		 *    'taxonomy' =>    // taxonomy name can be category,post_tag or any custom taxonomy default is category
		 *    'type' =>  // how to show taxonomy? 'select' (default) or 'checkbox_list'
		 *    'args' =>  // arguments to query taxonomy, see http://goo.gl/uAANN default ('hide_empty' => false)
		 * @param $args     mixed|array
		 *    'name' => // field name/label string optional
		 *    'desc' => // field description, string optional
		 *    'std' => // default value, string optional
		 *    'validation_function' => // validate function, string optional
		 * @param $repeater bool  is this a field inside a repeater? true|false(default)
		 *
		 * @return array
		 */
		public function addTaxonomy($id, $options, $args, $repeater = FALSE) {
			$q = array('hide_empty' => 0);
			$tax = 'category';
			$type = 'select';
			$temp = array('taxonomy' => $tax, 'type' => $type, 'args' => $q);
			$options = array_merge($temp, $options);
			$new_field = array(
				'type' => 'taxonomy',
				'id' => $id,
				'desc' => '',
				'name' => 'Taxonomy field',
				'options' => $options
			);
			$new_field = array_merge($new_field, $args);
			if(FALSE === $repeater) {
				$this->_fields[] = $new_field;
			} else {
				return $new_field;
			}
		}

		/**
		 *  Add WP_Roles field to Page
		 *
		 *
		 * @since  0.1
		 * @access public
		 *
		 * @param $id       string  field id, i.e. the meta key
		 * @param $options  mixed|array options of taxonomy field
		 *    'type' =>  // how to show taxonomy? 'select' (default) or 'checkbox_list'
		 * @param $args     mixed|array
		 *    'name' => // field name/label string optional
		 *    'desc' => // field description, string optional
		 *    'std' => // default value, string optional
		 *    'validation_function' => // validate function, string optional
		 * @param $repeater bool  is this a field inside a repeater? true|false(default)
		 *
		 * @return array
		 */
		public function addRoles($id, $options, $args, $repeater = FALSE) {
			$type = 'select';
			$temp = array('type' => $type);
			$options = array_merge($temp, $options);
			$new_field = array(
				'type' => 'WProle',
				'id' => $id,
				'desc' => '',
				'name' => 'Select WordPress Role',
				'options' => $options
			);
			$new_field = array_merge($new_field, $args);
			if(FALSE === $repeater) {
				$this->_fields[] = $new_field;
			} else {
				return $new_field;
			}
		}

		/**
		 *  Add posts field to Page
		 *
		 *
		 * @since  0.1
		 * @access public
		 *
		 * @param $id       string  field id, i.e. the meta key
		 * @param $options  mixed|array options of taxonomy field
		 *    'post_type' =>    // post type name, 'post' (default) 'page' or any custom post type
		 *                  type' =>  // how to show posts? 'select' (default) or 'checkbox_list'
		 *                  args' =>  // arguments to query posts, see http://goo.gl/is0yK default ('posts_per_page' => -1)
		 * @param $args     mixed|array
		 *    'name' => // field name/label string optional
		 *    'desc' => // field description, string optional
		 *    'std' => // default value, string optional
		 *    'validation_function' => // validate function, string optional
		 * @param $repeater bool  is this a field inside a repeater? true|false(default)
		 *
		 * @return array
		 */
		public function addPosts($id, $options, $args, $repeater = FALSE) {
			$q = array('posts_per_page' => -1);
			$temp = array('post_type' => 'post', 'type' => 'select', 'args' => $q);
			$options = array_merge($temp, $options);
			$new_field = array(
				'type' => 'posts',
				'id' => $id,
				'desc' => '',
				'name' => 'Posts field',
				'options' => $options
			);
			$new_field = array_merge($new_field, $args);
			if(FALSE === $repeater) {
				$this->_fields[] = $new_field;
			} else {
				return $new_field;
			}
			return false;
		}

		/**
		 *  Add repeater field Block to Page
		 *
		 * @author   Ohad Raz
		 * @since    0.1
		 * @access   public
		 *
		 * @param $id   string  field id, i.e. the meta key
		 * @param $args mixed|array
		 *    'name' => // field name/label string optional
		 *    'desc' => // field description, string optional
		 *    'std' => // default value, string optional
		 *    'style' =>   // custom style for field, string optional
		 *    'validation_function' => // validate function, string optional
		 *    'fields' => //fields to repeater
		 *
		 * @modified 0.4 added sortable option
		 */
		public function addRepeaterBlock($id, $args) {
			$new_field = array(
				'type' => 'repeater',
				'id' => $id,
				'name' => 'Reapeater field',
				'fields' => array(),
				'inline' => FALSE,
				'sortable' => FALSE
			);
			$new_field = array_merge($new_field, $args);
			$this->_fields[] = $new_field;
		}

		/**
		 * Response JSON
		 * Get json date from url and decode.
		 */

		public function json_response($url) {

			// Parse the given url
			$raw = file_get_contents($url, 0, NULL, NULL);
			$decoded = json_decode($raw);

			return $decoded;
		}

		public function Handle_plupload_action() {
			// check ajax nonce
			$imgid = $_POST["imgid"];
			check_ajax_referer($imgid.'pluploadan');
			// handle file upload
			$status = wp_handle_upload(
				$_FILES[$imgid.'async-upload'],
				array(
					'test_form' => TRUE,
					'action' => 'plupload_action'
				)
			);
			// send the uploaded file url in response
			echo $status['url'];
			exit;
		}
	}
endif;
