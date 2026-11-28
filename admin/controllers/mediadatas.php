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

// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FlexicontentControllerBaseAdmin' => JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'mediadata.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'mediadatas.php';

/**
 * FLEXIcontent Mediadatas Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerMediadatas extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_mediadatas';
	var $records_jtable = 'flexicontent_mediadatas';

	var $record_name = 'mediadata';
	var $record_name_pl = 'mediadatas';

	var $_NAME = 'MEDIADATA';
	var $record_alias = 'not_applicable';

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
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');

		$this->registerTask('unapproved', 'approved');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanMediadatas;

		// Error messages
		$this->err_locked_recs_changestate = 'FLEXI_ROW_STATE_NOT_MODIFIED_DUE_ASSOCIATED_DATA';
		$this->err_locked_recs_delete      = 'FLEXI_ROWS_NOT_DELETED_DUE_ASSOCIATED_DATA';

		// Warning messages
		$this->warn_locked_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_WITH_ASSOCIATIONS';
		$this->warn_noauth_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_UNAUTHORISED';
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
		$result = parent::save();

		if ($result !== false && $this->task === 'save' && $this->input->getCmd('tmpl') === 'component')
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(
				"<script>
					setTimeout(function(){
						window.parent.fc_closeDialog('fc_modal_popup_container');
					}, 2000);
				</script>"
				, 'success'
			);
		}
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		parent::checkin();
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
		if ($this->input->getCmd('tmpl') === 'component')
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(
				"<script>
					window.parent.fc_closeDialog('fc_modal_popup_container');
				</script>"
				, 'success'
			);
		}

		return parent::cancel();
	}


	/**
	 * Logic to publish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function publish()
	{
		parent::publish();
	}


	/**
	 * Logic to unpublish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function unpublish()
	{
		parent::unpublish();
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
		parent::remove();
	}


	/**
	 * Method to toggle the approved setting of a list of records
	 *
	 * @return  void
	 *
	 * @since   3.3
	 */
	public function approved()
	{
		// Check for request forgeries
		\Joomla\CMS\Session\Session::checkToken() or jexit(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'));

		$app   = \Joomla\CMS\Factory::getApplication();
		$user  = \Joomla\CMS\Factory::getApplication()->getIdentity();

		$cid    = $this->input->get('cid', array(), 'array');
		$values = array('approved' => 1, 'unapproved' => 0);
		$value  = ArrayHelper::getValue($values, $this->task, 0, 'int');

		// Access checks.
		foreach ($cid as $i => $id)
		{
			if (!$user->authorise('core.edit.state', 'com_content.article.' . (int) $id))
			{
				// Prune items that you can't change.
				unset($cid[$i]);
				\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'notice');
			}
		}

		if (empty($cid))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Get the model.
		$record_model = $this->getModel($this->record_name);

		// Update approved flag (model will also handle cache cleaning)
		if (!$record_model->approved($cid, $value))
		{
			$app->enqueueMessage($record_model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		$message = $value == 1
			? \Joomla\CMS\Language\Text::plural('FLEXI_N_MEDIADATAS_APPROVED', count($cid))
			: \Joomla\CMS\Language\Text::plural('FLEXI_N_MEDIADATAS_UNAPPROVED', count($cid));
		$this->setRedirect($this->returnURL, $message);
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
		$app      = \Joomla\CMS\Factory::getApplication();
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', $app->isClient('administrator') ? 'default' : 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Initialize a record if not done already
		if (empty($record))
		{
			$record = $model->getItem();
		}

		// Sanity checks
		$item = null;
		$errors = null;

		$this->_preChecks(0, $item, $model, $errors);

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


		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			if (!$user->id && $app->isClient('site'))
			{
				$cparams   = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
				$uri       = \Joomla\CMS\Uri\Uri::getInstance();
				$return    = strtr(base64_encode($uri->toString()), '+/=', '-_,');           // Current URL as return URL (but we will for id / cid)
				$login_url = $cparams->get('login_page', 'index.php?option=com_users&view=login');
				$login_url .= (strstr($login_url, '?') ? '&'  : '?') . 'tmpl=component&return='.$return;

				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('Please register or login before you can edit this record'), 'warning');
				$this->setRedirect($login_url);
			}
			else
			{
				$app->setHeader('status', '403 Forbidden', true);
				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($this->returnURL);
				}
			}

			return;
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

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
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

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
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		parent::_cleanCache();

		$cache_site = FLEXIUtilities::getCache($group = '', $client = 0);
		$cache_site->clean('com_flexicontent_items');

		$cache_admin = FLEXIUtilities::getCache($group = '', $client = 1);
		$cache_admin->clean('com_flexicontent_items');

		// Also clean this as it contains Joomla frontend view cache of the component)
		$cache_site->clean('com_flexicontent');
	}


	/**
	 * Method for extra form validation after \Joomla\CMS\Form\Form validation is executed
	 *
	 * @param   array     $validated_data  The already jform-validated data of the record
	 * @param   object    $model           The Model object of current controller instance
	 * @param   array     $data            The original posted data of the record
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _afterModelValidation(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		if (!parent::_afterModelValidation($validated_data, $data, $model))
		{
			return false;
		}

		/**
		 * If user can not manage mediadatas then do "Mediadata validation on the posted data"
		 */

		if (!$this->canManage)
		{
			$validated_data = $model->submitterValidation($validated_data);

			if (!$validated_data)
			{
				return false;
			}
		}

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
	 * START OF CONTROLLER SPECIFIC METHODS
	 */


	/**
	 * Method to do prechecks for loading / saving mediadata forms
	 *
	 * @param   object    $content_id  The id of the content
	 * @param   object    $item        by reference variable to return the mediadata containing item
	 * @param   object    $model       The record model (mediadata)
	 * @param   array     $errors      by reference the array of error messages that have occured
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	private function _preChecks($content_id, & $item, $model, & $errors)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');
	}
}
