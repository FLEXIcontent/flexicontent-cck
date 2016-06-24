<?php
/**
 * @version 1.5 stable $Id: default.php 1803 2013-11-05 03:10:36Z ggppdk $
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

$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCsearch'.($this->isADV ? '_advanced' : '_basic'), $start_text, $end_text);

$edit_entry = JText::_('FLEXI_EDIT_TYPE', true);
$list_total_cols = $this->isADV ? 10 : 5;
?>

<script type="text/javascript">

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
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);
	if (filter.attr('type')=='checkbox')
		filter.checked = '';
	else
		filter.val('');
}

function delAllFilters() {
	delFilter('filter_fieldtype'); delFilter('filter_itemtype'); delFilter('filter_itemstate');
	delFilter('search'); delFilter('search_itemtitle'); delFilter('search_itemid');
	delFilter('filter_order'); delFilter('filter_order_Dir');
}

</script>

<div class="flexicontent">

<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

	<div id="fc-filters-header">
		<span class="btn-group input-append fc-filter filter-search filter-search">
			<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
			<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
		</span>
		
		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<span class="btn-group input-append fc-filter">
			<input type="button" id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_FILTERS' ); ?>" />
			<input type="button" id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_COLUMNS' ); ?>" />
		</span>
		
		<span class="fc-filter nowrap_box">
			<span class="limit nowrap_box">
				<?php
				$pagination_footer = $this->pagination->getListFooter();
				if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
				?>
			</span>
			
			<span class="fc_item_total_data nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
			</span>
			
			<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
			<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $getPagesCounter; ?>
			</span>
			<?php endif; ?>
		</span>
	</div>
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->
		
		<span class="fc-filter nowrap_box">
			<?php echo '<label class="label'.($this->f_active['search_itemtitle'] ? " highlight":"").'">'.JText::_('FLEXI_TITLE').'</label> '; ?>
			<input type="text" name="search_itemtitle" id="search_itemtitle" value="<?php echo $this->lists['search_itemtitle']; ?>" class="text_area" onchange="document.adminForm.submit();" size="30"/>
		</span>

		<span class="fc-filter nowrap_box">
			<?php echo '<label class="label'.($this->f_active['search_itemid'] ? " highlight":"").'">'.JText::_('FLEXI_ID').'</label> '; ?>
			<input type="text" name="search_itemid" id="search_itemid" value="<?php echo $this->lists['search_itemid']; ?>" class="text_area" onchange="document.adminForm.submit();" size="6" />
		</span>
			
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_itemtype']; ?>
		</span>
			
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_itemstate']; ?>
		</span>
		
		<?php if ($this->isADV) : ?>
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_fieldtype']; ?>
		</span>
		<?php endif; ?>
		
		<div class="icon-arrow-up-2" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	<?php echo '<span class="label">'.JText::_('FLEXI_LISTING_RECORDS').': </span>'.$this->lists['filter_indextype']; ?>
	<div class="fcclear"></div>
	
	<table id="adminListTableFCsearch<?php echo $this->isADV ? '_advanced' : '_basic'; ?>" class="adminlist fcmanlist">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_ITEM_ID'), 'a.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left title"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_ITEM_TITLE'), 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			
			<?php if ($this->isADV) : ?>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_LABEL'), 'f.label', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_NAME'), 'f.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_FIELD_TYPE'), 'f.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_COUNT'), 'ai.extraid', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_ID'), 'ai.value_id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<?php endif; ?>
			
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', JText::_('FLEXI_SEARCH_INDEX'), ($this->isADV ? 'ai' : 'ext').'.search_index', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>
		
	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
	</tfoot>
	
	<tbody>
		
		<?php if (count($this->rows) == 0): ?>
		<tr class="row0">
			<td class="center" colspan="<?php echo $list_total_cols; ?>">
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
		foreach ($this->rows as $row): ?>
		<tr class="<?php echo 'row', $o; ?>">
			<td>
				<?php echo $this->pagination->getRowOffset( $n ); ?>
			</td>
			<td>
				<?php echo JHTML::_('grid.id', $n, ($this->isADV ? $row->field_id.'|' : '').$row->item_id); ?>
			</td>
			<td>
				<?php echo $row->item_id; ?>
			</td>
			<td>
				<?php echo '<a target="_blank" href="index.php?option=com_flexicontent&amp;task=items.edit&amp;cid='.$row->id.'" title="'.$edit_entry.'">'.$this->escape($row->title).'</a>'; ?>
			</td>
			
			<?php if ($this->isADV) : ?>
				<td>
					<?php echo $this->escape($row->label); ?>
				</td>
				<td>
					<?php echo $this->escape($row->name); ?>
				</td>
				<td class="col_fieldtype">
					<?php echo $row->field_type; ?>
				</td>
				<td class="center">
					<?php echo $row->extraid; ?>
				</td>
				<td class="center">
					<?php echo $row->value_id; ?>
				</td>
			<?php endif; ?>
		
			<td class="left col_search_index">
				<?php
					$_search_index = !$search_prefix ? $row->search_index : preg_replace('/\b'.$search_prefix.'/u', '', $row->search_index);
					if(iconv_strlen($row->search_index, "UTF-8")>400)
						echo iconv_substr($_search_index, 0, 400, "UTF-8").'...';
					else
						echo $_search_index;
				?>
			</td>
			<?php /*<td class="center">
				<?php //echo JHtml::date($row->indexdate, '%Y-%m-%d %H:%M:%S'); ?>
			</td>*/ ?>
		</tr>

		<?php $n++; $o = ++$o % 2; ?>
		<?php endforeach; ?>
	</tbody>
	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="search" />
	<input type="hidden" name="view" value="search" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHTML::_( 'form.token' ); ?>

	<!-- fc_perf -->
	</div>
</form>
</div>