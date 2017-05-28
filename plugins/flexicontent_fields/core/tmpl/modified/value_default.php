<?php
// Add prefix / suffix
$field->{$prop} =
	$pretext
		. JHTML::_( 'date', $item->modified, $dateformat ) .
	$posttext;

// Add microdata property
$itemprop = $field->parameters->get('microdata_itemprop', 'dateModified');
if ($itemprop)
{
	$field->{$prop} = '
		<div style="display:inline" itemprop="'.$itemprop.'" >
			' . $field->{$prop} . '
		</div>';
}
