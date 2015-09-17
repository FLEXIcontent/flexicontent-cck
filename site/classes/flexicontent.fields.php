<?php
/**
 * @version 1.5 stable $Id: flexicontent.fields.php 1990 2014-10-14 02:17:49Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

// Include com_content helper files, these are needed by some content plugins
require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'query.php');

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

class FlexicontentFields
{
	/**
	 * Function to render the field display variables for the given items
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	static function renderFields( $item_per_field=true, $item_ids=array(), $field_names=array(), $view=FLEXI_ITEMVIEW, $methods=array(), $cfparams=array() )
	{
		require_once (JPATH_ADMINISTRATOR.DS.'components/com_flexicontent/defineconstants.php');
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once("components/com_flexicontent/classes/flexicontent.helper.php");
		
		
		// ***************************
		// Check if no data were given
		// ***************************
		
		if ( empty($item_ids) || empty($field_names) ) return false;
		
		// Get item data, needed for rendering fields
		$db = JFactory::getDBO();
		
		$unique_item_ids = array_unique(array_map('intval', $item_ids));
		$item_ids_list = implode("," , $unique_item_ids) ;
		
		$query = 'SELECT i.id, i.*, ie.*, '
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' WHERE i.id IN ('. $item_ids_list .')'
			//. ' GROUP BY i.id'
			;
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if (empty($items)) return false;
		
		foreach ($items as $i => $item) $_item_id_map[$item->id] = & $items[$i];
		
		$return = array();
		foreach ($field_names as $i => $field_name)
		{
			$method = isset( $methods[$i] ) ? $methods[$i] : 'display';
			if ( $item_per_field )
			{
				if ( !isset( $_item_id_map[ $item_ids[$i] ] ) )  { /*echo "not found item: ".$item_ids[$i] ." <br/>";*/ continue;}
				
				// Render Display variable of Field for respective item
				$_item = & $_item_id_map[$item_ids[$i]];
				FlexicontentFields::getFieldDisplay($_item, $field_name, $values=null, $method, $view);
				// Add to return array
				$return[$_item->id][$field_name][$method] = $_item->fields[$field_name]->$method;
			}
			else
			{
				// Render Display variable of Field for all items
				FlexicontentFields::getFieldDisplay($items, $field_name, $values=null, $method, $view);
				// Add to return array
				foreach ($items as $item) {
					$return[$item->id][$field_name][$method] = $item->fields[$field_name]->$method;
				}
			}
		}
		return $return;
	}
	
	
	/**
	 * Method to bind fields to an items object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function &getFields(&$_items, $view = FLEXI_ITEMVIEW, $params = null, $aid = false, $use_tmpl = true)
	{
		static $expired_cleaned = false;
		
		if (!$_items) return $_items;
		if (!is_array($_items))  $items = array( & $_items );  else  $items = & $_items ;
		
		$user      = JFactory::getUser();
		$cparams   = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
		// Calculate access for current user if it was not given or if given access is invalid
		$aid = is_array($aid) ? $aid : JAccess::getAuthorisedViewLevels($user->id);
		
		// Apply cache to public (unlogged) users only 
		/*$apply_cache = !$user->id && FLEXI_CACHE;
		if ($apply_cache) {
			$itemcache = JFactory::getCache('com_flexicontent_items');  // Get Joomla Cache of '...items' Caching Group
			$itemcache->setCaching(1); 		              // Force cache ON
			$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			
			$filtercache = JFactory::getCache('com_flexicontent_filters');  // Get Joomla Cache of '...filters' Caching Group
			$filtercache->setCaching(1); 		              // Force cache ON
			$filtercache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			
			// Auto-clean expired item & filters cache, only done here once
			if (FLEXI_GC && !$expired_cleaned) {
				$itemcache->gc();
				$filtercache->gc();
				$expired_cleaned = true;
			}
			// ... now retrieved CACHED ... code removed ...
		}*/
		
		// This is optimized regarding the use of SINGLE QUERY to retrieve the core item data
		$vars['tags']       = FlexicontentFields::_getTags($items, $view);
		$vars['cats']       = FlexicontentFields::_getCategories($items, $view);
		$vars['favourites'] = FlexicontentFields::_getFavourites($items, $view);
		$vars['favoured']   = FlexicontentFields::_getFavoured($items, $view);
		$vars['authors']    = FlexicontentFields::_getAuthors($items, $view);
		$vars['modifiers']  = FlexicontentFields::_getModifiers($items, $view);
		$vars['typenames']  = FlexicontentFields::_getTypenames($items, $view);
		$vars['votes']      = FlexicontentFields::_getVotes($items, $view);
		$vars['custom']     = FlexicontentFields::_getCustomValues($items, $view);
		
		FlexicontentFields::getItemFields($items, $vars, $view, $aid);
		
		if ( $print_logging_info )  @$fc_run_times['field_values_params'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		$_rendered = array();
		if ($params)  // NULL/empty parameters mean only retrieve field values
		{
			$always_create_fields_display = $cparams->get('always_create_fields_display',0);
			$request_view = JRequest::getVar('view');
			
			// CHECK if 'always_create_fields_display' enabled and create the display for all item's fields
			// *** This should be normally set to ZERO (never), to avoid a serious performance penalty !!!
			
			// 0: never, 1: always, 2: only in item view, 3: never unless in a template position,  this effects function:  renderPositions()
			if ($always_create_fields_display==1 || ($always_create_fields_display==2 && $request_view==FLEXI_ITEMVIEW && $view==FLEXI_ITEMVIEW) )
			{
				$field_names = array();
				foreach ($items as $i => $item)
				{
					if ($items[$i]->fields)
					{
						foreach ($items[$i]->fields as $field)
						{
							$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
							$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display', $view);
							$field_names[$field->name] = 1;
						}
					}
				}
				foreach ($field_names as $field_name => $_ignore) {
					$_rendered['ALL'][$field_name] = 1;
				}
			}
			
			// Render field positions
			$items = FlexicontentFields::renderPositions($items, $view, $params, $use_tmpl, $_rendered);
		}
		return $items;
	}
	
	
	/**
	 * Method to get fields configuration data by field ids
	 * 
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & indexFieldsByIds($fields) 
	{
		static $byIds = null;
		if ($byIds===null) {
			foreach($fields as $_field) {
				$byIds[$_field->id] = $_field;
			}
		}
		return $byIds;
	}
	
	
	/**
	 * Method to get fields configuration data by field ids
	 * 
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & getFieldsByIds($field_ids) 
	{
		if (!count($field_ids))
		{
			$fields = array();
			return $fields;
		}
		
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		JArrayHelper::toInteger($field_ids);
		
		// Field's has_access flag
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);
		$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';
		
		$query 	= 'SELECT fi.*'
			. $select_access
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.id IN ('.implode(",", $field_ids).') '
			;
		$db->setQuery($query);
		$fields = $db->loadObjectList('id');
		
		return $fields;
	}
	
	
	/**
	 * Method to get fields values data by field ids + item ids
	 * 
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & getFieldValsById($field_ids, $item_ids, $version=0)
	{
		$db = JFactory::getDBO();
		JArrayHelper::toInteger($field_ids);
		JArrayHelper::toInteger($item_ids);
		
		$query = 'SELECT item_id, field_id, value, valueorder, suborder'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id IN ('.implode(",", $item_ids).') '
				.' AND field_id IN ('.implode(",", $field_ids).') '
				.( $version ? ' AND version=' . (int)$version:'')
				.' AND value > "" '
				.' ORDER BY field_id, valueorder, suborder'
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();
		
		$fieldvalues = array();
		if ($values) foreach ($values as $v) {
			$fieldvalues[$v->item_id][$v->field_id][$v->valueorder - 1][$v->suborder - 1] = $v->value;
		}
		foreach ($fieldvalues as & $iv) {
			foreach ($iv as & $fv) {
				foreach ($fv as & $ov) {
					if (count($ov) == 1) $ov = reset($ov);
				}
				unset($ov);
			}
			unset($fv);
		}
		unset($iv);
		
		return $fieldvalues;
	}
	
	
	/**
	 * Method to fetch the fields from an item object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function & getItemFields(&$items, &$vars, $view=FLEXI_ITEMVIEW, $aid=false)
	{
		if ( empty($items) ) return;
		
		static $type_fields = array();
		
		$dispatcher = JDispatcher::getInstance();
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		
		foreach ($items as $i => $item)
		{
			if (!FLEXI_J16GE && $item->sectionid != FLEXI_SECTION) continue;
			
			$item_id = $item->id;
			
			$cats      = isset($vars['cats'][$item_id])      ? $vars['cats'][$item_id]             : array();
			$tags      = isset($vars['tags'][$item_id])      ? $vars['tags'][$item_id]             : array();
			$favourites= isset($vars['favourites'][$item_id])? $vars['favourites'][$item_id]->favs : 0;
			$favoured  = isset($vars['favoured'][$item_id])  ? $vars['favoured'][$item_id]->fav    : 0;
			$author    = isset($vars['authors'][$item_id])   ? $vars['authors'][$item_id]          : '';
			$modifier  = isset($vars['modifiers'][$item_id]) ? $vars['modifiers'][$item_id]        : '';
			$typename  = isset($vars['typenames'][$item_id]) ? $vars['typenames'][$item_id]        : '';
			$vote      = isset($vars['votes'][$item_id])     ? $vars['votes'][$item_id]            : '';
			$custom    = isset($vars['custom'][$item_id])    ? $vars['custom'][$item_id]           : array();
			
			
			// ONCE per Content Item Type
			if ( !isset($type_fields[$item->type_id]) )
			{
				// Field's has_access flag
				$aid_arr = is_array($aid) ? $aid : JAccess::getAuthorisedViewLevels($user->id);
				$aid_list = implode(",", $aid_arr);
				$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';
				
				$query 	= 'SELECT fi.*'
					. $select_access
					. ' FROM #__flexicontent_fields AS fi'
					. ' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id = '.$item->type_id
					. ' WHERE fi.published = 1'
					. ' GROUP BY fi.id'
					. ' ORDER BY ftrel.ordering, fi.ordering, fi.name'
					;
				$db->setQuery($query);
				$type_fields[$item->type_id] = $db->loadObjectList('name');
				//echo "<pre>";  print_r( array_keys($type_fields[$item->type_id]) ); exit;
			}
			
			$item->fields = array();
			if ($type_fields[$item->type_id])
			{
				foreach($type_fields[$item->type_id] as $field_name => $field_data)
				{
					$item->fields[$field_name] = clone($field_data);
				}
			}
			$item->fields	= $item->fields	? $item->fields	: array();
			
			if (!isset($item->parameters)) $item->parameters = new JRegistry($item->attribs);
			$item->params		= $item->parameters;
			
			$item->text			= $item->introtext . chr(13).chr(13) . $item->fulltext;
			$item->tags = $tags;
			$item->cats = $cats;
			$item->favs			= $favourites;
			$item->fav			= $favoured;
			
			$item->creator 	= @$author->alias ? $author->alias : (@$author->name 		? $author->name 	: '') ;
			$item->author		= & $item->creator;  // An alias ... of creator
			$item->modifier	= @$modifier->name 		? $modifier->name 	: $item->creator;   // If never modified, set modifier to be the creator
			$item->modified	= ($item->modified != $db->getNulldate()) ? $item->modified : $item->created;   // If never modified, set modification date to be the creation date
			
			$item->cmail 		= @$author->email 		? $author->email 	: '' ;
			$item->cuname 	= @$author->username 	? $author->username 	: '' ;
			$item->mmail		= @$modifier->email 	? $modifier->email 	: $item->cmail;
			$item->muname		= @$modifier->muname 	? $modifier->muname : $item->cuname;
			
			$item->typename	= @$typename->name 		? $typename->name 	: JText::_('Article');
			$item->vote			= @$vote ? $vote : '';
			
			// some aliases to much CORE field names
			$item->categories    = & $item->cats;
			$item->favourites    = & $item->favs;
			$item->document_type = & $item->typename;
			$item->voting        = & $item->vote;
			
			// custom field values
			$item->fieldvalues = $custom;
		}
		
		return $items;
	}
	
	
	/**
	 * Method to render (display method) a field on demand and return the display
	 * 
	 * @access public
	 * @return object
	 * @since 1.5.5
	 */
	static function &getFieldDisplay(&$item_arr, $fieldname, $single_item_vals=null, $method='display', $view = FLEXI_ITEMVIEW)
	{
		// 1. Convert to array of items if not an array already
		if ( empty($item_arr) ) {
			$err_msg = __FUNCTION__."(): empty item data given";
			return $err_msg;
		}
		else if ( !is_array($item_arr) ) 
			$items = array( & $item_arr );
		else
			$items = & $item_arr;
		
  	// 2. Make sure that fields have been created for all given items
		$_items = array();
	  foreach ($items as $i => $item)  if (!isset($item->fields))  $_items[] = & $items[$i];
	  if ( count($_items) )  FlexicontentFields::getFields($_items, $view);
	  
	  // 3. Check and create HTML display for the given field name
	  $_return = array();
	  foreach ($items as $item)
	  {
		  // Check if we have already created the display and skip current item
		  if ( isset($item->onDemandFields[$fieldname]->{$method}) )  continue;
		  
		  // Find the field inside item
		  foreach ($item->fields as $field)  {
				if ( !empty($field->name) && $field->name==$fieldname ) break;
			}
		  
		  // Check for not found field, and skip it, this is either due to no access or wrong name ...
	    $item->onDemandFields[$fieldname] = new stdClass();
		  if ( empty($field->name) || $field->name!=$fieldname) {
			  $item->onDemandFields[$fieldname]->label = '';
		  	$item->onDemandFields[$fieldname]->noaccess = true;
		  	$item->onDemandFields[$fieldname]->errormsg = 'field not assigned to this type of item or current user has no access';
		  	$item->onDemandFields[$fieldname]->{$method} = '';
		  	continue;
		  }
		  
		  // Get field's values if they were custom values were not given
		  if ( $single_item_vals!==null && count($items) == 1 ) {
				// $values is used only if rendering a single item
		  	$values = $single_item_vals;
		  } else {
		  	$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
		  }
		  
		  // Set other field data like label and field itself !!!
		  $item->onDemandFields[$fieldname]->label = $field->label;
		  $item->onDemandFields[$fieldname]->noaccess = false;
		  $item->onDemandFields[$fieldname]->field = & $field;
		  
		  // Render the (display) method of the field
		  if (!isset($field->{$method})) $field = FlexicontentFields::renderField($item, $field, $values, $method, $view);
		  if (!isset($field->{$method})) $field->{$method} = '';
		  $item->onDemandFields[$fieldname]->{$method} = & $field->{$method};
		  $_method_html[$item->id] = & $field->{$method};
		}
		
		// Return field(s) HTML (in case of multiple items this will be an array indexable by item ids
		if ( !is_array($item_arr) ) {
			$_method_html = @ $_method_html[$item_arr->id];   // Suppress field name not found ...
		}
  	return $_method_html;
	}
	
	/**
	 * Method to render a field
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function renderField(&$_item, &$_field, &$values, $method='display', $view=FLEXI_ITEMVIEW)
	{
		static $_trigger_plgs_ft = array();
		static $_created = array();
		$request_view = JRequest::getVar('view');
		
		// field's source code, can use this JRequest variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		JRequest::setVar("flexi_callview", $view);
		
		static $cparams = null;
		if ($cparams === null) {
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		}
		
		static $aid;
		if ($aid === null) {
			$user = JFactory::getUser();
			$aid = JAccess::getAuthorisedViewLevels($user->id);
		}
		
		if (is_array($_item) && is_string($_field)) ;  // ok
		else if (is_object($_item) && is_object($_field)) ; // ok
		else {
			echo "renderField() must be called with: renderField(array of items, 'field_name',...) or  renderField(item object, field object,...)<br/>";
			exit;
		}
		
		// If $method (e.g. display method) is already created,
		// then return the field object without recreating the method
		if ( is_object($_field) && isset($_field->{$method}) ) return $_field;
		
		// Handle multi-item call
		if (!is_array($_item)) {
			$all_items = array( & $_item );
			$field_name = $_field->name;
			$first_item_field = & $_field;
		} else {
			$all_items = & $_item ;
			$field_name = $_field;
			$item = reset($_item);
			$first_item_field = & $item->fields[$field_name];
		}
		
		// Skip items that have already created the given 'method' for this 'field' and for the given view
		// we also use VIEW so that we can reder different displays of the field e.g. item VIEW and module view
		$items = array();
		foreach ($all_items as $_item_) {
			// Commented out, TODO: examine if we can return cached value here !!
			//if (isset($_created[$view][$method][$field_name][$_item_->id])) continue;  // Skip this item
			$items[] = $_item_;
			$_created[$view][$method][$field_name][$_item_->id] = 1;
		}
		// Check if item array is empty (all items already rendered)
		if (empty($items)) {
			return !is_object($_field) ? null : $_field;
		}
		
		// ***********************************************************************************************************
		// Create field parameters (and values) in an optimized way, and also apply Type Customization for CORE fields
		// ***********************************************************************************************************
		foreach($items as $item) {
			$field = is_object($_field) ? $_field : $item->fields[$field_name];  // only rendering 1 item the field object was given
			$field->item_id = (int)$item->id;  // Some code may make use of this
			
			// CHECK IF only rendering single field object for a single item  -->  thus we need to use custom values if these were given !
			// NOTE: values are overwritten by onDisplayCoreFieldValue() of CORE fields, and only used by onDisplayFieldValue() of CUSTOM fields
			if ( is_object($_field) && $values!==null ) {
				// CUSTOM VALUEs give for single field rendering, TODO (maybe): in future we may make values an array indexed by item ID
				$field->value = $values;
			} else {
				// CUSTOM VALUEs not given or rendering multiple items
				$field->value = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			}
			
			FlexicontentFields::loadFieldConfig($field, $item);
		}
		
		
		// ***********************************************************************************************
		// Return if field is in a group, since field display should be created only by the grouping field
		// ***********************************************************************************************
		/*if ( $first_item_field->parameters->get('use_ingroup', 0) )  {
			if (is_object($_field)) $_field->{$method} = '
				<span class="fc-mssg-inline fc-note">
					This field ('.$field->label.') is configured for <b>grouping</b>. <br/>Please use a <b>grouping field</b> to edit and display this field
				</span>
			';
			return !is_object($_field) ? null : $_field;
		}*/
		
		
		// **********************************************
		// Return no access message if user has no ACCESS
		// **********************************************
		
		// Calculate has_access flag if it is missing ... FLEXI_ACCESS ... no longer supported here ...
		if ( !isset($first_item_field->has_access) ) {
			$first_item_field->has_access = FLEXI_J16GE ? in_array($first_item_field->access, $aid) : $first_item_field->access <= $aid;
		}
		if ( !$first_item_field->has_access ) {
			// Get configuration out of the field of the first item, any CONFIGURATION that is different
			// per content TYPE, must not use this, instead it must be retrieved inside the item loops
			$show_acc_msg = $first_item_field->parameters->get('show_acc_msg', 0);
			$no_acc_msg = $first_item_field->parameters->get('no_acc_msg');
			$no_acc_msg = JText::_( $no_acc_msg ? $no_acc_msg : 'FLEXI_FIELD_NO_ACCESS');
			foreach($items as $item) {
				$field = is_object($_field) ? $_field : $item->fields[$field_name];  // only rendering 1 item the field object was given
				$field->$method = $show_acc_msg ? '<span class="fc-noauth fcfield_inaccessible_'.$field->id.'">'.$no_acc_msg.'</span>' : '';
			}
			
			// Return field only if single item was given (with a field object)
			return !is_object($_field) ? null : $_field;
		}
		
		
		// ***************************************************************************************************
		// Create field HTML by calling the appropriate DISPLAY-CREATING field plugin method.
		// NOTE 1: We will not pass the 'values' method parameter to the display-creating field method,
		//         instead we have set it above as the 'value' field property
		// NOTE 2: For CUSTOM fields the 'values' method parameter is prefered over the 'value' field property
		//         For CORE field, both the above ('values' method parameter and 'value' field property) are
		//         ignored and instead the other method parameters are used, along with the ITEM properties
		// ****************************************************************************************************
		// Log content plugin and other performance information
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ($print_logging_info)  global $fc_run_times;
		if ($print_logging_info)  $start_microtime = microtime(true);
		
		if ($first_item_field->iscore == 1)  // CORE field
		{
			//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$_field, $_item, &$_item->parameters, $_item->tags, $_item->cats, $_item->favs, $_item->fav, $_item->vote ));
			//FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$_field, & $_item, &$_item->parameters, $_item->tags, $_item->cats, $_item->favs, $_item->fav, $_item->vote, null, $method ) );
			$items_params = null;
			FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$_field, &$items, &$items_params, false, false, false, false, false, null, $method ) );
		}
		else                      // NON CORE field
		{
			// DOES NOT support multiple items YET, do it 1 at a time
			foreach($items as $item) {
				$field = is_object($_field) ? $_field : $item->fields[$field_name];  // only rendering 1 item the field object was given
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
				FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array(&$field, $item, null, $method) );
				if ($field->parameters->get('use_ingroup', 0) && empty($field->ingroup) && is_array($field->$method)) $field->$method = implode('', $field->$method);
			}
		}
		if ($print_logging_info) {
			$field_render_time = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			if ( isset($fc_run_times['render_subfields'][$item->id."_".$field->id]) ) {
				$field_render_time = $field_render_time - $fc_run_times['render_subfields'][$item->id."_".$field->id];
				@$fc_run_times['render_subfields'][$field->field_type] += $fc_run_times['render_subfields'][$item->id."_".$field->id];
				unset($fc_run_times['render_subfields'][$item->id."_".$field->id]);
			}
			@$fc_run_times['render_field'][$field->field_type] += $field_render_time;
		}
		
		
		// *****************************************
		// Trigger content plugins on the field text
		// *****************************************
		
		// Get configuration out of the field of the first item, if this CONFIGURATION was
		// different per content TYPE, then we should move this inside the item loop (below)
		if ( !is_array($_item) ) {
			$field = $_field;
		} else {
			$item = reset($items);
			$field = $item->fields[$field_name];
		}
		if ( !isset($_trigger_plgs_ft[$field_name]) ) {
			$_t = $field->parameters->get('trigger_onprepare_content', 0);
			if ($request_view=='category' && $view=='category') $_t = $_t && $field->parameters->get('trigger_plgs_incatview', 1);
			$_trigger_plgs_ft[$field_name] = $_t;
		}
		
		// DOES NOT support multiple items, do it 1 at a time
		if ( $_trigger_plgs_ft[$field_name] )
		{
			//echo "RENDER: ".$field_name."<br/>";
			foreach($items as $item) {
				$field = is_object($_field) ? $_field : $item->fields[$field_name];  // only rendering 1 item the field object was given
				if ($print_logging_info)  $start_microtime = microtime(true);	
				FlexicontentFields::triggerContentPlugins($field, $item, $method, $view);
				if ( $print_logging_info ) @$fc_run_times['content_plg'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
		}
		
		// Return field only if single item was given (with a field object)
		return !is_object($_field) ? null : $_field;
	}
	
	
	/**
	 * Method to selectively trigger content plugins for the text of the specified field
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function triggerContentPlugins(&$field, &$item, $method, $view=FLEXI_ITEMVIEW) 
	{
		$debug = false;
		static $_plgs_loaded = array();
		static $_fields_plgs = array();
		
		static $_initialize = false;
		static $_view, $_option, $limitstart;
		static $dispatcher, $fcdispatcher;
		
		//$flexiparams = JComponentHelper::getParams('com_flexicontent');
		//$print_logging_info = $flexiparams->get('print_logging_info');
		// Log content plugin and other performance information
		//if ($print_logging_info) 	global $fc_run_times;
		
		if (!$_initialize) {
			// some request and other variables
			$_view   = JRequest::getVar('view');
			$_option = JRequest::getVar('option');
			$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
			$_initialize = true;
			
			// ***********************************************************************
			// We use a custom Dispatcher to allow selective Content Plugin triggering
			// ***********************************************************************
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'dispatcher.php');
			$dispatcher   = JDispatcher::getInstance();
			$fcdispatcher = FCDispatcher::getInstance_FC($debug);
		}
		
		
		// CASE: FLEXIcontent item view:
		// Set triggering 'context' to 'com_content.article', (and also set the 'view' request variable)
		if ($view == FLEXI_ITEMVIEW) {
		  JRequest::setVar('view', 'article');
		  $context = 'com_content.article';
		}
		
		// ALL OTHER CASES: (FLEXIcontent category, FLEXIcontent module, etc),
		// Set triggering 'context' to 'com_content.category', (and also set the 'view' request variable)
		else {
		  JRequest::setVar('view', 'category');
		  $context = 'com_content.category';
		}
		
		
		if ($debug) echo "<br><br>Executing plugins for <b>".$field->name."</b>:<br>";
		
		if ( !@$_fields_plgs[$field->name] )
		{
			// Make sure the necessary plugin are already loaded, but do not try to load them again since this will harm performance
			if (!$field->parameters->get('plugins'))
			{
				$_plgs = null;
				if (!@$_plgs_loaded['__ALL__']) {
					JPluginHelper::importPlugin('content', $plugin = null, $autocreate = true, $dispatcher);
					$_plgs_loaded['__ALL__'] = 1;
				}
			}
			else
			{
				$_plgs = $field->parameters->get('plugins');
				$_plgs = $_plgs ? $_plgs : array();
				$_plgs = is_array($_plgs) ? $_plgs : explode('|', $_plgs);  // compatibility because old versions did not JSON encode the parameters
				
				if (!@$_plgs_loaded['__ALL__'])  foreach ($_plgs as $_plg)  if (!@$_plgs_loaded[$_plg]) {
					JPluginHelper::importPlugin('content', $_plg, $autocreate = true, $dispatcher);
					$_plgs_loaded[$_plg] = 1;
				}
			}
			
			$_fields_plgs[$field->name] = $_plgs;
		}
		
		$plg_arr = $_fields_plgs[$field->name];
		
		// Suppress some plugins from triggering for compatibility reasons, e.g.
		// (a) jcomments, jom_comment_bot plugins, because we will get comments HTML manually inside the template files
		$suppress_arr = array('jcomments', 'jom_comment_bot');
		FLEXIUtilities::suppressPlugins($suppress_arr, 'suppress' );
		
		// Initialize field for plugin triggering
		$method_text = isset($field->{$method}) ? $field->{$method} : '';
		$field->text = $method_text;
		$field->introtext = $method_text;  // needed by some plugins that do not use or clear ->text property
		$field->created_by = $item->created_by;
		$field->title = $item->title;
		$field->slug = isset($item->slug) ? $item->slug : $item->id;
		$field->sectionid = !FLEXI_J16GE ? $item->sectionid : false;
		$field->catid = $item->catid;
		$field->catslug = @$item->categoryslug;
		$field->fieldid = $field->id;
		$field->id = $item->id;
		$field->state = $item->state;
		$field->type_id = $item->type_id;
		
		// Set the 'option' to 'com_content' but set a flag 'isflexicontent' to indicate triggering from inside FLEXIcontent ... code
		JRequest::setVar('option', 'com_content');
		JRequest::setVar("isflexicontent", "yes");
		
		// Trigger content plugins on field's HTML display, as if they were a "joomla article"
		if (FLEXI_J16GE) $results = $fcdispatcher->trigger('onContentPrepare', array ($context, &$field, &$item->parameters, $limitstart), $plg_arr);
		else             $results = $fcdispatcher->trigger('onPrepareContent', array (&$field, &$item->parameters, $limitstart), false, $plg_arr);
		
		// Restore 'view' and 'option' request variables
		JRequest::setVar('view', $_view);
		JRequest::setVar('option', $_option);
		
		$field->id = $field->fieldid;
		$field->{$method} = $field->text;
		
		// Restore suppressed plugins
		FLEXIUtilities::suppressPlugins( $suppress_arr,'restore' );
	}


	/**
	 * Method to get the fields in their positions
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function &renderPositions(&$items, $view = FLEXI_ITEMVIEW, $params = null, $use_tmpl = true, & $_rendered = array())
	{
		if (!$items) return;
		if (!$params) return $items;
		
		if ($view == 'category')			$layout = 'clayout';
		if ($view == FLEXI_ITEMVIEW)	$layout = 'ilayout';
		
		// field's source code, can use this JRequest variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		JRequest::setVar("flexi_callview", $view);

		if ( $use_tmpl && ($view == 'category' || $view == FLEXI_ITEMVIEW) ) {
		  $fbypos = flexicontent_tmpl::getFieldsByPositions($params->get($layout, 'default'), $view);
		  //$onDemandOnly = false;
		} else { // $view == 'module', or other
			// Create a fake template position, for fields defined via parameters
		  $fbypos[0] = new stdClass();
		  $fbypos[0]->fields = explode(',', $params->get('fields'));
		  $fbypos[0]->methods = explode(',', $params->get('methods'));
		  $fbypos[0]->position = $view;
		  //$onDemandOnly = true;
		}
		
		$always_create_fields_display = $params->get('always_create_fields_display',0);
		
		// Render some fields by default, this is done for compatibility reasons, but avoid rendering these fields again (2nd time),
		// since some of them may also be in template positions. NOTE: this is not needed since renderField() should detect this case
		if ( /*!$onDemandOnly &&*/  $always_create_fields_display != 3) { // value 3 means never create for any view (blog template incompatible)
			
			$item = reset($items); // get the first item ... so that we can get the name of CORE fields out of it
			
		  // 'description' item field is implicitly used by category layout of some templates (blog), render it
		  $custom_values = null;
		  if ($view == 'category') {
		    if (isset($item->fields['text']) && !isset($_rendered['ALL']['text'])) {
		    	$_field_name_ = 'text';
		    	FlexicontentFields::renderField($items, $_field_name_, $custom_values, $method='display', $view);
		    }
		    $_rendered['ALL']['text'] = 1;
		  }
			// 'core' item fields are IMPLICITLY used by some item layout of some templates (blog), render them
			else if ($view == FLEXI_ITEMVIEW) {
				foreach ($item->fields as $field) {
					if ($field->iscore && !isset($_rendered['ALL'][$field->name])) {
						$_field_name_ = $field->name;
						FlexicontentFields::renderField($items, $_field_name_, $custom_values, $method='display', $view);
					}
				}
		    $_rendered['ALL']['core'] = 1;
			}
		}
		
		
		// *** RENDER fields on DEMAND, (if present in template positions)
		foreach ($fbypos as $pos) {
		  // RENDER fields if they are present in a template position (or in a dummy template position ... e.g. when called by module)
			foreach ($pos->fields as $c => $f) {
				
				// CORE/CUSTOM: Render field (if already rendered above, the function will return result immediately)
				$method = (isset($pos->methods[$c]) && $pos->methods[$c]) ? $pos->methods[$c] : 'display';
				
				// Render ANY CORE field with single call for all items, CORE fields are assigned to ALL types,
				// try to get field out of first item, if it does not exist, then field is a CUSTOM field
				$item = reset($items);
				$field = @ $item->fields[$f];
				
				if ($field && $field->iscore)
				{
					// Check if already rendered
					if ( !isset($_rendered['ALL']['core']) && !isset($_rendered['ALL'][$f]) )
					{
						// No custom values for CORE fields, values are decided inside the CORE field
						$values = null;
						FlexicontentFields::renderField($items, $f, $values, $method, $view);
					}
					$_rendered['ALL'][$f] = 1;
				}
				
				// Render ANY CUSTOM field with per item call
				// *** TODO: (future optimization) render a field at once for ALL ITEMs of SAME content type
				else foreach ($items as $item)
				{
					// Check that field with given name: $f exists for current item (AKA, that it is assigned to the item's type)
					if ( !isset($item->fields[$f]) )  continue;
					
					// Check if already rendered
					if ( isset($_rendered['ALL'][$f]) || isset($_rendered[$item->id][$f]) ) continue;
					
					// Get field and field values, currently, custom field values can be passed only for CUSTOM fields, OR versioned CORE/CUSTOM fields too ...
					$field  = $item->fields[$f];
					$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
					
					// Render the field's display
					$field 	= FlexicontentFields::renderField($item, $field, $values, $method, $view);
					$_rendered[$item->id][$f] = 1;
				}
				
				foreach ($items as $item)
				{
					// Check that field with given name: $f exists for current item (AKA, that it is assigned to the item's type)
					if ( !isset($item->fields[$f]) )  continue;
					$field = $item->fields[$f];
					
					// Skip field if empty display was produced
					if ( !isset($field->display) || !strlen($field->display) ) continue;
					
					// Set field display HTML/data in the template position,
					if (!isset($item->positions[$pos->position]))
						$item->positions[$pos->position] = new stdClass();
					$item->positions[$pos->position]->{$f} = new stdClass();
					
					$item->positions[$pos->position]->{$f}->id				= $field->id;
					$item->positions[$pos->position]->{$f}->id				= $field->id;
					$item->positions[$pos->position]->{$f}->name			= $field->name;
					$item->positions[$pos->position]->{$f}->label		= $field->parameters->get('display_label') ? $field->label : '';
					$item->positions[$pos->position]->{$f}->display	= $field->display;
				}
			}
		}
		return $items;
	}
	
	
	/**
	 * Method to get the values of the fields for multiple items at once
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function _getCustomValues(&$items, $view = FLEXI_ITEMVIEW)
	{
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);
		$version = $versioned_item ? $items[0]->version_id : 0;
		
		$item_ids = array();
		foreach ($items as $item) $item_ids[] = $item->id;
		
		$db = JFactory::getDBO();
		$query = 'SELECT field_id, value, item_id, valueorder, suborder'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id IN (' . implode(',', $item_ids) .')'
				.( $version ? ' AND version=' . (int)$version:'')
				.' AND value > "" '
				.' ORDER BY item_id, field_id, valueorder, suborder'  // first 2 parts are not needed ...
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();
		
		$fieldvalues = array();
		if ($values) foreach ($values as $v) {
			$fieldvalues[$v->item_id][$v->field_id][$v->valueorder - 1][$v->suborder - 1] = $v->value;
		}
		foreach ($fieldvalues as & $iv) {
			foreach ($iv as & $fv) {
				foreach ($fv as & $ov) {
					if (count($ov) == 1) $ov = reset($ov);
				}
				unset($ov);
			}
			unset($fv);
		}
		unset($iv);
		return $fieldvalues;
	}
	
	
	/**
	 * Method to get the tags
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getTags(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned fields in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->tags);
		
		$db = JFactory::getDBO();
		
		/*echo "_getTags <br/> \n";
		echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }
		print_r($cids);
		echo "<br/>";*/
		
		if ($versioned_item) {
			if (!count($items[0]->tags)) return array();
			$tids = $items[0]->tags;
			//echo "<pre>"; print_r($tids); echo "</pre>";
			$query 	= 'SELECT DISTINCT t.id, t.name, ' . $items[0]->id .' as itemid, '
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. " WHERE t.id IN ('" . implode("','", $tids) . "')"
				. ' AND t.published = 1'
				. ' ORDER BY t.name'
				;
		} else {
			$cids = array();
			foreach ($items as $item) { array_push($cids, $item->id); }
			$query 	= 'SELECT DISTINCT t.id, t.name, i.itemid,'
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
				. " WHERE i.itemid IN ('" . implode("','", $cids) . "')"
				. ' AND t.published = 1'
				. ' ORDER BY t.name'
				;
		}
		$db->setQuery( $query );
		$tags = $db->loadObjectList();
		
		// improve performance by doing a single pass of tags to aggregate them per item
		$taglists = array();
		foreach ($tags as $tag) {
			$taglists[$tag->itemid][] = $tag;
		}
		return $taglists;
	}

	/**
	 * Method to get the categories
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getCategories(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned fields in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->categories);
		
		$db = JFactory::getDBO();
		
		if ($versioned_item) {
			$catids = $items[0]->categories;
			$query 	= 'SELECT DISTINCT c.id, c.title, ' . $items[0]->id .' as itemid, '
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. " WHERE c.id IN ('" . implode("','", $catids) . "')"
				;
		} else {
			$cids = array();
			foreach ($items as $item) { array_push($cids, $item->id); }		
			$query 	= 'SELECT DISTINCT c.id, c.title, rel.itemid,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. " WHERE rel.itemid IN ('" . implode("','", $cids) . "')"
				;
		}
		$db->setQuery( $query );
		$cats = $db->loadObjectList();

		// improve performance by doing a single pass of cats to aggregate them per item
		$catlists = array();
		foreach ($cats as $cat) {
			$catlists[$cat->itemid][] = $cat;
		}
		return $catlists;
	}

	/**
	 * Method to get the nr of favourites
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getFavourites(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT itemid, COUNT(id) AS favs FROM #__flexicontent_favourites'
				. " WHERE itemid IN ('" . implode("','", $cids) . "')"
				. ' GROUP BY itemid'
				;
		$db->setQuery($query);
		$favs = $db->loadObjectList('itemid');

		return $favs;
	}

	/**
	 * Method to get the favourites of an user
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getFavoured(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$user = JFactory::getUser();

		$query 	= 'SELECT itemid, COUNT(id) AS fav FROM #__flexicontent_favourites'
				. " WHERE itemid IN ('" . implode("','", $cids) . "')"
				. " AND userid = '" . ((int)$user->id) ."'"
				. ' GROUP BY itemid'
				;
		$db->setQuery($query);
		$fav = $db->loadObjectList('itemid');

		return $fav;
	}

	/**
	 * Method to get the modifiers of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getModifiers(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned field of modifier in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->modified_by);
		
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }
		
		$query 	= 'SELECT i.id, u.name, u.username, u.email FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON '  .  ( $versioned_item ? 'u.id = '.$items[0]->modified_by : 'u.id = i.modified_by' )
				. " WHERE i.id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$modifiers = $db->loadObjectList('id');
		
		return $modifiers;
	}

	/**
	 * Method to get the authors of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getAuthors(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned fields in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);
		
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT i.id, u.name, i.created_by_alias as alias, u.username, u.email FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON '  .  ( $versioned_item ? 'u.id = '.$items[0]->created_by : 'u.id = i.created_by' )
				. " WHERE i.id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$authors = $db->loadObjectList('id');
		
		return $authors;
	}

	/**
	 * Method to get the types names of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getTypenames(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDBO();

		$type_ids = array();
		foreach ($items as $item) { $type_ids[$item->type_id]=1; }
		$type_ids = array_keys($type_ids);
		
		$query 	= 'SELECT id, name FROM #__flexicontent_types'
				. " WHERE id IN ('" . implode("','", $type_ids) . "')"
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('id');
		
		$typenames = array();
		foreach ($items as $item) {
			$typenames[$item->id] = new stdClass();
			$typenames[$item->id]->name = isset($types[$item->type_id]) ? $types[$item->type_id]->name : 'without type';
		}
		
		return $typenames;
	}

	/**
	 * Method to get the votes of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getVotes(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT * FROM #__content_rating'
				. " WHERE content_id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$votes = $db->loadObjectList('content_id');
		
		$query 	= 'SELECT *, field_id as extra_id FROM #__flexicontent_items_extravote'
				. " WHERE content_id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$extra_votes= $db->loadObjectList();
		
		// Assign each item 's extra votes to the item's votes as member variable "extra"
		foreach ($extra_votes as $extra_vote ) {
			$votes[$extra_vote->content_id]->extra[$extra_vote->extra_id] = $extra_vote;
		}
		
		return $votes;
	}
	
	
	
	
	
	// ***********************************************************
	// Methods for creating field configuration in an OPTMIZED way
	// ***********************************************************
	
	// Method to create field parameters in an optimized way, and also apply Type Customization for CORE fields
	static function loadFieldConfig(&$field, &$item, $name='', $field_type='', $label='', $desc='', $iscore=1) {
		$db = JFactory::getDBO();
		static $tparams = array();
		static $tinfo   = array();
		static $fdata   = array();
		static $no_typeparams = null;
		if ($no_typeparams) $no_typeparams = new JRegistry();
		static $is_form=null;
		if ($is_form===null) $is_form = JRequest::getVar('task')=='edit' && JRequest::getVar('option')=='com_flexicontent';
		
		// Create basic field data if no field given
		if (!empty($name)) {
			$field->iscore = $iscore;  $field->name = $name;  $field->field_type = $field_type;  $field->label = $label;  $field->description = $desc;  $field->attribs = '';
		}
		
		// Get Content Type parameters if not already retrieved
		$type_id = $item ? $item->type_id : 0;
		if ($type_id && ( !isset($tinfo[$type_id]) || !isset($tparams[$type_id]) ) )
		{
			$tinfo[$type_id] = $tparams[$type_id] = null;
			FlexicontentFields::_getTypeToCoreFieldParams ($type_id, $tinfo[$type_id], $tparams[$type_id]);
		}
		
		// Set Content Type parameters otherwise set empty defaults (e.g. new item form with not typeid set)
		$type_data_exist = $type_id && $tinfo[$type_id] && $tparams[$type_id] ;
		
		$typename   = $type_data_exist  ?  $tinfo[$type_id]['typename']    :  '';
		$typealias  = $type_data_exist  ?  $tinfo[$type_id]['typealias']   :  '';
		$tindex     = $type_data_exist  ?  $typename.'_'.$typealias        :  'no_type';
		if ($type_data_exist)  $typeparams = & $tparams[$type_id];  else  $typeparams = & $no_typeparams;
		

		// Create the (CREATED ONCE per field) SHARED object that will contain: (a) label, (b) description, (c) all (merged) field parameters
		// Create parameters once per custom field OR once per pair of:  Core FIELD type - Item CONTENT type
		if ( !isset($fdata[$tindex][$field->name]) ) {
			
			if ( !$field->iscore || !$type_id ) {
				
				// CUSTOM field or CORE field with no type
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = new JRegistry($field->attribs);
				if ($field->field_type=='maintext' && $fdata[$tindex][$field->name]->parameters->get('trigger_onprepare_content', '')==='') {
					$fdata[$tindex][$field->name]->parameters->set(1);  // Default for maintext (description field) is to trigger plugins
				}
				
			} else {
				
				$pn_prefix = $field->field_type!='maintext' ? $field->name : $field->field_type;
				
				// Initialize an empty object, and create parameters object of the field
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = new JRegistry($field->attribs);
				
				// SET a type specific label, description for the current CORE  field (according to current language)
				$field_label_type = $tparams[$type_id]->get($pn_prefix.'_label', '');
				$field_desc_type = $tparams[$type_id]->get($pn_prefix.($is_form ? '_desc' : '_viewdesc'), '');
				FlexicontentFields::_getLangSpecificValue ($type_id, $field_label_type, 'label', $fdata[$tindex][$field->name]);
				FlexicontentFields::_getLangSpecificValue ($type_id, $field_desc_type, 'description', $fdata[$tindex][$field->name]);
				
				// Override field parameters with Type specific Parameters
				if ( isset($tinfo[$type_id]['params'][$pn_prefix]) ) {
					foreach ($tinfo[$type_id]['params'][$pn_prefix] as $param_name => $param_value) {
						$fdata[$tindex][$field->name]->parameters->set( $param_name, $param_value) ;
					}
				}
				
				// SPECIAL CASE: check if it exists a FAKE (custom) field that customizes CORE field per Content Type
				$query = "SELECT attribs, published FROM #__flexicontent_fields WHERE name=".$db->Quote($field->name."_".$typealias);
				$db->setQuery($query);  //echo $query;
				$data = $db->loadObject(); //print_r($data);
				if (@$data->published) JFactory::getApplication()->enqueueMessage(__FUNCTION__."(): Please unpublish plugin with name: ".$field->name."_".$typealias." it is used for customizing a core field",'error');
				
				// Finally merge custom field parameters with the type specific parameters ones
				if ($data) {
					$ts_params = new JRegistry($data->attribs);
					$fdata[$tindex][$field->name]->parameters->merge($ts_params);
				}
			}
		}
		
		// Set custom label, description or maintain default
		$field->label       =  isset($fdata[$tindex][$field->name]->label)        ?  $fdata[$tindex][$field->name]->label        :  $field->label;
		$field->description =  isset($fdata[$tindex][$field->name]->description)  ?  $fdata[$tindex][$field->name]->description  :  $field->description;
		$field->label = JText::_($field->label);
		
		// Finally set field's parameters, but to clone ... or not to clone, better clone to allow customizations for individual item fields ...
		$field->parameters = clone($fdata[$tindex][$field->name]->parameters);
		
		return $field;
	}
	
	
	// Method to override PARAMETER VALUES with their Type Specific values
	static function _getTypeToCoreFieldParams ($type_id, & $tinfo, & $tparams) {
		
		static $core_field_names = null;
		if ( $core_field_names == null ) {
			$query = "SELECT field_type FROM #__flexicontent_fields WHERE iscore=1";
			//echo $query;
			$db = JFactory::getDBO();
			$db->setQuery($query);
			$core_field_names = $db->loadColumn();
			
			$core_field_names[] = 'maintext';
			$core_field_names = array_flip($core_field_names);
			unset($core_field_names['text']);
		}
		
		$query = 'SELECT t.attribs, t.name, t.alias FROM #__flexicontent_types AS t WHERE t.id = ' . $type_id;
		$db = JFactory::getDBO();
		$db->setQuery($query);
		$typedata = $db->loadObject();
		if ( $typedata ) {
			$tinfo['typename']  = $typedata->name;
			$tinfo['typealias'] = $typedata->alias;
			$tparams = new JRegistry($typedata->attribs);
			
			$_tparams = $tparams->toArray();
			$tinfo['params'] = array();
			
			foreach ($_tparams as $param_name => $param_value) {
				$res = preg_split('/_/', $param_name, 2);
				if ( count($res) < 2 ) continue;
				
				$o_field_type = $res[0];  $o_param_name = $res[1];
				if ( !isset($core_field_names[$o_field_type]) ) continue;
				
				//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
				$skipparam = false;
				
				if ( strlen($param_value) ) {
					/*$skipparam = in_array($o_param_name, array('label','desc','viewdesc'));
					if ($skipparam) continue;*/
					$tinfo['params'][$o_field_type][$o_param_name] = $param_value;
					//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
				}
			}
			//echo "<pre>"; print_r($tinfo['params']); echo "</pre>";
		}
	}
	
	
	// Method get a language specific value from given Content Type (or other) Data
	static function _getLangSpecificValue ($type_id, $type_prop_value, $prop_name, & $fdata)
	{
		//--. Get a 2 character language tag
		static $lang = null;
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
		$urlLang  = JRequest::getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
		
		// --. SET a type specific label for the current field
		// a. Try field label to get for current language
		$result = preg_match("/(\[$lang\])=([^[]+)/i", $type_prop_value, $matches);
		if ($result) {
			$fdata->{$prop_name} = $matches[2];
		} else if ($type_prop_value) {
			// b. Try to get default for all languages
			$result = preg_match("/(\[default\])=([^[]+)/i", $type_prop_value, $matches);
			if ($result) {
				$fdata->{$prop_name} = $matches[2];
			} else {
				// c. Check that no languages specific string are defined
				$result = preg_match("/(\[??\])=([^[]+)/i", $type_prop_value, $matches);
				if (!$result) {
					$fdata->{$prop_name} = $type_prop_value;
				}
			}
		} else {
			// d. Maintain field 's default label
		}
	}
	
	
	
	
	
	// **************************************************************************************
	// Methods (a) for INDEXED FIELDs, and (b) for replacement field values / item properties
	// **************************************************************************************
	
	// Common method to get the allowed element values (field values with index,label,... properties) for fields that use indexed values
	static function indexedField_getElements(&$field, $item, $extra_props=array(), &$item_pros=true, $create_filter=false, $and_clause=false)
	{
		static $_elements_cache = null;
		if ( isset($_elements_cache[$field->id]) ) return $_elements_cache[$field->id];
		$canCache = true;
		
		$sql_mode = $field->parameters->get( 'sql_mode', 0 ) ;   // For fields that use this parameter
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		$lang_filter_values = $field->parameters->get( 'lang_filter_values', 1);
		
		$default_extra_props = array('image','valgroup');
		
		if ($create_filter) {
			$filter_customize_options = $field->parameters->get('filter_customize_options', 0);
			$filter_custom_options    = $field->parameters->get('filter_custom_options', '');
			if ( $filter_customize_options && $filter_custom_options) {
				// Custom query for value retrieval
				$sql_mode =  $filter_customize_options==1;
				$field_elements = $filter_custom_options;
			} else if ( !$field_elements ) {
				$sql_mode = 1;
				$field_elements = "SELECT value, value as text FROM #__flexicontent_fields_item_relations as fir WHERE field_id='{field_id}' AND value != '' GROUP BY value";
			}
			// Set parameters may be used later
			$field->parameters->set('sql_mode', $sql_mode);
			$field->parameters->set('field_elements', $field_elements);
		}
		
		// TODO: examine this in combination with canCache
		//$field_elements = FlexicontentFields::replaceFieldValue( $field, $item, $field_elements, 'field_elements' );
		
		if ($sql_mode) {  // SQL mode, parameter field_elements contains an SQL query
			
			$db = JFactory::getDBO();
			
			// Get/verify query string, check if item properties and other replacements are allowed and replace them
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$query = FlexicontentFields::doQueryReplacements($field_elements, $field, $item, $item_pros, $canCache);
			if ($query && $and_clause) {
				$query = preg_replace('/_valgrp_in_/ui', $and_clause, $query);
			}
			
			// Execute SQL query to retrieve the field value - label pair, and any other extra properties
			if ( $query ) {
				$db->setQuery($query);
				$results = $db->loadObjectList('value');
			}
			if ($results && $lang_filter_values) {
				foreach ($results as $val=>$result) {
					$results[$val]->text  = JText::_($result->text);  // the text label
				}
			}
			
			// !! CHECK: DB query failed or produced an error (AN EMPTY ARRAY IS NOT AN ERROR)
			if (!$query || !is_array($results)) {
				if ( $canCache && !$and_clause ) $_elements_cache[$field->id] = false;
				return false;
			}
			
		} else { // Elements mode, parameter field_elements contain list of allowed values
			
			// Parse the elements used by field unsetting last element if empty
			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if ( empty($listelements[count($listelements)-1]) ) {
				unset($listelements[count($listelements)-1]);
			}
			
			$props_needed = 2 + count($extra_props);
			// Split elements into their properties: value, label, extra_prop1, extra_prop2
			$listarrays = array();
			$results = array();
			foreach ($listelements as $listelement)
			{
				$listelement_props  = preg_split("/[\s]*::[\s]*/", $listelement);
				if (count($listelement_props) < $props_needed)
				{
					if (count($listelement_props)==3 && $extra_props[1]=='valgroup') {
						$listelement_props[3] = '';
					} else {
						echo "Error in field: ".$field->label." while splitting element: ".$listelement." properties needed: ".$props_needed." properties found: ".count($listelement_props);
						return ($_elements_cache[$field->id] = false);
					}
				}
				$val = $listelement_props[0];
				$results[$val] = new stdClass();
				$results[$val]->value = $listelement_props[0];
				$results[$val]->text  = $lang_filter_values ? JText::_($listelement_props[1]) : $listelement_props[1];
				$el_prop_count = 2;
				if (!empty($extra_props)) {
					foreach ($extra_props as $extra_prop) {
						$results[$val]->{$extra_prop} = @ $listelement_props[$el_prop_count];  // extra property for fields that use it
						$el_prop_count++;
					}
				} else {
					foreach ($default_extra_props as $extra_prop) {
						$results[$val]->{$extra_prop} = @ $listelement_props[$el_prop_count];  // extra property for fields that use it
						$el_prop_count++;
					}
				}
			}
			
		}
		
		// Return found elements, caching them if possible (if no item specific elements are used)
		if ( $canCache && !$and_clause ) $_elements_cache[$field->id] = & $results;
		return $results;
	}
	
	
	// Common method to map element value INDEXES to value objects for fields that use indexed values
	static function indexedField_getValues(&$field, &$elements, $value_indexes, $prepost_prop='text')
	{
		// Check for empty values
		if ( !is_array($value_indexes) && !strlen($value_indexes) ) return array();
		// Make sure indexes is an array 
		$value_indexes = !is_array($value_indexes) ? array($value_indexes) : $value_indexes;
		
		$pretext=''; $posttext='';
		if ( $prepost_prop ) {
			$pretext  = $field->parameters->get( 'pretext', '' ) ;
			$posttext = $field->parameters->get( 'posttext', '' ) ;
			$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
			
			$pretext 	= $remove_space ? $pretext  : $pretext . ' ';
			$posttext	= $remove_space ? $posttext : ' ' . $posttext;
		}
		
		// Get the labels of used values in an display[] array
		$values = array();
		foreach($value_indexes as $val_index) {
			if ( !strlen($val_index) ) continue;
			if ( !isset($elements[$val_index]) ) continue;
			$values[$val_index] = get_object_vars($elements[$val_index] );
			//print_r($values[$val_index]); echo "<br/>\n";
			if ($prepost_prop) {
				$values[$val_index][$prepost_prop] = $pretext . $values[$val_index][$prepost_prop] . $posttext;
			}
		}
		
		return $values;
	}
	
	
	// Helper method to replace item properties for the SQL value mode for various fields
	static function doQueryReplacements(&$query, &$field, &$item, &$item_pros=true, $canCache=null)
	{
		// replace item properties
		preg_match_all("/{item->[^}]+}/", $query, $matches);
		$canCache = count($matches[0]) == 0;
		if ( !$item_pros && count($matches[0]) ) { $item_pros = count($matches[0]); return ''; }
		
		// If needed replace item properties, loading the item if not already loaded
		if (count($matches[0]) && !$item) {
			if ( !@$field->item_id ) { echo __FUNCTION__."(): field->item_id is not set"; return; }
			$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			$item->load( $field->item_id );
		}
		foreach ($matches[0] as $replacement_tag) {
			$replacement_value = '$'.substr($replacement_tag, 1, -1);
			eval ("\$replacement_value = \"$replacement_value\";");
			$query = str_replace($replacement_tag, $replacement_value, $query);
		}
		
		// replace field properties
		if ($field) {
			preg_match_all("/{field->[^}]+}/", $query, $matches);
			foreach ($matches[0] as $replacement_tag) {
				$replacement_value = '$'.substr($replacement_tag, 1, -1);
				eval ("\$replacement_value = \" $replacement_value\";");
				$query = str_replace($replacement_tag, $replacement_value, $query);
			}
		}
		
		// replace current user language
		$query = str_replace("{curr_userlang_shorttag}", flexicontent_html::getUserCurrentLang($short_tag=true), $query);
		$query = str_replace("{curr_userlang_fulltag}", flexicontent_html::getUserCurrentLang($short_tag=false), $query);
		return $query;
	}
	
	
	// Helper method to replace a field value inside a given named variable of a given item/field pair
	static function replaceFieldValue( &$field, &$item, $variable, $varname, & $cacheable = false )
	{
		static $parsed = array();
		static $d;
		static $c;
		
		if (JFactory::getApplication()->isAdmin()) return '';
		
		// Parse field variable if not already parsed
		if ( !isset($parsed[$field->id][$varname]) )
		{
			$parsed[$field->id][$varname] = true;
			
			$result = preg_match_all("/\{\{([a-zA-Z_0-9]+)(##)?([0-9]+)?(##)?([a-zA-Z_0-9]+)?\}\}/", $variable, $field_matches);
			if ($result) {
				$d[$field->id][$varname]['fulltxt']   = $field_matches[0];
				$d[$field->id][$varname]['fieldname'] = $field_matches[1];
				$d[$field->id][$varname]['valueno']   = $field_matches[3];
				$d[$field->id][$varname]['propname']  = $field_matches[5];
			} else {
				$d[$field->id][$varname]['fulltxt']   = array();
				$d[$field->id][$varname]['valueno']   = false;
			}
			
			$result = preg_match_all("/\{\{(item->)([a-zA-Z_0-9]+)\}\}/", $variable, $field_matches);
			if ($result) {
				$c[$field->id][$varname]['fulltxt']   = $field_matches[0];
				$c[$field->id][$varname]['propname']  = $field_matches[2];
			} else {
				$c[$field->id][$varname]['fulltxt']   = array();
			}
			
			if ( !count($d[$field->id][$varname]['fulltxt']) && !count($c[$field->id][$varname]['fulltxt']) ) {
				$cacheable = true;
			}
		}
		
		// Replace variable
		foreach($d[$field->id][$varname]['fulltxt'] as $i => $fulltxt)
		{
			$fieldname = $d[$field->id][$varname]['fieldname'][$i];
			$valueno   = $d[$field->id][$varname]['valueno'][$i] ? (int) $d[$field->id][$varname]['valueno'][$i] : 0;
			$propname  = $d[$field->id][$varname]['propname'][$i] ? $d[$field->id][$varname]['propname'][$i] : '';
			
			$fieldid = @ $item->fields[$fieldname]->id;
			$value   = @ $item->fieldvalues[$fieldid][$valueno];
			
			if ( !$fieldid ) {
				$value = 'Field with name: '.$fieldname.' not found';
				$variable = str_replace($fulltxt, $value, $variable);
				continue;
			}
			
			$is_indexable = $propname && preg_match("/^_([a-zA-Z_0-9]+)_$/", $propname, $prop_matches) && ($propname = $prop_matches[1]);
			if ($fieldid <= 14 ) {
				if ($fieldid==13) {
					$value = @ $item->categories[$valueno]->{$propname};
				} else if ($fieldid==14) {
					$value = @ $item->tags[$valueno]->{$propname};
				}
			} else if ( $is_indexable ) {
				if ( $propname!='value' ) // no need for value to retrieve custom elements
				{
					$extra_props = $propname!='text' ? array($propname) : array();  // this will work only if field has a single extra property
					$extra_props = array();
					if ( !isset($item->fields[$fieldname]->parameters) ) {
						FlexicontentFields::loadFieldConfig($item->fields[$fieldname], $item);
					}
					$elements = FlexicontentFields::indexedField_getElements( $item->fields[$fieldname], $item, $extra_props );
					$value = @ $elements[$value]->{$propname};
				}
			} else if ( $propname ) {
				$value = @ unserialize ( $value );
				$value = @ $value[$propname];
			}
			$variable = str_replace($fulltxt, $value, $variable);
			//echo "<pre>"; print_r($item->fieldvalues[$fieldid]); echo "</pre>"; echo "Replaced $fulltxt with ITEM field VALUE: $value <br>";
		}
		
		
		// Replace variable
		foreach($c[$field->id][$varname]['fulltxt'] as $i => $fulltxt)
		{
			$propname = $c[$field->id][$varname]['propname'][$i];
			
			if ( !isset($item->{$propname}) ) {
				$value = 'Item property with name: '.$propname.' not found';
				$variable = str_replace($fulltxt, $value, $variable);
				continue;
			}
			$value = $item->{$propname};
			
			$variable = str_replace($fulltxt, $value, $variable);
			//echo "<pre>"; echo "</pre>"; echo "Replaced $fulltxt with ITEM property VALUE: $value <br>";
		}
		
		// Return variable after all replacements
		return $variable;
	}
	
	
	
	
	
	// *********************************************************************
	// Methods for getting fields that support BASIC / ADVANCED search modes
	// *********************************************************************
	
	// Method to get various - SETs - of search fields, according to given limitations
	// Param 'search_type' : search, filter, all-search, dirty-search, dirty-nosupport, non-search
	static function getSearchFields($key='name', $indexer='advanced', $search_fields=null, $content_types=null, $load_params=true, $item_id=0, $search_type='all-search')
	{
		$db = JFactory::getDBO();
		static $sp, $nsp;
		
		if ($search_type=='search') {   // All fields marked as text-searchable (also are published)
			$where = $indexer=='basic' ? ' f.issearch IN (1,2)' : ' f.isadvsearch IN (1,2) ';
			$where = '('.$where.' AND f.published = 1)';
		} else if ($search_type=='filter') {   // All fields marked as filterable (also are published)
			$where = $indexer=='basic' ? ' f.isfilter IN (1,2)' : ' f.isadvfilter IN (1,2) ';
			$where = '('.$where.' AND f.published = 1)';
		} else if ($search_type=='all-search') {   // ALL fields that must enter values in search index (also are published)
			$where = $indexer=='basic' ? ' f.issearch IN (1,2)' : ' ( f.isadvsearch IN (1,2) OR f.isadvfilter IN (1,2) )';
			$where = '('.$where.' AND f.published = 1)';
		} else if ($search_type=='dirty-search' || $search_type=='dirty-nosupport') {     // ONLY 'dirty' search fields (also are published)
			$where = $indexer=='basic' ? ' f.issearch = 2' : ' ( f.isadvsearch = 2 OR f.isadvfilter = 2 )';
			$where = '('.$where.' AND f.published = 1)';
		} else if ($search_type=='non-search') {     // ALL non-search fields (either OFF or unpublished)
			$where = $indexer=='basic' ? ' f.issearch IN (-1,0)' : ' ( f.isadvsearch IN (-1,0) AND f.isadvfilter IN (-1,0) )';
			$where = '('.$where.' OR f.published = 0)';
		} else {
			die(__FUNCTION__ . "(): unknown value for 'search_type' parameter"); // nothing to TODO
		}
		
		$where .=
			(!empty($search_fields) && is_array($search_fields) ? " AND f.name IN (".implode(',', $search_fields).") " : "").       // Limit to given search fields list
			(!empty($search_fields) && is_string($search_fields) ? " AND f.name IN (".$search_fields.") " : "").       // Limit to given search fields list
			(!empty($content_types) ? " AND ftr.type_id IN (".implode(',', $content_types).") " : "")   // Limit to given contnt types list
			;
		
		$query = 'SELECT f.*'
			.' FROM #__flexicontent_fields AS f'
			.' JOIN #__flexicontent_fields_type_relations AS ftr ON ftr.field_id = f.id'
			.' WHERE '. $where 
			.' GROUP BY f.id'
			.' ORDER BY '.(($content_types && count($content_types)==1) ? ' ftr.ordering, f.name' : ' f.ordering, f.name') // if single type given then retrieve ordering for fields of this type
		;
		
		if (! isset($sp[$query]) )
		{
			$db->setQuery($query);
			$fields = $db->loadObjectList($key);
			
			$sp_fields = array();
			$nsp_fields = array();
			foreach ($fields as $field_id => $field)
			{
				// Skip fields not being capable of advanced/basic search
				if ( $indexer=='basic' ) {
					if ( ! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, $search_type=='filter' ? 'supportfilter' : 'supportsearch') ) {
						$nsp_fields[$field_id] = $field;
						continue;
					}
				} else if ($search_type != 'non-search') {
					$no_supportadvsearch = ! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, 'supportadvsearch');
					$no_supportadvfilter = ! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, 'supportadvfilter');
					$skip_field = ($no_supportadvsearch && $search_type=='search')  ||  ($no_supportadvfilter && $search_type=='filter') ||
						($no_supportadvsearch && $no_supportadvfilter && in_array($search_type, array('all-search', 'dirty-nosupport') ) );
					if ($skip_field) {
						$nsp_fields[$field_id] = $field;
						continue;
					}
				}
				$field->item_id		= $item_id;
				$field->value     = !$item_id ? false : $this->getExtrafieldvalue($field->id, $version=0, $item_id);  // WARNING: getExtrafieldvalue() is Frontend method
				if ($load_params) $field->parameters = new JRegistry($field->attribs);
				$sp_fields[$field_id] = $field;
			}
			
			$sp[$query]  = $sp_fields;
			$nsp[$query] = $nsp_fields;
		}
		
		if ($indexer=='advanced' && $search_type=='dirty-nosupport')
			return $nsp[$query];
		else
			return $sp[$query];
	}
	
		
	// Method to get properties support for CORE fields
	static function getPropertySupport_BuiltIn()
	{
		static $info = null;
		if ($info!==null) return $info;
		
		$info = new stdClass();
		$info->core_search= array('title', 'maintext', 'tags', 'categories'   // CORE fields as text searchable
			, 'created', 'modified', 'createdby','modifiedby', 'type'
		);
		$info->core_filters = array('tags', 'categories'   // CORE fields as filters
			, 'created', 'modified', 'createdby', 'modifiedby', 'type', 'state'
		);
		$info->core_advsearch = array('title', 'maintext', 'tags', 'categories'   // CORE fields as text searchable for search view
			, 'created', 'modified', 'createdby','modifiedby', 'type'
		);
		$info->core_advfilters = array('title', 'maintext', 'tags', 'categories'   // CORE fields as filters for search view
			, 'created', 'modified', 'createdby', 'modifiedby', 'type', 'state'
		);
		$info->indexable_fields = array('categories', 'tags', 'type', 'select', 'selectmultiple', 'checkbox', 'checkboximage', 'radio', 'radioimage');
		
		return $info;
	}
	
	
	// Method to get used the properties supported by given field_type
	static function getPropertySupport($field_type, $iscore, $spname=null)
	{
		static $fi = null;
		if ($fi === null) $fi = FlexicontentFields::getPropertySupport_BuiltIn();
		
		static $cparams = null;
		if ($cparams === null) $cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		static $support_ft = array();
		if ( isset( $support_ft[$field_type] ) ) return !$spname ? $support_ft[$field_type] : $support_ft[$field_type]->{$spname};
		
		// Existing fields with field type
		if ($field_type)
		{
			// Make sure that the Joomla plugin that implements the type of current flexi field, has been imported
			//JPluginHelper::importPlugin('flexicontent_fields', $field_type);
			FLEXIUtilities::call_FC_Field_Func($iscore ? 'core' : $field_type, null, null);
			
			// Get Methods implemented by the field
			$classname	= 'plgFlexicontent_fields'.($iscore ? 'core' : $field_type);
			$classmethods	= get_class_methods($classname);
			
			// SEARCH/FILTER related properties
			$supportsearch    = $iscore ? in_array($field_type, $fi->core_search)     : in_array('onIndexSearch', $classmethods);
			$supportfilter    = $iscore ? in_array($field_type, $fi->core_filters)    : in_array('onDisplayFilter', $classmethods);
			$supportadvsearch = $iscore ? in_array($field_type, $fi->core_advsearch)  : in_array('onIndexAdvSearch', $classmethods);
			$supportadvfilter = $iscore ? in_array($field_type, $fi->core_advfilters) : in_array('onAdvSearchDisplayFilter', $classmethods);
			
			// ITEM FORM related properties
			$supportuntranslatable = !$iscore || $field_type=='maintext';
			$supportvalueseditable = !$iscore || $field_type=='maintext';
			$supportformhidden     = !$iscore || $field_type=='maintext';
			$supportedithelp       = !$iscore || $field_type=='maintext';
		
		// New fields without field type
		} else {
			
			// SEARCH/FILTER related properties
			$supportsearch    = false;
			$supportfilter    = false;
			$supportadvsearch = false;
			$supportadvfilter = false;
			
			// ITEM FORM related properties
			$supportuntranslatable = !$iscore;
			$supportvalueseditable = !$iscore;
			$supportformhidden     = !$iscore;
			$supportedithelp       = !$iscore;
		}
		
		// This property is usable only when Translation Groups are enabled
		$supportuntranslatable = $supportuntranslatable && flexicontent_db::useAssociations(); //$cparams->get('enable_translation_groups');
		
		$support_ft[$field_type] = new stdClass();
		$support_ft[$field_type]->supportsearch = $supportsearch;
		$support_ft[$field_type]->supportfilter = $supportfilter;
		$support_ft[$field_type]->supportadvsearch = $supportadvsearch;
		$support_ft[$field_type]->supportadvfilter = $supportadvfilter;
		$support_ft[$field_type]->supportuntranslatable = $supportuntranslatable;
		$support_ft[$field_type]->supportvalueseditable = $supportvalueseditable;
		$support_ft[$field_type]->supportformhidden = $supportformhidden;
		$support_ft[$field_type]->supportedithelp = $supportedithelp;
		
		return !$spname ? $support_ft[$field_type] : $support_ft[$field_type]->{$spname};
	}
	
	
	
	
	
	// *****************************************************************************
	// Common methods for - populating - the BASIC and ADVANCED search INDEX records
	// *****************************************************************************
	
	// Common method to create (insert) advanced search index DB records for various fields,
	// this can be called by fields or copied inside the field to allow better customization
	static function onIndexAdvSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null) {
		FlexicontentFields::createIndexRecords($field, $values, $item, $required_props, $search_props, $props_spacer, $filter_func, $for_advsearch=1);
	}
	
	
	// Common method to create basic text search index for various fields (added as the property field->search),
	// this can be called by fields or copied inside the field to allow better customization
	static function onIndexSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null) {
		FlexicontentFields::createIndexRecords($field, $values, $item, $required_props, $search_props, $props_spacer, $filter_func, $for_advsearch=0);
	}
	
	
	// Get a language specific handler for parsing the text to be added to the search index
	// e.g. doing word segmentation for a language that does not space-separate the words
	static function getLangHandler($language) {
		$cparams   = JComponentHelper::getParams('com_flexicontent');
		$filter_word_like_any = $cparams->get('filter_word_like_any', 0);
		
		if ($language == 'th-TH' && $filter_word_like_any==0) {
			$segmenter_path = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'THSplitLib'.DS.'segment.php';
			if ( JFile::exists($segmenter_path) )
			{
				require_once ($segmenter_path);
				// Apply caching to dictionary parsing regardless of cache setting ...
				$handlercache = JFactory::getCache('com_flexicontent_lang_handlers');  // Get Joomla Cache of '... lang_handlers' Caching Group
				$handlercache->setCaching(1);         // Force cache ON
				$handlercache->setLifeTime(24*3600);  // Set expire time (hard-code this to 1 day), since it is costly
				$dictionary = $handlercache->call(array('Segment', 'loadDictionary'));
				Segment::setDictionary($dictionary);
				$handler = new Segment();
				return $handler;
			}
		}
		return false;
	}
	
	
	// Common method to create basic/advanced search index for various fields
	static function createIndexRecords(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null, $for_advsearch=0) {
		$fi = FlexicontentFields::getPropertySupport($field->field_type, $field->iscore);
		$db = JFactory::getDBO();
		
		// * Per language handlers e.g. word segmenter objects (add spaces between words for language without spaces)
		static $lang_handlers = array();
		
		if ( !$for_advsearch )
		{
			// Check if field type supports text search, this will also skip fields wrongly marked as text searchable
			if ( !$fi->supportsearch || !$field->issearch ) {
				$field->search = array();
				return;
			}
		}
		
		else {
			$field->ai_query_vals = array();
			
			// Check if field type supports advanced search text searchable or filterable, this will also skip fields wrongly marked
			if ( !($fi->supportadvsearch && $field->isadvsearch) && !($fi->supportadvfilter && $field->isadvfilter) )
				return;
		}
		
		// A null indicates to retrieve values
		if ($values===null) {
			$items_values = FlexicontentFields::searchIndex_getFieldValues($field, $item, $for_advsearch);
		} else {
			$items_values = !is_array($values) ? array($values) : $values;
			$items_values = array($field->item_id => $items_values);
		}
		
		// Make sure posted data is an array 
		$unserialize = (isset($field->unserialize)) ? $field->unserialize : ( count($required_props) || count($search_props) );
		
		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		
		// Create the new search data
		foreach($items_values as $itemid => $item_values) 
		{
			// Get item language: (a) multi-item indexing via the search indexer or (b) single item indexing via the item save task (e.g. item form)
			$language = isset($field->items_data) ? $field->items_data[$itemid]->language : $item->language;
			if ( !isset($lang_handlers[$language]) )
			{
				$lang_handlers[$language] = FlexicontentFields::getLangHandler($language);
			}
			$lang_handler = $lang_handlers[$language];
			
			
			if ( @$field->isindexed ) {
				// Get Elements of the field these will be cached if they do not depend on the item ...
				$field->item_id = $itemid;   // in case it needs to be loaded to replace item properties in a SQL query
				$item_pros = false;
				$elements = FlexicontentFields::indexedField_getElements($field, $item, $field->extra_props, $item_pros, $createFilter=true);
				// Map index field vlaues to their real properties
				$item_values = FlexicontentFields::indexedField_getValues($field, $elements, $item_values, $prepost_prop='');
			}
				
			$searchindex = array();
			foreach($item_values as $vi => $v)
			{
				// Make sure multi-property data are unserialized
				if ($unserialize) {
					$data = @ unserialize($v);
					$v = ($v === 'b:0;' || $data !== false) ? $data : $v;
				}
				
				// Check value that current should not be included in search index
				if ( !is_array($v) && !strlen($v) ) continue;
				foreach ($required_props as $cp) if (!@$v[$cp]) continue;
				
				// Skip multi-property fields if search properties are not specified
				if ( !count($search_props) && is_array($v)) continue;
				
				// Create search value
				$search_value = array();
				foreach ($search_props as $sp) {
					if ( isset($v[$sp]) && strlen($v[$sp]) ) $search_value[] = $v[$sp];
				}
				
				if (count($search_props) && !count($search_value)) continue;  // all search properties were empty, skip this value
				$searchindex[$vi] = (count($search_props))  ?  implode($props_spacer, $search_value)  :  $v;
				$searchindex[$vi] = $filter_func ? $filter_func($searchindex[$vi]) : $searchindex[$vi];
			}
			
			// * Use word segmenter (if it was created) to add spaces between words
			if ($lang_handler) {
				foreach($searchindex as $i => $_searchindex) {
					$searchindex[$i] = implode(' ', $lang_handler->get_segment_array($clear_previous = true, $_searchindex));
				}
			}
			
			if ( !$for_advsearch )
			{
				$field->search[$itemid] = implode(' | ', $searchindex);
			}
			
			else {
				$n = 0;
				foreach ($searchindex as $vi => $search_text)
				{
					if ($search_prefix)
						$search_text = preg_replace('/(\b[^\s]+\b)/u', $search_prefix.'$0', $search_text);
					// Add new search value into the DB
					$query_val = "( "
						.$field->id. "," .$itemid. "," .($n++). "," .$db->Quote($search_text). "," .$db->Quote($vi).
					")";
					$field->ai_query_vals[] = $query_val;
				}
			}
		}
		
		//echo $field->name . ": "; print_r($values);echo "<br/>";
		//echo if ( !empty($searchindex) ) implode(' | ', $searchindex) ."<br/><br/>";
	}
	
	
	// Method to retrieve field values to be used for creating search indexes 
	static function searchIndex_getFieldValues(&$field, &$item, $for_advsearch=0)
	{
		$db = JFactory::getDBO();
		static $nullDate = null;
		if ($nullDate===null) $nullDate	= $db->getNullDate();
		
		// Create DB query to retrieve field values
		$values = null;
		switch ($field->field_type)
		{
		case 'title':
			$query  = 'SELECT i.title AS value, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'maintext':
			$query  = 'SELECT CONCAT_WS(\' \', i.introtext, i.fulltext) AS value, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'categories':
			$query  = 'SELECT c.id AS value_id, c.title AS value, rel.itemid AS itemid'
				.' FROM #__categories AS c'
				.' JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
				.' WHERE c.id<>0 AND rel.itemid IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'tags':
			$query  = 'SELECT t.id AS value_id, t.name AS value, rel.itemid AS itemid'
				.' FROM #__flexicontent_tags AS t'
				.' JOIN #__flexicontent_tags_item_relations AS rel ON t.id=rel.tid'
				.' WHERE t.id<>0 AND rel.itemid IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'type':
			$textcol = ', t.name AS value';
			$query 	= ' SELECT t.id AS value_id '.$textcol.', ext.item_id AS itemid'
				. ' FROM #__flexicontent_types AS t'
				.' JOIN #__flexicontent_items_ext AS ext ON t.id=ext.type_id '
				.' WHERE t.id<>0 AND ext.item_id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'created': case 'modified':
			if (!isset($valuecols[$field->field_type])) {
				$date_filter_group = $field->parameters->get('date_filter_group', 'month');
				if ($date_filter_group=='year') { $date_valformat='%Y'; }
				else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
				else { $date_valformat='%Y-%m-%d'; }
				
				$valuecols[$field->field_type] = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_valformat);
			}
			$valuecol = $valuecols[$field->field_type];
			
			$query 	= 'SELECT '.$valuecol.' AS value_id, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.'.$field->name.'<>'.$db->Quote($nullDate).' AND i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		case 'createdby': case 'modifiedby':
			$textcol = ', u.name AS value';
			$query 	= ' SELECT u.id AS value_id '.$textcol.', i.id AS itemid'
				. ' FROM #__users AS u'
				.' JOIN #__content AS i ON i.'.$field->name.' = u.id'
				.' WHERE u.id<>0 AND i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
		break;
		
		default:
			if ($field->iscore) $values=array(); //die('Field Type: '.$field->field_type.' does not support FLEXIcontent Advanced Search Indexing');
			$valuesselect = @$field->field_valuesselect ? $field->field_valuesselect : ' fi.value AS value ';
			$valuesjoin = @$field->field_valuesjoin ? $field->field_valuesjoin : '';
			$groupby = @$field->field_groupby ? $field->field_groupby .', fi.item_id' : ' GROUP BY fi.value, fi.item_id ';
			$query = 'SELECT '.$valuesselect.', fi.item_id AS itemid'
				.' FROM #__flexicontent_fields_item_relations as fi'
				.' JOIN #__content as i ON i.id=fi.item_id'
				. $valuesjoin
				.' WHERE fi.field_id='.$field->id.' AND fi.item_id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')'
				.$groupby;
		break;
		}
		
		// Execute query if not already done to load a single value column with no value id
		$_raw = !empty($field->field_rawvalues);
		if ($values === null) {
			$db->setQuery($query);
			$_values = $db->loadObjectList();
			$values = array();
			if ($_values) foreach($_values as $v) {
				if (isset($v->value_id))
					$values[$v->itemid][$v->value_id] = $_raw ? (array) $v : (isset($v->value) ? $v->value : $v->value_id);
				else
					$values[$v->itemid][] = $_raw ? (array) $v : $v->value;
			}
		}
		
		return $values;
	}
	
	
	
	
	
	// ********************************************************************************************
	// Methods for - MATCHING - Field Filters of FC views, (thus limiting the current ITEM LISTING)
	// ********************************************************************************************
	
	// Private Method to create a generic matching of filter
	static function createFilterValueMatchSQL(&$filter, &$value, $is_full_text=0, $is_search=0, $colname='')
	{
		static $search_prefix = null;
		if ($search_prefix === null) $search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$_search_prefix = $colname=='fs.search_index' ? $search_prefix : '';
		
		$db = JFactory::getDBO();
		$display_filter_as = $filter->parameters->get( $is_search ? 'display_filter_as_s' : 'display_filter_as', 0 );
		$filter_compare_type = $filter->parameters->get( 'filter_compare_type', 0 );
		
		// Make sure the current filtering values match the field filter configuration to be single or multi-value
		if ( in_array($display_filter_as, array(2,3,5,6,8)) ) {  // range or multi-value filter
			if (!is_array($value)) $value = array( $value );
		} else {
			if (is_array($value)) $value = array ( @ $value[0] );
			else $value = array ( $value );
		}
		
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all = count($value)>1 && !$isRange ?   // prevent require_all for known ranges
			$require_all_param : 0;
		//echo "createFilterValueMatchSQL : filter name: ".$filter->name." Filter Type: ".$display_filter_as." Values: "; print_r($value); echo "<br>";
		
		$_value = array();
		foreach ($value as $i => $v) {
			$v = trim($v);
			if ( strlen($v) ) $_value[$i] = $v;
		}
		$value = $_value;
		if ( !count($value) ) return '';
		
		// For text input format the strings, if this was requested by the filter
		$istext_input = $display_filter_as==1 || $display_filter_as==3;
		if ($istext_input && isset($filter->filter_valueformat)) {
			foreach($value as $i => $val) {
				if ( !$filter_compare_type ) $typecasted_val = $db->Quote($value[$i]);
				else $typecasted_val = $filter_compare_type==1 ? intval($value[$i]) : floatval($value[$i]);
				$value[$i] = str_replace('__filtervalue__', $typecasted_val, $filter->filter_valueformat);
			}
			$quoted=true;
		}
		
		$valueswhere = '';
		switch ($display_filter_as) {
		// RANGE cases
		case 2: case 3: case 8:
			if ( ! @ $quoted ) foreach($value as $i => $v) {
				if ( !$filter_compare_type ) $value[$i] = $db->Quote( $_search_prefix . $v );
				else $value[$i] = $filter_compare_type==1 ? intval( $v ) : floatval( $v );
			}
			$reverse_values = $filter->parameters->get( 'reverse_filter_order', 0) && $display_filter_as == 8;
			$value1 = $reverse_values ? @$value[2] : @$value[1];
			$value2 = $reverse_values ? @$value[1] : @$value[2];
			$value_empty = !strlen(@$value[1]) && strlen(@$value[2]) ? ' OR _v_="" OR _v_ IS NULL' : '';
			if ( strlen($value1) ) $valueswhere .= ' AND (_v_ >=' . $value1 . ')';
			if ( strlen($value2) ) $valueswhere .= ' AND (_v_ <=' . $value2 . $value_empty . ')';
			break;
		// SINGLE TEXT select value cases
		case 1:
			// DO NOT put % in front of the value since this will force a full table scan instead of indexed column scan
			$_value_like = $_search_prefix.$value[0].($is_full_text ? '*' : '%');
			if (empty($quoted))  $_value_like = $db->Quote($_value_like);
			if ($is_full_text)
				$valueswhere .= ' AND  MATCH (_v_) AGAINST ('.$_value_like.' IN BOOLEAN MODE)';
			else
				$valueswhere .= ' AND _v_ LIKE ' . $_value_like;
			break;
		// EXACT value cases
		case 0: case 4: case 5: case 6: case 7: default:
			$value_clauses = array();
			
			if ( ! $require_all ) {
				foreach ($value as $val) {
					$value_clauses[] = '_v_=' . $db->Quote( $_search_prefix . $val );
				}
				$valueswhere .= ' AND ('.implode(' OR ', $value_clauses).') ';
			} else {
				foreach ($value as $val) {
					$value_clauses[] = $db->Quote( $_search_prefix . $val );
				}
				$valueswhere = ' AND _v_ IN ('. implode(',', $value_clauses) .')';
			}
			break;
		}
		
		//echo $valueswhere . "<br>";
		return $valueswhere;
	}
	
	
	// Method to get the active filter result for Content Lists Views (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFiltered( &$filter, $value, $return_sql=true )
	{
		$db = JFactory::getDBO();
		
		// Check if field type supports advanced search
		$support = FlexicontentFields::getPropertySupport($filter->field_type, $filter->iscore);
		if ( ! $support->supportfilter )  return null;
		
		$valueswhere = FlexicontentFields::createFilterValueMatchSQL($filter, $value, $is_full_text=0, $is_search=0);
		if ( !$valueswhere ) { return; }
		
		$colname     = @$filter->filter_colname ? $filter->filter_colname : 'value';
		$valueswhere = str_replace('_v_', $colname, $valueswhere);
		$valuesjoin  = @$filter->filter_valuesjoin   ? $filter->filter_valuesjoin   : ' JOIN #__flexicontent_fields_item_relations rel ON rel.item_id=c.id AND rel.field_id = ' . $filter->id;
		
		// Decide to require all values
		$display_filter_as = $filter->parameters->get('display_filter_as', 0 );
		
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all = count($value)>1 && !$isRange ?   // prevent require_all for known ranges
			$require_all_param : 0;
		
		if ( @$filter->filter_valuesjoin ) {
			$query = 'SELECT '.($require_all ? 'c.id' : 'DISTINCT c.id')
				.' FROM #__content c'
				.$filter->filter_valuesjoin
				.' WHERE 1'
				. $valueswhere ;
				if ($require_all) {
					// Do not use distinct on column, it makes it is very slow, despite column having an index !!
					// e.g. HAVING COUNT(DISTINCT colname) = ...
					// Instead the field code should make sure that no duplicate values are saved in the DB !!
					$query .=
						' GROUP BY c.id ' .' HAVING COUNT(*) >= '.count($value).
						' ORDER BY NULL';  // THIS should remove filesort in MySQL, and improve performance issue of REQUIRE ALL
				}
		} else {
			$query = 'SELECT '.($require_all ? 'rel.item_id' : 'DISTINCT rel.item_id')
				.' FROM #__flexicontent_fields_item_relations as rel'
				.' WHERE rel.field_id=' . $filter->id
				. $valueswhere ;
				if ($require_all) {
					// Do not use distinct on column, it makes it is very slow, despite column having an index !!
					// e.g. HAVING COUNT(DISTINCT colname) = ...
					// Instead the field code should make sure that no duplicate values are saved in the DB !!
					$query .=
						' GROUP BY rel.item_id ' .' HAVING COUNT(*) >= '.count($value).
						' ORDER BY NULL';  // THIS should remove filesort in MySQL, and improve performance issue of REQUIRE ALL
				}
		}
		//$query .= ' GROUP BY id';   // BAD PERFORMANCE ?
		
		if ( !$return_sql ) {
			//echo "<br>GET FILTERED Items (helper func) -- [".$filter->name."] using in-query ids :<br>". $query."<br>\n";
			$db->setQuery($query);
			$filtered = $db->loadColumn();
			return $filtered;
		}
		else if ($return_sql===2) {
			static $iids_tblname  = array();
			if ( !isset($iids_tblname[$filter->id]) ) {
				$iids_tblname[$filter->id] = 'fc_filter_iids_'.$filter->id;
			}
			$tmp_tbl = $iids_tblname[$filter->id];
			
			try {
				// Use sub-query on temporary table
				$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
				$db->execute();
				$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
				$db->execute();
				$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$query);
				$db->execute();
				$_query = $query;
				$query = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using temporary table: ".$query." for :".$_query ." <br/><br/>";
				/*$db->setQuery($query);
				$data = $db->loadObjectList();
				echo "<pre>";
				print_r($data);
				exit;*/
			}
			catch (Exception $e) {
				// Ignore table creation error
				//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
				//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
			}
		} else {
			//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
		}
		return ' AND i.id IN ('. $query .')';
	}
	
	
	// Method to get the active filter result Search View (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFilteredSearch( &$filter, $value, $return_sql=true )
	{
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		
		// Check if field type supports advanced search
		$support = FlexicontentFields::getPropertySupport($filter->field_type, $filter->iscore);
		if ( ! $support->supportadvsearch && ! $support->supportadvfilter )  return null;
		
		// Decide to require all values
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );
		
		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all = count($value)>1 && !$isRange ?   // prevent require_all for known ranges
			$require_all_param : 0;
		
		$istext_input = $display_filter_as==1 || $display_filter_as==3;
		$colname = (@ $filter->isindexed && !$istext_input) || $isDate ? 'fs.value_id' : 'fs.search_index';
		
		// Create where clause for matching the filter's values
		$valueswhere = FlexicontentFields::createFilterValueMatchSQL($filter, $value, $is_full_text=1, $is_search=1, $colname);
		if ( !$valueswhere ) { return; }
		$valueswhere = str_replace('_v_', $colname, $valueswhere);
		
		$field_tbl = 'flexicontent_advsearch_index_field_'.$filter->id;
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . $field_tbl . '"';
		$db->setQuery($query);
		$tbl_exists = (boolean) count($db->loadObjectList());
		$field_tbl = $tbl_exists ? $field_tbl : 'flexicontent_advsearch_index';
		
		// Get ALL items that have such values for the given field
		$query = 'SELECT '.($require_all ? 'fs.item_id' : 'DISTINCT fs.item_id')
			.' FROM #__'.$field_tbl.' AS fs'
			.' WHERE fs.field_id=' . $filter->id
			. $valueswhere ;
		if ($require_all) {
			// Do not use distinct on column, it makes it is very slow, despite column having an index !!
			// e.g. HAVING COUNT(DISTINCT colname) = ...
			// Instead the field code should make sure that no duplicate values are saved in the DB !!
			$query .=
				' GROUP BY fs.item_id ' .' HAVING COUNT(*) >= '.count($value).
				' ORDER BY NULL';  // THIS should remove filesort in MySQL, and improve performance issue of REQUIRE ALL
		}
		//echo 'Filter ['. $filter->label .']: '. $query."<br/><br/>\n";
		
		if ( !$return_sql ) {
			//echo "<br>GET FILTERED Items (helper func) -- [".$filter->name."] using in-query ids : ". $query."<br>\n";
			$db->setQuery($query);
			$filtered = $db->loadColumn();
			return $filtered;
		}
		else if ($return_sql===2) {
			static $iids_tblname  = array();
			if ( !isset($iids_tblname[$filter->id]) ) {
				$iids_tblname[$filter->id] = 'fc_filter_iids_'.$filter->id;
			}
			$tmp_tbl = $iids_tblname[$filter->id];
			
			try {
				// Use sub-query on temporary table
				$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
				$db->execute();
				$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
				$db->execute();
				$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$query);
				$db->execute();
				$_query = $query;
				$query = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using temporary table: ".$query." for :".$_query ." <br/><br/>";
				/*$db->setQuery($query);
				$data = $db->loadObjectList();
				echo "<pre>";
				print_r($data);
				exit;*/
			}
			catch (Exception $e) {
				// Ignore table creation error
				//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
				//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
			}
		} else {
			//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
		}
		return ' AND i.id IN ('. $query .')';
	}
	
	
	
	// **********************************************
	// Methods for creating Field Filters of FC views
	// **********************************************
	
	// Method to create a category (content list) or search filter
	static function createFilter(&$filter, $value='', $formName='adminForm', $indexed_elements=false, $search_prop='')
	{
		static $apply_cache = null;
		static $faceted_overlimit_msg = null;
		$user = JFactory::getUser();
		$cparams   = JComponentHelper::getParams('com_flexicontent');  // createFilter maybe called in backend too ...
		$print_logging_info = $cparams->get('print_logging_info');
		
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		$is_fc_component = $option=='com_flexicontent';
		$isCategoryView = $is_fc_component && $view=='category';
		$isSearchView   = $is_fc_component && $view=='search';
		
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
		// Apply caching to filters regardless of cache setting ...
		$apply_cache = FLEXI_CACHE;
		if ($apply_cache) {
			$itemcache = JFactory::getCache('com_flexicontent_filters');  // Get Joomla Cache of '...items' Caching Group
			$itemcache->setCaching(1); 		              // Force cache ON
			$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
		}
		
		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$default_size = $isDate ? 15 : 30;
		$_s = $isSearchView ? '_s' : '';
		
		// Some parameter shortcuts
		$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 0 ) ;   // How to show filter label
		$size         = $filter->parameters->get( 'text_filter_size', $default_size );        // Size of filter
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		
		$isSlider = $display_filter_as == 7 || $display_filter_as == 8;
		$slider_display_config = $filter->parameters->get( 'slider_display_config'.$_s, 1 );  // Slider found values: 1 or custom values/labels: 2
		
		$filter_vals_display = $filter->parameters->get( 'filter_vals_display'.$_s, 0 );
		
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all = count($value)>1 && !$isRange ?   // prevent require_all for known ranges
			$require_all_param : 0;
		
		$combine_tip = $filter->parameters->get( 'filter_values_require_all_tip', 0 );
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items'.$_s, 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		$hide_disabled_values = $filter->parameters->get( 'hide_disabled_values'.$_s, 0 );
		$get_filter_vals = in_array($display_filter_as, array(0,2,4,5,6)) || ($isSlider && $slider_display_config==1);
		
		$filter_ffname = 'filter_'.$filter->id;
		$filter_ffid   = $formName.'_'.$filter->id.'_val';
		
		// Make sure the current filtering values match the field filter configuration to single or multi-value
		if ( in_array($display_filter_as, array(2,3,5,6,8)) ) {
			if (!is_array($value)) $value = strlen($value) ? array($value) : array();
		} else {
			if (is_array($value)) $value = @ $value[0];
		}
		
		// Escape values for output
		if (!is_array($value)) $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
		else foreach($value as $i => $v) $value[$i] = htmlspecialchars($value[$i], ENT_COMPAT, 'UTF-8');
		
		// Alter search property name (indexed fields only), remove underscore _ at start & end of it
		if ($indexed_elements && $search_prop) {
			preg_match("/^_([a-zA-Z_0-9]+)_$/", $search_prop, $prop_matches);
			$search_prop = @ $prop_matches[1];
		}
		
		// Get filtering values, this can be cached if not filtering according to current category filters
		if ( $get_filter_vals )
		{
			$view_join = '';
			$view_join_n_text = '';
			$view_where = '';
			$filters_where = array();
			$text_search = '';
			$view_total=0;
			
			// *** Limiting of displayed filter values according to current category filtering, but show all field values if filter is active
			if ( $isCategoryView ) {
				// category view, use parameter to decide if limitting filter values
				global $fc_catview;
				if ( $faceted_filter ) {
					$view_join = @ $fc_catview['join_clauses'];
					$view_join_n_text = @ $fc_catview['join_clauses_with_text'];
					$view_where = @ $fc_catview['where_conf_only'];
					$filters_where = @ $fc_catview['filters_where'];
					$text_search = $fc_catview['search'];
					$view_total = isset($fc_catview['view_total']) ? $fc_catview['view_total'] : 0;
				}
			} else if ( $isSearchView ) {
				// search view, use parameter to decide if limitting filter values
				global $fc_searchview;
				if ( empty($fc_searchview) ) return array();  // search view plugin disabled ?
				if ( $faceted_filter ) {
					$view_join = $fc_searchview['join_clauses'];
					$view_join_n_text = $fc_searchview['join_clauses_with_text'];
					$view_where = $fc_searchview['where_conf_only'];
					$filters_where = $fc_searchview['filters_where'];
					$text_search = $fc_searchview['search'];
					$view_total = isset($fc_searchview['view_total']) ? $fc_searchview['view_total'] : 0;
				}
			}
			$createFilterValues = !$isSearchView ? 'createFilterValues' : 'createFilterValuesSearch';

			// This is hack for filter core properties to be filterable in search view without being added to the adv search index
			if( $filter->field_type == 'coreprops' &&  $view=='search' )
			{ 
				$createFilterValues = 'createFilterValues';
			}

			// Get filter values considering PAGE configuration (regardless of ACTIVE filters)
			if ( $apply_cache )
				$results_page = $itemcache->call(array('FlexicontentFields', $createFilterValues), $filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
			else if (!$isSearchView)
				$results_page = FlexicontentFields::createFilterValues($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
			else
				$results_page = FlexicontentFields::createFilterValuesSearch($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
			
			// Get filter values considering ACTIVE filters, but only if there is at least ONE filter active
			$faceted_max_item_limit = 10000;
			if ( $faceted_filter==2 ) {
				if ($view_total <= $faceted_max_item_limit)
				{
					// DO NOT cache at this point the filter combinations are endless, so they will produce big amounts of cached data, that will be rarely used ...
					// but if only a single filter is active we can get the cached result of it ... because its own filter_where is not used for the filter itself
					if ( !$text_search && (count($filters_where)==0 || (count($filters_where)==1 && isset($filters_where[$filter->id]))) ) {
						$results_active = $results_page;
					} else if (!$isSearchView)
						$results_active = FlexicontentFields::createFilterValues($filter, $view_join_n_text, $view_where, $filters_where, $indexed_elements, $search_prop);
					else {
						$results_active = FlexicontentFields::createFilterValuesSearch($filter, $view_join_n_text, $view_where, $filters_where, $indexed_elements, $search_prop);
					}
						
				} else if ($faceted_overlimit_msg === null) {
					// Set a notice message about not counting item per filter values and instead showing item TOTAL of current category / view
					$faceted_overlimit_msg = 1;
					$filter_messages = JRequest::getVar('filter_message', array());
					$filter_messages[] = JText::sprintf('FLEXI_FACETED_ITEM_LIST_OVER_LIMIT', $faceted_max_item_limit, $view_total);
					JRequest::setVar('filter_messages', $filter_messages);
				}
			}
			
			// Decide which results to show those based: (a) on active filters or (b) on page configuration
			// This depends if hiding disabled values (for FACETED: 2) AND if active filters exist
			$use_active_vals = $hide_disabled_values && isset($results_active);
			$results_shown = $use_active_vals ? $results_active : $results_page;
			$update_found = !$use_active_vals && isset($results_active);
			
			// Set usage counters
			$add_usage_counters = $faceted_filter==2 && $show_matches;
			$results = array();
			foreach ($results_shown as $i => $result) {
				$results[$i] = $result;
				
				// FACETED: 0,1 or NOT showing usage
				// Set usage to non-zero value e.g. -1 ... which maybe used (e.g. disabling values) but not be displayed
				if (!$show_matches || $faceted_filter<2)
					$results[$i]->found = -1;
				
				// FACETED: 2 and SHOWING PAGE VALUES (not hiding values or no active filters),
				// Set usage of filter values that was calculated according to active filters
				// 1. this overrides value usage calculated for page's configuration (faceted: 1)
				// 2. we set zero if value was not found
				else if ($update_found)
					$results[$i]->found = (int) @ $results_active[$i]->found;
				
				// FACETED: 1 or hiding unavailable values ... leave value unchanged (if it has been calculated)
				else ;
				
				// Append value usage to value's label
				if ($add_usage_counters && $results[$i]->found)
					$results[$i]->text .= ' ('.$results[$i]->found.')';  // THESE for indexed fields should have been cloned, so it is ok to modify
			}
		} else {
			$add_usage_counters = false;
			$faceted_filter = 0; // clear faceted filter flag
		}
		
		// Prepend Field's Label to filter HTML
		// Commented out because it was moved in to form template file
		//$filter->html = $label_filter==1 ? $filter->label.': ' : '';
		$filter->html = '';
		
		// *** Create the form field(s) used for filtering
		switch ($display_filter_as) {
		case 0: case 2: case 6:  // 0: Select (single value selectable), 2: Dual select (value range), 6: Multi Select (multiple values selectable)
			$options = array();
			// MULTI-select does not has an internal label a drop-down list option
			if ($display_filter_as != 6) {
				if ($label_filter==-1) {  // *** e.g. BACKEND ITEMS MANAGER custom filter
					$filter->html = '<span class="'.$filter->parameters->get( 'label_filter_css'.$_s, 'label' ).'">'.$filter->label.'</span>';
					$first_option_txt = '';
				} else if ($label_filter==2) {
					$first_option_txt = $filter->label;
				} else {
					$first_option_txt = $filter->parameters->get( 'filter_usefirstoption'.$_s, 0) ? $filter->parameters->get( 'filter_firstoptiontext'.$_s, 'FLEXI_ALL') : 'FLEXI_ANY';
					$first_option_txt = JText::_($first_option_txt);
				}
				$options[] = JHTML::_('select.option', '', !$first_option_txt ? '-' : '- '.$first_option_txt.' -');
			}
			foreach ($results as $result) {
				if ( !strlen($result->value) ) continue;
				$options[] = JHTML::_('select.option', $result->value, $result->text, 'value', 'text', $disabled = ($faceted_filter==2 && !$result->found));
			}
			
			// Make use of select2 lib
			flexicontent_html::loadFramework('select2');
			$classes  = " use_select2_lib";
			$extra_param = '';
			
			// MULTI-select: special label and prompts
			if ($display_filter_as == 6) {
				//$classes .= ' fc_label_internal fc_prompt_internal';
				$classes .= ' fc_prompt_internal';
				// Add field's LABEL internally or click to select PROMPT (via js)
				$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
				// Add type to filter PROMPT (via js)
				//$extra_param  = ' data-fc_label_text="'.flexicontent_html::escapeJsText($_inner_lb,'s').'"';
				$extra_param  = ' data-placeholder="'.flexicontent_html::escapeJsText($_inner_lb,'s').'"';
				$extra_param .= ' data-fc_prompt_text="'.flexicontent_html::escapeJsText(JText::_('FLEXI_TYPE_TO_FILTER'),'s').'"';
			}
			
			// Create HTML tag attributes
			$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
			$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="20" ' : '';
			if ( $extra_attribs = $filter->parameters->get( 'filter_extra_attribs'.$_s, '' ) )
			{
				$attribs_str .= $extra_attribs;
			}
			//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';
			
			if ($display_filter_as==6 && $combine_tip) {
				$filter->html	.= ' <span class="fc_filter_tip_inline badge badge-info">'.JText::_(!$require_all_param ? 'FLEXI_ANY_OF' : 'FLEXI_ALL_OF').'</span> ';
			}
			if ($display_filter_as==0) {
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname, $attribs_str, 'value', 'text', $value, $filter_ffid);
			} else if ($display_filter_as==6) {
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', $value, $filter_ffid);
			} else {
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[1]', $attribs_str, 'value', 'text', @ $value[1], $filter_ffid.'1');
				$filter->html	.= '<span class="fc_range"></span>';
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[2]', $attribs_str, 'value', 'text', @ $value[2], $filter_ffid.'2');
			}
			break;
		case 1: case 3: case 7: case 8: // (TODO: autocomplete) ... 1: Text input, 3: Dual text input (value range), both of these can be JS date calendars, 7: Slider, 8: Slider range
			
			if ( !$isSlider ) {
				$_inner_lb = $label_filter==2 ? $filter->label : JText::_($isDate ? 'FLEXI_CLICK_CALENDAR' : ''/*'FLEXI_TYPE_TO_LIST'*/);
				$_inner_lb = flexicontent_html::escapeJsText($_inner_lb,'s');
				//$attribs_str = ' class="fc_field_filter fc_label_internal '.($isDate ? 'fc_iscalendar' : '').'" data-fc_label_text="'.$_inner_lb.'"';
				//$attribs_arr = array('class'=>'fc_field_filter fc_label_internal '.($isDate ? 'fc_iscalendar' : '').'', 'data-fc_label_text' => $_inner_lb );
				$attribs_str = ' class="fc_field_filter '.($isDate ? 'fc_iscalendar' : '').'" placeholder="'.$_inner_lb.'"';
				$attribs_arr = array('class'=>'fc_field_filter '.($isDate ? 'fc_iscalendar' : '').'', 'placeholder' => $_inner_lb );
			} else {
				$attribs_str = "";
				
				$value1 = $display_filter_as==8 ? @$value[1] : $value;
				$value2 = @$value[2];
				if ($isSlider && $slider_display_config==1)
				{
					$start = $min = 0;
					$end = $max = -1;
					$step=1;
					$step_values = array(0=>"''");
					$step_labels = array(0=>JText::_('FLEXI_ANY'));
					$i = 1;
					foreach ($results as $result) {
						if ( !strlen($result->value) ) continue;
						$step_values[] = "'".$result->value."'";
						$step_labels[] = $result->text;
						if ($result->value==$value1) $start = $i;
						if ($result->value==$value2) $end   = $i;
						$i++;
					}
					// Set max according considering the skipped empty values
					$max = ($i-1)+($display_filter_as==7 ? 0 : 1); //count($results)-1;
					if ($end == -1) $end = $max;  // Set end to last element if it was not set
					
					if ($display_filter_as==8)
					{
						$step_values[] = "''";
						$step_labels[] = JText::_('FLEXI_ANY');
					}
					$step_range = 
							"step: 1,
							range: {'min': " .$min. ", 'max': " .$max. "},";
				}
				else if ($isSlider) {
					$custom_range  = $filter->parameters->get( 'slider_custom_range'.$_s, "'min': '', '25%': 500, '50%': 2000, '75%': 10000, 'max': ''" );
					$custom_labels = preg_split("/\s*##\s*/u", $filter->parameters->get( 'slider_custom_labels'.$_s, 'label_any ## label_500 ## label_2000 ## label_10000 ## label_any' ));
					if ($filter->parameters->get('slider_custom_labels_jtext'.$_s, 0))
					{
						foreach ($custom_labels as $i=> $custom_label) $custom_labels[$i] = JText::_($custom_label);  // Language filter the custom labels
					}
					$custom_vals = json_decode('{'.str_replace("'", '"', $custom_range).'}', true);
					if (!$custom_vals) {
						$filter->html = '
							<div class="alert">
								Bad syntax for custom range for slider filter: '.$filter->label."
								EXAMPLE: <br/> 'min': 0, '25%': 500, '50%': 2000, '75%': 10000, 'max': 50000".'
							</div>';
						break;
					}
					if (!strlen($custom_vals['min'])) $custom_vals['min'] = "''";
					if (!strlen($custom_vals['max'])) $custom_vals['max'] = "''";
					
					$start = 0;
					$end   = count($custom_vals)-1;
					$step_values = $custom_vals;
					$step_labels = & $custom_labels;
					$i = 0;
					$set_start = strlen($value1)>0;
					$set_end   = strlen($value1)>0;
					foreach($custom_vals as $n => $custom_val) {
						if ($set_start && $custom_val==$value1) $start = $i;
						if ($set_end   && $custom_val==$value2) $end   = $i;
						$custom_vals[$n] = $i++;
					}
					$step_range = '
							snap: true,
							range: '.json_encode($custom_vals).',
					';
				}
				
				flexicontent_html::loadFramework('nouislider');
				$left_no = $display_filter_as==7 ? '' : '1';
				$rght_no = '2';  // sometimes unused
				$js = "
					jQuery(document).ready(function(){
						var slider = document.getElementById('".$filter_ffid."_nouislider');
						
						var input1 = document.getElementById('".$filter_ffid.$left_no."');
						var input2 = document.getElementById('".$filter_ffid.$rght_no."');
						var isSingle = ".($display_filter_as==7 ? '1' : '0').";
						
						var step_values = [".implode(', ', $step_values)."];
						var step_labels = [\"".implode('", "', array_map('addslashes', $step_labels))."\"];
						
						noUiSlider.create(slider, {".
							($display_filter_as==7 ? "
								start: ".$start.",
								connect: false,
							" : "
								start: [".$start.", ".$end."],
								connect: true,
							")."
								".$step_range."
						});
						
						var tipHandles = slider.getElementsByClassName('noUi-handle'),
						tooltips = [];
						
						// Add divs to the slider handles.
						for ( var i = 0; i < tipHandles.length; i++ ){
							tooltips[i] = document.createElement('span');
							tipHandles[i].appendChild(tooltips[i]);
							
							tooltips[i].className += 'fc-sliderTooltip'; // Add a class for styling
							tooltips[i].innerHTML = '<span></span>'; // Add additional markup
							tooltips[i] = tooltips[i].getElementsByTagName('span')[0];  // Replace the tooltip reference with the span we just added
						}
						
						// When the slider changes, display the value in the tooltips and set it into the input form elements
						slider.noUiSlider.on('update', function( values, handle ) {
							var value = parseInt(values[handle]);
							var i = value;
							
							if ( handle ) {
								input2.value = typeof step_values[value] !== 'undefined' ? step_values[value] : value;
							} else {
								input1.value = typeof step_values[value] !== 'undefined' ? step_values[value] : value;
							}
							var tooltip_text = typeof step_labels[value] !== 'undefined' ? step_labels[value] : value;
							var max_len = 36;
							tooltips[handle].innerHTML = tooltip_text.length > max_len+4 ? tooltip_text.substring(0, max_len)+' ...' : tooltip_text;
							var left  = jQuery(tooltips[handle]).closest('.noUi-origin').position().left;
							var width = jQuery(tooltips[handle]).closest('.noUi-base').width();
							
							//window.console.log ('handle: ' + handle + ', left : ' + left + ', width : ' + width);
							if (isSingle) {
								left<(50/100)*width ?
									jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right') :
									jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left');
							}
							else if (handle) {
								left<=(76/100)*width ?
									jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right') :
									jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left');
								left<=(49/100)*width ?
									jQuery(tooltips[handle]).parent().addClass('fc-bottom') :
									jQuery(tooltips[handle]).parent().removeClass('fc-bottom');
							}
							else {
								left>=(24/100)*width ?
									jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left') :
									jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right');
								left>=(51/100)*width ?
									jQuery(tooltips[handle]).parent().addClass('fc-bottom') :
									jQuery(tooltips[handle]).parent().removeClass('fc-bottom');
							}
						});
						
						// Handle form autosubmit
						slider.noUiSlider.on('change', function() {
							var slider = jQuery('#".$filter_ffid."_nouislider');
							var jform  = slider.closest('form');
							var form   = jform.get(0);
							adminFormPrepare(form, parseInt(jform.attr('data-fc-autosubmit')));
						});
						
						input1.addEventListener('change', function(){
							var value = 0;  // default is first value = empty
							for(var i=1; i<step_values.length-1; i++) {
								if (step_values[i] == this.value) { value=i; break; }
							}
							slider.noUiSlider.set([value, null]);
						});
						".($display_filter_as==8 ? "
						input2.addEventListener('change', function(){
							var value = step_values.length-1;  // default is last value = empty
							for(var i=1; i<step_values.length-1; i++) {
								if (step_values[i] == this.value) { value=i; break; }
							}
							slider.noUiSlider.set([null, value]);
						});
						" : "")."
					});
				";
				JFactory::getDocument()->addScriptDeclaration($js);
				//JFactory::getDocument()->addStyleDeclaration("");
			}
			
			if ($display_filter_as==1 || $display_filter_as==7) {
				if ($isDate && !$isSlider) {
					$filter->html	.= '
						<span class="fc_filter_element">
							'.FlexicontentFields::createCalendarField($value, $allowtime=0, $filter_ffname, $filter_ffid, $attribs_arr).'
						</span>';
				} else {
					$filter->html	.=
					($isSlider ? '<div id="'.$filter_ffid.'_nouislider" class="fcfilter_with_nouislider"></div><div class="fc_slider_input_box">' : '').'
						<span class="fc_filter_element">
							<input id="'.$filter_ffid.'" name="'.$filter_ffname.'" '.$attribs_str.' type="text" size="'.$size.'" value="'.@ $value.'" />
						</span>
					'.($isSlider ? '</div>' : '');
				}
			} else {
				if ($isDate && !$isSlider) {
					$filter->html	.= '
						<span class="fc_filter_element">
							'.FlexicontentFields::createCalendarField(@ $value[1], $allowtime=0, $filter_ffname.'[1]', $filter_ffid.'1', $attribs_arr).'
						</span>
						<span class="fc_range"></span>
						<span class="fc_filter_element">
							'.FlexicontentFields::createCalendarField(@ $value[2], $allowtime=0, $filter_ffname.'[2]', $filter_ffid.'2', $attribs_arr).'
						</span>';
				} else {
					$size = (int)($size / 2);
					$filter->html	.=
					($isSlider ? '<div id="'.$filter_ffid.'_nouislider" class="fcfilter_with_nouislider"></div><div class="fc_slider_input_box">' : '').'
						<span class="fc_filter_element">
							<input name="'.$filter_ffname.'[1]" '.$attribs_str.' id="'.$filter_ffid.'1" type="text" size="'.$size.'" value="'.@ $value[1].'" />
						</span>
						<span class="fc_range"></span>
						<span class="fc_filter_element">
							<input name="'.$filter_ffname.'[2]" '.$attribs_str.' id="'.$filter_ffid.'2" type="text" size="'.$size.'" value="'.@ $value[2].'" />
						</span>
					'.($isSlider ? '</div>' : '');
				}
			}
			break;
		case 4: case 5:  // 4: radio (single value selectable), 5: checkbox (multiple values selectable)
			$lf_min = 10;  // add parameter for this ?
			$add_lf = count($results) >= $lf_min;
			if ($add_lf)  flexicontent_html::loadFramework('mCSB');
			$clear_values = 0;
			$value_style = $clear_values ? 'float:left; clear:both;' : '';
			
			$i = 0;
			$checked = ($display_filter_as==5) ? !count($value) || !strlen(reset($value)) : !strlen($value);
			$checked_attr = $checked ? 'checked="checked"' : '';
			$checked_class = $checked ? 'fc_highlight' : '';
			$checked_class_li = $checked ? ' fc_checkradio_checked' : '';
			$filter->html .= '<div class="fc_checkradio_group_wrapper fc_add_scroller'.($add_lf ? ' fc_list_filter_wrapper':'').'">';
			$filter->html .= '<ul class="fc_field_filter fc_checkradio_group'.($add_lf ? ' fc_list_filter':'').'">';
			$filter->html .= '<li class="fc_checkradio_option fc_checkradio_special'.$checked_class_li.'" style="'.$value_style.'">';
			$filter->html	.= ($label_filter==2  ? ' <span class="fc_filter_label_inline">'.$filter->label.'</span> ' : '');
			if ($display_filter_as==4) {
				$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClassGrp(this, \'fc_highlight\', 1);" ';
				$filter->html .= '  id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'" ';
				$filter->html .= '  value="" '.$checked_attr.' class="fc_checkradio" />';
			} else {
				$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClass(this, \'fc_highlight\', 1);" ';
				$filter->html .= '  id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'['.$i.']" ';
				$filter->html .= '  value="" '.$checked_attr.' class="fc_checkradio" />';
			}
			
			$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$tooltip_title = flexicontent_html::getToolTip('FLEXI_REMOVE_ALL', '', $translate=1, $escape=1);
			$filter->html .= '<label class="'.$checked_class.$tooltip_class.'" for="'.$filter_ffid.$i.'" '
				.' title="'.$tooltip_title.'" '
				.($checked ? ' style="display:none!important;" ' : ' style="background:none!important; padding-left:0px!important;" ').'>'.
				'<span class="fc_delall_filters"></span>';
			$filter->html .= '</label> '
				.($combine_tip ? ' <span class="fc_filter_tip_inline badge badge-info">'.JText::_(!$require_all_param ? 'FLEXI_ANY_OF' : 'FLEXI_ALL_OF').'</span> ' : '')
				.' </li>';
			$i++;
			
			foreach ($results as $result) {
				if ( !strlen($result->value) ) continue;
				$checked = ($display_filter_as==5) ? in_array($result->value, $value) : $result->value==$value;
				$checked_attr = $checked ? ' checked=checked ' : '';
				$disable_attr = $faceted_filter==2 && !$result->found ? ' disabled=disabled ' : '';
				$checked_class = $checked ? 'fc_highlight' : '';
				$checked_class .= $faceted_filter==2 && !$result->found ? ' fcdisabled ' : '';
				$checked_class_li = $checked ? ' fc_checkradio_checked' : '';
				$filter->html .= '<li class="fc_checkradio_option'.$checked_class_li.'" style="'.$value_style.'">';
				
				// *** PLACE image before label (and e.g. (default) above the label)
				if ($filter_vals_display == 2)
					$filter->html .= "<span class='fc_filter_val_img'><img onclick=\"jQuery(this).closest('li').find('input').click();\" src='" .$result->image_url. "' /></span>";
				
				if ($display_filter_as==4) {
					$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClassGrp(this, \'fc_highlight\');" ';
					$filter->html .= '  id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'" ';
					$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
				} else {
					$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClass(this, \'fc_highlight\');" ';
					$filter->html .= '  id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'['.$i.']" ';
					$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
				}
				
				$filter->html .= '<label class="fc_filter_val fc_cleared '.$checked_class.'" for="'.$filter_ffid.$i.'">';
				if ($filter_vals_display == 0 || $filter_vals_display == 2)
					$filter->html .= "<span class='fc_filter_val_lbl'>" .$result->text. "</span>";
				else if ($add_usage_counters && $result->found)
					$filter->html .= "<span class='fc_filter_val_lbl'>(".$result->found.")</span>";
				$filter->html .= '</label>';
				
				// *** PLACE image after label (and e.g. (default) next to the label)
				if ($filter_vals_display == 1)
					$filter->html .= "<span class='fc_filter_val_img'>"
					."<img onclick=\"jQuery(this).closest('li').find('input').click();\" src='" .$result->image_url. "' />"
					."</span>";
				
				$filter->html .= '</li>';
				$i++;
			}
			$filter->html .= '</ul>';
			$filter->html .= '</div>';
			break;
		}
		
		if ( $print_logging_info ) {
			$current_filter_creation = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$flt_active_count = isset($filters_where) ? count($filters_where) : 0;
			$faceted_str = array(0=>'non-FACETED ', 1=>'FACETED: current view &nbsp; (cacheable) ', 2=>'FACETED: current filters:'." (".$flt_active_count.' active) ');
			
			$fc_run_times['create_filter'][$filter->name] = $current_filter_creation + (!empty($fc_run_times['create_filter'][$filter->name]) ? $fc_run_times['create_filter'][$filter->name] : 0);
			if ( isset($fc_run_times['_create_filter_init']) ) {
				$fc_run_times['create_filter'][$filter->name] -= $fc_run_times['_create_filter_init'];
				$fc_run_times['create_filter_init'] = $fc_run_times['_create_filter_init'] + (!empty($fc_run_times['create_filter_init']) ? $fc_run_times['create_filter_init'] : 0);
				unset($fc_run_times['_create_filter_init']);
			}
			
			$fc_run_times['create_filter_type'][$filter->name] = $faceted_str[$faceted_filter];
		}
		
		//$filter_display_typestr = array(0=>'Single Select', 1=>'Single Text', 2=>'Range Dual Select', 3=>'Range Dual Text', 4=>'Radio Buttons', 5=>'Checkbox Buttons');
		//echo "FIELD name: <b>". $filter->name ."</b> Field Type: <b>". $filter->field_type."</b> Filter Type: <b>". $filter_display_typestr[$display_filter_as] ."</b> (".$display_filter_as.") ".sprintf(" %.2f s",$current_filter_creation/1000000)." <br/>";
	}
	
	
	// Method to create a calendar form field according to a given configuation, e.g. called during Filter Creation of FC views
	static function createCalendarField($value, $date_allowtime, $fieldname, $elementid, $attribs='', $skip_on_invalid=false, $timezone=false)
	{
		// 'false' timezone means ==> use server setting (=joomla site configured TIMEZONE),
		// in J1.5 this must be null for using server setting (=joomla site configured OFFSET)
		$timezone = ($timezone === false && !FLEXI_J16GE) ? null : $timezone;
		
		@list($date, $time) = preg_split('#\s+#', $value, $limit=2);
		$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;
		
		try {
			// we check if date has no SYNTAX error (=being invalid) so use $gregorian = true,
			// to avoid it being change according to CALENDAR of current user
			// because user already entered the date in his/her calendar
			if ( !$value ) {
				$date = '';
			} else if (!$date_allowtime || !$time) {
				$date = JHTML::_('date',  $date, 'Y-m-d', $timezone, $gregorian = true);
			} else {
				$date = JHTML::_('date',  $value, 'Y-m-d H:i', $timezone, $gregorian = true);
			}
		} catch ( Exception $e ) {
			if (!$skip_on_invalid) return '';
			else $date = '';
		}
		
		// Create JS calendar
		$date_format = '%Y-%m-%d';
		$time_formats_map = array('0'=>'', '1'=>' %H:%M', '2'=>' 00:00');
		$date_time_format = $date_format . $time_formats_map[$date_allowtime];
		$calendar = JHTML::_('calendar', $date, $fieldname, $elementid, $date_time_format, $attribs);
		return $calendar;
	}
	
	
	// Method to create filter values for a field filter to be used in content lists views (category, etc)
	static function createFilterValues($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$lang_filter_values = $filter->parameters->get( 'lang_filter_values', 1);
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		
		//echo "<b>FILTER NAME</b>: ". $filter->label ."<br/>\n";
		//echo "<b> &nbsp; view_join</b>: <br/>". $view_join ."<br/>\n";
		//echo "<b> &nbsp;view_where</b>: <br/>". $view_where ."<br/>\n";
		//echo "<b> &nbsp;filters_where</b>: <br/>". print_r($filters_where, true) ."<br/><br/>\n";
		//exit;
		
		if ($faceted_filter || !$indexed_elements) {
			$_results = FlexicontentFields::getFilterValues($filter, $view_join, $view_where, $filters_where);
			//if ($filter->id==NN) echo "<pre>". $filter->label.": ". print_r($_results, true) ."\n\n</pre>";
		}
		
		// Support of value-indexed fields
		if ( !$faceted_filter && $indexed_elements) {
			// Clone 'indexed_elements' because they maybe modified
			$results = array();
			foreach ($indexed_elements as $i => $result) {
				$results[$i] = clone($result);
			}
		} else 
		if ( $indexed_elements ) {
			
			// Limit indexed element according to DB results found
			$results = array_intersect_key($indexed_elements, $_results);
			//echo "<pre>". $filter->label.": ". print_r($results, true) ."\n\n</pre>";
			if ($faceted_filter==2 && $show_matches) foreach ($results as $i => $result) {
				$result->found = $_results[$i]->found;
				// Clone 'indexed_elements' because they maybe modified
				$results[$i] = clone($result);
			}
			
		// Support for multi-property fields
		} else if ($search_prop) {
			
			// Check and unserialize values
			foreach ($_results as $i => $result) {
				$v = @unserialize($result->value);
				if ( $v || $result->value === 'b:0;' ) $_results[$i] = & $v;
			}
			
			// Index values via the search property
			$results = array();
			foreach ($_results as $i => $result) {
				if ( is_array($_results[$i]) ) $_results[$i] = (object) $_results[$i];
				else $_results[$i] = (object) array($search_prop=>$_results[$i]);
				if ( isset($_results[$i]->$search_prop) ) $results[ $_results[$i]->$search_prop ] = $_results[$i];
			}
		
		// non-indexable or single property field
		} else {
			$results = & $_results;
		}
		if (empty($results)) $results = array();
		
		// Language filter labels
		if ($lang_filter_values) {
			foreach ($results as $i => $result) {
				$results[$i]->text = JText::_($result->text);
			}
		}
		
		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby) ) {
			uksort($results, 'strnatcasecmp');
			if ($filter->parameters->get( 'reverse_filter_order', 0)) $results = array_reverse($results, true);
		}
		
		return $results;
	}
	
	
	// Method to create filter values for a field filter to be used in search view
	static function createFilterValuesSearch($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$lang_filter_values = $filter->parameters->get( 'lang_filter_values', 1);
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		
		$filter->filter_isindexed = (boolean) $indexed_elements; 
		if ($faceted_filter || !$indexed_elements) {
			$_results = FlexicontentFields::getFilterValuesSearch($filter, $view_join, $view_where, $filters_where);
			//echo "<pre>". $filter->label.": ". print_r($_results, true) ."\n\n</pre>";
		}
		
		// Support of value-indexed fields
		if ( !$faceted_filter && $indexed_elements) {
			// Clone 'indexed_elements' because they maybe modified
			$results = array();
			foreach ($indexed_elements as $i => $result) {
				$results[$i] = clone($result);
			}
		} else 
		if ( $indexed_elements && is_array($indexed_elements) ) {
			
			// Limit indexed element according to DB results found
			$results = array_intersect_key($indexed_elements, $_results);
			//echo "<pre>". $filter->label.": ". print_r($indexed_elements, true) ."\n\n</pre>";
			if ($faceted_filter==2 && $show_matches) foreach ($results as $i => $result) {
				$result->found = $_results[$i]->found;
				// Clone 'indexed_elements' because they maybe modified
				$results[$i] = clone($result);
			}
		} else {
			$results = & $_results;
		}
		
		// Language filter values/labels (for indexed fields this is already done)
		if ($lang_filter_values && !$indexed_elements) {
			foreach ($results as $i => $result) {
				$results[$i]->text = JText::_($result->text);
			}
		}
		
		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby_adv) ) {
			uksort($results, 'strnatcasecmp');
			if ($filter->parameters->get( 'reverse_filter_order', 0)) $results = array_reverse($results, true);
		}
		
		return $results;
	}
	
	
	// Retrieves all available filter values of the given field according to the given VIEW'S FILTERING (Content Lists)
	static function getFilterValues(&$filter, &$view_join, &$view_where, &$filters_where)
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$db = JFactory::getDBO();
		
		$filter_where_curr = '';
		foreach ($filters_where as $filter_id => $filter_where) {
			if ($filter_id != $filter->id)  $filter_where_curr .= ' ' . $filter_where;
		}
		//echo "filter_where_curr : ". $filter_where_curr ."<br/>";
		
		// partial SQL clauses
		$valuesselect = @$filter->filter_valuesselect ? $filter->filter_valuesselect : ' fi.value AS value, fi.value AS text';
		$valuesfrom   = @$filter->filter_valuesfrom   ? $filter->filter_valuesfrom   : (($filter->iscore || $filter->field_type=='coreprops') ? ' FROM #__content AS i' : ' FROM #__flexicontent_fields_item_relations AS fi ');
		$valuesjoin   = @$filter->filter_valuesjoin   ? $filter->filter_valuesjoin   : ' ';
		$valueswhere  = @$filter->filter_valueswhere  ? $filter->filter_valueswhere  : ' AND fi.field_id ='.$filter->id;
		// full SQL clauses
		$groupby = @$filter->filter_groupby ? $filter->filter_groupby : ' GROUP BY value ';
		$having  = @$filter->filter_having  ? $filter->filter_having  : '';
		$orderby = @$filter->filter_orderby ? $filter->filter_orderby : '';
		if ($filter->parameters->get( 'reverse_filter_order', 0) && $orderby) {
			$replace_count = null;
			$orderby = str_ireplace( ' ASC', ' DESC', $orderby, $replace_count);
			if (!$replace_count) $orderby .= ' DESC';
		}
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		
		$use_tmp = true;
		static $iids_subquery = null;
		static $iids_tblname  = array();
		$view_n_text = 'SELECT DISTINCT i.id '."\n"
			. ' FROM #__'.($use_tmp ? 'flexicontent_items_tmp' : 'content').' AS i'."\n"
			. $view_join."\n"
			. $view_where."\n"
			;
		if ( !isset($iids_tblname[$view_n_text]) ) {
			$iids_tblname[$view_n_text] = 'fc_view_iids_'.count($iids_tblname);
		}
		$tmp_tbl = $iids_tblname[$view_n_text];
		
		if ( $faceted_filter > 1 )
		{
			// Find items belonging to current view
			if ( !isset($iids_subquery[$view_n_text]) && empty($view_where) )  $iids_subquery[$view_n_text] = '';  // current view has not limits in where clause
			
			if ( !isset($iids_subquery[$view_n_text]) )
			{
				global $fc_run_times, $fc_jprof, $fc_catview;
				$start_microtime = microtime(true);
				
				try {
					// Use sub-query on temporary table
					$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
					$db->execute();
					$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
					$db->execute();
					$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$view_n_text);
					$db->execute();
					$iids_subquery[$view_n_text] = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/> FILTER INITIALIZATION - using temporary table: ".$iids_subquery[$view_n_text]." for :".$view_n_text ." <br/><br/>";
				}
				catch (Exception $e) {
					// Repeat sub-query if creating temporary table failed
					//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					$iids_subquery[$view_n_text] = $view_n_text;   //echo "<br/><br/> FILTER INITIALIZATION - using subquery: ".$iids_subquery[$view_n_text]." <br/><br/>";
					/*if ($fc_catview['search']) {
						$db->setQuery($view_n_text);
						$item_ids = $db->loadColumn();
						$iids_subquery[$view_n_text] = implode(',', $item_ids);   //echo "<br/><br/> FILTER INITIALIZATION - using item ID list: ".$iids_subquery[$view_n_text]." <br/><br/>";
					}*/
				}
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			
			$item_id_col = ($filter->iscore || $filter->field_type=='coreprops') ? 'i.id' : 'fi.item_id';
			$filter_where_curr = $filter->iscore ? $filter_where_curr : str_replace('i.id', 'fi.item_id', $filter_where_curr);
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT '.$item_id_col.') as found ' : '')."\n"
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND '.$item_id_col.' IN('.$iids_subquery[$view_n_text].')'."\n")
				. $filter_where_curr."\n"
				. $valueswhere."\n"
				. $groupby."\n"
				. $having."\n"
				. $orderby
				;
			//if ($filter->id==NN) echo $query."<br/><br/>";
		}
		
		// Non FACETED filter (according to view but without acounting for filtering and without counting items)
		else {
			$query = 'SELECT DISTINCT '. $valuesselect ."\n"
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. $valueswhere."\n"
				//. $groupby."\n"  // replaced by distinct, when not counting items
				. $having."\n"
				. $orderby
				;
		}
		//if ( in_array($filter->field_type, array('tags','created','modified')) ) echo nl2br($query);
		
		$db->setQuery($query);
		try {
			$results = $db->loadObjectList('value');
			if ($db->getErrorNum()) {
				$filter->html .= __FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br/>" .nl2br( $db->getErrorMsg() ) ."<br/>";
			}
		}
		catch (Exception $e) {
			$filter->html = __FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br />" .$e->getMessage() ."<br/>";
			return array();
		}
		
		return $results;
	}
	
	
	// Retrieves all available filter values of the given field according to the given VIEW'S FILTERING (Search view)
	static function getFilterValuesSearch(&$filter, &$view_join, &$view_where, &$filters_where)
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		
		$filter_where_curr = '';
		foreach ($filters_where as $filter_id => $filter_where) {
			if ($filter_id != $filter->id)  $filter_where_curr .= ' ' . $filter_where;
		}
		
		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$using_value_id = $isDate || @$filter->filter_isindexed;
		$valuesselect = $using_value_id ? ' ai.value_id as value, ai.search_index as text ' : ' ai.search_index as value, ai.search_index as text';
		$orderby = @$filter->filter_orderby_adv ? $filter->filter_orderby_adv : '';
		if ($filter->parameters->get( 'reverse_filter_order', 0) && $orderby) {
			$replace_count = null;
			$orderby = str_ireplace( ' ASC', ' DESC', $orderby, $replace_count);
			if (!$replace_count) $orderby .= ' DESC';
		}
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		
		$field_tbl = 'flexicontent_advsearch_index_field_'.$filter->id;
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . $field_tbl . '"';
		$db->setQuery($query);
		$tbl_exists = (boolean) count($db->loadObjectList());
		$field_tbl = $tbl_exists ? $field_tbl : 'flexicontent_advsearch_index';
		
		
		static $iids_subquery = null;
		static $iids_tblname  = array();
		
		$view_n_text = 'SELECT DISTINCT i.id '."\n"
			.' FROM #__content i '."\n"
			. $view_join."\n"
			. $view_where."\n"
			;
		if ( !isset($iids_tblname[$view_n_text]) ) {
			$iids_tblname[$view_n_text] = 'fc_view_iids_'.count($iids_tblname);
		}
		$tmp_tbl = $iids_tblname[$view_n_text];
		
		if ( $faceted_filter > 1 )
		{
			// Find items belonging to current view
			if ( !isset($iids_subquery[$view_n_text]) && empty($view_where) )  $iids_subquery[$view_n_text] = '';  // current view has not limits in where clause
			
			if ( !isset($iids_subquery[$view_n_text]) )
			{
				global $fc_run_times, $fc_jprof, $fc_searchview;
				
				$start_microtime = microtime(true);
				try {
					// Use sub-query on temporary table
					$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
					$db->execute();
					$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
					$db->execute();
					$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$view_n_text);
					$db->execute();
					$iids_subquery[$view_n_text] = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/> FILTER INITIALIZATION - using temporary table: ".$iids_subquery[$view_n_text]." for :".$view_n_text ." <br/><br/>";
				}
				catch (Exception $e) {
					// Repeat sub-query if creating temporary table failed
					//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					$iids_subquery[$view_n_text] = $view_n_text;   //echo "<br/><br/> FILTER INITIALIZATION - using subquery: ".$iids_subquery[$view_n_text]." <br/><br/>";
					/*if ($fc_searchview['search']) {
						$db->setQuery($view_n_text);
						$item_ids = $db->loadColumn();
						$iids_subquery[$view_n_text] = implode(',', $item_ids);   //echo "<br/><br/> FILTER INITIALIZATION - using item ID list: ".$iids_subquery[$view_n_text]." <br/><br/>";
					}*/
				}
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			
			// Get ALL records that have such values for the given field
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT ai.item_id) as found ' : '')."\n"
				. ' FROM #__'.$field_tbl.' AS ai'."\n"
				. ' WHERE ai.field_id='.(int)$filter->id."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND ai.item_id IN('.$iids_subquery[$view_n_text].')'."\n")
				.  str_replace('i.id', 'ai.item_id', $filter_where_curr)."\n"
				. ' GROUP BY ai.search_index, ai.value_id'."\n"
				. $orderby
				;
		}
		
		// Non FACETED filter (according to view but without acounting for filtering and without counting items)
		else {
			$query = 'SELECT DISTINCT '. $valuesselect."\n"
				. ' FROM #__'.$field_tbl.' AS ai'."\n"
				. ' WHERE ai.field_id='.(int)$filter->id."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND ai.item_id IN('.$iids_subquery[$view_n_text].')'."\n")
				.  str_replace('i.id', 'ai.item_id', $filter_where_curr)."\n"
				//. ' GROUP BY ai.search_index, ai.value_id'."\n"  // replaced by distinct, when not counting items
				. $orderby
				;
		}
		//echo $query."<br/><br/>";
		
		$db->setQuery($query);
		try {
			$results = $db->loadObjectList('value');
			if ($db->getErrorNum()) {
				$filter->html .= __FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br/>" .nl2br( $db->getErrorMsg() ) ."<br/>";
			}
		}
		catch (Exception $e) {
			$filter->html = __FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br />" .$e->getMessage() ."<br/>";
			return array();
		}
		
		static $search_prefix = null;
		if ($search_prefix === null) $search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		if ($search_prefix) foreach ($results as $i => $result)
		{
			$result->text = preg_replace('/\b'.$search_prefix.'/u', '', $result->text);
			if (!$using_value_id) $result->value = $result->text;
		}
		
		return $results;
	}
	
	
	/**
	 * Method to set custom filters values VIA configuration parameters
	 * -- CASE 1: CONTENT LISTS (component / category / menu items / filtering module)
	 *    these are set as HTTP Request variables to be used by the filtering mechanism of the category model (content lists)
	 * -- CASE 2: Custom Fields SCOPE of Universal Content MODULE
	 *    these are returned as an array to be used directly into the SQL query
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function setFilterValues( &$cparams, $mfilter_name='persistent_filters', $is_persistent=1, $set_method="httpReq" )
	{
		$field_filters = array();   // Used when set_method is 'array' instead of 'httpReq'
		$is_persistent =            // Non-httpReq method does not have initial filters
			$set_method!="httpReq" ? 1 : $is_persistent;
			
		// Get configuration parameter holding the custom field filtering and abort if empty
		$mfilter_data = $cparams->get($mfilter_name, '');
		if (!$mfilter_data) {
			$cparams->set($mfilter_name, '');  // Set to empty string for J1.5 compatibility, otherwise this could be empty array too
			return array();
		}
		
		// Parse configuration parameter into individual fields
		$mfilter_arr = preg_split("/[\s]*%%[\s]*/", $mfilter_data);
		if ( empty($mfilter_arr[count($mfilter_arr)-1]) ) {
			unset($mfilter_arr[count($mfilter_arr)-1]);
		}
		
		// This array contains the field (filter) ID that were parsed without errors
		$filter_ids = array();
		
		foreach ($mfilter_arr as $mfilter)
		{
			// a. Split elements into their properties: filter_id, filter_value
			$_data  = preg_split("/[\s]*##[\s]*/", $mfilter);  //print_r($_data);
			$filter_id = (int) $_data[0];
			$filter_value = @$_data[1];
			//echo "filter_".$filter_id.": "; print_r( $filter_value ); echo "<br/>";
			
			// b. Basic parsing error check: a non numeric field id
			if ( !$filter_id ) continue;
			
			// c. Add field (filter) ID into those that are valid
			$filter_ids[] = $filter_id;
			
			// d. Skip field filter, if it is not persistent and user user has overriden it
			if ( !$is_persistent && JRequest::getVar('filter_'.$filter_id, false) !== false ) continue;
			
			// CASE: range values:  value01---value02
			if (strpos($filter_value, '---') !== false) {
				$filter_value = explode('---', $filter_value);
				$filter_value[2] = $filter_value[1];
				$filter_value[1] = $filter_value[0];
				unset($filter_value[0]);
			}
			
			// CASE: multiple values:  value01+++value02+++value03+++value04
			else if (strpos($filter_value, '+++') !== false) {
				$filter_value = explode('+++', $filter_value);
			}
			
			// CASE: specific value:  value01
			else {}
			
			// INDIRECT method of using field filter (via HTTP request)
			if ($set_method=='httpReq')
				JRequest::setVar('filter_'.$filter_id, $filter_value);
			
			// DIRECT method of using field filter (via a returned array)
			else
				$field_filters[$filter_id] = $filter_value;
		}
		
		// INDIRECT method of using field filter (via HTTP request),
		// NOTE: we overwrite the above configuration parameter of custom field filters with an ARRAY OF VALID FILTER IDS, to 
		// indicate to category/search model security not to skip these if they are not IN category/search configured filters list
		if ($set_method=='httpReq') {
			count($filter_ids) ?
				$cparams->set($mfilter_name, FLEXI_J16GE ? $filter_ids : implode( '|', $filter_ids) ) :
				$cparams->set($mfilter_name, false );  // FALSE means do not retrieve ALL
		}
		
		// DIRECT method filter values, return an array of filter values (for direct usage into an SQL query)
		else {
			return $field_filters;
		}
	}	
	
	
	/**
	 * Method to get data of filters
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function &getFilters($filt_param='filters', $usage_param='use_filters', & $params = null, $check_access=true)
	{
		// Parameter that controls using these filters
		$filters = array();
		if ( $usage_param!='__ALL_FILTERS__' && $params && !$params->get($usage_param,0) ) return $filters;
		
		// Get Filter IDs, false means do retrieve any filter
		$filter_ids = $params  ?  $params->get($filt_param, array())  :  array();
		if ($filter_ids === false) return $filters;
		
		// Check if array or comma separated list
		if ( !is_array($filter_ids) ) {
			$filter_ids = preg_split("/\s*,\s*/u", $filter_ids);
			if ( empty($filter_ids[0]) ) unset($filter_ids[0]);
		}
		// Sanitize the given filter_ids ... just in case
		$filter_ids = array_filter($filter_ids, 'is_numeric');
		// array_flip to get unique filter ids as KEYS (due to flipping) ... and then array_keys to get filter_ids in 0,1,2, ... array
		$filter_ids = array_keys(array_flip($filter_ids));
		
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		
		// None selected filters means ALL
		$and_scope = $usage_param!='__ALL_FILTERS__' && count($filter_ids) ? ' AND fi.id IN (' . implode(',', $filter_ids) . ')' : '';
		
		// Use ACCESS Level, usually this is only for shown filters
		$and_access = '';
		if ($check_access) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$and_access = ' AND fi.access IN (0,'.$aid_list.') ';
		}
		
		// Create and execute SQL query for retrieving filters
		$query  = 'SELECT fi.*'
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.published = 1'
			. ' AND fi.isfilter = 1'
			. $and_access
			. $and_scope
			. ' ORDER BY fi.ordering, fi.name'
		;
		$db->setQuery($query);
		$filters = $db->loadObjectList('id');
		if ( !$filters ) {
			$filters = array(); // need to do this because we return reference, but false here will also mean an error
			return $filters;
		}
		
		// Order filters according to given order
		$filters_tmp = array();
		if ( $params->get('filters_order', 0) && !empty($filter_ids) && $usage_param!='__ALL_FILTERS__' ) {
			foreach( $filter_ids as $filter_id) {
				if ( empty($filters[$filter_id]) ) continue;
				$filter = $filters[$filter_id];
				$filters_tmp[$filter->name] = $filter;
			}
		}
		
		// Not re-ordering, but index them via fieldname in this case too (for consistency)
		else {
			foreach( $filters as $filter) {
				$filters_tmp[$filter->name] = $filter;
			}
		}
		$filters = $filters_tmp;
		
		// Create filter parameters, language filter label, etc
		foreach ($filters as $filter) {
			$filter->parameters = new JRegistry($filter->attribs);
			$filter->label = JText::_($filter->label);
		}
		
		// Return found filters
		return $filters;
	}
	
	
	/**
	 * Method to creat the HTML of filters
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function renderFilters( &$params, &$filters, $form_name )
	{
		// Make the filter compatible with Joomla standard cache
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$filter_prefix = ($form_name == 'item_form' ? 'iform_' : '') .'filter_';
		
		$display_label_filter_override = (int) $params->get('show_filter_labels', 0);
		foreach ($filters as $filter_name => $filter)
		{
			$filtervalue = JRequest::getVar($filter_prefix.$filter->id, '', 'default');
			//print_r($filtervalue);
			
			// make sure filter HTML is cleared, and create it
			$display_label_filter_saved = $filter->parameters->get('display_label_filter');
			if ( $display_label_filter_override ) $filter->parameters->set('display_label_filter', $display_label_filter_override); // suppress labels inside filter's HTML (hide or show all labels externally)
			
			// else ... filter default label behavior
			$filter->html = '';  // make sure filter HTML display is cleared
			$field_type = $filter->iscore ? 'core' : $filter->field_type;
			//$results 	= $dispatcher->trigger('onDisplayFilter', array( &$filter, $filtervalue ));
			FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFilter', array( &$filter, $filtervalue, $form_name ) );
			$filter->parameters->set('display_label_filter', $display_label_filter_saved);
		}
	}	

	
	
	// **********************************************************************************************************
	// Helper methods to create GENERIC ITEM LISTs which also includes RENDERED display of fields and custom HTML
	// **********************************************************************************************************
	
	// Helper method to perform HTML replacements on given list of item ids (with optional catids too), the items list is either given
	// as parameter or the list is created via the items that have as value the id of 'parentitem' for field with id 'reverse_field'
	static function getItemsList(&$params, &$_item_data=null, $isform=0, $reverse_field=0, &$parentfield, &$parentitem, &$return_item_list=false, $states=array(1,-5,2))
	{
		// Execute query to get item list data 
		$db = JFactory::getDBO();
		$query = FlexicontentFields::createItemsListSQL($params, $_item_data, $isform, $reverse_field, $parentfield, $parentitem, $states);
		$db->setQuery($query);
		$item_list = $db->loadObjectList('id');
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		// Item list must be returned too ...
		if ($return_item_list)  $return_item_list = & $item_list;
		
		// No published related items or SQL query failed, return
		if ( !$item_list ) return '';
		
		if ($_item_data) foreach($item_list as $_item)   // if it exists ... add prefered catid to items list data
			$_item->rel_catid = @ $_item_data[$_item->id]->catid;
		return FlexicontentFields::createItemsListHTML($params, $item_list, $isform, $parentfield, $parentitem, $_item_data);
	}
	
	
	// Helper method to create SQL query for retrieving items list data
	static function createItemsListSQL(&$params, &$_item_data=null, $isform=0, $reverse_field=0, &$parentfield, &$parentitem, $states=array(1,-5,2))
	{
		$db = JFactory::getDBO();
		$sfx = $isform ? '_form' : '';
		
		// Get data like aliases and published state
		$publish_where = '';
		if ($params->get('use_publish_dates', 1 ))
		{
			// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
			//  thus the items are published globally at the time the author specified in his/her local clock
			//$app  = JFactory::getApplication();
			//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
			//$date = JFactory::getDate();
			//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
			$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
			$nullDate = $db->getNullDate();
			
			$publish_where  = ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )'; 
			$publish_where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )';
		}
		if (count($states)) {
			$publish_where .= ' AND i.state IN ('.implode(',',$states).')';
		}
		
		// item IDs via reversing a relation field
		if ($reverse_field) {
			$item_join  = ' JOIN #__flexicontent_fields_item_relations AS fi_rel'
				.'  ON i.id=fi_rel.item_id AND fi_rel.field_id=' .$reverse_field .' AND CAST(fi_rel.value AS SIGNED)=' .$parentitem->id;
		}
		// item IDs via a given list (relation field and ... maybe other cases too)
		else {
			$item_where = ' AND i.id IN ('. implode(",", array_keys($_item_data)) .')';
		}
		
		// Get orderby SQL CLAUSE ('ordering' is passed by reference but no frontend user override is used (we give empty 'request_var')
		$order = $params->get( 'orderby'.$sfx, 'alpha' );
		$orderby = flexicontent_db::buildItemOrderBy($params, $order, $request_var='', $config_param='', $item_tbl_alias = 'i', $relcat_tbl_alias = 'rel', '', '', $sfx, $support_2nd_lvl=true);
		$orderby_join = '';
		
		// Create JOIN for ordering items by a custom field (use SFC)
		if ( 'field' == $order[1] ) {
			$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid'.$sfx, 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
		}
		
		// Create JOIN for ordering items by a custom field (Level 2)
		if ( $sfx=='' && 'field' == $order[2] ) {
			$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
		}
		
		// Create JOIN for ordering items by a most commented
		if ( in_array('commented', $order) ) {
			$orderby_col   = ', COUNT(DISTINCT com.id) AS comments_total';
			$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published="1"';
		}
		
		// Create JOIN for ordering items by a most rated
		if ( in_array('rated', $order) ) {
			$orderby_col   = ', (cr.rating_sum / cr.rating_count) * 20 AS votes';
			$orderby_join .= ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id';
		}
		
		// Create JOIN for ordering items by author name
		if ( in_array('author', $order) || in_array('rauthor', $order) ) {
			$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}
		
		// Because query includes specific items it should be fast
		$query = 'SELECT i.*, ext.*,'
			.' GROUP_CONCAT(c.id SEPARATOR  ",") AS catidlist, '
			.' GROUP_CONCAT(c.alias SEPARATOR  ",") AS  cataliaslist '
			. @ $orderby_col
			.' FROM #__content AS i '
			.' LEFT JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '
			. @ $item_join
			. @ $orderby_join
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '  // to get info for item's categories
			.' LEFT JOIN #__categories AS c ON c.id=rel.catid '
			.' WHERE 1 '
			. @ $item_where
			. $publish_where
			.' GROUP BY i.id '
			. $orderby
			;
		//echo "<pre>".$query."</pre>";
		return $query;
	}
	
	
	// Helper method to create HTML display of an item list according to replacements
	static function createItemsListHTML(&$params, &$item_list, $isform=0, &$parentfield, &$parentitem, &$_item_data=null)
	{
		$db = JFactory::getDBO();
		global $globalcats, $globalnoroute, $fc_run_times;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		// Get fields of type relation
		static $disallowed_fieldnames = null;
		$disallowed_fields = array('relation', 'relation_reverse');
		if ($disallowed_fieldnames===null) {
			$query = "SELECT name FROM #__flexicontent_fields WHERE field_type IN ('". implode("','", $disallowed_fields) ."')";
			$db->setQuery($query);
			$field_name_col = $db->loadColumn();
			$disallowed_fieldnames = !$field_name_col ? array() : array_flip($field_name_col);
		}
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space	= $params->get( 'remove_space', 0 ) ;
		$pretext			= $params->get( $isform ? 'pretext_form' : 'pretext', '' ) ;
		$posttext			= $params->get( $isform ? 'posttext_form' : 'posttext', '' ) ;
		$separatorf		= $params->get( $isform ? 'separator' : 'separatorf' ) ;
		$opentag			= $params->get( $isform ? 'opentag_form' : 'opentag', '' ) ;
		$closetag			= $params->get( $isform ? 'closetag_form' : 'closetag', '' ) ;
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// some parameter shortcuts
		$relitem_html = $params->get( $isform ? 'relitem_html_form' : 'relitem_html', '__display_text__' ) ;
		$displayway		= $params->get( $isform ? 'displayway_form' : 'displayway', 1 ) ;
		$addlink 			= $params->get( $isform ? 'addlink_form' : 'addlink', 1 ) ;
		$addtooltip		= $params->get( $isform ? 'addtooltip_form' : 'addtooltip', 1 ) ;
		
		// Parse and identify custom fields
		$result = preg_match_all("/\{\{([a-zA-Z_0-9]+)(##)?([a-zA-Z_0-9]+)?\}\}/", $relitem_html, $field_matches);
		$custom_field_reps    = $result ? $field_matches[0] : array();
		$custom_field_names   = $result ? $field_matches[1] : array();
		$custom_field_methods = $result ? $field_matches[3] : array();
		
		/*foreach ($custom_field_names as $i => $custom_field_name)
			$parsed_fields[] = $custom_field_names[$i] . ($custom_field_methods[$i] ? "->". $custom_field_methods[$i] : "");
		echo "$relitem_html :: Fields for Related Items List: ". implode(", ", $parsed_fields ? $parsed_fields : array() ) ."<br/>\n";*/
		
		// Parse and identify language strings and then make language replacements
		$result = preg_match_all("/\%\%([^%]+)\%\%/", $relitem_html, $translate_matches);
		$translate_strings = $result ? $translate_matches[1] : array('FLEXI_READ_MORE_ABOUT');
		foreach ($translate_strings as $translate_string)
			$relitem_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $relitem_html);
		
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			$itemslug = $result->id.":".$result->alias;
			$catslug = "";
			
			// Check if removed from category or inside a noRoute category or inside a non-published category
			// and use main category slug or other routable & published category slug
			$catid_arr = explode(",", $result->catidlist);
			$catalias_arr = explode(",", $result->cataliaslist);
			for($i=0; $i<count($catid_arr); $i++) {
				$itemcataliases[$catid_arr[$i]] = $catalias_arr[$i];
			}
			$rel_itemid = $result->id;
			$rel_catid = !empty($result->rel_catid) ? $result->rel_catid : $result->catid;
			if ( isset($itemcataliases[$rel_catid]) && !in_array($rel_catid, $globalnoroute) && $globalcats[$rel_catid]->published) {
				$catslug = $rel_catid.":".$itemcataliases[$rel_catid];
			} else if (!in_array($result->catid, $globalnoroute) && $globalcats[$result->catid]->published ) {
				$catslug = $globalcats[$result->catid]->slug;
			} else {
				foreach ($catid_arr as $catid) {
					if ( !in_array($catid, $globalnoroute) && $globalcats[$catid]->published) {
						$catslug = $globalcats[$catid]->slug;
						break;
					}
				}
			}
			$result->slug = $itemslug;
			$result->categoryslug = $catslug;
		}
		
		// Perform field's display replacements
		if ( $i_slave = $parentfield ? $parentitem->id."_".$parentfield->id : '' ) {
			$fc_run_times['render_subfields'][$i_slave] = 0;
		}
		foreach($custom_field_names as $i => $custom_field_name)
		{
			if ( isset($disallowed_fieldnames[$custom_field_name]) ) continue;
			if ( $custom_field_methods[$i] == 'label' ) continue;
			
			if ($i_slave) $start_microtime = microtime(true);
			
			$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
			FlexicontentFields::getFieldDisplay($item_list, $custom_field_name, $custom_field_values=null, $display_var);
			
			if ($i_slave) $fc_run_times['render_subfields'][$i_slave] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$display = array();
		foreach($item_list as $result)
		{
			$url_read_more = JText::_( isset($_item_data->url_read_more) ? $_item_data->url_read_more : 'FLEXI_READ_MORE_ABOUT' , 1);
			$url_class = (isset($_item_data->url_class) ? $_item_data->url_class : 'relateditem');
			
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			// a. Replace some custom made strings
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($result->slug, $result->categoryslug, 0, $result));
			$item_title_escaped = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8');
			
			$tooltip_title = flexicontent_html::getToolTip($url_read_more, $item_title_escaped, $translate=0, $escape=0);
			$item_tooltip = ' class="'.$url_class.$tooltip_class.'" title="'.$tooltip_title.'" ';
						
			$display_text = $displayway ? $result->title : $result->id;
			$display_text = !$addlink ? $display_text : '<a href="'.$item_url.'"'.($addtooltip ? $item_tooltip : '').' >' .$display_text. '</a>';
			
			$curr_relitem_html = $relitem_html;
			$curr_relitem_html = str_replace('__item_url__', $item_url, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_title_escaped__', $item_title_escaped, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_tooltip__', $item_tooltip, $curr_relitem_html);
			$curr_relitem_html = str_replace('__display_text__', $display_text, $curr_relitem_html);
			
			// b. Replace item properties, e.g. {item->id}, (item->title}, etc
			$null_field = null;
			FlexicontentFields::doQueryReplacements($curr_relitem_html, $null_field, $result);
			
			// c. Replace HTML display of various item fields
			$err_mssg = 'Cannot replace field: "%s" because it is of not allowed field type: "%s", which can cause loop or other problem';
			foreach($custom_field_names as $i => $custom_field_name) {
				$_field = @ $result->fields[$custom_field_name];
				$custom_field_display = '';
				if ($is_disallowed_field = isset($disallowed_fieldnames[$custom_field_name])) {
					$custom_field_display .= sprintf($err_mssg, $custom_field_name, @ $_field->field_type);
				} else {
					$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
					$custom_field_display .= @ $_field->{$display_var};
				}
				$curr_relitem_html = str_replace($custom_field_reps[$i], $custom_field_display, $curr_relitem_html);
			}
			$display[] = trim($pretext . $curr_relitem_html . $posttext);
		}
		
		$display = $opentag . implode($separatorf, $display) . $closetag;
		return $display;
	}
	
	
	
	
	
	// **********************************************
	// Helper methods for handling runtime statistics
	// **********************************************
	
	static function getFieldRenderTimes( &$fields_render_total=0 )
	{
		global $fc_run_times;
		$fields_render = array();
		
		$inline_css_val = 'float:left !important; display:inline-block !important;';
		$inline_css_lbl = 'float:left !important; display:inline-block !important; margin-left:8px !important; min-width:100px; text-align:left !important;';
		foreach ($fc_run_times['render_field'] as $field_type => $field_msecs)
		{
			// Total rendering time of fields
			$fields_render_total += $field_msecs;
			
			// Create Log a message about current field rendering time
			$fld_msg =
				'<span class="flexi value" style="'.$inline_css_val.'">'. sprintf("%.3f s",$field_msecs/1000000) .'</span>'.
				'<span class="flexi label" style="'.$inline_css_lbl.'">'.$field_type.'</span>'
				;
			// Check if field rendered other fields as part of it's display
			if ( isset($fc_run_times['render_subfields'][$field_type]) ) {
				$fld_msg .= " <small> - Field rendered other fields. Time was (retrieval+render)= ";
				$fld_msg .= sprintf("%.3f s", $fc_run_times['render_subfields'][$field_type]/1000000).'</small>';
			}
			$fields_render[] = $fld_msg;
		}
		return $fields_render;
	}
	
	
	static function getFilterCreationTimes( &$filters_creation_total=0 )
	{
		global $fc_run_times;
		$filters_creation = array();
		
		if ( isset($fc_run_times['create_filter_init']) ) {
			$filters_creation_total += $fc_run_times['create_filter_init'];
		}
		$inline_css_val = 'float:left !important; display:inline-block !important;';
		$inline_css_lbl = 'float:left !important; display:inline-block !important; margin-left:8px !important; min-width:100px !important; text-align:left !important;';
		foreach ($fc_run_times['create_filter'] as $field_type => $filter_msecs)
		{
			// Total creation time of filters
			$filters_creation_total += $filter_msecs;
			
			// Create Log a message about current filter creation time
			$fld_msg =
				'<span class="" style="'.$inline_css_val.'">'. sprintf("%.3f s",$filter_msecs/1000000) .'</span>'.
				'<span class="flexi label" style="'.$inline_css_lbl.'">'.$field_type.'</span>'.
				'<span class="" style="'.$inline_css_val.' min-width:200px;">'.$fc_run_times['create_filter_type'][$field_type].'</span>'
				;
			
			$filters_creation[] = $fld_msg;
		}
		return $filters_creation;
	}
	
	
	
	static function & getFieldsPerGroup()
	{
		static $ginfo = null;
		if ( $ginfo!==null ) return $ginfo;
		
		$db = JFactory::getDBO();
		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.published = 1'
			. ' AND f.field_type = "fieldgroup" '
			;
		$db->setQuery($query);
		$field_groups = $db->loadObjectList('id');
		
		$grp_to_field = array();
		$field_to_grp = array();
		foreach($field_groups as $field_id => $field_group) {
			// Create field parameters, if not already created, NOTEL: for 'custom' fields loadFieldConfig() is optional
			$field_group->parameters = new JRegistry($field_group->attribs);
			
			$fieldids = $field_group->parameters->get('fields', array());
			if ( empty($fieldids) ) {
				$fieldids = array();
			}
			if ( !is_array($fieldids) ) {
				$fieldids = preg_split("/[\|,]/", $fieldids);
			}
			
			$field_group->label = JText::_($field_group->label);
			foreach ($fieldids as $grouped_fieldid) {
				$grp_to_field[$field_id][] = $grouped_fieldid;
				$field_to_grp[$grouped_fieldid] = $field_id;
			}
		}
		$ginfo = new stdClass;
		$ginfo->grps = $field_groups;
		$ginfo->grp_to_field = $grp_to_field;
		$ginfo->field_to_grp = $field_to_grp;
		
		return $ginfo;
	}
	
}