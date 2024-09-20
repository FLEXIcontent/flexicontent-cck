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
use Joomla\Registry\Registry;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

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
$hlpname  = 'fcitems';
$isAdmin  = $app->isClient('administrator');
$useAssocs= flexicontent_db::useAssociations();

$items_task = 'task=items.';
$cats_task  = 'task=category.';
$_sh404sef  = defined('SH404SEF_IS_RUNNING') && $config->get('sef');



/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$edit_cat_title  = Text::_('FLEXI_EDIT_CATEGORY', true);
$rem_filt_txt    = Text::_('FLEXI_REMOVE_FILTER', true);
$rem_filt_tip    = ' class="' . $this->tooltip_class . ' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';
$_NEVER_         = Text::_('FLEXI_NEVER');
$_NULL_DATE_     = Factory::getDbo()->getNullDate();



/**
 * JS for Columns chooser box and Filters box
 */

$filter_type = $this->getModel()->getState('filter_type');
$single_type = count($filter_type) === 1;
$disable_columns = $this->tparams->get('iman_skip_cols', array('single_type'));
$disable_columns = array_flip($disable_columns);

// ID of specific type if one type is selected otherwise ZERO
$single_type_id = $single_type ? reset($filter_type) : 0;

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

$image_flag_path = "../media/mod_languages/images/";
$featimg = HTMLHelper::image ( 'administrator/components/com_flexicontent/assets/images/star.png', Text::_( 'FLEXI_FEATURED' ), ' style="text-align:left" class="'.$ico_class.'" title="'.Text::_( 'FLEXI_FEATURED' ).'"' );



/**
 * Order stuff and table related variables
 */

$list_total_cols = 18
	+ ($useAssocs ? 2 : 0);

$list_total_cols += count($this->extra_fields);

$canOrder = $this->perms->CanOrder;
$ordering_draggable = $cparams->get('draggable_reordering', 1);

if ($this->reOrderingActive)
{
	$image_ordering_tip = '<span class="icon-info ' . $this->tooltip_class . '" title="' . flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_ENABLED_DESC', 1, 1) . '"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="' . Text::_('FLEXI_ORDER_SAVE_WHEN_DONE', true) . '"></div>';
}
else
{
	$image_ordering_tip = '<span class="icon-info ' . $this->tooltip_class . '" title="' . flexicontent_html::getToolTip('FLEXI_REORDERING', 'FLEXI_REORDERING_DISABLED_DESC', 1, 1) . '"></span>';
	$drag_handle_box = '<div class="fc_drag_handle%s" title="' . Text::_('FLEXI_ORDER_COLUMN_FIRST', true) . '" ></div>';
	$image_saveorder    = '';
}

$drag_handle_html['disabled'] = sprintf($drag_handle_box, ' fc_drag_handle_disabled');
$drag_handle_html['both']     = sprintf($drag_handle_box, ' fc_drag_handle_both');
$drag_handle_html['uponly']   = sprintf($drag_handle_box, ' fc_drag_handle_uponly');
$drag_handle_html['downonly'] = sprintf($drag_handle_box, ' fc_drag_handle_downonly');
$drag_handle_html['none']     = sprintf($drag_handle_box, '_none');

$_img_title = Text::_('MAIN category shown in bold', true);
$categories_tip  = '<span class="icon-info ' . $this->tooltip_class . '" title="'.flexicontent_html::getToolTip(null, $_img_title, 0, 1).'"></span>';

if (!$this->filter_order_type)
{
	$ord_catid = 'catid';
	$ord_col = 'ordering';
}
else
{
	$ord_catid = 'rel_catid';
	$ord_col = 'catsordering';
}
$ord_grp = 1;


/**
 * ICONS and reusable variables
 */

$stategrps = array(1 => 'published', 0 => 'unpublished', -2 => 'trashed', -3 => 'unpublished', -4 => 'unpublished', -5 => 'published');


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

	$max_cats_cnt = count($row->catids) > $max_cats_cnt
		? count($row->catids)
		: $max_cats_cnt;

	$max_tags_cnt = count($row->tagids) > $max_tags_cnt
		? count($row->tagids)
		: $max_tags_cnt;
}
$ocLang = $cparams->get('original_content_language', '_site_default_');
$ocLang = $ocLang === '_site_default_' ? ComponentHelper::getParams('com_languages')->get('site', '*') : $ocLang;
$ocLang = $ocLang !== '_disable_' && $ocLang !== '*' ? $ocLang : false;



/**
 * Create custom field columns
 */
foreach($this->extra_fields as $_field)
{
	$values = null;
	$field = $_field;
	FlexicontentFields::renderField($this->rows, $field->name, $values, $field->methodname);
}

