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
$ctrl     = 'search.';
$hlpname  = 'fcsearch';
$isAdmin  = $app->isClient('administrator');
$useAssocs= false;
$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_entry = JText::_('FLEXI_EDIT', true);
$fcfilter_attrs_row  = ' class="input-prepend fc-xpended-row" ';



/**
 * JS for Columns chooser box and Filters box
 */

flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	'adminListTableFC' . $this->view . ($this->isADV ? '_advanced' : '_basic'),
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

$list_total_cols = $this->isADV
	? 9
	: 4;



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
	delFilter('search_itemtitle');
	delFilter('search_itemid');
	delFilter('filter_itemlang');
	delFilter('filter_type');
	delFilter('filter_state');
	delFilter('filter_fieldtype');
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

					<?php echo $this->lists['search_itemtitle']; ?>
					<?php echo $this->lists['search_itemid']; ?>
					<?php echo $this->lists['filter_itemlang']; ?>
					<?php echo $this->lists['filter_type']; ?>
					<?php echo $this->lists['filter_state']; ?>

					<?php if ($this->isADV) : ?>
						<?php echo $this->lists['filter_fieldtype']; ?>
					<?php endif; ?>

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

	<?php echo '<span class="label">
		' . JText::_('FLEXI_LISTING_RECORDS') . '
	</span>' . $this->lists['filter_indextype']; ?>

	<div class="fcclear"></div>

	<table id="adminListTableFC<?php echo $this->view; ?><?php echo $this->isADV ? '_advanced' : '_basic'; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>

			<!--th class="left hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<!--th class="col_cb left">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th-->

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_ITEM_ID'), 'a.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left title">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_ITEM_TITLE'), 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left language">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_LANGUAGE'), 'a.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

		<?php if ($this->isADV) : ?>
			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_LABEL'), 'f.label', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_FIELD_INDEX').' '.JText::_('FLEXI_FIELD_NAME'), 'f.name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_FIELD_TYPE'), 'f.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_COUNT'), 'ai.extraid', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_INDEX_VALUE_ID'), 'ai.value_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
		<?php endif; ?>

			<th class="hideOnDemandClass left">
				<?php echo JHtml::_('grid.sort', JText::_('FLEXI_SEARCH_INDEX'), ($this->isADV ? 'ai' : 'ext').'.search_index', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$canManage = FlexicontentHelperPerm::getPerm()->CanIndex;
		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
		$k = 0;
		$text_cnt = 0;

		foreach ($this->rows as $i => $row)
		{
			// Permissions
			$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
			$row->canEdit      = $canManage;
			$row->canEditState = $canManage;
			$row->canDelete    = $canManage;

			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<!--td class="col_cb">
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td-->

			<td>
				<?php echo $row->item_id; ?>
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
				echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
					'ctrl'     => 'items',
					'view'     => 'item',
					'onclick'  => 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . JText::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
				));
				?>
			</td>

			<td>
				<?php echo $row->language; ?>
			</td>

			<?php if ($this->isADV) : ?>
				<td>
					<?php echo $this->escape($row->label); ?>
				</td>
				<td>
					<?php echo $this->escape($row->name); ?>
				</td>
				<td class="col_fieldtype">
					<?php echo $row->field_type; ?>
				</td>
				<td class="center">
					<?php echo $row->extraid; ?>
				</td>
				<td class="center">
					<?php echo $row->value_id; ?>
				</td>
			<?php endif; ?>

			<td class="left col_search_index">
				<?php
					$_search_index = !$search_prefix ? $row->search_index : preg_replace('/\b'.$search_prefix.'/u', '', $row->search_index);
					if (iconv_strlen($row->search_index, "UTF-8")> 610)
					{
						echo iconv_substr($_search_index, 0, 300, "UTF-8");
						$text_cnt++;
						?>
						<span id="search_index_text_<?php echo $text_cnt; ?>" style="display: none;">
							<?php echo $_search_index; ?>
						</span>
						<span class="badge" onclick="var box = jQuery('#search_index_text_<?php echo $text_cnt; ?>'); fc_itemelement_view_handle = fc_showAsDialog(box, 800, 0, null, { title: '<?php echo JText::_('FLEXI_MORE', true); ?>'}); return false;" style="cursor: pointer">...</span>
						<?php
						echo iconv_substr($_search_index, -300, 300, "UTF-8");
					}
					else
					{
						echo $_search_index;
					}
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