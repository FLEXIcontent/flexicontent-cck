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
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	// overloaded check function
	public function check()
	{
		return true;
	}
}
