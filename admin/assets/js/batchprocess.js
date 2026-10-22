function toggle_importlang_info() {
	if ( jQuery('#method-usejoomfish:checked').length || jQuery('#method-firstjf-thenauto:checked').length )
		jQuery('#falang-import-info').css('display', 'inline-block');
	else
		jQuery('#falang-import-info').css('display', 'none');
}

function moveonly() {
	jQuery('#row_copy_options').slideUp(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_keepseccats').slideDown(600); jQuery('#row_seccats').slideDown(600);
	jQuery('#seccats').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').trigger('click');
	
	jQuery('#row_keeptags').slideDown(600); jQuery('#row_tags').slideDown(600);
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#keeptags1').removeAttr('disabled'); jQuery('#keeptags2').removeAttr('disabled');
	jQuery('#keeptags2').attr('checked', 'checked'); jQuery('#keeptags2').trigger('click');
	jQuery('#prefix').attr('disabled', 'disabled'); jQuery('#row_prefix').slideUp(600);
	jQuery('#suffix').attr('disabled', 'disabled'); jQuery('#row_suffix').slideUp(600);
	jQuery('#copynr').attr('disabled', 'disabled'); jQuery('#row_copynr').slideUp(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600); jQuery('#state').val('').trigger('change');
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').slideDown(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').slideDown(600);
	toggle_importlang_info();
}
function copymove() {
	jQuery('#row_copy_options').slideDown(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_keepseccats').slideDown(600); jQuery('#row_seccats').slideDown(600);
	jQuery('#seccats').removeAttr('disabled');
	jQuery('#keepseccats0').removeAttr('disabled');
	jQuery('#keepseccats1').removeAttr('disabled');
	jQuery('#keepseccats1').attr('checked', 'checked');
	jQuery('#keepseccats1').trigger('click');
	
	jQuery('#row_keeptags').slideDown(600); jQuery('#row_tags').slideDown(600);
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#keeptags1').removeAttr('disabled'); jQuery('#keeptags2').removeAttr('disabled');
	jQuery('#keeptags2').attr('checked', 'checked'); jQuery('#keeptags2').trigger('click');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').slideDown(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').slideDown(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').slideDown(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600); jQuery('#state').val('0').trigger('change');
	jQuery('#type_id').removeAttr('disabled'); jQuery('#row_type_id').slideDown(600);
	jQuery('#access').removeAttr('disabled'); jQuery('#row_access').slideDown(600);
	toggle_importlang_info();
}
function copyonly() {
	jQuery('#row_copy_options').slideDown(600);
	jQuery('#maincat').removeAttr('disabled'); jQuery('#row_maincat').slideDown(600);
	
	jQuery('#row_seccats').slideUp(600); jQuery('#row_keepseccats').slideUp(600);
	jQuery('#seccats').attr('disabled', 'disabled');
	jQuery('#keepseccats0').attr('disabled', 'disabled');
	jQuery('#keepseccats1').attr('disabled', 'disabled');
	
	jQuery('#row_keeptags').slideDown(600); jQuery('#row_tags').slideDown(600);
	jQuery('#keeptags0').removeAttr('disabled'); jQuery('#keeptags1').removeAttr('disabled'); jQuery('#keeptags2').removeAttr('disabled');
	jQuery('#keeptags2').attr('checked', 'checked'); jQuery('#keeptags2').trigger('click');
	jQuery('#prefix').removeAttr('disabled'); jQuery('#row_prefix').slideDown(600);
	jQuery('#suffix').removeAttr('disabled'); jQuery('#row_suffix').slideDown(600);
	jQuery('#copynr').removeAttr('disabled'); jQuery('#row_copynr').slideDown(600);
	jQuery('#language').removeAttr('disabled'); jQuery('#row_language').slideDown(600);
	jQuery('.lang').removeAttr('disabled');
	jQuery('#state').removeAttr('disabled'); jQuery('#row_state').slideDown(600);  jQuery('#state').val('0').trigger('change');
	jQuery('#type_id').attr('disabled', 'disabled'); jQuery('#row_type_id').slideUp(600);
	jQuery('#access').attr('disabled', 'disabled'); jQuery('#row_access').slideUp(600);
	toggle_importlang_info();
}
function seccats_on() {
	jQuery('#seccats').removeAttr('disabled');
}
function seccats_off() {
	jQuery('#seccats').attr('disabled', 'disabled');
}
function tags_on() {
	jQuery('#tags').removeAttr('disabled');
}
function tags_off() {
	jQuery('#tags').attr('disabled', 'disabled');
	jQuery('#tags').val(null).trigger('change');
}
function tags_update() {
	var keeptags = jQuery('input[name=keeptags]:checked').val();
	if (keeptags === '0') {
		// Remove only - tags field can be disabled or enabled (won't be used anyway)
		jQuery('#tags').removeAttr('disabled');
	} else {
		// Replace or Add - tags field must be enabled
		jQuery('#tags').removeAttr('disabled');
	}
}

jQuery(document).ready(function(){
	var initial_behaviour = jQuery('input[name=initial_behaviour]').val();
	if      (initial_behaviour=='copyonly') copyonly();
	else if (initial_behaviour=='copymove') copymove();
	else if (initial_behaviour=='moveonly') moveonly();
	
	// Initialize tags field based on selected keeptags option
	jQuery('input[name=keeptags]').on('change', tags_update);
	tags_update();

	jQuery('#type_id').change(function() {
		if (jQuery(this).val() != '')
			jQuery('#fc-change-warning').css('display', 'inline-block');
		else
			jQuery('#fc-change-warning').css('display', 'none');
	});
});

/**
 * Batch setting of custom field values
 *
 * Fields are picked from a list, the (native) input of the field is loaded via AJAX inside a card, together with the assets the field needs.
 * Posted as: custom[fieldname] (value) and batch_fields[field_id][mode] (add / replace / fill / clear)
 */
var fc_batch_fields = {
	loaded_scripts: {},

	scriptLoaded: function (src) {
		var clean = src.split('?')[0];
		if (fc_batch_fields.loaded_scripts[clean]) return true;
		var found = false;
		jQuery('script[src]').each(function () {
			if (jQuery(this).attr('src').split('?')[0] === clean) found = true;
		});
		return found;
	},

	loadScripts: function (list, done) {
		var next = function () {
			if (!list.length) return done();
			var item = list.shift();
			var src = typeof item === 'string' ? item : item.src;
			if (!src || fc_batch_fields.scriptLoaded(src)) return next();
			fc_batch_fields.loaded_scripts[src.split('?')[0]] = true;
			var el = document.createElement('script');
			el.src = src;
			if (item.module) el.type = 'module'; else el.async = false;
			el.onload = el.onerror = next;
			document.head.appendChild(el);
		};
		next();
	},

	loadAssets: function (assets, done) {
		// Joomla script options (texts / settings) needed by the scripts of the field
		if (assets.options && window.Joomla && typeof Joomla.loadOptions === 'function' && Object.keys(assets.options).length) {
			Joomla.loadOptions(assets.options);
		}
		(assets.styles || []).forEach(function (href) {
			if (!jQuery('link[rel=stylesheet]').filter(function () { return jQuery(this).attr('href').split('?')[0] === href.split('?')[0]; }).length) {
				jQuery('<link rel="stylesheet">').attr('href', href).appendTo('head');
			}
		});
		(assets.style_inline || []).forEach(function (css) {
			jQuery('<style>').text(css).appendTo('head');
		});
		fc_batch_fields.loadScripts((assets.scripts || []).slice(), done);
	},

	addField: function (field_id, label, multiple) {
		var container = jQuery('#batch_field_cards');
		if (container.find('.batch-field-card[data-field-id=' + field_id + ']').length) return;

		var card = jQuery(document.getElementById('batch_field_card_tmpl').content.firstElementChild.cloneNode(true));
		card.attr('data-field-id', field_id).addClass('fc-loading');
		card.find('.batch-field-label').text(label);
		card.find('.batch-field-mode').attr('name', 'batch_fields[' + field_id + '][mode]').removeAttr('disabled');
		// A single value field has nothing to add to
		if (!multiple) card.find('.batch-field-mode option[value=add]').remove();
		card.find('.batch-field-remove').on('click', function () {
			card.remove();
			jQuery('#batch_field_picker option[value=' + field_id + ']').prop('disabled', false);
			jQuery('#batch_field_picker').trigger('change.select2');
		});
		container.append(card);

		jQuery.ajax({
			url: container.data('url'),
			data: {field_id: field_id},
			dataType: 'json'
		}).done(function (resp) {
			if (!resp || resp.error) {
				card.find('.batch-field-body').html('<div class="alert alert-warning">' + (resp && resp.error ? resp.error : 'Error') + '</div>');
				card.find('.batch-field-mode').attr('disabled', 'disabled');
				return;
			}
			fc_batch_fields.loadAssets(resp.assets || {}, function () {
				// jQuery executes the inline scripts contained in the HTML, then run the inline scripts added to the document by the field
				card.find('.batch-field-body').html(resp.html);
				(resp.assets.inline || []).forEach(function (code) {
					if (typeof code !== 'string' || !code.trim()) return;
					try { jQuery.globalEval(code); } catch (e) { if (window.console) console.warn('Batch field script failed', e); }
				});
				fc_batch_fields.stripValidation(card);
				// Calendars inserted after page load must be initialized
				if (typeof JoomlaCalendar === 'function') {
					card.find('.field-calendar').each(function () {
						try { JoomlaCalendar.init(this); } catch (e) { if (window.console) console.warn('Calendar init failed', e); }
					});
				}
				card.removeClass('fc-loading');

				// Show multi-selects as tags (no checkbox list), then attach the select library (select2 / choices.js) via the component's own initializer
				card.find('select[multiple].use_select2_lib').addClass('fc_select2_no_check');
				if (typeof fc_initSelect2 === 'function') fc_initSelect2(null, card);
			});
		}).fail(function () {
			card.find('.batch-field-body').html('<div class="alert alert-warning">AJAX error</div>');
			card.find('.batch-field-mode').attr('disabled', 'disabled');
		});
	},

	// A value is optional in batch (an empty card sets nothing), so remove the 'required' validation that the field adds for the item form
	stripValidation: function (card) {
		// Keep the format validators (validate-email, validate-url, validate-numeric ...), remove those that require a value or limit the number of values
		var drop = function (c) {
			return c === 'required' || c === 'required_boxed' || c === 'required_box' || c === 'invalid'
				|| c === 'validate-radio' || c === 'validate-checkbox' || c === 'validate-cboxlimitations' || c === 'validate-sellimitations';
		};
		card.find('*').each(function () {
			if (this.className && typeof this.className === 'string') {
				this.className = this.className.split(/\s+/).filter(function (c) { return c && !drop(c); }).join(' ');
			}
			this.removeAttribute('required');
			this.removeAttribute('aria-required');
			this.removeAttribute('aria-invalid');
		});
	},

	// Clear mode needs no value, hide the input
	toggleMode: function (select) {
		var card = jQuery(select).closest('.batch-field-card');
		card.find('.batch-field-body').toggle(jQuery(select).val() !== 'clear');
	}
};

jQuery(document).ready(function () {
	jQuery('#batch_field_picker').on('change', function () {
		var opt = jQuery(this).find('option:selected');
		var field_id = parseInt(opt.val(), 10);
		if (!field_id) return;
		fc_batch_fields.addField(field_id, jQuery.trim(opt.text()).replace(/\s{2,}\(.*\)$/, ''), opt.data('multiple') == 1);
		opt.prop('disabled', true);
		jQuery(this).val('').trigger('change.select2');
	});
	jQuery('#batch_field_cards').on('change', '.batch-field-mode', function () {
		fc_batch_fields.toggleMode(this);
	});
});
