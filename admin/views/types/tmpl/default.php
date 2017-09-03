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
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCtypes', $start_text, $end_text);

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );

$list_total_cols = 12;


// *********************
// COMMON repeated texts
// *********************

$edit_entry  = JText::_('FLEXI_EDIT_TYPE', true);
$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');
$view_fields = JText::_('FLEXI_VIEW', true); //JText::_('FLEXI_VIEW_FIELDS', true);
$view_items  = JText::_('FLEXI_VIEW', true); //JText::_('FLEXI_VIEW_ITEMS', true);


// *****
// ICONS
// *****

$attribs_preview    = ' class="fc-preview-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_rsslist    = ' class="fc-rss-list-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_FEED_RSS', 'FLEXI_DISPLAY_RSS_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_editlayout = ' class="fc-edit-layout-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_preview = JHtml::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'), ' class="'.$ico_class.'"');
$image_rsslist = JHtml::image( FLEXI_ICONPATH.'livemarks.png', JText::_('FLEXI_FEED'), ' class="'.$ico_class.'"');
$image_editlayout = 0 ?
	JHtml::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"') :
	'<span class="'.$ico_class.'"><span class="icon-edit"></span></span>' ;

$fcfilter_attrs_row  = ' class="input-prepend fc-xpended-row" ';
$article_viewing_tip  = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" data-placement="bottom" alt="'.JText::_('FLEXI_JOOMLA_ARTICLE_VIEW', true).'" title="'.flexicontent_html::getToolTip('FLEXI_JOOMLA_ARTICLE_VIEW', 'FLEXI_ALLOW_ARTICLE_VIEW_DESC', 1, 1).'" /> ';
$default_template_tip = '<img src="components/com_flexicontent/assets/images/comments.png" class="fc-man-icon-s '.$tip_class.'" data-placement="bottom" alt="'.JText::_( 'FLEXI_TYPE_DEFAULT_TEMPLATE', true ).'" title="'.flexicontent_html::getToolTip('FLEXI_TYPE_DEFAULT_TEMPLATE', 'FLEXI_TYPE_DEFAULT_TEMPLATE_DESC', 1, 1).'" /> ';
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
	delFilter('search'); delFilter('filter_state');  delFilter('filter_access');
	delFilter('filter_order'); delFilter('filter_order_Dir');
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
				<?php echo $this->lists['state']; ?>
			</div>
		</div>

		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['access']; ?>
			</div>
		</div>

		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
  
	<table id="adminListTableFCtypes" class="adminlist fcmanlist">
	<thead>
		<tr class="header">
			<th class="hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="left">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>

			<th class="hideOnDemandClass title"><?php echo JHtml::_('grid.sort', 'FLEXI_TYPE_NAME', 't.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass col_redirect hidden-tablet hidden-phone"><?php echo $article_viewing_tip . JText::_( 'FLEXI_JOOMLA_ARTICLE_VIEW' )."<br/><small>(" . JText::_( 'FLEXI_ALLOWED') .' / '. JText::_( 'FLEXI_REROUTED' ) .' / '. JText::_( 'FLEXI_REDIRECTED' ) . ")</small>"; ?></th>
			<th class="hideOnDemandClass hidden-phone" colspan="2"><?php echo $default_template_tip.JText::_( 'FLEXI_TEMPLATE' )."<br/><small>(".JText::_( 'FLEXI_PROPERTY_DEFAULT' )." ".JText::_( 'FLEXI_TEMPLATE_ITEM' ).")</small>"; ?></th>
			<th class="hideOnDemandClass hidden-tablet hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 't.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELDS', 'fassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHtml::_('grid.sort', 'FLEXI_ITEMS', 'iassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<!--th class="hideOnDemandClass"><?php // echo JHtml::_('grid.sort', 'ITEMS', 'iassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th-->
			<th class="hideOnDemandClass"><?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 't.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass center hidden-phone"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th class="hideOnDemandClass center hidden-tablet hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_ID', 't.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		
		$k = 0;
		
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row = & $this->rows[$i];
			$link 		= 'index.php?option=com_flexicontent&amp;task=types.edit&amp;view=type&amp;id='. $row->id;
			$published 	= JHtml::_('jgrid.published', $row->published, $i, 'types.' );
			$access		= flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'types.access\')" class="use_select2_lib fc_skip_highlight"');

			$fields_url = 'index.php?option=com_flexicontent&amp;view=fields&amp;filter_type='. $row->id;
			$items_url  = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_type='. $row->id;
			$layout_url = 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder='. $row->config->get("ilayout");
			$canEdit    = 1;
			$canEditOwn = 1;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="hidden-phone">
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			<td>
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>
			<td class="left">
				<?php
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'types.', $canCheckin);
						$task_str = 'types.checkin';
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
			<td class="col_redirect hidden-tablet hidden-phone">
				<?php
					$jarticle_url_handling_txts = array(0 => 'FLEXI_ROUTE_TO_ITEM_VIEW', 1 => 'FLEXI_ALLOWED', '2' => 'FLEXI_REDIRECT_TO_ITEM_VIEW');
					$allow_jview = $row->config->get("allow_jview");

					$jview_ops = array();
					$jview_ops[] = JHtml::_('select.option', '1', 'FLEXI_ALLOWED');
					$jview_ops[] = JHtml::_('select.option', '0', 'FLEXI_ROUTE_TO_ITEM_VIEW');
					$jview_ops[] = JHtml::_('select.option', '2', 'FLEXI_REDIRECT_TO_ITEM_VIEW');

					echo JHtml::_('select.genericlist', $jview_ops, 'allow_jview['.$row->id.']', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="listItemTask(\'cb'.$i.'\',\'types.toggle_jview\'); Joomla.submitform()"', 'value', 'text', $allow_jview, 'allow_jview'.$row->id, $translate=true);
				?>
			</td>
			
			<td class="hidden-phone col_edit_layout">
				<?php if ($this->CanTemplates) : ?>
				<a <?php echo $attribs_editlayout; ?> href="<?php echo $layout_url; ?>" onclick="var url = jQuery(this).attr('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'<?php echo $edit_layout; ?>'}); return false;" >
					<?php echo $image_editlayout;?>
				</a>
				<?php endif; ?>
			</td>
			<td class="hidden-phone col_template">
				<?php echo $row->config->get("ilayout"); ?>
			</td>
			
			<td class="hidden-tablet hidden-phone">
				<?php
				if (StringHelper::strlen($row->alias) > 25) {
					echo StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td class="right">
				<a class="btn btn-small fc-assignments-btn" href="<?php echo $fields_url; ?>">
					<?php echo $row->fassigned; ?>
				</a>
			</td>
			<td class="right">
				<a class="btn btn-small btn-info fc-assignments-btn" href="<?php echo $items_url; ?>">
					<?php echo $row->iassigned; ?>
				</a>
			</td>
			<td>
				<?php echo $access; ?>
			</td>
			<td class="center hidden-phone">
				<?php echo $published; ?>
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
	<input type="hidden" name="controller" value="types" />
	<input type="hidden" name="view" value="types" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHtml::_( 'form.token' ); ?>
	
		<!-- fc_perf -->
		</div>  <!-- j-main-container -->
	</div>  <!-- spanNN -->
</div>  <!-- row -->
</form>
</div><!-- #flexicontent end -->