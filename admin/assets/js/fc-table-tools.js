/**
 * FLEXIcontent table tools: resizable, movable and hideable columns of a manager table
 *
 * Needs a table whose header cells (th) and body cells (td) have a "data-col" attribute (the key of their column).
 * Several cells of a row may share the same key (e.g. a column made of 2 cells), they are handled as one column.
 *
 *   fcTableTools.init('table_id');
 *
 * What it does:
 *  - the table is placed in a scrollable box, so the page does not stretch when there are many columns
 *  - drag the right edge of a header to resize the column
 *  - drag the grip of a header to move the column
 *  - the eye of a header hides the column, the columns box (#mainChooseColBox) shows it again
 * The settings are stored per table in the browser (localStorage).
 */
(function () {
	'use strict';

	var NOHIDE = {cb: 1, title: 1};   // columns that can not be hidden
	var NOMOVE = {cb: 1};              // columns that can not be moved (and nothing can be placed before them)
	var AUTOHIDE = {notes: 1};         // columns hidden automatically while all their cells are empty
	var FITCONTENT = {order: 1, status: 1};  // columns with buttons: never narrower than their content
	var MIN_W  = 48;
	var instances = {};

	function T(key, def) {
		try {
			var s = Joomla.Text._(key);
			return s && s !== key ? s : (def || key);
		}
		catch (e) { return def || key; }
	}

	function h(tag, props) {
		var node = document.createElement(tag);
		props = props || {};

		Object.keys(props).forEach(function (k) {
			var v = props[k];
			if (v === null || v === undefined || v === false) return;
			if (k === 'class') node.className = v;
			else if (k === 'text') node.textContent = v;
			else if (k === 'html') node.innerHTML = v;
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

	var EYE_ON  = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M12 5C7 5 2.7 8.1 1 12.5 2.7 16.9 7 20 12 20s9.3-3.100 11-7.500C21.300 8.100 17 5 12 5zm0 12.500a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';
	var EYE_OFF = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M12 5c5 0 9.300 3.100 11 7.500-.8 2-2 3.700-3.600 5l-1.400-1.400a9 9 0 002.500-3.600C19 9.500 15.800 7 12 7c-1 0-2 .2-2.900.5L7.500 5.900C9 5.300 10.500 5 12 5zM2.100 3.500L3.500 2.100l18.400 18.400-1.400 1.400-3.200-3.200c-1.400.5-2.900.8-4.300.8-5 0-9.300-3.100-11-7.500.9-2.200 2.300-4 4-5.400L2.100 3.500zM12 9.500a3 3 0 013 3c0 .4-.1.800-.2 1.100l-3.900-3.900c.3-.1.700-.2 1.100-.2z"/></svg>';

	function load(id) {
		try {
			var s = JSON.parse(localStorage.getItem('fcTbl:' + id) || 'null');

			if (s && typeof s === 'object') {
				return {
					hidden: Array.isArray(s.hidden) ? s.hidden : [],
					order:  Array.isArray(s.order) ? s.order : [],
					widths: s.widths && typeof s.widths === 'object' ? s.widths : {}
				};
			}
		}
		catch (e) { /* storage not available */ }

		return {hidden: [], order: [], widths: {}};
	}

	function Tools(table, id) {
		this.table  = table;
		this.id     = id;
		this.state  = load(id);
		this.nat    = {};       // natural widths of the cells of each column (measured before the table is fixed)
		this.frozen = false;
		this.need   = null;     // minimal widths of the columns (their titles must fit)
		this.measuring = null;  // while measuring: the columns that are temporarily reduced to 1px
		this.empty  = {};       // columns that are empty in all rows (hidden automatically)
		this.drag   = null;
		this.resizing = false;
		this.dropKey = null;
		this.dropBefore = false;
		this.box    = document.getElementById('mainChooseColBox');
	}

	Tools.prototype = {

		save: function () {
			try { localStorage.setItem('fcTbl:' + this.id, JSON.stringify(this.state)); }
			catch (e) { /* ignore */ }
		},

		headRow: function () { return this.table.tHead.rows[0]; },

		/** Rows that have cells with a column key (the header row and the item rows) */
		rows: function () {
			return Array.prototype.filter.call(this.table.rows, function (tr) {
				return tr.querySelector(':scope > [data-col]');
			});
		},

		cells: function (row, key) {
			return Array.prototype.filter.call(row.children, function (c) { return c.getAttribute('data-col') === key; });
		},

		firstDataRow: function () {
			var body = this.table.tBodies[0];
			if (!body) return null;
			return Array.prototype.filter.call(body.rows, function (tr) { return tr.querySelector(':scope > [data-col]'); })[0] || null;
		},

		/** Keys of the columns, in the order of the header */
		keys: function () {
			var keys = [];
			Array.prototype.forEach.call(this.headRow().children, function (th) {
				var k = th.getAttribute('data-col');
				if (k && keys.indexOf(k) === -1) keys.push(k);
			});
			return keys;
		},

		isOff: function (key) {
			return this.state.hidden.indexOf(key) !== -1 || !!this.empty[key];
		},

		visibleKeys: function () {
			var self = this;
			return this.keys().filter(function (k) { return !self.isOff(k); });
		},

		/** Columns of the AUTOHIDE list whose cells are all empty (no icons, no text) */
		findEmptyColumns: function () {
			var self = this;

			Object.keys(AUTOHIDE).forEach(function (key) {
				var cells = [];
				self.rows().forEach(function (row) {
					if (row.parentNode.tagName !== 'THEAD') cells = cells.concat(self.cells(row, key));
				});

				if (cells.length && cells.every(function (c) { return !c.textContent.trim() && !c.querySelector('img, svg, input, button, a, [class*="icon-"]'); })) {
					self.empty[key] = true;
				}
			});
		},

		label: function (key) {
			var th = this.cells(this.headRow(), key)[0];
			if (!th) return key;
			var lbl = th.getAttribute('data-col-label');
			if (lbl) return lbl;
			var el = th.querySelector('.column_toggle_lbl');
			return ((el ? el.textContent : th.textContent) || '').replace(/\s+/g, ' ').trim() || key;
		},

		// ------------------------------------------------------------------ start

		start: function () {
			var self = this, table = this.table;

			// Scrollable box around the table
			this.wrap = h('div', {class: 'fc-table-wrap'});
			table.parentNode.insertBefore(this.wrap, table);
			this.wrap.appendChild(table);

			// A manager that switches between the details view and the thumbnails view (file manager) shows / hides
			// the table via this class, so it must be on the box that is around the table
			if (table.classList.contains('fman_list_element')) {
				table.classList.remove('fman_list_element');
				this.wrap.classList.add('fman_list_element');
			}

			// Drop the settings of columns that do not exist any more
			var keys = this.keys();
			this.state.hidden = this.state.hidden.filter(function (k) { return keys.indexOf(k) !== -1 && !NOHIDE[k]; });

			if (this.state.order.length) this.applyOrder(this.state.order);
			this.findEmptyColumns();
			this.applyHidden();

			if (Object.keys(this.state.widths).length) this.freeze();

			this.addHeaderTools();
			this.renderBox();
			this.bindBoxClose();
			this.updateWrap();

			window.addEventListener('resize', function () { self.updateWrap(); });

			// Fonts may change the width of the titles after the table was fixed
			if (document.fonts && document.fonts.ready) {
				document.fonts.ready.then(function () { if (self.frozen) self.fitHeads(); });
			}
		},

		// ------------------------------------------------------------------ order and visibility

		applyOrder: function (order) {
			var cur = this.keys();
			var result = order.filter(function (k) { return cur.indexOf(k) !== -1; });

			// Columns that are not in the saved order (e.g. new custom columns) stay after their previous neighbour
			cur.forEach(function (k, i) {
				if (result.indexOf(k) !== -1) return;

				var at = 0;
				for (var j = i - 1; j >= 0; j--) {
					var p = result.indexOf(cur[j]);
					if (p !== -1) { at = p + 1; break; }
				}
				result.splice(at, 0, k);
			});

			// "cb" always stays first
			if (result.indexOf('cb') > 0) { result.splice(result.indexOf('cb'), 1); result.unshift('cb'); }

			var self = this;
			this.rows().forEach(function (row) {
				result.forEach(function (k) {
					self.cells(row, k).forEach(function (c) { row.appendChild(c); });
				});
			});
		},

		applyHidden: function () {
			var self = this;

			this.rows().forEach(function (row) {
				Array.prototype.forEach.call(row.children, function (c) {
					var k = c.getAttribute('data-col');
					if (k) c.classList.toggle('fc-col-off', self.isOff(k));
				});
			});
		},

		toggle: function (key) {
			if (NOHIDE[key]) return;

			var i = this.state.hidden.indexOf(key);
			if (i === -1) this.state.hidden.push(key);
			else this.state.hidden.splice(i, 1);

			this.applyHidden();
			this.save();
			if (this.frozen) { this.buildCols(); this.fitHeads(); }
			this.renderBox();
			this.updateWrap();
		},

		moveColumn: function (src, tgt, before) {
			if (src === tgt || NOMOVE[src]) return;
			if (NOMOVE[tgt]) before = false;

			var self = this;
			this.rows().forEach(function (row) {
				var s = self.cells(row, src), t = self.cells(row, tgt);
				if (!s.length || !t.length) return;

				var ref = before ? t[0] : t[t.length - 1].nextSibling;
				s.forEach(function (c) { row.insertBefore(c, ref); });
			});

			this.state.order = this.keys();
			this.save();
			if (this.frozen) this.buildCols();
			this.renderBox();
		},

		reset: function () {
			this.state = {hidden: [], order: [], widths: {}};
			this.save();
			window.location.reload();
		},

		// ------------------------------------------------------------------ widths

		/** Fix the widths of the columns (measuring the current ones first) */
		freeze: function () {
			if (this.frozen) return true;

			var first = this.firstDataRow();
			if (!first || !window.matchMedia('(min-width: 992px)').matches) return false;

			var self = this;
			this.visibleKeys().forEach(function (k) {
				var ws = self.cells(first, k).map(function (c) { return Math.round(c.getBoundingClientRect().width); });
				self.nat[k] = ws;

				if (self.state.widths[k] == null) {
					self.state.widths[k] = ws.reduce(function (a, b) { return a + b; }, 0);
				}
			});

			this.frozen = true;
			this.table.classList.add('fc-table-fixed');
			this.buildCols();
			this.fitHeads();

			return true;
		},

		buildCols: function () {
			var table = this.table, first = this.firstDataRow(), self = this;
			var old = table.querySelector(':scope > colgroup[data-fc-colgroup]');
			if (old) old.remove();
			if (!first) return;

			var cg = h('colgroup', {'data-fc-colgroup': '1'}), total = 0;

			this.visibleKeys().forEach(function (k) {
				var m   = self.cells(first, k).length;

				// While measuring, the columns are reduced to 1px so that the cells overflow by their content width
				if (self.measuring === true || (self.measuring && self.measuring[k])) {
					for (var j = 0; j < m; j++) cg.appendChild(h('col', {style: 'width:1px'}));
					total += m;
					return;
				}

				var w   = Math.max(MIN_W, parseInt(self.state.widths[k], 10) || 120);
				var nat = self.nat[k] || [];
				var used = 0;

				for (var i = 0; i < m; i++) {
					var cw;

					if (i < m - 1) {
						cw = nat[i] || 44;
						used += cw;
					}
					else {
						cw = Math.max(MIN_W / 2, w - used);
					}

					total += cw;
					cg.appendChild(h('col', {style: 'width:' + cw + 'px'}));
				}
			});

			table.insertBefore(cg, table.firstChild);
			table.style.width = total + 'px';
		},

		cellNeed: function (el) {
			var pr = parseFloat(window.getComputedStyle(el).paddingRight) || 0;
			return Math.ceil(el.scrollWidth + pr + 2);
		},

		/** Width needed by the title of each (visible) column. Table must be fixed. */
		measureHeads: function () {
			var self = this, need = {}, ths = [];

			this.visibleKeys().forEach(function (k) {
				self.cells(self.headRow(), k).forEach(function (th) { th.classList.add('fc-measure'); ths.push([k, th]); });
			});

			this.measuring = true;
			this.buildCols();
			ths.forEach(function (p) { need[p[0]] = (need[p[0]] || 0) + self.cellNeed(p[1]); });
			ths.forEach(function (p) { p[1].classList.remove('fc-measure'); });
			this.measuring = null;
			this.buildCols();

			return need;
		},

		/** Width needed by the title and by the widest value of one column */
		measureColumn: function (key) {
			var self = this, els = [], head = this.headRow(), res = {head: 0, body: 0};

			this.rows().forEach(function (row) {
				var cs = self.cells(row, key);
				cs.forEach(function (c) { c.classList.add('fc-measure'); });
				if (cs.length) els.push([row, cs]);
			});

			this.measuring = {};
			this.measuring[key] = 1;
			this.buildCols();

			els.forEach(function (p) {
				var sum = 0;
				p[1].forEach(function (c) { sum += self.cellNeed(c); });
				if (p[0] === head) res.head = sum;
				else res.body = Math.max(res.body, sum);
			});

			els.forEach(function (p) { p[1].forEach(function (c) { c.classList.remove('fc-measure'); }); });
			this.measuring = null;
			this.buildCols();

			return res;
		},

		/** A column is never narrower than its title */
		fitHeads: function () {
			if (!this.frozen) return;

			var self = this, changed = false;
			this.need = this.measureHeads();

			// Columns made of buttons must keep their buttons visible (not cut with "...")
			this.visibleKeys().forEach(function (k) {
				if (!FITCONTENT[k]) return;
				var m = self.measureColumn(k);
				self.need[k] = Math.max(self.need[k] || 0, m.body);
			});

			this.visibleKeys().forEach(function (k) {
				var n = self.need[k] || 0;
				if ((parseInt(self.state.widths[k], 10) || 0) < n) {
					self.state.widths[k] = n;
					changed = true;
				}
			});

			if (changed) {
				this.save();
				this.buildCols();
				this.updateWrap();
			}
		},

		/** Double click on the edge of a header: fit the width to the title and the values (up to 600px) */
		autoFit: function (key) {
			if (!this.freeze()) return;

			var m = this.measureColumn(key);
			this.state.widths[key] = Math.max(MIN_W, m.head, Math.min(m.body, 600));
			this.save();
			this.buildCols();
			this.updateWrap();
		},

		resetWidths: function () {
			this.state.widths = {};
			this.save();
			this.frozen = false;

			var cg = this.table.querySelector(':scope > colgroup[data-fc-colgroup]');
			if (cg) cg.remove();
			this.table.style.width = '';
			this.table.classList.remove('fc-table-fixed');
			this.updateWrap();
		},

		startResize: function (key, e) {
			if (!this.freeze()) return;
			e.preventDefault();

			var self = this, startX = e.clientX, startW = parseInt(this.state.widths[key], 10) || 120;
			var dir = document.documentElement.dir === 'rtl' ? -1 : 1;
			var minW = Math.max(MIN_W + (this.nat[key] || []).slice(0, -1).reduce(function (a, b) { return a + b; }, 0), (this.need && this.need[key]) || 0);
			var edge = this.edgeCells(key);
			edge.forEach(function (c) { c.classList.add('fc-col-edge'); });
			var target = e.currentTarget;
			var th = target.parentNode;

			// Do not start dragging the column while resizing it
			this.resizing = true;
			th.setAttribute('draggable', 'false');

			document.body.classList.add('fc-col-resizing');
			target.setPointerCapture && target.setPointerCapture(e.pointerId);

			var move = function (ev) {
				self.state.widths[key] = Math.max(minW, Math.round(startW + dir * (ev.clientX - startX)));
				self.buildCols();
			};
			var up = function () {
				target.removeEventListener('pointermove', move);
				target.removeEventListener('pointerup', up);
				target.removeEventListener('pointercancel', up);
				document.body.classList.remove('fc-col-resizing');
				self.resizing = false;
				th.setAttribute('draggable', 'true');
				edge.forEach(function (c) { c.classList.remove('fc-col-edge'); });
				self.save();
				self.updateWrap();
			};

			target.addEventListener('pointermove', move);
			target.addEventListener('pointerup', up);
			target.addEventListener('pointercancel', up);
		},

		/** Last cell of the column in every row (to highlight the whole vertical line) */
		edgeCells: function (key) {
			var self = this, out = [];
			this.rows().forEach(function (row) {
				var cs = self.cells(row, key);
				if (cs.length) out.push(cs[cs.length - 1]);
			});
			return out;
		},

		/** Scrollbar only when the table is wider than the page */
		updateWrap: function () {
			this.wrap.classList.remove('fc-scroll');
			var wide = this.table.offsetWidth > this.wrap.clientWidth + 1;
			this.wrap.classList.toggle('fc-scroll', wide);
		},

		// ------------------------------------------------------------------ interface

		addHeaderTools: function () {
			var self = this;
			var thead = this.headRow();
			var keys = this.keys();

			keys.forEach(function (key) {
				var ths = self.cells(thead, key);
				var firstTh = ths[0], lastTh = ths[ths.length - 1];

				ths.forEach(function (th) { th.classList.add('fc-col-th'); });

				// Resizer on the right edge of the (last cell of the) column
				if (key !== 'cb') {
					var rs = h('span', {class: 'fc-colresizer', 'aria-hidden': 'true'});
					rs.addEventListener('pointerdown', function (e) { self.startResize(key, e); });
					rs.addEventListener('dblclick', function (e) { e.preventDefault(); e.stopPropagation(); self.autoFit(key); });
					lastTh.appendChild(rs);
				}

				// The whole header cell is the handle: grab the title and drag the column (the sort link still works with a click)
				if (!NOMOVE[key]) {
					ths.forEach(function (th) {
						th.setAttribute('draggable', 'true');
						Array.prototype.forEach.call(th.querySelectorAll('a, img'), function (el) { el.setAttribute('draggable', 'false'); });

						th.addEventListener('dragstart', function (e) {
							if (self.resizing) { e.preventDefault(); return; }

							self.drag = key;
							e.dataTransfer.effectAllowed = 'move';
							e.dataTransfer.setData('text/plain', key);
							if (e.dataTransfer.setDragImage) e.dataTransfer.setDragImage(th, 12, 12);
							setTimeout(function () { self.markDragging(key); }, 0);
						});
						th.addEventListener('dragend', function () { self.drag = null; self.clearMarks(true); });
					});
				}

				// Right click on a header: menu of the column
				ths.forEach(function (th) {
					th.addEventListener('contextmenu', function (e) {
						e.preventDefault();
						self.showMenu(key, e.clientX, e.clientY);
					});
				});

				// Drop target
				ths.forEach(function (th) {
					th.addEventListener('dragover', function (e) {
						if (!self.drag || self.drag === key) return;
						e.preventDefault();

						var r = th.getBoundingClientRect();
						var before = (e.clientX < r.left + r.width / 2) !== (document.documentElement.dir === 'rtl');
						if (NOMOVE[key]) before = false;

						self.markDrop(key, before);
					});
					th.addEventListener('drop', function (e) {
						if (!self.drag) return;
						e.preventDefault();

						var src = self.drag, before = self.dropKey === key ? self.dropBefore : false;
						self.drag = null;
						self.clearMarks(true);
						self.moveColumn(src, key, before);
					});
				});
			});
		},

		/** Highlight the whole vertical line (header and all rows) where the dragged column would be placed */
		markDrop: function (key, before) {
			var self = this;
			if (this.dropKey === key && this.dropBefore === before) return;

			this.clearMarks(false);
			this.dropKey = key;
			this.dropBefore = before;

			this.rows().forEach(function (row) {
				var cs = self.cells(row, key);
				if (cs.length) (before ? cs[0] : cs[cs.length - 1]).classList.add(before ? 'fc-col-drop-before' : 'fc-col-drop-after');
			});
		},

		markDragging: function (key) {
			var self = this;
			this.rows().forEach(function (row) {
				self.cells(row, key).forEach(function (c) { c.classList.add('fc-col-dragging'); });
			});
		},

		clearMarks: function (all) {
			var sel = '.fc-col-drop-before, .fc-col-drop-after' + (all ? ', .fc-col-dragging' : '');
			Array.prototype.forEach.call(this.table.querySelectorAll(sel), function (c) {
				c.classList.remove('fc-col-drop-before', 'fc-col-drop-after');
				if (all) c.classList.remove('fc-col-dragging');
			});
			this.dropKey = null;
		},

		/** Context menu of a column header */
		showMenu: function (key, x, y) {
			var self = this;
			this.closeMenu();

			var item = function (label, fn) {
				return h('button', {type: 'button', class: 'fc-ctxitem', text: label, onclick: function () { self.closeMenu(); fn(); }});
			};

			var menu = h('div', {class: 'fc-ctxmenu', role: 'menu'});
			if (!NOHIDE[key]) menu.appendChild(item(T('FLEXI_TBL_HIDE_COL', 'Hide column'), function () { self.toggle(key); }));
			menu.appendChild(item(T('FLEXI_TBL_FIT_COL', 'Fit width to content'), function () { self.autoFit(key); }));
			menu.appendChild(h('div', {class: 'fc-ctxsep'}));
			menu.appendChild(item(T('FLEXI_TBL_RESET_WIDTHS', 'Reset widths'), function () { self.resetWidths(); }));
			menu.appendChild(item(T('FLEXI_TBL_RESET_COLS', 'Reset columns'), function () { self.reset(); }));

			document.body.appendChild(menu);

			// Keep the menu inside the window
			var w = menu.offsetWidth, hgt = menu.offsetHeight;
			menu.style.left = Math.max(4, Math.min(x, window.innerWidth - w - 4)) + 'px';
			menu.style.top  = Math.max(4, Math.min(y, window.innerHeight - hgt - 4)) + 'px';

			this.menu = menu;
			this.menuClose = function (e) {
				if (e.type === 'keydown' && e.key !== 'Escape') return;
				if (e.type === 'mousedown' && menu.contains(e.target)) return;
				self.closeMenu();
			};
			document.addEventListener('mousedown', this.menuClose, true);
			document.addEventListener('keydown', this.menuClose, true);
			window.addEventListener('scroll', this.menuClose, true);
			window.addEventListener('resize', this.menuClose, true);
		},

		closeMenu: function () {
			if (!this.menu) return;

			this.menu.remove();
			this.menu = null;
			document.removeEventListener('mousedown', this.menuClose, true);
			document.removeEventListener('keydown', this.menuClose, true);
			window.removeEventListener('scroll', this.menuClose, true);
			window.removeEventListener('resize', this.menuClose, true);
		},

		/** The columns box closes with Esc or a click anywhere outside of it */
		bindBoxClose: function () {
			var box = this.box, btn = document.getElementById('fc_mainChooseColBox_btn');
			if (!box || !btn || !window.fc_toggle_box_via_btn) return;

			var close = function () {
				if (window.getComputedStyle(box).display !== 'none') window.fc_toggle_box_via_btn('mainChooseColBox', btn, 'btn-primary', undefined, 0);
			};

			document.addEventListener('mousedown', function (e) {
				if (!box.contains(e.target) && !btn.contains(e.target)) close();
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') close();
			});
		},

		/** The columns box (opened by the "columns" button above the table): one eye chip per column */
		renderBox: function () {
			var self = this, box = this.box;

			var badge = document.getElementById('columnchoose_totals');
			if (badge) badge.textContent = this.state.hidden.length ? String(this.state.hidden.length) : '';

			if (!box) return;
			box.innerHTML = '';

			var list = h('div', {class: 'fc-colbox-list'});
			this.keys().forEach(function (key) {
				if (NOHIDE[key] || self.empty[key]) return;

				var off = self.state.hidden.indexOf(key) !== -1;
				list.appendChild(h('button', {
					type: 'button', class: 'fc-colchip ' + (off ? 'off' : 'on'), title: off ? T('FLEXI_TBL_SHOW_COL', 'Show column') : T('FLEXI_TBL_HIDE_COL', 'Hide column'),
					onclick: function () { self.toggle(key); }
				}, h('span', {class: 'fc-colchip-eye', html: off ? EYE_OFF : EYE_ON}), h('span', {text: self.label(key)})));
			});

			box.appendChild(h('div', {class: 'fc-colbox'},
				list,
				h('div', {class: 'fc-colbox-actions'},
					h('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: function () { self.resetWidths(); }, text: T('FLEXI_TBL_RESET_WIDTHS', 'Reset widths')}),
					h('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: function () { self.reset(); }, text: T('FLEXI_TBL_RESET_COLS', 'Reset columns')})
				)
			));
		}
	};

	window.fcTableTools = {
		init: function (id) {
			var table = document.getElementById(id);
			if (!table || !table.tHead || !table.tHead.rows.length || instances[id]) return;

			instances[id] = new Tools(table, id);
			instances[id].start();
		},

		toggle: function (id, key) {
			if (instances[id]) instances[id].toggle(key);
		}
	};
})();


/**
 * Counters in the lists of the filters: an option text like "-7- Text" is shown as a small badge "7" followed by "Text"
 * (the lists are Choices.js controls that re-create their items, so a MutationObserver keeps the badges up to date)
 */
(function () {
	'use strict';

	var RE = /^\s*-(\d+)-\s*([\s\S]*)$/;
	var scheduled = false;

	function badge(item) {
		if (item.getAttribute('data-fc-badged')) return;

		var m = RE.exec(item.textContent || '');
		if (!m) return;

		item.setAttribute('data-fc-badged', '1');
		item.setAttribute('data-fc-label', item.textContent);

		var count = document.createElement('span');
		count.className = 'fc-opt-count' + (m[1] === '0' ? ' is-zero' : '');
		count.textContent = m[1];

		var text = document.createElement('span');
		text.className = 'fc-opt-text';
		text.textContent = m[2];

		while (item.firstChild) item.removeChild(item.firstChild);
		item.appendChild(count);
		item.appendChild(text);
		item.classList.add('fc-has-count');
	}

	function run() {
		scheduled = false;
		Array.prototype.forEach.call(document.querySelectorAll('.choices__item:not([data-fc-badged])'), badge);
	}

	function schedule() {
		if (scheduled) return;
		scheduled = true;
		(window.requestAnimationFrame || window.setTimeout)(run);
	}

	function start() {
		run();
		new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true});
	}

	if (document.readyState !== 'loading') start();
	else document.addEventListener('DOMContentLoaded', start);
})();


/**
 * Selectors of the ordering mode (Order, Field type, Category ...): the width fits the longest option,
 * so that the controls are not wider than their content
 */
(function () {
	'use strict';

	var RE = /^\s*-(\d+)-\s*([\s\S]*)$/;

	function fit() {
		var box = document.getElementById('fcorder_notes_box');
		if (!box) return;

		var ctx = document.createElement('canvas').getContext('2d');

		Array.prototype.forEach.call(box.querySelectorAll('.fc-iblock'), function (wrap) {
			var choices = wrap.querySelector('.choices');
			var sel     = choices && choices.querySelector('select');
			var inner   = choices && choices.querySelector('.choices__inner');
			if (!sel || !inner || !sel.options.length) return;

			var cs = window.getComputedStyle(inner);
			ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;

			var longest = 0;
			Array.prototype.forEach.call(sel.options, function (o) {
				var m = RE.exec(o.textContent || '');
				// A counter badge is shown before the text of the option
				var w = ctx.measureText(m ? m[2] : (o.textContent || '')).width + (m ? 52 : 0);
				if (w > longest) longest = w;
			});

			var label = wrap.querySelector('.input-group-text');
			var width = (label ? label.offsetWidth : 0) + Math.ceil(longest)
				+ (parseFloat(cs.paddingLeft) || 14) + 36   // room of the arrow
				+ 18                                          // scrollbar of the list, borders
			;

			wrap.style.width = Math.ceil(width) + 'px';
			wrap.style.maxWidth = '100%';
		});
	}

	function start() {
		fit();
		window.addEventListener('load', fit);
		setTimeout(fit, 400);
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
	}

	if (document.readyState !== 'loading') start();
	else document.addEventListener('DOMContentLoaded', start);
})();
