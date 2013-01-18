<?php
/**
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @author ggppdk
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

require_once JPATH_LIBRARIES.DS.'joomla'.DS.'html'.DS.'pane.php';

/**
 * Renders an new parameters sliding tab for a module / plugin
 */
class JElementJParamsTab extends JElement {

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
  var   $_name = 'JParamsTab';
  
  function fetchElement($name, $value, &$xmlNode, $control_name)
  {
    
    // Close Previous Group of parameters
    $new_group  = '</td></tr></table>';
    $new_group .= JPaneSliders::endPanel();
    
    // Start New Group of parameters
    $title = @$xmlNode->_attributes['title'];
    $description = @$xmlNode->_attributes['description'];
    if (!$title) $title = $description;
    
    $inline_style = 'font-size: 11px; color:gray; background-color: lightyellow; border: 1px solid lightgray; width: auto; padding: 4px 2%; margin: 1px 8px;';
    $new_group .= JPaneSliders::startPanel( ''.JText::_($title) );
    $new_group .= '<table width="100%" class="paramlist admintable" cellspacing="1">';
    $new_group .= '<tr><td class="paramlist_description" colspan="2"><div style="'.$inline_style.'">' . JText::_($description) . '</div></td>';
    $new_group .= '<td class="paramlist_value">';
  
    return $new_group;
  }
}

?>