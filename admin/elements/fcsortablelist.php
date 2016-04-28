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
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();

		$document = JFactory::getDocument();
		$js = "
			var sorttable_fcrecord_lists = '';
			
			/* unused to be removed */
			function fcrecord_add2list(list_tagid, selector){
				var list = jQuery('#'+list_tagid);
				var sep = list.val().trim() ? ', ' : '';
				val = list.val() +  sep + jQuery(selector).val();
				list.val(val);
			}
			
			function fcrecord_del_sortable_element(obj){
				var element = jQuery(obj).parent();
				var parent_element = jQuery(element.parent());
				element.remove();
				fcrecord_storeordering( parent_element );
			}
			
			function fcrecord_add_sortable_element(selector){
				var selobj = jQuery(selector);
				var tagid  = selobj.attr('id').replace('_selector','');
				var container = 'sortable-' + tagid;
				
				var val = selobj.val();
				if (!val) return;
				var lbl = selobj.find('option:selected').text();
				jQuery('#'+container).append('<li id=\"field_'+val+'\" class=\"fields delfield\">'+lbl+
				'<a title=\"".JText::_('FLEXI_REMOVE')."\" align=\"right\" onclick=\"javascript:fcrecord_del_sortable_element(this);\" class=\"delfield_handle\" href=\"javascript:;\"></a>'+
				'</li>');
				
				var field_list = jQuery('#'+tagid).val();
				field_list += field_list ? ','+val : val;
				jQuery('#'+tagid).val(field_list);
				if (selobj.hasClass('use_select2_lib')) {
					selobj.select2('val', '');
					selobj.prev().find('.select2-choice').removeClass('fc_highlight');
				} else
					selobj.prop('selectedIndex',0);
			}
			
			function fcrecord_storeordering(parent_element) {
				hidden_id = '#'+jQuery.trim(parent_element.attr('id').replace('sortable-',''));
				fields = new Array();
				i = 0;
				parent_element.children('li').each(function(){
					fields[i++] = jQuery(this).attr('id').replace('field_', '');
				});
				jQuery(hidden_id).val(fields.join(','))
			}
			
			jQuery(document).ready(function() {
			
				jQuery( sorttable_fcrecord_lists ).each(function(index, value) {
					fcrecord_storeordering(jQuery(this));
				});
				
				jQuery( sorttable_fcrecord_lists ).sortable({
					connectWith: sorttable_fcrecord_lists,
					update: function(event, ui) {
						if (ui.sender)
							fcrecord_storeordering(jQuery(ui.sender));
						else
							fcrecord_storeordering(jQuery(ui.item).parent());
					}
				});
				
			});
		";
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
		$db		= JFactory::getDBO();
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		
		$values = $this->value;
		if ( empty($values) ) {
			$values = array();
		}
		if ( !is_array($values) ) {
			$values = preg_split("/[\|,]/", $values);
		}
		//echo "<pre>"; print_r($values); exit;
		
		$subtype = @$attributes['subtype'];
		$issortable = $subtype =='sortable';
		$ismultiple = $subtype =='multiple';
		
		//if (!$ismultiple)  $issortable = 0;  // Disable sortable for multiple field, only USE issortable 
		if ($issortable) $ismultiple = 0;   // Disable multiple display if field is sortable, because multiple value will go inside the sortable container
		
		$suffix = $issortable ? 'selector' : '';
		
		$_fieldname	= $this->name;
		if ($issortable)
			$fieldname = $this->name.'['.$suffix.']';
		else if ($ismultiple)
			$fieldname = $this->name.'[]';
		else
			$fieldname = $this->name;
		
		$_element_id = $this->id;
		$element_id  = $this->id.'_'.$suffix;
		
		$attribs = ' style="float:left;" ';
		$selector_classes = array();
		$options = array();
		foreach(static::$records as $i => $v) {
			$option = new stdClass;
			$options[$i] = $option;
			$option->text = JText::_($v);
			$option->value = $i;
		}
		//print_r($options); exit;
		
		$prompt_label = @$attributes['prompt_label'];
		if ( $ismultiple ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= @$attributes['size'] ? ' size="'.$attributes['size'].'" ' : ' size="8" ';
		}
		else if ($issortable) {
			array_unshift($options, JHTML::_('select.option', '', JText::_($prompt_label ? $prompt_label : 'FLEXI_ADD_MORE')));
		}
		else {  // Singular
			array_unshift($options, JHTML::_('select.option', '', JText::_($prompt_label ? $prompt_label : 'FLEXI_SELECT')));
		}
		$selector_classes[] = 'use_select2_lib';
		if (!empty($selector_classes)) $attribs = ' class ="'.implode(' ', $selector_classes).'"';
		
		$html = $sorter_html = $tip = '';
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		else if ($appendtofield = @$attributes['appendtofield']) {
			$appendtofield = 'jform_attribs_'.$appendtofield;
			$onchange = 'fcrecord_add2list(\''.$appendtofield.'\', this);';
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		if ($issortable)
		{
			$sortable_id = 'sortable-'.$_element_id;
			
			$onchange = 'fcrecord_add_sortable_element(this);';
			$attribs .= ' onchange="'.$onchange.'"';
			
			$classes = "positions_container";
			if ($class = @$attributes['class']) {
				$classes .= ' '.$class;
			}
			$sorter_html  = '<div class="clear"></div>';
			$sorter_html .= '<div class="'.$classes.'" style="margin:6px; min-height:64px; overflow-y:hidden!important">';
			$sorter_html .= '<ul id="'.$sortable_id.'" class="positions"> ';
			foreach($values as $val) {
				if( !isset(static::$records[$val]) ) continue;
				$sorter_html .= '<li id="field_'.$val.'" class="fields delfield">';
				$sorter_html .= $options[$val]->text;
				$sorter_html .= '<a title="'.JText::_('FLEXI_REMOVE').'" align="right" onclick="javascript:fcrecord_del_sortable_element(this);" class="delfield_handle" href="javascript:;"></a>';
				$sorter_html .= '</li>';
			}
			$sorter_html .= '</ul>';
			$sorter_html .= '<input type="hidden" value="'.implode(',', $values).'" id="'.$_element_id.'" name="'.$_fieldname.'" />';
			$sorter_html .= '</div>';
			$sorter_html .= '<div class="clear"></div>';
			
			$js = "
				if (sorttable_fcrecord_lists)
					sorttable_fcrecord_lists = sorttable_fcrecord_lists + ',#".$sortable_id."';
				else
					sorttable_fcrecord_lists = '#".$sortable_id."';
			";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);
		}
		
		/*if ($ordertip = @$attributes['ordertip'])
		{
			$style = 'display:inline-block; float:left; margin: 0px 0px 0px 18px;';
			$tip = '
			<span class="hasTooltip" style="'.$style.'" title="'.JHtml::tooltipText('FLEXI_NOTES', 'FLEXI_SETTING_DEFAULT_FILTER_ORDER', 1, 1).'">
				'.JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) ).'
			</span>';
		}*/
		
		return '
		<div style="border-width:0px; margin:0px; padding:0px; width:68%; float:left;">
			'.JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', ($issortable ? array() : $values), $element_id).'
			'.$tip.'
			'.$sorter_html.'
		</div>
		';
	}
}


