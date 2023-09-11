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
$ctrl     = 'filemanager.';
$hlpname  = 'fcfilemanager';
$isAdmin  = $app->isClient('administrator');
$useAssocs= false;

$ctrl_task  = 'task=filemanager.';
$ctrl_task_authors = 'task=users.';
$action_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;' . JSession::getFormToken() . '=1&amp;' . $ctrl_task;

//$this->folder_mode = 1; // test
//$this->CanViewAllFiles = 0; // test
$isFilesElement = $this->view == 'fileselement';

$uri      = JUri::getInstance();
$base     = $uri->toString( array('scheme', 'host', 'port'));

$editor   = $jinput->getCmd('editor', '');
$isXtdBtn = $jinput->getCmd('isxtdbtn', '');
$function = $jinput->getCmd('function', 'jSelectFcfile');
$onclick  = $this->escape($function);

if (!empty($editor))
{
	// This view is used also in com_menus. Load the xtd script only if the editor is set!
	JFactory::getDocument()->addScriptOptions('xtd-fcfiles', array('editor' => $editor));
	$onclick = "jSelectFcfile";
}

/**
 * COMMON CSS classes and COMMON repeated texts
 */

$btn_class = 'btn';
$ico_class = 'fc-man-icon-s';
$out_class = FLEXI_J40GE ? 'btn btn-outline-dark' : 'btn';

$hint_image = '<i class="icon-info"></i>';//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$warn_image = '<i class="icon-warning"></i>';//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/note.gif', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$conf_image = '<i class="icon-cog"></i>';

$secure_folder_tip  = '<i data-placement="bottom" class="icon-info fc-man-icon-s '.$this->tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';
$stamp_folder_tip  = '<i data-placement="bottom" class="icon-info fc-man-icon-s '.$this->tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_DOWNLOAD_STAMPING', 'FLEXI_FILE_DOWNLOAD_STAMPING_CONF_FILE_FIELD_DESC', 1, 1).'"></i>';

// Common language strings
$edit_entry = JText::_('FLEXI_EDIT_FILE', true);
$view_entry = JText::_('FLEXI_VIEW', true);
$insert_entry = JText::_('FLEXI_INSERT', true);
$usage_in_str = JText::_('FLEXI_USAGE_IN', true);
$fields_str = JText::_('FLEXI_FIELDS', true);

$fcfilter_attrs_row  = ' class="input-prepend fc-xpended-row" ';

$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';

// Load JS tabber lib
$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs


$use_jmedia_man = !$isFilesElement || ($this->fieldid && (int) $this->field->parameters->get('use_myfiles', 0) === 2);

if ($use_jmedia_man)
{
	if ($this->layout === 'image')
	{
		$filetypes = 'folders,images';
	}
	elseif (!$isFilesElement)
	{
		$filetypes = 'folders,images,docs,videos';
	}
	else
	{
		$filetypes = $this->field->parameters->get('jmedia_filetypes', array('folders', 'images'));
		$filetypes = implode(',', $filetypes);
	}
}


// Calculated configuration values
$isAdmin  = $app->isClient('administrator');
$dbFolder = !strlen($this->target_dir) || $this->target_dir==2  ?  ''  :  ($this->target_dir==0 ? 'M' : 'S');
$_tmpl = $isFilesElement ? 'component' : '';


$enable_multi_uploader = 1;
$nonimg_message = $this->layout === 'image' ? '\'\'' : '-1';
$uploader_tag_id = 'fc_filesman_uploader';
$up_sfx_n = $this->fieldid ?: '_';


/**
 * JS for Columns chooser box and Filters box
 */

flexicontent_html::jscode_to_showhide_table(
	'mainChooseColBox',
	'adminListTableFC' . $this->getModel()->view_id,
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
		'addfiles_box' => 1,
	);

$show_addfiles = !empty($tools_state->addfiles_box);
$show_addfiles ? 1 : 0;



/**
 * ICONS and reusable variables
 */



/**
 * Order stuff and table related variables
 */

$list_total_cols = $isFilesElement ? 17 : 17;  // fileselement view has 1 more column the direct delete button column, and 1 ... less column the number of assigned items


// Optional columns of DB-mode
if ($this->folder_mode || !$this->CanViewAllFiles)
{
	unset($this->cols['uploader']);
}
if ($this->folder_mode)
{
	unset($this->cols['file_id']);
}

if (count($this->optional_cols) - count($this->cols) > 0)
{
	$list_total_cols -= (count($this->optional_cols) - count($this->cols));
}

if (empty($this->cols['usage']))  // This is 2 columns so remove 1 more
{
	$list_total_cols--;
}


/**
 * Add inline JS
 */

$js = '';

$js .= (!$isXtdBtn ? "" : "
(function() {
	\"use strict\";
	/**
	 * Javascript to insert the link
	 * View element calls jSelectFcfile when a fcfile is clicked
	 * jSelectFcfile creates the link tag, sends it to the editor,
	 * and closes the select frame.
	 **/
	window.jSelectFcfile = function(id, title, contentid, object, link, lang)
	{
		var hreflang = '', editor, tag;


		if (!Joomla.getOptions('xtd-fcfiles')) {
			// Something went wrong!
			if (window.parent.Joomla.Modal) window.parent.Joomla.Modal.getCurrent().close();
			else if (window.parent.jModalClose) window.parent.jModalClose();
			return false;
		}

		editor = Joomla.getOptions('xtd-fcfiles').editor;

		if (lang !== '')
		{
			hreflang = ' hreflang=\"' + lang + '\"';
		}

		tag = '<a' + hreflang + ' href=\"' + link + '\">' + title + '</a>';

		/** Use the API, if editor supports it **/
		if (!!window.parent.Joomla.editors.instances[editor]) {
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

				if (functionName === 'jSelectFcfile') {
					// Used in xtd_fcfiles
					window[functionName](event.target.getAttribute('data-id'), event.target.getAttribute('data-title'), event.target.getAttribute('data-content-id'), null, event.target.getAttribute('data-uri'), event.target.getAttribute('data-language'));
				} else {
					// Used in com_menus
					window.parent[functionName](
						event.target.getAttribute('data-id'),
						event.target.getAttribute('data-title'),
						event.target.getAttribute('data-content-id'),
						null,
						event.target.getAttribute('data-uri'),
						event.target.getAttribute('data-language')
					);
				}
			})
		}
	});
})();

");


if ($use_jmedia_man)
{
	$js .= "
	function fman_toggle_link_type(v)
	{
		if (v=='2')
		{
			document.getElementById('file-url-data-row').style.display = 'none';
			document.getElementById('file-url-data').disabled = true;
			document.getElementById('file-jmedia-data-row').style.display = '';
			document.getElementById('file-jmedia-data').disabled = false;
		}
		else
		{
			document.getElementById('file-jmedia-data-row').style.display = 'none';
			document.getElementById('file-jmedia-data').disabled = true;
			document.getElementById('file-url-data-row').style.display = '';
			document.getElementById('file-url-data').disabled = false;
		}

		return true;
	}
	";
}


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
	delFilter('filter_uploader');
	delFilter('filter_state');
	delFilter('filter_access');
	delFilter('filter_lang');
	delFilter('filter_url');
	delFilter('filter_stamp');
	delFilter('filter_secure');
	delFilter('filter_ext');
	delFilter('item_id');
	delFilter('filter_order');
	delFilter('filter_order_Dir');
}

function disabledEventPropagation(event)
{
	if (event.stopPropagation)
	{
		event.stopPropagation();
	}
	else if(window.event)
	{
		window.event.cancelBubble=true;
	}
}

// Thumbnail zoom
function fman_zoom_thumb(e, obj)
{
	disabledEventPropagation(e);

	var box = jQuery(obj).closest('.fc-fileman-grid-thumb-box');
	var img = box.find('img.fc-fileman-thumb');
	var btn = box.find('.fc-fileman-preview-btn');

	if (img.length==0) return;
	var IEversion = fc_isIE();

	if (img.hasClass('fc_zoomed'))
	{
		btn.removeClass('active btn-info');
		img.removeClass('fc_zoomed');
		setTimeout(function(){
			img.removeClass('fc_zooming');
			jQuery('#fc-fileman-overlay').hide();
			if (IEversion && IEversion < 9) img.css('left', '');
		}, (!IEversion || IEversion > 9 ? 320 : 20));
	}
	else
	{
		if (img.hasClass('fc_zooming')) return;
		jQuery('#fc-fileman-overlay').show();
		img.addClass('fc_zooming');
		setTimeout(function(){
			img.addClass('fc_zoomed');
			btn.addClass('active btn-info');
			if (IEversion && IEversion < 9) img.css('left', jQuery(window).width()/2-(img.width()/2));
		}, 20);
	}
}


// Toggle file selection (thumbs view) when click on parent container
function fman_toggle_thumb_selection(box, new_value)
{
	var i = box.find('.fc-fileman-selection-mark');
	new_value ? box.addClass('selected') : box.removeClass('selected');
	new_value ? i.addClass('selected') : i.removeClass('selected') ;
}


// Sync a file selection between the details/thumb views
function fman_sync_cid(id, is_cb)
{
	is_cb = !!is_cb;
	var CB  = jQuery('input#cb' + id);
	var CB_box = jQuery('span#_cb' + id);

	var c = CB.prop('checked');
	if (!is_cb)
	{
		CB.prop('checked', !c);
		!c ? CB.attr('checked', 'checked') : CB.removeAttr('checked');

		// Run onclick function of the input TAG, this must be after the 'checked' property is manually modified
		var input = CB.get(0);
		if (typeof input.onclick == 'function')
		{
			input.onclick.apply(input);
		}
	}

	fman_toggle_thumb_selection(CB_box.closest('.fc-fileman-grid-thumb-box'), !c);
}


// Select ALL files in thumbnails view
function fman_set_cids(el)
{
	var val = el.prop('checked');
	val ? el.closest('.btn').addClass('".$this->btn_iv_class."') : el.closest('.btn').removeClass('".$this->btn_iv_class."');

	jQuery('div.adminthumbs.fcmanthumbs').children('.fc-fileman-grid-thumb-box').each(function(index, value)
	{
		fman_toggle_thumb_selection(jQuery(value), val);
	});
}


var _file_data = new Array();
";

// Output file data JSON encoded so that they are available to JS code
// but exclude parameters and other array data from encoding since we will not need them
foreach ($this->rows as $i => $row)
{
	$data = new stdClass();
	foreach($row as $j => $d) {
		if (!is_array($d) && !is_object($d)) $data->$j = utf8_encode($d ?? '');
	}
	$js .= '  _file_data[' . $i . '] = ' . json_encode($data) . ";\n";
}

