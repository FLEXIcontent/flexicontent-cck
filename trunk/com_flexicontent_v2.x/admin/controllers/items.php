<?php
/**
 * @version 1.5 stable $Id: items.php 1249 2012-04-16 01:21:37Z ggppdk $
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
 * FLEXIcontent Component Item Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerItems extends FlexicontentController
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
		$this->registerTask( 'add',					'edit' );
		$this->registerTask( 'apply', 			'save' );
		$this->registerTask( 'saveandnew', 	'save' );
		$this->registerTask( 'cancel', 			'cancel' );
		$this->registerTask( 'copymove',		'copymove' );
		$this->registerTask( 'restore', 		'restore' );
		$this->registerTask( 'import', 			'import' );
		$this->registerTask( 'bindextdata', 		'bindextdata' );
		$this->registerTask( 'approval', 				'approval' );
		$this->registerTask( 'getversionlist',	'getversionlist');
		if (!FLEXI_J16GE) {
			$this->registerTask( 'accesspublic',		'access' );
			$this->registerTask( 'accessregistered','access' );
			$this->registerTask( 'accessspecial',		'access' );
		}
	}
	
	
	/**
	 * Logic to save an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$task	= JRequest::getVar('task');
		$user = & JFactory::getUser();
		$model = $this->getModel('item');
		
		// Get data from request and validate them
		if (FLEXI_J16GE) {
			// Retrieve form data these are subject to basic filtering
			$data   = JRequest::getVar('jform', array(), 'post', 'array');    // Core Fields and and item Parameters
			$custom = JRequest::getVar('custom', array(), 'post', 'array');   // Custom Fields
			$jfdata = JRequest::getVar('jfdata', array(), 'post', 'array');   // Joomfish Data
			
			// Validate Form data for core fields and for parameters
			$form = $model->getForm($data, false);
			$post = & $model->validate($form, $data);
			if (!$post) JError::raiseWarning( 500, "Error while validating data: " . $model->getError() );
			
			// Some values need to be assigned after validation
			$post['attribs'] = @ $data['attribs'];   // Workaround for item's template parameters being clear by validation since they are not present in item.xml
			$post['custom']  = & $custom;            // Assign array of custom field values, they are in the custom form array instead of jform
			$post['jfdata']  = & $jfdata;            // Assign array of Joomfish field values, they are in the jfdata form array instead of jform
		} else {
			// Retrieve form data these are subject to basic filtering
			$post = JRequest::get( 'post' );  // Core & Custom Fields and item Parameters
			
			// Some values need to be assigned after validation
			$post['text'] = JRequest::getVar( 'text', '', 'post', 'string', JREQUEST_ALLOWRAW ); // Workaround for allowing raw text field
		}
		
		// USEFULL FOR DEBUGING for J2.5 (do not remove commented code)
		//$diff_arr = array_diff_assoc ( $data, $post);
		//echo "<pre>"; print_r($diff_arr); exit();
		
		// PERFORM ACCESS CHECKS, NOTE: we need to check access again,
		// despite having checked them on edit form load, because user may have tampered with the form ... 
		$itemid = @$post['id'];
		$isnew  = !$itemid;
		if (FLEXI_J16GE) {
			JRequest::setVar( 'cid', array($itemid), 'post', 'array' );
		}
		
		$canAdd  = !FLEXI_J16GE ? $model->canAdd()  : $model->getItemAccess()->get('access-create');
		$canEdit = !FLEXI_J16GE ? $model->canEdit() : $model->getItemAccess()->get('access-edit');

		// New item: check if user can create in at least one category
		if ($isnew && !$canAdd) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '' );
			return;
		}

		// Existing item: Check if user can edit current item
		if (!$isnew && !$canEdit) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_EDIT' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '' );
			return;
		}

		if ( $model->store($post) ) {
			
			switch ($task)
			{
				case 'apply' :
					$ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit' ;
					$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'&cid='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					if(isset($post['type_id']))
						$link = 'index.php?option=com_flexicontent&view=item&typeid='.$post['type_id'];
					else
						$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW;
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=items';
					//$model->checkin();
					break;
			}
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );

			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
			} else {
				$cache = &JFactory::getCache('com_flexicontent_items');
				$cache->clean();
			}

		} else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ITEM' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
			$link 	= 'index.php?option=com_flexicontent&view=item';
		}

		$this->setRedirect($link, $msg);
	}


	/**
	 * Logic to order up/down an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function reorder($dir=null)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get variables: model, user, item id
		$model = $this->getModel('items');
		$user  =& JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		
		// calculate access
		if (FLEXI_J16GE) {
			$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		} else {
			$canOrder = $user->gid < 25 ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
		}
		
		// check access
		if ( !$canOrder ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if ( $model->move($dir) ){
			// success
		} else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ORDER' );
			JError::raiseWarning( 500, $model->getError() );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items');
	}
	
	
	/**
	 * Logic to orderup an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function orderup()
	{
		$this->reorder($dir=-1);
	}

	/**
	 * Logic to orderdown an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function orderdown()
	{
		$this->reorder($dir=1);
	}
	
	
	/**
	 * Logic to mass ordering items
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get variables: model, user, item id, new ordering
		$model = $this->getModel('items');
		$user  =& JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$order = JRequest::getVar( 'order', array(0), 'post', 'array' );
		
		// calculate access
		if (FLEXI_J16GE) {
			$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		} else {
			$canOrder = $user->gid < 25 ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
		}
		
		// check access
		if ( !$canOrder ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if (!$model->saveorder($cid, $order)) {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ORDER' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
		} else {
			$msg = JText::_( 'FLEXI_NEW_ORDERING_SAVED' );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', $msg );
	}


	/**
	 * Logic to display form for copy/move items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy()
	{
		$db   = & JFactory::getDBO();
		$user = & JFactory::getUser();
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		
		if (FLEXI_J16GE) {
			$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');
		} else if (FLEXI_ACCESS) {
			$canCopy = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1;
		} else {
			$canCopy = 1;
		}
		
		// check access of copy task
		if ( !$canCopy ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=items');
			return false;
		}
		
		// Access check
		$copytask_allow_uneditable = JComponentHelper::getParams( 'com_flexicontent' )->get('copytask_allow_uneditable', 1);
		if (!$copytask_allow_uneditable) {
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
				
			// Check authorization for edit operation
			foreach ($cid as $id) {
				if (FLEXI_J16GE) {
					$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $itemdata[$id]->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn = in_array('edit.own', $rights) && $itemdata[$id]->created_by == $user->id;
				} else if (!FLEXI_ACCESS || $user->gid > 24) {
					$canEdit = $canEditOwn = true;
				} else if (FLEXI_ACCESS) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('editown', $rights) && $itemdata[$id]->created_by == $user->id;
				}
					
				if ( $canEdit || $canEditOwn ) {
					$auth_cid[] = $id;
				} else {
					$non_auth_cid[] = $id;
				}
			}
			//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		} else {
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}
		
		// Set warning for uneditable items
		if (count($non_auth_cid)) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_COPY_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_EDIT_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
			if ( !count($auth_cid) ) {  // Cancel task if no items can be copied
				$this->setRedirect('index.php?option=com_flexicontent&view=items');
				return false;
			}
		}
		
		// Set only authenticated item ids, to be used by the parent display method ...
		$cid = JRequest::setVar( 'cid', $auth_cid, 'post', 'array' );
		
		// display the form of the task
		parent::display();
	}
	
	/**
	 * Logic to copy/move the items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copymove()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$db = & JFactory::getDBO();
		$task		= JRequest::getVar('task');
		$model 		= $this->getModel('items');
		$user  =& JFactory::getUser();
		$cid 		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$method 	= JRequest::getInt( 'method', 1);
		$keeepcats 	= JRequest::getInt( 'keeepcats', 1 );
		$keeptags 	= JRequest::getInt( 'keeptags', 1 );
		$prefix 	= JRequest::getVar( 'prefix', 1, 'post' );
		$suffix 	= JRequest::getVar( 'suffix', 1, 'post' );
		$copynr 	= JRequest::getInt( 'copynr', 1 );
		$maincat 	= JRequest::getInt( 'maincat', '' );
		$seccats 	= JRequest::getVar( 'seccats', array(), 'post', 'array' );
		$keepseccats = JRequest::getVar( 'keepseccats', 0, 'post', 'int' );
		$lang	 	= JRequest::getVar( 'language', '', 'post' );
		$state 		= JRequest::getInt( 'state', '');
		
		// Set $seccats to --null-- to indicate that we will maintain secondary categories
		$seccats = $keepseccats ? null : $seccats;
		
		if (FLEXI_J16GE) {
			$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');
		} else if (FLEXI_ACCESS) {
			$canCopy = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1;
		} else {
			$canCopy = 1;
		}
		
		// check access of copy task
		if ( !$canCopy ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=items');
			return false;
		}
		
		// Access check
		$copytask_allow_uneditable = JComponentHelper::getParams( 'com_flexicontent' )->get('copytask_allow_uneditable', 1);
		if (!$copytask_allow_uneditable) {
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
				
			// Check authorization for edit operation
			foreach ($cid as $id) {
				if (FLEXI_J16GE) {
					$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $itemdata[$id]->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn = in_array('edit.own', $rights) && $itemdata[$id]->created_by == $user->id;
				} else if (!FLEXI_ACCESS || $user->gid > 24) {
					$canEdit = $canEditOwn = true;
				} else if (FLEXI_ACCESS) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('editown', $rights) && $itemdata[$id]->created_by == $user->id;
				}
					
				if ( $canEdit || $canEditOwn ) {
					$auth_cid[] = $id;
				} else {
					$non_auth_cid[] = $id;
				}
			}
			//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		} else {
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}
		
		// Set warning for uneditable items
		if (count($non_auth_cid)) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_COPY_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_EDIT_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
			if ( !count($auth_cid) ) {  // Cancel task if no items can be copied
				$this->setRedirect('index.php?option=com_flexicontent&view=items');
				return false;
			}
		}
		
		// Set only authenticated item ids for the copyitems() method
		$auth_cid = $cid;
		
		// Try to copy/move items
		if ($task == 'copymove')
		{
			if ($method == 1) // copy only
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPY_SUCCESS', count($auth_cid) );

					if (FLEXI_J16GE) {
						$cache = FLEXIUtilities::getCache();
						$cache->clean('com_flexicontent_items');
					} else {
						$cache = &JFactory::getCache('com_flexicontent_items');
						$cache->clean();
					}
				}
				else
				{
					$msg = JText::_( 'FLEXI_ERROR_COPY_ITEMS' );
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
					$msg = '';
				}
			}
			else if ($method == 2) // move only
			{
				$msg = JText::sprintf( 'FLEXI_ITEMS_MOVE_SUCCESS', count($auth_cid) );
				
				foreach ($auth_cid as $itemid)
				{
					if ( !$model->moveitem($itemid, $maincat, $seccats) )
					{
						$msg = JText::_( 'FLEXI_ERROR_MOVE_ITEMS' );
						JError::raiseWarning( 500, $msg ." " . $model->getError() );
						$msg = '';
					}
				}
				
				if (FLEXI_J16GE) {
					$cache = FLEXIUtilities::getCache();
					$cache->clean('com_flexicontent_items');
				} else {
					$cache = &JFactory::getCache('com_flexicontent_items');
					$cache->clean();
				}
			}
			else // copy and move
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPYMOVE_SUCCESS', count($auth_cid) );

					if (FLEXI_J16GE) {
						$cache = FLEXIUtilities::getCache();
						$cache->clean('com_flexicontent_items');
					} else {
						$cache = &JFactory::getCache('com_flexicontent_items');
						$cache->clean();
					}
				}
				else
				{
					$msg = JText::_( 'FLEXI_ERROR_COPYMOVE_ITEMS' );
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
					$msg = '';
				}
			}
			$link 	= 'index.php?option=com_flexicontent&view=items';
		}
		
		$this->setRedirect($link, $msg);
	}
	
	
	/**
	 * Logic to importcsv of the items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function importcsv()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		$link 	= 'index.php?option=com_flexicontent&view=items';

		$task		= JRequest::getVar('task');
		$model 		= $this->getModel('item');
		if ($task == 'importcsv')
		{
			// Retrieve form uploaded CSV file
			$csvfile = @$_FILES["csvfile"]["tmp_name"];
			if(!is_file($csvfile)) {
				$this->setRedirect($link, "Upload file error!");
				return;
			}
			
			// Read and parse the file
			$contents = flexicontent_html::csvstring_to_array(file_get_contents($csvfile));
			
			// alternative way of reading / parsing SCV data ...
			/*require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'DataSource.php');
			$csv = new File_CSV_DataSource;
			$csv->load($csvfile);
			$columns = $csv->getHeaders();
			
			$fp  = fopen($csvfile, 'r');
			$line = fgetcsv($fp, 0, ',', '"');
			if(count($line)<=0) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 1.");
				return;
			}
			$columns = flexicontent_html::arrayTrim($line);*/
			
			// Basic error checking, for empty data
			if(count($contents[0])<=0) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 1.");
				return;
			}
			
			// Get field names (from the header line (row 0), and remove it form the data array
			$columns = flexicontent_html::arrayTrim($contents[0]);
			unset($contents[0]);
			
			// Check for the (required) title column
			if(!in_array('title', $columns)) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 2.");
				return;
			}
			
			//echo "<xmp>"; var_dump($csv->getRows()); echo "</xmp>";
			//$rows = $csv->connect();
			
			// Retrieve from configuration for (a) main category, (b) secondaries categories
			$mainframe = &JFactory::getApplication();
			$maincat 	= JRequest::getInt( 'maincat', '' );
			$seccats 	= JRequest::getVar( 'seccats', array(0), 'post', 'array' );
			if(!$maincat) $maincat = @$seccats[0];
			$vstate = $mainframe->get("auto_approve") ? 2 : 1;
			
			// Prepare request variable used by the item's Model
			JRequest::setVar('catid', $maincat);
			JRequest::setVar('cid', $seccats);
			JRequest::setVar('vstate', $vstate );
			JRequest::setVar('state', -4);

			//while (($line = fgetcsv($fp, 0, ',', '"')) !== FALSE) {
			//foreach($rows as $line) {
			
			// New item insertion LOOP (use's model's store() function to create the items)
			$cnt = 1;
			foreach($contents as $line)
			{
				// Trim item's data and set every field value as JRequest data ...
				$data = flexicontent_html::arrayTrim($line);
				foreach($data as $j=>$d) {
					JRequest::setVar($columns[$j], $d);
				}
				// foreach($line as $k=>$d) JRequest::setVar($k, $d);
				
				// Set/Force id to zero to indicate creation of new item
				JRequest::setVar('id', 0);
				
				// Sanitize data by retrieving them through JRequest ???
				$data = JRequest::get( 'request' );
				$data['text'] = JRequest::getVar( 'text', '', 'request', 'string', JREQUEST_ALLOWRAW );
				
				// Finally try to create the item by using Item Model's store() method
				if( !$model->store($data) ) {
					$msg = $cnt . ". Import item with title: '" . $line['title'] . "' error" ;
					//$msg = "Import item '" . implode(",", $line) . "' error" ;
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
				} else {
					$msg = $cnt . ". Import item with title: '" . $line['title'] . "' success" ;
					//$msg = "Import item '" . implode(",", $line) . "' success" ;
					$mainframe->enqueueMessage($msg);
				}
				$cnt++;
			}
			//fclose($fp);
			
			// Clean item's cache, but is this needed when adding items ?
			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
			} else {
				$cache = &JFactory::getCache('com_flexicontent_items');
				$cache->clean();
			}
		}
		
		$this->setRedirect($link);
	}


	/**
	 * Import Joomla com_content datas
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function import()
	{		
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$user	=& JFactory::getUser();
		$model 	= $this->getModel('items');
		
		if (!FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$canImport = $permission->CanConfig;
		} else if ($user->gid < 25) {
			$canImport = 1;
		} else {
			$canImport = 0;
		}
		
		if(!$canImport) {
			echo JText::_( 'ALERTNOTAUTH' );
			return;
		}
		
		$logs = $model->import();
		if (!FLEXI_J16GE) {
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		} else {
			$catscache = FLEXIUtilities::getCache();
			$catscache->clean('com_flexicontent_cats');
		}
		$msg  = JText::_( 'FLEXI_IMPORT_SUCCESSULL' );
		$msg .= '<ul class="import-ok">';
		if (!FLEXI_J16GE) {
			$msg .= '<li>' . $logs->sec . ' ' . JText::_( 'FLEXI_IMPORT_SECTIONS' ) . '</li>';
		}
		$msg .= '<li>' . $logs->cat . ' ' . JText::_( 'FLEXI_IMPORT_CATEGORIES' ) . '</li>';
		$msg .= '<li>' . $logs->art . ' ' . JText::_( 'FLEXI_IMPORT_ARTICLES' ) . '</li>';
		$msg .= '</ul>';

		if (isset($logs->err)) {
			$msg .= JText::_( 'FLEXI_IMPORT_FAILED' );
			$msg .= '<ul class="import-failed">';
			foreach ($logs->err as $err) {
				$msg .= '<li>' . $err->type . ' ' . $err->id . ': ' . $err->title . '</li>';
			}
			$msg .= '</ul>';
		} else {
			$msg .= JText::_( 'FLEXI_IMPORT_NO_ERROR' );		
		}
    
		$msg .= '<p class="button-close"><input type="button" class="button" onclick="window.parent.document.adminForm.submit();" value="'.JText::_( 'FLEXI_CLOSE' ).'" /><p>';

		echo $msg;
	}

	/**
	 * Bind fields, category relations and items_ext data to Joomla! com_content imported articles
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function bindextdata()
	{
		$extdata 	= JRequest::getInt('extdata', '');		
		$model 		= $this->getModel('items');
		$rows 		= $model->getUnassociatedItems($extdata);
		
		echo ($model->addFlexiData($rows));
	}

	/**
	 * Logic to change the state
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		$db    = & JFactory::getDBO();
		$user  = & JFactory::getUser();
		$cid 	= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('items');
		$state = JRequest::getVar( 'state', 0 );
		JRequest::setVar( 'cid', $cid );
		//@ob_end_clean();
		
		// Get owner and other item data
		$q = "SELECT id, created_by, catid FROM #__content WHERE id =".$cid;
		$db->setQuery($q);
		$itemdata = $db->loadObjectList('id');
		
		$id = $cid;
		if (FLEXI_J16GE) {
			$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $itemdata[$id]->id);
			$canPublish 		= in_array('edit.state', $rights);
			$canPublishOwn = in_array('edit.state.own', $rights) && $itemdata[$id]->created_by == $user->id;
		} else if (!FLEXI_ACCESS || $user->gid > 24) {
			$canPublish = $canPublishOwn = true;
		} else if (FLEXI_ACCESS) {
			$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
			$canPublish 		= in_array('publish', $rights);
			$canPublishOwn	= in_array('publishown', $rights) && $itemdata[$id]->created_by == $user->id;
		}
		
		// check if user can edit.state of the item
		$access_msg = '';
		if ( !$canPublish && !$canPublishOwn )
		{
			$access_msg =  JText::_( 'FLEXI_DENIED' );   // must a few words
		}
		else if(!$model->setitemstate($id, $state)) 
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_THE_ITEM_STATE');
			echo $msg . ": " .$model->getError();
			return;
		}

		if ( $state == 1 ) {
			$img = 'tick.png';
			$alt = JText::_( 'FLEXI_PUBLISHED' );
		} else if ( $state == 0 ) {
			$img = 'publish_x.png';
			$alt = JText::_( 'FLEXI_UNPUBLISHED' );
		} else if ( $state == -1 ) {
			$img = 'disabled.png';
			$alt = JText::_( 'FLEXI_ARCHIVED' );
		} else if ( $state == -3 ) {
			$img = 'publish_r.png';
			$alt = JText::_( 'FLEXI_PENDING' );
		} else if ( $state == -4 ) {
			$img = 'publish_y.png';
			$alt = JText::_( 'FLEXI_TO_WRITE' );
		} else if ( $state == -5 ) {
			$img = 'publish_g.png';
			$alt = JText::_( 'FLEXI_IN_PROGRESS' );
		}
		
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$cache = &JFactory::getCache('com_flexicontent_items');
			$cache->clean();
		}

		$path = JURI::root().'components/com_flexicontent/assets/images/';
		echo '<img src="'.$path.$img.'" width="16" height="16" border="0" alt="'.$alt.'" />' . $access_msg;
		exit;
	}


	/**
	 * Logic to change state of multiple items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function changestate()
	{
		$db    = & JFactory::getDBO();
		$user  =& JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(), 'post', 'array' );
		$model = $this->getModel('items');
		$msg = '';
		
		$newstate = JRequest::getVar("newstate", '');
		$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => -1 );
		$statenames = array ( 'PE' => 'FLEXI_PENDING', 'OQ' => 'FLEXI_TO_WRITE', 'IP' => 'FLEXI_IN_PROGRESS', 'P' => 'FLEXI_PUBLISHED', 'U' => 'FLEXI_UNPUBLISHED', 'A' => 'FLEXI_ARCHIVED' );
		
		// check valid state
		if ( !isset($stateids[$newstate]) ) {
			JError::raiseWarning(500, JText::_( 'Invalid State' ).": ".$newstate );
		}
		
		// check at least one item was selected
		if ( !count( $cid ) ) {
			JError::raiseWarning(500, JText::_( 'FLEXI_NO_ITEMS_SELECTED' ) );
		} else {
			// Remove unauthorized (undeletable) items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
			
			// Check authorization for publish operation
			foreach ($cid as $id) {
				if (FLEXI_J16GE) {
					$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $itemdata[$id]->id);
					$canPublish 		= in_array('edit.state', $rights);
					$canPublishOwn = in_array('edit.state.own', $rights) && $itemdata[$id]->created_by == $user->id;
				} else if (!FLEXI_ACCESS || $user->gid > 24) {
					$canPublish = $canPublishOwn = true;
				} else if (FLEXI_ACCESS) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canPublish 		= in_array('publish', $rights);
					$canPublishOwn	= in_array('publishown', $rights) && $itemdata[$id]->created_by == $user->id;
				}
				
				if ( $canPublish || $canPublishOwn ) {
					$auth_cid[] = $id;
				} else {
					$non_auth_cid[] = $id;
				}
			}
		}

		//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		
		// Set warning for undeletable items
		if (count($non_auth_cid)) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_CHANGE_STATE_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_PUBLISH_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
		}
		
		// Try to delete 
		if ( count($auth_cid) ){
			foreach ($auth_cid as $item_id) {
				$model->setitemstate($item_id, $stateids[$newstate]);
			}
			$msg = count($auth_cid) ." ". JText::_('FLEXI_ITEMS') ." : &nbsp; ". JText::_( 'FLEXI_ITEMS_STATE_CHANGED_TO')." -- ".JText::_( $statenames[$newstate] ) ." --";
		}

		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$cache = &JFactory::getCache('com_flexicontent_items');
			$cache->clean();
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', $msg );
	}


	/**
	 * Logic to submit item to approval
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function approval()
	{
		$cid	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$model 	= $this->getModel('items');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_APPROVAL_SELECT_ITEM_SUBMIT' ) );
		}
		
		$cids = $model->isUserDraft($cid);
		
		$excluded = count($cid) - count($cids);
		if ($excluded) {
			$excluded = $excluded . ' ' . JText::_( 'FLEXI_APPROVAL_ITEMS_EXCLUDED' );
		} else {
			$excluded = '';
		}
		
		if (count($cids)) {	
			if (count($cids) < 2) {
				$msg = JText::_( 'FLEXI_APPROVAL_ITEM_SUBMITTED' ) . ' ' . $excluded;

				$model->setitemstate($cids[0]->id, -3);
				
				$validators = $model->getValidators($cids[0]->id, $cids[0]->catid);
				if ($validators) {
					$model->sendNotification($validators, $cids[0]);
				}
			} else {
				$msg = count($cids) . ' ' . JText::_( 'FLEXI_APPROVAL_ITEMS_SUBMITTED' ) . ' ' . $excluded;
				
				foreach ($cids as $cid) {

					$model->setitemstate($cid->id, -3);
	
					$validators = $model->getValidators($cid->id, $cid->catid);
					if ($validators) {
						$model->sendNotification($validators, $cid);
					}
				}
			}
		} else {
			$msg = JText::_( 'FLEXI_APPROVAL_NO_ITEMS_SUBMITTED' ) . ' ' . $excluded;
		}

		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$cache = &JFactory::getCache('com_flexicontent_items');
			$cache->clean();
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', $msg );
	}

	/**
	 * Logic to delete items
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function remove()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$db    = & JFactory::getDBO();
		$user	=& JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$model = $this->getModel('items');
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseNotice(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else {
			// Remove unauthorized (undeletable) items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
			
			// Check authorization for delete operation
			foreach ($cid as $id) {
			
				if (FLEXI_J16GE) {
					$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $itemdata[$id]->id);
					$canDelete 		= in_array('delete', $rights);
					$canDeleteOwn = in_array('delete.own', $rights) && $itemdata[$id]->created_by == $user->id;
				} else if (!FLEXI_ACCESS || $user->gid > 24) {
					$canDelete = $canDeleteOwn = true;
				} else if (FLEXI_ACCESS) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canDelete 		= in_array('delete', $rights);
					$canDeleteOwn	= in_array('deleteown', $rights) && $itemdata[$id]->created_by == $user->id;
				}
				
				if ( $canDelete || $canDeleteOwn ) {
					$auth_cid[] = $id;
				} else {
					$non_auth_cid[] = $id;
				}
			}
		}
		//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		
		// Set warning for undeletable items
		if (count($non_auth_cid)) {
			if (count($non_auth_cid) < 2) {
				$msg_noauth = JText::_( 'FLEXI_CANNOT_DELETE_ITEM' );
			} else {
				$msg_noauth = JText::_( 'FLEXI_CANNOT_DELETE_ITEMS' );
			}
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_DELETE_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
		}
		
		// Try to delete 
		if ( count($auth_cid) && !$model->delete($auth_cid) ) {
			JError::raiseWarning(500, JText::_( 'FLEXI_OPERATION_FAILED' ));
		} else {
			$msg = count($auth_cid).' '.JText::_( 'FLEXI_ITEMS_DELETED' );
			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
			} else {
				$cache = &JFactory::getCache('com_flexicontent_items');
				$cache->clean();
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', $msg );
	}

	/**
	 * Logic to set the access level of the Items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user	=& JFactory::getUser();
		
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id   = (int)$cid[0];
		$task = JRequest::getVar( 'task' );
		
		// Decide / Retrieve new access level
		if (FLEXI_J16GE) {
			$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
			$access = $accesses[$id];
		} else {
			// J1.5 ...
			if ($task == 'accesspublic') {
				$access = 0;
			} elseif ($task == 'accessregistered') {
				$access = 1;
			} else {
				if (FLEXI_ACCESS) {
					$access = 3;
				} else {
					$access = 2;
				}
			}
		}

		$canEdit = !FLEXI_J16GE ? $model->canEdit() : $model->getItemAccess()->get('access-edit');
		
		// Check if user can edit the item
		if ( !$canEdit ) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_CHANGE_ACCLEVEL_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_PUBLISH_PERMISSION' );
		}
		if ($msg_noauth) {
			JError::raiseNotice(500, $msg_noauth);
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}

		$model = $this->getModel('items');
		
		if(!$model->saveaccess( $id, $access )) {
			$msg = JText::_( 'FLEXI_ERROR_SETTING_ITEM_ACCESS_LEVEL' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
		} else {
			if (!FLEXI_J16GE) {
				$cache = &JFactory::getCache('com_flexicontent_items');
				$cache->clean();
			} else {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
			}
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=items' );
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$item = & JTable::getInstance('flexicontent_items', '');
		if (!FLEXI_J16GE) {
			$item->bind(JRequest::get('post'));
			$item->checkin();
		} else {
			$post = JRequest::get('post');
			$item->checkin(@$post['jform']['id']);
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items' );
	}

	/**
	 * logic for restore an old version
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function restore()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$id			= JRequest::getInt( 'id', 0 );
		$version	= JRequest::getVar( 'version', '', 'request', 'int' );
		$model		= $this->getModel('item');

		// First checkin the open item
		$item = & JTable::getInstance('flexicontent_items', '');
		$item->bind(JRequest::get('request'));
		$item->checkin();
		if ($version) {
			$msg = JText::sprintf( 'FLEXI_VERSION_RESTORED', $version );
			$model->restore($version, $id);
		} else {
			$msg = JText::_( 'FLEXI_NOTHING_TO_RESTORE' );
		}
		$ctrlTask  = !FLEXI_J16GE ? 'controller=items&task=edit' : 'task=items.edit';
		$this->setRedirect( 'index.php?option=com_flexicontent&'.$ctrlTask.'&cid[]='.$id, $msg );
	}


	/**
	 * Logic to create the view for the edit item screen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{
		JRequest::setVar( 'view', 'item' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$user	=& JFactory::getUser();
		$model = $this->getModel('item');
		$itemid = $model->getId();
		$isnew = !$itemid;
		
		$canAdd  = !FLEXI_J16GE ? $model->canAdd()  : $model->getItemAccess()->get('access-create');
		$canEdit = !FLEXI_J16GE ? $model->canEdit() : $model->getItemAccess()->get('access-edit');

		// New item: check if user can create in at least one category
		if ($isnew && !$canAdd) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_NO_ACCESS_CREATE' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}

		// Existing item: Check if user can edit current item
		if (!$isnew && !$canEdit) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_NO_ACCESS_EDIT' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}

		// Check if item is checked out by other editor
		if ($model->isCheckedOut( $user->get('id') )) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}
		
		// Checkout the item and proceed to edit form
		$model->checkout( $user->get('id') );

		parent::display();
	}

	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function gettags()
	{
		$id 	=  JRequest::getInt('id', 0);
		$model 	=  $this->getModel('item');
		$tags 	=  $model->gettags();
		$user	=& JFactory::getUser();
		
		$used = null;

		if ($id) {
			$used = $model->getUsedtagsIds($id);
		}
		if(!is_array($used)){
			$used = array();
		}
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanNewTags = $permission->CanNewTags;
			$CanUseTags = $permission->CanUseTags;
		} if (FLEXI_ACCESS) {
			$CanNewTags = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'newtags', 'users', $user->gmid) : 1;
			$CanUseTags = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
		} else {
			$CanNewTags = 1;
			$CanUseTags = 1;
		}

		$CanUseTags = $CanUseTags ? '' : ' disabled="disabled"';
		$n = count($tags);
		$rsp = '';
		if ($n>0) {
			$rsp .= '<div class="qf_tagbox">';
			$rsp .= '<ul>';
			for( $i = 0, $n; $i < $n; $i++ ){
				$tag = $tags[$i];
				$rsp .=  '<li><div><span class="qf_tagidbox"><input type="checkbox" name="tag[]" value="'.$tag->id.'"' . (in_array($tag->id, $used) ? 'checked="checked"' : '') . $CanUseTags . ' /></span>'.$tag->name.'</div></li>';
				if ($CanUseTags && in_array($tag->id, $used)){
					$rsp .= '<input type="hidden" name="tag[]" value="'.$tag->id.'" />';
				}
			}
			$rsp .= '</ul>';
			$rsp .= '</div>';
			$rsp .= '<div class="clear"></div>';
			}
		if ($CanNewTags)
		{
			$rsp .= '<div class="qf_addtag">';
			$rsp .= '<label for="addtags">'.JText::_( 'FLEXI_ADD_TAG' ).'</label>';
			$rsp .= '<input type="text" id="tagname" class="inputbox" size="30" />';
			$rsp .=	'<input type="button" class="button" value="'.JText::_( 'FLEXI_ADD' ).'" onclick="addtag()" />';
			$rsp .= '</div>';
		}
		echo $rsp;
	}
	

	/**
	 * Method to fetch the votes
	 * 
	 * @since 1.5
	 */
	function getvotes()
	{
		$id 	= JRequest::getInt('id', 0);
		$model 	= $this->getModel('item');
		$votes 	= $model->getvotes($id);
		
		@ob_end_clean();
		if ($votes) {
			$score	= round((((int)$votes[0]->rating_sum / (int)$votes[0]->rating_count) * 20), 2);
			$vote	= ((int)$votes[0]->rating_count > 1) ? (int)$votes[0]->rating_count . ' ' . JText::_( 'FLEXI_VOTES' ) : (int)$votes[0]->rating_count . ' ' . JText::_( 'FLEXI_VOTE' );
			echo $score.'% | '.$vote;
		} else {
			echo JText::_( 'FLEXI_NOT_RATED_YET' );
		}
		exit;
	}

	/**
	 * Method to get hits
	 * 
	 * @since 1.0
	 */
	function gethits()
	{
		$id 	= JRequest::getInt('id', 0);
		$model 	= $this->getModel('item');

		@ob_end_clean();
		$hits 	= $model->gethits($id);

		if ($hits) {
			echo $hits;
		} else {
			echo 0;
		}
		exit;
	}
	
	function getversionlist()
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );
		@ob_end_clean();
		$id 		= JRequest::getInt('id', 0);
		$active 	= JRequest::getInt('active', 0);
		if(!$id) return;
		$revert 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
		$view 		= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_( 'FLEXI_VIEW' ) );
		$comment 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_COMMENT' ) );

		$model 	= $this->getModel('item');
		$model->setId($id);
		$item = $model->getItem( $id );
		
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$currentversion = $item->version;
		$page=JRequest::getInt('page', 0);
		$versioncount = $model->getVersionCount();
		$numpage = ceil($versioncount/$versionsperpage);
		if($page>$numpage) $page = $numpage;
		elseif($page<1) $page = 1;
		$limitstart = ($page-1)*$versionsperpage;
		$versions = $model->getVersionList();
		$versions	= & $model->getVersionList($limitstart, $versionsperpage);
		
		$jt_date_format = FLEXI_J16GE ? 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' : 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS';
		$df_date_format = FLEXI_J16GE ? "d/M H:i" : "%d/%m %H:%M" ;
		$date_format = JText::_( $jt_date_format );
		$date_format = ( $date_format == $jt_date_format ) ? $df_date_format : $date_format;
		$ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
		foreach($versions as $v) {
			$class = ($v->nr == $active) ? ' class="active-version"' : '';
			echo "<tr".$class."><td class='versions'>#".$v->nr."</td>
				<td class='versions'>".JHTML::_('date', (($v->nr == 1) ? $item->created : $v->date), $date_format )."</td>
				<td class='versions'>".(($v->nr == 1) ? $item->creator : $v->modifier)."</td>
				<td class='versions' align='center'><a href='#' class='hasTip' title='Comment::".$v->comment."'>".$comment."</a>";
				if((int)$v->nr==(int)$currentversion) {//is current version?
					echo "<a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&".$ctrl_task."&cid=".$item->id."&version=".$v->nr."\");' href='#'>".JText::_( 'FLEXI_CURRENT' )."</a>";
				}else{
					echo "<a class='modal-versions' href='index.php?option=com_flexicontent&view=itemcompare&cid[]=".$item->id."&version=".$v->nr."&tmpl=component' title='".JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' )."' rel='{handler: \"iframe\", size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}'>".$view."</a><a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&".$ctrl_task."&cid=".$item->id."&version=".$v->nr."&".JUtility::getToken()."=1\");' href='#' title='".JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $v->nr )."'>".$revert;
				}
				echo "</td></tr>";
		}
		exit;
	}
	
	
}
