	<?php
		// Add module Read More
		if ($show_more == 1) : ?>
		<span class="module_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
			<a class="readon" href="<?php echo JRoute::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo JText::_($more_title); ?></span></a>
		</span>
	<?php endif; ?>

</div>