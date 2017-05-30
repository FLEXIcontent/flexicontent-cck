<?php

$n = 0;
foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( !strlen($value['title']) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}
	
	$html = '
		<label class="fc_termtitle label label-success">' . $value['title'] . '</label>
		<div class="fc_termdesc">' . $value['text'] . '</div>';

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;
	
	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';
	
	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}