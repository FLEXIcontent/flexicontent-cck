<?php
/**
* @version 1.5 stable $Id: types.php 1340 2012-06-06 02:30:49Z ggppdk $
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
* Renders a multiple select element
*
*/

class JFormFieldMultiList extends JFormField
{
	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$type = 'MultiList';

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
		if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$name = FLEXI_J16GE ? $attributes['name'] : $name;
		$control_name = FLEXI_J16GE ? str_replace($name, '', $element_id) : $control_name;
		
		//$attribs = ' style="float:left;" ';
		$attribs = array(
	    'id' => $element_id, // HTML id for select field
	    'list.attr' => array( // additional HTML attributes for select field
	    ),
	    'list.translate'=>false, // true to translate
	    'option.key'=>'value', // key name for value in data array
	    'option.text'=>'text', // key name for text in data array
	    'option.attr'=>'attr', // key name for attr in data array
	    'list.select'=>$values, // value of the SELECTED field
		);
		
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs['list.attr']['multiple'] = 'multiple';
			$attribs['list.attr']['size'] = @$attributes['size'] ? $attributes['size'] : "6";
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			$maximize_link = '';
		}
		
		
		// HTML Tag parameters
		if ($onchange = @$attributes['onchange']) {
			$onchange = str_replace('{control_name}', $control_name, $onchange);
			$attribs['list.attr']['onchange'] = $onchange;
		}
		
		$subtype = @$attributes['subtype'];

		$attribs['list.attr']['class'] = array();
		if ($subtype=='radio') {
			$attribs['list.attr']['class'][] = 'radio';
		}

		if ($class = @$attributes['class']) {
			$attribs['list.attr']['class'][] = $class;
		}
		
		if (@$attributes['toggle_related']) {
			$attribs['list.attr']['class'][] = 'fcform_toggler_element';
		}
		$attribs['list.attr']['class'] = implode($attribs['list.attr']['class'], ' ');
		
		// Construct an array of the HTML OPTION statements.
		$options = array ();
		foreach ($node->children() as $option)
		{
			$val  = $option->attributes()->value;
			$text = FLEXI_J30GE ? $option->__toString() : $option->data();
			$name = FLEXI_J30GE ? $option->getName() : $option->name();
			//echo "<pre>"; print_r($option); echo "</pre>"; exit;
			if ($name=="group") {
				$group_label = FLEXI_J16GE ? $option->attributes()->label : $option->attributes('label');
				$options[] = JHTML::_('select.optgroup', JText::_($group_label) );
				foreach ($option->children() as $sub_option)
				{
					$attr_arr = array();
					if (isset($sub_option->attributes()->refsh_list)) $attr_arr['refsh_list'] = $sub_option->attributes()->refsh_list;
					if (isset($sub_option->attributes()->force_list)) $attr_arr['force_list'] = $sub_option->attributes()->force_list;
					if (isset($sub_option->attributes()->show_list))  $attr_arr['show_list'] = $sub_option->attributes()->show_list;
					if (isset($sub_option->attributes()->hide_list))  $attr_arr['hide_list'] = $sub_option->attributes()->hide_list;
					if (isset($sub_option->attributes()->class))  $attr_arr['class'] = $sub_option->attributes()->class;
					
					$val    = $sub_option->attributes()->value;
					$text   = FLEXI_J30GE ? $sub_option->__toString() : $sub_option->data();
					//$options[] = JHTML::_('select.option', $val, JText::_($text));
					$options[] = array(
						'value' => $val, 'text' => JText::_($text),
						'attr' => $attr_arr
					);
				}
				$options[] = JHTML::_('select.optgroup', '' );
			}
			else {
				$attr_arr = array();
				if (isset($option->attributes()->refsh_list)) $attr_arr['refsh_list'] = $option->attributes()->refsh_list;
				if (isset($option->attributes()->force_list)) $attr_arr['force_list'] = $option->attributes()->force_list;
				if (isset($option->attributes()->show_list))  $attr_arr['show_list'] = $option->attributes()->show_list;
				if (isset($option->attributes()->hide_list))  $attr_arr['hide_list'] = $option->attributes()->hide_list;
				if (isset($option->attributes()->class))  $attr_arr['class'] = $option->attributes()->class;
				
				//print_r($attr_arr['hide_list']);
				//$options[] = JHTML::_('select.option', $val, JText::_($text));
				$options[] = array(
					'value' => $val, 'text' => JText::_($text),
					'attr' => $attr_arr
				);
			}
		}
		
		/* support for parameter multi-value, multi-parameter dependencies in non-FLEXIcontent views */
		static $js_added = false;
		if (!$js_added) {
			$js_added = true;
			$doc = JFactory::getDocument();
			$doc->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js');
			if ( JRequest::getCmd('option')!='com_flexicontent' ) {
				$js = "
				jQuery(document).ready(function(){
					".(FLEXI_J30GE ?
						"fc_bind_form_togglers('body', 2, '.control-group');" :
						"fc_bind_form_togglers('body', 1, 'li');"
					)."
				});
				";
				$doc->addScriptDeclaration($js);
			}
		}
		
		if ($subtype=='radio') {
			$_class = ' class ="'.$attribs['list.attr']['class'].'"';
			$_id = ' id="'.$element_id.'"';
			$html = '';
			foreach($options as $i => $option) {
				$selected = count($values) && $values[0]==$option['value'] ? ' checked="checked"' : '';
				$input_attribs = '';
				$label_class = '';
				foreach ($option['attr'] as $k => $v) {
					if ($k=='class') { $label_class = $v; continue; }
					$input_attribs .= ' ' .$k. '="' .$v. '"';
				}
				$html .= '
					<input id="'.$element_id.$i.'" type="radio" value="'.$option['value'].'" name="'.$fieldname.'" '. $input_attribs . $selected.'/>
					<label class="'.$label_class.'" for="'.$element_id.$i.'" value="'.$option['text'].'">
						'.$option['text'].'
					</label>';
			}
			$html = '
				<fieldset '.$_class.$_id.'>
				'.$html.'
				</fieldset>
				';
		}
		else {
			$html = JHTML::_('select.genericlist', $options, $fieldname, $attribs);
		}
		if (!FLEXI_J16GE) $html = str_replace('<optgroup label="">', '</optgroup>', $html);
		return $html.$maximize_link;
	}
}