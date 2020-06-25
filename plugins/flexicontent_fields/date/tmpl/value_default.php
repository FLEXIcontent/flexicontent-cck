<?php

$n = 0;
foreach ($values as $value)
{
	// If time is not allowed then disgard it from the value
	if (strlen($value) && !$date_allowtime)
	{
		@ list($date, $time) = preg_split('#\s+#', $value, $limit=2);
	}
	else
	{
		$date = $value;
	}

	// Try to parse the date string
	if (!empty($date))
	{
		try {
			$date = JHtml::_('date', $date, $dateformat, $timezone ).$tz_info;
		} catch ( Exception $e ) {
			$date = '';
		}
	}

	// Skip empty value, adding an empty placeholder if field inside in field group
	if (empty($date))
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= $no_value_msg;
		}
		continue;
	}

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $date . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}