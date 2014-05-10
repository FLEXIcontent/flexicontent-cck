<?php
/**
 * @version 1.5 stable $Id$
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

<form action="index.php?option=com_flexicontent&".$ctrl_task."import&layout=import&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend style="font-size:12px; font-style:arial;" >
			<?php echo JText::_( 'FLEXI_IMPORT_TAGS' ); ?>
			<img class="editlinktip hasTip tags" title="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_DESC' ); ?>" style="float:none; margin:0 0 -4px 4px;" src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
		</legend>
		<textarea id="taglist" name="taglist" rows="20" cols="51" style="font-size:11px; font-style:arial;"></textarea>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="fc_button" value="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_BUTTON' ); ?>" />
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