/**
* Check the form is valid and if true, submit it (overload the joomla.javascript.js one)
*/
function fc_submit_form(form, task, validate)
{
	var $form = jQuery(form);
	
	// ***
	// *** Do task tests
	// ***

	// Check task is 'apply_ajax' / 'apply'
	var match_apply_ajax = new RegExp(/(.|^)apply_ajax$/);  // Do AJAX submit if 'apply_ajax' task
	var match_apply      = new RegExp(/(.|^)apply$/);  // Do AJAX submit if 'apply' task and form has ATTRIBUTE: 'data-fc_force_apply_ajax'

	// Check task is 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(task);


	// Pass (enable/disable) FLAG to the AJAX submit handler
	form.doajax_submit = match_apply_ajax.test(task)  ||  (match_apply.test(task) && !!form.getAttribute('data-fc_force_apply_ajax'));

	// Pass (enable/disable) FLAG to the SERIALIZED submit handler
	form.doserialized_submit = !form.hasAttribute('data-doserialized_submit') || form.getAttribute('data-fc_doserialized_submit');

	// Pass (enable/disable) FLAG to the ANIMATED submit handler (progress bar)
	form.doanimated_submit = !isCancel;


	// ***
	// *** SERIALIZED SUBMIT HANDLER
	// ***

	// Declare serialization related variables in current function scope, outside the submit handler
	var sinfo = {}, sdata, sdata_count,
		sdata_id = 'fcdata_serialized';

	if (typeof form.serialized_submit_handler_added === 'undefined' || !form.serialized_submit_handler_added)
	{
		form.serialized_submit_handler_added = true;

		form.addEventListener('submit', function(e)
		{
			if (!form.doserialized_submit)
			{
				return true;
			}
			form.doserialized_submit = 0;  // Clear FLAG until next time submit form method is called

			// These should have been removed already by cleanup process, but make sure that any previously injected fields are removed
			sdata = document.getElementById(sdata_id);
			sdata_count = document.getElementById(sdata_id);
			if (!!sdata) sdata.parentNode.removeChild(sdata);
			if (!!sdata_count) sdata_count.parentNode.removeChild(sdata_count);
			
			// Get max input vars limitation
			max_input_vars = typeof Joomla.fc_max_input_vars !== 'undefined' ? Joomla.fc_max_input_vars : 1000;
			
			// Abort serialization if number of form elements is below limitation
			if (form.elements.length < max_input_vars)
			{
				return true;
			}

			// Get serialized data and also get form's active elements
			var serialized_form_data = JSON.stringify( Joomla.serializeForm(form, sinfo) );

			//var serialized_form_data = JSON.stringify( jQuery(form).serializeArray() );
			//sinfo.fields_active = jQuery(form).find('textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled').toArray();
			//sinfo.fields_active = jQuery(form.elements).filter( 'textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled' );

			// Abort serialization if (estimated) active form elements count is lower than max_input_vars
			if (sinfo.fields_active.length < max_input_vars)
			{
				return true;
			}

			// Add form field that will hold the serialized form data
			sdata = document.createElement('input');
			sdata.type = 'hidden';
			sdata.name = sdata_id;
			sdata.value = serialized_form_data;
			form.appendChild(sdata);

			// Add form field that will hold the serialized form data counter
			sdata_count = document.createElement('input');
			sdata_count.type = 'hidden';
			sdata_count.name = sdata_id + '_count';
			sdata_count.value = sinfo.fields_active.length;
			form.appendChild(sdata_count);
				
			// Disable the form fields that were serialized, so that they will not be submitted (form fields of type 'file' are not included, and will be submitted normally)
			var field, field_type;
			for ( var i = 0, l = sinfo.fields_active.length; i < l; i++ )
			{
				// Get field type / tag name
				field = sinfo.fields_active[i];
				field_type = field.type || field.tagName.toLowerCase();
				
				// Only disable the enabled fields that were serialized, input-file was not serialized
				if (field_type!='file')
				{
					field.setAttribute('disabled', 'disabled');
				}
			};
			
			// Set FLAG for AJAX submit handler to restore the form
			form.form_was_serialized = 1;
		});
	}


	// ***
	// *** AJAXIFIED SUBMIT HANDLER
	// ***

	if (typeof form.ajaxified_submit_handler_added === 'undefined' || !form.ajaxified_submit_handler_added)
	{
		form.ajaxified_submit_handler_added = true;

		form.addEventListener('submit', function(e)
		{
			// This will not be reached if
			// - either HTML5 validation fails,
			// - or any previous submit handler has called preventDefault()
			
			if (!form.doajax_submit)
			{
				return true;
			}
			form.doajax_submit = 0;  // Clear FLAG until next time Joomla.submitform() is called
			
			// Do not use AJAX if form has an enabled and non-empty file input
			var file_inputs = $form.find('input[type="file"]:enabled');
			if ( file_inputs.length )
			{
				var has_selected_file = false;
				file_inputs.each(function()
				{
					has_selected_file = has_selected_file || jQuery(this).val() != '';
					if (has_selected_file) return false;  // stop .each 
				});
				
				if (has_selected_file)
				{
					return true;
				}
			}
			
			// CHECKs are done, do the AJAX submit, but first prevent the normal browser submit from continuing further
			e.preventDefault();
			
			$form.after('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... Saving</span>');
			$form.hide();
			$form.append('<input type="hidden" name="fc_doajax_submit" id="fc_doajax_submit" value="1" />');
			jQuery.ajax({
				type: form.method,
				url: form.action,
				data: $form.serialize(),
				success: function (data)
				{
					jQuery('#fc_doajax_loading').remove();
					jQuery('#fc_doajax_submit').remove();
					jQuery('#fc_filter_form_blocker').remove();

					if ($form.data('btn_box')) $form.data('btn_box').show();
					if ($form.data('msg_box')) $form.data('msg_box').hide();
					$form.show();

					jQuery('#system-message-container').html(data);
					if ( typeof form.form_was_serialized !== 'undefined' && form.form_was_serialized )
					{
						// Remove serialization data field and active elements counter field
						sdata.parentNode.removeChild(sdata);
						sdata_count.parentNode.removeChild(sdata_count);

						// Restore any modified form elements
						for ( var i = 0, l = sinfo.fields_active.length; i < l; i++ )
						{
							sinfo.fields_active[i].removeAttribute('disabled');
						};

						// Clear "form was serialized" FLAG
						form.form_was_serialized = 0;
					}
				}
			});
			return false;  // Indicate that nothing more should be done ... in case anyone is listening after us
		});
	}


	// ***
	// *** Submit progress bar
	// ***

	if (typeof form.progressbar_submit_handler_success === 'undefined' || !form.progressbar_submit_handler_success)
	{
		form.progressbar_submit_handler_success = true;

		form.addEventListener('submit', function(e)
		{
			if ( !form.doanimated_submit )
			{
				return true;
			}
			form.doanimated_submit = 0;  // Clear FLAG until next time Joomla.submitform() is called

			jQuery('body').prepend(
				'<div id="fc_filter_form_blocker">' +
					'<div class="fc_blocker_opacity"></div>' +
					'<div class="fc_blocker_content">' +
						Joomla.JText._('FLEXI_FORM_IS_BEING_SUBMITTED') +
						'<div class="fc_blocker_bar"><div></div></div>' +
					'</div>' +
				'</div>');
			var fc_filter_form_blocker = jQuery("#fc_filter_form_blocker");
			if (fc_filter_form_blocker) {
				fc_filter_form_blocker.css("display", "block");
				fc_admin_progress(95, jQuery('#fc_filter_form_blocker .fc_blocker_bar'));
			}
		});
	}
	
	// Submit the form, triggering submit handlers properly: Create an input type="submit" and click it
	var button = document.createElement('input');
	button.style.display = 'none';
	button.type = 'submit';
	form.appendChild(button).click();
	
	// If "submit" was prevented (e.g. due HTML5 validation or due to AJAX submit), make sure we don't get a build up of submit buttons
	form.removeChild(button);
}


