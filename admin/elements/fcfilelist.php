<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

jimport('joomla.filesystem.folder');  // JFolder
jimport('joomla.filesystem.file');    // JFile

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Supports an HTML select list of files
 *
 * @since  11.1
 */
class JFormFieldFcFileList extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'FcFileList';

	/**
	 * The filter.
	 *
	 * @var    string
	 * @since  3.2
	 */
	protected $filter;

	/**
	 * The exclude.
	 *
	 * @var    string
	 * @since  3.2
	 */
	protected $exclude;

	/**
	 * The hideNone.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $hideNone = false;

	/**
	 * The hideDefault.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $hideDefault = false;
	
	/**
	 * The defaultLabel.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $defaultLabel = false;
	
	/**
	 * The stripExt.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $stripExt = false;

	/**
	 * The stripPrefix.
	 *
	 * @var    boolean
	 * @since  3.2
	 */
	protected $stripPrefix = false;

	/**
	 * The directory.
	 *
	 * @var    string
	 * @since  3.2
	 */
	protected $directory;

	/**
	 * Method to get certain otherwise inaccessible properties from the form field object.
	 *
	 * @param   string  $name  The property name for which to the the value.
	 *
	 * @return  mixed  The property value or null.
	 *
	 * @since   3.2
	 */
	public function __get($name)
	{
		switch ($name)
		{
			case 'filter':
			case 'exclude':
			case 'hideNone':
			case 'hideDefault':
			case 'defaultLabel':
			case 'stripPrefix':
			case 'stripExt':
			case 'directory':
				return $this->$name;
		}

		return parent::__get($name);
	}

	/**
	 * Method to set certain otherwise inaccessible properties of the form field object.
	 *
	 * @param   string  $name   The property name for which to the the value.
	 * @param   mixed   $value  The value of the property.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	public function __set($name, $value)
	{
		switch ($name)
		{
			case 'filter':
			case 'directory':
			case 'exclude':
				$this->$name = (string) $value;
				break;

			case 'hideNone':
			case 'hideDefault':
			case 'defaultLabel':
			case 'stripPrefix':
			case 'stripExt':
				$value = (string) $value;
				$this->$name = ($value === 'true' || $value === $name || $value === '1');
				break;

			default:
				parent::__set($name, $value);
		}
	}

	/**
	 * Method to attach a JForm object to the field.
	 *
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the <field /> tag for the form field object.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   string            $group    The field name group control value. This acts as as an array container for the field.
	 *                                      For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                      full field name would end up being "bar[foo]".
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     JFormField::setup()
	 * @since   3.2
	 */
	public function setup(SimpleXMLElement $element, $value, $group = null)
	{
		$return = parent::setup($element, $value, $group);

		if ($return)
		{
			$this->filter  = (string) $this->element['filter'];
			$this->exclude = (string) $this->element['exclude'];
			$this->stripPrefix = (string) $this->element['stripprefix'];
			$this->defaultLabel = (string) $this->element['defaultlabel'];
			
			$hideNone       = (string) $this->element['hide_none'];
			$this->hideNone = ($hideNone == 'true' || $hideNone == 'hideNone' || $hideNone == '1');

			$hideDefault       = (string) $this->element['hide_default'];
			$this->hideDefault = ($hideDefault == 'true' || $hideDefault == 'hideDefault' || $hideDefault == '1');
			
			$stripExt       = (string) $this->element['stripext'];
			$this->stripExt = ($stripExt == 'true' || $stripExt == 'stripExt' || $stripExt == '1');

			// Get the path in which to search for file options.
			$this->directory = (string) $this->element['directory'];
		}

		return $return;
	}

	/**
	 * Method to get the list of files for the field options.
	 * Specify the target directory with a directory attribute
	 * Attributes allow an exclude mask and stripping of extensions from file name.
	 * Default attribute may optionally be set to null (no file) or -1 (use a default).
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   11.1
	 */
	protected function getOptions()
	{
		$options = array();

		$path = $this->directory;

		if (!is_dir($path))
		{
			$path = JPATH_ROOT . '/' . $path;
		}

		// Prepend some default options based on field attributes.
		if (!$this->hideNone)
		{
			$options[] = JHtml::_('select.option', '-1', JText::alt('JOPTION_DO_NOT_USE', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
		}

		if (!$this->hideDefault)
		{
			$options[] = JHtml::_('select.option', '', ($this->defaultLabel ? JText::_($this->defaultLabel) : JText::alt('JOPTION_USE_DEFAULT', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname))));
		}

		// Get a list of files in the search path with the given filter.
		$files = JFolder::files($path, $this->filter);

		// Build the options list from the list of files.
		if (is_array($files))
		{
			foreach ($files as $file)
			{
				// Check to see if the file is in the exclude mask.
				if ($this->exclude)
				{
					if (preg_match(chr(1) . $this->exclude . chr(1), $file))
					{
						continue;
					}
				}

				$text = $value = $file;
				
				// If the extension is to be stripped, do it.
				if ($this->stripPrefix)
				{
					$text = $value = str_replace($this->stripPrefix, '', $value);
				}
				
				// If the extension is to be stripped, do it.
				if ($this->stripExt)
				{
					$text = $value = JFile::stripExt($value);
				}

				// Handle case of (default) file name being exact the stripPrefix
				if ($this->stripPrefix && $text == rtrim($this->stripPrefix, '_'))
				{
					$text = '- ' . JText::_('FLEXI_DEFAULT') . ' -';
				}

				$options[] = JHtml::_('select.option', $value, $text);
			}
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}
