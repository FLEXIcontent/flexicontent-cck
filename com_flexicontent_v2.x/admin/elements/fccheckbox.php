<?php
/**
 * @version 1.5 stable $Id: fccheckbox.php 967 2011-11-21 00:01:36Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @author ggppdk
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
 * Renders a multi element checkbox (array of checkboxes)
 */
class JFormFieldFccheckbox extends JFormField
{

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
  var $type = 'Fccheckbox';
	
	function getInput()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		$split_char = ",";
		
		// Get options and values
		$checkoptions = preg_split("/[\s]*".$split_char."[\s]*/", $attributes['checkoptions']);
		$checkvals = preg_split("/[\s]*".$split_char."[\s]*/", $attributes['checkvals']);
		$defaultvals = preg_split("/[\s]*".$split_char."[\s]*/", @$attributes['defaultvals']);
		
		// Verify defaultvals option
		if ( count($defaultvals)==1 && empty($defaultvals[0]) ) $defaultvals = array();
		if ( count($defaultvals) && @$attributes['display_useglobal'] ) {
			$defaultvals = array();
			echo "Cannot use field option 'defaultvals' together with 'display_useglobal' 'defaultvals' cleared";
		}

		// Make value an array if value is not already array, also load defaults, if field parameter never saved
		if ( count($values)==0 )  $values = $defaultvals;

		// Sanity check
		if (count($checkoptions)!=count($checkvals))
			return "Number of check options not equal to number of check values";
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		// 'multiple' attribute in XML adds '[]' automatically in J2.5 and manually in J1.5
		// This field is always multiple, we will add '[]' WHILE checking for the attribute ...
		$is_multiple = @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true';
		if (!FLEXI_J16GE || !$is_multiple)
			$fieldname .= '[]';
		
		$html = '<fieldset id="'.$element_id.'" class="radio" style="border-width:0px;'.(FLEXI_J16GE ? "width:60%; " : "").'">';
		
		$inline_style  = "float:left; white-space:nowrap; ".(!FLEXI_J16GE ? "margin-right:12px; " : "");
		$disable_all = '';
		if ( @$attributes['display_useglobal'] ) {
			$check_global='';
			if (count($values) == 0) {
				$check_global = ' checked="checked" ';
				$disable_all = ' disabled="disabled" ';
			}
			$html .= '<div style="'.$inline_style.'" ><input id="'.$element_id.'_useglobal" type="checkbox" '.$check_global.' value="" onclick="toggle_options_fc_'.$element_id.'(this)" />';
			$html .= '<label for="'.$element_id.'_useglobal" >'.JText::_('FLEXI_USE_GLOBAL').'</label></div>';
		}

		// Create checkboxes
		foreach($checkoptions as $i => $o) {
			$curr_element_id = $element_id.$i;
			$html .= '<div style="'.$inline_style.'" ><input id="'.$curr_element_id.'" type="checkbox"'.$disable_all;
			$html .= in_array($checkvals[$i], $values) ? ' checked="checked"' : '' ;
			$html .= ' name="'.$fieldname.'" value="'.$checkvals[$i].'" />';
			$html .= '<label for="'.$curr_element_id.'" >'.JText::_($checkoptions[$i]).'</label></div>';
			$html .= FLEXI_J16GE ? ' &nbsp; ' : '';
		}

		$html .= '<input id="'.$element_id.'9999" type="hidden"  name="'.$fieldname.'" value="__SAVED__" '.$disable_all.'/> ';
		$html .= '</fieldset>';
		
		$js 	= "
function toggle_options_fc_".$element_id."(element) {
	
	var panel 	= $('".$element_id."');
	var inputs 	= panel.getElements('input');
	if ( $(element).checked ) {
		inputs.each(function(el){
			el.setProperty('disabled', 'disabled');
		});
	} else {
		inputs.each(function(el){
			el.setProperty('disabled', '');
		});
	}
	element.setProperty('disabled', '');
}";

		$doc = JFactory::getDocument();
		$doc->addScriptDeclaration($js);
		
		return $html;
	}
}

?>