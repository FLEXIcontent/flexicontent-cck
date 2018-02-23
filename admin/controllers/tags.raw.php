<?php
/**
 * @version 1.5 stable $Id: tags.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * FLEXIcontent Component Tags Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTags extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('import', 			'import');
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

		$model = $this->getModel('tags');
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
		JArrayHelper::toInteger($cid, array(0));
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		if (!FlexicontentHelperPerm::getPerm()->CanConfig)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test counting with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$has_zlib    = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');
		$indexer     = $this->input->getCmd('indexer', 'tags_default');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');
		$index_urls  = $this->input->getInt('index_urls', 0);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		// Actions according to rebuildmode
		if ($indexer != 'tags_default')
		{
			die("'rebuildmode': '" . $rebuildmode . "'. not supported");
		}

		// Clear previous log file
		$log_filename = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . 'tags_index_mappings_' . \JFactory::getUser()->id . '.php');

		if (file_exists($log_filename))
		{
			@ unlink($log_filename);
		}

		$session->set('tags.log_filename', $log_filename, 'flexicontent');

		// Get ids of files to index
		$model = $this->getModel('tags');
		$tags_ids = $model->getNotMappedTagIds();

		// Set tag ids into session to avoid recalculation ...
		$session->set($indexer . '_items_to_index', $tags_ids, 'flexicontent');

		echo 'success';
		// echo count($fieldids)*count($itemids).'|';

		// WARNING: json_encode will output object if given an array with gaps in the indexing
		// echo '|' . json_encode($itemids);
		// echo '|' . json_encode($fieldids);
		echo '|' . count($tags_ids);
		echo '|' . count(array());

		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$_total_runtime = $elapsed_microseconds;
		$_total_queries = 0;
		echo sprintf('|0| Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries;

		$session->set($indexer . '_total_runtime', $elapsed_microseconds, 'flexicontent');
		$session->set($indexer . '_total_queries', 0, 'flexicontent');
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
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test indexing with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		$indexer     = $this->input->getCmd('indexer', 'tags_default');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');
		$index_urls  = $this->input->getInt('index_urls', 0);

		$items_per_call = $this->input->getInt('items_per_call', 20);  // Number of item to index per HTTP request
		$itemcnt        = $this->input->getInt('itemcnt', 0);          // Counter of items indexed so far, this is given via HTTP request

		// Actions according to rebuildmode
		if ($indexer != 'tags_default')
		{
			die("'rebuildmode': '" . $rebuildmode . "'. not supported");
		}

		// Get items ids that have value for any of the searchable fields, but use session to avoid recalculation
		$itemids = $session->get($indexer . '_items_to_index', array(), 'flexicontent');

		// Get query size limit
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256 * 1024;
		$query_lim          = (int) (3 * $max_allowed_packet / 4);

		// echo 'fail|' . $query_lim; jexit();

		// Get script max
		$max_execution_time = ini_get("max_execution_time");

		// echo 'fail|' . $max_execution_time; jexit();

		// Get model
		$model      = $this->getModel('tags');
		$file_model = $this->getModel('tag');

		$query_count         = 0;
		$max_items_per_query = 100;
		$max_items_per_query = $max_items_per_query > $items_per_call ? $items_per_call : $max_items_per_query;
		$cnt                 = $itemcnt;

		while ($cnt < count($itemids) && $cnt < $itemcnt + $items_per_call)
		{
			$query_itemids = array_slice($itemids, $cnt, $max_items_per_query);
			$cnt += $max_items_per_query;

			// Get files
			$data_query = "SELECT id, name, alias"
				. " FROM #__flexicontent_tags"
				. " WHERE id IN (" . implode(', ', $query_itemids) . ")";
			$db->setQuery($data_query);
			$tag_data = $db->loadObjectList('id');

			$map_index = array();
			$errors = array();
			$tag_titles = array();

			// Find / Create Joomla tags
			foreach ($tag_data as $tag_id => $tag)
			{
				$jtag_id_arr = $model->createTagsFromField(array($tag->alias => '#new#' . $tag->name));
				$jtag_id = $jtag_id_arr ? reset($jtag_id_arr) : 0;
				$tag->jtag_id = $jtag_id_arr ? reset($jtag_id_arr) : 0;
				$map_index[] = ' WHEN ' . $tag->id . ' THEN ' . $tag->jtag_id;
			}

			if (count($errors))
			{
				$log_filename = 'tags_index_mappings_' . \JFactory::getUser()->id . '.php';
				jimport('joomla.log.log');
				JLog::addLogger(
					array(
						'text_file' => $log_filename,  // Sets the target log file
						'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
					),
					JLog::ALL,  // Sets messages of all log levels to be sent to the file
					array('com_flexicontent.tags.mappings_indexer')  // category of logged messages
				);

				$mappings_indexer_error_count = $session->get('tags.mappings_indexer_error_count', 0, 'flexicontent');
				$session->set('tags.mappings_indexer_error_count', $mappings_indexer_error_count + count($errors), 'flexicontent');

				// mappings_indexer_errors = $session->get('tags.mappings_indexer_errors', array(), 'flexicontent');
				foreach ($errors as $error_message)
				{
					// $mappings_indexer_errors[] = $error_message;
					JLog::add($error_message, JLog::WARNING, 'com_flexicontent.tags.mappings_indexer');
				}

				// $session->set('tags.mappings_indexer_errors', $mappings_indexer_errors, 'flexicontent');
			}
			else
			{
				/*$log_filename = 'tags_index_mappings_' . \JFactory::getUser()->id . '.php';
				jimport('joomla.log.log');
				JLog::addLogger(
					array(
						'text_file' => $log_filename,  // Sets the target log file
						'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
					),
					JLog::ALL,  // Sets messages of all log levels to be sent to the file
					array('com_flexicontent.tags.mappings_indexer')  // category of logged messages
				);

				// mappings_indexer_errors = $session->get('tags.mappings_indexer_errors', array(), 'flexicontent');
				foreach ($map_index as $map_index_clause)
				{
					// $mappings_indexer_errors[] = $error_message;
					JLog::add($map_index_clause, JLog::INFO, 'com_flexicontent.tags.mappings_indexer');
				}*/
			}

			// Create query that will update/insert data into the DB
			unset($query);
			$query = 'UPDATE #__flexicontent_tags '
				. ' SET '
				. ' ' . $db->quoteName('jtag_id') . ' = CASE id ' . implode('', $map_index) . '  END '
				. ' WHERE id IN (' . implode(', ', $query_itemids) . ')';
			$db->setQuery($query);
			$db->execute();
			$query_count++;

			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$elapsed_seconds = $elapsed_microseconds / 1000000.0;

			if ($elapsed_seconds > $max_execution_time / 3 || $elapsed_seconds > 5)
			{
				break;
			}
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

		jexit(printf($cnt . ' | Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries);
	}


	/**
	 * Logic to change the state of a tag
	 *
	 * @access public
	 * @return void
	 * @since 3.2
	 */
	function setitemstate()
	{
		flexicontent_html::setitemstate($this, 'json', $_record_type = 'tag');
	}
}