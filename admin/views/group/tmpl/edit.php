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

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
//JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidator');
$canDo = UsersHelper::getActions();
?>

<script>
	Joomla.submitbutton = function(task)
	{
		if (task == 'group.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
			Joomla.submitform(task, document.getElementById('adminForm'));
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
