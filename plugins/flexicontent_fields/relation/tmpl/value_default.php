<?php
foreach ($related_items_sets as $n => $related_items)
{
	// Skip empty if not in field group
	if (empty($related_items) && !$is_ingroup)
	{
		continue;
	}

	$HTML = new stdClass();



	// ***
	// *** --01-- Create the items list
	// ***

	$HTML->items_list = '';

	if ($disp->item_list && !empty($related_items))
	{
		// Implode using the configured separator
		$HTML->items_list = array();
		foreach($related_items as $rel_item)
		{
			// NOTE: besides normal item data, like ...->title, ...->created, ...->fields
			// you can also use ...->ri_html and ->ri_url (the display HTML of the related item)
			$HTML->items_list[$rel_item->id] = $rel_item->ri_html;
		}

		// Implode using the configured separator
		$HTML->items_list = implode($separatorf, $HTML->items_list);

		// Apply opening / closing texts
		$HTML->items_list = $HTML->items_list ? $opentag . $HTML->items_list . $closetag : '';
	}



	// ***
	// *** --02-- Create the submit button for auto related item
	// ***

	$HTML->submit_related_btn = '';

	if ($disp->submit_related_btn)
	{
		// Force single button display, if no items list HTML
		$submit_related_position = !$HTML->items_list ? 0 : $submit_related_position;

		$_btn_text = new stdClass();
		$_btn_text->title = $field->parameters->get( 'auto_relate_submit_title', 'FLEXI_RIFLD_SUBMIT_NEW_RELATED');
		$_btn_text->tooltip = $field->parameters->get( 'auto_relate_submit_text', 'FLEXI_RIFLD_SUBMIT_NEW_RELATED_TIP');

		$auto_relations[0] = new stdClass();
		$auto_relations[0]->itemid  = $item->id;
		$auto_relations[0]->fieldid = $field->id;

		$category = null;
		$_show_to_unauth = $field->parameters->get( 'auto_relate_show_to_unauth', 0);

		$HTML->submit_related_btn = flexicontent_html::addbutton(
			$field->parameters, $category, $submit_related_menu_itemid, $_btn_text, $auto_relations, $_show_to_unauth
		);
	}



	// ***
	// *** --03-- Create total info
	// ***

	$HTML->total_info = '';

	if ($disp->total_info)
	{
		$total_count = isset($options->total) ? $options->total : count($related_items);
		$total_append_text = JText::_($field->parameters->get('total_append_text', ''));

		// show_total_only: 2 , dictates to hide if empty, thus skip display if zero items were found
		// (e.g. according to configuration we may not include unpublished items)
		if ($show_total_only !== 2 || $total_count)
		{
			$HTML->total_info = '
				<div class="fcrelation_field_total">
					' . $total_count . ' ' . $total_append_text . '
				</div>';
		}
	}



	$field->{$prop}[$n] = ''
		. $HTML->total_info
		. ($submit_related_position == 0 || $submit_related_position == 2 ? $HTML->submit_related_btn : '')
		. $HTML->items_list
		. ($submit_related_position == 1 || $submit_related_position == 2 ? $HTML->submit_related_btn : '')
		;

	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}