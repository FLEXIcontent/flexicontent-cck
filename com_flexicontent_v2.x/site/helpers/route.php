<?php
/**
 * @version 1.5 stable $Id: route.php 1961 2014-09-20 07:49:53Z ggppdk $
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
	protected static $lang_lookup = null;
	protected static $add_url_lang  = null;
	protected static $interface_langs = null;
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
		if ($_menuitems) foreach ($_menuitems as $menuitem)
		{
			// In J1.5 filter by access levels of current user
			// In J2.5+ this is already done by JMenuSite::getItems()
			if (!FLEXI_J16GE && $menuitem->access > $user_access) continue;
			
			// Index by menu id
			self::$menuitems[$language][$menuitem->id] = $menuitem;
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
		if ( $_component_default_menuitem_id!==null ) return $_component_default_menuitem_id;
		
		// Default item not found (yet) ... set it to false to indicate that we tried
		$_component_default_menuitem_id = false;
		$curr_langtag = JFactory::getLanguage()->getTag();  // Current language tag for J2.5+ but not for J1.5
		
		
		//$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$user = JFactory::getUser();
		if (FLEXI_J16GE)
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		else
			$aid = (int) $user->get('aid');
		
		
		// NOTE: In J1.5 the static method JSite::getMenu() will give an error, while JFactory::getApplication('site')->getMenu() will not return the frontend menus
		$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
		
		
		// Get preference for default menu item
		$params = JComponentHelper::getParams('com_flexicontent');
		$default_menuitem_preference = $params->get('default_menuitem_preference', 0);
		
		switch ($default_menuitem_preference) {
		case 1:
			// Try to use ACTIVE (current) menu item if pointing to Flexicontent, (if so configure in global options)
			$menu = $menus->getActive();
			
			// Check that (a) it exists and is active (b) points to com_flexicontent
			if ($menu && @ $menu->query['option']=='com_flexicontent' )
			{
				// For J1.5 access level is already OK (since active), for J2.5+ check language
				$item_matches = !FLEXI_J16GE  ?  true  :  ($curr_langtag == '*' || in_array($menu->language, array('*', $curr_langtag)) || !JLanguageMultilang::isEnabled());
				
				// If matched set default and return it
				if ($item_matches)  return  $_component_default_menuitem_id = $menu->id;
			}
			// DO NOT BREAK HERE !! FALLBACK to 2
		
		case 2:
			// Try to use (user defined) component's default menu item, (if so configure in global options)
			$_component_default_menuitem_id = $params->get('default_menu_itemid', false);
			$menu = $menus->getItem($_component_default_menuitem_id);
			
			// Check that (a) it exists and is active (b) points to com_flexicontent
			if ($menu && @ $menu->query['option']=='com_flexicontent' )
			{
				// For J1.5 check access and for J2.5+ check language
				$item_matches = !FLEXI_J16GE  ?  ($menu->access <= $aid)  :  ($curr_langtag == '*' || in_array($menu->language, array('*', $curr_langtag)) || !JLanguageMultilang::isEnabled());
				
				// If matched set default and return it
				if ($item_matches)  return  $_component_default_menuitem_id = $menu->id;
				
				// For J2.5+ we also need to try menu item associations and select the current language item
				if ( FLEXI_J16GE && $menu->language!='*' && $menu->language!='' && $menu->language!=$curr_langtag )
				{
					require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_menus'.DS.'helpers'.DS.'menus.php');
					$helper = new MenusHelper();
					$associated = $helper->getAssociations($_component_default_menuitem_id);
								
					// Return associated menu item for current language
					if ( isset($associated[$curr_langtag]) )  $_component_default_menuitem_id = $associated[$curr_langtag];
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
		if( self::$lang_lookup !== null ) return;
		
		// Create map of: item language code to SEF URL language code
		// We don't use helper function so that we also get non-published ones
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
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
		$menus = JFactory::getApplication()->getMenu('site', array());
		
		// Get content languages and filter them to include only inteface languages
		$content_langs = JLanguageHelper::getLanguages();
		$interface_langs = array();
		
		foreach ($content_langs as $i => &$language)
		{
			// Do not display language without frontend UI
			if (!JLanguage::exists($language->lang_code))
				continue;
			
			// Do not display language without specific home menu
			elseif (!$menus->getDefault($language->lang_code))
				continue;
			
			// Do not display language without authorized access level
			elseif (isset($language->access) && $language->access && !in_array($language->access, $levels))
				continue;
			
			self::$interface_langs[$language->lang_code] = $language;
		}
		// DEBUG print the filtered languages
		//foreach (self::$interface_langs as $lang_code => $lang) echo $lang->title.'['.$lang_code.']'."<br/>\n";
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
		if ($current_language === null) {
			$current_language = JFactory::getLanguage()->getTag();  // Current language tag for J2.5+ but not for J1.5
		}
		
		static $use_language = null;
		if ($use_language === null)
		{
			$use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
			if ($use_language) {
				self::_buildLanguageLookup();
			}
		}
		
		global $globalcats;
		$_catid = (int) $catid;
		$_id = (int) $id;
		
		
		// *****************************************************************
		// Get data of the FLEXIcontent item (only if not already given)
		// including data like: type id, language, but do not do 1 SQL query
		// per item, to get the type id and language  ...  
		// for language we will use current language, for type_id, we ignore
		// *****************************************************************
		
		// Compatibility with calls not passing item data, check for item data in global object, avoiding an extra SQL call
		if ( !$item ) {
			global $fc_list_items;
			if ( !empty($fc_list_items) && isset($fc_list_items[$_id]) ) {
				$item = $fc_list_items[$_id];
			}
		}
		
		// Get language
		$language = (!FLEXI_J16GE || !$item || @!$item->language) ? $current_language : $item->language;
		
		// Get type ID
		$type_id = ($item && isset($item->type_id))? $item->type_id : 0;
		
		// Get type data
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
		
		
		// **********************************************************************************
		// Get item's parent categores to be used in search a menu item of type category view
		// **********************************************************************************
		
		$parents_ids = array();
		if ( $_catid && isset($globalcats[$_catid]->ancestorsarray) ) {
			$parents_ids = array_reverse($globalcats[$_catid]->ancestorsarray);
		}
		
		
		// **********************************************************
		// Create the needles for table lookup in descending priority
		// **********************************************************
		
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
		
		
		// ***************
		// Create the link
		// ***************
		
		// view
		$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW;
		// category id
		if ($catid) $link .= '&cid='.$catid;
		// item id
		$link .= '&id='. $id;
		
		// use SEF language code as so configured
		$data['language'] = '*';  // Default to ALL
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
		
		// Try to use component's default menu item, this is according to COMPONENT CONFIGURATION and includes ACTIVE menu item if appropriate
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
		if ($current_language === null) {
			$current_language = JFactory::getLanguage()->getTag();  // Current language tag for J2.5+ but not for J1.5
		}
		
		static $use_language = null;
		if ($use_language === null)
		{
			$use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
			if ($use_language) {
				self::_buildLanguageLookup();
			}
		}
		
		global $globalcats;
		$_catid = (int) $catid;
		
		
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
		
		
		// **********************************************************
		// Create the needles for table lookup in descending priority
		// **********************************************************
		
		$needles = array();
		
		// Category view menu items of given category ID ... and then parent categories in ascending order
		$needles['category'] = $parents_ids;
		
		// Directory view menu items starting at given category ID ... and then parent categories in ascending order
		$needles['flexicontent'] = $parents_ids;
		
		
		// ***************
		// Create the link
		// ***************
		
		$link = 'index.php?option=com_flexicontent&view=category&cid='.$catid;
		
		// Other data to pass to _findCategory()
		$data = array();
		
		// Append given variables
		foreach ($urlvars as $varname => $varval) $link .= '&'.$varname.'='.$varval;
		$data['urlvars'] = $urlvars;
		
		
		// use SEF language code as so configured
		$data['language'] = '*';  // Default to ALL
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
			// Special handly if directory view was matched
			if ( $menuitem->query['view'] == 'flexicontent' )
			{
				$link = str_replace('view=category', 'view=flexicontent', $link);
				$link = str_replace('cid=', 'rootcat=', $link);
			}
		}
		
		// Try to use component's default menu item, this is according to COMPONENT CONFIGURATION and includes ACTIVE menu item if appropriate
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
		
		// Get default menu item for 'search' view
		static $_search_default_menuitem_id = null;
		if ($_search_default_menuitem_id === null)
		{
			$params = JComponentHelper::getParams('com_flexicontent');
			$_search_default_menuitem_id = $params->get('search_view_default_menu_itemid', false);
		}
		
		
		// ***************
		// Create the link
		// ***************
		
		$link = 'index.php?option=com_flexicontent&view=search';
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		// Fallback to default menu item for the view
		else if ($_search_default_menuitem_id) {
			$link .= '&Itemid='.$_search_default_menuitem_id;
		}
		
		// Try to use component's default menu item, this is according to COMPONENT CONFIGURATION and includes ACTIVE menu item if appropriate
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
		
		// Get default menu item for 'favourites' view
		static $_favs_default_menuitem_id = null;
		if ($_favs_default_menuitem_id === null)
		{
			$params = JComponentHelper::getParams('com_flexicontent');
			$_favs_default_menuitem_id = $params->get('favs_view_default_menu_itemid', false);
		}
		
		
		// ***************
		// Create the link
		// ***************
		
		$link = 'index.php?option=com_flexicontent&view=favourites';
		
		// USE the itemid provided, if we were given one it means it is "appropriate and relevant"
		if ($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}
		
		// Fallback to default menu item for the view
		else if ($_favs_default_menuitem_id) {
			$link .= '&Itemid='.$_favs_default_menuitem_id;
		}
		
		// Try to use component's default menu item, this is according to COMPONENT CONFIGURATION and includes ACTIVE menu item if appropriate
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
		
		static $current_language = null;
		if ($current_language === null) {
			$current_language = JFactory::getLanguage()->getTag();  // Current language tag for J2.5+ but not for J1.5
		}
		
		static $use_language = null;
		if ($use_language === null)
		{
			$use_language = FLEXI_J16GE && JLanguageMultilang::isEnabled();
			if ($use_language) {
				self::_buildLanguageLookup();
			}
		}
		
		// Get default menu item for 'tags' view
		static $_tags_default_menuitem_id = null;
		if ($_tags_default_menuitem_id === null)
		{
			$params = JComponentHelper::getParams('com_flexicontent');
			$_tags_default_menuitem_id = $params->get('tags_view_default_menu_itemid', false);
		}
		
		
		// *********************
		// Get data of given TAG
		// data like: language
		// *********************
		
		// Get language, IN FUTURE tags will have language because we will use J3+ tags
		$language = '*';
		
		
		// **********************************************************
		// Create the needles for table lookup in descending priority
		// **********************************************************
		$needles = array();
		
		$needles['tags'] = array( (int) $id );
		
		
		// ***************
		// Create the link
		// ***************
		
		$link = 'index.php?option=com_flexicontent&view=tags&id='.$id;
		
		// Other data to pass to _findTag()
		$data = array();
		
		// use SEF language code as so configured
		$data['language'] = '*';  // Default to ALL
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
		else if ($menuitem = FlexicontentHelperRoute::_findTag($needles, $data)) {
			$link .= '&Itemid='.$menuitem->id;
		}
		
		// Fallback to default menu item for the view
		else if ($_tags_default_menuitem_id) {
			$link .= '&Itemid='.$_tags_default_menuitem_id;
		}
		
		// Try to use component's default menu item, this is according to COMPONENT CONFIGURATION and includes ACTIVE menu item if appropriate
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
		if ( !$needles ) return false;
		
		// Get language, item ,current (matched) category menu item
		$language = $data['language'];
		$item = $data['item'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Get current menu item, we will prefer current menu if it points to given category,
		// thus maintaining current menu item if multiple menu items to same category exist !!
		static $active = null;
		if ($active == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$active = $menus->getActive();
			if ($active && @ $active->query['option']!='com_flexicontent') $active=false;
		}
		
		
		// Now find menu item for given needles
		$matched_menu = false;
		
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
				
				// menu item matched, break out
				$matched_menu = $menuitem;
				break;
			}
		}


		// Prefer current category menu item if also appropriate
		if ($matched_menu && $active &&
			@ $matched_menu->query['view'] == 'category' &&  $active->query['view'] == 'category' &&
			@ $matched_menu->query['view'] == @ $active->query['view'] &&
			@ $matched_menu->query['cid'] == @ $active->query['cid']
		) {
			$matched_menu = $active;
		}
		
		return $matched_menu;
	}
	
	
	static function _findCategory($needles, &$data)
	{
		if ( !$needles ) return false;
		
		// Get language, url variables
		$language = $data['language'];
		$urlvars  = $data['urlvars'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Get current menu item, we will prefer current menu if it points to given category,
		// thus maintaining current menu item if multiple menu items to same category exist !!
		static $active = null;
		if ($active == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$active = $menus->getActive();
			if ($active && @ $active->query['option']!='com_flexicontent') $active=false;
		}
		
		
		// Now find menu item for given needles
		$matched_menu = false;
		
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
				
				if ( @ $menuitem->query['view'] == 'category' )
				{
					// Try to match important menu / url variables. NOTE: view and cid variables were already matched via table lookup
					// This step is needed because of our category view rendering various type of content listings ...
					if (
						// these variables must be explicitely checked, we must not rely on the urlvars loop below !
						@ $menuitem->query['layout'] != @$urlvars['layout'] || // match layout "author", "myitems", "mcats", "favs", "tags" etc
						@ $menuitem->query['authorid'] != @$urlvars['authorid'] || // match "authorid" for user id of author
						@ $menuitem->query['cids'] != @$urlvars['cids'] || // match "cids" of "mcats" (multi-category view)
						@ $menuitem->query['tagid'] != @$urlvars['tagid'] // match "tagid" of "tags" view
					) continue;
					
					// IF extra URL vars were specified (and thus are required) then try to match them
					$vars_matched = true;
					foreach ($urlvars as $varname => $varval) {
						$vars_matched = $vars_matched &&  (@$menuitem->query[$varname] == $varval);
					}
					if ( !$vars_matched ) continue;
				}
				
				// menu item matched, break out
				$matched_menu = $menuitem;
				break;
			}
		}
		
		// Prefer current category menu item if also appropriate
		if ($matched_menu && $active && @ $matched_menu->query['view'] == 'category' &&
			@ $matched_menu->query['view'] == @ $active->query['view'] &&
			@ $matched_menu->query['cid'] == @ $active->query['cid'] &&
			@ $matched_menu->query['layout'] == @ $active->query['layout'] &&
			@ $matched_menu->query['authorid'] == @ $active->query['authorid'] &&
			@ $matched_menu->query['cids'] == @ $active->query['cids'] &&
			@ $matched_menu->query['tagid'] == @ $active->query['tagid']
		) {
			$matched_menu = $active;
		}
		
		return $matched_menu;
	}
	
		
	static function _findTag($needles, &$data)
	{
		if ( !$needles ) return false;
		
		// Get language, item, current (matched) category menu item
		$language = $data['language'];
		
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		
		// *****************************************************************************************************************
		// Done ONCE per language: Iterate through menu items pointing to FLEXIcontent component, to create a reverse lookup
		// table for the given language, not if given language is missing the an '*' menu item will be allowed in its place
		// *****************************************************************************************************************
		if ( !isset(self::$lookup[$language]) ) {
			FlexicontentHelperRoute::populateLookupTable($language);
		}
		
		
		// Get current menu item, we will prefer current menu if it points to given category,
		// thus maintaining current menu item if multiple menu items to same category exist !!
		static $active = null;
		if ($active == null) {
			$menus = JFactory::getApplication()->getMenu('site', array());   // this will work in J1.5 backend too !!!
			$active = $menus->getActive();
			if ($active && @ $active->query['option']!='com_flexicontent') $active=false;
		}
		
		
		// Now find menu item for given needles
		$matched_menu = false;
		
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
				
				// menu item matched, break out
				$matched_menu = $menuitem;
				break;
			}
		}
		
		// Prefer current tags menu item if also appropriate
		if ($matched_menu && $active && @ $matched_menu->query['view'] == 'tags' &&
			@ $matched_menu->query['view'] == @ $active->query['view'] &&
			@ $matched_menu->query['id'] == @ $active->query['id']
		) {
			$matched_menu = $active;
		}
		
		return $matched_menu;
	}
	
	
	static function populateLookupTable($language)
	{
		// Set language menu items if not already done
		if ( !isset(self::$menuitems[$language]) ) {
			FlexicontentHelperRoute::_setMenuitems($language);
		}
		$component_menuitems = & self::$menuitems[$language];
		
		// Every VIEW may have a different variable for the lookup table in which we will add the menu items
		static $view_varnames = array(FLEXI_ITEMVIEW=>'id', 'category'=>'cid', 'tags'=>'id', 'flexicontent'=>'rootcatid');
		
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
			if ( isset(self::$lookup[$language][$view][$_index_val]) && FLEXI_J16GE && $menuitem->language == '*' ) continue;
			
			// Finally set new lookup entry or override existing lookup entry with language specific menu item
			self::$lookup[$language][$view][$_index_val] = (int) $menuitem->id;
		}
	}
		
}
?>