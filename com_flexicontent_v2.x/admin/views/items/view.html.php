<?php
/**
 * @version 1.5 stable $Id: view.html.php 268 2010-06-11 08:45:12Z emmanuel.danan $
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
	function display($tpl = null) {
		global $globalcats;
		$mainframe = &JFactory::getApplication();

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
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $mainframe->setUserState( $option.'.items.startdate', '' ); }
		$enddate	 		= $mainframe->getUserStateFromRequest( $context.'.items.enddate', 			'enddate', 			'', 			'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate	= $mainframe->setUserState( $option.'.items.enddate', '' ); }
		$filter_id 			= $mainframe->getUserStateFromRequest( $context.'.items.$filter_id', 		'filter_id', 		'', 			'int' );
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
		
		$js .="\n$$('li#toolbar-new a.toolbar').set('onclick', 'javascript:;');\n";
		$js .="$$('li#toolbar-new a.toolbar').set('href', 'index.php?option=com_flexicontent&view=types&format=raw');\n";
		$js .="$$('li#toolbar-new a.toolbar').set('rel', '{handler: \'iframe\', size: {x: 400, y: 400}, onClose: function() {}}');\n";
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		$permission = FlexicontentHelperPerm::getPerm();
		FLEXIcontentSubmenu('notvariable');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_ITEMS' ), 'items' );
		if ($permission->CanAdd) {
			JToolBarHelper::addNew('items.add');
			if ($permission->CanCopy) {
				JToolBarHelper::customX( 'items.copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY_MOVE' );
			}
		}
		if ($permission->CanEdit) {
			JToolBarHelper::editList('items.edit');
		}
		if ($permission->CanDelete) {
			JToolBarHelper::deleteList('Are you sure?', 'items.remove');
		}
		if (!$permission->CanPublish) {
			JToolBarHelper::customX( 'items.approval', 'person2.png', 'person2_f2.png', 'FLEXI_APPROVAL_REQUEST' );
		}
		if(JAccess::check($user->id, 'core.admin', 'root.1') || $permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');

		//Get data from the model
		$rows      		= & $this->get( 'Data');
		$pageNav 		= & $this->get( 'Pagination' );
		$types			= & $this->get( 'Typeslist' );
		$authors		= & $this->get( 'Authorslist' );
		$unassociated		= & $this->get( 'UnassociatedItems' );
		$status      		= & $this->get( 'ExtdataStatus');
		
		JHtml::_('behavior.modal', 'li#toolbar-new a.toolbar');
		
		$categories = $globalcats?$globalcats:array();

		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_STATE' ) );
		$state[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',  'RV', JText::_( 'FLEXI_REVISED_VER' ) );

		$lists['filter_state'] = JHTML::_('select.genericlist',   $state, 'filter_state', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_state );

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

		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'9',  'maxlength'=>'20'));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'9',  'maxlength'=>'20'));

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
		$this->assignRef('permission'		, $permission);
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

	function _displayCopyMove($tpl = null, $cid) {
		global $globalcats;
		$mainframe = &JFactory::getApplication();

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
		
		$permission = FlexicontentHelperPerm::getPerm();
		//Create Submenu
		FLEXIcontentSubmenu();

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_COPYMOVE_ITEM' ), 'itemadd' );
		JToolBarHelper::save('items.copymove');
		JToolBarHelper::cancel('items.cancel');

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
		$this->assignRef('permission'		, $permission);

		parent::display($tpl);
	}

}
?>
