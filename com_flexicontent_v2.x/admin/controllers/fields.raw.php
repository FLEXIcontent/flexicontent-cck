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
class FlexicontentControllerFields extends FlexicontentController{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {
		parent::__construct();
	}

	function getfieldspecificproperties() {
		//$id		= JRequest::getVar( 'id', 0 );
		JRequest::setVar( 'view', 'field' );
		JRequest::setVar( 'format', 'raw' );
		//JRequest::setVar( 'hidemainmenu', 1 );
		
		// Import field to execute its constructor, e.g. needed for loading language file etc
		JPluginHelper::importPlugin('flexicontent_fields', JRequest::getVar('field_type'));
		
		$model 	= $this->getModel('field');
		$user	=& JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
			return;
		}

		$model->checkout( $user->get('id') );
		parent::display();
	}
}
