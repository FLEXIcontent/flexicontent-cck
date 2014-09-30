
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
		containment: 'parent',
		tolerance: 'pointer',
		helper: function(e, tr) {
			var $originals = tr.children();
			var $helper = tr.clone();
			$helper.children().each(function(index) {
				// Set helper cell sizes to match the original sizes
				jQuery(this).width($originals.eq(index).width());
			});
			return $helper;
		},
		revert: 100,
		start: function(event, ui) {
			jQuery("#fcorder_notes_box").hide();
			jQuery("#fcorder_save_warn_box").show('slide');
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



// *** Check that a variable is not null and is defined (has a value)
function js_isset (variable) {
  if (variable==null) return false;
  if (typeof variable == "undefined") return false;
  return true;
}


//******************************************
//*** Column hide/show related functions ***
//******************************************

// *** Hide a column of a table
function toggle_column(container_div_id, data_tbl_id, col_no, firstrun) {
  // 1. Get column-status array for the table with id: data_tbl_id
  var show_col = eval('show_col_'+data_tbl_id);
  
  // 2. Decide show or hide action and update the displayed number of the hidden columns (if any must be in the: $(data_tbl_id+'_hidecolnum_box') element)
  if ( !js_isset(show_col[col_no]) || show_col[col_no] ) {
    var action_func = 'hide';
    show_col[col_no] = 0;
    if ( js_isset($(data_tbl_id+'_hidecolnum_box')) ) {
      $(data_tbl_id+'_hidecolnum_box').innerHTML = parseInt($(data_tbl_id+'_hidecolnum_box').innerHTML) + 1;
    }
  } else {
    var action_func = 'show';
    show_col[col_no] = 1;
    if ( js_isset($(data_tbl_id+'_hidecolnum_box')) ) {
      $(data_tbl_id+'_hidecolnum_box').innerHTML = parseInt($(data_tbl_id+'_hidecolnum_box').innerHTML) - 1;
    }
  }
  
  //var ffieldname_label = 'columnchoose_' + data_tbl_id + '_' + col_no + '_label';
  //if ( js_isset($(ffieldname_label)) ) if (action_func == 'show') $(ffieldname_label).style.color='black';  else $(ffieldname_label).style.color='#00aa00';
  
  // 3. Get table and all its rows
  var tbl  = document.getElementById(data_tbl_id);
  var rows = jQuery(tbl).find('> thead > tr, > tbody > tr');
  
  // 4. Iterate through rows toggling the particular column
  for (var row=0; row<rows.length; row++) {
    var cell_cnt, cell;
    
    // Hide the td cell of the column at the current row (if any)
    var tcells = jQuery(rows[row]).children('td, th');
    cell_cnt = 0;
    for (cell=0; cell<tcells.length; cell++) {
    	var colspan;
    	var data_colspan = jQuery(tcells[cell]).attr('data-colspan');
    	if (firstrun && !data_colspan) {
    		colspan = parseInt(jQuery(tcells[cell]).attr('colspan'));
    		if (colspan) {
    			jQuery(tcells[cell]).attr('data-colspan', colspan);
    		}
    	} else {
    		colspan = parseInt(jQuery(tcells[cell]).attr('data-colspan'));
    	}
    	
    	if (cell_cnt==col_no) break;
    	var next_cnt = colspan ? cell_cnt + colspan : cell_cnt + 1;
    	if (next_cnt > col_no) break;
    	cell_cnt = next_cnt;
    }
    if (cell<tcells.length) {
    	var colspan = parseInt(jQuery(tcells[cell]).attr('colspan'));
    	
			jQuery(tcells[cell]).removeClass('initiallyHidden');
    	if ( colspan ) {
				if ( action_func == 'hide' ) {
					if ( colspan > 1 ) {
						jQuery(tcells[cell]).attr('colspan', colspan - 1);
					} else {
						eval('jQuery(tcells[cell]).'+action_func+'()');
			    	jQuery(tcells[cell]).addClass('isHidden');
					}
    		} else if (!firstrun) {
					if ( !jQuery(tcells[cell]).hasClass('isHidden') ) {
						jQuery(tcells[cell]).attr('colspan', colspan + 1);
					} else {
			    	eval('jQuery(tcells[cell]).'+action_func+'()');
			    	jQuery(tcells[cell]).removeClass('isHidden');
			    }
		    }
    	} else {
		    eval('jQuery(tcells[cell]).'+action_func+'()');
		  }
    }
  }
  
  if (container_div_id) {
	  var col_selectors = jQuery('#'+container_div_id+' input');
	  var col_selected = new Array();
	  var i = 0;
	  for (var cnt=0; cnt<col_selectors.length; cnt++) {
	  	if ( jQuery(col_selectors[cnt]).attr("checked") ) {
	  		col_selected[i++] = jQuery(col_selectors[cnt]).attr("data-colno");
	  	}
	  }
		var cookieValue = col_selected.join(',');
		var cookieName = 'columnchoose_' + data_tbl_id;
		var nDays = 30;
		fclib_setCookie(cookieName, cookieValue, nDays);
	}
}

// *** Create column choosers row for a table. NOTE must have <th> cells at row 0
function create_column_choosers(container_div_id, data_tbl_id, firstload, lbltext) {
  // 1. Get column-status array for the table with id: data_tbl_id
  var show_col = eval('show_col_'+data_tbl_id);
  
  // 2. Get table and its first row and then the 'th' cells in it
  var firstrow  = jQuery("#"+data_tbl_id+" thead tr:first"); //document.getElementById(data_tbl_id);
  var thcells = firstrow.find('th');
  
  // 3. Iterate through the 'th' cells and create column hiders for those having the class 'hideOnDemandClass'
  var str = (typeof lbltext != "undefined") ? lbltext : '';
  for (var col=0; col<thcells.length;col++) {
    // 4. Skip if not having 'hideOnDemandClass' class
    if (!jQuery(thcells[col]).hasClass('hideOnDemandClass')) continue;
    
    // 5. Get column name
		var col_display_name = jQuery(thcells[col]).text();
    
    // 6. Show / Hide current column
    if ( ( !firstload && !js_isset(show_col[col]) ) || ( jQuery(thcells[col]).hasClass('initiallyHidden') && !js_isset(show_col[col]) ) ) {
      var checked_str = '';
      var fontcolor_str = 'black';//var fontcolor_str = '#aaaaaa';
      // *** It has value of 0 or not set, set it to 1 for toggle_column() function to hide it
      show_col[col] = 1;
      // *** Call toggle_column()
      toggle_column('', data_tbl_id, col, 1);
    } else {
      var checked_str = 'checked="checked"';
      var fontcolor_str = 'black'; //var fontcolor_str = 'black';
      // *** It has value of 1 or not set, set it to 0 for toggle_column() function to show it
      show_col[col] = 0;
      // *** Call toggle_column()
      toggle_column('', data_tbl_id, col, 1);
    }
    
    // 7. Create column checkbox and append it to str
    var ffieldid   = 'columnchoose_' + data_tbl_id + '_' + col;
    var ffieldname = 'columnchoose_' + data_tbl_id + '[' + col + ']';
    str = str + '<input align="right" id="' + ffieldid + '" name="' + ffieldname + '" type="checkbox" data-colno="' + col + '" ' + checked_str + ' onclick="toggle_column(\''+container_div_id+'\', \''+data_tbl_id+'\', '+col+', 0);">'
              + '<label id="' + ffieldid + '_label" style="color:'+fontcolor_str+';" for="' + ffieldid + '">' + col_display_name + '</label>';
  }
  
  // 8. Fill in 'column choose box'
  str = '<input type="hidden" name="columnchoose_'+data_tbl_id+'" value="true">'+str;
  $(container_div_id).innerHTML=str;
}



function fclib_setCookie(cookieName, cookieValue, nDays) {
	var today = new Date();
	var expire = new Date();
	var path = "'.JURI::base(true).'";
	if (nDays==null || nDays<0) nDays=0;
	if (nDays) {
		expire.setTime(today.getTime() + 3600000*24*nDays);
		document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path + ";expires="+expire.toGMTString();
	} else {
		document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path;
	}
	//alert(cookieName+"="+escape(cookieValue) + ";path=" + path);
}
