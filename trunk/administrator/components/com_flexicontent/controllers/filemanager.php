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

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Files Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFilemanager extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Upload a file
	 *
	 * @since 1.0
	 */
	function upload()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		
		$user		= & JFactory::getUser();
		$mainframe = &JFactory::getApplication();
		
		
		$option		= JRequest::getVar( 'option');
		$file 		= JRequest::getVar( 'Filedata', '', 'files', 'array' );
		$format		= JRequest::getVar( 'format', 'html', '', 'cmd');
		$secure		= JRequest::getVar( 'secure', 1, '', 'int');
		$return		= JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$filedesc	= JRequest::getVar( 'file-desc', '');
		$fieldid	= JRequest::getVar( 'fieldid', 0);
		$itemid		= JRequest::getVar( 'itemid', 0);
		$file_mode= JRequest::getVar( 'folder_mode', 0) ? 'folder_mode' : 'db_mode';
		$err		= null;
		
		
		// *****************************************
		// Check that a file was provided / uploaded
		// *****************************************
		if ( !isset($file['name']) )
		{
			JError::raiseWarning(100, JText::_( 'Filename has invalid characters (or other error occured)' ));
			$this->setRedirect( $_SERVER['HTTP_REFERER'], '' );
			return;
		}
		
		if ($file_mode == 'folder_mode') {
			$upload_path_var = 'fc_upload_path_'.$fieldid.'_'.$itemid;
			$path = $mainframe->getUserState( $upload_path_var, '' ).DS;
			$mainframe->setUserState( $upload_path_var, '');
		} else {
			$path = $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;
		}
		
		jimport('joomla.utilities.date');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Make the filename safe
		jimport('joomla.filesystem.file');
		$file['name']	= JFile::makeSafe($file['name']);


		//sanitize filename further and make unique
		$params = null;
		$upload_check = flexicontent_upload::check( $file, $err, $params );
		$filename 	= flexicontent_upload::sanitize($path, $file['name']);
		$filepath 	= JPath::clean($path.strtolower($filename));
			
		if (!$upload_check) {
			if ($format == 'json') {
				jimport('joomla.error.log');
				$log = &JLog::getInstance('com_flexicontent.error.php');
				$log->addEntry(array('comment' => 'Invalid: '.$filepath.': '.$err));
				header('HTTP/1.0 415 Unsupported Media Type');
				die('Error. Unsupported Media Type!');
			} else {
				JError::raiseNotice(100, JText::_($err));
				// REDIRECT
				if ($return) {
					$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
				}
				return;
			}
		}
			
		//get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($filename));

		// Upload Failed
		if (!JFile::upload($file['tmp_name'], $filepath)) {
			if ($format == 'json') {
				jimport('joomla.error.log');
				$log = &JLog::getInstance('com_flexicontent.error.php');
				$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
				header('HTTP/1.0 409 Conflict');
				jexit('Error. File already exists');
			} else {
				JError::raiseWarning(100, JText::_( 'FLEXI_UNABLE_TO_UPLOAD_FILE' ));
				// REDIRECT
				if ($return) {
					$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
				}
				return;
			}
				
		// Upload Successful
		} else {
				
			// a. Database mode
			if ($file_mode == 'db_mode')
			{
				if ($format == 'json')
				{
					jimport('joomla.error.log');
					$log = &JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
				}
					
				$db 	= &JFactory::getDBO();
				$user	= &JFactory::getUser();
				$config = &JFactory::getConfig();

				$tzoffset = $config->getValue('config.offset');
				$date = & JFactory::getDate( 'now', -$tzoffset);

				$obj = new stdClass();
				$obj->filename 			= $filename;
				$obj->altname 			= $file['altname'];
				$obj->url			= 0;
				$obj->secure			= $secure;
				$obj->ext			= $ext;
				$obj->hits			= 0;
				$obj->description		= $filedesc;
				$obj->uploaded			= $date->toMySQL();
				$obj->uploaded_by		= $user->get('id');
					
				// Insert file record in DB
				$db->insertObject('#__flexicontent_files', $obj);
					
				// Get id of new file record
				$file_id = (int)$db->insertid();

				$option = JRequest::getVar('option');
				$filter_item = $mainframe->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', '', 'int' );
				if($filter_item) {
					$session = JFactory::getSession();
					$files = $session->get('fileselement.'.$filter_item, null);

					if(!$files) {
						$files = array();
					}
					$files[] = $db->insertid();
					$session->set('fileselement.'.$filter_item, $files);
				}
				
			// b. Custom Folder mode
			} else {
				$file_id = 0;
			}
				
			// JSON output: Terminate printing a message
			if ($format == 'json') {
				jexit('Upload complete');
				
			// Normal output: Redirect setting a message
			} else {
				$mainframe->enqueueMessage(JText::_( 'FLEXI_UPLOAD_COMPLETE' ));
				if ( !$return ) return;  // No return URL
				$mainframe->redirect(base64_decode($return)."&newfileid=".$file_id."&newfilename=".base64_encode($filename)."&".JUtility::getToken()."=1");
			}
				
		}
	}
	
	function ftpValidate()
	{
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
	}

	/**
	 * Upload a file by url
	 *
	 * @since 1.0
	 */
	function addurl()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$mainframe = &JFactory::getApplication();
		
		$return		= JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$filename	= JRequest::getVar( 'file-url-data', null, 'post' );
		$altname	= JRequest::getVar( 'file-url-display', null, 'post', 'string' );
		$ext		= JRequest::getVar( 'file-url-ext', null, 'post', 'alnum' );
		$filedesc	= JRequest::getVar( 'file-url-desc', '');

		jimport('joomla.utilities.date');

		// check if the form fields are not empty
		if (!$filename || !$altname)
		{
			JError::raiseNotice(1, JText::_( 'FLEXI_WARNFILEURLFORM' ));
			if ($return) {
				$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
			}
			return;
		}
		
		// we verifiy the url prefix and add http if any
		if (!eregi("^http|^https|^ftp", $filename)) { $filename	= 'http://'.$filename; }
		
		$db 	= &JFactory::getDBO();
		$user	= &JFactory::getUser();
		$config = &JFactory::getConfig();

		$tzoffset = $config->getValue('config.offset');
		$date = & JFactory::getDate( 'now', -$tzoffset);

		$obj = new stdClass();
		$obj->filename 			= $filename;
		$obj->altname 			= $altname;
		$obj->url			= 1;
		$obj->secure			= 1;
		$obj->ext			= $ext;
		$obj->description		= $filedesc;
		$obj->hits			= 0;
		$obj->uploaded			= $date->toMySQL();
		$obj->uploaded_by		= $user->get('id');

		$db->insertObject('#__flexicontent_files', $obj);

		$mainframe->enqueueMessage(JText::_( 'FLEXI_FILE_ADD_SUCCESS' ));

		$option = JRequest::getVar('option');
		$filter_item = $mainframe->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', '', 'int' );
		if($filter_item) {
			$session = JFactory::getSession();
			$files = $session->get('fileselement.'.$filter_item, null);

			if(!$files) {
				$files = array();
			}
			$files[] = $db->insertid();
			$session->set('fileselement.'.$filter_item, $files);
		}

		// REDIRECT
		if ($return) {
			$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
		}
	}

	/**
	 * Upload a file from a server directory
	 *
	 * @since 1.0
	 */
	function addlocal()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$mainframe = &JFactory::getApplication();
		
		$return		=  JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$filesdir	=  JRequest::getVar( 'file-dir-path', '', 'post' );
		$regexp		=  JRequest::getVar( 'file-filter-re', '.', 'post' );
		$secure		=  JRequest::getInt( 'secure', 1, 'post' );
		$keep		=  JRequest::getInt( 'keep', 1, 'post' );
		$params 	=& JComponentHelper::getParams( 'com_flexicontent' );
		$destpath 	= $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;
		$db 		=& JFactory::getDBO();
		$user		=& JFactory::getUser();
		$config 	=& JFactory::getConfig();
		$tzoffset 	= $config->getValue('config.offset');
		
		$filedesc	=  JRequest::getVar( 'file-desc', '' );

		// allowed extensions
		$filterext	=  JRequest::getVar( 'file-filter-ext', '', 'post' );
		$filterext	= $filterext ? explode(',', $filterext) : array();
		$confext	=  explode(',', $params->get('upload_extensions','jpg,png,gif,bmp'));
		$allowed	= $filterext ? array_intersect($filterext, $confext) : $confext;
	
		jimport('joomla.utilities.date');
		jimport('joomla.filesystem.file');

		$filesdir = JPath::clean(JPATH_SITE . $filesdir . DS);
		
		$filenames = JFolder::files($filesdir, $regexp);

		// create the folder if it doesnt exists
		if (!JFolder::exists($destpath)) { 
			if (!JFolder::create($destpath)) { 
				JError::raiseWarning(100, JText::_('Error. Unable to create folders'));
				return;
			} 
		}
		
		
		// check if the form fields are not empty
		if (!$filesdir)
		{
			JError::raiseNotice(1, JText::_( 'FLEXI_WARN_NO_FILE_DIR' ));
			if ($return) {
				$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
			}
			return;
		}
		
		$c = 0;
		if($filenames) {
			for ($n=0; $n<count($filenames); $n++) {
				if (in_array(JFile::getExt($filesdir . $filenames[$n]), $allowed)) {
					$source 		= $filesdir . $filenames[$n];
					$filename		= flexicontent_upload::sanitize($destpath, $filenames[$n]);
					$destination 	= $destpath . $filename;
					$ext			= JFile::getExt($filesdir . $filenames[$n]);
					
					if ($keep) {
						// Copy the file
						if (JFile::copy($source, $destination))
						{
							$date =& JFactory::getDate( 'now', -$tzoffset);
						
							$obj = new stdClass();
							$obj->filename 			= $filename;
							$obj->altname 			= $filename;
							$obj->url			= 0;
							$obj->secure			= $secure;
							$obj->ext			= $ext;
							$obj->description		= $filedesc;
							$obj->hits			= 0;
							$obj->uploaded			= $date->toMySQL();
							$obj->uploaded_by		= $user->get('id');

							// Add the record to the DB
							$db->insertObject('#__flexicontent_files', $obj);
						
							$c++;
						}
					} else {
						// Move the file
						if (JFile::move($source, $destination))
						{
							$date =& JFactory::getDate( 'now', -$tzoffset);
						
							$obj = new stdClass();
							$obj->filename 			= $filename;
							$obj->altname 			= $filename;
							$obj->url			= 0;
							$obj->secure			= $secure;
							$obj->ext			= $ext;
							$obj->description		= $filedesc;
							$obj->hits			= 0;
							$obj->uploaded			= $date->toMySQL();
							$obj->uploaded_by		= $user->get('id');

							// Add the record to the DB
							$db->insertObject('#__flexicontent_files', $obj);
						
							$c++;
						}
					}
				}
			}
			$mainframe->enqueueMessage(JText::sprintf( 'FLEXI_FILES_COPIED_SUCCESS', $c ));
		} else {
			JError::raiseNotice(1, JText::_( 'FLEXI_WARN_NO_FILES_IN_DIR' ));
			if ($return) {
				$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
			}
			return;
		}
		
					
		// REDIRECT
		if ($return) {
			$mainframe->redirect(base64_decode($return)."&".JUtility::getToken()."=1");
		}
	}

	/**
	 * Logic for editing a file
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit( )
	{	
		$user		= & JFactory::getUser();
		$model	= & $this->getModel('file');
		$file		= & $model->getFile();
		
		JRequest::setVar( 'view', 'file' );
		JRequest::setVar( 'hidemainmenu', 1 );
		
		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$edit_task = FLEXI_J16GE ? "task=filemanager.edit" : "controller=filemanager&task=edit";
			$this->setRedirect( 'index.php?option=com_flexicontent&'.$edit_task, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
		}

		$model->checkout( $user->get('id') );

		parent::display();
	}
	
	/**
	 * Logic to delete files
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		
		$user		= & JFactory::getUser();
		$db =& JFactory::getDBO();
		$model	= & $this->getModel('file');
		$file		= & $model->getFile();
		$app = & JFactory::getApplication();
		
		$fieldid   = JRequest::getVar( 'fieldid', 0);
		$itemid    = JRequest::getVar( 'itemid', 0);
		$file_mode = JRequest::getVar( 'folder_mode', 0) ? 'folder_mode' : 'db_mode';
		
		if ($file_mode) {
			$filename = JRequest::getVar( 'filename' );
			
			$db->setQuery("SELECT * FROM #__flexicontent_fields WHERE id='".$fieldid."'");
			$field = $db->loadObject();
			$field->parameters = new JParameter($field->attribs);			
			$field->item_id = $itemid;
			
			$result = FLEXIUtilities::call_FC_Field_Func($field->field_type, 'removeOriginalFile', array( &$field, $filename ) );
			
			if ( !$result ) {
				JError::raiseWarning(100, JText::_( 'FLEXI_UNABLE_TO_CLEANUP_ORIGINAL_FILE' ) .": ". $path);
				$msg = '';
			} else {
				$msg = JText::_( 'FLEXI_FILES_DELETED' );
			}
			$this->setRedirect( $_SERVER['HTTP_REFERER'].'&delfilename='.base64_encode($filename), $msg );
			return;
		}
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$model 		= $this->getModel('filemanager');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_REMOVE_THIS_FILE' ));
		} else {

			if (!$model->delete($cid)) {
				JError::raiseError(500, JText::_( 'FLEXI_OPERATION_FAILED' ));
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_FILES_DELETED' );
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
				
		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', $msg );
	}
	
	/**
	 * Logic for saving altered file data
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$user		= & JFactory::getUser();
		$model	= & $this->getModel('file');
		$task		= JRequest::getVar('task');
		$post		= JRequest::get( 'post' );
		$file		= & $model->getFile();
		
		if ($model->store($post)) {

			switch ($task)
			{
				case 'apply' :
					$edit_task = FLEXI_J16GE ? "task=filemanager.edit" : "controller=filemanager&task=edit";
					$link = 'index.php?option=com_flexicontent&'.$edit_task.'&cid[]='.(int) $model->get('id');
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=filemanager';
					break;
			}
			$msg = JText::_( 'FLEXI_FILE_SAVED' );

			$model->checkin();
			
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_FILENAME' );
			JError::raiseError( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=filemanager';
		}

		$this->setRedirect($link, $msg);
	}
	
	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user		= & JFactory::getUser();
		$model	= & $this->getModel('file');
		$file		= & $model->getFile();
		$task		= JRequest::getVar('task');
		$post = JRequest::get( 'post' );
		
		// Check In the file and redirect ...
		$file = & JTable::getInstance('flexicontent_files', '');
		$file->bind(JRequest::get('post'));
		$file->checkin();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager' );
	}
	
	/**
	 * Logic to publish a file
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function publish()
	{
		$this->changeState(1);
	}
	
	/**
	 * Logic to unpublish a file
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function unpublish()
	{
		$this->changeState(0);
	}
	
	
	/**
	 * Logic to change publication state of files
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function changeState($state)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user		= & JFactory::getUser();
		$model	= & $this->getModel('file');
		$file		= & $model->getFile();
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( $state ? 'FLEXI_SELECT_ITEM_PUBLISH' : 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else {

			$model = $this->getModel('filemanager');

			if(!$model->publish($cid, $state)) {
				JError::raiseError(500, $model->getError());
			}

			$msg 	= JText::_( $state ? 'Published file' : 'Unpublished file' );
		
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', $msg );
	}

}
