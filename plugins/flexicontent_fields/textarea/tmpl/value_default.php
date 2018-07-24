<?php

$n = 0;
foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( !strlen($value) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	// Cut text to a given limit, optionally adding a toggle button
	if ($cut_text)
	{
		$uncut_length = 0;
		$value = flexicontent_html::striptagsandcut(
			$value, $cut_text_length, $uncut_length,
			$ops = array(
				'cut_at_word' => true,
				'more_toggler' => $cut_text_display,
				'more_icon' => $cut_text_display_btn_icon,
				'more_txt' => $cut_text_display_btn_text,
				'modal_title' => $field->label
			)
		);
	}

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $value . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}