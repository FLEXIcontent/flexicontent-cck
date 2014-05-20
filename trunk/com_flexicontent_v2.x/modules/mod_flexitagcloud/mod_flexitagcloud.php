<?php
/**
 * @version 1.1 $Id: mod_flexitagcloud.php 1767 2013-09-18 17:46:46Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Tag Cloud Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXItagcloud module is a tag cloud module module for flexicontent.
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

//no direct access
defined('_JEXEC') or die('Restricted access');

// Decide whether to show module contents
$app    = JFactory::getApplication();
$view   = JRequest::getVar('view');
$option = JRequest::getVar('option');

if ($option=='com_flexicontent')
	$_view = ($view==FLEXI_ITEMVIEW) ? 'item' : $view;
else
	$_view = 'others';

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

if ( $show_mod )
{
	global $modfc_jprof;
	jimport( 'joomla.error.profiler' );
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Tags Cloud Module');

	// load english language file for 'mod_flexitagcloud' module then override with current language file
	JFactory::getLanguage()->load('mod_flexitagcloud', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexitagcloud', JPATH_SITE, null, true);

	// initialize various variables
	$document = JFactory::getDocument();
	$caching 	= $app->getCfg('caching', 0);

	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');

	// get module's basic display parameters
	$add_ccs 				= $params->get('add_ccs', 1);
	$layout 				= $params->get('layout', 'default');

	// Add css
	if ($add_ccs) {
		if ($caching && !FLEXI_J16GE) {
			// Work around for caching bug in J1.5
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css">';
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css">';
			}
		} else {
			// Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
			$document->addStyleSheet(JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css');
			$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css');
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
			}
		}
	}
	
	// Get data, etc by calling methods from helper file and include then include template to display them
	$list = modFlexiTagCloudHelper::getTags($params, $module);

	// Render Layout
	require(JModuleHelper::getLayoutPath('mod_flexitagcloud', $layout));
	
	// append performance stats to global variable
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$modfc_jprof->mark('END: FLEXIcontent Tags Cloud Module');
		$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
		global $fc_performance_msg;
		$fc_performance_msg .= $msg;
	}
}