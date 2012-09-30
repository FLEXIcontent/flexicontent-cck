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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JView {

	function display($tpl = null)
	{
		global $globalcats;
		$mainframe = JFactory::getApplication();
		$cparams   = JComponentHelper::getParams( 'com_flexicontent' );

		//initialise variables
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd( 'option' );
		$task     = JRequest::getVar('task', '');
		$cid      = JRequest::getVar('cid', array());
		$extlimit = JRequest::getInt('extlimit', 100);
		
		if($task == 'copy') {
			$this->setLayout('copy');
			$this->_displayCopyMove($tpl, $cid);
			return;
		}

		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.calendar');

		//get vars
		$default_order_arr = array(""=>"i.ordering",  "lang"=>"lang", "type_name"=>"type_name",  "access"=>"i.access", "i.title"=>"i.title", "i.ordering"=>"i.ordering", "i.created"=>"i.created", "i.modified"=>"i.modified", "i.hits"=>"i.hits", "i.id"=>"i.id");
		$default_order = $cparams->get('items_manager_order', 'i.ordering');
		$default_order_dir = $cparams->get('items_manager_order_dir', 'ASC');
		
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
		$filter_subcats 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_subcats',		'filter_subcats', 	1, 				'int' );
		
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.items.filter_order', 'filter_order', $default_order, 'cmd' );
		if ($filter_cats && $filter_order == 'i.ordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', 'catsordering' );
		} else if (!$filter_cats && $filter_order == 'catsordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', $default_order );
		}
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	$default_order_dir, 'word' );

		$filter_type			= $mainframe->getUserStateFromRequest( $option.'.items.filter_type', 		'filter_type', 		0,		 		'int' );
		$filter_authors		= $mainframe->getUserStateFromRequest( $option.'.items.filter_authors', 	'filter_authors', 	0, 				'int' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_state', 		'filter_state', 	'', 			'word' );
		$filter_stategrp	= $mainframe->getUserStateFromRequest( $option.'.items.filter_stategrp',	'filter_stategrp', 	'', 			'word' );
		if (FLEXI_FISH || FLEXI_J16GE) {
			$filter_lang	 = $mainframe->getUserStateFromRequest( $option.'.items.filter_lang', 		'filter_lang', 		'', 			'cmd' );
		}
		$scope	 			= $mainframe->getUserStateFromRequest( $option.'.items.scope', 			'scope', 			1, 				'int' );
		$date	 				= $mainframe->getUserStateFromRequest( $option.'.items.date', 			'date', 			1, 				'int' );
		$startdate	 	= $mainframe->getUserStateFromRequest( $option.'.items.startdate', 	'startdate', 		'', 			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $mainframe->setUserState( $option.'.items.startdate', '' ); }
		$enddate	 		= $mainframe->getUserStateFromRequest( $option.'.items.enddate', 		'enddate', 			'', 			'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate	= $mainframe->setUserState( $option.'.items.enddate', '' ); }
		$filter_id 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_id', 	'filter_id', 		'', 			'int' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.items.search', 		'search', 			'', 			'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		$inline_ss_max = 30;
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$limit = $mainframe->getUserStateFromRequest( $option.'.items.limit',	'limit',	0, 'int' );
			$notice_iss_disabled = $mainframe->getUserStateFromRequest( $option.'.items.notice_iss_disabled',	'notice_iss_disabled',	0, 'int' );
			if (!$notice_iss_disabled && $limit > $inline_ss_max) {
				$mainframe->setUserState( $option.'.items.notice_iss_disabled', 1 );
				$mainframe->enqueueMessage(JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $inline_ss_max), 'notice');
				$show_turn_off_notice = 1;
			}
			
			$notice_define_item_order = 30;
			$notice_define_item_order = $mainframe->getUserStateFromRequest( $option.'.items.notice_define_item_order',	'notice_define_item_order',	0, 'int' );
			if (!$notice_define_item_order) {
				$mainframe->setUserState( $option.'.items.notice_define_item_order', 1 );
				$mainframe->enqueueMessage(JText::_('FLEXI_DEFINE_ITEM_ORDER_FILTER_BY_CAT'), 'notice');
				$show_turn_off_notice = 1;
			}
			if (!empty($show_turn_off_notice))
				$mainframe->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'notice');
		}
		
		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/stateselector.js' );

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
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		if (FLEXI_J16GE) {
			$permission 	= FlexicontentHelperPerm::getPerm();
			$CanAdd				= $permission->CanAdd;
			
			$CanEdit			= $permission->CanEdit;
			$CanPublish		= $permission->CanPublish;
			$CanDelete		= $permission->CanDelete;
			
			$CanEditOwn			= $permission->CanEditOwn;
			$CanPublishOwn	= $permission->CanPublishOwn;
			$CanDeleteOwn		= $permission->CanDeleteOwn;
			
			$CanCats		= $permission->CanCats;
			$CanRights	= $permission->CanConfig;
			$CanOrder		= $permission->CanOrder;
			$CanCopy		= $permission->CanCopy;
			$CanArchives= $permission->CanArchives;
			
		} else if (FLEXI_ACCESS) {
			$CanAdd			= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid) || FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) : 1;
			
			$CanEdit		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'edit', 'users', $user->gmid)		: 1;
			$CanPublish	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid)	: 1;
			$CanDelete	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'delete', 'users', $user->gmid)	: 1;
			
			$CanEditOwn			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'editown', 'users', $user->gmid)			: 1;
			$CanPublishOwn	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)	: 1;
			$CanDeleteOwn		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'deleteown', 'users', $user->gmid)		: 1;
			
			$CanCats		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid)	: 1;
			$CanRights	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid)			: 1;
			$CanOrder		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid)			: 1;
			$CanCopy		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1;
			$CanArchives= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid)		: 1;
			
		} else {
			$CanAdd			= 1;
			$CanEdit		= 1;
			$CanPublish	= 1;
			$CanDelete	= 1;
			$CanCats		= 1;
			$CanRights	= 1;
			$CanOrder		= 1;
			$CanCopy		= 1;
			$CanArchives= 1;
		}
		FLEXISubmenu('notvariable');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_ITEMS' ), 'items' );
		$toolbar =&JToolBar::getInstance('toolbar');
		
		if ( $CanPublish || $CanPublishOwn ) {
			// Implementation of multiple-item state selector
			$ctrl_task = FLEXI_J16GE ? '&task=items.selectstate' : '&controller=items&task=selectstate';
			$toolbar->appendButton('Popup', 'publish', JText::_('FLEXI_CHANGE_STATE'), JURI::base().'index.php?option=com_flexicontent'.$ctrl_task.'&format=raw', 840, 200);
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
		}
		
		if ($CanAdd) {
			//$ctrl_task = FLEXI_J16GE ? 'items.add' : 'add';
			//$js .="\n$$('li#toolbar-new a.toolbar').set('onclick', 'javascript:;');\n";
			//$js .="$$('li#toolbar-new a.toolbar').set('href', 'index.php?option=com_flexicontent&view=types&format=raw');\n";
			//$js .="$$('li#toolbar-new a.toolbar').set('rel', '{handler: \'iframe\', size: {x: 400, y: 400}, onClose: function() {}}');\n";
			//$js .= "});";
			//$document->addScriptDeclaration($js);
			//JToolBarHelper::addNew($ctrl_task);
			//JHtml::_('behavior.modal', 'li#toolbar-new a.toolbar');
			
			$toolbar->appendButton('Popup', 'new',  JText::_('FLEXI_NEW'), JURI::base().'index.php?option=com_flexicontent&view=types&format=raw', 600, 240);
			
			if ($CanCopy) {
				$ctrl_task = FLEXI_J16GE ? 'items.copy' : 'copy';
				JToolBarHelper::customX( $ctrl_task, 'copy.png', 'copy_f2.png', 'FLEXI_COPY_MOVE' );
				$enable_translation_groups = JComponentHelper::getParams( 'com_flexicontent' )->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
				if ($enable_translation_groups) {
					JToolBarHelper::customX( 'translate', 'translate', 'translate', 'FLEXI_TRANSLATE' );
				}
			}
		}
		if ($CanEdit || $CanEditOwn) {
			$ctrl_task = FLEXI_J16GE ? 'items.edit' : 'edit';
			JToolBarHelper::editList($ctrl_task);
		}
		if ( ($CanDelete || $CanDeleteOwn) && $filter_stategrp == 'trashed' ) {
			$ctrl_task = FLEXI_J16GE ? 'items.remove' : 'remove';
			JToolBarHelper::deleteList('Are you sure?', $ctrl_task);
		}
		
		if ( (FLEXI_ACCESS || FLEXI_J16GE) && !$CanPublish && !$CanPublishOwn) {
			$ctrl_task = FLEXI_J16GE ? 'items.approval' : 'approval';
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
			JToolBarHelper::customX( $ctrl_task, 'person2.png', 'person2_f2.png', 'FLEXI_APPROVAL_REQUEST' );
		}
		
		if(FLEXI_J16GE && $permission->CanConfig) {
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		JToolBarHelper::spacer();
		JToolBarHelper::spacer();

		//Get data from the model
		$rows     	= & $this->get( 'Data');
		$pageNav 		= & $this->get( 'Pagination' );
		$types			= & $this->get( 'Typeslist' );
		$authors		= & $this->get( 'Authorslist' );
		$unassociated	= & $this->get( 'UnassociatedItems' );
		$status				= & $this->get( 'ExtdataStatus' );
		$extra_fields	= & $this->get( 'ItemList_ExtraFields' );
		$this->get( 'ItemList_ExtraFieldValues' );
		
		if (FLEXI_FISH || FLEXI_J16GE) {
			$langs	= & FLEXIUtilities::getLanguages('code');
		}
		$categories = $globalcats?$globalcats:array();
		
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
			$sgn[''] = JText::_( 'FLEXI_GRP_NORMAL' );
			if ($CanDelete || $CanDeleteOwn)
				$sgn['trashed']  = JText::_( 'FLEXI_GRP_TRASHED' );
			if ($CanArchives)
				$sgn['archived'] = JText::_( 'FLEXI_GRP_ARCHIVED' );
			$sgn['orphan']      = JText::_( 'FLEXI_GRP_ORPHAN' );
			$sgn['all']      = JText::_( 'FLEXI_GRP_ALL' );
			
			$stategroups = array();
			foreach ($sgn as $i => $v) {
				if ($filter_stategrp == $i) $v = "<span class='flexi_radiotab highlight'>".$v."</span>";
				else                        $v = "<span class='flexi_radiotab downlight'>".$v."</span>";
				$stategroups[] = JHTML::_('select.option', $i, $v);
			}
			$lists['filter_stategrp'] = JHTML::_('select.radiolist', $stategroups, 'filter_stategrp', 'size="1" class="inputbox" onchange="submitform();"', 'value', 'text', $filter_stategrp );
		}
		
		// build the include subcats boolean list
		$lists['filter_subcats'] = JHTML::_('select.booleanlist',  'filter_subcats', 'class="inputbox"', $filter_subcats );
		
		// build the categories select list for filter
		$lists['filter_cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="submitform( );"', $check_published=true, $check_perms=false);

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"');

		//build authors select list
		$lists['filter_authors'] = flexicontent_html::buildauthorsselect($authors, 'filter_authors', $filter_authors, true, 'class="inputbox" size="1" onchange="submitform( );"');

		//search filter
		$scopes = array();
		$scopes[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_TITLE' ) );
		$scopes[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_INTROTEXT' ) );
		$scopes[] = JHTML::_('select.option', '4', JText::_( 'FLEXI_INDEXED_CONTENT' ) );
		$lists['scope'] = JHTML::_('select.radiolist', $scopes, 'scope', 'size="1" class="inputbox"', 'value', 'text', $scope );

		// build dates option list
		$dates = array();
		$dates[] = JHTML::_('select.option',  '1', JText::_( 'FLEXI_CREATED' ) );
		$dates[] = JHTML::_('select.option',  '2', JText::_( 'FLEXI_REVISED' ) );
		$lists['date'] = JHTML::_('select.radiolist', $dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );

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
		if ($filter_cats == '' || $filter_cats == 0)
		{
			$ordering = ($lists['order'] == 'i.ordering');
		} else {
			$ordering = ($lists['order'] == 'catsordering');
		}

		if (FLEXI_FISH || FLEXI_J16GE) {
		//build languages filter
			$lists['filter_lang'] = flexicontent_html::buildlanguageslist('filter_lang', 'class="inputbox" onchange="submitform();"', $filter_lang, 2);
		}
		
		//assign data to template
		$this->assignRef('db'				, $db);
		$this->assignRef('lists'		, $lists);
		$this->assignRef('rows'			, $rows);
		$this->assignRef('extra_fields' , $extra_fields);
		if (FLEXI_FISH || FLEXI_J16GE) {
			$this->assignRef('langs'    , $langs);
		}
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('pageNav' 		, $pageNav);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('user'				, $user);
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
		$this->assignRef('filter_lang'		, $filter_lang);
		$this->assignRef('inline_ss_max'	, $inline_ss_max);
		$this->assignRef('scope'			, $scope);
		$this->assignRef('search'			, $search);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'	, $startdate);
		$this->assignRef('enddate'		, $enddate);

		parent::display($tpl);
	}

	function _displayCopyMove($tpl = null, $cid)
	{
		global $globalcats;
		$mainframe = &JFactory::getApplication();

		//initialise variables
		$user 		= & JFactory::getUser();
		$document	= & JFactory::getDocument();
		$option		= JRequest::getCmd( 'option' );
		
		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		//add js functions
		$document->addScript('components/com_flexicontent/assets/js/copymove.js');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );
		
		if (FLEXI_J16GE) {
			$permission 	= FlexicontentHelperPerm::getPerm();
			$CanCats		= $permission->CanCats;
			$CanRights	= $permission->CanConfig;
			$CanOrder		= $permission->CanOrder;
			
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
		$rows      	= & $this->get( 'Data');
		$categories = $globalcats;
		
		// build the main category select list
		$lists['maincat'] = flexicontent_cats::buildcatselect($categories, 'maincat', '', 0, 'class="inputbox" size="10"', false, false);
		// build the secondary categories select list
		$lists['seccats'] = flexicontent_cats::buildcatselect($categories, 'seccats[]', '', 0, 'class="inputbox" multiple="multiple" size="10"', false, false);

		//assign data to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('user'			, $user);
		$this->assignRef('CanOrder'		, $CanOrder);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanRights'	, $CanRights);

		parent::display($tpl);
	}

}
?>
