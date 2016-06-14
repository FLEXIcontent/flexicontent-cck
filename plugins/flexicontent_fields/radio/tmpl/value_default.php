<?php

// Prepare for looping
if ( $display_all ) {
	// non-selected value shortcuts
  $ns_pretext			= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_pretext', '' ), 'ns_pretext' );
  $ns_posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_posttext', '' ), 'ns_posttext' );
  $ns_pretext  = $ns_pretext . '<div class="fc_field_unsused_val">';
  $ns_posttext = '</div>' . $ns_posttext;
	$ns_pretext  = $remove_space ? $ns_pretext : $ns_pretext . ' ';
  $ns_posttext = $remove_space ? $ns_posttext : ' ' . $ns_posttext;
}


// Create CSS class for image / icon HTML tag  ** only for (*IMAGE) fields
if ($text_or_value > 1)
{
	$_class = ' fc_ifield_val_icoclass'
		.($icon_size ? ' fc-icon-'.$icon_size : '')
		.($text_or_value == 2 || $text_or_value == 4  ?  ' '.$tooltip_class  :  '');
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
			
			/* only for (*IMAGE) fields */
			else if ($text_or_value == 2)
				$disp = !$image_type ?
					'<img src="'. $imgpath . $element->image .'" class="fc_ifield_val_img '.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />' :
					'<span class="'. $_class .' '. $element->image .'" style="'.($icon_color ? 'color: '.$icon_color.';' : '').'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"></span>' ;
			else
				$disp = '
				<div class="fc_ifield_val_box">
					'.(!$image_type ?
						'<img src="'.$imgpath . $element->image .'" class="fc_ifield_val_img '.($text_or_value == 4 ? $tooltip_class : '').'" '.($text_or_value == 4 ? 'title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"' : '').' alt="'.$element->text.'" />' :
						'<span class="'. $_class .' '. $element->image .'" style="'.($icon_color ? 'color: '.$icon_color.';' : '').'" '.($text_or_value == 4 ? ' title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"' : '').'"></span>'
					).'
					<span class="alert alert-info fc_ifield_val_txt">'.($text_or_value == 3 ? $element->text : $element->value).'</span>
				</div>
				';
			
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
			
			/* only for (*IMAGE) fields */
			else if ($text_or_value == 2)
				$disp = !$image_type ?
					'<img src="'.$imgpath . $element->image .'" class="fc_ifield_val_img '.$tooltip_class.'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'" alt="'.$element->text.'" />' :
					'<span class="'. $_class .' '. $element->image .'" style="'.($icon_color ? 'color: '.$icon_color.';' : '').'" title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"></span>' ;
			else
				$disp = '
				<div class="fc_ifield_val_box">
					'.(!$image_type ?
						'<img src="'. $imgpath . $element->image .'" class="fc_ifield_val_img '.($text_or_value == 4 ? $tooltip_class : '').'" '.($text_or_value == 4 ? 'title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"' : '').' alt="'.$element->text.'" />' :
						'<span class="'. $_class .' '. $element->image .'" style="'.($icon_color ? 'color: '.$icon_color.';' : '').'" '.($text_or_value == 4 ? ' title="'.flexicontent_html::getToolTip(null, $element->text, 0).'"' : '').'"></span>'
					).'
					<span class="alert alert-info fc_ifield_val_txt">'.($text_or_value == 3 ? $element->text : $element->value).'</span>
				</div>
				';
			
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