/**
 * FLEXIcontent dashboard layout editor
 *
 * Lets an administrator reorder / resize / rename the blocks of the dashboard, add or remove their buttons
 * (built-in buttons or custom links) and create new blocks. The layout is saved via AJAX, then the page is reloaded.
 * Data comes from Joomla.getOptions('com_flexicontent.dashboard'): { layout, catalog, icons, url, token }
 */
(function () {
	'use strict';

	var WIDTHS = [[3, '1/4'], [4, '1/3'], [6, '1/2'], [8, '2/3'], [12, '1/1']];

	function T(key) {
		var f = (window.Joomla && Joomla.Text && Joomla.Text._) ? Joomla.Text._ : (Joomla.JText && Joomla.JText._);
		var s = f ? f.call(Joomla.Text || Joomla.JText, key) : '';
		return s || key;
	}

	/** Tiny DOM builder: h('div', {class: 'x', onclick: fn, text: '..'}, child, child ...) */
	function h(tag, props) {
		var node = document.createElement(tag);
		props = props || {};

		Object.keys(props).forEach(function (k) {
			var v = props[k];

			if (v === null || v === undefined || v === false) return;
			if (k === 'class') node.className = v;
			else if (k === 'text') node.textContent = v;
			else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), v);
			else node.setAttribute(k, v === true ? '' : v);
		});

		for (var i = 2; i < arguments.length; i++) {
			var c = arguments[i];
			if (c === null || c === undefined || c === false) continue;
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		}

		return node;
	}

	/** Apply (or remove) the background color of a block card, with a readable text color */
	function applyBg(card, color) {
		if (!/^#[0-9a-f]{6}$/i.test(color || '')) {
			card.classList.remove('fc-dash-colored');
			card.style.removeProperty('--fc-dash-bg');
			card.style.removeProperty('--fc-dash-fg');
			return;
		}

		var r = parseInt(color.substr(1, 2), 16), g = parseInt(color.substr(3, 2), 16), b = parseInt(color.substr(5, 2), 16);
		card.classList.add('fc-dash-colored');
		card.style.setProperty('--fc-dash-bg', color);
		card.style.setProperty('--fc-dash-fg', (0.299 * r + 0.587 * g + 0.114 * b) < 140 ? '#ffffff' : '#212529');
	}

	function uid(prefix) {
		return prefix + Math.random().toString(36).slice(2, 10);
	}

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var opts      = Joomla.getOptions('com_flexicontent.dashboard');
		var container = document.getElementById('fc-dash-boardbtns');
		var toolbar   = document.getElementById('fc-dash-toolbar');
		var openBtn   = document.getElementById('fc-dash-customize');

		if (!opts || !container || !toolbar || !openBtn) return;

		var catalog = opts.catalog || {};
		var model   = JSON.parse(JSON.stringify(opts.layout));

		// Blocks that use the default (translated) title: show it in the editor, it is saved as empty again if not changed
		model.blocks.forEach(function (bl) { if (!bl.title && bl.tt) bl.title = bl.tt; });
		var drag    = null;   // {type: 'block'|'tile', el}
		var dialog  = null;

		openBtn.addEventListener('click', enter);

		// ------------------------------------------------------------------ helpers

		function tileId(b) {
			return b.k === 'custom' ? 'c:' + b.id : 'k:' + b.k;
		}

		function usedKeys() {
			var used = {};
			model.blocks.forEach(function (bl) {
				bl.btns.forEach(function (b) { if (b.k !== 'custom') used[b.k] = 1; });
			});
			return used;
		}

		function post(task, data) {
			var body = new URLSearchParams();
			body.append(opts.token, '1');
			Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });

			return fetch(opts.url + task, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'X-Requested-With': 'XMLHttpRequest'},
				body: body
			}).then(function (r) { return r.json(); });
		}

		function fail(msg) {
			alert(msg ? msg : T('FLEXI_DASH_SAVE_ERROR'));
		}

		/** Layout to send to the server (default titles are stored as empty, so that they follow the language) */
		function payload() {
			return {
				v: 2,
				blocks: model.blocks.map(function (bl) {
					var title = (bl.tk && bl.title === bl.tt) ? '' : bl.title;

					return {
						id: bl.id, tk: bl.tk || '', sys: bl.sys || '', bg: bl.bg || '', title: title, w: bl.w,
						btns: bl.btns.map(function (b) {
							if (b.k === 'custom') {
								return {k: 'custom', id: b.id, t: b.t, u: b.u, i: b.i, g: b.g};
							}
							var def = catalog[b.k] ? catalog[b.k].title : '';
							return {k: b.k, t: (b.t === def ? '' : b.t), na: b.na ? 1 : 0};
						})
					};
				})
			};
		}

		/** Rebuild the model order from the DOM, after a drag and drop */
		function syncFromDom() {
			var btnById = {}, blockById = {};

			model.blocks.forEach(function (bl) {
				blockById[bl.id] = bl;
				bl.btns.forEach(function (b) { btnById[tileId(b)] = b; });
			});

			var blocks = [];

			container.querySelectorAll(':scope > .fc-dashboard-card').forEach(function (card) {
				var bl = blockById[card.getAttribute('data-bid')];
				if (!bl) return;

				bl.btns = [];
				card.querySelectorAll('.fc-dash-tile').forEach(function (tile) {
					var b = btnById[tile.getAttribute('data-tid')];
					if (b) bl.btns.push(b);
				});
				blocks.push(bl);
			});

			model.blocks = blocks;
		}

		// ------------------------------------------------------------------ modes

		function enter() {
			toolbar.innerHTML = '';
			toolbar.classList.add('fc-dash-toolbar-editing');
			var hint = h('p', {class: 'fc-dash-hint', text: T('FLEXI_DASH_HINT')});
			toolbar.appendChild(hint);

			// Export / import of the layout (to reuse the same dashboard on other sites)
			var file = h('input', {type: 'file', accept: '.json,application/json', style: 'display:none'});
			file.addEventListener('change', function () {
				if (file.files.length) importFile(file.files[0], hint);
				file.value = '';
			});
			toolbar.appendChild(file);
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: exportLayout, text: '\u2193 ' + T('FLEXI_DASH_EXPORT')}));
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: function () { file.click(); }, text: '\u2191 ' + T('FLEXI_DASH_IMPORT')}));
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: addBlock, text: '+ ' + T('FLEXI_DASH_ADD_BLOCK')}));
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-outline-danger', onclick: reset, text: T('FLEXI_DASH_RESET')}));
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-secondary', onclick: function () { window.location.reload(); }, text: T('FLEXI_CANCEL')}));
			toolbar.appendChild(h('button', {type: 'button', class: 'btn btn-sm btn-success', id: 'fc-dash-save', onclick: save, text: T('FLEXI_SAVE')}));

			container.classList.add('fc-dash-editing');
			bindContainerDnD();
			document.addEventListener('mouseup', function () {
				container.querySelectorAll(':scope > .fc-dashboard-card').forEach(function (c) { c.draggable = false; });
			});
			render();
		}

		/** Download the current layout (including unsaved changes) as a JSON file */
		function exportLayout() {
			var data = {type: 'flexicontent-dashboard', version: 2, layout: payload()};
			var blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
			var a    = h('a', {href: URL.createObjectURL(blob), download: 'flexicontent-dashboard.json'});

			document.body.appendChild(a);
			a.click();
			a.remove();
			setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
		}

		/** Load a layout from an exported file into the editor (nothing is saved until "Save" is pressed) */
		function importFile(f, hint) {
			var reader = new FileReader();

			reader.onload = function () {
				try {
					var data = JSON.parse(reader.result);
					var src  = data && data.layout ? data.layout : data;

					if (!src || !Array.isArray(src.blocks)) throw new Error('no blocks');

					var factor = (src.v >= 2 || (data.version >= 2)) ? 1 : 2;   // old layouts used a grid of 6 columns
					var defaults = {};   // default titles of the current blocks, by language key
					model.blocks.forEach(function (bl) { if (bl.tk) defaults[bl.tk] = bl.tt; });

					var ids = {}, sysSeen = {}, blocks = [];

					src.blocks.forEach(function (bl) {
						if (!bl || typeof bl !== 'object') return;

						var id = /^[A-Za-z0-9_-]{1,40}$/.test(bl.id || '') && !ids[bl.id] ? bl.id : uid('b');
						ids[id] = 1;

						var sys = (bl.sys === 'waiting' || bl.sys === 'sysinfo') && !sysSeen[bl.sys] ? bl.sys : '';
						if (sys) sysSeen[sys] = 1;

						var w = parseInt(bl.w, 10) * factor;
						var tk = /^FLEXI_[A-Z0-9_]+$/.test(bl.tk || '') ? bl.tk : '';
						var tt = tk ? (defaults[tk] || '') : '';
						var used = {};

						var nb = {
							id: id, tk: tk, tt: tt, sys: sys, bg: /^#[0-9a-f]{6}$/i.test(bl.bg || '') ? bl.bg : '',
							title: String(bl.title || tt).slice(0, 100), w: [3, 4, 6, 8, 12].indexOf(w) !== -1 ? w : 6, btns: []
						};

						(sys ? [] : (Array.isArray(bl.btns) ? bl.btns : [])).forEach(function (b) {
							if (!b || !b.k) return;

							if (b.k === 'custom') {
								if (!b.u) return;
								nb.btns.push({k: 'custom', id: uid('c'), t: String(b.t || b.u).slice(0, 100), u: String(b.u), i: String(b.i || 'icon-link'), g: b.g || 'self'});
							}
							else if (catalog[b.k] && !used[b.k]) {
								used[b.k] = 1;
								nb.btns.push({k: b.k, t: b.t || catalog[b.k].title, na: b.na ? 1 : 0});
							}
						});

						blocks.push(nb);
					});

					// A built-in button can be placed only once on the dashboard
					var seen = {};
					blocks.forEach(function (bl) {
						bl.btns = bl.btns.filter(function (b) {
							if (b.k === 'custom') return true;
							if (seen[b.k]) return false;
							return (seen[b.k] = 1);
						});
					});

					// The system blocks must always exist: keep the current ones if the file has none
					model.blocks.forEach(function (bl) {
						if (bl.sys && !sysSeen[bl.sys]) blocks.push(bl);
					});

					model.blocks = blocks;
					render();
					hint.textContent = T('FLEXI_DASH_IMPORT_OK');
				}
				catch (e) {
					alert(T('FLEXI_DASH_IMPORT_ERROR'));
				}
			};

			reader.readAsText(f);
		}

		function save() {
			var btn = document.getElementById('fc-dash-save');
			btn.disabled = true;

			post('savedashlayout', {dashlayout: JSON.stringify(payload())})
				.then(function (r) {
					if (r && r.success) { window.location.reload(); return; }
					btn.disabled = false;
					fail(r && r.message);
				})
				.catch(function (e) { btn.disabled = false; fail(String(e)); });
		}

		function reset() {
			if (!confirm(T('FLEXI_DASH_RESET_CONFIRM'))) return;

			post('resetdashlayout', {})
				.then(function (r) {
					if (r && r.success) { window.location.reload(); return; }
					fail(r && r.message);
				})
				.catch(function (e) { fail(String(e)); });
		}

		function addBlock() {
			model.blocks.push({id: uid('b'), tk: '', sys: '', bg: '', tt: '', title: T('FLEXI_DASH_NEW_BLOCK'), w: 6, btns: []});
			render();

			var cards = container.querySelectorAll(':scope > .fc-dashboard-card');
			var last  = cards[cards.length - 1];
			last.scrollIntoView({behavior: 'smooth', block: 'center'});
			var input = last.querySelector('.fc-dash-title-input');
			input.focus();
			input.select();
		}

		// ------------------------------------------------------------------ rendering

		function render() {
			container.innerHTML = '';
			model.blocks.forEach(function (bl) { container.appendChild(renderBlock(bl)); });
		}

		function renderBlock(bl) {
			var card = h('fieldset', {class: 'fc-board-set card fc-dashboard-card fc-dash-span-' + bl.w + (bl.sys ? ' fc-dash-sys' : ''), 'data-bid': bl.id});

			var handle = h('span', {class: 'fc-dash-handle', title: '', text: '⋮⋮'});
			handle.addEventListener('mousedown', function () { card.draggable = true; });

			var input = h('input', {
				type: 'text', class: 'fc-dash-title-input', value: bl.title, maxlength: 100, 'aria-label': T('FLEXI_DASH_BLOCK_TITLE'),
				title: T('FLEXI_DASH_BLOCK_TITLE'),
				oninput: function () { bl.title = input.value; },
				onkeydown: function (e) { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } }
			});
			input.value = bl.title;

			var widths = h('div', {class: 'fc-dash-widths', title: T('FLEXI_DASH_WIDTH')});
			WIDTHS.forEach(function (w) {
				var b = h('button', {type: 'button', class: bl.w === w[0] ? 'active' : '', text: w[1]});
				b.addEventListener('click', function () {
					bl.w = w[0];
					card.className = card.className.replace(/fc-dash-span-\d+/, 'fc-dash-span-' + bl.w);
					widths.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
					b.classList.add('active');
				});
				widths.appendChild(b);
			});

			// Background color: a palette icon over a native color input
			var colorInput = h('input', {type: 'color', value: /^#[0-9a-f]{6}$/i.test(bl.bg || '') ? bl.bg : '#ffffff', 'aria-label': T('FLEXI_DASH_BG_COLOR')});
			var reset = h('button', {
				type: 'button', class: 'fc-dash-iconbtn', title: T('FLEXI_DASH_BG_RESET'), text: '\u21ba',
				onclick: function () { bl.bg = ''; applyBg(card, ''); colorInput.value = '#ffffff'; reset.style.display = 'none'; }
			});
			reset.style.display = bl.bg ? '' : 'none';
			colorInput.addEventListener('input', function () {
				bl.bg = colorInput.value;
				applyBg(card, bl.bg);
				reset.style.display = '';
			});

			var palette = h('label', {class: 'fc-dash-colorbtn', title: T('FLEXI_DASH_BG_COLOR')});
			palette.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c1.4 0 2.5-1.1 2.5-2.5 0-.6-.2-1.2-.6-1.6-.4-.4-.6-1-.6-1.6 0-1.4 1.1-2.5 2.5-2.5H18c2.2 0 4-1.8 4-4 0-4.9-4.5-8.8-10-8.8zM6.5 12a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3-4a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm5 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3 4a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>';
			palette.appendChild(colorInput);

			var del = h('button', {
				type: 'button', class: 'fc-dash-iconbtn danger', title: T('FLEXI_DASH_REMOVE'), text: '✕',
				onclick: function () {
					if (bl.btns.length && !confirm(T('FLEXI_DASH_DELETE_BLOCK_CONFIRM'))) return;
					model.blocks.splice(model.blocks.indexOf(bl), 1);
					render();
				}
			});

			var header = h('h3', {class: 'fc-board-header card-header'}, handle, input, widths, palette, reset, bl.sys ? null : del);

			var body;

			if (bl.sys) {
				// System block: no buttons, only a note
				body = h('div', {class: 'fc-board-set-inner card-body'}, h('div', {class: 'fc-dash-sys-note', text: T('FLEXI_DASH_SYSTEM_BLOCK')}));
			}
			else {
				body = h('div', {class: 'fc-board-set-inner card-body fc-dash-body'});
				bl.btns.forEach(function (b) { body.appendChild(renderTile(bl, b)); });
				body.appendChild(h('div', {class: 'fc-dash-empty', text: T('FLEXI_DASH_EMPTY_BLOCK')}));
				body.appendChild(h('button', {
					type: 'button', class: 'btn btn-sm btn-outline-primary fc-dash-addbtn',
					onclick: function () { openDialog(bl, null); }, text: '+ ' + T('FLEXI_DASH_ADD_BUTTON')
				}));
			}

			applyBg(card, bl.bg);

			card.addEventListener('dragstart', function (e) {
				if (e.target !== card) return;
				drag = {type: 'block', el: card};
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', 'block');
				setTimeout(function () { card.classList.add('fc-dash-dragging'); }, 0);
			});
			card.addEventListener('dragend', function () { finishDrag(card); });

			card.appendChild(header);
			card.appendChild(body);

			return card;
		}

		function renderTile(bl, b) {
			var isCustom = b.k === 'custom';
			var cat      = catalog[b.k] || {title: b.k, icon: 'icon-link', plus: false, avail: false};
			var icon     = isCustom ? b.i : cat.icon;
			var unavail  = !isCustom && !cat.avail;

			var title = h('input', {
				type: 'text', class: 'fc-dash-tile-title', maxlength: 100, title: T('FLEXI_DASH_LINK_TITLE'),
				oninput: function () { b.t = title.value; },
				onkeydown: function (e) { if (e.key === 'Enter') { e.preventDefault(); title.blur(); } }
			});
			title.value = b.t || cat.title;
			if (!b.t) b.t = isCustom ? '' : cat.title;

			var tools = h('div', {class: 'fc-dash-tile-tools'});

			if (!isCustom && cat.plus) {
				var plus = h('button', {
					type: 'button', class: 'fc-dash-iconbtn' + (b.na ? '' : ' fc-dash-plus-on'), title: T('FLEXI_DASH_TOGGLE_PLUS'), text: '+',
					onclick: function () { b.na = b.na ? 0 : 1; plus.classList.toggle('fc-dash-plus-on', !b.na); }
				});
				tools.appendChild(plus);
			}

			if (isCustom) {
				tools.appendChild(h('button', {
					type: 'button', class: 'fc-dash-iconbtn', title: T('FLEXI_DASH_EDIT_LINK'), text: '✎',
					onclick: function () { openDialog(bl, b); }
				}));
			}

			tools.appendChild(h('button', {
				type: 'button', class: 'fc-dash-iconbtn danger', title: T('FLEXI_DASH_REMOVE'), text: '✕',
				onclick: function () { bl.btns.splice(bl.btns.indexOf(b), 1); render(); }
			}));

			var tile = h('div', {
				class: 'fc-dash-tile' + (unavail ? ' unavail' : ''), 'data-tid': tileId(b),
				title: unavail ? T('FLEXI_DASH_UNAVAILABLE') : ''
			},
				tools,
				h('span', {class: icon + ' fc-dash-tile-icon', 'aria-hidden': 'true'}),
				title,
				isCustom ? h('span', {class: 'fc-dash-tile-url', text: b.u}) : null
			);

			// Text inputs inside a draggable element can not be edited in some browsers: enable dragging only outside of them
			tile.addEventListener('mousedown', function (e) {
				tile.draggable = !/^(INPUT|BUTTON)$/.test(e.target.tagName);
			});
			tile.addEventListener('dragstart', function (e) {
				e.stopPropagation();
				drag = {type: 'tile', el: tile};
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', 'tile');
				setTimeout(function () { tile.classList.add('fc-dash-dragging'); }, 0);
			});
			tile.addEventListener('dragend', function (e) { e.stopPropagation(); finishDrag(tile); });

			return tile;
		}

		// ------------------------------------------------------------------ drag and drop

		function finishDrag(el) {
			el.classList.remove('fc-dash-dragging');
			el.draggable = false;
			drag = null;
			syncFromDom();
		}

		/** Is the pointer before (left / above) the middle of the element */
		function isBefore(e, target, dragged) {
			var r = target.getBoundingClientRect();
			var d = dragged.getBoundingClientRect();
			var sameRow = r.top < d.bottom && r.bottom > d.top;

			return sameRow ? e.clientX < r.left + r.width / 2 : e.clientY < r.top + r.height / 2;
		}

		function bindContainerDnD() {
			container.addEventListener('dragover', function (e) {
				if (!drag) return;

				if (drag.type === 'block') {
					var card = e.target.closest ? e.target.closest('.fc-dashboard-card') : null;
					if (!card || card === drag.el || card.parentNode !== container) { e.preventDefault(); return; }

					e.preventDefault();
					container.insertBefore(drag.el, isBefore(e, card, drag.el) ? card : card.nextSibling);
					return;
				}

				// Tile: over another tile, or over the body of a block
				var tile = e.target.closest ? e.target.closest('.fc-dash-tile') : null;
				var body = e.target.closest ? e.target.closest('.fc-dash-body') : null;

				if (!body) return;
				e.preventDefault();

				if (tile && tile !== drag.el) {
					tile.parentNode.insertBefore(drag.el, isBefore(e, tile, drag.el) ? tile : tile.nextSibling);
				}
				else if (!tile) {
					// Over empty space of the block: move to the end (or stay, when the pointer is before the last tile)
					var tiles = body.querySelectorAll('.fc-dash-tile:not(.fc-dash-dragging)');
					var last  = tiles[tiles.length - 1];
					var ref   = body.querySelector('.fc-dash-empty');

					if (!last || !isBefore(e, last, drag.el)) body.insertBefore(drag.el, ref);
				}
			});

			container.addEventListener('drop', function (e) {
				if (drag) e.preventDefault();
			});
		}

		// ------------------------------------------------------------------ add / edit button dialog

		function openDialog(bl, editing) {
			if (dialog) dialog.remove();

			dialog = h('dialog', {class: 'fc-dash-dialog'});
			var close = function () { dialog.close(); dialog.remove(); dialog = null; };
			var error = h('div', {class: 'fc-dash-error'});

			var body = h('div', {class: 'fc-dash-dlg-body'});

			// 1. Standard buttons: the whole list, the ones already placed on the dashboard are disabled
			if (!editing) {
				var used  = usedKeys();
				var chips = h('div', {class: 'fc-dash-chips'});
				var count = 0;

				Object.keys(catalog).forEach(function (key) {
					if (!catalog[key].avail) return;
					var isUsed = !!used[key];
					if (!isUsed) count++;

					chips.appendChild(h('button', {
						type: 'button', disabled: isUsed, title: isUsed ? T('FLEXI_DASH_ALREADY_USED') : '',
						onclick: function () {
							bl.btns.push({k: key, t: catalog[key].title, na: 0});
							close();
							render();
						}
					}, h('span', {class: catalog[key].icon, 'aria-hidden': 'true'}), catalog[key].title));
				});

				body.appendChild(h('h4', {text: T('FLEXI_DASH_STANDARD_BUTTONS')}));
				body.appendChild(h('p', {class: 'fc-dash-pick-hint', text: count ? T('FLEXI_DASH_PICK_HINT') : T('FLEXI_DASH_NO_MORE_BUTTONS')}));
				body.appendChild(chips);
			}

			// 2. Custom link
			var cur = editing || {t: '', u: '', i: 'icon-link', g: 'self'};

			var fTitle = h('input', {type: 'text', maxlength: 100, value: cur.t});
			var fUrl   = h('input', {type: 'text', maxlength: 1000, value: cur.u, placeholder: 'https://  |  index.php?option=com_...'});
			var fIcon  = h('input', {type: 'text', maxlength: 60, value: cur.i});
			var fTarget = h('select', {},
				h('option', {value: 'self', text: T('FLEXI_DASH_TARGET_SELF')}),
				h('option', {value: 'blank', text: T('FLEXI_DASH_TARGET_BLANK')}),
				h('option', {value: 'modal', text: T('FLEXI_DASH_TARGET_MODAL')})
			);
			fTarget.value = cur.g;

			var grid = h('div', {class: 'fc-dash-icons'});
			var markIcon = function () {
				grid.querySelectorAll('button').forEach(function (b) { b.classList.toggle('active', b.className.split(' ').indexOf(fIcon.value.trim()) !== -1); });
			};
			(opts.icons || []).forEach(function (ic) {
				grid.appendChild(h('button', {type: 'button', class: ic, title: ic, onclick: function () { fIcon.value = ic; markIcon(); }}));
			});
			fIcon.addEventListener('input', markIcon);

			[fTitle, fUrl, fIcon].forEach(function (f) {
				f.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
			});

			var custom = editing ? h('div', {}) : h('details', {class: 'fc-dash-custom'}, h('summary', {text: T('FLEXI_DASH_CUSTOM_LINK')}));
			custom.appendChild(h('label', {text: T('FLEXI_DASH_LINK_TITLE')}));
			custom.appendChild(fTitle);
			custom.appendChild(h('label', {text: T('FLEXI_DASH_LINK_URL')}));
			custom.appendChild(fUrl);
			custom.appendChild(h('label', {text: T('FLEXI_DASH_ICON')}));
			custom.appendChild(fIcon);
			custom.appendChild(grid);
			custom.appendChild(h('label', {text: T('FLEXI_DASH_OPEN_IN')}));
			custom.appendChild(fTarget);
			custom.appendChild(error);
			body.appendChild(custom);

			var submit = function () {
				var url = fUrl.value.trim();

				// Same rule as on the server: http(s), mailto, or a relative address (no javascript: and other schemes)
				var ok = url !== '' && (/^(https?:\/\/|mailto:)/i.test(url) || !(/^[a-z][a-z0-9+.\-]*:/i.test(url) || url.indexOf('//') === 0));

				if (!ok) { error.textContent = T('FLEXI_DASH_URL_REQUIRED'); fUrl.focus(); return; }

				var icon = /^[a-z0-9 _-]{1,60}$/i.test(fIcon.value.trim()) ? fIcon.value.trim() : 'icon-link';
				var data = {k: 'custom', id: editing ? editing.id : uid('c'), t: fTitle.value.trim() || url, u: url, i: icon, g: fTarget.value};

				if (editing) Object.assign(editing, data);
				else bl.btns.push(data);

				close();
				render();
			};

			// The "Add" button is only for the custom link form (standard buttons are added by a click on them)
			var addBtn = h('button', {type: 'button', class: 'btn btn-primary', onclick: submit, text: editing ? T('FLEXI_SAVE') : T('FLEXI_ADD')});

			if (!editing) {
				addBtn.style.display = 'none';
				custom.addEventListener('toggle', function () { addBtn.style.display = custom.open ? '' : 'none'; });
			}

			dialog.appendChild(h('div', {class: 'fc-dash-dlg-head'},
				h('span', {text: editing ? T('FLEXI_DASH_EDIT_LINK') : T('FLEXI_DASH_ADD_BUTTON')}),
				h('button', {type: 'button', class: 'fc-dash-iconbtn', onclick: close, text: '✕'})
			));
			dialog.appendChild(body);
			dialog.appendChild(h('div', {class: 'fc-dash-dlg-foot'},
				h('button', {type: 'button', class: 'btn btn-secondary', onclick: close, text: T('FLEXI_CANCEL')}),
				addBtn
			));
			dialog.addEventListener('cancel', function () { setTimeout(function () { if (dialog) { dialog.remove(); dialog = null; } }, 0); });

			document.body.appendChild(dialog);
			markIcon();
			dialog.showModal();
		}
	});
})();
