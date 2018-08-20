<?php
/**
 * @version 1.5 stable $Id: templates.php 1260 2012-04-25 17:43:21Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controller.php');

/**
 * FLEXIcontent Component Templates Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTemplates extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
	}

	/**
	 * Logic to duplicate a template
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function duplicate()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		$source 		= JRequest::getCmd('source');
		$dest 			= JRequest::getCmd('dest');

		$model = $this->getModel('templates');

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
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		$dir = JRequest::getCmd('dir');
		$model = $this->getModel('templates');

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
	function getlayoutparams()
	{
		// Check access
		if (!FlexicontentHelperPerm::getPerm()->CanTemplates)
		{
			jexit(JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		jimport('joomla.filesystem.file');
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		// Get vars
		$ext_option = $app->input->get('ext_option', '', 'CMD');  // Current component name
		$ext_view   = $app->input->get('ext_view', '', 'CMD');    // Current view name
		$ext_type   = $app->input->get('ext_type', '', 'CMD');    // Type layouts: 'templates' or empty: ('modules'/'fields')
		$ext_name   = $app->input->get('ext_name', '', 'CMD');    // IN item/type/category (templates): template name
		$ext_id     = $app->input->get('ext_id', 0, 'INT');       // ID of item / type / category being edited
		$layout_pfx = $app->input->get('layout_pfx', '', 'CMD');  // A prefix to distinguish multiple loading of same layout in the same page: (This is typically the name of the layout parameter)

		$layout_name = $app->input->get('layout_name', '', 'CMD'); // IN modules/fields: layout name, IN item/type/category forms (FC templates):  'item' / 'category'
		$directory   = $app->input->get('directory', '', 'STRING');   // Explicit path of XML file:  $layout_name.xml

		$db = JFactory::getDbo();

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

			case 'type':
				$query = 'SELECT attribs FROM #__flexicontent_types WHERE id = ' . $ext_id;
				$inh_params = flexicontent_tmpl::getLayoutparams('items', $directory, '', true);
				$inh_params = new JRegistry($inh_params);

				// Load language file of the template
				FLEXIUtilities::loadTemplateLanguageFile($ext_name);
				$path = JPATH::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'templates' . DS . $directory);
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

		if ($ext_view == 'module' && $ext_option != 'com_modules' && $ext_option != 'com_advancedmodules')
		{
			echo '
			<div class="fc_layout_box_outer">
				<div class="alert fcpadded fc-iblock" style="">
					You are editing module via extension: <span class="label label-warning">' . $ext_option . '</span><br/>
					- If extension does not call Joomla event <span class="label label-warning">onExtensionBeforeSave</span> then custom layout parameters may not be saved
				</div>
			</div>';
		}

		if (!$app->isAdmin())
		{
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}

		if ($query)
		{
			// Load and parse parameters
			$ext_params_str = $db->setQuery($query)->loadResult();
			$ext_params = new JRegistry($ext_params_str);
		}

		$layout_names = explode(':', $layout_name);

		if (count($layout_names) > 1)
		{
			$layout_name = $layout_names[1];
			$layoutpath = JPATH::clean(JPATH_ROOT . DS . 'templates' . DS . $layout_names[0] . DS . 'html' . DS . $ext_name . DS . $layout_name . '.xml');
		}

		if (empty($layoutpath) || !file_exists($layoutpath))
		{
			$layoutpath = JPATH::clean($path . DS . $layout_name . '.xml');
		}

		if (!$layout_name)
		{
			echo '
			<div class="fc_layout_box_outer">
				<div class="alert alert-info">
					Using defaults
				</div>
			</div>';
			exit;
		}

		elseif (!file_exists($layoutpath))
		{
			if (file_exists($path . DS . '_fallback' . DS . '_fallback.xml'))
			{
				$layoutpath = $path . DS . '_fallback' . DS . '_fallback.xml';
				echo '
				<div class="fc_layout_box_outer">
					<div class="alert alert-warning">
						Currently selected layout: <b>"' . $layout_name . '"</b> does not have a parameters XML file, using general defaults.
						If this is an old template then these parameters will allow to continue using it, but we recommend that you create parameter file: ' . $layout_name . '.xml
					</div>
				</div>';
			}
			else
			{
				echo '
				<div class="fc_layout_box_outer">
					<div class="alert alert-info">
						Currently selected layout: <b>"' . $layout_name . '"</b> does not have layout specific parameters
					</div>
				</div>';
				exit;
			}
		}

		// Attempt to parse the XML file
		$xml = simplexml_load_file($layoutpath);

		if (!$xml)
		{
			die('Error parsing layout XML file : ' . $layoutpath);
		}

		// Create form object, (form name seems not to cause any problem)
		$form_layout = new JForm('com_flexicontent.layout.' . $layout_name, array('control' => 'jform', 'load_data' => true));

		// Load XML file
		$tmpl_params = $xml->asXML();
		$form_layout->load($tmpl_params);

		foreach ($form_layout->getGroup($groupname) as $field)
		{
			// Get prefixed fieldname (that is, if the given layout is using prefix)
			$prefixed_fieldname = str_replace('PPFX_', $layout_pfx . '_', $field->fieldname);

			// Check if value exists in the extension's parameters and set it into the non-prefixed field name
			$value = $ext_params->get($prefixed_fieldname);

			if (strlen($value))
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

					if ($ext_type === 'templates')
					{
						// For J3.7.0+ , we have extra form methods Form::getFieldXml()
						if ($cssprep && FLEXI_J37GE && $inh_params)
						{
							$_value = $form_layout->getValue($fieldname, $groupname, $inh_params->get($fieldname));
							$form_layout->setFieldAttribute($fieldname, 'disabled', 'true', $field->group);
							$field->setup($form_layout->getFieldXml($fieldname, $field->group), $_value, $field->group);
						}

						$_label = str_replace('jform_attribs_', 'jform_layouts_' . $ext_name . '_', $field->label);
						$_input = str_replace('jform_attribs_', 'jform_layouts_' . $ext_name . '_',
							str_replace('[attribs]', '[layouts][' . $ext_name . ']', $field->input)
						);

						if ($inh_params)
						{
							$_input = flexicontent_html::getInheritedFieldDisplay($field, $inh_params);
						}
					}
					elseif ($ext_view === 'field')
					{
						$_label = str_replace('jform_attribs_', 'jform_layouts_', $field->label);
						$_input = str_replace('jform_attribs_', 'jform_layouts_',
							str_replace('[attribs]', '[layouts]', $field->input)
						);
					}
					else
					{
						$_label = $field->label;
						$_input = $field->input;
					}

					if ($labelclass)
					{
						$_label = str_replace('class="', 'class="'.$labelclass.' ', $_label);
					}

					// Replace prefix in the parameter names (that is, if it exists)
					$_label = str_replace('PPFX_', $layout_pfx . '_', $_label);
					$_input = str_replace('PPFX_', $layout_pfx . '_', $_input);
					$_field_id = str_replace('PPFX_', $layout_pfx . '_', $field->id);

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
		$load_mode   = $app->input->get('load_mode', '0', 'INT');
		$layout_name = $app->input->get('layout_name', 'default', 'CMD');

		$file_subpath = $app->input->get('file_subpath', '', 'STRING');
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
		$layout_name  = $app->input->get('layout_name', 'default', 'CMD');

		$file_subpath = $app->input->get('file_subpath', '', 'STRING');
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
