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

defined( '_JEXEC' ) or die( 'Restricted access' );
?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('import').addEvent('click', function(e) {
			$('import-log').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
			e = new Event(e).stop();

			var url = "index.php?option=com_flexicontent&controller=items&task=import&<?php echo JUtility::getToken();?>=1&format=raw";
 
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('import-log')
			});
			ajax.request.delay(300, ajax);
		});
	}); 

</script>
<div id="import-log">
	<div class="alert-modalbox">
	<?php echo JText::_( 'FLEXI_IMPORT_WARNING' ); ?>
	</div
	<table width="100%">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="button" class="button" value="<?php echo JText::_( 'FLEXI_IMPORT_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="button" onclick="window.parent.document.getElementById('sbox-window').close();" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>" />			
			</td>
		</tr>
	</table>
</div>