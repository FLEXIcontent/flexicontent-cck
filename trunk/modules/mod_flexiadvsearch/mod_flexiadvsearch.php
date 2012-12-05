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
	// load english language file for 'mod_flexiadvsearch' component then override with current language file
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexiadvsearch', JPATH_SITE, null, true);
	
	// initialize various variables
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
	
	if ($imagebutton)
	{
		if (FLEXI_J16GE)
			$img = JHtml::_('image','searchButton.gif', $button_text, NULL, true, true);
		else
			$img = JHTML::_('image.site', 'searchButton.gif', '/images/M_images/', NULL, NULL, $button_text, null, 0);
	}
	
	if ($useitemid = $params->get('useitemid', '0'))
	{
		$set_Itemid		 = intval($params->get('set_itemid', 0));
		$mitemid = $set_Itemid > 0 ? $set_Itemid : JRequest::getInt('Itemid');
	}
	
	// include the helper only once
	require(JModuleHelper::getLayoutPath('mod_flexiadvsearch', $layout));
	?>

<?php
}
?>