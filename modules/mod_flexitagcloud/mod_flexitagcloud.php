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
	eval('$php_show_mod='.$params->get('php_rule').';');
else
	$php_show_mod = true;

if ($params->get('combine_show_rules', 'AND')=='AND') {
	$show_mod = $views_show_mod && $php_show_mod;
} else {
	$show_mod = $views_show_mod || $php_show_mod;
}

if ( $show_mod )
{
	// Logging Info variables
	$start_microtime = microtime(true);
	
	// load english language file for 'mod_flexitagcloud' component then override with current language file
	JFactory::getLanguage()->load('mod_flexitagcloud', JPATH_SITE, 'en-GB', true);
	JFactory::getLanguage()->load('mod_flexitagcloud', JPATH_SITE, null, true);
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	
	// initialize various variables
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

<?php
}
?>