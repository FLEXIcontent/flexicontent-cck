	/**
	 * Global variables
	 */

	var fcfield_addrint = {};

	fcfield_addrint.configure = [];

	fcfield_addrint.autoComplete    = [];
	fcfield_addrint.gmapslistener   = [];
	fcfield_addrint.google_maps     = [];
	fcfield_addrint.openstreet_maps = [];
	fcfield_addrint.map_data        = [];

	fcfield_addrint.allowed_countries = [];
	fcfield_addrint.single_country    = [];
	fcfield_addrint.map_zoom = [];
	fcfield_addrint.map_type = [];

	fcfield_addrint.LatLon    = [];
	fcfield_addrint.posMarker = [];

	fcfield_addrint.algolia_api_id    = [];
	fcfield_addrint.algolia_api_key   = [];




	/**
	 *  Google Maps Engine
	 */


	// initialize autocomplete
	fcfield_addrint.initAutoComplete = function(elementid_n, config_name)
	{
		var ac_input   = document.getElementById(elementid_n + '_autocomplete');
		var ac_type    = jQuery('#' + elementid_n + '_ac_type').val();
		var ac_country = fcfield_addrint.single_country[config_name];

		var ac_options = {};
		if (ac_type)    ac_options.types = [ ac_type ];
		if (ac_country) ac_options.componentRestrictions = {country: ac_country};

		var placesAutocomplete = new google.maps.places.Autocomplete( ac_input, ac_options );

		// Create a reference
		fcfield_addrint.autoComplete[elementid_n] = placesAutocomplete;

		// Apply field's configuration
		fcfield_addrint.configure[config_name] (placesAutocomplete);

		fcfield_addrint.gmapslistener[elementid_n] = google.maps.event.addListener(fcfield_addrint.autoComplete[elementid_n], 'place_changed', function()
		{
			jQuery('#' + elementid_n + '_messages').html('').hide();
			fcfield_addrint.fillInAddress(elementid_n, false, config_name);
		});

		return true;
	}


	// Initialize marker selector
	fcfield_addrint.initMarkerSelector = function(elementid_n, config_name)
	{
		var theSelect = jQuery('#' + elementid_n + '_custom_marker');

		theSelect.select2(
		{
			formatResult: function(state) { return fcfield_addrint.format_marker_image(state, theSelect) },
			formatSelection: function(state) { return fcfield_addrint.format_marker_image(state, theSelect) },
			escapeMarkup: function(m) { return m; }
		});

		fc_attachSelect2(theSelect.parent());
	}


	// Calculate marker anchor values
	fcfield_addrint.getCustomMarkerAnchor = function(anc, a, w, h)
	{
		switch(anc)
		{
			case 'TopL' : a.wA = 0;   a.hA = 0; break;
			case 'TopC' : a.wA = w/2; a.hA = 0; break;
			case 'TopR' : a.wA = w;   a.hA = 0; break;

			case 'MidL' : a.wA = 0;   a.hA = h/2; break;
			case 'MidC' : a.wA = w/2; a.hA = h/2; break;
			case 'MidR' : a.wA = w;   a.hA = h/2; break;

			case 'BotL' : a.wA = 0;   a.hA = h; break;
			case 'BotC' : a.wA = w/2; a.hA = h; break;
			case 'BotR' : a.wA = w;   a.hA = h; break;
		}
	}


	// Get custom marker image Url
	fcfield_addrint.getCustomMarkerUrl = function(elementid_n)
	{
		var theSelect = jQuery('#' + elementid_n + '_custom_marker');

		return url = theSelect.length ? theSelect.data('marker-base-url') + theSelect.val() : '';
	}


	// Update marker on map
	fcfield_addrint.updateMarkerIcon = function(elementid_n, config_name)
	{
		var img = new Image();
		var url = fcfield_addrint.getCustomMarkerUrl(elementid_n);
		var anc = jQuery('#' + elementid_n + '_marker_anchor').val();

		img.addEventListener("load", function()
		{
			var w = this.naturalWidth, h = this.naturalHeight, a = {};

			fcfield_addrint.getCustomMarkerAnchor(anc, a, w, h);

			var icon = {
				url: url,
				size: new google.maps.Size(w, h),
				origin: new google.maps.Point(0, 0),
				anchor: new google.maps.Point(a.wA, a.hA)
			};
			fcfield_addrint.posMarker[elementid_n].setIcon(icon);
		});

		img.src = url;
	}


	// re-initialize autocomplete
	fcfield_addrint.changeAutoCompleteType = function(elementid_n, config_name)
	{
		/*
		// Remove listener that update the google map on autocomplete selection
		google.maps.event.removeListener( fcfield_addrint.gmapslistener[elementid_n] );

		// Clone replace input to remove the currently configured autocomplete search
		var el = document.getElementById(elementid_n + '_autocomplete');
		el.parentNode.replaceChild(el.cloneNode(true), el);

		// Attach new autocomplete search
		return fcfield_addrint.initAutoComplete(elementid_n, config_name);
		*/
		jQuery('#' + elementid_n + '_ac_type').val()
			? fcfield_addrint.autoComplete[elementid_n].setTypes([jQuery('#' + elementid_n + '_ac_type').val()])
			: fcfield_addrint.autoComplete[elementid_n].setTypes([]);
	}


	fcfield_addrint.initMap = function(elementid_n, config_name)
	{
		var el = document.getElementById('map_canvas_' + elementid_n);

		jQuery(el).addClass('has_fc_google_maps_map');

		// Show map container
		jQuery('#' + elementid_n + '_addressint_map').css('display', '');

		var zoom = parseInt(jQuery('#' + elementid_n + '_zoom').val());
		zoom = !isNaN(zoom) ? zoom : fcfield_addrint.map_zoom[config_name];

		fcfield_addrint.google_maps[elementid_n] = new google.maps.Map(el, {
			center: fcfield_addrint.LatLon[elementid_n],
			scrollwheel: false,
			zoom: zoom,
			mapTypeId: google.maps.MapTypeId[ fcfield_addrint.map_type[config_name] ],
			zoomControl: true,
			mapTypeControl: true,
			streetViewControl: true,
			scaleControl: true,
			rotateControl: true,
		});

		el.dataset = !!el.dataset ? el.dataset : {};
		el.dataset.google_maps_ref = fcfield_addrint.google_maps[elementid_n];

		var posMarker = new google.maps.Marker({
			map: fcfield_addrint.google_maps[elementid_n],
			draggable:true,
			animation: google.maps.Animation.DROP,
			position: fcfield_addrint.LatLon[elementid_n],
			icon: fcfield_addrint.getCustomMarkerUrl(elementid_n)
		});
		fcfield_addrint.posMarker[elementid_n] = posMarker;

		google.maps.event.addListener(fcfield_addrint.google_maps[elementid_n], "zoom_changed", function()
		{
			jQuery('#' + elementid_n + '_zoom').val(fcfield_addrint.google_maps[elementid_n].getZoom());
		});

		google.maps.event.addListener(posMarker, "dragend", function (event)
		{
			fcfield_addrint.geocodePosition(elementid_n, this.getPosition(), posMarker, config_name);
		});
	}


	fcfield_addrint.geocodePosition = function(elementid_n, pos, marker, config_name)
	{
		jQuery('#' + elementid_n + '_messages')
			.removeClass('alert-success').removeClass('alert-warning').addClass('alert-info')
			.html("Searching address of new marker position ...").show();
		geocoder = new google.maps.Geocoder();
		geocoder.geocode(
			{ latLng: pos },
			function(results, status)
			{
				if (status == google.maps.GeocoderStatus.OK)
				{
					var tolerance = parseInt( jQuery('#' + elementid_n + '_marker_tolerance').val() );
					if ( !tolerance || tolerance < 1 )
					{
						tolerance = 50;
						jQuery('#' + elementid_n + '_marker_tolerance').val(tolerance);
					}

					var distance = Math.round( parseInt( google.maps.geometry.spherical.computeDistanceBetween(results[0].geometry.location, pos) ) );
					if (distance > tolerance)
					{
						jQuery('#' + elementid_n + '_lat').val(pos.lat);
						jQuery('#' + elementid_n + '_lon').val(pos.lng);
						jQuery('#' + elementid_n + '_messages')
							.removeClass('alert-success').removeClass('alert-warning').addClass('alert-info')
							.html( Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE').replace("%s", tolerance) + "<br/> -" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT') ).show();
					}
					else
					{
						jQuery('#' + elementid_n + '_messages').html('');
						fcfield_addrint.fillInAddress(elementid_n,  results[0], config_name);
						marker.setPosition( results[0].geometry.location );
						var html = ! jQuery('#' + elementid_n + '_messages').html().length ? "" : jQuery('#' + elementid_n + '_messages').html() + "<br/> ";
						jQuery('#' + elementid_n + '_messages')
							.removeClass('alert-info').removeClass('alert-warning').addClass('alert-success')
							.html(html + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE').replace("%s", distance)).show();
					}
				}
				else
				{
					jQuery('#' + elementid_n + '_lat').val(pos.lat);
					jQuery('#' + elementid_n + '_lon').val(pos.lng);
					jQuery('#' + elementid_n + '_messages')
						.removeClass('alert-info').removeClass('alert-success').removeClass('alert-info').addClass('alert-warning')
						.html( Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE') + "<br/> -" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT') ).show();
				}
			}
		);
	}


	// fill address fields when autocomplete address is selected
	fcfield_addrint.fillInAddress = function(elementid_n, place, config_name)
	{
		var redrawMap = false;
		if (typeof place === "undefined" || !place)
		{
			place = fcfield_addrint.autoComplete[elementid_n].getPlace();
			redrawMap = true;
		}
		//window.console.log(place);

		if (typeof place.address_components == 'undefined') return;

		// Check allowed country, (zero length means all allowed)
		var country_valid = fcfield_addrint.allowed_countries[config_name].length == 0;
		var selected_country = '';

		if (!country_valid)
		{
			//place.address_components.forEach(function(o)
			for(var j=0; j<place.address_components.length; j++)
			{
				var o = place.address_components[j];
				if (o.types[0] != 'country') continue;
				selected_country = o.long_name;
				for(var i=0; i<fcfield_addrint.allowed_countries[config_name].length; i++)
				{
					if (o.short_name == fcfield_addrint.allowed_countries[config_name][i])
					{
						country_valid = true;
						break;
					}
				}
				if (country_valid) break;
			}
		}

		if (!country_valid)
		{
			jQuery('#' + elementid_n + '_messages').removeClass('alert-success').removeClass('alert-info').addClass('alert-warning').html(
				Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING') + ': <b>'
				+ selected_country + '</b><br/>'
				+ '<b>' + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES') + '</b>: '
				+ fcfield_addrint.allowed_countries[config_name].join(', ')
			).show();
			return false;
		}
		else
		{
			jQuery('#' + elementid_n + '_messages').html('').hide();
		}


		// Empty all fields in case they are not set to a new value
		jQuery('' +
			'#' + elementid_n + '_autocomplete, #' + elementid_n + '_name, #' + elementid_n + '_url,' +
			'#' + elementid_n + '_addr_display, #' + elementid_n + '_addr_formatted,' +
			'#' + elementid_n + '_addr1, #' + elementid_n + '_addr2, #' + elementid_n + '_addr3,' +
			'#' + elementid_n + '_city, #' + elementid_n + '_state, #' + elementid_n + '_province, #' + elementid_n + '_country,' +
			'#' + elementid_n + '_zip, #' + elementid_n + '_zip_suffix, #' + elementid_n + '_lat, #' + elementid_n + '_lon'
		).val('');

		// load city, country code, postal code
		var country_long_name = "";
		place.address_components.forEach(function(o)
		{
			switch(o.types[0])
			{
				// load city
				case "locality":
				jQuery('#' + elementid_n + '_city').val(o.long_name);
				break;

				// load country code
				case "country":
				jQuery('#' + elementid_n + '_country').val(o.short_name).trigger('change');
				country_long_name = o.long_name;
				break;

				// load postal code
				case "postal_code":
				jQuery('#' + elementid_n + '_zip').val(o.long_name);
				break;

				// load postal code suffix
				case "postal_code_suffix":
				jQuery('#' + elementid_n + '_zip_suffix').val(o.long_name);
				break;

				// province
				case "administrative_area_level_1":
				jQuery('#' + elementid_n + '_province').val(o.long_name);
				break;
			}
		});

		var street_address = '', index = -1, div = document.createElement('div');

		// Get FULL address
		if (typeof place.formatted_address != 'undefined')
			street_address = place.formatted_address;
		else if (typeof place.adr_address != 'undefined')
			street_address = place.adr_address;

		//window.console.log(street_address);

		// Strip tags
		div.innerHTML = street_address;
		street_address = div.textContent || div.innerText; // innerText: FF >= 45  --  textContent: IE >= 9
		street_address = !!street_address ? street_address : "";  // Case div.textContent was empty string and div.innerText was undefined

		// Convert full-address to just street-address, by splitting at the (zip) postal code
		if (jQuery('#' + elementid_n + '_zip').val())
		{
			street_address = street_address.split( jQuery('#' + elementid_n + '_zip').val() )[0];
		}

		// Also split at the city / province or country in case that postal code (zip) was missing or in case it was placed after city or  province
		index = jQuery('#' + elementid_n + '_city').val() ? street_address.lastIndexOf( jQuery('#' + elementid_n + '_city').val() ) : -1;
		if (index != -1)  street_address = street_address.substring(0, index);

		index = jQuery('#' + elementid_n + '_province').val() ? street_address.lastIndexOf( jQuery('#' + elementid_n + '_province').val() ) : -1;
		if (index != -1)  street_address = street_address.substring(0, index);

		if (country_long_name)  street_address = street_address.split(country_long_name)[0];

		// Get the street address trimming any spaces, commas
		street_address = street_address.replace(/(^\s*,)|(,\s*$)/g, '')
		jQuery('#' + elementid_n + '_addr1').val(street_address);

		if(jQuery('#' + elementid_n + '_country').val() == 'US')
		{
			// load state
			place.address_components.forEach(function(o){
				if(o.types[0] == 'administrative_area_level_1')
				{
					jQuery('#' + elementid_n + '_state').val(o.short_name).trigger('change');
				}
			});
		}

		// load suggested display address
		jQuery('#' + elementid_n + '_addr_display, #' + elementid_n + '_addr_formatted').val(place.formatted_address);

		// name to google maps
		if ( place.formatted_address.indexOf(place.name) == -1 )
		{
			jQuery('#' + elementid_n + '_name').val(place.name);
		}

		// url to google maps
		jQuery('#' + elementid_n + '_url').val(place.url);

		// default zoom level
		jQuery('#' + elementid_n + '_zoom').val(fcfield_addrint.map_zoom[config_name]);
		jQuery('#' + elementid_n + '_zoom_label').text(fcfield_addrint.map_zoom[config_name]);

		// latitude
		jQuery('#' + elementid_n + '_lat').val(place.geometry.location.lat);

		// longitude
		jQuery('#' + elementid_n + '_lon').val(place.geometry.location.lng);

		// reset map lat/lon
		fcfield_addrint.LatLon[elementid_n] = place.geometry.location;

		// redraw map
		if (redrawMap)
		{
			fcfield_addrint.initMap(elementid_n, config_name);
		}

		// Trigger 'change' and 'blur' events on all fields in case they were not set to a new value, so that validation will work
		jQuery('' +
			'#' + elementid_n + '_autocomplete, #' + elementid_n + '_name, #' + elementid_n + '_url,' +
			'#' + elementid_n + '_addr_display, #' + elementid_n + '_addr_formatted,' +
			'#' + elementid_n + '_addr1, #' + elementid_n + '_addr2, #' + elementid_n + '_addr3,' +
			'#' + elementid_n + '_city, #' + elementid_n + '_state, #' + elementid_n + '_province, #' + elementid_n + '_country,' +
			'#' + elementid_n + '_zip, #' + elementid_n + '_zip_suffix, #' + elementid_n + '_lat, #' + elementid_n + '_lon'
		).trigger('change').trigger('blur');
	}


	// Hide / show and disable / enable US state property
	fcfield_addrint.toggle_USA_state = function(el)
	{
		var country = jQuery(el);
		var usstate_row = country.closest('table').find('.fc_gm_usstate_row');
		if (country.val()=='US')
		{
			usstate_row.show(600);
			usstate_row.find('.fc_gm_usstate').removeAttr('disabled');
		}
		else
		{
			usstate_row.hide(600);
			usstate_row.find('.fc_gm_usstate').attr('disabled', 'disabled');
			usstate_row.find('.invalid').removeClass('invalid').removeAttr('aria-invalid');
		}
	}


	fcfield_addrint.format_marker_image = function (state, theSelect)
	{
		if (!state.id)
		{
			return state.text;
		}

		return '<img class="flag" src="' + theSelect.data('marker-base-url') + state.id + '"/> ' + state.text;
	}




	/**
	 *  Algolia search Engine with OpenStreetMaps
	 */


	// Update marker icon on map, on marker selector change
	fcfield_addrint.updateMarkerIcon_OS = function(elementid_n, config_name)
	{
		var img = new Image();
		var url = fcfield_addrint.getCustomMarkerUrl(elementid_n);
		var anc = jQuery('#' + elementid_n + '_marker_anchor').val();

		var map     = fcfield_addrint.map_data[elementid_n].theMap;
		var markers = fcfield_addrint.map_data[elementid_n].theMarkers;

		img.addEventListener("load", function()
		{
			var w = this.naturalWidth, h = this.naturalHeight, a = {};

			fcfield_addrint.getCustomMarkerAnchor(anc, a, w, h);

			markers.forEach(function (marker) {
				marker.setIcon(L.icon({
					iconUrl: url,
					iconSize: [w, h],
					iconAnchor: [a.wA, a.hA]
				}));
			});
		});

		img.src = url;
	}


	// Handle changes in search autocomplete text, adding (temporarily) multiple markers that match current search text
	fcfield_addrint.handleOnSuggestions_OS = function (e, d)
	{
		d.theMarkers.forEach(function (item) { fcfield_addrint.removeMarker_OS(item, d); });
		d.theMarkers = [];

		if (e.suggestions.length === 0)
		{
			d.theMap.setView(new L.LatLng(45, 0), 11);
			return;
		}

		e.suggestions.forEach(function (item) { fcfield_addrint.addMarker_OS(item, d); });
		fcfield_addrint.findBestZoom_OS(d);
	}

	// Handle highlighting (opacity 100%) the marker of the search result row that is currently active (e.g. mouse hovered by the or touched)
	fcfield_addrint.handleOnCursorchanged_OS = function (e, d)
	{
		d.theMarkers.forEach(function(marker, markerIndex)
		{
			if (markerIndex === e.suggestionIndex) {
				marker.setOpacity(1);
				marker.setZIndexOffset(1000);
			} else {
				marker.setZIndexOffset(0);
				marker.setOpacity(0.5);
			}
		});
	}


	// Handle selecting a specific search result by keeping the specific marker, and removing all other search result markers
	fcfield_addrint.handleOnChange_OS = function (e, d)
	{
		d.theMarkers.forEach(function(marker, markerIndex)
		{
			if (markerIndex === e.suggestionIndex) {
				d.theMarkers = [marker];
				marker.setOpacity(1);
				fcfield_addrint.findBestZoom_OS(d);
			} else {
				fcfield_addrint.removeMarker_OS(marker, d);
			}
		});
	}


	// Handle removing all markers from map
	fcfield_addrint.handleOnClear_OS = function (e, d)
	{
		d.theMap.setView(new L.LatLng(45, 0), 11);
		d.theMarkers.forEach(function (item) { fcfield_addrint.removeMarker_OS(item, d); });
	}


	// Handle adding a marker into the map (e.g. search result markers, or a marker for an already selectd location)
	fcfield_addrint.addMarker_OS = function (suggestion, d, icon, opacity)
	{
		var opacity = !!opacity ? opacity : .4
		var marker  = L.marker(suggestion.latlng, {opacity: opacity});

		if (!!icon)
		{
			marker.setIcon(L.icon({ iconUrl: icon }));
		}

		marker.addTo(d.theMap);
		d.theMarkers.push(marker);

		return marker;
	}


	// Handle removing a marker from the map
	fcfield_addrint.removeMarker_OS = function (marker, d)
	{
		d.theMap.removeLayer(marker);
	}


	// Handle moving map's view port and also setting an appropriate map zoom so that all markers in the map are visible
	fcfield_addrint.findBestZoom_OS = function (d)
	{
		var featureGroup = L.featureGroup(d.theMarkers);
		d.theMap.fitBounds(featureGroup.getBounds().pad(0.5), {animate: false, maxZoom: 10});
	}


	// Handle moving map's view port and also setting an appropriate map zoom so that all markers in the map are visible
	fcfield_addrint.changeAutoCompleteType_OS = function (elementid_n, config_name)
	{
		var ac_type = jQuery('#' + elementid_n + '_ac_type').val();
		fcfield_addrint.map_data[elementid_n].thePlacesAutocomplete.configure({type: ac_type});
	}


	// Initaliaze Algolia search Engine (uses OpenStreetMap)
	fcfield_addrint.initAutoComplete_OS = function(elementid_n, config_name)
	{
		var el = document.getElementById(elementid_n + '_addressint_map');

		jQuery("#" + elementid_n + "_addressint_map").css('display', '');
		jQuery(el).addClass('has_fc_openstreet_map');


		/**
	   * Create Open Street Map
		 */
		var map = L.map('map_canvas_' + elementid_n, {
			scrollWheelZoom: true,
			zoomControl: true
		});

		// Store a reference to the map, e.g. to redraw it on TAB focus
		el.os_maps_ref = map;

		// Catch zoom change
    map.on('zoomend',function(e){
			jQuery('#' + elementid_n + '_zoom').val(map.getZoom());
    });

		// Redraw map after delay (catch case of parent container resizing)
		setTimeout(function() {
			map.invalidateSize();
		}, 10);


		/**
		 * Initialize autocomplete search box
		 */
		var placesAutocomplete = places({
			appId: fcfield_addrint.algolia_api_id,
			apiKey: fcfield_addrint.algolia_api_key,
			container: document.querySelector('#' + elementid_n + '_autocomplete'),
			templates: {
				value: function(suggestion) {
					return suggestion.name;
				}
			}
		});

		// Apply field's configuration
		fcfield_addrint.configure[config_name] (placesAutocomplete);


		/**
		 * Store a references to map, markers, placesAutocomplete
		 * e.g. to update marker icon, re-configure autocomplete on search type selector change
		 */
		var d = {theMap: map, theMarkers: [], thePlacesAutocomplete: placesAutocomplete};
		fcfield_addrint.map_data[elementid_n] = d;
		fcfield_addrint.openstreet_maps[elementid_n + '_addressint_map'] = map;


		/**
		 * Add current value as marker on the map
		 */
		var lat_lon = [jQuery('#' + elementid_n + '_lat').val(), jQuery('#' + elementid_n + '_lon').val()];
		var icon = jQuery('#' + elementid_n + '_custom_marker').val();

		map.setView(lat_lon, jQuery('#' + elementid_n + '_zoom').val());
		fcfield_addrint.addMarker_OS({latlng: lat_lon}, d, icon, 1);


		/**
		 * Actions to take when an autocomplete is done
		 */
		placesAutocomplete.on('change', function resultSelected(e)
		{
			//window.console.log(e.suggestion);
			var t = e.suggestion.type;

			!!!document.querySelector('#' + elementid_n + '_name') ? false : document.querySelector('#' + elementid_n + '_name').value =
				(t === 'busStop' || t === 'trainStation' || t === 'townhall' || t === 'airport') && !!e.suggestion.value ? e.suggestion.value : '';

			!!!document.querySelector('#' + elementid_n + '_addr1') ? false : document.querySelector('#' + elementid_n + '_addr1').value =
				t === 'address' && !!e.suggestion.value ? e.suggestion.value : '';

			!!!document.querySelector('#' + elementid_n + '_city') ? false : document.querySelector('#' + elementid_n + '_city').value =
				(!!e.suggestion.city ? e.suggestion.city : (t === 'city' && !!e.suggestion.value ? e.suggestion.value : ''));

			!!!document.querySelector('#' + elementid_n + '_province') ? false : document.querySelector('#' + elementid_n + '_province').value =
				e.suggestion.administrative || '';

			!!!document.querySelector('#' + elementid_n + '_zip') ? false : document.querySelector('#' + elementid_n + '_zip').value = e.suggestion.postcode || '';

			var country = !!!document.querySelector('#' + elementid_n + '_country') ? false : e.suggestion.countryCode.toUpperCase() || '';
			jQuery('#' + elementid_n + '_country').val(country).trigger('change');

			!!!document.querySelector('#' + elementid_n + '_lat') ? false : document.querySelector('#' + elementid_n + '_lat').value = e.suggestion.latlng['lat'] || '';
			!!!document.querySelector('#' + elementid_n + '_lon') ? false : document.querySelector('#' + elementid_n + '_lon').value = e.suggestion.latlng['lng'] || '';

			// Not applicable fields, clear them after an autocomplete operation
			!!!document.querySelector('#' + elementid_n + '_addr_formatted') ? false : document.querySelector('#' + elementid_n + '_addr_formatted').value = '';
			!!!document.querySelector('#' + elementid_n + '_url') ? false : document.querySelector('#' + elementid_n + '_url').value = '';

			!!!document.querySelector('#' + elementid_n + '_zip_suffix') ? false : document.querySelector('#' + elementid_n + '_zip_suffix').value = '';
			!!!document.querySelector('#' + elementid_n + '_addr2') ? false : document.querySelector('#' + elementid_n + '_addr2').value = '';
			!!!document.querySelector('#' + elementid_n + '_addr3') ? false : document.querySelector('#' + elementid_n + '_addr3').value = '';
		});

		var osmLayer = new L.TileLayer(
			'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				minZoom: 1,
				maxZoom: 19,
				attribution: 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'
			}
		);

		map.addLayer(osmLayer);

		placesAutocomplete.on('suggestions', function (e) { fcfield_addrint.handleOnSuggestions_OS(e, d); });
		placesAutocomplete.on('cursorchanged', function (e) { fcfield_addrint.handleOnCursorchanged_OS(e, d); });
		placesAutocomplete.on('change', function (e) { fcfield_addrint.handleOnChange_OS(e, d); });
		placesAutocomplete.on('clear', function (e) { fcfield_addrint.handleOnClear_OS(e, d); });
	}
