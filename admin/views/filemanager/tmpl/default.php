<?php
/**
 * @version 1.5 stable $Id: default.php 1929 2014-07-08 17:04:16Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;

$tip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$hint_image = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), '' );
$warn_image = JHTML::image ( 'components/com_flexicontent/assets/images/warning.png', JText::_( 'FLEXI_NOTES' ), '' );

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCfiles'.$this->layout.$this->fieldid, $start_text, $end_text);

$ctrl_task  = 'task=filemanager.';
$ctrl_task_authors = 'task=users.';
$action_url = JURI::base() . 'index.php?option=com_flexicontent&amp;' . JSession::getFormToken() . '=1&amp;' . $ctrl_task;
$action_url_js = str_replace('&amp;', '&', $action_url);

$session = JFactory::getSession();
$document = JFactory::getDocument();
$cparams = JComponentHelper::getComponent('com_flexicontent')->params;
$app  = JFactory::getApplication();
$jinput = $app->input;

$secure_folder_tip  = '<i data-placement="bottom" class="icon-info fc-man-icon-s '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';

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
$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

//$this->folder_mode = 1; // test
//$this->CanViewAllFiles = 0; // test

// Calculate count of shown columns 
$list_total_cols = 14;
if ($this->folder_mode) $list_total_cols -= 7;

// Optional columns
if (!$this->folder_mode && count($this->optional_cols) - count($this->cols) > 0) $list_total_cols -= (count($this->optional_cols) - count($this->cols));

// Currently multi-uploading is supported / finished only for LAYOUT 'image'
$_forced_secure_val = !strlen($this->target_dir) || $this->target_dir==2  ?  ''  :  ($this->target_dir==0 ? 'M' : 'S');
$_forced_secure_int = !strlen($this->target_dir) || $this->target_dir==2  ?  ''  :  ($this->target_dir==0 ? '0' : '1');
$_tmpl = $this->view == 'fileselement' ? 'component' : '';

$uconf = new JRegistry();
$uconf->merge( $this->params );
if (!empty($this->field))
{
	$uconf->merge( $this->field->parameters );
}

$is_inline_input = strlen($uconf->get('inputmode')) && $uconf->get('inputmode', 0) == 0;
$enable_multi_uploader = 1;
$nonimg_message = $this->layout == 'image' ? '' : '-1';

$js = "
jQuery(document).ready(function() {
	var use_mul_upload = ".($enable_multi_uploader ? 1 : 0).";
	var IEversion = isIE();
	var is_IE8_IE9 = IEversion && IEversion < 10;
	if (is_IE8_IE9) fctabber['fileman_tabset'].tabShow(1);
	
	//if (is_IE8_IE9) use_mul_upload = 0;
	if (use_mul_upload)
	{
		// Show uploader's outer container
		jQuery('#filemanager-2').show();
		jQuery('#multiple_uploader').css('min-height', 180);

		// Init uploader
		showUploader();

		// Also set filelist height
		fc_plupload_resize_now();
	}

	// Uploader does not initialize properly when hidden in IE8 / IE9 with 'runtime': 'html4' (it does if using 'runtime': 'flash')
	if (!is_IE8_IE9 && !fc_has_flash_addon()) fctabber['fileman_tabset'].tabShow(0);

	// Hide basic uploader form if using multi-uploader script
	if (use_mul_upload) jQuery('#filemanager-1').hide();
});


// delete active filter
function delFilter(name)
{
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);
	if (filter.length==0) return;
	if (filter.attr('type')=='checkbox')
		filter.checked = '';
	else
		filter.val('');
}

function delAllFilters()
{
	delFilter('search');
	delFilter('filter_uploader');
	delFilter('filter_lang');
	delFilter('filter_url');
	delFilter('filter_secure');
	delFilter('filter_ext');
	delFilter('item_id');
	delFilter('filter_order'); delFilter('filter_order_Dir');
}

function disabledEventPropagation(event)
{
	if (event.stopPropagation){
		event.stopPropagation();
	}
	else if(window.event){
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
	var IEversion = isIE();

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
	var el  = jQuery('input#cb' + id);
	var el2 = jQuery('span#_cb' + id);

	var c = el.prop('checked');
	if (!is_cb)
	{
		el.prop('checked', !c);
	}
	fman_toggle_thumb_selection(el2.closest('.fc-fileman-grid-thumb-box'), !c);
}


// Select ALL files in thumbnails view
function fman_set_cids(val)
{
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
		if (!is_array($d) && !is_object($d)) $data->$j = utf8_encode($d);
	}
	$js .= '  _file_data['.$i.'] = '.json_encode($data).";\n";
}
$document->addScriptDeclaration($js);



// *********************
// BOF multi-uploader JS
// *********************

if ($enable_multi_uploader)
{
	JText::script("FLEXI_FILE_PROPERTIES", true);
	JText::script("FLEXI_APPLYING_DOT", true);
	$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/plupload-extend.js', FLEXI_VHASH);
}

$has_field_upload_maxsize   = !empty($this->field) && strlen($this->field->parameters->get('upload_maxsize'));
$has_field_resize_on_upload = !empty($this->field) && strlen($this->field->parameters->get('resize_on_upload'));

// Try field with upload_maxsize and resize_on_upload parameters
$upload_maxsize   = (int) $uconf->get('upload_maxsize', 10000000);
$resize_on_upload = (int) $uconf->get('resize_on_upload', 1);

if ($resize_on_upload)
{
	$upload_max_w   = (int) $uconf->get('upload_max_w', 4000);
	$upload_max_h   = (int) $uconf->get('upload_max_h', 3000);
	$upload_quality = (int) $uconf->get('upload_quality', 95);
	$upload_method  = (int) $uconf->get('upload_method', 1);
}

$user = JFactory::getUser();	// get current user
$perms = FlexicontentHelperPerm::getPerm();  // get global perms


flexicontent_html::loadFramework('nouislider');

$slider_conf = new stdClass();
$slider_conf->slider_name = $name = "fc-fileman-list-thumb-size";
$slider_conf->element_selector = "div.fc-fileman-list-thumb-box";
$slider_conf->element_class_prefix = "thumb_";
$slider_conf->values = array(40, 60, 90, 120, 150);

$thumb_size['fm-list'] = $jinput->cookie->get($name . '-val', 40, 'int');
$slider_conf->initial_pos = (int) array_search($thumb_size['fm-list'], $slider_conf->values);
if ($slider_conf->initial_pos === false)
{
	$slider_conf->initial_pos = 0;
}
$thumb_size['fm-list'] = $slider_conf->values[$slider_conf->initial_pos];
$jinput->cookie->set($name . '-val', $thumb_size['fm-list']);

$slider_conf->labels = array();
foreach($slider_conf->values as $value) $slider_conf->labels[] = $value .'x'. $value;
//include(dirname(__FILE__). '/../../filemanager/tmpl/layouts/single_slider.php');

$cfg = $slider_conf;
$element_classes = array();
foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

$element_class_list = implode(' ', $element_classes);
$step_values = '[' . implode(', ', $cfg->values) . ']';
$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

$js = "
jQuery(document).ready(function()
{
	fc_attachSingleSlider({
		'name': '".$name."',
		'step_values': ".$step_values.",
		'step_labels': ".$step_labels.",
		'initial_pos': ".$cfg->initial_pos.",
		'element_selector': '".$cfg->element_selector."',
		'element_class_list': '".$element_class_list."',
		'element_class_prefix': '".$cfg->element_class_prefix."'
	});
});
";
JFactory::getDocument()->addScriptDeclaration($js);


$slider_conf = new stdClass();
$slider_conf->slider_name = $name = "fc-fileman-grid-thumb-size";
$slider_conf->element_selector = "div.fc-fileman-grid-thumb-box";
$slider_conf->element_class_prefix = "thumb_";
$slider_conf->values = array(90, 120, 150, 200, 250);
$slider_conf->labels = array();

$thumb_size['fm-grid'] = $jinput->cookie->get($name . '-val', 150, 'int');
$slider_conf->initial_pos = (int) array_search($thumb_size['fm-grid'], $slider_conf->values);
if ($slider_conf->initial_pos === false)
{
	$slider_conf->initial_pos = 0;
}
$thumb_size['fm-grid'] = $slider_conf->values[$slider_conf->initial_pos];
$jinput->cookie->set($name . '-val', $thumb_size['fm-grid']);

foreach($slider_conf->values as $value) $slider_conf->labels[] = $value .'x'. $value;
//include(dirname(__FILE__). '/../../filemanager/tmpl/layouts/single_slider.php');

$cfg = $slider_conf;
$element_classes = array();
foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

$element_class_list = implode(' ', $element_classes);
$step_values = '[' . implode(', ', $cfg->values) . ']';
$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

$js = "
jQuery(document).ready(function()
{
	fc_attachSingleSlider({
		'name': '".$name."',
		'step_values': ".$step_values.",
		'step_labels': ".$step_labels.",
		'initial_pos': ".$cfg->initial_pos.",
		'element_selector': '".$cfg->element_selector."',
		'element_class_list': '".$element_class_list."',
		'element_class_prefix': '".$cfg->element_class_prefix."'
	});
});
";
JFactory::getDocument()->addScriptDeclaration($js);


$slider_conf = new stdClass();
$slider_conf->slider_name = $name = "fc-uploader-grid-thumb-size";
$slider_conf->element_selector = "#multiple_uploader li.plupload_delete";
$slider_conf->element_class_prefix = "thumb_";
$slider_conf->values = array(90, 120, 150, 200, 250);
$slider_conf->labels = array();

$thumb_size['up-grid'] = $jinput->cookie->get($name . '-val', 150, 'int');
$slider_conf->initial_pos = (int) array_search($thumb_size['up-grid'], $slider_conf->values);
if ($slider_conf->initial_pos === false)
{
	$slider_conf->initial_pos = 1;
}
$thumb_size['up-grid'] = $slider_conf->values[$slider_conf->initial_pos];
$jinput->cookie->set($name . '-val', $thumb_size['up-grid']);

foreach($slider_conf->values as $value) $slider_conf->labels[] = $value .'x'. $value;

$cfg = $slider_conf;
$element_classes = array();
foreach($cfg->values as $value) $element_classes[] = $cfg->element_class_prefix . $value;

$element_class_list = implode(' ', $element_classes);
$step_values = '[' . implode(', ', $cfg->values) . ']';
$step_labels = '["' . implode('", "', $cfg->labels) . '"]';

$js = "
var fc_uploader_slider_cfg = {
	'name': '".$name."',
	'step_values': ".$step_values.",
	'step_labels': ".$step_labels.",
	'initial_pos': ".$cfg->initial_pos.",
	'element_selector': '".$cfg->element_selector."',
	'element_class_list': '".$element_class_list."',
	'element_class_prefix': '".$cfg->element_class_prefix."'
}
";
JFactory::getDocument()->addScriptDeclaration($js);


if ($enable_multi_uploader)
{
	// Load plupload JS framework
	$pluploadlib = JURI::root(true).'/components/com_flexicontent/librairies/plupload/';
	$plupload_mode = 'runtime';  // 'runtime,ui'
	flexicontent_html::loadFramework('plupload', $plupload_mode);
	flexicontent_html::loadFramework('flexi-lib');
	
	// Add plupload Queue handling functions and initialize a plupload Queue
	$js = '
	var fc_file_mul_uploader = null;
	
	// Auto-resize the currently open dialog vertically or horizontally
	function fc_plupload_resize_now()
	{
		var window_h = jQuery( window ).height();
		var window_w = jQuery( window ).width();
		
		// Also set filelist height
		var max_filelist_h = 568;
		var plupload_filelist_h = max_filelist_h > (window_h - 460) ? (window_h - 460) : max_filelist_h;
		jQuery(".plupload_filelist:not(.plupload_filelist_header):not(.plupload_filelist_footer)").css({ "height": plupload_filelist_h+"px" });
	}


	var fc_plupload_resize = fc_debounce_exec(fc_plupload_resize_now, 200, false);
	jQuery(window).resize(function()
	{
		fc_plupload_resize();
	});


	// Load pluploader if not already loaded
	function showUploader()
	{
		var IEversion = isIE();
		var is_IE8_IE9 = IEversion && IEversion < 10;

		var runtimes = !is_IE8_IE9  ?  "html5,flash,silverlight,html4"  : "flash,html4";  //,silverlight,html5
		if (!fc_file_mul_uploader && is_IE8_IE9)
		{
			if (!fc_has_flash_addon()) jQuery("<div class=\"alert alert-warning fc-iblock\">You have Internet explorer 8 / 9. Please install and activate (allow) FLASH add-on, for image preview to work</div>").insertBefore("#multiple_uploader");
		}

		// Already initialized
		//window.console.log("showUploader ...");
		if (fc_file_mul_uploader)
		{
			//window.console.log("exists");
			//fc_file_mul_uploader.refresh();  // refresh it
			//fc_file_mul_uploader.splice();   // empty it, ... not needed and problematic ... commented out
		}
		
		else if ("'.$plupload_mode.'"=="ui")
		{
	    fc_file_mul_uploader = jQuery("#multiple_uploader").plupload({
				// General settings
				runtimes : runtimes,
				url : "'.$action_url_js.'uploads'.(strlen($_forced_secure_int) ? '&secure='.$_forced_secure_int : '').'&fieldid='.$this->fieldid.'&u_item_id='.$this->u_item_id.'&folder_mode='.$this->folder_mode.'",
				prevent_duplicates : true,

				// Set maximum file size and chunking to 1 MB
				max_file_size : "'.$upload_maxsize.'",
				chunk_size: "1mb",

				// Resize images on clientside if we can
				'.($resize_on_upload ? '
				resize : {
					width : '.$upload_max_w.',
					height : '.$upload_max_h.',
					quality : '.$upload_quality.',
					crop: '.($upload_method ? 'true' : 'false').'},
				' : '').'

				// Specify what files to browse for
				// Also it is possible to prevent picking file over the upload limit, but since we have resize do not use it
				filters : {
					//max_file_size : "'.$upload_maxsize.'",
					'.($this->layout == 'image' ? '
					mime_types: [
						{title : "Image files", extensions : "jpg,jpeg,gif,png"},
						{title : "Zip files", extensions : "zip,avi"}
					]
					' : '').'
				},

				// Rename files by clicking on their titles
				rename: true,

				// Enable ability to drag n drop files onto the widget (currently only HTML5 supports that)
				dragdrop: true,

				// Sort files
				sortable: true,

				// Views to activate
				views: {
					list: true,
					thumbs: true, // Show thumbs
					active: "list"
				},

				// Flash settings
				flash_swf_url : "'.$pluploadlib.'/js/Moxie.swf",

				// Silverlight settings
				silverlight_xap_url : "'.$pluploadlib.'/js/Moxie.xap",

				init: {
					BeforeUpload: function (up, file) {
						// Called right before the upload for a given file starts, can be used to cancel it if required
						up.settings.multipart_params = {
							filename: file.name,
							file_row_id: file.id
						};
					},

					PostInit: fc_plupload_handle_init,
					FilesAdded: fc_plupload_handle_filesChanged,
					FilesRemoved: fc_plupload_handle_filesChanged,

					UploadComplete: function (up, files)
					{
						if(window.console) window.console.log("All Files Uploaded");
						window.document.body.innerHTML = "<span class=\"fc_loading_msg\">Reloading ... please wait</span>";
						window.location.reload(true);  //window.location.replace(window.location.href);
					}
				}
	    });

			// Binding event handlers is also possible after initialization
			//fc_file_mul_uploader.bind(\'PostInit\', fc_plupload_handle_init);
			//fc_file_mul_uploader.bind(\'FilesAdded\', fc_plupload_handle_filesChanged);
			//fc_file_mul_uploader.bind(\'FilesRemoved\', fc_plupload_handle_filesChanged);
		}

		else
		{
			//window.console.log("creating");
			jQuery("#multiple_uploader").pluploadQueue({
				// General settings
				runtimes : runtimes,
				url : "'.$action_url_js.'uploads'.(strlen($_forced_secure_int) ? '&secure='.$_forced_secure_int : '').'&fieldid='.$this->fieldid.'&u_item_id='.$this->u_item_id.'&folder_mode='.$this->folder_mode.'",
				prevent_duplicates : true,

				// Set maximum file size and chunking to 1 MB
				max_file_size : "'.$upload_maxsize.'",
				chunk_size: "1mb",

				// Resize images on clientside if we can
				'.($resize_on_upload ? '
				resize : {
					width : '.$upload_max_w.',
					height : '.$upload_max_h.',
					quality : '.$upload_quality.',
					crop: '.($upload_method ? 'true' : 'false').'},
				' : '').'

				// Specify what files to browse for
				filters : [
					'.($this->layout == 'image' ? '
					{title : "Image files", extensions : "jpg,jpeg,gif,png"},
					{title : "Zip files", extensions : "zip,avi"}
					' : '').'
				],

				// Rename files by clicking on their titles
				rename: true,

				// Enable ability to drag n drop files onto the widget (currently only HTML5 supports that)
				dragdrop: true,
				
				// "sortable", and "views" are not natively supported by "*Queue" , but we will add them and also enhance them ...

				// Flash settings
				flash_swf_url : "'.$pluploadlib.'/js/Moxie.swf",

				// Silverlight settings
				silverlight_xap_url : "'.$pluploadlib.'/js/Moxie.xap",

				init: {
					BeforeUpload: function (up, file)
					{
						// Called right before the upload for a given file starts, can be used to cancel it if required
						up.settings.multipart_params = {
							filename: file.name,
							file_row_id: file.id
						};
					},

					PostInit: fc_plupload_handle_init,
					FilesAdded: fc_plupload_handle_filesChanged,
					FilesRemoved: fc_plupload_handle_filesChanged,

					UploadComplete: function (up, files)
					{
						if(window.console) window.console.log("All Files Uploaded");
						window.document.body.innerHTML = "<span class=\"fc_loading_msg\">Reloading ... please wait</span>";
						window.location.reload(true);  //window.location.replace(window.location.href);
					}
				}
			});
			
			// Need to make 2nd call to get the created uploader instance
			fc_file_mul_uploader = jQuery("#multiple_uploader").pluploadQueue();
			
			// It is also possible to bind events also after initialization
			//fc_file_mul_uploader.bind(\'PostInit\', fc_plupload_handle_init);
			//fc_file_mul_uploader.bind(\'FilesAdded\', fc_plupload_handle_filesChanged);
			//fc_file_mul_uploader.bind(\'FilesRemoved\', fc_plupload_handle_filesChanged);
		}
	};
	';
	
	$document->addScriptDeclaration($js);
}

// *********************
// EOF multi-uploader JS
// *********************


flexicontent_html::loadFramework('flexi-lib');

/*<div id="themeswitcher" class="pull-right"> </div>
<script>
	jQuery(function() {
		jQuery.fn.themeswitcher && jQuery('#themeswitcher').themeswitcher({cookieName:''});
	});
</script>*/

