<?php
/**
 * @version 1.5 stable $Id: pluginlist.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');
/**
 * Renders the list of the content plugins
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldPluginlist extends JFormFieldList{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Pluginlist';

	function getInput() {
		//$name, $value, &$node, $control_name
		$name = $this->name;
		$value = $this->value;
		$values = explode("|", $value);
		$plugins 	= array();
//		$plugins[] 	= JHTMLSelect::option('', JText::_( 'FLEXI_ENABLE_ALL_PLUGINS' )); 

		$db =& JFactory::getDBO();
		
		$query  = 'SELECT element AS name'
				. ' FROM #__extensions'
				. ' WHERE folder = ' . $db->Quote('content')
				. ' AND `type`=' . $db->Quote('plugin')
				. ' AND element NOT IN ('.$db->Quote('pagebreak').','.$db->Quote('pagenavigation').','.$db->Quote('vote').')'
				. ' ORDER BY name';
		
		$db->setQuery($query);
		$plgs = $db->loadObjectList();

		foreach ($plgs as $plg) {
			$plugins[] = JHTMLSelect::option($plg->name, $plg->name); 
		}

		$class = 'class="inputbox" multiple="true" size="5"';
		return JHTML::_('select.genericlist', $plugins, $name.'[]', $class, 'value', 'text', $values, $name);
	}
}
