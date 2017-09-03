<?php
/**
 * @version 1.5 stable $Id: view.html.php 1902 2014-05-10 16:06:11Z ggppdk $ 
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

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

/**
 * View class for the FLEXIcontent search indexes screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FLEXIcontentViewSearch extends JViewLegacy
{
	function display( $tpl = null )
	{
		// ***********
		// Batch tasks
		// ***********
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		
		$layout  = $jinput->get('layout', '', 'cmd');
		if($layout=='indexer')
		{
			$this->indexer($tpl);
			return;
		}
		


		// ********************
		// Initialise variables
		// ********************
		
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		
		// Get model
		$model = $this->getModel();
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		
		// ***********
		// Get filters
		// ***********
		
		$count_filters = 0;
		
		// Get filter vars
		$filter_order     = $model->getState( 'filter_order' );
		$filter_order_Dir = $model->getState( 'filter_order_Dir' );
		
		$filter_indextype = $model->getState( 'filter_indextype' );
		$isADV = $filter_indextype=='advanced';
		
		$filter_fieldtype	= $model->getState( 'filter_fieldtype' );
		$filter_itemtype	= $model->getState( 'filter_itemtype' );
		$filter_itemstate	= $model->getState( 'filter_itemstate' );
		if ($filter_fieldtype) $count_filters++; if ($filter_itemtype) $count_filters++; if ($filter_itemstate) $count_filters++;
		
		$search			= $model->getState( 'search' );
		$search			= $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		$search_itemtitle	= $model->getState( 'search_itemtitle' );
		$search_itemid		= $model->getState( 'search_itemid' );
		$search_itemid		= !empty($search_itemid) ? (int)$search_itemid : '';
		if ($search_itemtitle) $count_filters++; if ($search_itemid) $count_filters++;
		
		$filter_indextype	= $model->getState( 'filter_indextype' );
		
		$f_active['filter_fieldtype']	= (boolean)$filter_fieldtype;
		$f_active['filter_itemtype']	= (boolean)$filter_itemtype;
		$f_active['filter_itemstate']	= (boolean)$filter_itemstate;
		
		$f_active['search']			= strlen($search);
		$f_active['search_itemtitle']	= strlen($search_itemtitle);
		$f_active['search_itemid']		= (boolean)$search_itemid;
		
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		flexicontent_html::loadFramework('select2');
		//JHtml::_('behavior.tooltip');
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		$js = "jQuery(document).ready(function(){";
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanIndex');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_SEARCH_INDEX' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, FLEXI_J16GE ? 'searchtext.png' : 'searchindex' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		$this->setToolbar();
		
		$types			= $this->get( 'Typeslist' );
		$fieldtypes = flexicontent_db::getFieldTypes($_grouped=false, $_usage=true, $_published=false);
		
		// Build select lists
		$lists = array();
		
		//build backend visible filter
		if ($isADV) {
			$fftypes = array();
			$fftypes[] = JHtml::_('select.option',  '', '-' /*JText::_( 'FLEXI_ALL_FIELDS_TYPE' )*/ );
			$fftypes[] = JHtml::_('select.option',  'C', JText::_( 'FLEXI_CORE_FIELDS' ) );
			$fftypes[] = JHtml::_('select.option',  'NC', JText::_( 'FLEXI_CUSTOM_NON_CORE_FIELDS' ) );
			foreach ($fieldtypes as $field_type => $ftdata) {
				$fftypes[] = JHtml::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_type);
			}
			
			$lists['filter_fieldtype'] = ($filter_fieldtype || 1 ? '<div class="add-on">'.JText::_('FLEXI_FIELD_TYPE').'</div>' : '').
				JHtml::_('select.genericlist', $fftypes, 'filter_fieldtype', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_fieldtype );
		}
		
		//build type select list
		$lists['filter_itemtype'] = ($filter_itemtype|| 1 ? '<div class="add-on">'.JText::_('FLEXI_TYPE').'</div>' : '').
			flexicontent_html::buildtypesselect($types, 'filter_itemtype', $filter_itemtype, '-'/*true*/, 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'filter_itemtype');
		
		//publish unpublished filter
		$ffstates = array();
		$ffstates[] = JHtml::_('select.option',  '', '-' /*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$ffstates[] = JHtml::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$ffstates[] = JHtml::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		
		$lists['filter_itemstate'] = ($filter_itemstate || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHtml::_('select.genericlist', $ffstates, 'filter_itemstate', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_itemstate );
		
		// build filter index type record listing
		$itn['basic'] = JText::_( 'FLEXI_INDEX_BASIC' );
		$itn['advanced'] = JText::_( 'FLEXI_INDEX_ADVANCED' );
		$indextypes = array();
		//foreach ($itn as $i => $v) $indextypes[] = JHtml::_('select.option', $i, $v);
		//$lists['filter_indextype'] = JHtml::_('select.radiolist', $indextypes, 'filter_indextype', 'size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_indextype );
		$lists['filter_indextype'] = '';
		foreach ($itn as $i => $v) {
			$checked = $filter_indextype == $i ? ' checked="checked" ' : '';
			$lists['filter_indextype'] .= '<input type="radio" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_indextype'.$i.'" name="filter_indextype" />';
			$lists['filter_indextype'] .= '<label class="" id="filter_indextype'.$i.'-lbl" for="filter_indextype'.$i.'">'.$v.'</label>';
		}
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		// search index & item title  filter
		$lists['search']= $search;
		$lists['search_itemtitle']= $search_itemtitle;
		$lists['search_itemid']= $search_itemid;
		
		$rows   = $this->get('Data');  // MUST BE BEFORE getCount and getPagination because it also calculates total rows
		$total  = $this->get('Count');
		$pagination = $this->get('Pagination');
		$limitstart = $this->get('LimitStart');

		if ($filter_fieldtype) {
			$js .= "jQuery('.col_fieldtype').addClass('filtered_column');";
		}		
		if ($search) {
			$js .= "jQuery('.col_search').addClass('filtered_column');";
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
		
		$old_add_search_prefix = $app->getUserState('add_search_prefix', null);
		
		$add_search_prefix = $cparams->get('add_search_prefix', 0);
		$app->setUserState('add_search_prefix', $add_search_prefix);
		
		
		if ($old_add_search_prefix !== null && $old_add_search_prefix != $add_search_prefix) {
			$app->enqueueMessage('Parameter: "Searching small/common words" has changed, please recreate (just once) the search indexes, otherwise text search will not work', 'warning');
		}
		
		if ( !$cparams->get('add_search_prefix', 0) )     // Important usability messages
		{
			if ( $ft_min_word_len > 1 && $notice_ft_min_word_len < 10) {
				$app->setUserState( $option.'.fields.notice_ft_min_word_len', $notice_ft_min_word_len+1 );
				$app->enqueueMessage("NOTE : Database limits minimum search word length (ft_min_word_len) to ".$ft_min_word_len, 'message');
				$app->enqueueMessage('Please enable: "Searching small/common words":
					<a class="btn" href="index.php?option=com_config&view=component&component=com_flexicontent&path=&"><span class="icon-options"></span>Configuration</a>
					and then click to re-INDEX both search indexes', 'notice');
			}
		}
		
		
		
		$this->count_filters = $count_filters;
		$this->lists = $lists;
		$this->pagination = $pagination;
		$this->rows = $rows;
		$this->total = $total;
		$this->limitstart = $limitstart;
		$this->f_active = $f_active;
		
		$this->option = $option;
		$this->view = $view;
		$this->isADV = $isADV;
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
	
	
	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$js = "jQuery(document).ready(function(){";

		$document = JFactory::getDocument();
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		$btn_task = '';
		$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic';
		//$toolbar->appendButton('Popup', 'basicindex', 'FLEXI_INDEX_BASIC_CONTENT_LISTS', str_replace('&', '&amp;', $popup_load_url), 500, 350);
		$js .= "
			jQuery('#toolbar-basicindex a.toolbar, #toolbar-basicindex button').attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_BASIC_CONTENT_LISTS'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'basicindex.png', 'basicindex_f2.png', 'FLEXI_REINDEX_BASIC_CONTENT_LISTS', false );

		JToolbarHelper::divider();  JToolbarHelper::spacer();

		$btn_task = '';
		$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced';
		//$toolbar->appendButton('Popup', 'advindex', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW', str_replace('&', '&amp;', $popup_load_url), 500, 350);
		$js .= "
			jQuery('#toolbar-advindex a.toolbar, #toolbar-advindex button').attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'advindex.png', 'advindex_f2.png', 'FLEXI_REINDEX_ADVANCED_SEARCH_VIEW', false );

		$btn_task = '';
		$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced&rebuildmode=quick';
		//$toolbar->appendButton('Popup', 'advindexdirty', 'FLEXI_INDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY', str_replace('&', '&amp;', $popup_load_url), 500, 350);
		$js .= "
			jQuery('#toolbar-advindexdirty a.toolbar, #toolbar-advindexdirty button').attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'advindexdirty.png', 'advindexdirty_f2.png', 'FLEXI_REINDEX_ADVANCED_SEARCH_VIEW_DIRTY_ONLY', false );

		//$toolbar->appendButton('Confirm', 'FLEXI_DELETE_INDEX_CONFIRM', 'trash', 'FLEXI_INDEX_ADVANCED_PURGE', 'search.purge', false);
		$btn_icon = 'icon-trash';
		$btn_name = 'purge';
		$btn_task = 'search.purge';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_INDEX_ADVANCED_PURGE',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_PURGE_INDEX_CONFIRM'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning", $btn_icon);

		//$toolbar->appendButton('Confirm', 'Update ?', 'shuffle', 'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES', 'search.custom_order', false);
		$btn_icon = 'icon-shuffle';
		$btn_name = 'custom_order';
		$btn_task = 'search.custom_order';
		$extra_js = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_UPDATE_CUSTOM_ORDER_INDEXES',
			$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_UPDATE_CUSTOM_ORDER_INDEXES'),
			$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);

		// Configuration button
		$user  = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		if ($perms->CanConfig) {
			JToolbarHelper::divider(); JToolbarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
	}
	
	
	function indexer($tpl)
	{		
		parent::display($tpl);
	}
}
