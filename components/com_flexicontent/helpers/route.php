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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Component Helper
jimport('joomla.application.component.helper');
jimport('joomla.html.parameter');

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

/**
 * FLEXIcontent Component Route Helper
 *
 * @static
 * @package		Joomla
 * @subpackage	FLEXIcontent
 * @since 1.5
 */
class FlexicontentHelperRoute
{
	/**
	 * function to retrieve component menuitems only once;
	 */
	static function _setComponentMenuitems () {
		// Cache the result on multiple calls
		static $_component_menuitems = null;
		if ($_component_menuitems) return $_component_menuitems;
		
		// Get menu items pointing to the Flexicontent component
		// NOTE: In J2.5 the method getItems() will return menu items that have language '*' (ALL) - OR - current user language,
		// this is what we need, since using a menu item with incorrect language will cause problems withs SEF URLs ...
		// NOTE: In J1.5 the static method JSite::getMenu() will give an error (in backend), and also an error in J3.2+
		// while JFactory::getApplication('site')->getMenu() will not return the frontend menus
		$component = JComponentHelper::getComponent('com_flexicontent');
		$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
		$_component_menuitems	= $menus->getItems(!FLEXI_J16GE ? 'componentid' : 'component_id', $component->id);
		$_component_menuitems = $_component_menuitems ? $_component_menuitems : array();
		
		return $_component_menuitems;
	}
	
	
	/**
	 * function to discover a default item id only once
	 */
	static function _setComponentDefaultMenuitemId ()
	{
		// Cache the result on multiple calls
		static $_component_default_menuitem_id = null;
		if ($_component_default_menuitem_id || $_component_default_menuitem_id===false) return $_component_default_menuitem_id;
		
		$_component_default_menuitem_id = false;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		// NOTE: In J1.5 the static method JSite::getMenu() will give an error, while JFactory::getApplication('site')->getMenu() will not return the frontend menus
		$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
		
		// Get preference for default menu item
		$params = JComponentHelper::getParams('com_flexicontent');
		$default_menuitem_preference = $params->get('default_menuitem_preference', 0);
		
		// 1. Case 0: Do not add any menu item id, if so configure in global options
		//    This will make 'componenent/flexicontent' appear in url if no other appropriate menu item is found
		if ($default_menuitem_preference == 0) {
			return $_component_default_menuitem_id=false;
		}
		
		// 2. Case 1: Try to use current menu item if pointing to Flexicontent, (if so configure in global options)
		if ($default_menuitem_preference==1) {
			$menu  = $menus->getActive();
			if ($menu && @$menu->query['option']=='com_flexicontent' ) {
				// USE current menu Item_id as default fallback menu Item_id, since it points to FLEXIcontent component
				return  $_component_default_menuitem_id = $menu->id;
			}
		}
		
		// 3. Case 1 or 2: Try to get a user defined default Menu Item, (if so configure in global options)
		if ( $default_menuitem_preference==1 || $default_menuitem_preference==2 ) {
			
			// Get default menu item id and (a) check it exists and is active (b) points to com_flexicontent (c) has public access level
			$_component_default_menuitem_id = $params->get('default_menu_itemid', false);
			$menu = $menus->getItem($_component_default_menuitem_id);
			if ( !$menu || @$menu->query['option']!='com_flexicontent' || $menu->access!=$public_acclevel ) {
				return $_component_default_menuitem_id=false; 
			}
			
			// For J1.7+ we need to get menu item associations and select the current language item
			$curr_langtag = JFactory::getLanguage()->getTag();  // Current language tag for J2.5 but not for J1.5
			if ( FLEXI_J16GE && $menu->language!='*' && $menu->language!=$curr_langtag )
			{
				require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_menus'.DS.'helpers'.DS.'menus.php');
				$helper = new MenusHelper();
				$associated = $helper->getAssociations($_component_default_menuitem_id);
				
				if ( isset($associated[$curr_langtag]) ) {
					// Return associated menu item for current language
					$_component_default_menuitem_id = $associated[$curr_langtag];
				} else {
					// No associated menu item exists pointing to the correct language
					$_component_default_menuitem_id = false;
				}
			}
			return $_component_default_menuitem_id;
		}
		
		// 4. Try to get the first menu item that points to the FlexiContent Component
		//    ... Default fallback behaviour ... it is our last choice ...
		//    ... otherwise component/flexicontent will be appended to the SEF URLs
		/*static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		if ($component_menuitems !== null && count($component_menuitems)>=1) {
			$_component_default_menuitem_id = $component_menuitems[0]->id;
		}
		return $_component_default_menuitem_id;*/
	}
	
	
	/**
	 * Get type parameters
	 */
	static function _getTypeParams()
	{
		static $types = null;
		if ($types !== null) return $types;
		
		// Retrieve item's Content Type parameters
		$db = JFactory::getDBO();
		$query = 'SELECT t.attribs, t.id '
				. ' FROM #__flexicontent_types AS t'
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('id');
		foreach ($types as $type) $type->params = FLEXI_J16GE ? new JRegistry($type->attribs) : new JParameter($type->attribs);
		
		return $types;
	}
	
	
	/**
	 * Get routed links for content items
	 */
	static function getItemRoute($id, $catid = 0, $Itemid = 0)
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		$needles = array(
			FLEXI_ITEMVIEW  => (int) $id,
			'category' => (int) $catid
		);
		
