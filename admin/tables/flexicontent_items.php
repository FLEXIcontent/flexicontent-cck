<?php
/**
 * @version 1.5 stable $Id: flexicontent_items.php 1832 2014-01-17 00:17:27Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherAwareTrait;
use Joomla\Event\DispatcherInterface;

jimport('joomla.access.rules');
require_once('flexicontent_basetable.php');

class _flexicontent_items_common extends flexicontent_basetable
{
	protected function __getAssetParentId(JTable $table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;

		// This is a article under a category.
		if ($this->catid)
		{
			// Build the query to get the asset id for the parent category.
			$query = $this->_db->getQuery(true)
				->select('asset_id')
				->from('#__categories')
				->where('id = '.(int) $this->catid);

			// Get the asset id from the database.
			$result = $this->_db->setQuery($query)->loadResult();
			if ($result)
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
	class _flexicontent_items extends _flexicontent_items_common
	{
		protected function _getAssetParentId(JTable $table = null, $id = null)
		{
			return parent::__getAssetParentId($table, $id);
		}
	}
}

else
{
	class _flexicontent_items extends _flexicontent_items_common
	{
		protected function _getAssetParentId($table = null, $id = null)
		{
			return parent::__getAssetParentId($table, $id);
		}
	}
}


/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_items extends _flexicontent_items
{
	/** @var int Primary key */
	var $id					= null;
	/** @var string */
	var $title				= null;
	/** @var string */
	var $alias				= null;
	/** @var string */
	var $introtext		= null;
	/** @var string */
	var $fulltext			= null;
	/** @var int */
	var $state				= null;
	/** @var int The id of the category section*/
	var $sectionid		= null;
	/** @var int DEPRECATED */
	var $mask					= null;  // deprecated do not use
	/** @var int */
	var $catid				= null;
	/** @var datetime */
	var $created			= null;
	/** @var int User id*/
	var $created_by		= null;
	/** @var string An alias for the author*/
	var $created_by_alias	= null;
	/** @var datetime */
	var $modified			= null;
	/** @var int User id*/
	var $modified_by		= null;
	/** @var boolean */
	var $checked_out		= 0;
	/** @var time */
	var $checked_out_time	= 0;
	/** @var datetime */
	var $frontpage_up		= null;
	/** @var datetime */
	var $frontpage_down		= null;
	/** @var datetime */
	var $publish_up			= null;
	/** @var datetime */
	var $publish_down		= null;
	/** @var string */
	var $images				= null;
	/** @var string */
	var $urls					= null;
	/** @var string */
	var $attribs			= null;
	/** @var int */
	var $version			= null;
	/** @var int */
	var $parentid			= null;  // deprecated do not use
	/** @var int */
	var $ordering			= null;
	/** @var string */
	var $metakey			= null;
	/** @var string */
	var $metadesc			= null;
	/** @var string */
	var $metadata			= null;
	/** @var int */
	var $access				= null;
	/** @var int */
	var $hits				= null;
	/** @var boolean */
	var $featured		= 0;

	/** @var int Primary Foreign key */
	var $item_id 			= null;
	/** @var int */
	var $type_id			= null;
	/** @var string */
	var $language			= null;
	/** @var int */
	var $lang_parent_id		= null;
	/** @var string */
	/** @TODO : implement */
	var $sub_items			= null;
	/** @var int */
	/** @TODO : implement */
	var $sub_categories		= null;
	/** @var string */
	/** @TODO : implement */
	var $related_items		= null;
	/** @var string */
	var $search_index		= null;

	/**
	 * Name of the the items ext table
	 *
	 * @var	string
	 * @access protected
	 */
	var $_tbl_join_ext			= '#__flexicontent_items_ext';
	
	/**
	 * Name of the foreign key in the link table
	 * $_tbl_key property maps to this property
	 *
	 * @var		string
	 * @access	protected
	 */
	var $_frn_key_ext			= 'item_id';
	
	/**
	 * Name of the the items ext table
	 *
	 * @var	string
	 * @access protected
	 */
	var $_tbl_join_tmp			= '#__flexicontent_items_tmp';
	
	/**
	 * Name of the foreign key in the link table
	 * $_tbl_key property maps to this property
	 *
	 * @var		string
	 * @access	protected
	 */
	var $_frn_key_tmp			= 'id';



	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database driver object.
	 *
	 * @since  11.1
	 */
	public function __construct($db)
	{
		parent::__construct('#__content', 'id', $db);
	}


	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id` which we will override
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 * @since	1.6
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;

		return 'com_content.article.' . (int) $this->$k;
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
				$this->_tbl_join_ext,
				$this->_tbl_join_tmp
			);

			foreach ($tbls as $tbl)
			{
				$tbl_fields[$tbl] = $this->_db->getTableColumns($tbl, false);
			}
		}

		$this->tbl_fields = $tbl_fields;

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
		foreach ($this->tbl_fields as $tbl => $props_arr)
		{
			// Skip this table as it contains copies of the other tables , they do not have their own values
			if ($tbl === $this->_tbl_join_tmp)
			{
				continue;
			}

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
	 * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match.
	 *                           If not set the instance property value is used.
	 * @param   boolean  $reset  True to reset the default values before loading the new row.
	 *
	 * @return  boolean  True if successful. False if row not found.
	 *
	 * @since   11.1
	 * @throws  \InvalidArgumentException
	 * @throws  \RuntimeException
	 * @throws  \UnexpectedValueException
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
			->select('*')
			->from($this->_tbl)
			->join('LEFT', $this->_tbl_join_ext . ' ON ' . $this->_tbl_key . ' = ' . $this->_frn_key_ext);

		$fields = array_keys($this->getProperties());

		foreach ($keys as $field => $value)
		{
			// Check that $field is in the table.
			if (!in_array($field, $fields))
			{
				throw new \UnexpectedValueException(sprintf('Missing field in database: %s &#160; %s.', get_class($this), $field));
			}

			// Add the search tuple to the query.
			$query->where($this->_db->quoteName($field) . ' = ' . $this->_db->quote($value));
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
	 * Overloaded bind function
	 *
	 * @param   array  $array   Named array
	 * @param   mixed  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return  mixed  Null if operation was satisfactory, otherwise returns an error string
	 *
	 * @see     JTable:bind
	 * @since   11.1
	 */
	public function bind($array, $ignore = '')
	{
		if (isset($array['images']) && is_array($array['images']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['images']);
			$array['images'] = (string)$registry;
		}

		if (isset($array['urls']) && is_array($array['urls']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['urls']);
			$array['urls'] = (string)$registry;
		}
		
		if (isset($array['attribs']) && is_array($array['attribs']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string)$registry;
		}

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
	 * Overloaded store function
	 *
	 * @access public
	 * @return boolean
	 * @since 1.5
	 */
	function store( $updateNulls=false )
	{
		$k             = $this->_tbl_key;
		$frn_key_ext   = $this->_frn_key_ext;
		$frn_key_tmp   = $this->_frn_key_tmp;

		// Split the object for the two tables #__content and #__flexicontent_items_ext, (#__flexicontent_items_tmp duplicates non-TEXT data of #__content)
		$record = JTable::getInstance('content');
		$record->_tbl = $this->_tbl;
		$record->_tbl_key = $this->_tbl_key;

		$record_ext = JTable::getInstance('flexicontent_items_ext', '');
		$record_ext->_tbl = $this->_tbl_join_ext;
		$record_ext->_tbl_key = $this->_frn_key_ext;

		$record_tmp = JTable::getInstance('flexicontent_items_tmp', '');
		$record_tmp->_tbl = $this->_tbl_join_tmp;
		$record_tmp->_tbl_key = $this->_frn_key_tmp;
		
		foreach ($this->getProperties() as $p => $v)
		{
			// If the property is in the join properties array we add it to the items_tmp object (coming either from tbl or tbl_ext or from other joined table)
			if (isset($this->tbl_fields[$this->_tbl_join_tmp][$p]))
			{
				$record_tmp->$p = $v;
			}
			
			// If the property is in the join properties array we add it to the items_ext object
			if (isset($this->tbl_fields[$this->_tbl_join_ext][$p]))
			{
				$record_ext->$p = $v;
				
				// Also use main table for article's language column
				if ($p === 'language')
				{
					$record->$p = $v;
				}
			
				// Master item id for (translation groups), (Deprecated, TODO remove)
				if ($p === 'lang_parent_id')
				{
					$record_ext->$p = 0;//$record->id;
					$record_tmp->$p = 0;//$record->id;
				}
			}
				
			// Else we add it to the core item properties
			else
			{
				$record->$p = $v;
			}
		}

		if ($this->$k)
		{
			$ret = $this->_db->updateObject($this->_tbl, $record, $this->_tbl_key, $updateNulls);

			$record_ext->$frn_key_ext = $this->$k;
			$record_tmp->$frn_key_tmp = $this->$k;
		}
		else
		{
			$ret = $this->_db->insertObject( $this->_tbl, $record, $this->_tbl_key );

			// Get record ID, either this was given or we will get auto-increment ID of last INSERT operation
			$this->id = $record->id ?: $this->_db->insertid();
		}

		if (!$ret)
		{
			$this->setError(get_class($this) . '::store failed - ' . $this->_db->getErrorMsg());
			return false;
		}

		else
		{
			// Check foreign key for DB table items_ext
			$ext_exists = false;
			if (!empty($record_ext->$frn_key_ext))
			{
				$ext_exists = (boolean) $this->_db->setQuery('SELECT COUNT(*) FROM ' . $this->_tbl_join_ext . ' WHERE ' . $frn_key_ext . '=' . (int) $record_ext->$frn_key_ext)->loadResult();
			}

			// Check foreign key for DB table items_tmp
			$tmp_exists = false;
			if (!empty($record_tmp->$frn_key_tmp))
			{
				$tmp_exists = (boolean) $this->_db->setQuery('SELECT COUNT(*) FROM ' . $this->_tbl_join_tmp . ' WHERE ' . $frn_key_tmp . '=' . (int) $record_tmp->$frn_key_tmp)->loadResult();
			}
			
			// Update #__flexicontent_items_ext table
			if ($ext_exists)
			{
				$ret = $this->_db->updateObject( $this->_tbl_join_ext, $record_ext, $this->_frn_key_ext, $updateNulls );
			}

			// Insert into #__flexicontent_items_ext table
			else
			{
				$record_ext->$frn_key_ext = $this->id;
				$ret = $this->_db->insertObject( $this->_tbl_join_ext, $record_ext, $this->_frn_key_ext );
			}

			// Update #__flexicontent_items_tmp table
			if ($tmp_exists)
			{
				$ret = $this->_db->updateObject( $this->_tbl_join_tmp, $record_tmp, $this->_frn_key_tmp, $updateNulls );
			}

			// Insert into #__flexicontent_items_tmp table
			else
			{
				$record_tmp->$frn_key_tmp = $this->id;
				$ret = $this->_db->insertObject( $this->_tbl_join_tmp, $record_tmp, $this->_frn_key_tmp );
			}

			// Check for unique Alias
			$sub_q = 'SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid=' . (int) $this->id;
			$query = 'SELECT COUNT(*) FROM #__flexicontent_items_tmp AS i '
				. ' JOIN #__flexicontent_items_ext AS e ON i.id = e.item_id '
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid '
				. ' WHERE i.alias=' . $this->_db->Quote($this->alias)
				. '  AND (i.catid=' . (int) $this->id . ' OR rel.catid IN (' . $sub_q . ') )'
				. '  AND e.language = ' . $this->_db->Quote($record_ext->language)
				. '  AND i.id <> ' . (int) $this->id
				//. '  AND e.lang_parent_id <> ' . (int) $record_ext->lang_parent_id
				;

			$duplicate_aliases = (boolean) $this->_db->setQuery($query)->loadResult();

			if ($duplicate_aliases)
			{
				$query 	= 'UPDATE #__content SET alias=' . $this->_db->Quote($this->alias . '_' . $this->id) . ' WHERE id=' . (int) $this->id;
				$this->_db->setQuery($query)->execute();
			}
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
	 * Overloaded check function
	 *
	 * @access public
	 * @return boolean
	 * @see JTable::check
	 * @since 1.5
	 */
	function check()
	{
		// check for empty title
		if (trim($this->title) == '')
		{
			$this->setError(JText::_( 'FLEXI_ARTICLES_MUST_HAVE_A_TITLE' ));
			return false;
		}
		
		// check for empty alias
		if (empty($this->alias))
		{
			$this->alias = $this->title;
		}
		
		// FLAGs
		$unicodeslugs = JFactory::getConfig()->get('unicodeslugs');
		
		$r = new ReflectionMethod('JApplicationHelper', 'stringURLSafe');
		$supports_content_language_transliteration = count( $r->getParameters() ) > 1;


		// Use ITEM's language or use SITE's default language in case of ITEM's language is ALL (or empty)
		$language = $this->language && $this->language != '*'
			? $this->language
			: JComponentHelper::getParams('com_languages')->get('site', '*') ;

		if ($language !== '*' && !JLanguage::getInstance($language)->getTransliterator())
		{
			// Remove any '-' from the string since they will be used as concatenaters
			$this->alias = str_replace('-', ' ', $this->alias);

			$this->alias = $this->transliterate($this->alias, $language);
		}

		// workaround for old joomla versions (Joomla <=3.5.x) that do not allowing to set transliteration language to be element's language
		elseif (!$unicodeslugs && !$supports_content_language_transliteration)
		{
			// Remove any '-' from the string since they will be used as concatenaters
			$this->alias = str_replace('-', ' ', $this->alias);

			// Do the transliteration accorting to ELEMENT's language
			$this->alias = JLanguage::getInstance($language)->transliterate($this->alias);
		}


		// make alias safe and transliterate it
		$this->alias = JApplicationHelper::stringURLSafe($this->alias, $this->language);


		// check for empty alias and fallback to using current date
		if (trim(str_replace('-', '', $this->alias)) == '')
		{
			$this->alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}
		
		// make fulltext empty if it only contains empty spaces
		if (trim(str_replace('&nbsp;', '', $this->fulltext)) == '')
		{
			$this->fulltext = '';
		}
		
		// clean up keywords -- eliminate extra spaces between phrases and cr (\r) and lf (\n) characters from string
		if (!empty($this->metakey))
		{
			// Remove bad characters
			$bad_characters = array("\n", "\r", "\"", "<", ">");
			$after_clean = StringHelper::str_ireplace($bad_characters, "", $this->metakey);

			// Create array using commas as delimiter
			$keys = explode(',', $after_clean);
			$clean_keys = array(); 

			foreach($keys as $key)
			{
				if (trim($key))
				{
					$clean_keys[] = trim($key);
				}
			}

			$this->metakey = implode(", ", $clean_keys); // put array back together delimited by ", "
		}
		
		// clean up description -- eliminate quotes and <> brackets
		if (!empty($this->metadesc))
		{
			$bad_characters = array("\"", "<", ">");
			$this->metadesc = StringHelper::str_ireplace($bad_characters, "", $this->metadesc);
		}

		return true;
	}
}
