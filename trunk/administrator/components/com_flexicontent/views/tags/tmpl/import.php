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
?>
<script type="text/javascript">
window.addEvent('domready', function(){
	$('adminForm').addEvent('submit', function(e) {
		$('log-bind').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		e = new Event(e).stop();
		
		this.send({
			update: 	$('log-bind')
		});
	});
}); 
</script>

<form action="index.php?option=com_flexicontent&controller=tags&task=import&layout=import&format=raw" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend>
			<?php echo JText::_( 'FLEXI_IMPORT_TAGS' ); ?>
			<span class="editlinktip hasTip tags" title="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_DESC' ); ?>" style="text-decoration: none; color: #333;">
				<img src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
			</span>
		</legend>
		<textarea id="taglist" name="taglist" rows="20" cols="52"></textarea>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="button" value="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="button" onclick="window.parent.document.adminForm.submit();window.parent.document.getElementById('sbox-window').close();" value="<?php echo JText::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="controller" value="tags" />
<input type="hidden" name="view" value="tags" />
<input type="hidden" name="task" value="import" />
</form>