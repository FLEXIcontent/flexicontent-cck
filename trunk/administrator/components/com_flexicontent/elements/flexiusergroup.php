<?php
/**
 * @version 1.5 stable $Id: flexiusergroup.php 1348 2012-06-19 02:38:15Z ggppdk $
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
 * Renders the list of the content plugins
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFlexiusergroup extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Flexiusergroup';

	function fetchElement($name, $value, &$node, $control_name)
	{
		if (FLEXI_J16GE)  $node = & $this->element;
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		// 'multiple' attribute in XML adds '[]' automatically in J2.5 and manually in J1.5
		// This field is always multiple, we will add '[]' WHILE checking for the attribute ...
		$is_multiple = @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true';
		if (!FLEXI_J16GE || !$is_multiple)
			$fieldname .= '[]';
		
		$db =& JFactory::getDBO();
		$acl =& JFactory::getACL();
		
		/*$plugins 	= array();
		//$plugins[] 	= JHTMLSelect::option('', JText::_( 'FLEXI_ENABLE_ALL_PLUGINS' )); 
		
		$query  = 'SELECT element AS name'
				. ' FROM #__plugins'
				. ' WHERE folder = ' . $db->Quote('content')
				. ' AND element NOT IN ('.$db->Quote('pagenavigation').','.$db->Quote('vote').')'
				. ' ORDER BY name';
		
		$db->setQuery($query);
		$plgs = $db->loadObjectList();

		foreach ($plgs as $plg) {
			$plugins[] = JHTMLSelect::option($plg->name, $plg->name); 
		}*/
		
		$gtree = $acl->get_group_children_tree( null, 'USERS', false );
		//$lists['gid'] 	= JHTML::_('select.genericlist', $gtree, 'gid', 'size="10"', 'value', 'text', $user->get('gid') );
		
		$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<9) { ${element_id}_oldsize=$element_id.size; $element_id.size=9;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		$class = 'class="inputbox" multiple="true" size="5"';
		
		$html = JHTML::_('select.genericlist', $gtree, $fieldname, $class, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}