<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('cms.plugin.plugin');

class FCField extends JPlugin
{
	// ***
	// *** ATTRIBUTES
	// ***

	protected $fieldtypes = null;
	protected $field = null;
	protected $item = null;
	protected $vars = null;
	protected $autoloadLanguage = false;

	static $cparams;

	static $mobileDetector;
	static $isMobile;
	static $isTablet;
	static $useMobile;

	static $itemViewId;
	static $isItemsManager;
	static $isHtmlViewFE;
	static $fcProPlg;


	/**
	 * CONSTRUCTOR
	 */

	public function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$class = strtolower(get_class($this));
		$fieldtype = str_replace('plgflexicontent_fields', '', $class);

		static::$field_types = static::$field_types ?: array($fieldtype);

		if (empty($this->fieldtypes))
		{
			$this->fieldtypes = static::$field_types;
		}

		// Load extra field types and their language files if these have not be loaded already
		static $initialized = array();

		foreach(static::$field_types as $ft)
		{
			if (isset($initialized[$ft]))
			{
				continue;
			}

			$initialized[$ft] = true;

			/**
			 * Because 'site-default' language file may not have all needed language strings, or it may be syntactically broken
			 * we load the ENGLISH language file (without forcing it, to avoid overwriting site-default), and then current language file
			 */
			$extension_name = 'plg_flexicontent_fields_' . $ft;
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB', $force_reload = false, $load_default = true);  // force_reload OFF
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null, $force_reload = true, $load_default = true);  // force_reload ON

			// Import field type if not already imported
			$class_name = 'plgFlexicontent_fields' . ucfirst($ft);

