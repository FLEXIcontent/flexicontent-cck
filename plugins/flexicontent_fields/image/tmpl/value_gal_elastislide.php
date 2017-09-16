<?php

/**
 * (Inline) Gallery layout  --  Elastislide
 *
 * This layout does not support inline_info, pretext, posttext
 *
 * Note: This layout uses a thumbnail list created with -- large -- size thubmnails, these will be then thumbnailed by the JS gallery code
 */

$i = -1;
foreach ($values as $n => $value)
{
	// Include common layout code for preparing values, but you may copy here to customize
	$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
	if ($result === _FC_CONTINUE_) continue;
	if ($result === _FC_BREAK_) break;

	$title_attr = $desc ? $desc : $title;
	$img_legend_custom ='
		 <img src="'.JUri::root(true).'/'.$src.'" alt ="'.$alt.'"'.$legend.' class="'.$class.'"
		 	data-large="' . JUri::root(true).'/'.$srcl . '" data-description="'.$title_attr.'" itemprop="image"/>
	';
	$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
	$field->{$prop}[] = '
		<li><a href="javascript:;" class="fc_image_thumb">
			'.$img_legend_custom.'
		</a></li>';
}