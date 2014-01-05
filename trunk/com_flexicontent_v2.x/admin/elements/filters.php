<?php
/**
 * @version 1.5 stable $Id: filters.php 1026 2011-12-07 09:15:07Z enjoyman@gmail.com $
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

	function getLabel()
	{
		// Initialize variables.
		$label = '';
		
		// Get the label text from the XML element, defaulting to the element name.
		$text = $this->element['label'] ? (string) $this->element['label'] : (string) $this->element['name'];
		
		// Build the class for the label.
		$class = !empty($this->description) ? 'hasTip' : '';
		$class = $this->required == true ? $class.' required' : $class;
		
		// Add the opening label tag and main attributes attributes.
		$label .= '<label style="display:inline-block !important; float:left !important;" id="'.$this->id.'-lbl" for="'.$this->id.'" class="'.$class.'"';
		
		// If a description is specified, use it to build a tooltip.
		if (!empty($this->description)) {
			$label .= ' title="'.htmlspecialchars(trim(JText::_($text), ':').'::' .
			JText::_($this->description), ENT_COMPAT, 'UTF-8').'"';
		}
		
		// Add the label text and closing tag.
		$label .= '>'.JText::_($text).'</label>';
		
		return $label;
	}
	
	function getInput()
	{
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
			$ovalue = '`name`';
		} else {
			$ovalue = 'id';  // ELSE should always be THIS , otherwise we break compatiblity with all previous FC versions
		}
		
		$isadvsearch = @$attributes['isadvsearch'];
		if($isadvsearch) {
			$and .= " AND isadvsearch='{$isadvsearch}'";
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
		
		$query = 'SELECT id AS value, label AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. ' AND isfilter=1'
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$attribs = ' style="float:left;" ';
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
			array_unshift($fields, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}

		$html = JHTML::_('select.genericlist', $fields, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		
		$style = 'display:inline-block;'.(FLEXI_J16GE ? 'float:left; margin: 0px 0px 0px 18px;':'margin:0px 0px 6px 12px');
		$tip = 
			"<span class='editlinktip hasTip' style='$style' title='".htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8')."::".htmlspecialchars(JText::_( 'FLEXI_SETTING_DEFAULT_FILTER_ORDER' ), ENT_COMPAT, 'UTF-8')."'>"
				.JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) )
			."</span>";
		
		return $html.$maximize_link.$tip;
	}
}
?>