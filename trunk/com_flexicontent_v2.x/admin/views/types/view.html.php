<?php
/**
 * @version 1.5 stable $Id: view.html.php 1431 2012-08-11 14:22:23Z ggppdk $
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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTypes extends JView {

	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		
		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.types.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.types.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.types.filter_state', 		'filter_state', 	'*', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.types.search', 			'search', 			'', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$perms = FlexicontentHelperPerm::getPerm();
		} else {
			$perms = new stdClass();
			$perms->CanTypes 		= 1;
			$perms->CanConfig		= 1;
		}
		
		if (!$perms->CanTypes) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXISubmenu('CanTypes');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_TYPES' ), 'types' );
		JToolBarHelper::customX( 'types.copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
		JToolBarHelper::publishList('types.publish');
		JToolBarHelper::unpublishList('types.unpublish');
		JToolBarHelper::addNew('types.add');
		JToolBarHelper::editList('types.edit');
		JToolBarHelper::deleteList('Are you sure?', 'types.remove');
		if($perms->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');

		//Get data from the model
		$rows             = & $this->get( 'Items');
		foreach($rows as $type) {
			$type->config = new JParameter($type->config);
		}
		$this->pagination = & $this->get( 'Pagination' );

		$lists = array();
		
		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );
		
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//assign data to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('user'      	, $user);

		parent::display($tpl);
	}
}
?>
