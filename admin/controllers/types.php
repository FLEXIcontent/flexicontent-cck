<?php
/**
 * @version 1.5 stable $Id: types.php 1655 2013-03-16 17:55:25Z ggppdk $
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Types Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTypes extends FlexicontentController
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
		$this->registerTask( 'add',          'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		$this->registerTask( 'copy',         'copy' );
	}

	/**
	 * Logic to save a record
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function save()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));

		$model = $this->getModel('type');
		$user  = JFactory::getUser();
		$app   = JFactory::getApplication();
		$jinput = $app->input;

		$task  = $jinput->get('task', '', 'cmd');
		$data  = $jinput->get('jform', array(), 'array');

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		// Validate Form data
		$form = $model->getForm($data, false);
		$validated_data = $model->validate($form, $data);

		// Check for validation error
		if (!$validated_data)
		{
			// Get the validation messages and push up to three validation messages out to the user
			$errors	= $form->getErrors();
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
				$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
			}
			
			// Set POST form date into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session
			
			// Redirect back to the item form
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			
			if ( JRequest::getVar('fc_doajax_submit') )
			{
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
			return false; //die('error');
		}

		// Some fields need to be assigned after JForm validation (main XML file), because they do not exist in main XML file
		// Workaround for type's template parameters being clear by validation since they are not present in type.xml
		$validated_data['attribs'] = @ $data['attribs'];
		
		if ( $model->store($validated_data) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&task=types.edit&view=type&id='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=type';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=types';
					break;
			}
			$msg = JText::_( 'FLEXI_TYPE_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_TYPE' );
			JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=type';
		}

		$model->checkin();
		$this->setRedirect($link, $msg);
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_types';
		$redirect_url = 'index.php?option=com_flexicontent&view=types';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
	}
	
	
	/**
	 * Logic to publish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function publish()
	{
		$model = $this->getModel('types');
		$cid  = JRequest::getVar( 'cid', array(0), 'default', 'array' );

		$msg = '';
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=types', '');
			return;
		}

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		if (!$model->publish($cid, 1))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_TYPE_PUBLISHED' );
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}
	
	
	/**
	 * Logic to unpublish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function unpublish()
	{
		$model = $this->getModel('types');
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		$msg = '';
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else if (!$model->candelete($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_UNPUBLISH_THIS_TYPE_THERE_ARE_STILL_ITEMS_ASSOCIATED' ));
		} else {

			if (!$model->publish($cid, 0)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				throw new Exception($msg, 500);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TYPE_UNPUBLISHED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}

	
	/**
	 * Logic to delete records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		$model = $this->getModel('types');
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_REMOVE_THIS_TYPE_THERE_ARE_STILL_ITEMS_ASSOCIATED' ));
		} else {
			
			if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				throw new Exception($msg, 500);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TYPES_DELETED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}
	
	
	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function cancel()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));
		
		$post = JRequest::get('post');
		$post = FLEXI_J16GE ? $post['jform'] : $post;
		JRequest::setVar('cid', $post['id']);
		$this->checkin();
	}
	
	
	/**
	 * Logic to create the view for the edit field screen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{
		JRequest::setVar( 'view', 'type' );
		JRequest::setVar( 'hidemainmenu', 1 );
		
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('type');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}
		
		$view->display();
	}
	
	
	/**
	 * Logic to set the access level of the Types
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));

		$model = $this->getModel('types');
		$task  = JRequest::getVar( 'task' );
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id    = (int)$cid[0];

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$id];
		
		if (!$model->saveaccess( $id, $access ))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$filtercache = JFactory::getCache('com_flexicontent_filters');
		$filtercache->clean();

		$this->setRedirect('index.php?option=com_flexicontent&view=types' );
	}


	/**
	 * Logic to set the access level of the Types
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));
		
		$model = $this->getModel('types');
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		// calculate access
		$perms = FlexicontentHelperPerm::getPerm();
		$is_authorised = $perms->CanTypes;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}

		if(!$model->copy( $cid )) {
			$msg = JText::_('FLEXI_TYPES_COPY_SUCCESS');
			JError::raiseWarning(500, JText::_( 'FLEXI_TYPES_COPY_FAILED' ));
		} else {
			$msg = JText::_('FLEXI_TYPES_COPY_SUCCESS');
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=types', $msg );
	}
	
	
	function toggle_jview()
	{
		$cid  = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		
		$toggle_count = 0;
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
		} else {
			$model = $this->getModel('type');
			
			foreach($cid as $id) {
				if (!$id) continue;
				//$type = $model->getItem($id);
				// Initialise variables.
				$type	= JTable::getInstance('flexicontent_types', '');
				
				// Attempt to load the row.
				$type->load($id);
				
				// Check for a table object error.
				if ($type->getError()) {
					JError::raiseWarning(500, $type->getError() );
					break;
				}
				
				$attribs = json_decode($type->get('attribs'));
				$attribs->allow_jview = $attribs->allow_jview ? '0' : '1';  // toggle
				$attribs = json_encode($attribs);
				
				$db = JFactory::getDBO();
				$query = "UPDATE #__flexicontent_types SET attribs=".$db->Quote($attribs) ." WHERE id = ".$id;
				$db->setQuery($query);
				$result = $db->execute();
				if ($db->getErrorNum())
					JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				else
					$toggle_count++;
			}
		}
		
		$msg = $toggle_count ? 'Toggle view method for '.$toggle_count.' types' : '';
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg);
	}
}