	var fcfield_addrint = {};
	
	fcfield_addrint.autoComplete = [];
	fcfield_addrint.gmapslistener = [];
	fcfield_addrint.google_maps = [];
	
	fcfield_addrint.allowed_countries = [];
	fcfield_addrint.single_country = [];
	fcfield_addrint.map_zoom = [];
	fcfield_addrint.map_type = [];
	fcfield_addrint.LatLon = [];


	// initialize autocomplete
	fcfield_addrint.initAutoComplete = function(elementid_n, config_name)
	{
		var ac_input = document.getElementById(elementid_n + '_autocomplete');
		var ac_type    = jQuery('#' + elementid_n + '_ac_type').val();
		var ac_country = fcfield_addrint.single_country[config_name];

		var ac_options = {};
		if (ac_type)    ac_options.types = [ ac_type ];
		if (ac_country) ac_options.componentRestrictions = {country: ac_country};

		fcfield_addrint.autoComplete[elementid_n] = new google.maps.places.Autocomplete( ac_input, ac_options );

		fcfield_addrint.gmapslistener[elementid_n] = google.maps.event.addListener(fcfield_addrint.autoComplete[elementid_n], 'place_changed', function()
		{
			jQuery('#' + elementid_n + '_messages').html('').hide();
			fcfield_addrint.fillInAddress(elementid_n, false, config_name);
		});
		return true;
	}


	// re-initialize autocomplete
	fcfield_addrint.changeAutoCompleteType = function(elementid_n, config_name)
	{
		// Remove listener that update the google map on autocomplete selection
		google.maps.event.removeListener( fcfield_addrint.gmapslistener[elementid_n] );
		
		// Clone replace input to remove the currently configured autocomplete search
		var el = document.getElementById(elementid_n + '_autocomplete');
		el.parentNode.replaceChild(el.cloneNode(true), el);
		
		// Attach new autocomplete search
		return fcfield_addrint.initAutoComplete(elementid_n, config_name);
	}


	fcfield_addrint.initMap = function(elementid_n, config_name)
	{
		var el = document.getElementById('map_canvas_' + elementid_n);
		
		jQuery(el).addClass('has_fc_google_maps_map');
		jQuery('#' + elementid_n + '_addressint_map').show();  // Show map container
		
		fcfield_addrint.google_maps[elementid_n] = new google.maps.Map(el, {
			center: fcfield_addrint.LatLon[elementid_n],
			scrollwheel: false,
			zoom: fcfield_addrint.map_zoom[config_name],
			mapTypeId: google.maps.MapTypeId[ fcfield_addrint.map_type[config_name] ],
			zoomControl: true,
			mapTypeControl: false,
			scaleControl: false,
			streetViewControl: false,
			rotateControl: false,
		});
		
		el.dataset = !!el.dataset ? el.dataset : {};
		el.dataset.google_maps_ref = fcfield_addrint.google_maps[elementid_n];
		
		myMarker = new google.maps.Marker({
			map: fcfield_addrint.google_maps[elementid_n],
			draggable:true,
			animation: google.maps.Animation.DROP,
			position: fcfield_addrint.LatLon[elementid_n]
		});
		
		google.maps.event.addListener(fcfield_addrint.google_maps[elementid_n], "zoom_changed", function()
		{
			jQuery('#' + elementid_n + '_zoom').val(fcfield_addrint.google_maps[elementid_n].getZoom());
			jQuery('#' + elementid_n + '_zoom_label').text(fcfield_addrint.google_maps[elementid_n].getZoom());
		});
		
		google.maps.event.addListener(myMarker, "dragend", function (event)
		{
			fcfield_addrint.geocodePosition(elementid_n, this.getPosition(), myMarker, config_name);
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