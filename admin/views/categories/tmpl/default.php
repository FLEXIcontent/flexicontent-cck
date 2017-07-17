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
JHtml::addIncludePath(JPATH_COMPONENT . '/helpers/html');
$isAdmin = JFactory::getApplication()->isAdmin();
global $globalcats;

$tip_class = ' hasTooltip';
$btn_class = 'btn';  //'fc_button fcsimple';

$ico_class   = 'fc-man-icon-s';
$btn_s_class = 'btn btn-small';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCcats', $start_text, $end_text);

$listOrder  = $this->lists['order'];
$listDirn   = $this->lists['order_Dir'];
$saveOrder  = ($listOrder == 'c.lft' && strtolower($listDirn) == 'asc');

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );

$list_total_cols = 15;


// *********************
// COMMON repeated texts
// *********************

$edit_entry = JText::_('FLEXI_EDIT_CATEGORY', true);
$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');


// *****
// ICONS
// *****

$fcfilter_attrs_row = ' class="input-prepend fc-xpended-row" ';
$attribs_preview    = ' class="fc-preview-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_rsslist    = ' class="fc-rss-list-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_FEED_RSS', 'FLEXI_DISPLAY_RSS_IN_FRONTEND_DESC', 1, 1).'" ';
$attribs_editlayout = ' class="fc-edit-layout-btn ntxt '.$btn_s_class.' '.$tip_class.'" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_preview = 0
	? JHTML::image( 'components/com_flexicontent/assets/images/'.'monitor_go.png', JText::_('FLEXI_PREVIEW'), ' class="'.$ico_class.'"')
	: '<span class="icon-screen"></span>';
$image_rsslist = 0
	? JHTML::image( FLEXI_ICONPATH.'livemarks.png', JText::_('FLEXI_FEED'), ' class="'.$ico_class.'"')
	: '<span class="icon-feed"></span>';

$image_editlayout = 0 ?
	JHTML::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"') :
	'<span class="'.$ico_class.'"><span class="icon-edit"></span></span>' ;

$image_flag_path = !FLEXI_J16GE ? "../components/com_joomfish/images/flags/" : "../media/mod_languages/images/";
$infoimage  = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), ' class="fc-man-icon-s" ' );

$img_path = '../components/com_flexicontent/assets/images/';
$state_names = array('ALL_P'=>JText::_('FLEXI_PUBLISHED'), 'ALL_U'=>JText::_('FLEXI_UNPUBLISHED'), 'A'=>JText::_('FLEXI_ARCHIVED'), 'T'=>JText::_('FLEXI_TRASHED'));
$state_imgs = array('ALL_P'=>'tick.png', 'ALL_U'=>'publish_x.png', 'A'=>'archive.png', 'T'=>'trash.png');
$state_icons = array('ALL_P'=>'publish', 'ALL_U'=>'unpublish', 'A'=>'archive', 'T'=>'trash');

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
	delFilter('search'); delFilter('filter_state'); delFilter('filter_cats');
	delFilter('filter_level'); delFilter('filter_access'); delFilter('filter_language');
	delFilter('filter_id');
	delFilter('filter_order'); delFilter('filter_order_Dir');
}

</script>

