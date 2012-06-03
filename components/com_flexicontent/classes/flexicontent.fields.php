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

// This is needed by some content plugins
require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

class FlexicontentFields
{
	/**
	 * Method to bind fields to an items object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function & getFields(&$items, $view = FLEXI_ITEMVIEW, $params = null, $aid = 0)
	{
		if (!$items) return $items;
		if (!is_array($items)) {
			$rows[] = $items;
			$items	= $rows;
		}

		$user 		= &JFactory::getUser();

		$mainframe	= &JFactory::getApplication();
		$cparams	=& $mainframe->getParams('com_flexicontent');
		
		$itemcache 	=& JFactory::getCache('com_flexicontent_items');
		$itemcache->setCaching(FLEXI_CACHE); 		//force cache
		$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	//set expiry to one hour
		if (FLEXI_GC) $itemcache->gc(); 			//auto-clean expired item cache

		// @TODO : move to the constructor
		// This is optimized regarding the use of SINGLE QUERY to retrieve the core item data
		$taglist			= FlexicontentFields::_getTags($items);
		$catlist			= FlexicontentFields::_getCategories($items);
		$vars['favourites']	= FlexicontentFields::_getFavourites($items);
		$vars['favoured']	= FlexicontentFields::_getFavoured($items);
		$vars['modifiers']	= FlexicontentFields::_getModifiers($items);
		$vars['authors']	= FlexicontentFields::_getAuthors($items);
		$vars['typenames']	= FlexicontentFields::_getTypenames($items);
		$vars['votes']		= FlexicontentFields::_getVotes($items);
		
		// TODO create single query, to optimize category view
		for ($i=0; $i < sizeof($items); $i++)
		{
			$var				= array();
			$var['favourites']	= isset($vars['favourites'][$items[$i]->id])	? $vars['favourites'][$items[$i]->id]->favs	: 0;
			$var['favoured']	= isset($vars['favoured'][$items[$i]->id]) 		? $vars['favoured'][$items[$i]->id]->fav 	: 0;
			$var['authors']		= isset($vars['authors'][$items[$i]->id]) 		? $vars['authors'][$items[$i]->id] 			: '';
			$var['modifiers']	= isset($vars['modifiers'][$items[$i]->id]) 	? $vars['modifiers'][$items[$i]->id] 		: '';
			$var['typenames']	= isset($vars['typenames'][$items[$i]->id]) 	? $vars['typenames'][$items[$i]->id] 		: '';
			$var['votes']		= isset($vars['votes'][$items[$i]->id]) 		? $vars['votes'][$items[$i]->id] 			: '';
			
			// Assign precalculated tags and cats lists
			$item_id = $items[$i]->id;
			$items[$i]->cats = isset($catlist[$item_id]) ? $catlist[$item_id] : array();
			$items[$i]->tags = isset($taglist[$item_id]) ? $taglist[$item_id] : array();
			$items[$i]->categories = & $items[$i]->cats;
			
			// Apply the fields cache to public or just registered users
			$apply_cache = true;
			if (FLEXI_ACCESS) {
				$apply_cache = $user->gmid == '0' || $user->gmid == '0,1';
			} else if (FLEXI_J16GE) {
				$apply_cache = max($user->getAuthorisedGroups()) <= 1;
			} else {
				$apply_cache = $user->gid <= 18;
			}
			
			if ( $apply_cache && FLEXI_CACHE ) {
				$hits = $items[$i]->hits;
				$items[$i]->hits = 0;
				$items[$i] = $itemcache->call(array('FlexicontentFields', 'getItemFields'), $items[$i], $var, $view, $aid);
				$items[$i]->hits = $hits;
			} else {
				$items[$i] = FlexicontentFields::getItemFields($items[$i], $var, $view, $aid);
			}

			// ***** SERIOUS PERFORMANCE ISSUE FIX -- ESPECIALLY IMPORTANT ON CATEGORY VIEW WITH A LOT OF ITEMS --
			$always_create_fields_display = $cparams->get('always_create_fields_display',0);
			$flexiview = JRequest::getVar('view', false);
			// 0: never, 1: always, 2: only in item view 
			if ($always_create_fields_display==1 || ($always_create_fields_display==2 && $flexiview==FLEXI_ITEMVIEW) ) {
				if ($items[$i]->fields)
				{
					foreach ($items[$i]->fields as $field)
					{
						$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
						$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display');
					}
				}
			}
		}
		
		$cparams->merge($params);  // merge components parameters into field parameters
		$items = FlexicontentFields::renderPositions($items, $view, $params);

		return $items;
	}

	/**
	 * Method to fetch the fields from an item object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function getItemFields($item, $var, $view=FLEXI_ITEMVIEW, $aid=0)
	{
		$db =& JFactory::getDBO();

		$mainframe = &JFactory::getApplication();
		if (!$item) return;
		if (!FLEXI_J16GE && $item->sectionid != FLEXI_SECTION) return;

		$user 		= &JFactory::getUser();
		$dispatcher = &JDispatcher::getInstance();

		$favourites	= $var['favourites'];
		$favoured	= $var['favoured'];
		$modifier	= $var['modifiers'];
		$author		= $var['authors'];
		$typename	= $var['typenames'];
		$vote		= $var['votes'];
		
		if (FLEXI_J16GE) {
			$aid_arr = ($aid && is_array($aid)) ? $aid : $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess 	= ' AND fi.access IN ('.$aid_list.')' ;
			$joinaccess = '';
		} else {
			$aid = $aid ? $aid : (int) $user->get('aid');
			$andaccess 	= FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. (int) $aid . ')' : ' AND fi.access <= '.$aid ;
			$joinaccess	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"' : '' ;
		}

		$query 	= 'SELECT fi.*'
				. ' FROM #__flexicontent_fields AS fi'
				. ' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
				. $joinaccess
				. ' WHERE ie.item_id = ' . (int)$item->id
				. ' AND fi.published = 1'
				. $andaccess
				. ' GROUP BY fi.id'
				. ' ORDER BY ftrel.ordering, fi.ordering, fi.name'
				;
		$db->setQuery($query);
		$item->fields	= $db->loadObjectList('name');
		$item->fields	= $item->fields	? $item->fields	: array();

		jimport('joomla.html.parameter');
		$item->parameters	= isset($item->parameters) ? $item->parameters : new JParameter( $item->attribs );
		$item->params		= $item->parameters;
		$item->text			= $item->introtext . chr(13).chr(13) . $item->fulltext;
		$item->modified		= ($item->modified != $db->getNulldate()) ? $item->modified : $item->created;
		$item->creator 		= @$author->alias ? $author->alias : (@$author->name 		? $author->name 	: '') ;
		$item->author 		= $item->creator ;
		$item->cmail 		= @$author->email 		? $author->email 	: '' ;
		$item->cuname 		= @$author->username 	? $author->username 	: '' ;
		$item->modifier		= @$modifier->name 		? $modifier->name 	: $item->creator;
		$item->mmail		= @$modifier->email 	? $modifier->email 	: $item->cmail;
		$item->muname		= @$modifier->muname 	? $modifier->muname : $item->cuname;
		$item->favs			= $favourites;
		$item->fav			= $favoured;
		$item->typename		= @$typename->name 		? $typename->name 	: JText::_('Article');
		$item->vote			= @$vote 				? $vote 			: '';
		
		if ($item->fields) {
			// IMPORTANT the items model and possibly other will set item PROPERTY version_id to indicate loading an item version,
			// It is not the responisibility of this CODE to try to detect previewing of an item version, it is better left to the model
			$item->fieldvalues = FlexicontentFields::_getFieldsvalues($item->id, $item->fields, !empty($item->version_id) ? $item->version_id : 0);
		}
		
		return $item;
	}

	/**
	 * Method to render (display method) a field on demand and return the display
	 * 
	 * @access public
	 * @return object
	 * @since 1.5.5
	 */
	function getFieldDisplay(&$item, $fieldname, $values=null, $method='display', $view = FLEXI_ITEMVIEW)
	{
	  if (!isset($item->fields)) {
	  	// This if will succeed once per item ... because getFields will retrieve all values
	  	// getFields() will not render the display of fields because we passed no params variable ...
			$items = array(&$item);
	  	FlexicontentFields::getFields($items, $view);
	  }

	  // Check if we have already created the display and return it
	  if ( isset($item->onDemandFields[$fieldname]->{$method}) ) {
	    return $item->onDemandFields[$fieldname]->{$method};
	  } else {
	    $item->onDemandFields[$fieldname] = new stdClass();
	  }
	  
	  // Find the field inside item
	  foreach ($item->fields as $field) {
	    if ($field->name==$fieldname) break;
	  }
	  
	  // Field not found, this is either due to no access or wrong name ...
	  $item->onDemandFields[$fieldname]->noaccess = false;
	  if ($field->name!=$fieldname) {
		  $item->onDemandFields[$fieldname]->label = '';
	  	$item->onDemandFields[$fieldname]->noaccess = true;
	  	$item->onDemandFields[$fieldname]->errormsg = 'field not assigned to this type of item or current user has no access';
	  	$item->onDemandFields[$fieldname]->{$method} = '';
	  	return $item->onDemandFields[$fieldname]->{$method};
	  }
	  
	  // Get field's values
	  if ($values===null) {
	  	$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
	  }
	  
	  // Set other field data like label and field itself !!!
	  $item->onDemandFields[$fieldname]->label = $field->label;
	  $item->onDemandFields[$fieldname]->field = & $field;
	  
	  // Render the (display) method of the field and return it
	  $field = FlexicontentFields::renderField($item, $field, $values, $method);
	  $item->onDemandFields[$fieldname]->{$method} = @$field->{$method};
	  return $item->onDemandFields[$fieldname]->{$method};
	}
	
