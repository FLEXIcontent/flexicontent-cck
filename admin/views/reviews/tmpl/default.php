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

use Joomla\String\StringHelper;

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCreviews', $start_text, $end_text);

$edit_entry = JText::_('FLEXI_EDIT_REVIEW', true);

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$autologin = '';//$cparams->get('autoflogin', 1) ? '&amp;fcu='.$user->username . '&amp;fcp='.$user->password : '';

$attribs_preview = ' class="fc-man-icon-s '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$image_preview = JHTML::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'),  $attribs_preview);

$list_total_cols = 7;
?>
<script type="text/javascript">

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
	delFilter('search'); delFilter('filter_state');
	delFilter('filter_order');  delFilter('filter_order_Dir');
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
		<span class="btn-group input-append filter-search fc-filter">
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
			<?php echo $this->lists['state']; ?>
		</span>
		
		<div class="icon-arrow-up-2" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
  
	<table id="adminListTableFCreviews" class="adminlist fcmanlist">
	<thead>
		<tr class="header">
			<th class="center hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="center"><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th class="center hidden-phone"></th>
			<th class="hideOnDemandClass title"><?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'r.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center"><?php echo JHTML::_('grid.sort', 'FLEXI_STATE', 'r.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center"><?php echo JHTML::_('grid.sort', 'FLEXI_REVIEW_APPROVED', 'r.approved', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center hidden-tablet hidden-phone"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'r.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
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
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'checkin');
		$edit_task = FLEXI_J16GE ? 'task=reviews.' : 'controller=reviews&amp;task=';
		$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;fcform=1&amp;filter_id=';
		
		$k = 0;
		
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row  = & $this->rows[$i];
			$link = 'index.php?option=com_flexicontent&amp;'.$edit_task.'edit&amp;cid='. $row->id;
			$published	= JHTML::_('jgrid.published', $row->state, $i, 'reviews.' );
			
			$checked 	= @ JHTML::_('grid.checkedout', $row, $i );
			$canEdit    = 1;
			$canEditOwn = 1;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="center hidden-phone"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td class="center"><?php echo $checked; ?></td>
			<td class="center hidden-phone">
				<?php
				if ($row->type=='item') {
					$review_link = str_replace('&', '&amp;', FlexicontentHelperRoute::getItemRoute($row->content_id));
					$review_link    = JRoute::_(JURI::root().$review_link, $xhtml=false)."#review_".$row->id;  // xhtml to false we do it manually above (at least the ampersand) also it has no effect because we prepended the root URL ?
					$previewlink = $review_link . $autologin;
				}
				else $previewlink="javascript:;";
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
				?>
			</td>
			<td class="title">
				<?php
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'reviews.', $canCheckin);
						$task_str = 'reviews.checkin';
						if ($row->checked_out == $user->id) {
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time);
						} else {
							echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">';
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);
						}
						?>
						<a class="btn btn-micro <?php echo $tip_class; ?>" title="<?php echo $_tip_title; ?>" href="javascript:;" onclick="var ccb=document.getElementById('cb<?php echo $i;?>'); ccb.checked=1; ccb.form.task.value='<?php echo $task_str; ?>'; ccb.form.submit();">
							<span class="icon-checkedout"></span>
						</a>
						<?php
					} else {
						echo '<span class="fc-noauth">'.JText::sprintf('FLEXI_RECORD_CHECKED_OUT_DIFF_USER').'</span><br/>';
					}
				}
				
				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit ) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
				
				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<a href="<?php echo $link; ?>" title="<?php echo $edit_entry; ?>">
						<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php
				}
				?>
			</td>
			<td class="center">
				<?php echo $published; ?>
			</td>
			<td class="center">
				<?php echo $row->approved ? 'Not approved' : 'Approved'; ?>
			</td>
			<td class="center hidden-tablet hidden-phone"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>

	</table>
	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="reviews" />
	<input type="hidden" name="view" value="reviews" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHTML::_( 'form.token' ); ?>

		<!-- fc_perf -->
	</div>  <!-- sidebar -->
</form>
</div><!-- #flexicontent end -->