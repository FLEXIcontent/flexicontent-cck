/** Google Maps address editor. Nominatim uses its own implementation of the same field hooks. */
(() => {
  const shared = window.fcfield_addrint ||= {};
  const FC = Object.create(shared);
  (shared.engines ||= {}).googlemap = FC;
  for (const name of ['configure','autoComplete','gmapslistener','google_maps','allowed_countries','single_country','map_zoom','map_type','LatLon','posMarker']) {
    shared[name] ||= [];
  }
  const field = (id, key) => document.getElementById(id + '_' + key);
  const get = (id, key) => field(id, key)?.value || '';
  const configure = (id, key, value) => {
    const control = field(id, key);
    if (control) control.value = value ?? '';
  };
  const change = control => {
    control?.fc_choices_instance?.refresh();
    FlexiForm.change(control);
  };
  const translation = key => FlexiForm.text('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_' + key);
  const requests = new WeakMap();

  function notify(id, lines = [], type = 'info') {
    const box = field(id, 'messages');
    if (!box) return;
    box.classList.remove('alert-warning','alert-success','alert-info');
    box.classList.add('alert-' + type);
    box.replaceChildren(...lines.flatMap((line, i) => [
      ...(i ? [document.createElement('br')] : []), document.createTextNode(line),
    ]));
    FlexiForm.show(box, lines.length > 0);
  }

  function coordinates(point) {
    return { lat:typeof point.lat === 'function' ? point.lat() : point.lat,
      lng:typeof point.lng === 'function' ? point.lng() : point.lng };
  }

  FC.initAutoComplete = (id, config) => {
    const input = field(id, 'autocomplete');
    const Autocomplete = window.google?.maps?.places?.Autocomplete;
    if (!input || !Autocomplete) return;
    const options = {};
    const type = get(id, 'ac_type');
    if (type) options.types = [type];
    if (FC.single_country[config]) options.componentRestrictions = { country:FC.single_country[config] };
    FC.gmapslistener[id]?.remove?.();
    const picker = FC.autoComplete[id] = new Autocomplete(input, options);
    FC.configure[config]?.(picker);
    FC.gmapslistener[id] = google.maps.event.addListener(picker, 'place_changed', () => FC.fillInAddress(id, null, config));
  };

  FC.changeAutoCompleteType = id => {
    const type = get(id, 'ac_type');
    FC.autoComplete[id]?.setTypes(type ? [type] : []);
  };

  FC.getCustomMarkerUrl = id => {
    const select = field(id, 'custom_marker');
    if (!select?.value || !select.dataset.markerBaseUrl) return '';
    try {
      const base = new URL(select.dataset.markerBaseUrl, document.baseURI);
      const url = new URL(encodeURIComponent(select.value), base);
      return url.origin === location.origin && url.pathname.startsWith(base.pathname) ? url.href : '';
    } catch { return ''; }
  };

  FC.markerLabel = (select, value, label) => {
    const node = document.createElement('span');
    node.className = 'fc-marker-label';
    if (value && select.dataset.markerBaseUrl) {
      let url;
      try { url = new URL(encodeURIComponent(value), new URL(select.dataset.markerBaseUrl, document.baseURI)); } catch { /* Invalid marker URL. */ }
      if (url?.origin === location.origin) {
        const image = document.createElement('img');
        image.className = 'flag fc-marker-img';
        image.alt = '';
        image.src = url.href;
        node.append(image);
      }
    }
    node.append(document.createTextNode([...select.options].find(option => option.value === String(value))?.textContent || label || ''));
    return node;
  };

  FC.initMarkerSelector = id => {
    const select = field(id, 'custom_marker');
    if (!select) return;
    const key = select.id;
    const existing = select.fc_choices_instance || window.fc_choices_instances?.[key];
    if (existing?.passedElement?.element === select) existing.destroy();
    delete select.fc_choices_instance;
    if (window.fc_choices_instances?.[key]) delete window.fc_choices_instances[key];

    const Choices = window.fc_Choices || window.Choices;
    if (window.fc_use_choicesjs && Choices && !window.skip_select2_js) {
      const options = {
        allowHTML:false, allowHtmlUserInput:false, shouldSort:false,
        callbackOnCreateTemplates:() => Object.fromEntries(['item','choice'].map(kind => [kind, function(config, data, extra, group) {
          const node = Choices.defaults.templates[kind].call(this, config, data, extra, group);
          for (const child of [...node.childNodes]) if (child.nodeType === Node.TEXT_NODE) child.remove();
          node.prepend(FC.markerLabel(select, data.value, data.label));
          return node;
        }])),
      };
      select.dispatchEvent(new CustomEvent('flexicontent:choices-options', { bubbles:true, detail:{options} }));
      const instance = select.fc_choices_instance = new Choices(select, options);
      (window.fc_choices_instances ||= {})[key] = instance;
      instance.containerOuter.element.classList.add('fc-choices-outer');
      select.dispatchEvent(new CustomEvent('flexicontent:choices-ready', { bubbles:true, detail:{instance} }));
      return;
    }

    // Preserve the optional legacy widget when explicitly enabled.
    if (!window.fc_use_choicesjs && !window.skip_select2_js && window.jQuery?.fn.select2) {
      const format = state => FC.markerLabel(select, state.id, state.text).outerHTML;
      window.jQuery(select).select2({ formatResult:format, formatSelection:format, escapeMarkup:markup => markup });
      return;
    }
    const preview = document.createElement('span');
    preview.className = 'fc-marker-native-preview';
    preview.setAttribute('aria-hidden','true');
    if (select.nextElementSibling?.matches('.fc-marker-native-preview')) select.nextElementSibling.remove();
    select.after(preview);
    const show = () => {
      if (!preview.isConnected) { select.removeEventListener('change', show); return; }
      const image = FC.markerLabel(select, select.value, '').querySelector('img');
      preview.replaceChildren(...(image ? [image] : []));
      preview.hidden = !image;
    };
    select.addEventListener('change', show);
    show();
  };

  FC.updateMarkerIcon = id => {
    const marker = FC.posMarker[id];
    if (!marker) return;
    const url = FC.getCustomMarkerUrl(id);
    if (!url) { marker.setIcon(null); return; }
    const anchor = get(id, 'marker_anchor') || 'BotC';
    const image = new Image();
    image.addEventListener('load', () => {
      if (FC.posMarker[id] !== marker || FC.getCustomMarkerUrl(id) !== url || (get(id, 'marker_anchor') || 'BotC') !== anchor) return;
      const w = image.naturalWidth, h = image.naturalHeight;
      const x = anchor.endsWith('L') ? 0 : anchor.endsWith('R') ? w : w/2;
      const y = anchor.startsWith('Top') ? 0 : anchor.startsWith('Mid') ? h/2 : h;
      marker.setIcon({ url, size:new google.maps.Size(w,h), anchor:new google.maps.Point(x,y) });
    });
    image.src = url;
  };

  FC.initMap = (id, config) => {
    const canvas = document.getElementById('map_canvas_' + id);
    if (!canvas || !window.google?.maps?.Map) return;
    canvas.classList.add('has_fc_google_maps_map');
    FlexiForm.show(field(id,'addressint_map'));
    const position = FC.LatLon[id];
    const zoom = Number.parseInt(get(id, 'zoom'),10);
    const map = FC.google_maps[id] = new google.maps.Map(canvas, {
      center:position, scrollwheel:false, zoom:Number.isFinite(zoom) ? zoom : FC.map_zoom[config],
      mapTypeId:google.maps.MapTypeId[FC.map_type[config]], zoomControl:true, mapTypeControl:true,
      streetViewControl:true, scaleControl:true, rotateControl:true,
    });
    const marker = FC.posMarker[id] = new google.maps.Marker({
      map, position, draggable:true, animation:google.maps.Animation.DROP, icon:FC.getCustomMarkerUrl(id),
    });
    FC.updateMarkerIcon(id);
    google.maps.event.addListener(map, 'zoom_changed', () => configure(id, 'zoom', map.getZoom()));
    google.maps.event.addListener(marker, 'dragend', () => FC.geocodePosition(id, marker.getPosition(), marker, config));
  };

  FC.geocodePosition = (id, position, marker, config) => {
    const box = field(id,'messages');
    if (!box) return;
    const request = {};
    requests.set(box, request);
    notify(id, ['Searching address of new marker position ...']);
    new google.maps.Geocoder().geocode({ location:position }, (results, status) => {
      if (!box.isConnected || requests.get(box) !== request) return;
      const location = results?.[0]?.geometry?.location;
      const tolerance = Math.max(Number.parseInt(get(id,'marker_tolerance'),10) || 50, 1);
      configure(id, 'marker_tolerance', tolerance);
      const distance = location && google.maps.geometry?.spherical
        ? Math.round(google.maps.geometry.spherical.computeDistanceBetween(location, position)) : Infinity;
      if (status === google.maps.GeocoderStatus.OK && distance <= tolerance && FC.fillInAddress(id, results[0], config)) {
        marker.setPosition(location);
        notify(id, [translation('MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE').replace('%s', distance)], 'success');
      } else {
        const { lat, lng } = coordinates(position);
        configure(id, 'lat', lat);
        configure(id, 'lon', lng);
        const key = location ? 'MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE' : 'MARKER_ADDRESS_NOT_FOUND_AT_MARKER';
        notify(id, [translation(key).replace('%s', tolerance), translation('MARKER_ADDRESS_ONLY_LONG_LAT')], 'warning');
      }
    });
  };

  FC.fillInAddress = (id, place, config) => {
    const redraw = !place;
    if (redraw) requests.delete(field(id, 'messages'));
    place ||= FC.autoComplete[id]?.getPlace();
    if (!place?.address_components || !place.geometry?.location) return false;
    const parts = Object.fromEntries(place.address_components.map(value => [value.types[0], value]));
    const allowed = (FC.allowed_countries[config] || []).map(country => country.toUpperCase());
    const country = parts.country?.short_name || '';
    if (allowed.length && !allowed.includes(country.toUpperCase())) {
      notify(id, [translation('COUNTRY_NOT_ALLOWED_WARNING') + ': ' + (parts.country?.long_name || ''),
        translation('PLEASE_USE_COUNTRIES') + ': ' + allowed.join(', ')], 'warning');
      return false;
    }
    notify(id);
    const markup = document.createElement('template');
    markup.innerHTML = place.formatted_address || place.adr_address || '';
    const full = markup.content.textContent || '';
    const city = parts.locality?.long_name, province = parts.administrative_area_level_1?.long_name;
    const zip = parts.postal_code?.long_name;
    let street = zip ? full.split(zip)[0] : full;
    for (const part of [city, province]) {
      const index = part ? street.lastIndexOf(part) : -1;
      if (index !== -1) street = street.slice(0,index);
    }
    if (parts.country?.long_name) street = street.split(parts.country.long_name)[0];
    const { lat, lng } = coordinates(place.geometry.location);
    const values = {
      autocomplete:'', addr_display:full, addr_formatted:full, addr1:street.replace(/(^\s*,)|(,\s*$)/g,''),
      addr2:'', addr3:'', name:full.includes(place.name || '') ? '' : place.name,
      url:place.url, city, province, zip, zip_suffix:parts.postal_code_suffix?.long_name,
      country, state:country === 'US' ? parts.administrative_area_level_1?.short_name : '', lat, lon:lng,
    };
    for (const [key, value] of Object.entries(values)) configure(id, key, value);
    configure(id,'zoom', FC.map_zoom[config]);
    const zoom = field(id,'zoom_label');
    if (zoom) zoom.textContent = FC.map_zoom[config];
    FC.LatLon[id] = place.geometry.location;
    if (redraw) FC.initMap(id,config);
    for (const key of Object.keys(values)) {
      const control = field(id,key);
      change(control);
      control?.dispatchEvent(new FocusEvent('blur'));
    }
    return true;
  };

  FC.toggle_USA_state = country => {
    const row = country?.closest('table')?.querySelector('.fc_gm_usstate_row');
    if (!row) return;
    const enabled = country.value === 'US';
    FlexiForm.show(row, enabled);
    const state = row.querySelector('.fc_gm_usstate');
    if (state) state.disabled = !enabled;
    if (!enabled) for (const element of row.querySelectorAll('.invalid')) {
      element.classList.remove('invalid');
      element.removeAttribute('aria-invalid');
    }
  };
  for (const method of ['geocodePosition', 'fillInAddress', 'markerLabel', 'getCustomMarkerUrl']) shared[method] = FC[method];
})();
