<?php

/**
 * REMAINING FORM
 */
?>
		<?php if ($buttons_placement === 1) : /* PLACE buttons at BOTTOM of form */ ?>
			<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn('fctoolbar', this, 'btn-primary');" >
				<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
			</div>
			<?php echo $this->toolbar->render(); ?>
		<?php endif; ?>

		<br class="clear" />
		<?php echo JHtml::_( 'form.token' ); ?>
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="controller" value="items" />
		<input type="hidden" name="view" value="item" />
		<?php echo $this->form->getInput('id');?>
		<?php echo $this->form->getInput('hits'); ?>

		<?php if ( $isnew && $typeid ) : ?>
			<input type="hidden" name="jform[type_id]" value="<?php echo $typeid; ?>" />
		<?php endif;?>

		<input type="hidden" name="referer" value="<?php echo htmlspecialchars($this->referer, ENT_COMPAT, 'UTF-8'); ?>" />

		<?php if ($isSite) : ?>
			<?php if ($isnew) echo $this->submitConf; ?>
		<?php endif; ?>

		<input type="hidden" name="unique_tmp_itemid" value="<?php echo substr($app->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);?>" />

	</form>
	<div class="fcclear"></div>
</div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
