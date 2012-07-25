<?php
/**
 * @version 1.5 stable $Id: admin.flexicontent.php 1264 2012-05-04 15:55:52Z ggppdk $ 
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

// load english language file for 'com_content' component then override with current language file
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

// load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

if(!function_exists('FLEXISubmenu')) {
	function FLEXISubmenu($cando)
	{
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanCats		= $permission->CanCats;
			$CanTypes		= $permission->CanTypes;
			$CanFields	= $permission->CanFields;
			$CanTags		= $permission->CanTags;
			$CanAuthors	= $permission->CanAuthors;
			$CanArchives= $permission->CanArchives;
			$CanFiles		= $permission->CanFiles;
			$CanStats		= $permission->CanStats;
			$CanRights	= $permission->CanConfig;
			$CanTemplates = $permission->CanTemplates;
			$CanFiles		= $permission->CanFiles;
			$CanImport	= $permission->CanImport;
			$CanIndex		= $permission->CanIndex;
		} else if (FLEXI_ACCESS) {
			$user = & JFactory::getUser();
			$CanCats 			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanTypes 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1;
			$CanFields 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1;
			$CanTags 			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1;
			$CanAuthors	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_users',        'manage', 'users', $user->gmid) : 1;
			$CanArchives	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
			$CanFiles	 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$CanStats	 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1;
			$CanRights		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess',  'manage', 'users', $user->gmid) : 1;
			$CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;
			$CanImport		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'import', 'users', $user->gmid) : 1;
			$CanIndex			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'index', 'users', $user->gmid) : 1;
			//$CanEditPublished = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editpublished', 'users', $user->gmid) : 1;
			//echo "<br>CanEditPublished: $CanEditPublished<br>";
		} else {
			$CanCats 			= 1;
			$CanTypes 		= 1;
			$CanFields		= 1;
			$CanTags 			= 1;
			$CanArchives	= 1;
			$CanFiles			= 1;
			$CanStats			= 1;
			$CanRights		= 1;
			$CanTemplates	= 1;
			$CanAuthors 	= 1;
			$CanImport		= 1;
			$CanIndex			= 1;
		}
		
		$authorized = FLEXI_J16GE ? (isset($permission->$cando) && !$permission->$cando) : (isset($$variable) && !$$variable);
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
			if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
			if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
			if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
			if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
			if ($CanAuthors && !FLEXI_J16GE)
				JSubMenuHelper::addEntry( JText::_( 'FLEXI_AUTHORS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
			//if ($CanArchives) JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
			if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
			if ($CanIndex)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEX' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
			if ($CanTemplates)JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
			if ($CanImport)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
			if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
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