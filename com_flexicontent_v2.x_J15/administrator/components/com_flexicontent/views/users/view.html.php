<?php
/**
* @version		$Id$
* @package		Joomla
* @subpackage	Users
* @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * HTML View class for the Users component
 *
 * @static
 * @package		Joomla
 * @subpackage	Users
 * @since 1.0
 */
class FlexicontentViewUsers extends JViewLegacy
{

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		$app      = JFactory::getApplication();
		$db				= JFactory::getDBO();
		$document	= JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$user     = JFactory::getUser();
		$acl      = JFactory::getACL();
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order      = $app->getUserStateFromRequest( "$option.authors.filter_order",		'filter_order',		'a.name',	'cmd' );
		$filter_order_Dir  = $app->getUserStateFromRequest( "$option.authors.filter_order_Dir",	'filter_order_Dir',	'',			'word' );
		
		$filter_itemscount = $app->getUserStateFromRequest( "$option.authors.filter_itemscount",		'filter_itemscount', 		'',			'int' );
		
		$filter_usergrp    = $app->getUserStateFromRequest( "$option.authors.filter_usergrp",		'filter_usergrp', 		'',			'string' );
		$filter_logged  = $app->getUserStateFromRequest( "$option.authors.filter_logged",		'filter_logged', 	'',			'int' );
		
		$date       = $app->getUserStateFromRequest( "$option.authors.date", 				'date', 			1, 				'int' );
		$startdate  = $app->getUserStateFromRequest( "$option.authors.startdate", 		'startdate', 		'', 			'cmd' );
		$enddate    = $app->getUserStateFromRequest( "$option.authors.enddate", 			'enddate', 			'', 			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $app->setUserState( "$option.authors.startdate", '' ); }
		if ($enddate   == JText::_('FLEXI_TO'))   { $enddate	= $app->setUserState( "$option.authors.enddate", '' ); }
		
