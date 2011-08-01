function create_form(d,txt,lang,tgt) {
	fx_2g=d.createElement('form');
	fx_2g.name = 'form1';
	d.body.appendChild(fx_2g);
	fx_2g.target=tgt;
	fx_2g.method='POST';
	fx_2g.action="http://www.hlt.nectec.or.th/speech/index.php?option=com_jumi&fileid=9&Itemid=113&lang=en&tmpl=component";
	//text
	t=d.createElement('input');
	t.name='txtinput';
	t.type='hidden';
	t.value=txt;
	fx_2g.appendChild(t);
	//text
	t=d.createElement('input');
	t.name='speed';
	t.type='hidden';
	t.value=txt;
	fx_2g.appendChild(t);
	//mp3
	t=d.createElement('input');
	t.name='mp3';
	t.type='hidden';
	t.value=1;
	fx_2g.appendChild(t);
	//example
	t=d.createElement('input');
	t.name='example';
	t.type='hidden';
	t.value='--';
	fx_2g.appendChild(t);
	//lang
	l=d.createElement('input');
	l.name='langinput';
	l.type='hidden';
	l.value=lang;
	fx_2g.appendChild(l);
	//submit
	t=d.createElement('input');
	t.name='button';
	t.type='submit';
	t.value='Submit';
	fx_2g.appendChild(t);
	//submit
	window.open('', tgt, 'width=800,height=500,scrollbars=yes,location=yes,menubar=yes,resizable=yes,status=yes,toolbar=yes'); 
	fx_2g.submit();
	delete fx_2g;
	return false;
}
function openwindow(id, lang) {
	d=window.document;
	if(d.getElementById(id).innerText){
		txt=d.getElementById(id).innerText;
	} else{
		txt=d.getElementById(id).textContent;
	}
	txt = txt.replace(/\s+/g," ");
	txt = txt.replace(/\r/g," ");
	txt = txt.replace(/\n/g," ");
	txt = txt.replace(/\t/g," ");
	if(lang=='th') lang=1;
	else if(lang=='la') lang=3;
	else lang=1;//may change to lang=2 in the future.
	var tgt='voice_'+parseInt(Math.random()*100000);
	create_form(d,txt,lang,tgt);
}
