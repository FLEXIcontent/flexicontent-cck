<?php
/**
 * @version 1.5 stable $Id$
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
		//require_once("components/com_flexicontent/classes/flexicontent.fields.php");
		require_once("components/com_flexicontent/classes/flexicontent.helper.php");
		
		
		// ***************************
		// Check if no data were given
		// ***************************
		
		if ( empty($item_ids) || empty($field_names) ) return false;
		
		// Get item data, needed for rendering fields
		$db = JFactory::getDBO();
		
		$item_ids = array_unique(array_map('intval', $item_ids));
		$item_ids_list = implode("," , $item_ids) ;
		
		$query = 'SELECT i.id, i.*, ie.*, '
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' WHERE i.id IN ('. $item_ids_list .')'
			. ' GROUP BY i.id';
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		if (!$items) return false;
		foreach ($items as $i => $item) $_item_id_map[$item->id] = & $items[$i];
		
		
		// **************
		// Get Field info
		// **************
		/*if ( $using_ids )
		{
			$field_ids = array_unique(array_map('intval', $_field_ids));
			$field_ids_list = implode("," , $field_ids) ;
			
			$field_where = ' WHERE f.id IN ('. $field_ids_list .')';
		}
		else {
			foreach ($field_names as $i => $field_name) {
				$field_names[$i] = preg_replace("/[\"'\\\]/u", "", $field_name);
			}
			$field_names_list = "'". implode("','" , $field_names) ."'";
			
			$field_where = 'f.name IN ('. $field_names_list .')';
		}
		
		$query = 'SELECT f.*'
			. ' FROM #__flexicontent_fields AS f'
			. ' WHERE 1 '.$field_where
			;
		$db->setQuery($query);
		$fields = $db->loadObjectList('id');
		if (!$fields) return false;*/
		
		
		// *********************************
		// Render Display Variable of Fields
		// *********************************
		
		// Get Field values at once to minimized performance impact, null 'params' mean only retrieve values 
		/*if ($item_per_field && count($items)>1)
			// we have at least 2 item and item is per field, this will retrieve all values with single SQL query
			FlexicontentFields::getFields($items, $view, $params = null, $aid = false);*/
		
		$return = array();
		foreach ($field_names as $i => $field_name)
		{
			$method = isset( $methods[$i] ) ? $methods[$i] : 'display';
			if ( $item_per_field )
			{
				if ( !isset( $_item_id_map[ $item_ids[$i] ] ) )  { echo "not found item: ".$item_ids[$i] ." <br/>"; continue;}
				
				// Render Display variable of Field for respective item
				$_item = & $_item_id_map[$item_ids[$i]];
				FlexicontentFields::getFieldDisplay($_item, $field_name, $values=null, $method, $view);
				// Add to return array
				$return[$_item->id][$field_name] = $_item->fields[$field_name]->$method;
			}
			else
			{
				// Render Display variable of Field for all items
				FlexicontentFields::getFieldDisplay($items, $field_name, $values=null, $method, $view);
				// Add to return array
				foreach ($items as $item) {
					$return[$item->id][$field_name] = $item->fields[$field_name]->display;
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
		static $apply_cache = null;
		static $expired_cleaned = false;
		
		if (!$_items) return $_items;
		if (!is_array($_items))  $items = array( & $_items );  else  $items = & $_items ;
		
		$user      = JFactory::getUser();
		$mainframe = JFactory::getApplication();
		$cparams   = $mainframe->getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
		// Calculate access if it was not providden
		if (FLEXI_J16GE) {
			$aid = is_array($aid) ? $aid : $user->getAuthorisedViewLevels();
		} else {
			$aid = $aid!==false ? (int) $aid : (int) $user->get('aid');
		}
		
		// Apply cache to public (unlogged) users only 
		if ($apply_cache === null) {
			if (FLEXI_J16GE) {
				$apply_cache = max($aid) <= 1;  // ACCESS LEVEL : PUBLIC 1 , REGISTERED 2
			} else {
				//$apply_cache = FLEXI_ACCESS ? ($user->gmid == '0' || $user->gmid == '0,1') : ($user->gid <= 18); // This is for registered too
				$apply_cache = $aid <= 0;  // ACCESS LEVEL : PUBLIC 0 , REGISTERED 1
			}
			$apply_cache = $apply_cache && FLEXI_CACHE;
		}
		if ($apply_cache) {
			$itemcache = JFactory::getCache('com_flexicontent_items');  // Get Joomla Cache of '...items' Caching Group
			$itemcache->setCaching(1); 		              // Force cache ON
			$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expiration to default e.g. one hour
			
			$filtercache = JFactory::getCache('com_flexicontent_filters');  // Get Joomla Cache of '...filters' Caching Group
			$filtercache->setCaching(1); 		              // Force cache ON
			$filtercache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expiration to default e.g. one hour
			
			// Auto-clean expired item & filters cache, only done here once
			if (FLEXI_GC && !$expired_cleaned) {
				$itemcache->gc();
				$filtercache->gc();
				$expired_cleaned = true;
			}
		}
		
		// @TODO : move to the constructor
		// This is optimized regarding the use of SINGLE QUERY to retrieve the core item data
		$vars['tags']       = FlexicontentFields::_getTags($items);
		$vars['cats']       = FlexicontentFields::_getCategories($items);
		$vars['favourites'] = FlexicontentFields::_getFavourites($items);
		$vars['favoured']   = FlexicontentFields::_getFavoured($items);
		$vars['authors']    = FlexicontentFields::_getAuthors($items);
		$vars['modifiers']  = FlexicontentFields::_getModifiers($items);
		$vars['typenames']  = FlexicontentFields::_getTypenames($items);
		$vars['votes']      = FlexicontentFields::_getVotes($items);
		$vars['custom']     = FlexicontentFields::_getCustomValues($items);
		
		foreach ($items as $i => $item)
		{
			$var = array();
			$item_id = $items[$i]->id;
			$var['cats']      = isset($vars['cats'][$item_id])      ? $vars['cats'][$item_id]             : array();
			$var['tags']      = isset($vars['tags'][$item_id])      ? $vars['tags'][$item_id]             : array();
			$var['favourites']= isset($vars['favourites'][$item_id])? $vars['favourites'][$item_id]->favs : 0;
			$var['favoured']  = isset($vars['favoured'][$item_id])  ? $vars['favoured'][$item_id]->fav    : 0;
			$var['authors']   = isset($vars['authors'][$item_id])   ? $vars['authors'][$item_id]          : '';
			$var['modifiers'] = isset($vars['modifiers'][$item_id]) ? $vars['modifiers'][$item_id]        : '';
			$var['typenames'] = isset($vars['typenames'][$item_id]) ? $vars['typenames'][$item_id]        : '';
			$var['votes']     = isset($vars['votes'][$item_id])     ? $vars['votes'][$item_id]            : '';
			$var['custom']    = isset($vars['custom'][$item_id])    ? $vars['custom'][$item_id]           : array();
			
			// NEW optimized code is faster WITHOUT CACHE ?
			/*if ( $apply_cache ) {
				$hits = $items[$i]->hits;
				$items[$i]->hits = 0;  // clear hits because it will prevent caching (changes frequently)
				$items[$i] = $itemcache->call(array('FlexicontentFields', 'getItemFields'), $items[$i], $var, $view, $aid);
				$items[$i]->hits = $hits;
			} else {*/
				$items[$i] = FlexicontentFields::getItemFields($items[$i], $var, $view, $aid);
			//}
		}
		if ( $print_logging_info )  @$fc_run_times['field_value_retrieval'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		if ($params)  // NULL/empty parameters mean only retrieve field values
		{
			// CHECK if 'always_create_fields_display' enabled and create the display for all item's fields
			// *** This should be normally set to ZERO (never), to avoid a serious performance penalty !!!
			foreach ($items as $i => $item)
			{
				$always_create_fields_display = $cparams->get('always_create_fields_display',0);
				$flexiview = JRequest::getVar('view', false);
				// 0: never, 1: always, 2: only in item view 
				if ($always_create_fields_display==1 || ($always_create_fields_display==2 && $flexiview==FLEXI_ITEMVIEW) ) {
					if ($items[$i]->fields)
					{
						foreach ($items[$i]->fields as $field)
						{
							$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
							$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display', $view);
						}
					}
				}
			}
			
			// Render field positions
			$items = FlexicontentFields::renderPositions($items, $view, $params, $use_tmpl);
		}
		return $items;
	}

	/**
	 * Method to fetch the fields from an item object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function getItemFields($item, $var, $view=FLEXI_ITEMVIEW, $aid=false)
	{
		if (!$item) return;
		if (!FLEXI_J16GE && $item->sectionid != FLEXI_SECTION) return;
		
		static $type_fields = array();
		
		$mainframe  = JFactory::getApplication();
		$dispatcher = JDispatcher::getInstance();
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();

		$cats			= $var['cats'];
		$tags			= $var['tags'];
		$favourites	= $var['favourites'];
		$favoured	= $var['favoured'];
		$author		= $var['authors'];
		$modifier	= $var['modifiers'];
		$typename	= $var['typenames'];
		$vote			= $var['votes'];
		
		if (FLEXI_J16GE) {
			$aid_arr = is_array($aid) ? $aid : $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid);
			$andaccess 	= ' AND fi.access IN (0,'.$aid_list.')' ;
			$joinaccess = '';
		} else {
			$aid = $aid!==false ? (int) $aid : (int) $user->get('aid');
			$andaccess 	= FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')' : ' AND fi.access <= '.$aid ;
			$joinaccess	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"' : '' ;
		}
		
		// ONCE per Content Item Type
		if ( !isset($type_fields[$item->type_id]) )
		{
			$query 	= 'SELECT fi.*'
					. ' FROM #__flexicontent_fields AS fi'
					. ' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id = '.$item->type_id
					. $joinaccess
					. ' WHERE fi.published = 1'
					. $andaccess
					. ' GROUP BY fi.id'
					. ' ORDER BY ftrel.ordering, fi.ordering, fi.name'
					;
			$db->setQuery($query);
			$type_fields[$item->type_id] = $db->loadObjectList('name');
		}
		$item->fields = array();
		if ($type_fields[$item->type_id]) foreach($type_fields[$item->type_id] as $field_name => $field_data)
			$item->fields[$field_name]	= clone($field_data);
		$item->fields	= $item->fields	? $item->fields	: array();
		
		jimport('joomla.html.parameter');
		if (!isset($item->parameters)) $item->parameters = FLEXI_J16GE ? new JRegistry($item->attribs) : new JParameter($item->attribs);
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
		$item->categories = & $item->cats;
		$item->favourites	= & $item->favs;
		$item->document_type = & $item->typename;
		$item->voting			= & $item->vote;
		
		// custom field values
		$item->fieldvalues = $var['custom'];
		
		/*if ($item->fields) {
			// IMPORTANT the items model and possibly other will set item PROPERTY version_id to indicate loading an item version,
			// It is not the responisibility of this CODE to try to detect previewing of an item version, it is better left to the model
			$item->fieldvalues = FlexicontentFields::_getFieldsvalues($item->id, $item->fields, !empty($item->version_id) ? $item->version_id : 0);
		}*/
		
		return $item;
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
		if ( !is_array($item_arr) ) 
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
	static function renderField(&$item, &$field, &$values, $method='display', $view=FLEXI_ITEMVIEW)
	{
		static $_trigger_plgs_ft = array();
		$flexiview = JRequest::getVar('view');
		
		// If $method (e.g. display method) is already created, then return the $field without recreating the $method
		if (isset($field->{$method})) return $field;
		
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		if ($print_logging_info)  global $fc_run_times;
		
		// Append some values to the field object
		$field->item_id 	= (int)$item->id;
		$field->value 		= $values;               // NOTE: currently ignored and overritten by all CORE fields
		
		// **********************************************************************************************
		// Create field parameters in an optimized way, and also apply Type Customization for CORE fields
		// **********************************************************************************************
		FlexicontentFields::loadFieldConfig($field, $item);
		
		
		// ***************************************************************************************************
		// Create field HTML by calling the appropriate DISPLAY-CREATING field plugin method.
		// NOTE 1: We will not pass the 'values' method parameter to the display-creating field method,
		//         instead we have set it above as the 'value' field property
		// NOTE 2: For CUSTOM fields the 'values' method parameter is prefered over the 'value' field property
		//         For CORE field, both the above ('values' method parameter and 'value' field property) are
		//         ignored and instead the other method parameters are used, along with the ITEM properties
		// ****************************************************************************************************
		// Log content plugin and other performance information
		if ($print_logging_info)  $start_microtime = microtime(true);
		if ($field->iscore == 1)  // CORE field
		{
			//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$field, $item, &$item->parameters, $item->tags, $item->cats, $item->favs, $item->fav, $item->vote ));
			FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, $item, &$item->parameters, $item->tags, $item->cats, $item->favs, $item->fav, $item->vote, null, $method ) );
		}
		else                      // NON CORE field
		{
			//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
			FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array(&$field, $item, null, $method) );
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
		if ( !isset($_trigger_plgs_ft[$field->name]) ) {
			$_t = $field->parameters->get('trigger_onprepare_content', 0);
			if ($flexiview=='category') $_t = $_t && $field->parameters->get('trigger_plgs_incatview', 1);
			$_trigger_plgs_ft[$field->name] = $_t;
		}
		
		if ( $_trigger_plgs_ft[$field->name] ) {
			if ($print_logging_info)  $start_microtime = microtime(true);	
			FlexicontentFields::triggerContentPlugins($field, $item, $method, $view);
			if ( $print_logging_info ) @$fc_run_times['content_plg'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		return $field;		
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
		$field->text = isset($field->{$method}) ? $field->{$method} : '';
		$field->title = $item->title;
		$field->slug = $item->slug;
		$field->sectionid = !FLEXI_J16GE ? $item->sectionid : false;
		$field->catid = $item->catid;
		$field->catslug = @$item->categoryslug;
		$field->fieldid = $field->id;
		$field->id = $item->id;
		$field->state = $item->state;
		$field->type_id = $item->type_id;

		// CASE: FLEXIcontent item view:
		// Set triggering 'context' to 'com_content.article', (and also set the 'view' request variable)
		if ($view == FLEXI_ITEMVIEW) {
		  JRequest::setVar('view', 'article');
		  $context = 'com_content.article';
		}
		
		// ALL OTHER CASES: (FLEXIcontent category, FLEXIcontent module, etc),
		// Set triggering 'context' to 'com_content.article', (and also set the 'view' request variable)
		else {
		  JRequest::setVar('view', 'category');
		  $context = 'com_content.category';
		}
		
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
	static function &renderPositions(&$items, $view = FLEXI_ITEMVIEW, $params = null, $use_tmpl = true)
	{
		if (!$items) return;
		if (!$params) return $items;
		
		if ($view == 'category')			$layout = 'clayout';
		if ($view == FLEXI_ITEMVIEW)	$layout = 'ilayout';
		
		// field's source code, can use this JRequest variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		JRequest::setVar("flexi_callview", $view);

		if ( $use_tmpl && ($view == 'category' || $view == FLEXI_ITEMVIEW) ) {
		  $fbypos = flexicontent_tmpl::getFieldsByPositions($params->get($layout, 'default'), $view);
		} else { // $view == 'module', or other
			// Create a fake template position, for fields defined via parameters
		  $fbypos[0] = new stdClass();
		  $fbypos[0]->fields = explode(',', $params->get('fields'));
		  $fbypos[0]->methods = explode(',', $params->get('methods'));
		  $fbypos[0]->position = $view;
		}
		
		$always_create_fields_display = $params->get('always_create_fields_display',0);
		
		// *** RENDER fields on DEMAND, (if present in template positions)
		for ($i=0; $i < sizeof($items); $i++)
		{
			if ($always_create_fields_display != 3) { // value 3 means never create for any view (blog template incompatible)
				
			  // 'description' item field is implicitly used by category layout of some templates (blog), render it
			  $custom_values = false;
			  if ($view == 'category') {
			    if (isset($items[$i]->fields['text'])) {
			    	$field = $items[$i]->fields['text'];
			    	$field = FlexicontentFields::renderField($items[$i], $field, $custom_values, $method='display', $view);
			    }
			  }
				// 'core' item fields are IMPLICITLY used by some item layout of some templates (blog), render them
				else if ($view == FLEXI_ITEMVIEW) {
					foreach ($items[$i]->fields as $field) {
						if ($field->iscore) {
							$field 	= FlexicontentFields::renderField($items[$i], $field, $custom_values, $method='display', $view);
						}
					}
				}
		  }
		  
		  // RENDER fields if they are present in a template position (or in a dummy template position ... e.g. when called by module)
			foreach ($fbypos as $pos) {
				foreach ($pos->fields as $c => $f) {
					// Check that field with given name: $f exists, (this will handle deleted fields, that still exist in a template position)
					if (!isset($items[$i]->fields[$f])) {	
						continue;
					}
					$field = $items[$i]->fields[$f];
					
					// Set field values, currently, this exists for CUSTOM fields only, OR versioned CORE/CUSTOM fields too ...
					$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
					
					// Render field (if already rendered above, the function will return result immediately)
					$method = (isset($pos->methods[$c]) && $pos->methods[$c]) ? $pos->methods[$c] : 'display';
					$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method, $view);
					
					// Set template position field data
					if (isset($field->display) && strlen($field->display))
					{
						if (!isset($items[$i]->positions[$pos->position]))
							$items[$i]->positions[$pos->position] = new stdClass();
						$items[$i]->positions[$pos->position]->{$f} = new stdClass();
						
						$items[$i]->positions[$pos->position]->{$f}->id				= $field->id;
						$items[$i]->positions[$pos->position]->{$f}->id				= $field->id;
						$items[$i]->positions[$pos->position]->{$f}->name			= $field->name;
						$items[$i]->positions[$pos->position]->{$f}->label		= $field->parameters->get('display_label') ? $field->label : '';
						$items[$i]->positions[$pos->position]->{$f}->display	= $field->display;
					}
				}
			}
		}
		return $items;
	}

	/**
	 * Method to get the values of the fields for an item
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function _getFieldsvalues($item, $fields, $version=0)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT field_id, value'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id = ' . (int)$item
				.( $version ? ' AND version=' . (int)$version:'')
				.' AND value > "" '
				.' ORDER BY field_id, valueorder'
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();

		$fieldvalues = array();
		foreach ($fields as $f) {
			foreach ($values as $v) {
				if ((int)$f->id == (int)$v->field_id) {
					$fieldvalues[$f->id][] = $v->value;
				}
			}
		}
		return $fieldvalues;
	}
	
	
	/**
	 * Method to get the values of the fields for multiple items at once
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function _getCustomValues($items)
	{
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);
		$version = $versioned_item ? $items[0]->version_id : 0;
		
		$item_ids = array();
		foreach ($items as $item) $item_ids[] = $item->id;
		
		$db = JFactory::getDBO();
		$query = 'SELECT field_id, value, item_id'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id IN (' . implode(',', $item_ids) .')'
				.( $version ? ' AND version=' . (int)$version:'')
				.' AND value > "" '
				.' ORDER BY item_id, field_id, valueorder'
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();
		
		$fieldvalues = array();
		foreach ($values as $v) {
			$fieldvalues[$v->item_id][$v->field_id][] = $v->value;
		}
		return $fieldvalues;
	}
	
	
	/**
	 * Method to get the tags
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getTags($items)
	{
		// This is fix for versioned field of creator in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->tags);
		
		$db = JFactory::getDBO();

		if ($versioned_item) {
			if (!count($items[0]->tags)) return array();
			$tids = $items[0]->tags;
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
	static function _getCategories($items)
	{
		// This is fix for versioned field of creator in items view when previewing
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
	static function _getFavourites($items)
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
	static function _getFavoured($items)
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
	static function _getModifiers($items)
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
	static function _getAuthors($items)
	{
		// This is fix for versioned field of creator in items view when previewing
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
	static function _getTypenames($items)
	{
		$db = JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT ie.item_id, t.name FROM #__flexicontent_items_ext AS ie'
				. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ie.type_id'
				. " WHERE ie.item_id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('item_id');
		
		return $types;
	}

	/**
	 * Method to get the votes of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getVotes($items)
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
		if ($no_typeparams) $no_typeparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		static $is_form=null;
		if ($is_form===null) $is_form = JRequest::getVar('task')=='edit' && JRequest::getVar('option')=='com_flexicontent';
		
		// Create basic field data if no field given
		if (!empty($name)) {
			$field->iscore = $iscore;  $field->name = $name;  $field->field_type = $field_type;  $field->label = $label;  $field->description = $desc;  $field->attribs = '';
		}
		
		// Get Content Type parameters if not already retrieved
		$type_id = @$item->type_id;
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
				$fdata[$tindex][$field->name]->parameters = FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
				
			} else {
				
				$pn_prefix = $field->field_type!='maintext' ? $field->name : $field->field_type;
				
				// Initialize an empty object, and create parameters object of the field
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
				
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
					$ts_params = FLEXI_J16GE ? new JRegistry($data->attribs) : new JParameter($data->attribs);
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
			$core_field_names = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
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
			$tparams = FLEXI_J16GE ? new JRegistry($typedata->attribs) : new JParameter($typedata->attribs);
			
			$_tparams = $tparams->toArray();
			$tinfo['params'] = array();
			
			// Extra voting parameters of --voting-- field parameters are overriden contiditionally
			if ( $tparams->get('voting_override_extra_votes', 0) ) {
				$tinfo['params']['voting']['voting_extra_votes'] = $tparams->get('voting_extra_votes', '');
				$main_label = $tparams->get('voting_main_label', '') ? $tparams->get('voting_main_label', '') : JText::_('FLEXI_OVERALL');  // Set default label in case of empty
				$tinfo['params']['voting']['main_label'] = $main_label;
			}
			
			foreach ($_tparams as $param_name => $param_value) {
				$res = preg_split('/_/', $param_name, 2);
				if ( count($res) < 2 ) continue;
				
				$o_field_type = $res[0];  $o_param_name = $res[1];
				if ( !isset($core_field_names[$o_field_type]) ) continue;
				
				//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
				$skipparam = false;
				
				if ( strlen($param_value) ) {
					if ($o_field_type=='maintext' && $o_param_name=='hide_html') {
						$tinfo['params'][$o_field_type]['use_html'] = !$param_value;
					} else if ($o_field_type=='voting') {
						$skipparam = in_array($o_param_name, array('override_extra_votes','voting_extra_votes','voting_main_label'));
					} else if ( in_array($o_param_name, array('label','desc','viewdesc')) ) {
						$skipparam = true;
					}
					if (!$skipparam) {
						$tinfo['params'][$o_field_type][$o_param_name] = $param_value;
						//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
					}
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
	static function indexedField_getElements(&$field, $item, $extra_props=array(), &$item_pros=true, $create_filter=false)
	{
		static $_elements_cache = null;
		if ( isset($_elements_cache[$field->id]) ) return $_elements_cache[$field->id];
		$canCache = true;
		
		$sql_mode = $field->parameters->get( 'sql_mode', 0 ) ;   // For fields that use this parameter
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		
		if ($create_filter) {
			$filter_customize_options = $field->parameters->get('filter_customize_options', 0);
			$filter_custom_options    = $field->parameters->get('filter_custom_options', '');
			if ( $filter_customize_options && $filter_custom_options) {
				// Custom query for value retrieval
				$sql_mode =  $filter_customize_options==1;
				$field_elements = $filter_custom_options;
			} else if ( !$field_elements ) {
				$field_elements = "SELECT value, value as text FROM #__flexicontent_fields_item_relations as fir WHERE field_id='{field_id}' AND value != '' GROUP BY value";
			}
			// Set parameters may be used later
			$field->parameters->set('sql_mode', $sql_mode);
			$field->parameters->set('field_elements', $field_elements);
		}
		
		if ($sql_mode) {  // SQL mode, parameter field_elements contains an SQL query
			
			$db = JFactory::getDBO();
			
			// Get/verify query string, check if item properties and other replacements are allowed and replace them
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$query = FlexicontentFields::doQueryReplacements($field_elements, $field, $item, $item_pros, $canCache);
			
			// Execute SQL query to retrieve the field value - label pair, and any other extra properties
			if ( $query ) {
				$db->setQuery($query);
				$results = $db->loadObjectList('value');
			}
			
			// !! CHECK: DB query failed or produced no data
			if (!$query || !is_array($results)) {
				if ( !$canCache ) return false;
				else return ($_elements_cache[$field->id] = false);
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
			foreach ($listelements as $listelement) {
				$listelement_props  = preg_split("/[\s]*::[\s]*/", $listelement);
				if (count($listelement_props) < $props_needed) {
					echo "Error in field: ".$field->label." while splitting element: ".$listelement." properties needed: ".$props_needed." properties found: ".count($listelement_props);
					return ($_elements_cache[$field->id] = false);
				}
				$val = $listelement_props[0];
				$results[$val] = new stdClass();
				$results[$val]->value = $listelement_props[0];
				$results[$val]->text  = JText::_($listelement_props[1]);  // the text label
				$el_prop_count = 2;
				foreach ($extra_props as $extra_prop) {
					$results[$val]->{$extra_prop} = @ $listelement_props[$el_prop_count];  // extra property for fields that use it
					$el_prop_count++;
				}
			}
			
		}
		
		// Return found elements, caching them if possible (if no item specific elements are used)
		if ( $canCache ) $_elements_cache[$field->id] = & $results;
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
		$query = str_replace("{curr_userlang_shorttag}", flexicontent_html::getUserCurrentLang(), $query);
		$query = str_replace("{curr_userlang_fulltag}", flexicontent_html::getUserCurrentLang(), $query);
		return $query;
	}
	
	
	// Helper method to replace a field value inside a given named variable of a given item/field pair
	static function replaceFieldValue( &$field, &$item, $variable, $varname )
	{
		static $parsed = array();
		static $d;
		static $c;
		
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
			}
			
			$result = preg_match_all("/\{\{(item->)([a-zA-Z_0-9]+)\}\}/", $variable, $field_matches);
			if ($result) {
				$c[$field->id][$varname]['fulltxt']   = $field_matches[0];
				$c[$field->id][$varname]['propname']  = $field_matches[2];
			} else {
				$c[$field->id][$varname]['fulltxt']   = array();
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
			if ( $is_indexable )
			{
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
			.' ORDER BY f.ordering, f.name'
		;
		$db->setQuery($query);
		$fields = $db->loadObjectList($key);
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
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
			if ($load_params) $field->parameters = FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
			$sp_fields[$field_id] = $field;
		}
		
		if ($indexer=='advanced' && $search_type=='dirty-nosupport')
			return $nsp_fields;
		else
			return $sp_fields;
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
		$supportuntranslatable = $supportuntranslatable && $cparams->get('enable_translation_groups');
		
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
	
	
	// Common method to create basic/advanced search index for various fields
	static function createIndexRecords(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null, $for_advsearch=0) {
		$fi = FlexicontentFields::getPropertySupport($field->field_type, $field->iscore);
		$db = JFactory::getDBO();
			
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
			$items_values = FlexicontentFields::searchIndex_getFieldValues($field,$item, $for_advsearch);
		} else {
			$items_values = !is_array($values) ? array($values) : $values;
			$items_values = array($field->item_id => $items_values);
		}
		
		// Make sure posted data is an array 
		$unserialize = (isset($field->unserialize)) ? $field->unserialize : ( count($required_props) || count($search_props) );
		
		// Create the new search data
		foreach($items_values as $itemid => $item_values) 
		{
			if ( @$field->isindexed ) {
				// Get Elements of the field these will be cached if they do not depend on the item ...
				$field->item_id = $itemid;   // in case it needs to be loaded to replace item properties in a SQL query
				$elements = FlexicontentFields::indexedField_getElements($field, $item, $field->extra_props, $item_pros=false, $createFilter=true);
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
				
				// Create search value
				$search_value = array();
				foreach ($search_props as $sp) {
					if ( isset($v[$sp]) && strlen($v[$sp]) ) $search_value[] = $v[$sp];
				}
				
				if (count($search_props) && !count($search_value)) continue;  // all search properties were empty, skip this value
				$searchindex[$vi] = (count($search_props))  ?  implode($props_spacer, $search_value)  :  $v;
				$searchindex[$vi] = $filter_func ? $filter_func($searchindex[$vi]) : $searchindex[$vi];
			}
			
			if ( !$for_advsearch )
			{
				$field->search[$itemid] = implode(' | ', $searchindex);
			}
			
			else {
				$n = 0;
				foreach ($searchindex as $vi => $search_text)
				{
					// Add new search value into the DB
					$query_val = "( "
						.$field->id. "," .$itemid. "," .($n++). "," .$db->Quote($search_text). "," .$db->Quote($vi).
					")";
					$field->ai_query_vals[] = $query_val;
				}
			}
		}
		
		//echo $field->name . ": "; print_r($values);echo "<br/>";
		//echo implode(' | ', $searchindex) ."<br/><br/>";
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
	static function createFilterValueMatchSQL(&$filter, &$value, $is_full_text=0, $is_search=0)
	{
		$db = JFactory::getDBO();
		$display_filter_as = $filter->parameters->get( $is_search ? 'display_filter_as_s' : 'display_filter_as', 0 );
		$filter_compare_type = $filter->parameters->get( 'filter_compare_type', 0 );
		$filter_values_combination = $filter->parameters->get( 'filter_values_combination', 0 );
		//echo "createFilterValueMatchSQL : filter name: ".$filter->name." Filter Type: ".$display_filter_as." Values: "; print_r($value); echo "<br>";
		
		// Make sure the current filtering values match the field filter configuration to be single or multi-value
		if ( in_array($display_filter_as, array(2,3,5,6)) ) {
			if (!is_array($value)) $value = array( $value );
		} else {
			if (is_array($value)) $value = array ( @ $value[0] );
			else $value = array ( $value );
		}
		
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
		case 2: case 3:
			if ( ! @ $quoted ) foreach($value as $i => $v) {
				if ( !$filter_compare_type ) $value[$i] = $db->Quote($v);
				else $value[$i] = $filter_compare_type==1 ? intval($v) : floatval($v);
			}
			$value_empty = !strlen(@$value[1]) && strlen(@$value[2]) ? ' OR _v_="" OR _v_ IS NULL' : '';
			if ( strlen(@$value[1]) ) $valueswhere .= ' AND (_v_ >=' . $value[1] . ')';
			if ( strlen(@$value[2]) ) $valueswhere .= ' AND (_v_ <=' . $value[2] . $value_empty . ')';
			break;
		// SINGLE TEXT select value cases
		case 1:
			// DO NOT put % in front of the value since this will force a full table scan instead of indexed column scan
			$_value_like = $value[0].($is_full_text ? '*' : '%');
			if (empty($quoted))  $_value_like = $db->Quote($_value_like);
			if ($is_full_text)
				$valueswhere .= ' AND  MATCH (_v_) AGAINST ('.$_value_like.' IN BOOLEAN MODE)';
			else
				$valueswhere .= ' AND _v_ LIKE ' . $_value_like;
			break;
		// EXACT value cases
		case 0: case 4: case 5: default:
			$value_clauses = array();
			foreach ($value as $val) {
				$value_clauses[] = '_v_=' . $db->Quote( $val );
			}
			$comb_op = $filter_values_combination ? 'AND' : ' OR ';
			$valueswhere .= ' AND ('.implode($comb_op, $value_clauses).') ';
			break;
		}
		
		//echo $valueswhere . "<br>";
		return $valueswhere;
	}
	
	
	// Method to get the active filter result for Content Lists Views (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFiltered( &$filter, $value, $return_sql=false )
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
		
		if ( @$filter->filter_valuesjoin ) {
			$query  = 'SELECT DISTINCT id'
				.' FROM #__content c'
				.$filter->filter_valuesjoin
				.' WHERE 1'
				. $valueswhere
				;
		} else {
			$query  = 'SELECT DISTINCT item_id'
				.' FROM #__flexicontent_fields_item_relations as rel'
				.' WHERE rel.field_id = ' . $filter->id
				. $valueswhere
				;
		}
		//$query .= ' GROUP BY c.id';   // VERY VERY BAD PERFORMANCE
		
		if ( !$return_sql ) {
			//echo "<br>FlexicontentFields::getFiltered() ".$filter->name." appying  query :<br>". $query."<br>\n";
			$db->setQuery($query);
			$filtered = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			return $filtered;
		} else {
			return ' AND i.id IN ('. $query .')';
		}
	}
	
	
	// Method to get the active filter result Search View (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFilteredSearch( &$filter, $value, $return_sql=false )
	{
		$db = JFactory::getDBO();
		
		// Check if field type supports advanced search
		$support = FlexicontentFields::getPropertySupport($filter->field_type, $filter->iscore);
		if ( ! $support->supportadvsearch && ! $support->supportadvfilter )  return null;
		
		$valueswhere = FlexicontentFields::createFilterValueMatchSQL($filter, $value, $is_full_text=1, $is_search=1);
		if ( !$valueswhere ) { return; }

		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );
		$istext_input = $display_filter_as==1 || $display_filter_as==3;
		//$colname = $istext_input ? 'fs.search_index' : 'fs.value_id';
		$colname = @ $filter->isindexed && !$istext_input ? 'fs.value_id' : 'fs.search_index';
		
		$valueswhere = str_replace('_v_', $colname, $valueswhere);
		
		// Get ALL items that have such values for the given field
		$query = "SELECT fs.item_id "
			." FROM #__flexicontent_advsearch_index AS fs"
			." WHERE fs.field_id='".$filter->id."' "
			. $valueswhere
			;

		if ( !$return_sql ) {
			//echo "<br>FlexicontentFields::getFiltered() ".$filter->name." appying  query :<br>". $query."<br>\n";
			$db->setQuery($query);
			$filtered = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			return $filtered;
		} else {
			return ' AND i.id IN ('. $query .')';
		}
	}
	
	
	
	
	
	// **********************************************
	// Methods for creating Field Filters of FC views
	// **********************************************
	
	// Method to create a category (content list) or search filter
	static function createFilter(&$filter, $value='', $formName='adminForm', $indexed_elements=false, $search_prop='')
	{
		static $apply_cache = null;
		$user = JFactory::getUser();
		$mainframe = JFactory::getApplication();
		//$cparams   = $mainframe->getParams('com_flexicontent');
		$cparams   = JComponentHelper::getParams('com_flexicontent');  // createFilter maybe called in backend too ...
		$print_logging_info = $cparams->get('print_logging_info');
		
		global $is_fc_component;
		$view = JRequest::getVar('view');
		$isCategoryView = $is_fc_component && $view=='category';
		$isSearchView   = $is_fc_component && $view=='search';
		
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
		// Apply caching to public or just registered users
		$apply_cache = 1;
		if ($apply_cache) {
			$itemcache = JFactory::getCache('com_flexicontent_filters');  // Get Joomla Cache of '...items' Caching Group
			$itemcache->setCaching(1); 		              // Force cache ON
			$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expiration to default e.g. one hour
		}
		
		$isdate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$default_size = $isdate ? 15 : 30;
		$_s = $isSearchView ? '_s' : '';
		
		// Some parameter shortcuts
		$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 0 ) ;   // How to show filter label
		$size         = $filter->parameters->get( 'text_filter_size', $default_size );        // Size of filter
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3)) ;
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items'.$_s, 1 );
		$show_matches = $filter_as_range || !$faceted_filter ?  0  :  $show_matching_items;
		$hide_disabled_values = $filter->parameters->get( 'hide_disabled_values'.$_s, 0 );
		
		$filter_ffname = 'filter_'.$filter->id;
		$filter_ffid   = $formName.'_'.$filter->id.'_val';
		
		// Make sure the current filtering values match the field filter configuration to single or multi-value
		if ( in_array($display_filter_as, array(2,3,5,6)) ) {
			if (!is_array($value)) $value = strlen($value) ? array($value) : array();
		} else {
			if (is_array($value)) $value = @ $value[0];
		}
		//print_r($value);
		
		// Alter search property name (indexed fields only), remove underscore _ at start & end of it
		if ($indexed_elements && $search_prop) {
			preg_match("/^_([a-zA-Z_0-9]+)_$/", $search_prop, $prop_matches);
			$search_prop = @ $prop_matches[1];
		}
		
		// Get filtering values, this can be cached if not filtering according to current category filters
		if ( in_array($display_filter_as, array(0,2,4,5,6)) )
		{
			$view_join = '';
			$view_where = '';
			$filters_where = array();
			
			// *** Limiting of displayed filter values according to current category filtering, but show all field values if filter is active
			if ( $isCategoryView ) {
				// category view, use parameter to decide if limitting filter values
				global $fc_catviev;
				if ( $faceted_filter ) {
					$view_join = @ $fc_catviev['join_clauses'];
					$view_where = $fc_catviev['where_conf_only'];
					$filters_where = $fc_catviev['filters_where'];
					if ($fc_catviev['alpha_where']) $filters_where['alpha'] = $fc_catviev['alpha_where'];  // we use count bellow ... so add it only if it is non-empty
				}
			} else if ( $isSearchView ) {
				// search view, use parameter to decide if limitting filter values
				global $fc_searchview;
				if ( $faceted_filter ) {
					$view_join = $fc_searchview['join_clauses'];
					$view_where = $fc_searchview['where_conf_only'];
					$filters_where = $fc_searchview['filters_where'];
				}
			}
			$createFilterValues = !$isSearchView ? 'createFilterValues' : 'createFilterValuesSearch';
			
			// Get filter values considering PAGE configuration (regardless of ACTIVE filters)
			if (  $apply_cache ) {
				$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expiration to default e.g. one hour
				$results_page = $itemcache->call(array('FlexicontentFields', $createFilterValues), $filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
			} else {
				if (!$isSearchView)
					$results_page = FlexicontentFields::createFilterValues($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
				else
					$results_page = FlexicontentFields::createFilterValuesSearch($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop);
			}
			
			// Get filter values considering ACTIVE filters, but only if there is at least ONE filter active
			if ( $faceted_filter==2 && count($filters_where) ) {
				/*if ( $apply_cache ) { // Can produces big amounts of cached data, that will be rarely used ... commented out
					$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expiration to default e.g. one hour
					$results_active = $itemcache->call(array('FlexicontentFields', $createFilterValues), $filter, $view_where, $filters_where, $indexed_elements, $search_prop);
				} else */
				if (!$isSearchView)
					$results_active = FlexicontentFields::createFilterValues($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop);
				else
					$results_active = FlexicontentFields::createFilterValuesSearch($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop);
			}
			
			// Decide which results to show those based: (a) on active filters or (b) on page configuration
			// This depends if hiding disabled values (for FACETED: 2) AND if active filters exist
			$use_active_vals = $hide_disabled_values && isset($results_active);
			$results_shown = $use_active_vals ? $results_active : $results_page;
			$update_found = !$use_active_vals && isset($results_active);
			
			// Set usage counters
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
				if ($faceted_filter==2 && $show_matches && $results[$i]->found)
					$results[$i]->text .= ' ('.$results[$i]->found.')';
			}
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
				$first_option_txt = $label_filter==2  ?  $filter->label  :  JText::_('FLEXI_ANY');
				$options[] = JHTML::_('select.option', '', '- '.$first_option_txt.' -');
			}
			
			// Make use of select2 lib
			flexicontent_html::loadFramework('select2');
			$classes  = " use_select2_lib";
			$extra_param = '';
			
			// MULTI-select: special label and prompts
			if ($display_filter_as == 6) {
				$classes .= ' fc_label_internal fc_prompt_internal';
				// Add field's LABEL internally or click to select PROMPT (via js)
				$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
				// Add type to filter PROMPT (via js)
				$extra_param  = ' fc_label_text="'.flexicontent_html::escapeJsText($_inner_lb,'s').'"';
				$extra_param .= ' fc_prompt_text="'.flexicontent_html::escapeJsText(JText::_('FLEXI_TYPE_TO_FILTER'),'s').'"';
			}
			
			// Create HTML tag attributes
			$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
			$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="20" ' : '';
			//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';
			
			foreach($results as $result) {
				if ( !strlen($result->value) ) continue;
				$options[] = JHTML::_('select.option', $result->value, JText::_($result->text), 'value', 'text', $disabled = ($faceted_filter==2 && !$result->found));
			}
			if ($display_filter_as==0 || $display_filter_as==6) {
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', $value, $filter_ffid);
			} else {
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[1]', $attribs_str, 'value', 'text', @ $value[1], $filter_ffid.'1');
				$filter->html	.= '<span class="fc_range"></span>';
				$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[2]', $attribs_str, 'value', 'text', @ $value[2], $filter_ffid.'2');
			}
			break;
		case 1: case 3:  // (TODO: autocomplete) ... 1: Text input, 3: Dual text input (value range), both of these can be JS date calendars
			$_inner_lb = $label_filter==2 ? $filter->label : JText::_($isdate ? 'FLEXI_CLICK_CALENDAR' : 'FLEXI_TYPE_TO_LIST');
			$_inner_lb = flexicontent_html::escapeJsText($_inner_lb,'s');
			$attribs_str = ' class="fc_field_filter fc_label_internal" fc_label_text="'.$_inner_lb.'"';
			$attribs_arr = array('class'=>'fc_field_filter fc_label_internal', 'fc_label_text' => $_inner_lb );
			
			if ($display_filter_as==1) {
				if ($isdate) {
					$filter->html	.= FlexicontentFields::createCalendarField($value, $allowtime=0, $filter_ffname, $filter_ffid, $attribs_arr);
				} else
					$filter->html	.= '<input id="'.$filter_ffid.'" name="'.$filter_ffname.'" '.$attribs_str.' type="text" size="'.$size.'" value="'.@ $value.'" />';
			} else {
				if ($isdate) {
					$filter->html	.= '<span class="fc_filter_element">';
					$filter->html	.= FlexicontentFields::createCalendarField(@ $value[1], $allowtime=0, $filter_ffname.'[1]', $filter_ffid.'1', $attribs_arr);
					$filter->html	.= '</span>';
					$filter->html	.= '<span class="fc_range"></span>';
					$filter->html	.= '<span class="fc_filter_element">';
					$filter->html	.= FlexicontentFields::createCalendarField(@ $value[2], $allowtime=0, $filter_ffname.'[2]', $filter_ffid.'2', $attribs_arr);
					$filter->html	.= '</span>';
				} else {
					$size = (int)($size / 2);
					$filter->html	.= '<input name="'.$filter_ffname.'[1]" '.$attribs_str.' type="text" size="'.$size.'" value="'.@ $value[1].'" /> - ';
					$filter->html	.= '<span class="fc_range"></span>';
					$filter->html	.= '<input name="'.$filter_ffname.'[2]" '.$attribs_str.' type="text" size="'.$size.'" value="'.@ $value[2].'" />'."\n";
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
			$filter->html .= '<span class="fc_checkradio_group_wrapper fc_add_scroller'.($add_lf ? ' fc_list_filter_wrapper':'').'">';
			$filter->html .= '<ul class="fc_field_filter fc_checkradio_group'.($add_lf ? ' fc_list_filter':'').'">';
			$filter->html .= '<li class="fc_checkradio_option fc_checkradio_special'.$checked_class_li.'" style="'.$value_style.'">';
			if ($display_filter_as==4) {
				$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClassGrp(this, \'fc_highlight\', 1);" ';
				$filter->html .= '  id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'" ';
				$filter->html .= '  value="" '.$checked_attr.' class="fc_checkradio" />';
			} else {
				$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClass(this, \'fc_highlight\', 1);" ';
				$filter->html .= '  id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'['.$i.']" ';
				$filter->html .= '  value="" '.$checked_attr.' class="fc_checkradio" />';
			}
			$filter->html .= '<label class="'.$checked_class.'" for="'.$filter_ffid.$i.'">'.
				($label_filter==2  ?  $filter->label.': ' : '').
				'- '.JText::_('FLEXI_ANY').' -';
			$filter->html .= '</label></li>';
			$i++;
			foreach($results as $result) {
				if ( !strlen($result->value) ) continue;
				$checked = ($display_filter_as==5) ? in_array($result->value, $value) : $result->value==$value;
				$checked_attr = $checked ? ' checked=checked ' : '';
				$disable_attr = $faceted_filter==2 && !$result->found ? ' disabled=disabled ' : '';
				$checked_class = $checked ? 'fc_highlight' : '';
				$checked_class .= $faceted_filter==2 && !$result->found ? ' fcdisabled ' : '';
				$checked_class_li = $checked ? ' fc_checkradio_checked' : '';
				$filter->html .= '<li class="fc_checkradio_option'.$checked_class_li.'" style="'.$value_style.'">';
				if ($display_filter_as==4) {
					$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClassGrp(this, \'fc_highlight\');" ';
					$filter->html .= '  id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'" ';
					$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
				} else {
					$filter->html .= ' <input href="javascript:;" onchange="fc_toggleClass(this, \'fc_highlight\');" ';
					$filter->html .= '  id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'['.$i.']" ';
					$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
				}
				$filter->html .= '<label class="'.$checked_class.'" for="'.$filter_ffid.$i.'">';
				$filter->html .= JText::_($result->text);
				$filter->html .= '</label>';
				$filter->html .= '</li>';
				$i++;
			}
			$filter->html .= '</ul>';
			$filter->html .= '</span>';
			break;
		}
		
		if ( $print_logging_info ) {
			$current_filter_creation = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$flt_active_count = isset($filters_where) ? count($filters_where) : 0;
			$faceted_str = array(0=>'non-FACETED', 1=>'FACETED MODE: current view &nbsp; (cacheable) &nbsp; ', 2=>'FACETED MODE: current filters:'." (".$flt_active_count.' active) ');
			
			$fc_run_times['create_filter'][$filter->name] = $current_filter_creation;
			if ( isset($fc_run_times['_create_filter_init']) ) {
				$fc_run_times['create_filter'][$filter->name] -= $fc_run_times['_create_filter_init'];
				$fc_run_times['create_filter_init'] = $fc_run_times['_create_filter_init'];
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
			if ( !$value ) {
				$date = '';
			} else if (!$date_allowtime || !$time) {
				$date = JHTML::_('date',  $date, JText::_( FLEXI_J16GE ? 'Y-m-d' : '%Y-%m-%d' ), $timezone);
			} else {
				$date = JHTML::_('date',  $value, JText::_( FLEXI_J16GE ? 'Y-m-d H:i' : '%Y-%m-%d %H:%M' ), $timezone);
			}
		} catch ( Exception $e ) {
			if (!$skip_on_invalid) return '';
			else $date = '';
		}
		
		// Create JS calendar
		$date_formats_map = array('0'=>'%Y-%m-%d', '1'=>'%Y-%m-%d %H:%M', '2'=>'%Y-%m-%d 00:00');
		$date_format = $date_formats_map[$date_allowtime];
		$calendar = JHTML::_('calendar', $date, $fieldname, $elementid, $date_format, $attribs);
		return $calendar;
	}
	
	
	// Method to create filter values for a field filter to be used in content lists views (category, etc)
	static function createFilterValues($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3)) ;
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $filter_as_range || !$faceted_filter ?  0  :  $show_matching_items;
		
		if ($faceted_filter || !$indexed_elements) {
			$_results = FlexicontentFields::getFilterValues($filter, $view_join, $view_where, $filters_where);
		}
		
		// Support of value-indexed fields
		if ( !$faceted_filter && $indexed_elements) {
			$results = & $indexed_elements;
		} else 
		if ( $indexed_elements ) {
			
			// Limit indexed element according to DB results found
			$results = array_intersect_key($indexed_elements, $_results);
			if ($faceted_filter==2 && $show_matches) foreach ($results as $i => $result) {
				$result->found = $_results[$i]->found;
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
		
		foreach ($results as $i => $result) {
			$results[$i]->text = JText::_($result->text);
		}
		
		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby) ) uksort($results, 'strcasecmp');
		
		return $results;
	}
	
	
	// Method to create filter values for a field filter to be used in search view
	static function createFilterValuesSearch($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3)) ;
		
		//$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		//$show_matches = $filter_as_range || !$faceted_filter ?  0  :  $show_matching_items;
		
		$filter->filter_isindexed = (boolean) $indexed_elements; 
		$_results = FlexicontentFields::getFilterValuesSearch($filter, $view_join, $view_where, $filters_where);
		$results = & $_results;
		
		foreach ($results as $i => $result) {
			$results[$i]->text = JText::_($result->text);
		}
		
		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby) ) uksort($results, 'strcasecmp');
		
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
		$valuesfrom   = @$filter->filter_valuesfrom   ? $filter->filter_valuesfrom   : ($filter->iscore ? ' FROM #__content AS i' : ' FROM #__flexicontent_fields_item_relations AS fi ');
		$valuesjoin   = @$filter->filter_valuesjoin   ? $filter->filter_valuesjoin   : ' ';
		$valueswhere  = @$filter->filter_valueswhere  ? $filter->filter_valueswhere  : ' AND fi.field_id ='.$filter->id;
		// full SQL clauses
		$groupby = @$filter->filter_groupby ? $filter->filter_groupby : ' GROUP BY value ';
		$having  = @$filter->filter_having  ? $filter->filter_having  : '';
		$orderby = @$filter->filter_orderby ? $filter->filter_orderby : '';
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3)) ;
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $filter_as_range || !$faceted_filter ?  0  :  $show_matching_items;
		
		static $item_ids_list = null;
		
		// FACETED filter
		if ( $faceted_filter ) {
			
			// Find items belonging to current view
			if ($item_ids_list === null && empty($view_where) )  $item_ids_list = '';
			
			if ($item_ids_list === null) {
				$sub_query = 'SELECT DISTINCT i.id '."\n"
					. ' FROM #__content AS i'."\n"
					. $view_join."\n"
					. $view_where."\n"
					;
				$db->setQuery($sub_query);
				
				global $fc_run_times, $fc_jprof;
				//$fc_jprof->mark('BEFORE FACETED INIT: FLEXIcontent component');
				$start_microtime = microtime(true);
				
				$item_ids = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				$item_ids_list = implode(',', $item_ids);
				unset($item_ids);
				
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//$fc_jprof->mark('AFTER FACETED INIT: FLEXIcontent component');
			}
			
			$item_id_col = $filter->iscore ? 'i.id' : 'fi.item_id';
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT '.$item_id_col.') as found ' : '')."\n"
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. (!$item_ids_list ? '' : ' AND '.$item_id_col.' IN('.$item_ids_list.')'."\n")
				.  ($filter->iscore ? $filter_where_curr : str_replace('i.id', 'fi.item_id', $filter_where_curr))."\n"
				. $valueswhere."\n"
				. $groupby."\n"
				. $having."\n"
				. $orderby
				;
		}
		
		// NON-FACETED filter
		else {
			$query = 'SELECT DISTINCT '. $valuesselect ."\n"
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. $valueswhere."\n"
				//. $groupby."\n"
				. $having."\n"
				. $orderby
				;
		}
		//if ( in_array($filter->field_type, array('tags','created','modified')) ) echo nl2br($query);
		
		$db->setQuery($query);
		$results = $db->loadObjectList('value');
		if ($db->getErrorNum()) {
			$filter->html	 = "Filter for : {$filter->label} cannot be displayed, error during db query :<br />" .$query ."<br/>" .__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
			return array();
		}
		
		return $results;
	}
	
	
	// Retrieves all available filter values of the given field according to the given VIEW'S FILTERING (Search view)
	static function getFilterValuesSearch(&$filter, &$view_join, &$view_where, &$filters_where)
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$db = JFactory::getDBO();
		
		$filter_where_curr = '';
		foreach ($filters_where as $filter_id => $filter_where) {
			if ($filter_id != $filter->id)  $filter_where_curr .= ' ' . $filter_where;
		}
		
		$valuesselect = @$filter->filter_isindexed ? ' ai.value_id as value, ai.search_index as text ' : ' ai.search_index as value, ai.search_index as text';
		
		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3)) ;
		
		$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		$show_matches = $filter_as_range || !$faceted_filter ?  0  :  $show_matching_items;
		
		static $item_ids_list = null;
		if ( $faceted_filter )
		{
			if ($item_ids_list === null && empty($view_where) ) {
				$item_ids_list = '';
			}
			if ($item_ids_list === null) {
				$sub_query = 'SELECT DISTINCT ai.item_id '."\n"
					.' FROM #__flexicontent_advsearch_index AS ai'."\n"
					.' JOIN #__content i ON ai.item_id = i.id'."\n"
					. $view_join."\n"
					.' WHERE '."\n"
					. $view_where."\n"
					;
				$db->setQuery($sub_query);
				
				global $fc_run_times, $fc_jprof;
				$start_microtime = microtime(true);
				//$fc_jprof->mark('BEFORE FACETED INIT: FLEXIcontent component');
				
				$item_ids = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				$item_ids_list = implode(',', $item_ids);
				
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//$fc_jprof->mark('AFTER FACETED INIT: FLEXIcontent component');
			}
			
			// Get ALL records that have such values for the given field
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT ai.item_id) as found ' : '')."\n"
				. ' FROM #__flexicontent_advsearch_index AS ai'."\n"
				. ' WHERE 1 '."\n"
				. (!$item_ids_list ? '' : ' AND ai.item_id IN('.$item_ids_list.')'."\n")
				. ' AND ai.field_id='.(int)$filter->id."\n"
				.  str_replace('i.id', 'ai.item_id', $filter_where_curr)."\n"
				. ' GROUP BY ai.search_index, ai.value_id'."\n"
				;
		} else {
			
			// Get ALL records that have such values for the given field
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT i.id) as found ' : '')."\n"
				.' FROM #__flexicontent_advsearch_index AS ai'."\n"
				.' JOIN #__content i ON ai.item_id = i.id'."\n"
				. $view_join."\n"
				.' WHERE '."\n"
				. ($view_where ? $view_where.' AND ' : '')."\n"
				.' ai.field_id='.(int)$filter->id."\n"
				. $filter_where_curr
				.' GROUP BY ai.search_index, ai.value_id'."\n"
				;
				
			/*$query = 'SELECT DISTINCT '. $valuesselect ."\n"
				.' FROM #__flexicontent_advsearch_index AS ai'."\n"
				.' WHERE ai.field_id='.(int)$filter->id."\n"
				//.' GROUP BY ai.search_index, ai.value_id'."\n"
				;*/
		}
		
		$db->setQuery($query);
		$results = $db->loadObjectList('text');
		//echo "<br/>". count($results) ."<br/>";
		//echo nl2br($query) ."<br/><br/>";
		
		if ($db->getErrorNum()) {
			$filter->html	 = "Filter for : {$filter->label} cannot be displayed, error during db query :<br />" .$query ."<br/>" .__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
			return array();
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
	function setFilterValues( &$cparams, $mfilter_name='persistent_filters', $is_persistent=1, $set_method="httpReq" )
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
	 * Method to creat the HTML of filters
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function renderFilters( &$params, &$filters, $form_name )
	{
		// Make the filter compatible with Joomla standard cache
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$display_label_filter_override = (int) $params->get('show_filter_labels', 0);
		foreach ($filters as $filter_name => $filter)
		{
			$filtervalue = JRequest::getVar('filter_'.$filter->id, '', 'default');
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
			$lists['filter_' . $filter->id] = $filtervalue;
		}
	}	

	
	
	// **********************************************************************************************************
	// Helper methods to create GENERIC ITEM LISTs which also includes RENDERED display of fields and custom HTML
	// **********************************************************************************************************
	
	// Helper method to perform HTML replacements on given list of item ids (with optional catids too), the items list is either given
	// as parameter or the list is created via the items that have as value the id of 'parentitem' for field with id 'reverse_field'
	static function getItemsList(&$params, &$_itemids_catids=null, $isform=0, $reverse_field=0, &$parentfield, &$parentitem, &$return_item_list=false)
	{
		// Execute query to get item list data 
		$db = JFactory::getDBO();
		$query = FlexicontentFields::createItemsListSQL($params, $_itemids_catids, $isform, $reverse_field, $parentfield, $parentitem);
		$db->setQuery($query);
		$item_list = & $db->loadObjectList('id');
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		// Item list must be returned too ...
		if ($return_item_list)  $return_item_list = & $item_list;
		
		// No published related items or SQL query failed, return
		if ( !$item_list ) return '';
		
		if ($_itemids_catids) foreach($item_list as $_item)   // if it exists ... add prefered catid to items list data
			$_item->rel_catid = @ $_itemids_catids[$_item->id]->catid;
		return FlexicontentFields::createItemsListHTML($params, $item_list, $isform, $parentfield, $parentitem);
	}
	
	
	// Helper method to create SQL query for retrieving items list data
	static function createItemsListSQL(&$params, &$_itemids_catids=null, $isform=0, $reverse_field=0, &$parentfield, &$parentitem)
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
		
		// item IDs via reversing a relation field
		if ($reverse_field) {
			$item_join  = ' JOIN #__flexicontent_fields_item_relations AS fi_rel'
				.'  ON i.id=fi_rel.item_id AND fi_rel.field_id=' .$reverse_field .' AND CAST(fi_rel.value AS UNSIGNED)=' .$parentitem->id;
		}
		// item IDs via a given list (relation field and ... maybe other cases too)
		else {
			$item_where = ' AND i.id IN ('. implode(",", array_keys($_itemids_catids)) .')';
		}
		
		// Get orderby SQL CLAUSE ('ordering' is passed by reference but no frontend user override is used (we give empty 'request_var')
		$order = $params->get( 'orderby'.$sfx, 'alpha' );
		$orderby = flexicontent_db::buildItemOrderBy($params, $order, $request_var='', $config_param='', $item_tbl_alias = 'i', $relcat_tbl_alias = 'rel', '', '', $sfx);
		
		// Create JOIN for ordering items by a custom field
		$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid'.$sfx, 0);
		if ($orderbycustomfieldid) {
			$orderby_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
		}
		
		// Create JOIN for ordering items by a most commented
		else if ($order=='commented') {
			$orderby_col  = ', count(com.object_id) AS comments_total';
			$orderby_join = ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id';
		}
		
		// Create JOIN for ordering items by a most rated
		else if ($order=='rated') {
			$orderby_col  = ', (cr.rating_sum / cr.rating_count) * 20 AS votes';
			$orderby_join = ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id';
		}
		
		// Because query includes a limited number of related field it should be fast
		$query = 'SELECT i.*, ext.type_id,'
			.' GROUP_CONCAT(c.id SEPARATOR  ",") AS catidlist, '
			.' GROUP_CONCAT(c.alias SEPARATOR  ",") AS  cataliaslist '
			. @ $orderby_col
			.' FROM #__content AS i '
			.' LEFT JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '
			. @ $item_join
			. @ $orderby_join
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '
			.' LEFT JOIN #__categories AS c ON c.id=rel.catid '
			.' LEFT JOIN #__users AS u ON u.id = i.created_by'
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
	static function createItemsListHTML(&$params, &$item_list, $isform=0, &$parentfield, &$parentitem)
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
			$field_name_col = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
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
		
		$display = array();
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			// a. Replace some custom made strings
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($result->slug, $result->categoryslug));
			$item_title_escaped = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8');
			$item_tooltip = ' class="hasTip relateditem" title="'. JText::_('FLEXI_READ_MORE_ABOUT').'::'.$item_title_escaped.'" ';
			
			$display_text = $displayway ? $result->title : $result->id;
			$display_text = !$addlink ? $display_text : '<a href="'.$item_url.'"'.($addtooltip ? $item_tooltip : '').' >' .$display_text. '</a>';
			
			$curr_relitem_html = $relitem_html;
			$curr_relitem_html = str_replace('__item_url__', $item_url, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_title_escaped__', $item_title_escaped, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_tooltip__', $item_tooltip, $curr_relitem_html);
			$curr_relitem_html = str_replace('__display_text__', $display_text, $curr_relitem_html);
			
			// b. Replace item properties, e.g. {item->id}, (item->title}, etc
			FlexicontentFields::doQueryReplacements($curr_relitem_html, $null_field=null, $result);
			
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
		
		foreach ($fc_run_times['render_field'] as $field_type => $field_msecs)
		{
			// Total rendering time of fields
			$fields_render_total += $field_msecs;
			
			// Create Log a message about current field rendering time
			$fld_msg = $field_type." : ". sprintf("%.3f s",$field_msecs/1000000);
			
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
		foreach ($fc_run_times['create_filter'] as $field_type => $filter_msecs)
		{
			// Total creation time of filters
			$filters_creation_total += $filter_msecs;
			
			// Create Log a message about current filter creation time
			$fld_msg = $field_type." ... ".$fc_run_times['create_filter_type'][$field_type].": ". sprintf("%.3f s",$filter_msecs/1000000);
			
			$filters_creation[] = $fld_msg;
		}
		return $filters_creation;
	}
	
}