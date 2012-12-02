<?php
/**
 * @version 1.5 stable $Id: import.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&controller=items&task=import&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
			if(MooTools.version>="1.2.4") {
				$('import-log').set('html','<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('import-log')
				}).send();
			}else{
				$('import-log').set('html','<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('import-log')
				});
				ajax.request.delay(300, ajax);
			}
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
			<input type="button" class="button" onclick="window.parent.SqueezeBox.close();;" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>" />			
			</td>
		</tr>
	</table>
</div>