<?php
/**
 * @version 1.5 stable $Id$
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
?>

<form action="index.php?option=com_flexicontent&amp;view=search" method="post" name="adminForm" id="adminForm">
<?php	/*<div class="form-filter" style="float: left;">
		<label for="filter_search"><?php echo JText::sprintf('FINDER_SEARCH_LABEL', JText::_('FINDER_ITEMS')); ?></label>
		<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->state->get('filter.search'); ?>" class="text_area" onchange="document.adminForm.submit();" />
		<button onclick="this.form.submit();"><?php echo JText::_('FINDER_SEARCH_GO'); ?></button>
		<button onclick="document.getElementById('filter_search').value='';document.getElementById('filter_type').value='0';document.getElementById('filter_state').value='*';this.form.submit();"><?php echo JText::_('FINDER_SEARCH_RESET'); ?></button>
	</div>

	<div class="form-filter" style="float: right;">
		<?php echo JText::sprintf('FINDER_FILTER_BY', JText::_('FINDER_ITEMS')); ?>
		<?php echo JHTML::_('finder.typeslist', $this->state->get('filter.type')); ?>
		<?php echo JHTML::_('finder.statelist', $this->state->get('filter.state')); ?>
	</div>
*/ ?>
	<table class="adminlist" style="clear: both;">
		<thead>
			<tr>
				<th width="5">
					<?php echo JText::_('NUM'); ?>
				</th>
				<th width="5">
					<input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count($this->data); ?>);" />
				</th>
				<th nowrap="nowrap" width="10%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_INDEX'), 'l.title', 'ASC', 'f.field_id'); ?>
				</th>
				<th nowrap="nowrap" width="20%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_ITEMS'), 'l.state', 'ASC', 'f.field_id'); ?>
				</th>
				<th nowrap="nowrap" width="10%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_EXTRA_TABLE'), 'l.type_id', 'ASC', 'f.field_id'); ?>
				</th>
				<th nowrap="nowrap" width="5%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_EXTRA_PK_ID'), 'l.url', 'ASC', 'f.field_id'); ?>
				</th>
				<th nowrap="nowrap">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_SEARCH_INDEX'), 'l.indexdate', 'ASC', 'f.field_id'); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if (count($this->data) == 0): ?>
			<tr class="row0">
				<td align="center" colspan="7">
					<?php
					if ($this->total == 0) {
						echo JText::_('FLEXI_NO_DATA');
						//echo JText::_('FINDER_INDEX_TIP');
					} else {
						echo JText::_('FINDER_INDEX_NO_CONTENT');
					}
					?>
				</td>
			</tr>
			<?php endif; ?>

			<?php
			$n = 0; $o = 0;
			foreach ($this->data as $row): ?>
			<tr class="<?php echo 'row', $o; ?>">
				<td>
					<?php echo $this->pagination->getRowOffset( $n ); ?>
				</td>
				<td align="center">
					<?php echo JHTML::_('grid.id', $n, $row->field_id.'|'.$row->item_id); ?>
				</td>
				<td>
					<?php echo $this->escape($row->label); ?>
				</td>
				<td>
					<?php echo $this->escape($row->title); ?>
				</td>
				<td nowrap="nowrap" style="text-align: center">
					<?php echo $row->extratable; ?>
				</td>
				<td nowrap="nowrap" style="text-align: center;">
					<?php echo $row->extraid; ?>
				</td>
				<td style="text-align: left;">
					<?php
						if(iconv_strlen($row->search_index, "UTF-8")>200)
							echo iconv_substr($row->search_index, 0, 200, "UTF-8").'...';
						else
							echo $row->search_index;
					?>
				</td>
				<?php /*<td nowrap="nowrap" style="text-align: center;">
					<?php //echo JHtml::date($row->indexdate, '%Y-%m-%d %H:%M:%S'); ?>
				</td>*/ ?>
			</tr>

			<?php $n++; $o = ++$o % 2; ?>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="7" nowrap="nowrap">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>
	</table>

	<input type="hidden" name="task" value="display" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="filter_order" value="<?php //echo $this->state->get('list.ordering') ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php //echo $this->state->get('list.direction') ?>" />
	<input type="hidden" name="controller" value="search" />
	<?php echo JHTML::_('form.token'); ?>
</form>
