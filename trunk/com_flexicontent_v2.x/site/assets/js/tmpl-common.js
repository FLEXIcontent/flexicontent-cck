	function tableOrdering( order, dir, task )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		
		var form = document.getElementById("adminForm");
		
		adminFormPrepare(form);
		form.submit( task );
	}

	function getSEFurl(loader_el, loader_html, form, url_to_load, autosubmit_msg, autosubmit) {

		var dooptions = {
			method: 'get',
			evalScripts: false,
			onSuccess: function(responseText) {
			 	form.action=responseText;
			 	if (autosubmit) {
			 		$(loader_el).innerHTML += autosubmit_msg;
					adminFormPrepare(form);
					form.submit();
				} else {
					$(loader_el).innerHTML = '';
				}
			} 
		};
		if(typeof options!="undefined") {
			dooptions = options;
		}
		
		if (MooTools.version>='1.2.4') {
			$(loader_el).set('html', loader_html);
			new Request({
				url: url_to_load,
				method: 'get',
				evalScripts: false,
				onSuccess: function(responseText) {
				 	form.action=responseText;
				 	if (autosubmit) {
				 		$(loader_el).innerHTML += autosubmit_msg;
						adminFormPrepare(form);
						form.submit();
					} else {
						$(loader_el).innerHTML = '';
					}
				} 
			}).send();
		} else {
			$(loader_el).setHTML(loader_html);
			var ajax = new Ajax(url_to_load, dooptions);
			ajax.request.delay(300, ajax);
		}
	}
	
	
	function adminFormPrepare(form) {
		var extra_action = '';
		var var_sep = form.action.match(/\?/) ? '&' : '?';
		
		for(i=0; i<form.elements.length; i++) {
			
			var element = form.elements[i];
			
			// No need to add the default values for ordering, to the URL
			if (element.name=='filter_order' && element.value=='i.title') continue;
			if (element.name=='filter_order_Dir' && element.value=='ASC') continue;
			
			var matches = element.name.match(/(filter[.]*|letter|clayout|limit|orderby)/);
			if (matches && element.value != '') {
				extra_action += var_sep + element.name + '=' + element.value;
				var_sep = '&';
			}
		}
		form.action += extra_action;   //alert(extra_action);
	}
	
	function adminFormClearFilters (form) {
		for(i=0; i<form.elements.length; i++) {
			var element = form.elements[i];
			
			if (element.name=='filter_order') {	element.value=='i.title'; continue; }
			if (element.name=='filter_order_Dir') { element.value=='ASC'; continue; }
			
			var matches = element.name.match(/(filter[.]*|letter)/);
			if (matches) {
				element.value = '';
			}
		}
	}