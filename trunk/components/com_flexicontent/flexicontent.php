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

// no direct access
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


// *************************
// Initialize some variables
// *************************

global $is_fc_component;
$is_fc_component = 1;

// Get component parameters and add tooltips css and js code
$cparams = JComponentHelper::getParams('com_flexicontent');
$print_logging_info = $cparams->get('print_logging_info');
if ( $print_logging_info ) {
	global $fc_run_times;
	$fc_run_times['render_field'] = array(); $fc_run_times['render_subfields'] = array();
	$start_microtime = microtime(true);
	global $fc_jprof;
	jimport( 'joomla.error.profiler' );
	$fc_jprof = new JProfiler();
	$fc_jprof->mark('START: FLEXIcontent component');
}



// ********************************
// Load needed helper/classes files
// ********************************

//include constants file
require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'defineconstants.php');

//include the needed classes and helpers
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'permission.php');
require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'route.php');

// Add component's table directory to the include path
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// No PDF support in J2.5
if ( FLEXI_J16GE && JRequest::getVar('format') == 'pdf' ) JRequest::setVar('format', 'html');


// *****************
// Language handling
// *****************

// Load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

// Load language overrides, just before executing the component (DONE manually for J1.5)
$overrideDir = JPATH_SITE .DS. 'languages' .DS. 'overrides' .DS;
if (!FLEXI_J16GE) {
	JFactory::getLanguage()->load('override', $overrideDir, 'en-GB', true);
	JFactory::getLanguage()->load('override', $overrideDir, null, true);
}


// ********************************
// Load common js libs / frameworks
// ********************************

if ($cparams->get('add_tooltips', 1)) {
	// Load mootools
	FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
	JHTML::_('behavior.tooltip');
}



// ***********************************
// PREPARE Calling the controller task
// ***********************************

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
$ctrlname = $controller;


// **************************************************************************************************************
// Include the component base AND FOR J1.5 ONLY: the view-specific controller (in J1.6+ is included automatically)
// **************************************************************************************************************

require_once (JPATH_COMPONENT.DS.'controller.php');
if ($controller) {
	$path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
	
	// Views allowed to have a controller
	if ( file_exists($path) ) {
		require_once $path;
	} else {
		JRequest::setVar('controller', $controller = '');
	}
}


// ****************************
// Create a controller instance
// ****************************

$classname	= 'FlexicontentController'.ucfirst($controller);
$controller = new $classname();


