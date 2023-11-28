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
 * FLEXIcontent Files Controller (RAW)
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

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFiles;
	}


	function saveprops()
	{
		// Set tree data into session
		$session = JFactory::getSession();
		$file_row_id = $this->input->get('file_row_id', '', 'string');
		$uploader_file_data = $session->get('uploader_file_data', array(), 'flexicontent');
		$props = array();

		$props['filetitle']  = $this->input->get('file-props-title', '', 'string');
		$props['filedesc']   = flexicontent_html::dataFilter($this->input->get('file-props-desc', '', 'string'), 32000, 'STRING', 0);  // Limit number of characters
		$props['filelang']   = $this->input->get('file-props-lang', '*', 'string');
		$props['fileaccess'] = flexicontent_html::dataFilter($this->input->get('file-props-access', 1, 'int'), 11, 'ACCESSLEVEL', 0);  // Validate access level exists (set to public otherwise)
		$props['secure']     = $this->input->get('secure', 1, 'int') ? 1 : 0;

		$props['fieldid']    = $this->input->get('fieldid', 0, 'int');
		$props['u_item_id']  = $this->input->get('u_item_id', 0, 'cmd');
		$props['file_mode']  = $this->input->get('folder_mode', 0, 'int') ? 'folder_mode' : 'db_mode';

		$uploader_file_data[$file_row_id] = & $props;
		$session->set('uploader_file_data', $uploader_file_data, 'flexicontent');

		// $app = JFactory::getApplication();
		// $app->enqueueMessage('<pre>'.print_r($props, true).'</pre>', 'message');

		// Return Success JSON-RPC response
		die('{"jsonrpc" : "2.0", "result" : "<div class=\"fc-mssg fc-success fc-iblock fc-left\">' . JText::_('FLEXI_APPLIED') . '</div>", "row_id" : ' . json_encode($file_row_id) . ', "sys_messages" : ' . json_encode(flexicontent_html::get_system_messages_html()) . '}');
	}


	/**
	 * count the rows
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function countrows()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		JSession::checkToken('request') or jexit('fail | ' . JText::_('JINVALID_TOKEN'));

		if (!FlexicontentHelperPerm::getPerm()->CanConfig)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test counting with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$has_zlib    = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');
		$indexer     = $this->input->getCmd('indexer', 'filemanager_stats');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');
		$index_urls  = $this->input->getInt('index_urls', 0);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		// Check indexer type
		if ($indexer !== 'filemanager_stats')
		{
			jexit('fail | indexer: ' . $indexer . ' not supported');
		}

		// Clear previous log file
		$log_filename      = 'filemanager_stats_indexer_' . \JFactory::getUser()->id . '.php';
		$log_filename_full = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename);

		if (file_exists($log_filename_full))
		{
			@ unlink($log_filename_full);
		}

		// Clear previous mediadata log file
		if (file_exists(str_replace('filemanager', 'mediadata', $log_filename_full)))
		{
			@ unlink(str_replace('filemanager', 'mediadata', $log_filename_full));
		}

		// Get records model to call needed methods
		$records_model = $this->getModel($this->record_name_pl);

		// Get ids of records to process
		$records_total = 0;
		$record_ids = $records_model->getFileIds($skip_urls = false,
			$records_total, 0, self::$record_limit
		);

		// Set record ids into session to avoid recalculation ...
		$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
		$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

		echo 'success';
		echo '|' . $records_total;
		echo '|' . count(array());

		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$_total_runtime = $elapsed_microseconds;
		$_total_queries = 0;
		echo sprintf('|0| Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries;

		$session->set($indexer . '_total_runtime', $elapsed_microseconds, 'flexicontent');
		$session->set($indexer . '_total_queries', 0, 'flexicontent');
		$session->set($indexer . '_records_total', $records_total, 'flexicontent');
		$session->set($indexer . '_records_start', 0, 'flexicontent');
		$session->set($indexer . '_log_filename', $log_filename, 'flexicontent');
		jexit();
	}


	function index()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		// JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		// Not need because this task need the user session data that are set by countrows that checked for forgeries

		if (!FlexicontentHelperPerm::getPerm()->SuperAdmin)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test indexing with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		$has_zlib      = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');

		$indexer     = $this->input->getCmd('indexer', 'filemanager_stats');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');
		$index_urls  = $this->input->getInt('index_urls', 0);

		$records_per_call = $this->input->getInt('records_per_call', 100);  // Number of item to index per HTTP request
		$records_cnt      = $this->input->getInt('records_cnt', 0);        // Counter of items indexed so far, this is given via HTTP request

		$log_filename  = $session->get($indexer . '_log_filename', null, 'flexicontent');
		$log_namespace = 'com_flexicontent.filemanager.stats_indexer';
		$loggers       = $this->_setUpLoggers($log_filename, $log_namespace);

		// Get record ids to process, but use session to avoid recalculation
		$record_ids = $session->get($indexer . '_records_to_index', array(), 'flexicontent');
		$record_ids = unserialize($has_zlib ? zlib_decode(base64_decode($record_ids)) : base64_decode($record_ids));

		// Get total and current limit-start
		$records_total = $session->get($indexer . '_records_total', 0, 'flexicontent');
		$records_start = $session->get($indexer . '_records_start', 0, 'flexicontent');


		// Get query size limit
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256 * 1024;
		$query_lim          = (int) (3 * $max_allowed_packet / 4);

		// jexit('fail | ' . $query_lim);

		// Get script max
		$max_execution_time = ini_get("max_execution_time");

		// jexit('fail | ' . $max_execution_time);

		// Get models
		$model        = $this->getModel($this->record_name_pl);
		$record_model = $this->getModel($this->record_name);
		
		// Get mediafile fields
		$media_fields = FlexicontentFields::getFieldsByType(array('mediafile'));
		$_item = null;

		foreach($media_fields as $media_field)
		{
			FlexicontentFields::loadFieldConfig($media_field, $_item);
		}

		// Find usage in fields
		$s_assigned_fields = array('file', 'mediafile');
		$m_assigned_fields = array('image');
		$m_assigned_props  = array('image' => array('originalname', 'existingname'));
		$m_assigned_vals   = array('image' => array('filename', 'filename'));

		// A lower value for this will allow safe checking if were getting near the (max_execution_time / 3) limit
		$max_items_per_query  = count($media_fields) ? 5 : 30;
		$max_items_per_query  = $max_items_per_query > $records_per_call ? $records_per_call : $max_items_per_query;
		$query_count          = 0;
		$mediadata_err_count  = 0;
		$mediadata_file_count = 0;

		// Start item counter at given index position
		$cnt = $records_cnt;

		// Until all records of current sub-set finish or until maximum records per AJAX call are reached
		while ($cnt < $records_start + count($record_ids)  &&  $cnt < $records_cnt + $records_per_call)
		{
			// Get maximum items per SQL query
			$query_itemids = array_slice($record_ids, ($cnt - $records_start), $max_items_per_query);

			if (empty($query_itemids))
			{
				jexit('fail | current step has no items');
			}

			// Increment item counter, but detect passing past current sub-set limit
			$cnt += $max_items_per_query;

			if ($cnt > $records_start + self::$record_limit)
			{
				$cnt = $records_start + self::$record_limit;
			}

			// Get record data
			$data_query = "SELECT * "
				. " FROM #__flexicontent_files"
				. " WHERE id IN (" . implode(', ', $query_itemids) . ")";
			$db->setQuery($data_query);
			$record_data = $db->loadObjectList('id');

			// Check if current set of files are assigned to any mediafields
			$media_fields_assignments = array();

			foreach($media_fields as $media_field)
			{
				$media_fields_assignments[$media_field->id] = $model->areAssignedToField($query_itemids, $media_field->id);
			}

			$size_index  = array();
			$usage_index = array();
			$errors = array();

			/**
			 * Loop through records
			 * Find out sizes of every file or url
			 */
			foreach ($record_data as $file_id => $file)
			{
				$file->total_usage = 0;

				$_ext  = strtolower(flexicontent_upload::getExt($file->filename));
				$_name = str_ireplace('.' . $_ext, '', basename($file->filename));

				// Local files: 0: FC file management Folders or 1: Joomla Media Folder
				if ($file->url == 0 || $file->url == 2)
				{
					$path = $file->url == 0
						? ($file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH)  // JPATH_ROOT . DS . <media_path | file_path>
						: JPATH_ROOT;

					$full_path     = $path . DS . $file->filename;
					$full_path_prw = $path . DS . 'audio_preview' . DS . $_name . '.mp3';
					$file->size    = file_exists($full_path) ? filesize($full_path) : 0;

					if (!file_exists($full_path))
					{
						$errors[] = $file->filename . ($file->url == 2 ? ' -- NOT FOUND (Joomla media file)' : '');
						continue;
					}
				}

				// File URLs
				elseif ($index_urls)
				{
					$full_path     = $file->filename;   // URL
					$full_path_prw = ($file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS . 'audio_preview' . DS . $_name . '.mp3';

					if ($file->filename)
					{
						$filesize = $record_model->get_file_size_from_url($file->filename);

						if ($filesize === -999)
						{
							$errors[] = $file->filename . ' -- ' . $record_model->getError();
						}

						$file->size = $filesize < 0 ? 0 : $filesize;
					}
				}

				$size_index[] = ' WHEN ' . $file->id . ' THEN ' . $file->size;

				// Recalculate JSON peaks files
				if ($file->url != 1 || file_exists($full_path_prw))
				{
					foreach($media_fields as $media_field)
					{
						if (isset($media_fields_assignments[$media_field->id][$file_id]))
						{
							// Try to get mediadata of the file from the DB
							$file->mediaData = $model->getDbMediaData($file);

							// Create audio preview file, if file is a media file
							if (!empty($file->mediaData))
							{
								$file->full_path = $full_path;
								$file->full_path_prw = file_exists($full_path_prw) ? $full_path_prw : false;

								if (!$model->createAudioPreview($media_field, $file, $loggers['mediadata']))
								{
									$mediadata_err_count++; 
								}
								$mediadata_file_count++;
							}
						}
					}
				}
			}

			// Increment error count in session, and log errors into the log file
			if (count($errors))
			{
				$error_count = $session->get('filemanager.stats_indexer.error_count', 0, 'flexicontent');
				$session->set('filemanager.stats_indexer.error_count', $error_count + count($errors), 'flexicontent');

				foreach ($errors as $error_message)
				{
					JLog::add($error_message, JLog::WARNING, $log_namespace);
				}
			}

			// Increment error count in session, and log errors into the log file
			if ($mediadata_err_count)
			{
				$error_count = $session->get('mediadata.stats_indexer.error_count', 0, 'flexicontent');
				$session->set('mediadata.stats_indexer.error_count', $error_count + $mediadata_err_count, 'flexicontent');
			}
			if ($mediadata_file_count)
			{
				$file_count = $session->get('mediadata.stats_indexer.file_count', 0, 'flexicontent');
				$session->set('mediadata.stats_indexer.file_count', $file_count + $mediadata_file_count, 'flexicontent');
			}
			if ($mediadata_err_count || $mediadata_file_count)
			{
				$session->set('mediadata_stats_log_filename', 'mediadata_stats_indexer_' . \JFactory::getUser()->id . '.php', 'flexicontent');
			}


			/**
			 * Single property fields, get file usage (# assignments)
			 */
			if ($s_assigned_fields)
			{
				foreach ($s_assigned_fields as $field_type)
				{
					$model->countFieldRelationsSingleProp($record_data, $field_type);
				}
			}


			/**
			 * Multi property fields, get file usage (# assignments)
			 */
			if ($m_assigned_fields)
			{
				foreach ($m_assigned_fields as $field_type)
				{
					$field_prop = $m_assigned_props[$field_type];
					$value_prop = $m_assigned_vals[$field_type];
					$model->countFieldRelationsMultiProp($record_data, $value_prop, $field_prop, $field_type);
				}
			}


			/**
			 * Files in download links created via the XTD-editor file button, get file usage (# of download links)
			 */
			$model->countUsage_FcFileBtn_DownloadLinks($record_data);


			// Also create assignments counter
			foreach ($record_data as $file_id => $file)
			{
				$usage_index[] = ' WHEN ' . $file->id . ' THEN ' . $file->total_usage;
			}

			// Create query that will update/insert data into the DB
			$queries = array();

			if ($indexer === 'filemanager_stats')
			{
				$queries[] = 'UPDATE #__flexicontent_files '
					. ' SET '
					. ' ' . $db->quoteName('size') . ' = CASE id ' . implode('', $size_index) . '  END, '
					. ' ' . $db->quoteName('assignments') . ' = CASE id ' . implode('', $usage_index) . '  END '
					. ' WHERE id IN (' . implode(', ', $query_itemids) . ')';
			}

			foreach ($queries as $query)
			{
				try
				{
					$db->setQuery($query)->execute();
				}
				catch (RuntimeException $e)
				{
					jexit('fail | ' . $e->getMessage());
				}
			}

			$query_count += count($queries);

			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$elapsed_seconds = $elapsed_microseconds / 1000000.0;

			if ($elapsed_seconds > $max_execution_time / 3 || $elapsed_seconds > 6)
			{
				break;
			}
		}

		// Terminate if not processing any records
		if (!$records_total)
		{
			jexit(
				'fail | No files found'
			);
		}

		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		if ($session->has($indexer . '_total_runtime', 'flexicontent'))
		{
			$_total_runtime = $session->get($indexer . '_total_runtime', 0, 'flexicontent');
		}
		else
		{
			$_total_runtime = 0;
		}

		$_total_runtime += $elapsed_microseconds;
		$session->set($indexer . '_total_runtime', $_total_runtime, 'flexicontent');

		if ($session->has($indexer . '_total_queries', 'flexicontent'))
		{
			$_total_queries = $session->get($indexer . '_total_queries', 0, 'flexicontent');
		}
		else
		{
			$_total_queries = 0;
		}

		$_total_queries += $query_count;
		$session->set($indexer . '_total_queries', $_total_queries, 'flexicontent');

		jexit(sprintf('success | ' . $cnt . ' | Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries);
	}


	/**
	 * Register Loggers for all distinct tasks
	 */
	private function _setUpLoggers($log_filename, $log_namespace)
	{
		jimport('joomla.log.log');

		// Currently default logger plus only 1 custom logger, we may add more custom logger if doing more distinct tasks
		$custom_loggers = array(
			'filemanager' => (object) array(
				'namespace' => $log_namespace,
				'filename' => $log_filename,
				'detailed_log' => true,
			),
			'mediadata' => (object) array(
				'namespace' => str_replace('filemanager', 'mediadata', $log_namespace),
				'filename' => str_replace('filemanager', 'mediadata', $log_filename),
				'detailed_log' => true,
			),
		);

		foreach($custom_loggers as $logger)
		{
			JLog::addLogger(
				array(
					'text_file' => $logger->filename,  // Sets the target log file
					'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
				),
				JLog::ALL,  // Sets messages of all log levels to be sent to the file
				array($logger->namespace)  // category of logged messages
			);
		}

		return $custom_loggers;
	}
}
