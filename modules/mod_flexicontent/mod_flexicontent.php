<?php
/**
 * @package         FLEXIcontent
 * @subpackage      mod_flexicontent
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

require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');
//JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');


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
$modfc_jprof->mark('START: FLEXIcontent Universal Content Module');
global $mod_fc_run_times;
$mod_fc_run_times = array();

// Include helpers class file
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

static $mod_initialized = null;
$modulename = 'mod_flexicontent';
if ($mod_initialized === null)
{
	flexicontent_html::loadModuleLanguage($modulename);
	$mod_initialized = true;
}

// Initialize various variables
global $globalcats;
$document = JFactory::getDocument();
$flexiparams = JComponentHelper::getParams('com_flexicontent');

// Include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

// Verify parameters (like forced menu item id and comments showing)
modFlexicontentHelper::verifyParams( $params );

// Get module ordering & count parameters
$ordering					= $params->get('ordering', array());
$ordering_addtitle= $params->get('ordering_addtitle',1);
$count 					= (int)$params->get('count', 5);
$featured				= (int)$params->get('count_feat', 1);

if ( $ordering_addtitle && (int)$params->get('orderbycustomfieldid', 0) && in_array('field', $ordering) )
{
	$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
	JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
	$orderby_custom_field = JTable::getInstance('flexicontent_fields', '');
	$orderby_custom_field->load($orderbycustomfieldid);
}

if ( empty($orderby_custom_field) ) {
	$orderby_custom_field = new stdClass();
	$orderby_custom_field->label = 'NA';
}

// Default ordering is 'added' if none ordering is set. Also make sure $ordering is an array (of ordering groups)
if ( empty($ordering) )    $ordering = array('added');
if (!is_array($ordering))  $ordering = explode(',', $ordering);

// Get module's basic display parameters
$moduleclass_sfx= $params->get('moduleclass_sfx', '');
$layout 				= $params->get('layout', 'default');

// Workaround for legacy seriazed form submit bug, posting empty radio as value 'on'
$disable_css  = (int) $flexiparams->get('disablecss', 0);
$add_ccs      = $params->get('add_ccs');
$add_ccs      = is_numeric($add_ccs) ? (int) $add_ccs : ($disable_css ? 0 : 1);
$add_tooltips = (int) $params->get('add_tooltips', 1);

$width 					= $params->get('width');
$height 				= $params->get('height');

// Get module basic fields parameters

// Standard
$display_title 		= $params->get('display_title');
$link_title 			= $params->get('link_title');
$display_date 		= $params->get('display_date');
$display_text 		= $params->get('display_text');
$display_hits			= $params->get('display_hits');
$display_voting		= $params->get('display_voting');
$display_comments	= $params->get('display_comments');
$mod_readmore	 		= $params->get('mod_readmore');
$mod_use_image	 	= $params->get('mod_use_image');
$mod_link_image		= $params->get('mod_link_image');

// Featured 
$display_title_feat 	= $params->get('display_title_feat');
$link_title_feat 			= $params->get('link_title_feat');
$display_date_feat		= $params->get('display_date_feat');
$display_text_feat 		= $params->get('display_text_feat');
$display_hits_feat 		= $params->get('display_hits_feat');
$display_voting_feat	= $params->get('display_voting_feat');
$display_comments_feat= $params->get('display_comments_feat');
$mod_readmore_feat		= $params->get('mod_readmore_feat');
$mod_use_image_feat 	= $params->get('mod_use_image_feat');
$mod_link_image_feat 	= $params->get('mod_link_image_feat');

// Get module custom fields parameters

// Standard
$use_fields 				= $params->get('use_fields',1);
$display_label 			= $params->get('display_label');
$hide_label_onempty	= $params->get('hide_label_onempty');
$text_after_label		= $params->get('text_after_label');
$fields 						= $params->get('fields');

// Featured
$use_fields_feat 				= $params->get('use_fields_feat',1);
$display_label_feat 		= $params->get('display_label_feat');
$hide_label_onempty_feat= $params->get('hide_label_onempty_feat');
$text_after_label_feat 	= $params->get('text_after_label_feat');
$fields_feat 						= $params->get('fields_feat');

// module display params
$show_more		= (int)$params->get('show_more', 1);
$more_link		= $params->get('more_link');
$more_title		= $params->get('more_title', 'FLEXI_MODULE_READ_MORE');
$more_css			= $params->get('more_css');

// custom parameters
$custom1 				= $params->get('custom1');
$custom2 				= $params->get('custom2');
$custom3 				= $params->get('custom3');
$custom4 				= $params->get('custom4');
$custom5 				= $params->get('custom5');

// Create Item List Data
$list_totals = $params->get('show_found_items', 0) ? array() : null;   // NULL indicates not to calculate totals
$list_arr = modFlexicontentHelper::getList($params, $list_totals);

// Get comments for the items (if enabled), NOTE !! TODO: modify templates and XML file so that this used
$comments_arr = modFlexicontentHelper::getComments($params, $list_arr);

$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime();

// Get Category List Data
$catdata_arr = modFlexicontentHelper::getCategoryData($params);
$catdata_arr = $catdata_arr ? $catdata_arr : array(0 => false);

$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['category_data_retrieval'];

$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime();

// Load needed JS libs & CSS styles
flexicontent_html::loadFramework('jQuery');
flexicontent_html::loadFramework('flexi_tmpl_common');
flexicontent_html::loadFramework('flexi-lib');

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
		echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', array('version'=>FLEXI_VHASH));

		// Component CSS with optional override
		echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version'=>FLEXI_VHASH));
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			echo flexicontent_html::getInlineLinkOnce(JUri::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css', array('version'=>FLEXI_VHASH));
		}
	}
	
	// Standards compliant implementation by placing CSS link into the HTML HEAD
	else
	{
		// Active module layout css (optional)
		if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css'))
		{
			$document->addStyleSheetVersion(JUri::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', FLEXI_VHASH);
		}

		// Module 's core CSS
		$document->addStyleSheetVersion(JUri::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', FLEXI_VHASH);

		// Component CSS with optional override
		$document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheet(JUri::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}
	}
}

// Include module header, but make sure that file exists, because otherwise the Joomla module helper will include ... 'default.php'
// module template ! thus a Joomla override template header is allowed only if template header.php file exists locally too !!
if ( file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.'header.php') )
	require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout.'/header'));

// Render Layout, (once per category if apply per category is enabled ...)
foreach ($catdata_arr as $catid => $catdata)
{
	$list = & $list_arr[$catid];

	// Check items exist
	$items_exist = false;
	foreach ($ordering as $ord)
	{
		if ( !empty($list[$ord]['featured']) || !empty($list[$ord]['standard']) )
		{
			$items_exist = true;
			break;
		}
	}

	// Check if we should skip empty categories (if catid is 0 (apply per category disabled) then we can skip)
	$can_skip_category = !$catid
		? true
		: $params->get('skip_category_if_noitems', 0);

	// Show total if total enabled and ... EITHER total is non-zero  OR total is set with skip category on-empty disabled
	$showing_total = !$list_totals
		? false
		: !empty($list_totals[$catid]) || (!$can_skip_category && isset($list_totals[$catid]));

	// Decide whether to skip rendering of the layout
	if ( !$items_exist && $can_skip_category && !$showing_total && !$params->get('display_favlist', 0) )
	{
		continue;
	}

	require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout));
}

// Include module footer, e.g. includes module's Read More, because otherwise the Joomla module helper will include ... 'default.php'
// module template ! thus a Joomla override template header is allowed only if template header.php file exists locally too !!
if ( file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.'footer.php') )
{
	require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout.'/footer'));
}


$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['rendering_template'];

$task_lbls = array(
'query_items'=>'Main DB Querying of Items ('.count($catdata_arr).' queries): %.2f secs',
'query_items_sec'=>'Sec SQL Querying of Items ('.count($catdata_arr).' queries): %.2f secs',
'empty_fields_filter'=>'Empty fields filter (skip items)): %.2f secs',
'item_list_creation'=>'Item list creation (with custom field rendering): %.2f secs',
'category_data_retrieval'=>'Category data retrieval: %.2f secs',
'rendering_template'=>'Adding css/js & Rendering Template with item/category/etc data: %.2f secs'
);

// append performance stats to global variable
if ( $flexiparams->get('print_logging_info') )
{
	$modfc_jprof->mark('END: FLEXIcontent Universal Content Module');
	$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
	foreach ($mod_fc_run_times as $modtask => $modtime)
	{
		$msg .= '<code>'.sprintf( ' -- '.$task_lbls[$modtask].'<br/>', $modtime) .'</code>';
	}
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}