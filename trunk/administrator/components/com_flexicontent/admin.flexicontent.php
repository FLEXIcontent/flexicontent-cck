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

require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.acl.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT.DS.'tables');

// Import the field plugins
JPluginHelper::importPlugin('flexicontent_fields');
JPlugin::loadLanguage('com_content', JPATH_ADMINISTRATOR);

// Set filepath
$params =& JComponentHelper::getParams('com_flexicontent');
define('COM_FLEXICONTENT_FILEPATH',    JPATH_ROOT.DS.$params->get('file_path', 'components/com_flexicontent/uploads'));
define('COM_FLEXICONTENT_MEDIAPATH',   JPATH_ROOT.DS.$params->get('media_path', 'components/com_flexicontent/medias'));

// Define some constants
if (!defined('FLEXI_SECTION'))	define('FLEXI_SECTION', $params->get('flexi_section'));
if (!defined('FLEXI_ACCESS')) 	define('FLEXI_ACCESS', (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);
if (!defined('FLEXI_FISH'))		define('FLEXI_FISH',	($params->get('flexi_fish', 0) && (JPluginHelper::isEnabled('system', 'jfdatabase'))) ? 1 : 0);
define('FLEXI_VERSION',	'1.5.4');
define('FLEXI_RELEASE',	'dev (r527)');

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

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

// Perform the Request task
$controller->execute( JRequest::getWord('task'));
$controller->redirect();
?>