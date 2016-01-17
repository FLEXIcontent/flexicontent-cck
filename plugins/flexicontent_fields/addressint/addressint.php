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
		$use_addr2    = $field->parameters->get('use_addr2', 1);
		$use_addr3    = $field->parameters->get('use_addr3', 1);
		$use_usstate  = $field->parameters->get('use_usstate', 1);
		$use_province = $field->parameters->get('use_province', 1);
		$use_country  = $field->parameters->get('use_country', 1);
		$use_zip_suffix = $field->parameters->get('use_zip_suffix', 1);
		$single_country = $field->parameters->get('single_country', '');
		
		$new=0;
		$newpost = array();
		foreach ($post as $n => $v)
		{
			if (empty($v)) continue;
			
			// validate data or empty/set default values
			$newpost[$new] = array();
			
			$v['country'] = !$use_country ? $single_country : @ $v['country'];  // Force single country
			$newpost[$new]['autocomplete']  = flexicontent_html::dataFilter($v['autocomplete'],   4000, 'STRING', 0);
			$newpost[$new]['addr_display']  = flexicontent_html::dataFilter($v['addr_display'],   4000, 'STRING', 0);
			$newpost[$new]['addr_formatted']= flexicontent_html::dataFilter($v['addr_formatted'], 4000, 'STRING', 0);
			$newpost[$new]['addr1'] = flexicontent_html::dataFilter($v['addr1'],  4000, 'STRING', 0);
			$newpost[$new]['city']  = flexicontent_html::dataFilter($v['city'],   4000, 'STRING', 0);
			$newpost[$new]['zip']   = flexicontent_html::dataFilter($v['zip'],    10,   'STRING', 0);
			$newpost[$new]['lat']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lat']),  100, 'DOUBLE', 0);
			$newpost[$new]['lon']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lon']),  100, 'DOUBLE', 0);
			$newpost[$new]['url']   = flexicontent_html::dataFilter($v['url'],    4000,   'URL', 0);
			$newpost[$new]['zoom']  = flexicontent_html::dataFilter($v['zoom'],  2, 'INTEGER', 0);
	
			$newpost[$new]['addr2']      = !$use_addr2      || !isset($v['addr2'])      ? '' : flexicontent_html::dataFilter($v['addr2'],     4000, 'STRING', 0);
			$newpost[$new]['addr3']      = !$use_addr3      || !isset($v['addr3'])      ? '' : flexicontent_html::dataFilter($v['addr3'],     4000, 'STRING', 0);
			$newpost[$new]['state']      = !$use_usstate    || !isset($v['state'])      ? '' : flexicontent_html::dataFilter($v['state'],     200,  'STRING', 0);
			$newpost[$new]['country']    = !$use_country    || !isset($v['country'])    ? '' : flexicontent_html::dataFilter($v['country'],     2,  'STRING', 0);
			$newpost[$new]['province']   = !$use_province   || !isset($v['province'])   ? '' : flexicontent_html::dataFilter($v['province'],  200,  'STRING', 0);
			$newpost[$new]['zip_suffix'] = !$use_zip_suffix || !isset($v['zip_suffix']) ? '' : flexicontent_html::dataFilter($v['zip_suffix'], 10,  'STRING', 0);
	
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