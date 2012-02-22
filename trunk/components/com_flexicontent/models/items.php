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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItems extends JModel
{
	/**
	 * Details data in details array
	 *
	 * @var array
	 */
	var $_item = null;


	/**
	 * tags in array
	 *
	 * @var array
	 */
	var $_tags = null;

	/**
	 * id
	 *
	 * @var array
	 */
	var $_id = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$id 	= JRequest::getVar('id', 0, '', 'int');
		$this->setId((int)$id);
	}

	/**
	 * Method to set the item id
	 *
	 * @access	public
	 * @param	int	faq ID number
	 */

	function setId($id)
	{
		// Set new item id
		$this->_id			= $id;
		$this->_item		= null;
	}

	/**
	 * Overridden get method to get properties from the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.5
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
	 * Overridden set method to pass properties on to the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function set( $property, $value=null )
	{
		if ($this->_loadItem()) {
			$this->_item->$property = $value;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Method to get data for the itemview
	 *
	 * @access public
	 * @return array
	 * @since 1.0
	 */
	function &getItem() {
		global $mainframe, $globalcats;
		
		/*
		* Load the Item data
		*/
		if ($this->_loadItem()) {
			// Get the page/component configuration (current menu item parameters are already merged in J1.5)
			$params = clone ( $mainframe->getParams('com_flexicontent') );
			$user	= & JFactory::getUser();
			$aid	= (int) $user->get('aid');
			$gid	= (int) $user->get('gid');
			$cid	= JRequest::getInt('cid');

			// Create the return parameter
			$return = array (
				'id' 	=> @$this->_item->id,
				'cid'	=> $cid
			);
			$return = serialize($return); 

			// Allow users to see their own content whatever the state is
			if ($this->_item->created_by != $user->id && $this->_item->modified_by != $user->id) 
			{

			// Is the category published?
			if ($cid) {
				if (!$globalcats[$cid]->published) {
					JError::raiseError( 404, JText::_("FLEXI_CATEGORY_NOT_PUBLISHED") );
				}
			}

			// Do we have access to the content itself
			if (@$this->_item->id && $this->_item->state != 1 && $this->_item->state != -5 && $gid < 20 ) // access the workflow for editors or more
			{
				if (!$aid) {
					// Redirect to login
					$url  = $params->get('login_page', 'index.php?option=com_user&view=login');
					$url .= '&return='.base64_encode($return);
	
					$mainframe->redirect($url, JText::_('FLEXI_LOGIN_FIRST') );
				} else {
					// Redirect to unauthorized page or 403
					if ($params->get('unauthorized_page', '')) {
						$mainframe->redirect($params->get('unauthorized_page'));				
					} else {
						JError::raiseError(403, JText::_("ALERTNOTAUTH"));
						return false;
					}
				}
			} else if (@$this->_item->id) { // otherwise check for the standard states
				$canreaditem = FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'item', $this->_item->id) : $this->_item->access <= $aid;
				if (!@$canreaditem)
				{
					if (!$aid) {
						// Redirect to login
						$url  = $params->get('login_page', 'index.php?option=com_user&view=login');
						$url .= '&return='.base64_encode($return);
		
						$mainframe->redirect($url, JText::_('FLEXI_LOGIN_FIRST') );
					} else {
						// Redirect to unauthorized page or 403
						if ($params->get('unauthorized_page', '')) {
							$url  = $params->get('unauthorized_page');
							$url .= '&return='.base64_encode($return);
		
							$mainframe->redirect($url);				
						} else {
							JError::raiseError(403, JText::_("ALERTNOTAUTH"));
							return false;
						}
					}
				}
			}
			}

			//add the author email in order to display the gravatar
			$query = 'SELECT email'
			. ' FROM #__users'
			. ' WHERE id = '. (int) $this->_item->created_by
			;
			$this->_db->setQuery($query);
			$this->_item->creatoremail = $this->_db->loadResult();
			
			//reduce unneeded query
			if ($this->_item->created_by_alias) {
				$this->_item->creator = $this->_item->created_by_alias;
			} else {
				$query = 'SELECT name'
				. ' FROM #__users'
				. ' WHERE id = '. (int) $this->_item->created_by
				;
				$this->_db->setQuery($query);
				$this->_item->creator = $this->_db->loadResult();
			}

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

			if ($this->_item->modified == $this->_db->getNulldate()) {
				$this->_item->modified = null;
			}

			//we show the introtext and fulltext (chr(13) = carriage return)
			//$this->_item->text = $this->_item->introtext . chr(13).chr(13) . $this->_item->fulltext;

			$this->_loadItemParams();
		}
		else
		{
			$user =& JFactory::getUser();
			$item =& JTable::getInstance('flexicontent_items', '');
			if ($user->authorize('com_flexicontent', 'state'))	{
				$item->state = 1;
			}
			$item->id					= 0;
			$item->author				= null;
			$item->created_by			= $user->get('id');
			$item->text					= '';
			$item->title				= null;
			$item->metadesc				= '';
			$item->metakey				= '';
			$item->type_id				= JRequest::getVar('typeid', 0, '', 'int');
			$item->typename				= null;
			$item->search_index			= '';
			$this->_item				= $item;
		}
		return $this->_item;
	}

	/**
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	function _loadItem() {
		static $unapproved_version_notice;
		$task=JRequest::getVar('task',false);
		$option=JRequest::getVar('option',false);
		
		$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');
		// Lets load the item if it doesn't already exist
		if (empty($this->_item)) {
			$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
			$use_versioning = $cparams->get('use_versioning', 1);

			$where	= $this->_buildItemWhere();
			$query = 'SELECT i.*, ie.*, c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
			. ' u.name AS author, u.usertype, ty.name AS typename,'
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $where
			;
			$this->_db->setQuery($query);
			$item = $this->_db->loadObject();
			if(!$item) {
				return false;
			}
			
			// -- Decide the version to load: (a) the one specified by request or (b) the current one or (c) the last one
			$isnew = (($this->_id <= 0) || !$this->_id);
			$current_version = isset($item->version) ? $item->version : 0;
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
			$lastversion = $use_versioning?FLEXIUtilities::getLastVersions($this->_id, true):$current_version;
			if($version==0) 
				JRequest::setVar( 'version', $version = ($loadcurrent?$current_version:$lastversion));
			
			// -- If loading not the current one, then raise a notice to inform the user
			if($current_version != $version && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice) {
				$unapproved_version_notice = 1;  //  While we are in edit form, we catch cases such as modules loading items , or someone querying priviledges of items, etc
				JError::raiseNotice(10, JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') );
			}

			// -- Get by (a) the table that contains versioned data, or by (b) the normal table (current version data only)
			if($use_versioning) 
			{
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_items_versions as iv "
					." JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.version='".$version."' AND iv.item_id='".$this->_id."';";
			}
			else
			{
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_fields_item_relations as iv "
					." JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.item_id='".$this->_id."';";
			}
			$this->_db->setQuery($query);
			$fields = $this->_db->loadObjectList();
			$fields = $fields?$fields:array();
			
			// -- Create the description field called 'text' by appending introtext + readmore + fulltext
			$item->text = $item->introtext;
			if (JString::strlen($item->fulltext) > 1) {
				$item->text .= '<hr id="system-readmore" />' . $item->fulltext;
			}
			
			// (Fix for issue 261), not overwrite joomfish data with versioned data
			
			// -- Retrieve joomfish data for current language if it exists (we will use them on next step instead of versioned data)
			if (FLEXI_FISH) {
				// a. Find if item language is different than current language 
				$currlang = JRequest::getWord('lang', '' );
				if(empty($currlang)){
					$langFactory= JFactory::getLanguage();
					$tagLang = $langFactory->getTag();
					// This more compatible than using the full lenght language tag since its second part maybe some non-standard country
					$currlang = substr($tagLang ,0,2);
				}
				$itemlang = substr($item->language ,0,2);
				$langdiffers = ( $currlang != $itemlang );
				
				// b. Retrieve joomfish data so that if they exist we will not overwrite with versioned data
				if ($langdiffers) {
					$query = "SELECT jfc.* FROM #__jf_content as jfc "
							." LEFT JOIN #__languages as jfl ON jfc.language_id = jfl.id"
							." WHERE jfc.reference_table='content' AND jfc.reference_id = {$this->_id} "
							." AND jfc.published=1 AND jfl.shortcode=".$this->_db->Quote($currlang);
					$this->_db->setQuery($query);
					$jf_data = $this->_db->loadObjectList('reference_field');
					if ($jf_data===false) {
					 die('Error while trying to retrieve (if the exist) item\'s joomfish for current language'.$this->_db->getErrorMsg());
					}
				}
			}
			
			// -- Overwrite item fields with the requested VERSION data, !! we do not overwrite fields that must be translated by joomfish
			foreach($fields as $f) {
				
				// Skip using versioned data for fields that must be translated by joomfish, wWe ONLY skip if joomfish data exists (Fix for issue 261)
				if (FLEXI_FISH) {
					$jf_translated_fields = array('title', 'text', 'introtext', 'fulltext' );
					if ( $task != 'edit' && $langdiffers && in_array($f->name, $jf_translated_fields) ) {
						// if joomfish translation exists for this field, then skip the versioned value and use joomfish value
						if ( !isset($jf_data->{$f->name}) ) continue;
					}
				}
				
				// Use versioned data, by overwriting the item data 
				$fieldname = $f->name;
				if( (($f->field_type=='categories') && ($f->name=='categories')) || (($f->field_type=='tags') && ($f->name=='tags')) ) {
					$item->$fieldname = unserialize($f->value);
				}elseif($fieldname) {
					$item->$fieldname = $f->value;
				}
			}
			
			// -- Retrieve tags (THESE ARE NOT VERSIONED ??? why are they in FC v2.x ?)
			if(!isset($item->tags)||!is_array($item->tags)) {
				$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->tags = $this->_db->loadResultArray();
			}
			
			// -- Retrieve categories (THESE ARE NOT VERSIONED)
			if(!isset($item->categories)||!is_array($item->categories)) {
				$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->categories = $this->_db->loadResultArray();
			}
			$item->id = $this->_id;
			
			// -- Retrieve item TYPE parameters, and ITEM ratings (THESE ARE NOT VERSIONED)
			$query = "SELECT t.name as typename, t.alias as typealias, cr.rating_count, ((cr.rating_sum / cr.rating_count)*20) as score"
					." FROM #__flexicontent_items_ext as ie "
					. " LEFT JOIN #__content_rating AS cr ON cr.content_id = ie.item_id"
					." LEFT JOIN #__flexicontent_types AS t ON ie.type_id = t.id"
					." WHERE ie.item_id='".$this->_id."';";
			$this->_db->setQuery($query);
			$type = $this->_db->loadObject();
			if($type) {
				$item->typename = $type->typename;
				$item->typealias = $type->typealias;
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
			
			// -- Detect if current version doesnot exist in version table and add it !!!
			$this->_item = &$item;
			if(!$isnew && $use_versioning && ($current_version>$lastversion) ) {//add current version.
				$mainframe = &JFactory::getApplication();
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
					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= $field->valueorder;;
					$obj->version		= (int)$current_version;
					// @TODO : move in the plugin code
					if( ($field->field_type=='categories') && ($field->name=='categories') ) {
						continue;
					}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
						continue;
					}else{
						$obj->value			= $field->value;
					}
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					if( !$clean_database && !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
						unset($obj->version);
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
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
				}
				if(!$tagflag) {
					$obj = new stdClass();
					$obj->field_id 		= 14;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$current_version;
					$obj->value		= serialize($item->tags);
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
				}
				$v = new stdClass();
				$v->item_id 		= (int)$item->id;
				$v->version_id		= (int)$current_version;
				$v->created 	= $item->created;
				$v->created_by 	= $item->created_by;
				//$v->comment		= 'kept current version to version table.';
				$this->_db->insertObject('#__flexicontent_versions', $v);
			}
			return (boolean) $this->_item;
		}
		return true;
	}
	
	/**
	 * Method to build the WHERE clause of the query to select a content item
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 * @since	1.5
	 */
	function _buildItemWhere()
	{
		global $mainframe;

		$user		=& JFactory::getUser();
		$aid		= (int) $user->get('aid', 0);

		$jnow		=& JFactory::getDate();
		$now		= $jnow->toMySQL();
		$nullDate	= $this->_db->getNullDate();

		/*
		 * First thing we need to do is assert that the content article is the one
		 * we are looking for and we have access to it.
		 */
		$where = ' WHERE i.id = '. (int) $this->_id;

		if ($aid < 2)
		{
			$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
			$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		}

		return $where;
	}

	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams()
	{
		if (!empty($this->_item->parameters)) return;
		
		global $mainframe;

		// Get the page/component configuration (Priority 4)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// Merge parameters from current category (Priority 3)
		if ($cid = JRequest::getVar( 'cid', 0 ) ) {
			// Retrieve ...
			$query = 'SELECT c.params'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = ' . (int)$cid
					;
			$this->_db->setQuery($query);
			$catparams = $this->_db->loadResult();
			$catparams = new JParameter($catparams);
			
			// Prevent some params from propagating ...
			$catparams->set('show_title', '');
			
			// Merge ...
			$params->merge($catparams);
		}
		
		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		
		// Merge type parameters into the page configuration (Priority 2)
		$tparams = new JParameter($tparams);
		$params->merge($tparams);

		// Merge item parameters into the page configuration (Priority 1)
		$iparams = new JParameter($this->_item->attribs);
		$params->merge($iparams);

/*
		// Set the popup configuration option based on the request
		$pop = JRequest::getVar('pop', 0, '', 'int');
		$params->set('popup', $pop);

		// Are we showing introtext with the article
		if (!$params->get('show_intro') && !empty($this->_article->fulltext)) {
			$this->_article->text = $this->_article->fulltext;
		} else {
			$this->_article->text = $this->_article->introtext . chr(13).chr(13) . $this->_article->fulltext;
		}
*/

		// Set the article object's parameters
		$this->_item->parameters = & $params;
	}

	/**
	 * Method to get the tags
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getTagsX() {
		$query = 'SELECT DISTINCT t.name,'
		. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
		. ' FROM #__flexicontent_tags AS t'
		. ' LEFT JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
		. ' WHERE i.itemid = ' . (int) $this->_id
		. ' AND t.published = 1'
		. ' ORDER BY t.name'
		;

		$this->_db->setQuery( $query );

		$this->_tags = $this->_db->loadObjectList();

		return $this->_tags;
	}
	
	/**
	 * Method to fetch tags
	 * 
	 * @return object
	 * @since 1.0
	 */
	function gettags($mask="") {
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getCategories()
	{
		$query = 'SELECT DISTINCT c.id, c.title,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
		. ' FROM #__categories AS c'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
		. ' WHERE rel.itemid = '.$this->_id
		;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();
		return $this->_cats;
	}

	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function hit() {
		if ( $this->_id )
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}

	function getAlltags() {
		$query = 'SELECT * FROM #__flexicontent_tags ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	function getUsedtags() {
		if(!@$this->_id) $this->_item->tags = array();
		if(@$this->_id && !@$this->_item->tags) {
			$query = 'SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$this->_item->tags = $this->_db->loadResultArray();
		}
		return $this->_item->tags;
	}

	/**
	 * Tests if item is checked out
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
			JError::raiseWarning( 0, 'Unable to Load Data');
			return false;
		}
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
			// Make sure we have a user id to checkout the item with
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
	 * Method to store the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data) {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$mainframe = &JFactory::getApplication();
		$dispatcher = & JDispatcher::getInstance();
		$item  	=& $this->getTable('flexicontent_items', '');
		$user	=& JFactory::getUser();
		
		//$details		= JRequest::getVar( 'details', array(), 'post', 'array');
		$details 		= array();
		$tags 			= JRequest::getVar( 'tag', array(), 'post', 'array');
		$cats 			= JRequest::getVar( 'cid', array(), 'post', 'array');
		$post 			= JRequest::get( 'post', JREQUEST_ALLOWRAW );
		$post['vstate'] = @(int)$post['vstate'];
		$typeid 		= JRequest::getVar('typeid', 0, '', 'int');

		// BOF: Reconstruct (main)text field if it has splitted up e.g. to seperate editors per tab
		if (is_array($data['text'])) {
			$data['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $data['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$tabs_text = '';
			foreach($data['text'] as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$data['text'] = & $tabs_text;
		}
		if (is_array($post['text'])) {
			$post['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $post['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$tabs_text = '';
			foreach($post['text'] as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$post['text'] = & $tabs_text;
		}
		//print_r($data['text']); exit();
		// EOF: Reconstruct (main)text field
		
		// bind it to the table
		if (!$item->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		$item->bind($details);

		// sanitise id field
		$item->id = (int) $item->id;
		
		$nullDate	= $this->_db->getNullDate();

		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$isnew = false;
		$tags = array_unique($tags);
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		
		if( ($isnew = !$item->id) || ($post['vstate']==2) ) {

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

			if ($item->publish_up && strlen(trim( $item->publish_up )) <= 10) {
				$item->publish_up 	.= ' 00:00:00';
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
				$item->catid 		= @$data['catid'] ? $data['catid'] : $cats[0];
			}
			
			// auto assign the section
			$item->sectionid 	= FLEXI_SECTION;

			// set type and language
 			$item->type_id 		= (int)$typeid;
 			$item->language		= $item->language ? $item->language : flexicontent_html::getSiteDefaultLang();			
			
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
			if(!$use_versioning) {
				$item->version = $isnew?1:($current_version+1);
			}else{
				$item->version = $isnew?1:(($post['vstate']==2)?($version+1):$current_version);
			}
			// process field mambots onBeforeSaveItem
			$result = $dispatcher->trigger('onBeforeSaveItem', array(&$item, $isnew));
			if((count($result)>0) && in_array(false, $result)) return false;
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

			foreach($cats as $cat) {
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
		} else {
			JError::raiseNotice(12, JText::_('FLEXI_SAVED_VERSION_MUST_BE_APPROVED_NOTICE') );
			
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
		
		// NOTE: This event is used by 'flexinotify' plugin, and possibly others in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( &$item, &$post ));
		
		// versioning backup procedure
		// first see if versioning feature is enabled
		if ($use_versioning) {
			$v = new stdClass();
			$v->item_id 		= (int)$item->id;
			$v->version_id		= (int)$version+1;
			$v->modified		= $item->modified;
			$v->modified_by		= $item->modified_by;
			$v->created 		= $item->created;
			$v->created_by 		= $item->created_by;
		}
		if($post['vstate']==2) {
			$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		if ($fields) {
			$files	= JRequest::get( 'files', JREQUEST_ALLOWRAW );
			$searchindex = '';
			$jcorefields = flexicontent_html::getJCoreFields();
			foreach($fields as $key=>$field) {
				// process field mambots onBeforeSaveField
				//$results = $dispatcher->trigger('onBeforeSaveField', array( &$field, &$post[$field->name], &$files[$field->name] ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onBeforeSaveField', array( &$field, &$post[$field->name], &$files[$field->name] ) );

				// -- Add the new values to the database 
				$postvalues = $post[$field->name];
				$postvalues = is_array($postvalues) ? $postvalues : array($postvalues);
				$i = 1;
				foreach ($postvalues as $postvalue) {
					
					// -- a. Add versioning values, but do not version the 'hits' field
					if ($field->field_type!='hits' && $field->field_type!='hits') {
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $item->id;
						$obj->valueorder	= $i;
						$obj->version		= (int)$version+1;
						
						// Normally this is redudant, since FLEXIcontent field must have had serialized the parameters of each value already
						$obj->value = is_array($postvalue) ? serialize($postvalue) : $postvalue;
						if ($use_versioning) {
							if ( isset($obj->value) && strlen(trim($obj->value)) ) {
								$this->_db->insertObject('#__flexicontent_items_versions', $obj);
							}
						}
					}
					//echo $field->field_type." ".strlen(trim($obj->value))." ".$field->iscore."<br />";
					
					// -- b. If item is new OR version is approved, AND field is not core (aka stored in the content table or in special table), then add field value to field values table
					if(	( $isnew || $post['vstate']==2 ) && !$field->iscore ) {
						unset($obj->version);
						if ( isset($obj->value) && strlen(trim($obj->value)) ) {
							$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						}
					}
					$i++;
				}
				
				// process field mambots onAfterSaveField
				//$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ) );
				$searchindex 	.= @$field->search;
			}
	
			// store the extended data if the version is approved
			if( ($isnew = !$item->id) || ($post['vstate']==2) ) {
				$item->search_index = $searchindex;
				// Make sure the data is valid
				if (!$item->check()) {
					$this->setError($item->getError());
					return false;
				}
				// Store it in the db
				if (!$item->store()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
		}
		if ($use_versioning) {
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
		$vcount	= FLEXIUtilities::getVersionsCount($item->id);
		$vmax	= $cparams->get('nr_versions', 10);

		if ($vcount > ($vmax+1)) {
			$deleted_version = FLEXIUtilities::getFirstVersion($this->_id, $vmax, $current_version);
			// on efface les versions en trop
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version <' . $deleted_version
					.' AND version!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version_id <' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		// process field mambots onCompleteSaveItem
		$results = $dispatcher->trigger('onCompleteSaveItem', array( &$item, &$fields ));
		return true;
	}

	/**
	 * Method to store a vote
	 * Deprecated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function storevote($id, $vote)
	{
		if ($vote == 1) {
			$target = 'plus';
		} elseif ($vote == 0) {
			$target = 'minus';
		} else {
			return false;
		}

		$query = 'UPDATE #__flexicontent_items_ext'
		.' SET '.$target.' = ( '.$target.' + 1 )'
		.' WHERE item_id = '.(int)$id
		;
		$this->_db->setQuery($query);
		$this->_db->query();

		return true;
	}

	/**
	 * Method to get the categories an item is assigned to
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getCatsselected()
	{
		if(!@$this->_item->categories) {
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$this->_item->categories = $this->_db->loadResultArray();
		}
		return $this->_item->categories;
	}

	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function storetag($data)
	{
		$row  =& $this->getTable('flexicontent_tags', '');

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}
		$this->_tag = &$row;
		return $row->id;
	}

	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addtag($name) {
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;

		//$this->storetag($obj);
		if($this->storetag($obj)) {
			return true;
		}
		return false;
	}

	/**
	 * Method to get the nr of favourites of anitem
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavourites()
	{
		$query = 'SELECT COUNT(id) AS favs FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id;
		$this->_db->setQuery($query);
		$favs = $this->_db->loadResult();
		return $favs;
	}

	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavoured()
	{
		$user = JFactory::getUser();

		$query = 'SELECT COUNT(id) AS fav FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid= '.(int)$user->id;
		$this->_db->setQuery($query);
		$fav = $this->_db->loadResult();
		return $fav;
	}
	
	/**
	 * Method to remove a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function removefav()
	{
		$user = JFactory::getUser();

		$query = 'DELETE FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid = '.(int)$user->id;
		$this->_db->setQuery($query);
		$remfav = $this->_db->query();
		return $remfav;
	}
	
	/**
	 * Method to add a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addfav()
	{
		$user = JFactory::getUser();

		$obj = new stdClass();
		$obj->itemid 	= $this->_id;
		$obj->userid	= $user->id;

		$addfav = $this->_db->insertObject('#__flexicontent_favourites', $obj);
		return $addfav;
	}
	
	/**
	 * Method to change the state of an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function setitemstate($id, $state = 1)
	{
		$user 	=& JFactory::getUser();
		
		if ( $id )
		{

			$query = 'UPDATE #__content'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
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
		$query	= 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t';

		if ($this->_id == null) {
			$type_id = JRequest::getInt('typeid', 0);
			$query .= ' WHERE t.id = ' . (int)$type_id;
		} else {
			$query .= ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
					. ' WHERE ie.item_id = ' . (int)$this->_id
					;
		}
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		return $tparams;
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
		$id = (int)$this->_id;
		if(!$id) return array();
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		$query = 'SELECT value'
			.((($version<=0) || !$use_versioning)?' FROM #__flexicontent_fields_item_relations AS fv':' FROM #__flexicontent_items_versions AS fv')
			.' WHERE fv.item_id = ' . (int)$this->_id
			.' AND fv.field_id = ' . (int)$fieldid
			.((($version>0) && $use_versioning)?' AND fv.version='.((int)$version):'')
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
	function getExtrafields() {
		$typeid = intval(@$this->_item->type_id);
		$version = (int)FLEXIUtilities::getLastVersions($this->_id, true);
		$where = $typeid?' WHERE ftrel.type_id='.(int)$typeid:' WHERE ie.item_id = ' . (int)$this->_id;
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.$where
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
	 * Method to get advanced search fields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getAdvSearchFields($search_fields) {
		$where = " WHERE `name` IN ({$search_fields}) AND fi.isadvsearch='1'";
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			//.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.$where
			.' AND fi.published = 1'
			.' GROUP BY fi.id'
			.' ORDER BY ftrel.ordering, fi.ordering, fi.name'
		;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList('name');
		foreach ($fields as $field) {
			$field->item_id		= 0;
			$field->value 		= $this->getExtrafieldvalue($field->id, 0);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.$field->field_type.'.xml';
			$field->parameters 	= new JParameter($field->attribs, $path);
		}
		return $fields;
	}
}
?>
