<?php
/**
 * @package         FLEXIcontent
 * @subpackage      mod_flexiadvsearch
 * 
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2017, FLEXIcontent team, All Rights Reserved
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
$modfc_jprof->mark('START: FLEXIcontent Search Module');

// Include helpers class file
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

static $mod_initialized = null;
$modulename = $module->module;
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

// Get module's basic display parameters
$moduleclass_sfx= $params->get('moduleclass_sfx', '');
$layout 				= $params->get('layout', 'default');

// Workaround for legacy serialized form submit bug, posting empty radio as value 'on'
$disable_css  = (int) $flexiparams->get('disablecss', 0);
$add_ccs      = $params->get('add_ccs');
$add_ccs      = is_numeric($add_ccs) ? (int) $add_ccs : ($disable_css ? 0 : 1);
$add_tooltips = (int) $params->get('add_tooltips', (int) $flexiparams->get('add_tooltips', 1));

$text      = JText::_($params->get('text', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
$width     = intval($params->get('width', 20));
$maxlength = $width > 20 ? $width : 20;

// Buttons and their positions, and their types (image or text)

// Go button
$button       = $params->get('button', '');
$button_pos   = $params->get('button_pos', 'left');
$button_as    = $params->get('button_as', '');
$button_text  = JText::_($params->get('button_text', 'FLEXI_ADV_MOD_GO'));
$button_image = $params->get('button_image', 'components/com_flexicontent/assets/images/magnifier.png');

// Direct button
$direct       = $params->get('direct_button', '');
$direct_pos   = $params->get('direct_pos', 'left');
$direct_as    = $params->get('direct_as', '');
$direct_text  = JText::_($params->get('direct_text', 'FLEXI_ADV_MOD_DIRECT'));
$direct_image = $params->get('direct_image', 'components/com_flexicontent/assets/images/question.png');

// Link to search view button ('Advanced')
$linkadvsearch     = $params->get('linkadvsearch', 1);
$linkadvsearch_pos = $params->get('linkadvsearch_pos', 'bottom');
$linkadvsearch_as  = '';
$linkadvsearch_txt = JText::_($params->get('linkadvsearch_txt', 'FLEXI_ADV_MOD_ADVANCED_SEARCH'));


// Load needed JS libs & CSS styles
flexicontent_html::loadFramework('jQuery');
flexicontent_html::loadFramework('flexi_tmpl_common');

// Add tooltips
if ($add_tooltips)
{
	JHtml::_('bootstrap.tooltip');
}

// Add css
if ($add_ccs && $layout)
{
	// Work around for extension that capture module's HTML 
	if ($add_ccs === 2)
	{
		// Active module layout css (optional)
		if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css'))
		{
			echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', array('version'=>FLEXI_VHASH));
		}

		// Module 's core CSS
		if (file_exists(dirname(__FILE__).DS.'tmpl_common'.DS.'module.css'))
		{
			echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', array('version'=>FLEXI_VHASH));
		}

		// Component CSS with optional override
		echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version'=>FLEXI_VHASH));
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css', array('version'=>FLEXI_VHASH));
		}

		// Filter's styles
		echo '<link rel="stylesheet" href="'.JUri::base(true).'/components/com_flexicontent/assets/css/flexi_filters.css?'.FLEXI_VHASH.'">';
	}
	
	// Standards compliant implementation by placing CSS link into the HTML HEAD
	else
	{
		// Active module layout css (optional)
		if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css'))
		{
			$document->addStyleSheet(JUri::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', array('version' => FLEXI_VHASH));
		}

		// Module 's core CSS
		if (file_exists(dirname(__FILE__).DS.'tmpl_common'.DS.'module.css'))
		{
			$document->addStyleSheet(JUri::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', array('version' => FLEXI_VHASH));
		}

		// Component CSS with optional override
		$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheet(JUri::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}

		// Filter's styles
		$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexi_filters.css', array('version' => FLEXI_VHASH));
	}
}

$itemid_force = $params->get('itemid_force', '0');
$itemid = $itemid_force ? (int) $params->get('itemid_force_value', 0)  :  0;

if ($itemid)
{
	$menu = $app->getMenu()->getItem($itemid);     // Retrieve active menu
	
	// Merge into module params (a) COMPONENT parameters, then (b) active menu parameters
	$params->merge(JComponentHelper::getComponent('com_flexicontent')->params);
	if ($menu)
	{
		$params->merge($menu->params);
	}
}

// Render Layout
require(JModuleHelper::getLayoutPath('mod_flexiadvsearch', $layout));

// append performance stats to global variable
if ( $flexiparams->get('print_logging_info') )
{
	$modfc_jprof->mark('END: FLEXIcontent Search Module');
	$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}