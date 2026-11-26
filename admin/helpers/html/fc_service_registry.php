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

/**
 * Register legacy JHtml helper files with Joomla's HTML service registry.
 *
 * HTMLHelper::addIncludePath() was removed in Joomla 7; two-segment keys such as
 * 'fcitems.edit_link' now resolve exclusively through the service registry.
 *
 * @param   string  $path  Directory containing JHtml<Name>.php helper files
 *
 * @return  void
 */
if (!function_exists('flexicontent_register_html_helpers'))
{
	function flexicontent_register_html_helpers($path)
	{
		if (!is_dir($path))
		{
			return;
		}

		$registry = \Joomla\CMS\HTML\HTMLHelper::getServiceRegistry();

		foreach (glob(rtrim($path, '/\\') . '/*.php') as $file)
		{
			$key = basename($file, '.php');

			if ($key === 'index' || $registry->hasService($key))
			{
				continue;
			}

			require_once $file;

			$class = 'JHtml' . ucfirst($key);

			if (class_exists($class, false))
			{
				$registry->register($key, $class);
			}
		}
	}
}
