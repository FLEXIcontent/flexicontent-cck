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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('radio');   // JFormFieldRadio

/**
 * Renders an FC Layout Builder element
 */
class JFormFieldFclayoutbuilder extends JFormField
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

		JHtml::addIncludePath(JPATH_SITE . '/components/com_flexicontent/helpers/html');
		return JHtml::_('fclayoutbuilder.getBuilderHtml', $builder_options);
	}
}
