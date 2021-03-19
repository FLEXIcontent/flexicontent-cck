<?php
/**
 * @version 1.5 stable $Id: import.php 1614 2013-01-04 03:57:15Z ggppdk $
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

$ctrl_task = FLEXI_J16GE ? 'task=tags.' : 'controller=tags&task=';
$close_popup_js = FLEXI_J16GE ? "window.parent.SqueezeBox.close();" : "window.parent.document.getElementById('sbox-window').close();";
?>
<script>
(function ($) {

	$(document).ready(function() {

		var ajaxloader = '<span class="ajax-loader"><\/span>';
		var adminForm = $('#adminForm');
		var log_bind = $('#log-bind');

		adminForm.on('submit', function(e, data)
		{
			e.preventDefault();
			log_bind.html(ajaxloader);

			$.ajax({
				type: 'POST',
				data: adminForm.serialize(),
				url:  adminForm.prop('action'),
				success: function(str) {
					log_bind.html(str);
				}
			});

		});

	});

})(jQuery);
</script>

<style>
	body.contentpane.component,
	body.contentpane.modal {
		height: 100%;
	}
</style>

<form action="index.php?option=com_flexicontent&".$ctrl_task."import&layout=import&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm" style="height: 92%;">

	<fieldset style="height: 92%;">
		<legend style="font-size:12px; font-style:arial;" >
			<?php echo JText::_( 'FLEXI_IMPORT_TAGS' ); ?>
			<img class="hasTooltip tags" data-placement="bottom" title="<?php echo JText::_('FLEXI_IMPORT_TAGS_DESC', true); ?>" style="float:none; margin:0 0 -4px 4px;" src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
		</legend>
		<textarea id="taglist" name="taglist" style="width: 84%; padding: 4%; margin: 0 4% 8px 4%; height: 90%"></textarea>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="btn btn-success" value="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="btn" onclick="window.parent.document.adminForm.submit();<?php echo $close_popup_js;?>" value="<?php echo JText::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />

<?php if (FLEXI_J16GE) : ?>
	<input type="hidden" name="task" value="tags.import" />
	<input type="hidden" name="layout" value="import" />
	<input type="hidden" name="format" value="raw" />
<?php else : ?>
	<input type="hidden" name="task" value="import" />
	<input type="hidden" name="controller" value="tags" />
	<input type="hidden" name="view" value="tags" />
	<input type="hidden" name="tmpl" value="component" />
<?php endif; ?>

</form>