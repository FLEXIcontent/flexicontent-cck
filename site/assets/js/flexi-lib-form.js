
	function fcfield_add2list(list_tagid, selector)
	{
		var list = jQuery('#'+list_tagid);
		var sep = list.val().trim() ? ', ' : '';
		val = list.val() +  sep + jQuery(selector).val();
		list.val(val);
	}
	
	
	function fcfield_del_sortable_element(obj)
	{
		var element = jQuery(obj).parent();
		var parent_element = jQuery(element.parent());
		element.slideUp(300, function(){
			jQuery(this).remove();
			fcfield_store_ordering( parent_element );
		});
	}
	
	
	function fcfield_add_sortable_element(selector)
	{
		var selobj = jQuery(selector);
		var tagid  = selobj.attr('id').replace('_selector','');
		var container = 'sortable-' + tagid;
		
		var val = selobj.val();
		if (!val) return true;
		var lbl = selobj.find('option:selected').text();
		jQuery('#'+container).append(
			'<li data-value="field_'+val+'" class="fcrecord">'+
				'<span class="fcprop_box">'+lbl+'</span>'+
				'<span class="delfield_handle" title="Remove" onclick="fcfield_del_sortable_element(this);"></span>'+
			'</li>'
		);
		
		var field_list = jQuery('#'+tagid).val();
		field_list += field_list ? ','+val : val;
		jQuery('#'+tagid).val(field_list);

		setTimeout(function(){
			jQuery(selobj).val('').trigger('change');
		}, 50);
		return true;
	}
	
	
	function fcfield_store_ordering(parent_element)
	{
		hidden_id = '#'+jQuery.trim(parent_element.attr('id').replace('sortable-',''));
		fields = new Array();
		i = 0;
		parent_element.children('li').each(function(){
			fields[i++] = jQuery(this).attr('data-value').replace('field_', '');
		});
		jQuery(hidden_id).val(fields.join(','))
	}
	
	
	function fcfield_attach_sortable(sel)
	{
		sel = typeof sel !== 'undefined' ? sel : 'body';

		var sortable_fcfield_lists = sel + ' ul.fcfields_list';

		jQuery( sortable_fcfield_lists ).each(function(index, value)
		{
			fcfield_store_ordering(jQuery(this));
		});
		
		jQuery( sortable_fcfield_lists ).sortable(
		{
			//connectWith: sortable_fcfield_lists,
			update: function(event, ui) {
				ui.sender ?
					fcfield_store_ordering(jQuery(ui.sender)) :
					fcfield_store_ordering(jQuery(ui.item).parent()) ;
			}
		});
	}




	var fc_field_dialog_handle_fcrecord_list;


	function fcrecord_del_sortable_element(obj)
	{
		var list_el = jQuery(obj).closest('li');
		var list = list_el.closest('ul');

		list_el.slideUp(300, function(){
			jQuery(this).remove();
			fcrecord_store_values( list );
		});
	}


	function fcrecord_toggle_textarea_edit(container)
	{
		var value_area = container.find('textarea');
		if (value_area.attr('readonly'))
			value_area.removeAttr('readonly');
		else
			value_area.attr('readonly', 'readonly');
	}
	
	function fcrecord_direct_edit(list, in_modal)
	{
		var value_element_id = list.data('value_element_id');
		var value_area = jQuery('#'+value_element_id);
		value_area.removeAttr('readonly');
		
		in_modal = typeof in_modal !== 'undefined' ? in_modal : 0;
		if (in_modal)
		{
			fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(value_area.parent(), null, null, fcrecord_toggle_textarea_edit, {'visibleOnClose': 1, 'title': Joomla.JText._('FLEXI_EDIT')});
			list.parent().hide();
		}
		else
		{
			value_area.css({'height': '', 'width': ''});
			value_area.parent().css({'height': '', 'width': ''}).slideDown();
			list.parent().slideUp();
		}
	}


	function fcrecord_toggle_details_btns(btn, current_visibility)
	{
		if (typeof btn === 'undefined' || !btn) return;

		if (current_visibility)
		{
			btn.parent().find('.fcrecords_show_btn').hide();
			btn.parent().find('.fcrecords_hide_btn').show();
		}
		else
		{
			btn.parent().find('.fcrecords_show_btn').show();
			btn.parent().find('.fcrecords_hide_btn').hide();
		}
	}


	function fcrecord_ui_edit(list, in_modal, btn_show, btn_hide)
	{
		// Make sure list is empty and hidden, till we have repopulated it
		list.hide();
		list.empty();

		// Display message about unused columns
		list.parent().find('.fcrec_general_msg').html('').hide();
		var unused_labels = list.parent().find('.fcrecord_label.fcrec_unused:not(.fcrec_cascaded_col)');
		unused_labels.each(function() {
			var label = jQuery(this).html();
			list.parent().find('.fcrec_general_msg').append(label + ' ' + Joomla.JText._('FLEXI_INDEXED_FIELD_UNUSED_COL_DISABLED').replace(/\\/g, '') + '<br/>').show();
		});

		var value_element_id = list.data('value_element_id');
		jQuery('#'+value_element_id).attr('readonly', 'readonly').css('height', '64px');

		in_modal = typeof in_modal !== 'undefined' ? in_modal : 0;
		if (in_modal)
		{
			fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(list.parent(), null, null, null, {'title': Joomla.JText._('FLEXI_EDIT')});
			fcrecord_toggle_details_btns(btn_show, 0);
		}
		else
		{
			if (list.parent().is(':visible'))
			{
				fcrecord_toggle_details_btns(btn_show, 0);
				list.parent().slideUp();
				return;
			}
			fcrecord_toggle_details_btns(btn_show, 1);
			list.parent().css('height', '').show();
		}

		var state_fieldname = list.data('state_fieldname');
		state_fieldname = state_fieldname ? jQuery('#jform_attribs_'+state_fieldname+' input:checked') : jQuery();
		var use_elements_state = state_fieldname.length ? parseInt(state_fieldname.val()) : 0;

		if (!use_elements_state)
		{
			list.parent().find('.fcrec_state_msg').html(Joomla.JText._('FLEXI_INDEXED_FIELD_STATE_COL_DISABLED').replace(/\\/g, '')).show();
			list.parent().find('.fcrec_state_col').addClass('fcrec_unused');
			list.data('use_elements_state', null);
		}
		else
		{
			list.parent().find('.fcrec_state_msg').html('').hide();
			list.parent().find('.fcrec_state_col').removeClass('fcrec_unused');
			list.data('use_elements_state', '1');
		}

		var master_fieldname = list.data('master_fieldname');
		master_fieldname = master_fieldname ? jQuery('#jform_attribs_'+master_fieldname) : jQuery();
		var master_field_id = master_fieldname.length && parseInt(master_fieldname.val()) ? parseInt(master_fieldname.val()) : 0;
		
		if (!master_field_id)
		{
			list.parent().find('.fcrec_cascaded_msg').html(Joomla.JText._('FLEXI_INDEXED_FIELD_VALGRP_COL_DISABLED').replace(/\\/g, '')).show();
			list.parent().find('.fcrec_cascaded_col').addClass('fcrec_unused');
			list.data('master_elements', null);
		}
		else
		{
			list.parent().find('.fcrec_cascaded_msg').html('').hide();
			list.parent().find('.fcrec_cascaded_col').removeClass('fcrec_unused');

			list.after( jQuery('<img src="components/com_flexicontent/assets/images/ajax-loader.gif">') );
			jQuery.ajax({
				async: false,
				type: 'GET',
				url: 'index.php?option=com_flexicontent&task=fields.getIndexedFieldJSON&format=raw',
				data: {
					field_id: master_field_id
				},
				success: function(str) {
					var master_elements = (str ? JSON.parse(str) : false);
					list.data('master_elements', master_elements);
					list.next().remove();
				},
				error: function(str) {
					list.next().remove();
				}
			});
		}

		list.show();
		fcrecord_populate_list_elements(list);
	}


	function fcrecord_populate_list_elements(list)
	{
		var record_sep = list.data('record_sep');
		var value_element_id = list.data('value_element_id');

		var values = jQuery('#'+value_element_id).val().split( new RegExp('\\s*'+record_sep+'\\s*') );

		// Make sure list is empty and re-add the values
		list.empty();
		jQuery(values).each(function(key, value)
		{
			if (value.length) fcrecord_add_sortable_element_ext(list, 1, value, 0);
		});
		list.children('li').slideDown();
	}


	function fcrecord_add_sortable_element_ext(addAt, placement, record_value, shown)
	{
		if (addAt.prop('tagName') == 'UL')
		{
			var list = addAt;
			list_el = null;
		}
		else {
			var list = addAt.closest('ul');
			var list_el = addAt;
		}

		var cascaded_prop = list.data('cascaded_prop');
		var state_prop    = list.data('state_prop');
		cascaded_prop = cascaded_prop ? parseInt(cascaded_prop) : -1;
		state_prop    = state_prop    ? parseInt(state_prop)    : -1;

		var master_elements = list.data('master_elements');
		var master_select = '';
		var master_values = {};
		if (master_elements)
		{
			master_select = '<select class="fcrecord_prop master_prop" style="__width__" onchange="fcrecord_store_values(jQuery(this).closest(\'ul\'));" >';
			master_select += '<option value="">-</option>';
			master_select += '__current_value__';
			for (var i = 0; i < master_elements.length; i++)
			{
				master_select += '<option value="'+master_elements[i].value+'">'+master_elements[i].text+'</option>';
				master_values[master_elements[i].value] = master_elements;
			}
			master_select += '</select>';
		}

		var use_elements_state = list.data('use_elements_state');
		var state_select = '';
		if (use_elements_state)
		{
			state_select = '<select class="fcrecord_prop state_prop" style="__width__" onchange="fcrecord_store_values(jQuery(this).closest(\'ul\'));" >';
			state_select += '<option value="">-</option>';
			state_select += '<option value="1">' + Joomla.JText._('FLEXI_PUBLISHED') + '</option>';
			state_select += '<option value="0">' + Joomla.JText._('FLEXI_UNPUBLISHED') + '</option>';
			state_select += '<option value="9">' + Joomla.JText._('FLEXI_EXPIRED') + '</option>';
			state_select += '<option value="2">' + Joomla.JText._('FLEXI_ARCHIVED') + '</option>';
			state_select += '<option value="-2">' + Joomla.JText._('FLEXI_TRASHED') + '</option>';
			state_select += '</select>';
		}

		record_value = typeof record_value !== 'undefined' ? record_value : '';
		shown = typeof shown !== 'undefined' ? shown : 1;

		var record_sep  = list.data('record_sep');
		var props_sep   = list.data('props_sep');

		var prop_widths = list.data('prop_widths');
		prop_widths = prop_widths.split( new RegExp('\\s*,\\s*') );
		
		var props_used = list.data('props_used');
		props_used = props_used.split( new RegExp('\\s*,\\s*') );
		var _unused_col = ' readonly="readonly" placeholder="' + Joomla.JText._('FLEXI_NA') + '" ';

		var add_after  = list.data('add_after');
		var add_before = list.data('add_before');
		var is_elements = list.data('is_elements');

		var props = record_value.split( new RegExp('\\s*'+props_sep+'\\s*') );
		var props_html = '';
		jQuery(props_used).each(function(key, in_use)
		{
			if (key > props_used - 1) return;
			value = key > props.length - 1 ? '' :  props[key];
			var _unused = !parseInt(props_used[key]) || (key==cascaded_prop && !master_elements) || (key==state_prop && !use_elements_state);
			var _width = 'width:'+prop_widths[key]+'!important';
			
			if (key==cascaded_prop && master_select)
			{
				var select = master_select.replace('__width__', _width);
				select = value.length && !master_values.hasOwnProperty(value) ?
					select.replace('__current_value__', '<option value="'+value+'">'+value+' [CURRENT]</option>') :
					select = select.replace('__current_value__', '') ;
				props_html += select;
			}
			else if (key==state_prop && state_select)
			{
				var select = state_select.replace('__width__', _width);
				if (value.length) select = select.replace('value="'+value+'"', 'value="'+value+'" selected="selected"');
				props_html += select;
			}
			else
				props_html += '<input type="text" value="'+value+'" class="fcrecord_prop '+(_unused ? ' fcrec_unused' : '')+'" onblur="fcrecord_store_values(jQuery(this).closest(\'ul\'));" style="'+_width+'" '+(_unused ? _unused_col : '')+'/>';
		});

		var lbl = 'empty';
		var newrec = jQuery(
			'<li class="fcrecord">'+
				(is_elements ?
					props_html :
					'<span class="fcprop_box">'+lbl+'</span>'
				)+
				'<span class="delfield_handle" title="' + Joomla.JText._('FLEXI_REMOVE') + '" onclick="fcrecord_del_sortable_element(this);"></span>'+
				(add_after ?  '<span class="addfield_handle fc_after"  title="' + Joomla.JText._('FLEXI_ADD') + '" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 1);"></span>' : '')+
				(add_before ? '<span class="addfield_handle fc_before" title="' + Joomla.JText._('FLEXI_ADD') + '" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 0);"></span>' : '')+
				(is_elements ? '<span class="ordfield_handle" title="' + Joomla.JText._('FLEXI_ORDER') + '"></span>' : '')+
			'</li>'
		).hide();
		
		if (master_select && props.length > cascaded_prop)
		{
			if ( props[cascaded_prop] ) newrec.find('select.master_prop').val(props[cascaded_prop]);
		}

		if (list_el)
			placement ?
				list_el.after(newrec) :
				list_el.before(newrec) ;
		else
			list.append(newrec);

		if (shown)
			newrec.slideDown();
	}


	function fcrecord_add_sortable_element(selector)
	{
		var selobj = jQuery(selector);
		var tagid  = selobj.attr('id').replace('_selector','');
		var list = jQuery('#sortable-' + tagid);

		var val = selobj.val();
		if (!val) return;
		var lbl = selobj.find('option:selected').text();
		list.append(
			'<li class="fcrecord" data-value="'+val+'">'+
				'<span class="fcprop_box">'+lbl+'</span>'+
				'<span title="' + Joomla.JText._('FLEXI_REMOVE') + '" onclick="fcrecord_del_sortable_element(this);" class="delfield_handle"></span>'+
			'</li>'
		);

		var field_list = jQuery('#'+tagid).val();
		field_list += field_list ? ','+val : val;
		jQuery('#'+tagid).val(field_list);
		if (selobj.hasClass('use_select2_lib')) {
			selobj.select2('val', '');
			selobj.prev().find('.select2-choice').removeClass('fc_highlight');
		} else
			selobj.prop('selectedIndex',0);
	}


	function fcrecord_store_values(list)
	{
		var values = [];
		if (list.data('is_elements'))
		{
			var props_sep = list.data('props_sep');

			list.children('li').each(function()
			{
				var v;
				var empty_cnt = 0;
				var props = [];
				jQuery(this).find('.fcrecord_prop').each(function()
				{
					v = jQuery(this).val().trim();
					if (v.length && v != '_NA_')
					{
						props.push(v);
						empty_cnt = 0;
					}
					else {
						props.push('_NA_');
						empty_cnt++;
					}
				});
				// Trim empty values at the ends
				for (var i=0; i < empty_cnt; i++) props.pop();

				if (props.length) values.push(props.join(props_sep));
			});
		}
		else
		{
			list.children('li').each(function()
			{
				values.push( jQuery(this).data('value') );
			});
		}

		var record_sep = list.data('record_sep');
		var value_element_id = list.data('value_element_id');

		list.data('is_elements') ?
			jQuery('#'+value_element_id).val( values.join(record_sep+'\n') ) :
			jQuery('#'+value_element_id).val( values.join(record_sep) ) ;
	}


	function fcrecord_attach_sortable(sel)
	{
		sel = typeof sel !== 'undefined' ? sel : 'body';

		jQuery(sel + ' ul.fcrecords_list').each(function(index, value)
		{
			var list = jQuery(this);

			if (!list.data('is_elements'))
			{
				fcrecord_store_values(jQuery(this));
			}

			if( !list.hasClass('ui-sortable') )
			{
				list.sortable({
					update: function(event, ui) {
						if (ui.sender)
							fcrecord_store_values(jQuery(ui.sender));
						else
							fcrecord_store_values(jQuery(ui.item).parent());
					}
				});
			}
		});
	}


	// Trigger value storing function and attach sortable to fcrecord lists
	jQuery(document).ready(function()
	{
		fcrecord_attach_sortable('body');
		fcfield_attach_sortable('body');
	});