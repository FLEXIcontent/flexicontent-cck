<?php

for ($n = 0; $n < $max_count; $n++)
{
	$field->html[$n] = '
		'.(!$add_ctrl_btns ? '' : '
		<div class="'.$input_grp_class.' fc-xpended-btns">
			'.$move2.'
			'.$remove_button.'
			'.$togglers.'
			'.(!$add_position ? '' : $add_here).'
		</div>

		') . (empty($isFlexBox) ? '' : '
		<div class="fc_form_flex_box">
		');

	// Append item-form display HTML of the every field in the group
	$i = 0;
	foreach($grouped_fields as $field_id => $grouped_field)
	{
		$hide_field = '';
		if ($grouped_field->defaultviewbehavior == 0) {
			if ($grouped_field->defaultviewbehavior !== $grouped_field->fieldviewbehavior) {
				$target_field = $grouped_field->checkfieldname;
				$target_field_id = $item->fields[$target_field]->id;
				$target_field_value = $grouped_field->checkfieldvalue;
				if ($grouped_fields[$target_field_id]->value[$n] !== $target_field_value) {
					$hide_field = ' style="display:none;"';
				}
			}
		}
		if ($grouped_field->formhidden == 4) continue;
		if ($isAdmin) {
			if ( $grouped_field->parameters->get('backend_hidden')  ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(2,3))) ) continue;
		} else {
			if ( $grouped_field->parameters->get('frontend_hidden') ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(1,3))) ) continue;
		}

		// Check for not-assigned to type fields,
		if (!isset($grouped_field->html[$n]))
		{
			if ($form_empty_fields)
			{
				$grouped_field->html[$n] = '<i>' . $form_empty_fields_text . '</i>';
			}
			else continue;
		}

		$lbl_class = 'fc_sub_label';
		$lbl_title = '';

		// Field has tooltip
		$edithelp = $grouped_field->edithelp ? (int) $grouped_field->edithelp : 1;
		if ($grouped_field->description && ($edithelp === 1 || $edithelp === 2))
		{
			$lbl_class .= ($edithelp === 2 ? ' fc_tooltip_icon ' : ' ') . $tooltip_class;
			$lbl_title .= flexicontent_html::getToolTip(trim($field->label, ':'), $grouped_field->description, 0, 1);
		}

		$gf_inline_desc = $grouped_field->description && $edithelp === 3
			? sprintf($alert_box, '', 'info', 'fc-nobgimage', $grouped_field->description)
			: '';
		$gf_compactedit = $compact_edit && isset($compact_edit_excluded[$field_id])
			? ' fcAlwaysVisibleField'
			: '';
		$gf_elementid_n = 'custom_' . $grouped_field->name . '_' . $n;
		$use_flex_grow  = (int) $field->parameters->get('use_flex_grow', 1);

		$gf_display_label_form = (int) $grouped_field->parameters->get('display_label_form', 1);

		$field->html[$n] .= (empty($isFlexBox) ? '' : '
		<div class="fc_form_flex_box_item' . ($use_flex_grow ? ' use_flex_grow' : '') . '" style="margin: 0;">') . '
			<div class="control-group control-fc_subgroup fcfieldval_container_outer' . $gf_compactedit . '"'.($hide_field !== '' ? $hide_field : '').'>
				<div
					class="control-label ' . ($gf_display_label_form === 2 ? 'fclabel_cleared' : '') . '"
					style="' . ($gf_display_label_form < 1 ? 'display:none;' : '') .'"
				>
					' . ($gf_display_label_form < 1 ? '' : '
					<label id="' . $gf_elementid_n . '-lbl" class="' . $lbl_class . '" title="' . $lbl_title . '" data-for="' . $gf_elementid_n . '">
						' . $grouped_field->label . '
					</label>
					') . '
				</div>

				<div class="controls" 	style="' . ($gf_display_label_form !== 1 ? 'margin: 0' : '') . '" >
					<div class="fcfieldval_container valuebox fcfieldval_container_'.$grouped_field->id.'" >
						' . $gf_inline_desc . '
						' . $grouped_field->html[$n] . '
					</div>
				</div>
			</div>
		' . (empty($isFlexBox) ? '' : '
		</div>
		');
		$i++;
	}

	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
