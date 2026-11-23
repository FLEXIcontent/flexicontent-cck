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
require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html/fc_service_registry.php';
flexicontent_register_html_helpers(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');

global $globalcats;
$app      = \Joomla\CMS\Factory::getApplication();
$jinput   = $app->input;
$config   = \Joomla\CMS\Factory::getApplication()->getConfig();
$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
$session  = \Joomla\CMS\Factory::getApplication()->getSession();
$document = \Joomla\CMS\Factory::getApplication()->getDocument();
$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
$ctrl     = 'reviews.';
$hlpname  = 'fcreviews';
$isAdmin  = $app->isClient('administrator');
$useAssocs= false;



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';



/**
 * Resizable / movable / hideable columns (replaces the old column chooser), settings are stored in the browser
 */

$document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/css/fc_table_tools.css', array('version' => FLEXI_VHASH));
$document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/css/fc_type_tabs.css', array('version' => FLEXI_VHASH));
$document->addScript(\Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/js/fc-table-tools.js', array('version' => FLEXI_VHASH));

foreach (array('FLEXI_TBL_MOVE_COL', 'FLEXI_TBL_HIDE_COL', 'FLEXI_TBL_SHOW_COL', 'FLEXI_TBL_FIT_COL', 'FLEXI_TBL_RESET_WIDTHS', 'FLEXI_TBL_RESET_COLS') as $fc_txt)
{
	\Joomla\CMS\Language\Text::script($fc_txt);
}



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

$list_total_cols = 13
	+ ($useAssocs ? 1 : 0);

// Date format of the list, same as in the items list
$date_format = \Joomla\CMS\Language\Text::_('FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE');
$_NEVER_     = \Joomla\CMS\Language\Text::_('FLEXI_NEVER');

// Number of stars used by the voting (reviews) field, for displaying / changing the rating of reviews
$rv_stars = flexicontent_html::reviewStarsCount();

// Speech bubble icon, used as the header of the column with the number of reviews of each item
$rv_icon_reviews = '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false" style="width:16px;height:16px;vertical-align:-2px;"><path fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64v288c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"/></svg>';

// Thumbs-up icon, used as the header of the column with the 'helpful' votes of each review
$rv_icon_helpful = '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false" style="width:16px;height:16px;vertical-align:-2px;"><path fill="currentColor" d="M313.4 32.9c26 5.2 42.9 30.5 37.7 56.5l-2.3 11.4c-5.3 26.7-15.1 52.1-28.8 75.2H464c26.5 0 48 21.5 48 48c0 18.5-10.5 34.6-25.9 42.6C497 275.4 504 288.9 504 304c0 23.4-16.8 42.9-38.9 47.1c4.4 7.3 6.9 15.8 6.9 24.9c0 21.3-13.9 39.4-33.1 45.6c.7 3.3 1.1 6.8 1.1 10.4c0 26.5-21.5 48-48 48H294.5c-19 0-37.5-5.6-53.3-16.1l-38.5-25.7C176 420.4 160 390.4 160 358.3V320 272 247.1c0-29.2 13.3-56.7 36-75l7.4-5.9c26.5-21.2 44.6-51 51.2-84.2l2.3-11.4c5.2-26 30.5-42.9 56.5-37.7zM32 192H96c17.7 0 32 14.3 32 32V448c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V224c0-17.7 14.3-32 32-32z"/></svg>';

// JS for changing the rating directly in the list (each star is a button)
$rv_star_svg = '<svg viewBox="0 0 576 512" aria-hidden="true" focusable="false" style="width:1.3em;height:1.3em;display:block;"><path fill="currentColor" d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 327 113.2 469.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 327 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z"/></svg>';

\Joomla\CMS\Factory::getApplication()->getDocument()->addScriptDeclaration("
	function fcReviewSetRating(btn)
	{
		var wrap  = btn.parentNode;
		var value = parseInt(btn.getAttribute('data-value'), 10);
		var stars = parseInt(wrap.getAttribute('data-stars'), 10);
		var body  = new URLSearchParams();
		body.set('id', wrap.getAttribute('data-id'));
		body.set('rating', Math.round(100 * value / stars));
		body.set(" . json_encode(\Joomla\CMS\Session\Session::getFormToken()) . ", '1');

		wrap.style.opacity = '0.5';

		fetch('index.php?option=com_flexicontent&task=reviews.setrating', {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			wrap.style.opacity = '';
			if (data.error) { alert(data.msg || 'Error'); return; }
			var s = wrap.querySelectorAll('button');
			for (var i = 0; i < s.length; i++) s[i].style.color = (i < value) ? '#f5a623' : '#ccc';
		})
		.catch(function(err) {
			wrap.style.opacity = '';
			alert('Error: ' + (err.message || err));
		});
		return false;
	}
");



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

// Show only the reviews of a specific item (click on the reviews counter of the item)
function fcReviewsOfItem(id)
{
	var form = document.adminForm;
	var sel  = form.elements['filter_item[]'];

	// Reset the other filters so that the list matches the counter
	['filter_state', 'filter_approved', 'filter_hastext', 'filter_verified', 'search'].forEach(function(name) {
		if (form.elements[name]) form.elements[name].value = '';
	});
	if (form.elements['filter_user[]']) {
		for (var u = 0; u < form.elements['filter_user[]'].options.length; u++) form.elements['filter_user[]'].options[u].selected = false;
	}

	var found = false;
	for (var i = 0; i < sel.options.length; i++) {
		sel.options[i].selected = (parseInt(sel.options[i].value, 10) === id);
		found = found || sel.options[i].selected;
	}
	if (!found) {
		sel.options[sel.options.length] = new Option(String(id), String(id), true, true);
	}

	if (!!form.limitstart) form.limitstart.value = 0;
	Joomla.submitform();
	return false;
}

function delAllFilters()
{
	delFilter('search');
	delFilter('filter_state');
	delFilter('filter_approved');
	delFilter('filter_hastext');
	delFilter('filter_verified');
	delFilter('filter_item');
	delFilter('filter_user');
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


	<div id="fc-managers-header" class="fc-mh-items fc-mh-solo">

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
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : \Joomla\CMS\Language\Text::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="fcfield_textval" />
				<button title="" data-original-title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : \Joomla\CMS\Language\Text::_('FLEXI_GO'); ?></button>

				<div id="fc_filters_box_btn" data-original-title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $out_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : \Joomla\CMS\Language\Text::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
				</div>

				<?php
				$filters_modern = (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('backend_filters_style', 1) === 1;
				$filters_apply_btn = $filters_modern && (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('backend_filters_apply', 1) === 1;
				?>
				<div id="fc-filters-box" <?php if (!$this->count_filters || empty($tools_state->filters_box)) echo 'style="display:none;"'; ?> class="fcman-abs<?php echo $filters_modern ? ' fc-filters-modern' : ''; ?>">
					<?php
					echo $this->lists['filter_item'];
					echo $this->lists['filter_user'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_approved'];
					echo $this->lists['filter_hastext'];
					echo $this->lists['filter_verified'] ?? '';
					?>

					<?php if ($filters_apply_btn) : ?>
					<div class="fc-filters-actions">
						<button type="button" class="btn btn-primary fc-filters-apply" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();">
							<span class="icon-filter"></span> <?php echo \Joomla\CMS\Language\Text::_('FLEXI_APPLY'); ?>
						</button>
					</div>
					<?php endif; ?>

					<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
				</div>

				<?php if ($filters_apply_btn) : ?>
				<script>
					/* Apply-button mode: changing a filter inside the box does not reload the list immediately.
					   The filters are applied by the Apply button or by clicking outside of the box. */
					(function() {
						var box = document.getElementById('fc-filters-box');
						if (!box) return;
						var dirty = false;
						var els = box.querySelectorAll('[onchange*="submitform"]');
						for (var i = 0; i < els.length; i++) {
							els[i].removeAttribute('onchange');
							els[i].onchange = null;
						}
						var markDirty = function() { dirty = true; };
						box.addEventListener('change', markDirty, true);
						if (window.jQuery) jQuery(box).on('change', markDirty);
						document.addEventListener('mousedown', function(e) {
							if (!dirty || box.contains(e.target)) return;
							dirty = false;
							if (!!document.adminForm.limitstart) document.adminForm.limitstart.value = 0;
							Joomla.submitform();
						}, true);
					})();
				</script>
				<?php endif; ?>

				<button title="" data-original-title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : \Joomla\CMS\Language\Text::_('FLEXI_CLEAR'); ?></button>
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
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_cb left" data-col="cb">
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo \Joomla\CMS\Language\Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="hideOnDemandClass left" data-col="status">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_STATUS', 'a.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass title" data-col="title">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_TITLE', 'a.' . $this->title_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass left" data-col="item">
				<?php echo \Joomla\CMS\Language\Text::_('FLEXI_ITEM'); ?>
			</th>

			<th class="hideOnDemandClass center" data-col="rating">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_REVIEW_RATING', 'a.average_rating', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="col_reviews hideOnDemandClass center" data-col="reviews">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS'); ?></small></span>
				<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS'); ?>" data-placement="top"><?php echo $rv_icon_reviews; ?></span>
			</th>

			<th class="col_helpful hideOnDemandClass center" data-col="helpful">
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_HELPFUL'); ?></small></span>
				<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_HELPFUL_YES_NO'); ?>" data-placement="top"><?php echo $rv_icon_helpful; ?></span>
			</th>

			<th class="hideOnDemandClass center" data-col="approved">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_REVIEW_APPROVED', 'a.approved', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center" data-col="verified">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_REVIEW_VERIFIED', 'a.verified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet" data-col="reviewer">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_REVIEW_REVIEWER', 'a.user_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet" data-col="email">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_REVIEW_EMAIL', 'a.email', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone" data-col="created">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_CREATED', 'a.submit_date', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass center hidden-phone hidden-tablet" data-col="modified">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_MODIFIED', 'a.update_date', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass col_id center hidden-phone hidden-tablet" data-col="id">
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
			</th>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		$canManage = FlexicontentHelperPerm::getPerm()->CanReviews;
		$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;fcform=1&amp;filter_id=';

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

			$content = $row->type === 'item' && isset($this->contentRows[$row->content_id])
				? $this->contentRows[$row->content_id]
				: null;
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_cb" data-col="cb">
				<!--div class="adminlist-table-row"></div-->
				<?php echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_status" style="padding-right: 8px;" data-col="status">
				<div class="btn-group fc-group fc-reviews">
					<?php
					//echo \Joomla\CMS\HTML\HTMLHelper::_('jgrid.published', $row->published, $i, $ctrl, $stateIsChangeable);
					//echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.published', $row->published, $i, $stateIsChangeable);

					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.statebutton', $row, $i);
					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.preview', $content, '_blank', $i, '#review_' . $row->id);
					?>
				</div>
			</td>

			<td class="col_title smaller" data-col="title">
				<?php
				/**
				 * Display an edit pencil or a check-in button if: either (a) current user has Global
				 * Checkin privilege OR (b) record checked out by current user, otherwise display a lock
				 */
				echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.checkedout', $row, $user, $i);

				/**
				 * Display title with edit link ... (row editable and not checked out)
				 * Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				 */
				// Reviews without title: placeholder as link text (a review that has only a rating, created by voting with the stars, has neither title nor text)
				if (trim((string) $row->title) === '')
				{
					$row->custom_title = \Joomla\CMS\Language\Text::_(trim((string) $row->text) === '' ? 'FLEXI_REVIEW_RATING_ONLY' : 'FLEXI_REVIEW_NO_TITLE');
				}

				echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit);
				?>
			</td>

			<td class="left" data-col="item">
				<?php
				/**
				 * Display the title of the reviewed item, linked to the item's edit form
				 */
				if ($content)
				{
					$canEditItem = $user->authorise('core.edit', 'com_content.article.' . (int) $content->id)
						|| ($user->authorise('core.edit.own', 'com_content.article.' . (int) $content->id) && (int) $content->created_by === (int) $user->id);

					// Edit pencil in front of the title (as in the Title column), only if the item can be edited
					echo $canEditItem ? '<span class="icon-pencil" style="margin-inline-end: 0.4em;"></span>' : '';
					echo \Joomla\CMS\HTML\HTMLHelper::_('fcitems.edit_link', $content, $i, $canEditItem);
				}
				else
				{
					echo '-';
				}
				?>
			</td>

			<td class="center nowrap" data-col="rating">
				<?php
				/**
				 * Display the rating (stars) given by the reviewer
				 */
				if ($canManage)
				{
					$rv_value = (int) round(((int) $row->average_rating) * $rv_stars / 100);

					echo '<span class="fc-review-rating-edit" data-id="' . (int) $row->id . '" data-stars="' . $rv_stars . '" style="display:inline-flex;gap:1px;">';

					for ($rv_i = 1; $rv_i <= $rv_stars; $rv_i++)
					{
						echo '<button type="button" data-value="' . $rv_i . '" title="' . $rv_i . ' / ' . $rv_stars . '" aria-label="' . $rv_i . ' / ' . $rv_stars . '"'
							. ' onclick="return fcReviewSetRating(this);"'
							. ' style="background:none;border:0;padding:0;margin:0;cursor:pointer;color:' . ($rv_i <= $rv_value ? '#f5a623' : '#ccc') . ';">' . $rv_star_svg . '</button>';
					}

					echo '</span>';
				}
				else
				{
					echo (int) $row->average_rating > 0
						? flexicontent_html::reviewStars($row->average_rating, $rv_stars, '1.2em')
						: '-';
				}
				?>
			</td>

			<td class="col_reviews center" style="padding: 0;" data-col="reviews">
				<?php
				/**
				 * Number of reviews of the item, click to list only the reviews of this item
				 */
				if ($content)
				{
					$rv_count = (int) ($this->reviewCounts[$row->content_id] ?? 0);
					?>
					<a href="#" onclick="return fcReviewsOfItem(<?php echo (int) $row->content_id; ?>);"
						title="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_COUNT_OF_ITEM'), ENT_QUOTES, 'UTF-8'); ?>"
						style="color:white; margin: 0;" class="badge <?php echo $this->tooltip_class; ?>"><?php echo $rv_count; ?></a>
					<?php
				}
				else
				{
					echo '-';
				}
				?>
			</td>

			<td class="col_helpful center" style="padding: 0;" data-col="helpful">
				<?php
				/**
				 * Number of 'helpful' votes of the review, Yes / No (nothing is displayed if there are no votes)
				 */
				$hl_yes = (int) ($row->useful_yes ?? 0);
				$hl_no  = (int) ($row->useful_no ?? 0);

				if ($hl_yes || $hl_no)
				{
					$hl_tip = \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_HELPFUL') . ' ' . ($hl_no
						? ($hl_yes ? \Joomla\CMS\Language\Text::sprintf('FLEXI_REVIEW_HELPFUL_TIP_YES_NO', $hl_yes, $hl_no) : \Joomla\CMS\Language\Text::sprintf('FLEXI_REVIEW_HELPFUL_TIP_NO', $hl_no))
						: \Joomla\CMS\Language\Text::sprintf('FLEXI_REVIEW_HELPFUL_TIP_YES', $hl_yes));
					?>
					<span title="<?php echo htmlspecialchars($hl_tip, ENT_QUOTES, 'UTF-8'); ?>"
						style="color:white; margin: 0;" class="badge <?php echo $this->tooltip_class; ?>"><?php echo $hl_yes . '/' . $hl_no; ?></span>
					<?php
				}
				?>
			</td>

			<td class="center" data-col="approved">
				<?php
				/**
				 * Display review approved toggler
				 */
				echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.approved', $row, $i);
				?>
			</td>

			<td class="center" data-col="verified">
				<?php
				/**
				 * Display review approved toggler
				 */
				switch ($row->verified)
				{
					case 0: echo \Joomla\CMS\Language\Text::_('FLEXI_NA'); break;
					case 1: echo \Joomla\CMS\Language\Text::_('FLEXI_YES'); break;
					case 2: echo \Joomla\CMS\Language\Text::_('FLEXI_NA'); break;
					default: echo $row->verified; break;
				}
				?>
			</td>

			<td class="center hidden-phone hidden-tablet" data-col="reviewer">
				<?php
				if ($row->author_name)
				{
					echo htmlspecialchars($row->author_name, ENT_QUOTES, 'UTF-8');
				}
				elseif (trim((string) $row->name) !== '')
				{
					// Guest: the name given in the review form, marked as a guest
					echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8') . ' <span class="badge bg-secondary badge-secondary" style="margin:0;">' . \Joomla\CMS\Language\Text::_('FLEXI_GUEST') . '</span>';
				}
				else
				{
					echo \Joomla\CMS\Language\Text::_('FLEXI_GUEST');
				}
				?>
			</td>

			<td class="center hidden-phone hidden-tablet" data-col="email">
				<?php echo $row->email; ?>
			</td>

			<td class="center hidden-phone" data-col="created">
				<?php echo ($row->submit_date && strpos($row->submit_date, '0000') !== 0 && strpos($row->submit_date, '1000') !== 0)
					? \Joomla\CMS\HTML\HTMLHelper::_('date', $row->submit_date, $date_format)
					: $_NEVER_; ?>
			</td>

			<td class="center hidden-phone hidden-tablet" data-col="modified">
				<?php echo ($row->update_date && strpos($row->update_date, '0000') !== 0 && strpos($row->update_date, '1000') !== 0)
					? \Joomla\CMS\HTML\HTMLHelper::_('date', $row->update_date, $date_format)
					: $_NEVER_; ?>
			</td>

			<td class="col_id center hidden-phone hidden-tablet" data-col="id">
				<?php echo $row->id; ?>
			</td>

		</tr>
		<?php
			$k++;
		}
		?>
	</tbody>

	</table>
	<script>window.fcTableTools && fcTableTools.init('adminListTableFC<?php echo $this->view; ?>');</script>


	<div>
		<?php echo $pagination_footer; ?>
	</div>

	<div style="margin-top: 48px;">
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-publish" style="font-size: 16px;"></span> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_PUBLISHED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-unpublish" style="font-size: 16px;"></span> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_UNPUBLISHED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-archive" style="font-size: 16px;"></span> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ARCHIVED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-trash" style="font-size: 16px;"></span>	<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TRASHED' ); ?></div>
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
	<?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
