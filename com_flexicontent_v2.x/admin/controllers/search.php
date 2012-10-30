<?php
/**
 * @version 1.5 stable $Id: search.php 1193 2012-03-14 09:20:15Z emmanuel.danan@gmail.com $ 
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
		//$typeid_for_advsearch = $params->get('typeid_for_advsearch');
		
		@ob_end_clean();
		//if($typeid_for_advsearch) {
			$indexer = JRequest::getVar('indexer','advanced');
			
			$itemmodel = $this->getModel('items');
			//$fields = & $itemmodel->getAdvSearchFields($typeid_for_advsearch, 'id');
			$fields = & $itemmodel->getSearchFields('id', $indexer);
			$keys = array_keys($fields);
			//$items	= & $itemmodel->getFieldsItems($keys, $typeid_for_advsearch);
			$items	= & $itemmodel->getFieldsItems($keys);
			echo 'success|';
			//echo $typeid_for_advsearch.'|';
			//echo count($keys)*count($items).'|';
			echo json_encode($keys).'|';
			echo json_encode($items);    // warning: json_encode will output object if given an array with gaps in the indexing
		//}else{
		//	echo 'fail|0';
		//}
		exit;
	}
	
	
	function index()
	{
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','advanced');
		
		$items_per_call = JRequest::getVar('items_per_call', 50);  // Number of item to index per HTTP request
		$itemcnt = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		$itemmodel = $this->getModel('items');
		$fields = & $itemmodel->getSearchFields('id', $indexer);          // Retrieve fields, that are assigned as (advanced) searchable
		$fieldid_arr = array_keys($fields);                        // Get the field ids of the (advanced) searchable fields
		$itemid_arr	= & $itemmodel->getFieldsItems($fieldid_arr);  // Get the items ids that have value for any of the searchable fields

		$db = &JFactory::getDBO();
		$fields = array();
		for ( $cnt=$itemcnt; $cnt < $itemcnt+$items_per_call; $cnt++ )
		{
			// Check if items have finished, otherwise continue with -current- item
			if ($cnt >= count($itemid_arr)) break; 
			$itemid = $itemid_arr[$cnt];
			
			$item = & JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			$item->load( $itemid );
			if ($indexer == 'basic') $searchindex = '';
			
			// For current item: Loop though all searchable fields according to their type
			foreach($fieldid_arr as $fieldid)
			{
				if(!isset($fields[$fieldid])) {
					$query = 'SELECT * FROM #__flexicontent_fields WHERE id='.$fieldid.' AND published=1 AND '.($indexer=='advanced' ? 'isadvsearch' : 'issearch').'=1';
					$db->setQuery($query);
					if(!$fields[$fieldid] = $db->loadObject()) {
						echo "fail|1";
						exit;
					}
				}
				$field = clone($fields[$fieldid]);
				$field->item_id = $itemid;
				$field->parameters = new JParameter($field->attribs);
				
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
