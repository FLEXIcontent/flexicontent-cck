<?php
/**
 * @version 1.5 stable $Id: fields.php 1640 2013-02-28 14:45:19Z ggppdk $
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
 * FLEXIcontent Component Fields Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFields extends FlexicontentController
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
		$this->registerTask( 'copy_wvalues', 'copy' );
		
		$this->registerTask( 'exportxml', 'export' );
		$this->registerTask( 'exportsql', 'export' );
		$this->registerTask( 'exportcsv', 'export' );
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

		$model = $this->getModel('field');
		$user  = JFactory::getUser();
		$app   = JFactory::getApplication();
		$jinput = $app->input;

		$task  = $jinput->get('task', '', 'cmd');
		$data  = $jinput->get('jform', array(), 'array');

		// calculate access
		$field_id = (int) $data['id'];
		$is_authorised = !$field_id ?
			$user->authorise('flexicontent.createfield', 'com_flexicontent') :
			$user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $field_id) ;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
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
		// Workaround for field's specific properties being clear by validation since they are not present in field.xml
		$validated_data['attribs'] = @ $data['attribs'];
		
		if ( $model->store($validated_data) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=field&id='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=field';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=fields';
					break;
			}
			$msg = JText::_( 'FLEXI_FIELD_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_FIELD' );
			JError::raiseWarning( 500, $model->getError() );
			$link = 'index.php?option=com_flexicontent&view=field';
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
		$tbl = 'flexicontent_fields';
		$redirect_url = 'index.php?option=com_flexicontent&view=fields';
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
		$user	 = JFactory::getUser();
		$model = $this->getModel('fields');
		$cid  = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id = (int)$cid[0];

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=fields', '');
			return;
		}

		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$msg = '';
		if (!$model->publish($cid, 1))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_FIELD_PUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$itemcache = JFactory::getCache('com_flexicontent_items');
		$itemcache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
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
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$field_id = (int)$cid[0];
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		if (!$model->canunpublish($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_UNPUBLISH_THESE_FIELDS' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$msg = '';
		if (!$model->publish($cid, 0))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_FIELD_UNPUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$itemcache = JFactory::getCache('com_flexicontent_items');
		$itemcache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}

	
	/**
	 * Logic to toggele boolean property of fields
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function toggleprop()
	{
		$user   = JFactory::getUser();
		$model  = $this->getModel('fields');
		$cid    = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$propname = JRequest::getCmd( 'propname', null, 'default' );
		$field_id = (int)$cid[0];

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_TOGGLE_PROPERTY' ) );
		} else {
			
			$asset = 'com_flexicontent.field.' . $field_id;
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);
			
			if ( !$is_authorised ) {
				$msg = '';
				JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			} else {
				$unsupported = 0; $locked = 0;
				$affected = $model->toggleprop($cid, $propname, $unsupported, $locked);
				if ($affected === false) {
					$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
					JError::raiseWarning( 500, $model->getError() );
				} else {
					// A message about total count of affected rows , and about skipped fields (unsupported or locked)
					$prop_map = array('issearch'=>'FLEXI_TOGGLE_TEXT_SEARCHABLE', 'isfilter'=>'FLEXI_TOGGLE_FILTERABLE',
						'isadvsearch'=>'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', 'isadvfilter'=>'FLEXI_TOGGLE_ADV_FILTERABLE');
					$property_fullname = isset($prop_map[$propname]) ? "'".JText::_($prop_map[$propname])."'" : '';
					$msg = JText::sprintf( 'FLEXI_FIELDS_TOGGLED_PROPERTY', $property_fullname, $affected);
					if ($affected < count($cid))
						$msg .= '<br/>'.JText::sprintf( 'FLEXI_FIELDS_TOGGLED_PROPERTY_FIELDS_SKIPPED', $unsupported + $locked, $unsupported, $locked);
					
					// Clean cache as needed
					$cache = JFactory::getCache('com_flexicontent');
					$cache->clean();
					$itemcache = JFactory::getCache('com_flexicontent_items');
					$itemcache->clean();
				}
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
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
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$field_id = (int)$cid[0];

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseNotice(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			$msg = '';
			JError::raiseNotice(500, JText::_( 'FLEXI_YOU_CANNOT_REMOVE_CORE_FIELDS' ));
		} else {
			
			$asset = 'com_flexicontent.field.' . $field_id;
			$is_authorised = $user->authorise('flexicontent.deletefield', $asset);
			
			if ( !$is_authorised ) {
				$msg = '';
				JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			} else if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
				JError::raiseWarning( 500, $model->getError() );
			} else {
				$msg = count($cid).' '.JText::_( 'FLEXI_FIELDS_DELETED' );
				$cache = JFactory::getCache('com_flexicontent');
				$cache->clean();
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
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
		JRequest::setVar( 'view', 'field' );
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
		$model = $this->getModel('field');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id = (int)$cid[0];

		// calculate access
		$is_authorised = !$field_id ?
			$user->authorise('flexicontent.createfield', 'com_flexicontent') :
			$user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $field_id) ;

		// check access
		if ( !$is_authorised )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}

		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$view->display();
	}


	/**
	 * Logic to order up/down a field
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function reorder($dir=null)
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));
		
		// Get variables: model, user, field id, new ordering
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		} else if ( $model->move($dir) ){
			// success
		} else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ORDER' );
			JError::raiseWarning( 500, $model->getError() );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields');
	}


	/**
	 * Logic to orderup a field
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
	 * Logic to orderdown a field
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
	 * Logic to mass ordering fields
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));
		
		// Get variables: model, user, field id, new ordering
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$order = JRequest::getVar( 'order', array(0), 'post', 'array' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		} else if(!$model->saveorder($cid, $order)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = JText::_( 'FLEXI_NEW_ORDERING_SAVED' );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}

	/**
	 * Logic to set the access level of the Fields
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));

		$user  = JFactory::getUser();
		$model = $this->getModel('fields');
		$task  = JRequest::getVar( 'task' );
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id = (int)$cid[0];
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$field_id];
		
		if(!$model->saveaccess( $field_id, $access )) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = '';
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=fields', $msg);
	}


	/**
	 * Logic to copy the fields
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));
		
		// Get model, user, ids of copied fields
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$task  = JRequest::getVar( 'task', 'copy' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.copyfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=fields');
			return;
		}
		
		// Remove core fields
		$core_cid = array();
		$non_core_cid = array();
		
		// Copying of core fields is not allowed
		foreach ($cid as $id) {
			if ($id < 15) {
				$core_cid[] = $id;
			} else {
				$non_core_cid[] = $id;
			}
		}
		
		// Remove uneditable fields
		$auth_cid = array();
		$non_auth_cid = array();
		
		// Cannot copy fields you cannot edit
		foreach ($non_core_cid as $id)
		{
			$asset = 'com_flexicontent.field.' . $id;
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);
			
			if ($is_authorised) {
				$auth_cid[] = $id;
			} else {
				$non_auth_cid[] = $id;
			}
		}
		
		// Try to copy fields
		$ids_map = $model->copy( $auth_cid, $task == 'copy_wvalues');
		if ( !$ids_map ) {
			$msg = JText::_( 'FLEXI_FIELDS_COPY_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = '';
			if (count($ids_map)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_COPY_SUCCESS', count($ids_map)) . ' ';
			}
			if ( count($auth_cid)-count($ids_map) ) {
				//$msg .= JText::sprintf('FLEXI_FIELDS_SKIPPED_DURING_COPY', count($auth_cid)-count($ids_map)) . ' ';
			}
			if (count($core_cid)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_CORE_FIELDS_NOT_COPIED', count($core_cid)) . ' ';
			}
			if (count($non_auth_cid)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_UNEDITABLE_FIELDS_NOT_COPIED', count($non_auth_cid)) . ' ';
			}
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$app = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		
		$filter_type = $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );
		if ($filter_type)
		{
			$app->setUserState( $option.'.fields.filter_type', '' );
			$msg .= ' '.JText::_('FLEXI_TYPE_FILTER_CLEARED_TO_VIEW_NEW_FIELDS');
		}
		$this->setRedirect('index.php?option=com_flexicontent&view=fields', $msg );
	}


	/**
	 * Method to select new state for many items
	 * 
	 * @since 1.5
	 */
	function selectsearchflag()
	{
		$btn_class = 'hasTooltip btn btn-small';
		
		$state['issearch'] = array( 'name' =>'FLEXI_TOGGLE_TEXT_SEARCHABLE', 'desc' =>'FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE_DESC', 'icon' => 'search', 'btn_class' => 'btn-success', 'clear' => true );
		$state['isfilter'] = array( 'name' =>'FLEXI_TOGGLE_FILTERABLE', 'desc' =>'FLEXI_FIELD_CONTENT_LIST_FILTERABLE_DESC', 'icon' => 'filter', 'btn_class' => 'btn-success', 'clear' => true );
		$state['isadvsearch'] = array( 'name' =>'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', 'desc' =>'FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE_DESC', 'icon' => 'search', 'btn_class' => 'btn-info', 'clear' => true );
		$state['isadvfilter'] = array( 'name' =>'FLEXI_TOGGLE_ADV_FILTERABLE', 'desc' =>'FLEXI_FIELD_ADVANCED_FILTERABLE_DESC', 'icon' => 'filter', 'btn_class' => 'btn-info', 'clear' => true );
		
?><div id="flexicontent" class="flexicontent" style="padding-top:5%;"><?php
		
		foreach($state as $shortname => $statedata) {
			$css = "width:216px; margin:0px 24px 12px 0px; text-align: left;";
			$link = JURI::base(true)."/index.php?option=com_flexicontent&task=fields.toggleprop&propname=".$shortname."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1";
			$icon = $statedata['icon'];
			
			if ($shortname=='issearch') echo '<br/><span class="label">'. JText::_( 'FLEXI_TOGGLE' ).'</span> '.JText::_( 'Content Lists' ).'<br/>';
			else if ($shortname=='isadvsearch') echo '<br/><span class="label">'. JText::_( 'FLEXI_TOGGLE' ).'</span> '.JText::_( 'Search View' ).'<br/>';
			?>
			<span style="<?php echo $css; ?>" class="<?php echo $btn_class.' '.$statedata['btn_class']; ?>" title="<?php echo JText::_( $statedata['desc'] ); ?>" data-placement="right"
				onclick="window.parent.document.adminForm.propname.value='<?php echo $shortname; ?>'; window.parent.document.adminForm.boxchecked.value==0  ?  alert('<?php echo JText::_('FLEXI_NO_ITEMS_SELECTED'); ?>')  :  window.parent.Joomla.submitbutton('fields.toggleprop')"
			>
				<span class="icon-<?php echo $icon; ?>"></span><?php echo JText::_( $statedata['name'] ); ?>
			</span>
			<?php
			if ( isset($statedata['clear']) ) echo '<div class="fcclear"></div>';
		}

?></div><?php

		return;
	}
}
