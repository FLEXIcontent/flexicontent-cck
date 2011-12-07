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

/**
 * Renders a filter element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFilters extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Filters';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$fparams 	=& JComponentHelper::getParams('com_flexicontent');
		$db =& JFactory::getDBO();
		
		$filters = $fparams->get('filter_types', 'createdby,modifiedby,type,state,tags,checkbox,checkboximage,radio,radioimage,select,selectmultiple');
		$filters = explode(',', $filters);

		foreach($filters as $f) {
			$f = $db->Quote($f);
		}
		$filterstring = implode('","', $filters);

		$query = 'SELECT id AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. ' AND (field_type IN ("'.$filterstring.'") OR isfilter=1)'
		. ' ORDER BY label ASC, id ASC'
		;
		$db->setQuery($query);
		$fields = $db->loadObjectList();

		$class = 'multiple="true" size="5"';
		
		return JHTML::_('select.genericlist', $fields, $control_name.'['.$name.'][]', $class, 'value', 'text', $value, $control_name.$name);
	}
}
