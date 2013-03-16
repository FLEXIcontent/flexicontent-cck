<?php
/**
 * @version 1.5 stable $Id$
 * @plugin 1.0.2
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

jimport( 'joomla.plugin.plugin' );
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

/**
 * System plugin for advanced FLEXIcontent routing
 */
class plgSystemFlexiadvroute extends JPlugin
{
	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$extension_name = 'com_flexicontent';
		//JPlugin::loadLanguage($extension_name, JPATH_SITE);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, 'en-GB'	, true);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, null		, true);
	}
	
	/**
	 * Do load rules and start checking function
	 */
	function onAfterInitialise()
	{
		global $globalnopath, $globalnoroute;

		$mainframe = JFactory::getApplication();
		
		if ($mainframe->isAdmin()) {
			return; // Dont run in admin
		}
		
		// Hide category names from pathway/url
		$route_to_type 		= $this->params->get('route_to_type', 0);
		$type_to_route 		= $this->params->get('type_to_route', '');
		if ($route_to_type)
		{
			$globalnopath = $type_to_route;
			if ( empty($type_to_route) )							$globalnopath = array();
			else if ( ! is_array($type_to_route) )		$globalnopath = !FLEXI_J16GE ? array($type_to_route) : explode("|", $type_to_route);
		} else {
			$globalnopath = array();
		}
		
		// Hide category links
		$cats_to_exclude 	= $this->params->get('cats_to_exclude', '');
		$globalnoroute = $cats_to_exclude;
		if ( empty($cats_to_exclude) )							$globalnoroute = array();
		else if ( ! is_array($cats_to_exclude) )		$globalnoroute = !FLEXI_J16GE ? array($cats_to_exclude) : explode("|", $cats_to_exclude);
	}
	
	
	function onAfterRoute( $args=null )
	{
		$this->switchLangAssocItem($args);
	}
	
	
	function switchLangAssocItem( $args=null )
	{
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$app = JFactory::getApplication();
		$session = JFactory::getSession();
		
		// Execute only in frontend
		if( $app->isAdmin() )  return;
		
		// Execute only if groups enabled and if plugin parameter for switching is enabled
		if ( !$flexiparams->get('enable_translation_groups') || !$this->params->get('switch_langassociated_items', 1) )  return;
		
		// Execute only if some languange switcher is available
		if ( !FLEXI_J16GE && !FLEXI_FISH )  return;
		
		// Execute only if not previewing
		if (JRequest::getVar('preview') || ( JRequest::getVar('fcu') && JRequest::getVar('fcp') ) ) return;
		
	  // Get current user language
		$cntLang   = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
		$urlLang   = JRequest::getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
		$curr_lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
		
	  // Get variables
	  if (FLEXI_FISH)
	  {
	  	$view   = JRequest::getVar('view');
		  $option = JRequest::getVar('option');
		  $task   = JRequest::getVar('task');
		  $item_slug = JRequest::getVar('id');
		  $item_id   = (int) $item_slug;
		  
		  // Execute only if current page is a FLEXIcontent items view (viewing an individual item)
		  if ( $view != FLEXI_ITEMVIEW || $option!='com_flexicontent' ) return;
		}
		else if (FLEXI_J16GE)
		{
			$menus = $app->getMenu('site', array());
			$curr_menu = $menus->getActive();
			$curr_menu_isitem = @$menu->query['option']=='com_flexicontent' && @$menu->query['view']==FLEXI_ITEMVIEW && @$menu->query['id']==(int)JRequest::getVar('id');
			$curr_menu_iscat  = @$menu->query['option']=='com_flexicontent' && @$menu->query['view']=='category' && @$menu->query['cid']==(int)JRequest::getVar('cid');
			
			$flexi_advroute_url = $session->get('flexi_advroute_url');
		  $view       = @$flexi_advroute_url['view'];
		  $option     = @$flexi_advroute_url['option'];
		  $task       = @$flexi_advroute_url['task'];
		  $cat_slug   = @$flexi_advroute_url['cid'];
		  $item_slug  = @$flexi_advroute_url['id'];
		  
		  $prev_lang_tag    = @$flexi_advroute_url['lang_tag'];
		  $prev_page_ishome = @$flexi_advroute_url['ishome'];
		  $prev_menu_id     = @$flexi_advroute_url['menu_id'];
		  $prev_menu_isitem = @$flexi_advroute_url['menu_isitem'];
		  $prev_menu_iscat  = @$flexi_advroute_url['menu_iscat'];
		  
		  $curr_lang_tag    = JFactory::getLanguage()->getTag();
			$curr_page_ishome = $this->detectHomepage();
			$curr_menu_id     = @$curr_menu->id;
			
			if ($this->params->get('debug_lang_switch', 0)) {
				//$app->enqueueMessage( "Previous Page is HOME: $prev_page_ishome, &nbsp; "."Previous menu item ID: $prev_menu_id<br>", 'message');
				//$app->enqueueMessage( "Current Page is HOME: $curr_page_ishome, &nbsp; "."Current menu item ID: $curr_menu_id<br>", 'message');
				//$app->enqueueMessage( "Previous language $prev_lang_tag && Current language: $curr_lang_tag<br><br>", 'message');
			}
			
			// Set variables for next function call (next page load)
		  $flexi_advroute_url['view']    = JRequest::getVar('view');
		  $flexi_advroute_url['option']  = JRequest::getVar('option');
		  $flexi_advroute_url['task']    = JRequest::getVar('task');
		  $flexi_advroute_url['id']      = JRequest::getVar('id');
		  $flexi_advroute_url['cid']     = JRequest::getVar('cid');
		  $flexi_advroute_url['lang_tag']= $curr_lang_tag;
		  $flexi_advroute_url['ishome']  = $curr_page_ishome;
		  $flexi_advroute_url['menu_id'] = $curr_menu_id;
		  $flexi_advroute_url['menu_isitem']= $curr_menu_isitem;
		  $flexi_advroute_url['menu_iscat'] = $curr_menu_iscat;
		  
		  $session->set('flexi_advroute_url', $flexi_advroute_url);
		  
			// Detect if already redirected once, this code must be after the code setting the 'flexi_advroute_url' session variable
			if ( $session->get('flexi_lang_switched') ) {
				$session->set('flexi_lang_switched', 0);
				return;
			}
			
			// Indentify previous and current flexicontent view
			$prev_page_isitemview = $view==FLEXI_ITEMVIEW && $option=='com_flexicontent';
			$prev_page_iscatview  = $view=='category'     && $option=='com_flexicontent';
			$curr_page_isitemview = JRequest::getVar('view')==FLEXI_ITEMVIEW && JRequest::getVar('option')=='com_flexicontent';
			$curr_page_iscatview  = JRequest::getVar('view')=='category'     && JRequest::getVar('option')=='com_flexicontent';
			
			// Execute only if previous page was a FLEXIcontent item or category view
			if ( !$prev_page_isitemview && !$prev_page_iscatview ) return;
			
			// Calculate flags needed to decide action to take
			$language_changed     = $prev_lang_tag!=$curr_lang_tag;
			$prev_page_isflexi    = $prev_page_isitemview || $prev_page_iscatview;
			$switching_language   = $prev_page_isflexi && $curr_page_ishome && $language_changed;
			
			// Check Joomla switching language for (a) Home Page menu items OR (b) other language associated menu items
			if ( $curr_menu && $curr_menu->language!='*' && $curr_menu->language!=$prev_lang_tag )
			{
				// (a) Home Page menu items
				if ($prev_page_ishome && $curr_page_ishome)
				{
					if ($this->params->get('debug_lang_switch', 0))
						$app->enqueueMessage( "Joomla language switched Home Page menu items<br><br>", 'message');
					$switching_language = false;
				}
				
				// (b) Other language associated menu items
				else
				{
					// Get menu item associations for previously activated menu item
					require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_menus'.DS.'helpers'.DS.'menus.php');
					$helper = new MenusHelper();
					$associated = $helper->getAssociations($prev_menu_id);
					
					if ( isset($associated[$curr_lang_tag]) && $associated[$curr_lang_tag] == $curr_menu_id ) {
						if ($this->params->get('debug_lang_switch', 0))
							$app->enqueueMessage( "Associated menu for $prev_menu_id: ".implode(',', $associated)."<br>" , 'message');
						
						if ( ($prev_page_isitemview && !$prev_menu_isitem) || ($prev_page_iscatview && !$prev_menu_iscat) ){
							if ($this->params->get('debug_lang_switch', 0))
								$app->enqueueMessage( "Joomla language switched associated menu items that did not point to current content: Doing FLEXI switch<br>", 'message');
							$switching_language = true;
						} else {
							if ($this->params->get('debug_lang_switch', 0))
								$app->enqueueMessage( "Joomla language switched associated menu items: that do point to current content: Aborting FLEXI switch<br><br>", 'message');
							$switching_language = false;
						}
					}
				}
			}
			
			/*echo "<br>prev_page_isflexi: $prev_page_isflexi, curr_page_ishome: $curr_page_ishome,"
					."<br>language_changed: $language_changed,"
					."<br>prev_page_isitemview: $prev_page_isitemview, prev_page_iscatview: $prev_page_iscatview";
					."<br>curr_page_isitemview: $curr_page_isitemview, curr_page_iscatview: $curr_page_iscatview";*/
		 	
		  // Decide to execute switching:
			if ( !$switching_language &&  // (a) if previous page was a FLEXIcontent view (item or category view) and we switched language
					 !$curr_page_isitemview   // (b) if current page is FLEXIcontent item in order to check if language specified is not that of item's language
			) return;
			
			$cat_id  = (int) ($switching_language ? $cat_slug  : JRequest::getVar('cid'));
		  $item_id = (int) ($switching_language ? $item_slug : JRequest::getVar('id'));
		  $view    = $switching_language ? $view    : JRequest::getVar('view');
		  $option  = $switching_language ? $option  : JRequest::getVar('option');
		  $task    = $switching_language ? $task    : JRequest::getVar('task');
		}
		
		if ( FLEXI_J16GE && $view=='category' ) {  
		  // Execute only if category id is set, and switching for categories is enabled
			if (!$cat_id) return;
			if (!$this->params->get('lang_switch_cats', 1)) return;
			
			if ($this->params->get('debug_lang_switch', 0))
				$app->enqueueMessage( "*** Language switching category no: $cat_id<br><br>", 'message');
			
			$session->set('flexi_lang_switched', 1);
			$cat_url = JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat_slug).'&lang='.$curr_lang );
			$app->redirect( $cat_url );
		}
		
	  if (!$item_id) return;       // Execute only if item id is set
	  if ($task=="edit") return;   // Execute only if not in item edit form
		if (!$this->params->get('lang_switch_items', 1)) return;   // Execute only if switching for items is enabled
		
	  // Execute only when not doing a task (e.g. edit)          BROKEN !!! DISABLED
	  //if ( !empty(JRequest::getVar('task')) ) return;
		
	  // Get associated translating item for current language
	  $db = JFactory::getDBO();
	  $query = "SELECT i.id, CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(':', i.id, i.alias) ELSE i.id END as slug"
	  . " FROM #__content AS i "
	  . " LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id "
	  . " WHERE ie.language LIKE ".$db->Quote( $curr_lang .'%' )." AND ie.lang_parent_id = (SELECT lang_parent_id FROM #__flexicontent_items_ext WHERE item_id=".(int) $item_id.")";
	  ;
	  $db->setQuery($query);
	  $translation = $db->loadObject();
	  if( $db->getErrorNum() ) { $app->enqueueMessage( $db->getErrorMsg(), 'warning'); }
	  
	  if ( !$translation || $item_id==$translation->id ) return;  // No associated item translation found
		
		if ($this->params->get('debug_lang_switch', 0))
			$app->enqueueMessage( "*** Found translation of item {$item_id} for language $curr_lang. <br>Translating item is {$translation->id}<br><br>", 'message');
		
		if (FLEXI_J16GE) {
			$item_slug = $translation->slug;
			$item_url = JRoute::_( FlexicontentHelperRoute::getItemRoute($item_slug,$cat_slug).'&lang='.$curr_lang );
			$session->set('flexi_lang_switched', 1);
			$app->redirect( $item_url );
		} else {
		  JRequest::setVar('id', $translation->id);
		}
	}
	
	
	function detectHomepage()
	{
		if (FLEXI_J16GE) {
			$app = JFactory::getApplication();
			$menu = $app->getMenu();
			$lang = JFactory::getLanguage();
			$isHomePage = $menu->getActive() == $menu->getDefault($lang->getTag());
		} else {
			$menu = JSite::getMenu();
			$isHomePage = $menu->getActive() == $menu->getDefault();
		}
		return $isHomePage;
	}
	
}