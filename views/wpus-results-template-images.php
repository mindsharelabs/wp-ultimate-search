<?php if($wpus_results->have_posts()) : ?>

	<?php while($wpus_results->have_posts()) : $wpus_results->the_post(); ?>

		<div class="post" id="post-<?php the_ID(); ?>">
			<?php the_post_thumbnail('thumbnail', array('class' => 'alignleft')) ?>
		</div>

	<?php endwhile; ?>

	<div style="clear: both;"></div>

	<?php if(wpus_option('clear_search')) { ?>
		<a id="wpus-clear-search" class="<?php echo wpus_option('clear_search_class') ?>" href="#"><?php echo wpus_option('clear_search_text'); ?></a>
	<?php } ?>

	<?php wp_reset_postdata(); ?>

<?php else : ?>

	<div class="wpus-no-results"><?php echo wpus_option('no_results_msg'); ?></div>

<?php endif; ?>
