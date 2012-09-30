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

		FLEXISubmenu('notvariable');
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_SEARCH_INDEX' ), FLEXI_J16GE ? 'searchtext.png' : 'searchindex' );
		
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

		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_START', 'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component', 500, 210);
		$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_PURGE_INDEX', FLEXI_J16GE ? 'search.purge' : 'purge', false);
		
		$user = &JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		if($permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
	}
	
	function indexer($tpl) {
		$document = JFactory::getDocument();
		
		JHTML::_('behavior.mootools');
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			$document->addScript('components/com_flexicontent/assets/js/jquery-'.FLEXI_JQUERY_VER.'.js');
			// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');
		}
		
		parent::display($tpl);
	}
}
