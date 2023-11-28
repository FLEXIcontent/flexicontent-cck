<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

$form = $this->form;
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<?php
	if (!JFactory::getApplication()->isClient('administrator') || JFactory::getApplication()->input->getCmd('tmpl') === 'component')
	{
		echo JToolBar::getInstance('toolbar')->render();
		echo '<div class="fcclear"></div><br><br>';
	}

	$fieldSets = $this->form->getFieldsets();
	foreach ($fieldSets as $name => $fieldSet) :

		//$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
		//echo '<h2>' . JText::_($label) . '</h2>';
	?>

	<table class="fc-form-tbl">

		<tr>
			<td class="key">
				<label class="fc-prop-lbl" for="file_title"><?php echo JText::_('FLEXI_TITLE'); ?></label>
			</td>
			<td>
				<input name="file_title" type="text" readonly="readonly" value="<?php echo htmlspecialchars($this->row->file->title, ENT_QUOTES, 'UTF-8'); ?>" />
			</td>
		</tr>

		<tr>
			<td class="key">
				<label class="fc-prop-lbl" for="file_uploader"><?php echo JText::_('FLEXI_UPLOADER'); ?></label>
			</td>
			<td>
				<input name="file_uploader" type="text" readonly="readonly" value="<?php echo htmlspecialchars($this->row->file->uploader, ENT_QUOTES, 'UTF-8'); ?>" />
			</td>
		</tr>

		<?php foreach ($this->form->getFieldset($name) as $field) : ?>
			<?php if ($field->hidden): ?>
				<tr style="display:none !important;">
					<td colspan="2"><?php echo $field->input; ?></td>
				</tr>
			<?php else: ?>
			<tr>
				<td class="key">
					<?php echo $field->label; ?>
				</td>
				<td>
					<?php echo $field->input; ?>
				</td>
			</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</table>

	<?php endforeach; ?>

	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="mediadatas" />
	<input type="hidden" name="view" value="mediadata" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="tmpl" value="<?php echo $this->_tmpl; ?>" />

</form>
</div>
<div style="margin-bottom:24px;"></div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>