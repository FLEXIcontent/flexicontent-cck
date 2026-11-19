<?php
/**
 * @version 1.5 stable $Id: duplicate.php 1750 2013-09-03 20:50:59Z ggppdk $
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

$ctrl_task = FLEXI_J16GE ? 'task=templates.' : 'controller=templates&task=';
?>

<?php
flexicontent_html::loadFramework('flexi-lib');
\Joomla\CMS\Factory::getApplication()->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/admin-actions.js', array('version' => FLEXI_VHASH));
?>
<script>
FlexiForm.ready(() => FlexiAdmin.bindResultForm(document.getElementById('adminForm'), document.getElementById('log-bind')));
</script>

<form action="index.php?option=com_flexicontent&<?php echo $ctrl_task; ?>duplicate&layout=duplicate&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<legend>
			<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?>
			<span class="hasTooltip" title="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DUPLICATE_TEMPLATE_DESC', true ); ?>" style="text-decoration: none; color: #333;">
				<img src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
			</span>
		</legend>
		<?php echo FLEXI_J16GE ? '<br />' : ''; ?>
		<input type="text" id="dest" name="dest" value="<?php echo htmlspecialchars($this->dest, ENT_QUOTES, 'UTF-8'); ?>" size="52" />
		<input type="hidden" id="source" name="source" value="<?php echo htmlspecialchars($this->source, ENT_QUOTES, 'UTF-8'); ?>" />
	</fieldset>
	<table width="100%">
		<tr>
			<td>
			<input id="import" type="submit" class="btn" value="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DUPLICATE_TEMPLATE_BUTTON' ); ?>" />
			<input type="button" class="btn" onclick="window.parent.fc_tmpls_modal.dialog('close');" value="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

	<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="task" value="templates.duplicate" />
	<input type="hidden" name="layout" value="templates.duplicate" />
	<input type="hidden" name="format" value="raw" />
</form>