<?php

/**
 * Thumbnail (basic) layout
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

	$field->{$prop}[] = $pretext . $img_legend . $inline_info . $posttext;
}