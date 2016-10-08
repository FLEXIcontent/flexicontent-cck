<?php
/**
 * @version 1.5 stable $Id: defineconstants.php 1576 2012-12-01 20:44:56Z ggppdk $
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

// Make sure that Joomla error reporting is used (some plugin may have turned it OFF)
// Also make some changes e.g. disable E_STRICT for maximum and leave it on only for development
switch ( JFactory::getConfig()->get('error_reporting') )
{
	case 'default':
	case '-1':
		break;
	
	case 'none':
	case '0':
		error_reporting(0);
		break;
	
	case 'simple':
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		ini_set('display_errors',1);
		break;
	
	case 'maximum':
		error_reporting(E_ALL & ~E_STRICT);
		ini_set('display_errors',1);
		break;
	
	case 'development':
		error_reporting(-1);
		ini_set('display_errors',1);
		break;
	
	default:
		error_reporting( JFactory::getConfig()->get('error_reporting') );
		ini_set('display_errors', 1);
		break;
}

// Joomla version variables
if (!defined('FLEXI_J16GE') || !defined('FLEXI_J30GE'))
{
	jimport('cms.version.version');
	$jversion = new JVersion;
	
	// J3.5.0+ added new CLASS: StringHelper, to fix name conflict with PHP7 String class, we need to define the StringHelper CLASS in the case of J3.4.x
	if ( version_compare( $jversion->getShortVersion(), '3.5.0', 'lt' ) && !class_exists('Joomla\String\StringHelper') )
	{
		require_once('j34x_LE.php');
	}
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);

if ( !class_exists('JControllerLegacy') )  jimport('legacy.controller.legacy');
if ( !class_exists('JModelLegacy') )       jimport('legacy.model.legacy');
if ( !class_exists('JViewLegacy') )        jimport('legacy.view.legacy');

// Set a default timezone if web server provider has not done so
// phpversion() should be used instead of PHP_VERSION, if not inside Joomla code
if ( ini_get('date.timezone')=='' && version_compare(PHP_VERSION, '5.1.0', '>')) {
	date_default_timezone_set('UTC');
}

// Set file manager paths
$params = JComponentHelper::getParams('com_flexicontent');
if (!defined('COM_FLEXICONTENT_FILEPATH'))	define('COM_FLEXICONTENT_FILEPATH',		JPath::clean( JPATH_ROOT.DS.$params->get('file_path', 'components/com_flexicontent/uploads') ) );
if (!defined('COM_FLEXICONTENT_MEDIAPATH'))	define('COM_FLEXICONTENT_MEDIAPATH',	JPath::clean( JPATH_ROOT.DS.$params->get('media_path', 'components/com_flexicontent/medias') ) );

// Set the media manager paths definitions
$view = JRequest::getCmd('view',null);
$popup_upload = JRequest::getCmd('pop_up',null);
$path = "fleximedia_path";
if(substr(strtolower($view),0,6) == "images" || $popup_upload == 1) $path = "image_path";
if (!defined('COM_FLEXIMEDIA_BASE'))		define('COM_FLEXIMEDIA_BASE',		 JPath::clean(JPATH_ROOT.DS.$params->get($path, 'images'.DS.'stories')));
if (!defined('COM_FLEXIMEDIA_BASEURL'))	define('COM_FLEXIMEDIA_BASEURL', JURI::root().$params->get($path, 'images/stories'));

// J1.5 Section or J1.7 category type
if (!FLEXI_J16GE) {
	if (!defined('FLEXI_SECTION'))				define('FLEXI_SECTION', $params->get('flexi_section'));
	if (!defined('FLEXI_CAT_EXTENSION'))	define('FLEXI_CAT_EXTENSION', '');
} else {
	if (!defined('FLEXI_SECTION'))				define('FLEXI_SECTION', 0);
	if (!defined('FLEXI_CAT_EXTENSION')) {
		define('FLEXI_CAT_EXTENSION', $params->get('flexi_cat_extension','com_content'));
		$db = JFactory::getDBO();
		$query = "SELECT lft,rgt FROM #__categories WHERE id=1 ";
		$db->setQuery($query);
		$obj = $db->loadObject();
		if (!defined('FLEXI_LFT_CATEGORY'))	define('FLEXI_LFT_CATEGORY', $obj->lft);
		if (!defined('FLEXI_RGT_CATEGORY'))	define('FLEXI_RGT_CATEGORY', $obj->rgt);
	}
}

// Define configuration constants
if (!defined('FLEXI_ACCESS'))  define('FLEXI_ACCESS'	, (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);
if (!defined('FLEXI_CACHE'))   define('FLEXI_CACHE'		, $params->get('advcache', 1));
if (!defined('FLEXI_CACHE_TIME'))	define('FLEXI_CACHE_TIME'	, $params->get('advcache_time', 3600));
if (!defined('FLEXI_FISH'))    define('FLEXI_FISH'		, ($params->get('flexi_fish', 0) && (JPluginHelper::isEnabled('system', FLEXI_J16GE ? 'falangdriver' : 'jfdatabase' ))) ? 1 : 0);
if ( FLEXI_FISH ) {
	$db  = JFactory::getDBO();
	$app = JFactory::getApplication();
	$dbprefix = $app->getCfg('dbprefix');
	$db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_languages_ext"');
	define('FLEXI_FISH_22GE', (boolean) count($db->loadObjectList()) );
}
if (!defined('FLEXI_ONDEMAND'))		define('FLEXI_ONDEMAND'	, 1 );
if (!defined('FLEXI_ITEMVIEW'))		define('FLEXI_ITEMVIEW'	, FLEXI_J16GE ? 'item' : 'items' );
if (!defined('FLEXI_ICONPATH'))		define('FLEXI_ICONPATH'	, FLEXI_J16GE ? 'media/system/images/' : 'images/M_images/' );

// Version constants
define('FLEXI_PHP_NEEDED',	'5.3.0');
define('FLEXI_PHP_RECOMMENDED',	'5.4.0');
define('FLEXI_VERSION', '3.2.0-dev');
define('FLEXI_RELEASE',	'');
define('FLEXI_VHASH',	md5(filemtime(__FILE__) . filectime(__FILE__) . FLEXI_VERSION));
?>