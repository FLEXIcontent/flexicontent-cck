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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

// Load the category class
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');

// Load the helper classes
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		
flexicontent_html::loadFramework('jQuery');
flexicontent_html::loadFramework('select2');

/**
 * Renders a category list
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.5
 */
class JFormFieldFlexicategories extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$type = 'Flexicategories';

	function getInput()
	{
		static $function_added = false;

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$values = $this->value;

		if (!empty($attributes['joinwith']))
		{
			$values = explode( $attributes['joinwith'],  $values );
		}

		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		$ffname = (string) $this->element['name'];
		
		$published_only = (boolean) @$attributes['published_only'];
		$parent_id   = (int) @$attributes['parent_id'];
		$depth_limit = (int) @$attributes['depth_limit'];
		$tree = flexicontent_cats::getCategoriesTree($published_only, $parent_id, $depth_limit);
		
		$attribs = '';
		
		// Steps needed for multi-value select field element, e.g. code to maximize select field
		$multiple = (string) $this->element['multiple'];
		$size = (int) $this->element['size'];

		$isMultiple = $multiple === 'multiple' ||  $multiple === 'true';
		
		if ($isMultiple)
		{
			$attribs .= ' multiple="multiple" ';
			$attribs .= ' size="' . ($size ?: 8). '" ';
		}
		
		$top = @$attributes['top'] ? $attributes['top'] : false;
		
		$classes = 'use_select2_lib ';
		$classes .= @$attributes['required'] && @$attributes['required']!='false' ? ' required' : '';
		$classes .= @$attributes['class'] ? ' '.$attributes['class'] : '';
		$classes = ' class="'.$classes.'"';
		$attribs .= $classes .' style="float:left;" ';
		
		
		// Add onclick functions (e.g. joining values to a string)
		if (!empty($attributes['joinwith']) && !$function_added)
		{
			$function_added = true;
			$js = '
			function FLEXIClickCategory(obj, name)
			{
				values = new Array();

				for (i = 0, j = 0; i < obj.options.length; i++)
				{
					if (obj.options[i].selected == true)
					{
						values[j++] = obj.options[i].value;
					}
				}

				value_list = values.join(\',\');
				document.getElementById(\'a_id_\' + name).value = value_list;
			}';

			JFactory::getDocument()->addScriptDeclaration($js);
		}
		
		$html = '';

		if (!empty($attributes['joinwith']))
		{
			$select_fieldname = '_'.$ffname.'_';
			$text_fieldname = str_replace('[]', '', $fieldname);
			
			$attribs .= ' onclick="FLEXIClickCategory(this,\''.$ffname.'\');" ';
			$html    .= "\n<input type=\"hidden\" id=\"a_id_{$ffname}\" name=\"$text_fieldname\" value=\"".@$values[0]."\" />";
		}
		else
		{
			$select_fieldname = $fieldname;
		}
		
		$html .= flexicontent_cats::buildcatselect
		(
			$tree, $select_fieldname, $values, $top, $attribs,
			false, true, $actions_allowed=array('core.create')
		);
		
		return $html;
	}
}
