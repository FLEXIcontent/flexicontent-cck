/**
 * Favourites field: add an item (or a category) to the visitor's favourites, or remove it.
 *
 * Plain JavaScript: it works the same with or without jQuery on the page.
 *
 * The button (layouts/flexicontent_fields/favourites/favicon.php) is a label with a checkbox inside:
 *
 *   <label class="favlink_{type}_{id} fcfavs-toggle-btn">
 *     <input type="checkbox" class="fcfavs-toggle-input" data-fcfav-id="{id}" data-fcfav-type="{type}" data-fcfav-counter="0|1">
 *     ... icon, with class fcfavs-heart-fill when a favourite and fcfavs-heart-border when not ...
 *   </label>
 *
 * FCFav(id, type, add_counter) stays global, for template overrides that call it from an onclick.
 */


function toggleFCFavCookie(id, type)
{
	var fcfavs = fc_getCookie('fcfavs');

	try {
		fcfavs = JSON.parse(fcfavs);
	} catch(e) {
		fcfavs = {};
	}

	if (!fcfavs.hasOwnProperty(type))
	{
		fcfavs[type] = [];
	}

	var index = fcfavs[type].indexOf(id);
	index === -1
		? fcfavs[type].push(id)
		: fcfavs[type].splice(index, 1);

	fcfavs = JSON.stringify(fcfavs);
	fc_setCookie('fcfavs', fcfavs, 365);
}



