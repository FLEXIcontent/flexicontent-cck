<?php
/**
 * @version 1.5 stable $Id: category.php 1372 2012-07-08 19:08:21Z ggppdk $
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
class FlexicontentModelCategory extends JModelAdmin
{
	/**
	 * Category data
	 *
	 * @var object
	 */
	var $_category = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
		if ( !@$array[0] ) {
			// Try id variable too (needed by J3.0+)
			$array = JRequest::getVar('id',  0, '', 'array');
		}
		// Make sure id variable is set (needed by J3.0+ controller)
		JRequest::setVar('id', (int)$array[0]);
		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int category identifier
	 */
	function setId($id)
	{
		// Set category id and wipe data
		$this->_id	    	= $id;
		$this->_category	= null;
	}
	
	/**
	 * Overridden get method to get properties from the category
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadCategory()) {
			if(isset($this->_category->$property)) {
				return $this->_category->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get category data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getCategory()
	{
		if ($this->_loadCategory())
		{

		}
		else  $this->_initCategory();

		return $this->_category;
	}

	/**
	 * Method to load category data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadCategory()
	{
		// Lets load the category if it doesn't already exist
		if (empty($this->_category))
		{
			$query = 'SELECT *'
					. ' FROM #__categories'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_category = $this->_db->loadObject();

			return (boolean) $this->_category;
		}
		return true;
	}

	/**
	 * Method to initialise the category data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initCategory()
	{
		// Lets load the category if it doesn't already exist
		if (empty($this->_category))
		{
			$category = new stdClass();
			$category->id					= 0;
			$category->parent_id			= 0;
			$category->title				= null;
			$category->name					= null;
			$category->alias				= null;
			//$category->image				= JText::_( 'FLEXI_CHOOSE_IMAGE' );
			$category->extension				= FLEXI_CAT_EXTENSION;
			$category->image_position		= 'left';
			$category->description			= null;
			$category->published			= 1;
			$category->editor				= null;
			$category->ordering				= 0;
			$category->access				= 0;
			$category->params				= null;
			$category->count				= 0;
			$this->_category				= $category;
			return (boolean) $this->_category;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin($pk = NULL)
	{
		if (!$pk) $pk = $this->_id;
		if ($pk) {
			$category = $this->getTable();
			return $category->checkin($pk);
		}
		return false;
	}
	
	
	/**
	 * Method to checkout/lock the category
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($pk = null)   // UPDATED to match function signature of J1.6+ models
	{
		// Make sure we have a record id to checkout the record with
		if ( !$pk ) $pk = $this->_id;
		if ( !$pk ) return true;
		
		// Get current user
		$user	= JFactory::getUser();
		$uid	= $user->get('id');
		
		// Lets get table record and checkout the it
		$tbl = $this->getTable();
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( FLEXI_J16GE ? $tbl->getError() : JText::_("FLEXI_ALERT_CHECKOUT_FAILED") );
		return false;
	}
	
	
	/**
	 * Tests if the category is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_id < 1) {
			return false;
		} else if ($this->_loadCategory()) {
			if ($uid) {
				return ($this->_category->checked_out && $this->_category->checked_out != $uid);
			} else {
				return $this->_category->checked_out;
			}
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function save($data)
	{
		// Initialise variables;
		$dispatcher = JDispatcher::getInstance();
		$table = $this->getTable();
		$pk = (!empty($data['id'])) ? $data['id'] : (int) $this->getState($this->getName() . '.id');
		$isNew = true;

		// Include the content plugins for the on save events.
		JPluginHelper::importPlugin('content');

		// Load the row if saving an existing category.
		if ($pk > 0)
		{
			$table->load($pk);
			$isNew = false;
		}
		
		// Retrieve form data these are subject to basic filtering
		$jform = JRequest::getVar('jform', array(), 'post', 'array');
		
		// Merge template fieldset this should include at least 'clayout' and optionally 'clayout_mobile' parameters
		if( !empty($jform['templates']) )
			$data['params'] = array_merge($data['params'], $jform['templates']);
		
		// Merge the parameters of the select clayout
		$clayout = $jform['templates']['clayout'];
		if( !empty($jform['layouts'][$clayout]) ) {
			$data['params'] = array_merge($data['params'], $jform['layouts'][$clayout]);
		}
		
		// Merge other special parameters, e.g. 'inheritcid'
		if( !empty($jform['special']) )
			$data['params'] = array_merge($data['params'], $jform['special']);
		
		// Set the new parent id if parent id not matched OR while New/Save as Copy .
		if ($table->parent_id != $data['parent_id'] || $data['id'] == 0)
		{
			$table->setLocation($data['parent_id'], 'last-child');
		}

		// Alter the title for save as copy
		if (JRequest::getVar('task') == 'save2copy')
		{
			list($title, $alias) = $this->generateNewTitle($data['parent_id'], $data['alias'], $data['title']);
			$data['title'] = $title;
			$data['alias'] = $alias;
		}

		//$params			= JRequest::getVar( 'params', null, 'post', 'array' );
		//$params			= $data["params"];
		$copyparams = $jform['copycid'];
		if ($copyparams) unset($data['params']);
		
		// Bind the data.
		if (!$table->bind($data))
		{
			$this->setError($table->getError());
			return false;
		}
		
		if ($copyparams) {
			$table->params = $this->getParams($copyparams);
		}
		
		// Bind the rules.
		/*if (isset($data['rules'])) {
			foreach($data['rules'] as $action_name => $identities) {
				foreach($identities as $grpid => $val) {
					if ($val==="") {
						unset($data['rules'][$action_name][$grpid]);
					}
				}
			}
			jimport('joomla.access.accessrules');
			$rules = new JRules($data['rules']);
			$table->setRules($rules);
		}
		echo "<pre>";
		print_r($data['rules']);
		print_r($rules);
		echo "</pre>";*/
		//die('die() in model');
		

		// Check the data.
		if (!$table->check())
		{
			$this->setError($table->getError());
			return false;
		}

		// Trigger the onContentBeforeSave event.
		$result = $dispatcher->trigger($this->event_before_save, array($this->option . '.' . $this->name, &$table, $isNew));
		if (in_array(false, $result, true))
		{
			$this->setError($table->getError());
			return false;
		}

		// Store the data.
		if (!$table->store())
		{
			$this->setError($table->getError());
			return false;
		}
		
		$this->_id = $table->id;
		$this->_category = & $table;  // variable reference instead of assigning object id, this will work even if $table is reassigned a different object

		// Trigger the onContentAfterSave event.
		$dispatcher->trigger($this->event_after_save, array($this->option . '.' . $this->name, &$table, $isNew));

		// Rebuild the path for the category:
		if (!$table->rebuildPath($table->id))
		{
			$this->setError($table->getError());
			return false;
		}

		// Rebuild the paths of the category's children:
		if (!$table->rebuild($table->id, $table->lft, $table->level, $table->path))
		{
			$this->setError($table->getError());
			return false;
		}

		$this->setState($this->getName() . '.id', $table->id);

		if ($table->id)
		{
			$query 	= 'UPDATE #__categories'
				. ' SET extension = "com_content" '
				. ' WHERE id = ' . (int)$table->id;
				;
		}

		// Clear the cache
		$this->cleanCache();
		
		return true;
	}
	
	
	/**
	 * Custom clean the cache of com_content and content modules
	 *
	 * @since	1.6
	 */
	protected function cleanCache($group = NULL, $client_id = 0)
	{
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
	}
	
	/**
	 * Method to get the parameters of another category
	 *
	 * @access	public
	 * @params	int			id of the category
	 * @return	string		ini string of params
	 * @since	1.5
	 */
	function getParams($id)
	{
		$query 	= 'SELECT params FROM #__categories'
				. ' WHERE id = ' . (int)$id
				;
		$this->_db->setQuery($query);
		$copyparams = $this->_db->loadResult();
		
		return $copyparams;
	}
	
	/**
	 * Method to copy category parameters
	 *
	 * @param 	int 	$id of target
	 * @param 	string 	$params to copy
	 * @return 	boolean	true on success
	 * 
	 * @since 1.5
	 */
	function copyParams($id, $params)
	{
		$query 	= 'UPDATE #__categories'
				. ' SET params = ' . $this->_db->Quote($params)
				. ' WHERE id = ' . (int)$id
				;
		$this->_db->setQuery( $query );
		if (!$this->_db->query()) {
			return false;
		}
		return true;
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
	public function getTable($type = 'flexicontent_categories', $prefix = '', $config = array()) {
		return JTable::getInstance($type, $prefix, $config);
	}
	
	
	/**
	 * Method to get the row form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 * @since	1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$extension	= $this->getState('com_flexicontent.category.extension');

		// Get the form.
		$form = $this->loadForm('com_flexicontent.category'.$extension, 'category', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		// Modify the form based on Edit State access controls.
		if (empty($data['extension'])) {
			$data['extension'] = $extension;
		}

		if (!$this->canEditState((object) $data)) {
			// Disable fields for display.
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('published', 'disabled', 'true');

			// Disable fields while saving.
			// The controller has already verified this is a record you can edit.
			$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('published', 'filter', 'unset');
		}

		return $form;
	}
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}
	
	/**
	 * Method to get a category.
	 *
	 * @param	integer	An optional id of the object to get, otherwise the id from the model state is used.
	 * @return	mixed	Category data object on success, false on failure.
	 * @since	1.6
	 */
	public function getItem($pk = null)
	{
		$pk = $pk ? $pk : $this->_id;
		
		if ( $result = parent::getItem($pk) )
		{
			// Prime required properties.
			if (empty($result->id)) {
				$result->parent_id	= $this->getState('com_flexicontent.category.parent_id');
				$result->extension	= $this->getState('com_flexicontent.category.extension');
			}

			// Convert the metadata field to an array.
			$registry = new JRegistry($result->metadata);
			$result->metadata = $registry->toArray();

			// Convert the created and modified dates to local user time for display in the form.
			jimport('joomla.utilities.date');
			
			$site_zone = JFactory::getApplication()->getCfg('offset');
			$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
			$tz_string = FLEXI_J16GE ? $user_zone : $site_zone ;
			$tz = new DateTimeZone( $tz_string );
			
			if (intval($result->created_time)) {
				$date = new JDate($result->created_time);
				$date->setTimezone($tz);
				$result->created_time = FLEXI_J16GE ? $date->toSql(true) : $date->toMySQL(true);
			} else {
				$result->created_time = null;
			}

			if (intval($result->modified_time)) {
				$date = new JDate($result->modified_time);
				$date->setTimezone($tz);
				$result->modified_time = FLEXI_J16GE ? $date->toSql(true) : $date->toMySQL(true);
			} else {
				$result->modified_time = null;
			}
			$this->_category = $result;
		}

		return $result;
	}
	
	
	/**
	 * Auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function populateState() {
		$app = JFactory::getApplication('administrator');

		if (!($parentId = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.parent_id'))) {
			$parentId = JRequest::getInt('parent_id');
		}
		$this->setState('com_flexicontent.category.parent_id', $parentId);

		if (!($extension = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.extension'))) {
			$extension = JRequest::getCmd('extension', FLEXI_CAT_EXTENSION);
		}
		// Load the User state.
		if (!($pk = (int) $app->getUserState('com_flexicontent.edit.'.$this->getName().'.id'))) {
			$cid = JRequest::getVar('cid', array(0));
			$pk = (int)$cid[0];
		}

		$this->setState('com_flexicontent.category.extension', $extension);
		$parts = explode('.',$extension);
		// extract the component name
		$this->setState('com_flexicontent.category.component', $parts[0]);
		// extract the optional section name
		$this->setState('com_flexicontent.category.section', (count($parts)>1)?$parts[1]:null);

		// Load the parameters.
		$params	= JComponentHelper::getParams('com_flexicontent');
		$this->setState('params', $params);
	}
	
	
	public function getAttribs()
	{
		if($this->_category) {
			return $this->_category->params;
		}
		return array();
	}
	
	
	/**
	 * Method to change the title & alias.
	 *
	 * @param   integer  $parent_id  The id of the parent.
	 * @param   string   $alias      The alias.
	 * @param   string   $title      The title.
	 *
	 * @return  array  Contains the modified title and alias.
	 *
	 * @since	1.7
	 */
	protected function generateNewTitle($parent_id, $alias, $title)
	{
		// Alter the title & alias
		$table = $this->getTable();
		while ($table->load(array('alias' => $alias, 'parent_id' => $parent_id)))
		{
			$title = JString::increment($title);
			$alias = JString::increment($alias, 'dash');
		}

		return array($title, $alias);
	}
	
}
?>
