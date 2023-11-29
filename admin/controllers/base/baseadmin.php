<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentController', JPATH_BASE . DS . 'components' . DS . 'com_flexicontent' . DS . 'controller.php');
require_once('traitbase.php');

/**
 * FLEXIcontent BaseAdmin Controller
 *
 * @since 3.3
 */
class FlexicontentControllerBaseAdmin extends FlexicontentController
{
	use FCControllerTraitBase;

	static $record_limit = 100000;

	var $records_dbtbl = 'flexicontent_records';
	var $records_jtable = 'flexicontent_records';

	var $record_name = 'record';
	var $record_name_pl = 'records';

	var $_NAME = 'RECORD';
	var $record_alias = 'alias';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		/**
		 * Register task aliases
		 */
		$this->registerTask('add',          'edit');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		// These will be usable only if (plural) records model has 'canDelRelated' Flag
		$this->registerTask('remove_cascade',   'remove');
		$this->registerTask('remove_relations', 'remove');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');


		/**
		 * OVERRIDE parent controler registerTask() mappings
		 * Wrap them to 'publish' (which is a Wrapper to our 'changestate' method)
		 */
		$this->registerTask('publish',    'publish');
		$this->registerTask('unpublish', 'unpublish');
		$this->registerTask('archive', 'archive');
		$this->registerTask('trash', 'trash');
		$this->registerTask('report', 'report');

		$this->option = $this->input->get('option', '', 'cmd');
		$this->task   = $this->input->get('task', '', 'cmd');
		$this->view   = $this->input->get('view', $this->record_name, 'cmd');
		$this->format = $this->input->get('format', '', 'cmd');

		// Get referer URL from HTTP request and validate it
		$this->refererURL = !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER'])
			? $_SERVER['HTTP_REFERER']
			: JUri::base();

		// Get return URL from HTTP request and validate it
		$this->returnURL = $this->_getReturnUrl();

		// Can manage ACL
		$this->canManage = false;

		/**
		 * Common messages, the derived controller may override these
		 */

		// Error messages
		$this->err_locked_recs_changestate = 'FLEXI_ROW_STATE_NOT_MODIFIED_DUE_ASSOCIATED_DATA';
		$this->err_locked_recs_delete      = 'FLEXI_ROWS_NOT_DELETED_DUE_ASSOCIATED_DATA';
		$this->err_noauth_recs_changestate = 'FLEXI_ROW_STATE_NOT_MODIFIED_DUE_NO_ACCESS';
		$this->err_noauth_recs_delete      = 'FLEXI_ROWS_NOT_DELETED_DUE_NO_ACCESS_OR_NOT_IN_TRASH';

		// Warning messages
		$this->warn_locked_recs_skipped     = 'FLEXI_SKIPPED_N_ROWS_WITH_ASSOCIATIONS';
		$this->warn_locked_recs_skipped_del = 'FLEXI_SKIPPED_N_ROWS_WITH_ASSOCIATIONS';
		$this->warn_noauth_recs_skipped     = 'FLEXI_SKIPPED_N_ROWS_UNAUTHORISED';
		$this->warn_noauth_recs_skipped_del = 'FLEXI_SKIPPED_N_ROWS_DUE_TO_NO_ACL_OR_NOT_IN_TRASH';

