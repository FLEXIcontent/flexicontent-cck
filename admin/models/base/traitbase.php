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
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $name     The table name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $options  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\Table\Table  A \Joomla\CMS\Table\Table object
	 *
	 * @since   3.0
	 * @throws  \Exception
	 */
	public function getTable($name = null, $prefix = '', $options = array())
	{
		$name = $name ?: $this->records_jtable;

		if (empty($name))
		{
			$name = $this->getName();
		}

		// Fix for Joomla 4 and up, enabling us to load UserTable & UserGroupTable without BC plugin.
		if(FLEXI_J40GE && substr($name, 0, 6) === '\Joomla\CMS\Table\Table')
		{
			$prefix = '\Joomla\CMS\Table\Table';
			$name = str_replace('\Joomla\CMS\Table\Table', '', $name);
		}

		if ($table = $this->_createTable($name, $prefix, $options))
		{
			return $table;
		}

		throw new \Exception(\Joomla\CMS\Language\Text::sprintf('JLIB_APPLICATION_ERROR_TABLE_NAME_NOT_SUPPORTED', $name), 0);
	}


	/**
	 * Returns where conditions that must always be applied
	 *
	 * @param		\Joomla\Data\DataObjectbaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  \Joomla\Data\DataObjectbaseQuery|array
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

		if ($q instanceof \Joomla\Data\DataObjectbaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
	}
}
