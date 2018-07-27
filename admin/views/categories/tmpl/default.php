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

global $globalcats;
$app     = JFactory::getApplication();
$jinput  = $app->input;
$config  = JFactory::getConfig();
$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );
$ctrl    = 'categories.';
$hlpname = 'fccats';
$isAdmin = $app->isAdmin();



/**
 * COMMON classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$btn_s_class = 'btn btn-small';



/**
 * JS for Columns chooser box and Filters box
 */

flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	'adminListTableFCcats',
	$start_html = '',  //'<span class="badge ' . (FLEXI_J40GE ? 'badge-dark' : 'badge-inverse') . '">' . JText::_('FLEXI_COLUMNS', true) . '<\/span> &nbsp; ',
	$end_html = '<div id="fc-columns-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="' . JText::_('FLEXI_HIDE') . '" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"><\/div>'
);
$tools_cookies['fc-filters-box-disp'] = JFactory::getApplication()->input->cookie->get('fc-filters-box-disp', 0, 'int');



/**
 * ICONS and reusable variables
 */

$fcfilter_attrs_row = ' class="input-prepend fc-xpended-row" ';
$attribs_editlayout = ' class="fc-edit-layout-btn ntxt '.$btn_s_class.' ' . $this->tooltip_class . '" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_editlayout = 0 ?
	JHtml::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"') :
	'<span class="'.$ico_class.'"><span class="icon-edit"></span></span>' ;

$img_path = '../components/com_flexicontent/assets/images/';
$image_flag_path = '../media/mod_languages/images/';
$infoimage  = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), ' class="fc-man-icon-s" ' );

$state_names = array('ALL_P'=>JText::_('FLEXI_PUBLISHED'), 'ALL_U'=>JText::_('FLEXI_UNPUBLISHED'), 'A'=>JText::_('FLEXI_ARCHIVED'), 'T'=>JText::_('FLEXI_TRASHED'));
$state_imgs  = array('ALL_P'=>'tick.png', 'ALL_U'=>'publish_x.png', 'A'=>'archive.png', 'T'=>'trash.png');
$state_icons = array('ALL_P'=>'publish', 'ALL_U'=>'unpublish', 'A'=>'archive', 'T'=>'trash');



/**
 * Order stuff and table related variables
 */

$list_total_cols = 15;

$listOrder = $this->lists['order'];
$listDirn  = $this->lists['order_Dir'];
$saveOrder = ($listOrder == 'a.lft' && strtolower($listDirn) == 'asc');

if ($saveOrder)
{
	$saveOrderingUrl = 'index.php?option=com_flexicontent&task='.$ctrl.'saveOrderAjax&format=raw';
	JHtml::_('sortablelist.sortable', 'adminListTableFCcats', 'adminForm', strtolower($listDirn), $saveOrderingUrl, false, true);
}

?>


<script type="text/javascript">

// delete active filter
function delFilter(name)
{
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);
	if (filter.attr('type')=='checkbox')
	{
		filter.checked = '';
	}
	else
	{
		filter.val('');
		// Case that input has Calendar JS attached
		if (filter.attr('data-alt-value'))
		{
			filter.attr('data-alt-value', '');
		}
	}
}

function delAllFilters()
{
	delFilter('search');
	delFilter('filter_state');
	delFilter('filter_cats');
	delFilter('filter_id');
	delFilter('filter_level');
	delFilter('filter_access');
	delFilter('filter_language');
	delFilter('filter_order');
	delFilter('filter_order_Dir');
}

</script>


<div id="flexicontent" class="flexicontent">


<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm">


<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">

<?php if (!empty( $this->sidebar)) : ?>

	<div id="j-sidebar-container" class="span2 col-md-2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10 col-md-10">

<?php else : ?>

	<div id="j-main-container" class="span12 col-md-12">

