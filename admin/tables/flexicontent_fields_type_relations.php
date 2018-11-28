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

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.5
 */
class flexicontent_fields_type_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $field_id 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $type_id				= null;
	/**
	 * Ordering
	 * @var int
	 */
	var $ordering				= null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_fields_type_relations', 'field_id', $db);
	}

	// overloaded check function
	function check()
	{
		return;
	}
}