	/**
	 * Method to render a field
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function renderField(&$item, &$field, &$values, $method='display')
	{
		// If $method (e.g. display method) is already created, then return the $field without recreating the $method
		if (isset($field->{$method})) return $field;
		
		// Append some values to the field object
		$field->item_id 	= (int)$item->id;
		$field->value 		= $values;
		
		// **********************************************************************************************
		// Create field parameters in an optimized way, and also apply Type Customization for CORE fields
		// **********************************************************************************************
		FlexicontentFields::loadFieldConfig($field, $item);
		
		
		// ******************************************************************
		// Create field html by calling the appropriate field plugin function
		// ******************************************************************
		if ($field->iscore == 1)  // CORE field
		{
			//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$field, $item, &$item->parameters, $item->tags, $item->cats, $item->favs, $item->fav, $item->vote ));
			FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, $item, &$item->parameters, $item->tags, $item->cats, $item->favs, $item->fav, $item->vote ) );
		}
		else                      // NON CORE field
		{
			//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
			FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array(&$field, $item, null, $method) );
		}
		
		
		// *****************************************
		// Trigger content plugins on the field text
		// *****************************************
		FlexicontentFields::triggerContentPlugins($field, $item, $method);
		
		return $field;		
	}
	
	
	/**
	 * Method to trigger content plugins on text of fields
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function triggerContentPlugins( &$field, &$item, $method ) 
	{
		// *************
		// Get variables
		// *************
		$dispatcher = &JDispatcher::getInstance();
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$flexiparams =& JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $flexiparams->get('print_logging_info');
		$flexiview	= JRequest::getVar('view');
		
		// Log content plugin and other performance information
		if ($print_logging_info) 	global $fc_content_plg_microtime;
		
		if ($field->parameters->get('trigger_onprepare_content', 0))
		{
			$field->text = isset($field->{$method}) ? $field->{$method} : '';
			$field->title = $item->title;
			// need now to reduce the scope through a parameter to avoid conflicts
			if (!$field->parameters->get('plugins')) {
				JPluginHelper::importPlugin('content');
			} else {
				if (FLEXI_J16GE) {
					$plg_arr = explode('|',$field->parameters->get('plugins'));
				} else if ( !is_array($field->parameters->get('plugins')) ) {
					$plg_arr = array($field->parameters->get('plugins'));
				} else {
					$plg_arr = $field->parameters->get('plugins');
				}
				foreach ($plg_arr as $plg) {
					JPluginHelper::importPlugin('content', $plg);
				}
			}
			$field->slug = $item->slug;
			$field->sectionid = !FLEXI_J16GE ? $item->sectionid : false;
			$field->catid = $item->catid;
			$field->catslug = @$item->categoryslug;
			$field->fieldid = $field->id;
			$field->id = $item->id;
			$field->state = $item->state;

			// Set the view and option to article and com_content
			if ($flexiview == FLEXI_ITEMVIEW) {
			  JRequest::setVar('view', 'article');
			  JRequest::setVar('option', 'com_content');
			}
			JRequest::setVar("isflexicontent", "yes");
			
			// Performance wise parameter 'trigger_plgs_incatview', recommended to be off: do not trigger content plugins on item's maintext while in category view
			if ($flexiview!='category' || $field->parameters->get('trigger_plgs_incatview', 1))
			{
				// Trigger content plugins
				if ($print_logging_info)  $start_microtime = microtime(true);
				if (!FLEXI_J16GE) {
					$results = $dispatcher->trigger('onPrepareContent', array (&$field, &$item->parameters, $limitstart));
				} else {
					$results = $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$field, &$item->parameters, $limitstart));
				}
				if ($print_logging_info)  $fc_content_plg_microtime += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			
			// Set the view and option back to items and com_flexicontent
			if ($flexiview == FLEXI_ITEMVIEW) {
			  JRequest::setVar('view', FLEXI_ITEMVIEW);
			  JRequest::setVar('option', 'com_flexicontent');
			}
			
			$field->id = $field->fieldid;
			$field->{$method} = $field->text;
		}
	}


	/**
	 * Method to get the fields in their positions
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function & renderPositions(&$items, $view = FLEXI_ITEMVIEW, $params = null)
	{
		if (!$items) return;
		if (!$params) return $items;
		
		if ($view == 'category')			$layout = 'clayout';
		if ($view == FLEXI_ITEMVIEW)	$layout = 'ilayout';
		
		// field's source code, can use this JRequest variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		JRequest::setVar("flexi_callview", $view);

		if ($view == 'category' || $view == FLEXI_ITEMVIEW) {
		  $fbypos = flexicontent_tmpl::getFieldsByPositions($params->get($layout, 'default'), $view);
		}	else { // $view == 'module', or other
			// Create a fake template position, for module fields
		  $fbypos[0] = new stdClass();
		  $fbypos[0]->fields = explode(',', $params->get('fields'));
		  $fbypos[0]->position = $view;
		}
		
		$always_create_fields_display = $params->get('always_create_fields_display',0);
		
		// *** RENDER fields on DEMAND, (if present in template positions)
		for ($i=0; $i < sizeof($items); $i++)
		{
			if ($always_create_fields_display != 3) { // value 3 means never create for any view (blog template incompatible)
				
			  // 'description' item field is implicitly used by category layout of some templates (blog), render it
			  if ($view == 'category') {
			    $field = $items[$i]->fields['text'];
			    $field 	= FlexicontentFields::renderField($items[$i], $field, $values=false, $method='display');
			  }
				// 'core' item fields are IMPLICITLY used by some item layout of some templates (blog), render them
				else if ($view == FLEXI_ITEMVIEW) {
					foreach ($items[$i]->fields as $field) {
						if ($field->iscore) {
							$field 	= FlexicontentFields::renderField($items[$i], $field, $values=false, $method='display');
						}
					}
				}
		  }
		  
		  // RENDER fields if they are present in a template position (or in a dummy template position ... e.g. when called by module)
			foreach ($fbypos as $pos) {
				foreach ($pos->fields as $f) {
					// Check that field with given name: $f exists, (this will handle deleted fields, that still exist in a template position)
					if (!isset($items[$i]->fields[$f])) {	
						continue;
					}
					$field = $items[$i]->fields[$f];
					
					// Set field values
					$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
					
					// Render field (if already rendered above, the function will return result immediately)
					$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display');
					
					// Set template position field data
					if (isset($field->display) && $field->display) {
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
	function _getFieldsvalues($item, $fields, $version=0)
	{
		$db =& JFactory::getDBO();
		$query = 'SELECT field_id, value'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id = ' . (int)$item
				.( $version ? ' AND version=' . (int)$version:'')
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
	 * Method to get the tags
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	function _getTags($items)
	{
		$db =& JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT DISTINCT t.name, i.itemid,'
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' LEFT JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
				. " WHERE i.itemid IN ('" . implode("','", $cids) . "')"
				. ' AND t.published = 1'
				. ' ORDER BY i.itemid, t.name'
				;
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
	function _getCategories($items)
	{
		$db =& JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT DISTINCT c.id, c.title, rel.itemid,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. " WHERE rel.itemid IN ('" . implode("','", $cids) . "')"
				;
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
	function _getFavourites($items)
	{
		$db =& JFactory::getDBO();
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
	function _getFavoured($items)
	{
		$db =& JFactory::getDBO();
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
	function _getModifiers($items)
	{
		// This is fix for versioned field of modifier in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->modified_by) && $items[0]->id==JRequest::getInt('id');
		
		$db =& JFactory::getDBO();
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
	function _getAuthors($items)
	{
		// This is fix for versioned field of creator in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by) && $items[0]->id==JRequest::getInt('id');
		
		$db =& JFactory::getDBO();
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
	function _getTypenames($items)
	{
		$db =& JFactory::getDBO();
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
	function _getVotes($items)
	{
		$db =& JFactory::getDBO();
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
	
	
	// 
	/**
	 * Method to create field parameters in an optimized way, and also apply Type Customization for CORE fields
	 *
	 * @access	_private
	 * @return	object
	 * @since	1.5
	 */
	function loadFieldConfig(&$field, &$item, $name='', $field_type='', $label='', $desc='', $iscore=1) {
		static $tparams = array();
		static $fdata = array();
		static $lang=null;
		
		//--. Discover Content Type Id, Name, Alias
		if ($item) {
			$type_id = ((is_object($item)) && ($item instanceof JForm))  ?  (int)$item->getValue('type_id')  :  @(int)$item->type_id;
			$typename = @$item->typename ? $item->typename : "__NOT_SET__";
			$typealias = @$item->typealias ? $item->typealias : "__NOT_SET__";
		} else {
			$type_id = 0; $typename = $typealias = "__NOT_SET__";
		}
		
		//--. Create basic field data if no field given
		if (!empty($name)) {
			$field->iscore = $iscore;
			$field->name = $name;
			$field->field_type = $field_type;
			$field->label = $label; 
			$field->description = $desc;
			$field->attribs = '';
		}
		
		//--. Get a 2 character language tag
		if (empty($lang)) {
			$lang = JRequest::getWord('lang', '' );
			if(empty($lang)){
				$langFactory= JFactory::getLanguage();
				$tagLang = $langFactory->getTag();
				$lang = substr($tagLang ,0,2);
			}
		}
		
		//--. Get Content Type parameters
		if (!isset($tparams[$typename]) && $type_id) {
			$query = 'SELECT t.attribs, t.name, t.alias'
					. ' FROM #__flexicontent_types AS t'
					. ' WHERE t.id = ' . $type_id
					;
			$db =& JFactory::getDBO();
			$db->setQuery($query);
			$typedata = $db->loadObject();
			if ($db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
			$typename = $typedata->name;  // workaround for J1.6+  form not having typename property
			$typealias = $typedata->alias;  // workaround for J1.6+  form not having typealias property
			$tparams[$typename] = new JParameter($typedata->attribs);
		}
		
		if (!isset($tparams[$typename])) {
			$tparams[$typename] = new JParameter("");
		}
		
		//--. BESIDES parameters we want to retrieve: ... Custom LABELs and DESCRIPTIONs
		if ($field->iscore && !isset($fdata[$typealias][$field->name])) {
			$fdata[$typealias][$field->name] = new stdClass();
			
			// -- SET a type specific label for the current field
			// a. Try field label to get for current language
			$field_label_type = $tparams[$typename]->get($field->field_type.'_label', '');
			$result = preg_match("/(\[$lang\])=([^[]+)/i", $field_label_type, $matches);
			if ($result) {
				$fdata[$typealias][$field->name]->label = $matches[2];
			} else if ($field_label_type) {
				// b. Try to get default for all languages
				$result = preg_match("/(\[default\])=([^[]+)/i", $field_label_type, $matches);
				if ($result) {
					$fdata[$typealias][$field->name]->label = $matches[2];
				} else {
					// c. Check that no languages specific string are defined
					$result = preg_match("/(\[??\])=([^[]+)/i", $field_label_type, $matches);
					if (!$result) {
						$fdata[$typealias][$field->name]->label = $field_label_type;
					}
				}
			} else {
				// Maintain field 's default label
			}
			
			// -- SET a type specific description for the current field
			// a. Try field description to get for current language
			$field_desc_type = $tparams[$typename]->get($field->field_type.'_desc', '');
			$result = preg_match("/(\[$lang\])=([^[]+)/i", $field_desc_type, $matches);
			if ($result) {
				$fdata[$typealias][$field->name]->description = $matches[2];
			} else if ($field_label_type) {
				// b. Try to get default for all languages
				$result = preg_match("/(\[default\])=([^[]+)/i", $field_desc_type, $matches);
				if ($result) {
					$fdata[$typealias][$field->name]->description = $matches[2];
				} else {
					// c. Check that no languages specific string are defined
					$result = preg_match("/(\[??\])=([^[]+)/i", $field_desc_type, $matches);
					if (!$result) {
						$fdata[$typealias][$field->name]->description = $field_desc_type;
					}
				}
			} else {
				// Maintain field 's default description
			}
			
			//--. Create type specific parameters for the CORE field that we will be used by all subsequent calls to retrieve parameters
			$fdata[$typealias][$field->name]->parameters = new JParameter($field->attribs);
			
			//--. In future we may automate this?, although this is faster
			if ($field->field_type == 'voting') {
				$voting_override_extra_votes = $tparams[$typename]->get('voting_override_extra_votes', '');
				$voting_extra_votes          = $tparams[$typename]->get('voting_extra_votes', '');
				$voting_main_label           = $tparams[$typename]->get('voting_main_label', '');
				
				// Override --voting field-- configuration regarding extra votes
				if ( $voting_override_extra_votes ) {
					$fdata[$typealias][$field->name]->parameters->set('extra_votes', $voting_extra_votes );
					// Set a Default main label if one was not given but extra votes exist
					$main_label = $voting_main_label ? $voting_main_label : JText::_('FLEXI_OVERALL');
				}
				if ( $voting_override_extra_votes ) {
					$fdata[$typealias][$field->name]->parameters->set('main_label', $voting_main_label );
				}
			}
			
			//--. Check if a custom field that customizes core field per Type
			$query = "SELECT attribs, published FROM #__flexicontent_fields WHERE name='".$field->name."_".$typealias."'";
			//echo $query;
			$db =& JFactory::getDBO();
			$db->setQuery($query);
			$data = $db->loadObject();
			//print_r($data);
			if ($db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			} else if (@$data->published) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(__FUNCTION__."(): Please unpublish plugin with name: ".$field->name."_".$typealias." it is used for customizing a core field",'error');
			}
			
			//--. Finally merge custom field parameters with the type specific parameters ones
			if ($data) {
				$ts_params = new JParameter($data->attribs);
				$fdata[$typealias][$field->name]->parameters->merge($ts_params);
			} else if ($field->field_type=='maintext') {
				$fdata[$typealias][$field->name]->parameters->set( 'use_html',  !$tparams[$typename]->get('hide_html', 0) ) ;
			}
			
		} else if ( !isset($fdata[$typealias][$field->name]) ) {
			$fdata[$typealias][$field->name]->parameters = new JParameter($field->attribs);
		}
		
		//--. Set custom label or maintain default
		if (isset($fdata[$typealias][$field->name]->label)) {
			$field->label = $fdata[$typealias][$field->name]->label;
		}
		//--. Set custom description or maintain default
		if (isset($fdata[$typealias][$field->name]->description)) {
			$field->description = $fdata[$typealias][$field->name]->description;
		} else if (!$field->description) {
			$field->description = '';
		}
		
		//--. Finally set field's parameters, but to clone ... or not to clone, better clone to allow customizations for individual item fields ...
		$field->parameters = clone($fdata[$typealias][$field->name]->parameters);
		
		return $field;
	}
	
}
?>
