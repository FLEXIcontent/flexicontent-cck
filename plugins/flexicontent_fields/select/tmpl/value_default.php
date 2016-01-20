<?php

// Prepare for looping
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
			else /* only for (*IMAGE) fields */
				$disp = '<img src="'.$imgpath . $element->image .'" class="'.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />';
			
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
			else  /* only for (*IMAGE) fields */
				$disp = '<img src="'.$imgpath . $element->image .'" class="'.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />';
			
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