<?php
/**
 * @version 1.5 stable $Id: item.php 1269 2012-05-08 01:51:53Z ggppdk $
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
		$node = & $this->element;
		$doc 		=& JFactory::getDocument();
		
		$element_id = $this->id;
		
		$fieldname	= $this->name;
		$value			= $this->value;
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

		$item =& JTable::getInstance('flexicontent_items', '');
		if ($value) {
			$item->load($value);
		} else {
			$item->title = JText::_( 'FLEXI_SELECT_ITEM' );
		}

		$js = "
		window.addEvent( 'domready', function()
		{
			$('remove').addEvent('click', function(){
				$('".$element_id."_name').setProperty('value', '".JText::_( 'FLEXI_SELECT_ITEM' )."');
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

		$langparent_item = (boolean) $node->getAttribute('langparent_item');
		$type_id = $node->getAttribute('type_id');
		$link = 'index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component'.( $langparent_item ? '&langparent_item=1' : '' ).( $type_id ? '&type_id='.$type_id : '' );
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$html = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"".$element_id."_name\" value=\"{$item->title}\" disabled=\"disabled\" /></div>";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_( 'FLEXI_SELECT' )."\"  href=\"$link\" rel=\"{handler: 'iframe', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}\">".JText::_( 'FLEXI_SELECT' )."</a></div></div>\n";
		$html .= "\n<input type=\"hidden\" id=\"".$element_id."_id\" name=\"$fieldname\" value=\"$value\" />";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a id=\"remove\" title=\"".JText::_( 'FLEXI_REMOVE_VALUE' )."\"  href=\"#\"\">".JText::_( 'FLEXI_REMOVE_VALUE' )."</a></div></div>\n";

		return $html;
	}
}
?>