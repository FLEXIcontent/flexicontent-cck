	var fcfield_relation = {};

	fcfield_relation.add_related = function(el)
	{
		if (!parseInt(jQuery(el).val())) return false;

		var val_box = el;
		while ((val_box = val_box.parentNode) && val_box.className.indexOf('fcfield-relation-value_box') < 0);

		var elementid = val_box.getAttribute('data-elementid'),
			item_selector = jQuery(el),
			item_id = parseInt(item_selector.val()),
			cat_selector = jQuery('#' + elementid + '_cat_selector'),
			cat_id = cat_selector.val(),
			selected_item = item_selector.find('option:selected'),
			item_title = selected_item.text(),
			itemid_catid = item_id + ':' + cat_id;

		//window.console.log(itemid_catid);

		var selitems_selector = jQuery('#' + elementid);
		selitems_selector.append(jQuery('<option>', {
			value: itemid_catid,
			text: item_title,
			selected: 'selected'
		})).trigger('change');

		return true;
	}


	fcfield_relation.mark_selected = function(elementid)
	{
		var item_selector = jQuery('#' + elementid + '_item_selector');
		var selitems_selector = jQuery('#' + elementid);

		var selitems_arr = selitems_selector.val();
		selitems_arr = !!selitems_arr ? selitems_arr : [];

		for (var i = 0; i < selitems_arr.length; i++)
		{
			selitems_arr[i] = parseInt(selitems_arr[i]).toString();
		}
		//window.console.log(selitems_arr);

		[].forEach.call(
			document.querySelectorAll('#' + item_selector.attr('id') + ' option'),
			function(el)
			{
				if (selitems_arr.indexOf(el.value) >= 0)
				{
					el.disabled = true;
					el.setAttribute('disabled', 'disabled');
				}
				else
				{
					el.disabled = false;
					el.removeAttribute('disabled');
				}
			}
		);

		// Clear item selection
		setTimeout(function() {
			item_selector.val('').trigger('change');
		}, 50);

		return true;
	}


	fcfield_relation.selected_items_modified = function(el)
	{
		var val_box = el;
		while ((val_box = val_box.parentNode) && val_box.className.indexOf('fcfield-relation-value_box') < 0);

		var elementid = val_box.getAttribute('data-elementid');

		fcfield_relation.mark_selected(elementid);

		var selitems_selector = jQuery('#' + elementid);
		setTimeout(function() {
			var non_selected = selitems_selector.find('option:not(:selected)');
			if (non_selected.length)
			{
				non_selected.remove();
				selitems_selector.trigger('change');
			}
		}, 50);

		return true;
	}


	fcfield_relation.cat_selector_change = function(el)
	{
		var val_box = el;
		while ((val_box = val_box.parentNode) && val_box.className.indexOf('fcfield-relation-value_box') < 0);
		
		var elementid = val_box.getAttribute('data-elementid'),
			item_id = val_box.getAttribute('data-item_id'),
			field_id = val_box.getAttribute('data-field_id'),
			item_type = val_box.getAttribute('data-item_type'),
			item_lang = val_box.getAttribute('data-item_lang');

		var cat_selector = document.getElementById(elementid + '_cat_selector');
		var catid = parseInt(cat_selector.value);

		var item_selector = jQuery('#' + elementid + '_item_selector');

		// Remove any previous error message
		item_selector.parent().find('.fc-relation-field-error').remove();

		// Check for empty category
		if (!catid)
		{
			item_selector.empty();
			item_selector.append('<option value="">-</option>');
			item_selector.val('').trigger('change');  // trigger change event to update select2 display
			item_selector.show();
			return;
		}

		var sel2_item_selector = jQuery('#s2id_' + elementid + '_item_selector');
		sel2_item_selector.hide();

		var loading = jQuery('<div class="fc_loading_msg" style="position:absolute; background-color:transparent;"></div>');
		loading.insertAfter(sel2_item_selector);

		var ajax_data = {
			task: 'call_extfunc',
			omethod: 'html', /* unused */
			exttype: 'plugins',
			extfolder: 'flexicontent_fields',
			extname: 'relation',
			extfunc: 'getCategoryItems',
			field_id: field_id,
			catid: catid
		};

		if (item_id)
		{
			ajax_data.item_id = item_id;
		}
		else
		{
			ajax_data.type_id = item_type;
			ajax_data.lang_code = item_lang;
		}

		jQuery.ajax({
			type: 'POST',
			url: 'index.php?option=com_flexicontent&tmpl=component&format=raw',
			dataType: 'json',
			data: ajax_data
		}).done( function(data) {
			//window.console.log ('Got data for:' + cat_selector.attr('id'));
			item_selector.empty();

			if (data=='')                   item_selector.append('<option value="">' + Joomla.JText._('FLEXI_RIFLD_ERROR') + '</option>');
			else if (data.error!='')        item_selector.append('<option value="">-</option>');
			else if (!data.options.length)  item_selector.append('<option value="">' + Joomla.JText._('FLEXI_RIFLD_NO_ITEMS') + '</option>');
			else {
				item_selector.append('<option value="">- ' + Joomla.JText._('FLEXI_RIFLD_ADD_ITEM', true) + '-</option>');
				var item;
				for(var i=0; i<data.options.length; i++)
				{
					item = data.options[i];
					item_selector.append(
						jQuery('<option>', {
							value: item.item_id,
							text: item.item_title
						})
					);
				}
			}

			// Disable selected values (this will also trigger change event to update select2 display)
			fcfield_relation.mark_selected(elementid);

			// Remove loading animation
			sel2_item_selector.next().remove();

			// Show the item selector or display the error message
			if (data && data.error!='')
				jQuery('<span class="add-on fc-relation-field-error"> <span class="icon-warning"></span> '+data.error+'</span>').insertAfter(item_selector);
			else
				sel2_item_selector.show();
		});

		return true;
	}