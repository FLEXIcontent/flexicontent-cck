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

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCgroups', $start_text, $end_text);

$edit_entry = JText::_('FLEXI_EDIT', true);
$view_entry = JText::_('FLEXI_VIEW', true);

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
//JHtml::_('behavior.tooltip');
JHtml::_('behavior.multiselect');

$user    = JFactory::getUser();
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$list_total_cols = 6;

JText::script('COM_USERS_GROUPS_CONFIRM_DELETE');
$tools_cookies['fc-filters-box-disp'] = JFactory::getApplication()->input->cookie->get('fc-filters-box-disp', 0, 'int');
?>
<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'groups.delete')
		{
			if (document.adminForm.boxchecked.value != 0)
			{
				if (confirm(Joomla.JText._('COM_USERS_GROUPS_CONFIRM_DELETE')))
				{
					Joomla.submitform(task);
				}
				return;
			}
		}
		Joomla.submitform(task);
	}
</script>

<div class="flexicontent">

<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div id="j-sidebar-container" class="span2 col-md-2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div class="span10 col-md-10">
		<div id="j-main-container">
<?php else : ?>
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
	<div class="span12 col-md-12">
		<div id="j-main-container">
<?php endif;?>

	<div id="fc-filters-header">
		<span class="btn-group input-append fc-filter filter-search">
			<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
			<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
		</span>
		
		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<span class="btn-group fc-filter">
			<span id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);"><?php echo JText::_( 'FLEXI_FILTERS' ) . ($this->count_filters  ? ' <sup>'.$this->count_filters.'</sup>' : ''); ?></span>
			<span id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');"><?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup></span>
		</span>
		<input type="hidden" id="fc-filters-box-disp" name="fc-filters-box-disp" value="<?php echo $tools_cookies['fc-filters-box-disp']; ?>" />
		
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
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_cookies['fc-filters-box-disp']) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->
		
		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>

	<table id="adminListTableFCgroups" class="adminlist fcmanlist" style="min-width: 400px;">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th>
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>
			<th class="hideOnDemandClass left">
				<?php echo JText::_('COM_USERS_HEADING_GROUP_TITLE'); ?>
			</th>
			<th class="hideOnDemandClass center" colspan="2">
				<?php echo JText::_('COM_USERS_HEADING_USERS_IN_GROUP'); ?>
			</th>
			<th class="hideOnDemandClass">
				<?php echo JText::_('JGRID_HEADING_ID'); ?>
			</th>
		</tr>
		
	</thead>

	<tbody>
	<?php foreach ($this->items as $i => $item) :
		$canCreate	= $user->authorise('core.create',		'com_users');
		$canEdit	= $user->authorise('core.edit',			'com_users');
		// If this group is super admin and this user is not super admin, $canEdit is false
		if (!$user->authorise('core.admin') && (JAccess::checkGroup($item->id, 'core.admin'))) {
			$canEdit = false;
		}
		$canChange	= $user->authorise('core.edit.state',	'com_users');
	?>
		<tr class="row<?php echo $i % 2; ?>">
			<td>
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			<td>
				<?php echo JHtml::_('grid.id', $i, $item->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>
			<td>
				<?php echo str_repeat('<span class="gi">|&mdash;</span>', $item->level) ?>
				<?php if ($canEdit) : ?>
				<a href="<?php echo JRoute::_('index.php?option=com_flexicontent&task=group.edit&id='.$item->id);?>">
					<?php echo $this->escape($item->title); ?></a>
				<?php else : ?>
					<?php echo $this->escape($item->title); ?>
				<?php endif; ?>
				<?php if (JDEBUG) : ?>
					<div class="fltrt"><div class="button2-left smallsub"><div class="blank"><a href="<?php echo JRoute::_('index.php?option=com_flexicontent&view=debuggroup&group_id='.(int) $item->id);?>">
					<?php echo JText::_('COM_USERS_DEBUG_GROUP');?></a></div></div></div>
				<?php endif; ?>
			</td>
			<td class="right" style="padding:0px;">
				<?php echo '<span class="badge badge-info">'.$item->user_count.'</span>'; ?>
			</td>
			<td class="left">
				<?php
				if ($item->user_count) {
					echo '
					<a onclick="delAllFilters();"  href="index.php?option=com_flexicontent&amp;view=users&amp;filter_usergrp='.$item->id.'">
					['.$view_entry.']
					</a>';
				}
				?>
			</td>
			<td class="center">
				<?php echo (int) $item->id; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
	</tfoot>

	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="groups" />
	<input type="hidden" name="view" value="groups" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
	<?php echo JHtml::_('form.token'); ?>

		<!-- fc_perf -->
		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</form>
</div><!-- #flexicontent end -->