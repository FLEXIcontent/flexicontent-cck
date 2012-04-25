<?php
/**
 * @version 1.5 stable $Id$
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Fields Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFields extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Logic to get (e.g. via AJAX call) the field specific parameters
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function getfieldspecificproperties() {
		//$id		= JRequest::getVar( 'id', 0 );
		JRequest::setVar( 'view', 'field' );    // set view to be field, if not already done in http request
		if (FLEXI_J16GE) {
			JRequest::setVar( 'format', 'raw' );    // force raw format, if not already done in http request
			JRequest::setVar( 'cid', '' );          // needed when changing type of an existing field
		}
		//JRequest::setVar( 'hidemainmenu', 1 );
		
		// Import field to execute its constructor, e.g. needed for loading language file etc
		JPluginHelper::importPlugin('flexicontent_fields', JRequest::getVar('field_type'));
		
		// Display the field parameters
		parent::display();
	}
}
