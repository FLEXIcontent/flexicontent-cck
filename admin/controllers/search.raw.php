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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'search.php';

/**
 * FLEXIcontent Search Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerSearch extends FlexicontentControllerBaseAdmin
{
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
	}



	/**
	 * Method called before updating indexes to discover and count the rows that will be indexed
	 *
	 * @access   public
	 * @return   void
	 * @since    1.0
	 */
	public function countrows()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		JSession::checkToken('request') or jexit('fail | ' . JText::_('JINVALID_TOKEN'));

		if (!FlexicontentHelperPerm::getPerm()->CanIndex)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test counting with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$has_zlib    = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');
		$indexer     = $this->input->getCmd('indexer', 'advanced');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();
		$model = $this->getModel('search');

		// Check indexer type
		if ($indexer !== 'advanced' && $indexer !== 'basic')
		{
			jexit('fail | indexer: ' . $indexer . ' not supported');
		}

		// Clear previous log file
		$log_filename = 'items_search_indexer_' . \JFactory::getUser()->id . '.php';
		$log_filename_full = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename);

		if (file_exists($log_filename_full))
		{
			@ unlink($log_filename_full);
		}

		/**
		 * Get ids of searchable fields and ids of items having values for these fields
		 */

		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		if ($rebuildmode === 'quick' && $indexer === 'advanced')
		{
			$nse_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'non-search');
			$nsp_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'dirty-nosupport');
			$fields     = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'dirty-search');

			// Get the field ids of the fields removed from searching
			$del_field_ids = array_unique(array_merge(array_keys($nse_fields), array_keys($nsp_fields), array_keys($fields)));
		}
		else
		{
			// INDEX: basic or advanced fully rebuilt
			$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'all-search');
			$del_field_ids = null;
		}

		// Check is session table DATA column is not mediumtext (16MBs, it can be 64 KBs ('text') in some sites that were not properly upgraded)
		$tblname  = 'session';
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$db->setQuery("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $dbname . "' AND TABLE_NAME = '" . $dbprefix . $tblname . "'");
		$jession_coltypes = $db->loadAssocList('COLUMN_NAME');
		$_dataColType = strtolower($jession_coltypes['data']['DATA_TYPE']);
		$_dataCol_wrongSize = ($_dataColType != 'mediumtext') && ($_dataColType != 'longtext');

		// If data type is "text" it is safe to assume that it can be converted to "mediumtext",
		// since "text" means that session table is not memory storage,
		// plus it is already stored externally aka operation will be quick ?
		/*
		if ($_dataCol_wrongSize && $_dataColType == 'text')
		{
			$db->setQuery("ALTER TABLE `#__session` MODIFY `data` MEDIUMTEXT");
			$db->execute();
			$_dataCol_wrongSize = false;
		}*/

		// Set field information into session to avoid recalculation ...
		$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($fields), -15) : serialize($fields));

		if (!$_dataCol_wrongSize || strlen($_sz_encoded) < 16000)
		{
			$session->set($indexer . '_fields', $_sz_encoded, 'flexicontent');
		}
		else
		{
			$session->set($indexer . '_fields', null, 'flexicontent');
		}

		// Set field IDs of fields that are advanced-index "filters"
		if ($indexer === 'advanced')
		{
			$filterables = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'filter');
			$filterable_ids = array_flip(array_keys($filterables));
		}
		else
		{
			$filterable_ids = array();
		}

		$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($filterable_ids), -15) : serialize($filterable_ids));
		$session->set($indexer . '_filterable_ids',  $_sz_encoded,  'flexicontent');  // This is both <3.4.7 session safe and also small, so do not compress

		// Get the field ids of the searchable fields that will be re-indexed, These are all ones ('all-search') or just the new ones ('dirty-search')
		$field_ids = array_keys($fields);

		// For advanced search index remove old search values from the DB, also creating missing per field tables
		if ($indexer === 'advanced')
		{
			$model->purge($del_field_ids);
		}

		// For basic index, clear records if no fields marked as text searchable
		elseif (!count($fields))
		{
			$db->setQuery("UPDATE #__flexicontent_items_ext SET search_index = '' ");
			$db->execute();
		}


		// Get records model to call needed methods
		$records_model = $this->getModel('items');

		// Get ids of records to process
		$records_total = 0;
		$record_ids = $records_model->getFieldsItems(/*$field_ids*/ null,
			$records_total, 0, self::$record_limit
		);

		// Set record ids into session to avoid recalculation ...
		$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
		$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

		echo 'success';
		echo '|' . $records_total;
		echo '|' . count($field_ids);

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



	/**
	 * Method to update the Basic or Advanced search index
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	public function index()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		// JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		// Not need because this task need the user session data that are set by countrows that checked for forgeries

		if (!FlexicontentHelperPerm::getPerm()->CanIndex)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test indexing with limited memory
		// ini_set("memory_limit", "20M");

		// Try to increment the memory limit
		$this->_increase_memory_limit();

		$start_microtime = microtime(true);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		$has_zlib      = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');
		$search_prefix = JComponentHelper::getParams('com_flexicontent')->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

		$indexer     = $this->input->getCmd('indexer', 'advanced');
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
			array('com_flexicontent.search.items_indexer')  // category of logged messages
		);

		// TAKE CARE: this code depends on countrows() to set session variables
		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		$fields = $session->get($indexer . '_fields', array(), 'flexicontent');

		// If missing from session then calculate them
		if (empty($fields))
		{
			// INDEX: quick advanced index rebuilt
			if ($rebuildmode == 'quick' && $indexer == 'advanced')
			{
				$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'dirty-search');
			}
			else
			{
				// INDEX: basic index or fully rebuilt advanced index
				$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params = false, 0, $search_type = 'all-search');
			}
		}
		else
		{
			$fields = unserialize($has_zlib ? zlib_decode(base64_decode($fields)) : base64_decode($fields));
		}

		// echo 'fail|'; print_r(array_keys($fields)); exit;

		// Get the field ids of the searchable fields
		$field_ids = array_keys($fields);

		// Get fields that will have atomic search tables, (current for advanced index only)
		if ($indexer === 'advanced')
		{
			$filterable_ids = $session->get($indexer . '_filterable_ids', array(), 'flexicontent');
			$filterable_ids = unserialize($has_zlib ? zlib_decode(base64_decode($filterable_ids)) : base64_decode($filterable_ids));
		}
		else
		{
			$filterable_ids = array();
		}


		/**
		 * We will process items that have value for any of the searchable fields
		 */

		// Get record ids to process, but use session to avoid recalculation
		$record_ids = $session->get($indexer . '_records_to_index', array(), 'flexicontent');
		$record_ids = unserialize($has_zlib ? zlib_decode(base64_decode($record_ids)) : base64_decode($record_ids));

		// Get total and current limit-start
		$records_total = $session->get($indexer . '_records_total', 0, 'flexicontent');
		$records_start = $session->get($indexer . '_records_start', 0, 'flexicontent');

		$_fields = array();

		foreach ($fields as $field_id => $field)
		{
			// Clone field to avoid problems
			$_fields[$field_id] = clone $field;

			// Create field parameters if not already created
			if (empty($_fields[$field_id]->parameters))
			{
				$_fields[$field_id]->parameters = new JRegistry($_fields[$field_id]->attribs);
			}
		}

		$fields = $_fields;

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
		$records_model = $this->getModel('items');
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

			// Item is not needed, later and only if field uses item replacements then it will be loaded
			$item = null;

			// Items language is needed to do (if needed) special per language handling
			$lang_query = $db->getQuery(true)
				->select('id, language')
				->from('#__content AS i')
				->where('id IN (' . implode(', ', $query_itemids) . ')')
				;
			$items_data = $db->setQuery($lang_query)->loadObjectList('id');

			if ($indexer === 'basic')
			{
				$supports_verify = array();

				foreach ($field_ids as $field_id)
				{
					$field = $fields[$field_id];

					if (!isset($supports_verify[$field->field_type]))
					{
						$supports_verify[$field->field_type] = FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onVerifyValues', array( null, null ))
						 ? true
						 : false;
					}

					if ($supports_verify[$field->field_type])
					{
						// Load field values for the purpose of verifying them
						$f_values_query = $db->getQuery(true)
							->select('*')
							->from('#__flexicontent_fields_item_relations AS r')
							->where('item_id IN (' . implode(', ', $query_itemids) . ')')
							->where('field_id = ' . $field->id)
							;
						$field_value_rows[$field->id] = $db->setQuery($f_values_query)->loadObjectList();
					}
				}

				// Add all query itemids to searchindex array so that it will be cleared even if zero fields are indexed
				$searchindex = array();

				foreach ($query_itemids as $query_itemid)
				{
					$searchindex[$query_itemid] = array();
				}
			}
			else
			{
				// This will hold the SQL inserting new advanced search records for multiple item/values
				$ai_query_vals = array();
				$ai_query_vals_f = array();  // Current for advanced index only
			}

			// For current item: Loop though all searchable fields according to their type
			foreach ($field_ids as $field_id)
			{
				// Must SHALLOW clone because we will be setting some properties , e.g. 'ai_query_vals', that we do not
				$field = clone $fields[$field_id];

				// Indicate multiple items per query
				$field->item_id = 0;
				$field->query_itemids = $query_itemids;
				$field->items_data = $items_data;   // Includes item language, which may be used for special per language handling

				// Indicate that the indexing fuction should retrieve the values
				$values = null;

				// Add values to advanced search index
				$fieldname = $field->iscore ? 'core' : $field->field_type;

				if ($indexer === 'advanced')
				{
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexAdvSearch', array( &$field, &$values, &$item ));

					// print_r($field->ai_query_vals);
					if (isset($field->ai_query_vals))
					{
						foreach ($field->ai_query_vals as $query_val)
						{
							$ai_query_vals[] = $query_val;
						}

						// Currently for advanced index only
						if (isset($filterable_ids[$field->id]))
						{
							foreach ($field->ai_query_vals as $query_val)
							{
								$ai_query_vals_f[$field->id][] = $query_val;
							}
						}
					}
					// else echo "Not set for : ". $field->name;
				}
				elseif ($indexer === 'basic')
				{
					// Create search index record for this set of items
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexSearch', array( &$field, &$values, &$item ));

					foreach ($query_itemids as $query_itemid)
					{
						if (!empty($field->search[$query_itemid]))
						{
							$searchindex[$query_itemid][] = $field->search[$query_itemid];
						}
					}

					// Verify field values validity according to field configuraton
					if (!empty($supports_verify[$field->field_type]) && count($field_value_rows[$field->id]))
					{
						FLEXIUtilities::call_FC_Field_Func($fieldname, 'onVerifyValues', array( $field, $field_value_rows[$field_id] ));
					}
				}
			}

			// Create query that will update/insert data into the DB
			$queries = array();

			if ($indexer === 'basic')
			{
				// Check for zero search index records
				if (count($searchindex))
				{
					// Start new query
					$query_vals = '';
					$query_ids = array();

					foreach ($searchindex as $query_itemid => $search_text)
					{
						if (strlen($query_vals) > $query_lim)
						{
							$queries[] = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id "
								. $query_vals
								. " END "
								. " WHERE item_id IN (" . implode(',', $query_ids) . ")";

							// Start new query
							$query_vals = '';
							$query_ids = array();
						}

						$query_ids[] = $query_itemid;
						$_search_text = implode(' | ', $search_text);

						if ($search_prefix && $_search_text)
						{
							$_search_text = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix . '$0', trim($_search_text));
						}

						$query_vals .= " WHEN $query_itemid THEN " . $db->Quote($_search_text);
					}

					if (count($query_ids))
					{
						$queries[] = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id "
							. $query_vals
							. " END "
							. " WHERE item_id IN (" . implode(',', $query_ids) . ")";
					}
				}
			}
			else
			{
				// Check for zero search index records
				if (count($ai_query_vals))
				{
					$query_vals = '';  // Start new query

					foreach ($ai_query_vals as &$query_value)
					{
						$query_vals .= ($query_vals ? ',' : '') . $query_value;

						if (strlen($query_vals) > $query_lim)
						{
							$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
								. " (field_id,item_id,extraid,search_index,value_id) VALUES "
								. $query_vals;
							$query_vals = ''; // Start new query
						}
					}

					unset($query_value);

					if (strlen($query_vals))
					{
						$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
							. " (field_id,item_id,extraid,search_index,value_id) VALUES "
							. $query_vals;
					}
				}

				// Per field Table
				foreach ($ai_query_vals_f as $_field_id => $_query_vals)
				{
					$query_vals = '';  // Start new query

					foreach ($_query_vals as &$query_value)
					{
						$query_vals .= ($query_vals ? ',' : '') . $query_value;

						if (strlen($query_vals) > $query_lim)
						{
							$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_" . $_field_id
								. " (field_id,item_id,extraid,search_index,value_id) VALUES "
								. $query_vals;
							$query_vals = ''; // Start new query
						}
					}

					if (strlen($query_vals))
					{
						$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_" . $_field_id
							. " (field_id,item_id,extraid,search_index,value_id) VALUES "
							. $query_vals;
						$query_vals = ''; // Start new query
					}
				}
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
			// Reset dirty SEARCH properties of published fields to be: normal ON/OFF
			$set_clause = ' SET' . ($indexer == 'basic'
				?
				' issearch = CASE issearch WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE issearch   END,' .
				' isfilter = CASE isfilter WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isfilter   END'
				:
				' isadvsearch = CASE isadvsearch WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isadvsearch   END,' .
				' isadvfilter = CASE isadvfilter WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isadvfilter   END');
			$query = 'UPDATE #__flexicontent_fields' . $set_clause . " WHERE published=1";
			$db->setQuery($query)->execute();

			/**
			 * *Force SEARCH properties of non-published fields to be: normal OFF
			 */
			if ($indexer === 'basic')
			{
				$query = 'UPDATE #__flexicontent_fields SET issearch = 0 WHERE published <> 1';
				$db->setQuery($query)->execute();

				// Do not clear (set to 0) the isfilter of unpublished fields ... since this uses no special index
				$query = 'UPDATE #__flexicontent_fields '
					. ' SET isfilter = CASE isfilter WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isfilter   END '
					. ' WHERE published <> 1 AND (isfilter = 2 OR isfilter = -1)';
				$db->setQuery($query)->execute();
			}
			else
			{
				$query = 'UPDATE #__flexicontent_fields SET isadvsearch = 0, isadvfilter = 0  WHERE published <> 1';
				$db->setQuery($query)->execute();
			}
		}

		// Get next sub-set of items
		elseif ($cnt >= $records_start + self::$record_limit)
		{
			$records_start = $records_start + self::$record_limit;

			// Get next set of records
			$total = null;
			$record_ids = $records_model->getFieldsItems(/*$field_ids*/ null,
				$total, $records_start, self::$record_limit
			);

			// Set item ids into session to avoid recalculation ...
			$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
			$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

			$session->set($indexer . '_records_start', $records_start, 'flexicontent');
		}

		// Terminate if no fields found to be indexable
		if (!count($field_ids))
		{
			/**
			 * *Force SEARCH properties of non-published fields to be: normal OFF
			 */
			if ($indexer === 'basic')
			{
				$query = 'UPDATE #__flexicontent_fields SET issearch = 0 WHERE published <> 1';
				$db->setQuery($query)->execute();

				// Do not clear (set to 0) the isfilter of unpublished fields ... since this uses no special index
				$query = 'UPDATE #__flexicontent_fields '
					. ' SET isfilter = CASE isfilter WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isfilter   END '
					. ' WHERE published <> 1 AND (isfilter = 2 OR isfilter = -1)';
				$db->setQuery($query)->execute();
			}
			else
			{
				$query = 'UPDATE #__flexicontent_fields SET isadvsearch = 0, isadvfilter = 0  WHERE published <> 1';
				$db->setQuery($query)->execute();
			}

			jexit(
				'fail | Index was only cleaned-up, <br/>since no <b>fields</b> were marked as: ' . '<br> -- ' .
				($indexer == 'basic'
					? 'Text Searchable (CONTENT LISTS)'
					: 'Text Searchable OR filterable (SEARCH VIEW)'
				)
			);
		}

		// Terminate if not processing any records
		if (!$records_total)
		{
			jexit(
				'fail | Index was only cleaned-up, <br/>since no <b>items</b> were found to have value for fields marked as: ' . '<br> -- ' .
				($indexer == 'basic'
					? 'Text Searchable (CONTENT LISTS)'
					: 'Text Searchable OR filterable (SEARCH VIEW)'
				)
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
	 * Method to purge the Advanced search index
	 *
	 * @access   public
	 * @return   void
	 * @since    1.0
	 */
	public function purge()
	{
		$model = $this->getModel('search');
		$model->purge();
		$msg = JText::_('FLEXI_ITEMS_PURGED');
		$this->setRedirect('index.php?option=com_flexicontent&view=search', $msg);
	}


	private function _increase_memory_limit()
	{
		// Try to increment memory limits
		$memory_limit	= trim( @ ini_get( 'memory_limit' ) );
		if ( $memory_limit )
		{
			switch (strtolower(substr($memory_limit, -1)))
			{
				case 'm': $memory_limit = (int)substr($memory_limit, 0, -1) * 1048576; break;
				case 'k': $memory_limit = (int)substr($memory_limit, 0, -1) * 1024; break;
				case 'g': $memory_limit = (int)substr($memory_limit, 0, -1) * 1073741824; break;
				case 'b':
					switch (strtolower(substr($memory_limit, -2, 1)))
					{
						case 'm': $memory_limit = (int)substr($memory_limit, 0, -2) * 1048576; break;
						case 'k': $memory_limit = (int)substr($memory_limit, 0, -2) * 1024; break;
						case 'g': $memory_limit = (int)substr($memory_limit, 0, -2) * 1073741824; break;
						default : break;
					} break;
				default: break;
			}
			if ( $memory_limit < 16 * 1024 * 1024 ) @ ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32 * 1024 * 1024 ) @ ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64 * 1024 * 1024 ) @ ini_set( 'memory_limit', '64M' );
			if ( $memory_limit < 128 * 1024 * 1024 ) @ ini_set( 'memory_limit', '128M' );
			if ( $memory_limit < 256 * 1024 * 1024 ) @ ini_set( 'memory_limit', '256M' );
			if ( $memory_limit < 512 * 1024 * 1024 ) @ ini_set( 'memory_limit', '512M' );
		}
	}
}
