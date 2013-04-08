<?php
/**
 * @version 1.5 stable $Id: view.html.php 1577 2012-12-02 15:10:44Z ggppdk $ 
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

jimport('joomla.application.component.view');
class FLEXIcontentViewSearch extends JViewLegacy
{
	function display($tpl = null) {
		$layout = JRequest::getVar('layout', 'default');
		if($layout=='indexer') {
			$this->indexer($tpl);
			return;
		}
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd( 'option' );
		$db       = JFactory::getDBO();
		
		$filter_order			= $app->getUserStateFromRequest( $option.'.search.filter_order', 			'filter_order', 'a.title', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.search.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		
		$filter_fieldtype	= $app->getUserStateFromRequest( $option.'.fields.filter_fieldtype', 	'filter_fieldtype', 	'', 'word' );
		$filter_itemtype	= $app->getUserStateFromRequest( $option.'.fields.filter_itemtype', 	'filter_itemtype', 		'', 'int' );
		$filter_itemstate	= $app->getUserStateFromRequest( $option.'.fields.filter_itemstate', 'filter_itemstate', 	'', 'word' );
		
		$search_index			= $app->getUserStateFromRequest( $option.'.search.search_index',			'search_index', '', 'string' );
		$search_index			= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search_index ) ) ) : $db->getEscaped( trim(JString::strtolower( $search_index ) ) );
		$search_itemtitle	= $app->getUserStateFromRequest( $option.'.search.search_itemtitle',	'search_itemtitle', '', 'string' );
		$search_itemid		= $app->getUserStateFromRequest( $option.'.search.search_itemid',	'search_itemid', '', 'string' );
		$search_itemid		= strlen($search_itemid) ? (int)$search_itemid : '';
		$filter_indextype	= $app->getUserStateFromRequest( $option.'.search.filter_indextype',		'filter_indextype',	'advanced',		'word' );
		
		$f_active['filter_fieldtype']	= (boolean)$filter_fieldtype;
		$f_active['filter_itemtype']	= (boolean)$filter_itemtype;
		$f_active['filter_itemstate']	= (boolean)$filter_itemstate;
		
		$f_active['search_index']			= strlen($search_index);
		$f_active['search_itemtitle']	= strlen($search_itemtitle);
		$f_active['search_itemid']		= (boolean)$search_itemid;
		
		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		// Create Submenu and check access
		FLEXISubmenu('CanIndex');
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_SEARCH_INDEX' ), FLEXI_J16GE ? 'searchtext.png' : 'searchindex' );
		
		// Configure the toolbar.
		$this->setToolbar();
		
		$types			= $this->get( 'Typeslist' );
		$fieldtypes	= $this->get( 'Fieldtypes' );
		
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
		$lists['filter_itemtype'] = flexicontent_html::buildtypesselect($types, 'filter_itemtype', $filter_itemtype, true, 'class="inputbox" size="1" onchange="submitform( );"', 'filter_itemtype');
		
		//publish unpublished filter
		$ffstate = array();
		$ffstate[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_SELECT_STATE' ) .' -' );
		$ffstate[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$ffstate[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$lists['filter_itemstate'] = JHTML::_('select.genericlist', $ffstate, 'filter_itemstate', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_itemstate );
		
		// build filter index type record listing
		//$itn['basic'] = JText::_( 'FLEXI_INDEX_BASIC' );
		$itn['advanced'] = JText::_( 'FLEXI_INDEX_ADVANCED' );
		$indextypes = array();
		foreach ($itn as $i => $v) {
			if ($filter_indextype == $i) $v = "<span class='flexi_radiotab highlight'>".$v."</span>";
			else                        $v = "<span class='flexi_radiotab downlight'>".$v."</span>";
			$indextypes[] = JHTML::_('select.option', $i, $v);
		}
		$lists['filter_indextype'] = JHTML::_('select.radiolist', $indextypes, 'filter_indextype', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_indextype );
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		// search index & item title  filter
		$lists['search_index']= $search_index;
		$lists['search_itemtitle']= $search_itemtitle;
		$lists['search_itemid']= $search_itemid;
		
		$pagination = $this->get('Pagination');
		$data   = $this->get('Data');
		$total  = $this->get('Count');
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

		$query = "SHOW VARIABLES LIKE '%ft_min_word_len%'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$ft_min_word_len = (int) @ $_dbvariable->Value;
		$notice_ft_min_word_len	= $app->getUserStateFromRequest( $option.'.fields.notice_ft_min_word_len',	'notice_ft_min_word_len',	0, 'int' );
		//if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		//{
			if ( $notice_ft_min_word_len < 2) {
				$app->setUserState( $option.'.fields.notice_ft_min_word_len', $notice_ft_min_word_len+1 );
				$app->enqueueMessage("NOTE : Database limits minimum search word length (ft_min_word_len) to ".$ft_min_word_len, 'notice');
				//$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'notice');
			}
		//}
		
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
		$toolbar = JToolBar::getInstance('toolbar');

		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_BASIC_CONTENT_LISTS',    'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic',     500, 210);
		JToolBarHelper::divider();  JToolBarHelper::spacer();
		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW', 'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced',  500, 210);
		$toolbar->appendButton('Popup', 'archive', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY', 'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced&rebuildmode=quick',  500, 210);
		$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_INDEX_ADVANCED_PURGE', FLEXI_J16GE ? 'search.purge' : 'purge', false);
		
		$user  = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
	}
	
	function indexer($tpl) {
		$document = JFactory::getDocument();
		
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		
		parent::display($tpl);
	}
}
