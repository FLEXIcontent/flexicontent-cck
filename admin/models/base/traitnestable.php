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
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

/**
 * FLEXIcontent Nestable Records Trait
 *
 */
trait FCModelTraitNestableRecord
{
	/**
	 * Method to add children or parent records to a given list
	 *
	 * @param   int      $id     The record id for which to find children or parents
	 * @param   array    $cid   The array of existing record ids to avoid loops
	 * @param   string   $type   'children' or 'parents' indicating which records to add
	 *
	 * @return   array   The original record ids 'list' plus the added parents or children record ids
	 *
	 * @since 3.3.0
	 */
	protected function _addPathRecords($id, & $cid, $type = 'children')
	{
		// Initialize variables
		$return = true;

		$cid = ArrayHelper::toInteger($cid);

		$id_col = $type === 'children' ? 'id' : $this->parent_col;
		$source = $type === 'children' ? $this->parent_col : 'id';

		$query = $this->_db->getQuery(true)
			->select('a.' . $id_col)
			->from('#__' . $this->records_dbtbl . ' AS a')
			->where('a.' . $source . ' = ' . (int) $id)
			->where('a.' . $id_col . ' <> 1')
		;
		$this->_buildHardFiltersWhere($query);

		$rows = $this->_db->setQuery($query)->loadObjectList();

		// Recursively iterate through all ancestors or descendants
		foreach ($rows as $row)
		{
			$found = false;

			foreach ($cid as $idx)
			{
				if ($idx == $row->{$id_col})
				{
					$found = true;
					break;
				}
			}

			if (!$found)
			{
				$cid[] = $row->{$id_col};
			}

			$return = $this->_addPathRecords($row->{$id_col}, $cid, $type);
		}

		return $return;
	}
}
