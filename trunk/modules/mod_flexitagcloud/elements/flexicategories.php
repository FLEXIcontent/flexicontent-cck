<?php
/**
 * @version 1.5 beta 4 $Id$
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
defined('_JEXEC') or die();

$fparams =& JComponentHelper::getParams('com_flexicontent');
if (!defined('FLEXI_SECTION')) define('FLEXI_SECTION', $fparams->get('flexi_section'));
if (!defined('FLEXI_ACCESS')) define('FLEXI_ACCESS', (JPluginHelper::isEnabled('system', 'flexiaccess') && version_compare(PHP_VERSION, '5.0.0', '>')) ? 1 : 0);

// Load the category class
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');

/**
 * Renders a category list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
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
		$db		=& JFactory::getDBO();

		$categories = flexicontent_cats::getCategoriesTree(0);
		$class = 'multiple="true" size="10"';
		$list = flexicontent_cats::buildcatselect($categories, $control_name.'['.$name.'][]', $value, false, 'class="inputbox" size="15" multiple="true"');
		
		return $list;
	}
}