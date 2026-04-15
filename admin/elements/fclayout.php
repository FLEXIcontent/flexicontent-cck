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
$doc = Factory::getDocument();
/* J5/J6 WebAsset: */ $doc->getWebAssetManager()->registerAndUseScript('fc-tabber-minimized', Uri::root(true) . '/components/com_flexicontent/assets/js/tabber-minimized.js', ['version' => FLEXI_VHASH]);
/* J5/J6 WebAsset: */ $doc->getWebAssetManager()->registerAndUseStyle('fc-tabber', Uri::root(true) . '/components/com_flexicontent/assets/css/tabber.css', ['version' => FLEXI_VHASH]);
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

		flexicontent_html::loadJQuery();

		if (empty($attributes['skipparams']))
		{
			if ($params_source === 'file')
			{
				$js = "
function fc_getLayout_{$_name}(el, initial)
{
	var bs_tab_handle = jQuery('a[href=\"#attrib-{$tmpl_container}\"]');
	var fc_tab_handle = jQuery('#{$tmpl_container}');

	var panel_header = bs_tab_handle;
	var panel_id     = 'attrib-{$tmpl_container}';
	var panel_sel    = '#'+panel_id;
	var panel        = jQuery('#'+panel_id);

	if (!panel_header.length && fc_tab_handle.length)
	{
		panel_header = fc_tab_handle;
		panel_header.closest('.tabberlive').children('.fc_empty_box,.field-spacer').remove();
		panel     = panel_header.closest('.tabberlive').children().eq( panel_header.parent().index() + 1 );
		panel_id  = panel.attr('id');
		panel_sel = '#' + panel_id;
	}

	if (!panel_header.length) { return; }

	if (initial && jQuery(el).data('fc-layout-first-run')) { return; }
	jQuery(el).data('fc-layout-first-run', 1);

	var selected_option = jQuery(el).find(':selected');
	var filename        = selected_option.data('filename');
	var display_name    = selected_option.data('displayname');
	var layout_name     = filename ? filename.replace(/^({$stripPrefix})/, '') : selected_option.val();

	display_name = display_name ? display_name : layout_name.replace(/^({$trimDisplayname})/, '');
	filename     = filename ? filename : '{$stripPrefix}' + layout_name;

	var _loading_img = '<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\">';
	bs_tab_handle.length
		? panel_header.html('<a href=\"javascript:void(0);\"><span> {$layout_label}: '+_loading_img+'</span></a>')
		: panel_header.html('<i class=\"{$icon_class}\"></i> {$layout_label}: ' + _loading_img);
	panel.html('');

	jQuery.ajax({
		type: 'GET',
		url: 'index.php?option=com_flexicontent&task=templates.getlayoutparams&ext_option={$ext_option}&ext_view={$ext_view}&ext_type={$ext_type}&ext_name={$ext_name}&layout_pfx={$layout_pfx}&ext_id={$pk}&directory={$directory}&layout_sfx={$layout_sfx}&layout_name='+filename+'&format=raw&" . Session::getFormToken() . "=1',
		success: function(str) {
			if (bs_tab_handle.length)
			{
				panel_header.html('<a href=\"javascript:void(0);\"><span> {$layout_label}: '+display_name+'</span></a>');
				panel_header.parent().css('display', '');
			}
			else
			{
				display_name = display_name.replace('_', ' ').replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
				panel_header.html('<i class=\"{$icon_class}\"></i> {$layout_label}: ' + display_name);
			}
			fc_initDynamicLayoutJsCss(panel_id, ['subform-row-add'], str);
		}
	});
}
";
			}
			else
			{
				$layoutsList = implode("','", $layout_files);
				$js          = "
function fc_getLayout_{$_name}(el, initial)
{
	if (initial && jQuery(el).data('fc-layout-first-run')) { return; }
	jQuery(el).data('fc-layout-first-run', 1);

	var container = jQuery('a[href=\"#attrib-{$tmpl_container}\"]');
	if (container) container.parent().css('display', 'none');

	var filename    = jQuery(el).find(':selected').data('filename');
	var layout_name = filename ? filename : el.value;

	var layouts = ['{$layoutsList}'];
	for (var i = 0; i < layouts.length; i++)
	{
		if (layouts[i] == layout_name) continue;
		var c = jQuery('a[href=\"#attrib-{$tmpl_container}_' + layouts[i] + '\"]');
		if (c) { c.parent().css('display', 'none'); }
	}

	var container = jQuery('a[href=\"#attrib-{$tmpl_container}_' + layout_name + '\"]');
	if (container) container.parent().css('display', '');
}
";
			}

			$js .= "
jQuery(document).ready(function(){
	var el = document.getElementById('jform_{$fld_groupname}{$_name}');
	fc_getLayout_{$_name}(el, 1);

	el.addEventListener('initialize-fc-element', function() {
		fc_getLayout_{$_name}(el, 1);
	});
});
";
			// Add inline so it works when changing FLEXIcontent field type
			echo '<script>' . $js . '</script>';
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
		$db = Factory::getDbo();

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
		$lang = Factory::getLanguage();
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