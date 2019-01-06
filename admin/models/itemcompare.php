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

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component Itemcompare Model
 *
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
	public function __construct($config = array())
	{
		parent::__construct($config);

		$app = JFactory::getApplication();
		$jinput = $app->input;

		$id = $jinput->get('id', array(0), 'array');
		$id = ArrayHelper::toInteger($id, array(0));
		$pk = (int) $id[0];

		if (!$pk)
		{
			$cid = $jinput->get('cid', array(0), 'array');
			$cid = ArrayHelper::toInteger($cid, array(0));
			$pk = (int) $cid[0];
		}

		$version = $jinput->get('version', 0, 'int');
		$this->setId($pk, $version);
	}

	/**
	 * Method to set the identifier
	 *
	 * @param		int	    $id        record identifier
	 * @param		int	    $version   record's version
	 *
	 * @since	3.3.0
	 */
	public function setId($id, $version)
	{
		// Set item id and wipe data
		$this->_id	    = $id;
		$this->_version	= $version;
		$this->_item    = null;
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
			if (StringHelper::strlen($this->_item->fulltext) > 1) {
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
		else
		{
			die('_loadItem() failed to load record: ' . $this->_id);
		}

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


	function getCustomFieldsValues($item_id=0, $version=0)
	{
		if (!$item_id)  $item_id = $this->_id;
		if (!$item_id)  return array();

		static $field_values;
		if ( isset($field_values[$item_id][$version] ) )
			return $field_values[$item_id][$version];

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$use_versioning = $cparams->get('use_versioning', 1);

		$query = 'SELECT field_id, value, valueorder, suborder'
			.( ($version<=0 || !$use_versioning) ? ' FROM #__flexicontent_fields_item_relations AS fv' : ' FROM #__flexicontent_items_versions AS fv' )
			.' WHERE fv.item_id = ' . (int)$item_id
			.( ($version>0 && $use_versioning) ? ' AND fv.version='.((int)$version) : '')
			.' ORDER BY field_id, valueorder, suborder'
			;
		$this->_db->setQuery($query);
		$rows = $this->_db->loadObjectList();

		// Add values to cached array
		$field_values[$item_id][$version] = array();
		foreach ($rows as $row) {
			$field_values[$item_id][$version][$row->field_id][$row->valueorder-1][$row->suborder-1] = $row->value;
		}

		foreach ($field_values[$item_id][$version] as & $fv) {
			foreach ($fv as & $ov) {
				if (count($ov) == 1) $ov = reset($ov);
			}
			unset($ov);
		}
		unset($fv);

		return $field_values[$item_id][$version];
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

		$cus_vals = $this->getCustomFieldsValues($this->_id, 0);
		$ver_vals = $this->getCustomFieldsValues($this->_id, $this->_version);

		foreach ($fields as $field) {
			$field->item_id 	= (int)$this->_id;
			$field->value 		= @ $cus_vals[$field->id];  // ignore not set
			$field->version 	= @ $ver_vals[$field->id];  // ignore not set
			$field->parameters= new JRegistry($field->attribs);
			//$field->parameters->set('use_ingroup', 0);
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
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, ua.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS ua ON ua.id = v.created_by'
				.' WHERE item_id = ' . (int) $this->_id
				.' ORDER BY version_id ASC'
				;
		$this->_db->setQuery($query);
		$versions = $this->_db->loadObjectList();

		return $versions;
	}
}
