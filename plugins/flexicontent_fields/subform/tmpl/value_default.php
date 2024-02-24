<?php

$n = 0;
foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( empty($value) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	$html = '<hr><br>';
	if (is_array($value)) foreach ($value as $k => $v)
	{
		if (is_array($v)) {
			foreach ($v as $kk => $vv) {
				$html .= '<b>' . $kk . '</b>: ' . (is_scalar($vv) ? $vv : json_encode($vv)) . '<br>';
			}
		}
		else $html .=  '<b>' . $k . '</b>: ' . (is_scalar($v) ? $v : json_encode($v)) . '<br>';
	}
	else $html .= is_scalar($value) ? $value : json_encode($value) . '<br>';

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
