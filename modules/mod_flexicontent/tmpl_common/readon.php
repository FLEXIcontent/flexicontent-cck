
	<?php if (isset($catdata->conf) && $catdata->conf->readmore) : ?>
  <span class="fc_block cat_readon_box" >
	  <span class="cat_readon" >
		  <a class="readon" href="<?php echo $catdata->titlelink; ?>"><span><?php echo JText::_('FLEXI_CATEGORY_READ_MORE'); ?></span></a>
		</span>
	</span>
	<?php endif;?>
