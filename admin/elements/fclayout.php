<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 *
 * Compatibility: Joomla 4.x, 5.x, 6.x
 */

// J4+ : _JEXEC remplace JPATH_PLATFORM (supprimé en J6)
defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;   // Remplace JFormFieldGroupedList (alias J3 supprimé en J6)
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;               // Remplace JHtml (alias J3 supprimé en J6)
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;                   // Remplace Joomla\CMS\Filesystem\File (déprécié J6, supprimé J7)
use Joomla\Filesystem\Folder;                 // Remplace Joomla\CMS\Filesystem\Folder (déprécié J6, supprimé J7)
use Joomla\Filesystem\Path;
use Joomla\Utilities\ArrayHelper;

// jimport() supprimé en J6 → les use statements ci-dessus suffisent
// DS est obsolète depuis J4, utiliser DIRECTORY_SEPARATOR directement
require_once JPATH_ROOT . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_flexicontent' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'flexicontent.helper.php';

// Load JS tabber lib
$doc = \Joomla\CMS\Factory::getApplication()->getDocument();
$doc->addScript(Uri::root(true) . '/components/com_flexicontent/assets/js/tabber-minimized.js', ['version' => FLEXI_VHASH]);
$doc->addStyleSheet(Uri::root(true) . '/components/com_flexicontent/assets/css/tabber.css', ['version' => FLEXI_VHASH]);
$doc->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');

/**
 * Renders an HTML select list of FLEXIcontent layouts.
 *
 * @package     FLEXIcontent
 * @subpackage  Form
 * @since       1.5
 */
