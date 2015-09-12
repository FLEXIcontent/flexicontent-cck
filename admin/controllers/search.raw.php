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

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Search Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerSearch extends FlexicontentController
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
	 * count the rows
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function countrows()
	{
		// Test counting with limited memory
		//ini_set("memory_limit", "20M");
		
		$start_microtime = microtime(true);
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		//$params = JComponentHelper::getParams( 'com_flexicontent' );
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		$session = JFactory::getSession();
		$db  = JFactory::getDBO();
		$app = JFactory::getApplication();
		$model = $this->getModel('search');
		
		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		if ($rebuildmode=='quick' && $indexer=='advanced') {
			$nse_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='non-search');
			$nsp_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-nosupport');
			$fields     = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-search');
			
			// Get the field ids of the fields removed from searching
			$del_fieldids = array_unique( array_merge(array_keys($nse_fields), array_keys($nsp_fields), array_keys($fields)) ) ;
			
			$session->set($indexer.'_nse_fields', $nse_fields, 'flexicontent');
			$session->set($indexer.'_nsp_fields', $nsp_fields, 'flexicontent');
		} else {  // INDEX: basic or advanced fully rebuilt
			$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='all-search');
			$del_fieldids = null;
		}
		
		// Set field information into session to avoid recalculation ...
		$session->set($indexer.'_fields', $fields, 'flexicontent');
		
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
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		$itemids	= $itemsmodel->getFieldsItems($fieldids);        // Get the items ids that have value for any of the searchable fields
		
		// Set item ids into session to avoid recalculation ...
		$session->set($indexer.'_items_to_index', $itemids, 'flexicontent');
		
		echo 'success';  //echo count($fieldids)*count($itemids).'|';
		// WARNING: json_encode will output object if given an array with gaps in the indexing
		//echo '|'.json_encode($itemids);
		//echo '|'.json_encode($fieldids);
		echo '|'.count($itemids);
		echo '|'.count($fieldids);
		
		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$session->set($indexer.'_total_runtime', $elapsed_microseconds ,'flexicontent');
		exit;
	}
	
	
	function index()
	{
		$start_microtime = microtime(true);
		$session = JFactory::getSession();
		$db = JFactory::getDBO();
		
		// Test indexing with limited memory
		//ini_set("memory_limit", "20M");
		
		@ob_end_clean();
		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$indexer = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		
		$items_per_call = JRequest::getVar('items_per_call', 20);  // Number of item to index per HTTP request
		$itemcnt = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		
		// TAKE CARE: this code depends on countrows() to set session variables
		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		if ($rebuildmode=='quick' && $indexer=='advanced') {
			$nse_fields = $session->get($indexer.'_nse_fields', array(),'flexicontent');
			$nsp_fields = $session->get($indexer.'_nsp_fields', array(),'flexicontent');
			$fields     = $session->get($indexer.'_fields', array(),'flexicontent');
			//echo 'fail|'; print_r(array_keys($fields)); exit;
			// Get the field ids of the fields removed from searching
			$del_fieldids = array_unique( array_merge(array_keys($nse_fields), array_keys($nsp_fields), array_keys($fields)) ) ;
		} else {
			$fields = $session->get($indexer.'_fields', array(),'flexicontent');
			//echo 'fail|'; print_r(array_keys($fields)); exit;
		}
		// Get the field ids of the searchable fields
		$fieldids = array_keys($fields);
		
		// Get fields that will have atomic search tables, (current for advanced index only)
		if ($indexer=='advanced')
		{
			$filterables = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='filter');
			$filterables = array_keys($filterables);
			$filterables = array_flip($filterables);
		} else $filterables = array();
		
		// Get items ids that have value for any of the searchable fields, but use session to avoid recalculation
		$itemids = $session->get($indexer.'_items_to_index', array(),'flexicontent');
		
		$_fields = array();
		foreach($fields as $field_id => $field)
		{
			// Clone field to avoid problems
			$_fields[$field_id] = clone($field);
			
			// Create field parameters if not already created
			if ( empty($_fields[$field_id]->parameters) ) $_fields[$field_id]->parameters = new JRegistry($_fields[$field_id]->attribs);
		}
		$fields = $_fields;
		
		$items_per_query = 50;
		$items_per_query = $items_per_query > $items_per_call ? $items_per_call : $items_per_query;
		$cnt = $itemcnt;
		while($cnt < count($itemids) && $cnt < $itemcnt+$items_per_call)
		{
			$query_itemids = array_slice($itemids, $cnt, $items_per_query);
			$cnt += $items_per_query;
			
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
						if ( isset($filterables[$field->id]) ) {  // Current for advanced index only
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
					$query = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id ";
					foreach ($searchindex as $query_itemid => $search_text)
					{
						$_search_text = implode(' | ', $search_text);
						if ($search_prefix && $_search_text) $_search_text = preg_replace('/(\b[^\s]+\b)/u', $search_prefix.'$0', trim($_search_text));
						
						// Add new search value into the DB
						$query .= " WHEN $query_itemid THEN ".$db->Quote( $_search_text );
					}
					$query .= " END ";
					$query .= " WHERE item_id IN (". implode(',', array_keys($searchindex)) .")";
				}
				$queries[] = $query;
			} else {
				if ( count($ai_query_vals) ) {  // check for zero search index records
					$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
						." (field_id,item_id,extraid,search_index,value_id) VALUES "
						.implode(",", $ai_query_vals);
				}
				foreach( $ai_query_vals_f as $_field_id => $_query_vals) {  // Current for advanced index only
					$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_".$_field_id
						." (field_id,item_id,extraid,search_index,value_id) VALUES "
						.implode(",", $_query_vals);
				}
			}
			foreach( $queries as $query ) {
				$db->setQuery($query);
				$db->execute();
			}
		}
		
		// Check if items have finished, otherwise continue with -next- group of item ids
		if ($cnt >= count($itemids))
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
		
		if ( !count($fieldids) ) {
			echo 'fail|Index was only cleaned-up, <br/>since no <b>fields</b> were marked as: '.'<br> -- ' .
				($indexer=='basic' ? 'Text Searchable (CONTENT LISTS)' :	'Text Searchable OR filterable (SEARCH VIEW)');
			exit;
		}
		
		if ( !count($itemids) ) {
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
		echo sprintf( ' [%.2f secs] ', $_total_runtime/1000000);
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