		// Create the link
		$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW;
		if($catid) {
			$link .= '&cid='.$catid;
		}
		$link .= '&id='. $id;
		
		// Find menu item id (best match)
		if ($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if ($menuitem = FlexicontentHelperRoute::_findItem($needles)) {
			$link .= '&Itemid='.$menuitem->id;
		} else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	/**
	 * Get routed links for categories
	 */
	static function getCategoryRoute($catid, $Itemid = 0, $urlvars = array())
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		$needles = array(
			'category' => (int) $catid
		);
		
		//Create the link
		$link = 'index.php?option=com_flexicontent&view=category&cid='.$catid;
		// Append given variables
		foreach ($urlvars as $varname => $varval) $link .= '&'.$varname.'='.$varval;
		
		if ($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if ($menuitem = FlexicontentHelperRoute::_findCategory($needles, $urlvars)) {
			$link .= '&Itemid='.$menuitem->id;
		} else if ($menuitem = FlexicontentHelperRoute::_findDirectory($needles)) {
			$link .= '&Itemid='.$menuitem->id;
		} else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	/**
	 * Get routed link for search view
	 */
	static function getSearchRoute($reserved=0, $Itemid = 0)
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		static $_search_default_menuitem_id = null;
		if ($_search_default_menuitem_id === null) {
			$params = JComponentHelper::getParams('com_flexicontent');
			$_search_default_menuitem_id = $params->get('search_view_default_menu_itemid', false);
		}
		
		//Create the link
		$link = 'index.php?option=com_flexicontent&view=search';

		if ($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if ($_search_default_menuitem_id) {
			$link .= '&Itemid='.$_search_default_menuitem_id;
		} else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	/**
	 * Get routed link for favourites view
	 */
	static function getFavsRoute($reserved=0, $Itemid = 0)
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		static $_favs_default_menuitem_id = null;
		if ($_favs_default_menuitem_id === null) {
			$params = JComponentHelper::getParams('com_flexicontent');
			$_favs_default_menuitem_id = $params->get('favs_view_default_menu_itemid', false);
		}
		
		//Create the link
		$link = 'index.php?option=com_flexicontent&view=favourites';

		if ($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if ($_favs_default_menuitem_id) {
			$link .= '&Itemid='.$_favs_default_menuitem_id;
		} else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	/**
	 * Get routed links for tags
	 */
	static function getTagRoute($id, $Itemid = 0)
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		static $_tags_default_menuitem_id = null;
		if ($_tags_default_menuitem_id === null) {
			$params = JComponentHelper::getParams('com_flexicontent');
			$_tags_default_menuitem_id = $params->get('tags_view_default_menu_itemid', false);
		}
		
		$needles = array(
			'tags' => (int) $id
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=tags&id='.$id;

		if ($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if ($menuitem = FlexicontentHelperRoute::_findTag($needles)) {
			$link .= '&Itemid='.$menuitem->id;
		} else if ($_tags_default_menuitem_id) {
			$link .= '&Itemid='.$_tags_default_menuitem_id;
		} else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	static function _findItem($needles)
	{
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		
		$min_matched = 99;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		
		// ******************************************************************
		// Try to use current menu item if pointing to a category of the item
		// ******************************************************************
		
		static $curr_catmenu = null;
		if ($curr_catmenu === null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$menu = $menus->getActive();
			if ( !$menu ) {
				$curr_catmenu = false;
			} else {
				$cid = JRequest::getInt('cid', 0);
				$view_is_scat = JRequest::getVar('option') == 'com_flexicontent' && JRequest::getVar('view') == 'category' && $cid; 
				$menu_matches = $menu && $view_is_scat &&
					@$menu->query['option'] == 'com_flexicontent' && @$menu->query['view'] == 'category' && @$menu->query['cid'] == $cid;
				$curr_catmenu = $menu_matches ? $menu : false;
			}
		}
		
		
		// *****************************************************
		// Get access level and type id of the FLEXIcontent item
		// *****************************************************
		
		if ( !$needles[FLEXI_ITEMVIEW] ) return null;
		$db = JFactory::getDBO();
		$db->setQuery( 'SELECT i.access, ie.type_id '
			.' FROM #__content AS i '
			.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			.' WHERE i.id='. $needles[FLEXI_ITEMVIEW]);
		$item = $db->loadObject();
		$item_access  = $item->access;
		$type_id = $item->type_id;
		
		
		// *******************************
		// Get type data (parameters, etc)
		// *******************************
		
		static $types = null;
		if ($type_id && $types === null) {
			$types = FlexicontentHelperRoute::_getTypeParams();
		}
		$type = $type_id && isset($types[$type_id])  ?  $types[$type_id] :  false;
		
		
		// ********************************************************************************************************
		// Set (a) Content type's default menu id and (b) current category menu item id ... into the priority array
		// ********************************************************************************************************
		
		if ( $type ) {
			$type_menu_itemid_usage = $type->params->get('type_menu_itemid_usage', 0);
			$type_menu_itemid       = $type->params->get('type_menu_itemid', 0);
			if ($type_menu_itemid) {
				// Get type menu item, check that it is valid and cache it
				if ( !isset($type->typeMenuItem) ) {
					$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
					$type->typeMenuItem = $menus->getItem( $type_menu_itemid );
				}
				// Valid type menu item
				if ($type->typeMenuItem) {
					$match_lvl = $type_menu_itemid_usage ? 3 : 6;  // Priority 3: prefer type menu item instead of category prorities 4,5, but 6 is less ...
					$matches[ $match_lvl ] = $type->typeMenuItem;
					$min_matched = $min_matched > $match_lvl ? $match_lvl : $min_matched;
				}
			}
		}
		
		if ( $curr_catmenu && $needles['category'] == $curr_catmenu->query['cid'] ) {
			$matches[4] = $curr_catmenu;
			$min_matched = $min_matched > 4 ? 4 : $min_matched;  // Priority 4: prefer a matched category menu item that is also CURRENT
		}
		
		
		// *************************************************************
		// Iterate through menu items pointing to FLEXIcontent component
		// *************************************************************
		
		foreach($component_menuitems as $menuitem)
		{
			// Require appropriate access level of the menu item, to avoid access problems and redirecting guest to login page
			if (!FLEXI_J16GE) {
				// In J1.5 we need menu access level lower than item access level
				if ($menuitem->access > $item_access) continue;
			} else {
				// In J2.5 we need menu access level public or the access level of the item
				if ($menuitem->access!=$public_acclevel && $menuitem->access!=$item_access) continue;
			}
			
			if (@$menuitem->query['view'] == FLEXI_ITEMVIEW && @$menuitem->query['id'] == $needles[FLEXI_ITEMVIEW]) {
				if (@$menuitem->query['view'] == FLEXI_ITEMVIEW && @$menuitem->query['cid'] == $needles['category']) {
					$matches[1] = $menuitem; // priority 1: item id+cid
					$min_matched = 1;
					break;  // MAX prority, break out !
				} else if ($min_matched > 2) {
					$matches[2] = $menuitem; // priority 2: item id
					$min_matched = $min_matched > 2 ? 2 : $min_matched;
				}
			} else if ($min_matched > 5 && @$menuitem->query['view'] == 'category'      // match category menu items ...
				&& @$menuitem->query['cid'] == $needles['category']   // ... that point to item's category
				&& @$menuitem->query['layout'] == '' // ... but do not match "author", "my items", etc, limited to the specific category
			) {
				// Do not match menu items that override category configuration parameters, these items will be selectable only
				// (a) via direct click on the menu item or (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
				if (!isset($menuitem->jparams)) $menuitem->jparams = FLEXI_J16GE ? $menuitem->params : new JParameter($menuitem->params);
				if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;
				
				$matches[5] = $menuitem; // priority 5 category cid
				$min_matched = $min_matched > 5 ? 5 : $min_matched;
			}
		}
		
		// Use the one with higher priority
		$match = $min_matched < 99 ? $matches[$min_matched] : null;
		return $match;
	}
	
	
	static function _findCategory($needles, $urlvars=array())
	{
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$db = JFactory::getDBO();
		
		// multiple needles, because maybe searching for multiple categories,
		// also earlier needles (catids) takes priority over later ones
		foreach($needles as $needle => $cid)  {

			// Get access level of the FLEXIcontent category
			$db->setQuery('SELECT access FROM #__categories WHERE id='.$cid);
			$cat_acclevel = $db->loadResult();

			foreach($component_menuitems as $menuitem) {

				// Require appropriate access level of the menu item, to avoid access problems and redirecting guest to login page
				if (!FLEXI_J16GE) {
					// In J1.5 we need menu access level lower than category access level
					if ($menuitem->access > $cat_acclevel) continue;
				} else {
					// In J2.5 we need menu access level public or the access level of the category
					if ($menuitem->access!=$public_acclevel && $menuitem->access==$cat_acclevel) continue;
				}

				// Try to match important menu / url variables
				if (
					@$menuitem->query['view'] != $needle ||
					@$menuitem->query['cid'] != $cid ||
					// these variables must be explicitely checked, we must not rely on the urlvars loop below !
					@$menuitem->query['layout'] != @$urlvars['layout'] || // match layout "author", "myitems", "mcats" etc
					@$menuitem->query['authorid'] != @$urlvars['authorid'] || // match "authorid" for user id of author
					@$menuitem->query['cids'] != @$urlvars['cids'] // match "cids" of "mcats" (multi-category view)
				) continue;
				
				// Try to match any other given url variables, if these were specified and thus are required
				$all_matched = true;
				foreach ($urlvars as $varname => $varval) {
					$all_matched = $all_matched &&  (@$menuitem->query[$varname] == $varval);
				}
				if ( !$all_matched ) continue;
				
				// All important menu variables and the optional urlvars were matched, an appropriate menu item was found
				// ... but do not match menu items that override category configuration parameters, these items will be selectable only
				// (a) via direct click on the menu item or (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
				if (!isset($menuitem->jparams)) $menuitem->jparams = FLEXI_J16GE ? $menuitem->params : new JParameter($menuitem->params);
				if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;

				$match = $menuitem;
				break;
			}
			
			if (isset($match))  break;  // If a menu item for a category found, do not search for next needles (category ids)
		}

		return $match;
	}
	
	
	static function _findDirectory($needles, $urlvars=array())
	{
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		global $globalcats;
		$db = JFactory::getDBO();
		
		// multiple needles, because maybe searching for multiple categories,
		// also earlier needles (catids) takes priority over later ones
		foreach($needles as $needle => $cid)  {

			// Get access level of the FLEXIcontent category
			$db->setQuery('SELECT access FROM #__categories WHERE id='.$cid);
			$cat_acclevel = $db->loadResult();

			foreach($component_menuitems as $menuitem) {

				// Require appropriate access level of the menu item, to avoid access problems and redirecting guest to login page
				if (!FLEXI_J16GE) {
					// In J1.5 we need menu access level lower than category access level
					if ($menuitem->access > $cat_acclevel) continue;
				} else {
					// In J2.5 we need menu access level public or the access level of the category
					if ($menuitem->access!=$public_acclevel && $menuitem->access==$cat_acclevel) continue;
				}

				// Check menu item points to directory view
				if ( @$menuitem->query['view'] != 'flexicontent' ) continue;
				
				// Check non-related directory view
				$matched_root   = @$menuitem->query['rootcat'] == $cid;
				$matched_parent = @$menuitem->query['rootcat'] == $globalcats[$cid]->parent_id;
				if ( !$matched_root && !$matched_parent ) continue;
				
				// Try to match any other given url variables, if these were specified and thus are required
				$all_matched = true;
				foreach ($urlvars as $varname => $varval) {
					$all_matched = $all_matched &&  (@$menuitem->query[$varname] == $varval);
				}
				if ( !$all_matched ) continue;
				
				$match = $menuitem;
				if ( $matched_root ) break; // DONE: we matched a directory having root the given category
				// else ... category's parent was matched ... continue searching for better match
			}
			
			if (isset($match))  break;  // If a menu item for a category found, do not search for next needles (category ids)
		}

		return $match;
	}
	
	
	static function _findTag($needles)
	{
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$user = JFactory::getUser();
		if (FLEXI_J16GE)
			$aid_arr = $user->getAuthorisedViewLevels();
		else
			$aid = (int) $user->get('aid');
		
		// multiple needles, because maybe searching for multiple tag ids,
		// also earlier needles (tag ids) takes priority over later ones
		foreach($needles as $needle => $id)
		{
			foreach($component_menuitems as $menuitem) {

				// Require appropriate access level of the menu item, to avoid access problems and redirecting guest to login page
				// Since tags do not have access level we will get a menu item accessible by the access levels of the current user
				if (!FLEXI_J16GE) {
					// In J1.5 we need menu access level lower or equal to that of the user
					if ($menuitem->access > $aid) continue;
				} else {
					// In J2.5 we need a menu access level granted to the current user
					if (!in_array($menuitem->access,$aid_arr)) continue;
				}

				if ( (@$menuitem->query['view'] == $needle) && (@$menuitem->query['id'] == $id) ) {
					$match = $menuitem;
					break;
				}
			}
			if(isset($match))  break;  // If a menu item for a tag found, do not search for next needles (tag ids)
		}

		return $match;
	}
}
?>