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
$view   = JRequest::getVar('view');
$option = JRequest::getVar('option');

if ($option=='com_flexicontent')
	$_view = ($view==FLEXI_ITEMVIEW) ? 'item' : $view;
else
	$_view = 'others';

$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;

$views_show_mod =!count($show_in_views) || in_array($_view,$show_in_views);

if ($params->get('enable_php_rule', 0))
	$php_show_mod = eval($params->get('php_rule'));
else
	$php_show_mod = true;

if ($params->get('combine_show_rules', 'AND')=='AND') {
	$show_mod = $views_show_mod && $php_show_mod;
} else {
	$show_mod = $views_show_mod || $php_show_mod;
}

if ( $show_mod )
{
	global $modfc_jprof;
	jimport( 'joomla.error.profiler' );
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Adv Search Module');
	
	// load english language file for 'mod_flexiadvsearch' component then override with current language file
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, null, true);
	
	// include the helper only once
	//require_once (dirname(__FILE__).DS.'helper.php');  // currently no helper file ...
	
	// initialize various variables
	//$document	= JFactory::getDocument();
	//$config 	= JFactory::getConfig();
	//$caching 	= $config->getValue('config.caching', 0);
	$add_ccs 			= $params->get('add_ccs', 1);
	$layout       = $params->get('layout', 'default');
	$button       = $params->get('button', '');
	$button_text  = $params->get('button_text', '');
	$imagebutton  = $params->get('imagebutton', '');
	$width        = intval($params->get('width', 20));
	$maxlength    = $width > 20 ? $width : 20;
	$text         = $params->get('text', JText::_('search...'));
	$button_pos   = $params->get('button_pos', 'left');
	$linkadvsearch     = $params->get('linkadvsearch', 1);
	$linkadvsearch_txt = $params->get('linkadvsearch_txt', 'Advanced Search');
	$moduleclass_sfx   = $params->get('moduleclass_sfx', '');
	
	if($linkadvsearch && !trim($linkadvsearch_txt))
		$linkadvsearch_txt = 'Advanced Search';
	
	// add module css file
	// currently no css file for this module
	/*if ($add_ccs) {
	  if ($caching && !FLEXI_J16GE) {
			// Work around for caching bug in J1.5
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexiadvsearch/tmpl/mod_flexiadvsearch.css">';
	  } else {
	    // Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
			$document->addStyleSheet(JURI::base(true).'/modules/mod_flexiadvsearch/tmpl/mod_flexiadvsearch.css');
	  }
	}*/
	
	if ($useitemid = $params->get('useitemid', '0'))
	{
		$set_itemid = intval($params->get('set_itemid', 0));
		$mitemid = $set_itemid > 0 ? $set_itemid : JRequest::getInt('Itemid');
	}
	
	// Render Layout
	require(JModuleHelper::getLayoutPath('mod_flexiadvsearch', $layout));
	
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	if ( $flexiparams->get('print_logging_info') )
	{
		$app = JFactory::getApplication();
		$modfc_jprof->mark('END: FLEXIcontent Adv Search Module');
		$msg  = implode('<br/>', $modfc_jprof->getbuffer());
		$app->enqueueMessage( $msg, 'notice' );
	}
	
}
?>