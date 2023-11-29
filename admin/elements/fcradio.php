<?php
/**
 * @version 1.5 stable $Id: fccheckbox.php 967 2011-11-21 00:01:36Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @author ggppdk
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
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('radio');   // JFormFieldRadio

/**
 * Renders a radio-set element
 */
class JFormFieldFcradio extends JFormFieldRadio
{

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
  var $type = 'Fcradio';
	
	function getLabel()
	{
		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return str_replace(' for="', ' data-for="', parent::getLabel());
	}
	
	function getInput()
	{
		$class = $this->element['class'];
		$isBtnGroup  = strpos(trim($class), 'btn-group') !== false;
		$isBtnYesNo  = strpos(trim($class), 'btn-group-yesno') !== false;
		return $isBtnGroup && !$isBtnYesNo
			? str_replace('btn-outline-secondary', '', parent::getInput())
			: parent::getInput();
	}

	/*function getInput()
	{
		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return str_replace(' required aria-required="true"', ' required', str_replace('<fieldset ', '<fieldset role="radiogroup" ', parent::getInput()));
	}*/
}

?>