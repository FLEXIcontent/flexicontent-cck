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


class flexicontent_items_tmp extends JTable{
	/* content properties
	(a) CORE content relations that maybe used for ordering too
	(b) other simple attributes used for ordering
	*/
	/* PRIMARY key */
	var $id = null;

	/**
	* @param database A database connector object
	*/
	function __construct(& $db)
	{
		static $tbl_fields = null;
		$tbl_name = '#__flexicontent_items_tmp';

		// Get columns
		if ($tbl_fields === null)
		{
			$tbls = array($tbl_name);
			foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
			$tbl_fields = array_keys($tbl_fields[$tbl_name]);
		}

		foreach ($tbl_fields as $tbl_field) $this->$tbl_field = null;

		parent::__construct($tbl_name, 'id', $db);
	}
}
