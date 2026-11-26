<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Helper for mod_flexicategories
 *
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @since       1.5
 */
abstract class ModFlexiCategoriesHelper
{
	/** Resolve the child layout independently of the outer category list. */
	public static function getItemsLayoutPath($layout)
	{
		$layout = (string) $layout;
		$path = \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_flexicategories', $layout . '_items');

		// A legacy module may select the item partial itself as its layout.
		if (basename($path) === 'default.php' && str_ends_with($layout, '_items'))
		{
			$path = \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_flexicategories', $layout);
		}

		// Joomla returns the outer default.php when the requested partial is missing.
		if (basename($path) === 'default.php')
		{
			return \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_flexicategories', 'default_items');
		}

		return $path;
	}

	/**
	 * Get list of articles
	 *
	 * @param   \Joomla\Registry\Registry  &$params  module parameters
	 *
	 * @return  array
	 *
	 * @since   1.5
	 */
	public static function getList(&$params)
	{
		$options               = array();
		$options['countItems'] = $params->get('numitems', 0);

		$categories = \Joomla\CMS\Factory::getApplication()->bootComponent('Content')->getCategory($options);
		$category   = $categories->get($params->get('parent', 'root'));

		if ($category != null)
		{
			$items = $category->getChildren();

			if ($params->get('count', 0) > 0 && count($items) > $params->get('count', 0))
			{
				$items = array_slice($items, 0, $params->get('count', 0));
			}

			return $items;
		}
	}
}
