<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Add prefix / suffix
$field->{$prop} =
	$pretext
		. (!$item->modified
			? Text::_('FLEXI_NEVER')
			: HTMLHelper::_('date', $item->modified, $dateformat)
	) .
	$posttext;

// Add microdata property
$itemprop = $field->parameters->get('microdata_itemprop', 'dateModified');
if ($itemprop)
{
	$field->{$prop} .= '
		<meta itemprop="' . $itemprop . '" content="' . $item->modified . '">
	';
}