<?php

/**
 * Link to URL layout (optionally using a popup)
 *
 * This layout supports inline_info, pretext, posttext
 */

$i = -1;
foreach ($values as $n => $value)
{
	// Include common layout code for preparing values, but you may copy here to customize
	$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
	if ($result === _FC_CONTINUE_) continue;
	if ($result === _FC_BREAK_) break;

	// CASE: Just image thumbnail since url link is empty
	if (!$urllink)
	{
		$field->{$prop}[] = $pretext.'
		<div class="fc_img_container">
			'.$img_legend.'
		</div>
		'.$inline_info.$posttext;
	}

	// CASE: Link to URL that opens inside a popup via multibox
	else if ($url_target=='multibox')
	{
		$field->{$prop}[] = $pretext.'
		<script>document.write(\'<a style="'.$style.'" href="'.$urllink.'" id="mb'.$uniqueid.'" class="fc_image_thumb mb" rel="width:\'+(jQuery(window).width()-150)+\',height:\'+(jQuery(window).height()-150)+\'">\')</script>
			'.$img_legend.'
		<script>document.write(\'</a>\')</script>
		<div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
		'.$inline_info.$posttext;
	}

	// CASE: Link to URL that opens inside a popup via fancybox
	else if ($url_target=='fancybox')
	{
		$field->{$prop}[] = $pretext.'
		<span class="fc_image_thumb" style="'.$style.'; cursor: pointer;" '.
			'onclick="jQuery.fancybox.open([{ type: \'iframe\', href: \''.$urllink.'\', topRatio: 0.9, leftRatio: 0.9, title: \''.($desc ? $title.': '.$desc : $title).'\' }], { padding : 0});"
		>
			'.$img_legend.'
		</span>
		'.$inline_info.$posttext;
	}

	// CASE: Just link to URL without popup
	else
	{
		$field->{$prop}[] = $pretext.'
		<a href="'.$urllink.'" target="'.$url_target.'">
			'.$img_legend.'
		</a>
		'.$inline_info.$posttext;
	}
}