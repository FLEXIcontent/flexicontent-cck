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
		global $globaltypes, $globalitems, $globalnoroute;

		$mainframe =& JFactory::getApplication();
		
		if ($mainframe->isAdmin()) {
			return; // Dont run in admin
		}

		$route_to_type 		= $this->params->get('route_to_type', 0);
		$type_to_route 		= $this->params->get('type_to_route', '');
		$cats_to_exclude 	= $this->params->get('cats_to_exclude', '');
		
		$globalnoroute = explode(",", $cats_to_exclude);

		if ($route_to_type && $type_to_route)
		{
			if (!$globaltypes) {
				$db =& JFactory::getDBO();
				$db->setQuery('SELECT c.id FROM #__content AS c LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = c.id WHERE ie.type_id = '.$type_to_route.' ORDER BY c.id ASC');
				$globaltypes = $db->loadResultArray();
			}
	
			if (!$globalitems) {
				// get an object of all contents with their associated type and primary category
				$db =& JFactory::getDBO();
				$db->setQuery('SELECT c.id, c.catid, ie.type_id FROM #__content AS c LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = c.id WHERE ie.type_id = '.$type_to_route.' ORDER BY c.catid ASC');
				$globalitems = $db->loadObjectList('catid');
			}
		}
	}
	
}