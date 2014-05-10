<?php
/**
 * @package		Joomla.Administrator
 * @subpackage	com_users
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
JHtml::_('behavior.tooltip');

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
?>

<form action="<?php echo JRoute::_('index.php?option=com_flexicontent&view=debuggroup&user_id='.(int) $this->state->get('filter.user_id'));?>" method="post" name="adminForm" id="adminForm">
	<fieldset id="filter-bar">
		<div class="filter-search fltlft">
			<label class="filter-search-lbl" for="filter_search"><?php echo JText::_('COM_USERS_SEARCH_ASSETS'); ?></label>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" title="<?php echo JText::_('COM_USERS_SEARCH_USERS'); ?>" />
			<button type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_RESET'); ?></button>
		</div>

		<div class="filter-select fltrt">
			<select name="filter_component" class="inputbox" onchange="this.form.submit()">
				<option value=""><?php echo JText::_('COM_USERS_OPTION_SELECT_COMPONENT');?></option>
				<?php if (!empty($this->components)) {
					echo JHtml::_('select.options', $this->components, 'value', 'text', $this->state->get('filter.component'));
				}?>
			</select>

			<select name="filter_level_start" class="inputbox" onchange="this.form.submit()">
				<option value=""><?php echo JText::_('COM_USERS_OPTION_SELECT_LEVEL_START');?></option>
				<?php echo JHtml::_('select.options', $this->levels, 'value', 'text', $this->state->get('filter.level_start'));?>
			</select>

			<select name="filter_level_end" class="inputbox" onchange="this.form.submit()">
				<option value=""><?php echo JText::_('COM_USERS_OPTION_SELECT_LEVEL_END');?></option>
				<?php echo JHtml::_('select.options', $this->levels, 'value', 'text', $this->state->get('filter.level_end'));?>
			</select>
		</div>

	</fieldset>
	<div class="clr"> </div>

	<div>
		<?php echo JText::_('COM_USERS_DEBUG_LEGEND'); ?>
		<span class="swatch"><?php echo JText::sprintf('COM_USERS_DEBUG_NO_CHECK', '-');?></span>
		<span class="check-0 swatch"><?php echo JText::sprintf('COM_USERS_DEBUG_IMPLICIT_DENY', '-');?></span>
		<span class="check-a swatch"><?php echo JText::sprintf('COM_USERS_DEBUG_EXPLICIT_ALLOW', '&#10003;');?></span>
		<span class="check-d swatch"><?php echo JText::sprintf('COM_USERS_DEBUG_EXPLICIT_DENY', '&#10007;');?></span>
	</div>

	<table class="adminlist">
		<thead>
			<tr>
				<th class="left">
					<?php echo JHtml::_('grid.sort', 'COM_USERS_HEADING_ASSET_TITLE', 'a.title', $listDirn, $listOrder); ?>
				</th>
				<th class="left">
					<?php echo JHtml::_('grid.sort', 'COM_USERS_HEADING_ASSET_NAME', 'a.name', $listDirn, $listOrder); ?>
				</th>
				<?php foreach ($this->actions as $key => $action) : ?>
				<th width="5%">
					<span class="hasTip" title="<?php echo htmlspecialchars(JText::_($key).'::'.JText::_($action[1]), ENT_COMPAT, 'UTF-8'); ?>"><?php echo JText::_($key); ?></span>
				</th>
				<?php endforeach; ?>
				<th class="nowrap" width="5%">
					<?php echo JHtml::_('grid.sort', 'COM_USERS_HEADING_LFT', 'a.lft', $listDirn, $listOrder); ?>
				</th>
				<th class="nowrap" width="3%">
					<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
				</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="15">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>
		<tbody>
		<?php foreach ($this->items as $i => $item) : ?>
			<tr class="row0">
				<td>
					<?php echo $this->escape($item->title); ?>
				</td>
				<td class="nowrap">
					<?php echo str_repeat('<span class="gi">|&mdash;</span>', $item->level) ?>
					<?php echo $this->escape($item->name); ?>
				</td>
				<?php foreach ($this->actions as $action) : ?>
					<?php
					$name	= $action[0];
					$check	= $item->checks[$name];
					if ($check === true) :
						$class	= 'check-a';
						$text	= '&#10003;';
					elseif ($check === false) :
						$class	= 'check-d';
						$text	= '&#10007;';
					elseif ($check === null) :
						$class	= 'check-0';
						$text	= '-';
					else :
						$class	= '';
						$text	= '&#160;';
					endif;
					?>
				<td class="center <?php echo $class;?>">
					<?php echo $text; ?>
				</td>
				<?php endforeach; ?>
				<td class="center">
					<?php echo (int) $item->lft; ?>
					- <?php echo (int) $item->rgt; ?>
				</td>
				<td class="center">
					<?php echo (int) $item->id; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div>
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
		<?php echo JHtml::_('form.token'); ?>
	</div>
</form>
