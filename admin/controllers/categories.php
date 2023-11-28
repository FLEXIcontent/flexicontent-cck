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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'category.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'categories.php';

/**
 * FLEXIcontent Categories Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerCategories extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'categories';
	var $records_jtable = 'flexicontent_categories';

	var $record_name = 'category';
	var $record_name_pl = 'categories';

	var $_NAME = 'CATEGORY';
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

		// The prefix to use with controller messages.
		$this->text_prefix = 'COM_CONTENT';

		/**
		 * Register task aliases
		 */
		$this->registerTask('params',     'params');
		$this->registerTask('orderdown',  'orderdown');
		$this->registerTask('orderup',    'orderup');
		$this->registerTask('saveorder',  'saveorder');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanCats;

		// Error messages
		$this->err_locked_recs_changestate = 'FLEXI_ROW_STATE_NOT_MODIFIED_DUE_ASSOCIATED_DATA';
		$this->err_locked_recs_delete      = 'FLEXI_ROWS_NOT_DELETED_DUE_ASSOCIATED_DATA';

		// Warning messages
		$this->warn_locked_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_WITH_ASSOCIATIONS';
		$this->warn_noauth_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_UNAUTHORISED';

		// Messages about deleted records
		$this->msg_records_deleted         = 'FLEXI_CATEGORIES_DELETED';

		// Load Joomla 'com_categories' language files
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, null, true);
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
		// Form uses singular controller
		//parent::save();
		die(__FUNCTION__ . ' task must use singular controller');
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
		// Form uses singular controller
		//return parent::cancel();
		die(__FUNCTION__ . ' task must use singular controller');
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
	 * Logic to orderup a category
	 *
	 * @since   3.3.0
	 */
	public function orderup()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Move record
		$model = $this->getModel($this->record_name_pl);
		$model->move(-1);

		// Clear dependent cache data
		$this->_cleanCache();

		$this->setRedirect($this->returnURL);
	}


	/**
	 * Logic to orderdown a category
	 *
	 * @since   3.3.0
	 */
	public function orderdown()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Move record
		$model = $this->getModel($this->record_name_pl);
		$model->move(1);

		// Clear dependent cache data
		$this->_cleanCache();

		$this->setRedirect($this->returnURL);
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
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setRedirect($this->returnURL);

			return;
		}

		$id = (int) reset($cid);

		// Calculate access
		$asset = 'com_content.category.' . $id;
		$is_authorised = $user->authorise('core.edit', $asset);

		// Check access
		if (!$is_authorised)
		{
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			return;
		}

		// Get new record access
		$accesses = $this->input->get('access', array(), 'array');
		$accesses = ArrayHelper::toInteger($accesses);
		$access = $accesses[$id];

		if (!$model->saveaccess($id, $access))
		{
			$msg = JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError();
			throw new Exception($msg, 500);
		}

		// Clear dependent cache data
		$this->_cleanCache();

		$this->setRedirect($this->returnURL);
	}


	/**
	 * Proxy for getModel
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  The array of possible config values. Optional.
	 *
	 * @return  JModelLegacy  The model.
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Categories', $prefix = 'FlexicontentModel', $config = array('ignore_request' => true))
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$name = $name ?: 'Categories';
		require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . strtolower($name) . '.php';

		return parent::getModel($name, $prefix, $config);
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
		// Clean cache
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
	}


	/**
	 * Rebuild the nested set tree.
	 *
	 * @return  bool  False on failure or error, true on success.
	 *
	 * @since  3.2
	 */
	public function rebuild()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$extension = 'com_content';
		$this->setRedirect(JRoute::_('index.php?option=com_flexicontent&view=categories', false));

		/** @var CategoriesModelCategory $model */
		$model = $this->getModel($this->record_name);

		if ($model->rebuild())
		{
			// Rebuild succeeded.
			$this->setMessage(JText::_('COM_CATEGORIES_REBUILD_SUCCESS'));

			return true;
		}

		// Rebuild failed.
		$this->setMessage(JText::_('COM_CATEGORIES_REBUILD_FAILURE'));

		return false;
	}
}
