<?php
/**
 * @version 1.5 stable $Id: default.php 1528 2012-10-30 01:51:10Z ggppdk $
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

$items_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';
?>

<script language="javascript" type="text/javascript">

// the function overloads joomla standard event
function submitform(pressbutton)
{
	form = document.adminForm;
	
	// Store the button task into the form
	if (pressbutton) {
		form.task.value=pressbutton;
	}

	// Execute onsubmit
	if (typeof form.onsubmit == "function") {
		form.onsubmit();
	}
	// Submit the form
	form.submit();
}

// delete active filter
function delFilter(name)
{
	var myForm = $('adminForm');
	if ($(name).type=='checkbox')
		$(name).checked = '';
	else
		$(name).setProperty('value', '');
}

function delAllFilters() {
	delFilter('filter_fieldtype'); delFilter('filter_itemtype'); delFilter('filter_itemstate');
	delFilter('search_index'); delFilter('search_itemtitle'); delFilter('search_itemid');
}
</script>
<form action="index.php?option=com_flexicontent&amp;view=search" method="post" name="adminForm" id="adminForm">

	<table class="adminlist" style="clear: both;">
		<thead>
			<tr>
				<th width="5">
					<?php echo JText::_('NUM'); ?>
				</th>
				<th width="5">
					<input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count($this->data); ?>);" />
				</th>
				<th nowrap="nowrap" width="20%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_ITEMS'), 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap" width="10%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_LABEL'), 'f.label', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap" width="10%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_NAME'), 'f.name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap" width="10%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_TYPE'), 'f.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap" width="5%">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_COUNT'), 'ai.extraid', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_SEARCH_INDEX'), 'ai.search_index', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
				<th nowrap="nowrap">
					<?php echo JHTML::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_ID'), 'ai.value_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
			</tr>
			
			
			<tr id="filterline">
				<!--td class="left col_title" colspan="4">
					<span class="radio"><?php echo $this->lists['scope']; ?></span>
					<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="inputbox" />
				</td-->
				<td class="left"></td>
				<td class="left"></td>
				<td class="left" colspan="3">
					<?php echo '<label class="label'.($this->f_active['search_itemtitle'] ? " highlight":"").'">'.JText::_('FLEXI_TITLE').'</label> '; ?> &nbsp;
					<input type="text" name="search_itemtitle" id="search_itemtitle" value="<?php echo $this->lists['search_itemtitle']; ?>" class="text_area" onChange="document.adminForm.submit();" size="30"/>
					<?php echo '<label class="label'.($this->f_active['search_itemid'] ? " highlight":"").'">'.JText::_('FLEXI_ID').'</label> '; ?> &nbsp;
					<input type="text" name="search_itemid" id="search_itemid" value="<?php echo $this->lists['search_itemid']; ?>" class="text_area" onChange="document.adminForm.submit();" size="6" />
					<br/>
					<?php echo '<label class="label'.($this->f_active['filter_itemtype'] ? " highlight":"").'">'.JText::_('FLEXI_TYPE_NAME').'</label> '; ?> &nbsp;
					<?php echo $this->lists['filter_itemtype']; ?>
					<?php echo '<label class="label'.($this->f_active['filter_itemstate'] ? " highlight":"").'">'.JText::_('FLEXI_STATE').'</label> '; ?> &nbsp;
					<?php echo $this->lists['filter_itemstate']; ?>
				</td>
				
				<td class="left col_fieldtype" >
					<?php echo '<label class="label'.($this->f_active['filter_fieldtype'] ? " highlight":"").'">'.JText::_('FLEXI_FILTER').'</label> '; ?> &nbsp; <br/>
					<?php echo $this->lists['filter_fieldtype']; ?>
				</td>
				
				<td class="left"></td>
				<td class="left col_search_index">
					<?php echo '<label class="label'.($this->f_active['search_index'] ? " highlight":"").'">'.JText::_('FLEXI_FILTER').'</label> '; ?> &nbsp; <br/>
					<input type="text" name="search_index" id="search_index" value="<?php echo $this->lists['search_index']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				</td>
				<td class="left"></td>
			</tr>

			<tr>
				<td colspan="9" class="filterbuttons">
					<input type="submit" class="fc_button fcsimple" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
					<input type="button" class="fc_button fcsimple" onclick="delAllFilters();this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
					<?php if (isset($this->lists['filter_stategrp'])) : ?>
						<span class="radio flexi_tabbox" style="margin-left:60px;"><?php echo '<span class="flexi_tabbox_label">'.JText::_('FLEXI_LISTING_RECORDS').': </span>'.$this->lists['filter_stategrp']; ?></span>
					<?php endif; ?>
	
					<div class='fc_mini_note_box' style='float:right; clear:both!important;'>
					<span class="radio flexi_tabbox" style="margin-left:60px;"><?php echo '<span class="flexi_tabbox_label">'.JText::_('FLEXI_LISTING_RECORDS').': </span>'.$this->lists['filter_indextype']; ?></span>
					</div>
	
	<!--
					<span style="float:right;">
						<input type="button" class="button" onclick="delAllFilters();this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
						<input type="button" class="button submitbutton" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
						
						<input type="button" class="button" id="hide_filters" value="<?php echo JText::_( 'FLEXI_HIDE_FILTERS' ); ?>" />
						<input type="button" class="button" id="show_filters" value="<?php echo JText::_( 'FLEXI_DISPLAY_FILTERS' ); ?>" />
					</span>
	-->
				</td>
			</tr>


		</thead>
		<tbody>

			
			<?php if (count($this->data) == 0): ?>
			<tr class="row0">
				<td align="center" colspan="9">
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
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
					<?php
						$link = 'index.php?option=com_flexicontent&'.$items_task.'edit&cid[]='. $row->id;
						echo '<a target="_blank" href="'.$link.'">'.$this->escape($row->title).'</a>';
					?>
					</span>
				</td>
				<td>
					<?php echo $this->escape($row->label); ?>
				</td>
				<td>
					<?php echo $this->escape($row->name); ?>
				</td>
				<td nowrap="nowrap" style="text-align: center" class="col_fieldtype">
					<?php echo $row->field_type; ?>
				</td>
				<td nowrap="nowrap" style="text-align: center;">
					<?php echo $row->extraid; ?>
				</td>
				<td style="text-align: left;" class="col_search_index">
					<?php
						if(iconv_strlen($row->search_index, "UTF-8")>400)
							echo iconv_substr($row->search_index, 0, 400, "UTF-8").'...';
						else
							echo $row->search_index;
					?>
				</td>
				<td nowrap="nowrap" style="text-align: center;">
					<?php echo $row->value_id; ?>
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
				<td colspan="9" nowrap="nowrap">
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
