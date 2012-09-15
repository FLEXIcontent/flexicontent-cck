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
		
		// Initialize variables
		$app     = & JFactory::getApplication();
		$db      = & JFactory::getDBO();
		$user    = & JFactory::getUser();
		$config  = & JFactory::getConfig();
		$session = & JFactory::getSession();
		$task	   = JRequest::getVar('task');
		$model   = $this->getModel('item');
		$ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
		
		// Get component parameters and them merge into them the type parameters
		$params  = new JParameter();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);
		
		$tparams = $model->getTypeparams();
		$tparams = new JParameter($tparams);
		$params->merge($tparams);
		
		
		// Get data from request and validate them
		if (FLEXI_J16GE) {
			// Retrieve form data these are subject to basic filtering
			$data   = JRequest::getVar('jform', array(), 'post', 'array');   // Core Fields and and item Parameters
			$custom = JRequest::getVar('custom', array(), 'post', 'array');  // Custom Fields
			$jfdata = JRequest::getVar('jfdata', array(), 'post', 'array');  // Joomfish Data
			
			// Validate Form data for core fields and for parameters
			$form = $model->getForm($data, false);
			$post = & $model->validate($form, $data);
			if (!$post) JError::raiseWarning( 500, "Error while validating data: " . $model->getError() );
			
			// Some values need to be assigned after validation
			$post['attribs'] = @$data['attribs'];  // Workaround for item's template parameters being clear by validation since they are not present in item.xml
			$post['custom']  = & $custom;          // Assign array of custom field values, they are in the 'custom' form array instead of jform
			$post['jfdata']  = & $jfdata;          // Assign array of Joomfish field values, they are in the 'jfdata' form array instead of jform
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
		
		// Check if item is editable till logoff
		if (!$canEdit) {
			if ($session->has('rendered_uneditable', 'flexicontent')) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				if ($canEdit) {
					// Set notice for existing item being editable till logoff 
					JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ) );
				}
			}
		}
		
		
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
		
		
		// Get "BEFORE SAVE" categories for information mail
		$before_cats = array();
		if ( !$isnew )
		{
			$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int) $model->get('id');
			$db->setQuery( $query );
			$before_cats = $db->loadObjectList('id');
			$before_maincat = $model->get('catid');
		}
		
		
		// Store the form data into the item and check it in
		if ( $store_success = $model->store($post) ) {
			if($isnew) {
				$post['id'] = (int) $model->get('id');
			}
		} else {
			// Set error message about saving failed, and also the reason (=model's error message)
			$msg = JText::_( 'FLEXI_ERROR_STORING_ITEM' );
			JError::raiseWarning( 500, $msg .": " . $model->getError() );

			// Since an error occured, check if (a) the item is new and (b) was not created
			if ($isnew && !$model->get('id')) {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'&cid=0&typeid='.$post['type_id'];
				$this->setRedirect($link, $msg);
			} else {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'&cid='.$model->get('id');
				$this->setRedirect($link, $msg);
			}
		}
		
		
		// Check in model and return if saving has 
		$model->checkin();
		if ( !$store_success ) return;
		
		
		// Actions if a content item was created
		if ( $model->get('id') )
		{
			// ********************************************************************************************
			// Use session to detect multiple item saves to avoid sending notification EMAIL multiple times
			// ********************************************************************************************
			$is_first_save = true;
			if ($session->has('saved_fcitems', 'flexicontent')) {
				$saved_fcitems = $session->get('saved_fcitems', array(), 'flexicontent');
				$is_first_save = $isnew ? true : !isset($saved_fcitems[$model->get('id')]);
			}
			// Add item to saved items of the corresponding session array
			$saved_fcitems[$model->get('id')] = $timestamp = time();  // Current time as seconds since Unix epoc;
			$session->set('saved_fcitems', $saved_fcitems, 'flexicontent');
			
			
			// ********************************************
			// Get categories added / removed from the item
			// ********************************************
			$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int) $model->get('id');
			$db->setQuery( $query );
			$after_cats = $db->loadObjectList('id');
			if ( !$isnew ) {
				$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));
				foreach($cats_added_ids as $cats_added_id) {
					$cats_added_titles[] = $after_cats[$cats_added_id]->title;
				}
				
				$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));
				foreach($cats_removed_ids as $cats_removed_id) {
					$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
				}
				$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
				$after_maincat = $model->get('catid');
			}
			
			
			// *******************************************************************************************************************
			// We need to get emails to notify, from Global/item's Content Type parameters -AND- from item's categories parameters
			// *******************************************************************************************************************
			$notify_emails = array();
			if ( $is_first_save || $cats_altered || $params->get('nf_enable_debug',0) )
			{
				// Get needed flags regarding the saved items
				$approve_version = 2;
				$pending_approval_state = -3;
				
				$needs_version_reviewal     = !$isnew && ($post['vstate'] != $approve_version) && !$has_publish;
				$needs_publication_approval =  $isnew && ($post['state'] == $pending_approval_state) && !$canPublish;
				
				// Get notifications configuration and select appropriate emails for current saving case
				$nConf = & $model->getNotificationsConf($params);
				//echo "<pre>"; print_r($nConf);
				
				if ($needs_publication_approval)   $notify_emails = $nConf->emails->notify_new_pending;
				else if ($isnew)                   $notify_emails = $nConf->emails->notify_new;
				else if ($needs_version_reviewal)  $notify_emails = $nConf->emails->notify_existing_reviewal;
				else if (!$isnew)                  $notify_emails = $nConf->emails->notify_existing;
				
				if ($needs_publication_approval)   $notify_text = $params->get('text_notify_new_pending');
				else if ($isnew)                   $notify_text = $params->get('text_notify_new');
				else if ($needs_version_reviewal)  $notify_text = $params->get('text_notify_existing_reviewal');
				else if (!$isnew)                  $notify_text = $params->get('text_notify_existing');
				//print_r($notify_emails);
				//exit;
			}
			
			
			// *****************************************************************************************
			// If there are emails to notify for current saving case, then send the notifications emails
			// *****************************************************************************************
			if ($notify_emails) {
				// ... we use some strings from administrator part
				// load english language file for 'com_flexicontent' component then override with current language file
				JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
				JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
				
				$model->sendNotificationEmails($isnew, $needs_version_reviewal, $needs_publication_approval, $notify_emails, $notify_text, $before_cats, $after_cats, $params);
			}
			
			
			// ******************************************************************************************
			// If the item is not new, then we need to CLEAN THE CACHE so that our changes appear realtime
			// ******************************************************************************************
			if ( !$isnew ) {
				if (FLEXI_J16GE) {
					$cache = FLEXIUtilities::getCache();
					$cache->clean('com_flexicontent_items');
				} else {
					$cache = &JFactory::getCache('com_flexicontent_items');
					$cache->clean();
				}
			}
			
			
			// ****************************************************************************************************************************
			// Recalculate EDIT PRIVILEGE of new item. Reason for needing to do this is because we can have create permission in a category
			// and thus being able to set this category as item's main category, but then have no edit/editown permission for this category
			// ****************************************************************************************************************************
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $model->get('id');
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
				// ALTERNATIVE 1
				//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if ($user->gid >= 25) {
				$canEdit = true;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
			}
		}		
		
		// *******************************************************************************************************
		// Check if user can not edit item further (due to changed main category, without edit/editown permission)
		// *******************************************************************************************************
		if ($isnew && !$canEdit)
		{
			// Set notice about creating an item that cannot be changed further
			JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_CHANGE_FURTHER' ) );
		} else if ( !$isnew ) {
			if ( $cparams->get('items_session_editable', 0) ) {
				// Allow item to be editable till logoff
				$session 	=& JFactory::getSession();
				$session->get('rendered_uneditable', array(),'flexicontent');
				$rendered_uneditable[$model->get('id')]  = 1;
				$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
			}
		}
		
		// ****************************************
		// Saving is done, decide where to redirect
		// ****************************************
		switch ($task)
		{
			case 'apply' :
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'&cid='.(int) $model->get('id');
				break;
			case 'saveandnew' :
				if(isset($post['type_id']))
					$link = 'index.php?option=com_flexicontent&view=item&typeid='.$post['type_id'];
				else
					$link = 'index.php?option=com_flexicontent&view=item';
				break;
			default :
				$link = 'index.php?option=com_flexicontent&view=items';
				break;
		}
		$msg = JText::_( 'FLEXI_ITEM_SAVED' );
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
		if (!$copytask_allow_uneditable || $method==2) { // if method is 2 (move) we will deny moving uneditable items
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
		
		// Get item model
		$model  = $this->getModel('item');
		
		// Set some variables
		$link  = 'index.php?option=com_flexicontent&view=items';
		$task  = JRequest::getVar('task');
		$mainframe = &JFactory::getApplication();
		
		if ($task == 'importcsv')
		{
			// Retrieve from configuration for (a) typeid(a) main category, (b) secondaries categories
			$type_id 	= JRequest::getInt( 'type_id', 0 );
			$maincat 	= JRequest::getInt( 'maincat', 0 );
			$seccats 	= JRequest::getVar( 'seccats', array(0), 'post', 'array' );
			if( !$maincat ) $maincat = @$seccats[0];
			
			if( !$type_id ) {
				// Check for the required Content Type Id
				$this->setRedirect($link, "Please select Content Type for the imported items");
				return;
			}
			if( !$maincat ) {
				// Check for the required main category
				$this->setRedirect($link, "Please select main category for the imported items");
				return;
			}
			
			// Retrieve CSV file format variables, EXPANDING the Escape Characters like '\n' ... provided by the form
			$pattern = '/(?<!\\\)(\\\(?:n|r|t|v|f|[0-7]{1,3}|x[0-9a-f]{1,2}))/ie';
			$replace = 'eval(\'return "$1";\')';
			$field_separator  = preg_replace($pattern, $replace, JRequest::getVar('field_separator'));  
			$enclosure_char   = preg_replace($pattern, $replace, JRequest::getVar('enclosure_char'));  ;
			$record_separator = preg_replace($pattern, $replace, JRequest::getVar('record_separator'));  
			
			if( $field_separator=='' || $record_separator=='' ) {
				// Check for the (required) title column
				$this->setRedirect($link, "CSV format not valid, please enter Field Separator and Item Separator");
				return;
			}
			
			// Retrieve the uploaded CSV file
			$csvfile = @$_FILES["csvfile"]["tmp_name"];
			if(!is_file($csvfile)) {
				$this->setRedirect($link, "Upload file error!");
				return;
			}
			
			// Read & Parse the CSV file according the given format
			$contents = FLEXIUtilities::csvstring_to_array(file_get_contents($csvfile), $field_separator, $enclosure_char, $record_separator);
			
			// alternative way of reading / parsing CSV data ...
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
			//echo "<xmp>"; var_dump($csv->getRows()); echo "</xmp>";
			$contents = $csv->connect();*/
			
			// Basic error checking, for empty data
			if(count($contents[0])<=0) {
				$this->setRedirect($link, "Upload file error! CSV file for mat is not correct 1.");
				return;
			}
			
			// Get field names (from the header line (row 0), and remove it form the data array
			$columns = flexicontent_html::arrayTrim($contents[0]);
			unset($contents[0]);  //echo "<pre>"; print_r($columns); exit;
			
			// Check for the (required) title column
			if(!in_array('title', $columns)) {
				$this->setRedirect($link, "Upload file error! CSV file format is not correct 2.");
				return;
			}
			
			// Handle each row (item) using store() method of the item model to create the items
			$cnt = 1;
			foreach($contents as $line)
			{
				// Trim item's data
				$fields = flexicontent_html::arrayTrim($line);
				
				// Prepare request variable used by the item's Model
				$data = array();
				$data['type_id'] = $type_id;
				$data['catid']   = $maincat;
				$data['cid']     = $seccats;
				$data['vstate']  = 2;
				$data['state']   = -4;
				
				// Handle each field of the item
				foreach($fields as $col_no => $field_data)
				{
					$fieldname = $columns[$col_no];
					if ($fieldname=='title' || $fieldname=='text' || $fieldname=='alias') {
						$field_values = $field_data;
					} else {
						// Split multi-value field
						$vals = $field_data ? preg_split("/[\s]*%%[\s]*/", $field_data) : array();
						$vals = flexicontent_html::arrayTrim($vals);
						unset($field_values);
						
						// Handle each value of the field
						foreach ($vals as $i => $val)
						{
							// Split multiple property fields
							$props = $val ? preg_split("/[\s]*!![\s]*/", $val) : array();
							$props = flexicontent_html::arrayTrim($props);
							unset($prop_arr);
							
							// Handle each property of the value
							foreach ($props as $j => $prop) {
								if ( preg_match( '/\[-(.*)-\]=(.*)/', $prop, $matches) ) {
									$prop_arr[$matches[1]] = $matches[2];
								}
							}
							$field_values[] = isset($prop_arr) ? $prop_arr : $val;
						}
					}
					
					// Assign array of field values to the item data row
					if (!FLEXI_J16GE || $fieldname=='title' || $fieldname=='text' || $fieldname=='alias') {
						$data[$fieldname] = $field_values;
					} else {
						$data['custom'][$fieldname] = $field_values;
					}
					
					
				}
				//echo "<pre>"; print_r($data); exit;
				
				// Set/Force id to zero to indicate creation of new item
				$data['id'] = 0;
				
				// Finally try to create the item by using Item Model's store() method
				if( !$model->store($data) ) {
					$msg = $cnt . ". Import item with title: '" . $data['title'] . "' error" ;
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
				} else {
					$msg = $cnt . ". Import item with title: '" . $data['title'] . "' success" ;
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
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$cid      = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$pk       = (int)$cid[0];
		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '' );
		
		// Only attempt to check the row in if it exists.
		if ($pk)
		{
			$user = JFactory::getUser();

			// Get an instance of the row to checkin.
			$table = & JTable::getInstance('flexicontent_items', '');
			if (!$table->load($pk))
			{
				$this->setError($table->getError());
				return;// false;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (a) record checked out by current user
			if ($table->checked_out) {
				if (FLEXI_J16GE) {
					$canCheckin = $user->authorise('core.admin', 'checkin');
				} else if (FLEXI_ACCESS) {
					$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
				} else {
					$canCheckin = $user->gid >= 24;
				}
				if ( !$canCheckin && $table->checked_out != $user->id) {
					$this->setError(JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER'));
					return;// false;
				}
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				$this->setError($table->getError());
				return;// false;
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=items', JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', 1) );
		return;// true;
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
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$canImport = $permission->CanConfig;
		} else if ($user->gid >= 25) {
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
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		flexicontent_html::setitemstate();
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
		$model = $this->getModel('item');
		$msg = '';
		
		$newstate = JRequest::getVar("newstate", '');
		$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => (FLEXI_J16GE ? 2:-1), 'T' => -2 );
		$statenames = array ( 'PE' => 'FLEXI_PENDING', 'OQ' => 'FLEXI_TO_WRITE', 'IP' => 'FLEXI_IN_PROGRESS', 'P' => 'FLEXI_PUBLISHED', 'U' => 'FLEXI_UNPUBLISHED', 'A' => 'FLEXI_ARCHIVED', 'T' => 'FLEXI_TRASHED' );
		
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
				
				// Determine priveleges of the current user on the given item
				if (FLEXI_J16GE) {
					$asset = 'com_content.article.' . $itemdata[$id]->id;
					$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $itemdata[$id]->created_by == $user->get('id'));
					$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $itemdata[$id]->created_by == $user->get('id'));
					// ...
					$permission = FlexicontentHelperPerm::getPerm();
					$has_archive    = $permission->CanArchives;
				} else if ($user->gid >= 25) {
					$has_edit_state = true;
					$has_delete     = true;
					$has_archive    = true;
				} else if (FLEXI_ACCESS) {
					$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $itemdata[$id]->created_by == $user->get('id')) ;
					$has_delete     = in_array('delete', $rights) || (in_array('deleteown', $rights) && $itemdata[$id]->created_by == $user->get('id')) ;
					$has_archive    = FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid);
				} else {
					$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
					$has_delete     = $user->gid >= 23; // is at least manager
					$has_archive    = $user->gid >= 23; // is at least manager
				}
				
				$has_edit_state = $has_edit_state && in_array($stateids[$newstate], array(0,1,-3,-4,-5));
				$has_delete     = $has_delete     && $stateids[$newstate] == -2;
				$has_archive    = $has_archive    && $stateids[$newstate] == (FLEXI_J16GE ? 2:-1);
				
				if ( $has_edit_state || $has_delete || $has_archive ) {
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
		
		// Set state
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
		$itemmodel = $this->getModel('item');
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
		if ( count($auth_cid) && !$model->delete($auth_cid, $itemmodel) ) {
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
		
		$model = $this->getModel('item');
		
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

		// Initialize some variables
		$user	= & JFactory::getUser();

		// Get an item model
		$model = & $this->getModel('item');
		
		// CHECK-IN the item if user can edit
		if ( $model->get('id') )
		{
			$canAdd  = !FLEXI_J16GE ? $model->canAdd()  : $model->getItemAccess()->get('access-create');
			$canEdit = !FLEXI_J16GE ? $model->canEdit() : $model->getItemAccess()->get('access-edit');
	
			// Check if item is editable till logoff
			if (!$canEdit) {
				$session 	=& JFactory::getSession();
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
					if ($canEdit) {
						// Set notice for existing item being editable till logoff 
						JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ) );
					}
				}
			}
			
			if ($canEdit) $model->checkin();
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

		// Check if item is editable till logoff
		if (!$canEdit) {
			$session 	=& JFactory::getSession();
			if ($session->has('rendered_uneditable', 'flexicontent')) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				if ($canEdit) {
					// Set notice for existing item being editable till logoff 
					JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ) );
				}
			}
		}
		
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
	
	
	//*************************
	// RAW output functions ...
	//*************************
	
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
		
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$cache = &JFactory::getCache('com_flexicontent_items');
			$cache->clean();
		}
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
		
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$cache = &JFactory::getCache('com_flexicontent_items');
			$cache->clean();
		}
		
		echo JText::_( 'FLEXI_NOT_RATED_YET' );
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
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanUseTags = $permission->CanUseTags;
		} else if (FLEXI_ACCESS) {
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
		$user	=& JFactory::getUser();
		
		// General permission since we do not have a specific item yet
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$auth_publish = $permission->CanPublish || $permission->CanPublishOwn;
			$auth_delete  = $permission->CanDelete  || $permission->CanDeleteOwn;
			$auth_archive = $permission->CanArchives;
		} else if (FLEXI_ACCESS) {
			$auth_publish  = ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)) : 1;
			$auth_delete   = ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'delete', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'deleteown', 'users', $user->gmid)) : 1;
			$auth_archive  = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
		} else {
			$auth_publish = 1;
			$auth_delete  = 1;
			$auth_archive = 1;
		}
		
		if($auth_publish || $auth_archive || $auth_delete) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");

			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css">';
			
			if ($auth_publish) {
				$state['P'] = array( 'name' =>'FLEXI_PUBLISHED', 'desc' =>'FLEXI_PUBLISHED_DESC', 'icon' => 'tick.png', 'color' => 'darkgreen' );
				$state['IP'] = array( 'name' =>'FLEXI_IN_PROGRESS', 'desc' =>'FLEXI_NOT_FINISHED_YET', 'icon' => 'publish_g.png', 'color' => 'darkgreen', 'clear' => true );
				$state['U'] = array( 'name' =>'FLEXI_UNPUBLISHED', 'desc' =>'FLEXI_UNPUBLISHED_DESC', 'icon' => 'publish_x.png', 'color' => 'darkred' );
				$state['PE'] = array( 'name' =>'FLEXI_PENDING', 'desc' =>'FLEXI_NEED_TO_BE_APPROVED', 'icon' => 'publish_r.png', 'color' => 'darkred' );
				$state['OQ'] = array( 'name' =>'FLEXI_TO_WRITE', 'desc' =>'FLEXI_TO_WRITE_DESC', 'icon' => 'publish_y.png', 'color' => 'darkred', 'clear' => true );
			}
			if ($auth_archive) {
				$state['A'] = array( 'name' =>'FLEXI_ARCHIVED', 'desc' =>'FLEXI_ARCHIVED_STATE', 'icon' => 'archive.png', 'color' => 'gray' );
			}
			if ($auth_delete) {
				$state['T'] = array( 'name' =>'FLEXI_TRASHED', 'desc' =>'FLEXI_TRASHED_TO_BE_DELETED', 'icon' => 'trash.png', 'color' => 'gray' );
			}
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
		<?php if (FLEXI_J16GE) { ?>
								window.parent.Joomla.submitbutton('items.changestate')";
		<?php } else { ?>
								window.parent.submitbutton('changestate')";
		<?php } ?>
						target="_parent">
					<img src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo JText::_( $statedata['desc'] ); ?>" />
					<?php echo JText::_( $statedata['name'] ); ?>
				</a>
		<?php
				if ( isset($statedata['clear']) ) echo "<div style='width:100%; float: left; clear both;'></div>";
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

}
