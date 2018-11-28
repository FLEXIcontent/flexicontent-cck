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


class flexicontent_favourites extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $itemid				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $userid				= null;
	/** @var int */
	var $notify				= null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_favourites', 'id', $db);
	}


	// overloaded check function
	public function check()
	{
		return;
	}
}
