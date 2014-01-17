<?php
/**
* @version		$Id: flexicontent_categories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
* @package		Joomla.Framework
* @subpackage	Table
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is within the rest of the framework
defined('_JEXEC') or die('Restricted access');

/**
 * Category table
 *
 * @package 	Joomla.Framework
 * @subpackage	Table
 * @since		1.0
 */
jimport('joomla.database.tablenested');
jimport('joomla.access.accessrules');
class flexicontent_categories extends JTableNested
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

	/**
	* @param database A database connector object
	*/
	function flexicontent_categories(& $db) {
		$this->extension = 'com_content';
		parent::__construct('#__categories', 'id', $db);
	}
	
	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id`
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 * @since	1.6
	 */
	protected function _getAssetName()
	{
		// we use 'com_content' instead of $this->extension which contains 'com_flexicontent'
		$k = $this->_tbl_key;
		return 'com_content.category.'.(int) $this->$k;
	}

	/**
	 * Method to return the title to use for the asset table.
	 *
	 * @return  string
	 *
	 * @since   11.1
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
	protected function _getAssetParentId($table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;
		$db		= $this->getDbo();

		if ($this->parent_id > 1) {
			// This is a category under a category.
			// Build the query to get the asset id for the parent category.
			$query	= $db->getQuery(true);
			$query->select('asset_id');
			$query->from('#__categories');
			$query->where('id = '.(int) $this->parent_id);

			// Get the asset id from the database.
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		
		} else {
			// This is root category.
			// Build the query to get the asset id of component.
			$query	= $db->getQuery(true);
			$query->select('id');
			$query->from('#__assets');
			$query->where('name= "com_flexicontent"');

			// Get the asset id from the database.
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		}

		// Return the asset id.
		if ($assetId) {
			return $assetId;
		} else {
			return parent::_getAssetParentId($table, $id);
		}
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
		// check for valid name
		if (trim( $this->title ) == '') {
			$this->setError(JText::sprintf( 'must contain a title', JText::_( 'FLEXI_Category' ) ));
			return false;
		}
		
		global $globalcats;
		
		// check for valid parent category
		if ( $this->id && in_array($this->parent_id, $globalcats[$this->id]->descendantsarray) ) {
			$this->setError( 'Parent of category is not allowed, you cannot move the category to be a child of itself' );
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

		if(empty($this->alias)) {
			$this->alias = $this->title;
		}
		$this->alias = FLEXI_J16GE ?
			JApplication::stringURLSafe($this->alias) :
			JFilterOutput::stringURLSafe($this->alias);
		
		if(trim(str_replace('-','',$this->alias)) == '') {
			$datenow = JFactory::getDate();
			$this->alias = FLEXI_J16GE ?
				$datenow->format($format = 'Y-M-d-H-i-s', $local = true) :
				$datenow->toFormat($format = '%Y-%m-%d-%H-%M-%S');
		}

		return true;
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
		$this->extension = 'com_content';
		
		if (isset($array['params']) && is_array($array['params'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['params']);
			$array['params'] = (string)$registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules'])) {
		
			// Make sure that empty group ids (=inherit) are removed from actions, (not needed this should already be done)
			foreach($array['rules'] as $action_name => $identities) {
				foreach($identities as $grpid => $val) {
					if ($val==="") {
						unset($array['rules'][$action_name][$grpid]);
					}
				}
			}
			$rules = new JRules($array['rules']);
			$this->setRules($rules);
		}
		
		return parent::bind($array, $ignore);
	}
	
	/**
	 * Overriden JTable::store to set created/modified and user id.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   11.1
	 */
	public function store($updateNulls = false)
	{
		$date	= JFactory::getDate();
		$user	= JFactory::getUser();

		if ($this->id) {
			// Existing category
			$this->modified_time	= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$this->modified_user_id	= $user->get('id');
			$is_new = false;
		} else {
			// New category
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
		/*if (isset($this->asset_id)) {
			$asset	= JTable::getInstance('Asset');
			if (!$asset->load($this->asset_id)) {
				$name = $this->_getAssetName();
				$asset->loadByName($name);
			
				$query = $this->_db->getQuery(true);
				$query->update($this->_db->quoteName($this->_tbl));
				$query->set('asset_id = '.(int)$asset->id);
				$query->where('id = '.(int) $this->id);
				$this->_db->setQuery($query);
				
				if (!$this->_db->query()) {
					$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED_UPDATE_ASSET_ID', $this->_db->getErrorMsg()));
					$this->setError($e);
					return false;
				}
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
			$this->_db->query();
			if (!$this->_db->query()) {
				$e = new JException(JText::sprintf($this->_db->getErrorMsg()));
				$this->setError($e);
				return false;
			}
		}
		
		return $result;
	}
}
