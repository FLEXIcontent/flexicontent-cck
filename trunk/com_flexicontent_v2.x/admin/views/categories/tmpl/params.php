<?php
/**
 * @version 1.5 stable $Id: params.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

<form action="index.php?option=com_flexicontent&task=categories.params&layout=params&format=raw" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend><?php echo JText::_( 'FLEXI_COPY_PARAMETERS_SOURCE' ).':'; ?></legend>
		<?php echo $this->lists['copyid']; ?>
	</fieldset>
	<fieldset>
		<legend><?php echo JText::_( 'FLEXI_COPY_PARAMETERS_DEST' ).':'; ?></legend>
		<?php echo $this->lists['destid']; ?>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="copy" type="submit" class="button" value="<?php echo JText::_( 'FLEXI_COPY_PARAMETERS_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="button" onclick="window.parent.SqueezeBox.close();;" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="task" value="categories.params" />
<input type="hidden" name="layout" value="categories.params" />
<input type="hidden" name="format" value="raw" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>