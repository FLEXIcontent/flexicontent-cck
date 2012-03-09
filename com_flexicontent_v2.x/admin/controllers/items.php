<?php
/**
 * @version 1.5 stable $Id: items.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
class FlexicontentControllerItems extends JController
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
		$data	= JRequest::getVar('jform', array(), 'post', 'array');

		$model = $this->getModel('item');
		$form 	= $model->getForm($data, false);

		//$validData = & $data;
		$validData = & $model->validate($form, $data);
		
		//$diff_arr = array_diff_assoc ( $data, $validData);
		//echo "<pre>"; print_r($diff_arr); exit();
		
		if ( $model->store($validData) ) {
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=item&cid='.(int) $model->getId();
					break;

				case 'saveandnew' :
					if(isset($validData['type_id']))
						$link = 'index.php?option=com_flexicontent&view=item&typeid='.$validData['type_id'];
					else
						$link = 'index.php?option=com_flexicontent&view=items';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=items';
					//$model->checkin();
					break;
			}
			//$model->checkin();
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );

			//$cache = &JFactory::getCache('com_flexicontent');
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');

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

					//$cache = &JFactory::getCache('com_flexicontent');
					$cache = FLEXIUtilities::getCache();
					$cache->clean('com_flexicontent_items');
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
				
				//$cache = &JFactory::getCache('com_flexicontent');
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
			}
			else // copy and move
			{
				if ( $model->copyitems($cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPYMOVE_SUCCESS', count($cid) );

					//$cache = &JFactory::getCache('com_flexicontent');
					$cache = FLEXIUtilities::getCache();
					$cache->clean('com_flexicontent_items');
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
		
		$permission = FlexicontentHelperPerm::getPerm();
		if(!$permission->CanConfig) {
			echo JText::_( 'ALERTNOTAUTH' );
			return;
		}
		
		$logs = $model->import();
		//$catscache 	=& JFactory::getCache('com_flexicontent_cats');
		//$catscache->clean();
		$catscache = FLEXIUtilities::getCache();
		$catscache->clean('com_flexicontent_cats');
		$msg  = JText::_( 'FLEXI_IMPORT_SUCCESSULL' );
		$msg .= '<ul class="import-ok">';
		//$msg .= '<li>' . $logs->sec . ' ' . JText::_( 'FLEXI_IMPORT_SECTIONS' ) . '</li>';
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
		@ob_end_clean();
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
		
		//$cache = &JFactory::getCache('com_flexicontent');
		$cache = FLEXIUtilities::getCache();
		$cache->clean('com_flexicontent_items');
		$path = JURI::root().'components/com_flexicontent/assets/images/';
		echo '<img src="'.$path.$img.'" width="16" height="16" border="0" alt="'.$alt.'" />';
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

		//$cache = &JFactory::getCache('com_flexicontent');
		$cache = FLEXIUtilities::getCache();
		$cache->clean('com_flexicontent_items');

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

		//$cache = &JFactory::getCache('com_flexicontent');
		$cache = FLEXIUtilities::getCache();
		$cache->clean('com_flexicontent_items');

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
				//$cache = &JFactory::getCache('com_flexicontent');
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
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
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$id];

		$model = $this->getModel('items');
		
		if(!$model->saveaccess( $id, $access )) {
			$msg = JText::_( 'FLEXI_ERROR_SETTING_ITEM_ACCESS_LEVEL' );
			JError::raiseWarning( 500, $msg ." " . $model->getError() );
			$msg = '';
		} else {
			//$cache = &JFactory::getCache('com_flexicontent');
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
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
		//$item->bind(JRequest::get('post'));
		$post = JRequest::get('post');
		$item->checkin(@$post['jform']['id']);

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
		$this->setRedirect( 'index.php?option=com_flexicontent&task=items.edit&cid[]='.$id, $msg );
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
		$cid 	= JRequest::getVar( 'cid', array(0) );
		$itemid = $cid[0];
		$isnew = !$itemid;

		// Check if user can create in at least one category
		if ($isnew && !$model->getItemAccess()->get('access-create')) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			return;
		}

		// Check if user can edit current item
		if (!$isnew && !$model->getItemAccess()->get('access-edit')) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_NO_ACCESS_EDIT' ) );
			return;
		}

		// Check if item is checked by other editor
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
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
			$used 	= $model->getUsedtagsIds($id);
		}
		if(!is_array($used)){
			$used = array();
		}
		$permission = FlexicontentHelperPerm::getPerm();
		$CanNewTags = (!$permission->CanConfig) ? $permission->CanNewTags : 1;
		$CanUseTags = (!$permission->CanConfig) ? $permission->CanUseTags : 1;

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
		$model->_id = $id;
		$item = $model->getItem($id);
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
		
		$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE') ? "d/M H:i" : $date_format;
		foreach($versions as $v) {
			$class = ($v->nr == $active) ? ' class="active-version"' : '';
			echo "<tr".$class."><td class='versions'>#".$v->nr."</td>
				<td class='versions'>".JHTML::_('date', (($v->nr == 1) ? $item->created : $v->date), JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' ))."</td>
				<td class='versions'>".(($v->nr == 1) ? $item->creator : $v->modifier)."</td>
				<td class='versions' align='center'><a href='#' class='hasTip' title='Comment::".$v->comment."'>".$comment."</a>";
				if((int)$v->nr==(int)$currentversion) {//is current version?
					echo "<a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&view=item&cid=".$item->id."&version=".$v->nr."\");' href='#'>".JText::_( 'FLEXI_CURRENT' )."</a>";
				}else{
					echo "<a class='modal-versions' href='index.php?option=com_flexicontent&view=itemcompare&cid[]=".$item->id."&version=".$v->nr."&tmpl=component' title='".JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' )."' rel='{handler: \"iframe\", size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}'>".$view."</a><a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&task=items.edit&cid=".$item->id."&version=".$v->nr."&".JUtility::getToken()."=1\");' href='#' title='".JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $v->nr )."'>".$revert;
				}
				echo "</td></tr>";
		}
		exit;
	}
}
