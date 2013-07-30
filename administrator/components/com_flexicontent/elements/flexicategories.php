<?php
/**
 * @version 1.5 stable $Id: flexicategories.php 967 2011-11-21 00:01:36Z ggppdk $
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

JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
// Load the category class
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');

/**
 * Renders a category list
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.5
 */
class JElementFlexicategories extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$_name = 'Flexicategories';

	function fetchElement($name, $value, &$node, $control_name)
	{
		static $function_added = false;
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( !empty($attributes['joinwith']) ) {
			$values = explode( $attributes['joinwith'],  $values );
		}
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		$ffname = @$attributes['name'];
		
		$published_only = (boolean) @$attributes['published_only'];
		$parent_id   = (int) @$attributes['parent_id'];
		$depth_limit = (int) @$attributes['depth_limit'];
		$tree = flexicontent_cats::getCategoriesTree($published_only, $parent_id, $depth_limit);
		
		$attribs = '';
		
		// Steps needed for multi-value select field element, e.g. code to maximize select field
		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="8" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$onclick = ""
				."${element_id} = document.getElementById(\"${element_id}\");"
				."if (${element_id}.size<40) {"
				."	${element_id}_oldsize = ${element_id}.size;"
				."	${element_id}.size=40;"
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
			$maximize_link = '';
		}
		
		$top = @$attributes['top'] ? $attributes['top'] : false;
		
		$classes = ' inputbox ';
		$classes .= ( @$attributes['required'] && @$attributes['required']!='false' ) ? ' required' : '';
		$classes .= $node->attributes('validation_class') ? ' '.$node->attributes('validation_class') : '';
		$classes = ' class="'.$classes.'"';
		$attribs .= $classes .' style="float:left;" ';
		
		
		// Add onClick functions (e.g. joining values to a string)
		if ( !empty($attributes['joinwith']) && !$function_added) {
			$function_added = true;
			$js = "
			function FLEXIClickCategory(obj, name) {
				values=new Array();
				for(i=0,j=0;i<obj.options.length;i++) {
					if(obj.options[i].selected==true)
						values[j++] = obj.options[i].value;
				}
				value_list = values.join(',');
				document.getElementById('a_id_'+name).value = value_list;
				//alert(document.getElementById('a_id_'+name).value);
			}";
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration($js);
		}
		
		$html = '';
		if ( !empty($attributes['joinwith']) ) {
			$select_fieldname = '_'.$ffname.'_';
			$text_fieldname = str_replace('[]', '', $fieldname);
			
			$attribs .= ' onclick="FLEXIClickCategory(this,\''.$ffname.'\');" ';
			$html    .= "\n<input type=\"hidden\" id=\"a_id_{$ffname}\" name=\"$text_fieldname\" value=\"".@$values[0]."\" />";
		} else {
			$select_fieldname = $fieldname;
		}
		
		$html .= flexicontent_cats::buildcatselect
		(
			$tree, $select_fieldname, $values, $top, $attribs,
			false, true, $actions_allowed=array('core.create')
		);
		
		return $html.$maximize_link;
	}
}
?>