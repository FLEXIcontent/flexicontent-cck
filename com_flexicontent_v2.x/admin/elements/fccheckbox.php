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
jimport('joomla.html.html');
jimport('joomla.form.formfield');
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
			$value = $this->value;
			$node = & $this->element;
		}
		//print_r($value);
		
		// Handle J1.6+
		if (FLEXI_J16GE) {
			$node = & $this->element;
			if ( empty($value) ) {
				$value = array();
			} else if ( !is_array($value) ) {
				$value = explode("|", $this->value);
				$value = ($value[0]=='') ? array() : $value;
			}
		}
		$split_char = ",";
		
		// Get options and values
		$checkoptions = explode($split_char, $node->getAttribute('checkoptions'));
		$checkvals = explode($split_char, $node->getAttribute('checkvals'));
		$defaultvals = explode($split_char, $node->getAttribute('defaultvals'));
		
		// Verify defaultvals option
		if ( empty($defaultvals[0]) ) $defaultvals = array();
		if ( count($defaultvals) && $node->getAttribute('display_useglobal') ) {
			$defaultvals = array();
			echo "Cannot use field option 'defaultvals' together with 'display_useglobal' 'defaultvals' cleared";
		}

		// Make value an array if value is not already array, also load defaults, if field parameter never saved
		if (!is_array($value) || count($value)==0)
			$value = (!is_array($value) && strlen($value)) ? array($value) : $defaultvals;

		// Sanity check
		if (count($checkoptions)!=count($checkvals))
			return "Number of check options not equal to number of check values";
		
		// Create checkboxes
		if (FLEXI_J16GE) {
			$fieldname = $this->name.'[]';
			$element_id = $this->id;
		} else {
			$fieldname = $control_name.'['.$name.'][]';
			$element_id = $control_name.$name;
		}
		$html = '<fieldset id="'.$element_id.'" class="radio" style="border-width:0px">';
		
		$disable_all = '';
		if ( $node->getAttribute('display_useglobal') ) {
			$check_global='';
			if (count($value) == 0) {
				$check_global = ' checked="checked" ';
				$disable_all = ' disabled="disabled" ';
			}
			$html .= '<input id="'.$element_id.'_useglobal" type="checkbox" '.$check_global.' value="" onclick="toggle_options_fc_'.$element_id.'(this)" />';
			$html .= '<label for="'.$element_id.'_useglobal" >-- '.JText::_('FLEXI_USE_GLOBAL').' --</label>';
		}

		foreach($checkoptions as $i => $o) {
			$curr_element_id = $element_id.$i;
			$html .= '<input id="'.$curr_element_id.'" type="checkbox"'.$disable_all;
			$html .= in_array($checkvals[$i], $value) ? ' checked="checked"' : '' ;
			$html .= ' name="'.$fieldname.'" value="'.$checkvals[$i].'">';
			$html .= '<label for="'.$curr_element_id.'" >'.JText::_($checkoptions[$i]).'</label>';
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

		$doc = & JFactory::getDocument();
		$doc->addScriptDeclaration($js);
		
		return $html;
	}
}

?>