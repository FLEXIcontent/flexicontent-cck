<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

jimport('cms.component.helper');
jimport('cms.plugin.helper');



/**
 * Initialize some variables
 */

global $is_fc_component;
$is_fc_component = 1;

$cparams = JComponentHelper::getParams('com_flexicontent');
$app     = JFactory::getApplication();
$document= JFactory::getDocument();
$jinput  = $app->input;
$format  = $jinput->get('format', 'html', 'cmd');

// Logging
global $fc_run_times;
$fc_run_times['render_field'] = array();
$fc_run_times['render_subfields'] = array();

$force_print = false || JDEBUG;

// Force post-install check for testing purposes
/*if ( $format == "html" ) {
	$session = JFactory::getSession();
	$postinst_integrity_ok = $session->get('flexicontent.postinstall');
	$recheck_aftersave = $session->get('flexicontent.recheck_aftersave');
	$force_print = $postinst_integrity_ok===NULL || $postinst_integrity_ok===false || $recheck_aftersave;
}*/

if ($force_print) $cparams->set('print_logging_info', 2);
$print_logging_info = $cparams->get('print_logging_info');

if ($print_logging_info && $format === 'html')
{
	$start_microtime = microtime(true);
	global $fc_jprof;
	jimport('joomla.profiler.profiler');
	$fc_jprof = new JProfiler();
	$fc_jprof->mark('START: FLEXIcontent component');
}



/**
 * Load needed helper/classes files
 */

//include constants file
require_once (JPATH_ADMINISTRATOR.'/components/com_flexicontent/defineconstants.php');

//include the needed classes and helpers
require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.helper.php');
require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.categories.php');
require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.fields.php');
require_once (JPATH_SITE.'/components/com_flexicontent/helpers/permission.php');
require_once (JPATH_SITE.'/components/com_flexicontent/helpers/route.php');

// Add component's table directory to the include path
JTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_flexicontent/tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
{
	JPluginHelper::importPlugin('flexicontent_fields');
}
JPluginHelper::importPlugin('flexicontent');



/**
 * Language handling
 */

// Load language file of 'com_content' component
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR);

if ( JFactory::getLanguage()->getDefault() != 'en-GB' )
{
	// If site default language is not english then load english language file for 'com_flexicontent' component, and the override (forcing a reload) with current language file
	// We make sure that 'english' file has been loaded, because we need it as fallback for language strings that do not exist in current language
	JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', $force_reload = false, $load_default = true);
	JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, $force_reload = true, $load_default = true);
}

else
	// No force loading needed, save some time, and do not force language file reload
	JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR);

// Load language overrides, just before executing the component (DONE manually for J1.5)
/*if (!FLEXI_J16GE)
{
	$overrideDir = JPATH_ADMINISTRATOR . '/languages/overrides/';
	JFactory::getLanguage()->load('override', $overrideDir, 'en-GB', $force_reload = true, $load_default = true);
	JFactory::getLanguage()->load('override', $overrideDir, null, $force_reload = true, $load_default = true);
}*/



/**
 * Prepare calling the controller task
 */

// Get view, task, controller, layout REQUEST variables
$view = $jinput->get('view', '', 'cmd');
$task = $jinput->get('task', '', 'cmd');
$controller = $jinput->get('controller', '', 'cmd');
//$layout   = $jinput->get('layout', '', 'cmd');  // UNUSED in backend

// Controller can be set via task variable, split task from controller name
$_ct = explode('.', $task);
$task = $_ct[ count($_ct) - 1];
if (count($_ct) > 1)
{
	$controller = $_ct[0];
}


// Cases that view variable must be ignored, and instead use the controller name as view
$forced_views = array(
	'category' => 1,
);
if ( isset($forced_views[$controller]) )
{
	$view = $controller;
	$jinput->set('view', $view);
}



// ***
// *** Force variables: controller AND/OR task,
// *** (thus ignoring controller set in HTTP REQUEST)
// ***
// *** Going through the controller makes sure that appropriate code and permission checking is always executed
// *** Any views / layouts that can be called without a forced controller task,  must contain permission checking
// ***

// CASE 1: Use (if it exists) controller named as current view name
if ( file_exists(JPATH_COMPONENT.'/controllers/'.$view . ($format !== 'html' ? '.' . $format : '') . '.php') )
{
	if ($controller !== 'group')
	{
		$controller = $view;
	}
}


// CASE 2: Singular views do not (usually) have a controller, use (if it exists) the 'Plural' controller by appending 's' to view name
else if ( file_exists( JPATH_COMPONENT.'/controllers/'.$view.'s' . ($format !== 'html' ? '.' . $format : '') . '.php' ) )
{
	$controller = $view.'s';
	$task = $task ?: 'edit';  // Default task for singular views is 'edit', set it if task is empty
}


