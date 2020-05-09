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

JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

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
			$associations = $id ? self::getItemAssociations($id) : array();

			if (!$associations)
			{
				return self::_getMenuAssociations($view, $id);
			}

			$category = JTable::getInstance('flexicontent_categories', '');

			foreach ($associations as $tag => $assoc)
			{
				$category->load(array('id' => $assoc->catid));

				$title_slug = $assoc->id . ':' . $assoc->alias;
				$cat_slug   = $category->id . ':' . $category->alias;

				$return[$tag] = FlexicontentHelperRoute::getItemRoute($title_slug, $cat_slug, 0, $assoc);
			}
			return $return;
		}

		elseif ($view === 'category')
		{
			$cid = $jinput->getInt('cid');

			$associations = $cid ? self::getCatAssociations($cid) : array();

			if (!$associations)
			{
				return self::_getMenuAssociations($view, $cid);
			}

			$category = JTable::getInstance('flexicontent_categories', '');
			$urlvars  = flexicontent_html::getCatViewLayoutVars($catmodel = null, $use_slug = true);

			foreach ($associations as $tag => $assoc)
			{
				$category->load(array('id' => $assoc->id));
				$title_slug = $category->id . ':' . $category->alias;

				$return[$tag] = FlexicontentHelperRoute::getCategoryRoute($title_slug, 0, $urlvars, $assoc);
			}

			return $return;
		}

		else
		{
			// We do not have do associations for legacy views, use current Joomla menu item associations
			return array();
		}
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


	private static function _getMenuAssociations($view, $id)
	{
		if ($view == 'item')
		{
			$record = JTable::getInstance('flexicontent_items', '');
			$record->load(array('id' => $id));
		}
		elseif ($view == 'category')
		{
			$record = JTable::getInstance('flexicontent_categories', '');
			$record->load(array('id' => $id));
		}

		if (($view !== 'item' && $view !== 'category') || $record->language !== '*')
		{
			return array();
		}

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$menus  = $app->getMenu();
		$Itemid = $jinput->getInt('Itemid', 0);

		$langAssociations = JLanguageAssociations::getAssociations('com_menus', '#__menu', 'com_menus.item', $Itemid, 'id', '', '');
		$associations     = array();

		foreach ($langAssociations as $tag => $menu_item)
		{
			$menu    = $menus->getItem($menu_item->id);
			$moption = isset($menu->query['option']) ? $menu->query['option'] : '';
			
			$matched = false;

			if (0 && $moption !== 'com_flexicontent')
			{
				// Active menu is a Non-flexicontent menu item, e.g. a Joomla category or Joomla article menu item
				// just switch to associated menu item without also pointing to the record
				$associations[$tag] = 'index.php?Itemid=' . $menu_item->id;
			}

			elseif ($view === 'item')
			{
				$title_slug = $record->id . ':' . $record->alias;
				$cat_slug   = self::_getItemCatSlug($record, $menu);

				$associations[$tag] = FlexicontentHelperRoute::getItemRoute($title_slug, $cat_slug, $menu_item->id, $record);
			}
			elseif ($view === 'category')
			{
				$title_slug = $record->id . ':' . $record->alias;
				$urlvars    = flexicontent_html::getCatViewLayoutVars($catmodel = null, $use_slug = true);

				$associations[$tag] = FlexicontentHelperRoute::getCategoryRoute($title_slug, $menu_item->id, $urlvars, $record);
			}
			else
			{
				// UNHANDLED view case, switch to associated menu item without also pointing to the record
				$associations[$tag] = 'index.php?Itemid=' . $menu_item->id;
			}
		}

		return $associations;
	}



	/**
	 *  Get category slug for a content item, considering if the given menu item points to a category belonging to the item
	 */
	private static function _getItemCatSlug($record, $menu)
	{
		$db = JFactory::getDbo();

		$moption = isset($menu->query['option']) ? $menu->query['option'] : '';
		$mview   = isset($menu->query['view']) ? $menu->query['view'] : '';
		$mcid    = isset($menu->query['cid']) ? $menu->query['cid'] : '';

		/**
		 * Get the categories assigned to the item
		 */
		$catids  = array();

		if ($moption === 'com_flexicontent' && $mview === 'category' && $mcid)
		{
			$query = $db->getQuery(true)->select('catid')
				->from('#__flexicontent_cats_item_relations')
				->where('itemid = ' . (int) $record->id);

			$catids = $db->setQuery($query)->loadColumn();
		}

		// Use matching category from menu item, otherwise use main category of item
		$matched_cid = in_array($mcid, $catids) ? $mcid : $record->catid;

		$cat = JTable::getInstance('flexicontent_categories', '');
		$cat->load(array('id' => $matched_cid));

		return $matched_cid . ':' . ($cat ? $cat->alias : '');
	}
}

