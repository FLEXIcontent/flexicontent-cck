	
	window.fc_init_hide_dependent = 1;
	window.fc_refreshing_dependent = 0;
	window.fc_dependent_params = {};
	window.fc_cascade_field_funcs = {};

	function fc_loadImagePreview(input_id, img_id, msg_id, thumb_w, thumb_h, nonimg_mssg)
	{
		var nonimg_mssg = typeof nonimg_mssg !== 'undefined' ? nonimg_mssg : '';
		var input = document.getElementById(input_id);
		var input_files = input.files;
		if (input_files && input_files[0]) {
			var imageType = /image.*/;
			if (!input_files[0].type.match(imageType)) {
		  	if (nonimg_mssg=='-1') ;
		  	else document.getElementById(msg_id).innerHTML = (nonimg_mssg!='' ? nonimg_mssg : Joomla.JText._('FLEXI_NOT_AN_IMAGE_FILE'));
		  	jQuery('#'+img_id).hide();
		  } else {
		  	document.getElementById(msg_id).innerHTML = '';
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
	}
	
	// Load given URL in an open dialog
	function fc_showDialog(url, tagid, no_iframe, winwidth, winheight)
	{
		// Initialize popup container
		var winwidth  = typeof winwidth !== 'undefined' && winwidth ? winwidth : jQuery( window ).width() - 80;
		var winheight = typeof winheight!== 'undefined' && winheight ? winheight : jQuery( window ).height() - 100;
		winwidth  = winwidth  > (jQuery( window ).width() - 80)   ? (jQuery( window ).width() - 80)   : winwidth;
		winheight = winheight > (jQuery( window ).height() - 100) ? (jQuery( window ).height() - 100) : winheight;
		//window.console.log ('winwidth  : ' + winwidth  + ', winheight : ' + winheight );

		var winleft = (jQuery( window ).width() - winwidth) / 2;
		var wintop  = (jQuery( window ).height() - winheight) / 2 - 5;
		//window.console.log ('winleft : ' + winleft + ', wintop : ' + wintop);
		
		// Get container creating it if it does not exist
		var container = jQuery('#'+tagid);
		if (!container.length) {
			container = jQuery('<div id="'+tagid+'"></div>').appendTo(document.body);
		}
		
		// Add loading animation
		var loading = jQuery('<div id="'+tagid+'_loading" class="fc_loading_msg" style="position:absolute; background-color:transparent;">loading...</div>');
		container.prepend(loading);
		
		// Add the iframe
		var iframe;
		if (!no_iframe) {
			iframe = jQuery('<iframe id="'+tagid+'_frame" style="visibility:hidden; width:100%; height:100%; border:0; margin:0; padding:0;" src=""></iframe>');
			container.append(iframe);
			iframe.load(function() {
				iframe.show().css('visibility', 'visible');
				loading.hide();
			});
		}
		
		// Contaner CSS depending on usage of iframe
		no_iframe ?
			container.css('overflow', 'scroll') :
			container.css('overflow', 'hidden').css('padding', '0') ;
		
		container.dialog({
			autoOpen: false,
			width: winwidth,
			height: winheight,
			modal: true,
			position: [winleft, wintop],
			// Load contents (url) when dialog opens
			open: function(ev, ui){
				no_iframe ?
					container.load(url) :
					jQuery('#'+tagid+'_frame').attr('src', url);
			},
			// Clear contents after dialog closes
			close: function(ev, ui){
				no_iframe ?
					container.html('') :
					jQuery('#'+tagid+'_frame').remove();
			}
		});
		
		// Open the dialog and return a reference of the dialog to the caller
		var theDialog = container.dialog('open');
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
	
	
	function toggleDepentParams(el, toggleParent, toggleParentSelector)
	{
		var seton_list = el.data('seton_list');
		var setoff_list = el.data('setoff_list');
		var show_list = el.data('show_list');
		var hide_list = el.data('hide_list');
		var force_list = el.data('force_list');
		var refsh_list = el.data('refsh_list');
		
		var _d;
		if (!seton_list) {
			seton_list = {};
			seton_list[0] = el.attr('seton_list')  ? el.attr('seton_list')  : null;
			el.data('seton_list', seton_list);
		}
		if (!setoff_list) {
			setoff_list = {};
			setoff_list[0] = el.attr('setoff_list')  ? el.attr('setoff_list')  : null;
			el.data('setoff_list', setoff_list);
		}
		if (!show_list) {
			_d  = el.attr('show_list')  ? el.attr('show_list').split(',')  : Array();
			show_list = {};
			for (var i = 0; i<_d.length; i++) show_list[_d[i]] = 1;
			el.data('show_list', show_list);
		}
		if (!hide_list) {
			_d = el.attr('hide_list')  ? el.attr('hide_list').split(',')  : Array();
			hide_list = {};
			for (var i = 0; i<_d.length; i++) hide_list[_d[i]] = 1;
			el.data('hide_list', hide_list);
		}
		if (!force_list) {
			_d = el.attr('force_list') ? el.attr('force_list').split(',') : Array();
			force_list = {};
			for (var i = 0; i<_d.length; i++) force_list[_d[i]] = 1;
			el.data('force_list', force_list);
		}
		if (!refsh_list) {
			_d = el.attr('refsh_list') ? el.attr('refsh_list').split(',') : Array();
			refsh_list = {};
			for (var i = 0; i<_d.length; i++) refsh_list[_d[i]] = 1;
			el.data('refsh_list', refsh_list);
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
			var noFX = fc_refreshing_dependent ? 1 : 0;
			fc_applyDependencies(toBeUpdated, toggleParent, toggleParentSelector, 0);
			
			if (!fc_refreshing_dependent) {
				fc_refreshing_dependent = 1;
				
				// Refresh needed dependencies
				if (typeof refsh_list != 'string') jQuery.each( refsh_list, function( cname, val ) {
					jQuery.each( fc_dependent_params[cname], function( index, elem ) {
						if (elem.is('select'))
							elem.trigger('change');
						else if (elem.is('input[type="radio"]'))
							elem.closest('.fcform_toggler_element').find('input[type="radio"]:checked').trigger('click');
					});
				});
			}
			fc_refreshing_dependent = 0;
		}
		return toBeUpdated;
	}
	
	
	// Add toggling of dependent form elements
	function fc_bind_form_togglers(container, toggleParent, toggleParentSelector)
	{
		var toBeUpdated_ALL = Array();
		var k = 0;
		
		// Bind select elements
		jQuery(container+' select.fcform_toggler_element').change(function() {
			var toBeUpdated = toggleDepentParams( jQuery('option:selected', this), toggleParent, toggleParentSelector );
			for (var i = 0; i < toBeUpdated.length; i++) {
				toBeUpdated_ALL[k++] = toBeUpdated[i];
			}
		});
		
		// Bind radio elements
		jQuery(document).on('click', container+' .fcform_toggler_element input:radio', function(event) {
			var toBeUpdated = toggleDepentParams( jQuery(this), toggleParent, toggleParentSelector );
			for (var i = 0; i < toBeUpdated.length; i++) {
				toBeUpdated_ALL[k++] = toBeUpdated[i];
			}
		});
		
		// Update the form
		jQuery('form select.fcform_toggler_element').trigger('change');
		jQuery('form .fcform_toggler_element input[type="radio"]:checked').trigger('click');
		
		//alert(toBeUpdated_ALL.length);
		fc_applyDependencies(toBeUpdated_ALL, toggleParent, toggleParentSelector, 1);
		fc_init_hide_dependent = 0;
		
		/*setTimeout(function(){ }, 20);*/
	}
	
	function fc_applyDependencies(toBeUpdated, toggleParent, toggleParentSelector, noFX)
	{
		jQuery.each( toBeUpdated, function( i, val ) {
			var c = jQuery(this);
			var dlist = c.data('fc_depend_list');
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
					//window.console.log ('Readding cascade function for source ID:' + trgID);
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
