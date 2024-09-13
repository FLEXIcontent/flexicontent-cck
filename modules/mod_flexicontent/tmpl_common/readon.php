
	<?php if (isset($catdata->conf) && $catdata->conf->readmore) : ?>
  <div class="fc_block cat_readon" >
		  <a class="readon btn btn-primary" href="<?php echo $catdata->titlelink; ?>"><span><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CATEGORY_READ_MORE'); ?></span></a>
	</div>
	<?php endif;?>
