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
	if (typeof jQuery.fn.autocomplete==='function') {
		jQuery( "input.fc_index_complete_simple" ).autocomplete({
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
	 	"<span id='fc_filter_form_blocker'>" +
	    "<span class='fc_blocker_opacity'></span>" +
	    "<span class='fc_blocker_content'>" +
	    	Joomla.JText._('FLEXI_APPLYING_FILTERING') +
	    	"<div class='fc_blocker_bar'><div></div></div>" +
	    "</span>" +
	  "</span>");
		
		fc_recalculateWindow();
});


/* recalculate window width/height and widow scrollbars */
function fc_recalculateWindow()
{
	// Set these to hidden to force scrollbar recalculation when we set to auto	
	document.documentElement.style.overflow = "hidden";
	document.body.style.overflow = "hidden";
	
	// make sure widht & height is automatic
	document.documentElement.style.height = "auto";
	document.documentElement.style.width  = "auto";
	document.body.style.height = "auto";
	document.body.style.width  = "auto";
	
	//document.body.scroll = "no";  // old ie versions ??
	setTimeout(function() {
		document.documentElement.style.overflow = "";  // firefox, chrome, ie11+
		document.body.style.overflow = "";
		//document.body.scroll = "yes";  // old ie versions ??
	}, 100);
	
	/* reset popup overlay containers ... TODO add more ? */
	jQuery('#OverlayContainer').css("height", jQuery('body').css('height'));
}


/* 
 * FOLLOWING part are jQuery easing functions authored by George McGinley Smith
 *
 * jQuery Easing v1.3 - http://gsgd.co.uk/sandbox/jquery/easing/
 *
 * Uses the built in easing capabilities added In jQuery 1.1 to offer multiple easing options
 *
 * TERMS OF USE - jQuery Easing
 * 
 * Open source under the BSD License. 
 * 
 * Copyright © 2008 George McGinley Smith
 * All rights reserved.
 */

// t: current time, b: begInnIng value, c: change In value, d: duration
jQuery.easing['jswing'] = jQuery.easing['swing'];

jQuery.extend( jQuery.easing,
{
	def: 'easeOutQuad',
	swing: function (x, t, b, c, d) {
		//alert(jQuery.easing.default);
		return jQuery.easing[jQuery.easing.def](x, t, b, c, d);
	},
	
	
	easeInQuad: function (x, t, b, c, d) {
		return c*(t/=d)*t + b;
	},
	easeOutQuad: function (x, t, b, c, d) {
		return -c *(t/=d)*(t-2) + b;
	},
	easeInOutQuad: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t + b;
		return -c/2 * ((--t)*(t-2) - 1) + b;
	},
	
	
	easeInCubic: function (x, t, b, c, d) {
		return c*(t/=d)*t*t + b;
	},
	easeOutCubic: function (x, t, b, c, d) {
		return c*((t=t/d-1)*t*t + 1) + b;
	},
	easeInOutCubic: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t + b;
		return c/2*((t-=2)*t*t + 2) + b;
	},
	
	
	easeInQuart: function (x, t, b, c, d) {
		return c*(t/=d)*t*t*t + b;
	},
	easeOutQuart: function (x, t, b, c, d) {
		return -c * ((t=t/d-1)*t*t*t - 1) + b;
	},
	easeInOutQuart: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
		return -c/2 * ((t-=2)*t*t*t - 2) + b;
	},
	
	
	easeInQuint: function (x, t, b, c, d) {
		return c*(t/=d)*t*t*t*t + b;
	},
	easeOutQuint: function (x, t, b, c, d) {
		return c*((t=t/d-1)*t*t*t*t + 1) + b;
	},
	easeInOutQuint: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t*t*t + b;
		return c/2*((t-=2)*t*t*t*t + 2) + b;
	},
	
	
	easeInSine: function (x, t, b, c, d) {
		return -c * Math.cos(t/d * (Math.PI/2)) + c + b;
	},
	easeOutSine: function (x, t, b, c, d) {
		return c * Math.sin(t/d * (Math.PI/2)) + b;
	},
	easeInOutSine: function (x, t, b, c, d) {
		return -c/2 * (Math.cos(Math.PI*t/d) - 1) + b;
	},
	
	
	easeInExpo: function (x, t, b, c, d) {
		return (t==0) ? b : c * Math.pow(2, 10 * (t/d - 1)) + b;
	},
	easeOutExpo: function (x, t, b, c, d) {
		return (t==d) ? b+c : c * (-Math.pow(2, -10 * t/d) + 1) + b;
	},
	easeInOutExpo: function (x, t, b, c, d) {
		if (t==0) return b;
		if (t==d) return b+c;
		if ((t/=d/2) < 1) return c/2 * Math.pow(2, 10 * (t - 1)) + b;
		return c/2 * (-Math.pow(2, -10 * --t) + 2) + b;
	},
	
	
	easeInCirc: function (x, t, b, c, d) {
		return -c * (Math.sqrt(1 - (t/=d)*t) - 1) + b;
	},
	easeOutCirc: function (x, t, b, c, d) {
		return c * Math.sqrt(1 - (t=t/d-1)*t) + b;
	},
	easeInOutCirc: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return -c/2 * (Math.sqrt(1 - t*t) - 1) + b;
		return c/2 * (Math.sqrt(1 - (t-=2)*t) + 1) + b;
	},
	
	
	easeInElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		return -(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
	},
	easeOutElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		return a*Math.pow(2,-10*t) * Math.sin( (t*d-s)*(2*Math.PI)/p ) + c + b;
	},
	easeInOutElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d/2)==2) return b+c;  if (!p) p=d*(.3*1.5);
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		if (t < 1) return -.5*(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
		return a*Math.pow(2,-10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )*.5 + c + b;
	},
	
	
	easeInBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158;
		return c*(t/=d)*t*((s+1)*t - s) + b;
	},
	easeOutBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158;
		return c*((t=t/d-1)*t*((s+1)*t + s) + 1) + b;
	},
	easeInOutBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158; 
		if ((t/=d/2) < 1) return c/2*(t*t*(((s*=(1.525))+1)*t - s)) + b;
		return c/2*((t-=2)*t*(((s*=(1.525))+1)*t + s) + 2) + b;
	},
	
	
	easeInBounce: function (x, t, b, c, d) {
		return c - jQuery.easing.easeOutBounce (x, d-t, 0, c, d) + b;
	},
	easeOutBounce: function (x, t, b, c, d) {
		if ((t/=d) < (1/2.75)) {
			return c*(7.5625*t*t) + b;
		} else if (t < (2/2.75)) {
			return c*(7.5625*(t-=(1.5/2.75))*t + .75) + b;
		} else if (t < (2.5/2.75)) {
			return c*(7.5625*(t-=(2.25/2.75))*t + .9375) + b;
		} else {
			return c*(7.5625*(t-=(2.625/2.75))*t + .984375) + b;
		}
	},
	easeInOutBounce: function (x, t, b, c, d) {
		if (t < d/2) return jQuery.easing.easeInBounce (x, t*2, 0, c, d) * .5 + b;
		return jQuery.easing.easeOutBounce (x, t*2-d, 0, c, d) * .5 + c*.5 + b;
	}
});
