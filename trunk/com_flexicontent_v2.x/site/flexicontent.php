<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 207 2010-04-16 07:51:31Z emmanuel.danan $
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

//include the route helper
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'route.php');
//include the needed classes
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'permission.php');
//require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');

//Set filepath
$params =& JComponentHelper::getParams('com_flexicontent');
define('COM_FLEXICONTENT_FILEPATH',    JPATH_ROOT.DS.$params->get('file_path', 'components/com_flexicontent/uploads'));
define('COM_FLEXICONTENT_MEDIAPATH',   JPATH_ROOT.DS.$params->get('media_path', 'components/com_flexicontent/medias'));

// Tooltips
if ($params->get('add_tooltips', 1)) JHTML::_('behavior.tooltip');

// define section
if($flexi_cat_extension = $params->get('flexi_cat_extension', 'com_content')) {
	if (!defined('FLEXI_CAT_EXTENSION')) {
		define('FLEXI_CAT_EXTENSION', $params->get('flexi_cat_extension'));
		$db = &JFactory::getDBO();
		$query = "SELECT lft,rgt FROM #__categories WHERE id=1";
		$db->setQuery($query);
		$obj = $db->loadObject();
		if (!defined('FLEXI_LFT_CATEGORY'))	define('FLEXI_LFT_CATEGORY', $obj->lft);
		if (!defined('FLEXI_RGT_CATEGORY'))	define('FLEXI_RGT_CATEGORY', $obj->rgt);
	}
}
if (!defined('FLEXI_ACCESS')) 		define('FLEXI_ACCESS'		, (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);
if (!defined('FLEXI_CACHE')) 		define('FLEXI_CACHE'		, $params->get('advcache', 1));
if (!defined('FLEXI_CACHE_TIME'))	define('FLEXI_CACHE_TIME'	, $params->get('advcache_time', 3600));
if (!defined('FLEXI_GC'))			define('FLEXI_GC'			, $params->get('purge_gc', 1));
//if (!defined('FLEXI_FISH'))			define('FLEXI_FISH'			, ($params->get('flexi_fish', 0) && (JPluginHelper::isEnabled('system', 'jfdatabase'))) ? 1 : 0);
if (!defined('FLEXI_FISH'))			define('FLEXI_FISH'			, 0);

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');

// Import the field plugins
JPluginHelper::importPlugin('flexicontent_fields');

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

$controller = JRequest::getWord('controller');
if(!$controller) {
	$task = JRequest::getVar('task');
	$tasks = explode(".", $task);
	if(count($tasks)>=2) {
		$controller = $controller?$controller:$tasks[0];
		$task = $tasks[1];
		JRequest::setVar('task', $tasks[1]);
	}
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

?>
