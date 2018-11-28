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


class flexicontent_items_extravote extends JTable
{
	/**
	 * Primary Key
	 * Foreign Key to content table
	 * @var int
	 */
	var $content_id 		= null;
	/** @var int */
	var $field_id			= null;
	/** @var int */
	var $rating_sum			= null;
	/** @var int */
	var $rating_count		= null;
	/** @var string */
	var $lastip				= null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_items_extravote', 'content_id', $db);
	}

	// overloaded check function
	function check()
	{
		return true;
	}
}
