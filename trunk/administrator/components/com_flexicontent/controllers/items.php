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
		$this->registerTask( 'add'  ,		 	'edit' );
		$this->registerTask( 'apply', 			'save' );
		$this->registerTask( 'saveandnew', 		'save' );
		$this->registerTask( 'cancel', 			'cancel' );
		$this->registerTask( 'copymove',		'copymove' );
		$this->registerTask( 'restore', 		'restore' );
		$this->registerTask( 'import', 			'import' );
		$this->registerTask( 'bindextdata', 	'bindextdata' );
		$this->registerTask( 'approval', 		'approval' );
		$this->registerTask( 'accesspublic', 	'access' );
		$this->registerTask( 'accessregistered','access' );
		$this->registerTask( 'accessspecial', 	'access' );
		$this->registerTask( 'getversionlist', 'getversionlist');
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

		//Sanitize
		$post = JRequest::get( 'post' );
		$post['text'] = JRequest::getVar( 'text', '', 'post', 'string', JREQUEST_ALLOWRAW );

		$model = $this->getModel('item');

		if ( $model->store($post) ) {

			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=item&cid='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=item';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=items';
					//$model->checkin();
					break;
			}
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );

			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();

		} else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ITEM' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
			$link 	= 'index.php?option=com_flexicontent&view=item';
		}

		$this->setRedirect($link, $msg);
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
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$model = $this->getModel('items');
		$model->move(-1);

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items');
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
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$model = $this->getModel('items');
		$model->move(1);

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items');
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
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$order 	= JRequest::getVar( 'order', array(0), 'post', 'array' );

		$model = $this->getModel('items');
		if(!$model->saveorder($cid, $order)) {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ORDER' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
		} else {
			$msg = JText::_( 'FLEXI_NEW_ORDERING_SAVED' );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', $msg );
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

		$task		= JRequest::getVar('task');
		$model 		= $this->getModel('items');
		$cid 		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$method 	= JRequest::getInt( 'method', 1);
		$keeepcats 	= JRequest::getInt( 'keeepcats', 1 );
		$keeptags 	= JRequest::getInt( 'keeptags', 1 );
		$prefix 	= JRequest::getVar( 'prefix', 1, 'post' );
		$suffix 	= JRequest::getVar( 'suffix', 1, 'post' );
		$copynr 	= JRequest::getInt( 'copynr', 1 );
		$maincat 	= JRequest::getInt( 'maincat', '' );
		$seccats 	= JRequest::getVar( 'seccats', array(0), 'post', 'array' );
		$lang	 	= JRequest::getVar( 'language', '', 'post' );
		$state 		= JRequest::getInt( 'state', '');

		if ($task == 'copymove')
		{
			if ($method == 1) // copy only
			{
				if ( $model->copyitems($cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPY_SUCCESS', count($cid) );

					$cache = &JFactory::getCache('com_flexicontent');
					$cache->clean();
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
				$msg = JText::sprintf( 'FLEXI_ITEMS_MOVE_SUCCESS', count($cid) );
				
				foreach ($cid as $itemid)
				{
					if ( !$model->moveitem($itemid, $maincat, $seccats) )
					{
						$msg = JText::_( 'FLEXI_ERROR_MOVE_ITEMS' );
						JError::raiseWarning( 500, $msg ." " . $model->getError() );
						$msg = '';
					}
				}
				
				$cache = &JFactory::getCache('com_flexicontent');
				$cache->clean();
			}
			else // copy and move
			{
				if ( $model->copyitems($cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPYMOVE_SUCCESS', count($cid) );

					$cache = &JFactory::getCache('com_flexicontent');
					$cache->clean();
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
	
	/*
	This function from http://stackoverflow.com/questions/1269562/how-to-create-an-array-from-a-csv-file-using-php-and-the-fgetcsv-function
	*/
	function csvstring_to_array($string, $separatorChar = ',', $enclosureChar = '"', $newlineChar = "\n") {
		// @author: Klemen Nagode
		$array = array();
		$size = strlen($string);
		$columnIndex = 0;
		$rowIndex = 0;
		$fieldValue="";
		$isEnclosured = false;
		for($i=0; $i<$size;$i++) {

		$char = $string{$i};
		$addChar = "";

		if($isEnclosured) {
		    if($char==$enclosureChar) {

			if($i+1<$size && $string{$i+1}==$enclosureChar){
			    // escaped char
			    $addChar=$char;
			    $i++; // dont check next char
			}else{
			    $isEnclosured = false;
			}
		    }else {
			$addChar=$char;
		    }
		}else {
		    if($char==$enclosureChar) {
			$isEnclosured = true;
		    }else {

			if($char==$separatorChar) {

			    $array[$rowIndex][$columnIndex] = $fieldValue;
			    $fieldValue="";

			    $columnIndex++;
			}elseif($char==$newlineChar) {
			    echo $char;
			    $array[$rowIndex][$columnIndex] = $fieldValue;
			    $fieldValue="";
			    $columnIndex=0;
			    $rowIndex++;
			}else {
			    $addChar=$char;
			}
		    }
		}
		if($addChar!=""){
		    $fieldValue.=$addChar;

		}
		}

		if($fieldValue) { // save last field
		$array[$rowIndex][$columnIndex] = $fieldValue;
		}
		return $array;
		}
	/**
	 * Logic to importcsv of the items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function importcsv() {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		$link 	= 'index.php?option=com_flexicontent&view=items';

		$task		= JRequest::getVar('task');
		$model 		= $this->getModel('item');
		if ($task == 'importcsv') {
			$csvfile = @$_FILES["csvfile"]["tmp_name"];
			if(!is_file($csvfile)) {
				$this->setRedirect($link, "Upload file error!");
				return;
			}
			$contents = $this->csvstring_to_array(file_get_contents($csvfile));
			
			/*require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'DataSource.php');
			$csv = new File_CSV_DataSource;
			$csv->load($csvfile);
			$columns = $csv->getHeaders();*/
			
			/*$fp  = fopen($csvfile, 'r');
			$line = fgetcsv($fp, 0, ',', '"');
			if(count($line)<=0) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 1.");
				return;
			}
			$columns = $this->trimA($line);*/
			if(count($contents[0])<=0) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 1.");
				return;
			}
			$columns = $this->trimA($contents[0]);
			unset($contents[0]);

			if(!in_array('title', $columns)) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 2.");
				return;
			}
			//echo "<xmp>";var_dump($csv->getRows());echo "</xmp>";
			//$rows = $csv->connect();
			
			
			$mainframe = &JFactory::getApplication();
			$maincat 	= JRequest::getInt( 'maincat', '' );
			$seccats 	= JRequest::getVar( 'seccats', array(0), 'post', 'array' );
			if(!$maincat) $maincat = @$seccats[0];
			JRequest::setVar('catid', $maincat);
			JRequest::setVar('cid', $seccats);
			JRequest::setVar('vstate', $seccats);//we must use default value from global configuration[enjoyman]
			JRequest::setVar('state', -4);

			//while (($line = fgetcsv($fp, 0, ',', '"')) !== FALSE) {
			//foreach($rows as $line) {
			foreach($contents as $line) {
				$data = $this->trimA($line);
				foreach($data as $j=>$d) {
					JRequest::setVar($columns[$j], $d);
				}
				/*foreach($line as $k=>$d) {
					JRequest::setVar($k, $d);
				}*/
				JRequest::setVar('id', 0);
				//Sanitize
				$data = JRequest::get( 'request' );
				$data['text'] = JRequest::getVar( 'text', '', 'request', 'string', JREQUEST_ALLOWRAW );
				if(!$model->store($data)) {
					$msg = "Import item '" . implode(",", $line) . "' error" ;
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
				}else{
					$msg = "Import item '" . implode(",", $line) . "' success" ;
					$mainframe->enqueueMessage($msg);
				}
			}
			//fclose($fp);
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
		}		
		//$this->setRedirect($link, $msg);
		$this->setRedirect($link);
	}
	
	function trimA($array) {
		if(!is_array($array)) return false;
		foreach($array as $k=>$a) {
			$array[$k] = trim($a);
		}
		return $array;
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
		
		if ($user->gid < 25) {
			echo JText::_( 'ALERTNOTAUTH' );
			return;
		}
		
		$logs = $model->import();
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
		$msg  = JText::_( 'FLEXI_IMPORT_SUCCESSULL' );
		$msg .= '<ul class="import-ok">';
		$msg .= '<li>' . $logs->sec . ' ' . JText::_( 'FLEXI_IMPORT_SECTIONS' ) . '</li>';
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
		$id 	= JRequest::getInt( 'id', 0 );
		$state 	= JRequest::getVar( 'state', 0 );

		$model = $this->getModel('items');

		if(!$model->setitemstate($id, $state)) 
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
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		echo '<img src="images/'.$img.'" width="16" height="16" border="0" alt="'.$alt.'" />';
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
		$cids	= JRequest::getVar( 'cid', array(), 'post', 'array' );
		$model 	= $this->getModel('items');
		
		$newstate = JRequest::getVar("newstate", '');
		$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => -1 );
		$statenames = array ( 'PE' => 'FLEXI_PENDING', 'OQ' => 'FLEXI_TO_WRITE', 'IP' => 'FLEXI_IN_PROGRESS', 'P' => 'FLEXI_PUBLISHED', 'U' => 'FLEXI_UNPUBLISHED', 'A' => 'FLEXI_ARCHIVED' );
		
		if ( !isset($stateids[$newstate]) ) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'Invalid State' ).": ".$newstate );
		}
		
		if ( !count( $cids ) ) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_NO_ITEMS_SELECTED' ) );
		}		
		if ( is_array( $cids ) && count( $cids ) ) {
			
			foreach ($cids as $item_id) {
				$model->setitemstate($item_id, $stateids[$newstate]);
			}
			$msg = JText::_( 'FLEXI_ITEMS_STATE_CHANGED_TO')." -- ".JText::_( $statenames[$newstate] ) ." --";
		}

		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

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

		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

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
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$model 		= $this->getModel('items');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			if (count($cid) < 2) {
				$msg = JText::_( 'FLEXI_CANNOT_DELETE_ITEM' );
			} else {
				$msg = JText::_( 'FLEXI_CANNOT_DELETE_ITEMS' );
			}
		} else {

			if (!$model->delete($cid)) {
				$msg = '';
				JError::raiseWarning(500, JText::_( 'FLEXI_OPERATION_FAILED' ));
			} else {
				$msg = count($cid).' '.JText::_( 'FLEXI_ITEMS_DELETED' );
				$cache = &JFactory::getCache('com_flexicontent');
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
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id			= (int)$cid[0];
		$task		= JRequest::getVar( 'task' );

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

		$model = $this->getModel('items');
		
		if(!$model->access( $id, $access )) {
			$msg = JText::_( 'FLEXI_ERROR_SETTING_ITEM_ACCESS_LEVEL' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
		} else {
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
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
		$item->bind(JRequest::get('post'));
		$item->checkin();

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
		$this->setRedirect( 'index.php?option=com_flexicontent&controller=items&task=edit&cid[]='.$id, $msg );
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

		$model 	= $this->getModel('item');
		$user	=& JFactory::getUser();

		// Error if no edition rights
		if (!$model->canAdd()) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_NO_ACCESS' ) );
			return;
		}

		if (!$model->canEdit()) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_NO_ACCESS' ) );
			return;
		}

		// Error if checked out by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
			return;
		}

		$model->checkout( $user->get('id') );

		parent::display();
	}

	/**
	 * Method to reset hits
	 * 
	 * @since 1.0
	 */
	function resethits()
	{
		$id		= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('item');

		$model->resetHits($id);
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		echo 0;
	}

	/**
	 * Method to reset votes
	 * 
	 * @since 1.0
	 */
	function resetvotes()
	{
		$id		= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('item');

		$model->resetVotes($id);
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		echo JText::_( 'FLEXI_NOT_RATED_YET' );
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
			$used 	= $model->getUsedtagsArray($id);
		}
		if(!is_array($used)){
			$used = array();
		}
		
		if (FLEXI_ACCESS) {
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
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function viewtags() {
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$user	=& JFactory::getUser();
		if (FLEXI_ACCESS) {
			$CanUseTags = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
		} else {
			$CanUseTags = 1;
		}
		if($CanUseTags) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");
			//header("Content-type:text/json");
			$model 		=  $this->getModel('item');
			$tagobjs 	=  $model->gettags(JRequest::getVar('q'));
			$array = array();
			echo "[";
			foreach($tagobjs as $tag) {
				$array[] = "{\"id\":\"".$tag->id."\",\"name\":\"".$tag->name."\"}";
			}
			echo implode(",", $array);
			echo "]";
			exit;
		}
	}
	

	/**
	 * Method to select new state for many items
	 * 
	 * @since 1.5
	 */
	function selectstate() {
		
		if (FLEXI_ACCESS) {
			$user =& JFactory::getUser();
			$CanPublish 	= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)) : 1;
		} else {
			$CanPublish = 1;
		}
		
		if($CanPublish) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");

			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css">';

			$state['P'] = array( 'name' =>'FLEXI_PUBLISHED', 'desc' =>'FLEXI_PUBLISHED_DESC', 'icon' => 'tick.png', 'color' => 'darkgreen' );
			$state['U'] = array( 'name' =>'FLEXI_UNPUBLISHED', 'desc' =>'FLEXI_UNPUBLISHED_DESC', 'icon' => 'publish_x.png', 'color' => 'darkred' );
			$state['A'] = array( 'name' =>'FLEXI_ARCHIVED', 'desc' =>'FLEXI_ARCHIVED_STATE', 'icon' => 'disabled.png', 'color' => 'gray' );
			$state['IP'] = array( 'name' =>'FLEXI_IN_PROGRESS', 'desc' =>'FLEXI_NOT_FINISHED_YET', 'icon' => 'publish_g.png', 'color' => 'darkgreen' );
			$state['OQ'] = array( 'name' =>'FLEXI_TO_WRITE', 'desc' =>'FLEXI_TO_WRITE_DESC', 'icon' => 'publish_y.png', 'color' => 'darkred' );
			$state['PE'] = array( 'name' =>'FLEXI_PENDING', 'desc' =>'FLEXI_NEED_TO_BE_APROVED', 'icon' => 'publish_r.png', 'color' => 'darkred' );
			
			echo "<b>". JText::_( 'FLEXI_SELECT_STATE' ).":</b><br /><br />";
		?>
			
		<?php
			foreach($state as $shortname => $statedata) {
				$css = "width:28%; margin:0px 1% 12px 1%; padding:1%; color:".$statedata['color'].";";
				$link = JURI::base(true)."/index.php?option=com_flexicontent&task=items.changestate&newstate=".$shortname."&".JUtility::getToken()."=1";
				$icon = "../components/com_flexicontent/assets/images/".$statedata['icon'];
		?>
				<a	style="<?php echo $css; ?>" class="fc_select_button" href="javascript:;"
						onclick="
							window.parent.document.adminForm.newstate.value='<?php echo $shortname; ?>';
							if(window.parent.document.adminForm.boxchecked.value==0)
								alert('<?php echo JText::_('FLEXI_NO_ITEMS_SELECTED'); ?>');
							else
								window.parent.submitbutton('changestate')";
						target="_parent">
					<img src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo JText::_( $statedata['desc'] ); ?>" />
					<?php echo JText::_( $statedata['name'] ); ?>
				</a>
		<?php
			}
		?>
			
		<?php
			exit();
		}
	}
	
	
	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function getorphans()
	{
		$model 		=  $this->getModel('items');
		$status 	=  $model->getExtdataStatus();

		echo count($status['no']);
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
	
	function getversionlist() {
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
		$model->_id = $id;
		$item = $model->getItem(true);
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
		foreach($versions as $v) {
			$class = ($v->nr == $active) ? ' class="active-version"' : '';
			echo "<tr".$class."><td class='versions'>#".$v->nr."</td>
				<td class='versions'>".JHTML::_('date', (($v->nr == 1) ? $item->created : $v->date), JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS' ))."</td>
				<td class='versions'>".(($v->nr == 1) ? $item->creator : $v->modifier)."</td>
				<td class='versions' align='center'><a href='#' class='hasTip' title='Comment::".$v->comment."'>".$comment."</a>";
				if((int)$v->nr==(int)$currentversion) {//is current version?
					echo "<a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&view=item&cid=".$item->id."&version=".$v->nr."\");' href='#'>".JText::_( 'FLEXI_CURRENT' )."</a>";
				}else{
					echo "<a class='modal-versions' href='index.php?option=com_flexicontent&view=itemcompare&cid[]=".$item->id."&version=".$v->nr."&tmpl=component' title='".JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' )."' rel='{handler: \"iframe\", size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}'>".$view."</a><a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&controller=items&task=edit&cid=".$item->id."&version=".$v->nr."&".JUtility::getToken()."=1\");' href='#' title='".JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $v->nr )."'>".$revert;
				}
				echo "</td></tr>";
		}
		exit;
	}
}