if ($js)
{
	$document->addScriptDeclaration($js);
}
$js = '';



// ***
// *** Slider for files list view
// ***

flexicontent_html::loadFramework('nouislider');

$cfg = new stdClass();
$cfg->slider_name = 'fc-fileman-list-thumb-size';
$cfg->element_selector = 'div.fc-fileman-list-thumb-box';
$cfg->element_class_prefix = 'thumb_';
$cfg->values = array(40, 60, 90, 120, 150);

$thumb_size['fm-list'] = $jinput->cookie->get($cfg->slider_name . '-val', 40, 'int');
$cfg->initial_pos = (int) array_search($thumb_size['fm-list'], $cfg->values);
if ($cfg->initial_pos === false)
{
	$cfg->initial_pos = 0;
}
$thumb_size['fm-list'] = $cfg->values[$cfg->initial_pos];
$jinput->cookie->set($cfg->slider_name . '-val', $thumb_size['fm-list']);

$cfg->labels = array();
foreach($cfg->values as $value) $cfg->labels[] = $value .'x'. $value;
//include(dirname(__FILE__). '/../../filemanager/tmpl/layouts/single_slider.php');

$element_classes = array();
foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

$element_class_list = implode(' ', $element_classes);
$step_values = '[' . implode(', ', $cfg->values) . ']';
$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

$js .= "
	fc_attachSingleSlider({
		'name': '".$cfg->slider_name."',
		'step_values': ".$step_values.",
		'step_labels': ".$step_labels.",
		'initial_pos': ".$cfg->initial_pos.",
		'start_hidden': false,
		'element_selector': '".$cfg->element_selector."',
		'element_class_list': '".$element_class_list."',
		'element_class_prefix': '".$cfg->element_class_prefix."',
		'elem_container_selector': '',
		'elem_container_class': '',
		'elem_container_prefix': '',
	});
";



// ***
// *** Slider for files grid view
// ***

$cfg = new stdClass();
$cfg->slider_name = "fc-fileman-grid-thumb-size";
$cfg->element_selector = "div.fc-fileman-grid-thumb-box";
$cfg->element_class_prefix = "thumb_";
$cfg->values = array(90, 120, 150, 200, 250);
$cfg->labels = array();

$thumb_size['fm-grid'] = $jinput->cookie->get($cfg->slider_name . '-val', 90, 'int');
$cfg->initial_pos = (int) array_search($thumb_size['fm-grid'], $cfg->values);
if ($cfg->initial_pos === false)
{
	$cfg->initial_pos = 0;
}
$thumb_size['fm-grid'] = $cfg->values[$cfg->initial_pos];
$jinput->cookie->set($cfg->slider_name . '-val', $thumb_size['fm-grid']);

foreach($cfg->values as $value) $cfg->labels[] = $value .'x'. $value;
//include(dirname(__FILE__). '/../../filemanager/tmpl/layouts/single_slider.php');

$element_classes = array();
foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

$element_class_list = implode(' ', $element_classes);
$step_values = '[' . implode(', ', $cfg->values) . ']';
$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

$js .= "
	fc_attachSingleSlider({
		'name': '".$cfg->slider_name."',
		'step_values': ".$step_values.",
		'step_labels': ".$step_labels.",
		'initial_pos': ".$cfg->initial_pos.",
		'start_hidden': true,
		'element_selector': '".$cfg->element_selector."',
		'element_class_list': '".$element_class_list."',
		'element_class_prefix': '".$cfg->element_class_prefix."',
		'elem_container_selector': '',
		'elem_container_class': '',
		'elem_container_prefix': '',
	});
";



if ($enable_multi_uploader)
{
	// ***
	// *** Create slider for resizing thumbnails in uploader
	// ***

	$cfg = new stdClass();
	$cfg->slider_name = 'fc-uploader-grid-thumb-size';
	$cfg->elem_container_selector = '#'.$uploader_tag_id . $up_sfx_n;
	$cfg->element_selector = '#'.$uploader_tag_id . $up_sfx_n .' ul.plupload_filelist > li';
	$cfg->element_class_prefix = 'thumb_';
	$cfg->values = array(90, 120, 150, 200, 250);
	$cfg->labels = array();

	$thumb_size['up-grid'] = $jinput->cookie->get($cfg->slider_name . '-val', 90, 'int');
	$cfg->initial_pos = (int) array_search($thumb_size['up-grid'], $cfg->values);
	if ($cfg->initial_pos === false)
	{
		$cfg->initial_pos = 1;
	}
	$thumb_size['up-grid'] = $cfg->values[$cfg->initial_pos];
	$jinput->cookie->set($cfg->slider_name . '-val', $thumb_size['up-grid']);

	foreach($cfg->values as $value) $cfg->labels[] = $value .'x'. $value;

	$element_classes = array();
	foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

	$element_class_list = implode(' ', $element_classes);
	$step_values = '[' . implode(', ', $cfg->values) . ']';
	$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

	$upload_options = array(
		'action' => JUri::base(true) . '/index.php?option=com_flexicontent&task=filemanager.uploads&history=' . ($isFilesElement ? 1 : 0)
			. '&'.JSession::getFormToken().'=1' . '&fieldid='.$this->fieldid . '&u_item_id='.$this->u_item_id,
		'upload_maxcount' => 0,
		'layout' => $this->layout,
		'edit_properties' => true,
		'add_size_slider' => true,
		'refresh_on_complete' => true,
		'height_spare' => ($isFilesElement ? 350 : 430),
		'thumb_size_slider_cfg' => "{
			name: '".$cfg->slider_name."',
			step_values: ".$step_values.",
			step_labels: ".$step_labels.",
			initial_pos: ".$cfg->initial_pos.",
			element_selector: '".$cfg->element_selector."',
			element_class_list: '',
			element_class_prefix: '',
			elem_container_selector: '".$cfg->elem_container_selector."',
			elem_container_class: '".$element_class_list."',
			elem_container_prefix: '".$cfg->element_class_prefix."'
		}"
	);

	JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
	$upConf = JHtml::_('fcuploader.getUploadConf', $this->field);
	$uploader_html = JHtml::_('fcuploader.getUploader', $this->field, $this->u_item_id, $uploader_tag_id, $up_sfx_n, $upload_options);

	$js .= '
		setTimeout(function(){
			var defaultTab = 0;
			var uploderTab = 0;
			var IEversion = fc_isIE();
			var is_IE8_IE9 = IEversion && IEversion < 10;
			if (is_IE8_IE9) fctabber["fc-fileman-addfiles"].tabShow(uploderTab);

			// Show outer container of uploader
			jQuery("#fc-fileman-formbox-2").show();
			jQuery("#'.$uploader_tag_id . $up_sfx_n.'").css("min-height", 180);

			// Show uploader
			'.$uploader_tag_id.'.toggleUploader("'.$up_sfx_n.'");

			// Also set filelist height
			'.$uploader_tag_id.'.autoResize("'.$up_sfx_n.'");

			// Uploader does not initialize properly when hidden in IE8 / IE9 with "runtime": "html4" (it does if using "runtime": "flash")
			if (!is_IE8_IE9 || fc_has_flash_addon()) fctabber["fc-fileman-addfiles"].tabShow(defaultTab);

			// Hide basic uploader form if using multi-uploader script
			jQuery("#fc-fileman-formbox-1").hide();
		}, 20);
	';
}

if ($use_jmedia_man)
{
	$js .= '
	document.getElementById("file-jmedia-data").disabled = true;
	';
}

