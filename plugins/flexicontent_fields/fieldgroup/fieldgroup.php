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

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsFieldgroup extends JPlugin
{
	static $field_types = array('fieldgroup');
	static $extra_props = array();
	static $prior_to_version = "3.1";
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsFieldgroup( &$subject, $params )
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
		
		// Check max allowed version
		$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'manifest.xml';
		$com_xml = JApplicationHelper::parseXMLInstallFile( $manifest_path );
		if (version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', self::$prior_to_version), '>=')) {
			echo '
			<span class="fc-note fc-nobgimage fc-mssg">
				Warning: installed version of Field: \'<b>'.$field->field_type.'</b>\' was meant for FLEXIcontent versions prior to: v'.self::$prior_to_version.' <br/> It may or may not work properly in later versions<br/>
			</span>';
		}
		
		$field->label = JText::_($field->label);
		$use_ingroup = 0; // Field grouped should not be recursively grouped
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$db = JFactory::getDBO();
		$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
		
		
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
		$grpfields = $this->getGroupFields($field);
		
		// Get values of fields making sure that also empty values are created too
		$max_count = 1;
		$this->getGroupFieldsValues($grpfields, $item, $max_count);
		
		// Render Form HTML of the field
		foreach($grpfields as $field_id => $grpfield)
		{
			for($n=count($grpfield->value); $n < $max_count; $n++) {
				$grpfield->value[$n] = null;
			}
			$grpfield->ingroup = 1;
			$grpfield->item_id = $item->id;
			FLEXIUtilities::call_FC_Field_Func($grpfield->field_type, 'onDisplayField', array(&$grpfield, &$item));
			unset($grpfield->ingroup);
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
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
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
				newSubLabel.attr('for', newLabelFor);
				newSubLabel.attr('for_bck', newLabelFor);
				fcflabels[ newLabelFor ] = newSubLabel;
				fcflabels_errcnt[ newLabelFor ] = 0;
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
					newSubLabel.attr('for_bck', newLabelFor);
					fcflabels[ newLabelFor ] = newSubLabel;
					fcflabels_errcnt[ newLabelFor ] = 0;
				}
				deleteField_GRP_FID_(null, groupval_box, groupval_box.find('.fcfieldval_container__GRP_FID_'));
				";
			$addField_funcs = $delField_funcs = '';
			foreach($grpfields as $field_id => $grpfield) {
				$addField_funcs .= str_replace("_GRP_FID_",  $grpfield->id,  sprintf($addField_pattern, $grpfield->name)  );
				$delField_funcs .= str_replace("_GRP_FID_",  $grpfield->id,  sprintf($delField_pattern, $grpfield->name)  );
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
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
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
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				
				// Add new values for each field
				var groupval_box = newField;
				var add_params = {remove_previous: 1, scroll_visible: 0, animate_visible: 0};
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
				".( FLEXI_J30GE ? "
					newField.find('.hasTooltip').tooltip({'html': true,'container': newField});
				" : "
					var tipped_elements = newField.find('.hasTip');
					tipped_elements.each(function() {
						var title = this.get('title');
						if (title) {
							var parts = title.split('::', 2);
							this.store('tip:title', parts[0]);
							this.store('tip:text', parts[1]);
						}
					});
					var ajax_JTooltips = new Tips($('#sortables_".$field->id."').getNext().getElements('.hasTip'), { maxTitleChars: 50, fixed: false});
				")."
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el)
			{
				var row = jQuery(el).closest('li');
				
				// Do cleanup by calling the deleteField of each individual field, these functions will re-add last element as empty if needed
				var groupval_box = jQuery(el).closest('li');
				".$delField_funcs."
				if(rowCount".$field->id." == 1)
				{
					uniqueRowNum".$field->id."++;   // increment unique row id, since last group was re-added
				}
				
				// Also remove the group field values container if not last one
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.fadeOut(420, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '';
			
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
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
		$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
		
		$field->html = array();
		for($n = 0; $n < $max_count; $n++)
		{
			$field->html[$n] = '
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				';
			
			// Append item-form display HTML of the every field in the group
			foreach($grpfields as $field_id => $grpfield) {
				$lbl_class = 'flexi label sub_label';
				$lbl_title = '';
				// field has tooltip
				$edithelp = $grpfield->edithelp ? $grpfield->edithelp : 1;
				if ( $grpfield->description && ($edithelp==1 || $edithelp==2) ) {
					 $lbl_class .= ($edithelp==2 ? ' fc_tooltip_icon ' : ' ') .$tooltip_class;
					 $lbl_title = flexicontent_html::getToolTip(trim($field->label, ':'), $grpfield->description, 0, 1);
				}
				
				$field->html[$n] .= '<div class="fcclear"></div>'
				.'<label class="'.$lbl_class.'" title="'.$lbl_title.'" for="custom_'.$grpfield->name.'_'.$n.'" for_bck="custom_'.$grpfield->name.'_'.$n.'">'.$grpfield->label.'</label>'
				.'<div class="fcfieldval_container valuebox fcfieldval_container_'.$grpfield->id.' container_fcfield_name_'.$grpfield->name.'" >'
					.($grpfield->description && $edithelp==3 ? sprintf( $alert_box, '', 'info', 'fc-nobgimage', $grpfield->description ) : '')
					.@ $grpfield->html[$n]
				.'</div>
				';
			}
			
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		// Non value HTML
		$non_value_html = '';
		foreach($grpfields as $field_id => $grpfield) {
			$non_value_html .= @$grpfield->html[-1];
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
			$field->html = '<ul class="'.$list_classes.'" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
		} else {
			$field->html = '';
		}
		if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		
		// Append non value html of fields
		$field->html .= '<div class="fcclear"></div>' . $non_value_html;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Get fields belonging to this field group
		$grpfields = $this->getGroupFields($field);
		
		// Get values of fields making sure that also empty values are created too
		$max_count = 0;
		$this->getGroupFieldsValues($grpfields, $item, $max_count);
		
		// Render fields display
		foreach($grpfields as $grpfield) {
			$_values = null;
			$grpfield->ingroup = 1;
			FLEXIUtilities::call_FC_Field_Func($grpfield->field_type, 'onDisplayFieldValue', array(&$grpfield, $item, $_values, $prop));
			unset($grpfield->ingroup);
		}
		
		// Create a basic display for the field group
		$method = array();
		for($n=0; $n < $max_count; $n++) {
			foreach($grpfields as $grpfield) {
				$_values = null;
				$method[] = '
					<span class="flexi label">'.$grpfield->label.'</span>
						'.(isset($grpfield->{$prop}[$n]) ? $grpfield->{$prop}[$n] : '').'
					<div class="clear"></div>
				';
			}
		}
		foreach($grpfields as $grpfield) {
			unset($grpfield->$prop);
		}
		
		$field->$prop = implode("<div class='clear'></div>", $method);
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
	
	
	function getGroupFields(&$field)
	{
		static $grpfields = array();
		if (isset($grpfields[$field->id])) return $grpfields[$field->id];
		
		$fieldids = $field->parameters->get('fields', array());
		if ( empty($fieldids) ) {
			$fieldids = array();
		}
		if ( !is_array($fieldids) ) {
			$fieldids = preg_split("/[\|,]/", $fieldids);
		}
		
		$db = JFactory::getDBO();
		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.published = 1'
			. ' AND f.id IN ('.implode(',',$fieldids).')'
			. ' ORDER BY FIELD(f.id, '.implode(',',$fieldids).')'
			;
		$db->setQuery($query);
		$grpfields[$field->id] = $db->loadObjectList('id');
		
		return $grpfields[$field->id];
	}
	
	
	function getGroupFieldsValues(&$grpfields, &$item, &$max_count)
	{
		if (!isset($item->fieldvalues)) {
			$itemmodel = new FlexicontentModelItem();
			$item->fieldvalues = $itemmodel->getCustomFieldsValues($item->id);
		}
		
		$_grpfields = array();
		foreach($grpfields as $field_id => $grpfield)
		{
			// Set field values
			if ( isset($item->fieldvalues[$field_id]) ) {
				$grpfield->value = is_array($item->fieldvalues[$field_id])  ?  $item->fieldvalues[$field_id]  :  array($item->fieldvalues[$field_id]);
			} else {
				$grpfield->value = null;
			}
			
			// Update max value count
			$value_count = is_array($grpfield->value) ? count($grpfield->value) : 0;
			$max_count = $value_count > $max_count ? $value_count : $max_count;
			
			// Create field parameters (for 'custom' fields loadFieldConfig() is optional)
			$grpfield->parameters = new JRegistry($grpfield->attribs);
			
			// Check if field is set to participate in a group
			if ( $grpfield->parameters->get('use_ingroup') ) $_grpfields[] = $grpfield;
		}
		$grpfields = $_grpfields;
		
		// Prepare empty values for the field
		foreach($grpfields as $field_id => $grpfield)
		{
			for($n=count($grpfield->value); $n < $max_count; $n++) {
				$grpfield->value[$n] = null;
			}
		}
	}
	
}