	/* Set a cookie */
	function fc_setCookie(cookieName, cookieValue, nDays, samesite="lax")
	{
		var today = new Date();
		var expire = new Date();
		var path = window.location.hostname;
		if (nDays==null || nDays<0) nDays=0;

		if (nDays)
		{
			expire.setTime(today.getTime() + 3600000*24*nDays);
			document.cookie = cookieName+"="+encodeURIComponent(cookieValue) + ";samesite=" + samesite + ";path=" + path + ";expires=" + expire.toGMTString();
		} else {
			document.cookie = cookieName+"="+encodeURIComponent(cookieValue) + ";samesite=" + samesite + ";path=" + path;
		}
		//alert(cookieName+"="+encodeURIComponent(cookieValue) + ";path=" + path);
	}

	/* Get a cookie */
	function fc_getCookie(cookieName)
	{
		let matched = document.cookie.match(new RegExp('(^|;\\s*)' + cookieName + '=([^;]+)'));
		return matched ? decodeURIComponent(matched[2]) : '';
	}


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
		var loader = document.getElementById(loader_el);
		if (loader) loader.innerHTML = loader_html;
		fetch(url_to_load)
		.then(function(response) {
			return response.text();
		})
		.then(function(responseText) {
			form.action = responseText;
			form.setAttribute('data-fcform_action', responseText);
			if (autosubmit) {
				if (loader) loader.insertAdjacentHTML('beforeend', autosubmit_msg || '');
				adminFormPrepare(form, 2);
			} else {
				if (loader) loader.innerHTML = '';
			}
		})
		.catch(function(err) {
			alert('Error fetching: ' + err);
		});
	}


	function adminFormPrepare(form, postprep, task)
	{
		var extra_action = '';

		var fcform_action = form.getAttribute('data-fcform_action');
		if (fcform_action === null)
		{
			fcform_action = form.getAttribute('action');
			form.setAttribute('data-fcform_action', fcform_action);
		}

		var var_sep = fcform_action.match(/\?/) ? '&' : '?';

		for (i=0; i<form.elements.length; i++)
		{
			var element = form.elements[i];
			if (typeof element.name === "undefined" || element.name === null || !element.name) continue;

			// No need to add the default values for ordering, to the URL
			if (element.name=='filter_order' && element.value=='i.title') continue;
			if (element.name=='filter_order_Dir' && element.value=='ASC') continue;

			var matches = element.name.match(/^(filter.*|cids|letter|clayout|limit|orderby|orderby_2nd|listall|q|searchword|p|searchphrase|areas\[\]|contenttypes\[\]|txtflds|o|ordering)$/);
			if (!matches || element.value == '') continue;

			if ( (element.type=='radio' || element.type=='checkbox') )
			{
				if ( !element.checked ) continue;
				if ( element.getAttribute('data-is-default-value') == '1' )
				{
					if (postprep==2) element.disabled = true;
					continue;
				}
			}
			if ( element.type=='select-one' )
			{
				var selected_opt = element.options[element.selectedIndex];
				if ( selected_opt && selected_opt.getAttribute('data-is-default-value') )
				{
					if (postprep==2) element.disabled = true;
					continue;
				}
			}

			if ( element.type=='select-multiple' )
			{
				for (var p=0; p < element.length; p++)
				{
					if ( ! element.options[p].selected ) continue;
					extra_action += var_sep + element.name.replace("[]","") + '[' + ']=' + encodeURIComponent(element.options[p].value);
					var_sep = '&';
				}
			}
			else
			{
				var element_value = element.value;
				if ( element.classList.contains('fc_iscalendar') && typeof JoomlaCalendar !== 'function' )
				{
					var frmt = '%Y-%m-%d';
					var date = Date.parseDate(element.value || element.innerHTML, frmt);
					if (postprep==2) element.value = date.print(frmt, true);
					element_value = date.print(frmt, true);
				}
				extra_action += var_sep + element.name + '=' + encodeURIComponent(element_value);
				var_sep = '&';
			}
		}

		var fc_uid = fc_getCookie('fc_uid');
		if (extra_action)
		{
      if (fc_uid != '')
  			extra_action += var_sep + 'cc' + '=' + fc_uid;
      else
  			extra_action += var_sep + 'cc' + '=' + 'p';
		}
		form.action = fcform_action + extra_action;  //alert(form.action);

		if (typeof postprep !== "undefined" && postprep !== null && postprep!=0)
		{
			if (postprep==2)
			{
				var fc_filter_form_blocker = document.getElementById("fc_filter_form_blocker");
				if (extra_action === '' && !!!task)
					window.location.href = fcform_action;
				else
					form.submit( task );
				if (fc_filter_form_blocker)
				{
					fc_filter_form_blocker.style.display = "block";
					fc_progress(95, fc_filter_form_blocker.querySelector('.fc_blocker_bar'));
				}
			}
			else if (postprep==1)
			{
				var form_id = form.getAttribute('id');
				var warn_el = document.getElementById(form_id+'_submitWarn');
				if (warn_el) warn_el.style.display = "inline-block";
			}
		}
	}

	function adminFormClearFilters (form)
	{
		for(i=0; i<form.elements.length; i++)
		{
			var element = form.elements[i];
			if (typeof element.name === "undefined" || element.name === null || !element.name) continue;

			if (element.name=='filter_order') {	element.value='i.title'; continue; }
			if (element.name=='filter_order_Dir') { element.value='ASC'; continue; }

			var matches = element.name.match(/(filter[.]*|letter)/);
			if (matches)
			{
				if (window.jQuery && window.jQuery.fn && element.s2) {
					window.jQuery(element).select2('val', '');
				} else if (window.fc_use_choicesjs && window.fc_choices_instances) {
					var instanceKey = element.id || element.name;
					var instance = window.fc_choices_instances[instanceKey];
					if (instance) {
						instance.removeActiveItems();
					}
				} else {
					element.value = '';
				}
			}
		}
	}

	function fc_toggleClass(ele, cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		var input_0 = inputs[0];
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
			if ( ele.checked ) {
				ele.nextElementSibling.classList.add(cls);
				ele.parentNode.classList.add('fc_checkradio_checked');
			} else {
				ele.nextElementSibling.classList.remove(cls);
				ele.parentNode.classList.remove('fc_checkradio_checked');
			}
		  // Handle disabling 'select all' checkbox (if it exists), not needed but to make sure ...
		  if (input_0.value=='') {
				input_0.checked = false;
				input_0.nextElementSibling.classList.remove(cls);
				input_0.parentNode.classList.remove('fc_checkradio_checked');
		  }
		}
		else
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = inputs[i];
				input_i.checked = false;
				input_i.nextElementSibling.classList.remove(cls);
				input_i.parentNode.classList.remove('fc_checkradio_checked');
			}
		  // Handle highlighting (but not enabling) 'select all' checkbox
			ele.checked = true;
			ele.nextElementSibling.classList.add(cls);
			ele.parentNode.classList.add('fc_checkradio_checked');
		}
		//alert('done fc_toggleClass()');
	}

	function fc_toggleClassGrp(ele, cls, fc_all) {
		var inputs = ele.parentNode.parentNode.getElementsByTagName('input');
		if (typeof fc_all === "undefined" || fc_all === null || !fc_all)
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = inputs[i];
				if ( input_i.checked ) {
					input_i.nextElementSibling.classList.add(cls);
					input_i.parentNode.classList.add('fc_checkradio_checked');
				} else {
					input_i.nextElementSibling.classList.remove(cls);
					input_i.parentNode.classList.remove('fc_checkradio_checked');
				}
			}
		}
		else
		{
			for (var i = 0; i < inputs.length; ++i) {
				var input_i = inputs[i];
				input_i.nextElementSibling.classList.remove(cls);
				input_i.parentNode.classList.remove('fc_checkradio_checked');
			}
		  // Handle highlighting (but not enabling) 'select all' radio button
			ele.nextElementSibling.classList.add(cls);
			ele.parentNode.classList.add('fc_checkradio_checked');
		}
		//alert('done fc_toggleClassGrp()');
	}


	function fc_progress(percent, element) {
		if (!element) return;
		var bar = element.querySelector ? element.querySelector('div') : null;
		if (!bar) bar = element;
		var progressBarWidth = percent * (element.offsetWidth || 100) / 100;
		bar.style.width = progressBarWidth + 'px';
		bar.innerHTML = '';
	}



