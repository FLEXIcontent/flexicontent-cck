<?php

/**
 * Escape every key and value. This generic layout cannot know which sub-field is meant to carry HTML, and
 * Joomla's form filter keeps the array keys (at any depth) of sub-fields that have no filter attribute.
 * To output the HTML of a specific sub-field, use a custom layout that prints that sub-field deliberately.
 */
$esc = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$fmt = static function ($v) use ($esc) { return $esc(is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)); };

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
				$html .= '<b>' . $esc($kk) . '</b>: ' . $fmt($vv) . '<br>';
			}
		}
		else $html .=  '<b>' . $esc($k) . '</b>: ' . $fmt($v) . '<br>';
	}
	else $html .= $fmt($value) . '<br>';

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
