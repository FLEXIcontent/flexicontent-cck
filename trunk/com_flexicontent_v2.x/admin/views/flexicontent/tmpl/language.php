<?php
/**
 * @version 1.5 stable $Id: language.php 1577 2012-12-02 15:10:44Z ggppdk $
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
<!-- CSS fixes for J2.5.x -->
<style>
	form.lang_submit select#lang {  float:none;	margin:0px;  }
</style>

<script type="text/javascript">
	window.addEvent('domready', function(){
		$('lang').addEvent('change', function(e) {
			if(MooTools.version>="1.2.4") {

				$('log').set('html', '<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CHECKING',true ); ?></span></p>');
				e = e.stop();

				var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&code=" + lang.value;
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('log'),
				}).send();

			} else {

				$('log').setHTML('<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CHECKING',true ); ?></span></p>');
				e = new Event(e).stop();

				var url = "index.php?option=com_flexicontent&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&code=" + lang.value;
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('log')
				});
				ajax.request.delay(1000, ajax);

 			}
		});

		$('missing').addEvent('click', function(e) {

			if(MooTools.version>="1.2.4") {
				$('log').set('html', '<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CREATING_MISSING',true ); ?></span></p>');
				e = e.stop();

				var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&method=create&code=" + lang.value;
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('log'),
				}).send();

			} else {

				$('log').setHTML('<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CREATING_MISSING',true ); ?></span></p>');
				e = new Event(e).stop();

				var url = "index.php?option=com_flexicontent&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&method=create&code=" + lang.value;
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('log')
				});
				ajax.request.delay(1000, ajax);

			}
		});

		$('archive').addEvent('click', function(e) {

			// Récupération des valeurs des champs de formulaire
			var code 	= encodeURIComponent($('lang').value);
			var name 	= encodeURIComponent($('myname').value);
			var email 	= encodeURIComponent($('myemail').value);
			var web 	= encodeURIComponent($('website').value);
			var message = encodeURIComponent($('message').value);

			// Préparation des paramètres d'URL
			var params 	 = '&code='+ code;
			params 		+= '&name='+ name;
			params 		+= '&email='+ email;
			params 		+= '&web=' + web;
			params 		+= '&message=' + message;

			if(MooTools.version>="1.2.4") {
				$('log').set('html', '<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CREATING_ARCHIVE',true ); ?></span></p>');
				e = e.stop();

				var url = "index.php?option=com_flexicontent&tmpl=component&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&method=zip" + params;
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('log'),
				}).send();

			} else {

				$('log').setHTML('<p class="spinner"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"><span><?php echo JText::_( 'FLEXI_SEND_LANGUAGE_CREATING_ARCHIVE',true ); ?></span></p>');
				e = new Event(e).stop();

				var url = "index.php?option=com_flexicontent&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&task=langfiles&method=zip" + params;
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('log')
				});
				ajax.request.delay(1000, ajax);

			}
		});
	});
</script>

<form action="" method="get" name="adminForm" id="adminForm" class="lang_submit">
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
							<label for="myname">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_NAME' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="myname" name="myname" class="required" value="<?php echo $this->fromname; ?>" size="50" maxlength="100" />
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="myemail">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_EMAIL' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="myemail" name="myemail" class="required" value="<?php echo $this->mailfrom; ?>" size="50" maxlength="100" />
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="website">
								<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_WEBSITE' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="website" name="website" class="required" value="<?php echo $this->website; ?>" size="50" maxlength="100" />
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
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', @$this->row->published );
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
						<td align="right"><input id="archive" type="button" class="button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_ARCHIVE' ); ?>" /></td>
						<td align="left"><input id="send" type="button" class="button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_SEND' ); ?>" onclick="alert('NOT ALLOWED. Next revision will allow public usage or limit to authorized translators only')" /></td>
					</tr>
				</table>
			</td>
			<td valign="top">
				<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
					<tr>
						<th style="text-align:left;"><h2>&nbsp;<?php // echo JText::_( 'FLEXI_SEND_LANGUAGE_MISSING_FILES_TITLE' ); ?></h2></th>
					</tr>
					<tr>
						<td>
							<div id="log" style="width:330px; display:block;"><?php echo $this->lists['missing_lang']; ?></div>
							<input id="missing" type="button" class="button" value="<?php echo JText::_( 'FLEXI_SEND_LANGUAGE_ADD_MISSING' ); ?>" />
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="flexicontent" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>