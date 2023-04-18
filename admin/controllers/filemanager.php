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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'file.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'filemanager.php';

/**
 * FLEXIcontent Files Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerFilemanager extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'flexicontent_files';
	var $records_jtable = 'flexicontent_files';

	var $record_name = 'file';
	var $record_name_pl = 'filemanager';

	var $_NAME = 'FILE';
	var $record_alias = 'filename';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		/**
		 * Register task aliases
		 */
		$this->registerTask('uploads', 	'upload');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		$this->option = $this->input->get('option', '', 'cmd');
		$this->task   = $this->input->get('task', '', 'cmd');
		$this->view   = $this->input->get('view', '', 'cmd');
		$this->format = $this->input->get('format', '', 'cmd');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFiles;
	}


	/**
	 * Logic to save a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$params  = JComponentHelper::getParams('com_flexicontent');

		$original_task = $this->task;

		// Retrieve form data these are subject to basic filtering
		$data  = $this->input->get('jform', array(), 'array');  // Unfiltered data, validation will follow via jform

		// Set into model: id (needed for loading correct item), and type id (e.g. needed for getting correct type parameters for new items)
		$data['id'] = $data ? (int) $data['id'] : $this->input->get('id', 0, 'int');
		$isnew = $data['id'] == 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel($this->record_name);

		// Make sure Primary Key is correctly set into the model ... (needed for loading correct item)
		$model->setId($data['id']);
		$record = $model->getItem();

		// The save2copy task needs to be handled slightly differently.
		if ($this->task === 'save2copy')
		{
			// Check-in the original row.
			if ($model->checkin($data['id']) === false)
			{
				// Check-in failed
				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				/**
				 * For errors, redirect back to referer, instead of the return URL, but skip redirection if inside a
				 * component-area-only view, showing error using current page, since usually we are inside a iframe modal
				 */
				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($this->refererURL);
				}

				if ($this->input->get('fc_doajax_submit'))
				{
					jexit(flexicontent_html::get_system_messages_html());
				}
				else
				{
					return false;
				}
			}

			// Reset the ID, the multilingual associations and then treat the request as for Apply.
			$isnew = 1;
			$data['id'] = 0;
			$data['associations'] = array();
			$this->task = 'apply';

			// Keep existing model data (only clear ID)
			$model->set('id', 0);
			$model->setProperty('_id', 0);
		}

		// The apply_ajax task is treat same as apply (also same redirection in case that AJAX submit is skipped)
		elseif ($this->task === 'apply_ajax')
		{
			$this->task = 'apply';
		}

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Validation with JForm
		$data = $this->input->post->getArray();  // Default filtering will remove HTML
		$data['description'] = flexicontent_html::dataFilter($data['description'], 32000, 'STRING', 0);  // Limit description to 32000 characters
		$data['hits'] = (int) $data['hits'];
		$data['secure'] = $data['secure'] ? 1 : 0;   // Only allow 1 or 0
		$data['stamp']  = $data['stamp'] ? 1 : 0;   // only allow 1 or 0
		$data['url']    = in_array((int) $data['url'], array(0, 1, 2)) ? (int) $data['url'] : 0;   // only allow 2 or 1 or 0

		// Get extensions allowed by configuration, and intersect them with desired extensions
		$allowed_exts = preg_split("/[\s]*,[\s]*/", strtolower($params->get('upload_extensions', 'bmp,wbmp,csv,doc,docx,webp,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,txt,xcf,xls,xlsx,zip,ics')));
		$allowed_exts = array_flip($allowed_exts);

		// Get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($data['filename']));

		if (!isset($allowed_exts[$ext]))
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('File extension not allowed'), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		switch ($data['url'])
		{
			// CASE local file
			case 0:
				$path = ($data['secure'] ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS;  // JPATH_ROOT . DS . <media_path | file_path> . DS
				$file_path = JPath::clean($path . $data['filename']);

				// Get file size from filesystem (local file)
				$data['size'] = file_exists($file_path) ? filesize($file_path) : 0;
				break;

			// CASE file URL
			case 1:

				// Validate file URL
				$data['filename_original'] = flexicontent_html::dataFilter($data['filename_original'], 4000, 'STRING', 0);  // Clean bad text/html
				$data['filename'] = $url = flexicontent_html::dataFilter($data['filename'], 4000, 'URL', 0);  // Clean bad text/html

				// Get file size from submitted field (file URL), set to zero if no size unit specified
				if (!empty($data['size']))
				{
					$arr_sizes = array('KBs' => 1024, 'MBs' => (1024 * 1024), 'GBs' => (1024 * 1024 * 1024));
					$size_unit = (int) @ $arr_sizes[$data['size_unit']];
					$data['size'] = ((int) $data['size']) * $size_unit;
				}

				else
				{
					$data['size'] = $model->get_file_size_from_url($url);

					if ($data['size'] === -999)
					{
						$app->enqueueMessage($url . ' -- ' . $model->getError(), 'warning');
					}

					$data['size'] = $data['size'] < 0 ? 0 : $data['size'];
				}
				break;

			// CASE JMedia file PATH
			case 2:

				// Validate file PATH
				$data['filename_original'] = flexicontent_html::dataFilter($data['filename_original'], 4000, 'STRING', 0);  // Clean bad text/html
				$data['filename'] = flexicontent_html::dataFilter($data['filename'], 4000, 'PATH', 0);  // Clean bad text/html

				$file_path = JPath::clean(JPATH_ROOT . DS . $data['filename']);

				// Get file size from filesystem (local file)
				$data['size'] = file_exists($file_path) ? filesize($file_path) : 0;
				break;
		}

		// Validate access level exists (set to public otherwise)
		$data['access'] = flexicontent_html::dataFilter($data['access'], 11, 'ACCESSLEVEL', 0);

		if (!$model->store($data))
		{
			// Set error message and the redirect URL (back to the record form)
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setError($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME));
			$this->setMessage($this->getError(), 'error');

			// For errors, skip redirection if in a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->refererURL);
			}

			// Try to check-in the record, but ignore any new errors
			try
			{
				!$isnew ? $model->checkin() : true;
			}
			catch (Exception $e)
			{
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Clear dependent cache data
		$this->_cleanCache();

		// Check in the record and get record id in case of new item
		$model->checkin();


		/**
		 * Saving is done, decide where to redirect
		 */

		$msg = JText::_('FLEXI_' . $this->_NAME . '_SAVED');

		switch ($this->task)
		{
			// REDIRECT CASE FOR APPLY / SAVE AS COPY: Save and reload the edit form
			case 'apply':
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name . '&id=' . (int) $model->get('id');
				break;

			// REDIRECT CASE FOR SAVE and NEW: Save and load new record form
			case 'save2new':
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name
					. '';
				break;

			// REDIRECT CASES FOR SAVING
			default:
				$link = $this->returnURL;
				break;
		}

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		// return;  // comment above and decomment this one to profile the saving operation

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		parent::checkin();
	}


	/**
	 * Cancel the edit, check in the record and return to the records manager
	 *
	 * @return bool
	 *
	 * @since 3.3
	 */
	public function cancel()
	{
		return parent::cancel();
	}


	/**
	 * Logic to publish records, this WRAPPER for changestate method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function publish()
	{
		$this->changestate(1);   // Security checks are done by the called method
	}


	/**
	 * * Logic to unpublish records, this WRAPPER for changestate method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function unpublish()
	{
		$this->changestate(0);   // Security checks are done by the called method
	}


	/**
	 * Upload files
	 *
	 * @since 1.0
	 */
	function upload($Fobj = null, & $exitMessages = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$db    = JFactory::getDbo();
		$session = JFactory::getSession();

		// Force interactive run mode, if given parameters
		$this->runMode = $Fobj ? 'interactive' : $this->runMode;
		$file_id = 0;

		// Force JSON format for 'uploads' task
		$this->format = $this->format != '' ? $this->format : ($this->task === 'uploads' ? 'json' : 'html');

		// Calculate access
		$canuploadfile = $user->authorise('flexicontent.uploadfiles', 'com_flexicontent');
		$is_authorised = $canuploadfile;

		// Check access
		if (!$is_authorised)
		{
			$this->exitHttpHead = array( 0 => array('status' => '403 Forbidden') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_YOUR_ACCOUNT_CANNOT_UPLOAD') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		$file_row_id = $data_from_sess = $uploader_file_data = false;

		if ($this->task === 'uploads')
		{
			$file = $this->input->files->get('file', '', 'array');
			$file_row_id = $this->input->get('file_row_id', '', 'string');

			$uploader_file_data = $session->get('uploader_file_data', array(), 'flexicontent');

			$data_from_sess = @ $uploader_file_data[$file_row_id];

			if ($data_from_sess)
			{
				$filetitle  = $data_from_sess['filetitle'];
				$filedesc   = $data_from_sess['filedesc'];
				$filelang   = $data_from_sess['filelang'];
				$fileaccess = $data_from_sess['fileaccess'];
				$secure     = $data_from_sess['secure'];
				$stamp      = $data_from_sess['stamp'];

				$fieldid    = $data_from_sess['fieldid'];
				$u_item_id  = $data_from_sess['u_item_id'];
				$file_mode  = $data_from_sess['file_mode'];
			}

			// Print_r($file_row_id); echo "\n"; print_r($uploader_file_data); exit();
		}
		else
		{
			// Default field <input type="file" is name="Filedata" ... get the file
			$ffname = $this->input->get('file-ffname', 'Filedata', 'cmd');
			$file   = $this->input->files->get($ffname, '', 'array');

			// Refactor the array swapping positions
			$file = $this->refactorFilesArray($file);

			// Get nested position, and reach the final file data array
			$fname_level1 = $this->input->get('fname_level1', null, 'string');
			$fname_level2 = $this->input->get('fname_level2', null, 'string');
			$fname_level3 = $this->input->get('fname_level3', null, 'string');

			if (strlen($fname_level1))
			{
				$file = $file[$fname_level1];
			}

			if (strlen($fname_level2))
			{
				$file = $file[$fname_level2];
			}

			if (strlen($fname_level3))
			{
				$file = $file[$fname_level3];
			}
		}

		if (empty($data_from_sess))
		{
			$secure  = $this->input->get('secure', 1, 'int');
			$secure  = $secure ? 1 : 0;

			$stamp = $this->input->get('stamp', 1, 'int');
			$stamp = $stamp ? 1 : 0;

			$filetitle  = $this->input->get('file-title', '', 'string');
			$filedesc   = flexicontent_html::dataFilter($this->input->get('file-desc', '', 'string'), 32000, 'STRING', 0);  // Limit number of characters
			$filelang   = $this->input->get('file-lang', '*', 'string');
			$fileaccess = $this->input->get('file-access', 1, 'int');
			$fileaccess = flexicontent_html::dataFilter($fileaccess, 11, 'ACCESSLEVEL', 0);  // Validate access level exists (set to public otherwise)

			$fieldid    = $this->input->get('fieldid', 0, 'int');
			$u_item_id  = $this->input->get('u_item_id', 0, 'cmd');
			$file_mode  = $this->input->get('folder_mode', 0, 'int') ? 'folder_mode' : 'db_mode';
		}

		$model = $this->getModel($this->record_name_pl);
		$field = false;

		if ($fieldid)
		{
			$field = $db->setQuery('SELECT * FROM #__flexicontent_fields WHERE id=' . $fieldid)->loadObject();
			$field->parameters = new JRegistry($field->attribs);
			$field->item_id = $u_item_id;
		}

		$default_dir = 2;

		if ($field)
		{
			if (in_array($field->field_type, array('file', 'image')))
			{
				$default_dir = 1;  // 'secure' folder
			}
			elseif (in_array($field->field_type, array('mediafile')))
			{
				$default_dir = 0;  // 'media' folder
			}

			$target_dir = $field->parameters->get('target_dir', $default_dir);

			// Force secure / media DB folder according to field configuration
			if (strlen($target_dir) && $target_dir != 2)
			{
				$secure = $target_dir ? 1 : 0;
			}
		}

		if ($field && $field->field_type == 'image' && $field->parameters->get('image_source') == 1)
		{
			$file_mode = 'folder_mode';
		}
		else
		{
			$file_mode = 'db_mode';
		}

		$estorage_mode = $field
			? $field->parameters->get('estorage_mode', '0')
			: '0';


		// *****************************************
		// Check that a file was provided / uploaded
		// *****************************************
		if (!isset($file['name']))
		{
			$this->exitHttpHead = array( 0 => array('status' => '400 Bad Request') );
			$this->exitMessages = array( 0 => array('error' => 'Filename has invalid characters (or other error occured)') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		// Chunking might be enabled
		$chunks = $this->input->get('chunks', 0, 'int');

		if ($chunks)
		{
			$chunk = $this->input->get('chunk', 0, 'int');

			// Get / Create target directory
			$targetDir = $app->getCfg('tmp_path') . DIRECTORY_SEPARATOR . "fc_fileselement";

			if (!file_exists($targetDir))
			{
				@ mkdir($targetDir);
			}

			// Create name of the unique temporary filename to use for concatenation of the chunks, or get the filename from session
			$fileName = $this->input->get('filename', '', 'string');

			$fileName_tmp = $app->getUserState($fileName, date('Y_m_d_') . uniqid());
			$app->setUserState($fileName, $fileName_tmp);
			$filePath_tmp = $targetDir . DIRECTORY_SEPARATOR . $fileName_tmp;

			// CREATE tmp file inside the Joomla temporary folder, but if this FAILS, then CREATE tmp file inside SERVER tmp directory
			if (!$out = @fopen("{$filePath_tmp}", "ab"))
			{
				$targetDir = (ini_get("upload_tmp_dir") ? ini_get("upload_tmp_dir") : sys_get_temp_dir()) . DIRECTORY_SEPARATOR . "fc_fileselement";

				if (!file_exists($targetDir))
				{
					@ mkdir($targetDir);
				}

				$filePath_tmp = $targetDir . DIRECTORY_SEPARATOR . $fileName_tmp;

				if (!$out = @fopen("{$filePath_tmp}", "ab"))
				{
					// Die("{'jsonrpc' : '2.0', 'error' : {'code': 102, 'message': 'Failed to open output stream: " . json_encode($filePath_tmp) . " fopen failed. reason: " . json_encode(implode(' ', error_get_last())) . "'}, 'data' : null}");
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream. Temporary path not writable."}, "data" : null}');
				}
			}

			if (!empty($_FILES))
			{
				if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"]))
				{
					die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "data" : null}');
				}

				if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb"))
				{
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "data" : null}');
				}
			}
			else
			{
				if (!$in = @fopen("php://input", "rb"))
				{
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "data" : null}');
				}
			}

			// Read binary input stream and append it to temp file
			while ($buff = fread($in, 4096))
			{
				fwrite($out, $buff);
			}

			@ fclose($out);
			@ fclose($in);

			// If not last chunk terminate further execution
			if ($chunk < $chunks - 1)
			{
				// Return Success JSON-RPC response
				die('{"jsonrpc" : "2.0", "result" : null, "data" : null}');
			}

			// Remove no longer needed file properties from session data
			elseif ($file_row_id && isset($uploader_file_data[$file_row_id]))
			{
				unset($uploader_file_data[$file_row_id]);
				$session->set('uploader_file_data', $uploader_file_data, 'flexicontent');
			}

			// Clear the temporary filename, from user state
			$app->setUserState($fileName, null);

			// Cleanup left-over files
			if (file_exists($targetDir))
			{
				foreach (new DirectoryIterator($targetDir) as $fileInfo)
				{
					if ($fileInfo->isDot())
					{
						continue;
					}

					if (time() - $fileInfo->getCTime() >= 60)
					{
						unlink($fileInfo->getRealPath());
					}
				}
			}

			// echo "-- chunk: $chunk \n-- chunks: $chunks \n-- targetDir: $targetDir \n--filePath_tmp: $filePath_tmp \n--fileName: $fileName";
			// echo "\n"; print_r($_REQUEST);
			$file['name'] = $fileName;
			$file['tmp_name'] = $filePath_tmp;
			$file['size'] = filesize($filePath_tmp);
			$file['error'] = 0;

			// echo "\n"; print_r($file);
		}

		if ($fieldid)
		{
			$_options = array('secure' => $secure);
			$path = $model->getFieldFolderPath($u_item_id, $fieldid, $_options);

			// Create field's folder if it does not exist already
			if (!is_dir($path))
			{
				mkdir($path, $mode = 0755, $recursive = true);
			}
		}
		else
		{
			$path = ($secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS;
		}

		jimport('joomla.utilities.date');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Make the filename safe
		jimport('joomla.filesystem.file');

		// Sanitize filename further and make unique
		$params = null;
		$err_text = null;
		$filesize = $file['size'];
		$filename_original = strip_tags($file['name']);  // Store original filename before sanitizing the filename
		$upload_check = flexicontent_upload::check($file, $err_text, $params);  // Check that file contents are safe, and also make the filename safe, transliterating it according to given language (this forces lowercase)
		$filename     = flexicontent_upload::sanitize($path, $file['name']);    // Sanitize the file name (filesystem-safe, (this should have been done above already)) and also return an unique filename for the given folder
		$filepath 	  = JPath::clean($path . $filename);

		// Check if uploaded file is valid
		if (!$upload_check)
		{
			$this->exitHttpHead = array( 0 => array('status' => '415 Unsupported Media Type') );
			$this->exitMessages = array( 0 => array('error' => $err_text) );
			$this->exitLogTexts = array( 0 => array(JLog::ERROR => 'Invalid: ' . $filepath . ': ' . JText::_($err_text)) );
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		// Get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($filename));

		// echo "\n". $file['tmp_name'] ." => ". $filepath ."\n";

		if ($chunks)
		{
			$move_success = copy($file['tmp_name'], $filepath);
			$move_success ? unlink($file['tmp_name']) : false;
		}
		else
		{
			$move_success = JFile::upload($file['tmp_name'], $filepath, false, false,
				// - Valid extensions are checked by our helper function
				// - also we allow all extensions and php inside content, FLEXIcontent will never execute "include" files evening when doing "in-browser viewing"
				array('null_byte' => true, 'forbidden_extensions' => array('_fake_ext_'), 'php_tag_in_content' => true, 'shorttag_in_content' => true, 'shorttag_extensions' => array(), 'fobidden_ext_in_content' => false, 'php_ext_content_extensions' => array() )
			);
		}

		// Check of upload failed
		if (!$move_success)
		{
			$this->exitHttpHead = array( 0 => array('status' => '409 Conflict') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_UNABLE_TO_UPLOAD_FILE') );
			$this->exitLogTexts = array( 0 => array(JLog::ERROR => JText::_('FLEXI_UNABLE_TO_UPLOAD_FILE') . ': ' . $filepath) );
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}



		// *****************
		// Upload Successful
		// *****************

		$fileObj = new stdClass;
		$fileObj->id = $file_id = 0;

		$fileObj->filename          = $filename;
		$fileObj->filename_original = $filename_original;
		$fileObj->altname           = $filetitle ? $filetitle : $filename_original;
		$fileObj->estorage_fieldid  = $estorage_mode === 'FTP' ? $fieldid : 0;

		$fileObj->url         = 0;
		$fileObj->secure      = $secure;
		$fileObj->stamp       = $stamp;
		$fileObj->ext         = $ext;

		$fileObj->description = $filedesc;
		$fileObj->language    = strlen($filelang) ? $filelang : '*';
		$fileObj->access      = strlen($fileaccess) ? $fileaccess : 1;

		$fileObj->hits        = 0;
		$fileObj->size        = $filesize;
		$fileObj->uploaded    = JFactory::getDate('now')->toSql();
		$fileObj->uploaded_by = $user->get('id');

		// A. Database mode
		if ($file_mode == 'db_mode')
		{
			// Insert file record in DB
			$db->insertObject('#__' . $this->records_dbtbl, $fileObj);

			// Get id of new file record
			$fileObj->id = $file_id = (int) $db->insertid();

			// Probe file to find if it is a supported media (audio or video) file
			$fileObj->full_path = $filepath;

			if ($field)
			{
				$res = $model->createMediaData($field, $fileObj);
				if (!$res)
				{
					$error_msg = JText::_("File uploaded successfully.\nBut got error reading media (audio/video) properties")
						. ":\n  " . $model->getError();
					$this->exitHttpHead = array( 0 => array('status' => '500 Error') );
					$this->exitMessages = array( 0 => array('warning' => $error_msg) );
					$this->exitLogTexts = array( 0 => array(JLog::WARNING => $error_msg) );
					$this->exitSuccess  = false;

					return $this->terminate($file_id, $exitMessages);
				}

				// Create audio preview file, if file is a media file
				if (!empty($fileObj->mediaData))
				{
					$res = $model->createAudioPreview($field, $fileObj);
					if (!$res)
					{
						$error_msg = JText::_("File uploaded successfully.\nBut got error during creating preview files")
							. ":\n  " . $model->getError();
						$this->exitHttpHead = array( 0 => array('status' => '500 Error') );
						$this->exitMessages = array( 0 => array('warning' => $error_msg) );
						$this->exitLogTexts = array( 0 => array(JLog::WARNING => $error_msg) );
						$this->exitSuccess  = false;

						return $this->terminate($file_id, $exitMessages);
					}
				}
			}
		}

		// B. Custom Folder mode
		else
		{
		}

		// Add information about uploaded file data into the session
		if ($this->input->get('history', 0, 'int'))
		{
			$upload_context = 'fc_upload_history.item_' . $u_item_id . '_field_' . $fieldid;

			$session_files = $session->get($upload_context, array());
			$session_files['ids'][] = $file_id;
			$session_files['names'][] = $filename;
			$session_files['ids_pending'][] = $file_id;
			$session_files['names_pending'][] = $filename;
			$session->set($upload_context, $session_files);
		}

		// Terminate with proper messaging
		$this->exitHttpHead = array( 0 => array('status' => '201 Created') );
		$this->exitMessages[] = array('message' => 'FLEXI_UPLOAD_COMPLETE');
		$this->exitLogTexts = array();
		$this->exitSuccess  = true;

		return $this->terminate($file_id, $exitMessages, $fileObj);
	}


	/**
	 * Upload a file by url
	 *
	 * @since 1.0
	 */
	function addurl($Fobj = null, & $exitMessages = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app     = JFactory::getApplication();
		$session = JFactory::getSession();
		$model = $this->getModel($this->record_name);

		// Force interactive run mode, if given parameters
		$this->runMode = $Fobj ? 'interactive' : $this->runMode;
		$file_id = 0;

		// 2: File Link (JMedia) , 1: URL Link
		$linktype = $this->input->get('file-link-type', 1, 'int');
		$linktype = $linktype === 2 ? 2 : 1;

		if ($linktype === 1)
		{
			$url = $this->input->get('file-url-data', null, 'string');
			$url = flexicontent_html::dataFilter($url, 4000, 'URL', 0);  // Validate file URL
		}
		else
		{
			$url = $this->input->get('file-jmedia-data', null, 'string');
			$url = flexicontent_html::dataFilter($url, 4000, 'PATH', 0);  // Validate JMedia file PATH
		}

		$altname  = $this->input->get('file-url-title', null, 'string');

		$filedesc   = flexicontent_html::dataFilter($this->input->get('file-url-desc', '', 'string'), 32000, 'STRING', 0);  // Limit number of characters
		$filelang   = $this->input->get('file-url-lang', '*', 'string');
		$fileaccess = $this->input->get('file-url-access', 1, 'int');
		$fileaccess = flexicontent_html::dataFilter($fileaccess, 11, 'ACCESSLEVEL', 0);  // Validate access level exists (set to public otherwise)

		$fieldid   = $this->input->get('fieldid', 0, 'int');
		$ext       = $this->input->get('file-url-ext', null, 'cmd');
		$filesize  = $this->input->get('file-url-size', 0, 'int');
		$size_unit = $this->input->get('size_unit', 'KBs', 'cmd');

		jimport('joomla.utilities.date');

		// Check if the form fields are not empty
		if (!$url || !$altname)
		{
			$this->exitHttpHead = array( 0 => array('status' => '400 Bad Request') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_WARNFILEURLFORM') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		if (empty($filesize))
		{
			if ($linktype === 1)
			{
				$filesize = $model->get_file_size_from_url($url);

				if ($filesize === -999)
				{
					$app->enqueueMessage($url . ' -- ' . $model->getError(), 'warning');
				}

				$filesize = $filesize < 0 ? 0 : $filesize;
			}
			else  // $linktype === 2
			{
				$filesize = filesize($url);
			}
		}

		else
		{
			$arr_sizes = array('KBs' => 1024, 'MBs' => (1024 * 1024), 'GBs' => (1024 * 1024 * 1024));
			$size_unit = (int) @ $arr_sizes[$size_unit];

			if ($size_unit)
			{
				$filesize = ((int) $filesize) * $size_unit;
			}
			else
			{
				$filesize = 0;
			}
		}

		// We verifiy the url prefix and add http if any
		if ($linktype === 1 && !preg_match("#^http|^https|^ftp#i", $url))
		{
			$url	= 'http://' . $url;
		}

		$db 	= JFactory::getDbo();
		$user	= JFactory::getUser();
		$field  = false;

		if ($fieldid)
		{
			$field = $db->setQuery('SELECT * FROM #__flexicontent_fields WHERE id=' . $fieldid)->loadObject();
			$field->parameters = new JRegistry($field->attribs);
			$field->item_id = $u_item_id;
		}

		$default_dir = 2;
		$secure      = 1;

		if ($field)
		{
			if (in_array($field->field_type, array('file', 'image')))
			{
				$default_dir = 1;  // 'secure' folder
			}
			elseif (in_array($field->field_type, array('mediafile')))
			{
				$default_dir = 0;  // 'media' folder
			}

			$target_dir = $field->parameters->get('target_dir', $default_dir);

			// Force secure / media DB folder according to field configuration
			if (strlen($target_dir) && $target_dir != 2)
			{
				$secure = $target_dir ? 1 : 0;
			}
		}

		$fileObj = new stdClass;
		$fileObj->filename    = $url;
		$fileObj->filename_original = $url;
		$fileObj->altname     = $altname;

		$fileObj->url         = $linktype;
		$fileObj->secure      = $secure;
		$fileObj->stamp       = 0;
		$fileObj->ext         = $ext;

		$fileObj->description = $filedesc;
		$fileObj->language    = strlen($filelang) ? $filelang : '*';
		$fileObj->access      = strlen($fileaccess) ? $fileaccess : 1;

		$fileObj->hits        = 0;
		$fileObj->size        = $filesize;
		$fileObj->uploaded    = JFactory::getDate('now')->toSql();
		$fileObj->uploaded_by = $user->get('id');

		$db->insertObject('#__' . $this->records_dbtbl, $fileObj);

		// Get id of new file record
		$file_id = (int) $db->insertid();

		// Add information about added (URL) file data into the session
		if ($this->input->get('history', 0, 'int'))
		{
			$upload_context = 'fc_upload_history.item_' . $u_item_id . '_field_' . $fieldid;

			$session_files = $session->get($upload_context, array());
			$session_files['ids'][] = $file_id;
			$session_files['names'][] = $url;
			$session_files['ids_pending'][] = $file_id;
			$session_files['names_pending'][] = $url;
			$session->set($upload_context, $session_files);
		}

		// Terminate with proper messaging
		$this->exitHttpHead = array( 0 => array('status' => '201 Created') );
		$this->exitMessages = array( 0 => array('message' => 'FLEXI_FILE_ADD_SUCCESS') );
		$this->exitLogTexts = array();
		$this->exitSuccess  = true;

		return $this->terminate($file_id, $exitMessages);
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$db     = JFactory::getDbo();

		$fieldid    = $this->input->get('fieldid', 0, 'int');
		$u_item_id  = $this->input->get('u_item_id', 0, 'cmd');
		$file_mode  = $this->input->get('folder_mode', 0, 'int') ? 'folder_mode' : 'db_mode';

		// Check for zero selected records
		$cid = $this->input->get('cid', array(), 'array');

		if ($file_mode != 'folder_mode')
		{
			// These are file ids, for DB-mode
			$cid = ArrayHelper::toInteger($cid);
		}

		if (!is_array($cid) || count($cid) < 1)
		{
			$this->exitHttpHead = array( 0 => array('status' => '400 Bad Request') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_SELECT_ITEM_DELETE') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		// Different handling for folder_mode
		if ($file_mode == 'folder_mode')
		{
			$field = $db->setQuery('SELECT * FROM #__flexicontent_fields WHERE id=' . $fieldid)->loadObject();
			$field->parameters = new JRegistry($field->attribs);
			$field->item_id = $u_item_id;

			$failed_files = array();

			foreach ($cid as $filename)
			{
				$filename = rawurldecode($filename);

				// Default 'CMD' filtering is maybe too aggressive, but allowing UTF8 will not work in all filesystems, so we do not allow
				// $filename_original = iconv(mb_detect_encoding($filename, mb_detect_order(), true), "UTF-8", $filename);

				if (!FLEXIUtilities::call_FC_Field_Func($field->field_type, 'removeOriginalFile', array(&$field, $filename)))
				{
					$failed_files[] = $filename;
				}
			}

			$failed_msg = !count($failed_files) ? '' : JText::_('FLEXI_UNABLE_TO_CLEANUP_ORIGINAL_FILE') . ': ' . implode(', ', $failed_files);
			$delete_count = count($cid) - count($failed_files);

			if ($delete_count)
			{
				$this->exitHttpHead = array( 0 => array('status' => '200 OK') );
				$this->exitMessages = array( 0 => array('message' => $delete_count . ' ' . JText::_('FLEXI_FILES_DELETED')) );

				if (count($failed_files))
				{
					$app->enqueueMessage($failed_msg, 'warning');
				}
			}
			else
			{
				$this->exitHttpHead = array( 0 => array('status' => '500 Internal Server Error') );
				$this->exitMessages = array( 0 => array('error' => $failed_msg) );
			}

			$this->exitLogTexts = array();
			$this->exitSuccess  = count($failed_files) == 0;

			return $this->terminate($file_id, $exitMessages);
		}

		// Calculate access
		/**
		 * Because we do not have ACL for individual files, we will abort here if both of 'flexicontent.deletefile' or 'flexicontent.deleteownfile' are not granted at component level
		 *
		 * Note later we check: 'flexicontent.deleteownfile' or (ownership + 'flexicontent.deleteownfile') by using $model->getDeletable($cid)
		 */
		$candelete     = $user->authorise('flexicontent.deletefile', 'com_flexicontent');
		$candeleteown  = $user->authorise('flexicontent.deleteownfile', 'com_flexicontent');
		$is_authorised = $candelete || $candeleteown;

		// Check access
		if (!$is_authorised)
		{
			$this->exitHttpHead = array( 0 => array('status' => '403 Forbidden') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_ALERTNOTAUTH_TASK') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		$msg = '';

		$db->setQuery('SELECT * FROM #__' . $this->records_dbtbl . ' WHERE id IN (' . implode(',', $cid) . ')');
		$files = $db->loadObjectList('id');
		$cid = array_keys($files);

		$model = $this->getModel($this->record_name_pl);
		$deletable = $model->getDeletable($cid);

		// Find files that are currently in use
		if (count($cid) != count($deletable))
		{
			$_del = array_flip($deletable);
			$inuse_files = array();

			foreach ($files as $_id => $file)
			{
				if (isset($_del[$_id]))
				{
					continue;
				}

				$inuse_files[] = $file->filename_original ? $file->filename_original : $file->filename;
			}

			$app->enqueueMessage(JText::_('FLEXI_CANNOT_REMOVE_FILES_IN_USE') . ': ' . implode(', ', $inuse_files), 'warning');
			$cid = $deletable;
		}

		// Find files allowed to be deleted
		$allowed_files = array();
		$denied_files = array();

		foreach ($cid as $_id)
		{
			if (!isset($files[$_id]))
			{
				continue;
			}

			$filename = $files[$_id]->filename_original
				? $files[$_id]->filename_original
				: $files[$_id]->filename;

			// Note: component 'deleteownfile' was checked above
			if ($candelete || ($user->get('id') && $files[$_id]->uploaded_by == $user->get('id')))
			{
				$allowed_files[$_id] = $filename;
			}
			else
			{
				$denied_files[$_id] = $filename;
			}
		}

		if (count($denied_files))
		{
			$app->enqueueMessage(' You are not allowed to delete files: ' . implode(', ', $denied_files), 'warning');
		}

		$allowed_cid = array_keys($allowed_files);

		// Check for error during delete operation
		if (count($allowed_cid) && !$model->delete($allowed_cid))
		{
			$this->exitHttpHead = array( 0 => array('status' => '500 Internal Server Error') );
			$this->exitMessages = array( 0 => array('error' => JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError()) );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_id, $exitMessages);
		}

		if (count($allowed_cid))
		{
			$msg .= count($allowed_cid) . ' ' . JText::_('FLEXI_FILES_DELETED');
		}

		// Clear cache and return
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect($this->returnURL, $msg);
	}



	/**
	 * Logic to modify the state of records, other state modifications tasks are wrappers to this task
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function changestate($state = 1)
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$db    = JFactory::getDbo();

		// Calculate access
		/**
		 * Because we do not have ACL for individual files, we will abort here if both of 'flexicontent.publishfile' or 'flexicontent.publishownfile' are not granted at component level
		 *
		 * Note: later we will check:  $canpublish || ($user->get('id') && $files[$_id]->uploaded_by == $user->get('id'))
		 */
		$canpublish = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$canpublishown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent');
		$is_authorised = $canpublish || $canpublishown;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			return;
		}

		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		if (!is_array($cid) || count($cid) < 1)
		{
			$app->setHeader('status', '400 Bad Request', true);
			$this->setRedirect($this->returnURL, JText::_($state ? 'FLEXI_SELECT_ITEM_PUBLISH' : 'FLEXI_SELECT_ITEM_UNPUBLISH'), 'error');

			return;
		}

		$db->setQuery('SELECT * FROM #__flexicontent_files WHERE id IN (' . implode(',', $cid) . ')');
		$files = $db->loadObjectList('id');
		$cid = array_keys($files);

		$model = $this->getModel($this->record_name_pl);

		$msg = '';
		$allowed_files = array();
		$denied_files = array();

		foreach ($cid as $_id)
		{
			if (!isset($files[$_id]))
			{
				continue;
			}

			$filename = $files[$_id]->filename_original
				? $files[$_id]->filename_original
				: $files[$_id]->filename;

			if ($canpublish || ($user->get('id') && $files[$_id]->uploaded_by == $user->get('id')))
			{
				$allowed_files[$_id] = $filename;
			}
			else
			{
				$denied_files[$_id] = $filename;
			}
		}

		if (count($denied_files))
		{
			$app->enqueueMessage(' You are not allowed to change state of files: ' . implode(', ', $denied_files), 'warning');
		}

		$allowed_cid = array_keys($allowed_files);

		if (count($allowed_cid) && !$model->publish($allowed_cid, $state))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			return;
		}

		if (count($allowed_cid))
		{
			$msg .= JText::_($state ? 'FLEXI_PUBLISHED' : 'FLEXI_UNPUBLISHED') . ': ' . implode(', ', $allowed_files);
		}

		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect($this->returnURL, $msg);
	}


	/**
	 * Upload a file from a server directory
	 *
	 * @since 1.0
	 */
	function addlocal($Fobj = null, & $exitMessages = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		static $imported_files = array();
		$file_ids = array();

		$app    = JFactory::getApplication();
		$db 		= JFactory::getDbo();
		$user		= JFactory::getUser();
		$params = JComponentHelper::getParams('com_flexicontent');

		$is_importcsv = $this->task === 'importcsv';

		// Get file properties
		$secure  = $Fobj ? $Fobj->secure : $this->input->get('secure', 1, 'int');
		$secure  = $secure ? 1 : 0;

		$stamp  = $Fobj ? $Fobj->stamp : $this->input->get('stamp', 1, 'int');
		$stamp  = $stamp ? 1 : 0;

		$filedesc   = flexicontent_html::dataFilter($this->input->get('file-desc', '', 'string'), 32000, 'STRING', 0);  // Limit number of characters
		$filelang   = $this->input->get('file-lang', '*', 'string');
		$fileaccess = $this->input->get('file-access', 1, 'int');
		$fileaccess = flexicontent_html::dataFilter($fileaccess, 11, 'ACCESSLEVEL', 0);  // Validate access level exists (set to public otherwise)

		// Get folder path and filename regexp
		$filesdir = $Fobj ? $Fobj->file_dir_path : $this->input->get('file-dir-path', '', 'string');
		$regexp   = $Fobj ? $Fobj->file_filter_re : $this->input->get('file-filter-re', '.', 'string');

		// Delete after adding flag
		$keep = $Fobj ? $Fobj->keep : $this->input->get('keep', 1, 'int');

		// Get desired extensions from request
		$filter_ext = $this->input->get('file-filter-ext', '', 'string');
		$filter_ext	= $filter_ext ? explode(',', $filter_ext) : array();

		foreach ($filter_ext as $_i => $_ext)
		{
			$filter_ext[$_i] = strtolower($_ext);
		}

		// Get extensions allowed by configuration, and intersect them with desired extensions
		$allowed_exts = preg_split("/[\s]*,[\s]*/", strtolower($params->get('upload_extensions', 'bmp,wbmp,csv,doc,docx,webp,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,txt,xcf,xls,xlsx,zip,ics')));
		$allowed_exts = $filter_ext ? array_intersect($filter_ext, $allowed_exts) : $allowed_exts;
		$allowed_exts = array_flip($allowed_exts);

		jimport('joomla.utilities.date');
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Get files
		$filesdir = JPath::clean(JPATH_SITE . $filesdir . DS);
		$filenames = JFolder::files($filesdir, $regexp);

		// Create the folder if it does not exists
		$destpath = ($secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS;

		if (!JFolder::exists($destpath))
		{
			if (!JFolder::create($destpath))
			{
				$this->exitHttpHead = array( 0 => array('status' => '500 Internal Server Error') );
				$this->exitMessages = array( 0 => array('error' => 'Error. Unable to create folders') );
				$this->exitLogTexts = array();
				$this->exitSuccess  = false;

				return $this->terminate($file_ids, $exitMessages);
			}
		}

		// Check if the form fields are not empty
		if (!$filesdir)
		{
			$this->exitHttpHead = array( 0 => array('status' => '400 Bad Request') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_WARN_NO_FILE_DIR') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_ids, $exitMessages);
		}

		$added = array();
		$excluded = array();

		if ($filenames)
		{
			for ($n = 0; $n < count($filenames); $n++)
			{
				$ext = strtolower(flexicontent_upload::getExt($filenames[$n]));

				if (!isset($allowed_exts[$ext]))
				{
					$excluded[] = $filenames[$n];
					continue;
				}

				$source 		= $filesdir . $filenames[$n];
				$filename		= flexicontent_upload::sanitize($destpath, $filenames[$n]);
				$destination 	= $destpath . $filename;

				// Check for file already added by import task and do not re-add the file
				if ($is_importcsv && isset($imported_files[$source]))
				{
					$file_ids[$filename] = $imported_files[$source];
					continue;
				}

				// Copy or move the file
				$success = $keep ? JFile::copy($source, $destination) : JFile::move($source, $destination);

				if ($success)
				{
					$filesize = filesize($destination);

					$fileObj = new stdClass;
					$fileObj->filename    = $filename;
					$fileObj->altname     = $filename;

					$fileObj->url         = 0;
					$fileObj->secure      = $secure;
					$fileObj->stamp       = $stamp;
					$fileObj->ext         = $ext;

					$fileObj->description = $filedesc;
					$fileObj->language    = strlen($filelang) ? $filelang : '*';
					$fileObj->access      = strlen($fileaccess) ? $fileaccess : 1;

					$fileObj->hits        = 0;
					$fileObj->size        = $filesize;
					$fileObj->uploaded    = JFactory::getDate('now')->toSql();
					$fileObj->uploaded_by = $user->get('id');

					// Add the record to the DB
					$db->insertObject('#__' . $this->records_dbtbl, $fileObj);
					$file_ids[$filename] = $db->insertid();

					// Add file ID to files imported by import task
					if ($is_importcsv)
					{
						$imported_files[$source] = $file_ids[$filename];
					}

					$added[] = $filenames[$n];
				}
			}

			if (count($added))
			{
				$app->enqueueMessage(JText::sprintf('FLEXI_FILES_COPIED_SUCCESS', count($added)), 'message');
			}

			if (count($excluded))
			{
				$app->enqueueMessage(JText::sprintf('FLEXI_FILES_EXCLUDED_WARNING', count($excluded)) . ' : ' . implode(', ', $excluded), 'warning');
			}
		}

		else
		{
			$this->exitHttpHead = array( 0 => array('status' => '400 Bad Request') );
			$this->exitMessages = array( 0 => array('error' => 'FLEXI_WARN_NO_FILES_IN_DIR') );
			$this->exitLogTexts = array();
			$this->exitSuccess  = false;

			return $this->terminate($file_ids, $exitMessages);
		}

		// Terminate with proper messaging
		$this->exitHttpHead = array( 0 => array('status' => '201 Created') );
		$this->exitMessages = array( 0 => array('message' => 'FLEXI_FILE_ADD_SUCCESS') );
		$this->exitLogTexts = array();
		$this->exitSuccess  = true;

		return $this->terminate($file_ids, $exitMessages);
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function edit()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Try to load file by attributes in HTTP Request
		if (0)
		{
			$record = $model->getRecord(array(
				'filename' => '',
			));
		}

		// Try to load by unique ID or NAME
		else
		{
			$record = $model->getItem();
		}

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Checkout the record and proceed to edit form
		if (!$model->checkout())
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
	}


	/**
	 * Logic to set the access level of the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function access()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Get model
		$model = $this->getModel($this->record_name_pl);

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		$file_id = (int) reset($cid);
		$row = JTable::getInstance('flexicontent_files', '');
		$row->load($file_id);

		// Calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanFiles && ($perms->CanViewAllFiles || $user->id == $row->uploaded_by);

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', '403 Forbidden', true);
			$this->setRedirect($this->returnURL);

			return;
		}

		// Get new record access
		$accesses = $this->input->get('access', array(), 'array');
		$accesses = ArrayHelper::toInteger($accesses);
		$access = $accesses[$file_id];

		if (!$model->saveaccess($file_id, $access))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			return;
		}

		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect($this->returnURL);
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */

	/**
	 * CONTROLLER specific Helper Methods (non-task methods)
	 */
	 

	/*
	 * Restructure a FILES array for easier usage
	 */
	function refactorFilesArray(&$f)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		if (empty($f['name']) || !is_array($f['name']))
		{
			return $f; // Nothing more to do
		}

		$level0_keys = array_keys($f);
		$level1_keys = array_keys($f['name']);

		// Swap indexLevel_N with indexLeveL_N+1, until there are no more inner arrays
		foreach ($level0_keys  as  $i)  // level0_keys are: name, type, tmp_name, error, size
		{
			foreach ($level1_keys  as  $k1)  // Level1_keys are: the indexes of ... file['name']
			{
				$r1[$k1][$i] = $f[$i][$k1];

				if (!is_array($r1[$k1][$i]))
				{
					continue;
				}

				foreach (array_keys($r1[$k1][$i])  as  $k2)
				{
					$r2[$k1][$k2][$i] = $r1[$k1][$i][$k2];

					if (!is_array($r2[$k1][$k2][$i]))
					{
						continue;
					}

					foreach (array_keys($r2[$k1][$k2][$i])  as  $k3)
					{
						$r3[$k1][$k2][$k3][$i] = $r2[$k1][$k2][$i][$k3];
					}
				}
			}
		}

		if (isset($r3))
		{
			return $r3;
		}
		elseif (isset($r2))
		{
			return $r2;
		}
		else
		{
			return $r1;
		}
	}


	/**
	 * Set credentials for using FTP layer for file handling
	 */
	function ftpValidate()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
	}
}
