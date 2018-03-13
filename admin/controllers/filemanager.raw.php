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

defined('_JEXEC') or die('Restricted access');

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controller.php');

/**
 * FLEXIcontent Component Files Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFilemanager extends FlexicontentController
{
	static $record_limit = 5000;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
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
		$log_filename = 'filemanager_stats_indexer_' . \JFactory::getUser()->id . '.php';
		$log_filename_full = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename);

		if (file_exists($log_filename_full))
		{
			@ unlink($log_filename_full);
		}


		// Get records model to call needed methods
		$records_model = $this->getModel('filemanager');

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

		$records_per_call = $this->input->getInt('records_per_call', 20);  // Number of item to index per HTTP request
		$records_cnt      = $this->input->getInt('records_cnt', 0);        // Counter of items indexed so far, this is given via HTTP request

		$log_filename = $session->get($indexer . '_log_filename', null, 'flexicontent');
		jimport('joomla.log.log');
		JLog::addLogger(
			array(
				'text_file' => $log_filename,  // Sets the target log file
				'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
			),
			JLog::ALL,  // Sets messages of all log levels to be sent to the file
			array('com_flexicontent.filemanager.stats_indexer')  // category of logged messages
		);

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
		$model        = $this->getModel('filemanager');
		$record_model = $this->getModel('file');

		// Find usage in fields
		$s_assigned_fields = array('file', 'minigallery');
		$m_assigned_fields = array('image');
		$m_assigned_props  = array('image' => 'originalname');
		$m_assigned_vals   = array('image' => 'filename');

		$query_count         = 0;
		$max_items_per_query = 100;
		$max_items_per_query = $max_items_per_query > $records_per_call ? $records_per_call : $max_items_per_query;

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
				$path = $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
				$file_path = $path . DS . $file->filename;

				if (!$file->url)
				{
					$file->size = file_exists($file_path) ? filesize($file_path) : 0;
				}

				elseif ($index_urls)
				{
					$url = $file->filename_original ?: $file->filename;

					if ($url)
					{
						$filesize = $record_model->get_file_size_from_url($url);

						if ($filesize === -999)
						{
							$errors[] = $url . ' -- ' . $record_model->getError();
						}

						$file->size = $filesize < 0 ? 0 : $filesize;
					}
				}

				$size_index[] = ' WHEN ' . $file->id . ' THEN ' . $file->size;
			}

			// Increment error count in session, and log errors into the log file
			if (count($errors))
			{
				$error_count = $session->get('filemanager.stats_indexer.error_count', 0, 'flexicontent');
				$session->set('filemanager.stats_indexer.error_count', $error_count + count($errors), 'flexicontent');

				foreach ($errors as $error_message)
				{
					JLog::add($error_message, JLog::WARNING, 'com_flexicontent.filemanager.stats_indexer');
				}
			}

			// Single property fields, get file usage (# assignments)
			if ($s_assigned_fields)
			{
				foreach ($s_assigned_fields as $field_type)
				{
					$model->countFieldRelationsSingleProp($record_data, $field_type);
				}
			}

			// Multi property fields, get file usage (# assignments)
			if ($m_assigned_fields)
			{
				foreach ($m_assigned_fields as $field_type)
				{
					$field_prop = $m_assigned_props[$field_type];
					$value_prop = $m_assigned_vals[$field_type];
					$model->countFieldRelationsMultiProp($record_data, $value_prop, $field_prop, $field_type);
				}
			}

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
}
