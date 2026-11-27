
	window.fc_init_hide_dependent = 0;
	window.fc_refreshing_dependent = 0;
	window.fc_Dialogs = {};

	function fc_getAutoSizePos(winwidth, winheight, params)
	{
		var maxWidth  = typeof winwidth === 'undefined'  ? 0 : winwidth;
		var maxHeight = typeof winheight === 'undefined' ? 0 : winheight;

		var paddingW = (!!params && !!params.paddingW ? params.paddingW : 70);
		var paddingH = (!!params && !!params.paddingH ? params.paddingH : 120);

		var visibleWidth  = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		var visibleHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;

		var w = maxWidth  ? maxWidth  : visibleWidth - paddingW;
		var h = maxHeight ? maxHeight : visibleHeight - paddingH;

		// Add fixed scrolling class and ... also add auto-width / auto-height classes so dimensions are treated as MAX values
		params.dialogClass = typeof params.dialogClass !== 'undefined'  ?  params.dialogClass  :  'fc-fixed-dialog';
		params.dialogClass += ' fc-autow-dialog';
		params.dialogClass += ' fc-autoh-dialog';

		if (visibleWidth < 600 || visibleHeight < 600)
		{
			params.winwidth  = w  > (visibleWidth - 10)  ? (visibleWidth - 10)  : w;
			params.winheight = h  > (visibleHeight - 10) ? (visibleHeight - 10) : h;
		}
		else
		{
			params.winwidth  = w  > (visibleWidth - paddingW)  ? (visibleWidth - paddingW)  : w;
			params.winheight = h  > (visibleHeight - paddingH) ? (visibleHeight - paddingH) : h;
		}
		//window.console.log ('winwidth  : ' + params.winwidth  + ', winheight : ' + params.winheight );

		params.winleft = (visibleWidth  - params.winwidth)  / 2 - 5;
		params.wintop  = (visibleHeight - params.winheight) / 2 - 5;
		//window.console.log ('winleft : ' + params.winleft + ', wintop : ' + params.wintop);

		return params;
	}


	function fc_loadImagePreview(input_id, img_id, msg_id, thumb_w, thumb_h, nonimg_mssg)
	{
		var nonimg_mssg = typeof nonimg_mssg !== 'undefined' ? nonimg_mssg : '';
		var input = document.getElementById(input_id);
		var msg_box = document.getElementById(msg_id);
		var preview = document.getElementById(img_id);
		if (!input || !preview) return;
		// Invalidate the previous asynchronous read when the selection changes.
		var selectedFile = input.files?.[0];
		input.fc_previewFile = selectedFile;
		if (!selectedFile) preview.style.display = 'none';
		var _msg = '';

		var input_files = input.files;

		if (input_files && input_files[0])
		{
			var imageType = /image.*/;

			if (!input_files[0].type.match(imageType))
			{
		  	if (nonimg_mssg=='-1') _msg = '';
		  	else if (nonimg_mssg=='-2') _msg = input.value;
		  	else if (nonimg_mssg=='0' || nonimg_mssg=='') _msg = (nonimg_mssg!='' ? nonimg_mssg : input.value+' <br/> '+Joomla.JText._('FLEXI_NOT_AN_IMAGE_FILE'));
				preview.style.display = 'none';
		  }
			else
			{
		  	if (nonimg_mssg=='-2' || nonimg_mssg=='0') _msg = input.value;
		  	else _msg = '';
				var reader = new FileReader();
				reader.onload = function (e) {
					if (input.fc_previewFile !== selectedFile) return;
					preview.src = e.target.result;
					if (thumb_w) preview.style.width = /^\d+(\.\d+)?$/.test(String(thumb_w)) ? thumb_w + 'px' : thumb_w;
					if (thumb_h) preview.style.height = /^\d+(\.\d+)?$/.test(String(thumb_h)) ? thumb_h + 'px' : thumb_h;
					preview.style.display = '';
				};
				reader.readAsDataURL(input_files[0]);
			}
		}
		if (msg_box) msg_box.nodeName == 'INPUT' ? msg_box.value = input.value : msg_box.textContent = input.value;
	}


	// Dialog handles retain dialog('close') for field plugins which already use it.
	const fc_nativeDialogs = new WeakMap();
	function fc_showAsDialog(obj, winwidth, winheight, closeFunc, params = {})
	{
		const content = FlexiForm.element(obj);
		if (!content) return null;
		const active = fc_nativeDialogs.get(content);
		if (active) return active;
		const settings = {...params};
		const previousFocus = document.activeElement;
		const marker = document.createComment('FLEXIcontent dialog position');
		content.before(marker);
		const originalStyle = content.getAttribute('style');
		const associations = [];
		content.querySelectorAll('input,textarea,select,button').forEach(input => {
			if (input.form?.id && !input.hasAttribute('form')) {
				input.setAttribute('form', input.form.id); associations.push(input);
			}
		});
		const Dialog = window.customElements?.get('joomla-dialog');
		const titleText = document.createElement('span'); titleText.textContent = settings.title || '';
		const popup = Dialog && settings.modal !== false ? new Dialog({
			popupType: 'inline', textHeader: titleText.innerHTML,
			cancelable: settings.closeOnEscape !== false, className: 'fc-joomla-dialog',
			preferredParent: settings.keepPlace ? marker.parentNode : document.body
		}) : null;
		const popupBody = popup?.getBody();
		const dialog = popupBody ? popupBody.closest('dialog') : document.createElement('dialog');
		if (popupBody) {
			// Restore the live form at our marker. Joomla's inline-content restoration
			// would also try to insert it before its former sibling, which may be a transient tooltip.
			popupBody.append(content);
			// Joomla's dialog body is a block box. As a flex column it lets the content's flex:1 fill it,
			// else an iframe dialog (e.g. item edit) falls back to the 150px default iframe height.
			popupBody.style.cssText += ';display:flex;flex-direction:column;min-height:0;';
		} else {
		dialog.className = 'fc-native-dialog ui-dialog ui-widget ui-widget-content';
		dialog.style.cssText = 'position:fixed;padding:0;margin:auto;max-width:calc(100vw - 10px);max-height:calc(100vh - 10px);border:1px solid #aaa;';
		const titlebar = document.createElement('div');
		titlebar.className = 'ui-dialog-titlebar ui-widget-header';
		titlebar.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px;flex:none;';
		const title = document.createElement('span'); title.className = 'ui-dialog-title'; title.textContent = settings.title || '';
		title.id = 'fc-dialog-title-' + (++fc_showAsDialog.sequence); dialog.setAttribute('aria-labelledby', title.id);
		const close = document.createElement('button'); close.type = 'button'; close.textContent = '\u00d7';
		close.className = 'btn fc-dialog-close'; close.setAttribute('aria-label', FlexiForm.text('JCLOSE'));
		titlebar.append(title, close); dialog.append(titlebar, content);
		(settings.keepPlace ? marker.parentNode : document.body).append(dialog);
		}
		content.style.cssText += ';display:block;visibility:visible;flex:1;min-height:0;width:auto;height:auto;overflow:auto;';
		const resize = () => {
			const size = fc_getAutoSizePos(winwidth, winheight, {...settings});
			dialog.style.width = size.winwidth + 'px'; dialog.style.height = size.winheight + 'px';
		};
		resize(); window.addEventListener('resize', resize);
		const handle = {element: content, close: () => { popup ? popup.close() : dialog.close(); finish(); }, dialog: action => {
			if (action === 'close' || action === 'destroy') handle.close();
			return handle;
		}};
		// Select2 3.x normally puts its menu under body, outside a native modal's
		// interactive subtree. Its existing body/positioning API can target this dialog.
		const legacySelects = new Map();
		let legacyMask;
		const legacyOpening = event => {
			const instance = window.jQuery(event.target).data('select2');
			if (!instance?.body || legacySelects.has(instance)) return;
			legacySelects.set(instance, instance.body); instance.body = window.jQuery(dialog);
			const mask = document.getElementById('select2-drop-mask');
			if (mask && mask.parentElement !== dialog) { legacyMask = {mask, parent: mask.parentElement}; dialog.append(mask); }
		};
		if (window.jQuery) window.jQuery(content).on('select2-opening.fcNativeDialog', legacyOpening);
		fc_nativeDialogs.set(content, handle);
		if (!popup) dialog.querySelector('.fc-dialog-close').addEventListener('click', handle.close);
		dialog.addEventListener('cancel', event => { if (settings.closeOnEscape === false) event.preventDefault(); });
		dialog.addEventListener('click', event => {
			if (event.target !== dialog || settings.closeOnOutClick === false) return;
			const rect = dialog.getBoundingClientRect();
			if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) handle.close();
		});
		let finished = false;
		const finish = () => {
			if (finished) return;
			finished = true;
			if (window.jQuery) window.jQuery(content).off('select2-opening.fcNativeDialog', legacyOpening);
			legacySelects.forEach((parent, instance) => { instance.close(); instance.body = parent; instance.dropdown.detach().appendTo(parent); });
			const mask = document.getElementById('select2-drop-mask');
			if (mask?.parentElement === dialog) (legacyMask?.parent || document.body).append(mask);
			window.removeEventListener('resize', resize); fc_nativeDialogs.delete(content);
			popup?.destroy();
			marker.replaceWith(content); associations.forEach(input => input.removeAttribute('form'));
			if (originalStyle === null) content.removeAttribute('style'); else content.setAttribute('style', originalStyle);
			if (settings.visibleOnClose) FlexiForm.show(content);
			dialog.remove();
			const count = Math.max(0, (FlexiForm.data(document.body, 'fc_modal_count') || 1) - 1);
			FlexiForm.data(document.body, 'fc_modal_count', count);
			if (!count) document.body.classList.remove('fc-no-scroll');
			if (typeof closeFunc === 'function') closeFunc(window.jQuery ? window.jQuery(content) : content);
			else if (closeFunc === 1) window.location.reload();
			if (previousFocus?.isConnected) previousFocus.focus();
		};
		dialog.addEventListener('close', finish, {once: true});
		FlexiForm.data(document.body, 'fc_modal_count', (FlexiForm.data(document.body, 'fc_modal_count') || 0) + 1);
		document.body.classList.add('fc-no-scroll');
		if (popup) popup.show();
		else {
			settings.modal === false ? dialog.show() : dialog.showModal();
			dialog.style.display = 'flex'; dialog.style.flexDirection = 'column';
		}
		return handle;
	}
	fc_showAsDialog.sequence = 0;

	function fc_showDialog(url, tagid, no_iframe, winwidth, winheight, closeFunc, params = {})
	{
		fc_closeDialog(tagid);
		let content = document.getElementById(tagid);
		if (!content) { content = document.createElement('div'); content.id = tagid; document.body.append(content); }
		content.replaceChildren(); content.style.display = 'none';
		const loading = document.createElement('div'); loading.className = 'fc_loading_msg'; loading.textContent = 'Loading...'; content.append(loading);
		let frame, controller;
		const handle = fc_showAsDialog(content, winwidth, winheight, () => {
			controller?.abort(); content.replaceChildren(); content.style.display = 'none';
			if (fc_Dialogs[tagid] === handle) delete fc_Dialogs[tagid];
			if (typeof closeFunc === 'function') closeFunc(); else if (closeFunc === 1) window.location.reload();
		}, {closeOnEscape: false, ...params});
		fc_Dialogs[tagid] = handle;
		if (!no_iframe) {
			frame = document.createElement('iframe'); frame.id = tagid + '_frame'; frame.title = params.title || FlexiForm.text('FLEXI_EDIT');
			frame.style.cssText = 'width:100%;height:100%;border:0;display:block;visibility:hidden;';
			content.style.overflow = 'hidden'; content.style.padding = '0';
			frame.addEventListener('load', () => {
				loading.remove(); frame.style.visibility = 'visible';
				try {
					const doc = frame.contentDocument;
					// Move focus into the loaded page, as a normal page load does: Tab then starts at its first
					// control. Focusing that control directly would open Joomla's skip-to menu on admin pages.
					frame.contentWindow?.focus();
					if (params.closeOnEscape) doc?.defaultView?.addEventListener('keydown', event => {
						if (event.key !== 'Escape' || event.defaultPrevented || event.isComposing || event.repeat) return;
						// Let an open menu or nested dialog inside the picker handle the first Escape.
						if (doc.querySelector('dialog[open], [aria-expanded="true"][aria-haspopup="listbox"], .choices.is-open, .select2-drop-active')) return;
						event.preventDefault();
						handle.close();
					});
				} catch (_) { /* Cross-origin previews do not expose their document. */ }
				if (typeof params.loadFunc === 'function') params.loadFunc(window.jQuery ? window.jQuery(content) : content);
			});
			frame.src = url; content.append(frame);
		} else {
			// Keep the optional legacy transport's AJAX hooks for existing integrations.
			if (window.jQuery) window.jQuery(content).load(url);
			else {
				controller = new AbortController();
				const targetUrl = new URL(url, document.baseURI);
				if (targetUrl.origin !== location.origin) { loading.textContent = 'Dialog URL must use this site'; return handle; }
				fetch(targetUrl, {credentials: 'same-origin', signal: controller.signal}).then(response => {
					if (!response.ok) throw Error('Dialog could not be loaded'); return response.text();
				}).then(html => FlexiForm.insertHTML(content, html, controller.signal)).catch(error => {
					if (error.name !== 'AbortError' && !controller.signal.aborted) { loading.textContent = error.message; content.prepend(loading); }
				});
			}
		}
		return handle;
	}

	function fc_closeDialog(name)
	{
		const handle = fc_Dialogs[name] || fc_nativeDialogs.get(document.getElementById(name));
		handle?.dialog('close'); return false;
	}


	// Scroll into view
	function fc_scrollIntoView(elem, smooth)
	{
		const target = FlexiForm.element(elem);
		if (!target) return;
		const rect = target.getBoundingClientRect();
		const bottom = rect.bottom + (parseFloat(getComputedStyle(target).marginBottom) || 0);
		const offset = rect.top < 90 ? rect.top - 90
			: bottom > window.innerHeight - 80 ? bottom - window.innerHeight + 80 : 0;
		if (offset) window.scrollBy({ top: offset, behavior: smooth ? 'smooth' : 'instant' });
	}


	const fcDependencyBindings = new WeakMap();
	const fcDependencyTogglers = 'select.fcform_toggler_element, .fcform_toggler_element input[type="radio"]';
	let fcDependencyUpdates = null;

	function fc_findFormDependencies(option, toggleParent, toggleParentSelector, field)
	{
		option = FlexiForm.element(option);
		field = FlexiForm.element(field);
		if (!option || !field) return [];

		const updated = new Set();
		const updateClasses = (attribute, delta) => {
			const names = (option.getAttribute('data-' + attribute) || '').split(',').map(name => name.trim()).filter(Boolean);
			for (const name of new Set(names)) {
				for (const target of document.getElementsByClassName(name)) {
					if (delta === 0) {
						target.setAttribute('data-fc_forced_display', '1');
					} else {
						const list = FlexiForm.data(target, 'fc_depend_list') || Object.create(null);
						list[name] = (Number(list[name]) || 0) + delta;
						FlexiForm.data(target, 'fc_depend_list', list);
					}
					updated.add(target);
				}
			}
		};
		updateClasses('force_list', 0);
		updateClasses('hide_list', 1);
		fc_configureDependencyFields(option, field);
		updateClasses('show_list', -1);

		const hide = option.getAttribute('data-setoff_list');
		const show = option.getAttribute('data-seton_list');
		if (hide || show) setTimeout(() => {
			if (hide) document.querySelectorAll(hide).forEach(target => FlexiForm.show(target, false));
			if (show) document.querySelectorAll(show).forEach(target => FlexiForm.show(target));
		}, fc_init_hide_dependent ? 50 : 0);

		if (!fc_init_hide_dependent) {
			fc_applyFormDependencies(updated, toggleParent, toggleParentSelector);
			const refresh = option.getAttribute('data-refsh_list');
			if (refresh && !fc_refreshing_dependent) {
				fc_refreshing_dependent = 1;
				try {
					for (const control of document.querySelectorAll(refresh)) {
						const target = control.matches('select') ? control
							: (control.closest('.fcform_toggler_element') || control).querySelector('input[type="radio"]:checked');
						target?.dispatchEvent(new Event('fcform_run_deps'));
					}
				} finally {
					fc_refreshing_dependent = 0;
				}
			}
		}

		if (field.classList.contains('fccustom_revert') && option.value !== '') {
			const radio = field.matches('fieldset, .fcform_toggler_element:not(select)');
			const current = radio ? field.querySelector('input[type="radio"]:checked') : field;
			if (current?.value) {
				const controls = radio ? [...field.querySelectorAll('input[type="radio"]')] : [field];
				const disabled = controls.map(control => control.disabled);
				controls.forEach(control => { control.disabled = true; });
				setTimeout(() => {
					controls.forEach((control, index) => { control.disabled = disabled[index]; });
					if (radio) controls.find(control => control.value === '')?.click();
					else {
						field.value = '';
						field.click();
					}
				}, 200);
			}
		}
		return [...updated];
	}

	function fc_configureDependencyFields(option, field)
	{
		const configs = attribute => {
			const value = option.getAttribute('data-' + attribute);
			if (!value) return [];
			// Older field XML stores objects with single quotes.
			try { return Object.entries(JSON.parse(value) || {}); }
			catch (_) { return Object.entries(JSON.parse(value.replace(/'/g, '"')) || {}); }
		};
		const find = name => document.getElementById('jform_attribs_' + name)
			|| document.getElementById('jform_params_' + name) || document.getElementById('jform_' + name);
		const readOnly = (target, enabled) => {
			target.toggleAttribute('readonly', enabled);
			if (target.matches('select, label')) target.style.pointerEvents = enabled ? 'none' : '';
			if (!target.matches('input[type="radio"]')) target.style.opacity = enabled ? '0.65' : '';
			if (target.matches('label')) target.toggleAttribute('disabled', enabled);
		};

		for (const [name, value] of configs('fcconfigs')) {
			const target = find(name);
			if (!target) continue;
			if (target.matches('fieldset')) {
				const inputs = [...target.querySelectorAll('input')];
				inputs.forEach(input => {
					input.disabled = false;
					readOnly(input, false);
				});
				target.querySelectorAll('label').forEach(label => readOnly(label, false));
				inputs.find(input => input.value === String(value))?.click();
			} else {
				target.disabled = false;
				readOnly(target, false);
				if (target.matches('select[multiple]')) {
					const selected = String(value).split(',').map(value => value.trim());
					for (const item of target.options) item.selected = selected.includes(item.value);
				} else {
					target.value = value;
				}
				if (target.matches('select')) FlexiForm.change(target);
				else {
					target.click();
					target.dispatchEvent(new FocusEvent('blur'));
				}
			}
		}

		for (const [name, value] of configs('fcreadonly')) {
			const target = find(name);
			if (!target) continue;
			const enabled = Boolean(value) && !field.classList.contains('fccustom_revert');
			if (target.matches('fieldset')) {
				target.querySelectorAll('input, label').forEach(control => readOnly(control, enabled));
			} else {
				readOnly(target, enabled);
			}
		}
	}

	// Add toggling of dependent form elements.
	function fc_bindFormDependencies(container, toggleParent, toggleParentSelector)
	{
		const box = FlexiForm.element(container);
		if (!box) return;
		const controls = [...box.querySelectorAll(fcDependencyTogglers)];
		const forms = new Map();
		for (const control of controls) {
			if (control.form && !forms.has(control.form)) {
				forms.set(control.form, FlexiForm.data(control.form, 'skip_validation'));
			}
			if (fcDependencyBindings.has(control)) continue;
			const select = control.matches('select');
			const eventType = select ? 'change' : 'click';
			let running = false;
			const run = event => {
				if (running || event?.originalEvent) return;
				running = true;
				try {
					const option = select ? control.selectedOptions[0] : control;
					const field = select ? control : control.closest('.fcform_toggler_element');
					const targets = fc_findFormDependencies(option, toggleParent, toggleParentSelector, field);
					if (fcDependencyUpdates) targets.forEach(target => fcDependencyUpdates.add(target));
				} finally {
					running = false;
				}
			};
			fcDependencyBindings.set(control, run);
			control.addEventListener(eventType, run);
			control.addEventListener('fcform_run_deps', run);
			// Select2 and older plugins can still trigger synthetic jQuery events.
			if (window.jQuery) {
				const events = select ? 'change.fcDependencies fcform_run_deps.fcDependencies' : 'fcform_run_deps.fcDependencies';
				window.jQuery(control).on(events, run);
			}
		}

		const wasInitializing = fc_init_hide_dependent;
		const previousUpdates = fcDependencyUpdates;
		fcDependencyUpdates = new Set();
		fc_init_hide_dependent = 1;
		try {
			for (const form of forms.keys()) FlexiForm.data(form, 'skip_validation', 1);
			for (const control of controls) {
				if (control.matches('select, :checked')) fcDependencyBindings.get(control)();
			}
			fc_applyFormDependencies(fcDependencyUpdates, toggleParent, toggleParentSelector);
		} finally {
			fcDependencyUpdates = previousUpdates;
			fc_init_hide_dependent = wasInitializing;
			for (const [form, previous] of forms) FlexiForm.data(form, 'skip_validation', previous ?? null);
		}
	}

	/* Attach form validation to inner elements of the given selector */
	function fc_validationAttach(sel)
	{
		document.formvalidator.attachToForm(null, sel);
	}


	/* Set children to have same minimum height e.g. floated elements that need to wrap properly */
	function fc_setEqualHeights(box, property = 0, delay = 20)
	{
		const parent = FlexiForm.element(box);
		if (!parent) return;
		const elements = Array.from(parent.children);
		elements.forEach(element => { element.style.height = ''; element.style.minHeight = ''; });
		setTimeout(() => {
			const height = Math.max(0, ...elements.map(element => element.getBoundingClientRect().height));
			for (const element of elements) {
				const style = getComputedStyle(element);
				const extra = style.boxSizing === 'border-box' ? 0 :
					['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']
					.reduce((sum, name) => sum + (parseFloat(style[name]) || 0), 0);
				const value = Math.max(0, height - extra) + 'px';
				if (Number(property) !== 2) element.style.height = value;
				if (Number(property) !== 1) element.style.minHeight = value;
			}
		}, delay);
	}


	function fc_toggleCompactValuesView(el, field_values_box, btn_class)
	{
		const button = FlexiForm.element(el);
		const box = FlexiForm.element(field_values_box);
		if (!button || !box) return;
		let expanded;
		if (button.tagName === 'INPUT')
		{
			expanded = button.type === 'checkbox' ? button.checked : button.value === '1';
		}
		else
		{
			expanded = !FlexiForm.data(button, 'expandedFieldState');
			FlexiForm.data(button, 'expandedFieldState', expanded);
			for (const name of ['active', ...(btn_class || 'btn-info').trim().split(/\s+/)]) {
				if (name) button.classList.toggle(name, expanded);
			}
		}
		box.querySelectorAll('.fcfieldval_container').forEach(row => row.classList.toggle('fc-expanded', expanded));
	}


	/* Attach boostrap styling / behaviour to the inner contents of the given selector */
	const fc_bootstrapBound = new WeakSet();
	function fc_bootstrapAttach(sel)
	{
		const box = fc_normalizeContainer(sel);
		box.querySelectorAll('.radio.btn-group label').forEach(label => label.classList.add('btn'));
		box.querySelectorAll('fieldset.btn-group').forEach(group => {
			group.querySelectorAll('.btn').forEach(button => button.classList.toggle('disabled', group.disabled));
		});
		box.querySelectorAll('.btn-group input[type=radio]').forEach(input => {
			const update = () => {
				if (!input.checked) return;
				const group = input.closest('.btn-group');
				group.querySelectorAll('label').forEach(label => {
					label.classList.remove('active', 'btn-success', 'btn-danger', 'btn-primary');
					if (label.htmlFor === input.id) label.classList.add('active', input.value === '' ? 'btn-primary' : input.value === '0' ? 'btn-danger' : 'btn-success');
				});
			};
			if (!fc_bootstrapBound.has(input)) {
				input.addEventListener('change', update);
				fc_bootstrapBound.add(input);
			}
			update();
		});
	}


	function fc_applyFormDependencies(toBeUpdated, toggleParent, toggleParentSelector)
	{
		const updated = [...toBeUpdated].map(FlexiForm.element).filter(Boolean);
		for (const target of updated) {
			const list = FlexiForm.data(target, 'fc_depend_list') || {};
			for (const name of Object.keys(list)) {
				if (list[name] > 0) list[name] = 1;
				else delete list[name];
			}
			FlexiForm.data(target, 'fc_depend_list', list);

			const show = !Object.keys(list).length || target.getAttribute('data-fc_forced_display') === '1';
			const parents = [];
			if (toggleParent && !target.classList.contains('fc_toggle_current')) {
				for (let parent = target.parentElement; parent; parent = parent.parentElement) parents.push(parent);
			}
			const wrappers = toggleParentSelector ? parents.filter(parent => parent.matches(toggleParentSelector))
				: parents.slice(toggleParent, toggleParent + 1);
			(wrappers.length ? wrappers : [target]).forEach(box => FlexiForm.show(box, show));
		}
		for (const target of updated) target.setAttribute('data-fc_forced_display', '0');
	}

	const fcCascadeBindings = new WeakMap();
	const fcCascadeRequests = new WeakMap();
	const fcCascadeMarker = { elementClear: true };

	function fcCascadedField_clear(element, prompt, promptEnabled)
	{
		const target = FlexiForm.element(element);
		if (!target) return;
		if (target.matches('select')) {
			const hadOptions = [...target.options].some(option => option.value && option.value !== '_field_selection_prompt_');
			target.replaceChildren();
			if (prompt) {
				const option = new Option(prompt, '');
				option.disabled = !promptEnabled;
				target.append(option);
			}
			target.value = '';
			target.fc_choices_instance?.refresh?.(false, false, true);
			if (hadOptions) target.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: fcCascadeMarker }));
		} else {
			const source = target.querySelector('input[type="radio"], input[type="checkbox"]');
			const message = document.createElement('span');
			message.className = 'fcpadded alert alert-info';
			message.textContent = prompt;
			// The next level listens on the stable input-set container.
			if (source) source.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: fcCascadeMarker }));
			target.replaceChildren(...(prompt ? [message] : []));
		}
	}

	async function fcCascadedField_update(value, targetId, fieldId, itemId, fieldType, prompt, promptEnabled, index)
	{
		const target = document.getElementById(targetId);
		if (!target) return;
		fcCascadeRequests.get(target)?.abort();
		const request = new AbortController();
		fcCascadeRequests.set(target, request);
		const status = target.parentElement.querySelector('.field_cascade_loading');
		if (status) status.textContent = FlexiForm.text('FLEXI_LOADING');
		fcCascadedField_clear(target, '', promptEnabled);
		target.removeAttribute('data-defvals');

		const body = new URLSearchParams({
			task: 'call_extfunc', extfunc: 'getCascadedField', exttype: 'plugins', extfolder: 'flexicontent_fields',
			extname: fieldType, omethod: 'html', field_id: fieldId, item_id: itemId, valindex: index, lang: window.fc_sef_lang || ''
		});
		if (Array.isArray(value)) value.forEach(entry => body.append('valgrps[]', entry));
		else body.set('valgrps', value);

		try {
			const url = new URL('index.php?option=com_flexicontent&tmpl=component&format=raw', window.jbase_url_fc || document.baseURI);
			const response = await fetch(url, { method: 'POST', body, signal: request.signal });
			if (!response.ok) throw new Error('Dependent options request failed');
			const html = (await response.text()).trim();
			if (request.signal.aborted || !target.isConnected) return;
			if (!html) {
				fcCascadedField_clear(target, prompt, promptEnabled);
				return;
			}
			const select = target.matches('select');
			const fragment = document.createElement('template');
			fragment.innerHTML = html;
			if (!fragment.content.querySelector(select ? 'option' : 'input[type="radio"], input[type="checkbox"]')) {
				throw new Error('Unexpected dependent options response');
			}
			target.replaceChildren(fragment.content);
			if (select) {
				target.value = '';
				target.fc_choices_instance?.refresh?.(false, false, true);
				FlexiForm.change(target);
			} else if (window.jQuery?.fn.prettyCheckable) {
				for (const input of target.querySelectorAll('.use_prettycheckable')) {
					const label = input.nextElementSibling;
					if (label?.matches('label')) {
						const labelHtml = label.innerHTML;
						label.remove();
						window.jQuery(input).prettyCheckable({ color: 'blue', label: labelHtml });
					}
				}
			}
		} catch (error) {
			if (!request.signal.aborted && status) status.textContent = FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED');
		} finally {
			if (fcCascadeRequests.get(target) === request) {
				fcCascadeRequests.delete(target);
				if (status?.textContent === FlexiForm.text('FLEXI_LOADING')) status.textContent = '';
			}
		}
	}

	function fcCascadedField(fieldId, itemId, fieldType, sourceSelector, targetId, prompt, promptEnabled, index, initialize = false)
	{
		const target = document.getElementById(targetId);
		// The generated selector identifies both a select and its radio/checkbox equivalent.
		// Use the stable container when the source currently has no choices.
		const sourceId = /^select#([^,]+),\s*input\.\1$/.exec(sourceSelector)?.[1];
		const firstOption = document.querySelector(sourceSelector);
		const source = (sourceId && document.getElementById(sourceId)) || firstOption?.closest('.fc_input_set') || firstOption;
		if (!source || !target || source === target) return;

		fcCascadeBindings.get(target)?.abort();
		const binding = new AbortController();
		fcCascadeBindings.set(target, binding);
		const isSelect = source.matches('select');
		const row = target.closest('.fcfieldval_container_outer, .control-group');
		function chosen(event) {
			if (isSelect) {
				const fromChoices = event?.detail?.value !== undefined ? source.fc_choices_instance?.getValue(true) : undefined;
				const raw = fromChoices ?? FlexiForm.value(source);
				const valid = value => value && value !== '_field_selection_prompt_';
				return Array.isArray(raw) ? raw.filter(valid) : valid(raw) ? raw : '';
			}
			const checked = source.matches('input:checked') ? [source] : [...source.querySelectorAll('input:checked')];
			return checked.filter(input => input.matches(sourceSelector)).map(input => input.value).join(',');
		}
		const hasValue = value => Array.isArray(value) ? value.length > 0 : Boolean(value);

		function update(event, detail = event.detail) {
			if (!event.target.matches(sourceSelector)) return;
			if (!target.isConnected) {
				binding.abort();
				return;
			}
			const value = detail?.elementClear ? '' : chosen(event);
			const show = hasValue(value);
			FlexiForm.show(row, show);
			if (show) {
				fcCascadedField_update(value, targetId, fieldId, itemId, fieldType, prompt, promptEnabled, index);
			} else {
				fcCascadeRequests.get(target)?.abort();
				fcCascadeRequests.delete(target);
				const status = target.parentElement.querySelector('.field_cascade_loading');
				if (status) status.textContent = '';
				fcCascadedField_clear(target, prompt, promptEnabled);
			}
		}

		source.addEventListener('change', update, { signal: binding.signal });
		// Select2 and PrettyCheckable can still provide synthetic events.
		if (window.jQuery) {
			const legacy = window.jQuery(source);
			const syntheticChange = (event, detail) => { if (!event.originalEvent) update(event, detail); };
			legacy.on('change.fcCascade', syntheticChange);
			binding.signal.addEventListener('abort', () => legacy.off('change.fcCascade', syntheticChange), { once: true });
		}
		if (initialize) {
			const trigger = isSelect ? source : source.querySelector('input:checked') || source.querySelector('input');
			if (trigger) update({ target: trigger });
			else FlexiForm.show(row, false);
		} else {
			FlexiForm.show(row, hasValue(chosen()));
		}
	}


	// *** Check that a variable is not null and is defined (has a value)
	function js_isset (variable)
	{
		if (variable==null) return false;
		if (typeof variable == "undefined") return false;
		return true;
	}


	// Store a configuration cookie (like current user choices)
	function fc_config_store(conf, prop_name, prop_value)
	{
		var cookieName  = conf.fc_man_config_cookie;
		var managerName = conf.fc_man_manager_name;
		var cookieValue = fclib_getCookie(cookieName);

		//window.console.log('Using cookieName: ' + cookieName + ', managerName: ' + managerName);
		//window.console.log(cookieName + ', Old value: ' +cookieValue);

		try { cookieValue = JSON.parse(cookieValue || '{}') || {}; }
		catch { cookieValue = {}; }

		if (typeof cookieValue[managerName] == 'undefined')
		{
			cookieValue[managerName] = {};
		}
		cookieValue[managerName][prop_name] = prop_value;
		window.console.log(cookieName + ', New value: ' + JSON.stringify(cookieValue));

		var nDays = 30;
		fclib_setCookie(cookieName, JSON.stringify(cookieValue), nDays);
	}


	//******************************************
	//*** Column hide/show related functions ***
	//******************************************

	// *** Hide a column of a table
	// Recompute each cell using its original column span, including summary rows.
	function fc_tbl_toggle_column(containerId, tableId, column, firstRun)
	{
		const table = document.getElementById(tableId);
		const header = table?.tHead?.rows[0];
		const visible = window['show_col_' + tableId];
		if (!header?.cells[column] || !visible) return;
		visible[column] = visible[column] === 0 ? 1 : 0;
		const shown = Array.from(header.cells).flatMap((cell, i) => Array(Number(cell.dataset.colspan ||= cell.colSpan)).fill(visible[i] !== 0));
		table.querySelectorAll(':scope > thead > tr, :scope > tbody > tr').forEach(row => {
			let start = 0;
			for (const cell of row.cells) {
				const span = Number(cell.dataset.colspan ||= cell.colSpan);
				const width = span - shown.slice(start, start + span).filter(value => !value).length;
				start += span;
				cell.classList.remove('initiallyHidden');
				cell.classList.toggle('isHidden', !width);
				cell.style.display = width ? '' : 'none';
				if (span > 1) cell.colSpan = width || span;
			}
		});
		const counter = document.getElementById(tableId + '_hidecolnum_box');
		if (counter) counter.textContent = visible.filter(value => value === 0).length;
		if (firstRun && visible[column] === 0)
			document.getElementById('fc_' + containerId + '_btn')?.classList.add('btn-dark', 'btn-inverse', 'text-white');
		if (!firstRun && containerId) column_toggles_update_data(containerId, tableId);
	}

	function column_toggles_update_data(containerId, tableId)
	{
		const selectors = document.getElementById(containerId + '_cols')?.querySelectorAll('input') || [];
		const chosen = Array.from(selectors).filter(input => input.checked || input.dataset.screenHidden === '1')
			.map(input => input.dataset.colno);
		let preferences;
		try { preferences = JSON.parse(fclib_getCookie('fc_columnchooser') || '{}') || {}; }
		catch { preferences = {}; }
		preferences[tableId] = chosen.join(',');
		fclib_setCookie('fc_columnchooser', JSON.stringify(preferences), 30);
		const totals = document.getElementById('columnchoose_totals');
		if (totals) totals.textContent = selectors.length === chosen.length ? '' : ' ' + (chosen.length - selectors.length);
	}

	function create_column_choosers(containerId, tableId, firstLoad, startHtml = '', endHtml = '', apply)
	{
		const container = document.getElementById(containerId);
		const header = document.getElementById(tableId)?.tHead?.rows[0];
		const visible = window['show_col_' + tableId];
		if (!container || !header || !visible) return;
		const chooser = document.createElement('span');
		chooser.id = containerId + '_cols';
		const list = document.createElement('ul');
		const columns = [];
		for (const [column, cell] of Array.from(header.cells).entries()) {
			if (!cell.classList.contains('hideOnDemandClass')) continue;
			const name = cell.querySelector('.column_toggle_lbl');
			const label = document.createElement('label');
			if (name) {
				label.append(...name.childNodes);
				name.remove();
			} else label.textContent = cell.textContent.trim();
			const shown = visible[column] !== undefined ? Boolean(visible[column])
				: firstLoad && !cell.classList.contains('initiallyHidden');
			const input = document.createElement('input');
			input.type = 'checkbox';
			input.id = 'columnchoose_' + tableId + '_' + column;
			input.name = 'columnchoose_' + tableId + '[' + column + ']';
			label.htmlFor = input.id;
			label.id = input.id + '_label';
			input.dataset.colno = column;
			input.checked = shown;
			input.addEventListener('change', () => fc_tbl_column_choice(containerId, tableId, column, input));
			const item = document.createElement('li');
			item.append(input, label);
			list.append(item);
			columns.push([column, shown]);
		}
		if (apply) for (const [column, shown] of columns) {
			visible[column] = shown ? 0 : 1;
			fc_tbl_toggle_column(containerId, tableId, column, true);
		}
		chooser.append(list);
		const note = document.createElement('div');
		note.id = containerId + '_screen_note';
		note.className = 'fc-columns-screen-note small text-muted';
		note.style.cssText = 'display:none; clear:both';
		note.textContent = FlexiForm.text('FLEXI_COLUMNS_SCREEN_HIDDEN', 'Some columns are hidden on smaller screens. Tick one to show it.');
		container.innerHTML = startHtml;
		container.append(chooser, note);
		if (endHtml) container.insertAdjacentHTML('beforeend', endHtml);
		fc_tbl_screen_columns(containerId, tableId, true);
		column_toggles_update_data(containerId, tableId);
	}


	/*
	 * Columns hidden by CSS on smaller screens (e.g. by the 'hidden-phone' / 'hidden-tablet' classes of the
	 * list tables) are shown unticked in the column chooser while hidden. Ticking such a column shows it on
	 * smaller screens too; this is remembered in the browser (localStorage). The screen size never changes
	 * the column chooser cookie, so wider screens keep showing the columns that the user has chosen.
	 */

	// *** Get / set the columns shown on smaller screens by the user's choice
	function fc_tbl_screen_cols_get(data_tbl_id)
	{
		try {
			var all = JSON.parse(window.localStorage.getItem('fc_columnchooser_screen') || '{}');
			return Array.isArray(all[data_tbl_id]) ? all[data_tbl_id] : [];
		} catch (e) {
			return [];
		}
	}

	function fc_tbl_screen_cols_set(data_tbl_id, cols)
	{
		try {
			var all = JSON.parse(window.localStorage.getItem('fc_columnchooser_screen') || '{}');
			all[data_tbl_id] = cols;
			window.localStorage.setItem('fc_columnchooser_screen', JSON.stringify(all));
		} catch (e) {}
	}


	// *** Get all cells of a column, given the index of its head cell (cells may use 'colspan')
	function fc_tbl_column_cells(data_tbl_id, col_no)
	{
		var rows = document.getElementById(data_tbl_id)?.querySelectorAll(':scope > thead > tr, :scope > tbody > tr') || [];
		var span = function(cell) { return parseInt(cell.getAttribute('data-colspan') || cell.getAttribute('colspan')) || 1; };
		var head = rows[0]?.cells || [];
		var start = 0;
		for (var i = 0; i < col_no && i < head.length; i++) start += span(head[i]);
		var end = start + (head[col_no] ? span(head[col_no]) : 1);

		var cells = [];
		for (var row = 0; row < rows.length; row++)
		{
			var tcells = rows[row].cells;
			var pos = 0;
			for (var cell = 0; cell < tcells.length && pos < end; cell++)
			{
				var n = span(tcells[cell]);
				if (pos >= start && pos + n <= end) cells.push(tcells[cell]);
				pos += n;
			}
		}
		return cells;
	}


	// *** Update the column chooser for the current screen size, and show the columns that the user has chosen to show
	function fc_tbl_screen_columns(container_div_id, data_tbl_id, firstrun)
	{
		var show_col = window['show_col_' + data_tbl_id] || [];
		var forced   = fc_tbl_screen_cols_get(data_tbl_id);
		var thcells  = (document.getElementById(data_tbl_id)?.tHead?.rows[0]?.cells || []);
		var hidden_cnt = 0;

		document.getElementById(container_div_id + '_cols')?.querySelectorAll('input').forEach(input =>
		{
			var col = Number(input.dataset.colno);
			var th  = thcells[col];
			if (!th) return;

			// Column is shown according to the column chooser (cookie)
			var shown = !js_isset(show_col[col]) || !!show_col[col];

			// Measure without the display set below (FC's own hide replaces it, so only remove our 'important' one)
			var cells = null;
			if (th.getAttribute('data-screen-forced') === '1')
			{
				cells = fc_tbl_column_cells(data_tbl_id, col);
				cells.forEach(cell => {
					if (cell.style.getPropertyPriority('display') === 'important') cell.style.removeProperty('display');
					delete cell.dataset.screenForced;
				});
			}

			var screen_hidden = shown && window.getComputedStyle(th).display === 'none';
			var force = screen_hidden && forced.indexOf(col) !== -1;

			if (force)
			{
				cells = cells || fc_tbl_column_cells(data_tbl_id, col);
				cells.forEach(cell => {
					cell.style.setProperty('display', 'table-cell', 'important');
					cell.dataset.screenForced = '1';
				});
			}

			input.checked = shown && (!screen_hidden || force);
			input.setAttribute('data-screen-hidden', screen_hidden && !force ? '1' : '0');
			if (screen_hidden && !force) hidden_cnt++;
		});

		var note = document.getElementById(container_div_id + '_screen_note');
		if (note) note.style.display = hidden_cnt ? 'block' : 'none';

		// Check again when the window is resized
		if (firstrun)
		{
			var timer = null;
			window.addEventListener('resize', function()
			{
				clearTimeout(timer);
				timer = setTimeout(function() { fc_tbl_screen_columns(container_div_id, data_tbl_id, false); }, 150);
			});
		}
	}


	// *** Tick / untick a column in the column chooser
	function fc_tbl_column_choice(container_div_id, data_tbl_id, col_no, el)
	{
		var show_col = window['show_col_' + data_tbl_id] || [];
		var shown    = !js_isset(show_col[col_no]) || !!show_col[col_no];
		var forced   = fc_tbl_screen_cols_get(data_tbl_id);
		var pos      = forced.indexOf(col_no);
		var th       = (document.getElementById(data_tbl_id)?.tHead?.rows[0]?.cells || [])[col_no];

		if (el.checked)
		{
			// Show the column, and keep showing it on this screen size if CSS hides it
			if (!shown) fc_tbl_toggle_column(container_div_id, data_tbl_id, col_no, 0);
			if (th && window.getComputedStyle(th).display === 'none' && pos === -1) forced.push(col_no);
		}
		else if (pos !== -1 && th && th.getAttribute('data-screen-forced') === '1')
		{
			// Shown on this screen size only by the user's choice: let CSS hide it again, the cookie is not changed
			forced.splice(pos, 1);
		}
		else
		{
			// Hide the column on all screen sizes (column chooser cookie)
			if (pos !== -1) forced.splice(pos, 1);
			fc_tbl_toggle_column(container_div_id, data_tbl_id, col_no, 0);
		}

		fc_tbl_screen_cols_set(data_tbl_id, forced);
		fc_tbl_screen_columns(container_div_id, data_tbl_id, false);
	}


	/* Set a cookie */
	function fclib_setCookie(cookieName, cookieValue, nDays, samesite="lax")
	{
		var today = new Date();
		var expires = new Date();
		var path = typeof jclient_path_fc != 'undefined' ? jclient_path_fc : '/';
		if (nDays==null || nDays<0) nDays=0;

		if (nDays)
		{
			expires.setTime(today.getTime() + 3600000*24*nDays);
			document.cookie = cookieName+"="+encodeURIComponent(cookieValue) + ";samesite=" + samesite + ";path=" + path + ";expires=" + expires.toGMTString();
		} else {
			// nDays 0 , aka no expiration date, keep cookie till browser closes
			document.cookie = cookieName+"="+encodeURIComponent(cookieValue) + ";samesite=" + samesite + ";path=" + path;
		}
		//alert(cookieName+"="+encodeURIComponent(cookieValue) + ";path=" + path);
	}


	/* Get a cookie */
	function fclib_getCookie(cookieName)
	{
		const prefix = cookieName + '=';
		const cookie = document.cookie.split(';').map(value => value.trim()).find(value => value.startsWith(prefix));
		try { return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : ''; }
		catch (error) { return ''; }
	}


	/* Get a new list of 2-typles (key, element) living inside a cookie
	 * List is an object with 'key' being the property name and 'element' being the value of the property
	 */
	function fclib_createCookieList(cookieName, nDays)
	{
		let items = Object.create(null);
		try {
			const saved = JSON.parse(fclib_getCookie(cookieName) || '{}');
			if (saved && typeof saved === 'object' && !Array.isArray(saved)) Object.assign(items, saved);
		} catch (error) { /* A stale or truncated cookie must not break the page. */ }
		nDays = nDays || 90;

		return {
			// Add to the items
			"add": function(key, element)
			{
				items[key] = element;
				fclib_setCookie(cookieName, JSON.stringify(items), nDays);
			},

			// Remove from items
			"remove": function (key)
			{
				if (typeof items[key] != 'undefined')
				{
					delete items[key];
					fclib_setCookie(cookieName, JSON.stringify(items), nDays);
				}
			},

			// Clear items
			"clear": function()
			{
				items = Object.create(null);
				fclib_setCookie(cookieName, JSON.stringify(items), nDays);
			},

			// Get all the items
			"items": function()
			{
				return items;
			}
		}
	}


	// Keep editor options when a row is detached, reordered, then restored.
	const fc_areaEditorOptions = new WeakMap();
	function fc_editorAreas(areas) {
		return areas?.nodeType ? [areas] : Array.from(areas || []);
	}

	/* Remove known textarea editors, optionally mark them for restoration. */
	function fc_removeAreaEditors(txtareas, addRestoreClass = false)
	{
		for (const area of fc_editorAreas(txtareas)) {
			const tiny = (window.tinymce || window.tinyMCE)?.get(area.id);
			const code = area.nextElementSibling?.CodeMirror;
			const saved = { tiny: tiny?.settings, code: code?.options };
			fc_areaEditorOptions.set(area, saved);
			if (tiny) {
				tiny.save();
				tiny.remove();
				if (area.closest('.js-editor-tinymce')) {
					// Joomla 5+ keeps a separate editor registry. Restore only after it is cleared.
					saved.detached = import('editor-api').then(({JoomlaEditor}) => JoomlaEditor.unregister(area.id)).catch(() => {});
				}
				if (addRestoreClass) area.classList.add('fc_hadTinyMCE');
			}
			if (code) {
				code.toTextArea();
				if (addRestoreClass) area.classList.add('fc_hadCodeMirror');
			}
		}
	}

	function fc_restoreAreaEditors(txtareas)
	{
		const areas = fc_editorAreas(txtareas);
		fc_attachTinyMCE(areas.filter(area => area.classList.contains('fc_hadTinyMCE')));
		fc_attachCodeMirror(areas.filter(area => area.classList.contains('fc_hadCodeMirror')));
	}

	function fc_attachTinyMCE(txtareas, MCEoptions, mceFieldname = false)
	{
		const areas = fc_editorAreas(txtareas), tiny = window.tinymce || window.tinyMCE;
		if (!tiny) return false;
		for (const area of areas) {
			if (tiny.get(area.id)) continue;
			const options = { ...(MCEoptions || fc_areaEditorOptions.get(area)?.tiny || {}) };
			area.classList.remove('mce_editable', 'fc_hadTinyMCE');
			const wrapper = area.closest('.js-editor-tinymce');
			if (wrapper && window.Joomla?.JoomlaTinyMCE?.setupEditor) {
				Promise.resolve(fc_areaEditorOptions.get(area)?.detached).then(() => {
					if (!tiny.get(area.id)) Joomla.JoomlaTinyMCE.setupEditor(area, Joomla.getOptions('plg_editor_tinymce', {}));
				});
			} else if (mceFieldname && options.joomlaExtButtons && window.Joomla?.JoomlaTinyMCE?.setupEditor) {
				Joomla.JoomlaTinyMCE.setupEditor(area, {tinyMCE: {[mceFieldname]: options}});
			} else if (Number(tiny.majorVersion) >= 4) {
				tiny.init({...options, selector: null, target: area});
			} else {
				tiny.init({...options, mode: 'exact', elements: area.id});
			}
			area.classList.add('mce_editable');
			for (const container of area.parentElement?.children || []) {
				if (container.classList.contains('mce-container')) container.style.removeProperty('display');
			}
		}
		return areas.length === 1 ? tiny.get(areas[0].id) : true;
	}

	function fc_attachCodeMirror(txtareas, CMoptions)
	{
		const areas = fc_editorAreas(txtareas);
		if (!window.CodeMirror) return false;
		let editor;
		for (const area of areas) {
			// Joomla 6 owns the lifecycle of its CodeMirror custom element.
			if (area.closest('joomla-editor-codemirror')) continue;
			const options = CMoptions || fc_areaEditorOptions.get(area)?.code || {
				mode: 'application/x-httpd-php', indentUnit: 2, lineNumbers: true,
				matchBrackets: true, lineWrapping: true, styleActiveLine: true
			};
			editor = area.nextElementSibling?.CodeMirror || CodeMirror.fromTextArea(area, {...options});
			editor.refresh();
			area.classList.remove('fc_hadCodeMirror');
		}
		return areas.length === 1 ? editor : true;
	}


	/* Toggle box via a button and set CSS class to indicate that it is open  */
	function fc_toggle_box_via_btn(target, btn, buttonClass, alternate, mode, cookie)
	{
		const boxes = typeof target === 'string' ? [document.getElementById(target)]
			: target?.nodeType ? [target] : Array.from(target || []);
		const button = FlexiForm.element(btn);
		const alternateButton = FlexiForm.element(alternate);
		if (alternateButton) {
			alternateButton.style.removeProperty('display');
			FlexiForm.show(button, false);
		}
		const shown = mode === undefined ? boxes.some(box => !FlexiForm.visible(box)) : Boolean(parseInt(mode, 10));
		if (cookie) fclib_setCookie(typeof cookie === 'string' ? cookie : (boxes[0]?.id || target) + '-disp', shown ? 1 : 0, 0);
		boxes.forEach(box => FlexiForm.show(box, shown));
		for (const name of (buttonClass || '').split(/\s+/).filter(Boolean)) button?.classList.toggle(name, shown);
		button?.setAttribute('aria-expanded', String(shown));
	}


	/* Disable/enable a check box group e.g. when 'Use Global' option is toggled */
	function fc_toggle_checkbox_group(containerID, useGlobalElement)
	{
		const container = document.getElementById(containerID);
		const control = FlexiForm.element(useGlobalElement);
		if (!container || !control) return;
		for (const input of container.querySelectorAll('input')) {
			input.disabled = input !== control && control.checked;
		}
		control.disabled = false;
	}


	/* Auto-resize the currently open dialog vertically or horizontally */
	function fc_dialog_resize_now(boxWsel, boxHsel, contentSel)
	{
		boxWsel = typeof boxWsel != 'undefined' ? boxWsel : '.ui-dialog.fc-autow-dialog';
		boxHsel = typeof boxHsel != 'undefined' ? boxHsel : '.ui-dialog.fc-autoh-dialog';
		contentSel = typeof contentSel != 'undefined' ? contentSel : '.ui-dialog-content';

		// Resize the modal(s)
		var dialogs = jQuery(boxWsel + ' , '+ boxHsel);
		dialogs.each(function( index )
		{
			var dialog_box = jQuery(this);
			var theDialog = dialog_box.data('fc_dialog');
			if (theDialog)
			{
				var params = theDialog.data('fc_dialog_params');
				var maxwidth  = theDialog.data('fc_dialog_maxwidth');
				var maxheight = theDialog.data('fc_dialog_maxheight');
				params = params || {};
				maxwidth  = maxwidth || 0;
				maxheight = maxheight || 0;
				params = fc_getAutoSizePos(maxwidth, maxheight, params);

				dialog_box
					.css({ 'left': params.winleft+'px', 'width': params.winwidth+'px' })
					.css({ 'top': params.wintop+'px', 'height': params.winheight+'px' });
			}
		});

		// Parameters for modal that should take full window
		var dialog_box_sbox = jQuery('#sbox-window');
		var dialog_box_jmedia = jQuery('div.field-media-wrapper > div.modal');

		if (dialog_box_sbox.length || dialog_box_jmedia.length)
		{
			params = fc_getAutoSizePos(0, 0, {});

			// Maximize sbox modal
			if (dialog_box_sbox.length)
			{
				dialog_box_sbox
					.css({ 'position': 'fixed' })
					.css({ 'left': params.winleft+'px', 'width': (params.winwidth-40)+'px' })
					.css({ 'top': params.wintop+'px', 'height': (params.winheight-20)+'px' })
					.find('#sbox-content').children('iframe')
						.removeAttr('width').removeAttr('height').css({ 'width': '100%', 'height': '100%' });
			}

			// Maximize joomla media modal
			if (dialog_box_jmedia.length)
			{
				dialog_box_jmedia.css({ 'top': (params.wintop-30)+'px', 'height': (params.winheight-60)+'px' });
			}
		}

		// Fix modal's inner container adding unneeded scrollbar
		var dialogs = jQuery(boxHsel);
		dialogs.each(function( index )
		{
			var dialog_box = jQuery(this);
			var content_box = dialog_box.find(contentSel);
			var h = dialog_box.height() - content_box.prev().outerHeight(true) - content_box.next().outerHeight(true);
			content_box.css({ 'height': h+'px', 'margin': '0', 'box-sizing': 'border-box' });

			// Remove unneeded scroll bar inside iframe document, new browser security disallows this ?
			//var body = content_box.find('iframe').contents().find('body');
			//body.css({ 'height': 'unset' });
		});
	}


	/* Debounced function execution by the given 'wait' time, forcing single function execution within the given 'wait' time */
	function fc_debounce_exec(func, wait, immediate, func_context)
	{
		var timeout;
		return function() {
			var context = func_context || this, args = arguments;
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

	var fc_dialog_resize = fc_debounce_exec(fc_dialog_resize_now, 200, false, null);

	if (typeof jQuery !== 'undefined') {
		jQuery(window).resize(function() {
			fc_dialog_resize();
		});
	}


	/* Destroy select library (select2 or choices.js) from cloned elements for reinit */
	function fc_destroySelectLib(container)
	{
		const box = FlexiForm.element(container);
		if (!box) return;
		const selects = box.querySelectorAll('select.use_select2_lib, select.has_select2_lib');
		for (const select of selects) {
			const key = select.id || select.name;
			const registered = window.fc_choices_instances[key];
			// A cloned select may have the source's ID, but never owns its instance.
			const instance = select.fc_choices_instance || FlexiForm.data(select, 'fc_choices_instance')
				|| (registered?.passedElement?.element === select ? registered : null);
			if (instance && typeof instance.destroy === 'function') {
				instance.destroy();
				delete select.fc_choices_instance;
				window.jQuery?.(select).removeData('fc_choices_instance');
				if (registered === instance) delete window.fc_choices_instances[key];
			} else {
				select.closest('div.choices')?.replaceWith(select);
				select.removeAttribute('data-choice');
				select.removeAttribute('tabindex');
				select.removeAttribute('data-choice-orig-style');
				select.removeAttribute('style');
				select.hidden = false;
				select.classList.remove('choices__input');
			}
			FlexiForm.show(select);
		}
	}


	/* Apply select library (select2 or choices.js) to targeted elements */
	// Accepts a string selector, a DOM element, a Document, or a jQuery object as container.
	function fc_normalizeContainer(sel)
	{
		if (typeof sel === 'undefined' || !sel) return document;
		if (sel.nodeType === 1 || sel.nodeType === 9) return sel;           // Element or Document
		if (typeof sel.jquery !== 'undefined' && sel.length) return sel[0];  // jQuery object
		if (typeof sel === 'string') { var found = document.querySelector(sel); return found || document.body; }
		return document.body;
	}

	// Normalize targets (jQuery set, NodeList, array, single element) to a real array of <select>.
	function fc_normalizeTargets(sbox, s2_elems)
	{
		var out = [];
		if (s2_elems)
		{
			if (typeof s2_elems.jquery !== 'undefined' || typeof s2_elems.length !== 'undefined')
			{
				for (var i = 0; i < s2_elems.length; i++) { if (s2_elems[i]) out.push(s2_elems[i]); }
			}
			else out.push(s2_elems);
		}
		else
		{
			out = Array.prototype.slice.call(sbox.querySelectorAll('select.use_select2_lib'));
		}
		return out;
	}

	function fc_attachSelect2(sel, s2_elems)
	{
		var sbox = fc_normalizeContainer(sel);
		var targets = fc_normalizeTargets(sbox, s2_elems);

		if (window.skip_select2_js)
		{
			for (var i = 0; i < targets.length; i++)
			{
				targets[i].classList.remove('use_select2_lib');
				targets[i].classList.add('fc_isselect', 'fc_ismobile', 'fc_no_js_attach');
			}
			var labels = sbox.querySelectorAll ? sbox.querySelectorAll('.fc_mobile_label') : [];
			for (var j = 0; j < labels.length; j++) labels[j].style.display = '';
			return;
		}

		fc_attachChoices(sbox, targets);
	}


	/* ===========================
	   CHOICES.JS IMPLEMENTATION
	   =========================== */

	// Store Choices.js instances for later access (destroy, etc.)
	window.fc_choices_instances = window.fc_choices_instances || {};

	function fc_attachChoices(sbox, s2_elems)
	{
		for (var n = 0; n < s2_elems.length; n++) fc_initChoicesFor(sbox, s2_elems[n]);

		// Per-element scope: 'var' declared inside a loop is function-scoped, so every variable
		// captured by a deferred callback (el, choicesInstance, containers, observers, timers)
		// was shared between all iterations and always resolved to the LAST processed <select>.
		// A dedicated function gives each <select> its own scope.
		function fc_initChoicesFor(sbox, el)
		{
			var isMultiple = el.hasAttribute('multiple');
			var isRemoteSearch = isMultiple && !!el.getAttribute('data-fc_choices_search_remote');
			var isNoSelect = el.classList.contains('fc_select2_noselect') && !isRemoteSearch;
			var isCheckbox = isMultiple && !el.classList.contains('fc_select2_no_check');
			var isSortable = el.classList.contains('fc_select2_sortable');

			// Build Choices.js options
			var choicesOptions = {
				searchEnabled: !isNoSelect,
				searchFloor: 1,
				searchChoices: true,
				searchResultLimit: 100,
				shouldSort: false,
				itemSelectText: '',
				removeItemButton: isMultiple,
				noResultsText: Joomla.JText._('FLEXI_NO_RESULTS'),
				noChoicesText: Joomla.JText._('FLEXI_NO_CHOICES'),
				classNames: {
					containerInner: 'choices__inner',
					input: 'choices__input',
					containerOuter: 'choices',
					dropdown: 'choices__dropdown',
					item: 'choices__item',
					list: 'choices__list',
					listItem: 'choices__list-item',
					choice: 'choices__choice',
					selectedState: 'is-selected',
					highlightedState: 'is-highlighted',
					disabledState: 'is-disabled',
					flippedState: 'is-flipped',
					activeState: 'is-active',
					openState: 'is-open',
					focusState: 'is-focused',
					placeholder: 'is-placeholder'
				},
				callbackOnCreateTemplates: function(template) {
					var customTemplates = {};
					// Already selected values are listed by Choices.js while searching, hide them and make them non-navigable
					if (isMultiple && !isCheckbox && el.classList.contains('fc_choices_keep_search')) {
						customTemplates.choice = function(config, data) {
							var tpl = document.createElement('div');
							tpl.classList.add('choices__item', 'choices__item--choice');
							tpl.dataset.choice = '';
							tpl.dataset.id = data.id;
							tpl.dataset.value = data.value;
							tpl.setAttribute('role', 'option');
							if (data.elementId) tpl.id = data.elementId;
							if (data.selected || data.disabled) {
								tpl.classList.add('is-disabled');
								tpl.dataset.choiceDisabled = '';
								tpl.setAttribute('aria-disabled', 'true');
								if (data.selected) tpl.style.display = 'none';
							} else {
								tpl.classList.add('choices__item--selectable');
								tpl.dataset.choiceSelectable = '';
							}
							tpl.textContent = data.label;
							return tpl;
						};
					}
					if (isCheckbox) {
						customTemplates.choice = function(config, data) {
							var tpl = document.createElement('div');
							tpl.classList.add('choices__item', 'choices__item--choice', 'fc-choices-checkbox-item');
							if (data.disabled) tpl.classList.add('is-disabled');
							if (data.selected) tpl.classList.add('is-selected');
							tpl.dataset.choice = '';
							tpl.dataset.id = data.id;
							tpl.dataset.value = data.value;
							if (data.disabled) {
								tpl.dataset.choiceDisabled = '';
							} else {
								tpl.dataset.choiceSelectable = '';
							}
							var checkbox = document.createElement('input');
							checkbox.type = 'checkbox';
							checkbox.checked = data.selected;
							checkbox.disabled = data.disabled;
							checkbox.classList.add('fc-choices-checkbox');
							checkbox.setAttribute('data-value', data.value);
							var label = document.createElement('label');
							label.classList.add('fc-choices-checkbox-label');
							label.textContent = data.label;
							tpl.appendChild(checkbox);

							if (data.labelDescription) {
								// Category path (see fc_cat_path): title and path in a column
								var textBox = document.createElement('span');
								textBox.className = 'fc-choices-checkbox-textbox';
								textBox.appendChild(label);
								var descEl = document.createElement('small');
								descEl.className = 'choices__description';
								descEl.textContent = data.labelDescription;
								textBox.appendChild(descEl);
								tpl.appendChild(textBox);
							} else {
								tpl.appendChild(label);
							}
							return tpl;
						};
					}
					return customTemplates;
				}
			};

			// Category selects with full path (see flexicontent_cats::buildcatselect): list shows the parents path of each category, search matches words of
			// the whole path (words in any order, e.g. 'windows browsers'), selected value is shown with its path
			if (el.classList.contains('fc_cat_path'))
			{
				choicesOptions.searchFields = ['customProperties.search'];
				choicesOptions.fuseOptions = { includeScore: true, ignoreLocation: true, threshold: 0, useExtendedSearch: true };
			}

			if (!isMultiple && el.classList.contains('fc_cat_path'))
			{
				var fcCatPathBaseTemplates = choicesOptions.callbackOnCreateTemplates;
				choicesOptions.callbackOnCreateTemplates = function(template) {
					var customTemplates = fcCatPathBaseTemplates ? fcCatPathBaseTemplates.apply(this, arguments) : {};

					customTemplates.item = function(config, choice) {
						// Choices.js 11 passes its config, 9 and 10 (e.g. Joomla's own copy) pass the class names
						var cn = config.classNames || config;
						var div = document.createElement('div');
						div.classList.add(cn.item);

						var path = choice.labelDescription || '';
						var title = String(choice.label).replace(/^(?:\. )*\|_\s*/, '');

						if (path && choice.value !== '')
						{
							// Path can be shortened with ellipsis, the title is always fully shown (single line)
							var spanPath = document.createElement('span');
							spanPath.className = 'fc-catpath-parents';
							spanPath.textContent = path + ' \u203a ';
							div.appendChild(spanPath);
						}

						var spanTitle = document.createElement('span');
						spanTitle.className = 'fc-catpath-title';
						spanTitle.textContent = title;
						div.appendChild(spanTitle);

						div.dataset.item = '';
						div.dataset.id = choice.id;
						div.dataset.value = choice.value;
						div.setAttribute('aria-selected', 'true');
						div.setAttribute('role', 'option');
						if (choice.placeholder) div.classList.add(cn.placeholder);
						div.classList.add(choice.highlighted ? cn.highlightedState : cn.itemSelectable);
						return div;
					};

					return customTemplates;
				};
			}

			// No-select mode: hide dropdown entirely, just display value
			if (isNoSelect) choicesOptions.searchEnabled = false;

			// Placeholder for multiple selects
			if (isMultiple && !isNoSelect) {
				choicesOptions.placeholderValue = el.getAttribute('data-fc_label_text') || el.getAttribute('fc_label_text') || '';
				choicesOptions.placeholder = true;
			}

			// Destroy existing instance if any (only when it belongs to THIS element:
			// cloned selects share the id/name of the original element)
			var registryInst = window.fc_choices_instances[el.id || el.name];
			if (registryInst && registryInst.passedElement && registryInst.passedElement.element === el) {
				registryInst.destroy();
				delete window.fc_choices_instances[el.id || el.name];
			}

			// Let templates and plugins change the options (for example wrap callbackOnCreateTemplates) before the instance is created
			el.dispatchEvent(new CustomEvent('flexicontent:choices-options', { bubbles: true, detail: { options: choicesOptions } }));

			// Initialize Choices.js
			// FLEXIcontent's own Choices.js (see flexicontent_html::loadFramework), even when Joomla's copy replaced window.Choices
			var ChoicesLib = window.fc_Choices || Choices;
			var choicesInstance = new ChoicesLib(el, choicesOptions);

			// Store instance
			var instanceKey = el.id || el.name || ('fc_choices_' + Math.random().toString(36).substr(2, 9));
			window.fc_choices_instances[instanceKey] = choicesInstance;

			// Store instance on the element so destroy/reset can find it even if several
			// selects share the same name attribute (id || name registry collisions)
			el.fc_choices_instance = choicesInstance;

			// Custom hook class (consistency with select2 markup for highlight, etc.)
			if (choicesInstance.containerOuter && choicesInstance.containerOuter.element) {
				choicesInstance.containerOuter.element.classList.add('fc-choices-outer');
			}

			// Make sortable if requested (drag via a visible handle prepended to each tag)
			if (isSortable && typeof Sortable !== 'undefined') {
				var container = choicesInstance.containerOuter && choicesInstance.containerOuter.element;
				if (container) {
					var sortableSel = container.querySelector('.choices__list--multiple') || container.querySelector('.choices__list--dropdown');
					var addSortableHandles = function(list) {
						Array.prototype.forEach.call(list.querySelectorAll('.choices__item'), function(item) {
							if (item.querySelector('.fc-select2-handle')) return;
							var handle = document.createElement('span');
							handle.className = 'fc-select2-handle';
							handle.innerHTML = '&#x2630;';
							item.insertBefore(handle, item.firstChild);
						});
					};
					// Choices handles mousedown in capture mode and re-renders a clicked item.
					// Leave handle presses to Sortable so its dragged node stays attached.
					var choicesMouseDown = choicesInstance._onMouseDown;
					if (typeof choicesMouseDown === 'function') {
						container.removeEventListener('mousedown', choicesMouseDown, true);
						choicesInstance._onMouseDown = function(event) {
							if (event.target && event.target.closest && event.target.closest('.fc-select2-handle')) return;
							return choicesMouseDown(event);
						};
						container.addEventListener('mousedown', choicesInstance._onMouseDown, true);
					}
					addSortableHandles(sortableSel);
					Sortable.create(sortableSel, {
						animation: 150,
						handle: '.fc-select2-handle',
						forceFallback: true,
						ghostClass: 'fc-choices-ghost',
						dragClass: 'fc-choices-drag',
						onEnd: function(evt) {
							var list = evt.from;
							var items = list.querySelectorAll('.choices__item');
							var select = el;
							var fragment = document.createDocumentFragment();
							Array.prototype.forEach.call(items, function(item) {
								var value = item.getAttribute('data-value') || (item.querySelector('[data-value]') && item.querySelector('[data-value]').getAttribute('data-value'));
								if (value) {
									var option = select.querySelector('option[value="' + value + '"]');
									if (option) fragment.appendChild(option);
								}
							});
							select.insertBefore(fragment, select.firstChild);
						}
					});
					// Add handle to items added after initial render (e.g. remote search selections)
					new MutationObserver(function(mutations) {
						mutations.forEach(function(mutation) {
							if (mutation.addedNodes && mutation.addedNodes.length) addSortableHandles(sortableSel);
						});
					}).observe(sortableSel, { childList: true, subtree: true });
				}
			}

			// Remote search mode (e.g. relation fields): fetch options from AJAX on input
			if (isRemoteSearch && typeof window.fc_fetch_relation_choices === 'function') {
				var remoteContainer = choicesInstance.containerOuter && choicesInstance.containerOuter.element;
				var searchTimer = null;
				var requestNumber = 0;
				var fetchRemote = function(query) {
					clearTimeout(searchTimer);
					const current = ++requestNumber;
					searchTimer = setTimeout(function() {
						if (!el.isConnected || el.fc_choices_instance !== choicesInstance) return;
						window.fc_fetch_relation_choices(el.id, query, function(options) {
							if (current !== requestNumber || !el.isConnected || el.fc_choices_instance !== choicesInstance) return;
							if (!options) options = [];
							var selectedIds = [];
							Array.prototype.forEach.call(el.options, function(opt) {
								if (opt.selected) selectedIds.push(String(opt.value).split(':')[0]);
							});
							var fresh = options.filter(function(o) {
								return selectedIds.indexOf(String(o.value).split(':')[0]) === -1;
							});
							if (fresh.length) {
								choicesInstance.setChoices(fresh.map(function(o) {
									return { value: String(o.value), label: String(o.label) };
								}), 'value', 'label', true);
							} else {
								choicesInstance.clearChoices();
							}
						});
					}, 250);
				};
				// Debounced search + initial fill when the search input receives focus
				if (remoteContainer) {
					var remoteHandler = function(e) {
						if (e.target && e.target.matches && e.target.matches('input.choices__input')) {
							fetchRemote(e.target.value || '');
						}
					};
					remoteContainer.addEventListener('input', remoteHandler);
					remoteContainer.addEventListener('focusin', remoteHandler);
				}
				el.addEventListener('fc:relation-options', function() {
					if (el.fc_choices_instance === choicesInstance) fetchRemote(choicesInstance.input?.element.value || '');
				});
				fetchRemote('');
			}

			// Set initial data if provided
			if (el.hasAttribute('data-select2-initdata')) {
				var initData = el._fc_initdata;
				if (!initData) {
					try { initData = JSON.parse(el.getAttribute('data-select2-initdata') || '[]'); } catch (eI) { initData = []; }
				}
				if (initData && initData.length) {
					choicesInstance.setChoiceByValue(initData.map(function(item) { return item.id || item.value; }));
				}
			}

			// Keep the search text after picking a value (Choices.js clears it), to allow picking several values of the same search
			if (isMultiple && !isCheckbox && el.classList.contains('fc_choices_keep_search')) {
				var kInpEl = choicesInstance.input && choicesInstance.input.element;
				var kTerm = '';
				if (kInpEl) kInpEl.addEventListener('input', function() { kTerm = kInpEl.value; });
				el.addEventListener('hideDropdown', function() { kTerm = ''; });
				el.addEventListener('addItem', function() {
					var term = kTerm;
					if (!term || !kInpEl) return;
					setTimeout(function() {
						kInpEl.value = term;
						kInpEl.dispatchEvent(new Event('input', { bubbles: true }));
					}, 0);
				});
			}

			// Checkbox mode: keep dropdown open, toggle values on click
			if (isCheckbox) {
				var dropdownEl = choicesInstance.dropdown && choicesInstance.dropdown.element;
				if (dropdownEl) {
					var observer = new MutationObserver(function(mutations) {
						mutations.forEach(function(mutation) {
							if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
								if (el._fc_keep_open) {
									if (!dropdownEl.classList.contains('is-active')) {
										dropdownEl.classList.add('is-active');
										dropdownEl.style.display = 'block';
									}
								}
							}
						});
					});
					observer.observe(dropdownEl, { attributes: true, attributeFilter: ['class'] });
				}

				// Choices.js clears the search text when a choice is selected, remember it and restore it after our sync
				// (arrow keys also clear the input text while the filter stays applied, so track what the user typed)
				var fcSearchTerm = '';
				var fcTypedTerm = '';
				var fcInpEl = choicesInstance.input && choicesInstance.input.element;
				if (fcInpEl) fcInpEl.addEventListener('input', function() { fcTypedTerm = fcInpEl.value; });
				el.addEventListener('hideDropdown', function() { fcTypedTerm = ''; });
				function fcRememberSearch() {
					fcSearchTerm = fcTypedTerm;
				}
				function fcRestoreSearch(hlValue) {
					var term = fcSearchTerm;
					fcSearchTerm = '';
					var inpEl = choicesInstance.input && choicesInstance.input.element;
					setTimeout(function() {
						if (term && inpEl) {
							inpEl.value = term;
							inpEl.dispatchEvent(new Event('input', { bubbles: true }));
						}
						// Keyboard: keep the highlight on the choice that was just toggled, so arrows continue from there
						if (hlValue !== undefined) {
							var outerEl = choicesInstance.containerOuter && choicesInstance.containerOuter.element;
							var items = outerEl ? outerEl.querySelectorAll('.fc-choices-checkbox-item') : [];
							for (var h = 0; h < items.length; h++) {
								var isIt = items[h].dataset.value === hlValue;
								items[h].classList.toggle('is-highlighted', isIt);
								if (isIt && items[h].scrollIntoView) items[h].scrollIntoView({ block: 'nearest' });
							}
						}
					}, 0);
				}
				sbox.addEventListener('mousedown', function(e) {
					var it = e.target && e.target.closest ? e.target.closest('.fc-choices-checkbox-item') : null;
					if (it && choicesInstance.containerOuter && choicesInstance.containerOuter.element
						&& choicesInstance.containerOuter.element.contains(it)) {
						// Choices.js selects the choice on mousedown and re-renders the list (the click target is then detached),
						// so restore the search text right after its handler ran
						fcRememberSearch();
						fcRestoreSearch();
					}
				}, true);

				// Handle checkbox clicks (event delegation on the container)
				sbox.addEventListener('click', function(e) {
					var item = e.target && e.target.closest ? e.target.closest('.fc-choices-checkbox-item') : null;
					if (!item) return;
					// Only handle clicks that belong to THIS select's own Choices widget
					if (choicesInstance.containerOuter && choicesInstance.containerOuter.element
						&& !choicesInstance.containerOuter.element.contains(item)) return;
					var checkbox = item.querySelector('.fc-choices-checkbox');
					if (!checkbox) return;
					var value = checkbox.getAttribute('data-value');
					if (value === '' || value === null) return;
					var isChecked = checkbox.checked;
					var vals = fcGetSelectValues(el);
					if (vals === null || vals === undefined) vals = [];
					if (!Array.isArray(vals)) vals = (String(vals) === '' ? [] : [vals]);
					vals = vals.map(function(v){ return String(v); });
					// The empty value is the placeholder, it must never stay selected
					vals = vals.filter(function(v){ return v !== ''; });
					if (isChecked && vals.indexOf(value) === -1) vals.push(value);
					else if (!isChecked) vals = vals.filter(function(v) { return v !== value; });
					// Update the underlying select (form submission + external listeners)
					fcSetSelectValues(el, vals);
					el.dispatchEvent(new Event('change', { bubbles: true }));
					// Sync Choices.js store so checked values render as tags before saving
					if (choicesInstance.removeActiveItems) choicesInstance.removeActiveItems();
					if (choicesInstance.setChoiceByValue) choicesInstance.setChoiceByValue(vals);
					// Keep dropdown open
					el._fc_keep_open = true;
					setTimeout(function() { el._fc_keep_open = false; }, 100);
					fcRestoreSearch();
				});

				// Keyboard: Enter on the highlighted choice. Choices.js only adds the item (it never removes an already selected one)
				// and does not know about our checkbox markup, so toggle the value and sync the widget like the click handler above
				sbox.addEventListener('keydown', function(e) {
					if (e.key !== 'Enter') return;
					var outer = choicesInstance.containerOuter && choicesInstance.containerOuter.element;
					if (!outer || !outer.contains(e.target) || !choicesInstance.dropdown || !choicesInstance.dropdown.isActive) return;
					var hlItem = outer.querySelector('.fc-choices-checkbox-item.is-highlighted');
					if (!hlItem) return;
					var hlValue = hlItem.dataset.value;
					if (hlValue === '' || hlValue === undefined || hlValue === null) return;
					var wasSelected = hlItem.classList.contains('is-selected');
					el._fc_keep_open = true;
					fcRememberSearch();
					setTimeout(function() {
						var kvals = fcGetSelectValues(el);
						if (kvals === null || kvals === undefined) kvals = [];
						if (!Array.isArray(kvals)) kvals = (String(kvals) === '' ? [] : [kvals]);
						kvals = kvals.map(function(v){ return String(v); }).filter(function(v){ return v !== ''; });
						if (wasSelected) kvals = kvals.filter(function(v){ return v !== hlValue; });
						else if (kvals.indexOf(hlValue) === -1) kvals.push(hlValue);
						fcSetSelectValues(el, kvals);
						el.dispatchEvent(new Event('change', { bubbles: true }));
						if (choicesInstance.removeActiveItems) choicesInstance.removeActiveItems();
						if (choicesInstance.setChoiceByValue) choicesInstance.setChoiceByValue(kvals);
						setTimeout(function() { el._fc_keep_open = false; }, 100);
						fcRestoreSearch(hlValue);
					}, 0);
				}, true);

				// Handle clicks on the remove button of an already selected item (multiple selects):
				// uncheck the value and let the filter be recomputed
				sbox.addEventListener('click', function(e) {
					var rmBtn = e.target && e.target.closest ? e.target.closest('.choices__button') : null;
					if (!rmBtn || !isMultiple) return;
					var rmItem = rmBtn.closest('[data-item]');
					var rmValue = rmItem ? rmItem.getAttribute('data-value') : null;
					if (rmValue === null || rmValue === '') return;
					if (choicesInstance.containerOuter && choicesInstance.containerOuter.element
						&& !choicesInstance.containerOuter.element.contains(rmItem)) return;
					var rmVals = fcGetSelectValues(el);
					if (rmVals === null || rmVals === undefined) rmVals = [];
					if (!Array.isArray(rmVals)) rmVals = (String(rmVals) === '' ? [] : [rmVals]);
					rmVals = rmVals.map(function(v){ return String(v); });
					rmVals = rmVals.filter(function(v){ return v !== '' && v !== rmValue; });
					fcSetSelectValues(el, rmVals);
					// Remove the choice from the Choices.js widget as well
					if (choicesInstance.removeChoice) choicesInstance.removeChoice(rmValue);
					el.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}

			if (el.classList.contains('fc_cat_path') && choicesInstance.containerOuter && choicesInstance.containerOuter.element)
			{
				choicesInstance.containerOuter.element.classList.add('fc-choices-catpath');

				// The dropdown can be wider than the select: keep it inside the viewport (e.g. mobiles, selects at the right side of the page)
				el.addEventListener('showDropdown', function() {
					var dd = choicesInstance.dropdown && choicesInstance.dropdown.element;
					if (!dd) return;
					dd.style.left = '';
					var overflow = dd.getBoundingClientRect().right - (document.documentElement.clientWidth - 8);
					if (overflow > 0) dd.style.left = -overflow + 'px';
				});
			}

			// Highlight active value for single selects
			if (!isMultiple && el.classList.contains('fc_add_highlight')) {
				var val = el.value;
				if (val && val.length) choicesInstance.containerOuter.element.classList.add('fc_highlight');
				else choicesInstance.containerOuter.element.classList.remove('fc_highlight');
			}

			// The instance is created and registered (el.fc_choices_instance): templates and plugins can decorate it
			el.dispatchEvent(new CustomEvent('flexicontent:choices-ready', { bubbles: true, detail: { instance: choicesInstance } }));

			// Listen for change to update highlight
			if (!isMultiple && el.classList.contains('fc_add_highlight')) {
				el.addEventListener('change', function() {
					var v = this.value;
					var inst = this.fc_choices_instance;
					if (inst && inst.containerOuter && inst.containerOuter.element) {
						if (v && v.length) inst.containerOuter.element.classList.add('fc_highlight');
						else inst.containerOuter.element.classList.remove('fc_highlight');
					}
				});
			}
		}

		// Inner labels and prompts for multi-select
		for (var m = 0; m < s2_elems.length; m++)
		{
			var selEl = s2_elems[m];
			var instanceKey2 = selEl.id || selEl.name;
			var instance2 = window.fc_choices_instances[instanceKey2];
			if (!instance2 || !instance2.containerOuter || !instance2.containerOuter.element) continue;

			var containerEl = instance2.containerOuter.element;
			var innerEl = containerEl.querySelector('.choices__inner');
			if (!innerEl) continue;

			// Inner label
			var fc_label_text = selEl.getAttribute('data-fc_label_text') || selEl.getAttribute('fc_label_text');
			if (fc_label_text) {
				var _label = fc_label_text.length >= 30 ? fc_label_text.substring(0, 28) + '...' : fc_label_text;
				var spanLabel = document.createElement('span');
				spanLabel.className = 'fc_has_inner_label fc_has_inner_label_choices';
				spanLabel.textContent = _label;
				if (innerEl.firstChild) innerEl.insertBefore(spanLabel, innerEl.firstChild);
				else innerEl.appendChild(spanLabel);
			}

			// Inner prompt
			var fc_prompt_text = selEl.getAttribute('data-fc_prompt_text') || selEl.getAttribute('fc_prompt_text');
			var promptSpan = null;
			if (fc_prompt_text) {
				var _prompt = fc_prompt_text.length >= 30 ? fc_prompt_text.substring(0, 28) + '...' : fc_prompt_text;
				promptSpan = document.createElement('span');
				promptSpan.className = 'fc_has_inner_prompt fc_has_inner_prompt_choices';
				promptSpan.textContent = _prompt;
				promptSpan.style.display = 'none';
				if (innerEl.firstChild) innerEl.insertBefore(promptSpan, innerEl.firstChild);
				else innerEl.appendChild(promptSpan);
			}

			// Show/hide inner prompt based on input
			var inputs = innerEl.querySelectorAll('.choices__input');
			for (var inp = 0; inp < inputs.length; inp++) {
				inputs[inp].addEventListener('input', function() {
					var siblings = this.parentNode ? this.parentNode.children : [];
					var promptEl = null, labelEl = null;
					for (var sib = 0; sib < siblings.length; sib++) {
						if (siblings[sib] === this) break;
						if (siblings[sib].classList.contains('fc_has_inner_prompt')) promptEl = siblings[sib];
						if (siblings[sib].classList.contains('fc_has_inner_label')) labelEl = siblings[sib];
					}
					if (this.value.length) {
						if (promptEl) promptEl.style.display = 'none';
						if (labelEl) labelEl.style.display = 'none';
					} else {
						if (promptEl) promptEl.style.display = '';
						if (labelEl) labelEl.style.display = '';
					}
				});
			}
		}
	}

	// Read the selected value(s) of a <select> (single -> string, multiple -> array)
	function fcGetSelectValues(sel)
	{
		if (sel.multiple) {
			var out = [];
			for (var i = 0; i < sel.options.length; i++) if (sel.options[i].selected) out.push(sel.options[i].value);
			return out;
		}
		return sel.value;
	}

	// Set the selected value(s) of a <select>
	function fcSetSelectValues(sel, vals)
	{
		vals = Array.isArray(vals) ? vals : [vals];
		for (var i = 0; i < sel.options.length; i++) {
			var v = sel.options[i].value;
			// On a multiple select the empty option is the placeholder: keeping it selected
			// makes select.value empty, which hides the filter from the form processing
			if (sel.multiple && v === '') { sel.options[i].selected = false; continue; }
			sel.options[i].selected = vals.indexOf(v) !== -1;
		}
	}

	/* Preserve legacy modal rel attributes without requiring jQuery. */
	FlexiForm.ready(() => {
		for (const element of document.querySelectorAll('.modal[data-rel]')) element.setAttribute('rel', element.dataset.rel);
	});

	/* Restore saved values, including unchecked boxes and multiple selections. */
	function fc_restore_form_field_values(form, data)
	{
		form = FlexiForm.element(form);
		if (!form?.elements) return;
		const controls = Array.from(form.elements);
		if (!data) form.reset();
		for (const control of controls) {
			if (!control.matches('input, select, textarea') || ['button', 'submit', 'reset', 'file'].includes(control.type)) continue;
			if (data && !Object.prototype.hasOwnProperty.call(data, control.name)) continue;
			if (data) {
				const value = data[control.name];
				const values = (Array.isArray(value) ? value : [value]).map(value => String(value ?? ''));
				if (control.type === 'checkbox' || control.type === 'radio') control.checked = values.includes(control.value);
				else if (control.tagName === 'SELECT' && control.multiple) {
					for (const option of control.options) option.selected = values.includes(option.value);
				} else control.value = value ?? '';
			}
			if (control.fc_choices_instance) {
				const values = Array.from(control.selectedOptions, option => option.value);
				control.fc_choices_instance.removeActiveItems();
				control.fc_choices_instance.setChoiceByValue(values);
			}
			control.dispatchEvent(new Event('change', {bubbles: true}));
		}
	}


	if (typeof jQuery !== 'undefined' && typeof jQuery.fn.serializeObject == "undefined")
	{
		jQuery.fn.serializeObject = function()
		{
			var o = {};
			var a = this.serializeArray();
			jQuery.each(a, function() {
				if (o[this.name]) {
					if (!o[this.name].push) {
						o[this.name] = [o[this.name]];
					}
					o[this.name].push(this.value || '');
				} else {
					o[this.name] = this.value || '';
				}
			});
			return o;
		}
	}


	if (typeof fc_isIE == "undefined")
	{
		window.fc_ie_version_number = null;
		fc_isIE = function()
		{
			if (window.fc_ie_version_number === null)
			{
				var userAgent = navigator.userAgent.toLowerCase();
				window.fc_ie_version_number = (userAgent.indexOf('msie') != -1) ? parseInt(userAgent.split('msie')[1]) : false;
			}
			return window.fc_ie_version_number;
		}
	}


	window.fc_has_flash_addon_enabled = false;
	fc_has_flash_addon = function()
	{
		try {
			window.fc_has_flash_addon_enabled = Boolean(new ActiveXObject("ShockwaveFlash.ShockwaveFlash"));
		} catch(exception) {
			window.fc_has_flash_addon_enabled = ("undefined" != typeof navigator.mimeTypes["application/x-shockwave-flash"]);
		}
		return window.fc_has_flash_addon_enabled;
	}


	function fc_remove_non_alphanum_chars(text, allowed_chars)
	{
		var result = '';
		if (!text) return result;
		allowed_chars = typeof allowed == 'undefined' ? '' : allowed_chars;

		var num_reg = new RegExp('[0-9]+$');
		var allowed_reg = new RegExp('['+allowed_chars+']+$');

		var lower = text.toLowerCase();
		var upper = text.toUpperCase();

		for (var i = 0; i < lower.length; ++i)
		{
			if (num_reg.test(text[i]) || (lower[i] != upper[i]) || (lower[i].trim() === ''))
			{
				result += text[i];
			}
			else if (allowed_chars.length && allowed_reg.test(text[i]))
			{
				result += text[i];
			}
		}
		return result;
	}


	function fc_attachSingleSlider(cfg, type = 2)
	{
		const id = cfg.name + (cfg.sfx || '');
		const slider = document.getElementById(id + '_nouislider');
		const select = document.getElementById(id + '-sel');
		const input = document.getElementById(id + '-val');
		const steps = cfg.step_values || [];
		if (!input || !steps.length) return;
		const initial = Math.max(0, Math.min(Number(cfg.initial_pos) || 0, steps.length - 1));
		function apply(value) {
			for (const [selector, classes, prefix] of [
				[cfg.element_selector, cfg.element_class_list, cfg.element_class_prefix],
				[cfg.elem_container_selector, cfg.elem_container_class, cfg.elem_container_prefix],
			]) {
				if (!selector || !classes) continue;
				document.querySelectorAll(selector).forEach(node => {
					node.classList.remove(...classes.trim().split(/\s+/).filter(Boolean));
					if (prefix !== undefined) node.classList.add(prefix + value);
				});
			}
			input.value = value;
			fclib_setCookie(input.name, value, 0);
		}
		if (type == 1 || !slider || !window.noUiSlider || steps.length === 1) {
			if (!select || select.dataset.fcSliderReady) return;
			select.dataset.fcSliderReady = '1';
			select.replaceChildren(...steps.map((value, index) => new Option(cfg.step_labels?.[index] ?? value, value)));
			select.value = steps[initial];
			select.addEventListener('change', () => apply(select.value));
			select.style.removeProperty('display');
			return;
		}
		if (slider.noUiSlider) return;
		slider.style.display = cfg.start_hidden ? 'none' : '';
		noUiSlider.create(slider, {start: initial, connect: false, step: 1, range:{min:0, max:steps.length - 1}});
		const handle = slider.querySelector('.noUi-handle');
		const tip = document.createElement('span');
		tip.className = 'fc-sliderTooltip fc-bottom';
		const label = document.createElement('span');
		tip.append(label);
		handle?.append(tip);
		slider.noUiSlider.on('update', values => {
			const index = Math.round(Number(values[0]));
			input.value = steps[index];
			const text = String(cfg.step_labels?.[index] ?? steps[index]);
			label.textContent = text.length > 40 ? text.slice(0, 36) + ' ?' : text;
			tip.classList.toggle('fc-left', index * 2 >= steps.length - 1);
			tip.classList.toggle('fc-right', index * 2 < steps.length - 1);
		});
		slider.noUiSlider.on('change', () => apply(input.value));
		input.addEventListener('change', () => {
			const index = steps.findIndex(value => String(value) === input.value);
			slider.noUiSlider.set(index < 0 ? 0 : index);
			apply(input.value);
		});
	}


	// Toggle elements of all other views when switching between views (e.g. details / thumbnails views)
	function fc_toggle_view_mode(value)
	{
		const button = FlexiForm.element(value);
		if (!button?.parentElement) return;
		for (const tab of button.parentElement.children) {
			const selected = tab === button;
			tab.classList.toggle('active', selected);
			if (tab.dataset.toggle_selector) {
				document.querySelectorAll(tab.dataset.toggle_selector).forEach(node => {
					node.style.display = selected ? '' : 'none';
					if (selected) node.style.removeProperty('visibility');
				});
			}
		}
	}


	// Create a sanitized filename similar to what server-side code will accept
	fc_sanitize_filename = function(text)
	{
		if (!text) return '';

		var validChars = new RegExp('[A-Za-z0-9\.\_\-]+');
		var result = '';

		for (var i = 0; i < text.length; ++i)
		{
			if (validChars.test(text[i]))
			{
				result += text[i];
			}
		}
		return result;
	}



	// Submit a form in the parent window from a dialog.
	fc_parent_form_submit = function(container_id, form_id, fields, options)
	{
		var form = window.parent.document.getElementById(form_id);
		var task = typeof options.task !== 'undefined'  ?  options.task  :  false;
		var is_list = typeof options.is_list !== 'undefined'  ?  options.is_list  :  false;
		var is_jform = typeof options.is_jform !== 'undefined'  ?  options.is_jform  :  false;

		for (var field_name in fields)
		{
			form.elements[field_name].value = fields[field_name];
		}

		if (is_list && form.boxchecked.value==0)
		{
			window.parent.FlexiForm.alert(FlexiForm.text('FLEXI_NO_ITEMS_SELECTED'));
			return;
		}

		// ***
		// *** Submit progress bar
		// ***

		if (typeof form.progressbar_submit_handler_success === 'undefined' || !form.progressbar_submit_handler_success)
		{
			form.progressbar_submit_handler_success = true;
			// Default to doing animation
			if (typeof form.doanimated_submit === 'undefined') form.doanimated_submit = 1;

			form.addEventListener('submit', function(e)
			{
				if ( !form.doanimated_submit )
				{
					return true;
				}
				//form.doanimated_submit = 0;  // Clear FLAG until next time Joomla.submitform() is called

				window.parent.fc_showSubmitProgress('FLEXI_LOADING', form.ownerDocument);
			});
		}

		// Close modal to allow click events to work on the parent window 's form
		fc_closeDialog(container_id);

		// If it is a Joomla form
		if (is_jform)
		{
			window.parent.Joomla.submitbutton(task);
			return;
		}

		// For proper submit, append a hidden submit button to the form and click it, then remove it if submit was prevented
		var button = document.createElement('input');
		button.style.display = 'none';
		button.type = 'submit';
		form.appendChild(button).click();
		form.removeChild(button);
	}



	//*******************************
	//*** START OF fc_init*() methods
	//*******************************


	/*
	 * Initialize CSS styles and effects
	 */
	function fc_initDynamicLayoutJsCss(container_id, reAddOnEvents, html)
	{
		const container_sel = '#' + container_id;
		document.getElementById(container_id).innerHTML = html;

		tabberAutomatic(tabberOptions, container_id);
		fc_bindFormDependencies(container_sel, 0, '');

		fc_initBootstrap(null, container_sel, reAddOnEvents);
		fc_initTooltips(null, container_sel, reAddOnEvents);
		fc_initCodeMirror(null, container_sel, reAddOnEvents);
		fc_initMinicolors(null, container_sel, reAddOnEvents);
		fc_initSubform(null, container_sel);

		if (typeof(fcrecord_attach_sortable) == 'function') fcrecord_attach_sortable(container_sel);
		if (typeof(fcfield_attach_sortable) == 'function')  fcfield_attach_sortable(container_sel);

		fc_initSelect2(null, container_sel, reAddOnEvents);
		window.fc_initColorInputs?.(document.getElementById(container_id));
		document.getElementById(container_id).dispatchEvent(new CustomEvent('joomla:updated', {bubbles: true}));
	}


	/*
	 * Initialize bootstrap styles and effects
	 */
	const fc_initBindings = new WeakMap();
	function fc_initContainer(event, selector, events, initializer)
	{
		const box = fc_normalizeContainer(selector || event?.detail?.row || event?.target);
		if (!fc_initBindings.has(box)) fc_initBindings.set(box, new Map());
		const bindings = fc_initBindings.get(box);
		(events || []).forEach(name => {
			const key = name + ':' + initializer.name;
			if (bindings.has(key)) return;
			const listener = event => initializer(event, event.detail?.row || event.target);
			box.addEventListener(name, listener);
			bindings.set(key, listener);
		});
		return box;
	}

	function fc_initBootstrap(event, container_sel, reAddOnEvents)
	{
		fc_bootstrapAttach(fc_initContainer(event, container_sel, reAddOnEvents, fc_initBootstrap));
	}

	const fc_openHelp = new Map();
	let fc_touchHelp = false, fc_touchHelpOwner = null, fc_touchHelpWasOpen = false;
	['Tooltip', 'Popover'].forEach(name => {
		const type = name.toLowerCase();
		document.addEventListener('show.bs.' + type, event => {
			if (!event.target.matches('.hasTooltip, .hasPopover')) return;
			// A touch-induced mouseenter can arrive after focus has scrolled the
			// form. Only the help control actually tapped may open in that case.
			if (fc_touchHelp && event.target !== fc_touchHelpOwner && document.activeElement !== event.target) {
				event.preventDefault();
				return;
			}
			fc_openHelp.set(event.target, name);
		});
		document.addEventListener('hidden.bs.' + type, event => fc_openHelp.delete(event.target));
	});
	function fc_dismissHelp(event)
	{
		fc_openHelp.forEach((name, owner) => {
			const popup = document.getElementById(owner.getAttribute('aria-describedby'));
			if (event.type === 'keydown' || (!owner.contains(event.target) && !popup?.contains(event.target))) {
				window.bootstrap?.[name]?.getInstance(owner)?.hide();
				fc_openHelp.delete(owner);
			}
		});
	}
	// Touch generates hover without a corresponding mouseleave on many devices.
	// Dismiss help on an outside tap, focus move or Escape, including cloned fields.
	document.addEventListener('pointerdown', event => {
		fc_touchHelp = event.pointerType === 'touch';
		fc_touchHelpOwner = fc_touchHelp ? event.target.closest('.hasTooltip, .hasPopover') : null;
		fc_touchHelpWasOpen = fc_openHelp.has(fc_touchHelpOwner);
		fc_dismissHelp(event);
	}, true);
	document.addEventListener('pointerup', event => {
		if (event.pointerType === 'touch' && fc_touchHelpOwner?.contains(event.target)) {
			const name = fc_touchHelpOwner.matches('.hasPopover') ? 'Popover' : 'Tooltip';
			const instance = window.bootstrap?.[name]?.getInstance(fc_touchHelpOwner);
			if (fc_touchHelpWasOpen) instance?.hide(); else instance?.show();
		}
	});
	document.addEventListener('click', fc_dismissHelp);
	document.addEventListener('focusin', fc_dismissHelp);
	document.addEventListener('keydown', event => { if (event.key === 'Escape') fc_dismissHelp(event); });

	function fc_initTooltips(event, container_sel, reAddOnEvents)
	{
		const box = fc_initContainer(event, container_sel, reAddOnEvents, fc_initTooltips);
		[['Tooltip', '.hasTooltip'], ['Popover', '.hasPopover']].forEach(([name, selector]) => {
			const options = {html: true, container: box, trigger: 'hover focus'};
			box.querySelectorAll(selector).forEach(el => {
				const Native = window.bootstrap?.[name];
				if (Native) Native.getOrCreateInstance(el, options);
				else if (window.jQuery?.fn[name.toLowerCase()]) window.jQuery(el)[name.toLowerCase()](options);
			});
		});
	}

	function fc_initSelect2(event, container_sel, reAddOnEvents)
	{
		const box = fc_initContainer(event, container_sel, reAddOnEvents, fc_initSelect2);
		if (!(window.fc_Choices || window.Choices)) return;
		fc_attachSelect2(box, box.querySelectorAll('select.use_select2_lib'));
	}

	function fc_initCodeMirror(event, container_sel, reAddOnEvents)
	{
		const box = fc_initContainer(event, container_sel, reAddOnEvents, fc_initCodeMirror);
		if (!window.Joomla?.editors || !window.CodeMirror?.fromTextArea) return;
		box.querySelectorAll('textarea.codemirror-source').forEach(input => {
			input.classList.remove('codemirror-source');
			Joomla.editors.instances[input.id] = CodeMirror.fromTextArea(input, FlexiForm.data(input, 'options'));
		});
	}

	function fc_initSubform(event, container_sel)
	{
		// Joomla's current joomla-field-subform element initializes itself. Older layouts
		// may still request the legacy plugin while it remains installed.
		const box = fc_initContainer(event, container_sel, [], fc_initSubform);
		if (window.jQuery?.fn.subformRepeatable) window.jQuery(box.querySelectorAll('div.subform-repeatable')).subformRepeatable();
	}

	function fc_initMinicolors(event, container_sel, reAddOnEvents)
	{
		const box = fc_initContainer(event, container_sel, reAddOnEvents, fc_initMinicolors);
		if (!window.jQuery?.fn.minicolors) return;
		box.querySelectorAll('.minicolors').forEach(input => {
			const data = key => FlexiForm.data(input, key);
			const format = data('validate') === 'color' ? 'hex' : data('format') || 'hex';
			window.jQuery(input).minicolors({control: data('control') || 'hue', format: format === 'rgba' ? 'rgb' : format,
				keywords: data('keywords') || '', opacity: format === 'rgba', position: data('position') || 'default', theme: data('theme') || 'bootstrap'});
		});
	}


	//*****************************
	//*** END OF fc_init*() methods
	//*****************************



	/*
	 * Allow custom input in date fields having the custom text class
	 */
	document.addEventListener('DOMContentLoaded', function()
	{
		if (typeof JoomlaCalendar != 'undefined')
		{
			var JoomlaCalendar_setAltValue_fc = JoomlaCalendar.prototype.setAltValue;
			JoomlaCalendar.prototype.setAltValue = function()
			{
				if (this.inputField.classList.contains('fc_date_allow_text'))
				{
					return;
				}
				return JoomlaCalendar_setAltValue_fc.call(this, arguments);
			};

			var JoomlaCalendar_init_fc = JoomlaCalendar.init;
			JoomlaCalendar.init = function()
			{
				var result = JoomlaCalendar_init_fc.apply(null, arguments);

				var element = arguments[0];
				var input = element ? element.getElementsByTagName('input')[0] : false;

				if (input)
				{
					if (!!input.id && input.classList.contains('fc_date_allow_text'))
					{
						input.value = document.getElementById(input.id + '_fc_value').getAttribute('data-fc-value');
					}
				}

				return result;
			}
		}
	});
