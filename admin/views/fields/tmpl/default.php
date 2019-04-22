<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
JHtml::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');

global $globalcats;
$app      = JFactory::getApplication();
$jinput   = $app->input;
$config   = JFactory::getConfig();
$user     = JFactory::getUser();
$session  = JFactory::getSession();
$document = JFactory::getDocument();
$cparams  = JComponentHelper::getParams('com_flexicontent');
$ctrl     = 'fields.';
$hlpname  = 'fcfields';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_entry      = JText::_('FLEXI_EDIT_FIELD', true);
$flexi_yes       = JText::_('FLEXI_YES');
$flexi_no        = JText::_('FLEXI_NO');
$flexi_nosupport = JText::_('FLEXI_PROPERTY_NOT_SUPPORTED', true);
$flexi_rebuild   = JText::_('FLEXI_REBUILD_SEARCH_INDEX', true);
$flexi_toggle    = JText::_('FLEXI_CLICK_TO_TOGGLE', true);



/**
 * JS for Columns chooser box and Filters box
 */

flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	'adminListTableFC' . $this->view,
	$start_html = '',  //'<span class="badge ' . (FLEXI_J40GE ? 'badge-dark' : 'badge-inverse') . '">' . JText::_('FLEXI_COLUMNS', true) . '<\/span> &nbsp; ',
	$end_html = '<div id="fc-columns-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="' . JText::_('FLEXI_HIDE') . '" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"><\/div>'
);



/**
 * Get cookie-based preferences of current user
 */

// Get all managers preferences
$fc_man_name = 'fc_' . $this->getModel()->view_id;
$FcMansConf = $this->getUserStatePrefs($fc_man_name);

// Get specific manager data
$tools_state = isset($FcMansConf->$fc_man_name)
	? $FcMansConf->$fc_man_name
	: (object) array(
		'filters_box' => 0,
	);



/**
 * ICONS and reusable variables
 */



/**
 * Order stuff and table related variables
 */

$canOrder = $this->perms->CanOrderFields;
$list_total_cols = 13;

$ordering_draggable = $cparams->get('draggable_reordering', 1);

if ($this->reOrderingActive)
{
	$_title = JText::_(!$this->filter_type ? 'FLEXI_GLOBAL_ORDER' : 'FLEXI_TYPE_ORDER', true);

	$image_ordering_tip = '<span class="icon-info ' . $this->tooltip_class . '" title="' . flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1)  . '<br><br>' . $_title . '"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="' . JText::_('FLEXI_ORDER_SAVE_WHEN_DONE', true) . '"></div>';
}
else
{
	$image_ordering_tip = '<span class="icon-info ' . $this->tooltip_class . '" title="' . flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1) . '"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="' . JText::_('FLEXI_ORDER_COLUMN_FIRST', true) . '" ></div>';
	$image_saveorder    = '';
}

$drag_handle_html['disabled'] = sprintf($drag_handle_box, ' fc_drag_handle_disabled');
$drag_handle_html['both']     = sprintf($drag_handle_box, ' fc_drag_handle_both');
$drag_handle_html['uponly']   = sprintf($drag_handle_box, ' fc_drag_handle_uponly');
$drag_handle_html['downonly'] = sprintf($drag_handle_box, ' fc_drag_handle_downonly');
$drag_handle_html['none']     = sprintf($drag_handle_box, '_none');

$ord_col = !$this->filter_type
	? 'ordering'
	: 'typeordering';
$ord_grp = 1;



/**
 * Add inline JS
 */

$js = '';

$js .= "

// Delete a specific list filter
function delFilter(name)
{
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);

	if (!filter.length)
	{
		return;
	}
	else if (filter.attr('type') == 'checkbox')
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
	delFilter('filter_type');
	delFilter('filter_assigned');
	delFilter('filter_fieldtype');
	delFilter('filter_state');
	delFilter('filter_access');
	delFilter('filter_order');
	delFilter('filter_order_Dir');
}

";

