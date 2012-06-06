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
		$doc 		=& JFactory::getDocument();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
				
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();
		/*if (!$function_added) {
			$function_added = true;
			$js = "
			function FLEXIClickCategory(obj, name) {
				values=new Array();
				for(i=0,j=0;i<obj.options.length;i++) {
					if(obj.options[i].selected==true)
						values[j++] = obj.options[i].value;
				}
				values = values.concat();
				document.getElementById('a_id_'+name).value = values;
			}";
			$doc->addScriptDeclaration($js);
		}*/
		
		$attribs = 'style="float:left;"';
		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .=' multiple="multiple"';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="8" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<40) { ${element_id}_oldsize=$element_id.size; $element_id.size=40;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			$maximize_link = '';
		}
		
		$classes = '';
		if ( @$attributes['required'] && @$attributes['required']!='false' ) {
			$classes .= ' required';
		}
		if ( $node->attributes('validation_class') ) {
			$classes .= ' '.$node->attributes('validation_class');
		}
		
		$top = false;
		if ( @$attributes['top'] ) {
			$top = @$attributes['top'];
		}
		
		$ffname = @$attributes['name'];
		$html = flexicontent_cats::buildcatselect($tree, $fieldname, $values, $top,
			/*' onClick="javascript:FLEXIClickCategory(this,\''.$ffname.'\');"*/
			' class="inputbox '.$classes.'" '.$attribs,
			false, true, $actions_allowed=array('core.create') );
		//$html .= "\n<input type=\"hidden\" id=\"a_id_{$ffname}\" name=\"$fieldname\" value=\"$values\" />";
		
		return $html.$maximize_link;
	}
}
?>