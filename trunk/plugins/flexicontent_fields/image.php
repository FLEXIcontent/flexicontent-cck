<?php
/**
 * @version 1.0 $Id$
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
	function onDisplayField(&$field, $item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		
		static $common_js_css_added = false;
		
		// some parameter shortcuts
		$multiple     = $field->parameters->get('allow_multiple', 1) ;
		$maxval       = $field->parameters->get('max_values', 0) ;
		$image_source = $field->parameters->get('image_source', 0) ;
		$imagepicker  = $field->parameters->get('imagepicker', 1) ;
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		// Get a unique id to use as item id if current item is new
		$u_item_id = $item->id ? $item->id : JRequest::getVar( 'unique_tmp_itemid' );
		
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$autoupload = $field->parameters->get('autoupload', 0);
		$autoassign = $field->parameters->get('autoassign', 0);
		$always_allow_removal = $field->parameters->get('always_allow_removal', 0);
		
		$thumb_w_s = $field->parameters->get( 'w_s', 120 );
		$thumb_h_s = $field->parameters->get( 'h_s', 90 );
		
		// optional properies configuration
		$linkto_url = $field->parameters->get('linkto_url',0);
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$title_usage = $field->parameters->get( 'title_usage', 0 ) ;
		$desc_usage  = $field->parameters->get( 'desc_usage', 0 ) ;
		
		$default_alt    = ($item->version == 0 || $alt_usage > 0) ? $field->parameters->get( 'default_alt', '' ) : '';
		$default_title  = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_desc   = ($item->version == 0 || $desc_usage > 0) ? $field->parameters->get( 'default_desc', '' ) : '';
		
		$usealt    = $field->parameters->get( 'use_alt', 1 ) ;
		$usetitle  = $field->parameters->get( 'use_title', 1 ) ;
		$usedesc   = $field->parameters->get( 'use_desc', 1 ) ;
		
		$none_props = !$linkto_url && !$usealt && !$usetitle && !$usedesc;
		
		if ( !$common_js_css_added ) {
			$js = "
				function fx_toggle_upload_select_tbl (obj_changed, obj_disp_toggle) {
					if (jQuery(obj_disp_toggle).length == 0)
						obj_disp_toggle = jQuery(obj_changed).closest('.fcfieldval_container').find('table.img_upload_select');
					else
						obj_disp_toggle = jQuery(obj_disp_toggle);
					if (obj_changed.checked)
						obj_disp_toggle.css('display', 'table');
					else
						obj_disp_toggle.css('display', 'none');
				}
				";
			$document->addScriptDeclaration($js);
			$common_js_css_added = true;
		}
		
		$field->html = '';
		
		// Make sure value is an array of values
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['originalname'] = '';
			$field->value[0] = serialize($field->value[0]);
		}
		
		
		if ($multiple) // handle multiple records
		{
			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});			
				});
			";
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			// WARNING: bellow we also use $field->name which is different than $fieldname
			
			$auto_enable_imgpicker = 0;  // Disabled to help performance
			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					} else {
						var fx = thisNewField.effects(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					}
					
				".( $image_source ? "" :"
					var has_imagepicker = jQuery(thisNewField).find('ul.image_picker_selector').length != 0;
					var has_select2     = jQuery(thisNewField).find('div.select2-container').length != 0;
					if (has_imagepicker) jQuery(thisNewField).find('ul.image_picker_selector').remove();
					if (has_select2)     jQuery(thisNewField).find('div.select2-container').remove();
					").
				"
				
					thisNewField.getElements('input.newfile').setProperty('value','');
					thisNewField.getElements('input.newfile').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+']');
					thisNewField.getElements('input.newfile').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_newfile');
					
					thisNewField.getElements('input.originalname').setProperty('value','');
					thisNewField.getElements('input.originalname').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][originalname]');
					thisNewField.getElements('input.originalname').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_originalname');
					
					thisNewField.getElements('.existingname').setProperty('value','');
					thisNewField.getElements('.existingname').addClass('no_value_selected');
					thisNewField.getElements('.existingname').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][existingname]');
					thisNewField.getElements('.existingname').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_existingname');
					
				".( $image_source ? "" :"
					if (has_imagepicker && ".$auto_enable_imgpicker." ) jQuery(thisNewField).find('select.image-picker').imagepicker({ hide_select:false, show_label:true });
					if (has_select2)  jQuery(thisNewField).find('select.use_select2_lib').select2();
					").
				"
					thisNewField.getElements('a.addfile_".$field->id."').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addfile');
					thisNewField.getElements('a.addfile_".$field->id."').setProperty('href','".JURI::base().'index.php?option=com_flexicontent&view=fileselement&tmpl=component&layout=image&filter_secure=M&folder_mode=1&'.JUtility::getToken().'=1&field='.$field->id.'&u_item_id='.$u_item_id.'&targetid='.$elementid."_'+uniqueRowNum".$field->id."+'_existingname&thumb_w=".$thumb_w_s.'&thumb_h='.$thumb_h_s.'&autoassign='.$autoassign."');
					
					// COPYING an existing value
					if (thisNewField.getElement('img.preview_image')) {
						if (MooTools.version>='1.2.4') {
							var tmpDiv = new Element('div',{html:'<div class=\"empty_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\"></div>'});
							tmpDiv.getFirst().setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
							tmpDiv.getFirst().replaces( thisNewField.getElement('img.preview_image') );
						} else {
							var tmpDiv = new Element('div', {}).setHTML('<div class=\"empty_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\"></div>');
							tmpDiv.getFirst().setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
							tmpDiv.getFirst().injectAfter(thisNewField.getElement('img.preview_image'));
							thisNewField.getElement('img.preview_image').remove();
						}
					
					// COPYING an empty value
					} else if (thisNewField.getElement('div.empty_image')) {
						thisNewField.getElement('div.empty_image').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
					}
					
					var imgchange_toggler = jQuery(thisNewField).find('input.imgchange');
					if (imgchange_toggler.length) {
						imgchange_toggler.prop('name','".$field->name."['+uniqueRowNum".$field->id."+']');
						imgchange_toggler.prop('id','".$elementid."_'+uniqueRowNum".$field->id."+'_change');
						imgchange_toggler.parent().find('label').prop('for','".$elementid."_'+uniqueRowNum".$field->id."+'_change');
						
						thisNewField.getElements('table.img_upload_select').setProperty('id','".$field->name."_upload_select_tbl_'+uniqueRowNum".$field->id.");
						thisNewField.getElements('table.img_upload_select').setStyle('display', 'table');
						jQuery(thisNewField).find('input.imgchange').prop('checked', true);
					}
					";
					
			if ($linkto_url) $js .= "
					thisNewField.getElements('input.imglink').setProperty('value','');
					thisNewField.getElements('input.imglink').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][urllink]');
					";
					
			if ($usealt) $js .= "
					thisNewField.getElements('input.imgalt').setProperty('value','".$default_alt."');
					thisNewField.getElements('input.imgalt').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][alt]');
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.imgtitle').setProperty('value','".$default_title."');
					thisNewField.getElements('input.imgtitle').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
					";
					
			if ($usedesc) $js .= "
					thisNewField.getElements('textarea.imgdesc').setProperty('value','".$default_desc."');
					thisNewField.getElements('textarea.imgdesc').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][desc]');
					";
					
			$js .= "
					thisNewField.injectAfter(thisField);
					".// We need to re-execute setting of modal popup since when this run the current element did not exist
					"
					
					SqueezeBox.initialize({});
					if (MooTools.version>='1.2.4') {
						SqueezeBox.assign($$('a.addfile_".$field->id."'), {
							parse: 'rel'
						});
					} else {
						$$('a.addfile_".$field->id."').each(function(el) {
							el.addEvent('click', function(e) {
								new Event(e).stop();
								SqueezeBox.fromElement(el);
							});
						});
					}
					
					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.fcfield-drag'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					rowCount".$field->id."++;       // incremented / decremented
					uniqueRowNum".$field->id."++;   // incremented only
				}
			}

			function deleteField".$field->id."(el)
			{
				var field	= $(el);
				
				if(rowCount".$field->id." == 1)
				{
					addField".$field->id."(field.getParent().getParent().getParent().getElement('input.fcfield-addvalue'));
				}
				
				var originalfftag = 'input.originalname';
				var existingfftag = '".($image_source ? "input" :"select")."' + '.existingname';
				
				var originalname = jQuery(field).parent().find( originalfftag ).val();
				var existingname = jQuery(field).parent().find( existingfftag ).val();
				
				if ( originalname != '' || existingname != '' ) {
					var valcounter = $('".$field->name."');
					if ( !valcounter.value || valcounter.value=='1' ) valcounter.value = '';
					else valcounter.value = parseInt(valcounter.value) - 1;
					//alert(valcounter.value);
				}
				
				if(rowCount".$field->id." > 0)
				{
					var row		= field.getParent();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
					} else {
						var fx = row.effects({duration: 300, transition: Fx.Transitions.linear});
					}
					
					fx.start({
						'height': 0,
						'opacity': 0
						}).chain(function(){
							(MooTools.version>='1.2.4')  ?  row.destroy()  :  row.remove();
						});
					rowCount".$field->id."--;
				}
			}
			
			";
			
			$css = '
			#sortables_'.$field->id.' {
				float:left!important; margin:0px!important; padding:0px!important;
				list-style:none!important; white-space:normal!important;
			}
			#sortables_'.$field->id.' li {
				'.($none_props ?
					'float:left!important; clear:none!important; white-space:normal!important;' :
					'clear:both!important;').'
				display: block!important;
				list-style: none!important;
				position: relative;  .'/* do not make important */.'
			}
			#sortables_'.$field->id.' li input { cursor:text; }
			#add'.$field->name.' { margin-top:5px; clear:both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align:left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag { display:none; }
			#sortables_'.$field->id.' li .fcimg_preview_box { min-width:'.($thumb_w_s+6).'px; min-height:'.($thumb_h_s+8).'px; }
			/*#sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }*/
			';
			
			$remove_button = '<input class="fcfield-button" style="margin: 0px 0px 4px 8px !important;" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= ($none_props ? '<br/>' : ''). '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		// Common JS/CSS
		$image_folder = JURI::root().$field->parameters->get('dir');
		$js .= "
			var fc_db_img_path='".$image_folder."';
			function qmAssignFile".$field->id."(tagid, file, file_url) {
				var replacestr = (tagid.indexOf('_existingname') > -1) ? '_existingname' : '_newfile';
				var elementid = tagid.replace(replacestr,'');
				
				var originalname = $( elementid + '_originalname' ).getProperty('value');
				var existingname = $( elementid + '_existingname' ).getProperty('value');
				
				var valcounter = $('".$field->name."');
				
				if (file=='') {  // DB-mode
				
					var newfilename  = $( elementid + '_newfile' ).getProperty('value');
					
					if ( replacestr == '_newfile' ) {
					
						if ( $( elementid + '_newfile' ).hasClass('no_value_selected') && newfilename!='' ) {
							var modify = ( originalname=='' && existingname=='' );
							$( elementid + '_newfile' ).removeClass('no_value_selected');
						} else if ( !$( elementid + '_newfile' ).hasClass('no_value_selected') && newfilename=='' ) {
							var modify = -1;
							$( elementid + '_newfile' ).addClass('no_value_selected');
						} else {
							var modify = 0;
						}
						
						$( elementid + '_existingname' ).setProperty('value', '');
						$( elementid + '_existingname' ).addClass('no_value_selected');
					} else {
					
						if ( $( elementid + '_existingname' ).hasClass('no_value_selected') && existingname!='' ) {
							var modify = ( originalname=='' && newfilename=='' );
							$( elementid + '_existingname' ).removeClass('no_value_selected');
						} else if ( !$( elementid + '_existingname' ).hasClass('no_value_selected') && existingname=='' ) {
							var modify = -1;
							$( elementid + '_existingname' ).addClass('no_value_selected');
						} else {
							var modify = 0;
						}
						
						$( elementid + '_newfile' ).setProperty('value', '');
						$( elementid + '_newfile' ).addClass('no_value_selected');
					}
					
					if (modify>0) {
						if ( valcounter.value=='' ) valcounter.value = '1';
						else valcounter.value = parseInt(valcounter.value) + modify;
					} else if (modify<0) {
						if ( valcounter.value=='1' ) valcounter.value = '';
						else valcounter.value = parseInt(valcounter.value) + modify;
					}
					
				} else {  // Folder mode
				
					if ( originalname=='' && existingname=='' ) {
						if ( valcounter.value=='' ) valcounter.value = '1';
						else valcounter.value = parseInt(valcounter.value) + 1;
					}
				}
				
				//alert(valcounter.value);
				
				var existing_obj = $( elementid + '_existingname' );
				var original_obj = $( elementid + '_originalname' );
				
				var prv_obj = $( elementid + '_preview_image' );
				
				// Folder-Mode
				if (file != '')  existing_obj.setProperty('value', file);
				original_obj.setProperty('value', '');
				// DB-Mode
				if (file == '') jQuery( '#' + elementid + '_imgdelete' ).remove();
				
				if (prv_obj) {
					if (file || !$( elementid + '_existingname' ).hasClass('no_value_selected') ) {
						var preview_container = '<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"'+file_url+'\" style=\"border: 1px solid silver; float:left;\" />';
					} else {
						var preview_container = '<div class=\"empty_image\" id=\"'+elementid+'_preview_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\">'
						if ( replacestr == '_newfile' && newfilename!='' ) preview_container = preview_container + '<br/>&nbsp; File selected<br/>&nbsp; for uploading';
						preview_container = preview_container + '</div>';
					}

					if (MooTools.version>='1.2.4') {
						var tmpDiv = new Element('div',{html:preview_container});
						tmpDiv.getFirst().replaces( prv_obj );
					} else {
						var tmpDiv = new Element('div', {}).setHTML(preview_container);
						tmpDiv.getFirst().injectAfter( prv_obj );
						prv_obj.remove();
					}
				}
				(MooTools.version>='1.2.4') ?  window.SqueezeBox.close()  :  window.document.getElementById('sbox-window').close();
			}
		";
		$css .='
			table.fcfield'.$field->id.'.img_upload_select { float:left; clear:left; }
			table.fcfield'.$field->id.'.img_upload_select li { min-height:'.($thumb_h_s+56).'px; }
			table.fcfield'.$field->id.'.img_upload_select ul { height:'.($thumb_h_s+96).'px; }
			table.fcfield'.$field->id.'.img_upload_select ul { width:'.(2*($thumb_w_s+64)).'px; }
		';
		
		$document->addScriptDeclaration($js);
		$document->addStyleDeclaration($css);
		
		if ( $image_source ) {
			JHTML::_('behavior.modal', 'a.addfile_'.$field->id);
		} else {
			$select = $this->buildSelectList( $field );
		}
		
		$class = ' class="'.$required.' "';
		$onchange= ' onchange="';
		//$onchange .= ($required) ? ' fx_img_toggle_required(this,$(\''.$field->name.'originalname\')); ' : '';
		
		$onchange .= " qmAssignFile".$field->id."(this.id, '', '');";
		$js_submit = FLEXI_J16GE ? "Joomla.submitbutton('items.apply')" : "submitbutton('apply')";
		$onchange .= ($autoupload && $app->isAdmin()) ? $js_submit : '';
		$onchange .= ' "';
		
		$i = -1;  // Count DB values (may contain invalid entries)
		$n = 0;   // Count sortable records added (the verified values or a single empty record if no good values)
		$count_vals = 0;  // Count non-empty sortable records added
		$image_added = false;
		$skipped_vals = array();
		foreach ($field->value as $value)
		{
			$value = unserialize($value);
			$i++;
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name.'_'.$n : $field->name.'_'.$n;
			
			$image_name = trim(@$value['originalname']);
			
			// Check and rebuild thumbnails if needed
			$rebuild_res = plgFlexicontent_fieldsImage::rebuildThumbs($field,$value);
			
			// Check if rebuilding thumbnails failed (e.g. file has been deleted)  
			if ( !$rebuild_res ) {
				// For non-empty value set a message when we have examined all values
				if ($image_name) $skipped_vals[] = $image_name;
				
				// Skip current value but add and an empty image container if no other image exists
				if ($image_added || ($i+1) < count($field->value) ) {
					continue;
				} else {
					$image_name = '';
				}
			} else {
				$count_vals++;
			}
			
			if ( $image_source ) {
				$select = "
				<input class='existingname fcfield_textval' id='".$elementid."_existingname' name='".$fieldname."[existingname]' value='".$image_name."' readonly='readonly' style='float:none;' />
				".($none_props ? '<br/>' : '')."
				<div class=\"fcfield-button-add\" style='margin: 0px 0px 4px -4px; display:inline-block;'>
					<a class=\"addfile_".$field->id."\" id='".$elementid."_addfile' title=\"".JText::_( 'FLEXI_SELECT_IMAGE' )."\"
						".//href=\"#\" style=\"margin: 0px;\" onmouseover=\"this.href=imgfld_fileelement_url(this,".$field->id.",'".$u_item_id."',".$thumb_w_s.",".$thumb_h_s.")\"
						"href=\"".JURI::base().'index.php?option=com_flexicontent&view=fileselement&tmpl=component&layout=image&filter_secure=M&folder_mode=1&'.JUtility::getToken().'=1&field='.$field->id.'&u_item_id='.$u_item_id.'&targetid='.$elementid."_existingname&thumb_w=$thumb_w_s&thumb_h=$thumb_h_s&autoassign=".$autoassign."\"
						rel=\"{handler: 'iframe', size: {x: (MooTools.version>='1.2.4' ? window.getSize().x : window.getSize().size.x)-100, y: (MooTools.version>='1.2.4' ? window.getSize().y : window.getSize().size.y)-100}}\">".JText::_( 'FLEXI_SELECT_IMAGE' )."</a>
				</div>
				";
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
						$delete  = '<div id="'.$elementid.'_imgdelete" class="imgdelete">';
						$delete .= ' <input class="imgdelete" type="checkbox" name="'.$fieldname.'[delete]" id="'.$elementid.'_delete" value="1"'.$delete_disabled.' />';
						$delete .= ' <label for="'.$elementid.'_delete">'.JText::_( 'FLEXI_FIELD_DELETE_FILE' ).'</label>';
						$delete .= '</div>';
						$remove_disabled = $always_allow_removal ? '' : $canDeleteImage ? ' disabled="disabled"' : '';
					}
					$remove  = '<div id="'.$elementid.'_imgremove" class="imgremove">';
					$remove .= ' <input class="imgremove" type="checkbox" name="'.$fieldname.'[remove]" id="'.$elementid.'_remove" value="1"'.$remove_disabled.' />';
					$remove .= ' <label style="display:inline;" for="'.$elementid.'_remove">'.JText::_( 'FLEXI_FIELD_REMOVE_VALUE' ).'</label>';
					$remove .= '</div>';
				}
				
				$originalname = '<input name="'.$fieldname.'[originalname]" id="'.$elementid.'_originalname" type="hidden" class="originalname" value="'.$value['originalname'].'" />';
				
				$img_link  = JURI::root().$field->parameters->get('dir');
				$img_link .= ($image_source ? '/item_'.$u_item_id . '_field_'.$field->id : "");
				$img_link .= '/s_' .$extra_prefix. $value['originalname'];
				$imgpreview = '<img class="preview_image" id="'.$elementid.'_preview_image" src="'.$img_link.'" style="border: 1px solid silver; float:left;" />';
				
			} else {
				
				$originalname = '<input name="'.$fieldname.'[originalname]" id="'.$elementid.'_originalname" type="hidden" class="originalname" value="" />';
				$imgpreview = '<div class="empty_image" id="'.$elementid.'_preview_image" style="height:'.$field->parameters->get('h_s').'px; width:'.$field->parameters->get('w_s').'px;"></div>';
			}
			
			if ( !$image_source ) {
				$change .= !$multiple ?
					' <input class="imgchange" style="display:none;" type="checkbox" name="'.$fieldname.'[change]" id="'.$elementid.'_change" onchange="fx_toggle_upload_select_tbl(this, $(\''.$field->name.'_upload_select_tbl_'.$n.'\'))" value="1" '.($image_name ? '' : ' checked="checked" ').'/>' :
					' <input class="imgchange" style="display:none;" type="checkbox" name="'.$fieldname.'[change]" id="'.$elementid.'_change" onchange="fx_toggle_upload_select_tbl(this)" value="1" '.($image_name ? '' : ' checked="checked" ').' />' ;
				$change .= ' <span></span><label class="fcfield-button" style="margin: 0px 0px 4px 0px !important;" for="'.$elementid.'_change">'.JText::_( 'FLEXI_TOGGLE_IMAGE_SELECTOR' ).'</label>';
			}
			
			if ($linkto_url) $urllink =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).':</td>
					<td><input class="imglink" size="40" name="'.$fieldname.'[urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" /></td>
				</tr>';
			if ($usealt) $alt =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).')</td>
					<td><input class="imgalt" size="40" name="'.$fieldname.'[alt]" value="'.(isset($value['alt']) ? $value['alt'] : $default_alt).'" type="text" /></td>
				</tr>';
			if ($usetitle) $title =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><input class="imgtitle" size="40" name="'.$fieldname.'[title]" value="'.(isset($value['title']) ? $value['title'] : $default_title).'" type="text" /></td>
				</tr>';
			if ($usedesc) $desc =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).': <br/>('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><textarea class="imgdesc" name="'.$fieldname.'[desc]" rows="5" cols="28" >'.(isset($value['desc']) ? $value['desc'] : $default_desc).'</textarea></td>
				</tr>';
			
			$curr_select = str_replace('__FORMFLDNAME__', $fieldname.'[existingname]', $select);
			$curr_select = str_replace('__FORMFLDID__', $elementid.'_existingname', $curr_select);
			
			$field->html[] = '
			'.($image_source ? $curr_select : $change).'
			'.$move2.'
			'.$remove_button.'<br/>
			<div class="fcimg_preview_box" style="float:left!important; clear:none!important; margin-right:5px!important;">
				'.$imgpreview.'
				'.$originalname.'
				<div style="float:left; clear:both;" class="imgactions_box">
					'.($remove ? $remove : '').'
					'.($delete ? $delete : '').'
				</div>
			</div>
			<div style="float:left; clear:none;" class="img_value_props">
				<table class="admintable"><tbody>
					'.@$urllink.'
					'.@$alt.'
					'.@$title.'
					'.@$desc.'
				</tbody></table>
			</div>'.
			
			( !$image_source ? '
				<table class="admintable fcfield'.$field->id.' img_upload_select" id="'.$field->name.'_upload_select_tbl_'.$n.'" style="border:1px dashed gray; float:left; margin-bottom:16px;'.($image_name ? "display:none;" : "").'" ><tbody>
					<tr class="img_newfile_row">
						<td class="key fckey_high">'.JText::_( 'FLEXI_FIELD_NEWFILE' ).':</td>
						<td style="white-space: normal;">'.
							'<input name="'.$field->name.'['.$n.']" id="'.$elementid.'_newfile"  class="newfile no_value_selected" '.$onchange.' type="file" /><br/><br/>' .
							'<b>'.JText::_( 'FLEXI_FIELD_MAXSIZE' ).'</b>: '.($field->parameters->get('upload_maxsize') / 1000000).' MBs &nbsp; - &nbsp; <br/>' .
							'<b>'.JText::_( 'FLEXI_FIELD_ALLOWEDEXT' ).'</b>: '.str_replace(",", ", ", $field->parameters->get('upload_extensions')) .'
						</td>
					<tr class="img_existingfile_row">
						<td class="key fckey_high">'.JText::_( !$image_source ? 'FLEXI_FIELD_EXISTINGFILE' : 'FLEXI_SELECT' ).':</td>
						<td>'.$curr_select.'</td>
					</tr>
				</tbody></table>
			'  :  '')
			;
			
			$n++;
			$image_added = true;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li class="fcfieldval_container">'. implode('</li><li class="fcfieldval_container">', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" style="float:left; clear:both;" onclick="addField'.$field->id.'(this);" value=" -- '.JText::_( 'FLEXI_ADD_IMAGE_CONTAINER' ).' -- " />';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container">' . $field->html[0] .'</div>';
		}
		
		$field->html .= '<input id="'.$field->name.'" class="'.$required.'" style="display:none;" name="__fcfld_valcnt__['.$field->name.']" value="'.($count_vals ? $count_vals : '').'">';
		
		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// Get isMobile / isTablet Flags
		static $isMobile = null;
		static $isTablet = null;
		static $useMobile = null;
		if ($useMobile===null) 
		{
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
		
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);

		static $multiboxadded = false;
		static $fancyboxadded = false;
		static $gallerifficadded = false;
		static $elastislideadded = false;
		static $photoswipeadded  = false;
		
		$values = $values ? $values : $field->value;
		
		$multiple     = $field->parameters->get('allow_multiple', 0 ) ;
		$image_source = $field->parameters->get('image_source', 0);
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir          = $field->parameters->get('dir');
		$dir_url      = str_replace('\\','/', $dir);
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		
		$usealt      = $field->parameters->get( 'use_alt', 1 ) ;
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$default_alt = ($alt_usage == 2)  ?  $field->parameters->get( 'default_alt', '' ) : '';
		
		$usetitle      = $field->parameters->get( 'use_title', 1 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		
		$usedesc       = $field->parameters->get( 'use_desc', 1 ) ;
		$desc_usage    = $field->parameters->get( 'desc_usage', 0 ) ;
		$default_desc  = ($desc_usage == 2)  ?  $field->parameters->get( 'default_desc', '' ) : '';
		
		// Separators / enclosing characters
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 0 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
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
		
		// Check for deleted image files or image files that cannot be thumbnailed,
		// rebuilding thumbnails as needed, and then assigning checked values to a new array
		$checked_arr = array();
		if ($values) foreach ($values as $index => $value) {
			$value	= unserialize($value);
			if ( plgFlexicontent_fieldsImage::rebuildThumbs($field,$value) )  $checked_arr[] = $values[$index];
		}
		$values = & $checked_arr;
		
		// Allow for thumbnailing of the default image
		$field->using_default_value = false;
		if ( !count($values) ) {
			// Create default image to be used if  (a) no image assigned  OR  (b) images assigned have been deleted
			$default_image = $field->parameters->get( 'default_image', '');
			if ( $default_image ) {
				$is_default_value = 1;  // flag to use bellow
				$default_image_val = array();
				$default_image_val['is_default_value'] = true;
				$default_image_val['default_image'] = $default_image;
				$default_image_val['originalname'] = basename($default_image);
				$default_image_val['alt'] = $default_alt;
				$default_image_val['title'] = $default_title;
				$default_image_val['desc'] = $default_desc;
				$default_image_val['urllink'] = '';
				$value = serialize($default_image_val);
				
				// Create thumbnails for default image a
				if ( plgFlexicontent_fieldsImage::rebuildThumbs($field, $default_image_val) ) $values = array($value);
				// Also default image can (possibly) be used across multiple fields, so set flag to add field id to filenames of thumbnails
				$multiple_image_usages = true;
				$field->using_default_value = true;
			}
		}
		
		// Check for no values, and return empty display, otherwise assign (possibly) altered value array to back to the field
		if ( !count($values) ) {
			$field->{$prop} = '';
			return;
		}
		$field->value = $values;
		
		$app       = JFactory::getApplication();
		$document	 = JFactory::getDocument();
		$view			 = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		$option    = JRequest::getVar('option');
		jimport('joomla.filesystem');
		
		$isFeedView = JRequest::getCmd('format', null) == 'feed';
		$isItemsManager = $app->isAdmin() && $view=='items' && $option=='com_flexicontent';
		$isSite = $app->isSite();
		
		// some parameter shortcuts
		$uselegend  = $field->parameters->get( 'uselegend', 1 ) ;
		$usepopup   = $field->parameters->get( 'usepopup',  1 ) ;
		
		$popuptype  = $field->parameters->get( 'popuptype', 1 ) ;
		$popuptype_mobile = $field->parameters->get( 'popuptype_mobile', $popuptype ) ;  // this defaults to desktop when empty
		$popuptype = $useMobile ? $popuptype_mobile : $popuptype;
		
		$grouptype  = $field->parameters->get( 'grouptype', 1 ) ;
		$grouptype = $multiple ? 0 : $grouptype;  // Field in gallery mode: Force grouping of images per field (current item)
		
		// Needed by some js galleries
		$thumb_w_s = $field->parameters->get( 'w_s', 120);
		$thumb_h_s = $field->parameters->get( 'h_s',  90);
		
		// Check and disable 'uselegend'
		$legendinview = $field->parameters->get('legendinview', array(FLEXI_ITEMVIEW,'category'));
		$legendinview  = FLEXIUtilities::paramToArray($legendinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$legendinview)) $uselegend = 0;
		if ($view=='category' && !in_array('category',$legendinview)) $uselegend = 0;
		if ($isItemsManager && !in_array('backend',$legendinview)) $uselegend = 0;
		
		// Check and disable 'usepopup'
		$popupinview = $field->parameters->get('popupinview', array(FLEXI_ITEMVIEW,'category','backend'));
		$popupinview  = FLEXIUtilities::paramToArray($popupinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$popupinview)) $usepopup = 0;
		if ($view=='category' && !in_array('category',$popupinview)) $usepopup = 0;
		if ($view=='module' && !in_array('module',$popupinview)) $usepopup = 0;
		if ($isItemsManager && !in_array('backend',$popupinview)) $usepopup = 0;
		
		// FORCE multibox popup in backend ...
		if ($isItemsManager) $popuptype = 1;
		
		// remaining parameters shortcuts
		$showtitle = $field->parameters->get( 'showtitle', 0 ) ;
		$showdesc  = $field->parameters->get( 'showdesc', 0 ) ;
		
		$linkto_url	= $field->parameters->get('linkto_url',0);
		$url_target = $field->parameters->get('url_target','_self');
		$isLinkToPopup = $linkto_url && $url_target=='multibox';
		
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpthumbsize= $field->parameters->get('ogpthumbsize', 2);
		
		// load the tooltip library if redquired
		if ($uselegend) JHTML::_('behavior.tooltip');
		
		// MultiBox maybe added in extra cases besides popup
		// (a) in Item manager, (b) When linking to URL in popup target
		$view_allows_mb  = $isItemsManager || ($isSite && !$isFeedView);
		$config_needs_mb = $isLinkToPopup  || ($usepopup && $popuptype == 1);
		if ( $view_allows_mb && $config_needs_mb )
		{
			if (!$multiboxadded) {
				//echo $field->name.": multiboxadded";
				FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
				
				// Multibox integration use different version for FC v2x
				if (FLEXI_J16GE) {
					
					// Include MultiBox CSS files
					$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/Styles/multiBox.css');
					
					// NEW ie6 hack
					if (substr($_SERVER['HTTP_USER_AGENT'],0,34)=="Mozilla/4.0 (compatible; MSIE 6.0;") {
						$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/Styles/multiBoxIE6.css');
					}  // This is the new code for new multibox version, old multibox hack is the following lines
					
					// Include MultiBox Javascript files
					$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/Scripts/overlay.js');
					$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/Scripts/multiBox.js');
					
					// Add js code for creating a multibox instance
					$extra_options = '';
					if ($isItemsManager) $extra_options .= ''
							.',showNumbers: false'  //show numbers such as "4 of 12"
							.',showControls: false' //show the previous/next, title, download etc
							;
					$box = "
						window.addEvent('domready', function(){
							//call multiBox
							var initMultiBox = new multiBox({
								mbClass: '.mb',//class you need to add links that you want to trigger multiBox with (remember and update CSS files)
								container: $(document.body),//where to inject multiBox
								descClassName: 'multiBoxDesc',//the class name of the description divs
								path: './Files/',//path to mp3 and flv players
								useOverlay: true,//use a semi-transparent background. default: false;
								maxSize: {w:4000, h:3000},//max dimensions (width,height) - set to null to disable resizing
								addDownload: false,//do you want the files to be downloadable?
								pathToDownloadScript: './Scripts/forceDownload.asp',//if above is true, specify path to download script (classicASP and ASP.NET versions included)
								addRollover: true,//add rollover fade to each multibox link
								addOverlayIcon: true,//adds overlay icons to images within multibox links
								addChain: true,//cycle through all images fading them out then in
								recalcTop: true,//subtract the height of controls panel from top position
								addTips: true,//adds MooTools built in 'Tips' class to each element (see: http://mootools.net/docs/Plugins/Tips)
								autoOpen: 0//to auto open a multiBox element on page load change to (1, 2, or 3 etc)
								".$extra_options."
							});
						});
					";
					$document->addScriptDeclaration($box);
				} else {
					
					// Include MultiBox CSS files
					$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/multibox.css');
					
					// OLD ie6 hack
					$csshack = '
					<!--[if lte IE 6]>
					<style type="text/css">
					.MultiBoxClose, .MultiBoxPrevious, .MultiBoxNext, .MultiBoxNextDisabled, .MultiBoxPreviousDisabled { 
						behavior: url('.'components/com_flexicontent/librairies/multibox/iepngfix.htc); 
					}
					</style>
					<![endif]-->
					';
					$document->addCustomTag($csshack);
					
					// Include MultiBox Javascript files
					$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/js/overlay.js');
					$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/js/multibox.js');
					
					// Add js code for creating a multibox instance
					$extra_options = $isItemsManager ? ', showNumbers: false, showControls: false' : '';
					$box = "
						var box = {};
						window.addEvent('domready', function(){
							box = new MultiBox('mb', {descClassName: 'multiBoxDesc', useOverlay: true".$extra_options." });
						});
					";
					$document->addScriptDeclaration($box);
				}
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
				$inline_gallery = 1;
				
				if (!$gallerifficadded) {
					flexicontent_html::loadFramework('galleriffic');
					$gallerifficadded = true;
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
		
		
		// *** Check if images are used in more than one fields ***
		// And add field id prefix to the image filenames
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		
		// Create thumbs/image Folder and URL paths
		if ( !$image_source || !empty($is_default_value) ) {
			$thumb_folder  = JPATH_SITE .DS. JPath::clean($dir);
			$thumb_urlpath = $dir_url;
			$orig_urlpath  = $dir_url;
		} else {
			$thumb_folder  = JPATH_SITE .DS. JPath::clean($dir) .DS. 'item_'.$item->id . '_field_'.$field->id;
			$thumb_urlpath = $dir_url . '/item_'.$item->id . '_field_'.$field->id;
			$orig_urlpath  = $thumb_urlpath . '/original';
		}
		
		$i = -1;
		$field->{$prop} = array();
		$field->thumbs_src['backend'] = array();
		$field->thumbs_src['small'] = array();
		$field->thumbs_src['medium'] = array();
		$field->thumbs_src['large'] = array();
		$field->thumbs_src['original'] = array();
		foreach ($values as $val)
		{
			// Unserialize value's properties and check for empty original name property
			$value	= unserialize($val);
			if ( !strlen(trim(@$value['originalname'])) ) continue;
			$i++;
			
			// Create thumbnails urls, note thumbnails have already been verified above
			$wl = $field->parameters->get( 'w_l', 800 );
			$hl = $field->parameters->get( 'h_l', 600 );
			$title	= @$value['title'] ? $value['title'] : '';
			$alt	= @$value['alt'] ? $value['alt'] : flexicontent_html::striptagsandcut($item->title, 60);
			$alt	= flexicontent_html::escapeJsText($alt,'s');
			$desc	= @$value['desc'] ? $value['desc'] : '';

			$srcb	= $thumb_urlpath . '/b_' .$extra_prefix. $value['originalname'];  // backend
			$srcs	= $thumb_urlpath . '/s_' .$extra_prefix. $value['originalname'];  // small
			$srcm	= $thumb_urlpath . '/m_' .$extra_prefix. $value['originalname'];  // medium
			$srcl	= $thumb_urlpath . '/l_' .$extra_prefix. $value['originalname'];  // large
			$srco	= $orig_urlpath  . '/'   .$value['originalname'];  // original image
			
			// Create a popup url link
			$urllink = @$value['urllink'] ? $value['urllink'] : '';
			if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
			
			// Create a popup tooltip (legend)
			$tip = $title . '::' . $desc;
			$tip = flexicontent_html::escapeJsText($tip,'s');
			$legend = ($uselegend && (!empty($title) || !empty($desc) ) )? ' class="hasTip" title="'.$tip.'"' : '' ;
			
			// Create a unique id for the link tags, and a class name for image tags
			$uniqueid = $field->item_id . '_' . $field->id . '_' . $i;
			$class_img_field = 'fc_field_image';
			
			
			// Decide thumbnail to use
			$thumb_size = 0;
			if ($view == 'category')
				$thumb_size =  $field->parameters->get('thumbincatview',1);
			if($view == FLEXI_ITEMVIEW)
				$thumb_size =  $field->parameters->get('thumbinitemview',2);
			switch ($thumb_size)
			{
				case 1: $src = $srcs; break;
				case 2: $src = $srcm; break;
				case 3: $src = $srcl; break;   // this makes little sense, since both thumbnail and popup image are size 'large'
				case 4: $src = $srco; break;
				default: $src = $srcs; break;
			}
			
			
			// Create a grouping name
			switch ($grouptype)
			{
				case 0: $group_name = 'fcview_'.$view.'_fcitem_'.$field->item_id.'_fcfield_'.$field->id; break;
				case 1: $group_name = 'fcview_'.$view.'_fcitem_'.$field->item_id; break;
				case 2: $group_name = 'fcview_'.$view; break;
				default: $group_name = ''; break;
			}
			
			
			// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only
			if ($i==0) {
				$field->{"display_backend_src"} = JURI::root().$srcb;
				$field->{"display_small_src"} = JURI::root().$srcs;
				$field->{"display_medium_src"} = JURI::root().$srcm;
				$field->{"display_large_src"} = JURI::root().$srcl;
				$field->{"display_original_src"} = JURI::root().$srco;
			}
			$field->thumbs_src['backend'][] = JURI::root().$srcb;
			$field->thumbs_src['small'][] = JURI::root().$srcs;
			$field->thumbs_src['medium'][] = JURI::root().$srcm;
			$field->thumbs_src['large'][] = JURI::root().$srcl;
			$field->thumbs_src['original'][] = JURI::root().$srco;
			
			$field->thumbs_path['backend'][] = JPATH_SITE.DS.$srcb;
			$field->thumbs_path['small'][] = JPATH_SITE.DS.$srcs;
			$field->thumbs_path['medium'][] = JPATH_SITE.DS.$srcm;
			$field->thumbs_path['large'][] = JPATH_SITE.DS.$srcl;
			$field->thumbs_path['original'][] = JPATH_SITE.DS.$srco;
			
			// Suggest image for external use, e.g. for Facebook etc
			if ( ($isSite && !$isFeedView) && $useogp) {
				if ( in_array($view, $ogpinview) ) {
					switch ($ogpthumbsize)
					{
						case 1: $ogp_src = $field->{"display_small_src"}; break;   // this maybe problematic, since it maybe too small or not accepted by social website
						case 2: $ogp_src = $field->{"display_medium_src"}; break;
						case 3: $ogp_src = $field->{"display_large_src"}; break;
						case 4: $ogp_src = $field->{"display_original_src"}; break;
						default: $ogp_src = $field->{"display_medium_src"}; break;
					}
					$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
					$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
				}
			}
			
			
			// Check if a custom URL-only (display) variable was requested and return it here,
			// without rendering the extra image parameters like legend, pop-up, etc
			if ( in_array($prop, array("display_backend_src", "display_small_src", "display_medium_src", "display_large_src", "display_original_src") ) ) {
				return $field->{$prop};
			}
			
			
			// Create image tags (according to configuration parameters) that will be used for the requested 'display' variable
			switch ($prop)
			{
				case 'display_backend':
					$img_legend   = '<img src="'.JURI::root().$srcb.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcb.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_small':
					$img_legend   = '<img src="'.JURI::root().$srcs.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcs.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_medium':
					$img_legend   = '<img src="'.JURI::root().$srcm.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcm.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_large':
					$img_legend   = '<img src="'.JURI::root().$srcl.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcl.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_original':
					$img_legend   = '<img src="'.JURI::root().$srco.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srco.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display': default:
					$_src = $isItemsManager ? $srcb : $src;
					$img_legend   = '<img src="'.JURI::root().$_src.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$_src.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
			}
			
			
			// *********************************************
			// FINALLY CREATE the field display variable ...
			// *********************************************
			
			if ($isItemsManager) {
				
				// CASE 1: Handle image displayed in backend items manager
				
				if ($usepopup) {
					$field->{$prop} = '
					<a href="../'.$srcl.'" id="mb'.$uniqueid.'" class="mb" rel="[images]" >
						'.$img_legend.'
					</a>
					<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					';
				} else {
					$field->{$prop} = $img_legend;
				}
				return;  // Single image always ...
				
			} else if ($linkto_url && $urllink) {
				
				// CASE 2: Handle linking to a URL instead of image zooming popup
				
				if ($url_target=='multibox') {  // (a) Link to URL that opens inside a popup
					$field->{$prop}[] = '
					<script>document.write(\'<a href="'.$urllink.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+((MooTools.version>=\'1.2.4\' ? window.getSize().x : window.getSize().size.x)-150)+\',height:\'+((MooTools.version>=\'1.2.4\' ? window.getSize().y : window.getSize().size.y)-150)+\'">\')</script>
						'.$img_legend.'
					<script>document.write(\'</a>\')</script>
					<div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					';
				} else {    // (b) Just link to URL
					$field->{$prop}[] = '
					<a href="'.$urllink.'" target="'.$url_target.'">
						'.$img_legend.'
					</a>
					';
				}
				
			} else if ($usepopup) {
				
				// CASE 3: Handle image zooming popup
				
				// no popup if image is the largest one
				if ($prop=='display_large' || $prop=='display_original') {
					$field->{$prop}[] = $img_legend;
					continue;
				}
				
				switch ($popuptype)
				{
				case 1:   // Multibox image popup
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" id="mb'.$uniqueid.'" class="mb" '.$group_str.' >
							'.$img_legend.'
						</a>
						<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
						';
					break;
				case 2:   // Rokbox image popup
					$title_attr = flexicontent_html::escapeJsText($desc ? $desc : $title,'s');
					$group_str = '';   // no support for image grouping
					$field->{$prop}[] = '
						<a href="'.$srcl.'" rel="rokbox['.$wl.' '.$hl.']" '.$group_str.' title="'.$title_attr.'">
							'.$img_nolegend.'
						</a>
						';
					break;
				case 3:   // JCE popup image popup
					$title_attr = flexicontent_html::escapeJsText($desc ? $desc : $title,'s');
					$group_str = $group_name ? 'rel="group['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" class="jcepopup" '.$group_str.' title="'.$title_attr.'">
							'.$img_nolegend.'
						</a>
						';
					break;
				case 4:   // Fancybox image popup
					$title_attr = flexicontent_html::escapeJsText($desc ? $desc : $title,'s');
					$group_str = $group_name ? 'data-fancybox-group="'.$group_name.'"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" class="fancybox" '.$group_str.' title="'.$title_attr.'">
							'.$img_nolegend.'
						</a>
						';
					break;
				case 5:   // Galleriffic inline slideshow gallery
					$group_str = '';   // image grouping: not needed / not applicatble
					$field->{$prop}[] = '
						<a class="thumb" name="drop" href="'.$srcl.'" style="">
							'.$img_legend.'
						</a>
						<div class="caption">
							'.'<b>'.$title.'</b><br/>'.$desc.'
						</div>
						';
					break;
				case 6:   // (Widgetkit) SPOTlight image popup
					$group_str = $group_name ? 'data-spotlight-group="'.$group_name.'"' : '';
					$title_attr = $title .' | '. ($desc ? $desc : $title);
					$title_attr = flexicontent_html::escapeJsText($title_attr,'s');
					$field->{$prop}[] = '
						<a href="'.$srcl.'" data-lightbox="on" data-spotlight="effect:bottom" '.$group_str.' title="'.$title_attr.'">
							'.$img_nolegend.'
							<div class="overlay">
								'.'<b>'.$title.'</b>: '.$desc.'
							</div>
						</a>
						';
					break;
				case 7:   // Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
					// *** NEEDS: thumbnail list must be created with large size thubmnails, these will be then thumbnailed by the JS gallery code
					$title_attr = flexicontent_html::escapeJsText($desc ? $desc : $title,'s');
					$img_legend_custom ='
						 <img src="'.JURI::root().$_src.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'"
						 	data-large="' . JURI::root().$srcl . '" data-description="'.$title_attr.'"/>
					';
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<li><a href="javascript:;">
							'.$img_legend_custom.'
						</a></li>
						';
					break;
				case 8:   // PhotoSwipe popup carousel gallery
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" '.$group_str.' >
							'.$img_legend.'
						</a>
						';
					break;
				default:  // Unknown Gallery Type, just add thumbails ...
					$field->{$prop}[] = $img_legend;
					break;
				}
				
			} else {
				// CASE 4: Plain Thumbnail List without any (popup / inline) gallery code 
				$field->{$prop}[] = $img_legend;
			}
			
			$n = count($field->{$prop}) - 1;
			if ( ($showtitle && $title ) || ($showdesc && $desc) )
				$field->{$prop}[$n] = '<div class="fc_img_tooltip_data" style="float:left; margin-right:8px;" >'.$field->{$prop}[$i];
				
			if ( $showtitle && $title )
				$field->{$prop}[$n] .= '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>';
			if ( $showdesc && $desc )
				$field->{$prop}[$n] .= '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>';
				
			if ( ($showtitle && $title ) || ($showdesc && $desc) )
				$field->{$prop}[$n] .= '</div>';
			
			$field->{$prop}[$n] = $pretext. $field->{$prop}[$i] .$posttext;
		}
		
		
		// ************************************************************
		// Apply separator and open/close tags and handle SPECIAL CASEs:
		// by add some exta html required by some JS image libraries
		// ************************************************************
		
		// Check for no values found
		if ( !count($field->{$prop}) ) {
			$field->{$prop} = '';
			return;
		}
		
		// Galleriffic inline slideshow gallery
		if ($usepopup && $popuptype == 5) {
			$field->{$prop} = $opentag . '
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
			' . $closetag;
		}
		
		// Elastislide inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
		else if ($usepopup && $popuptype == 7) {
			//$max_width = $field->parameters->get( 'w_l', 800 );
			
			// this should be size of previewer aka size of large image thumbnail
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
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) || empty($post[0]) ) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;   //echo "<pre>"; print_r($post);
		
		// Get configuration
		$is_importcsv      = JRequest::getVar('task') == 'importcsv';
		$import_media_folder  = JRequest::getVar('import_media_folder');
		$image_source = $field->parameters->get('image_source', 0);
		
		// Set a warning message for overriden/changed files: form.php (frontend) or default.php (backend)
		if ( !$is_importcsv && empty($unique_tmp_itemid) ) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( 'WARNING, field: '.$field->label.' requires variable -unique_tmp_itemid- please update your '.($app->isSite() ? 'form.php':'default.php'), 'warning');
		}
		
		// Execute once
		static $initialized = null;
		static $srcpath_original = '';
		if ( $is_importcsv && !$initialized ) {
			$initialized = 1;
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.jpath');
			$srcpath_original  = JPath::clean( JPATH_SITE .DS. $import_media_folder .DS );
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'filemanager.php');
		}

		
		// **********************************************
		// Special steps for image field in 'Folder' mode
		// **********************************************
		if ( $image_source )
		{
			$dir = $field->parameters->get('dir');
			$unique_tmp_itemid = JRequest::getVar( 'unique_tmp_itemid', '' );
			
			// Create original images folder if doing CSV import and folder does not exist
			if ( $is_importcsv ) {
				$destpath_original = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$field->item_id   . '_field_'.$field->id .DS. 'original' .DS);
				if ( !JFolder::exists($destpath_original) && !JFolder::create($destpath_original) ) {
					JError::raiseWarning(100, $field->label .': Error. Unable to create folder: '. $destpath_original );
					return false;  // Cancel item creation
				}
			}
			
			// New items have no item id during submission, thus we need to rename the temporary name of images upload folder
			else if ( $unique_tmp_itemid && $field->item_id != $unique_tmp_itemid ) {
				$temppath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$unique_tmp_itemid. '_field_'.$field->id .DS );
				$destpath = JPath::clean( JPATH_SITE .DS. $dir . DS. 'item_'.$field->item_id   . '_field_'.$field->id .DS );
				JFolder::move($temppath, $destpath);
			}
		}
		
		
		// **************************************************************************
		// Rearrange file array so that file properties are groupped per image number
		// **************************************************************************
		$files = array();
		if ($file) foreach( $file as $key => $all ) {
			foreach( $all as $i => $val ) {
				$files[$i][$key] = $val;
			}
		}
		
		
		// *****************************************************************************************
		// Reformat the posted data & handle uploading / removing / deleting / replacing image files
		// *****************************************************************************************
		$newpost = array();
		$new = 0;
    foreach ($post as $n => $v)
    {
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($post[$n]) ) {
				if ( @unserialize($post[$n])!== false || $post[$n] === 'b:0;' ) {  // support for exported serialized data)
					$v = $post[$n] = unserialize($post[$n]);
				} else {
					$v = $post[$n] = array('originalname' => $post[$n]);
				}
			} else {
				$v = $post[$n] = array('originalname' => $post[$n]);
			}
			
			// (a) Handle uploading a new original file
			if ( isset($files[$n]) ) {
				$this->uploadOriginalFile($field, $v, $files[$n]);
			}
			
			// Handle copying original files from a server folder during CSV import
			else if ($is_importcsv && $import_media_folder ) {
				$filename = $v['originalname'];
				if ($image_source) {
					$srcfilepath  = JPath::clean( $srcpath_original  . $filename );
					$destfilepath = JPath::clean( $destpath_original . $filename );
					if ( JFile::exists($srcfilepath) ) {
						$result = JFile::copy( $srcfilepath,  $destfilepath );
						if ( $result && JPath::canChmod($destfilepath) )  chmod($destfilepath, 0644);
					}
				} else {
					$fman = new FlexicontentControllerFilemanager();
					JRequest::setVar( 'return-url', null, 'post' );
					JRequest::setVar( 'file-dir-path', DS. $import_media_folder, 'post' );
					JRequest::setVar( 'file-filter-re', preg_quote($filename), 'post' );
					JRequest::setVar( 'secure', 1, 'post' );
					JRequest::setVar( 'keep', 1, 'post' );
					$fman->addlocal();
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
					if ( $v['delete'] ) {
						$filename = $v['originalname'];
						$this->removeOriginalFile( $field, $filename );
						//JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . JText::_('Deleted image from server storage'));
					} elseif ( $v['remove'] && $v['existingname'] ) {
						//JFactory::getApplication()->enqueueMessage($field->label . ' ['.$n.'] : ' . JText::_('Removed image assignment to the field'));
					}
				}
				
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
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
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
					return;
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
					return;
				}
			} else {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
					
					$db     = JFactory::getDBO();
					$user   = JFactory::getUser();
					$config = JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date     = JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= 1;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);
					
					jexit('Upload complete');
				} else {

					$db     = JFactory::getDBO();
					$user   = JFactory::getUser();
					$config = JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date     = JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= 1;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);

					$app->enqueueMessage($field->label . ' : ' . JText::_('Upload complete'));
					
					$sizes 		= array('l','m','s','b');
					foreach ($sizes as $size)
					{
						// create the thumbnail
						$this->create_thumb( $field, $filename, $size, $onlypath='', $destpath='', $copy_original=0, $extra_prefix );
						// set the filename for posting
						$post['originalname'] = $filename;
					}
					return;
				}
			}
		}
	}
	
	
	// ***********************************************************************************************
	// Decide parameters for calling phpThumb library to create a thumbnail according to configuration
	// ***********************************************************************************************
	function create_thumb( &$field, $filename, $size, $onlypath='', $destpath='', $copy_original=0, $extra_prefix='' ) {
		static $destpaths_arr = array();
		
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.jpath');
		
		// (DB/Folder) Mode of image field
		$image_source = $field->parameters->get('image_source', 0);
		
		// Image file paths
		$dir = $field->parameters->get('dir');
		$onlypath = $onlypath ? $onlypath : JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
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
			$result = JFile::copy( $onlypath.$filename,  $filepath );
		}
		
		// OR Create the thumnail by calling phpthumb
		else {
			$result = $this->imagePhpThumb( $onlypath, $destpath, $prefix, $filename, $ext, $w, $h, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos );
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

		if ( $image_source ) {
			$origfolder  = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id .DS. 'original';
			$origfolder  = JPath::clean(str_replace('\\','/', $origfolder)).DS;
			$thumbfolder = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id;
		} else {
			$origfolder  = JPath::clean( COM_FLEXICONTENT_FILEPATH );
			$thumbfolder = JPath::clean( JPATH_SITE .DS. $field->parameters->get('dir') );
		}
		
		// a. Delete the thumbnails
		$errors		= array();
		$sizes 		= array('l','m','s','b');
		foreach ($sizes as $size)
		{
			$thumbpath = $thumbfolder . DS . $size . '_' . $filename;
			if ( JFile::exists($thumbpath) && !JFile::delete($thumbpath) )
			{ 
				// Handle failed delete, currently this is not outputed, since thumbnails may not have been created, or may have been deleted manually ??
				JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $thumbpath);
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
				. ' WHERE ' . $db->nameQuote('filename') . ' = ' . $db->Quote($filename);
			$db->setQuery( $query );
			if(!$db->query())
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
	function rebuildThumbs( &$field, $value )
	{
		static $images_processed = array();
		
		$filename = trim( @$value['originalname'] );
		if ( !$filename ) return;  // check for empty filename
		
		$image_source = $field->parameters->get('image_source', 0);
		$all_media    = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$dir = $field->parameters->get('dir');
		
		// FLAG to indicate if images are shared across fields, has the effect of adding field id to image thumbnails
		$multiple_image_usages = !$image_source && $all_media && $unique_thumb_method==0;
		$multiple_image_usages = $multiple_image_usages || @$value['is_default_value'];
		
		// ******************************
		// Find out path to original file
		// ******************************
		if (empty($value['is_default_value'])) {
			
			if ( $image_source ) {
				$onlypath  = JPath::clean( JPATH_SITE .DS. $dir .DS. 'item_'.$field->item_id . '_field_'.$field->id .DS. 'original' .DS );
				$thumbpath = JPath::clean( JPATH_SITE .DS. $dir .DS. 'item_'.$field->item_id . '_field_'.$field->id .DS );
			} else {
				$onlypath  = JPath::clean( COM_FLEXICONTENT_FILEPATH .DS );
				$thumbpath = JPath::clean( JPATH_SITE .DS. $dir .DS );
			}
			
			$filepath = JPath::clean( $onlypath . $filename );
			$destpath = JPath::clean( JPATH_SITE .DS. $dir . ($image_source ?  DS. 'item_'.$field->item_id . '_field_'.$field->id  :  "") .DS );
			
		} else {
			$onlypath  = JPath::clean( JPATH_BASE .DS. dirname($value['default_image']) .DS );
			$thumbpath = JPath::clean( JPATH_SITE .DS. $dir .DS );
			
			$filepath = JPath::clean( JPATH_BASE .DS. $value['default_image'] );
			$destpath = JPath::clean( JPATH_SITE .DS. $dir .DS );
		}
		
		// ******************************************
		// Enforce protection of original image files
		// ******************************************
		if ($image_source) {
			$protect_original = $field->parameters->get('protect_original', 1);
			$htaccess_file = JPath::clean( $onlypath . '.htaccess' );
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
		
		// ** PERFORMANCE CONSIDERATION : Try to avoid rechecking/recreating image thumbnails multiple times
		if ($image_source) {
			$pindex = 'item_'.$field->item_id . '_field_'.$field->id;
		} else {
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
		
		
		// ***********************************************
		// Check dimension of thumbs and rebuild as needed
		// ***********************************************
		$filesize	= getimagesize($filepath);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];
		
		$sizes = array('l','m','s','b');
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		
		$extra_prefix = $multiple_image_usages  ?  'fld'.$field->id.'_'  :  '';
		if ($extra_prefix) $sizes[] = '_s';  // always create an unprefixed small thumb, it is needed when assigning preview (and by imagepicker JS lib)
		$thumbres = true;
		foreach ($sizes as $size)
		{
			$check_small = $size=='_s';
			$size = $check_small ? 's' : $size;
			$thumbname = $size . '_' . ($check_small ? '' : $extra_prefix) . $filename;
			$path	= JPath::clean( $thumbpath .DS. $thumbname);
			
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
				$was_thumbed = $this->create_thumb( $field, $filename, $size, $onlypath, $destpath, $copy_original, ($check_small ? '' : $extra_prefix) );
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
		$imagepickerlimit  = $field->parameters->get('imagepickerlimit', 200);
		$all_media         = $field->parameters->get('list_all_media_files', 0);
		$unique_thumb_method = $field->parameters->get('unique_thumb_method', 0);
		$limit_by_uploader = $field->parameters->get('limit_by_uploader', 0);  // USED ONLY WHEN all_media is ENABLED
		$image_folder = JURI::root().$field->parameters->get('dir');
		
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
		$onchange .= ($required) ? '' : '';
		$onchange .= " qmAssignFile".$field->id."(this.id, '', fc_db_img_path+'/s_'+this.value);";
		$onchange .= ' "';
		$classes = ' existingname no_value_selected ';
		$js = "";
		
	  // Add Image Picker script on the document.ready event
		static $imagepicker_added = false;
		$use_imgpicker = $images_count <= $imagepickerlimit;
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
			$list	= "<input class=\"fcfield-button\" type=\"button\" value=\"".$btn_name."\" onclick=\"fcimgfld_toggle_image_picker(this);\" > " .$list;
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
		
		if ( !$field->untranslatable ) $ignored['item_id'] = $item->id;
		else $ignored['lang_parent_id'] = $item->lang_parent_id;
		
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
	
}
