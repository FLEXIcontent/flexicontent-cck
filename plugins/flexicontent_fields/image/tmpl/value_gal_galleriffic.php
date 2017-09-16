<?php

/**
 * (Inline) Gallery layout  --  Galleriffic
 *
 * This layout does not support inline_info, pretext, posttext
 */

$i = -1;
foreach ($values as $n => $value)
{
	// Include common layout code for preparing values, but you may copy here to customize
	$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
	if ($result === _FC_CONTINUE_) continue;
	if ($result === _FC_BREAK_) break;

	$group_str = '';   // image grouping: not needed / not applicatble
	$field->{$prop}[] =
		'<a href="'.$srcl.'" class="fc_image_thumb thumb" name="drop">
			'.$img_legend.'
		</a>
		<div class="caption">
			<b>'.$title.'</b>
			<br/>'.$desc.'
		</div>';
}