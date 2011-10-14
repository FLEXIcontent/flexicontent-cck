<?php
/**
 * @version 1.5 stable $Id: items.php 682 2011-07-25 11:26:27Z enjoyman@gmail.com $
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
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'parentclassitem.php');

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItem extends ParentClassItem
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
	/*function get($property, $default=null)
	{
		if ($this->_loadItem()) {
			if(isset($this->_item->$property)) {
				return $this->_item->$property;
			}
		}
		return $default;
	}*/
	
	/**
	 * Overridden set method to pass properties on to the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	/*function set( $property, $value=null )
	{
		if ($this->_loadItem()) {
			$this->_item->$property = $value;
			return true;
		} else {
			return false;
		}
	}*/

	/**
	 * Method to get data for the itemview
	 *
	 * @access public
	 * @return array
	 * @since 1.0
	 */
	function &getItem($pk=null, $isform=true) {
		if($isform) {
			$item = parent::getItem($pk);
			$this->_item = &$item;
			return $this->_item;
		}
		// Initialise variables.
		$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');
		//$pk = (!empty($pk)) ? $pk : (int) $this->getState('article.id');

		//if ($this->_item === null) {
		//	$this->_item = array();
		//}

		if (!isset($this->_item[$pk])) {

			try {
				$db = $this->getDbo();
				$query = $db->getQuery(true);

				$query->select($this->getState(
					'item.select', 'a.id, a.asset_id, a.title, a.alias, a.title_alias, a.introtext, a.fulltext, ' .
					// If badcats is not null, this means that the article is inside an unpublished category
					// In this case, the state is set to 0 to indicate Unpublished (even if the article state is Published)
					'CASE WHEN badcats.id is null THEN a.state ELSE 0 END AS state, ' .
					'a.mask, a.catid, a.created, a.created_by, a.created_by_alias, ' .
					'a.modified, a.modified_by, a.checked_out, a.checked_out_time, a.publish_up, a.publish_down, ' .
					'a.images, a.urls, a.attribs, a.version, a.parentid, a.ordering, ' .
					'a.metakey, a.metadesc, a.access, a.hits, a.metadata, a.featured, a.language, a.xreference'
					)
				);
				$query->from('#__content AS a');
				
				$query->select('ie.*,ty.name AS typename,c.lft,c.rgt');
				$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id');
				$query->join('LEFT', '#__flexicontent_types AS ty ON ie.type_id = ty.id');
				$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id');

				// Join on category table.
				$query->select('c.title AS category_title, c.alias AS category_alias, c.access AS category_access');
				$query->select( 'CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug');
				$query->select( 'CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug');
				$query->join('LEFT', '#__categories AS c on c.id = a.catid');

				// Join on user table.
				$query->select('u.name AS author');
				$query->join('LEFT', '#__users AS u on u.id = a.created_by');

				// Join on contact table
				$query->select('contact.id as contactid' ) ;
				$query->join('LEFT','#__contact_details AS contact on contact.user_id = a.created_by');


				// Join over the categories to get parent category titles
				$query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias');
				$query->join('LEFT', '#__categories as parent ON parent.id = c.parent_id');

				// Join on voting table
				$query->select('ROUND( v.rating_sum / v.rating_count ) AS rating, v.rating_count as rating_count');
				$query->join('LEFT', '#__content_rating AS v ON a.id = v.content_id');

				$query->where('a.id = ' . (int) $pk);

				// Filter by start and end dates.
				$nullDate = $db->Quote($db->getNullDate());
				$nowDate = $db->Quote(JFactory::getDate()->toMySQL());

				$query->where('(a.publish_up = ' . $nullDate . ' OR a.publish_up <= ' . $nowDate . ')');
				$query->where('(a.publish_down = ' . $nullDate . ' OR a.publish_down >= ' . $nowDate . ')');

				// Join to check for category published state in parent categories up the tree
				// If all categories are published, badcats.id will be null, and we just use the article state
				$subquery = ' (SELECT cat.id as id FROM #__categories AS cat JOIN #__categories AS parent ';
				$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
				$subquery .= 'WHERE parent.extension = ' . $db->quote('com_content');
				$subquery .= ' AND parent.published <= 0 GROUP BY cat.id)';
				$query->join('LEFT OUTER', $subquery . ' AS badcats ON badcats.id = c.id');

				// Filter by published state.
				$published = $this->getState('filter.published');
				$archived = $this->getState('filter.archived');

				if (is_numeric($published)) {
					$query->where('(a.state = ' . (int) $published . ' OR a.state =' . (int) $archived . ')');
				}

				$db->setQuery($query);

				$data = $db->loadObject();

				if ($error = $db->getErrorMsg()) {
					throw new Exception($error);
				}

				if (empty($data)) {
					//return JError::raiseError(404,JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND'));
					return JError::raiseError(404,JText::_('Article not found or it is is currently being changed by an editor'));
				}

				// Check for published state if filter set.
				if (((is_numeric($published)) || (is_numeric($archived))) && (($data->state != $published) && ($data->state != $archived))) {
					return JError::raiseError(404,JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND'));
				}
				
				// Convert metadata string to parameters (object)
				$registry = new JRegistry;
				$registry->loadString($data->metadata);
				$data->metadata = $registry;

				//$this->_item[$pk] = $data;
				$this->_item = $data;
				$this->_loadItemParams();
			}
			catch (JException $e)
			{
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to work.
					JError::raiseError(404, $e->getMessage());
				}
				else {
					$this->setError($e);
					//$this->_item[$pk] = false;
					$this->_item = false;
				}
			}
		}

		//return $this->_item[$pk];
		return $this->_item;
	}

	/**
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	/*function _loadItem() {
		$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');
		// Lets load the item if it doesn't already exist
		if (empty($this->_item)) {
			$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
			$use_versioning = $cparams->get('use_versioning', 1);

			$where	= $this->_buildItemWhere();
			$query = 'SELECT i.*, ie.*, c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
			. ' u.name AS author, u.usertype, ty.name AS typename,c.lft,c.rgt,'
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
			
			$isnew = (($this->_id <= 0) || !$this->_id);
			$current_version = isset($item->version)?$item->version:0;
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
			//$lastversion = $use_versioning?FLEXIUtilities::getLastVersions($this->_id, true, true):$current_version;
			$lastversion = $use_versioning?FLEXIUtilities::getLastVersions($this->_id, true):$current_version;
			if($version==0) 
				JRequest::setVar( 'version', $version = ($loadcurrent?$current_version:$lastversion));
			if($use_versioning) {
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_items_versions as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.version='".$version."' AND iv.item_id='".$this->_id."';";
			}else{
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_fields_item_relations as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.item_id='".$this->_id."';";
			}
			$this->_db->setQuery($query);
			$fields = $this->_db->loadObjectList();
			$fields = $fields?$fields:array();

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
				//$item->text = $item->introtext;
				$item->text = $item->introtext . '<hr id="system-readmore" />' . $item->fulltext;
			}
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
					//JPluginHelper::importPlugin('flexicontent_fields', ($field->iscore ? 'core' : $field->field_type) );
					
				    // process field mambots onBeforeSaveField
					//$results = $dispatcher->trigger('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

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
	}*/
	
	/**
	 * Method to build the WHERE clause of the query to select a content item
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 * @since	1.5
	 */
	/*function _buildItemWhere()
	{
		$mainframe =& JFactory::getApplication();

		$user		=& JFactory::getUser();
		$aid		= max ($user->getAuthorisedViewLevels());

		$jnow		=& JFactory::getDate();
		$now		= $jnow->toMySQL();
		$nullDate	= $this->_db->getNullDate();

		//
		// First thing we need to do is assert that the content article is the one
		// we are looking for and we have access to it.
		//
		$where = ' WHERE i.id = '. (int) $this->_id;

		if ($aid < 2)
		{
			$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
			$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		}

		return $where;
	}*/


	/**
	 * Method to calculate Item Access Permissions
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _getItemAccess() {
		// Convert parameter fields to objects.
		/*$registry = new JRegistry;
		$registry->loadString($this->_item->attribs);
		$iparams_extra = clone $this->getState('params');
		$iparams_extra->merge($registry);*/
		$iparams_extra = new JRegistry;
		
		// Compute selected asset permissions.
		$user	= JFactory::getUser();
		
		// Technically guest could edit an article, but lets not check that to improve performance a little.
		if (!$user->get('guest')) {
			$userId	= $user->get('id');
			$asset	= 'com_content.article.'.$this->_item->id;
		
			// Check general edit permission first.
			if ($user->authorise('core.edit', $asset)) {
				$iparams_extra->set('access-edit', true);
			}
			// Now check if edit.own is available.
			else if (!empty($userId) && $user->authorise('core.edit.own', $asset)) {
				// Check for a valid user and that they are the owner.
				if ($userId == $this->_item->created_by) {
					$iparams_extra->set('access-edit', true);
				}
			}
		}
		
		// Compute view access permissions.
		if ($access = $this->getState('filter.access')) {
			// If the access filter has been set, we already know this user can view.
			$iparams_extra->set('access-view', true);
		}
		else {
			// If no access filter is set, the layout takes some responsibility for display of limited information.
			$user = JFactory::getUser();
			$groups = $user->getAuthorisedViewLevels();
		
			if ($this->_item->catid == 0 || $this->_item->category_access === null) {
				$iparams_extra->set('access-view', in_array($this->_item->access, $groups));
			}
			else {
				$iparams_extra->set('access-view', in_array($this->_item->access, $groups) && in_array($this->_item->category_access, $groups));
			}
		}
		
		return $iparams_extra;
	}

	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams() {
		$mainframe = &JFactory::getApplication();
		jimport('joomla.html.parameter');

		// Get the page/component configuration
		$params = clone($mainframe->getParams('com_flexicontent'));

		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$typeparams = $this->_db->loadResult();
		
		// Merge TYPE parameters into the page configuration
		$typeparams = new JParameter($typeparams);
		$params->merge($typeparams);

		// Merge ITEM parameters into the page configuration
		$itemparams = new JParameter($this->_item->attribs);
		$params->merge($itemparams);

		// Merge ACCESS permissions into the page configuration
		$accessperms = $this->_getItemAccess();
		$params->merge($accessperms);

//		// Set the popup configuration option based on the request
//		$pop = JRequest::getVar('pop', 0, '', 'int');
//		$params->set('popup', $pop);
//
//		// Are we showing introtext with the article
//		if (!$params->get('show_intro') && !empty($this->_article->fulltext)) {
//			$this->_article->text = $this->_article->fulltext;
//		} else {
//			$this->_article->text = $this->_article->introtext . chr(13).chr(13) . $this->_article->fulltext;
//		}


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
	/*function getTagsX() {
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
	}*/
	
	/**
	 * Method to fetch tags
	 * 
	 * @return object
	 * @since 1.0
	 */
	/*function gettags($mask="") {
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}*/

	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	/*function getCategories()
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
	}*/

	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function hit() {
		$mainframe =& JFactory::getApplication();

		if ($this->_id)
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
			//$this->_item->tags = $this->_db->loadResultArray();
			$this->_tags = $this->_db->loadResultArray();
		}
		return $this->_tags;
	}

	/**
	 * Tests if item is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	/*function isCheckedOut( $uid=0 )
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
	}*/

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function checkin()
	{
		if ($this->_id)
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			return $item->checkin($this->_id);
		}
		return false;
	}*/

	/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function checkout($uid = null)
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
	}*/
	
	/**
	 * Method to store the item
	 *
	 * @access	public
	 * @since	1.0
	 */
	/*function store($data) {
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

			if ($isnew) {
				$date =& JFactory::getDate($item->created, $tzoffset);
				$item->created = $date->toMySQL();
			}

			if ($item->publish_up) {
				// Append time if not added to publish date
				if (strlen(trim($item->publish_up)) <= 10) {
					$item->publish_up .= ' 00:00:00';
				}
			} else {
				$date =& JFactory::getDate($item->created, $tzoffset);
				$item->publish_up = $date->toMySQL();
			}

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
			//$item->sectionid 	= 0;

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
			//if (FLEXI_ACCESS) {//commented by enjoyman, I don't understand these lines.
			//	$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			//	$canRight 	= (in_array('right', $rights) || $user->gid >= 24);
			//	if ($canRight) FAccess::saveaccess( $item, 'item' );
			//}

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
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item, &$post ));
		
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
			    JPluginHelper::importPlugin('flexicontent_fields', ($field->iscore ? 'core' : $field->field_type) );
			    
				// process field mambots onBeforeSaveField
				$results = $dispatcher->trigger('onBeforeSaveField', array( &$field, &$post[$field->name], &$files[$field->name] ));

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
						}
						$fields[$key]->value[] = $obj->value;
						if ($use_versioning)
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
				} else if (isset($post[$field->name])) {
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $item->id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$version+1;
					// @TODO : move in the plugin code
					if (is_array($post[$field->name])) {
						$obj->value			= serialize($post[$field->name]);
					} else {
						$obj->value			= $post[$field->name];
					}
					$fields[$key]->value[] = $obj->value;
					if($use_versioning)
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
	}*/

	/**
	 * Method to store a vote
	 * Deprecated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function storevote($id, $vote)
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
	}*/

	/**
	 * Method to get the categories an item is assigned to
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function getCatsselected()
	{
		if(!@$this->_item->categories) {
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$this->_item->categories = $this->_db->loadResultArray();
		}
		return $this->_item->categories;
	}*/

	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function storetag($data)
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
	}*/

	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function addtag($name) {
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;

		//$this->storetag($obj);
		if($this->storetag($obj)) {
			return true;
		}
		return false;
	}*/

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
	 * @since	1.0/
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
	/*function setitemstate($id, $state = 1)
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
	}*/

	/**
	 * Method to get the type parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	/*function getTypeparams ()
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
	}*/

	
	/**
	 * Method to get the values of an extrafield
	 * 
	 * @return object
	 * @since 1.5
	 * @todo move in a specific class and add the parameter $itemid
	 */
	/*function getExtrafieldvalue($fieldid, $version = 0)
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
	}*/
	
	/**
	 * Method to get extrafields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	/*function getExtrafields() {
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
		jimport("joomla.html.parameter");
		foreach ($fields as $field) {
			$field->item_id		= (int)$this->_id;
			$field->value 		= $this->getExtrafieldvalue($field->id, $version);
			$field->parameters 	= new JParameter($field->attribs);
		}
		return $fields;
	}*/
	
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
