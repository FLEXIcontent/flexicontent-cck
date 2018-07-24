<?php

// Prepare for looping
if ( $display_all )
{
	// non-selected value shortcuts
	$ns_pretext  = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_pretext', '' ), 'ns_pretext' );
	$ns_posttext = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_posttext', '' ), 'ns_posttext' );
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
	if ( $multiple && static::$valueIsArr )
	{
		if (!is_array($value))
		{
			$value = $this->unserialize_array($value, $force_array=true, $force_value=true);
		}
	}

	// Make sure value is an array
	if (!is_array($value))
	{
		$value = strlen($value) ? array($value) : array();
	}

	// Skip empty if not in field group
	if ( !count($value) && !$is_ingroup && !$display_all )  continue;

	$html  = array();
	$index = array();

	// CASE a. Display ALL elements (selected and NON-selected)   ***  NOT supported inside fieldgroup YET
	if ( $display_all )
	{
		// *** value is always an array we made sure above
		$indexes = array_flip($value);
		foreach ($elements as $val => $element)
		{
			if ( isset($element->state) && $element->state < 1 )   // 0: unpublished, 1: published, 2: archived
			{
				if ( $is_ingroup ) $html[]	= '';
				continue;
			}

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
		foreach ($value as $v)
		{
			// Skip empty/invalid values but add empty display, if in field group
			$element = !strlen($v) ? false : @$elements[ $v ];
			if ( !$element || (isset($element->state) && $element->state < 1) )   // 0: unpublished, 1: published, 2: archived
			{
				if ( $is_ingroup ) $html[]	= '';
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

	// If field is multi-value with each value being an array of values, then implode current value into a single display string
	if ($multiple && static::$valueIsArr)
	{
		// For current array of values, apply values separator, and field 's opening / closing texts
		$field->{$prop}[] = !count($html) ? '' : $opentag . implode($separatorf, $html)  . $closetag;
		$display_index[]  = !count($html) ? '' : $opentag . implode($separatorf, $index) . $closetag;
	}

	// Not a multi-value field or each value is not an array of values
	else
	{
		// Done, there should not be more !!, since we handled an array of singular values
		$field->{$prop} = $html;
		$display_index = $index;
		break;
	}
}