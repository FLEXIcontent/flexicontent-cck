<?php
/**
 * @version 1.0 $Id: mod_flexiadvsearch.php 1795 2013-10-23 00:08:42Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIadvsearch Module
 * @copyright (C) 2011 flexicontent.org
 * @license GNU/GPL v3
 * 
 * Search Module for flexicontent.
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
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
	jimport('joomla.profiler.profiler');
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Adv Search Module');
	
	static $mod_initialized = null;
	$modulename = 'mod_flexiadvsearch';
	if ($mod_initialized === null)
	{
		// Load english language file for current module then override (forcing a reload) with current language file
		JFactory::getLanguage()->load($modulename, JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load($modulename, JPATH_SITE, null, $force_reload = true, $load_default = true);
		
		// Load english language file for 'com_flexicontent' and then override with current language file. Do not force a reload for either (not needed)
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, $force_reload = false, $load_default = true);
		$mod_initialized = true;
	}
	
	// initialize various variables
	$document = JFactory::getDocument();
	$caching 	= $app->getCfg('caching', 0);
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	
	// Other parameters
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', !$flexiparams->get('disablecss', 0));
	$add_tooltips 	= $params->get('add_tooltips', 1);
	
	$text      = JText::_($params->get('text', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
	$width     = intval($params->get('width', 20));
	$maxlength = $width > 20 ? $width : 20;
	
	// Buttons and their positions
	
	// Go button
	$button        = $params->get('button', '');
	$button_pos    = $params->get('button_pos', 'left');
	
	//Direct button
	$direct        = $params->get('direct_button', '');
	$direct_pos    = $params->get('direct_pos', 'left');
	
	// Buttons as image or as text
	
	// Go button
	$button_as    = $params->get('button_as', '');
	$button_text  = JText::_($params->get('button_text', 'FLEXI_ADV_MOD_GO'));
	$button_image = $params->get('button_image', 'components/com_flexicontent/assets/images/magnifier.png');
	
	// Direct button
	$direct_as    = $params->get('direct_as', '');
	$direct_text  = JText::_($params->get('direct_text', 'FLEXI_ADV_MOD_DIRECT'));
	$direct_image = $params->get('direct_image', 'components/com_flexicontent/assets/images/question.png');

	$linkadvsearch     = $params->get('linkadvsearch', 1);
	$linkadvsearch_txt = JText::_($params->get('linkadvsearch_txt', 'FLEXI_ADV_MOD_ADVANCED_SEARCH'));
	
	
	// Load needed JS libs & CSS styles
	//JHtml::_('behavior.framework', true);
	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	
	// Add tooltips
	if ($add_tooltips) JHtml::_('bootstrap.tooltip');
	
	// Add css
	if ($add_ccs && $layout) {
		// Work around for extension that capture module's HTML 
		if ($add_ccs==2) {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css?'.FLEXI_VHASH.'">';
			}
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css?'.FLEXI_VHASH.'">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css?'.FLEXI_VHASH.'">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexi_filters.css?'.FLEXI_VHASH.'">';
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css">';
			}
		}
		
		// Standards compliant implementation by placing CSS link into the HTML HEAD
		else {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', FLEXI_VHASH);
			}
			$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexi_filters.css', FLEXI_VHASH);
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
			}
		}
	}
	
	$itemid_force = $params->get('itemid_force', '0');
	$itemid = $itemid_force ? (int) $params->get('itemid_force_value', 0)  :  0;
	
	if ($itemid) {
		$menu = $app->getMenu()->getItem($itemid);     // Retrieve active menu
		
		// Get the COMPONENT only parameters, then merge the menu parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$comp_params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		$params->merge($comp_params);
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
	}
	
	// Render Layout
	require(JModuleHelper::getLayoutPath('mod_flexiadvsearch', $layout));
	
	// append performance stats to global variable
	if ( $flexiparams->get('print_logging_info') )
	{
		$modfc_jprof->mark('END: FLEXIcontent Adv Search Module');
		$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
		global $fc_performance_msg;
		$fc_performance_msg .= $msg;
	}
}