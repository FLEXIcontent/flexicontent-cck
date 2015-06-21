function toggle_importlang_info() {
	if ( jQuery('#method-usejoomfish:checked').length || jQuery('#method-firstjf-thenauto:checked').length )
		jQuery('#falang-import-info').css('display', 'inline-block');
	else
		jQuery('#falang-import-info').css('display', 'none');
}

function moveonly() {
	jQuery('#row_copy_options').hide(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').show(600);
	jQuery('#seccats').attr('disabled', 'disabled'); jQuery('#row_seccats').show(600);
	jQuery('#keepseccats0').removeAttr('disabled'); jQuery('#row_keepseccats').show(600);
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keeptags0').attr('disabled', 'disabled'); jQuery('#row_keeptags').hide(600);
	jQuery('#keeptags1').attr('disabled', 'disabled');
	jQuery('#prefix').attr('disabled', 'disabled'); jQuery('#row_prefix').hide(600);
	jQuery('#suffix').attr('disabled', 'disabled'); jQuery('#row_suffix').hide(600);
	jQuery('#copynr').attr('disabled', 'disabled'); jQuery('#row_copynr').hide(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').show(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').show(600);
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').show(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').show(600);
	toggle_importlang_info();
}
function copymove() {
	jQuery('#row_copy_options').show(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').show(600);
	jQuery('#seccats').attr('disabled', 'disabled'); jQuery('#row_seccats').show(600);
	jQuery('#keepseccats0').removeAttr('disabled'); jQuery('#row_keepseccats').show(600);
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#row_keeptags').show(600);
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').show(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').show(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').show(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').show(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').show(600);
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').show(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').show(600);
	toggle_importlang_info();
}
function copyonly() {
	jQuery('#row_copy_options').show(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').show(600);
	jQuery('#seccats').attr('disabled', 'disabled'); jQuery('#row_seccats').hide(600);
	jQuery('#keepseccats0').attr('disabled', 'disabled'); jQuery('#row_keepseccats').hide(600);
	jQuery('#keepseccats1').attr('disabled', 'disabled');
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#row_keeptags').show(600);
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').show(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').show(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').show(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').show(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').show(600);
	jQuery('#type_id').attr('disabled', 'disabled'); jQuery('#row_type_id').hide(600);
	jQuery('#access').attr('disabled', 'disabled'); jQuery('#row_access').hide(600);
	toggle_importlang_info();
}
function seccats_on() {
	jQuery('#seccats').removeAttr('disabled');
}
function seccats_off() {
	jQuery('#seccats').attr('disabled', 'disabled');
}

jQuery(document).ready(function(){
	var initial_behaviour = jQuery('input[name=initial_behaviour]').val();
	if      (initial_behaviour=='copyonly') copyonly();
	else if (initial_behaviour=='copymove') copymove();
	else if (initial_behaviour=='moveonly') moveonly();
	
	if (initial_behaviour=='copyonly' || jQuery('input[name=keepseccats]:checked').val() == 1) {
		jQuery('#seccats').removeAttr('disabled');
	} else {
		jQuery('#seccats').attr('disabled', 'disabled');
	}

	jQuery('#type_id').change(function() {
		if (jQuery(this).val() != '')
			jQuery('#fc-change-warning').css('display', 'inline-block');
		else
			jQuery('#fc-change-warning').css('display', 'none');
	});
});
