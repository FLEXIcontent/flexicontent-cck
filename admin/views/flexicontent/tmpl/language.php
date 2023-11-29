<?php
/**
 * @version 1.5 stable $Id: language.php 1750 2013-09-03 20:50:59Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');
?>

<!-- CSS fixes for J2.5.x -->
<style>
	form.lang_submit select#lang {  float:none;	margin:0px;  }
</style>

<script>
(function ($) {

	jQuery(document).ready(function() {
		var ajaxloader = '<span class="ajax-loader"><\/span>';

		$('#lang').on('change', function(e, data)
		{
			e.preventDefault();
			$('#log').html(ajaxloader);
			var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo JSession::getFormToken();?>=1&task=flexicontent.createlanguagepack&code=" + lang.value;

			jQuery.ajax({
				type: 'GET',
				url: url,
				data: {}
			}).done( function(data) {
				$('#log').html(data);
			});

		});

		$('#missing').on('click', function(e, data)
		{
			e.preventDefault();
			$('#log').html(ajaxloader);
			var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo JSession::getFormToken();?>=1&task=flexicontent.createlanguagepack&method=create&code=" + lang.value;

			jQuery.ajax({
				type: 'GET',
				url: url,
				data: {}
			}).done( function(data) {
				$('#log').html(data);
			});

		});

		$('#archive').on('click', function(e, data)
		{
			e.preventDefault();
			$('#log').html(ajaxloader);

			// Récupération des valeurs des champs de formulaire
			var code 	= encodeURIComponent($('#lang').value);
			var name 	= encodeURIComponent($('#myname').value);
			var email 	= encodeURIComponent($('#myemail').value);
			var web 	= encodeURIComponent($('#website').value);
			var message = encodeURIComponent($('#message').value);

			// Préparation des paramètres d'URL
			var params 	 = '&code='+ code;
			params 		+= '&name='+ name;
			params 		+= '&email='+ email;
			params 		+= '&web=' + web;
			params 		+= '&message=' + message;

			var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo JSession::getFormToken();?>=1&task=flexicontent.createlanguagepack&method=zip" + params;
			jQuery.ajax({
				type: 'GET',
				url: url,
				data: {}
			}).done( function(data) {
				$('#log').html(data);
			});

		});

	});

})(jQuery);
</script>

<div id="flexicontent">
<form action="" method="get" name="adminForm" id="adminForm" class="lang_submit">
	<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					<tr>
						<th colspan="2" style="text-align:left;">
							<label class="label" style="width: auto !important; max-width: 400px !important;">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_TITLE' ); ?>
							</label>
						</th>
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
							<label for="myname">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_NAME' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="myname" name="myname" class="required" value="<?php echo $this->fromname; ?>" size="40" maxlength="100" />
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="myemail">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_EMAIL' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="myemail" name="myemail" class="required" value="<?php echo $this->mailfrom; ?>" size="40" maxlength="100" />
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="website">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_WEBSITE' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="website" name="website" class="required" value="<?php echo $this->website; ?>" size="40" maxlength="100" />
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
<!--
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_COMPLETE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHtml::_('select.booleanlist', 'published', 'class="inputbox"', @$this->row->published );
							echo $html;
							?>
						</td>
					</tr>
-->
					<tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td align="right"><input id="archive" type="button" class="fc_button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_ARCHIVE' ); ?>" /></td>
						<td align="left"><input id="send" type="button" class="fc_button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_SEND' ); ?>" onclick="alert('NOT ALLOWED. Next revision will allow public usage or limit to authorized translators only')" /></td>
					</tr>
				</table>
			</td>
			<td valign="top">
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					<tr>
						<th style="text-align:left;" colspan="2">
							<label class="label" style="width: auto !important; max-width:400px !important;">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_MISSING_FILES_TITLE' ); ?>
							</label>
						</th>
					</tr>
					<tr>
						<td>
							<div id="log" style="width:320px; display:block;">
								<?php if ( !is_array($this->lists['missing_lang']) ) : ?>
									<div id="log" style="width:320px; display:block;"><?php echo $this->lists['missing_lang']; ?>
								<?php else : ?>
									<?php
									$missing = & $this->lists['missing_lang'];
									$missing_str = '';
									if (@$missing['site']) $missing_str .= '<label class="label">Missing files (Frontend)</label> <br/>'. implode('<br/>', $missing['site']);
									if (@$missing['site'] && @$missing['admin']) $missing_str .= '<br/>';
									if (@$missing['admin']) $missing_str .= '<label class="label">Missing files (Backend)</label> <br/>'. implode('<br/>', $missing['admin']);
									echo $missing_str;
									?>
									<div class="fcclear"></div>
									<input id="missing" type="button" class="fc_button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_ADD_MISSING' ); ?>" />
								<?php endif; ?>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="flexicontent" />
</form>
</div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>