/**
 * Items manager: 'Items using file' filter
 *
 * A text input that searches files by title / filename on the server while typing (nothing is preloaded),
 * and a hidden input (filter_fileid) holding the selected file id.
 * Setting the hidden input fires a 'change' event, so the usual filter handling applies
 * (reload on change, or the Apply button / click outside when the Modern filters apply mode is used).
 */
(function () {
	'use strict';

	function init(box) {
		var input  = box.querySelector('.fc-filefilter-input');
		var hidden = box.querySelector('input[type="hidden"]');
		var clear  = box.querySelector('.fc-filefilter-clear');
		var list   = box.querySelector('.fc-filefilter-list');
		if (!input || !hidden || !list) return;

		var url = box.getAttribute('data-search-url');
		var noResults = box.getAttribute('data-no-results') || '';
		var timer = null, controller = null, items = [], active = -1;
		var selectedLabel = input.value;

		function closeList() {
			list.hidden = true;
			list.innerHTML = '';
			items = [];
			active = -1;
		}

		function setActive(i) {
			var els = list.querySelectorAll('li[data-id]');
			for (var n = 0; n < els.length; n++) els[n].classList.toggle('is-active', n === i);
			active = i;
		}

		function choose(item) {
			hidden.value = item.id;
			selectedLabel = item.label;
			input.value = item.label;
			if (clear) clear.hidden = false;
			closeList();
			hidden.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function render(results) {
			list.innerHTML = '';
			items = results;
			active = -1;

			if (!results.length) {
				var li = document.createElement('li');
				li.className = 'fc-filefilter-empty';
				li.textContent = noResults;
				list.appendChild(li);
			}

			results.forEach(function (item) {
				var li = document.createElement('li');
				li.setAttribute('data-id', item.id);
				var title = document.createElement('span');
				title.className = 'fc-filefilter-title';
				title.textContent = item.label;
				li.appendChild(title);
				if (item.name && item.name !== item.label) {
					var name = document.createElement('span');
					name.className = 'fc-filefilter-name';
					name.textContent = item.name;
					li.appendChild(name);
				}
				// mousedown (not click): fires before the input loses focus
				li.addEventListener('mousedown', function (e) {
					e.preventDefault();
					choose(item);
				});
				list.appendChild(li);
			});

			list.hidden = false;
		}

		function search(term) {
			if (controller) controller.abort();
			controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;

			fetch(url + '&term=' + encodeURIComponent(term), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (input.value.trim() === term) render(Array.isArray(data) ? data : []);
			})
			.catch(function () { /* aborted or failed: keep the list closed */ });
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var term = input.value.trim();
			if (term.length < 2) { closeList(); return; }
			timer = setTimeout(function () { search(term); }, 250);
		});

		input.addEventListener('keydown', function (e) {
			var count = list.querySelectorAll('li[data-id]').length;
			if (e.key === 'ArrowDown' && count) {
				e.preventDefault();
				setActive((active + 1) % count);
			} else if (e.key === 'ArrowUp' && count) {
				e.preventDefault();
				setActive((active - 1 + count) % count);
			} else if (e.key === 'Enter') {
				// Never submit the form by Enter inside this input, only choose the highlighted result
				e.preventDefault();
				if (active > -1 && items[active]) choose(items[active]);
			} else if (e.key === 'Escape') {
				closeList();
				input.value = selectedLabel;
			}
		});

		input.addEventListener('blur', function () {
			closeList();
			// Typed text that was not chosen is discarded
			input.value = selectedLabel;
		});

		if (clear) {
			clear.addEventListener('click', function () {
				hidden.value = '';
				selectedLabel = '';
				input.value = '';
				clear.hidden = true;
				closeList();
				hidden.dispatchEvent(new Event('change', { bubbles: true }));
			});
		}
	}

	function initAll() {
		var boxes = document.querySelectorAll('.fc-filefilter');
		for (var i = 0; i < boxes.length; i++) init(boxes[i]);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
	else initAll();
})();
