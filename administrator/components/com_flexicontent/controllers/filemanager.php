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
		global $mainframe;

		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$file 		= JRequest::getVar( 'Filedata', '', 'files', 'array' );
		$format		= JRequest::getVar( 'format', 'html', '', 'cmd');
		$secure		= JRequest::getVar( 'secure', 1, '', 'int');
		$return		= JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$err		= null;
		
		jimport('joomla.utilities.date');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Make the filename safe
		jimport('joomla.filesystem.file');
		$file['name']	= JFile::makeSafe($file['name']);

		if (isset($file['name'])) {

			$path = $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;

			//sanitize filename further and make unique
			$filename 	= flexicontent_upload::sanitize($path, $file['name']);
			$filepath 	= JPath::clean($path.strtolower($filename));
			
			if (!flexicontent_upload::check( $file, $err )) {
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
						$mainframe->redirect(base64_decode($return));
					}
					return;
				}
			}
			
			//get the extension to record it in the DB
			$ext		= strtolower(JFile::getExt($filename));

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
						$mainframe->redirect(base64_decode($return));
					}
					return;
				}
			} else {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = &JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
					
					$db 	= &JFactory::getDBO();
					$user	= &JFactory::getUser();
					$config = &JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date = & JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= $secure;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);
					
					$option = JRequest::getVar('option');
					$filter_item = $mainframe->getUserStateFromRequest( $option.'.fileselement.items', 'items', '', 'int' );
					if($filter_item) {
						$session = JFactory::getSession();
						$files = $session->get('fileselement.'.$filter_item, null);

						if(!$files) {
							$files = array();
						}
						$files[] = $db->insertid();
						$session->set('fileselement.'.$filter_item, $files);
					}

					jexit('Upload complete');
				} else {

					$db 	= &JFactory::getDBO();
					$user	= &JFactory::getUser();
					$config = &JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date = & JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= $secure;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);

					$mainframe->enqueueMessage(JText::_( 'FLEXI_UPLOAD_COMPLETE' ));
					
					$option = JRequest::getVar('option');
					$filter_item = $mainframe->getUserStateFromRequest( $option.'.fileselement.items', 'items', '', 'int' );
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
						$mainframe->redirect(base64_decode($return));
					}
					return;
				}
			}
		}
		$mainframe->redirect(base64_decode($return));
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
		global $mainframe;

		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$return		= JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$filename	= JRequest::getVar( 'file-url-data', null, 'post' );
		$altname	= JRequest::getVar( 'file-url-display', null, 'post', 'string' );
		$ext		= JRequest::getVar( 'file-url-ext', null, 'post', 'alnum' );

		jimport('joomla.utilities.date');

		// check if the form fields are not empty
		if (!$filename || !$altname)
		{
			JError::raiseNotice(1, JText::_( 'FLEXI_WARNFILEURLFORM' ));
			if ($return) {
				$mainframe->redirect(base64_decode($return));
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
		$obj->url				= 1;
		$obj->secure			= 1;
		$obj->ext				= $ext;
		$obj->hits				= 0;
		$obj->uploaded			= $date->toMySQL();
		$obj->uploaded_by		= $user->get('id');

		$db->insertObject('#__flexicontent_files', $obj);

		$mainframe->enqueueMessage(JText::_( 'FLEXI_FILE_ADD_SUCCESS' ));

		$option = JRequest::getVar('option');
		$filter_item = $mainframe->getUserStateFromRequest( $option.'.fileselement.items', 'items', '', 'int' );
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
			$mainframe->redirect(base64_decode($return));
		}
	}

	/**
	 * Upload a file from a server directory
	 *
	 * @since 1.0
	 */
	function addlocal()
	{
		global $mainframe;

		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

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
				$mainframe->redirect(base64_decode($return));
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
							$obj->url				= 0;
							$obj->secure			= $secure;
							$obj->ext				= $ext;
							$obj->hits				= 0;
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
							$obj->url				= 0;
							$obj->secure			= $secure;
							$obj->ext				= $ext;
							$obj->hits				= 0;
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
				$mainframe->redirect(base64_decode($return));
			}
			return;
		}
		
					
		// REDIRECT
		if ($return) {
			$mainframe->redirect(base64_decode($return));
		}
	}

	/**
	 * Logic to create the view for the edit categoryscreen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit( )
	{	
		JRequest::setVar( 'view', 'file' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model 	= $this->getModel('file');
		$user	=& JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&controller=filemanager&task=edit', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
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
	 * Logic to save the altname
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$task		= JRequest::getVar('task');

		//Sanitize
		$post = JRequest::get( 'post' );

		$model = $this->getModel('file');

		if ($model->store($post)) {

			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&controller=filemanager&task=edit&cid[]='.(int) $model->get('id');
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
		
		$file = & JTable::getInstance('flexicontent_files', '');
		$file->bind(JRequest::get('post'));
		$file->checkin();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager' );
	}
	
	/**
	 * Logic to publish filemanager
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function publish()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
		} else {

			$model = $this->getModel('filemanager');

			if(!$model->publish($cid, 1)) {
				JError::raiseError(500, $model->getError());
			}

			$msg 	= JText::_( 'Published file' );
		
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', $msg );
	}
	
	/**
	 * Logic to unpublish filemanager
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function unpublish()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else {

			$model = $this->getModel('filemanager');

			if(!$model->publish($cid, 0)) {
				JError::raiseError(500, $model->getError());
			}

			$msg 	= JText::_( 'Unpublished file' );
		
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', $msg );
	}
}