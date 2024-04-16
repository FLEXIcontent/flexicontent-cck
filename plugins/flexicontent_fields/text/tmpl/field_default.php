<?php
	$box_classes = empty($simple_form_layout)
		? $input_grp_class . ' fc-xpended'
		: 'fc-floated-labels-box';
	$lbl_classes = empty($simple_form_layout)
		? $add_on_class
		: 'fc-floated-lbl';
	$input_classes = empty($simple_form_layout)
		? $classes . ' fcfield_textval '
		: $classes . ' fcfield_textval fc-floated-lbl-input';
	$ff_events = 'onfocus="jQuery(this).prev().addClass(\'fc-has-value\');" onblur="if (this.value===\'\') jQuery(this).prev().removeClass(\'fc-has-value\');"';
	
	$field->using_inner_label = true;

	$n = 0;


	foreach ($field->value as $value)
	{
		$value = $value ?? ''; // value maybe null
		if (!strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not inside a field group

		$fieldname_n = $fieldname.'['.$n.']';
		$elementid_n = $elementid.'_'.$n;

		$has_value_class = $value ? ' fc-has-value' : '';

		$lbl = empty($simple_form_layout) ? '' : '
			<label class="' . $lbl_classes . $has_value_class . '" for="'.$elementid_n.'" data-for="'.$elementid_n.'">
				' . $field->label . '
			</label>';

		// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
		$text_field =
			'<input value="'.htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' ).'" ' . $ff_events . ' '
				.$validate_mask.' id="'.$elementid_n.'" name="'.$fieldname_n.'" class="'. $input_classes .'" type="text" '.$attribs.'
			/>';

		$field->html[] = $pretext . '
			<div class="' . $box_classes . '">
				'.($auto_value || $select_field_placement !== 0 ? '' : $select_field).'
				' . $lbl . $text_field . '
				'.($auto_value || $select_field_placement !== 1 ? '' : $select_field).'
			</div>
			' . $posttext . '
			' . (!$add_ctrl_btns || $auto_value ? '' : '
			<div class="'.$btn_group_class.' fc-xpended-btns">
				'.$move2.'
				'.$remove_button.'
				'.(!$add_position ? '' : $add_here).'
			</div>
			');

		$n++;
		if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
	}
