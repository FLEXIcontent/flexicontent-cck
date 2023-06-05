<?php
/**
 * @version 1.5 beta 4 $Id: fcdate.php 967 2011-11-21 00:01:36Z ggppdk $
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
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('calendar');   // JFormField...

/**
 * Renders a date element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcdate extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var $type = 'Fcdate';

	public function getInput()
	{
		$node = & $this->element;
		$attribs = get_object_vars($node->attributes());
		$attribs = $attribs['@attributes'];

		$value = $this->value;
		$fieldname = $this->name;
		$elementid = $this->id;

		$dateFormat = isset($attribs['format']) ? $attribs['format'] : '%Y-%m-%d';
		$allowText  = isset($attribs['allowText']) ? (bool) $attribs['allowText'] : true;

		$value_holder = '';
		if ($allowText)
		{
			$attribs['class'] = isset($attribs['class']) ? $attribs['class'] . ' fc_date_allow_text' : 'fc_date_allow_text';
			$value_holder = '<span id="'.$elementid.'_fc_value" style="display: none;" data-fc-value="'.$value.'"></span>';
		}

		// Create JS calendar
		return $value_holder . JHtml::_('calendar', $value, $fieldname, $elementid, $dateFormat, $attribs);
	}
}