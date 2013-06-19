<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
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

defined( '_JEXEC' ) or die( 'Restricted access' );

// Do nothing if site is offline
if ( JFactory::getApplication()->getCfg('offline') ) {
	$user = JFactory::getUser();
	if (!$user->id) {
		return;
	} else {
		jimport( 'joomla.version' );  $jversion = new JVersion;
		define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
		$isAdmin = FLEXI_J16GE ? JAccess::check($user->id, 'core.admin', 'root.1') : $user->gid >= 24;
		if (!$isAdmin) return;
	}
}

$lhlist = array('localhost', '127.0.0.1');
if( in_array($_SERVER['HTTP_HOST'], $lhlist) ) {
	error_reporting(E_ALL & ~E_STRICT);
	ini_set('display_errors',1);
}

global $is_fc_component;
$is_fc_component = 1;

// Get component parameters and add tooltips css and js code
$cparams = JComponentHelper::getParams('com_flexicontent');
if ($cparams->get('add_tooltips', 1)) JHTML::_('behavior.tooltip');

if ( $cparams->get('print_logging_info') ) {
	// Logging Info variables
	global $fc_run_times;
	$fc_run_times['render_field'] = array(); $fc_run_times['render_subfields'] = array();
	
	global $fc_jprof;
	jimport( 'joomla.error.profiler' );
	$fc_jprof = new JProfiler();
	$fc_jprof->mark('START: FLEXIcontent component');
}

// load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

//include constants file
require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'defineconstants.php');
//include the route helper
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'route.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT.DS.'helpers'.DS.'permission.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// No PDF support in J2.5
if ( FLEXI_J16GE && JRequest::getVar('format') == 'pdf' )
{
	JRequest::setVar('format', 'html');
}

// Require the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

$view = JRequest::getCmd('view');
$task = JRequest::getCmd('task');
$tasks = explode(".", $task);
if(count($tasks)>=2) {
	$controller = @$controller ? $controller : $tasks[0];
	$task = $tasks[1];
	JRequest::setVar('task', $tasks[1]);
} else {
	$controller = JRequest::getWord('controller');
}

// Require specific controller if requested
if ($controller) {
	$path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
	
	// Views allowed to have a controller
	if ( file_exists($path) ) {
		require_once $path;
	} else {
		JRequest::setVar('controller', $controller = '');
	}
}

// Create the controller
$classname	= 'FlexicontentController'.ucfirst($controller);
$controller = new $classname( );

// load template language overrides, just before executing the component
$templateDir = JURI::base() . 'templates/' . JFactory::getApplication()->getTemplate();
JFactory::getLanguage()->load('com_flexicontent', $templateDir, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', $templateDir, null, true);

// Perform the Request task
$controller->execute($task);

// Redirect if set by the controller
$controller->redirect();

// TO BE MOVED TO HELPER FILE ...
// Remove (thus prevent) the default menu item from showing in the pathway
if ( $cparams->get('default_menuitem_nopathway',1) ) {
	$pathway =  JFactory::getApplication()->getPathWay();
	$default_menu_itemid = $cparams->get('default_menu_itemid', 0);
	$pathway_arr = $pathway->getPathway();
	if ( count($pathway_arr) && preg_match("/Itemid=([0-9]+)/",$pathway_arr[0]->link, $matches) ) {
		if ($matches[1] == $default_menu_itemid) {
			array_shift ($pathway_arr);
			$pathway->setPathway($pathway_arr);
			$pathway->set('_count',count($pathway_arr));
		}
	}
}

if ( $cparams->get('print_logging_info') && JRequest::getWord('tmpl')!='component' && JRequest::getWord('format')!='raw') {
	$fc_jprof->mark('END: FLEXIcontent component');
	$fields_render_total=0;
	$fields_render_times = FlexicontentFields::getFieldRenderTimes($fields_render_total);
	
	$app = JFactory::getApplication();
	$msg = implode('<br/>', $fc_jprof->getbuffer());
	
	$msg .= '<code>';
	
	if (isset($fc_run_times['execute_main_query']))
		$msg .= sprintf('<br/>-- [Execute Main Query: %.2f s] ', $fc_run_times['execute_main_query']/1000000);
	
	if (isset($fc_run_times['execute_sec_queries']))
		$msg .= sprintf('<br/>-- [Execute Secondary Queries: %.2f s] ', $fc_run_times['execute_sec_queries']/1000000);
	
	if (isset($fc_run_times['templates_parsing_cached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (cached): %.2f s] ', $fc_run_times['templates_parsing_cached']/1000000);
	
	if (isset($fc_run_times['templates_parsing_noncached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (not cached) : %.2f s] ', $fc_run_times['templates_parsing_noncached']/1000000);
	
	if (isset($fc_run_times['filter_creation']))
		$msg .= sprintf('<br/>-- [FC Filter Creation: %.2f s] ', $fc_run_times['filter_creation']/1000000);
	
	if (isset($fc_run_times['search_query_runtime']))
		$msg .= sprintf('<br/>-- [FC Advanced Search Plugin, Query: %.2f s] ', $fc_run_times['search_query_runtime']/1000000);
	
	if (isset($fc_run_times['template_render']))
		$msg .= sprintf('<br/>-- [FC "%s" view Template Rendering: %.2f s] ', $view, $fc_run_times['template_render']/1000000);
	
	if (count($fields_render_times))
		$msg .= sprintf('<br/>-- [FC Fields Value Retrieval: %.2f s] ', $fc_run_times['field_value_retrieval']/1000000);
	
	if (isset($fc_run_times['content_plg']))
		$msg .= sprintf('<br/>-- [Joomla Content Plugins: %.2f s] ', $fc_run_times['content_plg']/1000000);
	
	if (isset($fc_run_times['get_item_data']))
		$msg .= sprintf('<br/>-- [Get/Caculate Item Properties: %.2f s] ', $fc_run_times['get_item_data']/1000000);
	
	if (isset($fc_run_times['get_field_vals']))
		$msg .= sprintf('<br/>-- [Retrieve Field Values: %.2f s] ', $fc_run_times['get_field_vals']/1000000);
	
	if (isset($fc_run_times['render_field_html']))
		$msg .= sprintf('<br/>-- [Field HTML Rendering: %.2f s] ', $fc_run_times['render_field_html']/1000000);
	
	if (isset($fc_run_times['form_rendering']))
		$msg .= sprintf('<br/>-- [Form Template Rendering: %.2f s] ', $fc_run_times['form_rendering']/1000000);
	
	
	if (count($fields_render_times)) {
		$msg .= sprintf('<br/>-- [FC Fields Rendering: %.2f s] ', $fields_render_total/1000000);
		$msg .= '<br/>FIELD: '.implode('<br/> FIELD: ', $fields_render_times).'';
	}
	
	$msg .= '</code>';
	
	$app->enqueueMessage( $msg, 'notice' );
}
unset ($is_fc_component);

?>