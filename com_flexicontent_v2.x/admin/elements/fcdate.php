<?php
/**
 * @version 1.5 beta 4 $Id: fcdate.php 967 2011-11-21 00:01:36Z ggppdk $
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
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
}

/**
 * Renders a date element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcdate extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var $type = 'Fcdate';

	public function getInput()
	{
		$document =& JFactory::getDocument();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$css  = '.calendar { vertical-align:middle; }';
		$document->addStyleDeclaration($css);
		
		$value = FLEXI_J16GE ? $this->value : $value;
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		$format = '%Y-%m-%d';
		$attribs = (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="18" ';
		
 		return JHTML::_('calendar', $value, $fieldname, $element_id, $format, $attribs);
	}
}