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
 * Category table
 *
 * @package 	Joomla.Framework
 * @subpackage	Table
 * @since		1.0
 */
jimport('joomla.database.tablenested');
jimport('joomla.access.rules');
use Joomla\String\StringHelper;
require_once('flexicontent_basetablenested.php');

class _flexicontent_categories_common extends flexicontent_basetablenested
{
	protected function __getAssetParentId(JTable $table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;

		// This is a category under a category.
		if ($this->parent_id > 1)
		{
			// Build the query to get the asset id for the parent category.
			$query = $this->_db->getQuery(true)
				->select('asset_id')
				->from('#__categories')
				->where('id = '.(int) $this->parent_id);

			// Get the asset id from the database.
			if ($result = $this->_db->setQuery($query)->loadResult())
			{
				$assetId = (int) $result;
			}

		}

		// This is root category. Build the query to get the asset id of component.
		else
		{
			$query = $this->_db->getQuery(true)
				->select('id')
				->from('#__assets')
				->where('name= "com_content"');

			// Get the asset id from the database.
			if ($result = $this->_db->setQuery($query)->loadResult())
			{
				$assetId = (int) $result;
			}
		}

		// Return the asset id.
		if ($assetId)
		{
			return $assetId;
		}
		else
		{
			return parent::_getAssetParentId($table, $id);
		}
	}
}

/* This is no longer needed but it is a good example of
 *  how to fix STRICT warnings when a newer Joomla version adds TYPE to the parameter of a method
 *  thus makes possible for same code to run on both old and new Joomla version without warnings
 */
if (FLEXI_J30GE)
{
	class _flexicontent_categories extends _flexicontent_categories_common
	{
		protected function _getAssetParentId(JTable $table = null, $id = null)
		{
			return parent::__getAssetParentId($table, $id);
		}
	}
}

else
{
	class _flexicontent_categories extends _flexicontent_categories_common
	{
		protected function _getAssetParentId($table = null, $id = null)
		{
			return parent::__getAssetParentId($table, $id);
		}
	}
}


class flexicontent_categories extends _flexicontent_categories
{
	/** @var int Primary key */
	var $id					= null;
	/** @var int */
	var $asset_id			= null;
	var $parent_id			= null;
	var $lft			= null;
	var $rgt			= null;
	var $level			= null;
	/** @var string */
	var $path			= null;
	var $extension= 'com_content';
	/** @var string The menu title for the category (a short name)*/
	var $title				= null;
	/** @var string The the alias for the category*/
	var $alias				= null;
	var $note			= null;
	/** @var string */
	var $description		= null;
	/** @var boolean */
	var $published			= null;
	/** @var boolean */
	var $checked_out		= 0;
	/** @var time */
	var $checked_out_time	= 0;
	/** @var int */
	//var $ordering			= null;
	/** @var int */
	var $access				= null;
	/** @var string */
	var $params				= null;
	var $metadesc			= null;
	var $metakey			= null;
	var $metadata			= null;
	var $created_user_id		= null;
	var $created_time		= null;
	var $modified_user_id		= null;
	var $modified_time		= null;
	var $hits			= null;
	var $language			= null;

	// Non-table (private) properties
	var $_record_name = 'category';
	var $_title = 'title';
	var $_alias = 'alias';
	var $_force_ascii_alias = false;
	var $_allow_underscore = true;

	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database driver object.
	 *
	 * @since  3.3
	 */
	public function __construct($db)
	{
		// Override default setting of extension which contains 'com_flexicontent'
		$this->extension = 'com_content';

		$this->_records_dbtbl  = 'categories';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);