		$filter_id  = $app->getUserStateFromRequest( "$option.authors.filter_id", 		'filter_id', 		'', 			'int' );
		$search     = $app->getUserStateFromRequest( "$option.authors.search",			'search', 			'',			'string' );
		if (strpos($search, '"') !== false) {
			$search = str_replace(array('=', '<'), '', $search);
		}
		$search = JString::strtolower($search);
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$notice_author_with_items_only	= $app->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );
			if (!$notice_author_with_items_only) {
				$app->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				$app->enqueueMessage(JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'message');
			}
		}

		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');


		$js = "window.addEvent('domready', function(){";
		if ($filter_usergrp) {
			$js .= "$$('.col_usergrp').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_logged) {
			$js .= "$$('.col_logged').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_id) {
			$js .= "$$('.col_id').each(function(el){ el.addClass('yellow'); });";
		}
		if ($startdate || $enddate) {
			if ($date == 1) {
				$js .= "$$('.col_registered').each(function(el){ el.addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "$$('.col_visited').each(function(el){ el.addClass('yellow'); });";
			}
		}
		if ($filter_itemscount) {
			$js .= "$$('.col_itemscount').each(function(el){ el.addClass('yellow'); });";
		} else {
			$js .= "$$('.col_itemscount').each(function(el){ el.removeClass('yellow'); });";
		}
		if ($search) {
			$js .= "$$('.col_title').each(function(el){ el.addClass('yellow'); });";
		} else {
			$js .= "$$('.col_title').each(function(el){ el.removeClass('yellow'); });";
		}
		$js .= "});";
		$document->addScriptDeclaration($js);

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanAuthors');
		
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_AUTHORS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'authors' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		$contrl = FLEXI_J16GE ? "users." : "";
		JToolBarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );
		
		//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
		// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
		$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
		$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
		$btn_task    = $contrl.'remove';
		$extra_js    = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		
		JToolBarHelper::addNew($contrl.'add');
		JToolBarHelper::editList($contrl.'edit');
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		if (!FLEXI_J16GE)
			JToolBarHelper::help( 'screen.users' );
		else
			JToolBarHelper::help('JHELP_USERS_USER_MANAGER');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

		$limit		= $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
		$limitstart = $app->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );

		$where = array(); $having = array(); $extra_joins = array();
		if (isset( $search ) && $search!= '')
		{
			$searchEscaped = FLEXI_J16GE ? $db->escape( $search, true ) : $db->getEscaped( $search, true );
			$searchEscaped = $db->Quote( '%'.$searchEscaped.'%', false );
			$where[] = 'a.username LIKE '.$searchEscaped.' OR a.email LIKE '.$searchEscaped.' OR a.name LIKE '.$searchEscaped;
		}

		// visited date filtering
		if ($date == 1) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.registerDate >= ' . $db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.registerDate <= ' . $db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.registerDate >= ' . $db->Quote($startdate) . ' AND a.registerDate <= ' . $db->Quote($enddate) . ' )';
			}
		}
		if ($date == 2) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.lastvisitDate >= ' . $db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.lastvisitDate <= ' . $db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.lastvisitDate >= ' . $db->Quote($startdate) . ' AND a.lastvisitDate <= ' . $db->Quote($enddate) . ' )';
			}
		}
		
		
		if ($filter_id)
		{
			$where[] = 'a.id = '.$filter_id;
		}
		
		if ( $filter_usergrp )
		{
			if ( !FLEXI_J16GE ) {
				if ( $filter_usergrp == 'Public Frontend' )     $where[] = ' a.usertype = \'Registered\' OR a.usertype = \'Author\' OR a.usertype = \'Editor\' OR a.usertype = \'Publisher\' ';
				else if ( $filter_usergrp == 'Public Backend' ) $where[] = 'a.usertype = \'Manager\' OR a.usertype = \'Administrator\' OR a.usertype = \'Super Administrator\' ';
				else                                         $where[] = 'a.usertype = LOWER( '.$db->Quote($filter_usergrp).' ) ';
			} else {
				// Added as right join, see query bellow
				$extra_joins[] = ' RIGHT JOIN #__user_usergroup_map AS ug ON ug.user_id = a.id AND ug.group_id='.$filter_usergrp;
			}
		}
		if ( $filter_logged == 1 )
		{
			$where[] = 's.userid IS NOT NULL';
		}
		else if ($filter_logged == 2)
		{
			$where[] = 's.userid IS NULL';
		}

		if ( !$filter_itemscount )
		{
			$having[] = ' itemscount > 0 ';
		} else if ( $filter_itemscount==1 )
		{
			$having[] = ' itemscount = 0 ';
		}

		// exclude any child group id's for this user, this applicable only in J1.5, and not for J16/J1.7/J2.5+
		if (!FLEXI_J16GE) {
			$pgids = $acl->get_group_children( $user->get('gid'), 'ARO', 'RECURSE' );

			if (is_array( $pgids ) && count( $pgids ) > 0)
			{
				JArrayHelper::toInteger($pgids);
				$where[] = 'a.gid NOT IN (' . implode( ',', $pgids ) . ')';
			}
		}
		
		// ensure filter_order has a valid value.
		$allowed_order_cols = array('a.name', 'itemscount', 'a.username', 'loggedin',
			'a.block', 'groupname','a.email', 'a.lastvisitDate', 'a.lastvisitDate', 'a.id');
		if (!in_array($filter_order, $allowed_order_cols)) {
			$filter_order = 'a.name';
		}
		
		if (!in_array(strtoupper($filter_order_Dir), array('ASC', 'DESC'))) {
			$filter_order_Dir = '';
		}
		
		$orderby = ' ORDER BY '. $filter_order .' '. $filter_order_Dir;
		$where = ( count( $where ) ? ' WHERE (' . implode( ') AND (', $where ) . ')' : '' );
		$having = ( count( $having ) ? ' HAVING (' . implode( ') AND (', $having ) . ')' : '' );
		$extra_joins = ( count( $extra_joins ) ? implode( ' ', $extra_joins ) : '' );
		
		// Do main query to get the authors
		$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.*, s.userid IS NOT NULL AS loggedin'
			. ', (SELECT COUNT(*) FROM #__content AS i WHERE i.created_by = a.id) AS itemscount '
			. (!FLEXI_J16GE ? ', g.name AS groupname' : '')
			. ' FROM #__users AS a'
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_aro AS aro ON aro.value = a.id' : '')
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_groups_aro_map AS gm ON gm.aro_id = aro.id' : '')
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_aro_groups AS g ON g.id = gm.group_id' : '')
			. ' LEFT JOIN #__flexicontent_authors_ext AS ue ON a.id = ue.user_id'
			. ' LEFT JOIN #__session AS s ON s.userid = a.id'
			. $extra_joins
			. $where
			//. ' GROUP BY a.id'
			. $having
			. $orderby
		;
		$db->setQuery( $query, $limitstart, $limit );
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows = $db->loadObjectList();
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		if ($db->getErrorMsg())	echo $db->getErrorMsg();
		
		// Get total and create pagination controls
		$db->setQuery("SELECT FOUND_ROWS()");
		$total = $db->loadResult();
		if (!$total) echo $db->getErrorMsg();
		
		// Create pagination
		jimport('joomla.html.pagination');
		$pagination = new JPagination( $total, $limitstart, $limit );
		
		// DB Query (J2.5 only) to get -mulitple- user group ids for all authors,
		// this is needed because user-To-usergoup mapping are stored in separate table
		if (FLEXI_J16GE) {
			$user_ids = array();
			foreach ($rows as $row) {
				$row->usergroups = array();
				$user_ids[] = $row->id;
			}
			$query = 'SELECT user_id, group_id FROM #__user_usergroup_map ' . (count($user_ids) ? 'WHERE user_id IN ('.implode(',',$user_ids).')'  :  '');
			$db->setQuery( $query );
			$ugdata_arr = $db->loadObjectList();
			foreach ($ugdata_arr as $ugdata) $usergroups[$ugdata->user_id][] = $ugdata->group_id;
			foreach ($rows as $row) $row->usergroups = $usergroups[$row->id];
		}
		
		// get list of Groups for dropdown filter
		if (FLEXI_J16GE) {
			$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
		} else {
			$query = 'SELECT *, name AS value, name AS text FROM #__core_acl_aro_groups WHERE name != "ROOT" AND name != "USERS"';
		}
		$db->setQuery( $query );
		$usergroups = $db->loadObjectList('id');
		$types[]		= JHTML::_('select.option',  '', '- '. JText::_( 'Select Group' ) .' -' );
		foreach( $usergroups as $ugrp )
		{
			$types[]	= JHTML::_('select.option',  $ugrp->value, JText::_( $ugrp->text ) );
		}
		
		$itemscount_options[] = JHTML::_('select.option',  '', '- '. JText::_( 'One or more' ) .' -');
		$itemscount_options[] = JHTML::_('select.option',  1, JText::_( 'None' ) );
		$itemscount_options[] = JHTML::_('select.option',  2, JText::_( 'Any number' ) );
		$lists['filter_itemscount'] = JHTML::_('select.genericlist',   $itemscount_options, 'filter_itemscount', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_itemscount" );
		
		$lists['filter_usergrp'] 	= JHTML::_('select.genericlist',   $types, 'filter_usergrp', 'class="inputbox" style="width:auto;" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_usergrp" );

		// get list of Log Status for dropdown filter
		$logged[] = JHTML::_('select.option',  '', '- '. JText::_( 'Select Log Status' ) .' -');
		$logged[] = JHTML::_('select.option',  1, JText::_( 'Logged In' ) );
		$logged[] = JHTML::_('select.option',  2, JText::_( 'Logged Out' ) );
		$lists['filter_logged'] = JHTML::_('select.genericlist',   $logged, 'filter_logged', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_logged" );

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']		= $filter_order;

		// build dates option list
		$dates = array();
		$dates[] = JHTML::_('select.option',  '1', JText::_( 'Registered' ) );
		$dates[] = JHTML::_('select.option',  '2', JText::_( 'Last Visit' ) );
		$lists['date'] = JHTML::_('select.radiolist', $dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );
		
		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		
		// search filter
		$lists['search']= $search;
		// search id
		$lists['filter_id'] = $filter_id;

		$this->assignRef('lists',		$lists);
		$this->assignRef('items',		$rows);
		$this->assignRef('usergroups',	$usergroups);
		$this->assignRef('pagination',	$pagination);

		// filters
		$this->assignRef('filter_id'		, $filter_id);
		$this->assignRef('filter_itemscount'		, $filter_itemscount);
		$this->assignRef('filter_usergrp'		, $filter_usergrp);
		$this->assignRef('filter_logged'	, $filter_logged);
		$this->assignRef('search'			, $search);
		$this->assignRef('filter_id'			, $filter_id);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'		, $startdate);
		$this->assignRef('enddate'			, $enddate);
		
		parent::display($tpl);
	}
}
