<?php

/**
 * @package         FLEXIcontent
 * @subpackage      mod_flexigooglemap
 * 
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

if (!defined('DS'))  define('DS', DIRECTORY_SEPARATOR);
require_once(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'defineconstants.php');
require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'helpers' . DS . 'route.php');
require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'classes' . DS . 'flexicontent.helper.php');
require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'classes' . DS . 'flexicontent.fields.php');
require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'classes' . DS . 'flexicontent.categories.php');

require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'item.php');
//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');

//require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');
//\Joomla\CMS\Table\Table::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
JLoader::register('\Joomla\CMS\Form\FormFieldFclayoutbuilder', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'elements' . DS . 'fclayoutbuilder.php');


// Decide whether to show module contents
$app     = \Joomla\CMS\Factory::getApplication();
$config  = \Joomla\CMS\Factory::getConfig();
$jinput  = $app->input;
$option  = $jinput->get('option', '', 'cmd');
$view    = $jinput->get('view', '', 'cmd');


// Show in view

$_view   = $option == 'com_flexicontent' ? $view : 'others';
$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod = !count($show_in_views) || in_array($_view, $show_in_views);


// Show in client
$caching = $params->get('cache', '0') ? $config->get('caching', '0') : 0;
$cache_ppfx = (int) $config->get('cache_platformprefix', '0');
$client_detectable = !$caching || $cache_ppfx;

$show_in_clients = $params->get('show_in_clients', array());
$show_in_clients = !is_array($show_in_clients) ? array($show_in_clients) : $show_in_clients;

// Try to hide the module only if client is detectable
if ($client_detectable && count($show_in_clients) && count($show_in_clients) < 4)  // zero means not saved since we also have 1 extra value '__SAVED__'
{
	$mobileDetector = flexicontent_html::getMobileDetector();
	$_client = !$caching && $mobileDetector->isTablet()  // Joomla cache does not distiguish tablets !
		? 'tablet'
		: ($mobileDetector->isMobile() ? 'mobile' : 'desktop');

	$clients_show_mod = in_array($_client, $show_in_clients);
} else {
	$clients_show_mod = true;
}


// Show via PHP rule, but check if parameter is empty !
$php_show_mod = $params->get('enable_php_rule', 0) && trim($params->get('php_rule', ''))
	? eval($params->get('php_rule'))
	: true;


// Combine rules
$show_mod = $params->get('combine_show_rules', 'AND') == 'AND'
	? ($views_show_mod && $clients_show_mod && $php_show_mod)
	: ($views_show_mod || $clients_show_mod || $php_show_mod);


// ***
// *** TERMINATE if not assigned to current view
// ***
if (!$show_mod)  return;



global $modfc_jprof;
jimport('joomla.profiler.profiler');
$modfc_jprof = new \Joomla\CMS\Profiler\Profiler();
$modfc_jprof->mark('START: FLEXIcontent Google Maps Module');

// Include helpers class file
require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'classes' . DS . 'flexicontent.helper.php');

static $mod_initialized = null;
$modulename = $module->module;
if ($mod_initialized === null) {
	flexicontent_html::loadModuleLanguage($modulename);
	$mod_initialized = true;
}

// Initialize various variables
$document = \Joomla\CMS\Factory::getDocument();
$flexiparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');

// Include the helper only once
require_once(dirname(__FILE__) . DS . 'helper.php');

// Get module's basic display parameters
$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx') ?? '');
$layout 				= $params->get('layout', 'default');

// Workaround for legacy serialized form submit bug, posting empty radio as value 'on'
$disable_css  = (int) $flexiparams->get('disablecss', 0);
$add_ccs      = $params->get('add_ccs');
$add_ccs      = is_numeric($add_ccs) ? (int) $add_ccs : ($disable_css ? 0 : 1);
$add_tooltips = 0; //(int) $params->get('add_tooltips', 1);

/**
 * Render the map locations and get items having these markers
 * - These are 2 arrays indexed with the same integer e.g. 25 locations, 25 items (of these items e.g. 8 are unique items)
 *
 * NOTE: anonymous functions inside: LAYOUT_mapLocations.php, LAYOUT_mapLocation.php are used to render the map locations
 *    $renderedMapLocations = function ($params, &$mapItems, $module);
 */
$mapItems             = null;
$renderedMapLocations = modFlexigooglemapHelper::renderMapLocations($params, $mapItems, $module);

