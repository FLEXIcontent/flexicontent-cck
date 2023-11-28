<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;

require_once('base/base.php');
require_once('base/traitnestable.php');

/**
 * FLEXIcontent Component Category Model
 *
 */
class FlexicontentModelCategory extends FCModelAdmin
{

	use FCModelTraitNestableRecord;

	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'category';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'categories';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_categories';

	/**
	 * Column names
	 */
	var $state_col   = 'published';
	var $name_col    = 'title';
	var $parent_col  = 'parent_id';

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
	 * Events context to use during model FORM events and diplay PREPARE events triggering
	 *
	 * @var object
	 */
	var $events_context = 'com_content.category';

	/**
	 * Record's type alias string
	 *
	 * @var        string
	 */
	var $type_alias = 'com_content.category';

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
	 * Context to use for registering (language) associations
	 *
	 * @var string
	 */
	var $associations_context = 'com_categories.item';


	/**
	 * Groups of Fields that can be partially present in the form
	 */
	var $mergeableGroups = array('params');

	/**
	 * Various record specific properties
	 *
	 */

	// Inherited parameters
	var $_inherited_params = null;

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array('a.extension' => FLEXI_CAT_EXTENSION);

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getCategory($pk = null)
	{
		return parent::getRecord($pk);
	}


	/**
	 * Method to initialise the record data
	 *
	 * @param   object      $record    The record being initialized
	 * @param   boolean     $initOnly  If true then only a new record will be initialized without running the _afterLoad() method
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.5
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		parent::_initRecord($record, $initOnly);

		// Set some new record specific properties, note most properties already have proper values
		// Either the DB default values (set by getTable() method) or the values set by _afterLoad() method
		$record->id							= 0;
		$record->parent_id			= 0;
		$record->title					= null;
		$record->name						= null;  //$this->getName() . ($this->_getLastId() + 1);
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
	public function getTable($type = 'Category', $prefix = null, $config = array())
	{
		$prefix = $prefix !== null
			? $prefix
			: (FLEXI_J40GE ? 'JTable' : 'CategoriesTable');

		if (!FLEXI_J40GE)
		{
			JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_categories'.DS.'tables');
		}

		return parent::getTable($type, $prefix, $config);
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
		$this->cleanCache(null, 0);
		$this->cleanCache(null, 1);

		return true;
	}


	/**
	 * Method to load inherited parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	public function getInheritedParams($force=false)
	{
		if ($this->_inherited_params !== null && !$force)
		{
			return $this->_inherited_params;
		}

		$id = (int) $this->_id;

		$app = JFactory::getApplication();

		// a. Clone component parameters ... we will use these as parameters base for merging
		$compParams = clone(JComponentHelper::getComponent('com_flexicontent')->params);

		// b. Retrieve category parameters and create parameter object
		if ($id)
		{
			$query = $this->_db->getQuery(true)
				->select('params')
				->from('#__categories')
				->where('id = ' . (int) $id);
			$catParams = $this->_db->setQuery($query)->loadResult();
			$catParams = new JRegistry($catParams);
		}
		else
		{
			$catParams = new JRegistry();
		}


		// c. Retrieve inherited parameter and create parameter objects
		global $globalcats;
		$heritage_stack = array();
		$inheritcid = $catParams->get('inheritcid', '');
		$inheritcid_comp = $compParams->get('inheritcid', -1);
		$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);

		// CASE A: inheriting from parent category tree
		if ($id && $inherit_parent && !empty($globalcats[$id]->ancestorsonly))
		{
			$query = $this->_db->getQuery(true)
				->select('title, id, params')
				->from('#__categories')
				->where('id IN ( ' . $globalcats[$id]->ancestorsonly . ')')
				->order('level DESC');
				//>order('FIELD(id, ' . $globalcats[$id]->ancestorsonly . ' DESC)';
			$catdata = $this->_db->setQuery($query)->loadObjectList('id');

			if (!empty($catdata))
			{
				foreach ($catdata as $parentcat)
				{
					$parentcat->params = new JRegistry($parentcat->params);
					array_push($heritage_stack, $parentcat);
					$inheritcid = $parentcat->params->get('inheritcid', '');
					$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);

					// Stop inheriting from further parent categories
					if (!$inherit_parent)
					{
						break;
					}
				}
			}
		}

		// CASE B: inheriting from specific category
		elseif ($id && $inheritcid > 0 && !empty($globalcats[$inheritcid]))
		{
			$query = $this->_db->getQuery(true)
				->select('title, params')
				->from('#__categories')
				->where('id = ' . (int) $inheritcid);
			$catdata = $this->_db->setQuery($query)->loadObject();

			if ($catdata)
			{
				$catdata->params = new JRegistry($catdata->params);
				array_push($heritage_stack, $catdata);
			}
		}


		/**
		 * Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/ancestors-cats/category
		 */

		// -1. layout parameters will be placed on top at end of this code ...

		// 0. Start from component parameters
		$params = new JRegistry();
		$params->merge($compParams);

		// 1. Merge category's inherited parameters (e.g. ancestor categories or specific category)
		while (!empty($heritage_stack))
		{
			$catdata = array_pop($heritage_stack);
			if ($catdata->params->get('orderbycustomfieldid') === '0')
			{
				$catdata->params->set('orderbycustomfieldid', '');
			}
			$params->merge($catdata->params);
		}

		// 2. Merge category parameters
		/**
		 * CURRENT CATEGORY PARAMETERS MUST BE SKIPED ! we only want the inherited parameters
		 */
		/*
		if ($catParams->get('orderbycustomfieldid') === '0')
		{
			$catParams->set('orderbycustomfieldid', '');
		}
		$params->merge($catParams);
		*/

