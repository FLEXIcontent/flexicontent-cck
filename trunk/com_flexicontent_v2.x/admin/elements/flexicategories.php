<?php
/**
 * @version 1.5 stable $Id: qfcategory.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
/**
 * Renders an Item element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldFlexicategories extends JFormField
{
   /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Flexicategories';

	
	function getInput() {
		static $function_added = false;
		$node = & $this->element;
		
		$doc 		=& JFactory::getDocument();
		$fieldName	= $this->name;
		$values = $this->value;
		//var_dump($values);
		if ( ! is_array( $values ) ) {
			$values = explode(",", $values);
		}
		
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
		
		$attribs = '';
		if ($node->getAttribute('size')) {
			$attribs .= ' size="'.$node->attributes('size').'" ';
		} else {
			$attribs .= ' size="8" ';
		}
		if ( $node->getAttribute('multiple') && $node->getAttribute('multiple')=='true' ) {
			$attribs .=' multiple="multiple"';
			$fieldName .= "[]";
		}

		$classes = '';
		if ( $node->getAttribute('required') && $node->getAttribute('required')=='true' ) {
			$classes .= ' required';
		}
		if ( $node->getAttribute('validation_class') ) {
			$classes .= ' '.$node->getAttribute('validation_class');
		}
		
		$top = false;
		if ( $node->getAttribute('top') ) {
			$top = $node->getAttribute('top');
		}
		
		$ffname = $node->getAttribute('name');
		$html = flexicontent_cats::buildcatselect($tree, $fieldName, $values, $top,
			/*' onClick="javascript:FLEXIClickCategory(this,\''.$ffname.'\');"*/
			' class="inputbox '.$classes.'" '.$attribs,
			false, true, $actions_allowed=array('core.create') );
		//$html .= "\n<input type=\"hidden\" id=\"a_id_{$ffname}\" name=\"$fieldName\" value=\"$values\" />";
		return $html;
	}
}
?>
