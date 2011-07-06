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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport( 'joomla.application.component.view');
class FLEXIcontentViewSearch extends JView
{
	function display($tpl = null) {
		$layout = JRequest::getVar('layout', 'default');
		if($layout=='indexer') {
			$this->indexer($tpl);
			return;
		}
		$mainframe = &JFactory::getApplication();
		$document	= & JFactory::getDocument();
		
		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/stateselector.js' );
		if (FLEXI_ACCESS) {
			$user =& JFactory::getUser();
			$CanAdd 		= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid) || FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) : 1;
			$CanEdit 		= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'edit', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'editown', 'users', $user->gmid)) : 1;
			$CanPublish 	= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)) : 1;
			$CanDelete 		= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'delete', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'deleteown', 'users', $user->gmid)) : 1;
			$CanCats 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanTypes 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1;
			$CanFields 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1;
			$CanTags 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1;
			$CanArchives 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
			$CanFiles	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$CanStats	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1;
			$CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;
			$CanRights	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid) : 1;
			$CanOrder	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
			$CanCopy	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid) : 1;
		} else {
			$CanAdd 		= 1;
			$CanEdit		= 1;
			$CanPublish		= 1;
			$CanDelete		= 1;
			$CanCats 		= 1;
			$CanTypes 		= 1;
			$CanFields		= 1;
			$CanTags 		= 1;
			$CanArchives	= 1;
			$CanFiles		= 1;
			$CanStats		= 1;
			$CanTemplates	= 1;
			$CanRights		= 1;
			$CanOrder		= 1;
			$CanCopy		= 1;
		}
		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent');
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items');
		if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types');
		if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
		if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
		if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
		if ($CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
		if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
		if ($CanTemplates) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates');
		if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEX' ), 'index.php?option=com_flexicontent&view=search', true);
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_SEARCH_INDEX' ), 'searchtext.png' );
		
		// Configure the toolbar.
		$this->setToolbar();
		
		$pagination	= &$this->get('Pagination');
		$this->assignRef('pagination',	$pagination);
		$data = $this->get('Data');
		$total = $this->get('Count');
		$limitstart = $this->get('LimitStart');
		$this->assignRef('data', $data);
		$this->assignRef('total', $total);
		$this->assignRef('limitstart', $limitstart);
		parent::display($tpl);
	}
	
	
	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar() {
		$toolbar = &JToolBar::getInstance('toolbar');

		$toolbar->appendButton('Popup', 'archive', 'Index', 'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component', 500, 210);
		$toolbar->appendButton('Confirm', 'Are you sure you want to delete ALL items from the index? This can take a long time on large sites.', 'trash', 'Purge', 'purge', false);
		/*$toolbar->appendButton('Separator', 'divider');

		JToolBarHelper::publishList('index.publish', 'Publish');
		JToolBarHelper::unpublishList('index.unpublish', 'Unpublish');
		JToolBarHelper::deleteList('delete', 'index.delete', 'Delete');

		$toolbar->appendButton('Separator', 'divider');
		$toolbar->appendButton('Popup', 'config', 'FINDER_OPTIONS', 'index.php?option=com_finder&view=config&tmpl=component', 570, 500);
		$toolbar->appendButton('Popup', 'help', 'FINDER_ABOUT', 'index.php?option=com_finder&view=about&tmpl=component', 550, 500);
		*/
	}
	
	function indexer($tpl) {
		$document = & JFactory::getDocument();
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.4.min.js');
			$document->addCustomTag('<script>jQuery.noConflict();</script>');
		}
		parent::display($tpl);
	}
}
