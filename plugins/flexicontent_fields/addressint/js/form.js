/** Google Maps address editor. Nominatim uses its own implementation of the same field hooks. */
(() => {
  const shared = window.fcfield_addrint ||= {};
  const FC = Object.create(shared);
  (shared.engines ||= {}).googlemap = FC;
  for (const name of ['autoComplete','google_maps','allowed_countries','single_country','map_zoom','map_type','LatLon','posMarker']) {
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

  function primaryTypes(type) {
    // The new API accepts specific address types, plus the cities and regions collections.
    if (type === 'address') return ['street_address', 'premise', 'subpremise'];
    return type ? [type] : [];
  }

  FC.initAutoComplete = (id, config) => {
    const input = field(id, 'autocomplete');
    const PlaceAutocompleteElement = window.google?.maps?.places?.PlaceAutocompleteElement;
    if (!input || !PlaceAutocompleteElement || FC.autoComplete[id] === input) return;

    // A repeated value may already contain a cloned widget with its own input.
    const picker = input instanceof PlaceAutocompleteElement ? input : new PlaceAutocompleteElement();
    if (picker !== input) {
      picker.id = input.id;
      picker.className = input.className;
      picker.name = input.name;
      picker.disabled = input.disabled;
      input.replaceWith(picker);
    }
    FC.autoComplete[id] = picker;
    picker.description = translation('AUTOCOMPLETE_LABEL');
    picker.includedPrimaryTypes = primaryTypes(get(id, 'ac_type'));
    const countries = (FC.allowed_countries[config] || []).filter(Boolean);
    // Google accepts at most 15 codes. Larger lists are checked when applying the selection.
    picker.includedRegionCodes = countries.length <= 15 ? countries : [];
    const box = field(id, 'messages');
    const reportFailure = () => notify(id, [translation('PLACE_SEARCH_FAILED')], 'warning');
    picker.addEventListener('gmp-error', reportFailure);
    picker.addEventListener('gmp-select', async ({placePrediction}) => {
      const request = {};
      requests.set(box, request);
      const current = () => picker.isConnected && !picker.disabled && requests.get(box) === request;
      let place;
      try {
        place = placePrediction.toPlace();
        const fields = ['addressComponents', 'formattedAddress', 'location'];
        if (field(id, 'name')) fields.push('displayName');
        if (field(id, 'url')) fields.push('googleMapsURI');
        await place.fetchFields({ fields });
      } catch {
        if (current()) reportFailure();
        return;
      }
      if (!current()) return;
      if (!place.location || !place.addressComponents?.length) {
        reportFailure();
        return;
      }
      // Marker dragging supplies a GeocoderResult. Both paths share the field's address mapping.
      FC.fillInAddress(id, {
        address_components:place.addressComponents.map(part => ({
          types:part.types, long_name:part.longText, short_name:part.shortText,
        })),
        formatted_address:place.formattedAddress,
        geometry:{ location:place.location },
        name:place.displayName,
        url:place.googleMapsURI,
      }, config, true);
    });
  };

  FC.changeAutoCompleteType = id => {
    const picker = FC.autoComplete[id];
    if (picker) picker.includedPrimaryTypes = primaryTypes(get(id, 'ac_type'));
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

  FC.previewEmptyMap = (id, config) => {
    const properties = field(id, 'messages')?.closest('.fc-field-value-properties-box');
    if (!properties) return;
    const observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      observer.disconnect();
      const canvas = document.getElementById('map_canvas_' + id);
      if (properties.contains(canvas) && !canvas.classList.contains('has_fc_google_maps_map')) {
        FC.initMap(id, config, false);
      }
    });
    observer.observe(properties);
  };

  FC.initMap = (id, config, hasPosition = true) => {
    const canvas = document.getElementById('map_canvas_' + id);
    if (!canvas || !window.google?.maps?.Map) return;
    canvas.classList.add('has_fc_google_maps_map');
    FlexiForm.show(field(id,'addressint_map'));
    const position = FC.LatLon[id];
    const zoom = Number.parseInt(get(id, 'zoom'),10);
    const options = {
      center:hasPosition ? position : {lat:0, lng:0}, scrollwheel:false,
      zoom:hasPosition ? (Number.isFinite(zoom) ? zoom : FC.map_zoom[config]) : 2,
      mapTypeId:google.maps.MapTypeId[FC.map_type[config]], zoomControl:true, mapTypeControl:true,
      streetViewControl:true, scaleControl:true, rotateControl:true,
    };
    let map = FC.google_maps[id];
    if (map?.getDiv() === canvas) map.setOptions(options);
    else map = FC.google_maps[id] = new google.maps.Map(canvas, options);
    if (!hasPosition) return;
    if (FC.posMarker[id]?.getMap() === map) {
      FC.posMarker[id].setPosition(position);
      FC.updateMarkerIcon(id);
      return;
    }
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

  FC.fillInAddress = (id, place, config, redraw = false) => {
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
    if (redraw) {
      const skip = field(id, 'autocomplete')?.closest('.fcfieldval_container')?.querySelector('.fcfield-skipvalue');
      if (skip) window.fcfield_address.setEnabled(skip, true);
    }
    const full = place.formatted_address || '';
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
