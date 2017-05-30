<?php

// Optimization, do some stuff outside the loop
$hits_icon_arr = array();
$hits_icon = '';

if ( $display_hits==1 || $display_hits==3 )
{
	if ( !isset($hits_icon_arr[$field->id]) )
	{
		$_tip_class = $display_hits==1 ? ' ' . $tooltip_class : '';
		$_hits_tip  = $display_hits==1 ? ' title="' . flexicontent_html::getToolTip(null, '%s '.JText::_( 'FLEXI_HITS', true ), 0, 0) . '" ' : '';
		$hits_icon_arr[$field->id] = '<span class="fcweblink_icon icon-eye-open '.$_tip_class.'" '.$_hits_tip.'></span>';
	}
	$hits_icon = $hits_icon_arr[$field->id];
}

$n = 0;
foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( empty($value['link']) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}
	
	// If not using property or property is empty, then use default property value
	// NOTE: default property values have been cleared, if (propertyname_usage != 2)
	$title    = ($usetitle && @$value['title']   )  ?  $value['title']    : $default_title;
	$linktext = '';  // no linktext for weblink for extended web link field if this is needed
	$hits     = (int) @ $value['hits'];
	
	$link_params  = $title ? ' title="' . $title . '"' : '';
	$link_params .= $target_param;
	$link_params .= $rel_nofollow;
	
	// Direct access to the web-link, hits counting not possible
	if ( $field->parameters->get( 'use_direct_link', 0 ) )
	{
		$href = $value['link'];
	}

	// Indirect access to the web-link, via calling FLEXIcontent component, thus counting hits too
	else
	{
		$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$item->id.'&ord='.($n+1).'&task=weblink' );
	}
	
	// If linking text is  URL convert from Punycode to UTF8
	if ( empty($linktext) )
	{
		$linktext = $title ? $title : $this->cleanurl( JStringPunycode::urlToUTF8($value['link']) );
	}

	// Create indirect link to web-link address with custom displayed text
	$html = '<a href="' .$href. '" '.$link_params.' itemprop="url">' .$linktext. '</a>';
	
	// HITS: either as icon or as inline text or both
	$hits_html = '';
	if ($display_hits && $hits)
	{
		$hits_html = '
			<span class="fcweblink_hits">
				' . ( $add_hits_img && $hits_icon ? sprintf($hits_icon, $hits) : '') . '
				' . ( $add_hits_txt ? '(' . $hits . '&nbsp;' . JTEXT::_('FLEXI_HITS') . ')' : '') . '
			</span>';

		if ($prop == 'display_hitsonly')
		{
			$html = $hits_html;
		}
		else
		{
			$html .= ' ' . $hits_html;
		}
	}
	
	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;
	
	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
