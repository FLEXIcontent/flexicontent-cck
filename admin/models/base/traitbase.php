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
 * FLEXIcontent List of Records Trait (Legacy model methods)
 *
 */
trait FCModelTraitBase
{
	/**
	 * Method to get the last id
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	protected function _getLastId()
	{
		$query = $this->_db->getQuery(true)
			->select('MAX(id)')
			->from('#__' . $this->records_dbtbl)
			;
		$lastid = (int) $this->_db->setQuery($query)->loadResult();

		return $lastid;
	}


	/**
	 * Returns a Table object, always creating it
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 *
	 * @return	JTable	A database object
	 *
	 * @since   3.3.0
	*/
	public function getTable($type = null, $prefix = '', $config = array())
	{
		$type = $type ?: $this->records_jtable;
		return parent::getTable($type, $prefix, $config);
	}


	/**
	 * Returns where conditions that must always be applied
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since   3.3.0
	 */
	protected function _buildHardFiltersWhere($q = false)
	{
		$where = array();

		foreach ($this->hard_filters as $n => $v)
		{
			$where[] = $this->_db->quoteName($n) . ' = ' .  $this->_db->Quote($v);
		}

		if ($q instanceof \JDatabaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
	}
}
