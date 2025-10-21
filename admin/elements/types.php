<?php
/**
 * @version 1.5 stable $Id: types.php 1260 2012-04-25 17:43:21Z ggppdk $
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
use Joomla\Database\DatabaseInterface;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

/**
 * Renders a types element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldTypes extends \Joomla\CMS\Form\FormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Types';

	function getInput()
	{
		$doc = \Joomla\CMS\Factory::getDocument();
		$db  = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
		
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
				
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;
		
		$db->setQuery($query);
		$types = $db->loadObjectList();
		
		$values = $this->value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$attribs = 'style="float:left;"';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= @ $attributes['size'] ? ' size="'.$attributes['size'].'" ' : ' size="6" ';
		} else {
			if ( @ $attributes['user_selection'] )
				array_unshift($types, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('FLEXI_MENU_ALLOW_CONTENT_TYPE_SELECTION')));
			else
				array_unshift($types, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('FLEXI_PLEASE_SELECT')));
		}
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		if ($class = @$attributes['class']) {
			$attribs .= ' class="'.$class.'"';
		}

		return \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $types, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}
