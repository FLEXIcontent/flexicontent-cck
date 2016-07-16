<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');

class plgFlexicontent_fieldsPhonenumbers extends JPlugin
{
	static $field_types = array('phonenumbers');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_phonenumbers', JPATH_ADMINISTRATOR);
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
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
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
		
		// Optional form elements
		$use_label = (int) $field->parameters->get( 'use_label', 1 ) ;
		$use_cc    = (int) $field->parameters->get( 'use_cc', 1 ) ;
		$use_phone = (int) $field->parameters->get( 'use_phone', 3 ) ;
		$show_part_labels = (int) $field->parameters->get( 'show_part_labels', 0 ) ;
		
		// Input field display size & max characters
		if ($use_label) {
			$label_size = (int) $field->parameters->get( 'label_size', 48 ) ;
			$label_maxlength = (int) $field->parameters->get( 'label_maxlength', 0 ) ;  // client/server side enforced
		}
		if ($use_cc) {
			$cc_size    = (int) $field->parameters->get( 'cc_size', 6 ) ;
			$cc_maxlength    = (int) $field->parameters->get( 'cc_maxlength', 0 ) ;     // client/server side enforced
		}
		$phone1_size = (int) $field->parameters->get( 'phone1_size', 12 ) ;
		$phone1_maxlength = (int) $field->parameters->get( 'phone1_maxlength', 0 ) ;  // client/server side enforced
		$phone2_size = (int) $field->parameters->get( 'phone2_size', 12 ) ;
		$phone2_maxlength = (int) $field->parameters->get( 'phone2_maxlength', 0 ) ;  // client/server side enforced
		$phone3_size = (int) $field->parameters->get( 'phone3_size', 12 ) ;
		$phone3_maxlength = (int) $field->parameters->get( 'phone3_maxlength', 0 ) ;  // client/server side enforced
		$allow_letters = (int) $field->parameters->get( 'allow_letters', 0 ) ? '' : ' validate-numeric';  // class for enabling javascript validation 
				
