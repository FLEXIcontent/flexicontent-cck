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
require_once('flexicontent_basetable_trait.php');

class flexicontent_basetable extends \Joomla\CMS\Table\Table
{
	use flexicontent_basetable_trait;

	/**
	 * Store the record with the values that the database stores for it when strict mode is off (a text cut to the
	 * length of its column, '' as 0 in a number column, a value for a required column that a new row leaves out),
	 * so that the database accepts it with strict mode on too
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 */
	public function store($updateNulls = false)
	{
		if (class_exists('flexicontent_db'))
		{
			flexicontent_db::fitRecordToColumns($this, $this->getFields(), empty($this->{$this->_tbl_key}), $updateNulls);
		}

		return parent::store($updateNulls);
	}
}
