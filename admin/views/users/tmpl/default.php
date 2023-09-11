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
$ctrl     = 'users.';
$hlpname  = 'fcusers';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_entry = JText::_('FLEXI_EDIT', true);
$view_entry = JText::_('FLEXI_VIEW', true);

$rem_filt_txt    = JText::_('FLEXI_REMOVE_FILTER', true);
$rem_filt_tip    = ' class="' . $this->tooltip_class . ' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';


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

$list_total_cols = 14;



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
	delFilter('filter_itemscount');
	delFilter('filter_logged');
	delFilter('filter_state');
	delFilter('filter_active');
	delFilter('filter_usergrp');
	delFilter('startdate');
	delFilter('enddate');
	delFilter('filter_id');
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

					<?php echo $this->lists['filter_itemscount']; ?>
					<?php echo $this->lists['filter_logged']; ?>
					<?php echo $this->lists['filter_state']; ?>
					<?php echo $this->lists['filter_active']; ?>
					<?php echo $this->lists['filter_usergrp']; ?>
					<?php echo $this->lists['filter_id']; ?>
					<?php echo $this->lists['filter_date']; ?>

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

			<th class="hideOnDemandClass left nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_NAME', 'a.name', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if (strlen($this->getModel()->getState('search'))) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('search'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap" >
				<?php echo JHtml::_('grid.sort',   'FLEXI_USER_NAME', 'a.username', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_USER_LOGGED', 'loggedin', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_logged')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_logged'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap">
				<?php echo JHtml::_('grid.sort',   'COM_USERS_HEADING_ENABLED', 'a.block', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if (strlen($this->getModel()->getState('filter_state'))) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_state'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap hidden-phone">
				<?php echo JHtml::_('grid.sort',   'COM_USERS_HEADING_ACTIVATED', 'a.activation', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if (strlen($this->getModel()->getState('filter_active'))) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_active'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap">
				<?php echo JText::_( 'FLEXI_USERGROUPS' ); ?>
				<?php if ($this->getModel()->getState('filter_usergrp')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_usergrp'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_ITEMS', 'itemscount', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_itemscount')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_itemscount'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_FILES_MBS', 'uploadssize', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_uploadssize')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_uploadssize'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="hideOnDemandClass left nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_USER_EMAIL', 'a.email', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
			</th>

			<th style="width:110px;" class="hideOnDemandClass">
				<?php echo JHtml::_('grid.sort',   'FLEXI_REGISTRED_DATE', 'a.registerDate', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('startdate');delFilter('enddate'); document.adminForm.submit();"></span>
				</span>
				<?php
					endif;
				endif;
				?>
			</th>

			<th style="width:110px;" class="hideOnDemandClass nowrap">
				<?php echo JHtml::_('grid.sort',   'FLEXI_USER_LAST_VISIT', 'a.lastvisitDate', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('startdate');delFilter('enddate'); document.adminForm.submit();"></span>
				</span>
				<?php
					endif;
				endif;
				?>
			</th>

			<th class="hideOnDemandClass col_id center hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
				<?php if ($this->getModel()->getState('filter_id')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-cancel-circle btn btn-micro" onclick="delFilter('filter_id'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$canManage = FlexicontentHelperPerm::getPerm()->CanAuthors;

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
		$k = 0;

		foreach ($this->rows as $i => $row)
		{
			if (!$row->id)
			{
				continue;
			}

			$row->groupname = array();

			foreach($row->usergroups as $row_ugrp_id)
			{
				$row->groupname[] = $this->usergroups[$row_ugrp_id]->title;
			}

			$row->groupname = implode(', ', $row->groupname);

			$users_task = 'task=users.';
			$edit_link  = 'index.php?option=com_flexicontent&amp;controller=users&amp;view=user&amp;'.$users_task.'edit&amp;id='. $row->id. '';

			$img_path  = '../components/com_flexicontent/assets/images/';
			$tick_img  = $img_path . 'tick.png';

			$block_img   = $img_path . ($row->block ? 'publish_x.png' : 'tick.png');
			$block_task  = 'users.' . ($row->block ? 'unblock' : 'block');
			$block_title = $row->block ? JText::_( 'COM_USERS_TOOLBAR_UNBLOCK' ) : JText::_( 'COM_USERS_USER_FIELD_BLOCK_DESC' );

			$activation_img   = $img_path . ($row->activation ? 'publish_x.png' : 'tick.png');
			$activation_title = $row->activation ? JText::_( 'COM_USERS_UNACTIVATED' ) : JText::_( 'COM_USERS_ACTIVATED' );

			if ($row->lastvisitDate == "0000-00-00 00:00:00") {
				$lvisit = JText::_( 'Never' );
			} else {
				$lvisit	= JHtml::_('date', $row->lastvisitDate, 'Y-m-d H:i:s');
			}
			$registered	= JHtml::_('date', $row->registerDate, 'Y-m-d H:i:s');

			$itemscount = '<span class="badge bg-info badge-info" title="'.$view_entry.'">'.$row->itemscount.'</span>';
			if ($row->itemscount) {
				$itemscount = '
				<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=1&amp;filter_state=ALL&amp;filter_author='.$row->id.'&amp;fcform=1">
					'.$itemscount.'
				</a>';
			}
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_cb">
				<!--div class="adminlist-table-row"></div-->
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_title smaller">
				<a href="<?php echo $edit_link; ?>">
					<?php echo $row->name; ?></a>
			</td>
			<td>
				<!-- <a class="modal" rel="{handler: 'iframe', size: {x: 800, y: 500}, onClose: function() {alert('hello');} }" href="<?php echo $edit_link; ?>"> -->
				<?php echo $row->username; ?>
				<!-- </a> -->
			</td>
			<td class="center col_logged">
				<?php echo $row->loggedin ? '<img src="'.$tick_img.'" width="16" height="16" style="border:0;" class="fc-man-icon-s" alt="" />': ''; ?>
			</td>

			<td class="center col_status">
				<a href="javascript:void(0);" onclick="return Joomla.listItemTask('cb<?php echo $i;?>','<?php echo $block_task;?>')">
					<img src="images/<?php echo $block_img;?>" class="<?php echo $this->tooltip_class; ?> fc-man-icon-s" width="16" height="16" style="border:0;" title="<?php echo $block_title; ?>" alt="<?php echo $block_title; ?>" />
				</a>
			</td>

			<td class="center col_active hidden-phone">
				<img src="images/<?php echo $activation_img;?>" class="<?php echo $this->tooltip_class; ?> fc-man-icon-s" width="16" height="16" style="border:0;" title="<?php echo $activation_title; ?>" alt="<?php echo $activation_title; ?>" />
			</td>

			<td class="center col_usergrp">
				<?php echo JText::_( $row->groupname ); ?>
			</td>
			<td class="center col_itemscount">
				<?php echo $itemscount; ?>
			</td>
			<td class="right col_uploadssize">
				<?php echo number_format(($row->uploadssize / (1024*1024)) , 2); ?>
			</td>
			<td class="left">
				<a href="mailto:<?php echo $row->email; ?>">
					<?php echo $row->email; ?></a>
			</td>
			<td class="nowrap col_registered">
				<?php echo $registered; ?>
			</td>
			<td class="nowrap col_visited">
				<?php echo $lvisit; ?>
			</td>

			<td class="col_id center hidden-phone hidden-tablet">
				<?php echo $row->id; ?>
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
