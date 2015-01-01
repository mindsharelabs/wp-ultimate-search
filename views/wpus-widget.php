<?php
/**
 * WPUltimateSearchWidget widget
 */
if(!class_exists('WPUltimateSearchWidget')) :
	class WPUltimateSearchWidget extends WP_Widget {

		/**
		 * Register widget with WordPress.
		 */
		public function __construct() {
			parent::__construct(
				'wp_ultimate_search_widget', // Base ID
				'Ultimate Search Widget', // Name
				array('description' => __('Displays the WP Ultimate Search bar', WPUS_PLUGIN_SLUG),) // Args
			);
		}

		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget($args, $instance) {

			// Disable the widget if we're already on the search results page
			if(get_the_ID() == wpus_option('results_page')) {
				return;
			}

			extract($args);
			$title = apply_filters('widget_title', $instance['title']);

			echo $before_widget;
			if(!empty($title)) {
				echo $before_title . $title . $after_title;
			}

			$atts = array('widget' => true);

			wp_ultimate_search_bar($atts);
			echo $after_widget;
		}

		/**
		 * Sanitize widget form values as they are saved.
		 *
		 * @see WP_Widget::update()
		 *
		 * @param array $new_instance Values just sent to be saved.
		 * @param array $old_instance Previously saved values from database.
		 *
		 * @return array Updated safe values to be saved.
		 */
		public function update($new_instance, $old_instance) {
			$instance = array();
			$instance['title'] = strip_tags($new_instance['title']);

			return $instance;
		}

		/**
		 * Back-end widget form.
		 *
		 * @see WP_Widget::form()
		 *
		 * @param array $instance Previously saved values from database.
		 */
		public function form($instance) {
			if(isset($instance['title'])) {
				$title = $instance['title'];
			} else {
				$title = __('Search', WPUS_PLUGIN_SLUG);
			}
			?>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
			</p>
		<?php
		}
	} // class WPUltimateSearchWidget
endif;

?>
