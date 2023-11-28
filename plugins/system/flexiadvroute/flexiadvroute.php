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

jimport('cms.plugin.plugin');
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

/**
 * System plugin for advanced FLEXIcontent routing
 */
class plgSystemFlexiadvroute extends JPlugin
{
	var $extension;  // Component name
	var $autoloadLanguage = false;

	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );

		static $language_loaded = null;
		if (!$this->autoloadLanguage && $language_loaded === null) $language_loaded = JPlugin::loadLanguage('plg_system_flexiadvroute', JPATH_ADMINISTRATOR);

		$this->extension = 'com_flexicontent';
	}


	/**
	 * Joomla initialized, but component has not been decided yet, this is good place to some actions regardless of component
	 * OR to make early redirections OR to alter variables used to do routing (deciding the component that will be executed)
	 */
	function onAfterInitialise()
	{
		global $globalnopath, $globalnoroute;
		$app = JFactory::getApplication();

		// Dont run in admin
		if ($app->isClient('administrator'))
		{
			return;
		}

		// **********************************************************
		// Create global objects of non routable categories and types
		// **********************************************************

		// Hide Categories from Pathway/URLs for given Types
		// - These are types that contain content not being a part of structure but rather general information content like site usage instructions or license agreement, etc
		$route_to_type  = $this->params->get('route_to_type', 0);
		$type_to_route  = $this->params->get('type_to_route', '');
		if ($route_to_type)
		{
			$globalnopath = $type_to_route;
			if ( empty($type_to_route) )							$globalnopath = array();
			else if ( ! is_array($type_to_route) )		$globalnopath = explode("|", $type_to_route);
		}
		else
		{
			$globalnopath = array();
		}

		// Hide categories in Content / Content Listings by NOT displaying
		//  a. Direct category links 
		//  b. Category title as a content/content list markup 
		// - These categories are for special purposes, e.g. contain items displayed in frontpage or in a module Slideshow
		$cats_to_exclude = $this->params->get('cats_to_exclude', '');
		$globalnoroute   = $cats_to_exclude;
		if ( empty($cats_to_exclude) )							$globalnoroute = array();
		else if ( ! is_array($cats_to_exclude) )		$globalnoroute = explode("|", $cats_to_exclude);
	}


	/*function detectHomepage()
	{
		$menu = JFactory::getApplication()->getMenu();
		if ($menu)
		{
			$lang = JFactory::getLanguage();
			$isHomePage = $menu->getActive() == $menu->getDefault($lang->getTag());
		}
		else
		{
			$isHomePage = false;
		}
		return $isHomePage;
	}*/
}