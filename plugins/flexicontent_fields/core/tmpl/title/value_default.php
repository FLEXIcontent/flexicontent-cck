<?php

/*
 * Add prefix / suffix
 */
$field->{$prop} =
	$pretext
		. ($prop === 'csv_export' ? $item->title : htmlspecialchars((string) $item->title, ENT_QUOTES, 'UTF-8')) .
	$posttext;

/*
 * Add OGP Tags
 */
if ($field->parameters->get('useogp', 1) && $field->{$prop})
{
	// The current view is frontend view with HTML format and is a full item view of current item
	if (static::$isHtmlViewFE && $isMatchedItemView)
	{
		$ogpmaxlen = $field->parameters->get('ogpmaxlen', 300);
		$content_val = flexicontent_html::striptagsandcut($pretext . $item->title . $posttext, $ogpmaxlen);

		$document->addCustomTag('<meta property="og:title" content="'.$content_val.'" />');
	}
}

/*
 * Add microdata property (currently no parameter in XML for this field)
 */
$itemprop = $field->parameters->get('microdata_itemprop', 'name');
if ($itemprop)
{
	$field->{$prop} = '
		<div style="display:inline" itemprop="'.$itemprop.'" >
			' . $field->{$prop} . '
		</div>';
}