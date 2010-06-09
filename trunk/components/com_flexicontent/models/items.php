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
	function &getItem( )
	{
		global $mainframe, $globalcats;
		
		/*
		* Load the Item data
		*/
		if ($this->_loadItem())
		{
			// Get the paramaters of the active menu item
			$params = & $mainframe->getParams('com_flexicontent');
			$user	= & JFactory::getUser();
			$aid	= (int) $user->get('aid');
			$gid	= (int) $user->get('gid');
			$cid	= JRequest::getInt('cid');
			
			// Is the category published?
			if ($cid) {
				if (!$globalcats[$cid]->published) {
					JError::raiseError( 404, JText::_("FLEXI_CATEGORY_NOT_PUBLISHED") );
				}
			}

			// Do we have access to the category?
			if ($this->_item->catid) {
				$ancestors = $globalcats[$this->_item->catid]->ancestorsarray;
				foreach ($ancestors as $cat) {
					if (FLEXI_ACCESS) {
						if (FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'category', $cat)) {
							$canreadcat = true;
						} else {
							$canreadcat = false;
							break;
						}
					} else {
						if ($globalcats[$cat]->access <= $aid) {
							$canreadcat = true;
						} else {
							$canreadcat = false;
							break;
						}
					}
				}

				if (!@$canreadcat)
				{
					if (!$aid) {
						// Redirect to login
						$uri		= JFactory::getURI();
						$return		= $uri->toString();
		
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
				}
			} else {
				JError::raiseError(403, JText::_("FLEXI_ITEM_NO_CAT"));
				return false;
			}
			
			// Do we have access to the content itself
			if ($this->_item->state != 1 && $this->_item->state != -5 && $gid < 20 ) // access the workflow for editors or more
			{
				if (!$aid) {
					// Redirect to login
					$uri		= JFactory::getURI();
					$return		= $uri->toString();
	
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
			} else { // otherwise check for the standard states
				$canreaditem = FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'item', $this->_item->id) : $this->_item->access <= $aid;
				if (!@$canreaditem)
				{
					if (!$aid) {
						// Redirect to login
						$uri		= JFactory::getURI();
						$return		= $uri->toString();
		
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

			//check session if uservisit already recorded
			$session 	=& JFactory::getSession();
			$hitcheck = false;
			if ($session->has('hit', 'flexicontent')) {
				$hitcheck 	= $session->get('hit', 0, 'flexicontent');
				$hitcheck 	= in_array($this->_item->id, $hitcheck);
			}
			if (!$hitcheck) {
				//record hit
				$this->hit();

				$stamp = array();
				$stamp[] = $this->_item->id;
				$session->set('hit', $stamp, 'flexicontent');
			}
			//we show the introtext and fulltext (chr(13) = carriage return)
			$this->_item->text = $this->_item->introtext . chr(13).chr(13) . $this->_item->fulltext;

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
	function _loadItem()
	{
		if($this->_id == '0') {
			return false;
		}

		// Get the WHERE clause
		$where	= $this->_buildItemWhere();

		if (empty($this->_item))
		{
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
			$this->_item = $this->_db->loadObject();
			
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
		global $mainframe;

		// Get the page/component configuration
		$params = clone($mainframe->getParams('com_flexicontent'));

		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		
		// Merge type parameters into the page configuration
		$tparams = new JParameter($tparams);
		$params->merge($tparams);

		// Merge item parameters into the page configuration
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
	function getTags()
	{
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
	function hit()
	{
		global $mainframe;

		if ($this->_id)
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}

	function getAlltags()
	{
		$query = 'SELECT * FROM #__flexicontent_tags ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	function getUsedtags()
	{
		$query = 'SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResultArray();
		return $used;
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
	 * @since	1.0
	 */
	function store($data)
	{
		$item  		=& JTable::getInstance('flexicontent_items', '');
		$user     	=& JFactory::getUser();
		$tags 		= JRequest::getVar( 'tag', array(), 'post', 'array');
		$cats 		= JRequest::getVar( 'cid', array(), 'post', 'array');
		$typeid 	= JRequest::getVar('typeid', 0, '', 'int');
		$post 		= JRequest::get( 'post', JREQUEST_ALLOWRAW );
//		$post['vstate'] = 2;// approve version all time, you can change this if you want
		$data['type_id'] = $typeid;
		$mainframe = &JFactory::getApplication();
		$params 	= & $mainframe->getParams('com_flexicontent');

		// Bind the form fields to the table
		if (!$item->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// sanitise id field
		$item->id = (int) $item->id;
		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$nullDate	= $this->_db->getNullDate();

		// auto assign the main category
		$item->catid 		= $cats[0];
		// auto assign the main category
		$item->sectionid 	= FLEXI_SECTION;
		$item->type_id 		= $typeid;
		$item->language		= flexicontent_html::getSiteDefaultLang();

		$isNew = ($item->id < 1);

		$autopublished = $params->get('autopublished', 0);
		if($autopublished) {
			$item->state = 1;
		}
		if ($isNew)
		{
			$item->created 		= gmdate('Y-m-d H:i:s');
			$item->publish_up 	= $item->created;
			$item->created_by 	= $user->get('id');
			$params 	= & $mainframe->getParams('com_flexicontent');
		}
		else
		{
			$item->modified 	= gmdate('Y-m-d H:i:s');
			$item->modified_by 	= $user->get('id');

			$query = 'SELECT i.hits, i.created, i.created_by, i.version, i.state' .
			' FROM #__content AS i' .
			' WHERE i.id = '.(int) $item->id;

			$this->_db->setQuery($query);
			$result = $this->_db->loadObject();
			
			$item->hits = $result->hits;
			
			$item->created 		= $result->created;
			$item->created_by 	= $result->created_by;
			$item->version 		= $result->version;
			$item->version++;

			//if (!$autopublished && !$user->authorize('com_flexicontent', 'state'))	{
			//	$item->state = $result->state;
			//}
		}
		

		// Publishing state hardening for Authors
		if (!$autopublished && !$user->authorize('com_flexicontent', 'state'))
		{
			if ($isNew)
			{
				// For new items
				$item->state = -3;
			}
			else
			{
				$query = 'SELECT state' .
				' FROM #__content' .
				' WHERE id = '.(int) $item->id;

				$this->_db->setQuery($query);
				$result = $this->_db->loadResult();

				$item->state = $result;
			}
		}
		
		// Search for the {readmore} tag and split the text up accordingly.
		$text = str_replace('<br>', '<br />', $data['text']);

		$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
		$tagPos	= preg_match($pattern, $text);

		if ($tagPos == 0)	{
			$item->introtext	= $text;
		} else 	{
			list($item->introtext, $item->fulltext) = preg_split($pattern, $text, 2);
		}

		// Make sure the data is valid
		if (!$item->check()) {
			$this->setError($item->getError());
			return false;
		}

		// Store the article table to the database
		if (!$item->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		if ($isNew)
		{
			$this->_id = $item->_db->insertId();
		}

		$item->ordering = $item->getNextOrder();

		if ($isNew || $post['vstate']==2) {
			//store tags
			$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();

			foreach($tags as $tag)
			{
				$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
				$this->_db->setQuery($query);
				$this->_db->query();
			}

			//store cat relation
			$query = 'DELETE FROM #__flexicontent_cats_item_relations WHERE itemid = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();

			foreach($cats as $cat)
			{
				$query = 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`) VALUES(' . $cat . ',' . $item->id . ')';
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}
		
		if($isNew || @$post['vstate']) $lastversion = FLEXIUtilities::getLastVersions($this->_id, true);
		$fields		= $this->getExtrafields();
		$dispatcher = & JDispatcher::getInstance();
		
		$current_version = $item->version;
		
		// NOTE: This event isn't used yet but may be useful in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item ));
		
		$db =& JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		// versioning backup procedure
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		// first see if versioning feature is enabled
		$use_versioning = $cparams->get('use_versioning', 1);
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
			foreach($fields as $field) {
				// process field mambots onBeforeSaveField
				$results = $mainframe->triggerEvent('onBeforeSaveField', array( &$field, &$post[$field->name], &$files[$field->name] ));
				// add the new values to the database 
				if (is_array($post[$field->name])) {
					$postvalues = $post[$field->name];
					$i = 1;
					foreach ($postvalues as $postvalue) {
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $item->id;
						$obj->valueorder	= $i;
						$obj->version		= (int)$version+1;
						// @TODO : move to the plugin code
						if (is_array($postvalue)) {
							$obj->value			= serialize($postvalue);
						} else {
							$obj->value			= $postvalue;
						} var_dump($obj);
						if ($use_versioning)
							$this->_db->insertObject('#__flexicontent_items_versions', $obj);
						if(
							($isNew || ($post['vstate']==2) )
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
				} else if ($post[$field->name]) {
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $item->id;
					$obj->valueorder	= 1;
					//$obj->version		= (int)$version+1;
					$obj->version		= (int)$version+1;
					// @TODO : move in the plugin code
					if (is_array($post[$field->name])) {
						$obj->value			= serialize($post[$field->name]);
					} else {
						$obj->value			= $post[$field->name];
					}
					if($use_versioning)
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					if(
						($isNew || ($post['vstate']==2) )
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
	
			// store the extended data if the version is approved
			if( ($isNew = !$item->id) || ($post['vstate']==2) ) {
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
/*
				dump($searchindex,'search');
				dump($item,'item');
*/
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
		$vcount	= FLEXIUtilities::getVersionsCount($this->_id);
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
			$db->setQuery($query);
			$db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version_id <' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$db->setQuery($query);
			$db->query();
		}

		$this->_item	=& $item;
/*
		///////////////////////////////
		// store extra fields values //
		///////////////////////////////
		
		// get the field object
		$this->_id 	= $item->id;	
		$fields		= $this->getExtrafields();
		$dispatcher = & JDispatcher::getInstance();
		
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item ));
		
		// versioning backup procedure
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );

		// delete old values
		$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
		$this->_db->setQuery($query);
		$this->_db->query();

		// let's do first some cleaning
		$post 	= JRequest::get( 'post', JREQUEST_ALLOWRAW );
		$files	= JRequest::get( 'files', JREQUEST_ALLOWRAW );
		
		// here we append some specific values to the post array
		// to handle them as extrafields and store their values
		$post['created'] 		= $item->created;
		$post['created_by'] 	= $item->created_by;
		$post['modified'] 		= $item->modified;
		$post['modified_by'] 	= $item->modified_by;
		$post['version'] 		= $item->version++;

		// intialize the searchindex for fulltext search
		$searchindex = '';
		
		// loop through the field object
		if ($fields)
		{
			foreach($fields as $field)
			{
				// process field mambots onBeforeSaveField
				$results = $dispatcher->trigger('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
				// add the new values to the database 
				if (is_array($post[$field->name]))
				{
					$postvalues = $post[$field->name];
					$i = 1;

					foreach ($postvalues as $postvalue)
					{
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $field->item_id;
						$obj->valueorder	= $i;
						// @TODO : move to the plugin code
						if (is_array($postvalue)) {
							$obj->value			= serialize($postvalue);
						} else {
							$obj->value			= $postvalue;
						}
						
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						$i++;
					}
				}
				else
				{
					if ($post[$field->name])
					{
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $field->item_id;
						$obj->valueorder	= 1;
						// @TODO : move in the plugin code
						if (is_array($post[$field->name])) {
							$obj->value			= serialize($post[$field->name]);
						} else {
							$obj->value			= $post[$field->name];
						}
						
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
				}

				// process field mambots onAfterSaveField
				$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
				
				$searchindex 	.= $field->search;
			}
			
			$item->search_index = $searchindex;
			$item->version--;

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
*/

		return $this->_item->id;
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
		$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResultArray();
		return $used;
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

		return $row->id;
	}

	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addtag($name)
	{
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;

		$this->storetag($obj);

		return true;
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
	 * @return object
	 * @since 1.5
	 * @todo move in a specific class and add the parameter $itemid
	 */
	function getExtrafieldvalue($fieldid, $version = 0)
	{
		$id = (int)$this->_id;
		if(!$id) return array();
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
	function getExtrafields() {
		$typeid = JRequest::getVar('typeid', 0, '', 'int');
		$version = JRequest::getVar( 'version', '', 'request', 'int' );
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
}
?>