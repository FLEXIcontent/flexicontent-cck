<?php

// Note you can use: -- if ($prop === 'csv_export') { ... } -- to check if executed during CSV export
$is_csv_export = $prop === 'csv_export';

foreach ($categories as $category)
{
	$catid = $category->id;

	if ($is_csv_export)
	{
		$field->{$prop}[] = $category->title;
	}

	else
	{
		// Exclude the "noroute" categories (e.g. "Main slideshow", or other special categories that should NOT be displayed !)
		if ( in_array($catid, @$globalnoroute) ) continue;

		// Performance concern, only do routing once per category
		if ( $link_to_view && !isset($cat_links[$catid]) )
		{
			$cat_links[$catid] = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug));
		}

		// With / without link
		$isMain = $catid == $item->catid;

		$display = $link_to_view ?
			'<a class="fc_categories fc_category_' .$catid. ' '.($isMain ? 'fc_ismain_cat' : '').' link_' .$field->name. '" href="' . $cat_links[$catid] . '">' . $category->title . '</a>' :
			'<span class="fc_categories fc_category_' .$catid. ' '.($isMain ? 'fc_ismain_cat' : '').' nolink_' .$field->name. '">' . $category->title . '</span>' ;

		// Place main category to the front
		$isMain ?
			array_unshift($field->{$prop}, $pretext. $display .$posttext) :
			$field->{$prop}[] = $pretext . $display . $posttext ;

		// Some extra data
		$field->value[] = $category->title;
	}
}
