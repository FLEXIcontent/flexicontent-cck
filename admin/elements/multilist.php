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

use Joomla\String\StringHelper;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

/**
* Renders a multiple select element
*
*/
class JFormFieldMultiList extends JFormField
{
	protected $_options;
	protected $_inherited;

	static $css_js_added = null;

	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$type = 'MultiList';

	function getInput()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$values = $this->value;
		if ( ! is_array($values) )		$values = explode("|", $values);
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$name = $attributes['name'];
		$control_name = str_replace($name, '', $element_id);
		
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
		
		if (@$attributes['fccustom_revert']) {
			$attribs['list.attr']['class'][] = 'fccustom_revert';
		}
		
		if (@$attributes['toggle_related']) {
			$attribs['list.attr']['class'][] = 'fcform_toggler_element';
		}
		$attribs['list.attr']['class'] = implode(' ', $attribs['list.attr']['class']);
		
		// Construct an array of the HTML OPTION statements.
		$this->_options = array ();
		$V2L = array();
		foreach ($node->children() as $option)
		{
			$name = FLEXI_J30GE ? $option->getName() : $option->name();
			//echo "<pre>"; print_r($option); echo "</pre>"; exit;
			
			// Check for current option is a GROUP and add its START
			if ($name=="group")  $this->_options[] = JHTML::_('select.optgroup', JText::_( $option->attributes()->label ) );
			
			// If current option is group then iterrate through its children, otherwise create single value array
			$children = $name=="group" ? $option->children() : array( & $option );
			
			foreach ($children as $sub_option)
			{
				$attr_arr = array();
				if (isset($sub_option->attributes()->seton_list))  $attr_arr['data-seton_list']  = $sub_option->attributes()->seton_list;
				if (isset($sub_option->attributes()->setoff_list)) $attr_arr['data-setoff_list'] = $sub_option->attributes()->setoff_list;
				if (isset($sub_option->attributes()->refsh_list))  $attr_arr['data-refsh_list']  = $sub_option->attributes()->refsh_list;
				if (isset($sub_option->attributes()->force_list))  $attr_arr['data-force_list']  = $sub_option->attributes()->force_list;
				if (isset($sub_option->attributes()->show_list))   $attr_arr['data-show_list']   = $sub_option->attributes()->show_list;
				if (isset($sub_option->attributes()->hide_list))   $attr_arr['data-hide_list']   = $sub_option->attributes()->hide_list;
				if (isset($sub_option->attributes()->fcconfigs))   $attr_arr['data-fcconfigs']   = $sub_option->attributes()->fcconfigs;
				if (isset($sub_option->attributes()->fcreadonly))  $attr_arr['data-fcreadonly']  = $sub_option->attributes()->fcreadonly;
				
				if (isset($sub_option->attributes()->class))  $attr_arr['class'] = $sub_option->attributes()->class;
				
				$val  = (string)$sub_option->attributes()->value;
				$text = JText::_( (string) $sub_option );
				//$this->_options[] = JHTML::_('select.option', $val, $text);
				$this->_options[] = array(
					'value' => $val,
					'text'  => $text,
					'attr'  => $attr_arr
				);
				$V2L[$val] = $text;
			}
			
			// Check for current option is a GROUP and add its END
			if ($name=="group") $this->_options[] = JHTML::_('select.optgroup', '' );
		}
		
		/* support for parameter multi-value, multi-parameter dependencies in non-FLEXIcontent views */
		if (self::$css_js_added === null)
		{
			self::$css_js_added = true;
			flexicontent_html::loadFramework('flexi-lib');

			if ( JFactory::getApplication()->input->get('option') != 'com_flexicontent' )
			{
				$js = "
				jQuery(document).ready(function(){
					".(FLEXI_J30GE ?
						"fc_bindFormDependencies('body', 2, '.control-group');" :
						"fc_bindFormDependencies('body', 1, 'li');"
					)."
				});
				";
				JFactory::getDocument()->addScriptDeclaration($js);
			}
		}
		
		if ($subtype=='radio')
		{
			$_class = ' class ="'.$attribs['list.attr']['class'].'"';
			$_id = ' id="'.$element_id.'"';
			$html = '';
			foreach($this->_options as $i => $option) {
				$selected = count($values) && $values[0]==$option['value'] ? ' checked="checked"' : '';
				$input_attribs = '';
				$label_class = '';
				foreach ($option['attr'] as $k => $v) {
					if ($k=='class') { $label_class = $v; continue; }
					$input_attribs .= ' ' .$k. '="' .$v. '"';
				}
				$html .= '
					<input id="'.$element_id.$i.'" type="radio" value="'.$option['value'].'" name="'.$fieldname.'" '. $input_attribs . $selected.'/>
					<label class="'.$label_class.'" for="'.$element_id.$i.'">
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
			$lbl = reset($V2L);
			$val = key($V2L);
			if ( $val === '' && $this->_inherited!==null && !is_array($this->_inherited) && isset($V2L[$this->_inherited]) )
			{
				$this->_options[0]['text'] = StringHelper::strtoupper($this->_options[0]['text']). ' ... '. $V2L[$this->_inherited];
			}
			$html = JHTML::_('select.genericlist', $this->_options, $fieldname, $attribs);
		}
		
		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_img = @$attributes['tip_img'];
			$tip_img = $tip_img ? $tip_img : 'comment.png';
			$preview_img = @$attributes['preview_img'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' );
			$previewimage = $preview_img ? JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0 0 0 12px;" ' ) : '';
			$tip_text = '<span class="'.$tip_class.'" style="display: inline-block;" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		
		if ($inline_tip = @$attributes['inline_tip2'])
		{
			$tip_img = @$attributes['tip_img2'];
			$tip_img = $tip_img ? $tip_img : 'comment.png';
			$preview_img = @$attributes['preview_img2'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class2'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' );
			$previewimage = $preview_img ? JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0 0 0 12px;" ' ) : '';
			$tip_text2 = '<span class="'.$tip_class.'" style="display: inline-block;" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		
		if ($subtype=='list' && $this->_inherited && is_array($this->_inherited))
		{
			$_vals = is_array($this->_inherited) ? $this->_inherited : array($this->_inherited);
			$inherited_info = '';
			foreach ($_vals as $v)
			{
				if (isset($V2L[$v])) $inherited_info .= '<div class="fc-inherited-value">'.$V2L[$v].'</div>';
			}
		}
		
		return $html .@ $inherited_info .@ $tip_text .@ $tip_text2;
	}


	function getListOptions()
	{
		return $this->_options;
	}


	function setInherited($values)
	{
		$this->_inherited = $values;
	}


	function getLabel()
	{
		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return str_replace(' for="', ' data-for="', parent::getLabel());
	}
}