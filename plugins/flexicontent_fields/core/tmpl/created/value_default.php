<?php
// Add prefix / suffix
$field->{$prop} =
	$pretext
		. JHtml::_( 'date', $item->created, $dateformat ) .
	$posttext;

// Add microdata property
$itemprop = $field->parameters->get('microdata_itemprop', 'dateCreated');
if ($itemprop)
{
	$field->{$prop} .= '
		<meta itemprop="' . $itemprop . '" content="' . $item->created . '">
	';
}