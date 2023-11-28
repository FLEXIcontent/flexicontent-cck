<?php
/**
 * @version 1.5 stable $Id: controller.php 1896 2014-05-01 18:12:25Z ggppdk $
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

use Joomla\String\StringHelper;

jimport('legacy.controller.legacy');

/**
 * FLEXIcontent Component Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentController extends JControllerAdmin
{
	public function __construct($config = array())
	{
		parent::__construct($config);

		$params = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;

		$jversion = new JVersion;

		$config_saved = $params->get('flexi_cat_extension', 0);
		//$config_saved = $config_saved && $params->get('search_mode', 0);  // an Extra configuration check

		// If configuration not saved REDIRECT TO DASHBOARD VIEW (will ask to save or import)
		$view = $this->input->get('view', '', 'CMD');
		if($view && !$config_saved)
		{
			$link 	= 'index.php?option=com_flexicontent';
			$this->setRedirect($link);   // we do not message since this will be displayed by template of the view ...
		}
		$session = JFactory::getSession();

		// GET POSTINSTALL tasks from session variable AND IF NEEDED re-evaluate it
		// NOTE, POSTINSTALL WILL NOT LET USER USE ANYTHING UNTIL ALL TASKS ARE COMPLETED
		$postinst_integrity_ok = $session->get('flexicontent.postinstall');
		$recheck_aftersave = $session->get('flexicontent.recheck_aftersave');

		$collation_version = $session->get('flexicontent.collation_version');
		if ($collation_version != $jversion->getShortVersion())  $postinst_integrity_ok = NULL;

		//$valArray = array(false => 'false', true => 'true', null=>'null');
		//echo  "postinst_integrity_ok: " . (isset($valArray[$postinst_integrity_ok])  ?  $valArray[$postinst_integrity_ok]  :  $postinst_integrity_ok) ."<br/>\n";
		//echo  "recheck_aftersave: " . (isset($valArray[$recheck_aftersave])  ?  $valArray[$recheck_aftersave]  :  $recheck_aftersave) ."<br/>\n";

		$format = strtolower($this->input->get('format', 'html', 'CMD'));
		$task   = strtolower($this->input->get('task', '', 'CMD'));
		$tmpl   = strtolower($this->input->get('tmpl', '', 'CMD'));

		if ($format == 'html' && !$task)
		{
			$model = $this->getModel('flexicontent');

			if ( $postinst_integrity_ok===NULL || $postinst_integrity_ok===false || $recheck_aftersave )
			{
				// NULL mean POSTINSTALL tasks has not been checked YET (current PHP user session),
				// false means it has been checked during current session, but has failed one or more tasks
				// In both cases we must evaluate the POSTINSTALL tasks,  and set the session variable
				if ( $print_logging_info ) $start_microtime = microtime(true);
				$postinst_integrity_ok = $this->getPostinstallState();
				//echo  "set postinst_integrity_ok: " . (isset($valArray[$postinst_integrity_ok])  ?  $valArray[$postinst_integrity_ok]  :  $postinst_integrity_ok) ."<br/>\n";
				$session->set('flexicontent.postinstall', $postinst_integrity_ok);
				$session->set('unbounded_count', false, 'flexicontent');  // indicate to item manager to recheck unbound items
				if ( $print_logging_info ) @$fc_run_times['post_installation_tasks'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}

			// SET recheck_aftersave FLAG to indicate rechecking of (a) post installation tasks AND (b) integrity checks after configuration save or article importing
			if ($config_saved) {
				$session->set('flexicontent.recheck_aftersave', !$postinst_integrity_ok);
				//echo  "set recheck_aftersave: " . (isset($valArray[!$postinst_integrity_ok])  ?  $valArray[!$postinst_integrity_ok]  :  !$postinst_integrity_ok) ."<br/>\n";
			} else {
				$session->set('flexicontent.recheck_aftersave', true);
				//echo  "set recheck_aftersave: true" ."<br/>\n";
			}

			if ( $print_logging_info ) $start_microtime = microtime(true);

			// GET ALLPLGPUBLISH task from session variable AND IF NEEDED re-evaluate it
			// NOTE, we choose to have this separate from REQUIRED POSTINSTALL tasks,
			// because WE DON'T WANT TO FORCE the user to enable all plugins but rather recommend it
			$allplgpublish = $session->get('flexicontent.allplgpublish');
			if (($allplgpublish===NULL) || ($allplgpublish===false))
			{
				// NULL means ALLPLGPUBLISH task has not been checked YET (current PHP user session),
				// false means it has been checked during current session but has failed
				// In both cases we must evaluate the ALLPLGPUBLISH task,  and set the session variable
				$allplgpublish = $model->getAllPluginsPublished();
				$session->set('flexicontent.allplgpublish', $allplgpublish);
			}

			if ($view && in_array($view, array('items', 'item', 'types', 'type', 'categories', 'category', 'fields', 'field', 'reviews', 'review', 'tags', 'tag', 'archive', 'filemanager', 'templates', 'stats', 'search', 'import')) && !$postinst_integrity_ok)
			{
				$msg = JText::_( 'FLEXI_PLEASE_COMPLETE_POST_INSTALL' );
				$link 	= 'index.php?option=com_flexicontent';
				$this->setRedirect($link, $msg);
			}
			else if ($postinst_integrity_ok && $config_saved)
			{
				if (!$tmpl)
				{
					$model->checkDirtyFields();
				}
			}
		}
	}


	function getPostinstallState()
	{
		$params = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$model  = $this->getModel('flexicontent');
		$model->checkCollations();
		$model->install_template_overrides();
		$model->install_3rdParty_plugins();

		$params = JComponentHelper::getParams('com_flexicontent');
		$use_versioning = $params->get('use_versioning', 1);
		if ( $print_logging_info ) @$fc_run_times['checking_postinstall_task_init'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [checking_postinstall_task_init: %.2f s] ', $fc_run_times['checking_postinstall_task_init']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existtype 			= $model->getExistType();
		if ( $print_logging_info ) @$fc_run_times['getExistType'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistType: %.2f s] ', $fc_run_times['getExistType']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existmenuitems	= $model->getExistMenuItems();
		if ( $print_logging_info ) @$fc_run_times['getExistMenuItems'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistMenuItems: %.2f s] ', $fc_run_times['getExistMenuItems']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existfields 		= $model->getExistCoreFields();
		if ( $print_logging_info ) @$fc_run_times['getExistCoreFields'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistCoreFields: %.2f s] ', $fc_run_times['getExistCoreFields']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existcpfields 		= $model->getExistCpFields();
		if ( $print_logging_info ) @$fc_run_times['getExistCpFields'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistCpFields: %.2f s] ', $fc_run_times['getExistCpFields']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existfplg 			= $model->getExistFieldsPlugins();
		if ( $print_logging_info ) @$fc_run_times['getExistFieldsPlugins'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistFieldsPlugins: %.2f s] ', $fc_run_times['getExistFieldsPlugins']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existseplg 		= $model->getExistSearchPlugin();
		if ( $print_logging_info ) @$fc_run_times['getExistSearchPlugin'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistSearchPlugin: %.2f s] ', $fc_run_times['getExistSearchPlugin']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existsyplg 		= $model->getExistSystemPlugin();
		if ( $print_logging_info ) @$fc_run_times['getExistSystemPlugin'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistSystemPlugin: %.2f s] ', $fc_run_times['getExistSystemPlugin']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existcats        = !$model->getItemsNoCat();
		if ( $print_logging_info ) @$fc_run_times['getItemsNoCat'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getItemsNoCat: %.2f s] ', $fc_run_times['getItemsNoCat']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$langsynced				= $model->getExistLanguageColumns() && !$model->getItemsBadLang();
		if ( $print_logging_info ) @$fc_run_times['getItemsBadLang'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getItemsBadLang: %.2f s] ', $fc_run_times['getItemsBadLang']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existversions 		= $model->getExistVersionsTable();
		if ( $print_logging_info ) @$fc_run_times['getExistVersionsTable'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistVersionsTable: %.2f s] ', $fc_run_times['getExistVersionsTable']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existversionsdata= !$use_versioning || $model->getExistVersionsPopulated();
		if ( $print_logging_info ) @$fc_run_times['getExistVersionsPopulated'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistVersionsPopulated: %.2f s] ', $fc_run_times['getExistVersionsPopulated']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existauthors 		= $model->getExistAuthorsTable();
		if ( $print_logging_info ) @$fc_run_times['getExistAuthorsTable'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistAuthorsTable: %.2f s] ', $fc_run_times['getExistAuthorsTable']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$cachethumb				= $model->getCacheThumbPerms();
		if ( $print_logging_info ) @$fc_run_times['getCacheThumbPerms'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getCacheThumbPerms: %.2f s] ', $fc_run_times['getCacheThumbPerms']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$deprecatedfiles	= $model->getDeprecatedFiles();
		if ( $print_logging_info ) @$fc_run_times['getDeprecatedFiles'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getDeprecatedFiles: %.2f s] ', $fc_run_times['getDeprecatedFiles']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$nooldfieldsdata	= $model->getNoOldFieldsData();
		if ( $print_logging_info ) @$fc_run_times['getNoOldFieldsData'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getNoOldFieldsData: %.2f s] ', $fc_run_times['getNoOldFieldsData']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$missingversion		= true; //!$use_versioning || !$model->checkCurrentVersionData();
		if ( $print_logging_info ) @$fc_run_times['checkCurrentVersionData'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [checkCurrentVersionData: %.2f s] ', $fc_run_times['checkCurrentVersionData']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$existdbindexes = $model->getExistDBindexes();
		if ( $print_logging_info ) @$fc_run_times['getExistDBindexes'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getExistDBindexes: %.2f s] ', $fc_run_times['getExistDBindexes']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$itemcountingdok  = $model->getItemCountingDataOK();
		if ( $print_logging_info ) @$fc_run_times['getItemCountingDataOK'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [getItemCountingDataOK: %.2f s] ', $fc_run_times['getItemCountingDataOK']/1000000);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		$initialpermission = $model->checkInitialPermission();
		if ( $print_logging_info ) @$fc_run_times['checkInitialPermission'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		//printf('<br/>-- [checkInitialPermission: %.2f s] ', $fc_run_times['checkInitialPermission']/1000000);

		// Check if old field positions were converted
		$model->convertOldFieldsPositions();

		//echo "(!$existtype) || (!$existmenuitems) || (!$existfields) || (!$existcpfields) ||<br>";
		//echo "     (!$existfplg) || (!$existseplg) || (!$existsyplg) ||<br>";
		//echo "     (!$existcats)  || (!$langsynced) || (!$existdbindexes) || (!$itemcountingdok) || (!$existversions) || (!$existversionsdata) || (!$existauthors) || (!$cachethumb) ||<br>";
		//echo "     (!$deprecatedfiles) || (!$nooldfieldsdata) || (!$missingversion) ||<br>";
		//echo "     (!$initialpermission)<br>";

		// Display POST installation tasks if any task-check fails (returns false)
		$postinst_integrity_ok = true;
		if (
			!$existtype || !$existmenuitems || !$existfields || !$existcpfields ||
			//!$existfplg || !$existseplg || existsyplg ||
			!$existcats || !$langsynced || !$existversions || !$existversionsdata || !$existauthors ||
			!$deprecatedfiles || !$nooldfieldsdata || !$missingversion || !$cachethumb ||
			!$existdbindexes || !$itemcountingdok || !$initialpermission
		) {
			$postinst_integrity_ok = false;
		}
		return $postinst_integrity_ok;
	}


	/**
	 * Method to display a view.
	 *
	 * @param   boolean        $cachable   If true, the view output will be cached
	 * @param   mixed|boolean  $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return  JController  This object to support chaining.
	 *
	 * @since   3.1
	 */
	public function display($cachable = false, $urlparams = false)
	{
		/**
		 * Alternative way to get clasname could be: get_parent_class('JControllerAdmin')
		 * Also for PHP < 7.0 we need class name inside a string variable
		 */
		$class = get_parent_class(get_parent_class(get_class()));
		return $class::display($cachable, $urlparams);
	}


	function call_extfunc()
	{
		flexicontent_ajax::call_extfunc();
	}
}
