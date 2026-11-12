<?php
defined('_JEXEC') or die('Restricted access');

/** Shared presentation and download-name rules for the FLEXIcontent file editor. */
class flexicontent_fileeditor
{
	const FIELDS = array('download_filename', 'title', 'description', 'replace', 'keep_uploaded_date', 'keep_uploaded_by', 'language', 'access', 'stamp', 'hits', 'type', 'size', 'storage', 'path');

	static function visible($params, $field)
	{
		return (int) $params->get('file_editor_show_' . $field, $field === 'path' ? 0 : 1) === 1;
	}

	/**
	 * Get configuration of the 2 custom (HTML) file properties, stored in files table columns custom1, custom2
	 *
	 * - With the parameters of a File field: the settings of that field (params custom1_*, custom2_*)
	 * - Without parameters: the global settings of the component configuration (File editor tab, params file_custom1_* ...)
	 *   used by the file manager forms, when there is no File field context
	 *
	 * @param   \Joomla\Registry\Registry|null  $params     Parameters of a File field, or null for the global settings
	 * @param   int|null                        $inputmode  File field only: inline file properties editing (0: enabled, 1: disabled), null: read from params
	 *
	 * @return  array  Only enabled properties, array keyed by 1, 2, ('form' => editable in the inline form of the item)
	 */
	static function customProps($params = null, $inputmode = null)
	{
		if ($params === null)
		{
			$params = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
			$pfx = 'file_custom';
			$inputmode = 0;
		}
		else
		{
			$pfx = 'custom';
			$inputmode = $inputmode === null ? (int) $params->get('inputmode', 1) : (int) $inputmode;
		}

		$props = array();
		foreach (array(1, 2) as $n)
		{
			if (!(int) $params->get($pfx . $n . '_enable', 0))
			{
				continue;
			}

			$label = trim((string) $params->get($pfx . $n . '_label', ''));

			$props[$n] = array(
				'form'        => $inputmode !== 1,
				'label'       => $label === '' ? '' : \Joomla\CMS\Language\Text::_($label),  // language string key or plain text
				'show_label'  => $label !== '' && (int) $params->get($pfx . $n . '_label_show', 1),
				'default'     => (string) $params->get($pfx . $n . '_default', ''),
				'default_use' => (int) $params->get($pfx . $n . '_default_use', 0),
				'rows'        => max(1, min(30, (int) $params->get($pfx . $n . '_rows', 2))),  // textarea height
			);
		}

		return $props;
	}

	/** Settings of the File field with the given id, or null if there is no such File field */
	static function customPropsForFieldId($fieldid)
	{
		$fieldid = (int) $fieldid;
		if (!$fieldid) return null;

		$db = \Joomla\CMS\Factory::getDbo();
		$attribs = $db->setQuery($db->getQuery(true)
			->select('attribs')->from('#__flexicontent_fields')
			->where('id = ' . $fieldid)->where('field_type = ' . $db->quote('file'))
		)->loadResult();

		return $attribs === null ? null : self::customProps(new \Joomla\Registry\Registry($attribs), 0);
	}

	/** Properties enabled in any File field (first File field enabling a property provides its label / default), used as fallback */
	static function customPropsFromAnyField()
	{
		static $props = null;
		if ($props !== null) return $props;

		$props = array();
		$db = \Joomla\CMS\Factory::getDbo();
		$rows = $db->setQuery($db->getQuery(true)
			->select('attribs')->from('#__flexicontent_fields')
			->where('field_type = ' . $db->quote('file'))->order('id')
		)->loadColumn();

		foreach ((array) $rows as $attribs)
		{
			foreach (self::customProps(new \Joomla\Registry\Registry($attribs), 0) as $n => $cp)
			{
				if (!isset($props[$n])) $props[$n] = $cp;
			}
		}

		return $props;
	}

