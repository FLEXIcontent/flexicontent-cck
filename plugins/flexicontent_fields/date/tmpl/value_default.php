<?php

$n = 0;
foreach ($values as $value)
{
	if ( !strlen($value) && !$is_ingroup ) continue; // Skip empty if not in field group
	if ( !strlen($value) ) {
		$field->{$prop}[$n++]	= $no_value_msg;
		continue;
	}
	
	// Check if dates are allowed to have time part
	if ($date_allowtime) $date = $value;
	else @list($date, $time) = preg_split('#\s+#', $value, $limit=2);
	
	if ( empty($date) ) continue;
	
	try {
		$date = JHTML::_('date', $date, $dateformat, $timezone ).$tz_info;
	} catch ( Exception $e ) {
		$date = '';
	}
	
	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext.$date.$posttext;
	
	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';
	
	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}