<?php
/**
 * @version 1.5 stable $Id: fields.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.html.html');
jimport('joomla.form.formfield');

/**
 * Renders a module positions list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcpositions extends JFormField
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'Fcpositions';
	
	protected function getInput()
	{
		$doc	= & JFactory::getDocument();
		$db		= & JFactory::getDBO();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$values = FLEXI_J16GE ? $this->value : $value;
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$query 	= 'SELECT DISTINCT position as value, position as text'
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. ' ORDER BY position ASC'
				;
		
		$db->setQuery($query);
		$positions = $db->loadObjectList();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		if ( !$positions )  return array();
		
		// Put a select module option at top of list
		$first_option = new stdClass();
		$first_option->value = '';
		$first_option->text = JText::_( 'FLEXI_SELECT_MODULE' );
		array_unshift($positions, $first_option);
		
		$attribs = '';
		
		return JHTML::_('select.genericlist', $positions, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}