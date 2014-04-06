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



// *************************
// Initialize some variables
// *************************

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



// *****************
// Language handling
// *****************

// Load english language file for 'com_content' component then override with current language file
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

// Load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

// Load language overrides, just before executing the component (DONE manually for J1.5)
$overrideDir = JPATH_ADMINISTRATOR .DS. 'languages' .DS. 'overrides' .DS;
if (!FLEXI_J16GE) {
	JFactory::getLanguage()->load('override', $overrideDir, 'en-GB', true);
	JFactory::getLanguage()->load('override', $overrideDir, null, true);
}


// ********************************
// Load common js libs / frameworks
// ********************************

if ( JRequest::getWord('format')!='raw') {
	// Load mootools
	FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
	// Load jquery Framework
	flexicontent_html::loadJQuery();
}



// ***********************************
// PREPARE Calling the controller task
// ***********************************

// a. Get view, task, controller REQUEST variables
$view = JRequest::getWord( 'view' );
$controller = JRequest::getWord( 'controller' );
$task = JRequest::getVar( 'task' );


// b. In J1.6+ controller is set via task variable ... split task from controller name
if (FLEXI_J16GE) {
	$_ct = explode('.', $task);
	$task = $_ct[ count($_ct) - 1];
	if (count($_ct) > 1) $controller = $_ct[0];
}


// c. Force variables: controller AND/OR task
$forced_views = array('category'=>1);  // *** Cases that view variable must be ignored
if ( isset($forced_views[$controller]) )  JRequest::setVar('view', $view=$controller);

if ( file_exists( JPATH_COMPONENT.DS.'controllers'.DS.$view.'.php' ) ) {
	
	// FORCE (if it exists) using controller named as current view name (thus ignoring controller set in HTTP REQUEST)
	$controller = $view;
	
} else {
	
	// Singular views do not (usually) have a controller, instead the 'Plural' controller is used
	// Going through the controller makes sure that appropriate code is always executed
	// Views/Layouts that can be called without a forced controller task (and without redirect to them, these must contain permission checking)
	$view_to_ctrl = array(
		'type'=>'types', 'item'=>'items', 'field'=>'fields', 'tag'=>'tags',
		'category'=>'categories', 'user'=>'users', 'file'=>'filemanager'
	);
	if ( isset($view_to_ctrl[$view]) ) {
		$controller = $view_to_ctrl[$view];
		if ( !$task ) $task = 'edit';  // default task for singular views is edit
	}
}


// d. Set changes to controller/task variables back to HTTP REQUEST
if ( FLEXI_J16GE && $controller && $task) $task = $controller.'.'.$task;
JRequest::setVar('controller', $ctrlname=$controller);
JRequest::setVar('task', $task);   //echo "$controller -- $task <br/>\n";



// ************************************
// Files needed for user groups manager
// ************************************

if (FLEXI_J16GE && ($view=='group' || $view=='groups' || $ctrlname=='group' || $ctrlname=='groups') ) {
	// Load english language file for 'com_users' component then override with current language file
	JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, 'en-GB', true);
	JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, null, true);
	// users helper file
	require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'users.php');
}



// **************************************************************************************************************
// Include the component base AND FOR J1.5 ONLY: the view-specific controller (in J1.6+ is included automatically)
// **************************************************************************************************************

require_once (JPATH_COMPONENT.DS.'controller.php');
if (!FLEXI_J16GE) {
	if( $controller = JRequest::getWord('controller') ) {
		$path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
		if (file_exists($path)) {
			require_once $path;
		} else {
			$controller = '';
		}
	}
}


// initialization done ... log stats for initialization
if ( $print_logging_info ) @$fc_run_times['initialize_component'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


// ****************************
// Create a controller instance
// ****************************

if (FLEXI_J16GE) {
	$controller	= JControllerLegacy::getInstance('Flexicontent');
} else {
	$classname  = 'FlexicontentController'.ucfirst($controller);
	$controller = new $classname();
}


// **************************
// Perform the requested task
// **************************

$controller->execute( JRequest::getCmd('task') );



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
		
		
	if (isset($fc_run_times['post_installation_tasks']))
		$msg .= sprintf('<br/>-- [Post installation task: %.2f s] ', $fc_run_times['post_installation_tasks']/1000000);
		
			$msg .= '<small>';
			if (isset($fc_run_times['getExistMenuItems']))
				$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Default menu items exist: %.2f s ', $fc_run_times['getExistMenuItems']/1000000);
			if (isset($fc_run_times['getItemsNoLang']))
				$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Items with no Language: %.2f s ', $fc_run_times['getItemsNoLang']/1000000);
			if (isset($fc_run_times['getItemCountingDataOK']))
				$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Item temporary accounting data: %.2f s ', $fc_run_times['getItemCountingDataOK']/1000000);
			if (isset($fc_run_times['checkInitialPermission']))
				$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Check initial permissions: %.2f s ', $fc_run_times['checkInitialPermission']/1000000);
			$msg .= '</small>';
	
	
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

	// **** BOF: BACKEND SPECIFIC
	if (isset($fc_run_times['unassoc_items_query']))
		$msg .= sprintf('<br/>-- [Execute Unassociate Items Query: %.2f s] ', $fc_run_times['unassoc_items_query']/1000000);
	// **** EOF: BACKEND SPECIFIC
	
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
	
	if (isset($fc_run_times['quick_sliders']))
		$msg .= sprintf('<br/>-- [Workflow sliders (Pending/Revised/etc): %.2f s] ', $fc_run_times['quick_sliders']/1000000);
	
	if (isset($fc_run_times['auto_checkin_auto_state']))
		$msg .= sprintf('<br/>-- [Auto Checkin/Auto state(e.g. archive): %.2f s] ', $fc_run_times['auto_checkin_auto_state']/1000000);
	
	
	// **********************
	// Fields rendering times
	// **********************
	
	if (count($fields_render_times)) {
		$msg .= sprintf('<br/>-- [FC Fields Rendering: %.2f s] ', $fields_render_total/1000000);
		$msg .= '<br/>FIELD: '.implode('<br/> FIELD: ', $fields_render_times).'';
	}
	
	$msg .= '</span>';
	
	$app->enqueueMessage( $msg, 'notice' );
}



// ************************************************************************
// Redirect if a redirect URL was set, e.g. by the executed controller task
// ************************************************************************

$controller->redirect();
?>