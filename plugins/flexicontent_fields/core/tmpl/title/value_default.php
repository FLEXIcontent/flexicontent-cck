<?php
// Add prefix / suffix
$field->{$prop} =
	$pretext
		. $item->title .
	$posttext;

// Get ogp configuration
$useogp     = $field->parameters->get('useogp', 1);
$ogpinview  = $field->parameters->get('ogpinview', array());
$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);

if ($useogp && $field->{$prop})
{
	if ( in_array($view, $ogpinview) )
	{
		$content_val = flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen);
		JFactory::getDocument()->addCustomTag('<meta property="og:title" content="'.$content_val.'" />');
	}
}

// Add microdata property (currently no parameter in XML for this field)
$itemprop = $field->parameters->get('microdata_itemprop', 'name');
if ($itemprop)
{
	$field->{$prop} = '
		<div style="display:inline" itemprop="'.$itemprop.'" >
			' . $field->{$prop} . '
		</div>';
}