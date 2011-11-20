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
 * Renders a module list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcmodules extends JFormField
{

	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'Fcmodules';
	
	protected function getInput() {
		$values = $this->value;
		
		$allmodules 	= array();
		$allmodules[] 	= JHTMLSelect::option('', JText::_( 'FLEXI_SELECT_MODULE' )); 

		$db =& JFactory::getDBO();
		
		$query 	= 'SELECT id AS value, title AS text'
				. ' FROM #__modules'
				. ' WHERE published = 1 AND client_id = 0'
				. ' ORDER BY title ASC, id ASC'
				;
		
		$db->setQuery($query);
		$mods = $db->loadObjectList();
		
		if($db->getErrorNum()) {
			echo $db->getErrorMsg();
			exit();
			return array();
		}

		/*foreach ($mods as $m) {
			$allmodules[] = JHTMLSelect::option($m->value, $m->text); 
		}*/

		$class = '';
		
		//return JHTMLSelect::genericList($allmodules, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);
		return JHTML::_('select.genericlist', $mods, $this->name.'[]', $class, 'value', 'text', $values);
	}
}