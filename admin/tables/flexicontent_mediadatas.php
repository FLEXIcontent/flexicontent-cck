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

class flexicontent_mediadatas extends flexicontent_basetable
{
	// Non-table (private) properties
	var $_record_name = 'mediadata';
	var $_title = 'title';
	var $_alias = null;
	var $_force_ascii_alias = false;
	var $_allow_underscore = false;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);

		// Set the alias since the column is called state
		$this->setColumnAlias('published', 'state');
	}


	// overloaded check function
	public function check()
	{
		// Set submit date if it is empty
		/*if ( !$this->submit_date )
		{
			$datenow = JFactory::getDate();
			$this->submit_date = $datenow->toSql();
		}*/

		// If edited by mediadata submitter then also set the update_date
		/*if ( $this->id && $this->user_id == JFactory::getUser()->id )
		{
			$datenow = JFactory::getDate();
			$this->update_date = $datenow->toSql();
		}*/

		return true;
	}
}
