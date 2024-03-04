<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

for ($n = 0; $n < $max_count; $n++)
{
	$field->html[$n] = '
		'.(!$add_ctrl_btns ? '' : '
		<div class="'.$btn_group_class.' fc-xpended-btns">
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

		/**
		 * Create the data-shownon attribute for the control-group container of the field
		 */
		$dataShowOn        = '';
		$dataShowOnPattern = '';

		$_showon = $grouped_field->parameters->get('showon', '');
		if ($_showon)
		{
			$showOnConditions  = FormHelper::parseShowOnConditions($_showon, $_formControl = 'custom', $_formGroup = '');
			$dataShowOnPattern = $_showon ? 'data-showon-pattern=\'' . json_encode($showOnConditions) . '\'' : '';

			// Cases handled
			$cases_handled = [];
			foreach($showOnConditions as $showCase)
			{
				// When a new field group value is added, we will use this to replace it with the correct field index and set it as data-shown, then initialize the showon script
				if (!in_array($showCase['field'], $cases_handled)) {
					$dataShowOnPattern = str_replace($showCase['field'], $showCase['field'] . '[__n__]', $dataShowOnPattern);
				}
				$cases_handled[] = $showCase['field'];
			}
			$dataShowOn = str_replace('[__n__]', '[' . $n . ']', $dataShowOnPattern);
			$dataShowOn = str_replace('data-showon-pattern=', 'data-showon=', $dataShowOn);

			static $_shown_added = false;
			if (!$_shown_added)
			{
				$_shown_added = true;
				FLEXI_J40GE
					? Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('showon')
					: HTMLHelper::_('script', 'jui/cms.js', array('version' => 'auto', 'relative' => true));
			}
		}

		$field->html[$n] .= (empty($isFlexBox) ? '' : '
		<div class="fc_form_flex_box_item' . ($use_flex_grow ? ' use_flex_grow' : '') . '" style="margin: 0;">') . '
			<div class="control-group control-fc_subgroup fcfieldval_container_outer' . $gf_compactedit . '" ' . $dataShowOn . ' ' . $dataShowOnPattern . '>
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
