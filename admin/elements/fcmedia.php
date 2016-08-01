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
JFormHelper::loadFieldClass('media');   // JFormFieldMedia

/**
 * Renders a media element
 */
class JFormFieldFcmedia extends JFormFieldMedia
{

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
  var $type = 'Fcmedia';

	static $css_js_added = null;

	function getInput()
	{
		$jinput = JFactory::getApplication()->input;
		
		if (self::$css_js_added===null)
		{
			if ($jinput->get('format', 'html', 'cmd') == 'html')
			{
				flexicontent_html::loadFramework('flexi-lib');
			}
			self::$css_js_added = $jinput->get('format', 'html', 'cmd') == 'html';
		}

		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return $jinput->get('format', 'html', 'cmd') == 'html' ?
			str_replace(' rel="', ' data-rel="', parent::getInput()) :
			parent::getInput();
	}
}

?>