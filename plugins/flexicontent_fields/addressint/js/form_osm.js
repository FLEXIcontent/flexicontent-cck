/**
 * form_osm.js — FLEXIcontent addressint field
 * Autocomplete via Nominatim (OpenStreetMap) + carte interactive Leaflet
 * Aucune clé API requise.
 *
 * Interface publique identique à form.js (Google Maps) :
 *   fcfield_addrint.initAutoComplete(elementid, field_name_js)
 *   fcfield_addrint.initMap(elementid, field_name_js)
 *   fcfield_addrint.initMarkerSelector(elementid, field_name_js)
 *   fcfield_addrint.changeAutoCompleteType(base_id, field_name_js)
 *   fcfield_addrint.updateMarkerIcon(base_id, field_name_js)
 *   fcfield_addrint.toggle_USA_state(selectEl)
 *   fcfield_addrint.LatLon[elementid]          — {lat, lng}
 *   fcfield_addrint.allowed_countries[field]   — tableau de codes pays
 *   fcfield_addrint.single_country[field]      — code pays unique ou false
 *   fcfield_addrint.map_zoom[field]            — niveau de zoom par défaut
 *   fcfield_addrint.configure[field]           — ignoré (compatibilité)
 */
;(function ($) {
	'use strict';

	/* ------------------------------------------------------------------ *
	 * Constants
	 * ------------------------------------------------------------------ */
	var NOMINATIM_SEARCH  = 'https://nominatim.openstreetmap.org/search';
	var NOMINATIM_REVERSE = 'https://nominatim.openstreetmap.org/reverse';
	var TILE_URL          = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
	var TILE_ATTR         = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors';
	var DEBOUNCE_MS       = 400;
	var MIN_CHARS         = 3;
	var MAX_RESULTS       = 7;
	var DEFAULT_ZOOM      = 16;
	var DEFAULT_LAT       = 48.8566;   // Paris — centre par défaut si aucune coord
	var DEFAULT_LNG       = 2.3522;

	/* ------------------------------------------------------------------ *
	 * Shared state
	 * ------------------------------------------------------------------ */
	var _maps      = {};   // Leaflet map instances  keyed by elementid
	var _markers   = {};   // Leaflet marker instances
	var _timers    = {};   // debounce handles
	var _dropdowns = {};   // suggestion <ul> DOM nodes

	/* ------------------------------------------------------------------ *
	 * Public object — merge with existing fcfield_addrint if already set
	 * ------------------------------------------------------------------ */
	window.fcfield_addrint = window.fcfield_addrint || {};
	var FC = window.fcfield_addrint;

	FC.allowed_countries = FC.allowed_countries || {};
	FC.single_country    = FC.single_country    || {};
	FC.map_zoom          = FC.map_zoom          || {};
	FC.map_type          = FC.map_type          || {};
	FC.configure         = FC.configure         || {};   // ignoré (compatibilité Google Maps)
	FC.tile_url          = FC.tile_url          || {};   // tile server URL par champ (optionnel)
	FC.LatLon            = FC.LatLon            || {};

	/* ------------------------------------------------------------------ *
	 * DOM helpers
	 * ------------------------------------------------------------------ */
	function el(id) { return document.getElementById(id); }

	function val(id, v) {
		var e = el(id);
		if (!e) return;
		if (arguments.length === 1) return e.value;
		if (e.tagName === 'SELECT') {
			for (var i = 0; i < e.options.length; i++) {
				if (e.options[i].value.toUpperCase() === String(v).toUpperCase()) {
					e.value = e.options[i].value;
					return;
				}
			}
			e.value = '';
		} else {
			e.value = v;
		}
	}

	function fireChange(id) {
		var e = el(id);
		if (!e) return;
		$(e).trigger('change');
	}

	function debounce(fn, delay, key) {
		if (_timers[key]) clearTimeout(_timers[key]);
		_timers[key] = setTimeout(fn, delay);
	}

	/* ------------------------------------------------------------------ *
	 * Leaflet loader (dynamique si pas encore chargé)
	 * ------------------------------------------------------------------ */
	function ensureLeaflet(cb) {
		if (window.L && window.L.map) { cb(); return; }

		// CSS
		if (!document.getElementById('leaflet-css')) {
			var link = document.createElement('link');
			link.id   = 'leaflet-css';
			link.rel  = 'stylesheet';
			link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
			document.head.appendChild(link);
		}
		// JS
		var script = document.createElement('script');
		script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
		script.onload = cb;
		document.head.appendChild(script);
	}

	/* ------------------------------------------------------------------ *
	 * Dropdown suggestions
	 * ------------------------------------------------------------------ */
	function dropdownCreate(inputEl) {
		dropdownRemove(inputEl.id);

		var dd = document.createElement('ul');
		dd.className  = 'fc-osm-suggestions';
		dd.style.cssText = [
			'position:absolute',
			'z-index:100000',
			'background:#fff',
			'border:1px solid #bbb',
			'border-top:none',
			'border-radius:0 0 4px 4px',
			'margin:0',
			'padding:0',
			'list-style:none',
			'min-width:' + inputEl.offsetWidth + 'px',
			'max-height:260px',
			'overflow-y:auto',
			'box-shadow:0 4px 8px rgba(0,0,0,.18)',
			'font-size:13px'
		].join(';');

		var rect      = inputEl.getBoundingClientRect();
		var scrollTop = (window.pageYOffset || document.documentElement.scrollTop);
		var scrollLeft= (window.pageXOffset || document.documentElement.scrollLeft);
		dd.style.top  = (rect.bottom + scrollTop)  + 'px';
		dd.style.left = (rect.left   + scrollLeft) + 'px';

		document.body.appendChild(dd);
		_dropdowns[inputEl.id] = dd;
		return dd;
	}

	function dropdownRemove(inputId) {
		var dd = _dropdowns[inputId];
		if (dd && dd.parentNode) dd.parentNode.removeChild(dd);
		delete _dropdowns[inputId];
	}

	function dropdownPopulate(inputEl, results, elementid, field_name_js) {
		var dd = dropdownCreate(inputEl);

		if (!results.length) {
			var li = document.createElement('li');
			li.style.cssText = 'padding:8px 12px;color:#999;cursor:default';
			li.textContent   = Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_AT_MARKER') || 'Aucun résultat';
			dd.appendChild(li);
			return;
		}

		results.forEach(function (r) {
			var li = document.createElement('li');
			li.style.cssText = 'padding:7px 12px;cursor:pointer;border-bottom:1px solid #eee;line-height:1.3';
			li.innerHTML = '<span style="font-weight:600">' + _escHtml(r.display_name.split(',')[0]) + '</span>'
				+ '<br><small style="color:#666">' + _escHtml(r.display_name.split(',').slice(1).join(',').trim()) + '</small>';

			li.addEventListener('mouseenter', function () { this.style.background = '#f0f4ff'; });
			li.addEventListener('mouseleave', function () { this.style.background = ''; });
			li.addEventListener('mousedown',  function (e) {
				e.preventDefault(); // empêche blur de l'input avant le click
				inputEl.value = r.display_name;
				dropdownRemove(inputEl.id);
				fillFields(elementid, field_name_js, r);
			});
			dd.appendChild(li);
		});

		// Fermer si clic ailleurs
		setTimeout(function () {
			document.addEventListener('click', function closer(e) {
				if (e.target !== inputEl) { dropdownRemove(inputEl.id); }
				document.removeEventListener('click', closer);
			});
		}, 0);
	}

	function _escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	/* ------------------------------------------------------------------ *
	 * Nominatim query
	 * ------------------------------------------------------------------ */
	function nominatimSearch(query, countryCodes, callback) {
		var params = {
			q:              query,
			format:         'json',
			addressdetails: 1,
			limit:          MAX_RESULTS,
			'accept-language': document.documentElement.lang || 'fr'
		};
		if (countryCodes) params.countrycodes = countryCodes;

		var qs = Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
		}).join('&');

		fetch(NOMINATIM_SEARCH + '?' + qs, {
			headers: { 'Accept-Language': document.documentElement.lang || 'fr' }
		})
		.then(function (r) { return r.json(); })
		.then(callback)
		.catch(function (err) { console.warn('Nominatim error:', err); callback([]); });
	}

	function nominatimReverse(lat, lng, callback) {
		var url = NOMINATIM_REVERSE + '?format=json&addressdetails=1'
			+ '&lat=' + lat + '&lon=' + lng
			+ '&accept-language=' + (document.documentElement.lang || 'fr');
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(callback)
			.catch(function () { callback(null); });
	}

	/* ------------------------------------------------------------------ *
	 * Fill form fields from a Nominatim result object
	 * ------------------------------------------------------------------ */
	function fillFields(elementid, field_name_js, result) {
		var addr = result.address || {};
		var lat  = parseFloat(result.lat) || 0;
		var lng  = parseFloat(result.lon) || 0;

		/* Coordonnées */
		val(elementid + '_lat', lat || '');
		val(elementid + '_lon', lng || '');

		/* addr_display (mode plaintext) */
		val(elementid + '_addr_display', result.display_name || '');

		/* addr_formatted (champ caché) */
		val(elementid + '_addr_formatted', result.display_name || '');

		/* Décomposition */
		var road    = addr.road || addr.pedestrian || addr.footway || addr.path || addr.street || '';
		var houseNb = addr.house_number || '';
		var addr1   = (road + (houseNb ? ' ' + houseNb : '')).trim();
		var city    = addr.city || addr.town || addr.village || addr.municipality || addr.county || '';
		var state   = addr.state || '';
		var province= addr.state_district || addr.region || '';
		var postcode= (addr.postcode || '').replace(/\s+/g, '');
		var country = (addr.country_code || '').toUpperCase();

		val(elementid + '_addr1',    addr1);
		val(elementid + '_city',     city);
		val(elementid + '_state',    state);
		val(elementid + '_province', province);
		val(elementid + '_zip',      postcode);
		val(elementid + '_country',  country);

		/* URL directions */
		if (lat && lng) {
			val(elementid + '_url',
				'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng);
		}

		/* Mémoriser pour la carte */
		FC.LatLon[elementid] = { lat: lat, lng: lng };

		/* Déclencher change (ex: affichage état US) */
		fireChange(elementid + '_country');
		var countryEl = el(elementid + '_country');
		if (countryEl) FC.toggle_USA_state(countryEl);

		/* Carte */
		if (_maps[elementid]) {
			_moveMarker(elementid, lat, lng);
		} else if (lat && lng) {
			FC.initMap(elementid, field_name_js);
		}
	}

	/* ------------------------------------------------------------------ *
	 * Map helpers
	 * ------------------------------------------------------------------ */
	function _moveMarker(elementid, lat, lng) {
		var map    = _maps[elementid];
		var marker = _markers[elementid];
		if (!map || !marker) return;
		var ll = L.latLng(lat, lng);
		marker.setLatLng(ll);
		map.setView(ll);
	}

	function _mapContainerId(elementid) {
		return 'map_canvas_' + elementid;
	}

	/* ------------------------------------------------------------------ *
	 * PUBLIC: initAutoComplete
	 * ------------------------------------------------------------------ */
	FC.initAutoComplete = function (elementid, field_name_js) {
		var inputEl = el(elementid + '_autocomplete');
		if (!inputEl) return;

		/* Empêche la double init */
		if (inputEl.dataset.osmInit) return;
		inputEl.dataset.osmInit = '1';

		var countryCodes = (FC.allowed_countries[field_name_js] || [])
			.filter(Boolean)
			.map(function (c) { return c.toLowerCase(); })
			.join(',');

		inputEl.setAttribute('autocomplete', 'off');
		inputEl.setAttribute('spellcheck',   'false');

		inputEl.addEventListener('input', function () {
			var q = inputEl.value.trim();
			if (q.length < MIN_CHARS) { dropdownRemove(inputEl.id); return; }

			debounce(function () {
				nominatimSearch(q, countryCodes, function (results) {
					dropdownPopulate(inputEl, results, elementid, field_name_js);
				});
			}, DEBOUNCE_MS, inputEl.id);
		});

		inputEl.addEventListener('blur', function () {
			// Petit délai pour laisser mousedown du li se déclencher
			setTimeout(function () { dropdownRemove(inputEl.id); }, 200);
		});

		inputEl.addEventListener('keydown', function (e) {
			var dd = _dropdowns[inputEl.id];
			if (!dd) return;
			var items = dd.querySelectorAll('li');
			var active = dd.querySelector('li.fc-osm-active');
			var idx = -1;
			items.forEach(function (li, i) { if (li === active) idx = i; });

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				idx = Math.min(idx + 1, items.length - 1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				idx = Math.max(idx - 1, 0);
			} else if (e.key === 'Enter' && active) {
				e.preventDefault();
				active.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
				return;
			} else if (e.key === 'Escape') {
				dropdownRemove(inputEl.id);
				return;
			} else { return; }

			items.forEach(function (li) { li.classList.remove('fc-osm-active'); li.style.background = ''; });
			if (items[idx]) {
				items[idx].classList.add('fc-osm-active');
				items[idx].style.background = '#e8eeff';
				items[idx].scrollIntoView({ block: 'nearest' });
			}
		});
	};

	/* ------------------------------------------------------------------ *
	 * PUBLIC: initMap
	 * ------------------------------------------------------------------ */
	FC.initMap = function (elementid, field_name_js) {
		ensureLeaflet(function () {
			var canvasId = _mapContainerId(elementid);
			var canvasEl = el(canvasId);
			if (!canvasEl) return;

			/* Afficher le conteneur */
			var mapOuter = el(elementid + '_addressint_map');
			if (mapOuter) mapOuter.style.display = '';

			/* Détruire carte existante */
			if (_maps[elementid]) {
				_maps[elementid].remove();
				delete _maps[elementid];
				delete _markers[elementid];
			}

			var zoom    = FC.map_zoom[field_name_js] || DEFAULT_ZOOM;
			var latLon  = FC.LatLon[elementid] || {};
			var lat     = latLon.lat || DEFAULT_LAT;
			var lng     = latLon.lng || DEFAULT_LNG;

			// Tile server: per-field override or global default
			var tileUrl = FC.tile_url[field_name_js] || TILE_URL;

			var map = L.map(canvasId).setView([lat, lng], zoom);
			_maps[elementid] = map;

			L.tileLayer(tileUrl, {
				attribution: TILE_ATTR,
				maxZoom:     19
			}).addTo(map);

			/* Marqueur draggable */
			var markerIcon = _buildIcon(elementid, field_name_js);
			var marker = L.marker([lat, lng], {
				draggable: true,
				icon:      markerIcon
			}).addTo(map);
			_markers[elementid] = marker;

			/* Mise à jour coordonnées après drag */
			marker.on('dragend', function (e) {
				var pos = e.target.getLatLng();
				val(elementid + '_lat', pos.lat.toFixed(7));
				val(elementid + '_lon', pos.lng.toFixed(7));
				FC.LatLon[elementid] = { lat: pos.lat, lng: pos.lng };
				/* Reverse geocode pour mettre à jour l'adresse */
				nominatimReverse(pos.lat, pos.lng, function (result) {
					if (!result) return;
					var inputEl = el(elementid + '_autocomplete');
					if (inputEl) inputEl.value = result.display_name || '';
					fillFields(elementid, field_name_js, result);
				});
			});

			/* Clic sur la carte déplace le marqueur */
			map.on('click', function (e) {
				marker.setLatLng(e.latlng);
				val(elementid + '_lat', e.latlng.lat.toFixed(7));
				val(elementid + '_lon', e.latlng.lng.toFixed(7));
				FC.LatLon[elementid] = { lat: e.latlng.lat, lng: e.latlng.lng };
				nominatimReverse(e.latlng.lat, e.latlng.lng, function (result) {
					if (!result) return;
					var inputEl = el(elementid + '_autocomplete');
					if (inputEl) inputEl.value = result.display_name || '';
					/* On met à jour uniquement les coords + url, pas addr_display
					   pour ne pas écraser une saisie manuelle */
					val(elementid + '_url',
						'https://www.google.com/maps/dir/?api=1&destination='
						+ e.latlng.lat + ',' + e.latlng.lng);
				});
			});

			/* Forcer le recalcul de taille (le conteneur était caché) */
			setTimeout(function () { map.invalidateSize(); }, 300);

			/* Afficher le zoom actuel */
			map.on('zoomend', function () {
				val(elementid + '_zoom', String(map.getZoom()));
			});
			val(elementid + '_zoom', String(zoom));
		});
	};

	/* ------------------------------------------------------------------ *
	 * PUBLIC: initMarkerSelector
	 * ------------------------------------------------------------------ */
	FC.initMarkerSelector = function (elementid, field_name_js) {
		var selectEl = el(elementid + '_custom_marker');
		if (!selectEl) return;

		/* Preview image à côté du select */
		var baseUrl = selectEl.getAttribute('data-marker-base-url') || '';
		_renderMarkerPreview(elementid, baseUrl, selectEl.value);
	};

	/* ------------------------------------------------------------------ *
	 * PUBLIC: updateMarkerIcon  (appelé onChange du select marker/anchor)
	 * ------------------------------------------------------------------ */
	FC.updateMarkerIcon = function (base_id, field_name_js) {
		var elementid = base_id; // base_id IS the elementid (cf field.php replace logic)
		var selectEl  = el(elementid + '_custom_marker');
		var baseUrl   = selectEl ? selectEl.getAttribute('data-marker-base-url') || '' : '';
		var file      = selectEl ? selectEl.value : '';

		_renderMarkerPreview(elementid, baseUrl, file);

		if (_markers[elementid]) {
			var icon = _buildIcon(elementid, field_name_js);
			_markers[elementid].setIcon(icon);
		}
	};

	/* ------------------------------------------------------------------ *
	 * PUBLIC: changeAutoCompleteType
	 * (NOP pour Nominatim — pas de type géré côté API, on filtre par pays)
	 * ------------------------------------------------------------------ */
	FC.changeAutoCompleteType = function (base_id, field_name_js) {
		/* Nominatim ne filtre pas par "type" de lieu via l'API publique.
		   On ignore silencieusement ce changement. */
	};

	/* ------------------------------------------------------------------ *
	 * PUBLIC: toggle_USA_state
	 * ------------------------------------------------------------------ */
	FC.toggle_USA_state = function (selectEl) {
		if (!selectEl) return;
		var id       = selectEl.id || '';
		var prefix   = id.replace(/_country$/, '');
		var stateRow = el(prefix + '_state') ? $(el(prefix + '_state')).closest('tr')[0] : null;
		var provRow  = el(prefix + '_province') ? $(el(prefix + '_province')).closest('tr')[0] : null;

		if (!stateRow && !provRow) return;

		var isUS = (selectEl.value === 'US');
		if (stateRow)  stateRow.style.display  = isUS ? '' : 'none';
		if (provRow)   provRow.style.display   = isUS ? 'none' : '';
	};

	/* ------------------------------------------------------------------ *
	 * Internal: build Leaflet icon from custom marker select
	 * ------------------------------------------------------------------ */
	function _buildIcon(elementid, field_name_js) {
		var selectEl = el(elementid + '_custom_marker');
		var anchorEl = el(elementid + '_marker_anchor');
		var baseUrl  = selectEl ? (selectEl.getAttribute('data-marker-base-url') || '') : '';
		var file     = selectEl ? selectEl.value : '';
		var anchor   = anchorEl ? anchorEl.value : 'BotC';

		if (file && baseUrl) {
			var iconUrl = baseUrl.replace(/\/?$/, '/') + file;
			var ap      = _anchorPoint(anchor, 32, 32);
			return L.icon({
				iconUrl:     iconUrl,
				iconSize:    [32, 32],
				iconAnchor:  ap,
				popupAnchor: [0, -ap[1]]
			});
		}
		/* Marqueur par défaut Leaflet */
		return new L.Icon.Default();
	}

	function _anchorPoint(code, w, h) {
		var map = {
			TopL: [0,   0],
			TopC: [w/2, 0],
			TopR: [w,   0],
			MidL: [0,   h/2],
			MidC: [w/2, h/2],
			MidR: [w,   h/2],
			BotL: [0,   h],
			BotC: [w/2, h],
			BotR: [w,   h]
		};
		return map[code] || [w/2, h];
	}

	function _renderMarkerPreview(elementid, baseUrl, file) {
		var previewId = elementid + '_marker_preview';
		var prev = el(previewId);
		if (!prev) {
			var selectEl = el(elementid + '_custom_marker');
			if (!selectEl) return;
			prev = document.createElement('img');
			prev.id    = previewId;
			prev.style.cssText = 'height:32px;margin-left:8px;vertical-align:middle;border:none';
			selectEl.parentNode.insertBefore(prev, selectEl.nextSibling);
		}
		if (file && baseUrl) {
			prev.src   = baseUrl.replace(/\/?$/, '/') + file;
			prev.style.display = '';
		} else {
			prev.style.display = 'none';
		}
	}

}(jQuery));
