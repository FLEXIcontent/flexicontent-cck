<?php
/**
 * @version 1.0 $Id: checkboximage.php 1629 2013-01-19 08:45:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkboximage
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

class plgFlexicontent_fieldsCheckboximage extends JPlugin
{
	static $field_types = array('checkboximage');
	static $extra_props = array('image');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCheckboximage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkboximage', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = 0;  // Not supported
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || 0; //(int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$min_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'min_values', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		// Sanitize limitations
		$exact_values	= $field->parameters->get( 'exact_values', 0 ) ;
		if ($exact_values) $max_values = $min_values = $exact_values;
		$js_popup_err	= $field->parameters->get( 'js_popup_err', 0 ) ;
		
		
		// **************
		// Value handling
		// **************
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_values = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_values', '' ) : '';
		
		
		// *************************
		// Input field configuration
		// *************************
		
		// DISPLAY using prettyCheckable JS
		$use_jslib = $field->parameters->get( 'use_jslib', 2 ) ;
		$use_prettycheckable = $use_jslib==2;
		static $prettycheckable_added = null;
	  if ( $use_prettycheckable && $prettycheckable_added === null ) $prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		
		// when field is displayed as drop-down select (item edit form only)
		$firstoptiontext = $field->parameters->get( 'firstoptiontext', 'FLEXI_SELECT' ) ;
		$usefirstoption  = $field->parameters->get( 'usefirstoption', 1 ) ;
		$size = $field->parameters->get( 'size', 6 ) ;
		$size = $size ? ' size="'.$size.'"' : '';
		
		// image specific variables
		$form_vals_display = $field->parameters->get( 'form_vals_display', 1 ) ;  // this field includes image but it can be more convenient/compact not to be display image in item form
		$imagedir = preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath  = JURI::root(true) .'/'. $imagedir;
		$imgfolder = JPATH_SITE .DS. $imagedir;
		
		// Prefix - Suffix - Separator (item FORM) parameters, for the checkbox/radio elements
		$pretext			= $field->parameters->get( 'pretext_form', '' ) ;
		$posttext			= $field->parameters->get( 'posttext_form', '' ) ;
		$separator		= $field->parameters->get( 'separator', 0 ) ;
		$opentag			= $field->parameters->get( 'opentag_form', '' ) ;
		$closetag			= $field->parameters->get( 'closetag_form', '' ) ;
		
		switch($separator)
		{
			case 0:
			$separator = '&nbsp;';
			break;

			case 1:
			$separator = '<br />';
			break;

			case 2:
			$separator = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separator = ',&nbsp;';
			break;

			case 4:
			$separator = $closetag . $opentag;
			break;

			default:
			$separator = '&nbsp;';
			break;
		}
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = strlen($default_values) ? explode(",", $default_values) : array('');
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']'.'[]';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
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
				var exec_prep_clean = (typeof params!== 'undefined' && typeof params.exec_prep_clean !== 'undefined') ? params.exec_prep_clean : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				
				if ( exec_prep_clean )  beforeAddField".$field->id."(fieldval_box);  // not in Group
				var newField  = lastField.clone();
				if ( exec_prep_clean )   afterAddField".$field->id."(fieldval_box);  // not in Group
				
				// Update the new checkboxes
				var theSet = newField.find('input:checkbox');
				//if(window.console) window.console.log('theSet.length: ' + theSet.length);
				var nr = 0;
				theSet.each(function() {
					var elem = jQuery(this);
					elem.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']');
					elem.attr('id', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);
					".($use_prettycheckable && $prettycheckable_added ?
						"elem.attr('data-element-grpid', '".$elementid."_'+uniqueRowNum".$field->id.");" :
						"elem.attr('data-element-grpid', '".$elementid."_'+uniqueRowNum".$field->id.");" )."
					".($use_prettycheckable && $prettycheckable_added ?
						"elem.prev('label').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);" :
						"elem.next('label').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);" )."
					nr++;
				});
				
				// Add prettyCheckable to new radio set (if having appropriate CSS class)
				newField.find('.use_prettycheckable').each(function() {
					var elem = jQuery(this);
					var lbl = elem.prev('label');
					var lbl_html = lbl.html();
					lbl.remove();
					elem.prettyCheckable({ label: lbl_html });
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
			
			function beforeAddField".$field->id."(fieldval_box) {
				// Remove prettyCheckable before cloning (if having appropriate CSS class)
				fieldval_box.find('input.use_prettycheckable:checkbox').each(function() { jQuery(this).prettyCheckable('destroy'); });
			}
			function afterAddField".$field->id."(fieldval_box) {
				// Re-add prettyCheckable after cloning (if having appropriate CSS class)
				fieldval_box.find('.use_prettycheckable').each(function() {
					var elem = jQuery(this);
					var lbl_html = elem.prev('label').html();
					elem.prev('label').remove();
					elem.prettyCheckable({ label: lbl_html });
				});
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
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
		if ( !$elements ) {
			if ($sql_mode)
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		// Display as (multiple) select
		if ( $field->parameters->get( 'display_as_select', 0 ) ) {
			$options = array();
			if ($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
			foreach ($elements as $element) {
				$options[] = JHTML::_('select.option', $element->value, $element->text);
			}
			$field->html	= JHTML::_('select.genericlist', $options, $fieldname, 'multiple="multiple" class="'.$required.'"'.$size, 'value', 'text', $field->value, $elementid);
			return;
		} // else ...
		
		// Display as checkboxes
		$display_as_checkbox = 1;
		if ($display_as_checkbox) {
			$attribs  = '';
			$classes  = $use_prettycheckable && $prettycheckable_added ? ' use_prettycheckable ' : '';
			$classes .= $required;
			$onchange = "";
			if ($exact_values)  {
				$attribs .= ' exact_values="'.$exact_values.'" ';
			} else {
				if ($max_values)    $attribs .= ' max_values="'.$max_values.'" ';
				if ($min_values)    $attribs .= ' min_values="'.$min_values.'" ';
			}
			if ($js_popup_err)  $attribs .= ' js_popup_err="'.$js_popup_err.'" ';
			if ($max_values || $min_values || $exact_values)  $classes .= ' validate-cboxlimitations ';
			else if ($required) $classes .= ' validate-checkbox ';  // No min/max/exact values allow basic 'required' validation
			if ($classes)  $attribs .= ' class="'.$classes.'" ';
			if ($onchange) $attribs .= ' onchange="'.$onchange.'" ';
		}
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		// Create form field options
		$i = 0;
		$options = array();
		foreach ($elements as $element) {
			$checked  = in_array($element->value, $field->value)  ?  ' checked="checked"'  :  '';
			$elementid_no = $elementid.'_'.$i;
			$extra_params = $use_prettycheckable && $prettycheckable_added ? ' data-customClass="fcradiocheckimage"' : '';
			$input_fld = ' <input type="checkbox" id="'.$elementid_no.'" data-element-grpid="'.$elementid.'" name="'.$fieldname.'" '.$attribs.' value="'.$element->value.'" '.$checked.$extra_params.' />';
			$img_exists = file_exists($imgfolder . $element->image);
			$options[] = ''
				.($use_prettycheckable && $prettycheckable_added ? $input_fld : '')
				.'<label for="'.$elementid_no.'" class="'.(FLEXI_J30GE ? 'hasTooltip' : 'hasTip').' fccheckradio_lbl" title="'.flexicontent_html::getToolTip(null, $element->text, 0, 1).'" >'
				. (!$use_prettycheckable || !$prettycheckable_added ? $input_fld : '')
				.($form_vals_display!=1 ? $element->text : '')
				.($form_vals_display==2 ? ' <br/>' : '')
				.($form_vals_display >0 ? ($img_exists ? ' <img src="'.$imgpath . $element->image .'"  alt="'.$element->text.'" />' : '[NOT found]: '. $imgpath . $element->image) : '')
				.'</label>'
				;
			$i++;
		}
		
		// Apply values separator
		$field->html = implode($separator, $options);
		
		// Apply field 's opening / closing texts
		$field->html = $field->html ? array($opentag . $field->html . $closetag) : array('');
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
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
		
		// Add message box about allowed # values
		if ($exact_values) {
			$field->html = '<div class="fc_mini_note_box">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_EXACTLY', $exact_values) .'</div><div class="clear"></div>'. $field->html;
		} else if ($max_values || $min_values > 1) {
			$field->html = '<div class="fc_mini_note_box">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_BETWEEN', $min_values, $max_values) .'</div><div class="clear"></div>'. $field->html;
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$use_ingroup = 0;  // Not supported
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
		$text_or_value  = $field->parameters->get( 'text_or_value', 2 ) ;
		
		// image specific variables
		$imagedir = preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath  = JURI::root(true) .'/'. $imagedir;
		
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
		
		$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
		
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
			else $disp = '<img src="'.$imgpath . $element->image .'" class="'.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />';
			
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
			else $disp = '<img src="'.$imgpath . $element->image .'" class="'.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />';
			
			$field->{$prop}[] = $pretext . $disp . $posttext;
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
		
		$use_ingroup = 0;  // Not supported
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
		
		self::onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		// Get indexed element values
		$item_pros = false;
		$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, self::$extra_props, $item_pros, $create_filter=true);
		
		$_s = $isSearchView ? '_s' : '';
		$filter_vals_display = $filter->parameters->get( 'filter_vals_display'.$_s, 0 );
		$filter_as_images = in_array($filter_vals_display, array(1,2)) ;
		if ($filter_as_images && $elements)
		{
			// image specific variables
			$imagedir = preg_replace('#^(/)*#', '', $filter->parameters->get( 'imagedir' ) );
			$imgpath  = JURI::root(true) .'/'. $imagedir;
			foreach($elements as $element) {
				$element->image_url = $imgpath . $element->image;
			}
		}
		
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