class JFormFieldFclayout extends GroupedlistField
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
	 * Specify the target directory with a directory attribute.
	 * Attributes allow an exclude mask and stripping of extensions from file name.
	 * Default attribute may optionally be set to null (no file) or -1 (use a default).
	 *
	 * @return  string  The field HTML output.
	 *
	 * @since   1.5
	 */
	protected function getInput()
	{
		// Element params
		$node       = $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		// Get value
		$value = $this->value ?: ($attributes['default'] ?? '');

		// Get current extension and id being edited
		$app    = Factory::getApplication();
		$jinput = $app->input;
		$task   = $jinput->get('task', '', 'CMD');

		$ext_option = $jinput->get('option', '', 'CMD');
		$ext_view   = $jinput->get('view', '', 'CMD');

		if (
			$ext_option === 'com_modules' ||
			$ext_option === 'com_advancedmodules' ||
			($ext_option === 'com_falang' && $jinput->get('catid', '', 'CMD') === 'modules')
		)
		{
			$ext_view = 'module';
		}

		// Get RECORD id of current view
		$cid = $jinput->get('cid', [0], 'array');
		$cid = ArrayHelper::toInteger($cid);
		$pk  = (int) $cid[0];

		if (!$pk)
		{
			$pk = $jinput->get('id', 0, 'int');
		}

		// Initialize field attributes
		$filter          = (string) ($attributes['filter']          ?? '');
		$exclude         = (string) ($attributes['exclude']         ?? '');
		$stripExt        = (string) ($attributes['stripext']        ?? '');
		$stripPrefix     = (string) ($attributes['stripprefix']     ?? '');
		$hideNone        = (string) ($attributes['hide_none']       ?? '');
		$hideDefault     = (string) ($attributes['hide_default']    ?? '');
		$class           = (string) ($attributes['class']           ?? '');
		$class           = $class ?: (defined('FLEXI_J40GE') && FLEXI_J40GE ? 'form-select' : '');
		$trimDisplayname = (string) ($attributes['trim_displayname'] ?? '');
		$icon_class      = (string) ($attributes['icon_class']      ?? '') ?: 'icon-palette';
		$icon_class2     = (string) ($attributes['icon_class2']     ?? '');

		$custom_layouts_label = (string) ($attributes['custom_layouts_label'] ?? '');
		$use_default_label    = (string) ($attributes['use_default_label']    ?? '') ?: 'JOPTION_USE_DEFAULT';

		$layout_label = Text::_((string) ($attributes['layout_label'] ?? '') ?: 'FLEXI_LAYOUT');

		// Get the path which contains layouts
		$ext_name  = (string) ($attributes['ext_name']  ?? '');
		$ext_type  = (string) ($attributes['ext_type']  ?? '');
		$directory = (string) ($attributes['directory'] ?? '');
		$path      = is_dir($directory) ? $directory : JPATH_ROOT . $directory;

		$layout_pfx = (string) ($attributes['name']       ?? '');
		$layout_sfx = (string) ($attributes['layout_sfx'] ?? '');

		// For using directory in url
		$directory = str_replace('\\', '/', $directory);

		// ---------------------------------------------------------------
		// Prepare the grouped list
		// ---------------------------------------------------------------
		$groups = [];

		/**
		 * An integer index with no 'id' and 'text' will add the 'items' array
		 * without creating an Option-Group.
		 */
		$grp_index           = $ext_view === 'module' ? '_' : 0;
		$groups[$grp_index]  = [];
		$groups[$grp_index]['items'] = [];

		if ($ext_view === 'module')
		{
			$groups[$grp_index]['id']   = $this->id . '__';
			$groups[$grp_index]['text'] = Text::sprintf('JOPTION_FROM_MODULE');
		}

		// Prepend default options based on field attributes
		$fieldnameSanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname);

		if (!$hideNone)
		{
			$groups[$grp_index]['items'][] = HTMLHelper::_('select.option', '-1', Text::alt('JOPTION_DO_NOT_USE', $fieldnameSanitized));
		}

		if (!$hideDefault)
		{
			$groups[$grp_index]['items'][] = HTMLHelper::_('select.option', '', Text::alt($use_default_label, $fieldnameSanitized));
		}

		// ---------------------------------------------------------------
		// Parse XML children (options and groups)
		// ---------------------------------------------------------------
		$core_layout_names = [];
		$V2L               = [];
		$num_index         = 0;
		$last_was_grp      = false;

		foreach ($this->element->children() as $option)
		{
			$name = $option->getName();

			// If current option is a group, iterate its children; otherwise wrap it
			$children  = ($name === 'group') ? $option->children() : [$option];
			$_options  = [];

			foreach ($children as $sub_option)
			{
				$val  = (string) $sub_option->attributes()->value;
				$text = Text::_((string) $sub_option);

				$attr_arr = [];

				if (isset($sub_option->attributes()->filename))
				{
					$filename                    = (string) $sub_option->attributes()->filename;
					$attr_arr['data-filename']   = $filename;
					$core_layout_names[$filename] = $val;
				}

				if (isset($sub_option->attributes()->displayname))
				{
					$attr_arr['data-displayname'] = Text::_((string) $sub_option->attributes()->displayname);
				}

				$disable = (bool) $sub_option->attributes()->disable;
				$hidden  = (bool) $sub_option->attributes()->hidden;

				if ($hidden)
				{
					continue;
				}

				$_options[]   = (object) ['value' => $val, 'text' => $text, 'disable' => $disable, 'attr' => $attr_arr];
				$V2L[$val]    = $text;
			}

			if ($name === 'group')
			{
				$grp                    = (string) ($option->attributes()->name ?: $option->attributes()->label);
				$groups[$grp]           = [];
				$groups[$grp]['id']     = null;
				$groups[$grp]['text']   = Text::_((string) $option->attributes()->label);
				$groups[$grp]['items']  = $_options;
				$last_was_grp           = true;
			}
			else
			{
				$num_index                      = !$last_was_grp ? $num_index : ($num_index + 1);
				$groups[$num_index]['items'][]  = reset($_options);
				$last_was_grp                   = false;
			}
		}

		// ---------------------------------------------------------------
		// Get a list of files in the search path with the given filter
		// ---------------------------------------------------------------
		$files = Folder::files($path, $filter);
		$files = is_array($files) ? array_flip($files) : [];

		$groups['custom']          = [];
		$groups['custom']['id']    = $this->id . '_custom';
		$groups['custom']['text']  = $ext_view === 'module'
			? Text::sprintf('JOPTION_FROM_MODULE')
			: Text::_($custom_layouts_label ?: 'FLEXI_LAYOUTS');
		$groups['custom']['items'] = [];

		$layout_files = [];

		foreach ($files as $file => $index)
		{
			if ($exclude && preg_match(chr(1) . $exclude . chr(1), $file))
			{
				continue;
			}

			if ($stripExt)
			{
				$file = File::stripExt($file);
			}

			if (isset($core_layout_names[$file]))
			{
				continue;
			}

			$val = $stripPrefix ? str_replace($stripPrefix, '', $file) : $file;

			$groups['custom']['items'][] = (object) ['text' => $val, 'value' => $val];
			$layout_files[]              = $file;
		}

		if (empty($layout_files))
		{
			unset($groups['custom']);
		}

		// For modules, also get custom layouts from templates
		if ($ext_view === 'module')
		{
			$this->getModuleLayoutsFromTemplates($groups, $layout_files);
		}

		// ---------------------------------------------------------------
		// Build the select element
		// ---------------------------------------------------------------
		$_name      = $this->fieldname;
		$fieldname  = $this->name;
		$element_id = $this->id;

		$attribs = '';

		if (!empty($attributes['multiple']) && in_array($attributes['multiple'], ['multiple', 'true'], true))
		{
			$attribs .= ' multiple="multiple" ';
			$attribs .= !empty($attributes['size']) ? ' size="' . $attributes['size'] . '" ' : ' size="6" ';
		}

		$attribs .= ' class="fc-element-auto-init ' . $class . '"';
		$attribs .= ' onchange="fc_getLayout_' . $_name . '(this);"';

		$tmpl_container = (string) ($attributes['tmpl_container'] ?? '');
		$params_source  = (string) ($attributes['params_source']  ?? '');

		// Determine fieldset group name for current view
		switch ($ext_view)
		{
			case 'field':
				$fld_groupname = 'attribs_';
				break;
			case 'type':
				$fld_groupname = in_array($task, ['edit', 'add']) ? 'attribs_' : 'params_';
				break;
			case 'component':
				$fld_groupname = '';
				break;
			default:
				$fld_groupname = 'params_';
				break;
		}

		flexicontent_html::loadFramework('flexi-lib');
		Factory::getApplication()->getDocument()->addScript(Uri::root(true) . '/components/com_flexicontent/assets/js/layout-params.js', array('version' => 'auto'));
		Factory::getApplication()->getDocument()->addScript(Uri::root(true) . '/components/com_flexicontent/assets/js/color-input.js', array('version' => 'auto'), array('defer' => true));
		if (empty($attributes['skipparams']))
		{
			$options = json_encode(array(
				'remote' => $params_source === 'file', 'container' => $tmpl_container,
				'stripPrefix' => $stripPrefix, 'layouts' => $layout_files, 'label' => $layout_label,
				'url' => 'index.php?' . http_build_query(array(
					'option' => 'com_flexicontent', 'task' => 'templates.getlayoutparams',
					'ext_option' => $ext_option, 'ext_view' => $ext_view, 'ext_type' => $ext_type,
					'ext_name' => $ext_name, 'layout_pfx' => $layout_pfx, 'ext_id' => $pk,
					'directory' => $directory, 'layout_sfx' => $layout_sfx, 'format' => 'raw', Session::getFormToken() => 1,
				)),
			), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			$element = json_encode('jform_' . $fld_groupname . $_name);
			echo '<script>function fc_getLayout_' . $_name . '(el, initial) { return fc_loadLayoutParams(el, initial, ' . $options . '); }
			FlexiForm.ready(() => { const el = document.getElementById(' . $element . '); if (!el) return;
			fc_getLayout_' . $_name . '(el, true); el.addEventListener("initialize-fc-element", () => fc_getLayout_' . $_name . '(el, true)); });</script>';
		}

		// Compute the current selected values
		$selected = [$this->value];

		// Create form element
		return HTMLHelper::_(
			'select.groupedlist',
			$groups,
			$fieldname,
			[
				'id'           => $element_id,
				'group.id'     => 'id',
				'list.attr'    => $attribs,
				'list.select'  => $selected,
				'option.attr'  => 'attr',
			]
		);
	}

	/**
	 * Get custom layouts from templates for the current module.
	 * Updates $groups with custom layouts and marks layout overrides as disabled.
	 *
	 * @param   array  $groups        The option groups array (passed by reference).
	 * @param   array  $layout_files  Already-known layout file names.
	 *
	 * @return  void
	 */
	protected function getModuleLayoutsFromTemplates(array &$groups, array $layout_files): void
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		// Resolve client id
		$clientId = $this->element['client_id'];

		if (is_null($clientId) && $this->form instanceof Form)
		{
			$clientId = $this->form->getValue('client_id');
		}

		$clientId = (int) $clientId;
		$client   = ApplicationHelper::getClientInfo($clientId);

		// Resolve module name
		$module = (string) $this->element['module'];

		if (empty($module) && ($this->form instanceof Form))
		{
			$module = $this->form->getValue('module');
		}

		$module = preg_replace('#\W#', '', $module);

		// Resolve template and style
		$template = preg_replace('#\W#', '', (string) $this->element['template']);

		$template_style_id = ($this->form instanceof Form)
			? preg_replace('#\W#', '', (string) $this->form->getValue('template_style_id'))
			: '';

		// Build the query for enabled templates
		$query = $db->getQuery(true)
			->select('element, name')
			->from($db->quoteName('#__extensions', 'e'))
			->where('e.client_id = ' . $clientId)
			->where('e.type = ' . $db->quote('template'))
			->where('e.enabled = 1');

		if ($template)
		{
			$query->where('e.element = ' . $db->quote($template));
		}

		if ($template_style_id)
		{
			$query->join('LEFT', $db->quoteName('#__template_styles', 's') . ' ON s.template = e.element')
				->where('s.id = ' . (int) $template_style_id);
		}

		$templates = $db->setQuery($query)->loadObjectList('element');

		// Load sys language file for the current module
		$lang = \Joomla\CMS\Factory::getApplication()->getLanguage();
		$lang->load($module . '.sys', $client->path, null, false, true)
			|| $lang->load($module . '.sys', $client->path . '/modules/' . $module, null, false, true);

		foreach ($templates as $template)
		{
			$lang->load('tpl_' . $template->element . '.sys', $client->path, null, false, true)
				|| $lang->load('tpl_' . $template->element . '.sys', $client->path . '/templates/' . $template->element, null, false, true);

			$template_path = Path::clean($client->path . '/templates/' . $template->element . '/html/' . $module);

			if (!is_dir($template_path))
			{
				continue;
			}

			$files = Folder::files($template_path, '^[^_]*\.php$');

			if (empty($files))
			{
				continue;
			}

			$is_override      = [];
			$display_overrides = true;

			foreach ($files as $i => $file)
			{
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

			if (empty($files))
			{
				continue;
			}

			$groups[$template->element]           = [];
			$groups[$template->element]['id']     = $this->id . '_' . $template->element;
			$groups[$template->element]['text']   = Text::sprintf('JOPTION_FROM_TEMPLATE', $template->name);
			$groups[$template->element]['items']  = [];

			foreach ($files as $i => $file)
			{
				$value = basename($file, '.php');
				$key   = strtoupper('TPL_' . $template->element . '_' . $module . '_LAYOUT_' . $value);
				$text  = $lang->hasKey($key) ? Text::_($key) : $value;

				$groups[$template->element]['items'][] = (object) [
					'value'   => $template->element . ':' . $value,
					'text'    => $text . (!empty($is_override[$i]) ? '  -- overrides builtin automatically' : ''),
					'disable' => !empty($is_override[$i]),
					'attr'    => [],
				];
			}
		}
	}
}
