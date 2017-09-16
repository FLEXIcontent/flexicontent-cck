<?php

/**
 * (Popup) Gallery layout  --  Rokbox
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

	$title_attr = $desc ? $desc : $title;
	$group_str = '';   // no support for image grouping
	$field->{$prop}[] = $pretext.
		'<a style="'.$style.'" href="'.$srcl.'" rel="rokbox['.$wl.' '.$hl.']" '.$group_str.' title="'.$title_attr.'" class="fc_image_thumb" data-rokbox data-rokbox-caption="'.$title_attr.'">
			'.$img_legend.'
		</a>'
		.$inline_info.$posttext;
}