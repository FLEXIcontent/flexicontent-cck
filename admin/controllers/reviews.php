<?php
/**
 * @version 1.5 stable $Id: reviews.php 1655 2013-03-16 17:55:25Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * Register autoloader for parent controller, in case controller is executed by another component
 * We use JPATH_BASE since parent controller exists in frontend too
 */
JLoader::register('FlexicontentController', JPATH_BASE . DS . 'components' . DS . 'com_flexicontent' . DS . 'controller.php');

// Manually import in case used by frontend, then model will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'review.php';

/**
 * FLEXIcontent Component Reviews Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerReviews extends FlexicontentController
{
	var $records_dbtbl = 'flexicontent_reviews';

	var $records_jtable = 'flexicontent_reviews';

	var $record_name = 'review';

	var $record_name_pl = 'reviews';

	var $_NAME = 'REVIEW';

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
		$this->registerTask('add',          'edit');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');

		$this->option = $this->input->get('option', '', 'cmd');
		$this->task   = $this->input->get('task', '', 'cmd');
		$this->view   = $this->input->get('view', '', 'cmd');
		$this->format = $this->input->get('format', '', 'cmd');

		// Get custom return URL, if this was present in the HTTP request
		$this->returnURL = $this->input->get('return-url', null, 'base64');
		$this->returnURL = $this->returnURL ? base64_decode($this->returnURL) : $this->returnURL;

		// Check return URL if empty or not safe and set a default one
		if (! $this->returnURL || ! flexicontent_html::is_safe_url($this->returnURL))
		{
			if ($this->view == $this->record_name)
			{
				$this->returnURL = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			elseif (!empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']))
			{
				$this->returnURL = $_SERVER['HTTP_REFERER'];
			}
			else
			{
				$this->returnURL = null;
			}
		}

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanReviews;
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

		// Initialize variables
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$original_task = $this->task;

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
				$app->setUserState('com_flexicontent.edit.' . $this->record_name . '.data', $data);      // Save the jform data in the session

				// For errors, we redirect back to refer
				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($_SERVER['HTTP_REFERER']);
				}

				if ($this->input->get('fc_doajax_submit'))
				{
					jexit(flexicontent_html::get_system_messages_html());
				}
				else
				{
					return false;
				}
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

		// The apply_ajax task is treat same as apply (also same redirection in case that AJAX submit is skipped)
		elseif ($this->task == 'apply_ajax')
		{
			$this->task = 'apply';
		}

		// Calculate access
		$is_authorised = $record && $record->id
			? FlexicontentHelperPerm::getPerm()->CanCreateReviews && $model->canEdit($record)
			: FlexicontentHelperPerm::getPerm()->CanCreateReviews;

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}


		// ***
		// *** Basic Form data validation
		// ***

		// Get the JForm object, but do not pass any data we only want the form object,
		// in order to validate the data and not create a filled-in form
		$form = $model->getForm();

		// Validate Form data (record properties and parameters specified in XML file)
		$validated_data = $model->validate($form, $data);

		if (!$this->canManage)
		{
			$validated_data = $this->reviewerValidation($validated_data, $form);
		}

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
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($_SERVER['HTTP_REFERER']);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Extra custom step before model store
		if ($this->_beforeModelStore($validated_data, $data, $model) === false)
		{
			$app->enqueueMessage($this->getError(), 'error');
			$app->setHeader('status', 500, true);

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($_SERVER['HTTP_REFERER']);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		if (!$model->store($validated_data))
		{
			$app->enqueueMessage($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME), 'error');
			$app->setHeader('status', 500, true);

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session

			// For errors, we redirect back to refer
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($_SERVER['HTTP_REFERER']);
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Clear dependent cache data
		$this->_clearCache();

		// Checkin the record
		$model->checkin();

		switch ($this->task)
		{
			case 'apply' :
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name . '&id=' . (int) $model->get('id');
				break;

			case 'save2new' :
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name;
				break;

			default :
				$link = $this->returnURL;
				break;
		}

		$msg = JText::_('FLEXI_' . $this->_NAME . '_SAVED');

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}



	public function reviewerValidation($data, $form)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__)
		{
			die(__FUNCTION__ . ' : direct call not allowed');
		}

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		$review_id   = $data['id'];
		$content_id  = $data['content_id'];
		$review_type = $data['type'];

		$errors = array();

		// Validate title, decode entities, and strip HTML
		$title = flexicontent_html::dataFilter($data['title'], $maxlength=255, 'STRING', 0);

		// Validate email
		$email = $user->id ? $user->email : flexicontent_html::dataFilter($data['email'], $maxlength=255, 'EMAIL', 0);

		// Validate text, decode entities and strip HTML
		$text = flexicontent_html::dataFilter($this->input->get('text', '', 'string'), $maxlength=10000, 'STRING', 0);


		/**
		 * Check for validation failures on posted data
		 */

		if (!$content_id)
		{
			$form->setError('content_id is zero');
			return false;
		}

		if (!$email)
		{
			$form->setError('Email is invalid or empty');
			return false;
		}

		if (!$user->id)
		{
			$query = 'SELECT id FROM #__users WHERE email = ' . $db->Quote($email);
			$reviewer = $db->setQuery($query)->loadObject();

			if ($reviewer)
			{
				$form->setError('Please login');
				return false;
			}
		}

		if (!$text)
		{
			$form->setError('Text is invalid or empty');
			return false;
		}

		if ($review_type !== 'item')
		{
			$form->setError('review_type <> item is not yet supported');
			return false;
		}

		// Send response to client
		return $data;
	}



	/**
	 * Method to do prechecks for loading / saving review forms
	 *
	 * @param   object    $item       by reference variable to return the reviewed item
	 * @param   object    $field      by reference variable to return the voting (reviews) field
	 * @param   array     $errors     The array of error messages that have occured
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	private function _preReviewingChecks($content_id, & $item = null, & $field = null, $errors = null)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__)
		{
			die(__FUNCTION__ . ' : direct call not allowed');
		}

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();


		/**
		 * Load content item related to the review
		 */

		$item = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());

		if ($content_id && !$item->load($content_id))
		{
			$errors[] = 'ID: ' . $pk . ': ' . $item->getError();
			return;
		}


		/**
		 * Do voting / reviewing permissions check
		 */

		// Get voting field
		$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('voting');
		$field = $db->setQuery($query)->loadObject();

		// Load field's configuration together with type-specific field customization
		FlexicontentFields::loadFieldConfig($field, $item);

		// Load field's language files
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

		// Get needed parameters
		$allow_reviews = (int) $field->parameters->get('allow_reviews', 0);

		// Check reviews are allowed
		if (!$allow_reviews)
		{
			$errors[] = 'Reviews are disabled';
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
		ArrayHelper::toInteger($cid);

		if (!is_array($cid) || count($cid) < 1)
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
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}
		elseif (count($cid_noauth))
		{
			$app->enqueueMessage("You cannot change state of records : ", implode(', ', $cid_noauth), 'warning');
		}

		// Publish the record(s)
		$msg = '';

		if (!$model->publish($cid, 1))
		{
			$msg = JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		$total = count($cid);
		$msg = $total . ' ' . JText::_('FLEXI_' . $this->_NAME . '_PUBLISHED');

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
		ArrayHelper::toInteger($cid);

		if (!is_array($cid) || count($cid) < 1)
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
		if (!$is_authorised)
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::_('FLEXI_YOU_CANNOT_UNPUBLISH_THESE_' . $this->_NAME . 'S'), 'error')
				: $app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_WITH_ASSOCIATIONS', count($cid_locked), JText::_('FLEXI_' . $this->_NAME . 'S')) . '<br/>', 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_NOT_AUTHORISED', count($cid_noauth), JText::_('FLEXI_' . $this->_NAME . 'S')) . '<br/>', 'warning')
			: false;

		// Unpublish the record(s)
		$msg = '';

		if (!$model->publish($cid, 0))
		{
			$msg = JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		$total = count($cid);
		$msg = $total . ' ' . JText::_('FLEXI_' . $this->_NAME . '_UNPUBLISHED');

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
		ArrayHelper::toInteger($cid);

		if (!is_array($cid) || count($cid) < 1)
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
		if (!$is_authorised)
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::_('FLEXI_YOU_CANNOT_REMOVE_' . $this->_NAME . 'S'), 'error')
				: $app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_WITH_ASSOCIATIONS', count($cid_locked), JText::_('FLEXI_' . $this->_NAME . 'S')) . '<br/>', 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf('FLEXI_SKIPPED_RECORDS_NOT_AUTHORISED', count($cid_noauth), JText::_('FLEXI_' . $this->_NAME . 'S')) . '<br/>', 'warning')
			: false;

		// Delete the record(s)
		$msg = '';

		if (!$model->delete($cid))
		{
			$msg = JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		$total = count($cid);
		$msg = $total . ' ' . JText::_('FLEXI_' . $this->_NAME . 'S_DELETED');

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

		$content_id  = $this->input->get('content_id', 0, 'int');
		$review_type = $this->input->get('review_type', 'item', 'cmd');

		// Sanity checks before reviewing, content item exists, and reviewing are enabled
		$item = null;
		$field = null;
		$errors = null;

		$this->_preReviewingChecks($content_id, $item, $field, $errors);
			
		if ($errors)
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(reset($errors), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Try to load review by attributes in HTTP Request
		if ($content_id && $review_type)
		{
			$record = $model->getRecord(array(
				'content_id' => $content_id,
				'type' => $review_type,
				'user_id' => $user->id,
			));
		}

		// Try to load by unique ID or NAME
		else
		{	
			$record = $model->getItem();
		}

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $record && $record->id
			? FlexicontentHelperPerm::getPerm()->CanCreateReviews && $model->canEdit($record)
			: FlexicontentHelperPerm::getPerm()->CanCreateReviews;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Checkout the record and proceed to edit form
		if (!$model->checkout())
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 *  Method for voting (ajax)
	 *
	 * @TODO move the query part to the item model
	 * @access public
	 * @since 1.5
	 */
	public function ajaxvote()
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$session = JFactory::getSession();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		$no_ajax     = $this->input->get('no_ajax', 0, 'int');
		$user_rating = $this->input->get('user_rating', 0, 'int');
		$cid = $this->input->get('cid', 0, 'int');
		$xid = $this->input->get('xid', '', 'cmd');

		// Compatibility in case the voting originates from joomla's voting plugin
		if ($no_ajax && !$cid)
		{
			$cid = $this->input->get('id', 0, 'int');  // Joomla 's content plugin uses 'id' HTTP request variable
		}


		/**
		 * Validate xid
		 */

		$xid = empty($xid) ? 'main' : $xid;
		$xid = $xid === 'main' ? 'main' : (int) $xid;


		/**
		 * Load item
		 */

		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
		$item->load($cid);


		/**
		 * Get voting field configuration
		 */

		// Get voting field
		$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('voting');
		$field = $db->setQuery($query)->loadObject();

		// Load field's configuration together with type-specific field customization
		FlexicontentFields::loadFieldConfig($field, $item);

		// Load field's language files
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

		// Get needed parameters
		$rating_resolution = (int) $field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;

		$min_rating = 1;
		$max_rating = $rating_resolution;

		$main_counter = (int) $field->parameters->get('main_counter', 1);
		$main_counter_show_label = (int) $field->parameters->get('main_counter_show_label', 1);
		$main_counter_show_percentage = (int) $field->parameters->get('main_counter_show_percentage', 0);

		$enable_extra_votes = (int) $field->parameters->get('enable_extra_votes', '');

		$extra_counter = (int) $field->parameters->get('extra_counter', 1);
		$extra_counter_show_label = (int) $field->parameters->get('extra_counter_show_label', 1);
		$extra_counter_show_percentage = (int) $field->parameters->get('extra_counter_show_percentage', 0);


		/**
		 * Find if user has the ACCESS level required for voting
		 */

		$aid_arr = $user->getAuthorisedViewLevels();
		$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
		$has_acclvl = in_array($acclvl, $aid_arr);


		/**
		 * Create no access Redirect Message
		 */

		if (!$has_acclvl)
		{
			$logged_no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
			$guest_no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
			$no_acc_msg = $user->id ? $logged_no_acc_msg : $guest_no_acc_msg;
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';

			// Message not set create a Default Message
			if (!$no_acc_msg)
			{
				// Find name of required Access Level
				$query = 'SELECT title FROM #__viewlevels as level WHERE level.id = ' . (int) $acclvl;
				$acclvl_name = $db->setQuery($query)->loadResult();

				if (!$acclvl_name)
				{
					$acclvl_name = 'Access Level: ' . $acclvl . ' not found / was deleted';
				}

				$no_acc_msg = JText::sprintf('FLEXI_NO_ACCESS_TO_VOTE', $acclvl_name);
			}

			$error = $no_acc_msg;
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}

		/**
		 * Check if rating is NOT within valid range
		 */

		elseif ($user_rating < $min_rating || $user_rating > $max_rating)
		{
			$error = JText::sprintf( 'FLEXI_VOTE_OUT_OF_RANGE', $min_rating, $max_rating);
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}


		/**
		 * Check extra vote exists and get extra votes types
		 */

		$xids_extra = array();

		if ($enable_extra_votes)
		{
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = $field->parameters->get('extra_votes', '');
			$extra_votes = preg_split( "/[\s]*%%[\s]*/", $field->parameters->get('extra_votes', '') );

			if (empty($extra_votes[count($extra_votes)-1]))
			{
				unset( $extra_votes[count($extra_votes)-1] );
			}

			// Split extra voting ids (xid) and their titles
			foreach ($extra_votes as $extra_vote)
			{
				@ list($xid_ev, $title_ev, $desc_ev) = explode('##', $extra_vote);
				$xids_extra[$xid_ev] = 1;
			}
		}


		/**
		 * Allow XID that is either 'main' or an integer (that exists at the extra voting cases) 
		 */

		if ($xid === 'main')
		{
			if (count($xids_extra))
			{
				$error = JText::_('FLEXI_VOTE_AVERAGE_RATING_CALCULATED_AUTOMATICALLY');
				return $this->_ajaxvote_error($error, $xid, $no_ajax);
			}
		}

		elseif (!isset($xids_extra[$xid]))
		{
			$error = !$enable_extra_votes
				? JText::_('FLEXI_VOTE_COMPOSITE_VOTING_IS_DISABLED')
				: 'Voting characteristic with id: ' . $xid . ' does not exist';
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}


		/**
		 * Check: item id exists in our voting logging SESSION (array) variable, to avoid double voting
		 */

		$vote_history = $session->get('vote_history', array(),'flexicontent');
		//var_dump($vote_history); exit;

		if (!isset($vote_history[$cid]) || !is_array($vote_history[$cid]))
		{
			$vote_history[$cid] = array();
		}

		/**
		 * Allow user to change his vote. For the case that the browser was not closed,
		 * we can get rating from user's session and thus allow user to change the vote
		 */

		$old_ratings  = array();
		$rating_diffs = array();


		// Using main vote only
		if ($xid === 'main')
		{
			$voteIsComplete = true;

			$user_rating_main = $user_rating;

			$old_ratings['main'] = isset($vote_history[$cid]['main'])
				? (int) round($vote_history[$cid]['main'])
				: 0;
			$vote_history[$cid]['main'] = $user_rating_main;
		}

		// Using voting characteristics
		else
		{
			$voteIsComplete = true;
			
			$user_rating_main = 0;
			$user_ratings_completed = 0;
			$user_ratings_sum = 0;

			foreach($xids_extra as $xid_n => $i)
			{
				// Get old rating and calculate rating difference
				$old_ratings[$xid_n] = isset($vote_history[$cid][$xid_n])
					? (int) $vote_history[$cid][$xid_n]
					: 0;
				$rating_diffs[$xid_n] = $xid_n == $xid
					? $user_rating - $old_ratings[$xid_n]
					: 0;

				// Update voting history with current voting characteristic
				if ($xid_n == $xid)
				{
					$vote_history[$cid][$xid] = $user_rating;
				}

				// If at least 1 rating characteristic is missing then rating has not been completed
				if (!isset($vote_history[$cid][$xid_n]))
				{
					$voteIsComplete = false;
					continue;
				}

				// Sum up mainrating so far
				$user_ratings_completed++;
				$user_ratings_sum += (int) $vote_history[$cid][$xid_n];
			}

			// Update voting history of main vote only if there is a vote for all characteristics
			if ($voteIsComplete)
			{
				$old_ratings['main'] = isset($vote_history[$cid]['main'])
					? (int) $vote_history[$cid]['main']
					: 0;
				$user_rating_main = (int) round($user_ratings_sum / count($xids_extra));
				$vote_history[$cid]['main'] = $user_rating_main;
			}
		}

		// Calculate noz-zero 'main' vote rating difference only if there is a vote for all characteristics
		$rating_diffs['main'] = $voteIsComplete
			? $user_rating_main - $old_ratings['main']
			: 0;


		/**
		 * Retrieve last vote for the given item
		 */

		$currip = $_SERVER['REMOTE_ADDR'];
		$result	= new stdClass();

		foreach($vote_history[$cid] as $xid_n => $rating_n)
		{
			// Update only current characteristic
			if (!$voteIsComplete && $xid_n != $xid)
			{
				continue;
			}

			$old_rating = $old_ratings[$xid_n];
			$rating_diff = $rating_diffs[$xid_n];


			// Choose db table to store vote (normal or extra)
			$dbtbl = $xid_n === 'main'
				? '#__content_rating'
				: '#__flexicontent_items_extravote';

			// Second part is for defining the vote type in case of extra vote
			$and_extra_id = $xid_n !== 'main' ? ' AND field_id = ' . (int) $xid_n : '';

			$query = ' SELECT *'
				. ' FROM ' . $dbtbl . ' AS a '
				. ' WHERE content_id = ' . (int) $cid
				. ' ' . $and_extra_id;
			$db_itemratings = $db->setQuery($query)->loadObject();


			/**
			 * Voting access allowed and valid, but we will need to make
			 * some more checks (IF voting record exists AND double voting)
			 */

			// Voting record does not exist for this item, accept user's vote and insert new voting record in the db
			if (!$db_itemratings)
			{
				if ($voteIsComplete)
				{
					$query = ' INSERT ' . $dbtbl
						. ' SET content_id = ' . (int) $cid . ', '
						. '  lastip = ' . $db->Quote($currip) . ', '
						. '  rating_sum = ' . (int) $rating_n . ', '
						. '  rating_count = 1 '
						. ($xid_n !== 'main' ? ', field_id = ' . (int) $xid_n : '');

					$db->setQuery($query)->execute();
				}
			}

			// Voting record exists for this item, check if user has already voted
			else
			{
				/**
				 * If item is not in the user's voting history (session), then we check
				 * if this IP has voted for this item recently and refuse to accept vote
				 */

				if ($xid_n == $xid && !$old_rating && $currip === $db_itemratings->lastip)
				{
					$error = JText::_('FLEXI_YOU_HAVE_ALREADY_VOTED');
					return $this->_ajaxvote_error($error, $xid, $no_ajax);
				}

				//echo $db_itemratings->rating_sum. ' - ' . $rating_diff . "\n";

				/**
				 * If voting is completed, Either add all sub-votes into DB when voting is completed for the very first time
				 * -OR- if user has updated an existing voting set (update in DB only the current sub-vote and the main vote)
				 */
				if ($voteIsComplete && ($xid_n === 'main' || $xid_n == $xid))
				{
					// vote accepted update DB
					$query = 'UPDATE ' . $dbtbl
					. ' SET rating_count = rating_count + ' . ($old_rating ? 0 : 1)
					. '  , rating_sum = rating_sum + ' . ($old_rating ? $rating_diff : $rating_n)
					. '  , lastip = ' . $db->Quote($currip)
					. ' WHERE content_id = ' . (int) $cid
					. ' ' . $and_extra_id;

					$db->setQuery($query)->execute();
				}
			}

			$db_rating_sum = $db_itemratings ? (int) $db_itemratings->rating_sum : 0;
			$db_rating_count = $db_itemratings ? (int) $db_itemratings->rating_count : 0;
			
			if ($xid_n === 'main')
			{
				//$result->rating_sum_main_diff_debug  = ($voteIsComplete ? $rating_diffs['main'] : 0);
				$result->rating_sum_main  = $db_rating_sum   + ($voteIsComplete ? $rating_diffs['main'] : 0);
				$result->ratingcount_main = $db_rating_count + ($voteIsComplete && !$old_ratings['main'] ? 1 : 0);
				$result->percentage_main  = !$result->ratingcount_main ? 0 : (($result->rating_sum_main / $result->ratingcount_main) * (100 / $rating_resolution));
				$result->htmlrating_main  = ($main_counter ?
					$result->ratingcount_main . ($main_counter_show_label ? ' ' . JText::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($main_counter_show_percentage ? ' - ' : '')
					: '')
					. ($main_counter_show_percentage ? (int) $result->percentage_main . '%' : '');
			}

			// In case of composite voting being OFF only the above will be added
			elseif ($xid_n == $xid)
			{
				$result->rating_sum  = $db_rating_sum   + ($old_rating ? $rating_diffs[$xid_n] : $rating_n);
				$result->ratingcount = $db_rating_count + ($old_rating ? 0 : 1);
				$result->percentage  = !$result->ratingcount ? 0 : (($result->rating_sum / $result->ratingcount) * (100 / $rating_resolution));
				$result->htmlrating  = ($extra_counter ?
					$result->ratingcount . ($extra_counter_show_label ? ' ' . JText::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($extra_counter_show_percentage ? ' - ' : '')
					: '')
					. ($extra_counter_show_percentage ? (int) $result->percentage . '%' : '');
			}
		}


		/**
		 * Prepare response
		 */

		$html = $old_ratings[$xid]
			? '' . (100 * ($old_ratings[$xid] / $max_rating)) . '% => ' . (100 * ($user_rating / $max_rating)) . '%'
			: '' . (100 * ($user_rating / $max_rating)) . '%';

		$xid === 'main'
			? $result->html_main = $html
			: $result->html = $html;

		if ($xid !== 'main')
		{
			$result->message = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_('FLEXI_VOTE_YOUR_RATING').': '.(100*($user_rating / $max_rating)).'%
				</div>';

			if (!$voteIsComplete)
			{
				$result->message_main = '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						'.JText::sprintf('FLEXI_VOTE_PLEASE_COMPLETE_VOTING', $user_ratings_completed, count($xids_extra)).'
					</div>';
			}
			else
			{
				$result->html_main = JText::_($old_ratings['main'] ? 'FLEXI_VOTE_AVERAGE_RATING_UPDATED' : 'FLEXI_VOTE_AVERAGE_RATING_SUBMITTED');
				$result->message_main = '
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_AVERAGE_RATING_WAS_UPDATED' : 'FLEXI_VOTE_YOUR_AVERAGE_RATING_STORED' ) . ':
						<b>' . ($old_ratings['main'] ? (100 * ($old_ratings['main'] / $max_rating)) . '% => ' : '') . (100 * ($user_rating_main / $max_rating)) . '%</b>
				</div>';
			}
		}

		else
		{
			$result->message_main ='
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_RATING_WAS_CHANGED' : 'FLEXI_THANK_YOU_FOR_VOTING' ).'
				</div>';
		}

		// Set the voting data, into SESSION
		$session->set('vote_history', $vote_history, 'flexicontent');

		// Item average vote changed clean item-related caches
		if ($voteIsComplete)
		{
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent');  // Also clean this (as it contains Joomla frontend view cache)
		}

		/**
		 * Set response and exit
		 */

		if ($no_ajax)
		{
			$app->enqueueMessage($xid === 'main' ? $result->message_main.'<br/>'.$result->message : $result->message_main, 'notice');
			return;
		}
		else
		{
			$result->vote_history = print_r($vote_history[$cid], true);
			jexit(json_encode($result));
		}
	}


	/**
	 * Method to terminate the ajax voting tasking task on error
	 *
	 * @access private
	 * @since 1.0
	 */
	protected function _ajaxvote_error($mssg, $xid, $no_ajax = false)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		// Handle non ajax call
		if ($no_ajax)
		{
			JFactory::getApplication()->enqueueMessage($mssg, 'notice');
			return;
		}

		// Since voting REJECTED, avoid setting BAR percentage and HTML rating text ... someone else may have voted for the item ...
		else
		{
			$result	= new stdClass();
			$result->percentage = '';
			$result->htmlrating = '';
			$mssg = '
			<div class="fc-mssg fc-warning fc-nobgimage">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$mssg.'
			</div>';

			$xid !== 'main'
				? $result->message = $mssg
				: $result->message_main = $mssg;
			jexit(json_encode($result));
		}
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * return: string
	 *
	 * @since 3.2.0
	 */
	private function _clearCache()
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__)
		{
			die(__FUNCTION__ . ' : direct call not allowed');
		}

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
	private function _beforeModelStore(& $validated_data, & $data, $model)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__)
		{
			die(__FUNCTION__ . ' : direct call not allowed');
		}
	}


	/**
	 * Returns the content model of the item associated with the given review
	 *
	 * @param $review_id - The ID of the review
	 *
	 * @return An item model instance
	 */
	private function _getContentModel($review_id)
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__)
		{
			die(__FUNCTION__ . ' : direct call not allowed');
		}

		// Get review model and from it get the associated content ID of the review
		$review_model = $this->getModel('review');
		$review_model->setId($review_id);
		$content_id = $review_model->get('content_id');

		// Get content item owner via a new content item model
		$item_model = $this->getModel('item');
		$item_model->setId($content_id);  // Set desired content ID into the content item model

		return $item_model;
	}

}