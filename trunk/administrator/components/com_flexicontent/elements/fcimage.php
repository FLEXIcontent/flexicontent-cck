<?php
/**
 * @version 1.5 stable $Id$
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
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
}

/**
 * Renders an image element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFcimage extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Fcimage';

	function fetchElement($name, $value, &$node, $control_name)
	{

		$images 	= array();
		$images[] 	= JHTMLSelect::option('', JText::_( 'FLEXI_SELECT_IMAGE_FIELD' )); 

		$db =& JFactory::getDBO();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$valcolumn = 'name';
		if (@$attributes['valcolumn']) {
			$valcolumn = $attributes['valcolumn'];
		}
		
		$query = 'SELECT '.$valcolumn.' AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. ' AND field_type = ' . $db->Quote('image')
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();

		foreach ($fields as $field) {
			$images[] = JHTMLSelect::option($field->value, $field->text); 
		}

		return JHTMLSelect::genericList($images, $control_name.'['.$name.']', $class='', 'value', 'text', $value, $control_name.$name);
	}
}
?>