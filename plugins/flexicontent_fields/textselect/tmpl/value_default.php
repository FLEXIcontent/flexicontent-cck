<?php

$n = 0;
foreach ($values as $value)
{
	if ( !strlen($value) && !$is_ingroup ) continue; // Skip empty if not in field group
	if ( !strlen($value) ) {
		$field->{$prop}[$n++]	= '';
		continue;
	}
	
	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $value . $posttext;
	
	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';
	
	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}