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
JLoader::register('FlexicontentControllerFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');  // we use JPATH_BASE since controller exists in frontend too
JLoader::register('FlexicontentModelFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');  // we use JPATH_BASE since model exists in frontend too

class plgFlexicontent_fieldsMediafile extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = array('share_file_form', 'share_file_email');  // Field's methods allowed to be called via AJAX

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

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';
		$tip_class     = $tooltip_class;  // Compatibility with older custom templates

		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 1);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);
		$use_myfiles  = (int) $field->parameters->get('use_myfiles', '1');

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;

		// Inline file property editing
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;  // 1: file selection only,  0: inline file properties editing
		$top_notice = '';//$use_ingroup ? '<div class="alert alert-warning">Field group mode is not implenent in current version, please disable</div>' : '';

		$iform_allowdel = 0;//$field->parameters->get('iform_allowdel', 1);
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		$show_values_expand_btn = (int) $field->parameters->get('show_values_expand_btn', 1);
		$form_file_preview  = (int) $field->parameters->get('form_file_preview', 2);

		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_access= $inputmode==1 ? 0 : $field->parameters->get('iform_access',0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		$iform_stamp = $inputmode==1 ? 0 : $field->parameters->get('iform_stamp', 0);

		$secure_default = 0; //!$iform_dir   ? 0 : (int) $field->parameters->get('iform_dir_default', 1);
		$stamp_default  = 0; //!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 1);

		$mediapath   = $cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath    = $cparams->get('file_path', 'components/com_flexicontent/uploads');
		$imageexts   = array('png', 'ico', 'gif', 'jpg', 'jpeg', 'webp', 'bmp');

		$target_dir = $field->parameters->get('target_dir', 0);
		$base_url   = JUri::root(true) . '/' . (!$target_dir ? $mediapath : $docspath);
		$base_url   = str_replace(DS, '/', $base_url);

		$thumb_size_resizer = (int) $field->parameters->get('thumb_size_resizer', 0);
		$thumb_size_default = (int) $field->parameters->get('thumb_size_default', 120);
		$preview_thumb_w = $preview_thumb_h = 600;

		// Inline uploaders flags
		$use_inline_uploaders = 1;
		$usemediaurl = 0; //(int) $field->parameters->get('use_mediaurl', 0);
		$file_btns_position = 0; //(int) $field->parameters->get('file_btns_position', 0);


		/**
		 * Slider for files grid view
		 */

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

		// Empty field value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$files_data = array();
			$form_data = array();
			$field->value = array();
		}

		// Non empty field value
		else
		{
			$file_ids  = array();
			$form_data = array();

			// Check if reloading user data after form reload (e.g. due to form validation error)
			$v = reset($field->value);
			if (is_array($v) && isset($v['file-id']))
			{
				foreach($field->value as $v)
				{
					if (!isset($v['secure'])) $v['secure'] = 0; //!$iform_dir   ? 0 : (int) $field->parameters->get('iform_dir_default', 0);
					if (!isset($v['stamp']))  $v['stamp']  = 0; //!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0);
					$file_ids[] = $v['file-id'];
					$form_data[$v['file-id']] = $v;
				}
			}
			else
			{
				$file_ids = $field->value;
			}

			// Get file data for given file ids
			$files_data = $this->getFileData( $file_ids, $published=false );

			// Do not skip values if in fieldgroup
			if ($use_ingroup)
			{
				foreach($field->value as $i => $v)
				{
					$file_id = is_array($v) && isset($v['file-id']) ?  $v['file-id']  :  $v;
					$field->value[$i] = isset($files_data[$file_id]) ? (int)$file_id : 0;
				}
			}
			else
			{
				$field->value = array_keys($files_data);
			}
		}

		// Flag that indicates if field has values
		$has_values = !empty($field->value);

		// Inline mode needs an default value, TODO add for popup too ?
		if (empty($field->value) || $use_ingroup)
		{
			// Create an empty file properties value, used by code that creates empty inline file editing form fields
			if (empty($field->value))
			{
				$field->value = array(0 => 0);
			}
			$files_data[0] = (object)array(
				'id' => '', 'filename' => '', 'filename_original' => '', 'altname' => '', 'description' => '',
				'url' => '',
				'secure' => 0, //(!$iform_dir  ? 0 : (int) $field->parameters->get('iform_dir_default', 0)),
				'stamp' => 0, //(!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0)),
				'ext' => '', 'published' => 1,
				'language' => $field->parameters->get('iform_lang_default', '*'),
				'access' => (int) $field->parameters->get('iform_access_default', 1),
				'hits' => 0,
				'uploaded' => '', 'uploaded_by' => 0, 'checked_out' => false, 'checked_out_time' => ''
			);
		}

		// Button for popup file selection
		$autoassign = (int) $field->parameters->get( 'autoassign', 1 ) ;
		$filesElementURL = JUri::base(true)
			.'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component'
			.'&amp;index=%s'
			.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;autoassign='.$autoassign
			.'&amp;filter_uploader='.$user->id
			.'&amp;targetid=%s'
			.'&amp;existing_class=fc_filedata_storage_name'
			.'&amp;' . JSession::getFormToken() . '=1';

		// URL for modal fileselement view
		$addExistingURL = sprintf($filesElementURL, '__rowno__', '__thisid__');

		$_prompt_txt = JText::_( 'FLEXI_FIELD_FILE_SELECT_FILE' );  //JText::_( 'FLEXI_ADD_FILE' );

		// CSS classes of value container
		$value_classes_base     = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes_single   = $value_classes_base;// . ' fc-expanded' ;
		$value_classes_multiple = $value_classes_base . ($fields_box_placing ? ' floated' : '');

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		$js = "
			var fc_field_dialog_handle_".$field->id.";


			function file_fcfield_del_existing_value".$field->id."(el)
			{
				var el  = jQuery(el);
				var box = jQuery(el).closest('.fcfieldval_container');
				if ( el.prop('checked') ) {
					box.find('.fc_preview_thumb').css('opacity', 0.4);
					box.find('.fc_filedata_txt').css('text-decoration', 'line-through');
				} else {
					box.find('.fc_preview_thumb').css('opacity', 1);
					box.find('.fc_filedata_txt').css('text-decoration', '');
				}
			}


			function fc_openFileSelection_".$field->id."(element)
			{
				var obj = jQuery(element);
				var url = obj.attr('data-href');

				url = url.replace( '__rowno__',  obj.attr('data-rowno') ? obj.attr('data-rowno') : '' );
				url = url.replace( '__thisid__', obj.attr('id') ? obj.attr('id') : '' );

				//window.console.log(obj.attr('data-rowno'));
				//window.console.log(url);

				fcfield_mediafile.dialog_handle['".$field_name_js."'] = fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".JText::_('FLEXI_SELECT', true)."', paddingW: 10, paddingH: 16});
				return false;
			}
		";
		$css = '';

		// Handle multiple records
		if ($multiple)
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
				var fname_pfx  = '" . $fieldname . "[' + uniqueRowN + ']';

				var theInput = newField.find('input.inlinefile-del').first();
				theInput.removeAttr('checked');
				theInput.attr('name', fname_pfx + '[file-del]');
				theInput.attr('id', element_id + '_file-del');
				newField.find('.inlinefile-del-lbl').first().attr('for', element_id + '_file-del').attr('id', element_id + '_file-del-lbl');

				var theInput = newField.find('input.fc_filedata').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[file-data]');
				theInput.attr('id', element_id + '_file-data');
				theInput.attr('data-rowno', uniqueRowN);

				newField.find('.inlinefile-data-lbl').first().attr('for', element_id + '_file-data-txt').attr('id', element_id + '_file-data-lbl');

				var theInput = newField.find('input.fc_fileid').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[file-id]');
				theInput.attr('id', element_id + '_file-id');

				newField.find('.fc_filedata_txt_nowrap').html('-');
				newField.find('.fc_filedata_title').html('-');

				var theInput = newField.find('input.fc_filedata_txt').first();
				theInput.attr('value', '');
				theInput.removeAttr('data-filename')
					.removeAttr('data-wfpreview').removeAttr('data-wfpeaks');
				theInput.data('filename', null);
				theInput.attr('name', fname_pfx + '[file-data-txt]');
				theInput.attr('id', element_id + '_file-data-txt');

				var imgPreview = newField.find('.fc_preview_thumb').first();
				imgPreview.attr('id', element_id + '_image_preview');
				imgPreview.attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
				imgPreview.parent().find('.fc_preview_msg').html('');
				imgPreview.parent().find('.fc_preview_text').html('');
				".($form_file_preview != 1 ? '
				imgPreview.parent().hide();' : '')."

				".($iform_title ? "
				var theInput = newField.find('input.fc_filetitle').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[file-title]');
				theInput.attr('id', element_id + '_file-title');
				newField.find('.inlinefile-title-lbl').first().attr('for', element_id + '_file-title').attr('id', element_id + '_file-title-lbl');
				" : "")."

				".($iform_lang ? "
				var theInput = newField.find('select.fc_filelang').first();
				theInput.get(0).selectedIndex = 0;
				theInput.attr('name', fname_pfx + '[file-lang]');
				theInput.attr('id', element_id + '_file-lang');
				newField.find('.inlinefile-lang-lbl').first().attr('for', element_id + '_file-lang').attr('id', element_id + '_file-lang-lbl');
				" : "")."

				".($iform_access ? "
				var theInput = newField.find('select.fc_fileaccess').first();
				//theInput.get(0).selectedIndex = 0;
				theInput.val('1');
				theInput.attr('name', fname_pfx + '[file-access]');
				theInput.attr('id', element_id + '_file-access');
				newField.find('.inlinefile-access-lbl').first().attr('for', element_id + '_file-access').attr('id', element_id + '_file-access-lbl');
				" : "")."

				".($iform_dir ? "
				var nr = 0;
				newField.find('.inlinefile-secure-info').remove();
				newField.find('.inlinefile-secure-data').show();
				newField.find('input.fc_filedir').each(function() {
					var elem = jQuery(this);
					elem.removeAttr('disabled');
					elem.attr('name', fname_pfx + '[secure]');
					elem.attr('id', element_id + '_secure_'+nr);
					if (elem.val() == " . (int) $secure_default .")
					{
						elem.next().addClass('active');
						elem.prop('checked', true);
					}
					else
					{
						elem.next().removeClass('active');
						elem.prop('checked', false);
					}
					elem.next().attr('for', element_id + '_secure_'+nr).attr('id', element_id + '_file-secure'+nr+'-lbl');
					nr++;
				});
				" : "")."

				".($iform_stamp ? "
				var nr = 0;
				newField.find('.inlinefile-stamp-info').remove();
				newField.find('.inlinefile-stamp-data').show();
				newField.find('input.fc_filestamp').each(function() {
					var elem = jQuery(this);
					elem.removeAttr('disabled');
					elem.attr('name', fname_pfx + '[stamp]');
					elem.attr('id', element_id + '_stamp_'+nr);
					if (elem.val() == " . (int) $stamp_default .")
					{
						elem.next().addClass('active');
						elem.prop('checked', true);
					}
					else
					{
						elem.next().removeClass('active');
						elem.prop('checked', false);
					}
					elem.next().attr('for', element_id + '_stamp_'+nr).attr('id', element_id + '_file-stamp'+nr+'-lbl');
					nr++;
				});
				" : "")."

				".($iform_desc ? "
				var theInput = newField.find('textarea.fc_filedesc').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[file-desc]');
				theInput.attr('id', element_id + '_file-desc');
				newField.find('.inlinefile-desc-lbl').first().attr('for', element_id + '_file-desc').attr('id', element_id + '_file-desc-lbl');
				" : "")."

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}

				// Update button for modal file selection
				var theBTN = newField.find('span.addfile');
				theBTN.attr('id', element_id + '_addfile');
				theBTN.attr('data-rowno', uniqueRowN);

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
					mulupBTN.attr('id', element_id + '_mul_uploadvalue');
					mulupBTN.attr('data-rowno', uniqueRowN);

					mulselBTN = newField.find('.fc-files-modal-link.fc-sel');
					mulselBTN.attr('id', element_id + '_selectvalue');
					mulselBTN.attr('data-rowno', uniqueRowN);
				}
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

				// Init WaveSurfer
				var el = newField.find('.fc_mediafile_controls').first();
				el.attr('id', 'fc_mediafile_controls_" . $field_name_js . '_' . "' + uniqueRowN);

				var el = newField.find('.fc_mediafile_audio_spectrum_box').first();
				el.attr('id', 'fc_mediafile_audio_spectrum_box_" . $field_name_js . '_' . "' + uniqueRowN);

				var el = newField.find('.fc_mediafile_audio_spectrum').first();
				el.attr('id', 'fc_mediafile_audio_spectrum_" . $field_name_js . '_' . "' + uniqueRowN);
				el.empty();

				fcfield_mediafile.initValue('".$field->name."_' + uniqueRowN, '".$field_name_js."');
				";

			// Add new element to sortable objects (if field not in group)
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				// Attach bootstrap event on new element
				fc_bootstrapAttach(newField);

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only

				// return HTML Tag ID of field containing the file ID, needed when creating file rows to assign multi-fields at once
				return '".$elementid."_' + (uniqueRowNum".$field->id." - 1) + '_file-id';
			}


			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				if ( 1 ) // A deleted container always has a value, thus decrement (or empty) the counter value in the 'required' form element
				{
					var valcounter = document.getElementById('custom_".$field_name_js."');
					if (valcounter) {
						valcounter.value = ( !valcounter.value || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
					}
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
		else
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

			function fcfield_assignFile".$field->id."(value_container_id, file, keep_modal)
			{
				fcfield_mediafile.assignFile(value_container_id, file, keep_modal, '".$field_name_js."');
			}

			/* Method used as callbacks of AJAX uploader */

			function fcfield_FileFiltered_".$field->id."(uploader, file)
			{
				fcfield_mediafile.fileFiltered(uploader, file, '".$field_name_js."');
			}
			function fcfield_FileUploaded_".$field->id."(uploader, file, result)
			{
				fcfield_mediafile.fileUploaded(uploader, file, result, '".$field_name_js."');
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

			JText::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_RESPONSE_PARSING_FAILED', true);
			JText::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_FILE_NOT_FOUND', true);
			JText::script('FLEXI_PLEASE_UPLOAD_A_FILE', true);

			//flexicontent_html::loadFramework('wavesurfer');
			flexicontent_html::loadFramework('flexi-lib');
			JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
			$document->addScript('https://unpkg.com/wavesurfer.js/dist/wavesurfer.min.js');
			//$document->addScript('https://unpkg.com/wavesurfer.js/dist/plugin/wavesurfer.cursor.js');
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/mediafile/js/form.js', array('version' => FLEXI_VHASH));

			$js .= "
			jQuery(document).ready(function()
			{
				audio_spectrum_conf['" . $field_name_js . "'] = [];
				audio_spectrum_conf['" . $field_name_js . "']['waveColor'] = '" . $field->parameters->get( 'ws_wave_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['progressColor'] = '" .$field->parameters->get( 'ws_progress_color', '#d0d0d0' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorColor'] = '" .  $field->parameters->get( 'ws_cursor_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorWidth'] = '" . (int) $field->parameters->get( 'ws_cursor_width', 2 ) . "';

				new fc_Waveform_LazyLoad(
					document.getElementsByTagName('body')[0], {
						rootMargin: '0px 0px',
						threshold: 0.25
					}
				);
			});
			";
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

		$drop_btn_class =
			(FLEXI_J40GE
				? 'btn btn-sm toolbar dropdown-toggle dropdown-toggle-split'
				: 'btn btn-small toolbar dropdown-toggle'
			);
		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		foreach($field->html as $n => &$_html)
		{
			$uploader_html = $uploader_html_arr[$n];
			$_html = '
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here)
					.($use_inline_uploaders && !$file_btns_position ? '
					<div class="buttons btn-group fc-iblock ' . (FLEXI_J40GE ? ' dropdown ' : '') . '">
						<span role="button" class="' . $drop_btn_class . ' fcfield-addvalue ' . $font_icon_class . '" data-toggle="dropdown" data-bs-toggle="dropdown">
							<span class="caret"></span>
						</span>
						<ul class="dropdown-menu dropdown-menu-right" role="menu">
							<li>'.$uploader_html->toggleBtn.'</li>
							<li>'.$uploader_html->multiUploadBtn.'</li>
							' . ($use_myfiles > 0 ? '<li>'.$uploader_html->myFilesBtn.'</li>' : '') . '
							<li>'.$uploader_html->mediaUrlBtn.'</li>
						</ul>
					</div>
					'.$uploader_html->clearBtn.'
					' : '') . '
				</div>
				'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
				').'

				<div class="fc-field-props-box" ' . (!$multiple ? 'style="width: 96%; max-width: 1400px;"' : ''). '>

					'.($use_inline_uploaders && ($file_btns_position || !$add_ctrl_btns) ? '
					<div class="fcclear"></div>
					<div class="btn-group" style="margin: 4px 0 16px 0; display: inline-block;">
						<div class="'.$input_grp_class.' fc-xpended-btns">
							'.$uploader_html->toggleBtn.'
							'.$uploader_html->multiUploadBtn.'
							' . ($use_myfiles > 0 ? $uploader_html->myFilesBtn : '') . '
							'.$uploader_html->mediaUrlBtn.'
							'.$uploader_html->clearBtn.'
						</div>
					</div>
					' : '') . '

				'.$_html.'

				</div>
				';
		}
		unset($_html);

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
			$field->html = '<div class="'.$value_classes_single.'">' . $field->html[0] .'</div>';
		}


		// This is field HTML that is created regardless of values
		$non_value_html = '';//'<input id="custom_'.$field_name_js.'" class="fc_hidden_value '.($use_ingroup ? '' : $required_class).'" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'" />';
		if ($use_ingroup)
		{
			$uploader_html = reset($uploader_html_arr);

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

		// Add toggle button for: Compact values view (= multiple values per row)
		$show_values_expand_btn = $formlayout === 'field_InlineBoxes' ? $show_values_expand_btn : 0;
		if (!$use_ingroup && $show_values_expand_btn)
		{
			$field->html = '
			<button type="button" class="fcfield-expand-view-btn btn btn-small" data-expandedFieldState="0" aria-label="' . JText::_('FLEXI_EXPAND') . '"
				onclick="fc_toggleCompactValuesView(this, jQuery(this).closest(\'.container_fcfield\'));"
			>
				<span class="fcfield-expand-view ' . $font_icon_class . '" aria-hidden="true"></span>&nbsp; ' . JText::_('FLEXI_EXPAND', true) . '
			</button>
			' . $field->html;
		}

		// Button for popup file selection
		/*if (!$use_ingroup) $field->html .= '
			<input id="'.$elementid.'" class="'.$required_class.' fc_hidden_value" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($n ? $n : '').'" />';*/
		if ($top_notice) $field->html = $top_notice.$field->html;
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
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 1 ) ;


		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		static $langs = null;
		if ($langs === null) $langs = FLEXIUtilities::getLanguages('code');

		// Load the tooltip library according to configuration, FLAG is an array to have a different check per field ID
		// This is needed ONLY for fields that also have a configuration parameter, for this field is redundant
		static $tooltips_added = array();
		if ( empty($tooltips_added[$field->id]) )
		{
			$add_tooltips = JComponentHelper::getParams( 'com_flexicontent' )->get('add_tooltips', 1);
			if ($add_tooltips) JHtml::_('bootstrap.tooltip');
			$tooltips_added[$field->id] = true;
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// some parameter shortcuts
		$tooltip_class = 'hasTooltip';


		$display_total_count = $field->parameters->get( 'display_total_count', 0 ) ;
		$total_count_label = JText::_($field->parameters->get( 'total_count_label', 'FLEXI_FIELD_FILE_TOTAL_FILES' ));

		$display_total_hits  = $field->parameters->get( 'display_total_hits', 0 ) ;
		$total_hits_label = JText::_($field->parameters->get( 'total_hits_label', 'FLEXI_FIELD_FILE_TOTAL_DOWNLOADS' ));

		$useicon = $field->parameters->get( 'useicon', 0 ) ;
		$lowercase_filename = $field->parameters->get( 'lowercase_filename', 1 ) ;

		$display_filename	= (int) $field->parameters->get( 'display_filename', 1 ) ;
		$display_lang     = (int) $field->parameters->get( 'display_lang', 1 ) ;
		$display_size			= (int) $field->parameters->get( 'display_size', 0 ) ;
		$display_hits     = (int) $field->parameters->get( 'display_hits', 0 ) ;
		$display_descr		= (int) $field->parameters->get( 'display_descr', 1 ) ;

		// Force icons in items manager
		$display_lang  = $display_lang && static::$isItemsManager ?  0 : $display_lang;
		$display_size  = $display_size && static::$isItemsManager ?  0 : $display_size;
		$display_hits  = $display_hits && static::$isItemsManager ?  0 : $display_hits;
		$display_descr = $display_descr && static::$isItemsManager ?  0 : $display_descr;

		$add_lang_img = $display_lang == 1 || $display_lang == 3;
		$add_lang_txt = $display_lang == 2 || $display_lang == 3 || static::$isMobile;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || static::$isMobile;

		// NOTE: link_filename parameter is ignore when using buttons !!!
		$link_filename = $field->parameters->get( 'link_filename', 1 ) ;
		$usebutton     = $field->parameters->get( 'usebutton', 1 ) ;

		$buttonsposition = $field->parameters->get('buttonsposition', 1);
		$use_infoseptxt   = $field->parameters->get( 'use_info_separator', 0 ) ;
		$use_actionseptxt = $field->parameters->get( 'use_action_separator', 0 ) ;
		$infoseptxt   = $use_infoseptxt   ?  ' '.$field->parameters->get( 'info_separator', '' ).' '    :  '<br>';
		$actionseptxt = $use_actionseptxt ?  ' '.$field->parameters->get( 'action_separator', '' ).' '  :  '<br>';

		$allowdownloads = $field->parameters->get( 'allowdownloads', 1 ) ;
		$downloadstext  = $allowdownloads==2 ? $field->parameters->get( 'downloadstext', 'FLEXI_DOWNLOAD' ) : 'FLEXI_DOWNLOAD';
		$downloadstext  = JText::_($downloadstext);
		$downloadsinfo  = JText::_('FLEXI_FIELD_FILE_DOWNLOAD_INFO', true);

		$allowview = $field->parameters->get( 'allowview', 0 ) ;
		$viewtext  = $allowview==2 ? $field->parameters->get( 'viewtext', 'FLEXI_FIELD_FILE_VIEW' ) : 'FLEXI_FIELD_FILE_VIEW';
		$viewtext  = JText::_($viewtext);
		$viewinfo  = JText::_('FLEXI_FIELD_FILE_VIEW_INFO', true);
		$viewinside= $field->parameters->get( 'viewinside', 1 ) ;

		$mediapath = static::$cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath  = static::$cparams->get('file_path', 'components/com_flexicontent/uploads');

		$target_dir = $field->parameters->get('target_dir', 0);
		$base_url   = JUri::root(true) . '/' . (!$target_dir ? $mediapath : $docspath);
		$base_url   = str_replace(DS, '/', $base_url);

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		static $fc_lib_added = false;
		if ($viewinside==1 && !$fc_lib_added)
		{
			$fc_lib_added = true;

			flexicontent_html::loadFramework('flexi-lib');
			JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		}

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			$document = JFactory::getDocument();

			JText::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_RESPONSE_PARSING_FAILED', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_FILE_NOT_FOUND', false);

			//flexicontent_html::loadFramework('wavesurfer');
			flexicontent_html::loadFramework('flexi-lib');
			JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
			$document->addScript('https://unpkg.com/wavesurfer.js/dist/wavesurfer.min.js');
			//$document->addScript('https://unpkg.com/wavesurfer.js/dist/plugin/wavesurfer.cursor.js');
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/mediafile/js/view.js', array('version' => FLEXI_VHASH));
			//$document->addScript(JUri::root(true) . '/components/com_flexicontent/assets/js/pako.min.js', array('version' => FLEXI_VHASH));
			//$document->addScript(JUri::root(true) . '/components/com_flexicontent/assets/js/pako_deflate.min.js', array('version' => FLEXI_VHASH));
			//$document->addScript(JUri::root(true) . '/components/com_flexicontent/assets/js/pako_inflate.min.js', array('version' => FLEXI_VHASH));

			$document->addScriptDeclaration("
			jQuery(document).ready(function()
			{
				audio_spectrum_conf['" . $field_name_js . "'] = [];
				audio_spectrum_conf['" . $field_name_js . "']['waveColor'] = '" . $field->parameters->get( 'ws_wave_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['progressColor'] = '" .$field->parameters->get( 'ws_progress_color', '#d0d0d0' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorColor'] = '" .  $field->parameters->get( 'ws_cursor_color', '#619fc7' ) . "';
				audio_spectrum_conf['" . $field_name_js . "']['cursorWidth'] = '" . (int) $field->parameters->get( 'ws_cursor_width', 2 ) . "';

				new fc_Waveform_LazyLoad(
					document.getElementsByTagName('body')[0], {
						rootMargin: '0px 0px',
						threshold: 0.25
					});
			});
			");
		}

		$allowshare = $field->parameters->get( 'allowshare', 0 ) ;
		$sharetext  = $allowshare==2 ? $field->parameters->get( 'sharetext', 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND' ) : 'FLEXI_FIELD_FILE_EMAIL_TO_FRIEND';
		$sharetext  = JText::_($sharetext);
		$shareinfo  = JText::_('FLEXI_FIELD_FILE_EMAIL_TO_FRIEND_INFO', true);

		$allowaddtocart = $field->parameters->get( 'use_downloads_manager', 0);
		$addtocarttext  = $allowaddtocart==2 ? $field->parameters->get( 'addtocarttext', 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART' ) : 'FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART';
		$addtocarttext  = JText::_($addtocarttext);
		$addtocartinfo  = JText::_('FLEXI_FIELD_FILE_ADD_TO_DOWNLOADS_CART_INFO', true);

		/** BACKEND **/
		$link_filename = static::$isItemsManager ? 1 : $link_filename;
		$usebutton     = static::$isItemsManager ?  0 : $usebutton;
		$allowdownloads = static::$isItemsManager ?  1 : $allowdownloads;
		$allowview = static::$isItemsManager ?  0 : $allowview;
		$allowshare = static::$isItemsManager ?  0 : $allowshare;
		$allowaddtocart = static::$isItemsManager ?  0 : $allowaddtocart;

		$noaccess_display	     = $field->parameters->get( 'noaccess_display', 1 ) ;
		$noaccess_url_unlogged = $field->parameters->get( 'noaccess_url_unlogged', false ) ;
		$noaccess_url_logged   = $field->parameters->get( 'noaccess_url_logged', false ) ;
		$noaccess_msg_unlogged = JText::_($field->parameters->get( 'noaccess_msg_unlogged', '' ));
		$noaccess_msg_logged   = JText::_($field->parameters->get( 'noaccess_msg_logged', '' ));
		$noaccess_addvars      = $field->parameters->get( 'noaccess_addvars', 0);

		// Select appropriate messages depending if user is logged on
		if (JFactory::getUser()->guest)
		{
			if ($noaccess_url_unlogged)
			{
				$noaccess_url = $noaccess_url_unlogged;
			}
			else
			{
				$uri  = JUri::getInstance();

				$return   = strtr(base64_encode($uri->toString()), '+/=', '-_,');          // Current URL as return URL (but we will for id / cid)
				$fcreturn = serialize( array('id' => $app->input->getInt('id'), 'cid' => $app->input->getInt('cid')) );  // a special url parameter, used by some SEF code
				$noaccess_url = JComponentHelper::getParams( 'com_flexicontent' )->get('login_page', 'index.php?option=com_users&view=login')
					. '&return='.$return
					. '&fcreturn='.base64_encode($fcreturn);
			}

			$noaccess_msg = $noaccess_msg_unlogged;
		}
		else
		{
			$noaccess_url = $noaccess_url_logged;
			$noaccess_msg = $noaccess_msg_logged;
		}


		// VERIFY downloads manager module is installed and enabled
		static $mod_is_enabled = null;

		if ($mod_is_enabled === null && $allowaddtocart && !JFactory::getApplication()->isClient('administrator'))
		{
			$db = JFactory::getDbo();
			$query = "SELECT published FROM #__modules WHERE module = 'mod_flexidownloads' AND published = 1";
			$mod_is_enabled = $db->setQuery($query)->loadResult();

			if (!$mod_is_enabled)
			{
				JFactory::getApplication()->enqueueMessage("FILE FIELD: please disable parameter \"Use Downloads Manager Module\", the module is not install or not published", 'message' );
			}
		}
		$allowaddtocart = $allowaddtocart ? $mod_is_enabled : 0;


		// Downloads manager feature
		if ($allowshare)
		{
			$com_mailto_found = file_exists(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			if ($com_mailto_found)
			{
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$status = 'width=700,height=360,menubar=yes,resizable=yes';
			}
		}

		// Get user access level (these are multiple for J2.5)
		$user = JFactory::getUser();
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);

		$n = 0;

		/**
		 * Get All file information at once (Data maybe cached already)
		 * TODO (maybe) e.g. contentlists should could call this function ONCE for all file fields,
		 * This may be done by adding a new method to fields to prepare multiple fields with a single call
		 */
		$files_data = $this->getFileData( $values, $published=true );   //if ($field->id==NNN) { echo "<pre>"; print_r($files_data); exit; }

		// Legacy layout support, create 'hits_icon' variable
		static $hits_icon = null;

		if ($hits_icon === null && $add_hits_img)
		{
			if ($display_hits==1)
			{
				$_tooltip_title   = '';
				$_tooltip_content = '%s '.JText::_( 'FLEXI_HITS', true );
				$_attribs = 'class="'.$tooltip_class.' fcicon-hits" title="'.JHtml::tooltipText($_tooltip_title, $_tooltip_content, 0, 0).'"';
			}
			else
			{
				$_attribs = ' class="fcicon-hits"';
			}

			//$hits_icon = JHtml::image('components/com_flexicontent/assets/images/'.'user.png', JText::_( 'FLEXI_HITS' ), $_attribs) . ' ';
			$hits_icon = '<span class="icon-eye" ' . $_attribs . '></span>';
		}

		$show_filename = $display_filename > 0 || $prop=='namelist';
		$public_acclevel = 1;
		$empty_file_data = array('filename'=>false, 'filename_original'=>false, 'altname'=>false, 'description'=>false, 'ext'=>false, 'id'=>0);

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_InlineBoxes';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		if (!empty($fancybox_needed)) flexicontent_html::loadFramework('fancybox');

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// values_list_placement: 1:In slider, 0:Inline
			$values_list_placement = (int) $field->parameters->get('values_list_placement', 0);
			$values_list_placement = static::$isItemsManager ? 1 : $values_list_placement;
			$values_slider_title   = $field->parameters->get('values_slider_title', 'FLEXI_FILES');
			$head = $values_list_placement && isset($field->{$prop}[-1]) ? $field->{$prop}[-1] : '';

			if ($values_list_placement)
			{
				unset($field->{$prop}[-1]);
			}

			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}

			if ($values_list_placement)
			{
				$ff_slider_tagid = 'fcfile_slider_' . $item->id . '_' . $field->id;
				$ff_slider_title = JText::_($values_slider_title);

				$field->{$prop} = $head .
					JHtml::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => -1)) .
					JHtml::_('bootstrap.addSlide', $ff_slider_tagid, $ff_slider_title, $ff_slider_tagid . '_filelist_slide') .
					$field->{$prop} .
					JHtml::_('bootstrap.endSlide') .
					JHtml::_('bootstrap.endAccordion');
			}
		}
	}



	// ***
	// *** METHODS HANDLING events on field values
	// ***

	// Method to execute a task when an action on a value is performed
	function onFieldValueAction_FC(&$field, $item, $value_order, $config)
	{
		/**
		 * IMPORTANT:
		 * If you add EVENT 'onFieldValueAction_FC' to a SYSTEM plugin use $handled_types = array('file')
		 */
		$handled_types = static::$field_types;

		if (!in_array($field->field_type, $handled_types))
		{
			return;
		}

		/**
		 * Use $field->id, $item, $value_order, $config to decide on making an action
		 * Typical config array data is:

			$config = array(
				'fileid' => $file_id,  // (int)
				'task' => $task,  // (string) 'download', 'download_tree'
				'method' => $method,  // (string) 'view', 'download'
				'coupon_id' => $coupon_id,  // int or null
				'coupon_token' => $coupon_token // string or null
			);
		*/

		//echo '<pre>' . get_class($this) . '::' . __FUNCTION__ . "()\n\n"; print_r($config); echo '</pre>'; die('TEST code reached exiting');

		/**
		 * false is failure, indicates abort further actions
		 * true is success
		 * null is no work done
		 */
		return null;
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
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$import_docs_folder = $app->input->get('import_docs_folder', '', 'string');

		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;
		$iform_allowdel = $field->parameters->get('iform_allowdel', 1);

		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_access= $inputmode==1 ? 0 : $field->parameters->get('iform_access',0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		$iform_stamp = $inputmode==1 ? 0 : $field->parameters->get('iform_stamp', 0);

		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( !$initialized )
		{
			$initialized = 1;
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
			$srcpath_original  = JPath::clean( JPATH_SITE .DS. $import_docs_folder .DS );
		}


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			if (empty($v)) {
				// skip empty value, but allow empty (null) placeholder value if in fieldgroup
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			// support for basic CSV import / export
			if ( $is_importcsv )
			{
				if ( !is_numeric($v) )
				{
					$filename = basename($v);
					$sub_folder = dirname($v);
					$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';

					// Add by calling the filemanager upload() task in interactive mode
					$fman = new FlexicontentControllerFilemanager();
					$fman->runMode = 'interactive';

					$Fobj = new stdClass();
					$Fobj->return_url     = null;
					$Fobj->file_dir_path  = DS. $import_docs_folder . $sub_folder;
					$Fobj->file_filter_re = preg_quote($filename);
					$Fobj->secure = 0; //(int) $field->parameters->get('iform_dir_default', 0);
					$Fobj->stamp  = 0; //(int) $field->parameters->get('iform_stamp_default', 0);
					$Fobj->keep   = 1;

					$upload_errs = null;
					$file_ids = $fman->addlocal($Fobj, $upload_errs);

					// Get fist element
					$v = !empty($file_ids) ? reset($file_ids) : ($use_ingroup ? null : false);
					$v = $v ?: ($use_ingroup ? null : false);
					//$_filetitle = key($file_ids);  // This is the cleaned up filename, currently not needed
				}
			}

			// we were given a file ID
			elseif (!is_array($v))
			{
				$file_id = (int) $v;
				$v = $v ?: ($use_ingroup ? null : false);
			}

			// Using inline property editing
			else
			{
				$file_id = (int) $v['file-id'];

				$err_code = isset($_FILES['custom']['error'][$field->name][$n]['file-data'])
					? $_FILES['custom']['error'][$field->name][$n]['file-data']
					: UPLOAD_ERR_NO_FILE;
				$new_file = $err_code === 0;

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
					if ($use_ingroup) $newpost[$new++] = null;
					continue;
				}

				// validate data or empty/set default values
				$v['file-del']   = !$iform_allowdel ? 0 : (int) (!empty($v['file-del']) ? 1 : 0);
				$v['file-title'] = !$iform_title  ? '' : flexicontent_html::dataFilter($v['file-title'],  1000,  'STRING', 0);
				$v['file-desc']  = !$iform_desc   ? '' : flexicontent_html::dataFilter($v['file-desc'],   10000, 'STRING', 0);
				$v['file-lang']  = !$iform_lang   ? '' : flexicontent_html::dataFilter($v['file-lang'],   9,     'STRING', 0);
				$v['file-access']= !$iform_access ? '' : flexicontent_html::dataFilter($v['file-access'], 9,     'ACCESSLEVEL', 0);
				$v['stamp']      = 0;  //!$iform_stamp  ? 0 : ((int) $v['stamp'] ? 1 : 0);

				if( $new_file )
				{
					$v['secure']   = 0; //!$iform_dir    ? 0 : ((int) $v['secure'] ? 1 : 0);
				}

				// UPDATE existing file
				if ( !$new_file && $file_id )
				{
					$dbdata = array();

					$dbdata['id'] = $file_id;
					if ($iform_title)  $dbdata['altname'] = $v['file-title'];
					if ($iform_desc)   $dbdata['description'] = $v['file-desc'];
					if ($iform_lang)   $dbdata['language'] = $v['file-lang'];
					if ($iform_access) $dbdata['access'] = $v['file-access'];
					if ($iform_stamp)  $dbdata['stamp']  = $v['stamp'];
					//if ($iform_dir)  $dbdata['secure'] = $v['secure'];  // !! Do not change folder for existing files

					// Load file data from DB
					$row = JTable::getInstance('flexicontent_files', '');
					$row->load( $file_id );
					$_filename = $row->filename_original ? $row->filename_original : $row->filename;
					$dbdata['secure'] = $row->secure ? 1 : 0;  // !! Do not change media/secure -folder- for existing files

					// Security concern, check file is assigned to current item
					$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
					if ( $v['file-del'] )
					{
						/*!$isAssigned
							? JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );
							: JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to update file properties of a file: '".$_filename."', that is not assigned to current item", 'warning' );*/
					}

					// Delete existing file if so requested
					if ( $v['file-del'] )
					{
						$canDelete = $this->canDeleteFile($field, $file_id, $item);
						if ($isAssigned && $canDelete)
						{
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
						if ($use_ingroup) $newpost[$new++] = null;
						continue;  // Skip file since unloading / removal was requested
					}

					// Set the changed data into the object
					foreach ($dbdata as $index => $data) $row->{$index} = $data;

					// Update DB data of the file
					if ( !$row->check() || !$row->store() )
					{
						JFactory::getApplication()->enqueueMessage("FILE FIELD: " . $row->getError(), 'warning');
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}

					// Set file id as value of the field
					$v = $file_id;
				}

				//INSERT new file
				elseif( $new_file )
				{
					// new file was uploaded, but also handle previous selected file ...
					if ($file_id)
					{
						// Security concern, check file is assigned to current item
						$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
						if ( !$isAssigned )
						{
							/*$row = JTable::getInstance('flexicontent_files', '');
							$row->load( $file_id );
							$_filename = $row->filename_original ? $row->filename_original : $row->filename;
							JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );*/
						}

						// Delete previous file if no longer used
						else if ( $this->canDeleteFile($field, $file_id, $item) )
						{
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
					}

					// Skip file if unloading / removal was requested
					if ( $v['file-del'] )
					{
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}

					// Add file by calling filemanager controller upload() task, which will do the data filtering too
					$fman = new FlexicontentControllerFilemanager();
					$fman->runMode = 'interactive';

					$app->input->set('return', null);
					$app->input->set('secure', $v['secure']);
					$app->input->set('stamp', $v['stamp']);
					$app->input->set('file-title', $v['file-title']);
					$app->input->set('file-desc', $v['file-desc']);
					$app->input->set('file-lang', $v['file-lang']);
					$app->input->set('file-access', $v['file-access']);

					// The dform field name of the <input type="file" ...
					$app->input->set('file-ffname', 'custom');
					$app->input->set('fname_level1', $field->name);
					$app->input->set('fname_level2', $n);
					$app->input->set('fname_level3', 'file-data');

					$upload_errs = null;
					$file_id = $fman->upload(null, $upload_errs);
					$v = !empty($file_id) ? $file_id : ($use_ingroup ? null : false);

					if (empty($file_id)) foreach ($upload_errs as $err_type => $upload_err)
					{
						JFactory::getApplication()->enqueueMessage($upload_err, $err_type);
					}
				}

				else {
					// no existing file and no new file uploaded
					$v = $use_ingroup ? null : false;
				}
	    }

	    if (!$use_ingroup)
			{
	    	// NOT inside field group, add it only if not empty reverse the file array, indexing it by file IDs, to add each file only once
				if ( !empty($v) && is_numeric($v) ) $newpost[(int)$v] = $new++;
			}
			else
			{
				// Inside fieldgroup, allow same file multiple times
				$newpost[$new++] = $v===null ? null : (int)$v;  // null means skip value but increment value position
			}
    }

    // IF NOT inside field group, the file array was reversed (indexed by file IDs), so that the same file can be added once
   	$post = !$use_ingroup ? array_flip($newpost) : $newpost;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item) {
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// Available properties
		/*
		$mediadata = array('state', 'media_type', 'media_format', 'codec_type', 'codec_name', 'codec_long_name',
			'resolution', 'fps', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
			*/
		$mediadata = array('media_format', 'sample_rate', 'duration');
		$media_property_filters = $filter->parameters->get('media_property_filters', $mediadata);
    $media_property_filters = FLEXIUtilities::paramToArray($media_property_filters, "/[\s]*,[\s]*/", false, true);

		$html = array();
		$label = $filter->label;

		foreach($media_property_filters as $prop_name)
		{
			// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
			// partial SQL clauses
			$filter->filter_valuesselect = ' md.' . $prop_name . ' AS value, md.' . $prop_name . ' AS text';
			$filter->filter_valuesjoin   = ' JOIN #__flexicontent_files AS f ON f.id = fi.value '
				. ' JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id';
			//$filter->filter_valueswhere  = ' AND f.id NOT NULL)';
			// full SQL clauses
			$filter->filter_groupby = ' GROUP BY md.' . $prop_name;
			$filter->filter_having  = null;   // this indicates to use default, space is use empty
			//$filter->filter_orderby = ' ORDER by text';   // default will order by value and not by label

			$prop_value = isset($value[$prop_name]) ? $value[$prop_name] : null;  // TODO GET FROM URI
			//echo 'filter_id: ' . $filter->id . '<br>';
			//echo '<pre>'; print_r($prop_value); echo '</pre>';


			unset($filter->html);
			unset($filter->filt_prop_name);
			$filter->label = $prop_name;
			$filter->filt_prop_name = $prop_name;

			// For duration use text range
			$display_filter_as = $filter->parameters->set('display_filter_as', 0);
			if ($prop_name === 'duration')
			{
				$filter->parameters->set('display_filter_as', 3);
			}

			FlexicontentFields::createFilter($filter, $prop_value, $formName);
			$html[$prop_name] = $filter->html;

			// Restore previous value of filter
			$filter->parameters->set('display_filter_as', $display_filter_as);
		}


		unset($filter->filt_prop_name);

		$filter->label = $label;
		$filter->html = array();
		foreach($media_property_filters as $prop_name)
		{
			$filtername = $prop_name;

			switch($prop_name)
			{
				case 'media_format':  $filtername = JText::_('FLEXI_FIELD_MEDIADATA_MEDIA_TYPE'); break;
				case 'sample_rate':  $filtername = JText::_('FLEXI_FIELD_MEDIADATA_SAMPLE_RATE'); break;
				case 'duration':  $filtername = JText::_('FLEXI_FIELD_MEDIADATA_DURATION_SECONDS'); break;
			}

			$filter->html[] = '
			<div class="fc_filter_media_property_'.$prop_name.'">
				<div class="fc_filter_label fc_label_field_'.$filter->id.'_'.$prop_name.'">'. $filtername . '</div>
				<div class="fc_filter_html">' . $html[$prop_name] . '</div>
			</div>
			';
		}

		$filter->html =  '<div class="fc_filter_media_properties_box" style="float:left; width: 100%;">' . implode('', $filter->html) . '</div>';
	}


	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for category view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		// Available properties
		/*
		$mediadata = array('state', 'media_type', 'media_format', 'codec_type', 'codec_name', 'codec_long_name',
			'resolution', 'fps', 'bit_rate', 'bits_per_sample', 'sample_rate', 'duration',
			'channels', 'channel_layout', 'checked_out', 'checked_out_time');
		*/
		$mediadata = array('media_format', 'sample_rate', 'duration');
		$media_property_filters = $filter->parameters->get('media_property_filters', $mediadata);
    $media_property_filters = FLEXIUtilities::paramToArray($media_property_filters, "/[\s]*,[\s]*/", false, true);

		$results = array();
		$label = $filter->label;

		foreach($media_property_filters as $prop_name)
		{
			$filter->filter_valuesjoin   = ' JOIN #__flexicontent_fields_item_relations as rel ON rel.item_id = c.id AND rel.field_id = ' . (int) $filter->id
				. ' JOIN #__flexicontent_files AS f ON f.id = rel.value '
				. ' JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id';

			$prop_value = isset($value[$prop_name]) ? $value[$prop_name] : null;  // TODO GET FROM URI
			$is_empty  = ($prop_value && is_array($prop_value)) ? !strlen(trim(implode('', $prop_value))) : !strlen(trim($prop_value));

			//  No posted value, nothing to do
			if ($is_empty) continue;
			//echo 'filter_id: ' . $filter->id . '<br>';
			//echo '<pre>'; print_r($prop_value); echo '</pre>';

			unset($filter->filt_prop_name);
			$filter->label = $prop_name;
			$filter->filt_prop_name = $prop_name;
			$filter->filter_colname = 'md.' . $prop_name;

			// For duration use text range
			$display_filter_as = $filter->parameters->set('display_filter_as', 0);
			if ($prop_name === 'duration')
			{
				$filter->parameters->set('display_filter_as', 3);
			}

			$res = FlexicontentFields::getFiltered($filter, $prop_value, $return_sql);
			if ($res)
			{
				$results[] = $res;
			}

			// Restore previous value of filter
			$filter->parameters->set('display_filter_as', $display_filter_as);
		}

		unset($filter->filt_prop_name);
		$filter->label = $label;

		// This method should not have been called for empty values ... but in case this is called for empty values ...
		// return a non-empty string (prepend a space) to indicate no filtering should be done for this field
		return ' ' . implode(' ', $results);
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

		$field->field_isfile = true;
		$field->unserialize = 0;

		if ($post===null) {
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$values = null;
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('altname', 'description'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		$field->field_isfile = true;
		$field->unserialize = 0;

		if ($post===null) {
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$values = null;
			$field->field_rawvalues = 1;
			$field->field_valuesselect = ' file.id AS value_id, file.altname, file.description, file.filename, file.secure';
			$field->field_valuesjoin   = ' JOIN #__flexicontent_files AS file ON file.id = fi.value';
			$field->field_groupby      = null;
		} else {
			$_files_data = $this->getFileData( $post, $published=true, $extra_select =', f.id AS value_id' );
			$values = array();
			if ($_files_data) foreach($_files_data as $_file_id => $_file_data) $values[$_file_id] = (array)$_file_data;
		}

		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array('filename'), $search_properties=array('altname', 'description'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***




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
			$f = (int) $file_id;
			if (!isset($cached_data[$f]) && $f)
			{
				$new_ids[] = $f;
			}
		}

		$md_select = 'md.state, md.media_type, md.media_format, md.codec_type, md.codec_name, md.codec_long_name, ' .
			'md.resolution, md.fps, md.bit_rate, md.bits_per_sample, md.sample_rate, md.duration, ' .
			'md.channels, md.channel_layout, md.checked_out, md.checked_out_time, ';

		// Get file data not retrieved already
		if (count($new_ids))
		{
			// Only query files that are not already cached
			$db = JFactory::getDbo();
			$query = 'SELECT ' . $md_select . ' f.* '. $extra_select //filename, filename_original, altname, description, ext, id'
				. ' FROM #__flexicontent_files AS f'
				. ' LEFT JOIN #__flexicontent_mediadatas AS md ON f.id = md.file_id'
				. ' WHERE f.id IN ('. implode(',', $new_ids) . ')'
				. ($published ? '  AND published = 1' : '')
			;
			$new_data = $db->setQuery($query)->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data)
			{
				$cached_data[$file_id] = $file_data;
			}
		}

		// Finally get file data in correct order
		foreach($file_ids as $file_id)
		{
			$f = (int) $file_id;
			if (isset($cached_data[$f]) && $f)
			{
				$return_data[$file_id] = $cached_data[$f];
			}
		}

		return !is_array($fid) ? @ $return_data[(int) $fid] : $return_data;
	}


	// ************************************************
	// Returns an array of images that can be deleted
	// e.g. of a specific field, or a specific uploader
	// ************************************************
	function canDeleteFile( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = JFactory::getDbo();
		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE id='. $db->Quote($file_id)
			;
		$db->setQuery($query);
		$file_id = $db->loadResult();
		if (!$file_id)  return true;

		$ignored = array($item->id);

		$fm = new FlexicontentModelFilemanager();
		return $fm->candelete( array($file_id), $ignored );
	}


	// *****************************************
	// Check if file is assigned to current item
	// *****************************************
	function checkFileAssignment( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = JFactory::getDbo();
		$query = 'SELECT item_id '
			. ' FROM #__flexicontent_fields_item_relations '
			. ' WHERE '
			. '  field_id='. $db->Quote($field->id)
			. '  AND item_id='. $db->Quote($item->id)
			. '  AND value='. $db->Quote($file_id)
			. ' LIMIT 1'
			;
		$db->setQuery($query);
		$db_id = $db->loadResult();
		return (boolean)$db_id;
	}


	// *************************************
	// Return an icon according to file type
	// *************************************
	function addIcon( &$file )
	{
		static $icon_exists = array();

		switch ($file->ext)
		{
			// Image
			case 'jpg':
			case 'png':
			case 'gif':
			case 'xcf':
			case 'odg':
			case 'wbmp':
			case 'bmp':
			case 'ico':
			case 'jpeg':
			case 'webp':
				$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
			break;

			// Non-image document
			default:
				if ( !isset($icon_exists[$file->ext]) ) {
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$file->ext.'.png';
					$icon_exists[$file->ext] = file_exists($icon);
				}
				if ( $icon_exists[$file->ext] ) {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$file->ext.'.png';
				} else {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
				}
			break;
		}
		return $file;
	}



	// *******************************
	// HELPER METHODS FOR FILE SHARING
	// *******************************

	/**
	 * Create form for sharing the download link of given file
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_form($tpl = null)
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$app  = JFactory::getApplication();

		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$file_id    = $app->input->get('file_id', 0, 'int');
		$content_id = $app->input->get('content_id', 0, 'int');
		$field_id   = $app->input->get('field_id', 0, 'int');
		$tpl = $app->input->get('tpl', 'default', 'cmd');

		// Check for missing file id
		if (!$file_id)
		{
			jexit( JText::_('file id is missing') );
		}

		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();

		if (!$file)
		{
			jexit( JText::_('file id no '.$file_id.', was not found') );
		}

		$data = new stdClass();
		$data->file_id    = $file_id;
		$data->content_id = $content_id;
		$data->field_id   = $field_id;

		// Load with previous data, if it exists
		$mailto		= $app->input->get('mailto', '', 'string');
		$sender		= $app->input->get('sender', '', 'string');
		$from			= $app->input->get('from', '', 'string');
		$subject	= $app->input->get('subject', '', 'string');
		$desc     = $app->input->get('desc', '', 'string');

		if ($user->get('id') > 0)
		{
			$data->sender	= $user->get('name');
			$data->from		= $user->get('email');
		}
		else
		{
			$data->sender	= $sender;
			$data->from		= $from;
		}

		$data->subject = $subject;
		$data->desc    = $desc;
		$data->mailto  = $mailto;

		$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		include('file'.DS.'share_form.php');
		$session->set('com_flexicontent.formtime', time());
	}


	/**
	 * Send email with download (file) link, to the given email address
	 *
	 * @access public
	 * @since 1.0
	 */
	function share_file_email()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$app  = JFactory::getApplication();

		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$timeout = $session->get('com_flexicontent.formtime', 0);
		if ($timeout == 0 || time() - $timeout < 2) {
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}

		$SiteName	= $app->getCfg('sitename');
		$MailFrom	= $app->getCfg('mailfrom');
		$FromName	= $app->getCfg('fromname');

		$file_id    = $app->input->get('file_id', 0, 'int');
		$content_id = $app->input->get('content_id', 0, 'int');
		$field_id   = $app->input->get('field_id', 0, 'int');
		$tpl = $app->input->get('tpl', 'default', 'cmd');

		// Check for missing file id
		if (!$file_id)
		{
			jexit( JText::_('file id is missing') );
		}

		// Check file exists
		$query = ' SELECT * FROM #__flexicontent_files WHERE id='. $file_id;
		$db->setQuery( $query );
		$file = $db->loadObject();

		if (!$file)
		{
			jexit( JText::_('file id no '.$file_id.', was not found') );
		}


		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = false, $include_file = true );


		// Get field's configuration
		$q = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $field_id;
		$db->setQuery($q);
		$fld = $db->loadObject();
		$field_params = new JRegistry($fld->attribs);

		// Get all needed data related to the given file
		$query  = 'SELECT f.id, f.filename, f.altname, f.secure, f.stamp, f.url,'
				.' i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext, u.email as item_owner_email, '

				// Item and Current Category slugs (for URL)
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'

				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_files AS f ON f.id = rel.value'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS i ON i.id = rel.item_id'
				.' LEFT JOIN #__categories AS c ON c.id = i.catid'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				.' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. $access_clauses['join']
				.' WHERE rel.item_id = ' . $content_id
				.' AND rel.field_id = ' . $field_id
				.' AND f.id = ' . $file_id
				.' AND f.published= 1'
				. $access_clauses['and']
				;
		$file = $db->setQuery($query)->loadObject();

		if ( empty($file) )
		{
			// this is normally not reachable because the share link should not have been displayed for the user, but it is reachable if e.g. user session has expired
			jexit( JText::_( 'FLEXI_ALERTNOTAUTH' ). "File data not found OR no access for file #: ". $file_id ." of content #: ". $content_id ." in field #: ".$field_id );
		}

		$coupon_vars = '';
		if ( $field_params->get('enable_coupons', 0) )
		{
			// Insert new download coupon into the DB, in the case the file is sent to a user with no ACCESS
			$coupon_token = uniqid();  // create coupon token
			$query = ' INSERT #__flexicontent_download_coupons '
				. 'SET user_id = ' . (int)$user->id
				. ', file_id = ' . $file_id
				. ', token = ' . $db->Quote($coupon_token)
				. ', hits = 0'
				. ', hits_limit = '. (int)$field_params->get('coupon_hits_limit', 3)
				. ', expire_on = NOW() + INTERVAL '. (int)$field_params->get('coupon_expiration_days', 15).' DAY'
				;
			$db->setQuery( $query );
			$db->execute();
			$coupon_id = $db->insertid();  // get id of newly created coupon
			$coupon_vars = '&conid='.$coupon_id.'&contok='.$coupon_token;
		}

		$uri  = JUri::getInstance();
		$base = $uri->toString( array('scheme', 'host', 'port'));
		$vars = '&id='.$file_id.'&cid='.$content_id.'&fid='.$field_id . $coupon_vars;
		$link = $base . JRoute::_( 'index.php?option=com_flexicontent&task=download'.$vars, false );

		// Verify that this is a local link
		if (!$link || !JUri::isInternal($link))
		{
			//Non-local url...
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}

		// An array of email headers we do not want to allow as input
		$headers = array ('Content-Type:',
							'MIME-Version:',
							'Content-Transfer-Encoding:',
							'bcc:',
							'cc:');

		// An array of the input fields to scan for injected headers
		$fields = array(
			'mailto',
			'sender',
			'from',
			'subject',
		);

		/*
		 * Here is the meat and potatoes of the header injection test.  We
		 * iterate over the array of form input and check for header strings.
		 * If we find one, send an unauthorized header and die.
		 */
		foreach ($fields as $field)
		{
			foreach ($headers as $header)
			{
				if (strpos($_POST[$field], $header) !== false)
				{
					JError::raiseError(403, '');
				}
			}
		}

		/*
		 * Free up memory
		 */
		unset ($headers, $fields);

		$email		= $app->input->get('mailto', '', 'string');
		$sender		= $app->input->get('sender', '', 'string');
		$from			= $app->input->get('from', '', 'string');
		$_subject = JText::sprintf('FLEXI_FIELD_FILE_SENT_BY', $sender);
		$subject  = $app->input->get('subject', $_subject, 'string');
		$desc     = $app->input->get('desc', '', 'string');

		// Check for a valid to address
		$error	= false;
		if (! $email  || ! JMailHelper::isEmailAddress($email))
		{
			$error	= JText::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $email);
			JError::raiseWarning(0, $error);
		}

		// Check for a valid from address
		if (! $from || ! JMailHelper::isEmailAddress($from))
		{
			$error	= JText::sprintf('FLEXI_FIELD_FILE_EMAIL_INVALID', $from);
			JError::raiseWarning(0, $error);
		}

		if ($error)
		{
			return $this->share_file_form();
		}

		// Build the message to send
		$body  = JText::sprintf('FLEXI_FIELD_FILE_EMAIL_MSG', $SiteName, $sender, $from, $link);
		$body	.= "\n\n".JText::_('FLEXI_FIELD_FILE_EMAIL_SENDER_NOTES').":\n\n".$desc;

		// Clean the email data
		$subject = JMailHelper::cleanSubject($subject);
		$body    = JMailHelper::cleanBody($body);
		$sender  = JMailHelper::cleanAddress($sender);

		$html_mode=false; $cc=null; $bcc=null;
		$attachment=null; $replyto=null; $replytoname=null;

		// Send the email
		$send_result = JFactory::getMailer()->sendMail( $from, $sender, $email, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );
		if ( $send_result !== true )
		{
			JError::raiseNotice(500, JText:: _ ('FLEXI_FIELD_FILE_EMAIL_NOT_SENT'));
			return $this->share_file_form();
		}

		$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		include('file'.DS.'share_result.php');
	}


	// Private common method to create join + and-where SQL CLAUSEs, for checking access of field - item pair(s), IN FUTURE maybe moved
	function _createFieldItemAccessClause($get_select_access = false, $include_file = false )
	{
		$user  = JFactory::getUser();
		$select_access = $joinacc = $andacc = '';

		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);

		// Access Flags for: content item and field
		if ( $get_select_access )
		{
			$select_access = '';
			if ($include_file) $select_access .= ', CASE WHEN'.
				'   f.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_file_access';
			$select_access .= ', CASE WHEN'.
				'  fi.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_field_access';
			$select_access .= ', CASE WHEN'.
				'  ty.access IN (0,'.$aid_list.') AND '.
				'   c.access IN (0,'.$aid_list.') AND '.
				'   i.access IN (0,'.$aid_list.')'.
				' THEN 1 ELSE 0 END AS has_content_access';
		}

		else
		{
			if ($include_file)
				$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
			$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
			$andacc .= ''  // AND content access
				.' AND ty.access IN (0,'.$aid_list.')'
				.' AND  c.access IN (0,'.$aid_list.')'
				.' AND  i.access IN (0,'.$aid_list.')';
		}

		$clauses['select'] = $select_access;
		$clauses['join']   = $joinacc;
		$clauses['and']    = $andacc;
		return $clauses;
	}
}
