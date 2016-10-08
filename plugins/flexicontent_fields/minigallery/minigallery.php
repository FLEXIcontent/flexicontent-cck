<?php
/**
 * @version 1.0 $Id: minigallery.php 1800 2013-11-01 04:30:57Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.file
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('FlexicontentControllerFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');  // we use JPATH_BASE since controller exists in frontend too
JLoader::register('FlexicontentModelFilemanager', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');  // we use JPATH_BASE since model exists in frontend too

class plgFlexicontent_fieldsMinigallery extends FCField
{
	static $field_types = array('minigallery');
	var $task_callable = array();
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_minigallery', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = 0; //$field->parameters->get('use_ingroup', 0);
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
		$tip_class     = $tooltip_class;  // Compatibility with older custom templates
		
		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : JRequest::getVar( 'unique_tmp_itemid' );
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || 1; //(int) $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required_class = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		// Inline file property editing
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;  // 1: file selection only,  0: inline file properties editing
		$top_notice = $use_ingroup ? '<div class="alert alert-warning">Field group mode is not implenent in current version, please disable</div>' : '';
		
		$iform_allowdel = 0;//$field->parameters->get('iform_allowdel', 1);
		$form_file_preview = $field->parameters->get('form_file_preview', '2');
		
		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$mediapath   = $flexiparams->get('media_path', 'components/com_flexicontent/medias');
		$docspath    = $flexiparams->get('file_path', 'components/com_flexicontent/uploads');
		$imageexts   = array('jpg','gif','png','bmp','jpeg');
		
		// Load file data
		if ( !$field->value ) {
			// Field value empty
			$files_data = array();
			$form_data = array();
			$field->value = array();
		}
		else {
			$file_ids  = array();
			$form_data = array();
			
			// Check if reloading user data after form validation error
			$v = reset($field->value);
			if (is_array($v) && isset($v['file-id']))
			{
				foreach($field->value as $v) {
					$file_ids[] = $v['file-id'];
					$form_data[$v['file-id']] = $v;
				}
			} else {
				$file_ids = $field->value;
			}
			
			// Get data for given file ids
			$files_data = $this->getFileData( $file_ids, $published=false );
			
			// Do not skip values if in fieldgroup
			if ($use_ingroup) {
				foreach($field->value as $i => $v) {
					$file_id = is_array($v) && isset($v['file-id']) ?  $v['file-id']  :  $v;
					$field->value[$i] = isset($files_data[$file_id]) ? (int)$file_id : 0;
				}
			} else {
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
				'id'=>'', 'filename'=>'', 'filename_original'=>'', 'altname'=>'', 'description'=>'',
				'url'=>'',
				'secure'=>$field->parameters->get('iform_dir_default', '1'),
				'ext'=>'', 'published'=>1,
				'language'=>$field->parameters->get('iform_lang_default', '*'),
				'hits'=>0,
				'uploaded'=>'', 'uploaded_by'=>0, 'checked_out'=>false, 'checked_out_time'=>'', 'access'=>0,
			);
		}
		
		// Button for popup file selection
		$autoselect = 1; //$field->parameters->get( 'autoselect', 1 ) ;
		$addExistingURL = JURI::base(true)
			.'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component'
			.'&amp;layout=image&amp;filter_secure=M'
			.'&amp;folder_mode=0&amp;index=%s'
			.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;autoselect='.$autoselect
			//.'&amp;filter_uploader='.$user->id
			.'&amp;targetid=%s'
			.'&amp;'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1';
		
		$_prompt_txt = JText::_( 'FLEXI_ADD_FILE' );
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $field->parameters->get('fields_box_placing', '0')==1 ? ' floated' : '';
		
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
			
			function fc_openFileSelection_".$field->id."(event) {
				var obj = jQuery(event.data.obj);
				var url = obj.attr('href');
				
				url = url.replace( '__rowno__',  obj.attr('data-rowno') ? obj.attr('data-rowno') : '' );
				url = url.replace( '__thisid__', obj.attr('id') ? obj.attr('id') : '' );
				
				fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container');
				return false;
			}
			
			function qfSelectFile".$field->id."(obj, id, file, targetid, file_data)
			{
				var result = 1;
				var preview = typeof file_data.preview !== 'undefined' ? file_data.preview : '';
				var altname     = typeof file_data.altname     !== 'undefined' ? file_data.altname     : '';
				var description = typeof file_data.description !== 'undefined' ? file_data.description : '';
				var language    = typeof file_data.language    !== 'undefined' ? file_data.language    : '';
				
				var altname = typeof file_data.altname !== 'undefined' ? file_data.altname : '';
				var displaytitle = altname && (altname!=file) ? altname : '-';
				var hidden_text  = altname && (altname!=file) ? file+'<br/>'+altname : '';
				
				var container = jQuery('#'+targetid).closest('.fcfieldval_container');
				container.find('.fc_fileid').val(id);
				
				container.find('.fc_filedata_txt_nowrap').html(hidden_text).show();
				container.find('.fc_filedata_txt').removeClass('file_unpublished').val(file).blur();
				container.find('.fc_filedata_title').html(displaytitle);
				
				container.find('.fc_preview_thumb').attr('src', preview ? preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
				
				".($form_file_preview == 2 ? "
				preview ? container.find('.fc_preview_thumb').show() : container.find('.fc_preview_thumb').hide();
				" : "")."
				
				container.find('.fc_filetitle').val(altname).blur();
				container.find('.fc_filelang').val(language).trigger('change');
				container.find('.fc_filedesc').val(description);
				
				// Increment value counter (which is optionally used as 'required' form element)
				var valcounter = document.getElementById('".$elementid."');
				if (valcounter) {
					valcounter.value = valcounter.value=='' ? '1' : parseInt(valcounter.value) + 1;
					//if (window.console) window.console.log ('valcounter.value: ' + valcounter.value);
				}
				
				if (targetid) fc_field_dialog_handle_".$field->id.".dialog('close');
				
				var remove_obj = container.find('.inlinefile-del');
				remove_obj.removeAttr('checked').trigger('change');
				return result;
			}
						
			jQuery(document).ready(function() {
				jQuery('a.addfile_".$field->id."').each(function(index, value) {
					jQuery(this).on( 'click',  {obj:this},  fc_openFileSelection_".$field->id." );
				});
			});
		";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				// inline mode
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();

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
				imgPreview.attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=').hide();
				
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
				
				".($iform_dir ? "
				var nr = 0;
				newField.find('.inlinefile-secure-info').remove();
				newField.find('.inlinefile-secure-data').show();
				newField.find('input.fc_filedir').each(function() {
					var elem = jQuery(this);
					elem.removeAttr('disabled');
					elem.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][secure]');
					elem.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_secure_'+nr);
					elem.next().attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_secure_'+nr).attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_file-secure'+nr+'-lbl');
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
				
				// Re-init any select2 elements
				var has_select2 = newField.find('div.select2-container').length != 0;
				if (has_select2) {
					newField.find('div.select2-container').remove();
					newField.find('select.use_select2_lib').select2('destroy').show().select2();
				}
				
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
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({'html': true,'container': newField});

				// Attach form validation on new element
				fc_validationAttach(newField);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks
				var btn = fieldval_box ? false : jQuery(el);
				if (btn) btn.css('pointer-events', 'none').off('click');

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
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}

				// If not removing re-enable clicks
				else if (btn) btn.css('pointer-events', '').on('click');
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="'.$add_on_class.' fcfield-delvalue'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="'.$add_on_class.' fcfield-drag-handle'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_before'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_after'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		flexicontent_html::loadFramework('flexi-lib');
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));
		foreach($field->html as &$_html_) {
			$_html_ = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
			'.$_html_;
		}
		unset($_html_);
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue '.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
		
		// Button for popup file selection
		/*if (!$use_ingroup) $field->html .= '
			<input id="'.$elementid.'" class="'.$required_class.' fc_hidden_input" type="text" name="__fcfld_valcnt__['.$field->name.']" value="'.($n ? $n : '').'" />';*/
		if ($top_notice) $field->html = $top_notice.$field->html;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;
		// Load file data
		if ( !$values ) {
			$files_data = array();
			$values = array();
		} else {
			$files_data = $this->getFileData( $values, $published=true );   //if ($field->id==NNN) { echo "<pre>"; print_r($files_data); exit; }
			$values = array();
			foreach($files_data as $file_id => $file_data) $values[] = $file_id;
		}

		$app = JFactory::getApplication();

		$document    = JFactory::getDocument();
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$mediapath   = $flexiparams->get('media_path', 'components/com_flexicontent/medias');
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
			$document->addStyleSheet(JURI::root(true).'/plugins/flexicontent_fields/minigallery/css/minigallery.css');
		  JHtml::_('behavior.framework', true);
		  $document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.js');
		  if($slideshowtype!='slideshow')
			{
		  	$document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.'.strtolower($slideshowtype).'.js');
		  	$slideshowClass .= '.'.$slideshowtype;
		  }
		  // this allows you to override the default css files
		  $csspath = JPATH_ROOT.'/templates/'.$app->getTemplate().'/css/minigallery.css';
		  if(file_exists($csspath)) {
				$document->addStyleSheet(JURI::root(true).'/templates/'.$app->getTemplate().'/css/minigallery.css');
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
					JURI::root(true) . '/' . $mediapath . '/' . $file_data->filename :
					$file_data->filename ;
				$srcs	= JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w='.$w_s.'&amp;h='.$h_s.'&amp;zc=1&amp;q=95&amp;ar=x';
				$srcb	= JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&amp;w='.$w_l.'&amp;h='.$h_l.'&amp;zc=1&amp;q=95&amp;ar=x';
				$ext = pathinfo($img_path, PATHINFO_EXTENSION);
				if ( in_array( $ext, array('png', 'ico', 'gif') ) ) {
					$srcs .= '&amp;f='. $ext;
					$srcb .= '&amp;f='. $ext;
				}

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
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;   //echo "<pre>"; print_r($post);
		
		// Get configuration
		$inputmode = (int)$field->parameters->get( 'inputmode', 1 ) ;
		$is_importcsv      = JRequest::getVar('task') == 'importcsv';
		$import_docs_folder  = JRequest::getVar('import_docs_folder');
		
		$iform_allowdel = $field->parameters->get('iform_allowdel', 1);
		
		$iform_title = $inputmode==1 ? 0 : $field->parameters->get('iform_title', 1);
		$iform_desc  = $inputmode==1 ? 0 : $field->parameters->get('iform_desc',  1);
		$iform_lang  = $inputmode==1 ? 0 : $field->parameters->get('iform_lang',  0);
		$iform_dir   = $inputmode==1 ? 0 : $field->parameters->get('iform_dir',   0);
		
		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( !$initialized ) {
			$initialized = 1;
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.path');
			$srcpath_original  = JPath::clean( JPATH_SITE .DS. $import_docs_folder .DS );
		}
		
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
			if ( $is_importcsv ) {
				if ( !is_numeric($v) ) {
					$filename = basename($v);
					$sub_folder = dirname($v);
					$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';
					
					$fman = new FlexicontentControllerFilemanager();
					$Fobj = new stdClass();
					$Fobj->return_url     = null;
					$Fobj->file_dir_path  = DS. $import_docs_folder . $sub_folder;
					$Fobj->file_filter_re = preg_quote($filename);
					$Fobj->secure = 0;
					$Fobj->keep   = 1;
					$file_ids = $fman->addlocal($Fobj);
					$v = !empty($file_ids) ? reset($file_ids) : false; // Get fist element
					//$_filetitle = key($file_ids);  this is the cleaned up filename, currently not needed
				}
			}
			
			// Using inline property editing
			else {
	    	$file_id = isset($v['file-id']) ? (int) $v['file-id'] : $v;
	    	$file_id = is_numeric($file_id) ? (int) $file_id : 0;  // if $v is not an array
	    	
	    	$err_code = isset($_FILES['custom']['error'][$field->name][$n]['file-data']) ? $_FILES['custom']['error'][$field->name][$n]['file-data'] : UPLOAD_ERR_NO_FILE;
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
				$v['file-del']   = !$iform_allowdel ? 0 : (int) @ $v['file-del'];
				$v['file-title'] = !$iform_title ? '' : flexicontent_html::dataFilter($v['file-title'],  1000,  'STRING', 0);
				$v['file-desc']  = !$iform_desc  ? '' : flexicontent_html::dataFilter($v['file-desc'],   10000, 'STRING', 0);
				$v['file-lang']  = !$iform_lang  ? '' : flexicontent_html::dataFilter($v['file-lang'],   9,     'STRING', 0);
				$v['secure']     = !$iform_dir   ? 0 : ((int) $v['secure'] ? 1 : 0);
				
				// UPDATE existing file
				if( !$new_file && $file_id ) {
					$dbdata = array();
					
					$dbdata['id'] = $file_id;
					if ($iform_title)  $dbdata['altname'] = $v['file-title'];
					if ($iform_desc)   $dbdata['description'] = $v['file-desc'];
					if ($iform_lang)   $dbdata['language'] = $v['file-lang'];
					// !! Do not change folder for existing files
					//if ($iform_dir) {  $dbdata['secure'] = $v['secure'];
					
					// Load file data from DB
					$row = JTable::getInstance('flexicontent_files', '');
					$row->load( $file_id );
					$_filename = $row->filename_original ? $row->filename_original : $row->filename;
					$dbdata['secure'] = $row->secure ? 1 : 0;  // !! Do not change media/secure -folder- for existing files
					
					// Security concern, check file is assigned to current item
					$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
					if ( $v['file-del'] ) {
						if ( !$isAssigned ) {
							//JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );
						} else {
							//JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to update file properties of a file: '".$_filename."', that is not assigned to current item", 'warning' );
						}
					}
					
					// Delete existing file if so requested
					if ( $v['file-del'] ) {
						$canDelete = $this->canDeleteFile($field, $file_id, $item);
						if ($isAssigned && $canDelete) {
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
						if ($use_ingroup) $newpost[$new++] = null;
						continue;  // Skip file since unloading / removal was requested
					}
					
					// Set the changed data into the object
					foreach ($dbdata as $index => $data) $row->{$index} = $data;
					
					// Update DB data of the file 
					if ( !$row->check() || !$row->store() ) {
						JFactory::getApplication()->enqueueMessage("FILE FIELD: ".JFactory::getDBO()->getErrorMsg(), 'warning' );
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}
					
					// Set file id as value of the field
					$v = $file_id;
				}
				
				//INSERT new file
				else if( $new_file )
				{
					// new file was uploaded, but also handle previous selected file ...
					if ($file_id)
					{
						// Security concern, check file is assigned to current item
						$isAssigned = $this->checkFileAssignment($field, $file_id, $item);
						if ( !$isAssigned ) {
							/*$row = JTable::getInstance('flexicontent_files', '');
							$row->load( $file_id );
							$_filename = $row->filename_original ? $row->filename_original : $row->filename;
							JFactory::getApplication()->enqueueMessage("FILE FIELD: refusing to delete file: '".$_filename."', that is not assigned to current item", 'warning' );*/
						}
						
						// Delete previous file if no longer used
						else if ( $this->canDeleteFile($field, $file_id, $item) ) {
							$fm = new FlexicontentModelFilemanager();
							$fm->delete( array($file_id) );
						}
					}
					
					// Skip file if unloading / removal was requested
					if ( $v['file-del'] ) {
						if ($use_ingroup) $newpost[$new++] = null;
						continue;
					}
					
					$fman = new FlexicontentControllerFilemanager();   // Controller will do the data filter too
					JRequest::setVar( 'return-url', null, 'post' );  // needed !
					JRequest::setVar( 'secure', $v['secure'], 'post' );
					JRequest::setVar( 'file-title', $v['file-title'], 'post' );
					JRequest::setVar( 'file-desc', $v['file-desc'], 'post' );
					JRequest::setVar( 'file-lang', $v['file-lang'], 'post' );
					
					// The dform field name of the <input type="file" ...
					JRequest::setVar( 'file-ffname', 'custom', 'post' );
					JRequest::setVar( 'fname_level1', $field->name, 'post' );
					JRequest::setVar( 'fname_level2', $n, 'post' );
					JRequest::setVar( 'fname_level3', 'file-data', 'post' );
					$file_id = $fman->upload();
					$v = !empty($file_id) ? $file_id : ($use_ingroup ? null : false);
				}
				
				else {
					// no existing file and no new file uploaded
					$v = $use_ingroup ? null : false;
				}
	    }
			
	    if (!$use_ingroup) {
	    	// NOT inside field group, add it only if not empty reverse the file array, indexing it by file IDs, to add each file only once
				if ( !empty($v) && is_numeric($v) ) $newpost[(int)$v] = $new++;
			} else {
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
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function getFileData( $value, $published=1, $extra_select='' )
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$values = is_array($value) ? $value : array($value);
		foreach ($values as $file_id) {
			$f = (int)$file_id;
			if ( !isset($cached_data[$f]) && $f)
				$new_ids[] = $f;
		}
		
		// Get file data not retrieved already
		if ( count($new_ids) )
		{
			// Only query files that are not already cached
			$db = JFactory::getDBO();
			$query = 'SELECT * '. $extra_select //filename, filename_original, altname, description, ext, id'
					. ' FROM #__flexicontent_files'
					. ' WHERE id IN ('. implode(',', $new_ids) . ')'
					. ($published ? '  AND published = 1' : '')
					;
			$db->setQuery($query);
			$new_data = $db->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data) {
				$cached_data[$file_id] = $file_data;
			}
		}
		
		// Finally get file data in correct order
		foreach($values as $file_id) {
			$f = (int)$file_id;
			if ( isset($cached_data[$f]) && $f)
				$return_data[$file_id] = $cached_data[$f];
		}

		return !is_array($value) ? @$return_data[(int)$value] : $return_data;
	}


	// ************************************************
	// Returns an array of images that can be deleted
	// e.g. of a specific field, or a specific uploader
	// ************************************************
	function canDeleteFile( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = JFactory::getDBO();
		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE id='. $db->Quote($file_id)
			;
		$db->setQuery($query);
		$file_id = $db->loadResult();
		if (!$file_id)  return true;
		
		$ignored['item_id'] = $item->id;
		
		$fm = new FlexicontentModelFilemanager();
		return $fm->candelete( array($file_id), $ignored );
	}
	
	
	// *****************************************
	// Check if file is assigned to current item
	// *****************************************
	function checkFileAssignment( &$field, $file_id, &$item )
	{
		// Check file exists in DB
		$db   = JFactory::getDBO();
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
