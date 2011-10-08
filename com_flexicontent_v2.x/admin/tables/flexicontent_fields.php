<?php
/**
 * @version 1.5 stable $Id: flexicontent_fields.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.database.tableasset');
jimport('joomla.access.rules');
/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_fields extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $field_type			= null;
	/** @var string */
	var $name				= '';
	/** @var string */
	var $label				= '';
	/** @var string */
	var $description		= '';
	/** @var int */
	var $isfilter			= 0;
	/** @var int */
	var $iscore				= 0;
	/** @var int */
	var $issearch			= 1;
	/** @var int */
	var $isadvsearch		= 0;
	/** @var string */
	var $positions			= '';
	/** @var string */
	var $attribs	 		= null;
	/** @var int */
	var $published			= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time	= '';
	/** @var int */
	var $access 			= 0;
	/** @var int */
	var $ordering 			= null;
	var $_trackAssets 		= true;

	function flexicontent_fields(& $db) {
		parent::__construct('#__flexicontent_fields', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		// Not typed in a label?
		if (trim( $this->label ) == '') {
			$this->_error = JText::_( 'FLEXI_ADD_LABEL' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}

		$newname = str_replace('-', '', JFilterOutput::stringURLSafe($this->label));

		if((empty($this->name) || $this->name === $name) && $this->iscore != 1 ) {
			$this->name = $newname;
		}

		/** check for existing name */
		$query = 'SELECT id'
				.' FROM #__flexicontent_fields'
				.' WHERE name = '.$this->_db->Quote($this->name)
				;
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('FLEXI_THIS_FIELD_NAME_ALREADY_EXIST', $this->name));
			return false;
		}
	
		return true;
	}
	
	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id`
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 * @since	1.6
	 */
	protected function _getAssetName() {
		$k = $this->_tbl_key;
		return 'flexicontent.field.'.(int) $this->$k;
	}
	
	/**
	 * Overloaded bind function.
	 *
	 * @param   array   $array   named array
	 * @param   string  $ignore  An optional array or space separated list of properties
	 *                           to ignore while binding.
	 *
	 * @return  mixed   Null if operation was satisfactory, otherwise returns an error
	 *
	 * @see     JTable:bind
	 * @since   11.1
	 */
	public function bind($array, $ignore = '')
	{
		if (isset($array['attribs']) && is_array($array['attribs'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules'])) {
			$rules = new JRules($array['rules']);
			$this->setRules($rules);
		}

		return parent::bind($array, $ignore);
	}
	
	/**
	 * Method to store a row in the database from the JTable instance properties.
	 * If a primary key value is set the row with that primary key value will be
	 * updated with the instance property values.  If no primary key value is set
	 * a new row will be inserted into the database with the properties from the
	 * JTable instance.
	 *
	 * @param	boolean True to update fields even if they are null.
	 * @return	boolean	True on success.
	 * @since	1.0
	 * @link	http://docs.joomla.org/JTable/store
	 */
	/*public function store($updateNulls = false) {
		// Initialise variables.
		$k = $this->_tbl_key;

		// The asset id field is managed privately by this class.
		if ($this->_trackAssets) {
			unset($this->asset_id);
		}

		// If a primary key exists update the object, otherwise insert it.
		if ($this->$k) {
			$stored = $this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		}
		else {
			$stored = $this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}

		// If the store failed return false.
		if (!$stored) {
			$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $this->_db->getErrorMsg()));
			$this->setError($e);
			return false;
		}
		$this->load($this->id);

		// If the table is not set to track assets return true.
		if (!$this->_trackAssets) {
			return true;
		}

		if ($this->_locked) {
			$this->_unlock();
		}

		//
		// Asset Tracking
		//

		$parentId	= $this->_getAssetParentId();
		$name		= $this->_getAssetName();
		$title		= $this->_getAssetTitle();

		$asset	= JTable::getInstance('Asset');
		$asset->loadByName($name);

		// Re-inject the asset id.
		//$this->asset_id = $asset->id;
		$asset_id = $asset->id;

		// Check for an error.
		if ($error = $asset->getError()) {
			$this->setError($error);
			return false;
		}

		// Specify how a new or moved node asset is inserted into the tree.
		if (empty($asset_id) || $asset->parent_id != $parentId) {
			$asset->setLocation($parentId, 'last-child');
		}

		// Prepare the asset to be stored.
		$asset->parent_id	= $parentId;
		$asset->name		= $name;
		$asset->title		= $title;

		if ($this->_rules instanceof JRules) {
			$asset->rules = (string) $this->_rules;
		}

		if (!$asset->check() || !$asset->store($updateNulls)) {
			$this->setError($asset->getError());
			return false;
		}

		if ($asset->id && empty($this->asset_id)) {
			// Update the asset_id field in this table.
			$this->asset_id = (int) $asset->id;

			$query = $this->_db->getQuery(true);
			$query->update($this->_db->nameQuote($this->_tbl));
			$query->set('asset_id = '.(int) $this->asset_id);
			$query->where($this->_db->nameQuote($k).' = '.(int) $this->$k);
			$this->_db->setQuery($query);

			if (!$this->_db->query()) {
				$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED_UPDATE_ASSET_ID', $this->_db->getErrorMsg()));
				$this->setError($e);
				return false;
			}
		}

		return true;
	}*/
}
?>
