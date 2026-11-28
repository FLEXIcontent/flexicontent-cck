<?php
/**
 * @package     FLEXIcontent
 * @subpackage  JCE link browser
 * @copyright   Copyright (C) 2014 FLEXIcontent project. All rights reserved.
 * @license     GNU/GPL 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Installs and removes the FLEXIcontent link browser of the JCE editor.
 *
 * JCE 2.9 and JCE 3 load link extensions of other components from the "jce" plugin group, so the link browser is
 * a Joomla plugin of that group (folder links_flexicontentlinks next to this file). It is installed only on sites
 * that have JCE, by the dashboard of FLEXIcontent, and it is removed together with FLEXIcontent.
 */
class FlexicontentJceLinks
{
	// Element of the plugin: JCE takes the part before "_" as the kind of extension, the rest as its name
	const ELEMENT = 'links_flexicontentlinks';


	/**
	 * Find out which generation of JCE is installed
	 *
	 * @param   string  $root  Path of the site
	 *
	 * @return  string  '3' for JCE 3.x, '2' for JCE 2.x, '' when JCE is not installed
	 */
	public static function detectLayout($root)
	{
		// JCE 3 has moved the editor into its editor plugin
		if (is_dir($root . '/plugins/editors/jce/Wfe'))
		{
			return '3';
		}

		// JCE 2.x has the editor, with its link extensions, inside the site component
		if (is_dir($root . '/components/com_jce/editor/extensions/links'))
		{
			return '2';
		}

		return '';
	}


	/**
	 * The files and folder that FLEXIcontent copied into JCE 2.x before the link browser became a plugin
	 *
	 * JCE 2.9 loads only its own link extension from that folder, and these copies never get an update
	 *
	 * @param   string  $root  Path of the site
	 *
	 * @return  array   The paths that exist
	 */
	public static function getLegacyCopy($root)
	{
		$base  = $root . '/components/com_jce/editor/extensions/links/flexicontentlinks';
		$paths = array();

		foreach (array($base . '.php', $base . '.xml', $base) as $path)
		{
			if (file_exists($path))
			{
				$paths[] = $path;
			}
		}

		return $paths;
	}


	/**
	 * Install or update the plugin, if JCE is installed and its editor plugin is enabled
	 *
	 * @param   string  $root  Path of the site
	 *
	 * @return  string  'none' (no JCE), 'current', 'installed', 'updated' or 'failed'
	 */
	public static function install($root = JPATH_ROOT)
	{
		if (self::detectLayout($root) === '' || !PluginHelper::isEnabled('editors', 'jce'))
		{
			return 'none';
		}

		self::removeLegacyCopy($root);

		$source = $root . '/components/com_flexicontent/librairies/JCE/' . self::ELEMENT;
		$target = $root . '/plugins/jce/' . self::ELEMENT;
		$row    = self::getExtension();

		if ($row && self::sameFiles($source, $target))
		{
			return 'current';
		}

		$app = Factory::getApplication();
		$db  = Factory::getContainer()->get(DatabaseInterface::class);

		if (!is_dir($source) || !self::getInstaller($db)->install($source))
		{
			$app->enqueueMessage(Text::_('FLEXI_FAILED_TO') . ' ' . Text::_($row ? 'JLIB_INSTALLER_UPDATE' : 'JLIB_INSTALLER_INSTALL') . ' JCE Links plugin for FLEXIcontent', 'warning');

			return 'failed';
		}

		// JCE publishes every plugin of its group when it is installed. A new plugin has to be published to be
		// listed by JCE, but a plugin that was unpublished on this site stays unpublished after an update
		$db->setQuery('UPDATE #__extensions SET enabled = ' . ($row ? (int) $row->enabled : 1) . self::whereExtension($db))->execute();

		// The list of the published plugins is cached
		try
		{
			$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', array('defaultgroup' => ''));
			$cache->clean('com_plugins');
			$cache->clean('_system');
		}
		catch (\Throwable $e)
		{
		}

		$app->enqueueMessage('<span class="badge">' . Text::_($row ? 'FLEXI_UPDATED' : 'FLEXI_INSTALLED') . '</span> JCE Links plugin for FLEXIcontent', 'message');

		return $row ? 'updated' : 'installed';
	}


	/**
	 * Remove the plugin and the copies of earlier versions, called when FLEXIcontent is uninstalled
	 *
	 * @param   string  $root  Path of the site
	 *
	 * @return  bool|null  NULL when the plugin was not installed
	 */
	public static function uninstall($root = JPATH_ROOT)
	{
		self::removeLegacyCopy($root);

		$row = self::getExtension();

		if (!$row)
		{
			return null;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		return (bool) self::getInstaller($db)->uninstall('plugin', (int) $row->extension_id);
	}


	/**
	 * Delete the copies that earlier versions made into JCE 2.x
	 *
	 * @param   string  $root  Path of the site
	 *
	 * @return  int  Number of the files and folders that were found
	 */
	public static function removeLegacyCopy($root)
	{
		$paths = self::getLegacyCopy($root);

		foreach ($paths as $path)
		{
			if (is_dir($path) && !is_link($path))
			{
				$entries = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
					RecursiveIteratorIterator::CHILD_FIRST
				);

				foreach ($entries as $entry)
				{
					$entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
				}

				@rmdir($path);
			}
			else
			{
				@unlink($path);
			}
		}

		return count($paths);
	}


	/**
	 * The row of the plugin in the extensions table
	 *
	 * @return  object|null
	 */
	protected static function getExtension()
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery('SELECT extension_id, enabled FROM #__extensions' . self::whereExtension($db));

		return $db->loadObject() ?: null;
	}


	protected static function whereExtension($db)
	{
		return ' WHERE type = ' . $db->quote('plugin') . ' AND folder = ' . $db->quote('jce') . ' AND element = ' . $db->quote(self::ELEMENT);
	}


	protected static function getInstaller($db)
	{
		$installer = new Installer;

		// Joomla 6 no longer gives a new installer its database
		$installer->setDatabase($db);
		$installer->setOverwrite(true);
		$installer->setUpgrade(true);

		return $installer;
	}


	/**
	 * Check that every file of the package is installed and has the same content
	 *
	 * @param   string  $source  Folder of the package
	 * @param   string  $target  Folder of the installed plugin
	 *
	 * @return  bool
	 */
	protected static function sameFiles($source, $target)
	{
		if (!is_dir($source) || !is_dir($target))
		{
			return false;
		}

		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

		foreach ($files as $file)
		{
			$installed = $target . '/' . str_replace('\\', '/', $files->getSubPathName());

			if (!is_file($installed) || md5_file($installed) !== md5_file($file->getPathname()))
			{
				return false;
			}
		}

		return true;
	}
}
