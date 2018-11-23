<?php
	$n = 0;

	$box_classes = empty($simple_form_layout)
		? $input_grp_class . ' fc-xpended-row'
		: 'fc-floated-labels-box';
	$lbl_classes = empty($simple_form_layout)
		? $add_on_class
		: 'fc-floated-lbl';
	$input_classes = empty($simple_form_layout)
		? 'fcfield_textval'
		: 'fcfield_textval fc-floated-lbl-input';
	$ff_events = 'onfocus="jQuery(this).prev().addClass(\'fc-has-value\');" onblur="if (this.value===\'\') jQuery(this).prev().removeClass(\'fc-has-value\');"';

	foreach ($field->value as $value)
	{
		// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
		if ( !is_array($value) )
		{
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array(
				'addr' => $value, 'text' => ''
			);
		}
		if ( empty($value['addr']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

		$fieldname_n = $fieldname.'['.$n.']';
		$elementid_n = $elementid.'_'.$n;

		// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
		$value['addr'] = !empty($value['addr']) ? $value['addr'] : '';
		$has_value_class = $value['addr'] ? ' fc-has-value' : '';

		$addr = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl emailaddr-lbl" for="'.$elementid_n.'_addr">'.JText::_( 'FLEXI_FIELD_EMAILADDRESS' ).'</label>
				<input ' . $ff_events . ' class="emailaddr ' . $input_classes . ' ' . $addr_classes . '" name="'.$fieldname_n.'[addr]" id="'.$elementid_n.'_addr" type="text" value="'.htmlspecialchars(JStringPunycode::emailToUTF8($value['addr']), ENT_COMPAT, 'UTF-8').'" ' . $addr_attribs . '/>
			</div>';

		$text = '';

		if ($usetitle)
		{
			$value['text'] = !empty($value['text']) ? $value['text'] : $default_title;
			$has_value_class = $value['text'] ? ' fc-has-value' : '';

			$text = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl emailtext-lbl" for="'.$elementid_n.'_text">'.JText::_( 'FLEXI_FIELD_EMAILTITLE' ).'</label>
				<input ' . $ff_events . ' class="emailtext ' . $input_classes . '" name="'.$fieldname_n.'[text]"  id="'.$elementid_n.'_text" type="text" size="'.$size.'" value="'.htmlspecialchars($value['text'], ENT_COMPAT, 'UTF-8').'" />
			</div>';
		}

		$field->html[] = '
			'.(!$add_ctrl_btns ? '' : '
			<div class="'.$input_grp_class.' fc-xpended-btns">
				'.$move2.'
				'.$remove_button.'
				'.(!$add_position ? '' : $add_here).'
			</div>
			').'
			'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
			<div class="fc-field-props-box">
			'.$addr.'
			'.$text.'
			</div>
			';

		$n++;
		if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
	}