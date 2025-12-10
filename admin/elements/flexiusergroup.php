<?php
/**
 * @version 1.5 stable $Id: flexiusergroup.php 1348 2012-06-19 02:38:15Z ggppdk $
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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('usergroup');   // \Joomla\CMS\Form\Field\UserFieldgroup

/**
 * Form Field class for the Joomla Platform.
 * Supports a nested check box field listing user groups.
 * Multiselect is available by default.
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFLEXIUsergroup extends \Joomla\CMS\Form\FormField  // \Joomla\CMS\Form\Field\UserFieldgroup
{
	/**
	 * The form field type OR name
	 * @access	protected
	 * @var		string
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
		// Get attribute array
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		$children = $node->children();
		
		// Get values array
		$values = $this->value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		// Get field and element name
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		// Initialize variables.
		$extra_options = array();
		$allowAll = @$attributes['allowall'];
		$faGrps = @$attributes['fagrps'];

		// Iterate through the children and build an array of options.
		foreach ($children as $option)
		{
			// Only add <option /> elements.
			$is_option = $option->getName()=='option';
			if ( !$is_option ) continue;
			
			// Get variable for creating an extra option object based on the <option /> element
			$option_value    = (string)( FLEXI_J16GE ? $option['value']       : $option->_attributes['value']);
			$option_text     = (string)( FLEXI_J16GE ? trim((string) $option) : $option->_data);
			$option_disabled = (string)( FLEXI_J16GE ? $option['disabled']    : $option->_attributes['disabled']);
			$option_disabled = ((string) $option_disabled == 'true');
			
			// Create the extra option object
			$tmp = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $option_value, $option_text, 'value', 'text', $option_disabled);
			
			// Class / JS / other attributes
			$tmp->class = (string)( FLEXI_J16GE ? $option['class'] : $option->_attributes['class']);
			$tmp->onclick = (string)( FLEXI_J16GE ? $option['onclick'] : $option->_attributes['onclick']);
			
			// Add the option object to the result set.
			$extra_options[] = $tmp;
		}
		
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery(
			'SELECT a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level' .
			' FROM #__usergroups AS a' .
			' LEFT JOIN `#__usergroups` AS b ON a.lft > b.lft AND a.rgt < b.rgt' .
			' GROUP BY a.id' .
			' ORDER BY a.lft ASC'
		);
		$options = $db->loadObjectList();
		
		if ( !$options )  return null;
		
		for ($i = 0, $n = count($options); $i < $n; $i++)
		{
			$options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
		}
		
		// If all usergroups is allowed, push it into the array.
		if ($allowAll) {
			array_unshift($options, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('JOPTION_ACCESS_SHOW_ALL_GROUPS')));
		}
		$options = array_merge($extra_options, $options);
		
		$attribs = 'style="float:left;"';
		$initial_size = @$attributes['size'] ? intval($attributes['size']) : 5;
		$maximize_size = @$attributes['maxsize'] ? intval($attributes['maxsize']) : count($options);
		// SPECIAL CASE: this field is always multiple, we will add '[]' WHILE checking for the attribute ...
		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .=' multiple="multiple"';
			$attribs .= ' size="'.$initial_size.'" ';
		}
		
		// Initialize some field attributes.
		$attribs .= ((string) @$this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';

		// Initialize JavaScript field attributes.
		$attribs .= @$this->element['onchange'] ? ' onchange="' . (string) $this->element['onchange'] . '"' : '';		
		
		$classes = 'use_select2_lib';
		$classes .= @$attributes['required'] && @$attributes['required']!='false' ? ' required' : '';
		$classes .= @$attributes['class'] ? ' '.$attributes['class'] : '';
		$attribs .= ' class="'.$classes.'"';
		
		return \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
	
}
