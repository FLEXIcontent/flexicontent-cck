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

//blocage des accés directs sur ce script
defined('_JEXEC') or die('Restricted access');

// Check if component is installed
jimport( 'joomla.application.component.controller' );
if ( !JComponentHelper::isEnabled( 'com_flexicontent', true) )
{
	echo '<div class="alert alert-warning">This module requires FLEXIcontent package to be installed</div>';
	return;
}


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


// ***
// *** TERMINATE if not assigned to current view
// ***
if ( !$show_mod )  return;



$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));

// Include helper file
require_once dirname(__FILE__).'/helper.php';

$tMapTips = modFlexigooglemapHelper::renderMapLocations($params);
$markerdisplay = modFlexigooglemapHelper::getMarkerURL($params);

// Get Joomla Layout
require JModuleHelper::getLayoutPath('mod_flexigooglemap', $params->get('layout', 'default'));