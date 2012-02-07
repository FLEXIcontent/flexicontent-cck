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
class FlexicontentControllerSearch extends FlexicontentController{
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
	function countrows() {
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		//$params =& JComponentHelper::getParams( 'com_flexicontent' );
		//$typeid_for_advsearch = $params->get('typeid_for_advsearch');
		
		@ob_end_clean();
		//if($typeid_for_advsearch) {
			$itemmodel = $this->getModel('items');
			//$fields = & $itemmodel->getAdvSearchFields($typeid_for_advsearch, 'id');
			$fields = & $itemmodel->getAdvSearchFields('id');
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
	function index() {
		@ob_end_clean();
		$items_per_call = JRequest::getVar('items_per_call', 50);
		$itemcnt = JRequest::getVar('itemcnt', 0);
		$itemmodel = $this->getModel('items');
		$fields = & $itemmodel->getAdvSearchFields('id');
		$fieldid_arr = array_keys($fields);
		$itemid_arr	= & $itemmodel->getFieldsItems($fieldid_arr);

		$db = &JFactory::getDBO();
		$fields = array();
		for($cnt=$itemcnt; $cnt < $itemcnt+$items_per_call; $cnt++) {
			if ($cnt >= count($itemid_arr)) break;
			$itemid = $itemid_arr[$cnt];
			foreach($fieldid_arr as $fieldid) {
				if(!isset($fields[$fieldid])) {
					$query = "SELECT * FROM #__flexicontent_fields WHERE id='{$fieldid}' AND published='1' AND isadvsearch='1';";
					$db->setQuery($query);
					if(!$fields[$fieldid] = $db->loadObject()) {
						echo "fail|1";
						exit;
					}
				}
				$field = clone($fields[$fieldid]);
				$field->item_id = $itemid;
				$field->parameters = new JParameter($field->attribs);
				
				if ($field->field_type == 'tags') {
					$query  = 'SELECT `tid` FROM #__flexicontent_tags_item_relations as rel'
						." WHERE rel.itemid='{$itemid}';";
					$db->setQuery($query);
					$values = $db->loadResultArray();
					$values = is_array($values)?$values:array($values);
				} else if ($field->iscore) {
					$query  = 'SELECT * FROM #__content as c'
						." WHERE c.id='{$itemid}';";
					$db->setQuery($query);
					$data = $db->loadObject();
					if ( isset( $data->{$field->name} ) ) {
						$values = $data->{$field->name};
						$values = is_array($values)?$values:array($values);
					} else $values=array();
				} else {
					$query = "SELECT `value` FROM #__flexicontent_fields_item_relations as rel "
						." JOIN #__content as i ON i.id=rel.item_id "
						." WHERE rel.field_id='{$fieldid}' AND rel.item_id='{$itemid}';";
					$db->setQuery($query);
					$values = $db->loadResultArray();
					$values = is_array($values)?$values:array($values);
				}
				$dispatcher =& JDispatcher::getInstance();
				JPluginHelper::importPlugin('flexicontent_fields');
				if(count($values)==1)
					$results = $dispatcher->trigger( 'onIndexAdvSearch', array(&$field, $values[0]));
				elseif(count($values)>1)
					$results = $dispatcher->trigger( 'onIndexAdvSearch', array(&$field, $values));
			}
		}
		echo "success";
		exit;
	}
	
	function purge() {
		$model = $this->getModel('search');
		$model->purge();
		$msg = 'The item(s) were purged.';
		$this->setRedirect('index.php?option=com_flexicontent&view=search', $msg);
	}
}
