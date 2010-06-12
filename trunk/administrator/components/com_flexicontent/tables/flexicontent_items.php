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

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_items extends JTable{
	/** @var int Primary key */
	var $id					= null;
	/** @var string */
	var $title				= null;
	/** @var string */
	var $alias				= null;
	/** @var string */
	var $title_alias		= null;
	/** @var string */
	var $introtext			= null;
	/** @var string */
	var $fulltext			= null;
	/** @var int */
	var $state				= null;
	/** @var int The id of the category section*/
	var $sectionid			= null;
	/** @var int DEPRECATED */
	var $mask				= null;
	/** @var int */
	var $catid				= null;
	/** @var datetime */
	var $created			= null;
	/** @var int User id*/
	var $created_by			= null;
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
	var $urls				= null;
	/** @var string */
	var $attribs			= null;
	/** @var int */
	var $version			= null;
	/** @var int */
	var $parentid			= null;
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

	/** @var int Primary Foreign key */
	var $item_id 			= null;
	/** @var int */
	var $type_id			= null;
	/** @var string */
	var $language			= null;
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
     * Name of the the link table
     *
     * @var    string
     * @access protected
     */
    var $_tbl_join			= '#__flexicontent_items_ext';
    /**
     * Name of the foreign key in the link table
     * $_tbl_key property maps to this property
     *
     * @var		string
     * @access	protected
     */
    var $_frn_key			= 'item_id';
    /**
     * Array of all properties from the join table added to this object
     *
     * @var    array
     * @access protected
     */
    var $_join_prop    		= array('item_id',
    								'type_id',
    								'language',
    								'sub_items',
    								'sub_categories',
    								'related_items',
    								'search_index');
	/**
	* @param database A database connector object
	*/
	function flexicontent_items(& $db) {
		parent::__construct('#__content', 'id', $db);
	}

	/**
	 * Overloaded load function
	 *
	 * @access public
	 * @return boolean
	 * @since 1.5
	 */
	function load( $oid=null )
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

		$db =& $this->getDBO();

		$query = 'SELECT *'
		. ' FROM '.$this->_tbl
		. ' LEFT JOIN '.$this->_tbl_join
		. ' ON '.$this->_tbl_key.' = '.$this->_frn_key
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
	 * Overloaded store function
	 *
	 * @access public
	 * @return boolean
	 * @since 1.5
	 */
	function store( $updateNulls=false )
	{
		$k             = $this->_tbl_key;
		$frn_key       = $this->_frn_key;

		// Split the object for the two tables #__content and #__flexicontent_items_ext
		//$type     = new stdClass();
		$type = & JTable::getInstance('content');
		$type->_tbl = $this->_tbl;
		$type->_tbl_key = $this->_tbl_key;
		//$type_ext = new stdClass();
		$type_ext = & JTable::getInstance('flexicontent_items_ext', '');
		$type_ext->_tbl = $this->_tbl_join;
		$type_ext->_tbl_key = $this->_frn_key;
		foreach ($this->getProperties() as $p => $v) {
			// If the property is in the join properties array we add it to the items_ext object
			if (in_array($p, $this->_join_prop)) {
				$type_ext->$p = $v;
				// Else we add it to the type object
			} else {
				$type->$p = $v;
			}
		}

		if( $this->$k )
		{
			$ret = $this->_db->updateObject( $this->_tbl, $type, $this->_tbl_key, $updateNulls );
			$type_ext->$frn_key = $this->$k;
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
			if (isset($type_ext->$frn_key) && !empty($type_ext->$frn_key))
			{
				// update #__flexicontent_items_ext table
				$ret = $this->_db->updateObject( $this->_tbl_join, $type_ext, $this->_frn_key, $updateNulls );
			} else {
				// insert into #__flexicontent_items_ext table
				$type_ext->$frn_key = $this->id;
				$ret = $this->_db->insertObject( $this->_tbl_join, $type_ext, $this->_frn_key );
			}
			return true;
		}
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
		$filter = & JFilterInput::getInstance( null, null, 1, 1 );
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
		$this->alias = JFilterOutput::stringURLSafe($this->alias);

		if(trim(str_replace('-','',$this->alias)) == '') {
			$datenow =& JFactory::getDate();
			$this->alias = $datenow->toFormat("%Y-%m-%d-%H-%M-%S");
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
		$db =& JFactory::getDBO();

		if ($mapKeysToText) {
			$query = 'SELECT name'
			. ' FROM #__sections'
			. ' WHERE id = '. (int) $this->sectionid
			;
			$db->setQuery( $query );
			$this->sectionid = $db->loadResult();

			$query = 'SELECT name'
			. ' FROM #__categories'
			. ' WHERE id = '. (int) $this->catid
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
