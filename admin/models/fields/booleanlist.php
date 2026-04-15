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

// jimport removed J5: use Joomla\CMS\HTML\HTMLHelper; // TODO: add use statement at top      // JHtml
// jimport removed J5: use Joomla\CMS\...  /* cms.html.select */; // TODO: add use statement at top    // \Joomla\CMS\HTML\Helpers\Select
// jimport removed J5: use Joomla\CMS\...  /* joomla.form.field */; // TODO: add use statement at top  // \Joomla\CMS\Form\FormField

//// jimport removed J5: use Joomla\CMS\...  /* joomla.form.helper */; // TODO: add use statement at top // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Framework
 * @subpackage	Form
 * @since		1.6
 */
class JFormFieldBooleanlist extends \Joomla\CMS\Form\FormField
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Booleanlist';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 * @since	1.6
	 */
	protected function getInput()
	{
		// Initialize variables.
		$attr = '';

		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';

		return \Joomla\CMS\HTML\HTMLHelper::_('select.booleanlist', $this->name, $attr, $this->value );
	}
}
