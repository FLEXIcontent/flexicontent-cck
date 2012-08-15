<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
jformhelper::loadFieldClass('usergroup');

/**
 * Form Field class for the Joomla Platform.
 * Supports a nested check box field listing user groups.
 * Multiselect is available by default.
 *
 * @package     Joomla.Platform
 * @subpackage  Form
 * @since       11.1
 */
class JFormFieldFLEXIUsergroup extends JFormFieldUsergroup
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'FLEXIUsergroup';

	/**
	 * Method to get the user group field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   11.1
	 */
	protected function getInput()
	{
		// Initialize variables.
		$extra_options = array();
		$attr = '';
		$allowAll = false;

		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="' . (string) $this->element['class'] . '"' : '';
		$attr .= ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
		$attr .= $this->element['size'] ? ' size="' . (int) $this->element['size'] . '"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';

		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="' . (string) $this->element['onchange'] . '"' : '';

		// Iterate through the children and build an array of options.
		foreach ($this->element->children() as $option)
		{

			// Only add <option /> elements.
			if ($option->getName() != 'option')
			{
				continue;
			}

			// Create a new option object based on the <option /> element.
			$tmp = JHtml::_(
				'select.option', (string) $option['value'], trim((string) $option), 'value', 'text',
				((string) $option['disabled'] == 'true')
			);

			// Set some option attributes.
			$tmp->class = (string) $option['class'];

			// Set some JavaScript option attributes.
			$tmp->onclick = (string) $option['onclick'];

			// Add the option object to the result set.
			$extra_options[] = $tmp;
		}
		$allowAll = ((string)$this->element['allowAll']=='true') ? true : false;

		return JFormFieldFLEXIUsergroup::usergroup($this->name, $this->value, $attr, $allowAll, $extra_options, $this->id);
	}
	
	/**
	 * Displays a list of the available user groups.
	 *
	 * @param   string   $name      The form field name.
	 * @param   string   $selected  The name of the selected section.
	 * @param   string   $attribs   Additional attributes to add to the select field.
	 * @param   boolean  $allowAll  True to add "All Groups" option.
	 *
	 * @return  string   The required HTML for the SELECT tag.
	 *
	 * @see     JFormFieldUsergroup
	 *
	 * @since   11.1
	 */
	public static function usergroup($name, $selected, $attribs = '', $allowAll = true, $extra_options = array())
	{
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level' .
			' FROM #__usergroups AS a' .
			' LEFT JOIN `#__usergroups` AS b ON a.lft > b.lft AND a.rgt < b.rgt' .
			' GROUP BY a.id' .
			' ORDER BY a.lft ASC'
		);
		$options = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum())
		{
			JError::raiseNotice(500, $db->getErrorMsg());
			return null;
		}

		for ($i = 0, $n = count($options); $i < $n; $i++)
		{
			$options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
		}

		// If all usergroups is allowed, push it into the array.
		if ($allowAll)
		{
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_GROUPS')));
		}
		$options = array_merge($extra_options, $options);
		return JHtml::_('select.genericlist', $options, $name, array('list.attr' => $attribs, 'list.select' => $selected));
	}
}
