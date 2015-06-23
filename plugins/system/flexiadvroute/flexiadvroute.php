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
			else if ( ! is_array($type_to_route) )		$globalnopath = explode("|", $type_to_route);
		} else {
			$globalnopath = array();
		}
		
		// Hide category links
		$cats_to_exclude 	= $this->params->get('cats_to_exclude', '');
		$globalnoroute = $cats_to_exclude;
		if ( empty($cats_to_exclude) )							$globalnoroute = array();
		else if ( ! is_array($cats_to_exclude) )		$globalnoroute = explode("|", $cats_to_exclude);
	}
	
	
	function onAfterRoute( $args=null )
	{
	}
	
	
	function detectHomepage()
	{
		$app = JFactory::getApplication();
		$menu = $app->getMenu();
		$isHomePage = false;
		if ($menu) {
			$lang = JFactory::getLanguage();
			$isHomePage = $menu->getActive() == $menu->getDefault($lang->getTag());
		}
		return $isHomePage;
	}
}