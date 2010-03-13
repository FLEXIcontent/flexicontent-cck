<?php
/**
 * @version 1.5 stable $Id: flexicontent_tags.php 183 2009-11-18 10:30:48Z vistamedia $
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

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_tags extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var string */
	var $name				= '';
	/** @var string */
	var $alias				= '';
	/** @var int */
	var $published			= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time	= '';

	function flexicontent_tags(& $db) {
		parent::__construct('#__flexicontent_tags', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		// Not typed in a name?
		if (trim( $this->name ) == '') {
			$this->_error = JText::_( 'FLEXI_ADD_NAME' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}
		
		$alias = JFilterOutput::stringURLSafe($this->name);

		if(empty($this->alias) || $this->alias === $alias ) {
			$this->alias = $alias;
		}
		
		/** check for existing name */
		$query = 'SELECT id'
				.' FROM #__flexicontent_tags'
				.' WHERE name = '.$this->_db->Quote($this->name)
				;
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('FLEXI_TAG_NAME_ALREADY_EXIST', $this->name));
			//$this->_error = JText::sprintf('TAG NAME ALREADY EXIST', $this->name);
			return false;
		}
	
		return true;
	}
}
?>