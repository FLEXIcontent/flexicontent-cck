
	window.sorttable_fcfield_lists = '';
	
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
	
	
	jQuery(document).ready(function()
	{
		jQuery( sorttable_fcfield_lists ).each(function(index, value)
		{
			fcfield_store_ordering(jQuery(this));
		});
		
		jQuery( sorttable_fcfield_lists ).sortable(
		{
			connectWith: sorttable_fcfield_lists,
			update: function(event, ui) {
				ui.sender ?
					fcfield_store_ordering(jQuery(ui.sender)) :
					fcfield_store_ordering(jQuery(ui.item).parent()) ;
			}
		});
	});