/**
 * Filter presets for the backend list views (Modern filters style)
 *
 * Up to 5 presets per list view, kept in the browser (localStorage), shown next to the Apply button.
 * A preset is the list of (name, value) pairs of the controls inside the filters box, plus the search text and
 * search scope of the toolbar. Applying a preset submits a temporary form with these values (fcform=1),
 * so it does not depend on the select library (Choices.js / Select2) used by the filter widgets.
 */
(function () {
	'use strict';

	var MAX_PRESETS = 5;
	var NAME_MAXLEN = 30;

	function T(key, def) {
		try {
			if (window.Joomla && Joomla.Text && Joomla.Text._) return Joomla.Text._(key, def) || def;
		} catch (e) { /* ignore */ }
		return def;
	}

	function init() {
		var box  = document.getElementById('fc-filters-box');
		var form = document.adminForm;
		if (!box || !form || !box.classList.contains('fc-filters-modern')) return;

		var viewEl = form.elements['view'];
		var storageKey = 'fc_filter_presets_' + (viewEl && viewEl.value ? viewEl.value : 'items');

		/* ---------- storage ---------- */

		function load() {
			try {
				var data = JSON.parse(localStorage.getItem(storageKey) || '[]');
				return Array.isArray(data) ? data.slice(0, MAX_PRESETS) : [];
			} catch (e) { return []; }
		}

		function store(list) {
			try { localStorage.setItem(storageKey, JSON.stringify(list)); } catch (e) { /* storage not available */ }
		}

		/* ---------- reading / applying filter values ---------- */

		var TOOLBAR_FIELDS = ['search', 'scope'];

		function collect() {
			var pairs = [];
			var controls = box.querySelectorAll('input, select, textarea');

			for (var i = 0; i < controls.length; i++) {
				var el = controls[i], type = (el.type || '').toLowerCase();
				if (!el.name || el.disabled) continue;
				if (['button', 'submit', 'reset', 'file', 'image'].indexOf(type) > -1) continue;
				if ((type === 'checkbox' || type === 'radio') && !el.checked) continue;

				if (el.tagName === 'SELECT') {
					for (var n = 0; n < el.options.length; n++) {
						if (el.options[n].selected) pairs.push([el.name, el.options[n].value]);
					}
				} else {
					pairs.push([el.name, el.value]);
				}
			}

			TOOLBAR_FIELDS.forEach(function (name) {
				var el = form.elements[name];
				if (el && typeof el.value === 'string') pairs.push([name, el.value]);
			});

			return pairs;
		}

		// Comparable form: empty values are dropped, order is normalized
		function signature(pairs) {
			return JSON.stringify(
				pairs.filter(function (p) { return p[1] !== ''; })
					.map(function (p) { return p[0] + '=' + p[1]; })
					.sort()
			);
		}

		function applyPreset(pairs) {
			var skip = { limitstart: 1, task: 1, boxchecked: 1, newstate: 1, search: 1, scope: 1 };
			var controls = box.querySelectorAll('input, select, textarea');
			for (var i = 0; i < controls.length; i++) if (controls[i].name) skip[controls[i].name] = 1;

			var f = document.createElement('form');
			f.method = 'post';
			f.action = form.getAttribute('action');
			f.style.display = 'none';

			function add(name, value) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = name;
				input.value = value;
				f.appendChild(input);
			}

			// Keep the hidden fields of the list form (option, view, sorting, token, ...), except the filters themselves
			var hidden = form.querySelectorAll('input[type="hidden"]');
			for (var h = 0; h < hidden.length; h++) {
				var name = hidden[h].name;
				if (!name || skip[name] || name.indexOf('ord_grp') === 0 || name.indexOf('cid') === 0) continue;
				add(name, hidden[h].value);
			}

			pairs.forEach(function (p) { add(p[0], p[1]); });
			add('limitstart', '0');
			add('boxchecked', '0');
			add('task', '');

			document.body.appendChild(f);
			f.submit();
		}

		/* ---------- UI ---------- */

		var actions = box.querySelector('.fc-filters-actions');
		if (!actions) {
			actions = document.createElement('div');
			actions.className = 'fc-filters-actions';
			var slide = document.getElementById('fc-filters-slide-btn');
			if (slide) box.insertBefore(actions, slide); else box.appendChild(actions);
		}

		var wrap = document.createElement('div');
		wrap.className = 'fc-presets';
		actions.appendChild(wrap);

		function el(tag, cls, text) {
			var e = document.createElement(tag);
			if (cls) e.className = cls;
			if (text !== undefined) e.textContent = text;
			return e;
		}

		function render() {
			var list = load();
			var current = signature(collect());
			wrap.innerHTML = '';

			list.forEach(function (preset, index) {
				var pill = el('span', 'fc-preset');
				pill.title = preset.name;
				if (signature(preset.params) === current) pill.classList.add('is-active');

				var apply = el('button', 'fc-preset-apply', preset.name);
				apply.type = 'button';
				apply.addEventListener('click', function () { applyPreset(preset.params); });

				var del = el('button', 'fc-preset-del', '×');
				del.type = 'button';
				del.title = T('FLEXI_PRESET_DELETE', 'Delete');
				del.addEventListener('click', function (e) {
					e.stopPropagation();
					if (!window.confirm(T('FLEXI_PRESET_DELETE_CONFIRM', 'Delete this preset?'))) return;
					var l = load();
					l.splice(index, 1);
					store(l);
					render();
				});

				pill.appendChild(apply);
				pill.appendChild(del);
				wrap.appendChild(pill);
			});

			var save = el('button', 'fc-preset-save', '+ ' + T('FLEXI_PRESET_SAVE', 'Save'));
			save.type = 'button';
			save.title = T('FLEXI_PRESET_SAVE_TITLE', 'Save the current filters as a preset');
			save.addEventListener('click', function () { askName(save); });
			wrap.insertBefore(save, wrap.firstChild);

			// Info icon after the last preset (or after "Save" when there are none): tooltip on hover, toggled by click
			var info = el('span', 'fc-preset-info');
			var icon = el('button', 'fc-preset-info-icon', 'i');
			icon.type = 'button';
			icon.setAttribute('aria-label', T('FLEXI_PRESET_HELP', 'Presets remember the current filters together with the search text.'));
			var tip = el('span', 'fc-preset-info-tip', T('FLEXI_PRESET_HELP',
				'Presets remember the current filters together with the search text. Set the filters, click Save and enter a name. Click a preset to apply it, click \u00d7 to delete it. Up to 5 presets per list, kept in this browser only.'));
			tip.setAttribute('role', 'tooltip');
			icon.addEventListener('click', function (e) {
				e.stopPropagation();
				info.classList.toggle('is-open');
			});
			info.appendChild(icon);
			info.appendChild(tip);
			wrap.appendChild(info);
		}

		// Click anywhere else closes the opened info tooltip
		document.addEventListener('click', function (e) {
			var open = wrap.querySelector('.fc-preset-info.is-open');
			if (open && !open.contains(e.target)) open.classList.remove('is-open');
		});

		function askName(saveBtn) {
			var input = el('input', 'fc-preset-name');
			input.type = 'text';
			input.maxLength = NAME_MAXLEN;
			input.placeholder = T('FLEXI_PRESET_NAME', 'Preset name');
			input.autocomplete = 'off';
			input.removeAttribute('name');

			function finish(doSave) {
				var name = input.value.trim();
				if (!doSave || !name) { render(); return; }

				var list = load();
				var existing = -1;
				list.forEach(function (p, i) { if (p.name.toLowerCase() === name.toLowerCase()) existing = i; });
				var entry = { name: name, params: collect() };

				if (existing > -1) {
					list[existing] = entry;
				} else if (list.length >= MAX_PRESETS) {
					window.alert(T('FLEXI_PRESET_MAX', 'Maximum 5 presets, delete one first'));
					render();
					return;
				} else {
					list.push(entry);
				}

				store(list);
				render();
			}

			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); finish(true); }
				else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
			});
			input.addEventListener('blur', function () {
				// leave the field without saving, unless the click went to a preset action
				setTimeout(function () { if (document.activeElement !== input && input.parentNode) finish(false); }, 150);
			});

			saveBtn.replaceWith(input);
			input.focus();
		}

		render();

		// Keep the "active" highlight in sync when filters are edited in the box
		box.addEventListener('change', function () {
			var current = signature(collect());
			var list = load();
			var pills = wrap.querySelectorAll('.fc-preset');
			for (var i = 0; i < pills.length && i < list.length; i++) {
				pills[i].classList.toggle('is-active', signature(list[i].params) === current);
			}
		}, true);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
