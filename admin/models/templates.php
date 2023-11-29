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

defined('_JEXEC') or die('Restricted access');

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component Templates Model
 *
 */
class FlexicontentModelTemplates extends JModelLegacy
{
	/**
	 * Record rows
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}

	/**
	 * Method to get templates data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the templates if it doesn't already exist
		if (empty($this->_data))
		{
			$this->_data = $this->_getTemplates();
		}

		return $this->_data;
	}


	/**
	 * Method to get the template list and their properties
	 *
	 * @access	private
	 * @return	array
	 * @since	1.5
	 */
	function _getTemplates()
	{
		$folders = flexicontent_tmpl::getThemes();
		$tmpls   = flexicontent_tmpl::getTemplates();

		$themes = array();

		foreach ($folders as $folder) {
			$themes[$folder] = new stdClass();
			$themes[$folder]->name  = $folder;
			$themes[$folder]->items    = isset($tmpls->items->{$folder})    ? $tmpls->items->{$folder}    : '';
			$themes[$folder]->category = isset($tmpls->category->{$folder}) ? $tmpls->category->{$folder} : '';
		}

		return $themes;
	}


	/**
	 * Method to duplicate a template folder
	 *
	 * @access	public
	 * @return	boolean	true on success
	 * @since	1.5
	 */
	function duplicate($source, $dest)
	{
		jimport('joomla.filesystem.folder');

		$path 	= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS;
		$dest	= $dest ? flexicontent_upload::sanitizedir($path, $dest) : '';

		if (!$source || !$dest) return false;

		if (!JFolder::copy($source, $dest, $path)) return false;

		return true;
	}


	/**
	 * Method to remove a template folder
	 *
	 * @access	public
	 * @return	boolean	true on success
	 * @since	1.5
	 */
	function delete($dir)
	{
		jimport('joomla.filesystem.folder');

		$path 	= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS;

		if (!$dir || ($dir === 'grid') || ($dir === 'table') || ($dir === 'faq') || ($dir === 'items-tabbed')) return false;
		if (!JFolder::delete($path.$dir)) return false;

		// delete old record
		$query 	= 'DELETE FROM #__flexicontent_templates'
				. ' WHERE template = ' . $this->_db->Quote($dir)
				;
		$this->_db->setQuery($query);
		$this->_db->execute();

		return true;
	}

}
