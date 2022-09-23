<?php

for ($n = 0; $n < $max_count; $n++)
{
	$default_html = array();

	foreach($grouped_fields as $_grouped_field)
	{
		// Check field is assigned to current item type, and get item's field object
		if (!isset($item->fields[$_grouped_field->name]))
		{
			continue;
		}
		$grouped_field = $item->fields[$_grouped_field->name];

		// Skip (hide) label for field without value (is such behaviour was configured)
		if ( (!isset($grouped_field->{$method.'_arr'}[$n]) || !strlen($grouped_field->{$method.'_arr'}[$n]))  &&  isset($hide_lbl_ifnoval[$grouped_field->id]) )
		{
			continue;
		}

		// Add field's HTML (optionally including label)
		if ($grouped_field->field_type == 'fieldgroup') {
			$default_html[] = '<div class="fc-field-box field_' . $grouped_field->name . '">'.
			$item->fields[$_grouped_field->name]->display_arr.'
			</div>';
		} else {
			$default_html[] = '
			<div class="fc-field-box field_' . $grouped_field->name . '">
				'.($grouped_field->parameters->get('display_label') ? '
				<span class="flexi label">'.$grouped_field->label.'</span>' : '').
				(isset($grouped_field->{$method.'_arr'}[$n]) ? '<div class="flexi value">'.$grouped_field->{$method.'_arr'}[$n].'</div>' : '').'
			</div>';
		}
	}

	if (count($default_html))
	{
		$field->{$prop}[] = $pretext . implode('<div class="fcclear"></div>', $default_html).'<div class="fcclear"></div>' . $posttext;
	}
}
