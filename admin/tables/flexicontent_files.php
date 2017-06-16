<?php
/**
 * @version 1.5 stable $Id: flexicontent_files.php 1665 2013-04-08 02:26:21Z ggppdk $
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
	var $id						= null;

	/** @var string */
	var $filename			= '';

	/** @var string */
	var $filename_original = '';

	/** @var string */
	var $altname		= '';

	/** @var text */
	var $description= '';

	/** @var int */
	var $url				= 0;

	/** @var int */
	var $secure			= 1;

	/** @var string */
	var $ext				= '';

	/** @var int */
	var $published	= 1;

	/** @var string */
	var $language		= '*';

	/** @var unsigned int */
	var $hits				= 0;

	/** @var unsigned int */
	var $size				= 0;

	/** @var int */
	var $stamp			= 1;

	/** @var date */
	var $uploaded			= '';

	/** @var int */
	var $uploaded_by	= '';

	/** @var int */
	var $checked_out	= 0;

	/** @var date */
	var $checked_out_time	= '';

	/** @var int */
	var $access				= 1;  // Public access

	/** @var string */
	var $attribs			= null;

	// Non-table (private) properties
	var $_record_name = 'file';
	var $_title = 'filename';
	var $_alias = null;
	var $_force_ascii_alias = true;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_records_jtable = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	// overloaded check function
	public function check()
	{
		return true;
	}
}
