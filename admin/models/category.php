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

jimport('legacy.model.admin');
use Joomla\String\StringHelper;
require_once('base.php');

/**
 * FLEXIcontent Component Category Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelCategory extends FCModelAdmin
{
	/**
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = 'category';

	/**
	 * Record database table 
	 *
	 * @var string
	 */
	var $records_dbtbl = 'com_categories';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_categories';

	/**
	 * Record primary key
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Record data
	 *
	 * @var object
	 */
	var $_record = null;

	/**
	 * Flag to indicate adding new records with next available ordering (at the end),
	 * this is ignored if this record DB model does not have 'ordering'
	 *
	 * @var boolean
	 */
	var $useLastOrdering = false;

	/**
	 * Plugin group used to trigger events
	 *
	 * @var boolean
	 */
	var $plugins_group = 'content';

	/**
	 * Records real extension
	 *
	 * @var string
	 */
	var $extension_proxy = 'com_content';

	/**
	 * Records real extension
	 *
	 * @var string
	 */
	var $associations_context = 'com_categories.item';

	/**
	 * Various record specific properties
	 *
	 */
	var $_inherited_params = null;  // @var object, Inherited parameters

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}


	/**
	 * Legacy method to get the record
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function &getCategory($pk = null)
	{
		return parent::getRecord($pk);
	}


	/**
	 * Method to initialise the record data
	 *
	 * @access	protected
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		parent::_initRecord($record, $initOnly);

		// Set some new record specific properties, note most properties already have proper values
		// Either the DB default values (set by getTable() method) or the values set by _afterLoad() method
		$record->id							= 0;
		$record->parent_id			= 0;
		$record->title					= null;
		$record->name						= null;  //$this->record_name . ($this->_getLastId() + 1);
		$record->alias					= null;
		$record->description		= null;
		$record->extension			= FLEXI_CAT_EXTENSION;
		$record->image_position	= 'left';
		$record->published			= 1;
		$record->params					= null;
		$record->editor					= null;
		$record->ordering				= 0;
		$record->access					= 1;
		$record->count					= 0;
		$record->checked_out		= 0;
		$record->checked_out_time	= '';

		$this->_record = $record;

		return true;
	}


	/**
	 * Custom clean the cache
	 *
	 * @since	1.6
	 */
	protected function cleanCache($group = NULL, $client_id = -1)
	{
		// -1 means both, but we will do both always
		parent::cleanCache('com_flexicontent');
		parent::cleanCache('com_content');
		parent::cleanCache('mod_articles_archive');
		parent::cleanCache('mod_articles_categories');
		parent::cleanCache('mod_articles_category');
		parent::cleanCache('mod_articles_latest');
		parent::cleanCache('mod_articles_news');
		parent::cleanCache('mod_articles_popular');

		JFactory::getSession()->set('clear_cats_cache', 1, 'flexicontent');  //parent::cleanCache('com_flexicontent_cats');
	}


	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable  A JTable object
	 *
	 * @since   3.2.0
	 */
	public function getTable($type = 'Category', $prefix = 'CategoriesTable', $config = array())
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_categories'.DS.'tables');
		return JTable::getInstance($type, $prefix, $config);
	}


	/**
	 * Method rebuild the entire nested set tree.
	 *
	 * @return  boolean  False on failure or error, true otherwise.
	 *
	 * @since   3.2.0
	 */
	public function rebuild()
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->rebuild())
		{
			$this->setError($table->getError());

			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}


	/**
	 * Method to load inherited parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getInheritedParams($force=false)
	{
		if ( $this->_inherited_params !== NULL && !$force ) return $this->_inherited_params;
		$id = (int)$this->_id;
		
		$app = JFactory::getApplication();
		
		// a. Clone component parameters ... we will use these as parameters base for merging
		$compParams = clone(JComponentHelper::getComponent('com_flexicontent')->params);     // Get the COMPONENT only parameters
		
		// b. Retrieve category parameters and create parameter object
		if ($id) {
			$query = 'SELECT params FROM #__categories WHERE id = ' . $id;
			$this->_db->setQuery($query);
			$catParams = $this->_db->loadResult();
			$catParams = new JRegistry($catParams);
		} else {
			$catParams = new JRegistry();
		}
		
		
		// c. Retrieve inherited parameter and create parameter objects
		global $globalcats;
		$heritage_stack = array();
		$inheritcid = $catParams->get('inheritcid', '');
		$inheritcid_comp = $compParams->get('inheritcid', -1);
		$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
		
		// CASE A: inheriting from parent category tree
		if ( $id && $inherit_parent && !empty($globalcats[$id]->ancestorsonly) ) {
			$order_clause = 'level';  // 'FIELD(id, ' . $globalcats[$id]->ancestorsonly . ')';
			$query = 'SELECT title, id, params FROM #__categories'
				.' WHERE id IN ( ' . $globalcats[$id]->ancestorsonly . ')'
				.' ORDER BY '.$order_clause.' DESC';
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObjectList('id');
			if (!empty($catdata)) {
				foreach ($catdata as $parentcat) {
					$parentcat->params = new JRegistry($parentcat->params);
					array_push($heritage_stack, $parentcat);
					$inheritcid = $parentcat->params->get('inheritcid', '');
					$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
					if ( !$inherit_parent ) break; // Stop inheriting from further parent categories
				}
			}
		}
		
		// CASE B: inheriting from specific category
		else if ( $id && $inheritcid > 0 && !empty($globalcats[$inheritcid]) ){
			$query = 'SELECT title, params FROM #__categories WHERE id = '. $inheritcid;
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObject();
			if ($catdata) {
				$catdata->params = new JRegistry($catdata->params);
				array_push($heritage_stack, $catdata);
			}
		}
		
		
		// *******************************************************************************************************
		// Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/ancestors-cats/category
		// *******************************************************************************************************
		
		// -1. layout parameters will be placed on top at end of this code ...
		
		// 0. Start from component parameters
		$params = new JRegistry();
		$params->merge($compParams);
		
		// 1. Merge category's inherited parameters (e.g. ancestor categories or specific category)
		while (!empty($heritage_stack)) {
			$catdata = array_pop($heritage_stack);
			if ($catdata->params->get('orderbycustomfieldid')==="0") $catdata->params->set('orderbycustomfieldid', '');
			$params->merge($catdata->params);
		}
		
		// 2. Merge category parameters -- CURRENT CATEGORY PARAMETERS MUST BE SKIPED ! we only want the inherited parameters
		//if ($catParams->get('orderbycustomfieldid')==="0") $catParams->set('orderbycustomfieldid', '');
		//$params->merge($catParams);
		
		// Retrieve Layout's parameters
		$layoutParams = flexicontent_tmpl::getLayoutparams('category', $params->get('clayout'), '', $force);
		$layoutParams = new JRegistry($layoutParams);
		
		// Allow global layout parameters to be inherited properly, placing on TOP of all others
		$this->_inherited_params = clone($layoutParams);
		$this->_inherited_params->merge($params);
		
		return $this->_inherited_params;
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

		return $this->_db->loadResult();
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
		$this->_db->setQuery($query);
		$this->_db->execute();

		return true;
	}


	/**
	 * Method to get the row form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		$this->setState($this->getName().'.extension', 'com_content');
		$extension = $this->getState($this->getName().'.extension');

		$jinput = JFactory::getApplication()->input;

		// A workaround to get the extension and other data into the model for save requests.
		if (empty($extension) && isset($data['extension']))
		{
			$extension = $data['extension'];
			$parts = explode('.', $extension);

			$this->setState($this->getName().'.extension', $extension);
			$this->setState($this->getName().'.component', $parts[0]);
			$this->setState($this->getName().'.section', @$parts[1]);
		}
		$this->setState($this->getName().'.language', isset($data['language']) ? $data['language'] : null);

		// Get the form.
		$form = $this->loadForm($this->option.'.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}
		$form->option = $this->option;
		$form->context = $this->getName();

		// Modify the form based on Edit State access controls.
		if (empty($data['extension']))
		{
			$data['extension'] = $extension;
		}

		// Force asset from request
		$categoryId = $jinput->get('id');
		$assetKey   = $categoryId ? $this->extension_proxy . '.category.' . $categoryId : $this->extension_proxy;

		if (!JFactory::getUser()->authorise('core.edit.state', $assetKey))
		{
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
	 * Method to get a record.
	 *
	 * @param	integer  $pk An optional id of the object to get, otherwise the id from the model state is used.
	 *
	 * @return	mixed 	Record data object on success, false on failure.
	 *
	 * @since	1.6
	 */
	public function getItem($pk = null)
	{
		$pk = $pk ? (int) $pk : $this->_id;
		$pk = $pk ? $pk : (int) $this->getState($this->getName().'.id');
		
		static $items = array();
		if ( $pk && isset($items[$pk]) ) return $items[$pk];
		
		// Instatiate the JTable
		$item = parent::getItem($pk);

		if ( $item )
		{
			// Prime required properties.
			if (empty($item->id))
			{
				$item->parent_id	= $this->getState($this->getName().'.parent_id');
				$item->extension	= $this->getState($this->getName().'.extension');
			}

			// Convert the metadata field to an array.
			$registry = new JRegistry($item->metadata);
			$item->metadata = $registry->toArray();

			// Convert the created and modified dates to local user time for display in the form.
			jimport('joomla.utilities.date');
			
			$site_zone = JFactory::getApplication()->getCfg('offset');
			$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
			$tz_string = $user_zone;
			$tz = new DateTimeZone( $tz_string );
			
			if (intval($item->created_time))
			{
				$date = new JDate($item->created_time);
				$date->setTimezone($tz);
				$item->created_time = $date->toSql(true);
			}
			else
			{
				$item->created_time = null;
			}

			if (intval($item->modified_time))
			{
				$date = new JDate($item->modified_time);
				$date->setTimezone($tz);
				$item->modified_time = $date->toSql(true);
			}
			else
			{
				$item->modified_time = null;
			}

			$this->_record = $item;

			$useAssocs = $this->useAssociations();
			if ($useAssocs)
			{
				if ($item->id != null)
				{
					$item->associations = CategoriesHelper::getAssociations($item->id, $item->extension);
					JArrayHelper::toInteger($item->associations);
				}
				else
				{
					$item->associations = array();
				}
			}
		}

		if ($pk) $items[$pk] = $item;
		return $item;
	}


	/**
	 * Method to preprocess the form.
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 * @param   string  $plugins_group  The name of the plugin group to import and trigger
	 *
	 * @return  void
	 *
	 * @see     JFormField
	 * @since   1.6
	 * @throws  Exception if there is an error in the form event.
	 */
	protected function preprocessForm(JForm $form, $data, $plugins_group = null)
	{
		jimport('joomla.filesystem.path');

		$lang = JFactory::getLanguage();
		$component = $this->getState($this->getName().'.component');
		$section = $this->getState($this->getName().'.section');
		$extension = JFactory::getApplication()->input->get('extension', null);

		// Get the component form if it exists
		$name = 'category' . ($section ? ('.' . $section) : '');

		// Try to find the component helper.
		$eName = str_replace('com_', '', $component);
		$path = JPath::clean(JPATH_ADMINISTRATOR . "/components/$component/helpers/category.php");

		if (file_exists($path))
		{
			$cName = ucfirst($eName) . ucfirst($section) . 'HelperCategory';

			JLoader::register($cName, $path);

			if (class_exists($cName) && is_callable(array($cName, 'onPrepareForm')))
			{
				$lang->load($component, JPATH_BASE, null, false, false)
					|| $lang->load($component, JPATH_BASE . '/components/' . $component, null, false, false)
					|| $lang->load($component, JPATH_BASE, $lang->getDefault(), false, false)
					|| $lang->load($component, JPATH_BASE . '/components/' . $component, $lang->getDefault(), false, false);
				call_user_func_array(array($cName, 'onPrepareForm'), array(&$form));

				// Check for an error.
				if ($form instanceof Exception)
				{
					$this->setError($form->getMessage());

					return false;
				}
			}
		}

		// Set the access control rules field component value.
		$form->setFieldAttribute('rules', 'component', $component);
		$form->setFieldAttribute('rules', 'section', $name);

		// Association category items
		if ($this->useAssociations())
		{
			$languages = JLanguageHelper::getContentLanguages(false, true, null, 'ordering', 'asc');
			$data_language = !empty($data->language) ? $data->language : $this->getState($this->getName().'.language');

			if (count($languages) > 1)
			{
				$addform = new SimpleXMLElement('<form />');
				$fields = $addform->addChild('fields');
				$fields->addAttribute('name', 'associations');
				$fieldset = $fields->addChild('fieldset');
				$fieldset->addAttribute('name', 'item_associations');
				$fieldset->addAttribute('description', 'COM_CATEGORIES_ITEM_ASSOCIATIONS_FIELDSET_DESC');

				foreach ($languages as $language)
				{
					if ($language->lang_code == $data_language) continue;
					$field = $fieldset->addChild('field');
					$field->addAttribute('name', $language->lang_code);
					$field->addAttribute('type', 'qfcategory');
					$field->addAttribute('language', $language->lang_code);
					$field->addAttribute('label', $language->title);
					$field->addAttribute('class', 'label');
					$field->addAttribute('translate_label', 'false');
					$field->addAttribute('extension', $extension);
					$field->addAttribute('edit', 'true');
					$field->addAttribute('clear', 'true');
					$field->addAttribute('filter', 'INT');  // also enforced later, but better to have it here too
				}

				$form->load($addform, false);
			}
		}

		// Trigger the default form events.
		$plugins_group = $plugins_group ?: $this->plugins_group;
		parent::preprocessForm($form, $data, $plugins_group);
	}


	/**
	 * Method to get parameters of parent categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getParentParams($cid)
	{
		if (empty($cid)) return array();
		
		global $globalcats;
		$db = JFactory::getDbo();
		
		// Select the required fields from the table.
		$query = ' SELECT id, params '
			. ' FROM #__categories '
			. ' WHERE id IN (' . $globalcats[$cid]->ancestors . ') '
			. ' ORDER BY level ASC '
			;
		$db->setQuery( $query );
		$data = $db->loadObjectList('id');
		return $data;
	}


	/**
	 * Method to change the state of a record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function setitemstate($id, $state = 1, $cleanCache = true)
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$jinput     = JFactory::getApplication()->input;
		$dispatcher = JEventDispatcher::getInstance();

		$option = $jinput->get('option', '', 'cmd');
		$view = $jinput->get('view', '', 'cmd');
		$format = $jinput->get('format', 'html', 'cmd');

		$jinput->set('isflexicontent', 'yes');
		static $event_failed_notice_added = false;

		if ( !$id )
		{
			return false;
		}

		// Add all children to the list
		$cid = array($id);
		if ($state!=1) $this->_addCategories($id, $cid);

		// Add all parents to the list
		if ($state==1) $this->_addCategories($id, $cid, 'parents');

		$cids = implode( ',', $cid );

		// Get the owner of all categories
		$query = 'SELECT id, created_user_id'
			. ' FROM #__categories as c'
			. ' WHERE c.extension=\'com_content\' '
			. ' AND id IN (' . $cids . ')'
			;
		$this->_db->setQuery( $query );
		$cats = $this->_db->loadObjectList('id');

		// Check access to change state of categories
		foreach ($cid as $catid)
		{
			$hasEditState			= $user->authorise('core.edit.state', 'com_content.category.'.$catid);
			$hasEditStateOwn	= $user->authorise('core.edit.state.own', 'com_content.category.'.$catid) && $cats[$catid]->created_user_id==$user->get('id');
			if (!$hasEditState && !$hasEditStateOwn)
			{
				$msg = 'You are not authorised to change state of category with id: '. $catid
					.'<br />NOTE: when publishing a category the parent categories will get published'
					.'<br />NOTE: when unpublishing a category the children categories will get unpublished';

				$this->setError($msg);
				return false;
			}
		}

		$query = 'UPDATE #__categories'
			. ' SET published = ' . (int) $state
			. ' WHERE id IN (' . $cids . ')'
		;
		$this->_db->setQuery( $query );
		$this->_db->execute();
		
		
		// ****************************************************************
		// Trigger Event 'onContentChangeState' of Joomla's Content plugins
		// ****************************************************************
		// Make sure we import flexicontent AND content plugins since we will be triggering their events
		JPluginHelper::importPlugin('content');
		
		$item = new stdClass();
		
		// Compatibility steps, so that 3rd party plugins using the change state event work properly
		$jinput->set('view', 'categories');
		$jinput->set('option', 'com_content');

		$result = $dispatcher->trigger($this->event_change_state, array('com_content.category', (array) $id, $state));
		
		// Revert compatibilty steps ... besides the plugins using the change state event, should have updated DB state value anyway
		$jinput->set('view', $view);
		$jinput->set('option', $option);
		
		if (in_array(false, $result, true) && !$event_failed_notice_added)
		{
			$app->enqueueMessage('At least 1 plugin event handler for onContentChangeState failed', 'warning');
			$event_failed_notice_added = true;
			return false;
		}

		if ($cleanCache)
		{
			$this->cleanCache(null, -1);
		}
		return true;
	}


	/**
	 * Method to add children/parents to a specific category
	 *
	 * @param int $id
	 * @param array $list
	 * @param string $type
	 * @return oject
	 * 
	 * @since 1.0
	 */
	function _addCategories($id, &$list, $type = 'children')
	{
		// Initialize variables
		$return = true;

		$get = $type == 'children' ? 'id' : 'parent_id';
		$source = $type == 'children' ? 'parent_id' : 'id';

		// Get all rows with parent of $id
		$query = 'SELECT ' . $get
			. ' FROM #__categories as c'
			. ' WHERE c.extension=\'com_content\' '
			. ' AND ' . $source . ' = ' . (int) $id . ' AND ' . $get . ' <> 1';
		$this->_db->setQuery( $query );
		$rows = $this->_db->loadObjectList();

		// Recursively iterate through all children
		foreach ($rows as $row)
		{
			$found = false;
			foreach ($list as $idx)
			{
				if ($idx == $row->$get)
				{
					$found = true;
					break;
				}
			}
			if (!$found)
			{
				$list[] = $row->$get;
			}
			$return = $this->_addCategories($row->$get, $list, $type);
		}

		return $return;
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	protected function _prepareBind($record, & $data)
	{
		// Merge template fieldset this should include at least 'clayout' and optionally 'clayout_mobile' parameters
		if( !empty($data['templates']) )
		{
			$data['params'] = array_merge($data['params'], $data['templates']);
			unset($data['templates']);
		}

		// Merge other special parameters, e.g. 'inheritcid'
		if( !empty($data['special']) )
		{
			$data['params'] = array_merge($data['params'], $data['special']);
			unset($data['special']);
		}

		// Get RAW layout field values, validation will follow ...
		$raw_data = JFactory::getApplication()->input->post->get('jform', array(), 'array');
		$data['params']['layouts'] = !empty($raw_data['layouts']) ? $raw_data['layouts'] : null;


		// ***
		// *** Special handling of some FIELDSETs: e.g. 'attribs/params' and optionally for other fieldsets too, like: 'metadata'
		// *** By doing partial merging of these arrays we support having only a sub-set of them inside the form
		// *** we will use mergeAttributes() instead of bind(), thus fields that are not set will maintain their current DB values,
		// ***
		$mergeProperties = array('params');
		$mergeOptions = array('params_fset' => 'params', 'layout_type' => 'category', 'model_names' => array($this->option => $this->record_name, 'com_categories' => 'category'));
		$this->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
		}


		parent::_prepareBind($record, $data);

		// Optionally copy parameters from another category
		$copycid = (int) $data['copycid'];
		if ($copycid)
		{
			unset($data['params']);
			$record->params = $this->getParams($copycid);
		}
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	protected function _afterStore($record, & $data)
	{
		parent::_afterStore($record, $data);

		// Rebuild the path for the category:
		if (!$record->rebuildPath($record->id))
		{
			$this->setError($record->getError());
			return false;
		}

		// Rebuild the paths of the category's children:
		if (!$record->rebuild($record->id, $record->lft, $record->level, $record->path))
		{
			$this->setError($record->getError());
			return false;
		}

		// Restore extension property of the category to 'com_content'
		if ($record->id)
		{
			$query 	= 'UPDATE #__categories'
				. ' SET extension = "com_content" '
				. ' WHERE id = ' . (int)$record->id
				;
		}
	}


	/**
	 * Method to do some work after record has been loaded via JTable::load()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		parent::_afterLoad($record);
	}
}
