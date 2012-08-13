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

//include constants file
require_once (JPATH_COMPONENT.DS.'defineconstants.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'permission.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// load english language file for 'com_content' component then override with current language file
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

// load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

if(!function_exists('FLEXISubmenu')) {
	function FLEXISubmenu($cando)
	{
		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$perms = FlexicontentHelperPerm::getPerm();
			//$CanEditPublished = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editpublished', 'users', $user->gmid) : 1;
			//echo "<br>CanEditPublished: $CanEditPublished<br>";
		} else {
			$perms->CanCats     = 1;
			$perms->CanTypes    = 1;
			$perms->CanFields   = 1;
			$perms->CanTags     = 1;
			$perms->CanArchives = 1;
			$perms->CanFiles    = 1;
			$perms->CanStats    = 1;
			$perms->CanRights   = 1;
			$perms->CanTemplates= 1;
			$perms->CanAuthors  = 1;
			$perms->CanImport   = 1;
			$perms->CanIndex    = 1;
		}
		
		$authorized = isset($perms->$cando) && !$perms->$cando;
		if ($authorized) {
			$mainframe = &JFactory::getApplication();
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		$session  =& JFactory::getSession();
		$dopostinstall = $session->get('flexicontent.postinstall');
		$view = JRequest::getVar('view', 'flexicontent');
		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');
		// ensures the PHP version is correct
		if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
		{
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items');
			if ($perms->CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
			if ($perms->CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
			if ($perms->CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
			if ($perms->CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
			if ($perms->CanAuthors && !FLEXI_J16GE)
				JSubMenuHelper::addEntry( JText::_( 'FLEXI_AUTHORS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
			//if ($perms->CanArchives) JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
			if ($perms->CanFiles) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
			if ($perms->CanIndex)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEX' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
			if ($perms->CanTemplates) JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
			if ($perms->CanImport)	JSubMenuHelper::addEntry( JText::_( 'FLEXI_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
			if ($perms->CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
		}
	}
}

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

// SECURITY CONCERN !!!: Force permission checking by requiring to go through the custom controller when view is specified
$controller = JRequest::getWord( 'controller' );
$view = JRequest::getWord( 'view' );
$view2ctrl = array('type'=>'types', 'item'=>'items', 'field'=>'fields', 'tag'=>'tags', 'category'=>'categories', 'user'=>'users');

if( !$controller && isset($view2ctrl[$view]) ) {
	JRequest::setVar('controller', $view2ctrl[$view]);
	$task = JRequest::getWord( 'task' );
	if ( !$task ) {
		JRequest::setVar('task', 'edit');
	}
}

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