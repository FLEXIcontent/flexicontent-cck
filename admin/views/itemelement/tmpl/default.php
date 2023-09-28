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
use Joomla\CMS\Language\Text;;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

HTMLHelper::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');

global $globalcats;
$app      = Factory::getApplication();
$jinput   = $app->input;
$config   = Factory::getConfig();
$user     = Factory::getUser();
$session  = Factory::getSession();
$document = Factory::getDocument();
$cparams  = ComponentHelper::getParams('com_flexicontent');
$ctrl     = 'items.';
$hlpname  = 'fcitemelement';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();

$editor   = $jinput->getCmd('editor', '');
$isXtdBtn = $jinput->getCmd('isxtdbtn', '');
$function = $jinput->getCmd('function', 'jSelectFcitem');
$onclick  = $this->escape($function);

if (!empty($editor))
{
	// This view is used also in com_menus. Load the xtd script only if the editor is set!
	Factory::getDocument()->addScriptOptions('xtd-fcitems', array('editor' => $editor));
	$onclick = "jSelectFcitem";
}

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

$filter_type = $this->getModel()->getState('filter_type');
$single_type = $filter_type ?: 0;
$disable_columns = $this->tparams->get('iman_skip_cols', array('single_type'));
$disable_columns = array_flip($disable_columns);

// ID of specific type if one type is selected otherwise ZERO
$single_type_id = $single_type && is_array($filter_type) ? reset($filter_type) : $single_type;

$this->data_tbl_id = 'adminListTableFC' . $this->view . '_type_' . $single_type_id;
flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	$this->data_tbl_id,
	$start_html = '',  //'<span class="badge ' . (FLEXI_J40GE ? 'badge-dark' : 'badge-inverse') . '">' . Text::_('FLEXI_COLUMNS', true) . '<\/span> &nbsp; ',
	$end_html = '<div id="fc-columns-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="' . Text::_('FLEXI_HIDE') . '" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"><\/div>',
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

$state_names = array(
	 1  => Text::_('FLEXI_PUBLISHED'),
	-5  => Text::_('FLEXI_IN_PROGRESS'),
	 0  => Text::_('FLEXI_UNPUBLISHED'),
	-3  => Text::_('FLEXI_PENDING'),
	-4  => Text::_('FLEXI_TO_WRITE'),
	 2  => Text::_('FLEXI_ARCHIVED'),
	-2  => Text::_('FLEXI_TRASHED'),
	'u' => Text::_('FLEXI_UNKNOWN'),
);
$state_icons = array(
	 1  => 'icon-publish',
	-5  => 'icon-checkmark-2',
	 0  => 'icon-unpublish',
	-3  => 'icon-question',
	-4  => 'icon-pencil-2',
	 2  => 'icon-archive',
	-2  => 'icon-trash',
	'u' => 'icon-question-2',
);


/**
 * Calculate maximum column size of associations, and max assigned cats and tags for all rows
 */

$max_assocs   = 0;
$max_tags_cnt = 0;
$max_cats_cnt = 0;

foreach($this->rows as $row)
{
	$max_assocs = !empty($this->lang_assocs[$row->id]) && count($this->lang_assocs[$row->id]) > $max_assocs
		? count($this->lang_assocs[$row->id])
		: $max_assocs;

}
$ocLang = $cparams->get('original_content_language', '_site_default_');
$ocLang = $ocLang === '_site_default_' ? ComponentHelper::getParams('com_languages')->get('site', '*') : $ocLang;
$ocLang = $ocLang !== '_disable_' && $ocLang !== '*' ? $ocLang : false;



/**
 * Order stuff and table related variables
 */

$list_total_cols = 9
	+ ($useAssocs ? 1 : 0);



/**
 * Add inline JS
 */

$js = '';

