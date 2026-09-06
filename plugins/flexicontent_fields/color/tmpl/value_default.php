<?php

$output_as = (int) $field->parameters->get('output_as', 2);

$n = 0;
foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ($value === null || !strlen($value) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	// 2: Color box (having color as background)
	if ($output_as === 2)
	{
		// Reject characters that can escape the CSS declaration, then encode for the HTML attribute.
		$css_value = preg_match('/[\\\\;"\'<>{}]/', $value) ? 'transparent' : $value;
		$css_value = htmlspecialchars($css_value, ENT_QUOTES, 'UTF-8');
		$value = '<span class="fcfield_color_vbox" style="background-color: ' . $css_value . '; padding: 8px; display: inline-block; border-radius: 4px; box-shadow: 1px 1px 2px;"></span>';
	}
	else
	{
		$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $value . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