		// Set the alias for 'published' column (if different than the default)
		//$this->setColumnAlias('published', 'published');
	}


	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id` (which we will override)
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 * @since	1.6
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;

		return 'com_content.category.'.(int) $this->$k;
	}


	/**
	 * Method to return the title to use for the asset table.
	 *
	 * @return  string
	 *
	 * @since   3.3
	 */
	protected function _getAssetTitle()
	{
		return $this->title;
	}


	/**
	 * Get the parent asset id for the record
	 *
	 * @param   JTable   $table  A JTable object for the asset parent.
	 * @param   integer  $id     The id for the asset
	 *
	 * @return  integer  The id of the asset's parent
	 *
	 * @since   11.1
	 */
	// see (above) parent class method: _getAssetParentId($table = null, $id = null)


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

		global $globalcats;

		// Check for valid parent category
		if ($this->id && in_array($this->parent_id, $globalcats[$this->id]->descendantsarray))
		{
			$this->setError('Parent of category is not allowed, you cannot move the category to be a child of itself');
			return false;
		}

		// check for existing name
		/*$query = 'SELECT id'
		. ' FROM #__categories c'
		. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND title = '.$this->_db->Quote($this->title)
		. ' AND lft>=' . FLEXI_LFT_CATEGORY . ' AND rgt<=' . FLEXI_RGT_CATEGORY
		;
		$this->_db->setQuery( $query );

		$xid = intval( $this->_db->loadResult() );
		if ($xid && $xid != intval( $this->id )) {
			$this->_error = JText::sprintf( 'WARNNAMETRYAGAIN', JText::_( 'FLEXI_Category' ) );
			return false;
		}*/

		return true;
	}


	/**
	 * Overloaded bind function
	 *
	 * @param   array  $array   Named array
	 * @param   mixed  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return  mixed  Null if operation was satisfactory, otherwise returns an error string
	 *
	 * @see     JTable:bind
	 * @since   3.3
	 */
	public function bind($array, $ignore = '')
	{
		$this->extension = 'com_content';

		// Bind parameters (params or attribs)
		if (isset($array['params']) && is_array($array['params']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['params']);
			$array['params'] = (string)$registry;
		}

		// Bind metadata
		if (isset($array['metadata']) && is_array($array['metadata']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules']))
		{
			$rules = new JAccessRules($array['rules']);
			$this->setRules($rules);
		}

		return parent::bind($array, $ignore);
	}


	/**
	 * Overloaded JTable::store
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.3
	 */
	public function store($updateNulls = false)
	{
		$date	= JFactory::getDate();
		$user	= JFactory::getUser();

		// Existing category
		if ($this->id)
		{
			$this->modified_time	= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$this->modified_user_id	= $user->get('id');
			$is_new = false;
		}

		// New category
		else
		{
			$this->created_time		= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$this->created_user_id	= $user->get('id');
			$is_new = true;
		}

		// Verify that the alias is unique
		$table = JTable::getInstance('flexicontent_categories','');
		if ($table->load(array('alias'=>$this->alias,'parent_id'=>$this->parent_id,'extension'=>$this->extension)) && ($table->id != $this->id || $this->id==0)) {

			$this->setError(JText::_('JLIB_DATABASE_ERROR_CATEGORY_UNIQUE_ALIAS'));
			return false;
		}

		// NOT NEEDED handle by parent::store()
		/*if (isset($this->asset_id))
		{
			$asset	= JTable::getInstance('Asset');
			if (!$asset->load($this->asset_id)) {
				$name = $this->_getAssetName();
				$asset->loadByName($name);

				$query = $this->_db->getQuery(true);
				$query->update($this->_db->quoteName($this->_tbl));
				$query->set('asset_id = '.(int)$asset->id);
				$query->where('id = '.(int) $this->id);
				$this->_db->setQuery($query);
				$this->_db->execute();
			}
		}*/

		$result = parent::store($updateNulls);

		// Force com_content extension for new categories ... and for existing ones, just to make sure ...
		if ($is_new || !$is_new)
		{
			$query 	= 'UPDATE #__categories'
				. ' SET extension = "com_content" '
				. ' WHERE id = ' . (int)$this->id;
				;
			$this->_db->setQuery($query);
			$this->_db->execute();
		}

		return $result;
	}
}
