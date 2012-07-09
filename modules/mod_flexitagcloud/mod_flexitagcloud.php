<?php
/**
 * @version 1.1 $Id$
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


if ($add_ccs) {
  if ($caching && !FLEXI_J16GE) {
		// Work around for caching bug in J1.5
		echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css">';
  } else {
    // Standards compliant implementation for >= J1.6 or earlier versions without caching disabled
		$document->addStyleSheet(JURI::base(true).'/modules/mod_flexitagcloud/tmpl/mod_flexitagcloud.css');
  }
}


$list = modFlexiTagCloudHelper::getTags($params, $module);
require(JModuleHelper::getLayoutPath('mod_flexitagcloud'));

$flexiparams =& JComponentHelper::getParams('com_flexicontent');
if ( $flexiparams->get('print_logging_info') ) {
	$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	$app = & JFactory::getApplication();
	$app->enqueueMessage( sprintf( 'FLEXIcontent tags cloud module creation is %.2f secs', $elapsed_microseconds/1000000), 'notice' );
}
?>