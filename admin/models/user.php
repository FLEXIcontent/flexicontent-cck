<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_flexicontent
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
jimport('legacy.model.admin');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'user.php');

/**
 * User model.
 *
 * @since  1.6
 */
class FlexicontentModelUser extends UsersModelUser
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @since   3.2
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		$pluginParams = new Registry;
		
		if (JPluginHelper::isEnabled('user', 'joomla'))
		{
			$plugin = JPluginHelper::getPlugin('user', 'joomla');
			$pluginParams->loadString($plugin->params);
		}

		// Get the form.
		$form = $this->loadForm('com_flexicontent.user', 'user', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		// Passwords fields are required when mail to user is set to No in joomla user plugin
		$userId = $form->getValue('id');

		if ($userId === 0 && $pluginParams->get('mail_to_user', '1') === '0')
		{
			$form->setFieldAttribute('password', 'required', 'true');
			$form->setFieldAttribute('password2', 'required', 'true');
		}

		// If the user needs to change their password, mark the password fields as required
		if (JFactory::getUser()->requireReset)
		{
			$form->setFieldAttribute('password', 'required', 'true');
			$form->setFieldAttribute('password2', 'required', 'true');
		}

		// When multilanguage is set, a user's default site language should also be a Content Language
		if (JLanguageMultilang::isEnabled())
		{
			$form->setFieldAttribute('language', 'type', 'frontend_language', 'params');
		}

		// The user should not be able to set the requireReset value on their own account
		if ((int) $userId === (int) JFactory::getUser()->id)
		{
			$form->removeField('requireReset');
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.user.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		$this->preprocessData('com_users.profile', $data, 'user');

		return $data;
	}
}