document.addEventListener('DOMContentLoaded', function() {

	// case-insensitive contains() helper (replaces jQuery.expr :contains_ci_fc)
	function fcContainsCI(text, needle) {
		return (text || "").toUpperCase().indexOf((needle || "").toUpperCase()) >= 0;
	}

	// Add instant text type filter to lists
	var listWrappers = document.querySelectorAll('div.fc_list_filter_wrapper');
	for (var w = 0; w < listWrappers.length; w++) {
		var wrapper = listWrappers[w];
		var list = wrapper.querySelector('ul');
		if (!list) continue;
		// prepend text filter input to the list
		var form = document.createElement('form');
		form.className = 'fc_instant_filter';
		form.setAttribute('action', '#');
		var input = document.createElement('input');
		input.className = 'fc_field_filter fc_label_internal fc_instant_filter fc_autosubmit_exclude';
		input.type = 'text';
		input.setAttribute('data-fc_label_text', Joomla.JText._('FLEXI_TYPE_TO_FILTER'));
		form.appendChild(input);
		wrapper.insertBefore(form, wrapper.firstChild);

		var filterInput = input;
		filterInput.addEventListener('change', function() {
			var filter = filterInput.value;
			var lis = list.querySelectorAll('li:not(.fc_checkradio_checked):not(.fc_checkradio_special)');
			for (var l = 0; l < lis.length; l++) {
				var li = lis[l];
				var label = li.querySelector('label');
				var show = !filter || fcContainsCI(label ? label.textContent : '', filter);
				li.style.display = show ? '' : 'none';
			}
		});
		filterInput.addEventListener('keyup', function() {
			filterInput.dispatchEvent(new Event('change'));
		});
	}


	// Initialize internal labels
	var labelInputs = document.querySelectorAll('input.fc_label_internal');
	for (var li = 0; li < labelInputs.length; li++) {
		var labelEl = labelInputs[li];
		var fc_label_text = labelEl.getAttribute('data-fc_label_text');
		if (!fc_label_text) fc_label_text = labelEl.getAttribute('fc_label_text');
		if (!fc_label_text) continue;
		var _label = (fc_label_text.length >= 27) ? fc_label_text.substring(0, 25) + '...' : fc_label_text;

		var labelSpan = document.createElement('span');
		labelSpan.className = 'fc_has_inner_label fc_has_inner_label_input';
		labelSpan.textContent = _label;
		labelEl.parentNode.insertBefore(labelSpan, labelEl);
		if (labelEl.value && labelEl.value.length > 0) {
			var labelPrev = labelEl.previousElementSibling;
			if (labelPrev) labelPrev.style.display = 'none';
		}
	}

	var labelInputs2 = document.querySelectorAll('input.fc_label_internal');
	for (var li2 = 0; li2 < labelInputs2.length; li2++) {
		(function(el) {
			el.addEventListener('focus', function() {
				var fc_label_text = el.getAttribute('data-fc_label_text');
				if (!fc_label_text) fc_label_text = el.getAttribute('fc_label_text');
				if (!fc_label_text) return;
				var prev = el.previousElementSibling;
				if (prev) prev.style.display = 'none';
				el.style.opacity = "1";
			});
			el.addEventListener('change', function() {
				var fc_label_text = el.getAttribute('data-fc_label_text');
				if (!fc_label_text) fc_label_text = el.getAttribute('fc_label_text');
				if (!fc_label_text) return;
				var prev = el.previousElementSibling;
				if (el.value && el.value.length) {
					if (prev) prev.style.display = 'none';
					el.style.opacity = "1";
				} else {
					if (prev) prev.style.display = '';
					el.style.opacity = "0.5";
				}
			});
			el.addEventListener('blur', function() {
				var fc_label_text = el.getAttribute('data-fc_label_text');
				if (!fc_label_text) fc_label_text = el.getAttribute('fc_label_text');
				if (!fc_label_text) return;

				var previous_value = el.getAttribute('data-previous_value');
				if (typeof previous_value !== "undefined" && previous_value != el.value) {
					el.dispatchEvent(new Event('change'));
				}

				var prev = el.previousElementSibling;
				if (el.value && el.value.length) {
					if (prev) prev.style.display = 'none';
					el.style.opacity = "1";
				} else {
					if (prev) prev.style.display = '';
					el.style.opacity = "0.5";
				}
			});
		})(labelInputs2[li2]);
	}

	// handle calender fields being changed by popup calendar
	var calIcons = document.querySelectorAll('input + button .icon-calendar');
	for (var ci = 0; ci < calIcons.length; ci++) {
		var calBtn = calIcons[ci].parentElement;
		var calInput = calBtn.previousElementSibling;
		calBtn.addEventListener('click', function() {
			var newCalendar = typeof JoomlaCalendar === 'function';
			var el = calInput;
			el.setAttribute('data-previous_value', el.value);

			// Set a singular variable for the current input field
			if (!newCalendar)
			{
				Calendar.prototype.__currentCalendarInput = el;
			}
			else
			{
				JoomlaCalendar.prototype.__currentCalendarInput = el;
			}
			//el.focus(); // Set document.activeElement so that close handler of calendar will find it
		});
	}


	if (typeof JoomlaCalendar !== "undefined")
	{
		var oldFunc = JoomlaCalendar.prototype.close;
		JoomlaCalendar.prototype.close = function()
		{
			var oldFuncResult = oldFunc.apply(this, arguments);
			var el = JoomlaCalendar.prototype.__currentCalendarInput; //document.activeElement;
			if (!!el)
			{
				var previous_value = el.getAttribute('data-previous_value');
				if (typeof previous_value !== "undefined" && previous_value != el.value)
				{
					el.dispatchEvent(new Event('change'));
				}
			}
			return oldFuncResult;
		}
	}

	else if (typeof Calendar !== "undefined" && typeof Calendar.prototype.callCloseHandler === 'function')
	{
		var oldFunc = Calendar.prototype.callCloseHandler;
		Calendar.prototype.callCloseHandler = function()
		{
			var oldFuncResult = oldFunc.apply(this, arguments);
			var el = Calendar.prototype.__currentCalendarInput; //document.activeElement;
			var previous_value = el.getAttribute('data-previous_value');
			if (typeof previous_value !== "undefined" && previous_value != el.value)
			{
				el.dispatchEvent(new Event('change'));
			}
			return oldFuncResult;
		}
	}

	var fc_select_pageSize = 10;

	// add Simple text search autocomplete
	if (typeof jQuery !== 'undefined' && typeof jQuery.ui != 'undefined' && typeof jQuery.ui.autocomplete==='function') {
		var theElements = jQuery("input.fc_index_complete_simple");
		theElements.each(function () {
			jQuery.ui.autocomplete( {
				source: function( request, response ) {
					var el = jQuery(this.element);
					var el_lang = el.attr('data-txt_ac_lang') ? el.attr('data-txt_ac_lang') : '';
					var el_cid  = el.attr('data-txt_ac_cid')  ? el.attr('data-txt_ac_cid')  : (parseInt(FC_URL_VARS['cid']) || 0);
					var el_cids = el.attr('data-txt_ac_cids') ? el.attr('data-txt_ac_cids') : FC_URL_VARS['cids'];
					var el_usesubs = parseInt(el.attr('data-txt_ac_usesubs')) || 0;
					jQuery.ajax({
						url: (jroot_url_fc + "components/com_flexicontent/tasks/core.php"),
						dataType: "json",
						data: {
							type: (el.hasClass('fc_adv_complete') ? "adv_index" : "basic_index"),
							task: "txtautocomplete",
							pageSize: fc_select_pageSize,
							text: request.term,
							lang: el_lang,
							cid: el_cid,
							cids: el_cids,
							usesubs: el_usesubs
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
					jQuery(ele).val(ui.item.value); // set value before triggering change
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
	if (window.fc_use_choicesjs || (typeof jQuery !== 'undefined' && typeof jQuery('input.fc_index_complete_tlike').select2 !== 'undefined')) {
		if (window.fc_use_choicesjs) {
			// Choices.js AJAX autocomplete
			var tlikeInputs = document.querySelectorAll('input.fc_index_complete_tlike');
			for (var tl = 0; tl < tlikeInputs.length; tl++) {
				var el = tlikeInputs[tl];
				// Convert space-separated value to initial choices
				var initialData = [];
				if (el.value) {
					el.value.split(" ").forEach(function(val) {
						if (val.trim()) {
							initialData.push({ value: val.trim(), label: val.trim(), selected: true });
						}
					});
				}

				var choicesInstance = new Choices(el, {
					maxItemCount: -1,
					placeholderValue: Joomla.JText._('FLEXI_TYPE_TO_LIST'),
					searchPlaceholderText: Joomla.JText._('FLEXI_TYPE_TO_LIST'),
					searchChoices: false,
					paste: true,
					removeItemButton: true,
					shouldSort: false
				});

				if (initialData.length) {
					choicesInstance.setChoices(initialData);
				}

				// AJAX search
				var searchTimeout = null;
				el.addEventListener('search', function(e) {
					var searchTerm = e.detail.value;
					if (searchTimeout) clearTimeout(searchTimeout);
					if (!searchTerm || searchTerm.length < 1) return;

					searchTimeout = setTimeout(function() {
						var type = el.classList.contains('fc_adv_complete') ? "adv_index" : "basic_index";
						var params = {
							type: type,
							task: "txtautocomplete",
							text: searchTerm,
							pageSize: typeof fc_select_pageSize !== 'undefined' ? fc_select_pageSize : 50,
							pageNum: 1,
							lang: (el.getAttribute('data-txt_ac_lang') ? el.getAttribute('data-txt_ac_lang') : ''),
							cid:  (el.getAttribute('data-txt_ac_cid')  ? el.getAttribute('data-txt_ac_cid')  : (typeof FC_URL_VARS !== 'undefined' ? (parseInt(FC_URL_VARS['cid']) || 0) : 0)),
							cids: (el.getAttribute('data-txt_ac_cids') ? el.getAttribute('data-txt_ac_cids') : (typeof FC_URL_VARS !== 'undefined' ? FC_URL_VARS['cids'] : '')),
							usesubs: (parseInt(el.getAttribute('data-txt_ac_usesubs')) || 0)
						};

						fetch(jroot_url_fc + "components/com_flexicontent/tasks/core.php?" + new URLSearchParams(params))
							.then(function(response) { return response.json(); })
							.then(function(data) {
								if (data && data.Matches && data.Matches.length) {
									var resultChoices = data.Matches.map(function(m) {
										return { value: m.id, label: m.text };
									});
									// Replace dropdown choices in-place (keeps pre-selected initial tags in the list)
									choicesInstance.setChoices(initialData.concat(resultChoices), 'value', 'label', true);
								}
							});
					}, 200);
				});

				// Store instance for external access
				el.fc_choices_instance = choicesInstance;
				window.fc_choices_instances = window.fc_choices_instances || {};
				window.fc_choices_instances[el.id || el.name] = choicesInstance;
				}
		} else {
			// Select2 AJAX autocomplete (legacy)
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
					url: (jroot_url_fc + "components/com_flexicontent/tasks/core.php"),
					dataType: 'json',
					//Our search term and what page we are on
					data: function (term, page) {
						return {
							type: (jQuery(this).hasClass('fc_adv_complete') ? "adv_index" : "basic_index"),
							task: "txtautocomplete",
							text: term,
							pageSize: fc_select_pageSize,
							pageNum: page,
							lang: (jQuery(this).attr('data-txt_ac_lang') ? jQuery(this).attr('data-txt_ac_lang') : ''),
							cid:  (jQuery(this).attr('data-txt_ac_cid')  ? jQuery(this).attr('data-txt_ac_cid')  : (parseInt(FC_URL_VARS['cid']) || 0)),
							cids: (jQuery(this).attr('data-txt_ac_cids') ? jQuery(this).attr('data-txt_ac_cids') : FC_URL_VARS['cids']),
							usesubs: (parseInt(jQuery(this).attr('data-txt_ac_usesubs')) || 0)
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
	}



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
	var overlayContainer = document.getElementById('OverlayContainer');
	if (overlayContainer) overlayContainer.style.height = (document.body.offsetHeight) + 'px';
}


function fc_replaceUrlParam(url, paramName, paramValue)
{
	var pattern = new RegExp('('+paramName+'=).*?(&|$)');
	return (url.search(pattern)>=0) ?
		url.replace(pattern,'$1' + paramValue + '$2') :
		url + (url.indexOf('?')>0 ? '&' : '?') + paramName + '=' + paramValue;
}



// redirect contents (cc param) after cookie mis-match
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', fc_checkContentCookie);
} else {
	fc_checkContentCookie();
}

function fc_checkContentCookie() {
	var cc = (typeof FC_URL_VARS != "undefined" && 'cc' in FC_URL_VARS ? FC_URL_VARS['cc']: '');
	var fc_uid = fc_getCookie('fc_uid');
	var base_url = !!jbase_url_fc ? jbase_url_fc : '';

	if (cc!='' && fc_uid!=cc)
	{
		var staleUrl = window.location.href;
		newUrl = fc_replaceUrlParam(staleUrl, 'cc', fc_uid);
		window.location.replace(newUrl);
		document.body.innerHTML = '<div style="display:none;">' + Joomla.JText._('FLEXI_UPDATING_CONTENTS') + ' <img id="loading_img" src="'+base_url+'components/com_flexicontent/assets/images/ajax-loader.gif"></div>';
	}
}