// initialization done ... log stats for initialization
if ( $print_logging_info ) @$fc_run_times['initialize_component'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



// **************************
// Perform the requested task
// **************************

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


// *********************************************************************************************
// Enqueue PERFORMANCE statistics as a message BUT  NOT if in RAW FORMAT or COMPONENT only views
// *********************************************************************************************

if ( $print_logging_info && JRequest::getWord('tmpl')!='component' && JRequest::getWord('format')!='raw')
{
	
	// ***************************************
	// Total performance stats of current view
	// ***************************************
	
	$app = JFactory::getApplication();
	$_view = JRequest::getWord('view','flexicontent');
	$_layout = JRequest::getWord('layout','');
	if ($task) $_msg = ' (TASK: '.(!FLEXI_J16GE ? $ctrlname.'.' : '').$task.')';
	else       $_msg = ' (VIEW: ' .$_view. ($_layout ? ' -- LAYOUT: '.$_layout : '') .')';
	
	
	// **************************************
	// Various Partial time performance stats
	// **************************************
	$fields_render_total=0;
	$fields_render_times = FlexicontentFields::getFieldRenderTimes($fields_render_total);
	
	$fc_jprof->mark('END: FLEXIcontent component: '.$_msg);
	$msg = '<span style="font-family:tahoma!important; font-size:11px!important;">'. implode('<br/>', $fc_jprof->getbuffer()) .'</span>';
	
	$msg .= '<span style="font-family:tahoma!important; font-size:11px!important;">';
		
	if (isset($fc_run_times['initialize_component']))
		$msg .= sprintf('<br/>-- [Initialize component: %.2f s] ', $fc_run_times['initialize_component']/1000000);
	
	if (isset($fc_run_times['test_time']))
		$msg .= sprintf('<br/>-- [Time of TEST part: %.2f s] ', $fc_run_times['test_time']/1000000);
	
	if (isset($fc_run_times['item_store_prepare']))
		$msg .= sprintf('<br/>-- [Prepare item store: %.2f s] ', $fc_run_times['item_store_prepare']/1000000);
	
	if (isset($fc_run_times['onBeforeSaveItem_event']) && $fc_run_times['onBeforeSaveItem_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [FLEXIcontent plugins (event: onBeforeSaveItem): %.2f s] ', $fc_run_times['onBeforeSaveItem_event']/1000000);
	
	if (isset($fc_run_times['onContentBeforeSave_event']) && $fc_run_times['onContentBeforeSave_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [Joomla Content (event: onContentBeforeSave): %.2f s] ', $fc_run_times['onContentBeforeSave_event']/1000000);
	
	if (isset($fc_run_times['item_store_core']))
		$msg .= sprintf('<br/>-- [Store item core data: %.2f s] ', $fc_run_times['item_store_core']/1000000);

	
	if (isset($fc_run_times['execute_main_query']))
		$msg .= sprintf('<br/>-- [Execute Main Query: %.2f s] ', $fc_run_times['execute_main_query']/1000000);
	
	if (isset($fc_run_times['execute_sec_queries']))
		$msg .= sprintf('<br/>-- [Execute Secondary Query(-ies): %.2f s] ', $fc_run_times['execute_sec_queries']/1000000);
	
	// **** BOF: ITEM FORM SAVING
	if (isset($fc_run_times['onAfterSaveField_event']) && $fc_run_times['onAfterSaveField_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [FLEXIcontent plugins (event: onAfterSaveField): %.2f s] ', $fc_run_times['onAfterSaveField_event']/1000000);
	
	if (isset($fc_run_times['onAfterSaveItem_event']) && $fc_run_times['onAfterSaveItem_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [FLEXIcontent plugins (event: onAfterSaveItem): %.2f s] ', $fc_run_times['onAfterSaveItem_event']/1000000);
	
	if (isset($fc_run_times['onContentAfterSave_event']) && $fc_run_times['onContentAfterSave_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [Joomla Content/Smart Index plugins (event: onContentAfterSave): %.2f s] ', $fc_run_times['onContentAfterSave_event']/1000000);
	
	if (isset($fc_run_times['onCompleteSaveItem_event']) && $fc_run_times['onCompleteSaveItem_event']/1000000 >= 0.01)
		$msg .= sprintf('<br/>-- [FLEXIcontent plugins (event: onCompleteSaveItem): %.2f s] ', $fc_run_times['onCompleteSaveItem_event']/1000000);
	
	if (isset($fc_run_times['ver_cleanup_ver_metadata']))
		$msg .= sprintf('<br/>-- [Version Cleanup and Version Metadata: %.2f s] ', $fc_run_times['ver_cleanup_ver_metadata']/1000000);
	
	if (isset($fc_run_times['fields_value_preparation']))
		$msg .= sprintf('<br/>-- [Fields value preparation: %.2f s] ', $fc_run_times['fields_value_preparation']/1000000);
		
	if (isset($fc_run_times['fields_value_indexing']))
		$msg .= sprintf('<br/>-- [Fields value Indexing: %.2f s] ', $fc_run_times['fields_value_indexing']/1000000);
	
	if (isset($fc_run_times['fields_value_saving']))
		$msg .= sprintf('<br/>-- [Fields value saving: %.2f s] ', $fc_run_times['fields_value_saving']/1000000);
	// **** EOF: ITEM FORM SAVING
	
	if (isset($fc_run_times['templates_parsing_cached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (cached): %.2f s] ', $fc_run_times['templates_parsing_cached']/1000000);
	
	if (isset($fc_run_times['templates_parsing_noncached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (not cached) : %.2f s] ', $fc_run_times['templates_parsing_noncached']/1000000);
	
	// **** BOF: FRONTEND SPECIFIC
	if (isset($fc_run_times['filter_creation']))
		$msg .= sprintf('<br/>-- [FC Filter Creation: %.2f s] ', $fc_run_times['filter_creation']/1000000);
	
	if (isset($fc_run_times['search_query_runtime']))
		$msg .= sprintf('<br/>-- [FC Advanced Search Plugin, Query: %.2f s] ', $fc_run_times['search_query_runtime']/1000000);
	
	if (isset($fc_run_times['content_plg']))
		$msg .= sprintf('<br/>-- [Joomla Content Plugins: %.2f s] ', $fc_run_times['content_plg']/1000000);
	// **** EOF: FRONTEND SPECIFIC
	
	if (isset($fc_run_times['get_item_data']))
		$msg .= sprintf('<br/>-- [Get/Calculate Item Properties: %.2f s] ', $fc_run_times['get_item_data']/1000000);
	
	if (isset($fc_run_times['get_field_vals']))
		$msg .= sprintf('<br/>-- [Retrieve Field Values: %.2f s] ', $fc_run_times['get_field_vals']/1000000);
	
	if (isset($fc_run_times['render_field_html']))
		$msg .= sprintf('<br/>-- [Field HTML Rendering: %.2f s] ', $fc_run_times['render_field_html']/1000000);
	
	if (isset($fc_run_times['form_rendering']))
		$msg .= sprintf('<br/>-- [Form Template Rendering: %.2f s] ', $fc_run_times['form_rendering']/1000000);
	
	if (isset($fc_run_times['render_categories_select']))
		$msg .= sprintf('<br/>-- [Render Categories Select: %.2f s] ', $fc_run_times['render_categories_select']/1000000);
	
	if (count($fields_render_times))
		$msg .= sprintf('<br/>-- [FC Fields Value Retrieval: %.2f s] ', $fc_run_times['field_value_retrieval']/1000000);
	
	if (isset($fc_run_times['template_render']))
		$msg .= sprintf('<br/>-- [FC "%s" view Template Rendering: %.2f s] ', $view, $fc_run_times['template_render']/1000000);
	
	
	// **********************
	// Fields rendering times
	// **********************
	
	if (count($fields_render_times)) {
		$msg .= sprintf('<br/>-- [FC Fields Rendering: %.2f s] ', $fields_render_total/1000000);
		$msg .= '<br/>FIELD: '.implode('<br/> FIELD: ', $fields_render_times).'';
	}
	
	$msg .= '</span>';
	
	
	// SYSTEM PLGs
	if (isset($fc_run_times['auto_checkin_auto_state']))
		$msg = sprintf('<br/><small>** [Flexisystem PLG: Auto Checkin/Auto state(e.g. archive): %.2f s] ', $fc_run_times['auto_checkin_auto_state']/1000000) .'</small><br/>'.$msg;
	
	if (isset($fc_run_times['global_field_replacements']))
		$msg = sprintf('<br/><small>** [Flexisystem PLG: Replace Field Times: %.2f s] ', $fc_run_times['global_field_replacements']/1000000) .'</small><br/>'.$msg;
	
	$app->enqueueMessage( $msg, 'notice' );
}
unset ($is_fc_component);

?>