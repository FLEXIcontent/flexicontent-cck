function qffileselementadd(id, file) {
	document.adminForm.file.value=id;	
	window.parent.qfSelectFile(id, file);	
	document.adminForm.submit();	
}