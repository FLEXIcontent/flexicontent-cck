<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 * 
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('FlexicontentControllerFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');  // we use JPATH_BASE since controller exists in frontend too
JLoader::register('FlexicontentModelFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');  // we use JPATH_BASE since model exists in frontend too

if (!defined('_FC_CONTINUE_'))  define('_FC_CONTINUE_', 0);
if (!defined('_FC_BREAK_'))  define('_FC_BREAK_', -1);

class plgFlexicontent_fieldsImage extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	static $value_only_displays = array("display_backend_src"=>0, "display_small_src"=>1, "display_medium_src"=>2, "display_large_src"=>3, "display_original_src"=>4);
	static $single_displays = array('display_single'=>0, 'display_single_total'=>1, 'display_single_link'=>2, 'display_single_total_link'=>3);
	
	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$is_ingroup  = !empty($field->ingroup);
		
		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		// Execute once
		static $initialized = null;
		if ( !$initialized )
		{
			$initialized = 1;
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
		}
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';

		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);
		
		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $u_item_id;
		
		
		
		// ***
		// *** Number of values
		// ***

		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : !$multiple ? 1 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required_class = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir     = $field->parameters->get('dir');
		$dir_url = str_replace('\\', '/', $dir);
		

		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		$autoassign = (int) $field->parameters->get( 'autoassign', 1 );
		
		$thumb_size_resizer = $field->parameters->get('thumb_size_resizer', 2);
		$thumb_size_default = $field->parameters->get('thumb_size_default', 120);
		$preview_thumb_w = $preview_thumb_h = 600;

		// optional properies configuration
		$linkto_url  = $field->parameters->get('linkto_url', 0 );
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$title_usage = $field->parameters->get( 'title_usage', 0 ) ;
		$desc_usage  = $field->parameters->get( 'desc_usage', 0 ) ;
		$cust1_usage = $field->parameters->get( 'cust1_usage', 0 );
		$cust2_usage = $field->parameters->get( 'cust2_usage', 0 );
		
		$default_alt    = ($item->version == 0 || $alt_usage > 0) ? $field->parameters->get( 'default_alt', '' ) : '';
		$default_title  = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_desc   = ($item->version == 0 || $desc_usage > 0) ? $field->parameters->get( 'default_desc', '' ) : '';
		$default_cust1  = ($item->version == 0 || $cust1_usage > 0) ? $field->parameters->get( 'default_cust1', '' ) : '';
		$default_cust2  = ($item->version == 0 || $cust2_usage > 0) ? $field->parameters->get( 'default_cust2', '' ) : '';
		
		$usealt    = $field->parameters->get( 'use_alt', 1 ) ;
		$usetitle  = $field->parameters->get( 'use_title', 1 ) ;
		$usedesc   = $field->parameters->get( 'use_desc', 1 ) ;
		$usecust1  = $field->parameters->get( 'use_cust1', 0 ) ;
		$usecust2  = $field->parameters->get( 'use_cust2', 0 ) ;

		// *** Calculate some configuration flags

		// Display properties box
		$none_props = !$linkto_url && !$usealt && !$usetitle && !$usedesc && !$usecust1 && !$usecust2;

		// Inline uploaders flags
		$use_inline_uploaders = $image_source >= 0;
		$file_btns_position = (int) $field->parameters->get('file_btns_position', 0);


		// Intro / Full mode
		if ( $image_source == -1 )
		{
			$field->html = $use_ingroup ?
				array('<div class="alert alert-warning fc-small fc-iblock">Field is configured to use intro/full images, please disable use in group</div>') :
				'_INTRO_FULL_IMAGES_HTML_';
			return;
		}


		// Add JS /CSS for Media manager mode, and also check their PHP layouts overides exist
		static $mm_mode_common_js_added = false;
		if ( $image_source == -2 && !$mm_mode_common_js_added )
		{
			$pathDestFolder   = JPath::clean(JPATH_BASE.'/templates/'.$app->getTemplate().'/html/com_media/images/');
			$pathSourceFolder = JPath::clean(JPATH_ROOT.'/plugins/flexicontent_fields/image/media/tmpl/');
			
			// 1. Check DESTINATION folder
			if ( !JFolder::exists($pathDestFolder) && !JFolder::create($pathDestFolder) )
			{
			 echo '<span class="alert alert-warning"> Error, unable to create folder: '. $pathDestFolder.'</span>';
			}
			
			// 2. Copy override files
			$files = glob($pathSourceFolder."/*.*");
			foreach($files as $sourcepath)
			{
			 $dest_path = $pathDestFolder.'/'.basename($sourcepath);
			 if (!JFile::exists($dest_path) || filemtime($sourcepath) > filemtime($dest_path)) copy($sourcepath, $dest_path);
			}

			// We will use the mootools based media manager
			JHtml::_('behavior.framework', true);
			
			// Load the modal behavior script.
			JHtml::_('behavior.modal'/*, '.fc_image_field_mm_modal'*/);
			
			// Include media field JS, detecting different version of Joomla
			if( file_exists($path = JPATH_ROOT.'/media/media/js/mediafield-mootools.min.js') ) $media_js = 'media/mediafield-mootools.min.js';
			else if( file_exists($path = JPATH_ROOT.'/media/media/js/mediafield.min.js') ) $media_js = 'media/mediafield.min.js';
			else $media_js = 'media/mediafield.js';
			
			JHtml::_('script', $media_js, $mootools_framework = true, $media_folder_relative_path = true, false, false, true);
			
			// Tooltips for image path and image popup preview
			JHtml::_('behavior.tooltip', '.hasTipImgpath', array('onShow' => 'jMediaRefreshImgpathTip'));
			JHtml::_('behavior.tooltip', '.hasTipPreview', array('onShow' => 'jMediaRefreshPreviewTip'));
			$mm_mode_common_js_added = true;
		}


		// ***
		// *** Slider for files grid view
		// ***

		if ($use_inline_uploaders && $thumb_size_resizer)
		{
			flexicontent_html::loadFramework('nouislider');

			$resize_cfg = new stdClass();
			$resize_cfg->slider_name = 'fcfield-'.$field->id.'-uploader-thumb-size';
			$resize_cfg->element_selector = 'div.fcfield-'.$field->id.'-uploader-thumb-box';

			$resize_cfg->elem_container_selector = '.fcfieldval_container_'.$field->id.' div.fc-box';
			$resize_cfg->element_selector = '.fcfieldval_container_'.$field->id.' div.fc_file_uploader ul.plupload_filelist > li';

			$resize_cfg->element_class_prefix = 'thumb_';
			$resize_cfg->values = array(90, 120, 150, 200, 250);
			$resize_cfg->labels = array();

			$thumb_size_default = $app->input->cookie->get($resize_cfg->slider_name . '-val', $thumb_size_default, 'int');
			$resize_cfg->initial_pos = (int) array_search($thumb_size_default, $resize_cfg->values);
			if ($resize_cfg->initial_pos === false)
			{
				$resize_cfg->initial_pos = 1;
			}
			$thumb_size_default = $resize_cfg->values[$resize_cfg->initial_pos];
			$app->input->cookie->set($resize_cfg->slider_name . '-val', $thumb_size_default);

			foreach($resize_cfg->values as $value) $resize_cfg->labels[] = $value .'x'. $value;

			$element_classes = array();
			foreach($resize_cfg->values as $value) $element_classes[] = $resize_cfg->element_class_prefix . $value;

			$element_class_list = implode(' ', $element_classes);
			$step_values = '[' . implode(', ', $resize_cfg->values) . ']';
			$step_labels = '["' . implode('", "', $resize_cfg->labels) . '"]';
		
			$thumb_size_slider_cfg = "{
					name: '".$resize_cfg->slider_name."',
					step_values: ".$step_values.",
					step_labels: ".$step_labels.",
					initial_pos: ".$resize_cfg->initial_pos.",
					start_hidden: false,
					element_selector: '".$resize_cfg->element_selector."',
					element_class_list: '',
					element_class_prefix: '',
					elem_container_selector: '".$resize_cfg->elem_container_selector."',
					elem_container_class: '".$element_class_list."',
					elem_container_prefix: '".$resize_cfg->element_class_prefix."'
				}";

			$document->addScriptDeclaration("
			jQuery(document).ready(function()
			{
				fc_attachSingleSlider(".$thumb_size_slider_cfg.", ".$thumb_size_resizer.");
			});
			");
		}


		// Initialise property with default value
		if ( !$field->value )
		{
			$field->value = array();
			$field->value[0]['originalname'] = '';
			$field->value[0] = serialize($field->value[0]);
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// URL for modal fileselement view
		$filesElementURL =
			JUri::base(true).'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;layout=image'
				.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;targetid=%s_existingname&amp;thumb_w='.$preview_thumb_w.'&amp;thumb_h='.$preview_thumb_h.'&amp;autoassign='.$autoassign
				.'&amp;'.JSession::getFormToken().'=1';

		// Media manager mode
		if ( $image_source == -2 )
		{
			//$start_microtime = microtime(true);
			
			/*$xml_field = '<field name="'.$field->name.'" type="media" width="500" />';
			$xml_form = '<form><fields name="attribs"><fieldset name="attribs">'.$xml_field.'</fieldset></fields></form>';
			
			$jform = new JForm('flexicontent_field.image', array('control' => 'custom', 'load_data' => true));
			$jform->load($xml_form);
			$media = new JFormFieldMedia($jform);
			
			$value = str_replace('\\', '/', !empty($field->value[0])  ?  $field->value[0]  :  '');
			$media->setup(new SimpleXMLElement($xml_field), $value, '');
			$field->html = str_replace($elementid.'"', $elementid.'_0"',
				str_replace('name="'.$fieldname.'"', 'name="'.$fieldname.'[0]"', $media->input)
			);
			
			//$diff = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//echo sprintf('<br/>-- [Media manager field creation : %.3f s] ', $diff/1000000);
			return;*/
		}
		
		$js = "
			var fc_field_dialog_handle_".$field->id.";
		";
		$css = '';
		
		if (1) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup && $multiple) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($field->parameters->get('fields_box_placing', 1) ? "
					,start: function(e) {
						//jQuery(e.target).children().css('float', 'left');
						//fc_setEqualHeights(jQuery(e.target), 0);
					}
					,stop: function(e) {
						//jQuery(e.target).children().css({'float': 'none', 'min-height': '', 'height': ''});
					}
					" : '')."
				});
			});
			";
			
			// WARNING: bellow we also use $field->name which is different than $fieldname
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if(!remove_previous && (rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');
				
			".( $image_source == 0 ? "
				var has_select2     = newField.find('div.select2-container').length != 0;
				if (has_select2)     newField.find('div.select2-container').remove();
				" : "").
			"

				newField.find('input.hasvalue').val('');
				newField.find('input.hasvalue').attr('name','".$elementid."_'+uniqueRowNum".$field->id."+'_hasvalue');
				newField.find('input.hasvalue').attr('id','".$elementid."_'+uniqueRowNum".$field->id.");
				
				newField.find('input.originalname').val('');
				newField.find('input.originalname').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][originalname]');
				newField.find('input.originalname').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_originalname');
				
				newField.find('.existingname').val('');
				newField.find('.existingname').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][existingname]');
				newField.find('.existingname').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_existingname');
				
				newField.find('.fcimg_preview_msg').html(' ');
				newField.find('.fcimg_preview_msg').attr('name','".$elementid."_'+uniqueRowNum".$field->id."+'_fcimg_preview_msg');
				newField.find('.fcimg_preview_msg').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_fcimg_preview_msg');
				
			".( $image_source == 0 ? "
				if (has_select2)  newField.find('select.use_select2_lib').show().select2();
				" : "").
			"

				// Update uploader related data
				var fcUploader = newField.find('.fc_file_uploader');
				var upBTN;
				if (fcUploader.length)
				{
					// Update uploader attributes
					fcUploader.empty().hide();
					fcUploader.attr('id', fcUploader.attr('data-tagid-prefix') + uniqueRowNum".$field->id.");

					// Update button for toggling uploader
					upBTN = newField.find('.fc_files_uploader_toggle_btn');
					upBTN.attr('data-rowno',uniqueRowNum".$field->id.");

					mulupBTN = newField.find('.fc-files-modal-link.fc-up');
					mulupBTN.attr('data-href', " . "'" . str_replace('&amp;', '&', sprintf($filesElementURL,  $elementid . "_' + uniqueRowNum".$field->id ." + '")) . "');

					mulselBTN = newField.find('.fc-files-modal-link.fc-sel');
					mulselBTN.attr('data-href', " . "'" . str_replace('&amp;', '&', sprintf($filesElementURL,  $elementid . "_' + uniqueRowNum".$field->id ." + '")) . "');
				}

				// COPY an preview box
				var img_preview = newField.find('img.preview_image');
				if (img_preview.length)
				{
					var emptyImg = jQuery('<img class=\"preview_image\" id=\"\" src=\"data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=\" alt=\"Preview image\" />');
					emptyImg.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
					emptyImg.insertAfter( img_preview );
					img_preview.remove();
				}
				
				newField.find('table.fcimg_dbfile_tbl').parent('display', 'none');
				";
				
			if ($linkto_url) $js .= "
				newField.find('input.imglink').val('');
				newField.find('input.imglink').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][urllink]');
				newField.find('input.imglink').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_urllink');
				";
				
			if ($usealt) $js .= "
				newField.find('input.imgalt').val(".json_encode($default_alt).");
				newField.find('input.imgalt').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][alt]');
				newField.find('input.imgalt').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_alt');
				";
				
			if ($usetitle) $js .= "
				newField.find('input.imgtitle').val(".json_encode($default_title).");
				newField.find('input.imgtitle').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				newField.find('input.imgtitle').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				";
				
			if ($usedesc) $js .= "
				newField.find('textarea.imgdesc').val(".json_encode($default_desc).");
				newField.find('textarea.imgdesc').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][desc]');
				";
				
			if ($usecust1) $js .= "
				newField.find('input.imgcust1').val(".json_encode($default_cust1).");
				newField.find('input.imgcust1').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][cust1]');
				";
				
			if ($usecust2) $js .= "
				newField.find('input.imgcust2').val(".json_encode($default_cust2).");
				newField.find('input.imgcust2').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][cust2]');
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });
				
				// Set tooltip data placeholders
				newField.find('.media-preview').html('<span class=\"hasTipPreview\" title=\"&lt;strong&gt;Selected image.&lt;/strong&gt;&lt;br /&gt;&lt;div id=&quot;'+'".$elementid."_'+uniqueRowNum".$field->id."+'_existingname_preview_empty&quot; style=&quot;display:none&quot;&gt;No image selected.&lt;/div&gt;&lt;div id=&quot;'+'".$elementid."_'+uniqueRowNum".$field->id."+'_existingname_preview_img&quot;&gt;&lt;img src=&quot;&quot; alt=&quot;Selected image.&quot; id=&quot;'+'".$elementid."_'+uniqueRowNum".$field->id."+'_existingname_preview&quot; class=&quot;media-preview&quot; style=&quot; style=&quot;max-width:480px; max-height:360&quot; &quot; /&gt;&lt;/div&gt;\"><i class=\"icon-eye\"></i></span>');
				
				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Show tooltips
				var tipped_elements = newField.find('.hasTipImgpath, .hasTipPreview');
				tipped_elements.each(function() {
					var title = this.get('title');
					if (title) {
						var parts = title.split('::', 2);
						this.store('tip:title', parts[0]);
						this.store('tip:text', parts[1]);
					}
				});
				if ( tipped_elements.length ) {
					var imgpath_JTooltips = new Tips(jQuery(newField.get(0)).getElements('.hasTipImgpath'), { \"maxTitleChars\": 50, \"fixed\": false, \"onShow\": jMediaRefreshImgpathTip});
					var imgprev_JTooltips = new Tips(jQuery(newField.get(0)).getElements('.hasTipPreview'), { \"maxTitleChars\": 50, \"fixed\": false, \"onShow\": jMediaRefreshPreviewTip});
				}

				// Show uploader, if not 'animating visible' e.g. if not doing multi-add
				if (animate_visible && fcUploader.length) upBTN.click();

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only

				// return HTML Tag ID of field containing the file ID, needed when creating file rows to assign multi-fields at once
				return '".$elementid."_' + (uniqueRowNum".$field->id." - 1) + '_existingname';
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// When removing a field value we need to check if it had value and decrement the value counter
				var originalfftag = 'input.originalname';
				var existingfftag = '".($image_source == 0 ? "select" : "input")."' + '.existingname';
				var originalname = row.find( originalfftag ).val();
				var existingname = row.find( existingfftag ).val();
				var hasvalue = row.find( 'input.hasvalue').val();
				var valcounter = document.getElementById('".$elementid."');
				
				// IF a non-empty container is being removed ... get counter (which is optionally used as 'required' form element and empty it if is 1, or decrement if 2 or more)
				//if ( originalname != '' || existingname != '' )
				if (hasvalue!='')
				{
					valcounter.value = ( !valcounter.value || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
					//if(window.console) window.console.log ('valcounter.value: ' + valcounter.value);
				}
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}
				//if (window.console) window.console.log('valcounter: ' + valcounter.value);
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="' . $add_on_class . ' fcfield-delvalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="' . $add_on_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		}

		if (!$multiple)
		{
			$add_here = '';
			$move2 = '';
			$js .= '';
			$css .= '';
		}
		
		// Common JS/CSS
		$image_folder = JUri::root(true).'/'.$dir_url;
		$js .= "
			var fc_db_img_path='".$image_folder."';

			//function qmAssignFile".$field->id."(tagid, file, preview_url, keep_modal, file_original) {}
			function fcfield_assignImage".$field->id."(tagid, file, preview_url, keep_modal, file_original)
			{
				// Get TAG ID of the main form element of this field
				var ff_suffix = '_existingname';
				var elementid = tagid.replace(ff_suffix, '');

				// Get current has-value Flag and also set new value to the flag
				var valcounter = document.getElementById('".$elementid."');
				var hasvalue_obj = jQuery('#' + elementid );
				var hasValue = hasvalue_obj.val();
				hasvalue_obj.val(file ? '1' : '');

				// Increment/Make non-empty the form field used as value counter, so that is-required validation works
				if (file && !hasValue)
				{
					valcounter.value = valcounter.value==''  ?  1  :  parseInt(valcounter.value) + 1;
				}

				// Decrement/Make empty the form field used as value counter, so that is-required validation works
				else if (!file && hasValue)
				{
					valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
				}
				//if (window.console) window.console.log('valcounter: ' + valcounter.value);

				// Set existing value & Clear original value for both DB-mode & Folder-mode(s)
				jQuery('#' + elementid + '_existingname').val(file);
				jQuery('#' + elementid + '_originalname').val('');

				// Replace old preview image
				var preview_img_OLD = jQuery('#' + elementid + '_preview_image' );
				if (preview_img_OLD)
				{
					var box = preview_img_OLD.closest('.fcfieldval_container');
					var preview_img_NEW = preview_url != ''
						? '<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"'+preview_url+'\" alt=\"Preview image\" />'
						: '<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=\" alt=\"Preview image\" />';

					preview_img_NEW = jQuery(preview_img_NEW);
					preview_img_NEW.insertAfter( preview_img_OLD );
					preview_img_OLD.remove();

					if (keep_modal!=2)
					{
						preview_img_NEW.closest('.fcimg_preview_box').show();
						clearFieldUploader".$field->id."(box);
					}

					// Set new preview text too (a 'title')
					if (file) jQuery('#' + elementid + '_fcimg_preview_msg' ).html( !!file_original ? file_original : file );

					jQuery('#' + elementid + '_remove').removeAttr('checked').trigger('change');
				}

				// Close file select modal dialog
				if (!keep_modal && fc_field_dialog_handle_".$field->id.")
				{
					fc_field_dialog_handle_".$field->id.".dialog('close');
				}

				// Re-validate
				jQuery(valcounter).trigger('blur');
				//if (window.console) window.console.log('valcounter: ' + valcounter.value);
			}
			
			
			function clearFieldUploader".$field->id."(box)
			{
				var upload_container = box.find('.fc_file_uploader');
				var upload_instance = upload_container.data('plupload_instance');

				var upBTN = box.find('.fc_files_uploader_toggle_btn');
				if (upload_instance)
				{
					jQuery(upload_instance).data('fc_plupload_instance').clearUploader(upBTN.data('rowno'));
				}
				upBTN.removeClass('active');
				upload_container.hide();
			}


			function clearField".$field->id."(el, options)
			{
				var box = jQuery(el).closest('.fcfieldval_container');
				var hasValue = box.find('.hasvalue').val();
				var valcounter = document.getElementById('".$elementid."');
				//if (window.console) window.console.log('valcounter: ' + valcounter.value);
				options = options || {};
				options.hide_image = options.hide_image || false;
				options.keep_props = options.keep_props || false;

				if (options.hide_image)
				{
					box.find('.fcimg_preview_box').hide();
				}
				else
				{
					clearFieldUploader".$field->id."(box);

					box.find('.originalname').val('');
					box.find('.existingname').val('');
					box.find('.hasvalue').val('');
					box.find('.preview_image').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
					box.find('.fcimg_preview_msg').html(' ');
					box.find('.fcimg_preview_box').show();

					if (hasValue) valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
					//if (window.console) window.console.log('valcounter: ' + valcounter.value);
				}
				if (options.keep_props)
				{
					box.find('input, textarea').val('');
				}
			}


			var currElement".$field->id.";
			function incrementValCnt".$field->id."(el)
			{
				var box = jQuery('#'+currElement".$field->id.").closest('.fcfieldval_container');
				var hasValue = box.find('.hasvalue').val();
				box.find('.hasvalue').val('1');
				var valcounter = document.getElementById('".$elementid."');
				if (hasValue=='')
				{
					valcounter.value = valcounter.value==''  ?  '1'  :  parseInt(valcounter.value) + 1;
				}
				//if (window.console) window.console.log('valcounter: ' + valcounter.value);
			}


			function fcfield_FileFiltered_".$field->id."(uploader, file)
			{
			}


			function fcfield_FileUploaded_".$field->id."(uploader, file, result)
			{
				// Get 'fc_plupload' class instance from uploader
				var _this = jQuery(uploader).data('fc_plupload_instance');
				try {
					var response = eval(result.response);
				} catch(err) {
					var response = eval('(' + result.response + ')');
				}

				if (!!response.error)
				{
					alert(response.error.message);
					return;
				}

				//window.console.log(response.data);
			 	var file = response.data;
			 	file.targetid    = jQuery(uploader.settings.container).closest('li').find('.existingname').attr('id');				 	
			 	file.preview_url = jQuery(uploader.settings.container).find('.plupload_img_preview > img').attr('src');
			 	fcfield_assignImage".$field->id."(file.targetid, file.filename, file.preview_url, 2, file.filename_original);
			}
		";
		$css .='';

		flexicontent_html::loadFramework('flexi-lib');
		JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');

		$i = -1;  // Count DB values (may contain invalid entries)
		$n = 0;   // Count sortable records added (the verified values or a single empty record if no good values)
		$count_vals = 0;  // Count non-empty sortable records added
		$image_added = false;
		$skipped_vals = array();
		$uploadLimitsTxt = $this->getUploadLimitsTxt($field);

		// Handle file-ids as values
		$v = reset($field->value);
		if ((string)(int)$v == $v)
		{
			$files_data = $this->getFileData( $field->value, $published=false );
		}

		$field->html = array();  // Make sure this is an array

		foreach ($field->value as $index => $value)
		{
			// Compatibility for non-serialized values, e.g. Reload user input after form validation error
			// or for NULL values in a field group or file ids as values (minigallery legacy field)
			if ( !is_array($value) )
			{
				if ((string)(int)$value == $value)
				{
					if (isset($files_data[$value]))
					{
						$_filename = $files_data[$value]->filename_original ? $files_data[$value]->filename_original : $files_data[$value]->filename;
						$value = array('originalname' => $_filename);
					}
					else $value = array('originalname' => null);
				}
				else
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'originalname' => $value
					);
				}
				$field->value[$index] = $value;
			}
			$i++;

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			$value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';
			$image_subpath = !empty($value['existingname']) ? $value['existingname'] : $value['originalname'];  // existingname should be present only via form reloading

			// Check and rebuild thumbnails if needed, existing name mean newly selected image e.g. after form reload
			$rebuild_res = !$image_subpath
				? false
				: ($value['existingname'] ? true : plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item));

			// Check if rebuilding thumbnails failed (e.g. file has been deleted)  
			if ( !$rebuild_res )
			{
				// For non-empty value set a message when we have examined all values
				if ($image_subpath) $skipped_vals[] = $image_subpath;

				// Skip current value but add and an empty image container if :
				// (a) no other image exists or  (b) field is in a group
				if (!$use_ingroup && ($image_added || ($i+1) < count($field->value)) ) {
					continue;
				} else {
					// 1st value or empty value for fieldgroup position
					$image_subpath = '';
				}
			}

			// Increment count of images if thumbnailing was successful
			else
			{
				$count_vals++;
			}


			if ( $image_source == -2 || $image_source == -1 )
			{
				$fcimg_preview_msg = '';  // Joomla Media Manager / and Intro/Full use their own path preview
			}
			else
			{
				$fcimg_preview_msg = '
					<span type="text" class="fcimg_preview_msg"  id="'.$elementid_n.'_fcimg_preview_msg"  name="'.$elementid_n.'_fcimg_preview_msg">'.$image_subpath.'</span>
				';
			}


			$existingname = '';
			$select_existing = '';
			$pick_existing = '';
			$addExistingURL = sprintf($filesElementURL, $elementid_n);
			$addExistingURL_onclick = "fc_field_dialog_handle_".$field->id." = fc_showDialog(jQuery(this).attr('data-href'), 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".JText::_('FLEXI_SELECT_IMAGE', true)."'});";

			if ( $image_source >= 0 )
			{
				$existingname = '
					<input type="hidden" class="existingname fcfield_textval" id="'.$elementid_n.'_existingname" name="'.$fieldname_n.'[existingname]" value="'.(!empty($value['existingname']) ? $value['existingname'] : '').'" />
				';

				$select_existing = '';
			}

			else if ( $image_source == -2 )
			{
				$mm_id = $elementid_n.'_existingname';
				$img_path = $value['originalname'];
				$img_src  = ($img_path && file_exists(JPATH_ROOT . '/' . $img_path))  ?  JUri::root() . $img_path  :  '';
				$img_attr = array('id' => $mm_id . '_preview', 'class' => 'media-preview', 'style' => ' style="max-width:480px; max-height:360" ');
				$img = $img_src  ?  JHtml::image($img_src, JText::_('JLIB_FORM_MEDIA_PREVIEW_ALT'), $img_attr)  :  '';
				
				$previewImg = '<div id="' . $mm_id . '_preview_img"' . ($img_src ? '' : ' style="display:none"') . '>' . $img . '</div>';
				$previewImgEmpty = '<div id="' . $mm_id . '_preview_empty"' . ($img_src ? ' style="display:none"' : '') . '>'.JText::_('JLIB_FORM_MEDIA_PREVIEW_EMPTY').'</div>';
				
				$tooltip = $previewImgEmpty . $previewImg;
				$tooltip_options = array('title' => JText::_('JLIB_FORM_MEDIA_PREVIEW_SELECTED_IMAGE'), 'text' => '<i class="icon-eye"></i>', 'class' => 'hasTipPreview');
				
				$mm_link = 'index.php?option=com_media&amp;view=images&amp;layout=default_fc&amp;tmpl=component&amp;asset=com_flexicontent&amp;author=&amp;fieldid=\'+mm_id+\'&amp;folder=';
				$select_existing = '
				<div class="'.$input_grp_class.'">
					<div class="media-preview ' . $add_on_class . ' ">
						'.JHtml::tooltip($tooltip, $tooltip_options).'
					</div>
					<input type="text" name="'.$fieldname_n.'[existingname]" id="'.$mm_id.'" value="'.htmlspecialchars($img_path, ENT_COMPAT, 'UTF-8').'" readonly="readonly"
						class="existingname input-xxlarge field-media-input hasTipImgpath"  title="'.htmlspecialchars('<span id="TipImgpath"></span>', ENT_COMPAT, 'UTF-8').'" data-basepath="'.JUri::root().'"
					/>
					<a class="fc_image_field_mm_modal btn '.$tooltip_class.'" title="'.JText::_('FLEXI_SELECT_IMAGE').'" onclick="var mm_id=jQuery(this).parent().find(\'.existingname\').attr(\'id\'); currElement'.$field->id.'=mm_id; SqueezeBox.open(\''.$mm_link.'\', {size:{x: ((screen.width-120) > 1360 ? 1360 : (screen.width-120)), y: ((screen.height-220) > 800 ? 800 : (screen.height-220))}, handler: \'iframe\', onClose: function() { incrementValCnt'.$field->id.'(); } });  return false;">
						'.JText::_('FLEXI_SELECT').'
					</a>
					<a class="btn btn-small '.$tooltip_class.'" href="javascript:;" title="'.JText::_('FLEXI_CLEAR').'" onclick="var mm_id=jQuery(this).parent().find(\'.existingname\').attr(\'id\');  clearField'.$field->id.'(this); jInsertFieldValue(\'\', mm_id); return false;" >
						<i class="icon-remove"></i>
					</a>
				</div>
				';
			}

			// Calculate image preview link
			if ( $image_source == -2 || $image_source == -1 )
			{
				//$img_link  = JUri::root(true).'/'.$image_subpath;
				$img_link = false;  // Joomla Media Manager / and Intro/Full use a popup preview
			}
			else if ( $image_subpath )
			{
				// $image_source >= 0, if 'existingname' is set then it is propably a form reload
				$img_link = rawurlencode(
					JUri::root(true).'/'.$dir_url
					.($image_source ? '/item_'.$u_item_id . '_field_'.$field->id : '')
					.($item->id && empty($value['existingname'])  ?  '/m_' .$extra_prefix .basename($image_subpath)  :  '/original/' .$image_subpath)
				);

				if (isset($value['existingname']))
				{
					$ext = strtolower(flexicontent_upload::getExt($image_subpath));
					$_f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$img_link = str_replace('\\', '/', $img_link);
					$img_link = JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$img_link.'&amp;w='.$preview_thumb_w.'&amp;h='.$preview_thumb_h.'&amp;zc=1&amp;q=95&amp;ar=x';
				}
			}
			else
			{
				$img_link = '';
			}
			
			// Create the image preview using the image preview link
			if ( $img_link ) {
				$imgpreview = '<img class="preview_image" id="'.$elementid_n.'_preview_image" src="'.$img_link.'" alt="Preview image" />';
			}
			else if ( $img_link !== false ) {
				$imgpreview = '<img class="preview_image" id="'.$elementid_n.'_preview_image" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Preview image" />';
			}
			else {
				$imgpreview = '';
			}
			
			// originalname form field
			if ( $image_subpath )
			{
				$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="'.$value['originalname'].'" />';
				$originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="text" class="fc_hidden_value hasvalue '.($use_ingroup ? $required_class : '').'" value="1" />';
			} else {
				$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="" />';
				$originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="text" class="fc_hidden_value hasvalue '.($use_ingroup ? $required_class : '').'" value="" />';
			}


			if ($linkto_url) $urllink =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).'</label></td-->
					<td><input class="imglink" size="40" name="'.$fieldname_n.'[urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_LINKTO_URL' ), ENT_COMPAT, 'UTF-8').'"/></td>
				</tr>';
			if ($usealt) $alt =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_ALT' ).'</label></td-->
					<td><input class="imgalt" size="40" name="'.$fieldname_n.'[alt]" value="'.(isset($value['alt']) ? $value['alt'] : $default_alt).'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_ALT' ), ENT_COMPAT, 'UTF-8').'"/></td>
				</tr>';
			if ($usetitle) $title =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_TITLE' ).' <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</label></td-->
					<td><input class="imgtitle" size="40" name="'.$fieldname_n.'[title]" value="'.(isset($value['title']) ? $value['title'] : $default_title).'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_TITLE' ), ENT_COMPAT, 'UTF-8').'"/></td>
				</tr>';
			if ($usedesc) $desc =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_DESC' ).' <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</label></td-->
					<td><textarea class="imgdesc" name="'.$fieldname_n.'[desc]" rows="3" cols="24" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_DESC' ), ENT_COMPAT, 'UTF-8').'">'.(isset($value['desc']) ? $value['desc'] : $default_desc).'</textarea></td>
				</tr>';
			if ($usecust1) $cust1 =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_IMG_CUST1' ).'</label></td-->
					<td><input class="imgcust1" size="40" name="'.$fieldname_n.'[cust1]" value="'.(isset($value['cust1']) ? $value['cust1'] : $default_cust1).'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_IMG_CUST1' ), ENT_COMPAT, 'UTF-8').'"/></td>
				</tr>';
			if ($usecust2) $cust2 =
				'<tr>
					<!--td class="key"><label class="fc-prop-lbl">'.JText::_( 'FLEXI_FIELD_IMG_CUST2' ).'</label></td-->
					<td><input class="imgcust2" size="40" name="'.$fieldname_n.'[cust2]" value="'.(isset($value['cust2']) ? $value['cust2'] : $default_cust2).'" type="text" placeholder="'.htmlspecialchars(JText::_( 'FLEXI_FIELD_IMG_CUST2' ), ENT_COMPAT, 'UTF-8').'"/></td>
				</tr>';
			
			// DB-mode needs a 'pick_existing_n'
			if ( $image_source == 0 )
			{
				$pick_existing_n = $pick_existing ? str_replace('__FORMFLDNAME__', $fieldname_n.'[existingname]', $pick_existing) : '';
				$pick_existing_n = $pick_existing ? str_replace('__FORMFLDID__', $elementid_n.'_existingname', $pick_existing_n) : '';
			}

			if ($use_inline_uploaders)
			{
				$uploader_html = JHtml::_('fcuploader.getUploader', $field, $u_item_id, null, $n,
					array(
					'container_class' => 'fc_inline_uploader fc_uploader_thumbs_view fc_compact_uploader fc_auto_uploader fc-box thumb_'.$thumb_size_default,
					'upload_maxcount' => 1,
					'autostart_on_select' => true,
					'refresh_on_complete' => false,
					'thumb_size_default' => $thumb_size_default,
					'toggle_btn' => array(
						'class' => $add_on_class.' fcfield-uploadvalue' . $font_icon_class,
						'text' => '',
						'onclick' => 'var box = jQuery(this).closest(\'.fcfieldval_container\').find(\'.fcimg_preview_box\'); box.parent().find(\'.fc_file_uploader\').is(\':visible\') ? box.show() : box.hide(); box.is(\':visible\') ? jQuery(this).removeClass(\'active\') : jQuery(this).addClass(\'active\'); ',
						'action' => null
					),
					'thumb_size_slider_cfg' => ($thumb_size_resizer ? $thumb_size_slider_cfg : 0),
					'resize_cfg' => ($thumb_size_resizer ? $resize_cfg : 0),
					'handle_FileFiltered' => 'fcfield_FileFiltered_'.$field->id,
					'handle_FileUploaded' => 'fcfield_FileUploaded_'.$field->id
					)
				);

				$multi_icon = $form_font_icons ? ' <span class="icon-stack"></span>' : '<span class="pages_stack"></span>';
				$btn_classes = 'fc-files-modal-link ' . $add_on_class . ' ' . $font_icon_class;
				$uploader_html->multiUploadBtn = '';  /*'
					<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-up fcfield-uploadvalue multi">
						&nbsp; ' . $multi_icon . ' ' . ($file_btns_position==2 ? JText::_('FLEXI_UPLOAD') : '') . '
					</span>';*/
				$uploader_html->myFilesBtn = '
					<span data-href="'.$addExistingURL.'" onclick="'.$addExistingURL_onclick.'" class="'.$btn_classes.' fc-sel fcfield-selectvalue multi">
						&nbsp; ' . $multi_icon . ' ' . ($file_btns_position==2 ? JText::_('FLEXI_MY_FILES') : '') . '
					</span>';
				$uploader_html->clearBtn = '
					<span class="' . $add_on_class . ' fcfield-clearvalue ' . $font_icon_class . '" title="'.JText::_('FLEXI_CLEAR').'" onclick="clearField'.$field->id.'(this);">
					</span>';
			}

			$field->html[] = '
			'.(!$multiple ? '' : '
				'.(!$none_props ? '<div class="fcclear"></div>' : '').'
				'.($use_ingroup || !$multiple ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
			')
			.($use_inline_uploaders && !$file_btns_position ?'
			<div class="'.$input_grp_class.' fc-xpended-btns" style="margin-left: 8px !important;">
				'.$uploader_html->toggleBtn.'
				'.$uploader_html->multiUploadBtn.'
				'.$uploader_html->myFilesBtn.'
				'.$uploader_html->clearBtn.'		
			</div>
			' : '')
			.($use_inline_uploaders && $file_btns_position ? '
			<div class="fcclear"></div>
			<div class="btn-group" style="margin: 4px 0 16px 0; display: inline-block;">
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$uploader_html->toggleBtn.'
					'.$uploader_html->multiUploadBtn.'
					'.$uploader_html->myFilesBtn.'
					'.$uploader_html->clearBtn.'
				</div>
			</div>
			' : '') . '
			'.$originalname.'
			<div class="fcclear"></div>
			'.$existingname.'
			<div class="fcclear"></div>
			
			'.($image_source == -2 || $image_source == -1  ?  // Do not add image preview box if using Joomla Media Manager (or intro/full mode)
				$select_existing.'
				<div class="fcclear"></div>
			' : '
				<div class="fcimg_preview_box fc-box thumb_'.$thumb_size_default.'">
					'.$imgpreview.'
					'.$fcimg_preview_msg.'
					<div class="fcclear"></div>
				'.$select_existing.'
				</div>
				'.(!empty($uploader_html) ? $uploader_html->container : '').'
			').'
			'

			.(($linkto_url || $usealt || $usetitle || $usedesc || $usecust1 || $usecust2) ?
			'
			<div class="fcimg_value_props">
				<table class="fc-form-tbl fcinner fccompact">
					'.@ $urllink.'
					'.@ $alt.'
					'.@ $title.'
					'.@ $desc.'
					'.@ $cust1.'
					'.@ $cust2.'
				</table>
			</div>'
			: '');
			
			$n++;
			$image_added = true;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}


		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if (1 || $multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}

		// This is field HTML that is created regardless of values
		$non_value_html = '<input id="'.$elementid.'" class="fc_hidden_value '.($use_ingroup ? '' : $required_class).'" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'" />';
		if ($use_ingroup)
		{
			$field->html[-1] = $non_value_html;
			if ($use_inline_uploaders && $uploader_html->thumbResizer)
			{
				$field->html[-1] = $uploader_html->thumbResizer . ' ' . $field->html[-1];
			}
		}
		else
		{
			$field->html .= $non_value_html;
			if ($use_inline_uploaders && $uploader_html->thumbResizer)
			{
				$field->html = $uploader_html->thumbResizer . ' ' . $field->html;
			}
		}

		if ( count($skipped_vals) )
		{
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_IMAGE_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;

		$app  = JFactory::getApplication();
		
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		
		// ***
		// *** One time initialization 
		// ***

		static $initialized = null;
		static $app, $document, $option;
		static $isMobile, $isTablet, $useMobile;
		if ($initialized===null)
		{
			$app       = JFactory::getApplication();
			$document	 = JFactory::getDocument();
			$option    = $app->input->get('option', '', 'cmd');
			$cparams   = JComponentHelper::getParams( 'com_flexicontent' );
			$configured_file_path = $cparams->get('file_path', 'components/com_flexicontent/uploads');
			
			// Get isMobile / isTablet Flags

			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}


		// ***
		// *** Static FLAGS indicating if JS libs were loaded
		// ***

		static $multiboxadded = false;
		static $fancyboxadded = false;
		static $gallerifficadded = false;
		static $elastislideadded = false;
		static $photoswipeadded  = false;


		// ***
		// *** Current view variable / FLAGs
		// ***

		$realview = $app->input->get('view', 'item', 'cmd');
		$view = $app->input->get('flexi_callview', $realview, 'cmd');
		$isItemsManager = $app->isAdmin() && $realview=='items' && $option=='com_flexicontent';
		$isSite = $app->isSite();
		$is_FE_html_view = $app->input->get('format', 'html', 'cmd') == 'html' && $isSite;
		
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\', '/', $dir);
		
		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		$usealt      = $field->parameters->get( 'use_alt', 1 ) ;
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$default_alt = ($alt_usage == 2)  ?  $field->parameters->get( 'default_alt', '' ) : '';
		
		$usetitle      = $field->parameters->get( 'use_title', 1 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		
		$usedesc       = $field->parameters->get( 'use_desc', 1 ) ;
		$desc_usage    = $field->parameters->get( 'desc_usage', 0 ) ;
		$default_desc  = ($desc_usage == 2)  ?  $field->parameters->get( 'default_desc', '' ) : '';
		
		$usecust1      = $field->parameters->get( 'use_cust1', 0 ) ;
		$cust1_usage   = $field->parameters->get( 'cust1_usage', 0 ) ;
		$default_cust1 = ($cust1_usage == 2)  ?  JText::_($field->parameters->get( 'default_cust1', '' )) : '';
		
		$usecust2      = $field->parameters->get( 'use_cust2', 0 ) ;
		$cust2_usage   = $field->parameters->get( 'cust2_usage', 0 ) ;
		$default_cust2 = ($cust2_usage == 2)  ?  JText::_($field->parameters->get( 'default_cust2', '' )) : '';
		
		// Separators / enclosing characters
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 0 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}


		// ***
		// *** SETUP VALUES: retrieve, verify, load defaults, etc
		// ***

		$values = $values ? $values : $field->value;
		
		// Intro-full mode get their values from item's parameters
		if ( $image_source == -1 )
		{
			$values = array();
			
			// Use 'intro' image in multi-item listings
			$_image_name = $view!='item' ? 'intro' : 'fulltext';
			
			if ( $item->images )
			{
				if (!is_object($item->images))
				{
					try
					{
						$item->images = new JRegistry($item->images);
					}
					catch (Exception $e)
					{
						$item->images = flexicontent_db::check_fix_JSON_column('images', 'content', 'id', $item->id);
					}
				}
				
				$_image_path = $item->images->get('image_'.$_image_name, '');
				
				// Use 'fulltext' image if 'intro' image is empty
				if (!$_image_path && $_image_name=='intro')
				{
					$_image_name = 'fulltext';
					$_image_path = $item->images->get('image_'.$_image_name, '');
				}
				
				$image_IF = array();
				// field attributes (mode-specific)
				$image_IF['image_size']  = $_image_name;
				$image_IF['image_path']  = $_image_path;
				// field attributes (value)
				$image_IF['originalname'] = basename($_image_path);
				$image_IF['alt']   = $item->images->get('image_'.$_image_name.'_alt', '');
				$image_IF['title'] = $item->images->get('image_'.$_image_name.'_alt', '');
				$image_IF['desc']  = $item->images->get('image_'.$_image_name.'_caption', '');
				$image_IF['cust1'] = '';
				$image_IF['cust2'] = '';
				$image_IF['urllink'] = '';
				$values = array(serialize($image_IF));
				//echo "<pre>"; print_r($image_IF); echo "</pre>"; exit;
			}
		}


		// Check for deleted image files or image files that cannot be thumbnailed,
		// rebuilding thumbnails as needed, and then assigning checked values to a new array
		$usable_values = array();
		if ($values)
		{
			// Handle file-ids as values
			$v = reset($values);
			if ((string)(int)$v == $v)
			{
				$files_data = $this->getFileData( $values, $published=false );
			}

			foreach ($values as $index => $value)
			{
				// Non-serialized values, e.g file ids as values (minigallery legacy field)
				if ((string)(int)$value == $value)
				{
					if (isset($files_data[$value]))
					{
						$_filename = $files_data[$value]->filename_original
							? $files_data[$value]->filename_original
							: $files_data[$value]->filename;
						$value = array('originalname' => $_filename);
					}
					else
					{
						$value = array('originalname' => null);
					}
					$values[$index] = serialize($value);
				}

				// Serialized value
				else
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'originalname' => $value
					);
				}

				// Try to check / rebuild thumbnails
				if ( plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item) )
				{
					$usable_values[] = $values[$index];
				}

				// In case of failed thumbnailing, add empty value if in fieldgroup
				else if ($is_ingroup)
				{
					$usable_values[] = array('originalname' => null);
				}
			}
		}
		$values = & $usable_values;


		// Allow for thumbnailing of the default image
		$field->using_default_value = false;
		if ( !count($values) )
		{
			// Create default image to be used if  (a) no image assigned  OR  (b) images assigned have been deleted
			$default_image = $field->parameters->get( 'default_image', '');
			if ( $default_image )
			{
				$image_DF = array();
				// field attributes (default value specific)
				$image_DF['default_image'] = $default_image;  // holds complete relative path and indicates that it is default image for field
				// field attributes (value)
				$image_DF['originalname'] = basename($default_image);
				$image_DF['alt'] = $default_alt;
				$image_DF['title'] = $default_title;
				$image_DF['desc'] = $default_desc;
				$image_DF['cust1'] = $default_cust1;
				$image_DF['cust2'] = $default_cust2;
				$image_DF['urllink'] = '';
				
				// Create thumbnails for default image
				if ( plgFlexicontent_fieldsImage::rebuildThumbs($field, $image_DF, $item) )
				{
					$values = array(serialize($image_DF));
				}

				// Also default image can (possibly) be used across multiple fields, so set flag to add field id to filenames of thumbnails
				$multiple_image_usages = true;
				$extra_prefix = 'fld'.$field->id.'_';
				$field->using_default_value = true;
			}
		}


		// ***
		// *** Check for no values and no default value, and return empty display
		// ***

		if ( empty($values) )
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		// Assign (possibly) altered value array to back to the field
		$field->value = $values;  // This is not done for onDisplayFieldValue, TODO check if this is needed


		// ***
		// *** Default display method depends on view
		// ***

		if ($prop=='display' && ($view==FLEXI_ITEMVIEW || $view=='category'))
		{
			$_method = $view==FLEXI_ITEMVIEW ?
				$field->parameters->get( 'default_method_item',  'display' ) :
				$field->parameters->get( 'default_method_cat',  'display_single_total') ;
		}
		else
		{
			$_method = $prop;
		}
		$cat_link_single_to = $field->parameters->get( 'cat_link_single_to', 1) ;

		// Calculate some flags, SINGLE image display and Link-to-Item FLAG
		$isSingle = isset(self::$single_displays[$_method]);
		$linkToItem = $_method == 'display_link' || $_method == 'display_single_link' || $_method == 'display_single_total_link' || ($view!='item' && $cat_link_single_to && $isSingle);


		// ***
		// *** JS gallery configuration
		// ***

		$usepopup   = (int)$field->parameters->get( 'usepopup',  1 ) ; // use JS gallery
		$popuptype  = (int)$field->parameters->get( 'popuptype', 1 ) ; // JS gallery type

		// Different for mobile clients
		$popuptype_mobile = (int)$field->parameters->get( 'popuptype_mobile', $popuptype ) ;  // this defaults to desktop when empty
		$popuptype = $useMobile ? $popuptype_mobile : $popuptype;

		// Enable/Disable GALLERY JS according to current view and according to other parameters
		$popupinview = $field->parameters->get('popupinview', array(FLEXI_ITEMVIEW,'category','backend'));
		$popupinview  = FLEXIUtilities::paramToArray($popupinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$popupinview)) $usepopup = 0;
		if ($view=='category' && !in_array('category',$popupinview)) $usepopup = 0;
		if ($view=='module' && !in_array('module',$popupinview)) $usepopup = 0;
		if ($isItemsManager && !in_array('backend',$popupinview)) $usepopup = 0;

		// Enable/Disable GALLERY JS if linking to item view
		if ($linkToItem) $usepopup = 0;

		// Only allow multibox and fancybox in items manager, in other cases force fancybox
		if ($isItemsManager && !in_array($popuptype, array(1,4))) $popuptype = 4;

		// Displays that need special container are not allowed when field in a group, force fancybox
		$no_container_needed = array(1,2,3,4,6);
		if ( $is_ingroup && !in_array($popuptype, $no_container_needed) ) $popuptype = 4;

		// Optionally group images from all image fields of current item ... or of all items in view too
		$grouptype  = $field->parameters->get( 'grouptype', 1 ) ;
		//$grouptype = $multiple ? 0 : $grouptype;  // Field in gallery mode: Force grouping of images per field (current item)

		// Needed by some js galleries
		$thumb_w_s = $field->parameters->get( 'w_s', 120);
		$thumb_h_s = $field->parameters->get( 'h_s',  90);
		$wl = $field->parameters->get( 'w_l', 800 );
		$hl = $field->parameters->get( 'h_l', 600 );


		// ***
		// *** Hovering ToolTip configuration
		// ***

		$uselegend  = $field->parameters->get( 'uselegend', 1 ) ;
		$tooltip_class = 'hasTooltip';

		// Enable/disable according to current view
		$legendinview = $field->parameters->get('legendinview', array(FLEXI_ITEMVIEW,'category'));
		$legendinview  = FLEXIUtilities::paramToArray($legendinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$legendinview)) $uselegend = 0;
		if ($view=='category' && !in_array('category',$legendinview)) $uselegend = 0;
		if ($isItemsManager && !in_array('backend',$legendinview)) $uselegend = 0;

		// Load the tooltip library according to configuration, FLAG is an array to have a different check per field ID
		// This is needed ONLY for fields that also have a configuration parameter, for this field is NEEDED 
		static $tooltips_added = array();
		if ( empty($tooltips_added[$field->id]) )
		{
			$add_tooltips = JComponentHelper::getParams( 'com_flexicontent' )->get('add_tooltips', 1);
			if ($add_tooltips && $uselegend) JHtml::_('bootstrap.tooltip');
			$tooltips_added[$field->id] = true;
		}


		// ***
		// *** Title/Description in inline thumbnails
		// ***

		$showtitle = $field->parameters->get( 'showtitle', 0 ) ;
		$showdesc  = $field->parameters->get( 'showdesc', 0 ) ;


		// ***
		// *** Link to URL configuration
		// ***

		$linkto_url	= $field->parameters->get('linkto_url',0);
		$url_target = $field->parameters->get('url_target','_self');
		$isLinkToPopup = $linkto_url && ($url_target=='multibox' || $url_target=='fancybox');

		// Force opening in new window in backend, if URL target is _self
		if ($isItemsManager && $url_target=='_self') $url_target = "_blank";

		// Only allow multibox (and TODO: add fancybox) when linking to URL, in other cases force fancybox
		if ($isLinkToPopup && $url_target=='multibox') $popuptype = 1;
		if ($isLinkToPopup && $url_target=='fancybox') $popuptype = 4;
		else if ($linkto_url) $usepopup = 0;


		// ***
		// *** Social website sharing configuration
		// ***

		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpthumbsize= $field->parameters->get('ogpthumbsize', 2);


		// ***
		// *** Create thumb paths and thumb base URL
		// ***

		// *** Extra thumbnails sub-folder for various
		
		// Default value
		if ( $field->using_default_value )
			$extra_folder = '';

		// Intro-full image mode
		else if ( $image_source == -1 )
			$extra_folder = 'intro_full';

		// Media manager mode
		else if ( $image_source == -2 )
			$extra_folder = 'mediaman';

		// Various Folder-mode(s) ... TODO: $image_source > 1
		else if ( $image_source >= 1 )
			$extra_folder = 'item_'.$u_item_id.'_field_'.$field->id;  // Folder-mode 1

		// DB-mode
		else
			$extra_folder = '';

		// Create thumbs/image Folder and URL paths
		$thumb_folder  = JPATH_SITE .DS. JPath::clean( $dir .($extra_folder ? DS.$extra_folder : '') );
		$thumb_urlpath = $dir_url .($extra_folder ? '/'. $extra_folder : '');
		
		if ( $field->using_default_value )
		{
			// default image of this field, these are relative paths up to site root
			$orig_urlpath  = str_replace('\\', '/', dirname($image_DF['default_image']));
		}
		else
		{
			// Intro-full image mode, image names are relative paths up to the site root
			if ( $image_source == -1 )
			{
				$orig_urlpath  = str_replace('\\', '/', dirname($image_IF['image_path']));
			}

			// Media manager mode, image names are paths relative to the site root
			else if ( $image_source == -2 )
			{
				$orig_urlpath = array();  // calculate later inside value loop
			}

			// Various Folder-mode(s) ... TODO: $image_source > 1
			else if ( $image_source >= 1 )
			{
				$orig_urlpath  = $thumb_urlpath . '/original';  // Folder-mode 1
			}

			// DB-mode
			else
			{
				$orig_urlpath = str_replace('\\', '/', $configured_file_path);
			}
		}


		// ***
		// *** Load the configured (or the forced) JS gallery
		// ***

		// Do not load JS, for value only displays
		if ( !isset(self::$value_only_displays[$prop]) )
		{
			// MultiBox maybe added in extra cases besides popup
			// (a) in Item manager, (b) When linking to URL in popup target
			$view_allows_mb  = $isItemsManager || $is_FE_html_view;
			$config_needs_mb = $isLinkToPopup  || ($usepopup && $popuptype == 1);
			if ( $view_allows_mb && $config_needs_mb )
			{
				if (!$multiboxadded)
				{
					flexicontent_html::loadFramework('jmultibox');  //echo $field->name.": multiboxadded";
					$js = "
					jQuery(document).ready(function() {
						jQuery('a.mb').jmultibox({
							initialWidth: 250,  //(number) the width of the box when it first opens while loading the item. Default: 250
							initialHeight: 250, //(number) the width of the box when it first opens while loading the item. Default: 250
							container: document.body, //(element) the element that the box will take it coordinates from. Default: document.body
							contentColor: '#000', //(string) the color of the content area inside the box. Default: #000
							showNumbers: ".($isItemsManager ? 'false' : 'true').",    //(boolean) show the number of the item e.g. 2/10. Default: true
							showControls: ".($isItemsManager ? 'false' : 'true').",   //(boolean) show the navigation controls. Default: true
							descClassName: 'multiBoxDesc',  //(string) the classname of the divs that contain the description for the item. Default: false
							descMinWidth: 400,     //(number) the min width of the description text, useful when the item is small. Default: 400
							descMaxWidth: 600,     //(number) the max width of the description text, so it can wrap to multiple lines instead of being on a long single line. Useful when the item is large. Default: 600
							movieWidth: 576,    //(number) the default width of the box that contains content of a movie type. Default: 576
							movieHeight: 324,   //(number) the default height of the box that contains content of a movie type. Default: 324
							offset: {x: 0, y: 0},  //(object) containing x & y coords that adjust the positioning of the box. Default: {x:0, y:0}
							fixedTop: false,       //(number) gives the box a fixed top position relative to the container. Default: false
							path: '',            //(string) location of the resources files, e.g. flv player, etc. Default: ''
							openFromLink: true,  //(boolean) opens the box relative to the link location. Default: true
							opac:0.7,            //(decimal) overlay opacity Default: 0.7
							useOverlay:false,    //(boolean) use a semi-transparent background. Default: false
							overlaybg:'01.png',  //(string) overlay image in 'overlays' folder. Default: '01.png'
							onOpen:function(){},   //(object) a function to call when the box opens. Default: function(){} 
							onClose:function(){},  //(object) a function to call when the box closes. Default: function(){} 
							easing:'swing',        //(string) effect of jQuery Default: 'swing'
							useratio:false,        //(boolean) windows size follows ratio. (iframe or Youtube) Default: false
							ratio:'90'             //(number) window ratio Default: '90'
						});
					});";
					$document->addScriptDeclaration($js);
					
					$multiboxadded = true;
				}
			}
			
			// Regardless if above has added multibox , we will add a different JS gallery if so configured because it maybe needed
			if ( !$is_FE_html_view )
			{
				// Is backend OR it is not an HTML view, do not add any JS library
			}

			else if ( $usepopup )
			{
				switch ($popuptype)
				{
				// Add Fancybox image popup
				case 4:
					if (!$fancyboxadded) {
						$fancyboxadded = true;
						flexicontent_html::loadFramework('fancybox');
					}
					break;
				
				// Add Galleriffic inline slideshow gallery
				case 5:
					$inline_gallery = 1; // unused
					
					if (!$gallerifficadded)
					{
						flexicontent_html::loadFramework('galleriffic');
						$gallerifficadded = true;
						
						$js = "
						//document.write('<style>.noscript { display: none; }</style>');
						jQuery(document).ready(function() {
							// We only want these styles applied when javascript is enabled
							jQuery('div.navigation').css({'width' : '150px', 'float' : 'left'});
							jQuery('div.content').css({'display' : 'inline-block', 'float' : 'none'});
			
							// Initially set opacity on thumbs and add
							// additional styling for hover effect on thumbs
							var onMouseOutOpacity = 0.67;
							jQuery('#gf_thumbs ul.thumbs li').opacityrollover({
								mouseOutOpacity:   onMouseOutOpacity,
								mouseOverOpacity:  1.0,
								fadeSpeed:         'fast',
								exemptionSelector: '.selected'
							});
							
							// Initialize Advanced Galleriffic Gallery
							jQuery('#gf_thumbs').galleriffic({
								/*enableFancybox: true,*/
								delay:                     2500,
								numThumbs:                 4,
								preloadAhead:              10,
								enableTopPager:            true,
								enableBottomPager:         true,
								maxPagesToShow:            20,
								imageContainerSel:         '#gf_slideshow',
								controlsContainerSel:      '#gf_controls',
								captionContainerSel:       '#gf_caption',
								loadingContainerSel:       '#gf_loading',
								renderSSControls:          true,
								renderNavControls:         true,
								playLinkText:              'Play Slideshow',
								pauseLinkText:             'Pause Slideshow',
								prevLinkText:              '&lsaquo; Previous Photo',
								nextLinkText:              'Next Photo &rsaquo;',
								nextPageLinkText:          'Next &rsaquo;',
								prevPageLinkText:          '&lsaquo; Prev',
								enableHistory:             false,
								autoStart:                 false,
								syncTransitions:           true,
								defaultTransitionDuration: 900,
								onSlideChange:             function(prevIndex, nextIndex) {
									// 'this' refers to the gallery, which is an extension of jQuery('#gf_thumbs')
									this.find('ul.thumbs').children()
										.eq(prevIndex).fadeTo('fast', onMouseOutOpacity).end()
										.eq(nextIndex).fadeTo('fast', 1.0);
								},
								onPageTransitionOut:       function(callback) {
									this.fadeTo('fast', 0.0, callback);
								},
								onPageTransitionIn:        function() {
									this.fadeTo('fast', 1.0);
								}
							});
						});
							";
						$document->addScriptDeclaration($js);
					}
					break;
				
				// Add Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
				case 7:
					if (!$elastislideadded)
					{
						flexicontent_html::loadFramework('elastislide');
						$elastislideadded = true;
					}
					$uid = 'es_'.$field->name."_fcitem".$item->id;
					$js = file_get_contents(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'elastislide'.DS.'js'.DS.'gallery_tmpl.js');
					$js = str_replace('unique_gal_id', $uid, $js);
					$js = str_replace('__thumb_width__', $field->parameters->get( 'w_s', 120 ), $js);
					$document->addScriptDeclaration($js);
					
					$document->addCustomTag('
					<script id="img-wrapper-tmpl_'.$uid.'" type="text/x-jquery-tmpl">	
						<div class="rg-image-wrapper">
							{{if itemsCount > 1}}
								<div class="rg-image-nav">
									<a href="javascript:;" class="rg-image-nav-prev">'.JText::_('FLEXI_PREVIOUS').'</a>
									<a href="javascript:;" class="rg-image-nav-next">'.JText::_('FLEXI_NEXT').'</a>
								</div>
							{{/if}}
							<div class="rg-image"></div>
							<div class="rg-loading"></div>
							<div class="rg-caption-wrapper">
								<div class="rg-caption" style="display:none;">
									<p></p>
								</div>
							</div>
						</div>
					</script>
					');
					break;
				
				// Add PhotoSwipe popup carousel gallery
				case 8:
					if (!$photoswipeadded)
					{
						flexicontent_html::loadFramework('photoswipe');
						$photoswipeadded = true;
					}
					break;
				}
			}
		}


		// ***
		// *** Initialize value handling arrays and loop's common variables
		// ***

		$field->{$prop} = array();
		$field->thumbs_src['backend'] = array();
		$field->thumbs_src['small']   = array();
		$field->thumbs_src['medium']  = array();
		$field->thumbs_src['large']   = array();
		$field->thumbs_src['original']= array();
		$field->{"display_backend_src"} = array();
		$field->{"display_small_src"}   = array();
		$field->{"display_medium_src"}  = array();
		$field->{"display_large_src"}   = array();
		$field->{"display_original_src"}= array();
		
		$cust1_label = JText::_('FLEXI_FIELD_IMG_CUST1');
		$cust2_label = JText::_('FLEXI_FIELD_IMG_CUST2');
		
		// Decide thumbnail to use
		$thumb_size = 0;
		if ($isItemsManager)
			$thumb_size = -1;
		else if ($view == 'category')
			$thumb_size = $field->parameters->get('thumbincatview',1);
		else if ($view == FLEXI_ITEMVIEW)
			$thumb_size = $field->parameters->get('thumbinitemview',2);

		// Cutoff title to a max length
		$alt_image_prefix = flexicontent_html::striptagsandcut($item->title, 60) . ' ' . JText::_('FLEXI_IMAGE') . ' ';


		// ***
		// *** The values loop
		// ***

		$value_list_has_containers = ($popuptype == 5 || $popuptype == 7);
		$i = -1;
		foreach ($values as $n => $value)
		{
			// Include common layout code for preparing values, but you may copy here to customize
			//$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
			//if ($result === _FC_CONTINUE_) continue;
			//if ($result === _FC_BREAK_) break;

			// Unserialize value's properties and check for empty original name property
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array(
				'originalname' => $value
			);

			$image_subpath = $value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';

			// Skip empty value, adding an empty placeholder if field inside in field group
			if ( !strlen($image_subpath) )
			{
				if ( $is_ingroup )
				{
					$field->{$prop}[]	= '';
				}
				continue;
			}
			$i++;
			
			// Some types contain sub-path together with the image name (relative to joomla folder)
			if ( is_array($orig_urlpath) )
			{
				$orig_urlpath[$i] = str_replace('\\', '/', dirname($image_subpath));
			}
			
			// In other cases check for sub-path relative to the calculated 'original path'
			else
			{
				if ($dirname = dirname($image_subpath))
				{
					$orig_urlpath .=  '/'. str_replace('\\', '/', $dirname);
				}
			}

			$image_name = basename($image_subpath);


			// ***
			// Create thumbnails urls, note thumbnails have already been verified above
			// ***

			// Optional properties
			$title	= ($usetitle && isset($value['title'])) ? $value['title'] : '';
			$alt	= ($usealt && isset($value['alt'])) ? $value['alt'] : $alt_image_prefix . ($n + 1);
			$desc	= ($usedesc && isset($value['desc'])) ? $value['desc'] : '';
			
			// Optional custom properties
			$cust1	= ($usecust1 && isset($value['cust1'])) ? $value['cust1'] : '';
			$desc .= $cust1 ? $cust1_label.': '.$cust1 : '';  // ... Append custom properties to description
			$cust2	= ($usecust2 && isset($value['cust2'])) ? $value['cust2'] : '';
			$desc .= $cust2 ? $cust2_label.': '.$cust2 : '';  // ... Append custom properties to description
			
			// HTML encode output
			$title= htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
			$alt	= htmlspecialchars($alt, ENT_COMPAT, 'UTF-8');
			$desc	= htmlspecialchars($desc, ENT_COMPAT, 'UTF-8');
			
			$srcb = $thumb_urlpath . '/b_' .$extra_prefix. $image_name;  // backend
			$srcs = $thumb_urlpath . '/s_' .$extra_prefix. $image_name;  // small
			$srcm = $thumb_urlpath . '/m_' .$extra_prefix. $image_name;  // medium
			$srcl = $thumb_urlpath . '/l_' .$extra_prefix. $image_name;  // large
			$srco = (is_array($orig_urlpath) ? $orig_urlpath[$i] : $orig_urlpath)  . '/'   .$image_name;  // original image
			
			// Create a popup url link
			$urllink = isset($value['urllink']) ? $value['urllink'] : '';
			//if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
			
			// Create a popup tooltip (legend)
			$class = 'fc_field_image';
			if ($uselegend && (!empty($title) || !empty($desc)))
			{
				$class .= ' '.$tooltip_class;
				$legend = ' title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'"';
			}
			else
			{
				$legend = '';
			}
			
			// Handle single image display, with/without total, TODO: verify all JS handle & ignore display none on the img TAG
			$style = ($i!=0 && $isSingle) ? 'display:none;' : '';
			
			// Create a unique id for the link tags, and a class name for image tags
			$uniqueid = $item->id . '_' . $field->id . '_' . $i;
			
			switch ($thumb_size)
			{
				case -1: $src = $srcb; break;
				case 1: $src = $srcs; break;
				case 2: $src = $srcm; break;
				case 3: $src = $srcl; break;   // this makes little sense, since both thumbnail and popup image are size 'large'
				case 4: $src = $srco; break;
				default: $src = $srcs; break;
			}
			
			
			// Create a grouping name
			switch ($grouptype)
			{
				// This field only
				case 0: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id.'_fcfield_'.$field->id; break;
				
				// All fields of the item
				case 1: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id; break;
				
				// Per view:  all items of category page, or search page
				case 2: $group_name = 'fcview_'.$view; break;
				
				// No group
				default: $group_name = ''; break;
			}
			
			
			// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only (for consistency use 'use_ingroup' of 'ingroup')
			if ($use_ingroup)
			{
				// In case of field displayed via in fieldgroup, this is an array
				$field->{"display_backend_src"}[$n] = JUri::root(true).'/'.$srcb;
				$field->{"display_small_src"}[$n] = JUri::root(true).'/'.$srcs;
				$field->{"display_medium_src"}[$n] = JUri::root(true).'/'.$srcm;
				$field->{"display_large_src"}[$n] = JUri::root(true).'/'.$srcl;
				$field->{"display_original_src"}[$n] = JUri::root(true).'/'.$srco;
			}

			// Field displayed not via fieldgroup return only the 1st value
			else if ($i==0)
			{
				$field->{"display_backend_src"} = JUri::root(true).'/'.$srcb;
				$field->{"display_small_src"} = JUri::root(true).'/'.$srcs;
				$field->{"display_medium_src"} = JUri::root(true).'/'.$srcm;
				$field->{"display_large_src"} = JUri::root(true).'/'.$srcl;
				$field->{"display_original_src"} = JUri::root(true).'/'.$srco;
			}

			$field->thumbs_src['backend'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcb;
			$field->thumbs_src['small'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcs;
			$field->thumbs_src['medium'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcm;
			$field->thumbs_src['large'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcl;
			$field->thumbs_src['original'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srco;
			
			$field->thumbs_path['backend'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcb;
			$field->thumbs_path['small'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcs;
			$field->thumbs_path['medium'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcm;
			$field->thumbs_path['large'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcl;
			$field->thumbs_path['original'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srco;
			
			// Suggest image for external use, e.g. for Facebook etc, (making sure that URL is ABSOLUTE URL)
			if ( $is_FE_html_view && $useogp )
			{
				if ( in_array($view, $ogpinview) ) {
					switch ($ogpthumbsize)
					{
						case 1: $ogp_src = JUri::root().$srcs; break;   // this maybe problematic, since it maybe too small or not accepted by social website
						case 2: $ogp_src = JUri::root().$srcm; break;
						case 3: $ogp_src = JUri::root().$srcl; break;
						case 4: $ogp_src =  JUri::root().$srco; break;
						default: $ogp_src = JUri::root().$srcm; break;
					}
					$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
					$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
				}
			}


			// ***
			// *** CHECK if we were asked for value only display (e.g. image source)
			// *** if so we will not be creating the HTML code for Image / Gallery 
			// ***

			if ( isset(self::$value_only_displays[$prop]) )
			{
				continue;
			}


			// ***
			// *** Create image tags (according to configuration parameters)
			// *** that will be used for the requested 'display' variable
			// ***

			switch ($prop)
			{
				case 'display_backend':
					$img_legend   = '<img src="'.JUri::root(true).'/'.$srcb.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcb.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;

				case 'display_small':
					$img_legend   = '<img src="'.JUri::root(true).'/'.$srcs.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcs.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;

				case 'display_medium':
					$img_legend   = '<img src="'.JUri::root(true).'/'.$srcm.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcm.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;

				case 'display_large':
					$img_legend   = '<img src="'.JUri::root(true).'/'.$srcl.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcl.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;

				case 'display_original':
					$img_legend   = '<img src="'.JUri::root(true).'/'.$srco.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$srco.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;

				case 'display': default:
					$img_legend   = '<img src="'.JUri::root(true).'/'.$src.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JUri::root(true).'/'.$src.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
			}


			// ***
			// *** Create thumbnail appending text (not linked text)
			// ***

			// For galleries that are not inline/special, we can have inline text ??
			$inline_info = '';
			if ( $linkto_url || ($prop=='display_large' || $prop=='display_original') || !$usepopup || !$value_list_has_containers )
			{
				// Add inline display of title/desc
				if ( ($showtitle && $title ) || ($showdesc && $desc) )
					$inline_info = '<div class="fc_img_tooltip_data alert alert-info" style="'.$style.'" >';

				if ( $showtitle && $title )
					$inline_info .= '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>';
				if ( $showdesc && $desc )
					$inline_info .= '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>';

				if ( ($showtitle && $title ) || ($showdesc && $desc) )
					$inline_info .= '</div>';
			}


			// ***
			// *** FINALLY CREATE the field display variable ...
			// ***

			// CASE 0: Add single image display information (e.g. image count)
			if ( $linkToItem )
			{
				$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
				$field->{$prop}[] =
				'<span style="display: inline-block; text-align:center; ">
					<a href="'.$item_link.'" style="display: inline-block;">
					'.$img_nolegend.'
					</a><br/>'
					.($_method == 'display_single_total' || $_method == 'display_single_total_link' ? '
					<span class="fc_img_total_data badge badge-info" style="display: inline-block;" >
						'.count($values).' '.JText::_('FLEXI_IMAGES').'
					</span>' : '').'
				</span>';
				
				// If single display and not in field group then do not add more images
				if (!$is_ingroup && $isSingle) break;
			}

			// CASE 1: Handle linking to a URL instead of image zooming popup
			else if ($linkto_url)
			{
				// CASE: Just image thumbnail since url link is empty
				if (!$urllink)
				{
					$field->{$prop}[] = $pretext.'<div class="fc_img_container">'.$img_legend.$inline_info.'</div>'.$posttext;
				}

				// CASE: Link to URL that opens inside a popup via multibox
				else if ($url_target=='multibox')
				{
					$field->{$prop}[] = $pretext.'
					<script>document.write(\'<a style="'.$style.'" href="'.$urllink.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+(jQuery(window).width()-150)+\',height:\'+(jQuery(window).height()-150)+\'">\')</script>
						'.$img_legend.'
					<script>document.write(\'</a>\')</script>
					<div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					'.$inline_info.$posttext;
				}

				// CASE: Link to URL that opens inside a popup via fancybox
				else if ($url_target=='fancybox')
				{
					$field->{$prop}[] = $pretext.'
					<span class="fc_image_thumb" style="'.$style.'; cursor: pointer;" '.
						'onclick="jQuery.fancybox.open([{ type: \'iframe\', href: \''.$urllink.'\', topRatio: 0.9, leftRatio: 0.9, title: \''.($desc ? $title.': '.$desc : $title).'\' }], { padding : 0});"
					>
						'.$img_legend.'
					</span>
					'.$inline_info.$posttext;
				}

				// CASE: Just link to URL without popup
				else
				{
					$field->{$prop}[] = $pretext.'
					<a href="'.$urllink.'" target="'.$url_target.'">
						'.$img_legend.'
					</a>
					'.$inline_info.$posttext;
				}

			}

			// CASE 2: // No gallery code ... just apply pretext/posttext
			else if
			(
				!$usepopup || // Plain Thumbnail List without any (popup / inline) gallery code
				($prop=='display_large' || $prop=='display_original') // No popup if image is the largest OR original thumbs
			)
			{
				$field->{$prop}[] = $pretext.$img_legend.$inline_info.$posttext;
			}

			// CASE 3: Inline/special galleries OR --> GALLERIES THAT NEED SPECIAL ENCLOSERS
			else if ($value_list_has_containers)
			{
				// !!! ... pretext/posttext/inline_info/etc not meaningful or not supported or not needed
				switch ($popuptype)
				{
				// Galleriffic inline slideshow gallery
				case 5:
					$group_str = '';   // image grouping: not needed / not applicatble
					$field->{$prop}[] =
						'<a href="'.$srcl.'" class="fc_image_thumb thumb" name="drop">
							'.$img_legend.'
						</a>
						<div class="caption">
							<b>'.$title.'</b>
							<br/>'.$desc.'
						</div>';
					break;

				// Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
				case 7:
					// *** NEEDS: thumbnail list must be created with large size thubmnails, these will be then thumbnailed by the JS gallery code
					$title_attr = $desc ? $desc : $title;
					$img_legend_custom ='
						 <img src="'.JUri::root(true).'/'.$src.'" alt ="'.$alt.'"'.$legend.' class="'.$class.'"
						 	data-large="' . JUri::root(true).'/'.$srcl . '" data-description="'.$title_attr.'" itemprop="image"/>
					';
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<li><a href="javascript:;" class="fc_image_thumb">
							'.$img_legend_custom.'
						</a></li>';
					break;

				// ERROR unhandled INLINE GALLERY case
				default:
					$field->{$prop}[] = ' Unhandled INLINE GALLERY case in image with field name: {$field->name} '; break;
				}
			}

			// CASE 4: Popup galleries
			else
			{
				switch ($popuptype)
				{
				// Multibox image popup
				case 1:
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" id="mb'.$uniqueid.'" class="fc_image_thumb mb" '.$group_str.' >
							'.$img_legend.'
						</a>
						<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? '<span class="badge">'.$title.'</span> '.$desc : $title).'</div>'
						.$inline_info.$posttext;
					break;

				// Rokbox image popup
				case 2:
					$title_attr = $desc ? $desc : $title;
					$group_str = '';   // no support for image grouping
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" rel="rokbox['.$wl.' '.$hl.']" '.$group_str.' title="'.$title_attr.'" class="fc_image_thumb" data-rokbox data-rokbox-caption="'.$title_attr.'">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;

				// JCE popup image popup
				case 3:
					$title_attr = $desc ? $title.'::'.$desc : $title;
					$group_str = $group_name ? 'group['.$group_name.'];' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'"  class="fc_image_thumb jcepopup" data-mediabox="'.$group_str.'title['.$title_attr.']">
							'.$img_nolegend.'
						</a>'
						.$inline_info.$posttext;
					break;

				// Fancybox image popup
				case 4:
					$title_attr = $desc ? '<span class=\'badge\'>'.$title.'</span> '.$desc : $title;
					$group_str = $group_name ? 'data-fancybox-group="'.$group_name.'"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'"  class="fc_image_thumb fancybox" '.$group_str.' title="'.$title_attr.'">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;

				// (Widgetkit) SPOTlight image popup
				case 6:
					$title_attr = $desc ? $desc : $title;
					$group_str = $group_name ? 'data-spotlight-group="'.$group_name.'"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" class="fc_image_thumb" data-lightbox="on" data-spotlight="effect:bottom" '.$group_str.' title="'.$title_attr.'">
							'.$img_legend.'
							<div class="overlay">
								'.'<b>'.$title.'</b>: '.$desc.'
							</div>
						</a>'
						.$inline_info.$posttext;
					break;

				// PhotoSwipe popup carousel gallery
				case 8:
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" '.$group_str.' class="fc_image_thumb">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;

				// Unknown / Other Gallery Type, just add thumbails ... maybe pretext/posttext/separator/opentag/closetag will add a gallery
				default:
					$field->{$prop}[] = $pretext.$img_legend.$inline_info.$posttext;
					break;
				}
			}
		}


		// ***
		// *** Apply separator and open/close tags and handle SPECIAL CASEs:
		// *** by adding (container) HTML required by some JS image libraries
		// ***

		// Using in field group, return array
		if ( $is_ingroup )
		{
			return;
		}
		
		// Check for value only displays and return
		if ( isset(self::$value_only_displays[$prop]) )
		{
			return;
		}
		
		// Check for no values found
		if ( !count($field->{$prop}) )
		{
			$field->{$prop} = '';
			return;
		}
		
		// Galleriffic inline slideshow gallery
		if ($usepopup && $popuptype == 5)
		{
			$field->{$prop} = '
			<div id="gf_container">
				<div id="gallery" class="content">
					<div id="gf_controls" class="controls"></div>
					<div class="slideshow-container">
						<div id="gf_loading" class="loader"></div>
						<div id="gf_slideshow" class="slideshow"></div>
					</div>
					<div id="gf_caption" class="caption-container"></div>
				</div>
				<div id="gf_thumbs" class="navigation">
					<ul class="thumbs noscript">
						<li>
						'. implode("</li>\n<li>", $field->{$prop}) .'
						</li>
					</ul>
				</div>
				<div style="clear: both;"></div>
			</div>
			';
		}
		
		// Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
		else if ($usepopup && $popuptype == 7)
		{
			//$max_width = $field->parameters->get( 'w_l', 800 );
			
			// this should be size of previewer aka size of large image thumbnail
			$uid = 'es_'.$field->name."_fcitem".$item->id;
			$field->{$prop} = '
			<div id="rg-gallery_'.$uid.'" class="rg-gallery" >
				<div class="rg-thumbs">
					<!-- Elastislide Carousel Thumbnail Viewer -->
					<div class="es-carousel-wrapper">
						<div class="es-nav">
							<span class="es-nav-prev">'.JText::_('FLEXI_PREVIOUS').'</span>
							<span class="es-nav-next">'.JText::_('FLEXI_NEXT').'</span>
						</div>
						<div class="es-carousel">
							<ul>
								' . implode('', $field->{$prop}) . '
							</ul>
						</div>
					</div>
					<!-- End Elastislide Carousel Thumbnail Viewer -->
				</div><!-- rg-thumbs -->
			</div><!-- rg-gallery -->
			';
		}
		
		// PhotoSwipe popup carousel gallery
		else if ($usepopup && $popuptype == 8) {
			$field->{$prop} = '
			<span class="photoswipe_fccontainer" >
				'. implode($separatorf, $field->{$prop}) .'
			</span>
			';
		}
		
		// OTHER galleries need no special enclosing, only apply separator
		else {
			$field->{$prop} = implode($separatorf, $field->{$prop});
		}
		
		// Apply open/close tags
		$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
	}
	
	
	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$import_media_folder = $app->input->get('import_media_folder', '', 'string');
		$unique_tmp_itemid = $app->input->get('unique_tmp_itemid', '', 'string');
		$unique_tmp_itemid = substr($unique_tmp_itemid, 0, 1000);
		
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		
		// Set a warning message for overriden/changed files: form.php (frontend) or default.php (backend)
		if ( !$is_importcsv && empty($unique_tmp_itemid) )
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage( 'WARNING, field: '.$field->label.' requires variable -unique_tmp_itemid- please update your '.($app->isSite() ? 'form.php':'default.php'), 'warning');
		}
		
		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( !$initialized )
		{
			$initialized = 1;
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
			if ( $is_importcsv )
			{
				$srcpath_original = JPath::clean( JPATH_SITE .DS. $import_media_folder .DS );
			}
		}

		
		// ***
		// *** Special steps for image field in Folder-mode(s)
		// ***

		if ( $image_source >= 1 )
		{
			$dir = $field->parameters->get('dir');
			$unique_tmp_itemid = substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);
			
			$dest_path = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id . '_field_'.$field->id .DS );
			//if ( $image_source > 1 ) ; // TODO
			
			// Create original images folder if doing CSV import and folder does not exist
			if ( $is_importcsv )
			{
				$dest_path_original = $dest_path. 'original' .DS;
				if ( !JFolder::exists($dest_path_original) && !JFolder::create($dest_path_original) ) {
					JError::raiseWarning(100, $field->label .': Error. Unable to create folder: '. $dest_path_original );
					return false;  // Cancel item creation
				}
			}
			
			// New items have no item id during submission, thus we need to rename the temporary name of images upload folder
			else if ( $unique_tmp_itemid && $item->id != $unique_tmp_itemid ) {
				$temppath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$unique_tmp_itemid. '_field_'.$field->id .DS );
				JFolder::move($temppath, $dest_path);
			}
		}


		// ***
		// *** Special steps for image field in MM-mode
		// ***
		
		if ( $image_source == -2 )
		{
			$dest_path_media = 'images/';
			$dest_path_media_full = JPath::clean( JPATH_SITE .DS. $dest_path_media );
		}


		// ***
		// *** Rearrange file array so that file properties are groupped per value number
		// ***

		//echo "<pre>"; print_r($file); echo "</pre>";
		$files = array();
		if ($file) foreach( $file as $key => $all )
		{
			foreach( $all as $i => $val )
			{
				$files[$i][$key] = $val;
			}
		}
		//echo "<pre>"; print_r($files); echo "</pre>";


		// ***
		// *** Reformat the posted data & handle uploading / removing / deleting / replacing image files
		// ***

		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;

		$new_filenames = array();
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			if (empty($v))
			{
				// skip empty value, but allow empty (null) placeholder value if in fieldgroup
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			// Support for serialized user data, e.g. basic CSV import / export. (Safety concern: objects code will abort unserialization!)
			if ( $is_importcsv && !is_array($v) )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'originalname' => $v
				);
			}


			// Add system message if upload error
			$err_code = isset($files[$n]['error']) ? $files[$n]['error'] : UPLOAD_ERR_NO_FILE;
			if ( $err_code && $err_code!=UPLOAD_ERR_NO_FILE )
			{
				$err_msg = array(
					UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
					UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
					UPLOAD_ERR_PARTIAL  => 'The uploaded file was only partially uploaded',
					UPLOAD_ERR_NO_FILE  => 'No file was uploaded',
					UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
					UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
					UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
				);
				JFactory::getApplication()->enqueueMessage("FILE FIELD: ".$err_msg[$err_code], 'warning' );
				continue;
			}
			
			// Handle uploading a new original file
			$new_file = $err_code === 0;
			$new_file_uploaded = null;
			if ($new_file) {
				$new_file_uploaded = $this->uploadOriginalFile($field, $v, $files[$n]);
			}
			
			// Handle copying original files from a server folder during CSV import
			else if ($is_importcsv && $import_media_folder )
			{
				$filename = basename($v['originalname']);
				$sub_folder = dirname($v['originalname']);
				$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';
				
				if ( $image_source >= 1 )
				{
					$src_file_path  = JPath::clean( $srcpath_original . $v['originalname'] );
					$dest_file_path = JPath::clean( $dest_path_original . $filename );
					if ( JFile::exists($src_file_path) ) {
						$result = JFile::copy( $src_file_path,  $dest_file_path );
						if ( $result && JPath::canChmod($dest_file_path) )  chmod($dest_file_path, 0644);
					}
					$v['originalname'] = $filename; // make sure filename is WITHOUT subfolder
				}

				else if ( $image_source == -2 )
				{
					$src_file_path  = JPath::clean( $srcpath_original . $v['originalname'] );
					$dest_file_path = JPath::clean( $dest_path_media_full . $filename );
					if ( JFile::exists($src_file_path) ) {
						$result = JFile::copy( $src_file_path,  $dest_file_path );
						if ( $result && JPath::canChmod($dest_file_path) )  chmod($dest_file_path, 0644);
					}
					$v['originalname'] = $dest_path_media . $filename; // make sure filename is WITH subfolder
				}

				else
				{
					$fman = new FlexicontentControllerFilemanager();
					$fman->runMode = 'interactive';

					$app->input->set('return-url', null);
					$app->input->set('file-dir-path', DS.$import_media_folder . $sub_folder);
					$app->input->set('file-filter-re', preg_quote($filename));
					$app->input->set('secure', 1);
					$app->input->set('keep', 1);

					$upload_err = null;
					$file_ids = $fman->addlocal(null, $upload_err);
					reset($file_ids);  // Reset array to point to first element
					$v['originalname'] = key($file_ids);  // The (first) key of file_ids array is the cleaned up filename
				}
			}
			
			
			// Defaut values for unset required properties of values
			$v['originalname'] = isset($v['originalname']) ? $v['originalname'] : '';
			$v['existingname'] = isset($v['existingname']) ? $v['existingname'] : '';
			
			if ( $v['originalname'] || $v['existingname'] )
			{
				// Handle replacing image with a new existing image
				if ( $v['existingname'] ) {
					$v['originalname'] = $v['existingname'];
					$v['existingname'] = '';
				}				
			}

			else
			{
				// No new file posted and no existing selected: Skip current image row, but allow empty (null) placeholder value if in fieldgroup
				$v = $use_ingroup ? null : false; //$use_ingroup ? array('originalname'=>'') : null;
			}

			// Add image entry to a new array skipping empty image entries
			if ($v!==false)
			{
				if ($v) $new_filenames[$v['originalname']] = 1;
				$newpost[$new] = $v;
				$new++;
			}
		}
		$post = $newpost;
		
    
    // Remove no longer used files, if limiting existing image list to current field, or if existing image list is hidden/disabled
    if ( $image_source == 0 && ($field->parameters->get('auto_delete_unused', 1) || !$field->parameters->get('list_all_media_files', 0)) )
    {
			// Get existing field values, 
			if (!isset($item->fieldvalues)) {
				$_fieldvalues = FlexicontentFields::getFieldValsById(null, array($item->id));
				$item->fieldvalues = isset($_fieldvalues[$item->id]) ? $_fieldvalues[$item->id] : array();
			}
			$db_values = !empty($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			//echo "<pre>"; print_r($new_filenames); print_r($db_values);
			
			// Remove unused files
			foreach($db_values as $i => $v)
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'originalname' => $v
				);

				$filename = isset($v['originalname']) ? $v['originalname'] : false;
				if ($filename && !isset($new_filenames[$filename]))
				{
					// Check if value is in use
					$canDeleteImage = $this->canDeleteImage($field, $filename, $item);
					if ($canDeleteImage)
					{
						$this->removeOriginalFile( $field, $filename );
						//JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . 'Deleted image file: '.$filename.' from server storage');
					}
				}
			}
		}
		
		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
			if ($v!==null) $post[$i] = serialize($v);
		}
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( empty($post) ) return;

		$app    = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';

		if ( !$is_importcsv ) return;
		
		$values = array();
		foreach($post as $i => $v)
		{
			if ( !is_array($v) )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'originalname' => $v
				);
			}

			$values[$i] = $v;
			plgFlexicontent_fieldsImage::rebuildThumbs($field, $values[$i], $item);
		}
		//echo "<b>{$field->field_type}</b>: <br/> <pre>".print_r($values, true)."</pre>\n";
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
		$dir = $field->parameters->get('dir');

		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		if ( $image_source >= 1 )
		{
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
			
			// Delete image folder if it exists
			$dest_path = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id   . '_field_'.$field->id .DS);
			//if ( $image_source > 1 ) ; // TODO
			
			if ( JFolder::exists($dest_path) && !JFolder::delete($dest_path) )
			{
				JError::raiseNotice(100, $field->label .': Notice: Unable to delete folder: '. $dest_path );
				return false;
			}
		}
	}
	
	
	
	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}
	
	
	
	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text

		/*if ($post !== null)
		{
			$v = reset($post);
			if ((string)(int)$v == $v)
			{
				$files_data = $this->getFileData( $post, $published=false );
			}
			foreach ($post as $index => $value)
			{
				// Compatibility for non-serialized values, e.g file ids as values (minigallery legacy field)
				if ((string)(int)$value == $value)
				{
					if (isset($files_data[$value]))
					{
						$_filename = $files_data[$value]->filename_original ? $files_data[$value]->filename_original : $files_data[$value]->filename;
						$value = array('originalname' => $_filename);
					}
					else $value = array('originalname' => null);
					$post[$index] = serialize($value);
				}
				else
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'originalname' => $value
					);
				}
				$post[$index] = $value;
			}
		}*/
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		/*if ($post !== null)
		{
			$v = reset($post);
			if ((string)(int)$v == $v)
			{
				$files_data = $this->getFileData( $post, $published=false );
			}
			foreach ($post as $index => $value)
			{
				// Compatibility for non-serialized values, e.g file ids as values (minigallery legacy field)
				if ((string)(int)$value == $value)
				{
					if (isset($files_data[$value]))
					{
						$_filename = $files_data[$value]->filename_original ? $files_data[$value]->filename_original : $files_data[$value]->filename;
						$value = array('originalname' => $_filename);
					}
					else $value = array('originalname' => null);
					$post[$index] = serialize($value);
				}
				else
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'originalname' => $value
					);
				}
				$post[$index] = $value;
			}
		}*/
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***


	/**
	 * Method to handle the uploading of an image file (for 'DB-reusable' mode and not for 'folder' mode)
	 */

	function uploadOriginalFile($field, &$post, $file)
	{
		$app    = JFactory::getApplication();
		$format = $app->input->get('format', 'html', 'cmd');
		$err_text = null;
		
		// Get the component configuration
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$params = clone($cparams);
		
		// Merge field parameters into the global parameters
		$fparams = $field->parameters;
		$params->merge($fparams);
				
		jimport('joomla.utilities.date');
		jimport('joomla.filesystem.file');
		jimport('joomla.client.helper');
		
		// Set FTP credentials, if given
		JClientHelper::setCredentialsFromRequest('ftp');
		
		// Make the filename safe
		$file['name'] = JFile::makeSafe($file['name']);

		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);

		// IMAGE SOURCE, should be always ZERO (DB-mode) inside this function
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		if ( isset($file['name']) && $file['name'] != '' )
		{
			// only handle the secure folder
			$path = ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS;

			//sanitize filename further and make unique
			$upload_check = flexicontent_upload::check($file, $err_text, $params);  // Check that file contents are safe, and also make the filename safe, transliterating it according to given language (this forces lowercase)
			$filename     = flexicontent_upload::sanitize($path, $file['name']);    // Sanitize the file name (filesystem-safe, (this should have been done above already)) and also return an unique filename for the given folder
			$filepath     = JPath::clean($path.$filename);
			
			//perform security check according
			if (!$upload_check)
			{
				if ($format == 'json')
				{
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Invalid: '.$filepath.': '.$err_text));
					header('HTTP/1.0 415 Unsupported Media Type');
					die('Error. Unsupported Media Type!');
				}

				JError::raiseNotice(100, $field->label . ' : ' . JText::_($err_text));
				return false;
			}

			//get the extension to record it in the DB
			$ext = strtolower(flexicontent_upload::getExt($filename));

			// - we allow Joomla default security to execute
			// - if user really uploads an image file, it should not be trigger anyway
			$upload_success = JFile::upload($file['tmp_name'], $filepath);
			if ( !$upload_success )
			{
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
					header('HTTP/1.0 409 Conflict');
					jexit('Error. File already exists');
				}

				JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to upload file'));
				return false;
			} else {
				$db     = JFactory::getDbo();
				$user   = JFactory::getUser();
				$config = JFactory::getConfig();
				
				$timezone = $config->get('offset');
				$date = JFactory::getDate('now');
				$date->setTimeZone( new DateTimeZone( $timezone ) );
				
				$obj = new stdClass();
				$obj->filename	= $filename;
				$obj->altname		= $file['name'];
				$obj->url				= 0;
				$obj->secure		= 1;
				$obj->access		= 1;  // public
				$obj->ext				= $ext;
				$obj->hits			= 0;
				$obj->uploaded		= $date->toSql();
				$obj->uploaded_by	= $user->get('id');
				
				if ($format == 'json')
				{
					jimport('joomla.error.log');
					$log = JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
					
					$db->insertObject('#__flexicontent_files', $obj);
					jexit('FLEXI_UPLOAD_COMPLETE');
				}
				else
				{
					$db->insertObject('#__flexicontent_files', $obj);
					$app->enqueueMessage($field->label . ' : ' . JText::_('FLEXI_UPLOAD_COMPLETE'));
					
					$sizes = array('l','m','s','b');
					foreach ($sizes as $size)
					{
						// create the thumbnail
						$this->create_thumb( $field, $filename, $size, $src_path='', $dest_path='', $copy_original=0, $extra_prefix );
						
						// set the filename for posting
						$post['originalname'] = $filename;
					}
					return true;
				}
			}
		}
		else
		{
			$err_text = 'File upload failed';
			JError::raiseNotice(100, $field->label . ' : ' . JText::_($err_text));
			return false;
		}
	}


	/**
	 * Decide parameters for calling phpThumb library to create a thumbnail according to configuration
	 */

	function create_thumb( &$field, $filename, $size, $src_path='', $dest_path='', $copy_original=0, $extra_prefix='' )
	{
		static $dest_paths_arr = array();
		
		// Execute once
		static $initialized = null;
		if ( !$initialized )
		{
			$initialized = 1;
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
		}
		
		// (DB/Folder/Other) Mode of image field
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);
		
		// Image file paths
		$dir = $field->parameters->get('dir');
		$src_path = $src_path ? $src_path : JPath::clean(($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS);
		$dest_path = $dest_path ? $dest_path : JPath::clean( JPATH_SITE .DS. $dir .DS );
		$prefix		= $size . '_' . $extra_prefix;
		$filepath = $dest_path.$prefix.$filename;
		
		// Parameters for phpthumb
		$ext = strtolower(flexicontent_upload::getExt($filename));
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		$w			= $field->parameters->get('w_'.$size, $default_widths[$size]);
		$h			= $field->parameters->get('h_'.$size, $default_heights[$size]);
		$crop		= $field->parameters->get('method_'.$size);
		$quality= $field->parameters->get('quality');
		$usewm	= $field->parameters->get('use_watermark_'.$size);
		$wmfile	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('wm_'.$size));
		$wmop		= $field->parameters->get('wm_opacity');
		$wmpos	= $field->parameters->get('wm_position');
		
		// Create destination folder if it does not exist
		if ( !JFolder::exists($dest_path) && !JFolder::create($dest_path) ) { 
			JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to create folders'));
			return false;
		}
		
		// Make sure folder is writtable by phpthumb
		if ( !isset($dest_paths_arr[$dest_path]) && JPath::canChmod($dest_path) ) {
			//JPath::setPermissions($dest_path, '0644', '0755');  // *** VERY SLOW does chmod on all folder / subfolder files
			chmod($dest_path, 0755);
		}
		$dest_paths_arr[$dest_path] = 1;  // Avoid trying to set folder permission multiple times
		
		// EITHER copy original image file as current thumbnail (FLAG 'copy_original' is set)
		if ($copy_original) {
			$result = JFile::copy( $src_path.$filename,  $filepath );
		}
		
		// OR Create the thumnail by calling phpthumb
		else {
			$result = $this->imagePhpThumb( $src_path, $dest_path, $prefix, $filename, $ext, $w, $h, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos );
		}
		
		// Make sure the created thumbnail has correct permissions
		if ( $result && JPath::canChmod($filepath) )  chmod($filepath, 0644);
		
		return $result;
	}



	/**
	 * Call phpThumb library to create a thumbnail according to configuration
	 */

	function imagePhpThumb( $src_path, $dest_path, $prefix, $filename, $ext, $width, $height, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos )
	{
		static $initialized = null;
		if ($initialized === null)
		{
			$initialized = 1;
			require_once ( JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpthumb.class.php' );
			// WE DO INCLUDE TO FORCE LOADING OF configuration AFTER the class
			// WE HAVE PATCHED configuration not to double define CONSTANTS and FUNCTIONS
			include ( JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpThumb.config.php' );
		}
		
		unset ($phpThumb);
		$phpThumb = new phpThumb();
		
		$filepath = $src_path . $filename;
		
		$phpThumb->setSourceFilename($filepath);
		$phpThumb->setParameter('config_output_format', "$ext");
		$phpThumb->setParameter('w', $width);
		$phpThumb->setParameter('h', $height);
		$phpThumb->setParameter('ar', 'x');

		if ($usewm == 1)
		{
			$phpThumb->setParameter('fltr', 'wmi|'.$wmfile.'|'.$wmpos.'|'.$wmop);
		}

		$phpThumb->setParameter('q', $quality);

		if ($crop == 1)
		{
			$phpThumb->setParameter('zc', 1);
		}

		$ext = strtolower(flexicontent_upload::getExt($filename));

		if ( in_array( $ext, array('png', 'ico', 'gif') ) )
		{
			$phpThumb->setParameter('f', $ext);
		}

		$output_filename = $dest_path . $prefix . $filename ;

		if ($phpThumb->GenerateThumbnail())
		{
			if ($phpThumb->RenderToFile($output_filename))
			{
				return true;
			}

			//echo 'Failed:<pre>' . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
			return false;
		}

		//echo 'Failed2:<pre>' . $phpThumb->fatalerror . "\n\n" . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
		return false;
	}



	/**
	 * Removes an orignal image file and its thumbnails
	 */

	function removeOriginalFile( $field, $filename )
	{
		$db = JFactory::getDbo();
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.path');

		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		if ($image_source > 1) $image_source = $this->nonImplementedMode($image_source, $field);

		// Folder-mode 1
		if ( $image_source >= 1 )
		{
			$thumbfolder = JPath::clean(JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id);
			$origfolder  = $thumbfolder .DS. 'original' .DS;
		}

		// DB-mode
		else if ( $image_source == 0 )
		{
			$thumbfolder = JPath::clean( JPATH_SITE .DS. $field->parameters->get('dir') );
			$origfolder  = JPath::clean( ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) );
		}

		// Negative, intro-full mode, this should be unreachable, because
		else
		{
			echo "image field id: ".$field->id." is in intro-full mode, removeOriginalFile() should not have been called";
		}
		
		// a. Delete the thumbnails
		$errors		= array();
		$sizes 		= array('l','m','s','b');
		foreach ($sizes as $size)
		{
			$dest_path = $thumbfolder . DS . $size . '_' . $filename;
			if ( JFile::exists($dest_path) && !JFile::delete($dest_path) )
			{
				// Handle failed delete, currently this is not outputed, since thumbnails may not have been created, or may have been deleted manually ??
				JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $dest_path);
			}
		}
		
		// b. Delete the original image from file manager
		$src_path = JPath::clean($origfolder.DS.$filename);
		if (!JFile::delete($src_path))
		{
			JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $src_path);
		}

		// For DB-mode, also delete file from database
		if ( $image_source == 0 )
		{
			$query = 'DELETE FROM #__flexicontent_files'
				. ' WHERE ' . $db->quoteName('filename') . ' = ' . $db->Quote($filename);
			$db->setQuery( $query );
			if(!$db->execute())
			{
				$this->setError($db->getErrorMsg());
				return false;
			}
		}
		
		return true;
	}



	/**
	 * Smart image thumbnail size check and rebuilding
	 */

	function rebuildThumbs( &$field, &$value, &$item )
	{
		static $images_processed = array();

		// Execute once
		static $initialized = null;
		if ( !$initialized )
		{
			$initialized = 1;
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
		}

		// Check for empty filename
		$value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';
		if ( !$value['originalname'] )
		{
			return;
		}
		$filename = basename($value['originalname']);

		// *** Extra thumbnails sub-folder
		list($file_path, $src_path, $dest_path, $field_index) = $this->getThumbPaths($field, $item, $value);


		// *** Return cached data, avoiding rechecking/recreating image thumbnails multiple times
		if (isset($images_processed[$field_index][$file_path]))
			return $images_processed[$field_index][$file_path];

		// *** Check for original file have been deleted or is not being a file
		if ( !file_exists($file_path) || !is_file($file_path) )
			return ($images_processed[$field_index][$file_path] = false);


		// *** Enforce protection of original image files any Folder-mode
		$this->protectImagePath($field, $src_path);


		// Configuration
		$image_source = (int) $field->parameters->get('image_source', 0);
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);

		// *** FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method==0;
		$multiple_image_usages = $multiple_image_usages || @ $value['default_image'];
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';


		// ***
		// *** Check dimension of thumbs and rebuild as needed
		// ***

		$filesize	= getimagesize($file_path);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];
		
		$sizes = array('l','m','s','b');
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		
		if ($extra_prefix) $sizes[] = '_s';  // always create an unprefixed small thumb, it is needed when assigning preview
		$thumbres = true;
		foreach ($sizes as $size)
		{
			$check_small = $size=='_s';
			$size = $check_small ? 's' : $size;
			$thumbname = $size . '_' . ($check_small ? '' : $extra_prefix) . $filename;
			$path	= JPath::clean( $dest_path .DS. $thumbname);
			
			$thumbnail_exists = false;
			if (file_exists($path)) {
				$filesize = getimagesize($path);
				$filesize_w = $filesize[0];
				$filesize_h = $filesize[1];
				$thumbnail_exists = true;
			}
			if ($thumbnail_exists && $check_small) continue;
			
			$param_w = $field->parameters->get( 'w_'.$size, $default_widths[$size] );
			$param_h = $field->parameters->get( 'h_'.$size, $default_heights[$size] );
			$crop = $field->parameters->get('method_'.$size);
			$usewm = $field->parameters->get('use_watermark_'.$size);
			$copyorg = $field->parameters->get('copy_original_'.$size, 1);
			$copy_original = ($copyorg==2) || ($origsize_w == $param_w && $origsize_h == $param_h && !$usewm && $copyorg==1);
			
			// Check if size of file is not same as parameters and recreate the thumbnail
			if (
					!$thumbnail_exists ||
					( $crop==0 && (
													($origsize_w >= $param_w && abs($filesize_w - $param_w)>1 ) &&  // scale width can be larger than it is currently
													($origsize_h >= $param_h && abs($filesize_h - $param_h)>1 )     // scale height can be larger than it is currently
												)
					) ||
					( $crop==1 && (
													($param_w <= $origsize_w && abs($filesize_w - $param_w)>1 ) ||  // crop width can be smaller than it is currently
													($param_h <= $origsize_h && abs($filesize_h - $param_h)>1 )     // crop height can be smaller than it is currently
												)
					)
				 )
			 {
				//echo "FILENAME: ".$thumbname.", ".($crop ? "CROP" : "SCALE").", ".($thumbnail_exists ? "OLDSIZE(w,h): $filesize_w,$filesize_h" : "")."  NEWSIZE(w,h): $param_w,$param_h <br />";
				$was_thumbed = $this->create_thumb( $field, $filename, $size, $src_path, $dest_path, $copy_original, ($check_small ? '' : $extra_prefix) );
				$thumbres = $thumbres && $was_thumbed;
			}
		}
		
		return ($images_processed[$field_index][$file_path] = $thumbres);
	}
	
	

	/**
	 * Returns an array of images that can be deleted
	 * e.g. of a specific field, or a specific uploader
	 */

	function canDeleteImage( &$field, $record, &$item )
	{
		// Retrieve available (and appropriate) images from the DB
		$db   = JFactory::getDbo();
		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE filename='. $db->Quote($record)
			;
		$db->setQuery($query);
		$file_id = $db->loadResult();
		if (!$file_id)  return true;
		
		$ignored['item_id'] = $item->id;
		
		$fm = new FlexicontentModelFilemanager();
		return $fm->candelete( array($file_id), $ignored );
	}



	/**
	 * Create a string that concatenates various image information
	 * (Function is not called anywhere, used only for debugging)
	 */

	function listImageUses($field, $record)
	{
		$db = JFactory::getDbo();
		$query = 'SELECT value, item_id'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		$db->setQuery($query);
		$db_data = $db->loadObjectList();
		
		$itemids = array();

		// Remove unused files
		foreach($db_data as $i => $data)
		{
			$array = $this->unserialize_array($data->value, $force_array=false, $force_value=false);
			$data->value = $array ?: array(
				'originalname' => $data->value
			);

			$filename = isset($data->value['originalname']) ? $data->value['originalname'] : false;
			if ($filename && $filename == $record)
			{
				$itemids[] = $data->item_id;
			}
		}

		return implode(',' , $itemids);
	}



	/**
	 * Get message about upload information & upload limitations
	 * (Function is not called anywhere, used only for debugging)
	 */

	function getUploadLimitsTxt($field, $enable_multi_uploader = true)
	{
		$tooltip_class = 'hasTooltip';
		$hint_image = JHtml::image ( 'components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), '' );
		$warn_image = JHtml::image ( 'components/com_flexicontent/assets/images/warning.png', JText::_( 'FLEXI_NOTES' ), '' );

		JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		$upConf = JHtml::_('fcuploader.getUploadConf', $field);
		$phpUploadLimit = flexicontent_upload::getPHPuploadLimit();

		$server_limit_exceeded = $phpUploadLimit['value'] < $upConf['upload_maxsize'];
		$server_limit_active = $server_limit_exceeded && ! $enable_multi_uploader;

		$conf_limit_class = $server_limit_active ? 'badge badge-box' : '';
		$conf_limit_style = $server_limit_active ? 'text-decoration: line-through;' : '';
		$conf_lim_image   = $server_limit_active ? $warn_image.$hint_image : $hint_image;
		$sys_limit_class  = $server_limit_active ? 'badge badge-box badge-warning' : '';

		return '
		<span class="fc-img-field-upload-limits-box">
			<span class="label label-info fc-upload-box-lbl">'.JText::_( $server_limit_exceeded ? 'FLEXI_UPLOAD_LIMITS' : 'FLEXI_UPLOAD_LIMIT' ).'</span>
			<span class="fc-php-upload-limit-box">
				<span class="'.$tooltip_class.'" style="margin-left:24px;" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT', 'FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT_DESC', 1, 1).'">'.$conf_lim_image.'</span>
				<span class="badge '.$conf_limit_class.'" style="'.$conf_limit_style.'">'.round($upConf['upload_maxsize'] / (1024*1024), 2).' M </span>
			</span>
			'.($server_limit_exceeded ? '
			<span class="fc-sys-upload-limit-box">
				<span class="'.$tooltip_class.'" style="margin-left:24px;" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SERVER_UPLOAD_MAX_LIMIT'), JText::sprintf('FLEXI_SERVER_UPLOAD_MAX_LIMIT_DESC', $phpUploadLimit['name']), 0, 1).'">'.$hint_image.'</span>
				<span class="badge '.$sys_limit_class.'">'.round($phpUploadLimit['value'] / (1024*1024), 2).' M </span>
			</span>' : '').'
		</span>
		';
	}



	/**
	 * Register a message about internal bug of not-implemented image source mode
	 */

	function nonImplementedMode($image_source, &$field)
	{
		static $fc_folder_mode_err;
		if ( isset($fc_folder_mode_err[$field->id]) ) return 1;
		
		JFactory::getApplication()->enqueueMessage("Error source-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id, 'warning' );
		$fc_folder_mode_err[$field->id] = 1;
		return 1;
	}



	/**
	 * Get DB data for the given file IDs
	 */

	function getFileData( $fid, $published=1, $extra_select='' )
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$file_ids = is_array($fid) ? $fid : array($fid);
		foreach ($file_ids as $file_id)
		{
			$f = (int)$file_id;
			if ( !isset($cached_data[$f]) && $f)
				$new_ids[] = $f;
		}
		
		// Get file data not retrieved already
		if ( count($new_ids) )
		{
			// Only query files that are not already cached
			$db = JFactory::getDbo();
			$query = 'SELECT * '. $extra_select //filename, filename_original, altname, description, ext, id'
					. ' FROM #__flexicontent_files'
					. ' WHERE id IN ('. implode(',', $new_ids) . ')'
					. ($published ? '  AND published = 1' : '')
					;
			$db->setQuery($query);
			$new_data = $db->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data)
			{
				$cached_data[$file_id] = $file_data;
			}
		}
		
		// Finally get file data in correct order
		foreach($file_ids as $file_id)
		{
			$f = (int)$file_id;
			if ( isset($cached_data[$f]) && $f)
			{
				$return_data[$file_id] = $cached_data[$f];
			}
		}

		return !is_array($fid) ? @$return_data[(int)$fid] : $return_data;
	}



	/**
	 * Get folder path used by image thubmnails
	 */

	function getThumbPaths($field, $item, $value)
	{
		$image_source = (int) $field->parameters->get('image_source', 0);
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		$dir = $field->parameters->get('dir');

		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;

		// Extract sub-folder pathfrom the (filepath) value (if it exists)
		$subpath  = dirname($value['originalname']);
		$filename = basename($value['originalname']);


		// *** Find extra thumbnails sub-folder
		// *** Find original folder path

		// Default value
		if ( !empty($value['default_image']) )
		{
			$extra_folder = '';
			$src_path  = JPath::clean( JPATH_SITE .DS. dirname($value['default_image']) .DS ); // 'default_image' is a FILE path
		}

		// Intro-full image mode
		else if ( $image_source == -1 )
		{
			$extra_folder = 'intro_full';
			$src_path  = JPath::clean( JPATH_SITE .DS. dirname($value['image_path']) .DS );  // 'image_path' is a FILE path of an intro / full image
		}

		// Media manager mode
		else if ( $image_source == -2 )
		{
			$extra_folder = 'mediaman';
			$src_path  = JPath::clean( JPATH_SITE .DS );
		}

		// Folder-mode 1
		else if ( $image_source >= 1 )
		{
			$extra_folder = 'item_'.$u_item_id . '_field_'.$field->id;
			$src_path  = JPath::clean( JPATH_SITE .DS. $dir .DS. $extra_folder .DS. 'original' .DS );
		}

		// DB-mode
		else
		{
			$extra_folder = '';
			$src_path  = JPath::clean( ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS );
		}

		// Add value-extracted subpath to original folder
		$src_path = JPath::clean( $src_path . ($subpath ?: '') .DS );

		// Full path of original file  - and - Destination folder
		$file_path = JPath::clean( $src_path . $filename );
		$dest_path = JPath::clean( JPATH_SITE .DS. $dir .DS. ($extra_folder ? $extra_folder .DS : '') );


		// Create an index that differentiatiates same file in different fields

		// Folder-mode 1
		if ( $image_source >= 1 )  // TODO other folder modes
			$field_index = 'item_'.$u_item_id . '_field_'.$field->id;

		// DB-mode or intro-full mode
		else
			$field_index = 'field_'.$field->id;


		//echo 'file_path: ' . $file_path ."<br/>" . 'dest_path: ' . $dest_path ."<br/><br/>";
		return array($file_path, $src_path, $dest_path, $field_index);
	}



	/**
	 * Enforce protection of original image files any Folder-mode
	 */

	protected function protectImagePath($field, $src_path)
	{
		$image_source = (int) $field->parameters->get('image_source', 0);

		if ($image_source < 1  ||  !JFolder::exists($src_path))
		{
			return;
		}

		$protect_original = $field->parameters->get('protect_original', 1);
		$htaccess_file = JPath::clean( $src_path . '.htaccess' );
		$file_contents = $protect_original
		?
			'# do not allow direct access and also deny scripts'."\n".
			'<FilesMatch ".*">'."\n".
			'  Order Allow,Deny'."\n".
			'  Deny from all'."\n".
			'</FilesMatch>'."\n".
			'OPTIONS -Indexes -ExecCGI'."\n"
		:
			'# allow direct access but deny script'."\n".
			'<FilesMatch ".*">'."\n".
			'  Order Allow,Deny'."\n".
			'  Allow from all'."\n".
			'</FilesMatch>'."\n".
			'OPTIONS -Indexes -ExecCGI'."\n";

		// write .htaccess file
		$fh = @ fopen($htaccess_file, 'w');
		if (!$fh)
		{
			JFactory::getApplication()->enqueueMessage( 'Cannot create/write file:'.$htaccess_file, 'notice' );
		}
		else
		{
			fwrite($fh, $file_contents);
			fclose($fh);
		}
	}
}
