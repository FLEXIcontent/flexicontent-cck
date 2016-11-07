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

// Include helper file
require_once dirname(__FILE__).'/helper.php';

$itemsLoc = modFlexigooglemapHelper::getLoc($params);
$tMapTips = modFlexigooglemapHelper::fixeCatmode($params, $itemsLoc);
$catidmode = $params->get('catidmode');
$catid = $params->get('catid');

$fieldaddressid = $params->get('fieldaddressid');
$count = $params->get('count');

$markercolor = $params->get('markercolor');
$lettermarker = $params->get('lettermarker');
$lettermarkermode = $params->get('lettermarkermode');

$markerdisplay = modFlexigooglemapHelper::getMarkercolor($params);
$relitem_html  = $params->get('relitem_html', '__display_text__' ) ;

$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));

// Get Joomla Layout
require JModuleHelper::getLayoutPath('mod_flexigooglemap', $params->get('layout', 'default'));
