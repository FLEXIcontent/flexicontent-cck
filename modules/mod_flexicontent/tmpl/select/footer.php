	<?php
		// Add module Read More
		if ($show_more == 1) : ?>
		<span class="module_readon<?php echo htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8'); ?>"<?php if ($more_css) : ?> style="<?php echo htmlspecialchars((string) $more_css, ENT_QUOTES, 'UTF-8'); ?>"<?php endif;?>>
			<a class="readon" href="<?php echo \Joomla\CMS\Router\Route::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo htmlspecialchars((string) \Joomla\CMS\Language\Text::_($more_title), ENT_QUOTES, 'UTF-8', false); ?></span></a>
		</span>
	<?php endif; ?>

</div>