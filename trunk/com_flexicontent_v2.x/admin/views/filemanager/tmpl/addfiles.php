<?php
/**
 * @version 1.5 stable $Id: addfiles.php 1800 2013-11-01 04:30:57Z ggppdk $
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
$ctrl_task   = FLEXI_J16GE ? 'task=filemanager.'  :  'controller=filemanager&amp;task=';
$session = JFactory::getSession();
?>
<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td>
			<?php if ($this->require_ftp): ?>
			<form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>ftpValidate" name="ftpForm" id="ftpForm" method="post">
				<fieldset title="<?php echo JText::_( 'FLEXI_DESCFTPTITLE' ); ?>">
					<legend><?php echo JText::_( 'FLEXI_DESCFTPTITLE' ); ?></legend>
					<?php echo JText::_( 'FLEXI_DESCFTP' ); ?>
					<table class="adminform nospace">
						<tbody>
							<tr>
								<td width="120">
									<label for="username"><?php echo JText::_( 'FLEXI_USERNAME' ); ?>:</label>
								</td>
								<td>
									<input type="text" id="username" name="username" class="input_box" size="70" value="" />
								</td>
							</tr>
							<tr>
								<td width="120">
									<label for="password"><?php echo JText::_( 'FLEXI_PASSWORD' ); ?>:</label>
								</td>
								<td>
									<input type="password" id="password" name="password" class="input_box" size="70" value="" />
								</td>
							</tr>
						</tbody>
					</table>
				</fieldset>
			</form>
			<?php endif; ?>

			<!-- File Upload Form -->
			<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>upload&amp;<?php echo $session->getName().'='.$session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" id="uploadForm" method="post" enctype="multipart/form-data">
				<fieldset>
					<legend><?php echo JText::_( 'FLEXI_UPLOAD_FILE' ); ?> [ <?php echo JText::_( 'FLEXI_MAX' ); ?>&nbsp;<?php echo ($this->params->get('upload_maxsize') / 1000000); ?>M ]</legend>
					<fieldset class="actions">
						<?php echo JText::_( 'FLEXI_FILE_TITLE' ).': '; ?><input type="text" id="file-upload-name" name="altname" />
						<br /><br />
						<input type="file" id="file-upload" name="Filedata" />
						<textarea name="file-desc" cols="23" rows="5" id="file-desc"></textarea>
						<input type="submit" id="file-upload-submit" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
						<span id="upload-clear"></span>
					</fieldset>
					<ul class="upload-queue" id="upload-queue">
						<li style="display: none" />
						</ul>
					</fieldset>
					<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager&layout=addfiles&tmpl=component'); ?>" />
				</form>
			</td>
		</tr>
	</table>
