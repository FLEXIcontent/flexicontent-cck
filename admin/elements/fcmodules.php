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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

/**
 * Renders a module names list
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
	
	protected function getInput()
	{
		$doc = JFactory::getDocument();
		$db  = JFactory::getDbo();

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$values = $this->value;
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$and='';
		
		$modtype = @ $attributes['modtype'];
		if ($modtype)
		{
			$modtype = preg_split("/[\s]*,[\s]*/", $modtype);
			$and .= " AND module IN ('". implode("','", $modtype)."')";
		}		
		
		$query 	= 'SELECT id AS value, title AS text'
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. $and
				. ' ORDER BY title ASC, id ASC'
				;
		
		$db->setQuery($query);
		$mods = $db->loadObjectList();
		
		if ( !$mods )  return array();
		
		// Put a select module option at top of list
		$first_option = new stdClass();
		$first_option->value = '';
		$first_option->text = JText::_( 'FLEXI_SELECT_MODULE' );
		array_unshift($mods, $first_option);
		
		$attribs = '';
		
		return JHtml::_('select.genericlist', $mods, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}