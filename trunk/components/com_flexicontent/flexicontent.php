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

defined( '_JEXEC' ) or die( 'Restricted access' );

$lhlist = array('localhost', '127.0.0.1');
if( in_array($_SERVER['HTTP_HOST'], $lhlist) ) {
	error_reporting(E_ALL & ~E_STRICT);
	ini_set('display_errors',1);
}

// Logging Info variables
$start_microtime = microtime(true);
global $fc_content_plg_microtime;
$fc_content_plg_microtime = 0;

// load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

// Get component parameters and add tooltips css and js code
$cparams =& JComponentHelper::getParams('com_flexicontent');
if ($cparams->get('add_tooltips', 1)) JHTML::_('behavior.tooltip');

//include constants file
require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'defineconstants.php');
//include the route helper
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'route.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'permission.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// No PDF support in J2.5
if ( FLEXI_J16GE && JRequest::getVar('format') == 'pdf' )
{
	JRequest::setVar('format', 'html');
}

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

$task = JRequest::getCmd('task');
$tasks = explode(".", $task);
if(count($tasks)>=2) {
	$controller = @$controller ? $controller : $tasks[0];
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
if ( $cparams->get('default_menuitem_nopathway',1) ) {
	$mainframe = & JFactory::getApplication();
	$pathway 	= & $mainframe->getPathWay();
	$default_menu_itemid = $cparams->get('default_menu_itemid', 0);
	$pathway_arr = $pathway->getPathway();
	if ( count($pathway_arr) && preg_match("/Itemid=([0-9]+)/",$pathway_arr[0]->link, $matches) ) {
		if ($matches[1] == $default_menu_itemid) {
			array_shift ($pathway_arr);
			$pathway->setPathway($pathway_arr);
			$pathway->set('_count',count($pathway_arr));
		}
	}
}

if ( $cparams->get('print_logging_info') ) {
	$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	$app = & JFactory::getApplication();
	$msg = sprintf( 'FLEXIcontent page creation is %.2f secs, (including content plugins: %.2f secs)', $elapsed_microseconds/1000000, $fc_content_plg_microtime/1000000);
	$app->enqueueMessage( $msg, 'notice' );
}

?>