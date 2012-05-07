<?php
/**
 * @version 1.5 stable $Id: flexiadvroute.php 546 2011-03-27 01:26:00Z emmanuel.danan@gmail.com $
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
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

//$app = JFactory::getApplication();
//$app->registerEvent('onAfterRoute', 'switchLangAssocItem');

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
		JPlugin::loadLanguage('com_flexicontent', JPATH_SITE);
	}
	
	/**
	 * Do load rules and start checking function
	 */
	function onAfterInitialise()
	{
		global $globalnopath, $globalnoroute;

		$mainframe =& JFactory::getApplication();
		
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
		$flexiparams =& JComponentHelper::getParams('com_flexicontent');
		$app =& JFactory::getApplication();
		$session  =& JFactory::getSession();
		
		// Execute only in frontend
		if( $app->isAdmin() )  return;
		
		// Execute only if groups enabled and if plugin parameter for switching is enabled
		if ( !$flexiparams->get('enable_language_groups') || !$this->params->get('switch_langassociated_items', 1) )  return;
		
		// Execute only if some languange switcher is available
		if ( !FLEXI_J16GE && !FLEXI_FISH )  return;
		
	  // Get current user language
		$currlang = JRequest::getWord('lang', '' ) ? JRequest::getWord('lang', '' ) : substr(JFactory::getLanguage()->getTag() ,0,2);
		
	  // Get variables
	  if (FLEXI_FISH)
	  {
	  	$view = JRequest::getVar('view');
		  $option = JRequest::getVar('option');
		  $item_slug = JRequest::getVar('id');
		  
		  // Execute only if current page is a FLEXIcontent items view (viewing an individual item)
		  if ( $view != FLEXI_ITEMVIEW || $option!='com_flexicontent' || !$item_id ) return;
		  $item_id = (int) $item_slug;
		}
		else if (FLEXI_J16GE)
		{
			$flexi_advroute_url = $session->get('flexi_advroute_url');
		  $view       = $flexi_advroute_url['view'];
		  $option     = $flexi_advroute_url['option'];
		  $cat_slug   = $flexi_advroute_url['cid'];
		  $item_slug  = $flexi_advroute_url['id'];
		  $prevlang   = $flexi_advroute_url['lang'];
		  
		  $flexi_advroute_url['view'] = JRequest::getVar('view');
		  $flexi_advroute_url['option'] = JRequest::getVar('option');
		  $flexi_advroute_url['id'] = JRequest::getVar('id');
		  $flexi_advroute_url['cid'] = JRequest::getVar('cid');
		  $flexi_advroute_url['lang'] = $currlang;
		  $session->set('flexi_advroute_url', $flexi_advroute_url);
		  
			// Detect if already redirected once, this code must be after the code setting the 'flexi_advroute_url' session variable
			if ( $session->get('flexi_lang_switched') ) {
				$session->set('flexi_lang_switched', 0);
				return;
			}
			
			$prev_page_isitemview = $view==FLEXI_ITEMVIEW && $option=='com_flexicontent';
			$prev_page_iscatview  = $view=='category'     && $option=='com_flexicontent';
			$curr_page_isitemview = JRequest::getVar('view')==FLEXI_ITEMVIEW && JRequest::getVar('option')=='com_flexicontent';
			$curr_page_iscatview  = JRequest::getVar('view')=='category'     && JRequest::getVar('option')=='com_flexicontent';
			
			$language_changed     = $prevlang!=$currlang;
			$curr_page_ishome     = $this->detectHomepage();
			$prev_page_isflexi    = $prev_page_isitemview || $prev_page_iscatview;
			
			$switching_language   = $prev_page_isflexi && $curr_page_ishome && $language_changed;
			$check_currpage       = ($curr_page_iscatview && $language_changed) || $curr_page_isitemview;
			
			/*echo "<br>prev_page_isflexi: $prev_page_isflexi, curr_page_ishome: $curr_page_ishome,"
					."<br>language_changed: $language_changed,"
					."<br>prev_page_isitemview: $prev_page_isitemview, prev_page_iscatview: $prev_page_iscatview";
					."<br>curr_page_isitemview: $curr_page_isitemview, curr_page_iscatview: $curr_page_iscatview";*/
		 
		  // Execute only if previous page was FLEXIcontent item or category view
			if ( !$switching_language && !$check_currpage ) return;
			
			$cat_id  = (int) ($switching_language ? $cat_slug  : JRequest::getVar('cid'));
		  $item_id = (int) ($switching_language ? $item_slug : JRequest::getVar('id'));
		  $view    = $switching_language ? $view      : JRequest::getVar('view');
		}
		
		if ( FLEXI_J16GE && $view=='category' ) {  
		  // Execute only if category id is set, and switching for categories is enabled
			if (!$cat_id) return;
			if (!$this->params->get('lang_switch_cats', 1)) return;
			
			$session->set('flexi_lang_switched', 1);
			$cat_url = JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat_slug).'&lang='.$currlang );
			$app->redirect( $cat_url );
		}
		
	  // Execute only if item id is set, and switching for items is enabled
	  if (!$item_id) return;
		if (!$this->params->get('lang_switch_items', 1)) return;
		
	  // Execute only when not doing a task (e.g. edit)          BROKEN !!! DISABLED
	  //if ( !empty(JRequest::getVar('task')) ) return;
		
	  // Get translations of the item
	  $db =& JFactory::getDBO();
	  $query = "SELECT i.id, CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(':', i.id, i.alias) ELSE i.id END as slug"
	  . " FROM #__content AS i "
	  . " LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id "
	  . " WHERE ie.language LIKE ".$db->Quote( $currlang .'%' )." AND ie.lang_parent_id = (SELECT lang_parent_id FROM #__flexicontent_items_ext WHERE item_id=".(int) $item_id.")";
	  ;
	  $db->setQuery($query);
	  $translation = $db->loadObject();
	  if( $db->getErrorNum() ) { $app->enqueueMessage( $db->getErrorMsg(), 'warning'); }
	  
	  if ( !$translation || $item_id==$translation->id ) return;
		
		if ($this->params->get('debug_lang_switch', 0)) {
			$app->enqueueMessage( "Found translation for language $currlang.<br>Item id: {$translation->id}<br>", 'message');
		}
		if (FLEXI_J16GE) {
			$item_slug = $translation->slug;
			$item_url = JRoute::_( FlexicontentHelperRoute::getItemRoute($item_slug,$cat_slug).'&lang='.$currlang );
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
			$menu = & JSite::getMenu();
			$isHomePage = $menu->getActive() == $menu->getDefault();
		}
		return $isHomePage;
	}
	
}