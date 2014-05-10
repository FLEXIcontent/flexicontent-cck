function moveonly() {
	jQuery('#maincat').attr('disabled', '');
	jQuery('#keepseccats0').attr('disabled', '');
	jQuery('#keepseccats1').attr('disabled', '');
	jQuery('#keeptags0').attr('disabled', 'disabled');
	jQuery('#keeptags1').attr('disabled', 'disabled');
	jQuery('#prefix').attr('disabled', 'disabled');
	jQuery('#suffix').attr('disabled', 'disabled');
	jQuery('#copynr').attr('disabled', 'disabled');
	jQuery('#state').attr('disabled', 'disabled');
	jQuery('.lang').attr('disabled', 'disabled');
}
function copymove() {
	jQuery('#maincat').attr('disabled', '');
	jQuery('#keepseccats0').attr('disabled', '');
	jQuery('#keepseccats1').attr('disabled', '');
	jQuery('#keeptags0').attr('disabled', '');
	jQuery('#keeptags1').attr('disabled', '');
	jQuery('#prefix').attr('disabled', '');
	jQuery('#suffix').attr('disabled', '');
	jQuery('#copynr').attr('disabled', '');
	jQuery('#state').attr('disabled', '');
	jQuery('.lang').attr('disabled', '');
}
function copyonly() {
	jQuery('#maincat').attr('disabled', 'disabled');
	jQuery('#seccats').attr('disabled', 'disabled');
	jQuery('#keepseccats0').attr('disabled', 'disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').attr('disabled', 'disabled');
	jQuery('#keeptags0').attr('disabled', '');
	jQuery('#keeptags1').attr('disabled', '');
	jQuery('#prefix').attr('disabled', '');
	jQuery('#suffix').attr('disabled', '');
	jQuery('#copynr').attr('disabled', '');
	jQuery('#state').attr('disabled', '');
	jQuery('.lang').attr('disabled', '');
}
function secmove() {
	jQuery('#seccats').attr('disabled', '');
}
function secnomove() {
	jQuery('#seccats').attr('disabled', 'disabled');
}

jQuery(document).ready(function(){
	var initial_behaviour = jQuery('input[name=initial_behaviour]').val();
	if      (initial_behaviour=='copyonly') copyonly();
	else if (initial_behaviour=='copymove') copymove();
	else if (initial_behaviour=='moveonly') moveonly();
	
	if (initial_behaviour!='copyonly' && jQuery('input[name=keepseccats]:checked').val() == 1) {
		jQuery('#seccats').attr('disabled', 'disabled');
	} else {
		jQuery('#seccats').attr('disabled', '');
	}
});
