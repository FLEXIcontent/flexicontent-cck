	<?php
		// Add module Read More
		if ($show_more == 1) : ?>
		<div class="module_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
			<a class="readon btn-primary" href="<?php echo \Joomla\CMS\Router\Route::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo \Joomla\CMS\Language\Text::_($more_title); ?></span></a>
		</div>
	<?php endif; ?>

</div>