<?php endif;?>


	<div id="fc-managers-header">

		<div class="fc-filter-head-box filter-search nowrap_box">
			<div class="btn-group <?php echo $this->ina_grp_class; ?>">
				<?php
					echo !empty($this->lists['scope']) ? $this->lists['scope'] : '';
				?>
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : JText::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
				<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>

				<?php $_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn'; ?>
				<div id="fc_filters_box_btn" data-original-title="<?php echo JText::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : JText::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>

					<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_cookies['fc-filters-box-disp']) echo 'style="display:none;"'; ?> class="fcman-abs">

						<?php echo $this->lists['filter_cats']; ?>
						<?php echo $this->lists['filter_level']; ?>
						<?php echo $this->lists['filter_state']; ?>
						<?php echo $this->lists['filter_access']; ?>
					  <?php echo $this->lists['filter_language']; ?>
					  <?php echo $this->lists['filter_id']; ?>

						<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
						<input type="hidden" id="fc-filters-box-disp" name="fc-filters-box-disp" value="<?php echo $tools_cookies['fc-filters-box-disp']; ?>" />
					</div>

				</div>
				<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
			</div>

		</div>


		<div class="fc-filter-head-box nowrap_box">

			<div class="btn-group">
				<div id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?> hidden-phone" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');">
					<?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup>
				</div>
			</div>
			<div id="mainChooseColBox" class="group-fcset fcman-abs" style="display:none;"></div>

		</div>

		<div class="fc-filter-head-box nowrap_box">
			<div class="limit nowrap_box">
				<?php
				$pagination_footer = $this->pagination->getListFooter();
				if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
				?>
			</div>

			<span class="fc_item_total_data nowrap_box fc-mssg-inline fc-info fc-nobgimage hidden-phone hidden-tablet">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
			</span>

			<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
			<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $getPagesCounter; ?>
			</span>
			<?php endif; ?>
		</div>
	</div>


	<div class="fcclear"></div>


	<table id="adminListTableFCcats" class="adminlist table fcmanlist">
	<thead>
		<tr>

			<!--th class="left hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th width="1%" class="nowrap center hidden-phone">
				<?php echo JHtml::_('searchtools.sort', '', 'a.lft', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
			</th>

			<th class="left">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'a.published', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass title">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 'a.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="left hideOnDemandClass hidden-phone hidden-tablet" colspan="2">
				<?php echo JText::_('FLEXI_TEMPLATE'); ?>
			</th>

			<!--th class="hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th-->

			<th class="hideOnDemandClass center hidden-phone hidden-tablet">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge badge-info"><?php echo $state_names['ALL_P']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['ALL_P'].'" title="'.$state_names['ALL_P'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge badge-info"><?php echo $state_names['ALL_U']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['ALL_U'].'" title="'.$state_names['ALL_U'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge badge-info"><?php echo $state_names['A']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['A'].'" title="'.$state_names['A'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge badge-info"><?php echo $state_names['T']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['T'].'" title="'.$state_names['T'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<!--th class="hideOnDemandClass">
				<?php echo JHtml::_('grid.sort', 'FLEXI_REORDER', 'a.lft', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->orderingx ? str_replace('rel="tooltip"', '', JHtml::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' )) : ''; ?>
			</th-->

			<th class="hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_LANGUAGE', 'language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');

		$originalOrders = array();
		$extension	= 'com_content';

		$clayout_bycatid = array();
		$cat_ancestors = array();
		$inheritcid_comp = $cparams->get('inheritcid', -1);

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		foreach ($this->rows as $i => $row)
		{
			// Get orderkey
			$orderkey = array_search($row->id, $this->ordering[$row->parent_id]);

			// Permissions
			$row->canEdit =
				$user->authorise('core.edit', $extension.'.category.'.$row->id) ||
				$user->authorise('core.edit.own', $extension.'.category.'.$row->id) && $row->created_user_id == $user->get('id');

			$row->canEditState =
				$user->authorise('core.edit.state', $extension.'.category.'.$row->id) ||
				$user->authorise('core.edit.state.own', $extension.'.category.'.$row->id) && $row->created_user_id==$user->get('id');

			$canCheckin = $canCheckinRecords || $row->checked_out == $user->id || !$row->checked_out;
			$canChange  = $row->canEditState && $canCheckin;

			// Get the parents of item for sorting
			if ($row->level > 1)
			{
				$parentsStr = '';
				$_currentParentId = $row->parent_id;
				$parentsStr = ' ' . $_currentParentId;
				for ($i2 = 0; $i2 < $row->level; $i2++)
				{
					foreach ($this->ordering as $k => $v)
					{
						$v = implode('-', $v);
						$v = '-' . $v . '-';
						if (strpos($v, '-' . $_currentParentId . '-') !== false)
						{
							$parentsStr .= ' ' . $k;
							$_currentParentId = $k;
							break;
						}
					}
				}
			}
			else
			{
				$parentsStr = '';
			}

			$link	= 'index.php?option=com_flexicontent&amp;task=category.edit&amp;cid='. $row->id;

			$inheritcid = $row->config->get('inheritcid', '');
			$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);

			if (!$inherit_parent || $row->parent_id==='1')
			{
				$row_clayout = $row->config->get('clayout', $cparams->get('clayout', 'blog'));
			}
			else
			{
				$row_clayout = $row->config->get('clayout', '');

				if (!$row_clayout)
				{
					if (isset($clayout_bycatid[$row->parent_id]))
					{
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
			$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;filter_cats='. $row->id.'&amp;fcform=1&amp;filter_state=';
   		?>

		<tr class="<?php echo 'row' . ($i % 2); ?>" sortable-group-id="<?php echo $row->parent_id; ?>" item-id="<?php echo $row->id ?>" parents="<?php echo $parentsStr ?>" level="<?php echo $row->level ?>">

			<!--td class="left col_rowcount hidden-phone">
				<div class="adminlist-table-row"></div>
				<?php /*echo $this->pagination->getRowOffset($i);*/ ?>
			</td-->

			<td class="order nowrap center hidden-phone">
				<?php
				$iconClass = '';
				if (!$canChange)
				{
					$iconClass = ' inactive';
				}
				elseif (!$saveOrder)
				{
					$iconClass = ' inactive tip-top hasTooltip" title="' . JHtml::_('tooltipText', 'JORDERINGDISABLED');
				}
				?>
				<span class="sortable-handler<?php echo $iconClass ?>">
					<span class="icon-menu"></span>
				</span>
				<?php if ($canChange && $saveOrder) : ?>
					<input type="text" style="display:none" name="order[]" size="5" value="<?php echo $orderkey + 1; ?>" />
				<?php endif; ?>
			</td>

			<td>
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>					
			</td>

			<td class="col_state" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-cats">
					<?php
					//echo JHtml::_('jgrid.published', $row->published, $i, $ctrl, $canChange);
					//echo JHtml::_($hlpname . '.published', $row->published, $i, $canChange);

					echo JHtml::_($hlpname . '.statebutton', $row, $i);
					echo JHtml::_($hlpname . '.rss_link', $row, '_blank', $i);
					echo JHtml::_($hlpname . '.preview', $row, '_blank', $i);
					?>
				</div>
			</td>

			<td class="col_title left">
				<?php
				echo $row->level > 1
					? str_repeat('.&nbsp;&nbsp;', $row->level - 1) . '<sup>|_</sup>&nbsp;'
					: '';

				// Display a check-in button if: either (a) current user has Global Checkin privilege OR (b) record checked out by current user, otherwise display a lock with no link
				echo JHtml::_($hlpname . '.checkedout', $row, $user, $i);

				/**
				 * Display title with edit link ... (row editable and not checked out)
				 * Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				 */
				echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit);
				?>

				<?php	if (!empty($row->note)) : /* Display J1.6+ category note in a tooltip */ ?>
					<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip( JText::_ ('FLEXI_NOTES'), $row->note, 0, 1); ?>">
						<?php echo $infoimage; ?>
					</span>
				<?php endif; ?>

			</td>

			<td class="hidden-phone hidden-tablet">
				<?php
				echo StringHelper::strlen($row->alias) > 25
					? StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25) . '...'
					: htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				?>
			</td>

			<td class="col_edit_layout hidden-phone hidden-tablet">
				<?php echo JHtml::_($hlpname . '.edit_layout', $row, '__modal__', $i, $this->perms->CanTemplates, $row_clayout); ?>
			</td>

			<td class="col_template hidden-phone hidden-tablet">
				<?php echo $row->config->get('clayout') ? $row->config->get('clayout') : ($row_clayout ? $row_clayout : '...').'<span class="badge">inherited</span>'; ?>
			</td>

			<?php /*<td>
				<a href="<?php echo $items_link; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset;">
					<span class="badge badge-info"><?php echo $row->nrassigned; ?></span>
				</a>
			</td>*/ ?>

			<?php
				$c_p = (int) @ $row->byStateTotals[1] + (int) @ $row->byStateTotals[-5];
				$c_u = (int) @ $row->byStateTotals[0] + (int) @ $row->byStateTotals[-3] + (int) @ $row->byStateTotals[-4];
				$c_a = (int) @ $row->byStateTotals[2];
				$c_t = (int) @ $row->byStateTotals[-2];
			?>

			<td style="padding: 0;" class="center hidden-phone hidden-tablet">
				<a href="<?php echo $items_link.'ALL_P'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_p ? ' badge-success' : ''; ?>">
					<?php echo $c_p ? $c_p : '0'; ?>
				</a>
			</td>
			<td style="padding: 0;" class="center hidden-phone hidden-tablet">
				<a href="<?php echo $items_link.'ALL_U'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_u ? ' badge-important' : ''; ?>">
					<?php echo $c_u ? $c_u : '0'; ?>
				</a>
			</td>
			<td style="padding: 0;" class="center hidden-phone hidden-tablet">
				<a href="<?php echo $items_link.'A'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_a ? ' badge-info' : ''; ?>">
					<?php echo $c_a ? $c_a : '0'; ?>
				</a>
			</td>
			<td style="padding: 0;" class="center hidden-phone hidden-tablet">
				<a href="<?php echo $items_link.'T'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_t ? ' badge-inverse' : ''; ?>">
					<?php echo $c_t ? $c_t : '0'; ?>
				</a>
			</td>

			<td class="col_access hidden-phone">
				<?php echo $row->canEdit && $this->perms->CanAccLvl
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'class="use_select2_lib" onchange="return listItemTask(\'cb'.$i.'\',\''.$ctrl.'.access\')"')
					: $this->escape($row->access_level); ?>
			</td>

			<!--td class="left order">
			 <?php if ($canChange) : ?>
				<?php $disabled = $saveOrder ?  '' : 'disabled="disabled"'; ?>
				<input type="text" name="order[]" size="5" value="<?php echo $orderkey + 1;?>" <?php echo $disabled ?> class="text-area-order" style="text-align: center" />
				<?php $originalOrders[] = $orderkey + 1; ?>

				<?php if ($saveOrder) : ?>
					<span><?php echo $this->pagination->orderUpIcon($i, isset($this->ordering[$row->parent_id][$orderkey - 1]), $ctrl.'orderup', 'JLIB_HTML_MOVE_UP', $this->orderingx); ?></span>
					<span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, isset($this->ordering[$row->parent_id][$orderkey + 1]), $ctrl.'orderdown', 'JLIB_HTML_MOVE_DOWN', $this->orderingx); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<?php echo $orderkey + 1;?>
			<?php endif; ?>
			</td-->

			<td class="left nowrap hidden-phone">
			<?php if ($row->language=='*'):?>
				<?php echo JText::alt('JALL','language'); ?>
			<?php else:?>
				<?php echo $row->language_title ? $this->escape($row->language_title) : JText::_('JUNDEFINED'); ?>
			<?php endif;?>
			</td>

			<td class="center hidden-phone">
				<span title="<?php echo sprintf('%d-%d', $row->lft, $row->rgt);?>">
				<?php echo $row->id; ?>
				</span>
			</td>

		</tr>
		<?php
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

	<div class="fcclear"></div>
	<input type="hidden" name="original_order_values" value="<?php echo implode(',', $originalOrders); ?>" />

	<!-- Common management form fields -->
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<!---input type="hidden" name="controller" value="categories" /-->
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
