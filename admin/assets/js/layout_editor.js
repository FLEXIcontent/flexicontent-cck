(function () {
	'use strict';

	const byId = id => document.getElementById(id);
	const editor = field => window.Joomla?.editors?.instances?.[field.id] || field.nextElementSibling?.CodeMirror;
	let pendingLoad;

	FlexiForm.ready(() => {
		const hex = /^#[\da-f]{6}$/i;
		document.querySelectorAll('input.fc-layout-color').forEach(field => {
			const picker = document.createElement('input');
			picker.type = 'color';
			picker.disabled = field.disabled || field.readOnly;
			picker.setAttribute('aria-label', field.labels?.[0]?.textContent.trim() || FlexiForm.text('FLEXI_COLOR'));
			const sync = () => { if (hex.test(field.value)) picker.value = field.value; };
			field.addEventListener('input', sync);
			picker.addEventListener('change', () => {
				field.value = picker.value;
				FlexiForm.change(field);
			});
			sync();
			field.after(picker);
		});
	});

	window.fc_layout_init_positions = function (ids) {
		const storeOrder = list => {
			const hidden = byId(list.id.replace(/^sortable-/, ''));
			if (hidden) hidden.value = Array.from(list.children)
				.filter(field => field.matches('li[id^="field_"]'))
				.map(field => field.id.substring(6)).join(',');
		};
		ids.map(byId).filter(Boolean).forEach(list => {
			Sortable.create(list, {
				group: 'flexicontent-layout-fields',
				draggable: 'li[id^="field_"]',
				onEnd(event) {
					storeOrder(event.from);
					if (event.to !== event.from) storeOrder(event.to);
				}
			});
			storeOrder(list);
		});
	};

	window.filterFieldList = function (id, method, group) {
		const filters = ['content_type', 'field_type'].map(name => [name, byId(name + '_' + group)?.value]);
		byId(id)?.querySelectorAll('li').forEach(field => {
			const matches = filters.every(([name, value]) => !value || field.classList.contains(name + '_' + value));
			if (method === 'hide') FlexiForm.show(field, matches);
			else field.style.color = matches && filters.some(([, value]) => value) ? 'red' : 'black';
		});
	};

	window.toggle_code_inputbox = function (button) {
		const label = button.nextElementSibling;
		const field = label.nextElementSibling;
		const show = !FlexiForm.visible(field);
		FlexiForm.show(label, show);
		FlexiForm.show(field, show);
		button.classList.toggle('btn-info', show);
		const icon = button.querySelector('span');
		icon.classList.toggle('icon-eye', !show);
		icon.classList.toggle('icon-eye-close', show);
		if (show) field.select();
	};

	async function requestLayout(endpoint, data, message, signal) {
		const field = byId('editor__file_contents');
		const indicator = document.createElement('span');
		indicator.id = 'fc_doajax_loading';
		indicator.setAttribute('role', 'status');
		indicator.textContent = FlexiForm.text(message);
		field.before(indicator);
		try {
			const response = await fetch(endpoint, {
				method: 'POST',
				headers: {'X-Requested-With': 'XMLHttpRequest'},
				body: new URLSearchParams({...data, [jformToken]: 1}),
				signal
			});
			if (!response.ok) throw new Error('HTTP ' + response.status);
			const result = await response.json();
			if (!signal?.aborted) byId('ajax-system-message-container').innerHTML = result.sysmssg || '';
			return result;
		} catch (error) {
			if (!signal?.aborted) Joomla.renderMessages({error: [FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED')]}, byId('ajax-system-message-container'));
			return null;
		} finally {
			indicator.remove();
		}
	}

	function applyContents(field, content, path) {
		if (typeof content !== 'string') return;
		field.value = content;
		const instance = editor(field);
		if (instance) {
			instance.setValue(content);
			instance.setOption?.('mode', path.split('.').pop().toLowerCase());
			instance.refresh?.();
		} else {
			FlexiForm.show(field);
		}
	}

	window.save_layout_file = async function () {
		const field = byId('editor__file_contents');
		const path = byId('editor__file_subpath').value;
		const layout = byId('editor__layout_name').value;
		if (!path) {
			FlexiForm.alert(FlexiForm.text('FLEXI_TMPLS_LOAD_FILE_BEFORE_SAVING'));
			return;
		}
		if (isCoreLayout && !await FlexiForm.confirm(FlexiForm.text('FLEXI_TMPLS_SAVE_BUILT_IN_TEMPLATE_FILE_WARNING'))) return;
		const button = byId('editor__save_file_btn');
		if (button.disabled || pendingLoad) return;
		button.disabled = true;
		try {
			const result = await requestLayout('index.php?option=com_flexicontent&task=templates.savelayoutfile&format=raw', {
				layout_name: layout, file_subpath: path,
				file_contents: editor(field)?.getValue() ?? field.value
			}, 'FLEXI_SAVING');
			if (result && byId('editor__file_subpath').value === path && byId('editor__layout_name').value === layout) {
				applyContents(field, result.content, path);
			}
		} finally {
			button.disabled = false;
		}
	};

	window.load_layout_file = async function (layout, path, mode = 0, classes = '') {
		layout ||= byId('editor__layout_name').value;
		path ||= byId('editor__file_subpath').value;
		if (String(classes) === '-1') classes = byId('editor__btn_classes').value;
		const form = byId('layout_file_editor_form');
		const field = byId('editor__file_contents');
		const save = byId('editor__save_file_btn');
		if (save.disabled && !pendingLoad) return;
		byId('editor__layout_name').value = layout;
		byId('editor__file_subpath').value = path;
		byId('editor__load_mode').value = mode;
		byId('editor__btn_classes').value = classes;
		if (String(mode) === '2') {
			form.submit();
			return;
		}

		const visibleClasses = classes.split(/\s+/).filter(Boolean);
		document.querySelectorAll('.code_box').forEach(box => FlexiForm.show(box, false));
		visibleClasses.forEach(name => Array.from(document.getElementsByClassName(name)).forEach(box => FlexiForm.show(box)));
		FlexiForm.show(byId('code_box_header'), visibleClasses.length > 0);
		byId('layout_edit_name_container').textContent = path;

		pendingLoad?.abort();
		const request = pendingLoad = new AbortController();
		const download = byId('editor__download_file_btn');
		const loadCommon = byId('editor__load_common_file_btn');
		save.disabled = true;
		FlexiForm.show(download, false);
		FlexiForm.show(loadCommon, false);
		try {
			const result = await requestLayout(form.action, {layout_name: layout, file_subpath: path, load_mode: mode}, 'FLEXI_LOADING', request.signal);
			if (!result || request.signal.aborted || typeof result.content !== 'string') return;
			applyContents(field, result.content, path);
			FlexiForm.show(byId('edit-css-files-warning'), /\.css$/i.test(path));
			FlexiForm.show(save);
			FlexiForm.show(download);
			FlexiForm.show(loadCommon, Number(result.default_exists) > 0);
			save.disabled = false;
		} finally {
			if (pendingLoad === request) {
				pendingLoad = null;
				// A failed load must never save the previously loaded file under the new name.
				if (save.disabled) FlexiForm.show(save, false);
				save.disabled = false;
			}
		}
	};
})();
