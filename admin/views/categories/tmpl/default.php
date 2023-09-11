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
$ctrl     = 'categories.';
$hlpname  = 'fccats';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_cat_title  = JText::_('FLEXI_EDIT_CATEGORY', true);
$rem_filt_txt    = JText::_('FLEXI_REMOVE_FILTER', true);
$rem_filt_tip    = ' class="' . $this->tooltip_class . ' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';
$_NEVER_         = JText::_('FLEXI_NEVER');
$_NULL_DATE_     = JFactory::getDbo()->getNullDate();



/**
 * JS for Columns chooser box and Filters box
 */

$this->data_tbl_id = 'adminListTableFC' . $this->view;
flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	$this->data_tbl_id,
	$start_html = '',  //'<span class="badge ' . (FLEXI_J40GE ? 'badge-dark' : 'badge-inverse') . '">' . JText::_('FLEXI_COLUMNS', true) . '<\/span> &nbsp; ',
	$end_html = '<div id="fc-columns-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="' . JText::_('FLEXI_HIDE') . '" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"><\/div>',
	$toggle_on_init = 1 // Initial page load (JS Performance) we already hidden columns via PHP Logic
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

$infoimage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), ' class="fc-man-icon-s" ' );

$state_names = array(
	'ALL_P' => JText::_('FLEXI_PUBLISHED'),
	'ALL_U' => JText::_('FLEXI_UNPUBLISHED'),
	'A'     => JText::_('FLEXI_ARCHIVED'),
	'T'     => JText::_('FLEXI_TRASHED'),
);
$state_icons = array(
	'ALL_P' => 'publish',
	'ALL_U' => 'unpublish',
	'A'     => 'archive',
	'T'     => 'trash',
);


/**
 * Calculate maximum column size of associations
 */

$max_assocs = 0;
foreach($this->rows as $row)
{
	$max_assocs = !empty($this->lang_assocs[$row->id]) && count($this->lang_assocs[$row->id]) > $max_assocs
		? count($this->lang_assocs[$row->id])
		: $max_assocs;
}
$ocLang = $cparams->get('original_content_language', '_site_default_');
$ocLang = $ocLang === '_site_default_' ? JComponentHelper::getParams('com_languages')->get('site', '*') : $ocLang;
$ocLang = $ocLang !== '_disable_' && $ocLang !== '*' ? $ocLang : false;



/**
 * Order stuff and table related variables
 */

$list_total_cols = 12
	+ ($useAssocs ? 2 : 0);

$listOrder = $this->lists['order'];
$listDirn  = $this->lists['order_Dir'];
$saveOrder = ($listOrder == 'a.lft' && strtolower($listDirn) == 'asc');

