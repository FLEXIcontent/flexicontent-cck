<?php
/**
 * @version		$Id$
 * @package		Joomla.Framework
 * @subpackage	Form
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');

/**
 * Supports an HTML select list of plugins
 *
 * @package		Joomla.Administrator
 * @subpackage	com_newsfeeds
 * @since		1.6
 */
class JFormFieldFieldordering extends JFormField{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Ordering';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 * @since	1.6
	 */
	protected function getInput() {
		// Initialize variables.
		$attr  = '';
		$html = array();
		
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';
		$attr .= ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
		$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';

		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';
		
		$db = JFactory::getDbo();
		
		// Build the query for the ordering list.
		$query = 'SELECT ordering AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published >= 0'
		. ' ORDER BY ordering'
		;
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		$fieldid = $this->form->getValue('id');
		
		// Create a read-only list (no name) with a hidden input to store the value.
		if ( (string) $this->element['readonly'] == 'true' ) {
			$attr .= ' disabled="disabled" ';
			$ordering = $this->form->getValue('ordering');
			$html[] = str_replace('jform'.$attributes['name'], $element_id, JHtml::_('list.ordering', $this->name, $query, trim($attr), $ordering, $fieldid ? 0 : 1));
		}
		// Create a regular list.
		else {
			$ordering = $this->form->getValue('ordering');
			$html[] = str_replace('jform'.$attributes['name'], $element_id, JHtml::_('list.ordering', $this->name, $query, trim($attr), $ordering, $fieldid ? 0 : 1));
		}
		
		return implode($html);
	}
}
