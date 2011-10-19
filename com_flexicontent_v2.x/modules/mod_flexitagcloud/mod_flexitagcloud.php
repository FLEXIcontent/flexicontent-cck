<?php
/**
 * @version 1.1 $Id: mod_flexitagcloud.php 907 2011-09-19 00:21:53Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Tag Cloud Module
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

//Include the only once
require_once (dirname(__FILE__).DS.'helper.php');

$document 	= & JFactory::getDocument();
$config 	=& JFactory::getConfig();
$caching 	= $config->getValue('config.caching', 0);
$add_ccs 	= $params->get('add_ccs', 1);

// Only when caching not active, we can be xhtml compliant by inserting css file at the html head
if ($add_ccs && !$caching) {
	$document->addStyleSheet(JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css');
}

// Only when caching is active, we insert somewhere inside body, which is not xhtml compliant, but this is ok for all browsers
if ($add_ccs && $caching) {
	// active layout css
	echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css">';
}

$list = modFlexiTagCloudHelper::getTags($params);
require(JModuleHelper::getLayoutPath('mod_flexitagcloud'));