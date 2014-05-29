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
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
}

/**
 * Renders a filter element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFilters extends JFormFieldList
{
	/**
	 * JFormField type
	 * @access	protected
	 * @var		string
	 */
	
	protected $type = 'Filters';

	function add_css_js() {
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();

		$document = JFactory::getDocument();
		$js = "
			var sorttable_fcfield_lists = '';
			
			/* unused to be removed */
			function fcfield_add2list(list_tagid, selector){
				var list = jQuery('#'+list_tagid);
				var sep = list.val().trim() ? ', ' : '';
				val = list.val() +  sep + jQuery(selector).val();
				list.val(val);
			}
			
			function fcfield_del_sortable_element(obj){
				var element = jQuery(obj).parent();
				var parent_element = jQuery(element.parent());
				element.remove();
				storeordering( parent_element );
			}
			
			function fcfield_add_sortable_element(selector){
				var selobj = jQuery(selector);
				var tagid  = selobj.attr('id').replace('_selector','');
				var container = 'sortable-' + tagid;
				
				var val = selobj.val();
				if (!val) return;
				var lbl = selobj.find('option:selected').text();
				jQuery('#'+container).append('<li id=\"field_'+val+'\" class=\"fields delfield\">'+lbl+
				'<a title=\"".JText::_('FLEXI_REMOVE')."\" align=\"right\" onclick=\"javascript:fcfield_del_sortable_element(this);\" class=\"deletetag\" href=\"javascript:;\"></a>'+
				'</li>');
				
				var field_list = jQuery('#'+tagid).val();
				field_list += field_list ? ','+val : val;
				jQuery('#'+tagid).val(field_list);
				selobj.prop('selectedIndex',0);
			}
			
			function storeordering(parent_element) {
				hidden_id = '#'+jQuery.trim(parent_element.attr('id').replace('sortable-',''));
				fields = new Array();
				i = 0;
				parent_element.children('li').each(function(){
					fields[i++] = jQuery(this).attr('id').replace('field_', '');
				});
				jQuery(hidden_id).val(fields.join(','))
			}
			
			jQuery(document).ready(function() {
			
				jQuery( sorttable_fcfield_lists ).each(function(index, value) {
					storeordering(jQuery(this));
				});
				
				jQuery( sorttable_fcfield_lists ).sortable({
					connectWith: sorttable_fcfield_lists,
					update: function(event, ui) {
						if(ui.sender) {
							storeordering(jQuery(ui.sender));
						}else{
							storeordering(jQuery(ui.item).parent());
						}
					}
				});
				
			});
		";
		if ($js) $document->addScriptDeclaration($js);
	}
	
	
	function getInput()
	{
		static $js_css_added = null;
		if ($js_css_added===null) {
			$this->add_css_js();
			$js_css_added = true;
		}
		
		$doc	= JFactory::getDocument();
		$db		= JFactory::getDBO();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$and = ((boolean)@$attributes['isnotcore']) ? ' AND iscore = 0' : '';
		if ((boolean)@$attributes['fieldnameastext']) {
			$text = 'CONCAT(label, \'(\', `name`, \')\')';
		} else {
			$text = 'label';
		}
		if ((boolean)@$attributes['fieldnameasvalue']) {
			$ovalue = 'name';
		} else {
			$ovalue = 'id';  // ELSE should always be THIS , otherwise we break compatiblity with all previous FC versions
		}
		
		$issearch = @$attributes['issearch'];
		if($issearch) {
			$and .= " AND issearch='{$issearch}'";
		}
		
		$isadvsearch = @$attributes['isadvsearch'];
		if($isadvsearch) {
			$and .= " AND isadvsearch='{$isadvsearch}'";
		}
		
		$isadvfilter = @$attributes['isadvfilter'];
		if($isadvfilter) {
			$and .= " AND isadvfilter='{$isadvfilter}'";
		}
		
		$isfilter = (int) @$attributes['isfilter'];
		if ( $isfilter || (!$issearch && !$isadvsearch && !$isadvfilter)) {
			$and .= " AND isfilter='1'";
		}
		
		$field_type = @$attributes['field_type'];
		if($field_type) {
			$field_type = explode(",", $field_type);
			$and .= " AND field_type IN ('". implode("','", $field_type)."')";
		}
		
		$exclude_field_type =  @$attributes['exclude_field_type'];
		if($exclude_field_type) {
			$exclude_field_type = explode(",", $exclude_field_type);
			$and .= " AND field_type NOT IN ('". implode("','", $exclude_field_type)."')";
		}
		
		// Limit to current type
		$tid_var =  @$attributes['type_id_variable'];
		$tid = 0;
		if($tid_var) {
			$tid = JRequest::getVar($tid_var, 0);
			if ( is_array($tid)) $tid = (int) reset($tid);
			else $tid = (int) $tid;
			if ($tid) $and .= " AND ftr.type_id=" . $tid;
		}
		
		$query = 'SELECT f.*, f.'.$ovalue.' AS value, f.label AS text '
		. ' FROM #__flexicontent_fields AS f '
		.($tid ? ' JOIN #__flexicontent_fields_type_relations AS ftr ON ftr.field_id = f.id' : '')
		. ' WHERE published = 1'
		. $and
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList($ovalue);
		//echo "<pre>"; print_r($fields); exit;
		
		$values = FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) ) {
			$values = array();
		}
		if ( !is_array($values) ) {
			$values = preg_split("/[\|,]/", $values);;
		}
		//echo "<pre>"; print_r($values); exit;
		
		$issortable = @$attributes['issortable'];
		// Not set mean make sortable
		$issortable = $issortable === null ? 1 : $issortable;
		$_suffix = $issortable ? '_selector' : '';
		
		$_fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$fieldname	= FLEXI_J16GE ? $this->name.$_suffix : $control_name.'['.$name.$_suffix.']';
		
		$_element_id = $element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		$element_id .= $_suffix;
		
		
		$attribs = ' style="float:left;" ';
		$options = $fields;
		foreach($options as $option) {
			$option->text = JText::_($option->text);
		}
		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="8" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$onclick = ""
				."${element_id} = document.getElementById(\"${element_id}\");"
				."if (${element_id}.size<20) {"
				."	${element_id}_oldsize = ${element_id}.size;"
				."	${element_id}.size=20;"
				."} else {"
				."	${element_id}.size = ${element_id}_oldsize;"
				."}"
				."parent = ${element_id}.getParent(); upcnt=0;"
				."while(upcnt<10 && !parent.hasClass(\"jpane-slider\")) {"
				."	upcnt++; parent = parent.getParent();"
				."}"
				."if (parent.hasClass(\"jpane-slider\")) parent.setStyle(\"height\", \"auto\");"
			;
			$style = 'display:inline-block;'.(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px');
			$maximize_link = "<a style='$style' href='javascript:;' onclick='$onclick' >Maximize/Minimize</a>";
		} else {
			array_unshift($options, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}
		
		$html = $sorter_html = $tip = '';
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		else if ($appendtofield = @$attributes['appendtofield']) {
			$appendtofield = FLEXI_J16GE ? 'jform_attribs_'.$appendtofield : 'params'.$appendtofield;
			$onchange = 'fcfield_add2list(\''.$appendtofield.'\', this);';
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		if ($issortable) {
			$sortable_id = 'sortable-'.$_element_id;
			
			$onchange = 'fcfield_add_sortable_element(this);';
			$attribs .= ' onchange="'.$onchange.'"';
			
			$sorter_html  = '<div class="clear"></div>';
			$sorter_html .= '<div class="positions_container" style="width: auto!important; margin:6px; min-width:100%; min-height:64px; overflow-y:hidden!important">';
			$sorter_html .= '<ul id="'.$sortable_id.'" class="positions"> ';
			foreach($values as $val) {
				if( !isset($fields[$val]) ) continue;
				$sorter_html .= '<li id="field_'.$val.'" class="fields delfield">';
				$sorter_html .= $fields[$val]->text;
				$sorter_html .= '<a title="'.JText::_('FLEXI_REMOVE').'" align="right" onclick="javascript:fcfield_del_sortable_element(this);" class="deletetag" href="javascript:;"></a>';
				$sorter_html .= '</li>';
			}
			$sorter_html .= '</ul></div>';
			$sorter_html .= '<div class="clear"></div>';
			$sorter_html .= '<input type="hidden" value="'.implode(',', $values).'" id="'.$_element_id.'" name="'.$_fieldname.'" />';
			
			$js = "
				if (sorttable_fcfield_lists)
					sorttable_fcfield_lists = sorttable_fcfield_lists + ',#".$sortable_id."';
				else
					sorttable_fcfield_lists = '#".$sortable_id."';
			";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);
		}
		
		$html = JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', ($issortable ? array() : $values), $element_id);
		/*if ($ordertip = @$attributes['ordertip']) {
			if ($ordertip != -1) {
				$style = 'display:inline-block;'.(FLEXI_J16GE ? 'float:left; margin: 0px 0px 0px 18px;':'margin:0px 0px 6px 12px');
				$tip = 
					"<span class='editlinktip hasTip' style='$style' title='".htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8')."::".htmlspecialchars(JText::_( 'FLEXI_SETTING_DEFAULT_FILTER_ORDER' ), ENT_COMPAT, 'UTF-8')."'>"
						.JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) )
					."</span>";
			}
		}*/
		
		$html =
		'<div style="border-width:0px; margin:0px; padding:0px; width:68%; float:left;">'.
			$html.$maximize_link.$tip.$sorter_html.
		'</div>';
		return $html;
	}
}
?>