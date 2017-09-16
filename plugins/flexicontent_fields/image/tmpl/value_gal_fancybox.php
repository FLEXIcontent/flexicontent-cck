<?php

/**
 * (Popup) Gallery layout  --  Fancybox
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

	$title_attr = $desc ? '<span class=\'badge\'>'.$title.'</span> '.$desc : $title;
	$group_str = $group_name ? 'data-fancybox-group="'.$group_name.'"' : '';
	$field->{$prop}[] = $pretext.
		'<a style="'.$style.'" href="'.$srcl.'"  class="fc_image_thumb fancybox" '.$group_str.' title="'.$title_attr.'">
			'.$img_legend.'
		</a>'
		.$inline_info.$posttext;
}