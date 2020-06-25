<?php
/**
 * @version 1.5 stable $Id: flexicontent.helper.php 1966 2014-09-21 17:33:27Z ggppdk $
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

use Joomla\String\StringHelper;

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

// Try re-appling Joomla configuration of error reporting (some installed plugins may disable it)
switch ( JFactory::getConfig()->get('error_reporting') )  // Set the error_reporting
{
	case 'default': case '-1':
		break;
		
	case 'none': case '0':
		error_reporting(0);
		break;
		
	case 'simple':
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		ini_set('display_errors', 1);
		break;
		
	case 'maximum':
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		break;
		
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
		break;
		
	default:
		error_reporting( JFactory::getConfig()->get('error_reporting') );
		ini_set('display_errors', 1);
		break;
}

if (!function_exists('json_encode'))  // PHP < 5.2 lack support for json
{
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'json'.DS.'jsonwrapper_inner.php');
} 

JLoader::register('flexicontent_html', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'html.php');
JLoader::register('flexicontent_upload', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'upload.php');
JLoader::register('flexicontent_tmpl', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'tmpl.php');
JLoader::register('flexicontent_db', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'db.php');
JLoader::register('flexicontent_ajax', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'ajax.php');
JLoader::register('flexicontent_favs', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'favs.php');
JLoader::register('flexicontent_images', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'images.php');
JLoader::register('flexicontent_zip', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'zip.php');

JLoader::register('FLEXIUtilities', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'flexiutilities.php');
JLoader::register('flexicontent_FPDI', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'FPDI.php');
