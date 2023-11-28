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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'tag.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'tags.php';

/**
 * FLEXIcontent Tags Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerTags extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_tags';

	var $records_jtable = 'flexicontent_tags';

	var $record_name = 'tag';

	var $record_name_pl = 'tags';

	var $_NAME = 'TAG';

	var $record_alias = 'alias';

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
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTags;
	}


	/**
	 * Logic to import a tag list
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function import( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		if (!FlexicontentHelperPerm::getPerm()->CanCreateTags)
		{
			echo '<div class="copyfailed">' . JText::_('FLEXI_NO_AUTH_CREATE_NEW_TAGS') . '</div>';

			return;
		}

		$list  = $this->input->get('taglist', null, 'string');

		$model = $this->getModel($this->record_name_pl);
		$logs  = $model->importList($list);

		if ($logs)
		{
			if ($logs['success'])
			{
				echo '<div class="copyok">' . JText::sprintf('FLEXI_TAG_IMPORT_SUCCESS', $logs['success']) . '</div>';
			}

			if ($logs['error'])
			{
				echo '<div class="copywarn>' . JText::sprintf('FLEXI_TAG_IMPORT_FAILED', $logs['error']) . '</div>';
			}
		}
		else
		{
			echo '<div class="copyfailed">' . JText::_('FLEXI_NO_TAG_TO_IMPORT') . '</div>';
		}
	}


	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$name = $this->input->get('name', null, 'string');
		$cid  = $this->input->get('cid', array(0), 'array');
		$cid  = ArrayHelper::toInteger($cid, array(0));
		$cid  = (int) $cid[0];

		// Check if tag exists (id exists or name exists)
		JLoader::register("FlexicontentModelTag", JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'tag.php');
		$model = new FlexicontentModelTag;
		$model->setId($cid);
		$tag = $model->getTag($name);

		if ($tag && $tag->id)
		{
			// Since tag was found just output the loaded tag
			$id   = $model->get('id');
			$name = $model->get('name');
			echo $id . "|" . $name;
			jexit();
		}

		if ($cid)
		{
			echo "0|Tag not found";
			jexit();
		}

		if (!FlexicontentHelperPerm::getPerm()->CanCreateTags)
		{
			echo "0|" . JText::_('FLEXI_NO_AUTH_CREATE_NEW_TAGS');
			jexit();
		}

		// Add the new tag and output it so that it gets loaded by the form
		try
		{
			$obj = new stdClass;
			$obj->name = $name;
			$obj->published	= 1;
			$result = $model->store($obj);
			echo $result
				? $model->get('id') . '|' . $model->get('name')
				: '0|New tag was not created';
		}
		catch (Exception $e)
		{
			echo "0|New tag creation failed";
		}

		jexit();
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
		$indexer     = $this->input->getCmd('indexer', 'tag_mappings');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		// Check indexer type
		if ($indexer !== 'tag_mappings')
		{
			jexit('fail | indexer: ' . $indexer . ' not supported');
		}

		// Clear previous log file
		$log_filename = 'tag_mappings_checker_' . \JFactory::getUser()->id . '.php';
		$log_filename_full = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename);

		if (file_exists($log_filename_full))
		{
			@ unlink($log_filename_full);
		}


		// Get records model to call needed methods
		$records_model = $this->getModel($this->record_name_pl);

		// Get ids of records to process
		$records_total = 0;
		$record_ids = $records_model->getNotMappedTagIds(
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

		if (!FlexicontentHelperPerm::getPerm()->CanConfig)
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

		$indexer     = $this->input->getCmd('indexer', 'tag_mappings');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');

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
			array('com_flexicontent.tags.mappings_indexer')  // category of logged messages
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

		// Get records models
		$records_model = $this->getModel($this->record_name_pl);
		$record_model  = null;

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
			$data_query = "SELECT id, name, alias"
				. " FROM #__flexicontent_tags"
				. " WHERE id IN (" . implode(', ', $query_itemids) . ")";
			$db->setQuery($data_query);
			$record_data = $db->loadObjectList('id');

			$map_index = array();
			$errors = array();

			/**
			 * Loop through records
			 * Find / Create Joomla tags
			 */
			if ($indexer === 'tag_mappings')
			{
				foreach ($record_data as $tag_id => $tag)
				{
					$jtag_data_arr = $records_model->createFindJoomlaTags(array($tag->alias => '#new#' . $tag->name));
					$jtag_data = $jtag_data_arr ? reset($jtag_data_arr) : false;
					$tag->jtag_id = $jtag_data ? $jtag_data->id : 0;
					$map_index[] = ' WHEN ' . $tag->id . ' THEN ' . $tag->jtag_id;
				}
			}

			// Increment error count in session, and log errors into the log file
			if (count($errors))
			{
				$error_count = $session->get('tags.mappings_indexer.error_count', 0, 'flexicontent');
				$session->set('tags.mappings_indexer.error_count', $error_count + count($errors), 'flexicontent');

				foreach ($errors as $error_message)
				{
					JLog::add($error_message, JLog::WARNING, 'com_flexicontent.tags.mappings_indexer');
				}
			}


			// Create query that will update/insert data into the DB
			$queries = array();

			if ($indexer === 'tag_mappings')
			{
				$queries[] = 'UPDATE #__flexicontent_tags '
					. ' SET '
					. ' ' . $db->quoteName('jtag_id') . ' = CASE id ' . implode('', $map_index) . '  END '
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

		// Check if items have finished, otherwise continue with -next- group of item ids
		if ($cnt >= $records_total)
		{
			// Nothing to do in here
		}

		// Get next sub-set of items
		elseif ($cnt >= $records_start + self::$record_limit)
		{
			$records_start = $records_start + self::$record_limit;

			// Get next set of records
			$record_ids = $records_model->getNotMappedTagIds(
				$total, $records_start, self::$record_limit
			);

			// Set item ids into session to avoid recalculation ...
			$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
			$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

			$session->set($indexer . '_records_start', $records_start, 'flexicontent');
		}

		// Terminate if not processing any records
		if (!$records_total)
		{
			jexit(
				'fail | No tag mappings need to be synced'
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
