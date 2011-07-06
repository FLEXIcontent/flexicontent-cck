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
		$params =& JComponentHelper::getParams( 'com_flexicontent' );
		$typeid_for_advsearch = $params->get('typeid_for_advsearch');
		
		@ob_end_clean();
		if($typeid_for_advsearch) {
			$itemmodel = $this->getModel('items');
			$fields = & $itemmodel->getAdvSearchFields($typeid_for_advsearch, 'id');
			$keys = array_keys($fields);
			$items	= & $itemmodel->getFieldsItems($keys, $typeid_for_advsearch);
			echo 'success|';
			echo $typeid_for_advsearch.'|';
			//echo count($keys)*count($items).'|';
			echo json_encode($keys).'|';
			echo json_encode($items);
		}else{
			echo 'fail|';
		}
		exit;
	}
	function index() {
		@ob_end_clean();
		$fieldid = JRequest::getVar('fieldid', 0);
		$itemid = JRequest::getVar('itemid', 0);
		$db = &JFactory::getDBO();
		$query = "SELECT * FROM #__flexicontent_fields WHERE id='{$fieldid}' AND published='1' AND isadvsearch='1';";
		$db->setQuery($query);
		if(!$field = $db->loadObject()) {
			echo "fail|Cannot index.";
			exit;
		}
		$field->item_id = $itemid;
		$field->parameters = new JParameter($field->attribs);
		$query = "SELECT `value` FROM #__flexicontent_fields_item_relations WHERE field_id='{$fieldid}' AND item_id='{$itemid}';";
		$db->setQuery($query);
		$values = $db->loadResultArray();
		$values = is_array($values)?$values:array();
		$dispatcher =& JDispatcher::getInstance();
		if(count($values)==1)
			$results = $dispatcher->trigger( 'onIndexAdvSearch', array(&$field, $values[0]));
		elseif(count($values)>1)
			$results = $dispatcher->trigger( 'onIndexAdvSearch', array(&$field, $values));
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
