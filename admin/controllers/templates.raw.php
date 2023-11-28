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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'template.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'templates.php';

/**
 * FLEXIcontent Templates Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerTemplates extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_templates';
	var $records_jtable = 'flexicontent_templates';

	var $record_name = 'template';
	var $record_name_pl = 'templates';

	var $_NAME = 'TEMPLATE';
	var $record_alias = null;

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTemplates;
	}


	/**
	 * Logic to duplicate a template
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	public function duplicate()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		$source = $this->input->getString('source');
		$dest   = $this->input->getString('dest');

		$model = $this->getModel($this->record_name_pl);

		if (!$model->duplicate($source, $dest))
		{
			echo JText::sprintf('FLEXI_TEMPLATE_FAILED_CLONE', $source);

			return;
		}
		else
		{
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->clean();
			echo '<span class="copyok" style="margin-top:15px; display:block">' . JText::sprintf('FLEXI_TEMPLATE_CLONED', $source, $dest) . '</span>';
		}
	}

	/**
	 * Logic to remove a template
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	public function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		$dir    = $this->input->getString('dir');
		$model  = $this->getModel($this->record_name_pl);

		if (!$model->delete($dir))
		{
			echo JText::sprintf('FLEXI_TEMPLATE_FAILED_DELETE', $dir);

			return;
		}
		else
		{
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->clean();
			echo '<span class="copyok">' . JText::sprintf('FLEXI_TEMPLATE_DELETED', $dir) . '</span>';
		}
	}


	/**
	 * Logic to render an XML file as form parameters
	 * NOTE: Saving of these extra parameters requires extra handling as the are cleared during main form validation,
	 *       These parameters must validated via an extra JForm object that represents their XML file and then re-added before DB saving step
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	public function getlayoutparams()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		// Get vars
		$ext_option = $this->input->getCmd('ext_option', '');  // Current component name
		$ext_view   = $this->input->getCmd('ext_view', '');    // Current view name
		$ext_type   = $this->input->getCmd('ext_type', '');    // Type layouts: 'templates' or 'forms' or '' (= 'modules'/'fields')
		$ext_name   = $this->input->getCmd('ext_name', '');    // IN item/type/category: template folder name or form layout name
		$ext_id     = $this->input->getInt('ext_id', 0);       // ID of item / type / category being edited

		/**
		 * A prefix and/or a suffix to distinguish multiple loading of same layout in the same page
		 * (This is typically the name of the layout parameter)
		 * Typically the layouts will either use prefix 'PPFX_' or suffix '_PSFX', e.g. to distiguish between
		 * 'desktop_' and 'mobile_' or '_fe' and '_be' for (frontend and backend)
		 */
		$layout_pfx = $this->input->getCmd('layout_pfx', '');
		$layout_sfx = $this->input->getCmd('layout_sfx', '');

		//echo "ext_option: $ext_option , ext_view: $ext_view , ext_type: $ext_type , ext_name: $ext_name , ext_id: $ext_id , layout_pfx: $layout_pfx"; exit;

		$layout_name = $this->input->getString('layout_name', ''); // IN modules/fields: layout name, IN item/type/category forms (FC templates):  'item' / 'category'
		$directory   = $this->input->getString('directory', '');   // Explicit path of XML file:  $layout_name.xml

		$inh_params = false;

		switch ($ext_view)
		{
			case 'item':
				// Get/Create item model ... note there should not be any relevant HTTP Request variables set ...
				require_once (JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.php');
				$item_model = new FlexicontentModelItem();
				$item = $item_model->getItem($ext_id, $check_view_access=false, $no_cache=true);
				$ext_params = $item->parameters;
				$inh_params = $item_model->getComponentTypeParams();
				$query = false;
				//$query = 'SELECT attribs FROM #__content WHERE id = ' . $ext_id;

				// Load language file of the template
				FLEXIUtilities::loadTemplateLanguageFile($ext_name);
				$path = JPATH::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $directory);
				$groupname = 'attribs';  // Name="..." of <fields> container
				break;

			case 'component':
				if ($ext_type !== 'forms')
				{
					echo 'Can only handle ext_type: \'forms\' for view \'component\'';
					return;
				}

				JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, 'en-GB', true);
				JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, null, true);
				$ext_params = JComponentHelper::getParams('com_flexicontent');
				$query = '';
				$path = JPATH::clean(JPATH_ADMINISTRATOR . '/components/com_flexicontent/views/item/tmpl');

				$groupname = 'attribs';  // when empty we use 'attribs'
				break;

			case 'type':
				$query = 'SELECT attribs FROM #__flexicontent_types WHERE id = ' . $ext_id;

				// Load item form layout
				if ($ext_type === 'forms')
				{
					JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, 'en-GB', true);
					JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, null, true);
					$inh_params = JComponentHelper::getParams('com_flexicontent');
					$path = JPATH::clean(JPATH_ADMINISTRATOR . '/components/com_flexicontent/views/item/tmpl');
				}
				// Load item view layout
				else
				{
					$inh_params = flexicontent_tmpl::getLayoutparams('items', $directory, '', true);
					$inh_params = new JRegistry($inh_params);

					// Also Load language file of the template
					FLEXIUtilities::loadTemplateLanguageFile($ext_name);
					$path = JPATH::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $directory);
				}

				$groupname = 'attribs';  // Name="..." of <fields> container
				break;

			case 'category':
				// Get/Create category model ... note there should not be any relevant HTTP Request variables set ...
				require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');
				$cat_model = new FlexicontentModelCategory();
				$category = $cat_model->getCategory($ext_id, $raiseErrors=false, $checkAccess=false);
				$ext_params = $category->params;
				$inh_params = $cat_model->getInheritedParams();
				$query = false;
				//$query = 'SELECT params FROM #__categories WHERE id = ' . $ext_id;

				// Load language file of the template
				FLEXIUtilities::loadTemplateLanguageFile($ext_name);
				$path = JPATH::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $directory);
				$groupname = 'attribs';  // Name="..." of <fields> container
				break;

			case 'user':
				$query = 'SELECT author_catparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $ext_id;
				$inh_params = flexicontent_tmpl::getLayoutparams('category', $directory, '', true);
				$inh_params = new JRegistry($inh_params);

				// Load language file of the template
				FLEXIUtilities::loadTemplateLanguageFile($ext_name);
				$path = JPATH::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $directory);
				$groupname = 'attribs';  // Name="..." of <fields> container
				break;

			case 'module':
				$query = 'SELECT params FROM #__modules WHERE id = ' . $ext_id;

				if ($ext_name)
				{
					JFactory::getLanguage()->load($ext_name, JPATH_SITE, 'en-GB', true);
					JFactory::getLanguage()->load($ext_name, JPATH_SITE, null, true);
				}

				$path = is_dir($directory) ? $directory : JPATH_ROOT . $directory;
				$groupname = 'params';  // Name="..." of <fields> container
				break;

			case 'field':
				$query = 'SELECT attribs FROM #__flexicontent_fields WHERE id = ' . $ext_id;

				if ($ext_name)
				{
					JFactory::getLanguage()->load('plg_flexicontent_fields_' . $ext_name, JPATH_ADMINISTRATOR, 'en-GB', true);
					JFactory::getLanguage()->load('plg_flexicontent_fields_' . $ext_name, JPATH_ADMINISTRATOR, null, true);
				}

				$path = is_dir($directory) ? $directory : JPATH_ROOT . $directory;
				$groupname = 'attribs';  // Name="..." of <fields> container
				break;

			default:
				echo "not supported extension/view: " . $ext_view;

				return;
		}


		/**
		 * Check if using a known modules manager
		 */
		if ($ext_view == 'module' && $ext_option != 'com_modules' && $ext_option != 'com_advancedmodules')
		{
			echo '
			<div class="fc_layout_box_outer">
				<div class="alert fcpadded fc-iblock" style="">
					You are editing module via extension: <span class="label text-white bg-warning label-warning">' . $ext_option . '</span><br/>
					- If extension does not call Joomla event <span class="label text-white bg-warning label-warning">onExtensionBeforeSave</span> then custom layout parameters may not be saved
				</div>
			</div>';
		}

		/**
		 * Load backend language file
		 */
		if (!$app->isClient('administrator'))
		{
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}


		/**
		 * Load and parse parameters from database
		 */
		if ($query)
		{
			$ext_params_str = $db->setQuery($query)->loadResult();
			$ext_params = new JRegistry($ext_params_str);
			//echo '<pre>'; print_r($ext_params); echo '</pre>'; exit;
		}


		/**
		 * Modules case: Check if loading layout file from overrides folder of a Joomla template
		 */
		$layout_names = explode(':', $layout_name);

		if (count($layout_names) > 1)
		{
			$template_name = $layout_names[0];
			$layout_name   = $layout_names[1];
			$layoutpath    = JPATH::clean(JPATH_ROOT . DS . 'templates' . DS . $template_name . DS . 'html' . DS . $ext_name . DS . $layout_name . '.xml');
		}

		if ($layout_name && (empty($layoutpath) || !file_exists($layoutpath)))
		{
			$layoutpath = JPATH::clean($path . DS . $layout_name . '.xml');
		}

		if (!$layout_name)
		{
			$layoutpath = '';
			$layout_msg = JText::_('FLEXI_USING_DEFAULTS');
		}

		elseif (!file_exists($layoutpath))
		{
			$layoutpath_shown = $app->isClient('administrator') ? ' <br><i>' . $layoutpath . '</i>' : '';

			if (file_exists($path . DS . '_fallback' . DS . '_fallback.xml'))
			{
				// Desired layout file does not exist but fallback layout parameter file exists, set layout to fallback layout
				$layoutpath = $path . DS . '_fallback' . DS . '_fallback.xml';
				$layout_msg = JText::sprintf('FLEXI_FIELD_ABOUT_LEGACY_LAYOUT_WITH_NO_PARAMS', $layout_name, $layoutpath_shown, $layout_name );
			}
			else
			{
				$layoutpath = '';
				$layout_msg = JText::sprintf('FLEXI_FIELD_ABOUT_NAMED_LAYOUT_WITH_NO_PARAMS', $layout_name, $layoutpath_shown);
			}
		}

		// Displayed message regarded non-found layout
		if (!empty($layout_msg))
		{
			echo '
			<div class="fc_layout_box_outer">
				<div class="alert alert-info">
					' . $layout_msg . '
				</div>
			</div>';
		}

		// Terminate further execution if we failed to find an existing layout path
		if (!$layoutpath)
		{
			exit;
		}


		/**
		 * Read file and replace parameter suffix if this was providden
		 * layout_sfx should include underscore unlike layout_pfx which does not
		 */
		$file_string = file_get_contents($layoutpath);
		$file_string = str_replace('_PSFX', $layout_sfx, $file_string);
		$file_string = str_replace('PPFX_', $layout_pfx . '_', $file_string);


		/**
		 * Attempt to parse the XML file
		 */
		$xml = simplexml_load_string($file_string);

		if (!$xml)
		{
			echo 'Error parsing layout XML file : ' . $layoutpath;
			return;
		}


		// Create form object, (form name seems not to cause any problem)
		$form_layout = new JForm('com_flexicontent.layout.' . $layout_name, array('control' => 'jform', 'load_data' => true));

		// Load XML file
		$tmpl_params = $xml->asXML();
		$form_layout->load($tmpl_params);

		foreach ($form_layout->getGroup($groupname) as $field)
		{
			// Check if value exists in the extension's parameters and set it into the field name
			$value = $ext_params->get($field->fieldname);

			// NOTE: $value is an object for 'subform' field
			if ( (is_string($value) && strlen($value)) || (is_array($value) && count($value)>0) || is_object($value) )
			{
				$form_layout->setValue($field->fieldname, $groupname, $value);
			}
		}

		if ($layout_name)
		{
			$fieldSets = $form_layout->getFieldsets($groupname);

			foreach ($fieldSets as $fsname => $fieldSet) : ?>

			<div class="fc_layout_box_outer">

				<?php
				if (isset($fieldSet->label) && trim($fieldSet->label))
				{
					echo '<div style="margin:0 0 12px 0; font-size: 16px; background-color: #333; float:none;" class="fcsep_level0">' . JText::_($fieldSet->label) . '</div>';
				}

				if (isset($fieldSet->description) && trim($fieldSet->description))
				{
					echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
				}

				foreach ($form_layout->getFieldset($fsname) as $field) :

					if ($field->getAttribute('not_inherited'))
					{
						continue;
					}

					$fieldname  = $field->fieldname;
					$cssprep    = $field->getAttribute('cssprep');
					$labelclass = $cssprep == 'less' ? 'fc_less_parameter' : '';

					/**
					 * Clear disable fields that are flagged as 'cssprep' when displaying parameters in forms that inherit these values
					 * the values LESS configuration parameters, can only be compiled once for the template's main configuration
					 */
					if ($cssprep && $inh_params && $ext_type === 'templates')
					{
						// Not only set the disabled attribute but also clear the required attribute to avoid issues with some fields (like 'color' field)
						$form_layout->setFieldAttribute($fieldname, 'disabled', 'true', $field->group);
						$form_layout->setFieldAttribute($fieldname, 'required', 'false', $field->group);
					}

					/**
					 * Display the inherited value for the cases:
					 *  item, category view layouts
					 *  item form layouts
					 * This is possible since J3.7.0+ we have the extra form method: Form::getFieldXml()
					 */
					if ($inh_params)
					{
						$_value = $form_layout->getValue($fieldname, $groupname, $inh_params->get($fieldname));

						$_xml_field = $form_layout->getFieldXml($fieldname, $field->group);
						if ($_xml_field)
						{
							$field->setup($_xml_field, $_value, $field->group);
						}

						$field_input_inherited = $inh_params ? flexicontent_html::getInheritedFieldDisplay($field, $inh_params) : $field->input;
					}
					else
					{
						$field_input_inherited = $field->input;
					}


					/**
					 * Rename the fields to match the current form fieldset
					 */
					switch ($ext_type)
					{
						case 'templates':
							$_label = str_replace('jform_attribs_', 'jform_layouts_' . $ext_name . '_', $field->label);
							$_input = str_replace('jform_attribs_', 'jform_layouts_' . $ext_name . '_',
								str_replace('[attribs]', '[layouts][' . $ext_name . ']', $field_input_inherited)
							);
							$_field_id = str_replace('jform_attribs_', 'jform_layouts_' . $ext_name . '_', $field->id);
							break;

						case 'forms':

							$_label = str_replace('jform_attribs_', 'jform_iflayout_', $field->label);
							$_input = str_replace('jform_attribs_', 'jform_iflayout_',
								str_replace('[attribs]', '[iflayout]', $field_input_inherited)
							);
							$_field_id = str_replace('jform_attribs_', 'jform_iflayout_', $field->id);
							break;

						default:
							if (in_array($ext_view, array('field', 'module')))
							{
								$_label = str_replace('jform_attribs_', 'jform_layouts_', $field->label);
								$_input = str_replace('jform_attribs_', 'jform_layouts_',
									str_replace('[attribs]', '[layouts]', $field_input_inherited)
								);
								$_field_id = str_replace('jform_attribs_', 'jform_layouts_', $field->id);
							}
							else
							{
								$_label = $field->label;
								$_input = $field_input_inherited;
								$_field_id = $field->id;
							}
							break;
					}

					/**
					 * Style the label if extra classes were provided
					 */
					if ($labelclass)
					{
						$_label = str_replace('class="', 'class="'.$labelclass.' ', $_label);
					}

					if (!$field->label || $field->hidden)
					{
						echo $_input;
						continue;
					}
					elseif ($field->input)
					{
						$_depends = $field->getAttribute('depend_class');
						echo '
						<div class="control-group' . ($_depends ? ' ' . $_depends : '') . '" id="' . $_field_id . '-container">
							<div class="control-label">
								' . $_label . '
							</div>
							<div class="controls container_fcfield">
								' . $_input . '
							</div>
						</div>
						';
					}

				endforeach; ?>

			</div>

			<?php endforeach; // FieldSets
		}
		else
		{
			echo '
			<div class="fc_layout_box_outer">
				<div class="alert alert-info">
					' . JText::_('FLEXI_APPLY_TO_SEE_THE_PARAMETERS') . '
				</div>
			</div>';
		}

		// parent::display($tpl);
	}


	function loadlayoutfile()
	{
		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$var['sysmssg'] = '';
		$var['content'] = '';
		$var['default_exists'] = '0';

		// Check for request forgeries
		if (!JSession::checkToken('request'))
		{
			$app->enqueueMessage('Invalid Token', 'error');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit;
		}

		$common = array(
			'item.php' => 'item_layouts/modular.php',
			'item_html5.php' => 'item_layouts/modular_html5.php',
		);

		// Get vars
		$load_mode   = $this->input->getInt('load_mode', 0);
		$layout_name = $this->input->getString('layout_name', '');

		$file_subpath = $this->input->getString('file_subpath', '');
		$file_subpath = preg_replace("/\.\.\//", "", $file_subpath);

		// $file_subpath = preg_replace("#\\#", DS, $file_subpath);
		if (!$layout_name)
		{
			$app->enqueueMessage('Layout name is empty / invalid', 'warning');
		}

		if (!$file_subpath)
		{
			$app->enqueueMessage('File path is empty / invalid', 'warning');
		}

		if (!$layout_name || !$file_subpath)
		{
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		$path = JPath::clean(JPATH_ROOT . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $layout_name);

		if (!is_dir($path))
		{
			$app->enqueueMessage('Path: ' . $path . ' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		$file_path = JPath::clean($path . DS . $file_subpath);

		if (!file_exists($file_path))
		{
			$app->enqueueMessage('File: ' . $file_path . ' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		// CASE of downloading instead of loading the file
		if ($load_mode == 2)
		{
			header("Pragma: public"); // Required
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false); // Required for certain browsers
			header("Content-Type: text/plain");
			header("Content-Disposition: attachment; filename=\"" . basename($file_subpath) . "\";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($file_path));
			readfile($file_path);
		}

		// Check if default file path exists
		$default_path = JPath::clean(JPATH_ROOT . DS . 'components' . DS . 'com_flexicontent' . DS . 'tmpl_common');
		$default_file = isset($common[$file_subpath]) ? $common[$file_subpath] : $file_subpath;    // Some files do not have the same name as default file
		$default_file_path = JPath::clean($default_path . DS . $default_file);
		$default_file_exists = file_exists($default_file_path) ? 1 : 0;

		// CASE LOADING system's default, set a different path to be read
		if ($load_mode)
		{
			if (!$default_file_exists)
			{
				$app->enqueueMessage('No default file for: ' . $file_subpath . ' exists, current file was --reloaded--', 'notice');
			}
			else
			{
				$file_path = $default_file_path;
			}
		}

		$var['sysmssg'] = flexicontent_html::get_system_messages_html();
		$var['default_exists'] = (string) $default_file_exists;
		$var['content'] = file_get_contents($file_path);
		echo json_encode($var);
	}


	function savelayoutfile()
	{
		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$var['sysmssg'] = '';
		$var['content'] = '';

		// Check for request forgeries
		if (!JSession::checkToken('request'))
		{
			$app->enqueueMessage('Invalid Token', 'error');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit;
		}

		// Get vars
		$file_contents = $_POST['file_contents'];
		$layout_name  = $this->input->getString('layout_name', '');

		$file_subpath = $this->input->getString('file_subpath', '');
		$file_subpath = preg_replace("/\.\.\//", "", $file_subpath);

		if (!$layout_name)
		{
			$app->enqueueMessage('Layout name is empty / invalid', 'warning');
		}

		if (!$file_subpath)
		{
			$app->enqueueMessage('File path is empty / invalid', 'warning');
		}

		if (!$layout_name || !$file_subpath)
		{
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		$path = JPath::clean(JPATH_ROOT . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $layout_name);

		if (!is_dir($path))
		{
			$app->enqueueMessage('Layout: ' . $layout_name . ' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		$file_path = JPath::clean($path . DS . $file_subpath);

		if (!file_exists($file_path))
		{
			$app->enqueueMessage('Layout: ' . $layout_name . ' was not found', 'warning');
			$var['sysmssg'] = flexicontent_html::get_system_messages_html();
			echo json_encode($var);
			exit();
		}

		if (file_put_contents($file_path, $file_contents))
		{
			$app->enqueueMessage('File: ' . $file_path . ' was saved ', 'message');

			if (preg_match('#\.xml#', $file_path))
			{
				$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
				$tmplcache->clean();
			}
		}

		else
		{
			$app->enqueueMessage('Failed to save file: ' . $layout_name, 'warning');
		}

		$var['sysmssg'] = flexicontent_html::get_system_messages_html();
		$var['content'] = file_get_contents($file_path);
		echo json_encode($var);
	}
}