		// 3. Retrieve and merge Global Layout's parameters (placing these at TOP allows proper heritage)
		$layoutParams = flexicontent_tmpl::getLayoutparams('category', $params->get('clayout'), '', $force);
		$layoutParams = new JRegistry($layoutParams);

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
	public function getParams($id)
	{
		$query = $this->_db->getQuery(true)
			->select('params')
			->from('#__categories')
			->where('id = ' . (int) $id);

		return $this->_db->setQuery($query)->loadResult();
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
	public function copyParams($id, $params)
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
		$form = parent::getForm($data, $loadData);

		if (empty($form))
		{
			return false;
		}

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
					$item->associations = FLEXI_J40GE
						? CategoriesHelper::getAssociations($item->id, $item->extension)
						: \CategoriesHelper::getAssociations($item->id, $item->extension);
					$item->associations = ArrayHelper::toInteger($item->associations);
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
		$component = $this->getState($this->getName().'.component', '');
		$section   = $this->getState($this->getName().'.section', '');
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
					$field->addAttribute('type', 'fccategory');
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
	 * Method to change the title & alias.
	 *
	 * @param   integer  $parent_id  If applicable, the id of the parent (e.g. assigned category)
	 * @param   string   $alias      The alias / name.
	 * @param   string   $title      The title / label.
	 *
	 * @return  array    Contains the modified title and alias / name.
	 *
	 * @since   1.7
	 */
	protected function generateNewTitle($parent_id, $alias, $title)
	{
		return parent::generateNewTitle($parent_id, $alias, $title);
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEdit($record = null, $user = null)
	{
		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();
		$asset   = $record && !$record->id ? $this->type_alias . '.' . $record->id : $this->option;
		$isOwner = $record && $user->id && $record->created_user_id == $user->id;

		$canDo    = $user->authorise('core.edit', $asset);
		$canDoOwn	= $user->authorise('core.edit.own', $asset) && $isOwner;

		return $canDo || $canDoOwn;
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEditState($record = null, $user = null)
	{
		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();
		$asset   = $record && !$record->id ? $this->type_alias . '.' . $record->id : $this->option;
		$isOwner = $record && $user->id && $record->created_user_id == $user->id;

		$canDo    = $user->authorise('core.edit.state', $asset);
		$canDoOwn	= $user->authorise('core.edit.state.own', $asset) && $isOwner;

		return $canDo || $canDoOwn;
	}


	/**
	 * Method to check if the user can delete the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canDelete($record = null)
	{
		$record  = $record ?: $this->_record;
		$user    = JFactory::getUser();
		$asset   = $record && !$record->id ? $this->type_alias . '.' . $record->id : $this->option;
		$isOwner = $record && $user->id && $record->created_user_id == $user->id;

		$canDo    = $user->authorise('core.delete', $asset);
		$canDoOwn	= $user->authorise('core.delete.own', $asset) && $isOwner;

		return $canDo || $canDoOwn;
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
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
		$mergeProperties = $this->mergeableGroups;
		$mergeOptions = array(
			'params_fset'  => 'params',
			'layout_type'  => 'category',
			'model_names'  => array($this->option => $this->getName(), 'com_categories' => 'category'),
			'cssprep_save' => false,
		);
		$this->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
		}

		// Optionally copy parameters from another category
		$copycid = (int) $data['copycid'];

		if ($copycid)
		{
			unset($data['params']);
			$record->params = $this->getParams($copycid);
		}

		// Call parent class bind preparation
		parent::_prepareBind($record, $data);
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
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
	 * @param	object   $record   The record object
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		parent::_afterLoad($record);
	}


	/**
	 * Custom clean the cache
	 *
	 * @param   string   $group      Clean cache only in the given group
	 * @param   integer  $client_id  Site Cache (0) / Admin Cache (1)
	 *
	 * @return  void
	 *
	 * @since   3.2.0
	 */
	protected function cleanCache($group = NULL, $client_id = 0)
	{
		if ($group)
		{
			parent::cleanCache($group, $client_id);
		}

		// An empty '$group' will clean '$this->option' which is the Component VIEW Cache, we will do a little more ...
		else
		{
			/**
			 * Note: null should be the same as $this->option ...
			 * Maybe add option not clean Component's VIEW cache it will be too aggressive ...
			 */
			if (1)
			{
				parent::cleanCache(null, $client_id);
				parent::cleanCache('com_content', $client_id);
			}

			parent::cleanCache('mod_articles_archive', $client_id);
			parent::cleanCache('mod_articles_categories', $client_id);
			parent::cleanCache('mod_articles_category', $client_id);
			parent::cleanCache('mod_articles_latest', $client_id);
			parent::cleanCache('mod_articles_news', $client_id);
			parent::cleanCache('mod_articles_popular', $client_id);

			parent::cleanCache('com_flexicontent_cats', $client_id);

			// Indicate to our system plugin that its category cache needs to be cleaned
			JFactory::getSession()->set('clear_cats_cache', 1, 'flexicontent');
		}
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */



	/**
	 * Method to get parameters of parent categories
	 *
	 * @param   integer  $pk  The category id
	 * @return	string   An array of JSON strings
	 *
	 * @since	3.3.0
	 */
	public function getParentParams($pk)
	{
		if (empty($pk))
		{
			return array();
		}

		global $globalcats;

		$query = 'SELECT id, params'
			. ' FROM #__categories'
			. ' WHERE id IN (' . $globalcats[$pk]->ancestors . ')'
			. ' ORDER BY level ASC'
		;
		return $this->_db->setQuery($query)->loadObjectList('id');
	}
}
