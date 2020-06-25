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

		$categories = JCategories::getInstance('Content', $options);
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
