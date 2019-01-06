<?php
/**
 * @package         FLEXIcontent
 * @subpackage      mod_flexicategories
 * 
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');

//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.php');
//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');

//require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');
//JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
JLoader::register('JFormFieldFclayoutbuilder', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'elements' . DS . 'fclayoutbuilder.php');


// Decide whether to show module contents
$app     = JFactory::getApplication();
$config  = JFactory::getConfig();
$jinput  = $app->input;
$option  = $jinput->get('option', '', 'cmd');
$view    = $jinput->get('view', '', 'cmd');


// Show in view

$_view   = $option=='com_flexicontent' ? $view : 'others';
$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod = !count($show_in_views) || in_array($_view,$show_in_views);


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
$modfc_jprof->mark('START: FLEXIcontent Categories Module');

// Include helpers class file
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

static $mod_initialized = null;
$modulename = 'mod_flexidownloads';
if ($mod_initialized === null)
{
	flexicontent_html::loadModuleLanguage($modulename);
	$mod_initialized = true;
}

// Initialize various variables
$document = JFactory::getDocument();
$flexiparams = JComponentHelper::getParams('com_flexicontent');

// Include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

// Include JCategoryNode class file
JLoader::register('JCategoryNode', JPATH_BASE . '/libraries/legacy/categories/categories.php');

// Get module's basic display parameters
$cacheid = md5($module->id);

$cacheparams               = new stdClass;
$cacheparams->cachemode    = 'id';
$cacheparams->class        = 'ModFlexiCategoriesHelper';
$cacheparams->method       = 'getList';
$cacheparams->methodparams = $params;
$cacheparams->modeparams   = $cacheid;

$list = JModuleHelper::moduleCache($module, $params, $cacheparams);

if (!empty($list))
{
	$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));
	$startLevel      = reset($list)->getParent()->level;
	// Render Layout
	require JModuleHelper::getLayoutPath('mod_flexicategories', $params->get('layout', 'default'));
}

// append performance stats to global variable
if ( $flexiparams->get('print_logging_info') )
{
	$modfc_jprof->mark('END: FLEXIcontent Categories Module');
	$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}
