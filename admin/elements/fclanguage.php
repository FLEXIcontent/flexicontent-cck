<?php
/**
 * @version 1.5 stable $Id: fields.php 1256 2012-04-24 01:51:48Z ggppdk $
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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFclanguage extends \Joomla\CMS\Form\FormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Fclanguage';

	function getInput()
	{
		$doc	= \Joomla\CMS\Factory::getDocument();
		$db		= \Joomla\CMS\Factory::getDbo();
		
		// Get field configuration
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		// Get values
		$values			= $this->value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = explode("|", $values);
		
		// Field name and HTML tag id
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		// Create options
		$langs = array();
		
		// Add 'use global' (no value option)
		if (@$attributes['use_global']) {
			$langs[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('FLEXI_USE_GLOBAL') );
		}
		
		// Add 'please select' (no value option)
		if (@$attributes['please_select']) {
			$langs[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('FLEXI_PLEASE_SELECT') );
		}

		// Whether to add language 'ALL' (*)
		$add_all = @$attributes['skip_all'] ? false : true;
		
		foreach ($node->children() as $option)
		{
			$val  = $option->attributes()->value;
			$text = \Joomla\CMS\Language\Text::_( FLEXI_J30GE ? $option->__toString() : $option->data() );
			$langs[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $val, $text );
		}
		
		$languages = FLEXIUtilities::getlanguageslist($_published_only=false, $add_all);
		foreach($languages as $lang) {
			$langs[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $lang->code, $lang->name );
		}
		
		// Create HTML tag parameters
		$attribs = '';
		$classes = 'use_select2_lib';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
		}
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		$attribs .= ' class="'.$classes.'" ';
		
		// Render the field's HTML
		return \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $langs, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}
