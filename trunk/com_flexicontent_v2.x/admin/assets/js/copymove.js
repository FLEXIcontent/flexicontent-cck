function moveonly() {
	$('maincat').setProperty('disabled', '');
	$('keepseccats0').setProperty('disabled', '');
	$('keepseccats1').setProperty('disabled', '');
	$('keeptags0').setProperty('disabled', 'disabled');
	$('keeptags1').setProperty('disabled', 'disabled');
	$('prefix').setProperty('disabled', 'disabled');
	$('suffix').setProperty('disabled', 'disabled');
	$('copynr').setProperty('disabled', 'disabled');
	$('state').setProperty('disabled', 'disabled');
	$$('.lang').setProperty('disabled', 'disabled');
}
function copymove() {
	$('maincat').setProperty('disabled', '');
	$('keepseccats0').setProperty('disabled', '');
	$('keepseccats1').setProperty('disabled', '');
	$('keeptags0').setProperty('disabled', '');
	$('keeptags1').setProperty('disabled', '');
	$('prefix').setProperty('disabled', '');
	$('suffix').setProperty('disabled', '');
	$('copynr').setProperty('disabled', '');
	$('state').setProperty('disabled', '');
	$$('.lang').setProperty('disabled', '');
}
function copyonly() {
	$('maincat').setProperty('disabled', 'disabled');
	$('seccats').setProperty('disabled', 'disabled');
	$('keepseccats0').setProperty('disabled', 'disabled');
	$('keepseccats1').setProperty('checked', 'checked');
	$('keepseccats1').setProperty('disabled', 'disabled');
	$('keeptags0').setProperty('disabled', '');
	$('keeptags1').setProperty('disabled', '');
	$('prefix').setProperty('disabled', '');
	$('suffix').setProperty('disabled', '');
	$('copynr').setProperty('disabled', '');
	$('state').setProperty('disabled', '');
	$$('.lang').setProperty('disabled', '');
}
function secmove() {
	$('seccats').setProperty('disabled', '');
}
function secnomove() {
	$('seccats').setProperty('disabled', 'disabled');
}

window.addEvent('domready', function(){
	var initial_behaviour = $$('input[name=initial_behaviour]').get('value');
	if      (initial_behaviour=='copyonly') copyonly();
	else if (initial_behaviour=='copymove') copymove();
	else if (initial_behaviour=='moveonly') moveonly();
	
	if (initial_behaviour!='copyonly' && $$('input[name=keepseccats]:checked').get('value') == 1) {
		$('seccats').setProperty('disabled', 'disabled');
	} else {
		$('seccats').setProperty('disabled', '');
	}
});

//var somefield = $$('input[name=somefield]:checked').map(function(e) { return e.value; });   // This is for checkboxes