	/**
	 * Properties to use in the file editor / file manager forms:
	 * - settings of the File field that opened the form (fieldid / field request variable)
	 * - otherwise (e.g. the file manager) the global settings of the component configuration, and for any property that
	 *   is not enabled there, the settings of the first File field that enables it
	 */
	static function contextProps($input = null)
	{
		$input = $input ?: \Joomla\CMS\Factory::getApplication()->input;
		$props = self::customPropsForFieldId($input->getInt('fieldid', 0) ?: $input->getInt('field', 0));
		if ($props !== null) return $props;

		$props = self::customProps();
		foreach (self::customPropsFromAnyField() as $n => $cp)
		{
			if (!isset($props[$n])) $props[$n] = $cp;
		}
		ksort($props);
		return $props;
	}

	/** Filter posted custom property values (HTML allowed, per Joomla Text Filters), returns values of enabled properties only */
	static function filterCustomPost($input)
	{
		$values = array();
		foreach (self::contextProps($input) as $n => $cp)
		{
			$values['custom' . $n] = \Joomla\CMS\Component\ComponentHelper::filterText((string) $input->get('custom' . $n, '', 'raw'));
		}
		return $values;
	}

	/** Render the form rows (label cell + textarea cell) of the enabled custom properties */
	static function renderCustomRows($file_data, $atum_control = '', $rows = 3)
	{
		$html = '';
		foreach (self::contextProps() as $n => $cp)
		{
			$cv = self::customFormValue($cp, $n, $file_data);
			$html .= '
				<tr class="fc-file-custom' . $n . '-row">
					<td class="key"><label class="fc-prop-lbl" for="file-custom' . $n . '-' . (int) self::$uid . '">' . ($cp['show_label'] ? htmlspecialchars($cp['label'], ENT_QUOTES, 'UTF-8') : '') . '</label></td>
					<td><textarea name="custom' . $n . '" cols="24" rows="' . (int) $cp['rows'] . '" id="file-custom' . $n . '-' . (int) self::$uid . '" class="input-xxlarge' . $atum_control . '">' . htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') . '</textarea></td>
				</tr>';
		}
		self::$uid++;
		return $html;
	}

	protected static $uid = 0;

	/** Value of a custom property to be used in a form, applying the default value according to configuration */
	static function customFormValue(array $cp, $n, $file_data, $form_row = null)
	{
		// Posted value (e.g. form re-displayed after validation failure)
		if (is_array($form_row) && isset($form_row['custom' . $n]))
		{
			return (string) $form_row['custom' . $n];
		}

		$value = isset($file_data->{'custom' . $n}) ? (string) $file_data->{'custom' . $n} : '';

		// Default is always loaded for a new (empty) file row, and for existing files with empty value if configured so
		if (!strlen(trim($value)) && ($cp['default_use'] >= 1 || empty($file_data->id)))
		{
			$value = $cp['default'];
		}

		return $value;
	}

	/** Value of a custom property to be displayed on the site, empty string means "do not display" */
	static function customDisplayValue(array $cp, $n, $file_data)
	{
		$keep = '<img><iframe><video><audio><svg>';
		$value = isset($file_data->{'custom' . $n}) ? (string) $file_data->{'custom' . $n} : '';

		if (!strlen(trim(strip_tags($value, $keep))) && $cp['default_use'] >= 2)
		{
			$value = $cp['default'];
		}

		return strlen(trim(strip_tags($value, $keep))) ? $value : '';
	}

	static function extension($stored)
	{
		$source = preg_match('#^https?://#i', $stored) ? (string) parse_url($stored, PHP_URL_PATH) : $stored;
		return strtolower(pathinfo(str_replace('\\', '/', $source), PATHINFO_EXTENSION));
	}

	/** Keep the requested name, but derive its suffix from the stored file or validated URL. */
	static function downloadName($requested, $stored)
	{
		$source = preg_match('#^https?://#i', $stored) ? (string) parse_url($stored, PHP_URL_PATH) : $stored;
		$fallback = pathinfo(basename(str_replace('\\', '/', $source)), PATHINFO_FILENAME);
		$name = basename(str_replace('\\', '/', preg_replace('/[\x00-\x1f\x7f]/', '', strip_tags((string) $requested))));
		$stem = trim(pathinfo(trim($name), PATHINFO_FILENAME), '. ');
		if ($stem === '') $stem = trim($fallback, '. ');
		$extension = self::extension($stored);
		return $stem . ($extension !== '' ? '.' . $extension : '');
	}
}
