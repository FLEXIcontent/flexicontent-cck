<?php
/**
 * @version 1.5 stable $Id: fields.php 183 2009-11-18 10:30:48Z vistamedia $
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

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFields extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Fields';

	function fetchElement($name, $value, &$node, $control_name)
	{

		$db =& JFactory::getDBO();
		
		$query = 'SELECT id AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
//		. ' ORDER BY iscore DESC, label ASC, id ASC'
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();

		$class = 'multiple="true" size="10"';
		
		return JHTML::_('select.genericlist', $fields, $control_name.'['.$name.'][]', $class, 'value', 'text', $value, $control_name.$name);
	}
}