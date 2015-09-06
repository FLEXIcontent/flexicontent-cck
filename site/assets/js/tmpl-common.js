	function tableOrdering( order, dir, task )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		
		var form = document.getElementById("adminForm");
		
		adminFormPrepare(form, 2, task);
	}

	function getSEFurl(loader_el, loader_html, form, url_to_load, autosubmit_msg, autosubmit)
	{
		jQuery('#'+loader_el).html(loader_html);
		jQuery.ajax({
			type: 'GET',
			url: url_to_load,
			dataType: "text",
			data: {
			},
			success: function( responseText )
			{
			 	form.action=responseText;
			 	var fcform = jQuery(form);
			 	fcform.attr('data-fcform_action', responseText);
			 	if (autosubmit) {
			 		jQuery('#'+loader_el).append(autosubmit_msg);
					adminFormPrepare(form, 2);
				} else {
					jQuery('#'+loader_el).html('');
				}
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
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
			
			var matches = element.name.match(/^(filter.*|cids|letter|clayout|limit|orderby|q|searchword|p|searchphrase|areas|contenttypes|txtflds|o|ordering)$/);
			if (!matches || element.value == '') continue;
			if ((element.type=='radio' || element.type=='checkbox') && !element.checked) continue;
			
			if ( element.type=='select-multiple' ) {
				for (var p=0; p < element.length; p++) {
					if ( ! element.options[p].selected ) continue;
					extra_action += var_sep + element.name.replace("[]","") + '[' + ']=' + element.options[p].value;
					var_sep = '&';
				}
			} else {
				element_value = element.value;
				if ( jQuery(element).hasClass('fc_iscalendar') ) {
					var frmt = '%Y-%m-%d';
					var date = Date.parseDate(element.value || element.innerHTML, frmt);
					if (postprep==2) element.value = date.print(frmt, true);
					element_value = date.print(frmt, true);
				}
				extra_action += var_sep + element.name + '=' + element_value;
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
			if (typeof element.name === "undefined" || element.name === null || !element.name) continue;
			
			if (element.name=='filter_order') {	element.value=='i.title'; continue; }
			if (element.name=='filter_order_Dir') { element.value=='ASC'; continue; }
			
			var matches = element.name.match(/(filter[.]*|letter)/);
			if (matches) {
				if (jQuery(element).data('select2')) {
					jQuery(element).select2('val', '');
				} else {
					element.value = '';
				}
			}
		}
	}
	
	function fc_toggleClass(ele, cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		var input_0 = jQuery(inputs[0]);
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
			if ( jQuery(ele).attr('checked') ) {
				jQuery(ele).next().addClass(cls);
				jQuery(ele).parent().addClass('fc_checkradio_checked');
			} else {
				jQuery(ele).next().removeClass(cls);
				jQuery(ele).parent().removeClass('fc_checkradio_checked');
			}
		  // Handle disabling 'select all' checkbox (if it exists), not needed but to make sure ...
		  if (input_0.val()=='') {
				input_0.prop('checked', false);
				input_0.next().removeClass(cls);
				input_0.parent().removeClass('fc_checkradio_checked');
		  }
		}
		else
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = jQuery(inputs[i]);
				input_i.prop('checked', false);
				input_i.next().removeClass(cls);
				input_i.parent().removeClass('fc_checkradio_checked');
			}
		  // Handle highlighting (but not enabling) 'select all' checkbox
			jQuery(ele).prop('checked', true);
			jQuery(ele).next().addClass(cls);
			jQuery(ele).parent().addClass('fc_checkradio_checked');
		}
		//alert('done fc_toggleClass()');
	}
	
	function fc_toggleClassGrp(ele, cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		var input_0 = jQuery(inputs[0]);
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = jQuery(inputs[i]);
				if ( input_i.attr('checked') ) {
					input_i.next().addClass(cls);
					input_i.parent().addClass('fc_checkradio_checked');
				} else {
					input_i.next().removeClass(cls);
					input_i.parent().removeClass('fc_checkradio_checked');
				}
			}
		}
		else
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = jQuery(inputs[i]);
				input_i.next().removeClass(cls);
				input_i.parent().removeClass('fc_checkradio_checked');
			}
		  // Handle highlighting (but not enabling) 'select all' radio button
			jQuery(ele).next().addClass(cls);
			jQuery(ele).parent().addClass('fc_checkradio_checked');
		}
		//alert('done fc_toggleClassGrp()');
	}


	function fc_progress(percent, element) {
		var progressBarWidth = percent * element.width() / 100;
		element.find('div').animate({ width: progressBarWidth }, 5000).html("");
	}



