<?php
/**
 * @version 1.5 stable $Id: duplicate.php 1750 2013-09-03 20:50:59Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('_JEXEC') or die('Restricted access');

$ctrl_task = FLEXI_J16GE ? 'task=templates.' : 'controller=templates&task=';

$js = "
jQuery(document).ready(function() {
	var adminForm = jQuery('#adminForm');
	adminForm.submit(function( event ) {
		var log_bind = jQuery('#log-bind');
		log_bind.html('<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
		jQuery.ajax({
			type: 'POST',
			data: adminForm.serialize(),
			url:  adminForm.prop('action'),
			success: function(str) {
				log_bind.html(str);
			}
		});
		event.preventDefault();
	});	
});
";

JFactory::getDocument()->addScriptDeclaration($js);
?>

<form action="index.php?option=com_flexicontent&<?php echo $ctrl_task; ?>duplicate&layout=duplicate&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<fieldset>
		<legend>
			<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?>
<i class="ml-10 small icon-info hasTooltip" data-placement="right" title="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_DESC', true ); ?>"></i>		
		</legend>
		
		<div class="control-group text-center">
			<div class="controls">
		<input type="text" id="dest" name="dest" value="<?php echo $this->dest; ?>" size="52" />
		<input type="hidden" id="source" name="source" value="<?php echo $this->source; ?>" />
		</div>
		</div>
	</fieldset>
	
	<div class="control-group text-center">
			<div class="controls">
	<div class="btn-group">
	<input id="import" type="submit" class="btn btn-success" value="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_BUTTON' ); ?>" />
	<input type="button" class="btn btn-danger" onclick="window.parent.fc_tmpls_modal.dialog('close');" value="<?php echo JText::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />
			</div>
		</div>
</div>


	<div id="log-bind"></div>

	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="task" value="templates.duplicate" />
	<input type="hidden" name="layout" value="templates.duplicate" />
	<input type="hidden" name="format" value="raw" />
</form>
