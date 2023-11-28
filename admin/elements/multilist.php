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

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('groupedlist');   // JFormFieldGroupedList

/**
* Renders a multiple select element
*
*/
class JFormFieldMultiList extends JFormFieldGroupedList
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
		//$attributes = get_object_vars($this->element->attributes());
		//$attributes = $attributes['@attributes'];
		
		$split_via = ($this->element['split_str_value'] ?: '|');
		$values = is_array($this->value)
			? $this->value
			: explode($split_via, $this->value);
		$values = array_map('trim', $values);

		$fieldname	= $this->name;
		$element_id = $this->id;

		$name = $this->element['name'];
		$control_name = str_replace($name, '', $element_id);

		$attribs = array(
			'id' => $element_id, // HTML id for select field
			'group.id' => 'id',
			'list.attr' => array(), // additional HTML attributes for select field
	    'list.translate'=>false, // true to translate
	    'option.key'=>'value', // key name for value in data array
	    'option.text'=>'text', // key name for text in data array
	    'option.attr'=>'attr', // key name for attr in data array
	    'list.select'=>$values, // value of the SELECTED field
		);


		// ***
		// *** HTML Tag parameters
		// ***

		$popover_class = 'hasPopover';

		if ($this->element['multiple']=='multiple' || $this->element['multiple']=='true' )
		{
			$attribs['list.attr']['multiple'] = 'multiple';
			$attribs['list.attr']['size'] = $this->element['size'] ? $this->element['size'] : "6";
		}

		if ($onchange = $this->element['onchange'])
		{
			$onchange = str_replace('{control_name}', $control_name, $onchange);
			$attribs['list.attr']['onchange'] = $onchange;
		}

		$subtype = $this->element['subtype'];

		$attribs['list.attr']['class'] = array();

		if ($subtype=='radio')
		{
			$attribs['list.attr']['class'][] = 'radio';
		}
		if ($class = $this->element['class'])
		{
			$attribs['list.attr']['class'][] = $class;
		}

		if ( (int) $this->element['fccustom_revert'] )
		{
			$attribs['list.attr']['class'][] = 'fccustom_revert';
		}
		
		if ( (int) $this->element['toggle_related'] )
		{
			$attribs['list.attr']['class'][] = 'fcform_toggler_element';
		}

		$attribs['list.attr']['class'] = implode(' ', $attribs['list.attr']['class']);


		// ***
		// *** Construct an array of the HTML OPTION statements.
		// ***

		$this->_options = array();
		$V2L = array();

		$num_index = 0;
		$last_was_grp = false;
		foreach ($this->element->children() as $option)
		{
			$name = $option->getName();   //echo 'Name: ' . $name . '<pre>' . print_r($option, true) .'</pre>'; exit;

			// If current option is group then iterrate through its children, otherwise create single value array
			$children = $name=="group"
				? $option->children()
				: array( & $option );

			$_options = array();
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

				$val  = (string) $sub_option->attributes()->value;
				$text = JText::_( (string) $sub_option );

				$_options[] = (object) array(
					'value' => $val,
					'text'  => $text,
					'attr'  => $attr_arr
				);
				$V2L[$val] = $text;
			}

			// Check for current option is a GROUP
			if ($name=="group")
			{
				$grp = $option->attributes()->name ?: $option->attributes()->label;
				$grp = (string) $grp;
				$this->_options[$grp] = array();
				$this->_options[$grp]['id'] = null;
				$this->_options[$grp]['text'] = JText::_($option->attributes()->label);
				$this->_options[$grp]['items'] = $_options;
				$last_was_grp = true;
			}
			else
			{
				$num_index = !$last_was_grp ? $num_index : ($num_index + 1);
				$this->_options[$num_index]['items'][] = reset($_options);
				$last_was_grp = false;
			}
		}
		
		// Support for parameter multi-value, flexicontent multi-parameter dependencies in non-FLEXIcontent views
		if (self::$css_js_added === null)
		{
			self::$css_js_added = true;
			flexicontent_html::loadFramework('flexi-lib');

			if ( JFactory::getApplication()->input->get('option', '', 'cmd') != 'com_flexicontent' )
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


		// ***
		// *** SUBTYPE: radio
		// ***

		if ($subtype=='radio')
		{
			$group_classes = $attribs['list.attr']['class'];

			$isBtnGroup  = strpos(trim($group_classes), 'btn-group') !== false;
			$isBtnYesNo  = strpos(trim($group_classes), 'btn-group-yesno') !== false;
			
			$_class = ' class="'.$group_classes.'"';
			$_id = ' id="'.$element_id.'"';
			$html = '';
			foreach($this->_options as $i => $ops)
			{
				foreach($ops['items'] as $i => $option)
				{
					$selected = count($values) && $values[0]==$option->value ? ' checked="checked"' : '';
					$input_attribs = '';
					$label_class = '';
					foreach ($option->attr as $k => $v)
					{
						if ($k=='class') { $label_class = $v; continue; }
						$input_attribs .= ' ' .$k. '="' .$v. '"';
					}
					if (FLEXI_J40GE)
					{
						if (!$label_class)
						{
							$label_class = "btn";
						}
						$input_attribs .= ' class="btn-check" ';

						// Initialize some option attributes.
						if ($isBtnYesNo)
						{
							// Set the button classes for the yes/no group
							switch ($option->value)
							{
								case '0':
									$label_class .= ' btn-outline-danger';
									break;
								case '1':
									$label_class .= ' btn-outline-success';
									break;
								default:
									$label_class .= ' btn-outline-secondary';
									break;
							}
						}
					}
					$html .= '
						<input id="'.$element_id.$i.'" type="radio" value="'.$option->value.'" name="'.$fieldname.'" '. $input_attribs . $selected.'/>
						<label class="'.$label_class.'" for="'.$element_id.$i.'">
							' . $option->text . '
						</label>';
				}
			}
			$html = FLEXI_J40GE ? '
				<fieldset '.$_id.'>
					<legend class="visually-hidden">Enable</legend>
					<div '.$_class.'>
					'.$html.'
					</div>
				</fieldset>
				' : '
				<fieldset '.$_class.$_id.'>
				'.$html.'
				</fieldset>
				';
		}


		// ***
		// *** SUBTYPE: drop-down select
		// ***

		else
		{
			$lbl = reset($V2L);
			$val = key($V2L);
			if ( $val === '' && $this->_inherited!==null && !is_array($this->_inherited) && isset($V2L[$this->_inherited]) )
			{
				$this->_options[0]['items'][0]->text = StringHelper::strtoupper($this->_options[0]['items'][0]->text). ' ... '. $V2L[$this->_inherited];
			}
			$html = JHtml::_('select.groupedlist', $this->_options, $fieldname, $attribs);
		}


		// ***
		// *** inline tooltips and texts
		// ***

		$tip_text = $tip_text2 = '';

		if ($inline_tip = $this->element['inline_tip'])
		{
			$tip_img = $this->element['tip_img'] ? $this->element['tip_img'] : 'comments.png';
			$preview_img = $this->element['preview_img'] ? $this->element['preview_img'] : '';
			$tip_class = $this->element['tip_class'] . ' ' . $popover_class;

			$hintmage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' );
			$previewimage = $preview_img ? JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0 0 0 12px;" ' ) : '';
			$tip_text .= '
				<span class="'.$tip_class.'" style="display: inline-block;" data-content="' . htmlspecialchars(JText::_($inline_tip), ENT_COMPAT, 'UTF-8') . '">
					' . $hintmage . $previewimage . '
				</span>';
		}

		if ($inline_text = $this->element['inline_text'])
		{
			$text_class = $this->element['text_class'];
			$text_class .= ($text_class ? ' ' : '') . 'fc_toggle_current';
			$tip_text .= '
				<span class="'.$text_class.'" style="display: inline-block;">
					' . JText::_($inline_text) . '
				</span>';
		}

		if ($inline_tip = $this->element['inline_tip2'])
		{
			$tip_img = $this->element['tip_img2'] ? $this->element['tip_img2'] : 'comments.png';
			$preview_img = $this->element['preview_img2'] ? $this->element['preview_img2'] : '';
			$tip_class = $this->element['tip_class2'] . ' ' . $popover_class;

			$hintmage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' );
			$previewimage = $preview_img ? JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0 0 0 12px;" ' ) : '';
			$tip_text2 .= '
				<span class="'.$tip_class.'" style="display: inline-block;" data-content="' . htmlspecialchars(JText::_($inline_tip), ENT_COMPAT, 'UTF-8') . '">
					' . $hintmage . $previewimage . '
				</span>';
		}


		// ***
		// *** Inherited value display
		// ***

		$inherited_info = '';
		if ($subtype=='list' && $this->_inherited && is_array($this->_inherited))
		{
			$_vals = is_array($this->_inherited)
				? $this->_inherited
				: array($this->_inherited);
			foreach ($_vals as $v)
			{
				if (isset($V2L[$v])) $inherited_info .= '<div class="fc-inherited-value">'.$V2L[$v].'</div>';
			}
		}

		return $html . $inherited_info . $tip_text . $tip_text2;
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