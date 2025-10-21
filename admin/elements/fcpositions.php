<?php
/**
 * @version 1.5 stable $Id: fields.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * Renders a module positions list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcpositions extends \Joomla\CMS\Form\FormField
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'Fcpositions';
	
	protected function getInput()
	{
		$doc = \Joomla\CMS\Factory::getDocument();
		$db  = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$values = $this->value;
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$query 	= 'SELECT DISTINCT position as value, position as text'
				. ' FROM #__modules'
				. ' WHERE published = 1'
				. ' AND client_id = 0'
				. ' ORDER BY position ASC'
				;
		
		$db->setQuery($query);
		$positions = $db->loadObjectList();
		
		if ( !$positions )  return array();
		
		// Put a select module option at top of list
		$first_option = new stdClass();
		$first_option->value = '';
		$first_option->text = \Joomla\CMS\Language\Text::_( 'FLEXI_SELECT_MODULE' );
		array_unshift($positions, $first_option);
		
		$attribs = '';
		
		return \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $positions, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}