		// Messages about related data
		$this->msg_relations_deleted        = 'FLEXI_ASSIGNMENTS_DELETED';
	}


	/*
	 * Terminate
	 * - CASE 1: either do no redirection / no termination, only returning exit data (controller task was executed by custom code)
	 * - CASE 2: or setting HTTP header and doing a JSON response with data or error message
	 * - CASE 3: or setting HTTP header and enqueuing an error/success message and doing a redirect
	 */
	function terminate($exitData = null, & $exitMessages = null, $data = null)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// CASE 1: interactive mode
		if ($this->runMode === 'interactive')
		{
			// Return messages to the caller
			$exitMessages = array();

			foreach	($this->exitMessages as $msg)
			{
				$exitMessages[] = array(key($msg) => JText::_(reset($msg)));
			}

			return $exitData;
		}

		// Standalone modes, set HTTP headers, also get value of 'status' header
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$httpStatus = $this->exitSuccess ? '200 OK' : '400 Bad Request';

		foreach	($this->exitHttpHead as $header)
		{
			if (key($header) == 'status')
			{
				$httpStatus = key($header);
			}

			$app->setHeader(key($header), reset($header), true);
		}

		// CASE 2: standalone mode with JSON response:  Set HTTP headers, and create a jsonrpc response
		if ($this->format === 'json')
		{
			if (!empty($this->exitLogTexts))
			{
				$log_filename = 'filemanager_upload_' . ($user->id) . '.php';
				jimport('joomla.log.log');
				JLog::addLogger(
					array(
						'text_file' => $log_filename,  // Sets the target log file
					'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
					),
					JLog::ALL,  // Sets messages of all log levels to be sent to the file
					array('com_flexicontent.filemanager')  // category of logged messages
				);

				foreach	($this->exitLogTexts as $msg)
				{
					JLog::add(reset($msg), key($msg), 'com_flexicontent.filemanager');
				}
			}

			$msg_text_all = array();

			foreach	($this->exitMessages as $msg)
			{
				$msg_text_all[] = JText::_(reset($msg));
			}

			$msg_text_all = implode(' <br/> ', $msg_text_all);

			if ($this->exitSuccess)
			{
				jexit('{"jsonrpc" : "2.0", "result" : ' . json_encode($msg_text_all) . ', "data" : ' . json_encode($data) . '}');
			}
			else
			{
				jexit('{"jsonrpc" : "2.0", "error" : {"code": ' . $httpStatus . ', "message": ' . json_encode($msg_text_all) . '}, "data" : ' . json_encode($data) . '}');
			}
		}

		// CASE 3: standalone mode with HTML response:  Set HTTP headers, enqueue messages and optionally redirect
		foreach	($this->exitMessages as $msg)
		{
			$app->enqueueMessage(JText::_(reset($msg)), key($msg));
		}

		// Redirect or return
		if ($this->returnURL)
		{
			$app->redirect($this->returnURL . ($httpStatus == '403 Forbidden' ? '' : '&' . JSession::getFormToken() . '=1'));
		}
		else
		{
			return ! $this->exitSuccess ? false : null;
		}
	}


	/**
	 * Logic to save a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();

		$ctrl_task = 'task=' . $this->record_name_pl . '.';
		$original_task = $this->task;

		// Retrieve form data these are subject to basic filtering
		$data  = $this->input->get('jform', array(), 'array');  // Unfiltered data, validation will follow via jform

		// Validate ID and set is new flag
		$data['id'] = (int) $data['id'];
		$isnew = $data['id'] == 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel($this->record_name);

		// Make sure Primary Key is correctly set into the model ... (needed for loading correct item)
		$model->setId($data['id']);
		$record = $model->getItem();

		// The save2copy task needs to be handled slightly differently.
		if ($this->task === 'save2copy')
		{
			// Check-in the original row.
			if ($model->checkin($data['id']) === false)
			{
				// Check-in failed
				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				// Set the POSTed form data into the session, so that they get reloaded
				$app->setUserState('com_flexicontent.edit.' . $this->record_name . '.data', $data);      // Save the jform data in the session

				// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
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
		elseif ($this->task === 'apply_ajax')
		{
			$this->task = 'apply';
		}

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
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


		/**
		 * Basic Form data validation
		 */

		// Get the JForm object, but do not pass any data we only want the form object,
		// in order to validate the data and not create a filled-in form
		$form = $model->getForm();

		// Validate Form data (record properties and parameters specified in XML file)
		$validated_data = $model->validate($form, $data);


		/**
		 * Perform validation / manipulation of the already validated data,
		 * run this even if validation failed, in case we want to handle this case too
		 */
		$extraChecks = $this->_afterModelValidation($validated_data, $data, $model);


		/**
		 * Redirect on validation errors or on other checks failing
		 */
		if (!$validated_data || !$extraChecks)
		{
			// Check for validation errors
			if (!$validated_data)
			{
				// Get any 'form' validation messages and push up to three validation messages out to the user
				$errors	= $form->getErrors();

				for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
				{
					$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
				}
			}

			// Check for errors in after-validation handler
			if (!$extraChecks)
			{
				$app->enqueueMessage($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME), 'error');
			}

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session

			// Validation error, reload edit form using referer URL
			$this->setRedirect($this->refererURL);

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

			// Propably recoverable error, reload edit form using referer URL
			$this->setRedirect($this->refererURL);

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}


		/**
		 * Try to store the form data into the item
		 */

		// If saving fails, do any needed cleanup, and then redirect back to record form
		if (!$model->store($validated_data))
		{
			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session

			// Set error message and the redirect URL (back to the record form)
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setError($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME));
			$this->setMessage($this->getError(), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			// Try to check-in the record, but ignore any new errors
			try
			{
				!$isnew ? $model->checkin() : true;
			}
			catch (Exception $e)
			{
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
		$this->_cleanCache();

		// Check in the record and get record id in case of new item
		$model->checkin();
		$validated_data['id'] = $isnew ? (int) $model->get('id') : $validated_data['id'];


		/**
		 * Saving is done, decide where to redirect
		 */

		$msg  = JText::_('FLEXI_' . $this->_NAME . '_SAVED');
		$tmpl = $this->input->getCmd('tmpl');

		switch ($this->task)
		{
			// REDIRECT CASE FOR APPLY / SAVE AS COPY: Save and reload the edit form
			case 'apply':
				if ($app->isClient('administrator') || in_array($this->record_name, array('review')))
				{
					$link = 'index.php?option=com_flexicontent&' . $ctrl_task . 'edit&view=' . $this->record_name
						. '&id=' . (int) $model->get('id') . ($tmpl ? '&tmpl=' . $tmpl : '');
				}
				// REDIRECT CASE: Return to the form 's original referer after item saving
				else
				{
					$link = $this->returnURL;
				}
				break;

			// REDIRECT CASE FOR SAVE and NEW: Save and load new record form
			case 'save2new':
				if ($app->isClient('administrator'))
				{
					$link = 'index.php?option=com_flexicontent&view=' . $this->record_name . ($tmpl ? '&tmpl=' . $tmpl : '');
				}

				// REDIRECT CASE: Return to the form 's original referer after item saving
				else
				{
					$link = $this->returnURL;
				}
				break;

			// REDIRECT CASES FOR SAVING
			default:
				if ($app->isClient('administrator'))
				{
					$link = $this->returnURL;
				}

				// REDIRECT CASE: Return to the form 's original referer after item saving
				else
				{
					$link = $this->returnURL;
				}
				break;
		}

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		// return;  // comment above and decomment this one to profile the saving operation

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$redirect_url = $this->returnURL;
		flexicontent_db::checkin($this->records_jtable, $redirect_url, $this);
	}


	/**
	 * Cancel the edit, check in the record and return to the records manager
	 *
	 * @return bool
	 *
	 * @since 3.3
	 */
	public function cancel()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Set record ID (JForm) into the request array variable cid[] (expected by the 'checkin' task)
		$raw_data = $this->input->get('jform', array(), 'array');
		$cid = $raw_data['id'] ? (int) $raw_data['id'] : $this->input->getInt('id', 0);
		$this->input->set('cid', $cid);

		// Check in the record (if possible) and redirect (typically) to records manager
		$this->checkin();

		// Set redirect URL
		$this->setRedirect($this->returnURL);

		return true;
	}


	/**
	 * Logic to modify the state of records, other state modifications tasks are wrappers to this task
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function changestate($state = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();

		// Get models
		$model = $this->getModel($this->record_name_pl);
		$record_model = $this->getModel($this->record_name);

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		$state_aliases = array(
			'PE' => -3,
			'OQ' => -4,
			'IP' => -5,
			'P'  =>  1,
			'U'  =>  0,
			'A'  =>  2,
			'T'  => -2
		);

		$state = strlen($state)
			? $state
			: $this->input->get('newstate', '', 'string');

		$state = isset($state_aliases[$state])
			? $state_aliases[$state]
			: (is_numeric($state) ? (int) $state : null);

		// Check for valid state
		if ($state === null || !isset($record_model->supported_conditions[$state]))
		{
			$app->enqueueMessage(JText::_('Invalid State') . ': ' . $state, 'error');
			$app->redirect($this->returnURL);
		}

		// Calculate access
		$cid_noauth = array();
		$cid_locked = array();

		$model->canDoAction($cid, $cid_noauth, $cid_locked, $state);
		$cid = array_diff($cid, $cid_noauth, $cid_locked);

		$is_authorised = count($cid);

		// Check access
		if (!$is_authorised)
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::sprintf($this->err_locked_recs_changestate, JText::_('FLEXI_' . $this->_NAME . 'S')), 'error')
				: $app->enqueueMessage(JText::sprintf($this->err_noauth_recs_changestate, JText::_('FLEXI_' . $this->_NAME . 'S')), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf($this->warn_locked_recs_skipped, count($cid_locked), JText::_('FLEXI_' . $this->_NAME . 'S'))
				. ' <br> ' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '
				. implode(',', $cid_locked), 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf($this->warn_noauth_recs_skipped, count($cid_noauth), JText::_('FLEXI_' . $this->_NAME . 'S'))
				. ' <br> ' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '
				. implode(',', $cid_locked), 'warning')
			: false;

		// Do not modify records that already in target state
		$record_table = $model->getTable();
		$cid = array_keys($model->getItemsByConditions(
			array(
				'select' => array($db->quoteName($record_table->getKeyName()) . ' AS id'),
				'where'  => array(
					$db->quoteName($record_table->getKeyName()) . ' IN (' . implode(',', ArrayHelper::toInteger($cid)) . ')',
					$model->state_col . ' <> '. (int) $state,
				),
			), false
		));

		if (count($cid))
		{
			// Some record types need to be changed atomically to allow plugin triggering ... and other custom code execution
			$atomic_change_record_types = array('item');

			/**
			 * Change state of the record(s), note cache will be cleaned in subsequent step
			 */
			if (in_array($this->record_name, $atomic_change_record_types))
			{
				$count = 0 ;

				foreach ($cid as $item_id)
				{
					$result = $record_model->setitemstate($item_id, $state, $_cleanCache = false);

					// Check for errors during state changing
					if ($result === false)
					{
						$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $record_model->getError(), 'warning');
					}
					else
					{
						$count++;
					}
				}

				$result = true;
			}
			else
			{
				$result = $model->changestate($cid, $state);

				// Check for errors during state changing
				if ($result === false)
				{
					$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');
					$app->setHeader('status', '500', true);
					$this->setRedirect($this->returnURL);
				}

				$count = (int) $result;
			}

			// Clear dependent cache data
			$this->_cleanCache();

			// Check for errors during state changing
			if ($result === false)
			{
				return;
			}
		}

		// Set success message and redirect
		$msg = JText::sprintf('FLEXI_N_RECORDS_CHANGED_TO', $count) . ' ' . JText::_($record_model->supported_conditions[$state]);
		$this->setRedirect($this->returnURL, $msg, 'message');
	}


	/**
	 * Logic to change the state of a tag
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function setitemstate()
	{
		flexicontent_html::setitemstate($this, 'json', $this->record_name);
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Get model
		$model   = $this->getModel($this->record_name_pl);
		$model_s = $this->getModel($this->record_name);

		// Check that request action is supported by the model
		if (in_array($this->task, array('remove_cascade', 'remove_relations')) && !$model::canDelRelated)
		{
			$app->enqueueMessage(JText::_('Unsupported task called'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);

			return false;
		}

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEM_DELETE'), 'error');
			$app->setHeader('status', 500, true);
			$this->setRedirect($this->returnURL);

			return false;
		}

		// Calculate access, if cascade removal, then pass via 'cid_locked' all records as ignore-assignments records
		$cid_noauth = array();
		$cid_locked = in_array($this->task, array('remove_cascade', 'remove_relations'))
			? $cid
			: array();

		$model->canDoAction($cid, $cid_noauth, $cid_locked, 'core.delete');

		$cid = array_diff($cid, $cid_noauth, $cid_locked);
		$is_authorised = count($cid);

		// Check access
		if (!$is_authorised)
		{
			count($cid_locked)
				? $app->enqueueMessage(JText::sprintf($this->err_locked_recs_delete, JText::_('FLEXI_' . $this->_NAME . 'S')), 'warning')
				: $app->enqueueMessage(JText::sprintf($this->err_noauth_recs_delete, JText::_('FLEXI_' . $this->_NAME . 'S')), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return false;
		}

		count($cid_locked)
			? $app->enqueueMessage(JText::sprintf($this->warn_locked_recs_skipped_del, count($cid_locked), JText::_('FLEXI_' . $this->_NAME . 'S'))
				. ' <br> ' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '
				. implode(',', $cid_locked), 'warning')
			: false;
		count($cid_noauth)
			? $app->enqueueMessage(JText::sprintf($this->warn_noauth_recs_skipped_del, count($cid_noauth), JText::_('FLEXI_' . $this->_NAME . 'S'))
				. ' <br> ' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '
				. implode(',', $cid_noauth), 'warning')
			: false;

		// Delete the record assignments
		switch ($this->task)
		{
			case 'remove_relations':
				$result = $model->delete_relations($cid);
				break;

			// Delete the record or records and their assignments
			case 'remove':
			case 'remove_cascade':
				$result = $model->delete($cid, $model_s);
				break;
		}

		// Clear dependent cache data
		$this->_cleanCache();

		// Check for errors during deletion
		if (!$result)
		{
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');
			$app->setHeader('status', '500', true);
			$this->setRedirect($this->returnURL);

			return $result;
		}

		$total = count($cid);
		$msg = $this->task === 'remove_relations'
			? JText::sprintf($this->msg_relations_deleted, $total)
			: $total . ' ' . JText::_(isset($this->msg_records_deleted) ? $this->msg_records_deleted : 'FLEXI_' . $this->_NAME . 'S_DELETED');

		$this->setRedirect($this->returnURL, $msg, 'message');
		return true;
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function edit()
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
		$viewLayout = $this->input->get('layout', $app->isClient('administrator') ? 'default' : 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Try to load review by attributes in HTTP Request
		if (0)
		{
			$record = $model->getRecord(array(
				$this->record_alias => '',
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
		$is_authorised = $model->canEdit($record);

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
	 * Logic to set the access level of the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function access()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Get model
		$model = $this->getModel($this->record_name_pl);

		// Get and santize records ids
		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Calculate access
		$cid_noauth = array();
		$is_authorised = $this->record_name === 'item'
			? FlexicontentHelperPerm::getPerm()->CanAccLvl
			: $this->canManage;

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

		/**
		 * Find and excluded records that we can not change their state
		 */
		$record_model = $this->getModel($this->record_name);

		foreach ($cid as $i => $_id)
		{
			$record = $record_model->getRecord($_id);

			if (!$record_model->canEditState($record))
			{
				$cid_noauth[] = $_id;
				unset($cid[$i]);
			}
		}

		$is_authorised = count($cid);

		$msg_noauth = JText::_('FLEXI_CANNOT_CHANGE_ACCLEVEL_ASSETS')
			. ': ' . implode(',', $cid_noauth)
			. ',' . JText::_('FLEXI_REASON_NO_PUBLISH_PERMISSION');

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage($msg_noauth, 'error');
			$app->setHeader('status', '403 Forbidden', true);

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}
		elseif (count($cid_noauth))
		{
			$app->enqueueMessage($msg_noauth, 'warning');
		}

		// Get new record access
		$accesses = $this->input->get('access', array(), 'array');
		$accesses = ArrayHelper::toInteger($accesses);

		// Change access of the record(s)
		$result = $model->saveaccess($cid, $accesses);

		// Clear dependent cache data
		$this->_cleanCache();

		// Check for errors during access changing
		if (!$result)
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_ITEM_ACCESS_LEVEL') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		$msg = count($cid) . ' ' . JText::_('FLEXI_RECORDS_MODIFIED');

		$this->setRedirect($this->returnURL, $msg, 'message');
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Currently no cache cleaned for all cases
	}


	/**
	 * Method for extra form validation after JForm validation is executed
	 *
	 * @param   array     $validated_data  The already jform-validated data of the record
	 * @param   object    $model            The Model object of current controller instance
	 * @param   array     $data            The original posted data of the record
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _afterModelValidation(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		return true;
	}


	/**
	 * Method for doing some record type specific work before calling model store
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _beforeModelStore(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		return true;
	}


	/**
	 * Logic to copy the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function copy()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		$app->enqueueMessage(JText::_('Task ' . __FUNCTION__ . ' not implemented YET'), 'error');
		$app->setHeader('status', 500, true);
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Get return URL via a client request variable, checking if it is safe (otherwise home page will be used)
	 *
	 * @return  string  A validated URL to be used typical as redirect URL when a task completes
	 *
	 * @since 3.3
	 */
	protected function _getReturnUrl()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$app = JFactory::getApplication();

		// Try 'return' from the GET / POST data (base64 encoded)
		$return = $this->input->get('return', null, 'base64');

		if ($return)
		{
			$return = base64_decode($return);
		}

		else
		{
			// Try 'referer' from the GET / POST data (htmlspecialchars encoded)
			$referer = $this->input->getString('referer', null);

			if ($referer)
			{
				$referer = htmlspecialchars_decode($referer);
			}

			// Try WEB SERVER variable 'HTTP_REFERER'
			else
			{
				$referer = null;
				// Wrong redirection in some cases, since it redirects to the form itself after saving more than once
				/*$referer = !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER'])
					? $_SERVER['HTTP_REFERER']
					: JUri::base();*/
			}

			$return = $referer;
		}

		// Check return URL if empty or not safe and set a default one
		if (!$return || !flexicontent_html::is_safe_url($return))
		{
			if ($app->isClient('administrator') && ($this->view === $this->record_name || $this->view === $this->record_name_pl))
			{
				$return = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			else
			{
				$return = $app->isClient('administrator') ? null : JUri::base();
			}
		}

		return $return;
	}


	/**
	 * Method to create a query object for getting record data (specific columns) of multiple records
	 *
	 * @param   array     $cid    an array record ids
	 * @param   array     $cid    an array columns names
	 *
	 * @return  object    return a Joomla Database Query object
	 *
	 * @since 3.3.0
	 */
	protected function _getRecordsQuery($cid, $cols)
	{
		$db = JFactory::getDbo();

		$cid = ArrayHelper::toInteger($cid);
		$cols_list = implode(',', array_filter($cols, array($db, 'quoteName')));

		$query = $db->getQuery(true)
			->select($cols_list)
			->from('#__' . $this->records_dbtbl)
			->where('id IN (' . implode(',', $cid) . ')');

		return $query;
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */

}
