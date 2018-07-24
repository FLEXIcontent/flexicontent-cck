<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('ContentHelper', JPATH_ADMINISTRATOR . '/components/com_content/helpers/content.php');
JLoader::register('FlexicontentHelperRoute', JPATH_SITE . '/components/com_flexicontent/helpers/route.php');
JLoader::register('CategoryHelperAssociation', JPATH_ADMINISTRATOR . '/components/com_categories/helpers/association.php');

/**
 * Content Component Association Helper
 *
 * @package     Joomla.Site
 * @subpackage  com_content
 * @since       3.0
 */
abstract class FlexicontentHelperAssociation extends CategoryHelperAssociation
{
	/**
	 * Method to get the associations for a given item
	 *
	 * @param   integer  $id    Id of the item
	 * @param   string   $view  Name of the view
	 *
	 * @return  array   Array of associations for the item
	 *
	 * @since  3.0
	 */

	public static function getAssociations($id = 0, $view = null)
	{
		$jinput = JFactory::getApplication()->input;
		$view   = is_null($view) ? $jinput->get('view', '', 'cmd') : $view;
		$id     = empty($id) ? $jinput->get('id', 0, 'int') : $id;

		if ($view === 'item')
		{
			if ($id)
			{
				//$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $id);
				$associations = FlexicontentHelperAssociation::getItemAssociations($id);
				$return = array();

				foreach ($associations as $tag => $item)
				{
					$return[$tag] = FlexicontentHelperRoute::getItemRoute($item->id, $item->catid, 0, $item);
				}
				return $return;
			}
		}

		elseif ($view === 'category')
		{
			$cid = $jinput->getInt('cid');
			if ($cid)
			{
				$associations = FlexicontentHelperAssociation::getCatAssociations($cid);
				$return = array();

				foreach ($associations as $tag => $item)
				{
					$return[$tag] = FlexicontentHelperRoute::getCategoryRoute($item->catid, 0, array(), $item);
				}
				return $return;
			}
		}

		return array();
	}


	public static function getItemAssociations($item_id)
	{
		if (!$item_id)
		{
			return array();
		}

		$db = JFactory::getDbo();
		$query = 'SELECT i.language, ie.type_id, '
			. '  CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(":", i.id, i.alias) ELSE i.id END as id, '
			. '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(":", c.id, c.alias) ELSE c.id END as catid '
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__content AS i ON i.id = k.id'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id '
			. ' JOIN #__categories AS c ON c.id = i.catid '
			. ' WHERE a.id = '. $item_id .' AND a.context = ' . $db->Quote('com_content.item');

		$translations = $db->setQuery($query)->loadObjectList('language');

		return $translations;
	}

	public static function getCatAssociations($cat_id)
	{
		if (!$cat_id)
		{
			return array();
		}

		$db = JFactory::getDbo();
		$query = 'SELECT c.language, '
			. '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(":", c.id, c.alias) ELSE c.id END as catid '
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__categories AS c ON c.id = k.id '
			. ' WHERE a.id = '. $cat_id .' AND a.context = ' . $db->Quote('com_categories.item');

		$translations = $db->setQuery($query)->loadObjectList('language');

		return $translations;
	}
}
