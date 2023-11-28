<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('joomla.filesystem.folder');  // JFolder
jimport('joomla.filesystem.file');    // JFile

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('groupedlist');   // JFormFieldGroupedList

// Load JS tabber lib
JFactory::getDocument()->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

/**
 * Renders an HTML select list of FLEXIcontent layouts
 *
 * @package     FLEXIcontent
 * @subpackage  Form
 * @since       1.5
 */
class JFormFieldFclayout extends JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.5
	 */
	public $type = 'Fclayout';

	/**
	 * Method to get the list of files for the field options.
	 * Specify the target directory with a directory attribute
	 * Attributes allow an exclude mask and stripping of extensions from file name.
	 * Default attribute may optionally be set to null (no file) or -1 (use a default).
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   1.5
	 */
	protected function getInput()
	{
		// Element params
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		// Get value
		$value = $this->value;
		$value = $value ? $value : $attributes['default'];

		// Get current extension and id being edited
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$task   = $jinput->get('task', '', 'CMD');

		$ext_option = $jinput->get('option', '', 'CMD');
		$ext_view   = $jinput->get('view', '', 'CMD');

		if (
			$ext_option == 'com_modules' ||
			$ext_option == 'com_advancedmodules' ||
			($ext_option == 'com_falang' && $jinput->get('catid', '', 'CMD')=='modules')
		) $ext_view = 'module';

		// Get RECORD id of current view
		$cid = $jinput->get('cid', array(0), 'array');
		$cid = ArrayHelper::toInteger($cid);
		$pk = (int) $cid[0];
		if (!$pk) $pk = $jinput->get('id', 0, 'int');


		// Initialize variables.
		//$options = array();

		// Initialize some field attributes.
		$filter   = (string) @ $attributes['filter'];
		$exclude  = (string) @ $attributes['exclude'];
		$stripExt = (string) @ $attributes['stripext'];
		$stripPrefix = (string) @ $attributes['stripprefix'];
		$hideNone    = (string) @ $attributes['hide_none'];
		$hideDefault = (string) @ $attributes['hide_default'];
		$class       = (string) @ $attributes['class'];
		$class       = $class ?: (FLEXI_J40GE ? 'form-select' : '');

		// e.g. 'gallery_' in image-gallery field (e.g. layout filename: value_gallery_multibox.php)
		$trimDisplayname = (string) @ $attributes['trim_displayname'];

		$icon_class = (string) @ $attributes['icon_class'];
		$icon_class = $icon_class ?: 'icon-palette';

		$icon_class2 = (string) @ $attributes['icon_class2'];

		$custom_layouts_label  = (string) @ $attributes['custom_layouts_label'];
		$use_default_label  = (string) @ $attributes['use_default_label'];
		$use_default_label  = $use_default_label ?: 'JOPTION_USE_DEFAULT';

		$layout_label = (string) @ $attributes['layout_label'];
		$layout_label = JText::_($layout_label ?: 'FLEXI_LAYOUT');

		// Get the path which contains layouts
		$ext_name   = (string) @ $attributes['ext_name'];
		$ext_type   = (string) @ $attributes['ext_type'];
		$directory  = (string) @ $attributes['directory'];
		$path = @is_dir($directory)  ?  $directory  :  JPATH_ROOT . $directory;

		/**
		 * A prefix and/or a suffix to distinguish multiple loading of same layout in the same page
		 * (This is typically the name of the layout parameter)
		 * Typically the layouts will either use prefix 'PPFX_' or suffix '_PSFX', e.g. to distiguish between
		 * 'desktop_' and 'mobile_' or '_fe' and '_be' for (frontend and backend)
		 */
		$layout_pfx = (string) @ $attributes['name']; //
		$layout_sfx = (string) @ $attributes['layout_sfx'];

		// For using directory in url
		$directory = str_replace('\\', '/', $directory);

		// Prepare the grouped list
		$groups = array();

		/**
		 * An integer index with no 'id' and 'text' will add the 'items' array without creating an Option-Group
		 */
		$grp_index = $ext_view === 'module' ? '_' : 0;
		$groups[$grp_index] = array();
		$groups[$grp_index]['items'] = array();

		// Adding these will create an option group
		if ($ext_view === 'module')
		{
			$groups[$grp_index]['id'] = $this->id . '__';
			$groups[$grp_index]['text'] = $ext_view === 'module' ? JText::sprintf('JOPTION_FROM_MODULE') : '';
		}

		// Prepend some default options based on field attributes.
		if (!$hideNone)   $groups[$grp_index]['items'][] = JHtml::_('select.option', '-1', JText::alt('JOPTION_DO_NOT_USE', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
		if (!$hideDefault) $groups[$grp_index]['items'][] = JHtml::_('select.option', '', JText::alt($use_default_label, preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));

		// ***
		// *** Get any additional options in the XML definition.
		// ***

		/*
		$options = parent::getOptions();
		if (count($options)>0)
		{
			$groups['extended'] = array();
			$groups['extended']['id'] = $this->id . '_extended';
			$groups['extended']['text'] =  JText::_('Built-in');
			$groups['extended']['items'] = $options;
		}
		*/

		$core_layout_names = array();

		$num_index = 0;
		$last_was_grp = false;
		foreach ($this->element->children() as $option)
		{
			$name = $option->getName();   //echo 'Name: ' . $name . '<pre>' . print_r($option, true) .'</pre>'; exit;

			// If current option is group then iterrate through its children, otherwise create single value array
			$children = $name=="group"
				? $option->children()
				: array( & $option );

			$_options = array();
			foreach ($children as $sub_option)
			{
				$val  = (string) $sub_option->attributes()->value;
				$text = JText::_( (string) $sub_option );
				$attr_arr = array();

				// When filename cannot be calculated from 'value' e.g. value is an integer (legacy parameter value)
				if (isset($sub_option->attributes()->filename))
				{
					$filename = (string) $sub_option->attributes()->filename;
					$attr_arr['data-filename']  = $filename;
					$core_layout_names[$filename] = $val;
				}

				// Custom displayname, displayname will not be calculated from 'filename' or 'value'
				if (isset($sub_option->attributes()->displayname))
				{
					$displayname = (string) $sub_option->attributes()->displayname;
					$attr_arr['data-displayname']  = JText::_($displayname);
				}

				$disable = $sub_option->attributes()->disable ? true : false;
				$hidden = $sub_option->attributes()->hidden ? true : false;
				if ($hidden)
				{
					continue;
				}

				$_options[] = (object) array(
					'value' => $val,
					'text'  => $text,
					'disable' => $disable,
					'attr'  => $attr_arr
				);
				$V2L[$val] = $text;
				//print_r($attr_arr);
			}

			// Check for current option is a GROUP
			if ($name=="group")
			{
				$grp = $option->attributes()->name ?: $option->attributes()->label;
				$grp = (string) $grp;
				$groups[$grp] = array();
				$groups[$grp]['id'] = null;
				$groups[$grp]['text'] = JText::_($option->attributes()->label);
				$groups[$grp]['items'] = $_options;
				$last_was_grp = true;
			}
			else
			{
				$num_index = !$last_was_grp ? $num_index : ($num_index + 1);
				$groups[$num_index]['items'][] = reset($_options);
				$last_was_grp = false;
			}
		}


		/**
		 * Get a list of files in the search path with the given filter.
		 */

		$files = JFolder::files($path, $filter);
		$files = is_array($files) ? $files : array();
		$files = array_flip($files);

		// Build the options list from the list of files.
		$groups['custom'] = array();
		$groups['custom']['id'] = $this->id . '_custom';
		$groups['custom']['text'] = $ext_view === 'module'
			? JText::sprintf('JOPTION_FROM_MODULE')
			: JText::_($custom_layouts_label ?: 'FLEXI_LAYOUTS');
		$groups['custom']['items'] = array();

		$layout_files = array();
		foreach ($files as $file => $index)
		{
			// Check to see if the file is in the exclude mask.
			if ( $exclude && preg_match(chr(1) . $exclude . chr(1), $file) )
			{
				continue;
			}

			// If the extension is to be stripped, do it.
			if ($stripExt)
			{
				$file = JFile::stripExt($file);
			}

			if (isset($core_layout_names[$file]))
			{
				continue;
			}

			$val = $stripPrefix ? str_replace($stripPrefix, '', $file) : $file;
			$txt = $val;

			$groups['custom']['items'][] = (object) array('text' => $txt, 'value' => $val);
			$layout_files[] = $file;

		}

		// If none non core files were found then remove the group
		if (!count($layout_files))
		{
			unset($groups['custom']);
		}


		/**
		 * Case of module, also get custom layouts from templates
		 * Note: layout overrides are excluded from being listed
		 */
		if ($ext_view === 'module')
		{
			$this->getModuleLayoutsFromTemplates($groups, $layout_files);
		}


		// Element name and id
		$_name	= $this->fieldname;
		$fieldname	= $this->name;
		$element_id = $this->id;

		// Add tag attributes
		$attribs = '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' )
		{
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
		}
		$attribs .= ' class="fc-element-auto-init '.$class.'"';
		$attribs .= ' onchange="fc_getLayout_'.$_name.'(this);"';


		// Container of parameters
		$tmpl_container = (string) @ $attributes['tmpl_container'];

		/**
		 * Add JS code to display parameters, either via 'file' or 'inline'
		 * 'file' means make an AJAX call to read the respective XML file, then set the the target in the same container (TAB)
		 * 'inline' means parameters of all layout were specified inside the main XML, thus just hide all containers (TABs) and display only the appropriate one
		 */

		$params_source = (string) @ $attributes['params_source'];


		/**
		 * Find the fieldset group of current form
		 */
		switch ($ext_view)
		{
			case 'field': $fld_groupname = 'attribs_'; break;
			case 'type' : $fld_groupname = in_array($task, array('edit', 'add')) ? 'attribs_' : 'params_'; break;
			case 'component': $fld_groupname = ''; break;
			default: $fld_groupname = 'params_'; break;
		}

flexicontent_html::loadJQuery();

if ( ! @$attributes['skipparams'] )
{
		$doc 	= JFactory::getDocument();
		$js 	= "

".($params_source=="file" ? "

function fc_getLayout_".$_name."(el, initial)
{
 	var bs_tab_handle = jQuery('a[href=\"#attrib-".$tmpl_container."\"]');
 	var fc_tab_handle = jQuery('#".$tmpl_container."');

	// First try Joomla bootstrap TAB handle with 'attrib-' prefix for its ID
	var panel_header = bs_tab_handle;
	var panel_id     = 'attrib-".$tmpl_container."';
	var panel_sel    = '#'+panel_id;
	var panel        = jQuery('#'+panel_id);

	// Second try FC TAB handle
	if (!panel_header.length && fc_tab_handle.length)
	{
		panel_header = fc_tab_handle;

		// Remove empty box, to allow index number to match correct Tab container
		panel_header.closest('.tabberlive').children('.fc_empty_box,.field-spacer').remove();

		panel     = panel_header.closest('.tabberlive').children().eq( panel_header.parent().index() + 1 );
		panel_id  = panel.attr('id');
		panel_sel = '#' + panel_id;
	}

	if (!panel_header.length)
	{
		//alert('Layout container: ".$tmpl_container." not found');
		return;
	}

	// Panel found, abort if this should only run once
 	if (initial && jQuery(el).data('fc-layout-first-run'))
 	{
 		return;
 	}
 	jQuery(el).data('fc-layout-first-run', 1);

	var selected_option = jQuery(el).find(':selected');
	var filename        = selected_option.data('filename');
	var display_name    = selected_option.data('displayname');
	var layout_name     = filename ? filename.replace(/^(".$stripPrefix.")/, '') : selected_option.val();

	// Trim '$trimDisplayname' if not using custom display name
	display_name = display_name ? display_name : layout_name.replace(/^(".$trimDisplayname.")/, '');

	// Construct filename by prefixing value wiht '$stripPrefix'
	filename     = filename ? filename : '".$stripPrefix."' + layout_name;

	var _loading_img = '<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\">';
	bs_tab_handle.length
		? panel_header.html('<a href=\"javascript:void(0);\"><span> " . $layout_label . ": '+_loading_img+'</span></a>')
		: panel_header.html('" . '<i class="'.$icon_class.'"></i> ' . $layout_label . ": " . "' + _loading_img);
	panel.html('');

	jQuery.ajax({
		type: 'GET',
		url: 'index.php?option=com_flexicontent&task=templates.getlayoutparams&ext_option=".$ext_option."&ext_view=".$ext_view."&ext_type=".$ext_type."&ext_name=".$ext_name."&layout_pfx=".$layout_pfx."&ext_id=".$pk."&directory=".$directory."&layout_sfx=".$layout_sfx."&layout_name='+filename+'&format=raw&" . JSession::getFormToken() . "=1',
		success: function(str) {
			if (bs_tab_handle.length)
			{
				panel_header.html('<a href=\"javascript:void(0);\"><span> " . $layout_label . ": '+display_name+'</span></a>');
				panel_header.parent().css('display', '');
			}
			else
			{
				display_name = display_name.replace('_', ' ').replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
				panel_header.html('" . '<i class="'.$icon_class.'"></i> ' . $layout_label . ": " . "' + display_name);
		 	}

			// Initialize JS and CSS of the layout
			fc_initDynamicLayoutJsCss(panel_id, ['subform-row-add'], str);
		}
	});
}

":"

function fc_getLayout_".$_name."(el, initial)
{
 	if (initial && jQuery(el).data('fc-layout-first-run'))
 	{
 		return;
 	}
 	jQuery(el).data('fc-layout-first-run', 1);

  // *** Hide default container
 	var container = jQuery('a[href=\"#attrib-".$tmpl_container."\"]');
 	if (container) container.parent().css('display', 'none');

	var filename = jQuery(el).find(':selected').data('filename');
	var layout_name = filename ? filename : el.value;

  // *** Hide ALL containers
	var layouts = new Array('".implode("','", $layout_files)."');
	for (i=0; i<layouts.length; i++)
	{
		if (layouts[i] == layout_name) continue;

  	var container = jQuery('a[href=\"#attrib-".$tmpl_container."_' + layouts[i] + '\"]');
  	if (container) {container.parent().css('display', 'none');}
  }

	// *** Show current container
	var container = jQuery('a[href=\"#attrib-".$tmpl_container."_' + layout_name + '\"]');
	if (container) container.parent().css('display', '');
}

")."

jQuery(document).ready(function(){
	var el = document.getElementById('jform_".$fld_groupname.$_name."');
	fc_getLayout_".$_name."(el, 1);

	// In case on DOM ready the element is intialized, retry on this custom event
	el.addEventListener('initialize-fc-element', function() {
		fc_getLayout_".$_name."(el, 1);
	});
});

";
		echo '<script>' . $js . '</script>';  // Add JS inline so that it works when changing flexicontent field type too
		//$doc->addScriptDeclaration($js);
}
		// Compute the current selected values
		$selected = array($this->value);

		// Create form element
		return JHtml::_('select.groupedlist', $groups, $fieldname,
			array(
				'id' =>  $element_id,
				'group.id' => 'id',
				'list.attr' => $attribs,
				'list.select' => $selected,
				'option.attr' => 'attr'  // need to set the name we use for options attributes
			)
		);
	}



	/**
	 * Get custom layouts from templates for current module
	 * Update $groups with the custom layouts and list layout overrides as disabled
	 *
	 * param   $groups         array
	 * param   $layout_files   array
	 *
	 * @return  array  The updated groups of options
	 */
	protected function getModuleLayoutsFromTemplates(& $groups, $layout_files)
	{
		$db = JFactory::getDbo();

		// Get the client id.
		$clientId = $this->element['client_id'];

		if (is_null($clientId) && $this->form instanceof JForm)
		{
			$clientId = $this->form->getValue('client_id');
		}
		$clientId = (int) $clientId;

		$client = JApplicationHelper::getClientInfo($clientId);

		// Get the module.
		$module = (string) $this->element['module'];

		if (empty($module) && ($this->form instanceof JForm))
		{
			$module = $this->form->getValue('module');
		}

		$module = preg_replace('#\W#', '', $module);

		// Get the template.
		$template = (string) $this->element['template'];
		$template = preg_replace('#\W#', '', $template);

		// Get the style.
		if ($this->form instanceof JForm)
		{
			$template_style_id = $this->form->getValue('template_style_id');
		}

		$template_style_id = preg_replace('#\W#', '', $template_style_id ?? '');

		// Build the query.
		$query = $db->getQuery(true)
			->select('element, name')
			->from('#__extensions as e')
			->where('e.client_id = ' . (int) $clientId)
			->where('e.type = ' . $db->quote('template'))
			->where('e.enabled = 1');

		if ($template)
		{
			$query->where('e.element = ' . $db->quote($template));
		}

		if ($template_style_id)
		{
			$query
				->join('LEFT', '#__template_styles as s on s.template=e.element')
				->where('s.id=' . (int) $template_style_id);
		}

		// Set the query and load the templates.
		$templates = $db->setQuery($query)->loadObjectList('element');

		// Load 'sys' language file of current module
		$lang = JFactory::getLanguage();
		$lang->load($module . '.sys', $client->path, null, false, true)
			|| $lang->load($module . '.sys', $client->path . '/modules/' . $module, null, false, true);

		// Loop on all templates
		foreach ($templates as $template)
		{
			// Load language file
			$lang->load('tpl_' . $template->element . '.sys', $client->path, null, false, true)
				|| $lang->load('tpl_' . $template->element . '.sys', $client->path . '/templates/' . $template->element, null, false, true);

			$template_path = JPath::clean($client->path . '/templates/' . $template->element . '/html/' . $module);

			// Add the layout options from the template path.
			if (is_dir($template_path) && ($files = JFolder::files($template_path, '^[^_]*\.php$')))
			{
				$is_override = array();
				$display_overrides = true;

				foreach ($files as $i => $file)
				{
					// Remove layout that already exist in component ones
					if (in_array(basename($file, '.php'), $layout_files))
					{
						if ($display_overrides)
						{
							$is_override[$i] = true;
							continue;
						}
					 unset($files[$i]);
					}
				}

				if (count($files))
				{
					// Create the group for the template
					$groups[$template->element] = array();
					$groups[$template->element]['id'] = $this->id . '_' . $template->element;
					$groups[$template->element]['text'] = JText::sprintf('JOPTION_FROM_TEMPLATE', $template->name);
					$groups[$template->element]['items'] = array();

					foreach ($files as $i => $file)
					{
						// Add an option to the template group
						$value = basename($file, '.php');
						$text = $lang->hasKey($key = strtoupper('TPL_' . $template->element . '_' . $module . '_LAYOUT_' . $value))
							? JText::_($key)
							: $value;

						//$groups[$template->element]['items'][] = JHtml::_('select.option', $template->element . ':' . $value, $text);
						$groups[$template->element]['items'][] = (object) array(
							'value' => $template->element . ':' . $value,
							'text'  => $text . (!empty($is_override[$i]) ? '  -- overrides builtin automatically' : ''),
							'disable' => !empty($is_override[$i]),
							'attr'  => array()
						);
					}
				}
			}
		}
	}

}
