<?php
/**
 * @version 1.5 stable $Id: qfcategory.php
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

class JFormFieldQfcategory extends JFormField
{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Qfcategory';

	function getInput()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$paramset = isset($attributes['paramset']) ? $attributes['paramset'] : 'request';
		$required = isset($attributes['required']) ? $attributes['required'] : false;
		
		$value = $this->value;
		$fieldname = "jform[".$paramset."][".$this->element["name"]."]";
		$element_id = "jform_".$paramset."_".$node["name"];
		$prompt_str = JText::_( 'FLEXI_SELECT_ONE_CATEGORY', true );
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		
		$item = JTable::getInstance('flexicontent_categories', '');
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
			function qfClearSelectedCategory(element_id)
			{
				jQuery('#'+element_id+'_name').val('');
				jQuery('#'+element_id+'_name').attr('placeholder', '".JText::_( 'FLEXI_SELECT_ITEM',true )."');
				jQuery('#'+element_id).val('');
				return false;
			};
			
			var fc_select_element_id;
			function qfSelectCategory(id, title)
			{
				document.getElementById(fc_select_element_id).value = id;
				document.getElementById(fc_select_element_id+'_name').value = title;
				$('sbox-btn-close').fireEvent('click');
			}
			";
			JFactory::getDocument()->addScriptDeclaration($js);
			JHTML::_('behavior.modal', 'a.modal');
		}
		
		$link = 'index.php?option=com_flexicontent&amp;view=qfcategoryelement&amp;tmpl=component';
		
		$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		return '
		<span class="input-append">
			<input type="text" id="'.$element_id.'_name" value="'.$item->title.'" '.$required_param.' readonly="readonly" />
			<a class="modal btn hasTooltip" title="'.JText::_( 'FLEXI_SELECT' ).'" onclick="fc_select_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" >
				'.JText::_( 'FLEXI_SELECT' ).'
			</a>
			<button id="' .$element_id. '_clear" class="btn" onclick="return qfClearSelectedCategory(\''.$element_id . '\')">
				<span class="icon-remove"></span>'
				.JText::_('FLEXI_CLEAR').'
			</button>
		</span>
		<input type="hidden" id="'.$element_id.'" name="'.$fieldname.'" '.$required_param.' value="'.$value.'" />
		';
	}
}
?>