if ($saveOrder)
{
	$saveOrderingUrl = 'index.php?option=com_flexicontent&task='.$ctrl.'saveOrderAjax&format=raw';
	JHtml::_('sortablelist.sortable', $this->data_tbl_id, 'adminForm', strtolower($listDirn), $saveOrderingUrl, false, true);
}



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
		filter.removeAttr('checked');
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
	jQuery('.fc_field_filter').val('');  // clear custom filters
	delFilter('search');
	delFilter('filter_level');
	delFilter('filter_state');
	delFilter('filter_cats');
	delFilter('filter_author');
	delFilter('filter_id');
	delFilter('filter_lang');
	delFilter('filter_access');
	delFilter('filter_assockey');
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
					echo $this->lists['filter_assockey'];
					echo $this->lists['filter_cats'];
					echo $this->lists['filter_level'];
					echo $this->lists['filter_author'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					echo $this->lists['filter_lang'];
					echo $this->lists['filter_id'];
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


	<table id="<?php echo $this->data_tbl_id; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>
			<?php $colposition = 0; ?>

			<!--th class="left hidden-phone"><?php //$colposition++; ?>
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_order center hidden-phone"><?php $colposition++; ?>
				<?php echo JHtml::_('searchtools.sort', '', 'a.lft', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
			</th>

			<th class="col_cb left"><?php $colposition++; ?>
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="col_status hideOnDemandClass nowrap left" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_STATUS', 'a.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_state')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_state'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="col_title hideOnDemandClass nowrap" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'a.' . $this->title_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
				&nbsp; <small>[
				<?php echo JHtml::_('grid.sort', 'FLEXI_ALIAS', 'a.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				]<small>
				<?php if (strlen($this->getModel()->getState('search'))) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('search'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>

				<?php if (($filter_cats = $this->getModel()->getState('filter_cats'))): ?>
						<?php echo '<br><span class="alert alert-info fcpadded">'.JText::_('FLEXI_PARENT') . ': ' . $globalcats[$filter_cats]->title .'</span>'; ?>
						<span <?php echo $rem_filt_tip; ?>>
							<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_cats'); document.adminForm.submit();"></span>
						</span>
				<?php endif; ?>
			</th>

			<th class="col_lang hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_LANGUAGE', 'a.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_lang')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_lang'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

		<?php if ($useAssocs) : ?>

			<th class="col_assocs_count"><?php $colposition++; ?>
				<div id="fc-toggle-assocs_btn" style="padding: 4px 0 2px 6px;" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?>" title="<?php echo JText::_('FLEXI_ASSOCIATIONS'); ?>" onclick="jQuery('#columnchoose_<?php echo $this->data_tbl_id . '_' . $colposition; ?>_label').click();" >
					<span class="icon-flag"></span>
				</div>
			</th>

			<th class="col_assocs hideOnDemandClass nowrap hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JText::_('FLEXI_ASSOCIATIONS'); ?>
				<?php if ($this->getModel()->getState('filter_assockey')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_assockey'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
		<?php endif; ?>

			<th class="col_template hideOnDemandClass left hidden-phone hidden-tablet" colspan="2" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JText::_('FLEXI_TEMPLATE'); ?>
			</th>

			<!--th class="hideOnDemandClass" style="<?php //echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th-->

			<th class="col_published hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo $state_names['ALL_P']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['ALL_P'].'" title="'.$state_names['ALL_P'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="col_unpublished hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo $state_names['ALL_U']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['ALL_U'].'" title="'.$state_names['ALL_U'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="col_archived hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo $state_names['A']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['A'].'" title="'.$state_names['A'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="col_trashed hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<span class="column_toggle_lbl" style="display:none;"><small class="badge bg-info badge-info"><?php echo $state_names['T']; ?></small></span>
				<?php echo '<span class="' . $this->tooltip_class . ' icon-'.$state_icons['T'].'" title="'.$state_names['T'].' '.JText::_ ('FLEXI_ITEMS').'" data-placement="top" style="font-size: 16px;"></span>'; ?>
			</th>

			<th class="col_access hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_access')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_access'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<!--th class="hideOnDemandClass" style="<?php //echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_REORDER', 'a.lft', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->orderingx ? str_replace('rel="tooltip"', '', JHtml::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' )) : ''; ?>
			</th-->

			<th class="col_authors hideOnDemandClass nowrap left hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_AUTHOR', 'a.created_by', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_author')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_author'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="col_id hideOnDemandClass nowrap center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
				<?php if ($this->getModel()->getState('filter_id')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_id'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<?php if ($this->getModel()->getState('filter_level')) : ?>
			<th class="col_level nowrap hidden-phone">
				<?php if ($this->getModel()->getState('filter_level')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_level'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
				<br>
				<span class="icon-tree"></span>
			</th>
			<?php endif; ?>

		</tr>
	</thead>

	<tbody>
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');

		$originalOrders = array();
		$clayout_bycatid = array();
		$cat_ancestors = array();
		$inheritcid_comp = $cparams->get('inheritcid', -1);

		// Add 1 collapsed row to the empty table to allow border styling to apply
		if (!count($this->rows))
		{
			echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';
		}

		// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
		$k = 0;

		foreach ($this->rows as $i => $row)
		{
			$colposition = 0;
			$assetName = 'com_content.category.'.$row->id;
			$isAuthor  = $row->created_user_id && $row->created_user_id == $user->id;

			// Permissions
			$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
			$row->canEdit      = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
			$row->canEditState = $user->authorise('core.edit.state', $assetName) || ($isAuthor && $user->authorise('core.edit.state.own', $assetName));
			$row->canDelete    = $user->authorise('core.delete', $assetName) || ($isAuthor && $user->authorise('core.delete.own', $assetName));

			$stateIsChangeable = $row->canCheckin && $row->canEditState;

			// Get orderkey
			$orderkey = array_search($row->id, $this->ordering[$row->parent_id]);

			// Get the parents of item for sorting
			if ($row->level > 1)
			{
				$parentsStr = '';
				$_currentParentId = $row->parent_id;
				$parentsStr = ' ' . $_currentParentId;
				for ($i2 = 0; $i2 < $row->level; $i2++)
				{
					foreach ($this->ordering as $m => $v)
					{
						$v = implode('-', $v);
						$v = '-' . $v . '-';
						if (strpos($v, '-' . $_currentParentId . '-') !== false)
						{
							$parentsStr .= ' ' . $m;
							$_currentParentId = $m;
							break;
						}
					}
				}
			}
			else
			{
				$parentsStr = '';
			}

			$inheritcid = $row->config->get('inheritcid', '');
			$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);

			if (!$inherit_parent || $row->parent_id==='1')
			{
				$row_clayout = $row->config->get('clayout', $cparams->get('clayout', 'grid'));
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
						$row_clayout = $cparams->get('clayout', 'grid');
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

			$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_subcats=0&amp;filter_cats='. $row->id.'&amp;fcform=1&amp;filter_state=';
			?>

		<?php if (FLEXI_J40GE) : ?>
		<tr class="row<?php echo $i % 2; ?>" data-draggable-group="<?php echo $row->parent_id; ?>"
			data-item-id="<?php echo $row->id; ?>" data-parents="<?php echo $parentsStr; ?>"
			data-level="<?php echo $row->level; ?>">
		<?php else : ?>
		<tr class="row<?php echo $k % 2; ?>" sortable-group-id="<?php echo $row->parent_id; ?>"
			item-id="<?php echo $row->id ?>" parents="<?php echo $parentsStr ?>"
			level="<?php echo $row->level ?>">
		<?php endif; ?>

			<!--td class="left col_rowcount hidden-phone"><?php //$colposition++; ?>
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_order nowrap center hidden-phone"><?php $colposition++; ?>
				<?php
				$iconClass = '';
				if (!$row->canEdit)
				{
					$iconClass = ' inactive';
				}
				elseif (!$saveOrder)
				{
					$iconClass = ' inactive tip-top hasTooltip" title="' . JHtml::_('tooltipText', 'JORDERINGDISABLED');
				}
				?>
				<span class="sortable-handler<?php echo $iconClass ?>">
					<span class="icon-move"></span>
				</span>
				<?php if ($row->canEdit && $saveOrder) : ?>
					<input type="text" style="display:none" name="order[]" size="5" value="<?php echo $orderkey + 1; ?>" />
				<?php endif; ?>
			</td>

			<td class="col_cb"><?php $colposition++; ?>
				<!--div class="adminlist-table-row"></div-->
				<?php echo JHtml::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_status" style="padding-right: 8px;" style="<?php echo $this->hideCol($colposition++); ?>" >
				<div class="btn-group fc-group fc-categories">
					<?php
					//echo JHtml::_('jgrid.published', $row->published, $i, $ctrl, $stateIsChangeable);
					//echo JHtml::_($hlpname . '.published', $row->published, $i, $stateIsChangeable);

					echo JHtml::_($hlpname . '.statebutton', $row, $i);
					echo JHtml::_($hlpname . '.rss_link', $row, '_blank', $i);
					echo JHtml::_($hlpname . '.preview', $row, '_blank', $i);
					?>
				</div>
			</td>

			<td class="col_title smaller" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
				echo $row->level > 1
					? str_repeat('.&nbsp;&nbsp;', $row->level - 1) . '<sup>|_</sup>&nbsp;'
					: '';

				/**
				 * Display an edit pencil or a check-in button if: either (a) current user has Global
				 * Checkin privilege OR (b) record checked out by current user, otherwise display a lock
				 */
				echo JHtml::_($hlpname . '.checkedout', $row, $user, $i);

				/**
				 * Display title with edit link ... (row editable and not checked out)
				 * Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				 */
				echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, array('ctrl' => 'category'));
				?>

				<?php	if (!empty($row->note)) : ?>
					<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip( JText::_ ('FLEXI_NOTES'), $row->note, 0, 1); ?>">
						<?php echo $infoimage; ?>
					</span>
				<?php endif; ?>

				&nbsp;<small>[<?php echo StringHelper::strlen($row->alias) > 25
						? StringHelper::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25) . '...'
						: htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				?>]</small>
			</td>

			<td class="col_lang small hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
					/**
					 * Display language
					 */
					echo JHtml::_($hlpname . '.lang_display', $row, $i, $this->langs, $use_icon = 2); ?>
			</td>


			<?php if ($useAssocs) : ?>

				<td><?php $colposition++; ?>
					<?php if (!empty($this->lang_assocs[$row->id])): ?>
						<?php $row_assocs = $this->lang_assocs[$row->id]; ?>
						<a href="index.php?option=com_flexicontent&amp;view=categories&amp;filter_assockey=<?php echo reset($row_assocs)->key; ?>&amp;fcform=1&amp;filter_state=ALL&amp;limit=50"
							class="<?php echo $this->btn_sm_class; ?> fc_assocs_count"
						>
							<?php echo count($row_assocs); ?>
						</a>
					<?php endif; ?>
				</td>

				<td class="hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
					<?php
					if (!empty($this->lang_assocs[$row->id]))
					{
						// Find record of original content
						$oc_item = null;
						foreach($this->lang_assocs[$row->id] as $assoc_item)
						{
							if ($ocLang && $assoc_item->language === $ocLang)
							{
								$oc_item = $assoc_item;
								break;
							}
						}

						if ($oc_item)
						{
							$oc_item_modified_date = $oc_item->modified && $oc_item->modified !== '0000-00-00 00:00:00' ? $oc_item->modified : $oc_item->created;
							$oc_item_modified = strtotime($oc_item_modified_date);
						}

						foreach($this->lang_assocs[$row->id] as $assoc_item)
						{
							// Joomla article manager show also current item, so we will not skip it
							$is_oc_item = !$oc_item || $assoc_item->id == $oc_item->id;
							$assoc_modified_date = $assoc_item->modified && $assoc_item->modified !== '0000-00-00 00:00:00' ? $assoc_item->modified : $assoc_item->created;
							$assoc_modified = strtotime($assoc_modified_date);

							$_link  = 'index.php?option=com_flexicontent&amp;task='.$ctrl.'edit&amp;cid='. $assoc_item->id;
							$_title = flexicontent_html::getToolTip(
								$assoc_item->title,
								(isset($state_icons[$assoc_item->state]) ? '<span class="' . $state_icons[$assoc_item->state] . '"></span>' : '') .
								(isset($state_names[$assoc_item->state]) ? $state_names[$assoc_item->state] . '<br>': '') .
								($is_oc_item ? '' : '<span class="icon-pencil"></span>' . JText::_( $assoc_modified < $oc_item_modified ? 'FLEXI_TRANSLATION_IS_OUTDATED' : 'FLEXI_TRANSLATION_IS_UPTODATE')) .
								': ' . $assoc_modified_date . '<br>'.
								( !empty($this->langs->{$assoc_item->lang}) ? ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" /> ' : '').
								($assoc_item->lang === '*' ? JText::_('FLEXI_ALL') : (!empty($this->langs->{$assoc_item->lang}) ? $this->langs->{$assoc_item->lang}->name: '?')).' <br/> '
								, 0, 1
							);

							$state_colors = array(1 => ' fc_assoc_ispublished', -5 => ' fc_assoc_isinprogress');
							$assoc_state_class   = isset($state_colors[$assoc_item->state]) ? $state_colors[$assoc_item->state] : ' fc_assoc_isunpublished';
							$assoc_isstale_class = $oc_item && ($assoc_modified < $oc_item_modified) ? ' fc_assoc_isstale' : ' fc_assoc_isuptodate';

							echo '
							<a class="fc_assoc_translation label label-association ' . $this->popover_class . $assoc_isstale_class . $assoc_state_class . '"
								target="_blank" href="'.$_link.'" data-placement="top" data-content="'.$_title.'"
							>
								<span>' . ($assoc_item->lang=='*' ? JText::_('FLEXI_ALL') : strtoupper($assoc_item->shortcode ?: '?')) . '</span>
							</a>';
						}
					}
					?>
				</td>

			<?php endif ; ?>


			<td class="col_edit_layout hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition); ?>" >
				<?php echo JHtml::_($hlpname . '.edit_layout', $row, '__modal__', $i, $this->perms->CanTemplates, $row_clayout); ?>
			</td>

			<td class="col_template small hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->config->get('clayout') ? $row->config->get('clayout') : ($row_clayout ? $row_clayout : '...').'<span class="badge">inherited</span>'; ?>
			</td>

			<?php /*<td>
				<a href="<?php echo $items_link; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:unset;">
					<span class="badge bg-info badge-info"><?php echo $row->nrassigned; ?></span>
				</a>
			</td>*/ ?>

			<?php
				$c_p = (int) @ $row->byStateTotals[1] + (int) @ $row->byStateTotals[-5];
				$c_u = (int) @ $row->byStateTotals[0] + (int) @ $row->byStateTotals[-3] + (int) @ $row->byStateTotals[-4];
				$c_a = (int) @ $row->byStateTotals[2];
				$c_t = (int) @ $row->byStateTotals[-2];
			?>

			<td class="col_published center hidden-phone hidden-tablet" style="padding: 0; <?php echo $this->hideCol($colposition++); ?>" >
				<a href="<?php echo $items_link.'ALL_P'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_p ? ' badge-success' : ''; ?>">
					<?php echo $c_p ? $c_p : '0'; ?>
				</a>
			</td>
			<td class="col_unpublished center hidden-phone hidden-tablet" style="padding: 0; <?php echo $this->hideCol($colposition++); ?>" >
				<a href="<?php echo $items_link.'ALL_U'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_u ? ' badge-important' : ''; ?>">
					<?php echo $c_u ? $c_u : '0'; ?>
				</a>
			</td>
			<td class="col_archived center hidden-phone hidden-tablet" style="padding: 0; <?php echo $this->hideCol($colposition++); ?>" >
				<a href="<?php echo $items_link.'A'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_a ? ' badge-info' : ''; ?>">
					<?php echo $c_a ? $c_a : '0'; ?>
				</a>
			</td>

			<td class="col_trashed center hidden-phone hidden-tablet" style="padding: 0; <?php echo $this->hideCol($colposition++); ?>" >
				<a href="<?php echo $items_link.'T'; ?>" title="<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>" style="color:white; margin: 0;" class="badge <?php echo $c_t ? ' badge-inverse' : ''; ?>">
					<?php echo $c_t ? $c_t : '0'; ?>
				</a>
			</td>

			<td class="col_access hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->canEdit
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'class="fcfield_selectval" onchange="return Joomla.listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"')
					: $this->escape($row->access_level);
				?>
			</td>

			<td class="col_authors small hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->author; ?>
			</td>

			<td class="col_id center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->id; ?>
			</td>

			<?php if ($this->getModel()->getState('filter_level')) : ?>
			<td class="col_level hidden-phone">
				<?php echo $this->escape($row->level); ?>
			</td>
			<?php endif; ?>

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


	<!-- This manager form fields -->
	<input type="hidden" name="original_order_values" value="<?php echo implode(',', $originalOrders); ?>" />

	<!-- Common management form fields -->
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
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
