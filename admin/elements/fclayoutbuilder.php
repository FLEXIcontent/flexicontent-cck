<?php
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

	// TODO-J5: jimport("cms.html.select") — find J5 equivalent

//\Joomla\CMS\Form\FormHelper::loadFieldClass('radio');   // \Joomla\CMS\Form\Field\RadioField

/**
 * Renders an FC Layout Builder element
 */
class JFormFieldFclayoutbuilder extends \Joomla\CMS\Form\FormField
{

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
  var $type = 'Fclayoutbuilder';
	
	function getLabel()
	{
		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return str_replace(' for="', ' data-for="', parent::getLabel());
	}


	function getInput()
	{
		$builder_options = (object) array(
			'fieldname'  => $this->name,
			'element_id' => $this->id,
			'lessfile'   => $this->element['lessfile'],
			'editor_sfx' => ($this->element['editor_sfx'] ?: $this->element['name']),
		);

		\Joomla\CMS\HTML\HTMLHelper::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		return \Joomla\CMS\HTML\HTMLHelper::_('fclayoutbuilder.getBuilderHtml', $builder_options);
	}
}