if ($js)
{
	$document->addScriptDeclaration('
		jQuery(document).ready(function()
		{
			' . $js . '
		});
	');
}


/*<div id="themeswitcher" class="pull-right"> </div>
<script>
	jQuery(function() {
		jQuery.fn.themeswitcher && jQuery('#themeswitcher').themeswitcher({cookieName:''});
	});
</script>*/

?>

<div id="fc-fileman-overlay" onclick="jQuery('img.fc_zoomed, li.fc_zoomed .plupload_img_preview img').trigger('click');"></div>


<div id="flexicontent" class="flexicontent">

<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>" id="fileman">

<?php if (!empty( $this->sidebar) && FLEXI_J40GE == false) : ?>

	<div id="j-sidebar-container" class="span2 col-md-2">

		<?php echo str_replace('type="button"', '', $this->sidebar); ?>

	</div>
	
	<div id="j-main-container" class="span10 col-md-10">

	<?php else : ?>

		<div id="j-main-container" class="span12 col-md-12">

<?php endif;?>


<?php if ($isFilesElement): ?>
	<div class="alert alert-info" style="display: none; width: 300px;">
		<?php echo JText::_('FLEXI_ASSIGNING') . ' ... ' . JText::_('FLEXI_PLEASE_WAIT'); ?>
	</div>
<?php endif; ?>


<?php /* echo JHtml::_('tabs.start'); */ ?>
<!--<div class="fctabber" id="fc-fileman-addfiles">-->
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">

	<!-- File listing -->

	<?php /* echo JHtml::_('tabs.panel', JText::_( 'FLEXI_FILEMAN_LIST' ), 'filelist' ); */ ?>
	<!--
	<div class="tabbertab" id="filelist_tab" data-icon-class="icon-list">
		<h3 class="tabberheading hasTooltip" data-placement="bottom" title="<?php echo JText::_('FLEXI_FILES_REGISTRY_DESC'); ?>"> <?php echo JText::_('FLEXI_FILES_REGISTRY'); ?> </h3>
	-->
	<div class="<?php echo $show_addfiles ? 'span6 col-md-6' : ''; ?> fullwidth_1270" id="fc-fileman-fileslist-col" data-icon-class="icon-list">
		<fieldset class="fc-formbox" id="fc-fileman-formbox-0">

		<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?>&amp;layout=<?php echo $this->layout; ?>&amp;field=<?php echo $this->fieldid?>" method="post" name="adminForm" id="adminForm">

			<div id="fc-managers-header">

			<?php if (!$this->folder_mode) : ?>

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
							<?php echo '<i class="icon-filter"></i>' . ($isFilesElement && $this->count_filters ? JText::_('FLEXI_FILTERS') : ''); ?>
							<?php echo ($this->count_filters  ? ' <sup>' . $this->count_filters . '</sup>' : ''); ?>
						</div>

						<div id="fc-filters-box" <?php if (!$this->count_filters || empty($tools_state->filters_box)) echo 'style="display:none;"'; ?> class="fcman-abs" onclick="var event = arguments[0] || window.event; event.stopPropagation();">
							<?php
							/**
							 * When layout == image then some filters are not applicable
							 */
							if ($this->layout !== 'image')
							{
								echo !empty($this->cols['state']) ? $this->lists['filter_state'] : '';
								echo !empty($this->cols['access']) ? $this->lists['filter_access'] : '';
								echo !empty($this->cols['lang']) ? $this->lists['filter_lang'] : '';
								echo $this->lists['filter_url'];
								echo !empty($this->cols['stamp']) ? $this->lists['filter_stamp'] : '';
								echo !empty($this->cols['target']) && ! $dbFolder ? $this->lists['filter_secure'] : '';
							}

							echo $this->lists['filter_uploader'];
							echo $this->lists['filter_ext'];
							echo $this->lists['item_id'];
							?>

							<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn btn-outline-secondary" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
						</div>

						<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class . (FLEXI_J40GE ? ' btn-outline-dark ' : ' ') . $this->tooltip_class; ?>" onclick="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-cancel"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
					</div>

				</div>

			<?php endif; ?>


			<?php if (!$this->folder_mode) : ?>

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

			<?php endif; ?>

				<div class="fcclear"></div>

				<div class="nowrap_box" style="margin: 6px 0;">
					<div class="<?php echo $this->btn_sm_class . ' ' . $this->tooltip_class; ?>" style="padding: 0 0 0 4px;"
						data-title="<?php echo flexicontent_html::getToolTip('', 'JGLOBAL_CHECK_ALL', 1, 1); ?>" data-placement="bottom"
					>
						<div class="group-fcset" style="display: inline-block; vertical-align: middle;">
							<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value=""
								onclick="Joomla.checkAll(this); fman_set_cids(jQuery(this));" style="display: none;" />
							<label for="checkall-toggle" class="green single" style="color: inherit;">
								&nbsp;<?php echo JText::_('FLEXI_ALL'); ?>
							</label>&nbsp;
						</div>
					</div>

					<?php echo '
						<span style="cursor: pointer;" class="fc_shown_1271 ' . $this->btn_sm_class . ' btn-success ' . $this->tooltip_class . '"
							onclick="var box = document.getElementById(\'fc-fileman-addfiles\'); fc_fm_add_handle = fc_showAsDialog(box, 0, 0, null, { title: \'' . JText::_('FLEXI_ADD_FILE',  true) . '\', visibleOnClose: 1, paddingW: 10, paddingH: 10}); return false;"
							data-title="' . flexicontent_html::getToolTip('', 'FLEXI_ADD_FILE', 1, 1) . '" data-placement="bottom"
						>
							<span class="icon-upload"></span>
						</span>
					'; ?>
					<?php echo '
						<span style="' . ($show_addfiles ? 'display: none;' : '') . '" class="fc_hidden_1270 btn-success fc_noeffect ' . $this->btn_sm_class . ' ' . $this->tooltip_class . '"
							onclick="var tagid = \'fc-fileman-addfiles-col\'; var fileslist_col = jQuery(\'#fc-fileman-fileslist-col\'); var isv = jQuery(\'#\' + tagid).is(\':visible\'); isv ? fileslist_col.removeClass(\'span6 col-md-6\') : fileslist_col.addClass(\'span6 col-md-6\'); fc_config_store(FCMAN_conf, \'addfiles_box\', (isv ? 0 : 1)); fc_toggle_box_via_btn(tagid, this, \'\', jQuery(this).next()); return false;"
							data-title="' . flexicontent_html::getToolTip('', 'FLEXI_ADD_FILE', 1, 1) . '" data-placement="bottom"
						>
							<span class="icon-upload"></span>
						</span>
					'; ?>
					<?php echo '
						<span style="' . (!$show_addfiles ? 'display: none;' : '') . '" class="fc_hidden_1270 fc_noeffect ' . $this->btn_sm_class . ' ' . $this->tooltip_class . '"
							onclick="var tagid = \'fc-fileman-addfiles-col\'; var fileslist_col = jQuery(\'#fc-fileman-fileslist-col\'); var isv = jQuery(\'#\' + tagid).is(\':visible\'); isv ? fileslist_col.removeClass(\'span6 col-md-6\') : fileslist_col.addClass(\'span6 col-md-6\'); fc_config_store(FCMAN_conf, \'addfiles_box\', (isv ? 0 : 1)); fc_toggle_box_via_btn(tagid, this, \'\', jQuery(this).prev()); return false;"
							data-title="' . flexicontent_html::getToolTip('', 'FLEXI_EXPAND', 1, 1) . '" data-placement="bottom"
						>
							<span class="icon-expand-2"></span>
						</span>
					'; ?>


					<?php if ($isFilesElement): ?>
						<div class="fc-iblock nowrap_box" style="position: relative; vertical-align: middle;">
							<span class="btn btn-primary <?php echo $this->btn_sm_class . ' ' . $this->tooltip_class; ?>" id="insert_selected_btn" onclick="fc_fileselement_assign_files(jQuery(this));"
								data-title="<?php echo flexicontent_html::getToolTip('', 'FLEXI_FILEMAN_INSERT_SELECTED', 1, 1); ?>" data-placement="bottom">
								<span class="icon-plus"></span> <?php echo JText::_('FLEXI_INSERT'); ?>
							</span>
							<span class="<?php echo $this->btn_sm_class . ' ' . $this->tooltip_class; ?>" onclick="fc_fileselement_delete_files()" data-title="<?php echo flexicontent_html::getToolTip('', 'FLEXI_DELETE', 1, 1); ?>" data-placement="bottom">
								<span class="icon-remove" style="color: darkred;"></span>
							</span>
						</div>
					<?php else: ?>
						<span class="<?php echo $this->btn_sm_class . ' ' . $this->tooltip_class; ?>" onclick="if (document.adminForm.boxchecked.value == 0) { alert(Joomla.JText._('FLEXI_NO_ITEMS_SELECTED')); } else { if (confirm(Joomla.JText._('FLEXI_ARE_YOU_SURE'))) { Joomla.submitbutton('filemanager.remove'); } }" data-title="<?php echo flexicontent_html::getToolTip('', 'FLEXI_DELETE', 1, 1); ?>" data-placement="bottom">
							<span class="icon-remove" style="color: darkred;"></span>
						</span>
					<?php endif; ?>


					<div class="btn-group" style="margin: 0 12px;">
						<button type="button" class="<?php echo $this->btn_sm_class; ?> list-view <?php echo $this->tooltip_class; ?> active" id="btn-fman-list-view"
							onclick="fc_toggle_view_mode(jQuery(this)); var c = jQuery('#fc_mainChooseColBox_btn'); c.removeClass('disabled').css('pointer-events', '');" data-toggle_selector=".fman_list_element" style="min-width: 40px;"
							data-title="<?php echo flexicontent_html::getToolTip('', 'FLEXI_FILEMAN_DETAILS', 1, 1); ?>" data-placement="bottom"
							>
							<i class="icon-list-view"></i>
						</button>
						<button type="button" class="<?php echo $this->btn_sm_class; ?> grid-view <?php echo $this->tooltip_class; ?>" id="btn-fman-grid-view"
							onclick="fc_toggle_view_mode(jQuery(this)); var c = jQuery('#fc_mainChooseColBox_btn'); c.hasClass('btn-primary') ? c.click() : false; c.addClass('disabled').css('pointer-events', 'none');" data-toggle_selector=".fman_grid_element" style="min-width: 40px;"
							data-title="<?php echo flexicontent_html::getToolTip('', 'FLEXI_FILEMAN_GRID', 1, 1); ?>" data-placement="bottom"
							>
							<i class="icon-grid-view"></i>
						</button>
					</div>

					<div class="fc-iblock nowrap_box" style="position: relative; vertical-align: middle;">
						<div id="fc_mainChooseColBox_btn" class="<?php echo $this->tooltip_class . ' ' . $this->btn_sm_class . ' ' . $out_class; ?> hidden-phone" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" data-title="<?php echo flexicontent_html::getToolTip('FLEXI_COLUMNS', 'FLEXI_ABOUT_AUTO_HIDDEN_COLUMNS', 1, 1); ?>" data-placement="bottom">
							<span class="icon-contract"></span><sup id="columnchoose_totals"></sup>
						</div>

						<div id="mainChooseColBox" class="group-fcset fcman-abs" style="display:none;"></div>
					</div>

					<?php if (!empty($this->minihelp) && FlexicontentHelperPerm::getPerm()->CanConfig): ?>
					<div id="fc-mini-help_btn" class=<?php echo $this->btn_sm_class . ' ' . $out_class; ?>" onclick="fc_toggle_box_via_btn('fc-mini-help', this, 'btn-primary');" >
						<span class="icon-help"></span>
						<?php echo $this->minihelp; ?>
					</div>
					<?php endif; ?>

				</div>

				<div class= "fc-iblock" style="margin: 8px 6px;">
					<select id="fc-fileman-list-thumb-size-sel" name="fc-fileman-list-thumb-size-sel" style="display: none;"></select>
					<div id="fc-fileman-list-thumb-size_nouislider" class="fman_list_element" style="display: none; max-width: 180px;"></div>
					<div class="fc_slider_input_box">
						<input id="fc-fileman-list-thumb-size-val" name="fc-fileman-list-thumb-size-val" type="text" size="12" value="140" />
					</div>

					<select id="fc-fileman-grid-thumb-size-sel" name="fc-fileman-grid-thumb-size-sel" style="display: none;"></select>
					<div id="fc-fileman-grid-thumb-size_nouislider" class="fman_grid_element" style="display: none; max-width: 180px;"></div>
					<div class="fc_slider_input_box">
						<input id="fc-fileman-grid-thumb-size-val" name="fc-fileman-grid-thumb-size-val" type="text" size="12" value="140" />
					</div>
				</div>

			</div>

			<table id="adminListTableFC<?php echo $this->getModel()->view_id; ?>" class="adminlist table fcmanlist fman_list_element">
			<thead>
    		<tr>
					<th class="center hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>

					<th>&nbsp;</th>

					<th class="center hideOnDemandClass"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
					<th class="left">
						<?php if (!$this->folder_mode) : ?>
							<?php echo JHtml::_('grid.sort', 'FLEXI_FILENAME', 'a.filename_displayed', $this->lists['order_Dir'], $this->lists['order'] ); ?>
							&nbsp; -- &nbsp;
							<?php echo JHtml::_('grid.sort', 'FLEXI_FILE_DISPLAY_TITLE', 'a.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?>
						<?php else: ?>
							<?php echo JText::_('FLEXI_FILENAME'); ?>
						<?php endif; ?>
					</th>

				<?php if (!empty($this->cols['state'])) : ?>
					<th class="center hideOnDemandClass">
						<?php echo JHtml::_('grid.sort', 'FLEXI_PUBLISHED', 'a.published', $this->lists['order_Dir'], $this->lists['order'] ); ?>
					</th>
				<?php endif; ?>
				<?php if (!empty($this->cols['access'])) : ?>
					<th class="center hideOnDemandClass hidden-phone">
						<?php echo JHtml::_('grid.sort', 'FLEXI_ACCESS', 'a.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
					</th>
				<?php endif; ?>
				<?php if (!empty($this->cols['lang'])) : ?>
					<th class="center hideOnDemandClass hidden-phone">
						<?php echo JHtml::_('grid.sort', 'FLEXI_LANGUAGE', 'a.language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
					</th>
				<?php endif; ?>

				<?php if ($this->folder_mode) : ?>
					<th class="center hideOnDemandClass"><?php echo JText::_('FLEXI_SIZE'); ?></th>
				<?php else : ?>
					<th class="center hideOnDemandClass"><?php echo JHtml::_('grid.sort', 'FLEXI_SIZE', 'a.size', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['hits'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_HITS', 'a.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['target'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo $secure_folder_tip; ?><?php echo JHtml::_('grid.sort', 'FLEXI_URL_SECURE', 'a.secure', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['stamp'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo $stamp_folder_tip; ?><?php echo JHtml::_('grid.sort', 'FLEXI_DOWNLOAD_STAMPING', 'a.stamp', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['usage'])) : ?>
					<th class="left hideOnDemandClass hidden-phone" colspan="2"><?php echo JHtml::_('grid.sort', 'FLEXI_USAGE', 'a.assignments', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!$isFilesElement) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JText::_( 'FLEXI_FILE_NUM_ITEMS' ); ?> </th>
				<?php endif; ?>

				<?php if (!empty($this->cols['uploader'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JHtml::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['upload_time'])) : ?>
					<th class="center hideOnDemandClass hidden-phone hidden-tablet"><?php echo JHtml::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'a.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!$isFilesElement) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JHtml::_('grid.sort', 'E-storage', 'estorage', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if (!empty($this->cols['file_id'])) : ?>
					<th class="center hideOnDemandClass col_id hidden-phone hidden-tablet">
						<?php echo JHtml::_('grid.sort', 'FLEXI_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order']); ?>
					</th>
				<?php endif; ?>

				<?php if ($isFilesElement) : /* Direct delete button for fileselement view */ ?>
					<th>&nbsp;</th>
				<?php endif; ?>

				</tr>
			</thead>

			<tbody>
				<?php
				$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
				$canManage = FlexicontentHelperPerm::getPerm()->CanFiles;

				// Component level ACL we do not have per file ACL
				$canedit       = $user->authorise('flexicontent.editfile', 'com_flexicontent');
				$caneditown    = $user->authorise('flexicontent.editownfile', 'com_flexicontent');
				$candelete     = $user->authorise('flexicontent.deletefile', 'com_flexicontent');
				$candeleteown  = $user->authorise('flexicontent.deleteownfile', 'com_flexicontent');
				$canpublish    = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
				$canpublishown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent');

				$file_is_selected = false;

				$imageexts = array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico');
				$index = $jinput->get('index', 0, 'INT');

				// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
				$k = 0;

				foreach ($this->rows as $i => $row)
				{
					$row->checked_out = $this->folder_mode ? 0 : $row->checked_out;
					$isOwner = $user->id && !empty($row->uploaded_by) && $row->uploaded_by == $user->id;

					// Permissions
					$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
					$row->canEdit      = $canedit || ($caneditown && $isOwner);
					$row->canEditState = $canpublish || ($canpublishown && $isOwner);
					$row->canDelete    = $candelete || ($candeleteown && $isOwner);

					unset($thumb_or_icon);
					$filename = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
					$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
					$filename_original = $filename_original ? $filename_original : $filename;

					$fileid = $this->folder_mode ? '' : $row->id;

					// Check if file is NOT an known / allowed image, and skip it if LAYOUT is 'image' otherwise display a 'type' icon
					$ext = strtolower($row->ext);
					$is_img = in_array($ext, $imageexts);

					if (!$is_img && $this->layout === 'image')
					{
						continue;
					}

					if (!$is_img)
					{
						$thumb_or_icon = empty($row->icon) ? '' : JHtml::image($row->icon, $row->filename);
					}

					if ($this->folder_mode)
					{
						$file_path = $this->img_folder . DS . $row->filename;
					}

					elseif (!$row->url && substr($row->filename, 0, 7) !== 'http://' && substr($row->filename, 0, 8) !== 'https://')
					{
						$path = !empty($row->secure) ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
						$file_path = $path . DS . $row->filename;
					}
					else
					{
						$file_path = $row->filename;
						$thumb_or_icon = 'URL';
					}

					$file_path = JPath::clean($file_path);

					// URL or media manager link
					$file_url = $row->url == 2
						? JUri::root(true) . '/' . $file_path
						: $file_path;

					$file_url = rawurlencode(str_replace('\\', '/', $file_url));

					// Use same format for output if possible
					$output_formats = array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico');
					$f = in_array($ext, $output_formats)
						? '&amp;f=' . $ext
						: '';

					if (empty($thumb_or_icon))
					{
						$thumb_or_icon = file_exists($file_path)
							? '<img class="fc-fileman-thumb" onclick="if (jQuery(this).hasClass(\'fc_zoomed\')) { fman_zoom_thumb(event, this); return false; }" src="'.JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_url.$f. '&amp;w=800&amp;h=800&amp;zc=1&amp;q=95&amp;f=jpeg&amp;ar=x" alt="'.$filename_original.'" />'
							: '<span class="badge badge-box badge-important">'.JText::_('FLEXI_FILE_NOT_FOUND').'</span>';
					}

					$row->thumb_or_icon = $thumb_or_icon;

					if (!$this->folder_mode && !$this->is_pending)
					{
						$row->count_assigned = 0;

						foreach ($this->assigned_fields_labels as $field_type => $ignore)
						{
							$row->count_assigned += $row->{'assigned_'.$field_type};
						}

						if ($row->count_assigned)
						{
							$row->assigned = array();

							foreach ($this->assigned_fields_labels as $field_type => $field_label)
							{
								if ($row->{'assigned_' . $field_type})
								{
									$icon_name = $this->assigned_fields_icons[$field_type];
									$tip = $row->{'assigned_'.$field_type} . ' ' . $field_label;
									$image = JHtml::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip, 'title="'.$usage_in_str.' '.$field_type.' '.$fields_str.'"' );
									$row->assigned[] = $row->{'assigned_'.$field_type} . ' ' . $image;
								}
							}

							$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
						}
						else
						{
							$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
						}
					}

					// Displayed filename calculated for DB-mode only
					else
					{
						$row->filename_displayed = $row->filename_original ? $row->filename_original : $row->filename;
					}


					if ($isFilesElement)
					{
						// File preview icon for content form
						$file_is_selected = isset($this->pending_file_names[$row->filename]);
						$file_preview = !in_array($ext, $imageexts) ? '' : JUri::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_url.$f. '&amp;w='.$this->thumb_w.'&amp;h='.$this->thumb_h.'&amp;zc=1&amp;q=95&amp;ar=x';

						// Link to assign file value into the content form
						$row->file_assign_link = $this->assign_mode ?
							"window.parent.fcfield_assignImage" . $this->fieldid . "(fcfiles_targetid, '" . $filename . "', '" . $file_preview . "', fcfiles_keep_modal, '" . $filename_original . "'); document.getElementById('file" . $row->id . "').className='striketext';" :
							"fc_fileselement_assign_file(fcfiles_targetid, _file_data['" . $i . "'], '" . $file_preview . "');";
					}

					// Link to items using the field
					else
					{
						$item_link = !$isAdmin ? '' : 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_fileid='. $row->id.'&amp;fcform=1&amp;filter_state=ALL';
					}
					?>
				<tr class="<?php echo 'row' . ($k % 2); ?>">
					<td class="center hidden-phone">
						<?php echo $this->pagination->getRowOffset($i); ?>
					</td>

					<td class="center <?php echo ($file_is_selected ? ' is-pending-file' : ''); ?>">
						<!--div class="adminlist-table-row"></div-->
						<?php echo JHtml::_($hlpname . '.grid_id', $i,
							!$this->folder_mode ? $row->id : rawurlencode($filename),
							false, 'cid', 'cb', '', 'fman_sync_cid(' . $i . ', 1);'
						);
						?>
					</td>

					<td class="center">
						<div class="fc-fileman-list-thumb-box thumb_<?php echo $thumb_size['fm-list'] ; ?>" onclick="fman_sync_cid(<?php echo $i; ?>, 0);">
							<?php echo $thumb_or_icon; ?>
						</div>
					</td>

					<td class="col_title smaller">
						<?php
						if (!$isFilesElement)
						{
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

							// Note we will pass true to canEdit parameter because we want to create an assign link
							echo JHtml::_($hlpname . '.edit_link', $row, $i, true, array(
								'option'   => 'com_flexicontent',
								'ctrl'     => 'mediadatas',
								'view'     => 'mediadata',
								'url_data' => '&file_id=' . $row->id,
								'keyname'  => 'mm_id',
								'noTitle'  => true,
								'linkedPrefix' => '<span class="icon-music"></span>',
								'attribs' => array(
									'class'       => $this->btn_sm_class,
									'title'       => JText::_('FLEXI_MEDIADATA_EDIT'),
								),
								'useModal' => (object) array(
									'title'       => 'FLEXI_MEDIADATA_EDIT',
									'onloadfunc'  => 'fc_edit_mmdata_modal_load',
									'onclosefunc' => 'fc_edit_mmdata_modal_close',
								),
							));
						}
						else
						{
							if ($isXtdBtn)
							{
								$vars = '&id='.$row->id;
								$link = JUri::root(true) . '/index.php?option=com_flexicontent&task=download_file' . $vars;

								$attribs = 'data-function="' . $this->escape($onclick) . '"'
									. ' data-id="' . $this->escape($row->id) . '"'
									. ' data-title="' . $this->escape($row->filename) . '"'
									. ' data-content-id="' . 0 . '"'
									. ' data-uri="' . $this->escape($link) . '"'
									. ' data-language="' . $this->escape($row->language) . '"';
								?>
								<a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
									<?php echo $row->filename; ?>
								</a>
								<?php
							}
							else
							{
								// Note we will pass true to canEdit parameter because we want to create an assign link
								echo JHtml::_($hlpname . '.edit_link', $row, $i, true, array(
									'linkedPrefix' => '<span class="icon-checkbox"></span><span class="icon-new"></span>',
									'onclick' => 'if (jQuery(this).hasClass(\'striketext\')) return; ' . $row->file_assign_link,
									'attribs' => array(
										'id' => 'file' . $row->id,
										'class' => 'fc_set_file_assignment fc-iblock text-dark ' . $this->btn_sm_class . ' ' . $this->tooltip_class,
										'title' => $insert_entry,
										'data-fileid' => $fileid,
										'data-filename' => $filename,
									)
								));
							}
						}

						if (!$this->folder_mode && $row->altname != $row->filename_displayed)
						{
							echo StringHelper::strlen($row->altname) > 100 ?
								'<br/><small><span class="badge" style="border-radius: 3px; padding: 2px 4px; margin: 6px 0 0 0;">Title</span> '.StringHelper::substr( htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8'), 0 , 100).'... </small>' :
								'<br/><small><span class="badge" style="border-radius: 3px; padding: 2px 4px; margin: 6px 0 0 0;">Title</span> '.htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8').'</small>' ;
						}
						if (!$this->folder_mode && $row->filename != $row->filename_displayed)
						{
							echo StringHelper::strlen($row->filename) > 100 ?
								'<br/><small><span class="badge" style="border-radius: 3px; padding: 2px 4px; margin: 6px 0 0 0;">Real-name</span> '.StringHelper::substr( htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8'), 0 , 100).'... </small>' :
								'<br/><small><span class="badge" style="border-radius: 3px; padding: 2px 4px; margin: 6px 0 0 0;">Real-name</span> '.htmlspecialchars($row->filename, ENT_QUOTES, 'UTF-8').'</small>' ;
						}
						?>
					</td>

				<?php if (!empty($this->cols['state'])) : ?>
					<td class="center">
						<?php echo JHtml::_('jgrid.published', $row->published, $i, $ctrl); ?>
					</td>
				<?php endif; ?>

				<?php if (!empty($this->cols['access'])) : ?>
					<td class="center hidden-phone">
					<?php
					echo $this->CanFiles && ($this->CanViewAllFiles || $user->id == $row->uploaded_by)
						? flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'class="fcfield_selectval" onchange="return Joomla.listItemTask(\'cb'.$i.'\',\''.$ctrl.'access\')"')
						: (strlen($row->access_level) ? $this->escape($row->access_level) : '-');
					?>
					</td>
				<?php endif; ?>

				<?php $row->language = empty($row->language) ? '' : $row->language; /* Set language ALL when language is empty */ ?>

				<?php if (!empty($this->cols['lang'])) : ?>
					<td class="col_lang hidden-phone">
						<?php
							/**
							 * Display language
							 */
							echo JHtml::_($hlpname . '.lang_display', $row, $i, $this->langs, $use_icon = 2, ''); ?>
					</td>
				<?php endif; ?>

					<td class="center"><?php echo $row->size; ?></td>

				<?php if (!empty($this->cols['hits'])) : ?>
					<td class="center hidden-phone"><span class="badge"><?php echo empty($row->hits) ? 0 : $row->hits; ?></span></td>
				<?php endif; ?>

				<?php if (!empty($this->cols['target'])) : ?>
					<td class="center hidden-phone"><?php echo $row->secure ? '<span class="badge bg-info badge-info">' . JText::_('FLEXI_YES') : '<span class="badge">' . JText::_('FLEXI_NO'); ?></span></td>
				<?php endif; ?>

				<?php if (!empty($this->cols['stamp'])) : ?>
					<td class="center hidden-phone"><?php echo $row->stamp ? '<span class="badge bg-info badge-info">' . JText::_('FLEXI_YES') : '<span class="badge">' . JText::_('FLEXI_NO'); ?></span></td>
				<?php endif; ?>

				<?php if (!empty($this->cols['usage'])) : ?>
					<td class="center hidden-phone">
						<span class="badge"><?php echo $row->assignments; ?></span>
					</td>
					<td class="center hidden-phone">
						<span class="nowrap_box"><?php echo $row->assigned; ?></span>
					</td>
				<?php endif; ?>

				<?php if (!$isFilesElement) : ?>
					<td class="center hidden-phone">
						<?php echo '<a class="' . $this->btn_sm_class . '" href="'.$item_link.'" title="'.$view_entry.'">'.count($row->itemids).'</a>'; ?>
					</td>
				<?php endif; ?>

				<?php if ($this->CanViewAllFiles && !empty($this->cols['uploader'])) : ?>
					<td class="center hidden-phone">
					<?php if (!$isFilesElement && $this->perms->CanAuthors) :?>
						<a target="_blank" href="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task_authors; ?>edit&amp;hidemainmenu=1&amp;cid=<?php echo $row->uploaded_by; ?>">
						<?php echo $row->uploader; ?>
						</a>
					<?php else :?>
						<?php echo $row->uploader; ?>
					<?php endif; ?>
					</td>
				<?php endif; ?>

				<?php if (!empty($this->cols['upload_time'])) : ?>
					<td class="center hidden-phone hidden-tablet">
						<?php echo JHtml::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC3' )." H:i" ); ?>
					</td>
				<?php endif; ?>

				<?php if (!$isFilesElement) : ?>
					<td class="center col_estorage hidden-phone hidden-tablet">
						<?php echo !$row->estorage_fieldid ? JText::_('FLEXI_NO') : ($row->estorage_fieldid > 0 ? JText::_('Pending') :  JText::_('Uploading')); ?>
					</td>
				<?php endif; ?>

				<?php if (!empty($this->cols['file_id'])) : ?>
					<td class="center col_id hidden-phone hidden-tablet">
						<?php echo $row->id; ?>
					</td>
				<?php endif; ?>

					<?php if ($isFilesElement) : /* Direct delete button for fileselement view */ ?>
					<td>
						<a class="btn btn-mini ntxt" href="javascript:;" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE', true); ?>')) { return Joomla.listItemTask('cb<?php echo $i; ?>','filemanager.remove'); }">
							<span class="icon-remove" title="<?php echo JText::_('FLEXI_REMOVE'); ?>"></span>
						</a>
					</td>
					<?php endif; ?>

				</tr>
				<?php
					$k++;
				}
				?>
			</tbody>

			<tfoot>

			<?php if (!$this->folder_mode && !$this->is_pending) :
				$field_legend = array();
				$this->assigned_fields_labels;
				foreach($this->assigned_fields_labels as $field_type => $field_label)
				{
					$icon_name = $this->assigned_fields_icons[$field_type];
					$tip = $field_label;
					$image = JHtml::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip);
					$field_legend[$field_type] = $image. " ".$field_label;
				}
				?>

				<tr>
					<td colspan="<?php echo $list_total_cols; ?>" style="text-align: center; border-top:0px solid black;">
						<span class="label fc_legend_box <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_USAGE_LEGEND', 'FLEXI_FILE_USAGE_LEGEND_TIP', 1, 1); ?> " ><?php echo JText::_('FLEXI_FILE_USAGE_LEGEND'); ?></span> &nbsp;
						<?php echo implode(' &nbsp; &nbsp; | &nbsp; &nbsp; ', $field_legend); ?>
					</td>
				</tr>

			<?php else : ?>
				<tr>
					<td colspan="<?php echo $list_total_cols; ?>" style="text-align: center; border-top:0px solid black;">
						--
					</td>
				</tr>
			<?php endif; ?>

			</tfoot>

			</table>

			<div id="adminListThumbsFCfiles<?php echo $this->layout.$this->fieldid; ?>" class="adminthumbs fcmanthumbs fman_grid_element" style="display: none;">
				<?php
				$imageexts = array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico');
				$index = $jinput->get('index', 0, 'INT');

				// In the case we skip rows, we need a reliable incrementing counter with no holes, used for e.g. even / odd row class
				$k = 0;

				foreach ($this->rows as $i => $row)
				{
					$row->checked_out = $this->folder_mode ? 0 : $row->checked_out;
					$isOwner = $user->id && !empty($row->uploaded_by) && $row->uploaded_by == $user->id;

					// Permissions
					$row->canCheckin   = empty($row->checked_out) || $row->checked_out == $user->id || $canCheckinRecords;
					$row->canEdit      = $canedit || ($caneditown && $isOwner);
					$row->canEditState = $canpublish || ($canpublishown && $isOwner);
					$row->canDelete    = $candelete || ($candeleteown && $isOwner);

					unset($thumb_or_icon);
					$filename = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
					$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
					$filename_original = $filename_original ? $filename_original : $filename;

					$fileid = $this->folder_mode ? '' : $row->id;

					// Check if file is NOT an known / allowed image, and skip it if LAYOUT is 'image' otherwise display a 'type' icon
					$ext = strtolower($row->ext);
					$is_img = in_array($ext, $imageexts);

					if (!$is_img && $this->layout === 'image')
					{
						continue;
					}

					$thumb_or_icon = $row->thumb_or_icon;

					if (!$this->folder_mode)
					{
						$row->count_assigned = 0;
						foreach($this->assigned_fields_labels as $field_type => $ignore)
						{
							$row->count_assigned += $row->{'assigned_'.$field_type};
						}
						if ($row->count_assigned)
						{
							$row->assigned = array();
							foreach($this->assigned_fields_labels as $field_type => $field_label)
							{
								if ( $row->{'assigned_'.$field_type} )
								{
									$icon_name = $this->assigned_fields_icons[$field_type];
									$tip = $row->{'assigned_'.$field_type} . ' ' . $field_label;
									$image = JHtml::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip, 'title="'.$usage_in_str.' '.$field_type.' '.$fields_str.'"' );
									$row->assigned[] = $row->{'assigned_'.$field_type} . ' ' . $image;
								}
							}
							$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
						}
						else {
							$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
						}
					}
					?>

				<div class="fc-fileman-grid-thumb-box thumb_<?php echo $thumb_size['fm-grid'] ; ?>" onclick="fman_sync_cid(<?php echo $i; ?>, 0);">
					<?php
					echo $thumb_or_icon;
					echo !$is_img ? '' : '
					<span class="btn fc-fileman-preview-btn icon-search" onclick="fman_zoom_thumb(event, this); return false;"></span>
					'; ?>
					<span class="btn fc-fileman-selection-mark icon-checkmark" id="_cb<?php echo $i; ?>" ></span>
					<span class="btn fc-fileman-delete-btn icon-remove" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE', true); ?>')) { document.adminForm.filename.value='<?php echo rawurlencode($row->filename);?>'; return Joomla.listItemTask('cb<?php echo $i; ?>','filemanager.remove'); }"></span>
					<span class="fc-fileman-filename-box"><?php echo $row->title_cut; ?></span>
				</div>
				<?php
					$k++;
				}
				?>

			</div>

			<?php if (!$this->folder_mode) : ?>
				<?php echo $pagination_footer; ?>
			<?php endif; ?>

			<input type="hidden" name="editor" value="<?php echo $editor; ?>" />
			<input type="hidden" name="isxtdbtn" value="<?php echo $isXtdBtn; ?>" />
			<input type="hidden" name="function" value="<?php echo $function; ?>" />

			<input type="hidden" name="boxchecked" value="0" />
			<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
			<input type="hidden" name="controller" value="filemanager" />
			<input type="hidden" name="task" value="" />
			<?php echo $_tmpl ? '<input type="hidden" name="tmpl" value="'.$_tmpl.'" />' : ''; ?>
			<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
			<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
			<input type="hidden" name="fcform" value="1" />

			<?php echo JHtml::_('form.token'); ?>
			<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
			<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
			<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
			<input type="hidden" name="filename" value="" />

			<?php /* NOTE: return URL should use & and not &amp; for variable separation as these will be re-encoded on redirect */ ?>
			<input type="hidden" name="return" value="<?php echo base64_encode('index.php?option=com_flexicontent&view='.$this->view.($_tmpl ? '&tmpl='.$_tmpl : '').'&field='.$this->fieldid.'&layout='.$this->layout.'&'.JSession::getFormToken().'=1'.'&folder_mode='.$this->folder_mode); ?>" />
		</form>

		</fieldset>

	</div>


	<!-- File(s) by uploading -->
	<div class="span6 col-md-6" id="fc-fileman-addfiles-col" <?php echo !$show_addfiles ? ' style="display: none;" ' : ''; ?> >

	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_FILES' ), 'fileupload' );*/ ?>
	<?php /* echo JHtml::_('tabs.start'); */ ?>

		<div class="fctabber fc_hidden_1270" id="fc-fileman-addfiles">

			<div class="tabbertab" id="local_tab" data-icon2-class="icon-upload fc-icon-orange" data-icon-class="fc-icon-green">
				<h3 class="tabberheading hasTooltip" data-placement="bottom" title="<?php echo JText::_( 'FLEXI_UPLOAD_FILES_DESC' ); ?>"> <?php echo JText::_( 'FLEXI_UPLOAD_FILES' ); ?> </h3>

				<?php if (!$this->CanUpload && ($this->layout != 'image' || !$isFilesElement)) : /* image layout of fileselement view is not subject to upload check */ ?>
					<?php echo sprintf( $alert_box, '', 'note', '', JText::_('FLEXI_YOUR_ACCOUNT_CANNOT_UPLOAD') ); ?>
				<?php else : ?>

				<?php if ($this->require_ftp) : ?>
				<form action="<?php echo $action_url . 'ftpValidate'; ?>" name="ftpForm" id="ftpForm" method="post">
					<fieldset title="<?php echo JText::_( 'FLEXI_FTP_LOGIN_DETAILS' ); ?>">
						<legend><?php echo JText::_( 'FLEXI_FTP_LOGIN_DETAILS' ); ?></legend>
						<table class="fc-form-tbl fcinner">
							<tbody>
								<tr>
									<td class="key">
											<label class="fc-prop-lbl" for="username"><?php echo JText::_( 'FLEXI_USERNAME' ); ?></label>
									</td>
									<td>
										<input type="text" id="username" name="username" class="input-xxlarge" size="70" value="" />
									</td>
									<td class="key">
											<label class="fc-prop-lbl" for="password"><?php echo JText::_( 'FLEXI_PASSWORD' ); ?></label>
									</td>
									<td>
											<input type="password" id="password" name="password" class="input-xxlarge" size="70" value="" />
									</td>
								</tr>
							</tbody>
						</table>
					</fieldset>
				</form>
				<?php endif; ?>

				<!-- File Upload Form -->
				<fieldset class="fc-fileman-tab" >
					<?php
					// Configuration
					$phpUploadLimit = flexicontent_upload::getPHPuploadLimit();

					$server_limit_exceeded = $phpUploadLimit['value'] < $upConf['upload_maxsize'];
					$server_limit_active = $server_limit_exceeded && ! $enable_multi_uploader;

					$conf_limit_class = $server_limit_active ? 'badge badge-box' : '';
					$conf_limit_style = $server_limit_active ? 'text-decoration: line-through;' : '';
					$conf_lim_image   = $server_limit_active ? $warn_image.$hint_image : $hint_image;
					$sys_limit_class  = $server_limit_active ? 'badge badge-box badge-important' : '';

					$has_field_upload_maxsize   = !empty($this->field) && strlen($this->field->parameters->get('upload_maxsize'));
					$has_field_resize_on_upload = !empty($this->field) && strlen($this->field->parameters->get('resize_on_upload'));

					$limit_typename = $has_field_upload_maxsize ? 'FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT' : 'FLEXI_CONF_UPLOAD_MAX_LIMIT';
					$show_server_limit = $server_limit_exceeded && ! $enable_multi_uploader;  // plupload JS overcomes server limitations so we will not display it, if using plupload

					echo '
					<span id="fc_dispInfoBox_btn" class="' . $this->btn_sm_class . '" onclick="fc_toggle_box_via_btn(\'upload_info_box\', this, \'btn-primary\');"><i class="icon-info"></i>'. JText::_( 'FLEXI_FILES_INFO_UPLOAD' ).'</span>
					'.
					($enable_multi_uploader ? '
						<span class="' . $this->btn_sm_class . ' ' . $this->tooltip_class.'" onclick="jQuery(\'#fc-fileman-formbox-1\').toggle(); jQuery(\'#fc-fileman-formbox-2\').toggle(); setTimeout(function(){ '.$uploader_tag_id.'.autoResize(\''.$up_sfx_n.'\'); }, 100);"
							id="single_multi_uploader" data-title="' . flexicontent_html::getToolTip('', 'FLEXI_TOGGLE_BASIC_UPLOADER_DESC', 1, 1) . '" style="float: ' . (JFactory::getLanguage()->isRTL() ? 'left;' : 'right;') . '"
						data-placement="'. (JFactory::getLanguage()->isRTL() ? 'right' : 'left') . '">
							'.JText::_( 'FLEXI_TOGGLE_BASIC_UPLOADER' ).'
						</span>
					' : '') . '

				<div class="fcclear"></div>

				<div id="upload_info_box" style="display:none; margin: 16px 0 0 0;">
					<!--span class="label" style="font-size: 11px; margin-right:12px;" >'.JText::_( 'FLEXI_UPLOAD_LIMITS' ).'</span-->
					<div class="fc-fileman-upload-limits-box" style="font-size: 14px !important;">
					<table class="fc_uploader_header_tbl">
						<tr class="fc-about-size-limits">
							<td>
								<div class="fc-mssg fc-info fc-nobgimage fc-about-box '.$this->tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_FILESIZE_MAX_DESC', '', 1, 1).'" data-placement="top">'.JText::_( 'FLEXI_UPLOAD_FILESIZE_MAX' ).'</div>
							</td>
							<td>
								<span class="fc-sys-upload-limit-box fc-about-conf-size-limit">
									<span class="icon-database"></span>
									<span class="'.$conf_limit_class.'" style="margin-right: 4px; '.$conf_limit_style.'">'.round($upConf['upload_maxsize'] / (1024*1024), 2).'</span> <span class="fc_hidden_580">MBytes</span>
								</span>
								'.($this->perms->SuperAdmin ?
									'<span class="icon-info '.$this->tooltip_class.'" style="padding: 2px 4px 0px 2px;" title="'.flexicontent_html::getToolTip($limit_typename, $limit_typename.'_DESC', 1, 1).'" data-placement="top"></span>
								' : '').'
								'.($server_limit_active ? /* plupload JS overcomes server limitations so we will not display it, if using plupload*/
								'
									<span class="fc-php-upload-limit-box fc-about-server-size-limit">
										<span class="icon-database"></span>
										<span class="'.$sys_limit_class.' '.$this->tooltip_class.'" style="margin-right: 4px;" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SERVER_UPLOAD_MAX_LIMIT'), JText::sprintf('FLEXI_SERVER_UPLOAD_MAX_LIMIT_DESC', $phpUploadLimit['name']), 0, 1).'">'.round($phpUploadLimit['value'] / (1024*1024), 2).'</span> MBytes
									</span>
								' : '').'
							</td>
						</tr>

						'.($upConf['resize_on_upload'] ? '
						<tr class="fc-about-dim-limits">
							<td>
								<div class="fc-mssg fc-info fc-nobgimage fc-about-box '.$this->tooltip_class.'" title="'.JText::_('FLEXI_UPLOAD_IMAGE_LIMITATION').'">
									'.JText::_( 'FLEXI_UPLOAD_DIMENSIONS_MAX' ).'
									<span class="icon-image pull-right" style="margin:2px -4px 0px 8px"></span>
								</div>
							</td>
							<td>
								<span class="fc-php-upload-limit-box">
									<span class="icon-contract-2"></span>
									<span class="'.$sys_limit_class.'" style="margin-right: 4px;">'.$upConf['upload_max_w'].'x'.$upConf['upload_max_h'].'</span> <span class="fc_hidden_580">Pixels</span>
									<span class="icon-info '.$this->tooltip_class.'" style="padding: 2px 4px 0px 2px;" title="'.htmlspecialchars(JText::_('FLEXI_UPLOAD_DIMENSIONS_MAX_DESC'), ENT_QUOTES, 'UTF-8').'" data-placement="top"></span>
								</span>
							</td>
						</tr>

						<tr class="fc-about-crop-quality-limits">
							<td>
								<div class="fc-mssg fc-info fc-nobgimage fc-about-box '.$this->tooltip_class.'" title="'.JText::_('FLEXI_UPLOAD_IMAGE_LIMITATION').'">
									'.JText::_( 'FLEXI_UPLOAD_FIT_METHOD' ).'
									<span class="icon-image pull-right" style="margin:2px -4px 0px 8px"></span>
								</div>
							</td>
							<td>
								<span class="fc-php-upload-limit-box">
									<span class="icon-scissors" style="margin-right: 4px;'.($upConf['upload_method'] ? '' : 'opacity: 0.3;').'"></span>
									<span style="margin-right: 4px;">'.JText::_($upConf['upload_method'] ? 'FLEXI_CROP' : 'FLEXI_SCALE').' , '.$upConf['upload_quality'].'% <span class="fc_hidden_580">'.JText::_('FLEXI_QUALITY', true).'</span></span>
									<span class="icon-info '.$this->tooltip_class.'" style="padding: 2px 4px 0px 2px;" title="'.htmlspecialchars(JText::_('FLEXI_UPLOAD_FIT_METHOD_DESC'), ENT_QUOTES, 'UTF-8').'" data-placement="top"></span>
								</span>
							</td>
						</tr>
						' : '').'

					</table>
					</div>

					<div class="fc-mssg-inline fc-nobgimage fc-success" style="margin: 8px 0;">
						<span class="icon-info"></span>
						' . JText::_('FLEXI_FILES_CLICK_TO_EDIT_PROPERTIES') . '
					</div>

				</div>
				<div style="margin: 16px 0 0 0;"></div>
					';
					?>

				<div class="fcclear"></div>

				<div id="filePropsForm_box_outer" style="display:none;">
					<fieldset class="fc-formbox flexicontent" id="filePropsForm_box">
						<form action="<?php echo $action_url . 'saveprops'; ?>&amp;format=raw" name="filePropsForm" id="filePropsForm" method="get" enctype="multipart/form-data">

							<!--span class="fcsep_level0" style="margin: 16px 0 12px 0; "><?php echo JText::_('FLEXI_FILE_PROPERTIES'); ?></span-->
							<table class="fc-form-tbl fcinner fcfullwidth" id="file-props-form-container">

								<tr>
									<td id="file-props-name-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILENAME', 'FLEXI_FILE_DOWNLOAD_FILENAME_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-name-lbl" for="file-props-name">
										<?php echo JText::_( 'FLEXI_FILE_FILENAME' ); ?>
										</label>
									</td>
									<td id="file-props-name-container">
										<input type="text" id="file-props-name" class="required input-xlarge" name="file-props-name" /> .
										<input type="text" id="file-props-name-ext" class="required input-small" name="file-props-name-ext" readonly="readonly" size="6" />
									</td>
								</tr>

							<?php if (!$this->folder_mode) : ?>
								<tr>
									<td id="file-props-title-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-title-lbl" for="file-props-title">
										<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
										</label>
									</td>
									<td id="file-props-title-container">
										<input type="text" id="file-props-title" size="44" class="required input-xlarge" name="file-props-title" />
									</td>
								</tr>

								<tr>
									<td id="file-props-desc-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-desc-lbl" for="file-props-desc_uploadFileForm">
										<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
										</label>
									</td>
									<td id="file-props-desc-container" style="vertical-align: top;">
										<textarea name="file-props-desc" cols="24" rows="3" id="file-props-desc_uploadFileForm" class="input-xlarge"></textarea>
									</td>
								</tr>

								<tr>
									<td id="file-props-lang-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-lang-lbl" for="file-props-lang">
										<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
										</label>
									</td>
									<td id="file-props-lang-container">
										<?php echo str_replace('file-lang', 'file-props-lang', $this->ffields['file-lang']); ?>
									</td>
								</tr>

								<tr>
									<td id="file-props-access-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-access-lbl" for="file-props-access">
										<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
										</label>
									</td>
									<td id="file-props-access-container">
										<?php echo str_replace('file-access', 'file-props-access', $this->ffields['file-access']); ?>
									</td>
								</tr>

								<?php if ($this->target_dir==2) : ?>
								<tr>
									<td id="file-props-secure-lbl-container" class="key <?php echo $this->tooltip_class; ?>" data-placement="bottom" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-props-secure-lbl">
										<?php echo JText::_( 'FLEXI_URL_SECURE' ); ?>
										</label>
									</td>
									<td id="file-props-secure-container">
										<div class="group-fcset radio">
										<?php
										//echo JHtml::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_filePropsForm' );
										$_options = array();
										$_options['0'] = JText::_( 'FLEXI_MEDIA' );
										$_options['1'] = JText::_( 'FLEXI_SECURE' );
										echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_filePropsForm');
										?>
										</div>
									</td>
								</tr>
								<?php endif; ?>
							<?php endif; ?>

								<tr>
									<td style="text-align:right; padding: 12px 4px;">
										<input type="button" id="file-props-apply" class="btn btn-success" onclick="var up = jQuery('#<?php echo $uploader_tag_id . $up_sfx_n; ?>').data('plupload_instance'); jQuery(up).data('fc_plupload_instance').submit_props_form(this, up); return false;" value="<?php echo JText::_( 'FLEXI_APPLY' ); ?>"/>
									</td>
									<td style="text-align:left; padding: 12px 4px;">
										<input type="button" id="file-props-close" class="btn" onclick="fc_file_props_handle.dialog('close'); return false;" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>"/>
									</td>
								</tr>
							</table>

							<?php echo JHtml::_('form.token'); ?>
							<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
							<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
							<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
							<input type="hidden" name="file_row_id" value="" />

							<?php /* NOTE: this AJAX submitted, return URL is not needed / not applicable */?>
						</form>

					</fieldset>
				</div>


					<fieldset class="fc-formbox" id="fc-fileman-formbox-1">
						<form action="<?php echo $action_url . 'upload'; ?>" name="uploadFileForm" id="uploadFileForm" method="post" enctype="multipart/form-data">

							<span class="fcsep_level0" style="margin: 0 0 12px 0; background-color: #444; border-radius: 0; font-weight: normal;">
								<span class="icon-plus" style="font-size: 36px; width: 1em; height: 1em; line-height: 1em; box-sizing: content-box; vertical-align: top;"></span>
								<span style="display: inline-block;">
									<span style="font-size: 16px;"><?php echo JText::_('FLEXI_BASIC_UPLOADER'); ?></span>
									<br>
									<span style="font-size: 12px;"><?php echo JText::_('FLEXI_TOGGLE_BASIC_UPLOADER_DESC'); ?></span>
								</span>
							</span>
							<table class="fc-form-tbl fcinner" id="file-upload-form-container">

								<tr>
									<td id="file-upload-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_FILE', 'FLEXI_CHOOSE_FILE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-upload-lbl" for="file-upload">
										<?php echo JText::_( 'FLEXI_CHOOSE_FILE' ); ?>
										</label>
									</td>
									<td id="file-upload-container">
										<div id="img_preview_msg" style="float:left;"></div>
										<img id="img_preview" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Preview image placeholder" style="float:left; display:none;" />
										<input type="file" id="file-upload" name="Filedata" onchange="fc_loadImagePreview(this.id,'img_preview', 'img_preview_msg', 100, 0, <?php echo $nonimg_message; ?>);" />
									</td>
								</tr>

							<?php if (!$this->folder_mode) : ?>
								<tr>
									<td id="file-title-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-title-lbl" for="file-title">
										<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
										</label>
									</td>
									<td id="file-title-container">
										<input type="text" id="file-title" size="44" class="required input-xxlarge" name="file-title" />
									</td>
								</tr>

								<tr>
									<td id="file-desc-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-desc-lbl" for="file-desc_uploadFileForm">
										<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
										</label>
									</td>
									<td id="file-desc-container" style="vertical-align: top;">
										<textarea name="file-desc" cols="24" rows="3" id="file-desc_uploadFileForm" class="input-xxlarge"></textarea>
									</td>
								</tr>

								<tr>
									<td id="file-lang-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-lang-lbl" for="file-lang">
										<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
										</label>
									</td>
									<td id="file-lang-container">
										<?php echo $this->ffields['file-lang']; ?>
									</td>
								</tr>

								<tr>
									<td id="file-access-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-access-lbl" for="file-access">
										<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
										</label>
									</td>
									<td id="file-access-container">
										<?php echo $this->ffields['file-access']; ?>
									</td>
								</tr>

								<?php if ($this->target_dir==2) : ?>
								<tr>
									<td id="secure-lbl-container" class="key <?php echo $this->tooltip_class; ?>" data-placement="bottom" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="secure-lbl">
										<?php echo JText::_( 'FLEXI_URL_SECURE' ); ?>
										</label>
									</td>
									<td id="secure-container">
										<div class="group-fcset radio">
										<?php
										//echo JHtml::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_uploadFileForm' );
										$_options = array();
										$_options['0'] = JText::_( 'FLEXI_MEDIA' );
										$_options['1'] = JText::_( 'FLEXI_SECURE' );
										echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_uploadFileForm');
										?>
										</div>
									</td>
								</tr>
								<?php endif; ?>
							<?php endif; ?>

							</table>

							<input type="submit" id="file-upload-submit" class="btn btn-success" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>" style="margin: 16px 48px 0 48px;" />

							<?php echo JHtml::_('form.token'); ?>
							<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
							<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
							<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />

							<?php /* NOTE: return URL should use & and not &amp; for variable separation as these will be re-encoded on redirect */ ?>
							<input type="hidden" name="return" value="<?php echo base64_encode('index.php?option=com_flexicontent&view='.$this->view.($_tmpl ? '&tmpl='.$_tmpl : '').'&field='.$this->fieldid.'&layout='.$this->layout.'&'.JSession::getFormToken().'=1'.'&folder_mode='.$this->folder_mode); ?>" />
						</form>

					</fieldset>

					<fieldset class="fc-formbox" id="fc-fileman-formbox-2" style="display:none;">
						<?php echo $uploader_html->container; ?>
					</fieldset>

				</fieldset>

				<?php endif; /*CanUpload*/ ?>

			</div>


			<!-- File URL by Form -->
			<?php if ($this->layout !='image' ) : /* not applicable for LAYOUT 'image' */ ?>

			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_URL' ), 'filebyurl' );*/ ?>
			<?php
				$tab_title = $use_jmedia_man ? 'FLEXI_ADD_LINK' : 'FLEXI_ADD_URL';
			?>
			<div class="tabbertab" id="fileurl_tab" data-icon2-class="icon-link fc-icon-gray" data-icon-class="fc-icon-green">
				<h3 class="tabberheading hasTooltip" data-placement="bottom" title="<?php echo JText::_($tab_title . '_DESC'); ?>"> <?php echo JText::_($tab_title); ?> </h3>

				<div class="fc_loading_msg alert alert-info" style="display:none;"> &nbsp; &nbsp; <?php echo JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'); ?></div>
				<form action="<?php echo $action_url . 'addurl'; ?>" class="form-validate form-horizontal" name="addUrlForm" id="addUrlForm" method="post" onsubmit="this.style.display='none'; jQuery(this).prev().show(); return true;">
					<fieldset class="fc-fileman-tab" >
						<fieldset class="fc-formbox" id="fc-fileman-formbox-3">

							<table class="fc-form-tbl fcinner" id="file-url-form-container">

								<?php if ($use_jmedia_man) : ?>
								<tr>
									<td id="file-link-type-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_URL_LINK', 'FLEXI_URL_LINK_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-data">
										<?php echo JText::_( 'Link type' ); ?>
										</label>
									</td>
									<td id="file-link-type-container">
										<?php
										//echo JHtml::_('select.booleanlist', 'keep', 'class="inputbox"', 1, JText::_( 'FLEXI_YES' ), JText::_( 'FLEXI_NO' ) );
										$_options = array();
										$_options['1'] = JText::_( 'FLEXI_URL_LINK' );
										$_options['2'] = JText::_( 'FLEXI_JMEDIA_LINK' );
										echo flexicontent_html::buildradiochecklist($_options, 'file-link-type', /*selected*/1, /*type*/1, /*attribs*/'onchange="return fman_toggle_link_type(this.value);"', /*tagid*/'file-link-type');
										?>
									</td>
								</tr>
								<?php endif; ?>

								<tr id="file-url-data-row">
									<td id="file-url-data-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_URL_LINK', 'FLEXI_URL_LINK_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-data">
										<b><?php echo JText::_( 'FLEXI_URL_LINK' ); ?></b>
										</label>
									</td>
									<td id="file-url-data-container">
										<input type="text" id="file-url-data" size="44" class="required input-xxlarge" name="file-url-data" style="padding-top: 3px; padding-bottom: 3px;" />
									</td>
								</tr>

								<?php if ($use_jmedia_man) : ?>
								<tr id="file-jmedia-data-row" style="display: none;">
									<td id="file-jmedia-data-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_URL', 'FLEXI_FILE_URL_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-jmedia-data">
										<b><?php echo JText::_( 'FLEXI_JMEDIA_LINK' ); ?></b>
										</label>
									</td>
									<td id="file-jmedia-data-container">
										<?php
										$jMedia_file_displayData = array(
											'disabled' => false,
											'preview' => 'tooltip',
											'readonly' => false,
											'class' => 'required',
											'link' => 'index.php?option=com_media&amp;view=images&amp;layout=default_fc&amp;tmpl=component&amp;filetypes=' . $filetypes . '&amp;asset=',  //com_flexicontent&amp;author=&amp;fieldid=\'+mm_id+\'&amp;folder='
											'asset' => 'com_flexicontent',
											'authorId' => '',
											'previewWidth' => 480,
											'previewHeight' => 360,
											'name' => 'file-jmedia-data',
											'id' => 'file-jmedia-data',
											'value' => '',
											'folder' => '',
										);
										echo JLayoutHelper::render($media_field_layout = 'joomla.form.field.media', $jMedia_file_displayData, $layouts_path = null);
										?>
									</td>
								</tr>
								<?php endif; ?>

								<tr>
									<td colspan="2">&nbsp;</td>
								</tr>

								<tr>
									<td id="file-url-title-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-title">
										<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
										</label>
									</td>
									<td id="file-url-title-container">
										<input type="text" id="file-url-title" size="44" class="required input-xxlarge" name="file-url-title" />
									</td>
								</tr>

								<tr>
									<td id="file-url-desc-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-desc">
										<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
										</label>
									</td>
									<td id="file-url-desc-container">
										<textarea name="file-url-desc" cols="24" rows="3" id="file-url-desc" class="input-xxlarge"></textarea>
									</td>
								</tr>

								<tr>
									<td id="file-url-lang-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-url-lang-lbl" for="file-url-lang">
										<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
										</label>
									</td>
									<td id="file-url-lang-container">
										<?php echo str_replace('file-lang', 'file-url-lang', $this->ffields['file-lang']); ?>
									</td>
								</tr>

								<tr>
									<td id="file-url-access-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="file-url-access-lbl" for="file-url-access">
										<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
										</label>
									</td>
									<td id="file-url-access-container">
										<?php echo str_replace('file-access', 'file-url-access', $this->ffields['file-access']); ?>
									</td>
								</tr>

								<tr>
									<td id="file-url-ext-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILEEXT_MIME', 'FLEXI_FILEEXT_MIME_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-ext">
										<?php echo JText::_( 'FLEXI_FILEEXT_MIME' ); ?>
										</label>
									</td>
									<td id="file-url-ext-container">
										<input type="text" id="file-url-ext" size="5" class="required input-xxlarge" name="file-url-ext" />
									</td>
								</tr>

								<tr>
									<td id="file-url-size-lbl-container" class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', '', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-url-size">
										<?php echo JText::_( 'FLEXI_SIZE' ); ?>
										</label>
									</td>
									<td id="file-url-size-container">
										<input type="text" id="file-url-size" size="44" class="input-xxlarge" name="file-url-size" />
										<select id="size_unit" name="size_unit" class="use_select2_lib">
											<option value="KBs" selected="selected">KBs</option>
											<option value="MBs">MBs</option>
											<option value="GBs">GBs</option>
										</select>
										<span class="hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', 'FLEXI_SIZE_IN_FORM', 1, 1); ?>"><i class="icon-info"></i></span>
									</td>
								</tr>

							</table>

							<input type="submit" id="file-url-submit" class="btn btn-success validate" value="<?php echo JText::_( 'FLEXI_ADD_FILE' ); ?>" style="margin: 16px 48px 0 48px;" />

							<?php echo JHtml::_('form.token'); ?>
							<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />

						</fieldset>
					</fieldset>

					<?php /* NOTE: return URL should use & and not &amp; for variable separation as these will be re-encoded on redirect */ ?>
					<input type="hidden" name="return" value="<?php echo base64_encode('index.php?option=com_flexicontent&view='.$this->view.($_tmpl ? '&tmpl='.$_tmpl : '').'&field='.$this->fieldid.'&layout='.$this->layout.'&'.JSession::getFormToken().'=1'); ?>" />
				</form>

			</div>

			<?php endif; /* End of TAB for File via URL form */ ?>


			<!-- File(s) from server Form -->
		<?php if (!$isFilesElement) : ?>

			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'filefromserver' );*/ ?>
			<div class="tabbertab" id="server_tab" data-icon2-class="icon-stack fc-icon-gray" data-icon-class="fc-icon-green">
				<h3 class="tabberheading hasTooltip" data-placement="bottom" title="<?php echo JText::_( 'FLEXI_BATCH_ADD_FILES_DESC' ); ?>"> <?php echo JText::_( 'FLEXI_BATCH_ADD_FILES' ); ?> </h3>

				<form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addlocal&amp;<?php echo JSession::getFormToken() . '=1'; ?>" class="form-validate form-horizontal" name="addFileForm" id="addFileForm" method="post">
					<fieldset class="fc-fileman-tab" >
						<fieldset class="fc-formbox" id="fc-fileman-formbox-4">

							<table class="fc-form-tbl fcinner" id="add-files-form-container">

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_SRC_DIR', 'FLEXI_CHOOSE_SRC_DIR_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-dir-path">
										<?php echo JText::_( 'FLEXI_SRC_DIR' ); ?>
										</label>
									</td>
									<td>
										<input type="text" id="file-dir-path" size="50" value="/tmp" class="required input-xxlarge" name="file-dir-path" />
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" id="_file-lang-lbl" for="_file-lang">
										<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
										</label>
									</td>
									<td>
										<?php echo
											str_replace('id="file-lang', 'id="_file-lang',
											str_replace('id="file-lang', 'id="_file-lang', $this->ffields['file-lang'])
											); ?>
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILTER_EXT', 'FLEXI_FILE_FILTER_EXT_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-filter-ext">
										<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>
										</label>
									</td>
									<td>
										<input type="text" id="file-filter-ext" size="50" value="" class="input-xxlarge" name="file-filter-ext" />
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-desc_addFileForm">
										<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
										</label>
									</td>
									<td>
										<textarea name="file-desc" cols="24" rows="6" id="file-desc_addFileForm" class="input-xxlarge"></textarea>
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILTER_REGEX', 'FLEXI_FILE_FILTER_REGEX_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl" for="file-filter-re">
										<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>
										</label>
									</td>
									<td>
										<input type="text" id="file-filter-re" size="50" value="" class="input-xxlarge" name="file-filter-re" />
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_KEEP_ORIGINAL_FILE', 'FLEXI_KEEP_ORIGINAL_FILE_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl">
										<?php echo JText::_( 'FLEXI_KEEP_ORIGINAL_FILE' ); ?>
										</label>
									</td>
									<td>
										<div class="group-fcset radio">
										<?php
										//echo JHtml::_('select.booleanlist', 'keep', 'class="inputbox"', 1, JText::_( 'FLEXI_YES' ), JText::_( 'FLEXI_NO' ) );
										$_options = array();
										$_options['0'] = JText::_( 'FLEXI_NO' );
										$_options['1'] = JText::_( 'FLEXI_YES' );
										echo flexicontent_html::buildradiochecklist($_options, 'keep', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'keep_addFileForm');
										?>
										</div>
									</td>
								</tr>

								<tr>
									<td class="key <?php echo $this->tooltip_class; ?>" data-placement="top" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
										<label class="fc-prop-lbl">
										<?php echo JText::_( 'FLEXI_TARGET_DIRECTORY' ); ?>
										</label>
									</td>
									<td>
										<div class="group-fcset radio">
										<?php
										//echo JHtml::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_addFileForm' );
										$_options = array();
										$_options['0'] = JText::_( 'FLEXI_MEDIA' );
										$_options['1'] = JText::_( 'FLEXI_SECURE' );
										echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_addFileForm');
										?>
										</div>
									</td>
								</tr>

							</table>

							<input type="submit" id="file-dir-submit" class="btn btn-success validate" value="<?php echo JText::_( 'FLEXI_ADD_DIR' ); ?>" style="margin: 16px 48px 0 16px;" />

						</fieldset>
					</fieldset>
					<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
					<input type="hidden" name="return" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
				</form>

			</div>


			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_FILEMAN_INFO' ), 'fileinfo' );*/ ?>
			<div class="tabbertab" id="fileman_info_tab" data-icon-class="icon-info fc-icon-gray">
				<h3 class="tabberheading hasTooltip" data-placement="bottom" title="<?php echo JText::_( 'FLEXI_FILEMAN_INFO_DESC' ); ?>"> <?php echo JText::_( 'FLEXI_FILEMAN_INFO' ); ?> </h3>
				<div id="why_box" class="info-box">
				<?php echo JText::_( 'FLEXI_FILES_INFO_ABOUT_FILES_IN_DB' ); ?>
				</div>
			</div>

		<?php endif; ?>

		<?php /* echo JHtml::_('tabs.end'); */ ?>
		</div><!-- .fctabber end -->
	</div><!-- .span6 end -->

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

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

<?php
JFactory::getDocument()->addScriptDeclaration('
	function fc_edit_mmdata_modal_load( container )
	{
		if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=mediadatas") != -1 )
		{
			container.dialog("close");
		}
	}
	function fc_edit_mmdata_modal_close()
	{
		window.location.reload(false);
		document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
	}
');