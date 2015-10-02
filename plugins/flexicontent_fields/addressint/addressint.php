<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');
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
			$values[0]['addr1'] = '';
			$values[0]['addr2'] = '';
			$values[0]['addr3'] = '';
			$values[0]['city'] = '';
			$values[0]['state'] = '';
			$values[0]['province'] = '';
			$values[0]['zip'] = '';
			$values[0]['country'] = '';
			$values[0]['lat'] = '';
			$values[0]['lon'] = '';
		}
		$this->values = & $values;
		
		// Render form field
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field';
		
		$this->displayField( $formlayout );
		
		// Add needed JS/CSS
		static $js_added = null;
		if ($js_added === null) {
			$js_added = true;
			$document = JFactory::getDocument();
			$document->addScript('//maps.google.com/maps/api/js?sensor=false');
			$document->addScriptDeclaration('
			function geolocateAddr(fieldname, plainname) {
				var geocoder = new google.maps.Geocoder();
				var address = document.forms["adminForm"].elements[fieldname+"[addr1]"].value +", "+document.forms["adminForm"].elements[fieldname+"[city]"].value +", "+document.forms["adminForm"].elements[fieldname+"[state]"].value +" "+document.forms["adminForm"].elements[fieldname+"[province]"].value +", "+document.forms["adminForm"].elements[fieldname+"[zip]"].value +", "+document.forms["adminForm"].elements[fieldname+"[country]"].value;
				geocoder.geocode( { \'address\': address}, function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) {
						var latitude = results[0].geometry.location.lat();
						var longitude = results[0].geometry.location.lng();
						var latfield = fieldname+"[lat]";
						var lonfield = fieldname+"[lon]";
						document.forms["adminForm"].elements[latfield].value = latitude;
						document.forms["adminForm"].elements[lonfield].value = longitude;
						document.getElementById(plainname+"_map").innerHTML = \'<img src="http://maps.google.com/maps/api/staticmap?center=\'+latitude+\',\'+longitude+\'&amp;zoom=12&amp;size=320x240&amp;maptype=roadmap&amp;markers=size:mid%7Ccolor:red%7C|\'+latitude+\',\'+longitude+\'&amp;sensor=false" alt="Geographical address locator" />\';
					} 
					else {
						var latfield = fieldname+"[lat]";
						var lonfield = fieldname+"[lon]";
						document.forms["adminForm"].elements[latfield].value = "";
						document.forms["adminForm"].elements[lonfield].value = "";
						document.getElementById(plainname+"_map").innerHTML = \'<img src="http://maps.google.com/maps/api/staticmap?center=0,0&amp;zoom=12&amp;size=320x240&amp;maptype=roadmap&amp;markers=size:mid%7Ccolor:red%7C|0,0&amp;sensor=false" alt="Geographical address locator" />\';
					}
				}); 
			}
			');
		}
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
		//echo "<pre>"; print_r($post); exit;
		$v = reset($post);
		$post = !is_array($v) ? array($post) : $post;   //echo "<pre>"; print_r($post);
		
		$use_addr2    = $field->parameters->get('use_addr2', 1);
		$use_addr3    = $field->parameters->get('use_addr3', 1);
		$use_usstate  = $field->parameters->get('use_usstate', 1);
		$use_province = $field->parameters->get('use_province', 1);
		$use_country  = $field->parameters->get('use_country', 1);
		$single_country = $field->parameters->get('single_country', '');
		
		$new=0;
		$newpost = array();
    foreach ($post as $n => $v)
    {
    	if (empty($v)) continue;
			
			// validate data or empty/set default values
			$newpost[$new] = array();
			
			$v['country'] = !$use_country ? $single_country : @ $v['country'];  // Force single country
			$newpost[$new]['addr2']    = !$use_addr2     || !isset($v['addr2'])     ? '' : flexicontent_html::dataFilter($v['addr2'],    4000, 'STRING', 0);
			$newpost[$new]['addr3']    = !$use_addr3     || !isset($v['addr3'])     ? '' : flexicontent_html::dataFilter($v['addr3'],    4000, 'STRING', 0);
			$newpost[$new]['state']    = !$use_usstate   || !isset($v['state'])     ? '' : flexicontent_html::dataFilter($v['state'],    200,  'STRING', 0);
			$newpost[$new]['province'] = !$use_province  || !isset($v['province'])  ? '' : flexicontent_html::dataFilter($v['province'], 200,  'STRING', 0);
			$newpost[$new]['country']  = !$use_country   || !isset($v['country'])   ? '' : flexicontent_html::dataFilter($v['country'],  2,    'STRING', 0);
			
			$newpost[$new]['addr1'] = flexicontent_html::dataFilter($v['addr1'],  4000, 'STRING', 0);
			$newpost[$new]['city']  = flexicontent_html::dataFilter($v['addr3'],  4000, 'STRING', 0);
			$newpost[$new]['zip']   = flexicontent_html::dataFilter($v['zip'],    10,   'STRING', 0);
			
			$newpost[$new]['lat'] = flexicontent_html::dataFilter(str_replace(',', '.', $v['lat']),  100, 'DOUBLE', 0);
			$newpost[$new]['lon'] = flexicontent_html::dataFilter(str_replace(',', '.', $v['lon']),  100, 'DOUBLE', 0);
			
			$new++;
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
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