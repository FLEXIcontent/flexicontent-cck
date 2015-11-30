<?php

foreach ($tags as $tag) :
	$tag_id = $tag->id;
	
	// Performance concern, only do routing once per tag
	if ( !isset($tag_links[$tag_id]) ) {
		$tag_links[$tag_id] = $use_catlinks ?
			JRoute::_( FlexicontentHelperRoute::getCategoryRoute(0, 0, array('layout'=>'tags','tagid'=>$tag->slug)) ) :
			JRoute::_( FlexicontentHelperRoute::getTagRoute($tag->slug) ) ;
	}
	$tag_link = & $tag_links[$tag_id];
	$display = $link_to_view ?
		'<a class="fc_tags fc_tag_' .$tag->id. ' link_' .$field->name. '" href="' . $tag_link . '">' . $tag->name . '</a>' :
		'<span class="fc_tags fc_tag_' .$tag->id. ' nolink_' .$field->name. '">' . $tag->name . '</span>' ;
	$field->{$prop}[] = $pretext. $display .$posttext;
	
	// Some extra data
	$field->value[] = $tag->name; 
endforeach;