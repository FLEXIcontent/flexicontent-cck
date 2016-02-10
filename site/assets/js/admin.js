/**
 * @version 1.5 stable $Id: admin.js 183 2009-11-18 10:30:48Z vistamedia $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */


/**
* Check the form is valid and if true, submit it (overload the joomla.javascript.js one)
*/
function fc_submit_form(form, task, validate) {
	var jform = jQuery(form);
	var doserialized = jform.attr('data-fc_doserialized_submit');
	
	var match_apply_ajax = new RegExp(/(.|^)apply_ajax$/);  // Do AJAX submit only for 'apply_ajax' button
	var match_apply      = new RegExp(/(.|^)apply$/);  // Do AJAX submit only for 'apply_ajax' button
	var doajax = match_apply_ajax.test(task)  ||  (match_apply.test(task) && jform.attr('data-fc_doajax_submit'));
	
	var sdata_id = 'fcdata_serialized';
	
	var max_input_vars = (typeof fc_max_input_vars !== 'undefined' && typeof fc_max_input_vars !== 'undefined') ? fc_max_input_vars : 1000;  
	
	var form_fields_active = !doserialized ? 0 :   // Get form's active elements
		jform.find('textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled');
	var doserialized = form_fields_active.length > max_input_vars; // Do serialization if estimated form variable count higher than max_input_vars
	
	if (doserialized) {
		var sdata = jQuery('#'+sdata_id);
		if (!sdata.length) {
			jform.append('<input type="hidden" name="'+sdata_id+'" id="'+sdata_id+'" value="" />');
			sdata = jQuery('#'+sdata_id);
			
			jform.append('<input type="hidden" name="'+sdata_id+'_count" id="'+sdata_id+'_count" value="" />');
			sdata_count = jQuery('#'+sdata_id+'_count');
		}
		sdata_count.val( form_fields_active.length );
		sdata.val( JSON.stringify( jform.serializeArray() ) );  //sdata.val( jform.serialize() );
		
		// Disable all form fields, only serialized field will be submitted
		form_fields_active.each(function() {
			var el = jQuery(this);
			el.attr('data-fcs_disabled', el.attr('disabled'));
			el.attr('disabled', true);
		});
		
		sdata.attr('disabled', false);
	}
	
	// Trigger submit events
	//var start = new Date().getTime();
	jform.trigger('submit');
	//alert('onSubmit event execution time: ' + (new Date().getTime() - start));
	
	if (doajax) {
		jform.after('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... Saving</span>');
		jform.hide();
		jform.append('<input type="hidden" name="fc_doajax_submit" id="fc_doajax_submit" value="1" />');
		jQuery.ajax({
			type: form.method,
			url: form.action,
			data: jform.serialize(),
			success: function (data) {
				jQuery('#fc_doajax_loading').remove();
				jQuery('#fc_doajax_submit').remove();
				jform.show();
				jQuery('#system-message-container').html(data);
				if (doserialized) {
					sdata.val( '' );
					form_fields_active.each(function() {
						var el = jQuery(this);
						el.attr('data-fcs_disabled')  ?  el.attr('disabled', el.attr('data-fcs_disabled'))  :  el.removeAttr('disabled');
					});
				}
			}
		});
	} else {
		form.submit();
	}
}


function fc_admin_progress(percent, element) {
	var progressBarWidth = percent * element.width() / 100;
	element.find('div').animate({ width: progressBarWidth }, 5000).html("");
}


// Overload Joomla submit function
Joomla.submitform = function(task, form, validate)
{
	if (!form) {
		form = document.getElementById('adminForm');
	}
	form.noValidate = !!validate;
	
	// Do form validation if button task is not 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(task);
	if ( document.formvalidator && !isCancel )
	{
		var start = new Date().getTime();
		var isValid = document.formvalidator.isValid(form);
		//alert('Form validation time: ' + (new Date().getTime() - start));
		
		if (!isValid) // If form is invalid, then focus the first invalid element
		{
			var invalid = jQuery('.invalid').first();  // Get single element so that hidden check will work
			if (invalid.is(':hidden')) {
				invalid = jQuery('#system-message-container');
			}
			
			if (invalid.length) {
				var pos = invalid.offset().top - 80;
				jQuery('html, body').animate({
					scrollTop: pos
				}, 1000);
				invalid[0].focus();
			}
			return;
		}
	}
	if (task) form.task.value=task;    // Set form's TASK field
	
	// Submit progress bar
	if ( !isCancel )
	{
		jQuery('body').prepend(
		 	'<span id="fc_filter_form_blocker">' +
		    '<span class="fc_blocker_opacity"></span>' +
		    '<span class="fc_blocker_content">' +
		    	Joomla.JText._('FLEXI_FORM_IS_BEING_SUBMITTED') +
		    	'<div class="fc_blocker_bar"><div></div></div>' +
		    '</span>' +
		  '</span>');
		var fc_filter_form_blocker = jQuery("#fc_filter_form_blocker");
		if (fc_filter_form_blocker) {
			fc_filter_form_blocker.css("display", "block");
			fc_admin_progress(95, jQuery('#fc_filter_form_blocker .fc_blocker_bar'));
		}
	}
	
	setTimeout(function(){
		fc_submit_form(form, task); // Submit the form
	}, 50);
}
