<?php
/**
* @version 0.6.0 stable $Id: mod_flexigooglemap.php yannick berges
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2015 Berges Yannick - www.com3elles.com
* @license GNU/GPL v2

* special thanks to ggppdk and emmanuel dannan for flexicontent
* special thanks to my master Marc Studer

* FLEXIadmin module is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
**/

// no direct access
defined('_JEXEC') or die('Restricted access');
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);


// Decide whether to show module contents
$app     = JFactory::getApplication();
$jinput  = $app->input;
$option  = $jinput->get('option', '', 'cmd');
$view    = $jinput->get('view', '', 'cmd');


// Show in view

$_view   = $option=='com_flexicontent' ? $view : 'others';
$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod = !count($show_in_views) || in_array($_view,$show_in_views);


// Show in client

$show_in_clients = $params->get('show_in_clients', array());
$show_in_clients = !is_array($show_in_clients) ? array($show_in_clients) : $show_in_clients;

if (count($show_in_clients) && count($show_in_clients) < 4)  // zero means not saved since we also have 1 extra value '__SAVED__'
{
	$mobileDetector = flexicontent_html::getMobileDetector();
	$_client = $mobileDetector->isTablet()
		? 'tablet'
		: ($mobileDetector->isMobile() ? 'mobile' : 'desktop');

	$clients_show_mod = in_array($_client, $show_in_clients);
}
else
{
	$clients_show_mod = true;
}


// Show via PHP rule

$php_show_mod = $params->get('enable_php_rule', 0)
	? eval($params->get('php_rule'))
	: true;


// Combine rules
$show_mod = $params->get('combine_show_rules', 'AND') == 'AND'
	? ($views_show_mod && $clients_show_mod && $php_show_mod)
	: ($views_show_mod || $clients_show_mod || $php_show_mod);


// ***
// *** TERMINATE if not assigned to current view
// ***
if ( !$show_mod )  return;



global $modfc_jprof;
jimport('joomla.profiler.profiler');
$modfc_jprof = new JProfiler();
$modfc_jprof->mark('START: FLEXIcontent Tags Cloud Module');

// Include helpers class file
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

static $mod_initialized = null;
$modulename = 'mod_flexitagcloud';
if ($mod_initialized === null)
{
	flexicontent_html::loadModuleLanguage($modulename);
	$mod_initialized = true;
}

$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));

// initialize various variables
$document = JFactory::getDocument();
$caching 	= $app->getCfg('caching', 0);
$flexiparams = JComponentHelper::getParams('com_flexicontent');

// include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

// get module's basic display parameters
$add_ccs 				= $params->get('add_ccs', !$flexiparams->get('disablecss', 0));
$layout 				= $params->get('layout', 'default');

$tMapTips = modFlexigooglemapHelper::renderMapLocations($params);
$markerdisplay = modFlexigooglemapHelper::getMarkerURL($params);

// Render Layout
require(JModuleHelper::getLayoutPath('mod_flexigooglemap', $layout));

// append performance stats to global variable
if ( $flexiparams->get('print_logging_info') )
{
	$modfc_jprof->mark('END: FLEXIcontent Tags Cloud Module');
	$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}