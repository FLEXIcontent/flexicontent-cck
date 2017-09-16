<?php

/**
 * Link to item layout (typically useful in multi-item views)
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

	// Create link to current item
	$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));

	// Create HTML linking to current item
	$field->{$prop}[] =
	'<span style="display: inline-block; text-align:center; ">
		<a href="'.$item_link.'" style="display: inline-block;">
		'.$img_nolegend.'
		</a><br/>'
		.($_method == 'display_single_total' || $_method == 'display_single_total_link' ? '
		<span class="fc_img_total_data badge badge-info" style="display: inline-block;" >
			'.count($values).' '.JText::_('FLEXI_IMAGES').'
		</span>' : '').'
	</span>';
	
	// If single display and not in field group then do not add more images
	if (!$is_ingroup && $isSingle)
	{
		break;
	}
}