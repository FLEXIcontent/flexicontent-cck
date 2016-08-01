<?php
/**
 * @version 1.0 $Id: fieldgroup.php
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.fieldgroup
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

class plgFlexicontent_fieldsFieldgroup extends JPlugin
{
	static $field_types = array('fieldgroup');
	static $extra_props = array();
	//static $prior_to_version = "3.2";  // Display message for non free plugin
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_fieldgroup', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = 0; // Field grouped should not be recursively grouped
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$compact_edit = $field->parameters->get('compact_edit', 0);
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$db = JFactory::getDBO();
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// **************
		// Value handling
		// **************
		
		// Get fields belonging to this field group
		$grouped_fields = $this->getGroupFields($field);
		
		// Get values of fields making sure that also empty values are created too
		$max_count = 1;
		$this->getGroupFieldsValues($field, $item, $grouped_fields, $max_count);
		
		// Render Form HTML of the field
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			$grouped_field->ingroup = 1;
			$grouped_field->item_id = $item->id;
			FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayField', array(&$grouped_field, &$item));
			unset($grouped_field->ingroup);
		}
		
		
		
		$js = "";
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
			$js .= "
			jQuery(document).ready(function(){"
				.($compact_edit==2 ? "jQuery('#sortables_".$field->id."').find('.toggle_group_down').data('fc_noeffect', 1).trigger('click');" : "")
				.($compact_edit==1 ? "jQuery('#sortables_".$field->id."').find('.toggle_group_up').data('fc_noeffect', 1).trigger('click');" : "")
			."});
			";
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".$max_count.";  // Unique row number incremented only
			var rowCount".$field->id."	= ".$max_count.";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			";
		
			// Create function call for add/deleting Field values
			$addField_pattern = "
				var fieldval_box = groupval_box.find('.fcfieldval_container__GRP_FID_');
				fieldval_box.find('.invalid').removeClass('invalid').attr('aria-invalid', 'false');
				var newSubLabel = fieldval_box.prev('label.sub_label');
				var newLabelFor = 'custom_%s_'+uniqueRowNum".$field->id.";
				newSubLabel.attr('id', newLabelFor + '-lbl');
				newSubLabel.attr('for', newLabelFor);
				newSubLabel.attr('data-for', newLabelFor);
				addField_GRP_FID_(null, groupval_box, groupval_box.find('.fcfieldval_container__GRP_FID_'), add_params);";
			$delField_pattern = "
				if(rowCount".$field->id." == 1)
				{
					// We need to update the current grouped label of the field if this was the last element being re-added
					var fieldval_box = groupval_box.find('.fcfieldval_container__GRP_FID_');
					fieldval_box.find('.invalid').removeClass('invalid').attr('aria-invalid', 'false');
					var newSubLabel = fieldval_box.prev('label.sub_label');
					var newLabelFor = 'custom_%s_'+uniqueRowNum".$field->id.";
					newSubLabel.attr('for', newLabelFor);
					newSubLabel.attr('data-for', newLabelFor);
				}
				deleteField_GRP_FID_(null, groupval_box, groupval_box.find('.fcfieldval_container__GRP_FID_'));
				";
			$addField_funcs = $delField_funcs = '';
			foreach($grouped_fields as $field_id => $grouped_field) {
				$addField_funcs .= str_replace("_GRP_FID_",  $grouped_field->id,  sprintf($addField_pattern, $grouped_field->name)  );
				$delField_funcs .= str_replace("_GRP_FID_",  $grouped_field->id,  sprintf($delField_pattern, $grouped_field->name)  );
			}
		
			$js .= "
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
				
				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().find('ul.fcfield-sortables').children().last();
				var newField  = lastField.clone();
				
				// Need to at least change FORM field names and HTML tag IDs before adding the container to the DOM
				var theSet = newField.find('input, select');
				var nr = 0;
				theSet.each(function() {
					var elem = jQuery(this);
					elem.attr('name', '_duplicated_".$field->id."_'+uniqueRowNum".$field->id."+'_'+nr);
					elem.attr('id', '_duplicated_".$field->id."_'+uniqueRowNum".$field->id."+'_'+nr);
					nr++;
				});
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				";
			
			// Add new element to sortable objects (if field not in group) -- NOTE: remove_previous: 2 means remove element without do any cleanup actions
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				
				// Add new values for each field
				var groupval_box = newField;
				var add_params = {remove_previous: 2, scroll_visible: 0, animate_visible: 0};
				".$addField_funcs."
				";
			
			// Readd prettyCheckable and remove previous if so requested
			$js .="
				if (remove_previous) lastField.remove();
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

			function deleteField".$field->id."(el)
			{
				// Disable clicks
				var btn = jQuery(el);
				if (btn) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = btn.closest('li');
				
				// Do cleanup by calling the deleteField of each individual field, these functions will re-add last element as empty if needed
				var groupval_box = jQuery(el).closest('li');
				".$delField_funcs."
				if(rowCount".$field->id." == 1)
				{
					uniqueRowNum".$field->id."++;   // increment unique row id, since last group was re-added
				}
				
				// Also remove the group field values container if not last one
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.fadeOut(420, function(){ this.remove(); });
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
			$togglers = !$compact_edit ? '' : '
				<span class="toggle_group_down '.$add_on_class.' btn btn-small btn-success" style="text-shadow: unset; '.($compact_edit==2 ? 'display:none;' :'').' min-width: 120px;" onclick="fc_toggle_box_via_btn(jQuery(this).closest(\'li\').find(\'.fcfieldval_container_outer:not(.fcAlwaysVisibleField)\'), this, \'\', jQuery(this).next(), 1); return false;">
					<i class="icon-downarrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_EDIT_DETAILS' ). '
				</span>
				<span class="toggle_group_up   '.$add_on_class.' btn btn-small" style="text-shadow: unset; '.($compact_edit==1 ? 'display:none;' :'').' min-width: 120px;" onclick="fc_toggle_box_via_btn(jQuery(this).closest(\'li\').find(\'.fcfieldval_container_outer:not(.fcAlwaysVisibleField)\'), this, \'\', jQuery(this).prev(), 0); return false;">
					<i class="icon-uparrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_HIDE_DETAILS' ). '
				</span>
			';
		} else {
			$remove_button = '';
			$move2 = '';
			$togglers = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
		$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
		
		
		if ($compact_edit) {
			$compact_edit_excluded = $field->parameters->get('compact_edit_excluded', array());
			if ( empty($compact_edit_excluded) )  $compact_edit_excluded = array();
			if ( !is_array($compact_edit_excluded) )  $compact_edit_excluded = preg_split("/[\|,]/", $compact_edit_excluded);
			$compact_edit_excluded = array_flip($compact_edit_excluded);
		}
		
		
		$field->html = array();
		for($n = 0; $n < $max_count; $n++)
		{
			$field->html[$n] = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.$togglers.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				');
			
			// Append item-form display HTML of the every field in the group
			$i = 0;
			foreach($grouped_fields as $field_id => $grouped_field) {
				$lbl_class = 'flexi label sub_label';
				$lbl_title = '';
				// field has tooltip
				$edithelp = $grouped_field->edithelp ? $grouped_field->edithelp : 1;
				if ( $grouped_field->description && ($edithelp==1 || $edithelp==2) ) {
					 $lbl_class .= ($edithelp==2 ? ' fc_tooltip_icon ' : ' ') .$tooltip_class;
					 $lbl_title = flexicontent_html::getToolTip(trim($field->label, ':'), $grouped_field->description, 0, 1);
				}
				
				$field->html[$n] .= '<div class="fcclear"></div>
				<div class="fcfieldval_container_outer'.($compact_edit && isset($compact_edit_excluded[$field_id]) ? ' fcAlwaysVisibleField' : '').'">
					<label id="custom_'.$grouped_field->name.'_'.$n.'-lbl" class="'.$lbl_class.'" title="'.$lbl_title.'" data-for="custom_'.$grouped_field->name.'_'.$n.'">'.$grouped_field->label.'</label>
					<div class="fcfieldval_container valuebox fcfieldval_container_'.$grouped_field->id.' container_fcfield_name_'.$grouped_field->name.'" >
						'.($grouped_field->description && $edithelp==3 ? sprintf( $alert_box, '', 'info', 'fc-nobgimage', $grouped_field->description ) : '').'
						'.@ $grouped_field->html[$n].'
					</div>
				</div>
				';
				$i++;
			}
			
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		// Non value HTML
		$non_value_html = '';
		foreach($grouped_fields as $field_id => $grouped_field) {
			$non_value_html .= @$grouped_field->html[-1];
		}
		
		// Implode form HTML as a list
		$list_classes  = "fcfield-sortables";
		$list_classes .= " fcfield-group";
		if (count($field->html)) {
			$field->html = '<li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.' container_fcfield_name_'.$field->name.'">'.
				implode(
				'</li><li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.' container_fcfield_name_'.$field->name.'">',
					$field->html
				).
				'</li>';
			$field->html = '<div id="sortables_outer_'.$field->id.'"><ul class="'.$list_classes.'" id="sortables_'.$field->id.'">' .$field->html. '</ul></div>';
		} else {
			$field->html = '';
		}
		if (!$add_position) $field->html .= '<span class="fcfield-addvalue '.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').' fccleared" onclick="jQuery(this).prev().prev().find(\'.show_vals_btn\').data(\'fc_noeffect\', 1).trigger(\'click\'); addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>';
		
		// Check max allowed version
		//$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'manifest.xml';
		//$com_xml = JApplicationHelper::parseXMLInstallFile( $manifest_path );
		
		// Append non value html of fields
		$field->html =
			/*(version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', self::$prior_to_version), '>=') ?
				'<span class="alert alert-warning fc-iblock fc-small" style="margin: 0 0 8px 0;">
					<b>Warning</b>: installed version of Field: \'<b>'.$field->field_type.'</b>\' was meant for FLEXIcontent versions prior to: v'.self::$prior_to_version.' It may or may not work properly in later versions
				</span>' : '').*/
			($field->parameters->get('compact_edit_global', 0) ? '
			<div class="toggle_all_values_buttons_box">
				<span id="sortables_'.$field->id.'_hide_vals_btn" class="btn hide_vals_btn" onclick="fc_toggle_box_via_btn(jQuery(\'#sortables_outer_'.$field->id.'\'), this, \'\', jQuery(this).next(), 0); return false;">
					<i class="icon-uparrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_HIDE_VALUES' ).'
				</span>
				<span id="sortables_'.$field->id.'_show_vals_btn" class="btn btn-success show_vals_btn" onclick="fc_toggle_box_via_btn(jQuery(\'#sortables_outer_'.$field->id.'\'), this, \'\', jQuery(this).prev(), 1); return false;" style="display:none;">
					<i class="icon-downarrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_SHOW_VALUES' ).'
				</span>
			</div>
				' : '').'
			'.$field->html.
			($non_value_html ? '
				<div class="fcclear"></div>'.$non_value_html : '');
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Use custom HTML display parameter
		$display_mode = (int) $field->parameters->get( 'display_mode', 0 ) ;
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		// Microdata (classify the field group values for search engines)
		// we use itemtype and not itemprop as it is more appropriate for the a grouping field
		$fieldgroup_itemtype      = $field->parameters->get('fieldgroup_itemtype');
		$fieldgroup_itemtype_code = $fieldgroup_itemtype ? 'itemscope itemtype="http://schema.org/'.$fieldgroup_itemtype.'"' : '';
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		if (!$pretext && !$posttext && !$display_mode)
		{
			$pretext = '<div class="fc-fieldgrp-value-box">';
			$posttext = '</div>';
		}
		if ($fieldgroup_itemtype_code) {
			$pretext = '<div '.$fieldgroup_itemtype_code.' style="display:inline-block;">'.$pretext;
			$posttext = $posttext.'</div>';
		}
		
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
		
		
		// Get fields belonging to this field group
		$grouped_fields = $this->getGroupFields($field);
		
		// Get values of fields making sure that also empty values are created too
		$max_count = 0;
		$this->getGroupFieldsValues($field, $item, $grouped_fields, $max_count);
		
		
		// **********************************************
		// Create a CUSTOMIZED display of the field group
		// **********************************************
		
		if ( $display_mode )
		{
			$custom_html = trim($field->parameters->get( 'custom_html', '' )) ;
			$field->{$prop} = $this->_createDisplayHTML($field, $item, $grouped_fields, $custom_html, $max_count, $pretext, $posttext);
		}
		
		
		// *********************************************
		// Create the DEFAULT display of the field group
		// *********************************************
		
		else {
			// Render HTML of fields in the group
			$method = 'display';
			$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
			foreach($grouped_fields as $grouped_field) {

				// Render the display method for the given field
				$_values = $grouped_field->value;
				$grouped_field->ingroup = 1;  // render as array
				
				//echo 'Rendering: '. $grouped_field->name . ', method: ' . $method . '<br/>';
				//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayFieldValue', array(&$grouped_field, $item, $_values, $method));
				
				unset($grouped_field->$method);  // Unset display variable to make sure display HTML it is created, because we reuse the field
				FlexicontentFields::renderField($item, $grouped_field, $_values, $method, $view, $_skip_trigger_plgs = true);  // We will trigger only once the final result
				unset($grouped_field->ingroup);
			}
			
			// Get labels to hide on empty values
			$hide_lbl_ifnoval = $this->getHideLabelsOnEmpty($field);
			
			// Render the list of groups
			$field->{$prop} = array();
			for($n=0; $n < $max_count; $n++) {
				$default_html = array();
				foreach($grouped_fields as $grouped_field)
				{
					// Skip (hide) label for field without value (is such behaviour was configured)
					if ( (!isset($grouped_field->{$prop}[$n]) || !strlen($grouped_field->{$prop}[$n]))  &&  isset($hide_lbl_ifnoval[$grouped_field->id]) ) continue;
					
					// Add field's HTML (optionally including label)
					$_values = null;
					$default_html[] = '
					<div class="fc-field-box">
						'.($grouped_field->parameters->get('display_label') ? '
						<span class="flexi label">'.$grouped_field->label.'</span>' : '').
						(isset($grouped_field->{$prop}[$n]) ? '<div class="flexi value">'.$grouped_field->{$prop}[$n].'</div>' : '').'
					</div>';
				}
				if (count($default_html)) {
					$field->{$prop}[] = $pretext . implode('<div class="fcclear"></div>', $default_html).'<div class="fcclear"></div>' . $posttext;
				}
			}
			
			// Unset display of fields in case they need to be rendered again
			//foreach($grouped_fields as $grouped_field)  unset($grouped_field->$prop);
		}
		
		if (count($field->{$prop})) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
	
	
	
	// Helper method to create HTML display of an item list according to replacements
	private function _createDisplayHTML(&$field, &$item, &$grouped_fields, $custom_html, $max_count, $pretext, $posttext)
	{
		// ********************************
		// Parse and identify custom fields
		// ********************************
		//return array('"<b>Custom HTML</b>" display for fieldgroup field, is not implemented yet, please use default HTML');
		
		if (!$custom_html) return "Empty custom HTML variable for group field: ". $field->label;
		$result = preg_match_all("/\{\{([a-zA-Z_0-9]+)(##)?([a-zA-Z_0-9]+)?\}\}/", $custom_html, $field_matches);
		$gf_reps    = $result ? $field_matches[0] : array();
		$gf_names   = $result ? $field_matches[1] : array();
		$gf_methods = $result ? $field_matches[3] : array();
		
		//foreach ($gf_names as $i => $gf_name)
		//	$parsed_fields[] = $gf_names[$i] . ($gf_methods[$i] ? "->". $gf_methods[$i] : "");
		//echo "$custom_html :: Fields for Related Items List: ". implode(", ", $parsed_fields ? $parsed_fields : array() ) ."<br/>\n";
		$_name_to_field = array();
		foreach($grouped_fields as $i => $grouped_field) {
			$_name_to_field[$grouped_field->name] = & $grouped_fields[$i];
		}
		//print_r(array_keys($_name_to_field)); echo "<br/>";
		
		
		// ***********************************************************************
		// Parse and identify language strings and then make language replacements
		// ***********************************************************************
		
		$result = preg_match_all("/\%\%([^%]+)\%\%/", $custom_html, $translate_matches);
		$translate_strings = $result ? $translate_matches[1] : array('FLEXI_READ_MORE_ABOUT');
		foreach ($translate_strings as $translate_string)
			$custom_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $custom_html);
		
		
		// **************************************************************
		// Render HTML of grouped fields mentioned inside the custom HTML
		// **************************************************************
		
		$_rendered_fields = array();
		if ( count($gf_names) )
		{
			$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));;
			$gf_props = array();
			foreach($gf_names as $pos => $grp_field_name)
			{
				
				// Check that field exists and is assigned the fieldgroup field
				$grouped_field = $_name_to_field[$grp_field_name];
				if ( ! isset($_name_to_field[$grp_field_name]) ) continue;
				$_rendered_fields[$pos] = $grouped_field;
				
				// Check if display method is 'label' aka nothing to render
				if ( $gf_methods[$pos] == 'label' ) continue;
				
				// Optional use custom display method
				$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';
				
				// SAME field with SAME method, may have been used more than ONCE, inside the custom HTML parameter
				// Check if field has been rendered already
				if ( isset($grouped_field->{$method}) && is_array($grouped_field->{$method}) ) continue;
				
				// Render the display method for the given field
				$_values = $grouped_field->value;
				$grouped_field->ingroup = 1;  // render as array
				
				//echo 'Rendering: '. $grouped_field->name . ', method: ' . $method . '<br/>';
				//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayFieldValue', array(&$grouped_field, $item, $_values, $method));
				
				FlexicontentFields::renderField($item, $grouped_field, $_values, $method, $view, $_skip_trigger_plgs = true);  // We will trigger only once the final result
				//print_r($grouped_field->$method);
				$grouped_field->_method = $method;  // This is used to decide if field does not have value and hide label (if configured to hide on empty values)
				
				unset($grouped_field->ingroup);
			}
		}
		
		
		// *******************************************************************
		// Render the value list of the fieldgroup, using custom HTML for each
		// value-set of the fieldgroup, and performing the field replacements
		// *******************************************************************
		
		// Get labels to hide on empty values
		$hide_lbl_ifnoval = $this->getHideLabelsOnEmpty($field);
		
		$custom_display = array();
		//echo "<br/>max_count: ".$max_count."<br/>";
		for($n=0; $n < $max_count; $n++) {
			$rendered_html = $custom_html;
			foreach($_rendered_fields as $pos => $_rendered_field)
			{
				$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';
				//echo 'Replacing: '. $_rendered_field->name . ', method: ' . $method . ', index: ' .$n. '<br/>';
				if ($method!='label')
					$_html = isset($_rendered_field->{$method}[$n]) ? $_rendered_field->{$method}[$n] : '';
				else {
					$_method = isset($_rendered_field->_method) ? $_rendered_field->_method : 'display';
					
					if ( (!isset($_rendered_field->{$_method}[$n]) || !strlen($_rendered_field->{$_method}[$n]))  &&  isset($hide_lbl_ifnoval[$_rendered_field->id]) ) {
						$_html = ''; // Skip (hide) label for field without value (is such behaviour was configured)
					} else {
						$_html = $_rendered_field->label;
					}
				}
				$rendered_html = str_replace($gf_reps[$pos], $_html, $rendered_html);
			}
			$custom_display[$n] = $pretext . $rendered_html . $posttext;
		}
		
		// IMPORTANT FIELD IS REUSED, !! unset display methods since it maybe rendered again for different item
		foreach($_rendered_fields as $pos => $_rendered_field) {
			unset($_rendered_field->$method);
		}
		
		return $custom_display;
	}
	

	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		//FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		//FLEXIUtilities::call_FC_Field_Func('text', 'onAfterSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		//FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeDeleteField', array(&$field, &$item));
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Retrieves the fields that are part of the given 'fieldgroup' field
	function getGroupFields(&$field)
	{
		static $grouped_fields = array();
		if (isset($grouped_fields[$field->id])) return $grouped_fields[$field->id];
		
		$fieldids = $field->parameters->get('fields', array());
		if ( empty($fieldids) ) {
			$fieldids = array();
		}
		if ( !is_array($fieldids) ) {
			$fieldids = preg_split("/[\|,]/", $fieldids);
		}
		
		if ( empty($fieldids) ) {  // No assigned fields
			return $grouped_fields[$field->id] = array();  
		}
		
		$db = JFactory::getDBO();
		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.published = 1'
			. ' AND f.id IN ('.implode(',',$fieldids).')'
			. ' ORDER BY FIELD(f.id, '.implode(',',$fieldids).')'
			;
		$db->setQuery($query);
		$grouped_fields[$field->id] = $db->loadObjectList('id');
		
		$_grouped_fields = array();
		foreach($grouped_fields[$field->id] as $field_id => $grouped_field)
		{
			// Create field parameters, if not already created, NOTE: for 'custom' fields loadFieldConfig() is optional
			if (empty($grouped_field->parameters)) {
				$grouped_field->parameters = new JRegistry($grouped_field->attribs);
			}
			
			// Check if field is not set to participate in a field group and skip it
			if ( !$grouped_field->parameters->get('use_ingroup') ) continue;
			$_grouped_fields[$field_id] = $grouped_field;
		}
		$grouped_fields[$field->id] = $_grouped_fields;
		
		return $grouped_fields[$field->id];
	}
	
	
	// Retrieves and add values to the given field objects
	function getGroupFieldsValues(&$field, &$item, &$grouped_fields, &$max_count)
	{
		$do_compact = true;
		
		// ****************
		// Get field values
		// ****************
		$max_index = 0;
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			// Item viewing
			if ( isset($item->fieldvalues[$field_id]) ) {
				$grouped_field->value = is_array($item->fieldvalues[$field_id])  ?  $item->fieldvalues[$field_id]  :  array($item->fieldvalues[$field_id]);
			}
			// Item form
			else if ( isset($item->fields[$grouped_field->name]->value) ) {
				$grouped_field->value = $item->fields[$grouped_field->name]->value;
			}
			// Value not set
			else {
				$grouped_field->value = null;
			}
			
			// Update max value index
			$last_index = !is_array($grouped_field->value) || !count($grouped_field->value) ? 0 : max(array_keys($grouped_field->value));
			$max_index = $last_index > $max_index ? $last_index : $max_index;
		}
		//echo "<br/><br/><br/>DB DATA<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";
		
		
		// ***********************************************************************************
		// (Compatibility) For groups that have fields with non-set values, add NULL values
		// This way the field will not skip the value and instead will create an empty display
		// ***********************************************************************************
		$null_count = array();
		for ($n=0; $n <= $max_index; $n++) $null_count[$n] = 0;
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			$vals = array();
			for ($n=0; $n <= $max_index; $n++) {
				if ( isset($grouped_field->value[$n]) )
				{
					$vals[$n] = $grouped_field->value[$n];
				} else {
					$vals[$n] = null;
					++$null_count[$n];
				}
			}
			$grouped_field->value = $vals;
		}
		//echo "<br/><br/><br/>NULLED<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";
		//echo "<pre>"; print_r($null_count); echo "</pre>";
		
		
		// *********************************
		// Find groups that had empty values
		// *********************************
		$grp_isempty = array();
		for($n=0; $n <= $max_index; $n++) {
			if ( isset($null_count[$n]) && $null_count[$n]==count($grouped_fields) )  $grp_isempty[$n] = 1;
		}
		//print_r($grp_isempty); exit;
		
		
		// *************************************************************************
		// Compact FIELD GROUP values by removing groups that are (ALL values) empty
		// *************************************************************************
		
		// Make sure we have some empty fieldgroups, if this was requested (= that is the max_count that was passed to the function)
		$start_at = $max_count + count($grp_isempty) - ($max_index+1);
		if ($start_at < 0) $start_at = 0;
		
		if ($do_compact) foreach($grouped_fields as $field_id => $grouped_field)
		{
			$i = $start_at;
			for ($n = $start_at; $n <= $max_index; $n++)
			{
				//echo $n." - ".$i."<br/>";
				// Move down to fill empty gaps, if current index is not in sync, meaning 1 empty group was encountered -before-, and also if current (value) group is non-empty
				if ( $n > $i && !isset($grp_isempty[$n]) )
				{
					$grouped_field->value[$i] = $grouped_field->value[$n];
					if ( isset($grouped_field->value[$n]) )
					{
						if ( isset($item->fieldvalues[$field_id]) )               $item->fieldvalues[$field_id][$i] = $grouped_field->value[$n];
						if ( isset($item->fields[$grouped_field->name]->value) )  $item->fields[$grouped_field->name]->value[$i] = $grouped_field->value[$n];
					}
				}
				
				// Unset moved groups or group with ALL-empty values
				if ( $n > $i || isset($grp_isempty[$n]) )
				{
					unset($grouped_field->value[$n]);
					if ( isset($item->fieldvalues[$field_id]) ) unset($item->fieldvalues[$field_id][$n]);
					if ( isset($item->fields[$grouped_field->name]->value) ) unset($item->fields[$grouped_field->name]->value[$n]);
				}
				
				// Increment adding position if group was not empty
				if ( !isset($grp_isempty[$n]) ) $i++;
			}
		}
		//echo "<br/><br/><br/>COMPACTED<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";
		
		$max_count = $max_index + 1;
		if ($do_compact) $max_count -= (count($grp_isempty) - $start_at);
		//echo $field->label.": max_count = $max_count <br/>";
	}
	
	
	// Return the fields (ids) that will hide their labels if they have no value
	function getHideLabelsOnEmpty(&$field)
	{
		static $hide_lbl_ifnoval_arr = array();
		if (isset($hide_lbl_ifnoval_arr[$field->id])) return $hide_lbl_ifnoval_arr[$field->id];
		
		$hide_lbl_ifnoval = $field->parameters->get('hide_lbl_ifnoval', array());
		if ( empty($hide_lbl_ifnoval) )  $hide_lbl_ifnoval = array();
		if ( !is_array($hide_lbl_ifnoval) )  $hide_lbl_ifnoval = preg_split("/[\|,]/", $hide_lbl_ifnoval);
		$hide_lbl_ifnoval_arr[$field->id] = array_flip($hide_lbl_ifnoval);
		
		return $hide_lbl_ifnoval_arr[$field->id];
	}
	
}