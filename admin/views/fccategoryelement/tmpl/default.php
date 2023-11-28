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
$hlpname  = 'fccategoryelement';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();

$editor   = $jinput->getCmd('editor', '');
$isXtdBtn = $jinput->getCmd('isxtdbtn', '');
$function = $jinput->getCmd('function', 'jSelectFccategory');
$onclick  = $this->escape($function);

if (!empty($editor))
{
	// This view is used also in com_menus. Load the xtd script only if the editor is set!
	JFactory::getDocument()->addScriptOptions('xtd-fccats', array('editor' => $editor));
	$onclick = "jSelectFccategory";
}

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

$state_names = array(
	 1  => JText::_('FLEXI_PUBLISHED'),
	-5  => JText::_('FLEXI_IN_PROGRESS'),
	 0  => JText::_('FLEXI_UNPUBLISHED'),
	-3  => JText::_('FLEXI_PENDING'),
	-4  => JText::_('FLEXI_TO_WRITE'),
	 2  => JText::_('FLEXI_ARCHIVED'),
	-2  => JText::_('FLEXI_TRASHED'),
	'u' => JText::_('FLEXI_UNKNOWN'),
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
 * Order stuff and table related variables
 */

$list_total_cols = 7
	+ ($useAssocs ? 1 : 0);



/**
 * Add inline JS
 */

$js = '';

$js .= (!$isXtdBtn ? "" : "
(function() {
	\"use strict\";
	/**
	 * Javascript to insert the link
	 * View element calls jSelectFccategory when a fccat is clicked
	 * jSelectFccategory creates the link tag, sends it to the editor,
	 * and closes the select frame.
	 **/
	window.jSelectFccategory = function(id, title, catid, object, link, lang)
	{
		var hreflang = '', editor, tag;

		if (!Joomla.getOptions('xtd-fccats')) {
			// Something went wrong!
			if (window.parent.Joomla.Modal) window.parent.Joomla.Modal.getCurrent().close();
			else if (window.parent.jModalClose) window.parent.jModalClose();
			return false;
		}

		editor = Joomla.getOptions('xtd-fccats').editor;

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

				if (functionName === 'jSelectFccategory') {
					// Used in xtd_fccats
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
	jQuery('.fc_field_filter').val('');
	delFilter('search');
	delFilter('filter_level');
	delFilter('filter_state');
	delFilter('filter_cats');
	delFilter('filter_author');
	delFilter('filter_id');
	delFilter('startdate');
	delFilter('enddate');
	delFilter('filter_lang');
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
				<input type="text" name="search" id="search" placeholder="<?php echo !empty($this->scope_title) ? $this->scope_title : JText::_('FLEXI_SEARCH'); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="fcfield_textval" />
				<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>

				<div id="fc_filters_box_btn" data-original-title="<?php echo JText::_('FLEXI_FILTERS'); ?>" class="<?php echo $this->tooltip_class . ' ' . ($this->count_filters ? 'btn ' . $this->btn_iv_class : $out_class); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);">
					<?php echo FLEXI_J30GE ? '<i class="icon-filter"></i>' : JText::_('FLEXI_FILTERS'); ?>
					<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
				</div>

				<div id="fc-filters-box" <?php if (!$this->count_filters || empty($tools_state->filters_box)) echo 'style="display:none;"'; ?> class="fcman-abs" onclick="var event = arguments[0] || window.event; event.stopPropagation();">
					<?php
					echo $this->lists['filter_author'];
					echo $this->lists['filter_level'];
					echo $this->lists['filter_lang'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					echo $this->lists['filter_cats'];
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


	<table id="adminListTableFC<?php echo $this->view; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>

			<!--th class="left hidden-phone">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="hideOnDemandClass col_title">
				<?php echo JHtml::_('grid.sort', 'FLEXI_TITLE', 'a.' . $this->title_propname, $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass">
				<?php echo JText::_( 'FLEXI_STATE' ); ?>
			</th>

			<th class="hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_LANGUAGE', 'a.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

		<?php if ($useAssocs) : ?>
			<th class="hideOnDemandClass <?php echo !$this->assocs_id ? 'hidden-phone hidden-tablet' : ''; ?>">
				<?php echo JText::_('FLEXI_ASSOCIATIONS'); ?>
			</th>
		<?php endif; ?>

			<th class="hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_AUTHOR', 'author', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass hidden-phone">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>

			<th class="hideOnDemandClass col_id center hidden-phone hidden-tablet">
				<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
			</th>

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
			$lang = JHtml::_($hlpname . '.lang_display', $row, $i, $this->langs, $use_icon = 2);
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone">
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

			<td class="col_title smaller">
				<!--div class="adminlist-table-row"></div-->
				<?php
					// Do not activate row selection link if doing associations and the record is in use
					$activate_row = !$this->assocs_id || !empty($row->is_current_association) || empty($this->lang_assocs[$row->id]);

					$parentcats_ids = isset($globalcats[$row->id]) ? $globalcats[$row->id]->ancestorsarray : array();
					$pcpath = array();

					foreach($parentcats_ids as $pcid)
					{
						$pcpath[] = $globalcats[$pcid]->title;
					}

					$pcpath = implode(' / ', $pcpath);
				?>
				<?php if ($row->level > 1) echo str_repeat('.&nbsp;&nbsp;&nbsp;', $row->level - 1) . '<sup>|_</sup>'; ?>

				<span class="<?php echo $this->tooltip_class; ?>" title="<?php echo JHtml::tooltipText(JText::_('FLEXI_SELECT'), $row->title . '<br/><br/>' . $pcpath, 0, 1); ?>">

					<?php if ($isXtdBtn): ?>

						<?php $attribs = 'data-function="' . $this->escape($onclick) . '"'
							. ' data-id="' . $this->escape($row->id) . '"'
							. ' data-title="' . $this->escape($row->title) . '"'
							. ' data-cat-id="' . $this->escape($row->parent_id) . '"'
							. ' data-uri="' . $this->escape(FlexicontentHelperRoute::getCategoryRoute($row->id, $_Itemid = 0, $_urlvars = array(), $row)) . '"'
							. ' data-language="' . $this->escape($row->language) . '"';
						?>
						<a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>

					<?php elseif ($activate_row): ?>

						<a style="cursor: pointer;" href="javascript:;" onclick="window.parent.fcSelectCategory('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');" class="<?php echo $this->tooltip_class; ?>" title="<?php echo JHtml::tooltipText(JText::_('FLEXI_SELECT'), $pcpath, 0, 1); ?>" >

					<?php else: ?>

						<a style="cursor: default;" href="javascript:;" onclick="var box = jQuery('#assoc_not_allowed_msg'); fc_itemelement_view_handle = fc_showAsDialog(box, 300, 200, null, { title: '<?php echo JText::_('FLEXI_ABOUT', true); ?>'}); return false;">

					<?php endif; ?>

							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
				</span>

				<?php
					echo !empty($row->is_current_association) ? ' <span class="label label-association label-warning">' . JText::_('FLEXI_CURRENT') . '</span> ' : '';
				?>
			</td>

			<td class="col_state">
				<?php $state = isset($state_icons[$row->published]) ? $row->published : 'u'; ?>
				<span class="<?php echo $state_icons[$state]; ?>" title="<?php echo $state_names[$state]; ?>"></span>
			</td>

			<td class="col_lang hidden-phone">
				<?php
					/**
					 * Display language
					 */
					echo $lang; ?>
			</td>


			<?php if ($useAssocs) : ?>
			<td class="<?php echo !$this->assocs_id ? 'hidden-phone hidden-tablet' : ''; ?>">
				<?php
				if (!empty($this->lang_assocs[$row->id]))
				{
					$row_modified = strtotime($row->modified_time) ?: strtotime($row->created_time);

					foreach($this->lang_assocs[$row->id] as $assoc_item)
					{
						// Joomla article manager show also current item, so we will not skip it
						$is_current = $assoc_item->id == $row->id;
						$assoc_modified = strtotime($assoc_item->modified) ?: strtotime($assoc_item->created);

						$_title = flexicontent_html::getToolTip(
							($is_current ? '' : JText::_( $assoc_modified < $row_modified ? 'FLEXI_EARLIER_THAN_THIS' : 'FLEXI_LATER_THAN_THIS')),
							( !empty($this->langs->{$assoc_item->lang}) ? ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" /> ' : '').
							($assoc_item->lang === '*' ? JText::_('FLEXI_ALL') : (!empty($this->langs->{$assoc_item->lang}) ? $this->langs->{$assoc_item->lang}->name: '?')).' <br/> '.
							$assoc_item->title, 0, 1
						);

						echo '
						<span class="fc_assoc_translation label label-association ' . $this->tooltip_class . ($assoc_modified < $row_modified ? ' fc_assoc_later_mod' : '').'" title="'.$_title.'" >
							'.($assoc_item->lang=='*' ? JText::_('FLEXI_ALL') : strtoupper($assoc_item->shortcode ?: '?')).'
						</span>';
					}
				}
				?>
			</td>
			<?php endif ; ?>


			<td class="col_author hidden-phone">
				<?php echo $row->author; ?>
			</td>

			<td class="col_access hidden-phone">
				<?php echo $row->access_level; ?>
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
	<input type="hidden" name="tmpl" value="component" />
	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="editor" value="<?php echo $editor; ?>" />
	<input type="hidden" name="isxtdbtn" value="<?php echo $isXtdBtn; ?>" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_('form.token'); ?>

	<?php echo $this->assocs_id ? '
		<input type="hidden" name="assocs_id" value="'.$this->assocs_id.'" />'
		: ''; ?>
</form>
</div><!-- #flexicontent end -->

<div id="assoc_not_allowed_msg" style="display: none;">
	<?php echo JText::_('FLEXI_ITEM_TRANSLATION_IN_USE'); ?>
</div>