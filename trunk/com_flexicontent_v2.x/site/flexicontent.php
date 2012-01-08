<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 898 2011-09-10 15:44:47Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

// Get component parameters and add tooltips css and js code
$params =& JComponentHelper::getParams('com_flexicontent');
if ($params->get('add_tooltips', 1)) JHTML::_('behavior.tooltip');

//include constants file
require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'defineconstants.php');
//include the route helper
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'route.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.helper.php');
if (FLEXI_J16GE)
	require_once (JPATH_COMPONENT.DS.'helpers'.DS.'permission.php');
else
	require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

$task = JRequest::getCmd('task');
$tasks = explode(".", $task);
if(count($tasks)>=2) {
	$controller = $controller?$controller:$tasks[0];
	$task = $tasks[1];
	JRequest::setVar('task', $tasks[1]);
} else {
	$controller = JRequest::getWord('controller');
}

// Require specific controller if requested
if($controller) {
	$path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
	if (file_exists($path)) {
		require_once $path;
	} else {
		$controller = '';
	}
}

// Create the controller
$classname	= 'FlexicontentController'.ucfirst($controller);
$controller = new $classname( );

// Perform the Request task
$controller->execute($task);

// Redirect if set by the controller
$controller->redirect();

// TO BE MOVED TO HELPER FILE ...
// Remove (thus prevent) the default menu item from showing in the pathway
if ( $params->get('default_menuitem_nopathway',1) ) {
	$mainframe = & JFactory::getApplication();
	$pathway 	= & $mainframe->getPathWay();
	$default_menu_itemid = $params->get('default_menu_itemid', 0);
	$pathway_arr = $pathway->getPathway();
	if ( count($pathway_arr) && preg_match("/Itemid=([0-9]+)/",$pathway_arr[0]->link, $matches) ) {
		if ($matches[1] == $default_menu_itemid) {
			array_shift ($pathway_arr);
			$pathway->setPathway($pathway_arr);
			$pathway->set('_count',count($pathway_arr));
		}
	}
}
?>