$tools_cookies['fc-filters-box-disp'] = $jinput->cookie->get('fc-filters-box-disp', 0, 'int');
?>

<div id="fc-fileman-overlay" onclick="jQuery('img.fc_zoomed, li.fc_zoomed .plupload_img_preview img').trigger('click');"></div>

<div id="flexicontent" class="flexicontent">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>


<?php /* echo JHtml::_('tabs.start'); */ ?>
<div class="fctabber" id="fileman_tabset">
	
	<!-- File listing -->
	
	<?php /* echo JHtml::_('tabs.panel', JText::_( 'FLEXI_FILEMAN_LIST' ), 'filelist' ); */ ?>
	<div class="tabbertab" id="filelist_tab" data-icon-class="icon-list">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FILEMAN_LIST' ); ?> </h3>
		
		<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?><?php echo $_forced_secure_val ? '&amp;filter_secure='.$_forced_secure_val : ''; ?>&amp;layout=<?php echo $this->layout; ?>&amp;field=<?php echo $this->fieldid?>" method="post" name="adminForm" id="adminForm">
		
		<?php if (!$this->folder_mode) : ?>
		
			<div id="fc-filters-header">
				<span class="fc-filter nowrap_box" style="margin: 1px;">
					<?php echo $this->lists['scope']; ?>
				</span>
				<span class="btn-group input-append fc-filter filter-search">
					<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
					<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
					<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
				</span>
				
				<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
				<span class="btn-group input-append fc-filter">
					<span id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary', false, undefined, 1);"><?php echo JText::_( 'FLEXI_FILTERS' ) . ($this->count_filters  ? ' <sup>'.$this->count_filters.'</sup>' : ''); ?></span>
					<span id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');"><?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup></span>
				</span>
				<input type="hidden" id="fc-filters-box-disp" name="fc-filters-box-disp" value="<?php echo $tools_cookies['fc-filters-box-disp']; ?>" />
				
				<span class="fc-filter nowrap_box">
					<span class="limit nowrap_box">
						<?php
						$pagination_footer = $this->pagination->getListFooter();
						if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
						?>
					</span>
					
					<span class="fc_item_total_data nowrap_box fc-mssg-inline fc-info fc-nobgimage">
						<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
					</span>
					
					<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
					<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
						<?php echo $getPagesCounter; ?>
					</span>
					<?php endif; ?>
				</span>

				<?php if ( $this->fieldid && isset($this->lists['uploader']) ) : /* WHEN using field id (fileselement view) place filter outside the filter box */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['uploader']; ?>
					</div>
				</div>
				<?php endif; ?>
			
			</div>
			
			
			<div id="fc-filters-box" <?php if (!$this->count_filters || !$tools_cookies['fc-filters-box-disp']) echo 'style="display:none;"'; ?> class="">
				<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->

				<?php if (!empty($this->cols['lang'])) :  /* if layout==image then this was force to unset */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['language']; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ($this->layout != 'image') : /* if layout == image then this URL filter is not applicable */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['url']; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if (!empty($this->cols['target']) && $_forced_secure_val=='') :  /* if layout==image then this was force to unset */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['secure']; ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['ext']; ?>
					</div>
				</div>

				<?php if ( !$this->fieldid && isset($this->lists['uploader']) ) : ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['uploader'].' &nbsp; &nbsp; &nbsp;'; ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<div class="add-on">Item ID</div>
						<?php echo $this->lists['item_id']; ?>
					</div>
				</div>

				<div id="fc-filters-slide-btn" class="icon-arrow-up-2 btn" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
			</div>
			
		<?php else: ?>
		
				<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
				<div class="btn-group" style="margin: 2px 32px 6px 24px; display:inline-block;">
					<span id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');"><?php echo JText::_( 'FLEXI_COLUMNS' ); ?><sup id="columnchoose_totals"></sup></span>
					<!--input type="button" id="fc_upload_box_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('fileman_tabset', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_UPLOAD' ); ?>" /-->
				</div>
		
		<?php endif; ?>
		
			<div class="fcclear"></div>
			<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
			<div class="fcclear"></div>

			<span class="btn btn-info btn-small" style="height: 24px; line-height: 24px; padding-left: 6px; padding-right: 0;">
				<input type="checkbox" name="toggle" value="" id="checkall_btn" onclick="Joomla.checkAll(this); fman_set_cids(jQuery(this).prop('checked'));" />
				<label for="checkall_btn" class="green" style="margin: 0 !important; padding-right: 12px !important; color: white">Select all</label>
			</span>

			<div class="btn-group" style="margin: 0 12px;">
				<button type="button" class="btn list-view hasTooltip active" id="btn-fman-list-view" onclick="fc_toggle_view_mode(jQuery(this));" data-toggle_selector=".fman_list_element" style="min-width: 60px;"><i class="icon-list-view"></i> <?php echo JText::_('FLEXI_FILEMAN_DETAILS'); ?></button>
				<button type="button" class="btn grid-view hasTooltip" id="btn-fman-grid-view" onclick="fc_toggle_view_mode(jQuery(this));" data-toggle_selector=".fman_grid_element" style="min-width: 60px;"><i class="icon-grid-view"></i> <?php echo JText::_('FLEXI_FILEMAN_GRID'); ?></button>
			</div>

			<select id="fc-fileman-grid-thumb-size-sel" name="fc-fileman-grid-thumb-size-sel" type="text" style="display: none;"></select>
			<div id="fc-fileman-list-thumb-size_nouislider" class="fman_list_element" style="display: none;"></div>
			<div class="fc_slider_input_box">
				<input id="fc-fileman-list-thumb-size-val" name="fc-fileman-list-thumb-size-val" type="text" size="12" value="140" />
			</div>

			<select id="fc-fileman-grid-thumb-size-sel" name="fc-fileman-grid-thumb-size-sel" type="text" style="display: none;"></select>
			<div id="fc-fileman-grid-thumb-size_nouislider" class="fman_grid_element" style="visibility: hidden; display: none;"></div>
			<div class="fc_slider_input_box">
				<input id="fc-fileman-grid-thumb-size-val" name="fc-fileman-grid-thumb-size-val" type="text" size="12" value="140" />
			</div>

			<table id="adminListTableFCfiles<?php echo $this->layout.$this->fieldid; ?>" class="adminlist fcmanlist fman_list_element">
			<thead>
    		<tr class="header">
					<th class="center hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>

					<th>&nbsp;</th>

					<th class="center hideOnDemandClass"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
					<th class="left">
						<?php if (!$this->folder_mode) : ?>
							<?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename_displayed', $this->lists['order_Dir'], $this->lists['order'] ); ?>
							&nbsp; -- &nbsp;
							<?php echo JHTML::_('grid.sort', 'FLEXI_FILE_DISPLAY_TITLE', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?>
						<?php else: ?>
							<?php echo JText::_('FLEXI_FILENAME'); ?>
						<?php endif; ?>
					</th>
					
			<?php if (!$this->folder_mode) : ?>
				<?php if (!empty($this->cols['state'])) : ?>
					<th class="center hideOnDemandClass"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
				<?php endif; ?>
				<?php if (!empty($this->cols['access'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JText::_( 'FLEXI_ACCESS' ); ?></th>
				<?php endif; ?>
				<?php if (!empty($this->cols['lang'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JText::_( 'FLEXI_LANGUAGE' ); ?></th>
				<?php endif; ?>
			<?php endif; ?>
				
				<?php if ($this->folder_mode) : ?>
					<th class="center hideOnDemandClass"><?php echo JText::_( 'FLEXI_SIZE' ); ?></th>
				<?php else : ?>
					<th class="center hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_SIZE', 'f.size', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>
					
			<?php if (!$this->folder_mode) : ?>
				<?php if (!empty($this->cols['hits'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'f.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>
					
				<?php if (!empty($this->cols['target'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo $secure_folder_tip; ?><?php echo JHTML::_('grid.sort', 'FLEXI_URL_SECURE', 'f.secure', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>
						
				<?php if (!empty($this->cols['usage'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JText::_( 'FLEXI_FILE_ITEM_ASSIGNMENTS' ); ?> </th>
				<?php endif; ?>
			<?php endif; ?>
					
				<?php if (!$this->folder_mode && $this->CanViewAllFiles && !empty($this->cols['uploader'])) : ?>
					<th class="center hideOnDemandClass hidden-phone"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOADER', 'uploader', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>
				
				<?php if (!empty($this->cols['upload_time'])) : ?>
					<th class="center hideOnDemandClass hidden-tablet hidden-phone"><?php echo JHTML::_('grid.sort', 'FLEXI_UPLOAD_TIME', 'f.uploaded', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>
				
				<?php if (!$this->folder_mode && !empty($this->cols['file_id'])) : ?>
					<th class="center hideOnDemandClass hidden-tablet hidden-phone"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'f.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif; ?>

				<?php if ($this->view == 'fileselement') : /* Direct delete button for fileselement view */ ?>
					<th>&nbsp;</th>
				<?php endif; ?>

				</tr>
			</thead>
		
		<?php if (!$this->folder_mode) : ?>
			<tfoot>

				<?php
				$field_legend = array();
				$this->assigned_fields_labels;
				foreach($this->assigned_fields_labels as $field_type => $field_label) {
					$icon_name = $this->assigned_fields_icons[$field_type];
					$tip = $field_label;
					$image = JHTML::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip);
					$field_legend[$field_type] = $image. " ".$field_label;
				}
				?>

				<tr>
					<td colspan="<?php echo $list_total_cols; ?>" style="text-align: center; border-top:0px solid black;">
						<span class="label fc_legend_box <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND', 'FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND_TIP', 1, 1); ?> " ><?php echo JText::_('FLEXI_FILE_ITEM_ASSIGNMENTS_LEGEND'); ?></span> &nbsp; 
						<?php echo implode(' &nbsp; &nbsp; | &nbsp; &nbsp; ', $field_legend); ?>
					</td>
				</tr>

			</tfoot>
		<?php endif; ?>
		
			<tbody>
				<?php
				$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
				$thumbs_icons_arr = array();
				$filenames_cut = array();
				$items_link_arr = array();

				$imageexts = array('jpg','gif','png','bmp','jpeg');
				$index = JRequest::getInt('index', 0);
				$k = 0;
				$i = 0;
				$n = count($this->rows);
				foreach ($this->rows as $row)
				{
					unset($thumb_or_icon);
					$filename = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
					$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
					$filename_original = $filename_original ? $filename_original : $filename;
					
					$fileid = $this->folder_mode ? '' : $row->id;
					
					$ext = strtolower($row->ext);
					
					// Check if file is NOT an known / allowed image, and skip it if LAYOUT is 'image' otherwise display a 'type' icon
					$is_img = in_array($ext, $imageexts);
					if (!$is_img)
					{
						if ( $this->layout == 'image' )
							continue;
						else
							$thumb_or_icon = JHTML::image($row->icon, $row->filename);
					}
					
					if ($this->folder_mode) {
						$file_path = $this->img_folder . DS . $row->filename;
					} else if (!$row->url && substr($row->filename, 0, 7)!='http://') {
						$path = !empty($row->secure) ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
						$file_path = $path . DS . $row->filename;
					} else {
						$file_path = $row->filename;
						$thumb_or_icon = 'URL';
					}
					$file_path = JPath::clean($file_path);
					
					$file_url = rawurlencode(str_replace('\\', '/', $file_path));
					$_f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					if ( empty($thumb_or_icon) )
					{
						if (file_exists($file_path)){
							$thumb_or_icon = '<img class="fc-fileman-thumb" onclick="if (jQuery(this).hasClass(\'fc_zoomed\')) { fman_zoom_thumb(event, this); return false; }" src="'.JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_url.$_f. '&amp;w=800&amp;h=800&amp;zc=1&amp;q=95&amp;f=jpeg&amp;ar=x" alt="'.$filename_original.'" />';
						} else {
							$thumb_or_icon = '<span class="badge badge-box badge-important">'.JText::_('FLEXI_FILE_NOT_FOUND').'</span>';
						}
					}
					$thumbs_icons_arr[] = $thumb_or_icon;
					
					if (!$this->folder_mode)
					{
						$row->count_assigned = 0;
						foreach($this->assigned_fields_labels as $field_type => $ignore) {
							$row->count_assigned += $row->{'assigned_'.$field_type};
						}
						if ($row->count_assigned)
						{
							$row->assigned = array();
							foreach($this->assigned_fields_labels as $field_type => $field_label) {
								if ( $row->{'assigned_'.$field_type} )
								{
									$icon_name = $this->assigned_fields_icons[$field_type];
									$tip = $row->{'assigned_'.$field_type} . ' ' . $field_label;
									$image = JHTML::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip, 'title="'.$usage_in_str.' '.$field_type.' '.$fields_str.'"' );
									$row->assigned[] = $row->{'assigned_'.$field_type} . ' ' . $image;
								}
							}
							$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
						} else {
							$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
						}
					}

					// Link to items using the field
					$items_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_catsinstate=99&amp;filter_fileid='. $row->id.'&amp;fcform=1&amp;filter_state=ALL';
					$items_link_arr[$i] = $items_link;
		   		?>
				<tr class="<?php echo "row$k"; ?>">
					<td class="center hidden-phone">
						<div class="adminlist-table-row"></div>
						<?php echo $this->pagination->getRowOffset( $i ); ?>
					</td>
					
					<td class="center">
						<?php echo JHtml::_('grid.id', $i, $row->id); ?>
						<label for="cb<?php echo $i; ?>" class="green single" onclick="fman_sync_cid(<?php echo $i; ?>, 1);"></label>
					</td>

					<td class="center">
						<div class="fc-fileman-list-thumb-box thumb_<?php echo $thumb_size['fm-list'] ; ?>" onclick="fman_sync_cid(<?php echo $i; ?>, 0);">
							<?php echo $thumb_or_icon; ?>
						</div>
					</td>
					
					<td class="left">
						<?php
						// Display an icon with checkin link, if current user has checked out current item
						if ($row->checked_out) {
							// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
							$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
							if ($canCheckin)
							{
								//if ($row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'types.', $canCheckin);
								$task_str = 'types.checkin';
								if ($row->checked_out == $user->id) {
									$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->checked_out, $row->checked_out_time);
								} else {
									echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">';
									$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->checked_out, $row->checked_out_time);
								}
								?>
								<a class="btn btn-micro <?php echo $tip_class; ?>" title="<?php echo $_tip_title; ?>" href="javascript:;" onclick="var ccb=document.getElementById('cb<?php echo $i;?>'); ccb.checked=1; ccb.form.task.value='<?php echo $task_str; ?>'; ccb.form.submit();">
									<span class="icon-checkedout"></span>
								</a>
								<?php
							}
							else
							{
								echo '<span class="fc-noauth">'.JText::sprintf('FLEXI_RECORD_CHECKED_OUT_DIFF_USER').'</span><br/>';
							}
						}
						?>
						<?php
							if (StringHelper::strlen($row->filename_displayed) > 100) {
								$filename_cut = htmlspecialchars(StringHelper::substr($row->filename_displayed, 100), ENT_QUOTES, 'UTF-8') . '...';
							} else {
								$filename_cut = htmlspecialchars($row->filename_displayed, ENT_QUOTES, 'UTF-8');
							}
							$filenames_cut[$i] = $filename_cut;
						?>
						<?php echo '
						<a class="fc_set_file_assignment '.$btn_class.' '.$tip_class.' btn-small isedit_link" href="index.php?option=com_flexicontent&amp;'.$ctrl_task.'edit&amp;cid='.$row->id.'" title="'.$edit_entry.'">
							<span class="icon-pencil"></span>'.$filename_cut.'
						</a>
						'; ?>
						
						<?php
						if (!$this->folder_mode && $row->altname != $row->filename_displayed)
						{
							echo StringHelper::strlen($row->altname) > 100 ?
								'<br/><small>'.StringHelper::substr( htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8'), 0 , 100).'... </small>' :
								'<br/><small>'.htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8').'</small>' ;
						}
						?>
					</td>
					
			<?php if (!$this->folder_mode) : ?>
				
				<?php if (!empty($this->cols['state'])) : ?>
					<td class="center">
						<?php echo JHTML::_('jgrid.published', $row->published, $i, 'filemanager.' ); ?>
					</td>
				<?php endif; ?>
					
				<?php if (!empty($this->cols['access'])) : ?>
					<td class="center hidden-phone">
					<?php
					$is_authorised = $this->CanFiles && ($this->CanViewAllFiles || $user->id == $row->uploaded_by);
					if ($is_authorised) {
						$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'filemanager.access\')"');
					} else {
						$access = strlen($row->access_level) ? $this->escape($row->access_level) : '-';
					}
					echo $access;
					?>
					</td>
				<?php endif; ?>
					
				<?php $row->language = empty($row->language) ? '' : $row->language; /* Set language ALL when language is empty */ ?>
					
				<?php if (!empty($this->cols['lang'])) : ?>
					<td class="center col_lang hidden-phone">
						<?php if ( 0 && !empty($row->language) && !empty($this->langs->{$row->language}->imgsrc) ) : ?>
							<img title="<?php echo $row->language=='*' ? JText::_("FLEXI_ALL") : $this->langs->{$row->language}->name; ?>" src="<?php echo $this->langs->{$row->language}->imgsrc; ?>" alt="<?php echo $row->language; ?>" />
						<?php elseif( !empty($row->language) ) : ?>
							<?php echo $row->language=='*' ? JText::_("FLEXI_ALL") : $this->langs->{$row->language}->name;?>
						<?php endif; ?>
					</td>
				<?php endif; ?>
				
			<?php endif; ?>
					
					<td class="center"><?php echo $row->size; ?></td>
					
			<?php if (!$this->folder_mode) : ?>
				
				<?php if (!empty($this->cols['upload_time'])) : ?>
					<td class="center hidden-phone"><span class="badge"><?php echo empty($row->hits) ? 0 : $row->hits; ?></span></td>
				<?php endif; ?>
				
				<?php if (!empty($this->cols['target'])) : ?>
					<td class="center hidden-phone"><span class="badge badge-info"><?php echo JText::_( $row->secure ? 'FLEXI_YES' : 'FLEXI_NO' ); ?></span></td>
				<?php endif; ?>
				
				<?php if (!empty($this->cols['usage'])) : ?>
					<td class="center hidden-phone">
						<span class="nowrap_box"><?php echo $row->assigned; ?></span>
						<?php if ($row->count_assigned) : ?>
							<br/><br/>
							<span class="badge badge-info"><?php echo count($row->itemids); ?></span>
							<a href="<?php echo $items_link; ?>">
							[<?php echo $view_entry;?>]
							</a>
						<?php endif; ?>
					</td>
				<?php endif; ?>
				
				<?php if ($this->CanViewAllFiles && !empty($this->cols['uploader'])) : ?>
					<td class="center hidden-phone">
					<?php if ($this->view == 'filemanager' && FlexicontentHelperPerm::getPerm()->CanAuthors) :?>
						<a target="_blank" href="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task_authors; ?>edit&amp;hidemainmenu=1&amp;cid=<?php echo $row->uploaded_by; ?>">
						<?php echo $row->uploader; ?>
						</a>
					<?php else :?>
						<?php echo $row->uploader; ?>
					<?php endif; ?>
					</td>
				<?php endif; ?>
				
			<?php endif; ?>
			
			
				<?php if (!empty($this->cols['upload_time'])) : ?>
					<td class="center hidden-tablet hidden-phone">
						<?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC3' )." H:i" ); ?>
					</td>
				<?php endif; ?>
			
			
			<?php if (!$this->folder_mode) : ?>
			
				<?php if (!empty($this->cols['file_id'])) : ?>
					<td class="center hidden-tablet hidden-phone"><?php echo $row->id; ?></td>
				<?php endif; ?>
				
			<?php endif; ?>
				
					<?php if ($this->view == 'fileselement') : /* Direct delete button for fileselement view */ ?>
					<td>
						<a class="btn btn-mini" href="javascript:;" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE', true); ?>')) { document.adminForm.filename.value='<?php echo rawurlencode($row->filename);?>'; return listItemTask('cb<?php echo $i; ?>','filemanager.remove'); }" style="padding: 4px;">
							<span class="icon-remove" title="<?php echo JText::_('FLEXI_REMOVE'); ?>"></span>
						</a>
					</td>
					<?php endif; ?>

				</tr>
				<?php 
					$k = 1 - $k;
					$i++;
				} 
				?>
			</tbody>
			
			</table>

			<div id="adminListThumbsFCfiles<?php echo $this->layout.$this->fieldid; ?>" class="adminthumbs fcmanthumbs fman_grid_element" style="display: none;">
				<?php
				$imageexts = array('jpg','gif','png','bmp','jpeg');
				$index = JRequest::getInt('index', 0);
				$k = 0;
				$i = 0;
				$n = count($this->rows);
				foreach ($this->rows as $row)
				{
					$checked 	= @ JHTML::_('grid.checkedout', $row, $i );

					$filename = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
					$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
					$filename_original = $filename_original ? $filename_original : $filename;
					
					$fileid = $this->folder_mode ? '' : $row->id;
					
					$ext = strtolower($row->ext);
					
					// Check if file is NOT an known / allowed image, and skip it if LAYOUT is 'image' otherwise display a 'type' icon
					$is_img = in_array($ext, $imageexts);
					if (!$is_img)
					{
						if ( $this->layout == 'image' )  continue;
					}
					$thumb_or_icon = $thumbs_icons_arr[$i];

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
									$image = JHTML::image('administrator/components/com_flexicontent/assets/images/'.$icon_name.'.png', $tip, 'title="'.$usage_in_str.' '.$field_type.' '.$fields_str.'"' );
									$row->assigned[] = $row->{'assigned_'.$field_type} . ' ' . $image;
								}
							}
							$row->assigned = implode('&nbsp;&nbsp;| ', $row->assigned);
						}
						else {
							$row->assigned = JText::_( 'FLEXI_NOT_ASSIGNED' );
						}
					}
					$items_link = $items_link_arr[$i];
		   		?>

				<div class="fc-fileman-grid-thumb-box thumb_<?php echo $thumb_size['fm-grid'] ; ?>" onclick="fman_sync_cid(<?php echo $i; ?>, 0);">
					<?php
					echo $thumb_or_icon;
					echo !$is_img ? '' : '
					<span class="btn fc-fileman-preview-btn icon-search" onclick="fman_zoom_thumb(event, this); return false;"></span>
					'; ?>
					<span class="btn fc-fileman-selection-mark icon-checkmark" id="_cb<?php echo $i; ?>" ></span>
					<span class="btn fc-fileman-delete-btn icon-remove" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE', true); ?>')) { document.adminForm.filename.value='<?php echo rawurlencode($row->filename);?>'; return listItemTask('cb<?php echo $i; ?>','filemanager.remove'); }"></span>
					<span class="fc-fileman-filename-box"><?php echo $filenames_cut[$i]; ?></span>
				</div>
				<?php 
					$k = 1 - $k;
					$i++;
				} 
				?>

			</div>

			<?php if (!$this->folder_mode) : ?>
				<?php echo $pagination_footer; ?>
			<?php endif; ?>
			
			<input type="hidden" name="boxchecked" value="0" />
			<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
			<input type="hidden" name="controller" value="filemanager" />
			<input type="hidden" name="task" value="" />
			<?php echo $_tmpl ? '<input type="hidden" name="tmpl" value="'.$_tmpl.'" />' : ''; ?>
			<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
			<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
			<input type="hidden" name="fcform" value="1" />
			<?php echo JHTML::_( 'form.token' ); ?>

			<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
			<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
			<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
			<?php echo strlen($_forced_secure_int) ? '<input type="hidden" name="secure" value="'.$_forced_secure_int.'" />' : ''; ?>
			<input type="hidden" name="filename" value="" />
		</form>
		
	</div>
	
	
	<!-- File(s) by uploading -->
	
	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_FILES' ), 'fileupload' );*/ ?>
	<div class="tabbertab" id="local_tab" data-icon-class="icon-upload fc-fileman-upload-icon">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_UPLOAD_FILES' ); ?> </h3>
		
		<?php if (!$this->CanUpload && ($this->layout != 'image' || $this->view != 'fileselement')) : /* image layout of fileselement view is not subject to upload check */ ?>
			<?php echo sprintf( $alert_box, '', 'note', '', JText::_('FLEXI_YOUR_ACCOUNT_CANNOT_UPLOAD') ); ?>
		<?php else : ?>

		<?php if ($this->require_ftp) : ?>
		<form action="<?php echo $action_url . 'ftpValidate'; ?>" name="ftpForm" id="ftpForm" method="post">
			<fieldset title="<?php echo JText::_( 'FLEXI_FTP_LOGIN_DETAILS' ); ?>">
				<legend><?php echo JText::_( 'FLEXI_FTP_LOGIN_DETAILS' ); ?></legend>
				<table class="fc-form-tbl">
					<tbody>
						<tr>
							<td class="key"><label class="label" for="username"><?php echo JText::_( 'FLEXI_USERNAME' ); ?></label></td>
							<td><input type="text" id="username" name="username" class="input-xlarge" size="70" value="" /></td>
							<td class="key"><label class="label" for="password"><?php echo JText::_( 'FLEXI_PASSWORD' ); ?></label></td>
							<td><input type="password" id="password" name="password" class="input-xlarge" size="70" value="" /></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
		</form>
		<?php endif; ?>

		<!-- File Upload Form -->
		<fieldset class="filemanager-tab" >
			<?php
			// Configuration
			$phpUploadLimit = flexicontent_upload::getPHPuploadLimit();
			$server_limit_exceeded = $phpUploadLimit['value'] < $upload_maxsize;
			
			$conf_limit_class = $server_limit_exceeded ? 'badge badge-box' : '';
			$conf_limit_style = $server_limit_exceeded ? 'text-decoration: line-through;' : '';
			$conf_lim_image   = $server_limit_exceeded ? $warn_image.$hint_image : $hint_image;
			$sys_limit_class  = $server_limit_exceeded ? 'badge badge-box badge-important' : '';
			
			$limit_typename = $has_field_upload_maxsize ? 'FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT' : 'FLEXI_CONF_UPLOAD_MAX_LIMIT';
			$show_server_limit = $server_limit_exceeded && ! $enable_multi_uploader;  // plupload JS overcomes server limitations so we will not display it, if using plupload
			
			echo '
			<!--span class="label" style="font-size: 11px; margin-right:12px;" >'.JText::_( 'FLEXI_UPLOAD_LIMITS' ).'</span-->
			<div class="fc-fileman-upload-limits-box" style="font-size: 14px !important;">
			<table class="fc_uploader_header_tbl">
				<tr class="fc-about-size-limits">
					<td>
						<div class="fc-mssg fc-info fc-nobgimage fc-about-box">'.JText::_( 'FLEXI_UPLOAD_FILESIZE_MAX' ).'</div>
					</td>
					<td>
						<span class="fc-sys-upload-limit-box fc-about-conf-size-limit">
							<span class="icon-database"></span>
							<span class="'.$conf_limit_class.' '.$tip_class.'" style="margin-right: 4px; '.$conf_limit_style.'" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_FILESIZE_MAX_DESC', '', 1, 1).'">'.round($upload_maxsize / (1024*1024), 2).'</span> <span class="fc_hidden_580">MBytes</span>
						</span>
						'.($perms->SuperAdmin ?
							'<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip($limit_typename, $limit_typename.'_DESC', 1, 1).'"></span>
						' : '').'
						'.($server_limit_exceeded && ! $enable_multi_uploader ? /* plupload JS overcomes server limitations so we will not display it, if using plupload*/
						'
							<span class="fc-php-upload-limit-box fc-about-server-size-limit">
								<span class="icon-database"></span>
								<span class="'.$sys_limit_class.' '.$tip_class.'" style="margin-right: 4px;" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SERVER_UPLOAD_MAX_LIMIT'), JText::sprintf('FLEXI_SERVER_UPLOAD_MAX_LIMIT_DESC', $phpUploadLimit['name']), 0, 1).'">'.round($phpUploadLimit['value'] / (1024*1024), 2).'</span> MBytes
							</span>
						' : '').'
					</td>
					<td rowspan="3" style="text-align: center;" class="fc_hidden_960">
					'.($enable_multi_uploader ? '
						<div class="fc-mssg fc-info" style="margin: 0px 0 8px 0; padding-top: 4px; padding-bottom: 4px; width: 100%; box-sizing: border-box;">'.JText::_('Please edit file properties<br/>after you upload the files').'</div>
						<button class="btn-small '.$btn_class.' '.$tip_class.'" onclick="jQuery(\'#filemanager-1\').toggle(); jQuery(\'#filemanager-2\').toggle(); /*jQuery(\'#multiple_uploader\').css(\'min-height\', 180);*/ setTimeout(function(){showUploader(); fc_plupload_resize_now();}, 100);"
							id="single_multi_uploader" title="'.JText::_( 'FLEXI_TOGGLE_BASIC_UPLOADER_DESC' ).'" style=""
						>
							'.JText::_( 'FLEXI_TOGGLE_BASIC_UPLOADER' ).'
						</button>
					' : '').'
					<td>
				</tr>

				'.($resize_on_upload ? '
				<tr class="fc-about-dim-limits">
					<td>
						<div class="fc-mssg fc-info fc-nobgimage fc-about-box">
							'.JText::_( 'FLEXI_UPLOAD_DIMENSIONS_MAX' ).'
							<span class="icon-image '.$tip_class.' pull-right" style="margin:2px -4px 0px 8px" title="'.JText::_('FLEXI_UPLOAD_IMAGE_LIMITATION').'"></span>
						</div>
					</td>
					<td>
						<span class="fc-php-upload-limit-box">
							<span class="icon-contract-2"></span>
							<span class="'.$sys_limit_class.'" style="margin-right: 4px;">'.$upload_max_w.'x'.$upload_max_h.'</span> <span class="fc_hidden_580">Pixels</span>
							<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_DIMENSIONS_MAX_DESC', '', 1, 1).'"></span>
						</span>
					</td>
					<td>
					</td>
				</tr>

				<tr class="fc-about-crop-quality-limits">
					<td>
						<div class="fc-mssg fc-info fc-nobgimage fc-about-box">
							'.JText::_( 'FLEXI_UPLOAD_FIT_METHOD' ).'
							<span class="icon-image '.$tip_class.' pull-right" style="margin:2px -4px 0px 8px" title="'.JText::_('FLEXI_UPLOAD_IMAGE_LIMITATION').'"></span>
						</div>
					</td>
					<td>
						<span class="fc-php-upload-limit-box">
							<span class="icon-scissors" style="margin-right: 4px;'.($upload_method ? '' : 'opacity: 0.3;').'"></span>
							<span class="'.$tip_class.'" style="margin-right: 4px;">'.JText::_($upload_method ? 'FLEXI_CROP' : 'FLEXI_SCALE').' , '.$upload_quality.'% <span class="fc_hidden_580">'.JText::_('FLEXI_QUALITY', true).'</span></span>
							<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_FIT_METHOD_DESC', '', 1, 1).'"></span>
						</span>
					</td>
					<td>
					</td>
				</tr>
				' : '').'

			</table>
			</div>
			';
			?>

		<div id="filePropsForm_box_outer" style="display:none;">
			<fieldset class="actions flexicontent" id="filePropsForm_box">
				<form action="<?php echo $action_url . 'saveprops'; ?>&amp;format=raw" name="filePropsForm" id="filePropsForm" method="get" enctype="multipart/form-data">
					
					<!--span class="fcsep_level0" style="margin: 16px 0 12px 0; "><?php echo JText::_('FLEXI_FILE_PROPERTIES'); ?></span-->
					<table class="fc-form-tbl" id="file-props-form-container">

						<tr>
							<td id="file-props-name-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILENAME', 'FLEXI_FILE_FILENAME_DESC', 1, 1); ?>">
								<label class="label" id="file-props-name-lbl" for="file-props-name">
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
							<td id="file-props-title-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
								<label class="label" id="file-props-title-lbl" for="file-props-title">
								<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
								</label>
							</td>
							<td id="file-props-title-container">
								<input type="text" id="file-props-title" size="44" class="required input-xxlarge" name="file-props-title" />
							</td>
						</tr>

						<tr>
							<td id="file-props-desc-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" id="file-props-desc-lbl" for="file-props-desc_uploadFileForm">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td id="file-props-desc-container" style="vertical-align: top;">
								<textarea name="file-props-desc" cols="24" rows="3" id="file-props-desc_uploadFileForm" class="input-xxlarge"></textarea>
							</td>
						</tr>

						<tr>
							<td id="file-props-lang-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
								<label class="label" id="file-props-lang-lbl" for="file-props-lang">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
								</label>
							</td>
							<td id="file-props-lang-container">
								<?php echo str_replace('file-lang', 'file-props-lang', $this->lists['file-lang']); ?>
							</td>
						</tr>

						<tr>
							<td id="file-props-access-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
								<label class="label" id="file-props-access-lbl" for="file-props-access">
								<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
								</label>
							</td>
							<td id="file-props-access-container">
								<?php echo str_replace('file-access', 'file-props-access', $this->lists['file-access']); ?>
							</td>
						</tr>

						<?php if ($this->target_dir==2) : ?>
						<tr>
							<td id="file-props-secure-lbl-container" class="key <?php echo $tip_class; ?>" data-placement="bottom" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
								<label class="label" id="file-props-secure-lbl">
								<?php echo JText::_( 'FLEXI_URL_SECURE' ); ?>
								</label>
							</td>
							<td id="file-props-secure-container">
								<?php
								//echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_filePropsForm' );
								$_options = array();
								$_options['0'] = JText::_( 'FLEXI_MEDIA' );
								$_options['1'] = JText::_( 'FLEXI_SECURE' );
								echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_filePropsForm');
								?>
							</td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>

						<tr>
							<td style="text-align:right; padding: 12px 4px;">
								<input type="button" id="file-props-apply" class="btn btn-success" onclick="fc_plupload_submit_props_form(this, jQuery('#multiple_uploader').pluploadQueue()); return false;" value="<?php echo JText::_( 'FLEXI_APPLY' ); ?>"/>
							</td>
							<td style="text-align:left; padding: 12px 4px;">
								<input type="button" id="file-props-close" class="btn" onclick="fc_file_props_handle.dialog('close'); return false;" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>"/>
							</td>
						</tr>
					</table>
										
					<?php echo JHTML::_( 'form.token' ); ?>
					<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
					<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
					<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
					<?php echo strlen($_forced_secure_int) ? '<input type="hidden" name="secure" value="'.$_forced_secure_int.'" />' : ''; ?>
					<input type="hidden" name="file_row_id" value="" />
				</form>
				
			</fieldset>
		</div>

		
			<fieldset class="actions" id="filemanager-1">
				<form action="<?php echo $action_url . 'upload'; ?>" name="uploadFileForm" id="uploadFileForm" method="post" enctype="multipart/form-data">
					
					<span class="fcsep_level0" style="margin: 16px 0 12px 0; "><?php echo JText::_('FLEXI_BASIC_UPLOADER'); ?></span>
					<table class="fc-form-tbl" id="file-upload-form-container">
						
						<tr>
							<td id="file-upload-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_FILE', 'FLEXI_CHOOSE_FILE_DESC', 1, 1); ?>">
								<label class="label" id="file-upload-lbl" for="file-upload">
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
							<td id="file-title-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
								<label class="label" id="file-title-lbl" for="file-title">
								<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
								</label>
							</td>
							<td id="file-title-container">
								<input type="text" id="file-title" size="44" class="required input-xxlarge" name="file-title" />
							</td>
						</tr>
					<?php endif; ?>

					<?php if (!$this->folder_mode) : ?>
						<tr>
							<td id="file-desc-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" id="file-desc-lbl" for="file-desc_uploadFileForm">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td id="file-desc-container" style="vertical-align: top;">
								<textarea name="file-desc" cols="24" rows="3" id="file-desc_uploadFileForm" class="input-xxlarge"></textarea>
							</td>
						</tr>

						<tr>
							<td id="file-lang-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
								<label class="label" id="file-lang-lbl" for="file-lang">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
								</label>
							</td>
							<td id="file-lang-container">
								<?php echo $this->lists['file-lang']; ?>
							</td>
						</tr>

						<tr>
							<td id="file-access-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
								<label class="label" id="file-access-lbl" for="file-access">
								<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
								</label>
							</td>
							<td id="file-access-container">
								<?php echo $this->lists['file-access']; ?>
							</td>
						</tr>

						<?php if ($this->target_dir==2) : ?>
						<tr>
							<td id="secure-lbl-container" class="key <?php echo $tip_class; ?>" data-placement="bottom" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
								<label class="label" id="secure-lbl">
								<?php echo JText::_( 'FLEXI_URL_SECURE' ); ?>
								</label>
							</td>
							<td id="secure-container">
								<?php
								//echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_uploadFileForm' );
								$_options = array();
								$_options['0'] = JText::_( 'FLEXI_MEDIA' );
								$_options['1'] = JText::_( 'FLEXI_SECURE' );
								echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_uploadFileForm');
								?>
							</td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>

					</table>
					
					<input type="submit" id="file-upload-submit" class="btn btn-success" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>" style="margin: 16px 48px 0 48px;" />
					
					<?php echo JHTML::_( 'form.token' ); ?>
					<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
					<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
					<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
					<?php echo strlen($_forced_secure_int) ? '<input type="hidden" name="secure" value="'.$_forced_secure_int.'" />' : ''; ?>
					<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
					<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=<?php echo $this->view; ?>&field='.$this->fieldid.'&folder_mode='.$this->folder_mode.'&layout='.$this->layout.($_forced_secure_val ? '&filter_secure='.$_forced_secure_val : '').'&tmpl='.$_tmpl); ?>" />
				</form>
				
			</fieldset>
			
			<fieldset class="actions" id="filemanager-2" style="display:none;">
				<div id="multiple_uploader" class="fc_file_uploading" style="height: 0px;">
					<div id="multiple_uploader_failed" class="alert alert-warning">
						There was some JS error or JS issue, file uploader script failed to start
					</div>
				</div>
			</fieldset>
			
		</fieldset>
		
		<?php endif; /*CanUpload*/ ?>
		
	</div>
	
	
	<!-- File URL by Form -->
	<?php if ($this->layout !='image' ) : /* not applicable for LAYOUT 'image' */ ?>

	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_URL' ), 'filebyurl' );*/ ?>
	<div class="tabbertab" id="fileurl_tab" data-icon-class="icon-link">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ADD_FILE_URL' ); ?> </h3>

		<form action="<?php echo $action_url . 'addurl'; ?>" class="form-validate" name="addUrlForm" id="addUrlForm" method="post">
			<fieldset class="filemanager-tab" >
				<fieldset class="actions" id="filemanager-3">
					
					<table class="fc-form-tbl" id="file-url-form-container">
						
						<tr>
							<td id="file-url-data-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_URL', 'FLEXI_FILE_URL_DESC', 1, 1); ?>">
								<label class="label" for="file-url-data">
								<?php echo JText::_( 'FLEXI_FILE_URL' ); ?>
								</label>
							</td>
							<td id="file-url-data-container">
								<input type="text" id="file-url-data" size="44" class="required input-xxlarge" name="file-url-data" />
							</td>
						</tr>

						<tr>
							<td id="file-url-title-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
								<label class="label" for="file-url-title">
								<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
								</label>
							</td>
							<td id="file-url-title-container">
								<input type="text" id="file-url-title" size="44" class="required input-xxlarge" name="file-url-title" />
							</td>
						</tr>

						<tr>
							<td id="file-url-desc-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" for="file-url-desc">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td id="file-url-desc-container">
								<textarea name="file-url-desc" cols="24" rows="3" id="file-url-desc" class="input-xxlarge"></textarea>
							</td>
						</tr>

						<tr>
							<td id="file-url-lang-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
								<label class="label" id="file-url-lang-lbl" for="file-url-lang">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
								</label>
							</td>
							<td id="file-url-lang-container">
								<?php echo str_replace('file-lang', 'file-url-lang', $this->lists['file-lang']); ?>
							</td>
						</tr>

						<tr>
							<td id="file-url-access-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
								<label class="label" id="file-url-access-lbl" for="file-url-access">
								<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
								</label>
							</td>
							<td id="file-url-access-container">
								<?php echo str_replace('file-access', 'file-url-access', $this->lists['file-access']); ?>
							</td>
						</tr>

						<tr>
							<td id="file-url-ext-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILEEXT_MIME', 'FLEXI_FILEEXT_MIME_DESC', 1, 1); ?>">
								<label class="label" for="file-url-ext">
								<?php echo JText::_( 'FLEXI_FILEEXT_MIME' ); ?>
								</label>
							</td>
							<td id="file-url-ext-container">
								<input type="text" id="file-url-ext" size="5" class="required input-xxlarge" name="file-url-ext" />
							</td>
						</tr>

						<tr>
							<td id="file-url-size-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', '', 1, 1); ?>">
								<label class="label" for="file-url-size">
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

				</fieldset>
			</fieldset>

			<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
			<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
		</form>

	</div>
	
	<?php endif; /* End of TAB for File via URL form */ ?>
	
	
	<!-- File(s) from server Form -->
	
	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ), 'filefromserver' );*/ ?>
	<div class="tabbertab" id="server_tab" data-icon-class="icon-stack">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ADD_FILE_FROM_SERVER' ); ?> </h3>
		
		<form action="index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addlocal&amp;<?php echo JSession::getFormToken() . '=1'; ?>" class="form-validate" name="addFileForm" id="addFileForm" method="post">
			<fieldset class="filemanager-tab" >
				<fieldset class="actions" id="filemanager-4">
	
					<table class="fc-form-tbl" id="add-files-form-container">
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_SRC_DIR', 'FLEXI_CHOOSE_SRC_DIR_DESC', 1, 1); ?>">
								<label class="label" for="file-dir-path">
								<?php echo JText::_( 'FLEXI_CHOOSE_SRC_DIR' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-dir-path" size="50" value="/tmp" class="required input-xxlarge" name="file-dir-path" />
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
								<label class="label" id="_file-lang-lbl" for="_file-lang">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
								</label>
							</td>
							<td>
								<?php echo
									str_replace('id="file-lang', 'id="_file-lang',
									str_replace('id="file-lang', 'id="_file-lang', $this->lists['file-lang'])
									); ?>
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILTER_EXT', 'FLEXI_FILE_FILTER_EXT_DESC', 1, 1); ?>">
								<label class="label" for="file-filter-ext">
								<?php echo JText::_( 'FLEXI_FILE_FILTER_EXT' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-filter-ext" size="50" value="" class="input-xxlarge" name="file-filter-ext" />
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" for="file-desc_addFileForm">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td>
								<textarea name="file-desc" cols="24" rows="6" id="file-desc_addFileForm" class="input-xxlarge"></textarea>
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILTER_REGEX', 'FLEXI_FILE_FILTER_REGEX_DESC', 1, 1); ?>">
								<label class="label" for="file-filter-re">
								<?php echo JText::_( 'FLEXI_FILE_FILTER_REGEX' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-filter-re" size="50" value="" class="input-xxlarge" name="file-filter-re" />
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_KEEP_ORIGINAL_FILE', 'FLEXI_KEEP_ORIGINAL_FILE_DESC', 1, 1); ?>">
								<label class="label">
								<?php echo JText::_( 'FLEXI_KEEP_ORIGINAL_FILE' ); ?>
								</label>
							</td>
							<td>
								<?php
								//echo JHTML::_('select.booleanlist', 'keep', 'class="inputbox"', 1, JText::_( 'FLEXI_YES' ), JText::_( 'FLEXI_NO' ) );
								$_options = array();
								$_options['0'] = JText::_( 'FLEXI_NO' );
								$_options['1'] = JText::_( 'FLEXI_YES' );
								echo flexicontent_html::buildradiochecklist($_options, 'keep', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'keep_addFileForm');
								?>
							</td>
						</tr>
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" data-placement="top" title="<?php echo flexicontent_html::getToolTip('FLEXI_CHOOSE_DIRECTORY', 'FLEXI_CHOOSE_DIRECTORY_DESC', 1, 1); ?>">
								<label class="label">
								<?php echo JText::_( 'FLEXI_TARGET_DIRECTORY' ); ?>
								</label>
							</td>
							<td>
								<?php
								//echo JHTML::_('select.booleanlist', 'secure', 'class="inputbox"', 1, JText::_( 'FLEXI_SECURE' ), JText::_( 'FLEXI_MEDIA' ), 'secure_addFileForm' );
								$_options = array();
								$_options['0'] = JText::_( 'FLEXI_MEDIA' );
								$_options['1'] = JText::_( 'FLEXI_SECURE' );
								echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/0, /*attribs*/'', /*tagid*/'secure_addFileForm');
								?>
							</td>
						</tr>
						
					</table>
					
					<input type="submit" id="file-dir-submit" class="btn btn-success validate" value="<?php echo JText::_( 'FLEXI_ADD_DIR' ); ?>" style="margin: 16px 48px 0 16px;" />

				</fieldset>
			</fieldset>
			<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
			<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=filemanager'); ?>" />
		</form>
		
	</div>


			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_FILEMAN_INFO' ), 'fileinfo' );*/ ?>
			<div class="tabbertab" id="fileman_info_tab" data-icon-class="icon-info">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FILEMAN_INFO' ); ?> </h3>
				<div id="why_box" class="info-box">
					<h3>Why a DB-based filemanager ?</h3>
					<ul>
						<li>To keep track of <strong>file usage</strong> inside content (<strong>assigned</strong> items column in this page)</li>
						<li>To <strong>prevent direct access</strong> to files, allowing only indirect access, thus also hiding file's real path (* <strong>file / image-gallery fields</strong>)</li>
						<li>To add <strong>more control</strong> over the download <sup>1</sup>(<strong>file field</strong>) <br/>
							&nbsp; &nbsp; a. gathering <strong>hits</strong> and other statistics <br/>
							&nbsp; &nbsp; b. adding <strong>access</strong> control to the files, and more (e.g. download coupons <sup>1</sup>)</li>
						<li>To better handle a <strong>SET of re-usable</strong> images <sup>2,3</sup>(<strong>image-gallery field</strong> in DB-mode)</li>
					</ul>
					<p class="well"><sup>1</sup> Each new version may add more statistics and/or more download control<br/>
						<sup>2</sup> If images are <strong>not reusable</strong>, please do NOT use the DB-mode in image-gallery field, instead use <strong>'folder mode'</strong><br/>
						<sup>3</sup> If user can not add extra images and/or you need filtering in item listings, then instead use <strong>checkbox-image or radio-image fields</strong></p>
				</div>
			</div>
			<?php /* echo JHtml::_('tabs.end'); */ ?>
			
		</div>
		<!-- .fctabber end --> 

		<!-- fc_perf -->
	</div>  <!-- sidebar -->
</div><!-- #flexicontent end -->