<div id="flexicontent" class="flexicontent">

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
				<?php echo $this->lists['cats']; ?>
			</div>
		</div>
		
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<?php echo $this->lists['level']; ?>
			</div>
		</div>
		
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
		
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
			  <?php echo $this->lists['language']; ?>
			</div>
		</div>
		
		<div class="fc-filter nowrap_box">
			<div <?php echo $fcfilter_attrs_row; ?> >
				<div class="add-on"><?php echo JText::_('FLEXI_ID'); ?></div>
				<input type="text" name="filter_id" id="filter_id" size="6" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" style="width:auto;" />
			</div>
		</div>
		
		<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
	<table id="adminListTableFCcats" class="adminlist fcmanlist">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="left">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>
			<th class="hideOnDemandClass left"><?php echo JHTML::_('grid.sort', 'FLEXI_STATUS', 'c.published', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass title"><?php echo JHTML::_('grid.sort', 'FLEXI_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ALIAS', 'c.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass left" colspan="2"><?php echo JText::_( 'FLEXI_TEMPLATE' ); ?></th>
			<!--th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th-->
			<th class="hideOnDemandClass left">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge"><?php echo $state_names['ALL_P']; ?></small></span>
				<?php echo '<span class="'.$tip_class.' icon-'.$state_icons['ALL_P'].'" title="'.$state_names['ALL_P'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top"></span>'; ?>
			</th>
			<th class="hideOnDemandClass left">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge"><?php echo $state_names['ALL_U']; ?></small></span>
				<?php echo '<span class="'.$tip_class.' icon-'.$state_icons['ALL_U'].'" title="'.$state_names['ALL_U'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top"></span>'; ?>
			</th>
			<th class="hideOnDemandClass left">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge"><?php echo $state_names['A']; ?></small></span>
				<?php echo '<span class="'.$tip_class.' icon-'.$state_icons['A'].'" title="'.$state_names['A'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top"></span>'; ?>
			</th>
			<th class="hideOnDemandClass left">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge"><?php echo $state_names['T']; ?></small></span>
				<?php echo '<span class="'.$tip_class.' icon-'.$state_icons['T'].'" title="'.$state_names['T'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top"></span>'; ?>
			</th>
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'c.lft', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->orderingx ? str_replace('rel="tooltip"', '', JHTML::_('grid.order', $this->rows, 'filesave.png', 'categories.saveorder' )) : ''; ?>
			</th>
			<th class="hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_LANGUAGE', 'language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th class="hideOnDemandClass">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'c.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		
		$originalOrders = array();
		$extension	= 'com_content';
		
		$k = 0;
		$i = 0;
		$clayout_bycatid = array();
		$cat_ancestors = array();
		$inheritcid_comp = $cparams->get('inheritcid', -1);
		
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		foreach ($this->rows as $row)
		{
			$row->canEdit    = $user->authorise('core.edit', $extension.'.category.'.$row->id);
			$row->canEditOwn = $user->authorise('core.edit.own', $extension.'.category.'.$row->id) && $row->created_user_id == $user->get('id');
			$row->canEditState    = $user->authorise('core.edit.state', $extension.'.category.'.$row->id);
			$row->canEditStateOwn	= $user->authorise('core.edit.state.own', $extension.'.category.'.$row->id) && $row->created_user_id==$user->get('id');
			$recordAvailable	= ($canCheckinRecords && $row->checked_out == $user->id) || !$row->checked_out;
			$canChange  = ($row->canEditState || $row->canEditStateOwn ) && $recordAvailable;
			
			$orderkey = array_search($row->id, $this->ordering[$row->parent_id]);
			$link	= 'index.php?option=com_flexicontent&amp;task=category.edit&amp;cid='. $row->id;
			
			$inheritcid = $row->config->get('inheritcid', '');
			$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
			
			if (!$inherit_parent || $row->parent_id==='1')
				$row_clayout = $row->config->get('clayout', $cparams->get('clayout', 'blog'));
			else {
				$row_clayout = $row->config->get('clayout', '');
				
				if (!$row_clayout)
				{
					if (isset($clayout_bycatid[$row->parent_id])) {
						$row_clayout = $clayout_bycatid[$row->parent_id];
					}
					else
					{
						$_ancestors = $this->getModel()->getParentParams($row->id);  // This is ordered by level ASC
						$row_clayout = $cparams->get('clayout', 'blog');
						foreach($_ancestors as $_cid => $_cat)
						{
							if (!isset($cats_params[$_cid]))
							{
								$cats_params[$_cid] = new JRegistry($_cat->params);
							}
							$row_clayout = $cats_params[$_cid]->get('clayout', '') ? $cats_params[$_cid]->get('clayout', '') : $row_clayout;
							$clayout_bycatid[$_cid] = $row_clayout;
						}
					}
				}
			}
			$clayout_bycatid[$row->id] = $row_clayout;
			
			$layout_url = 'index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;tmpl=component&amp;ismodal=1&amp;folder='. $row_clayout;
			
			if (($row->canEdit || $row->canEditOwn) && $this->perms->CanAccLvl) {
				$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'categories.access\')"');
			} else {
				$access = $this->escape($row->access_level);
			}

			$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;filter_cats='. $row->id.'&amp;fcform=1&amp;filter_state=';
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td>
				<div class="adminlist-table-row"></div>
				<?php echo $this->pagination->getRowOffset( $i ); ?>
			</td>
			<td>
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>

			<td class="col_state" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-cats">
					<?php
					$record_url =
						// Route the record URL to an appropriate menu item
						FlexicontentHelperRoute::getCategoryRoute($globalcats[$row->id]->slug, 0, array(), $row)

						// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
						. ( $row->language != '*' ? '&lang=' . substr($row->language, 0, 2) : '' );

					// Build a frontend SEF url
					$record_url = flexicontent_html::getSefUrl($record_url);

					$previewlink = $record_url;
					$rsslink = $record_url . (strstr($record_url, '?') ? '&amp;' : '?') . 'format=feed&amp;type=rss';

					//echo JHTML::_('jgrid.published', $row->published, $i, 'categories.', $canChange);
					//echo JHtml::_('fccats.published', $row->published, $i, $canChange);

					echo flexicontent_html::statebutton( $row, null, $addToggler = ($this->pagination->limit <= $this->inline_ss_max), 'top', 'btn btn-small', array('controller'=>'categories', 'state_propname'=>'published') );
					echo '<a '.$attribs_rsslist.' href="'.$rsslink.'" target="_blank">'.$image_rsslist.'</a>';
					echo '<a '.$attribs_preview.' href="'.$previewlink.'" target="_blank">'.$image_preview.'</a>';
					?>
				</div>
			</td>

			<td style="text-align:left;" class="col_title">
				<?php
				if ($row->level>1) echo str_repeat('.&nbsp;&nbsp;&nbsp;', $row->level-1)."<sup>|_</sup>";
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'categories.', $canCheckin);
						$task_str = 'categories.checkin';
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
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$row->canEdit && !$row->canEditOwn ) ) {
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
				
				<?php	if (!empty($row->note)) : /* Display J1.6+ category note in a tooltip */ ?>
					<span class="<?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip( JText::_ ('FLEXI_NOTES'), $row->note, 0, 1); ?>">
						<?php echo $infoimage; ?>
					</span>
				<?php endif; ?>
				
			</td>
			
			<td>
				<?php
				if (StringHelper::strlen($row->alias) > 25) {
					echo StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td class="col_edit_layout">
				<?php if ($this->CanTemplates && $row_clayout) : ?>
				<a <?php echo $attribs_editlayout; ?> href="<?php echo $layout_url; ?>" onclick="var url = jQuery(this).attr('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'<?php echo $edit_layout; ?>'}); return false;" >
					<?php echo $image_editlayout;?>
				</a>
				<?php endif; ?>
			</td>
			<td class="col_template">
				<?php echo $row->config->get('clayout') ? $row->config->get('clayout') : ($row_clayout ? $row_clayout : '...').'<span class="badge">inherited</span>'; ?>
			</td>
			
			<?php /*<td>
				<a href="<?php echo $items; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset;">
					<span class="badge badge-info"><?php echo $row->nrassigned; ?></span>
				</a>
			</td>*/ ?>
			
			<td>
				<a href="<?php echo $items_link.'ALL_P'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset; display:inline-block;">
					<span class="badge badge-success"><?php $c = (int)@$row->byStateTotals[1] + (int)@$row->byStateTotals[-5]; echo $c ? $c : '.'; ?></span>
				</a>
			</td>
			<td>
				<a href="<?php echo $items_link.'ALL_U'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset; display:inline-block;">
					<span class="badge badge-warning"><?php $c = (int)@$row->byStateTotals[0] + (int)@$row->byStateTotals[-3] + (int)@$row->byStateTotals[-4]; echo $c ? $c : '.'; ?></span>
				</a>
			</td>
			<td>
				<a href="<?php echo $items_link.'A'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset; display:inline-block;">
					<span class="badge badge-info"><?php $c = (int)@$row->byStateTotals[2]; echo $c ? $c : '.'; ?></span>
				</a>
			</td>
			<td>
				<a href="<?php echo $items_link.'T'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset; display:inline-block;">
					<span class="badge"><?php $c = (int)@$row->byStateTotals[-2]; echo $c ? $c : '.'; ?></span>
				</a>
			</td>
			<td>
				<?php echo $access; ?>
			</td>
			<td class="left order">
			 <?php if ($canChange) : ?>
				<?php $disabled = $saveOrder ?  '' : 'disabled="disabled"'; ?>
				<input type="text" name="order[]" size="5" value="<?php echo $orderkey + 1;?>" <?php echo $disabled ?> class="text-area-order" style="text-align: center" />
				<?php $originalOrders[] = $orderkey + 1; ?>
				
				<?php if ($saveOrder) : ?>
					<span><?php echo $this->pagination->orderUpIcon($i, isset($this->ordering[$row->parent_id][$orderkey - 1]), 'categories.orderup', 'JLIB_HTML_MOVE_UP', $this->orderingx); ?></span>
					<span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, isset($this->ordering[$row->parent_id][$orderkey + 1]), 'categories.orderdown', 'JLIB_HTML_MOVE_DOWN', $this->orderingx); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<?php echo $orderkey + 1;?>
			<?php endif; ?>
			</td>
			<td class="left nowrap">
			<?php if ($row->language=='*'):?>
				<?php echo JText::alt('JALL','language'); ?>
			<?php else:?>
				<?php echo $row->language_title ? $this->escape($row->language_title) : JText::_('JUNDEFINED'); ?>
			<?php endif;?>
			</td>
			<td>
				<span title="<?php echo sprintf('%d-%d', $row->lft, $row->rgt);?>">
				<?php echo $row->id; ?>
				</span>
			</td>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
	</tfoot>

	</table>
	
	<?php /*echo JText::_( 'FLEXI_PARAMS_CAT' );*/ ?>
	
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<!---input type="hidden" name="controller" value="categories" /-->
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="original_order_values" value="<?php echo implode(',', $originalOrders); ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHTML::_( 'form.token' ); ?>

	<!-- fc_perf -->
	</div>  <!-- sidebar -->
</form>
</div><!-- #flexicontent end -->