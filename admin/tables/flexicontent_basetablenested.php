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

class flexicontent_basetablenested extends \Joomla\CMS\Table\Nested
{
	use flexicontent_basetable_trait;

	/**
	 * Store the record with its texts cut to the length of their columns, as the database does when strict mode is off,
	 * so that it accepts the record with strict mode on too
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 */
	public function store($updateNulls = false)
	{
		if (class_exists('flexicontent_db'))
		{
			flexicontent_db::fitRecordToColumns($this, $this->getFields());
		}

		return parent::store($updateNulls);
	}
}