else
{
	// CASE 3: Some 'Plural' controllers have special naming
	$view_to_ctrl = array(
		'category' => 'categories',
		'file' => 'filemanager',
	);

	if ( isset($view_to_ctrl[$view]) )
	{
		$controller = $view_to_ctrl[$view];
		$task = $task ?: 'edit';  // Default task for singular views is 'edit', set it if task is empty
	}

	// Direct URL to views, these views MUST CONTAIN permission checking
	else
	{
	}
}


//echo "$controller -- $task <br/>\n";


// d. Set changes to controller/task variables back to HTTP REQUEST
$controller_task = $controller && $task  ?  $controller.'.'.$task  :  $task;
$controller_name = $controller;

$jinput->set('controller', $controller_name);
$jinput->set('task', $controller_task);



/**
 * Files needed for user groups manager
 */

if (
	$view=='group' || $controller_name=='group'   || $view=='groups' || $controller_name=='groups' ||
	$view=='user' || $controller_name=='user'   || $view=='users' || $controller_name=='users' ||
	$view=='debuggroup' || $controller_name=='debuggroup'
)
{
	// Load english language file for 'com_users' component then override with current language file
	JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, 'en-GB', true);
	JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, null, true);
	// users helper file
	require_once (JPATH_ADMINISTRATOR.'/components/com_flexicontent/helpers/users.php');
}
if ( $view=='debuggroup' || $controller_name=='debuggroup' ) {
	// users helper file
	require_once (JPATH_ADMINISTRATOR.'/components/com_flexicontent/helpers/debug.php');
}



