<?php if($wpus_results->have_posts()) : ?>

	<?php while($wpus_results->have_posts()) : $wpus_results->the_post(); ?>

		<div class="post" id="post-<?php the_ID(); ?>">
			<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>"><?php the_title(); ?></a></h2>

			<div class="entry">
				<?php the_excerpt(); ?>
			</div>
			<p class="postmetadata">
				<?php the_taxonomies('before=<p class="tags">&after=</p>&template=%s: %l.'); ?> | <?php edit_post_link('Edit', '', ' | '); ?>
				<?php comments_popup_link('No Comments »', '1 Comment »', '% Comments »'); ?>
			</p>
		</div>

	<?php endwhile; ?>

	<?php if(wpus_option('clear_search')) { ?>
		<a id="wpus-clear-search" class="<?php echo wpus_option('clear_search_class') ?>" href="#"><?php echo wpus_option('clear_search_text'); ?></a>
	<?php } ?>

	<?php wp_reset_postdata(); ?>

<?php else : ?>

	<div class="wpus-no-results"><?php echo wpus_option('no_results_msg'); ?></div>

<?php endif; ?>