function fc_admin_progress(percent, element)
{
	var progressBarWidth = percent * element.width() / 100;
	element.find('div').animate({ width: progressBarWidth }, 5000).html("");
}


// Overload Joomla submit function
Joomla.submitform = function(task, form, validate)
{
	if (!form) {
		form = document.getElementById('adminForm');
	}
	var task_field_exists = typeof form.task !== 'undefined' && typeof form.task.value !== 'undefined';
	var form_task = task ? task : (task_field_exists  ?  form.task.value  :  '');

	// Do not do add ajax submit or form validation if changing record type
	var match_apply_ajax = new RegExp(/(.|^)apply_ajax$/);
	var modifying_record_type = jQuery('#fc-change-warning').is(':visible');
	form_task = match_apply_ajax.test(form_task) && modifying_record_type ? form_task.replace('apply_ajax', 'apply') : form_task;

	// Do form validation if button task is not 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(form_task);

	// For flexicontent views we will do validation too (FLAG: fc_validateOnSubmitForm), NOTE: for non-FC views this is done before the method is called
	var doValidation = !modifying_record_type && typeof window.fc_validateOnSubmitForm !== undefined ? window.fc_validateOnSubmitForm : 0;
	if ( doValidation && document.formvalidator && !isCancel )
	{
		var isValid = document.formvalidator.isValid(form);
		if (!isValid)
		{
			// Form is not invalid, focus the first invalid element, or focus the all errors container
			var invalid = jQuery('.invalid').first();  // Get single element so that hidden check will work

			setTimeout(function()   // This works without the timeout too ...
			{
				if (invalid.is(':hidden'))
				{
					invalid = jQuery('#system-message-container');
				}
				if (invalid.length)
				{
					var pos = invalid.offset().top - 80;
					jQuery('html, body').animate({
						scrollTop: pos
					}, 400);
					invalid[0].focus();
				}
			}, 20);
			return false;
		}
	}

	// Modify form 's task field after JS validation has run
	if (form_task && task_field_exists)
	{
		form.task.value = form_task;
	}

	// Suppress validateForm() of htm5fallback.js
	if ( form.H5Form ) {
		form.H5Form.donotValidate = true;
	}

	// HTML5 VALIDATION: Suppress if "*.cancel" or "cancel" tasks
	// (*) we force it to OFF ... currently we will not use it all
	if (1 || isCancel)
		form.setAttribute('novalidate', 'novalidate');

	// HTML5 VALIDATION: do according to form original code
	else if (typeof validate === 'undefined' || validate === null)
		;

	// HTML5 VALIDATION: Force OFF / ON
	else !validate ?
		form.setAttribute('novalidate', 'novalidate') :
		form.removeAttribute('novalidate') ;

	fc_submit_form(form, form_task); // Submit the form
}


Joomla.serializeForm = function (form, result)
{
	var fields_active = [];
	var field, field_type, arr = [];
	var len = form.elements.length;

	for (i = 0; i < len; i++)
	{
		field = form.elements[i];
		field_type = field.type || field.tagName.toLowerCase();

		var submitable = field.name && !field.disabled && field_type != 'file' && field_type != 'reset' && field_type != 'submit' && field_type != 'button';
		if (!submitable)
		{
			continue;
		}
		fields_active[fields_active.length] = field;

		if (field_type == 'select-multiple')
		{
			var ops_len = form.elements[i].options.length;
			for (j = 0; j < ops_len; j++)
			{
				if (field.options[j].selected)
				{
					arr[arr.length] = { name: field.name, value: field.options[j].value };
				}
			}
		}

		else if (field_type != 'checkbox' && field_type != 'radio')
		{
			arr[arr.length] = { name: field.name, value: field.value };
		}

		else if (field.checked)
		{
			arr[arr.length] = { name: field.name, value: field.value.length ? field.value : (field_type == 'checkbox' ? 'on' : '') };
		}
	}
	
	// Set active fields into the result object
	result.fields_active = fields_active;

	return arr;
}