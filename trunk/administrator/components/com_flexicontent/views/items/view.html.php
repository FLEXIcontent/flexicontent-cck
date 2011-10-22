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
		global $mainframe, $globalcats;

		//initialise variables
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$option		= JRequest::getCmd( 'option' );
		$context	= 'com_flexicontent';
		$task		= JRequest::getVar('task', '');
		$cid		= JRequest::getVar('cid', array());
		$extlimit	= JRequest::getInt('extlimit', 100);
		
		if($task == 'copy') {
			$this->setLayout('copy');
			$this->_displayCopyMove($tpl, $cid);
			return;
		}

		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.calendar');

		//get vars
		$default_order_arr = array(""=>"i.ordering",  "lang"=>"lang", "type_name"=>"type_name",  "access"=>"i.access", "i.title"=>"i.title", "i.ordering"=>"i.ordering", "i.created"=>"i.created", "i.modified"=>"i.modified", "i.hits"=>"i.hits", "i.id"=>"i.id");
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$default_order = $cparams->get('items_manager_order', 'i.ordering');
		$default_order_dir = $cparams->get('items_manager_order_dir', 'ASC');
		
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
		$filter_subcats 	= $mainframe->getUserStateFromRequest( $context.'.items.filter_subcats',		'filter_subcats', 	1, 				'int' );
		
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.items.filter_order', 'filter_order', $default_order, 'cmd' );
		if ($filter_cats && $filter_order == 'i.ordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', 'catsordering' );
		} else if (!$filter_cats && $filter_order == 'catsordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', $default_order );
		}
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	$default_order_dir, 'word' );

		$filter_type 		= $mainframe->getUserStateFromRequest( $context.'.items.filter_type', 		'filter_type', 		0,		 		'int' );
		$filter_authors		= $mainframe->getUserStateFromRequest( $context.'.items.filter_authors', 	'filter_authors', 	0, 				'int' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $context.'.items.filter_state', 		'filter_state', 	'', 			'word' );
		if (FLEXI_FISH) {
			$filter_lang	 = $mainframe->getUserStateFromRequest( $context.'.items.filter_lang', 		'filter_lang', 		'', 			'cmd' );
		}
		$scope	 			= $mainframe->getUserStateFromRequest( $context.'.items.scope', 			'scope', 			1, 				'int' );
		$date	 			= $mainframe->getUserStateFromRequest( $context.'.items.date', 				'date', 			1, 				'int' );
		$startdate	 		= $mainframe->getUserStateFromRequest( $context.'.items.startdate', 		'startdate', 		'', 			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $mainframe->setUserState( $context.'.items.startdate', '' ); }
		$enddate	 		= $mainframe->getUserStateFromRequest( $context.'.items.enddate', 			'enddate', 			'', 			'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate	= $mainframe->setUserState( $context.'.items.enddate', '' ); }
		$filter_id 			= $mainframe->getUserStateFromRequest( $context.'.items.filter_id', 		'filter_id', 		'', 			'int' );
		$search 			= $mainframe->getUserStateFromRequest( $context.'.items.search', 			'search', 			'', 			'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

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
		if (FLEXI_FISH) {
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
		FLEXISubmenu('notvariable');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_ITEMS' ), 'items' );
		if ($CanAdd) {
			JToolBarHelper::addNew();
			if ($CanCopy) {
				JToolBarHelper::customX( 'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY/MOVE' );
			}
		}
		if ($CanEdit) {
			JToolBarHelper::editList();
		}
		if ($CanDelete) {
			JToolBarHelper::deleteList();
		}
		if (FLEXI_ACCESS && !$CanPublish) {
			JToolBarHelper::customX( 'approval', 'person2.png', 'person2_f2.png', 'FLEXI_APPROVAL_REQUEST' );
		}

		//Get data from the model
		$rows      		= & $this->get( 'Data');
		$pageNav 		= & $this->get( 'Pagination' );
		$types			= & $this->get( 'Typeslist' );
		$authors		= & $this->get( 'Authorslist' );
		$unassociated	= & $this->get( 'UnassociatedItems' );
		$status      	= & $this->get( 'ExtdataStatus');
		
		if (FLEXI_FISH) {
			$langs	= & $this->get( 'Languages' );
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
				
		// build the include subcats boolean list
		$lists['filter_subcats'] = JHTML::_('select.booleanlist',  'filter_subcats', 'class="inputbox"', $filter_subcats );
		
		// build the categories select list for filter
		$lists['filter_cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="submitform( );"', true);

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
		$extdata[] = JHTML::_('select.option', 5, 	'5 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 30, 	'30 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 50, 	'50 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 100, '100 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 200, '200 ' . JText::_( 'FLEXI_ITEMS' ) );
		$extdata[] = JHTML::_('select.option', 300, '300 ' . JText::_( 'FLEXI_ITEMS' ) );
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

		if (FLEXI_FISH) {
		//build languages filter
		$lists['filter_lang'] = flexicontent_html::buildlanguageslist('filter_lang', 'class="inputbox" onchange="submitform();"', $filter_lang, 2);
		}
		
		//assign data to template
		$this->assignRef('db'  			, $db);
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		if (FLEXI_FISH) {
			$this->assignRef('langs'    , $langs);
		}
		$this->assignRef('cid'      	, $cid);
		$this->assignRef('pageNav' 		, $pageNav);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('user'			, $user);
		$this->assignRef('CanOrder'		, $CanOrder);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanRights'	, $CanRights);
		$this->assignRef('unassociated'	, $unassociated);
		// filters
		$this->assignRef('filter_id'		, $filter_id);
		$this->assignRef('filter_state'		, $filter_state);
		$this->assignRef('filter_authors'	, $filter_authors);
		$this->assignRef('filter_type'		, $filter_type);
		$this->assignRef('filter_cats'		, $filter_cats);
		$this->assignRef('filter_subcats'	, $filter_subcats);
		$this->assignRef('filter_lang'		, $filter_lang);
		$this->assignRef('scope'			, $scope);
		$this->assignRef('search'			, $search);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'		, $startdate);
		$this->assignRef('enddate'			, $enddate);

		parent::display($tpl);
	}

	function _displayCopyMove($tpl = null, $cid)
	{
		global $mainframe, $globalcats;

		//initialise variables
		$user 		= & JFactory::getUser();
		$document	= & JFactory::getDocument();
		$context	= 'com_flexicontent';
		
		JHTML::_('behavior.tooltip');

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		//add js functions
		$document->addScript('components/com_flexicontent/assets/js/copymove.js');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $context.'.items.filter_order', 		'filter_order', 	'', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $context.'.items.filter_order_Dir',	'filter_order_Dir',	'', 		'word' );

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
			$CanRights	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid) : 1;
			$CanOrder	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid) : 1;
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
			$CanRights		= 1;
			$CanOrder		= 1;
		}

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent');
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', true);
		if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types');
		if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
		if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
		if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
		if ($CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
		if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
		if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_COPYMOVE_ITEM' ), 'itemadd' );
		JToolBarHelper::save('copymove');
		JToolBarHelper::cancel();

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
