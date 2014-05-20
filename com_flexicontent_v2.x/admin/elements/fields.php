<?php
/**
 * @version 1.5 stable $Id: fields.php 1683 2013-06-02 07:51:11Z ggppdk $
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
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFields extends JFormField
{
	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$type = 'Fields';

	function getInput()
	{
		$doc	= JFactory::getDocument();
		$db		= JFactory::getDBO();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		
		// *******************************************************************
		// Option labels and option values (for the created SELECT form field)
		// *******************************************************************
		
		$otext  = ((boolean) @ $attributes['fieldnameastext'])  ?  "CONCAT(label, ' [', `name`, ']')"  :  'label';
		$ovalue = ((boolean) @ $attributes['fieldnameasvalue']) ?  '`name`' : 'id';  // for old FC versions compatibility, second option must be like id
		
		
		// *********************************
		// IS advanced filter / search FLAGs
		// *********************************
		
		$and = '';
		$isnotcore = (boolean) @ $attributes['isnotcore'];
		if ($isnotcore) {
			$and .= ' AND iscore = 0';
		}
		$isadvsearch = (int) @ $attributes['isadvsearch'];
		if($isadvsearch) {
			$and .= ' AND isadvsearch='.$isadvsearch;
		}
		$isadvfilter = (int) @ $attributes['isadvfilter'];
		if($isadvfilter) {
			$and .= ' AND isadvfilter='.$isadvfilter;
		}
		
		
		// ********************************
		// INCLUDE/EXCLUDE some field types
		// ********************************
		
		$field_type = (string) @ $attributes['field_type'];
		if($field_type) {
			$field_type = explode(",", $field_type);
			foreach($field_type as $i => $ft) $field_type_quoted[$i] = $db->Quote($ft);
			$and .= " AND field_type IN (". implode(",", $field_type_quoted).")";
		}
		$exclude_field_type = (string) @ $attributes['exclude_field_type'];
		if($exclude_field_type) {
			$exclude_field_type = explode(",", $exclude_field_type);
			foreach($exclude_field_type as $i => $ft) $exclude_field_type_quoted[$i] = $db->Quote($ft);
			$and .= " AND field_type NOT IN (". implode(",", $exclude_field_type_quoted).")";
		}
		$orderable = (int) @ $attributes['orderable'];
		if ($orderable) {
			$non_orderable = $cparams->get('non_orderable_types', 'toolbar,file,image,groupmarker,fcpagenav,minigallery,weblink,email');
			$non_orderable = explode(",", $non_orderable);
			foreach($non_orderable as $i => $ft) $non_orderable_quoted[$i] = $db->Quote($ft);
			$and .= " AND field_type NOT IN (". implode(",", $non_orderable_quoted).")";
		}
		
		
		// **************************
		// Retrieve field data for DB
		// **************************
		
		$query = 'SELECT '.$ovalue.' AS value, '.$otext.' AS text'
			.' FROM #__flexicontent_fields'
			.' WHERE published = 1'
			. $and
			.' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();
		
		
		// ***********************************
		// Values, form field name and id, etc
		// ***********************************
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$name = FLEXI_J16GE ? $attributes['name'] : $name;
		$control_name = FLEXI_J16GE ? str_replace($name, '', $element_id) : $control_name;
		
		
		// *******************************************
		// HTML Tag parameters parameters, and styling
		// *******************************************
		
		$attribs = ' style="float:left;" ';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			if ((boolean) @ $attributes['displa_useglobal']) {
				array_unshift($fields, JHTML::_('select.option', '' , JText::_('FLEXI_USE_GLOBAL')));
				array_unshift($fields, JHTML::_('select.option', '0', JText::_('FLEXI_NOT_SET')));   // Compatibility with older FC versions
			} else {
				array_unshift($fields, JHTML::_('select.option', '0', JText::_('FLEXI_PLEASE_SELECT')));
			}
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}
		
		if ($onchange = @$attributes['onchange']) {
			$onchange = str_replace('{control_name}', $control_name, $onchange);
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		
		// ***********************
		// Create the field's HTML
		// ***********************
		
		$html = JHTML::_('select.genericlist', $fields, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}
