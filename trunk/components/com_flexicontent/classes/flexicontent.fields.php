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
	 * Method to bind fields to an items object
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function &getFields(&$items, $view = FLEXI_ITEMVIEW, $params = null, $aid = 0, $use_tmpl = true)
	{
		if (!$items) return $items;
		if (!is_array($items)) {
			$items = array( $items );
		}
		
		$user 		= &JFactory::getUser();
		$mainframe	= &JFactory::getApplication();
		$cparams	=& $mainframe->getParams('com_flexicontent');
		
		if ( $print_logging_info = $cparams->get('print_logging_info') ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
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
		}
		if ( $print_logging_info ) {
			@$fc_run_times['field_value_retrieval'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		// CHECK if 'always_create_fields_display' enabled and create the display for all item's fields
		// *** This should be normally set to ZERO (never), to avoid a serious performance penalty !!!
		for ($i=0; $i < sizeof($items); $i++)
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
						$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display');
					}
				}
			}
		}
		
		// Render field positions
		if ($params) $items = FlexicontentFields::renderPositions($items, $view, $params, $use_tmpl);

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
	function &getFieldDisplay(&$item_data, $fieldname, $values=null, $method='display', $view = FLEXI_ITEMVIEW)
	{
		// 1. Convert to array of items if not an array already
		if ( !is_array($item_data)  ) 
			$items = array($item_data);
		else
			$items = &$item_data;
		
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
		  foreach ($item->fields as $field)  if ($field->name==$fieldname) break;
		  
		  // Check for not found field, and skip it, this is either due to no access or wrong name ...
	    $item->onDemandFields[$fieldname] = new stdClass();
		  if ($field->name!=$fieldname) {
			  $item->onDemandFields[$fieldname]->label = '';
		  	$item->onDemandFields[$fieldname]->noaccess = true;
		  	$item->onDemandFields[$fieldname]->errormsg = 'field not assigned to this type of item or current user has no access';
		  	$item->onDemandFields[$fieldname]->{$method} = '';
		  	continue;
		  }
		  
		  // Get field's values if they were custom values were not given
		  if ($values===null) {
		  	$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
		  }
		  
		  // Set other field data like label and field itself !!!
		  $item->onDemandFields[$fieldname]->label = $field->label;
		  $item->onDemandFields[$fieldname]->noaccess = false;
		  $item->onDemandFields[$fieldname]->field = & $field;
		  
		  
		  
		  // Render the (display) method of the field
		  if (!isset($field->{$method})) $field = FlexicontentFields::renderField($item, $field, $values, $method);
		  if (!isset($field->{$method})) $field->{$method} = '';
		  $item->onDemandFields[$fieldname]->{$method} = & $field->{$method};
		  $_method_html[$item->id] = & $field->{$method};
		}
		
		
		
		// Return field(s) HTML (in case of multiple items this will be an array indexable by item ids
		if ( !is_array($item_data) )  $_method_html = & $_method_html[$item_data->id];
  	return $_method_html;
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
		FlexicontentFields::triggerContentPlugins($field, $item, $method);
		
		return $field;		
	}
	
	
	/**
	 * Method to selectively trigger content plugins for the text of the specified field
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function triggerContentPlugins( &$field, &$item, $method ) 
	{
		$debug = false;
		
		// ***********************************************************************
		// We use a custom Dispatcher to allow selective Content Plugin triggering
		// ***********************************************************************
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'dispatcher.php');
		$dispatcher = & JDispatcher::getInstance();
		$fcdispatcher = & FCDispatcher::getInstance_FC($debug);
		
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$flexiparams =& JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $flexiparams->get('print_logging_info');
		$flexiview	= JRequest::getVar('view');
		
		// Log content plugin and other performance information
		if ($print_logging_info) 	global $fc_run_times;
		
		if ($field->parameters->get('trigger_onprepare_content', 0))
		{
			$field->text = isset($field->{$method}) ? $field->{$method} : '';
			$field->title = $item->title;
			
			if ($debug) echo "<br><br>Executing plugins for <b>".$field->name."</b>:<br>";
			
			// Make sure the necessary plugin are already loaded
			if (!$field->parameters->get('plugins')) {
				
				$plg_arr = null;
				JPluginHelper::importPlugin('content', $plugin = null, $autocreate = true, $dispatcher);
				
			} else {
				
				if (FLEXI_J16GE) {
					$plg_arr = explode('|',$field->parameters->get('plugins'));
				} else if ( !is_array($field->parameters->get('plugins')) ) {
					$plg_arr = array($field->parameters->get('plugins'));
				} else {
					$plg_arr = $field->parameters->get('plugins');
				}
				foreach ($plg_arr as $plg)
					JPluginHelper::importPlugin('content', $plg, $autocreate = true, $dispatcher);
				
			}
			
			// Suppress some plugins from triggering for compatibility reasons, e.g.
			// (a) jcomments, jom_comment_bot plugins, because we will get comments HTML manually inside the template files
			$suppress_arr = array('jcomments', 'jom_comment_bot');
			FLEXIUtilities::suppressPlugins($suppress_arr, 'suppress' );
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
				if ($print_logging_info)  $start_microtime = microtime(true);
				
				// Trigger content plugins on field's HTML display, as if they were a "joomla article"
				if (FLEXI_J16GE) $results = $fcdispatcher->trigger('onContentPrepare', array ('com_content.article', &$field, &$item->parameters, $limitstart), $plg_arr);
				else             $results = $fcdispatcher->trigger('onPrepareContent', array (&$field, &$item->parameters, $limitstart), false, $plg_arr);
				
				if ($print_logging_info)  $fc_run_times['content_plg'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			
			// Set the view and option back to items and com_flexicontent
			if ($flexiview == FLEXI_ITEMVIEW) {
			  JRequest::setVar('view', FLEXI_ITEMVIEW);
			  JRequest::setVar('option', 'com_flexicontent');
			}
			
			$field->id = $field->fieldid;
			$field->{$method} = $field->text;
			
			// Restore suppressed plugins
			FLEXIUtilities::suppressPlugins( $suppress_arr,'restore' );
		}
	}


	/**
	 * Method to get the fields in their positions
	 * 
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	function &renderPositions(&$items, $view = FLEXI_ITEMVIEW, $params = null, $use_tmpl = true)
	{
		if (!$items) return;
		if (!$params) return $items;
		
		if ($view == 'category')			$layout = 'clayout';
		if ($view == FLEXI_ITEMVIEW)	$layout = 'ilayout';
		
		// field's source code, can use this JRequest variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		JRequest::setVar("flexi_callview", $view);

		if ( $use_tmpl && ($view == 'category' || $view == FLEXI_ITEMVIEW) ) {
		  $fbypos = flexicontent_tmpl::getFieldsByPositions($params->get($layout, 'default'), $view);
		}	else { // $view == 'module', or other
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
					$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method);
					
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
		// This is fix for versioned field of creator in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->tags);
		
		$db =& JFactory::getDBO();

		if ($versioned_item) {
			if (!count($items[0]->tags)) return array();
			$tids = $items[0]->tags;
			$query 	= 'SELECT DISTINCT t.name, ' . $items[0]->id .' as itemid, '
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. " WHERE t.id IN ('" . implode("','", $tids) . "')"
				. ' AND t.published = 1'
				. ' ORDER BY t.name'
				;
		} else {
			$cids = array();
			foreach ($items as $item) { array_push($cids, $item->id); }
			$query 	= 'SELECT DISTINCT t.name, i.itemid,'
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
	function _getCategories($items)
	{
		// This is fix for versioned field of creator in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->categories);
		
		$db =& JFactory::getDBO();
		
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
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->modified_by);
		
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
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);
		
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
	
	
	
	function _getTypeToCoreFieldParams ($type_id, & $tinfo, & $tparams) {
		
		static $core_field_names = null;
		if ( $core_field_names == null ) {
			$query = "SELECT field_type FROM #__flexicontent_fields WHERE iscore=1";
			//echo $query;
			$db =& JFactory::getDBO();
			$db->setQuery($query);
			$core_field_names = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			
			$core_field_names[] = 'maintext';
			$core_field_names = array_flip($core_field_names);
			unset($core_field_names['text']);
		}
		
		$query = 'SELECT t.attribs, t.name, t.alias FROM #__flexicontent_types AS t WHERE t.id = ' . $type_id;
		$db =& JFactory::getDBO();
		$db->setQuery($query);
		$typedata = $db->loadObject();
		if ( $typedata ) {
			$tinfo['typename']  = $typedata->name;
			$tinfo['typealias'] = $typedata->alias;
			$tparams = new JParameter($typedata->attribs);
			
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
	
	
	function _getLangSpecificValue ($type_id, $type_prop_value, $prop_name, & $fdata)
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
	
	
	/**
	 * Method to create field parameters in an optimized way, and also apply Type Customization for CORE fields
	 *
	 * @access	_private
	 * @return	object
	 * @since	1.5
	 */
	function loadFieldConfig(&$field, &$item, $name='', $field_type='', $label='', $desc='', $iscore=1) {
		$db =& JFactory::getDBO();
		static $tparams = array();
		static $tinfo   = array();
		static $fdata   = array();
		static $no_typeparams = null;
		if ($no_typeparams) $no_typeparams = new JParameter("");
		static $is_form;
		$is_form = JRequest::getVar('edit')=='edit' && JRequest::getVar('option')=='com_flexicontent';
		
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
				$fdata[$tindex][$field->name]->parameters = new JParameter($field->attribs);
				
			} else {
				
				$pn_prefix = $field->field_type!='maintext' ? $field->name : $field->field_type;
				
				// Initialize an empty object, and create parameters object of the field
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = new JParameter($field->attribs);
				
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
					$ts_params = new JParameter($data->attribs);
					$fdata[$tindex][$field->name]->parameters->merge($ts_params);
				}
			}
		}
		
		// Set custom label, description or maintain default
		$field->label       =  isset($fdata[$tindex][$field->name]->label)        ?  $fdata[$tindex][$field->name]->label        :  $field->label;
		$field->description =  isset($fdata[$tindex][$field->name]->description)  ?  $fdata[$tindex][$field->name]->description  :  $field->description;
		
		// Finally set field's parameters, but to clone ... or not to clone, better clone to allow customizations for individual item fields ...
		$field->parameters = clone($fdata[$tindex][$field->name]->parameters);
		
		return $field;
	}
	
	
	/**
	 * Common method to get ALL items that have matching search values for the given field id
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function onFLEXIAdvSearch( &$field )
	{
		$db = &JFactory::getDBO();
		
		if ( $field->iscore && !in_array( $field->field_type, array('title', 'maintext', 'categories', 'tags') ) ) {
			$field->html	= 'Field type: '.$field->field_type.' can be used as search filter' ;
			return;
		}
		
		$indexable_fields = array('categories', 'tags', 'select', 'selectmultiple', 'checkbox', 'checkboximage', 'radio', 'radioimage');
		
		$field_vals = JRequest::getVar('filter_'.$field->id,'');
		$field_vals = is_array($field_vals) ? $field_vals : array($field_vals);
		
		$values = array();
		foreach ($field_vals as $val) {
			$val = $db->getEscaped( trim( $val ) );
			if ( strlen($val) ) $values[] = $val;
		}
		if ( !count($values) ) return;
		//echo " &nbsp; :: &nbsp; "; print_r($values);
		
		// EITHER MATCH TEXT or VALUE IDs
		if ( in_array($field->field_type, $indexable_fields) ) {
			$match_criteria_str = " ai.value_id IN ( '". implode("', '", $values) ."')";
		} else {
			$match_criteria_str = " ai.search_index LIKE '%". implode("%' OR ai.search_index LIKE '%", $values) ."%'";
		}
		
		// Get ALL items that have such values for the given field
		$query = "SELECT ai.search_index, ai.item_id "
			." FROM #__flexicontent_advsearch_index as ai"
			." WHERE ai.field_id='{$field->id}' "
			."	AND ai.extratable='{$field->field_type}' "
			."	AND ".$match_criteria_str
			;
		$db->setQuery($query);
		$objs = $db->loadObjectList();
		if ($db->getErrorNum()) {
			echo $db->getErrorMsg();
		}
		
		// Check reusult making sure it is an array
		if ($objs===false) continue;
		$objs = is_array($objs) ? $objs : array($objs);
		
		// Create search results for found ALL items
		$resultfields = array();
		foreach($objs as $o) {
			$obj = new stdClass;
			$obj->item_id = $o->item_id;
			$obj->label = $field->label;
			$obj->value = $o->search_index;
			$resultfields[] = $obj;
		}
		$field->results = $resultfields;
	}
	
	
	/**
	 * Common method to create (insert) advanced search index DB records for various fields,
	 * this can be called by fields or copied inside the field to allow better customization
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function onIndexAdvSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null)
	{
		// Remove old search values from the DB
		$db = &JFactory::getDBO();
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='".$field->field_type."';";
		$db->setQuery($query);
		$db->query();
		
		if ( $field->iscore && !in_array( $field->field_type, array('title', 'maintext', 'categories', 'tags') ) ) {
			return;
		}
		
		// A null indicates to retrieve values
		if ($values===null) $values = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		
		// Make sure posted data is an array 
		$values = !is_array($values) ? array($values) : $values;
		
		// Add new values
		$i = 0;
		foreach($values as $vi => $v) {
			// Make sure multi-property data are unserialized
			$data = @ unserialize($v);
			$v = ($v === 'b:0;' || $data !== false) ? $data : $v;
			
			// Check value that current should be inclued in search index
			if ( !is_array($v) && !strlen($v) ) continue;
			foreach ($required_props as $cp) if (!@$v[$cp]) return;
			
			// Create search value
			$search_value = array();
			foreach ($search_props as $sp) {
				if ( isset($v[$sp]) && strlen($v[$sp]) ) $search_value[] = $v[$sp];
			}
			
			if (count($search_props) && !count($search_value)) continue;  // all search properties were empty, skip this value
			$search_value = (count($search_props))  ?  implode($props_spacer, $search_value)  :  $v;
			$search_value = $filter_func ? $filter_func($search_value) : $search_value;
			
			// Add new search value into the DB
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','".$field->field_type."','{$i}', ".$db->Quote($search_value).", ".$db->Quote($vi).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		
		//echo $field->name . ": "; print_r($values);echo "<br/>";
		//echo @$search_value ."<br/><br/>";
		return true;
	}
	
	
	/**
	 * Common method to create basic search index for various fields (added as the property field->search),
	 * this can be called by fields or copied inside the field to allow better customization
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function onIndexSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func=null)
	{
		if ( $field->iscore && !in_array( $field->field_type, array('title', 'maintext', 'categories', 'tags') ) ) {
			$field->search = '';
			return;
		}
		
		// A null indicates to retrieve values
		if ($values===null) $values = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		
		// Make sure posted data is an array 
		$values = !is_array($values) ? array($values) : $values;
		
		// Create the new search data
		$searchindex = array();
		foreach($values as $i => $v)
		{
			// Make sure multi-property data are unserialized
			$data = @ unserialize($v);
			$v = ($v === 'b:0;' || $data !== false) ? $data : $v;
			
			// Check value that current should be included in search index
			if ( !$v ) continue;
			foreach ($required_props as $cp) if (!@$v[$cp]) return;
			
			// Create search value
			$search_value = array();
			foreach ($search_props as $sp) {
				if ( @$v[$sp] ) $search_value[] = $v[$sp];
			}
			
			if (count($search_props) && !count($search_value)) continue;  // all search properties were empty, skip this value
			$searchindex[$i] = (count($search_props))  ?  implode($props_spacer, $search_value)  :  $v;
			if ($filter_func) {
				$searchindex[$i] = $filter_func ? $filter_func($searchindex[$i]) : $searchindex[$i];
			}
		}
		$field->search = implode(' | ', $searchindex);
		
		//echo $field->name . ": "; print_r($values);echo "<br/>";
		//echo $field->search ."<br/><br/>";
		return true;
	}
	
	
	/**
	 * Common method to get the allowed element values (field values with index,label,... properties) for fields that use indexed values
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function indexedField_getElements(&$field, $item, $extra_props=array())
	{
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;   // For fields that use this parameter
		
		if ($sql_mode) {  // SQL mode, parameter field_elements contains an SQL query
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			// Get/verify query string and replace item properties in it
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$query = FlexicontentFields::doQueryReplacements($field_elements, $item);
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			if (!$query || !is_array($results)) {
				return false;
			}
			
		} else { // Elements mode, parameter field_elements contain list of allowed values
			
			// Parse the elements used by field unsetting last element if empty
			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if ( empty($listelements[count($listelements)-1]) ) {
				unset($listelements[count($listelements)-1]);
			}
			
			// Split elements into their properties: value, label, extra_prop1, extra_prop2
			$listarrays = array();
			$results = array();
			$n = 0;
			foreach ($listelements as $listelement) {
				$listelement_props  = preg_split("/[\s]*::[\s]*/", $listelement);
				$results[$n] = new stdClass();
				$results[$n]->value = $listelement_props[0];
				$results[$n]->text  = $listelement_props[1];  // the text label
				$el_prop_count = 2;
				foreach ($extra_props as $extra_prop) {
					$results[$n]->{$extra_prop} = @ $listelement_props[$el_prop_count];  // extra property for fields that use it
					$el_prop_count++;
				}
				$n++;
			}
			
		}
		
		$elements = array();
		if ($results) foreach($results as $result) {
			$elements[$result->value] = $result;
		}
		
		return $elements;
	}	
	
	/**
	 * Common method to map element value INDEXES to value objects for fields that use indexed values
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function indexedField_getValues(&$field, $elements, $value_indexes, $prepost_prop='text')
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
	
	
	/**
	 * Common method to retrieve field values to be used for creating search indexes 
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function &searchIndex_getFieldValues(&$field, &$item)
	{
		$db = &JFactory::getDBO();
		
		// Create DB query to retrieve field values
		$values = null;
		if ($field->field_type == 'tags')
		{
			$query  = 'SELECT t.id AS value_id, t.name AS value'
				.' FROM #__flexicontent_tags AS t'
				.' JOIN #__flexicontent_tags_item_relations AS rel ON t.id=rel.tid'
				.' WHERE rel.itemid='.$field->item_id;
			$db->setQuery($query);
			$data = $db->loadObjectList('value_id');
			$values = array();
			foreach ($data as $v) $values[$v->value_id] = $v->value;
		}
		else if ($field->field_type == 'categories')
		{
			$query  = 'SELECT c.id AS value_id, c.title AS value'
				.' FROM #__categories AS c'
				.' JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
				.' WHERE rel.itemid='.$field->item_id;
			$db->setQuery($query);
			$data = $db->loadObjectList('value_id');
			$values = array();
			foreach ($data as $v) $values[$v->value_id] = $v->value;
		}
		else if ($field->field_type == 'maintext')
		{
			$query  = 'SELECT CONCAT_WS(\' \', c.introtext, c.fulltext) AS value'
				.' FROM #__content AS c'
				.' WHERE c.id='.$field->item_id;
		}
		else if ($field->iscore)
		{
			$query  = 'SELECT *'
				.' FROM #__content AS c'
				.' WHERE c.id='.$field->item_id;
			$db->setQuery($query);
			$data = $db->loadObject();
			$values = isset( $data->{$field->name} ) ? array($data->{$field->name}) : array();
		}
		else
		{
			$query = 'SELECT value'
				.' FROM #__flexicontent_fields_item_relations as rel'
				.' JOIN #__content as i ON i.id=rel.item_id'
				.' WHERE rel.field_id='.$field->id.' AND rel.item_id='.$field->item_id;
		}
		
		// Execute query if not already done
		if ($values === null) {
			$db->setQuery($query);
			$values = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
		}
		return $values;
	}
	
	
	/**
	 * Common method to replace item properties for the SQL value mode for various fields
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function doQueryReplacements(&$query, &$item, &$item_pros=true)
	{
		// replace item properties
		preg_match_all("/{item->[^}]+}/", $query, $matches);
		if ( !$item_pros && count($matches[0]) ) { $item_pros = count($matches[0]); return ''; }
		
		foreach ($matches[0] as $replacement_tag) {
			$replacement_value = '$'.substr($replacement_tag, 1, -1);
			eval ("\$replacement_value = \" $replacement_value\";");
			$query = str_replace($replacement_tag, $replacement_value, $query);
		}
		// replace current user language
		$query = str_replace("{curr_userlang_shorttag}", flexicontent_html::getUserCurrentLang(), $query);
		$query = str_replace("{curr_userlang_fulltag}", flexicontent_html::getUserCurrentLang(), $query);
		return $query;
	}
	
}
?>
