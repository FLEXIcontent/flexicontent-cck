<?php
/**
 * @version 1.5 stable $Id: controller.php 1802 2013-11-03 18:31:43Z ggppdk $
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

jimport('joomla.application.component.controller');
/**
 * FLEXIcontent Component Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentController extends JControllerLegacy
{
	function __construct()
	{
		parent::__construct();
		$params = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		$config_saved = !FLEXI_J16GE ? $params->get('flexi_section', 0) : $params->get('flexi_cat_extension', 0);
		//$config_saved = $config_saved && $params->get('search_mode', 0);  // an Extra configuration check
		
		// If configuration not saved REDIRECT TO DASHBOARD VIEW (will ask to save or import)
		$view = JRequest::getVar('view');
		if($view && !$config_saved) {
			$link 	= 'index.php?option=com_flexicontent';
			$this->setRedirect($link);   // we do not message since this will be displayed by template of the view ...
		}
		$session = JFactory::getSession();
		
		// GET POSTINSTALL tasks from session variable AND IF NEEDED re-evaluate it
		// NOTE, POSTINSTALL WILL NOT LET USER USE ANYTHING UNTIL ALL TASKS ARE COMPLETED
		$postinst_integrity_ok = $session->get('flexicontent.postinstall');
		$recheck_aftersave = $session->get('flexicontent.recheck_aftersave');
		$format	= JRequest::getCmd('format', null);
		 if ( $format!="raw"  &&  ($postinst_integrity_ok===NULL || $postinst_integrity_ok===false || $recheck_aftersave) ) {
			// NULL mean POSTINSTALL tasks has not been checked YET (current PHP user session),
			// false means it has been checked during current session, but has failed one or more tasks
			// In both cases we must evaluate the POSTINSTALL tasks,  and set the session variable
			if ( $print_logging_info ) $start_microtime = microtime(true);
			$session->set('flexicontent.postinstall', $postinst_integrity_ok = $this->getPostinstallState());
			$session->set('unbounded_count', false, 'flexicontent');  // indicate to item manager to recheck unbound items
			if ( $print_logging_info ) @$fc_run_times['post_installation_tasks'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		// SET recheck_aftersave FLAG to indicate rechecking of (a) post installation tasks AND (b) integrity checks after configuration save or article importing
		if ($config_saved) {
			$session->set('flexicontent.recheck_aftersave', !$postinst_integrity_ok);
		} else {
			$session->set('flexicontent.recheck_aftersave', true);
		}
		
		if ( $print_logging_info ) $start_microtime = microtime(true);
		
		// GET ALLPLGPUBLISH task from session variable AND IF NEEDED re-evaluate it
		// NOTE, we choose to have this separate from REQUIRED POSTINSTALL tasks,
		// because WE DON'T WANT TO FORCE the user to enable all plugins but rather recommend it
		$allplgpublish = $session->get('flexicontent.allplgpublish');
		if(($allplgpublish===NULL) || ($allplgpublish===false)) {
			// NULL means ALLPLGPUBLISH task has not been checked YET (current PHP user session),
			// false means it has been checked during current session but has failed
			// In both cases we must evaluate the ALLPLGPUBLISH task,  and set the session variable
			$model = $this->getModel('flexicontent');
			$allplgpublish = $model->getAllPluginsPublished();
			$session->set('flexicontent.allplgpublish', $allplgpublish);
		}
		
		if($view && in_array($view, array('items', 'item', 'types', 'type', 'categories', 'category', 'fields', 'field', 'tags', 'tag', 'archive', 'filemanager', 'templates', 'stats', 'search', 'import')) && !$postinst_integrity_ok) {
			$msg = JText::_( 'FLEXI_PLEASE_COMPLETE_POST_INSTALL' );
			$link 	= 'index.php?option=com_flexicontent';
			$this->setRedirect($link, $msg);
		} else {
			$this->checkDirtyFields();
		}
		
		// Register Extra task
		$this->registerTask( 'apply'								, 'save' );
		$this->registerTask( 'applyacl'							, 'saveacl' );
		$this->registerTask( 'createmenuitems'			, 'createMenuItems' );
		$this->registerTask( 'createdefaultype'			, 'createDefaultType' );
		$this->registerTask( 'createdefaultfields'	, 'createDefaultFields' );
		$this->registerTask( 'publishplugins'				, 'publishplugins' );
		$this->registerTask( 'addmcatitemrelations'	, 'addMcatItemRelations' );
		$this->registerTask( 'createlangcolumn'			, 'createLangColumn' );
		$this->registerTask( 'createdbindexes'			, 'createDBindexes' );
		$this->registerTask( 'createversionstbl'		, 'createVersionsTable' );
		$this->registerTask( 'populateversionstbl'	, 'populateVersionsTable' );
		$this->registerTask( 'createauthorstbl'			, 'createauthorstable' );
		$this->registerTask( 'updateitemcounting'   , 'updateItemCountingData' );
		$this->registerTask( 'deleteoldfiles'				, 'deleteOldBetaFiles' );
		$this->registerTask( 'cleanupoldtables'			, 'cleanupOldTables' );
		$this->registerTask( 'addcurrentversiondata', 'addCurrentVersionData' );
		$this->registerTask( 'langfiles'						, 'processLanguageFiles' );
		
		$task = JRequest::getVar('task');
		if (is_string($task) && $task=="translate") {
			JRequest::setVar('task', 'copy');
			JRequest::setVar('copy_behaviour', 'translate');
		}
		
		if ( $print_logging_info ) @$fc_run_times['initialize_component'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
	
	function processLanguageFiles() 
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		
		$code 	= JRequest::getVar('code', 'en-GB');
		$method = JRequest::getVar('method', '');
		$name = JRequest::getVar('name', '');
		$email = JRequest::getVar('email', '');
		$web = JRequest::getVar('web', '');
		$message = JRequest::getVar('message', '');
		
		$formparams = array();
		$formparams['name'] 	= $name;
		$formparams['email'] 	= $email;
		$formparams['web'] 		= $web;
		$formparams['message'] 	= $message;
		
		$model 	= $this->getModel('flexicontent');		

		$missing = $model->processLanguageFiles($code, $method, $formparams);
		
		if (is_array($missing) && $method != 'zip') {
			if (@$missing['admin']) {
				echo '<h3>'.JText::_('Folder: administrator/languages/').$code.'/</h3>';
				foreach ($missing['admin'] as $a) {
					echo '<p>'.$code.'.'.$a.'.ini'.(($method == 'create') ? ' <span class="lang-success">created</span>' : ' <span class="lang-fail">is missing</span>').'</p>';
				}
			}
			if (@$missing['site']) {
				echo '<h3>'.JText::_('Folder: languages/').$code.'/</h3>';
				foreach ($missing['site'] as $s) {
					echo '<p>'.$code.'.'.$s.'.ini'.(($method == 'create') ? ' <span class="lang-success">created</span>' : ' <span class="lang-fail">is missing</span>').'</p>';
				}
			}
			if ($method != 'create') {
				echo '<style>#missing {display:block;}</style>';
			}
		} else {
			echo $missing;
			echo '<style>#missing {display:none;}</style>';
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
		$params = JComponentHelper::getParams('com_flexicontent');
		$use_versioning = $params->get('use_versioning', 1);
					if ( $print_logging_info ) @$fc_run_times['checking_postinstall_task_init'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [checking_postinstall_task_init: %.2f s] ', $fc_run_times['checking_postinstall_task_init']/1000000);

			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$existmenuitems	= $model->getExistMenuItems();
				if ( $print_logging_info ) @$fc_run_times['getExistMenuItems'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getExistMenuItems: %.2f s] ', $fc_run_times['getExistMenuItems']/1000000);
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$existtype 			= $model->getExistType();
				if ( $print_logging_info ) @$fc_run_times['getExistType'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getExistType: %.2f s] ', $fc_run_times['getExistType']/1000000);
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$existfields 		= $model->getExistFields();
				if ( $print_logging_info ) @$fc_run_times['getExistFields'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getExistFields: %.2f s] ', $fc_run_times['getExistFields']/1000000);

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
		$existlang				= $model->getExistLanguageColumn() && !$model->getItemsNoLang();
				if ( $print_logging_info ) @$fc_run_times['getItemsNoLang'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getItemsNoLang: %.2f s] ', $fc_run_times['getItemsNoLang']/1000000);
			
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$existdbindexes = $model->getExistDBindexes();
				if ( $print_logging_info ) @$fc_run_times['getExistDBindexes'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getExistDBindexes: %.2f s] ', $fc_run_times['getExistDBindexes']/1000000);
		
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$itemcountingdok  = $model->getItemCountingDataOK();
				if ( $print_logging_info ) @$fc_run_times['getItemCountingDataOK'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getItemCountingDataOK: %.2f s] ', $fc_run_times['getItemCountingDataOK']/1000000);
			
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
		$cachethumb				= $model->getCacheThumbChmod();
				if ( $print_logging_info ) @$fc_run_times['getCacheThumbChmod'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getCacheThumbChmod: %.2f s] ', $fc_run_times['getCacheThumbChmod']/1000000);
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$oldbetafiles			= $model->getOldBetaFiles();
				if ( $print_logging_info ) @$fc_run_times['getOldBetaFiles'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getOldBetaFiles: %.2f s] ', $fc_run_times['getOldBetaFiles']/1000000);
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$nooldfieldsdata	= $model->getNoOldFieldsData();
				if ( $print_logging_info ) @$fc_run_times['getNoOldFieldsData'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [getNoOldFieldsData: %.2f s] ', $fc_run_times['getNoOldFieldsData']/1000000);
			
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$missingversion		= !$use_versioning || !$model->checkCurrentVersionData();
				if ( $print_logging_info ) @$fc_run_times['checkCurrentVersionData'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [checkCurrentVersionData: %.2f s] ', $fc_run_times['checkCurrentVersionData']/1000000);
		
			 if ( $print_logging_info ) $start_microtime = microtime(true);
		$initialpermission = FLEXI_J16GE ? $model->checkInitialPermission() : true;
				if ( $print_logging_info ) @$fc_run_times['checkInitialPermission'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				//printf('<br/>-- [checkInitialPermission: %.2f s] ', $fc_run_times['checkInitialPermission']/1000000);
		
		// This will check and add custom FLEXI_ACCESS privileges
		if (!FLEXI_J16GE)
			$model->checkExtraAclRules();  // For J1.5
		
		//echo "(!$existmenuitems) || (!$existtype) || (!$existfields) ||<br>";
		//echo "     (!$existfplg) || (!$existseplg) || (!$existsyplg) ||<br>";
		//echo "     (!$existcats)  || (!$existlang) || (!$existdbindexes) || (!$itemcountingdok) || (!$existversions) || (!$existversionsdata) || (!$existauthors) || (!$cachethumb) ||<br>";
		//echo "     (!$oldbetafiles) || (!$nooldfieldsdata) || (!$missingversion) ||<br>";
		//echo "     (!$initialpermission)<br>";
		
		// Display POST installation tasks if any task-check fails (returns false)
		$postinst_integrity_ok = true;
		if ( !$existmenuitems || !$existtype || !$existfields ||
		     //!$existfplg || !$existseplg || existsyplg ||
		     !$existcats || !$existlang || !$existdbindexes || !$itemcountingdok || !$existversions || !$existversionsdata || !$existauthors ||
		     !$oldbetafiles || !$nooldfieldsdata || !$missingversion || !$cachethumb ||
				 !$initialpermission
		   ) {
			$postinst_integrity_ok = false;
		}
		return $postinst_integrity_ok;
	}
	
	
	/**
	 * Display the view
	 */
	function display($cachable = false, $urlparams = false)
	{
		parent::display();
	}
	
	
	/**
	 * Saves the acl file
	 *
	 */
	function saveacl()
	{

		JRequest::checkToken() or jexit( 'Invalid Token' );

		// Initialize some variables
		$app     = JFactory::getApplication();
		$option  = JRequest::getVar('option');
		$filename    = JRequest::getVar('filename', '', 'post', 'cmd');
		$filecontent = JRequest::getVar('filecontent', '', '', '', JREQUEST_ALLOWRAW);

		if (!$filecontent) {
			$app->redirect('index.php?option='.$option, JText::_( 'FLEXI_OPERATION_FAILED' ).': '.JText::_( 'FLEXI_CONTENT_EMPTY' ));
		}

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
		$ftp = JClientHelper::getCredentials('ftp');

		$file = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.$filename;

		// Try to make the acl file writeable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0755')) {
			JError::raiseNotice('SOME_ERROR_CODE', JText::_( 'FLEXI_COULD_NOT_MAKE_ACL_FILE_WRITABLE' ));
		}

		jimport('joomla.filesystem.file');
		$return = JFile::write($file, $filecontent);

		// Try to make the acl file unwriteable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0555')) {
			JError::raiseNotice('SOME_ERROR_CODE', JText::_( 'FLEXI_COULD_NOT_MAKE_ACL_FILE_UNWRITABLE' ));
		}

		if ($return)
		{
			$task = JRequest::getVar('task');
			switch($task)
			{
				case 'applyacl' :
					$app->redirect('index.php?option='.$option.'&view=editacl', JText::_( 'FLEXI_ACL_FILE_SUCCESSFULLY_ALTERED' ));
					break;

				case 'saveacl'  :
				default         :
					$app->redirect('index.php?option='.$option, JText::_( 'FLEXI_ACL_FILE_SUCCESSFULLY_ALTERED' ) );
					break;
			}
		} else {
			$app->redirect('index.php?option='.$option, JText::_( 'FLEXI_OPERATION_FAILED' ).': '.JText::sprintf('FLEXI_FAILED_TO_OPEN_FILE_FOR_WRITING', $file));
		}
	}
	
	/**
	 * Method to create default type : article
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultType()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();

		$query 	=	"INSERT INTO `#__flexicontent_types` "
			." (id, ".(FLEXI_J16GE ? "asset_id, " : "")."name, alias, published, checked_out, checked_out_time, access, attribs) "
			." VALUES(1, ".(FLEXI_J16GE ? "0, " : "")."'Article', 'article', 1, 0, '0000-00-00 00:00:00', ".(FLEXI_J16GE ? 1 : 0).", 'ilayout=default\nhide_maintext=0\nhide_html=0\nmaintext_label=\nmaintext_desc=\ncomments=\ntop_cols=two\nbottom_cols=two')"
			;
		$db->setQuery($query);
		if (!$db->query()) {
			if ($db->getErrorNum()) echo $db->getErrorMsg();
			echo '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			$query 	=	"INSERT INTO `#__flexicontent_fields_type_relations` (`field_id`,`type_id`,`ordering`)
						VALUES
							(1,1,1),
							(2,1,2),
							(3,1,3),
							(4,1,4),
							(5,1,5),
							(6,1,6),
							(7,1,7),
							(8,1,8),
							(9,1,9),
							(10,1,10),
							(11,1,11),
							(12,1,12),
							(13,1,13),
							(14,1,14)" ;
			$db->setQuery($query);
			if (!$db->query()) {
				if ($db->getErrorNum()) echo $db->getErrorMsg();
				echo '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				echo '<span class="install-ok"></span>';
			}
		}
	}
	
	/**
	 * Method to create default menu items used for SEF links
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createMenuItems()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		if (FLEXI_J16GE) {
			$db->setQuery("SELECT extension_id FROM #__extensions WHERE element='com_flexicontent' AND type='component' ");
		} else {
			$db->setQuery("SELECT id FROM #__components WHERE admin_menu_link='option=com_flexicontent'");
		}
		$flexi_comp_id = $db->loadResult();	
		
		$db->setQuery("DELETE FROM #__menu_types WHERE menutype='flexihiddenmenu' ");	
		$db->query();
		
		$db->setQuery("INSERT INTO #__menu_types (`menutype`,`title`,`description`) ".
			"VALUES ('flexihiddenmenu', 'FLEXIcontent Hidden Menu', 'A hidden menu to host Flexicontent needed links')");
		$db->query();
		
		$db->setQuery("DELETE FROM #__menu WHERE menutype='flexihiddenmenu' ");	
		$db->query();
		
		if (FLEXI_J30GE) {
			$query 	=	"INSERT INTO #__menu (`menutype`,`title`,`alias`,`path`,`link`,`type`,`published`,`parent_id`,`component_id`,`level`,`checked_out`,`checked_out_time`,`browserNav`,`access`,`params`,`lft`,`rgt`,`home`)
			VALUES ".
			"('flexihiddenmenu','Site Content','site_content','site_content','index.php?option=com_flexicontent&view=flexicontent','component',1,1,$flexi_comp_id,1,0,'0000-00-00 00:00:00',0,1,'rootcat=0',0,0,0)";
		} else if (FLEXI_J16GE) {
			$query 	=	"INSERT INTO #__menu (`menutype`,`title`,`alias`,`path`,`link`,`type`,`published`,`parent_id`,`component_id`,`level`,`ordering`,`checked_out`,`checked_out_time`,`browserNav`,`access`,`params`,`lft`,`rgt`,`home`)
			VALUES ".
			"('flexihiddenmenu','Site Content','site_content','site_content','index.php?option=com_flexicontent&view=flexicontent','component',1,1,$flexi_comp_id,1,1,0,'0000-00-00 00:00:00',0,1,'rootcat=0',0,0,0)";
		} else {
			$query 	=	"INSERT INTO #__menu (`menutype`,`name`,`alias`,`link`,`type`,`published`,`parent`,`componentid`,`sublevel`,`ordering`,`checked_out`,`checked_out_time`,`pollid`,`browserNav`,`access`,`utaccess`,`params`,`lft`,`rgt`,`home`)
			VALUES ".
			"('flexihiddenmenu','Site Content','site_content','index.php?option=com_flexicontent&view=flexicontent','component',1,0,$flexi_comp_id,0,1,0,'0000-00-00 00:00:00',0,0,0,0,'rootcat=0',0,0,0)";
		}
		
		$db->setQuery($query);
		$result = $db->query();
		if($result) {
			// Save the created menu item as default_menu_itemid for the component
			$component = JComponentHelper::getParams('com_flexicontent');
			$component->set('default_menu_itemid', $db->insertid());
			$cparams = $component->toString();

			$flexi = JComponentHelper::getComponent('com_flexicontent');

			$query 	= 'UPDATE '. (FLEXI_J16GE ? '#__extensions' : '#__components')
					. ' SET params = ' . $db->Quote($cparams)
					. ' WHERE '. (FLEXI_J16GE ? 'extension_id' : 'id') .' = '. $flexi->id
					;
			$db->setQuery($query);
			$result = $db->query();
		}
		
		// This is necessary as extension data are cached ... and just above we updated the component parameters -manually- (and (also added menu item)
		$cache = JFactory::getCache();
		$cache->clean( '_system' );
		
		if (!$result) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existmenuitems" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	/**
	 * Method to create default fields data
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultFields()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		
		$acclevel = FLEXI_J16GE ? 1 : 0;
		$query 	=	"INSERT INTO #__flexicontent_fields (`id`,`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,`positions`,`published`,`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`)
VALUES
	(1,'maintext','text','Description','The main description text (introtext/fulltext)',0,1,1,0,'description.items.default',1,'display_label=0\ntrigger_onprepare_content=0',0,'0000-00-00 00:00:00',{$acclevel},2),
	(2,'created','created','Created','Creation date',0,1,1,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},3),
	(3,'createdby','created_by','Created by','Item author',0,1,1,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},4),
	(4,'modified','modified','Last modified','Date of the last modification',0,1,1,0,'top.items.default',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},5),
	(5,'modifiedby','modified_by','Revised by','Name of the user which last edited the item',0,1,1,0,'top.items.default',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},6),
	(6,'title','title','Title','The item title',0,1,1,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',{$acclevel},1),
	(7,'hits','hits','Hits','Number of hits',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=views',0,'0000-00-00 00:00:00',{$acclevel},7),
	(8,'type','document_type','Document type','Document type',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},8),
	(9,'version','version','Version','Number of version',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',{$acclevel},9),
	(10,'state','state','State','State',0,1,1,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',{$acclevel},10),
	(11,'voting','voting','Voting','The up and down voting buttons',0,1,1,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1\ndimension=16\nimage=components/com_flexicontent/assets/images/star-small.png',0,'0000-00-00 00:00:00',{$acclevel},11),
	(12,'favourites','favourites','Favourites','The add to favourites button',0,1,1,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1',0,'0000-00-00 00:00:00',{$acclevel},12),
	(13,'categories','categories','Categories','The categories assigned to this item',0,1,1,0,'top.items.default\nunder-description-line1.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',{$acclevel},13),
	(14,'tags','tags','Tags','The tags assigned to this item',0,1,1,0,'top.items.default\nunder-description-line2.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',{$acclevel},14)" ;
		$db->setQuery($query);
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existfields" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}

	/**
	 * Publish FLEXIcontent plugins
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function publishplugins()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$format = JRequest::getVar('format', '');
		$db = JFactory::getDBO();
		
		$query	= 'UPDATE '. (FLEXI_J16GE ? '#__extensions' : '#__plugins')
				. ' SET '. (FLEXI_J16GE ? 'enabled' : 'published') .' = 1'
				. ' WHERE '. (FLEXI_J16GE ? ' `type`= ' . $db->Quote('plugin') : '1')
				. ' AND (folder = ' . $db->Quote('flexicontent_fields')
				. ' OR element = ' . $db->Quote('flexisearch')
				. ' OR element = ' . $db->Quote('flexisystem')
				. ' OR element = ' . $db->Quote('flexiadvsearch')
				. ' OR element = ' . $db->Quote('flexiadvroute')
				. ')'
				;
		
		$db->setQuery($query);
		if (!$db->query()) {
			if ($format == 'raw') {
				echo '<span class="install-notok"></span><span class="button-add"><a id="publishplugins" href="index.php?option=com_flexicontent&task=publishplugins&format=raw">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				JError::raiseNotice(1, JText::_( 'FLEXI_COULD_NOT_PUBLISH_PLUGINS' ));
				return false;
			}
		} else {
			if ($format == 'raw') {
				echo '<span class="install-ok"></span>';
			} else {
				return true;
			}
		}
	}

	/**
	 * Set phpThumb cache permissions
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function cachethumbchmod()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$format		= JRequest::getVar('format', '');
		// PhpThumb cache directory
		$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');
		$success = JPath::setPermissions($phpthumbcache, '0644', '0755');
		if (!$success) {
			if ($format == 'raw') {
				echo '<span class="install-notok"></span><span class="button-add"><a id="cachethumb" href="index.php?option=com_flexicontent&task=cachethumbchmod&format=raw">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				JError::raiseNotice(1, JText::_( 'FLEXI_COULD_NOT_PUBLISH_PLUGINS' ));
				return false;
			}
		} else {
			if ($format == 'raw') {
				echo '<span class="install-ok"></span>';
			} else {
				return true;
			}
		}
	}
	
	
	/**
	 * Method to set the default site language the items with no language
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function setItemsDefaultLang($lang)
	{
		$db = JFactory::getDBO();
		
		// Set default language for items that do not have their language set
		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE language = ""'
				;
		$db->setQuery($query);
		$result1 = $db->query();
		
		$query 	= 'UPDATE #__content'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE language = ""'
				;
		$db->setQuery($query);
		$result2 = $db->query();
		
		// Set default translation group for items that don't have one
		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET lang_parent_id = item_id '
				. ' WHERE lang_parent_id = 0'
				;
		$db->setQuery($query);
		$result3 = $db->query();
		
		return $result1 && result2 && result3;
	}
	
	/**
	 * Method to set the default site language the items with no language
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function addmcatitemrelations()
	{
		$db = JFactory::getDBO();
		$subquery 	= "SELECT i.catid, i.id, 0 FROM #__flexicontent_items_ext as ie "
			. " JOIN #__content as i ON i.id=ie.item_id "
			. " LEFT JOIN #__flexicontent_cats_item_relations as rel ON rel.catid=i.catid AND i.id=rel.itemid "
			. " WHERE rel.catid IS NULL";
		
		// Set default language for items that do not have their language set
		$query 	= 'INSERT INTO #__flexicontent_cats_item_relations'
			.' (catid, itemid, ordering) '.$subquery;
		$db->setQuery($query);
		$result = $db->query();
		
		if (!$result) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existcats" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	/**
	 * Method to create the language datas
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createLangColumn()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		$nullDate	= $db->getNullDate();
		
		// Add language column
		if (!FLEXI_J16GE) {
			$fields = $db->getTableFields(array('#__flexicontent_items_ext'));
			$columns = $fields['#__flexicontent_items_ext'];
		} else {
			$columns = $db->getTableColumns('#__flexicontent_items_ext');
		}
		$language_col = array_key_exists('language', $columns) ? true : false;
		if(!$language_col) {
			$query 	=	"ALTER TABLE #__flexicontent_items_ext ADD `language` VARCHAR( 11 ) NOT NULL DEFAULT '' AFTER `type_id`" ;
			$db->setQuery($query);
			$result_lang_col = $db->query();
			if (!$result_lang_col) echo "Cannot add language column<br>";
		} else $result_lang_col = true;
		
		// Add translation group column
		$lang_parent_id_col = array_key_exists('lang_parent_id', $columns) ? true : false;
		if(!$lang_parent_id_col) {
			$query 	=	"ALTER TABLE #__flexicontent_items_ext ADD `lang_parent_id` INT NOT NULL DEFAULT 0 AFTER `language`" ;
			$db->setQuery($query);
			$result_tgrp_col = $db->query();
			if (!$result_tgrp_col) echo "Cannot add translation group column<br>";
		} else $result_tgrp_col = true;
		
		// Add default language for items that do not have one, and add translation group to items that do not have one set
		$model = $this->getModel('flexicontent');
		if ($model->getItemsNoLang()) {
			// Add site default language to the language field if empty
			$lang = flexicontent_html::getSiteDefaultLang();
			$result_items_default_lang = $this->setItemsDefaultLang($lang);
			if (!$result_items_default_lang) echo "Cannot set default language or set default translation group<br>";
		} else $result_items_default_lang = true;
		
		if (!$result_lang_col
			|| !$result_tgrp_col
			|| !$result_items_default_lang
		) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existlanguagecolumn" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	
	/**
	 * Method to create missing DB indexes
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDBindexes()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		$nullDate	= $db->getNullDate();
		
		$model  = $this->getModel('flexicontent');
		$existdbindexes = $model->getExistDBindexes();
		if (!$existdbindexes) {
			$app = JFactory::getApplication();
			$dbprefix = $app->getCfg('dbprefix');
			
			$query	= "ALTER TABLE `".$dbprefix."flexicontent_fields_item_relations` ADD INDEX value(`value`(32))";
			$db->setQuery($query);
			
			if (!$db->query()) {
				echo '<span class="install-notok"></span><span class="button-add"><a id="existauthors" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				echo '<span class="install-ok"></span>';
			}
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
		
	
	/**
	 * Method to update content into non-indexed columns
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function updateItemCountingData()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		
		// Set language in the content to be same as in items_ext db table
		$query 	= 'UPDATE #__flexicontent_items_ext AS ie '
				. ' JOIN #__content AS i ON i.id=ie.item_id '
				. ' SET ' . (FLEXI_J16GE ? ' ie.language = i.language,' : '')
				. '  ie.cnt_state = i.state '
				. ', ie.cnt_access = i.access '
				. ', ie.cnt_publish_up = i.publish_up '
				. ', ie.cnt_publish_down = i.publish_down '
				. ', ie.cnt_created_by = i.created_by '
				;
		
		$db->setQuery($query);
		
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="itemcountingdok" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	
	/**
	 * Method to create the versions table
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createVersionsTable()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	= " CREATE TABLE IF NOT EXISTS #__flexicontent_versions (
	  				`id` int(11) unsigned NOT NULL auto_increment,
					`item_id` int(11) unsigned NOT NULL default '0',
					`version_id` int(11) unsigned NOT NULL default '0',
					`comment` mediumtext NOT NULL,
					`created` datetime NOT NULL default '0000-00-00 00:00:00',
					`created_by` int(11) unsigned NOT NULL default '0',
					`state` int(3) NOT NULL default '0',
					PRIMARY KEY  (`id`),
					KEY `version2item` (`item_id`,`version_id`)
					) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`"
					;
		$db->setQuery($query);
		
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existversions" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	/**
	 * Method to create the authors table
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createAuthorsTable()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	= " CREATE TABLE IF NOT EXISTS #__flexicontent_authors_ext (
  				`user_id` int(11) unsigned NOT NULL,
  				`author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  				`author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
					PRIMARY KEY  (`user_id`)
					) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`"
					;
		$db->setQuery($query);
		
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existauthors" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}
	
	/**
	 * Method to handle the versions data and to populate the new beta4 versions table
	 * From #__flexicontent_items_versions to #__flexicontent_versions
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function populateVersionsTable()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db = JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	= 'SELECT item_id, version FROM #__flexicontent_items_versions'
				. ' WHERE field_id = 2'
				. ' ORDER BY item_id, version'
				;
		$db->setQuery($query);
		$cvs = $db->loadObjectList();

		$query 	= 'SELECT * FROM #__flexicontent_items_versions'
				. ' WHERE field_id IN ( 2,3,4,5 )'
				. ' ORDER BY item_id, version, field_id'
				;
		$db->setQuery($query);
		$fvs = $db->loadObjectList();
		
		for ($i=0; $i<count($cvs); $i++) {
			foreach ($fvs as $fv) {
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '2') $cvs[$i]->created = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '3') $cvs[$i]->created_by = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '4') $cvs[$i]->modified = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '5') $cvs[$i]->modified_by = $fv->value;
			}
		}
		
		$versions = new stdClass();
		$n = 0;
		foreach ($cvs as $cv) {
			$versions->$n->item_id 		= $cv->item_id;
			$versions->$n->version_id 	= $cv->version;
			$versions->$n->comment	 	= '';
			$versions->$n->created 		= (isset($cv->modified) && ($cv->modified != $nullDate)) ? $cv->modified : $cv->created;
			$versions->$n->created_by 	= (isset($cv->modified_by) && $cv->modified_by) ? $cv->modified_by : $cv->created_by;
			$versions->$n->state	 	= 1;
			$db->insertObject('#__flexicontent_versions', $versions->$n);
			$n++;
		}

		echo '<span class="install-ok"></span>';
	}

	/**
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function deleteOldBetaFiles()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		jimport('joomla.filesystem.file');

		$files 	= array (
			'author.xml',
			'author.php',
			'myitems.xml',
			'myitems.php',
			'mcats.xml',
			'mcats.php',
			'default.xml',
			'default.php',
			'index.html',
			'form.php',
			'form.xml'
			);
		$catdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'category'.DS.'tmpl');
		$cattmpl 	= JFolder::files($catdir);		
		$ctmpl 		= array_diff($cattmpl,$files);
		foreach ($ctmpl as $c) {
			JFile::delete($catdir.DS.$c);
		}
		
		$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.FLEXI_ITEMVIEW.DS.'tmpl');
		$itemtmpl 	= JFolder::files($itemdir);		
		$itmpl 		= array_diff($itemtmpl,$files);
		foreach ($itmpl as $i) {
			JFile::delete($itemdir.DS.$i);
		}

		$model = $this->getModel('flexicontent');
		if ($model->getOldBetaFiles()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="oldbetafiles" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}

	/**
	 * Method to delete old core fields data in the fields_items_relations table
	 * Delete also old versions fields data
	 * Alter value fields to mediumtext in order to store large items
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function cleanupOldTables()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db  = JFactory::getDBO();
		$app = JFactory::getApplication();

		$queries 	= array();
		// alter some table field types
		$queries[] 	= "ALTER TABLE #__flexicontent_fields_item_relations CHANGE `value` `value` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_versions CHANGE `value` `value` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `search_index` `search_index` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `sub_items` `sub_items` TEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `sub_categories` `sub_categories` TEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `related_items` `related_items` TEXT" ;

		foreach ($queries as $query) {
			$db->setQuery($query);
			$db->query();
		}
		$query = "SELECT id,version,created,created_by FROM #__content " . (!FLEXI_J16GE ? "WHERE sectionid='".FLEXI_SECTION."'" : "");
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		$jcorefields = flexicontent_html::getJCoreFields();
		$add_cats = true;
		$add_tags = true;
		$clean_database = true;
		
		// For all items not having the current version, add it
		$last_versions = FLEXIUtilities::getLastVersions();
		foreach($rows as $row)
		{
			$lastversion = @$last_versions[$row->id]['version'];
			
			if($row->version > $lastversion)
			{
				// Get field values of the current item version
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder,f.iscore "
						." FROM #__flexicontent_fields_item_relations as fir"
						." JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id=".$row->id." AND f.iscore=0";  // old versions stored categories & tags into __flexicontent_fields_item_relations
				$db->setQuery($query);
				$fields = $db->loadObjectList();
				
				// Delete old data
				if ($clean_database && $fields) {
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$row->id;
					$db->setQuery($query);
					$db->query();
				}
				
				// Add the 'maintext' field to the fields array for adding to versioning table
				$f = new stdClass();
				$f->id					= 1;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->field_type	= "maintext";
				$f->name				= "text";
				$f->value				= $row->introtext;
				if ( JString::strlen($row->fulltext) > 1 ) {
					$f->value .= '<hr id="system-readmore" />' . $row->fulltext;
				}
				if(substr($f->value, 0, 3)!="<p>") {
					$f->value = "<p>".$f->value."</p>";
				}
				$fields[] = $f;

				// Add the 'categories' field to the fields array for adding to versioning table
				$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='".$row->id."';";
				$db->setQuery($query);
				$categories = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if(!$categories || !count($categories)) {
					$categories = array($catid = $row->catid);
					$query = "INSERT INTO #__flexicontent_cats_item_relations VALUES('$catid','".$row->id."', '0');";
					$db->setQuery($query);
					$db->query();
				}
				$f = new stdClass();
				$f->id					= 13;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->version		= (int)$row->version;
				$f->value		= serialize($categories);
				if ($add_cats) $fields[] = $f;
				
				// Add the 'tags' field to the fields array for adding to versioning table
				$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='".$row->id."';";
				$db->setQuery($query);
				$tags = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				$f = new stdClass();
				$f->id					= 14;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->version		= (int)$row->version;
				$f->value		= serialize($tags);
				if ($add_tags) $fields[] = $f;

				// Add field values to field value versioning table
				foreach($fields as $field) {
					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id   = $field->id;
					$obj->item_id    = $row->id;
					$obj->valueorder = $field->valueorder;
					$obj->version    = (int)$row->version;
					$obj->value      = $field->value;
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder."<br />";
					//echo "inserting into __flexicontent_items_versions<br />";
					$db->insertObject('#__flexicontent_items_versions', $obj);
					if( !$field->iscore ) {
						unset($obj->version);
						//echo "inserting into __flexicontent_fields_item_relations<br />";
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
					//$searchindex 	.= @$field->search;
				}
				
				// **********************************************************************************
				// Add basic METADATA of current item version (kept in table #__flexicontent_versions)
				// **********************************************************************************
				$v = new stdClass();
				$v->item_id    = (int)$row->id;
				$v->version_id = (int)$row->version;
				$v->created    = ($row->modified && ($row->modified != $nullDate)) ? $row->modified : $row->created;
				$v->created_by = $row->created_by;
				$v->comment    = '';
				//echo "inserting into __flexicontent_versions<br />";
				$db->insertObject('#__flexicontent_versions', $v);
			}
		}
		
		$queries 	= array();
		// delete unused records
		// 1,'maintext',  2,'created',  3,'createdby',  4,'modified',  5,'modifiedby',  6,'title',  7,'hits'
		// 8,'type',  9,'version',  10,'state',   11,'voting',  12,'favourites',  13,'categories',  14,'tags'
		$queries[] 	= "DELETE FROM #__flexicontent_fields_item_relations WHERE field_id < 15" ;
		$queries[] 	= "DELETE FROM #__flexicontent_items_versions WHERE field_id IN ( 7, 9, 11, 12 )" ;
		
		foreach ($queries as $query) {
			$db->setQuery($query);
			$db->query();
		}

		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();

		$model = $this->getModel('flexicontent');
		if ($model->getNoOldFieldsData()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="oldfieldsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}
	
	function addCurrentVersionData()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$model = $this->getModel('flexicontent');
		if ($model->addCurrentVersionData()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="missingversion" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}
	
	function initialPermission() {
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$model = $this->getModel('flexicontent');
		if ($model->initialPermission()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="initialpermission" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}

	function checkDirtyFields() {
		if (JRequest::getVar('task')!='' || JRequest::getVar('format')!='' || JRequest::getVar('tmpl')!='') return;
		$perms = FlexicontentHelperPerm::getPerm();
		if ( !$perms->CanFields ) return;
		
		$db = JFactory::getDBO();
		
		// GET fields having dirty field properties, NOTE: a dirty field property means that search index must be updated,
		// even if the field was unpublished, because the field values may still exists in the search index for some items
		
		$query = 'SELECT COUNT(*) '
			. ' FROM #__flexicontent_fields'
			. ' WHERE (issearch=-1 || issearch=2)'  // Regardless publication state
			;
		$db->setQuery($query);
		$dirty_basic = $db->loadResult();
		
		$query = 'SELECT COUNT(*) '
			. ' FROM #__flexicontent_fields'
			. ' WHERE (isadvsearch=-1 OR isadvsearch=2 OR isadvfilter=-1 OR isadvfilter=2)'  // Regardless publication state
			;
		$db->setQuery($query);
		$dirty_advanced = $db->loadResult();
		
		if ($dirty_basic)    JError::raiseNotice( 403, JText::sprintf( 'FLEXI_ALERT_UPDATE_BASIC_INDEX', $dirty_basic) );
		if ($dirty_advanced) JError::raiseNotice( 403, JText::sprintf( 'FLEXI_ALERT_UPDATE_ADVANCED_INDEX', $dirty_advanced) );
	}
	
	
	function fversioncompare() {
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		@ob_end_clean();
			JRequest::setVar('layout', 'fversion');
			parent::display();
		exit;
	}
	function doPlgAct() {
		FLEXIUtilities::doPlgAct();
	}
}
?>
