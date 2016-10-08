<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsAddressint extends FCField
{
	static $field_types = array('addressint');

	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$this->setField($field);
		$this->setItem($item);
		
		// Initialise value property
		$values = $this->parseValues($field->value);
		if (empty($values)) {
			$values[0]['autocomplete'] = '';
			$values[0]['addr_display'] = '';
			$values[0]['addr_formatted'] = '';
			$values[0]['name'] = '';
			$values[0]['addr1'] = '';
			$values[0]['addr2'] = '';
			$values[0]['addr3'] = '';
			$values[0]['city'] = '';
			$values[0]['state'] = '';
			$values[0]['province'] = '';
			$values[0]['zip'] = '';
			$values[0]['zip_suffix'] = '';
			$values[0]['country'] = '';
			$values[0]['lat'] = '';
			$values[0]['lon'] = '';
			$values[0]['url'] = '';
			$values[0]['zoom'] = '';
		}
		$this->values = & $values;
		
		// Render form field
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field';

		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			$document = JFactory::getDocument();
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_AT_MARKER', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES', false);
			$document->addScript(JURI::root(true).'/plugins/flexicontent_fields/addressint/js/form.js');	

			// Load google maps library
			$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
			$document->addScript('https://maps.google.com/maps/api/js?libraries=geometry,places' . ($google_maps_js_api_key ? '&key=' . $google_maps_js_api_key : ''));
		}

		$this->displayField( $formlayout );
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/
	
	
	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/

	
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) || !is_array($post)) return;
		
		// Make sure posted data is an array
		$v = reset($post);
		$post = !is_array($v) ? array($post) : $post;
		
		// Enforce configuration so that user does not manipulate form to add disabled data
		$use_name     = $field->parameters->get('use_name', 1);
		$use_addr2    = $field->parameters->get('use_addr2', 1);
		$use_addr3    = $field->parameters->get('use_addr3', 1);
		$use_usstate  = $field->parameters->get('use_usstate', 1);
		$use_province = $field->parameters->get('use_province', 1);
		$use_country  = $field->parameters->get('use_country', 1);
		$use_zip_suffix = $field->parameters->get('use_zip_suffix', 1);
		$map_zoom = $field->parameters->get('map_zoom', 16);
		
		// Get allowed countries
		$ac_country_default = $field->parameters->get('ac_country_default', '');
		$ac_country_allowed_list = $field->parameters->get('ac_country_allowed_list', '');
		$ac_country_allowed_list = array_unique(FLEXIUtilities::paramToArray($ac_country_allowed_list, "/[\s]*,[\s]*/", false, true));
		$ac_country_allowed_list = array_flip($ac_country_allowed_list);
		
		$new=0;
		$newpost = array();
		foreach ($post as $n => $v)
		{
			if (empty($v)) continue;
			
			// Skip value if both address and formated address are empty
			if (
				empty($v['addr_display']) && empty($v['addr_formatted']) && empty($v['addr1']) &&
				empty($v['city']) && empty($v['state']) && empty($v['province']) &&
				(empty($v['lat']) || empty($v['lon'])) && empty($v['url'])
			) continue;
			
			// validate data or empty/set default values
			$newpost[$new] = array();
			
			// Skip value if non-allowed country was passed
			if ( $use_country && @ $v['country'] && count($ac_country_allowed_list) && !isset($ac_country_allowed_list[$v['country']]) ) $continue;
			
			$newpost[$new]['autocomplete']  = flexicontent_html::dataFilter($v['autocomplete'],   4000, 'STRING', '');
			$newpost[$new]['addr_display']  = flexicontent_html::dataFilter($v['addr_display'],   4000, 'STRING', '');
			$newpost[$new]['addr_formatted']= flexicontent_html::dataFilter($v['addr_formatted'], 4000, 'STRING', '');
			$newpost[$new]['addr1'] = flexicontent_html::dataFilter($v['addr1'],  4000, 'STRING', '');
			$newpost[$new]['city']  = flexicontent_html::dataFilter($v['city'],   4000, 'STRING', '');
			$newpost[$new]['zip']   = flexicontent_html::dataFilter($v['zip'],    10,   'STRING', '');
			$newpost[$new]['lat']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lat']),  100, 'DOUBLE', 0);
			$newpost[$new]['lon']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lon']),  100, 'DOUBLE', 0);
			$newpost[$new]['url']   = flexicontent_html::dataFilter($v['url'],    4000,   'URL', '');
			$newpost[$new]['zoom']  = flexicontent_html::dataFilter($v['zoom'],  2, 'INTEGER', $map_zoom);
			
			$newpost[$new]['lat']   = $newpost[$new]['lat'] ? $newpost[$new]['lat'] : '';  // clear if zero
			$newpost[$new]['lon']   = $newpost[$new]['lon'] ? $newpost[$new]['lon'] : '';  // clear if zero
			
			// Allow saving these into the DB, so that they can be enabled later
			$newpost[$new]['name']       = /*!$use_name       ||*/ !isset($v['name'])       ? '' : flexicontent_html::dataFilter($v['name'],     4000,  'STRING', 0);
			$newpost[$new]['addr2']      = /*!$use_addr2      ||*/ !isset($v['addr2'])      ? '' : flexicontent_html::dataFilter($v['addr2'],    4000, 'STRING', 0);
			$newpost[$new]['addr3']      = /*!$use_addr3      ||*/ !isset($v['addr3'])      ? '' : flexicontent_html::dataFilter($v['addr3'],    4000,  'STRING', 0);
			$newpost[$new]['state']      = /*!$use_usstate    ||*/ !isset($v['state'])      ? '' : flexicontent_html::dataFilter($v['state'],     200,  'STRING', 0);
			$newpost[$new]['country']    = /*!$use_country    ||*/ !isset($v['country'])    ? '' : flexicontent_html::dataFilter($v['country'],     2,  'STRING', 0);
			$newpost[$new]['province']   = /*!$use_province   ||*/ !isset($v['province'])   ? '' : flexicontent_html::dataFilter($v['province'],  200,  'STRING', 0);
			$newpost[$new]['zip_suffix'] = /*!$use_zip_suffix ||*/ !isset($v['zip_suffix']) ? '' : flexicontent_html::dataFilter($v['zip_suffix'], 10,  'STRING', 0);
			
			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB, also map some properties as fields
		$props_to_fields = array('addr1', 'addr2', 'addr3', 'city', 'zip', 'country', 'lon', 'lat');
		$_fields = array();
		$byIds = FlexicontentFields::indexFieldsByIds($item->fields, $item);
		foreach($post as $i => $v)
		{
			foreach($props_to_fields as $propname)
			{
				$to_fieldid = $field->parameters->get('field_'.$propname);
				if ( $to_fieldid && isset($byIds[$to_fieldid]) )
				{
					$to_fieldname = $byIds[$to_fieldid]->name;
					$item->calculated_fieldvalues[$to_fieldname][$i] = $v[$propname];
				}
			}
			
			$post[$i] = serialize($v);
		}
	}
	
	
	/*function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);
		
		// Get choosen display layout
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value';
		
		// Render the field's HTML
		$this->values = $values;
		$this->displayFieldValue( $prop, $viewlayout );
	}*/
}