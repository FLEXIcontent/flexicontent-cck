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
		$mainframe= &JFactory::getApplication();
		$document	= & JFactory::getDocument();
		$option   = JRequest::getCmd( 'option' );
		$db  		  = JFactory::getDBO();
		
		$filter_order			= $mainframe->getUserStateFromRequest( $option.'.search.filter_order', 			'filter_order', 'a.title', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.search.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		
		$filter_fieldtype	= $mainframe->getUserStateFromRequest( $option.'.fields.filter_fieldtype', 	'filter_fieldtype', 	'', 'word' );
		$filter_itemtype	= $mainframe->getUserStateFromRequest( $option.'.fields.filter_itemtype', 	'filter_itemtype', 		'', 'int' );
		$filter_itemstate	= $mainframe->getUserStateFromRequest( $option.'.fields.filter_itemstate', 'filter_itemstate', 	'', 'word' );
		
		$search_index			= $mainframe->getUserStateFromRequest( $option.'.search.search_index',			'search_index', '', 'string' );
		$search_index			= $db->getEscaped( trim(JString::strtolower( $search_index ) ) );
		$search_itemtitle	= $mainframe->getUserStateFromRequest( $option.'.search.search_itemtitle',	'search_itemtitle', '', 'string' );
		$search_itemid		= $mainframe->getUserStateFromRequest( $option.'.search.search_itemid',	'search_itemid', '', 'int' );
		
		$f_active['filter_fieldtype']	= (boolean)$filter_fieldtype;
		$f_active['filter_itemtype']	= (boolean)$filter_itemtype;
		$f_active['filter_itemstate']	= (boolean)$filter_itemstate;
		
		$f_active['search_index']			= strlen($search_index);
		$f_active['search_itemtitle']	= strlen($search_itemtitle);
		$f_active['search_itemid']		= strlen($search_itemid);
		
		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		FLEXISubmenu('notvariable');
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_SEARCH_INDEX' ), FLEXI_J16GE ? 'searchtext.png' : 'searchindex' );
		
		// Configure the toolbar.
		$this->setToolbar();
		
		$types			= & $this->get( 'Typeslist' );
		$fieldtypes	= & $this->get( 'Fieldtypes' );
		
		// Build select lists
		$lists = array();
		
		//build backend visible filter
		$fftypes = array();
		$fftypes[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FIELDS_TYPE' ) .' -' );
		$fftypes[] = JHTML::_('select.option',  'C', JText::_( 'FLEXI_CORE_FIELDS' ) );
		$fftypes[] = JHTML::_('select.option',  'NC', JText::_( 'FLEXI_NON_CORE_FIELDS' ) );
		foreach ($fieldtypes as $field_type => $ftdata) {
			$fftypes[] = JHTML::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_type);
		}
		$lists['filter_fieldtype'] = JHTML::_('select.genericlist', $fftypes, 'filter_fieldtype', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_fieldtype );
		
		//build type select list
		$lists['filter_itemtype'] = flexicontent_html::buildtypesselect($types, 'filter_itemtype', $filter_itemtype, true, 'class="inputbox" size="1" onchange="submitform( );"');
		
		//publish unpublished filter
		$ffstate = array();
		$ffstate[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_SELECT_STATE' ) .' -' );
		$ffstate[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$ffstate[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$lists['filter_itemstate'] = JHTML::_('select.genericlist', $ffstate, 'filter_itemstate', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_itemstate );
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		// search index & item title  filter
		$lists['search_index']= $search_index;
		$lists['search_itemtitle']= $search_itemtitle;
		$lists['search_itemid']= $search_itemid;
		
		$pagination	= &$this->get('Pagination');
		$data = $this->get('Data');
		$total = $this->get('Count');
		$limitstart = $this->get('LimitStart');

		$js = "window.addEvent('domready', function(){";
		if ($filter_fieldtype) {
			$js .= "$$('.col_fieldtype').each(function(el){ el.addClass('yellow'); });";
		}		
		if ($search_index) {
			$js .= "$$('.col_search_index').each(function(el){ el.addClass('yellow'); });";
		}		
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		$this->assignRef('lists',	$lists);
		$this->assignRef('pagination',	$pagination);
		$this->assignRef('data', $data);
		$this->assignRef('total', $total);
		$this->assignRef('limitstart', $limitstart);
		$this->assignRef('f_active', $f_active);
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

		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_BASIC',    'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic',     500, 210);
		JToolBarHelper::spacer();
		JToolBarHelper::divider();
		JToolBarHelper::spacer();
		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_ADVANCED', 'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced',  500, 210);
		$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_PURGE_INDEX', FLEXI_J16GE ? 'search.purge' : 'purge', false);
		
		$user = &JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		if($permission->CanConfig) {
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
	}
	
	function indexer($tpl) {
		$document = JFactory::getDocument();
		
		JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		
		parent::display($tpl);
	}
}
