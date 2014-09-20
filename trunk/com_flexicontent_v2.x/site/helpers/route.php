<?php
/**
 * @version 1.5 stable $Id: route.php 1960 2014-09-19 02:20:50Z ggppdk $
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
	protected static $add_url_lang  = null;
	protected static $interface_langs = null;
	protected static $lang_homes = null;
	protected static $menuitems = null;
	
	/**
	 * function to retrieve component menuitems only once;
	 */
	static function _setMenuitems($language = '*')
	{
		// J1.5 has no language in menu items
		if ( !FLEXI_J16GE ) $language = '*';
		
		// Return already retrieved data
		if ( isset(self::$menuitems[$language]) ) return self::$menuitems[$language];
		
		// Get user access, this is needed only for J1.5
		$user_access = null;
		if (!FLEXI_J16GE && $user_access===null) {
			$user = JFactory::getUser();
			$user_access = (int) $user->get('aid');
		}
		
		// Get component
		$component = JComponentHelper::getComponent('com_flexicontent');
		
		// Get menu items pointing to the Flexicontent component
		// NOTE:
		//  -- In J1.5 the static method JSite::getMenu() will give an error (in backend), and also an error in J3.2+
		//     while JFactory::getApplication('site')->getMenu() will not return the frontend menus
		$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
		
		// NOTE:
		
		if (!FLEXI_J16GE) {
			$_menuitems	= $menus->getItems('componentid', $component->id);
		}
		
		else {
			$attribs = array('component_id');
			$values  = array($component->id);
			
			if ($language != '*') {
				// Limit to given language and ... to language ALL ('*')
				$attribs[] = 'language';
				$values[]  = array($language, '*');
			} else {
				// Getting menu items regardless language
				// A. If language filtering is enabled,  then menu items with currently active language - OR - language '*'
				// B. If language filtering is disabled, then menu items of any language are returned
			}
			$_menuitems = $menus->getItems($attribs, $values);
		}
		
		// Assign menu item objects to per language array, and also index by menu id
		self::$menuitems[$language] = array();
		if ($_menuitems) foreach ($_menuitems as $item)
		{
			// In J1.5 filter by access levels of current user
			// In J2.5+ this is already done by JMenuSite::getItems()
			if (!FLEXI_J16GE && $menuitem->access > $user_access) continue;
			
			// Index by menu id
			self::$menuitems[$language][$item->id] = $item;
		}
		
		return self::$menuitems[$language];
	}
	
	
	/**
	 * function to discover a default item id only once
	 */
	static function _setDefaultMenuitemId()
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
	}
	
	
	
	/**
	 * Get language data
	 */
	protected static function _buildLanguageLookup()
	{
		if(count(self::$lang_lookup) == 0)
		{
			// Create map of: item language code to SEF URL language code
			// We don't use helper function so that we also get non-published ones
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
			
			// Get configuration whether to remove SEF language code from URL
			$plugin = JPluginHelper::getPlugin('system', 'languagefilter');
			if (!empty($plugin)) {
				$pluginParams = FLEXI_J16GE ? new JRegistry($plugin->params) : new JParameter($plugin->params);
				self::$add_url_lang = ! $pluginParams->get('remove_default_prefix', 0);
			} else {
				self::$add_url_lang = 1;
			}
			
			// No need to do more work since we will not add language code to the URLs
			if ( !self::$add_url_lang ) return;
			
			// Get user's access levels
			$user	= JFactory::getUser();
			$levels = JAccess::getAuthorisedViewLevels($user->id);
			
			// Get home page menu items according to language, and 
			self::_getHomes();
			
			// Get content languages and filter them to include only inteface languages
			$content_langs = JLanguageHelper::getLanguages();
			$interface_langs = array();
			
			foreach ($content_langs as $i => &$language)
			{
				// Do not display language without frontend UI
				if (!JLanguage::exists($language->lang_code))
					continue;
				
				// Do not display language without specific home menu
				elseif (!isset(self::$lang_homes[$language->lang_code]))
					continue;
				
				// Do not display language without authorized access level
				elseif (isset($language->access) && $language->access && !in_array($language->access, $levels))
					continue;
				
				self::$interface_langs[$language->lang_code] = $language;
			}
			// DEBUG print the filtered languages
			//foreach (self::$interface_langs as $lang_code => $lang) echo $lang->title.'['.$lang_code.']'."<br/>\n";
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
		if ($use_language===null) {
			$use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
			if ($use_language) {
				self::_buildLanguageLookup();
			}
		}
		
		$_id = (int) $id;
		$_catid = (int) $catid;
		global $globalcats;
		
		// *************************************************************
		// Get data of the FLEXIcontent item (only if not already given)
		// including data like: type id, language
		// *************************************************************
		
		// Compatibility with calls not passing item data, check for item data in global object, avoiding an extra SQL call
		if ( !$item ) {
			global $fc_list_items;
			if ( !empty($fc_list_items) && isset($fc_list_items[$_id]) ) {
				$item = $fc_list_items[$_id];
			}
		}
		
		// Do not do 1 SQL query per item, to get the type id and language  ...  
		// 1. for type_id, we ignore, 2. for language we will use current language
		$language = (!FLEXI_J16GE || !$item || @!$item->language) ? $current_language : $item->language;
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
		
		
		// *******************************************************************************
		// DONE ONCE: if current view is category view ... then check if current menu item
		// points to current category or to parent of it, so that we will prefer it,
		// if multiple menu items point to the same category as current menu item
		// *******************************************************************************
		
		static $curr_catmenu = null;
		if ($curr_catmenu === null) {
			$current_cid = JRequest::getVar('cid');
			
			$curr_catmenu = false;
			$view_is_cat = JRequest::getVar('option') == 'com_flexicontent' && JRequest::getVar('view') == 'category';
			if ( $view_is_cat && !is_array($current_cid) && (int)$current_cid ) {
				$current_cid = (int) $current_cid;
				$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
				$menu = $menus->getActive();
				
				$menu_cid = $menu && (int)@$menu->query['cid'] ? (int)@$menu->query['cid'] : 0;
				if ( $menu_cid && isset($globalcats[$current_cid]) ) {
					$current_cid_parents = array_reverse($globalcats[$current_cid]->ancestorsarray);
					//print_r($current_cid_parents );
					//exit;
					$menu_is_cat = @$menu->query['option'] == 'com_flexicontent' && @$menu->query['view'] == 'category';
					$menu_matches = false;
					if ($menu_is_cat) {
						foreach($current_cid_parents as $_catid) {
							if ($_catid == $menu_cid) {
								$menu_matches = true;
								break;
							}
						}
					}
					$curr_catmenu = $menu_matches ? $menu : false;
				}
			}
		}
		
		
		// **********************************************************************************
		// Get item's parent categores to be used in search a menu item of type category view
		// **********************************************************************************
		
		$parents_ids = array();
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
		
		// Do not add component's default menu item to allow trying "ALL" language items ? before component default ?
		
		// Other data to pass to _findItem()
		$data = array();
		$data['item'] = $item;   
		$data['language'] = '*';   // we will overwrite language below if needed, if language filtering is enabled
		$data['cat_menu'] = $curr_catmenu;  // currently active menu item that matches current category view ...
		
		
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
			if(isset(self::$lang_lookup[$language]))
			{
				if ( self::$add_url_lang && isset(self::$interface_langs[$language]) ) {
					$link .= '&lang='.self::$lang_lookup[$language];
				}
				$data['language'] = $language;
			}
		}
		
		
		// *************************************************
		// Finally find the menu item id (best match) to use
		// *************************************************
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		// Try to find the most appropriate/relevant menu item, using the priority set via needles array
		else if ($menuitem = FlexicontentHelperRoute::_findItem($needles, $data)) {
			$link .= '&Itemid='.$menuitem->id;
		}
		
		// Try to use component's default menu item
		else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setDefaultMenuitemId();
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
		
		static $current_language = null;
		if ($current_language===null) $current_language = JFactory::getLanguage()->getTag();
		
		static $use_language = null;
		if ($use_language===null) {
			$use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
			if ($use_language) {
				self::_buildLanguageLookup();
			}
		}
		
		$_catid = (int) $catid;
		global $globalcats;
		
		// **************************************
		// Get data of the FLEXIcontent category
		// data like: language, and ancestors ids
		// **************************************
		
		// Get language
		$language = isset($globalcats[$_catid]->language) ? $globalcats[$_catid]->language : $current_language;
		
		// Get item's parent categores to be used in search a menu item of type category view
		$parents_ids = array();
		if ( $_catid && isset($globalcats[$_catid]->ancestorsarray) ) {
			$parents_ids = array_reverse($globalcats[$_catid]->ancestorsarray);
		}
		
		$needles = array();
		
		// Priority 1: Category view menu items of given category ID ... and parent categories in ascending order
		$needles['category'] = $parents_ids;
		
		// Get language of category
		$data['language'] = '*';   // we will overwrite language below if needed, if language filtering is enabled
		
		
		//Create the link
		$link = 'index.php?option=com_flexicontent&view=category&cid='.$catid;
		
		// Append given variables
		foreach ($urlvars as $varname => $varval) $link .= '&'.$varname.'='.$varval;
		$data['urlvars'] = $urlvars;
		
		
		// SEF language code as so configured
		if ($use_language && $language && $language != "*")
		{
			if(isset(self::$lang_lookup[$language]))
			{
				if ( self::$add_url_lang && isset(self::$interface_langs[$language]) ) {
					$link .= '&lang='.self::$lang_lookup[$language];
				}
				$data['language'] = $language;
			}
		}
		
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		// Try to find the most appropriate/relevant menu item, using the priority set via needles array
		else if ($menuitem = FlexicontentHelperRoute::_findCategory($needles, $data)) {
			$link .= '&Itemid='.$menuitem->id;
		}
		
		// Try to find the most appropriate/relevant menu item, using the priority set via needles array
		else if ($menuitem = FlexicontentHelperRoute::_findDirectory($needles, $data)) {
			$link .= '&Itemid='.$menuitem->id;
			$link = str_replace('view=category', 'view=flexicontent', $link);
			$link = str_replace('cid=', 'rootcat=', $link);
		}
		
		// Try to use component's default menu item
		else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setDefaultMenuitemId();
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
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		else if ($_search_default_menuitem_id) {
			$link .= '&Itemid='.$_search_default_menuitem_id;
		}
		
		// Try to use component's default menu item
		else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setDefaultMenuitemId();
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
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		else if ($_favs_default_menuitem_id) {
			$link .= '&Itemid='.$_favs_default_menuitem_id;
		}
		
		// Try to use component's default menu item
		else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setDefaultMenuitemId();
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
		$data = array();
		$data['language'] = '*';  // currently, our tags do not have language

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=tags&id='.$id;
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		else if ($menuitem = FlexicontentHelperRoute::_findTag($needles, $data)) {
			$link .= '&Itemid='.$menuitem->id;
		}
		
		else if ($_tags_default_menuitem_id) {
			$link .= '&Itemid='.$_tags_default_menuitem_id;
		}
		
		// Try to use component's default menu item
		else {
			if ($component_default_menuitem_id === null)
				$component_default_menuitem_id = FlexicontentHelperRoute::_setDefaultMenuitemId();
			if ($component_default_menuitem_id)
				$link .= '&Itemid='.$component_default_menuitem_id;
		}
		
		return $link;
	}
	
	
	static function _findItem($needles, &$data)
	{
		// Get language, item ,current (matched) category menu item
		$language = $data['language'];
		$item = $data['item'];
		$cat_menu = $data['cat_menu'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		if ( !$needles ) return false;
		
		$min_matched = 99;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		
		
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Now find menu item for given needles
		foreach ($needles as $view => $ids)
		{
			if ( is_object($ids) ) return $ids;  // done, this an already appropriate menu item object
			
			// Lookup if then given ids for the given view exists for the given language
			if ( !isset(self::$lookup[$language][$view]) ) continue;
			
			foreach($ids as $id)
			{
				if ( !isset(self::$lookup[$language][$view][(int)$id]) ) continue;  // not found
				
				//echo "$language $view $id : ". self::$lookup[$language][$view][(int)$id] ."<br/>";
				$menuid = self::$lookup[$language][$view][(int)$id];
				$menuitem = $component_menuitems[$menuid];
				
				// Use current category menu if it is a category menu for same category was matched
				if ( $view=='category' && $cat_menu && $cat_menu->query['cid'] == $menuitem->query['cid'] ) {
					$menuitem = $cat_menu;
				}
				
				return $menuitem;
			}
		}
		
		return false;
	}
	
	
	static function _findCategory($needles, &$data)
	{
		// Get language, url variables
		$language = $data['language'];
		$urlvars  = $data['urlvars'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
		
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Get current menu item, we will prefer current menu if it points to given category,
		// thus maintaining current menu item if multiple menu items to same category exist !!
		static $menu = null;
		if ($menu == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$menu = $menus->getActive();
			if ($menu && @$menu->query['option']!='com_flexicontent') $menu=false;
		}
		
		
		// Prefer current menu item
		foreach ($needles as $view => $ids)
		{
			if ($view!='category') continue;
			
			// 1st category id, is ID of category that we make route,  and remaining ids are parents of it
			$cid = reset($ids);
			
			// Prefer current menu item if pointing to given category url ...
			if ($menu && 
				@$menu->query['view'] == $view &&
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
		}
		
		

		// Now find menu item for given needles
		foreach ($needles as $view => $ids)
		{
			if ( is_object($ids) ) return $ids;  // done, this an already appropriate menu item object
			if ( $view=='_language' ) continue;
			
			// Lookup if then given ids for the given view exists for the given language
			if ( !isset(self::$lookup[$language][$view]) ) continue;
			
			foreach($ids as $id)
			{
				if ( !isset(self::$lookup[$language][$view][(int)$id]) ) continue;  // not found
				
				//echo "$language $view $id : ". self::$lookup[$language][$view][(int)$id] ."<br/>";
				$menuid = self::$lookup[$language][$view][(int)$id];
				$menuitem = $component_menuitems[$menuid];
				
				// Try to match important menu / url variables. NOTE: view and cid variables were already matched via table lookup
				if (
					// these variables must be explicitely checked, we must not rely on the urlvars loop below !
					@$menuitem->query['layout'] != @$urlvars['layout'] || // match layout "author", "myitems", "mcats", "favs", "tags" etc
					@$menuitem->query['authorid'] != @$urlvars['authorid'] || // match "authorid" for user id of author
					@$menuitem->query['cids'] != @$urlvars['cids'] || // match "cids" of "mcats" (multi-category view)
					@$menuitem->query['tagid'] != @$urlvars['tagid'] // match "tagid" of "tags" view
				) continue;
				
				// Try to match any other given url variables, if these were specified and thus are required
				$all_matched = true;
				foreach ($urlvars as $varname => $varval) {
					$all_matched = $all_matched &&  (@$menuitem->query[$varname] == $varval);
				}
				if ( !$all_matched ) continue;
				
				return $menuitem;
			}
		}
		
		return false;
	}
	
	
	static function _findDirectory($needles, &$data)
	{
		// Get language, urlvars
		$language = $data['language'];
		$urlvars  = $data['urlvars'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		global $globalcats;
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		
			
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Now find menu item for given needles
		foreach ($needles as $view => $ids)
		{
			if ($view != 'category') continue;  // only category ids are supported by this function
			
			// Get access level of the FLEXIcontent category
			$cid = reset($ids);
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
		if ($component_menuitems === null) $component_menuitems = FlexicontentHelperRoute::_setMenuitems();
		
		$match = null;
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$user = JFactory::getUser();
		if (FLEXI_J16GE)
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
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
		foreach($needles as $view => $id)
		{
			// Prefer current menu item if pointing to given tag
			if ($menu && (@$menu->query['view'] == $view) && (@$menu->query['id'] == $id) ) {
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

				if ( (@$menuitem->query['view'] == $view) && (@$menuitem->query['id'] == $id) ) {
					$match = $menuitem;
					break;
				}
			}
			if(isset($match))  break;  // If a menu item for a tag found, do not search for next needles (tag ids)
		}

		return $match;
	}
	
	
	static function _getHomes()
	{
		$menu = JFactory::getApplication()->getMenu('site', array());
		
		// Get menu home items
		self::$lang_homes = array();
		foreach ($menu->getMenu() as $item)
		{
			if ($item->home)
			{
				self::$lang_homes[$item->language] = $item;
			}
		}
	}
	
	
	static function populateLookupTable($language)
	{
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		// Every VIEW may have a different variable for the lookup table in which we will add the menu items
		static $view_varnames = array(FLEXI_ITEMVIEW=>'id', 'category'=>'cid', 'flexicontent'=>'rootcatid');
		
		self::$lookup[$language] = array();
		$user = JFactory::getUser();
		
		foreach($component_menuitems as $menuitem)
		{
			if ( !isset($menuitem->query) || !isset($menuitem->query['view']) ) continue;  // view not set
			if ( FLEXI_J16GE && $menuitem->language != $language && $menuitem->language!='*') continue;   // wrong menu item language, neither item's language, nor '*' = ALL
			
			if ( @$menuitem->query['view'] == 'category' ) {     // CHECK if category menu items ... need to be skipped
				
				if (@$menuitem->query['layout']=='author' && $menuitem->query['authorid']!=$user->id)
					// Do not match "author" if specific author is not current user
					continue;
			
				else if (@$menuitem->query['layout']=='myitems' && !$user->id)
					// Do not match "myitems" if current user is unlogged
					continue;
				
				else if (@$menuitem->query['layout'])
					// Do not match custom layouts, limited to a category id
					continue;
				
				// Do not match menu items that override category configuration parameters, these items will be selectable only
				// (a) via direct click on the menu item or
				// (b) if their specific Itemid is passed to getCategoryRoute(), getItemRoute()
				// (c) they are currently active ...
				//if (!isset($menuitem->jparams)) $menuitem->jparams = FLEXI_J16GE ? $menuitem->params : new JParameter($menuitem->params);
				//if ( $menuitem->jparams->get('override_defaultconf',0) ) continue;
			}
			
			// Create lookup table for view if it does not exist already
			$view = $menuitem->query['view'];
			if (!isset(self::$lookup[$language][$view]))  self::$lookup[$language][$view] = array();
			
			// Check if view 's variable (used in lookup table) exists in the menu item
			if ( !isset($view_varnames[$view]) ) continue;
			$_index_name = $view_varnames[$view];
			if ( empty($menuitem->query[$_index_name]) ) continue;
			$_index_val = $menuitem->query[$_index_name];
			
			// Only a specific language menu item can override an existing lookup entry
			if ( isset(self::$lookup[$language][$view][$_index_val]) && $menuitem->language == '*' ) continue;
			
			// Finally set new lookup entry or override existing lookup entry with language specific menu item
			self::$lookup[$language][$view][$_index_val] = (int) $menuitem->id;
		}
	}
		
}
?>