// initialization done ... log stats for initialization
if ($print_logging_info && $format === 'html')
	@$fc_run_times['initialize_component'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;




/**
 * (If needed) Re-compile LESS files as CSS (call the less proprocessor)
 */

if ( $cparams->get('recompile_core_less', 0) && $format == 'html' )
{
	$start_microtime = microtime(true);

	FLEXIUtilities::checkedLessCompile_coreFiles();

	if ( $print_logging_info)
		@$fc_run_times['core_less_recompile'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
}



/**
 * Create a controller instance
 */

$controller	= JControllerLegacy::getInstance('Flexicontent');



/**
 * Perform the requested task
 */

$controller->execute( $task );

// Redirect if set by the controller
$controller->redirect();



/**
 * Load common js libs / frameworks
 */

// Re-get view it may have changed
$view   = $jinput->getCmd('view', 'flexicontent');
$layout = $jinput->getString('layout', '');

if ($format === 'html')
{
	// Load jquery Framework, but let some views decide for themselves, so that they can choose not to load some parts of jQuery.ui JS
	if ($view != 'item' || $view != 'fileselement') flexicontent_html::loadFramework('jQuery');

	if ( 1 ) // always load tooltips JS in backend
	{
		// J3.0+ tooltips (bootstrap based)
		JHtml::_('bootstrap.tooltip');
	}
	// Add flexi-lib JS
	JFactory::getDocument()->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js', array('version' => FLEXI_VHASH));  // Frontend/backend script
	JFactory::getDocument()->addScript(JUri::base(true).'/components/com_flexicontent/assets/js/flexi-lib.js', array('version' => FLEXI_VHASH));  // Backend only script

	// Validate when Joomla.submitForm() is called, NOTE: for non-FC views this is done before the method is called
	$js = '
		var fc_validateOnSubmitForm = 1;
	';
	$document->addScriptDeclaration( $js );

	// Load bootstrap CSS
	if ( 0 )  // Let backend template decide to load bootstrap CSS
		JHtml::_('bootstrap.loadCss', true);
}



/**
 * Enqueue PERFORMANCE statistics as a message BUT NOT if in RAW FORMAT or COMPONENT only views
 */

if ( $print_logging_info && $jinput->get('tmpl', '', 'cmd')!='component' && $format=='html' )
{
	/**
	 * Total performance stats of current view
	 */

	$_msg = $task
		? ' (TASK: ' . $controller_name . '.' . $task . ')'
		: ' (VIEW: ' . $view . ($layout ? ' -- LAYOUT: ' . $layout : '') . ')';


	/*
	 * Various Partial time performance stats
	 */

	$fields_render_total=0;
	$fields_render_times = FlexicontentFields::getFieldRenderTimes($fields_render_total);

	$fc_jprof->mark('END: FLEXIcontent component: '.$_msg);
	$msg = '<div style="font-family:tahoma!important; font-size:11px!important;">'. implode('<br/>', $fc_jprof->getbuffer()) .'</div>';

	$msg .= '<div style="font-family:tahoma!important; font-size:11px!important;">';

	if (isset($fc_run_times['initialize_component']))
		$msg .= sprintf('<br/>-- [Initialize component: %.2f s] ', $fc_run_times['initialize_component']/1000000);

	// **** BOF: BACKEND SPECIFIC
	if (isset($fc_run_times['post_installation_tasks']))
	{
		$msg .= sprintf('<br/>-- [Post installation / DB intergrity TASKs: %.2f s] ', $fc_run_times['post_installation_tasks']/1000000);

		$msg .= '<small>';
		if (isset($fc_run_times['getExistMenuItems']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Default menu item for URLs : %.2f s ', $fc_run_times['getExistMenuItems']/1000000);
		if (isset($fc_run_times['getItemsBadLang']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Items language and translation associations: %.2f s ', $fc_run_times['getItemsBadLang']/1000000);
		if (isset($fc_run_times['getItemsNoCat']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Items multi-category relations: %.2f s ', $fc_run_times['getItemsNoCat']/1000000);
		if (isset($fc_run_times['checkCurrentVersionData']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Items current version data: %.2f s ', $fc_run_times['checkCurrentVersionData']/1000000);
		if (isset($fc_run_times['getItemCountingDataOK']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - Items temporary accounting data: %.2f s ', $fc_run_times['getItemCountingDataOK']/1000000);
		if (isset($fc_run_times['checkInitialPermission']))
			$msg .= sprintf('<br/>&nbsp; &nbsp; &nbsp; - ACL initial permissions: %.2f s ', $fc_run_times['checkInitialPermission']/1000000);
		$msg .= '</small>';
	}
	// **** EOF: BACKEND SPECIFIC

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

	if (isset($fc_run_times['item_store_custom']))
		$msg .= sprintf('<br/>-- [Store item custom fields data: %.2f s] ', $fc_run_times['item_store_custom']/1000000);

	// **** BOF: BACKEND SPECIFIC
	if (isset($fc_run_times['unassoc_items_query']))
		$msg .= sprintf('<br/>-- [Execute Unassociate Items Query: %.2f s] ', $fc_run_times['unassoc_items_query']/1000000);
	// **** EOF: BACKEND SPECIFIC

	if (isset($fc_run_times['execute_main_query']))
		$msg .= sprintf('<br/>-- [Query: item LISTING: %.2f s] ', $fc_run_times['execute_main_query']/1000000);

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

	if (isset($fc_run_times['templates_parsing_xml']))
		$msg .= sprintf('<br/>-- [FC Templates: XML files parsing: %.2f s] ', $fc_run_times['templates_parsing_xml']/1000000);

	if (isset($fc_run_times['templates_parsing_less']))
		$msg .= sprintf('<br/>-- [FC Templates: LESS files parsing: %.2f s] ', $fc_run_times['templates_parsing_less']/1000000);

	if (isset($fc_run_times['templates_parsing_ini']))
		$msg .= sprintf('<br/>-- [FC Templates: INI files parsing: %.2f s] ', $fc_run_times['templates_parsing_ini']/1000000);

	if (isset($fc_run_times['core_less_recompile']))
		$msg .= sprintf('<br/>-- [FC core LESS checked re-compile: %.2f s] ', $fc_run_times['core_less_recompile']/1000000);

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

	if (isset($fc_run_times['field_values_params']))
		$msg .= sprintf('<br/>-- [FC fields values retrieval + field params creation: %.2f s] ', $fc_run_times['field_values_params']/1000000);

	if (isset($fc_run_times['template_render']))
		$msg .= sprintf('<br/>-- [FC "%s" view Template Rendering: %.2f s] ', $view, $fc_run_times['template_render']/1000000);

	// **** BOF: BACKEND SPECIFIC
	if (isset($fc_run_times['quick_sliders']))
		$msg .= sprintf('<br/>-- [Workflow sliders (Pending/Revised/etc): %.2f s] ', $fc_run_times['quick_sliders']/1000000);
	// **** EOF: BACKEND SPECIFIC

	// **********************
	// Fields rendering times
	// **********************

	if (count($fields_render_times))
	{
		$msg .= sprintf('<br/><br/>-- [FC Fields Rendering: %.2f s] ', $fields_render_total/1000000);
		$msg .= '<br/>';

		foreach($fields_render_times as $i => $_time)
		{
			$msg .=
				'<div style="white-space:nowrap; float:left;'.($i%3==0 ? 'clear:both !important;' : '').'">'.
					$fields_render_times[$i].
				'</div>';
		}

		$msg .= '<br/><div class="fcclear"></div>';
	}

	$msg .= '</div>';


	// SYSTEM PLGs
	if (isset($fc_run_times['auto_checkin_auto_state']))
		$msg = sprintf('** [Flexisystem PLG: Auto Checkin/Auto state(e.g. archive): %.2f s] ', $fc_run_times['auto_checkin_auto_state']/1000000) .'<br/>'.$msg.'<br/>';

	if (isset($fc_run_times['global_replacements']))
		$msg = sprintf('** [Flexisystem PLG: Replace Field/Items/etc Times: %.2f s] ', $fc_run_times['global_replacements']/1000000) .'<br/>'.$msg.'<br/>';

	global $fc_performance_msg;
	$fc_performance_msg .= $msg . '<div class="fcclear"></div>';
}

unset ($is_fc_component);
