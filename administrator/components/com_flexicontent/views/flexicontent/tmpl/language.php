<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009-2012 Emmanuel Danan - www.vistamedia.fr
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
		$('lang').addEvent('change', function(e) {
			$('log').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
			e = new Event(e).stop();

			var url = "index.php?option=com_flexicontent&format=raw&<?php echo JUtility::getToken();?>=1&task=checklangfiles&code=" + lang.value;
 
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('log')
			});
			ajax.request.delay(300, ajax);
		});
	}); 
</script>

<form action="index.php" method="post" name="adminForm" id="adminForm">
	<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					<tr>
						<th colspan="2" style="text-align:left;"><h2><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_TITLE' ); ?></h2></th>
					</tr>
					<tr>
						<td class="key">
							<label for="lang">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CODE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['languages']; ?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="message">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_MESSAGE' ).':'; ?>
							</label>
						</td>
						<td>
							<textarea id="message" name="message" cols="30" rows="10"></textarea>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_COMPLETE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', @$this->row->published );
							echo $html;
							?>
						</td>
					</tr>
				</table>
			</td>
			<td valign="top">
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					<tr>
						<th style="text-align:left;"><h2><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_MISSING_FILES_TITLE' ); ?></h2></th>
					</tr>
					<tr>
						<td><div id="log"><?php echo $this->lists['missing_lang']; ?></div></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="flexicontent" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
<!--
<div id="language">
	<table width="100%">
		<tr>
			<td width="50%" align="right">
				<input id="import" type="button" class="button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
				<input type="button" class="button" onclick="window.parent.document.getElementById('sbox-window').close();" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>" />			
			</td>
		</tr>
	</table>
</div>
-->