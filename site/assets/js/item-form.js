/** Protect unsaved FLEXIcontent item values, including rich-text editors. */
(function () {
	'use strict';

	function init() {
		var form = document.getElementById('adminForm');
		if (!form || form.dataset.fcLeaveGuard) return;
		form.dataset.fcLeaveGuard = '1';
		var interacted = false;
		var leaving = false;
		var submitted = null;

		function snapshot() {
			var values = [];
			var editors = Object.create(null);
			// Reading a rich editor can synchronize its textarea. Read it before
			// FormData so the same edit is not compared in two different states.
			form.querySelectorAll('textarea[id]').forEach(function (field) {
				var editor = window.Joomla && Joomla.editors && Joomla.editors.instances[field.id];
				try {
					if (editor && typeof editor.getValue === 'function') editors[field.name] = editor.getValue();
				} catch (error) { /* Use the textarea while its editor is starting. */ }
			});
			new FormData(form).forEach(function (value, name) {
				if (/^[a-f0-9]{32}$/i.test(name) || /^(task|fc_doajax_submit|fcdata_serialized(?:_count)?)$/.test(name)) return;
				if (Object.prototype.hasOwnProperty.call(editors, name)) value = editors[name];
				if (value instanceof File) value = value.name ? [value.name, value.size, value.lastModified].join(':') : '';
				values.push([name, String(value)]);
			});
			return JSON.stringify(values);
		}

		var saved = snapshot();
		// Widgets may finish initializing after DOMContentLoaded. Capture before the first edit.
		function captureInteraction() {
			if (!interacted) { saved = snapshot(); interacted = true; }
		}
		['focusin', 'pointerdown', 'keydown', 'beforeinput'].forEach(function (name) {
			form.addEventListener(name, captureInteraction, true);
		});
		window.addEventListener('load', function () { if (!interacted) saved = snapshot(); }, { once: true });

		form.addEventListener('submit', function (event) {
			if (form.fc_ajax_saving) return;
			submitted = snapshot();
			leaving = !form.doajax_submit;
			setTimeout(function () { if (event.defaultPrevented) leaving = false; }, 0);
		}, true);
		form.addEventListener('flexicontent:save-confirmed', function () {
			if (submitted !== null) saved = submitted;
			submitted = null;
			leaving = false;
		});

		// Cancel is an explicit discard. Validation failures never reach the submit event.
		if (window.Joomla && typeof Joomla.submitbutton === 'function') {
			var submitbutton = Joomla.submitbutton;
			Joomla.submitbutton = function (task) {
				if (/(?:^|\.)cancel$/.test(task || '') && !form.fc_ajax_saving) leaving = true;
				return submitbutton.apply(this, arguments);
			};
		}
		window.addEventListener('beforeunload', function (event) {
			if (!leaving && (form.fc_ajax_saving || snapshot() !== saved)) {
				event.preventDefault();
				event.returnValue = '';
			}
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
	else init();
})();