$js .= (!$isXtdBtn ? "" : "
(function() {
	/**
	 * Javascript to insert the link
	 * View element calls jSelectFcitem when a fcitem is clicked
	 * jSelectFcitem creates the link tag, sends it to the editor,
	 * and closes the select frame.
	 **/
	window.jSelectFcitem = function(id, title, catid, object, link, lang)
	{
		var hreflang = '', editor, tag;

		if (!Joomla.getOptions('xtd-fcitems')) {
			// Something went wrong!
			if (window.parent.Joomla.Modal) window.parent.Joomla.Modal.getCurrent().close();
			else if (window.parent.jModalClose) window.parent.jModalClose();
			return false;
		}

		editor = Joomla.getOptions('xtd-fcitems').editor;

		if (lang !== '')
		{
			hreflang = ' hreflang=\"' + lang + '\"';
		}

		tag = '<a' + hreflang + ' href=\"' + link + '\">' + title + '</a>';

		/** Use the API, if editor supports it **/
		if (window.parent.Joomla && window.parent.Joomla.editors && window.parent.Joomla.editors.instances && window.parent.Joomla.editors.instances.hasOwnProperty(editor)) {
			window.parent.Joomla.editors.instances[editor].replaceSelection(tag)
		} else {
			window.parent.jInsertEditorText(tag, editor);
		}

		if (window.parent.Joomla.Modal) window.parent.Joomla.Modal.getCurrent().close();
		else if (window.parent.jModalClose) window.parent.jModalClose();
		return false;
	};

	document.addEventListener('DOMContentLoaded', function(){
		// Get the elements
		var elements = document.querySelectorAll('.select-link');

		for(var i = 0, l = elements.length; l>i; i++) {
			// Listen for click event
			elements[i].addEventListener('click', function (event) {
				event.preventDefault();
				var functionName = event.target.getAttribute('data-function');

				if (functionName === 'jSelectFcitem') {
					// Used in xtd_fcitems
					window[functionName](event.target.getAttribute('data-id'), event.target.getAttribute('data-title'), event.target.getAttribute('data-cat-id'), null, event.target.getAttribute('data-uri'), event.target.getAttribute('data-language'));
				} else {
					// Used in com_menus
					window.parent[functionName](
						event.target.getAttribute('data-id'),
						event.target.getAttribute('data-title'),
						event.target.getAttribute('data-cat-id'),
						null,
						event.target.getAttribute('data-uri'),
						event.target.getAttribute('data-language')
					);
				}
			})
		}
	});
})();

") . "


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
	delFilter('filter_type');
	delFilter('filter_state');
	delFilter('filter_cats');
	delFilter('filter_author');
	delFilter('filter_id');
	delFilter('startdate');
	delFilter('enddate');
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


<form action="index.php" method="post" name="adminForm" id="adminForm">

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
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : Text::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="fcfield_textval" />
				<button title="" data-original-title="<?php echo Text::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : Text::_('FLEXI_GO'); ?></button>

				<div id="fc_filters_box_btn" data-original-title="<?php echo Text::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $out_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : Text::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
				</div>

				<div id="fc-filters-box" <?php if (!$this->count_filters || empty($tools_state->filters_box)) echo 'style="display:none;"'; ?> class="fcman-abs" onclick="var event = arguments[0] || window.event; event.stopPropagation();">
					<?php
					echo $this->lists['filter_assockey'];
					echo $this->lists['filter_author'];
					echo $this->lists['filter_type'];
					echo $this->lists['filter_lang'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					echo $this->lists['filter_cats'];
					?>

					<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo Text::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
				</div>

				<button title="" data-original-title="<?php echo Text::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : Text::_('FLEXI_CLEAR'); ?></button>
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
				<?php echo Text::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_status hideOnDemandClass nowrap left" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_STATUS', 'a.' . $this->state_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_state') || $this->getModel()->getState('filter_catsinstate') != 1) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_state'); jQuery('#filter_catsinstate').val('1'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>

			<th class="col_title hideOnDemandClass nowrap left" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_TITLE', 'a.' . $this->title_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if (strlen($this->getModel()->getState('search'))) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('search'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>


			<?php if (!isset($disable_columns['author'])) : ?>
			<th class="col_authors hideOnDemandClass nowrap left hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_AUTHOR', 'a.created_by', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_author')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_author'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['lang'])) : ?>
			<th class="col_lang hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_LANGUAGE', 'a.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_lang')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_lang'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if ($useAssocs && !isset($disable_columns['assocs'])) : ?>
			<th class="col_assocs_count"><?php $colposition++; ?>
				<div id="fc-toggle-assocs_btn" style="padding: 4px 0 2px 6px;" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?>" title="<?php echo Text::_('FLEXI_ASSOCIATIONS'); ?>" onclick="jQuery('#columnchoose_<?php echo $this->data_tbl_id . '_'. $colposition; ?>_label').click();" >
					<span class="icon-flag"></span>
				</div>
			</th>

			<th class="col_assocs hideOnDemandClass nowrap hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo Text::_('FLEXI_ASSOCIATIONS'); ?>
				<?php if ($this->getModel()->getState('filter_assockey')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_assockey'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (/*!$single_type ||*/ !isset($disable_columns['single_type'])): ?>
			<th class="col_type hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_type')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_type'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['access'])): ?>
			<th class="col_access hideOnDemandClass nowrap left hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_access')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_access'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['cats'])): ?>
			<th class="col_cats hideOnDemandClass nowrap left hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_MAIN_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_cats') || $this->getModel()->getState('filter_subcats') == 0) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_cats'); jQuery('#filter_subcats').attr('checked', 'checked'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['id'])) : ?>
			<th class="col_id hideOnDemandClass nowrap center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
				<?php if ($this->getModel()->getState('filter_id')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_id'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


		</tr>
	</thead>

	<tbody>
		<?php

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
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone"><?php //$colposition++; ?>
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_status" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php $state = isset($state_icons[$row->state]) ? $row->state : 'u'; ?>
				<span class="<?php echo $state_icons[$state]; ?>" title="<?php echo $state_names[$state]; ?>"></span>
			</td>

			<td class="col_title smaller" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
					// Do not activate row selection link if doing associations and the record is in use
					$row_assoc_cnt = empty($this->lang_assocs[$row->id]) ? 0 : count($this->lang_assocs[$row->id]);
					$assoc_data = array();
					if ($row_assoc_cnt)
					{
						foreach($this->lang_assocs[$row->id] as $assoc)
						{
							$assoc_data[str_replace('-', '_', $assoc->language)] = (object) array(
								'item_id' => $assoc->item_id,
								'id' => $assoc->id,
								'title' => $assoc->title,
								'language' => $assoc->language,
							);
						}
					}

					$parentcats_ids = isset($globalcats[$row->catid]) ? $globalcats[$row->catid]->ancestorsarray : array();
					$pcpath = array();

					foreach($parentcats_ids as $pcid)
					{
						$pcpath[] = $globalcats[$pcid]->title;
					}

					$pcpath = implode(' / ', $pcpath);
				?>

					<?php if ($isXtdBtn): ?>

						<?php $attribs = 'data-function="' . $this->escape($onclick) . '"'
							. ' data-id="' . $this->escape($row->id) . '"'
							. ' data-title="' . $this->escape($row->title) . '"'
							. ' data-cat-id="' . $this->escape($row->catid) . '"'
							. ' data-uri="' . $this->escape(FlexicontentHelperRoute::getItemRoute($row->id, $row->catid, $_Itemid = 0, $row)) . '"'
							. ' data-language="' . $this->escape($row->language) . '"';
						?>
					<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo HTMLHelper::tooltipText(Text::_('FLEXI_SELECT'), $row->title . '<br/><br/>' . $pcpath, 0, 1); ?>">
						<a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					</span>

					<?php elseif ($this->assocs_id): ?>

						<?php if(true): /* Always allow */ ?>
							<a class="btn" style="cursor: pointer;" href="javascript:;"
								data-assocs="<?php echo str_replace('"', '_QUOTE_', json_encode($assoc_data, JSON_UNESCAPED_UNICODE)); ?>"
								onclick="window.parent.fcSelectItem('<?php echo $row->id; ?>', '<?php echo $this->filter_cats ?: $row->catid; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>', this, 1);">
							All</a>

							<a class="btn" style="cursor: pointer;" href="javascript:;"
								onclick="window.parent.fcSelectItem('<?php echo $row->id; ?>', '<?php echo $this->filter_cats ?: $row->catid; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
							1</a>

							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>

						<?php else: /* (unused) Disable with message */ ?>
							<a style="cursor: default;" href="javascript:;" onclick="var box = jQuery('#assoc_not_allowed_msg'); fc_itemelement_view_handle = fc_showAsDialog(box, 300, 200, null, { title: '<?php echo Text::_('FLEXI_ABOUT', true); ?>'}); return false;">
								<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						<?php endif; ?>


					<?php else: ?>

						<a style="cursor: pointer;" href="javascript:;" onclick="window.parent.fcSelectItem('<?php echo $row->id; ?>', '<?php echo $this->filter_cats ?: $row->catid; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>

					<?php endif; ?>

				<?php
					echo !empty($row->is_current_association) ? ' <span class="label label-association label-warning">' . Text::_('FLEXI_CURRENT') . '</span> ' : '';
				?>
			</td>


			<?php if (!isset($disable_columns['author'])) : ?>
			<td class="col_authors small hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->author; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['lang'])) : ?>
			<td class="col_lang small hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
					/**
					 * Display language
					 */
					echo HTMLHelper::_($hlpname . '.lang_display', $row, $i, $this->langs, $use_icon = 2); ?>
			</td>
			<?php endif; ?>


			<?php if ($useAssocs && !isset($disable_columns['assocs'])) : ?>

				<td><?php $colposition++; ?>
					<?php if (!empty($this->lang_assocs[$row->id])): ?>
						<?php $row_assocs = $this->lang_assocs[$row->id]; ?>
						<a href="index.php?option=com_flexicontent&amp;view=itemelement&amp;filter_catsinstate=99&amp;filter_assockey=<?php echo reset($row_assocs)->key; ?>&amp;assocs_id=<?php echo $this->assocs_id; ?>&amp;fcform=1&amp;filter_state=ALL&amp;limit=50"
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

							$_link  = 'index.php?option=com_flexicontent&amp;task='.$ctrl.'edit&amp;id='. $assoc_item->id;
							$_title = flexicontent_html::getToolTip(
								$assoc_item->title,
								(isset($state_icons[$assoc_item->state]) ? '<span class="' . $state_icons[$assoc_item->state] . '"></span>' : '') .
								(isset($state_names[$assoc_item->state]) ? $state_names[$assoc_item->state] . '<br>': '') .
								($is_oc_item ? '' : '<span class="icon-pencil"></span>' . Text::_( !$assoc_item->is_uptodate && $assoc_modified < $oc_item_modified ? 'FLEXI_TRANSLATION_IS_OUTDATED' : 'FLEXI_TRANSLATION_IS_UPTODATE')) .
								': ' . $assoc_modified_date . '<br>'.
								( !empty($this->langs->{$assoc_item->lang}) ? ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" /> ' : '').
								($assoc_item->lang === '*' ? Text::_('FLEXI_ALL') : (!empty($this->langs->{$assoc_item->lang}) ? $this->langs->{$assoc_item->lang}->name: '?')).' <br/> '
								, 0, 1
							);

							$state_colors = array(1 => ' fc_assoc_ispublished', -5 => ' fc_assoc_isinprogress');
							$assoc_state_class   = isset($state_colors[$assoc_item->state]) ? $state_colors[$assoc_item->state] : ' fc_assoc_isunpublished';
							$assoc_isstale_class = $oc_item && (!$assoc_item->is_uptodate && $assoc_modified < $oc_item_modified) ? ' fc_assoc_isstale' : ' fc_assoc_isuptodate';

							echo '
							<span class="fc_assoc_translation label label-association ' . $this->popover_class . $assoc_isstale_class . $assoc_state_class . '"
							>
								<span>' . ($assoc_item->lang=='*' ? Text::_('FLEXI_ALL') : strtoupper($assoc_item->shortcode ?: '?')) . '</span>
							</span>';
						}
					}
					?>
				</td>

			<?php endif ; ?>


			<?php if (/*!$single_type || */!isset($disable_columns['single_type'])): ?>
			<td class="col_type small hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo Text::_($row->type_name); ?>
			</td>
			<?php endif ; ?>


			<?php if (!isset($disable_columns['access'])): ?>
			<td class="col_access hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->access_level; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['cats'])): ?>
			<td class="col_cats small hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $globalcats[$row->catid]->title; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['id'])) : ?>
			<td class="col_id center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->id; ?>
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


	<!-- Common management form fields -->
	<input type="hidden" name="tmpl" value="component" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="editor" value="<?php echo $editor; ?>" />
	<input type="hidden" name="isxtdbtn" value="<?php echo $isXtdBtn; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo HTMLHelper::_('form.token'); ?>

	<?php echo $this->assocs_id ? '
		<input type="hidden" name="assocs_id" value="'.$this->assocs_id.'" />'
		: ''; ?>
</form>
</div><!-- #flexicontent end -->

<div id="assoc_not_allowed_msg" style="display: none;">
	<?php echo Text::_('FLEXI_ITEM_TRANSLATION_IN_USE'); ?>
</div>