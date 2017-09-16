<?php

/**
 * (Popup) Gallery layout  --  Photoswipe
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

	$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
	$field->{$prop}[] = $pretext.
		'<a style="'.$style.'" href="'.$srcl.'" '.$group_str.' class="fc_image_thumb">
			'.$img_legend.'
		</a>'
		.$inline_info.$posttext;
}