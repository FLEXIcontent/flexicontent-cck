<?php
/**
 * @version 1.5 stable $Id: duplicate.php 1614 2013-01-04 03:57:15Z ggppdk $
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
$close_popup_js = FLEXI_J16GE ? "window.parent.SqueezeBox.close();" : "window.parent.document.getElementById('sbox-window').close();";
?>
<script type="text/javascript">
window.addEvent('domready', function(){
	$('adminForm').addEvent('submit', function(e) {
		e = new Event(e).stop();
		if (MooTools.version>="1.2.4") {
			$('log-bind').set('html','<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
			new Request.HTML({
				 url: this.get('action'),
			   evalScripts: true,
			   update: $('log-bind'),
			   data: $('adminForm')
			}).send();
		} else {
			$('log-bind').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
			this.send({
				update: 	$('log-bind')
			});
		}
	});
}); 
</script>

<form action="index.php?option=com_flexicontent&".$ctrl_task."duplicate&layout=duplicate&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend>
			<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?>
			<span class="editlinktip hasTip tags" title="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_DESC' ); ?>" style="text-decoration: none; color: #333;">
				<img src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
			</span>
		</legend>
		<?php echo FLEXI_J16GE ? '<br />' : ''; ?>
		<input type="text" id="dest" name="dest" value="<?php echo $this->dest; ?>" size="52" />
		<input type="hidden" id="source" name="source" value="<?php echo $this->source; ?>" />
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="fc_button" value="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="fc_button" onclick="window.parent.document.adminForm.submit();<?php echo $close_popup_js;?>" value="<?php echo JText::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />

<?php if (FLEXI_J16GE) : ?>
	<input type="hidden" name="task" value="templates.duplicate" />
	<input type="hidden" name="layout" value="templates.duplicate" />
	<input type="hidden" name="format" value="raw" />
<?php else : ?>
	<input type="hidden" name="task" value="duplicate" />
	<input type="hidden" name="controller" value="templates" />
	<input type="hidden" name="view" value="templates" />
	<input type="hidden" name="tmpl" value="component" />
<?php endif; ?>
</form>