// Load JS tabber lib
$document->addScript(Uri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

?>


<script>

function fetchcounter(el_id, task_name)
{
	var url = "index.php?option=com_flexicontent&<?php echo $items_task; ?>"+task_name+"&tmpl=component&format=raw";

	jQuery.ajax(
	{
		url : url,
		type: 'get',
		data : null,
		success:function(data, textStatus, jqXHR)
		{
			jQuery('#' + el_id).html(data);

			if (data == 0)
			{
				if (confirm("<?php echo Text::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>"))
				{
					location.href = 'index.php?option=com_flexicontent&view=items';
				}
			}
			return data;
		},
		error: function(jqXHR, textStatus, errorThrown)
		{
				//if fails
		}
	});
}

// the function overloads joomla standard event
function submitform(pressbutton)
{
	form = document.adminForm;
	// If formvalidator activated
	/*if( pressbutton == 'remove' ) {
		var answer = confirm('<?php echo Text::_( 'FLEXI_ITEMS_DELETE_CONFIRM',true ); ?>')
		if (!answer){
			new Event(e).stop();
			return;
		}
	}*/

	// Store the button task into the form
	if (pressbutton) {
		form.task.value=pressbutton;
	}

	// Execute onsubmit
	if (typeof form.onsubmit == "function") {
		form.onsubmit();
	}
	// Submit the form
	form.submit();
}

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
	<?php $single_type ? '' : "delFilter('filter_type'); "; ?>
	delFilter('filter_state');
	delFilter('filter_cats');
	delFilter('filter_featured');
	delFilter('filter_author');
	delFilter('filter_id');
	delFilter('startdate');
	delFilter('enddate');
	delFilter('filter_lang');
	delFilter('filter_tag');
	delFilter('filter_access');
	delFilter('filter_meta');
	delFilter('filter_fileid');
	delFilter('filter_assockey');
	delFilter('filter_order');
	delFilter('filter_order_Dir');
	jQuery('#filter_subcats').attr('checked', 'checked');  // default: include subcats
	jQuery('#filter_catsinstate').val('1');	  // default: published categories
}

<?php if ($this->reOrderingActive) : ?>
var move_within_ordering_groups_limits = <?php echo '"'.Text::_('FLEXI_MOVE_WITHIN_ORDERING_GROUPS_LIMITS',true).'"'; ?>
<?php endif; ?>

</script>

<script>

<?php if ($this->unassociated && !$this->badcatitems) : ?>
	var unassociated_items = <?php echo $this->unassociated; ?>;
	function bindItems()
	{
		jQuery('#log-bind').html('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" />');
		jQuery('#orphan_items_mssg').html("<?php echo Text::_( 'FLEXI_ITEMS_TO_BIND', true ); ?>");

    var postData = jQuery('#bindToTypeBox').serializeArray();
    var taskURL  = jQuery('#bindToTypeBox').attr("data-action");

		taskURL += '&typeid=' + jQuery('#bindToTypeBox').find('#typeid').val();
		taskURL += '&bind_limit=' + jQuery('#bindToTypeBox').find('#bind_limit').val();

    jQuery.ajax(
		{
			url : taskURL,
			type: "POST",
			data : postData,
			success:function(data, textStatus, jqXHR)
			{
				jQuery('#log-bind').html(data);
				bind_limit = jQuery('#bind_limit').val();
				unassociated_items = unassociated_items - bind_limit;

				if (unassociated_items > 0)
				{
					jQuery('#orphan_items_count').html(unassociated_items);
					bindItems();
				}
				else
				{
					jQuery('#orphan_items_count').html('0');
					if(confirm("<?php echo Text::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>"))
					{
						location.href = 'index.php?option=com_flexicontent&view=items';
					}
				}
				//if (fetchcounter('orphan_items_count', 'getOrphansItems', jQuery('#bind_limit').val()) != 0) bindItems();
			},
			error: function(jqXHR, textStatus, errorThrown)
			{
			    //if fails
			}
		});
	}
<?php endif; ?>

<?php if ($this->badcatitems) : ?>
jQuery(document).ready(function(){
	jQuery('#button-fixcat').on('click', function(event)
	{
		var default_cat = jQuery('#fixCatBox').find('#default_cat');

		if (!default_cat.val())
		{
			alert('Please select a category');
			return false;
		}

		var action = jQuery('#fixCatBox').data('action') + '&default_cat=' + default_cat.val();

		jQuery(this).parent().remove();
		jQuery('#log-fixcat').html('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" />');
		event.stopImmediatePropagation();

    jQuery.ajax(
		{
			url : action,
			type: 'post',
			data : null,
			success:function(data, textStatus, jqXHR)
			{
				jQuery('#log-fixcat').html(data);
				fetchcounter('badcat_items_count', 'getBadCatItems');
			},
			error: function(jqXHR, textStatus, errorThrown)
			{
			    //if fails
			}
		});
	});
});
<?php endif; ?>

</script>

<style>
	.tabbernav {
		display: flex !important;
		flex-wrap: wrap !important;
	}

  .tabbernav li:has(a.break-subtypes) {
    flex-basis: 100% !important;
	  height: 0 !important;
	  overflow: hidden !important;
  }

  .tabbernav li:has(a.subtypes-header) {
    margin-left: 24px !important;
  }
  #flexicontent .s-cblue ul.tabbernav > li:hover:has(a.subtypes-header) > a,
  .tabbernav li:has(a.subtypes-header) {
    background: black !important;
  }
  .tabbernav li a.subtypes-header {
	  display:none !important;
  }
  .tabbernav li:has(a.subtypes-header) a.subtypes-header {
    display:flex !important;
    font-weight: bold!important;
    padding: 4px;
  }
  .tabbernav li:has(a.subtypes-header) a.subtypes-header > span {
	  color: white !important;
  }
  body .s-cblue ul.tabbernav > li a.fc-subtype {
     position: relative;
  }
  body .s-cblue ul.tabbernav > li a.fc-subtype:after {
    opacity: 0.4;
    content: "s" !important;
	  position: absolute;
	  height: 1rem;
	  top: 6px;
    line-height: 11px;
	  font-size: 11px;
	  background-color: #000 !important;
	  padding: 2px;
	  border-radius: 2px;
	  color: white;
  }
</style>

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



<?php
//echo '<pre>'; print_r($this->itemTypes); echo '</pre>'; exit;
foreach ($this->itemTypes as $itemType)
{
	if (empty($itemType->params))
	{
		$itemType->params = new Registry($itemType->attribs);
	}
}

