<?php
/**
 * @version 1.5 stable $Id: tags.php 1655 2013-03-16 17:55:25Z ggppdk $
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

use Joomla\String\StringHelper;

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

// Manually import in case used by frontend, then model will not be autoloaded correctly via getModel('name')
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'tag.php');

/**
 * FLEXIcontent Component Tags Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTags extends FlexicontentController
{
	var $records_dbtbl = 'flexicontent_tags';
	var $records_jtable = 'flexicontent_tags';
	var $record_name = 'tag';
	var $record_name_pl = 'tags';
	var $_NAME = 'TAG';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register task aliases
		$this->registerTask( 'add',          'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'apply_ajax',   'save' );
		$this->registerTask( 'save2new',     'save' );
		$this->registerTask( 'save2copy',    'save' );

		$this->registerTask( 'import',       'import' );

		$this->option = $this->input->get('option', '', 'cmd');
		$this->task   = $this->input->get('task', '', 'cmd');
		$this->view   = $this->input->get('view', '', 'cmd');
		$this->format = $this->input->get('format', '', 'cmd');

		// Get custom return URL, if this was present in the HTTP request
		$this->returnURL = $this->input->get('return-url', null, 'base64');
		$this->returnURL = $this->returnURL ? base64_decode($this->returnURL) : $this->returnURL;

		// Check return URL if empty or not safe and set a default one
		if ( ! $this->returnURL || ! flexicontent_html::is_safe_url($this->returnURL) )
		{
			if ($this->view == $this->record_name)
			{
				$this->returnURL = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			else if ( !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']) )
			{
				$this->returnURL = $_SERVER['HTTP_REFERER'];
			}
			else
			{
				$this->returnURL = null;
			}
		}

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTags;
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Retrieve form data these are subject to basic filtering
		$data  = $this->input->get('jform', array(), 'array');  // Unfiltered data, validation will follow via jform

		// Set into model: id (needed for loading correct item), and type id (e.g. needed for getting correct type parameters for new items)
		$data['id'] = (int) $data['id'];
		$isnew = $data['id'] == 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel($this->record_name);
		$model->setId($data['id']);  // Make sure id is correct
		$record = $model->getItem();

		// The save2copy task needs to be handled slightly differently.
		if ($this->task == 'save2copy')
		{
			// Check-in the original row.
			if ($model->checkin($data['id']) === false)
			{
				// Check-in failed
				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				// Set the POSTed form data into the session, so that they get reloaded
				$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session

				// For errors, we redirect back to refer
				$this->setRedirect( $_SERVER['HTTP_REFERER'] );

				if ($this->input->get('fc_doajax_submit'))
					jexit(flexicontent_html::get_system_messages_html());
				else
					return false;
			}

			// Reset the ID, the multilingual associations and then treat the request as for Apply.
			$isnew = 1;
			$data['id'] = 0;
			$data['associations'] = array();
			$this->task = 'apply';

			// Keep existing model data (only clear ID)
			$model->set('id', 0);
			$model->setProperty('_id', 0);
		}

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if ( !$is_authorised )
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ($this->input->get('fc_doajax_submit'))
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		// Validate Form data
		$form = $model->getForm($data, false);
		$validated_data = $model->validate($form, $data);

		// Check for validation error
		if (!$validated_data)
		{
			// Get the validation messages and push up to three validation messages out to the user
			$errors	= $form->getErrors();
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
			{
				$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
			}

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );

			if ($this->input->get('fc_doajax_submit'))
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		// Extra custom step before model store
		if ($this->_beforeModelStore($validated_data, $data) === false)
		{
			$app->enqueueMessage($this->getError(), 'error');
			$app->setHeader('status', 500, true);

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );

			if ($this->input->get('fc_doajax_submit'))
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		if ( !$model->store($validated_data) )
		{
			$app->enqueueMessage($model->getError() ?: JText::_( 'FLEXI_ERROR_SAVING_'. $this->_NAME ), 'error');
			$app->setHeader('status', 500, true);

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );

			if ($this->input->get('fc_doajax_submit'))
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		// Clear dependent cache data
		$this->_clearCache();

		// Checkin the record
		$model->checkin();

		switch ($this->task)
		{
			case 'apply' :
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name . '&id='.(int) $model->get('id');
				break;

			case 'save2new' :
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name;
				break;

			default :
				$link = $this->returnURL;
				break;
		}
		$msg = JText::_( 'FLEXI_'. $this->_NAME .'_SAVED' );

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}


	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$redirect_url = $this->returnURL;
		flexicontent_db::checkin($this->records_jtable, $redirect_url, $this);
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
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$model = $this->getModel($this->record_name_pl);

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEM_PUBLISH'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);
			return;
		}

		// Calculate access
		$cid_noauth = array();
		$is_authorised = $this->canManage;

		// Check access
		if ( !$is_authorised )
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);
			return;
		}
		else if (count($cid_noauth))
		{
			$app->enqueueMessage("You cannot change state of records : ", implode(', ', $cid_noauth), 'warning');
		}

		// Publish the record(s)
		$msg = '';
		if (!$model->publish($cid, 1))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$total = count($cid);
		$msg = $total . ' ' . JText::_( 'FLEXI_' . $this->_NAME . '_PUBLISHED' );

		// Clear dependent cache data
		$this->_clearCache();
		
		$this->setRedirect($this->returnURL, $msg);
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
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$model = $this->getModel($this->record_name_pl);

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEM_UNPUBLISH'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);
			return;
		}

		// Calculate access
		$cid_noauth = array();
		$cid_locked = array();
		$model->canunpublish($cid, $cid_noauth, $cid_locked);
		$cid = array_diff($cid, $cid_noauth, $cid_locked);
		$is_authorised = count($cid);

		// Check access
		if ( !$is_authorised )
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::_('FLEXI_YOU_CANNOT_UNPUBLISH_THESE_'. $this->_NAME .'S'), 'error')
				: $app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);
			return;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_BEING_OF_CORE_TYPE', count($cid_locked), JText::_('FLEXI_'. $this->_NAME .'S')) . '<br/>', 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_NOT_AUTHORISED', count($cid_noauth), JText::_('FLEXI_'. $this->_NAME .'S')) . '<br/>', 'warning')
			: false;

		// Unpublish the record(s)
		$msg = '';
		if (!$model->publish($cid, 0))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$total = count($cid);
		$msg = $total . ' ' . JText::_( 'FLEXI_' . $this->_NAME . '_UNPUBLISHED' );

		// Clear dependent cache data
		$this->_clearCache();
		
		$this->setRedirect($this->returnURL, $msg);
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
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$model = $this->getModel($this->record_name_pl);

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEM_DELETE'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);
			return;
		}

		// Calculate access
		$cid_noauth = array();
		$cid_locked = array();
		$model->candelete($cid, $cid_noauth, $cid_locked);
		$cid = array_diff($cid, $cid_noauth, $cid_locked);
		$is_authorised = count($cid);

		// Check access
		if ( !$is_authorised )
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::_('FLEXI_YOU_CANNOT_REMOVE_CORE_'. $this->_NAME .'S'), 'error')
				: $app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);
			return;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_BEING_OF_CORE_TYPE', count($cid_locked), JText::_('FLEXI_'. $this->_NAME .'S')) . '<br/>', 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_NOT_AUTHORISED', count($cid_noauth), JText::_('FLEXI_'. $this->_NAME .'S')) . '<br/>', 'warning')
			: false;

		// Delete the record(s)
		$msg = '';
		if (!$model->delete($cid))
		{
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			throw new Exception($msg, 500);
		}

		$total = count( $cid );
		$msg = $total . ' ' . JText::_( 'FLEXI_'. $this->_NAME .'S_DELETED' );

		// Clear dependent cache data
		$this->_clearCache();

		$this->setRedirect($this->returnURL, $msg);
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$data = $this->input->get('jform', array(), 'array');  // Unfiltered data (no need for filtering)
		$this->input->set('cid', (int) $data['id']);

		$this->checkin();
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model  = $this->getModel($this->record_name);
		$record = $model->getItem();

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if ( !$is_authorised )
		{
			$app->setHeader('status', '403 Forbidden', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			return;
		}

		// Check if record is checked out by other editor
		if ( $model->isCheckedOut($user->get('id')) )
		{
			$app->setHeader('status', '400 Bad Request', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() )
		{
			$app->setHeader('status', '400 Bad Request', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');
			return;
		}

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * return: string
	 * 
	 * @since 1.5
	 */
	private function _clearCache()
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$itemcache = JFactory::getCache('com_flexicontent_items');
		$itemcache->clean();
	}


	/**
	 * Method for doing some record type specific work before calling model store
	 *
	 * return: string
	 * 
	 * @since 1.5
	 */
	private function _beforeModelStore(& $validated_data, & $data)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');
	}
}