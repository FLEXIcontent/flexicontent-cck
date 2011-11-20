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
	
	protected function getInput() {
		$values = $this->value;
		
		$allpositions 	= array();
		$allpositions[] = JHTMLSelect::option('', JText::_( 'FLEXI_SELECT_POSITION' )); 

		$db =& JFactory::getDBO();
		
		$query 	= 'SELECT DISTINCT position as value, position as text '
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. ' ORDER BY position ASC'
				;
		
		$db->setQuery($query);
		$pos = $db->loadObjectList();

		/*foreach ($pos as $p) {
			$allpositions[] = JHTMLSelect::option($p, $p); 
		}*/

		$class = '';
		
		//return JHTMLSelect::genericList($allpositions, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);
		return JHTML::_('select.genericlist', $pos, $this->name.'[]', $class, 'value', 'text', $values);
	}
}