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
class FlexicontentModelItem extends JModelAdmin {
	/**
	 * Item data
	 *
	 * @var object
	 */
	var $_id = 0;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {
		parent::__construct();
		$data = JRequest::get( 'post' );
		$pk = @$data['jform']['id'];
		if(!$pk) {
			$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
			JArrayHelper::toInteger($cid, array(0));
			$pk = $cid[0];
		}
		// Initialise variables.
		$this->setState($this->getName().'.id', $pk);
		$this->setId($pk);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id) {
		// Set item id and wipe data
		$this->_id	    = $id;
	}
	function getId() {
		return $this->_id;
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
		if ($id = (int) $this->getState('item.id')) {
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
	public function getItem($pk = null) {
		static $item;
		if(!$item) {
			// Initialise variables.
			$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');

			if ($item = parent::getItem($pk)) {
				// Convert the params field to an array.
				$registry = new JRegistry;
				$registry->loadJSON($item->attribs);
				$item->attribs = $registry->toArray();

				// Convert the params field to an array.
				$registry = new JRegistry;
				$registry->loadJSON($item->metadata);
				$item->metadata = $registry->toArray();

				$item->text = trim($item->fulltext) != '' ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
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
				$version = JRequest::getVar( 'version', 0, 'request', 'int' );
				$this->getExtrafieldvalue($fields['categories'], $version);
				$item->cid = $fields['categories']->value;
			}
			if($pk <= 0) {
				$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
				$item->state				= $cparams->get('new_item_state', -4);
				$item->language			= flexicontent_html::getSiteDefaultLang();
			}
			$used = $this->getTypesselected();
			$item->type_id = $used->id;
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
		$app = JFactory::getApplication('administrator');

		// Get the pk of the record from the request.
		$data = JRequest::get( 'post' );
		$pk = @$data['jform']['id'];
		if(!$pk) {
			$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
			JArrayHelper::toInteger($cid, array(0));
			$pk = $cid[0];
		}
		// Initialise variables.
		$this->setState($this->getName().'.id', $pk);
		$this->setId($pk);

		// Load the parameters.
		$value = JComponentHelper::getParams($this->option);
		$this->setState('params', $value);
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
		if ($item = $this->getItem()) {
			if ($uid) {
				return ($item->checked_out && $item->checked_out != $uid);
			} else {
				return $item->checked_out;
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
	function canAdd() {
		$user	=& JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		if(!$permission->CanAdd) return false;
		return true;
	}

	/**
	 * Method to check if the user can edit the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canEdit() {
		$user	=& JFactory::getUser();

		if (!JAccess::check($user->id, 'core.admin', 'root.1')) {
				$permission = FlexicontentHelperPerm::getPerm();
				$id = $this->getState($this->getName().'.id');
				if ($id) {
					$rights 	= $permission->checkAllItemAccess($uid, 'item', $id);
					$canEdit 	= in_array('flexicontent.editall', $rights) || $permission->CanEdit;
					$canEditOwn	= (in_array('flexicontent.editown', $rights) && ($item->created_by == $user->id));
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

		$mainframe = &JFactory::getApplication();
		$item  	=& $this->getTable('flexicontent_items', '');
		$user	=& JFactory::getUser();

		$tags			= isset($data['tag'])?$data['tag']:array();
		$cats			= isset($data['jform']['cid'])?$data['jform']['cid']:array();
		$id			= (int)$data['jform']['id'];
		$data['vstate']		= (int)$data['vstate'];
		$item->id = $id;
		$nullDate	= $this->_db->getNullDate();
		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$isnew = false;
		$tags = array_unique($tags);
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		$item->setRules($data['jform']['rules']);
		if( ($isnew = !$id) || ($data['vstate']==2) ) {//vstate = 2 is approve version then save item to #__content table.
			// Add the primary cat to the array if it's not already in
			if (!in_array($data['jform']['catid'], $cats)) {
				$cats[] = $item->catid;
			}
			// Store categories to item relations
			$data['jform']['categories'][0] = $cats;
			$data['jform']['tags'][0] = $tags;
			if(!$this->applyCurrentVersion($item, $data)) return false;
		} else {
			$item->load($id);
			$datenow =& JFactory::getDate();
			$item->modified 		= $datenow->toMySQL();
			$item->modified_by 		= $user->get('id');
			// Add the primary cat to the array if it's not already in
			if (!in_array($item->catid, $cats)) {
				$cats[] = $item->catid;
			}
			// Store categories to item relations
			$data['jform']['categories'][0] = $cats;
			$data['jform']['tags'][0] = $tags;

			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError('FLEXI_SELECT_CATEGORY');
				return false;
			}
		}
		
		$dispatcher = & JDispatcher::getInstance();
		// NOTE: This event isn't used yet but may be useful in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item ));

		if(!$this->saveFields($item, $data)) return false;
		
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

			if ($v->modified != $nullDate) {
				$v->created 	= $v->modified;
				$v->created_by 	= $v->modified_by;
			}
			
			$v->comment		= isset($data['jform']['versioncomment'])?htmlspecialchars($data['jform']['versioncomment'], ENT_QUOTES):'';
			unset($v->modified);
			unset($v->modified_by);
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}
		
		// delete old versions
		$vcount	= FLEXIUtilities::getVersionsCount($item->id);
		$vmax	= $cparams->get('nr_versions', 10);

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
		return true;
	}
	
	function applyCurrentVersion(&$item, &$data) {
		$isnew = !$item->id;
		$nullDate	= $this->_db->getNullDate();
		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		$user	=& JFactory::getUser();
		$cats = $data['jform']['categories'][0];
		$tags = $data['jform']['tags'][0];

		// bind it to the table
		if (!$item->bind($data['jform'])) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		// sanitise id field
		$item->id = (int) $item->id;
		
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

		// Append time if not added to publish date
		if (strlen(trim($item->publish_up)) <= 10) {
			$item->publish_up .= ' 00:00:00';
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
		$item->state	= JRequest::getVar( 'state', 0, '', 'int' );
		$oldstate	= JRequest::getVar( 'oldstate', 0, '', 'int' );
		//$params		= JRequest::getVar( 'params', null, 'post', 'array' );
		$params		= $data['jform']['attribs'];

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
		}

		// Get metadata string
		//$metadata = JRequest::getVar( 'meta', null, 'post', 'array');
		$metadata = $data['jform']['metadata'];

		if (is_array($metadata)) {
			$txt = array();
			foreach ($metadata as $k => $v) {
				$txt[] = "$k=$v";
			}
			$item->metadata = implode("\n", $txt);
		}
		
		// Clean text for xhtml transitional compliance
		$text = str_replace('<br>', '<br />', $data['jform']['text']);
		
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

		//$this->_item	=& $item;

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
			$this->setError('FLEXI_SELECT_CATEGORY');
			return false;
		}

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
		return true;
	}
	
	function saveFields(&$item, &$data) {
		$mainframe = &JFactory::getApplication();
		$dispatcher = & JDispatcher::getInstance();
		
		$isnew = !$item->id;
		$cats = $data['jform']['categories'][0];
		$tags = $data['jform']['tags'][0];
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
			//$files		= $data['jform']['files'];
			$searchindex = '';
			foreach($fields as $field) {
				// process field mambots onBeforeSaveField
				$results = $mainframe->triggerEvent('onBeforeSaveField', array( &$field, &$data['jform'][$field->name], &$files['jform'][$field->name] ));

				// add the new values to the database 
				if (is_array($data['jform'][$field->name])) {
					$postvalues = $data['jform'][$field->name];
					$i = 1;
					foreach ($postvalues as $postvalue) {
						$this->saveFieldItem($item->id, $field->id, $postvalue, $isnew, $field->iscore, ($data['vstate']==2), $i++);
					}
				} else if ($data['jform'][$field->name]) {
					//not versionning hits field => Fix this issue 18 http://code.google.com/p/flexicontent/issues/detail?id=18
					if ($field->id != 7) {
						$this->saveFieldItem($item->id, $field->id, $data['jform'][$field->name], $isnew, $field->iscore, ($data['vstate']==2));
					}
				}
				// process field mambots onAfterSaveField
				$results	 = $dispatcher->trigger('onAfterSaveField', array( $field, &$data['jform'][$field->name], &$files['jform'][$field->name] ));
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

				//dump($searchindex,'search');
				//dump($item,'item');
			}
		}
		return true;
	}
	
	function saveFieldItem($itemid, $fieldid, $value, $isnew, $iscore, $isapproveversion, $valueorder=1) {
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		$version = FLEXIUtilities::getLastVersions($itemid, true);
		$version = is_array($version)?0:$version;
		$obj = new stdClass();
		$obj->field_id 		= $fieldid;
		$obj->item_id 		= $itemid;
		$obj->valueorder	= $valueorder;
		$obj->version		= (int)$version+1;
		// @TODO : move to the plugin code
		if (is_array($value)) {
			$obj->value			= serialize($value);
		} else {
			$obj->value			= $value;
		}
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
	function restore($version, $id) {
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
	function gettags($mask="") {
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	/**
	 * Method to get the list of the used tags
	 * 
	 * @param 	array
	 * @return 	array
	 * @since 	1.5.2
	 */
	function getUsedtags($A)
	{
		if (empty($A)) {
			return array();
		}
		
		$query 	= 'SELECT *,t.id as tid FROM #__flexicontent_tags as t '
				. ' WHERE t.id IN (\'' . implode("','", $A).'\')'
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
	function getTypeslist () {
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
		if(!$used || $force) {
			if($this->_id) {
				$query = 'SELECT ie.type_id as id,t.name FROM #__flexicontent_items_ext as ie'
					. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
					. ' WHERE ie.item_id = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			}else{
				$typeid = (int)JRequest::getInt('typeid', 1);
				$query = 'SELECT t.id,t.name FROM #__flexicontent_types as t'
					. ' WHERE t.id = ' . (int)$typeid;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			}
		}
		return $used;
	}
	function getCoreFieldValue(&$field) {
		$item = $this->getItem();
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

			case 'title': // hits
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

			case 'state': // state
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
	function getExtrafieldvalue(&$field, $version = 0) {
		if(isset($field->value)) return;
		if(!$version && $field->iscore) {//load current version of core field
			$this->getCoreFieldValue($field);
		}else{
			$query = 'SELECT value'
				.(($version<=0)?' FROM #__flexicontent_fields_item_relations AS fv':' FROM #__flexicontent_items_versions AS fv')
				.' WHERE fv.item_id = ' . (int)$this->_id
				.' AND fv.field_id = ' . (int)$field->id
				.(($version>0)?' AND fv.version='.((int)$version):'')
				.' ORDER BY valueorder'
				;
			$this->_db->setQuery($query);
			$field_value = $this->_db->loadResultArray();
			foreach($field_value as $k=>$value) {
				if($field->value = @unserialize($value))
					return;
					
			}
			$field->value = $field_value;
		}
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
				$this->getExtrafieldvalue($field, $version);
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
	function getVersionList($limitstart=0, $versionsperpage=0) {
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
		$query 	= 'SELECT version'
				.' FROM #__content'
				.' WHERE id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}
}
?>
