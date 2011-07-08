<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIadvsearch Module
 * @copyright (C) 2011 flexicontent.org
 * @license GNU/GPL v3
 * 
 * FLEXIadvsearch module is an advanced search module for flexicontent.
 * FLEXIadvsearch is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

//no direct access
defined('_JEXEC') or die('Restricted access');
$layout = $params->get('layout', 'default');
$button = $params->get('button', '');
$button_text = $params->get('button_text', '');
$imagebutton = $params->get('imagebutton', '');
$moduleclass_sfx = $params->get('moduleclass_sfx', '');
$width			 = intval($params->get('width', 20));
$maxlength		 = $width > 20 ? $width : 20;
$text			 = $params->get('text', JText::_('search...'));
$button_pos		 = $params->get('button_pos', 'left');
$linkadvsearch		 = $params->get('linkadvsearch', 1);
$linkadvsearch_txt		 = $params->get('linkadvsearch_txt', 'Advanced Search');
if($linkadvsearch && !trim($linkadvsearch_txt)) $linkadvsearch_txt = 'Advanced Search';
if ($imagebutton) {
    $img = modSearchHelper::getSearchImage( $button_text );
}
if($useitemid = $params->get('useitemid', '0')) {
	$set_Itemid		 = intval($params->get('set_itemid', 0));
	$mitemid = $set_Itemid > 0 ? $set_Itemid : JRequest::getInt('Itemid');
}
require(JModuleHelper::getLayoutPath('mod_flexiadvsearch', $layout));
