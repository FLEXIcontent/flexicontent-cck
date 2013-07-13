<?php
/**
 * @version 1.5 stable $Id: itemlayout.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldFieldtypes extends JFormFieldList{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Fieldtypes';

	protected function getOptions() {
		global $global_field_types;
		$db = JFactory::getDBO();
		
		$query = 'SELECT element AS value, REPLACE(name, "FLEXIcontent - ", "") AS text'
		. ' FROM '.(FLEXI_J16GE ? '#__extensions' : '#__plugins')
		. ' WHERE '.(FLEXI_J16GE ? 'enabled = 1' : 'published = 1')
		. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
		. ' ORDER BY text ASC'
		;
		
		$db->setQuery($query);
		$global_field_types = $db->loadObjectList();
		
		// This should not be neccessary as, it was already done in DB query above
		foreach($global_field_types as $field_type) {
			$field_type->text = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->text);
			$field_arr[$field_type->text] = $field_type;
		}
		ksort( $field_arr, SORT_STRING );
		
		return $field_arr;
		
	}
}
?>
