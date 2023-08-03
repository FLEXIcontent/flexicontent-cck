<?php
defined('_JEXEC') or die('Restricted access');

/**
 * REMAINING FORM
 */
?>
			<?php if ($buttons_placement === 1) : /* PLACE buttons at BOTTOM of form */ ?>
			<div class="fctoolbar_bottom_placement fcpos_right">
				<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn(<?php echo FLEXI_J40GE ? "jQuery('#fctoolbar').parent()" : "'fctoolbar'"; ?>, this, 'btn-primary');" >
					<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
				</div>
				<?php // An EXAMPLE of adding more buttons: $this->toolbar->appendButton('Standard', 'cancel', 'JCANCEL', 'items.cancel', false);
				echo $this->toolbar->render(); ?>
			</div>
			<?php endif; ?>

				<br class="clear" />
				<?php echo JHtml::_( 'form.token' ); ?>
				<input type="hidden" name="task" id="task" value="" />
				<input type="hidden" name="option" value="com_flexicontent" />
				<input type="hidden" name="controller" value="items" />
				<input type="hidden" name="view" value="item" />
				<?php echo $this->form->getInput('id');?>
				<?php echo $this->form->getInput('hits'); /* this is ignored by form validation */ ?>

				<?php if ($is_autopublished) :
				/* Auto publish new item via MENU OVERRIDE, (these are overwritten by the controller checks) */
				?>
					<input type="hidden" id="jform_state" name="jform[state]" value="1" />
					<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />
				<?php elseif (!$usestate) :
				/* Not using state (this is overwritten by the controller checks to maintain current value) */
				?>
					<input type="hidden" id="jform_state" name="jform[state]" value="<?php echo (int) $this->row->state; ?>" />
					<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />
				<?php elseif ($this->perms['canpublish'] && (!$use_versioning || $auto_approve)) :?>
					<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />
				<?php endif; ?>

				<?php if ( $isnew && $typeid ) : /* this is compared to submit menu item configuration by the controller */ ?>
					<input type="hidden" name="jform[type_id]" value="<?php echo $typeid; ?>" />
				<?php endif;?>

				<input type="hidden" name="referer" value="<?php echo htmlspecialchars($this->referer ?? '', ENT_COMPAT, 'UTF-8'); ?>" />

				<?php if ($isSite) : ?>
					<?php if ($isnew) echo $this->submitConf; ?>
				<?php endif; ?>

				<input type="hidden" name="unique_tmp_itemid" value="<?php echo substr($app->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);?>" />

			</form>
			<div class="fcclear"></div>

		</div> <!-- class="span** col**" -->

	<?php if ($buttons_placement === 3) : /* PLACE buttons at RIGHT of form */ ?>
		<div class="span2 col-md-2 fctoolbar_side_placement">
			<div id="fctoolbar_btn" class="btn btn-primary" onclick="fc_toggle_box_via_btn(<?php echo FLEXI_J40GE ? "jQuery('#fctoolbar').parent()" : "'fctoolbar'"; ?>, this, 'btn-primary');" >
				<?php echo JText::_('JTOOLBAR'); ?> <span class="icon-wrench"></span></a>
			</div>
			<?php // An EXAMPLE of adding more buttons: $this->toolbar->appendButton('Standard', 'cancel', 'JCANCEL', 'items.cancel', false);
			echo $this->toolbar->render(); ?>
		</div>
	<?php endif; ?>

	</div>  <!-- class="container-fluid row" -->
</div>  <!-- id="flexicontent" -->

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
