<?php
/**
 * @version 1.5 stable $Id: view.html.php 1768 2013-09-22 21:42:30Z ggppdk $ 
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
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');

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
		//foreach ($itn as $i => $v) $indextypes[] = JHTML::_('select.option', $i, $v);
		//$lists['filter_indextype'] = JHTML::_('select.radiolist', $indextypes, 'filter_indextype', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_indextype );
		$lists['filter_indextype'] = '';
		foreach ($itn as $i => $v) {
			$checked = $filter_indextype == $i ? ' checked="checked" ' : '';
			$lists['filter_indextype'] .= '<input type="radio" onchange="submitform();" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_indextype'.$i.'" name="filter_indextype">';
			$lists['filter_indextype'] .= '<label class="" id="filter_indextype'.$i.'-lbl" for="filter_indextype'.$i.'">'.$v.'</label>';
		}
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		// search index & item title  filter
		$lists['search_index']= $search_index;
		$lists['search_itemtitle']= $search_itemtitle;
		$lists['search_itemid']= $search_itemid;
		
		$data   = $this->get('Data');  // MUST BE BEFORE getCount and getPagination because it also calculates total rows
		$total  = $this->get('Count');
		$pagination = $this->get('Pagination');
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
		$document = JFactory::getDocument();
		$js = "window.addEvent('domready', function(){";
		$toolbar = JToolBar::getInstance('toolbar');

		$btn_task = '';
		$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic';
		if (FLEXI_J16GE) {
			$js .= "
				$$('li#toolbar-basicindex a.toolbar, #toolbar-basicindex button')
					.set('onclick', 'javascript:;')
					.set('href', '".$popup_load_url."')
					.set('rel', '{handler: \'iframe\', size: {x: 500, y: 240}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'basicindex.png', 'basicindex_f2.png', 'FLEXI_INDEX_BASIC_CONTENT_LISTS', false );
			JHtml::_('behavior.modal', 'li#toolbar-basicindex a.toolbar, #toolbar-basicindex button');
		} else {
			$toolbar->appendButton('Popup', 'basicindex', 'FLEXI_INDEX_BASIC_CONTENT_LISTS', $popup_load_url, 500, 240);
		}
		
		JToolBarHelper::divider();  JToolBarHelper::spacer();
		
		$btn_task = '';
		$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced';
		if (FLEXI_J16GE) {
			$js .= "
				$$('li#toolbar-advindex a.toolbar, #toolbar-advindex button')
					.set('onclick', 'javascript:;')
					.set('href', '".$popup_load_url."')
					.set('rel', '{handler: \'iframe\', size: {x: 500, y: 240}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'advindex.png', 'advindex_f2.png', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW', false );
			JHtml::_('behavior.modal', 'li#toolbar-advindex a.toolbar, #toolbar-advindex button');
		} else {
			$toolbar->appendButton('Popup', 'advindex', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW', $popup_load_url, 500, 240);
		}
		
		$btn_task = '';
		$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced&rebuildmode=quick';
		if (FLEXI_J16GE) {
			$js .= "
				$$('li#toolbar-advindexdirty a.toolbar, #toolbar-advindexdirty button')
					.set('onclick', 'javascript:;')
					.set('href', '".$popup_load_url."')
					.set('rel', '{handler: \'iframe\', size: {x: 500, y: 240}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'advindexdirty.png', 'advindexdirty_f2.png', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY', false );
			JHtml::_('behavior.modal', 'li#toolbar-advindexdirty a.toolbar, #toolbar-advindexdirty button');
		} else {
			$toolbar->appendButton('Popup', 'advindexdirty', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY', $popup_load_url, 500, 240);
		}
		
		$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_INDEX_ADVANCED_PURGE', FLEXI_J16GE ? 'search.purge' : 'purge', false);
		
		$user  = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
	}
	
	function indexer($tpl) {
		$document = JFactory::getDocument();
		
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		
		parent::display($tpl);
	}
}
