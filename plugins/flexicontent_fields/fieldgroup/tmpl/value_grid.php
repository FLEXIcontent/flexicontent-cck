<?php

$header_html = '';

for ($n = 0; $n < $max_count; $n++)
{
	$default_html = array();
	$labels_html  = array();

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

		if (!$header_html)
		{
			$labels_html[] = '
				<div class="fg_grid_item_lbl">
					<div>' . $grouped_field->label . '</div>
				</div>';
		}

		// Add field's HTML (optionally including label)
		$default_html[] = '
		<div class="fg_grid_item_val fc-field-box field_' . $grouped_field->name . '">
			<div>
				' . (isset($grouped_field->{$method.'_arr'}[$n]) ? ''.$grouped_field->{$method.'_arr'}[$n] : '') . '
			</div>
		</div>';
	}

	if (count($default_html))
	{
		$field->{$prop}[] = implode('', $default_html);
	}
	if (!$header_html)
	{
		$header_html = implode('', $labels_html);
	}
}

$field->{$prop} = '
<div style="display: grid; grid-template-columns: repeat(' . count($grouped_fields) . ', 1fr);">
	' . $header_html . '
	' . implode('', $field->{$prop}) . '
</div>';
