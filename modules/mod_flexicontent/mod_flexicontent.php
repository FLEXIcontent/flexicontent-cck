<?php
/**
 * @version 1.2 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

//no direct access
defined('_JEXEC') or die('Restricted access');

global $globalcats;

// initialize
$document 	=& JFactory::getDocument();
$config 	=& JFactory::getConfig();
$caching 	= $config->getValue('config.caching', 0);

// get module ordering parameters
$ordering 				= $params->get('ordering');
if (!is_array($ordering)) { $ordering = explode(',', $ordering); }
$count 					= (int)$params->get('count', 5);
$featured				= (int)$params->get('count_feat', 1);
// get module display parameters
$moduleclass_sfx 		= $params->get('moduleclass_sfx', '');
$layout 				= $params->get('layout', 'default');
$add_ccs 				= $params->get('add_ccs', 1);
$add_tooltips 			= $params->get('add_tooltips', 1);
$width 					= $params->get('width');
$height 				= $params->get('height');
// standard
$display_title 			= $params->get('display_title');
$link_title 			= $params->get('link_title');
$display_text 			= $params->get('display_text');
$mod_readmore	 		= $params->get('mod_readmore');
$mod_use_image 			= $params->get('mod_use_image');
$mod_image 				= $params->get('mod_image');
$mod_link_image 		= $params->get('mod_link_image');
$mod_width 				= $params->get('mod_width');
$mod_height 			= $params->get('mod_height');
$mod_method 			= $params->get('mod_method');
// featured
$display_title_feat 	= $params->get('display_title_feat');
$link_title_feat 		= $params->get('link_title_feat');
$display_text_feat 		= $params->get('display_text');
$mod_readmore_feat		= $params->get('mod_readmore_feat');
$mod_use_image_feat 	= $params->get('mod_use_image_feat');
$mod_link_image_feat 	= $params->get('mod_link_image_feat');
$mod_width_feat 		= $params->get('mod_width_feat');
$mod_height_feat 		= $params->get('mod_height_feat');
$mod_method_feat 		= $params->get('mod_method_feat');

// get module fields parameters
$use_fields 			= $params->get('use_fields');
$display_label 			= $params->get('display_label');
$fields 				= $params->get('fields');
// featured
$display_label_feat 	= $params->get('display_label_feat');
$fields_feat 			= $params->get('fields_feat');

// custom parameters
$custom1 				= $params->get('custom1');
$custom2 				= $params->get('custom2');
$custom3 				= $params->get('custom3');
$custom4 				= $params->get('custom4');
$custom5 				= $params->get('custom5');

//Include the helper only once
require_once (dirname(__FILE__).DS.'helper.php');

$list = modFlexicontentHelper::getList($params);

/*
$c = 0;
foreach ($list as $l) {
	$c = $c + count($l);
}

if (!$c) {
	$module->position = 'none';
	$module->showtitle = '';
	return;
}
*/

global ${$layout};

if ($add_ccs && !$caching && !${$layout}) {
	if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
		// active layout css
		$document->addStyleSheet(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/'.$layout.'.css');
		${$layout} = 1;
	}
}

require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout));