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
require_once('flexicontent_basetable.php');

class flexicontent_tags extends flexicontent_basetable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id						= null;

	/** @var string */
	var $name					= null;

	/** @var string */
	var $alias				= null;

	/** @var int */
	var $published		= 0;

	/** @var int */
	var $checked_out	= 0;

	/** @var date */
	var $checked_out_time	= '';

	// Non-table (private) properties
	var $_record_name = 'tag';
	var $_title = 'name';
	var $_alias = 'alias';
	var $_force_ascii_alias = false;
	var $_allow_underscore = false;

	var $_jtbls = array(
		'#__flexicontent_tags' => array('flexicontent_tags', '', 'published'),
		'#__tags' => array('Tag', 'TagsTable', 'published'),
	);

	/**
	 * Name of the data table
	 *
	 * @var	string
	 * @access protected
	 */
	var $_tbl			= '#__flexicontent_tags';

	/**
	 * Name of the the jtags table
	 *
	 * @var	string
	 * @access protected
	 */
	var $_tbl_ext = '#__tags';

	/**
	 * Name of the foreign key in the link table
	 * $_tbl_key property maps to this property
	 *
	 * @var		string
	 * @access	protected
	 */
	var $_frn_key_ext			= 'id';
	var $_tbl_key_ext			= 'jtag_id';

	public function __construct(& $db)
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');

		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	/**
	 * Get the columns from database table.
	 *
	 * @param   bool  $reload  flag to reload cache
	 *
	 * @return  mixed  An array of the field names, or false if an error occurs.
	 *
	 * @since   11.1
	 * @throws  UnexpectedValueException
	 */
	public function getFields($reload = false)
	{
		static $tbl_fields = null;

		// Get the fields of the joined tables
		if (!isset($tbl_fields))
		{
			$tbls = array(
				$this->_tbl,
				$this->_tbl_ext,
			);

			foreach ($tbls as $tbl)
			{
				$tbl_fields[$tbl] = $this->_db->getTableColumns($tbl, false);
			}
		}

		$this->_tbl_fields = $tbl_fields;

		// Now get and return fields of the main table
		return parent::getFields($reload);
	}


	/**
	 * Method to reset class properties to the defaults set in the class
	 * definition. It will ignore the primary key as well as any private class
	 * properties (except $_errors).
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function reset()
	{
		// Get the default values for the class from the table.
		foreach ($this->getFields() as $k => $v)
		{
			// If the property is not the primary key or private, reset it.
			if (!in_array($k, $this->_tbl_keys) && (strpos($k, '_') !== 0))
			{
				$this->$k = $v->Default;
			}
		}

		// Get the default values for the class from every joined table.
		foreach ($this->_tbl_fields as $tbl => $props_arr)
		{
			foreach ($props_arr as $k => $v)
			{
				// If the property is not the primary key or private, reset it.
				if (!in_array($k, $this->_tbl_keys) && (strpos($k, '_') !== 0))
				{
					$this->$k = $v->Default;
				}
			}
		}

		// Reset table errors
		$this->_errors = array();
	}


	/**
	 * Method to load a row from the database by primary key and bind the fields to the Table instance properties.
	 *
	 * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match. If not set the instance property value is used.
	 * @param   boolean  $reset  True to reset the default values before loading the new row.
	 *
	 * @return  boolean  True if successful. False if row not found.
	 *
	 * @since   3.3
	 * @throws  \InvalidArgumentException, \RuntimeException, \UnexpectedValueException
	 */
	public function load($keys = null, $reset = true)
	{
		if (FLEXI_J40GE)
		{
			// Pre-processing by observers
			$event = AbstractEvent::create(
				'onTableBeforeLoad',
				[
					'subject'	=> $this,
					'keys'		=> $keys,
					'reset'		=> $reset,
				]
			);
			$this->getDispatcher()->dispatch('onTableBeforeLoad', $event);
		}

		else
		{
			// Implement JObservableInterface: Pre-processing by observers
			$this->_observers->update('onBeforeLoad', array($keys, $reset));
		}

		if (empty($keys))
		{
			$empty = true;
			$keys  = array();

			// If empty, use the value of the current key
			foreach ($this->_tbl_keys as $key)
			{
				$empty      = $empty && empty($this->$key);
				$keys[$key] = $this->$key;
			}

			// If empty primary key there's is no need to load anything
			if ($empty)
			{
				return true;
			}
		}
		elseif (!is_array($keys))
		{
			// Load by primary key.
			$keyCount = count($this->_tbl_keys);

			if ($keyCount)
			{
				if ($keyCount > 1)
				{
					throw new \InvalidArgumentException('Table has multiple primary keys specified, only one primary key value provided.');
				}

				$keys = array($this->getKeyName() => $keys);
			}
			else
			{
				throw new \RuntimeException('No table keys defined.');
			}
		}

		if ($reset)
		{
			$this->reset();
		}

		// Initialise the query.
		$query = $this->_db->getQuery(true)
			->select('e.*, a.*')
			->from($this->_tbl . ' AS a')
			->join('LEFT', $this->_tbl_ext . ' AS e ON a.' . $this->_tbl_key_ext . ' = e.' . $this->_frn_key_ext);

		$fields = array_keys($this->getProperties());

		foreach ($keys as $field => $value)
		{
			// Check that $field is in the table.
			if (!in_array($field, $fields))
			{
				throw new \UnexpectedValueException(sprintf('Missing field in database: %s &#160; %s.', get_class($this), $field));
			}

			// Add the search tuple to the query.
			$query->where('a.' . $this->_db->quoteName($field) . ' = ' . $this->_db->quote($value));
		}

		$row = $this->_db->setQuery($query)->loadAssoc();

		// Check that we have a result.
		if (empty($row))
		{
			$result = false;
		}
		else
		{
			// Bind the object with the row and return.
			$result = $this->bind($row);
		}

		if (FLEXI_J40GE)
		{
			// Post-processing by observers
			$event = AbstractEvent::create(
				'onTableAfterLoad',
				[
					'subject'		=> $this,
					'result'		=> &$result,
					'row'			=> $row,
				]
			);
			$this->getDispatcher()->dispatch('onTableAfterLoad', $event);
		}

		else
		{
			// Implement JObservableInterface: Post-processing by observers
			$this->_observers->update('onAfterLoad', array(&$result, $row));
		}

		return $result;
	}


	/**
	 * Method to perform sanity checks on the Table instance properties to ensure they are safe to store in the database.
	 *
	 * Child classes should override this method to make sure the data they are storing in the database is safe and as expected before storage.
	 *
	 * @return  boolean  True if the instance is sane and able to be stored in the database.
	 *
	 * @since   3.3
	 */
	public function check()
	{
		$config = (object) array('automatic_alias' => true);

		// Check common properties, like title and alias 
		if (parent::_check_record($config) === false)
		{
			return false;
		}

		return true;
	}


	/**
	 * Overloaded store function
	 *
	 * @return boolean
	 *
	 * @since 1.5
	 */
	public function store($updateNulls = false)
	{
		$k      = $this->_tbl_key;
		$fk_ext = $this->_frn_key_ext;
		$tk_ext = $this->_tbl_key_ext;

		// Split the data to their actual DB table
		$record = JTable::getInstance($this->_jtbls[$this->_tbl][0], $this->_jtbls[$this->_tbl][1]);
		$record->_tbl = $this->_tbl;
		$record->_tbl_key = $k;

		$record_ext = JTable::getInstance($this->_jtbls[$this->_tbl_ext][0], $this->_jtbls[$this->_tbl_ext][1]);
		$record_ext->_tbl = $this->_tbl_ext;
		$record_ext->_tbl_key = $fk_ext;

		foreach ($this->getProperties() as $p => $v)
		{
			// If the property is in the join properties array we add it to the items_ext object
			if (isset($this->_tbl_fields[$this->_tbl_ext][$p]))
			{
				if ($p === $tk_ext)
				{
					$record_ext->$fk_ext = $v;
				}
				elseif ($p !== $k)
				{
					$record_ext->$p = $v;
				}
			}

			// Add it to the main record properties
			if (isset($this->_tbl_fields[$this->_tbl][$p]))
			{
				$record->$p = $v;
			}
		}

		if ($this->$k)
		{
			$ret = $this->_db->updateObject($this->_tbl, $record, $k, $updateNulls);
		}
		else
		{
			$ret = $this->_db->insertObject($this->_tbl, $record, $k);

			// Get record ID, either this was given or we will get auto-increment ID of last INSERT operation
			$this->$k = $record->$k;
		}

		// Set related tables IDs
		$record_ext->$fk_ext = $this->$tk_ext;

		if (!$ret)
		{
			$this->setError(get_class($this) . '::store failed - ' . $this->_db->getErrorMsg());
			return false;
		}

		else
		{
			// Check if record at extended data DB table exists
			$ext_exists = false;

			if (!empty($record_ext->$fk_ext))
			{
				$ext_exists = (boolean) $this->_db->setQuery('SELECT COUNT(*) FROM ' . $this->_tbl_ext . ' WHERE ' . $fk_ext . '=' . (int) $record_ext->$fk_ext)->loadResult();
			}

			// Update extended data record
			if ($ext_exists)
			{
				$ret = $this->_db->updateObject($this->_tbl_ext, $record_ext, $fk_ext, $updateNulls);
			}

			// Insert extended data record, COMMENTED OUT, MAPPING TAGS MUST BE HANDLED BY DB MODEL
			/*else
			{
				// Zero means autoincrement
				$record_ext->$fk_ext = 0;
				$ret = $this->_db->insertObject($this->_tbl_ext, $record_ext, $fk_ext);

				// Get record ID, this is the auto-increment ID of last INSERT operation
				$this->$tk_ext = $record_ext->$fk_ext;
			}*/
		}

		// If the table is not set to track assets return true.
		if (!$this->_trackAssets)
		{
			return true;
		}

		if ($this->_locked)
		{
			$this->_unlock();
		}

		//
		// Asset Tracking
		//

		$parentId = $this->_getAssetParentId();
		$name  = $this->_getAssetName();
		$title = $this->_getAssetTitle();

		$asset = JTable::getInstance('Asset');
		$asset->loadByName($name);

		// Check for an error.
		if ($error = $asset->getError())
		{
			$this->setError($error);
			return false;
		}

		// Specify how a new or moved node asset is inserted into the tree.
		if (empty($this->asset_id) || $asset->parent_id != $parentId)
		{
			$asset->setLocation($parentId, 'last-child');
		}

		// Prepare the asset to be stored.
		$asset->parent_id = $parentId;
		$asset->name  = $name;
		$asset->title = $title;

		if ($this->_rules instanceof JAccessRules)
		{
			$asset->rules = (string) $this->_rules;
		}

		if (!$asset->check() || !$asset->store($updateNulls))
		{
			$this->setError($asset->getError());
			return false;
		}

		// Update the asset_id field in this table.
		if (empty($this->asset_id))
		{
			$this->asset_id = (int) $asset->id;

			$query = $this->_db->getQuery(true)
				->update($this->_db->quoteName($this->_tbl))
				->set('asset_id = '.(int) $this->asset_id)
				->where($this->_db->quoteName( $k ) . ' = ' . (int) $this->$k);

			$this->_db->setQuery($query)->execute();
		}

		return true;
	}


	/**
	 * Returns an associative array of object properties.
	 *
	 * @param   boolean  $public  If true, returns only the public properties.
	 *
	 * @return  array
	 *
	 * @since   11.1
	 *
	 * @see     CMSObject::get()
	 */
	public function getProperties($public = true)
	{
		foreach($this->getFields() as $propname => $field)
		{
			if (!isset($this->$propname))
			{
				$this->$propname = null;
			}
		}

		foreach($this->_tbl_fields as $tbl => $tbl_fields)
		{
			foreach($tbl_fields as $propname => $field)
			{
				if (!isset($this->$propname))
				{
					$this->$propname = null;
				}
			}
		}

		return parent::getProperties($public);
	}
}
