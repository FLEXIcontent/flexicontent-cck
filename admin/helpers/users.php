<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;

/**
 * Users component helper.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_users
 * @since       1.6
 */
class UsersHelper
{
	/**
	 * @var    \Joomla\CMS\Object\CMSObject  A cache for the available actions.
	 * @since  1.6
	 */
	protected static $actions;

	/**
	 * Configure the Linkbar.
	 *
	 * @param   string  $vName  The name of the active view.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public static function addSubmenu($vName)
	{
		JSubMenuHelper::addEntry(
			\Joomla\CMS\Language\Text::_('COM_USERS_SUBMENU_USERS'),
			'index.php?option=com_users&view=users',
			$vName == 'users'
		);

		// Groups and Levels are restricted to core.admin
		$canDo = self::getActions();

		if ($canDo->get('core.admin'))
		{
			JSubMenuHelper::addEntry(
				\Joomla\CMS\Language\Text::_('COM_USERS_SUBMENU_GROUPS'),
				'index.php?option=com_users&view=groups',
				$vName == 'groups'
			);
			JSubMenuHelper::addEntry(
				\Joomla\CMS\Language\Text::_('COM_USERS_SUBMENU_LEVELS'),
				'index.php?option=com_users&view=levels',
				$vName == 'levels'
			);
			JSubMenuHelper::addEntry(
				\Joomla\CMS\Language\Text::_('COM_USERS_SUBMENU_NOTES'),
				'index.php?option=com_users&view=notes',
				$vName == 'notes'
			);

			$extension = \Joomla\CMS\Factory::getApplication()->input->getString('extension');
			JSubMenuHelper::addEntry(
				\Joomla\CMS\Language\Text::_('COM_USERS_SUBMENU_NOTE_CATEGORIES'),
				'index.php?option=com_categories&extension=com_users',
				$vName == 'categories' || $extension == 'com_users'
			);
		}
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @return  \Joomla\CMS\Object\CMSObject
	 *
	 * @since   1.6
	 * @todo    Refactor to work with notes
	 */
	public static function getActions()
	{
		if (empty(self::$actions))
		{
			$user = \Joomla\CMS\Factory::getApplication()->getIdentity();
			self::$actions = new \Joomla\CMS\Object\CMSObject;

			$actions = \Joomla\CMS\Access\Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/' . 'com_users' . '/access.xml',
				"/access/section[@name='component']/"
			);

			foreach ($actions as $action)
			{
				self::$actions->set($action->name, $user->authorise($action->name, 'com_users'));
			}
		}

		return self::$actions;
	}

	/**
	 * Get a list of filter options for the blocked state of a user.
	 *
	 * @return  array  An array of JHtmlOption elements.
	 *
	 * @since   1.6
	 */
	static function getStateOptions()
	{
		// Build the filter options.
		$options = array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '0', \Joomla\CMS\Language\Text::_('JENABLED'));
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '1', \Joomla\CMS\Language\Text::_('JDISABLED'));

		return $options;
	}

	/**
	 * Get a list of filter options for the activated state of a user.
	 *
	 * @return  array  An array of JHtmlOption elements.
	 *
	 * @since   1.6
	 */
	static function getActiveOptions()
	{
		// Build the filter options.
		$options = array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '0', \Joomla\CMS\Language\Text::_('COM_USERS_ACTIVATED'));
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '1', \Joomla\CMS\Language\Text::_('COM_USERS_UNACTIVATED'));

		return $options;
	}

	/**
	 * Get a list of the user groups for filtering.
	 *
	 * @return  array  An array of JHtmlOption elements.
	 *
	 * @since   1.6
	 */
	static function getGroups()
	{
		$db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery(
			'SELECT a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level' .
			' FROM #__usergroups AS a' .
			' LEFT JOIN '.$db->quoteName('#__usergroups').' AS b ON a.lft > b.lft AND a.rgt < b.rgt' .
			' GROUP BY a.id, a.title, a.lft, a.rgt' .
			' ORDER BY a.lft ASC'
		);
		$options = $db->loadObjectList();

		foreach ($options as &$option)
		{
			$option->text = str_repeat('- ', $option->level).$option->text;
		}
		unset($option);  // unset the variable reference to avoid trouble if variable is reused, thus overwritting last pointed variable

		return $options;
	}

	/**
	 * Creates a list of range options used in filter select list
	 * used in com_users on users view
	 *
	 * @return  array
	 *
	 * @since   2.5
	 */
	public static function getRangeOptions()
	{
		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'today', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_TODAY')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'past_week', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_PAST_WEEK')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'past_1month', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_PAST_1MONTH')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'past_3month', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_PAST_3MONTH')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'past_6month', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_PAST_6MONTH')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'past_year', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_PAST_YEAR')),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', 'post_year', \Joomla\CMS\Language\Text::_('COM_USERS_OPTION_RANGE_POST_YEAR')),
		);
		return $options;
	}
}
