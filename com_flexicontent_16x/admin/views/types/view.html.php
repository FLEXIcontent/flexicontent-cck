<?php
/**
 * @version 1.5 stable $Id: view.html.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

	function display($tpl = null) {
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

		if (FLEXI_ACCESS) {
			$user =& JFactory::getUser();
			$CanCats 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanTypes 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1;
			$CanFields 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1;
			$CanTags 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1;
			$CanArchives 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
			$CanFiles	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$CanStats	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1;
			$CanRights	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 	'manage', 'users', $user->gmid) : 1;
			$CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;
		} else {
			$CanCats 		= 1;
			$CanTypes 		= 1;
			$CanFields		= 1;
			$CanTags 		= 1;
			$CanArchives	= 1;
			$CanFiles		= 1;
			$CanStats		= 1;
			$CanRights		= 1;
			$CanTemplates	= 1;
		}

		if (!$CanTypes) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent');
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items');
		if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', true);
		if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
		if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
		if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
		if ($CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
		if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
		if ($CanTemplates) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates');
		if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_TYPES' ), 'types' );
		JToolBarHelper::customX( 'copy', 'copy.png', 'copy_f2.png', 'Copy' );
		JToolBarHelper::publishList();
		JToolBarHelper::unpublishList();
		JToolBarHelper::addNew();
		JToolBarHelper::editList();
		JToolBarHelper::deleteList();

		//Get data from the model
		$rows      	= & $this->get( 'Data');
		$pageNav 	= & $this->get( 'Pagination' );

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
		$this->assignRef('pageNav' 		, $pageNav);

		parent::display($tpl);
	}
}
?>