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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JViewLegacy
{
	function display( $tpl = null )
	{
		//initialise variables
		global $globalcats;
		$app      = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$view     = JRequest::getVar('view');
		$task     = JRequest::getVar('task', '');
		$cid      = JRequest::getVar('cid', array());
		$bind_limit = JRequest::getInt('bind_limit', 1000);
		$fcform   = JRequest::getInt('fcform', 0);
		
		$session = JFactory::getSession();
		
		// Some flags
		$enable_translation_groups = $cparams->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
		$print_logging_info = $cparams->get('print_logging_info');
		
		if($task == 'copy') {
			$this->setLayout('copy');
			$this->_displayCopyMove($tpl, $cid);
			return;
		}
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.calendar');

		// Get filters
		$count_filters = 0;
		
		// File id filtering
		$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
		$filter_fileid = JRequest::getInt('filter_fileid', 0);
		if ($filter_fileid) $count_filters++;
		
		// Category filtering
		$filter_cats       = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',				'filter_cats',			'',		'int' );
		$filter_subcats    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_subcats',		'filter_subcats',		1,		'int' );
		$filter_catsinstate = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_catsinstate', 'filter_catsinstate', 1,		'int' );
		if ($filter_cats) $count_filters++;
		
		// Order type, order, order direction
		$filter_order_type = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_type',	'filter_order_type',	1,		'int' );
		$filter_order      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',			'filter_order',				'',		'cmd' );
		$filter_order_Dir  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir',	'filter_order_Dir',		'',		'word' );
		
		// Other filters
		$filter_tag    = $fcform ? JRequest::getVar('filter_tag')    : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_tag',    'filter_tag',    false,   'array' );
		$filter_lang	 = $fcform ? JRequest::getVar('filter_lang')   : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',   'filter_lang',   false,   'array' );
		$filter_type   = $fcform ? JRequest::getVar('filter_type')   : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_type',   'filter_type',   false,   'array' );
		$filter_author = $fcform ? JRequest::getVar('filter_author') : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author', 'filter_author', false,   'array' );
		$filter_state  = $fcform ? JRequest::getVar('filter_state')  : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',  'filter_state',  false,   'array' );
		$filter_access = $fcform ? JRequest::getVar('filter_access') : $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access', 'filter_access', false,   'array' );
		
		// Important set the session data of multiple-value filters
		if ($fcform) {
			$app->setUserState( $option.'.'.$view.'.filter_tag',    $filter_tag );
			$app->setUserState( $option.'.'.$view.'.filter_lang',   $filter_lang );
			$app->setUserState( $option.'.'.$view.'.filter_type',   $filter_type );
			$app->setUserState( $option.'.'.$view.'.filter_author', $filter_author );
			$app->setUserState( $option.'.'.$view.'.filter_state',  $filter_state );
			$app->setUserState( $option.'.'.$view.'.filter_access', $filter_access );
		}
		
		// Support for ZERO author id
		if (!is_array($filter_author) && !strlen($filter_author)) $filter_author = array();
		// Support for using 'ALL', 'ORPHAN' fake states, by clearing other values
		if (is_array($filter_state) && in_array('ALL', $filter_state))     $filter_state = array('ALL');
		if (is_array($filter_state) && in_array('ORPHAN', $filter_state))  $filter_state = array('ORPHAN');
		
		// Count active filters
		if ($filter_tag)   $count_filters++;  if ($filter_lang)   $count_filters++;
		if ($filter_type)  $count_filters++;  if ($filter_author) $count_filters++;
		if ($filter_state) $count_filters++;  if ($filter_access) $count_filters++;
		
		// Date filters
		$date	 				= $app->getUserStateFromRequest( $option.'.'.$view.'.date', 			'date', 			1, 			'int' );
		$startdate	 	= $app->getUserStateFromRequest( $option.'.'.$view.'.startdate', 	'startdate',	'',			'cmd' );
		$enddate	 		= $app->getUserStateFromRequest( $option.'.'.$view.'.enddate', 		'enddate', 		'', 		'cmd' );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;
		
		// Item ID filter
		$filter_id  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_id', 	'filter_id', 		'', 			'int' );
		$filter_id  = $filter_id ? $filter_id : '';
		if ($filter_id) $count_filters++;
		
		// Text search
		$scope  = $app->getUserStateFromRequest( $option.'.'.$view.'.scope', 			'scope', 			1, 			'int' );
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		
		// *********************************
		// Add custom css and js to document
		// *********************************
		
		$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');
		$document->addScript( JURI::base(true).'/components/com_flexicontent/assets/js/stateselector.js' );

		$js = "jQuery(document).ready(function(){";
		if ($filter_cats) {
			$js .= "jQuery('.col_cats').each(function(){ jQuery(this).addClass('yellow'); });";
		}		
		if ($filter_type) {
			$js .= "jQuery('.col_type').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_author) {
			$js .= "jQuery('.col_authors').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_state) {
			$js .= "jQuery('.col_state').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_lang) {
			$js .= "jQuery('.col_lang').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_access) {
			$js .= "jQuery('.col_access').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_tag) {
			$js .= "jQuery('.col_tag').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_id) {
			$js .= "jQuery('.col_id').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($startdate || $enddate) {
			if ($date == 1) {
				$js .= "jQuery('.col_created').each(function(){ jQuery(this).addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "jQuery('.col_revised').each(function(){ jQuery(this).addClass('yellow'); });";
			}
		}
		if (strlen($search)) {
			$js .= "jQuery('.col_title').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		
		// Check if user can create in at least one published category
		require_once("components/com_flexicontent/models/item.php");
		$itemmodel = new FlexicontentModelItem();
		$CanAdd = $itemmodel->getItemAccess()->get('access-create');
		
		// Performance consideration: ... CHECK ONLY global permissions, and not individual categories
		$perms = FlexicontentHelperPerm::getPerm();
		
		$CanEdit			= $perms->CanEdit;
		$CanPublish		= $perms->CanPublish;
		$CanDelete		= $perms->CanDelete;
		
		$CanEditOwn			= $perms->CanEditOwn;
		$CanPublishOwn	= $perms->CanPublishOwn;
		$CanDeleteOwn		= $perms->CanDeleteOwn;
		
		$hasEdit    = $CanEdit    || $CanEditOwn;
		$hasPublish = $CanPublish || $CanPublishOwn;
		$hasDelete  = $CanDelete  || $CanDeleteOwn;
		
		$CanCats		= $perms->CanCats;
		$CanAccLvl	= $perms->CanAccLvl;
		$CanOrder		= $perms->CanOrder;
		$CanCopy		= $perms->CanCopy;
		$CanArchives= $perms->CanArchives;
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('notvariable');
		
		
		// ******************
		// Create the toolbar
		// ******************
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_ITEMS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'items' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$toolbar = JToolBar::getInstance('toolbar');
		
		$add_divider = false;
		/*if ( $filter_stategrp != '') {
			$btn_task    = FLEXI_J16GE ? 'items.display' : 'display';
			$extra_js    = "document.getElementById('filter_stategrp').checked=true;";
			flexicontent_html::addToolBarButton(
				'FLEXI_DISPLAY_NORMAL', 'preview', $full_js='', $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=false);
			$add_divider = true;
		}*/
		
		/*if ( $hasDelete && $filter_stategrp != 'trashed' ) {
			$btn_task    = FLEXI_J16GE ? 'items.display' : 'display';
			$extra_js    = "document.getElementById('filter_stategrptrashed').checked=true;";
			flexicontent_html::addToolBarButton(
				'FLEXI_DISPLAY_TRASH', 'preview', $full_js='', $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=false);
			$add_divider = true;
		}
		if ($CanArchives && $filter_stategrp != 'archived') {
			$btn_task    = FLEXI_J16GE ? 'items.display' : 'display';
			$extra_js    = "document.getElementById('filter_stategrparchived').checked=true;";
			flexicontent_html::addToolBarButton(
				'FLEXI_DISPLAY_ARCHIVE', 'preview', $full_js='', $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=false);
			$add_divider = true;
		}
		if ($add_divider) { JToolBarHelper::divider(); }*/
		
		// Implementation of multiple-item state selector
		$add_divider = false;
		if ( $hasPublish ) {
			$btn_task = '';
			$ctrl_task = FLEXI_J16GE ? '&task=items.selectstate' : '&controller=items&task=selectstate';
			$popup_load_url = JURI::base().'index.php?option=com_flexicontent'.$ctrl_task.'&format=raw';
			if (FLEXI_J30GE || !FLEXI_J16GE) {  // Layout of Popup button broken in J3.1, add in J1.5 it generates duplicate HTML tag id (... just for validation), so add manually
				$js .= "
					jQuery('#toolbar-publish a.toolbar, #toolbar-publish button')
						.attr('onclick', 'javascript:;')
						.attr('href', '".$popup_load_url."')
						.attr('rel', '{handler: \'iframe\', size: {x: 800, y: 240}, onClose: function() {}}');
				";
				//JToolBarHelper::publishList( $btn_task );
				JToolBarHelper::custom( $btn_task, 'publish.png', 'publish_f2.png', 'FLEXI_CHANGE_STATE', false );
				JHtml::_('behavior.modal', '#toolbar-publish a.toolbar, #toolbar-publish button');
			} else {
				$toolbar->appendButton('Popup', 'publish', JText::_('FLEXI_CHANGE_STATE'), str_replace('&', '&amp;', $popup_load_url), 800, 240);
			}
			$add_divider = true;
		}
		
		if ($hasDelete) {
			if ( $filter_state && in_array('T',$filter_state) ) {
				//$btn_msg = JText::_('FLEXI_ARE_YOU_SURE');
				//$btn_task = FLEXI_J16GE ? 'items.remove' : 'remove';
				//JToolBarHelper::deleteList($btn_msg, $btn_task);
				$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = FLEXI_J16GE ? 'items.remove' : 'remove';
				$extra_js    = "";
				flexicontent_html::addToolBarButton(
					'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning");
			} else {
				$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_TRASH') );
				$msg_confirm = JText::_('FLEXI_TRASH_CONFIRM');
				$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
				$extra_js    = "document.adminForm.newstate.value='T';";
				flexicontent_html::addToolBarButton(
					'FLEXI_TRASH', 'trash', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true, $btn_class="");
			}
			$add_divider = true;
		}
		
		if ($CanArchives && (!$filter_state || !in_array('A',$filter_state))) {
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ARCHIVE')  );
			$msg_confirm = JText::_('FLEXI_ARCHIVE_CONFIRM');
			$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
			$extra_js    = "document.adminForm.newstate.value='A';";
			flexicontent_html::addToolBarButton(
				'FLEXI_ARCHIVE', 'archive', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			$add_divider = true;
		}
		
		if (
			($CanArchives && $filter_state && in_array('A',$filter_state)) ||
			($hasDelete   && $filter_state && in_array('T',$filter_state))
		) {
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_RESTORE') );
			$msg_confirm = JText::_('FLEXI_RESTORE_CONFIRM');
			$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
			$extra_js    = "document.adminForm.newstate.value='P';";
			flexicontent_html::addToolBarButton(
				'FLEXI_RESTORE', 'restore', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}
		if ($add_divider) { JToolBarHelper::divider(); }
		
		$add_divider = false;
		if ($CanAdd) {
			$btn_task = '';
			$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=types&format=raw';
			if (FLEXI_J30GE || !FLEXI_J16GE) {  // Layout of Popup button broken in J3.1, add in J1.5 it generates duplicate HTML tag id (... just for validation), so add manually
				$js .= "
					jQuery('#toolbar-new a.toolbar, #toolbar-new button')
						.attr('onclick', 'javascript:;')
						.attr('href', '".$popup_load_url."')
						.attr('rel', '{handler: \'iframe\', size: {x: 800, y: 240}, onClose: function() {}}');
				";
				//JToolBarHelper::addNew( $btn_task );
				JToolBarHelper::custom( $btn_task, 'new.png', 'new_f2.png', 'FLEXI_NEW', false );
				JHtml::_('behavior.modal', '#toolbar-new a.toolbar, #toolbar-new button');
			} else {
				$toolbar->appendButton('Popup', 'new',  JText::_('FLEXI_NEW'), str_replace('&', '&amp;', $popup_load_url), 800, 240);
			}
			$add_divider = true;
		}
		if ($hasEdit) {
			$btn_task = FLEXI_J16GE ? 'items.edit' : 'edit';
			JToolBarHelper::editList($btn_task);
			$add_divider = true;
		}
		if ($add_divider) { JToolBarHelper::divider(); }
		
		$add_divider = false;
		if ($CanAdd && $CanCopy) {
			$btn_task = FLEXI_J16GE ? 'items.copy' : 'copy';
			JToolBarHelper::custom( $btn_task, 'copy.png', 'copy_f2.png', 'FLEXI_COPY_MOVE' );
			if ($enable_translation_groups) {
				JToolBarHelper::custom( 'translate', 'translate', 'translate', 'FLEXI_TRANSLATE' );
			}
			$add_divider = true;
		}
		
		if ($add_divider) { JToolBarHelper::divider(); JToolBarHelper::spacer(); }
		if ($perms->CanConfig) {
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		// ***********************
		// Get data from the model
		// ***********************
		
		$model = $this->getModel();
		$badcatitems  = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=false, $checkInvalidCat=true);
		$unassociated = (int) $model->getUnboundedItems($limit=10000000, $count_only=true, $checkNoExtData=true, $checkInvalidCat=false);
		
		$rows     	= $this->get( 'Data');
		$pagination	= $this->get( 'Pagination' );
		$types			= $this->get( 'Typeslist' );
		$authors		= $this->get( 'Authorslist' );
		// these depend on data rows and must be called after getting data
		$extraCols  = $this->get( 'ExtraCols' );
		$customFilts= $this->get( 'CustomFilts' );
		foreach($customFilts as $filter) if (count($filter->value)) $count_filters++;
		$itemCats   = $this->get( 'ItemCats' );
		$itemTags   = $this->get( 'ItemTags' );
		
		if ($enable_translation_groups)  $langAssocs = $this->get( 'LangAssocs' );
		$langs = FLEXIUtilities::getLanguages('code');
		$categories = $globalcats ? $globalcats : array();
		
		
		$limit = $pagination->limit;
		$inline_ss_max = 30;
		$drag_reorder_max = 200;
		if ( $limit > $drag_reorder_max ) $cparams->set('draggable_reordering', 0);
		
		// ******************************************
		// Add usability notices if these are enabled
		// ******************************************
		
		if ( $cparams->get('show_usability_messages', 1)  && !$unassociated && !$badcatitems)     // Important usability messages
		{
			$notice_iss_disabled = $app->getUserStateFromRequest( $option.'.items.notice_iss_disabled',	'notice_iss_disabled',	0, 'int' );
			if (!$notice_iss_disabled && $limit > $inline_ss_max) {
				$app->setUserState( $option.'.items.notice_iss_disabled', 1 );
				$app->enqueueMessage(JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $inline_ss_max), 'notice');
				$show_turn_off_notice = 1;
			}
			
			$notice_drag_reorder_disabled = $app->getUserStateFromRequest( $option.'.items.notice_drag_reorder_disabled',	'notice_drag_reorder_disabled',	0, 'int' );
			if (!$notice_drag_reorder_disabled && $limit > $drag_reorder_max) {
				$app->setUserState( $option.'.items.notice_drag_reorder_disabled', 1 );
				$app->enqueueMessage(JText::sprintf('FLEXI_DRAG_REORDER_DISABLED', $drag_reorder_max), 'notice');
				$show_turn_off_notice = 1;
			}
			
			$notice_add_custom_columns = $app->getUserStateFromRequest( $option.'.items.notice_add_custom_columns',	'notice_add_custom_columns',	0, 'int' );
			if (!$notice_add_custom_columns) {
				$app->setUserState( $option.'.items.notice_add_custom_columns', 1 );
				$app->enqueueMessage(JText::_('FLEXI_YOU_MAY_CONFIGURE_CUSTOM_COLUMNS_GLOBAL_AND_PER_TYPE'), 'message');
				$show_turn_off_notice = 1;
			}
			
			if (!empty($show_turn_off_notice))
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'notice');
		}
		
		
		// *******************
		// Create Filters HTML
		// *******************
		
		// filter publication state
		$states 	= array();
		//$states[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$states[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_SINGLE_STATUS' ) );
		$states[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$states[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
		$states[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
		$states[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
		$states[] = JHTML::_('select.option',  'RV', JText::_( 'FLEXI_REVISED_VER' ) );
		$states[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ARCHIVED' ) );
		$states[] = JHTML::_('select.option',  'T', JText::_( 'FLEXI_TRASHED' ) );
		$states[] = JHTML::_('select.optgroup', '' );
		
		$states[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_STATUS_GROUPS' ) );
		$states[] = JHTML::_('select.option',  'ALL', JText::_( 'FLEXI_GRP_ALL' ).' '. JText::_( 'FLEXI_STATE_S' ) );
		$states[] = JHTML::_('select.option',  'ALL_P', JText::_( 'FLEXI_GRP_PUBLISHED' ).' '. JText::_( 'FLEXI_STATE_S' ) );
		$states[] = JHTML::_('select.option',  'ALL_U', JText::_( 'FLEXI_GRP_UNPUBLISHED' ).' '. JText::_( 'FLEXI_STATE_S' ) );
		$states[] = JHTML::_('select.option',  'ORPHAN', JText::_( 'FLEXI_GRP_ORPHAN' ) );
		$states[] = JHTML::_('select.optgroup', '' );

		$lists['filter_state'] = ($filter_state || 1 ? '<label class="label">'.JText::_('FLEXI_STATE').'</label>' : '').
			JHTML::_('select.genericlist', $states, 'filter_state[]', 'class="use_select2_lib fcfilter_be" multiple="multiple" size="3" onchange="Joomla.submitform()"', 'value', 'text', $filter_state );
			//JHTML::_('grid.state', $filter_state );
		
		// build filter state group
		if ($hasDelete || $CanArchives)   // Create state group filter only if user can delete or archive
		{
			//$stategroups[''] = JText::_( 'FLEXI_GRP_NORMAL' ) .' '. JText::_( 'FLEXI_STATE_S' );
			//$stategroups['published'] = JText::_( 'FLEXI_GRP_PUBLISHED' ) .' '. JText::_( 'FLEXI_STATE_S' );
			//$stategroups['unpublished'] = JText::_( 'FLEXI_GRP_UNPUBLISHED' ) .' '. JText::_( 'FLEXI_STATE_S' );
			/*if ($hasDelete)
				$stategroups['trashed']  = JText::_( 'FLEXI_GRP_TRASHED' );*/
			/*if ($CanArchives)
				$stategroups['archived'] = JText::_( 'FLEXI_GRP_ARCHIVED' );*/
			//$stategroups['orphan']      = JText::_( 'FLEXI_GRP_ORPHAN' );
			//$stategroups['all']      = JText::_( 'FLEXI_GRP_ALL' );
			
			/*$_stategroups = array();
			foreach ($stategroups as $i => $v) {
				$_stategroups[] = JHTML::_('select.option', $i, $v);
			}
			$lists['filter_stategrp'] = JHTML::_('select.radiolist', $_stategroups, 'filter_stategrp', 'size="1" class="inputbox" onchange="Joomla.submitform()"', 'value', 'text', $filter_stategrp );*/
			
			/*$lists['filter_stategrp'] = '';
			foreach ($stategroups as $i => $v) {
				$checked = $filter_stategrp == $i ? ' checked="checked" ' : '';
				$lists['filter_stategrp'] .= '<input type="radio" onchange="Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="filter_stategrp'.$i.'" name="filter_stategrp" />';
				$lists['filter_stategrp'] .= '<label class="" id="filter_stategrp'.$i.'-lbl" for="filter_stategrp'.$i.'">'.$v.'</label>';
			}*/
		}
		
		// build the include subcats boolean list
		
		// build the include non-published cats boolean list
		if ( ($filter_order_type && $filter_cats && ($filter_order=='i.ordering' || $filter_order=='catsordering')) ) {
			$ordering_tip  = '<img src="components/com_flexicontent/assets/images/comment.png" class="hasTip" title="'.JText::_('FLEXI_REASON', true).'::'.JText::_('<u>Items may belong to both</u>: <br/> (a) currently filtered category <br/>(b) to any of its subcategories, <br/> -- Thus item may get listed in a subcategory instead of the currently filtered category, to avoid this sub-categories are not included.', true).'" />';
			$lists['filter_subcats'] = '<span class="fc-mssg-inline fc-note">'.JText::_( 'Sub-categories not included during specific category ordering: ' )
			.$ordering_tip
			.'</span>';
		} else {
			//$lists['filter_subcats'] = JHTML::_('select.booleanlist',  'filter_subcats', 'class="inputbox" onchange="Joomla.submitform()"', $filter_subcats );
			$subcats = array();
			$subcats[] = JHTML::_('select.option', 0, JText::_( 'FLEXI_NO' ) );
			$subcats[] = JHTML::_('select.option', 1, JText::_( 'FLEXI_YES' ) );
			$lists['filter_subcats'] = JHTML::_('select.genericlist', $subcats, 'filter_subcats', 'size="1" class="use_select2_lib" onchange="Joomla.submitform()"', 'value', 'text', $filter_subcats, 'filter_subcats' );
		}

		// build the include non-published cats boolean list
		$catsinstate[1] = JText::_( 'FLEXI_PUBLISHED' );
		$catsinstate[0] = JText::_( 'FLEXI_UNPUBLISHED' );
		$catsinstate[99] = JText::_( 'FLEXI_ANY' );
		$catsinstate[2] = JText::_( 'FLEXI_ARCHIVED_STATE' );
		$catsinstate[-2] = JText::_( 'FLEXI_TRASHED_STATE' );
		$_catsinstate = array();
		foreach ($catsinstate as $i => $v) {
			$_catsinstate[] = JHTML::_('select.option', $i, $v);
		}
		$lists['filter_catsinstate'] = ($filter_catsinstate || 1 ? '<label class="label">'.JText::_('FLEXI_LIST_ITEMS_IN_CATS').'</label>' : '').
			JHTML::_('select.genericlist', $_catsinstate, 'filter_catsinstate', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="Joomla.submitform()"', 'value', 'text', $filter_catsinstate, 'filter_catsinstate' );
		//$lists['filter_catsinstate'] = JHTML::_('select.radiolist', $_catsinstate, 'filter_catsinstate', 'size="1" class="inputbox" onchange="Joomla.submitform()"', 'value', 'text', $filter_catsinstate );
		/*$lists['filter_catsinstate']  = '';
		foreach ($catsinstate as $i => $v) {
			$checked = $filter_catsinstate == $i ? ' checked="checked" ' : '';
			$lists['filter_catsinstate'] .= '<input type="radio" onchange="Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="filter_catsinstate'.$i.'" name="filter_catsinstate" />';
			$lists['filter_catsinstate'] .= '<label class="" id="filter_catsinstate'.$i.'-lbl" for="filter_catsinstate'.$i.'">'.$v.'</label>';
		}*/
		
		// build the order type boolean list
		$order_types = array();
		$order_types[] = JHTML::_('select.option', '0', JText::_( 'FLEXI_ORDER_JOOMLA' ) );
		$order_types[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_ORDER_FLEXICONTENT' ) );
		//$lists['filter_order_type'] = JHTML::_('select.radiolist', $order_types, 'filter_order_type', 'size="1" class="inputbox" onchange="Joomla.submitform()"', 'value', 'text', $filter_order_type );
		$lists['filter_order_type'] = JHTML::_('select.genericlist', $order_types, 'filter_order_type', 'size="1" class="use_select2_lib fc_skip_highlight" onchange="Joomla.submitform()"', 'value', 'text', $filter_order_type, 'filter_order_type' );
		
		// build the categories select list for filter
		$lists['filter_cats'] = ($filter_cats || 1 ? '<label class="label">'.JText::_('FLEXI_CATEGORY').'</label>' : '').
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-'/*2*/, 'class="use_select2_lib" size="1" onchange="Joomla.submitform()"', $check_published=false, $check_perms=false);

		//build type select list
		$lists['filter_type'] = ($filter_type || 1 ? '<label class="label">'.JText::_('FLEXI_TYPE').'</label>' : '').
			flexicontent_html::buildtypesselect($types, 'filter_type[]', $filter_type, 0/*'-'*//*true*/, 'class="use_select2_lib fcfilter_be" multiple="multiple" size="3" onchange="Joomla.submitform()"', 'filter_type');

		//build authors select list
		$lists['filter_author'] = ($filter_author || 1 ? '<label class="label">'.JText::_('FLEXI_AUTHOR').'</label>' : '').
			flexicontent_html::buildauthorsselect($authors, 'filter_author[]', $filter_author, 0/*'-'*//*true*/, 'class="use_select2_lib fcfilter_be" multiple="multiple" size="3" onchange="Joomla.submitform()"');

		if ($badcatitems) $lists['default_cat'] = flexicontent_cats::buildcatselect($categories, 'default_cat', '', 2, 'class="use_select2_lib"', false, false);
		
		//search filter
		$scopes = array();
		$scopes[1] = JText::_( 'FLEXI_TITLE' );
		$scopes[2] = JText::_( 'FLEXI_INTROTEXT' );
		$scopes[4] = JText::_( 'FLEXI_INDEXED_CONTENT' );
		$_scopes = array();
		foreach ($scopes as $i => $v) {
			$_scopes[] = JHTML::_('select.option', $i, $v);
		}
		//$lists['scope'] = JHTML::_('select.radiolist', $_scopes, 'scope', 'size="1" class="inputbox"', 'value', 'text', $scope );
		$lists['scope'] = JHTML::_('select.genericlist', $_scopes, 'scope', 'size="1" class="use_select2_lib fc_skip_highlight" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'"', 'value', 'text', $scope, 'scope' );
		/*$lists['scope']  = '';
		foreach ($scopes as $i => $v) {
			$checked = $scope == $i ? ' checked="checked" ' : '';
			$lists['scope'] .= '<input type="radio" onchange="Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="scope'.$i.'" name="scope" />';
			$lists['scope'] .= '<label class="" id="scope'.$i.'-lbl" for="scope'.$i.'">'.$v.'</label>';
		}*/
		
		// build item dates option list
		$dates[1] = JText::_( 'FLEXI_CREATED' );
		$dates[2] = JText::_( 'FLEXI_REVISED' );
		$_dates = array();
		foreach ($dates as $i => $v) {
			$_dates[] = JHTML::_('select.option', $i, $v);
		}
		//$lists['date'] = JHTML::_('select.radiolist', $_dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );
		$lists['date'] = '<label class="label">'.JText::_('FLEXI_DATE').'</label>'.
			JHTML::_('select.genericlist', $_dates, 'date', 'size="1" class="use_select2_lib fc_skip_highlight"', 'value', 'text', $date, 'date' );
		/*$lists['date']  = '';
		foreach ($dates as $i => $v) {
			$checked = $date == $i ? ' checked="checked" ' : '';
			$lists['date'] .= '<input type="radio" onchange="Joomla.submitform()" class="inputbox" '.$checked.' value="'.$i.'" id="date'.$i.'" name="date" />';
			$lists['date'] .= '<label class="" id="date'.$i.'-lbl" for="date'.$i.'">'.$v.'</label>';
		}*/
		
		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_FROM')));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_TO')));

		// search filter
		$bind_limits = array();
		$bind_limits[] = JHTML::_('select.option', 250, '250 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHTML::_('select.option', 500, '500 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHTML::_('select.option', 750, '750 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHTML::_('select.option', 1000,'1000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHTML::_('select.option', 1500,'1500 ' . JText::_( 'FLEXI_ITEMS' ) );
		$bind_limits[] = JHTML::_('select.option', 2000,'2000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$lists['bind_limits'] = JHTML::_('select.genericlist', $bind_limits, 'bind_limit', 'size="1" class="use_select2_lib"', 'value', 'text', $bind_limit, 'bind_limit' );

		// search filter
		$lists['search'] = $search;
		// search id
		$lists['filter_id'] = $filter_id;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// filter ordering
		if ( !$filter_order_type )
		{
			$ordering = ($lists['order'] == 'i.ordering');
		} else {
			$ordering = ($lists['order'] == 'catsordering');
		}
		
		//build tags filter
		$lists['filter_tag'] = ($filter_tag || 1 ? '<label class="label">'.JText::_('FLEXI_TAG').'</label>' : '').
			flexicontent_html::buildtagsselect('filter_tag[]', 'class="use_select2_lib fcfilter_be" onchange="Joomla.submitform()" multiple="multiple" size="3" ', $filter_tag, 0);

		//build languages filter
		$lists['filter_lang'] = ($filter_lang || 1 ? '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>' : '').
			flexicontent_html::buildlanguageslist('filter_lang[]', 'class="use_select2_lib fcfilter_be" onchange="Joomla.submitform()" multiple="multiple" size="3" ', $filter_lang, 1/*'-'*//*2*/);
		
		// build access level filter
		$access_levels = JHtml::_('access.assetgroups');
		/*if ( $cparams->get('iman_viewable_items', 1) )  // only viewable items is enabled, skip the non available levels to avoid user confusion
		{
			$_aid_arr = array_flip(JAccess::getAuthorisedViewLevels($user->id));
			$_levels = array();
			foreach($access_levels as $i => $level)
			{
				if ( isset($_aid_arr[$level->value]) )
					$_levels[] = $level;
				//else $access_levels[$i]->disable = 1;
			}
			$access_levels = $_levels;
		}*/  // Above code is maybe problematic (e.g. in multi-sites), need to test more
		array_unshift($access_levels, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );
		$fieldname = 'filter_access[]';  // make multivalue
		$elementid = 'filter_access';
		$attribs = 'class="use_select2_lib fcfilter_be" onchange="Joomla.submitform()" multiple="multiple';
		$lists['filter_access'] = ($filter_access || 1 ? '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>' : '').
			JHTML::_('select.genericlist', $access_levels, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		// filter by item usage a specific file
		if ($fileid_to_itemids && count($fileid_to_itemids)) {
			$files_data = $model->getFileData(array_keys($fileid_to_itemids));
			$file_options = array();
			$file_options[] = JHTML::_('select.option',  '', '-'/*.JText::_( 'FLEXI_SELECT' ).' '.JText::_( 'FLEXI_FILE' )*/ );
			foreach($files_data as $_file) {
				$file_options[] = JHTML::_('select.option', $_file->id, $_file->altname );
			}
			flexicontent_html::loadFramework('select2');
			$lists['filter_fileid'] = ($filter_fileid || 1 ? '<label class="label label-warning">'.JText::_('FLEXI_ITEMS_USING').' '.JText::_('FLEXI_FILE').'</label>' : '').
				JHTML::_('select.genericlist', $file_options, 'filter_fileid', 'size="1" class="use_select2_lib" onchange="Joomla.submitform()"', 'value', 'text', $filter_fileid );
		}
		
		//assign data to template
		$this->assignRef('CanTemplates', $perms->CanTemplates);
		$this->assignRef('count_filters', $count_filters);
		//$this->assignRef('filter_stategrp', $filter_stategrp);
		$this->assignRef('filter_catsinstate', $filter_catsinstate);
		$this->assignRef('db'				, $db);
		$this->assignRef('lists'		, $lists);
		$this->assignRef('rows'			, $rows);
		$this->assignRef('itemCats'	, $itemCats);
		$this->assignRef('itemTags'	, $itemTags);
		$this->assignRef('extra_fields'	, $extraCols);
		$this->assignRef('custom_filts'	, $customFilts);
		if ($enable_translation_groups)  $this->assignRef('lang_assocs', $langAssocs);
		if (FLEXI_FISH || FLEXI_J16GE)   $this->assignRef('langs', $langs);
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('pagination'	, $pagination);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('CanOrder'		, $CanOrder);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanAccLvl'	, $CanAccLvl);
		$this->assignRef('unassociated'	, $unassociated);
		$this->assignRef('badcatitems'	, $badcatitems);
		
		// filters
		$this->assignRef('filter_id'			, $filter_id);
		$this->assignRef('filter_state'		, $filter_state);
		$this->assignRef('filter_author'	, $filter_author);
		$this->assignRef('filter_type'		, $filter_type);
		
		$this->assignRef('filter_cats'		, $filter_cats);
		$this->assignRef('filter_subcats'	, $filter_subcats);
		$this->assignRef('filter_catsinstate'	, $filter_catsinstate);
		
		$this->assignRef('filter_order_type', $filter_order_type);
		$this->assignRef('filter_order'     ,  $filter_order);
		
		$this->assignRef('filter_lang'		, $filter_lang);
		$this->assignRef('filter_access'	, $filter_access);
		$this->assignRef('filter_tag'		  , $filter_tag);
		$this->assignRef('filter_fileid'	, $filter_fileid);
		
		$this->assignRef('inline_ss_max'	, $inline_ss_max);
		$this->assignRef('scope'			, $scope);
		$this->assignRef('search'			, $search);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'	, $startdate);
		$this->assignRef('enddate'		, $enddate);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);

		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
		
		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}

	function _displayCopyMove($tpl = null, $cid)
	{
		global $globalcats;
		$app = JFactory::getApplication();

		//initialise variables
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();
		$option		= JRequest::getCmd( 'option' );
		
		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');

		//add js functions
		$document->addScript('components/com_flexicontent/assets/js/copymove.js');

		//get vars
		$filter_order     = $app->getUserStateFromRequest( $option.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );
		
		//$perms 	= FlexicontentHelperPerm::getPerm();
		
		
		// Create document/toolbar titles
		$copy_behaviour = JRequest::getVar('copy_behaviour','copy/move');
		if ($copy_behaviour == 'translate') {
			$doc_title =  JText::_( 'FLEXI_TRANSLATE_ITEM' );
		} else {
			$doc_title = JText::_( 'FLEXI_COPYMOVE_ITEM' );
		}
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'itemadd' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		JToolBarHelper::save(FLEXI_J16GE ? 'items.copymove' : 'copymove');
		JToolBarHelper::cancel(FLEXI_J16GE ? 'items.cancel' : 'cancel');

		//Get data from the model
		$rows     = $this->get( 'Data');
		$itemCats = $this->get( 'ItemCats' );		
		$categories = $globalcats;
		
		// build the main category select list
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat', '', 0, 'class="inputbox" size="10"', false, false);
		// build the secondary categories select list
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="inputbox" multiple="multiple" size="10"', false, false);

		//assign data to template
		$this->assignRef('lists'     	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('itemCats'		, $itemCats);
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('user'				, $user);
		
		parent::display($tpl);
	}
}
?>
