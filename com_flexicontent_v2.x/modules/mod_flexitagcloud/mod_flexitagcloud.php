<?php
/**
 * @version 1.1 $Id: mod_flexitagcloud.php 1312 2012-05-17 01:08:16Z ggppdk $
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

// Logging Info variables
$start_microtime = microtime(true);

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

$list = modFlexiTagCloudHelper::getTags($params, $module);
require(JModuleHelper::getLayoutPath('mod_flexitagcloud'));

$params =& JComponentHelper::getParams('com_flexicontent');
if ( $params->get('print_logging_info') ) {
	$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	$app = & JFactory::getApplication();
	$app->enqueueMessage( sprintf( 'FLEXIcontent tags cloud module creation is %.2f secs', $elapsed_microseconds/1000000), 'notice' );
}
?>