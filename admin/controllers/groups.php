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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'groups.php';

/**
 * FLEXIcontent (User) Groups Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerGroups extends FlexicontentControllerBaseAdmin
{

	/**
	 * @var     string  The prefix to use with controller messages.
	 * @since   1.6
	 */
	protected $text_prefix;

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
		$this->text_prefix = 'COM_USERS_GROUPS';

		// Register task aliases
		// ...

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanAuthors;
	}


	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  object  The model.
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Group', $prefix = 'FlexicontentModel', $config = array('ignore_request' => true))
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$name = $name ?: 'Group';
		require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . strtolower($name) . '.php';

		return parent::getModel($name, $prefix, $config);
	}


	/**
	 * Removes an item.
	 *
	 * Overrides JControllerAdmin::delete to check the core.admin permission.
	 *
	 * @return  boolean  Returns true on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function delete()
	{
		if (!$this->canManage)
		{
			$app = JFactory::getApplication();
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		return parent::delete();
	}

	/**
	 * Method to publish a list of records.
	 *
	 * Overrides JControllerAdmin::publish to check the core.admin permission.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function publish()
	{
		if (!$this->canManage)
		{
			$app = JFactory::getApplication();
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		parent::publish();
	}

	/**
	 * Changes the order of one or more records.
	 *
	 * Overrides JControllerAdmin::reorder to check the core.admin permission.
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.6
	 */
	public function reorder()
	{
		if (!$this->canManage)
		{
			$app = JFactory::getApplication();
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		return parent::reorder();
	}

	/**
	 * Method to save the submitted ordering values for records.
	 *
	 * Overrides JControllerAdmin::saveorder to check the core.admin permission.
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.6
	 */
	public function saveorder()
	{
		if (!$this->canManage)
		{
			$app = JFactory::getApplication();
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		return parent::saveorder();
	}

	/**
	 * Check in of one or more records.
	 *
	 * Overrides JControllerAdmin::checkin to check the core.admin permission.
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.6
	 */
	public function checkin()
	{
		if (!$this->canManage)
		{
			$app = JFactory::getApplication();
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		return parent::checkin();
	}
}
