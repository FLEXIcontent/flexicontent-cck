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
		$doc 		=& JFactory::getDocument();
		
		//var_dump($this->value);
		if ( ! is_array( $this->value ) ) {
			$values = explode("|", $this->value);
		} else {
			$values = $this->value;
		}
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();
		$js =  "function FLEXIClickCategory(obj) {
			values=new Array();
			for(i=0,j=0;i<obj.options.length;i++) {
				if(obj.options[i].selected==true)
					values[j++] = obj.options[i].value;
			}
			values = values.concat();
			//document.getElementById('a_id').value = values;
		}";
		$doc->addScriptDeclaration($js);
		$html = flexicontent_cats::buildcatselect($tree, $this->name, $values, false, ' onClick="javascript:FLEXIClickCategory(this);" class="inputbox validate-cid" multiple="multiple" size="8"', true);
		//$html = flexicontent_cats::buildcatselect($tree, $this->name, $this->value, false, ' class="inputbox validate-cid" multiple="multiple" size="8"', true);
		//$html .= "\n<input type=\"hidden\" id=\"a_id\" name=\"jform[request][".$this->element["name"]."]\" value=\"".implode(",", $values)."\" />";
		return $html;
	}
}
?>