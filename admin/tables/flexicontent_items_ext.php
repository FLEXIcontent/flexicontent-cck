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


class flexicontent_items_ext extends JTable{
	/** @var int Primary key */
	var $item_id					= null;
	/** @var int */
	var $type_id				= null;
	/** @var string */
	var $language				= null;
	/** @var int */
	var $lang_parent_id	= null;
	/** @var string */
	var $sub_items		= null;
	/** @var string */
	var $sub_categories			= null;
	/** @var string */
	var $related_items			= null;
	/** @var string */
	var $search_index				= null;

	/**
	* @param database A database connector object
	*/
	function __construct(& $db) {
		parent::__construct('#__flexicontent_items_ext', 'item_id', $db);
	}
}
