<?php
/**
 * @version 1.0 $Id: checkbox.php 1629 2013-01-19 08:45:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkbox
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

class plgFlexicontent_fieldsCheckbox extends JPlugin
{
	var $task_callable = array('getCascadedField');
	
	static $field_types = array('checkbox');
	static $extra_props = array();
	static $valueIsArr = 1;
	static $isDropDown = 0;
	static $promptEnabled = 0;
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCheckbox( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkbox', JPATH_ADMINISTRATOR);
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
		$ajax = !empty($field->isAjax);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup) && !$ajax) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$cascade_after  = (int)$field->parameters->get('cascade_after', 0);
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$min_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'min_values', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		//$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		// Sanitize limitations
		$exact_values	= $field->parameters->get( 'exact_values', 0 ) ;
		if ($required && !$min_values) $min_values = 1;
		if ($exact_values) $max_values = $min_values = $exact_values;
		$js_popup_err	= $field->parameters->get( 'js_popup_err', 0 ) ;
		
		
		// **************
		// Value handling
		// **************
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_values = ($item->version == 0 || $value_usage > 0) ? trim($field->parameters->get( 'default_values', '' )) : '';
		
		
		// *************************
		// Input field configuration
		// *************************
		
		// DISPLAY using prettyCheckable JS
		$use_jslib = $field->parameters->get( 'use_jslib', 2 ) ;
		$use_prettycheckable = $use_jslib==2;
		static $prettycheckable_added = null;
	  if ( $use_prettycheckable && $prettycheckable_added === null ) $prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		
		// Display text label, use checkbox/radio image field for more
		$form_vals_display = 0;
		
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
			$field->value = preg_split("/\s*,\s*/u", $default_values);
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
		
		if ($cascade_after && !$ajax)
		{
			$byIds = FlexicontentFields::indexFieldsByIds($item->fields);
			
			if ( isset($byIds[$cascade_after]) )
			{
				$cascade_prompt = $field->parameters->get('cascade_prompt', '');
				$cascade_prompt = $cascade_prompt ? JText::_($cascade_prompt) : JText::_('FLEXI_PLEASE_SELECT').': '.$byIds[$cascade_after]->label;
				
				$srcELid = 'custom_'.$byIds[$cascade_after]->name;
				$trgELid = $elementid;
				
				// Get values of cascade (on) source field
				$field->valgrps = $byIds[$cascade_after]->value ? $byIds[$cascade_after]->value : array();
				foreach($field->valgrps as & $vg) {
					if (is_array($vg));
					else if (@unserialize($vg)!== false || $vg === 'b:0;' ) {
						$vg = unserialize($vg);
					} else {
						$vg = array($vg);
					}
				}
				unset($vg);
			} else {
				$cascade_after = 0;
				echo 'Error in field '.$field->label.' ['.$field->id.']'.' cannot cascaded after field no: '.$cascade_after.', field was not found <br/>';
			}
		}
		
		else if ($cascade_after && $ajax)
		{
			$field->valgrps = isset($field->valgrps) ? $field->valgrps : array();
			$field->valgrps = is_array($field->valgrps) ? $field->valgrps : preg_split("/\s*,\s*/u", trim($field->valgrps));
		}
		
		
		// ***********************
		// Handle multiple records
		// ***********************
		
		if ($multiple && !$ajax)
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
				
				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				
				// Remove HTML added by prettyCheckable JS, from the dupicate new INPUT SET
				var prettyContainers = newField.find('.prettyradio, .prettycheckbox');
				prettyContainers.find('input, label').each(function() {
					var el = jQuery(this);
					el.insertAfter(el.parent());
				});
				prettyContainers.remove();
				
				
				// Update INPUT SET container id
				newField.find('.fc_input_set').attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");
				var js_class = '".($use_prettycheckable && $prettycheckable_added ? ' use_prettycheckable' : '')."';
				
				// Update the new INPUT SET
				var theSet = newField.find('input:radio, input:checkbox');
				//if(window.console) window.console.log('theSet.length: ' + theSet.length);
				var nr = 0;
				theSet.each(function() {
					var elem = jQuery(this);
					elem.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']".(self::$valueIsArr ? '[]' : '')."');
					elem.attr('id', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);
					elem.attr('class', '".$elementid."_'+uniqueRowNum".$field->id." + js_class);
					".($use_prettycheckable && $prettycheckable_added ?
						"elem.attr('data-element-grpid', '".$elementid."_'+uniqueRowNum".$field->id.");" :
						"elem.attr('data-element-grpid', '".$elementid."_'+uniqueRowNum".$field->id.");" )."
					".($use_prettycheckable && $prettycheckable_added ?
						"elem.prev('label').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);" :
						"elem.next('label').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_'+nr);" )."
					nr++;
				});
				
				// Reapply prettyCheckable JS 
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
			
			// Listen to the changes of cascade-after field
			if ($cascade_after) $js .= "
				fc_cascade_field_funcs['".$srcELid."_'+uniqueRowNum".$field->id."] = function(rowNo){
					return function () {
						fcCascadedField(".$field->id.", '".$item->id."', '".$field->field_type."', 'select#".$srcELid."_'+rowNo+', input.".$srcELid."_'+rowNo, '".$trgELid."_'+rowNo, '".$cascade_prompt."', ".self::$promptEnabled.", rowNo);
					}
				}(uniqueRowNum".$field->id.");
				fc_cascade_field_funcs['".$srcELid."_'+uniqueRowNum".$field->id."]();
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
		
		// If cascading we will get it inside the value loop for every value, thus supporting field grouping properly
		$elements = !$cascade_after ? $this->getLimitedProps($field, $item) : array();
		if ( !is_array($elements) ) {
			$field->html = $elements;
			return;
		}
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		// Create the attributes of the form field
		$fftype = 'checkbox';
		$display_as_checkbox = 1;
		if ($display_as_checkbox)
		{
			$classes  = $use_prettycheckable && $prettycheckable_added ? ' use_prettycheckable ' : '';
			//$classes .= $required;
			$onchange = '';
			// Extra properties
			$attribs  = '';
			if ($exact_values)  {
				$attribs .= ' data-exact_values="'.$exact_values.'" ';
			} else {
				if ($max_values)    $attribs .= ' data-max_values="'.$max_values.'" ';
				if ($min_values)    $attribs .= ' data-min_values="'.$min_values.'" ';
			}
			if ($js_popup_err)  $attribs .= ' data-js_popup_err="'.$js_popup_err.'" ';
			if ($max_values || $min_values || $exact_values)  $classes .= ' validate-cboxlimitations ';
			else if ($required) $classes .= ' validate-checkbox ';  // No min/max/exact values allow basic 'required' validation
			if ($onchange) $attribs .= ' onchange="'.$onchange.'" ';
		}
		$label_class = 'fccheckradio_lbl'.(FLEXI_J30GE ? ' hasTooltip' : ' hasTip');
		$label_style = '';
		
		// Handle case of FORM fields that each value is an array of values
		// (e.g. selectmultiple, checkbox), and that multi-value input is also enabled
		$is_array_already = is_array($field->value) ? is_array(reset($field->value)) : false;
		$values = self::$valueIsArr && !$multiple && !$is_array_already ? array($field->value) : $field->value;
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = $ajax ? $field->valindex : 0;
		$js = "";
		foreach ($values as $value)
		{
			// Compatibility for serialized values
			if ( self::$valueIsArr ) {
				if (is_array($value));
				else if (@unserialize($value)!== false || $value === 'b:0;' ) {
					$value = unserialize($value);
				}
			}
			
			// Make sure value is an array
			if (!is_array($value))
				$value = strlen($value) ? array($value) : array();
			
			// Skip empty if not in field group, and at least one value was added
			if ( !count($value) && !$use_ingroup && $n)
				continue;
			
			// Get options according to cascading, this is here so that it works with field grouping too
			if ($cascade_after) {
				$elements = $this->getLimitedProps($field, $item, !$ajax ? $cascade_prompt : null, $ajax, $n);
			}
			
			if (1)
			{
				$fieldname_n = $fieldname.'['.$n.']'. (self::$valueIsArr ? '[]' : '');
				$elementid_n = $elementid.'_'.$n;
				
				// Create form field options
				$i = 0;
				$options = array();
				foreach ($elements as $element)
				{
					if ( !empty($element->isprompt) ) {
						$options[] = '<span style="float: left;" class="'.$element->isprompt.'">'.$element->text.'</span>';
						continue;
					}
					$checked  = in_array($element->value, $value)  ?  ' checked="checked"'  :  '';
					$elementid_no = $elementid_n.'_'.$i;
					//echo " &nbsp; &nbsp; $elementid_n , $elementid_no , $fieldname_n  , &nbsp; value: {$element->value} <br/>\n";
					$input_attribs  = $use_prettycheckable && $prettycheckable_added ? ' data-customClass="fcradiocheck"'/*.' data-labelPosition="right" data-labeltext="'.$element->text.'"'*/ : '';
					$input_attribs .= ' class="'.@ $classes.' '.$elementid_n.'" ';
					$input_fld = ' <input type="'.$fftype.'" id="'.$elementid_no.'" data-element-grpid="'.$elementid_n.'" name="'.$fieldname_n.'" '.$attribs.$input_attribs.' value="'.$element->value.'" '.$checked.' />';
					$options[] = ''
						.$pretext
						.$input_fld
						.'<label for="'.$elementid_no.'" class="'.$label_class.'" style="'.$label_style.'" title="'.@$element->label_tip.'">'
							.($form_vals_display!=1 ? $element->text : '')
						.'</label>'
						.$posttext
						;
					$i++;
				}
				
				// Apply (item form) separator and open/close tags to create the radio field
				$form_field = $opentag . implode($separator, $options) . $closetag;
			}
			
			if (!$ajax)
			{
				$field->html[] = '
					'.'<div id="'.$elementid_n.'" class="fc_input_set">'.$form_field.'</div>'.($cascade_after ? '<span class="field_cascade_loading"></span>' : '').'
					'.($use_ingroup ? '' : $move2).'
					'.($use_ingroup ? '' : $remove_button).'
					'.($use_ingroup || !$add_position ? '' : $add_here).'
					';
				
				// Listen to the changes of cascade-after field
				if ($cascade_after && !$ajax) $js .= "
				jQuery(document).ready(function(){
					fc_cascade_field_funcs['".$srcELid.'_'.$n."'] = function(){
						fcCascadedField(".$field->id.", '".$item->id."', '".$field->field_type."', 'select#".$srcELid.'_'.$n.", input.".$srcELid.'_'.$n."', '".$trgELid.'_'.$n."', '".$cascade_prompt."', ".self::$promptEnabled.", ".$n.");
					}
					fc_cascade_field_funcs['".$srcELid.'_'.$n."']();
				});
				";
			} else {
				$field->html = $form_field;
			}
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		if ($js)  $document->addScriptDeclaration($js);
		
		
		if ($ajax) {
			return; // Done
		} else if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
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
			$field->html = '<div class="alert alert-info fc-small fc-iblock">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_EXACTLY', $exact_values) .'</div><div class="clear"></div>'. $field->html;
		} else if ($max_values || $min_values > 1) {
			$field->html = '<div class="alert alert-info fc-small fc-iblock">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_BETWEEN', $min_values, $max_values) .'</div><div class="clear"></div>'. $field->html;
		}
	}
	
	
	function & getLimitedProps(&$field, &$item, $cascade_prompt='Please select above', $ajax=false, $i=0)
	{
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$cascade_after  = (int)$field->parameters->get('cascade_after', 0);
		
		if ($cascade_after)
		{
			$_valgrps = $ajax ? $field->valgrps : (isset($field->valgrps[$i]) ? $field->valgrps[$i] : null);
			if (empty($_valgrps)) {
				$elements = array();
				if (!$ajax) {
					//$prompt = JHTML::_('select.option', (self::$valueIsArr ? '_field_selection_prompt_' : ''), $cascade_prompt, 'value', 'text', (self::$valueIsArr ? 'disabled' : null));
					$prompt = (object) array( 'value'=>(self::$valueIsArr ? '_field_selection_prompt_' : ''), 'text'=>$cascade_prompt, 'disable'=>(self::$valueIsArr ? true : null), 'isprompt'=>'badge badge-info' );
					$elements = array($prompt);
				}
				return $elements;
			}
		}
		
		if ($sql_mode)  // SQL query mode
		{
			$and_clause = '';
			if ($cascade_after)
			{
				// Filter out values not in the the value group, this is done by modifying the SQL query
				$db = JFactory::getDBO();
				$_elements = array();
				foreach($_valgrps as & $vg) $vg = $db->Quote($vg);
				unset($vg);
				$and_clause = ' AND valgroup IN ('.implode(',', $_valgrps).')';
			}
			$item_pros = true;
			$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props, $item_pros, false, $and_clause);
			if ( !is_array($elements) ) {
				//$prompt = JHTML::_('select.option', (self::$valueIsArr ? '_field_selection_prompt_' : ''), JText::_('FLEXI_FIELD_INVALID_QUERY'), 'value', 'text', (self::$valueIsArr ? 'disabled' : null));
				$prompt = (object) array( 'value'=>(self::$valueIsArr ? '_field_selection_prompt_' : ''), 'text'=>JText::_('FLEXI_FIELD_INVALID_QUERY'), 'disable'=>(self::$valueIsArr ? true : null), 'isprompt'=>'badge badge-important' );
				$elements = array($prompt);
				return $elements;
			}
		}
		
		else  // Elements mode
		{
			$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
			if ( !is_array($elements) ) {
				//$prompt = JHTML::_('select.option', (self::$valueIsArr ? '_field_selection_prompt_' : ''), JText::_('FLEXI_FIELD_INVALID_ELEMENTS'), 'value', 'text', (self::$valueIsArr ? 'disabled' : null));
				$prompt = (object) array( 'value'=>(self::$valueIsArr ? '_field_selection_prompt_' : ''), 'text'=>JText::_('FLEXI_FIELD_INVALID_ELEMENTS'), 'disable'=>(self::$valueIsArr ? true : null), 'isprompt'=>'badge badge-important' );
				$elements = array($prompt);
				return $elements;
			}
			if ($cascade_after)
			{
				// Filter out values not in the the value group, this is done after the elements text is parsed
				$_elements = array();
				$_valgrps = array_flip($_valgrps);
				foreach($elements as $element)
					if (isset($_valgrps[$element->valgroup]))  $_elements[$element->value] = $element;
				$elements = $_elements;
			}
		}
		
		if (empty($elements)) {
			//$prompt = JHTML::_('select.option', (self::$valueIsArr ? '_field_selection_prompt_' : ''), 'No data found', 'value', 'text', (self::$valueIsArr ? 'disabled' : null));
			$prompt = (object) array( 'value'=>(self::$valueIsArr ? '_field_selection_prompt_' : ''), 'text'=>JText::_('FLEXI_FIELD_NO_DATA_FOUND'), 'disable'=>(self::$valueIsArr ? true : null), 'isprompt'=>'badge badge-warning' );
			$elements = array(0=>$prompt);
			return $elements;
		} else {
			$firstoptiontext = $field->parameters->get( 'firstoptiontext', 'FLEXI_SELECT' ) ;
			$usefirstoption  = $field->parameters->get( 'usefirstoption', self::$isDropDown ? 1 : 0 ) ;
			if ($usefirstoption) { // Add selection prompt
				//prompt = JHTML::_('select.option', (self::$valueIsArr ? '_field_selection_prompt_' : ''), JText::_($firstoptiontext), 'value', 'text', (self::$valueIsArr ? 'disabled' : null));
				$prompt = (object) array( 'value'=>(self::$valueIsArr ? '_field_selection_prompt_' : ''), 'text'=>JText::_($firstoptiontext), 'disable'=>(self::$valueIsArr ? true : null), 'isprompt'=>'badge badge-info' );
				array_unshift($elements, $prompt);
			}
		}
		
		if ($field->field_type=='radioimage' || $field->field_type=='checkboximage')
		{
			// image specific variables
			$form_vals_display = $field->parameters->get( 'form_vals_display', 1 ) ;  // this field includes image but it can be more convenient/compact not to be display image in item form
			$imagedir = preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
			$imgpath  = JURI::root(true) .'/'. $imagedir;
			$imgfolder = JPATH_SITE .DS. $imagedir;
			
			foreach ($elements as $element) {
				if ($form_vals_display >0 && !isset($element->image_html) && empty($element->isprompt))
					$element->image_html = file_exists($imgfolder . $element->image) ?
						'<img style="vertical-align:unset!important;" src="'.$imgpath . $element->image .'"  alt="'.$element->text.'" />' :
						'[NOT found]: '. $imgpath . $element->image;
				if (!isset($element->label_tip))
					$element->label_tip = flexicontent_html::getToolTip(null, $element->text, 0, 1);
			}
		}
		
		return $elements;
	}
	
	
	// Method called via AJAX to get dependent values
	function getCascadedField()
	{
		$field_id = JRequest::getInt('field_id', 0);
		$item_id  = JRequest::getInt('item_id', 0);
		$valgrps  = JRequest::getVar('valgrps', '');
		$valindex = JRequest::getVar('valindex', 0);
		
		// Load field
		$_fields = FlexicontentFields::getFieldsByIds(array($field_id), array($item_id));
		$field = $_fields[$field_id];
		$field->item_id = $item_id;
		$field->valgrps = $valgrps;
		$field->valindex = $valindex;
		
		// Load item
		$item = JTable::getInstance( $_type = 'flexicontent_items', $_prefix = '', $_config = array() );
		$item->load( $item_id );
		
		// Get field configuration
		FlexicontentFields::loadFieldConfig($field, $item);
		
		// Get field values
		//$_fieldvalues = FlexicontentFields::getFieldValsById(array($field_id), array($item_id));
		$field->value = null; //isset($_fieldvalues[$item_id][$field_id]) ? $_fieldvalues[$item_id][$field_id] : array();
		
		// Render field
		$field->isAjax = 1;
		$this->onDisplayField($field, $item);
		
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
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Check for no values and not displaying ALL elements
    $display_all = $field->parameters->get( 'display_all', 0 ) && !$is_ingroup;  // NOT supported inside fielgroup yet
		if ( empty($values) && !$display_all ) {
			if (!$is_ingroup) {
				$field->{$prop} = ''; $field->display_index = '';
			} else {
				$field->{$prop} = array(); $field->display_index = array();
			}
			return;
		}
		
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		// Microdata (classify the field values for search engines)
		$itemprop    = $field->parameters->get('microdata_itemprop');
		
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
		
		
		// Handle case of FORM fields that each value is an array of values
		// (e.g. selectmultiple, checkbox), and that multi-value input is also enabled
		// we make sure that values should be an array of arrays
		$values = $multiple && self::$valueIsArr ? $values : array($values);
		
		
		// Create display of field
		$field->{$prop} = array();
		$display_index = array();
		
		// Prepare for looping
		if ( !$values ) $values = array();
		if ( $display_all ) {
			// non-selected value shortcuts
	    $ns_pretext			= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_pretext', '' ), 'ns_pretext' );
  	  $ns_posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_posttext', '' ), 'ns_posttext' );
  	  $ns_pretext  = $ns_pretext . '<span class="fc_field_unsused_val">';
  	  $ns_posttext = '</span>' . $ns_posttext;
    	$ns_pretext  = $remove_space ? $ns_pretext : $ns_pretext . ' ';
	    $ns_posttext = $remove_space ? $ns_posttext : ' ' . $ns_posttext;
		}
		
		foreach ($values as $value)
		{
			// Compatibility for serialized values
			if ( $multiple && self::$valueIsArr ) {
				if ( is_array($value) );
				else if (@unserialize($value)!== false || $value === 'b:0;' ) {
					$value = unserialize($value);
				}
			}
			
			// Make sure value is an array
			if (!is_array($value))
				$value = strlen($value) ? array($value) : array();
			
			// Skip empty if not in field group
			if ( !count($value) && !$is_ingroup )
				continue;
			
			$html  = array();
			$index = array();
			
			// CASE a. Display ALL elements (selected and NON-selected)   ***  NOT supported inside fieldgroup YET
			if ( $display_all )
			{
				// *** value is always an array we made sure above
				$indexes = array_flip($value);
				foreach ($elements as $val => $element)
				{
					if ($text_or_value == 0) $disp = $element->value;
					else if ($text_or_value == 1) $disp =$element->text;
					
					if ( isset($indexes[$val]) ) {
						$html[]  = $pretext.$disp.$posttext;
						$index[] = $element->value;
					} else
						$html[]  = $ns_pretext.$disp.$ns_posttext;
				}
			}
			
			// CASE b. Display only selected elements
			else
			{
				foreach ($value as $v) {
					// Skip empty/invalid values but add empty display, if in field group
					$element = !strlen($v) ? false : @$elements[ $v ];
					if ( !$element ) {
						if ( $use_ingroup ) $html[]	= '';
						continue;
					}
					
					if ($text_or_value == 0) $disp = $element->value;
					else if ($text_or_value == 1) $disp = $element->text;
					
					$html[]  = $pretext . $disp . $posttext;
					$index[] = $pretext . $element->value . $posttext;
				}
			}
			if ($multiple && self::$valueIsArr) {
				// For current array of values, apply values separator, and field 's opening / closing texts
				$field->{$prop}[] = !count($html) ? '' : $opentag . implode($separatorf, $html)  . $closetag;
				$display_index[]  = !count($html) ? '' : $opentag . implode($separatorf, $index) . $closetag;
			} else {
				// Done, there should not be more !!, since we handled an array of singular values
				$field->{$prop} = $html;
				$display_index = $index;
				break;
			}
		}
		
		
		// Add microdata to every group of values if field -- is -- in a field group
		if ($is_ingroup && $itemprop) {
			foreach($field->{$prop} as $n => $disp_html) {
				$field->{$prop}[$n] = '<span itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</span>';
			}
		}
		
		
		// Do not convert the array to string if field is in a group, and do not add: FIELD's opetag, closetag, value separator
		if (!$is_ingroup)
		{
			if ($multiple && self::$valueIsArr) {
				// Values separator, field 's opening / closing texts, were already applied for every array of values
				$field->{$prop} = implode("", $field->{$prop});
				$field->display_index = implode("", $display_index);
			} else {
				// Apply values separator, and field 's opening / closing texts
				$field->{$prop} = !count($field->{$prop}) ? '' : $opentag . implode($separatorf, $field->{$prop}) . $closetag;
				$field->display_index = !count($field->{$prop}) ? '' : $opentag . implode($separatorf, $display_index) . $closetag;
			}
			
			// Add microdata once for all values, if field -- is NOT -- in a field group
			if ( $field->{$prop}!=='' && $itemprop )
			{
				$field->{$prop} = '<span itemprop="'.$itemprop.'" >' .$field->{$prop}. '</span>';
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
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		$field->use_suborder = $multiple && self::$valueIsArr;
		
		// Make sure posted data is an array of arrays
		$post = !is_array($post) ? array($post) : $post;
		$v = reset($post);
		$post = (!is_array($v) && @unserialize($v)=== false)  ?  array($post)  :  $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($v) ) {
				if ( @unserialize($v)!== false || $v === 'b:0;' ) {  // support for exported serialized data)
					$v = unserialize($v);
				} else {
					$v = array($v);
				}
			}
			
			// Do server-side validation and skip empty/invalid values
			$vals = array();
			foreach ($v as $i => $nv) {
				$element = !strlen($nv) ? false : @$elements[$nv];
				if ( $element ) $vals[] = $nv;  // include value
			}
			
			// Skip empty value ARRAY if not in group
			if (empty($vals) && !$use_ingroup) continue;
			// If multiple disabled, use 1st value ARRAY only
			if (!$multiple) {  $newpost = $vals;  break;  }
			
			$newpost[$new] = $vals;
			$new++;
			
			// max values limitation (*if in group, this was zeroed above)
			if ($max_values && $new >= $max_values) continue;
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
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->isindexed = true;
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
