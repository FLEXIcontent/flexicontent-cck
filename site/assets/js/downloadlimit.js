/**
 * Download limits: disables download buttons/links whose quota is exhausted, and shows a modal when they are clicked.
 * Buttons are marked by the layouts with data-fc-dl="fieldId:fileId:itemId"; one POST per page (and after each download)
 * asks the server which ones are blocked, so the state is correct even when the page itself was served from a cache.
 */
(function () {
	'use strict';

	var opts = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('fcDownloadLimit') : null;
	if (!opts) { return; }

	var blocked = {};

	function elements() {
		return document.querySelectorAll('[data-fc-dl]');
	}

	function apply() {
		elements().forEach(function (el) {
			var info = blocked[el.getAttribute('data-fc-dl')];
			var isBlocked = !!(info && info.blocked);

			el.classList.toggle('fcfile_dl_blocked', isBlocked);
			el.setAttribute('aria-disabled', isBlocked ? 'true' : 'false');
		});
	}

	var timer = null;

	function fetchStatus(keys) {
		var body = new URLSearchParams();
		body.set('pairs', keys.join(','));

		return fetch(opts.url, {method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store'})
			.then(function (r) { return r.ok ? r.json() : {}; });
	}

	// The period is sliding: when the oldest download expires a slot frees up, so re-check exactly then
	function schedule() {
		clearTimeout(timer);

		var next = null;
		Object.keys(blocked).forEach(function (k) {
			if (blocked[k] && blocked[k].blocked) {
				var s = Math.max(1, (blocked[k].reset_in || 0) + 1);
				next = next === null ? s : Math.min(next, s);
			}
		});

		if (next !== null) {
			timer = setTimeout(refresh, Math.min(next, 3600) * 1000);   // long waits are re-checked at least hourly
		}
	}

	function refresh() {
		var keys = [];
		elements().forEach(function (el) {
			var k = el.getAttribute('data-fc-dl');
			if (keys.indexOf(k) < 0) { keys.push(k); }
		});
		if (!keys.length) { return Promise.resolve(); }

		return fetchStatus(keys)
			.then(function (data) { blocked = data || {}; apply(); schedule(); })
			.catch(function () { /* the server still enforces the limit */ });
	}

	function showModal(html) {
		var old = document.getElementById('fc-dl-modal');
		if (old) { old.remove(); }

		var overlay = document.createElement('div');
		overlay.id = 'fc-dl-modal';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;';

		var box = document.createElement('div');
		box.style.cssText = 'background:#fff;color:#222;max-width:520px;width:100%;max-height:90vh;overflow:auto;border-radius:6px;padding:24px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.4);';

		var content = document.createElement('div');
		content.innerHTML = html || opts.fallback;   // HTML comes from the administrator's configuration

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'btn btn-secondary';
		close.textContent = opts.close || 'Close';
		close.style.marginTop = '16px';

		function dismiss() { overlay.remove(); document.removeEventListener('keydown', onKey); }
		function onKey(e) { if (e.key === 'Escape') { dismiss(); } }

		close.addEventListener('click', dismiss);
		overlay.addEventListener('click', function (e) { if (e.target === overlay) { dismiss(); } });
		document.addEventListener('keydown', onKey);

		box.appendChild(content);
		box.appendChild(close);
		overlay.appendChild(box);
		document.body.appendChild(overlay);
		close.focus();
	}

	// Capture phase: stops inline onclick handlers and link navigation of blocked buttons
	document.addEventListener('click', function (e) {
		var el = e.target.closest ? e.target.closest('[data-fc-dl]') : null;
		if (!el || el.__fcDlPass) { return; }

		var key  = el.getAttribute('data-fc-dl');
		var info = blocked[key];

		if (info && info.blocked) {
			e.preventDefault();
			e.stopImmediatePropagation();

			// Never show stale data: ask again, the limit may have been reset meanwhile
			fetchStatus([key]).then(function (data) {
				var fresh = data && data[key];
				blocked[key] = fresh || info;

				if (fresh && fresh.blocked) {
					showModal(fresh.html);
					apply();
					schedule();
				} else {
					// Quota is available again: update the buttons and perform the download the user asked for
					apply();
					el.__fcDlPass = true;
					el.click();
					el.__fcDlPass = false;
					setTimeout(refresh, 2500);
				}
			}).catch(function () { showModal(info.html); });
			return;
		}

		// A download is starting: re-check shortly after, the quota may now be used up
		setTimeout(refresh, 2500);
	}, true);

	// Coming back to the tab (possibly after the limit expired): refresh the state
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) { refresh(); }
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refresh);
	} else {
		refresh();
	}

	var style = document.createElement('style');
	style.textContent = '.fcfile_dl_blocked{opacity:.55;cursor:not-allowed !important;pointer-events:auto !important;}';
	document.head.appendChild(style);
})();
