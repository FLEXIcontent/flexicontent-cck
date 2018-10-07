<?php
// no direct access
defined('_JEXEC') or die;
JHtml::_('behavior.keepalive');
?>

<script>
	Joomla.submitbutton = function(pressbutton) {
		var form = document.getElementById('mailtoForm');

		// do field validation
		if (form.mailto.value == "" || form.from.value == "") {
			alert('<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_ERR_NOINFO'); ?>');
			return false;
		}
		form.submit();
	}
</script>
<?php
//$data	= $this->get('data');
?>

<div id="mailto-window">
	<!--div style="text-align:right">
		<a href="javascript: void window.close()">
			<?php echo JText::_('FLEXI_FIELD_FILE_CLOSE_WINDOW'); ?> <?php echo JHtml::_('image', 'mailto/close-x.png', NULL, NULL, true); ?>
		</a>
	</div-->
	<!--div class="fc-mssg fc-nobgimg fc-info">
		<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_TO_A_FRIEND'); ?>
	</div-->
	<div class="fcclear"></div>

	<form action="<?php echo JUri::root(true) ?>/index.php?tmpl=component" id="mailtoForm" method="post">
		<div class="formelm">
			<label for="mailto_field"><?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_TO'); ?></label>
			<input type="text" id="mailto_field" name="mailto" class="inputbox" value="<?php echo flexicontent_html::escape($data->mailto); ?>" size="52" />
		</div>
		<div class="formelm">
			<label for="sender_field">
			<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_SENDER'); ?></label>
			<input type="text" id="sender_field" name="sender" class="inputbox" value="<?php echo flexicontent_html::escape($data->sender); ?>" size="52" />
		</div>
		<div class="formelm">
			<label for="from_field">
			<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_YOUR_EMAIL'); ?></label>
			<input type="text" id="from_field" name="from" class="inputbox" value="<?php echo flexicontent_html::escape($data->from); ?>" size="52" />
		</div>
		<div class="formelm">
			<label for="subject_field">
			<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_SUBJECT'); ?></label>
			<input type="text" id="subject_field" name="subject" class="inputbox" value="<?php echo flexicontent_html::escape($data->subject); ?>" size="52" />
		</div>
		<div class="formelm">
			<label for="desc_field" style="vertical-align:top;">
			<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_DESCRIPTION'); ?></label>
			<textarea id="desc_field" name="desc" class="inputbox" cols="40" rows="5" /><?php echo $data->desc; ?></textarea>
		</div>
		<p>
			<button class="btn" onclick="return Joomla.submitbutton('send');">
				<?php echo JText::_('FLEXI_FIELD_FILE_SEND'); ?>
			</button>
			<!--button class="btn" onclick="window.close();return false;">
				<?php echo JText::_('FLEXI_FIELD_FILE_CANCEL'); ?>
			</button-->
		</p>
		<?php /*<input type="hidden" name="layout" value="<?php echo $this->getLayout();?>" />  */ ?>
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="task" value="call_extfunc" />
		<input type="hidden" name="exttype" value="plugins" />
		<input type="hidden" name="extfolder" value="flexicontent_fields" />
		<input type="hidden" name="extname" value="file" />
		<input type="hidden" name="extfunc" value="share_file_email" />
		<input type="hidden" name="file_id" value="<?php echo $data->file_id; ?>" />
		<input type="hidden" name="content_id" value="<?php echo $data->content_id; ?>" />
		<input type="hidden" name="field_id" value="<?php echo $data->field_id; ?>" />
		<?php echo JHtml::_('form.token'); ?>

	</form>
</div>
