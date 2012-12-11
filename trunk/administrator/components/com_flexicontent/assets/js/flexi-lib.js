
function updateDragImg( index ) {
	var row = jQuery("#sortable_fcitems tr").get(index);
	if (!row) return;
	row = jQuery(row);
	
	row_drag_handle = row.find("td div.fc_drag_handle");
	row_ord_grp  = row.find("td input[name=ord_grp\\[\\]]");
	prev_ord_grp = row.prev() ? row.prev().find("td input[name=ord_grp\\[\\]]") : false;
	next_ord_grp = row.next() ? row.next().find("td input[name=ord_grp\\[\\]]") : false;
	
	has_ordUp   = prev_ord_grp && prev_ord_grp.val() == row_ord_grp.val();
	has_ordDown = next_ord_grp && next_ord_grp.val() == row_ord_grp.val();
	
	if (has_ordUp && has_ordDown) {
		new_drag_handle_class = 'fc_drag_handle_both';
	} else if (has_ordUp) {
		new_drag_handle_class = 'fc_drag_handle_uponly';
	} else if (has_ordDown) {
		new_drag_handle_class = 'fc_drag_handle_downonly';
	} else {
		new_drag_handle_class = 'fc_drag_handle_none';
	}

	if ( !row_drag_handle.hasClass(new_drag_handle_class) ) {
		row_drag_handle.removeClass('fc_drag_handle_both');
		row_drag_handle.removeClass('fc_drag_handle_uponly');
		row_drag_handle.removeClass('fc_drag_handle_downonly');
		row_drag_handle.addClass(new_drag_handle_class);
	}
}

window.addEvent('domready', function(){

	var moved_row_order;
	var next_row_order;
	
	var row_old_index;
	var row_new_index;
	
	jQuery("#sortable_fcitems").sortable({
		handle: 'div.fc_drag_handle',
		revert: 100,
		start: function(event, ui) {
			moved_row_order = ui.item.find("td input[name=order\\[\\]]").val();
			row_old_index  = ui.item.index();
		},
		stop: function(event, ui) {
			
			row_new_index = ui.item.index();
			moved_row_ord_grp = ui.item.find("td input[name=ord_grp\\[\\]]").val();
			
			if (row_new_index == row_old_index) {
				return;
			} else if (row_new_index < row_old_index) {
				start_ord_grp = ui.item.next().find("td input[name=ord_grp\\[\\]]").val();
				if (start_ord_grp!=moved_row_ord_grp) {
					alert(move_within_ordering_groups_limits);
					jQuery(this).sortable('cancel');
					return;
				}
			} else {
				end_ord_grp = ui.item.prev().find("td input[name=ord_grp\\[\\]]").val();
				if (end_ord_grp!=moved_row_ord_grp) {
					alert(move_within_ordering_groups_limits);
					jQuery(this).sortable('cancel');
					return;
				}
			}
			
			updateDragImg(row_new_index);
			updateDragImg(row_old_index);
			if ( ui.item.prev() )  updateDragImg( ui.item.prev().index() );
			if ( ui.item.next() )  updateDragImg( ui.item.next().index() );
			
			var start_row_index = row_old_index < row_new_index ? row_old_index : row_new_index;
			var end_row_index = row_new_index > row_old_index ? row_new_index: row_old_index;
			
			var rows = jQuery("#sortable_fcitems tr").get();
			for (i=start_row_index; i<=end_row_index; i++) {
				row = jQuery(rows[i]);
				
				if (row_new_index < row_old_index) {
					if (i>=start_row_index && i<end_row_index) {
						next_row_order = row.next().find("td input[name=order\\[\\]]").val();
						row.find("td input[name=order\\[\\]]").val( next_row_order );
					} else if (i==end_row_index) {
						row.find("td input[name=order\\[\\]]").val( moved_row_order );
					}
				} else {
					if (i>start_row_index && i<=end_row_index) {
						tmp_row_order = row.find("td input[name=order\\[\\]]").val();
						row.find("td input[name=order\\[\\]]").val( next_row_order );
						next_row_order = tmp_row_order;
				} else if (i==start_row_index) {
						next_row_order = row.find("td input[name=order\\[\\]]").val();
						row.find("td input[name=order\\[\\]]").val( moved_row_order );
					}
				}
			}
			
		}
	});//.disableSelection();
});