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

	// Check if link is 'internal' aka 'safer', but make it absolute before checking it !
	$link       = $this->make_absolute_url($value['link']);
	$isInternal = JUri::isInternal($link);
	$isVideo    = $playback_videos && (strpos($value['link'], 'youtube') !== false || strpos($value['link'], 'vimeo') !== false);

	// If not using property or property is empty, then use default property value
	// NOTE: default property values have been cleared, if (propertyname_usage != 2)
	$title    = ($usetitle  && !empty($value['title'])   )  ?  JText::_($value['title'])    : $default_title;
	$linktext = ($usetext   && !empty($value['linktext']))  ?  JText::_($value['linktext']) : $default_text;
	$class    = ($useclass  && !empty($value['class'])   )  ?  $value['class']    : $default_class;
	$id       = ($useid     && !empty($value['id'])      )  ?  $value['id']       : $default_id;
	$target   = ($usetarget && !empty($value['target'])  )  ?  $value['target']   : $default_target;
	$image    = ($useimage  && !empty($value['image'])  )   ?  $value['image']    : $default_image;
	$hits     = isset($value['hits']) ? (int) $value['hits'] : 0;

	// New window is forced for external links
	$target = $isInternal ? $target : '_blank';

	// Calculate a REL attribute of the link
	$rel = ''
		// Prevent external pages from having access the original window object (the 'opener' window)
		. (!$isInternal ? 'noopener noreferrer' : '')

		// 1: nofollow all, 0: nofollow external, -1: allow following (indexing) any link
		. ($add_rel_nofollow == 1 || ($add_rel_nofollow == 0 && !$isInternal) ? ' nofollow' : '');

	$link_params  = '';
	$playbackicon = '';

	static $fbox_loaded = null;

	if ($isVideo)
	{
		if ($fbox_loaded)
		{
			$fbox_loaded = true;
			flexicontent_html::loadFramework('fancybox');
		}
		
		// Video providers natively supported by this JS gallery
		if (strpos($value['link'], 'youtube') !== false || strpos($value['link'], 'vimeo') !== false)
		{
			$link_params  .= ' data-fancybox="item_' . $item->id . '_field_' . $field->id . '" ';
			$playbackicon  = '<div style="font-size: 48px; opacity: 0.8; background: white; position: absolute; right: 50%; bottom: calc(50% - 12px);"><div class="icon-video-2" style="color: font-size: 48px;"></div></div>';
		}

		// Non video URL or a video provider not supported natively by this JS gallery
		else
		{
			$link_params .= ' data-type="iframe"';
		}

		//$link_params .= ' onclick="fc_field_dialog_handle_'.$field->id.' = fc_showDialog(jQuery(this).attr(\'href\'), \'fc_modal_popup_container\', 0, 0, 0, 0, {title: \'\'}); return false;" ';
	}
	elseif ($target === '_popup')
	{
		$link_params .= ' onclick="fc_field_dialog_handle_'.$field->id.' = fc_showDialog(jQuery(this).attr(\'href\'), \'fc_modal_popup_container\', 0, 0, 0, 0, {title: \'\'}); return false;" ';
	}
	else if ($target === '_modal')
	{
		$link_params .= ' onclick="window.open(this.href, \'targetWindow\', \'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=600,height=600\'); return false;" ';
	}
	else
	{
		$link_params .= $target ? ' target="'.$target.'"' : '';
	}

	$link_params .= ''
		. ($title  ? ' title="' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '"' : '')
		. ($id     ? ' id="' . $id . '"' : '')
		. ($class ? ' class="' . $class . '"' : '')
		. ($rel    ? ' rel="' . $rel . '" ' : '');

	// Direct access to the web-link, hits counting not possible
	if ($isVideo || $field->parameters->get('use_direct_link', 0) || $field->parameters->get('link_source', 0) == -1)
	{
		$href = $link;
	}

	// Indirect access to the web-link, via calling FLEXIcontent component, thus counting hits too
	else
	{
		$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$item->id.'&ord='.($n+1).'&task=weblink' );
	}

	// If linking text is  URL convert from Punycode to UTF8
	if (empty($linktext))
	{
		$linktext = $title ? $title : $this->cleanurl(JStringPunycode::urlToUTF8($link));
	}

	// Create URL image with playback icon if it is video
	$img_tag = '';

	if ($display_image && $image)
	{
		$img_src = file_exists(JPATH_ROOT . '/' . $image)
			? JUri::root() . $image
			: $image;
		$img_tag = ($display_image == 1 ? '<br>' : '') . '
			<div style="position: relative; display: inline-block;">
				<img src="' . $img_src . '" alt="' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '" width="' . $image_w . '" height="' . $image_h . '" style="width: ' . $image_w . 'px; width: ' . $image_h . 'px; "/>
				' . $playbackicon . '
			</div>
		';
	}

	// Create indirect link to web-link address with custom displayed text
	$html = '<a href="' .$href. '" '.$link_params.' itemprop="url">'
		. ($img_tag && $display_image == 2 ? '' : $linktext)
		. $img_tag
	. '</a>';

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