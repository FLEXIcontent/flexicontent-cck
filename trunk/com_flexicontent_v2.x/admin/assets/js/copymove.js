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
	$('maincat').setProperty('disabled', 'disabled');
	$('seccats').setProperty('disabled', 'disabled');
	$('keepseccats0').setProperty('disabled', 'disabled');
	$('keepseccats1').setProperty('disabled', 'disabled');
});