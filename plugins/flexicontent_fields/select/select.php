<?php
/**
 * @version 1.0 $Id: select.php 1629 2013-01-19 08:45:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.select
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

class plgFlexicontent_fieldsSelect extends JPlugin
{
	var $task_callable = array('getCascadedField');
	
	static $field_types = array('select');
	static $extra_props = array();
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsSelect( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item, $ajax=0)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$cascade_onfield = (int)$field->parameters->get('cascade_onfield', 0);
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $cascade_onfield ? 0 : $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// **************
		// Value handling
		// **************
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		
		// *************************
		// Input field configuration
		// *************************
		
		// DISPLAY using select2 JS
		$use_jslib = $field->parameters->get( 'use_jslib', 1 ) ;
		$use_select2 = $use_jslib==1;
		static $select2_added = null;
	  if ( $use_select2 && $select2_added === null ) $select2_added = flexicontent_html::loadFramework('select2');
		
		// Parameters for DISPLAY with / without using select2 JS
		$firstoptiontext = $field->parameters->get( 'firstoptiontext', 'FLEXI_SELECT' ) ;
		$usefirstoption  = $field->parameters->get( 'usefirstoption', 1 ) ;
		
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0] = $default_value;
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		
		// *********************************************************************************************
		// Handle adding the needed JS code to CASCADE (listen to) changes of the dependent master field
		// *********************************************************************************************
		
		if ($cascade_onfield && !$ajax)
		{
			$byIds = FlexicontentFields::indexFieldsByIds($item->fields);
			
			if ( isset($byIds[$cascade_onfield]) )
			{
				$cascade_prompt = $field->parameters->get('cascade_prompt', '');
				$cascade_prompt = $cascade_prompt ? JText::_($cascade_prompt) : JText::_('FLEXI_PLEASE_SELECT').': '.$byIds[$cascade_onfield]->label;
				
				$srcELid = 'custom_'.$byIds[$cascade_onfield]->name;
				$trgELid = $elementid;
				
				// Get values of cascade (on) source field
				$field->valgrps = $byIds[$cascade_onfield]->value ? $byIds[$cascade_onfield]->value : null;
				
				// Create a cascaded function that can be called later
				$js .= "
				jQuery(document).ready(function(){
					fcCascadedField(
						".$field->id.", '".$field->item_id."', '".$field->field_type."',
						'".$srcELid."', '".$trgELid."', '".$cascade_prompt."',
						1
					);
				});
				";
			} else {
				$cascade_prompt = 'Error field no: '.$cascade_onfield.' not found';
			}
		}
		
		
		// ***********************
		// Handle multiple records
		// ***********************
		
		if ($multiple && !$ajax)
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .="
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
				
				// Update the new select field
				var theSelect= newField.find('select.fcfield_textselval').first();
				theSelect.val('');
				theSelect.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']');
				theSelect.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");
				theSelect.attr('data-uniqueRowNum', uniqueRowNum);
				
				// Destroy any select2 elements
				var has_select2 = newField.find('div.select2-container').length != 0;
				if (has_select2) {
					newField.find('div.select2-container').remove();
					newField.find('select.use_select2_lib').select2('destroy').show().select2();
				}
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
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
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
		
		
		// Added field's custom CSS / JS
		if (!$ajax && $js)  $document->addScriptDeclaration($js);
		if (!$ajax && $css) $document->addStyleDeclaration($css);
		
		
		// **************************
		// Get indexed element values
		// **************************
		
		if ($sql_mode)  // SQL query mode
		{
			$and_clause = '';
			if (isset($field->valgrps))
			{
				// Filter out values not in the the value group
				$db = JFactory::getDBO();
				$_valgrps = explode(',', $field->valgrps);
				foreach($_valgrps as & $vg) $vg = $db->Quote($vg);
				unset($vg);
				$and_clause = ' AND valgroup IN ('.implode(',', $_valgrps).')';
			}
			$item_pros = true;
			$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props, $item_pros, false, $and_clause);
			if ( !$elements ) {
				$field->html = $ajax ? '<option selected="selected" value="" disabled="disabled">No data found</option>' : JText::_('FLEXI_FIELD_INVALID_QUERY');
				return;
			}
		}
		
		else  // Elements mode
		{
			$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
			if ( !$elements ) {
				$field->html = $ajax ? '<option selected="selected" value="" disabled="disabled">No data found</option>' : JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
				return;
			}
			if (isset($field->valgrps))
			{
				// Filter out values not in the the value group
				$_valgrps = is_array($field->valgrps) ? $field->valgrps : explode(',', $field->valgrps);
				$_valgrps = array_flip($_valgrps);
				$_elements = array();
				foreach($elements as $element)
					if (isset($_valgrps[$element->valgroup]))  $_elements[$element->value] = $element;
				$elements = $_elements;
			}
		}
		
		
		// Display as (single) select
		$display_as_select = 1;
		if ($display_as_select) {
			$attribs  = '';
			$classes  = 'fcfield_textselval' . ($use_jslib && $select2_added ? ' use_select2_lib' : '');
			$classes .= $required;
			$onchange = "";
			if ($classes)  $attribs .= ' class="'.$classes.'" ';
			if ($onchange) $attribs .= ' onchange="'.$onchange.'" ';
		}
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		// Create form field options
		$options = array();
		
		// CASE 1: Either add the field options (non-cascaded field or AJAX request (cascade)
		if ($cascade_onfield || !$cascade_onfield || $ajax) {
			// Add the select prompt internally regardless of using JS
			if ($usefirstoption) {
				$options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
			}
			foreach ($elements as $element) $options[] = JHTML::_('select.option', $element->value, $element->text);
			$_msg = '';
		}
		
		// CASE 2: Or add cascade prompt, asking user to select value on the depend-from field
		else {
			//$options[] = JHTML::_('select.option', '', $cascade_prompt);
			$_msg = $cascade_prompt;
		}
		
		
		// Render the drop down select
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value)
		{
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			if (!$ajax)
			{
				$fieldname_n = !$multiple ? $fieldname : $fieldname.'['.$n.']';
				$elementid_n = !$multiple ? $elementid : $elementid.'_'.$n;
				$select_field = JHTML::_('select.genericlist', $options, $fieldname_n, $attribs.' data-uniqueRowNum="'.$n.'"', 'value', 'text', $value, $elementid_n);
				
				$field->html[] = '
					'.$select_field.($cascade_onfield ? '<span class="field_cascade_loading"></span>' : '').'
					'.($use_ingroup ? '' : $move2).'
					'.($use_ingroup ? '' : $remove_button).'
					'.($use_ingroup || !$add_position ? '' : $add_here).'
					';
			} else {
				$field->html = JHTML::_('select.options', $options, 'value', 'text', $value, $translate = false);
			}
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($ajax) {
			return; // Done
		} else if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method called via AJAX to get dependent values
	function getCascadedField()
	{
		$field_id = JRequest::getInt('field_id', 0);
		$item_id  = JRequest::getInt('item_id', 0);
		$valgrps  = JRequest::getVar('valgrps', '');
		
		// Load field
		$_fields = FlexicontentFields::getFieldsByIds(array($field_id), array($item_id));
		$field = $_fields[$field_id];
		$field->item_id = $item_id;
		$field->valgrps = $valgrps;
		
		// Load item
		$item = JTable::getInstance( $_type = 'flexicontent_items', $_prefix = '', $_config = array() );
		$item->load( $field->item_id );
		
		// Get field configuration
		FlexicontentFields::loadFieldConfig($field, $item);
		
		// Get field values
		$_fieldvalues = FlexicontentFields::getFieldValsById(array($field_id), array($item_id));
		$field->value = !empty($_fieldvalues[$item_id][$field_id]) ? $_fieldvalues[$item_id][$field_id] : array();
		
		// Render field
		$this->onDisplayField($field, $item, 1);
		
		// Output the field
		echo $field->html;
		exit;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$add_enclosers = !$use_ingroup || $field->parameters->get('add_enclosers_ingroup', 0);
		$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Check for no values and not displaying ALL elements
    $display_all = $field->parameters->get( 'display_all', 0 ) ;
		if ( empty($values) && !$display_all ) { $field->{$prop} = ''; $field->display_index = ''; return; }
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// Value creation
		$sql_mode = $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		$text_or_value  = $field->parameters->get( 'text_or_value', 1 ) ;
		
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
		
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
		if ( !$elements ) {
			if ($sql_mode)
				$field->{$prop} = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->{$prop} = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		// Check for no elements found
		if ( empty($elements) )  { $field->{$prop} = ''; $field->display_index = ''; return; }
		
		// Create display of field
		$field->{$prop} = array();
		$display_index = array();
		
		// Prepare for looping
		if ( !$values ) $values = array();
		if ( $display_all ) {
			$indexes = array_flip($values);
			
			// non-selected value shortcuts
	    $ns_pretext			= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_pretext', '' ), 'ns_pretext' );
  	  $ns_posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_posttext', '' ), 'ns_posttext' );
  	  $ns_pretext  = $ns_pretext . '<span class="fc_field_unsused_val">';
  	  $ns_posttext = '</span>' . $ns_posttext;
    	$ns_pretext  = $remove_space ? $ns_pretext : $ns_pretext . ' ';
	    $ns_posttext = $remove_space ? $ns_posttext : ' ' . $ns_posttext;
		}
		
		// CASE a. Display ALL elements (selected and NON-selected).  NOTE: not supported if field in field group
		if ( $display_all && !$use_ingroup ) foreach ($elements as $val => $element)
		{
			if ($text_or_value == 0) $disp = $element->value;
			else if ($text_or_value == 1) $disp =$element->text;
			
			$is_selected = isset($indexes[$val]);
			
			$field->{$prop}[] = $is_selected ?  $pretext.$disp.$posttext : $ns_pretext.$disp.$ns_posttext;
			if ( $is_selected ) $display_index[] = $element->value;
		}
		
		// CASE b. Display only selected elements. NOTE: This is forced if field is in field group
		else foreach ($values as $n => $val)
		{
			// Skip empty/invalid values but add empty display, if in field group
			$element = !strlen($val) ? false : @$elements[ $val ];
			if ( !$element ) {
				if ( $use_ingroup ) $field->{$prop}[$n]	= '';
				continue;
			}
			
			if ($text_or_value == 0) $disp = $element->value;
			else if ($text_or_value == 1) $disp =$element->text;
			
			$field->{$prop}[] = !$add_enclosers ? $disp : $pretext . $disp . $posttext;
			$display_index[] = $element->value;
		}
		
		if (!$use_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});
			$field->display_index = implode($separatorf, $display_index);
			
			// Apply field 's opening / closing texts
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
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
		
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
		foreach ($post as $n => $v)
		{
			// Do server-side validation and skip empty/invalid values
			$element = !strlen($post[$n]) ? false : @$elements[ $post[$n] ];
			if ( !$element ) {
				$post[$n] = '';  // clear invalid value
				if (!$use_ingroup) continue;
			}
			// max values limitation
			if ($max_values && $n > $max_values) continue;
			
			$newpost[$new] = array();
			$newpost[$new] = $post[$n];
			$new++;
		}
		$post = $newpost;
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
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
		
		self::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		// Get indexed element values
		$item_pros = false;
		$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, self::$extra_props, $item_pros, $create_filter=true);
		
		// Check for error during getting indexed field elements
		if ( !$elements ) {
			$filter->html = '';
			$sql_mode = $filter->parameters->get( 'sql_mode', 0 );  // must retrieve variable here, and not before retrieving elements !
			if ($sql_mode && $item_pros > 0)
				$filter->html = sprintf( JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $filter->label );
			else if ($sql_mode)
				$filter->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$filter->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		FlexicontentFields::createFilter($filter, $value, $formName, $elements);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->isindexed = true;
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		$field->isindexed = true;
		$field->extra_props = self::$extra_props;
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		$field->isindexed = true;
		$field->extra_props = self::$extra_props;
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
}