		// Create extra HTML TAG parameters for the form field(s)
		if ($use_label) $label_attribs = ' size="'.$label_size.'"'. ($label_maxlength ? ' maxlength="'.$label_maxlength.'" ' : '');
		if ($use_cc)    $cc_attribs    = ' size="'.$cc_size.'"'.    ($cc_maxlength ? ' maxlength="'.$cc_maxlength.'" ' : '');
		$phone1_attribs = ' size="'.$phone1_size.'"'. ($phone1_maxlength ? ' maxlength="'.$phone1_maxlength.'" ' : '');
		$phone2_attribs = ' size="'.$phone2_size.'"'. ($phone2_maxlength ? ' maxlength="'.$phone2_maxlength.'" ' : '');
		$phone3_attribs = ' size="'.$phone3_size.'"'. ($phone3_maxlength ? ' maxlength="'.$phone3_maxlength.'" ' : '');
		
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0] = array('label'=>'', 'cc'=>'', 'phone1'=>'', 'phone2'=>'', 'phone3'=>'');
			$field->value[0] = serialize($field->value[0]);
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
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
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				
				theInput = newField.find('input.phonelabel').first();
				theInput.val('');
				theInput.attr('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][label]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_label');
				newField.find('.phonelabel-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_label');
				
				theInput = newField.find('input.phonecc').first();
				theInput.val('');
				theInput.attr('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][cc]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_cc');
				newField.find('.phonecc-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_cc');
				
				theInput = newField.find('input.phonenum1').first();
				theInput.val('');
				theInput.attr('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone1]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone1');
				newField.find('.phonenum1-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_phone1');
				
				theInput = newField.find('input.phonenum2').first();
				theInput.val('');
				theInput.attr('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone2]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone2');
				newField.find('.phonenum2-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_phone2');
				
				theInput = newField.find('input.phonenum3').first();
				theInput.val('');
				theInput.attr('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone3]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone3');
				newField.find('.phonenum3-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_phone3');
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
		
		if ($show_part_labels) {
			$part1_lbl = $use_phone > 1 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_AREA_CODE' : '';
			$part2_lbl = $use_phone == 2 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_LOCAL_NUM' : ($use_phone == 3 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_PART_2' : '' );
			$part3_lbl = $use_phone >  2 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_PART_3' : '';
		}
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('label'=>'', 'cc'=>'', 'phone1'=>$value, 'phone2'=>'', 'phone3'=>'');
			}
			if ( empty($value['label']) && empty($value['cc']) && empty($value['phone1']) && empty($value['phone2']) && empty($value['phone3']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$phonelabel = (!$use_label ? '' : '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_LABEL' ). '</td><td>
					<input class="fcfield_textval phonelabel" name="'.$fieldname_n.'[label]" id="'.$elementid_n.'_label" type="text" value="'.@$value['label'].'" '.$label_attribs.' />
				</td></tr>');
			
			$phonecc = (!$use_cc ? '' : '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_COUNTRY_CODE' ). '</td><td>
					<input class="phonecc fcfield_textval inlineval" name="'.$fieldname_n.'[cc]" id="'.$elementid_n.'_cc" type="text" value="'.@$value['cc'].'" '.$cc_attribs.' />
				</td></tr>');
			
			$phone = '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_NUMBER' ). '</td><td>
					<div class="nowrap_box">
						'.($show_part_labels && $part1_lbl ? '<label class="label phonenum1-lbl" for="'.$elementid_n.'_phone1" >'.JText::_($part1_lbl).'</label><br/>' : '').'
						<input class="phonenum1 fcfield_textval inlineval'.$allow_letters.$required.'" name="'.$fieldname_n.'[phone1]" id="'.$elementid_n.'_phone1" type="text" value="'.$value['phone1'].'" '.$phone1_attribs.' />
						'.($use_phone > 1 ? '-' : '').'
					</div>
					
					'.($use_phone >= 2 ? '
					<div class="nowrap_box">
						'.($show_part_labels && $part2_lbl ? '<label class="label phonenum2-lbl" for="'.$elementid_n.'_phone2" >'.JText::_($part2_lbl).'</label><br/>' : '').'
						<input class="phonenum2 fcfield_textval inlineval'.$allow_letters.$required.'" name="'.$fieldname_n.'[phone2]" id="'.$elementid_n.'_phone2" type="text" value="'.$value['phone2'].'" '.$phone2_attribs.' />
						'.($use_phone > 2 ? '-' : '').'
					</div>' : '').'
					
					'.($use_phone > 2 ? '
					<div class="nowrap_box">
						'.($show_part_labels && $part3_lbl ? '<label class="label phonenum3-lbl" for="'.$elementid_n.'_phone3" >'.JText::_($part3_lbl).'</label><br/>' : '').'
						<input class="phonenum3 fcfield_textval inlineval'.$allow_letters.$required.'" name="'.$fieldname_n.'[phone3]" id="'.$elementid_n.'_phone3" type="text" value="'.$value['phone3'].'" '.$phone3_attribs.' />
					</div>' : '').'
				</td></tr>';
			
			$field->html[] = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
				'.($use_ingroup ? '' : '<div class="fcclear"></div>').'
				<table class="admintable"><tbody>
				'.$phonelabel.'
				'.$phonecc.'
				'.$phone.'
				</tbody></table>
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
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
		
		// Get field values
		$values = $values ? $values : $field->value;
		$values = !is_array($values) ? array($values) : $values;     // make sure values is an array
		$isempty = !count($values) || !strlen($values[0]);           // detect empty value
		
		if($isempty) return;
		
		// Optional display
		$display_phone_label		= $field->parameters->get( 'display_phone_label', 1 ) ;
		$display_country_code		= $field->parameters->get( 'display_country_code', 1 ) ;
		$display_area_code		= $field->parameters->get( 'display_area_code', 1 ) ;
		$add_tel_link		= $field->parameters->get( 'add_tel_link', 0 ) ;
		
		// Property Separators
		$label_prefix = $field->parameters->get( 'label_prefix', '' ) ;
		$label_suffix = $field->parameters->get( 'label_suffix', '' ) ;
		$country_code_prefix = $field->parameters->get( 'country_code_prefix', '' ) ;
		$separator_cc_phone1 = $field->parameters->get( 'separator_cc_phone1', '' ) ;
		$separator_phone1_phone2 = $field->parameters->get( 'separator_phone1_phone2', '' ) ;
		$separator_phone2_phone3 = $field->parameters->get( 'separator_phone2_phone3', '' ) ;
		
		// Open/close tags (every value)
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		// Prefix/suffix (value list)
		$field_prefix		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'field_prefix', '' ), 'field_prefix' );
		$field_suffix		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'field_suffix', '' ), 'field_suffix' );
		
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			// Compatibility for unserialized values or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('label'=>'', 'cc'=>'', 'phone1'=>$value, 'phone2'=>'', 'phone3'=>'');
			}
			if (empty($value['phone1']) && empty($value['phone2']) && empty($value['phone3']) && !$is_ingroup ) continue; // Skip empty if not in field group
			
			$html = $opentag
					.($display_phone_label  ? $label_prefix.$value['label'] . $label_suffix : '');
			
			if ($add_tel_link) {
				$html .= '<a href="tel:' 
						. ($display_country_code ? '+' . $value['cc'] : '')
						. ($display_area_code    ? $value['phone1'] : '')
						. $value['phone2'] . $value['phone3']
						. '">';
			}
			$html .= ($display_country_code ? $country_code_prefix . $value['cc'] . $separator_cc_phone1 : '')
					. ($display_area_code    ? $value['phone1'] . $separator_phone1_phone2 : '')
					. $value['phone2'] . $separator_phone2_phone3 . $value['phone3']
					. ($add_tel_link ? '</a>' : '')
					. $closetag;
					
			$field->{$prop}[] = $html;
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if (!$is_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			if(count($field->{$prop})) {
				$field->{$prop}  = implode('', $field->{$prop});
				$field->{$prop}  = $field_prefix . $field->{$prop} . $field_suffix;
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
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		$label_maxlength = (int) $field->parameters->get( 'label_maxlength', 0 ) ;  // client/server side enforced
		$cc_maxlength    = (int) $field->parameters->get( 'cc_maxlength', 0 ) ;     // client/server side enforced
		$phone1_maxlength = (int) $field->parameters->get( 'phone1_maxlength', 0 ) ;  // client/server side enforced
		$phone2_maxlength = (int) $field->parameters->get( 'phone2_maxlength', 0 ) ;  // client/server side enforced
		$phone3_maxlength = (int) $field->parameters->get( 'phone3_maxlength', 0 ) ;  // client/server side enforced
		$allow_letters = (int) $field->parameters->get( 'allow_letters', 0 ); // allow letters during validation
			
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($v) ) {
				if ( @unserialize($v)!== false || $v === 'b:0;' ) {  // support for exported serialized data)
					$v = unserialize($v);
				} else {
					$v = array('label'=>'', 'cc'=>'', 'phone1'=>'', 'phone2'=>$v, 'phone3'=>'');
				}
			}
			
			// ****************************************************************************
			// Validate phone number, skipping phone number that are empty after validation
			// ****************************************************************************

			$regex = $allow_letters ? '/[^0-9A-Z]/' : '/[^0-9]/';	// allow letters?
			 
			// force string to uppercase, remove any forbiden characters
			$v['phone1'] = preg_replace($regex, '', strtoupper($v['phone1']));
			$v['phone2'] = preg_replace($regex, '', strtoupper($v['phone2']));
			$v['phone3'] = preg_replace($regex, '', strtoupper($v['phone3']));
			
			// enforce max length
			$newpost[$new]['phone1'] = $phone1_maxlength ? $v['phone1'] : substr($v['phone1'], 0, $phone1_maxlength);
			$newpost[$new]['phone2'] = $phone2_maxlength ? $v['phone2'] : substr($v['phone2'], 0, $phone2_maxlength);
			$newpost[$new]['phone3'] = $phone3_maxlength ? $v['phone3'] : substr($v['phone3'], 0, $phone3_maxlength);
			
			if (!strlen($v['phone1']) && !strlen($v['phone2']) && !strlen($v['phone3']) && !$use_ingroup ) continue;  // Skip empty values if not in field group
			
			// Validate other value properties
			$newpost[$new]['label']  = flexicontent_html::dataFilter(@$v['label'],  $label_maxlength, 'STRING', 0);
			$newpost[$new]['cc']     = flexicontent_html::dataFilter(@$v['cc'],     $cc_maxlength,    'STRING', 0);
			
			$new++;
		}
		$post = $newpost;
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
	/*function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$this->onDisplayFilter($filter, $value, $formName);
	}*/
	
	
	// Method to display a category filter for the category view
	/*function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;

		$filter->html = '';
	}*/
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/
	
	
	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/
		
}
