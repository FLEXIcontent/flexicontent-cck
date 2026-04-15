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
use Joomla\String\StringHelper;
require_once('flexicontent_basetable.php');

#[AllowDynamicProperties]
class flexicontent_templates extends flexicontent_basetable
{
	/** @var mixed $_NAME */
	public mixed $_NAME = null;
	/** @var mixed $_records_dbtbl */
	public mixed $_records_dbtbl = null;


	// Non-table (private) properties
	var $_record_name = 'template';
	var $_title = 'template';
	var $_alias = null;
	var $_force_ascii_alias = false;
	var $_allow_underscore = false;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	// overloaded check function, todo more
	public function check()
	{
		parent::check();
	}
}