if ($this->max_tab_types && count($this->itemTypes) >= $this->max_tab_types)
{
	echo '
	<div style="min-height: 36px; margin-top: -8px; ">
		' . $this->lists['filter_type'] . '
	</div>';
}
elseif ($this->max_tab_types && count($this->itemTypes) > 1)
{
	echo '
	<div style="overflow: hidden; margin: -8px 0; ">
		<div class="fctabber s-cblue" id="items_type_tabset">';

	$type_class = empty($filter_type) ? ' tabbertabforced' : '';
	$__tip_class = ''; //' hasTooltip';
	$__tip_props = ''; //' data-placement="top" data-title="' . Text::_('FLEXI_ALL') . '" ';

	echo '
		<div class="tabbertab ' . $type_class . '" id="type_tab_all" style="padding-left: 0; padding-right: 0; border-left: 0; border-right: 0; border-bottom: 0;">
			<h3 class="tabberheading ' . $__tip_class . '" ' . $__tip_props . '
				onmouseup="jQuery(\'#filter_assockey\').removeAttr(\'checked\'); jQuery(\'#filter_type\').val([]); jQuery(\'#filter_type\').trigger(\'change\')"
			>' . Text::_('FLEXI_ALL') . '</h3>
		</div>
		';

	if (count($filter_type) > 1)
	{
		$type_class  = ' tabbertabforced';
		$_name = count($filter_type) . ' ' . Text::_('FLEXI_TYPES');

		$__tip_props = ''; //' data-placement="top" data-title="' . $_name . '" ';
		$__tip_class = ''; //' hasTooltip';
		echo '
			<div class="tabbertab ' . $type_class . '" id="type_tab_all" style="padding-left: 0; padding-right: 0; border-left: 0; border-right: 0; border-bottom: 0;">
				<h3 class="tabberheading ' . $__tip_class . '" ' . $__tip_props . '
				>' . $_name . '</h3>
			</div>
			';
	}

	$main_types = [];
	$sub_types  = [];
	foreach($this->itemTypes as $itemType) {
		if ($itemType->params->get('is_subtype', 0)) {
			$sub_types[] = $itemType;
		} else {
			$main_types[] = $itemType;
		}
	}
	$sorted_itemTypes = array_merge($main_types, $sub_types);

	$subtype_count = 0;
	$itemType = reset($sorted_itemTypes);
	do
	{
		if ($itemType->params->get('is_subtype', 0) && $subtype_count == 0) {
			$type_class = ' subtypes-header';
			echo '
				<div class="tabbertab ' . $type_class . '" id="type_tab_break_subtype" style="padding:0; border: 0;">
					<h3 class="tabberheading ' . $type_class . '"
						data-data_attr_a="' . (int) $itemType->id . '"
					> Subtypes: </h3>
			';
			echo '</div>';
		}

		$type_class = $single_type && in_array($itemType->id, $filter_type) ? ' tabbertabforced' : '';
		$__tip_class = ''; //' hasTooltip';
		$__tip_props = ''; //' data-placement="top" data-title="' . Text::_( $itemType->name ) . '" ';
		if ($itemType->params->get('is_subtype', 0)) {
			$type_class .= ' fc-subtype';
			$subtype_count++;
		}
		echo '
			<div class="tabbertab ' . $type_class . '" id="type_tab_' . (int) $itemType->id . '" style="padding-left: 0; padding-right: 0; border-left: 0; border-right: 0; border-bottom: 0;">
				<h3 class="tabberheading ' . $__tip_class . $type_class . '" ' . $__tip_props . '
					data-data_attr_a="' . (int) $itemType->id . '"
					onmouseup="jQuery(\'#filter_assockey\').removeAttr(\'checked\'); jQuery(\'#filter_type\').val([this.getAttribute(\'data-data_attr_a\')]); jQuery(\'#filter_type\').trigger(\'change\')"
				>' . Text::_( $itemType->name ) . '</h3>
		';
		echo '</div>';

		$itemType = next($sorted_itemTypes);
		if (0 && $itemType && $itemType->params->get('is_subtype', 0) && $subtype_count == 0) {
			$type_class = ' break-subtypes';
			echo '
				<div class="tabbertab ' . $type_class . '" id="type_tab_break_subtype" style="padding:0; border: 0;">
					<h3 class="tabberheading ' . $type_class . '"
						data-data_attr_a="' . (int) $itemType->id . '"
					></h3>
			';
			echo '</div>';
		}

	} while($itemType);

	echo '
		</div>
	</div>';
}
?>


	<?php if ($this->unassociated && !$this->badcatitems) : ?>
		<div class="fc-mssg fc-success" style="margin-bottom: 32px;">

			<?php echo Text::_( 'FLEXI_UNASSOCIATED_WARNING' ); ?>

			<br/><br/>
			<span id="log-bind"></span>
			<span class="badge" style="border-radius: 3px;" id="orphan_items_count"><?php echo $this->unassociated; ?></span>
			<span id="orphan_items_mssg"><?php echo Text::_( 'FLEXI_ITEMS' ); ?></span>

			<div id="bindToTypeBox" style="display: inline-block;" data-action="index.php?option=com_flexicontent&amp;<?php echo $items_task; ?>bindextdata&amp;tmpl=component&amp;format=raw">

				<input id="button-bind" type="button"
					class="<?php echo $btn_class; ?> btn-primary" style='float:none !important; box-sizing: border-box; min-width: 200px;'
					value="<?php echo Text::_('FLEXI_BIND'); ?>" onclick="jQuery(this).parent().hide(); bindItems();"
				/>

				<?php
					echo '
						<span class="badge" style="border-radius: 3px;">'.Text::_( 'FLEXI_TO' ) . '</span> ' .
						flexicontent_html::buildtypesselect($_types = $this->get( 'Typeslist' ), 'typeid', $_typesselected='', false, ' class="use_select2_lib" ', 'typeid') . '

						<div style="display: '.($this->unassociated > 1000 ? 'inline-block;' : 'none;').'">
							<span class="label">'.Text::_( 'with step ' ) . '</span>' . $this->lists['bind_limits'] .'
						</div>';
				?>

			</div>
		</div>
	<?php endif; ?>


	<?php if ($this->badcatitems) : ?>
		<div class="fc-mssg fc-warning">

			<?php echo Text::_( 'Items with invalid or missing main category' ); ?>
			<br><br>
			<span id="log-fixcat"></span>
			<span id="badcat_items_count" class="badge" style="border-radius: 3px;"><?php echo $this->badcatitems; ?></span>
			<?php echo Text::_( 'FLEXI_ITEMS' ); ?>

			<div id="fixCatBox" style="display: inline-block;" data-action="index.php?option=com_flexicontent&amp;<?php echo $items_task; ?>fixmaincat&amp;tmpl=component&amp;format=raw">
				<input id="button-fixcat" type="button"
					class="<?php echo $btn_class; ?> btn-primary"
					value="<?php echo Text::_( 'FLEXI_FIX' ); ?>" onclick="return false;"
				/>

				<span class="label"><?php echo Text::_('FLEXI_CATEGORY'); ?></span> <?php echo $this->lists['default_cat']; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($this->unassociated && !count($this->rows)) echo '<div style="display: none;">'; ?>


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
					echo $this->lists['filter_fileid'];
					echo $this->lists['filter_author'];
					echo $this->lists['filter_tag'];
					if (!$this->max_tab_types || count($this->itemTypes) < $this->max_tab_types) echo $this->lists['filter_type'];
					echo $this->lists['filter_lang'];
					echo $this->lists['filter_state'];
					echo $this->lists['filter_access'];
					echo $this->lists['filter_meta'];

					if (!$this->reOrderingActive)
					{
						echo $this->lists['filter_cats'];
					}
					echo $this->lists['filter_subcats'];

					echo $this->lists['filter_featured'];
					echo $this->lists['filter_catsinstate'];
					echo $this->lists['filter_id'];
					echo $this->lists['filter_date'];

					foreach($this->custom_filts as $filt)
					{
						echo $this->getFilterDisplay(array(
							'label' => $filt->label,
							'label_extra_class' => ($filt->value ? ' fc-lbl-inverted' : ''),
							'html' => $filt->html,
						));
					}

					if (ComponentHelper::getParams('com_flexicontent')->get('show_csvbutton_be', 0))
					{
						echo '
							<div class="clear"></div>
							<h4>' . Text::_('FLEXI_CSV_EXPORT') . '</h4>
							<div class="clear"></div>
						';
						echo $this->lists['csv_header'];
						echo $this->lists['csv_raw_export'];
						echo $this->lists['csv_all_fields'];
					}
					?>

					<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo Text::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
				</div>

				<button title="" data-original-title="<?php echo Text::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : Text::_('FLEXI_CLEAR'); ?></button>
			</div>

		</div>


		<div class="fc-filter-head-box nowrap_box">

			<div class="btn-group">
				<?php if (!isset($disable_columns['cats']) && $max_cats_cnt > 1): ?>
					<div id="fc-toggle-cats_btn" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?>  hidden-phone" title="<?php echo Text::_('FLEXI_SECONDARY_CATEGORIES'); ?>" onclick="jQuery(this).data('box_showing', !jQuery(this).data('box_showing')); jQuery(this).data('box_showing') ? jQuery('.fc_assignments_box.fc_cats').show(400) : jQuery('.fc_assignments_box.fc_cats').hide(400);" ><span class="icon-tree-2"></span></div>
				<?php endif; ?>
				<?php if (!isset($disable_columns['tags']) && $max_tags_cnt > 1): ?>
					<div id="fc-toggle-tags_btn" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?>  hidden-phone hidden-tablet" title="<?php echo Text::_('FLEXI_TAGS'); ?>" onclick="jQuery(this).data('box_showing', !jQuery(this).data('box_showing')); jQuery(this).data('box_showing') ? jQuery('.fc_assignments_box.fc_tags').show(400) : jQuery('.fc_assignments_box.fc_tags').hide(400);" ><span class="icon-tags"></span></div>
				<?php endif; ?>

				<div id="fc_mainChooseColBox_btn" class="<?php echo $this->tooltip_class . ' ' . $out_class; ?> hidden-phone" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" title="<?php echo flexicontent_html::getToolTip('FLEXI_COLUMNS', 'FLEXI_ABOUT_AUTO_HIDDEN_COLUMNS', 1, 1); ?>">
					<span class="icon-contract"></span><sup id="columnchoose_totals"></sup>
				</div>

				<?php if (!empty($this->minihelp) && FlexicontentHelperPerm::getPerm()->CanConfig): ?>
				<div id="fc-mini-help_btn" class="<?php echo $out_class . ' ' . $this->tooltip_class; ?> hidden-phone hidden-tablet" title="<?php echo Text::_('FLEXI_IMAN_ABOUT_ADDING_MORE_COLUMNS_AND_FILTERS'); ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');" >
					<span class="icon-cog"></span>
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

		if (!$this->filter_order_type)
		{
			//$ico_text  = Text::_('FLEXI_FCORDER_JOOMLA_ORDER_GROUPING_BY_MAINCAT');
			//$msg_icon  = '<span class="icon-question ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $msg_text, 0, 1) . '"></span>';
			$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-success';
		}
		else
		{
			if (!$this->getModel()->getState('filter_cats'))
			{
				$ico_text  = Text::_('FLEXI_FCORDER_FC_ORDER_PLEASE_SET_CATEGORY_FILTER');
				$msg_icon  = '<span class="icon-notification ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $ico_text, 0, 1) . '"></span>';
				$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-info';
			}
			else
			{
				//$ico_text  = Text::_('FLEXI_FCORDER_FC_ORDER_GROUPING_BY_SELECTED_CATEGORY');
				//$msg_icon  = '<span class="icon-question ' . $this->popover_class . '" data-content="'.flexicontent_html::getToolTip(null, $ico_text, 0, 1) . '"></span>';
				$msg_class = '';//'fc-mssg-inline fc-nobgimage fc-success';
			}
		}

		$order_msg .= '<div class="fc-iblock" style="margin: 0 32px 0 0;">' . $this->lists['filter_cats'] . $msg_icon . '</div>';
		?>

		<div class="clear"></div>

		<div id="fcorder_notes_box" class="hidden-phone <?php echo $msg_class; ?>" style="<?php echo $msg_style; ?> line-height: 28px; max-width: unset;">
			<?php echo $order_msg;?>
			<div id="order_type_selector" class="fc-iblock">
				<?php echo $this->lists['filter_order_type']; ?>
			</div>
		</div>

		<div class="fcclear"></div>

		<?php if ($canOrder): ?>
		<div class="hidden-phone" style="z-index: 1; position: sticky; top: 30%; margin: 0 -20px;">
			<div style="position: absolute; margin: 0; height: 0;">
				<div style="padding: 0px; font-weight: normal; line-height: 28px; width: auto; text-align: center;">
					<?php echo HTMLHelper::_($hlpname . '.saveorder_btn', $this->rows, $_config = null); ?>
				</div>
			</div>
		</div>
		<div class="hidden-phone" style="z-index: 1; position: sticky; top: 30%; margin: 0 -20px;">
			<div style="position: absolute; margin: 40px 0 0 0; height: 0;">
				<div style="padding: 0px; font-weight: normal; line-height: 28px; width: auto; text-align: center;">
					<?php echo HTMLHelper::_($hlpname . '.manualorder_btn', $this->rows, $_config = null); ?>
				</div>
			</div>
		</div>
		<?php else: ?>
			<?php echo '<span class="icon-cancel ' . $this->tooltip_class . '" title="'.flexicontent_html::getToolTip('', 'FLEXI_FCORDER_ONLY_VIEW', 1, 1) . '"></span>'; ?>
		<?php endif; ?>

	<?php endif; ?>

	<div class="fcclear"></div>

	<table id="<?php echo $this->data_tbl_id; ?>" class="adminlist table fcmanlist" itemscope itemtype="http://schema.org/WebPage">
	<thead>
		<tr>
			<?php $colposition = 0; ?>

			<!--th class="left hidden-phone"><?php //$colposition++; ?>
				<?php echo Text::_( 'FLEXI_NUM' ); ?>
			</th-->

			<th class="col_order center hidden-phone "><?php $colposition++; ?>
				<?php
				echo $canOrder ? $image_ordering_tip : '';
				echo str_replace('_FLEXI_ORDER_',
					''/*Text::_('FLEXI_ORDER', true)*/,
					str_replace('_FLEXI_ORDER_</a>', '<span class="icon-menu-2 btn btn-micro"></span></a>',
					HTMLHelper::_('grid.sort', '_FLEXI_ORDER_', (!$this->filter_order_type ? 'a.ordering' : 'catsordering'), $this->lists['order_Dir'], $this->lists['order']))
				);
				?>
				<span class="column_toggle_lbl" style="display:none;"><?php echo Text::_( 'FLEXI_ORDER' ); ?></span>
			</th>

			<th class="col_cb left"><?php $colposition++; ?>
				<div class="group-fcset">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					<label for="checkall-toggle" class="green single"></label>
				</div>
			</th>

			<th class="left"><?php $colposition++; ?>
			</th>

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


			<?php if (!$single_type || !isset($disable_columns['single_type'])): ?>
			<th class="col_type hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->getModel()->getState('filter_type')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_type'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['template'])): ?>
			<th class="col_template hideOnDemandClass nowrap left hidden-phone hidden-tablet" colspan="2" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo Text::_('FLEXI_TEMPLATE'); ?>
			</th>
			<?php endif; ?>


			<?php foreach($this->extra_fields as $field) :?>
				<th class="hideOnDemandClass nowrap left hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
					<?php echo $field->label; ?>
				</th>
			<?php endforeach; ?>


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
				<?php echo $categories_tip; ?>
				<?php echo Text::_( 'FLEXI_CATEGORIES' ); ?>
				<?php if ($this->getModel()->getState('filter_cats') || $this->getModel()->getState('filter_subcats') == 0) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_cats'); jQuery('#filter_subcats').attr('checked', 'checked'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['tags'])): ?>
			<th class="col_tag hideOnDemandClass nowrap left hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo Text::_( 'FLEXI_TAGS' ); ?>
				<?php if ($this->getModel()->getState('filter_tag')) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('filter_tag'); document.adminForm.submit();"></span>
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['created'])): ?>
			<th class="col_created hideOnDemandClass nowrap hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort',   'FLEXI_CREATED', 'a.created', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != Text::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != Text::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('startdate');delFilter('enddate'); document.adminForm.submit();"></span>
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['modified'])) : ?>
			<th class="col_revised hideOnDemandClass nowrap hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort',   'FLEXI_REVISED', 'a.modified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != Text::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != Text::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<span class="icon-purge fc-del-filter-icon" onclick="delFilter('startdate');delFilter('enddate'); document.adminForm.submit();"></span>
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['hits'])) : ?>
			<th class="col_hits hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_HITS', 'a.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['votes'])) : ?>
			<th class="col_votes hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_VOTES', 'rating_count', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<?php endif; ?>


			<?php if (!isset($disable_columns['ratings'])) : ?>
			<th class="col_ratings hideOnDemandClass center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_RATINGS', 'rating', $this->lists['order_Dir'], $this->lists['order'] ); ?>
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

	<tbody <?php echo $ordering_draggable && $canOrder && $this->reOrderingActive ? 'id="sortable_fcitems"' : ''; ?> >
		<?php
		$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');

		$needsApproval = false;
		$date_format   = Text::_('FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE');

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
			$assetName = 'com_content.article.' . $row->id;
			$isAuthor  = $row->created_by && $row->created_by == $user->id;

			// Get the type params of the item
      $tparams = $this->tparams_array[$row->type_id];
      $row->tparams = $tparams;

			// Permissions
			$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
			$row->canEdit      = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
			$row->canEditState = $user->authorise('core.edit.state', $assetName) || ($isAuthor && $user->authorise('core.edit.state.own', $assetName));
			$row->canDelete    = $user->authorise('core.delete', $assetName) || ($isAuthor && $user->authorise('core.delete.own', $assetName));

			// No edit privilege, check if item is editable till logoff
			if (!$row->canEdit && $session->has('rendered_uneditable', 'flexicontent'))
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');
				$row->canEdit = isset($rendered_uneditable[$row->id]) && $rendered_uneditable[$row->id];
			}

			$stateIsChangeable = $row->canCheckin && $row->canEditState;
			$needsApproval     = $needsApproval || ($row->state == -3 && !$stateIsChangeable);
			$row_ilayout       = $row->config->get('ilayout') ?  $row->config->get('ilayout') : $row->tconfig->get('ilayout');

			// Set a row language, even if empty to avoid errors
			$row->lang = !empty($row->lang) ? $row->lang : '*';
			?>

		<tr class="<?php echo 'row' . ($k % 2); ?>">

			<!--td class="left col_rowcount hidden-phone"><?php //$colposition++; ?>
				<?php echo $this->pagination->getRowOffset($i); ?>
			</td-->

		<?php if ($canOrder) : ?>

			<td class="col_order nowrap center hidden-phone"><?php $colposition++; ?>
				<?php
					if ($this->reOrderingActive)
					{
						$row_stategrp_prev = @ $stategrps[@$this->rows[$i-1]->state];
						$row_stategrp = @ $stategrps[$this->rows[$i]->state];
						$row_stategrp_next = @ $stategrps[@$this->rows[$i+1]->state];

						$show_orderUp   = @$this->rows[$i-1]->$ord_catid == $this->rows[$i]->$ord_catid && $row_stategrp_prev == $row_stategrp;
						$show_orderDown = $this->rows[$i]->$ord_catid == @$this->rows[$i+1]->$ord_catid && $row_stategrp == $row_stategrp_next;
						$fcorder_lang_separately = true;
						$jorder_lang_separately = true;

						if (
							($this->filter_order_type && $fcorder_lang_separately) ||
							(!$this->filter_order_type && $jorder_lang_separately)
						) {
							$show_orderUp   = $show_orderUp   && @ $this->rows[$i-1]->lang == $this->rows[$i]->lang;
							$show_orderDown = $show_orderDown && $this->rows[$i]->lang == @ $this->rows[$i+1]->lang;
						}
					}
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
					<input class="fcitem_order_no" type="text" name="order[]" size="5" value="<?php echo $row->$ord_col; ?>" style="text-align: center; display: none;" />
					<input type="hidden" name="ord_grp[]" value="<?php echo $show_orderDown ? $ord_grp : $ord_grp++; ?>" />
				<?php endif; ?>
			</td>

		<?php else : ?>

			<td class="center hidden-phone"><?php $colposition++; ?>
				<?php
				echo !$this->reOrderingActive
					? '<span class="icon-move" style="color: #d0d0d0"></span>'
					: '';
				?>
			</td>

		<?php endif; ?>

			<td class="col_cb"><?php $colposition++; ?>
				<!--div class="adminlist-table-row"></div-->
				<?php echo HTMLHelper::_($hlpname . '.grid_id', $i, $row->id); ?>
			</td>

			<td class="col_notes nowrap"><?php $colposition++; ?>
				<?php
				// Display an icon if item has an unapproved latest version, thus needs revising
				echo HTMLHelper::_($hlpname . '.reviewing_needed', $row, $user, $i);

				// Display item scheduled / expired icons if item is in published state
				echo HTMLHelper::_($hlpname . '.scheduled_expired', $row, $i);
				?>
			</td>

			<td class="col_status" style="<?php echo $this->hideCol($colposition++); ?>" >
				<div class="btn-group fc-group fc-items">
					<?php
					//echo HTMLHelper::_('jgrid.published', $row->state, $i, $ctrl, $stateIsChangeable, 'cb', $row->publish_up, $row->publish_down);
					//echo HTMLHelper::_($hlpname . '.published', $row->state, $i, $stateIsChangeable, 'cb', $row->publish_up, $row->publish_down);

					echo HTMLHelper::_($hlpname . '.statebutton', $row, $i);
					if (!$tparams->get('is_subtype', 0))
					{
						echo HTMLHelper::_($hlpname . '.featured', $row, $i);
						echo HTMLHelper::_($hlpname . '.preview', $row, '_blank', $i);
					}
					?>
				</div>
			</td>

			<td class="col_title " style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
				/**
				 * Display an edit pencil or a check-in button if: either (a) current user has Global
				 * Checkin privilege OR (b) record checked out by current user, otherwise display a lock
				 */
				echo HTMLHelper::_($hlpname . '.checkedout', $row, $user, $i);

				/**
				 * Display title with edit link ... (row editable and not checked out)
				 * Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
				 */
				$create_jarticle_form_links = !empty($this->itemTypes[$row->type_id])
					? (int) $this->itemTypes[$row->type_id]->params->get('create_jarticle_form_links' , 0)
					: 0;
				echo $create_jarticle_form_links === 1
					? HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
							'option'   => 'com_content',
							'ctrl'     => 'article',
							//'url_data' => '&amp;tmpl=component',
							'keyname'  => 'id',
							'noTitle'  => false,
							'linkedPrefix' => '', //'<span class="icon-pencil-2"></span>',
							'attribs' => array(
								'class'       => '',
								'title'       => Text::_('FLEXI_EDIT'),
							),
							'useModal' => (object) array(
								'title'       => 'FLEXI_EDIT',
								'onloadfunc'  => 'fc_edit_jarticle_modal_load',
								'onclosefunc' => 'fc_edit_jarticle_modal_close',
							),
						))
					: HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit);
				?>
				<div class="small break-word">
				<?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($row->alias)); ?>
                </div>
			</td>


			<?php if (!isset($disable_columns['author'])) : ?>
			<td class="col_authors hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->author; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['lang'])) : ?>
			<td class="col_lang hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
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
						<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_assockey=<?php echo reset($row_assocs)->key; ?>&amp;fcform=1&amp;filter_state=ALL&amp;limit=50"
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
							<a class="fc_assoc_translation label label-association ' . $this->popover_class . $assoc_isstale_class . $assoc_state_class . ' hasTooltip"
								target="_blank" href="'.$_link.'" data-placement="top" aria-label="'.$_title.' data-content="'.$_title.' data-bs-original-title="'.$_title.'"
							>
								<span>' . ($assoc_item->lang=='*' ? Text::_('FLEXI_ALL') : strtoupper($assoc_item->shortcode ?: '?')) . '</span>
							</a>';
						}
					}
					?>
				</td>

			<?php endif ; ?>


			<?php if (!$single_type || !isset($disable_columns['single_type'])): ?>
			<td class="col_type hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo Text::_($row->type_name); ?>
			</td>
			<?php endif ; ?>


			<?php if (!isset($disable_columns['template'])): ?>
			<td class="col_edit_layout hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition); ?>" >
				<?php echo HTMLHelper::_($hlpname . '.edit_layout', $row, '__modal__', $i, $this->perms->CanTemplates, $row_ilayout); ?>
			</td>
			<td class="col_template hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row_ilayout.($row->config->get('ilayout') ? '' : '<sup>[1]</sup>') ?>
			</td>
			<?php endif; ?>


    <?php foreach($this->extra_fields as $field) :?>

			<td class="hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
		    <?php
				// Output the field's display HTML
				echo isset( $row->fields[$field->name]->{$field->methodname} ) ? $row->fields[$field->name]->{$field->methodname} : '';
		    ?>
			</td>
		<?php endforeach; ?>


			<?php if (!isset($disable_columns['access'])): ?>
			<td class="col_access hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo $row->canEditState && $this->perms->CanAccLvl
					? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'class="fcfield_selectval" onchange="return Joomla.listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"')
					: $row->access_level; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['cats'])): ?>
			<td class="col_cats hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
				// Reorder categories place item's MAIN category first or ...
				// place first the category being filtered (if order is 'FLEXIcontent')
				$catids = array();
				$nn = 1;
				foreach ($row->catids as $key => $_icat)
				{
					if ( !isset($this->itemCats[$_icat]) ) continue;
					$category = & $this->itemCats[$_icat];

					// Place first category of category filter
					if ($this->filter_order === 'catsordering' && (int) $this->getModel()->getState('filter_cats'))
					{
						$isFilterCat = ((int) $category->id === (int) $this->getModel()->getState('filter_cats'));
						if ($isFilterCat) $catids[0] = $_icat;
						else $catids[$nn++] = $_icat;
					}

					// Place first the main category of the item, in ALL cases except if doing per category FLEXIcontent ordering
					elseif ($this->filter_order !== 'catsordering')
					{
						$isMainCat = ((int) $category->id === (int) $row->catid);
						if ($isMainCat) $catids[0] = $_icat;
						else $catids[$nn++] = $_icat;
					}
					// $this->filter_order=='catsordering' AND filter_cats is empty, ordering by first found category, DONOT reoder the display
					else
					{
						$catids[$nn-1] = $_icat;
						$nn++;
					}
				}
				$row->catids = $catids;

				$nr = count($row->catids);
				$ix = 0;
				$nn = 0;
				$row_cats  = array();
				$cat_names = array();
				$_maincat = '';
				for ($nn=0; $nn < $nr; $nn++)
				{
					$_item_cat = $row->catids[$nn];
					if ( !isset($this->itemCats[$_item_cat]) ) continue;

					$category = & $this->itemCats[$_item_cat];
					$isMainCat = ((int)$category->id == (int)$row->catid);

					$catClass = ($isMainCat ? 'maincat' : 'secondarycat').
						(($ix==0 && $this->reOrderingActive) ? ' orderingcat' : '');

					$catLink	= 'index.php?option=com_flexicontent&amp;'.$cats_task.'edit&amp;id='. $category->id;
					$title = htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8');

					$short_name = StringHelper::strlen($title) > 40  ? StringHelper::substr( $title , 0 , 40) . '...' : $title;
					if (!$isMainCat)
					{
						$row_cats[] = !$this->perms->CanCats ? $short_name : '
							<span class="'.$catClass.'" title="'.$edit_cat_title.'">
								<a href="'.$catLink.'">'.$short_name.'</a>
							</span>';
						$cat_names[] = $short_name;
					}
					else
					{
						$_maincat = !$this->perms->CanCats ? '<span class="badge">' . $short_name . '</span>' : '
						<span class="'.$catClass.'" title="'.$edit_cat_title.'">
							<a href="'.$catLink.'">'.$short_name.'</a>
						</span>';
					}
					$ix++;
				}
				echo $_maincat;
				echo count($row_cats) ? '
					<span class="btn btn-mini ' . $this->popover_class . ' nowrap_box" onclick="jQuery(this).next().toggle(400);" data-content="'.flexicontent_html::getToolTip(Text::_('FLEXI_CATEGORIES'), '<ul class="fc_plain"><li>'.implode('</li><li>', $cat_names).'</li></ul>', 0, 1).'">
						'.count($row_cats).' <i class="icon-tree-2"></i>
					</span>
					<div class="fc_assignments_box fc_cats">' : '';
				echo count($row_cats) > 8
					? implode(', ', $row_cats)
					: (count($row_cats) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_cats) . '</li></ul>' : '');
				echo count($row_cats) ? '</div>' : '';
				?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['tags'])): ?>
			<td class="col_tag hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
					$row_tags  = array();
					$tag_names = array();
					foreach ($row->tagids as $key => $_itag)
					{
						if ( isset($this->itemTags[$_itag]) )
						{
							$row_tags[] = ' <span class="itemtag">'.$this->itemTags[$_itag]->name.'</span> ';
							$tag_names[] = $this->itemTags[$_itag]->name;
						}
					}
					echo count($row_tags) ? '
						<span class="btn btn-mini ' . $this->popover_class . ' nowrap_box" onclick="jQuery(this).next().toggle(400);" data-content="'.flexicontent_html::getToolTip(Text::_('FLEXI_TAGS'), '<ul class="fc_plain"><li>'.implode('</li><li>', $tag_names).'</li></ul>', 0, 1).'">
							'.count($row_tags).' <i class="icon-tags"></i>
						</span>
						<div class="fc_assignments_box fc_tags">' : '';
					echo count($row_tags) > 8
						? implode(', ', $row_tags)
						: (count($row_tags) ? '<ul class="fc_plain"><li>' . implode('</li><li>', $row_tags) . '</li></ul>' : '');
					echo count($row_tags) ? '</div>' : '';
				?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['created'])) : ?>
			<td class="col_created hidden-phone" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo HTMLHelper::_('date',  $row->created, $date_format); ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['modified'])) : ?>
			<td class="col_revised hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php
				if ($row->modified == $row->created)  echo Text::_('Same as creation');
				else echo ($row->modified != $_NULL_DATE_) ? HTMLHelper::_('date', $row->modified, $date_format) : $_NEVER_;
				?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['hits'])) : ?>
			<td class="col_hits center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo '<span class="badge bg-info badge-info"> ' . ($row->hits ?: 0) . '</span>'; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['votes'])) : ?>
			<td class="col_votes center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo '<span class="badge bg-success badge-success"> ' . ($row->rating_count ?: 0) . '</span>'; ?>
			</td>
			<?php endif; ?>


			<?php if (!isset($disable_columns['ratings'])) : ?>
			<td class="col_ratings center hidden-phone hidden-tablet" style="<?php echo $this->hideCol($colposition++); ?>" >
				<?php echo '<span class="badge bg-warning badge-warning"> ' .sprintf('%.0f', (float) $row->rating) .'%</span>'; ?>
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

		if ($needsApproval)
		{
			$ctrl_task = 'items.approval';
			ToolbarHelper::spacer();
			ToolbarHelper::divider();
			ToolbarHelper::spacer();
			ToolbarHelper::custom($ctrl_task, 'apply.png', 'apply.png', 'FLEXI_APPROVAL_REQUEST');
		}

		ToolbarHelper::spacer();
		ToolbarHelper::spacer();
		?>
	</tbody>

	</table>


	<div>
		<?php echo $pagination_footer; ?>
	</div>

	<div style="margin-top: 48px;">
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-publish" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_PUBLISHED_DESC' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-unpublish" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-archive" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_ARCHIVED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-trash" style="font-size: 16px;"></span>	<?php echo Text::_( 'FLEXI_TRASHED' ); ?></div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-checkmark-2" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_NOT_FINISHED_YET' ); ?> <br> (<?php echo Text::_( 'FLEXI_PUBLISHED' ); ?>)</div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-clock" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_NEED_TO_BE_APPROVED' ); ?> <br> (<?php echo Text::_( 'FLEXI_UNPUBLISHED' ); ?>)</div>
		<div class="fc-iblock" style="width: 140px; min-height:2em; vertical-align: top; padding: 6px;"><span class="icon-pencil-2" style="font-size: 16px;"></span> <?php echo Text::_( 'FLEXI_TO_WRITE_DESC' ); ?> <br> (<?php echo Text::_( 'FLEXI_UNPUBLISHED' ); ?>)</div>
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
	<?php echo HTMLHelper::_('form.token'); ?>

	<?php if ($this->unassociated && !count($this->rows)) echo '</div>'; ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->

<?php
Factory::getDocument()->addScriptDeclaration('
	function fc_edit_jarticle_modal_load( container )
	{
		if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=articles") != -1 )
		{
			container.dialog("close");
		}
	}
	function fc_edit_jarticle_modal_close()
	{
		//window.location.reload(false);
		window.location.href = \'index.php?option=com_flexicontent&view=items\';
		document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
	}

	function fc_edit_batch_modal_close()
	{
		//window.location.reload(false);
		window.location.href = \'index.php?option=com_flexicontent&view=items\';
		document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
	}
');
