<?php
/**
 * @version 1.5 stable $Id: fctag.php 1800 2013-11-01 04:30:57Z ggppdk $
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

// jimport removed J5: use Joomla\CMS\HTML\HTMLHelper; // TODO: add use statement at top      // JHtml
// jimport removed J5: use Joomla\CMS\...  /* cms.html.select */; // TODO: add use statement at top    // \Joomla\CMS\HTML\Helpers\Select
// jimport removed J5: use Joomla\CMS\...  /* joomla.form.field */; // TODO: add use statement at top  // \Joomla\CMS\Form\FormField

//// jimport removed J5: use Joomla\CMS\...  /* joomla.form.helper */; // TODO: add use statement at top // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...


require "fctag.php";

/**
 * Renders an FC Tag element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldTag extends \Joomla\CMS\Form\FormFieldFctag
{
 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Tag';
}
?>