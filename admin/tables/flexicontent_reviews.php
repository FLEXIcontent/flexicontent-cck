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
class flexicontent_reviews extends flexicontent_basetable
{
	/** @var mixed $_NAME */
	public mixed $_NAME = null;
	/** @var mixed $_records_dbtbl */
	public mixed $_records_dbtbl = null;
	/** @var mixed $submit_date */
	public mixed $submit_date = null;
	/** @var mixed $update_date */
	public mixed $update_date = null;


	// Non-table (private) properties
	var $_record_name = 'review';
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
		if ( !$this->submit_date )
		{
			$datenow = \Joomla\CMS\Factory::getDate();
			$this->submit_date = $datenow->toSql();
		}

		// If edited by review submitter then also set the update_date
		if ( $this->id && $this->user_id == \Joomla\CMS\Factory::getUser()->id )
		{
			$datenow = \Joomla\CMS\Factory::getDate();
			$this->update_date = $datenow->toSql();
		}

		return true;
	}
}
