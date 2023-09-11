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
$ctrl     = 'types.';
$hlpname  = 'fctypes';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_entry  = JText::_('FLEXI_EDIT_TYPE', true);
$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');
$view_fields = JText::_('FLEXI_VIEW', true); //JText::_('FLEXI_VIEW_FIELDS', true);
$view_items  = JText::_('FLEXI_VIEW', true); //JText::_('FLEXI_VIEW_ITEMS', true);



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


$attribs_editlayout = ' class="fc-edit-layout-btn ntxt ' . $this->btn_sm_class . ' ' . $this->tooltip_class . '" title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'" ';

$image_editlayout = 0 ?
	JHtml::image('components/com_flexicontent/assets/images/'.'layout_edit.png', htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8'), ' class="'.$ico_class.'"') :
	'<span class="'.$ico_class.'"><span class="icon-edit"></span></span>' ;
$article_viewing_tip  = '<span class="icon-info ' . $this->tooltip_class . '" data-placement="bottom" title="'.flexicontent_html::getToolTip('FLEXI_JOOMLA_ARTICLE_VIEW', 'FLEXI_ALLOW_ARTICLE_VIEW_DESC', 1, 1).'"></span>';
$default_template_tip = '<span class="icon-info ' . $this->tooltip_class . '" data-placement="bottom" title="'.flexicontent_html::getToolTip('FLEXI_TYPE_DEFAULT_TEMPLATE', 'FLEXI_TYPE_DEFAULT_TEMPLATE_DESC', 1, 1).'"></span>';
$limit_maincat_tip = '<span class="icon-info ' . $this->tooltip_class . '" data-placement="bottom" title="'.flexicontent_html::getToolTip('FLEXI_MAIN_CATEGORY_LIMITATION', 'FLEXI_MAIN_CATEGORY_LIMITATION_DESC', 1, 1).'"></span>';

/**
 * Order stuff and table related variables
 */

$list_total_cols = 12;



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
					<?php
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					?>

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

			<th class="col_num hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th>

			<th class="col_cb left">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="col_status hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'a.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_title hideOnDemandClass title">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TYPE_NAME', 'a.name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_redirect hideOnDemandClass hidden-phone hidden-tablet">
				<?php echo $article_viewing_tip . JText::_( 'FLEXI_JOOMLA_ARTICLE_VIEW' )."<br/><small>(" . JText::_( 'FLEXI_ALLOWED') .' / '. JText::_( 'FLEXI_REROUTED' ) .' / '. JText::_( 'FLEXI_REDIRECTED' ) . ")</small>"; ?>
			</th>

			<th class="col_template left hideOnDemandClass hidden-phone hidden-tablet" colspan="2">
				<?php echo $default_template_tip.JText::_( 'FLEXI_TEMPLATE' )."<br/><small>(".JText::_( 'FLEXI_PROPERTY_DEFAULT' )." ".JText::_( 'FLEXI_TEMPLATE_ITEM' ).")</small>"; ?>
			</th>

			<th class="col_template left hideOnDemandClass hidden-phone hidden-tablet" >
				<?php echo $limit_maincat_tip.JText::_( 'FLEXI_MAIN_CATEGORY' ) . '<br/><small>(' . JText::_( 'FLEXI_SUBTREE_LIMITATION' ) . ')</small>'; ?>
			</th>

			<th class="col_iflayout center hideOnDemandClass hidden-phone hidden-tablet" colspan="2" >
				<?php echo JText::_( 'JForm'). ' ' . JText::_( 'FLEXI_LAYOUT' ) . '<br/><small>(' . JText::_( 'FLEXI_FRONTEND' ) . ' ' . JText::_( 'FLEXI_BACKEND' ) . ')</small>'; ?>
			</th>

			<th class="col_alias hideOnDemandClass hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 'a.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_fassigned hideOnDemandClass center">
				<?php echo JHtml::_('grid.sort', 'FLEXI_FIELDS', 'fassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_iassigned hideOnDemandClass center">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ITEMS', 'iassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_access hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_id hideOnDemandClass center hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		foreach ($this->rows as $i => $row)
		{
			$row = & $this->rows[$i];
			$link 		= 'index.php?option=com_flexicontent&amp;task=types.edit&amp;view=type&amp;id='. $row->id;

			$fields_link = 'index.php?option=com_flexicontent&amp;view=fields&amp;filter_type='. $row->id;
			$items_link  = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_type='. $row->id;

			// Permissions
			$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
			$row->canEdit      = 1;
			$row->canEditState = 1;
			$row->canDelete    = 1;

			$canEdit    = 1;

			$stateIsChangeable    = $row->canCheckin && $row->canEditState;
			$row_ilayout          = $row->config->get('ilayout');
			$row_form_ilayout_fe  = $row->config->get('form_ilayout_fe', 'tabs');
			$row_form_ilayout_be  = $row->config->get('form_ilayout_be', 'tabs');
			$catid_allowed_parent = $row->config->get('catid_allowed_parent');
			?>
		<tr class="<?php echo 'row' . ($i % 2); ?>">

			<td class="col_num hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td>

			<td class="col_cb">
				<!--div class="adminlist-table-row"></div-->
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_status" style="padding-right: 8px;">
				<div class="btn-group fc-group fc-types">
					<?php
					//echo JHtml::_('jgrid.published', $row->state, $i, $ctrl, $stateIsChangeable);
					//echo JHtml::_($hlpname . '.published', $row->state, $i, $stateIsChangeable);

					echo JHtml::_($hlpname . '.statebutton', $row, $i);
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

			<td class="col_redirect hidden-phone hidden-tablet">
				<?php
					$jarticle_url_handling_txts = array(0 => 'FLEXI_ROUTE_TO_ITEM_VIEW', 1 => 'FLEXI_ALLOWED', '2' => 'FLEXI_REDIRECT_TO_ITEM_VIEW');
					$allow_jview = $row->config->get("allow_jview");

					$jview_ops = array();
					$jview_ops[] = JHtml::_('select.option', '1', 'FLEXI_ALLOWED');
					$jview_ops[] = JHtml::_('select.option', '0', 'FLEXI_ROUTE_TO_ITEM_VIEW');
					$jview_ops[] = JHtml::_('select.option', '2', 'FLEXI_REDIRECT_TO_ITEM_VIEW');

					echo JHtml::_('select.genericlist', $jview_ops, 'allow_jview['.$row->id.']', 'class="fcfield_selectval" size="1" onchange="Joomla.listItemTask(\'cb'.$i.'\',\'types.toggle_jview\'); Joomla.submitform()"', 'value', 'text', $allow_jview, 'allow_jview'.$row->id, $translate=true);
				?>
			</td>

			<td class="col_edit_layout hidden-phone hidden-tablet">
				<?php echo JHtml::_($hlpname . '.edit_layout', $row, '__modal__', $i, $this->perms->CanTemplates, $row_ilayout); ?>
			</td>

			<td class="col_template smaller hidden-phone hidden-tablet">
				<?php echo $row_ilayout; ?>
			</td>

			<td class="col_allowed_subtree smaller hidden-phone hidden-tablet">
				<?php echo isset($globalcats[$catid_allowed_parent]) ? $globalcats[$catid_allowed_parent]->title : '-'; ?>
			</td>

			<td class="col_iflayout_fe smaller center hidden-phone hidden-tablet">
				<?php echo $row_form_ilayout_fe; ?>
			</td>

			<td class="col_iflayout_be smaller center hidden-phone hidden-tablet">
				<?php echo $row_form_ilayout_be; ?>
			</td>

			<td class="col_alias smaller hidden-phone hidden-tablet">
				<?php echo StringHelper::strlen($row->alias) > 25
					? StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25) . '...'
					: htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				?>
			</td>

			<td class="col_fassigned center">
				<a class="badge fc-assignments-btn" href="<?php echo $fields_link; ?>">
					<?php echo $row->fassigned; ?>
				</a>
			</td>

			<td class="col_iassigned center">
				<a class="badge bg-info badge-info fc-assignments-btn" href="<?php echo $items_link; ?>">
					<?php echo $row->iassigned; ?>
				</a>
			</td>

			<td class="col_access hidden-phone">
				<?php echo $row->canEdit
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'class="fcfield_selectval" onchange="return Joomla.listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"')
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
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-publish" style="font-size: 16px;"></span> <?php echo JText::_( 'FLEXI_ENABLED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-unpublish" style="font-size: 16px;"></span> <?php echo JText::_( 'FLEXI_DISABLED' ); ?></div>
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
$sidebar_state = $cparams->get('sidebar_state', 'closed');
if(($sidebar_state) == 'closed') : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var sidebar = document.querySelector('#sidebar-wrapper');
  var wrapper = document.querySelector('#wrapper');
  var menuCollapse = document.querySelector('#menu-collapse');
  var menuIcon = document.querySelector('#menu-collapse-icon');
  var navLogo = document.querySelector('#header .logo');
  // Retrieve sidebar state from localStorage
  var sidebarState = localStorage.getItem('sidebar');
  console.log(sidebarState);


// Apply initial sidebar state
if (sidebarState === 'closed') {
  wrapper.classList.add('closed');
  menuIcon.classList.remove('icon-toggle-on');
  menuIcon.classList.add('icon-toggle-off');
  navLogo.classList.add('small');
} else if (sidebarState === 'open') {
  wrapper.classList.remove('closed');
  menuIcon.classList.remove('icon-toggle-off');
  menuIcon.classList.add('icon-toggle-on');
  navLogo.classList.remove('small');
} else {
  wrapper.classList.add('closed');
  menuIcon.classList.remove('icon-toggle-on');
  menuIcon.classList.add('icon-toggle-off');
  navLogo.classList.add('small');
}


  // Handle menu click
  menuCollapse.addEventListener('click', function() {
    // Toggle wrapper class
   
    // Update icon
    if (wrapper.classList.contains('closed')) {
      menuIcon.classList.remove('icon-toggle-on');
      menuIcon.classList.add('icon-toggle-off');
	  navLogo.classList.add('small');
      localStorage.setItem('sidebar', 'closed');
    } else {
      menuIcon.classList.remove('icon-toggle-off');
      menuIcon.classList.add('icon-toggle-on');
      localStorage.setItem('sidebar', 'open');
	  navLogo.classList.remove('small');
    }
  });
});
</script>
<?php endif; ?>
