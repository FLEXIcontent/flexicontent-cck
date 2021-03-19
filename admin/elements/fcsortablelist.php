<?php
/**
 * @version 1.5 stable $Id: filters.php 1829 2014-01-05 22:18:17Z ggppdk $
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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList


/**
 * Renders a filter element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcSortableList extends JFormField
{
	/**
	 * JFormField type
	 * @access	protected
	 * @var		string
	 */
	
	protected $type = 'FcSortableList';
	
	// Record list
	protected static $records = array('1'=>'example1', '2'=>'example2');
	protected static $cnt = 0;  // to do use this
	
	public function __construct($form = null)
	{
		static::$cnt = static::$cnt + 1;
		parent::__construct( $form );
	}
	
	function add_css_js()
	{
		flexicontent_html::loadJQuery();

		$document = JFactory::getDocument();
		$js = "";
		if ($js) $document->addScriptDeclaration($js);
	}
	
	
	function getInput()
	{
		static $js_css_added = null;
		if ($js_css_added===null)
		{
			$this->add_css_js();
			$js_css_added = true;
		}
		
		$doc	= JFactory::getDocument();
		$db		= JFactory::getDbo();
		$attributes = $this->element->attributes();

		$iselements = $attributes->subtype =='elements';
		$issortable = $attributes->subtype =='sortable' || $iselements;
		$ismultiple = $attributes->subtype =='multiple';
		
		// Disable multiple display if field is sortable, because multiple value will go inside the sortable container
		if ($issortable) $ismultiple = 0;
		
		// Set record separator to comma if this is not set
		$record_sep = $attributes->record_sep ? $attributes->record_sep : ',';
		$props_sep  = $attributes->props_sep;
		
		$values = !is_array($this->value) && !strlen($this->value) ? array() : $this->value;
		
		// Split values if not already an array
		if ( !is_array($values) )
		{
			$values = preg_split("/\s*".$record_sep."\s*/", $values);
		}
		//echo "<pre>"; print_r($values); echo "</pre>";


		$fieldname  = $this->name;
		$element_id = $this->id;
		
		// A drop-down selector to select new values
		if ($iselements)
		{
			// Not needed
		}
		else if ($issortable)
		{
			// Not real element, real element will be a hidden input
			$fieldname_selector = $fieldname.'[selector]';
			$element_id_selector = $element_id.'_selector';
		}
		else if ($ismultiple)
		{
			// Real multi-value element
			$fieldname_selector = $fieldname.'[]';
			$element_id_selector = $element_id;
		}
		else
		{
			// Real single-value element
			$fieldname_selector = $fieldname;
			$element_id_selector = $element_id;
		}


		$attribs = '';
		$selector_classes = array();
		
		if (!$iselements)
		{
			$options = array();
			foreach(static::$records as $i => $v)
			{
				$option = new stdClass;
				$options[$i] = $option;
				$option->text = JText::_($v);
				$option->value = $i;
			}
			//print_r($options); exit;
			
			if ( $ismultiple )
			{
				$size = (int) $attributes->size ? (int) $attributes->size : 8;
				$attribs .= ' multiple="multiple" size="'.$size.'" ';
			}
			else if ($issortable) {
				array_unshift($options, JHtml::_('select.option', '', JText::_($attributes->prompt_label ? $attributes->prompt_label : 'FLEXI_ADD_MORE')));
			}
			else {  // Single drop down select
				array_unshift($options, JHtml::_('select.option', '', JText::_($attributes->prompt_label ? $attributes->prompt_label : 'FLEXI_SELECT')));
			}
			$selector_classes[] = 'use_select2_lib';
			if (!empty($selector_classes)) $attribs .= ' class ="'.implode(' ', $selector_classes).'"';
			
			$html = $sorter_html = '';
			if ($attributes->onchange) {
				$attribs .= ' onchange="'.$attributes->onchange.'"';
			}
			
			else if ($attributes->appendtofield) {
				$attribs .= ' onchange="fcrecord_add2list(\'jform_attribs_'.$attributes->appendtofield.'\', this);"';
			}
			
			if ($issortable)
			{
				$attribs .= ' onchange="fcrecord_add_sortable_element(this);"';
			}
		}
		
		if ($issortable)
		{
			$classes = 'records_container' . ($attributes->class ? ' '.$attributes->class : '');

			$skip_initial_list = strlen($attributes->skip_initial_list) ? (int) $attributes->skip_initial_list : ($iselements ? 1 : 0);

			$add_end    = strlen($attributes->add_end)    ? (int) $attributes->add_end : 0;
			$add_before = strlen($attributes->add_before) ? (int) $attributes->add_before : $iselements;
			$add_after  = strlen($attributes->add_after)  ? (int) $attributes->add_after  : $iselements;
			
			$edit_inline = strlen($attributes->edit_inline) ? (int) $attributes->edit_inline : 0;
			$edit_popup  = strlen($attributes->edit_popup)  ? (int) $attributes->edit_popup  : $iselements;
			$raw_inline  = strlen($attributes->raw_inline)  ? (int) $attributes->raw_inline  : 0;
			$raw_popup   = strlen($attributes->raw_popup)   ? (int) $attributes->raw_popup   : $iselements;

			$cascaded_prop  = strlen($attributes->cascaded_prop) ? (int) $attributes->cascaded_prop : -1;

			$list_attrs =
				($add_after  ? ' data-add_after="1" ' : '') .
				($add_before  ? ' data-add_before="1" ' : '') .
				($iselements ? ' data-is_elements="1" ' : '') .
				($record_sep ? ' data-record_sep="'.$record_sep.'" ' : '') .
				($props_sep ? ' data-props_sep="'.$props_sep.'" ' : '') .
				($attributes->prop_widths ? ' data-prop_widths="'.$attributes->prop_widths.'" ' : '') .
				($attributes->props_used ? ' data-props_used="'.$attributes->props_used.'" ' : '') .
				($attributes->master_fieldname ? ' data-master_fieldname="'.$attributes->master_fieldname.'" ' : '') .
				($attributes->cascaded_prop ? ' data-cascaded_prop="'.$attributes->cascaded_prop.'" ' : '') .
				($attributes->state_fieldname ? ' data-state_fieldname="'.$attributes->state_fieldname.'" ' : '') .
				($attributes->state_prop ? ' data-state_prop="'.$attributes->state_prop.'" ' : '') .
				' data-value_element_id="'.$element_id.'" ';

			$props_header = array();
			if ($iselements)
			{
				$props_used = $attributes->props_used ? preg_split("/\s*,\s*/", $attributes->props_used) : array();

				$prop_widths = $attributes->prop_widths ? preg_split("/\s*,\s*/", $attributes->prop_widths) : array();
				$prop_lbls = $attributes->prop_lbls ? preg_split("/\s*,\s*/", $attributes->prop_lbls) : array();

				if (count($prop_lbls)) foreach($prop_lbls as $i => $prop_lbl)
				{
					$_unused = empty($props_used[$i]);

					$_classes = $_unused ? ' fcrec_unused' : '';
					$_styles  = isset($prop_widths[$i]) ? ' width:'.$prop_widths[$i].'!important;' : '';

					$props_header[] = '<span class="fcrecord_label label '.($cascaded_prop==$i ? ' fcrec_cascaded_col' : '').$_classes.'" style="'.$_styles.'">'.JText::_($prop_lbl).'</span>';
				}
			}

			$sortable_id = 'sortable-'.$element_id;

			$sorter_html  = '
			'.($iselements ? '
				<div class="'.($attributes->editbtns_class ? ' '.$attributes->editbtns_class : '').'">
					<span class="btn-group">'.
						($edit_inline ? '
						<span class="btn fcrecords_show_btn" title="'.JText::_('FLEXI_SHOW').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 0, jQuery(this), jQuery(this).next());">
							<span class="icon-downarrow"></span>'.JText::_('FLEXI_EDIT_PROPERTIES').'
						</span>
						<span class="btn fcrecords_hide_btn" title="'.JText::_('FLEXI_HIDE').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 0, jQuery(this).prev(), jQuery(this));" style="display:none;">
							<span class="icon-uparrow"></span>'.JText::_('FLEXI_HIDE_PROPERTIES').'
						</span>
						' : '').
						($edit_popup ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 1); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="icon-pencil" style="font-size: 80%;"></span>'.JText::_('FLEXI_EDIT').'
						</span>
						' : '').
						($raw_inline ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').'" onclick="fcrecord_direct_edit(jQuery(\'#'.$sortable_id.'\'), 0); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="icon-paragraph-justify"></span>'.JText::_('FLEXI_RAW_EDIT').'
						</span>
						' : '').
						($raw_popup ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').' '.JText::_('FLEXI_RAW_EDIT').'" onclick="fcrecord_direct_edit(jQuery(\'#'.$sortable_id.'\'), 1); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="'.($raw_inline ? 'icon-expand-2' : 'icon-paragraph-justify').'"></span>'.($raw_inline ? JText::_('FLEXI_POPUP').' ' : '').JText::_('FLEXI_RAW_EDIT').'
						</span>
						' : '').'
					</span>
					<div class="fcclear"></div>
				</div>
				<div>
					<textarea name="'.$fieldname.'" id="'.$element_id.'" class="fcrecords_textarea'.($attributes->value_area_class ? ' '.$attributes->value_area_class : '').'" style="min-height: 64px;">'.$this->value.'</textarea>
					<p class="fcrecords_desc alert alert-info">'.JText::_($attributes->description).'</p>
				</div>'
			: '
				<input type="text" id="'.$element_id.'" name="'.$fieldname.'" value="'.implode(',', $values).'" class="fc_hidden_value" />'
			).'
			<div class="fcclear"></div>

			<div class="'.($attributes->editbtns_class ? ' '.$attributes->editbtns_class : '').'">
				<div class="'.$classes.'" style="'.($skip_initial_list ? 'display: none;' : '').'">
					<div class="fcrec_general_msg alert alert-info" style="display: none;"></div>
					<div class="fcclear"></div>
					<div class="fcrec_cascaded_msg alert alert-info" style="display: none;"></div>
					<div class="fcclear"></div>
					<div class="fcrec_state_msg alert alert-info" style="display: none;"></div>
					'.($props_header ? '<span class="fcrecord_header"> '.implode('', $props_header).'</span>' : '').'
					<ul id="'.$sortable_id.'" class="fcrecords fcrecords_list" '.$list_attrs.'>';

			$_unused_col = ' readonly="readonly" placeholder="'.JText::_('FLEXI_NA').'" ';
			
			if (!$skip_initial_list) foreach($values as $val)
			{
				if ($iselements)
				{
					$_v = & $val;
					$props = $props_sep ? preg_split("/\s*".$props_sep."\s*/", $_v) : array($_v);
					$props_html = array();
					foreach ($prop_lbls as $i => $prop_lbl)
					{
						$prop_v = isset($props[$i]) ? $props[$i] : '';
						$_unused = empty($props_used[$i]);

						$_classes = $_unused ? ' fcrec_unused' : '';
						$_styles  = isset($prop_widths[$i]) ? ' width:'.$prop_widths[$i].'!important;' : '';

						$props_html[] = '<input type="text"  value="'.htmlspecialchars( $prop_v, ENT_COMPAT, 'UTF-8' ).'" class="fcrecord_prop '.$_classes.'" onblur="fcrecord_store_values(jQuery(this).closest(\'ul\'));" style="'.$_styles.'" '.($_unused ? $_unused_col : '').'/>';
					}
				}
				else {
					if( !isset(static::$records[$val]) ) continue;
					$_v = & $options[$val]->text;
				}

				$sorter_html .= '
						<li class="fcrecord" data-value="'.$val.'">
							'.($iselements ?
								implode(' ', $props_html) :
								'<span class="fcprop_box">'.$_v.'</span>'
							).'
							<span class="delfield_handle" title="'.JText::_('FLEXI_REMOVE').'" onclick="fcrecord_del_sortable_element(this);"></span>
							'.($add_after  ? '<span class="addfield_handle fc_after"  title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 1);"></span>' : '').'
							'.($add_before ? '<span class="addfield_handle fc_before" title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 0);"></span>' : '').'
							'.($iselements ? '<span class="ordfield_handle" title="'.JText::_('FLEXI_MOVE').'"></span>' : '').'
						</li>';
			}
			$sorter_html .= '
					</ul>
				'.($add_end ? '<span class="btn" title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(\'#'.$sortable_id.'\'), 1);" style="float:right;"><span class="icon-new"></span>'.JText::_('FLEXI_ADD').'</span>' : '').'
				</div>
			</div>
			<div class="fcclear"></div>';
			
			$js = "";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);
		}

		return
			($iselements ? '' : JHtml::_('select.genericlist', $options, $fieldname_selector, $attribs, 'value', 'text', ($issortable ? array() : $values), $element_id_selector) )
			.$sorter_html;
	}
}
