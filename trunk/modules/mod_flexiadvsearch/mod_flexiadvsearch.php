<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIadvsearch Module
 * @copyright (C) 2011 flexicontent.org
 * @license GNU/GPL v3
 * 
 * FLEXIadvsearch module is an advanced search module for flexicontent.
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
	$modfc_jprof->mark('START: FLEXIcontent Adv Search Module');
	
	// load english language file for 'mod_flexiadvsearch' module then override with current language file
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, null, true);
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');  // currently helper class is empty ...
	
	// initialize various variables
	$document	= JFactory::getDocument();
	$caching 	= $app->getCfg('caching', 0);
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', 1);
	$add_tooltips 	= $params->get('add_tooltips', 1);
	
	$text      = JText::_($params->get('text', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
	$width     = intval($params->get('width', 20));
	$maxlength = $width > 20 ? $width : 20;
	
	// display button and its position
	$button       = $params->get('button', '');
	$button_pos   = $params->get('button_pos', 'left');
	
	// button as image or as text
	$button_as    = $params->get('button_as', '');
	$button_text  = JText::_($params->get('button_text', 'FLEXI_ADV_MOD_GO'));
	$button_image = $params->get('button_image', '');
	
	$linkadvsearch     = $params->get('linkadvsearch', 1);
	$linkadvsearch_txt = JText::_($params->get('linkadvsearch_txt', 'FLEXI_ADV_MOD_ADVANCED_SEARCH'));
	
	// Currently no JS/CSS needed
	
	// Load needed JS libs & CSS styles
	FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	
	// Add tooltips
	if ($add_tooltips) JHTML::_('behavior.tooltip');
	
	// Add css
	if ($add_ccs && $layout) {
		if ($caching && !FLEXI_J16GE) {
			// Work around for caching bug in J1.5
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexiadvsearch/tmpl/'.$layout.'/'.$layout.'.css">';
			}
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexiadvsearch/tmpl_common/module.css">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css">';
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css">';
			}
		} else {
			// Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				$document->addStyleSheet(JURI::base(true).'/modules/mod_flexiadvsearch/tmpl/'.$layout.'/'.$layout.'.css');
			}
			$document->addStyleSheet(JURI::base(true).'/modules/mod_flexiadvsearch/tmpl_common/module.css');
			$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css');
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
			}
		}
	}
	
	$itemid_force = $params->get('itemid_force', '0');
	$itemid = $itemid_force ? (int) $params->get('itemid_force_value', 0)  :  0;
	
	if ($itemid) {
		$menu = JSite::getMenu()->getItem($itemid);     // Retrieve active menu
		
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
	
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$modfc_jprof->mark('END: FLEXIcontent Adv Search Module');
		$msg  = implode('<br/>', $modfc_jprof->getbuffer());
		$app->enqueueMessage( $msg, 'notice' );
	}

}
?>