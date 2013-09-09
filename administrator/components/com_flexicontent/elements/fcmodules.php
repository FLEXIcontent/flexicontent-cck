<?php
/**
 * @version 1.5 beta 4 $Id: fcmodules.php 146 2010-06-01 08:27:23Z vistamedia $
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

$fparams =& JComponentHelper::getParams('com_flexicontent');
if (!defined('FLEXI_SECTION')) define('FLEXI_SECTION', $fparams->get('flexi_section'));
if (!defined('FLEXI_ACCESS')) define('FLEXI_ACCESS', (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);

/**
 * Renders a module names list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFcmodules extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Fcmodules';
	
	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc = JFactory::getDocument();
		$db  = JFactory::getDBO();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$values = FLEXI_J16GE ? $this->value : $value;
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$and='';
		
		$modtype =  @$attributes['modtype'];
		if($modtype) {
			$modtype = preg_split("/[\s]*,[\s]*/", $modtype);
			$and .= " AND module IN ('". implode("','", $modtype)."')";
		}		
		
		$query 	= 'SELECT id AS value, title AS text'
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. $and
				. ' ORDER BY title ASC, id ASC'
				;
		
		$db->setQuery($query);
		$mods = $db->loadObjectList();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		if ( !$mods )  return array();
		
		// Put a select module option at top of list
		$first_option = new stdClass();
		$first_option->value = '';
		$first_option->text = JText::_( 'FLEXI_SELECT_MODULE' );
		array_unshift($mods, $first_option);
		
		$attribs = '';
		
		return JHTML::_('select.genericlist', $mods, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}