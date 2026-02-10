<?php
/**
* @version 0.7 stable $Id: install.php yannick berges
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2015 Berges Yannick - www.com3elles.com
* @license GNU/GPL v2

* special thanks to ggppdk and emmanuel dannan for flexicontent
* special thanks to my master Marc Studer

* FLEXIadmin module is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
**/


// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Script file of HelloWorld module
 */
class mod_flexigooglemapInstallerScript
{
	/**
	 * Method to install the extension
	 * $parent is the class calling this method
	 *
	 * @return void
	 */
	function install($parent)
	{
		//echo '<p>The module has been installed</p>';
	}


	/**
	 * Method to uninstall the extension
	 * $parent is the class calling this method
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		//echo '<p>The module has been uninstalled</p>';
	}


	/**
	 * Method to update the extension
	 * $parent is the class calling this method
	 *
	 * @return void
	 */
	function update($parent)
	{
		//echo '<p>The module has been updated to version' . $parent->get('manifest')->version) . '</p>';
	}


	/**
	 * Method to run before an install/update/uninstall method
	 * $parent is the class calling this method
	 * $type is the type of change (install, update or discover_install)
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		//echo '<p>Update is good</p>';
	}


	/**
	 * Method to run after an install/update/uninstall method
	 * $parent is the class calling this method
	 * $type is the type of change (install, update or discover_install)
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
		/** marker copy **/
		$pathSourceName = \Joomla\Filesystem\Path::clean(JPATH_ROOT.'/modules/mod_flexigooglemap/assets/marker');
		$pathDestName   = \Joomla\Filesystem\Path::clean(JPATH_ROOT.'/images/mod_flexigooglemap/marker');

		// 1. Check DESTINATION folder
		if ( !is_dir($pathDestName) && !mkdir($pathDestName) )
		{
			echo '<span class="alert alert-warning"> Error, unable to create folder: '. $pathDestName.'</span>';
		}

		// 2. Copy all files
		$files = glob($pathSourceName."/*.*");
		foreach ($files as $file)
		{
			$file_dest = basename($file);
			copy($file, $pathDestName.'/'.$file_dest);
		}


		/** cluster copy **/
		$pathSourceName2 = \Joomla\Filesystem\Path::clean(JPATH_ROOT.'/modules/mod_flexigooglemap/assets/cluster');
		$pathDestName2   = \Joomla\Filesystem\Path::clean(JPATH_ROOT.'/images/mod_flexigooglemap/cluster');

		// 1. Check DESTINATION folder
		if ( !is_dir($pathDestName2) && !mkdir($pathDestName2) )
		{
			echo '<span class="alert alert-warning"> Error, unable to create folder: '. $pathDestName2.'</span>';
		}

		// 2. Copy all files
		$files2 = glob($pathSourceName2."/*.*");
		foreach ($files2 as $file2)
		{
			$file_dest2 = basename($file2);
			copy($file2, $pathDestName2.'/'.$file_dest2);
		}
	}
}
