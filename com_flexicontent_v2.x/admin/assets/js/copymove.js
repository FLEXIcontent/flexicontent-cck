function moveonly() {
	jQuery('#maincat').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keeptags0').attr('disabled', 'disabled');
	jQuery('#keeptags1').attr('disabled', 'disabled');
	jQuery('#prefix').attr('disabled', 'disabled');
	jQuery('#suffix').attr('disabled', 'disabled');
	jQuery('#copynr').attr('disabled', 'disabled');
	jQuery('#state').attr('disabled', 'disabled');
	jQuery('.lang').attr('disabled', 'disabled');
}
function copymove() {
	jQuery('#maincat').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keeptags0').removeAttr('disabled');
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled');
	jQuery('#suffix').removeAttr('disabled');
	jQuery('#copynr').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled');
	jQuery('.lang').removeAttr('disabled');
}
function copyonly() {
	jQuery('#maincat').attr('disabled', 'disabled');
	jQuery('#seccats').attr('disabled', 'disabled');
	jQuery('#keepseccats0').attr('disabled', 'disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').attr('disabled', 'disabled');
	jQuery('#keeptags0').removeAttr('disabled');
	jQuery('#keeptags1').removeAttr('disabled');
	jQuery('#prefix').removeAttr('disabled');
	jQuery('#suffix').removeAttr('disabled');
	jQuery('#copynr').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled');
	jQuery('.lang').removeAttr('disabled');
}
function secmove() {
	jQuery('#seccats').removeAttr('disabled');
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
		jQuery('#seccats').removeAttr('disabled');
	}
});
