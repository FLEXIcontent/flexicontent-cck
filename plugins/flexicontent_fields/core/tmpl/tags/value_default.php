<?php

// Note you can use: -- if ($prop === 'csv_export') { ... } -- to check if executed during CSV export
$is_csv_export = $prop === 'csv_export';

foreach ($tags as $tag)
{
	$tag_id = $tag->id;

	if ($is_csv_export)
	{
		$field->{$prop}[] = $tag->name;
	}

	else
	{
		// Performance concern, only do routing once per tag
		if ( !isset($tag_links[$tag_id]) )
		{
			$tag_links[$tag_id] = $use_catlinks ?
				JRoute::_( FlexicontentHelperRoute::getCategoryRoute(0, 0, array('layout'=>'tags','tagid'=>$tag->slug)) ) :
				JRoute::_( FlexicontentHelperRoute::getTagRoute($tag->slug) ) ;
		}

		// With / without link
		$display = $link_to_view ?
			'<a class="fc_tags fc_tag_' .$tag->id. ' link_' .$field->name. '" href="' . $tag_links[$tag_id] . '">' . $tag->name . '</a>' :
			'<span class="fc_tags fc_tag_' .$tag->id. ' nolink_' .$field->name. '">' . $tag->name . '</span>' ;
		$field->{$prop}[] = $pretext . $display . $posttext;

		// Some extra data
		$field->value[] = $tag->name;
	}
}