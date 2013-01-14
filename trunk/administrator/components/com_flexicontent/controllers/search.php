<?php
/**
 * @version 1.5 stable $Id$ 
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
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		//$params =& JComponentHelper::getParams( 'com_flexicontent' );
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		
		// Get ids of searchable and ids of item having values for these fields
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		$fields   = FlexicontentFields::getSearchFields('id', $indexer);  // Retrieve fields, that are assigned as (advanced/basic) searchable (thus indexable too)
		$fieldids = array_keys($fields);                             // Get the field ids of the searchable fields
		$itemids	= & $itemsmodel->getFieldsItems($fieldids);        // Get the items ids that have value for any of the searchable fields
		
		// Set item ids into session to avoid recalculation ...
		$session = & JFactory::getSession();
		$session->set($indexer.'_items_to_index', $itemids, 'flexicontent');
		$session->set($indexer.'_items_to_index', $itemids, 'flexicontent');
		
		echo 'success|';
		//echo count($fieldids)*count($itemids).'|';
		echo json_encode($fieldids) .'|'. json_encode($itemids);    // warning: json_encode will output object if given an array with gaps in the indexing
		exit;
	}
	
	
	function index()
	{
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		
		$items_per_call = JRequest::getVar('items_per_call', 50);  // Number of item to index per HTTP request
		$itemcnt = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		
		$itemsmodel = $this->getModel('items');          // Get items model to call needed methods
		$fields   = FlexicontentFields::getSearchFields('id', $indexer);  // Retrieve fields, that are assigned as (advanced/basic) searchable (thus indexable too)
		$fieldids = array_keys($fields);                             // Get the field ids of the searchable fields
		
		// Get item ids from session to avoid recalculation ...
		$session = & JFactory::getSession();
		if ($session->has($indexer.'_items_to_index', 'flexicontent')) {
			$itemids = $session->get($indexer.'_items_to_index', array(),'flexicontent');
		} else {
			$itemids	= & $itemsmodel->getFieldsItems($fieldids);        // Get the items ids that have value for any of the searchable fields
		}

		$db = &JFactory::getDBO();
		for ( $cnt=$itemcnt; $cnt < $itemcnt+$items_per_call; $cnt++ )
		{
			// Check if items have finished, otherwise continue with -current- item
			if ($cnt >= count($itemids)) break; 
			$itemid = $itemids[$cnt];
			
			$item = & JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			$item->load( $itemid );
			if ($indexer == 'basic') $searchindex = '';
			
			// For current item: Loop though all searchable fields according to their type
			foreach($fieldids as $fieldid)
			{
				$field = clone($fields[$fieldid]);
				$field->item_id = $itemid;
				
				// Indicate that the indexing fuction should retrieve the values
				$values = null;
				
				// Add values to advanced search index
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				if ($indexer == 'advanced') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexAdvSearch', array( &$field, &$values, &$item ));
				} else if ($indexer == 'basic') {
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexSearch', array( &$field, &$values, &$item ));
					$searchindex .= @$field->search;
					$searchindex .= @$field->search ? ' | ' : '';
				}
			}
			// search_index was updated
			if ($indexer == 'basic') {
				$item->search_index = $searchindex;
				$item->store();
			}
		}
		echo "success";
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
