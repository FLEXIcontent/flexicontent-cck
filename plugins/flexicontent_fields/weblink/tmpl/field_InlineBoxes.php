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
				'link' => $value, 'title' => '', 'hits' => 0
			);
		}
		if ( empty($value['link']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

		$fieldname_n = $fieldname.'['.$n.']';
		$elementid_n = $elementid.'_'.$n;

		// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
		$value['link'] = !empty($value['link']) ? $value['link'] : $default_link;
		$value['link'] = htmlspecialchars( JStringPunycode::urlToUTF8($value['link']), ENT_COMPAT, 'UTF-8' );
		$has_value_class = $value['link'] ? ' fc-has-value' : '';

		$link = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urllink-lbl" for="'.$elementid_n.'_link">'.JText::_( 'FLEXI_FIELD_URL' ).'</label>
				<input ' . $ff_events . ' class="urllink ' . $input_classes . ' ' . $link_classes . '" name="'.$fieldname_n.'[link]" id="'.$elementid_n.'_link" type="text" value="'.$value['link'].'" ' . $link_attribs . '/>
			</div>';

		$title = '';
		if ($usetitle)
		{
			$value['title'] = !empty($value['title']) ? $value['title'] : $default_title;
			$value['title'] = htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8');
			$has_value_class = $value['title'] ? ' fc-has-value' : '';

			$title = '
			<div class="' . $box_classes . '">
				<label class="' . $lbl_classes . $has_value_class . ' fc-lbl urltitle-lbl" for="'.$elementid_n.'_title">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).'</label>
				<input ' . $ff_events . ' class="urltitle ' . $input_classes . '" name="'.$fieldname_n.'[title]" id="'.$elementid_n.'_title" type="text" size="'.$size.'" value="'.$value['title'].'" />
			</div>';
		}

		$hits = '';
		if ($usehits) {
			$hits = (int) @ $value['hits'];
			$has_value_class = ' fc-has-value';

			$hits = '
				<div class="' . $input_grp_class . ' fc-xpended-row">
					<label class="' . $add_on_class . ' fc-lbl urlhits-lbl" for="'.$elementid_n.'_hits">'.JText::_( 'FLEXI_FIELD_HITS' ).'</label>
					<input class="urlhits fc_hidden_value ' . $has_value_class . '" name="'.$fieldname_n.'[hits]" id="'.$elementid_n.'_hits" type="text" value="'.$hits.'" />
					<span class="' . $add_on_class . ' hitcount">'.$hits.'</span>
				</div>
				';
		}

		$field->html[] = '
			'.($use_ingroup || !$multiple ? '' : '
			<div class="'.$input_grp_class.' fc-xpended-btns">
				'.$move2.'
				'.$remove_button.'
				'.(!$add_position ? '' : $add_here).'
			</div>
			').'
			'.($fields_box_placing ? '<div class="fcclear"></div>' : '').'
			<div class="fc-field-props-box">
			'.$link.'
			'.$title.'
			'.$hits.'
			</div>
			';

		$n++;
		if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
	}