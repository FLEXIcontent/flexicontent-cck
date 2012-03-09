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
		// Set item id and wipe data
		if ($this->_id != $id) {
			$this->_item		= null;
		}
		$this->_id = $id;
	}
	

	/**
	 * Method to get data for the itemview
	 *
	 * @access public
	 * @return array
	 * @since 1.0
	 */
	function &getItem($pk=null, $isform=true) {
		global $globalcats;
		$mainframe =& JFactory::getApplication();
		$cparams = clone($mainframe->getParams('com_flexicontent'));
		
		if($isform) {
			$item = parent::getItem($pk);
			$this->_item = &$item;
			return $this->_item;
		}
		
		// Cache items retrieved, we can retrieve multiple items, for this purpose
		// use function setId($pk) to change primary key and then call getItem()
		static $items = array();
		if (isset ($items[@$this->_item->id])) {
			return $items[@$this->_item->id];
		}
		
		// Initialise variables.
		$pk		= (!empty($pk)) ? $pk : $this->_id;
		$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');
		$cid	= JRequest::getInt('cid');  // CURRENT CATEGORY
		
		// when previewing SET item version and also set a warning message to warn the user that the previewing version is not CURRENT
		$preview = JRequest::getVar('preview');
		if($preview) {
			$lversion = JRequest::getVar('version');//loaded version
			$lversion = $lversion?$lversion:FLEXIUtilities::getLastVersions($pk, true);//latest version
			$cversion = FLEXIUtilities::getCurrentVersions($pk, true);
			if($lversion!=$cversion) {
				$warning = "This version --$lversion-- of the document is not yet current. Current document version is: --$cversion--<br>A privileged user (e.g. admin) must approved it, before it becomes current and visible to the public";
				JError::raiseWarning(403, $warning);
			}
			JRequest::setVar('lversion', $lversion);
		}

		if (!isset($this->_item)) {

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
					'a.metakey, a.metadesc, a.access, a.hits, a.metadata, a.featured, a.language, a.xreference'.($preview?',ver.version_id':'')
					)
				);
				$query->from('#__content AS a');
				
				$query->select('ie.*, ty.name AS typename, ty.alias as typealias, c.lft,c.rgt');
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
				
				//preview mode
				if($preview) {
					$query->join('LEFT', '#__flexicontent_versions AS ver ON ver.item_id = a.id');
					$query->where("ver.version_id = '" . $lversion . "'");
				}

				// Filter by published state.
				$published = $this->getState('filter.published');
				$archived = $this->getState('filter.archived');

				if (is_numeric($published)) {
					$query->where('(a.state = ' . (int) $published . ' OR a.state =' . (int) $archived . ')');
				}

				$db->setQuery($query);
				$data = $db->loadObject();
				
				if ($preview) {
					// When previewing load the specified item version
					$data = $this->loadUnapprovedVersion($data, $lversion);
				}

				if ($error = $db->getErrorMsg()) {
					throw new Exception($error);
				}

				if (empty($data)) {
					return JError::raiseError(404,JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND'));
				}

				// Check for published state if filter set.
				if (((is_numeric($published)) || (is_numeric($archived))) && (($data->state != $published) && ($data->state != $archived))) {
					return JError::raiseError(404,JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND'));
				}
				
				// Convert metadata string to parameters (object)
				$registry = new JRegistry;
				$registry->loadString($data->metadata);
				$data->metadata = $registry;

				$this->_item = $data;
				$this->_loadItemParams();
				$params = & $this->_item->parameters;

				$user	= & JFactory::getUser();
				$asset = 'com_content.article.' . $data->id;
				
				// ********************************************************************************************
				// CHECK item's -VIEWING- ACCESS, this could be moved to the controller, if we do this, then we
				// must check the view variable, because DISPLAY() CONTROLLER TASK is shared among all views)
				// ... or create a separate FRONTEND controller for the ITEM VIEW
				// ********************************************************************************************
				
				if (@$this->_item->id)
				{
					// Calculate edit access ... we will allow view access if current user can edit the item (but set a warning message about it, see bellow)
					$canedititem = $params->get('access-edit');
					
					// Do not allow item's VERSION PREVIEWING unless the user can edit the item
					if ($preview && !$canedititem) {
						JError::raiseError(500, JText::_('JERROR_ALERTNOAUTHOR')."<br />You cannot preview an item that you cannot edit.");
					}
					
					// Check that current category is published (only if item cannot be edited)
					if ( !$canedititem && $cid) {
						if (!$globalcats[$cid]->published) {
							JError::raiseError( 404, JText::_("FLEXI_CATEGORY_NOT_PUBLISHED") );
						}
					}
					
					// Check that the item is published
					if ($this->_item->state != 1 && $this->_item->state != -5)
					{
						// ITEM NOT PUBLISHED, 
						$canreaditem = false;
						
						// (a) Set warning that the item is unpublished
						$mainframe->enqueueMessage('The item is not published','message');
						// (b) Set warning to the editors, that they are viewing unpublished content
						if ( $canedititem && !JRequest::getVar('task', false) ) {
							$mainframe->enqueueMessage('Viewing access allowed because you can edit the item', 'message');
						}
						
					} else {
						// ITEM PUBLISHED, check for standard view access
						$canreaditem = $params->get('access-view');
					}
				
					// REDIRECT TO APPROPRIATE PAGES IF ACCESS IS ALLOWED
					if (!$canreaditem && !$canedititem)
					{
						if($user->guest) {
							// Redirect to login
							$uri		= JFactory::getURI();
							$return		= $uri->toString();
							$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
							$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
							$url .= '&return='.base64_encode($return);
			
							JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
							$mainframe->redirect( $url );
						} else {
							if ($cparams->get('unauthorized_page', '')) {
								$mainframe->redirect($cparams->get('unauthorized_page'));				
							} else {
								JError::raiseWarning( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
								$mainframe->redirect( 'index.php' );
							}
						}
					} else if (!$canreaditem && !JRequest::getVar('task', false) ) {
						$mainframe->enqueueMessage('You do not have view access.<br /> Viewing access allowed because you can edit the item', 'message');
					}
				}
			}
			catch (JException $e)
			{
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to work.
					JError::raiseError(404, $e->getMessage());
				}
				else {
					$this->setError($e);
					$this->_item = false;
				}
			}
		}

		$items[@$this->_item->id] = & $this->_item;
		return $this->_item;
	}
	
	/**
	* Method to load unapproved version
	*/
	function &loadUnapprovedVersion(&$data, $lversion) {
		$db = &JFactory::getDBO();
		$jfields = flexicontent_html::getJCoreFields(NULL, true, true);
		$query = "SELECT f.field_type,f.name,ftr.field_id, iv.valueorder, value FROM #__flexicontent_items_versions as iv"
			. " JOIN #__flexicontent_fields_type_relations as ftr ON iv.field_id=ftr.field_id"
			. " JOIN #__flexicontent_fields as f ON iv.field_id=f.id"
			. " WHERE ftr.type_id='{$data->type_id}' AND iv.version='{$lversion}' AND iv.item_id='{$data->id}'"
			. " AND published='1' AND iscore='1'"
			;
		$db->setQuery($query);
		$objs = $db->loadObjectList();
		$objs = $objs ? $objs : array();
		foreach($objs as $obj) {
			$v = @json_decode($obj->value);
			if($v) {
				$obj->value = $v;
			}
			if(isset($jfields[$obj->field_type]) && isset($data->$jfields[$obj->field_type])) {
				if(@json_decode($obj->value)) {
					$data->$jfields[$obj->field_type] = json_decode($obj->value);
				}else{
					$data->$jfields[$obj->field_type] = $obj->value;
				}
			}
		}
		return $data;
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
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams() {
		$mainframe = &JFactory::getApplication();
		jimport('joomla.html.parameter');

		// Get the page/component configuration  (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$cparams = clone($mainframe->getParams('com_flexicontent'));
		$params = & $cparams;
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE) {
			if ($menu = JSite::getMenu()->getActive()) {
				$menuParams = new JRegistry;
				$menuParams->loadJSON($menu->params);
				$params->merge($menuParams);
			}
		}

		// Merge parameters from current category
		if ($cid = JRequest::getVar( 'cid', 0 ) ) {
			$query = 'SELECT c.params'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = ' . (int)$cid
					;
			$this->_db->setQuery($query);
			$catparams = $this->_db->loadResult();
			$catparams = new JParameter($catparams);
			
			// Prevent some params from propagating ...
			$catparams->set('show_title', '');
			
			// Merge categories parameters (Priority 3)
			$params->merge($catparams);
		}
		
		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$typeparams = $this->_db->loadResult();
		
		// Merge TYPE parameters into the page configuration (Priority 2)
		$typeparams = new JParameter($typeparams);
		$params->merge($typeparams);

		// Merge ITEM parameters into the page configuration (Priority 1)
		$itemparams = new JParameter($this->_item->attribs);
		$params->merge($itemparams);

		// Merge ACCESS permissions into the page configuration (Priority 0)
		$accessperms = $this->getItemAccess();
		$params->merge($accessperms);

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
