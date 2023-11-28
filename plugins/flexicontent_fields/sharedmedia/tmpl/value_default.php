<?php

$n = 0;

foreach ($values as $value)
{
	// Skip empty value but add empty placeholder if inside fieldgroup
	if ( empty($value) )
	{
		if ($is_ingroup)
		{
			$field->{$prop}[$n++] = '';
		}
		continue;
	}
	//echo '<pre>'; print_r($value); echo '</pre>';

	// Compatibility with deprecated fields
	if (empty($value['api_type'])) $value['api_type'] = isset($value['videotype']) ? $value['videotype'] : (isset($value['audiotype']) ? $value['audiotype'] : '');
	if (empty($value['media_id']))  $value['media_id']  = isset($value['videoid'])   ? $value['videoid']   : (isset($value['audioid'])   ? $value['audioid']   : '');

	// Skip empty value but add empty placeholder if inside fieldgroup
	if ( (empty($value['api_type']) || empty($value['media_id'])) && empty($value['embed_url']) )
	{
		if ($is_ingroup)
		{
			$field->{$prop}[$n++] = '';
		}
		continue;
	}

	$duration = intval($value['duration']);
	if ($display_duration && $duration)
	{
		$h = $duration >= 3600  ?  intval($duration/3600)  :  0;
		$m = $duration >= 60    ?  intval($duration/60 - $h*60)  :  0;
		$s = $duration - $m*60 -$h*3600;
		$duration_str  = $h > 0  ? $h.":" : "";
		$duration_str .= str_pad($m,2,'0',STR_PAD_LEFT).':';
		$duration_str .= str_pad($s,2,'0',STR_PAD_LEFT);
	}
	else $duration_str = '';

	// Create field's html
	$html_meta = '
		'.($display_title  && !empty($value['title'])  ? '<h'.$headinglevel.'>' . $value['title']  . '</h'.$headinglevel.'>' : '') .'
		'.($display_author && !empty($value['author']) ? '<span class="label text-white bg-info label-info label-small fc_sm_author-lbl">'.JText::_('Author').'</span> <b class="fc_sm_author">' . $value['author'] . '</b> ' : '') .'
		'.($duration_str ? '<span class="label text-white bg-info label-info label-small fc_sm_duration-lbl">'.JText::_('Duration').'</span> <b class="fc_sm_duration">'.$duration_str.'</b> ' : '') .'
		'.($display_description && !empty($value['description']) ? '<div class="description">' . $value['description'] . '</div>' : '');

	$player_id     = 'player-' . $field->name . '_' . $item->id . '_' . $n;
	$player_params = '';//'&origin=http://localhost';
	$player_html   = '';

	if (!empty($value['embed_url']) && (empty($value['media_id']) || empty($value['api_type'])))
	{
		$embed_url = $value['embed_url'];
		$_show_related = '';
		$_show_srvlogo = '';
	}
	else
	{
		$content_id = $value['media_id'];
		switch($value['api_type'])
		{
			case 'youtube':
				$embed_url = '//www.youtube' . ($privacy_embeed ? '-nocookie' : '') . '.com/embed/' . $content_id;
				$_show_related = '&amp;rel=0';
				$_show_srvlogo = '&amp;modestbranding=1&amp;maxwidth=0';
				//$player_params = '&amp;origin=https://plyr.io&amp;iv_load_policy=3&amp;playsinline=1&amp;showinfo=0&amp;rel=0&amp;enablejsapi=1';
				//$player_html   = '<div class="fc_use_plyr" id="' . $player_id . '" data-plyr-provider="youtube" data-plyr-embed-id="' . $content_id . '"></div>';
				break;
			case 'vimeo':
				$embed_url = '//player.vimeo.com/video/' . $content_id;
				$_show_related = '';
				$_show_srvlogo = '';
				//$player_params = '&amp;loop=false&amp;byline=false&amp;portrait=false&amp;title=false&amp;speed=true&amp;transparent=0&amp;gesture=media';
				//$player_html   = '<div class="fc_use_plyr" id="' . $player_id . '" data-plyr-provider="vimeo" data-plyr-embed-id="' . $content_id . '"></div>';
				break;
			case 'dailymotion':
				$embed_url = '//www.dailymotion.com/embed/video/' . $content_id;
				$_show_related = '&amp;related=0';
				$_show_srvlogo = '&amp;logo=0';
				break;
			default:  // For embed.ly , the full URL is inside content ID
				$embed_url = $content_id;
				$_show_related = '';
				$_show_srvlogo = '';
				break;
		}
	}

	// Player dimensions
	$_width  = ($display_edit_size_form && (int) @ $value['width'])  ? (int)$value['width']  : $width;
	$_height = ($display_edit_size_form && (int) @ $value['height']) ? (int)$value['height'] : $height;

	if ($player_html)
	{
		$player_html = '
			<div class="fc_sharedmedia_player_outer">
				' . $player_html . '
			</div>
		';
	}
	else
	{
		$player_url = $embed_url ? $embed_url : 'about:blank';
		$player_url .= (strstr($player_url, '?') ? '&'  : '?') . 'autoplay=' . $autostart . $player_params . $_show_related . $_show_srvlogo;

		$player_html = '
		<div class="fc_sharedmedia_player_outer" id="' . $player_id . '_box"
			style="min-width: ' . $_width . 'px; min-height: ' . $_height . 'px; border: none; overflow:hidden;"
		>
			<iframe id="' . $player_id . '"
				class="fc_sharedmedia_player_frame seamless"
				src="' . $player_url . '"
				style="min-width:' . $_width . 'px; min-height:' . $_height . 'px; border: none; overflow:hidden;"
				allowfullscreen allowtransparency allow="autoplay"
			>
			</iframe>
		</div>';
	}

	$field->{$prop}[$n] = $pretext
		. ($player_position ? '' : $player_html)
		. $html_meta
		. ($player_position ? $player_html : '')
		. $posttext;

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
