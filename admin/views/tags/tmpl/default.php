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
$ctrl     = 'tags.';
$hlpname  = 'fctags';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';



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

$list_total_cols = 7;



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
	delFilter('filter_state');
	delFilter('filter_assigned');
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

<?php if (!empty( $this->sidebar) && FLEXI_J40GE == false) : ?>

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
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : JText::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="fcfield_textval" />
				<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>

				<div id="fc_filters_box_btn" data-original-title="<?php echo JText::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $out_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : JText::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
				</div>

				<div id="fc-filters-box" <?php if (!$this->count_filters || empty($tools_state->filters_box)) echo 'style="display:none;"'; ?> class="fcman-abs" onclick="var event = arguments[0] || window.event; event.stopPropagation();">

					<?php echo $this->lists['filter_state']; ?>
					<?php echo $this->lists['filter_assigned']; ?>

					<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
				</div>

				<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
			</div>

		</div>


		<div class="fc-filter-head-box nowrap_box">

			<div class="btn-group">
				<div id="fc_mainChooseColBox_btn" class="<?php echo $this->tooltip_class . ' ' . $out_class; ?> hidden-phone" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" title="<?php echo flexicontent_html::getToolTip('FLEXI_COLUMNS', 'FLEXI_ABOUT_AUTO_HIDDEN_COLUMNS', 1, 1); ?>">
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


	<table id="adminListTableFC<?php echo $this->view; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>

			<!--th class="left hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_cb left">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'a.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass title">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'a.' . $this->title_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass alias hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 'a.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ASSIGNED_TO', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass col_id center hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet" colspan="2">
				<?php echo JHtml::_('grid.sort', 'FLEXI_JTAG_ID', 'a.jtag_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$canManage = FlexicontentHelperPerm::getPerm()->CanTags;
		$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;fcform=1&amp;filter_tag=';

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
		$k = 0;

		foreach ($this->rows as $i => $row)
		{
			// Permissions
			$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
			$row->canEdit      = $canManage;
			$row->canEditState = $canManage;
			$row->canDelete    = $canManage;

			$stateIsChangeable = $row->canCheckin && $row->canEditState;
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_cb">
				<!--div class="adminlist-table-row"></div-->
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_status" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-tags">
					<?php
					//echo JHtml::_('jgrid.published', $row->published, $i, $ctrl, $stateIsChangeable);
					//echo JHtml::_($hlpname . '.published', $row->published, $i, $stateIsChangeable);

					echo JHtml::_($hlpname . '.statebutton', $row, $i);
					echo JHtml::_($hlpname . '.rss_link', $row, '_blank', $i);
					echo JHtml::_($hlpname . '.preview', $row, '_blank', $i);
					?>
				</div>
			</td>

			<td class="col_title smaller">
				<?php
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

			<td class="alias hidden-phone">
				<?php echo StringHelper::strlen($row->alias) > 25
					? StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25) . '...'
					: htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				?>
			</td>

			<td class="center">
				<a class="<?php echo $this->btn_sm_class; ?> fc-assignments-btn" href="<?php echo $items_link.$row->id; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>">
					<?php echo $row->nrassigned ? $row->nrassigned : 0; ?>
				</a>
			</td>

			<td class="col_id center hidden-phone hidden-tablet">
				<?php echo $row->id; ?>
			</td>

			<td class="center hidden-phone hidden-tablet">
				<?php echo '<i style="color: #777;">' . $row->jtag_id . '</i>'; ?>
			</td>

			<td class="center hidden-phone hidden-tablet">
				<?php
				echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
					'option'   => 'com_tags',
					'ctrl'     => 'tag',
					'keyname'  => 'jtag_id',
					'noTitle'  => true,
					'linkedPrefix' => '<span class="icon-pencil-2"></span><span class="icon-tag"></span>',
					'attribs' => array(
						'class'       => $this->btn_sm_class,
						'title'       => JText::_('FLEXI_EDIT_JTAG'),
					),
					'useModal' => (object) array(
						'title'       => 'FLEXI_EDIT_JTAG',
						'onloadfunc'  => 'fc_edit_jtag_modal_load',
						'onclosefunc' => 'fc_edit_jtag_modal_close',
					),
				));
				?>
			</td>

		</tr>
		<?php
			$k++;
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
	function fc_edit_jtag_modal_load( container )
	{
		if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=tags") != -1 )
		{
			container.dialog("close");
		}
	}
	function fc_edit_jtag_modal_close()
	{
		//window.location.reload(false);
		window.location.href = \'index.php?option=com_flexicontent&view=tags\';
		document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
	}
');