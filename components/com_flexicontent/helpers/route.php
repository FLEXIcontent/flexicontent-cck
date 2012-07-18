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
	function _setComponentMenuitems () {
		// Cache the result on multiple calls
		static $_component_menuitems = null;
		if ($_component_menuitems) return $_component_menuitems;
		
		// Get menu items pointing to the Flexicontent component
		// NOTE: In J2.5 the method getItems() will return menu items that have language '*' (ALL) - OR - current user language,
		// this is what we need, since using a menu item with incorrect language will cause problems withs SEF URLs ...
		$component =& JComponentHelper::getComponent('com_flexicontent');
		$menus	= &JApplication::getMenu('site', array());
		$_component_menuitems	= $menus->getItems(!FLEXI_J16GE ? 'componentid' : 'component_id', $component->id);
		$_component_menuitems = $_component_menuitems ? $_component_menuitems : array();
		
		return $_component_menuitems;
	}
	
	/**
	 * function to discover a default item id only once
	 */
	function _setComponentDefaultMenuitemId () {
		// Cache the result on multiple calls
		static $_component_default_menuitem_id = null;
		if ($_component_default_menuitem_id || $_component_default_menuitem_id===false) return $_component_default_menuitem_id;
		$_component_default_menuitem_id = false;
		
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$menus = JSite::getMenu();
		$params =& JComponentHelper::getParams('com_flexicontent');
		$default_menuitem_preference = $params->get('default_menuitem_preference', 0);
		
		// 1. Case 0: Do not add any menu item id, if so configure in global options
		//    This will make 'componenent/flexicontent' appear in url if no other appropriate menu item is found
		if ($default_menuitem_preference == 0) {
			return $_component_default_menuitem_id=false;
		}
		
		// 2. Case 1: Try to use current menu item if pointing to Flexicontent, (if so configure in global options)
		$app = JFactory::getApplication();
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
		/*$component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		if ($component_menuitems !== null && count($component_menuitems)>=1) {
			$_component_default_menuitem_id = $component_menuitems[0]->id;
		}
		return $_component_default_menuitem_id;*/
	}
	
	/**
	 * @param	int	The route of the item
	 */
	function getItemRoute($id, $catid = 0, $Itemid = 0)	{
		
		$needles = array(
			FLEXI_ITEMVIEW  => (int) $id,
			'category' => (int) $catid
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW;

		if($catid) {
			$link .= '&cid='.$catid;
		}

		$link .= '&id='. $id;

		if($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if($menuitem = FlexicontentHelperRoute::_findItem($needles)) {
			$link .= '&Itemid='.$menuitem->id;
		} else {
			$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}

	function getCategoryRoute($catid, $Itemid = 0, $urlvars = array()) {
		
		$needles = array(
			'category' => (int) $catid
		);
		
		//Create the link
		$link = 'index.php?option=com_flexicontent&view=category&cid='.$catid;
		// Append given variables
		foreach ($urlvars as $varname => $varval) $link .= '&'.$varname.'='.$varval;
		
		if($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if($menuitem = FlexicontentHelperRoute::_findCategory($needles, $urlvars)) {
			$link .= '&Itemid='.$menuitem->id;
		} else {
			$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if($component_default_menuitem_id) {
				$link .= '&Itemid='.$component_default_menuitem_id;
			}
		}
		
		return $link;
	}
	
	function getTagRoute($id, $Itemid = 0) {
		
		$needles = array(
			'tags' => (int) $id
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=tags&id='.$id;

		if($Itemid) { // USE the itemid provided, if we were given one it means it is "appropriate and relevant"
			$link .= '&Itemid='.$Itemid;
		} else if($menuitem = FlexicontentHelperRoute::_findTag($needles)) {
			$link .= '&Itemid='.$menuitem->id;
		} else {
			$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
			if($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}

	function _findItem($needles)
	{
		$component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		// Get access level of the FLEXIcontent item
		$db =& JFactory::getDBO();
		$db->setQuery('SELECT access FROM #__content WHERE id='.$needles[FLEXI_ITEMVIEW]);
		$item_acclevel = $db->loadResult();
		
		foreach($component_menuitems as $menuitem)
		{
			// Require appropriate access level of the menu item, to avoid access problems and redirecting guest to login page
			if (!FLEXI_J16GE) {
				// In J1.5 we need menu access level lower than item access level
				if ($menuitem->access > $item_acclevel) continue;
			} else {
				// In J2.5 we need menu access level public or the access level of the item
				if ($menuitem->access!=$public_acclevel && $menuitem->access==$item_acclevel) continue;
			}
			
			if (@$menuitem->query['view'] == FLEXI_ITEMVIEW && @$menuitem->query['id'] == $needles[FLEXI_ITEMVIEW]) {
				if (@$menuitem->query['view'] == FLEXI_ITEMVIEW && @$menuitem->query['cid'] == $needles['category']) {
					$matches[1] = $menuitem; // priority 1: item id+cid
					break;
				} else {
					$matches[2] = $menuitem; // priority 2: item id
					// no break continue searching for better match ...
				}
			} else if (@$menuitem->query['view'] == 'category'      // match category menu items ...
				&& @$menuitem->query['cid'] == $needles['category']   // ... that point to item's category
				&& @$menuitem->query['layout'] == '' // ... but do not match "author", "my items", etc, limited to the specific category
			) {	
				// Do not match menu items that override category configuration parameters, these items will be selectable only
				// (a) via direct click on the menu item or (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
				//if (!isset($menuitem->jparams)) $menuitem->jparams = new JParameter($menuitem->params);
				//if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;
				
				$matches[3] = $menuitem; // priority 3 category cid
				// no break continue searching for better match ...
			}
		}
		
		// Use the one with higher priority
		for ($priority=1; $priority<=3; $priority++) {
			if (isset($matches[$priority])) {
				$match = $matches[$priority];
				break;
			}
		}

		return $match;
	}

	function _findCategory($needles, $urlvars=array())
	{
		$component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		// multiple needles, because maybe searching for multiple categories,
		// also earlier needles (catids) takes priority over later ones
		foreach($needles as $needle => $cid)  {

			// Get access level of the FLEXIcontent category
			$db =& JFactory::getDBO();
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

				if ( @$menuitem->query['view'] == $needle && @$menuitem->query['cid'] == $cid ) {
					
					// Try to match optional url variables, if these were specified
					$all_matched = true;
					foreach ($urlvars as $varname => $varval) {
						$all_matched = $all_matched &&  (@$menuitem->query[$varname] == $varval);
					}
					
					// all view , cid and urlvars were matched an appropriate menu item was found
					if ($all_matched) {
						
						// Do not match menu items that override category configuration parameters, these items will be selectable only
						// (a) via direct click on the menu item or (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
						if (!isset($menuitem->jparams)) $menuitem->jparams = new JParameter($menuitem->params);
						if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;

						$match = $menuitem;
						break;
					}
				}
			}
			
			if(isset($match))  break;  // If a menu item for a category found, do not search for next needles (category ids)
		}

		return $match;
	}

	function _findTag($needles)
	{
		$component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$user = &JFactory::getUser();
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