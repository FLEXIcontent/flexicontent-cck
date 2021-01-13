<?php
/**
 * @version 1.5 stable $Id: fcimage.php 1143 2012-02-08 06:25:02Z ggppdk $
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
jimport('joomla.form.field');  // JFormField

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('groupedlist');   // JFormFieldGroupedList

/**
 * Renders an image element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
 class JFormFieldFcimage extends JFormFieldGroupedList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var $type = 'Fcimage';

	public function getGroups()
	{
		$db = JFactory::getDbo();
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$prompt_text = !empty($attributes['prompt_text']) ? $attributes['prompt_text'] : 'FLEXI_SELECT_IMAGE_FIELD';

		$images = array();
		$images[] = array(
			array('value' => '', 'text' => JText::_($prompt_text))
		);

		$valcolumn = 'name';
		if (@$attributes['valcolumn'])
		{
			$valcolumn = $attributes['valcolumn'];
		}
		
		$query = 'SELECT '.$valcolumn.' AS value, label AS text'
			. ' FROM #__flexicontent_fields'
			. ' WHERE published = 1'
			. ' AND field_type = ' . $db->Quote('image')
			. ' ORDER BY label ASC, id ASC';

		$db->setQuery($query);
		$fields = $db->loadObjectList();

		$grp = JText::_('FLEXI_FIELD');
		$images[$grp] = array();

		foreach ($fields as $field)
		{
			$images[$grp][] = array('value' => $field->value, 'text' => $field->text); 
		}

		return $images;
	}
}