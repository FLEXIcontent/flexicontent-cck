<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\Component\Categories\Administrator\Helper\CategoryAssociationHelper as J4_CategoryAssociationHelper;

JLoader::register('FlexicontentHelperRoute', JPATH_SITE . '/components/com_flexicontent/helpers/route.php');
JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');


/*
 * Class is different in J3 ...
 */
if (!FLEXI_J40GE)
{
	JLoader::register('CategoryHelperAssociation', JPATH_ADMINISTRATOR . '/components/com_categories/helpers/association.php');

	class CategoryAssociationHelper extends CategoryHelperAssociation {}
}
else
{
	class CategoryAssociationHelper extends J4_CategoryAssociationHelper {}
}


/**
 * Content Component Association Helper
 *
 * @package     Joomla.Site
 * @subpackage  com_content
 * @since       3.0
 */
abstract class FlexicontentHelperAssociation extends CategoryAssociationHelper
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

			foreach ($associations as $tag => $assoc)
			{
				$return[$tag] = FlexicontentHelperRoute::getItemRoute($assoc->title_slug, $assoc->cat_slug, 0, $assoc);
			}
			return $return;
		}

		elseif ($view === 'category')
		{
            $cid    = $jinput->getInt('cid');
            $layout = $jinput->getCmd('layout');
            if ($layout === 'tags')
            {
                $tagid = $jinput->getInt('tagid', 0);
                $associations = $tagid ? self::getTagAssociations($tagid) : array();

                if ($associations)
                {
					$urlvars = flexicontent_html::getCatViewLayoutVars($catmodel = null, $use_slug = true);

					foreach ($associations as $tag => $assoc)
					{
						$return[$tag] = FlexicontentHelperRoute::getCategoryRoute($cid, 0, $urlvars, $assoc);
					}

					return $return;
				}
            }

			$associations = $cid ? self::getCatAssociations($cid) : array();

			if (!$associations)
			{
				return self::_getMenuAssociations($view, $cid);
			}

			$urlvars = flexicontent_html::getCatViewLayoutVars($catmodel = null, $use_slug = true);

			foreach ($associations as $tag => $assoc)
			{
				$return[$tag] = FlexicontentHelperRoute::getCategoryRoute($assoc->title_slug, 0, $urlvars, $assoc);
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
		$query = 'SELECT i.language, ie.type_id, i.id, i.catid, '
			. '  CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(":", i.id, i.alias) ELSE i.id END as title_slug, '
			. '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(":", c.id, c.alias) ELSE c.id END as cat_slug '
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__content AS i ON i.id = k.id'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id '
			. ' JOIN #__categories AS c ON c.id = i.catid '
			. ' WHERE a.id = '. $item_id .' AND a.context = ' . $db->Quote('com_content.item');

		$translations = $db->setQuery($query)->loadObjectList('language');

		return $translations;
	}


	public static function getTagAssociations($tag_id)
	{

		if (!$tag_id)
		{
			return array();
		}

		$db = JFactory::getDbo();
        if (!FLEXI_FALANG) return array();

        $query  =
			//'SELECT la.lang_code AS language, t.id AS id, '
			'SELECT la.lang_code AS language, t.id AS id, '
			. '  CASE WHEN CHAR_LENGTH(fat.value) THEN fat.value ELSE t.name END as title, '
            . '  CONCAT_WS(":", t.id, t.alias) AS title_slug '
            //. '  CASE WHEN CHAR_LENGTH(faa.value) THEN CONCAT_WS(":", t.id, faa.value) ELSE CONCAT_WS(":", t.id, t.alias) END as title_slug '
            .' FROM #__flexicontent_tags AS t'
            .' JOIN #__tags AS jt ON t.jtag_id = jt.id'
            . ' JOIN #__falang_content AS fat ON fat.reference_table = "tags" '
            . '   AND fat.reference_field = "title" AND fat.reference_id = t.jtag_id'
            . ' LEFT JOIN #__falang_content AS faa ON faa.reference_table = "tags" '
            . '   AND faa.reference_field = "alias" AND faa.reference_id = t.jtag_id'
            . ' JOIN #__languages AS la ON la.lang_id = fat.language_id '
            . ' WHERE t.id = '. $tag_id;
            ;
		$translations = $db->setQuery($query)->loadObjectList('language');
		$lang = flexicontent_html::getSiteDefaultLang();
		if ($translations && !isset($translations[$lang]))
		{
			$tag = $db->setQuery('SELECT t.name AS title, CONCAT_WS(":", t.id, t.alias) AS title_slug '
				. ' FROM #__flexicontent_tags AS t'
	            . ' WHERE t.id = '. $tag_id
			)->loadObject();
			$translations[$lang] = (object) array('language' => $lang, 'id' => ($tag_id), 'title' => $tag->title, 'title_slug' => $tag->title_slug);
		}
		//echo '<pre>'; print_r($translations); echo '</pre>'; exit;

		return $translations;
	}


    public static function getCatAssociations($cat_id)
    {
        if (!$cat_id)
        {
            return array();
        }

        $db = JFactory::getDbo();
        $query = 'SELECT c.language, c.id, '
            . '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(":", c.id, c.alias) ELSE c.id END as title_slug '
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

		return $matched_cid . ($cat ? ':' . $cat->alias : '');
	}
}

