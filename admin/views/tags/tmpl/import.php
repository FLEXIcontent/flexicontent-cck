<?php
/**
 * @version 1.5 stable $Id: import.php 1614 2013-01-04 03:57:15Z ggppdk $
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

$ctrl_task = FLEXI_J16GE ? 'task=tags.' : 'controller=tags&task=';
$close_popup_js = "window.parent.fc_closeDialog();";
?>
<?php
flexicontent_html::loadFramework('flexi-lib');
\Joomla\CMS\Factory::getApplication()->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/admin-actions.js', array('version' => FLEXI_VHASH));
?>
<script>
FlexiForm.ready(() => FlexiAdmin.bindResultForm(document.getElementById('adminForm'), document.getElementById('log-bind')));
</script>

<style>
	body.contentpane.component,
	body.contentpane.modal {
		height: 100%;
	}
	dialog.fc-import-report {
		box-sizing: border-box;
		width: calc(100% - 24px);
		max-width: 800px;
		max-height: calc(100% - 24px);
		overflow: auto;
		padding: 16px 20px;
		border: 1px solid var(--border-color, #ccc);
		border-radius: 6px;
		background: var(--body-bg, #fff);
		color: var(--body-color, #222);
		box-shadow: 0 8px 32px rgba(0, 0, 0, .3);
	}
	dialog.fc-import-report::backdrop { background: rgba(0, 0, 0, .45); }
	.fc-import-report h2 { font-size: 1.35rem; }
	.fc-import-report h3 { font-size: 1.05rem; margin-top: 16px; }
	.fc-import-report-lists { display: flex; gap: 12px; align-items: flex-start; }
	.fc-import-report-names { flex: 1; min-width: 0; }
	.fc-import-report-ids { width: 140px; }
	.fc-import-report-lists textarea { display: block; width: 100%; margin-bottom: 6px; font-family: monospace; resize: vertical; }
	.fc-import-report-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
	.fc-import-report-verify-btn { margin-right: auto; }
	iframe.fc-import-report-verify { box-sizing: border-box; display: block; width: 100%; height: 240px; margin-top: 12px; border: 1px solid var(--border-color, #ccc); background: #fff; }
	.fc-import-report-reopen { text-align: center; margin-top: 8px; }
</style>

<form action="index.php?option=com_flexicontent&<?php echo $ctrl_task; ?>import&layout=import&<?php echo FLEXI_J16GE ? 'format=raw' : 'tmpl=component';?>" method="post" name="adminForm" id="adminForm" style="height: 92%;">

	<fieldset style="height: 80%;">
		<legend style="font-size:12px; font-style:arial;" >
			<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_IMPORT_TAGS' ); ?>
			<img class="hasTooltip tags" data-placement="bottom" title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_IMPORT_TAGS_DESC', true); ?>" style="float:none; margin:0 0 -4px 4px;" src="components/com_flexicontent/assets/images/information.png" border="0" alt="Note"/>
		</legend>
		<textarea id="taglist" name="taglist" style="width: 84%; padding: 4%; margin: 0 4% 8px 4%; height: 90%"></textarea>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="import" type="submit" class="btn btn-success" value="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_IMPORT_TAGS_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="btn" onclick="window.parent.document.adminForm.submit();<?php echo $close_popup_js;?>" value="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CLOSE_IMPORT_TAGS' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

	<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />

<?php if (FLEXI_J16GE) : ?>
	<input type="hidden" name="task" value="tags.import" />
	<input type="hidden" name="layout" value="import" />
	<input type="hidden" name="format" value="raw" />
<?php else : ?>
	<input type="hidden" name="task" value="import" />
	<input type="hidden" name="controller" value="tags" />
	<input type="hidden" name="view" value="tags" />
	<input type="hidden" name="tmpl" value="component" />
<?php endif; ?>

</form>