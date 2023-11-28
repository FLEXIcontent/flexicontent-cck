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
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldFieldtypes extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Fieldtypes';

	protected function getOptions() {
		$db = JFactory::getDbo();

		$query = 'SELECT element AS value, REPLACE(name, "FLEXIcontent - ", "") AS text'
		. ' FROM '.(FLEXI_J16GE ? '#__extensions' : '#__plugins')
		. ' WHERE '.(FLEXI_J16GE ? 'enabled = 1' : 'published = 1')
		. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
		. ' ORDER BY text ASC'
		;

		$db->setQuery($query);
		$field_types = $db->loadObjectList();

		// This should not be neccessary as, it was already done in DB query above
		foreach($field_types as $field_type) {
			$field_type->text = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->text);
			$field_arr[$field_type->text] = $field_type;
		}
		ksort( $field_arr, SORT_STRING );

		return $field_arr;

	}
}
?>