jQuery(document).ready(function() {

	// case-insensitive contains()
	jQuery.expr[':'].contains_ci_fc = function(el,i,txt){
		return (el.textContent || el.innerText || "").toUpperCase().indexOf(txt[3].toUpperCase()) >= 0;
	};
	
	// Add instant text type filter to lists
	jQuery('span.fc_list_filter_wrapper').each(function() {
		var list = jQuery(this).find('ul:first');
		// prepend text filter input to the list
		var form = jQuery("<form>").attr({"class":"fc_instant_filter", "action":"#"}),
		input = jQuery("<input>").attr({"class":"fc_field_filter fc_label_internal fc_instant_filter fc_autosubmit_exclude", "type":"text", "data-fc_label_text":Joomla.JText._('FLEXI_TYPE_TO_FILTER')});
		jQuery(form).append(input).insertBefore(this);
	
		jQuery(input)
		.change( function () {
			var filter = jQuery(this).val();
			if(filter) {
				jQuery(list).find("li:not(.fc_checkradio_checked):not(.fc_checkradio_special) label:not(:contains_ci_fc(" + filter + "))").parent().slideUp();
				jQuery(list).find("li:not(.fc_checkradio_checked):not(.fc_checkradio_special) label:contains_ci_fc(" + filter + ")").parent().slideDown();
			} else {
				jQuery(list).find("li").slideDown();
			}
			return false;
		})
		.keyup( function () {
			jQuery(this).change();
		});
	});
	

	// Initialize internal labels
	jQuery('input.fc_label_internal').each(function() {
		var el = jQuery(this);
		var fc_label_text = el.attr('data-fc_label_text');
		if (!fc_label_text) fc_label_text = el.attr('fc_label_text');
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
		var fc_label_text = el.attr('data-fc_label_text');
		if (!fc_label_text) fc_label_text = el.attr('fc_label_text');
		if (!fc_label_text) return;
		el.prev().hide();
		el.css("opacity", "1");
	}).bind('change blur', function(event) {
		var el = jQuery(this);
		var fc_label_text = el.attr('data-fc_label_text');
		if (!fc_label_text) fc_label_text = el.attr('fc_label_text');
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
	
	/* handle calender fields being changed by popup calendar, focus/blur is cross-browser compatible ... to detect input field being changed */
	/* up to J2.5 */
	jQuery('img.calendar').bind('click', function() {
		var el = jQuery(this).prev();
		el.prev().hide();
		el.attr('data-previous_value', el.val());
		el.css("opacity", "1");
		el.focus();
	});
	/* up to J3.0+ */
	jQuery('button i.icon-calendar').parent().bind('click', function() {
		var el = jQuery(this).prev();
		el.prev().hide();
		el.attr('data-previous_value', el.val());
		el.css("opacity", "1");
		el.focus();
	});
	if (typeof Calendar !== "undefined" && typeof Calendar.prototype.callCloseHandler === 'function') {
		var oldFunc = Calendar.prototype.callCloseHandler;
		Calendar.prototype.callCloseHandler = function() {
			var oldFuncResult = oldFunc.apply(this, arguments);
			document.activeElement.blur();
			return oldFuncResult;
		}
	}
	
	var fc_select_pageSize = 10;
	
	// add Simple text search autocomplete
	if (typeof jQuery.ui != 'undefined' && typeof jQuery.ui.autocomplete==='function') {
		var theElements = jQuery("input.fc_index_complete_simple");
		theElements.each(function () {
			jQuery.ui.autocomplete( {
				source: function( request, response ) {
					el = jQuery(this.element);
					jQuery.ajax({
						url: "index.php?option=com_flexicontent&tmpl=component",
						dataType: "json",
						data: {
							type: (el.hasClass('fc_adv_complete') ? "adv_index" : "basic_index"),
							task: "txtautocomplete",
							pageSize: fc_select_pageSize,
							text: request.term,
							lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: ''),
							cid: parseInt(_FC_GET['cid']),
							cids: _FC_GET['cids'],
							filter_13: _FC_GET['filter_13']
						},
						success: function( data ) {
							//console.log( '... done' );
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
					//console.log( ui.item  ?  "Selected: " + ui.item.label  :  "Nothing selected, input was " + this.value);
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
					//console.log( 'quering ... ' );
					jQuery(this).addClass('working');
				}
			}, this );
		});
	}



	// add Tag-Like text search autocomplete
	if(typeof jQuery('input.fc_index_complete_tlike').select2!=='undefined') {
		jQuery('input.fc_index_complete_tlike').select2(
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
						type: (jQuery(this).hasClass('fc_adv_complete') ? "adv_index" : "basic_index"),
						task: "txtautocomplete",
						text: term,
						pageSize: fc_select_pageSize,
						pageNum: page,
						lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: ''),
						cid: parseInt(_FC_GET['cid']),
						cids: _FC_GET['cids'],
						filter_13: _FC_GET['filter_13']
					};
				},
				results: function (data, page) {
					//Used to determine whether or not there are more results available,
					//and if requests for more data should be sent in the infinite scrolling
					var more = (page * fc_select_pageSize) < data.Total;
					return { results: data.Matches, more: more };
				}
			}
		});
	}
	
	jQuery('body').prepend(
	 	'<span id="fc_filter_form_blocker">' +
	    '<span class="fc_blocker_opacity"></span>' +
	    '<span class="fc_blocker_content">' +
	    	Joomla.JText._('FLEXI_APPLYING_FILTERING') +
	    	'<div class="fc_blocker_bar"><div></div></div>' +
	    '</span>' +
	  '</span>');
		
		fc_recalculateWindow();
});


// recalculate window width/height and window scrollbars
function fc_recalculateWindow()
{
	// Set these to hidden to force scrollbar recalculation when we set to auto	
	/*document.documentElement.style.overflow = "hidden";
	document.body.style.overflow = "hidden";
	
	// make sure widht & height is automatic
	document.documentElement.style.height = "auto";
	document.documentElement.style.width  = "auto";
	document.body.style.height = "auto";
	document.body.style.width  = "auto";
	
	setTimeout(function() {
		document.documentElement.style.overflow = "";  // firefox, chrome, ie11+
		document.body.style.overflow = "";
	}, 100);*/
	
	// reset popup overlay containers ... TODO add more ?
	jQuery('#OverlayContainer').css("height", jQuery('body').css('height'));
}
