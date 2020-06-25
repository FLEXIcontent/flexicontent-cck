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


class flexicontent_versions extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $item_id			= null;
	/** @var int */
	var $version_id			= null;
	/** @var string */
	var $comment	 		= null;
	/** @var date */
	var $created			= null;
	/** @var int */
	var $created_by 		= null;
	/** @var int */
	var $state				= null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_versions', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		return true;
	}
}
