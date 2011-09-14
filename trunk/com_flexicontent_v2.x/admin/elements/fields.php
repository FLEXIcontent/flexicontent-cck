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
defined('_JEXEC') or die();
jimport('joomla.html.html');
jimport('joomla.form.formfield');

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFields extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Fields';

	function getInput() {
		$values = $this->value;

		$db =& JFactory::getDBO();
		$node = &$this->element;

		$and = ($node->getAttribute('isnotcore')) ? ' AND iscore = 0' : '';
		
		if ((boolean)$node->getAttribute('fieldnameastext')) {
			$text = 'CONCAT(label, \'(\', `name`, \')\')';
		}else{
			$text = 'label';
		}
		if ((boolean)$node->getAttribute('fieldnameasvalue')) {
			$ovalue = '`name`';
		}else{
			$ovalue = 'id';  // ELSE should always be THIS , otherwise we break compatiblity with all previous FC versions
		}
		$isadvsearch = $node->getAttribute('isadvsearch');
		if($isadvsearch!==NULL) {
			$and .= " AND isadvsearch='{$isadvsearch}'";
		}
		$query = 'SELECT '.$ovalue.' AS value, '.$text.' AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. $and
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();

		$attribs = "";
		if ($node->getAttribute('multiple')) {
			$attribs .= 'multiple="true" size="10"';
		} else {
			array_unshift($fields, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
		}
		if ($onchange = $node->getAttribute('onchange')) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		return JHTML::_('select.genericlist', $fields, $this->name.'[]', $attribs, 'value', 'text', $values);
	}
}
