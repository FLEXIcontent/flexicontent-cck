<?php
/**
 * @package		Joomla.Administrator
 * @subpackage	com_users
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * Users component debugging helper.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_users
 * @since		1.6
 */
class UsersHelperDebug
{
	/**
	 * Get a list of the components.
	 *
	 * @return	array
	 * @since	1.6
	 */
	static function getComponents()
	{
		// Initialise variable.
		$db		= \Joomla\CMS\Factory::getDbo();
		$query	= $db->getQuery(true);

		$query->select('name AS text, element AS value')
			->from('#__extensions')
			->where('enabled >= 1')
			->where('type ='.$db->Quote('component'));

		$items = $db->setQuery($query)->loadObjectList();

		if (count($items)) {
			$lang = \Joomla\CMS\Factory::getLanguage();

			foreach ($items as &$item)
			{
				// Load language
				$extension 	= $item->value;
				$source 	= JPATH_ADMINISTRATOR . '/components/' . $extension;
					$lang->load("$extension.sys", JPATH_ADMINISTRATOR, null, false, true)
				||	$lang->load("$extension.sys", $source, null, false, true);

				// Translate component name
				$item->text = \Joomla\CMS\Language\Text::_($item->text);
			}
			unset($item);  // unset the variable reference to avoid trouble if variable is reused, thus overwritting last pointed variable

			// Sort by component name
			ArrayHelper::sortObjects($items, 'text', 1, true, $lang->getLocale());
		}

		return $items;
	}

	/**
	 * Get a list of the actions for the component or code actions.
	 *
	 * @param	string	The name of the component.
	 *
	 * @return	array
	 * @since	1.6
	 */
	public static function getDebugActions($component = null)
	{
		$actions	= array();

		// Try to get actions for the component
		if (!empty($component))
		{
			$component_actions = \Joomla\CMS\Access\Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/' . $component . '/access.xml',
				"/access/section[@name='component']/"
			);

			if (!empty($component_actions)) {
				foreach($component_actions as &$action)
				{
					echo $action->name . " -- ";
					if ( StringHelper::substr( $action->name , 0 , 5) != 'core.' ) continue;
					$action_title = str_replace('core.', '', $action->name); //$action->title;
					$actions[$action_title] = array($action->name, $action->description);
				}
				unset($action);  // unset the variable reference to avoid trouble if variable is reused, thus overwritting last pointed variable
			}
		}

		// Use default actions from configuration if no component selected or component doesn't have actions
		if (empty($actions)) {
			$filename = JPATH_ADMINISTRATOR.'/components/com_config/models/forms/application.xml';

			if (is_file($filename)) {
				$xml = simplexml_load_file($filename);

				foreach($xml->children()->fieldset as $fieldset)
				{
					if ('permissions' == (string) $fieldset['name']) {
						foreach ($fieldset->children() as $field)
						{
							if ('rules' == (string) $field['name']) {
								foreach ($field->children() as $action)
								{
									$actions[(string) $action['title']] = array(
										(string) $action['name'],
										(string) $action['description']
									);
								}
								break;
								break;
								break;
							}
						}
					}
				}

				// Load language
				$lang 		= \Joomla\CMS\Factory::getLanguage();
				$extension 	= 'com_config';
				$source 	= JPATH_ADMINISTRATOR . '/components/' . $extension;

					$lang->load("$extension.sys", JPATH_ADMINISTRATOR, null, false, true)
				||	$lang->load("$extension.sys", $source, null, false, true);
			}
		}

		return $actions;
	}

 	/**
	 * Get a list of filter options for the levels.
	 *
	 * @return	array	An array of JHtmlOption elements.
	 */
	static function getLevelsOptions()
	{
		// Build the filter options.
		$options	= array();
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '1', \Joomla\CMS\Language\Text::sprintf('COM_USERS_OPTION_LEVEL_COMPONENT', 1));
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '2', \Joomla\CMS\Language\Text::sprintf('COM_USERS_OPTION_LEVEL_CATEGORY', 2));
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '3', \Joomla\CMS\Language\Text::sprintf('COM_USERS_OPTION_LEVEL_DEEPER', 3));
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '4', '4');
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '5', '5');
		$options[]	= \Joomla\CMS\HTML\HTMLHelper::_('select.option', '6', '6');

		return $options;
	}
}
