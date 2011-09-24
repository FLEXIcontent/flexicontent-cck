<?php
/**
 * @version 1.5 stable $Id: duplicate.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
		$('log-bind').set('html','<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		e = new Event(e).stop();

		new Request.HTML({
			 url: this.get('action'),
		   evalScripts: true,
		   update: $('log-bind'),
		   data: $('adminForm')
		}).send();
		
	});
}); 
</script>

<form action="index.php?option=com_flexicontent&task=templates.duplicate&layout=duplicate&format=raw" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend>
			<?php echo trim(JText::_( 'FLEXI_DUPLICATE_TEMPLATE' )); ?>
			<span class="editlinktip hasTip tags" title="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_DESC' ); ?>" style="text-decoration: none; color: #333;">
				<img src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
			</span>
			</legend>
			<br />
		<input type="text" id="dest" name="dest" value="<?php echo $this->dest; ?>" size="52" />
		<input type="hidden" id="source" name="source" value="<?php echo $this->source; ?>" />
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="button" value="<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="button" onclick="window.parent.document.adminForm.submit();window.parent.document.getElementById('sbox-window').close();" value="<?php echo JText::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="task" value="templates.duplicate" />
<input type="hidden" name="layout" value="templates.duplicate" />
<input type="hidden" name="format" value="raw" />
</form>