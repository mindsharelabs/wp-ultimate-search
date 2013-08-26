<?php if ($results): ?>

	<?php global $post; ?>
	
	<?php foreach ($results as $post): ?>
		
		<?php setup_postdata($post); ?>

		<div class="post" id="post-<?php the_ID(); ?>">
		<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>">
		<?php the_title(); ?></a></h2>
			<small><?php // the_time('F jS, Y') ?> <!-- by <?php the_author() ?> --></small>
			<div class="entry">
				<?php the_excerpt(); ?>
			</div>
			<p class="postmetadata">Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  
			<?php comments_popup_link('No Comments »', '1 Comment »', '% Comments »'); ?></p>
		</div>
		
	<?php endforeach; ?>
	
	<?php if(wpus_option('clear_search')) { ?>
		<a id="wpus-clear-search" class="<?php echo wpus_option('clear_search_class') ?>" href="#"><?php echo wpus_option('clear_search_text'); ?></a>
	<?php } ?>
	
<?php else : ?>
		<div class="wpus-no-results"><?php echo wpus_option('no_results_msg'); ?></div>
<?php endif; ?>