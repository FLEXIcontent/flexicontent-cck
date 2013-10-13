<?php
// no direct access
defined('_JEXEC') or die;
?>

<div style="padding: 10px;">
	<div style="text-align:right">
		<a href="javascript: void window.close()">
			<?php echo JText::_('FLEXI_FIELD_FILE_CLOSE_WINDOW'); ?> <?php echo JHtml::_('image', 'mailto/close-x.png', NULL, NULL, true); ?></a>
	</div>

	<span class="fc-mssg fc-success">
		<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_SENT'); ?>
	</span>

	<label class="flexi label" for="subject_field">
		<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_SUBJECT'); ?>
	</label>
	<div class="fcclear"></div>
	<?php echo $subject; ?>
	<div class="fcclear"></div>
	<br/>
	
	<label class="flexi label" for="subject_field">
		<?php echo JText::_('FLEXI_FIELD_FILE_EMAIL_DESCRIPTION'); ?>
	</label>
	<div class="fcclear"></div>
	<div style="white-space:pre"><?php echo $body; ?></div>
	<div class="fcclear"></div>
	<br/>

</div>
