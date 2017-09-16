<?php

/**
 * (Popup) Gallery layout  --  widgetkit
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
	$group_str = $group_name ? 'data-spotlight-group="'.$group_name.'"' : '';
	$field->{$prop}[] = $pretext.
		'<a style="'.$style.'" href="'.$srcl.'" class="fc_image_thumb" data-lightbox="on" data-spotlight="effect:bottom" '.$group_str.' title="'.$title_attr.'">
			'.$img_legend.'
			<div class="overlay">
				'.'<b>'.$title.'</b>: '.$desc.'
			</div>
		</a>'
		.$inline_info.$posttext;
}