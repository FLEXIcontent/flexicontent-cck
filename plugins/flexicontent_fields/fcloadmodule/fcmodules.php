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
 * Renders a category list
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
		$allmodules 	= array();
		$allmodules[] 	= JHTMLSelect::option('', JText::_( 'FLEXI_SELECT_MODULE' )); 

		$db =& JFactory::getDBO();
		
		$query 	= 'SELECT id AS value, title AS text'
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. ' ORDER BY title ASC, id ASC'
				;
		
		$db->setQuery($query);
		$mods = $db->loadObjectList();

		foreach ($mods as $m) {
			$allmodules[] = JHTMLSelect::option($m->value, $m->text); 
		}

		$class = '';
		
		return JHTMLSelect::genericList($allmodules, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);

		return $list;
	}
}