<?php
/**
 * @package		Joomla.Administrator
 * @subpackage	com_flexicontent
 * @copyright	Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
//JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
$canDo = UsersHelper::getActions();
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'group.cancel' || document.formvalidator.isValid(document.id('group-form'))) {
			Joomla.submitform(task, document.getElementById('group-form'));
		}
	}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_flexicontent&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">
	<div class="width-100">
		<fieldset>
			<legend><?php echo JText::_('COM_USERS_USERGROUP_DETAILS');?></legend>
			<table class="fc-form-tbl">
				<tr>
					<td class="key"><?php echo $this->form->getLabel('title'); ?></td>
					<td><?php echo $this->form->getInput('title'); ?></td>
				</tr>
				
				<?php $parent_id = $this->form->getField('parent_id');?>
				<tr>
					<td class="key"><?php if (!$parent_id->hidden) echo $parent_id->label; ?></td>
					<td><?php echo $parent_id->input; ?></td>
				</tr>
			</table>
		</fieldset>
		<input type="hidden" name="task" value="" />
		<?php echo JHtml::_('form.token'); ?>
	</div>
</form>
<div class="clr"></div>
