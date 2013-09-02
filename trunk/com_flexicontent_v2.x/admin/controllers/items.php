<?php
/**
 * @version 1.5 stable $Id: items.php 1650 2013-03-11 10:27:06Z ggppdk $
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
		$app     = JFactory::getApplication();
		$db      = JFactory::getDBO();
		$user    = JFactory::getUser();
		$config  = JFactory::getConfig();
		$session = JFactory::getSession();
		$task	   = JRequest::getVar('task');
		$model   = $this->getModel('item');
		$isnew   = !$model->getId();
		$ctrl_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';
		
		// Get component parameters
		$params = clone( JComponentHelper::getParams('com_flexicontent') );
		
		// Merge the type parameters
		$tparams = $model->getTypeparams();
		$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
		$params->merge($tparams);
		
		
		// Get data from request and validate them
		if (FLEXI_J16GE) {
			// Retrieve form data these are subject to basic filtering
			$data   = JRequest::getVar('jform', array(), 'post', 'array');   // Core Fields and and item Parameters
			$custom = JRequest::getVar('custom', array(), 'post', 'array');  // Custom Fields
			$jfdata = JRequest::getVar('jfdata', array(), 'post', 'array');  // Joomfish Data
			
			// Validate Form data for core fields and for parameters
			$model->setId((int) $data['id']);   // Set data id into model in case some function tries to get a property and item gets loaded
			$form = $model->getForm();          // Do not pass any data we only want the form object in order to validate the data and not create a filled-in form
			$post = $model->validate($form, $data);
			if (!$post) {
				//JError::raiseWarning( 500, "Error while validating data: " . $model->getError() );
				echo "Error while validating data: " . $model->getError();
				echo '<span class="fc_return_msg">'.JText::sprintf('FLEXI_CLICK_HERE_TO_RETURN', '"JavaScript:window.history.back();"').'</span>';
				jexit();
			}
			
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
		
		
		// Make sure Content ID in the REQUEST is set, this is needed in BACKEND, needed in some cases
		// NOTE this is not the same as jform['cid'] which is the category IDs of the Content Item
		if (FLEXI_J16GE)  JRequest::setVar( 'cid', array($model->getId()), 'post', 'array' );
		
		
		// ********************************************************************************
		// PERFORM ACCESS CHECKS, NOTE: we need to check access again, despite having
		// checked them on edit form load, because user may have tampered with the form ... 
		// ********************************************************************************
		
		if (FLEXI_J16GE) $itemAccess = $model->getItemAccess();
		$canAdd  = !FLEXI_J16GE ? $model->canAdd()  : $itemAccess->get('access-create');
		$canEdit = !FLEXI_J16GE ? $model->canEdit() : $itemAccess->get('access-edit');
		
		$type_id = (int) $post['type_id'];  // Typecast to int, (already done for J2.5 via validating)
		if ( !$isnew && $model->get('type_id') == $type_id) {
			// Existing item with Type not being ALTERED, content type can be maintained regardless of privilege
			$canCreateType = true;
		} else {
			// New item or existing item with Type is being ALTERED, check privilege to create items of this type
			$canCreateType = $model->canCreateType( array($type_id), true, $types );
		}
		
		if ( !$canCreateType ) {
			$msg = isset($types[$type_id]) ?
				JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', JText::_($types[$type_id]->name) ) :
				' Content Type '.$type_id.' was not found OR is not published';
			JError::raiseWarning( 403, $msg );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '' );
			return;
		}
		
		if ( !$canEdit ) {
			// No edit privilege, check if item is editable till logoff
			if ($session->has('rendered_uneditable', 'flexicontent')) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
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
			$original_item = $model->getItem($post['id'], $check_view_access=false, $no_cache=true, $force_version=0);
		}
		
		
		// ****************************************
		// Try to store the form data into the item
		// ****************************************
		if ( ! $model->store($post) )
		{
			// Set error message about saving failed, and also the reason (=model's error message)
			$msg = JText::_( 'FLEXI_ERROR_STORING_ITEM' );
			JError::raiseWarning( 500, $msg .": " . $model->getError() );

			// Since an error occured, check if (a) the item is new and (b) was not created
			if ($isnew && !$model->get('id')) {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'add&cid=0&typeid='.$type_id;
				$this->setRedirect($link, $msg);
			} else {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&cid='.$model->get('id');
				$this->setRedirect($link, $msg);
			}
			
			// Saving has failed check-in and return, (above redirection will be used)
			$model->checkin();
			return;
		}
		
		
		// **************************************************
		// Check in model and get item id in case of new item
		// **************************************************
		$model->checkin();
		$post['id'] = $isnew ? (int) $model->get('id') : $post['id'];
		
		// Get items marked as newly submitted
		$newly_submitted = $session->get('newly_submitted', array(), 'flexicontent');
		if ($isnew) {
			// Mark item as newly submitted, to allow to a proper "THANKS" message after final save & close operation (since user may have clicked add instead of add & close)
			$newly_submitted[$model->get('id')] = 1;
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
		}
		$newly_submitted_item = @ $newly_submitted[$model->get('id')];
		
		
		// ***********************************************************************************************************
		// Get newly saved -latest- version (store task gets latest) of the item, and also calculate publish privelege
		// ***********************************************************************************************************
		$item = $model->getItem($post['id'], $check_view_access=false, $no_cache=true, $force_version=-1);
		$canPublish = $model->canEditState( $item, $check_cat_perm=true );
		
		
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
			$draft_state = -4;
			
			$current_version = FLEXIUtilities::getCurrentVersions($item->id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($item->id, true);    // Get last version (=latest one saved, highest version id),
			
			// $post variables vstate & state may have been (a) tampered in the form, and/or (b) altered by save procedure so better not use them
			$needs_version_reviewal     = !$isnew && ($last_version > $current_version) && !$canPublish;
			$needs_publication_approval =  $isnew && ($item->state == $pending_approval_state) && !$canPublish;
			
			$draft_from_non_publisher = $item->state==$draft_state && !$canPublish;
			
			if ($draft_from_non_publisher) {
				// Suppress notifications for draft-state items (new or existing ones), for these each author will publication approval manually via a button
				$nConf = false;
			} else {
				// Get notifications configuration and select appropriate emails for current saving case
				$nConf = $model->getNotificationsConf($params);  //echo "<pre>"; print_r($nConf); "</pre>";
			}
			
			if ($nConf)
			{
				$states_notify_new = $params->get('states_notify_new', array(1,0,(FLEXI_J16GE ? 2:-1),-3,-4,-5));
				if ( empty($states_notify_new) )						$states_notify_new = array();
				else if ( ! is_array($states_notify_new) )	$states_notify_new = !FLEXI_J16GE ? array($states_notify_new) : explode("|", $states_notify_new);
				
				$states_notify_existing = $params->get('states_notify_existing', array(1,0,(FLEXI_J16GE ? 2:-1),-3,-4,-5));
				if ( empty($states_notify_existing) )						$states_notify_existing = array();
				else if ( ! is_array($states_notify_existing) )	$states_notify_existing = !FLEXI_J16GE ? array($states_notify_existing) : explode("|", $states_notify_existing);

				$n_state_ok = in_array($item->state, $states_notify_new);
				$e_state_ok = in_array($item->state, $states_notify_existing);
				
				if ($needs_publication_approval)   $notify_emails = $nConf->emails->notify_new_pending;
				else if ($isnew && $n_state_ok)    $notify_emails = $nConf->emails->notify_new;
				else if ($isnew)                   $notify_emails = array();
				else if ($needs_version_reviewal)  $notify_emails = $nConf->emails->notify_existing_reviewal;
				else if (!$isnew && $e_state_ok)   $notify_emails = $nConf->emails->notify_existing;
				else if (!$isnew)                  $notify_emails = array();
				
				if ($needs_publication_approval)   $notify_text = $params->get('text_notify_new_pending');
				else if ($isnew)                   $notify_text = $params->get('text_notify_new');
				else if ($needs_version_reviewal)  $notify_text = $params->get('text_notify_existing_reviewal');
				else if (!$isnew)                  $notify_text = $params->get('text_notify_existing');
				//print_r($notify_emails); exit;
			}
		}
		
		
		// *********************************************************************************************************************
		// If there are emails to notify for current saving case, then send the notifications emails, but 
		// *********************************************************************************************************************
		if ( !empty($notify_emails) && count($notify_emails) ) {
			$notify_vars = new stdClass();
			$notify_vars->needs_version_reviewal     = $needs_version_reviewal;
			$notify_vars->needs_publication_approval = $needs_publication_approval;
			$notify_vars->isnew         = $isnew;
			$notify_vars->notify_emails = $notify_emails;
			$notify_vars->notify_text   = $notify_text;
			$notify_vars->before_cats   = $before_cats;
			$notify_vars->after_cats    = $after_cats;
			$notify_vars->original_item = @ $original_item;
			
			$model->sendNotificationEmails($notify_vars, $params, $manual_approval_request=0);
		}
		
		
		// ***************************************************
		// CLEAN THE CACHE so that our changes appear realtime
		// ***************************************************
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
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
		} else if (FLEXI_ACCESS && $user->gid < 25) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
			$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
		} else {
			// This is meaningful when executed in frontend, since all backend users (managers and above) can edit items
			$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
		}
		
		
		// *******************************************************************************************************
		// Check if user can not edit item further (due to changed main category, without edit/editown permission)
		// *******************************************************************************************************
		if (!$canEdit)
		{
			if ($task=='apply') {
				// APPLY TASK: Temporarily set item to be editable till closing it
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$rendered_uneditable[$model->get('id')]  = 1;
				$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				$canEdit = 1;
			}
			
			else if ( $newly_submitted_item ) {
				// NEW ITEM: Do not use editable till logoff behaviour
				// ALSO: Clear editable FLAG set due to 'apply' task
				if ( !$params->get('items_session_editable', 0) ) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					if ( isset($rendered_uneditable[$model->get('id')]) ) {
						unset( $rendered_uneditable[$model->get('id')] );
						$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					}
				}
			}
			
			else {
				// EXISTING ITEM: We can use editable till logoff behaviour
				if ( $params->get('items_session_editable', 0) ) {
					
					// Set notice for existing item being editable till logoff 
					JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ) );
					
					// Allow item to be editable till logoff
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$rendered_uneditable[$model->get('id')]  = 1;
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					$canEdit = 1;
				}
			}
			
			// Set notice about saving an item that cannot be changed further
			if ( !$canEdit ) {
				$app->enqueueMessage(JText::_( 'FLEXI_CANNOT_MAKE_FURTHER_CHANGES_TO_CONTENT' ), 'message' );
			}
		}
		
		
		// ****************************************************************
		// Check for new Content Item is being closed, and clear some flags
		// ****************************************************************
		
		if ($task!='apply' && $newly_submitted_item )
		{
			// Clear item from being marked as newly submitted
			unset($newly_submitted[$model->get('id')]);
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
			
			// Clear editable FLAG set temporarily, e.g. due to 'apply' task
			if ( !$params->get('items_session_editable', 0) ) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				if ( isset($rendered_uneditable[$model->get('id')]) ) {
					unset( $rendered_uneditable[$model->get('id')] );
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				}
			}
		}
		
		
		// ****************************************
		// Saving is done, decide where to redirect
		// ****************************************
		switch ($task)
		{
			case 'apply' :
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&cid='.(int) $model->get('id');
				break;
			case 'saveandnew' :
				if( $type_id )
					$link = 'index.php?option=com_flexicontent&view=item&typeid='.$type_id;
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
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$ord_catid = JRequest::getVar( 'ord_catid', array(0), 'post', 'array' );
		$prev_order = JRequest::getVar( 'prev_order', array(0), 'post', 'array' );
		
		// calculate access
		if (FLEXI_J16GE) {
			$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		} else {
			$canOrder = $user->gid < 25 ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
		}
		
		// check access
		if ( !$canOrder ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if ( $model->move($dir, $ord_catid, $prev_order) ){
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
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$order = JRequest::getVar( 'order', array(0), 'post', 'array' );
		$ord_catid = JRequest::getVar( 'ord_catid', array(0), 'post', 'array' );
		$prev_order = JRequest::getVar( 'prev_order', array(0), 'post', 'array' );
		
		// calculate access
		if (FLEXI_J16GE) {
			$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		} else {
			$canOrder = $user->gid < 25 ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
		}
		
		// check access
		if ( !$canOrder ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if (!$model->saveorder($cid, $order, $ord_catid, $prev_order)) {
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
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		
		if (FLEXI_J16GE) {
			$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');
		} else if (FLEXI_ACCESS) {
			$canCopy = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1;
		} else {
			// no global privilege we will check edit privilege bellow (for backend users it will be always true)
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
				} else if (FLEXI_ACCESS && $user->gid < 25) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('editown', $rights) && $itemdata[$id]->created_by == $user->id;
				} else {
					// This is meaningful when executed in frontend, since all backend users (managers and above) can edit items
					$canEdit = $user->authorize('com_content', 'edit', 'content', 'all');
					$canEditOwn	= $user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id');
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

		$db    = JFactory::getDBO();
		$task  = JRequest::getVar('task');
		$model = $this->getModel('items');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$method   = JRequest::getInt( 'method', 1);
		$keeepcats= JRequest::getInt( 'keeepcats', 1 );
		$keeptags = JRequest::getInt( 'keeptags', 1 );
		$prefix   = JRequest::getVar( 'prefix', 1, 'post' );
		$suffix   = JRequest::getVar( 'suffix', 1, 'post' );
		$copynr   = JRequest::getInt( 'copynr', 1 );
		$maincat  = JRequest::getInt( 'maincat', '' );
		$seccats  = JRequest::getVar( 'seccats', array(), 'post', 'array' );
		$keepseccats = JRequest::getVar( 'keepseccats', 0, 'post', 'int' );
		$lang    = JRequest::getVar( 'language', '', 'post' );
		$state   = JRequest::getInt( 'state', '');
		
		// Set $seccats to --null-- to indicate that we will maintain secondary categories
		$seccats = $keepseccats ? null : $seccats;
		
		if (FLEXI_J16GE) {
			$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');
		} else if (FLEXI_ACCESS) {
			$canCopy = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1;
		} else {
			// no global privilege we will check edit privilege bellow (for backend users it will be always true)
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
				} else if (FLEXI_ACCESS && $user->gid < 25) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('editown', $rights) && $itemdata[$id]->created_by == $user->id;
				} else {
					// This is meaningful when executed in frontend, since all backend users (managers and above) can edit items
					$canEdit = $user->authorize('com_content', 'edit', 'content', 'all');
					$canEditOwn	= $user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id');
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
		$clean_cache_flag = false;
		
		// Try to copy/move items
		if ($task == 'copymove')
		{
			if ($method == 1) // copy only
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPY_SUCCESS', count($auth_cid) );
					$clean_cache_flag = true;
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
				
				$clean_cache_flag = true;
			}
			else // copy and move
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPYMOVE_SUCCESS', count($auth_cid) );
					$clean_cache_flag = true;
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
		
		// CLEAN THE CACHE so that our changes appear realtime
		if ($clean_cache_flag) {
			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
				$cache->clean('com_flexicontent_filters');
			} else {
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();
				$filtercache = JFactory::getCache('com_flexicontent_filters');
				$filtercache->clean();
			}
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
		
		echo '<link rel="stylesheet" href="'.JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css" />';
		if      (FLEXI_J30GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j3x.css';
		else if (FLEXI_J16GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j25.css';
		else                  $fc_css = JURI::base().'components/com_flexicontent/assets/css/j15.css';
		echo '<link rel="stylesheet" href="'.$fc_css.'" />';
		
		// Get item model
		$model  = $this->getModel('item');
		
		// Set some variables
		$link  = 'index.php?option=com_flexicontent&view=items';  // $_SERVER['HTTP_REFERER'];
		$task  = JRequest::getCmd( 'task' );
		$debug = JRequest::getInt( 'debug', 0 );
		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		
		$failure_count = $success_count = 0;
		$log_filename = 'importcsv_'.($user->id).'.php';
		
		if (FLEXI_J16GE) {
			jimport('joomla.log.log');
			JLog::addLogger(array('text_file' => $log_filename));
		} else {
			jimport('joomla.error.log');
			$log = JLog::getInstance($log_filename);
		}
		
		if ($task == 'importcsv')
		{
			// Retrieve from configuration for (a) typeid, language, main category, secondaries categories, etc
			$type_id 	= JRequest::getInt( 'type_id', 0 );
			$id_col = JRequest::getInt( 'id_col', 0 );
			
			$language	= JRequest::getVar( 'language', '' );
			$state = JRequest::getVar( 'state', '' );
			
			$maincat 	= JRequest::getInt( 'maincat', 0 );
			$maincat_col = JRequest::getInt( 'maincat_col', 0 );
			
			$seccats 	= JRequest::getVar( 'seccats', array(), 'post', 'array' );
			$seccats_col = JRequest::getInt( 'seccats_col', 0 );
			
			$tags_col = JRequest::getInt( 'tags_col', 0 );
			
			$created_col = JRequest::getInt( 'created_col', 0 );
			$created_by_col = JRequest::getInt( 'created_by_col', 0 );
			
			$metadesc_col = JRequest::getInt( 'metadesc_col', 0 );
			$metakey_col = JRequest::getInt( 'metakey_col', 0 );
			
			$publish_up_col = JRequest::getInt( 'publish_up_col', 0 );
			$publish_down_col = JRequest::getInt( 'publish_down_col', 0 );
			
			$ignore_unused_cols = JRequest::getInt( 'ignore_unused_cols', 0 );
			
			
			// ********************************************************************************************
			// Obligatory form fields, js validation should have prevented form submission but check anyway
			// ********************************************************************************************
			if( !$type_id ) {
				// Check for the required Content Type Id
				echo "<script>alert ('Please select Content Type for the imported items');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			if( !$maincat && !$maincat_col ) {
				// Check for the required main category
				echo "<script>alert ('Please select main category for the imported items');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			
			// ******************************************************************************************************
			// Retrieve CSV file format variables, EXPANDING the Escape Characters like '\n' ... provided by the form
			// ******************************************************************************************************
			$pattern = '/(?<!\\\)(\\\(?:n|r|t|v|f|[0-7]{1,3}|x[0-9a-f]{1,2}))/ie';
			$replace = 'eval(\'return "$1";\')';
			$field_separator  = preg_replace($pattern, $replace, JRequest::getVar('field_separator'));  
			$enclosure_char   = preg_replace($pattern, $replace, JRequest::getVar('enclosure_char'));  ;
			$record_separator = preg_replace($pattern, $replace, JRequest::getVar('record_separator'));  
			
			
			// ****************************************************************************************************************
			// Check for proper CSV file format variables, js validation should have prevented form submission but check anyway
			// ****************************************************************************************************************
			if( $field_separator=='' || $record_separator=='' ) {
				// Check for the (required) title column
				echo "<script>alert ('CSV format not valid, please enter Field Separator and Item Separator');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			// Retrieve the uploaded CSV file
			$csvfile = @$_FILES["csvfile"]["tmp_name"];
			if(!is_file($csvfile)) {
				echo "<script>alert ('Upload file error!');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			
			// ****************************************************
			// Read & Parse the CSV file according the given format
			// ****************************************************
			$contents = FLEXIUtilities::csvstring_to_array(file_get_contents($csvfile), $field_separator, $enclosure_char, $record_separator);
			
			// Basic error checking, for empty data
			if(count($contents[0])<=0) {
				echo "<script>alert ('Upload file error! CSV file format is not correct!');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			
			// ********************************************************************************
			// Get field names (from the header line (row 0), and remove it form the data array
			// ********************************************************************************
			$columns = flexicontent_html::arrayTrim($contents[0]);
			unset($contents[0]);
			$q = 'SELECT id, name, field_type FROM #__flexicontent_fields AS fi'
				.' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$type_id;
			$db->setQuery($q);
			$thefields = $db->loadObjectList('name');
			unset($thefields['tags']); // Prevent Automated Raw insertion of tags, we will use special code
			
			
			// ******************************************************************
			// Check for REQUIRED columns and decide CORE property columns to use
			// ******************************************************************
			$core_props = array();
			if ( $id_col && !in_array('id', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'id\' (Item ID)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($id_col) $core_props[] = 'id';
			
			if(!in_array('title', $columns)) {
				echo "<script>alert ('CSV file lacks column \'title\'');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			$core_props[] = 'title';
			$core_props[] = 'text';
			$core_props[] = 'alias';
			
			if ( !$language && !in_array('language', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'language\'');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if (!$language) $core_props[] = 'language';
			
			if ( !strlen($state) && !in_array('state', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'state\'');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ( !strlen($state) ) $core_props[] = 'state';
			
			if ( $maincat_col && !in_array('catid', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'catid\' (primary category)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($maincat_col) $core_props[] = 'catid';
			
			if ( $seccats_col && !in_array('cid', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'cid\' (secondary categories)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($seccats_col) $core_props[] = 'cid';
			
			if ( $created_col && !in_array('created', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'created\' (Creation date)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($created_col) $core_props[] = 'created';
			
			if ( $created_by_col && !in_array('created_by', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'created_by\' (Creator - Author)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($created_by_col) $core_props[] = 'created_by';
			
			if ( $metadesc_col && !in_array('metadesc', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'metadesc\' (META Description)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($metadesc_col) $core_props[] = 'metadesc';
			
			if ( $metakey_col && !in_array('metakey', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'metakey\' (META Keywords)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($metakey_col) $core_props[] = 'metakey';
			
			if ( $publish_up_col && !in_array('publish_up', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'publish_up\' (Start publication date)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($publish_up_col) $core_props[] = 'publish_up';
			
			if ( $publish_down_col && !in_array('publish_down', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'publish_down\' (End publication Date)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($publish_down_col) $core_props[] = 'publish_down';
			
			if ( $tags_col==1 && !in_array('tags_names', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'tags_names\' (Comma separated list of tag names)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ($tags_col==1) {
				$core_props[] = 'tags_names';
				$tags_model	= $this->getModel('tags');
			}
			
			if ( $tags_col==2 && !in_array('tags_raw', $columns) ) {
				echo "<script>alert ('CSV file lacks column \'tags_raw\' (Comma separated list of tag ids)');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			} else if ( $tags_col==2 ) {
				$core_props[] = 'tags_raw';
				$tags_model	= $this->getModel('tags');
			}
			
			
			// *********************************************************
			// Verify that all non core property columns are field names
			// *********************************************************
			$unused_columns = array();
			foreach($columns as $colname) {
				if ( !in_array($colname, $core_props) && !isset($thefields[$colname]) ) {
					$unused_columns[] = $colname;
					JError::raiseNotice( 500, "Column '".$colname."' : &nbsp; field name NOT FOUND, column will be ignored<br>" );
				}
			}
			if ( count($unused_columns) && !$ignore_unused_cols) {
				echo "<script>alert ('File has unused ".count($unused_columns)." columns \'".implode("\' , \'",$unused_columns)."\' (these field names are not assigned to choose CONTENT TYPE), please enable: Ignoring of unused columns');";
				echo "window.history.back();";
				echo "</script>";
				jexit();
			}
			
			
			// **********************************************************
			// Verify that custom specified item ids do not already exist
			// **********************************************************
			if ( $id_col ) {
				// Get 'id' column no
				$id_col_no = 0;
				foreach($columns as $col_no => $column) {
					if ( $columns[$col_no] == 'id' ) {
						$id_col_no = $col_no;
						break;
					}
				}
				
				// Get custom IDs in csv file
				$custom_id_arr = array();
				foreach($contents as $fields)
				{
					$custom_id_arr[] = $fields[$id_col_no];
				}
				$custom_id_list = "'" . implode("','", $custom_id_arr) ."'";
				
				// Cross check them if they already exist in the DB
				$q = "SELECT id FROM #__content WHERE id IN (".$custom_id_list.")";
				$db->setQuery($q);
				$existing_ids = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ( $existing_ids && count($existing_ids) ) {
					echo "<script>alert ('File has ".count($existing_ids)." item IDs that already exist: \'".implode("\' , \'",$existing_ids)."\', please fix or set to ignore \'id\' column');";
					echo "window.history.back();";
					echo "</script>";
					jexit();
				}
			}
			
			
			// ***************************************************************
			// Verify that imported files exist in the media/documents folders
			// ***************************************************************
			
			// Get fields that use files
			$import_media_folder  = JRequest::getVar('import_media_folder');
			$import_docs_folder  = JRequest::getVar('import_docs_folder');
			
			$mfolder  = JPath::clean( JPATH_SITE .DS. $import_media_folder .DS );
			$dfolder  = JPath::clean( JPATH_SITE .DS. $import_docs_folder .DS );
			
			$ff_types_to_props = array('image'=>'originalname', 'file'=>'_value_');
			$ff_types_to_paths = array('image' => $mfolder, 'file'=> $dfolder);
			$ff_names_to_types = array();
			foreach ($thefields as $_fld) {
				if ( isset($ff_types_to_props[$_fld->field_type]) )  $ff_names_to_types[$_fld->name] = $_fld->field_type;
			}
			
			// Fields that should be skipped from file checking
			$skip_file_field = JRequest::getVar('skip_file_field', array());
			
			// Get file field present in the header
			$ff_fields = array();
			foreach($columns as $col_no => $column) {
				$fld_name = $columns[$col_no];
				if ( isset($ff_names_to_types[$fld_name]) )  $ff_fields[$col_no] = $fld_name;
			}
			
			// Get filenames from file columns
			$filedata_arr = array();
			foreach($contents as $lineno => $fields)
			{
				foreach($ff_fields as $col_no => $fld_name) {
					$filedata_arr[$fld_name][$lineno] = $fields[$col_no];
				}
			}
			
			//echo "<pre>"; print_r($filedata_arr); exit;
			if ( count($filedata_arr) )
			{
				$filenames_missing = array();
				
				foreach($filedata_arr as $fld_name => $filedata_arr) {
					if ( in_array($fld_name, $skip_file_field) ) continue;
					
					$field_type = $ff_names_to_types[$fld_name];
					$prop_name = $ff_types_to_props[$field_type];
					$srcpath_original = $ff_types_to_paths[$field_type];
					
					foreach($filedata_arr as $lineno => $field_data) {
						// Split multi-value field
						$vals = $field_data ? preg_split("/[\s]*%%[\s]*/", $field_data) : array();
						$vals = flexicontent_html::arrayTrim($vals);
						unset($field_values);
						
						// Handle each value of the field
						$field_values = array();
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
							
							$filename = '';
							if ( !isset($prop_arr) ) {
								$filename = $val;
							} else {
								$filename = $prop_arr[$prop_name];
							}
							
							if ( $filename ) {
								//echo "<pre>"; print_r(JPath::clean( $srcpath_original  . $filename)); exit;
								$srcfilepath  = JPath::clean( $srcpath_original  . $filename );
								if ( !JFile::exists($srcfilepath) ) {
									$filenames_missing[$fld_name][] = "LINE ".$lineno.": ". flexicontent_html::escapeJsText($filename, $skipquote='');
								}
							}
						}
					}
				}
				
				
				$skip_file_field = JRequest::getVar('skip_file_field', array());
				
				// Cross check them if they already exist in the DB
				if ( count($filenames_missing) ) {
					if (!$debug) {
						echo '<span class="fc-mssg fc-note">(DEBUG was auto enabled for first 2 records)</span>'."\n";
						$debug = 2; //jexit();
					}
					echo '<span class="fc-mssg fc-warning"> CSV File has FILE references to missing media / document files, <br/>please fix or set EACH field to be skipped from checking </span>'."\n";
					foreach ($filenames_missing as $fld_name => $fld_files_missing) {
						$field_type = $ff_names_to_types[$fld_name];
						$srcpath_original = $ff_types_to_paths[$field_type];
						?>
						<span class="fc-mssg fc-note">
						FIELD: <b> <?php echo $fld_name; ?> </b> has <?php echo count($fld_files_missing); ?> missing filename(s) <br/>
						-- Not found in folder: <b> <?php echo $srcpath_original; ?> </b><br/>
						-- Missing filenames list: <br/> <?php echo implode('<br/>', $fld_files_missing); ?> <br/>
						</span>
						<?php
					}
				}
			}
			
			
			// *********************************************************************************
			// Handle each row (item) using store() method of the item model to create the items
			// *********************************************************************************
			$cnt = 1;
			foreach($contents as $lineno => $line)
			{
				// Trim item's data
				$fields = flexicontent_html::arrayTrim($line);
				
				// Prepare request variable used by the item's Model
				$data = array();
				$data['type_id'] = $type_id;
				$data['language']= $language;
				$data['catid']   = $maincat;
				$data['cid']     = $seccats;
				$data['vstate']  = 2;
				$data['state']   = $state;
				
				if (count($fields) > count($columns)) {
					$msg = "Redundadant columns at record row ".$lineno.", Found # columns: ". count($fields) . " > expected: ". count($columns);
					if (FLEXI_J16GE) JLog::add($msg); else $log->addEntry( array('comment' => $msg) );
				}
				
				// Handle each field of the item
				foreach($fields as $col_no => $field_data)
				{
					if ($col_no >= count($columns)) break;
					
					$fieldname = $columns[$col_no];
					if ( in_array($fieldname, $core_props) ) {
						$field_values = $field_data;
					} else {
						// Split multi-value field
						$vals = $field_data ? preg_split("/[\s]*%%[\s]*/", $field_data) : array();
						$vals = flexicontent_html::arrayTrim($vals);
						unset($field_values);
						
						// Handle each value of the field
						$field_values = array();
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
					if ( $fieldname=='id' )
					{
						if ( $id_col ) $data[$fieldname] = $field_values;
					}
					else if ($fieldname=='title' || $fieldname=='text' || $fieldname=='alias')
					{
						$data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='language' )
					{
						if ( !$language ) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='state' )
					{
						if ( !strlen($state) ) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='catid' )
					{
						if ($maincat_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='cid' )
					{
						if ($seccats_col) $data[$fieldname] = preg_split("/[\s]*,[\s]*/", $field_values);
					}
					else if ( $fieldname=='tags_names' )
					{
						if ($tags_col==1) {
							// Get tag names from comma separated list, filtering out bad characters
							$_tns_list = preg_replace("/[\"'\\\]/u", "", $field_values);
							$_tns_arr = array_unique(preg_split("/\s*,\s*/u", $_tns_list));
							$_tns = array();
							foreach($_tns_arr as $_tindex => $_tname)  if ($_tname) $_tns[] = $_tname;
							
							if (count($_tns))
							{
								$_tns_list = "'". implode("','", $_tns) ."'";
								$q = "SELECT name FROM #__flexicontent_tags WHERE name IN (". $_tns_list .")";
								$db->setQuery($q);
								$_tns_e = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
								
								$_tns_m = array_diff( $_tns , $_tns_e );
								if ( count($_tns_m) ) {
									// Create a newline separated list of tag names and then import missing tags,
									// thus making sure they are inserted into the tags DB table if not already present
									$_tns_list_m = implode("\n", $_tns_m);
									$tags_model->importList($_tns_list_m);
								}
								
								// Get tag ids
								$q = "SELECT id FROM #__flexicontent_tags WHERE name IN (". $_tns_list .")";
								$db->setQuery($q);
								$data['tag'] = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
							}
						}
					}
					else if ( $fieldname=='tags_raw' )
					{
						if ($tags_col==2) {
							// Get tag ids from comma separated list, filtering out bad characters
							$_tis_list = preg_replace("/[\"'\\\]/u", "", $field_values);
							$_tis = array_unique(array_map('intval', $_tis));
							$_tis = array_flip( $_tis );
							
							// Check to use only existing tag ids
							$_tis_list = implode(",", array_keys($_tis));
							$q = "SELECT id FROM #__flexicontent_tags WHERE id IN (". $_tis_list .")";
							$db->setQuery($q);
							$data['tag'] = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
						}
					}
					else if ( $fieldname=='created' )
					{
						if ($created_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='created_by' )
					{
						if ($created_by_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='metadesc' )
					{
						if ($metadesc_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='metakey' )
					{
						if ($metakey_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='publish_up' )
					{
						if ($publish_up_col) $data[$fieldname] = $field_values;
					}
					else if ( $fieldname=='publish_down' )
					{
						if ($publish_down_col) $data[$fieldname] = $field_values;
					}
					else if ( !FLEXI_J16GE )
					{
						// Custom Fields for J1.5
						$data[$fieldname] = $field_values;
					}
					else
					{
						// Custom Fields for J1.6+
						$data['custom'][$fieldname] = $field_values;
					}
				}
				
				
				if ( $debug ) {
					if ($cnt==1) {
						echo 'Parsing result of the first '.$debug.' records (<b>please click</b> <a href="JavaScript:window.history.back();">here</a> to return previous page): <br/><br/>';
						echo "\n\nCOLUMNS: ". implode(', ', $columns) ."<br />\n";
					}
					$data['text'] = mb_substr(strip_tags($data['text']), 0, 80, 'UTF-8') . ' ... ';
					echo "<pre>\n\n\n\nRECORD no $cnt:\n"; print_r($data); echo "</pre>";
					if ($cnt==$debug) break;
				} else {
					// Set/Force id to zero to indicate creation of new item, in case item 'id' column is being used
					$c_item_id = @$data['id'];
					$data['id'] = 0;
					
					// Finally try to create the item by using Item Model's store() method
					if( !$model->store($data) ) {
						$failure_count++;
						$msg = 'Failed item no: '. $cnt . ". titled as: '" . $data['title'] . "' : ". $model->getError();
						if (FLEXI_J16GE) JLog::add($msg); else $log->addEntry( array('comment' => $msg) );
					} else {
						$success_count++;
						$msg = 'Imported item no: '. $cnt . ". titled as: '" . $data['title'] . "'" ;
						if (FLEXI_J16GE) JLog::add($msg); else $log->addEntry( array('comment' => $msg) );
						
						// Try to rename entry if id column is being used
						if ( $id_col && $c_item_id )
						{
							$item_id = $model->getId();
							$q = "UPDATE #__content SET id='".$c_item_id."' WHERE id='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_items_ext SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_tags_item_relations SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_cats_item_relations SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_fields_item_relations SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_items_versions SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_versions SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							$q = "UPDATE #__flexicontent_favourites SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
							$db->setQuery($q);
							$db->query();
							
							if (FLEXI_J16GE) {
								$q = "UPDATE #__assets SET id='".$c_item_id."' WHERE id='".$item_id."'";
							} else {
								$q = "UPDATE #__flexiaccess_acl SET axo='".$c_item_id."'"
									. " WHERE acosection = ". $db->Quote('com_content')
									. " AND axosection = ". $db->Quote('item')
									. " AND axo='".$item_id."'";
							}
							$db->setQuery($q);
							$db->query();
						}
					}
				}
				$cnt++;
			}
			//fclose($fp);
			if ( $debug ) {
				echo "\n\n\n".'<b>please click</b> <a href="JavaScript:window.history.back();">here</a> to return previous page'."\n\n\n";
				jexit();
			}
			
			// Clean item's cache, but is this needed when adding items ?
			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
				$cache->clean('com_flexicontent_filters');
			} else {
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();
				$filtercache = JFactory::getCache('com_flexicontent_filters');
				$filtercache->clean();
			}
		}
		
		// Set a total results message and redirect
		$app->enqueueMessage(
			'Imported items: '.$success_count.' , failed items: '.$failure_count .
			', please review (in the logs folder) the import log file: '.$log_filename,
			($failure_count==0 && $success_count>0) ? 'message' : 'warning'
		);
		$this->setRedirect($link);
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_items';
		$redirect_url = 'index.php?option=com_flexicontent&view=items';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
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

		$user  = JFactory::getUser();
		$model = $this->getModel('items');
		
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
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		} else {
			$catscache = FLEXIUtilities::getCache();
			$catscache->clean('com_flexicontent_cats');
		}
		$msg  = JText::_( 'FLEXI_IMPORT_SUCCESSFUL' );
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
		$rows 		= $model->getUnassociatedItems($extdata, $_ids_only=false);
		
		echo ($model->addFlexiData($rows));
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
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
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
				} else if (FLEXI_ACCESS && $user->gid < 25) {
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
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
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
		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_APPROVAL_SELECT_ITEM_SUBMIT' ) );
		} else {
			$itemmodel = $this->getModel('item');
			$msg = $itemmodel->approval($cid);
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
		
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
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
				} else if (FLEXI_ACCESS && $user->gid < 25) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $itemdata[$id]->id, $itemdata[$id]->catid);
					$canDelete 		= in_array('delete', $rights);
					$canDeleteOwn	= in_array('deleteown', $rights) && $itemdata[$id]->created_by == $user->id;
				} else {
					$canDelete    = $user->gid >= 23; // is at least manager
					$canDeleteOwn = $user->gid >= 23; // is at least manager
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
				$cache->clean('com_flexicontent_filters');
			} else {
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();
				$filtercache = JFactory::getCache('com_flexicontent_filters');
				$filtercache->clean();
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
		
		$user	= JFactory::getUser();
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
			if (FLEXI_J16GE) {
				$cache = FLEXIUtilities::getCache();
				$cache->clean('com_flexicontent_items');
				$cache->clean('com_flexicontent_filters');
			} else {
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();				
				$filtercache = JFactory::getCache('com_flexicontent_filters');
				$filtercache->clean();				
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
		
		$post = JRequest::get('post');
		$post = FLEXI_J16GE ? $post['jform'] : $post;
		JRequest::setVar('cid', $post['id']);
		$this->checkin();
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
		$item = JTable::getInstance('flexicontent_items', '');
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

		$user  = JFactory::getUser();
		$session = JFactory::getSession();
		$model = $this->getModel('item');
		$isnew  = !$model->getId();
		
		$canAdd  = !FLEXI_J16GE ? $model->canAdd()  : $model->getItemAccess()->get('access-create');
		$canEdit = !FLEXI_J16GE ? $model->canEdit() : $model->getItemAccess()->get('access-edit');

		if ( !$canEdit ) {
			// No edit privilege, check if item is editable till logoff
			if ($session->has('rendered_uneditable', 'flexicontent')) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
			}
		}
		
		// New item: check if user can create in at least one category
		if ($isnew) {
			
			// A. Check create privilege
			if( !$canAdd ) {
				JError::raiseNotice( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ));
				$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
				return;
			}
			
			// Get User Group / Author parameters
			$db = JFactory::getDBO();
			$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
			$authorparams = $db->loadResult();
			$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);
			$max_auth_limit = $authorparams->get('max_auth_limit', 0);  // maximum number of content items the user can create
			
			// B. Check if max authored content limit reached
			if ($max_auth_limit) {
				$db->setQuery('SELECT COUNT(id) FROM #__content WHERE created_by = ' . $user->id);
				$authored_count = $db->loadResult();
				if ($authored_count >= $max_auth_limit) {
					JError::raiseNotice( 403, JText::sprintf( 'FLEXI_ALERTNOTAUTH_CREATE_MORE', $max_auth_limit ) );
					$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '' );
					return;
				}
			}
			
			// C. Check if Content Type can be created by current user
			$typeid = JRequest::getVar('typeid', 0, '', 'int');
			if ($typeid) {
				$canCreateType = $model->canCreateType( array($typeid), true, $types ); // Can create given Content Type
			} else {
				$canCreateType = $model->canCreateType( );  // Can create at least one Content Type
			}
			
			if( !$canCreateType ) {
				$type_name = isset($types[$$typeid]) ? '"'.JText::_($types[$$typeid]->name).'"' : JText::_('FLEXI_ANY');
				$msg = JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', $type_name );
				JError::raiseNotice( 403, $msg );
				$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
				return;
			}
		}
		
		// Existing item: Check if user can edit current item
		else {
			if ( !$canEdit ) {
				JError::raiseNotice( 403, JText::_( 'FLEXI_NO_ACCESS_EDIT' ));
				$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
				return;
			}
		}
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=items', '');
			return;
		}
		
		parent::display();
	}

	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function gettags()
	{
		$id    = JRequest::getInt('id', 0);
		$model = $this->getModel('item');
		$tags  = $model->gettags();
		$user  = JFactory::getUser();
		
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
			// no FLEXIAccess everybody can create / use tags
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
		$db = JFactory::getDBO();
		
		$db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
		$field = $db->loadObject();
		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
		$item->load( $id );
		FlexicontentFields::loadFieldConfig($field, $item);
		
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		
		
		@ob_end_clean();
		if ($votes) {
			$score	= round((((int)$votes[0]->rating_sum / (int)$votes[0]->rating_count) * (100 / $rating_resolution)), 2);
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
		
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$currentversion = $item->version;
		$page=JRequest::getInt('page', 0);
		$versioncount = $model->getVersionCount();
		$numpage = ceil($versioncount/$versionsperpage);
		if($page>$numpage) $page = $numpage;
		elseif($page<1) $page = 1;
		$limitstart = ($page-1)*$versionsperpage;
		$versions = $model->getVersionList();
		$versions	= $model->getVersionList($limitstart, $versionsperpage);
		
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
					echo "<a class='modal-versions' href='index.php?option=com_flexicontent&view=itemcompare&cid[]=".$item->id."&version=".$v->nr."&tmpl=component' title='".JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' )."' rel='{handler: \"iframe\", size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}'>".$view."</a><a onclick='javascript:return clickRestore(\"index.php?option=com_flexicontent&".$ctrl_task."&cid=".$item->id."&version=".$v->nr."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1\");' href='#' title='".JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $v->nr )."'>".$revert;
				}
				echo "</td></tr>";
		}
		exit;
	}
	
	
}
