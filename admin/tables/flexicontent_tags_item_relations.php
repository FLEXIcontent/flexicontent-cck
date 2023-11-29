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

class flexicontent_tags_item_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $tid 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $itemid				= null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_tags_item_relations', 'tid', $db);
	}

	// overloaded check function
	function check()
	{
		return;
	}
}
