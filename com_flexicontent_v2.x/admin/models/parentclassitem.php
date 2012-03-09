<?php
/**
 * @version 1.5 stable $Id: item.php 373 2010-07-22 12:43:24Z enjoyman $
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

jimport('joomla.application.component.modeladmin');
/**
 * FLEXIcontent Component Category Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class ParentClassItem extends JModelAdmin {
	/**
	 * Component parameters
	 *
	 * @var object
	 */
	var $_cparams = null;
	
	/**
	 * Item id
	 *
	 * @var int
	 */
	var $_id = 0;
	
	/**
	 * Item data
	 *
	 * @var object
	 */
	var $_item = null;
	
	/**
	 * Item tags
	 *
	 * @var array
	 */
	var $_tags = null;
	
	/**
	 * Item current version
	 *
	 * @var int
	 */
	var $_currentversion = -1;

	
	/**
	 * Item current category id
	 *
	 * @var int
	 */
	var $_currentcatid = 0;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {
		parent::__construct();
		
		$this->_cparams = & JComponentHelper::getParams( 'com_flexicontent' );
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id, $currcatid=0) {
		// Set item id and wipe data
		if ($this->_id != $id) {
			$this->_item = null;
		}
		$this->_id = $id;
		$this->_currentcatid = $currcatid;
	}
	
	/**
	 * Method to get the identifier
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getId() {
		return $this->_id;
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
		if ( $this->_item || $this->_item = $this->getItem() ) {
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
		if ( $this->_item || $this->_item = $this->getItem() ) {
			$this->_item->$property = $value;
			return true;
		} else {
			return false;
		}
	}

	
	/**
	 * Method to get the row form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 * @since	1.6
	 */
	public function getForm($data = array(), $loadData = true) {
		// Get the form.
		$form = $this->loadForm('com_flexicontent.item', 'item', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		// Determine correct permissions to check.
		$id = @$data['id'] ? $data['id'] : (int) $this->getState($this->getName().'.id');
		if ($id) {
			// Existing record. Can only edit in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit');
			// Existing record. Can only edit own articles in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit.own');
		}
		else {
			// New record. Can only create in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.create');
		}

		// Modify the form based on Edit State access controls.
		if (!$this->canEditState((object) $data)) {
			// Disable fields for display.
			$form->setFieldAttribute('featured', 'disabled', 'true');
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('publish_up', 'disabled', 'true');
			$form->setFieldAttribute('publish_down', 'disabled', 'true');
			$form->setFieldAttribute('state', 'disabled', 'true');

			// Disable fields while saving.
			// The controller has already verified this is an article you can edit.
			$form->setFieldAttribute('featured', 'filter', 'unset');
			$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('publish_up', 'filter', 'unset');
			$form->setFieldAttribute('publish_down', 'filter', 'unset');
			$form->setFieldAttribute('state', 'filter', 'unset');
		}

		return $form;
	}
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	protected function loadFormData() {
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}
	
	/**
	 * Method to get a single record.
	 *
	 * @param	integer	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 */
	public function &getItem($pk = null, $isform = false) {
		static $item;
		static $unapproved_version_notice;
		
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		$task = JRequest::getVar('task',false);
		$option = JRequest::getVar('option',false);
		$isform = ($isform===false) ? ($task!=false) : $isform;

		if(!$item) {
			// Initialise variables.
			$pk		= (!empty($pk)) ? $pk : $this->_id;
			$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');

			if ($item = parent::getItem($pk))
			{
				$version = JRequest::getVar( 'version', 0, 'request', 'int' );
				
				if($isform && $use_versioning) {
					if(!$version) {
						$version = FLEXIUtilities::getLastVersions($item->id, true);
						JRequest::setVar( 'version', $version);
					}
					
					if($version != $item->version && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice) {
						$unapproved_version_notice = 1;  //  While we are in edit form, we catch cases such as modules loading items , or someone querying priviledges of items, etc
						JError::raiseNotice(10, JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') );
					}
				}
				
				// Convert the params field to an array.
				$registry = new JRegistry;
				$registry->loadJSON($item->attribs);
				$item->attribs = $registry->toArray();
				$this->_currentversion = $item->version;

				// Convert the params field to an array.
				$registry = new JRegistry;
				$registry->loadJSON($item->metadata);
				$item->metadata = $registry->toArray();

				$item->text = trim($item->fulltext) != '' ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
				$item->_currentversion = $item->version;
				$query = 'SELECT access'
					. ' FROM #__categories'
					. ' WHERE id = '. (int) $item->catid
					;
				$this->_db->setQuery($query);
				$item->category_access = $this->_db->loadResult();

				$query = 'SELECT name'
					. ' FROM #__users'
					. ' WHERE id = '. (int) $item->created_by
					;
				$this->_db->setQuery($query);
				$item->creator = $this->_db->loadResult();

				//reduce unneeded query
				if ($item->created_by == $item->modified_by) {
					$item->modifier = $item->creator;
				} else {
					$query = 'SELECT name'
						. ' FROM #__users'
						. ' WHERE id = '. (int) $item->modified_by
						;
					$this->_db->setQuery($query);
					$item->modifier = $this->_db->loadResult();
				}
				$fields = $this->getExtraFields();
				
				$item->cid = @$fields['categories']->value;
				// Calculate item's score so far (percentage), we have 5 votes max , so 5 * 20 = 100%
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
				}else{
					$item->score = 0;
				}
				
			}
			
			else if ($pk > 0)
			{
				return JError::raiseError(404,JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND'));
			}
			
			else
			{		
				$item->state = $this->_cparams->get('new_item_state', -4);
				//$item->language = flexicontent_html::getSiteDefaultLang();
			}
			
			$item->type_id = $this->getTypesselected()->id;
		}
		return $item;
	}
	
	/**
	 * Stock method to auto-populate the model state.
	 *
	 * @return	void
	 * @since	1.6
	 */
	protected function populateState() {
		$app = &JFactory::getApplication();
		
		// get ITEM's primary key (pk) 
		if (!$app->isAdmin()) {
			// FRONTEND, use "id" from request
			$pk = JRequest::getVar('id', 0, 'default', 'int');
			$curcatid = JRequest::getVar('cid', 0, $hash='default', 'int');
		} else {
			// BACKEND, use "cid" array from request
			$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
			$pk = $cid[0];
			$curcatid = 0;
		}
		
		// Initialise variables.
		$this->setState($this->getName().'.id', $pk);
		$this->setId($pk, $curcatid);  // NOTE: when setting $pk to a new value the $this->_item is cleared

		// Load global parameters
		$this->setState('params', $this->_cparams);
	}
	
	/**
	 * Returns a Table object, always creating it
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 * @since	1.6
	*/
	public function getTable($type = 'flexicontent_items', $prefix = '', $config = array()) {
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin() {
		if ($this->_id) {
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
	function checkout($uid = null) {
		if ($this->_id) {
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
	function isCheckedOut( $uid=0 ) {
		if ( $this->_item || $this->_item = $this->getItem() ) {
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
	 * Method to calculate Item Access Permissions
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getItemAccess($create_cats=array()) {
		$iparams_extra = new JRegistry;
		$user		= JFactory::getUser();
		$asset	= 'com_content.article.'.$this->_id;
		$permission	= FlexicontentHelperPerm::getPerm();  // Global component permissions
		
		// NOTE, technically in J1.6+ a guest may edit able to edit/delete an item, so we commented out the guest check bellow,
		// this applies for creating item, but flexicontent already allows create to guests via menu item too, so no check there too
		
		// Compute CREATE access permissions.
		if ( !$this->_id ) {
			// first check if general create permission is missing, to avoid unneeded checking of creation in individual categories
			if ( !$user->authorise('core.create', 'com_flexicontent') ) {
				$iparams_extra->set('access-create', false);
			}
			// general permission is present, check that user can create item in at least one category
			else {
				$usercats = FlexicontentHelperPerm::getCats(array('core.create'));
				$iparams_extra->set('access-create', count($usercats));
			}
			return $iparams_extra;  // New item, so do not calculate EDIT, DELETE and VIEW access
		}
		
		// Not a new item retrieve item if not already done
		if ( empty($this->_item) ) {
			$this->_item = $this->getItem();
		}

		// Compute EDIT access permissions.
		if ( $this->_id ) {
			// first check edit permission on the item
			if ($user->authorise('core.edit', $asset)) {
				$iparams_extra->set('access-edit', true);
			}
			// no edit permission, check if edit.own is available for this item
			else if ( $user->authorise('core.edit.own', $asset) && $user->get('id') == $this->_item->created_by  /* && !$user->get('guest') */ ) {
				// Check ownership (this maybe needed since ownership may have changed ? and above permission maybe invalid? )
				if ($user->get('id') == $this->_item->created_by) {
					$iparams_extra->set('access-edit', true);
				}
			}
		}

		// Compute DELETE access permissions.
		if ( $this->_id ) {
		
			// first check delete permission on the item
			if ($user->authorise('core.delete', $asset)) {
				$iparams_extra->set('access-delete', true);
			}
			// no delete permission, chekc delete.own permission if the item is owned by the user
			else if ( $user->authorise('core.delete.own', $asset) && $user->get('id') == $this->_item->created_by  /* && !$user->get('guest') */ ) {
				// Check ownership
				if ($user->get('id') == $this->_item->created_by) {
					$iparams_extra->set('access-delete', true);
				}
			}
		}
		
		// Compute VIEW access permissions.
		if ($access = $this->getState('filter.access')) {
			// If the access filter has been set, we already know this user can view.
			$iparams_extra->set('access-view', true);
		}
		else {
			// If no access filter is set, the layout takes some responsibility for display of limited information.
			$user = JFactory::getUser();
			$groups = $user->getAuthorisedViewLevels();
			
			// If no category info available, then use only item access level
			if ($this->_item->catid == 0 || $this->_item->category_access === null) {
				$iparams_extra->set('access-view', in_array($this->_item->access, $groups));
			}
			// Require both item and category access level
			else {
				$iparams_extra->set('access-view', in_array($this->_item->access, $groups) && in_array($this->_item->category_access, $groups));
			}
		}

		return $iparams_extra;
	}



	/**
	 * Method to check if you can assign a (new/existing) item in the specified categories
	 *
	 * @param	array	An array of input data.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	protected function itemAllowedInCats($data = array())
	{
		// Initialise variables.
		$user		= JFactory::getUser();
		
		$cats = isset($data['cid']) ? $data['cid'] : array();
		if ( !empty($data['catid']) && !in_array($data['catid'], $cats) ) {
			$cats[] =  $data['catid'];
		}
		
		$allow = null;
		if (count($cats)) {
			$allow = true;
			foreach ($cats as $curcatid) {
				// If the category has been passed in the data or URL check it.
				$cat_allowed = $user->authorise('core.create', 'com_content.category.'.$curcatid);
				if (!$cat_allowed) {
					return JError::raiseWarning( 500, "No access to add item to category with id ".$curcatid );
				}
				$allow &= $cat_allowed;
			}
		}
		
		if ($allow === null) {
			// no categories specified, revert to the component permissions.
			$allow	= $user->authorise('core.create', 'com_flexicontent');
		}
		
		return $allow;
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
		
		// Get an empty item model (with default values), and the current user too
		$item  	=& $this->getTable('flexicontent_items', '');
		$user	=& JFactory::getUser();
		
		// tags and cats will need some manipulation so we retieve them 
		$tags			= isset($data['tag']) ? $data['tag'] : array();
		$cats			= isset($data['cid']) ? $data['cid'] : array();
		
		// Set the item id to the now empty item model
		$id			= (int)$data['id'];
		$item->id = $id;
		
		// Make tags unique
		$tags = array_unique($tags);
		
		// Get version and current version, and some global params too
		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		
		// Item Rules ?
		//$item->setRules($data['rules']);
		
		// A zero id indicate new item
		$isnew = !$id;
		
		// vstate = 2 is approve version then save item to #__content table.
		$data['vstate']		= (int)$data['vstate'];
		
		// Reconstruct (main)text field if it has splitted up e.g. to seperate editors per tab
		if (is_array($data['text'])) {
			$data['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $data['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$tabs_text = '';
			foreach($data['text'] as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$data['text'] = & $tabs_text;
		}
		/*if (is_array($post['text'])) {
			$post['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $post['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$tabs_text = '';
			foreach($post['text'] as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$post['text'] = & $tabs_text;
		}*/
		//print_r($data['text']); exit();
		
		if( $isnew || ($data['vstate']==2) ) {
			// auto assign the main category if none selected
			if ( empty($data['catid']) && !empty($cats[0]) ) {
				$data['catid'] = $cats[0];
			}
			
			// Add the primary cat to the array if it's not already in
			if ( @$data['catid'] && !in_array($data['catid'], $cats) ) {
				$cats[] =  $data['catid'];
			}
			
			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError(JText::_('FLEXI_SELECT_CATEGORY'));
				return false;
			}
			
			// Set back the altered categories and tags to the form data
			$data['cid'] = $cats;
			$data['tags'] = $tags;
			
			if(!$this->applyCurrentVersion($item, $data)) return false;
			//echo "<pre>"; var_dump($data); exit();
		} else {
			if( $mainframe->isAdmin() ) {
				JError::raiseNotice(11, JText::_('FLEXI_SAVED_VERSION_WAS_NOT_APPROVED_NOTICE') );
			} else {
				JError::raiseNotice(12, JText::_('FLEXI_SAVED_VERSION_MUST_BE_APPROVED_NOTICE') );
			}
			
			// Not new and not approving version, load item data
			$item->load($id);
			$datenow =& JFactory::getDate();
			$item->modified 		= $datenow->toMySQL();
			$item->modified_by 		= $user->get('id');
			
			// Add the primary cat to the array if it's not already in
			if ( !empty($data['catid']) && !in_array($data['catid'], $cats) ) {
				$cats[] =  $data['catid'];
			}
			
			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError(JText::_('FLEXI_SELECT_CATEGORY'));
				return false;
			}
			
			// Set back the altered categories and tags to the form data
			$data['cid'] = $cats;
			$data['tags'] = $tags;
		}
		
		$dispatcher = & JDispatcher::getInstance();
		// NOTE: This event isn't used yet but may be useful in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( &$item, &$data ));

		if(!$this->saveFields($isnew, $item, $data)) return false;
		
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

			if ($v->modified != $this->_db->getNullDate() ) {
				$v->created 	= $v->modified;
				$v->created_by 	= $v->modified_by;
			}
			
			$v->comment		= isset($data['versioncomment'])?htmlspecialchars($data['versioncomment'], ENT_QUOTES):'';
			unset($v->modified);
			unset($v->modified_by);
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}
		
		// delete old versions
		$vcount	= FLEXIUtilities::getVersionsCount($item->id);
		$vmax	= $this->_cparams->get('nr_versions', 10);

		if ($vcount > ($vmax+1)) {
			$deleted_version = FLEXIUtilities::getFirstVersion($item->id, $vmax, $current_version);
			// on efface les versions en trop
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version <' . $deleted_version
					.' AND version!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version_id <' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		$this->checkin();
		return true;
	}
	
	function applyCurrentVersion(&$item, &$data)
	{
		$isnew = !$item->id;
		$nullDate	= $this->_db->getNullDate();
		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		$user	=& JFactory::getUser();
		$cats = $data['cid'];
		$tags = $data['tags'];

		// bind it to the table
		if (!$item->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		// sanitise id field
		$item->id = (int) $item->id;
		
		// if main catid not selected then set the first selected category as main category
		if(!$item->catid && (count($cats)>0)) {
			$item->catid = $cats[0];
		}
	
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
		if (trim($item->publish_down) == JText::_('Never') || trim( $item->publish_down ) == '') {
			$item->publish_down = $nullDate;
		} else {
			if (strlen(trim( $item->publish_down )) <= 10) {
				$item->publish_down .= ' 00:00:00';
			}
			$date =& JFactory::getDate($item->publish_down, $tzoffset);
			$item->publish_down = $date->toMySQL();
		}
		
		// Get a state and parameter variables from the request
		//$item->state	= JRequest::getVar( 'state', 0, '', 'int' );
		$oldstate	= JRequest::getVar( 'oldstate', 0, '', 'int' );
		//$params		= JRequest::getVar( 'params', null, 'post', 'array' );
		/*$params		= $data['attribs'];

		// Build parameter INI string
		if (is_array($params)) {
			$txt = array ();
			foreach ($params as $k => $v) {
				if (is_array($v)) {
					$v = implode('|', $v);
				}
				$txt[] = "$k=$v";
			}
			$item->attribs = implode("\n", $txt);
		}*/

		// Get metadata string
		//$metadata = JRequest::getVar( 'meta', null, 'post', 'array');
		$metadata = $data['metadata'];

		if (is_array($metadata)) {
			$txt = array();
			foreach ($metadata as $k => $v) {
				$txt[] = "$k=$v";
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
			$item->version = $isnew?1:(($data['vstate']==2)?($version+1):$current_version);
		}
		// Store it in the db
		if (!$item->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		$this->_id = $item->id;

		$this->_item	=& $item;

		if($data['vstate']==2) {
			//store tag relation
			$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();
			foreach($tags as $tag) {
				$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}

		//At least one category needs to be assigned
		if (!is_array( $cats ) || count( $cats ) < 1) {
			$this->setError(JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
		}

		// delete only relations which are not part of the categories array anymore to avoid loosing ordering
		$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = '.$item->id
			. ($cats ? ' AND catid NOT IN (' . "'".implode("','", $cats)."'" . ')' : '')
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
		return true;
	}
	
	function saveFields($isnew, &$item, &$data) {
		$mainframe = &JFactory::getApplication();
		$dispatcher = & JDispatcher::getInstance();

		$cats = $data['cid'];
		$tags = $data['tags'];
		///////////////////////////////
		// store extra fields values //
		///////////////////////////////

		// get the field object
		//$this->_id 	= $item->id;
		$fields		= $this->getExtrafields();

		if($data['vstate']==2) {// approve version, clear data table
			$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		if ($fields) {
			$files	= JRequest::get( 'files', JREQUEST_ALLOWRAW );
			$searchindex = '';
			
			// SET THE real name of categories. The name is categories not cid
			$data['categories'] = & $data['cid'];
			
			$data['custom'] = JRequest::getVar('custom', array(), 'post', 'array');
			
			foreach($fields as $field) {
				// Field's posted data have different location if not CORE (aka custom field)
				if ($field->iscore) {
					$postdata = & $data;
				} else {
					$postdata = & $data['custom'];
				}
				
				// process field mambots onBeforeSaveField
				//$results = $dispatcher->trigger('onBeforeSaveField', array( $field, &$postdata[$field->name], &$files[$field->name] ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onBeforeSaveField',array( $field, &$postdata[$field->name], &$files[$field->name] ));

				// add the new values to the database 
				if (is_array(@$data[$field->name])) {
					$postvalues = $data[$field->name];
					$i = 1;
					foreach ($postvalues as $postvalue) {
						$this->saveFieldItem($item->id, $field->id, $postvalue, $isnew, $field->iscore, ($data['vstate']==2), $i++);
					}
				} else if (isset($data[$field->name])) {
					//not versionning hits field => Fix this issue 18 http://code.google.com/p/flexicontent/issues/detail?id=18
					if ($field->id != 7) {
						$this->saveFieldItem($item->id, $field->id, $data[$field->name], $isnew, $field->iscore, ($data['vstate']==2));
					}
				} else if (is_array(@$data['custom'][$field->name])) {
					$postvalues = $data['custom'][$field->name];
					$i = 1;
					foreach ($postvalues as $postvalue) {
						$this->saveFieldItem($item->id, $field->id, $postvalue, $isnew, $field->iscore, ($data['vstate']==2), $i++);
					}
				} else if (isset($data['custom'][$field->name])) {
					$this->saveFieldItem($item->id, $field->id, $data['custom'][$field->name], $isnew, $field->iscore, ($data['vstate']==2));
				}
				// process field mambots onAfterSaveField
				//$results	 = $dispatcher->trigger('onAfterSaveField', array( $field, &$postdata[$field->name], &$files[$field->name] ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onAfterSaveField',array( $field, &$postdata[$field->name], &$files[$field->name] ));
				$searchindex 	.= @$field->search;
			}
			
			// store the extended data if the version is approved
			if( $isnew || ($data['vstate']==2) ) {
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
		return true;
	}
	
	function saveFieldItem($itemid, $fieldid, $value, $isnew, $iscore, $isapproveversion, $valueorder=1) {
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		$version = FLEXIUtilities::getLastVersions($itemid, true);
		$version = is_array($version)?0:$version;
		$obj = new stdClass();
		$obj->field_id 		= $fieldid;
		$obj->item_id 		= $itemid;
		$obj->valueorder	= $valueorder;
		$obj->version		= (int)$version+1;
		
		// THIS IS REDUDANT (WILL BE REMOVED), since FLEXIcontenty field must have had serialize the parameters of each value already
		if (is_array($value)) {
			$obj->value			= serialize($value);
		} else {
			$obj->value			= $value;
		}
		//$obj->value = $value;
		
		if($use_versioning) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
		if( ($isnew || $isapproveversion ) && !$iscore) {
			unset($obj->version);
			$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
		}
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
	function gettags($mask="")
	{
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}
	
	/**
	 * Method to fetch used tags IDs as an array when performing an edit action
	 * 
	 * @param int id
	 * @return array
	 * @since 1.0
	 */
	function getUsedtagsIds($item_id=0)
	{
			static $tags = array();
			if (count($tags)>0) return $tags;
			if(!$item_id) $tags = array();
			else {
				$query 	= 'SELECT tid FROM #__flexicontent_tags_item_relations'
					. " WHERE itemid ='$item_id'"
					;
				$this->_db->setQuery($query);
				$tags = $this->_db->loadResultArray();
			}
		return $tags;
	}

	/**
	 * Method to get the list of the used tags
	 * 
	 * @param 	array
	 * @return 	array
	 * @since 	1.5.2
	 */
	function getUsedtagsData($tagIds)
	{
		if (empty($tagIds)) {
			return array();
		}
		
		$query 	= 'SELECT *,t.id as tid FROM #__flexicontent_tags as t '
				. ' WHERE t.id IN (\'' . implode("','", $tagIds).'\')'
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
				. ' ORDER BY name ASC'
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
	function getTypesselected($force = false) {
		static $used;
		if (!$used || $force) {
			if ($this->_id) {
				$query = 'SELECT ie.type_id as id,t.name FROM #__flexicontent_items_ext as ie'
					. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
					. ' WHERE ie.item_id = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			} else {
				$typeid = (int)JRequest::getInt('typeid', 1);
				$query = 'SELECT t.id,t.name FROM #__flexicontent_types as t'
					. ' WHERE t.id = ' . (int)$typeid;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			}
		}
		return $used;
	}
	
	
	/**
	 * Method to retrieve the value of a core field for a specified item version
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getCoreFieldValue(&$field, $version = 0)
	{
		if(isset($this->_item)) {
			$item = & $this->_item;
		} else {
			$item = $this->getItem();
		}
		switch ($field->field_type) {
			case 'created': // created
			$field->value = array($item->created);
			break;
			
			case 'createdby': // created by
			$field->value = array($item->created_by);
			break;

			case 'modified': // modified
			$field->value = array($item->modified);
			break;
			
			case 'modifiedby': // modified by
			$field->value = array($item->modified_by);
			break;

			case 'title': // title
			$field->value = array($item->title);
			break;

			case 'hits': // hits
			$field->value = array($item->hits);
			break;

			case 'type': // document type
			$field->value = array($item->type_id);
			break;

			case 'version': // version
			$field->value = array($item->version);
			break;

			case 'state': // publication state
			$field->value = array($item->state);
			break;

			case 'voting': // voting button // remove dummy value in next version for legacy purposes
			$field->value = array('button'); // dummy value to force display
			break;

			case 'favourites': // favourites button // remove dummy value in next version for legacy purposes
			$field->value = array('button'); // dummy value to force display
			break;

			case 'score': // voting score // remove dummy value in next version for legacy purposes
			$field->value = array('button'); // dummy value to force display
			break;
			
			case 'categories': // assigned categories
			$db = &JFactory::getDBO();
			$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='{$item->id}';";
			$db->setQuery($query);
			$field->value = $db->loadResultArray();
			break;

			case 'tags': // assigned tags
			$db = &JFactory::getDBO();
			$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='{$item->id}';";
			$db->setQuery($query);
			$field->value = $db->loadResultArray();
			break;
			
			case 'maintext': // main text
			$value = (trim($item->fulltext) != '') ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
			$field->value = array($value);
			break;
		}
		return array();
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
		
		// The is different than plugin code from v1.5.x , plus it is problematic to unserialize here
		// !!! The FLEXIcontent field plugin itself will always know better how to handle the values !!!
		/*foreach($field_value as $k=>$value) {
			if($unserialized_value = @unserialize($value)) {
				return $unserialized_value;
			} else {
				break;
			}
		}*/
		
		return $field_value;
	}
	
	/**
	 * Method to get extrafields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields($force = false) {
		static $fields;
		if(!$fields || $force) {
			jimport('joomla.html.parameter');
			$typeid = JRequest::getVar('typeid', 0, '', 'int');
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
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
				// IMPORTANT $version should be ZERO when versioning disabled
				if (!$version && $field->iscore) {
					// load (non-versioned) core field from item data, (the $version variable is ignored),
					$this->getCoreFieldValue($field, $version);
				} else {
					// Load non core field (versioned or non-versioned) OR core field (versioned only)
					$field->value 		= $this->getExtrafieldvalue($field->id, $version);
				}
				//echo "Got ver($version) id {$field->id}: ". $field->name .": ";  print_r($field->value); 	echo "<br>";
				$field->parameters 	= new JParameter($field->attribs);
			}
		}
		return $fields;
	}

	/**
	 * Method to get the versionlist which belongs to the item
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getVersionList($limitstart=0, $versionsperpage=0)
	{
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, u.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
				.' WHERE item_id = ' . (int)$this->_id
				.' ORDER BY version_id ASC'
				. ($versionsperpage?' LIMIT '.$limitstart.','.$versionsperpage:'')
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
		if($item->_currentversion<0) {
			$query 	= 'SELECT version'
					.' FROM #__content'
					." WHERE id = '{$this->_id}'"
					;
			$this->_db->setQuery($query);
			$currentversion = $this->_db->loadResult();
			$item->_currentversion = ($currentversion===NULL)?-1:$currentversion;
		}
		return $item->_currentversion;
	}
}
?>
