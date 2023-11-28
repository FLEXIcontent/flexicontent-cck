<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('FlexicontentControllerFilemanager', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');  // we use JPATH_BASE since controller exists in frontend too
JLoader::register('FlexicontentModelFilemanager', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');  // we use JPATH_BASE since model exists in frontend too

if (!defined('_FC_CONTINUE_'))  define('_FC_CONTINUE_', 1);
if (!defined('_FC_BREAK_'))  define('_FC_BREAK_', 2);
if (!defined('_FC_RETURN_'))  define('_FC_RETURN_', 3);

class plgFlexicontent_fieldsImage extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	static $default_widths  = array('l' => 800,'m' => 400,'s' => 120,'b' => 40);
	static $default_heights = array('l' => 600,'m' => 300,'s' => 90,'b' => 30);

	static $display_to_thumb_size = array(
		'display_backend_src' => 'b', 'display_small_src' => 's', 'display_medium_src' => 'm', 'display_large_src' => 'l', 'display_original_src' => 'o',
		'display_backend_thumb' => 'b', 'display_small_thumb' => 's', 'display_medium_thumb' => 'm', 'display_large_thumb' => 'l', 'display_original_thumb' => 'o',
	);
	static $index_to_thumb_size = array(-1 => 'b', 1 => 's', 2 => 'm', 3 => 'l', 4 => 'o');

	static $thumb_only_displays = array('display_backend_thumb' => 0, 'display_small_thumb' => 1, 'display_medium_thumb' => 2, 'display_large_thumb' => 3, 'display_original_thumb' => 4);
	static $value_only_displays = array('display_backend_src' => 0, 'display_small_src' => 1, 'display_medium_src' => 2, 'display_large_src' => 3, 'display_original_src' => 4);
	static $single_displays = array('display_single' => 0, 'display_single_total' => 1, 'display_single_link' => 2, 'display_single_total_link' => 3);
	static $js_added = array();

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

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
			$this->_load_phpthumb();
		}

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';

		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);

		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $u_item_id;



		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);

		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir     = $field->parameters->get('dir');
		$dir_url = str_replace('\\', '/', $dir);


		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source === 0 && $all_media && $unique_thumb_method == 0;
		$extra_prefix = $multiple_image_usages  ?  'fld' . $field->id . '_'  :  '';

		$autoassign = (int) $field->parameters->get( 'autoassign', 1 );

		$thumb_size_resizer = (int) $field->parameters->get('thumb_size_resizer', 2);
		$thumb_size_default = (int) $field->parameters->get('thumb_size_default', 120);
		$thumb_method       = (int) $field->parameters->get('thumb_method', 1);
		$preview_thumb_w    = $preview_thumb_h = 600;

		// Optional properies configuration
		$linkto_url = (int) $field->parameters->get('linkto_url', 0);

		$usemediaurl = (int) $field->parameters->get('use_mediaurl', 0);
		$usealt      = (int) $field->parameters->get('use_alt', 0);
		$usetitle    = (int) $field->parameters->get('use_title', 0);
		$usedesc     = (int) $field->parameters->get('use_desc', 1);
		$usecust1    = (int) $field->parameters->get('use_cust1', 0);
		$usecust2    = (int) $field->parameters->get('use_cust2', 0);

		$mediaurl_usage = (int) $field->parameters->get('mediaurl_usage', 0);
		$alt_usage      = (int) $field->parameters->get('alt_usage', 0);
		$title_usage    = (int) $field->parameters->get('title_usage', 0);
		$desc_usage     = (int) $field->parameters->get('desc_usage', 0);
		$cust1_usage    = (int) $field->parameters->get('cust1_usage', 0);
		$cust2_usage    = (int) $field->parameters->get('cust2_usage', 0);

		$default_mediaurl = ($item->version == 0 || $mediaurl_usage > 0) ? $field->parameters->get( 'default_mediaurl', '' ) : '';
		$default_alt      = ($item->version == 0 || $alt_usage > 0) ? $field->parameters->get( 'default_alt', '' ) : '';
		$default_title    = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_desc     = ($item->version == 0 || $desc_usage > 0) ? $field->parameters->get( 'default_desc', '' ) : '';
		$default_cust1    = ($item->version == 0 || $cust1_usage > 0) ? $field->parameters->get( 'default_cust1', '' ) : '';
		$default_cust2    = ($item->version == 0 || $cust2_usage > 0) ? $field->parameters->get( 'default_cust2', '' ) : '';

		$use_jformfields = true; //FLEXI40GE

		// *** Calculate some configuration flags

		// Display properties box
		$none_props = !$linkto_url && !$usemediaurl && !$usealt && !$usetitle && !$usedesc && !$usecust1 && !$usecust2;

		// Inline uploaders flags
		$use_inline_uploaders = $image_source >= 0;
		$file_btns_position = (int) $field->parameters->get('file_btns_position', 0);


		// Intro / Full mode
		if ($image_source === -1)
		{
			$field->html = $use_ingroup ?
				array('<div class="alert alert-warning fc-small fc-iblock">Field is configured to use intro/full images, please disable use in group</div>') :
				'_INTRO_FULL_IMAGES_HTML_';
			return;
		}


		// Add JS /CSS for Media manager mode, and also check their PHP layouts overides exist
		static $mm_mode_common_js_added = false;

		if ($image_source === -2 && !$mm_mode_common_js_added)
		{
			// Check and if needed install Joomla template overrides into current Joomla template
			flexicontent_html::install_template_overrides();

			if (!$use_jformfields)
			{
				// We will use the mootools based media manager
				JHtml::_('behavior.framework', true);

				// Load the modal behavior script.
				JHtml::_('behavior.modal'/*, '.fc_image_field_mm_modal'*/);

				// Include media field JS, detecting different version of Joomla
				if (file_exists($path = JPATH_ROOT.'/media/media/js/mediafield-mootools.min.js'))
				{
					$media_js = 'media/mediafield-mootools.min.js';
				}
				else
				{
					$media_js = file_exists($path = JPATH_ROOT.'/media/media/js/mediafield.min.js')
						? 'media/mediafield.min.js'
						: 'media/mediafield.js';
				}

				JHtml::_('script', $media_js, $mootools_framework = true, $media_folder_relative_path = true, false, false, true);

				// Tooltips for image path and image popup preview
				JHtml::_('behavior.tooltip', '.hasTipImgpath', array('onShow' => 'jMediaRefreshImgpathTip'));
				JHtml::_('behavior.tooltip', '.hasTipPreview', array('onShow' => 'jMediaRefreshPreviewTip'));
			}
			else
			{
				jimport('joomla.form.helper'); // JFormHelper
				JFormHelper::loadFieldClass('media');   // JFormFieldMedia
			}

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

		// Flag that indicates if field has values
		$has_values = count($field->value);

		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0]['originalname'] = '';
			$field->value[0] = serialize($field->value[0]);
		}

		// CSS classes of value container
		$value_classes_base     = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes_single   = $value_classes_base . ' fc-expanded' ;
		$value_classes_multiple = $value_classes_base . ($fields_box_placing ? ' floated' : '');

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);


		// URL for modal fileselement view
		$filesElementURL =
			JUri::base(true).'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;layout=image'
				.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;targetid=%s_existingname&amp;thumb_w='.$preview_thumb_w.'&amp;thumb_h='.$preview_thumb_h.'&amp;autoassign='.$autoassign
				.'&amp;'.JSession::getFormToken().'=1';

		$js = '
		var fc_field_dialog_handle_'.$field->id.';

		fcfield_image.debugToConsole["'.$field_name_js.'"] = 0;
		fcfield_image.use_native_apis["'.$field_name_js.'"] = 0;
		';
		$css = '';

		// Handle multiple records
		//if ($multiple)
		if (1)  // This if contains more than multile record handlings, TODO: split it
		{
			// Add the drag and drop sorting feature
			if ($add_ctrl_btns) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					cancel: false,
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($fields_box_placing ? "
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

				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');

				// New element's field name and id
				var uniqueRowN = uniqueRowNum" . $field->id . ";
				var element_id = '" . $elementid . "_' + uniqueRowN;

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}

				newField.find('input.hasvalue').val('');
				newField.find('input.hasvalue').attr('name', element_id + '_hasvalue');
				newField.find('input.hasvalue').attr('id', element_id);

				newField.find('input.originalname').val('');
				newField.find('input.originalname').attr('name','".$fieldname."['+uniqueRowN+'][originalname]');
				newField.find('input.originalname').attr('id', element_id + '_originalname');

				newField.find('.existingname').val('');
				newField.find('.existingname').attr('name','".$fieldname."['+uniqueRowN+'][existingname]');
				newField.find('.existingname').attr('id', element_id + '_existingname');

				newField.find('.fc_preview_msg').html('');
				newField.find('.fc_preview_msg').attr('name', element_id + '_fc_preview_msg');
				newField.find('.fc_preview_msg').attr('id', element_id + '_fc_preview_msg');

				// Update uploader related data
				var fcUploader = newField.find('.fc_file_uploader');
				var upBTN, mulupBTN, mulselBTN;
				if (fcUploader.length)
				{
					// Update uploader attributes
					fcUploader.empty().hide();
					fcUploader.attr('id', fcUploader.attr('data-tagid-prefix') + uniqueRowN);

					// Update button for toggling uploader
					upBTN = newField.find('.fc_files_uploader_toggle_btn');
					upBTN.attr('data-rowno', uniqueRowN);

					mulupBTN = newField.find('.fc-files-modal-link.fc-up');
					mulupBTN.attr('data-href', " . "'" . str_replace('&amp;', '&', sprintf($filesElementURL,  $elementid . "_' + uniqueRowNum".$field->id ." + '")) . "');

					mulselBTN = newField.find('.fc-files-modal-link.fc-sel');
					mulselBTN.attr('data-href', " . "'" . str_replace('&amp;', '&', sprintf($filesElementURL,  $elementid . "_' + uniqueRowNum".$field->id ." + '")) . "');
				}

				// COPY an preview box
				var img_preview = newField.find('img.fc_preview_thumb');
				if (img_preview.length)
				{
					var emptyImg = jQuery('<img class=\"fc_preview_thumb\" id=\"\" src=\"data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=\" alt=\"Preview image\" />');
					emptyImg.attr('id', element_id + '_preview_image');
					emptyImg.insertAfter( img_preview );
					img_preview.remove();
				}

				newField.find('table.fcimg_dbfile_tbl').parent('display', 'none');
				";

			if ($linkto_url) $js .= "
				newField.find('input.imgurllink').val('');
				newField.find('input.imgurllink').attr('name','".$fieldname."['+uniqueRowN+'][urllink]');
				newField.find('input.imgurllink').attr('id', element_id + '_urllink');
				";

			if ($usemediaurl)
			{
				$js .= "
				var elements = ['img_mediaurl', 'img_fetch_btn', 'img_clear_btn'];
				for	(var i = 0; i < elements.length; i++)
				{
					theInput = newField.find('.' + elements[i]).first();
					var el_name = elements[i].replace(/^img_/, '');
					theInput.attr('name','".$fieldname."['+uniqueRowN+']['+el_name+']');
					theInput.attr('id', element_id + '_' + el_name);
				}

				newField.find('input.img_mediaurl').attr('value', ".json_encode($default_mediaurl).");

				newField.find('.fcfield-medialurlvalue').attr('onclick', 'fcfield_image.toggleMediaURL(\'' + element_id + '\', \'".$field_name_js."\');');
				newField.find('.img_fetch_btn').attr('onclick', 'fcfield_image.fetchData(\'' + element_id + '\', \'".$field_name_js."\'); return false;');
				newField.find('.img_clear_btn').attr('onclick', 'fcfield_image.clearData(\'' + element_id + '\', \'".$field_name_js."\'); return false;');
				newField.find('.fcfield_message_box').attr('id','fcfield_message_box_' + element_id);

				// Clear any existing message
				jQuery('#fcfield_message_box_' + element_id).html('');
				";
			}

			if ($usealt) $js .= "
				newField.find('input.imgalt').attr('value', ".json_encode($default_alt).");
				newField.find('input.imgalt').attr('name','".$fieldname."['+uniqueRowN+'][alt]');
				newField.find('input.imgalt').attr('id', element_id + '_alt');
				";

			if ($usetitle) $js .= "
				newField.find('input.imgtitle').attr('value', ".json_encode($default_title).");
				newField.find('input.imgtitle').attr('name','".$fieldname."['+uniqueRowN+'][title]');
				newField.find('input.imgtitle').attr('id', element_id + '_title');
				";

			if ($usedesc) $js .= "
				newField.find('textarea.imgdesc').attr('value', ".json_encode($default_desc).");
				newField.find('textarea.imgdesc').attr('name','".$fieldname."['+uniqueRowN+'][desc]');
				";

			if ($usecust1) $js .= "
				newField.find('input.imgcust1').attr('value', ".json_encode($default_cust1).");
				newField.find('input.imgcust1').attr('name','".$fieldname."['+uniqueRowN+'][cust1]');
				";

			if ($usecust2) $js .= "
				newField.find('input.imgcust2').attr('value', ".json_encode($default_cust2).");
				newField.find('input.imgcust2').attr('name','".$fieldname."['+uniqueRowN+'][cust2]');
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Re-init any select2 elements
				fc_attachSelect2(newField);

				" .
				// Re-init joomla media form field element (J3 only)
				($image_source === -2 && $use_jformfields && !FLEXI_J40GE ? "newField.find('.field-media-wrapper').fieldMedia();" : '') .
				// Clear image preview
				($image_source === -2 && $use_jformfields ? "newField.find('.field-media-wrapper').find('.button-clear').click();" : '')
				;

			// Add new element to sortable objects (if field not in group)
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Set tooltip data placeholders
				var _name = '_existingname';
				newField.find('.media-preview').html('<span class=\"hasTipPreview\" title=\"&lt;strong&gt;" . JText::_('JLIB_FORM_MEDIA_PREVIEW_SELECTED_IMAGE', true)
					. "&lt;/strong&gt;&lt;br /&gt;&lt;span style=&quot;display: block;&quot; id=&quot;' + element_id + _name + '_preview_empty&quot; style=&quot;display:none&quot;&gt;" . JText::_('JLIB_FORM_MEDIA_PREVIEW_EMPTY', true)
					. "&lt;/span&gt;&lt;span style=&quot;display: block;&quot; id=&quot;' + element_id + _name + '_preview_img&quot;&gt;&lt;img src=&quot;&quot; alt=&quot;" . JText::_('JLIB_FORM_MEDIA_PREVIEW_SELECTED_IMAGE', true)
					. "&quot; id=&quot;' + element_id + _name + '_preview&quot; class=&quot;media-preview&quot; style=&quot; style=&quot;max-width:480px; max-height:360&quot; &quot; /&gt;&lt;/span&gt;\"><span class=\"icon-eye\" aria-hidden=\"true\"></span><span class=\"icon-image\" aria-hidden=\"true\"></span> "
					. "</span>');

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

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

				if (tipped_elements.length)
				{
					var imgpath_JTooltips = new Tips(jQuery(newField).find('.hasTipImgpath').get(0), { \"maxTitleChars\": 50, \"fixed\": false, \"onShow\": jMediaRefreshImgpathTip});
					var imgprev_JTooltips = new Tips(jQuery(newField).find('.hasTipPreview').get(0), { \"maxTitleChars\": 50, \"fixed\": false, \"onShow\": jMediaRefreshPreviewTip});
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
				var existingfftag = '".($image_source === 0 ? "select" : "input")."' + '.existingname';
				var originalname = row.find( originalfftag ).val();
				var existingname = row.find( existingfftag ).val();
				var hasvalue = row.find( 'input.hasvalue').val();
				var valcounter = document.getElementById('custom_".$field_name_js."');

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

		// Field not multi-value
		if (!$multiple)
		{
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		// Add field's custom CSS / JS
		$js .= "
			/**
			 * Method Wrappers to class methods (used for compatibility)
			 */

			function fcfield_assignImage".$field->id."(tagid, file, preview_url, keep_modal, preview_caption)
			{
				fcfield_image.assignImage(tagid, file, preview_url, keep_modal, preview_caption, '".$field_name_js."');
			}

			/* Method used as callbacks of AJAX uploader */

			function fcfield_FileFiltered_".$field->id."(uploader, file)
			{
				fcfield_image.fileFiltered(uploader, file, '".$field_name_js."');
			}
			function fcfield_FileUploaded_".$field->id."(uploader, file, result)
			{
				fcfield_image.fileUploaded(uploader, file, result, '".$field_name_js."');
			}
		";
		$css .='';


		/**
		 * Load form JS
		 */

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;

			JText::script("FLEXI_FIELD_IMAGE_CLEAR_MEDIA_URL_FIRST", true);
			JText::script("FLEXI_FIELD_IMAGE_ENTER_MEDIA_URL", true);
			JText::script("FLEXI_FIELD_MEDIA_URL", true);
			JText::script("FLEXI_ERROR", true);

			flexicontent_html::loadFramework('flexi-lib');
			JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/image/js/form.js', array('version' => FLEXI_VHASH));
		}

		// Add field's CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$uploader_html_arr = array();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));


		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="' . $value_classes_multiple . '">'.
					implode('</li><li class="' . $value_classes_multiple . '">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			// Because of JS seeking the parent containers, use UL/LI instead of DIV
			$field->html = !count($field->html) ? '' :
				'<li class="' . $value_classes_single . '">'.
					implode('</li><li class="' . $value_classes_single . '">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
		}


		// This is field HTML that is created regardless of values
		$non_value_html = '<input id="custom_'.$field_name_js.'" class="fc_hidden_value '.($use_ingroup ? '' : $required_class).'" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'" />';
		if ($use_ingroup)
		{
			$field->html[-1] = $non_value_html;
			if ($use_inline_uploaders && $uploader_html->thumbResizer)
			{
				$field->html[-1] = '<div class="label" style="float: left">' . $field->label . '</div>' . $uploader_html->thumbResizer . ' ' . $field->html[-1] . '<div class="clear"></div>';
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
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// For legacy layouts
		$isItemsManager = static::$isItemsManager;
		$isHtmlViewFE   = static::$isHtmlViewFE;

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// The current view is a full item view of the item
		$isMatchedItemView = static::$itemViewId === (int) $item->id;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;

		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);


		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\', '/', $dir);

		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;

		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source === 0 && $all_media && $unique_thumb_method == 0;
		$extra_prefix = $multiple_image_usages  ?  'fld' . $field->id . '_'  :  '';

		$usemediaurl      = (int) $field->parameters->get('use_mediaurl', 0);
		$mediaurl_usage   = (int) $field->parameters->get('mediaurl_usage', 0);
		$default_mediaurl = ($mediaurl_usage == 2) ? $field->parameters->get('default_mediaurl', '') : '';

		$usealt        = (int) $field->parameters->get('use_alt', 0);
		$alt_usage     = (int) $field->parameters->get('alt_usage', 0);
		$default_alt   = ($alt_usage == 2) ? $field->parameters->get('default_alt', '') : '';

		$usetitle      = (int) $field->parameters->get('use_title', 0);
		$title_usage   = (int) $field->parameters->get('title_usage', 0);
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get('default_title', '')) : '';

		$usedesc       = (int) $field->parameters->get('use_desc', 1);
		$desc_usage    = (int) $field->parameters->get('desc_usage', 0);
		$default_desc  = ($desc_usage == 2) ? $field->parameters->get('default_desc', '') : '';

		$usecust1      = (int) $field->parameters->get('use_cust1', 0);
		$cust1_usage   = (int) $field->parameters->get('cust1_usage', 0);
		$default_cust1 = ($cust1_usage == 2) ? JText::_($field->parameters->get('default_cust1', '')) : '';

		$usecust2      = (int) $field->parameters->get('use_cust2', 0);
		$cust2_usage   = (int) $field->parameters->get('cust2_usage', 0);
		$default_cust2 = ($cust2_usage == 2) ? JText::_($field->parameters->get('default_cust2', '')) : '';


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// ***
		// *** SETUP VALUES: retrieve, verify, load defaults, etc
		// ***

		$values = $values ? $values : $field->value;

		// Intro-full mode get their values from item's parameters
		if ($image_source === -1)
		{
			$values = array();

			// Use 'intro' image in multi-item listings
			switch ($view)
			{
				case 'item':
					$_image_name = $field->parameters->get('img-if-for-itemlist', '2') === '1' ? 'intro' : 'fulltext';
					break;

				case 'category':
					$_image_name = $field->parameters->get('img-if-for-catlist', '1') === '1' ? 'intro' : 'fulltext';
					break;

				case 'module':
				case 'sublist':
				default:
					$_image_name = $field->parameters->get('img-if-for-modlist', '1') === '1' ? 'intro' : 'fulltext';
					break;
			}

			if ($item->images)
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

				$_image_path = $item->images->get('image_' . $_image_name, '');
				$_image_path = explode('#', $_image_path);
				$_image_path = $_image_path[0];

				// Use 'fulltext' image if 'intro' image is empty
				if (!$_image_path && $_image_name === 'intro')
				{
					$_image_name = 'fulltext';
					$_image_path = $item->images->get('image_' . $_image_name, '');
				}

				$image_IF = array();
				// field attributes (mode-specific)
				$image_IF['image_size']  = $_image_name;
				$image_IF['image_path']  = $_image_path;
				// field attributes (value)
				$image_IF['originalname'] = basename($_image_path);
				$image_IF['alt']   = $item->images->get('image_' . $_image_name.'_alt', '');
				$image_IF['title'] = $item->images->get('image_' . $_image_name.'_title', '');
				$image_IF['desc']  = $item->images->get('image_' . $_image_name.'_caption', '');
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

			/**
			 * Iterate passing A REFERENCE of THE VALUE to rebuildThumbs() and other methods so that value can be modifled, and data like real image width, height can be added
			 */
			foreach ($values as $index => $value)
			{
				// Non-serialized values, e.g file ids as values (minigallery legacy field)
				if ((string)(int)$value == $value)
				{
					if (isset($files_data[$value]))
					{
						$value = array('originalname' => $files_data[$value]->filename);
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
				if (plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item))
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

		/**
		 * Create default image to be used if  (a) no image assigned  OR  (b) images assigned have been deleted
		 */
		if ( !count($values))
		{
			$image_DF = array();

			// Holds complete relative path and indicates that it is default image for field
			$default_image = '';
			$default_image_custom = $field->parameters->get('default_image_custom', 0);
			if ($default_image_custom)
			{
				$default_image = $this->_get_custom_text($field, $item, 'default_image_custom');
			}

			if (!$default_image)
			{
				$default_image = $field->parameters->get('default_image', '');

			}

			if ($default_image)
			{
				$image_DF['default_image'] = $default_image;
				$image_DF['originalname']  = basename($default_image);

				// Also default image can (possibly) be used across multiple fields, so set flag to add field id to filenames of thumbnails
				$multiple_image_usages      = true;
				$extra_prefix               = 'fld' . $field->id . '_';
				$field->using_default_value = true;
				// field attributes (value)
				$image_DF['alt']          = $default_alt;
				$image_DF['title']        = $default_title;
				$image_DF['desc']         = $default_desc;
				$image_DF['cust1']        = $default_cust1;
				$image_DF['cust2']        = $default_cust2;
				$image_DF['urllink']      = '';

				// Create thumbnails for default image
				if (plgFlexicontent_fieldsImage::rebuildThumbs($field, $image_DF, $item))
				{
					$values = array(serialize($image_DF));
				}
			}
		}


		// ***
		// *** Check for no values and no default value, and return empty display
		// ***

		if (empty($values))
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		// Assign (possibly) altered value array to back to the field
		$field->value = $values;  // This is not done for onDisplayFieldValue, TODO check if this is needed


		// ***
		// *** Default display method depends on view
		// ***

		if ($prop=='display' && ($view=='item' || $view=='category'))
		{
			$_method = $view === 'item'
				? $field->parameters->get('default_method_item', 'display')
				: $field->parameters->get('default_method_cat', 'display_single_total');
		}
		else
		{
			$_method = $prop;
		}
		$cat_link_single_to = $field->parameters->get( 'cat_link_single_to', 1) ;

		// Calculate some flags, SINGLE image display and Link-to-Item FLAG
		$isSingle = isset(self::$single_displays[$_method]);
		$linkto_item = $_method == 'display_link' || $_method == 'display_single_link' || $_method == 'display_single_total_link' || ($view!='item' && $cat_link_single_to && $isSingle);


		// ***
		// *** JS gallery configuration
		// ***

		$usepopup   = (int) $field->parameters->get( 'usepopup',  1 ) ; // use JS gallery
		$popuptype  = $field->parameters->get( 'popuptype', 1 ) ; // JS gallery type

		if (is_numeric($popuptype))
		{
			$popuptype = (int) $popuptype;
		}

		// Different for mobile clients
		$popuptype_mobile = $field->parameters->get( 'popuptype_mobile', $popuptype ) ;  // this defaults to desktop when empty

		if (is_numeric($popuptype_mobile))
		{
			$popuptype_mobile = (int) $popuptype_mobile;
		}

		$PPFX_ = static::$useMobile ? 'popuptype_mobile_' : 'popuptype_';
		$popuptype = static::$useMobile
			? $popuptype_mobile
			: $popuptype;

		// Enable/Disable GALLERY JS according to current view and according to other parameters
		$popupinview = FLEXIUtilities::paramToArray($field->parameters->get('popupinview', array('item', 'category',  'module', 'backend')));

		if (
			($view=='item' && !in_array('item', $popupinview)) ||
			($view=='category' && !in_array('category', $popupinview)) ||
			($view=='module' && !in_array('module', $popupinview)) ||
			(static::$isItemsManager && !in_array('backend', $popupinview)) ||
			$linkto_item ||
			$prop=='display_large' ||
			$prop=='display_original' ||
			$format !== 'html' ||
			$realview=='itemcompare'
		)
		{
			$usepopup = 0;
		}


		/**
		 * If using a non allowed gallery JS, then force fancybox
		 */

		// Only allow multibox and fancybox in items manager
		$iManager_containers = array(1,4);

		// Display types that need special container are not allowed when field in a group
		$no_container_needed = array(1,2,3,4,6);

		if (
			(static::$isItemsManager && !in_array($popuptype, $iManager_containers)) ||
			($is_ingroup && is_numeric($popuptype) && !in_array($popuptype, $no_container_needed))
		)
		{
			$popuptype = 4;
		}


		// Optionally group images from all image fields of current item ... or of all items in view too
		$grouptype  = $field->parameters->get( 'grouptype', 1 ) ;

		// Needed by some js galleries
		$thumb_w_s = $field->parameters->get( 'w_s', 120);
		$thumb_h_s = $field->parameters->get( 'h_s',  90);
		$wl = $field->parameters->get( 'w_l', 800 );
		$hl = $field->parameters->get( 'h_l', 600 );


		// ***
		// *** Hovering ToolTip configuration
		// ***

		$uselegend = $field->parameters->get( 'uselegend', 1 ) ;
		$tooltip_class = 'hasTooltip';

		// Enable/disable according to current view
		$legendinview = FLEXIUtilities::paramToArray($field->parameters->get('legendinview', array('item', 'category')));

		if (
			($view=='item' && !in_array('item', $legendinview)) ||
			($view=='category' && !in_array('category', $legendinview)) ||
			(static::$isItemsManager && !in_array('backend', $legendinview))
		)
		{
			$uselegend = 0;
		}

		// Check only once per field, if tooltip JS is both needed and has been loaded
		static $tooltips_loaded = false;
		static $tooltips_checked = array();
		if ( !$tooltips_loaded && !isset($tooltips_checked[$field->id]) )
		{
			if ($uselegend)
			{
				$add_tooltips = JComponentHelper::getParams( 'com_flexicontent' )->get('add_tooltips', 1);
				if ($add_tooltips)
				{
					JHtml::_('bootstrap.tooltip');
					$tooltips_loaded = true;
				}
			}
			$tooltips_checked[$field->id] = true;
		}


		// ***
		// *** Title/Description in inline thumbnails
		// ***

		$showtitle = $field->parameters->get('showtitle', 0);
		$showdesc  = $field->parameters->get('showdesc', 0);


		// ***
		// *** Link to URL configuration
		// ***

		$linkto_url	= (int) $field->parameters->get('linkto_url', 0);

		if ($linkto_url && $realview !== 'itemcompare')
		{
			$url_target = $field->parameters->get('url_target','_self');
			$isLinkToPopup = $format === 'html' && ($url_target=='multibox' || $url_target=='fancybox');

			// Force opening in new window in backend, if URL target is _self
			if (static::$isItemsManager && $url_target=='_self')
			{
				$url_target = "_blank";
			}

			// Linking to URL in popup, enable gallery JS and select appropriate gallery type
			if ($isLinkToPopup)
			{
				$usepopup = 1;
				if ($url_target === 'multibox') $popuptype = 1;
				if ($url_target === 'fancybox') $popuptype = 4;
			}

			// Not linking in popup, disable adding gallery JS
			else
			{
				$usepopup = 0;
			}
		}

		// Force Fancybox if using Media embeeding, this is until we add support for more galleries ...
		elseif ($usemediaurl)
		{
			$popuptype = 4;
		}


		// ***
		// *** Social website sharing configuration
		// ***

		$useogp = (int) $field->parameters->get('useogp', 0);
		$ogpthumbsize = (int) $field->parameters->get('ogpthumbsize', 2);
		$ogplimit     = (int) $field->parameters->get('ogplimit', 1);


		// ***
		// *** Create thumb paths and thumb base URL
		// ***

		// *** Extra thumbnails sub-folder for various

		// Default value
		if ($field->using_default_value)
		{
			$extra_folder = '';
		}

		// Intro-full image mode
		elseif ($image_source === -1)
		{
			$extra_folder = 'intro_full';
		}

		// Media manager mode
		elseif ($image_source === -2)
		{
			$extra_folder = 'mediaman';
		}

		// Various Folder-mode(s) ... TODO: $image_source > 1
		elseif ($image_source >= 1)
		{
			$extra_folder = 'item_'.$u_item_id.'_field_'.$field->id;  // Folder-mode 1
		}

		// DB-mode
		else
		{
			$extra_folder = '';
		}

		// Create thumbs/image Folder and URL paths
		$thumb_folder  = JPATH_SITE .DS. JPath::clean( $dir .($extra_folder ? DS.$extra_folder : '') );
		$thumb_urlpath = $dir_url .($extra_folder ? '/'. $extra_folder : '');

		if ($field->using_default_value)
		{
			// default image of this field, these are relative paths up to site root
			$orig_urlpath  = str_replace('\\', '/', dirname($image_DF['default_image']));
		}

		// Intro-full image mode, image names are relative paths up to the site root
		elseif ($image_source === -1)
		{
			$orig_urlpath  = str_replace('\\', '/', dirname($image_IF['image_path']));
		}

		// Media manager mode, image names are paths relative to the site root
		elseif ($image_source === -2)
		{
			$orig_urlpath = array();  // calculate later inside value loop
		}

		// Various Folder-mode(s) ... TODO: $image_source > 1
		elseif ($image_source >= 1)
		{
			$orig_urlpath  = $thumb_urlpath . '/original';  // Folder-mode 1
		}

		// DB-mode
		else
		{
			$target_dir    = (int) $field->parameters->get('target_dir', 1);
			$orig_filepath = $target_dir
				? static::$cparams->get('file_path', 'components/com_flexicontent/uploads')
				: static::$cparams->get('media_path', 'components/com_flexicontent/medias');
			$orig_urlpath  = str_replace('\\', '/', $orig_filepath);
		}


		// ***
		// *** Initialize value handling arrays and loop's common variables
		// ***

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

		if (static::$isItemsManager)
		{
			$thumb_size = -1;
		}
		elseif ($view === 'category')
		{
			$thumb_size = $field->parameters->get('thumbincatview',1);
		}
		elseif ($view === 'item')
		{
			$thumb_size = $field->parameters->get('thumbinitemview',2);
		}

		// Cutoff title to a max length
		$alt_image_prefix = flexicontent_html::striptagsandcut($item->title, 60) . ' ' . JText::_('FLEXI_IMAGE') . ' ';



		// ***
		// *** Get layout name
		// ***

		// CASE 0: Add single image display information (e.g. image count)
		if ( $linkto_item )
		{
			$viewlayout = 'value_link_to_item';
		}

		// CASE 1: Handle linking to a URL instead of image zooming popup
		elseif ( $linkto_url )
		{
			$viewlayout = 'value_link_to_url';
		}

		// CASE 2: // No gallery JS ... just append inline info and apply pretext, posttext
		elseif ( !$usepopup )
		{
			$viewlayout = 'value_thumbnail_basic';
		}

		// CASE 3: // Built-in JS gallery
		elseif ( is_numeric($popuptype) )
		{
			$built_in_gallery_names = array(
				// Inline slideshow galleries
				5 => 'galleriffic',
				7 => 'elastislide',

				// Popup galleries
				1 => 'multibox',
				2 => 'rokbox',
				3 => 'jce_mediabox',
				4 => 'fancybox',
				6 => 'widgetkit',
				8 => 'photoswipe',
				9 => 'pannellum',
				10 => 'juxtapose',
			);

			$viewlayout = isset($built_in_gallery_names[$popuptype])
				? 'value_gallery_' . $built_in_gallery_names[$popuptype]
				: 'value_thumbnail_basic';
		}

		// CASE 4: // Custom layout
		else
		{
			$viewlayout = 'value_' . $popuptype;
		}

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app = JFactory::getApplication();

		$is_importcsv        = $app->input->get('task', '', 'cmd') === 'importcsv';
		$import_media_folder = $app->input->get('import_media_folder', '', 'string');
		$id_col              = $app->input->get('id_col', 0, 'int');

		$unique_tmp_itemid = $app->input->get('unique_tmp_itemid', '', 'string');
		$unique_tmp_itemid = substr($unique_tmp_itemid, 0, 1000);

		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);


		// Set a warning message for overriden/changed files: form.php (frontend) or default.php (backend)
		if (!$is_importcsv && empty($unique_tmp_itemid))
		{
			$app->enqueueMessage('Field: ' . $field->label . ' requires variable -unique_tmp_itemid- please update your ' . ($app->isClient('site') ? 'form.php' : 'default.php'), 'warning');
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


		// Optional properies configuration
		$linkto_url = (int) $field->parameters->get('linkto_url', 0);

		$usemediaurl  = (int) $field->parameters->get('use_mediaurl', 0);
		$usealt       = (int) $field->parameters->get('use_alt', 0);
		$usetitle     = (int) $field->parameters->get('use_title', 0);
		$usedesc      = (int) $field->parameters->get('use_desc', 1);
		$usecust1     = (int) $field->parameters->get('use_cust1', 0);
		$usecust2     = (int) $field->parameters->get('use_cust2', 0);


		// ***
		// *** Special steps for image field in Folder-mode(s)
		// ***

		if ($image_source >= 1)
		{
			$dir = $field->parameters->get('dir');
			$unique_tmp_itemid = substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);

			$dest_path = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id . '_field_'.$field->id .DS );
			//if ( $image_source > 1 ) ; // TODO

			/**
			 * Create original images folder if doing CSV import and folder does not exist, and there are images (values) for this field
			 */
			if ($is_importcsv)
			{
				if ($post)
				{
					$dest_path_original = $dest_path. 'original' .DS;

					if (!JFolder::exists($dest_path_original) && !JFolder::create($dest_path_original))
					{
						// Cancel item creation
						$app->enqueueMessage('Field: ' . $field->label . ' : Unable to create folder: ' . $dest_path_original, 'error');
						return false;
					}
				}
			}

			/**
			 * New items have no item id during submission, thus we need to
			 * either rename the temporary name of the images upload folder (NEW ITEM SAVED)
			 * or copy the images upload folder (SAVE AS COPY)
			 */
			elseif ($unique_tmp_itemid && $item->id != $unique_tmp_itemid)
			{
				$temppath = JPath::clean(JPATH_SITE .DS. $dir .DS. 'item_' . $unique_tmp_itemid . '_field_' . $field->id .DS);
				$save_as_copy = $unique_tmp_itemid == (int) $unique_tmp_itemid;

				if (file_exists($temppath))
				{
					$save_as_copy
						? JFolder::copy($temppath, $dest_path)
						: JFolder::move($temppath, $dest_path);
				}
			}
		}


		// ***
		// *** Special steps for image field in MM-mode
		// ***

		if ($image_source === -2)
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
			if ( /*$is_importcsv &&*/ !is_array($v) && $v )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'originalname' => $v
				);
			}


			// Add system message if upload error
			$err_code = isset($files[$n]['error'])
				? $files[$n]['error']
				: UPLOAD_ERR_NO_FILE;

			if ( $err_code && $err_code !== UPLOAD_ERR_NO_FILE )
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
			if ($new_file)
			{
				$new_file_uploaded = $this->uploadOriginalFile($field, $v, $files[$n]);
			}

			// Handle copying original files from a server folder during CSV import
			else if ($is_importcsv && $import_media_folder )
			{
				$filename = basename($v['originalname']);
				$sub_folder = dirname($v['originalname']);
				$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';

				if ($image_source >= 1)
				{
					$src_file_path  = JPath::clean( $srcpath_original . $v['originalname'] );
					$dest_file_path = JPath::clean( $dest_path_original . $filename );
					$result = false;
					if ( JFile::exists($src_file_path) )
					{
						$result = JFile::copy( $src_file_path,  $dest_file_path );
						if ( $result && JPath::canChmod($dest_file_path) )
						{
							chmod($dest_file_path, 0644);
						}
					}
					elseif ( JFile::exists($dest_file_path) )
					{
						$result = true;
					}
					$v['originalname'] = $result
						? $filename  // make sure filename is WITHOUT subfolder
						: '';
				}

				elseif ($image_source == -2)
				{
					$src_file_path  = JPath::clean( $srcpath_original . $v['originalname'] );
					$dest_file_path = JPath::clean( $dest_path_media_full . $filename );
					$result = false;
					if ( JFile::exists($src_file_path) )
					{
						$result = JFile::copy( $src_file_path,  $dest_file_path );
						if ( $result && JPath::canChmod($dest_file_path) )
						{
							chmod($dest_file_path, 0644);
						}
					}
					elseif (JFile::exists($dest_file_path))
					{
						$result = true;
					}
					$v['originalname'] = $result
						? $dest_path_media . $filename  // make sure filename is WITH subfolder
						: '';
				}

				elseif ($image_source === 0)
				{
					if ($v['originalname'] == (int) $v['originalname'] && $id_col >= 2)
					{
						// Keep existing value
					}
					else
					{
						$fman = new FlexicontentControllerFilemanager();
						$fman->runMode = 'interactive';

						$app->input->set('return', null);
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
				else
				{
					// keep value only cleaning it
					$v['originalname'] = JPath::clean( $v['originalname'] );
				}
			}


			// Default values for unset required properties of values
			$v['originalname'] = isset($v['originalname']) ? $v['originalname'] : '';
			$v['existingname'] = isset($v['existingname']) ? $v['existingname'] : '';

			if ( $v['originalname'] || $v['existingname'] )
			{
				// Handle replacing image with a new existing image
				if ( $v['existingname'] )
				{
					// Handle value containing more than just the filepath
					if ($image_source == -2)
					{
						$_tmp = explode('#', $v['existingname']);
						$v['existingname'] = $_tmp[0];
					}
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
			if ($v !== false)
			{
				if ($v)
				{
					$new_filenames[$v['originalname']] = 1;

					// Validate other value properties
					$v['urllink'] = $linkto_url ? flexicontent_html::dataFilter($v['urllink'], 4000, 'URL', 0) : null;
					$v['mediaurl'] = $usemediaurl ? flexicontent_html::dataFilter($v['mediaurl'], 4000, 'URL', 0) : null;
					if ($usemediaurl === 1 && strpos($v['mediaurl'], 'youtube') === false && strpos($v['mediaurl'], 'vimeo') === false)
					{
						$v['mediaurl'] = '';
					}
					$v['alt'] = $usealt ? flexicontent_html::dataFilter($v['alt'], 400, 'STRING', 0) : null;
					$v['title'] = $usetitle ? flexicontent_html::dataFilter($v['title'], 400, 'STRING', 0) : null;
					$v['desc'] = $usedesc ? flexicontent_html::dataFilter($v['desc'], 4000, 'STRING', 0) : null;
					$v['cust1'] = $usecust1 ? flexicontent_html::dataFilter($v['cust1'], 4000, 'STRING', 0) : null;
					$v['cust2'] = $usecust2 ? flexicontent_html::dataFilter($v['cust2'], 4000, 'STRING', 0) : null;

					foreach($v as $propname => $propval)
					{
						if ($propval === null)
						{
							unset($v[$propname]);
						}
					}
				}

				$newpost[$new] = $v;
				$new++;
			}
		}
		$post = $newpost;


    // Remove no longer used files, if limiting existing image list to current field, or if existing image list is hidden/disabled
    if ($image_source === 0 && ($field->parameters->get('auto_delete_unused', 1) || !$field->parameters->get('list_all_media_files', 0)))
    {
			// Get existing field values,
			if (!isset($item->fieldvalues))
			{
				$_fieldvalues = FlexicontentFields::getFieldValsById(null, array($item->id));
				$item->fieldvalues = isset($_fieldvalues[$item->id])
					? $_fieldvalues[$item->id]
					: array();
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
						$this->removeOriginalFile($field, $filename);
						//$app->enqueueMessage('Field: ' . $field->label . ' ['.$n.'] : ' . 'Deleted image file: ' . $filename . ' from server storage');
					}
				}
			}
		}

		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
			if ($v !== null)
			{
				$post[$i] = serialize($v);
			}
		}
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
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
	public function onBeforeDeleteField(&$field, &$item)
	{
		$app = JFactory::getApplication();
		$dir = $field->parameters->get('dir');

		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		if ($image_source >= 1)
		{
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');

			// Delete image folder if it exists
			$dest_path = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id   . '_field_'.$field->id .DS);
			//if ( $image_source > 1 ) ; // TODO

			if (JFolder::exists($dest_path) && !JFolder::delete($dest_path))
			{
				$app->enqueueMessage('Field: ' . $field->label . ': Notice: Unable to delete folder: ' . $dest_path, 'warning');
				return false;
			}
		}
	}


	/**
	 * Method to do extra handling of field's values after all fields have validated their posted data, and are ready to be saved
	 * OVERRIDE Default implementation
	 *
	 * $item->fields['fieldname']->postdata contains values of other fields
	 * $item->fields['fieldname']->filedata contains files of other fields (normally this is empty due to using AJAX for file uploading)
	 */
	function onAllFieldsPostDataValidated( &$field, &$item )
	{
		$auto_intro_full = (int) $field->parameters->get('auto_intro_full', 0);

		if ($auto_intro_full === 1)
		{
			try
			{
				if (is_string($item->images))
				{
					$item->images = json_decode($item->images ?: '{}');
				}
			}
			catch (Exception $e)
			{
				$item->images = json_decode('{}');
			}
			//JFactory::getApplication()->enqueueMessage('<pre>' . print_r($item->images, true) . '</pre>' , 'notice');

			$p = $item->fields[$field->name]->postdata;
			//JFactory::getApplication()->enqueueMessage('<pre>Field: ' . $field->name . '<br>' . print_r($p, true) . '</pre>' , 'notice');

			if ($p && reset($p))
			{
				$value = unserialize(reset($p));
				//JFactory::getApplication()->enqueueMessage(print_r($value, true), 'notice');
				list($file_path, $src_path, $dest_path, $field_index, $extra_prefix) = $this->getThumbPaths($field, $item, $value, $relative = true);
				$thumb_M = $dest_path . 'm_' . $extra_prefix . $value['originalname'];
				$thumb_L = $dest_path . 'l_' . $extra_prefix . $value['originalname'];
				/*JFactory::getApplication()->enqueueMessage('<pre>Field: '
					. $field->name . '<br>'
					. $file_path . "\n"
					. $dest_path . "\n"
					. $field_index . "\n"
					. $thumb_M . "\n"
					. $thumb_L . "\n"
					. '</pre>'
					, 'notice');*/
				$item->images->image_intro = $thumb_M;
				$item->images->image_fulltext = $thumb_L;
			}

			$item->images = json_encode($item->images);
		}
	}


	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
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
						$value = array('originalname' => $files_data[$value]->filename);
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
	public function onIndexSearch(&$field, &$post, &$item)
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
						$value = array('originalname' => $files_data[$value]->filename);
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
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method == 0;
		$extra_prefix = $multiple_image_usages  ?  'fld' . $field->id . '_'  :  '';

		if ( isset($file['name']) && $file['name'] != '' )
		{
			// Only handle the secure folder
			$path = ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS;

			/**
			 * Check that file contents are safe, and make filename file-system safe
			 * transliterating it according to given language (this forces lowercase)
			 */
			$upload_check = flexicontent_upload::check($file, $err_text, $params);

			/**
			 * Sanitize the file name (filesystem-safe, (this should have been done above already))
			 * and also return an unique filename for the given folder
			 */
			$filename     = flexicontent_upload::sanitize($path, $file['name']);
			$filepath     = JPath::clean($path.$filename);

			//perform security check according
			if (!$upload_check)
			{
				if ($format === 'json')
				{
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array(
						'comment' => 'Invalid: ' . $filepath . ': ' . $err_text
					));
					header('HTTP/1.0 415 Unsupported Media Type');
					die('Error. Unsupported Media Type!');
				}

				$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_($err_text), 'error');
				return false;
			}

			//get the extension to record it in the DB
			$ext = strtolower(flexicontent_upload::getExt($filename));

			/**
			 * We allow Joomla default security to execute, if user really uploads an image file, it should not be trigger anyway
			 */
			$upload_success = JFile::upload($file['tmp_name'], $filepath);

			if (!$upload_success)
			{
				if ($format === 'json')
				{
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
					header('HTTP/1.0 409 Conflict');
					jexit('Error. File already exists');
				}

				$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_('FLEXI_UNABLE_TO_UPLOAD_FILE'), 'error');
				return false;
			}
			else
			{
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

				if ($format === 'json')
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

					$sizes = array('l', 'm', 's', 'b');
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
			$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_('FLEXI_UNABLE_TO_UPLOAD_FILE'), 'error');
			return false;
		}
	}


	/**
	 * Decide parameters for calling phpThumb library to create a thumbnail according to configuration
	 */

	function create_thumb( &$field, $filename, $size, $src_path='', $dest_path='', $copy_original=0, $extra_prefix='' )
	{
		$app = JFactory::getApplication();

		static $dest_paths_arr = array();

		// Execute once
		static $initialized = null;

		if (!$initialized)
		{
			$initialized = 1;
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
		}

		// (DB/Folder/Other) Mode of image field
		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		// Image file paths
		$dir = $field->parameters->get('dir');
		$src_path = $src_path ? $src_path : JPath::clean(($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS);
		$dest_path = $dest_path ? $dest_path : JPath::clean( JPATH_SITE .DS. $dir .DS );
		$prefix		= $size . '_' . $extra_prefix;
		$filepath = $dest_path.$prefix.$filename;

		// Parameters for phpthumb
		$ext = strtolower(flexicontent_upload::getExt($filename));
		$w			= $field->parameters->get('w_' . $size, self::$default_widths[$size]);
		$h			= $field->parameters->get('h_' . $size, self::$default_heights[$size]);
		$crop		= $field->parameters->get('method_' . $size);
		$quality= $field->parameters->get('quality');
		$usewm	= $field->parameters->get('use_watermark_' . $size);
		$wmfile	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('wm_' . $size));
		$wmop		= $field->parameters->get('wm_opacity');
		$wmpos	= $field->parameters->get('wm_position');

		// Create destination folder if it does not exist
		if (!JFolder::exists($dest_path) && !JFolder::create($dest_path))
		{
			$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_('Error. Unable to create folders'), 'error');
			return false;
		}

		// Make sure folder is writtable by phpthumb
		if (!isset($dest_paths_arr[$dest_path]) && JPath::canChmod($dest_path))
		{
			/**
			 * JPath::setPermissions() is VERY SLOW, because it does chmod() on all folder / subfolder files
			 */
			//JPath::setPermissions($dest_path, '0644', '0755');
			chmod($dest_path, 0755);
		}

		// Avoid trying to set folder permission multiple times
		$dest_paths_arr[$dest_path] = 1;

		// EITHER copy original image file as current thumbnail (FLAG 'copy_original' is set)
		if ($copy_original)
		{
			$result = JFile::copy( $src_path.$filename,  $filepath );
		}

		// OR Create the thumnail by calling phpthumb
		else
		{
			$result = $this->imagePhpThumb(
				$src_path, $dest_path,
				$prefix, $filename, $ext,
				$w, $h, $quality, $size, $crop,
				$usewm, $wmfile, $wmop, $wmpos
			);
		}

		// Make sure the created thumbnail has correct permissions
		if ($result && JPath::canChmod($filepath))
		{
			chmod($filepath, 0644);
		}

		return $result;
	}


	/**
	 * Load phpThumb if not already loaded
	 */
	private function _load_phpthumb()
	{
		static $loaded = null;

		if ($loaded === null)
		{
			$loaded = 1;
			require_once ( JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpthumb.class.php' );
			// WE DO INCLUDE TO FORCE LOADING OF configuration AFTER the class
			// WE HAVE PATCHED configuration not to double define CONSTANTS and FUNCTIONS
			include ( JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpThumb.config.php' );
		}
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
			$this->_load_phpthumb();
		}

		unset ($phpThumb);
		$phpThumb = new phpThumb();

		// Needed for file paths in windows
		$filepath = str_replace('\\', '/', $src_path . $filename);

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

		/**
		 * Maintain image format for output file for specific file extensions
		 * this is needed to keep features like transparency support,
		 * for other formats use default (unless changed this is jpg)
		 */
		if ( in_array( $ext, array('png', 'gif', 'ico', 'webp') ) )
		{
			$phpThumb->setParameter('f', $ext);
		}

		$output_filename = $dest_path . $prefix . $filename ;

		// Catch case of bad permission for thumbnail files but good permission for containing folder ...
		if (file_exists($output_filename))
		{
			JFile::delete($output_filename);
		}

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

	public function removeOriginalFile($field, $filename)
	{
		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.path');

		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);

		// Folder-mode 1
		if ($image_source >= 1)
		{
			$thumbfolder = JPath::clean(JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id);
			$origfolder  = $thumbfolder .DS. 'original' .DS;
		}

		// DB-mode
		elseif ($image_source === 0)
		{
			$thumbfolder = JPath::clean( JPATH_SITE .DS. $field->parameters->get('dir') );
			$origfolder  = JPath::clean( ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) );
		}

		// Negative, intro-full mode, this should be unreachable, because
		else
		{
			echo '<div class="alert alert-warning">image field id: ' . $field->id . ' is in intro-full mode, removeOriginalFile() should not have been called</div>';
		}

		// a. Delete the thumbnails
		$errors = array();
		$sizes  = array('l','m','s','b');

		foreach ($sizes as $size)
		{
			$dest_path = $thumbfolder . DS . $size . '_' . $filename;

			if (JFile::exists($dest_path) && !JFile::delete($dest_path))
			{
				$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $dest_path, 'warning');
			}
		}

		// b. Delete the original image from file manager
		$src_path = JPath::clean($origfolder.DS.$filename);

		if (!JFile::delete($src_path))
		{
			$app->enqueueMessage('Field: ' . $field->label . ' : ' . JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') . ': ' . $src_path, 'warning');
		}

		// For DB-mode, also delete file from database
		if ($image_source === 0)
		{
			$query = 'DELETE FROM #__flexicontent_files  WHERE ' . $db->quoteName('filename') . ' = ' . $db->Quote($filename);
			$db->setQuery($query)->execute();
		}

		return true;
	}



	/**
	 * Smart image thumbnail size check and rebuilding
	 */

	function rebuildThumbs(&$field, &$value, &$item)
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

		// Unseriaze value if not already done
		if ( !is_array($value) )
		{
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array(
				'originalname' => $value
			);
		}

		// Check for empty filename
		$value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';

		if (!$value['originalname'])
		{
			return;
		}

		// Do not thumbnail URLs (we only check for absolute URLs)
		if (preg_match("#^(?:[a-z]+:)?//#", $value['originalname']))
		{
			return true;
		}

		$filename = basename($value['originalname']);


		// Extra thumbnails sub-folder
		list($file_path, $src_path, $dest_path, $field_index, $extra_prefix) = $this->getThumbPaths($field, $item, $value);

		// Return cached data, avoiding rechecking/recreating image thumbnails multiple times
		if (isset($images_processed[$field_index][$file_path]))
		{
			return $images_processed[$field_index][$file_path];
		}

		// Check for original file have been deleted or is not being a file
		if (!file_exists($file_path) || !is_file($file_path))
		{
			return ($images_processed[$field_index][$file_path] = false);
		}


		/**
		 * Enforce protection of original image files for any type of Folder-mode
		 * But do not try to protect folder of default image
		 */
		if (empty($value['default_image']))
		{
			$this->protectImagePath($field, $src_path);
		}


		/**
		 * Check dimension of thumbs and rebuild as needed
		 */

		$filesize	= getimagesize($file_path);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];

		$sizes = array('l', 'm', 's', 'b');

		// Always create an unprefixed small thumb, it is needed when assigning preview
		if ($extra_prefix)
		{
			$sizes[] = '_s';
		}

		$thumbres = true;

		foreach ($sizes as $size)
		{
			$check_small = $size=='_s';
			$size = $check_small ? 's' : $size;
			$thumbname = $size . '_' . ($check_small ? '' : $extra_prefix) . $filename;
			$path	= JPath::clean($dest_path .DS. $thumbname);

			if (file_exists($path))
			{
				$filesize = getimagesize($path);
				$filesize_w = $filesize[0];
				$filesize_h = $filesize[1];
				$thumbnail_exists = true;

				// Set real sizes for using the with SRCSET
				if (!$check_small)
				{
					$value['size_w_' . $size] = $filesize_w;
					$value['size_h_' . $size] = $filesize_h;
				}
			}
			else
			{
				$thumbnail_exists = false;
			}

			if ($thumbnail_exists && $check_small)
			{
				continue;
			}

			$param_w = $field->parameters->get('w_' . $size, self::$default_widths[$size]);
			$param_h = $field->parameters->get('h_' . $size, self::$default_heights[$size]);
			$crop = $field->parameters->get('method_' . $size);
			$usewm = $field->parameters->get('use_watermark_' . $size);
			$copyorg = $field->parameters->get('copy_original_' . $size, 1);
			$copy_original = ($copyorg==2) || ($origsize_w == $param_w && $origsize_h == $param_h && !$usewm && $copyorg==1);

			if (!$crop)
			{
				$check_w = $param_w > $origsize_w ? $origsize_w : $param_w;
				$check_h = $param_h > $origsize_h ? $origsize_h : $param_h;
			}

			// Check if size of file is not same as parameters and recreate the thumbnail
			if (
					!$thumbnail_exists ||
					( $crop==0 && (
													(abs($filesize_w - $check_w)>1 ) &&  // scale width can be larger than it is currently
													(abs($filesize_h - $check_h)>1 )     // scale height can be larger than it is currently
												)
					) ||
					( $crop==1 && (
													($param_w <= $origsize_w && abs($filesize_w - $param_w)>1 ) ||  // crop width can be smaller than it is currently
													($param_h <= $origsize_h && abs($filesize_h - $param_h)>1 )     // crop height can be smaller than it is currently
												)
					)
			)
			{
				/*if (JFactory::getUser()->authorise('core.admin', 'root.1'))
				{
					echo "FILENAME: ".$thumbname.", ".($crop ? "CROP" : "SCALE").", ".($thumbnail_exists ? "OLDSIZE(w,h): $filesize_w,$filesize_h" : "")
						."  NEWSIZE(w,h): $param_w,$param_h <br />"
						."  ORIGSIZE(w,h): $origsize_w,$origsize_h <br />"
						;
				}*/
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

		$ignored = array($item->id);

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
			<span class="label text-white bg-info label-info fc-upload-box-lbl">'.JText::_( $server_limit_exceeded ? 'FLEXI_UPLOAD_LIMITS' : 'FLEXI_UPLOAD_LIMIT' ).'</span>
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
		$field->parameters->set('image_source', 1);

		static $fc_folder_mode_err;

		if (isset($fc_folder_mode_err[$field->id]))
		{
			return 1;
		}

		JFactory::getApplication()->enqueueMessage("Error source-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id, 'warning' );
		$fc_folder_mode_err[$field->id] = 1;

		return 1;
	}



	/**
	 * Get DB data for the given file IDs
	 */

	function getFileData($fid, $published = 1, $extra_select = '')
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$file_ids = (array) $fid;
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

	function getThumbPaths($field, $item, $value, $rel = false)
	{
		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;
		$target_dir   = (int) $field->parameters->get('target_dir', 1);
		$dir = $field->parameters->get('dir');

		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;

		// Extract sub-folder pathfrom the (filepath) value (if it exists)
		$subpath  = dirname($value['originalname']);
		$subpath  = $subpath !== '.' ? $subpath . DS  : '';
		$filename = basename($value['originalname']);


		/**
		 * Find extra thumbnails sub-folder
		 * Find original folder path
		 */

		// Default value, ('default_image' is a FILE path)
		if (!empty($value['default_image']))
		{
			$extra_folder = '';
			$src_path = JPath::clean( (!$rel ? JPATH_SITE . DS : '') . dirname($value['default_image']) .DS );
		}

		// Intro-full image mode, ('image_path' is a FILE path of an intro / full image)
		elseif ($image_source === -1)
		{
			$extra_folder = 'intro_full';
			$src_path = JPath::clean( (!$rel ? JPATH_SITE . DS : '') . dirname($value['image_path']) .DS );
		}

		// Media manager mode
		elseif ($image_source === -2)
		{
			$extra_folder = 'mediaman';
			$src_path = JPath::clean( JPATH_SITE .DS );
		}

		// Folder-mode 1
		elseif ($image_source >= 1)
		{
			$extra_folder = 'item_'.$u_item_id . '_field_'.$field->id;
			$src_path = JPath::clean( (!$rel ? JPATH_SITE . DS : '') . $dir .DS. $extra_folder .DS. 'original' .DS );
		}

		// DB-mode
		else
		{
			$extra_folder = '';
			$src_path = JPath::clean( ($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS );
		}

		// Add value-extracted subpath to original folder
		$src_path = JPath::clean( $src_path . ($subpath ? $subpath . DS : '')  );

		// Full path of original file  - and - Destination folder
		$file_path = JPath::clean( $src_path . $filename );
		$dest_path = JPath::clean( (!$rel ? JPATH_SITE . DS : '') . $dir .DS. ($extra_folder ? $extra_folder .DS : '') );


		/**
		 * Create an index that differentiatiates same file in different fields
		 */

		// Folder-mode 1
		if ($image_source >= 1)
		{
			// TODO other folder modes
			$field_index = 'item_'.$u_item_id . '_field_'.$field->id;
		}

		// DB-mode or intro-full mode
		else
		{
			$field_index = 'field_'.$field->id;
		}

		// Configuration
		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;

		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);

		// *** FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = $image_source == 0 && $all_media && $unique_thumb_method == 0;
		$multiple_image_usages = $multiple_image_usages || !empty($value['default_image']);
		$extra_prefix = $multiple_image_usages  ?  'fld' . $field->id . '_'  :  '';

		//echo 'file_path: ' . $file_path ."<br/>" . 'dest_path: ' . $dest_path ."<br/><br/>";
		return array($file_path, $src_path, $dest_path, $field_index, $extra_prefix);
	}



	/**
	 * Enforce protection of original image files any Folder-mode
	 */

	protected function protectImagePath($field, $src_path)
	{
		$image_source = (int) $field->parameters->get('image_source', 0);
		$image_source = $image_source > 1 ? $this->nonImplementedMode($image_source, $field) : $image_source;

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

	/**
	 * TODO move this method to parent field class or to helper class
	 */
	private function _get_custom_text($field, $item, $paramName, $paramNameCode = null)
	{
		// Check if using 'auto_value_code', clear 'auto_value', if function not set
		$custom_text = (int) $field->parameters->get($paramName, 0);
		if ($custom_text === 2)
		{
			$paramNameCode = $paramNameCode ?: $paramName . '_code';
			$custom_text_code = $field->parameters->get($paramNameCode, '');
			$custom_text_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $custom_text_code);
		}
		$custom_text = $custom_text === 2 && !$custom_text_code ? 0 : $custom_text;

		if (!$custom_text)
		{
			return;
		}

		$return = array();

		switch($custom_text)
		{
			case 2:     // AUTOMATIC value, via function
				try {
					ob_start();

					$old_error_reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
					$old_error_handler = set_error_handler(array($this, '_custom_error_handler'));

					$return = eval($custom_text_code);

					error_reporting($old_error_reporting);
					set_error_handler($old_error_handler);

					$errors = trim(ob_get_contents());
					ob_clean();

					if ($errors) JFactory::getApplication()->enqueueMessage( 'Field \'<b>' . $field->label . '</b>\' : <br/> <pre>' . $errors . '</pre>', 'notice');
				}
				catch (ParseError $e) {
					JFactory::getApplication()->enqueueMessage( "Automatic value custom code, failed with: <pre>" . $e->getMessage() . '</pre>', 'warning');
				}
				break;
		}
		return $return;
	}

	private function _custom_error_handler($errno, $errstr, $errfile, $errline)
	{
		if (!(error_reporting() & $errno))
		{
			// This error code is not included in error_reporting, so let it fall through to the standard PHP error handler
			return false;
		}

		switch ($errno) {
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
			case E_STRICT:
				echo("NOTICE: $errstr at line: $errline \n");
				break;

			case E_WARNING:
			case E_USER_WARNING:
				echo("WARNING: $errstr at line: $errline \n");
				break;

			case E_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				echo("ERROR: $errstr at line: $errline \n");

			default:
				echo("UNKNOWN ERROR at line: $errline \n");
		}
	}
}
