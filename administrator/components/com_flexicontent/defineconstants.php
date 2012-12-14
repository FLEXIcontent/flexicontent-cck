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

$lhlist = array('localhost', '127.0.0.1');
if( in_array($_SERVER['HTTP_HOST'], $lhlist) ) {
	error_reporting(E_ALL & ~E_STRICT);
	ini_set('display_errors',1);
}

// Joomla version variables
if (!defined('FLEXI_J16GE')) {
	//define('FLEXI_J16GE' , 1 );
	jimport( 'joomla.version' );  $jversion = new JVersion;
	define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
	define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );
}

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);

if ( !class_exists('JControllerLegacy') )
{
	jimport('joomla.application.component.controller');
	class JControllerLegacy extends JController {}
}

if ( !class_exists('JModelLegacy') )
{
	jimport('joomla.application.component.model');
	class JModelLegacy extends JModel {}
}

if ( !class_exists('JViewLegacy') )
{
	jimport('joomla.application.component.view');
	class JViewLegacy extends JView {}
}

if( FLEXI_J30GE )
{
	class JParameter{
		var $params = null;
		
		function __construct($string = ''){
			if(is_array($string)){
				$this->params = $string;
			}else{
				$this->setParams($string);
			}
		}
		
		function get($k, $v = null){
			if(array_key_exists($k, $this->params)){
				return $this->params[$k];
			}else{
				return $v;
			}
		}
		
		function set($k, $v){
			$this->params[$k] = $v;
		}
		
		function setParams($string = ''){
			if(strlen(trim(($string))) > 0){
				$data = json_decode($string, true);
				$this->params = $data;
			}
			if (!$this->params) $this->params = array();
		}
		
		function toString(){
			return json_encode($this->params);
		}
		
		function toArray(){
			return $this->params;
		}
		
		function toObject(){
			return json_decode(json_encode($this->params));
		}
	}
}

// Set a default timezone if web server provider has not done so
if ( ini_get('date.timezone')=='' && version_compare(PHP_VERSION, '5.1.0', '>'))
	date_default_timezone_set('UTC');

// Set jQuery to load in views that use it
define('FLEXI_JQUERY_VER', '1.8.3.min' );
define('FLEXI_JQUERY_UI_VER', '1.9.2.min' );
define('FLEXI_JQUERY_UI_CSS_VER', '1.9.2' );

// Set file manager paths
$params =& JComponentHelper::getParams('com_flexicontent');
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
		$db = &JFactory::getDBO();
		$query = "SELECT lft,rgt FROM #__categories WHERE id=1 ";
		$db->setQuery($query);
		$obj = $db->loadObject();
		if (!defined('FLEXI_LFT_CATEGORY'))	define('FLEXI_LFT_CATEGORY', $obj->lft);
		if (!defined('FLEXI_RGT_CATEGORY'))	define('FLEXI_RGT_CATEGORY', $obj->rgt);
	}
}

// Define configuration constants
if (!defined('FLEXI_ACCESS')) 		define('FLEXI_ACCESS'			, (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);
if (!defined('FLEXI_CACHE')) 			define('FLEXI_CACHE'			, $params->get('advcache', 1));
if (!defined('FLEXI_CACHE_TIME'))	define('FLEXI_CACHE_TIME'	, $params->get('advcache_time', 3600));
if (!defined('FLEXI_GC'))					define('FLEXI_GC'					, $params->get('purge_gc', 1));
if (!defined('FLEXI_FISH'))				define('FLEXI_FISH'				, ($params->get('flexi_fish', 0) && (JPluginHelper::isEnabled('system', 'jfdatabase'))) ? 1 : 0);
if ( FLEXI_FISH ) {
	$db = & JFactory::getDBO();
	$config =& JFactory::getConfig();
	$dbprefix = $config->getValue('config.dbprefix');
	$db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_languages_ext"');
	define('FLEXI_FISH_22GE', (boolean) count($db->loadObjectList()) );
}
if (!defined('FLEXI_ONDEMAND'))		define('FLEXI_ONDEMAND'	, 1 );
if (!defined('FLEXI_ITEMVIEW'))		define('FLEXI_ITEMVIEW'	, FLEXI_J16GE ? 'item' : 'items' );
if (!defined('FLEXI_ICONPATH'))		define('FLEXI_ICONPATH'	, FLEXI_J16GE ? 'media/system/images/' : 'images/M_images/' );

// Version constants
define('FLEXI_VERSION',	FLEXI_J16GE ? '2.0.0' : '1.5.6');
define('FLEXI_RELEASE',	'RC9b (r1601)');
?>