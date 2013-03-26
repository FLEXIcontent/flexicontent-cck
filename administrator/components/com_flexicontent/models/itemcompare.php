<?php
/**
 * @version 1.5 stable $Id$
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

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Category Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItemcompare extends JModelLegacy
{
	/**
	 * Item data
	 *
	 * @var object
	 */
	var $_item = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		$version = JRequest::getVar('version', 0, '', 'int');
		$this->setId($cid[0],$version);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id,$version)
	{
		// Set item id and wipe data
		$this->_id	    = $id;
		$this->_version	= $version;
		$this->_item	= null;
	}
	
	/**
	 * Overridden get method to get properties from the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadItem()) {
			if(isset($this->_item->$property)) {
				return $this->_item->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get item data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getItem()
	{		
		if ($this->_loadItem())
		{		
			if (JString::strlen($this->_item->fulltext) > 1) {
				$this->_item->text = $this->_item->introtext . "<hr id=\"system-readmore\" />" . $this->_item->fulltext;
			} else {
				$this->_item->text = $this->_item->introtext;
			}
			
			$query = 'SELECT name'
					. ' FROM #__users'
					. ' WHERE id = '. (int) $this->_item->created_by
					;
			$this->_db->setQuery($query);
			$this->_item->creator = $this->_db->loadResult();

			//reduce unneeded query
			if ($this->_item->created_by == $this->_item->modified_by) {
				$this->_item->modifier = $this->_item->creator;
			} else {
				$query = 'SELECT name'
						. ' FROM #__users'
						. ' WHERE id = '. (int) $this->_item->modified_by
						;
				$this->_db->setQuery($query);
				$this->_item->modifier = $this->_db->loadResult();
			}
			
		}
		else  $this->_initItem();

		return $this->_item;
	}


	/**
	 * Method to load item data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadItem()
	{
		// Lets load the item if it doesn't already exist
		if (empty($this->_item))
		{
			$query = 'SELECT i.*, ie.*, t.name AS typename, cr.rating_count, ((cr.rating_sum / cr.rating_count)*20) as score'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id'
					. ' LEFT JOIN #__flexicontent_types AS t ON ie.type_id = t.id'
					. ' WHERE i.id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_item = $this->_db->loadObject();
			return (boolean) $this->_item;
		}
		return true;
	}

	/**
	 * Method to initialise the item data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initItem()
	{
		// Lets load the item if it doesn't already exist
		if (empty($this->_item))
		{
			$createdate = JFactory::getDate();
			$nullDate	= $this->_db->getNullDate();
			
			$item = new stdClass();
			$item->id						= 0;
			$item->cid[]				= 0;
			$item->title				= null;
			$item->alias				= null;
			$item->title_alias	= null;  // deprecated do not use
			$item->text					= null;
			if (!FLEXI_J16GE)
				$item->sectionid	= FLEXI_SECTION;
			$item->catid				= null;
			$item->score				= 0;
			$item->votecount		= 0;
			$item->hits					= 0;
			$item->version				= 0;
			$item->metadesc				= null;
			$item->metakey				= null;
			$item->created				= $createdate->toUnix();
			$item->created_by			= null;
			$item->created_by_alias		= '';
			$item->modified				= $nullDate;
			$item->modified_by		= null;
			$item->publish_up 		= $createdate->toUnix();
			$item->publish_down 	= JText::_( 'FLEXI_NEVER' );
			$item->attribs				= null;
			$item->access					= 0;
			$item->metadata				= null;
			$item->state				= 1;
			$item->mask					= null;  // deprecated do not use
			$item->parentid			= null;  // deprecated do not use
			$item->images				= null;
			$item->urls					= null;
			$this->_item				= $item;
			return (boolean) $this->_item;
		}
		return true;
	}

	/**
	 * Method to get the type parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getTypeparams ()
	{
		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		return $tparams;
	}

	/**
	 * Method to get the values of an extrafield
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getExtrafieldvalue($fieldid)
	{
		if ($fieldid == 1) {
			$field_value = array();
			array_push($field_value, $this->_item->text);
		} else {
		$query = 'SELECT value'
				.' FROM #__flexicontent_fields_item_relations AS firel'
				.' WHERE firel.item_id = ' . (int)$this->_id
				.' AND firel.field_id = ' . (int)$fieldid
				.' ORDER BY valueorder'
				;
		$this->_db->setQuery($query);
		$field_value = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		}
		return $field_value;
	}

	/**
	 * Method to get the value of the older version
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getExtrafieldVersionvalue($fieldid)
	{
		$query = 'SELECT value'
				.' FROM #__flexicontent_items_versions AS iv'
				.' WHERE iv.item_id = ' . (int)$this->_id
				.' AND iv.field_id = ' . (int)$fieldid
				.' AND iv.version = ' . (int)$this->_version
				.' ORDER BY valueorder'
				;
		$this->_db->setQuery($query);
		$field_versionvalue = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();

		return $field_versionvalue;
	}
	
	/**
	 * Method to get extrafields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields()
	{
		$query = 'SELECT fi.*'
				.' FROM #__flexicontent_fields AS fi'
				.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
				.' WHERE ie.item_id = ' . (int)$this->_id
				.' AND fi.published = 1'
				.' GROUP BY fi.id'
				.' ORDER BY ftrel.ordering, fi.ordering, fi.name'
				;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList();

		foreach ($fields as $field) {
			$field->item_id 	= (int)$this->_id;
			$field->value 		= $this->getExtrafieldvalue($field->id);
			$field->version 	= $this->getExtrafieldVersionvalue($field->id);
			$field->parameters= FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
		}

		return $fields;
	}

	/**
	 * Method to get the versions count
	 * 
	 * @return int
	 * @since 1.5
	 */
	function getVersionsCount()
	{
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$versionscount = $this->_db->loadResult();
		
		return $versionscount;
	}
	
	/**
	 * Method to get the first version kept
	 * 
	 * @return int
	 * @since 1.5
	 */
	function getFirstVersion($max)
	{
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$this->_id
				.' ORDER BY version_id DESC'
				;
		$this->_db->setQuery($query, ($max-1), 1);
		$firstversion = $this->_db->loadResult();
		
		return $firstversion;
	}

	/**
	 * Method to get the versionlist which belongs to the item
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getVersionList()
	{
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, u.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
				.' WHERE item_id = ' . (int)$this->_id
				.' ORDER BY version_id ASC'
				;
		$this->_db->setQuery($query);
		$versions = $this->_db->loadObjectList();

		return $versions;
	}	
}
?>