if ($js)
{
	$document->addScriptDeclaration($js);
}
?>


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

		<?php if (!empty($this->lists['scope_tip'])) : ?>
		<div class="fc-filter-head-box filter-search nowrap_box" style="margin: 0;">
			<?php echo $this->lists['scope_tip']; ?>
		</div>
		<?php endif; ?>

		<div class="fc-filter-head-box filter-search nowrap_box">
			<div class="btn-group <?php echo $this->ina_grp_class; ?>">
				<?php
					echo !empty($this->lists['scope']) ? $this->lists['scope'] : '';
				?>
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : JText::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
				<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>

				<div id="fc_filters_box_btn" data-original-title="<?php echo JText::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $out_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : JText::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
				</div>

				<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_state->filters_box) echo 'style="display:none;"'; ?> class="fcman-abs" onclick="var event = arguments[0] || window.event; event.stopPropagation();">
					<?php
					if (!$this->reOrderingActive)
					{
						echo $this->lists['filter_type'];
					}
					echo $this->lists['filter_assigned'];
					echo $this->lists['filter_fieldtype'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					?>

					<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
				</div>

				<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
			</div>

		</div>


		<div class="fc-filter-head-box nowrap_box">

			<div class="btn-group">
				<div id="fc_mainChooseColBox_btn" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?> hidden-phone" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" title="<?php echo flexicontent_html::getToolTip('FLEXI_COLUMNS', 'FLEXI_ABOUT_AUTO_HIDDEN_COLUMNS', 1, 1); ?>">
					<span class="icon-contract"></span><sup id="columnchoose_totals"></sup>
				</div>

				<?php if (!empty($this->minihelp) && FlexicontentHelperPerm::getPerm()->CanConfig): ?>
				<div id="fc-mini-help_btn" class="<?php echo $out_class; ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');" >
					<span class="icon-help"></span>
					<?php echo $this->minihelp; ?>
				</div>
				<?php endif; ?>
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

	<?php if ($this->reOrderingActive): ?>

		<?php
		$order_msg = '';
		$msg_icon  = '';
		$msg_style = 'padding-top: 4px; padding-bottom: 4px; margin: 12px 0 6px 0;';

		if (!$this->filter_type)
		{
			//$ico_text  = JText::_('FLEXI_GLOBAL_ORDER');
			//$msg_icon  = '<span class="icon-question ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $msg_text, 0, 1) . '"></span>';
			$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-success';
		}
		else
		{
			if (!$this->getModel()->getState('filter_type'))
			{
				$ico_text  = JText::_('FLEXI_TYPE_ORDER_DESC');
				$msg_icon  = '<span class="icon-notification ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $ico_text, 0, 1) . '"></span>';
				$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-info';
			}
			else
			{
				//$ico_text  = JText::_('FLEXI_TYPE_ORDER');
				//$msg_icon  = '<span class="icon-question ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $ico_text, 0, 1) . '"></span>';
				$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-success';
			}
		}
		?>

		<div class="clear"></div>

		<div id="fcorder_notes_box" class="hidden-phone <?php echo $msg_class; ?>" style="<?php echo $msg_style; ?> line-height: 28px; max-width: unset;">
			<?php echo $order_msg;?>
			<div id="order_type_selector" class="fc-iblock">
				<?php echo $this->lists['filter_type']; ?>
			</div>
		</div>

		<div class="fcclear"></div>

		<?php if ($canOrder): ?>
		<div class="hidden-phone" style="z-index: 1; position: sticky; top: 30%; margin: 0 -20px;">
			<div style="position: absolute; margin: 0; height: 0;">
				<div style="padding: 0px; font-weight: normal; line-height: 28px; width: auto; text-align: center;">
					<?php echo JHtml::_($hlpname . '.saveorder_btn', $this->rows, $_config = null); ?>
				</div>
			</div>
		</div>
		<div class="hidden-phone" style="z-index: 1; position: sticky; top: 30%; margin: 0 -20px;">
			<div style="position: absolute; margin: 40px 0 0 0; height: 0;">
				<div style="padding: 0px; font-weight: normal; line-height: 28px; width: auto; text-align: center;">
					<?php echo JHtml::_($hlpname . '.manualorder_btn', $this->rows, $_config = null); ?>
				</div>
			</div>
		</div>
		<?php else: ?>
			<?php echo '<span class="icon-cancel ' . $this->tooltip_class . '" title="'.flexicontent_html::getToolTip('', 'FLEXI_FCORDER_ONLY_VIEW', 1, 1) . '"></span>'; ?>
		<?php endif; ?>

	<?php endif; ?>

	<div class="fcclear"></div>

	<table id="adminListTableFC<?php echo $this->view; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>

			<!--th class="left hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_order center hidden-phone">
				<?php
				echo $canOrder ? $image_ordering_tip : '';
				echo str_replace('_FLEXI_ORDER_',
					''/*JText::_('FLEXI_ORDER', true)*/,
					str_replace('_FLEXI_ORDER_</a>', '<span class="icon-menu-2 btn btn-micro"></span></a>',
					JHtml::_('grid.sort', '_FLEXI_ORDER_', (!$this->filter_type ? 'a.ordering' : 'typeordering'), $this->lists['order_Dir'], $this->lists['order']))
				);
				?>
				<span class="column_toggle_lbl" style="display:none;"><?php echo JText::_( 'FLEXI_ORDER' ); ?></span>
			</th>

			<th class="col_cb left">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="col_status hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'i.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<?php /*<th style="padding:0px;"><?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_DESCRIPTION', 'a.description', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>*/ ?>

			<th class="col_title hideOnDemandClass title" colspan="2" style="text-align:left; padding-left:18px; padding-right:18px;">
				<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_LABEL', 'a.label', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_alias hideOnDemandClass hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_NAME', 'a.name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<small>(<?php echo JText::_('FLEXI_ALIAS'); ?>)</small>
			</th>

			<th class="col_fieldtype hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_FIELD_TYPE', 'a.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center nowrap hidden-phone" colspan="2">
				<?php echo '<small class="label" style="padding: 2px 4px; border-radius: 4px;">'.JText::_( 'Content Lists' ).'</small>'; ?><br/>
				<small>
					<?php echo JHtml::_('grid.sort', 'FLEXI_SEARCH', 'a.issearch', $this->lists['order_Dir'], $this->lists['order'] ); ?> /
					<?php echo JHtml::_('grid.sort', 'FLEXI_FILTER', 'a.isfilter', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</small>
				<span class="column_toggle_lbl" style="display:none;"><?php echo '<small class="badge">'.JText::_( 'Content Lists' ).'</small>'; ?></span>
			</th>

			<th class="hideOnDemandClass center nowrap hidden-phone" colspan="2">
				<?php echo '<small class="label" style="padding: 2px 4px; border-radius: 4px;">'.JText::_( 'Search view' ).'</small>'; ?><br/>
				<small>
					<?php echo JHtml::_('grid.sort', 'FLEXI_SEARCH', 'a.isadvsearch', $this->lists['order_Dir'], $this->lists['order'] ); ?> /
					<?php echo JHtml::_('grid.sort', 'FLEXI_FILTER', 'a.isadvfilter', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</small>
				<span class="column_toggle_lbl" style="display:none;"><?php echo '<small class="badge">'.JText::_( 'Search view' ).'</small>'; ?></span>
			</th>

			<th class="col_ntypes hideOnDemandClass left hidden-phone" colspan="2">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ASSIGNED_TYPES', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_access hideOnDemandClass left hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass col_id center hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
			</th>

		</tr>
	</thead>

	<tbody <?php echo $ordering_draggable && $canOrder && $this->reOrderingActive ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');

		$padcount = 0;
		$padspacer_next = '';

		$total_rows = count($this->rows);

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		foreach ($this->rows as $i => $row)
		{
			$row_css = '';
			$repeat = $padcount;
			$padspacer = $padspacer_next;

			// Create coloring and padding for groupmarker fields if filtering by specific type is enabled
			if ($this->filter_type && $row->field_type === 'groupmarker')
			{
				switch ($row->parameters->get('marker_type'))
				{
					case 'tabset_start':
					case 'tabset_end':
						$row_css = 'color:black;';
						break;

					case 'tab_open':
					case 'fieldset_open':
						$row_css = 'color:darkgreen;';
						$repeat = $padcount++;
						break;

					case 'tab_close':
					case 'fieldset_close':
						$row_css = 'color:darkred;';
						$repeat = $padcount ? --$padcount : 0;

						// Use new padspacer instead of previous one
						$padspacer = str_repeat('&nbsp;|_&nbsp;', $padcount);
						break;
				}
			}

			// Calculate padding for next row
			$padspacer_next = str_repeat('&nbsp;|_&nbsp;', $padcount);

			$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'field', $row->id);
			$row->canCheckin   = $canCheckinRecords;
			$row->canEdit      = in_array('editfield', $rights);
			$row->canEditState = in_array('publishfield', $rights);
			$row->canDelete    = in_array('deletefield', $rights);

			$search_filter_icons = JHtml::_($hlpname . '.search_filter_icons', $row, $i);

			$orphan_warning	= '
				<span class="icon-warning fc-icon-orange ' . $this->tooltip_class . '" title="'. flexicontent_html::getToolTip('FLEXI_WARNING', 'FLEXI_NO_TYPES_ASSIGNED', 1, 1) .'"></span>';
			?>

		<tr class="<?php echo 'row' . ($i % 2); ?>" style="<?php echo $row_css; ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

		<?php if ($canOrder) : ?>

			<td class="col_order nowrap center hidden-phone">
				<?php
					$show_orderUp   = $i > 0;
					$show_orderDown = $i < $total_rows-1;
				?>
				<?php if (!$this->reOrderingActive): echo '<span class="icon-move" style="color: #d0d0d0"></span>'; //$drag_handle_html['disabled']; ?>
				<?php elseif ($ordering_draggable): ?>
					<?php
						if ($show_orderUp && $show_orderDown) echo $drag_handle_html['both'];
						else if ($show_orderUp) echo $drag_handle_html['uponly'];
						else if ($show_orderDown) echo $drag_handle_html['downonly'];
						else echo $drag_handle_html['none'];
					?>
				<?php else: ?>
					<span><?php echo $this->pagination->orderUpIcon( $i, $show_orderUp, $ctrl.'orderup', 'Move Up', $this->reOrderingActive ); ?></span>
					<span><?php echo $this->pagination->orderDownIcon( $i, count($this->rows), $show_orderDown, $ctrl.'orderdown', 'Move Down', $this->reOrderingActive );?></span>
				<?php endif; ?>

				<?php if ($this->reOrderingActive): ?>
					<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" style="display: none;" />
					<input type="hidden" name="ord_grp[]" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />
				<?php endif; ?>
			</td>

		<?php else : ?>

			<td class="center hidden-phone">
				<?php
				echo !$this->reOrderingActive
					? '<span class="icon-move" style="color: #d0d0d0"></span>'
					: '';
				?>
			</td>

		<?php endif; ?>

			<td class="col_cb">
				<!--div class="adminlist-table-row"></div-->
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_status">
				<div class="btn-group fc-group fc-fields">
					<?php
					/**
					 * State changer button
					 */
					echo JHtml::_($hlpname . '.statebutton', $row, $i, $row->id < 7);

					/**
					 * Create an icon having information of field participating in a group
					 */
					echo JHtml::_($hlpname . '.in_group', $row, $i);

					/**
					 * Create an icon having information about master field (if current field cascading after it)
					 */
					echo JHtml::_($hlpname . '.cascade_after', $row, $i);
					?>
				</div>
			</td>

			<td class="col_title">
				<?php
				echo $padspacer;

				/**
				 * Display an edit pencil or a check-in button if: either (a) current user has Global
				 * Checkin privilege OR (b) record checked out by current user, otherwise display a lock
				 */
				echo JHtml::_($hlpname . '.checkedout', $row, $user, $i);

				/**
				 * Display title with edit link ... (row editable and not checked out)
				 * Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				 */
				echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit);
				?>
			</td>

			<td>
				<?php echo JHtml::_($hlpname . '.info_text', $row, $i, 'description', 'FLEXI_FIELD_DESCRIPTION'); ?>
			</td>

			<td class="col_alias small hidden-phone hidden-tablet">
				<?php echo $row->name; ?>
			</td>

			<td class="col_fieldtype hidden-phone">
				<?php echo JHtml::_($hlpname . '.fieldtype_info', $row, $i); ?>
			</td>

			<td class="right hidden-phone">
				<?php echo $search_filter_icons['search']; ?>
			</td>

			<td class="left hidden-phone">
				<?php echo $search_filter_icons['filter']; ?>
			</td>

			<td class="right hidden-phone">
				<?php echo $search_filter_icons['advsearch']; ?>
			</td>

			<td class="left hidden-phone">
				<?php echo $search_filter_icons['advfilter']; ?>
			</td>

			<td class="col_ntypes_count center hidden-phone">
				<?php echo $row->nrassigned ? '<span class="badge badge-info hasTooltip">'.$row->nrassigned.'</span>' : $orphan_warning; ?>
			</td>

			<td class="col_ntypes_list hidden-phone">
				<?php
				if (!count($row->content_types))
				{
					echo '<span class="badge badge-warning">'.JText::_('FLEXI_NONE').'</span>';
				}
				else
				{
					$row_types  = array();
					$type_names = array();
					foreach($row->content_types as $type_id)
					{
						$row_types[] = '
						<span class="itemtype">
							'.$this->types[$type_id]->jname.'
						</span>';
						$type_names[] = $this->types[$type_id]->jname;
					}
					echo count($row_types) > 3 ? '
						<span class="btn btn-mini hasTooltip nowrap_box" onclick="jQuery(this).next().toggle();" title="' . flexicontent_html::getToolTip(JText::_('FLEXI_ASSIGNED_TYPES', true), '<ul class="fc_plain"><li>'.implode('</li><li>', $type_names).'</li></ul>', 0, 1) . '">
							'.count($row_types).' <i class="icon-briefcase"></i>
						</span>
						<div class="fc_assignments_box fc_types">' : '';
					echo count($row_types) > 8
						? implode(', ', $row_types)
						: (count($row_types) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_types) . '</li></ul>' : '');
					echo count($row_types) > 3 ? '</div>' : '';
				}
				?>
			</td>

			<td class="col_access hidden-phone">
				<?php echo $row->canEdit
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"')
					: $this->escape($row->access_level);
				?>
			</td>

			<td class="col_id center hidden-phone hidden-tablet">
				<?php echo $row->id; ?>
			</td>

		</tr>
		<?php
		}
		?>
	</tbody>

	</table>


	<div>
		<?php echo $pagination_footer; ?>
	</div>

	<div style="margin-top: 48px;">
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-publish" style="font-size: 16px;"></span> <?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-unpublish" style="font-size: 16px;"></span> <?php echo JText::_( 'FLEXI_UNPUBLISHED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-archive" style="font-size: 16px;"></span> <?php echo JText::_( 'FLEXI_ARCHIVED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-trash" style="font-size: 16px;"></span>	<?php echo JText::_( 'FLEXI_TRASHED' ); ?></div>
	</div>

	<!-- This manager form fields -->
	<input type="hidden" name="propname" value="" />

	<!-- Common management form fields -->
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_('form.token'); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->


<?php
JFactory::getDocument()->addScriptDeclaration('
	function fc_edit_fcfield_modal_load( container )
	{
		if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=fields") != -1 )
		{
			container.dialog("close");
		}
	}
	function fc_edit_fcfield_modal_close()
	{
		window.location.reload(false);
		document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
	}
');
