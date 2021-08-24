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

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'template.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'templates.php';

/**
 * FLEXIcontent Templates Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerTemplates extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_templates';
	var $records_jtable = 'flexicontent_templates';

	var $record_name = 'template';
	var $record_name_pl = 'templates';

	var $_NAME = 'TEMPLATE';
	var $record_alias = null;

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

		// Register task aliases
		$this->registerTask('add',          'edit');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_modal',  'save');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTemplates;
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

		$type    = $this->input->getWord('type', 'items');
		$folder  = $this->input->getString('folder', 'table');
		$cfgname = $this->input->getString('cfgname');

		$isnew = 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel($this->record_name);

		// Make sure Primary Key is correctly set into the model ... (needed for loading correct item)
		$model->setId($type, $folder, $cfgname);

		// Calculate access
		$is_authorised = $this->canManage;

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

		$positions = $this->input->getString('positions',  '');
		$positions = explode(',', $positions);

		// Default filtering will remove HTML
		$post = $this->input->get->post->getArray();
		$attribs = $post['jform']['layouts'][$folder];

		// Set templates configuration
		$model->setConfig($positions, $attribs);


		/**
		 * Try to store the form data into the item
		 */

		// If saving fails, do any needed cleanup, and then redirect back to record form
		if (!$model->store($post))
		{
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


		/**
		 * Saving is done, decide where to redirect
		 */

		$msg = JText::_('FLEXI_SAVE_FIELD_POSITIONS');

		switch ($this->task)
		{
			case 'apply_modal' :
				$link = 'index.php?option=com_flexicontent&view=template&type=' . $type . '&folder=' . $folder . '&tmpl=component&ismodal=1&' . JSession::getFormToken() . '=1';
				break;

			case 'apply':
				$link = 'index.php?option=com_flexicontent&view=' . $this->record_name
					. '&type=' . $type . '&folder=' . $folder;
				break;

			// REDIRECT CASES FOR SAVING
			default:
				$link = $this->returnURL;
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

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			JError::raiseWarning(403, JText::_('FLEXI_ALERTNOTAUTH_TASK'));
			$this->setRedirect('index.php?option=com_flexicontent', '');

			return;
		}

		$this->setRedirect('index.php?option=com_flexicontent&view=templates');
	}
}
