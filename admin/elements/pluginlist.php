<?php
/**
 * @version 1.5 stable $Id: pluginlist.php 967 2011-11-21 00:01:36Z ggppdk $
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

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Renders the list of the content plugins
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldPluginlist extends JFormFieldList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Pluginlist';

	function getInput()
	{
		$doc = JFactory::getDocument();
		$db  = JFactory::getDbo();
		
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$values = $this->value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = explode("|", $values);
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		// 'multiple' attribute in XML adds '[]' automatically in J2.5+
		// This field is always multiple, we will add '[]' WHILE checking for the attribute ...
		$is_multiple = @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true';
		if (!$is_multiple) $fieldname .= '[]';
		
		$plggroup = @$attributes['plggroup'];
		$plggroup = $plggroup ? $plggroup : 'content';
		
		$query  = 'SELECT element AS name'
				. ' FROM '. (FLEXI_J16GE ? '#__extensions' : '#__plugins')
				. ' WHERE folder = ' . $db->Quote($plggroup)
				.  (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
				. ' AND element NOT IN ('.$db->Quote('pagebreak').','.$db->Quote('pagenavigation').','.$db->Quote('vote').')'
				. ' ORDER BY name'
				;
		$db->setQuery($query);
		$plgs = $db->loadObjectList();
		
		$plugins = array();
		foreach ($plgs as $plg)
		{
			$plugins[] = JHtmlSelect::option($plg->name, $plg->name); 
		}
		
		$attribs = ' class="use_select2_lib" multiple="multiple" size="5" ';
		
		return JHtmlSelect::genericList($plugins, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}
