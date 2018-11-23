<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('FlexicontentControllerFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');  // we use JPATH_BASE since controller exists in frontend too
JLoader::register('FlexicontentModelFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');  // we use JPATH_BASE since model exists in frontend too

class plgFlexicontent_fieldsMinigallery extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

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
		$use_ingroup = 0; //$field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$is_ingroup  = 0; //!empty($field->ingroup);

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
		$tip_class     = $tooltip_class;  // Compatibility with older custom templates

		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : substr(JFactory::getApplication()->input->get('unique_tmp_itemid', '', 'string'), 0, 1000);


		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || 1; //(int) $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required   = (int) $field->parameters->get('required', 0);
		$required_class = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get('add_position', 3);

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;

		// Inline file property editing
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;  // 1: file selection only,  0: inline file properties editing
		$top_notice = $use_ingroup ? '<div class="alert alert-warning">Field group mode is not implenent in current version, please disable</div>' : '';

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

		$mediapath   = $cparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath    = $cparams->get('file_path', 'components/com_flexicontent/uploads');
		$imageexts   = array('jpg','gif','png','bmp','jpeg');

		// Empty field value
		if (!$field->value)
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
					if (!isset($v['secure'])) $v['secure'] = !$iform_dir   ? 0 : (int) $field->parameters->get('iform_dir_default', 0);
					if (!isset($v['stamp']))  $v['stamp']  = !$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0);
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

		// Inline mode needs an default value, TODO add for popup too ?
		$has_values = count($field->value);

		if (empty($field->value) || $use_ingroup)
		{
			// Create an empty file properties value, used by code that creates empty inline file editing form fields
			if (empty($field->value)) $field->value = array(0=>0);
			$files_data[0] = (object)array(
				'id' => '', 'filename' => '', 'filename_original' => '', 'altname' => '', 'description' => '',
				'url' => '',
				'secure' => (!$iform_dir  ? 0 : (int) $field->parameters->get('iform_dir_default', 0)),
				'stamp' => (!$iform_stamp ? 0 : (int) $field->parameters->get('iform_stamp_default', 0)),
				'ext' => '', 'published' => 1,
				'language' => $field->parameters->get('iform_lang_default', '*'),
				'access' => (int) $field->parameters->get('iform_access_default', 1),
				'hits' => 0,
				'uploaded' => '', 'uploaded_by' => 0, 'checked_out' => false, 'checked_out_time' => ''
			);
		}

		// Button for popup file selection
		$autoassign = (int) $field->parameters->get( 'autoassign', 1 ) ;
		$addExistingURL = JUri::base(true)
			.'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component'
			.'&amp;index=%s'
			.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;autoassign='.$autoassign
			//.'&amp;filter_uploader='.$user->id
			.'&amp;targetid=%s'
			.'&amp;existing_class=fc_filedata_storage_name'
			.'&amp;' . JSession::getFormToken() . '=1';

		$_prompt_txt = JText::_( 'FLEXI_FIELD_MGALLERY_SELECTED_FILE' );  //JText::_( 'FLEXI_ADD_FILE' );

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

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


			function fc_openFileSelection_".$field->id."(event)
			{
				var obj = jQuery(event.data.obj);
				var url = obj.attr('href');

				url = url.replace( '__rowno__',  obj.attr('data-rowno') ? obj.attr('data-rowno') : '' );
				url = url.replace( '__thisid__', obj.attr('id') ? obj.attr('id') : '' );

				fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title: '".JText::_('FLEXI_FIELD_MGALLERY_SELECTED_FILE', true)."'});
				return false;
			}


			function fcfield_assignFile".$field->id."(value_container_id, file, keep_modal)
			{
				// We use altname (aka title) that is by default (unless modified) same as 'filename_original'
				var originalname = file.filename_original ? file.filename_original : file.filename;
				var displaytitle = file.altname && (file.altname!=file.filename) ? file.altname : '-';
				var text_nowrap  = file.altname && (file.altname!=file.filename) ? file.filename+'<br/>'+file.altname : '';

				var container = jQuery('#'+value_container_id).closest('.fcfieldval_container');

				container.find('.fc_fileid').val(file.id);
				container.find('.fc_filedata_storage_name').html(file.filename);
				container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
				container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
				container.find('.fc_filedata_title').html(displaytitle);

				container.find('.fc_preview_thumb').attr('src', file.preview ? file.preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

				".($form_file_preview == 2 ? "
				file.preview ? container.find('.fc_preview_thumb').show() : container.find('.fc_preview_thumb').hide();
				" : "")."

				container.find('.fc_filetitle').val(file.altname).blur();
				container.find('.fc_filelang').val(file.language).trigger('change');
				container.find('.fc_filedesc').val(file.description);

				// Increment value counter (which is optionally used as 'required' form element)
				var valcounter = document.getElementById('".$elementid."');
				if (valcounter)
				{
					valcounter.value = valcounter.value=='' ? '1' : parseInt(valcounter.value) + 1;
				}

				var remove_obj = container.find('.inlinefile-del');
				remove_obj.removeAttr('checked').trigger('change');

				if (!keep_modal && fc_field_dialog_handle_".$field->id.")
				{
					fc_field_dialog_handle_".$field->id.".dialog('close');
				}

				// Re-validate
				jQuery(valcounter).trigger('blur');
				return true;
			}


			jQuery(document).ready(function() {
				jQuery('a.addfile_".$field->id."').each(function(index, value) {
					jQuery(this).on( 'click',  {obj:this},  fc_openFileSelection_".$field->id." );
				});
			});
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

				var theInput = newField.find('input.inlinefile-del').first();
				theInput.removeAttr('checked');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-del]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-del');
				newField.find('.inlinefile-del-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-del').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-del-lbl');

				var theInput = newField.find('input.fc_filedata').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-data]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-data');
				theInput.attr('data-rowno',uniqueRowNum".$field->id.");

				newField.find('.inlinefile-data-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-data-txt').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-data-lbl');

				var theInput = newField.find('input.fc_fileid').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-id]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-id');

				newField.find('.fc_filedata_txt_nowrap').html('-');
				newField.find('.fc_filedata_title').html('-');

				var theInput = newField.find('input.fc_filedata_txt').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-data-txt]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-data-txt');

				var imgPreview = newField.find('.fc_preview_thumb').first();
				imgPreview.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_img_preview');
				imgPreview.attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
				".($form_file_preview != 1 ? '
				imgPreview.hide();' : '')."

				".($iform_title ? "
				var theInput = newField.find('input.fc_filetitle').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-title]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-title');
				newField.find('.inlinefile-title-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-title').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-title-lbl');
				" : "")."

				".($iform_lang ? "
				var theInput = newField.find('select.fc_filelang').first();
				theInput.get(0).selectedIndex = 0;
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-lang]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-lang');
				newField.find('.inlinefile-lang-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-lang').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-lang-lbl');
				" : "")."

				".($iform_access ? "
				var theInput = newField.find('select.fc_fileaccess').first();
				//theInput.get(0).selectedIndex = 0;
				theInput.val('1');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-access]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-access');
				newField.find('.inlinefile-access-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-access').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-access-lbl');
				" : "")."

				".($iform_dir ? "
				var nr = 0;
				newField.find('.inlinefile-secure-info').remove();
				newField.find('.inlinefile-secure-data').show();
				newField.find('input.fc_filedir').each(function() {
					var elem = jQuery(this);
					elem.removeAttr('disabled');
					elem.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][secure]');
					elem.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_secure_'+nr);
					elem.next().removeClass('active');
					elem.prop('checked', false);
					elem.next().attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_secure_'+nr).attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-secure'+nr+'-lbl');
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
					elem.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][stamp]');
					elem.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_stamp_'+nr);
					elem.next().removeClass('active');
					elem.prop('checked', false);
					elem.next().attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_stamp_'+nr).attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-stamp'+nr+'-lbl');
					nr++;
				});
				" : "")."

				".($iform_desc ? "
				var theInput = newField.find('textarea.fc_filedesc').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][file-desc]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-desc');
				newField.find('.inlinefile-desc-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_file-desc').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-desc-lbl');
				" : "")."

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}

				// Update button for modal file selection
				var theBTN = newField.find('a.addfile_".$field->id."');
				theBTN.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addfile');
				theBTN.attr('data-rowno',uniqueRowNum".$field->id.");
				theBTN.each(function(index, value) {
					jQuery(this).on( 'click',  {obj:this},  fc_openFileSelection_".$field->id." );
				});
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
					var valcounter = document.getElementById('".$elementid."');
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


		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			flexicontent_html::loadFramework('flexi-lib');
		}


		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		// *****************************************
		// Create field's HTML display for item form
		// *****************************************

		$field->html = array();  // Make sure this is an array

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		foreach($field->html as &$_html)
		{
			$_html = '
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
				').'
				<div class="fc-field-props-box">
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
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}

		// Add toggle button for: Compact values view (= multiple values per row)
		$show_values_expand_btn = $formlayout === 'field_InlineBoxes' ? $show_values_expand_btn : 0;
		if (!$use_ingroup && $show_values_expand_btn)
		{
			$field->html = '
			<span class="fcfield-expand-view-btn btn btn-small" onclick="fc_toggleCompactValuesView(this, jQuery(this).closest(\'.container_fcfield\'));" data-expandedFieldState="0">
				<span class="fcfield-expand-view ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_EXPAND_VALUES', true ).'"></span> &nbsp;'.JText::_( 'FLEXI_EXPAND_VALUES', true ).'
			</span>
			' . $field->html;
		}

		// Button for popup file selection
		/*if (!$use_ingroup) $field->html .= '
			<input id="'.$elementid.'" class="'.$required_class.' fc_hidden_value" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($n ? $n : '').'" />';*/
		if ($top_notice) $field->html = $top_notice.$field->html;
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || 1; //(int) $field->parameters->get( 'allow_multiple', 1 ) ;

		$field->label = JText::_($field->label);

		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if ( empty($values) )
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		$files_data = $this->getFileData( $values, $published=true );   //if ($field->id==NNN) { echo "<pre>"; print_r($files_data); exit; }
		$values = array();
		foreach($files_data as $file_id => $file_data)
		{
			$values[] = $file_id;
		}

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app  = JFactory::getApplication();

		$mediapath = $cparams->get('media_path', 'components/com_flexicontent/medias');
		$usepopup  = $field->parameters->get('usepopup', 1);
		$popuptype = $field->parameters->get('popuptype', 4);

		// some parameter shortcuts
		$tooltip_class = 'hasTooltip';
		$thumbposition		= $field->parameters->get( 'thumbposition', 3 ) ;
		$w_l				= $field->parameters->get( 'w_l', 450 ) ;
		$h_l				= $field->parameters->get( 'h_l', 300 ) ;
		$w_s				= $field->parameters->get( 'w_s', 100 ) ;
		$h_s				= $field->parameters->get( 'h_s', 66 ) ;

		switch ($thumbposition) {
			case 1: // top
			$marginpos = 'top';
			$marginval = $h_s;
			break;

			case 2: // left
			$marginpos = 'left';
			$marginval = $w_s;
			break;

			case 4: // right
			$marginpos = 'right';
			$marginval = $w_s;
			break;

			case 3:
			default : // bottom
			$marginpos = 'bottom';
			$marginval = $h_s;
			break;
		}

		$scroll_thumbnails = $field->parameters->get( 'scroll_thumbnails', 1 ) ;
		switch ($thumbposition) {
			case 1: // top
			case 3:	default : // bottom
			$rows = ceil( (count($values) * ($w_s+8) ) / $w_l );  // thumbnail rows
			$series = ($scroll_thumbnails) ? 1: $rows;
			$series_size = ($h_s+8) * $series;
			break;

			case 2: // left
			case 4: // right
			$cols = ceil( (count($values) * ($h_s+8) ) / $h_l );  // thumbnail columns
			$series = ($scroll_thumbnails) ? 1: $cols;
			$series_size = ($w_s+8) * $series;
			break;
		}

		static $item_field_arr = null;
		static $js_and_css_added = false;

		$slideshowtype = $field->parameters->get( 'slideshowtype', 'Flash' );// default is normal slideshow
		$slideshowClass = 'Slideshow';

		if (empty($values)) return;

		if (!$js_and_css_added)
		{
			$document->addStyleSheet(JUri::root(true).'/plugins/flexicontent_fields/minigallery/css/minigallery.css');
		  JHtml::_('behavior.framework', true);
		  $document->addScript(JUri::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.js');
		  if($slideshowtype!='slideshow')
			{
		  	$document->addScript(JUri::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.'.strtolower($slideshowtype).'.js');
		  	$slideshowClass .= '.'.$slideshowtype;
		  }
		  // this allows you to override the default css files
		  $csspath = JPATH_ROOT.'/templates/'.$app->getTemplate().'/css/minigallery.css';
		  if(file_exists($csspath)) {
				$document->addStyleSheet(JUri::root(true).'/templates/'.$app->getTemplate().'/css/minigallery.css');
		  }
			if ($usepopup && $popuptype==4) flexicontent_html::loadFramework('fancybox');
		}
		$js_and_css_added = true;

		$htmltag_id = "slideshowContainer_".$field->name."_".$item->id;
		$slidethumb = "slideshowThumbnail_".$field->name."_".$item->id;
		$transition = $field->parameters->get( 'transition', 'back' );
		$t_dir = $field->parameters->get( 't_dir', 'in' );
		$thumbnails = $field->parameters->get( 'thumbnails', '1' );
		$thumbnails = $thumbnails ? 'true' : 'false';
		$controller = $field->parameters->get( 'controller', '1' );
		$controller = $controller ? 'true' : 'false';
		$otheroptions = $field->parameters->get( 'otheroptions', '' );

		if ( !isset($item_field_arr[$item->id][$field->id]) )
		{
			$item_field_arr[$item->id][$field->id] = 1;

			$css = "
			#$htmltag_id {
				width: ".$w_l."px;
				height: ".$h_l."px;
				margin-".$marginpos.": ".(($marginval+8)*$series)."px;
			}
				";

			if ($thumbposition == 2 || $thumbposition == 4) {
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($series_size+4)."px; height: 100%; width: ".($series_size+4)."px; top:0px; }";
				$css .= "div .slideshow-thumbnails ul { width: ".$series_size."px; }";
				$css .= "div .slideshow-thumbnails ul li {  }";
			} else if ($thumbposition==1 || $thumbposition==3) {
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($series_size+4)."px; height: ".$series_size."px; }";
				if ($series > 1) $css .= "div .slideshow-thumbnails ul { width:100%!important; }";
				$css .= "div .slideshow-thumbnails ul li { float: left!important;}";
			} else { // inside TODO
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($marginval+8)."px; height: ".($h_s+8)."px; top:0px; z-index:100; }";
				$css .= "div .slideshow-thumbnails ul { width: 100%!important;}";
				$css .= "div .slideshow-thumbnails ul li { float: left!important;}";
			}

			$document->addStyleDeclaration($css);

			$otheroptions = ($otheroptions?','.$otheroptions:'');
			$js = "
		  	window.addEvent('domready',function(){
				var options = {
					delay: ".$field->parameters->get( 'delay', 4000 ).",
					hu:'{$mediapath}/',
					transition:'{$transition}:{$t_dir}',
					duration: ".$field->parameters->get( 'duration', 1000 ).",
					width: {$w_l},
					height: {$h_l},
					thumbnails: {$thumbnails},
					controller: {$controller}
					{$otheroptions}
				}
				show = new {$slideshowClass}('{$htmltag_id}', null, options);
			});
			";
			$document->addScriptDeclaration($js);
		}

		$pimages = array();
		$display = array();
		$thumbs  = array();

		$usecaptions = (int)$field->parameters->get( 'usecaptions', 1 );
		$captions = '';
		if($usecaptions===2)
			$captions = htmlspecialchars($field->parameters->get( 'customcaptions', 'This is a caption' ), ENT_COMPAT, 'UTF-8');

		$group_str = 'data-fancybox-group="fcitem_'.$item->id.'_fcfield_'.$field->id.'"';
		$n = 0;
		foreach($files_data as $file_id => $file_data)
		{
			if ($file_data) {
				$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
					JUri::root(true) . '/' . $mediapath . '/' . $file_data->filename :
					$file_data->filename ;
				$srcs	= JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w='.$w_s.'&amp;h='.$h_s.'&amp;zc=1&amp;q=95&amp;ar=x';
				$srcb	= JUri::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w='.$w_l.'&amp;h='.$h_l.'&amp;zc=1&amp;q=95&amp;ar=x';

				$ext = strtolower(pathinfo($img_path, PATHINFO_EXTENSION));
				$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';

				$srcs .= '&amp;f='. $ext;
				$srcb .= '&amp;f='. $ext;

				if ($usecaptions===1) $captions = htmlspecialchars('<b>'.$file_data->altname.($file_data->description ? "</b> <br/> ".$file_data->description : ""), ENT_COMPAT, 'UTF-8');
				if ($usepopup && $popuptype == 4)
				{
					$pimages[] = '<img src="'.$img_path.'" id="'.$htmltag_id.'_'.$n.'_popup_img" class="fc_image_thumb fancybox" '.$group_str.' alt="'.$captions.'" title="'.$captions.'" >';
				}
				$tag_params = $usepopup && $popuptype == 4  ?  ' onclick="jQuery(\'#\' + jQuery(this).find(\'img\').last().attr(\'id\') + \'_popup_img\').trigger(\'click\'); return false;" ' : '';
				$display[] = '
					<a '.$tag_params.' href="javascript:;" >
						<img src="'.$srcb.'" id="'.$htmltag_id.'_'.$n.'" alt="'.$captions.'" style="border:0" itemprop="image" />
					</a>';
				$thumbs[] = '
					<li><a href="#'.$htmltag_id.'_'.$n.'"><img src="'.$srcs.'" style="border:0" itemprop="image" alt="'.$captions.'"/></a></li>';
				$n++;
			}
		}

		$field->{$prop} = '
		<div id="'.$htmltag_id.'" class="slideshow">
			<div class="slideshow-images">
				'.implode("\n", $display).'
			</div>
			<div class="slideshow-thumbnails">
				<ul>
				'.implode("\n", $thumbs).'
				</ul>
			</div>
		</div>
		<div class="clr"></div>
		<div class="fcclear"></div>
		<div id="'.$htmltag_id.'_popup_images" style="display:none;">
			'.implode("\n", $pimages).'
		</div>';
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
					$Fobj->secure = (int) $field->parameters->get('iform_dir_default', 0);
					$Fobj->stamp  = (int) $field->parameters->get('iform_stamp_default', 0);
					$Fobj->keep   = 1;

					$upload_err = null;
					$file_ids = $fman->addlocal($Fobj, $upload_err);

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
				$v['stamp']      = !$iform_stamp  ? 0 : ((int) $v['stamp'] ? 1 : 0);
				if( $new_file )
				{
					$v['secure']   = !$iform_dir    ? 0 : ((int) $v['secure'] ? 1 : 0);
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
						JFactory::getApplication()->enqueueMessage("FILE FIELD: ".JFactory::getDbo()->getErrorMsg(), 'warning' );
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

					$upload_err = null;
					$file_id = $fman->upload(null, $upload_err);
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
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	function getFileData($fid, $published=1, $extra_select='')
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$file_ids = (array)$fid;
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
				$return_data[$file_id] = $cached_data[$f];
		}

		return !is_array($fid) ? @$return_data[(int)$fid] : $return_data;
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
			case 'bmp':
			case 'jpeg':
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
}