$mapItemData = [];
foreach ($mapItems as $mapItem)
{
	$mapItemData[] = (object) array(
		'title' => $mapItem->title,
		'link' => $mapItem->link,
	);
}


/**
 * Default marker URL is used in map locations that do not include a custom marker (via item form)
 * or if the usage of custom marker icon per location has not been enabled in module configuration
 */
$wS_dMU = 0;
$hS_dMU = 0;
$wA_dMU = 0;
$hA_dMU = 0;
$defaultMarkerURL = modFlexigooglemapHelper::getDefaultMarkerURL($params, $wS_dMU, $hS_dMU, $wA_dMU, $hA_dMU);

/**
 * Compatibility with old custom module layouts
 */
$tMapTips      = $renderedMapLocations;
$markerdisplay = "'" . $defaultMarkerURL . "'";

/**
 * Add tooltips
 */
if ($add_tooltips)
{
	HTMLHelper::_('bootstrap.tooltip');
}

// Add css
if ($add_ccs && $layout) {
	// Work around for extension that capture module's HTML
	if ($add_ccs === 2) {
		// Active module layout css (optional)
		if (file_exists(dirname(__FILE__) . DS . 'tmpl' . DS . $layout . DS . $layout . '.css')) {
			echo flexicontent_html::getInlineLinkOnce(Uri::base(true) . '/modules/' . $modulename . '/tmpl/' . $layout . '/' . $layout . '.css', array('version' => FLEXI_VHASH));
		}

		// Module 's core CSS
		if (file_exists(dirname(__FILE__) . DS . 'tmpl_common' . DS . 'module.css')) {
			echo flexicontent_html::getInlineLinkOnce(Uri::base(true) . '/modules/' . $modulename . '/tmpl_common/module.css', array('version' => FLEXI_VHASH));
		}

		// Component CSS with optional override
		echo flexicontent_html::getInlineLinkOnce(Uri::base(true) . '/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		if (FLEXI_J40GE && file_exists(JPATH_SITE . DS . 'media/templates/site' . DS . $app->getTemplate() . DS . 'css' . DS . 'flexicontent.css')) {
			echo flexicontent_html::getInlineLinkOnce(Uri::base(true) . '/media/templates/site/' . $app->getTemplate() . '/css/flexicontent.css', array('version' => FLEXI_VHASH));
		} elseif (file_exists(JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'css' . DS . 'flexicontent.css')) {
			echo flexicontent_html::getInlineLinkOnce(Uri::base(true) . '/templates/' . $app->getTemplate() . '/css/flexicontent.css', array('version' => FLEXI_VHASH));
		}
	}

	// Standards compliant implementation by placing CSS link into the HTML HEAD
	else {
		// Active module layout css (optional)
		if (file_exists(dirname(__FILE__) . DS . 'tmpl' . DS . $layout . DS . $layout . '.css')) {
			$document->addStyleSheet(Uri::base(true) . '/modules/' . $modulename . '/tmpl/' . $layout . '/' . $layout . '.css', array('version' => FLEXI_VHASH));
		}

		// Module 's core CSS
		if (file_exists(dirname(__FILE__) . DS . 'tmpl_common' . DS . 'module.css')) {
			$document->addStyleSheet(Uri::base(true) . '/modules/' . $modulename . '/tmpl_common/module.css', array('version' => FLEXI_VHASH));
		}

		// Component CSS with optional override
		$document->addStyleSheet(Uri::base(true) . '/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		if (FLEXI_J40GE && file_exists(JPATH_SITE . DS . 'media/templates/site' . DS . $app->getTemplate() . DS . 'css' . DS . 'flexicontent.css')) {
			$document->addStyleSheet(Uri::base(true) . '/media/templates/site/' . $app->getTemplate() . '/css/flexicontent.css', array('version' => FLEXI_VHASH));
		} elseif (file_exists(JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'css' . DS . 'flexicontent.css')) {
			$document->addStyleSheet(Uri::base(true) . '/templates/' . $app->getTemplate() . '/css/flexicontent.css');
		}
	}
}

// Render Layout
require(ModuleHelper::getLayoutPath('mod_flexigooglemap', $layout));

/**
 * Append performance stats to global variable
 */
if ($flexiparams->get('print_logging_info'))
{
	$modfc_jprof->mark('END: FLEXIcontent Google Maps Module');
	$msg  = '<br/><br/>' . implode('<br/>', $modfc_jprof->getbuffer());
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}
