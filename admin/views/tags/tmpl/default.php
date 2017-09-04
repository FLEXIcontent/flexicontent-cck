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

$tip_class = ' hasTooltip';
$btn_class = 'btn';  //'fc_button fcsimple';

$ico_class   = 'fc-man-icon-s';
$btn_s_class = 'btn btn-small';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCtags', $start_text, $end_text);

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );

$list_total_cols = 7;


// *********************
// COMMON repeated texts
// *********************

$edit_entry = JText::_('FLEXI_EDIT_TAG', true);
$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');


// *****
// ICONS
// *****

$attribs_preview    = ' class="fc-preview-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_rsslist    = ' class="fc-rss-list-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_FEED_RSS', 'FLEXI_DISPLAY_RSS_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_editlayout = ' class="fc-edit-layout-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_preview = 0
	? JHtml::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'), ' class="'.$ico_class.'"')
	: '<span class="icon-screen"></span>';
$image_rsslist = 0
	? JHtml::image( FLEXI_ICONPATH.'livemarks.png', JText::_('FLEXI_FEED'), ' class="'.$ico_class.'"')
	: '<span class="icon-feed"></span>';

$image_editlayout = 0 ?
	JHtml::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"') :
	'<span class="'.$ico_class.'"><span class="icon-edit"></span></span>' ;

$fcfilter_attrs_row = ' class="input-prepend fc-xpended-row" ';
$tools_cookies['fc-filters-box-disp'] = JFactory::getApplication()->input->cookie->get('fc-filters-box-disp', 0, 'int');
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
	delFilter('search'); delFilter('filter_assigned');  delFilter('filter_state');
	delFilter('filter_order');  delFilter('filter_order_Dir');
}

</script>

<div id="flexicontent" class="flexicontent">

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

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['assigned']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['state']; ?>
			</div>
		</div>

		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
  
	<table id="adminListTableFCtags" class="adminlist fcmanlist">
	<thead>
		<tr class="header">
			<th class="center hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="left">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>
			<th class="hideOnDemandClass left"><?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 't.published', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass title"><?php echo JHtml::_('grid.sort', 'FLEXI_TITLE' /*'FLEXI_TAG_NAME'*/, 't.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass alias hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 't.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center"><?php echo JHtml::_('grid.sort', 'FLEXI_ASSIGNED_TO', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center hidden-tablet hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_ID', 't.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$edit_task = 'task=tags.';
		$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;fcform=1&amp;filter_tag=';
		$use_catlinks = $cparams->get('tags_using_catview', 0);

		$k = 0;
		
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row  = & $this->rows[$i];
			$row->language = !empty($row->language) ? $row->language : '*';

			$link = 'index.php?option=com_flexicontent&amp;'.$edit_task.'edit&amp;cid='. $row->id;

			$canEdit    = 1;
			$canEditOwn = 1;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="center hidden-phone">
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			<td>
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>
			<td class="col_state" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-tags">
					<?php
					$record_url =
						// Route the record URL to an appropriate menu item
						($use_catlinks
							? FlexicontentHelperRoute::getCategoryRoute(0, 0, array('layout'=>'tags','tagid'=>$row->slug), $row)
							: FlexicontentHelperRoute::getTagRoute($row->slug)
						)

						// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
						. ( $row->language != '*' ? '&lang=' . substr($row->language, 0, 2) : '' );

					// Build a frontend SEF url
					$record_url = flexicontent_html::getSefUrl($record_url);

					$previewlink = $record_url;
					$rsslink = $record_url  . (strstr($record_url, '?') ? '&amp;' : '?') . 'format=feed&amp;type=rss';

					//echo JHtml::_('jgrid.published', $row->published, $i, 'tags.', $canChange);
					//echo JHtml::_('fctags.published', $row->published, $i, $canChange);

					echo flexicontent_html::statebutton( $row, null, $addToggler = ($this->pagination->limit <= $this->inline_ss_max), 'top', 'btn btn-small', array('controller'=>'tags', 'state_propname'=>'published') );
					echo (!$use_catlinks ? '' : '<a '.$attribs_rsslist.' href="'.$rsslink.'" target="_blank">'.$image_rsslist.'</a>');
					echo '<a '.$attribs_preview.' href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
					?>
				</div>
			</td>
			<td class="title">
				<?php
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'tags.', $canCheckin);
						$task_str = 'tags.checkin';
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
					echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
				
				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<a href="<?php echo $link; ?>" title="<?php echo $edit_entry; ?>">
						<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php
				}
				?>
			</td>
			<td class="alias hidden-phone">
				<?php
				if (StringHelper::strlen($row->alias) > 25) {
					echo StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td class="center">
				<a class="btn btn-small fc-assignments-btn" href="<?php echo $items_link.$row->id; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>">
					<?php echo $row->nrassigned ? $row->nrassigned : 0; ?>
				</a>
			</td>
			<td class="center hidden-tablet hidden-phone"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
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
	<input type="hidden" name="controller" value="tags" />
	<input type="hidden" name="view" value="tags" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>

		<!-- fc_perf -->
		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</form>
</div><!-- #flexicontent end -->