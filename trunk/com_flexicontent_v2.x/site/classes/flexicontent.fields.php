<?php
/**
 * @version 1.5 stable $Id: flexicontent.fields.php 972 2011-11-23 04:24:23Z enjoyman@gmail.com $
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

class FlexicontentFields
{
	/**
	 * Method to bind fields to an items object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function getFields($items, $view = 'item', $params = null, $aid = 0)
	{
		if (!is_array($items)) {
			$rows[] = $items;
			$items	= $rows;
		}
		if (!$items) return $items;

		$user 		= &JFactory::getUser();
		$gid		= max ($user->getAuthorisedViewLevels());

		$mainframe	= &JFactory::getApplication();
		$cparams	=& $mainframe->getParams('com_flexicontent');
		
		$itemcache 	=& JFactory::getCache('com_flexicontent_items');
		$itemcache->setCaching(FLEXI_CACHE); 		//force cache
		$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	//set expiry to one hour
		if (FLEXI_GC) $itemcache->gc(); 			//auto-clean expired item cache

		// @TODO : move to the constructor
		$taglist			= FlexicontentFields::_getTags($items);
		$catlist			= FlexicontentFields::_getCategories($items);
		$vars['favourites']	= FlexicontentFields::_getFavourites($items);
		$vars['favoured']	= FlexicontentFields::_getFavoured($items);
		$vars['modifiers']	= FlexicontentFields::_getModifiers($items);
		$vars['authors']	= FlexicontentFields::_getAuthors($items);
		$vars['typenames']	= FlexicontentFields::_getTypenames($items);
		$vars['votes']		= FlexicontentFields::_getVotes($items);

		for ($i=0; $i < sizeof($items); $i++)
		{
			$var				= array();
			$var['favourites']	= isset($vars['favourites'][$items[$i]->id])	? $vars['favourites'][$items[$i]->id]->favs	: 0;
			$var['favoured']	= isset($vars['favoured'][$items[$i]->id]) 		? $vars['favoured'][$items[$i]->id]->fav 	: 0;
			$var['authors']		= isset($vars['authors'][$items[$i]->id]) 		? $vars['authors'][$items[$i]->id] 			: '';
			$var['modifiers']	= isset($vars['modifiers'][$items[$i]->id]) 	? $vars['modifiers'][$items[$i]->id] 		: '';
			$var['typenames']	= isset($vars['typenames'][$items[$i]->id]) 	? $vars['typenames'][$items[$i]->id] 		: '';
			$var['votes']		= isset($vars['votes'][$items[$i]->id]) 		? $vars['votes'][$items[$i]->id] 			: '';
			
			$items[$i]->cats	= array();
			foreach ($catlist as $cat) {
				if ($cat->itemid == $items[$i]->id) {
					$items[$i]->cats[] = $cat;
				}
			}
			$items[$i]->tags	= array();
			foreach ($taglist as $tag) {
				if ($tag->itemid == $items[$i]->id) {
					$items[$i]->tags[] = $tag;
				}
			}

			//if (FLEXI_ACCESS) {
			//	if ((($user->gmid == '0') || ($user->gmid == '0,1')) && FLEXI_CACHE) {
			//		$hits = $items[$i]->hits;
			//		$items[$i]->hits = 0;
			//		$items[$i] = $itemcache->call(array('FlexicontentFields', 'getItemFields'), $items[$i], $var, $view, $aid);
			//		$items[$i]->hits = $hits;
			//	} else {
			//		$items[$i] = FlexicontentFields::getItemFields($items[$i], $var, $view, $aid);
			//	}
			//} else {
				if (FLEXI_CACHE) {
					$hits = $items[$i]->hits;
					$items[$i]->hits = 0;
					$items[$i] = $itemcache->call(array('FlexicontentFields', 'getItemFields'), $items[$i], $var, $view, $aid);
					$items[$i]->hits = $hits;
				} else {
					$items[$i] = FlexicontentFields::getItemFields($items[$i], $var, $view, $aid);
				}
			//}

			// ***** SERIOUS PERFORMANCE ISSUE FIX -- ESPECIALLY IMPORTANT ON CATEGORY VIEW WITH A LOT OF ITEMS --
			$always_create_fields_display = $cparams->get('always_create_fields_display',0);
			$flexiview = JRequest::getVar('view', false);
			// 0: never, 1: always, 2: only in item view 
			if ($always_create_fields_display==1 || ($always_create_fields_display==2 && $flexiview=='items') ) {
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
	function getItemFields($item, $var, $aid)
	{
		$db =& JFactory::getDBO();

		$mainframe = &JFactory::getApplication();
		if (!$item) return;

		$user 		= &JFactory::getUser();
		$gid_a		= $user->getAuthorisedViewLevels();
		$gids		= "'".implode("','", $gid_a)."'";
		$dispatcher = &JDispatcher::getInstance();

		$favourites	= $var['favourites'];
		$favoured	= $var['favoured'];
		$modifier	= $var['modifiers'];
		$author		= $var['authors'];
		$typename	= $var['typenames'];
		$vote		= $var['votes'];

		//$andaccess 	= FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. (int) $gid . ')' : ' AND fi.access <= '.$gid ;
		$andaccess 	= ' AND fi.access IN ('.$gids.')' ;
		$joinaccess	= '' ;

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
			$item->fieldvalues = FlexicontentFields::_getFieldsvalues($item->id, $item->fields, isset($item->version_id)?$item->version_id:$item->version);
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
	function getFieldDisplay(&$item, $fieldname, $values=null, $method='display')
	{
	  if (!isset($item->fields)) {
	  	// This if will succeed once per item ... because getFields will retrieve all values
	  	// getFields() will not render the display of fields because we passed no params variable ...
	  	FlexicontentFields::getFields(array(&$item));
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
		  $item->onDemandFields[$fieldname]->label = 'not found for this type of item';
	  	$item->onDemandFields[$fieldname]->noaccess = true;
	  	$item->onDemandFields[$fieldname]->{$method} = "not found for this type of item or no access";
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
		// If $method (e.g. display method) is already created,
		// then return the $field without recreating the $method
		if (isset($field->{$method})) return $field;
		
		$dispatcher = &JDispatcher::getInstance();

		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');

		// we append some values to the field object
		$field->item_id 	= (int)$item->id;
		$field->value 		= $values;
		$field->parameters 	= new JParameter( $field->attribs );
		$params				= $item->parameters;
		$flexiview 			= JRequest::getVar('view');
		
		// and append html trought the field plugins
		if ($field->iscore == 1)
		{
			JPluginHelper::importPlugin('flexicontent_fields', $plugin_name='core');
			$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$field, $item, &$params, $item->tags, $item->cats, $item->favs, $item->fav, $item->vote ));

			if ($field->parameters->get('trigger_onprepare_content', 0)) {
				$field->text = isset($field->display) ? $field->display : '';
				$field->title = $item->title;
				// need now to reduce the scope through a parameter to avoid conflicts
				if (!$field->parameters->get('plugins')) {
					JPluginHelper::importPlugin('content');
				/*} else if (!is_array($field->parameters->get('plugins'))) {
					JPluginHelper::importPlugin('content', $field->parameters->get('plugins'));
				*/} else {
					foreach (explode('|',$field->parameters->get('plugins')) as $plg) {
						JPluginHelper::importPlugin('content', $plg);
					}
				}
				$field->slug = $item->slug;
				$field->catid = $item->catid;
				$field->catslug = @$item->categoryslug;
				$field->fieldid = $field->id;
				$field->id = $item->id;
				$field->state = $item->state;

				// Set the view and option to article and com_content
				if ($flexiview == 'item') {
				  JRequest::setVar('view', 'article');
				  JRequest::setVar('option', 'com_content');
				}
				JRequest::setVar("isflexicontent", "yes");
				
				// Performance wise parameter 'trigger_plgs_incatview', recommended to be off: do not trigger content plugins on item's maintext while in category view
				if (JRequest::getVar('view')!='category' || $field->field_type!='maintext' || $field->parameters->get('trigger_plgs_incatview', 0)) 
					$results = $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$field, &$params, $limitstart));
				
				// Set the view and option back to items and com_flexicontent
				if ($flexiview == 'item') {
				  JRequest::setVar('view', 'item');
				  JRequest::setVar('option', 'com_flexicontent');
				}
				
				$field->id = $field->fieldid;
				$field->display = $field->text;
			}
		}
		else
		{
			// NOT core field but just in case code is updated ... we check for core
			JPluginHelper::importPlugin('flexicontent_fields', ($field->iscore ? 'core' : $field->field_type) );
			$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
			
			if ($field->parameters->get('trigger_onprepare_content', 0)) {
				$field->text = isset($field->display) ? $field->display : '';
				$field->title = $item->title;
				// need now to reduce the scope through a parameter to avoid conflicts
				if (!$field->parameters->get('plugins')) {
					JPluginHelper::importPlugin('content');
				/*} else if (!is_array($field->parameters->get('plugins'))) {
					JPluginHelper::importPlugin('content', $field->parameters->get('plugins'));
				*/} else {
					foreach (explode('|',$field->parameters->get('plugins')) as $plg) {
						JPluginHelper::importPlugin('content', $plg);
					}
				}
				$field->slug = $item->slug;
				$field->catid = $item->catid;
				$field->catslug = $item->categoryslug;
				$field->fieldid = $field->id;
				$field->id = $item->id;
				$field->state = $item->state;

				// Set the view and option to article and com_content
				if ($flexiview == 'item') {
				  JRequest::setVar('view', 'article');
				  JRequest::setVar('option', 'com_content');
				}
				JRequest::setVar("isflexicontent", "yes");
				$results = $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$field, &$params, $limitstart));
				// Set the view and option back to items and com_flexicontent
				if ($flexiview == 'item') {
				  JRequest::setVar('view', 'item');
				  JRequest::setVar('option', 'com_flexicontent');
				}
				
				$field->id = $field->fieldid;
				$field->display = $field->text;
			}
		}
		
		return $field;		
	}


	/**
	 * Method to get the fields in their positions
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function renderPositions($items, $view = 'item', $params = null)
	{
		if (!$items) return;
		if (!$params) return $items;
		
		if ($view == 'category')	$layout = 'clayout';
		if ($view == 'item') 		$layout = 'ilayout';

		if ($view == 'category' || $view == 'item') {
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
				else if ($view == 'item') {
					foreach ($items[$i]->fields as $field) {
						if ($field->iscore) {
							$field 	= FlexicontentFields::renderField($items[$i], $field, $values=false, $method='display');
						}
					}
				}
		  }
		  
		  // render fields if they are present in a template position (or dummy position ...)
			foreach ($fbypos as $pos) {
				foreach ($pos->fields as $f) {
					if (!isset($items[$i]->fields[$f])) {
						// Field with name: $f does not exist for the type of current item, we simply skip it
						continue;
					}
					$field = $items[$i]->fields[$f];
					$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
					$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display');
					if (isset($field->display) && $field->display) {
						$items[$i]->positions[$pos->position]->{$f}->id 		= $field->id;
						$items[$i]->positions[$pos->position]->{$f}->name 		= $field->name;
						$items[$i]->positions[$pos->position]->{$f}->label 		= $field->parameters->get('display_label') ? $field->label : '';
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
		$preview = JRequest::getVar('preview');
		if($preview) {
			$lversion = $version?$version:JRequest::setVar('lversion');
		}
		$db =& JFactory::getDBO();
		$query = 'SELECT field_id, value'
				.($preview?' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id = ' . (int)$item
				.($preview?' AND version=' . (int)$lversion:'')
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

		return $tags;
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

		return $cats;
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
		$db =& JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT i.id, u.name, u.username, u.email FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON u.id = i.modified_by'
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
		$db =& JFactory::getDBO();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }		

		$query 	= 'SELECT i.id, u.name, i.created_by_alias as alias, u.username, u.email FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
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
		
		return $votes;
	}
}
?>
