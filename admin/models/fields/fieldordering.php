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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

/**
 * Supports an HTML select list of plugins
 *
 * @package		Joomla.Administrator
 * @subpackage	com_newsfeeds
 * @since		1.6
 */
class JFormFieldFieldordering extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Fieldordering';

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

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

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
