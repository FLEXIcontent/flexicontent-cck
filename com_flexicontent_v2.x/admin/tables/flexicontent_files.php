<?php
/**
 * @version 1.5 stable $Id: flexicontent_files.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
class flexicontent_files extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var string */
	var $filename			= '';
	/** @var string */
	var $altname			= '';
	/** @var int */
	var $url				= 0;
	/** @var int */
	var $secure				= 1;
	/** @var string */
	var $ext				= '';
	/** @var text */
	var $description				= '';
	/**
	 * @var int 
	 * @TODO implement
	 */
	var $published			= 1;
	/** @var int */
	var $hits				= 0;
	/** @var date */
	var $uploaded			= '';
	/** @var int */
	var $uploaded_by		= '';
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time	= '';
	/** @var int */
	var $access 			= null;
	/** @var string */
	var $attribs	 		= null;

	function flexicontent_files(& $db) {
		parent::__construct('#__flexicontent_files', 'id', $db);
		$this->access = FLEXI_J16GE ? 1 : 0;    // Public access is 1 for J2.5 and 0 for J1.5
	}
	
	// overloaded check function
	function check()
	{		
		return true;
	}
}
?>