			if (!class_exists($class_name))
			{
				JPluginHelper::importPlugin('flexicontent_fields', $ft);
			}
		}

		/**
		 * One time initialization for all fields (static variables)
		 */
		static $init = null;

		if ($init === null)
		{
			$init = true;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');

			static::$itemViewId     = $realview === 'item' && $option === 'com_flexicontent' ? $app->input->get('id', 0, 'int') : 0;
			static::$isItemsManager = $app->isClient('administrator') && $realview === 'items' && $option === 'com_flexicontent';
			static::$isHtmlViewFE   = $format === 'html' && $app->isClient('site');

			static::$cparams        = JComponentHelper::getParams('com_flexicontent');
			static::$mobileDetector = flexicontent_html::getMobileDetector();

			static::$isMobile  = static::$mobileDetector->isMobile();
			static::$isTablet  = static::$mobileDetector->isTablet();
			static::$useMobile = static::$cparams->get('force_desktop_layout', 0)
				? static::$isMobile && !static::$isTablet
				: static::$isMobile;

			// Check for PRO system plugin presence
			$plg_enabled = JPluginHelper::isEnabled('system', 'flexisyspro');
			$extfolder   = 'system';
			$extname     = 'flexisyspro';
			$className   = 'plg' . ucfirst($extfolder) . $extname;
			$plgPath     = JPATH_SITE . '/plugins/' . $extfolder . '/' . $extname . '/' . $extname . '.php';

			if (!$plg_enabled)
			{
				self::$fcProPlg = false;

				if (file_exists($plgPath))
				{
					$app->enqueueMessage('Flexisyspro (system) plugin is installed but not enabled', 'notice');
				}
			}

			// Create plugin instance of PRO system plugin
			else
			{
				$dispatcher     = JEventDispatcher::getInstance();
				$plg_db_data    = JPluginHelper::getPlugin($extfolder, $extname);

				// Load class if called by CLI
				JLoader::register($className, $plgPath);

				self::$fcProPlg = new $className($dispatcher, array(
					'type'   => $extfolder,
					'name'   => $extname,
					'params' => $plg_db_data->params
				));
			}
		}
	}



	// ***
	// *** Accessor functions
	// ***

	public function setField($field)
	{
		$this->field = $field;
	}
	public function setItem($item)
	{
		$this->item = $item;
	}


	public function getField()
	{
		return $this->field;
	}
	public function getItem()
	{
		return $this->item;
	}


	/**
	 * Check if field should be rendered for given 'display variable' ($prop) and for current page ($view) and current user's client
	 */
	protected function checkRenderConds($prop, $view)
	{
		$show_in_views   = FLEXIUtilities::paramToArray($this->field->parameters->get('show_in_views', array('item', 'category', 'module', 'backend')));
		$show_in_clients = FLEXIUtilities::paramToArray($this->field->parameters->get('show_in_clients', array('desktop', 'tablet', 'mobile')));

		// Calculate if field should be shown , if field is in 'sublist' then ignore 'view', since 'view' should be checked only for the relation field itself
		return (in_array($view, $show_in_views) || $view === 'sublist' || $view === 'itemcompare') && (
			(static::$isTablet && in_array('tablet', $show_in_clients)) ||
			(!static::$isTablet && static::$isMobile && in_array('mobile', $show_in_clients)) ||
			(!static::$isTablet && !static::$isMobile && in_array('desktop', $show_in_clients))
		);
	}


	/**
	 * Method to decide and return the field value separator
	 *
	 * @param		string      $opentag      Needed only when using $closetag + $opentag as separator
	 * @param		string      $closetag     Needed only when using $closetag + $opentag as separator
	 * @param		int|string  $sep_default  Default separator, either a selection index (integer) or custom text
	 *
	 * @return	string      The field values separator
	 *
	 * @since   3.3.0
	 */
	protected function getSeparatorF($opentag, $closetag, $sep_default = 1)
	{
		$separatorf = $this->field->parameters->get('separatorf', $sep_default);

		// Check if using custom separator
		if ($separatorf == 7)
		{
			$sep_custom = $this->field->parameters->get('separatorf_custom', '');

			// Fallback to default separator if custom separator HTML is not,
			// if default separator is ... ? also 7 (aka custom) then fallback to 1
			if (!strlen($sep_custom))
			{
				$separatorf = $sep_default != 7 ? $sep_default : 1;
			}
		}

		switch($separatorf)
		{
			case 0:
				$separatorf = ' ';
				break;

			case 1:
				$separatorf = '<br class="fcclear" />';
				break;

			case 2:
				$separatorf = ' | ';
				break;

			case 3:
				$separatorf = ', ';
				break;

			case 4:
				$separatorf = $closetag . $opentag;
				break;

			case 5:
				$separatorf = '';
				break;

			case 6:
				$separatorf = '<hr class="fcclearline" />';
				break;

			case 7:
				// Custom separator
				$separatorf = $sep_custom;
				break;

			default:
				// '$separatorf_default' actually contains the separator HTML, and not a selection index
				$separatorf = $sep_default;
				break;
		}

		return $separatorf;
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		// Parse field values
		$this->values = $this->parseValues($this->field->value);

		// Optionally, get default field values array is empty
		$this->values = count($this->values) ? $this->values : $this->getDefaultValues($isform = true);

		// Call before display method, for optionally work
		$this->beforeDisplayField();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout && $formlayout != 'field' ? 'field_'.$formlayout : 'field';

		// Display the form field
		$this->displayField($formlayout);
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// Use the custom field values, if these were provided
		$values = $values !== null ? $values : $this->field->value;

		// Parse field values
		$this->values = $this->parseValues($values);

		// Get default values if field current values are empty
		$this->values = count($this->values) ? $this->values : $this->getDefaultValues($isform = false);

		// Get choosen display layout
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout && $viewlayout !== 'value' ? 'value_'.$viewlayout : 'value';

		// Create field's display
		$this->displayFieldValue($prop, $viewlayout);
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// As default implementation of this method, we force a basic filter of single text search input
		$filter->parameters->set( 'display_filter_as_s', 1 );
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// As default implementation of this method, we force a basic filter of single text search input
		$filter->parameters->set( 'display_filter_as', 1 );
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// As default implementation of this method, we force a basic filter of single text search input
		$filter->parameters->set( 'display_filter_as', 1 );
		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// As default implementation of this method, we force a basic filter of single text search input
		$filter->parameters->set( 'display_filter_as_s', 1 );
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}



	/**
	 * Function to calculate and return the layout path for a field
	 */

	public static function getLayoutPath($plg, $layout = 'field', $plg_subtype='', $default_layout='field')
	{
		static $paths = array();
		static $template = null;

		if ( isset($paths[$plg][$layout][$plg_subtype]) )  // return cached
		{
			return $paths[$plg][$layout][$plg_subtype];
		}

		if ($template === null)
		{
			// Get frontend template in case the field is using a layout override in the frontend template folder
			$template = flexicontent_html::getSiteTemplate();
		}

		// Get the template and layout name from the string if string contains ':' (aka folder seperators)
		if (strpos($layout, ':') !== false)
		{
			$temp = explode(':', $layout);
			$template = ($temp[0] == '_') ? $template : $temp[0];
			$layout = $temp[1];
		}

		// ***
		// *** Build the template and base path for the layout, and check it exists, returing it, in order of priority
		// ***

		// Layout via Joomla template override, in /templates/ folder
		if (file_exists(($tPath = JPATH_ROOT . '/templates/' . $template . '/html/flexicontent_fields/' . $plg . '/' . ($plg_subtype ? $plg_subtype.'/' : '') . $layout . '.php')))
			$return = $tPath;

		// Layout inside field folder
		elseif (file_exists($fPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . $layout . '.php'))
			$return = $fPath;

		// Default fallback
		elseif (file_exists($dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . $default_layout . '.php'))
			$return = $dPath;

		// Default fallback (strip '_default')
		else
			$return = $dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . str_replace('_default', '', $default_layout) . '.php';

		$paths[$plg][$layout][$plg_subtype] = $return;  // Cache result
		return $return;
	}


	// Get Layout paths for editing, this is a wrapper to getLayoutPath()
	public function getFormPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout ?: 'field', $plg_subtype, $default='field_default');
	}

	// Get Layout paths for viewing, this is a wrapper to getLayoutPath()
	public function getViewPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout ?: 'value', $plg_subtype, $default_layout='value_default');
	}



	/**
	 * Include a layout file, setting some variables, for compatibility: $field, $item, $values
	 * UNUSED, remains for B/C reasons with 3rd party fields that may use it
	 * This is unused because we need to expose more values to the layout,
	 * and duplicating various per field code to assign various variables, would be bug-prone
	 */
	protected function includePath($path, $prop = 'display', $document_type = 'html')
	{
		if (!file_exists($path))
		{
			return false;
		}

		$field  = $this->getField();
		$item   = $this->getItem();
		$values = $this->values;

		include($path);

		return true;
	}



	/**
	 * Function to create field's HTML for edit form
	 */
	protected function displayField($layout = 'field')
	{
		// Prepare variables
		$use_ingroup = 0;
		$multiple = 0;

		$field  = $this->getField();
		$item   = $this->getItem();
		$values = $this->values;

		$field->html = array();

		// Create field's form HTML, using layout file, (editing layout)
		include(self::getFormPath($this->fieldtypes[0], $layout));

		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}
	}



	// *****************************************************
	// Function to create field's HTML display (for viewing)
	// *****************************************************

	protected function displayFieldValue($prop = 'display', $layout = 'value')
	{
		// Get Field and Item objects, and values
		$field  = $this->getField();
		$item   = $this->getItem();
		$values = $this->values;

		// Prepare some of the needed variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);

		// Current view variable
		$app      = JFactory::getApplication();
		$realview = $app->input->getCmd('view', '');
		$view     = JFactory::getApplication()->input->getCmd('flexi_callview', ($realview ?: 'item'));

		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		/**
		 * Create field's display HTML, using layout file, (viewing layout)
		 * NOTE: we will use $this->fieldtypes[0] to get the proper path that contains the layouts
		 */

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();

		$layout_filename = self::getViewPath($this->fieldtypes[0], $layout); 
		if (file_exists($layout_filename))
		{
			include($layout_filename);
		}
		else
		{
			$field->{$prop} = '<div class="alert alert-info">' . $field->label .
				' field does not implement the layout file: \'' . $layout . '\' please remove field from displayed fields' .
				'</div>';
			return;
		}

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}
			elseif ($no_value_msg !== '')
			{
				$field->{$prop} = $no_value_msg;
			}
		}
	}



	// ***
	// *** Functions to execute common value preparation
	// ***

	// Do something before creating field's HTML for edit form
	protected function beforeDisplayField() {}

	// do something after creating field's HTML for edit form
	protected function afterDisplayField() {}

	// Create and returns a default value
	protected function getDefaultValues($isform = true, $translate = true, $split = '')
	{
		$value_usage = (int) $this->field->parameters->get('default_value_use', 0);

		if ($split)
		{
			$default_values = $isform
				? (($this->item->version == 0 || $value_usage > 0) ? trim($this->field->parameters->get( 'default_values', '' )) : '')
				: ($value_usage === 2 ? trim($this->field->parameters->get( 'default_values', '' )) : '');

			$default_values = preg_split("/\s*" . $split . "\s*/u", $default_values);
		}
		else
		{
			$default_value = $isform
				? (($this->item->version == 0 || $value_usage > 0) ? trim($this->field->parameters->get( 'default_value', '' )) : '')
				: ($value_usage === 2 ? trim($this->field->parameters->get( 'default_value', '' )) : '');

			/**
			 * Return default value. Note: If no default value then return:
			 *  array('') for item form
			 *  array() for item viewing
			 */
			$default_values = strlen($default_value) || $isform
				? array($default_value)
				: array();
		}

		if ($translate)
		{
			foreach ($default_values as $i => $v)
			{
				$default_values[$i] = strlen($v) ? JText::_($v) : '';
			}
		}

		return $default_values;
	}

	// Parses and returns fields values, unserializing them if serialized
	protected function parseValues($values)
	{
		$vals = array();

		// Check if empty
		if (empty($values))
		{
			return $vals;
		}

		// Make sure we have an array of values
		$values = is_array($values) ? $values : array($values);

		foreach($values as $value)
		{
			// Compatibility for non-serialized values or for NULL values in a field group
			if (!is_array($value))
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$vals[] = $array ?: array();
			}
		}

		return $vals;
	}


	// Get Prefix - Suffix - Separator parameters and other common parameters
	protected function & getCommonParams($sep_default = 1)
	{
		static $conf = array();

		if (isset($conf[$this->field->id][$this->item->id]))
		{
			return $conf[$this->field->id][$this->item->id];
		}

		// Value Prefix - Suffix, Value List Open Text - Close Text, replacing other field values, and item properties
		$arr = array();
		$arr['pretext']  = FlexicontentFields::replaceFieldValue($this->field, $this->item, JText::_($this->field->parameters->get('pretext', '')), 'pretext');
		$arr['posttext'] = FlexicontentFields::replaceFieldValue($this->field, $this->item, JText::_($this->field->parameters->get('posttext', '')), 'posttext');
		$arr['opentag']  = FlexicontentFields::replaceFieldValue($this->field, $this->item, JText::_($this->field->parameters->get('opentag', '')), 'opentag');
		$arr['closetag'] = FlexicontentFields::replaceFieldValue($this->field, $this->item, JText::_($this->field->parameters->get('closetag', '')), 'closetag');

		// Add spaces to Value Prefix - Suffix texts
		$arr['remove_space'] = $this->field->parameters->get('remove_space', 0);

		if ($arr['pretext'])
		{
			$arr['pretext']  = $arr['remove_space'] ? $arr['pretext'] : $arr['pretext'] . ' ';
		}
		if ($arr['posttext'])
		{
			$arr['posttext'] = $arr['remove_space'] ? $arr['posttext'] : ' ' . $arr['posttext'];
		}

		// Get value separator text
		$arr['separatorf'] = $this->getSeparatorF($arr['opentag'], $arr['closetag'], $sep_default);

		// Microdata (classify the field values for search engines)
		$arr['itemprop'] = $this->field->parameters->get('microdata_itemprop');

		// No value text
		$arr['no_value_msg'] = $this->field->parameters->get('show_no_value', 0)
			? JText::_($this->field->parameters->get('no_value_msg', 'FLEXI_NO_VALUE'))
			: '';

		$conf[$this->field->id][$this->item->id] = $arr;

		return $arr;
	}


	// Unserialize array from string but abort if objects are detected
	function unserialize_array($v, $force_array=false, $force_value = true)
	{
		return flexicontent_db::unserialize_array($v, $force_array, $force_value);
	}


	/**
	 * Method to do extra handling of field's values after all fields have validated their posted data, and are ready to be saved
	 *
	 * $item->fields['fieldname']->postdata contains values of other fields
	 * $item->fields['fieldname']->filedata contains files of other fields (normally this is empty due to using AJAX for file uploading)
	 */
	public function onAllFieldsPostDataValidated(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		/**
		 * Check if using 'auto_value_code', clear 'auto_value', if function not set
		 */
		$auto_value = (int) $field->parameters->get('auto_value', 0);
		if ($auto_value === 2)
		{
			$auto_value_code = $field->parameters->get('auto_value_code', '');
			$auto_value_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $auto_value_code);
		}
		$auto_value = $auto_value === 2 && !$auto_value_code ? 0 : $auto_value;

		if (!$auto_value)
		{
			return;
		}

		//JFactory::getApplication()->enqueueMessage('Automatic field value for field  \'' . $field->label, 'notice');

		if (!self::$fcProPlg)
		{
			JFactory::getApplication()->enqueueMessage('Automatic field value for field  \'' . $field->label . '\' is only supported by FLEXIcontent PRO version, please disable this feature in field configuration', 'notice');
			return;
		}

		// Create automatic value
		return self::$fcProPlg->onAllFieldsPostDataValidated($field, $item);
	}


	// Create once the options for every field property that has specific selection options (e.g. drop down-selection)
	function getPropertyOptions($choices, $default_option = null)
	{
		// Parse the elements used by field unsetting last element if empty
		$choices = preg_split("/[\s]*%%[\s]*/", $choices);
		if ( empty($choices[count($choices)-1]) )
		{
			unset($choices[count($choices)-1]);
		}

		// Split elements into their properties: value, label, extra_prop1, extra_prop2
		$elements = array();
		$k = 0;
		foreach ($choices as $choice)
		{
			$choice_props  = preg_split("/[\s]*::[\s]*/", $choice);
			if (count($choice_props) < 2)
			{
				echo "Error in field: ".$field->label.
					" while splitting class element: ".$choice.
					" properties needed: ".$props_needed.
					" properties found: ".count($choice_props);
				continue;
			}
			$elements[$k] = new stdClass();
			$elements[$k]->value = $choice_props[0];
			$elements[$k]->text  = $choice_props[1];
			$k++;
		}

		// Create the options for select drop down
		$options = array();
		if ($default_option === null)
		{
			$options[] = JHtml::_('select.option', '', '-');
		}
		else
		{
			$default_label = $default_option->label;

			foreach ($elements as $element)
			{
				if ($element->value === $default_option->value)
				{
					$default_label .= ' - (' . JText::_($element->text) . ')';
				}
			}
			$options[] = JHtml::_('select.option', '', $default_label);
		}
		foreach ($elements as $element)
		{
			$options[] = JHtml::_('select.option', $element->value, JText::_($element->text));
		}

		return $options;
	}


	/**
	 *  Get existing field values
	 */
	public function getExistingFieldValues()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('value')
			->from('#__flexicontent_fields_item_relations')
			->where('field_id = ' . (int) $this->field->id)
			->where('item_id = ' . (int) $this->item->id)
			->order('valueorder')
			;

		return $db->setQuery($query)->loadColumn();
	}


	// Get existing field values
	function renameLegacyFieldParameters($map)
	{
		// Load parameters directly from DB
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('attribs')
			->from('#__flexicontent_fields')
			->where('id = ' . (int) $this->field->id)
			;
		$attribs = $db->setQuery($query)->loadResult();

		// Decode parameters
		$_attribs = json_decode($attribs);

		// Set old parameter values into new parameters, removing the old parameter values
		foreach($map as $old => $new)
		{
			if (isset($_attribs->$old))
			{
				// Set new parameter value and remove legacy parameter value
				$_attribs->$new = $_attribs->$old;
				unset($_attribs->$old);

				// Update existing parameters object, to avoid reload field parameters
				$this->field->parameters->set($new, $_attribs->$new);
			}
		}

		// Re-encode parameters
		$attribs = json_encode($_attribs);

		// Store field parameter back to the DB
		$query = $db->getQuery(true)
			->update('#__flexicontent_fields')
			->set('attribs = ' . $db->Quote($attribs))
			->where('id = ' . (int) $this->field->id)
			;
		$db->setQuery($query)->execute();
	}
}
