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
jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

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
				'<a title=\"".JText::_('FLEXI_REMOVE')."\" align=\"right\" onclick=\"javascript:fcfield_del_sortable_element(this);\" class=\"delfield_handle\" href=\"javascript:;\"></a>'+
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
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$and = ((boolean)@$attributes['isnotcore']) ? ' AND iscore = 0' : '';
		if ((boolean)@$attributes['fieldnameastext']) {
			$otext = "CONCAT(label, ' [', `name`, ']')";
		} else {
			$otext = "label";
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
		
		$isfilter = strlen(@$attributes['isfilter']) ? (int)$attributes['isfilter'] : 1;  // Force 'isfilter' if not set
		if ($isfilter) {
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
		
		
		// Get field data
		$query = 'SELECT f.*, f.'.$ovalue.' AS value,'.$otext.' AS text, f.id '
			.' FROM #__flexicontent_fields AS f '
			.($tid ? ' JOIN #__flexicontent_fields_type_relations AS ftr ON ftr.field_id = f.id' : '')
			. ' WHERE published = 1'
			.$and
			.' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList($ovalue);
		
		
		// Handle fields having the same label
		$_keys = array_keys($fields);
		$_total = count($fields);
		$_dupls = array();
		foreach($_keys as $i => $key)
		{
			if ($i == $_total-1) continue;
			if ($fields[$key]->text == $fields[ $_keys[$i+1] ]->text)
			{
				$_dupls[ $key ] = $_dupls[ $_keys[$i+1] ] = 1;
			}
		}
		foreach($_dupls as $_dkey => $i)
		{
			$fields[$_dkey]->text .= ' :: ' .$fields[$_dkey]->id;
		}
		
		
		// Render parameters if needed
		$groupables = @$attributes['groupables'];
		if ($groupables) {
			$_fields = array();
			foreach($fields as $field) {
				$field->params = new JRegistry($field->attribs);
				if ($field->params->get('use_ingroup')) $_fields[$field->id] = $field;
			}
			$fields = $_fields;
		}
		
		
		$values = $this->value;
		if ( empty($values) ) {
			$values = array();
		}
		if ( !is_array($values) ) {
			$values = preg_split("/[\|,]/", $values);
		}
		//echo "<pre>"; print_r($values); exit;
		
		
		$issortable = @$attributes['issortable'];
		// Not set mean make sortable
		$issortable = $issortable === null ? 1 : $issortable;
		$suffix = $issortable ? 'selector' : '';
		
		$_fieldname	= $this->name;
		$fieldname	= $this->name.'['.$suffix.']';
		
		$_element_id = $this->id;
		$element_id  = $this->id.'_'.$suffix;
		
		
		$attribs = ' style="float:left;" ';
		$options = $fields;
		foreach($options as $option) {
			$option->text = JText::_($option->text);
		}
		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="8" ';
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
			$style = 'display:inline-block; float:left; margin: 6px 0px 0px 18px;';
		} else {
			array_unshift($options, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
		}
		
		$html = $sorter_html = $tip = '';
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		else if ($appendtofield = @$attributes['appendtofield']) {
			$appendtofield = 'jform_attribs_'.$appendtofield;
			$onchange = 'fcfield_add2list(\''.$appendtofield.'\', this);';
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		if ($issortable) {
			$sortable_id = 'sortable-'.$_element_id;
			
			$onchange = 'fcfield_add_sortable_element(this);';
			$attribs .= ' onchange="'.$onchange.'"';
			
			$classes = "positions_container";
			if ($class = @$attributes['class']) {
				$classes .= ' '.$class;
			}
			$sorter_html  = '<div class="clear"></div>';
			$sorter_html .= '<div class="'.$classes.'" style="margin:6px; min-height:64px; overflow-y:hidden!important">';
			$sorter_html .= '<ul id="'.$sortable_id.'" class="positions"> ';
			foreach($values as $val) {
				if( !isset($fields[$val]) ) continue;
				$sorter_html .= '<li id="field_'.$val.'" class="fields delfield">';
				$sorter_html .= $fields[$val]->text;
				$sorter_html .= '<a title="'.JText::_('FLEXI_REMOVE').'" align="right" onclick="javascript:fcfield_del_sortable_element(this);" class="delfield_handle" href="javascript:;"></a>';
				$sorter_html .= '</li>';
			}
			$sorter_html .= '</ul>';
			$sorter_html .= '<input type="hidden" value="'.implode(',', $values).'" id="'.$_element_id.'" name="'.$_fieldname.'" />';
			$sorter_html .= '</div>';
			$sorter_html .= '<div class="clear"></div>';
			
			$js = "
				if (sorttable_fcfield_lists)
					sorttable_fcfield_lists = sorttable_fcfield_lists + ',#".$sortable_id."';
				else
					sorttable_fcfield_lists = '#".$sortable_id."';
			";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);
		}
		
		/*if ($ordertip = @$attributes['ordertip'])
		{
			$style = 'display:inline-block; float:left; margin: 0px 0px 0px 18px;';
			$tip = '
			<span class="editlinktip hasTooltip" style="'.$style.'" title="'.JHtml::tooltipText('FLEXI_NOTES', 'FLEXI_SETTING_DEFAULT_FILTER_ORDER', 1, 1).'">
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
?>