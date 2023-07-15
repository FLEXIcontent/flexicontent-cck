<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

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
	$jversion = new JVersion;
	
	// J3.5.0+ added new CLASS: StringHelper, to fix name conflict with PHP7 String class, we need to define the StringHelper CLASS in the case of J3.4.x
	if ( version_compare( $jversion->getShortVersion(), '3.5.0', 'lt' ) && !class_exists('Joomla\String\StringHelper') )
	{
		require_once('j34x_LE.php');
	}
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', true );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', true );
if (!defined('FLEXI_J37GE'))   define('FLEXI_J37GE', version_compare( $jversion->getShortVersion(), '3.6.99', '>' ) );
if (!defined('FLEXI_J38GE'))   define('FLEXI_J38GE', version_compare( $jversion->getShortVersion(), '3.7.99', '>' ) );
if (!defined('FLEXI_J40GE'))   define('FLEXI_J40GE', version_compare( $jversion->getShortVersion(), '3.99.99', '>' ) );

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);

if ( !class_exists('JControllerLegacy') )  jimport('legacy.controller.legacy');
if ( !class_exists('JModelLegacy') )       jimport('legacy.model.legacy');
if ( !class_exists('JViewLegacy') )        jimport('legacy.view.legacy');

// Set a default timezone if web server provider has not done so
// phpversion() should be used instead of PHP_VERSION, if not inside Joomla code
if (ini_get('date.timezone') == '')
{
	date_default_timezone_set('UTC');
}

// Set file manager paths
$params = JComponentHelper::getParams('com_flexicontent');
if (!defined('COM_FLEXICONTENT_FILEPATH'))	define('COM_FLEXICONTENT_FILEPATH',		JPath::clean( JPATH_ROOT.DS.$params->get('file_path', 'components/com_flexicontent/uploads') ) );
if (!defined('COM_FLEXICONTENT_MEDIAPATH'))	define('COM_FLEXICONTENT_MEDIAPATH',	JPath::clean( JPATH_ROOT.DS.$params->get('media_path', 'components/com_flexicontent/medias') ) );

// Set the media manager paths definitions
$jinput = JFactory::getApplication()->input;
$view = $jinput->get('view', '', 'cmd');
$popup_upload = $jinput->get('pop_up', null, 'cmd');
$path = "fleximedia_path";
if(substr(strtolower($view),0,6) == "images" || $popup_upload == 1) $path = "image_path";
if (!defined('COM_FLEXIMEDIA_BASE'))		define('COM_FLEXIMEDIA_BASE',		 JPath::clean(JPATH_ROOT.DS.$params->get($path, 'images'.DS.'stories')));
if (!defined('COM_FLEXIMEDIA_BASEURL'))	define('COM_FLEXIMEDIA_BASEURL', (php_sapi_name() !== 'cli' ? JUri::root() : JPATH_ROOT . '/').$params->get($path, 'images/stories'));

if (!defined('FLEXI_SECTION'))				define('FLEXI_SECTION', 0);

if (!defined('FLEXI_CAT_EXTENSION'))
{
	define('FLEXI_CAT_EXTENSION', $params->get('flexi_cat_extension','com_content'));
	$db = JFactory::getDbo();
	$query = "SELECT lft,rgt FROM #__categories WHERE id=1 ";
	$db->setQuery($query);
	$obj = $db->loadObject();
	if (!defined('FLEXI_LFT_CATEGORY'))	define('FLEXI_LFT_CATEGORY', $obj->lft);
	if (!defined('FLEXI_RGT_CATEGORY'))	define('FLEXI_RGT_CATEGORY', $obj->rgt);
}

if (FLEXI_J40GE)
{
	class JEventDispatcher extends Joomla\Event\Dispatcher
	{
		protected static $instance = null;
		public static function getInstance()
		{
			return self::$instance ?: (self::$instance = new static);
		}
	}
}

// Define configuration constants
if (!defined('FLEXI_ACCESS'))  define('FLEXI_ACCESS'	, 0);  // TO BE REMOVED
if (!defined('FLEXI_CACHE'))   define('FLEXI_CACHE'		, $params->get('advcache', 1));
if (!defined('FLEXI_CACHE_TIME'))	define('FLEXI_CACHE_TIME'	, $params->get('advcache_time', 3600));
if (!defined('FLEXI_FALANG'))     define('FLEXI_FALANG'		, (JPluginHelper::isEnabled('system', 'falangdriver') ? 1 : 0));
if (!defined('FLEXI_FISH'))       define('FLEXI_FISH'		  , ($params->get('flexi_fish', 0) && FLEXI_FALANG ? 1 : 0));
if (!defined('FLEXI_ONDEMAND'))		define('FLEXI_ONDEMAND'	, 1 );
if (!defined('FLEXI_ITEMVIEW'))		define('FLEXI_ITEMVIEW'	, FLEXI_J16GE ? 'item' : 'items' );
if (!defined('FLEXI_ICONPATH'))		define('FLEXI_ICONPATH'	, FLEXI_J16GE ? 'media/system/images/' : 'images/M_images/' );

// Version constants
define('FLEXI_PHP_NEEDED',	'7.0.0');
define('FLEXI_PHP_RECOMMENDED',	'8.0.25');
define('FLEXI_VERSION', '4.2.1');
define('FLEXI_RELEASE',	'');
define('FLEXI_VHASH',	md5(filemtime(__FILE__) . filectime(__FILE__) . FLEXI_VERSION));
define('FLEXI_PHP_54GE', version_compare(PHP_VERSION, '5.4.0', '>='));
