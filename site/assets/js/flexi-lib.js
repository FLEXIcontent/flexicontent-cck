	
	window.fc_init_hide_dependent = 1;
	window.fc_refreshing_dependent = 0;
	window.fc_dependent_params = {};
	window.fc_cascade_field_funcs = {};
	
	
	function fc_getAutoSizePos(winwidth, winheight, params)
	{
		
		var autoWidth  = typeof winwidth === 'undefined'  || !winwidth;
		var autoHeight = typeof winheight === 'undefined' || !winheight;
		
		params.dialogClass = typeof params.dialogClass !== 'undefined'  ?  params.dialogClass  :  'fc-fixed-dialog';
		if (autoWidth)  params.dialogClass += ' fc-autow-dialog';
		if (autoHeight) params.dialogClass += ' fc-autoh-dialog';
		
		var w = typeof winwidth !== 'undefined' && winwidth  ? winwidth  : jQuery( window ).width() - 80;
		var h = typeof winheight!== 'undefined' && winheight ? winheight : jQuery( window ).height() - 120;
		
		params.winwidth  = w  > (jQuery( window ).width() - 80)   ? (jQuery( window ).width() - 80)  :  w;
		params.winheight = h  > (jQuery( window ).height() - 120) ? (jQuery( window ).height() - 120) : h;
		//window.console.log ('winwidth  : ' + params.winwidth  + ', winheight : ' + params.winheight );
		
		params.winleft = (jQuery( window ).width()  - params.winwidth)  / 2 + 5;
		params.wintop  = (jQuery( window ).height() - params.winheight) / 2 - 5;
		//window.console.log ('winleft : ' + params.winleft + ', wintop : ' + params.wintop);
		
		return params;
	}
	
	
	function fc_loadImagePreview(input_id, img_id, msg_id, thumb_w, thumb_h, nonimg_mssg)
	{
		var nonimg_mssg = typeof nonimg_mssg !== 'undefined' ? nonimg_mssg : '';
		var input = document.getElementById(input_id);
		var msg_box = document.getElementById(msg_id);
		var _msg = '';
		
		var input_files = input.files;
		if (input_files && input_files[0]) {
			var imageType = /image.*/;
			if (!input_files[0].type.match(imageType)) {
		  	if (nonimg_mssg=='-1') _msg = '';
		  	else if (nonimg_mssg=='-2') _msg = input.value;
		  	else if (nonimg_mssg=='0' || nonimg_mssg=='') _msg = (nonimg_mssg!='' ? nonimg_mssg : input.value+' <br/> '+Joomla.JText._('FLEXI_NOT_AN_IMAGE_FILE'));
		  	jQuery('#'+img_id).hide();
		  } else {
		  	if (nonimg_mssg=='-2' || nonimg_mssg=='0') _msg = input.value;
		  	else _msg = '';
				var reader = new FileReader();
				reader.onload = function (e) {
					var img = jQuery('#'+img_id);
					img.attr('src', e.target.result);
					if (thumb_w) img.width(thumb_w);
					if (thumb_h) img.height(thumb_h);
					img.show();
				};
				reader.readAsDataURL(input_files[0]);
			}
		}
		msg_box.nodeName == 'INPUT' ? msg_box.value = input.value : msg_box.innerHTML = input.value;
	}
	
	
	// Display content in modal popup
	function fc_showAsDialog(obj, winwidth, winheight, closeFunc, params)
	{
		params = typeof params !== 'undefined' ? params : {};
		params = fc_getAutoSizePos(winwidth, winheight, params);
		
		// Get close function
		var closeFunc = typeof closeFunc !== 'undefined' && closeFunc ? closeFunc : 0;
		var keepPlace = !!params.keepPlace;
		var visibleOnClose = !!params.visibleOnClose;
		
		// Because allowing moving modal to be moved out of form (under body), we need to set form ATTRIBUTE to form, for any form elements inside it
		if (!keepPlace)
		{
			var form_fields = obj.find('textarea, select, input, button').toArray();
			for ( var i = 0, l = form_fields.length; i < l; i++ )
			{
				// Get field type / tag name
				var $el = jQuery(form_fields[i]);
				var form = form_fields[i].form;
				if (form && form.id && !$el.attr('form'))
				{
					$el.attr('form', form.id);
				}
			}
		}
		
		var parent = obj.parent();
		var theDialog = obj.dialog({
			title: params.title,
			closeOnEscape: (typeof params.closeOnEscape !== 'undefined'  ?  params.closeOnEscape  :  true),
			resize: false,
			autoOpen: false,
			width: params.winwidth,
			height: params.winheight,
			position: [params.winleft, params.wintop],
			modal: (typeof params.modal !== 'undefined'  ?  params.modal  :  true),
			dialogClass: params.dialogClass,
			icons: {
				primary: "ui-icon-heart"
			},
			buttons: {
				/*'Close': function() { jQuery(this).dialog('close'); }*/
			},
			// Clear contents after dialog closes
			close: function(ev, ui) {
				// Allow parent document to scroll again
				jQuery(document.body).removeClass('fc-no-scroll');
				
				// Destroy the dialog completely, this is needed because we have moved the content back into its proper position
				jQuery(this).dialog('destroy');

				if (visibleOnClose) obj.css({'height': '', 'width': ''}).show();

				// Finalize by doing the closing operation
				if (typeof closeFunc === 'function') closeFunc(obj);
				else if (closeFunc == 1) window.location.reload(false);
				else if (closeFunc == 0) ;
				else alert('Unknown action'+closeFunc);
			}
		})
		.dialog('widget').next('.ui-widget-overlay').css('background', 'gray');  // Add an overlay
		
		// Manually move the dialog content back into its proper position, (the extra dialog container will be destroyed on dialog close)
		if (keepPlace) obj.parent().appendTo(parent);
		
		// Open the dialog manually
		theDialog = obj.dialog('open');
		
		// Stop scrolling of parent document
		jQuery(document.body).addClass('fc-no-scroll');
		
		// Return a reference of the dialog to the caller
		return theDialog;
	}
	
	
	// Load given URL in an open dialog
	function fc_showDialog(url, tagid, no_iframe, winwidth, winheight, closeFunc, params)
	{
		params = typeof params !== 'undefined' ? params : {};
		params = fc_getAutoSizePos(winwidth, winheight, params);
		
		var loadFunc = typeof params.loadFunc !== 'undefined' && params.loadFunc ? params.loadFunc : false;
		
		// Get close function
		var closeFunc = typeof closeFunc !== 'undefined' && closeFunc ? closeFunc : 0;
		
		// Get container creating it if it does not exist
		var container = jQuery('#'+tagid);
		if (!container.length) {
			container = jQuery('<div id="'+tagid+'"></div>').appendTo(document.body);
		}
		
		// Get loading animation tag, creating it if it does not exist
		var loading = jQuery('#'+tagid+'_loading');
		if (!loading.length) {
			loading = jQuery('<div id="'+tagid+'_loading" class="fc_loading_msg" style="position:absolute; background-color:transparent;">loading...</div>');
			container.prepend(loading);
		}
		
		// Add the iframe
		var iframe;
		if (!no_iframe) {
			iframe = jQuery('<iframe id="'+tagid+'_frame" style="visibility:hidden; width:100%; height:100%; border:0; margin:0; padding:0;" src=""></iframe>');
			container.append(iframe);
			iframe.load(function() {
				// Show retrieved contents and hide the loading animation
				iframe.show().css('visibility', 'visible');
				loading.hide();
				
				// Remove unneeded scroll bar inside iframe document
				iframe.contents().find('body').css({ 'height': 'unset' });
			});
		}
		
		// Contaner CSS depending on usage of iframe
		no_iframe ?
			container.css('overflow', 'scroll') :
			container.css('overflow', 'hidden').css('padding', '0') ;
		
		// Initialize popup container
		container.dialog({
			title: params.title,
			closeOnEscape: (typeof params.closeOnEscape !== 'undefined'  ?  params.closeOnEscape  :  false),
			autoOpen: false,
			width: params.winwidth,
			height: params.winheight,
			modal: (typeof params.modal !== 'undefined'  ?  params.modal  :  true),
			position: [params.winleft, params.wintop],
			dialogClass: params.dialogClass,
			// Load contents (url) when dialog opens
			open: function(ev, ui){
				no_iframe ?
					container.load(url) :
					jQuery('#'+tagid+'_frame').attr('src', url);
				
				// Close on click outside of modal popup
				jQuery('.ui-widget-overlay').bind('click', function(){
					container.dialog('close');
				});
			},
			// Clear contents after dialog closes
			close: function(ev, ui) {
				// Allow parent document to scroll again
				jQuery(document.body).removeClass('fc-no-scroll');
				
				// Remove container / iframe contents (we re-use it)
				no_iframe  ?  container.html('')  :  jQuery('#'+tagid+'_frame').remove();
				
				// Finalize by doing the closing operation
				if (typeof closeFunc === 'function') closeFunc();
				else if (closeFunc == 1) window.location.reload(false);
				else if (closeFunc == 0) ;
				else alert('Unknown action'+closeFunc);
			}
		});
		
		// Open the dialog manually
		var theDialog = container.dialog('open');
		
		// Attach on-load function to the iframe
		if (!no_iframe && loadFunc)
		{
			jQuery('#'+tagid).find('iframe').first().on('load', function(e, data) {
				loadFunc( container );
			});
		}
		
		// Stop scrolling of parent document
		jQuery(document.body).addClass('fc-no-scroll');
		
		// Return a reference of the dialog to the caller
		return theDialog;
	}
	
	// Scroll into view
	function fc_scrollIntoView (elem, smooth)
	{
		var cTop = elem.offset().top;
		var cHeight = elem.outerHeight(true);
		var windowTop = jQuery(window).scrollTop();
		var visibleHeight = jQuery(window).height();
		var top_extra = 90;
		var bottom_extra = 80;
	
		//window.console.log ('cTop: ' + cTop + ' , windowTop: ' + windowTop);
		//window.console.log ('cHeight: ' + cHeight + ' , visibleHeight: ' + visibleHeight);
		if (cTop - top_extra < windowTop) {
			if (smooth) {
				jQuery('html, body').animate({scrollTop: cTop - top_extra}, 400);
			} else {
				jQuery('html, body').scrollTop(cTop - top_extra);
			}
		} else if (cTop + cHeight + bottom_extra > windowTop + visibleHeight) {
			if (smooth) {
				jQuery('html, body').animate({scrollTop: cTop - visibleHeight + cHeight + bottom_extra}, 400);
			} else {
				jQuery('html, body').scrollTop(cTop - visibleHeight + cHeight + bottom_extra);
			}
		}
	}
	
	
	function fc_findFormDependencies(el, toggleParent, toggleParentSelector, field)
	{
		var seton_list = el.data('_seton_list');   // (selector comma list) show elements by selector
		var setoff_list= el.data('_setoff_list');  // (selector comma list) hide elements by selector
		var refsh_list = el.data('_refsh_list');   // (selector comma list) trigger change on elements
		
		var show_list  = el.data('_show_list');    // (classnames comma list) grant  display dependency to elements (container shown  when dependencies are zero)
		var hide_list  = el.data('_hide_list');    // (classnames comma list) revoke display dependency to elements (container hidden when dependencies non-zero)
		var force_list = el.data('_force_list');   // (classnames comma list) show regardless of display dependencies (this is non-persistent -and- element's dependencies are not cleared)
		
		var fcreadonly = el.data('_fcreadonly');   // (JSON format, element name : value) set readonly property of elements, 1:ON , 2:OFF
		var fcconfigs  = el.data('_fcconfigs');    // (JSON format, element name : value) set value of elements
		
		var _d;
		if (!seton_list) {
			seton_list = {};
			seton_list[0] = el.attr('data-seton_list')  ? el.attr('data-seton_list')  : null;
			el.data('_seton_list', seton_list);
		}
		if (!setoff_list) {
			setoff_list = {};
			setoff_list[0] = el.attr('data-setoff_list')  ? el.attr('data-setoff_list')  : null;
			el.data('_setoff_list', setoff_list);
		}
		if (!refsh_list) {
			refsh_list = {};
			refsh_list[0] = el.attr('data-refsh_list')  ? el.attr('data-refsh_list')  : null;
			el.data('_refsh_list', refsh_list);
		}
		
		if (!fcreadonly) {
			var fcreadonly = el.attr('data-fcreadonly');
			if (fcreadonly) {
				fcreadonly = fcreadonly.replace(/\'/g, '"');
				fcreadonly = jQuery.parseJSON(fcreadonly);
			}
			el.data('_fcconfigs', fcconfigs);
		}
		if (!fcconfigs) {
			var fcconfigs = el.attr('data-fcconfigs');
			if (fcconfigs) {
				fcconfigs = fcconfigs.replace(/\'/g, '"');
				fcconfigs = jQuery.parseJSON(fcconfigs);
			}
			el.data('_fcconfigs', fcconfigs);
		}
		
		if (!show_list) {
			show_list = {};
			_d  = el.attr('data-show_list')  ? el.attr('data-show_list').split(',')  : Array();
			for (var i = 0; i<_d.length; i++) show_list[_d[i].trim()] = 1;
			el.data('_show_list', show_list);
		}
		if (!hide_list) {
			hide_list = {};
			_d = el.attr('data-hide_list')  ? el.attr('data-hide_list').split(',')  : Array();
			for (var i = 0; i<_d.length; i++) hide_list[_d[i].trim()] = 1;
			el.data('_hide_list', hide_list);
		}
		if (!force_list) {
			force_list = {};
			_d = el.attr('data-force_list') ? el.attr('data-force_list').split(',') : Array();
			for (var i = 0; i<_d.length; i++) force_list[_d[i].trim()] = 1;
			el.data('_force_list', force_list);
		}
		
		var toBeUpdated = Array();
		var u = 0;
		jQuery.each( force_list, function( cname, val ) {
			if (!fc_dependent_params.hasOwnProperty(cname))  fc_dependent_params[cname] = Array();
			fc_dependent_params[cname][fc_dependent_params[cname].length] = el;
			
			if (val) {
				jQuery('.'+cname).each(function( index ) {
					var c = jQuery(this);
					c.attr('data-fc_forced_display', '1');
					toBeUpdated[u++] = c;
				});
			}
		});
		jQuery.each( hide_list, function( cname, val ) {
			if (!fc_dependent_params.hasOwnProperty(cname))  fc_dependent_params[cname] = Array();
			fc_dependent_params[cname][fc_dependent_params[cname].length] = el;
			
			if (val) {
				jQuery('.'+cname).each(function( index ) {
					var c = jQuery(this);
					var dlist = c.data('fc_depend_list');
					if (!dlist) dlist = {};
					if (dlist.hasOwnProperty(cname)) dlist[cname]++; else dlist[cname]= 1;
					c.data('fc_depend_list', dlist);
					if (c.attr('data-fc_forced_display')!='1') {
						toBeUpdated[u++] = c;
					}
				});
			}
		});
		
		// Display fields / enable input on them ( removeAttr + css ),  force them to update display ( trigger:click ), and to validate new value ( trigger:blur )
		if (fcconfigs) for (var fieldname in fcconfigs) {
			if (fcconfigs.hasOwnProperty(fieldname)) {
				var jf_field = jQuery('#'+'jform_attribs_'+fieldname); // first try 'jform_attribs'
				if (!jf_field.length) {
					jf_field = jQuery('#'+'jform_params_'+fieldname);  // then try 'jform_params'
					if (!jf_field.length) {
						jf_field = jQuery('#'+'jform_'+fieldname);  // then try just 'jform_'
						if (!jf_field.length) continue;
					}
				}
				jf_field = jf_field.first();
				
				if (jf_field.is('fieldset')) {
					jf_field.find('input').removeAttr('disabled').removeAttr('readonly');
					jf_field.find('label').removeAttr('disabled').css('pointer-events', 'auto').css('opacity', '1');
					jf_field.find(':input[value="'+fcconfigs[fieldname]+'"]').next().trigger('click').trigger('blur');
				} else {
					jf_field.removeAttr('disabled').removeAttr('readonly').val(fcconfigs[fieldname]).trigger('click').trigger('blur');
				}
			}
		}
		
		if (fcreadonly) for (var fieldname in fcreadonly) {
			if (fcreadonly.hasOwnProperty(fieldname)) {
				var jf_field = jQuery('#'+'jform_attribs_'+fieldname); // first try 'jform_attribs'
				if (!jf_field.length) {
					jf_field = jQuery('#'+'jform_params_'+fieldname);  // then try 'jform_params'
					if (!jf_field.length) {
						jf_field = jQuery('#'+'jform_'+fieldname);  // then try just 'jform_'
						if (!jf_field.length) continue;
					}
				}
				jf_field = jf_field.first();
				
				if (fcreadonly[fieldname] && !field.hasClass('fccustom_revert')) {
					if (jf_field.is('fieldset')) {
						jf_field.find('input').attr('readonly', 'readonly');
						jf_field.find('label').attr('disabled', true).css('pointer-events', 'none').css('opacity', '0.65');
					} else if (jf_field.is('select')) {
						jf_field.attr('readonly', 'readonly').css('pointer-events', 'none').css('opacity', '0.85');
					} else {
						jf_field.attr('readonly', 'readonly').css('opacity', '0.85')
					}
				} else {
					if (jf_field.is('fieldset')) {
						jf_field.find('input').removeAttr('disabled');
						jf_field.find('label').removeAttr('disabled').css('pointer-events', 'auto').css('opacity', '1');
					}
					else {
						jf_field.removeAttr('readonly').css('pointer-events', 'auto').css('opacity', '1');
					}
				}
			}
		}
		
		jQuery.each( show_list, function( cname, val ) {
			if (!fc_dependent_params.hasOwnProperty(cname))  fc_dependent_params[cname] = Array();
			fc_dependent_params[cname][fc_dependent_params[cname].length] = el;
			
			if (val) {
				jQuery('.'+cname).each(function( index ) {
					var c = jQuery(this);
					var dlist = c.data('fc_depend_list');
					if (!dlist) dlist = {};
					if (dlist.hasOwnProperty(cname)) dlist[cname]--; else dlist[cname]= -1;
					c.data('fc_depend_list', dlist);
					if (c.attr('data-fc_forced_display')!='1') {
						toBeUpdated[u++] = c;
					}
				});
			}
		});
		
		setTimeout(function(){
			jQuery.each( setoff_list, function( i, selector ) {
				if (selector) {
					jQuery(selector).each(function( index ) {
						var c = jQuery(this);
						c.slideUp('fast');
					});
				}
			});
			jQuery.each( seton_list, function( i, selector ) {
				if (selector) {
					jQuery(selector).each(function( index ) {
						var c = jQuery(this);
						c.slideDown('fast');
					});
				}
			});
		}, !fc_init_hide_dependent ? 0 : 50);
		
		
		if (!fc_init_hide_dependent) {
			fc_applyFormDependencies(toBeUpdated, toggleParent, toggleParentSelector, 0);
			
			if (!fc_refreshing_dependent) {
				fc_refreshing_dependent = 1;
				
				// Refresh needed dependencies
				if (typeof refsh_list != 'string') jQuery.each( refsh_list, function( i, selector ) {
					if (selector) {
						jQuery(selector).each(function( index ) {
							var c = jQuery(this);
							if (c.is('select'))
								c.trigger('fcform_run_deps');  // instead of change
							else if (c.is('input[type="radio"]'))
								c.closest('.fcform_toggler_element').find('input[type="radio"]:checked').trigger('fcform_run_deps');  // instead of click
							else if (c.is('fieldset') && c.hasClass('radio'))
								c.find('input[type="radio"]:checked').trigger('fcform_run_deps');  // instead of click
						});
					}
				});
			}
			fc_refreshing_dependent = 0;
		}
		
		// Restore the form to select the element with value ''
		if ( field.hasClass('fccustom_revert') && el.value!='' )
		{
			var currVal;
			if ( field.is('fieldset') ) {
				currVal = field.find('input[type="radio"]:checked').val();
				if (currVal!='') {
					field.find('input').attr('disabled', 'disabled');
					field.find('label').attr('disabled', true).css('pointer-events', 'none').css('opacity', '0.65');
				}
			} else {
				currVal = field.val();
				if (currVal!='') field.attr('disabled', 'disabled').css('pointer-events', 'none').css('opacity', '0.85');
			}
			
			if (currVal!='') setTimeout(function(){
				if (field.is('fieldset')) {
					field.find('input').removeAttr('disabled').removeAttr('readonly');
					field.find('label').removeAttr('disabled').css('pointer-events', 'auto').css('opacity', '1');
					field.find(':input[value=""]').next().trigger('click');
				} else {
					field.removeAttr('disabled').removeAttr('readonly').css('pointer-events', 'auto').css('opacity', '1');
					field.val('').trigger('click');
				}
			}, 200);
		}
		
		return toBeUpdated;
	}
	
	
	// Add toggling of dependent form elements
	function fc_bindFormDependencies(container, toggleParent, toggleParentSelector)
	{
		var vTimeStart = new Date().getTime();
		
		var toBeUpdated_ALL = Array();
		var k = 0;
		
		// Performance concern: use custom event

		// Bind dependencies of select elements
		jQuery(container+' select.fcform_toggler_element').on('change fcform_run_deps', function(e, data) {
			var toBeUpdated = fc_findFormDependencies( jQuery('option:selected', this), toggleParent, toggleParentSelector, jQuery(this) );
			for (var i = 0; i < toBeUpdated.length; i++) {
				toBeUpdated_ALL[k++] = toBeUpdated[i];
			}
		});

		// Bind dependencies of radio elements
		jQuery(container+' .fcform_toggler_element input:radio').on('click fcform_run_deps', function(e, data) {
			var toBeUpdated = fc_findFormDependencies( jQuery(this), toggleParent, toggleParentSelector, jQuery(this).parent('.fcform_toggler_element') );
			for (var i = 0; i < toBeUpdated.length; i++) {
				toBeUpdated_ALL[k++] = toBeUpdated[i];
			}
		});

		// *** Update the form
		jQuery('form').data('skip_validation' ,1);
		
		// Enqueue dependencies by triggering change on select elements
		jQuery('form select.fcform_toggler_element').trigger('fcform_run_deps');
		// Enqueue dependencies by triggering change on radio elements
		jQuery('form .fcform_toggler_element input[type="radio"]:checked').trigger('fcform_run_deps');
		
		//alert(toBeUpdated_ALL.length);
		// Apply form changes according to dependencies
		fc_applyFormDependencies(toBeUpdated_ALL, toggleParent, toggleParentSelector, 1);
		
		// Clear this flag to indicate that form initialization is done
		fc_init_hide_dependent = 0;
		
		jQuery('form').data('skip_validation', null);
		
		/*setTimeout(function(){ }, 20);*/
		if (!!Joomla.fc_debug) window.console.log( 'fc_bindFormDependencies() time: ' + ((new Date())  - vTimeStart) + ' ms');
	}


	/* Attach form validation to inner elements of the given selector */
	function fc_validationAttach(sel)
	{
		document.formvalidator.attachToForm(null, sel);
	}


	/* Attach boostrap styling / behaviour to the inner contents of the given selector */
	function fc_bootstrapAttach(sel)
	{
		// Turn radios into btn-group
		jQuery(sel).find('.radio.btn-group label').addClass('btn');
		
		jQuery(sel).find('fieldset.btn-group').each(function() {
			if (jQuery(this).prop('disabled')) {
				jQuery(this).css('pointer-events', 'none').off('click');
				jQuery(this).find('.btn').addClass('disabled');
			}
		});
		
		jQuery(sel).find('.btn-group label:not(.active)').click(function()
		{
			var label = jQuery(this);
			var input = jQuery('#' + label.attr('for'));
	
			if (!input.prop('checked')) {
				label.closest('.btn-group').find('label').removeClass('active btn-success btn-danger btn-primary');
				if (input.val() == '') {
					label.addClass('active btn-primary');
				} else if (input.val() == 0) {
					label.addClass('active btn-danger');
				} else {
					label.addClass('active btn-success');
				}
				input.prop('checked', true);
				input.trigger('change');
			}
		});
		
		jQuery(sel).find('.btn-group input[checked="checked"]').each(function()
		{
			var input = jQuery(this);
			if (input.val() == '') {
				input.parent().find('label[for="' + input.attr('id') + '"]').addClass('active btn-primary');
			} else if (input.val() == 0) {
				input.parent().find('label[for="' + input.attr('id') + '"]').addClass('active btn-danger');
			} else {
				input.parent().find('label[for="' + input.attr('id') + '"]').addClass('active btn-success');
			}
		});
	}

	
	function fc_applyFormDependencies(toBeUpdated, toggleParent, toggleParentSelector, noFX)
	{
		jQuery.each( toBeUpdated, function( i, val ) {
			var c = jQuery(this);
			var dlist = c.data('fc_depend_list');
			if (!dlist) dlist = {};
			var forced = c.attr('data-fc_forced_display');
			
			jQuery.each(dlist, function( i, val ) { if(val<=0) delete dlist[i]; if(val>=1) dlist[i]=1 ;});
			c.data('fc_depend_list', dlist);
			
			if ( jQuery.isEmptyObject(dlist) || forced=='1' ) {
				!toggleParent ? c.slideDown(noFX ? 0 : 500) :
					(toggleParentSelector ?
						c.parents(toggleParentSelector).slideDown(noFX ? 0 : 500) :
						c.parents().eq(toggleParent).slideDown(noFX ? 0 : 500)
					);
			} else {
				!toggleParent ? c.slideUp(noFX ? 0 : 'fast') :
					(toggleParentSelector ?
						c.parents(toggleParentSelector).slideUp(noFX ? 0 : 'fast') :
						c.parents().eq(toggleParent).slideUp(noFX ? 0 : 'fast')
					);
			}
		});
		
		jQuery.each( toBeUpdated, function( i, val ) {
			var c = jQuery(this);
			c.attr('data-fc_forced_display', '0');
		});
	}
	
	function fcCascadedField_update(elVal, trgID, field_id, item_id, field_type, cascade_prompt, prompt_enabled, valindex)
	{
		var trgEL = jQuery('#'+trgID);
		trgEL.parent().find('.field_cascade_loading').html('<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\" /> ... Loading');
		
		fcCascadedField_clear(trgEL, 'Please wait', prompt_enabled);
		
		jQuery.ajax({
			type: 'POST',
			url: 'index.php?option=com_flexicontent&tmpl=component&format=raw',
			data: {
				lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: ''),
				task: 'call_extfunc',
				omethod: 'html', /* unused */
				exttype: 'plugins',
				extfolder: 'flexicontent_fields',
				extname: field_type,
				extfunc: 'getCascadedField',
				field_id: field_id,
				item_id: item_id,
				valgrps: elVal,
				valindex: valindex
			}
		}).done( function(data) {
			//window.console.log ('Got data for:' + trgEL.attr('id'));
			trgEL.parent().find('.field_cascade_loading').html('');
			data = data.trim();
			
			if (data!='') {
				trgEL.empty().append(data).val('');
				var trgTagName = trgEL.prop("tagName");
				if (fc_cascade_field_funcs.hasOwnProperty(trgID) && trgTagName!='SELECT') {
					//window.console.log ('Reading cascade function for source ID:' + trgID);
					fc_cascade_field_funcs[trgID]();
				}
				
				// Add prettyCheckable to new radio set (if having appropriate CSS class)
				trgEL.find('.use_prettycheckable').each(function() {
					var elem = jQuery(this);
					var lbl = elem.next('label');
					var lbl_html = elem.next('label').html();
					lbl.remove();
					elem.prettyCheckable({
						color: 'blue',
						label: lbl_html
					});
				});
			} else {
				trgEL.empty().append('<option value="" '+(!prompt_enabled ? 'disabled="disabled"' : '')+'>'+cascade_prompt+'</option>');
			}
			trgEL.trigger('change');  // Retrigger change event to update select2 display
		});
	}
	
	function fcCascadedField_clear(el, prompt, prompt_enabled)
	{
		var trgTagName = el.prop("tagName");
		if (trgTagName=='SELECT') {
			el.empty().append('<option value="" '+(!prompt_enabled ? 'disabled="disabled"' : '')+'>'+prompt+'</option>');
			el.trigger('change', [{elementClear:1}]);
		} else {
			el.find('input').first().trigger('change', [{elementClear:1}]);
			el.empty().append('<span class="badge badge-info">'+prompt+'</span>');
		}
	}
	
	function fcCascadedField(field_id, item_id, field_type, srcSelector, trgID, cascade_prompt, prompt_enabled, valindex)
	{
		var onEL  = jQuery(srcSelector);
		var onEL2 = onEL.parent().find('select.use_select2_lib');
		var isSel2 = onEL2.length != 0;
		
		var srcEL = isSel2 ? onEL2 : onEL;
		var trgEL = jQuery('#'+trgID);
		//window.console.log ('fcCascadedField FOR source SELECTOR: ' + srcSelector + ' target ID: ' + trgID + ' , valindex: ' + valindex);
		
		srcEL.on('change', function(e, data){
			var elementClear = (typeof data!== 'undefined' && typeof data.elementClear !== 'undefined') ? data.elementClear : 0;  // workaround for radio, checkbox causing unneeded server call
			var elType = srcEL.attr('type');
			if (elType=='radio' || elType=='checkbox') {
				var elVal = Array();
				srcEL.parent().parent().find('input:checked').each(function( index ) {
					elVal[elVal.length] = jQuery(this).val();
				});
				elVal = elVal.join(',');
			} else {
				var elVal = srcEL.val();
			}
			
			//window.console.log ('CHANGED element ID: ' + srcEL.attr('id') + ' , isCHECKED: ' + srcEL.is(':checked') + ' type: '+srcEL.attr('type'));
			if ( !elementClear && !! elVal ) {
				//window.console.log ('CHANGED element ID: ' + srcEL.attr('id') + ' --> Updating:' + trgEL.attr('id') + ' for VALGROUP: -' + elVal + '-  value of 1st element: ' + srcEL.val() + ' type: '+srcEL.attr('type'));
				fcCascadedField_update(elVal, trgID, field_id, item_id, field_type, cascade_prompt, prompt_enabled, valindex);
			} else {
				//window.console.log ('CHANGED element ID: ' + srcEL.attr('id') + ' --> Clearing:' + trgEL.attr('id') + ' TAG type: ' + trgEL.prop("tagName"));
				fcCascadedField_clear(trgEL, cascade_prompt, prompt_enabled);
			}
		});
	}
	
	
	
	// *** Check that a variable is not null and is defined (has a value)
	function js_isset (variable) {
		if (variable==null) return false;
		if (typeof variable == "undefined") return false;
		return true;
	}
	
	
	//******************************************
	//*** Column hide/show related functions ***
	//******************************************
	
	// *** Hide a column of a table
	function toggle_column(container_div_id, data_tbl_id, col_no, firstrun) {
		// 1. Get column-status array for the table with id: data_tbl_id
		var show_col = eval('show_col_'+data_tbl_id);
	
		// 2. Decide show or hide action and update the displayed number of the hidden columns (if any must be in the element with id: data_tbl_id+'_hidecolnum_box')
		if ( !js_isset(show_col[col_no]) || show_col[col_no] ) {
			var action_func = 'hide';
			show_col[col_no] = 0;
			var hidecol_box = document.getElementById(data_tbl_id+'_hidecolnum_box');
			if ( js_isset(hidecol_box) ) {
				hidecol_box.innerHTML = parseInt(hidecol_box.innerHTML) + 1;
			}
		} else {
			var action_func = 'show';
			show_col[col_no] = 1;
			var hidecol_box = document.getElementById(data_tbl_id+'_hidecolnum_box');
			if ( js_isset(hidecol_box) ) {
				hidecol_box.innerHTML = parseInt(hidecol_box.innerHTML) - 1;
			}
		}
	
		//var ffieldname_label = 'columnchoose_' + data_tbl_id + '_' + col_no + '_label';
		//var ffieldname_label = document.getElementById(ffieldname_label);
		//if ( js_isset(ffieldname_label) ) if (action_func == 'show')  ffieldname_label.style.color='black';  else ffieldname_label.style.color='#00aa00';
	
		// 3. Get table and all its rows
		var tbl  = document.getElementById(data_tbl_id);
		var rows = jQuery(tbl).find('> thead > tr, > tbody > tr');
		var toggle_amount = 1;
	
		// Find 'colspan' start of the column given the -index- 'col_no' of the head cell
		var _col_no = 0;
		var tcells = jQuery(rows[0]).children('td, th');
		for (cell=0; cell<col_no; cell++) {
			if (cell>=tcells.length) break;
			colspan = parseInt(jQuery(tcells[cell]).attr('colspan'));
			if (colspan) {
				_col_no = _col_no + colspan;
			} else {
				_col_no++;
			}
		}
	
		// 4. Iterate through rows toggling the particular column
		for (var row=0; row<rows.length; row++) {
			var cell_cnt, cell;
	
			// Get cell(s) of the current row
			var tcells = jQuery(rows[row]).children('td, th');
	
			// First row is header, we will get 'colspan' of the HEAD CELL, which indicates how many 'toggle_amount' single-colspan columns should be toggled
			if (row==0) {
				toggle_amount = parseInt(jQuery(tcells[col_no]).attr('colspan'));
				if (!toggle_amount) toggle_amount = 1;
			}
	
			// Find where the cell's index in current row, we need this loop since previous cell of row maybe using colspan
			cell_cnt = 0;
			for (cell=0; cell<tcells.length; cell++) {
				var colspan;
				var data_colspan = jQuery(tcells[cell]).attr('data-colspan');
				if (firstrun && !data_colspan) {
					colspan = parseInt(jQuery(tcells[cell]).attr('colspan'));
					if (colspan) {
						jQuery(tcells[cell]).attr('data-colspan', colspan);
					}
				} else {
					colspan = parseInt(jQuery(tcells[cell]).attr('data-colspan'));
				}
	
				if (cell_cnt==_col_no+toggle_amount-1) break;
				var next_cnt = colspan ? cell_cnt + colspan : cell_cnt + 1;
				if (next_cnt > _col_no) break;
				cell_cnt = next_cnt;
			}
	
			// Finally TOGGLE the found cell, we take into account that the given cell may have colspan,
			// if it does have colspan we increase / decrease, a ZERO remaining colspan, will make us hide the cell, and a non-zero makes us display the cell
			var _cell = cell;
			var colspan_remaining = toggle_amount;
			for (cell=_cell; cell<_cell+toggle_amount; cell++) {
				if (cell<tcells.length) {
					var colspan = parseInt(jQuery(tcells[cell]).attr('colspan'));
	
					jQuery(tcells[cell]).removeClass('initiallyHidden');
					if ( colspan ) {
						if ( action_func == 'hide' ) {
							if ( colspan > colspan_remaining ) {
								jQuery(tcells[cell]).attr('colspan', colspan - colspan_remaining);
							} else {
								firstrun ?
								eval('jQuery(tcells[cell]).'+action_func+'()') :
								eval('jQuery(tcells[cell]).'+action_func+'("slow")');
								jQuery(tcells[cell]).addClass('isHidden');
							}
						} else if (!firstrun) {
							if ( !jQuery(tcells[cell]).hasClass('isHidden') ) {
								jQuery(tcells[cell]).attr('colspan', colspan + colspan_remaining);
							} else {
								eval('jQuery(tcells[cell]).'+action_func+'("slow")');
								jQuery(tcells[cell]).removeClass('isHidden');
							}
						}
						colspan_remaining = colspan_remaining - colspan;
					} else {
						firstrun ?
						eval('jQuery(tcells[cell]).'+action_func+'()') :
						eval('jQuery(tcells[cell]).'+action_func+'("slow")');
						colspan_remaining--;
					}
				}
				if (colspan_remaining<1) break; // no more colspan to toggle columns
			}
		}
	
		if (container_div_id) {
			var col_selectors = jQuery('#'+container_div_id+' input');
			var col_selected = new Array();
			var i = 0;
			for (var cnt=0; cnt<col_selectors.length; cnt++) {
				if ( jQuery(col_selectors[cnt]).attr("checked") ) {
					col_selected[i++] = jQuery(col_selectors[cnt]).attr("data-colno");
				}
			}
			var cookieValue = col_selected.join(',');
			var cookieName = 'columnchoose_' + data_tbl_id;
			var nDays = 30;
			fclib_setCookie(cookieName, cookieValue, nDays);
		}
	}


	// *** Create column choosers row for a table. NOTE must have <th> cells at row 0
	function create_column_choosers(container_div_id, data_tbl_id, firstload, start_text, end_text)
	{
		// 1. Get column-status array for the table with id: data_tbl_id
		var show_col = eval('show_col_'+data_tbl_id);
	
		// 2. Get table and its first row and then the 'th' cells in it
		var firstrow  = jQuery("#"+data_tbl_id+" thead tr:first"); //document.getElementById(data_tbl_id);
		var thcells = firstrow.find('th');
	
		// 3. Iterate through the 'th' cells and create column hiders for those having the class 'hideOnDemandClass'
		var str = (typeof start_text != "undefined") ? start_text : '';
		for (var col=0; col<thcells.length;col++) {
			// 4. Skip if not having 'hideOnDemandClass' class
			if (!jQuery(thcells[col]).hasClass('hideOnDemandClass')) continue;
	
			// 5. Get column name
			var column_toggle_lbl = jQuery(thcells[col]).find('.column_toggle_lbl');
			var col_display_name = column_toggle_lbl.length ? column_toggle_lbl.html(): jQuery(thcells[col]).text();
			column_toggle_lbl.remove();
	
			// 6. Show / Hide current column
			if ( ( !firstload && !js_isset(show_col[col]) ) || ( jQuery(thcells[col]).hasClass('initiallyHidden') && !js_isset(show_col[col]) ) ) {
				var checked_str = '';
				var fontcolor_str = 'black';//var fontcolor_str = '#aaaaaa';
				// *** It has value of 0 or not set, set it to 1 for toggle_column() function to hide it
				show_col[col] = 1;
				// *** Call toggle_column()
				toggle_column('', data_tbl_id, col, 1);
			} else {
				var checked_str = 'checked="checked"';
				var fontcolor_str = 'black'; //var fontcolor_str = 'black';
				// *** It has value of 1 or not set, set it to 0 for toggle_column() function to show it
				show_col[col] = 0;
				// *** Call toggle_column()
				toggle_column('', data_tbl_id, col, 1);
			}
	
			// 7. Create column checkbox and append it to str
			var ffieldid   = 'columnchoose_' + data_tbl_id + '_' + col;
			var ffieldname = 'columnchoose_' + data_tbl_id + '[' + col + ']';
			str = str + '<input align="right" id="' + ffieldid + '" name="' + ffieldname + '" type="checkbox" data-colno="' + col + '" ' + checked_str + ' onclick="toggle_column(\''+container_div_id+'\', \''+data_tbl_id+'\', '+col+', 0);">'
			+ '<label id="' + ffieldid + '_label" style="color:'+fontcolor_str+';" for="' + ffieldid + '">' + col_display_name + '</label>';
		}
	
		// 8. Fill in 'column choose box'
		str = '<input type="hidden" name="columnchoose_'+data_tbl_id+'" value="true">' + str + end_text;
		document.getElementById(container_div_id).innerHTML=str;
	}


	/* Set a cookie */
	function fclib_setCookie(cookieName, cookieValue, nDays)
	{
		var today = new Date();
		var expire = new Date();
		var path = "'.JURI::base(true).'";
		if (nDays==null || nDays<0) nDays=0;

		if (nDays) {
			expire.setTime(today.getTime() + 3600000*24*nDays);
			document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path + ";expires="+expire.toGMTString();
		} else {
			document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path;
		}
		//alert(cookieName+"="+escape(cookieValue) + ";path=" + path);
	}


	/* Remove known textarea editors, optionally add a flag to restore editors laters */
	function fc_removeAreaEditors(txtareas, addRestoreClass)
	{
		addRestoreClass = typeof addRestoreClass != 'undefined' ? addRestoreClass : 0; 
		
		// Remove known JS editors
		txtareas.each(function(i, txtarea)
		{
			var areaid = jQuery(txtarea).attr('id');

			var hasTinyMCE = !!(!areaid || typeof tinyMCE === 'undefined' ? false : tinyMCE.get(areaid));
			if (hasTinyMCE) {
				tinyMCE.majorVersion >= 4 ?
					tinymce.remove('#'+areaid) :
					tinymce.EditorManager.execCommand('mceRemoveEditor', false, areaid) ;
				if (addRestoreClass) jQuery(txtarea).addClass('fc_hadTinyMCE');
			}

			var hasCodeMirror = typeof CodeMirror === 'undefined' ? false : jQuery(txtarea).first().next().hasClass('CodeMirror');
			if (hasCodeMirror) {
				jQuery(txtarea).first().next().get(0).CodeMirror.toTextArea();
				if (addRestoreClass) jQuery(txtarea).addClass('fc_hadCodeMirror');
			}
		});
	}


	/* Restore known textarea editors to textareas with respective class */
	function fc_restoreAreaEditors(txtareas)
	{
		fc_attachTinyMCE( txtareas.filter('.fc_hadTinyMCE') );
		fc_attachCodeMirror( txtareas.filter('.fc_hadCodeMirror') );
	}


	/* Attach tinyMCE with default settings */
	function fc_attachTinyMCE(txtareas)
	{
		var theArea, areaID;
		txtareas.each(function(i, txtarea)
		{
			var theArea = jQuery(txtarea);
			var areaID = theArea.attr('id');

			// Remove tinymce classes from the textarea
			theArea.removeClass('mce_editable');

			// Add tinyMCE editor
			tinyMCE.majorVersion >= 4 ?
				tinyMCE.EditorManager.execCommand('mceAddEditor', true, areaID)  :  //{ tinyMCE.init({  mode : 'exact',  elements : areaID });  //tinyMCE.get(areaid).show();  //tinyMCE.editors[areaID].show(); }
				tinyMCE.EditorManager.execCommand('mceAddControl', true, areaID) ;  //{ tinyMCE.execCommand('mceAddControl', false, theArea.attr('id')); }

			// Some cleanups for proper display
			theArea.addClass('mce_editable');
			theArea.parent().children('.mce-container').css('display', '');   // allow editor container to stretch to the full width of its external container
			//tinyMCE.EditorManager.execCommand('mceFocus', true, areaID);
		});

		return txtareas.length==1 ? tinyMCE.get(areaID) : true;
	}


	/* Attach CodeMirror with default settings */
	function fc_attachCodeMirror(txtareas, CMoptions)
	{
		CMoptions = typeof CMoptions!=='undefined' ? CMoptions : {
			mode: 'application/x-httpd-php',
			indentUnit: 2,
			lineNumbers: true,
			matchBrackets: true,
			lineWrapping: true,
			onCursorActivity: function(CM) 
			{
				CM.setLineClass(hlLine, null);
				hlLine = CM.setLineClass(CM.getCursor().line, 'activeline');
			}
		};

		var editor, theArea;
		txtareas.each(function(i, txtarea)
		{
			theArea = jQuery(txtarea);
			theArea.removeClass(); // Remove all classes from the textarea
			editor = CodeMirror.fromTextArea(theArea.get(0), CMoptions);
			editor.refresh();
		});

		return txtareas.length==1 ? editor : true;
	}


	/* Toggle box via a button and set CSS class to indicate that it is open  */
	function fc_toggle_box_via_btn(theBox, btn, btnClass, btnNew, mode)
	{
		var box = typeof theBox=='string' ? jQuery('#'+theBox) : theBox;

		if (btnNew)
		{
			btnNew.show();
			jQuery(btn).hide();
		}

		if (
			(typeof mode!=='undefined' && parseInt(mode)) ||  // use the mode provided
			(typeof mode==='undefined' && box.is(':hidden'))  // if any of the elements collection 'box' is hidden then open them all
		) {
			jQuery(btn).data('fc_noeffect') || jQuery(btn).hasClass('fc_noeffect') ?
				box.show() :
				box.slideDown(400) ;
			jQuery(btn).addClass(btnClass).data('fc_noeffect', null).removeClass('fc_noeffect');
		} else {
			jQuery(btn).data('fc_noeffect') || jQuery(btn).hasClass('fc_noeffect') ?
				box.hide() :
				box.slideUp(400) ;
			jQuery(btn).removeClass(btnClass).data('fc_noeffect', null).removeClass('fc_noeffect');
		}
	}


	/* Disable/enable a check box group e.g. when 'Use Global' option is toggled */
	function fc_toggle_checkbox_group(containerID, useGlobalElement)
	{
		var container = jQuery('#'+containerID);
		var inputs = container.find('input');
		el = jQuery(useGlobalElement);
		if ( el.is(':checked') ) {
			inputs.each(function(index){
				jQuery(this).attr('disabled', true);
			});
		} else {
			inputs.each(function(index){
				jQuery(this).attr('disabled', false);
			});
		}
		el.attr('disabled', false);
	}


	/* Auto-resize the currently open dialog vertically or horizontally */
	function fc_dialog_resize_now(boxWsel, boxHsel, contentSel)
	{
		params = {};
		params = fc_getAutoSizePos(0, 0, params);

		boxWsel = typeof boxWsel != 'undefined' ? boxWsel : '.ui-dialog.fc-autow-dialog';
		boxHsel = typeof boxHsel != 'undefined' ? boxHsel : '.ui-dialog.fc-autoh-dialog';
		contentSel = typeof contentSel != 'undefined' ? contentSel : '.ui-dialog-content';
		
		jQuery(boxWsel).css({ 'left': params.winleft+'px', 'width': params.winwidth+'px' });
		jQuery(boxHsel).css({ 'top': params.wintop+'px', 'height': params.winheight+'px' });
		
		var dialogs = jQuery(boxHsel);
		dialogs.each(function( index )
		{
			var dialog_box = jQuery(this);
			var content_box = dialog_box.find(contentSel);
			var h = dialog_box.height() - content_box.prev().outerHeight(true) - content_box.next().outerHeight(true);
			content_box.css({ 'height': h+'px', 'margin': '0', 'box-sizing': 'border-box' });
			content_box.find('iframe').contents().find('body').css({ 'height': 'unset' });
		});
	}


	/* Debounced function execution by the given 'wait' time, forcing single function execution within the given 'wait' time */
	function fc_debounce_exec(func, wait, immediate) {
		var timeout;
		return function() {
			var context = this, args = arguments;
			var later = function() {
				timeout = null;
				if (!immediate) func.apply(context, args);
			};
			var callNow = immediate && !timeout;
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
			if (callNow) func.apply(context, args);
		};
	}
	
	var fc_dialog_resize = fc_debounce_exec(fc_dialog_resize_now, 200, false);
	
	jQuery(window).resize(function() {
		fc_dialog_resize();
	});

	
	/* Apply select2 JS to targeted elements */
	function fc_attachSelect2(sel)
	{
		sel = typeof sel !== 'undefined' && sel ? sel : 'body';

		if (window.skip_select2_js)
		{
			jQuery(sel).find('select.use_select2_lib')
				//.filter(function(){return !jQuery(this).attr('multiple');})
				.removeClass('use_select2_lib').addClass('fc_isselect fc_ismobile fc_no_js_attach');
			jQuery(sel).find('.fc_mobile_label').show();
			return;
		}


		// Attach select2 to specific to select elements having specific CSS class, for select-multiple show values as togglable checkboxes
		jQuery(sel).find('select.use_select2_lib').each(function()
		{
			var sel_EL = jQuery(this);
			if ( !sel_EL.attr('multiple') )
			{
				sel_EL.select2({
					minimumResultsForSearch: 10
				});
			}
			else
			{
				sel_EL.select2({
					minimumResultsForSearch: 10,
					closeOnSelect : false
				}).addClass('select2_fc_checkboxes');
			}
		});


		// Customization of SELECT2 JS selectors
		jQuery(sel).find('div.use_select2_lib').each(function()
		{
			var el_container = jQuery(this);
			var sel_EL = el_container.next('select');


			// MULTI-SELECT2: Initialize internal labels, placing the label so that it overlaps the text filter box
			var fc_label_text = sel_EL.attr('data-fc_label_text');
			if (!fc_label_text) fc_label_text = sel_EL.attr('fc_label_text');
			if (fc_label_text)
			{
				var _label = (fc_label_text.length >= 30) ? fc_label_text.substring(0, 28) + '...' : fc_label_text;

				jQuery('<span/>', {
					'class': 'fc_has_inner_label fc_has_inner_label_select2',
					'text': _label
				}).prependTo(el_container.find('.select2-search-field'));
			}


			// MULTI-SELECT2: Initialize internal prompts, placing the prompt so that it overlaps the text filter box
			var fc_prompt_text = sel_EL.attr('data-fc_prompt_text');
			if (!fc_prompt_text) fc_prompt_text = sel_EL.attr('fc_prompt_text');
			if (fc_prompt_text)
			{
				var _prompt = (fc_prompt_text.length >= 30) ? fc_prompt_text.substring(0, 28) + '...' : fc_prompt_text;

				jQuery('<span/>', {
					'class': 'fc_has_inner_prompt fc_has_inner_prompt_select2',
					'text': _prompt
				}).prependTo(el_container.find('.select2-search-field')).hide();
			}


			// SINGLE-SELECT2: Highlight selects with an active value
			if ( ! sel_EL.attr('multiple') && !sel_EL.hasClass('fc_skip_highlight') )
			{
				var el = el_container.find('.select2-choice');
				var val = sel_EL.val();
				if (val === null) {
					//el.addClass('fc_highlight_disabled');
				} else if (!!val && val.length) {
					el.addClass('fc_highlight');
				} else {
					el.removeClass('fc_highlight');
				}
			}
		});


		// MULTI-SELECT2:
		jQuery(sel).find('select.use_select2_lib').on('select2-open', function()
		{

			// Add events to handle focusing the text filter box (hide inner label)
			var sel_EL = jQuery(this);
			var el = sel_EL.parent().find('.select2-input');
			var el_label = el.prevAll('.fc_has_inner_label');
			if (el_label) el_label.hide();
			var el_prompt = el.prevAll('.fc_has_inner_prompt');
			if (el_prompt) el_prompt.show();


			// Allow listing already selected options WHEN having class 'select2_fc_checkboxes'
			if (sel_EL.hasClass('select2_fc_checkboxes')) {
				var els = jQuery('#select2-drop').find('.select2-selected');
				els.addClass('select2-selected-visible').removeClass('select2-selected');
				
				// Suppress the onchange attribute while selector is open
				sel_EL.data('onchange', sel_EL.attr('onchange'));
				sel_EL.removeAttr('onchange');
			}


		}).on('select2-close', function()
		{

			// Add events to handle bluring the text filter box (show inner label)
			var sel_EL = jQuery(this);
			var el = sel_EL.parent().find('.select2-input');
			var el_label = el.prevAll('.fc_has_inner_label');
			if (el_label) el_label.show();
			var el_prompt = el.prevAll('.fc_has_inner_prompt');
			if (el_prompt) el_prompt.hide();
			
			// Restore already selected options state
			if (sel_EL.hasClass('select2_fc_checkboxes')) {
				var els = jQuery('#select2-drop').find('.select2-selected-visible');
				els.removeClass('select2-selected-visible').addClass('select2-selected');
			}


			// Restore the onchange attribute when selector closes, running the JS if changes were made
			if ( sel_EL.data('onchange') )
			{
				sel_EL.attr('onchange', sel_EL.data('onchange'));
				sel_EL.data('onchange', null);
			}

			// Trigger change event on close if this was prevented
			if ( sel_EL.data('field_being_edited') )
			{
				sel_EL.data('field_being_edited', null);
				sel_EL.trigger('change');
				if ( sel_EL.attr('onchange') )
				{
					eval(sel_EL.attr('onchange'));
				}
			}


		}).on('select2-selecting', function(e)
		{

			// Handle toggling MULTI-SELECT2 as checkboxes
			var sel_EL = jQuery(this);
			if ( sel_EL.attr('multiple') && sel_EL.hasClass('select2_fc_checkboxes') )
			{
				var vals = sel_EL.val();
				var nVals = new Array();
				var i = 0;
				var found = 0;
				if (vals && vals.length) vals.each(function(v) {
					if (v != e.val) nVals[i++] = v;
					else found = 1;
				});
				if (!found) nVals[i++] = e.val;
				sel_EL.data('set_selected_values', nVals);
				sel_EL.data('field_being_edited', true);
				sel_EL.data('field_being_selected', true);
			}


		}).on('change', function(e)
		{

			// SELECT2: Handle change event
			var sel_EL = jQuery(this);
			sel_EL.data('field_being_selected', null);
			
			if (sel_EL.data('field_being_edited'))
			{
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
			}

			var nVals = sel_EL.data('set_selected_values');
			if (nVals)
			{
				jQuery('#select2-drop').find('.select2-selected-visible, .select2-selected').removeClass('select2-selected-visible').removeClass('select2-selected');
				jQuery('#select2-drop').find('.select2-selected').removeClass('select2-selected-visible').removeClass('select2-selected');
				
				sel_EL.data('set_selected_values', null).select2('val', nVals).trigger('change');
			}


			// SINGLE-SELECT2:
			if ( ! sel_EL.attr('multiple') && !sel_EL.hasClass('fc_skip_highlight') )
			{
				// Handle highlighting selected value
				var el = jQuery(this).prev('div').find('.select2-choice');
				var val = sel_EL.val();
				if (!!val && val.length) {
					el.addClass('fc_highlight');
				} else {
					el.removeClass('fc_highlight');
				}
			}


			// MULTI-SELECT2:
			if ( sel_EL.attr('multiple') )
			{
				// Allow listing already selected options WHEN having class 'select2_fc_checkboxes'
				if (jQuery(this).hasClass('select2_fc_checkboxes')) {
					var els = jQuery('#select2-drop').find('.select2-selected');
					els.addClass('select2-selected-visible').removeClass('select2-selected');//.removeClass('select2-result-selectable');
				}
			}

		});

		
		// MULTI-SELECT2: Handle highlighting selected value
		jQuery(sel).find('div.use_select2_lib.select2-container-multi input').on('keydown', function()
		{
			var el = jQuery(this);
			setTimeout(function() {
				var val = el.val();
				if (!!val && val.length) {
					var el_prompt = el.prevAll('.fc_has_inner_prompt');
					if (el_prompt) el_prompt.hide();
				} else {
					var el_prompt = el.prevAll('.fc_has_inner_prompt');
					if (el_prompt) el_prompt.show();
				}
			}, 0);
		});


		// SELECT2: scrollbar wrap problem
		jQuery(sel).find('select.use_select2_lib').on('loaded open', function()
		{
			var ul = jQuery('#select2-drop ul.select2-results');
			var needsScroll= ul.prop('scrollHeight') > ul.prop('clientHeight');
			if (needsScroll) ul.css('overflow-y', 'scroll');
			else  ul.css('overflow-y', 'auto');
		});
	}


	/* Valid HTML for legacy modals using rel, TODO: add more */
	jQuery(document).ready(function() {
		jQuery(".modal").each(function(i, el) {
			if (jQuery(el).get(0).hasAttribute("data-rel")) jQuery(el).attr("rel", jQuery(el).attr("data-rel"));
		});
	});