(function ()
{
	'use strict';

	// Heart of toggle styles 0 and 1 (the same shape as the CSS heart of toggle style 2), see favicon.php
	var HEART = 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z';

	// Requests that are running, by type and id
	var pending = {};


	function each(list, fn)
	{
		Array.prototype.forEach.call(list, fn);
	}

	function setting(name, def)
	{
		return typeof window[name] !== 'undefined' && window[name] !== null ? window[name] : def;
	}

	function text(key)
	{
		var J = window.Joomla, T = J && (J.Text || J.JText);
		return T && T._ ? T._(key) : key;
	}

	function escapeHtml(str)
	{
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}


	// Icon of a button whose markup has no checkbox (a template override of favicon.php)
	function iconHtml(on)
	{
		var state = on ? 'fcfavs-heart-fill' : 'fcfavs-heart-border';

		return setting('fcfav_toggle_style', 2) == 2
			? '<span class="fcfavs-btn"><span class="fcfavs-btn-inner ' + state + '"></span></span>'
			: '<svg class="fcfavs-img_icon fcfavs-svg_icon ' + state + '" viewBox="1.5 1.5 21 21" width="28" height="28" aria-hidden="true" focusable="false">'
				+ '<path d="' + HEART + '"/>'
				+ '</svg>';
	}


	// Show the state on every button of the item: tick the checkbox and swap the icon classes
	function showState(links, on)
	{
		each(links, function (link)
		{
			var input = link.querySelector('.fcfavs-toggle-input');

			if (!input)
			{
				link.innerHTML = iconHtml(on);
				return;
			}

			input.checked = on;

			each(link.querySelectorAll('.fcfavs-heart-fill, .fcfavs-heart-border'), function (icon)
			{
				icon.classList.toggle('fcfavs-heart-fill', on);
				icon.classList.toggle('fcfavs-heart-border', !on);
			});
		});
	}


	function fadeOut(box, ms, then)
	{
		if (ms <= 1)
		{
			then();
			return;
		}

		box.style.transition = 'opacity ' + ms + 'ms';
		box.style.opacity = '0';

		setTimeout(function ()
		{
			then();
			box.style.transition = '';
			box.style.opacity = '';
		}, ms);
	}


	// The server answers: added / removed, +N / -N (N = users who favour it, when the counter is shown), login, or a message
	function done(response, links, boxes, add_counter, was)
	{
		var on = response == 'added' || response > 0
			? true
			: (response == 'removed' || response < 0 ? false : null);

		var note_box = '<div class="fc-mssg fc-note fc-iblock fc-nobgimage fcfavs-result-mssg" style="position: relative;">';
		var succ_box = '<div class="fc-mssg fc-success fc-iblock fc-nobgimage fcfavs-result-mssg" style="z-index:1000; position: relative;">';
		var info_box = '<div class="fc-mssg fc-info fc-iblock fc-nobgimage';

		if (setting('fcfav_toggle_info', 1))
		{
			var toggle_info = response == 'login'
				? note_box + text('FLEXI_YOU_NEED_TO_LOGIN') + '</div>'
				: (on === true
					? succ_box + text('FLEXI_ADDED_TO_YOUR_FAVOURITES') + '</div>'
					: (on === false
						? succ_box + text('FLEXI_REMOVED_FROM_YOUR_FAVOURITES') + '</div>'
						: note_box + escapeHtml(response) + '</div>'));

			each(boxes, function (box)
			{
				box.innerHTML = toggle_info;
			});
		}

		// The new state, or back to the state before the click
		if (on !== null)
		{
			showState(links, on);
		}
		else if (was !== null)
		{
			showState(links, was);
		}

		var status_info = setting('fcfav_status_info', 0);
		var status_html = on === null ? '' : (on
			? info_box + ' fcfavs-is-subscriber">' + text('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>'
			: info_box + ' fcfavs-isnot-subscriber">' + text('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>');
		var users = add_counter ? (Math.abs(response) || 0) : 0;

		// Update text with some delay
		setTimeout(function ()
		{
			each(boxes, function (box)
			{
				fadeOut(box, status_info ? 1800 : 1, function ()
				{
					box.innerHTML = status_info ? status_html : '';

					if (on === null)
					{
						return;
					}

					each(box.parentNode.querySelectorAll('.fcfavs-subscribers-count'), function (counter)
					{
						counter.style.display = users ? '' : 'none';

						if (users)
						{
							each(counter.querySelectorAll('.fcfavs-counter-num'), function (num)
							{
								num.textContent = users;
							});
						}
					});
				});
			});
		}, status_info ? 200 : 1);
		return on;
	}


	// input: the checkbox that was clicked, or null when called from an onclick (template override)
	function toggle(id, type, add_counter, input)
	{
		type = type || 'item';

		var key   = type + '_' + id;
		var links = document.querySelectorAll('.favlink_' + key + '.fcfavs-toggle-btn');
		var boxes = document.querySelectorAll('.fcfavs-responce_' + key);
		var was   = input ? !input.checked : null;

		// One request at a time for an item: undo a click made while it runs
		if (pending[key])
		{
			if (input)
			{
				input.checked = was;
			}
			return;
		}

		pending[key] = true;

		// Show the new state at once, it is undone if the request fails
		if (input)
		{
			showState(links, input.checked);
		}

		var root_url = setting('jroot_url_fc', '');
		var base_url = setting('jbase_url_fc', '');

		each(boxes, function (box)
		{
			box.innerHTML = '<span class="fcfavs-loading"><img src="' + base_url + 'components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> </span>';
		});

		var J     = window.Joomla;
		var token = J && J.getOptions ? (J.getOptions('csrf.token') || '') : '';
		var url   = root_url + 'index.php?option=com_flexicontent&format=raw&task=ajaxfav'
			+ '&id=' + encodeURIComponent(id)
			+ '&type=' + encodeURIComponent(type)
			+ (token ? '&' + token + '=1' : '')
			+ '&lang=' + encodeURIComponent(setting('fc_sef_lang', ''))
			+ '&_=' + Date.now();

		fetch(url, {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (r)
			{
				return r.text().then(function (body)
				{
					if (!r.ok)
					{
						throw { status: r.status, text: r.statusText };
					}
					return body;
				});
			})
			.then(function (response)
			{
				pending[key] = false;
				var on = done(response.trim(), links, boxes, add_counter, was);
				if (on !== null) document.dispatchEvent(new CustomEvent('flexicontent:favourite-changed', {
					detail: {id: id, type: type, on: on, source: input || links[0]}
				}));
			})
			.catch(function (err)
			{
				pending[key] = false;

				if (was !== null)
				{
					showState(links, was);
				}

				each(boxes, function (box)
				{
					box.innerHTML = '';
				});

				var message = 'Error status: ' + ((err && err.status) || 0) + ' , Error text: ' + ((err && (err.text || err.message)) || '');
				if (window.Joomla && Joomla.renderMessages) {
					Joomla.renderMessages({error: [escapeHtml(message)]});
				} else {
					// Older templates may omit Joomla's message container/assets.
					var notice = document.createElement('div');
					notice.className = 'alert alert-danger'; notice.setAttribute('role', 'alert');
					notice.textContent = message;
					(links[0] || document.body).insertAdjacentElement('afterend', notice);
				}
			});
	}


	window.FCFav = function (id, type, add_counter)
	{
		toggle(id, type, add_counter, null);
	};


	// One listener for every button on the page, including buttons added later (lists, modules)
	if (!window.fcfav_change_listener)
	{
		window.fcfav_change_listener = true;

		document.addEventListener('change', function (e)
		{
			var input = e.target;

			if (!input || !input.classList || !input.classList.contains('fcfavs-toggle-input'))
			{
				return;
			}

			toggle(
				input.getAttribute('data-fcfav-id'),
				input.getAttribute('data-fcfav-type'),
				parseInt(input.getAttribute('data-fcfav-counter'), 10) > 0,
				input
			);
		});
	}
})();
