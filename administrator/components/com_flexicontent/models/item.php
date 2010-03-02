<?php
/**
 * @version 1.5 beta 5 $Id: item.php 183 2009-11-18 10:30:48Z vistamedia $
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
if(!class_exists('FLEXIUtilities')) {
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.utilities.php');
}
/**
 * FLEXIcontent Component Category Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItem extends JModel {
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
		$this->setId($cid[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id)
	{
		// Set item id and wipe data
		$this->_id	    = $id;
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
	function &getItem($loadcurrent=false)
	{
		if ($this->_loadItem($loadcurrent))
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
	function _loadItem($loadcurrent=false) {
		// Lets load the item if it doesn't already exist
		if (empty($this->_item)) {
			$item =& $this->getTable('flexicontent_items', '');
			$item->load($this->_id);
			$isnew = (($this->_id <= 0) || !$this->_id);
			$current_version = $item->version;
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
			$lastversion = FLEXIUtilities::getLastVersion($this->_id);
			if($version==0) 
				JRequest::setVar( 'version', $version = $loadcurrent?$current_version:$lastversion);
			$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_items_versions as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.version='".$version."' AND iv.item_id='".$this->_id."';";
			$this->_db->setQuery($query);
			$fields = $this->_db->loadObjectList();
			foreach($fields as $f) {
				$fieldname = $f->name;
				if( (($f->field_type=='categories') && ($f->name=='categories')) || (($f->field_type=='tags') && ($f->name=='tags')) ) {
					$item->$fieldname = unserialize($f->value);
				}else{
					$item->$fieldname = $f->value;
				}
			}
			if(!isset($item->tags)||!is_array($item->tags)) {
				$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->tags = $this->_db->loadResultArray();
			}
			if(!isset($item->categories)||!is_array($item->categories)) {
				$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->categories = $this->_db->loadResultArray();
			}
			$item->id = $this->_id;

			$query = "SELECT t.name as typename, cr.rating_count, ((cr.rating_sum / cr.rating_count)*20) as score"
					." FROM #__flexicontent_items_ext as ie "
					. " LEFT JOIN #__content_rating AS cr ON cr.content_id = ie.item_id"
					." LEFT JOIN #__flexicontent_types AS t ON ie.type_id = t.id"
					." WHERE ie.item_id='".$this->_id."';";
			$this->_db->setQuery($query);
			$type = $this->_db->loadObject();
			if($type) {
				$item->typename = $type->typename;
				$item->rating_count = $type->rating_count;
				$item->score = $type->score;
				$item->version = $current_version;
			}else{
				$item->version = 0;
				$item->score = 0;
			}
			if($isnew) {
				$createdate = & JFactory::getDate();
				$nullDate	= $this->_db->getNullDate();
				$item->created 		= $createdate->toUnix();
				$item->modified 	= $nullDate;
				$item->publish_up 	= $createdate->toUnix();
				$item->publish_down = JText::_( 'FLEXI_NEVER' );
				$item->state 		= -4;
			}

			if($version == $current_version) {
				$item->text = $item->introtext;
			}
			$this->_item = &$item;
			$lastversion = FLEXIUtilities::getLastVersion($item->id);
			if(!$isnew && $current_version>$lastversion) {
				//echo "<xmp>";var_dump(get_object_vars($item));echo "</xmp>";
				/*$vars = get_object_vars($item);
				$fieldtype = array();
				foreach($vars as $k=>$v) {
					if( !($fieldid = flexicontent_html::getFlexiFieldId($k)) || ($k=='created') || ($k=='created_by') ) continue;
					if(!isset($fieldtype[$fieldid])) {
						$query = "SELECT * FROM #__flexicontent_fields WHERE id='$fieldid';";
						$this->_db->setQuery($query)
						$fieldtype[$fieldid] = $this->_db->loadObject();
					}
				}
				return (boolean) $this->_item;*/
				global $mainframe;
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder "
						." FROM #__flexicontent_fields_item_relations as fir"
						//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
						." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id='".$this->_id."';";
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				$jcorefields = flexicontent_html::getJCoreFields();
				$catflag = false;
				$tagflag = false;
				$clean_database = true;
				if(!$clean_database && $fields) {
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$this->_id;
					$this->_db->setQuery($query);
					$this->_db->query();
				}
				foreach($fields as $field) {
					// process field mambots onBeforeSaveField
					//$results = $mainframe->triggerEvent('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= $field->valueorder;;
					$obj->version		= (int)$current_version;
					// @TODO : move in the plugin code
					if( ($field->field_type=='categories') && ($field->name=='categories') ) {
						continue;
						//$obj->value = serialize($item->categories);
						//$catflag = true;
					}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
						continue;
						//$obj->value = serialize($item->tags);
						//$tagflag = true;
					}else{
						$obj->value			= $field->value;
					}
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder."<br />";
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					//echo "insert into __flexicontent_items_versions<br />";
					if( !$clean_database && !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
						unset($obj->version);
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						//echo "insert into __flexicontent_fields_item_relations<br />";
					}
					// process field mambots onAfterSaveField
					//$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
					//$searchindex 	.= @$field->search;
				}
				if(!$catflag) {
					$obj = new stdClass();
					$obj->field_id 		= 13;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$current_version;
					$obj->value		= serialize($item->categories);
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				if(!$tagflag) {
					$obj = new stdClass();
					$obj->field_id 		= 14;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$current_version;
					$obj->value		= serialize($item->tags);
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				$v = new stdClass();
				$v->item_id 		= (int)$item->id;
				$v->version_id		= (int)$current_version;
				$v->created 	= $item->created;
				$v->created_by 	= $item->created_by;
				//$v->comment		= 'kept current version to version table.';
				//echo "insert into __flexicontent_versions<br />";
				$this->_db->insertObject('#__flexicontent_versions', $v);
			}
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
			$createdate = & JFactory::getDate();
			$nullDate	= $this->_db->getNullDate();
			
			$item = new stdClass();
			$item->id					= 0;
			$item->cid[]				= 0;
			$item->title				= null;
			$item->alias				= null;
			$item->title_alias			= null;
			$item->text					= null;
			$item->sectionid			= FLEXI_SECTION;
			$item->catid				= null;
			$item->score				= 0;
			$item->votecount			= 0;
			$item->hits					= 0;
			$item->version				= 0;
			$item->metadesc				= null;
			$item->metakey				= null;
			$item->created				= $createdate->toUnix();
			$item->created_by			= null;
			$item->created_by_alias		= '';
			$item->modified				= $nullDate;
			$item->modified_by			= null;
			$item->publish_up 			= $createdate->toUnix();
			$item->publish_down 		= JText::_( 'FLEXI_NEVER' );
			$item->attribs				= null;
			$item->access				= 0;
			$item->metadata				= null;
			$item->state				= -4;
			$item->mask					= null;
			$item->images				= null;
			$item->urls					= null;
			$item->language				= flexicontent_html::getSiteDefaultLang();
			$this->_item				= $item;
			return (boolean) $this->_item;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			return $item->checkin($this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($uid = null)
	{
		if ($this->_id)
		{
			// Make sure we have a user id to checkout the group with
			if (is_null($uid)) {
				$user	=& JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$item = & JTable::getInstance('flexicontent_items', '');
			return $item->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the item is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadItem())
		{
			if ($uid) {
				return ($this->_item->checked_out && $this->_item->checked_out != $uid);
			} else {
				return $this->_item->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}
	
	/**
	 * Method to check if the user can an item anywhere
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canAdd()
	{
		$user	=& JFactory::getUser();

		if (FLEXI_ACCESS && ($user->gid < 25))
		{
			if 	((!FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid)) && (!FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all'))) return false;
		}
		return true;
	}

	/**
	 * Method to check if the user can edit the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canEdit()
	{
		$user	=& JFactory::getUser();

		if (FLEXI_ACCESS && $this->_loadItem() && ($user->gid < 25))
		{
				if ($this->_item->id && $this->_item->catid)
				{
					$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $this->_item->id, $this->_item->catid);
					$canEdit 	= in_array('edit', $rights) || FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
					$canEditOwn	= ((in_array('editown', $rights) || FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all')) && ($this->_item->created_by == $user->id));
				
					if ($canEdit || $canEditOwn) return true;
					return false;
				}
		}
		return true;
	}

	/**
	 * Method to store the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data) {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		global $mainframe;
		$item  	=& $this->getTable('flexicontent_items', '');
		$user	=& JFactory::getUser();
		
		$details	= JRequest::getVar( 'details', array(), 'post', 'array');
		$tags 		= JRequest::getVar( 'tag', array(), 'post', 'array');
		$cats 		= JRequest::getVar( 'cid', array(), 'post', 'array');
		$post 	= JRequest::get( 'post', JREQUEST_ALLOWRAW );
		$post['vstate'] = (int)$post['vstate'];
		//unset($data['state']);
		// bind it to the table
		if (!$item->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		$item->bind($details);

		// sanitise id field
		$item->id = (int) $item->id;
		
		$nullDate	= $this->_db->getNullDate();
		//$item->version++;
		$version = FLEXIUtilities::getLastVersion($item->id);
		$isnew = false;
		$tags = array_unique($tags);
		
		if( ($isnew = !$item->id) || ($post['vstate']==2) )
		{
			
			$config =& JFactory::getConfig();
			$tzoffset = $config->getValue('config.offset');

			if ($isnew = !$item->id) {
				$item->modified 	= $nullDate;
				$item->modified_by 	= 0;
			} else {
				$mdate =& JFactory::getDate();
				$item->modified 	= $mdate->toMySQL();
				$item->modified_by 	= (int)$user->id;
			}
			// Are we saving from an item edit?
			// This code comes directly from the com_content

			$item->created_by 	= $item->created_by ? $item->created_by : $user->get('id');

			if ($item->created && strlen(trim( $item->created )) <= 10) {
				$item->created 	.= ' 00:00:00';
			}

			$date =& JFactory::getDate($item->created, $tzoffset);
			$item->created = $date->toMySQL();

			// Append time if not added to publish date
			if (strlen(trim($item->publish_up)) <= 10) {
				$item->publish_up .= ' 00:00:00';
			}

			$date =& JFactory::getDate($item->publish_up, $tzoffset);
			$item->publish_up = $date->toMySQL();

			// Handle never unpublish date
			if (trim($item->publish_down) == JText::_('Never') || trim( $item->publish_down ) == '')
			{
				$item->publish_down = $nullDate;
			}
			else
			{
				if (strlen(trim( $item->publish_down )) <= 10) {
					$item->publish_down .= ' 00:00:00';
				}
				$date =& JFactory::getDate($item->publish_down, $tzoffset);
				$item->publish_down = $date->toMySQL();
			}


			// auto assign the main category if none selected
			if (!$item->catid) {
				$item->catid 		= $cats[0];
			}
			
			// auto assign the section
			$item->sectionid 	= FLEXI_SECTION;
			
			// Get a state and parameter variables from the request
			$item->state	= JRequest::getVar( 'state', 0, '', 'int' );
			$oldstate		= JRequest::getVar( 'oldstate', 0, '', 'int' );
			$params			= JRequest::getVar( 'params', null, 'post', 'array' );

			// Build parameter INI string
			if (is_array($params))
			{
				$txt = array ();
				foreach ($params as $k => $v) {
					if (is_array($v)) {
						$v = implode('|', $v);
					}
					$txt[] = "$k=$v";
				}
				$item->attribs = implode("\n", $txt);
			}

			// Get metadata string
			$metadata = JRequest::getVar( 'meta', null, 'post', 'array');
			if (is_array($params))
			{
				$txt = array();
				foreach ($metadata as $k => $v) {
					if ($k == 'description') {
						$item->metadesc = $v;
					} elseif ($k == 'keywords') {
						$item->metakey = $v;
					} else {
						$txt[] = "$k=$v";
					}
				}
				$item->metadata = implode("\n", $txt);
			}
					
			// Clean text for xhtml transitional compliance
			$text = str_replace('<br>', '<br />', $data['text']);
			
			// Search for the {readmore} tag and split the text up accordingly.
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos	= preg_match($pattern, $text);

			if ($tagPos == 0)	{
				$item->introtext	= $text;
			} else 	{
				list($item->introtext, $item->fulltext) = preg_split($pattern, $text, 2);
			}
			
			if (!$item->id) {
				$item->ordering = $item->getNextOrder();
			}
			
			// Make sure the data is valid
			if (!$item->check()) {
				$this->setError($item->getError());
				return false;
			}
			$item->version = $isnew?1:(($post['vstate']==2)?($version+1):$item->version);
			// Store it in the db
			if (!$item->store()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$canRight 	= (in_array('right', $rights) || $user->gid >= 24);
				if ($canRight) FAccess::saveaccess( $item, 'item' );
			}

			$this->_item	=& $item;
			
			//if($post['vstate']==2) {
				//store tag relation
				$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
				$this->_db->setQuery($query);
				$this->_db->query();
				foreach($tags as $tag)
				{
					$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			//}

			// Store categories to item relations

			// Add the primary cat to the array if it's not already in
			if (!in_array($item->catid, $cats)) {
				$cats[] = $item->catid;
			}

			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError('FLEXI_SELECT_CATEGORY');
				return false;
			}
			
			//if($isnew || $post['vstate']==2) {
				// delete only relations which are not part of the categories array anymore to avoid loosing ordering
				$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
						. ' WHERE itemid = '.$item->id
						. ($cats ? ' AND catid NOT IN (' . implode(', ', $cats) . ')' : '')
						;
				$this->_db->setQuery($query);
				$this->_db->query();
				
				// draw an array of the used categories
				$query 	= 'SELECT catid'
						. ' FROM #__flexicontent_cats_item_relations'
						. ' WHERE itemid = '.$item->id
						;
				$this->_db->setQuery($query);
				$used = $this->_db->loadResultArray();
				
				foreach($cats as $cat)
				{
					// insert only the new records
					if (!in_array($cat, $used)) {
						$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
								.' VALUES(' . $cat . ',' . $item->id . ')'
								;
						$this->_db->setQuery($query);
						$this->_db->query();
					}
				}
			//}
		}else {
			$datenow =& JFactory::getDate();
			$item->modified 		= $datenow->toMySQL();
			$item->modified_by 		= $user->get('id');
			// Add the primary cat to the array if it's not already in
			if (!in_array($item->catid, $cats)) {
				$cats[] = $item->catid;
			}

			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError('FLEXI_SELECT_CATEGORY');
				return false;
			}
		}
		$post['categories'][0] = $cats;
		$post['tags'][0] = $tags;
		///////////////////////////////
		// store extra fields values //
		///////////////////////////////
		
		// get the field object
		$this->_id 	= $item->id;	
		$fields		= $this->getExtrafields();
		$dispatcher = & JDispatcher::getInstance();
		
		// NOTE: This event isn't used yet but may be useful in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item ));
		
		// versioning backup procedure
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		// first see if versioning feature is enabled
		if ($cparams->get('use_versioning', 1)) {
			$v = new stdClass();
			$v->item_id 		= (int)$item->id;
			$v->version_id		= (int)$version+1;
			$v->modified		= $item->modified;
			$v->modified_by		= $item->modified_by;
			$v->created 	= $item->created;
			$v->created_by 	= $item->created_by;

			$files	= JRequest::get( 'files', JREQUEST_ALLOWRAW );
			$searchindex = '';
			if($post['vstate']==2) {
				$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
				$this->_db->setQuery($query);
				$this->_db->query();
			}
			if ($fields) {
				$jcorefields = flexicontent_html::getJCoreFields();
				foreach($fields as $field) {
					// process field mambots onBeforeSaveField
					$results = $mainframe->triggerEvent('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

					// add the new values to the database 
					if (is_array($post[$field->name])) {
						$postvalues = $post[$field->name];
						$i = 1;
						foreach ($postvalues as $postvalue) {
							$obj = new stdClass();
							$obj->field_id 		= $field->id;
							$obj->item_id 		= $field->item_id;
							$obj->valueorder	= $i;
							$obj->version		= $v->version_id;
							// @TODO : move to the plugin code
							if (is_array($postvalue)) {
								$obj->value			= serialize($postvalue);
							} else {
								$obj->value			= $postvalue;
							}
							$this->_db->insertObject('#__flexicontent_items_versions', $obj);
							if(
								($isnew || ($post['vstate']==2) )
								&& !isset($jcorefields[$field->name])
								&& !in_array($field->field_type, $jcorefields)
								&& ( ($field->field_type!='categories') || ($field->name!='categories') )
								&& ( ($field->field_type!='tags') || ($field->name!='tags') )
							) {
								unset($obj->version);
								$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
							}
							$i++;
						}
					}else if ($post[$field->name]) {
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $field->item_id;
						$obj->valueorder	= 1;
						$obj->version		= $v->version_id;
						// @TODO : move in the plugin code
						if (is_array($post[$field->name])) {
							$obj->value			= serialize($post[$field->name]);
						} else {
							$obj->value			= $post[$field->name];
						}
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
						if(
							($isnew || ($post['vstate']==2) )
							&& !isset($jcorefields[$field->name])
							&& !in_array($field->field_type, $jcorefields)
							&& ( ($field->field_type!='categories') || ($field->name!='categories') )
							&& ( ($field->field_type!='tags') || ($field->name!='tags') )
						) {
							unset($obj->version);
							$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						}
					}
					// process field mambots onAfterSaveField
					$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
					$searchindex 	.= @$field->search;
				}
			}
			
			if ($v->modified != $nullDate) {
				$v->created 	= $v->modified;
				$v->created_by 	= $v->modified_by;
			}
			
			$v->comment		= isset($post['versioncomment'])?htmlspecialchars($post['versioncomment'], ENT_QUOTES):'';
			unset($v->modified);
			unset($v->modified_by);
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}
		
		// delete old versions
		$vcount	= $this->getVersionsCount();
		$vmax	= $cparams->get('nr_versions', 10);

		if ($vcount > ($vmax+1)) {
			$lastversion = $this->getFirstVersion($vmax, $item->version);
			// on efface les versions en trop
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version <' . $lastversion
					.' AND version!=' . (int)$item->version
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version_id <' . $lastversion
					.' AND version_id!=' . (int)$item->version
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		return true;
	}

	/**
	 * Method to restore an old version
	 * 
	 * @param int id
	 * @param int version
	 * @return int
	 * @since 1.5
	 */
	function restore($version, $id)
	{
		// delete current field values
		$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.(int)$id;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// load field values from the version to restore
		$query 	= 'SELECT item_id, field_id, value, valueorder, iscore'
				. ' FROM #__flexicontent_items_versions as iv'
				. ' LEFT JOIN #__flexicontent_fields as f ON iv.field_id=f.id'
				. ' WHERE item_id = '. (int)$id
				. ' AND version = '. (int)$version
				;
		$this->_db->setQuery($query);
		$versionrecords = $this->_db->loadObjectList();
		
		// restore the old values
		foreach ($versionrecords as $versionrecord) {
			if(!(int)$versionrecord->iscore)
				$this->_db->insertObject('#__flexicontent_fields_item_relations', $versionrecord);
		}
		$query = "UPDATE #__content SET version='$version' WHERE id='$id';";
		$this->_db->setQuery($query);
		$this->_db->query($query);
		// handle the maintext not very elegant but functions properly
		$row  =& $this->getTable('flexicontent_items', '');
		$row->load($id);

		if (@$versionrecords[0]->value) {
			// Search for the {readmore} tag and split the text up accordingly.
			$text 		= $versionrecords[0]->value;
			$pattern 	= '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos		= preg_match($pattern, $text);

			if ($tagPos == 0) {
				$row->introtext	= $text;
			} else 	{
				list($row->introtext, $row->fulltext) = preg_split($pattern, $text, 2);
			}
		}
		//$row->store();
		$row->checkin();
	}

	/**
	 * Method to reset hits
	 * 
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function resetHits($id)
	{
		$row  =& $this->getTable('flexicontent_items', '');
		$row->load($id);
		$row->hits = 0;
		$row->store();
		$row->checkin();
		return $row->id;
	}
	
	/**
	 * Method to reset votes
	 * 
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function resetVotes($id)
	{
		$query = 'DELETE FROM #__content_rating WHERE content_id = '.$id;
		$this->_db->setQuery($query);
		$this->_db->query();
	}
	
	/**
	 * Method to fetch tags
	 * 
	 * @return object
	 * @since 1.0
	 */
	function gettags($mask="")
	{
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}
	
	/**
	 * Method to fetch used tags when performing an edit action
	 * 
	 * @param int id
	 * @return array
	 * @since 1.0
	 */
	function getUsedtagsArray($id=0)
	{
		if(!isset($this->_item->tags)||!is_array($this->_item->tags)) {
			if(!$id) $this->_item->tags = array();
			else {
				$query 	= 'SELECT tid FROM #__flexicontent_tags_item_relations'
					. " WHERE itemid ='$id'"
					;
				$this->_db->setQuery($query);
				$this->_item->tags = $this->_db->loadResultArray;
			}
		}
		return $this->_item->tags;
	}
	function getUsedtags($A)
	{
		$query 	= 'SELECT *,t.id as tid FROM #__flexicontent_tags as t '
				. ' WHERE t.id IN (' . implode(',', $A).')'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$used = $this->_db->loadObjectList();
		return $used;
	}
	
	/**
	 * Method to reset hits
	 * 
	 * @param int id
	 * @return object
	 * @since 1.0
	 */
	function getvotes($id)
	{
		$query = 'SELECT rating_sum, rating_count FROM #__content_rating WHERE content_id = '.(int)$id;
		$this->_db->setQuery($query);
		$votes = $this->_db->loadObjectlist();

		return $votes;
	}
	
	/**
	 * Method to get hits
	 * 
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function gethits($id)
	{
		$query = 'SELECT hits FROM #__content WHERE id = '.(int)$id;
		$this->_db->setQuery($query);
		$hits = $this->_db->loadResult();
		
		return $hits;
	}
	
	/**
	 * Method to get used categories when performing an edit action
	 * 
	 * @return array
	 * @since 1.0
	 */
	function getCatsselected()
	{
		if(!isset($this->_item->categories)||!is_array($this->_item->categories)) {
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$used = $this->_db->loadResultArray();
			return $used;
		}
		return $this->_item->categories;
	}
	
	/**
	 * Method to get subscriber count
	 * 
	 * @TODO add the notification as an option with a checkbox in the favourites screen
	 * @return object
	 * @since 1.5
	 */
	function getSubscribersCount()
	{
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_favourites'
				.' WHERE itemid = ' . (int)$this->_id
//				.' AND notify = 1'
				;
		$this->_db->setQuery($query);
		$subscribers = $this->_db->loadResult();
		return $subscribers;
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
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ()
	{
		$query = 'SELECT id, name'
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1'
				;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		return $types;
	}
	
	/**
	 * Method to get used types when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypesselected()
	{
		$query = 'SELECT type_id FROM #__flexicontent_items_ext WHERE item_id = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResult();
		return $used;
	}

	/**
	 * Method to get the values of an extrafield
	 * 
	 * @return object
	 * @since 1.5
	 * @todo move in a specific class and add the parameter $itemid
	 */
	function getExtrafieldvalue($fieldid, $version = 0)
	{
		$query = 'SELECT value'
				.(($version<=0)?' FROM #__flexicontent_fields_item_relations AS fv':' FROM #__flexicontent_items_versions AS fv')
				.' WHERE fv.item_id = ' . (int)$this->_id
				.' AND fv.field_id = ' . (int)$fieldid
				.(($version>0)?' AND fv.version='.((int)$version):'')
				.' ORDER BY valueorder'
				;
		$this->_db->setQuery($query);
		$field_value = $this->_db->loadResultArray();
		return $field_value;
	}
	
	/**
	 * Method to get extrafields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields()
	{
		$version = JRequest::getVar( 'version', '', 'request', 'int' );
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
		$fields = $this->_db->loadObjectList('name');
		foreach ($fields as $field) {
			$field->item_id		= (int)$this->_id;
			$field->value 		= $this->getExtrafieldvalue($field->id, $version);
			$field->parameters 	= new JParameter($field->attribs);
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
	function getFirstVersion($max, $used_version)
	{
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$this->_id
				.' AND version_id!=' . (int)$used_version
				.' ORDER BY version_id DESC'
				;
		$this->_db->setQuery($query, ($max-1), 1);
		//echo $this->_db->_sql;
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
		$limitstart = JRequest::getVar('limitstart', 0);
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, u.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
				.' WHERE item_id = ' . (int)$this->_id
				.' ORDER BY version_id ASC'
				. ' LIMIT '.$limitstart.','.$versionsperpage
				;
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}
	function getVersionCount()
	{
		$query 	= 'SELECT count(*) as num'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
				.' WHERE item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}
	function getCurrentVersion() {
		$query 	= 'SELECT version'
				.' FROM #__content'
				.' WHERE id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}
}
?>