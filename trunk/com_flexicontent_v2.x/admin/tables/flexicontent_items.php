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

if (FLEXI_J16GE) {
	jimport('joomla.access.rules');
}

class _flexicontent_items_common extends JTable {
	protected function __getAssetParentId(JTable $table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;
		$db = $this->getDbo();
	
		// This is a article under a category.
		if ($this->catid) {
			// Build the query to get the asset id for the parent category.
			$query	= $db->getQuery(true);
			$query->select('asset_id');
			$query->from('#__categories');
			$query->where('id = '.(int) $this->catid);

			// Get the asset id from the database.
			$this->_db->setQuery($query);
			if ($result = $this->_db->loadResult()) {
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
}

if (FLEXI_J30GE) {
	class _flexicontent_items extends _flexicontent_items_common {
		protected function _getAssetParentId(JTable $table = null, $id = null) {
			return parent::__getAssetParentId($table, $id);
		}
	}
}

else {
	class _flexicontent_items extends _flexicontent_items_common {
		protected function _getAssetParentId($table = null, $id = null) {
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
class flexicontent_items extends _flexicontent_items {
	/** @var int Primary key */
	var $id					= null;
	/** @var string */
	var $title				= null;
	/** @var string */
	var $alias				= null;
	/** @var string */
	var $title_alias	= null;  // deprecated do not use
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
     * @var    string
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
     * Array of all properties from the join table added to this object
     *
     * @var    array
     * @access protected
     */
    var $_join_prop_ext		= array('item_id',
    								'type_id',
    								'language',
    								'lang_parent_id',
    								'sub_items',
    								'sub_categories',
    								'related_items',
    								'search_index');

    /**
     * Name of the the items ext table
     *
     * @var    string
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
     * Array of all properties from the join table added to this object
     *
     * @var    array
     * @access protected
     */
    var $_join_prop_tmp		= array();

	/**
	* @param database A database connector object
	*/
	function flexicontent_items(& $db) {
		$tbls = array($this->_tbl_join_tmp);
		if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
		else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
		
		$this->_join_prop_tmp = array_keys($tbl_fields[$this->_tbl_join_tmp]);
		
		parent::__construct('#__content', 'id', $db);
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
		return 'com_content.article.'.(int) $this->$k;
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
	 * Method to get the parent asset id for the record
	 *
	 * @param   JTable   $table  A JTable object for the asset parent
	 * @param   integer  $id
	 *
	 * @return  integer
	 *
	 * @since   11.1
	 */
	// see (above) parent class method: _getAssetParentId($table = null, $id = null)
	
	
	/**
	 * Overloaded load function
	 *
	 * @access public
	 * @return boolean
	 * @since 1.5
	 */
	function load( $oid=null, $reset = true )
	{
		$k = $this->_tbl_key;

		if ($oid !== null) {
			$this->$k = $oid;
		}

		$oid = $this->$k;

		if ($oid === null) {
			return false;
		}
		$this->reset();

		$db = $this->getDBO();

		$query = 'SELECT *'
		. ' FROM '.$this->_tbl
		. ' LEFT JOIN '.$this->_tbl_join_ext
		. ' ON '.$this->_tbl_key.' = '.$this->_frn_key_ext
		//. ' LEFT JOIN '.$this->_tbl_join_tmp                        // same name and same data columns
		//. ' ON '.$this->_tbl_key.' = '.$this->_frn_key_tmp          // ... so do not join
		. ' WHERE '.$this->_tbl_key.' = '.$db->Quote($oid);
		$db->setQuery( $query );
		
		if ($result = $db->loadAssoc( )) {
		//dump($result,'bind');
			return $this->bind($result);
		}
		else
		{
			$this->setError( $db->getErrorMsg() );
			return false;
		}
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
		if (isset($array['images']) && is_array($array['images'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['images']);
			$array['images'] = (string)$registry;
		}

		if (isset($array['urls']) && is_array($array['urls'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['urls']);
			$array['urls'] = (string)$registry;
		}
		
		if (isset($array['attribs']) && is_array($array['attribs'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string)$registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules'])) {
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

		// Split the object for the two tables #__content and #__flexicontent_items_ext
		//$type     = new stdClass();
		$type = JTable::getInstance('content');
		$type->_tbl = $this->_tbl;
		$type->_tbl_key = $this->_tbl_key;
		//$type_ext = new stdClass();
		$type_ext = JTable::getInstance('flexicontent_items_ext', '');
		$type_ext->_tbl = $this->_tbl_join_ext;
		$type_ext->_tbl_key = $this->_frn_key_ext;
		//$type_tmp = new stdClass();
		$type_tmp = JTable::getInstance('flexicontent_items_tmp', '');
		$type_tmp->_tbl = $this->_tbl_join_tmp;
		$type_tmp->_tbl_key = $this->_frn_key_tmp;
		
		foreach ($this->getProperties() as $p => $v) {
		
			// If the property is in the join properties array we add it to the items_tmp object (coming either from tbl or tbl_ext or from other joined table)
			if (in_array($p, $this->_join_prop_tmp)) {
				$type_tmp->$p = $v;
			}
			
			// If the property is in the join properties array we add it to the items_ext object
			if (in_array($p, $this->_join_prop_ext)) {
				$type_ext->$p = $v;
				
				// Catch case of new J1.6+ article language column
				if (FLEXI_J16GE && $p == "language") {
					//$jAp= JFactory::getApplication();
					//$jAp->enqueueMessage('setting content language to' . $v,'message');
					$type->$p = $v;
				}
			
				// Catch case of master item for (translation groups) not being set
				if ($p == "lang_parent_id" && $v==0) {
					//$jAp= JFactory::getApplication();
					//$jAp->enqueueMessage('Setting default lang_parent_id to '. $type->id,'message');
					$type_ext->$p = $type->id;
				}
			}
				
			// Else we add it to the core item properties
			else {
				$type->$p = $v;
			}
		}

		if( $this->$k )
		{
			$ret = $this->_db->updateObject( $this->_tbl, $type, $this->_tbl_key, $updateNulls );
			$type_ext->$frn_key_ext   = $this->$k;
			$type_tmp->$frn_key_tmp = $this->$k;
		}
		else
		{
			$ret = $this->_db->insertObject( $this->_tbl, $type, $this->_tbl_key );
			// set the type_id
			$this->id = $type->id;
			$this->id = $this->id?$this->id:$this->_db->insertid();
		}

		if( !$ret )
		{
			$this->setError(get_class( $this ).'::store failed - '.$this->_db->getErrorMsg());
			return false;
		}
		else
		{
			// check for foreign key
			if (isset($type_ext->$frn_key_ext) && !empty($type_ext->$frn_key_ext)) {
				// update #__flexicontent_items_ext table
				$ret = $this->_db->updateObject( $this->_tbl_join_ext, $type_ext, $this->_frn_key_ext, $updateNulls );
				
				// update #__flexicontent_items_tmp table
				$ret = $this->_db->updateObject( $this->_tbl_join_tmp, $type_tmp, $this->_frn_key_tmp, $updateNulls );
			}
			
			else {
				if ($type_ext->lang_parent_id == 0) $type_ext->lang_parent_id = $this->id;  // case of new item we need to set lang_parent_id after initial content creation
				
				// insert into #__flexicontent_items_ext table
				$type_ext->$frn_key_ext = $this->id;
				$ret = $this->_db->insertObject( $this->_tbl_join_ext, $type_ext, $this->_frn_key_ext );
				
				// insert into #__flexicontent_items_ext table
				$type_tmp->$frn_key_tmp = $this->id;
				$ret = $this->_db->insertObject( $this->_tbl_join_tmp, $type_tmp, $this->_frn_key_tmp );
			}
			
			// Check for unique Alias
			$sub_q = 'SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='.(int)$this->id;
			$query = 'SELECT COUNT(*) FROM #__content AS i '
				.' JOIN #__flexicontent_items_ext AS e ON i.id = e.item_id '
				.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid '
				.' WHERE i.alias='.$this->_db->Quote($this->alias)
				.'  AND (i.catid='.(int)$this->id.' OR rel.catid IN ('.$sub_q.') )'
				.'  AND e.language = '.$this->_db->Quote($type_ext->language)
				.'  AND i.id <> '.(int)$this->id
				//.'  AND e.lang_parent_id <> '.(int)$type_ext->lang_parent_id
				;
			$this->_db->setQuery($query);
			$duplicate_aliases = (boolean) $this->_db->loadResult();
			if ($duplicate_aliases) {
				$query 	= 'UPDATE #__content SET alias='.$this->_db->Quote($this->alias.'_'.$this->id).' WHERE id='.(int)$this->id;
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}
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

		// Check for an error.
		if ($error = $asset->getError()) {
			$this->setError($error);
			return false;
		}

		// Specify how a new or moved node asset is inserted into the tree.
		if (empty($this->asset_id) || $asset->parent_id != $parentId) {
			$asset->setLocation($parentId, 'last-child');
		}

		// Prepare the asset to be stored.
		$asset->parent_id	= $parentId;
		$asset->name		= $name;
		$asset->title		= $title;
		
		//print_r ($this->_rules); exit();
		if ($this->_rules instanceof JAccessRules) {
			$asset->rules = (string) $this->_rules;
		}

		if (!$asset->check() || !$asset->store($updateNulls)) {
			$this->setError($asset->getError());
			return false;
		}

		if (empty($this->asset_id)) {
			// Update the asset_id field in this table.
			$this->asset_id = (int) $asset->id;

			$query = $this->_db->getQuery(true);
			$query->update( $this->_db->quoteName( $this->_tbl ) );
			$query->set('asset_id = '.(int) $this->asset_id);
			$query->where( $this->_db->quoteName( $k ) .' = '.(int) $this->$k);
			$this->_db->setQuery($query);

			if (!$this->_db->query()) {
				$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED_UPDATE_ASSET_ID', $this->_db->getErrorMsg()));
				$this->setError($e);
				return false;
			}
		}
		return true;
	}

	/**
	 * Overloaded check function
	 *
	 * @access public
	 * @return boolean
	 * @since 1.5
	 */
	function check()
	{
		/*
		TODO: This filter is too rigorous,need to implement more configurable solution
		// specific filters
		$filter = JFilterInput::getInstance( null, null, 1, 1 );
		$this->introtext = trim( $filter->clean( $this->introtext ) );
		$this->fulltext =  trim( $filter->clean( $this->fulltext ) );
		*/


		if(empty($this->title)) {
			$this->setError(JText::_( 'FLEXI_ARTICLES_MUST_HAVE_A_TITLE' ));
			return false;
		}

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

		if (trim( str_replace( '&nbsp;', '', $this->fulltext ) ) == '') {
			$this->fulltext = '';
		}

		// clean up keywords -- eliminate extra spaces between phrases
		// and cr (\r) and lf (\n) characters from string
		if(!empty($this->metakey)) { // only process if not empty
			$bad_characters = array("\n", "\r", "\"", "<", ">"); // array of characters to remove
			$after_clean = JString::str_ireplace($bad_characters, "", $this->metakey); // remove bad characters
			$keys = explode(',', $after_clean); // create array using commas as delimiter
			$clean_keys = array(); 
			foreach($keys as $key) {
				if(trim($key)) {  // ignore blank keywords
					$clean_keys[] = trim($key);
				}
			}
			$this->metakey = implode(", ", $clean_keys); // put array back together delimited by ", "
		}
		
		// clean up description -- eliminate quotes and <> brackets
		if(!empty($this->metadesc)) { // only process if not empty
			$bad_characters = array("\"", "<", ">");
			$this->metadesc = JString::str_ireplace($bad_characters, "", $this->metadesc);
		}

		return true;
	}
	
	/**
	* Converts record to XML
	* @param boolean Map foreign keys to text values

	function toXML( $mapKeysToText=false )
	{
		$db = JFactory::getDBO();

		if ($mapKeysToText) {
			$query = 'SELECT name'
			. ' FROM #__sections'
			. ' WHERE id = '. (int) $this->sectionid
			;
			$db->setQuery( $query );
			$this->sectionid = $db->loadResult();

			$query = 'SELECT name'
			. ' FROM #__categories c'
			. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND id = '. (int) $this->catid
			;
			$db->setQuery( $query );
			$this->catid = $db->loadResult();

			$query = 'SELECT name'
			. ' FROM #__users'
			. ' WHERE id = ' . (int) $this->created_by
			;
			$db->setQuery( $query );
			$this->created_by = $db->loadResult();
		}

		return parent::toXML( $mapKeysToText );
	}
	*/
}
