<?php
/**
 * @version 1.5 stable $Id: types.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
defined('_JEXEC') or die();
jimport('joomla.html.html');
jimport('joomla.form.formfield');
/**
 * Renders a types element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldTypes extends JFormField
{
/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Types';

	function getInput() {
		$doc 		=& JFactory::getDocument();
		$db =& JFactory::getDBO();
		$node = &$this->element;
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;
		$db->setQuery($query);
		$types = $db->loadObjectList();
		$attribs = "";
		if ($node->getAttribute('multiple')) {
			$attribs .= 'multiple="true" size="4"';
			$fieldname = $this->name;//.'[]';
		} else {
			array_unshift($types, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$fieldname = $this->name;
		}
		$values = $this->value;
		//var_dump($values);
		return JHTML::_('select.genericlist', $types, $fieldname, $attribs, 'value', 'text', $values, $this->id);
	}
}
?>
