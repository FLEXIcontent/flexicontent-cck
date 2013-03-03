<?php
/**
 * @version 1.5 stable $Id: admin.flexicontent.php 1608 2012-12-25 04:31:58Z ggppdk $ 
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

$lhlist = array('localhost', '127.0.0.1');
if( in_array($_SERVER['HTTP_HOST'], $lhlist) ) {
	error_reporting(E_ALL & ~E_STRICT);
	ini_set('display_errors',1);
}

// Logging Info variables
$start_microtime = microtime(true);

//include constants file
require_once (JPATH_COMPONENT.DS.'defineconstants.php');
//include the needed classes and helpers
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'flexicontent.acl.php');
require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'permission.php');
require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'route.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT.DS.'tables');

// Import the flexicontent_fields plugins and flexicontent plugins
if (!FLEXI_ONDEMAND)
	JPluginHelper::importPlugin('flexicontent_fields');
JPluginHelper::importPlugin('flexicontent');

// load english language file for 'com_content' component then override with current language file
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

// load english language file for 'com_flexicontent' component then override with current language file
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

// Get component parameters
$cparams =& JComponentHelper::getParams('com_flexicontent');

// Logging Info variables
if ( $cparams->get('print_logging_info') ) {
	global $fc_run_times;
}

if (!function_exists('FLEXISubmenu'))
{
	function FLEXISubmenu($cando)
	{
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Check access to current management tab
		$not_authorized = isset($perms->$cando) && !$perms->$cando;
		if ( $not_authorized ) {
			$mainframe = &JFactory::getApplication();
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		// Get post-installation FLAG (session variable), and current view (HTTP request variable)
		$session  =& JFactory::getSession();
		$dopostinstall = $session->get('flexicontent.postinstall');
		$view = JRequest::getVar('view', 'flexicontent');
		
		// Create Submenu, Dashboard (HOME is always added, other will appear only if post-installation tasks are done)
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');
		if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
		{
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items');
			if ($perms->CanTypes)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
			if ($perms->CanCats) 			JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
			if ($perms->CanFields) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
			if ($perms->CanTags) 			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
			if ($perms->CanAuthors)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_AUTHORS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
		//if ($perms->CanArchives)	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
			if ($perms->CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
			if ($perms->CanIndex)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEXES' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
			if ($perms->CanTemplates)	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
			if ($perms->CanImport)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
			if ($perms->CanStats)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
		}
	}
}

// Include the base controller
require_once (JPATH_COMPONENT.DS.'controller.php');

// ***********************************
// PREPARE Calling the controller task
// ***********************************

// a. Get view, task, controller REQUEST variables
$view = JRequest::getWord( 'view' );
$controller = JRequest::getWord( 'controller' );
$task = JRequest::getVar( 'task' );
// In J1.6+ controller is set via task variable ...
if (FLEXI_J16GE) {
	$_p = explode('.', $task);
	$task = $_p[ count($_p) - 1];
	if (count($_p) > 1) $controller = $_p[0];
}
// Cases that view variable must be ignored
$forced_views = array('category'=>1);
if ( isset($forced_views[$controller]) )  JRequest::setVar('view', $view=$controller);

$alt_path = JPATH_COMPONENT.DS.'controllers'.DS.$view.'.php';
if ( file_exists($alt_path) ) {
	// b. FORCE (if it exists) using controller named as current view name (thus ignoring controller set in HTTP REQUEST)
	$controller = $view;
} else {
	// c. Singular views do not (usually) have a controller, instead the 'Plural' controller is used
	// Going through the controller makes sure that appropriate code is always executed
	// Views/Layouts that can be called without a forced controller task (and without redirect to them, these must contain permission checking)
	$view_to_ctrl = array('type'=>'types', 'item'=>'items', 'field'=>'fields', 'tag'=>'tags', 'category'=>'categories', 'user'=>'users', 'file'=>'filemanager');
	if ( isset($view_to_ctrl[$view]) ) {
		$controller = $view_to_ctrl[$view];
		if ( !$task ) $task = 'edit';  // default task for singular views is edit
	}
}

// d. Set changes to controller/task variables back to HTTP REQUEST
if ( FLEXI_J16GE && $controller && $task) $task = $controller.'.'.$task;
JRequest::setVar('controller', $ctrlname=$controller);
JRequest::setVar('task', $task);
//echo "$controller -- $task <br/>\n";

// Include the specific controller file (if one was given in the HTTP request). This is needed for J1.5 only, since in J1.6+ this is done automatically
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

// Create a controller instance
if (FLEXI_J16GE) {
	$controller	= JControllerLegacy::getInstance('Flexicontent');
} else {
	$classname  = 'FlexicontentController'.$controller;
	$controller = new $classname();
}

// Perform the requested task
$controller->execute( JRequest::getCmd('task') );

// Do not print logging info in raw or component only views
if ( $cparams->get('print_logging_info') && JRequest::getWord('tmpl')!='component' && JRequest::getWord('format')!='raw') {
	$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	$app = & JFactory::getApplication();
	$msg = sprintf( 'FLEXIcontent page creation is %.2f secs', $elapsed_microseconds/1000000);
	$_view = JRequest::getWord('view','flexicontent');
	$_layout = JRequest::getWord('layout','');
	if ($task) $msg .= ' (TASK: '.(!FLEXI_J16GE ? $ctrlname.'.' : '').$task.')';
	else $msg .= ' (VIEW: ' .$_view. ($_layout ? ' -- LAYOUT: '.$_layout : '') .')';

	// Logging Info variables
	
	if (isset($fc_run_times['templates_parsing_cached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (cached): %.2f s] ', $fc_run_times['templates_parsing_cached']/1000000);
	
	if (isset($fc_run_times['templates_parsing_noncached']))
		$msg .= sprintf('<br/>-- [FC Templates Parsing (not cached) : %.2f s] ', $fc_run_times['templates_parsing_noncached']/1000000);
	
	if (isset($fc_run_times['get_item_data']))
		$msg .= sprintf('<br/>-- [Get/Caculate Item Properties: %.2f s] ', $fc_run_times['get_item_data']/1000000);
	
	if (isset($fc_run_times['get_field_vals']))
		$msg .= sprintf('<br/>-- [Retrieve Field Values: %.2f s] ', $fc_run_times['get_field_vals']/1000000);
	
	if (isset($fc_run_times['render_field_html']))
		$msg .= sprintf('<br/>-- [Field HTML Rendering: %.2f s] ', $fc_run_times['render_field_html']/1000000);
	
	if (isset($fc_run_times['form_rendering']))
		$msg .= sprintf('<br/>-- [Form Template Rendering: %.2f s] ', $fc_run_times['form_rendering']/1000000);
	
	$app->enqueueMessage( $msg, 'notice' );
}

// Redirect if a redirect URL was set, e.g. by the executed controller task
$controller->redirect();
?>