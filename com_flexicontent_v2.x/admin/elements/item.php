<?php
/**
 * @version 1.5 stable $Id: item.php 1317 2012-05-19 22:17:59Z ggppdk $
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
 * Renders an Item element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldItem extends JFormField
{
/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Item';

	function getInput()
	{
		$doc = JFactory::getDocument();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}

		$value = FLEXI_J16GE ? $this->value : $value;

		$fieldname	= FLEXI_J16GE ? $this->name : ($control_name ? $control_name.'['.$name.']' : $name);
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

		$item = JTable::getInstance('flexicontent_items', '');
		if ($value) {
			$item->load($value);
		} else {
			$item->title = JText::_( 'FLEXI_SELECT_ITEM' );
		}

		$js = "
		window.addEvent( 'domready', function()
		{
			$('remove').addEvent('click', function(){
				$('".$element_id."_name').setProperty('value', '".JText::_( 'FLEXI_SELECT_ITEM',true )."');
				$('".$element_id."_id').setProperty('value', '0');
			});
		});

		function qfSelectItem(id, cid, title) {
			document.getElementById('".$element_id."_id').value = id;

			var cid_field =	document.getElementById('jform_request_cid');
			if (cid_field) cid_field.value = cid;
			/*else document.getElementById('".$element_id."_id').value += ':'+cid; */

			document.getElementById('".$element_id."_name').value = title;
			$('sbox-btn-close').fireEvent('click');
		}";

		$langparent_item = (boolean) @$attributes['langparent_item'];
		$type_id = @$attributes['type_id'];
		$created_by = @$attributes['created_by'];
		$link = 'index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component';
		$link .= '&amp;langparent_item='.($langparent_item ? '1' : '0');
		$link .= $type_id ? '&amp;type_id='.$type_id : '';
		$link .= $created_by ? '&amp;created_by='.$created_by : '';
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');
		
		$app = JFactory::getApplication();
		$rel = "{handler: \"iframe\", size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}";
		$html  = "
		<input type='text' id='".$element_id."_name' value='{$item->title}' disabled='disabled' class='inputbox fcfield_textval'/>
		<div class='button2-left' style='margin-top:2px;'><div class='blank'>
			<a class='modal' style='margin:0px !important;' title='".JText::_( 'FLEXI_SELECT' )."'  href='".$link."' rel='".$rel."'>
				".JText::_( 'FLEXI_SELECT' )."
			</a>
		</div></div>
		<div class='button2-left' style='margin-top:2px;'><div class='blank'>
			<a id='remove' style='margin:0px !important;' title='".JText::_( 'FLEXI_REMOVE_VALUE' )."'  href='#'>
				".JText::_( 'FLEXI_REMOVE_VALUE' )."
			</a>
		</div></div>
		<input type='hidden' id='".$element_id."_id' name='".$fieldname."' value='".$value."' />
		";
		return $html;
	}
}
?>