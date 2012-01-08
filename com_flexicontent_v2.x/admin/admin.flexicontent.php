<?php
/**
 * @version 1.5 stable $Id: admin.flexicontent.php 1078 2011-12-31 14:02:09Z enjoyman@gmail.com $ 
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

//include constants file
require_once (JPATH_COMPONENT.DS.'defineconstants.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.categories.php');
if (FLEXI_J16GE)
	require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'permission.php');
else
	require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.acl.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');
//load language file com_content component
JPlugin::loadLanguage('com_content', JPATH_ADMINISTRATOR);

if(!function_exists('FLEXISubmenu')) {
	function FLEXISubmenu($cando) {
		$permission = FlexicontentHelperPerm::getPerm();
		if (isset($permission->$cando) && !$permission->$cando) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		$session  =& JFactory::getSession();
		$dopostinstall = $session->get('flexicontent.postinstall');
		$view = JRequest::getVar('view');
		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view||($view=='flexicontent'));
		// ensures the PHP version is correct
		if (FLEXI_CAT_EXTENSION && $dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>')) {
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', ($view=='items'));
			if ($permission->CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', ($view=='types'));
			if ($permission->CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', ($view=='categories'));
			if ($permission->CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', ($view=='fields'));
			if ($permission->CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', ($view=='tags'));
			if ($permission->CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', ($view=='archive'));
			if ($permission->CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', ($view=='filemanager'));
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEX' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
			if ($permission->CanTemplates) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', ($view=='templates'));
			if ($permission->CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', ($view=='stats'));
		}
	}
}

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

if (FLEXI_J16GE) {
	//Create the controller
	$controller	= JController::getInstance('Flexicontent');
} else {
	// Require specific controller if requested
	if( $controller = JRequest::getWord('controller') ) {
		$path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
		if (file_exists($path)) {
			require_once $path;
		} else {
			$controller = '';
		}
	}
	
	//Create the controller
	$classname  = 'FlexicontentController'.$controller;
	$controller = new $classname( );
}

// Perform the Request task
$controller->execute( JRequest::getCmd('task') );

// Redirect if set by the controller
$controller->redirect();
?>