<?php
/**
 * @version 1.5 stable $Id: item.php
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
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$required = isset($attributes['required']) ? $attributes['required'] : false;
		
		$value = $this->value;
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		
		$item = JTable::getInstance('flexicontent_items', '');
		if ($value) {
			$item->load($value);
		} else {
			$item->title = JText::_( 'FLEXI_SELECT_ITEM' );
		}
		
		// J1.6+ does have required field capability, add a HTML tag parameter
		$required_param = $required ? ' required="required" class="required" aria-required="true" ' : '';
		
		static $js_added = false;
		if (!$js_added) {
			$js = "
			function qfClearSelectedItem(element_id)
			{
				jQuery('#'+element_id+'_name').val('');
				jQuery('#'+element_id+'_name').attr('placeholder', '".JText::_( 'FLEXI_SELECT_ITEM',true )."');
				jQuery('#'+element_id).val('');
				return false;
			};
			
			var fc_select_element_id;
			function qfSelectItem(id, cid, title)
			{
				document.getElementById(fc_select_element_id).value = id;
				document.getElementById(fc_select_element_id+'_name').value = title;
				if (cid_field =	document.getElementById('jform_request_cid')) cid_field.value = cid;
				$('sbox-btn-close').fireEvent('click');
			}
			";
			JFactory::getDocument()->addScriptDeclaration($js);
			JHTML::_('behavior.modal', 'a.modal');
		}
		
		$langparent_item = (boolean) @$attributes['langparent_item'];
		$type_id = @$attributes['type_id'];
		$created_by = @$attributes['created_by'];
		$link = 'index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component';
		$link .= '&amp;langparent_item='.($langparent_item ? '1' : '0');
		$link .= $type_id ? '&amp;type_id='.$type_id : '';
		$link .= $created_by ? '&amp;created_by='.$created_by : '';
		
		$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		return '
		<span class="input-append">
			<input type="text" id="'.$element_id.'_name" value="'.$item->title.'" '.$required_param.' readonly="readonly" />
			<a class="modal btn hasTooltip" title="'.JText::_( 'FLEXI_SELECT' ).'" onclick="fc_select_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" >
				'.JText::_( 'FLEXI_SELECT' ).'
			</a>
			<button id="' .$element_id. '_clear" class="btn" onclick="return qfClearSelectedItem(\''.$element_id . '\')">
				<span class="icon-remove"></span>'
				.JText::_('FLEXI_CLEAR').'
			</button>
		</span>
		<input type="hidden" id="'.$element_id.'" name="'.$fieldname.'" value="'.$value.'" />
		';
	}
}
?>