<?php
/**
 * @version 1.5 stable $Id: search.php 1767 2013-09-18 17:46:46Z ggppdk $ 
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
	function __construct() {
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
		$start_microtime = microtime(true);
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		//$params = JComponentHelper::getParams( 'com_flexicontent' );
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		$session = JFactory::getSession();
		
		// Retrieve fields, that are assigned as (advanced/basic) searchable/filterable
		if ($rebuildmode=='quick' && $indexer=='advanced') {
			$nse_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='non-search');
			$nsp_fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=false, 0, $search_type='dirty-nosupport');
			$session->set($indexer.'_nse_fields', $nse_fields, 'flexicontent');
			$session->set($indexer.'_nsp_fields', $nsp_fields, 'flexicontent');
			$fields     = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=true,  0, $search_type='dirty-search');
		} else {
			$fields = FlexicontentFields::getSearchFields('id', $indexer, null, null, $_load_params=true, 0, $search_type='all-search');
		}
		// Get the field ids of the searchable fields
		$fieldids = array_keys($fields);
		
		// Get ids of searchable and ids of item having values for these fields
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		$itemids	= $itemsmodel->getFieldsItems($fieldids);        // Get the items ids that have value for any of the searchable fields
		
		// Set item ids into session to avoid recalculation ...
		$session->set($indexer.'_items_to_index', $itemids, 'flexicontent');
		// Set field information into session to avoid recalculation ...
		$session->set($indexer.'_fields', $fields, 'flexicontent');
		
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
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		
		$items_per_call = JRequest::getVar('items_per_call', 20);  // Number of item to index per HTTP request
		$itemcnt = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		
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
		
		// Get items ids that have value for any of the searchable fields, but use session to avoid recalculation
		$itemids = $session->get($indexer.'_items_to_index', array(),'flexicontent');
		
		// For advanced search index Remove old search values from the DB
		if ($itemcnt==0) {
			if ($indexer=='advanced') {
				if ($rebuildmode!='quick') {
					$clear_query = "TRUNCATE TABLE #__flexicontent_advsearch_index";
				} else if (count($del_fieldids)) {
					$del_fieldids_list = implode( ',' , $del_fieldids);
					$clear_query = "DELETE FROM #__flexicontent_advsearch_index "
						." WHERE field_id IN (". $del_fieldids_list. ")";
				}
			} else { // INDEX: basic 
				if ( !count($fields) ) {
					// (all items) Clear basic index records no fields marked as text searchable
					$clear_query = "UPDATE #__flexicontent_items_ext SET search_index = '' ";
				}
			}
			if ( !empty($clear_query) ) {
				$db->setQuery($clear_query);
				$db->query();
			}
		}
		
		$items_per_query = 50;
		$items_per_query = $items_per_query > $items_per_call ? $items_per_call : $items_per_query;
		$cnt = $itemcnt;
		while($cnt < $itemcnt+$items_per_call)
		{
			$query_itemids = array_slice($itemids, $cnt, $items_per_query);
			$cnt += $items_per_query;
			
			// Item is not needed, later and only if field uses item replacements then it will be loaded
			$item = null;
			
			$lang_query = "SELECT id, language"
				." FROM #__content"
				.(!FLEXI_J16GE ? " LEFT JOIN #__flexicontent_items_ext" : "")
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
			}
			
			// For current item: Loop though all searchable fields according to their type
			foreach($fieldids as $fieldid)
			{
				// Clone field to avoid problems
				$field = clone($fields[$fieldid]);
				
				// Indicate multiple items per query
				$field->item_id = 0;
				$field->query_itemids = $query_itemids;
				$field->items_data = $items_data;
				
				// Indicate that the indexing fuction should retrieve the values
				$values = null;
				
				// Add values to advanced search index
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				if ($indexer == 'advanced') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexAdvSearch', array( &$field, &$values, &$item ));
					//print_r($field->ai_query_vals);
					if ( isset($field->ai_query_vals) ) {
						foreach ($field->ai_query_vals as $query_val) $ai_query_vals[] = $query_val;
					} //else echo "Not set for : ". $field->name;
					
				} else if ($indexer == 'basic') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexSearch', array( &$field, &$values, &$item ));
					foreach ($query_itemids as $query_itemid)
						if ( @$field->search[$query_itemid] ) $searchindex[$query_itemid][] = /*$field->name.': '.*/$field->search[$query_itemid];
				}
			}
			
			// Create query that will update/insert data into the DB
			unset($query);  // make sure it is not set above
			if ($indexer == 'basic') {
				if (count($searchindex)) {  // check for zero search index records
					$query = "UPDATE #__flexicontent_items_ext SET search_index = CASE item_id ";
					foreach ($searchindex as $query_itemid => $search_text)
					{
						// Add new search value into the DB
						$query .= " WHEN $query_itemid THEN ".$db->Quote( implode(' | ', $search_text) );
					}
					$query .= " END ";
					$query .= " WHERE item_id IN (". implode(',', array_keys($searchindex)) .")";
				}
			} else {
				if ( count($ai_query_vals) ) {  // check for zero search index records
					$query = "INSERT INTO #__flexicontent_advsearch_index "
						." (field_id,item_id,extraid,search_index,value_id) VALUES "
						.implode(",", $ai_query_vals);
				}
			}
			if ( !empty($query) ) {
				$db->setQuery($query);
				$db->query();
				if ($db->getErrorNum()) {
					echo $db->getErrorMsg();
				}
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
			$db->query();
			
			// Force SEARCH properties of unpublished fields to be: normal OFF
			if ($indexer=='basic') {
				$query = 'UPDATE #__flexicontent_fields SET issearch = 0 WHERE published=0';
				$db->setQuery($query);
				$db->query();
			}	else {
				$query = 'UPDATE #__flexicontent_fields SET isadvsearch = 0, isadvfilter = 0  WHERE published=0';
				$db->setQuery($query);
				$db->query();
			}
		}
		
		if ( !count($fieldids) ) {
			echo 'fail|Index was only cleaned-up, since no field(s) were marked as: '.'<br> -- ' .
				($indexer=='basic' ? 'Text Searchable (CONTENT LISTS)' :	'Text Searchable OR filterable (SEARCH VIEW)');
			exit;
		}
		
		if ( !count($itemids) ) {
			echo 'fail|Index was only cleaned-up, since no items were found to have value for fields marked as: '.'<br> -- ' .
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
