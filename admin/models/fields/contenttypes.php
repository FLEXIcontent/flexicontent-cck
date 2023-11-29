<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldContenttypes extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Contenttypes';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 * @since	1.6
	 */
	protected function getInput()
	{
		// Initialize some field attributes.
		$attr  = '';
		$attr .= $this->element['class'] ? ' class="use_select2_lib '.(string) $this->element['class'].'"' : '';
		$attr .= ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
		$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';
		$options = (array) $this->getOptions();

		$value = $this->value;
		$fieldname	= $this->name;
		$element_id = $this->id;

		return JHtml::_('select.genericlist', $options, $fieldname, trim($attr), 'value', 'text', $value, $element_id);
		//return JHtmlSelect::genericList($options, $fieldname, $attr, 'value', 'text', $value, $element_id);
	}


	protected function getOptions()
	{
		$db = JFactory::getDbo();
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;

		$db->setQuery($query);
		$types = $db->loadObjectList();
		return $types;
	}


	public function setAttributes($attribs = array())
	{
		$this->name = $attribs['name'];
		$this->value = $attribs['value'];
		$this->label = JText::_($attribs['label']);
	}
}
