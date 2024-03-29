<?php
// Add prefix / suffix
$field->{$prop} =
	$pretext
		. \Joomla\CMS\HTML\HTMLHelper::_( 'date', $item->modified, $dateformat ) .
	$posttext;

// Add microdata property
$itemprop = $field->parameters->get('microdata_itemprop', 'dateModified');
if ($itemprop)
{
	$field->{$prop} .= '
		<meta itemprop="' . $itemprop . '" content="' . $item->modified . '">
	';
}