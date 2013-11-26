<?php
/**
 * @version 1.5 stable $Id: view.html.php 1794 2013-10-22 02:41:41Z ggppdk $
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

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JViewLegacy {

	function display($tpl = null)
	{
		global $globalcats;
		$app     = JFactory::getApplication();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		//initialise variables
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd( 'option' );
		$task     = JRequest::getVar('task', '');
		$cid      = JRequest::getVar('cid', array());
		$extlimit = JRequest::getInt('extlimit', 500);
		
		$session = JFactory::getSession();
		$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
		$filter_fileid = JRequest::getInt('filter_fileid', 0);
		
		// Some flags
		$enable_translation_groups = $cparams->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
		$print_logging_info = $cparams->get('print_logging_info');
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		
		if($task == 'copy') {
			$this->setLayout('copy');
			$this->_displayCopyMove($tpl, $cid);
			return;
		}
		
		flexicontent_html::loadJQuery();
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.calendar');

		// Get filters
		$filter_cats       = $app->getUserStateFromRequest( $option.'.items.filter_cats',				'filter_cats',			'',		'int' );
		$filter_subcats    = $app->getUserStateFromRequest( $option.'.items.filter_subcats',		'filter_subcats',		1,		'int' );
		$filter_catsinstate = $app->getUserStateFromRequest( $option.'.items.filter_catsinstate', 'filter_catsinstate',2,		'int' );
		
		$filter_order_type = $app->getUserStateFromRequest( $option.'.items.filter_order_type',	'filter_order_type',	1,		'int' );
		$filter_order      = $app->getUserStateFromRequest( $option.'.items.filter_order',			'filter_order',				'',		'cmd' );
		$filter_order_Dir  = $app->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',		'',		'word' );
		
		$filter_type			= $app->getUserStateFromRequest( $option.'.items.filter_type',				'filter_type',			0,		'int' );
		$filter_authors		= $app->getUserStateFromRequest( $option.'.items.filter_authors',			'filter_authors',		0,		'int' );
		$filter_state 		= $app->getUserStateFromRequest( $option.'.items.filter_state',				'filter_state',			'',		'word' );
		$filter_stategrp	= $app->getUserStateFromRequest( $option.'.items.filter_stategrp',		'filter_stategrp',	'',		'word' );
		
		if (FLEXI_FISH || FLEXI_J16GE) {
			$filter_lang	 = $app->getUserStateFromRequest( $option.'.items.filter_lang', 		'filter_lang', 		'', 			'string' );
		}
		
		$scope	 			= $app->getUserStateFromRequest( $option.'.items.scope', 			'scope', 			1, 			'int' );
		$date	 				= $app->getUserStateFromRequest( $option.'.items.date', 			'date', 			1, 			'int' );
		
		$startdate	 	= $app->getUserStateFromRequest( $option.'.items.startdate', 	'startdate',	'',			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $app->setUserState( $option.'.items.startdate', '' ); }
		
		$enddate	 		= $app->getUserStateFromRequest( $option.'.items.enddate', 		'enddate', 		'', 		'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate	= $app->setUserState( $option.'.items.enddate', '' ); }
		
		$filter_id 		= $app->getUserStateFromRequest( $option.'.items.filter_id', 	'filter_id', 		'', 			'int' );
		$search 			= $app->getUserStateFromRequest( $option.'.items.search', 		'search', 			'', 			'string' );
		$search 			= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		
		// Add custom css and js to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/stateselector.js' );
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/flexi-lib.js' );

		$js = "window.addEvent('domready', function(){";
		if ($filter_cats) {
			$js .= "$$('.col_cats').each(function(el){ el.addClass('yellow'); });";
		}		
		if ($filter_type) {
			$js .= "$$('.col_type').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_authors) {
			$js .= "$$('.col_authors').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_state) {
			$js .= "$$('.col_state').each(function(el){ el.addClass('yellow'); });";
		}
		if (FLEXI_FISH || FLEXI_J16GE) {
			if ($filter_lang) {
				$js .= "$$('.col_lang').each(function(el){ el.addClass('yellow'); });";
			}
		}
		if ($filter_id) {
			$js .= "$$('.col_id').each(function(el){ el.addClass('yellow'); });";
		}
		if ($startdate || $enddate) {
			if ($date == 1) {
				$js .= "$$('.col_created').each(function(el){ el.addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "$$('.col_revised').each(function(el){ el.addClass('yellow'); });";
			}
		}
		if ($search) {
			$js .= "$$('.col_title').each(function(el){ el.addClass('yellow'); });";
		} else {
			$js .= "$$('.col_title').each(function(el){ el.removeClass('yellow'); });";
		}
		
		$perms 	= FlexicontentHelperPerm::getPerm();
		$CanAdd				= $perms->CanAdd;
		
		$CanEdit			= $perms->CanEdit;
		$CanPublish		= $perms->CanPublish;
		$CanDelete		= $perms->CanDelete;
		
		$CanEditOwn			= $perms->CanEditOwn;
		$CanPublishOwn	= $perms->CanPublishOwn;
		$CanDeleteOwn		= $perms->CanDeleteOwn;
		
		$CanCats		= $perms->CanCats;
		$CanRights	= $perms->CanConfig;
		$CanOrder		= $perms->CanOrder;
		$CanCopy		= $perms->CanCopy;
		$CanArchives= $perms->CanArchives;
		
		FLEXISubmenu('notvariable');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_ITEMS' ), 'items' );
		$toolbar = JToolBar::getInstance('toolbar');
		
		$add_divider = false;
		if ( $filter_stategrp != '') {
			$btn_task    = FLEXI_J16GE ? 'items.display' : 'display';
			$extra_js    = "document.getElementById('filter_stategrp').checked=true;";
			flexicontent_html::addToolBarButton(
				'FLEXI_DISPLAY_NORMAL', 'preview', $full_js='', $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=false);
			$add_divider = true;
		}
		
		/*if ( ($CanDelete || $CanDeleteOwn) && $filter_stategrp != 'trashed' ) {
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
		if ($add_divider) { JToolBarHelper::divider(); JToolBarHelper::spacer(); }*/
		
		// Implementation of multiple-item state selector
		$add_divider = false;
		if ( $CanPublish || $CanPublishOwn ) {
			$btn_task = FLEXI_J16GE ? '&task=items.selectstate' : '&controller=items&task=selectstate';
			$popup_load_url = JURI::base().'index.php?option=com_flexicontent'.$btn_task.'&format=raw';
			if (FLEXI_J30GE) {  // Layout of Popup button broken in J3.1, add manually
				$js .= "
					$$('li#toolbar-publish a.toolbar, #toolbar-publish button')
						.set('onclick', 'javascript:;')
						.set('href', '".$popup_load_url."')
						.set('rel', '{handler: \'iframe\', size: {x: 800, y: 240}, onClose: function() {}}');
				";
				//JToolBarHelper::publishList( $btn_task );
				JToolBarHelper::custom( $btn_task, 'publish.png', 'publish_f2.png', 'FLEXI_CHANGE_STATE', false );
				JHtml::_('behavior.modal', 'li#toolbar-publish a.toolbar, #toolbar-publish button');
			} else {
				$toolbar->appendButton('Popup', 'publish', JText::_('FLEXI_CHANGE_STATE'), $popup_load_url, 800, 240);
			}
			$add_divider = true;
		}
		if ($CanDelete || $CanDeleteOwn) {
			if ( $filter_stategrp == 'trashed' ) {
				$btn_msg = 'FLEXI_ARE_YOU_SURE';
				$btn_task = FLEXI_J16GE ? 'items.remove' : 'remove';
				JToolBarHelper::deleteList($btn_msg, $btn_task);
			} else {
				$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_TRASH') );
				$msg_confirm = JText::_('FLEXI_TRASH_CONFIRM');
				$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
				$extra_js    = "document.adminForm.newstate.value='T';";
				flexicontent_html::addToolBarButton(
					'FLEXI_TRASH', 'trash', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			}
			$add_divider = true;
		}
		if ($CanArchives && $filter_stategrp != 'archived') {
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ARCHIVE')  );
			$msg_confirm = JText::_('FLEXI_ARCHIVE_CONFIRM');
			$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
			$extra_js    = "document.adminForm.newstate.value='A';";
			flexicontent_html::addToolBarButton(
				'FLEXI_ARCHIVE', 'archive', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			$add_divider = true;
		}
		if ( ($CanArchives && $filter_stategrp=='archived') || (($CanDelete || $CanDeleteOwn) && $filter_stategrp=='trashed') ) {
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_RESTORE') );
			$msg_confirm = JText::_('FLEXI_RESTORE_CONFIRM');
			$btn_task    = FLEXI_J16GE ? 'items.changestate' : 'changestate';
			$extra_js    = "document.adminForm.newstate.value='P';";
			flexicontent_html::addToolBarButton(
				'FLEXI_RESTORE', 'restore', $full_js='', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}
		if ($add_divider) { JToolBarHelper::divider(); JToolBarHelper::spacer(); }
		
		$add_divider = false;
		if ($CanAdd) {
			$btn_task = FLEXI_J16GE ? 'items.add' : 'add';
			$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=types&format=raw';
			if (FLEXI_J30GE) {  // Layout of Popup button broken in J3.1, add manually
				$js .= "
					$$('li#toolbar-new a.toolbar, #toolbar-new button')
						.set('onclick', 'javascript:;')
						.set('href', '".$popup_load_url."')
						.set('rel', '{handler: \'iframe\', size: {x: 800, y: 240}, onClose: function() {}}');
				";
				//JToolBarHelper::addNew( $btn_task );
				JToolBarHelper::custom( $btn_task, 'new.png', 'new_f2.png', 'FLEXI_NEW', false );
				JHtml::_('behavior.modal', 'li#toolbar-new a.toolbar, #toolbar-new button');
			} else {
				$toolbar->appendButton('Popup', 'new',  JText::_('FLEXI_NEW'), $popup_load_url, 800, 240);
			}
			$add_divider = true;
		}
		if ($CanEdit || $CanEditOwn) {
			$btn_task = FLEXI_J16GE ? 'items.edit' : 'edit';
			JToolBarHelper::editList($btn_task);
			$add_divider = true;
		}
		if ($add_divider) { JToolBarHelper::divider(); JToolBarHelper::spacer(); }
		
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
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		// ***********************
		// Get data from the model
		// ***********************
		
		$model = $this->getModel();
		$unassociated	= $model->getUnassociatedItems(1000000, $_ids_only=true);
		
		$rows     	= $this->get( 'Data');
		$pagination	= $this->get( 'Pagination' );
		$types			= $this->get( 'Typeslist' );
		$authors		= $this->get( 'Authorslist' );
		// these depend on data rows and must be called after getting data
		$extraCols  = $this->get( 'ExtraCols' );
		$itemCats   = $this->get( 'ItemCats' );
		
		if ($enable_translation_groups)  $langAssocs = $this->get( 'LangAssocs' );
		if (FLEXI_FISH || FLEXI_J16GE)   $langs = FLEXIUtilities::getLanguages('code');
		$categories = $globalcats ? $globalcats : array();
		
		
		$limit = $pagination->limit;
		$inline_ss_max = 30;
		$drag_reorder_max = 100;
		if ( $limit > $drag_reorder_max ) $cparams->set('draggable_reordering', 0);
		
		// ******************************************
		// Add usability notices if these are enabled
		// ******************************************
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
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
			if (!empty($show_turn_off_notice))
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'notice');
		}
		
		
		// *******************
		// Create Filters HTML
		// *******************
		
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_STATE' ) );
		$state[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',  'RV', JText::_( 'FLEXI_REVISED_VER' ) );

		$lists['filter_state'] = JHTML::_('select.genericlist',   $state, 'filter_state', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_state );
		
		// build filter state group
		if ($CanDelete || $CanDeleteOwn || $CanArchives)   // Create state group filter only if user can delete or archive
		{
			$stategroups[''] = JText::_( 'FLEXI_GRP_NORMAL' ) .' '. JText::_( 'FLEXI_STATE_S' );
			$stategroups['published'] = JText::_( 'FLEXI_GRP_PUBLISHED' ) .' '. JText::_( 'FLEXI_STATE_S' );
			$stategroups['unpublished'] = JText::_( 'FLEXI_GRP_UNPUBLISHED' ) .' '. JText::_( 'FLEXI_STATE_S' );
			if ($CanDelete || $CanDeleteOwn)
				$stategroups['trashed']  = JText::_( 'FLEXI_GRP_TRASHED' );
			if ($CanArchives)
				$stategroups['archived'] = JText::_( 'FLEXI_GRP_ARCHIVED' );
			$stategroups['orphan']      = JText::_( 'FLEXI_GRP_ORPHAN' );
			$stategroups['all']      = JText::_( 'FLEXI_GRP_ALL' );
			
			/*$_stategroups = array();
			foreach ($stategroups as $i => $v) {
				$_stategroups[] = JHTML::_('select.option', $i, $v);
			}
			$lists['filter_stategrp'] = JHTML::_('select.radiolist', $_stategroups, 'filter_stategrp', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_stategrp );*/
			
			$lists['filter_stategrp'] = '';
			foreach ($stategroups as $i => $v) {
				$checked = $filter_stategrp == $i ? ' checked="checked" ' : '';
				$lists['filter_stategrp'] .= '<input type="radio" onchange="submitform();" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_stategrp'.$i.'" name="filter_stategrp">';
				$lists['filter_stategrp'] .= '<label class="" id="filter_stategrp'.$i.'-lbl" for="filter_stategrp'.$i.'">'.$v.'</label>';
			}
		}
		
		// build the include subcats boolean list
		$lists['filter_subcats'] = JHTML::_('select.booleanlist',  'filter_subcats', 'class="inputbox" onchange="submitform();"', $filter_subcats );
		
		// build the include non-published cats boolean list
		$catsinstate[2] = JText::_( 'FLEXI_ANY' );
		$catsinstate[1] = JText::_( 'FLEXI_PUBLISHED' );
		$catsinstate[0] = JText::_( 'FLEXI_UNPUBLISHED' );
		/*$_catsinstate = array();
		foreach ($catsinstate as $i => $v) {
			$_catsinstate[] = JHTML::_('select.option', $i, $v);
		}
		$lists['filter_catsinstate'] = JHTML::_('select.radiolist', $_catsinstate, 'filter_catsinstate', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_catsinstate );*/
		$lists['filter_catsinstate']  = '';
		foreach ($catsinstate as $i => $v) {
			$checked = $filter_catsinstate == $i ? ' checked="checked" ' : '';
			$lists['filter_catsinstate'] .= '<input type="radio" onchange="submitform();" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="filter_catsinstate'.$i.'" name="filter_catsinstate">';
			$lists['filter_catsinstate'] .= '<label class="" id="filter_catsinstate'.$i.'-lbl" for="filter_catsinstate'.$i.'">'.$v.'</label>';
		}
		
		// build the order type boolean list
		$order_types = array();
		$order_types[] = JHTML::_('select.option', '0', JText::_( 'FLEXI_ORDER_JOOMLA' ).'<br/>' );
		$order_types[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_ORDER_FLEXICONTENT' ) );
		$lists['filter_order_type'] = JHTML::_('select.radiolist', $order_types, 'filter_order_type', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_order_type );
		
		// build the categories select list for filter
		$lists['filter_cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="submitform( );"', $check_published=false, $check_perms=false);

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"', 'filter_type');

		//build authors select list
		$lists['filter_authors'] = flexicontent_html::buildauthorsselect($authors, 'filter_authors', $filter_authors, true, 'class="inputbox" size="1" onchange="submitform( );"');

		if ($unassociated) $lists['default_cat'] = flexicontent_cats::buildcatselect($categories, 'default_cat', '', 2, 'class="inputbox"', false, false);
		
		//search filter
		$scopes = array();
		$scopes[1] = JText::_( 'FLEXI_TITLE' );
		$scopes[2] = JText::_( 'FLEXI_INTROTEXT' );
		$scopes[4] = JText::_( 'FLEXI_INDEXED_CONTENT' );
		/*$_scopes = array();
		foreach ($scopes as $i => $v) {
			$_scopes[] = JHTML::_('select.option', $i, $v);
		}
		$lists['scope'] = JHTML::_('select.radiolist', $_scopes, 'scope', 'size="1" class="inputbox"', 'value', 'text', $scope );*/
		$lists['scope']  = '';
		foreach ($scopes as $i => $v) {
			$checked = $scope == $i ? ' checked="checked" ' : '';
			$lists['scope'] .= '<input type="radio" onchange="submitform();" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="scope'.$i.'" name="scope">';
			$lists['scope'] .= '<label class="" id="scope'.$i.'-lbl" for="scope'.$i.'">'.$v.'</label>';
		}
		
		// build item dates option list
		$dates[1] = JText::_( 'FLEXI_CREATED' );
		$dates[2] = JText::_( 'FLEXI_REVISED' );
		/*$_dates = array();
		foreach ($dates as $i => $v) {
			$_dates[] = JHTML::_('select.option', $i, $v);
		}
		$lists['date'] = JHTML::_('select.radiolist', $_dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );*/
		$lists['date']  = '';
		foreach ($dates as $i => $v) {
			$checked = $date == $i ? ' checked="checked" ' : '';
			$lists['date'] .= '<input type="radio" onchange="submitform();" class="inputbox" size="1" '.$checked.' value="'.$i.'" id="date'.$i.'" name="date">';
			$lists['date'] .= '<label class="" id="date'.$i.'-lbl" for="date'.$i.'">'.$v.'</label>';
		}
		
		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));

		// search filter
		$extdata = array();
		$extdata[] = JHTML::_('select.option', 100, '100 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 250, '200 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 500, '500 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 1000,'1000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 2000,'2000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 3000,'3000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 4000,'4000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 5000,'5000 ' . JText::_( 'FLEXI_ITEMS' ) );
		$lists['extdata'] = JHTML::_('select.genericlist', $extdata, 'extdata', 'size="1" class="inputbox"', 'value', 'text', $extlimit );

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

		if (FLEXI_FISH || FLEXI_J16GE) {
		//build languages filter
			$lists['filter_lang'] = flexicontent_html::buildlanguageslist('filter_lang', 'class="inputbox" onchange="submitform();" size="1" ', $filter_lang, 2);
		}
		
		// filter by item usage a specific file
		if ($fileid_to_itemids && count($fileid_to_itemids)) {
			$files_data = $model->getFileData(array_keys($fileid_to_itemids));
			$file_options = array();
			$file_options[] = JHTML::_('select.option',  '', ' -- '.JText::_( 'FLEXI_SELECT' ).' '.JText::_( 'FLEXI_FILE' ).' -- ' );
			foreach($files_data as $_file) {
				$file_options[] = JHTML::_('select.option', $_file->id, $_file->altname );
			}
			flexicontent_html::loadFramework('select2');
			$lists['filter_fileid'] = JHTML::_('select.genericlist', $file_options, 'filter_fileid', 'size="1" class="use_select2_lib" onchange="submitform();"', 'value', 'text', $filter_fileid );
		}
		
		//assign data to template
		$this->assignRef('db'				, $db);
		$this->assignRef('lists'		, $lists);
		$this->assignRef('rows'			, $rows);
		$this->assignRef('itemCats'	, $itemCats);
		$this->assignRef('extra_fields'	, $extraCols);
		if ($enable_translation_groups)  $this->assignRef('lang_assocs', $langAssocs);
		if (FLEXI_FISH || FLEXI_J16GE)   $this->assignRef('langs', $langs);
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('pagination'	, $pagination);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('CanOrder'		, $CanOrder);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanRights'	, $CanRights);
		$this->assignRef('unassociated'	, $unassociated);
		// filters
		$this->assignRef('filter_id'			, $filter_id);
		$this->assignRef('filter_state'		, $filter_state);
		$this->assignRef('filter_authors'	, $filter_authors);
		$this->assignRef('filter_type'		, $filter_type);
		$this->assignRef('filter_cats'		, $filter_cats);
		$this->assignRef('filter_subcats'	, $filter_subcats);
		$this->assignRef('filter_catsinstate'	, $filter_catsinstate);
		$this->assignRef('filter_order_type', $filter_order_type);
		$this->assignRef('filter_lang'		, $filter_lang);
		$this->assignRef('filter_fileid'	, $filter_fileid);
		
		$this->assignRef('inline_ss_max'	, $inline_ss_max);
		$this->assignRef('scope'			, $scope);
		$this->assignRef('search'			, $search);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'	, $startdate);
		$this->assignRef('enddate'		, $enddate);

		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
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
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');

		//add js functions
		$document->addScript('components/com_flexicontent/assets/js/copymove.js');

		//get vars
		$filter_order     = $app->getUserStateFromRequest( $option.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );
		
		if (FLEXI_J16GE) {
			$perms 	= FlexicontentHelperPerm::getPerm();
			$CanCats		= $perms->CanCats;
			$CanRights	= $perms->CanConfig;
			$CanOrder		= $perms->CanOrder;
			
		} else if (FLEXI_ACCESS) {
			$CanCats		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid)	: 1;
			$CanRights	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid)			: 1;
			$CanOrder		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid)			: 1;
			
		} else {
			$CanCats		= 1;
			$CanRights	= 1;
			$CanOrder		= 1;
		}

		//create the toolbar
		$copy_behaviour = JRequest::getVar('copy_behaviour','copy/move');
		if ($copy_behaviour == 'translate') {
			$page_title =  JText::_( 'FLEXI_TRANSLATE_ITEM' );
		} else {
			$page_title = JText::_( 'FLEXI_COPYMOVE_ITEM' );
		}
		JToolBarHelper::title( $page_title, 'itemadd' );
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
		$this->assignRef('CanOrder'		, $CanOrder);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanRights'	, $CanRights);

		parent::display($tpl);
	}

}
?>
