<?php
/**
 * @version 1.5 stable $Id: filemanager.php 1846 2014-02-14 02:36:41Z enjoyman@gmail.com $
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');   // we use JPATH_BASE since parent controller exists in frontend too


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
		$this->registerTask( 'uploads', 	'upload' );
		
		$view = JRequest::getVar('view', 'filemanager');
		$this->return_url = $view == 'filemanager' ?
			'index.php?option=com_flexicontent&view=filemanager' :
			$_SERVER['HTTP_REFERER'] ;
	}
	
	/**
	 * Upload files
	 *
	 * @since 1.0
	 */
	function upload()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );
		
		$user = JFactory::getUser();
		$app  = JFactory::getApplication();
		
		$task = JRequest::getVar('task');
		
		// calculate access
		$canupload = $user->authorise('flexicontent.uploadfiles', 'com_flexicontent');
		$is_authorised = $canupload;
		
		// check access
		if ( !$is_authorised ) {
			if ($task=='uploads') {
				die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "'.JText::_( 'FLEXI_ALERTNOTAUTH' ).'"}, "id" : "id"}');
			} else {
				JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
				$this->setRedirect( $this->return_url, '');
			}
			return;
		}

		$option		= JRequest::getVar( 'option');
		if ($task=='uploads') {
			$file = JRequest::getVar( 'file', '', 'files', 'array' );
		} else {
			// Default field <input type="file" is name="Filedata" ... get the file
			$ffname = JRequest::getCmd( 'file-ffname', 'Filedata', 'post' );
			$file   = JRequest::getVar( $ffname, '', 'files', 'array' );
			
			// Refactor the array swapping positions
			$file = $this->refactorFilesArray($file);
			
			// Get nested position, and reach the final file data array
			$fname_level1 = JRequest::getCmd( 'fname_level1', null, 'post' );
			$fname_level2 = JRequest::getCmd( 'fname_level2', null, 'post' );
			$fname_level3 = JRequest::getCmd( 'fname_level3', null, 'post' );
			
			if (strlen($fname_level1))  $file = $file[$fname_level1];
			if (strlen($fname_level2))  $file = $file[$fname_level2];
			if (strlen($fname_level3))  $file = $file[$fname_level3];
		}
		$format		= JRequest::getVar( 'format', 'html', '', 'cmd' );
		$secure		= JRequest::getInt( 'secure', 1 );
		$secure		= $secure ? 1 : 0;
		$return		= JRequest::getVar( 'return-url', null, '', 'base64' );
		$filetitle= JRequest::getVar( 'file-title', '' );
		$filedesc	= JRequest::getVar( 'file-desc', '' );
		$filelang	= JRequest::getVar( 'file-lang', '' );
		$fieldid	= JRequest::getVar( 'fieldid', 0 );
		$u_item_id= JRequest::getVar( 'u_item_id', 0 );
		$file_mode= JRequest::getVar( 'folder_mode', 0 ) ? 'folder_mode' : 'db_mode';
		$err		= null;
		
		$model = $this->getModel('filemanager');
		if ($file_mode != 'folder_mode' && $fieldid)
		{
			// Check if FORCED secure/media mode parameter exists and if it is forced
			$field_params = $model->getFieldParams($fieldid);
			$target_dir = $field_params->get('target_dir', '');
			
			if ( strlen($target_dir) && $target_dir!=2 ) {
				$secure = $target_dir ? 1 : 0; // force secure / media
			} else {
				// allow filter secure via form/URL variable
			}
		}
		
		// *****************************************
		// Check that a file was provided / uploaded
		// *****************************************
		if ( !isset($file['name']) )
		{
			if ($task=='uploads') {
				die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "'.JText::_( 'Filename has invalid characters (or other error occured)' ).'"}, "id" : "id"}');
			} else {
				JError::raiseWarning(100, JText::_( 'Filename has invalid characters (or other error occured)' ));
				$this->setRedirect( $_SERVER['HTTP_REFERER'], '' );
			}
			return;
		}
		
		// Chunking might be enabled
		$chunks = JRequest::getInt('chunks');
		if ($chunks)
		{
			$chunk = JRequest::getInt('chunk');
			
			// Get / Create target directory
			$targetDir = (ini_get("upload_tmp_dir") ? ini_get("upload_tmp_dir") : sys_get_temp_dir()) . DIRECTORY_SEPARATOR . "fc_fileselement";
			if (!file_exists($targetDir))  @mkdir($targetDir);
			
			// Create name of the unique temporary filename to use for concatenation of the chunks, or get the filename from session
			$fileName = JRequest::getVar( 'filename' );
			$fileName_tmp = $app->getUserState( $fileName, date('Y_m_d_').uniqid() );
			$app->setUserState( $fileName, $fileName_tmp );
			
			$filePath_tmp = $targetDir . DIRECTORY_SEPARATOR . $fileName_tmp;
			
			// CREATE tmp file inside SERVER tmp directory, but if this FAILS, then CREATE tmp file inside the Joomla temporary folder
			if (!$out = @fopen("{$filePath_tmp}", "ab"))
			{
				
				$targetDir = $app->getCfg('tmp_path') . DIRECTORY_SEPARATOR . "fc_fileselement";
				if (!file_exists($targetDir))  @mkdir($targetDir);
				$filePath_tmp = $targetDir . DIRECTORY_SEPARATOR . $fileName_tmp;
				
				ini_set('track_errors', 1);
				if (!$out = @fopen("{$filePath_tmp}", "ab")) {
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream: '.$filePath_tmp. ' fopen failed. reason: ' .@$php_errormsg. '"}, "id" : "id"}');
				}
			}
			
			if (!empty($_FILES)) {
				if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"]))
					die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
				if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb"))
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
			} else {	
				if (!$in = @fopen("php://input", "rb"))
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
			}
			// Read binary input stream and append it to temp file
			while ($buff = fread($in, 4096)) {
				fwrite($out, $buff);
			}
			
			@fclose($out);
			@fclose($in);
			
			// If not last chunk terminate further execution
			if ($chunk < $chunks - 1) {
				// Return Success JSON-RPC response
				die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
			}
			$app->setUserState( $fileName, null );
			
			// Cleanup left-over files
			if (file_exists($targetDir)) {
				foreach (new DirectoryIterator($targetDir) as $fileInfo) {
					if ($fileInfo->isDot()) {
						continue;
					}
					if (time() - $fileInfo->getCTime() >= 60) {
						unlink($fileInfo->getRealPath());
					}
				}
			}
			
			//echo "-- chunk: $chunk \n-- chunks: $chunks \n-- targetDir: $targetDir \n--filePath_tmp: $filePath_tmp \n--fileName: $fileName";
			//echo "\n"; print_r($_REQUEST);
			$file['name'] = $fileName;
			$file['tmp_name'] = $filePath_tmp;
			$file['size'] = filesize($filePath_tmp);
			$file['error'] = 0;
			//echo "\n"; print_r($file);
		}
		
		if ($file_mode == 'folder_mode') {
			$upload_path_var = 'fc_upload_path_'.$fieldid.'_'.$u_item_id;
			$path = $app->getUserState( $upload_path_var, '' ).DS;
			if ($task!='uploads') $app->setUserState( $upload_path_var, '');  // Do not clear in multi-upload
		} else {
			$path = $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;
		}
		
		jimport('joomla.utilities.date');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Make the filename safe
		jimport('joomla.filesystem.file');

		// Sanitize filename further and make unique
		$params = null;
		$filename_original = strip_tags($file['name']);  // Store original filename before sanitizing the filename
		$upload_check = flexicontent_upload::check( $file, $err, $params );
		$filename 	  = flexicontent_upload::sanitize($path, $file['name']);
		$filepath 	  = JPath::clean($path.strtolower($filename));
		
		// Check if uploaded file is valid
		if (!$upload_check) {
			if ($format == 'json') {
				jimport('joomla.error.log');
				$log = JLog::getInstance('com_flexicontent.error.php');
				$log->addEntry(array('comment' => 'Invalid: '.$filepath.': '.$err));
				header('HTTP/1.0 415 Unsupported Media Type');
				if ($task=='uploads') {
					die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Error. Unsupported Media Type!"}, "id" : "id"}');
				} else {
					die('Error. Unsupported Media Type!');
				}
			} else {
				if ($task=='uploads') {
					die('{"jsonrpc" : "2.0", "error" : {"code": 104, "message": "'.$err.'"}, "id" : "id"}');
				} else {
					JError::raiseNotice(100, JText::_($err));
					// REDIRECT
					if ($return) {
						$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
					}
				}
				return;
			}
		}
		
		// Get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($filename));

		// Upload Failed
		//echo "\n". $file['tmp_name'] ." => ". $filepath ."\n";
		$move_success = $chunks ?
			rename($file['tmp_name'], $filepath) :
			JFile::upload($file['tmp_name'], $filepath, false, false,
				// - Valid extensions are checked by our helper function
				// - also we allow all extensions and php inside content, FLEXIcontent will never execute "include" files evening when doing "in-browser viewing"
				array('null_byte'=>true, 'forbidden_extensions'=>array('_fake_ext_'), 'php_tag_in_content'=>true, 'shorttag_in_content'=>true, 'shorttag_extensions'=>array(), 'fobidden_ext_in_content'=>false, 'php_ext_content_extensions'=>array() )
			);
		if (!$move_success) {
			if ($format == 'json') {
				jimport('joomla.error.log');
				$log = JLog::getInstance('com_flexicontent.error.php');
				$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
				header('HTTP/1.0 409 Conflict');
				if ($task=='uploads') {
					die('{"jsonrpc" : "2.0", "error" : {"code": 105, "message": "File already exists"}, "id" : "id"}');
				} else {
					jexit('Error. File already exists');
				}
			} else {
				if ($task=='uploads') {
					die('{"jsonrpc" : "2.0", "error" : {"code": 106, "message": "'.JText::_( 'FLEXI_UNABLE_TO_UPLOAD_FILE' ).'"}, "id" : "id"}');
				} else {
					JError::raiseWarning(100, JText::_( 'FLEXI_UNABLE_TO_UPLOAD_FILE' ));
					// REDIRECT
					if ($return) {
						$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
					}
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
					$log = JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
				}
					
				$db 	= JFactory::getDBO();
				$user	= JFactory::getUser();
				
				$path = $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;  // JPATH_ROOT . DS . <media_path | file_path> . DS
				$filepath = $path . $filename;
				$filesize = file_exists($filepath) ? filesize($filepath) : 0;
				
				$obj = new stdClass();
				$obj->filename    = $filename;
				$obj->filename_original = $filename_original;
				$obj->altname     = $filetitle ? $filetitle : $filename_original;
				$obj->url         = 0;
				$obj->secure      = $secure;
				$obj->ext         = $ext;
				$obj->hits        = 0;
				$obj->size        = $filesize;
				$obj->description = $filedesc;
				$obj->language    = $filelang ? $filelang : '*';
				$obj->uploaded    = JFactory::getDate( 'now' )->toSql();
				$obj->uploaded_by = $user->get('id');
					
				// Insert file record in DB
				$db->insertObject('#__flexicontent_files', $obj);
					
				// Get id of new file record
				$file_id = (int)$db->insertid();

				$option = JRequest::getVar('option');
				$filter_item = $app->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', '', 'int' );
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
				if ($task=='uploads') {
					// Return Success JSON-RPC response
					die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
				} else {
					jexit('FLEXI_UPLOAD_COMPLETE');
				}
			// Normal output: Redirect setting a message
			} else {
				if ($task=='uploads') {
					die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
				} else {
					$app->enqueueMessage(JText::_( 'FLEXI_UPLOAD_COMPLETE' ));
					if ( !$return ) return $file_id;  // No return URL, return the file ID
					$this->setRedirect(base64_decode($return)."&newfileid=".$file_id."&newfilename=".base64_encode($filename)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1" , '');
				}
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

		$app = JFactory::getApplication();
		$jinput = $app->input;
		
		$return   = $jinput->get( 'return-url', null, 'base64' );
		$filename = $jinput->get( 'file-url-data', null, 'string');
		$filename = flexicontent_html::dataFilter($filename, 4000, 'URL', 0);  // Validate file URL
		$altname  = $jinput->get( 'file-url-title', null, 'string' );
		$ext      = $jinput->get( 'file-url-ext', null, 'alnum' );
		$filedesc = $jinput->get( 'file-url-desc', '');  // Default filtering
		$filelang = $jinput->get( 'file-url-lang', '*', 'string');
		$filesize = $jinput->get( 'file-url-size', 0, 'int');
		$size_unit= $jinput->get( 'size_unit', 'KBs', 'cmd');

		jimport('joomla.utilities.date');

		// check if the form fields are not empty
		if (!$filename || !$altname)
		{
			JError::raiseNotice(1, JText::_( 'FLEXI_WARNFILEURLFORM' ));
			if ($return) {
				$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
			}
			return;
		}

		$arr_sizes = array('KBs'=>1024, 'MBs'=>(1024*1024), 'GBs'=>(1024*1024*1024));
		$size_unit = (int) @ $arr_sizes[$size_unit];
		if ( $size_unit )
			$filesize = ((int)$filesize) * $size_unit;
		else
			$filesize = 0;
		
		// we verifiy the url prefix and add http if any
		if (!preg_match("#^http|^https|^ftp#i", $filename)) { $filename	= 'http://'.$filename; }
		
		$db 	= JFactory::getDBO();
		$user	= JFactory::getUser();
		
		$obj = new stdClass();
		$obj->filename    = $filename;
		$obj->altname     = $altname;
		$obj->url         = 1;
		$obj->secure      = 1;
		$obj->ext         = $ext;
		$obj->description = $filedesc;
		$obj->language    = $filelang ? $filelang : '*';
		$obj->hits        = 0;
		$obj->size        = $filesize;
		$obj->uploaded    = JFactory::getDate( 'now' )->toSql();
		$obj->uploaded_by = $user->get('id');

		$db->insertObject('#__flexicontent_files', $obj);

		$app->enqueueMessage(JText::_( 'FLEXI_FILE_ADD_SUCCESS' ));

		$option = $jinput->get('option', '', 'cmd');
		$filter_item = $app->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', '', 'int' );
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
			$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
		}
	}

	/**
	 * Upload a file from a server directory
	 *
	 * @since 1.0
	 */
	function addlocal($Fobj=null)
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$app    = JFactory::getApplication();
		$db 		= JFactory::getDBO();
		$user		= JFactory::getUser();
		$params = JComponentHelper::getParams( 'com_flexicontent' );
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		static $imported_files = array();
		
		$return		= $Fobj ? $Fobj->return_url     : JRequest::getVar( 'return-url', null, 'post', 'base64' );
		$filesdir	= $Fobj ? $Fobj->file_dir_path  : JRequest::getVar( 'file-dir-path', '', 'post' );
		$regexp		= $Fobj ? $Fobj->file_filter_re : JRequest::getVar( 'file-filter-re', '.', 'post' );
		$secure		= $Fobj ? $Fobj->secure         : JRequest::getInt( 'secure', 1, 'post' );
		$keep			= $Fobj ? $Fobj->keep           : JRequest::getInt( 'keep', 1, 'post' );
		
		$secure		= $secure ? 1 : 0;  // A correction for future compatibility, so that secure may have more values
		$destpath = $secure ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;
		
		$filedesc	=  JRequest::getVar( 'file-desc', '' );
		$filelang	=  JRequest::getVar( 'file-lang', '');
		
		// allowed extensions
		$filterext	=  JRequest::getVar( 'file-filter-ext', '', 'post' );
		$filterext	= $filterext ? explode(',', $filterext) : array();
		foreach($filterext as $_i => $_ext) $filterext[$_i] = strtolower($_ext);
		
		$confext = preg_split("/[\s]*,[\s]*/", strtolower($params->get('upload_extensions', 'bmp,csv,doc,docx,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,swf,txt,xcf,xls,xlsx,zip,ics')));
		
		// (optionally) Limit COMPONENT configured extensions, to those extensions requested by the FORM/URL variable
		$allowed	= $filterext ? array_intersect($filterext, $confext) : $confext;
	
		jimport('joomla.utilities.date');
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

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
			if (!$return) return;  // REDIRECT only if this was requested
			$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
		}
		
		$c = 0;
		$file_ids = array();
		if($filenames)
		{
			for ($n=0; $n<count($filenames); $n++)
			{
				$ext = strtolower(JFile::getExt($filesdir . $filenames[$n]));
				if ( !in_array($ext, $allowed) ) continue;
				
				$source 		= $filesdir . $filenames[$n];
				$filename		= flexicontent_upload::sanitize($destpath, $filenames[$n]);
				$destination 	= $destpath . $filename;
				
				// Check for file already added by import task and do not re-add the file
				if ( $is_importcsv && isset($imported_files[$source]) ) {
					$file_ids[$filename] = $imported_files[$source];
					continue;
				}
				
				// Copy or move the file
				$success = $keep  ?  JFile::copy($source, $destination)  :  JFile::move($source, $destination) ;
				if ($success)
				{
					$filesize = filesize($destination);
					
					$obj = new stdClass();
					$obj->filename    = $filename;
					$obj->altname     = $filename;
					$obj->url         = 0;
					$obj->secure      = $secure;
					$obj->ext         = $ext;
					$obj->description = $filedesc;
					$obj->language    = $filelang ? $filelang : '*';
					$obj->hits        = 0;
					$obj->size        = $filesize;
					$obj->uploaded    = JFactory::getDate( 'now' )->toSql();
					$obj->uploaded_by = $user->get('id');

					// Add the record to the DB
					$db->insertObject('#__flexicontent_files', $obj);
					$file_ids[$filename] = $db->insertid();
					
					// Add file ID to files imported by import task
					if ( $is_importcsv )  $imported_files[$source] = $file_ids[$filename];
					
					$c++;
				}
			}
			$app->enqueueMessage(JText::sprintf( 'FLEXI_FILES_COPIED_SUCCESS', $c ));
		} else {
			JError::raiseNotice(1, JText::_( 'FLEXI_WARN_NO_FILES_IN_DIR' ));
			if (!$return) return;  // REDIRECT only if this was requested
			$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
		}
		
					
		if (!$return) return $file_ids;  // REDIRECT only if this was requested
		$app->redirect(base64_decode($return)."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1");
	}
	
	
	/**
	 * Logic for editing a file
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{	
		require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		$user		= JFactory::getUser();
		$model	= $this->getModel('file');
		$file		= $model->getFile();
		
		JRequest::setVar( 'view', 'file' );
		JRequest::setVar( 'hidemainmenu', 1 );
		
		// calculate access
		$canedit = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$caneditown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent') && $file->uploaded_by == $user->get('id');
		$is_authorised = $canedit || $caneditown;
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'index.php?option=com_flexicontent&view=filemanager', '');
			return;
		}
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'index.php?option=com_flexicontent&view=filemanager', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'index.php?option=com_flexicontent&view=filemanager', '');
			return;
		}
		
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
		
		//require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		$user  = JFactory::getUser();
		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		
		$fieldid   = JRequest::getVar( 'fieldid', 0);
		$u_item_id = JRequest::getVar( 'u_item_id', 0);
		$file_mode = JRequest::getVar( 'folder_mode', 0) ? 'folder_mode' : 'db_mode';
		
		if ($file_mode == 'folder_mode') {
			$filename = rawurldecode( JRequest::getVar('filename') );
			//$filename_original = iconv(mb_detect_encoding($filename, mb_detect_order(), true), "UTF-8", $filename);
			$db->setQuery("SELECT * FROM #__flexicontent_fields WHERE id='".$fieldid."'");
			$field = $db->loadObject();
			$field->parameters = new JRegistry($field->attribs);
			$field->item_id = $u_item_id;
			
			$result = FLEXIUtilities::call_FC_Field_Func($field->field_type, 'removeOriginalFile', array( &$field, $filename ) );
			
			if ( !$result ) {
				JError::raiseWarning(100, JText::_( 'FLEXI_UNABLE_TO_CLEANUP_ORIGINAL_FILE' ) .": ". $path);
				$msg = '';
			} else {
				$msg = JText::_( 'FLEXI_FILES_DELETED' );
			}
			$vc_start = StringHelper::strrpos('?', $_SERVER['HTTP_REFERER']) ? '&' : '?'; 
			$this->setRedirect( $_SERVER['HTTP_REFERER'].$vc_start.'delfilename='.base64_encode($filename), $msg );
			return;
		}
		
		// calculate access
		$candelete = $user->authorise('flexicontent.deletefile', 'com_flexicontent');
		$candeleteown = $user->authorise('flexicontent.deleteownfile', 'com_flexicontent');
		$is_authorised = $candelete || $candeleteown;
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( $this->return_url, '');
			return;
		}
		
		$cid = JRequest::getVar( 'cid', array(), $hash='default', 'array' );
		JArrayHelper::toInteger($cid, array());
		
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		}
		
		else {
			$msg = '';
			
			$db->setQuery( 'SELECT * FROM #__flexicontent_files WHERE id IN ('.implode(',', $cid).')' );
			$files = $db->loadObjectList('id');
			$cid = array_keys($files);
			
			$model = $this->getModel('filemanager');
			$deletable = $model->getDeletable($cid);
			
			if (count($cid) != count($deletable))
			{
				$_del = array_flip($deletable);
				$inuse_files = array();
				foreach ($files as $_id => $file) if ( !isset($_del[$_id]) )  $inuse_files[] = $file->filename_original ? $file->filename_original : $file->filename;
				$app->enqueueMessage(JText::_( 'FLEXI_CANNOT_REMOVE_FILES_IN_USE' ) .': '. implode(', ', $inuse_files), 'warning');
				$cid = $deletable;
			}
			
			$allowed_files = array();
			$denied_files = array();
			foreach($cid as $_id) {
				if ( !isset($files[$_id]) ) continue;
				$filename = $files[$_id]->filename_original ? $files[$_id]->filename_original : $files[$_id]->filename;
				if ($candelete || $files[$_id]->uploaded_by == $user->get('id'))
					$allowed_files[$_id] = $filename;
				else
					$denied_files[$_id] = $filename;
			}
			if ( count($denied_files) ) {
				$app->enqueueMessage( ' You are not allowed to delete files: '. implode(', ', $denied_files), 'warning');
			}
			$allowed_cid = array_keys($allowed_files);
			
			if (count($allowed_cid) && !$model->delete($allowed_cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			if (count($allowed_cid)) $msg .= count($allowed_cid).' '.JText::_( 'FLEXI_FILES_DELETED' );
			
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( $this->return_url, $msg );
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
		$jinput = JFactory::getApplication()->input;
		
		require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		$user		= JFactory::getUser();
		$model	= $this->getModel('file');
		$file		= $model->getFile();

		$task = $jinput->get('task', '', 'cmd');
		$data = $jinput->post->getArray();  // Default filtering will remove HTML

		// calculate access
		$canedit = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$caneditown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent') && $file->uploaded_by == $user->get('id');
		$is_authorised = $canedit || $caneditown;

		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', '');
			return;
		}

		$data['secure'] = $data['secure'] ? 1 : 0;   // only allow 1 or 0
		$data['url'] = $data['url'] ? 1 : 0;   // only allow 1 or 0

		$path = $data['secure'] ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;  // JPATH_ROOT . DS . <media_path | file_path> . DS
		$file_path = JPath::clean($path . $data['filename']);

		if (!$data['url'])
		{
			// Get file size from filesystem (local file)
			$data['size'] = file_exists($file_path) ? filesize($file_path) : 0;
		}
		else
		{
			// Get file size from submitted field (file URL)
			$arr_sizes = array('KBs'=>1024, 'MBs'=>(1024*1024), 'GBs'=>(1024*1024*1024));
			$size_unit = (int) @ $arr_sizes[$data['size_unit']];
			if ( $size_unit )
				$data['size'] = ((int)$data['size']) * $size_unit;
			else
				$data['size'] = 0;

		  // Validate file URL
			$data['filename_original'] = flexicontent_html::dataFilter($data['filename_original'], 4000, 'URL', 0);  // Clean bad text/html
		}

		if ($model->store($data))
		{
			switch ($task)
			{
				case 'apply' :
					$edit_task = "task=filemanager.edit";
					$link = 'index.php?option=com_flexicontent&'.$edit_task.'&cid[]='.(int) $model->get('id');
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=filemanager';
					break;
			}
			$msg = JText::_( 'FLEXI_FILE_SAVED' );
			$model->checkin();
			
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_FILENAME' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
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
		
		require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		$user		= JFactory::getUser();
		$model	= $this->getModel('file');
		$file		= $model->getFile();
		
		$task = JRequest::getVar('task');
		$post = JRequest::get( 'post' );
		
		// calculate access
		$canedit = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$caneditown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent') && $file->uploaded_by == $user->get('id');
		$is_authorised = $canedit || $caneditown;
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager', '');
			return;
		}
		
		// Check In the file and redirect ...
		$file = JTable::getInstance('flexicontent_files', '');
		$file->bind(JRequest::get('post'));
		$file->checkin();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=filemanager' );
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_files';
		$redirect_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : $this->return_url;
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
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
		
		//require_once (JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'file.php');
		$user  = JFactory::getUser();
		$db    = JFactory::getDBO();
		$app   = JFactory::getApplication();
		
		// calculate access
		$canpublish = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$canpublishown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent');
		$is_authorised = $canpublish || $canpublishown;
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( $this->return_url, '');
			return;
		}
		
		$cid = JRequest::getVar( 'cid', array(), $hash='default', 'array' );
		JArrayHelper::toInteger($cid, array());
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( $state ? 'FLEXI_SELECT_ITEM_PUBLISH' : 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else {
			
			$db->setQuery( 'SELECT * FROM #__flexicontent_files WHERE id IN ('.implode(',', $cid).')' );
			$files = $db->loadObjectList('id');
			$cid = array_keys($files);
			
			$model = $this->getModel('filemanager');
			
			$msg = '';
			$allowed_files = array();
			$denied_files = array();
			foreach($cid as $_id) {
				if ( !isset($files[$_id]) ) continue;
				$filename = $files[$_id]->filename_original ? $files[$_id]->filename_original : $files[$_id]->filename;
				if ($canpublish || $files[$_id]->uploaded_by == $user->get('id'))
					$allowed_files[$_id] = $filename;
				else
					$denied_files[$_id] = $filename;
			}
			if ( count($denied_files) ) {
				$app->enqueueMessage(' You are not allowed to change state of files: '. implode(', ', $denied_files), 'warning');
			}
			$allowed_cid = array_keys($allowed_files);
			
			if (count($allowed_cid) && !$model->publish($allowed_cid, $state)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			if (count($allowed_cid)) $msg .= JText::_( $state ? 'FLEXI_PUBLISHED' : 'FLEXI_UNPUBLISHED' ) . ': '. implode(', ', $allowed_files);
			
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( $this->return_url, $msg);
	}
	
	
	/**
	 * Logic to set the access level of the Fields
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user  = JFactory::getUser();
		$model = $this->getModel('filemanager');
		$task  = JRequest::getVar( 'task' );
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		
		$file_id = (int)$cid[0];
		$row = JTable::getInstance('flexicontent_files', '');
		$row->load($file_id);
		
		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanFiles && ($perms->CanViewAllFiles || $user->id == $row->uploaded_by);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( $this->return_url, '');
			return;
		}
		
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$file_id];
		
		if(!$model->saveaccess( $file_id, $access )) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = '';
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect($this->return_url, $msg);
	}
	
	
	/* Restructure a FILES array for easier usage */
	function refactorFilesArray(&$f)
	{
		if ( empty($f['name']) || !is_array($f['name']) )  return $f; // nothing more to do
		
		$level0_keys = array_keys($f);
		$level1_keys = array_keys($f['name']);
		
		// Swap indexLevel_N with indexLeveL_N+1, until there are no more inner arrays
		foreach ($level0_keys  as  $i)  // level0_keys are: name, type, tmp_name, error, size
		{
			foreach ($level1_keys  as  $k1)  // level1_keys are: the indexes of ... file['name']
			{
				$r1[$k1][$i] = $f[$i][$k1];
				if ( !is_array($r1[$k1][$i]) ) continue;
				
				foreach(array_keys($r1[$k1][$i])  as  $k2)
				{
					$r2[$k1][$k2][$i] = $r1[$k1][$i][$k2];
					if ( !is_array($r2[$k1][$k2][$i]) ) continue;
					
					foreach(array_keys($r2[$k1][$k2][$i])  as  $k3)
					{
						$r3[$k1][$k2][$k3][$i] = $r2[$k1][$k2][$i][$k3];
					}
				}
			}
		}
		
		if (isset($r3))
			return $r3;
		else if (isset($r2))
			return $r2;
		else
			return $r1;
	}
}
