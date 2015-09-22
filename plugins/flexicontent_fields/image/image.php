<?php
/**
 * @version 1.0 $Id: image.php 1901 2014-05-07 02:37:25Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.image
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsImage extends JPlugin
{
	static $field_types = array('image');
	static $value_only_displays = array("display_backend_src"=>0, "display_small_src"=>1, "display_medium_src"=>2, "display_large_src"=>3, "display_original_src"=>4);
	static $single_displays = array('display_single'=>0, 'display_single_total'=>1, 'display_single_link'=>2, 'display_single_total_link'=>3);
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsImage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_image', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		
		static $common_js_css_added = false;
		
		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : JRequest::getVar( 'unique_tmp_itemid' );
		
		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $u_item_id;
		
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		$existing_imgs= $field->parameters->get('existing_imgs', 1);
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\','/', $dir);
		
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		$autoupload = $field->parameters->get('autoupload', 0);
		$autoassign = $field->parameters->get('autoassign', 0);
		$always_allow_removal = $field->parameters->get('always_allow_removal', 0);
		$imgsel_visible = $field->parameters->get('imgsel_visible', 0);
		
		$thumb_w_s = $field->parameters->get( 'w_s', 120 );
		$thumb_h_s = $field->parameters->get( 'h_s', 90 );
		
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
		
		$none_props = !$linkto_url && !$usealt && !$usetitle && !$usedesc && !$usecust1 && !$usecust2;
		
		if ( !$common_js_css_added ) {
			$js = "
				function fx_toggle_upload_select_tbl (obj_changed, objid_disp_toggle) {
					var value_container = jQuery(obj_changed).closest('.fcfieldval_container');
					obj_disp_toggle = value_container.find('table.img_upload_select');
					if (obj_changed.checked) {
						obj_disp_toggle.css('display', 'table');
						value_container.css('width', '100%');
					} else {
						obj_disp_toggle.css('display', 'none');
						value_container.css('width', '');
					}
				}
					";
			$document->addScriptDeclaration($js);
			$common_js_css_added = true;
		}
		
		$field->html = '';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['originalname'] = '';
			$field->value[0] = serialize($field->value[0]);
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "
			var fc_field_dialog_handle_".$field->id.";
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
			
			// WARNING: bellow we also use $field->name which is different than $fieldname
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$auto_enable_imgpicker = 0;  // Disabled imagepicker during copy to help performance
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
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				
			".( $image_source ? "" :"
				var has_imagepicker = newField.find('ul.image_picker_selector').length != 0;
				var has_select2     = newField.find('div.select2-container').length != 0;
				if (has_imagepicker) newField.find('ul.image_picker_selector').remove();
				if (has_select2)     newField.find('div.select2-container').remove();
				").
			"
			
				newField.find('input.hasvalue').val('');
				newField.find('input.hasvalue').attr('name','".$elementid."_'+uniqueRowNum".$field->id."+'_hasvalue');
				newField.find('input.hasvalue').attr('id','".$elementid."_'+uniqueRowNum".$field->id.");
				
				newField.find('input.newfile').val('');
				newField.find('input.newfile').attr('name','".$field->name."['+uniqueRowNum".$field->id."+']');
				newField.find('input.newfile').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_newfile');
				
				newField.find('input.originalname').val('');
				newField.find('input.originalname').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][originalname]');
				newField.find('input.originalname').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_originalname');
				
				newField.find('.existingname').val('');
				newField.find('.existingname').addClass('no_value_selected');
				newField.find('.existingname').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][existingname]');
				newField.find('.existingname').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_existingname');
				
			".( $image_source ? "" :"
				if (has_imagepicker && ".$auto_enable_imgpicker." ) newField.find('select.image-picker').imagepicker({ hide_select:false, show_label:true });
				if (has_select2)  newField.find('select.use_select2_lib').show().select2();
				").
			"
				newField.find('a.addfile_".$field->id."').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addfile');
				newField.find('a.addfile_".$field->id."').attr('href','".JURI::base(true).'/index.php?option=com_flexicontent&view=fileselement&tmpl=component&layout=image&filter_secure=M&folder_mode=1&'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1&field='.$field->id.'&u_item_id='.$u_item_id.'&targetid='.$elementid."_'+uniqueRowNum".$field->id."+'_existingname&thumb_w=".$thumb_w_s.'&thumb_h='.$thumb_h_s.'&autoassign='.$autoassign."');
				
				// COPY an preview box
				var img_preview = newField.find('img.preview_image');
				var empty_img   = newField.find('img.preview_image');
				var old_preview = img_preview.length ? img_preview : newField.find('div.empty_image');
				
				if (old_preview.length)
				{
					var tmpDiv = jQuery('<div class=\"empty_image empty_image".$field->id."\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\"></div>');
					tmpDiv.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
					tmpDiv.insertAfter( old_preview );
					old_preview.remove();
				}
				
				var imgchange_toggler = newField.find('input.imgchange');
				if (imgchange_toggler.length) {
					imgchange_toggler.prop('name','".$field->name."['+uniqueRowNum".$field->id."+']');
					imgchange_toggler.prop('id','".$elementid."_'+uniqueRowNum".$field->id."+'_change');
					imgchange_toggler.parent().find('label').prop('for','".$elementid."_'+uniqueRowNum".$field->id."+'_change');
					
					newField.find('table.img_upload_select').attr('id','".$field->name."_upload_select_tbl_'+uniqueRowNum".$field->id.");
					newField.find('table.img_upload_select').css('display', 'table');
					newField.find('input.imgchange').prop('checked', true);
				}
					";
				
			if ($linkto_url) $js .= "
				newField.find('input.imglink').val('');
				newField.find('input.imglink').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][urllink]');
				newField.find('input.imglink').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_urllink');
					";
				
			if ($usealt) $js .= "
				newField.find('input.imgalt').val('".$default_alt."');
				newField.find('input.imgalt').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][alt]');
				newField.find('input.imgalt').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_alt');
					";
				
			if ($usetitle) $js .= "
				newField.find('input.imgtitle').val('".$default_title."');
				newField.find('input.imgtitle').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				newField.find('input.imgtitle').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
					";
				
			if ($usedesc) $js .= "
				newField.find('textarea.imgdesc').val('".$default_desc."');
				newField.find('textarea.imgdesc').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][desc]');
					";
				
			if ($usecust1) $js .= "
				newField.find('input.imgcust1').val('".$default_cust1."');
				newField.find('input.imgcust1').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][cust1]');
					";
				
			if ($usecust2) $js .= "
				newField.find('input.imgcust2').val('".$default_cust2."');
				newField.find('input.imgcust2').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][cust2]');
					";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				
				// Add jQuery modal window to the select image file button
				newField.find('a.addfile_".$field->id."').each(function(index, value) {
					jQuery(this).on('click', function() {
						var url = jQuery(this).attr('href');
						fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container');
						return false;
					});
				});
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
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// When removing a field value we need to check if it had value and decrement the value counter
				var originalfftag = 'input.originalname';
				var existingfftag = '".($image_source ? "input" :"select")."' + '.existingname';
				var originalname = row.find( originalfftag ).val();
				var existingname = row.find( existingfftag ).val();
				
				if ( originalname != '' || existingname != '' )
				{
					// A non-empty container is being removed ... get counter (which is optionally used as 'required' form element and empty it if is 1, or decrement if 2 or more)
					var valcounter = document.getElementById('".$field->name."');
					valcounter.value = ( !valcounter.value || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
					//if(window.console) window.console.log ('valcounter.value: ' + valcounter.value);
				}
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '
			ul#sortables_'.$field->id.' {
				float:left; margin:0px; padding:0px;
				list-style:none; white-space:normal;
			}
			ul#sortables_'.$field->id.' li {
				'.($none_props ?
					'clear:none; white-space:normal;' :
					'').'
				float:left;
				display: block;
				list-style: none;
				position: relative;  .'/* do not make important */.'
			}
			.fcfieldval_container_'.$field->id.' input { cursor:text; }
			.fcfieldval_container_'.$field->id.' .fcimg_preview_box { min-width:'.($thumb_w_s+6).'px; min-height:'.($thumb_h_s+8).'px; }
			';
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		// Common JS/CSS
		$image_folder = JURI::root(true).'/'.$dir_url;
		$js .= "
			var fc_db_img_path='".$image_folder."';
			function qmAssignFile".$field->id."(tagid, file, file_url, action)
			{
				// Get TAG ID of the main form element of this field
				var action = typeof action!== 'undefined' ? action : '0';
				var ff_suffix = (tagid.indexOf('_existingname') > -1) ? '_existingname' : '_newfile';
				var elementid = tagid.replace(ff_suffix,'');
				
				// Get current value of new / existing filename fields
				var hasvalue_obj = jQuery('#' + elementid );
				var original_obj = jQuery('#' + elementid + '_originalname' );
				var existing_obj = jQuery('#' + elementid + '_existingname' );
				var existingAllowed = existing_obj.length != 0;
				
				var originalname = original_obj.val();
				var existingname = existingAllowed ? existing_obj.val() : '';
				
				// a flag if file URL was given
				var fileUrlGiven = file_url!='';
				
				if (file=='')  // DB-mode
				{
					var newfilename  = jQuery('#' + elementid + '_newfile' ).val();
					
					// Assigning newfile
					if ( ff_suffix == '_newfile' )
					{
						if ( jQuery('#' + elementid + '_newfile' ).hasClass('no_value_selected') && newfilename!='' ) {
							var modify = ( originalname=='' && existingname=='' ) ? 1 : 0;
							jQuery('#' + elementid + '_newfile' ).removeClass('no_value_selected');
						} else if ( !jQuery('#' + elementid + '_newfile' ).hasClass('no_value_selected') && newfilename=='' ) {
							var modify = -1;
							jQuery('#' + elementid + '_newfile' ).addClass('no_value_selected');
						} else {
							var modify = 0;
						}
						if (existingAllowed) existing_obj.addClass('no_value_selected').val('');
					}
					
					// Assigning existingfile
					else
					{
						//alert('existingAllowed: ' + existingAllowed + ', existingname: ' + existingname + ', no_value_selected: ' + (existing_obj.hasClass('no_value_selected') ? 'yes' : 'no'));
						if ( existingAllowed && existingname!='' && existing_obj.hasClass('no_value_selected') ) {
							var modify = ( originalname=='' ) ? 1 : 0;
							existing_obj.removeClass('no_value_selected');
						} else if ( existingAllowed && existingname=='' && !existing_obj.hasClass('no_value_selected') ) {
							var modify = -1;
							existing_obj.addClass('no_value_selected');
						} else {
							var modify = 0;
						}
						jQuery('#' + elementid + '_newfile' ).addClass('no_value_selected').val('');
					}
					
					// Increment/decrement the form field used as value counter, we do not use ZERO in case of decrement, instead we set to empty string, so that is-required validation works
					var valcounter = document.getElementById('".$field->name."');
					if (modify>0)
					{
						if ( typeof valcounter.value === 'undefined' || valcounter.value=='' ) valcounter.value = '1';
						else valcounter.value = parseInt(valcounter.value) + modify;
						hasvalue_obj.val('1');  // value assigned
					}
					
					else if (modify<0)
					{
						if ( valcounter.value=='1' ) valcounter.value = '';
						else valcounter.value = parseInt(valcounter.value) + modify;
						hasvalue_obj.val('');  // value de-assigned, (or ? fieldgroup is being deleted)
					}
					
				} else {  // Folder mode
					
					if ( originalname=='' && existingname=='' ) {
						// Increment/Make non-empty the form field used as value counter, so that is-required validation works
						var valcounter = document.getElementById('".$field->name."');
						if ( valcounter.value=='' ) valcounter.value = '1';
						else valcounter.value = parseInt(valcounter.value) + 1;
					}
					hasvalue_obj.val('1');  // value assigned
				}
				
				//if(window.console) window.console.log(valcounter.value);
				
				var prv_obj = jQuery('#' + elementid + '_preview_image' );
				
				// DB-Mode
				if (file == '') {
					var imgdel_box = jQuery( '#' + elementid + '_imgdelete' );
					//imgdel_box.remove();
					imgdel_box.hide();
					if (originalname) {
						imgdel_box.find('input').prop('type', 'text');
						imgdel_box.find('input').val(originalname);
					}
				}
				
				// Folder-Mode
				if (file != '' && existingAllowed)  existing_obj.val(file);
				
				// DB-Mode, Folder-Mode empty original value
				original_obj.val('');
				
				if (prv_obj) {
					preview_msg = '<span id=\"'+elementid+'_preview_msg\"></span>';
					if (file || (fileUrlGiven && existingAllowed && !existing_obj.hasClass('no_value_selected')) ) {
						var preview_container = '<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"'+file_url+'\" style=\"border: 1px solid silver; float:left;\" alt=\"Preview image\" />';
					} else if (action!='0') {
						var preview_container = '<div class=\"empty_image empty_image".$field->id."\" id=\"'+elementid+'_preview_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\">';
					} else {
						var preview_container = '<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"\" style=\"border: 1px solid silver; float:left;\" alt=\"Preview image\" />';
					}
					
					var tmpDiv = jQuery(preview_container);
					tmpDiv.insertAfter( prv_obj );
					var tmpDiv = jQuery(preview_msg);
					tmpDiv.insertAfter( prv_obj );
					prv_obj.remove();
					
					if (file || (fileUrlGiven && existingAllowed && !existing_obj.hasClass('no_value_selected')) || action!='0') {
					} else {
						fc_loadImagePreview(tagid, elementid+'_preview_image', elementid+'_preview_msg', ".$thumb_w_s.", "./*$thumb_h_s*/'0'.");
					}
				}
				// Close dialog if open,  action=='1'  means do not closing modal popup
				if (action!='1' && fc_field_dialog_handle_".$field->id.")
					fc_field_dialog_handle_".$field->id.".dialog('close');
			}
		";
		$css .='
			table.fcfield'.$field->id.'.img_upload_select li { min-height:'.($thumb_h_s+56).'px; }
			table.fcfield'.$field->id.'.img_upload_select ul { height:'.($thumb_h_s+96).'px; }
			table.fcfield'.$field->id.'.img_upload_select ul { width:'.(2*($thumb_w_s+64)).'px; }
			table.fcfield'.$field->id.'.img_upload_select ul.image_picker_selector { min-height: 400px; max-height: 600px; height: unset; width:100%; box-sizing: border-box; }
		';
		
		// Add jQuery modal window to the select image file button, the container will be created if it does not exist already
		if ( $image_source ) {
			$js .= "
			jQuery(document).ready(function() {
				jQuery('a.addfile_".$field->id."').each(function(index, value) {
					jQuery(this).on('click', function() {
						var url = jQuery(this).attr('href');
						fc_field_dialog_handle_".$field->id." = fc_showDialog(url, 'fc_modal_popup_container');
						return false;
					});
				});
			});
			";
		} else {
			$select = $existing_imgs ? $this->buildSelectList( $field ) : '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		flexicontent_html::loadFramework('flexi-lib');
		
		
		$class = ' class="'.$required.' "';
		$onchange= ' onchange="';
		
		$onchange .= " qmAssignFile".$field->id."(this.id, '', '');";
		$js_submit = FLEXI_J16GE ? "Joomla.submitbutton('items.apply')" : "submitbutton('apply')";
		$onchange .= ($autoupload && $app->isAdmin()) ? $js_submit : '';
		$onchange .= ' "';
		
		$i = -1;  // Count DB values (may contain invalid entries)
		$n = 0;   // Count sortable records added (the verified values or a single empty record if no good values)
		$count_vals = 0;  // Count non-empty sortable records added
		$image_added = false;
		$skipped_vals = array();
		$uploadLimitsTxt = $this->getUploadLimitsTxt($field);
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('originalname' => $value);
			}
			$i++;
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$image_name = !empty($value['existingname']) ? $value['existingname'] : trim(@$value['originalname']);  // existingname should be present only via form reloading
			
			// Check and rebuild thumbnails if needed
			$rebuild_res = !empty($value['existingname']) ? true : plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item);
			
			// Check if rebuilding thumbnails failed (e.g. file has been deleted)  
			if ( !$rebuild_res ) {
				// For non-empty value set a message when we have examined all values
				if ($image_name) $skipped_vals[] = $image_name;
				
				// Skip current value but add and an empty image container if :
				// (a) no other image exists or  (b) field is in a group
				if (!$use_ingroup && ($image_added || ($i+1) < count($field->value)) ) {
					continue;
				} else {
					// 1st value or empty value for fieldgroup position
					$image_name = '';
				}
			} else {
				$count_vals++;
			}
			
			if ( $image_source )
			{
				$linkfsel = JURI::base(true)
					.'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;layout=image&amp;filter_secure=M&amp;folder_mode=1'
					.'&amp;field='.$field->id.'&amp;u_item_id='.$u_item_id.'&amp;targetid='.$elementid_n."_existingname&amp;thumb_w=$thumb_w_s&amp;thumb_h=$thumb_h_s&amp;autoassign=".$autoassign
					.'&amp;'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1';
				
				$_prompt_txt = JText::_( 'FLEXI_SELECT_IMAGE' );
				$select = '
				<input class="existingname fcfield_textval" id="'.$elementid_n.'_existingname" name="'.$fieldname_n.'[existingname]" value="'.$image_name.'" readonly="readonly" style="float:none;" />
				'.($none_props ? '<br/>' : '').'
				<span class="fcfield-button-add">
					<a class="addfile_'.$field->id.'" id="'.$elementid_n.'_addfile" title="'.$_prompt_txt.'" href="'.$linkfsel.'" >
						'.$_prompt_txt.'
					</a>
				</span>';
			}
			
			// Add current image or add an empty image container
			$delete = $remove = $change = '';
			if ( $image_name )
			{
				if ( !$multiple)
				{
					$remove_disabled = '';
					if ( !$image_source )
					{
						$canDeleteImage = $this->canDeleteImage( $field, $image_name, $item );
						$delete_disabled = $canDeleteImage ? '' : ' disabled="disabled"';
						$delete  = '<div id="'.$elementid_n.'_imgdelete" class="imgdelete">';
						$delete .= ' <input class="imgdelete" type="checkbox" name="'.$fieldname_n.'[delete]" id="'.$elementid_n.'_delete" value="1"'.$delete_disabled.' />';
						$delete .= ' <label for="'.$elementid_n.'_delete">'.JText::_( 'FLEXI_FIELD_DELETE_FILE' ).'</label>';
						$delete .= '</div>';
						$remove_disabled = $always_allow_removal ? '' : ($canDeleteImage ? ' disabled="disabled"' : '');
					}
					$remove  = '<div id="'.$elementid_n.'_imgremove" class="imgremove">';
					$remove .= ' <input class="imgremove" type="checkbox" name="'.$fieldname_n.'[remove]" id="'.$elementid_n.'_remove" value="1"'.$remove_disabled.' />';
					$remove .= ' <label style="display:inline;" for="'.$elementid_n.'_remove">'.JText::_( 'FLEXI_FIELD_REMOVE_VALUE' ).'</label>';
					$remove .= '</div>';
				}
				
				$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="'.$value['originalname'].'" />';
				if ($use_ingroup) $originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="hidden" class="hasvalue '.($use_ingroup ? $required : '').'" value="'.$value['originalname'].'" />';
				
				if (!empty($image_name)) {
					$img_link  = JURI::root(true).'/'.$dir_url;
					$img_link .= ($image_source ? '/item_'.$u_item_id . '_field_'.$field->id : "");
					$img_link .= $item->id ? '/s_' .$extra_prefix. $image_name : '/original/'. $image_name;
					if (isset($value['existingname'])) {
 						$img_link = str_replace('\\','/', $img_link);
 						$img_link = JURI::root().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$img_link.'&amp;w=120&amp;h=90';
 					}
				} else {
					$img_link = '';
				}
				$imgpreview = '<img class="preview_image" id="'.$elementid_n.'_preview_image" src="'.$img_link.'" style="border: 1px solid silver; float:left;" alt="Preview image" />';
				
			} else {
				
				$originalname = '<input name="'.$fieldname_n.'[originalname]" id="'.$elementid_n.'_originalname" type="hidden" class="originalname" value="" />';
				if ($use_ingroup) $originalname .= '<input name="'.$elementid_n.'_hasvalue" id="'.$elementid_n.'" type="hidden" class="hasvalue '.($use_ingroup ? $required : '').'" value="" />';
				$imgpreview = '<div class="empty_image empty_image'.$field->id.'" id="'.$elementid_n.'_preview_image" style="height:'.$field->parameters->get('h_s').'px; width:'.$field->parameters->get('w_s').'px;"></div>';
			}
			
			if ( !$image_source && !$imgsel_visible ) {
				$change .= ' <input class="imgchange" style="display:none;" type="checkbox" name="'.$fieldname_n.'[change]" id="'.$elementid_n.'_change" onchange="fx_toggle_upload_select_tbl(this)" value="1" '.($image_name ? '' : ' checked="checked" ').'/>';
				$change .= ' <span></span><label class="fcfield-button" style="margin: 0px 0px 4px 0px !important;" for="'.$elementid_n.'_change">'.JText::_( 'FLEXI_TOGGLE_IMAGE_SELECTOR' ).'</label>';
			}
			
			if ($linkto_url) $urllink =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).'</td>
					<td><input class="imglink" size="40" name="'.$fieldname_n.'[urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" /></td>
				</tr>';
			if ($usealt) $alt =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).'</td>
					<td><input class="imgalt" size="40" name="'.$fieldname_n.'[alt]" value="'.(isset($value['alt']) ? $value['alt'] : $default_alt).'" type="text" /></td>
				</tr>';
			if ($usetitle) $title =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><input class="imgtitle" size="40" name="'.$fieldname_n.'[title]" value="'.(isset($value['title']) ? $value['title'] : $default_title).'" type="text" /></td>
				</tr>';
			if ($usedesc) $desc =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).': <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><textarea class="imgdesc" name="'.$fieldname_n.'[desc]" rows="3" cols="28" >'.(isset($value['desc']) ? $value['desc'] : $default_desc).'</textarea></td>
				</tr>';
			if ($usecust1) $cust1 =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_IMG_CUST1' ).'</td>
					<td><input class="imgcust1" size="40" name="'.$fieldname_n.'[cust1]" value="'.(isset($value['cust1']) ? $value['cust1'] : $default_cust1).'" type="text" /></td>
				</tr>';
			if ($usecust2) $cust2 =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_IMG_CUST2' ).'</td>
					<td><input class="imgcust2" size="40" name="'.$fieldname_n.'[cust2]" value="'.(isset($value['cust2']) ? $value['cust2'] : $default_cust2).'" type="text" /></td>
				</tr>';
			
			$curr_select = $select ? str_replace('__FORMFLDNAME__', $fieldname_n.'[existingname]', $select) : '';
			$curr_select = $select ? str_replace('__FORMFLDID__', $elementid_n.'_existingname', $curr_select) : '';
			
			$field->html[] = '
			'.($image_source ? $curr_select : $change).'
			'.($multiple ? '
				'.(!$none_props ? '<div class="fcclear"></div>' : '').'
				<div class="nowrap_box">
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				</div>
				<div class="fcclear"></div>
				' : '').'
			<div class="fcimg_preview_box" style="float:left!important; clear:none!important; margin-right:5px!important;">
				'.$imgpreview.'
				'.$originalname.'
				<div style="float:left; clear:both;" class="imgactions_box">
					'.($remove ? $remove : '').'
					'.($delete ? $delete : '').'
				</div>
			</div>
			'
			
			.(($linkto_url || $usealt || $usetitle || $usedesc || $usecust1 || $usecust2) ?
			'
			<div style="float:left; clear:none;" class="img_value_props">
				<table class="admintable"><tbody>
					'.@$urllink.'
					'.@$alt.'
					'.@$title.'
					'.@$desc.'
					'.@$cust1.'
					'.@$cust2.'
				</tbody></table>
			</div>'
			: '').
			
			( !$image_source ? '
				<table class="admintable fcfield'.$field->id.' img_upload_select" id="'.$field->name.'_upload_select_tbl_'.$n.'" style="'.($image_name && $imgsel_visible==0 ? "display:none;" : "").($multiple ? '' : 'width:auto;').'" >
				<tbody>
					<tr class="img_newfile_row">
						'.($curr_select ? '<td class="key fckey_high">'.JText::_( 'FLEXI_FIELD_NEWFILE' ).':</td>' : '').'
						<td style="white-space: normal;">'.
							'<input name="'.$field->name.'['.$n.']" id="'.$elementid_n.'_newfile" class="newfile no_value_selected" '.$onchange.' type="file" /><br/>' .
							$uploadLimitsTxt.
							'<br/><span class="label label-info">'.JText::_( 'FLEXI_FIELD_ALLOWEDEXT' ).'</span>'.
							'<span style="margin-left:12px;">'.str_replace(",", ", ", $field->parameters->get('upload_extensions')) .'</span>
						</td>
					</tr>
					'.($curr_select ? '
					<tr class="img_existingfile_row">
						<td class="key fckey_high">'.JText::_( !$image_source ? 'FLEXI_FIELD_EXISTINGFILE' : 'FLEXI_SELECT' ).':</td>
						<td>'.$curr_select.'</td>
					</tr>
					' : '').'
				</tbody></table>
			'  :  '')
			;
			
			$n++;
			$image_added = true;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
		
		// This is field HTML that is created regardless of values
		$non_value_html = '<input id="'.$field->name.'" class="'.($use_ingroup ? '' : $required).'" type="hidden" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'" />';
		if ($use_ingroup) {
			$field->html[-1] = $non_value_html;
		} else {
			$field->html .= $non_value_html;
		}
		
		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$image_source = $field->parameters->get('image_source', 0);
		
		
		// ***********************
		// One time initialization 
		// ***********************
		static $initialized = null;
		static $app, $document, $option;
		static $isMobile, $isTablet, $useMobile;
		if ($initialized===null)
		{
			$app       = JFactory::getApplication();
			$document	 = JFactory::getDocument();
			$option    = JRequest::getVar('option');
			jimport('joomla.filesystem');
			
			// *****************************
			// Get isMobile / isTablet Flags
			// *****************************
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			//$start_microtime = microtime(true);
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
			//$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//printf('<br/>-- [Detect Mobile: %.3f s] ', $time_passed/1000000);
		}
		
		
		// **********************************************
		// Static FLAGS indicating if JS libs were loaded
		// **********************************************
		
		static $multiboxadded = false;
		static $fancyboxadded = false;
		static $gallerifficadded = false;
		static $elastislideadded = false;
		static $photoswipeadded  = false;
		
		
		// *****************************
		// Current view variable / FLAGs
		// *****************************
		
		$realview = JRequest::getVar('view', FLEXI_ITEMVIEW);
		$view = JRequest::getVar('flexi_callview', $realview);
		$isFeedView = JRequest::getCmd('format', null) == 'feed';
		$isItemsManager = $app->isAdmin() && $realview=='items' && $option=='com_flexicontent';
		$isSite = $app->isSite();
		
		
		
		// *************************************************
		// TODO:  implement MODES >= 2, and remove this CODE
		// *************************************************
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\','/', $dir);
		
		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
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
			$separatorf = '<br />';
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
		
		
		// **************************************************
		// SETUP VALUES: retrieve, verify, load defaults, etc
		// **************************************************
		
		$values = $values ? $values : $field->value;
		
		// Intro-full mode get their values from item's parameters
		if ( $image_source == -1 ) {
			$values = array();
			$_image_name = $view=='item' ? 'fulltext' : 'intro';
			if ( $item->images ) {
				if (!is_object($item->images)) $item->images = new JRegistry($item->images);
				//echo "<pre>"; print_r($item->images); echo "</pre>";
				$_image_path = $item->images->get('image_'.$_image_name, '');
				$image_by_params = array();
				// field attributes (mode-specific)
				$image_by_params['image_size']  = $_image_name;
				$image_by_params['image_path']  = $_image_path;
				// field attributes (value)
				$image_by_params['originalname'] = basename($_image_path);
				$image_by_params['alt']   = $item->images->get('image_'.$_image_name.'_alt', '');
				$image_by_params['title'] = $item->images->get('image_'.$_image_name.'_alt', '');
				$image_by_params['desc']  = $item->images->get('image_'.$_image_name.'_caption', '');
				$image_by_params['cust1'] = '';
				$image_by_params['cust2'] = '';
				$image_by_params['urllink'] = '';
				$values = array(serialize($image_by_params));
				//echo "<pre>"; print_r($image_by_params); echo "</pre>"; exit;
			}
		}
		
		// Check for deleted image files or image files that cannot be thumbnailed,
		// rebuilding thumbnails as needed, and then assigning checked values to a new array
		$usable_values = array();
		if ($values) foreach ($values as $index => $value) {
			$value	= unserialize($value);
			if ( plgFlexicontent_fieldsImage::rebuildThumbs($field, $value, $item) )  $usable_values[] = $values[$index];
		}
		$values = & $usable_values;
		
		// Allow for thumbnailing of the default image
		$field->using_default_value = false;
		if ( !count($values) ) {
			// Create default image to be used if  (a) no image assigned  OR  (b) images assigned have been deleted
			$default_image = $field->parameters->get( 'default_image', '');
			if ( $default_image ) {
				$default_image_val = array();
				// field attributes (default value specific)
				$default_image_val['default_image'] = $default_image;  // holds complete relative path and indicates that it is default image for field
				// field attributes (value)
				$default_image_val['originalname'] = basename($default_image);
				$default_image_val['alt'] = $default_alt;
				$default_image_val['title'] = $default_title;
				$default_image_val['desc'] = $default_desc;
				$default_image_val['cust1'] = $default_cust1;
				$default_image_val['cust2'] = $default_cust2;
				$default_image_val['urllink'] = '';
				
				// Create thumbnails for default image
				if ( plgFlexicontent_fieldsImage::rebuildThumbs($field, $default_image_val, $item) ) $values = array(serialize($default_image_val));
				// Also default image can (possibly) be used across multiple fields, so set flag to add field id to filenames of thumbnails
				$multiple_image_usages = true;
				$extra_prefix = 'fld'.$field->id.'_';
				$field->using_default_value = true;
			}
		}
		
		
		// *********************************************
		// Check for no values, and return empty display
		// *********************************************
		if ( !count($values) ) {
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}
		// Assign (possibly) altered value array to back to the field
		$field->value = $values;  // This is not done for onDisplayFieldValue, TODO check if this is needed
		
		
		// **************************************
		// Default display method depends on view
		// **************************************
		
		if ($prop=='display' && ($view==FLEXI_ITEMVIEW || $view=='category')) {
			$_method = $view==FLEXI_ITEMVIEW ?
				$field->parameters->get( 'default_method_item',  'display' ) :
				$field->parameters->get( 'default_method_cat',  'display_single_total') ;
		} else {
			$_method = $prop;
		}
		$cat_link_single_to = $field->parameters->get( 'cat_link_single_to', 1) ;
		
		// Calculate some flags, SINGLE image display and Link-to-Item FLAG
		$isSingle = isset(self::$single_displays[$_method]);
		$linkToItem = $_method == 'display_link' || $_method == 'display_single_link' || $_method == 'display_single_total_link' || ($view!='item' && $cat_link_single_to && $isSingle);
		
		
		// ************************
		// JS gallery configuration
		// ************************
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
		$grouptype = $multiple ? 0 : $grouptype;  // Field in gallery mode: Force grouping of images per field (current item)
		
		// Needed by some js galleries
		$thumb_w_s = $field->parameters->get( 'w_s', 120);
		$thumb_h_s = $field->parameters->get( 'h_s',  90);
		
		
		// ******************************
		// Hovering ToolTip configuration
		// ******************************
		$uselegend  = $field->parameters->get( 'uselegend', 1 ) ;
		$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		
		// Enable/disable according to current view
		$legendinview = $field->parameters->get('legendinview', array(FLEXI_ITEMVIEW,'category'));
		$legendinview  = FLEXIUtilities::paramToArray($legendinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$legendinview)) $uselegend = 0;
		if ($view=='category' && !in_array('category',$legendinview)) $uselegend = 0;
		if ($isItemsManager && !in_array('backend',$legendinview)) $uselegend = 0;
		
		// load the tooltip library if required
		if ($uselegend)
			FLEXI_J30GE ? JHtml::_('bootstrap.tooltip') : JHTML::_('behavior.tooltip');
		
		
		// **************************************
		// Title/Description in inline thumbnails
		// **************************************
		
		$showtitle = $field->parameters->get( 'showtitle', 0 ) ;
		$showdesc  = $field->parameters->get( 'showdesc', 0 ) ;
		
		
		// *************************
		// Link to URL configuration
		// *************************
		
		$linkto_url	= $field->parameters->get('linkto_url',0);
		$url_target = $field->parameters->get('url_target','_self');
		$isLinkToPopup = $linkto_url && ($url_target=='multibox' || $url_target=='fancybox');
		
		// Force opening in new window in backend, if URL target is _self
		if ($isItemsManager && $url_target=='_self') $url_target = "_blank";
		
		// Only allow multibox (and TODO: add fancybox) when linking to URL, in other cases force fancybox
		if ($isLinkToPopup && $url_target=='multibox') $popuptype = 1;
		if ($isLinkToPopup && $url_target=='fancybox') $popuptype = 4;
		else if ($linkto_url) $usepopup = 0;
		
		
		// ************************************
		// Social website sharing configuration
		// ************************************
		
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpthumbsize= $field->parameters->get('ogpthumbsize', 2);
		
		
		// **********************************************
		// Load the configured (or the forced) JS gallery
		// **********************************************
		
		// Do not load JS, for value only displays
		if ( !isset(self::$value_only_displays[$prop]) )
		{
			// MultiBox maybe added in extra cases besides popup
			// (a) in Item manager, (b) When linking to URL in popup target
			$view_allows_mb  = $isItemsManager || ($isSite && !$isFeedView);
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
					})";
					$document->addScriptDeclaration($js);
					
					$multiboxadded = true;
				}
			}
			
			// Regardless if above has added multibox , we will add a different JS gallery if so configured because it maybe needed
			if ( !$isSite || $isFeedView ) {
				// Is backend OR it is a feed view, do not add any JS library
			}
			
			else if ( $usepopup ) {
				
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
					
					if (!$gallerifficadded) {
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
					if (!$elastislideadded) {
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
									<a href="#" class="rg-image-nav-prev">'.JText::_('FLEXI_PREVIOUS').'</a>
									<a href="#" class="rg-image-nav-next">'.JText::_('FLEXI_NEXT').'</a>
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
					if (!$photoswipeadded) {
						flexicontent_html::loadFramework('photoswipe');
						$photoswipeadded = true;
					}
					break;
				}
			}
		}
		
		
		// *******************************
		// Prepare for value handling loop
		// *******************************
		
		// Extra thumbnails sub-folder for various
		if ( $field->using_default_value ) {
			$extra_folder = '';  // default value
		}
		else if ( $image_source == -1 ) {
			$extra_folder = 'intro_full';  // intro-full images mode
		}
		else if ( $image_source > 0 ) {
			$extra_folder = 'item_'.$u_item_id.'_field_'.$field->id;  // folder-mode 1
			if ( $image_source > 1 ) ; // TODO
		}
		else {
			$extra_folder = '';  // db-mode
		}
		
		// Create thumbs/image Folder and URL paths
		$thumb_folder  = JPATH_SITE .DS. JPath::clean( $dir .($extra_folder ? DS.$extra_folder : '') );
		$thumb_urlpath = $dir_url .($extra_folder ? '/'. $extra_folder : '');
		if ( $field->using_default_value ) {
			// default image of this field, these are relative paths up to site root
			$orig_urlpath  = str_replace('\\','/', dirname($default_image_val['default_image']));
		} else if ($image_source == -1) {
			// intro-full image values, these are relative paths up to the site root, must be calculated later !!
			$orig_urlpath  = str_replace('\\','/', dirname($image_by_params['image_path']));
		} else if ( $image_source > 0) {
			// various folder-mode(s)
			$orig_urlpath  = $thumb_urlpath . '/original';
		} else {
			// db-mode
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			$orig_urlpath  = str_replace('\\','/', JPath::clean($cparams->get('file_path', 'components/com_flexicontent/uploads')) );
		}
		
		// Initialize value handling arrays and loop's common variables
		$i = -1;
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
		
		
		// ***************
		// The values loop
		// ***************
		
		foreach ($values as $val)
		{
			// Unserialize value's properties and check for empty original name property
			$value	= unserialize($val);
			$image_name = trim(@$value['originalname']);
			if ( !strlen($image_name) ) {
				if ($is_ingroup) $field->{$prop}[] = '';  // add empty position to the display array
				continue;
			}
			$i++;
			
			// Create thumbnails urls, note thumbnails have already been verified above
			$wl = $field->parameters->get( 'w_l', 800 );
			$hl = $field->parameters->get( 'h_l', 600 );
			
			// Optional properties
			$title	= ($usetitle && @$value['title']) ? $value['title'] : '';
			$alt	= ($usealt && @$value['alt']) ? $value['alt'] : flexicontent_html::striptagsandcut($item->title, 60);
			$desc	= ($usedesc && @$value['desc']) ? $value['desc'] : '';
			
			// Optional custom properties
			$cust1	= ($usecust1 && @$value['cust1']) ? $value['cust1'] : '';
			$desc .= $cust1 ? $cust1_label.': '.$cust1 : '';  // ... Append custom properties to description
			$cust2	= ($usecust2 && @$value['cust2']) ? $value['cust2'] : '';
			$desc .= $cust2 ? $cust2_label.': '.$cust2 : '';  // ... Append custom properties to description
			
			// HTML encode output
			$title= htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
			$alt	= htmlspecialchars($alt, ENT_COMPAT, 'UTF-8');
			$desc	= htmlspecialchars($desc, ENT_COMPAT, 'UTF-8');
			
			$srcb = $thumb_urlpath . '/b_' .$extra_prefix. $image_name;  // backend
			$srcs = $thumb_urlpath . '/s_' .$extra_prefix. $image_name;  // small
			$srcm = $thumb_urlpath . '/m_' .$extra_prefix. $image_name;  // medium
			$srcl = $thumb_urlpath . '/l_' .$extra_prefix. $image_name;  // large
			$srco = $orig_urlpath  . '/'   .$image_name;  // original image
			
			// Create a popup url link
			$urllink = @$value['urllink'] ? $value['urllink'] : '';
			if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
			
			// Create a popup tooltip (legend)
			$class = 'fc_field_image';
			if ($uselegend && (!empty($title) || !empty($desc) ) ) {
				$class .= $tip_class;
				$legend = ' title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'"';
			} else {
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
				case 0: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id.'_fcfield_'.$field->id; break;
				case 1: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id; break;
				case 2: $group_name = 'fcview_'.$view; break;
				default: $group_name = ''; break;
			}
			
			
			// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only
			if ($is_ingroup) {
				// In case of field displayed via in fieldgroup, this is an array
				$field->{"display_backend_src"}[] = JURI::root(true).'/'.$srcb;
				$field->{"display_small_src"}[] = JURI::root(true).'/'.$srcs;
				$field->{"display_medium_src"}[] = JURI::root(true).'/'.$srcm;
				$field->{"display_large_src"}[] = JURI::root(true).'/'.$srcl;
				$field->{"display_original_src"}[] = JURI::root(true).'/'.$srco;
			}
			else if ($i==0) {
				// Field displayed not via fieldgroup return only the 1st value
				$field->{"display_backend_src"} = JURI::root(true).'/'.$srcb;
				$field->{"display_small_src"} = JURI::root(true).'/'.$srcs;
				$field->{"display_medium_src"} = JURI::root(true).'/'.$srcm;
				$field->{"display_large_src"} = JURI::root(true).'/'.$srcl;
				$field->{"display_original_src"} = JURI::root(true).'/'.$srco;
			}
			$field->thumbs_src['backend'][] = JURI::root(true).'/'.$srcb;
			$field->thumbs_src['small'][] = JURI::root(true).'/'.$srcs;
			$field->thumbs_src['medium'][] = JURI::root(true).'/'.$srcm;
			$field->thumbs_src['large'][] = JURI::root(true).'/'.$srcl;
			$field->thumbs_src['original'][] = JURI::root(true).'/'.$srco;
			
			$field->thumbs_path['backend'][] = JPATH_SITE.DS.$srcb;
			$field->thumbs_path['small'][] = JPATH_SITE.DS.$srcs;
			$field->thumbs_path['medium'][] = JPATH_SITE.DS.$srcm;
			$field->thumbs_path['large'][] = JPATH_SITE.DS.$srcl;
			$field->thumbs_path['original'][] = JPATH_SITE.DS.$srco;
			
			// Suggest image for external use, e.g. for Facebook etc, (making sure that URL is ABSOLUTE URL)
			if ( ($isSite && !$isFeedView) && $useogp ) {
				if ( in_array($view, $ogpinview) ) {
					switch ($ogpthumbsize)
					{
						case 1: $ogp_src = JURI::root().$srcs; break;   // this maybe problematic, since it maybe too small or not accepted by social website
						case 2: $ogp_src = JURI::root().$srcm; break;
						case 3: $ogp_src = JURI::root().$srcl; break;
						case 4: $ogp_src =  JURI::root().$srco; break;
						default: $ogp_src = JURI::root().$srcm; break;
					}
					$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
					$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
				}
			}
			
			
			// ****************************************************************
			// CHECK if we were asked for value only display (e.g. image source)
			// if so we will not be creating the HTML code for Image / Gallery 
			// ****************************************************************
			
			if ( isset(self::$value_only_displays[$prop]) )
			{
				continue;
			}
			
			
			// *********************************************************
			// Create image tags (according to configuration parameters)
			// that will be used for the requested 'display' variable
			// *********************************************************
			
			switch ($prop)
			{
				case 'display_backend':
					$img_legend   = '<img src="'.JURI::root(true).'/'.$srcb.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$srcb.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
				case 'display_small':
					$img_legend   = '<img src="'.JURI::root(true).'/'.$srcs.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$srcs.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
				case 'display_medium':
					$img_legend   = '<img src="'.JURI::root(true).'/'.$srcm.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$srcm.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
				case 'display_large':
					$img_legend   = '<img src="'.JURI::root(true).'/'.$srcl.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$srcl.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
				case 'display_original':
					$img_legend   = '<img src="'.JURI::root(true).'/'.$srco.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$srco.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
				case 'display': default:
					$img_legend   = '<img src="'.JURI::root(true).'/'.$src.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
					$img_nolegend = '<img src="'.JURI::root(true).'/'.$src.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
					break;
			}
			
			
			// *************************************************
			// Create thumbnail appending text (not linked text)
			// *************************************************
			
			// For galleries that are not inline/special, we can have inline text ??
			$inline_info = '';
			if ( $linkto_url || ($prop=='display_large' || $prop=='display_original') || !$usepopup || ($popuptype != 5 && $popuptype != 7) )
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
			
			
			// *********************************************
			// FINALLY CREATE the field display variable ...
			// *********************************************
			
			if ( $linkToItem ) {
				
				// CASE 0: Add single image display information (e.g. image count)
				
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
				
			} else if ($linkto_url) {
				
				// CASE 1: Handle linking to a URL instead of image zooming popup
				
				if (!$urllink) {
					// CASE: Just image thumbnail since url link is empty
					$field->{$prop}[] = $pretext.'<div class="fc_img_container">'.$img_legend.$inline_info.'</div>'.$posttext;
				}
				
				else if ($url_target=='multibox') {
					// CASE: Link to URL that opens inside a popup via multibox
					$field->{$prop}[] = $pretext.'
					<script>document.write(\'<a style="'.$style.'" href="'.$urllink.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+(jQuery(window).width()-150)+\',height:\'+(jQuery(window).height()-150)+\'">\')</script>
						'.$img_legend.'
					<script>document.write(\'</a>\')</script>
					<div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					'.$inline_info.$posttext;
				}
				
				else if ($url_target=='fancybox') {
					// CASE: Link to URL that opens inside a popup via fancybox
					$field->{$prop}[] = $pretext.'
					<span class="fc_image_thumb" style="'.$style.'; cursor: pointer;" '.
						'onclick="jQuery.fancybox.open([{ type: \'iframe\', href: \''.$urllink.'\', topRatio: 0.9, leftRatio: 0.9, title: \''.($desc ? $title.': '.$desc : $title).'\' }], { padding : 0});"
					>
						'.$img_legend.'
					</span>
					'.$inline_info.$posttext;
				}
				
				else {
					// CASE: Just link to URL without popup
					$field->{$prop}[] = $pretext.'
					<a href="'.$urllink.'" target="'.$url_target.'">
						'.$img_legend.'
					</a>
					'.$inline_info.$posttext;
				}
				
			} else if (
				!$usepopup || // Plain Thumbnail List without any (popup / inline) gallery code
				($prop=='display_large' || $prop=='display_original') // No popup if image is the largest OR original thumbs
			) {
				
				// CASE 2: // No gallery code ... just apply pretext/posttext
				$field->{$prop}[] = $pretext.$img_legend.$inline_info.$posttext;
				
			} else if ($popuptype == 5 || $popuptype == 7) {
			
				// CASE 3: Inline/special galleries OR --> GALLERIES THAT NEED SPECIAL ENCLOSERS
				// !!! ... pretext/posttext/inline_info/etc not meaningful or not supported or not needed
				
				switch ($popuptype)
				{
				case 5:   // Galleriffic inline slideshow gallery
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
				case 7:   // Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
					// *** NEEDS: thumbnail list must be created with large size thubmnails, these will be then thumbnailed by the JS gallery code
					$title_attr = $desc ? $desc : $title;
					$img_legend_custom ='
						 <img src="'.JURI::root(true).'/'.$src.'" alt ="'.$alt.'"'.$legend.' class="'.$class.'"
						 	data-large="' . JURI::root(true).'/'.$srcl . '" data-description="'.$title_attr.'" itemprop="image"/>
					';
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<li><a href="javascript:;" class="fc_image_thumb">
							'.$img_legend_custom.'
						</a></li>';
					break;
				default: // ERROR unhandled INLINE GALLERY case
					$field->{$prop}[] = ' Unhandled INLINE GALLERY case in image with field name: {$field->name} '; break;
				}
			
			} else {  
				
				// CASE 4: Popup galleries
				
				switch ($popuptype)
				{
				case 1:   // Multibox image popup
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" id="mb'.$uniqueid.'" class="fc_image_thumb mb" '.$group_str.' >
							'.$img_legend.'
						</a>
						<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? '<span class="badge">'.$title.'</span> '.$desc : $title).'</div>'
						.$inline_info.$posttext;
					break;
				case 2:   // Rokbox image popup
					$title_attr = $desc ? $desc : $title;
					$group_str = '';   // no support for image grouping
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" rel="rokbox['.$wl.' '.$hl.']" '.$group_str.' title="'.$title_attr.'" class="fc_image_thumb" data-rokbox data-rokbox-caption="'.$title_attr.'">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;
				case 3:   // JCE popup image popup
					$title_attr = $desc ? $title.': '.$desc : $title;  // does not support HTML
					$group_str = $group_name ? 'rel="group['.$group_name.']"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'"  class="fc_image_thumb jcepopup" '.$group_str.' title="'.$title_attr.'">
							'.$img_nolegend.'
						</a>'
						.$inline_info.$posttext;
					break;
				case 4:   // Fancybox image popup
					$title_attr = $desc ? '<span class=\'badge\'>'.$title.'</span> '.$desc : $title;
					$group_str = $group_name ? 'data-fancybox-group="'.$group_name.'"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'"  class="fc_image_thumb fancybox" '.$group_str.' title="'.$title_attr.'">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;
				case 6:   // (Widgetkit) SPOTlight image popup
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
				case 8:   // PhotoSwipe popup carousel gallery
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = $pretext.
						'<a style="'.$style.'" href="'.$srcl.'" '.$group_str.' class="fc_image_thumb">
							'.$img_legend.'
						</a>'
						.$inline_info.$posttext;
					break;
				default:  // Unknown / Other Gallery Type, just add thumbails ... maybe pretext/posttext/separator/opentag/closetag will add a gallery
					$field->{$prop}[] = $pretext.$img_legend.$inline_info.$posttext;
					break;
				}
			}
		}
		
		
		// **************************************************************
		// Apply separator and open/close tags and handle SPECIAL CASEs:
		// by adding (container) HTML required by some JS image libraries
		// **************************************************************
		
		// Using in field group, return array
		if ( $is_ingroup ) {
			return;
		}
		
		// Check for value only displays and return
		if ( isset(self::$value_only_displays[$prop]) )
		{
			return;
		}
		
		// Check for no values found
		if ( !count($field->{$prop}) ) {
			$field->{$prop} = '';
			return;
		}
		
		// Galleriffic inline slideshow gallery
		if ($usepopup && $popuptype == 5) {
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
		else if ($usepopup && $popuptype == 7) {
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
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		
		// Check if field has posted data
		if ( empty($post) && !$use_ingroup) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;   //echo "<pre>"; print_r($post);
		
		// Get configuration
		$is_importcsv      = JRequest::getVar('task') == 'importcsv';
		$import_media_folder  = JRequest::getVar('import_media_folder');
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		$unique_tmp_itemid = JRequest::getVar( 'unique_tmp_itemid', '' );
		
		// Set a warning message for overriden/changed files: form.php (frontend) or default.php (backend)
		if ( !$is_importcsv && empty($unique_tmp_itemid) ) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( 'WARNING, field: '.$field->label.' requires variable -unique_tmp_itemid- please update your '.($app->isSite() ? 'form.php':'default.php'), 'warning');
		}
		
		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( !$initialized ) {
			$initialized = 1;
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.jpath');
			if ( $is_importcsv ) {
				$srcpath_original = JPath::clean( JPATH_SITE .DS. $import_media_folder .DS );
				require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');
			}
		}

		
		// ***********************************************
		// Special steps for image field in folder-mode(s)
		// ***********************************************
		if ( $image_source > 0 )
		{
			$dir = $field->parameters->get('dir');
			$unique_tmp_itemid = JRequest::getVar( 'unique_tmp_itemid', '' );
			
			$destpath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id . '_field_'.$field->id .DS );
			if ( $image_source > 1 ) ; // TODO
			
			// Create original images folder if doing CSV import and folder does not exist
			if ( $is_importcsv ) {
				$destpath_original = $destpath. 'original' .DS;
				if ( !JFolder::exists($destpath_original) && !JFolder::create($destpath_original) ) {
					JError::raiseWarning(100, $field->label .': Error. Unable to create folder: '. $destpath_original );
					return false;  // Cancel item creation
				}
			}
			
			// New items have no item id during submission, thus we need to rename the temporary name of images upload folder
			else if ( $unique_tmp_itemid && $item->id != $unique_tmp_itemid ) {
				$temppath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$unique_tmp_itemid. '_field_'.$field->id .DS );
				JFolder::move($temppath, $destpath);
			}
		}
		
		
		// **************************************************************************
		// Rearrange file array so that file properties are groupped per image number
		// **************************************************************************
		//echo "<pre>"; print_r($file); echo "</pre>";
		$files = array();
		if ($file) foreach( $file as $key => $all ) {
			foreach( $all as $i => $val ) {
				$files[$i][$key] = $val;
			}
		}
		//echo "<pre>"; print_r($files); echo "</pre>";
		
		
		// *****************************************************************************************
		// Reformat the posted data & handle uploading / removing / deleting / replacing image files
		// *****************************************************************************************
		$newpost = array();
		$new = 0;
    foreach ($post as $n => $v)
    {
    	if (empty($v)) {
    		if ($use_ingroup) {  // empty value for group
					$newpost[$new] = array('originalname' => '');
					$new++;
				}
    		continue;
    	}
    	
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($v) ) {
				if ( @unserialize($v)!== false || $v === 'b:0;' ) {  // support for exported serialized data)
					$v = unserialize($v);
				} else {
					$v = array('originalname' => $v);
				}
			}
			
			// Add system message if upload error
			$err_code = isset($files[$n]['error']) ? $files[$n]['error'] : false;
			if ( $err_code && $err_code!=UPLOAD_ERR_NO_FILE)
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
			else if ($is_importcsv && $import_media_folder ) {
				$filename = basename($v['originalname']);
				$sub_folder = dirname($v['originalname']);
				$sub_folder = $sub_folder && $sub_folder!='.' ? DS.$sub_folder : '';
				
				if ($image_source) {
					$srcfilepath  = JPath::clean( $srcpath_original . $v['originalname'] );
					$destfilepath = JPath::clean( $destpath_original . $filename );
					if ( JFile::exists($srcfilepath) ) {
						$result = JFile::copy( $srcfilepath,  $destfilepath );
						if ( $result && JPath::canChmod($destfilepath) )  chmod($destfilepath, 0644);
					}
					$v['originalname'] = $filename; // make sure filename is without subfolder
				} else {
					$fman = new FlexicontentControllerFilemanager();
					JRequest::setVar( 'return-url', null, 'post' );
					JRequest::setVar( 'file-dir-path', DS.$import_media_folder . $sub_folder, 'post' );
					JRequest::setVar( 'file-filter-re', preg_quote($filename), 'post' );
					JRequest::setVar( 'secure', 1, 'post' );
					JRequest::setVar( 'keep', 1, 'post' );
					$file_ids = $fman->addlocal();
					reset($file_ids);  // Reset array to point to first element
					$v['originalname'] = key($file_ids);  // The (first) key of file_ids array is the cleaned up filename
				}
			}
			
			
			// Defaut values for unset required properties of values
			$v['originalname'] = isset($v['originalname']) ? $v['originalname'] : '';
			$v['existingname'] = isset($v['existingname']) ? $v['existingname'] : '';
			$v['delete'] = isset($v['delete']) ? $v['delete'] : false;
			$v['remove'] = isset($v['remove']) ? $v['remove'] : false;
			
			if ( $v['originalname'] || $v['existingname'] ) {
				//echo $v['originalname'] ." ". $v['existingname'] ."<br>";
				
				// (b) Handle removing image assignment OR deleting the image file
				if ($v['originalname']) {
					if ( $v['delete']==1 || ($new_file_uploaded && !$field->parameters->get('existing_imgs', 1)) ) {
						// Try to clean unused values (this is forced to YES if existing image list is disabled)
						$filename = $v['delete']==1 ? $v['originalname'] : $v['delete'];   // we may submit filename for deletion via 'delete' form field
						$canDeleteImage = $this->canDeleteImage( $field, $filename, $item );  // security concern check if value is in use
						if ($canDeleteImage) {
							$this->removeOriginalFile( $field, $filename );
							//JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . 'Deleted image file: '.$filename.' from server storage');
						} else {
							if ($v['delete'] == 1) JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . 'Cannot delete image file: '.$filename.' from server storage, it is in use');
						}
					} elseif ( $v['remove'] && $v['existingname'] ) {
						JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . 'Removed image assignment of file: '.$filename.' from the field');
					}
				}
				
				// Need to clear 'delete' if not, to allow saving new value, we use 'delete' to post the auto-saved clear text
				$v['delete'] = $v['delete'] == 1 ? $v['delete'] : false;
				
				// (c) Handle replacing image with a new existing image
				if ( $v['existingname'] ) {
					$v['originalname'] = $v['existingname'];
					$v['existingname'] = '';
				} else if ( $v['delete'] || $v['remove'] ) {
					$v = '';
				}
				
			} else {
				// No original file posted discard current image row
				$v = '';
			}
			
			// Add image entry to a new array skipping empty image entries
			if ($v) {
				$newpost[$new] = $v;
				$new++;
			}
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( empty($post) ) return;
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		if ( !$is_importcsv ) return;
		
		$values = array();
		foreach($post as $i => $d) {
			$values[$i] = ( @unserialize($d)!== false || $d === 'b:0;' ) ? unserialize($d) : $d;
			plgFlexicontent_fieldsImage::rebuildThumbs( $field, $values[$i], $item );
		}
		//echo "<b>{$field->field_type}</b>: <br/> <pre>".print_r($values, true)."</pre>\n";
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		$dir = $field->parameters->get('dir');
		
		if ( $image_source > 0 ) {
			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.jpath');
			
			// Delete image folder if it exists
			$destpath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$item->id   . '_field_'.$field->id .DS);
			if ( $image_source > 1 ) ; // TODO
			
			if ( JFolder::exists($destpath) && !JFolder::delete($destpath) ) {
				JError::raiseNotice(100, $field->label .': Notice: Unable to delete folder: '. $destpath );
				return false;
			}
		}
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a advanced filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// **************************************************************************************************
	// Method to handle the uploading of an image file (for 'DB-reusable' mode and not for 'folder' mode)
	// **************************************************************************************************
	function uploadOriginalFile($field, &$post, $file)
	{
		$app    = JFactory::getApplication();
		$format = JRequest::getVar( 'format', 'html', '', 'cmd');
		$err    = null;
		
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
		$image_source = $field->parameters->get('image_source', 0);  // This should be always ZERO inside this function
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		if ( isset($file['name']) && $file['name'] != '' )
		{
			// only handle the secure folder
			$path = COM_FLEXICONTENT_FILEPATH.DS;

			//sanitize filename further and make unique
			$filename = flexicontent_upload::sanitize($path, $file['name']);
			$filepath = JPath::clean(COM_FLEXICONTENT_FILEPATH.DS.strtolower($filename));
			
			//perform security check according
			if (!flexicontent_upload::check( $file, $err, $params )) {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Invalid: '.$filepath.': '.$err));
					header('HTTP/1.0 415 Unsupported Media Type');
					die('Error. Unsupported Media Type!');
				} else {
					JError::raiseNotice(100, $field->label . ' : ' . JText::_($err));
					return false;
				}
			}
			
			//get the extension to record it in the DB
			$ext		= strtolower(JFile::getExt($filename));

			if (!JFile::upload($file['tmp_name'], $filepath)) {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
					header('HTTP/1.0 409 Conflict');
					jexit('Error. File already exists');
				} else {
					JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to upload file'));
					return false;
				}
			} else {
				$db     = JFactory::getDBO();
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
				$obj->ext				= $ext;
				$obj->hits			= 0;
				$obj->uploaded			= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
				$obj->uploaded_by		= $user->get('id');
				
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
					
					$db->insertObject('#__flexicontent_files', $obj);
					jexit('Upload complete');
				}
				else {
					$db->insertObject('#__flexicontent_files', $obj);
					$app->enqueueMessage($field->label . ' : ' . JText::_('Upload complete'));
					
					$sizes = array('l','m','s','b');
					foreach ($sizes as $size) {
						// create the thumbnail
						$this->create_thumb( $field, $filename, $size, $origpath='', $destpath='', $copy_original=0, $extra_prefix );
						
						// set the filename for posting
						$post['originalname'] = $filename;
					}
					return true;
				}
			}
		} else {
			$err = 'File upload failed';
			JError::raiseNotice(100, $field->label . ' : ' . JText::_($err));
			return false;
		}
	}
	
	
	// ***********************************************************************************************
	// Decide parameters for calling phpThumb library to create a thumbnail according to configuration
	// ***********************************************************************************************
	function create_thumb( &$field, $filename, $size, $origpath='', $destpath='', $copy_original=0, $extra_prefix='' ) {
		static $destpaths_arr = array();
		
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.jpath');
		
		// (DB/Folder) Mode of image field
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		
		// Image file paths
		$dir = $field->parameters->get('dir');
		$origpath = $origpath ? $origpath : JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
		$destpath = $destpath ? $destpath : JPath::clean( JPATH_SITE .DS. $dir .DS );
		$prefix		= $size . '_' . $extra_prefix;
		$filepath = $destpath.$prefix.$filename;
		
		// Parameters for phpthumb
		$ext = strtolower(JFile::getExt($filename));
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
		if ( !JFolder::exists($destpath) && !JFolder::create($destpath) ) { 
			JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to create folders'));
			return false;
		}
		
		// Make sure folder is writtable by phpthumb
		if ( !isset($destpaths_arr[$destpath]) && JPath::canChmod($destpath) ) {
			//JPath::setPermissions($destpath, '0644', '0755');  // *** VERY SLOW does chmod on all folder / subfolder files
			chmod($destpath, 0755);
		}
		$destpaths_arr[$destpath] = 1;  // Avoid trying to set folder permission multiple times
		
		// EITHER copy original image file as current thumbnail (FLAG 'copy_original' is set)
		if ($copy_original) {
			$result = JFile::copy( $origpath.$filename,  $filepath );
		}
		
		// OR Create the thumnail by calling phpthumb
		else {
			$result = $this->imagePhpThumb( $origpath, $destpath, $prefix, $filename, $ext, $w, $h, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos );
		}
		
		// Make sure the created thumbnail has correct permissions
		if ( $result && JPath::canChmod($filepath) )  chmod($filepath, 0644);
		
		return $result;
	}
	
	
	// **********************************************************************
	// Call phpThumb library to create a thumbnail according to configuration
	// **********************************************************************
	function imagePhpThumb( $origpath, $destpath, $prefix, $filename, $ext, $width, $height, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos )
	{
		$lib = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpthumb.class.php';		
		require_once ( $lib );
		
		unset ($phpThumb);
		$phpThumb = new phpThumb();
		
		$filepath = $origpath . $filename;
		
		$phpThumb->setSourceFilename($filepath);
		$phpThumb->setParameter('config_output_format', "$ext");
		//if ( $ext=='gif' )  // Force maximum color for GIF images?
		//	$phpThumb->setParameter('fltr', 'rcd|256|1');
		$phpThumb->setParameter('w', $width);
		$phpThumb->setParameter('h', $height);
		if ($usewm == 1)
		{
			$phpThumb->setParameter('fltr', 'wmi|'.$wmfile.'|'.$wmpos.'|'.$wmop);
		}
		$phpThumb->setParameter('q', $quality);
		if ($crop == 1)
		{
			$phpThumb->setParameter('zc', 1);
		}
		
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if ( in_array( $ext, array('png', 'ico', 'gif') ) )
		{
			$phpThumb->setParameter('f', $ext);
		}
		
		$output_filename = $destpath . $prefix . $filename ;
		
		if ($phpThumb->GenerateThumbnail()) {
			if ($phpThumb->RenderToFile($output_filename)) {
				return true;
			} else {
				echo 'Failed:<pre>' . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
				return false;
			}
		} else {
			echo 'Failed2:<pre>' . $phpThumb->fatalerror . "\n\n" . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
			return false;
		}
	}
	
	
	// ************************************************
	// Removes an orignal image file and its thumbnails
	// ************************************************
	function removeOriginalFile( $field, $filename )
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.jpath');
		
		$db = JFactory::getDBO();
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}

		if ( $image_source > 0 ) {  // folder-modes
			$thumbfolder = JPath::clean(JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id);
			if ( $image_source > 1 ) ; // TODO
			
			$origfolder  = $thumbfolder .DS. 'original' .DS;
		}
		else if ( $image_source == 0 ) { // db-bode
			$thumbfolder = JPath::clean( JPATH_SITE .DS. $field->parameters->get('dir') );
			$origfolder  = JPath::clean( COM_FLEXICONTENT_FILEPATH );
		}
		else { // negative, intro-full mode, this should be unreachable, because
			echo "image field id: ".$field->id." is in intro-full mode, removeOriginalFile() should not have been called";
		}
		
		// a. Delete the thumbnails
		$errors		= array();
		$sizes 		= array('l','m','s','b');
		foreach ($sizes as $size)
		{
			$destpath = $thumbfolder . DS . $size . '_' . $filename;
			if ( JFile::exists($destpath) && !JFile::delete($destpath) )
			{ 
				// Handle failed delete, currently this is not outputed, since thumbnails may not have been created, or may have been deleted manually ??
				JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $destpath);
			}
		}
		
		// b. Delete the original image from file manager
		$origpath = JPath::clean($origfolder.DS.$filename);
		if (!JFile::delete($origpath)) {
			JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $origpath);
		}

		if ( $image_source ) {
			// Done nothing more to clean
		} else {
			$query = 'DELETE FROM #__flexicontent_files'
				. ' WHERE ' . (FLEXI_J16GE ? $db->quoteName('filename') : $db->nameQuote('filename') ) . ' = ' . $db->Quote($filename);
			$db->setQuery( $query );
			if(!$db->execute())
			{
				$this->setError($db->getErrorMsg());
				return false;
			}
		}
		
		return true;
	}
	
	
	// ***********************************************
	// Smart image thumbnail size check and rebuilding
	// ***********************************************
	function rebuildThumbs( &$field, &$value, &$item )
	{
		static $images_processed = array();
		
		$filename = trim( @$value['originalname'] );
		if ( !$filename ) return;  // check for empty filename
		
		$image_source = $field->parameters->get('image_source', 0);
		if ($image_source > 1) {
			global $fc_folder_mode_err;
			if (empty($fc_folder_mode_err[$field->id])) {
				echo __FUNCTION__."(): folder-mode: ".$image_source." not implemented please change image-source mode in image/gallery field with id: ".$field->id;
				$fc_folder_mode_err[$field->id] = 1;
				$image_source = 1;
			}
		}
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir = $field->parameters->get('dir');
		
		// Check if using folder of original content being translated
		$of_usage = $field->untranslatable ? 1 : $field->parameters->get('of_usage', 0);
		$u_item_id = ($of_usage && $item->lang_parent_id && $item->lang_parent_id != $item->id)  ?  $item->lang_parent_id  :  $item->id;
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		$multiple_image_usages = $multiple_image_usages || @ $value['default_image'];
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		
		// Extra thumbnails sub-folder for various
		if ( !empty($value['default_image']) ) {
			$extra_folder = '';  // default value
		}
		else if ( $image_source == -1 ) {
			$extra_folder = 'intro_full';  // intro-full images mode
		}
		else if ( $image_source > 0 ) {
			$extra_folder = 'item_'.$u_item_id . '_field_'.$field->id;  // folder-mode 1
			if ( $image_source > 1 ) ; // TODO
		}
		else {
			$extra_folder = '';  // db-mode
		}
		
		
		// ******************************
		// Find out path to original file
		// ******************************
		if ( !empty($value['default_image']) ) {
			// default image of this field, these are relative paths up to site root
			$origpath  = JPath::clean( JPATH_SITE .DS. dirname($value['default_image']) .DS );
		} else if ($image_source == -1) {
			// intro-full image values, these are relative paths up to the site root
			$origpath  = JPath::clean( JPATH_SITE .DS. dirname($value['image_path']) .DS );
		} else if ( $image_source > 0) {
			// various folder-mode(s)
			$origpath  = JPath::clean( JPATH_SITE .DS. $dir .DS. $extra_folder .DS. 'original' .DS );
		} else {
			// db-mode
			$origpath  = JPath::clean( COM_FLEXICONTENT_FILEPATH .DS );
		}
		$filepath  = JPath::clean( $origpath . $filename );
		$destpath = JPath::clean( JPATH_SITE .DS. $dir .DS .($extra_folder ? $extra_folder.DS : '') );
		
		
		// *************************************************************************************************
		// ** PERFORMANCE CONSIDERATION : Try to avoid rechecking/recreating image thumbnails multiple times
		// *************************************************************************************************
		if ( $image_source > 0 ) {  // folder-modes
			$pindex = 'item_'.$u_item_id . '_field_'.$field->id;
			if ( $image_source > 1 ) ; // TODO
		} else {  // db-mode or intro-full mode
			$pindex = 'field_'.$field->id;
		}
		if (isset($images_processed[$pindex][$filepath])) {
			return $images_processed[$pindex][$filepath];
		} else {
			if ( !file_exists($filepath) || !is_file($filepath) ) {
				//echo "Original file seems to have been deleted or is not a file, cannot find image file: ".$filepath ."<br />\n";
				return ($images_processed[$pindex][$filepath] = false);
			}
		}
		
		
		// **********************************************************
		// Enforce protection of original image files any folder-mode
		// **********************************************************
		jimport('joomla.filesystem.folder');
		if ($image_source > 0 && JFolder::exists($origpath))
		{
			$protect_original = $field->parameters->get('protect_original', 1);
			$htaccess_file = JPath::clean( $origpath . '.htaccess' );
			if ($protect_original) {
				$file_contents =
					'# do not allow direct access and also deny scripts'."\n".
					'<FilesMatch ".*">'."\n".
					'  Order Allow,Deny'."\n".
					'  Deny from all'."\n".
					'</FilesMatch>'."\n".
					'OPTIONS -Indexes -ExecCGI'."\n";
			} else {
				$file_contents =
					'# allow direct access but deny script'."\n".
					'<FilesMatch ".*">'."\n".
					'  Order Allow,Deny'."\n".
					'  Allow from all'."\n".
					'</FilesMatch>'."\n".
					'OPTIONS -Indexes -ExecCGI'."\n";
			}
			// write .htaccess file
			$fh = @ fopen($htaccess_file, 'w');
			if (!$fh) {
				JFactory::getApplication()->enqueueMessage( 'Cannot create/write file:'.$htaccess_file, 'notice' );
			} else {
				fwrite($fh, $file_contents);
				fclose($fh);
			}
		}
		
		
		// ***********************************************
		// Check dimension of thumbs and rebuild as needed
		// ***********************************************
		$filesize	= getimagesize($filepath);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];
		
		$sizes = array('l','m','s','b');
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		
		if ($extra_prefix) $sizes[] = '_s';  // always create an unprefixed small thumb, it is needed when assigning preview (and by imagepicker JS lib)
		$thumbres = true;
		foreach ($sizes as $size)
		{
			$check_small = $size=='_s';
			$size = $check_small ? 's' : $size;
			$thumbname = $size . '_' . ($check_small ? '' : $extra_prefix) . $filename;
			$path	= JPath::clean( $destpath .DS. $thumbname);
			
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
				$was_thumbed = $this->create_thumb( $field, $filename, $size, $origpath, $destpath, $copy_original, ($check_small ? '' : $extra_prefix) );
				$thumbres = $thumbres && $was_thumbed;
			}
		}
		
		return ($images_processed[$pindex][$filepath] = $thumbres);
	}
	
	
	// ********************************************************************************
	// Builds a seletion of images stored in the DB, according to field's configuration
	// ********************************************************************************
	function buildSelectList( $field )
	{
		$db    = JFactory::getDBO();
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$document = JFactory::getDocument();
		
		// Get configuration parameters
		$required		       = $field->parameters->get( 'required', 0 ) ;
		$required		       = $required ? ' required' : '';
		$autoupload        = $field->parameters->get('autoupload', 1);
		
		$existing_imgs    = $field->parameters->get('existing_imgs', 1);
		$imagepickerlimit = $field->parameters->get('imagepickerlimit', 200);
		
		$all_media         = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$limit_by_uploader = $field->parameters->get('limit_by_uploader', 0);  // USED ONLY WHEN all_media is ENABLED
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\','/', $dir);
		$image_folder = JURI::root(true).'/'.$dir_url;
		
		// Retrieve available (and appropriate) images from the DB
		if ($all_media) {
			$query = 'SELECT filename'
				. ' FROM #__flexicontent_files'
				. ' WHERE secure=1 AND ext IN ("jpg","gif","png","jpeg") '
				.( $limit_by_uploader ? " AND uploaded_by = ". $user->id : "")
				;
		} else {
			$query = 'SELECT value'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		}
		$db->setQuery($query);
		$values = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		
		// Create original filenames array skipping any empty records, 
		// NOTE: if all_media is ON the we already retrieved filenames above
		if ( $all_media ) {
			$filenames = & $values;
		} else {
			$filenames = array();
			foreach ( $values as $value )
			{
				if ( empty($value) ) continue;
				$value = @ unserialize($value);
				
				if ( !isset($value['originalname']) ) continue;
				$filenames[] = $value['originalname'];
			}
		}
		
		// Eliminate duplicate records in the array
		sort($filenames);
		$filenames = array_unique($filenames);
		
		// Eliminate records that have no original files
		$securepath = JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
		$existing_files = array();
		foreach($filenames as $filename) {
			$filepath = $securepath . $filename;
			if (file_exists($filepath)) {
				$existing_files[] = $filename;
			}
		}
		$filenames = $existing_files;
		$images_count = count($filenames);
		
		// Create attributes of the drop down field for selecting existing images
		$onchange  = ' onchange="';
		$onchange .= " qmAssignFile".$field->id."(this.id, '', fc_db_img_path+'/s_'+this.value);";
		$onchange .= ' "';
		$classes = ' existingname no_value_selected ';
		$js = "";
		
	  // Add Image Picker script on the document.ready event
		static $imagepicker_added = false;
		$use_imgpicker = $existing_imgs==1 && $images_count <= $imagepickerlimit;
		if ( $use_imgpicker )
		{
			$classes .= ' image-picker masonry show-labels show-html ';
		  if ( !$imagepicker_added )
		  {
				$imagepicker_added = true;
				flexicontent_html::loadFramework('image-picker');
				$js .= "
					function fcimgfld_toggle_image_picker(obj) {
						var has_imagepicker = jQuery(obj).parent().find('ul.image_picker_selector').length != 0;
						if (has_imagepicker) jQuery(obj).parent().find('ul.image_picker_selector').remove();
						else                 jQuery(obj).parent().find('select.image-picker').imagepicker({ hide_select:false, show_label:true })
					}
					";
			}
		}
		
		
	  // Add Select2 script on the document.ready event
		static $select2_added = false;
		$classes .= ' use_select2_lib ';
	  if ( !$select2_added )
	  {
			$select2_added = true;
			flexicontent_html::loadFramework('select2');
		}
		
		// Add custom Javascript Code
		if ($js) $document->addScriptDeclaration($js);
		$attribs = $onchange." ".' class="'.$classes.'"';
		
		// Populate the select field options
		$options = array(); 
		$options[] = $use_imgpicker ?
			'<option value="">'.JText::_('FLEXI_FIELD_PLEASE_SELECT').'</option>' :
			JHTML::_('select.option', '', JText::_('FLEXI_FIELD_PLEASE_SELECT')) ;
		
		foreach ($filenames as $filename) {
			$options[] = $use_imgpicker ?
				'<option data-img-src="'.$image_folder.'/s_'.$filename.'" value="'.$filename.'">'.$filename.'</option>' :
				JHTML::_('select.option', $filename, $filename) ;
		}
		
		// Finally create the select field and return it
		$formfldname = '__FORMFLDNAME__'; $formfldid = '__FORMFLDID__';
		$list	= $use_imgpicker ?
			'<select '.$attribs.' name="'.$formfldname.'" id="'.$formfldid.'">' . implode("\n", $options) . '</select>' :
			JHTML::_('select.genericlist', $options, $formfldname, $attribs, 'value', 'text', '', $formfldid) ;
		if ($use_imgpicker) {
			$btn_name = JText::_( 'FLEXI_TOGGLE_ALL_THUMBS' )." (". $images_count .")";
			$list	= '<input class="fcfield-button" type="button" value="'.$btn_name.'" onclick="fcimgfld_toggle_image_picker(this);" /> ' .$list;
		}
		
		return $list;
	}
	
	
	// ************************************************
	// Returns an array of images that can be deleted
	// e.g. of a specific field, or a specific uploader
	// ************************************************
	function canDeleteImage( &$field, $record, &$item )
	{
		// Retrieve available (and appropriate) images from the DB
		$db   = JFactory::getDBO();
		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE filename='. $db->Quote($record)
			;
		$db->setQuery($query);
		$file_id = $db->loadResult();
		if (!$file_id)  return true;
		
		$ignored['item_id'] = $item->id;
		
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'filemanager.php');
		$fm = new FlexicontentModelFilemanager();
		return $fm->candelete( array($file_id), $ignored );
	}
	
	
	// ************************************************************
	// Create a string that concatenates various image information
	// (Function is not called anywhere, used only for debugging)
	// ************************************************************
	function listImageUses( $field, $record )
	{
		$db = JFactory::getDBO();
		$query = 'SELECT value, item_id'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();
		
		$itemid_list = ''; $sep = '';
		for($n=0, $c=count($values); $n<$c; $n++)
		{
			$val = unserialize($values[$n]->value);
			$val = $val['originalname'];
			if ($val == $record) {
				$itemid_list .= $sep . $values[$n]->item_id.",";
				$sep = ',';
			}
		}
		
		return $itemid_list;
	}
	
	function getUploadLimitsTxt(&$field) {
		$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$hint_image = JHTML::image ( 'components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_NOTES' ), '' );
		
		$upload_maxsize = $field->parameters->get('upload_maxsize');
		$phpUploadLimit = flexicontent_upload::getPHPuploadLimit();
		$server_limit_exceeded = $phpUploadLimit['value'] < $upload_maxsize;
		
		if ($server_limit_exceeded) {
			$warn_image = JHTML::image ( 'components/com_flexicontent/assets/images/warning.png', JText::_( 'FLEXI_NOTES' ), '' );
		}
		
		$conf_limit_class = $server_limit_exceeded ? '' : 'badge-success';
		$conf_limit_style = $server_limit_exceeded ? 'text-decoration: line-through;' : '';
		$conf_lim_image   = $server_limit_exceeded ? $warn_image.$hint_image : $hint_image;
		$sys_limit_class  = $server_limit_exceeded ? 'badge-important' : '';
		
		return '
		<span class="fc-img-field-upload-limits-box">
			<span class="label label-info fc-upload-box-lbl">'.JText::_( $server_limit_exceeded ? 'FLEXI_UPLOAD_LIMITS' : 'FLEXI_UPLOAD_LIMIT' ).'</span>
			<span class="fc-php-upload-limit-box">
				<span class="'.$tip_class.'" style="margin-left:24px;" title="'.flexicontent_html::getToolTip('FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT', 'FLEXI_FIELD_CONF_UPLOAD_MAX_LIMIT_DESC', 1, 1).'">'.$conf_lim_image.'</span>
				<span class="badge '.$conf_limit_class.'" style="'.$conf_limit_style.'">'.round($upload_maxsize / (1024*1024), 2).' M </span>
			</span>
			'.($server_limit_exceeded ? '
			<span class="fc-sys-upload-limit-box">
				<span class="'.$tip_class.'" style="margin-left:24px;" title="'.flexicontent_html::getToolTip(JText::_('FLEXI_SERVER_UPLOAD_MAX_LIMIT'), JText::sprintf('FLEXI_SERVER_UPLOAD_MAX_LIMIT_DESC', $phpUploadLimit['name']), 0, 1).'">'.$hint_image.'</span>
				<span class="badge '.$sys_limit_class.'">'.round($phpUploadLimit['value'] / (1024*1024), 2).' M </span>
			</span>' : '').'
		</span>
		';
	}
}
