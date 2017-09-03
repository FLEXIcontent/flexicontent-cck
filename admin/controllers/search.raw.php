<?php
/**
 * @version 1.5 stable $Id: search.php 1900 2014-05-03 07:25:51Z ggppdk $ 
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Search Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerSearch extends FlexicontentController
{
	static $item_limit = 5000;
	
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

		if (!FlexicontentHelperPerm::getPerm()->CanIndex)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test counting with limited memory
		//ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);
		
		$has_zlib    = function_exists ( "zlib_encode" ); //version_compare(PHP_VERSION, '5.4.0', '>=');
		$indexer     = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();
		$model = $this->getModel('search');

		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		if ($rebuildmode=='quick' && $indexer=='advanced') {
			$nse_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='non-search');
			$nsp_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-nosupport');
			$fields     = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-search');
			
			// Get the field ids of the fields removed from searching
			$del_fieldids = array_unique( array_merge(array_keys($nse_fields), array_keys($nsp_fields), array_keys($fields)) ) ;
		} else {  // INDEX: basic or advanced fully rebuilt
			$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='all-search');
			$del_fieldids = null;
		}
		
		
		// Check is session table DATA column is not mediumtext (16MBs, it can be 64 KBs ('text') in some sites that were not properly upgraded)
		$tblname  = 'session';
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$db->setQuery("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$dbprefix.$tblname."'");
		$jession_coltypes = $db->loadAssocList('COLUMN_NAME');
		$_dataColType = strtolower($jession_coltypes['data']['DATA_TYPE']);
		$_dataCol_wrongSize = ($_dataColType != 'mediumtext') && ($_dataColType != 'longtext');
		
		// If data type is "text" it is safe to assume that it can be converted to "mediumtext",
		// since "text" means that session table is not memory storage,
		// plus it is already stored externally aka operation will be quick ?
		/*if ($_dataCol_wrongSize && $_dataColType == 'text')
		{
			$db->setQuery("ALTER TABLE `#__session` MODIFY `data` MEDIUMTEXT");
			$db->execute();
			$_dataCol_wrongSize = false;
		}*/
		
		
		// Set field information into session to avoid recalculation ...
		$_sz_encoded = base64_encode( $has_zlib ? zlib_encode(serialize($fields), -15) : serialize($fields) );
		if ( !$_dataCol_wrongSize || strlen($_sz_encoded) < 16000 ) {
			$session->set($indexer.'_fields', $_sz_encoded, 'flexicontent');
		} else {
			$session->set($indexer.'_fields', null, 'flexicontent');
		}
		
		
		// Set field IDs of fields that are advanced-index "filters"
		if ($indexer=='advanced') {
			$filterables = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='filter');
			$filterable_ids = array_flip( array_keys($filterables) );
		} else {
			$filterable_ids = array();
		}
		
		$_sz_encoded = base64_encode( $has_zlib ? zlib_encode(serialize($filterable_ids), -15) : serialize($filterable_ids) );
		$session->set($indexer.'_filterable_ids',  $_sz_encoded,  'flexicontent');  // this is both <3.4.7 session safe and also small, so do not compress
		
		
		// Get the field ids of the searchable fields that will be re-indexed, These are all ones ('all-search') or just the new ones ('dirty-search')
		$fieldids = array_keys($fields);
		
		
		// For advanced search index remove old search values from the DB, also creating missing per field tables
		if ($indexer=='advanced')
		{
			$model->purge( $del_fieldids );
		}
		
		// For basic index, clear records if no fields marked as text searchable
		else if ( !count($fields) )
		{
			$db->setQuery("UPDATE #__flexicontent_items_ext SET search_index = '' ");
			$db->execute();
		}
		
		
		// Get ids of searchable and ids of item having values for these fields
		$items_total = 0;
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		$itemids    = $itemsmodel->getFieldsItems(/*$fieldids*/null, $items_total, 0, self::$item_limit);
		
		// Set item ids into session to avoid recalculation ...
		$_sz_encoded = base64_encode( $has_zlib ? zlib_encode(serialize($itemids), -15) : serialize($itemids) );
		$session->set($indexer.'_items_to_index', $_sz_encoded, 'flexicontent');
		
		echo 'success';
		// WARNING: json_encode will output object if given an array with gaps in the indexing
		//echo '|'.json_encode($itemids);
		//echo '|'.json_encode($fieldids);
		echo '|'.$items_total;
		echo '|'.count($fieldids);
		
		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$_total_runtime = $elapsed_microseconds;
		$_total_queries = 0;
		echo sprintf( '|0| Server execution time: %.2f secs ', $_total_runtime/1000000) . ' | Total DB updates: '. $_total_queries;
		
		$session->set($indexer.'_total_runtime', $elapsed_microseconds, 'flexicontent');
		$session->set($indexer.'_total_queries', 0, 'flexicontent');
		$session->set($indexer.'_items_total', $items_total, 'flexicontent');
		$session->set($indexer.'_items_start', 0, 'flexicontent');
		exit;
	}
	
	
	function index()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		//JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		// Not need because this task need the user session data that are set by countrows that checked for forgeries

		if (!FlexicontentHelperPerm::getPerm()->CanIndex)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test indexing with limited memory
		//ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		
		$has_zlib      = function_exists ( "zlib_encode" ); //version_compare(PHP_VERSION, '5.4.0', '>=');
		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		
		$indexer     = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		
		$items_per_call = JRequest::getVar('items_per_call', 20);  // Number of item to index per HTTP request
		$itemcnt        = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		
		// TAKE CARE: this code depends on countrows() to set session variables
		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		$fields = $session->get($indexer.'_fields', array(),'flexicontent');
		if ( empty($fields) ) {  // if missing from session then calculate them
			if ($rebuildmode=='quick' && $indexer=='advanced') {
				$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-search');
			} else {  // INDEX: basic or advanced fully rebuilt
				$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='all-search');
			}
		}
		else {
			$fields = unserialize( $has_zlib ? zlib_decode(base64_decode($fields)) : base64_decode($fields) );
		}
		//echo 'fail|'; print_r(array_keys($fields)); exit;
		
		// Get the field ids of the searchable fields
		$fieldids = array_keys($fields);
		
		
		// Get fields that will have atomic search tables, (current for advanced index only)
		if ($indexer=='advanced') {
			$filterable_ids = $session->get($indexer.'_filterable_ids', array(),'flexicontent');
			$filterable_ids = unserialize( $has_zlib ? zlib_decode(base64_decode($filterable_ids)) : base64_decode($filterable_ids) );
		} else
			$filterable_ids = array();
		
		
		// Get items ids that have value for any of the searchable fields, but use session to avoid recalculation
		$itemids = $session->get($indexer.'_items_to_index', array(),'flexicontent');
		$itemids = unserialize( $has_zlib ? zlib_decode(base64_decode($itemids)) : base64_decode($itemids) );
		
		// Get total and current limit-start
		$items_total = $session->get($indexer.'_items_total', 0,'flexicontent');
		$items_start = $session->get($indexer.'_items_start', 0,'flexicontent');
		
		$_fields = array();
		foreach($fields as $field_id => $field)
		{
			// Clone field to avoid problems
			$_fields[$field_id] = clone($field);
			
			// Create field parameters if not already created
			if ( empty($_fields[$field_id]->parameters) ) $_fields[$field_id]->parameters = new JRegistry($_fields[$field_id]->attribs);
		}
		$fields = $_fields;
		
		
		// Get query size limit
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256*1024;
		$query_lim = (int) (3 * $max_allowed_packet / 4);
		//echo 'fail|'.$query_lim; exit;
		
		// Get script max
		$max_execution_time = ini_get("max_execution_time");
		//echo 'fail|'.$max_execution_time; exit;
		
		$query_count = 0;
		$max_items_per_query = 100;
		$max_items_per_query = $max_items_per_query > $items_per_call ? $items_per_call : $max_items_per_query;
		$cnt = $itemcnt;  // Start item counter at given index position
		
		while($cnt < $items_start+count($itemids)  &&  $cnt < $itemcnt+$items_per_call)   // Until all items of current sub-set finish or until maximum items per AJAX call are reached
		{
			// Get maximum items per SQL query
			$query_itemids = array_slice($itemids, ($cnt - $items_start), $max_items_per_query);
			if ( empty($query_itemids) ) die("fail|current step has no items");
			
			// Increment item counter, but detect passing past current sub-set limit
			$cnt += $max_items_per_query;
			if ($cnt > $items_start+self::$item_limit) $cnt = $items_start+self::$item_limit;
			
			// Item is not needed, later and only if field uses item replacements then it will be loaded
			$item = null;
			
			// Items language is needed to do (if needed) special per language handling
			$lang_query = "SELECT id, language"
				." FROM #__content AS i "
				." WHERE id IN (".implode(', ',$query_itemids).")"
				;
			$db->setQuery($lang_query);
			$items_data = $db->loadObjectList('id');
			
			if ($indexer == 'basic') {
				$searchindex = array();
				// Add all query itemids to searchindex array so that it will be cleared even if zero fields are indexed
				foreach ($query_itemids as $query_itemid) $searchindex[$query_itemid] = array();
			} else {
				// This will hold the SQL inserting new advanced search records for multiple item/values
				$ai_query_vals = array();
				$ai_query_vals_f = array();  // Current for advanced index only
			}
			
			// For current item: Loop though all searchable fields according to their type
			foreach($fieldids as $fieldid)
			{
				// Must SHALLOW clone because we will be setting some properties , e.g. 'ai_query_vals', that we do not 
				$field = clone($fields[$fieldid]);
				
				// Indicate multiple items per query
				$field->item_id = 0;
				$field->query_itemids = $query_itemids;
				$field->items_data = $items_data;   // Includes item langyage, which may be used for special per language handling
				
				// Indicate that the indexing fuction should retrieve the values
				$values = null;
				
				// Add values to advanced search index
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				if ($indexer == 'advanced') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexAdvSearch', array( &$field, &$values, &$item ));
					//print_r($field->ai_query_vals);
					if ( isset($field->ai_query_vals) ) {
						foreach ($field->ai_query_vals as $query_val) $ai_query_vals[] = $query_val;
						if ( isset($filterable_ids[$field->id]) ) {  // Current for advanced index only
							foreach ($field->ai_query_vals as $query_val) $ai_query_vals_f[$field->id][] = $query_val;
						}
					} //else echo "Not set for : ". $field->name;
					
				} else if ($indexer == 'basic') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexSearch', array( &$field, &$values, &$item ));
					foreach ($query_itemids as $query_itemid) {
						if ( @$field->search[$query_itemid] ) $searchindex[$query_itemid][] = /*$field->name.': '.*/$field->search[$query_itemid];
					}
				}
			}
			
			// Create query that will update/insert data into the DB
			unset($queries);  // make sure it is not set above
			$queries = array();
			if ($indexer == 'basic') {
				if (count($searchindex)) {  // check for zero search index records
					$query_vals = '';  $query_ids = array();  // Start new query
					foreach ($searchindex as $query_itemid => $search_text)
					{
						if (strlen($query_vals) > $query_lim) {
							$query = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id "
								. $query_vals
								." END "
								." WHERE item_id IN (". implode(',', $query_ids) .")";
							$queries[] = $query;
							$query_vals = '';  $query_ids = array();  // Start new query
						}
						
						$query_ids[] = $query_itemid;
						$_search_text = implode(' | ', $search_text);
						if ($search_prefix && $_search_text) $_search_text = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($_search_text));
						$query_vals .= " WHEN $query_itemid THEN ".$db->Quote( $_search_text );
					}
					if (count($query_ids)) {
						$query = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id "
							. $query_vals
							." END "
							." WHERE item_id IN (". implode(',', $query_ids) .")";
						$queries[] = $query;
					}
				}
			} else {
				if ( count($ai_query_vals) ) {  // check for zero search index records
					$query_vals = '';  // Start new query
					foreach ($ai_query_vals as &$query_value) {
						$query_vals .= ($query_vals ? ',' : '').$query_value;
						if (strlen($query_vals) > $query_lim) {
							$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
								." (field_id,item_id,extraid,search_index,value_id) VALUES "
								.$query_vals;
							$query_vals = ''; // Start new query
						}
					}
					unset($query_value);
					if (strlen($query_vals)) {
						$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
							." (field_id,item_id,extraid,search_index,value_id) VALUES "
							.$query_vals;
					}
				}
				
				foreach( $ai_query_vals_f as $_field_id => $_query_vals)  // Per field Table
				{
					$query_vals = '';  // Start new query
					foreach ($_query_vals as &$query_value) {
						$query_vals .= ($query_vals ? ',' : '').$query_value;
						if (strlen($query_vals) > $query_lim) {
							$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_".$_field_id
								." (field_id,item_id,extraid,search_index,value_id) VALUES "
								.$query_vals;
							$query_vals = ''; // Start new query
						}
					}
					if (strlen($query_vals)) {
						$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_".$_field_id
							." (field_id,item_id,extraid,search_index,value_id) VALUES "
							.$query_vals;
						$query_vals = ''; // Start new query
					}
				}
			}
			foreach( $queries as $query ) {
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (RuntimeException $e) {
					echo "fail|".$e->getMessage();
					exit;
				}
			}
			$query_count += count($queries);
			
			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$elapsed_seconds = $elapsed_microseconds / 1000000.0;
			if ($elapsed_seconds > $max_execution_time/3 || $elapsed_seconds > 6) break;
		}
		
		
		// Check if items have finished, otherwise continue with -next- group of item ids
		if ($cnt >= $items_total)
		{
			// Reset dirty SEARCH properties of published fields to be: normal ON/OFF
			$set_clause = ' SET' .($indexer=='basic' ?
				' issearch = CASE issearch WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE issearch   END' :
				' isadvsearch = CASE isadvsearch WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isadvsearch   END,'.
				' isadvfilter = CASE isadvfilter WHEN 2 THEN 1   WHEN -1 THEN 0   ELSE isadvfilter   END');
			$query = 'UPDATE #__flexicontent_fields'. $set_clause	." WHERE published=1";
			$db->setQuery($query);
			$db->execute();
			
			// Force SEARCH properties of unpublished fields to be: normal OFF
			if ($indexer=='basic') {
				$query = 'UPDATE #__flexicontent_fields SET issearch = 0 WHERE published=0';
				$db->setQuery($query);
				$db->execute();
			}	else {
				$query = 'UPDATE #__flexicontent_fields SET isadvsearch = 0, isadvfilter = 0  WHERE published=0';
				$db->setQuery($query);
				$db->execute();
			}
		}
		
		// Get next sub-set of items
		else if ($cnt >= $items_start + self::$item_limit)
		{
			$items_start = $items_start + self::$item_limit;
			
			$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
			$itemids = $itemsmodel->getFieldsItems(/*$fieldids*/null, $total, $items_start, self::$item_limit);
			
			// Set item ids into session to avoid recalculation ...
			$_sz_encoded = base64_encode( $has_zlib ? zlib_encode(serialize($itemids), -15) : serialize($itemids) );
			$session->set($indexer.'_items_to_index', $_sz_encoded, 'flexicontent');
			
			$session->set($indexer.'_items_start', $items_start, 'flexicontent');
		}
		
		// Terminate if no fields found to be indexable
		if ( !count($fieldids) ) {
			echo 'fail|Index was only cleaned-up, <br/>since no <b>fields</b> were marked as: '.'<br> -- ' .
				($indexer=='basic' ? 'Text Searchable (CONTENT LISTS)' :	'Text Searchable OR filterable (SEARCH VIEW)');
			exit;
		}
		
		// Terminate if not indexing any items
		if ( !$items_total ) {
			echo 'fail|Index was only cleaned-up, <br/>since no <b>items</b> were found to have value for fields marked as: '.'<br> -- ' .
				($indexer=='basic' ? 'Text Searchable (CONTENT LISTS)' :	'Text Searchable OR filterable (SEARCH VIEW)');
			exit;
		}
		
		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		if ( $session->has($indexer.'_total_runtime', 'flexicontent')) {
			$_total_runtime = $session->get($indexer.'_total_runtime', 0,'flexicontent');
		} else {
			$_total_runtime = 0;
		}
		$_total_runtime += $elapsed_microseconds;
		$session->set($indexer.'_total_runtime', $_total_runtime ,'flexicontent');
		
		if ( $session->has($indexer.'_total_queries', 'flexicontent')) {
			$_total_queries = $session->get($indexer.'_total_queries', 0,'flexicontent');
		} else {
			$_total_queries = 0;
		}
		$_total_queries += $query_count;
		$session->set($indexer.'_total_queries', $_total_queries ,'flexicontent');
		
		echo sprintf( 'success | '. $cnt.' | Server execution time: %.2f secs ', $_total_runtime/1000000) . ' | Total DB updates: '. $_total_queries;
		exit;
	}
	
	
	function purge()
	{
		$model = $this->getModel('search');
		$model->purge();
		$msg = JText::_('FLEXI_ITEMS_PURGED');
		$this->setRedirect('index.php?option=com_flexicontent&view=search', $msg);
	}	
}
