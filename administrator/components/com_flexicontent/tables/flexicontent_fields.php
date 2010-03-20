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

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_fields extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $field_type			= null;
	/** @var string */
	var $name				= '';
	/** @var string */
	var $label				= '';
	/** @var string */
	var $description		= '';
	/** @var int */
	var $isfilter			= 0;
	/** @var int */
	var $iscore				= 0;
	/** @var int */
	var $issearch			= 1;
	/** @var int */
	var $isadvsearch		= 0;
	/** @var string */
	var $positions			= '';
	/** @var string */
	var $attribs	 		= null;
	/** @var int */
	var $published			= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time	= '';
	/** @var int */
	var $access 			= 0;
	/** @var int */
	var $ordering 			= null;

	function flexicontent_fields(& $db) {
		parent::__construct('#__flexicontent_fields', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		// Not typed in a label?
		if (trim( $this->label ) == '') {
			$this->_error = JText::_( 'FLEXI_ADD_LABEL' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}
				
		$newname = str_replace('-', '', JFilterOutput::stringURLSafe($this->label));

		if((empty($this->name) || $this->name === $name) && $this->iscore != 1 ) {
			$this->name = $newname;
		}

		/** check for existing name */
		$query = 'SELECT id'
				.' FROM #__flexicontent_fields'
				.' WHERE name = '.$this->_db->Quote($this->name)
				;
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('FLEXI_THIS_FIELD_NAME_ALREADY_EXIST', $this->name));
			return false;
		}
	
		return true;
	}
}
?>