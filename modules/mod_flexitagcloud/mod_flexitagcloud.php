<?php
/**
 * @version 1.1 $Id: mod_flexitagcloud.php 1767 2013-09-18 17:46:46Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Tag Cloud Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * Tags Cloud Module for flexicontent.
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);


// Decide whether to show module contents
$app     = JFactory::getApplication();
$jinput  = $app->input;
$option  = $jinput->get('option', '', 'cmd');
$view    = $jinput->get('view', '', 'cmd');

$_view   = $option=='com_flexicontent' ? $view : 'others';

$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod =!count($show_in_views) || in_array($_view,$show_in_views);

if ($params->get('enable_php_rule', 0)) {
	$php_show_mod = eval($params->get('php_rule'));
	$show_mod = $params->get('combine_show_rules', 'AND')=='AND'
		? ($views_show_mod && $php_show_mod)  :  ($views_show_mod || $php_show_mod);
} else {
	$show_mod = $views_show_mod;
}


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

// initialize various variables
$document = JFactory::getDocument();
$caching 	= $app->getCfg('caching', 0);
$flexiparams = JComponentHelper::getParams('com_flexicontent');

// include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

// get module's basic display parameters
$add_ccs 				= $params->get('add_ccs', !$flexiparams->get('disablecss', 0));
$layout 				= $params->get('layout', 'default');

// Add css
if ($add_ccs)
{
	// Work around for extension that capture module's HTML 
	if ($add_ccs==2)
	{

		// Module 's core CSS
		echo '<link rel="stylesheet" href="'.JUri::base(true).'/modules/'.$modulename.'/tmpl/'.$modulename.'.css?'.FLEXI_VHASH.'">';

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

		// Module 's core CSS
		$document->addStyleSheetVersion(JUri::base(true).'/modules/'.$modulename.'/tmpl/'.$modulename.'.css', FLEXI_VHASH);

		// Component CSS with optional override
		$document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheet(JUri::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}
	}
}

// Get data, etc by calling methods from helper file and include then include template to display them
$list = modFlexiTagCloudHelper::getTags($params, $module);

// Render Layout
require(JModuleHelper::getLayoutPath('mod_flexitagcloud', $layout));

// append performance stats to global variable
if ( $flexiparams->get('print_logging_info') )
{
	$modfc_jprof->mark('END: FLEXIcontent Tags Cloud Module');
	$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
	global $fc_performance_msg;
	$fc_performance_msg .= $msg;
}