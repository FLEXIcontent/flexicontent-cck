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

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$hint_image = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), '' );
$warn_image = JHTML::image ( 'components/com_flexicontent/assets/images/warning.png', JText::_( 'FLEXI_NOTES' ), '' );

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCfileselement_'.$this->layout.$this->fieldid, $start_text, $end_text);
$ctrl_task  = 'task=filemanager.';

$session = JFactory::getSession();
$document = JFactory::getDocument();
$cparams = JComponentHelper::getComponent('com_flexicontent')->params;

$secure_folder_tip  = '<i data-placement="bottom" class="icon-info fc-man-icon-s '.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';

// Common language strings
$edit_entry = JText::_('FLEXI_EDIT_FILE', true);
$view_entry = JText::_('FLEXI_VIEW', true);
$select_entry = JText::_('FLEXI_SELECT', true);
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
$enable_multi_uploader = $this->layout=='image';
$_forced_secure_val = $this->target_dir==2  ?  ''  :  ($this->target_dir==0 ? 'M' : 'S');
$_forced_secure_int = $this->target_dir==2  ?  ''  :  ($this->target_dir==0 ? '0' : '1');
?>
<script type="text/javascript">

jQuery(document).ready(function() {
	var use_mul_upload = <?php echo $enable_multi_uploader ? 1 : 0; ?>;
	if (use_mul_upload)
	{
		// Show TAB and plupload manager
		jQuery('#filemanager-2').show();
		showUploader();
		jQuery('#multiple_uploader').height(210);

		// Also set filelist height
		var plupload_filelist_h = 568 > jQuery( window ).height()  ?  jQuery( window ).height() - 320  :  568;
		jQuery('.plupload_filelist:not(.plupload_filelist_header):not(.plupload_filelist_footer)').css({ 'height': plupload_filelist_h+'px' });
	}
	fctabber['fileman_tabset'].tabShow(0);
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

function delAllFilters() {
	delFilter('search');
	delFilter('filter_uploader');
	delFilter('filter_lang');
	delFilter('filter_url');
	delFilter('filter_secure');
	delFilter('filter_ext');
	delFilter('item_id');
	delFilter('filter_order'); delFilter('filter_order_Dir');
}

var _file_data = new Array();
<?php
// Output file data JSON encoded so that they are available to JS code
// but exclude parameters and other array data from encoding since we will not need them
foreach ($this->rows as $i => $row) :
	$data = new stdClass();
	foreach($row as $j => $d) {
		if (!is_array($d) && !is_object($d)) $data->$j = utf8_encode($d);
	}
	echo '  _file_data['.$i.'] = '.json_encode($data).";\n";
endforeach;
?>
</script>



<?php

// *********************
// BOF multi-uploader JS
// *********************

$upload_maxsize = $this->field ? (int) $this->field->parameters->get('upload_maxsize', 0) : 0;  // Try field with upload_maxsize parameter
$has_field_upload_maxsize = (boolean) $upload_maxsize;
$upload_maxsize = $upload_maxsize ? $upload_maxsize : (int) $this->params->get('upload_maxsize', '10000000');   // Fallback to component parameter
$resize_on_upload = $this->field ? (int) $this->field->parameters->get('resize_on_upload', 1) : 0;

$user = JFactory::getUser();	// get current user
$perms = FlexicontentHelperPerm::getPerm();  // get global perms

if ($enable_multi_uploader)
{
	// Load plupload JS framework
	$doc = JFactory::getDocument();
	$pluploadlib = JURI::root(true).'/components/com_flexicontent/librairies/plupload/';
	$plupload_mode = 'runtime';  // 'runtime,ui'
	flexicontent_html::loadFramework('plupload', $plupload_mode);
	
	// Initialize a plupload Queue
	if ($resize_on_upload)
	{
		$upload_max_w   = $this->field->parameters->get('upload_max_w', 4000);
		$upload_max_h   = $this->field->parameters->get('upload_max_h', 3000);
		$upload_quality = $this->field->parameters->get('upload_quality', 95);
		$upload_crop    = $this->field->parameters->get('upload_crop', 1);
	}
	
	$js = '
	// I handle the PostInit event. At this point, we will know which runtime
	// has loaded, and whether or not drag-drop functionality is supported.
	// --
	// NOTE: we use the "PostInit" instead of the "Init" event in order for the "dragdrop" feature to be correct defined
	function handlePluploadInit( uploader, params )
	{
		//window.console.log( "Initialization complete." );
		//window.console.log( "Drag-drop supported:", !! uploader.features.dragdrop );
	}
	
	
	// I handle the files-added event. This is different that the queue-changed event.
	// Since at this point, we have an opportunity to reject files from the queue.
	function handlePluploadFilesAdded( uploader, files )
	{
		//window.console.log( "Files added." );

		// Since the full list is recreated, on new file(s) added. We need to loop through all
		// files and update their client-side preview, and not only through the newly addede files
		for ( var i = 0 ; i < uploader.files.length ; i++ )   //for ( var i = 0 ; i < files.length ; i++ )
		{
			if ( uploader.files[i].name.match(/\.(jpg|jpeg|png|gif)$/i) )
			{
				showImagePreview( uploader.files[i] );  //showImagePreview( files[i] );
			}
			else
			{
				var tagid = uploader.files[i].id;
				var item = jQuery("#"+tagid).find(".plupload_file_name");
				item.css("width", "auto");
				item.find("span").css("width", "auto");
				item.closest("li").css("width", "auto");
			}
		}
	}
	
	var fc_loaded_imgs = {};
	
	// Create client side image preview. This is given a File object (as presented by Plupload),
	// and show the client-side-only preview of the selected image object.
	function showImagePreview( file )
	{
		var tagid = file.id;
		var item = jQuery("#"+tagid).find(".plupload_file_name");

		var box = jQuery("<span class=\"plupload_img_preview\"></span>").appendTo( item );
		var prev_handle = jQuery("<span class=\"btn fc_img_preview_btn icon-eye\"></span></span>").insertBefore( box );

		// Try to use already loaded image
		var image_loaded = typeof fc_loaded_imgs[tagid] != "undefined";
		if (!image_loaded)
		{
			fc_loaded_imgs[tagid] = jQuery( "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" />" );
		}
		fc_loaded_imgs[tagid].appendTo( box );


		// Add zoom-in-out on click
		prev_handle.add(fc_loaded_imgs[tagid]).on( "click", function()
		{
			// Close any open previews
			var btn, img;
			btn = jQuery(this).closest(".plupload_file_name").find(".fc_img_preview_btn");
			img = jQuery(this).closest(".plupload_file_name").find(".plupload_img_preview img");

			btn.closest("ul").find("li:not(\'#" + btn.closest("li").attr("id") + "\') .btn.fc_img_preview_btn.active").trigger("click");
			if (img.hasClass("fc_uploader_zoomed"))
			{
				btn.removeClass("active btn-info");
				img.removeClass("fc_uploader_zoomed");
				setTimeout(function(){ img.removeClass("fc_uploader_zooming"); }, 400);
			}
			else {
				if (img.hasClass("fc_uploader_zooming")) return;
				img.addClass("fc_uploader_zooming fc_uploader_zoomed");
				btn.addClass("active btn-info");
			}
		});
		
		if (image_loaded) return; // Done if image has been loaded already


		// Create an instance of the mOxie Image object.  --  Wiki: https://github.com/moxiecode/moxie/wiki/Image
		// This utility object provides several means of reading in and loading image data from various sources.
		file.preloader = new mOxie.Image();
	
		// Define the onload BEFORE you execute the load() command as load() does not execute async.
		file.preloader.onload = function()
		{
			// This will scale the image (in memory) before it tries to render it. This just reduces the amount of Base64 data that needs to be rendered.
			// Use higher resultion to allow zooming and also for better thumbnail
			this.downsize( 800, 600 );

			// Now that the image is preloaded, grab the Base64 encoded data URL. This will show the image without making an Network request using the client-side file binary.
			fc_loaded_imgs[tagid].prop( "src", this.getAsDataURL() );
		};

		// Calling the .getSource() on the file will return an instance of mOxie.File, which is a unified file wrapper that can be used across the various runtimes.
		// Wiki: https://github.com/moxiecode/plupload/wiki/File
		file.preloader.load( file.getSource() );
	}


	var uploader = 0;
	function showUploader()
	{
		if (uploader)
		{
			// Already initialized, re-initialize and empty it
			uploader.init();
			uploader.splice();
		}
		
		else if ("'.$plupload_mode.'"=="ui")
		{
	    uploader = jQuery("#multiple_uploader").plupload({
				// General settings
				runtimes : "html5,html4,flash,silverlight",
				url : "'.JURI::base().'index.php?option=com_flexicontent&'.$ctrl_task.'uploads&'.$session->getName().'='.$session->getId().(strlen($_forced_secure_int) ? '&secure='.$_forced_secure_int : '').'&fieldid='.$this->fieldid.'&u_item_id='.$this->u_item_id.'&folder_mode='.$this->folder_mode.'&'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1",
				unique_names : true,
				
				// Set maximum file size and chunking to 1 MB
				max_file_size : "'.$upload_maxsize.'",
				chunk_size: "1mb",
				
				// Resize images on clientside if we can
				'.($resize_on_upload ? '
				resize : {
					width : '.$upload_max_w.',
					height : '.$upload_max_h.',
					quality : '.$upload_quality.',
					crop: '.($upload_crop ? 'true' : 'false').'},
				' : '').'
				
				// Specify what files to browse for
				filters : {
					max_file_size : "'.$upload_maxsize.'"
				},

				// Rename files by clicking on their titles
				rename: true,

				// Sort files
				sortable: true,

				// Enable ability to drag n drop files onto the widget (currently only HTML5 supports that)
				dragdrop: true,

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
							filename: file.name
						};
					}
					/*,
					UploadComplete: function (up, files) {
						if(window.console) window.console.log("All Files Uploaded");
						//window.location.reload();
						window.location.replace(window.location.href);
					}*/
				}
	    })

			// Set up the event handlers for the uploader
			.bind(\'PostInit\', handlePluploadInit)
			.bind(\'FilesAdded\', handlePluploadFilesAdded)
			.bind(\'complete\',function()
			{
				if(window.console) window.console.log("All Files Uploaded");
				window.location.replace(window.location.href);  //window.location.reload();
			});
			
		} else {
			jQuery("#multiple_uploader").pluploadQueue({
				// General settings
				runtimes : "html5,html4,flash,silverlight",
				url : "'.JURI::base().'index.php?option=com_flexicontent&'.$ctrl_task.'uploads&'.$session->getName().'='.$session->getId().(strlen($_forced_secure_int) ? '&secure='.$_forced_secure_int : '').'&fieldid='.$this->fieldid.'&u_item_id='.$this->u_item_id.'&folder_mode='.$this->folder_mode.'&'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1",
				unique_names : true,
				
				// Set maximum file size and chunking to 1 MB
				max_file_size : "'.$upload_maxsize.'",
				chunk_size: "1mb",
				
				// Resize images on clientside if we can
				'.($resize_on_upload ? '
				resize : {
					width : '.$upload_max_w.',
					height : '.$upload_max_h.',
					quality : '.$upload_quality.',
					crop: '.($upload_crop ? 'true' : 'false').'},
				' : '').'

				// Specify what files to browse for
				filters : {
					max_file_size : "'.$upload_maxsize.'"
				},
				
				// Rename files by clicking on their titles
				rename: true,
				 
				// Sort files
				sortable: true,
				
				// Enable ability to drag n drop files onto the widget (currently only HTML5 supports that)
				dragdrop: true,
				
				// Views to activate
				views: {
					list: true,
					thumbs: true, // Show thumbs
					active: "thumbs"
				},
				
				// Flash settings
				flash_swf_url : "'.$pluploadlib.'/js/Moxie.swf",

				// Silverlight settings
				silverlight_xap_url : "'.$pluploadlib.'/js/Moxie.xap",
				
				init: {
					BeforeUpload: function (up, file) {
						// Called right before the upload for a given file starts, can be used to cancel it if required
						up.settings.multipart_params = {
							filename: file.name
						};
					}
					/*,
					UploadComplete: function (up, files) {
						if(window.console) window.console.log("All Files Uploaded");
						//window.location.reload();
						window.location.replace(window.location.href);
					}*/
				}
			});
			
			// Need to make 2nd call to get the created uploader instance
			uploader = jQuery("#multiple_uploader").pluploadQueue();

			uploader.bind(\'PostInit\', handlePluploadInit);
			uploader.bind(\'FilesAdded\', handlePluploadFilesAdded);
			uploader.bind(\'UploadComplete\',function()
			{
				if(window.console) window.console.log("All Files Uploaded");
				//window.location.reload();
				window.location.replace(window.location.href);
			});
		}
	};
	';
	
	$doc->addScriptDeclaration($js);
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
?>

<div id="flexicontent" class="flexicontent">


<?php /* echo JHtml::_('tabs.start'); */ ?>
<div class="fctabber" id="fileman_tabset">
	
	<!-- File listing -->
	
	<?php /* echo JHtml::_('tabs.panel', JText::_( 'FLEXI_FILEMAN_LIST' ), 'filelist' ); */ ?>
	<div class="tabbertab" id="filelist_tab" data-icon-class="icon-list">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FILEMAN_LIST' ); ?> </h3>
		
		<form action="index.php?option=<?php echo $this->option; ?>&amp;view=<?php echo $this->view; ?><?php echo $_forced_secure_val ? '&amp;filter_secure='.$_forced_secure_val : ''; ?>&amp;layout=<?php echo $this->layout; ?>&amp;field=<?php echo $this->fieldid?>&amp;tmpl=component" method="post" name="adminForm" id="adminForm">
		
		<?php if (!$this->folder_mode) : ?>
		
			<div id="fc-filters-header">
				<span class="fc-filter nowrap_box" style="margin:0;">
					<?php echo $this->lists['scope']; ?>
				</span>
				<span class="btn-group input-append fc-filter filter-search">
					<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
					<button title="" data-original-title="<?php echo JText::_('FLEXI_SEARCH'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
					<button title="" data-original-title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class.' '.$tip_class; ?>" onclick="document.adminForm.limitstart.value=0; delAllFilters(); Joomla.submitform();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
				</span>
				
				<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
				<span class="btn-group input-append fc-filter">
					<input type="button" id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_FILTERS' ); ?>" />
					<input type="button" id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_COLUMNS' ); ?>" />
				</span>
				
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
				
				<?php if ($this->CanViewAllFiles && !empty($this->cols['uploader'])) : /* FOR files element place filter outside the filter box */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['uploader']; ?>
					</div>
				</div>
				<?php endif; ?>
			
			</div>
			
			
			<div id="fc-filters-box" <?php if (!$this->count_filters) echo 'style="display:none;"'; ?> class="">
				<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->

				<?php if (!empty($this->cols['lang'])) :  /* if layout==image then this was force to unset */ ?>
				<div class="fc-filter nowrap_box">
					<div <?php echo $fcfilter_attrs_row; ?> >
						<?php echo $this->lists['language']; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ($this->layout!='image') : /* if layout==image then this URL filter is not applicable */ ?>
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
					<input type="button" id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_COLUMNS' ); ?>" />
					<!--input type="button" id="fc_upload_box_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('fileman_tabset', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_UPLOAD' ); ?>" /-->
				</div>
		
		<?php endif; ?>
		
			<div class="fcclear"></div>
			<div id="mainChooseColBox" class="well well-small" style="display:none;"></div>
			<div class="fcclear"></div>
			
			<table id="adminListTableFCfileselement_<?php echo $this->layout.$this->fieldid; ?>" class="adminlist fcmanlist">
			<thead>
    		<tr class="header">
					<th class="center hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
					
				<?php if (!$this->folder_mode) : ?>
					<th class="center"><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
				<?php else : ?>
					<th>&nbsp;</th>
				<?php endif; ?>
					
					<th class="center hideOnDemandClass"><?php echo JText::_( 'FLEXI_THUMB' ); ?></th>
					<th class="left">
						<?php echo JHTML::_('grid.sort', 'FLEXI_FILENAME', 'f.filename_displayed', $this->lists['order_Dir'], $this->lists['order'] ); ?>
						/
						<?php echo JHTML::_('grid.sort', 'FLEXI_FILE_DISPLAY_TITLE', 'f.altname', $this->lists['order_Dir'], $this->lists['order'] ); ?>
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
				
				</tr>
			</thead>
		
		<?php if (!$this->folder_mode) : ?>
			<tfoot>
				<tr>
					<td colspan="<?php echo $list_total_cols; ?>" style="text-align: left;">
						<?php echo $pagination_footer; ?>
					</td>
				</tr>
			</tfoot>
		<?php endif; ?>
		
			<tbody>
				<?php
				$imageexts = array('jpg','gif','png','bmp','jpeg');
				$index = JRequest::getInt('index', 0);
				$k = 0;
				$i = 0;
				$n = count($this->rows);
				foreach ($this->rows as $row)
				{
					$checked 	= @ JHTML::_('grid.checkedout', $row, $i );
					
					unset($thumb_or_icon);
					$filename = str_replace( array("'", "\""), array("\\'", ""), $row->filename );
					$filename_original = str_replace( array("'", "\""), array("\\'", ""), $row->filename_original );
					$filename_original = $filename_original ? $filename_original : $filename;
					
					$fileid = $this->folder_mode ? '' : $row->id;
					
					$ext = strtolower($row->ext);
					
					// Check if file is NOT an known / allowed image, and skip it if LAYOUT is 'image' otherwise display a 'type' icon
					if ( !in_array($ext, $imageexts)) {
						if ( $this->layout=='image')
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
					if ( empty($thumb_or_icon) ) {
						if (file_exists($file_path)){
							$thumb_or_icon = '<img src="'.JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_url.$_f. '&amp;w=60&amp;h=60&amp;zc=1&amp;ar=x" alt="'.$filename_original.'" />';
						} else {
							$thumb_or_icon = '<span class="badge badge-important">'.JText::_('FLEXI_FILE_NOT_FOUND').'</span>';
						}
					}
					
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
					
					if ( in_array($ext, $imageexts)) {
						$file_preview  = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' .$file_url.$_f. '&amp;w='.$this->thumb_w.'&amp;h='.$this->thumb_h.'&amp;zc=1&amp;q=95&amp;ar=x';
					} else {
						$file_preview  = '';
					}
					
					if ($this->folder_mode) {
						$img_assign_link = "window.parent.qmAssignFile".$this->fieldid."('".$this->targetid."', '".$filename."', '".$file_preview."', 0, '".$filename_original."');document.getElementById('file{$i}').className='striketext';";
					} else {
						$img_assign_link = "var file_data = _file_data[ '".$i."']; file_data.displayname = '".$filename_original."'; file_data.preview = '".$file_preview."';  qffileselementadd(document.getElementById('file".$row->id."'), '".$row->id."', '".$filename_original."', '".$this->targetid."', file_data);";
					}
		   		?>
				<tr class="<?php echo "row$k"; ?>">
					<td class="center hidden-phone">
						<?php echo $this->pagination->getRowOffset( $i ); ?>
					</td>
					
					<td>
						<?php echo '<span style="display: none;">'.$checked.'</span>'; ?>
						<a href="javascript:;" onclick="if (confirm('<?php echo JText::_('FLEXI_SURE_TO_DELETE_FILE', true); ?>')) { document.adminForm.filename.value='<?php echo rawurlencode($row->filename);?>'; return listItemTask('cb<?php echo $i; ?>','filemanager.remove'); }">
						<?php echo JHTML::image('components/com_flexicontent/assets/images/trash.png', JText::_('FLEXI_REMOVE') ); ?>
						</a>
					</td>
					
					<td class="center">
						<a style="cursor:pointer; font-family:Georgia;" class="<?php echo $tip_class; ?>" onclick="<?php echo $img_assign_link; ?>" title="<?php echo $select_entry; ?>">
							<?php echo $thumb_or_icon; ?>
						</a>
					</td>
					
					<td class="left">
						<?php
							$_filename_original = $row->filename_original ? $row->filename_original :$row->filename;
							if (StringHelper::strlen($row->filename_original) > 100) {
								$filename_cut = htmlspecialchars(flexicontent_html::striptagsandcut($_filename_original, 100) . '...', ENT_QUOTES, 'UTF-8');
							} else {
								$filename_cut = htmlspecialchars($_filename_original, ENT_QUOTES, 'UTF-8');
							}
						?>
						<?php echo '
						<a style="cursor:pointer; font-family:Georgia;" id="file'.$row->id.'" class="'.$btn_class.' '.$tip_class.' btn-small" data-fileid="'.$fileid.'" data-filename="'.$filename.'" onclick="'.$img_assign_link.'" title="'.$select_entry.'">
							'.$filename_cut.'
						</a>
						'; ?>
						
						<?php
						if (!$this->folder_mode && $row->altname != $row->filename_displayed) {
							if (StringHelper::strlen($row->altname) > 100) {
								echo '<br/><small>'.StringHelper::substr( htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8'), 0 , 100).'... </small>';
							} else {
								echo '<br/><small>'.htmlspecialchars($row->altname, ENT_QUOTES, 'UTF-8').'</small>';
							}
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
					</td>
				<?php endif; ?>
				
				<?php if ($this->CanViewAllFiles && !empty($this->cols['uploader'])) : ?>
					<td class="center hidden-phone">
						<?php echo $row->uploader; ?>
					</td>
				<?php endif; ?>
				
			<?php endif; ?>
			
			
				<?php if (!empty($this->cols['upload_time'])) : ?>
					<td class="center hidden-tablet hidden-phone">
						<?php echo JHTML::Date( $row->uploaded, JText::_( 'DATE_FORMAT_LC4' )." H:i:s" ); ?>
					</td>
				<?php endif; ?>
			
			
			<?php if (!$this->folder_mode) : ?>
			
				<?php if (!empty($this->cols['file_id'])) : ?>
					<td class="center hidden-tablet hidden-phone"><?php echo $row->id; ?></td>
				<?php endif; ?>
				
			<?php endif; ?>
				
				</tr>
				<?php 
					$k = 1 - $k;
					$i++;
				} 
				?>
			</tbody>
			
			</table>
			
			<input type="hidden" name="boxchecked" value="0" />
			<input type="hidden" name="controller" value="filemanager" />
			<input type="hidden" name="task" value="" />
			<input type="hidden" name="file" value="" />
			<input type="hidden" name="files" value="<?php echo $this->files; ?>" />
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
	
	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ), 'fileupload' );*/ ?>
	<div class="tabbertab" id="local_tab" data-icon-class="icon-upload">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_UPLOAD_LOCAL_FILE' ); ?> </h3>
		
		<?php if (!$this->CanUpload && !$this->layout!=='image') : /* image layout is not subject to upload check */ ?>
			<?php echo sprintf( $alert_box, '', 'note', '', JText::_('FLEXI_YOUR_ACCOUNT_CANNOT_UPLOAD') ); ?>
		<?php else : ?>
		
		<!-- File Upload Form -->
		<fieldset class="filemanager-tab" >
			<?php
			// Configuration
			$phpUploadLimit = flexicontent_upload::getPHPuploadLimit();
			$server_limit_exceeded = $phpUploadLimit['value'] < $upload_maxsize;
			
			$conf_limit_class = $server_limit_exceeded ? '' : 'badge-success';
			$conf_limit_style = $server_limit_exceeded ? 'text-decoration: line-through;' : '';
			$conf_lim_image   = $server_limit_exceeded ? $warn_image.$hint_image : $hint_image;
			$sys_limit_class  = $server_limit_exceeded ? 'badge-important' : '';
			
			$limit_typename = $has_field_upload_maxsize ? 'FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT' : 'FLEXI_CONF_UPLOAD_MAX_LIMIT';
			$show_server_limit = $server_limit_exceeded && ! $enable_multi_uploader;  // plupload JS overcomes server limitations so we will not display it, if using plupload
			
			echo '
			<!--span class="alert alert-info fcpadded" style="font-size: 11px; margin-right:12px;" >'.JText::_( 'FLEXI_UPLOAD_LIMITS' ).'</span-->
			<span class="fc-fileman-upload-limits-box" style="font-size: 14px !important;">
			<table style="border-collapse: collapse;">
				<tr>
					<td style="padding-right: 24px;">
						<div class="alert alert-info" style="padding: 2px; margin: 2px 0; text-align: left;">'.JText::_( 'FLEXI_UPLOAD_FILESIZE_MAX' ).'</div>
					</td>
					<td>
						<span class="fc-sys-upload-limit-box">
							<span class="icon-database"></span>
							<span class="badge '.$conf_limit_class.' '.$tip_class.'" style="margin-right: 4px; '.$conf_limit_style.'" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_FILESIZE_MAX_DESC', '', 1, 1).'">'.round($upload_maxsize / (1024*1024), 2).'</span> MBytes
						</span>
						'.($perms->SuperAdmin ?
							'<span class="icon-info '.$tip_class.'" title="'.flexicontent_html::getToolTip($limit_typename, $limit_typename.'_DESC', 1, 1).'"></span>
						' : '').'
						'.($server_limit_exceeded && ! $enable_multi_uploader ? /* plupload JS overcomes server limitations so we will not display it, if using plupload*/
						'
							<span class="fc-php-upload-limit-box">
								<span class="icon-database"></span>
								<span class="badge '.$sys_limit_class.' '.$tip_class.'" style="margin-right: 4px;" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SERVER_UPLOAD_MAX_LIMIT'), JText::sprintf('FLEXI_SERVER_UPLOAD_MAX_LIMIT_DESC', $phpUploadLimit['name']), 0, 1).'">'.round($phpUploadLimit['value'] / (1024*1024), 2).'</span> MBytes
							</span>
						' : '').'
					</td>
					'.($enable_multi_uploader ? '
					<td rowspan="2" style="padding-left: 48px;">
						<button class="btn-small '.$btn_class.' '.$tip_class.'" onclick="jQuery(\'#filemanager-1\').toggle(); jQuery(\'#filemanager-2\').toggle(); jQuery(\'#multiple_uploader\').height(210); setTimeout(function(){showUploader()}, 100);"
							id="single_multi_uploader" title="'.JText::_( 'FLEXI_TOGGLE_BASIC_UPLOADER_DESC' ).'"
						>
							'.JText::_( 'FLEXI_TOGGLE_BASIC_UPLOADER' ).'
						</button>
					<td>' : '').'
				</tr>

				<tr>
					<td style="padding-right: 24px;">
						<div class="alert alert-info" style="padding: 2px; margin: 2px 0; text-align: left;">'.JText::_( 'FLEXI_UPLOAD_DIMENSIONS_MAX' ).'</div>
					</td>
					<td>
						<span class="fc-php-upload-limit-box">
							<span class="icon-contract-2"></span>
							<span class="badge badge-success'.$sys_limit_class.' '.$tip_class.'" style="margin-right: 4px;" title="'.flexicontent_html::getToolTip('FLEXI_UPLOAD_DIMENSIONS_MAX_DESC', '', 1, 1).'">'.$upload_max_w.'x'.$upload_max_h.'</span> Pixels
						</span>
					</td>
				</tr>
			</table>
			</span>
			';
			?>

		
			<fieldset class="actions" id="filemanager-1">
				<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>upload&amp;<?php echo $session->getName().'='.$session->getId(); ?>" name="uploadFileForm" id="uploadFileForm" method="post" enctype="multipart/form-data">
					
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
								<input type="file" id="file-upload" name="Filedata" onchange="fc_loadImagePreview(this.id,'img_preview', 'img_preview_msg', 100, 0, '-1');" />
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
							<td id="file-desc-lbl-container" class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" id="file-desc-lbl" for="file-desc_uploadFileForm">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td id="file-desc-container" style="vertical-align: top;">
								<textarea name="file-desc" cols="24" rows="3" id="file-desc_uploadFileForm" class="input-xxlarge"></textarea>
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
								echo flexicontent_html::buildradiochecklist($_options, 'secure', /*selected*/1, /*type*/1, /*attribs*/'', /*tagid*/'secure_uploadFileForm');
								?>
							</td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>

					</table>
					
					<input type="submit" id="file-upload-submit" class="fc_button fcsimple" value="<?php echo JText::_( 'FLEXI_START_UPLOAD' ); ?>"/>
					<span id="upload-clear"></span>
					
					<?php echo JHTML::_( 'form.token' ); ?>
					<input type="hidden" name="fieldid" value="<?php echo $this->fieldid; ?>" />
					<input type="hidden" name="u_item_id" value="<?php echo $this->u_item_id; ?>" />
					<input type="hidden" name="folder_mode" value="<?php echo $this->folder_mode; ?>" />
					<?php echo strlen($_forced_secure_int) ? '<input type="hidden" name="secure" value="'.$_forced_secure_int.'" />' : ''; ?>
					<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
					<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&tmpl=component&field='.$this->fieldid.'&folder_mode='.$this->folder_mode.'&layout='.$this->layout.($_forced_secure_val ? '&filter_secure='.$_forced_secure_val : '')); ?>" />
				</form>
				
			</fieldset>
			
			<fieldset class="actions" id="filemanager-2" style="display:none;">
				<div id="multiple_uploader" class="fc_file_uploading" style="height: 0px;">
					<div id="multiple_uploader_failed" class="alert alert-warning">
						There was some JS error or JS issue, plupload script failed to start
					</div>
				</div>
			</fieldset>
			
		</fieldset>
		
		<?php endif; /*CanUpload*/ ?>
		
	</div>
	
	
	<!-- File URL by Form -->
	<?php if ($this->layout !='image' ) : /* not applicable for LAYOUT 'image' */ ?>

	<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_ADD_FILE_BY_URL' ), 'filebyurl' );*/ ?>
	<div class="tabbertab" id="fileurl_tab" data-icon-class="icon-out">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ADD_FILE_BY_URL' ); ?> </h3>

		<form action="<?php echo JURI::base(); ?>index.php?option=com_flexicontent&amp;<?php echo $ctrl_task; ?>addurl&amp;<?php echo $session->getName().'='.$session->getId(); ?>&amp;<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1" class="form-validate" name="addUrlForm" id="addUrlForm" method="post">
			<fieldset class="filemanager-tab" >
				<fieldset class="actions" id="filemanager-3">
					
					<table class="fc-form-tbl" id="file-url-form-container">
						
						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_URL', 'FLEXI_FILE_URL_DESC', 1, 1); ?>">
								<label class="label" for="file-url-data">
								<?php echo JText::_( 'FLEXI_FILE_URL' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-url-data" size="44" class="required input-xxlarge" name="file-url-data" />
							</td>
						</tr>

						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
								<label class="label" for="file-url-title">
								<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-url-title" size="44" class="required input-xxlarge" name="file-url-title" />
							</td>
						</tr>

						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
								<label class="label" id="file-url-lang-lbl" for="file-url-lang">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
								</label>
							</td>
							<td>
								<?php echo str_replace('file-lang', 'file-url-lang', $this->lists['file-lang']); ?>
							</td>
						</tr>

						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
								<label class="label" for="file-url-desc">
								<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
								</label>
							</td>
							<td>
								<textarea name="file-url-desc" cols="24" rows="3" id="file-url-desc" class="input-xxlarge"></textarea>
							</td>
						</tr>

						<tr>
							<td class="key <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILEEXT_MIME', 'FLEXI_FILEEXT_MIME_DESC', 1, 1); ?>">
								<label class="label" for="file-url-ext">
								<?php echo JText::_( 'FLEXI_FILEEXT_MIME' ); ?>
								</label>
							</td>
							<td>
								<input type="text" id="file-url-ext" size="5" class="required input-xxlarge" name="file-url-ext" />
							</td>
						</tr>

					</table>
					
					<input type="submit" id="file-url-submit" class="fc_button fcsimple validate" value="<?php echo JText::_( 'FLEXI_ADD_FILE' ); ?>"/>
				</fieldset>
			</fieldset>
			<?php /* NOTE: return URL should use & and not &amp; for variable seperation as these will be re-encoded on redirect */ ?>
			<input type="hidden" name="return-url" value="<?php echo base64_encode('index.php?option=com_flexicontent&view=fileselement&tmpl=component&field='.$this->fieldid.'&folder_mode='.$this->folder_mode.'&layout='.$this->layout.($_forced_secure_val ? '&filter_secure='.$_forced_secure_val : '')); ?>" />
		</form>

	</div>
	
	<?php endif; /* End of TAB for File via URL form */ ?>

<?php /* echo JHtml::_('tabs.end'); */ ?>
</div><!-- .fctabber end -->

</div><!-- #flexicontent end -->