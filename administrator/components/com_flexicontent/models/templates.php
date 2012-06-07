<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Templates Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTemplates extends JModel
{
	/**
	 * Tag data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
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
		$folders 	= flexicontent_tmpl::getThemes();
		$tmpl		= flexicontent_tmpl::getTemplates();

		$themes = array();

		foreach ($folders as $folder) {
			$themes[$folder] = new stdClass();
			$themes[$folder]->name 		= $folder;
			$themes[$folder]->items 	= isset($tmpl->items->{$folder}) ? $tmpl->items->{$folder} : '';
			$themes[$folder]->category 	= isset($tmpl->category->{$folder}) ? $tmpl->category->{$folder} : '';
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

		$path 	= JPATH_COMPONENT_SITE . DS . 'templates' . DS;
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

		$path 	= JPATH_COMPONENT_SITE . DS . 'templates' . DS;
		
		if (!$dir || ($dir == 'blog') || ($dir == 'default') || ($dir == 'faq') || ($dir == 'presentation')) return false;		
		if (!JFolder::delete($path.$dir)) return false;
		
		// delete old record
		$query 	= 'DELETE FROM #__flexicontent_templates'
				. ' WHERE template = ' . $this->_db->Quote($dir)
				;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		return true;
	}

}
?>