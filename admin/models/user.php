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

defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

jimport('legacy.model.admin');

if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'src'.DS.'Model'.DS.'UserModel.php');
	class _FlexicontentModelUser extends Joomla\Component\Users\Administrator\Model\UserModel {}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'user.php');
	class _FlexicontentModelUser extends UsersModelUser {}
}

/**
 * User model.
 *
 */
class FlexicontentModelUser extends _FlexicontentModelUser
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
	 * @return  mixed  A \Joomla\CMS\Form\Form object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		$pluginParams = new Registry;

		if (\Joomla\CMS\Plugin\PluginHelper::isEnabled('user', 'joomla'))
		{
			$plugin = \Joomla\CMS\Plugin\PluginHelper::getPlugin('user', 'joomla');
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
		if (\Joomla\CMS\Factory::getApplication()->getIdentity()->requireReset)
		{
			$form->setFieldAttribute('password', 'required', 'true');
			$form->setFieldAttribute('password2', 'required', 'true');
		}

		// When multilanguage is set, a user's default site language should also be a Content Language
		if (\Joomla\CMS\Language\Multilanguage::isEnabled())
		{
			$form->setFieldAttribute('language', 'type', 'frontend_language', 'params');
		}

		// The user should not be able to set the requireReset value on their own account
		if ((int) $userId === (int) \Joomla\CMS\Factory::getApplication()->getIdentity()->id)
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
		$data = \Joomla\CMS\Factory::getApplication()->getUserState('com_flexicontent.edit.user.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		$this->preprocessData('com_users.profile', $data, 'user');

		return $data;
	}
}
