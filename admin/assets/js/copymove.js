function toggle_importlang_info() {
	if ( jQuery('#method-usejoomfish:checked').length || jQuery('#method-firstjf-thenauto:checked').length )
		jQuery('#falang-import-info').css('display', 'inline-block');
	else
		jQuery('#falang-import-info').css('display', 'none');
}

function moveonly() {
	jQuery('#row_copy_options').slideUp(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_keepseccats').slideDown(600); jQuery('#row_seccats').slideDown(600);
	jQuery('#seccats').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').trigger('click');
	
	jQuery('#keeptags0').attr('disabled', 'disabled'); jQuery('#row_keeptags').slideUp(600);
	jQuery('#keeptags1').attr('disabled', 'disabled');
	jQuery('#prefix').attr('disabled', 'disabled'); jQuery('#row_prefix').slideUp(600);
	jQuery('#suffix').attr('disabled', 'disabled'); jQuery('#row_suffix').slideUp(600);
	jQuery('#copynr').attr('disabled', 'disabled'); jQuery('#row_copynr').slideUp(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600); jQuery('#state').val('').trigger('change');
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').slideDown(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').slideDown(600);
	toggle_importlang_info();
}
function copymove() {
	jQuery('#row_copy_options').slideDown(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_keepseccats').slideDown(600); jQuery('#row_seccats').slideDown(600);
	jQuery('#seccats').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').trigger('click');
	
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#row_keeptags').slideDown(600);
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').slideDown(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').slideDown(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').slideDown(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600); jQuery('#state').val('0').trigger('change');
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').slideDown(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').slideDown(600);
	toggle_importlang_info();
}
function copyonly() {
	jQuery('#row_copy_options').slideDown(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_seccats').slideUp(600); jQuery('#row_keepseccats').slideUp(600);
	jQuery('#seccats').attr('disabled', 'disabled');
	jQuery('#keepseccats0').attr('disabled', 'disabled');
	jQuery('#keepseccats1').attr('disabled', 'disabled');
	
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#row_keeptags').slideDown(600);
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').slideDown(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').slideDown(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').slideDown(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600);  jQuery('#state').val('0').trigger('change');
	jQuery('#type_id').attr('disabled', 'disabled'); jQuery('#row_type_id').slideUp(600);
	jQuery('#access').attr('disabled', 'disabled'); jQuery('#row_access').slideUp(600);
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
	

	jQuery('#type_id').change(function() {
		if (jQuery(this).val() != '')
			jQuery('#fc-change-warning').css('display', 'inline-block');
		else
			jQuery('#fc-change-warning').css('display', 'none');
	});
});
