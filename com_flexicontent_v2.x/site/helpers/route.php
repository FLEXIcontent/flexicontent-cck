<?php
/**
 * @version 1.5 stable $Id: route.php 1910 2014-06-08 17:48:19Z ggppdk $
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
	protected static $lookup = null;
	protected static $lang_lookup = array();
	
	/**
	 * function to retrieve component menuitems only once;
	 */
	static function _setComponentMenuitems () {
		// Cache the result on multiple calls
		static $component_menuitems = null;
		if ($component_menuitems) return $component_menuitems;
		
		// Get menu items pointing to the Flexicontent component
		// NOTE 1:
		//  -- In J2.5+ if language filtering is enabled: JFactory::getApplication('site')->getLanguageFilter()==true ... or same ... JLanguageMultilang::isEnabled()==true
		//     then method getItems() will return menu items that : 
		//     (a) are of currently selected language - OR - language '*' (=ALL) and
		//     (b) have any of the access levels given to the current user
		//     this is what we need, since using a menu item with incorrect language or incorrect access level will cause problems with SEF URLs ...
		//     if language filtering is disable then menu item of any language is returned BUT WE WILL USE ONLY:
		//     (a) item's language (if not given we will use currently active language)
		//     (b) also try to find menu items of 'ALL' language (='*')
		//
		// NOTE 2:
		//  -- In J1.5 the static method JSite::getMenu() will give an error (in backend), and also an error in J3.2+
		//     while JFactory::getApplication('site')->getMenu() will not return the frontend menus
		// 
		$component = JComponentHelper::getComponent('com_flexicontent');
		$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
		$_component_menuitems	= $menus->getItems(!FLEXI_J16GE ? 'componentid' : 'component_id', $component->id);
		$_component_menuitems = $_component_menuitems ? $_component_menuitems : array();
		$component_menuitems = array();
		foreach ($_component_menuitems as $item) {
			$component_menuitems[$item->id] = $item;
		}
		
		return $component_menuitems;
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
			if ( FLEXI_J16GE && $menu->language!='*' && $menu->language!='' && $menu->language!=$curr_langtag )
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
	 * Get language data
	 */
	protected static function buildLanguageLookup()
	{
		if(count(self::$lang_lookup) == 0)
		{
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true)
				->select('a.sef AS sef')
				->select('a.lang_code AS lang_code')
				->from('#__languages AS a');

			$db->setQuery($query);
			$langs = $db->loadObjectList();
			foreach ($langs as $lang)
			{
				self::$lang_lookup[$lang->lang_code] = $lang->sef;
			}
		}
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
	static function getItemRoute($id, $catid = 0, $Itemid = 0, $item = null)
	{
		static $component_default_menuitem_id = null;  // Calculate later only if needed
		
		static $current_language = null;
		if ($current_language===null) $current_language = JFactory::getLanguage()->getTag();
		
		static $use_language = null;
		if ($use_language===null) $use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
		
		$_id = (int) $id;
		$_catid = (int) $catid;
		
		// *************************************************************
		// Get data of the FLEXIcontent item (only if not already given)
		// including data like : access level, type id, language
		// *************************************************************
		
		// Compatibility with calls not passing item data, check for item data in global object, avoiding an extra SQL call
		if ( !$item ) {
			global $fc_list_items;
			if ( !empty($fc_list_items) && isset($fc_list_items[$_id]) ) {
				$item = $fc_list_items[$_id];
			}
		}
		
		// Do not do 1 SQL query per item, to get the type id and language  ...  1. for type_id, we ignore, 2. for language we will use current language
		$language = !FLEXI_J16GE || !$item || @!$item->language ? $current_language : $item->language;
		$type_id = ($item && isset($item->type_id))? $item->type_id : 0;
		
		
		// **************************************************
		// DONE ONCE: Get data of ALL types (parameters, etc)
		// **************************************************
		
		static $types = null;
		if ($type_id && $types === null) {
			$types = FlexicontentHelperRoute::_getTypeParams();
		}
		$type = $type_id && isset($types[$type_id])  ?  $types[$type_id] :  false;
		
		
		// *****************************************************************
		// DONE ONCE (per encountered type): Get content type's default menu
		// *****************************************************************
		
		if ( $type ) {
			$type_menu_itemid_usage = $type->params->get('type_menu_itemid_usage', 0);  // ZERO: do not use, 1: before item's category, 2: after item's category
			$type_menu_itemid       = $type->params->get('type_menu_itemid', 0);
			if ($type_menu_itemid_usage && $type_menu_itemid) {
				if ( !isset($type->typeMenuItem) ) {
					$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
					$type->typeMenuItem = $menus->getItem( $type_menu_itemid );
				}
			}
		}
		
		
		// *******************************************************************************************
		// DONE ONCE: ... if currently in a category view ... check if current menu item points to it
		// so that we will prefer it, if none menu items that matches the given needles array is found
		// *******************************************************************************************
		
		static $curr_catmenu = null;
		if ($curr_catmenu === null) {
			$current_cid = JRequest::getVar('cid');
			
			if ( is_array($current_cid) ) {
				$curr_catmenu = false;
			} else if ( (int) $current_cid ) {
				$current_cid = (int) $current_cid;
				$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
				$menu = $menus->getActive();
				
				if ( !$menu || !$current_cid ) {
					$curr_catmenu = false;
				} else {
					$view_is_cat = JRequest::getVar('option') == 'com_flexicontent' && JRequest::getVar('view') == 'category';
					$menu_is_cat = @$menu->query['option'] == 'com_flexicontent' && @$menu->query['view'] == 'category';
					$menu_matches = $menu && $view_is_cat && $menu_is_cat &&@$menu->query['cid'] == $current_cid;
					$curr_catmenu = $menu_matches ? $menu : false;
				}
			}
		}
		
		
		// **********************************************************************************
		// Get item's parent categores to be used in search a menu item of type category view
		// **********************************************************************************
		
		$parents_ids = array();
		global $globalcats;
		if ( $_catid && isset($globalcats[$_catid]->ancestorsarray) ) {
			$parents_ids = array_reverse($globalcats[$_catid]->ancestorsarray);
		}
		
		
		// *******************************************************
		// Create the needles search array, in descending priority
		// *******************************************************
		
		$needles = array();
		
		// Priority 1: Item view menu items of given item ID
		$needles[FLEXI_ITEMVIEW] = array($_id);
		
		// Priority 2: Type's default before categories (if so configured): ... giving an object means no-lookup and just use it
		if ($type && $type_menu_itemid_usage==1 && $type->typeMenuItem)  $needles['type_before'] = $type->typeMenuItem;
		
		// Priority 3: Category view menu items of given category IDs ... item's category and its parent categories in ascending order
		$needles['category'] = $parents_ids;
		
		// Priority 4: Directory view menu items ... pointing to same category IDs as above
		$needles['flexicontent'] = $needles['category'];
		
		// Priority 5: Type's default after categories (if so configured): ... giving an object means no-lookup and just use it
		if ($type && $type_menu_itemid_usage==2 && $type->typeMenuItem)  $needles['type_after'] = $type->typeMenuItem;
		
		// Priority 6: Currently active menu item that matches current category view ... giving an object means no-lookup and just use it
		if ($curr_catmenu)  $needles['current_category'] = $curr_catmenu;
		
		// Do not add component's default menu item to allow trying "ALL" language items ? before component default ?
		
		// These will be unset and not used needles lookup loop
		$needles['_item'] = $item;   
		$needles['_language'] = 0;   // we will overwrite language below if needed, ()
		
		
		// ***************
		// Create the link
		// ***************
		
		// view
		$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW;
		// category id
		if ($catid) $link .= '&cid='.$catid;
		// item id
		$link .= '&id='. $id;
		// SEF language code as so configured
		if ($use_language && $language && $language != "*")
		{
			self::buildLanguageLookup();
			if(isset(self::$lang_lookup[$language]))
			{
				$link .= '&lang='.self::$lang_lookup[$language];
				$needles['_language'] = $language;
			}
		}
		
		
		// *************************************************
		// Finally find the menu item id (best match) to use
		// *************************************************
		
		// Try 1: USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
			return $link;
		}
		
		// Try 2: given item's language (or current language), this will fallback to also try '*' (language 'ALL')
		if ($menuitem = FlexicontentHelperRoute::_findItem($needles)) {
			$link .= '&Itemid='.$menuitem->id;
			return $link;
		}
		
		// Try 3: component's default menu item
		if ($component_default_menuitem_id === null)
			$component_default_menuitem_id = FlexicontentHelperRoute::_setComponentDefaultMenuitemId();
		if ($component_default_menuitem_id)
			$link .= '&Itemid='.$component_default_menuitem_id;
		
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
		// These will be added to a reverse lookup hash for O(1) lookup
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		
		static $user_access = null;
		if ($user_access===null) {
			$user = JFactory::getUser();
			$user_access = FLEXI_J16GE ? $user->getAuthorisedViewLevels() : (int) $user->get('aid');
		}
		
		if ( empty($needles[FLEXI_ITEMVIEW]) ) return null;
		
		$min_matched = 99;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		
		// Get language to match
		$language = $needles['_language'];
		unset($needles['_language']);
		
		
		// **********************************************************************************************************
		// DONCE ONCE: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup hash
		// **********************************************************************************************************
		
		if ( !isset(self::$lookup[$language]) ) {
			self::$lookup[$language] = array();
			
			foreach($component_menuitems as $menuitem)
			{
				if ( !isset($menuitem->query) || !isset($menuitem->query['view']) ) continue;  // view not set
				if ( FLEXI_J16GE && $menuitem->language != $language ) continue;   // wrong language
				
				// Do not match menu items that override category configuration parameters, these items will be selectable only
				// (a) via direct click on the menu item or
				// (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
				// (c) they are currently active ...
				if ( @$menuitem->query['view'] == 'category'      // match category menu items ...
					&& @$menuitem->query['cid'] == $needles['category']   // ... that point to item's category
					&& @$menuitem->query['layout'] == '' // ... but do not match "author", "my items", etc, limited to the specific category
				) {
					if (!isset($menuitem->jparams)) $menuitem->jparams = FLEXI_J16GE ? $menuitem->params : new JParameter($menuitem->params);
					if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;
				}
				
				$view = $menuitem->query['view'];
				if (!isset(self::$lookup[$language][$view])) {
					self::$lookup[$language][$view] = array();
				}
				if ($view == FLEXI_ITEMVIEW) {
					if ( !empty($menuitem->query['id']) )  self::$lookup[$language][$view][$menuitem->query['id']] = (int) $menuitem->id;
				} else if ($view == 'category') {
					if ( !empty($menuitem->query['cid']) )  self::$lookup[$language][$view][$menuitem->query['cid']] = (int) $menuitem->id;
				} else if ($view == 'flexicontent') {
					if ( !empty($menuitem->query['rootcat']) )  {
						//echo $menuitem->id . " - ". $menuitem->query['rootcat'];
						self::$lookup[$language][$view][$menuitem->query['rootcat']] = (int) $menuitem->id;
					}
				}
				
				
			}
		}
		
		
		// No find menu item for given needles of item, this will be usually 1 lookup for item's 
		$level = 0;
		if ($needles)
		{
			foreach ($needles as $view => $ids)
			{
				if ( is_object($ids) ) return $ids;  // done, this an already appropriate menu item object
				
				// Lookup if then given ids for the given view exists for the given language
				if (isset(self::$lookup[$language][$view]))
				{
					foreach($ids as $id)
					{
						if ( !isset(self::$lookup[$language][$view][(int)$id]) ) continue;  // not found
						
						//echo "$language $view $id : ". self::$lookup[$language][$view][(int)$id] ."<br/>";
						$menuid = self::$lookup[$language][$view][(int)$id];
						$menuitem = $component_menuitems[$menuid];
						
						// In J1.5 we need menu access level lower/equal to user's access level, in J2.5+ this is already done by JMenuSite::getItems()
						if (!FLEXI_J16GE && $menuitem->access > $user_access) continue;
						
						return $menuitem;
					}
				}
			}
		}
		
		if ($language != '*')
		{
			$needles['_language'] = '*';
			return self::_findItem($needles);
		}
		
		// not found
		return false;
	}
	
	
	static function _findCategory($needles, $urlvars=array())
	{
		static $component_menuitems = null;
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setComponentMenuitems();
		global $globalcats;
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$db = JFactory::getDBO();
		
		// Get current menu item, we will prefer current menu if it points to given tag,
		// thus maintaining current menu item if multiple menu items to same tag exist !!
		static $menu = null;
		if ($menu == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$menu = $menus->getActive();
			if ($menu && @$menu->query['option']!='com_flexicontent') $menu=false;
		}
		
		// multiple needles, because maybe searching for multiple categories,
		// also earlier needles (catids) takes priority over later ones
		foreach($needles as $needle => $cid)  {

			// Get access level of the FLEXIcontent category
			$cat_acclevel = $cid && isset($globalcats[$cid]) ? $globalcats[$cid]->access : 0;
			
			// Prefer current menu item if pointing to given category url ...
			if ($menu && 
				@$menu->query['view'] == $needle &&
				@$menu->query['cid'] == $cid &&
				// these variables must be explicitely checked, we must not rely on the urlvars loop below !
				@$menu->query['layout'] == @$urlvars['layout'] && // match layout "author", "myitems", "mcats", "favs", "tags" etc
				@$menu->query['authorid'] == @$urlvars['authorid'] && // match "authorid" for user id of author
				@$menu->query['cids'] == @$urlvars['cids'] && // match "cids" of "mcats" (multi-category view)
				@$menu->query['tagid'] == @$urlvars['tagid'] // match "tagid" of "tags" view
			) {
				$match = $menu;
				break;
			}
			
			foreach($component_menuitems as $menuitem)
			{
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
					@$menuitem->query['layout'] != @$urlvars['layout'] || // match layout "author", "myitems", "mcats", "favs", "tags" etc
					@$menuitem->query['authorid'] != @$urlvars['authorid'] || // match "authorid" for user id of author
					@$menuitem->query['cids'] != @$urlvars['cids'] || // match "cids" of "mcats" (multi-category view)
					@$menu->query['tagid'] != @$urlvars['tagid'] // match "tagid" of "tags" view
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
		global $globalcats;
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		global $globalcats;
		$db = JFactory::getDBO();
		
		// multiple needles, because maybe searching for multiple categories,
		// also earlier needles (catids) takes priority over later ones
		foreach($needles as $needle => $cid)  {

			// Get access level of the FLEXIcontent category
			$cat_acclevel = $cid && isset($globalcats[$cid]) ? $globalcats[$cid]->access : 0;

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
				if ($cid && isset($globalcats[$cid])) {
					$matched_parent = @$menuitem->query['rootcat'] == $globalcats[$cid]->parent_id;
					if ( !$matched_parent ) continue;
				}
				
				// Try to match any other given url variables, if these were specified and thus are required
				$all_matched = true;
				foreach ($urlvars as $varname => $varval) {
					$all_matched = $all_matched &&  (@$menuitem->query[$varname] == $varval);
				}
				if ( !$all_matched ) continue;
				
				$match = $menuitem;
				break; // DONE: we matched a directory having root the parent of given category
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
		
		// Get current menu item, we will prefer current menu if it points to given tag,
		// thus maintaining current menu item if multiple menu items to same tag exist !!
		static $menu = null;
		if ($menu == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$menu = $menus->getActive();
			if ($menu && @$menu->query['option']!='com_flexicontent') $menu=false;
		}
		
		// multiple needles, because maybe searching for multiple tag ids,
		// also earlier needles (tag ids) takes priority over later ones
		foreach($needles as $needle => $id)
		{
			// Prefer current menu item if pointing to given tag
			if ($menu && (@$menu->query['view'] == $needle) && (@$menu->query['id'] == $id) ) {
				$match = $menu;
				break;
			}
			
			foreach($component_menuitems as $menuitem)
			{
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