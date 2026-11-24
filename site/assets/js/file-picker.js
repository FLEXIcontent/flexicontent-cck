/* File library shared by the administrator and site field pickers. */
document.addEventListener('DOMContentLoaded', () => {
	const bar = document.querySelector('.fc-picker-bar');
	if (!bar) return;
	const form = bar.closest('form');
	const search = bar.querySelector('#search');
	const owners = bar.querySelectorAll('[data-picker-owner]');
	const ownerSelect = form.elements.filter_uploader;
	const currentOwner = [...owners].find(button => button.getAttribute('aria-pressed') === 'true');
	function setOwner(button) {
		if (![...ownerSelect.options].some(option => option.value === button.dataset.pickerOwner)) {
			ownerSelect.add(new Option(button.textContent, button.dataset.pickerOwner));
		}
		ownerSelect.value = button.dataset.pickerOwner;
	}
	if (ownerSelect && currentOwner) setOwner(currentOwner);

	function submit() {
		if (form.elements.limitstart) form.elements.limitstart.value = 0;
		Joomla.submitform('', form);
	}
	for (const button of owners) {
		button.addEventListener('click', () => {
			if (!ownerSelect || button === currentOwner) return;
			setOwner(button);
			submit();
		});
	}

	bar.querySelector('[data-picker-search]')?.addEventListener('click', submit);
	bar.addEventListener('keydown', event => {
		if (event.key === 'Enter' && event.target.matches('#search, #item_id')) {
			event.preventDefault();
			submit();
		}
	});
	search?.addEventListener('search', () => {
		if (!search.value && search.defaultValue) submit();
	});
	bar.querySelector('[data-picker-reset]')?.addEventListener('click', () => {
		delAllFilters();
		if (ownerSelect && currentOwner) setOwner(currentOwner);
		submit();
	});
	const filters = bar.querySelector('details');
	document.addEventListener('click', event => {
		if (filters?.open && !filters.contains(event.target)) filters.open = false;
	});
	bar.addEventListener('keydown', event => {
		if (event.key === 'Escape' && filters?.open) {
			event.preventDefault();
			event.stopPropagation();
			filters.open = false;
			filters.querySelector('summary').focus();
		}
	});
	for (const field of bar.querySelectorAll('.fc-picker-filter-panel select, .fc-picker-filter-panel input')) {
		const label = field.closest('.fc-filter')?.querySelector('.input-group-text');
		if (label) field.setAttribute('aria-label', label.textContent.trim());
	}
	const sort = bar.querySelector('[data-picker-sort]');
	const dir = bar.querySelector('[data-picker-direction]');
	for (const control of [sort, dir]) {
		control?.addEventListener('change', () => {
			form.elements.filter_order.value = sort.value;
			form.elements.filter_order_Dir.value = dir.value;
			submit();
		});
	}

	const modes = [...bar.querySelectorAll('[data-toggle_selector]')];
	function showMode(button) {
		fc_toggle_view_mode(button);
		for (const mode of modes) mode.setAttribute('aria-pressed', String(mode === button));
	}
	for (const mode of modes) {
		mode.addEventListener('click', () => {
			showMode(mode);
			try { localStorage.setItem('fc.filePicker.view', mode.id); } catch {}
		});
	}
	try {
		const saved = modes.find(mode => mode.id === localStorage.getItem('fc.filePicker.view'));
		if (saved) showMode(saved);
	} catch {}

	const range = bar.querySelector('[data-picker-sizes]');
	const sizes = range.dataset.pickerSizes.split(',');
	range.addEventListener('input', () => {
		const size = sizes[range.value];
		for (const card of form.querySelectorAll('.fc-fileman-grid-thumb-box')) {
			card.classList.remove(...sizes.map(n => 'thumb_' + n));
			card.classList.add('thumb_' + size);
		}
		fclib_setCookie('fc-fileman-grid-thumb-size-val', size, 0);
	});
	const insert = bar.querySelector('#insert_selected_btn');
	const count = bar.querySelector('[data-picker-count]');
	const remove = bar.querySelector('[data-picker-delete]');
	function syncSelection() {
		const selected = form.querySelectorAll('input[name="cid[]"]:checked');
		count.textContent = selected.length ? '(' + selected.length + ')' : '';
		insert.disabled = ![...selected].some(box => box.closest('tr')?.querySelector('.fc_set_file_assignment:not(.striketext)'));
		remove.disabled = selected.length === 0;
	}
	form.addEventListener('change', syncSelection);
	syncSelection();
});
