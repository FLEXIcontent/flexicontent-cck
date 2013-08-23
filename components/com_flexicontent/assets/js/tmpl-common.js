	function tableOrdering( order, dir, task )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		
		var form = document.getElementById("adminForm");
		
		adminFormPrepare(form, 2, task);
	}

	function getSEFurl(loader_el, loader_html, form, url_to_load, autosubmit_msg, autosubmit) {

		var dooptions = {
			method: 'get',
			evalScripts: false,
			onSuccess: function(responseText) {
			 	form.action=responseText;
			 	var fcform = jQuery(form);
			 	fcform.attr('data-fcform_action', responseText);
			 	if (autosubmit) {
			 		$(loader_el).innerHTML += autosubmit_msg;
					adminFormPrepare(form, 2);
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
				 	var fcform = jQuery(form);
				 	fcform.attr('data-fcform_action', responseText);
				 	if (autosubmit) {
				 		$(loader_el).innerHTML += autosubmit_msg;
						adminFormPrepare(form, 2);
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
	
	
	function adminFormPrepare(form, postprep, task) {
		var extra_action = '';
		var fcform = jQuery(form);
		
		var fcform_action = fcform.attr('data-fcform_action');
		if ( typeof fcform_action === "undefined" || fcform_action === null ) {
			fcform_action = fcform.attr('action');
			fcform.attr('data-fcform_action', fcform_action);
		}
		
		var var_sep = fcform_action.match(/\?/) ? '&' : '?';
		
		for(i=0; i<form.elements.length; i++) {
			
			var element = form.elements[i];
			if (typeof element.name === "undefined" || element.name === null || !element.name) continue;
			
			// No need to add the default values for ordering, to the URL
			if (element.name=='filter_order' && element.value=='i.title') continue;
			if (element.name=='filter_order_Dir' && element.value=='ASC') continue;
			
			var matches = element.name.match(/(filter[.]*|cids|letter|clayout|limit|orderby|searchword|searchphrase|areas|contenttypes|txtflds|ordering)/);
			if (!matches || element.value == '') continue;
			if ((element.type=='radio' || element.type=='checkbox') && !element.checked) continue;
			
			if ( element.type=='select-multiple' ) {
				for (var p=0; p < element.length; p++) {
					if ( ! element.options[p].selected ) continue;
					extra_action += var_sep + element.name.replace("[]","") + '[' + p + ']=' + element.options[p].value;
					var_sep = '&';
				}
			} else {
				extra_action += var_sep + element.name + '=' + element.value;
				var_sep = '&';
			}
		}
		form.action = fcform_action + extra_action;  //alert(form.action);
		
		if (typeof postprep !== "undefined" && postprep !== null && postprep!=0) {
			if (postprep==2) {
				var fc_filter_form_blocker = jQuery("#fc_filter_form_blocker");
				form.submit( task );
				if (fc_filter_form_blocker) {
					fc_filter_form_blocker.css("display", "block");
					fc_progress(95, jQuery('#fc_filter_form_blocker .fc_blocker_bar'));
				}
			} else if (postprep==1) {
				var form_id = jQuery(form).attr('id');
				jQuery('#'+form_id+'_submitWarn').css("display", "inline-block");
			}
		}
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
	
	function fc_toggleClass(ele,cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
		  jQuery(ele).next().hasClass(cls) ? jQuery(ele).next().removeClass(cls) : jQuery(ele).next().addClass(cls);
		  // Handle disabling 'select all' checkbox (if it exists), not needed but to make sure ...
		  if (inputs[0].value=='') {
		  	inputs[0].checked = 0;
				jQuery(inputs[0]).next().removeClass(cls);
		  }
		}
		else
		{
			for (var i = 1; i < inputs.length; ++i) {
				inputs[i].checked = 0;
				jQuery(inputs[i]).next().removeClass(cls);
			}
		  // Handle highlighting (but not enabling) 'select all' checkbox
			jQuery(inputs[0]).next().addClass(cls);
		}
	}
	
	function fc_toggleClassGrp(ele, cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
			for (var i = 0; i < inputs.length; ++i) {
				inputs[i].checked ? jQuery(inputs[i]).next().addClass(cls) : jQuery(inputs[i]).next().removeClass(cls);
			}
		}
		else
		{
			for (var i = 1; i < inputs.length; ++i) {
				inputs[i].checked = 0;
				jQuery(inputs[i]).next().removeClass(cls);
			}
		  // Handle highlighting (but not enabling) 'select all' radio button
			jQuery(inputs[0]).next().addClass(cls);
		}
	}


	function fc_progress(percent, element) {
		var progressBarWidth = percent * element.width() / 100;
		element.find('div').animate({ width: progressBarWidth }, 5000).html("");
	}


jQuery(document).ready(function() {
	// Initialize internal labels
	jQuery('input.fc_label_internal').each(function() {
		var el = jQuery(this);
		var fc_label_text = el.attr('fc_label_text');
		if (!fc_label_text) return;
		var _label = (fc_label_text.length >= 27) ? fc_label_text.substring(0, 25) + '...' : fc_label_text;
		
		el.before(jQuery('<span/>', {
			'class': 'fc_has_inner_label fc_has_inner_label_input',
			'text': _label
		}));
		if (el.val().length > 0) el.prev().hide();
	});
	
	
	jQuery('input.fc_label_internal').bind('focus', function() {
		var el = jQuery(this);
		var fc_label_text = el.attr('fc_label_text');
		if (!fc_label_text) return;
		el.prev().hide();
		el.css("opacity", "1");
	}).bind('change blur', function(event) {
		var el = jQuery(this);
		var fc_label_text = el.attr('fc_label_text');
		if (!fc_label_text) return;
		
		if (event.type=='blur') {
			var previous_value = el.attr('data-previous_value');
			if ( typeof previous_value !== "undefined" && previous_value != el.val())  el.trigger('change');
		}
		
		if ( el.val().length ) {
			el.prev().hide();
			el.css("opacity", "1");
		} else {
			el.prev().show();
			el.css("opacity", "0.5");
		}
	});
	
	jQuery('img.calendar').bind('click', function() {
		var el = jQuery(this).prev();
		el.prev().hide();
		el.attr('data-previous_value', el.val());
		el.css("opacity", "1");
		el.focus();
	});
	
	var fc_select_pageSize = 10;
	
	// Simple text search autocomplete
	jQuery( "input.fc_basicindex_complete_simple" ).autocomplete({
		source: function( request, response ) {
			jQuery.ajax({
				url: "index.php?option=com_flexicontent&tmpl=component",
				dataType: "json",
				data: {
					type: "basic_index",
					task: "txtautocomplete",
					pageSize: fc_select_pageSize,
					text: request.term
				},
				success: function( data ) {
					response( jQuery.map( data.Matches, function( item ) {
						return {
							/*label: item.item_id +': '+ item.text,*/
							label: item.text,
							value: item.text
						}
					}));
				}
			});
		},
		delay: 200,
		minLength: 1,
		select: function( event, ui ) {
			/*log( ui.item  ?  "Selected: " + ui.item.label  :  "Nothing selected, input was " + this.value);*/
			var ele = event.target;
			jQuery(ele).trigger('change');
		},
		open: function() {
			jQuery( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
			jQuery(this).removeClass('working');
		},
		close: function() {
			jQuery( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
		},
		search: function() {
			jQuery(this).addClass('working');
		}
	});



	// Tag-Like text search autocomplete
	jQuery('input.fc_basicindex_complete_tlike').select2(
	{
		placeholder: Joomla.JText._('FLEXI_TYPE_TO_LIST'),
		multiple: true,
		minimumInputLength: 1,
		separator: " ",
		allowClear: true,
	
		initSelection : function (element, callback) {
			var data = [];
			jQuery(element.val().split(" ")).each(function () {
				data.push({id: this, text: this});
			});
			callback(data);
		},
	
		ajax: {
			quietMillis: 200,
			url: "index.php?option=com_flexicontent&tmpl=component",
			dataType: 'json',
			//Our search term and what page we are on
			data: function (term, page) {
				return {
					type: "basic_index",
					task: "txtautocomplete",
					text: term,
					pageSize: fc_select_pageSize,
					pageNum: page
				};
			},
			results: function (data, page) {
				//Used to determine whether or not there are more results available,
				//and if requests for more data should be sent in the infinite scrolling
				var more = (page * fc_select_pageSize) < data.Total;
				return { results: data.Matches, more: more };
			}
		}
	})/*.on('change', function(){
		alert(jQuery(this).val());
	})*/;



	jQuery('body').prepend(
	 	"<span id='fc_filter_form_blocker'>" +
	    "<span class='fc_blocker_opacity'></span>" +
	    "<span class='fc_blocker_content'>" +
	    	Joomla.JText._('FLEXI_APPLYING_FILTERING') +
	    	"<div class='fc_blocker_bar'><div></div></div>" +
	    "</span>" +
	  "</span>");

});